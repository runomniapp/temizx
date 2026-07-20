<?php
require_once __DIR__ . '/includes/header.php';

// SEO Meta
renderSeoTags(
    'Hızlı Teklif Al',
    'OLiFA Temizlik otomasyon sistemi ile saniyeler içinde ev veya ofis temizliğiniz için teklif alın.',
    'temizlik teklifi, temizlik randevusu, maraş temizlik randevu'
);

// Kategorileri ve Alt Kategorileri JS için JSON olarak hazırla
$db = Database::getConnection();
$categoriesJson = [];
$categoriesQuery = $db->query("SELECT * FROM categories WHERE status = 1 ORDER BY order_num ASC")->fetchAll();

foreach ($categoriesQuery as $cat) {
    $subs = $db->prepare("SELECT * FROM subcategories WHERE category_id = ? AND status = 1 ORDER BY id ASC");
    $subs->execute([$cat['id']]);
    $subList = $subs->fetchAll();
    
    // Paketleri de çek
    $pkgs = $db->prepare("SELECT p.* FROM packages p INNER JOIN category_packages cp ON p.id = cp.package_id WHERE cp.category_id = ? AND p.status = 1");
    $pkgs->execute([$cat['id']]);
    $pkgList = $pkgs->fetchAll();
    foreach ($pkgList as &$p) {
        $p['features'] = $p['features'] ? json_decode($p['features'], true) : [];
    }
    unset($p);
    
    // Sort packages: Yarım Gün first, then Tam Gün. Sub-sort by duration_weeks
    usort($pkgList, function($a, $b) {
        $aIsHalf = (stripos($a['name'], 'yarım') !== false);
        $bIsHalf = (stripos($b['name'], 'yarım') !== false);
        if ($aIsHalf && !$bIsHalf) return -1;
        if (!$aIsHalf && $bIsHalf) return 1;
        
        $aWeeks = (int)($a['duration_weeks'] ?? 1);
        $bWeeks = (int)($b['duration_weeks'] ?? 1);
        return $aWeeks <=> $bWeeks;
    });
    
    $categoriesJson[] = [
        'id' => (int)$cat['id'],
        'name' => $cat['name'],
        'icon' => $cat['icon'],
        'color' => $cat['color'],
        'pricing_type' => $cat['pricing_type'],
        'price' => (float)$cat['price'],
        'half_day_price' => (float)$cat['half_day_price'],
        'max_person' => (int)$cat['max_person'],
        'person_full_price' => (float)$cat['person_full_price'],
        'person_half_price' => (float)$cat['person_half_price'],
        'is_subscription_active' => (int)$cat['is_subscription_active'],
        'subcategories' => $subList,
        'packages' => $pkgList
    ];
}

// Parametreyle gelen varsayılan kategori/paket
$defaultCategoryId = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$defaultPackageId = isset($_GET['paket']) ? (int)$_GET['paket'] : 0;
?>

<section class="wizard-section">
    <div class="container">
        
        <div class="wizard-card">
            <!-- Wizard Header -->
            <div class="wizard-header" style="padding: 30px 20px;">
                <h2 style="font-size: 1.6rem; font-weight: 800;">Rezervasyon ve Teklif Sihirbazı</h2>
                <p style="color: var(--text-muted); margin-top: 6px; font-size: 0.9rem;">Saniyeler içinde temizliğinizi planlayın.</p>
                
                <div class="wizard-progress" style="max-width: 400px; margin: 20px auto 0 auto;">
                    <div class="wizard-progress-bar" id="progressBar"></div>
                    <div class="progress-step active" data-step="1">1</div>
                    <div class="progress-step" data-step="2">2</div>
                    <div class="progress-step" data-step="3">3</div>
                    <div class="progress-step" data-step="4">4</div>
                    <div class="progress-step" data-step="5">5</div>
                </div>
            </div>
            
            <!-- Wizard Body Forms -->
            <form id="wizardForm" class="wizard-body" onsubmit="event.preventDefault(); submitWizardForm();" style="padding: 30px 20px;">
                <?php csrfInput(); ?>
                
                <!-- Hidden inputs to hold state -->
                <input type="hidden" name="category_id" id="input_category_id" value="">
                <input type="hidden" name="subcategory_id" id="input_subcategory_id" value="">
                <input type="hidden" name="package_id" id="input_package_id" value="">
                <input type="hidden" name="booking_date" id="input_booking_date" value="">
                <input type="hidden" name="booking_time_slot" id="input_booking_time_slot" value="">
                <input type="hidden" name="person_count" id="input_person_count" value="2">
                
                <!-- STEP 1: Category Selection (Pill shapes) -->
                <div class="wizard-step active" data-step="1">
                    <h3 class="wizard-step-title" style="font-size: 1.3rem; font-weight: 800; margin-bottom: 6px;">Hizmet Seçimi</h3>
                    <p class="wizard-step-subtitle" style="font-size: 0.9rem; margin-bottom: 30px;">Lütfen almak istediğiniz temizlik hizmetini seçin.</p>
                    
                    <div class="wizard-options-grid service-pills" id="categoryGrid">
                        <!-- Dynamic render as pills -->
                    </div>
                </div>
                
                <!-- STEP 2: Tarih & Saat Seçimi -->
                <div class="wizard-step" data-step="2">
                    <h3 class="wizard-step-title" style="font-size: 1.3rem; font-weight: 800; margin-bottom: 6px;">Temizlik Zamanı</h3>
                    <p class="wizard-step-subtitle" style="font-size: 0.9rem; margin-bottom: 30px;">Lütfen temizlik tarihini ve saat dilimini seçin.</p>
                    
                    <div style="display: flex; flex-direction: column; gap: 20px; max-width: 600px; margin: 0 auto;">
                        <!-- Takvim Kartı -->
                        <div class="card" style="padding: 20px;">
                            <h4 style="font-weight: 800; font-size: 0.95rem; margin-bottom: 15px;"><i class="fa-regular fa-calendar-days" style="color: var(--primary); margin-right: 8px;"></i> Tarih Seçin</h4>
                            <div class="custom-calendar">
                                <div class="calendar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <button type="button" class="calendar-nav-btn" onclick="prevMonth()" style="background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-chevron-left"></i></button>
                                    <span class="calendar-month-title" id="calendarMonthTitle" style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);"></span>
                                    <button type="button" class="calendar-nav-btn" onclick="nextMonth()" style="background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-chevron-right"></i></button>
                                </div>
                                <div class="calendar-grid-days" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center; font-weight: 700; font-size: 0.72rem; color: var(--text-muted); margin-bottom: 8px;">
                                    <div>Pzt</div><div>Sal</div><div>Çar</div><div>Per</div><div>Cum</div><div>Cmt</div><div>Paz</div>
                                </div>
                                <div class="calendar-grid" id="calendarGrid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px;">
                                    <!-- JS generated days -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Saat Dilimi Kartı -->
                        <div class="card" style="padding: 20px;">
                            <h4 id="timeSlotsTitle" style="font-weight: 800; font-size: 0.95rem; margin-bottom: 12px;"><i class="fa-regular fa-clock" style="color: var(--primary); margin-right: 8px;"></i> Saat Dilimi</h4>
                            <div style="display: flex; flex-direction: column; gap: 10px;" id="timeSlotsList">
                                <button type="button" class="time-pill-btn" data-slot="08-12" onclick="selectTimeSlot('08-12')" style="text-align: left; display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                    <span>Yarım Gün (8-12)</span>
                                    <span class="slot-badge-info" id="badge_08-12">Müsait</span>
                                </button>
                                <button type="button" class="time-pill-btn" data-slot="13-17" onclick="selectTimeSlot('13-17')" style="text-align: left; display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                    <span>Yarım Gün (13-17)</span>
                                    <span class="slot-badge-info" id="badge_13-17">Müsait</span>
                                </button>
                                <button type="button" class="time-pill-btn" data-slot="08-17" onclick="selectTimeSlot('08-17')" style="text-align: left; display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                    <span>Tam Gün (8-17)</span>
                                    <span class="slot-badge-info" id="badge_08-17">Müsait</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Süre/Metrekare (Alt Kategori) Kartı - Dinamik Görünecek -->
                        <div class="card" style="padding: 20px; display: none;" id="subcategoriesContainer">
                            <h4 style="font-weight: 800; font-size: 0.95rem; margin-bottom: 12px;"><i class="fa-solid fa-layer-group" style="color: var(--primary); margin-right: 8px;"></i> Süre / Alan Seçimi</h4>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;" id="subcategoriesListPills">
                                <!-- Dynamic subcategories as pills -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- STEP 3: Müşteri Kimlik Bilgileri (Ad Soyad & Telefon) -->
                <div class="wizard-step" data-step="3">
                    <h3 class="wizard-step-title" style="font-size: 1.3rem; font-weight: 800; margin-bottom: 6px;">Kişisel Bilgiler</h3>
                    <p class="wizard-step-subtitle" style="font-size: 0.9rem; margin-bottom: 30px;">Size ulaşabilmemiz için kimlik bilgilerinizi girin.</p>
                    
                    <div style="max-width: 500px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px;">
                        <div class="card" style="padding: 25px;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; margin-bottom: 8px;">Adınız Soyadınız *</label>
                                <input type="text" name="customer_name" id="c_name" class="form-control" placeholder="Ad Soyad" style="padding: 12px 18px; font-size: 0.9rem;" required>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; margin-bottom: 8px;">Telefon Numaranız *</label>
                                <div class="phone-input-group" style="display: flex; align-items: center; border: 1px solid var(--border); border-radius: 14px; background-color: var(--card-bg); overflow: hidden; padding: 0 16px;">
                                    <span style="font-size: 1.1rem; display: flex; align-items: center; gap: 8px; color: var(--text-muted); border-right: 1px solid var(--border); padding-right: 12px; user-select: none;">
                                        <span>🇹🇷</span>
                                        <span style="font-weight: 700; font-size: 0.9rem;">+90</span>
                                    </span>
                                    <input type="tel" name="customer_phone" id="c_phone" class="form-control" placeholder="555 555 55 55" style="border: none; box-shadow: none; padding: 12px 14px; font-weight: 700; font-size: 0.95rem; width: 100%;" maxlength="13" required>
                                </div>
                                <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 6px; display: block;">Lütfen 10 haneli telefon numaranızı girin (örn: 555 555 55 55).</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- STEP 4: İletişim Detayları & Konum (Eposta, Adres, Konum) -->
                <div class="wizard-step" data-step="4">
                    <h3 class="wizard-step-title" style="font-size: 1.3rem; font-weight: 800; margin-bottom: 6px;">İletişim & Konum Detayları</h3>
                    <p class="wizard-step-subtitle" style="font-size: 0.9rem; margin-bottom: 30px;">Hizmetin sunulacağı adresi ve e-posta bilgisini girin.</p>
                    
                    <div style="max-width: 500px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px;">
                        <div class="card" style="padding: 25px; display: flex; flex-direction: column; gap: 15px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; margin-bottom: 8px;">E-posta Adresiniz</label>
                                <input type="email" name="customer_email" id="c_email" class="form-control" placeholder="E-posta (İsteğe bağlı)" style="padding: 12px 18px; font-size: 0.9rem;">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; margin-bottom: 8px;">Temizlik Adresi *</label>
                                <textarea name="customer_address" id="c_address" class="form-control" rows="3" placeholder="Sokak, mahalle, daire no vb. detaylı adres girin" style="padding: 12px 18px; font-size: 0.9rem; border-radius: 14px; resize: none;" required></textarea>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-weight: 700; font-size: 0.85rem; margin-bottom: 8px;">Google Harita Konum Linki</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="url" name="customer_location" id="c_location" class="form-control" placeholder="Harita paylaşım linki" style="padding: 12px 18px; font-size: 0.9rem; width: 100%;">
                                    <button type="button" id="getLocationBtn" class="btn btn-outline" onclick="getGeolocation()" style="padding: 12px 16px; border-radius: 14px; display: flex; align-items: center; gap: 8px; flex-shrink: 0; border-color: var(--primary); color: var(--primary);">
                                        <i class="fa-solid fa-location-crosshairs"></i> Konumumu Bul
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- STEP 5: Özet, Abonelik Önerileri & Fiyatlandırma -->
                <div class="wizard-step" data-step="5">
                    <h3 class="wizard-step-title" style="font-size: 1.3rem; font-weight: 800; margin-bottom: 6px;">Teklif Özeti & Gönderim</h3>
                    <p class="wizard-step-subtitle" style="font-size: 0.9rem; margin-bottom: 30px;">Hizmet detaylarını gözden geçirin, paket önerilerini seçin ve teklifinizi onaylayın.</p>
                    
                    <div class="responsive-step-grid">
                        <div style="display: flex; flex-direction: column; gap: 20px;">
                            <!-- Özet kartı -->
                            <div class="card" style="padding: 22px;">
                                <h4 style="font-weight: 800; font-size: 0.95rem; margin-bottom: 15px; border-bottom: 1px solid var(--border); padding-bottom: 10px;"><i class="fa-solid fa-circle-info" style="color: var(--primary); margin-right: 8px;"></i> Seçimleriniz</h4>
                                <ul style="list-style: none; display: flex; flex-direction: column; gap: 12px; font-size: 0.88rem;">
                                    <li style="display: flex; justify-content: space-between;"><span style="color: var(--text-muted);">Hizmet:</span> <strong id="summary_category">-</strong></li>
                                    <li style="display: flex; justify-content: space-between;"><span style="color: var(--text-muted);">Tarih:</span> <strong id="summary_date">-</strong></li>
                                    <li style="display: flex; justify-content: space-between;"><span style="color: var(--text-muted);">Saat Dilimi:</span> <strong id="summary_time_slot">-</strong></li>
                                    <li style="display: flex; justify-content: space-between;"><span style="color: var(--text-muted);">Ad Soyad:</span> <strong id="summary_customer_name">-</strong></li>
                                    <li style="display: flex; justify-content: space-between;"><span style="color: var(--text-muted);">Telefon:</span> <strong id="summary_customer_phone">-</strong></li>
                                </ul>
                            </div>
                            
                            <!-- Abonelik Önerileri -->
                            <div class="card" style="padding: 20px;" id="subscriptionContainer">
                                <h4 style="font-weight: 800; font-size: 0.95rem; margin-bottom: 12px;"><i class="fa-solid fa-gift" style="color: var(--primary); margin-right: 8px;"></i> Abonelik / Paket Önerisi</h4>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;" id="packagesListPills">
                                    <!-- Dynamic rendered pills -->
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 20px;">
                            <!-- Fiyat kartı -->
                            <div class="card" style="padding: 25px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-color: rgba(37, 99, 235, 0.15); display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;">
                                <div>
                                    <span style="font-size: 0.75rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px;">Hesaplanan Toplam Fiyat</span>
                                    <h3 style="font-size: 2.2rem; font-weight: 900; color: var(--primary); margin-top: 5px;" id="livePriceDisplay">0 ₺</h3>
                                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500; margin-top: 4px; display: block;" id="priceDetailSubtext">Seçimlerinize göre otomatik güncellenir.</span>
                                </div>
                            </div>
                            
                            <div id="bookingErrorAlert" style="display: none; background-color: #fef2f2; color: var(--danger); padding: 12px 16px; border-radius: 10px; font-weight: 600; font-size: 0.85rem; gap: 8px; align-items: center;">
                                <i class="fa-solid fa-triangle-exclamation"></i> <span id="bookingErrorMessage">Hata oluştu!</span>
                            </div>
                            
                            <!-- Gönder Butonu -->
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; border-radius: 14px; font-size: 0.95rem; font-weight: 800; box-shadow: var(--shadow-md);" id="submitOfferBtn">Talep Gönder</button>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Wizard Footer Buttons -->
            <div class="wizard-footer" style="padding: 20px; background-color: #fafbfc; border-top: 1px solid var(--border);">
                <button type="button" class="btn btn-outline" id="prevBtn" onclick="prevStep()" style="visibility: hidden; padding: 8px 18px; font-size: 0.85rem;">Geri</button>
                <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextStep()" style="padding: 8px 18px; font-size: 0.85rem;">Devam Et</button>
            </div>
            
        </div>
        
    </div>
</section>

<!-- Success Modal (4 seconds auto redirect) -->
<div class="admin-modal" id="successModal">
    <div class="modal-content" style="text-align: center; padding: 40px; border-radius: 28px; max-width: 450px;">
        <div style="width: 70px; height: 70px; border-radius: 50%; background-color: #ecfdf5; color: var(--success); display: flex; align-items: center; justify-content: center; font-size: 2.2rem; margin: 0 auto 20px auto;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h3 style="font-size: 1.35rem; font-weight: 800; margin-bottom: 10px;">Teklif Talebi Alındı!</h3>
        <p style="color: var(--text-muted); margin-bottom: 25px; font-size: 0.9rem; line-height: 1.5;">Size çok kısa sürede dönüş yapacağız. Ana sayfaya yönlendiriliyorsunuz...</p>
        <button type="button" id="btnSuccessOk" class="btn btn-primary" style="width: 100%; padding: 10px; border-radius: 12px; font-size: 0.9rem;">Tamam</button>
    </div>
</div>

<!-- Load Wizard Controller script -->
<script>
// JSON verilerini JavaScript ortamına aktar
const categoriesData = <?php echo json_encode($categoriesJson); ?>;
const defaultCategoryId = <?php echo $defaultCategoryId; ?>;
const defaultPackageId = <?php echo $defaultPackageId; ?>;
</script>
<script src="assets/js/wizard.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
