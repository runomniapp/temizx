<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../classes/Setting.php';

$settingModel = new Setting();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verifyCsrfToken($csrf)) {
        // Text ayarları güncelle
        $textSettings = [
            'company_name'     => $_POST['company_name'] ?? '',
            'site_title'       => $_POST['site_title'] ?? '',
            'site_description' => $_POST['site_description'] ?? '',
            'site_keywords'    => $_POST['site_keywords'] ?? '',
            'phone'            => $_POST['phone'] ?? '',
            'whatsapp'         => $_POST['whatsapp'] ?? '',
            'email'            => $_POST['email'] ?? '',
            'address'          => $_POST['address'] ?? '',
            'work_hours'       => $_POST['work_hours'] ?? '',
            'facebook'         => $_POST['facebook'] ?? '',
            'instagram'        => $_POST['instagram'] ?? '',
            'footer_text'      => $_POST['footer_text'] ?? '',
            'maps_iframe'      => $_POST['maps_iframe'] ?? ''
        ];
        
        $settingModel->updateMany($textSettings);
        
        // Logo yükleme
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoRes = Setting::uploadFile($_FILES['logo'], 'system');
            if ($logoRes['success']) {
                $settingModel->update('logo_path', $logoRes['path']);
            } else {
                $msg .= '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 20px;">Logo Hatası: ' . e($logoRes['message']) . '</div>';
            }
        }
        
        // Favicon yükleme
        if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $favRes = Setting::uploadFile($_FILES['favicon'], 'system');
            if ($favRes['success']) {
                $settingModel->update('favicon_path', $favRes['path']);
            } else {
                $msg .= '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 20px;">Favicon Hatası: ' . e($favRes['message']) . '</div>';
            }
        }
        
        $msg .= '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 20px;"><i class="fa-solid fa-circle-check"></i> Sistem ayarları başarıyla güncellendi!</div>';
        
        // Önbelleği yenilemek için sayfayı yenile veya ayarları tekrar yükle
        global $settings;
        $settings = $settingModel->getAll();
    } else {
        $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 20px;">Güvenlik doğrulaması başarısız.</div>';
    }
}

$allSettings = $settingModel->getAll();
?>

<div class="page-header" style="max-width: 900px; margin: 0 auto;">
    <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 10px;">Sistem Ayarları</h2>
    <p style="color: var(--text-muted); margin-bottom: 30px;">Firma bilgileri, iletişim kanalları, logolar ve SEO anahtar kelimelerini buradan düzenleyin.</p>
    
    <?php echo $msg; ?>
    
    <div class="card" style="padding: 40px; border: 1px solid var(--border);">
        <form action="ayarlar.php" method="POST" enctype="multipart/form-data">
            <?php csrfInput(); ?>
            
            <h3 style="font-weight: 800; font-size: 1.15rem; margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 12px; color: var(--primary);">Genel Firma Bilgileri</h3>
            
            <div class="form-group">
                <label class="form-label" for="company_name">Firma Adı</label>
                <input type="text" name="company_name" id="company_name" class="form-control" value="<?php echo e($allSettings['company_name'] ?? ''); ?>" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label" for="phone">Telefon Numarası</label>
                    <input type="text" name="phone" id="phone" class="form-control" value="<?php echo e($allSettings['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="whatsapp">WhatsApp Numarası</label>
                    <input type="text" name="whatsapp" id="whatsapp" class="form-control" value="<?php echo e($allSettings['whatsapp'] ?? ''); ?>">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label" for="email">E-posta Adresi</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?php echo e($allSettings['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="work_hours">Çalışma Saatleri</label>
                    <input type="text" name="work_hours" id="work_hours" class="form-control" value="<?php echo e($allSettings['work_hours'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="address">Adres</label>
                <textarea name="address" id="address" class="form-control" rows="3" style="border-radius: 20px; resize: none;"><?php echo e($allSettings['address'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="maps_iframe">Google Maps Iframe Kodu</label>
                <textarea name="maps_iframe" id="maps_iframe" class="form-control" rows="3" style="border-radius: 20px; resize: none; font-family: monospace; font-size: 0.85rem;"><?php echo e($allSettings['maps_iframe'] ?? ''); ?></textarea>
            </div>
            
            <h3 style="font-weight: 800; font-size: 1.15rem; margin-top: 40px; margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 12px; color: var(--primary);">Görsel Ayarları</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 25px;">
                <div class="form-group">
                    <label class="form-label" for="logo">Firma Logosu (Önerilen: PNG transparent)</label>
                    <input type="file" name="logo" id="logo" class="form-control" style="padding: 10px 20px;">
                    <?php if (isset($allSettings['logo_path']) && $allSettings['logo_path']): ?>
                        <div style="margin-top: 15px; padding: 15px; border: 1px solid var(--border); border-radius: 12px; display: inline-block; background-color: #fafbfc;">
                            <img src="../<?php echo e($allSettings['logo_path']); ?>" alt="Logo" style="max-height: 40px;">
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="favicon">Favicon / Tarayıcı İkonu</label>
                    <input type="file" name="favicon" id="favicon" class="form-control" style="padding: 10px 20px;">
                    <?php if (isset($allSettings['favicon_path']) && $allSettings['favicon_path']): ?>
                        <div style="margin-top: 15px; padding: 15px; border: 1px solid var(--border); border-radius: 12px; display: inline-block; background-color: #fafbfc;">
                            <img src="../<?php echo e($allSettings['favicon_path']); ?>" alt="Favicon" style="max-height: 32px; max-width: 32px;">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <h3 style="font-weight: 800; font-size: 1.15rem; margin-top: 40px; margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 12px; color: var(--primary);">SEO & Sosyal Medya Ayarları</h3>
            
            <div class="form-group">
                <label class="form-label" for="site_title">SEO Sayfa Başlığı (Title)</label>
                <input type="text" name="site_title" id="site_title" class="form-control" value="<?php echo e($allSettings['site_title'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="site_description">SEO Sayfa Açıklaması (Description)</label>
                <textarea name="site_description" id="site_description" class="form-control" rows="3" style="border-radius: 20px; resize: none;"><?php echo e($allSettings['site_description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="site_keywords">SEO Anahtar Kelimeler (Keywords)</label>
                <input type="text" name="site_keywords" id="site_keywords" class="form-control" value="<?php echo e($allSettings['site_keywords'] ?? ''); ?>" placeholder="Virgülle ayırın">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label" for="facebook">Facebook Linki</label>
                    <input type="url" name="facebook" id="facebook" class="form-control" value="<?php echo e($allSettings['facebook'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="instagram">Instagram Linki</label>
                    <input type="url" name="instagram" id="instagram" class="form-control" value="<?php echo e($allSettings['instagram'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="footer_text">Footer Copyright Metni</label>
                <input type="text" name="footer_text" id="footer_text" class="form-control" value="<?php echo e($allSettings['footer_text'] ?? ''); ?>">
            </div>
            
            <button type="submit" class="btn btn-primary" style="margin-top: 20px; width: 100%; padding: 14px 24px;">Ayarları Kaydet</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
