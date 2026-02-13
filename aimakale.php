<?php
/**
 * Plugin Name: AI makale
 * Description: Düzenli aralıklarıla makale yazıp taslak olarak kaydeden wp eklentisi
 * Author: Halil ibrahim ATAYLAR
 * Version: 0.51
 */

// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) exit;

// --- ENV YÜKLEME ---
function gemini_env_yukle() {
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
gemini_env_yukle();

// Varsayılanlar
if ( ! defined( 'GEMINI_API_KEY' ) ) define( 'GEMINI_API_KEY', '' );
if ( ! defined( 'GEMINI_MODEL' ) )   define( 'GEMINI_MODEL', 'gemini-1.5-flash' );
define( 'KONU_DOSYASI', plugin_dir_path( __FILE__ ) . 'konular.txt' );

// --- 1. ADMİN MENÜSÜ EKLEME ---
add_action( 'admin_menu', 'gemini_menu_olustur' );

function gemini_menu_olustur() {
    add_menu_page(
        'Gemini Yazar',          // Sayfa Başlığı
        'Gemini Yazar',          // Menü Adı
        'manage_options',        // Yetki (Sadece admin)
        'gemini-yazar-ayarlari', // Sayfa Slug'ı (URL)
        'gemini_sayfa_getir',    // İçeriği basacak fonksiyon
        'dashicons-edit',        // İkon
        100                      // Sıra
    );
}

// Admin sayfasını dosyadan dahil et
function gemini_sayfa_getir() {
    include plugin_dir_path( __FILE__ ) . 'admin/index.php';
}

// --- 2. ZAMANLAYICI AYARLARI ---
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['weekly'] = array( 'interval' => 604800, 'display'  => 'Haftada Bir' );
    return $schedules;
});

// Aktivasyonda varsayılan zamanlayıcıyı kur
register_activation_hook( __FILE__, function() {
    $aralik = get_option( 'gemini_cron_aralik_opt', 'daily' );
    if ( ! wp_next_scheduled( 'gemini_gorevi_v5' ) ) {
        wp_schedule_event( time(), $aralik, 'gemini_gorevi_v5' );
    }
});

// Deaktivasyonda temizle
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'gemini_gorevi_v5' );
});

add_action( 'gemini_gorevi_v5', 'gemini_baslat' );

// --- 3. ANA FONKSİYON ---
function gemini_baslat( $debug = false ) {
    
    if ( empty( GEMINI_API_KEY ) || strlen( GEMINI_API_KEY ) < 10 ) {
        if ($debug) echo "HATA: API Key yok."; return;
    }
    
    if ( ! file_exists( KONU_DOSYASI ) ) return;

    $satirlar = file( KONU_DOSYASI, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    if ( empty( $satirlar ) ) return;

    $konu = trim( $satirlar[0] );
    unset( $satirlar[0] );
    file_put_contents( KONU_DOSYASI, implode( PHP_EOL, $satirlar ) );

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;
    $prompt = "Şu konuda Türkçe, SEO uyumlu, HTML formatlı (h2, p) blog yazısı yaz. Başlık h1 olmasın. Konu: $konu";

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
        gemini_baslat( true );
        exit;
    }
});