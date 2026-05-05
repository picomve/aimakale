<?php
// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) exit;

// --- ENV KURULUMU İŞLEMİ ---
if ( isset( $_POST['aimakale_setup_submit'] ) ) {
    check_admin_referer( 'aimakale_env_setup' );
    
    $env_vars = [];
    $missing_env = aimakale_validate_env();
    
    // Eksik olan her değişken için formdan al
    foreach ( array_keys( $missing_env ) as $key ) {
        if ( isset( $_POST[ 'env_' . $key ] ) ) {
            $value = sanitize_text_field( $_POST[ 'env_' . $key ] );
            if ( ! empty( trim( $value ) ) ) {
                $env_vars[ $key ] = $value;
            }
        }
    }
    
    if ( ! empty( $env_vars ) ) {
        if ( aimakale_write_env( $env_vars ) ) {
            // Yeni değerleri tanımla
            foreach ( $env_vars as $key => $value ) {
                if ( ! defined( $key ) ) {
                    define( $key, $value );
                }
            }
            echo '<div class="notice notice-success is-dismissible"><p>✓ Ortam değişkenleri başarıyla kaydedildi! Lütfen sayfayı yenileyin.</p></div>';
            $setup_success = true;
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>✗ Dosya yazma hatası. Lütfen klasör izinlerini kontrol edin.</p></div>';
        }
    }
}

$missing_env = aimakale_validate_env();
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="wrap" style="width: 100%; max-width: none; margin: 0; padding: 2rem;">
    <div style="max-width: 600px; margin: 0 auto;">
        <div class="card border-danger shadow-lg">
            <div class="card-header bg-danger text-white">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">⚠️</span>
                    <h1 class="h4 mb-0">Konfigürasyon Gerekli</h1>
                </div>
            </div>
            <div class="card-body">
                <p class="card-text mb-4">
                    <strong>AI Makale eklentisi düzgün çalışabilmesi için aşağıdaki ortam değişkenlerinin ayarlanması gerekmektedir:</strong>
                </p>

                <div class="alert alert-info" role="alert">
                    <strong>ℹ️ Bilgi:</strong> Bu değerler <code>.env.local</code> dosyasına kaydedilecek ve sunucuda güvenli bir şekilde saklanacaktır.
                </div>

                <form method="post" action="">
                    <?php wp_nonce_field( 'aimakale_env_setup' ); ?>

                    <div class="mb-4">
                        <h5 class="text-danger">Eksik Değişkenler:</h5>
                        
                        <?php foreach ( $missing_env as $key => $label ): ?>
                            <div class="mb-3">
                                <label for="env_<?php echo esc_attr( $key ); ?>" class="form-label">
                                    <strong><?php echo esc_html( $label ); ?></strong>
                                    <span class="badge bg-danger">Zorunlu</span>
                                </label>
                                
                                <?php if ( $key === 'GEMINI_API_KEY' ): ?>
                                    <small class="form-text text-muted d-block mb-2">
                                        <a href="https://ai.google.dev/tutorials/setup" target="_blank" rel="noopener noreferrer">
                                            Buradan Gemini API Anahtarınızı alabilirsiniz →
                                        </a>
                                    </small>
                                    <input 
                                        type="password" 
                                        class="form-control" 
                                        id="env_<?php echo esc_attr( $key ); ?>"
                                        name="env_<?php echo esc_attr( $key ); ?>"
                                        placeholder="sk-XXXXXXXXXXXXXXXXXXXXXXXX"
                                        required
                                    >
                                <?php else: ?>
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        id="env_<?php echo esc_attr( $key ); ?>"
                                        name="env_<?php echo esc_attr( $key ); ?>"
                                        required
                                    >
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" name="aimakale_setup_submit" class="btn btn-danger btn-lg">
                            ✓ Konfigürasyonu Tamamla
                        </button>
                    </div>
                </form>

                <hr class="my-4">

                <div class="alert alert-secondary" role="alert">
                    <h6 class="alert-heading">Alternatif: El ile .env Dosyası Yükleme</h6>
                    <p class="mb-0">
                        Eğer zaten bir <code>.env.local</code> dosyanız varsa, 
                        <a href="<?php echo esc_url( add_query_arg( 'page', 'aimakale-ayarlari-upload' ) ); ?>">
                            buradan yükleyebilirsiniz.
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-4 p-3 bg-light rounded">
            <h6>📝 Adımlar:</h6>
            <ol>
                <li>Gerekli API anahtarlarını bulun (yukarıdaki bağlantılara bakın)</li>
                <li>Değerleri aşağıdaki form alanlarına girin</li>
                <li>"Konfigürasyonu Tamamla" düğmesine tıklayın</li>
                <li>Sayfayı yenileyin</li>
            </ol>
        </div>
    </div>
</div>
