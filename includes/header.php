<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helper.php';

// Veritabanı ve Sınıfları Yükle
require_once __DIR__ . '/../classes/Category.php';
require_once __DIR__ . '/../classes/Slider.php';
require_once __DIR__ . '/../classes/Package.php';

$categoryModel = new Category();
$sliderModel = new Slider();
$packageModel = new Package();

$allCategories = $categoryModel->getAll(true);

$compName = getSetting('company_name', 'OLiFA Temizlik');
$phone = getSetting('phone', '');
$whatsapp = getSetting('whatsapp', '');
$logoPath = getSetting('logo_path', 'assets/img/olifa_logo.png');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?php echo e($logoPath); ?>">
    
    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <?php 
    // Alt sayfalarda renderSeoTags çağrılacak. Eğer çağrılmadıysa varsayılan etiketler yazılacak.
    if (function_exists('renderSeoTags')) {
        // renderSeoTags() is called inside child pages to allow customization
    }
    ?>
</head>
<body>

    <!-- Animated background waves and lines -->
    <div class="bg-waves-container">
        <div class="wave wave1"></div>
        <div class="wave wave2"></div>
        <div class="wave wave3"></div>
    </div>

    <!-- Header / Nav -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">
                <img src="<?php echo e($logoPath); ?>" alt="<?php echo e($compName); ?>">
            </a>
            
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Ana Sayfa</a></li>
                <li><a href="index.php#hakkimizda" class="nav-link">Hakkımızda</a></li>
                <li><a href="index.php#hizmetler" class="nav-link">Hizmetler</a></li>
                <li><a href="index.php#paketler" class="nav-link">Paketler</a></li>
                <li><a href="index.php#sss" class="nav-link">S.S.S.</a></li>
                <li><a href="index.php#iletisim" class="nav-link">İletişim</a></li>
            </ul>
            
            <div class="header-actions">
                <?php if ($phone): ?>
                    <a href="tel:<?php echo e(str_replace(' ', '', $phone)); ?>" class="contact-btn" title="Telefon Et">
                        <i class="fa-solid fa-phone"></i>
                    </a>
                <?php endif; ?>
                
                <?php if ($whatsapp): ?>
                    <a href="https://wa.me/<?php echo e(preg_replace('/[^0-9]/', '', $whatsapp)); ?>" target="_blank" class="contact-btn whatsapp" title="WhatsApp">
                        <i class="fa-brands fa-whatsapp"></i>
                    </a>
                <?php endif; ?>
                
                <a href="teklif-al.php" class="btn btn-primary">Teklif Al</a>
                
                <!-- Mobile Menu Button -->
                <button class="contact-btn mobile-toggle" style="display: none;" onclick="toggleMobileMenu()">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Mobile Navigation Drawer -->
    <div id="mobile-menu-drawer" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.5); z-index: 1001; backdrop-filter: blur(4px);">
        <div style="width: 280px; height: 100%; background: #ffffff; padding: 30px; display: flex; flex-direction: column; gap: 20px; box-shadow: 0 0 20px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <img src="<?php echo e($logoPath); ?>" alt="<?php echo e($compName); ?>" style="height: 40px;">
                <button onclick="toggleMobileMenu()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <hr style="border: 0; border-top: 1px solid var(--border);">
            <ul style="list-style: none; display: flex; flex-direction: column; gap: 20px; font-weight: 600; font-size: 1.1rem;">
                <li><a href="index.php" onclick="toggleMobileMenu()">Ana Sayfa</a></li>
                <li><a href="index.php#hakkimizda" onclick="toggleMobileMenu()">Hakkımızda</a></li>
                <li><a href="index.php#hizmetler" onclick="toggleMobileMenu()">Hizmetler</a></li>
                <li><a href="index.php#paketler" onclick="toggleMobileMenu()">Paketler</a></li>
                <li><a href="index.php#sss" onclick="toggleMobileMenu()">S.S.S.</a></li>
                <li><a href="index.php#iletisim" onclick="toggleMobileMenu()">İletişim</a></li>
            </ul>
            <div style="margin-top: auto; display: flex; flex-direction: column; gap: 15px;">
                <a href="teklif-al.php" class="btn btn-primary" style="width: 100%;">Teklif Al</a>
                <?php if ($phone): ?>
                    <a href="tel:<?php echo e(str_replace(' ', '', $phone)); ?>" class="btn btn-outline" style="width: 100%;"><i class="fa-solid fa-phone"></i> <?php echo e($phone); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function toggleMobileMenu() {
        const drawer = document.getElementById('mobile-menu-drawer');
        if (drawer.style.display === 'none') {
            drawer.style.display = 'block';
        } else {
            drawer.style.display = 'none';
        }
    }
    
    // Check screen size to show mobile menu button
    function checkWidth() {
        const toggle = document.querySelector('.mobile-toggle');
        const menu = document.querySelector('.nav-menu');
        if (window.innerWidth <= 768) {
            if (toggle) toggle.style.display = 'flex';
            if (menu) menu.style.display = 'none';
        } else {
            if (toggle) toggle.style.display = 'none';
            if (menu) menu.style.display = 'flex';
            const drawer = document.getElementById('mobile-menu-drawer');
            if (drawer) drawer.style.display = 'none';
        }
    }
    window.addEventListener('resize', checkWidth);
    window.addEventListener('DOMContentLoaded', checkWidth);
    </script>
