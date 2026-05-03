<?php
// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) exit;

// --- AYARLARI KAYDETME İŞLEMİ ---
if ( isset( $_POST['gemini_ayarlari_kaydet'] ) ) {
    // Güvenlik kontrolü (Nonce)
    check_admin_referer( 'gemini_ayar_guvenligi' );

    // Formdan gelen veriyi al
    $yeni_aralik = sanitize_text_field( $_POST['gemini_cron_aralik'] );

    // Veritabanına kaydet
    update_option( 'gemini_cron_aralik_opt', $yeni_aralik );

    wp_clear_scheduled_hook( 'gemini_gorevi_v5' );
    $first_run = ( $yeni_aralik === 'daily' ) ? gemini_next_midnight_utc() : time();
    wp_schedule_event( $first_run, $yeni_aralik, 'gemini_gorevi_v5' );

    echo '<div class="notice notice-success is-dismissible"><p>Ayarlar kaydedildi ve zamanlayıcı güncellendi!</p></div>';
}

// --- KONULARI KAYDETME İŞLEMİ ---
if ( isset( $_POST['konular_kaydet'] ) ) {
    // Güvenlik kontrolü (Nonce)
    check_admin_referer( 'konular_guvenligi' );

    // Formdan gelen veriyi al
    $yeni_konular = sanitize_textarea_field( $_POST['konular_icerik'] );

    // Dosyaya kaydet
    file_put_contents( KONU_DOSYASI, $yeni_konular );

    echo '<div class="notice notice-success is-dismissible"><p>Konular dosyası güncellendi!</p></div>';
}

$mevcut_aralik = get_option( 'gemini_cron_aralik_opt', 'daily' );
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="wrap container mt-4" style="max-width: 800px;">
    
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h2 class="h5 mb-0" style="color:white;">AI Makele Ayarları</h2>
        </div>
        <div class="card-body">
            
            <p class="card-text">Bu panelden makale oluşturma sıklığını değiştirebilirsiniz. API anahtarınız <code>.env.local</code> dosyasından okunmaktadır.</p>
            <hr>
            <form method="post" action="">
                <?php wp_nonce_field( 'gemini_ayar_guvenligi' ); ?>

                <div class="mb-3">
                    <label for="zamanlama" class="form-label fw-bold">Makale Yazma Sıklığı</label>
                    <select name="gemini_cron_aralik" id="zamanlama" class="form-select">
                        <option value="hourly" <?php selected( $mevcut_aralik, 'hourly' ); ?>>Saat Başı (Test İçin)</option>
                        <option value="daily" <?php selected( $mevcut_aralik, 'daily' ); ?>>Günde Bir (Daily)</option>
                        <option value="weekly" <?php selected( $mevcut_aralik, 'weekly' ); ?>>Haftada Bir (Weekly)</option>
                    </select>
                    <div class="form-text text-muted">
                        Seçtiğiniz aralıkta konular dosyasından bir satır silinir ve taslak oluşturulur.
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" name="gemini_ayarlari_kaydet" class="btn btn-success">
                        Ayarları Kaydet ve Zamanlayıcıyı Güncelle
                    </button>
                </div>
            </form>

        </div>
    </div>

    <div class="card mt-4 border-info">
        <div class="card-body">
            <h5 class="card-title text-info">Sistem Durumu</h5>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Konu Dosyası Durumu:
                    <?php if ( file_exists( KONU_DOSYASI ) ):
                        $satirlar = file( KONU_DOSYASI, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
                        $count = count($satirlar);
                    ?>
                        <span class="badge bg-success rounded-pill">Bulundu (<?php echo $count; ?> konu)</span>
                    <?php else: ?>
                        <span class="badge bg-danger rounded-pill">Bulunamadı</span>
                    <?php endif; ?>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Şu anki Zamanlama:
                    <span class="badge bg-secondary rounded-pill"><?php echo ucfirst($mevcut_aralik); ?></span>
                </li>
                <?php if ( $mevcut_aralik === 'daily' ): $next = wp_next_scheduled( 'gemini_gorevi_v5' ); ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Sonraki çalışma (00:00):
                    <span class="badge bg-success rounded-pill"><?php echo $next ? date_i18n( 'Y-m-d H:i', $next ) : '—'; ?></span>
                </li>
                <?php endif; ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Manuel Test:
                    <a href="<?php echo admin_url('?gemini_tetikle=1'); ?>" class="btn btn-sm btn-outline-warning" target="_blank">Şimdi Tetikle</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="card mt-4 border-warning">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">Konular Yönetimi</h5>
        </div>
        <div class="card-body">
            <?php if ( file_exists( KONU_DOSYASI ) ):
                $icerik = file_get_contents( KONU_DOSYASI );
            ?>
            <p>Konular dosyasını düzenleyebilirsiniz. Her satır bir konu olacak.</p>
            <form method="post" action="">
                <?php wp_nonce_field( 'konular_guvenligi' ); ?>
                <div class="mb-3">
                    <label for="konular_icerik" class="form-label">Konular (Her satır bir konu)</label>
                    <textarea name="konular_icerik" id="konular_icerik" class="form-control" rows="10"><?php echo esc_textarea( $icerik ); ?></textarea>
                </div>
                <button type="submit" name="konular_kaydet" class="btn btn-warning">Konuları Kaydet</button>
            </form>
            <?php else: ?>
            <p class="text-danger">Konular dosyası bulunamadı.</p>
            <?php endif; ?>
        </div>
    </div>

</div>