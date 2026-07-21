<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../classes/WhatsAppService.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_whatsapp_url') {
    $url = trim($_POST['whatsapp_service_url']);
    if (!empty($url)) {
        updateSetting('whatsapp_service_url', $url);
        $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-weight: 600;"><i class="fa-solid fa-circle-check"></i> WhatsApp Servis adresi başarıyla güncellendi!</div>';
    }
}

$statusData = WhatsAppService::getStatus();
$status = $statusData['status'] ?? 'OFFLINE';
$currentServiceUrl = WhatsAppService::getServiceUrl();
?>

<div class="main-content">
    <?php echo $msg; ?>

    <div class="content-header" style="margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #1e293b; margin-bottom: 6px;">
                <i class="fa-brands fa-whatsapp" style="color: #22c55e; margin-right: 8px;"></i> WhatsApp Web Entegrasyonu
            </h1>
            <p style="color: #64748b; font-size: 0.95rem;">
                Müşteri ve çalışan WhatsApp bildirim servisinin durumunu ve QR kod bağlantısını buradan yönetebilirsiniz.
            </p>
        </div>
        <div>
            <button onclick="location.reload();" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; font-weight: 600; cursor: pointer; border: 1px solid #cbd5e1; background: #fff; color: #334155;">
                <i class="fa-solid fa-rotate-right"></i> Durumu Yenile
            </button>
        </div>
    </div>

    <!-- Status Cards Container -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-bottom: 30px;">
        
        <!-- Connection Status Card -->
        <div style="background: #fff; padding: 24px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #e2e8f0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <span style="font-weight: 700; color: #475569; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Bağlantı Durumu</span>
                <?php if ($status === 'READY'): ?>
                    <span style="background: #dcfce7; color: #15803d; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                        <span style="width: 8px; height: 8px; background: #22c55e; border-radius: 50%; display: inline-block;"></span> Aktif & Bağlı
                    </span>
                <?php elseif ($status === 'AUTHENTICATED'): ?>
                    <span style="background: #e0f2fe; color: #0369a1; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-spinner fa-spin" style="font-size: 0.8rem;"></i> Doğrulandı (Hazırlanıyor)
                    </span>
                <?php elseif ($status === 'QR_READY'): ?>
                    <span style="background: #fef3c7; color: #b45309; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                        <span style="width: 8px; height: 8px; background: #f59e0b; border-radius: 50%; display: inline-block;"></span> QR Kod Bekleniyor
                    </span>
                <?php elseif ($status === 'INITIALIZING'): ?>
                    <span style="background: #fef3c7; color: #b45309; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-spinner fa-spin" style="font-size: 0.8rem;"></i> Başlatılıyor...
                    </span>
                <?php else: ?>
                    <span style="background: #fee2e2; color: #b91c1c; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                        <span style="width: 8px; height: 8px; background: #ef4444; border-radius: 50%; display: inline-block;"></span> Çevrimdışı
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($status === 'READY' && !empty($statusData['info'])): ?>
                <div style="background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #f1f5f9;">
                    <div style="font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
                        <?php echo e($statusData['info']['name'] ?? 'Olifa WhatsApp Hesabı'); ?>
                    </div>
                    <div style="font-size: 0.9rem; color: #64748b;">
                        Tel: +<?php echo e($statusData['info']['phone'] ?? '-'); ?>
                    </div>
                </div>
            <?php elseif ($status === 'OFFLINE'): ?>
                <p style="color: #64748b; font-size: 0.9rem; line-height: 1.5; margin: 0;">
                    Node.js WhatsApp mikroservisi çalışmıyor. Başlatmak için terminalde komutu çalıştırın:
                </p>
                <code style="display: block; background: #0f172a; color: #38bdf8; padding: 10px 14px; border-radius: 8px; margin-top: 10px; font-size: 0.85rem;">
                    cd whatsapp-service && npm start
                </code>
            <?php elseif ($status === 'INITIALIZING'): ?>
                <p style="color: #b45309; font-size: 0.9rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-spinner fa-spin"></i> WhatsApp sanal tarayıcı başlatılıyor. QR Kod hazırlanıyor, lütfen birkaç saniye bekleyin...
                </p>
            <?php else: ?>
                <p style="color: #64748b; font-size: 0.9rem; margin: 0;">
                    WhatsApp servisi başlatıldı, telefonunuz ile aşağıdaki QR kodu taratmanız beklenmektedir.
                </p>
            <?php endif; ?>
        </div>

        <!-- Automatic Triggers Info Card -->
        <div style="background: #fff; padding: 24px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #e2e8f0;">
            <span style="font-weight: 700; color: #475569; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 16px;">Otomatik Bildirim Kuralları</span>
            <ul style="padding-left: 20px; margin: 0; color: #334155; font-size: 0.9rem; line-height: 1.8;">
                <li><strong>Yeni Randevu:</strong> Admin onaylı bir randevu eklediğinde tetiklenir.</li>
                <li><strong>Teklif Onayı:</strong> Bekleyen bir teklif onaylandığında (`confirmed`) tetiklenir.</li>
                <li><strong>Çalışana Gönderim:</strong> Görevli personele tarih, saat, müşteri adı ve adres iletilir.</li>
                <li><strong>Müşteriye Gönderim:</strong> Müşteriye tarih, saat, görevli ekip isimleri ve Olifa Temizlik teşekkür mesajı iletilir.</li>
            </ul>
        </div>

        <!-- Service URL Settings Card -->
        <div style="background: #fff; padding: 24px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; grid-column: 1 / -1;">
            <span style="font-weight: 700; color: #475569; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 12px;">Servis Bağlantı Adresi (Webhook URL)</span>
            <form method="POST" style="display: flex; gap: 12px; align-items: center;">
                <input type="hidden" name="action" value="save_whatsapp_url">
                <input type="text" name="whatsapp_service_url" value="<?php echo e($currentServiceUrl); ?>" class="form-input" style="flex: 1; padding: 10px 14px; border-radius: 10px; border: 1px solid #cbd5e1;" placeholder="http://localhost:3000">
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-weight: 700; border-radius: 10px;">Kaydet</button>
            </form>
            <p style="color: #64748b; font-size: 0.82rem; margin-top: 8px; margin-bottom: 0;">
                Varsayılan: <code>http://localhost:3000</code>. Eğer servisi ayrı bir VDS sunucuda veya yerel bilgisayarınızda (ngrok / sabit IP) çalıştırıyorsanız adresi buraya yazabilirsiniz.
            </p>
        </div>
    </div>

    <!-- QR Code Scan Section -->
    <?php if ($status === 'QR_READY' || $status === 'READY' || $status === 'INITIALIZING'): ?>
        <div style="background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; text-align: center;">
            <h2 style="font-size: 1.3rem; font-weight: 700; color: #0f172a; margin-bottom: 15px;">
                <i class="fa-solid fa-qrcode" style="color: #0284c7; margin-right: 8px;"></i> WhatsApp Web QR Kodu
            </h2>
            
            <?php if ($status === 'QR_READY' && !empty($statusData['qr'])): ?>
                <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 20px;">
                    Telefonunuzda WhatsApp'ı açıp <strong>Bağlı Cihazlar > Cihaz Bağla</strong> kısmından QR kodunu okutun:
                </p>
                <div style="display: inline-block; padding: 16px; background: #fff; border-radius: 16px; border: 2px dashed #cbd5e1; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
                    <img src="<?php echo $statusData['qr']; ?>" alt="WhatsApp QR Code" style="width: 260px; height: 260px; display: block; margin: 0 auto;">
                </div>
                <p style="color: #94a3b8; font-size: 0.85rem; margin-top: 15px;">
                    QR kod otomatik güncellenir. Tarattıktan sonra sayfayı yenileyiniz.
                </p>
            <?php elseif ($status === 'READY'): ?>
                <div style="padding: 30px; background: #f0fdf4; border-radius: 16px; color: #166534; display: inline-block; max-width: 500px;">
                    <i class="fa-solid fa-circle-check" style="font-size: 3rem; color: #22c55e; margin-bottom: 12px;"></i>
                    <h3 style="margin: 0 0 6px 0; font-size: 1.2rem;">WhatsApp Web Başarıyla Bağlandı!</h3>
                    <p style="margin: 0; font-size: 0.9rem; color: #15803d;">
                        Oluşturulan ve onaylanan tüm randevularda WhatsApp mesajları müşterilere ve çalışanlara otomatik iletilecektir.
                    </p>
                </div>
            <?php else: ?>
                <div style="padding: 40px; background: #f8fafc; border-radius: 16px; color: #475569; display: inline-block; max-width: 500px; border: 1px dashed #cbd5e1;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size: 3rem; color: #f59e0b; margin-bottom: 15px;"></i>
                    <h3 style="margin: 0 0 6px 0; font-size: 1.1rem; color: #0f172a;">QR Kod Oluşturuluyor...</h3>
                    <p style="margin: 0; font-size: 0.88rem; color: #64748b;">
                        WhatsApp sanal tarayıcısı başlatılıyor. Lütfen birkaç saniye bekleyin, sayfa otomatik olarak QR kodu gösterecektir.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($status === 'INITIALIZING' || $status === 'QR_READY'): ?>
<script>
// Auto-refresh page every 4 seconds until connected
setTimeout(function() {
    location.reload();
}, 4000);
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
