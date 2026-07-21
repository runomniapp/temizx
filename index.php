<?php
require_once __DIR__ . '/includes/header.php';

// SEO Etiketlerini render et
renderSeoTags(
    'Kusursuz Temizlik Otomasyonu',
    getSetting('site_description'),
    getSetting('site_keywords')
);

// Slider verilerini al
$sliders = $sliderModel->getAll(true);

// Paketleri al
$packages = $packageModel->getAll(true);
usort($packages, function($a, $b) {
    $aIsHalf = (stripos($a['name'], 'yarım') !== false);
    $bIsHalf = (stripos($b['name'], 'yarım') !== false);
    if ($aIsHalf && !$bIsHalf) return -1;
    if (!$aIsHalf && $bIsHalf) return 1;
    
    $aWeeks = (int)($a['duration_weeks'] ?? 1);
    $bWeeks = (int)($b['duration_weeks'] ?? 1);
    return $aWeeks <=> $bWeeks;
});

// Yorumları al
$db = Database::getConnection();
$reviews = $db->query("SELECT * FROM reviews WHERE status = 1 ORDER BY id DESC")->fetchAll();

// SSS'leri al
$faqs = $db->query("SELECT * FROM faqs WHERE status = 1 ORDER BY order_num ASC")->fetchAll();
?>

<!-- Hero Slider Section -->
<section class="hero-slider-floating">
    <div class="slider-container">
        <?php foreach ($sliders as $index => $slide): ?>
            <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                <!-- Fallback to placeholder if custom image is not loaded -->
                <?php 
                $imgSrc = $slide['image'];
                if ($slide['id'] == 1 && $imgSrc === 'assets/img/slider1.jpg') {
                    $imgSrc = 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=1400&q=80';
                } else if ($slide['id'] == 2 && $imgSrc === 'assets/img/slider2.jpg') {
                    $imgSrc = 'https://images.unsplash.com/photo-1527515637462-cff94eecc1ac?auto=format&fit=crop&w=1400&q=80';
                }
                ?>
                <img src="<?php echo e($imgSrc); ?>" alt="<?php echo e($slide['title']); ?>" class="slide-img">
                <div class="container" style="height: 100%; display: flex; align-items: center; position: relative;">
                    <div class="slide-content">
                        <h1 class="slide-title"><?php echo e($slide['title']); ?></h1>
                        <p class="slide-subtitle"><?php echo e($slide['subtitle']); ?></p>
                        
                        <div class="slide-actions">
                            <a href="teklif-al.php" class="btn btn-primary" style="box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);"><i class="fa-solid fa-calendar-check"></i> Teklif Al</a>
                            
                            <?php if (!empty($phone)): ?>
                                <a href="tel:<?php echo e(str_replace(' ', '', $phone)); ?>" class="btn btn-outline" style="background-color: rgba(255,255,255,0.12); color: #ffffff; border-color: rgba(255,255,255,0.3); backdrop-filter: blur(10px);"><i class="fa-solid fa-phone"></i> Hemen Ara</a>
                            <?php endif; ?>
                            
                            <?php if (!empty($whatsapp)): ?>
                                <a href="https://wa.me/<?php echo e(preg_replace('/[^0-9]/', '', $whatsapp)); ?>" target="_blank" class="btn btn-secondary" style="background-color: #25d366; color: #ffffff; border: none; box-shadow: 0 8px 20px rgba(37, 211, 102, 0.2);"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="slider-nav">
        <?php foreach ($sliders as $index => $slide): ?>
            <span class="slider-dot <?php echo $index === 0 ? 'active' : ''; ?>" onclick="currentSlide(<?php echo $index; ?>)"></span>
        <?php endforeach; ?>
    </div>
</section>

<!-- Paketler Section -->
<section id="paketler" style="padding: 100px 0; background: linear-gradient(180deg, #f8fafc 0%, #e2eeff 100%); position: relative; overflow: hidden; z-index: 1;">
    <!-- Section Wavy Background Overlay (as requested: waves behind subscription packages) -->
    <div style="position: absolute; bottom: 0; left: 0; width: 100%; height: 160px; pointer-events: none; z-index: -1;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" style="position: absolute; bottom: 0; width: 100%; height: 160px; transform: scaleY(-1); opacity: 0.28;">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V120H0V0C26.9,8.75,55.05,16.27,84.08,22.26,160.74,38.07,242.45,61.9,321.39,56.44Z" fill="#93c5fd"></path>
        </svg>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none" style="position: absolute; bottom: 10px; width: 100%; height: 120px; transform: scaleY(-1); opacity: 0.18;">
            <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V120H0V0C26.9,8.75,55.05,16.27,84.08,22.26,160.74,38.07,242.45,61.9,321.39,56.44Z" fill="#a7f3d0"></path>
        </svg>
    </div>

    <style>
        .pkg-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .pkg-card {
            border-radius: 28px;
            padding: 30px;
            box-shadow: 0 15px 35px -5px rgba(37, 99, 235, 0.05), 0 10px 15px -8px rgba(37, 99, 235, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transform: perspective(1000px) rotateX(0deg) rotateY(0deg) translateZ(0);
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            overflow: hidden;
            z-index: 1;
            cursor: pointer;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
        }
        
        /* Pastel background variations matching Mockup Image 2 */
        .pkg-color-0 { background-color: rgba(239, 246, 255, 0.95); border: 1.5px solid rgba(191, 219, 254, 0.8); }
        .pkg-color-1 { background-color: rgba(240, 253, 244, 0.95); border: 1.5px solid rgba(187, 247, 208, 0.8); }
        .pkg-color-2 { background-color: rgba(250, 245, 255, 0.95); border: 1.5px solid rgba(233, 213, 255, 0.8); }
        .pkg-color-3 { background-color: rgba(255, 251, 235, 0.95); border: 1.5px solid rgba(254, 243, 199, 0.8); }
        .pkg-color-4 { background-color: rgba(255, 241, 242, 0.95); border: 1.5px solid rgba(254, 226, 226, 0.8); }
        .pkg-color-5 { background-color: rgba(240, 253, 250, 0.95); border: 1.5px solid rgba(153, 246, 228, 0.8); }
        
        .pkg-card:hover {
            transform: perspective(1000px) translateY(-8px) translateZ(10px);
            box-shadow: 0 25px 45px -12px rgba(37, 99, 235, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.4);
            border-color: var(--primary);
        }
        
        /* Highlight popular package card border with an elegant primary glow */
        .pkg-card.popular-highlight {
            border: 2px solid var(--primary) !important;
            box-shadow: 0 20px 40px -10px rgba(37, 99, 235, 0.15);
        }
        
        .pkg-icon-badge {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            box-shadow: var(--shadow-sm);
            color: var(--primary);
        }
        
        .pkg-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        
        .pkg-desc {
            font-size: 0.8rem;
            color: #475569;
            line-height: 1.5;
            margin-bottom: 22px;
        }
        
        .pkg-price-wrap {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .pkg-price {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--primary);
            line-height: 1.1;
        }
        
        .pkg-price-old {
            font-size: 0.8rem;
            text-decoration: line-through;
            color: var(--text-muted);
            font-weight: 600;
        }
        
        .pkg-btn-pill {
            background-color: var(--primary);
            color: #ffffff !important;
            border-radius: 50px;
            padding: 8px 18px;
            font-size: 0.8rem;
            font-weight: 800;
            border: none;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.15);
            transition: var(--transition);
            cursor: pointer;
        }
        
        .pkg-btn-pill:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.25);
        }
        
        @media (max-width: 768px) {
            .pkg-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .pkg-card {
                padding: 24px;
                transform: none !important;
            }
            .pkg-card:hover {
                transform: translateY(-4px) !important;
            }
        }
    </style>
    
    <div class="container">
        <div class="section-header" style="margin-bottom: 60px;">
            <span class="section-subtitle" style="letter-spacing: 1px;">DÜZENLİ ABONELİK</span>
            <h2 class="section-title">Avantajlı Temizlik Paketlerimiz</h2>
            <p style="max-width: 600px; margin: 0 auto;">İhtiyacınıza en uygun paketi seçin, indirimli fiyatlarla hemen abone olun. Paket detaylarını incelemek için karta tıklayın.</p>
        </div>
        
        <div class="pkg-grid">
            <?php 
            $colorsCount = 6;
            foreach ($packages as $index => $pkg): 
                $isFeatured = ($pkg['is_popular'] == 1);
                $bgClass = "pkg-color-" . ($index % $colorsCount);
                $perSession = $pkg['duration_weeks'] > 0 ? (float)$pkg['discounted_price'] / $pkg['duration_weeks'] : 0;
                
                // Kategoriye göre ikon seçimi
                $iconClass = 'fa-solid fa-gift';
                if (stripos($pkg['name'], 'yarım') !== false) {
                    $iconClass = 'fa-solid fa-cloud-sun';
                } else if (stripos($pkg['name'], 'tam') !== false) {
                    $iconClass = 'fa-solid fa-sun';
                }
            ?>
                <div class="pkg-card <?php echo $bgClass; ?> <?php echo $isFeatured ? 'popular-highlight' : ''; ?>" onclick="openPackageDetail(<?php echo $pkg['id']; ?>, event)">
                    <!-- Card Top Header (Mockup Image 2) -->
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                        <div class="pkg-icon-badge">
                            <i class="<?php echo $iconClass; ?>"></i>
                        </div>
                        <?php if ($isFeatured): ?>
                            <span style="font-size: 0.62rem; font-weight: 800; background: var(--primary); color: white; padding: 4px 10px; border-radius: 20px; display: flex; align-items: center; gap: 4px; text-transform: uppercase;">
                                <i class="fa-solid fa-star"></i> Popüler
                            </span>
                        <?php else: ?>
                            <div style="width: 8px; height: 8px; border-radius: 50%; background: #64748b; opacity: 0.35;"></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Card Content (Clean and spacious) -->
                    <div>
                        <h3 class="pkg-title"><?php echo e($pkg['name']); ?></h3>
                        <p class="pkg-desc"><?php echo e($pkg['description']); ?></p>
                    </div>
                    
                    <!-- Card Bottom Footer (Mockup Image 2 pricing) -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(0,0,0,0.04); padding-top: 15px; margin-top: 5px;">
                        <div class="pkg-price-wrap">
                            <span class="pkg-price"><?php echo number_format($pkg['discounted_price'], 0, ',', '.'); ?> ₺</span>
                            <span class="pkg-price-old"><?php echo number_format($pkg['normal_price'], 0, ',', '.'); ?> ₺</span>
                        </div>
                        <button type="button" class="pkg-btn-pill">Abone Ol</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Package Detail Modal (Mockup overlay) -->
<div class="admin-modal" id="packageDetailModal">
    <div class="modal-content" style="max-width: 500px; padding: 0; overflow: hidden; border-radius: 28px; border: 1px solid rgba(255,255,255,0.45); background: rgba(255, 255, 255, 0.88); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); box-shadow: var(--shadow-lg);">
        <!-- Header with matching pastel gradient -->
        <div id="modalHeaderBg" style="padding: 35px 30px; position: relative; color: var(--text-main);">
            <span class="modal-close" onclick="closePackageModal()" style="position: absolute; top: 15px; right: 20px; font-size: 1.8rem; cursor: pointer; color: var(--text-muted);">&times;</span>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div id="modalIconContainer" style="width: 48px; height: 48px; border-radius: 14px; background: white; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; box-shadow: var(--shadow-sm); color: var(--primary);">
                    <i class="fa-solid fa-gift"></i>
                </div>
                <span id="modalPopularBadge" style="font-size: 0.65rem; font-weight: 800; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; display: none;"></span>
            </div>
            <h3 id="modalPackageName" style="font-size: 1.4rem; font-weight: 800; line-height: 1.3; color: #0f172a;"></h3>
            <p id="modalPackageDesc" style="font-size: 0.85rem; color: #475569; margin-top: 6px; line-height: 1.45;"></p>
        </div>
        
        <div style="padding: 25px 30px 30px 30px; display: flex; flex-direction: column; gap: 20px;">
            <!-- Price Summary Box -->
            <div style="background: white; border: 1px solid var(--border); border-radius: 18px; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm);">
                <div>
                    <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Paket Fiyatı</span>
                    <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 2px;">
                        <span id="modalDiscountedPrice" style="font-size: 1.7rem; font-weight: 900; color: var(--primary);"></span>
                        <span id="modalNormalPrice" style="font-size: 0.9rem; text-decoration: line-through; color: var(--text-muted);"></span>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span id="modalPerSessionPrice" style="font-size: 0.75rem; color: var(--success); font-weight: 700; background: #e6fdf5; padding: 5px 12px; border-radius: 20px; display: inline-block;"></span>
                </div>
            </div>
            
            <!-- Features List -->
            <div>
                <h4 style="font-size: 0.9rem; font-weight: 800; margin-bottom: 12px; color: #0f172a;"><i class="fa-solid fa-list-check" style="color: var(--primary); margin-right: 8px;"></i> Paket İçeriği & Hizmetler</h4>
                <ul id="modalFeaturesList" style="list-style: none; display: flex; flex-direction: column; gap: 10px; padding: 0; margin: 0;">
                    <!-- Filled dynamically -->
                </ul>
            </div>
            
            <!-- Action Button -->
            <a id="modalSubscribeBtn" href="#" class="btn btn-primary" style="width: 100%; padding: 12px; font-weight: 800; border-radius: 14px; text-align: center; box-shadow: var(--shadow-md); color: #ffffff !important;">Hemen Abone Ol</a>
        </div>
    </div>
</div>

<script>
const packagesDataList = <?php echo json_encode($packages); ?>;

function openPackageDetail(id, event) {
    if (event) event.stopPropagation();
    const pkg = packagesDataList.find(p => parseInt(p.id) === parseInt(id));
    if (!pkg) return;
    
    document.getElementById("modalPackageName").innerText = pkg.name;
    document.getElementById("modalPackageDesc").innerText = pkg.description;
    
    const discountedPriceFormatted = parseFloat(pkg.discounted_price).toLocaleString('tr-TR') + ' ₺';
    const normalPriceFormatted = parseFloat(pkg.normal_price).toLocaleString('tr-TR') + ' ₺';
    document.getElementById("modalDiscountedPrice").innerText = discountedPriceFormatted;
    document.getElementById("modalNormalPrice").innerText = normalPriceFormatted;
    
    const perSession = pkg.duration_weeks > 0 ? (parseFloat(pkg.discounted_price) / parseInt(pkg.duration_weeks)) : 0;
    document.getElementById("modalPerSessionPrice").innerText = "Seans Başı: " + Math.round(perSession).toLocaleString('tr-TR') + ' ₺';
    
    // Features list
    const featuresList = document.getElementById("modalFeaturesList");
    featuresList.innerHTML = "";
    try {
        const features = JSON.parse(pkg.features);
        if (Array.isArray(features)) {
            features.forEach(ft => {
                const li = document.createElement("li");
                li.style.display = "flex";
                li.style.alignItems = "flex-start";
                li.style.gap = "10px";
                li.style.fontSize = "0.85rem";
                li.style.color = "#334155";
                li.innerHTML = `<i class="fa-solid fa-circle-check" style="color: var(--primary); font-size: 0.95rem; margin-top: 3px;"></i> <span>${ft}</span>`;
                featuresList.appendChild(li);
            });
        }
    } catch(e) {
        console.error("Features parsing error: ", e);
    }
    
    // Set Subscribe link
    document.getElementById("modalSubscribeBtn").href = `teklif-al.php?paket=${pkg.id}`;
    document.getElementById("modalSubscribeBtn").innerText = "Hemen Abone Ol";
    
    // Icon badge adjustment
    let iconClass = 'fa-solid fa-gift';
    if (pkg.name.toLowerCase().includes('yarım')) {
        iconClass = 'fa-solid fa-cloud-sun';
    } else if (pkg.name.toLowerCase().includes('tam')) {
        iconClass = 'fa-solid fa-sun';
    }
    document.getElementById("modalIconContainer").innerHTML = `<i class="${iconClass}"></i>`;
    
    // Set matching background header gradient based on ID/index
    const colors = [
        "linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%)", // blue
        "linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)", // green
        "linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%)", // purple
        "linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%)", // yellow
        "linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%)", // pink
        "linear-gradient(135deg, #f0f3ff 0%, #e0e7ff 100%)"  // indigo
    ];
    const index = pkg.id % colors.length;
    document.getElementById("modalHeaderBg").style.background = colors[index];
    
    // Popular badge
    const pop = document.getElementById("modalPopularBadge");
    if (pkg.is_popular == 1) {
        pop.style.display = "inline-block";
        pop.innerHTML = '<i class="fa-solid fa-star"></i> En Popüler';
        pop.style.backgroundColor = "var(--primary)";
        pop.style.color = "white";
    } else {
        pop.style.display = "none";
    }
    
    document.getElementById("packageDetailModal").classList.add("active");
}

function closePackageModal() {
    document.getElementById("packageDetailModal").classList.remove("active");
}
</script>

<!-- Hakkımızda Section -->
<section id="hakkimizda" class="floating-section">
    <div class="container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 50px; align-items: center;">
        <div>
            <span class="section-subtitle">BİZ KİMİZ?</span>
            <h2 class="section-title" style="margin-bottom: 25px;">Maraş'ın En Güvenilir Temizlik Çözüm Ortağı</h2>
            <p style="margin-bottom: 20px; font-size: 1.05rem;">OLiFA Temizlik, kurulduğu günden bu yana Kahramanmaraş genelinde kaliteden ödün vermeden, müşteri memnuniyeti odaklı profesyonel temizlik hizmetleri sunmaktadır.</p>
            <p style="margin-bottom: 30px;">Geliştirdiğimiz özel otomasyon altyapısı sayesinde temizlik randevularınızı saniyeler içinde planlıyor, uygun personelleri otomatik olarak atayarak takviminizi güvence altına alıyoruz. Tüm personellerimiz sigortalı, güvenlik sorgulamasından geçmiş ve alanında uzmandır.</p>
            <div style="display: flex; gap: 30px;">
                <div>
                    <h3 style="font-size: 2.25rem; color: var(--primary); font-weight: 800;">5K+</h3>
                    <p style="font-weight: 600; font-size: 0.9rem;">Mutlu Müşteri</p>
                </div>
                <div style="border-left: 2px solid var(--border); padding-left: 30px;">
                    <h3 style="font-size: 2.25rem; color: var(--primary); font-weight: 800;">8+</h3>
                    <p style="font-weight: 600; font-size: 0.9rem;">Profesyonel Ekip</p>
                </div>
            </div>
        </div>
        <div style="position: relative;">
            <img src="https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=800&q=80" alt="Hakkımızda" style="width: 100%; border-radius: var(--radius-card); box-shadow: var(--shadow-lg);">
            <div style="position: absolute; bottom: -20px; left: -20px; background-color: var(--primary); color: #ffffff; padding: 20px 30px; border-radius: 20px; box-shadow: var(--shadow-md); z-index: 5;">
                <h4 style="color: #ffffff; font-size: 1.1rem; margin-bottom: 5px;">7/24 Destek</h4>
                <p style="color: rgba(255,255,255,0.8); font-size: 0.85rem; font-weight: 500;">Her an yanınızdayız.</p>
            </div>
        </div>
    </div>
</section>

<!-- Hizmetler Section -->
<section id="hizmetler" class="floating-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">HİZMETLERİMİZ</span>
            <h2 class="section-title">Size Özel Temizlik Çözümleri</h2>
            <p>Eviniz, ofisiniz veya özel mülkleriniz için ihtiyacınıza en uygun hizmeti seçin, gerisini profesyonel ekiplerimize bırakın.</p>
        </div>
        
        <div class="services-grid">
            <?php foreach ($allCategories as $cat): ?>
                <div class="service-card">
                    <div class="service-img-wrapper">
                        <?php 
                        $catImg = $cat['image'];
                        if ($cat['slug'] === 'ev-temizligi') {
                            $catImg = 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=600&q=80';
                        } else if ($cat['slug'] === 'ofis-temizligi') {
                            $catImg = 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=600&q=80';
                        } else if ($cat['slug'] === 'insaat-sonrasi') {
                            $catImg = 'https://images.unsplash.com/photo-1628177142898-93e36e4e3a50?auto=format&fit=crop&w=600&q=80';
                        } else if ($cat['slug'] === 'villa-temizligi') {
                            $catImg = 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=600&q=80';
                        } else if ($cat['slug'] === 'cam-temizligi') {
                            $catImg = 'https://images.unsplash.com/photo-1527515637462-cff94eecc1ac?auto=format&fit=crop&w=600&q=80';
                        } else if ($cat['slug'] === 'koltuk-temizligi') {
                            $catImg = 'https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&w=600&q=80';
                        }
                        ?>
                        <img src="<?php echo e($catImg); ?>" alt="<?php echo e($cat['name']); ?>" class="service-card-img" loading="lazy">
                        <div class="service-card-icon" style="background-color: <?php echo e($cat['color']); ?>;">
                            <?php if ($cat['icon'] === 'home'): ?>
                                <i class="fa-solid fa-house"></i>
                            <?php elseif ($cat['icon'] === 'briefcase'): ?>
                                <i class="fa-solid fa-briefcase"></i>
                            <?php elseif ($cat['icon'] === 'hammer'): ?>
                                <i class="fa-solid fa-trowel-bricks"></i>
                            <?php elseif ($cat['icon'] === 'building'): ?>
                                <i class="fa-solid fa-hotel"></i>
                            <?php elseif ($cat['icon'] === 'eye'): ?>
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            <?php elseif ($cat['icon'] === 'coffee'): ?>
                                <i class="fa-solid fa-couch"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-check"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="service-card-body">
                        <h3 class="service-card-title"><?php echo e($cat['name']); ?></h3>
                        <p class="service-card-desc"><?php echo e($cat['description']); ?></p>
                        <div class="service-card-footer">
                            <span class="service-price">
                                <?php if ($cat['pricing_type'] === 'category'): ?>
                                    <?php echo e(formatPrice($cat['price'])); ?>'den başlayan
                                <?php elseif ($cat['pricing_type'] === 'discovery'): ?>
                                    Keşif Sonrası Fiyatlandırılır
                                <?php else: ?>
                                    Detaylı Fiyatlandırma
                                <?php endif; ?>
                            </span>
                            <a href="teklif-al.php?kategori=<?php echo e($cat['id']); ?>" class="btn btn-secondary" style="padding: 8px 18px; font-size: 0.85rem;">Teklif Al</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Avantajlar / Neden Biz Section -->
<section class="floating-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">AVANTAJLARIMIZ</span>
            <h2 class="section-title">Neden OLiFA Temizlik?</h2>
            <p>Kahramanmaraş'ta temizlik standartlarını yeniden tanımlıyoruz. Bizimle çalışmanız için en önemli nedenler.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
            <div class="card" style="text-align: center; border: none; background-color: var(--background);">
                <div style="width: 70px; height: 70px; border-radius: 50%; background-color: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 25px auto;">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <h3 style="font-size: 1.25rem; margin-bottom: 12px;">Güvenilir & SGK'lı Personel</h3>
                <p>Tüm ekibimiz referanslı, gerekli eğitimleri almış ve SGK güvencesinde çalışan kadrolu personellerimizdir.</p>
            </div>
            
            <div class="card" style="text-align: center; border: none; background-color: var(--background);">
                <div style="width: 70px; height: 70px; border-radius: 50%; background-color: #e6fdf5; color: var(--success); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 25px auto;">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <h3 style="font-size: 1.25rem; margin-bottom: 12px;">Otomatik Planlama</h3>
                <p>Sizin için en uygun personelleri takvim çakışmalarını hesaplayarak otomatik atar, aksaklıkları önleriz.</p>
            </div>
            
            <div class="card" style="text-align: center; border: none; background-color: var(--background);">
                <div style="width: 70px; height: 70px; border-radius: 50%; background-color: #e6f7ed; color: #25d366; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 25px auto;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <h3 style="font-size: 1.25rem; margin-bottom: 12px;">Profesyonel Malzemeler</h3>
                <p>Temizlik işlemlerinde sağlığa zarar vermeyen, yüksek hijyen sağlayan ithal ve çevre dostu ürünler kullanırız.</p>
            </div>
            
            <div class="card" style="text-align: center; border: none; background-color: var(--background);">
                <div style="width: 70px; height: 70px; border-radius: 50%; background-color: #fff8e6; color: var(--warning); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 25px auto;">
                    <i class="fa-solid fa-shield-heart"></i>
                </div>
                <h3 style="font-size: 1.25rem; margin-bottom: 12px;">%100 Memnuniyet</h3>
                <p>Yapılan işi beğenmediğiniz takdirde, sorunu çözmek adına ekibimizle gerekli müdahaleyi anında yapıyoruz.</p>
            </div>
        </div>
    </div>
</section>

<!-- İşleyiş / Nasıl Çalışır Section -->
<section class="floating-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">KOLAY REZERVASYON</span>
            <h2 class="section-title">Süreç Nasıl İşler?</h2>
            <p>Evinizi temizletmek OLiFA ile sadece 3 basit adımdan ibarettir.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 40px; position: relative;">
            <div style="text-align: center; position: relative;">
                <span style="font-size: 5rem; font-weight: 800; color: rgba(0, 102, 255, 0.08); position: absolute; top: -30px; left: 50%; transform: translateX(-50%); z-index: 1;">01</span>
                <div style="position: relative; z-index: 2; margin-top: 30px;">
                    <h3 style="font-size: 1.35rem; margin-bottom: 12px;">Teklifini Al</h3>
                    <p>Web sitemiz üzerinden kategori, tarih, saat ve paketini seçerek saniyeler içinde teklif al veya rezervasyon yap.</p>
                </div>
            </div>
            
            <div style="text-align: center; position: relative;">
                <span style="font-size: 5rem; font-weight: 800; color: rgba(0, 102, 255, 0.08); position: absolute; top: -30px; left: 50%; transform: translateX(-50%); z-index: 1;">02</span>
                <div style="position: relative; z-index: 2; margin-top: 30px;">
                    <h3 style="font-size: 1.35rem; margin-bottom: 12px;">Onay & Atama</h3>
                    <p>Yönetim panelimiz takviminize en uygun ve müsait personelleri otomatik olarak rezerve eder.</p>
                </div>
            </div>
            
            <div style="text-align: center; position: relative;">
                <span style="font-size: 5rem; font-weight: 800; color: rgba(0, 102, 255, 0.08); position: absolute; top: -30px; left: 50%; transform: translateX(-50%); z-index: 1;">03</span>
                <div style="position: relative; z-index: 2; margin-top: 30px;">
                    <h3 style="font-size: 1.35rem; margin-bottom: 12px;">Kusursuz Temizlik</h3>
                    <p>Belirlenen gün ve saatte ekibimiz adresinize gelir ve temizlik işlemini mükemmel seviyede tamamlar.</p>
                </div>
            </div>
        </div>
    </div>
</section>



<!-- Müşteri Yorumları Section -->
<section class="floating-section">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">MÜŞTERİ YORUMLARI</span>
            <h2 class="section-title">Kullanıcılarımız Ne Diyor?</h2>
            <p>Ekiplerimizden hizmet alan değerli müşterilerimizin hakkımızdaki görüşleri.</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px;">
            <?php foreach ($reviews as $rev): ?>
                <div class="card" style="border: none;">
                    <div style="display: flex; gap: 5px; color: #ffc107; margin-bottom: 15px;">
                        <?php for ($i = 0; $i < $rev['rating']; $i++): ?>
                            <i class="fa-solid fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <p style="font-size: 0.95rem; line-height: 1.7; margin-bottom: 20px; font-style: italic;">"<?php echo e($rev['comment']); ?>"</p>
                    <h4 style="font-size: 1rem; font-weight: 700;"><?php echo e($rev['name']); ?></h4>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- SSS Section -->
<section id="sss" class="floating-section">
    <div class="container" style="max-width: 800px;">
        <div class="section-header">
            <span class="section-subtitle">SADECE MERAK ETTİKLERİNİZ</span>
            <h2 class="section-title">Sıkça Sorulan Sorular</h2>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 15px;">
            <?php foreach ($faqs as $index => $faq): ?>
                <div style="border: 1px solid var(--border); border-radius: 16px; overflow: hidden; transition: var(--transition);">
                    <button class="faq-toggle" onclick="toggleFaq(<?php echo $index; ?>)" style="width: 100%; display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; background: none; border: none; font-weight: 700; font-size: 1.05rem; text-align: left; cursor: pointer; color: var(--text-main); transition: var(--transition);">
                        <span><?php echo e($faq['question']); ?></span>
                        <i class="fa-solid fa-chevron-down" style="font-size: 0.9rem; transition: transform 0.3s ease;"></i>
                    </button>
                    <div class="faq-content" id="faq-content-<?php echo $index; ?>" style="max-height: 0; overflow: hidden; transition: max-height 0.3s ease; background-color: #fcfdfe;">
                        <p style="padding: 0 25px 25px 25px; font-size: 0.95rem; line-height: 1.7;"><?php echo e($faq['answer']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- İletişim Section -->
<section id="iletisim" style="padding: 100px 0; background-color: var(--background);">
    <div class="container" style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 50px;">
        <!-- Left Side: Map and Contact Info -->
        <div>
            <span class="section-subtitle">BİZE ULAŞIN</span>
            <h2 class="section-title" style="margin-bottom: 30px;">Hemen İletişime Geçin</h2>
            <p style="margin-bottom: 40px;">Hizmetlerimiz hakkında daha fazla bilgi almak, özel projeleriniz için fiyat teklifi istemek veya diğer tüm sorularınız için bize yazın ya da doğrudan arayın.</p>
            
            <div style="margin-bottom: 40px; border-radius: 20px; overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--border);">
                <?php echo getSetting('maps_iframe'); ?>
            </div>
        </div>
        
        <!-- Right Side: Contact Form -->
        <div class="card" style="border: none; align-self: start;">
            <h3 style="font-size: 1.35rem; margin-bottom: 25px;">Mesaj Bırakın</h3>
            
            <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
                <div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
                    <i class="fa-solid fa-circle-check"></i> Mesajınız başarıyla iletildi. En kısa sürede döneceğiz.
                </div>
            <?php endif; ?>
            
            <form action="index.php#iletisim" method="POST">
                <?php csrfInput(); ?>
                
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $csrf = $_POST['csrf_token'] ?? '';
                    if (verifyCsrfToken($csrf)) {
                        $name = trim($_POST['name'] ?? '');
                        $phone = trim($_POST['phone'] ?? '');
                        $msg = trim($_POST['message'] ?? '');
                        
                        if ($name && $phone && $msg) {
                            // In a real application, you'd send an email or store in DB.
                            // For this project, redirect to show success state.
                            redirect("index.php?status=success#iletisim");
                        }
                    }
                }
                ?>
                
                <div class="form-group">
                    <label class="form-label" for="name">Adınız Soyadınız *</label>
                    <input type="text" name="name" id="name" class="form-control" placeholder="Örn. Ahmet Yılmaz" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="form_phone">Telefon Numaranız *</label>
                    <input type="tel" name="phone" id="form_phone" class="form-control" placeholder="555-555-55-55" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="message">Mesajınız *</label>
                    <textarea name="message" id="message" class="form-control" rows="4" placeholder="Nasıl yardımcı olabiliriz?" style="border-radius: 20px; resize: none;" required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Mesajı Gönder</button>
            </form>
        </div>
    </div>
</section>

<!-- Slider & Accordion Javascript -->
<script>
let slideIndex = 0;
let slides = document.querySelectorAll(".slide");
let dots = document.querySelectorAll(".slider-dot");
let slideInterval = setInterval(nextSlide, 6000);

function showSlide(index) {
    if (slides.length === 0) return;
    
    if (index >= slides.length) { slideIndex = 0; }
    else if (index < 0) { slideIndex = slides.length - 1; }
    else { slideIndex = index; }
    
    slides.forEach((slide, i) => {
        if (i === slideIndex) {
            slide.classList.add("active");
            dots[i].classList.add("active");
        } else {
            slide.classList.remove("active");
            dots[i].classList.remove("active");
        }
    });
}

function nextSlide() {
    showSlide(slideIndex + 1);
}

function currentSlide(index) {
    clearInterval(slideInterval);
    showSlide(index);
    slideInterval = setInterval(nextSlide, 6000);
}

function toggleFaq(index) {
    const content = document.getElementById("faq-content-" + index);
    const wrapper = content.parentElement;
    const chevron = wrapper.querySelector(".fa-chevron-down");
    
    if (content.style.maxHeight && content.style.maxHeight !== "0px") {
        content.style.maxHeight = "0px";
        chevron.style.transform = "rotate(0deg)";
        wrapper.style.borderColor = "var(--border)";
    } else {
        // Close other FAQs first
        document.querySelectorAll(".faq-content").forEach((c, idx) => {
            c.style.maxHeight = "0px";
            const icon = c.parentElement.querySelector(".fa-chevron-down");
            if (icon) icon.style.transform = "rotate(0deg)";
            c.parentElement.style.borderColor = "var(--border)";
        });
        
        content.style.maxHeight = content.scrollHeight + "px";
        chevron.style.transform = "rotate(180deg)";
        wrapper.style.borderColor = "var(--primary)";
    }
}

// Telefon Giriş Formatlayıcı (3-3-2-2)
const formPhoneInput = document.getElementById("form_phone");
if (formPhoneInput) {
    formPhoneInput.placeholder = "555 555 55 55";
    formPhoneInput.maxLength = 13;
    formPhoneInput.addEventListener("input", () => {
        let value = formPhoneInput.value.replace(/\D/g, "");
        if (value.startsWith("0")) value = value.substring(1);
        if (value.length > 10) value = value.substring(0, 10);
        let formatted = "";
        if (value.length > 0) formatted += value.substring(0, 3);
        if (value.length > 3) formatted += " " + value.substring(3, 6);
        if (value.length > 6) formatted += " " + value.substring(6, 8);
        if (value.length > 8) formatted += " " + value.substring(8, 10);
        formPhoneInput.value = formatted;
    });
    formPhoneInput.addEventListener("keydown", (e) => {
        const allowedKeys = ["Backspace", "Delete", "Tab", "ArrowLeft", "ArrowRight", "Enter", "Control", "a", "c", "v", "x"];
        if (allowedKeys.includes(e.key) || (e.ctrlKey && ["a", "c", "v", "x"].includes(e.key.toLowerCase()))) return;
        if (!/\d/.test(e.key)) e.preventDefault();
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
