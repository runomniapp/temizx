<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../classes/WhatsAppService.php';

$msg = '';
$testResult = null;

// POST İstekleri (Ayarlar ve Test Gönderimi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. Ayarları Kaydet
    if ($_POST['action'] === 'save_settings') {
        $provider = trim($_POST['whatsapp_provider_type'] ?? 'cloud_api');
        updateSetting('whatsapp_provider_type', $provider);

        if ($provider === 'cloud_api') {
            updateSetting('whatsapp_cloud_token', trim($_POST['whatsapp_cloud_token'] ?? ''));
            updateSetting('whatsapp_cloud_phone_id', trim($_POST['whatsapp_cloud_phone_id'] ?? ''));
            updateSetting('whatsapp_cloud_waba_id', trim($_POST['whatsapp_cloud_waba_id'] ?? ''));
            $msg = '<div style="background-color: #ecfdf5; color: #047857; padding: 14px 20px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 10px;"><i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i> Meta WhatsApp Cloud API ayarları başarıyla kaydedildi!</div>';
        } else {
            updateSetting('whatsapp_service_url', trim($_POST['whatsapp_service_url'] ?? 'http://localhost:3099'));
            $msg = '<div style="background-color: #ecfdf5; color: #047857; padding: 14px 20px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 10px;"><i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i> Node.js mikroservis adresi başarıyla kaydedildi!</div>';
        }
    }

    // 2. Canlı Test Mesajı Gönder
    if ($_POST['action'] === 'send_test_message') {
        $testPhone = trim($_POST['test_phone'] ?? '');
        $testBody = trim($_POST['test_message'] ?? 'Merhaba! Bu bir OLiFA Temizlik WhatsApp test mesajıdır.');

        if (!empty($testPhone)) {
            $provider = WhatsAppService::getProviderType();
            if ($provider === 'cloud_api') {
                $testResult = WhatsAppService::sendCloudApiMessage($testPhone, $testBody);
            } else {
                $testResult = ['success' => false, 'message' => 'Test mesajı yalnızca Meta Cloud API modunda aktiftir.'];
            }
        } else {
            $testResult = ['success' => false, 'message' => 'Lütfen geçerli bir telefon numarası giriniz.'];
        }
    }
}

$statusData = WhatsAppService::getStatus();
$status = $statusData['status'] ?? 'OFFLINE';
$currentProvider = WhatsAppService::getProviderType();
$cloudToken = getSetting('whatsapp_cloud_token', '');
$cloudPhoneId = getSetting('whatsapp_cloud_phone_id', '');
$cloudWabaId = getSetting('whatsapp_cloud_waba_id', '');
$serviceUrl = WhatsAppService::getServiceUrl();
?>

<div class="main-content">
    <?php echo $msg; ?>

    <!-- Content Header -->
    <div class="content-header" style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-bottom: 6px; display: flex; align-items: center; gap: 10px;">
                <i class="fa-brands fa-whatsapp" style="color: #22c55e; font-size: 2rem;"></i> WhatsApp Entegrasyonu
            </h1>
            <p style="color: #64748b; font-size: 0.95rem; margin: 0;">
                Müşteri ve çalışan WhatsApp bildirim servisinin Meta Cloud API veya Node.js bağlantı ayarları.
            </p>
        </div>
        
        <div style="display: flex; align-items: center; gap: 10px;">
            <button onclick="location.reload();" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 12px; font-weight: 600; cursor: pointer; border: 1px solid #cbd5e1; background: #fff; color: #334155;">
                <i class="fa-solid fa-rotate-right"></i> Durumu Kontrol Et
            </button>
        </div>
    </div>

    <!-- Active Provider & Status Overview Card -->
    <div style="background: #fff; padding: 24px; border-radius: 18px; box-shadow: 0 4px 25px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; margin-bottom: 30px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 50px; height: 50px; border-radius: 14px; background: <?php echo ($currentProvider === 'cloud_api') ? '#f0fdf4' : '#eff6ff'; ?>; display: flex; align-items: center; justify-content: center; color: <?php echo ($currentProvider === 'cloud_api') ? '#16a34a' : '#2563eb'; ?>; font-size: 1.5rem;">
                    <i class="<?php echo ($currentProvider === 'cloud_api') ? 'fa-brands fa-whatsapp' : 'fa-solid fa-server'; ?>"></i>
                </div>
                <div>
                    <h3 style="margin: 0 0 3px 0; font-size: 1.1rem; color: #0f172a; font-weight: 700;">
                        <?php echo ($currentProvider === 'cloud_api') ? 'Meta WhatsApp Cloud API (Resmi)' : 'Node.js Özel Mikroservis'; ?>
                    </h3>
                    <span style="font-size: 0.85rem; color: #64748b;">
                        <?php echo ($currentProvider === 'cloud_api') ? 'Meta Facebook geliştirici uç noktası üzerinden 7/24 direk gönderim' : 'Sunucu / Localhost Node.js sanal tarayıcısı'; ?>
                    </span>
                </div>
            </div>

            <div>
                <?php if ($status === 'READY'): ?>
                    <span style="background: #dcfce7; color: #15803d; padding: 8px 18px; border-radius: 30px; font-size: 0.9rem; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: 1px solid #bbf7d0;">
                        <span style="width: 10px; height: 10px; background: #22c55e; border-radius: 50%; display: inline-block;"></span> Aktif & Bağlı (<?php echo e($statusData['info']['phone'] ?? 'Bağlandı'); ?>)
                    </span>
                <?php else: ?>
                    <span style="background: #fef2f2; color: #b91c1c; padding: 8px 18px; border-radius: 30px; font-size: 0.9rem; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: 1px solid #fecaca;">
                        <span style="width: 10px; height: 10px; background: #ef4444; border-radius: 50%; display: inline-block;"></span> Çevrimdışı / Eksik Bilgi
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($statusData['lastError'])): ?>
            <div style="margin-top: 15px; padding: 12px 16px; background: #fff5f5; border: 1px solid #fed7d7; border-radius: 10px; color: #c53030; font-size: 0.85rem;">
                <strong><i class="fa-solid fa-circle-exclamation"></i> Bağlantı Hatası:</strong> <?php echo e($statusData['lastError']); ?>

            </div>
        <?php endif; ?>
    </div>

    <!-- Provider Selection & Settings Form -->
    <form method="POST" action="whatsapp.php" style="margin-bottom: 30px;">
        <input type="hidden" name="action" value="save_settings">

        <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-bottom: 16px;">
            <i class="fa-solid fa-sliders" style="color: var(--primary); margin-right: 8px;"></i> WhatsApp Servis Sağlayıcısı Seçimi
        </h2>

        <!-- Mode Selection Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 25px;">
            
            <!-- Option 1: Meta Cloud API -->
            <label style="position: relative; background: #fff; padding: 22px; border-radius: 16px; border: 2px solid <?php echo ($currentProvider === 'cloud_api') ? '#22c55e' : '#e2e8f0'; ?>; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 20px rgba(0,0,0,0.02);" onclick="switchProvider('cloud_api')">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="radio" name="whatsapp_provider_type" value="cloud_api" <?php echo ($currentProvider === 'cloud_api') ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #22c55e;">
                        <strong style="font-size: 1.05rem; color: #0f172a;">Meta WhatsApp Cloud API</strong>
                    </div>
                    <span style="background: #dcfce7; color: #15803d; font-size: 0.72rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; text-transform: uppercase;">Önerilen</span>
                </div>
                <p style="color: #64748b; font-size: 0.88rem; margin: 12px 0 0 28px; line-height: 1.5;">
                    Meta (Facebook) resmi API servisi. Sunucu/Chrome gerektirmez, 7/24 kesintisiz çalışır, ban riski yoktur.
                </p>
            </label>

            <!-- Option 2: Node.js Microservice -->
            <label style="position: relative; background: #fff; padding: 22px; border-radius: 16px; border: 2px solid <?php echo ($currentProvider === 'web_js') ? '#2563eb' : '#e2e8f0'; ?>; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 20px rgba(0,0,0,0.02);" onclick="switchProvider('web_js')">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="radio" name="whatsapp_provider_type" value="web_js" <?php echo ($currentProvider === 'web_js') ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: #2563eb;">
                        <strong style="font-size: 1.05rem; color: #0f172a;">Node.js WhatsApp Web (Özel Servis)</strong>
                    </div>
                </div>
                <p style="color: #64748b; font-size: 0.88rem; margin: 12px 0 0 28px; line-height: 1.5;">
                    Yerel bilgisayarınızda veya özel VDS sunucunuzda çalışan sanal Chrome WhatsApp Web mikroservisi.
                </p>
            </label>
        </div>

        <!-- Section 1: Meta Cloud API Credentials Form -->
        <div id="cloud_api_settings_panel" style="background: #fff; padding: 30px; border-radius: 18px; box-shadow: 0 4px 25px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; display: <?php echo ($currentProvider === 'cloud_api') ? 'block' : 'none'; ?>;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;">
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0 0 4px 0;">
                        <i class="fa-solid fa-key" style="color: #22c55e; margin-right: 6px;"></i> Meta Cloud API Kimlik Bilgileri
                    </h3>
                    <p style="color: #64748b; font-size: 0.88rem; margin: 0;">
                        Meta Developer Portal (developers.facebook.com) üzerinden aldığınız WhatsApp API anahtarları.
                    </p>
                </div>
                <a href="https://developers.facebook.com/apps/" target="_blank" class="btn btn-outline" style="font-size: 0.82rem; padding: 6px 12px; border-radius: 8px; font-weight: 600; text-decoration: none;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Meta Portal'a Git
                </a>
            </div>

            <div style="display: flex; flex-direction: column; gap: 20px;">
                
                <!-- Access Token -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; font-size: 0.9rem; color: #334155; margin-bottom: 6px; display: block;">
                        Geçici veya Kalıcı Erişim Jetonu (Access Token) *
                    </label>
                    <div style="position: relative;">
                        <textarea name="whatsapp_cloud_token" id="cloud_token_input" class="form-control" rows="3" style="font-family: monospace; font-size: 0.85rem; padding: 12px 16px; border-radius: 12px; border: 1px solid #cbd5e1; resize: vertical;" placeholder="EAAG..."><?php echo e($cloudToken); ?></textarea>
                    </div>
                    <span style="font-size: 0.8rem; color: #94a3b8; margin-top: 4px; display: block;">
                        Meta App > WhatsApp > Başlarken sekmesinde yer alan <code>Temporary Access Token</code> veya System User token'ı.
                    </span>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                    <!-- Phone Number ID -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 0.9rem; color: #334155; margin-bottom: 6px; display: block;">
                            Telefon Numarası Kimliği (Phone Number ID) *
                        </label>
                        <input type="text" name="whatsapp_cloud_phone_id" value="<?php echo e($cloudPhoneId); ?>" class="form-control" style="font-size: 0.9rem; padding: 12px 16px; border-radius: 12px; border: 1px solid #cbd5e1;" placeholder="Örn: 105423859238123">
                        <span style="font-size: 0.8rem; color: #94a3b8; margin-top: 4px; display: block;">
                            Örnek: <code>105423859238123</code> (Meta ekranındaki "Phone number ID")
                        </span>
                    </div>

                    <!-- WABA ID -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 0.9rem; color: #334155; margin-bottom: 6px; display: block;">
                            WhatsApp İşletme Hesabı Kimliği (WABA ID)
                        </label>
                        <input type="text" name="whatsapp_cloud_waba_id" value="<?php echo e($cloudWabaId); ?>" class="form-control" style="font-size: 0.9rem; padding: 12px 16px; border-radius: 12px; border: 1px solid #cbd5e1;" placeholder="Örn: 109238492381234">
                        <span style="font-size: 0.8rem; color: #94a3b8; margin-top: 4px; display: block;">
                            Örnek: <code>109238492381234</code> ("WhatsApp Business Account ID")
                        </span>
                    </div>
                </div>

                <div style="margin-top: 10px;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-weight: 700; border-radius: 12px; font-size: 0.95rem; background: #22c55e; border-color: #22c55e; color: #fff;">
                        <i class="fa-solid fa-floppy-disk"></i> Meta Cloud API Ayarlarını Kaydet
                    </button>
                </div>
            </div>
        </div>

        <!-- Section 2: Node.js Service Settings Panel -->
        <div id="web_js_settings_panel" style="background: #fff; padding: 30px; border-radius: 18px; box-shadow: 0 4px 25px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; display: <?php echo ($currentProvider === 'web_js') ? 'block' : 'none'; ?>;">
            <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0 0 15px 0;">
                <i class="fa-solid fa-server" style="color: #2563eb; margin-right: 6px;"></i> Node.js Mikroservis Ayarları
            </h3>
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" style="font-weight: 700; font-size: 0.9rem; color: #334155; margin-bottom: 6px; display: block;">
                    Servis Bağlantı Adresi (Webhook URL)
                </label>
                <input type="text" name="whatsapp_service_url" value="<?php echo e($serviceUrl); ?>" class="form-control" style="font-size: 0.9rem; padding: 12px 16px; border-radius: 12px; border: 1px solid #cbd5e1;" placeholder="http://localhost:3099">
                <span style="font-size: 0.82rem; color: #64748b; margin-top: 6px; display: block;">
                    Varsayılan: <code>http://localhost:3099</code>. Özel VDS sunucunuz varsa IP adresini girebilirsiniz.
                </span>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-weight: 700; border-radius: 12px; font-size: 0.95rem;">
                <i class="fa-solid fa-floppy-disk"></i> Node.js Servis Adresini Kaydet
            </button>
        </div>

    </form>

    <!-- Test Message Sender Card -->
    <?php if ($currentProvider === 'cloud_api'): ?>
        <div style="background: #fff; padding: 30px; border-radius: 18px; box-shadow: 0 4px 25px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; margin-bottom: 30px;">
            <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-paper-plane" style="color: #0284c7;"></i> Canlı Test Mesajı Gönder
            </h2>
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 20px;">
                Meta Cloud API ayarlarınızı doğrulamak için istediğiniz bir telefon numarasına anında test mesajı iletebilirsiniz.
            </p>

            <?php if ($testResult !== null): ?>
                <?php if ($testResult['success']): ?>
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; font-size: 0.9rem;">
                        <i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 1.1rem; margin-right: 6px;"></i> Test mesajı başarıyla WhatsApp'a iletildi!
                    </div>
                <?php else: ?>
                    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem;">
                        <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444; font-size: 1.1rem; margin-right: 6px;"></i> Mesaj Gönderilemedi: <?php echo e($testResult['message']); ?>

                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST" action="whatsapp.php">
                <input type="hidden" name="action" value="send_test_message">
                <div style="display: grid; grid-template-columns: 1fr 2fr auto; gap: 15px; align-items: flex-end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 4px; display: block;">Telefon Numarası *</label>
                        <div style="display: flex; align-items: center; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; overflow: hidden;">
                            <span style="background: #f8fafc; padding: 10px 12px; border-right: 1px solid #cbd5e1; font-weight: 700; font-size: 0.85rem; color: #475569;">🇹🇷 +90</span>
                            <input type="text" name="test_phone" class="form-control" placeholder="5XX XXX XX XX" maxlength="14" style="border: none; border-radius: 0; padding: 10px 14px; font-size: 0.9rem;" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 4px; display: block;">Test Mesaj Metni</label>
                        <input type="text" name="test_message" value="✨ OLiFA Temizlik WhatsApp Cloud API Bağlantısı Başarıyla Kuruldu!" class="form-control" style="padding: 10px 14px; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 0.9rem;">
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary" style="padding: 11px 22px; font-weight: 700; border-radius: 10px; background: #0284c7; border-color: #0284c7; color: #fff;">
                            <i class="fa-solid fa-paper-plane"></i> Gönder
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Rules & Automatic Notifications Info Card -->
    <div style="background: #fff; padding: 25px; border-radius: 18px; box-shadow: 0 4px 25px rgba(0,0,0,0.03); border: 1px solid #e2e8f0;">
        <h3 style="font-size: 1rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 15px 0;">
            <i class="fa-solid fa-bolt" style="color: #f59e0b; margin-right: 6px;"></i> Otomatik Tetiklenen WhatsApp Bildirimleri
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 15px;">
            <div style="background: #f8fafc; padding: 14px 18px; border-radius: 12px; border: 1px solid #f1f5f9;">
                <strong style="color: #0f172a; font-size: 0.9rem; display: block; margin-bottom: 4px;">1. Yeni Randevu Onayı</strong>
                <span style="color: #64748b; font-size: 0.83rem;">Admin bir randevuyu onayladığında müşteriye randevu detayları WhatsApp'tan iletilir.</span>
            </div>
            <div style="background: #f8fafc; padding: 14px 18px; border-radius: 12px; border: 1px solid #f1f5f9;">
                <strong style="color: #0f172a; font-size: 0.9rem; display: block; margin-bottom: 4px;">2. Çalışan Görev Ataması</strong>
                <span style="color: #64748b; font-size: 0.83rem;">Görevlendirilen personele adres, saat, müşteri adı ve tel bilgisi otomatik bildirilir.</span>
            </div>
            <div style="background: #f8fafc; padding: 14px 18px; border-radius: 12px; border: 1px solid #f1f5f9;">
                <strong style="color: #0f172a; font-size: 0.9rem; display: block; margin-bottom: 4px;">3. Teklif Kabulü</strong>
                <span style="color: #64748b; font-size: 0.83rem;">Teklif Onayla & Kaydet butonuna basıldığı an mesaj kuyruğuna eklenir.</span>
            </div>
        </div>
    </div>
</div>

<script>
function switchProvider(provider) {
    const cloudPanel = document.getElementById('cloud_api_settings_panel');
    const webJsPanel = document.getElementById('web_js_settings_panel');
    
    if (provider === 'cloud_api') {
        cloudPanel.style.display = 'block';
        webJsPanel.style.display = 'none';
    } else {
        cloudPanel.style.display = 'none';
        webJsPanel.style.display = 'block';
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
