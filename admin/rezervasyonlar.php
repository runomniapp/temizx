<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../classes/Booking.php';
require_once __DIR__ . '/../classes/Category.php';
require_once __DIR__ . '/../classes/Package.php';
require_once __DIR__ . '/../classes/WhatsAppService.php';

$bookingModel = new Booking();
$categoryModel = new Category();
$packageModel = new Package();
$msg = '';

// Filtreler
$filters = [
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Silme aksiyonu
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($bookingModel->deleteBooking($id)) {
        $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Rezervasyon başarıyla silindi!</div>';
    } else {
        $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;">Rezervasyon silinirken hata oluştu.</div>';
    }
}

// Durum değiştirme aksiyonu
if (isset($_GET['action']) && $_GET['action'] === 'status' && isset($_GET['id']) && isset($_GET['status'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['status'];
    
    if (in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
        if ($bookingModel->updateStatus($id, $status)) {
            $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Rezervasyon durumu başarıyla güncellendi!</div>';
        } else {
            $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;">Durum güncellenirken hata oluştu.</div>';
        }
    }
}

// Rezervasyon Güncelleme Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_booking') {
    $id = (int)$_POST['id'];
    $data = [
        'category_id' => (int)$_POST['category_id'],
        'subcategory_id' => !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null,
        'customer_name' => trim($_POST['customer_name']),
        'customer_phone' => trim($_POST['customer_phone']),
        'customer_email' => trim($_POST['customer_email']),
        'customer_address' => trim($_POST['customer_address']),
        'booking_date' => $_POST['booking_date'],
        'booking_time_slot' => $_POST['booking_time_slot'],
        'person_count' => (int)$_POST['person_count'],
        'service_days' => isset($_POST['service_days']) ? (int)$_POST['service_days'] : 1,
        'total_price' => isset($_POST['total_price']) ? (float)$_POST['total_price'] : null,
        'status' => $_POST['status']
    ];
    $employeeIds = isset($_POST['employee_ids']) ? array_map('intval', $_POST['employee_ids']) : [];
    
    $validationError = false;
    $checkedCount = count($employeeIds);
    
    // Eğer çalışan atandıysa ve durum beklemedeyse otomatik onaylandı yap
    if ($checkedCount > 0 && $data['status'] === 'pending') {
        $data['status'] = 'confirmed';
    }
    
    $personCount = $data['person_count'];
    
    if ($data['status'] === 'confirmed' || $data['status'] === 'completed') {
        if ($checkedCount !== $personCount) {
            $validationError = true;
            $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-triangle-exclamation"></i> Hata: Onaylanmış veya Tamamlanmış bir rezervasyon için tam olarak ' . $personCount . ' personel atamalısınız (Seçilen: ' . $checkedCount . ').</div>';
        }
    } else {
        if ($checkedCount > 0 && $checkedCount !== $personCount) {
            $validationError = true;
            $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-triangle-exclamation"></i> Hata: Personel ataması yapıldığında, seçilen sayı (' . $checkedCount . ') ile görevli personel sayısı (' . $personCount . ') eşit olmalıdır.</div>';
        }
    }
    
    if (!$validationError) {
        if ($bookingModel->updateBooking($id, $data, $employeeIds)) {
            $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Rezervasyon başarıyla güncellendi!</div>';
        } else {
            $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;">Güncelleme sırasında hata oluştu.</div>';
        }
    }
}

// Rezervasyon Oluşturma Post (Manuel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    $data = [
        'category_id' => (int)$_POST['category_id'],
        'subcategory_id' => !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null,
        'package_id' => !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null,
        'customer_name' => trim($_POST['customer_name']),
        'customer_phone' => trim($_POST['customer_phone']),
        'customer_email' => trim($_POST['customer_email']),
        'customer_address' => trim($_POST['customer_address']),
        'customer_location' => '',
        'booking_date' => $_POST['booking_date'],
        'booking_time_slot' => $_POST['booking_time_slot'],
        'person_count' => (int)$_POST['person_count'],
        'service_days' => isset($_POST['service_days']) ? (int)$_POST['service_days'] : 1,
        'total_price' => isset($_POST['total_price']) ? (float)$_POST['total_price'] : null,
        'status' => $_POST['status']
    ];
    
    // Eğer paket seçildiyse paketin değerlerini zorunlu kıl
    if ($data['package_id']) {
        $stmtPkg = Database::getConnection()->prepare("SELECT time_slot, person_count, duration_weeks FROM packages WHERE id = ?");
        $stmtPkg->execute([$data['package_id']]);
        $pkgInfo = $stmtPkg->fetch(PDO::FETCH_ASSOC);
        if ($pkgInfo) {
            $data['booking_time_slot'] = $pkgInfo['time_slot'];
            $data['person_count'] = $pkgInfo['person_count'];
            $data['service_days'] = $pkgInfo['duration_weeks'];
        }
    }
    
    $employeeIds = isset($_POST['employee_ids']) ? array_map('intval', $_POST['employee_ids']) : [];
    
    $validationError = false;
    $checkedCount = count($employeeIds);
    
    // Eğer çalışan atandıysa ve durum beklemedeyse otomatik onaylandı yap
    if ($checkedCount > 0 && $data['status'] === 'pending') {
        $data['status'] = 'confirmed';
    }
    
    $personCount = $data['person_count'];
    
    if ($data['status'] === 'confirmed' || $data['status'] === 'completed') {
        if ($checkedCount !== $personCount) {
            $validationError = true;
            $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-triangle-exclamation"></i> Hata: Onaylanmış veya Tamamlanmış bir rezervasyon/abonelik için tam olarak ' . $personCount . ' personel seçmelisiniz (Seçilen: ' . $checkedCount . ').</div>';
        }
    } else {
        if ($checkedCount > 0 && $checkedCount !== $personCount) {
            $validationError = true;
            $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-triangle-exclamation"></i> Hata: Personel ataması yapıldığında, seçilen sayı (' . $checkedCount . ') ile görevli personel sayısı (' . $personCount . ') eşit olmalıdır.</div>';
        }
    }
    
    if (!$validationError) {
        $newBookingId = $bookingModel->createBooking($data);
        if ($newBookingId) {
            // Eğer personel ID'leri seçilmiş ve onaylanmış ise atamaları ata
            if (!empty($employeeIds) && ($data['status'] === 'confirmed' || $data['status'] === 'completed')) {
                $stmtSch = Database::getConnection()->prepare("SELECT id FROM booking_schedule WHERE booking_id = ?");
                $stmtSch->execute([$newBookingId]);
                $schIds = $stmtSch->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($schIds)) {
                    $inQuery = implode(',', array_fill(0, count($schIds), '?'));
                    $stmtDel = Database::getConnection()->prepare("DELETE FROM booking_employees WHERE booking_schedule_id IN ($inQuery)");
                    $stmtDel->execute($schIds);
                    
                    $stmtIns = Database::getConnection()->prepare("INSERT INTO booking_employees (booking_schedule_id, employee_id) VALUES (?, ?)");
                    foreach ($schIds as $sid) {
                        foreach ($employeeIds as $eid) {
                            $stmtIns->execute([$sid, $eid]);
                        }
                    }
                }
            }
            if ($data['status'] === 'confirmed') {
                WhatsAppService::sendBookingNotifications($newBookingId);
            }
            $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Manuel rezervasyon/abonelik başarıyla oluşturuldu!</div>';
        } else {
            $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;">Kayıt sırasında hata oluştu.</div>';
        }
    }
}

// Kategori verilerini getir
$allCategories = $categoryModel->getAll();
$categoriesJsonData = [];
foreach ($allCategories as $cat) {
    $subcategories = $categoryModel->getSubcategories($cat['id'], true);
    $categoriesJsonData[] = [
        'id' => (int)$cat['id'],
        'name' => $cat['name'],
        'price' => (float)$cat['price'],
        'half_day_price' => (float)$cat['half_day_price'],
        'max_person' => (int)$cat['max_person'],
        'person_full_price' => (float)$cat['person_full_price'],
        'person_half_price' => (float)$cat['person_half_price'],
        'pricing_type' => $cat['pricing_type'],
        'has_subscription' => (int)$cat['is_subscription_active'],
        'subcategories' => array_map(function($sub) {
            return [
                'id' => (int)$sub['id'],
                'name' => $sub['name'],
                'price' => (float)$sub['price'],
                'half_day_price' => (float)$sub['half_day_price'],
                'max_person' => (int)$sub['max_person'],
                'person_full_price' => (float)$sub['person_full_price'],
                'person_half_price' => (float)$sub['person_half_price']
            ];
        }, $subcategories)
    ];
}

function getCategoryShortInfo($categoryName) {
    $name = trim($categoryName);
    if (stripos($name, 'Ev') !== false) {
        return ['icon' => '<i class="fa-solid fa-house"></i>', 'short' => 'Ev'];
    } elseif (stripos($name, 'Ofis') !== false) {
        return ['icon' => '<i class="fa-solid fa-building"></i>', 'short' => 'Ofis'];
    } elseif (stripos($name, 'İnşaat') !== false) {
        return ['icon' => '<i class="fa-solid fa-trowel-bricks"></i>', 'short' => 'İnşaat'];
    } elseif (stripos($name, 'Villa') !== false) {
        return ['icon' => '<i class="fa-solid fa-house-chimney"></i>', 'short' => 'Villa'];
    } elseif (stripos($name, 'Cam') !== false) {
        return ['icon' => '<i class="fa-solid fa-window-maximize"></i>', 'short' => 'Cam'];
    } elseif (stripos($name, 'Koltuk') !== false || stripos($name, 'Yatak') !== false) {
        return ['icon' => '<i class="fa-solid fa-couch"></i>', 'short' => 'Koltuk'];
    }
    $parts = explode(' ', $name);
    return ['icon' => '<i class="fa-solid fa-sparkles"></i>', 'short' => $parts[0] ?? 'Hizmet'];
}

$allPackages = $packageModel->getAll(true);
$allBookings = $bookingModel->getAll($filters);
?>

<style>
.show-mobile-only {
    display: none !important;
}
.category-mobile-badge {
    display: none !important;
}
@media (max-width: 992px) {
    .admin-table td.hide-mobile, .admin-table th.hide-mobile, .hide-mobile {
        display: none !important;
    }
    .show-mobile-only {
        display: inline-flex !important;
    }
    .category-mobile-badge {
        display: inline-flex !important;
        align-items: center !important;
        gap: 3px !important;
        font-size: 0.6rem !important;
        padding: 2px 6px !important;
        border-radius: 6px !important;
        font-weight: 700 !important;
        white-space: nowrap !important;
        line-height: 1.2 !important;
        flex-shrink: 0 !important;
    }
    /* Outer container overflow reset */
    .admin-table-wrapper > div {
        overflow-x: hidden !important;
    }
    /* Table layout transform */
    .admin-table {
        display: block !important;
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        width: 100% !important;
    }
    .admin-table thead {
        display: none !important;
    }
    .admin-table tbody {
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
    }
    .admin-table tr {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        background-color: var(--card-bg) !important;
        border: 1px solid var(--border) !important;
        border-radius: 40px !important;
        padding: 8px 10px !important;
        box-shadow: var(--shadow-sm) !important;
        transition: var(--transition) !important;
        margin-bottom: 0 !important;
        max-width: 100% !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
    }
    .admin-table tr:hover {
        box-shadow: var(--shadow-md) !important;
        border-color: rgba(37, 99, 235, 0.2) !important;
    }
    .admin-table td {
        display: block !important;
        border: none !important;
        padding: 0 !important;
        background: transparent !important;
    }
    /* Left column flex grow */
    .admin-table td:first-child {
        flex: 1 1 0 !important;
        min-width: 0 !important;
        margin-right: 6px !important;
        overflow: hidden !important;
    }
    /* Right action buttons container */
    .admin-table td:last-child {
        flex-shrink: 0 !important;
        text-align: right !important;
    }
    .admin-table td .action-btn {
        width: 28px !important;
        height: 28px !important;
        padding: 0 !important;
        border-radius: 50% !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.72rem !important;
        border: 1px solid var(--border) !important;
        background-color: var(--background) !important;
        box-shadow: none !important;
        margin: 0 1px !important;
        cursor: pointer !important;
        text-decoration: none !important;
        position: relative !important;
        z-index: 2 !important;
        -webkit-tap-highlight-color: transparent !important;
        touch-action: manipulation !important;
    }
    .admin-table td .action-btn i {
        pointer-events: none !important;
    }
    .admin-table td .action-btn[data-action="call"] {
        color: var(--success) !important;
        border-color: rgba(16, 185, 129, 0.3) !important;
        background-color: #ecfdf5 !important;
    }
    .admin-table td .action-btn[data-action="calendar"] {
        color: var(--primary) !important;
        border-color: rgba(37, 99, 235, 0.3) !important;
        background-color: var(--primary-light) !important;
    }
    .admin-table td .action-btn[data-action="edit"] {
        color: #f59e0b !important;
        border-color: rgba(245, 158, 11, 0.3) !important;
        background-color: #fffbeb !important;
    }
    .admin-table td .action-btn[data-action="delete"] {
        color: var(--danger) !important;
        border-color: rgba(239, 68, 68, 0.3) !important;
        background-color: #fef2f2 !important;
    }
    /* Status indicator small */
    .status-indicator {
        width: 24px !important;
        height: 24px !important;
        min-width: 24px !important;
    }
    .status-indicator i {
        font-size: 0.65rem !important;
    }
    /* Name text */
    .customer-name-text {
        font-size: 0.82rem !important;
        max-width: 100px !important;
    }
}
@media (max-width: 400px) {
    .customer-name-text {
        max-width: 80px !important;
    }
}
</style>

<div style="max-width: 1200px; margin: 0 auto;">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; gap: 15px; flex-wrap: wrap;">
        <div>
            <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 5px;">Teklif & Rezervasyonlar</h2>
            <p style="color: var(--text-muted);">Sistemdeki tüm teklif taleplerini, tek seferlik randevuları ve abonelik programlarını yönetin.</p>
        </div>
    </div>
    
    <?php echo $msg; ?>
    
    <!-- Filter Bar -->
    <div style="margin-bottom: 25px; display: flex; justify-content: flex-start; align-items: center; gap: 15px;">
        <div class="search-container" style="position: relative; flex-grow: 1; max-width: 500px; display: flex; align-items: center; gap: 8px;">
            <div style="position: relative; flex-grow: 1;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.9rem;"></i>
                <input type="text" id="searchQuery" class="form-control" placeholder="Müşteri adı veya telefon no..." style="padding: 10px 16px 10px 42px; font-size: 0.9rem; border-radius: 50px; width: 100%;">
            </div>
            
            <!-- Filter Dropdown Trigger -->
            <div class="filter-dropdown-wrapper">
                <button type="button" class="btn btn-outline filter-trigger-btn" style="width: 38px; height: 38px; padding: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid var(--border); touch-action: manipulation; -webkit-tap-highlight-color: transparent;" id="filterBtn" title="Duruma Göre Filtrele" onclick="toggleFilterDropdown(event)">
                    <i class="fa-solid fa-filter" style="pointer-events: none;"></i>
                </button>
                <!-- Filter Dropdown Content -->
                <div class="filter-dropdown-content" id="filterDropdown">
                    <div class="dropdown-header-title">Duruma Göre Filtrele</div>
                    <label class="filter-option"><input type="radio" name="statusFilter" value="all" checked onchange="applyFilters()"> <span>Hepsi</span></label>
                    <label class="filter-option"><input type="radio" name="statusFilter" value="pending" onchange="applyFilters()"> <span>Teklif (Bekleyen)</span></label>
                    <label class="filter-option"><input type="radio" name="statusFilter" value="confirmed" onchange="applyFilters()"> <span>Onaylı</span></label>
                    <label class="filter-option"><input type="radio" name="statusFilter" value="completed" onchange="applyFilters()"> <span>Bitti (Tamamlandı)</span></label>
                    <label class="filter-option"><input type="radio" name="statusFilter" value="cancelled" onchange="applyFilters()"> <span>İptal Edildi</span></label>
                </div>
            </div>
            
            <!-- Sort Dropdown Trigger -->
            <div class="sort-dropdown-wrapper">
                <button type="button" class="btn btn-outline sort-trigger-btn" style="width: 38px; height: 38px; padding: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid var(--border); touch-action: manipulation; -webkit-tap-highlight-color: transparent;" id="sortBtn" title="Sırala" onclick="toggleSortDropdown(event)">
                    <i class="fa-solid fa-arrow-down-wide-short" style="pointer-events: none;"></i>
                </button>
                <!-- Sort Dropdown Content -->
                <div class="sort-dropdown-content" id="sortDropdown">
                    <div class="dropdown-header-title">Sıralama Seçenekleri</div>
                    <label class="sort-option"><input type="radio" name="sortOption" value="date-desc" checked onchange="applyFilters()"> <span>En Son Eklenen (En Yeni)</span></label>
                    <label class="sort-option"><input type="radio" name="sortOption" value="date-asc" onchange="applyFilters()"> <span>Hizmet Tarihine Göre</span></label>
                    <label class="sort-option"><input type="radio" name="sortOption" value="name-asc" onchange="applyFilters()"> <span>İsim (A - Z)</span></label>
                    <label class="sort-option"><input type="radio" name="sortOption" value="name-desc" onchange="applyFilters()"> <span>İsim (Z - A)</span></label>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Table -->
    <div class="admin-table-wrapper">
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Müşteri Bilgileri</th>
                        <th class="hide-mobile">Hizmet / Kategori</th>
                        <th class="hide-mobile">Program / Tarih</th>
                        <th class="hide-mobile">Toplam Fiyat</th>
                        <th class="hide-mobile">Durum</th>
                        <th style="text-align: right;">Aksiyonlar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allBookings)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 50px; color: var(--text-muted);">Kayıt bulunamadı.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allBookings as $row): ?>
                            <tr class="booking-row" data-id="<?php echo (int)$row['id']; ?>" data-name="<?php echo e(mb_strtolower($row['customer_name'], 'UTF-8')); ?>" data-phone="<?php echo e(preg_replace('/[^0-9]/', '', $row['customer_phone'])); ?>" data-status="<?php echo e($row['status']); ?>" data-date="<?php echo strtotime($row['booking_date']); ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px; min-width: 0; flex-grow: 1;">
                                        <!-- Kategori Rozeti (Mobilde satır başında) -->
                                        <?php $catInfo = getCategoryShortInfo($row['category_name']); ?>
                                        <span class="category-mobile-badge" style="background-color: <?php echo e($row['category_color']); ?>15; color: <?php echo e($row['category_color']); ?>;">
                                            <?php echo $catInfo['icon']; ?> <?php echo e($catInfo['short']); ?>
                                        </span>
                                        <!-- Müşteri Durum İkonu -->
                                        <?php 
                                        if ($row['status'] === 'pending') {
                                            $statusIcon = '<i class="fa-regular fa-clock" style="font-size: 0.7rem; color: #f59e0b;"></i>';
                                            $statusBg = '#fffbeb';
                                            $statusBorder = 'rgba(245,158,11,0.2)';
                                        } else if ($row['status'] === 'confirmed' || $row['status'] === 'completed') {
                                            $statusIcon = '<i class="fa-solid fa-check" style="font-size: 0.65rem; color: #10b981;"></i>';
                                            $statusBg = '#ecfdf5';
                                            $statusBorder = 'rgba(16,185,129,0.2)';
                                        } else { // cancelled
                                            $statusIcon = '<i class="fa-solid fa-xmark" style="font-size: 0.65rem; color: #ef4444;"></i>';
                                            $statusBg = '#fef2f2';
                                            $statusBorder = 'rgba(239,68,68,0.2)';
                                        }
                                        ?>
                                        <div style="width: 34px; height: 34px; border-radius: 50%; background-color: <?php echo $statusBg; ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid <?php echo $statusBorder; ?>;" class="status-indicator">
                                            <?php echo $statusIcon; ?>
                                        </div>
                                        <div style="min-width: 0; flex-grow: 1;">
                                            <strong class="customer-name-text" style="font-size: 0.88rem; color: var(--text-main); display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo e($row['customer_name']); ?></strong>
                                            <!-- Desktop-only secondary info -->
                                            <span style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;" class="hide-mobile"><i class="fa-solid fa-phone"></i> <?php echo e(formatPhoneDisplay($row['customer_phone'])); ?></span>
                                            <?php if ($row['customer_email']): ?>
                                                <span style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-top: 2px;" class="hide-mobile"><i class="fa-solid fa-envelope"></i> <?php echo e($row['customer_email']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="hide-mobile">
                                    <?php $catInfo = getCategoryShortInfo($row['category_name']); ?>
                                    <span class="badge" style="background-color: <?php echo e($row['category_color']); ?>15; color: <?php echo e($row['category_color']); ?>; display: inline-flex; align-items: center; gap: 6px;">
                                        <?php echo $catInfo['icon']; ?> <?php echo e($catInfo['short']); ?>
                                    </span>
                                    <?php if ($row['subcategory_name']): ?>
                                        <span style="display: block; font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;"><?php echo e($row['subcategory_name']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="hide-mobile">
                                    <strong><?php echo date('d.m.Y', strtotime($row['booking_date'])); ?></strong>
                                    <span style="display: block; font-size: 0.8rem; color: var(--text-muted);"><?php echo e(translateTimeSlot($row['booking_time_slot'])); ?></span>
                                    <?php if ($row['package_name']): ?>
                                        <span style="display: block; font-size: 0.75rem; color: var(--success); font-weight: 700; margin-top: 4px;">
                                            <i class="fa-solid fa-arrows-spin"></i> <?php echo e($row['package_name']); ?> (<?php echo e($row['duration_weeks']); ?> Hafta)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="hide-mobile">
                                    <strong style="color: var(--primary); font-size: 1.1rem;"><?php echo formatPrice($row['total_price']); ?></strong>
                                    <span style="display: block; font-size: 0.75rem; color: var(--text-muted);"><?php echo e($row['person_count']); ?> Personel</span>
                                </td>
                                <td class="hide-mobile">
                                    <span class="badge badge-<?php echo e($row['status']); ?>">
                                        <?php 
                                        if ($row['status'] === 'pending') echo 'Bekleyen (Teklif)';
                                        else if ($row['status'] === 'confirmed') echo 'Onaylandı';
                                        else if ($row['status'] === 'completed') echo 'Tamamlandı';
                                        else if ($row['status'] === 'cancelled') echo 'İptal Edildi';
                                        ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; justify-content: flex-end; gap: 3px; align-items: center; flex-wrap: nowrap;">
                                        <!-- Ara Butonu -->
                                        <a href="tel:<?php echo formatPhoneTelUrl($row['customer_phone']); ?>" class="btn btn-outline action-btn" data-action="call" style="color: var(--success); border-color: var(--success); padding: 6px 10px; font-size: 0.85rem;" title="Müşteriyi Ara (<?php echo e(formatPhoneDisplay($row['customer_phone'])); ?>)">
                                            <i class="fa-solid fa-phone-flip"></i>
                                        </a>
                                        <!-- Detay Butonu -->
                                        <button type="button" class="btn btn-outline action-btn" data-action="calendar" data-booking-id="<?php echo $row['id']; ?>" style="padding: 6px 10px; font-size: 0.85rem;" title="Program Takvimi & Personeller">
                                            <i class="fa-solid fa-calendar-week"></i>
                                        </button>
                                        <!-- Düzenle Butonu -->
                                        <button type="button" class="btn btn-outline action-btn" data-action="edit" data-row='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>' style="padding: 6px 10px; font-size: 0.85rem;" title="Rezervasyonu Düzenle / Tarih Değiştir">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <!-- Silme Butonu -->
                                        <a href="rezervasyonlar.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-outline action-btn" data-action="delete" style="padding: 6px 10px; font-size: 0.85rem; color: var(--danger); border-color: var(--danger);" onclick="return confirm('Bu rezervasyonu ve bağlı tüm takvim programını KALICI OLARAK silmek istediğinize emin misiniz?')" title="Rezervasyonu Kalıcı Olarak Sil">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Floating Action Button (FAB) -->
<button onclick="openNewBookingModal()" class="fab-btn" title="Manuel Rezervasyon / Abonelik Ekle">
    <i class="fa-solid fa-plus"></i>
</button>

<!-- Booking Program / Schedule Modal -->
<div class="admin-modal" id="programModal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title">Hizmet Program Takvimi</h3>
            <span class="modal-close" onclick="closeProgramModal()">&times;</span>
        </div>
        <div class="modal-body" style="max-height: 480px; overflow-y: auto;">
            <!-- Customer Summary info -->
            <div style="margin-bottom: 20px; font-size: 0.95rem;">
                Müşteri: <strong id="prog_cust_name">Ahmet Yılmaz</strong> | Hizmet: <strong id="prog_cat_name">Ev Temizliği</strong>
            </div>
            
            <table class="admin-table" style="box-shadow: none; border: 1px solid var(--border); border-radius: 12px;">
                <thead>
                    <tr>
                        <th>Abonelik</th>
                        <th>Tarih</th>
                        <th>Saat Dilimi</th>
                        <th>Atanan Personel</th>
                    </tr>
                </thead>
                <tbody id="programTableBody">
                    <!-- Dynamic rendering -->
                </tbody>
            </table>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="closeProgramModal()">Kapat</button>
        </div>
    </div>
</div>

<!-- Manuel Rezervasyon / Abonelik Ekleme Modalı -->
<div class="admin-modal" id="newBookingModal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title">Manuel Rezervasyon / Abonelik Ekle</h3>
            <span class="modal-close" onclick="closeNewBookingModal()">&times;</span>
        </div>
        <form action="rezervasyonlar.php" method="POST" onsubmit="return validateNewBookingForm();">
            <input type="hidden" name="action" value="create_booking">
            <input type="hidden" name="category_id" id="new_booking_cat_id">
            
            <div class="modal-body" style="max-height: 480px; overflow-y: auto;">
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <!-- Abonelik Paket Seçimi (Kilit Nokta) -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700; color: var(--primary);">Abonelik / Paket Seçimi *</label>
                        <select name="package_id" id="new_booking_package_id" class="form-control" style="font-size: 0.9rem; padding: 8px 16px; height: auto;" onchange="handleNewBookingPackageChange()">
                            <option value="">-- Tek Seferlik Temizlik (Paketsiz / Aboneliksiz) --</option>
                            <?php foreach ($allPackages as $pkg): ?>
                                <option value="<?php echo $pkg['id']; ?>" 
                                        data-cat-id="<?php echo $pkg['category_id']; ?>" 
                                        data-slot="<?php echo $pkg['time_slot']; ?>" 
                                        data-persons="<?php echo $pkg['person_count']; ?>" 
                                        data-weeks="<?php echo $pkg['duration_weeks']; ?>" 
                                        data-price="<?php echo $pkg['discounted_price']; ?>">
                                    <?php echo e($pkg['name']); ?> (<?php echo number_format($pkg['discounted_price'], 0, ',', '.'); ?> ₺)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Müşteri Adı Soyadı *</label>
                            <input type="text" name="customer_name" id="new_booking_cust_name" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Telefon *</label>
                            <div style="display: flex; align-items: center; background: #fff; border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden;">
                                <div style="display: flex; align-items: center; gap: 6px; background: #f8fafc; padding: 6px 10px; border-right: 1px solid var(--border); font-weight: 700; color: #334155; font-size: 0.85rem; user-select: none;">
                                    <span style="font-size: 1.1rem;">🇹🇷</span>
                                    <span>+90</span>
                                </div>
                                <input type="text" name="customer_phone" id="new_booking_cust_phone" class="form-control" placeholder="555 555 55 55" maxlength="14" style="border: none; border-radius: 0; flex: 1; font-size: 0.9rem; padding: 8px 14px;" required>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">E-posta</label>
                            <input type="email" name="customer_email" id="new_booking_cust_email" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Durum *</label>
                            <select name="status" id="new_booking_status" class="form-control" style="font-size: 0.9rem; padding: 8px 16px; height: auto;" required>
                                <option value="pending">Bekleyen (Teklif)</option>
                                <option value="confirmed" selected>Onaylandı (Aktif Program)</option>
                                <option value="completed">Tamamlandı</option>
                                <option value="cancelled">İptal Edildi</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1.2fr 1fr 0.8fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Hizmet Tarihi *</label>
                            <input type="date" name="booking_date" id="new_booking_date" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Saat Dilimi *</label>
                            <select name="booking_time_slot" id="new_booking_time_slot" class="form-control" style="font-size: 0.9rem; padding: 8px 16px; height: auto;" required>
                                <option value="08-17">Tam Gün (8-17)</option>
                                <option value="08-12">Yarım Gün (8-12)</option>
                                <option value="13-17">Yarım Gün (13-17)</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;" id="new_booking_days_label">Gün Sayısı *</label>
                            <input type="number" name="service_days" id="new_booking_service_days" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" min="1" value="1" required>
                        </div>
                    </div>
                    
                    <div class="form-group" id="new_booking_category_selector_group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 6px; font-weight: 700;">Hizmet Kategorisi *</label>
                        <div id="new_booking_category_pills_container" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px;"></div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Görevli Personel Sayısı *</label>
                            <input type="number" name="person_count" id="new_booking_person_count" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" min="1" value="2" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Toplam Fiyat (₺) *</label>
                            <input type="number" name="total_price" id="new_booking_total_price" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-group" id="new_booking_subcategory_container" style="margin-bottom: 0; display: none;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Alt Kategori (Alan/m2) *</label>
                        <select name="subcategory_id" id="new_booking_subcategory_id" class="form-control" style="font-size: 0.9rem; padding: 8px 16px; height: auto;"></select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 6px; font-weight: 700;">Görevli Personel Atama</label>
                        <div id="newBookingEmployeeSelectionList" style="display: flex; flex-direction: column; gap: 8px; max-height: 180px; overflow-y: auto; padding: 2px;">
                            <!-- Dinamik olarak AJAX ile yüklenecek -->
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Temizlik Adresi *</label>
                        <textarea name="customer_address" id="new_booking_cust_address" class="form-control" rows="3" style="font-size: 0.9rem; padding: 10px 16px; border-radius: 14px; resize: none;" required></textarea>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeNewBookingModal()">Kapat</button>
                <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Rezervasyon Düzenleme Modal -->
<div class="admin-modal" id="editBookingModal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title">Rezervasyonu Düzenle / Tarih Değiştir</h3>
            <span class="modal-close" onclick="closeEditBookingModal()">&times;</span>
        </div>
        <form action="rezervasyonlar.php" method="POST" onsubmit="return validateEditBookingForm();">
            <input type="hidden" name="action" value="update_booking">
            <input type="hidden" name="id" id="edit_booking_id">
            <input type="hidden" name="category_id" id="edit_booking_cat_id">
            <input type="hidden" name="package_id" id="edit_booking_package_id">
            
            <div class="modal-body" style="max-height: 480px; overflow-y: auto;">
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Müşteri Adı *</label>
                            <input type="text" name="customer_name" id="edit_booking_cust_name" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Telefon *</label>
                            <div style="display: flex; align-items: center; background: #fff; border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden;">
                                <div style="display: flex; align-items: center; gap: 6px; background: #f8fafc; padding: 6px 10px; border-right: 1px solid var(--border); font-weight: 700; color: #334155; font-size: 0.85rem; user-select: none;">
                                    <span style="font-size: 1.1rem;">🇹🇷</span>
                                    <span>+90</span>
                                </div>
                                <input type="text" name="customer_phone" id="edit_booking_cust_phone" class="form-control" placeholder="555 555 55 55" maxlength="14" style="border: none; border-radius: 0; flex: 1; font-size: 0.9rem; padding: 8px 14px;" required>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">E-posta</label>
                            <input type="email" name="customer_email" id="edit_booking_cust_email" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Durum *</label>
                            <select name="status" id="edit_booking_status" class="form-control" style="font-size: 0.9rem; padding: 8px 16px; height: auto;" required>
                                <option value="pending">Bekleyen (Teklif)</option>
                                <option value="confirmed">Onaylandı (Aktif Program)</option>
                                <option value="completed">Tamamlandı</option>
                                <option value="cancelled">İptal Edildi</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1.2fr 1fr 0.8fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Hizmet Tarihi (Tarih Ötele) *</label>
                            <input type="date" name="booking_date" id="edit_booking_date" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Saat Dilimi *</label>
                            <select name="booking_time_slot" id="edit_booking_time_slot" class="form-control" style="font-size: 0.9rem; padding: 8px 16px; height: auto;" required>
                                <option value="08-17">Tam Gün (08-17)</option>
                                <option value="08-12">Sabah (08-12)</option>
                                <option value="13-17">Öğleden Sonra (13-17)</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Gün Sayısı *</label>
                            <input type="number" name="service_days" id="edit_booking_service_days" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" min="1" required>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 6px; font-weight: 700;">Hizmet (Hizmet Değiştir) *</label>
                        <div id="booking_category_pills_container" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px;"></div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Görevli Personel Sayısı *</label>
                            <input type="number" name="person_count" id="edit_booking_person_count" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" min="1" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Toplam Fiyat (₺) *</label>
                            <input type="number" name="total_price" id="edit_booking_total_price" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-group" id="edit_booking_subcategory_container" style="margin-bottom: 0; display: none;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Alt Kategori *</label>
                        <select name="subcategory_id" id="edit_booking_subcategory_id" class="form-control" style="font-size: 0.9rem; padding: 8px 16px; height: auto;"></select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 6px; font-weight: 700;">Görevli Personel Atama</label>
                        <div id="bookingEmployeeSelectionList" style="display: flex; flex-direction: column; gap: 8px; max-height: 180px; overflow-y: auto; padding: 2px;">
                            <!-- Dinamik olarak AJAX ile yüklenecek -->
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px; font-weight: 700;">Adres *</label>
                        <textarea name="customer_address" id="edit_booking_cust_address" class="form-control" rows="3" style="font-size: 0.9rem; padding: 10px 16px; border-radius: 14px; resize: none;" required></textarea>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeEditBookingModal()">Kapat</button>
                <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
const categoriesData = <?php echo json_encode($categoriesJsonData); ?>;

function renderBookingCategoryPills(activeCatId, selectedSubcatId = 0) {
    const container = document.getElementById("booking_category_pills_container");
    container.innerHTML = "";
    
    categoriesData.forEach(cat => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "btn " + (cat.id === activeCatId ? "btn-primary" : "btn-outline");
        btn.style.padding = "6px 14px";
        btn.style.fontSize = "0.82rem";
        btn.innerText = cat.name;
        
        btn.onclick = function() {
            document.getElementById("edit_booking_cat_id").value = cat.id;
            document.querySelectorAll("#booking_category_pills_container button").forEach(b => {
                b.className = "btn btn-outline";
            });
            btn.className = "btn btn-primary";
            handleBookingSubcategoryDisplay(cat, 0);
            recalculateBookingTotalPrice();
            loadBookingEmployeeAvailability();
        };
        
        container.appendChild(btn);
    });
    
    const activeCat = categoriesData.find(c => c.id === activeCatId) || categoriesData[0];
    document.getElementById("edit_booking_cat_id").value = activeCat ? activeCat.id : 0;
    handleBookingSubcategoryDisplay(activeCat, selectedSubcatId);
}

function handleBookingSubcategoryDisplay(category, selectedSubcatId = 0) {
    const subContainer = document.getElementById("edit_booking_subcategory_container");
    const subSelect = document.getElementById("edit_booking_subcategory_id");
    
    if (category && category.subcategories && category.subcategories.length > 0) {
        subSelect.innerHTML = "";
        category.subcategories.forEach(sub => {
            const opt = document.createElement("option");
            opt.value = sub.id;
            opt.innerText = sub.name;
            if (sub.id === parseInt(selectedSubcatId)) {
                opt.selected = true;
            }
            subSelect.appendChild(opt);
        });
        subSelect.required = true;
        subContainer.style.display = "block";
    } else {
        subSelect.innerHTML = "";
        subSelect.required = false;
        subSelect.value = "";
        subContainer.style.display = "none";
    }
}

function openEditBooking(row) {
    document.getElementById("edit_booking_id").value = row.id;
    document.getElementById("edit_booking_package_id").value = row.package_id || '';
    document.getElementById("edit_booking_cust_name").value = row.customer_name;
    
    let cleanPhone = (row.customer_phone || "").replace(/\D/g, "");
    if (cleanPhone.startsWith("90")) cleanPhone = cleanPhone.substring(2);
    if (cleanPhone.startsWith("0")) cleanPhone = cleanPhone.substring(1);
    if (cleanPhone.length > 10) cleanPhone = cleanPhone.substring(0, 10);
    let formattedPhone = "";
    if (cleanPhone.length > 0) formattedPhone += cleanPhone.substring(0, 3);
    if (cleanPhone.length > 3) formattedPhone += " " + cleanPhone.substring(3, 6);
    if (cleanPhone.length > 6) formattedPhone += " " + cleanPhone.substring(6, 8);
    if (cleanPhone.length > 8) formattedPhone += " " + cleanPhone.substring(8, 10);
    document.getElementById("edit_booking_cust_phone").value = formattedPhone;
    
    document.getElementById("edit_booking_cust_email").value = row.customer_email || '';
    document.getElementById("edit_booking_cust_address").value = row.customer_address;
    document.getElementById("edit_booking_date").value = row.booking_date;
    document.getElementById("edit_booking_time_slot").value = row.booking_time_slot;
    document.getElementById("edit_booking_person_count").value = row.person_count;
    document.getElementById("edit_booking_service_days").value = row.service_days || 1;
    document.getElementById("edit_booking_total_price").value = parseFloat(row.total_price || 0).toFixed(2);
    document.getElementById("edit_booking_status").value = row.status;
    
    renderBookingCategoryPills(parseInt(row.category_id), parseInt(row.subcategory_id || 0));
    
    // Bind change events
    document.getElementById("edit_booking_date").onchange = function() {
        loadBookingEmployeeAvailability();
    };
    document.getElementById("edit_booking_time_slot").onchange = function() {
        loadBookingEmployeeAvailability();
        recalculateBookingTotalPrice();
    };
    document.getElementById("edit_booking_service_days").onchange = function() {
        loadBookingEmployeeAvailability();
        recalculateBookingTotalPrice();
    };
    document.getElementById("edit_booking_person_count").onchange = function() {
        recalculateBookingTotalPrice();
    };
    document.getElementById("edit_booking_subcategory_id").onchange = function() {
        recalculateBookingTotalPrice();
    };
    
    // Load initial employee availability list
    loadBookingEmployeeAvailability();
    
    document.getElementById("editBookingModal").classList.add("active");
}

function openConfirmBookingModal(row) {
    const confirmRow = Object.assign({}, row);
    confirmRow.status = 'confirmed';
    openEditBooking(confirmRow);
}

function closeEditBookingModal() {
    document.getElementById("editBookingModal").classList.remove("active");
}

function openProgramDetails(bookingId) {
    const tableBody = document.getElementById("programTableBody");
    tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 30px;">Yükleniyor...</td></tr>';
    
    fetch(`../ajax/get_booking_schedule.php?id=${bookingId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById("prog_cust_name").innerText = data.booking.customer_name;
                document.getElementById("prog_cat_name").innerText = data.booking.category_name;
                
                tableBody.innerHTML = "";
                
                let foundNext = false;
                const isSubscription = !!data.booking.package_id;
                
                data.schedule.forEach((sch, index) => {
                    const dParts = sch.date.split('-');
                    const dateFormatted = `${dParts[2]}.${dParts[1]}.${dParts[0]}`;
                    
                    let slotText = sch.time_slot;
                    if (sch.time_slot === '08-17') slotText = 'Tam Gün (08-17)';
                    else if (sch.time_slot === '08-12') slotText = 'Sabah (08-12)';
                    else if (sch.time_slot === '13-17') slotText = 'Öğleden Sonra (13-17)';
                    
                    let crewNames = sch.employees.map(e => e.name).join(', ');
                    if (!crewNames) {
                        crewNames = '<span style="color: var(--danger); font-weight: 600;"><i class="fa-solid fa-triangle-exclamation"></i> Atanmamış!</span>';
                    }
                    
                    // Abonelik bilgisi kolonu
                    let subscriptionText = "";
                    if (isSubscription) {
                        subscriptionText = `<strong>${data.booking.duration_weeks}'li Abonelik</strong>`;
                    } else {
                        subscriptionText = `<strong>Tek Seferlik</strong>`;
                    }
                    
                    // Kaçıncı temizlik bilgisi
                    const cleaningSeqText = isSubscription ? ` <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">(${index + 1}. Temizlik)</span>` : '';
                    
                    // Tamamlandı / Sıradaki Temizlik Rozetleri
                    let statusBadge = "";
                    if (sch.status === 'completed') {
                        statusBadge = `<span class="badge" style="background-color: #ecfdf5; color: var(--success); font-weight: 700; font-size: 0.72rem; margin-left: 8px; padding: 2px 8px; border-radius: 8px;"><i class="fa-solid fa-circle-check"></i> Tamamlandı</span>`;
                    } else if (sch.status === 'cancelled') {
                        statusBadge = `<span class="badge" style="background-color: #fef2f2; color: var(--danger); font-weight: 700; font-size: 0.72rem; margin-left: 8px; padding: 2px 8px; border-radius: 8px;"><i class="fa-solid fa-circle-xmark"></i> İptal Edildi</span>`;
                    } else if (!foundNext && (sch.status === 'confirmed' || sch.status === 'pending')) {
                        statusBadge = `<span class="badge" style="background-color: #e0f2fe; color: #0284c7; font-weight: 700; font-size: 0.72rem; margin-left: 8px; padding: 2px 8px; border-radius: 8px;"><i class="fa-solid fa-calendar-day"></i> Sıradaki Temizlik</span>`;
                        foundNext = true;
                    }
                    
                    const tr = document.createElement("tr");
                    tr.innerHTML = `
                        <td>${subscriptionText}</td>
                        <td><strong>${dateFormatted}</strong>${cleaningSeqText}${statusBadge}</td>
                        <td>${slotText}</td>
                        <td>${crewNames}</td>
                    `;
                    tableBody.appendChild(tr);
                });
                
                document.getElementById("programModal").classList.add("active");
            } else {
                alert("Program verisi yüklenirken hata: " + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("İletişim hatası oluştu.");
        });
}

function closeProgramModal() {
    document.getElementById("programModal").classList.remove("active");
}

function loadBookingEmployeeAvailability() {
    const bookingId = document.getElementById("edit_booking_id").value || 0;
    const date = document.getElementById("edit_booking_date").value;
    const timeSlot = document.getElementById("edit_booking_time_slot").value;
    const serviceDays = document.getElementById("edit_booking_service_days").value || 1;
    const categoryId = document.getElementById("edit_booking_cat_id").value || 0;
    
    const listContainer = document.getElementById("bookingEmployeeSelectionList");
    if (!date || !timeSlot || !categoryId) {
        listContainer.innerHTML = '<div style="color: var(--text-muted); font-size: 0.85rem; padding: 10px;">Lütfen önce Tarih, Saat Dilimi ve Kategori seçin.</div>';
        return;
    }
    
    listContainer.innerHTML = '<div style="color: var(--text-muted); font-size: 0.85rem; padding: 10px;">Personel doluluk durumları kontrol ediliyor...</div>';
    
    fetch(`../ajax/get_employee_availability_for_booking.php?booking_id=${bookingId}&date=${date}&time_slot=${timeSlot}&service_days=${serviceDays}&category_id=${categoryId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                listContainer.innerHTML = "";
                if (data.employees.length === 0) {
                    listContainer.innerHTML = '<div style="color: var(--danger); font-size: 0.85rem; padding: 10px; font-weight:600;"><i class="fa-solid fa-triangle-exclamation"></i> Bu hizmet grubuna ait aktif çalışan bulunamadı!</div>';
                    return;
                }
                
                data.employees.forEach(emp => {
                    const item = document.createElement("div");
                    item.style.display = "flex";
                    item.style.alignItems = "center";
                    item.style.justifyContent = "space-between";
                    item.style.padding = "8px 12px";
                    item.style.border = "1px solid var(--border)";
                    item.style.borderRadius = "10px";
                    item.style.backgroundColor = "var(--background)";
                    
                    let statusBadgeHtml = '';
                    let isDisabled = false;
                    
                    if (emp.is_off) {
                        statusBadgeHtml = '<span class="badge" style="background-color: #fef2f2; color: var(--danger); font-size: 0.72rem;">İzin Günü</span>';
                        isDisabled = true;
                    } else if (emp.has_overlap) {
                        statusBadgeHtml = `<span class="badge" style="background-color: #fff8e6; color: var(--warning); font-size: 0.72rem;" title="${emp.overlap_customer} ile çakışıyor (${emp.overlap_date})">Meşgul (${emp.overlap_date.split('-')[2]}.${emp.overlap_date.split('-')[1]})</span>`;
                        isDisabled = true;
                    } else {
                        statusBadgeHtml = '<span class="badge" style="background-color: #ecfdf5; color: var(--success); font-size: 0.72rem;">Müsait</span>';
                    }
                    
                    // Eğer atanmış ise, disabled bile olsa seçili kalsın ve disabled olmasın ki atamayı iptal edebilsinler veya koruyabilsinler!
                    const isChecked = emp.is_assigned;
                    const finalDisabled = isDisabled && !isChecked;
                    
                    item.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="employee_ids[]" value="${emp.id}" id="bk_emp_check_${emp.id}" 
                                   ${isChecked ? 'checked' : ''} ${finalDisabled ? 'disabled' : ''} 
                                   style="width: 16px; height: 16px; accent-color: var(--primary);">
                            <label for="bk_emp_check_${emp.id}" style="font-weight: 600; font-size: 0.85rem; cursor: ${finalDisabled ? 'not-allowed' : 'pointer'}; margin-bottom:0;">${emp.name}</label>
                        </div>
                        <div>${statusBadgeHtml}</div>
                    `;
                    listContainer.appendChild(item);
                });
            } else {
                listContainer.innerHTML = `<div style="color: var(--danger); font-size: 0.85rem; padding: 10px;">Kontrol hatası: ${data.message}</div>`;
            }
        })
        .catch(err => {
            listContainer.innerHTML = '<div style="color: var(--danger); font-size: 0.85rem; padding: 10px;">Bağlantı hatası oluştu.</div>';
        });
}

function recalculateBookingTotalPrice() {
    const pkgId = document.getElementById("edit_booking_package_id").value;
    if (pkgId) {
        return; // Abonelik paketi fiyatını sıfırlamayalım, sabit kalsın.
    }
    const catId = parseInt(document.getElementById("edit_booking_cat_id").value) || 0;
    const cat = categoriesData.find(c => c.id === catId);
    if (!cat) return;
    
    if (cat.pricing_type === 'discovery') {
        return; // Keşifli fiyatlandırmada ellemeyelim
    }
    
    const timeSlot = document.getElementById("edit_booking_time_slot").value;
    const isHalfDay = (timeSlot === '08-12' || timeSlot === '13-17');
    
    let maxPerson = 1;
    let basePrice = 0;
    let extraPersonPrice = 0;
    
    if (cat.pricing_type === 'subcategory') {
        const subId = parseInt(document.getElementById("edit_booking_subcategory_id").value) || 0;
        const sub = cat.subcategories.find(s => s.id === subId);
        if (sub) {
            maxPerson = parseInt(sub.max_person || 1);
            basePrice = isHalfDay ? parseFloat(sub.half_day_price || 0) : parseFloat(sub.price || 0);
            extraPersonPrice = isHalfDay ? parseFloat(sub.person_half_price || 0) : parseFloat(sub.person_full_price || 0);
        }
    } else {
        maxPerson = parseInt(cat.max_person || 1);
        basePrice = isHalfDay ? parseFloat(cat.half_day_price || 0) : parseFloat(cat.price || 0);
        extraPersonPrice = isHalfDay ? parseFloat(cat.person_half_price || 0) : parseFloat(cat.person_full_price || 0);
    }
    
    const personCount = parseInt(document.getElementById("edit_booking_person_count").value) || 1;
    const serviceDays = parseInt(document.getElementById("edit_booking_service_days").value) || 1;
    
    const extraCount = Math.max(0, personCount - maxPerson);
    const totalPrice = (basePrice + (extraCount * extraPersonPrice)) * serviceDays;
    
    document.getElementById("edit_booking_total_price").value = totalPrice.toFixed(2);
}

function validateEditBookingForm() {
    const status = document.getElementById("edit_booking_status").value;
    const requiredPersonCount = parseInt(document.getElementById("edit_booking_person_count").value) || 0;
    
    // Count checked checkboxes inside bookingEmployeeSelectionList
    const checkedEmployees = document.querySelectorAll('#bookingEmployeeSelectionList input[name="employee_ids[]"]:checked');
    const checkedCount = checkedEmployees.length;
    
    if (status === 'confirmed' || status === 'completed') {
        if (checkedCount !== requiredPersonCount) {
            alert("Hata: Onaylanmış veya Tamamlanmış bir rezervasyon için tam olarak " + requiredPersonCount + " personel seçmelisiniz. Şu an " + checkedCount + " personel seçtiniz.");
            return false;
        }
    } else {
        // pending veya cancelled durumunda ya hiç seçilmeyecek ya da tam sayısı kadar seçilecek
        if (checkedCount > 0 && checkedCount !== requiredPersonCount) {
            alert("Hata: Personel ataması yapıyorsanız tam olarak " + requiredPersonCount + " personel seçmelisiniz. Şu an " + checkedCount + " personel seçtiniz.");
            return false;
        }
    }
    return true;
}

// ========================================================
// MANUEL REZERVASYON / ABONELİK EKLEME JS MANTIĞI
// ========================================================

let newBookingSelectedCategory = null;
let newBookingSelectedSubcategory = null;

function openNewBookingModal() {
    // Inputları sıfırla
    document.getElementById("new_booking_cust_name").value = "";
    document.getElementById("new_booking_cust_phone").value = "";
    document.getElementById("new_booking_cust_email").value = "";
    document.getElementById("new_booking_cust_address").value = "";
    document.getElementById("new_booking_status").value = "confirmed";
    
    // Varsayılan tarih olarak yarını ayarla
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const yyyy = tomorrow.getFullYear();
    const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
    const dd = String(tomorrow.getDate()).padStart(2, '0');
    document.getElementById("new_booking_date").value = `${yyyy}-${mm}-${dd}`;
    document.getElementById("new_booking_time_slot").value = "08-17";
    document.getElementById("new_booking_service_days").value = "1";
    document.getElementById("new_booking_person_count").value = "2";
    document.getElementById("new_booking_package_id").value = "";
    
    // Değişim eventlerini bağla
    document.getElementById("new_booking_date").onchange = loadNewBookingEmployeeAvailability;
    document.getElementById("new_booking_time_slot").onchange = function() {
        loadNewBookingEmployeeAvailability();
        recalculateNewBookingTotalPrice();
    };
    document.getElementById("new_booking_service_days").onchange = function() {
        loadNewBookingEmployeeAvailability();
        recalculateNewBookingTotalPrice();
    };
    document.getElementById("new_booking_person_count").onchange = function() {
        recalculateNewBookingTotalPrice();
    };
    document.getElementById("new_booking_subcategory_id").onchange = function() {
        const subId = parseInt(this.value) || 0;
        if (newBookingSelectedCategory && newBookingSelectedCategory.subcategories) {
            newBookingSelectedSubcategory = newBookingSelectedCategory.subcategories.find(s => s.id === subId);
        }
        recalculateNewBookingTotalPrice();
    };

    // İlk kategoriyi varsayılan seç
    const defaultCatId = categoriesData.length > 0 ? categoriesData[0].id : 0;
    renderNewBookingCategoryPills(defaultCatId);
    handleNewBookingPackageChange();

    document.getElementById("newBookingModal").classList.add("active");
}

function closeNewBookingModal() {
    document.getElementById("newBookingModal").classList.remove("active");
}

function renderNewBookingCategoryPills(activeCatId, selectedSubcatId = 0) {
    const container = document.getElementById("new_booking_category_pills_container");
    container.innerHTML = "";
    
    categoriesData.forEach(cat => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "btn " + (cat.id === activeCatId ? "btn-primary" : "btn-outline");
        btn.style.padding = "6px 14px";
        btn.style.fontSize = "0.82rem";
        btn.innerText = cat.name;
        
        btn.onclick = function() {
            if (document.getElementById("new_booking_package_id").value !== "") {
                return; // Paket seçiliyse kategori değiştirilemez
            }
            document.getElementById("new_booking_cat_id").value = cat.id;
            document.querySelectorAll("#new_booking_category_pills_container button").forEach(b => {
                b.className = "btn btn-outline";
            });
            btn.className = "btn btn-primary";
            newBookingSelectedCategory = cat;
            handleNewBookingSubcategoryDisplay(cat, 0);
            recalculateNewBookingTotalPrice();
            loadNewBookingEmployeeAvailability();
        };
        
        container.appendChild(btn);
    });
    
    newBookingSelectedCategory = categoriesData.find(c => c.id === activeCatId) || categoriesData[0];
    document.getElementById("new_booking_cat_id").value = newBookingSelectedCategory ? newBookingSelectedCategory.id : 0;
    handleNewBookingSubcategoryDisplay(newBookingSelectedCategory, selectedSubcatId);
}

function handleNewBookingSubcategoryDisplay(category, selectedSubcatId = 0) {
    const subContainer = document.getElementById("new_booking_subcategory_container");
    const subSelect = document.getElementById("new_booking_subcategory_id");
    
    if (category && category.subcategories && category.subcategories.length > 0) {
        subSelect.innerHTML = "";
        category.subcategories.forEach(sub => {
            const opt = document.createElement("option");
            opt.value = sub.id;
            opt.innerText = sub.name;
            if (sub.id === parseInt(selectedSubcatId)) {
                opt.selected = true;
            }
            subSelect.appendChild(opt);
        });
        subSelect.required = true;
        subContainer.style.display = "block";
        newBookingSelectedSubcategory = category.subcategories.find(s => s.id === parseInt(subSelect.value));
    } else {
        subSelect.innerHTML = "";
        subSelect.required = false;
        subSelect.value = "";
        subContainer.style.display = "none";
        newBookingSelectedSubcategory = null;
    }
}

function handleNewBookingPackageChange() {
    const pkgSelect = document.getElementById("new_booking_package_id");
    const selectedOpt = pkgSelect.options[pkgSelect.selectedIndex];
    
    const catGroup = document.getElementById("new_booking_category_selector_group");
    const timeSlotSelect = document.getElementById("new_booking_time_slot");
    const personCountInput = document.getElementById("new_booking_person_count");
    const serviceDaysInput = document.getElementById("new_booking_service_days");
    const daysLabel = document.getElementById("new_booking_days_label");
    const totalPriceInput = document.getElementById("new_booking_total_price");
    
    if (selectedOpt && selectedOpt.value !== "") {
        // Paket seçildi, alanları kilitle ve pakete göre ayarla
        const catId = parseInt(selectedOpt.dataset.catId);
        const slot = selectedOpt.dataset.slot;
        const persons = parseInt(selectedOpt.dataset.persons);
        const weeks = parseInt(selectedOpt.dataset.weeks);
        const price = parseFloat(selectedOpt.dataset.price);
        
        document.getElementById("new_booking_cat_id").value = catId;
        renderNewBookingCategoryPills(catId);
        
        // Kategori seçimini devre dışı bırak/soluklaştır
        catGroup.style.opacity = "0.5";
        catGroup.style.pointerEvents = "none";
        
        // Zaman ve gün sayılarını kilitli olarak ata
        timeSlotSelect.value = slot;
        timeSlotSelect.disabled = true;
        
        personCountInput.value = persons;
        personCountInput.disabled = true;
        
        serviceDaysInput.value = weeks;
        serviceDaysInput.disabled = true;
        daysLabel.innerText = "Seans (Hafta) Sayısı";
        
        totalPriceInput.value = price.toFixed(2);
        totalPriceInput.readOnly = true;
        
        // Alt kategoriyi gizle
        document.getElementById("new_booking_subcategory_container").style.display = "none";
    } else {
        // Paketsiz/Tek Seferlik, alanları kilidini aç
        catGroup.style.opacity = "1";
        catGroup.style.pointerEvents = "auto";
        
        timeSlotSelect.disabled = false;
        personCountInput.disabled = false;
        serviceDaysInput.disabled = false;
        daysLabel.innerText = "Gün Sayısı *";
        
        totalPriceInput.readOnly = false;
        
        // Alt kategoriyi kategorinin durumuna göre göster
        if (newBookingSelectedCategory) {
            handleNewBookingSubcategoryDisplay(newBookingSelectedCategory, 0);
        }
        recalculateNewBookingTotalPrice();
    }
    
    loadNewBookingEmployeeAvailability();
}

function recalculateNewBookingTotalPrice() {
    if (document.getElementById("new_booking_package_id").value !== "") {
        return; // Paket seçili ise hesaplama yapma, sabit kalsın.
    }
    
    if (!newBookingSelectedCategory) return;
    
    if (newBookingSelectedCategory.pricing_type === 'discovery') {
        document.getElementById("new_booking_total_price").value = "0.00";
        return;
    }
    
    const timeSlot = document.getElementById("new_booking_time_slot").value;
    const isHalfDay = (timeSlot === '08-12' || timeSlot === '13-17');
    
    let maxPerson = 1;
    let basePrice = 0;
    let extraPersonPrice = 0;
    
    if (newBookingSelectedCategory.pricing_type === 'subcategory' && newBookingSelectedSubcategory) {
        maxPerson = parseInt(newBookingSelectedSubcategory.max_person || 1);
        basePrice = isHalfDay ? parseFloat(newBookingSelectedSubcategory.half_day_price || 0) : parseFloat(newBookingSelectedSubcategory.price || 0);
        extraPersonPrice = isHalfDay ? parseFloat(newBookingSelectedSubcategory.person_half_price || 0) : parseFloat(newBookingSelectedSubcategory.person_full_price || 0);
    } else {
        maxPerson = parseInt(newBookingSelectedCategory.max_person || 1);
        basePrice = isHalfDay ? parseFloat(newBookingSelectedCategory.half_day_price || 0) : parseFloat(newBookingSelectedCategory.price || 0);
        extraPersonPrice = isHalfDay ? parseFloat(newBookingSelectedCategory.person_half_price || 0) : parseFloat(newBookingSelectedCategory.person_full_price || 0);
    }
    
    const personCount = parseInt(document.getElementById("new_booking_person_count").value) || 1;
    const serviceDays = parseInt(document.getElementById("new_booking_service_days").value) || 1;
    
    const extraCount = Math.max(0, personCount - maxPerson);
    const totalPrice = (basePrice + (extraCount * extraPersonPrice)) * serviceDays;
    
    document.getElementById("new_booking_total_price").value = totalPrice.toFixed(2);
}

function loadNewBookingEmployeeAvailability() {
    const date = document.getElementById("new_booking_date").value;
    const timeSlot = document.getElementById("new_booking_time_slot").value;
    const serviceDays = document.getElementById("new_booking_service_days").value || 1;
    const categoryId = document.getElementById("new_booking_cat_id").value || 0;
    
    const listContainer = document.getElementById("newBookingEmployeeSelectionList");
    if (!date || !timeSlot || !categoryId) {
        listContainer.innerHTML = '<div style="color: var(--text-muted); font-size: 0.85rem; padding: 10px;">Lütfen önce Tarih, Saat Dilimi ve Kategori seçin.</div>';
        return;
    }
    
    listContainer.innerHTML = '<div style="color: var(--text-muted); font-size: 0.85rem; padding: 10px;">Personel durumları kontrol ediliyor...</div>';
    
    fetch(`../ajax/get_employee_availability_for_booking.php?booking_id=0&date=${date}&time_slot=${timeSlot}&service_days=${serviceDays}&category_id=${categoryId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                listContainer.innerHTML = "";
                if (data.employees.length === 0) {
                    listContainer.innerHTML = '<div style="color: var(--danger); font-size: 0.85rem; padding: 10px; font-weight:600;"><i class="fa-solid fa-triangle-exclamation"></i> Bu hizmet grubuna ait aktif çalışan bulunamadı!</div>';
                    return;
                }
                
                data.employees.forEach(emp => {
                    const item = document.createElement("div");
                    item.style.display = "flex";
                    item.style.alignItems = "center";
                    item.style.justifyContent = "space-between";
                    item.style.padding = "8px 12px";
                    item.style.border = "1px solid var(--border)";
                    item.style.borderRadius = "10px";
                    item.style.backgroundColor = "var(--background)";
                    
                    let statusBadgeHtml = '';
                    let isDisabled = false;
                    
                    if (emp.is_off) {
                        statusBadgeHtml = '<span class="badge" style="background-color: #fef2f2; color: var(--danger); font-size: 0.72rem;">İzin Günü</span>';
                        isDisabled = true;
                    } else if (emp.has_overlap) {
                        statusBadgeHtml = `<span class="badge" style="background-color: #fff8e6; color: var(--warning); font-size: 0.72rem;" title="${emp.overlap_customer} ile çakışıyor (${emp.overlap_date})">Meşgul (${emp.overlap_date.split('-')[2]}.${emp.overlap_date.split('-')[1]})</span>`;
                        isDisabled = true;
                    } else {
                        statusBadgeHtml = '<span class="badge" style="background-color: #ecfdf5; color: var(--success); font-size: 0.72rem;">Müsait</span>';
                    }
                    
                    item.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="employee_ids[]" value="${emp.id}" id="new_bk_emp_check_${emp.id}" 
                                   ${isDisabled ? 'disabled' : ''} 
                                   style="width: 16px; height: 16px; accent-color: var(--primary);">
                            <label for="new_bk_emp_check_${emp.id}" style="font-weight: 600; font-size: 0.85rem; cursor: ${isDisabled ? 'not-allowed' : 'pointer'}; margin-bottom:0;">${emp.name}</label>
                        </div>
                        <div>${statusBadgeHtml}</div>
                    `;
                    listContainer.appendChild(item);
                });
            } else {
                listContainer.innerHTML = `<div style="color: var(--danger); font-size: 0.85rem; padding: 10px;">Kontrol hatası: ${data.message}</div>`;
            }
        })
        .catch(err => {
            listContainer.innerHTML = '<div style="color: var(--danger); font-size: 0.85rem; padding: 10px;">Bağlantı hatası oluştu.</div>';
        });
}

function validateNewBookingForm() {
    const status = document.getElementById("new_booking_status").value;
    const requiredPersonCount = parseInt(document.getElementById("new_booking_person_count").value) || 0;
    
    // Count checked checkboxes inside newBookingEmployeeSelectionList
    const checkedEmployees = document.querySelectorAll('#newBookingEmployeeSelectionList input[name="employee_ids[]"]:checked');
    const checkedCount = checkedEmployees.length;
    
    if (status === 'confirmed' || status === 'completed') {
        if (checkedCount !== requiredPersonCount) {
            alert("Hata: Onaylanmış veya Tamamlanmış bir randevu için tam olarak " + requiredPersonCount + " personel seçmelisiniz. Şu an " + checkedCount + " personel seçtiniz.");
            return false;
        }
    } else {
        // pending veya cancelled durumunda ya hiç seçilmeyecek ya da tam sayısı kadar seçilecek
        if (checkedCount > 0 && checkedCount !== requiredPersonCount) {
            alert("Hata: Personel ataması yapıyorsanız tam olarak " + requiredPersonCount + " personel seçmelisiniz. Şu an " + checkedCount + " personel seçtiniz.");
            return false;
        }
    }
    
    // Form submit edilmeden önce disabled inputların değerlerini gönderebilmek için kilidini geçici olarak açalım!
    document.getElementById("new_booking_time_slot").disabled = false;
    document.getElementById("new_booking_person_count").disabled = false;
    document.getElementById("new_booking_service_days").disabled = false;
    
    return true;
}

// Parametreyle gelirse modalı otomatik aç ve telefon maskesini kur
window.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('new')) {
        openNewBookingModal();
    }
    
    const maskPhoneInput = (input) => {
        if (!input) return;
        input.placeholder = "555 555 55 55";
        input.maxLength = 14;
        input.addEventListener("input", () => {
            let value = input.value.replace(/\D/g, "");
            if (value.startsWith("90")) value = value.substring(2);
            if (value.startsWith("0")) value = value.substring(1);
            if (value.length > 10) value = value.substring(0, 10);
            let formatted = "";
            if (value.length > 0) formatted += value.substring(0, 3);
            if (value.length > 3) formatted += " " + value.substring(3, 6);
            if (value.length > 6) formatted += " " + value.substring(6, 8);
            if (value.length > 8) formatted += " " + value.substring(8, 10);
            input.value = formatted;
        });
    };
    
    maskPhoneInput(document.getElementById("new_booking_cust_phone"));
    maskPhoneInput(document.getElementById("edit_booking_cust_phone"));
    
    // Canlı arama ve filtreler için event listener
    const searchInput = document.getElementById("searchQuery");
    if (searchInput) {
        searchInput.addEventListener("input", applyFilters);
    }
    
    // URL parametrelerini oku ve varsayılan durumları ata
    const searchParam = urlParams.get('search');
    const statusParam = urlParams.get('status');
    
    if (searchParam) {
        if (searchInput) searchInput.value = searchParam;
    }
    
    if (statusParam) {
        const radio = document.querySelector(`input[name="statusFilter"][value="${statusParam}"]`);
        if (radio) radio.checked = true;
    }
    
    // Sayfa yüklendiğinde ilk filtrelemeyi yap
    applyFilters();
});

// Dropdown menü açma/kapama fonksiyonları
window.toggleFilterDropdown = function(e) {
    if (e) e.stopPropagation();
    const fd = document.getElementById('filterDropdown');
    const sd = document.getElementById('sortDropdown');
    const fb = document.getElementById('filterBtn');
    const sb = document.getElementById('sortBtn');
    
    if (sd) {
        sd.classList.remove('active');
        sb.classList.remove('active');
    }
    
    if (fd) {
        fd.classList.toggle('active');
        fb.classList.toggle('active', fd.classList.contains('active'));
    }
};

window.toggleSortDropdown = function(e) {
    if (e) e.stopPropagation();
    const fd = document.getElementById('filterDropdown');
    const sd = document.getElementById('sortDropdown');
    const fb = document.getElementById('filterBtn');
    const sb = document.getElementById('sortBtn');
    
    if (fd) {
        fd.classList.remove('active');
        fb.classList.remove('active');
    }
    
    if (sd) {
        sd.classList.toggle('active');
        sb.classList.toggle('active', sd.classList.contains('active'));
    }
};

// Dışarı tıklama durumunda dropdown kapatma
document.addEventListener('click', (e) => {
    const fd = document.getElementById('filterDropdown');
    const sd = document.getElementById('sortDropdown');
    const fb = document.getElementById('filterBtn');
    const sb = document.getElementById('sortBtn');
    
    if (fd && !fd.contains(e.target) && !fb.contains(e.target)) {
        fd.classList.remove('active');
        fb.classList.remove('active');
    }
    if (sd && !sd.contains(e.target) && !sb.contains(e.target)) {
        sd.classList.remove('active');
        sb.classList.remove('active');
    }
});

// İstemci tarafında anlık süzme ve sıralama (Türkçe karakter desteği ile)
function turkishLower(str) {
    return str.replace(/İ/g, 'i').replace(/I/g, 'ı').replace(/Ş/g, 'ş').replace(/Ğ/g, 'ğ').replace(/Ü/g, 'ü').replace(/Ö/g, 'ö').replace(/Ç/g, 'ç').toLowerCase();
}

window.applyFilters = function() {
    const rawQuery = document.getElementById('searchQuery') ? document.getElementById('searchQuery').value.trim() : '';
    const searchQuery = turkishLower(rawQuery);
    
    const statusRadio = document.querySelector('input[name="statusFilter"]:checked');
    const activeStatus = statusRadio ? statusRadio.value : 'all';
    
    const sortRadio = document.querySelector('input[name="sortOption"]:checked');
    const activeSort = sortRadio ? sortRadio.value : 'date-desc';
    
    const rows = Array.from(document.querySelectorAll('.booking-row'));
    let visibleCount = 0;
    
    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const phone = row.getAttribute('data-phone') || '';
        const status = row.getAttribute('data-status') || '';
        
        let matchSearch = true;
        if (searchQuery) {
            const searchDigits = searchQuery.replace(/[^0-9]/g, '');
            if (searchDigits.length > 0 && searchDigits === searchQuery) {
                // Sadece rakam girilmişse telefon numarasında ara
                matchSearch = phone.includes(searchDigits);
            } else {
                // İsimde ara (Türkçe normalize edilmiş)
                matchSearch = turkishLower(name).includes(searchQuery);
            }
        }
        
        let matchStatus = true;
        if (activeStatus && activeStatus !== 'all') {
            matchStatus = status === activeStatus;
        }
        
        if (matchSearch && matchStatus) {
            row.style.setProperty('display', '', 'important');
            visibleCount++;
        } else {
            row.style.setProperty('display', 'none', 'important');
        }
    });
    
    // Sıralama uygula
    const tbody = document.querySelector('table.admin-table tbody');
    if (tbody && rows.length > 0) {
        rows.sort((a, b) => {
            const idA = parseInt(a.getAttribute('data-id')) || 0;
            const idB = parseInt(b.getAttribute('data-id')) || 0;
            const dateA = parseInt(a.getAttribute('data-date')) || 0;
            const dateB = parseInt(b.getAttribute('data-date')) || 0;

            if (activeSort === 'date-desc') {
                return idB - idA; // En son eklenen/oluşturulan randevu en üstte
            } else if (activeSort === 'date-asc') {
                return dateA - dateB || idB - idA;
            } else if (activeSort === 'name-asc') {
                return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '', 'tr');
            } else if (activeSort === 'name-desc') {
                return (b.getAttribute('data-name') || '').localeCompare(a.getAttribute('data-name') || '', 'tr');
            }
            return idB - idA;
        });
        
        rows.forEach(row => tbody.appendChild(row));
    }
    
    // 'Kayıt Bulunamadı' mesajı
    let noRecordsRow = document.getElementById('no-records-row');
    if (visibleCount === 0) {
        if (!noRecordsRow) {
            noRecordsRow = document.createElement('tr');
            noRecordsRow.id = 'no-records-row';
            noRecordsRow.innerHTML = `<td colspan="6" style="text-align: center; padding: 50px; color: var(--text-muted);">Aramanıza uygun kayıt bulunamadı.</td>`;
            tbody.appendChild(noRecordsRow);
        } else {
            noRecordsRow.style.setProperty('display', '', 'important');
        }
    } else if (noRecordsRow) {
        noRecordsRow.style.setProperty('display', 'none', 'important');
    }
};

// Event delegation: Takvim ve Düzenle butonları (mobilde onclick çalışmama sorunu çözümü)
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.action-btn');
    if (!btn) return;
    
    const action = btn.getAttribute('data-action');
    
    if (action === 'calendar') {
        e.preventDefault();
        e.stopPropagation();
        const bookingId = btn.getAttribute('data-booking-id');
        if (bookingId) openProgramDetails(parseInt(bookingId));
    }
    
    if (action === 'edit') {
        e.preventDefault();
        e.stopPropagation();
        const rowData = btn.getAttribute('data-row');
        if (rowData) {
            try {
                const parsed = JSON.parse(rowData);
                openEditBooking(parsed);
            } catch(err) {
                console.error('Edit verisi parse edilemedi:', err);
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
