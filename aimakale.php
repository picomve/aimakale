<?php
/**
 * Plugin Name: AI makale
 * Description: Düzenli aralıklarıla makale yazıp taslak olarak kaydeden wp eklentisi
 * Author: Halil ibrahim ATAYLAR
 * Version: 0.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// --- .ENV YÜKLEME FONKSİYONU ---
function gemini_env_yukle() {
    // Önce .env.local, yoksa .env dosyasına bakar
    $env_dosyasi = plugin_dir_path( __FILE__ ) . '.env';
    
    if ( ! file_exists( $env_dosyasi ) ) {
        $env_dosyasi = plugin_dir_path( __FILE__ ) . '.env.local';
    }

    if ( file_exists( $env_dosyasi ) ) {
        $satirlar = file( $env_dosyasi, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        foreach ( $satirlar as $satir ) {
            // Yorum satırlarını (#) atla
            if ( strpos( trim( $satir ), '#' ) === 0 ) continue;

            // Eşittir işaretinden böl
            list( $anahtar, $deger ) = explode( '=', $satir, 2 );
            
            $anahtar = trim( $anahtar );
            $deger   = trim( $deger );

            // Eğer tanımlanmamışsa sabiti tanımla
            if ( ! defined( $anahtar ) ) {
                define( $anahtar, $deger );
            }
        }
    }
}

// .env dosyasını yükle
gemini_env_yukle();

// --- SABİTLER VE AYARLAR ---
// Artık .env dosyasından gelen sabitleri kullanıyoruz
// Eğer .env yoksa veya anahtar girilmemişse varsayılan değerler (boş) kalır.
if ( ! defined( 'GEMINI_API_KEY' ) ) define( 'GEMINI_API_KEY', '' );
if ( ! defined( 'GEMINI_MODEL' ) )   define( 'GEMINI_MODEL', 'gemini-2.5-flash' );

define( 'KONU_DOSYASI', plugin_dir_path( __FILE__ ) . 'konular.txt' );

// Zamanlayıcı Tanımları
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['weekly'] = array( 'interval' => 604800, 'display'  => 'Haftada Bir' );
    return $schedules;
});

register_activation_hook( __FILE__, function() {
    if ( ! wp_next_scheduled( 'gemini_gorevi' ) ) wp_schedule_event( time(), 'weekly', 'gemini_gorevi' );
});

register_deactivation_hook( __FILE__, function() { wp_clear_scheduled_hook( 'gemini_gorevi' ); });
add_action( 'gemini_gorevi', 'gemini_baslat' );

// --- ANA FONKSİYON ---
function gemini_baslat( $debug = false ) {
    
    // 1. Temel Kontroller
    if ( !defined('GEMINI_API_KEY') || strlen(GEMINI_API_KEY) < 10 ) {
        if ($debug) echo "<p style='color:red;'>HATA: API Anahtarı eksik.</p>"; return;
    }
    
    if ( !file_exists( KONU_DOSYASI ) ) {
        if ($debug) echo "<p style='color:red;'>HATA: konular.txt dosyası yok.</p>"; return;
    }

    $satirlar = file( KONU_DOSYASI, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    if ( empty( $satirlar ) ) {
        if ($debug) echo "<p style='color:red;'>HATA: Konu listesi boş.</p>"; return;
    }

    $konu = trim( $satirlar[0] );
    
    // 2. Modelleri Listeleme Modu (Eğer hata alırsak neyin var olduğunu görmek için)
    if ( $debug ) {
        echo "<h3>--- MODEL KONTROLÜ ---</h3>";
        $list_url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . GEMINI_API_KEY;
        $list_response = wp_remote_get( $list_url );
        
        if ( !is_wp_error($list_response) && wp_remote_retrieve_response_code($list_response) == 200 ) {
            $data = json_decode( wp_remote_retrieve_body($list_response), true );
            echo "<strong>Kullanabileceğiniz Geçerli Modeller:</strong><br>";
            echo "<textarea style='width:100%; height:100px;'>";
            if(isset($data['models'])) {
                foreach($data['models'] as $m) {
                    // Sadece 'generateContent' destekleyenleri göster
                    if(in_array('generateContent', $m['supportedGenerationMethods'])) {
                        // "models/" kısmını atarak yazdır
                        echo str_replace('models/', '', $m['name']) . "\n";
                    }
                }
            }
            echo "</textarea><br><small>Yukarıdaki listeden birini koddaki GEMINI_MODEL_ID kısmına yazabilirsiniz.</small><br><hr>";
        } else {
            echo "Model listesi alınamadı. API Key yanlış olabilir.<br><hr>";
        }
    }

    // 3. Makale Yazdırma İsteği
    if ($debug) echo "<strong>Seçilen Konu:</strong> $konu <br>";
    if ($debug) echo "<strong>Kullanılan Model:</strong> " . GEMINI_MODEL_ID . "<br>";

    // Dosyadan sil
    unset( $satirlar[0] );
    file_put_contents( KONU_DOSYASI, implode( PHP_EOL, $satirlar ) );

    // URL Yapısı: v1beta + gemini-1.5-flash
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL_ID . ':generateContent?key=' . GEMINI_API_KEY;
    
    $prompt = "Şu konuda Türkçe, SEO uyumlu, HTML formatlı blog yazısı yaz. Başlık h1 olmasın. Konu: $konu";

    $body = json_encode([
        'contents' => [
            [ 'parts' => [ [ 'text' => $prompt ] ] ]
        ]
    ]);

    $args = [
        'body' => $body,
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 60, 'method' => 'POST'
    ];

    $response = wp_remote_post( $url, $args );

    if ( is_wp_error( $response ) ) {
        if ($debug) echo "Bağlantı Hatası: " . $response->get_error_message(); return;
    }

    $res_body = json_decode( wp_remote_retrieve_body( $response ), true );

    // Hata Kontrolü
    if ( isset($res_body['error']) ) {
        if ($debug) {
            echo "<p style='color:red; font-weight:bold;'>API HATASI:</p>";
            echo "<pre>" . print_r($res_body['error'], true) . "</pre>";
            echo "<p>Lütfen yukarıdaki 'Kullanabileceğiniz Geçerli Modeller' listesindeki bir ismi koda yazın.</p>";
        }
        return;
    }

    // İçeriği Al
    $ai_text = $res_body['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if ( !empty( $ai_text ) ) {
        $lines = explode("\n", trim($ai_text));
        $title = strip_tags($lines[0]);
        unset($lines[0]);
        
        $post_id = wp_insert_post([
            'post_title' => $title, 'post_content' => implode("\n", $lines),
            'post_status' => 'draft', 'post_author' => 1
        ]);
        
        if ($debug) echo "<h3 style='color:green;'>BAŞARILI! Yazı Taslaklara Eklendi. ID: $post_id</h3>";
    }
}

// Test Tetikleyici
add_action( 'init', function() {
    if ( isset( $_GET['gemini_tetikle'] ) && current_user_can( 'manage_options' ) ) {
        gemini_baslat( true );
        exit;
    }
});