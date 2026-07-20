<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
$auth->requireLogin(); // Giriş yapılmamışsa login.php'ye atar

$compName = getSetting('company_name', 'OLiFA Temizlik');
$logoPath = '../' . getSetting('logo_path', 'assets/img/olifa_logo.png');
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | <?php echo e($compName); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e($logoPath); ?>">
    
    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

    <!-- Animated Wave Background -->
    <div class="wave-bg-container">
        <div class="wave"></div>
        <div class="wave"></div>
        <div class="wave"></div>
    </div>

    <!-- Sidebar Menu -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-logo">
            <a href="/admin/index.php">
                <img src="<?php echo e($logoPath); ?>" alt="<?php echo e($compName); ?>">
            </a>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="/admin/index.php" class="menu-link <?php echo isMenuActive('index.php'); ?>">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="/admin/takvim.php" class="menu-link <?php echo isMenuActive('takvim.php'); ?>">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Akıllı Takvim</span>
                </a>
            </li>
            <li>
                <a href="/admin/rezervasyonlar.php" class="menu-link <?php echo isMenuActive('rezervasyonlar.php'); ?>">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>Teklif & Rezervasyonlar</span>
                </a>
            </li>
            <li>
                <a href="/admin/kategoriler.php" class="menu-link <?php echo isMenuActive('kategoriler.php'); ?>">
                    <i class="fa-solid fa-list-check"></i>
                    <span>Hizmet Kategorileri</span>
                </a>
            </li>
            <li>
                <a href="/admin/paketler.php" class="menu-link <?php echo isMenuActive('paketler.php'); ?>">
                    <i class="fa-solid fa-box-archive"></i>
                    <span>Abonelik Paketleri</span>
                </a>
            </li>
            <li>
                <a href="/admin/personeller.php" class="menu-link <?php echo isMenuActive('personeller.php'); ?>">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Personel Yönetimi</span>
                </a>
            </li>
            <li>
                <a href="/admin/raporlar.php" class="menu-link <?php echo isMenuActive('raporlar.php'); ?>">
                    <i class="fa-solid fa-chart-column"></i>
                    <span>Raporlar</span>
                </a>
            </li>
            <li>
                <a href="/admin/slider.php" class="menu-link <?php echo isMenuActive('slider.php'); ?>">
                    <i class="fa-solid fa-images"></i>
                    <span>Slider Yönetimi</span>
                </a>
            </li>
            <li>
                <a href="/admin/yorumlar.php" class="menu-link <?php echo isMenuActive('yorumlar.php'); ?>">
                    <i class="fa-solid fa-comments"></i>
                    <span>Müşteri Yorumları</span>
                </a>
            </li>
            <li>
                <a href="/admin/sss.php" class="menu-link <?php echo isMenuActive('sss.php'); ?>">
                    <i class="fa-solid fa-circle-question"></i>
                    <span>Sıkça Sorulan Sorular</span>
                </a>
            </li>
            <li>
                <a href="/admin/ayarlar.php" class="menu-link <?php echo isMenuActive('ayarlar.php'); ?>">
                    <i class="fa-solid fa-gears"></i>
                    <span>Sistem Ayarları</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="/admin/logout.php" class="menu-link" style="color: var(--danger); background-color: #fef2f2;">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Güvenli Çıkış</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Container -->
    <div class="admin-main">
        <!-- Top Header Bar -->
        <header class="admin-header">
            <div style="display: flex; align-items: center; gap: 15px; flex-grow: 1;">
                <button onclick="toggleAdminSidebar(event)" class="btn btn-outline" style="padding: 10px 14px;" id="mobileSidebarToggle">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="admin-title" style="margin-right: 15px;">OLiFA<span class="hide-mobile"> Panel</span></div>
                
                <!-- Mockup Arama Kutusu -->
                <div class="hide-mobile" style="display: flex; align-items: center; flex-grow: 1; max-width: 320px; position: relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 16px; color: var(--text-muted); font-size: 0.85rem;"></i>
                    <input type="text" placeholder="Her şeyi arayın..." style="width: 100%; padding: 8px 16px 8px 40px; border-radius: 20px; border: 1px solid var(--border); background-color: var(--background); font-size: 0.82rem; font-weight: 500; transition: var(--transition);">
                </div>
            </div>
            
            <div class="user-profile" style="gap: 20px;">
                <!-- Bildirim İkonu -->
                <div style="position: relative; cursor: pointer; color: var(--text-muted); font-size: 1.1rem; display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; background-color: var(--background); border-radius: 50%; border: 1px solid var(--border);">
                    <i class="fa-regular fa-bell"></i>
                    <span style="position: absolute; top: 0; right: 0; width: 8px; height: 8px; background-color: var(--primary); border-radius: 50%; border: 2px solid #ffffff;"></span>
                </div>
                
                <div class="user-info hide-mobile" style="text-align: right;">
                    <div style="font-weight: 700; font-size: 0.88rem; color: var(--text-main);"><?php echo e($adminUsername); ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">Yönetici</div>
                </div>
                <div class="user-avatar" style="background-color: var(--primary); color: #ffffff;">
                    <?php echo strtoupper(substr($adminUsername, 0, 2)); ?>
                </div>
            </div>
        </header>
        
        <main class="admin-content">
