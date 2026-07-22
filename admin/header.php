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

// Son eklenen teklif ve rezervasyonları çek (id DESC)
$stmtPending = $pdo->prepare("
    SELECT b.*, c.name as category_name 
    FROM bookings b
    LEFT JOIN categories c ON b.category_id = c.id
    ORDER BY b.id DESC 
    LIMIT 15
");
$stmtPending->execute();
$pendingOffers = $stmtPending->fetchAll();
$pendingCount = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn() ?: 0;
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
            <a href="index.php">
                <img src="<?php echo e($logoPath); ?>" alt="<?php echo e($compName); ?>">
            </a>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="index.php" class="menu-link <?php echo isMenuActive('index.php'); ?>">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="takvim.php" class="menu-link <?php echo isMenuActive('takvim.php'); ?>">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Akıllı Takvim</span>
                </a>
            </li>
            <li>
                <a href="rezervasyonlar.php" class="menu-link <?php echo isMenuActive('rezervasyonlar.php'); ?>">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>Teklif & Rezervasyonlar</span>
                </a>
            </li>
            <li>
                <a href="kategoriler.php" class="menu-link <?php echo isMenuActive('kategoriler.php'); ?>">
                    <i class="fa-solid fa-list-check"></i>
                    <span>Hizmet Kategorileri</span>
                </a>
            </li>
            <li>
                <a href="paketler.php" class="menu-link <?php echo isMenuActive('paketler.php'); ?>">
                    <i class="fa-solid fa-box-archive"></i>
                    <span>Abonelik Paketleri</span>
                </a>
            </li>
            <li>
                <a href="personeller.php" class="menu-link <?php echo isMenuActive('personeller.php'); ?>">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Personel Yönetimi</span>
                </a>
            </li>
            <li>
                <a href="whatsapp.php" class="menu-link <?php echo isMenuActive('whatsapp.php'); ?>">
                    <i class="fa-brands fa-whatsapp" style="color: #22c55e;"></i>
                    <span>WhatsApp Entegrasyonu</span>
                </a>
            </li>
            <li>
                <a href="raporlar.php" class="menu-link <?php echo isMenuActive('raporlar.php'); ?>">
                    <i class="fa-solid fa-chart-column"></i>
                    <span>Raporlar</span>
                </a>
            </li>
            <li>
                <a href="slider.php" class="menu-link <?php echo isMenuActive('slider.php'); ?>">
                    <i class="fa-solid fa-images"></i>
                    <span>Slider Yönetimi</span>
                </a>
            </li>
            <li>
                <a href="yorumlar.php" class="menu-link <?php echo isMenuActive('yorumlar.php'); ?>">
                    <i class="fa-solid fa-comments"></i>
                    <span>Müşteri Yorumları</span>
                </a>
            </li>
            <li>
                <a href="sss.php" class="menu-link <?php echo isMenuActive('sss.php'); ?>">
                    <i class="fa-solid fa-circle-question"></i>
                    <span>Sıkça Sorulan Sorular</span>
                </a>
            </li>
            <li>
                <a href="ayarlar.php" class="menu-link <?php echo isMenuActive('ayarlar.php'); ?>">
                    <i class="fa-solid fa-gears"></i>
                    <span>Sistem Ayarları</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="menu-link" style="color: var(--danger); background-color: #fef2f2;">
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
            </div>
            
            <div class="user-profile" style="gap: 20px;">
                <!-- Bildirim İkonu -->
                <div style="position: relative;" id="notificationDropdownContainer">
                    <div onclick="toggleNotificationDropdown(event)" style="position: relative; cursor: pointer; color: var(--text-muted); font-size: 1.1rem; display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; background-color: var(--background); border-radius: 50%; border: 1px solid var(--border);">
                        <i class="fa-regular fa-bell"></i>
                        <?php if ($pendingCount > 0): ?>
                            <span style="position: absolute; top: 0; right: 0; width: 8px; height: 8px; background-color: var(--primary); border-radius: 50%; border: 2px solid #ffffff;"></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Glassmorphism Dropdown Menu -->
                    <div id="notificationDropdown" style="display: none; position: absolute; top: 48px; right: 0; width: 320px; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border: 1px solid rgba(255, 255, 255, 0.35); border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); z-index: 1000; padding: 15px; box-sizing: border-box;">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 10px;">
                            <strong style="font-size: 0.88rem; color: var(--text-main);">Rezervasyon & Teklifler</strong>
                            <?php if ($pendingCount > 0): ?>
                                <span class="badge badge-pending" style="font-size: 0.7rem; padding: 2px 8px; border-radius: 8px;"><?php echo $pendingCount; ?> Onay Bekleyen</span>
                            <?php else: ?>
                                <span class="badge badge-confirmed" style="font-size: 0.7rem; padding: 2px 8px; border-radius: 8px; background: #ecfdf5; color: #10b981;">Tümü Güncel</span>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px; max-height: 280px; overflow-y: auto;">
                            <?php if (count($pendingOffers) === 0): ?>
                                <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem; padding: 20px 0;">
                                    <i class="fa-regular fa-circle-check" style="font-size: 1.5rem; margin-bottom: 8px; color: var(--success); display: block;"></i>
                                    Henüz randevu veya teklif bulunmuyor.
                                </div>
                            <?php else: ?>
                                <?php foreach ($pendingOffers as $offer): ?>
                                    <?php 
                                    $stClass = 'badge-pending';
                                    $stText = 'Bekliyor';
                                    if ($offer['status'] === 'confirmed') { $stClass = 'badge-confirmed'; $stText = 'Onaylandı'; }
                                    elseif ($offer['status'] === 'completed') { $stClass = 'badge-completed'; $stText = 'Tamamlandı'; }
                                    elseif ($offer['status'] === 'cancelled') { $stClass = 'badge-cancelled'; $stText = 'İptal'; }
                                    ?>
                                    <a href="rezervasyonlar.php?open=<?php echo $offer['id']; ?>" style="display: flex; flex-direction: column; gap: 4px; padding: 9px 12px; border-radius: 10px; background: rgba(255, 255, 255, 0.6); border: 1px solid rgba(226, 232, 240, 0.8); text-decoration: none; transition: var(--transition);" onmouseover="this.style.background='#ffffff'; this.style.borderColor='var(--primary)';" onmouseout="this.style.background='rgba(255, 255, 255, 0.6)'; this.style.borderColor='rgba(226, 232, 240, 0.8)';">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <strong style="font-size: 0.83rem; color: var(--text-main); font-weight: 700;"><?php echo e($offer['customer_name']); ?></strong>
                                            <span style="font-size: 0.75rem; color: var(--primary); font-weight: 800;"><?php echo formatPrice($offer['total_price']); ?></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">
                                            <span><i class="fa-solid fa-list-check" style="font-size: 0.68rem; margin-right: 3px;"></i> <?php echo e($offer['category_name'] ?? 'Genel Hizmet'); ?></span>
                                            <span class="badge <?php echo $stClass; ?>" style="font-size: 0.65rem; padding: 2px 6px; border-radius: 6px; font-weight: 700;"><?php echo $stText; ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div style="border-top: 1px solid var(--border); margin-top: 10px; padding-top: 10px; text-align: center;">
                            <a href="rezervasyonlar.php" style="font-size: 0.8rem; font-weight: 700; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                Tümünü Gör <i class="fa-solid fa-arrow-right" style="font-size: 0.75rem;"></i>
                            </a>
                        </div>
                    </div>
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
        
        <script>
            function toggleNotificationDropdown(event) {
                event.stopPropagation();
                const dropdown = document.getElementById('notificationDropdown');
                if (dropdown.style.display === 'none' || !dropdown.style.display) {
                    dropdown.style.display = 'block';
                } else {
                    dropdown.style.display = 'none';
                }
            }
            document.addEventListener('click', function(event) {
                const dropdown = document.getElementById('notificationDropdown');
                const container = document.getElementById('notificationDropdownContainer');
                if (dropdown && container && !container.contains(event.target)) {
                    dropdown.style.display = 'none';
                }
            });
        </script>
        
        <main class="admin-content">
