<?php
// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) exit;

// --- ENV DEĞERLERİ GÜNCELLEME ---
if ( isset( $_POST['aimakale_update_env'] ) ) {
    check_admin_referer( 'aimakale_env_update' );
    
    $env_vars = [];
    
    if ( isset( $_POST['env_GEMINI_API_KEY'] ) && ! empty( trim( $_POST['env_GEMINI_API_KEY'] ) ) ) {
        $env_vars['GEMINI_API_KEY'] = sanitize_text_field( $_POST['env_GEMINI_API_KEY'] );
    }
    
    if ( isset( $_POST['env_GEMINI_MODEL'] ) && ! empty( trim( $_POST['env_GEMINI_MODEL'] ) ) ) {
        $env_vars['GEMINI_MODEL'] = sanitize_text_field( $_POST['env_GEMINI_MODEL'] );
    }
    
    if ( ! empty( $env_vars ) ) {
        if ( aimakale_write_env( $env_vars ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>✓ Ortam değişkenleri başarıyla güncellendi!</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>✗ Dosya yazma hatası. Lütfen klasör izinlerini kontrol edin.</p></div>';
        }
    }
}

// --- ENV DOSYASI İNDİRME ---
if ( isset( $_GET['aimakale_download_env'] ) && current_user_can( 'manage_options' ) ) {
    check_admin_referer( 'aimakale_env_download' );
    
    $example_env = "# AI Makale Yazar - Ortam Değişkenleri\nGEMINI_API_KEY=your-api-key-here\nGEMINI_MODEL=gemini-1.5-flash\n";
    
    header( 'Content-Type: text/plain' );
    header( 'Content-Disposition: attachment; filename=".env.local"' );
    header( 'Content-Length: ' . strlen( $example_env ) );
    echo $example_env;
    exit;
}

// --- ENV DOSYASI YÜKLEME ---
if ( isset( $_POST['aimakale_upload_env'] ) ) {
    check_admin_referer( 'aimakale_env_upload' );
    
    if ( isset( $_FILES['env_file'] ) && $_FILES['env_file']['error'] === UPLOAD_ERR_OK ) {
        $file = $_FILES['env_file'];
        
        if ( $file['type'] === 'text/plain' || strpos( $file['name'], '.env' ) !== false ) {
            $content = file_get_contents( $file['tmp_name'] );
            $env_path = plugin_dir_path( dirname( __FILE__ ) ) . '.env.local';
            
            if ( file_put_contents( $env_path, $content ) !== false ) {
                echo '<div class="notice notice-success is-dismissible"><p>.env.local dosyası başarıyla yüklendi!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>.env.local dosyası yazılamadı. Klasör izinlerini kontrol edin.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Lütfen geçerli bir .env dosyası seçin.</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>Dosya yükleme hatası oluştu.</p></div>';
    }
}

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

// --- KONU SİLME İŞLEMİ (Anında) ---
if ( isset( $_POST['aimakale_delete_topic'] ) ) {
    check_admin_referer( 'konular_guvenligi' );
    $topic_id = 0;
    if ( isset( $_POST['delete_topic_id'] ) ) {
        $topic_id = (int) $_POST['delete_topic_id'];
    } elseif ( ! empty( $_POST['aimakale_delete_topic'] ) ) {
        $topic_id = (int) $_POST['aimakale_delete_topic'];
    }
    if ( $topic_id > 0 ) {
        aimakale_db_delete_topic( $topic_id );
        echo '<div class="notice notice-success is-dismissible"><p>Konu başarıyla silindi!</p></div>';
    }
}

// --- KONULARI KAYDETME İŞLEMİ ---
if ( isset( $_POST['konular_kaydet'] ) ) {
    check_admin_referer( 'konular_guvenligi' );

    $topic_ids = isset( $_POST['topic_ids'] ) && is_array( $_POST['topic_ids'] ) ? $_POST['topic_ids'] : [];
    $topics    = isset( $_POST['topics'] ) && is_array( $_POST['topics'] ) ? $_POST['topics'] : [];

    foreach ( $topic_ids as $index => $id ) {
        $topic_text = isset( $topics[ $index ] ) ? sanitize_text_field( $topics[ $index ] ) : '';
        if ( trim( $topic_text ) === '' ) {
            aimakale_db_delete_topic( $id );
        } else {
            aimakale_db_update_topic( $id, $topic_text );
        }
    }

    $yeni_konu = isset( $_POST['yeni_konu'] ) ? sanitize_text_field( $_POST['yeni_konu'] ) : '';
    if ( trim( $yeni_konu ) !== '' ) {
        aimakale_db_add_topic( $yeni_konu );
    }

    echo '<div class="notice notice-success is-dismissible"><p>Konular veritabanı güncellendi!</p></div>';
}

$mevcut_aralik = get_option( 'gemini_cron_aralik_opt', 'daily' );
$konular = aimakale_db_get_topics();
$topic_count = count( $konular );
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="wrap" style="width: 100%; max-width: none; margin: 0; padding: 1.5rem 1.5rem;">
    <div class="row gx-4">
        <div class="col-xl-6 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="h5 mb-0" style="color:white;">AI Makale Ayarları</h2>
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
                                Seçtiğiniz aralıkta veritabanındaki ilk konu kullanılır ve taslak oluşturulur.
                            </div>
                        </div>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" name="gemini_ayarlari_kaydet" class="btn btn-success">Ayarları Kaydet ve Zamanlayıcıyı Güncelle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-6 mb-4">
            <div class="card h-100 border-info">
                <div class="card-body">
                    <h5 class="card-title text-info">Sistem Durumu</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Konu Veritabanı Durumu:
                            <?php if ( file_exists( KONU_DB ) ): ?>
                                <span class="badge bg-success rounded-pill">Bulundu (<?php echo $topic_count; ?> konu)</span>
                            <?php else: ?>
                                <span class="badge bg-danger rounded-pill">Bulunamadı</span>
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Şu anki Zamanlama:
                            <span class="badge bg-secondary rounded-pill"><?php echo ucfirst( $mevcut_aralik ); ?></span>
                        </li>
                        <?php if ( $mevcut_aralik === 'daily' ): $next = wp_next_scheduled( 'gemini_gorevi_v5' ); ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Sonraki çalışma (00:00):
                            <span class="badge bg-success rounded-pill"><?php echo $next ? date_i18n( 'Y-m-d H:i', $next ) : '—'; ?></span>
                        </li>
                        <?php endif; ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Manuel Test:
                            <a href="<?php echo admin_url( '?gemini_tetikle=1' ); ?>" class="btn btn-sm btn-outline-warning" target="_blank">Şimdi Tetikle</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row gx-4">
        <div class="col-12">
            <div class="card mt-4 border-secondary">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Ortam Anahtarları Ayarları</h5>
                </div>
                <div class="card-body">
                    <p>API anahtarınız ve model ayarlarınız <code>.env.local</code> dosyasında saklanır.</p>
                    <hr>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6>Örnek Dosya İndir</h6>
                            <p class="text-muted small">Doldurmanız gereken ortam değişkenlerinin bir örneğini indirin.</p>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( '?aimakale_download_env=1' ), 'aimakale_env_download' ) ); ?>" class="btn btn-info">
                                📥 .env.local Örneğini İndir
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6>Dosya Yükle</h6>
                            <p class="text-muted small">Hazırladığınız .env.local dosyasını yükleyin.</p>
                            <form method="post" enctype="multipart/form-data" style="display: inline;">
                                <?php wp_nonce_field( 'aimakale_env_upload' ); ?>
                                <div class="d-flex gap-2">
                                    <input type="file" name="env_file" accept=".env,.env.local,text/plain" class="form-control form-control-sm" style="flex: 1;">
                                    <button type="submit" name="aimakale_upload_env" class="btn btn-success btn-sm">📤 Yükle</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3">
                        <strong>ℹ️ Bilgi:</strong> .env.local dosyası güvenlik nedeniyle versiyon kontrolüne eklenmez. Sadece bu panel üzerinden yönetin.
                    </div>
                    <hr class="my-4">
                    <h6 class="mb-3">Ortam Değerlerini Doğrudan Güncelleyin</h6>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'aimakale_env_update' ); ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="env_gemini_key" class="form-label">Gemini API Anahtarı</label>
                                <input type="password" class="form-control" id="env_gemini_key" name="env_GEMINI_API_KEY" placeholder="API anahtarınızı girin">
                                <small class="form-text text-muted">
                                    <a href="https://ai.google.dev/tutorials/setup" target="_blank" rel="noopener noreferrer">
                                        API anahtarı almak için tıklayın
                                    </a>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <label for="env_gemini_model" class="form-label">Gemini Model</label>
                                <input type="text" class="form-control" id="env_gemini_model" name="env_GEMINI_MODEL" placeholder="gemini-1.5-flash" value="<?php echo esc_attr( defined( 'GEMINI_MODEL' ) ? GEMINI_MODEL : 'gemini-1.5-flash' ); ?>">
                                <small class="form-text text-muted">Varsayılan: gemini-1.5-flash</small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" name="aimakale_update_env" class="btn btn-primary">
                                💾 Ortam Değerlerini Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

    <div class="row gx-4">
        <div class="col-12">
            <div class="card mt-4 border-warning" style="position: relative; left: 0px; width: calc(100vw - 280px); max-width: none; margin: 0;">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Konular Yönetimi</h5>
                </div>
                <div class="card-body">
                    <p>Veritabanındaki konuları düzenleyebilir, silebilir veya yeni konu ekleyebilirsiniz. Boş bıraktığınız konu satırları kaydedildiğinde silinir.</p>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'konular_guvenligi' ); ?>
                        <div class="row g-3">
                            <?php if ( ! empty( $konular ) ): ?>
                                <?php foreach ( $konular as $index => $konu ): ?>
                                    <div class="col-12">
                                        <div class="d-flex align-items-start gap-3 p-3 bg-white border rounded">
                                            <div class="flex-shrink-0 text-muted" style="width: 40px; font-weight: 700;"><?php echo esc_html( $index + 1 ); ?></div>
                                            <div class="flex-grow-1">
                                                <input type="hidden" name="topic_ids[]" value="<?php echo esc_attr( $konu['id'] ); ?>">
                                                <label class="form-label visually-hidden" for="topic_<?php echo esc_attr( $konu['id'] ); ?>">Konu <?php echo esc_html( $index + 1 ); ?></label>
                                                <input type="text" id="topic_<?php echo esc_attr( $konu['id'] ); ?>" name="topics[]" value="<?php echo esc_attr( $konu['topic'] ); ?>" class="form-control" placeholder="Konu metni">
                                            </div>
                                            <div class="flex-shrink-0 text-end">
                                                <button type="submit" name="aimakale_delete_topic" value="<?php echo esc_attr( $konu['id'] ); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Bu konuyu silmek istediğinizden emin misiniz?');">🗑️ Sil</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-secondary">Henüz konu yok. Aşağıdan yeni konu ekleyin.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3 mt-4">
                            <label for="yeni_konu" class="form-label">Yeni Konu</label>
                            <input type="text" name="yeni_konu" id="yeni_konu" class="form-control" placeholder="Yeni bir konu ekleyin">
                        </div>
                        <button type="submit" name="konular_kaydet" class="btn btn-warning">Konuları Kaydet</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
