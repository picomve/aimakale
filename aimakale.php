<?php
/**
 * Plugin Name: AI makale
 * Description: Düzenli aralıklarıla makale yazıp taslak olarak kaydeden wp eklentisi
 * Author: Picomve
 * Version: 3.2
 */

// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) exit;

// --- ENV YÜKLEME ---
function aimakale_env_yukle() {
    $env_dosyasi = plugin_dir_path( __FILE__ ) . '.env';
    if ( ! file_exists( $env_dosyasi ) ) $env_dosyasi = plugin_dir_path( __FILE__ ) . '.env.local';
    
    if ( file_exists( $env_dosyasi ) ) {
        $satirlar = file( $env_dosyasi, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        foreach ( $satirlar as $satir ) {
            if ( strpos( trim( $satir ), '#' ) === 0 ) continue;
            list( $anahtar, $deger ) = explode( '=', $satir, 2 );
            if ( ! defined( trim($anahtar) ) ) define( trim($anahtar), trim($deger) );
        }
    }
}
aimakale_env_yukle();

// Varsayılanlar
if ( ! defined( 'GEMINI_API_KEY' ) ) define( 'GEMINI_API_KEY', '' );
if ( ! defined( 'GEMINI_MODEL' ) )   define( 'GEMINI_MODEL', 'gemini-1.5-flash' );
define( 'KONU_DOSYASI', plugin_dir_path( __FILE__ ) . 'konular.txt' );
define( 'KONU_DB', plugin_dir_path( __FILE__ ) . 'konular.sqlite' );

function aimakale_db_conn() {
    static $db = null;
    if ( $db !== null ) {
        return $db;
    }

    try {
        $db = new PDO( 'sqlite:' . KONU_DB );
        $db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        $db->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
        $db->exec( 'PRAGMA journal_mode = WAL;' );
        $db->exec( 'PRAGMA foreign_keys = ON;' );
        $db->exec( 'CREATE TABLE IF NOT EXISTS topics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            topic TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )' );
    } catch ( Exception $e ) {
        return null;
    }

    if ( file_exists( KONU_DOSYASI ) ) {
        $satirlar = file( KONU_DOSYASI, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        $count = (int) $db->query( 'SELECT COUNT(*) FROM topics' )->fetchColumn();
        if ( $count === 0 && ! empty( $satirlar ) ) {
            $stmt = $db->prepare( 'INSERT INTO topics (topic) VALUES (:topic)' );
            foreach ( $satirlar as $satir ) {
                $topic = trim( $satir );
                if ( $topic === '' ) {
                    continue;
                }
                $stmt->bindValue( ':topic', $topic, PDO::PARAM_STR );
                $stmt->execute();
            }
        }
    }

    return $db;
}

function aimakale_db_get_topics() {
    $db = aimakale_db_conn();
    if ( ! $db ) {
        return [];
    }
    $stmt = $db->query( 'SELECT id, topic FROM topics ORDER BY id ASC' );
    return $stmt ? $stmt->fetchAll() : [];
}

function aimakale_db_add_topic( $topic ) {
    $topic = trim( $topic );
    if ( $topic === '' ) {
        return false;
    }
    $db = aimakale_db_conn();
    if ( ! $db ) {
        return false;
    }
    $stmt = $db->prepare( 'INSERT INTO topics (topic) VALUES (:topic)' );
    $stmt->bindValue( ':topic', $topic, PDO::PARAM_STR );
    return $stmt->execute();
}

function aimakale_db_update_topic( $id, $topic ) {
    $topic = trim( $topic );
    if ( $topic === '' ) {
        return aimakale_db_delete_topic( $id );
    }
    $db = aimakale_db_conn();
    if ( ! $db ) {
        return false;
    }
    $stmt = $db->prepare( 'UPDATE topics SET topic = :topic WHERE id = :id' );
    $stmt->bindValue( ':topic', $topic, PDO::PARAM_STR );
    $stmt->bindValue( ':id', (int) $id, PDO::PARAM_INT );
    return $stmt->execute();
}

function aimakale_db_delete_topic( $id ) {
    $db = aimakale_db_conn();
    if ( ! $db ) {
        return false;
    }
    $stmt = $db->prepare( 'DELETE FROM topics WHERE id = :id' );
    $stmt->bindValue( ':id', (int) $id, PDO::PARAM_INT );
    return $stmt->execute();
}

function aimakale_db_consume_topic() {
    $db = aimakale_db_conn();
    if ( ! $db ) {
        return null;
    }
    $stmt = $db->query( 'SELECT id, topic FROM topics ORDER BY id ASC LIMIT 1' );
    $row = $stmt ? $stmt->fetch() : false;
    if ( ! $row || empty( $row['topic'] ) ) {
        return null;
    }
    $delete = $db->prepare( 'DELETE FROM topics WHERE id = :id' );
    $delete->bindValue( ':id', (int) $row['id'], PDO::PARAM_INT );
    $delete->execute();
    return $row['topic'];
}


// plugin-update-checker kütüphaneyi dahil et.
require 'plugin-update-checker-5.6/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Güncelleme kontrolcüsünü başlat
$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/picomve/aimakale/', // GitHub depo linkin
	__FILE__, // Ana eklenti dosyası
	'aimakale' // Eklenti slug (klasör adı)
);

// Opsiyonel: Sadece "main" branch'indeki release'leri kontrol etmesini sağlar
$myUpdateChecker->setBranch('main');

// --- 1. ADMİN MENÜSÜ EKLEME ---
add_action( 'admin_menu', 'gemini_menu_olustur' );

function gemini_menu_olustur() {
    add_menu_page(
        'AI Makale Yazar',          // Sayfa Başlığı
        'AI Makale Yazar',          // Menü Adı
        'manage_options',           // Yetki (Sadece admin)
        'aimakale-ayarlari',        // Sayfa Slug'ı (URL)
        'aimakale_sayfa_getir',     // İçeriği basacak fonksiyon
        'dashicons-edit',           // İkon
        100                         // Sıra
    );
}

// Admin sayfasını dosyadan dahil et
function aimakale_sayfa_getir() {
    include plugin_dir_path( __FILE__ ) . 'admin/index.php';
}

// --- 2. ZAMANLAYICI AYARLARI ---
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['weekly'] = array( 'interval' => 604800, 'display'  => 'Haftada Bir' );
    return $schedules;
});

// Daily için bir sonraki 00:00 zamanını döndür (WordPress timezone). Cron UTC kullandığı için UTC timestamp.
function gemini_next_midnight_utc() {
    $tz_string = get_option( 'timezone_string' );
    if ( empty( $tz_string ) ) {
        $offset = (float) get_option( 'gmt_offset' );
        $tz_string = 'UTC' . ( $offset >= 0 ? '+' : '' ) . $offset;
    }
    try {
        $tz = new DateTimeZone( $tz_string );
    } catch ( Exception $e ) {
        $tz = new DateTimeZone( 'UTC' );
    }
    $now = new DateTime( 'now', $tz );
    $midnight = clone $now;
    $midnight->setTime( 0, 0, 0 );
    if ( $now >= $midnight ) {
        $midnight->modify( '+1 day' );
    }
    $midnight->setTimezone( new DateTimeZone( 'UTC' ) );
    return $midnight->getTimestamp();
}

// Aktivasyonda varsayılan zamanlayıcıyı kur
register_activation_hook( __FILE__, function() {
    $aralik = get_option( 'gemini_cron_aralik_opt', 'daily' );
    if ( ! wp_next_scheduled( 'gemini_gorevi_v5' ) ) {
        $first_run = ( $aralik === 'daily' ) ? gemini_next_midnight_utc() : time();
        wp_schedule_event( $first_run, $aralik, 'gemini_gorevi_v5' );
    }
});

// Deaktivasyonda temizle
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'gemini_gorevi_v5' );
});

add_action( 'gemini_gorevi_v5', 'aimakale_baslat' );

// --- 3. ANA FONKSİYON ---
function aimakale_baslat( $debug = false ) {
    if ( empty( GEMINI_API_KEY ) || strlen( GEMINI_API_KEY ) < 10 ) {
        if ( $debug ) {
            echo "HATA: API Key yok.";
        }
        return;
    }

    $konu = aimakale_db_consume_topic();
    if ( empty( $konu ) ) {
        if ( $debug ) {
            echo "HATA: Veri tabanında konu bulunamadı.";
        }
        return;
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;
    $prompt = "Şu konuda Türkçe, SEO uyumlu, HTML formatlı (h2, p) blog yazısı yaz. Başlık h1 olmasın. İlk satır başlık olacak ve konuyu içerecek. Konu: $konu";

    $body = json_encode([ 'contents' => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ] ]);

    $args = [
        'body'    => $body,
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 60, 'method'  => 'POST'
    ];

    $response = wp_remote_post( $url, $args );

    if ( is_wp_error( $response ) ) return;

    $res_body = json_decode( wp_remote_retrieve_body( $response ), true );
    $ai_text = $res_body['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if ( ! empty( $ai_text ) ) {
        $lines = explode("\n", trim($ai_text));
        $title = strip_tags($lines[0]);
        unset($lines[0]);
        
        wp_insert_post([
            'post_title'   => $title,
            'post_content' => implode("\n", $lines),
            'post_status'  => 'draft',
            'post_author'  => 1
        ]);
        
        if ($debug) echo "Başarılı.";
    }
}

// Test Tetikleyici (Admin paneli dışından URL ile test için)
add_action( 'init', function() {
    if ( isset( $_GET['gemini_tetikle'] ) && current_user_can( 'manage_options' ) ) {
        aimakale_baslat( true );
        exit;
    }
});