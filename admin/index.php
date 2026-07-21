<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../classes/Booking.php';
require_once __DIR__ . '/../classes/Category.php';

$bookingModel = new Booking();
$categoryModel = new Category();

// Bugünün ve yarının tarihleri
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));

$msg = '';

// Onaylama, İptal etme veya Güncelleme işlemleri
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
            $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Rezervasyon başarıyla güncellendi ve onaylandı!</div>';
        } else {
            $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;">Güncelleme sırasında hata oluştu.</div>';
        }
    }
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    if ($action === 'cancel') {
        if ($bookingModel->updateStatus($id, 'cancelled')) {
            $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-xmark"></i> Teklif iptal edildi.</div>';
        }
    }
}

// 1. Bilgi Kartları ve İstatistikler
// Bugünkü Temizlik Sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) FROM booking_schedule WHERE date = ? AND status IN ('confirmed', 'completed')");
$stmt->execute([$today]);
$todayCount = (int)$stmt->fetchColumn();

// Yarınki Temizlik Sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) FROM booking_schedule WHERE date = ? AND status IN ('confirmed', 'completed')");
$stmt->execute([$tomorrow]);
$tomorrowCount = (int)$stmt->fetchColumn();

// Bu Haftaki Temizlik Sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) FROM booking_schedule WHERE date BETWEEN ? AND ? AND status IN ('confirmed', 'completed')");
$stmt->execute([$monday, $sunday]);
$weekCount = (int)$stmt->fetchColumn();

// Bugünkü Ciro
$stmt = $pdo->prepare("
    SELECT SUM(b.total_price / b.service_days) 
    FROM booking_schedule bs 
    INNER JOIN bookings b ON bs.booking_id = b.id 
    WHERE bs.date = ? AND bs.status IN ('confirmed', 'completed')
");
$stmt->execute([$today]);
$todayRevenue = (float)$stmt->fetchColumn() ?: 0.0;

// Bu Haftaki Ciro
$stmt = $pdo->prepare("
    SELECT SUM(b.total_price / b.service_days) 
    FROM booking_schedule bs 
    INNER JOIN bookings b ON bs.booking_id = b.id 
    WHERE bs.date BETWEEN ? AND ? AND bs.status IN ('confirmed', 'completed')
");
$stmt->execute([$monday, $sunday]);
$weekRevenue = (float)$stmt->fetchColumn() ?: 0.0;

// Bugünkü Çalışan Ödemeleri
$stmt = $pdo->prepare("
    SELECT be.employee_id, bs.time_slot, e.daily_wage_full, e.daily_wage_half
    FROM booking_employees be
    INNER JOIN booking_schedule bs ON be.booking_schedule_id = bs.id
    INNER JOIN employees e ON be.employee_id = e.id
    WHERE bs.date = ? AND bs.status IN ('confirmed', 'completed')
");
$stmt->execute([$today]);
$todayWages = 0.0;
while ($row = $stmt->fetch()) {
    $todayWages += ($row['time_slot'] === '08-17') ? (float)$row['daily_wage_full'] : (float)$row['daily_wage_half'];
}

// Bu Haftaki Çalışan Ödemeleri
$stmt = $pdo->prepare("
    SELECT be.employee_id, bs.time_slot, e.daily_wage_full, e.daily_wage_half
    FROM booking_employees be
    INNER JOIN booking_schedule bs ON be.booking_schedule_id = bs.id
    INNER JOIN employees e ON be.employee_id = e.id
    WHERE bs.date BETWEEN ? AND ? AND bs.status IN ('confirmed', 'completed')
");
$stmt->execute([$monday, $sunday]);
$weekWages = 0.0;
while ($row = $stmt->fetch()) {
    $weekWages += ($row['time_slot'] === '08-17') ? (float)$row['daily_wage_full'] : (float)$row['daily_wage_half'];
}

// 2. Bugünün ve Yarının Temizlik İşleri Listeleri
$todayJobs = $bookingModel->getTodayJobs();
$tomorrowJobs = $bookingModel->getScheduleRange($tomorrow, $tomorrow);

// 3. Aylık Net Kar Grafiği Verisi (Haziran, Temmuz, Ağustos 2026)
$months = [
    '06' => ['name' => 'Haziran', 'start' => '2026-06-01', 'end' => '2026-06-30'],
    '07' => ['name' => 'Temmuz', 'start' => '2026-07-01', 'end' => '2026-07-31'],
    '08' => ['name' => 'Ağustos', 'start' => '2026-08-01', 'end' => '2026-08-31'],
];
$chartData = [];
foreach ($months as $key => $m) {
    // Ciro
    $stmt = $pdo->prepare("
        SELECT SUM(b.total_price / b.service_days) 
        FROM booking_schedule bs 
        INNER JOIN bookings b ON bs.booking_id = b.id 
        WHERE bs.date BETWEEN ? AND ? AND bs.status IN ('confirmed', 'completed')
    ");
    $stmt->execute([$m['start'], $m['end']]);
    $rev = (float)$stmt->fetchColumn() ?: 0.0;
    
    // Gider (Personel Yövmiesi)
    $stmt = $pdo->prepare("
        SELECT be.employee_id, bs.time_slot, e.daily_wage_full, e.daily_wage_half
        FROM booking_employees be
        INNER JOIN booking_schedule bs ON be.booking_schedule_id = bs.id
        INNER JOIN employees e ON be.employee_id = e.id
        WHERE bs.date BETWEEN ? AND ? AND bs.status IN ('confirmed', 'completed')
    ");
    $stmt->execute([$m['start'], $m['end']]);
    $exp = 0.0;
    while ($row = $stmt->fetch()) {
        $exp += ($row['time_slot'] === '08-17') ? (float)$row['daily_wage_full'] : (float)$row['daily_wage_half'];
    }
    
    $chartData[] = [
        'month' => $m['name'],
        'revenue' => $rev,
        'expense' => $exp,
        'profit' => $rev - $exp
    ];
}

// 4. Onay Bekleyen Son 5 Rezervasyon
$stmtPendingList = $pdo->prepare("
    SELECT b.*, c.name as category_name, sub.name as subcategory_name, p.name as package_name
    FROM bookings b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN subcategories sub ON b.subcategory_id = sub.id
    LEFT JOIN packages p ON b.package_id = p.id
    WHERE b.status = 'pending'
    ORDER BY b.id DESC
    LIMIT 5
");
$stmtPendingList->execute();
$pendingList = $stmtPendingList->fetchAll();

// Modal Kategorileri Verisini Çek
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
?>

<style>
.glass-card {
    background: rgba(255, 255, 255, 0.45);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 24px;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.03);
    position: relative;
    overflow: hidden;
    padding: 24px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.glass-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.08);
    background: rgba(255, 255, 255, 0.55);
}
.glass-card-wave {
    position: absolute;
    bottom: -10px;
    left: 0;
    right: 0;
    height: 60px;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1440 320'%3E%3Cpath fill='%232563eb' fill-opacity='0.04' d='M0,224L60,208C120,192,240,160,360,165.3C480,171,600,213,720,218.7C840,224,960,192,1080,181.3C1200,171,1320,181,1380,186.7L1440,192L1440,320L1380,320C1320,320,1200,320,1080,320C960,320,840,320,720,320C600,320,480,320,360,320C240,320,120,320,60,320L0,320Z'%3E%3C/path%3E%3C/svg%3E") no-repeat bottom/cover;
    pointer-events: none;
    z-index: 0;
}
.stats-grid-row-1 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}
.stats-grid-row-2 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}
@media (max-width: 991px) {
    .stats-grid-row-1, .stats-grid-row-2 {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 576px) {
    .stats-grid-row-1, .stats-grid-row-2 {
        grid-template-columns: 1fr;
    }
}
.card-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    display: block;
    margin-bottom: 8px;
}
.card-value {
    font-size: 1.55rem;
    font-weight: 900;
    color: var(--text-main);
    margin: 0;
    display: flex;
    align-items: baseline;
    gap: 4px;
}
.card-icon {
    font-size: 1.25rem;
    color: var(--primary);
    margin-bottom: 12px;
    opacity: 0.85;
}
.job-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.25);
    margin-bottom: 10px;
    transition: var(--transition);
}
.job-item:hover {
    background: rgba(255, 255, 255, 0.75);
    border-color: rgba(37, 99, 235, 0.15);
}
</style>

<div style="max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 10px;">
    
    <?php echo $msg; ?>
    
    <!-- Üst Satır Bilgi Kartları (Bugün) -->
    <div class="stats-grid-row-1">
        <!-- Card 1: Bugünkü Temizlik Sayısı -->
        <div class="glass-card">
            <div class="glass-card-wave"></div>
            <div style="position: relative; z-index: 1;">
                <div class="card-icon" style="color: var(--primary);"><i class="fa-solid fa-calendar-day"></i></div>
                <span class="card-label">Bugünkü Temizlik</span>
                <h3 class="card-value"><?php echo $todayCount; ?> <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted);">İş</span></h3>
            </div>
        </div>
        
        <!-- Card 2: Bugünkü Ciro -->
        <div class="glass-card">
            <div class="glass-card-wave"></div>
            <div style="position: relative; z-index: 1;">
                <div class="card-icon" style="color: var(--success);"><i class="fa-solid fa-money-bill-wave"></i></div>
                <span class="card-label">Bugünkü Ciro</span>
                <h3 class="card-value"><?php echo formatPrice($todayRevenue); ?></h3>
            </div>
        </div>
        
        <!-- Card 3: Bugünkü Çalışan Ödemeleri -->
        <div class="glass-card">
            <div class="glass-card-wave"></div>
            <div style="position: relative; z-index: 1;">
                <div class="card-icon" style="color: var(--danger);"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                <span class="card-label">Bugünkü Ödemeler</span>
                <h3 class="card-value"><?php echo formatPrice($todayWages); ?></h3>
            </div>
        </div>
    </div>
    
    <!-- Alt Satır Bilgi Kartları (Yarın ve Haftalık) -->
    <div class="stats-grid-row-2">
        <!-- Card 4: Yarınki Temizlik Sayısı -->
        <div class="glass-card">
            <div class="glass-card-wave"></div>
            <div style="position: relative; z-index: 1;">
                <div class="card-icon" style="color: var(--warning);"><i class="fa-solid fa-calendar-plus"></i></div>
                <span class="card-label">Yarınki Temizlik</span>
                <h3 class="card-value"><?php echo $tomorrowCount; ?> <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted);">İş</span></h3>
            </div>
        </div>
        
        <!-- Card 5: Bu Haftaki Temizlik Sayısı -->
        <div class="glass-card">
            <div class="glass-card-wave"></div>
            <div style="position: relative; z-index: 1;">
                <div class="card-icon" style="color: #6366f1;"><i class="fa-solid fa-calendar-week"></i></div>
                <span class="card-label">Haftalık Temizlik</span>
                <h3 class="card-value"><?php echo $weekCount; ?> <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted);">İş</span></h3>
            </div>
        </div>
        
        <!-- Card 6: Bu Haftaki Toplam Ciro -->
        <div class="glass-card">
            <div class="glass-card-wave"></div>
            <div style="position: relative; z-index: 1;">
                <div class="card-icon" style="color: #06b6d4;"><i class="fa-solid fa-sack-dollar"></i></div>
                <span class="card-label">Haftalık Ciro</span>
                <h3 class="card-value"><?php echo formatPrice($weekRevenue); ?></h3>
            </div>
        </div>
        
        <!-- Card 7: Bu Haftaki Toplam Çalışan Ödemeleri -->
        <div class="glass-card">
            <div class="glass-card-wave"></div>
            <div style="position: relative; z-index: 1;">
                <div class="card-icon" style="color: #ec4899;"><i class="fa-solid fa-coins"></i></div>
                <span class="card-label">Haftalık Ödemeler</span>
                <h3 class="card-value"><?php echo formatPrice($weekWages); ?></h3>
            </div>
        </div>
    </div>
    
    <!-- Bugünkü ve Yarınki İşler Liste Kartları -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        <!-- Bugünün Temizlik İşleri -->
        <div class="glass-card" style="min-height: 250px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 15px;">
                <h3 style="font-size: 1.02rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-calendar-check" style="color: var(--primary);"></i> Bugünün Temizlik İşleri
                </h3>
                <span class="badge badge-confirmed" style="font-size: 0.75rem;"><?php echo date('d.m.Y'); ?></span>
            </div>
            
            <div style="max-height: 320px; overflow-y: auto; padding-right: 2px;">
                <?php if (empty($todayJobs)): ?>
                    <div style="padding: 40px 10px; text-align: center; color: var(--text-muted);">
                        <i class="fa-solid fa-mug-hot" style="font-size: 1.8rem; margin-bottom: 10px; color: var(--border);"></i>
                        <p style="font-weight: 500; font-size: 0.85rem; margin: 0;">Bugün planlanmış bir iş bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($todayJobs as $job): ?>
                        <div class="job-item">
                            <div>
                                <strong style="font-size: 0.85rem; color: var(--text-main); display: block;"><?php echo e($job['customer_name']); ?></strong>
                                <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 2px;"><?php echo e(translateTimeSlot($job['time_slot'])); ?></span>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge" style="background-color: <?php echo e($job['category_color']); ?>15; color: <?php echo e($job['category_color']); ?>; font-size: 0.72rem; font-weight: 700; padding: 4px 10px; border-radius: 8px;">
                                    <?php echo e($job['category_name']); ?>
                                </span>
                                <?php if (!empty($job['employees'])): ?>
                                    <div style="display: flex; gap: 4px; justify-content: flex-end; margin-top: 4px;">
                                        <?php foreach ($job['employees'] as $emp): ?>
                                            <span style="font-size: 0.68rem; background: #e2e8f0; color: #334155; padding: 1px 6px; border-radius: 4px; font-weight: 600;"><?php echo e(explode(' ', $emp['name'])[0]); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="display: block; font-size: 0.7rem; color: var(--danger); font-weight: 700; margin-top: 4px;"><i class="fa-solid fa-triangle-exclamation"></i> Personel Atanmamış!</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Yarınki Temizlik İşleri -->
        <div class="glass-card" style="min-height: 250px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 15px;">
                <h3 style="font-size: 1.02rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-calendar-days" style="color: var(--success);"></i> Yarınki Temizlik İşleri
                </h3>
                <span class="badge badge-pending" style="font-size: 0.75rem;"><?php echo date('d.m.Y', strtotime('+1 day')); ?></span>
            </div>
            
            <div style="max-height: 320px; overflow-y: auto; padding-right: 2px;">
                <?php if (empty($tomorrowJobs)): ?>
                    <div style="padding: 40px 10px; text-align: center; color: var(--text-muted);">
                        <i class="fa-solid fa-mug-hot" style="font-size: 1.8rem; margin-bottom: 10px; color: var(--border);"></i>
                        <p style="font-weight: 500; font-size: 0.85rem; margin: 0;">Yarın planlanmış bir iş bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tomorrowJobs as $job): ?>
                        <div class="job-item">
                            <div>
                                <strong style="font-size: 0.85rem; color: var(--text-main); display: block;"><?php echo e($job['customer_name']); ?></strong>
                                <span style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 2px;"><?php echo e(translateTimeSlot($job['time_slot'])); ?></span>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge" style="background-color: <?php echo e($job['category_color']); ?>15; color: <?php echo e($job['category_color']); ?>; font-size: 0.72rem; font-weight: 700; padding: 4px 10px; border-radius: 8px;">
                                    <?php echo e($job['category_name']); ?>
                                </span>
                                <?php if (!empty($job['employees'])): ?>
                                    <div style="display: flex; gap: 4px; justify-content: flex-end; margin-top: 4px;">
                                        <?php foreach ($job['employees'] as $emp): ?>
                                            <span style="font-size: 0.68rem; background: #e2e8f0; color: #334155; padding: 1px 6px; border-radius: 4px; font-weight: 600;"><?php echo e(explode(' ', $emp['name'])[0]); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="display: block; font-size: 0.7rem; color: var(--danger); font-weight: 700; margin-top: 4px;"><i class="fa-solid fa-triangle-exclamation"></i> Personel Atanmamış!</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Aylık Kar Grafiği ve Detay Paneli -->
    <div style="display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 20px; margin-bottom: 20px;">
        <!-- Grafik -->
        <div class="glass-card">
            <h3 style="font-size: 1.02rem; font-weight: 800; margin-bottom: 20px; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-chart-line" style="color: var(--primary);"></i> Aylık Kar Durumu
            </h3>
            <div style="position: relative; height: 260px;">
                <canvas id="profitChart"></canvas>
            </div>
        </div>
        
        <!-- Detay Paneli -->
        <div class="glass-card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h3 style="font-size: 1.02rem; font-weight: 800; margin-bottom: 20px; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-info-circle" style="color: var(--primary);"></i> Ay Detayları
                </h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div>
                        <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Dönem</span>
                        <div id="details_month_name" style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-top: 2px;">Yükleniyor...</div>
                    </div>
                    <hr style="border: 0; border-top: 1px solid var(--border); margin: 0;">
                    <div>
                        <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Toplam Ciro</span>
                        <div id="details_revenue" style="font-size: 1.15rem; font-weight: 800; color: var(--primary); margin-top: 2px;">0,00 ₺</div>
                    </div>
                    <hr style="border: 0; border-top: 1px solid var(--border); margin: 0;">
                    <div>
                        <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Çalışan Ödemeleri Gideri</span>
                        <div id="details_expense" style="font-size: 1.15rem; font-weight: 800; color: var(--danger); margin-top: 2px;">0,00 ₺</div>
                    </div>
                    <hr style="border: 0; border-top: 1px solid var(--border); margin: 0;">
                    <div>
                        <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Net Kar</span>
                        <div id="details_profit" style="font-size: 1.3rem; font-weight: 900; color: var(--success); margin-top: 2px;">0,00 ₺</div>
                    </div>
                </div>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500; text-align: center; margin-top: 15px; border-top: 1px solid var(--border); padding-top: 10px;">
                Grafikteki sütunlara tıklayarak aylar arası geçiş yapabilirsiniz.
            </div>
        </div>
    </div>
    
    <!-- Onay Bekleyen Son 5 Rezervasyon -->
    <div class="glass-card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 15px;">
            <h3 style="font-size: 1.02rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-clock-rotate-left" style="color: var(--warning);"></i> Onay Bekleyen Son 5 Rezervasyon
            </h3>
            <span class="badge badge-pending" style="font-size: 0.75rem;"><?php echo count($pendingList); ?> Bekliyor</span>
        </div>
        
        <div style="overflow-x: auto;">
            <?php if (empty($pendingList)): ?>
                <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                    <i class="fa-solid fa-circle-check" style="font-size: 2rem; margin-bottom: 15px; color: var(--success);"></i>
                    <p style="font-weight: 500;">Bekleyen yeni bir teklif bulunmuyor.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Müşteri</th>
                            <th>Hizmet / Kategori</th>
                            <th>Tarih / Saat</th>
                            <th>Fiyat</th>
                            <th style="text-align: right;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingList as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($row['customer_name']); ?></strong>
                                    <span style="display: block; font-size: 0.8rem; color: var(--text-muted);"><i class="fa-solid fa-phone" style="font-size: 0.75rem;"></i> <?php echo e(formatPhoneDisplay($row['customer_phone'])); ?></span>
                                </td>
                                <td>
                                    <strong style="display: block;"><?php echo e($row['category_name']); ?></strong>
                                    <?php if ($row['subcategory_name']): ?>
                                        <span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($row['subcategory_name']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="display: block;"><?php echo date('d.m.Y', strtotime($row['booking_date'])); ?></strong>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e(translateTimeSlot($row['booking_time_slot'])); ?></span>
                                </td>
                                <td>
                                    <strong style="color: var(--primary);"><?php echo formatPrice($row['total_price']); ?></strong>
                                    <?php if ($row['package_name']): ?>
                                        <span style="display: block; font-size: 0.75rem; color: var(--success); font-weight: 600;">(<?php echo e($row['package_name']); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <button onclick='openOnaylaModal(<?php echo json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="btn btn-primary" style="padding: 6px 14px; font-size: 0.8rem; background-color: var(--success); box-shadow: none;">Onayla</button>
                                    <a href="index.php?action=cancel&id=<?php echo $row['id']; ?>" class="btn btn-outline" style="padding: 6px 14px; font-size: 0.8rem; margin-left: 5px; color: var(--danger); border-color: var(--danger);">Reddet</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Rezervasyon Düzenleme Modal -->
<div class="admin-modal" id="editBookingModal">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title">Rezervasyonu Düzenle / Tarih Değiştir</h3>
            <span class="modal-close" onclick="closeEditBookingModal()">&times;</span>
        </div>
        <form action="index.php" method="POST" onsubmit="return validateEditBookingForm();">
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
                            <input type="text" name="customer_phone" id="edit_booking_cust_phone" class="form-control" placeholder="555 555 55 55" maxlength="13" style="font-size: 0.9rem; padding: 8px 16px;" required>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const categoriesData = <?php echo json_encode($categoriesJsonData); ?>;
const chartData = <?php echo json_encode($chartData); ?>;

document.addEventListener("DOMContentLoaded", function() {
    // Grafik detayını varsayılan olarak Temmuz ayıyla yükle (index 1)
    showMonthDetails(1);
    
    const ctx = document.getElementById('profitChart').getContext('2d');
    const myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.map(d => d.month),
            datasets: [{
                label: 'Aylık Net Kar (₺)',
                data: chartData.map(d => d.profit),
                backgroundColor: 'rgba(37, 99, 235, 0.65)',
                borderColor: '#2563eb',
                borderWidth: 2,
                borderRadius: 8,
                hoverBackgroundColor: 'rgba(37, 99, 235, 0.85)',
                barPercentage: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Net Kar: ' + formatCurrency(context.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            onClick: (event, elements) => {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    showMonthDetails(index);
                }
            }
        }
    });
});

function showMonthDetails(index) {
    const data = chartData[index];
    if (!data) return;
    document.getElementById('details_month_name').innerText = data.month + ' 2026';
    document.getElementById('details_revenue').innerText = formatCurrency(data.revenue);
    document.getElementById('details_expense').innerText = formatCurrency(data.expense);
    const profitEl = document.getElementById('details_profit');
    profitEl.innerText = formatCurrency(data.profit);
    if (data.profit >= 0) {
        profitEl.style.color = 'var(--success)';
    } else {
        profitEl.style.color = 'var(--danger)';
    }
}

function formatCurrency(val) {
    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(val);
}

// Modal JS Kodları
function openOnaylaModal(row) {
    const confirmRow = Object.assign({}, row);
    confirmRow.status = 'confirmed';
    openEditBooking(confirmRow);
}

function openEditBooking(row) {
    document.getElementById("edit_booking_id").value = row.id;
    document.getElementById("edit_booking_package_id").value = row.package_id || '';
    document.getElementById("edit_booking_cust_name").value = row.customer_name;
    document.getElementById("edit_booking_cust_phone").value = row.customer_phone;
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
    
    loadBookingEmployeeAvailability();
    
    document.getElementById("editBookingModal").classList.add("active");
}

function closeEditBookingModal() {
    document.getElementById("editBookingModal").classList.remove("active");
}

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
        return; // Abonelik paketi fiyatını koru
    }
    const catId = parseInt(document.getElementById("edit_booking_cat_id").value) || 0;
    const cat = categoriesData.find(c => c.id === catId);
    if (!cat) return;
    
    if (cat.pricing_type === 'discovery') {
        return;
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
    
    const checkedEmployees = document.querySelectorAll('#bookingEmployeeSelectionList input[name="employee_ids[]"]:checked');
    const checkedCount = checkedEmployees.length;
    
    if (status === 'confirmed' || status === 'completed') {
        if (checkedCount !== requiredPersonCount) {
            alert("Hata: Onaylanmış veya Tamamlanmış bir rezervasyon için tam olarak " + requiredPersonCount + " personel seçmelisiniz. Şu an " + checkedCount + " personel seçtiniz.");
            return false;
        }
    } else {
        if (checkedCount > 0 && checkedCount !== requiredPersonCount) {
            alert("Hata: Personel ataması yapıyorsanız tam olarak " + requiredPersonCount + " personel seçmelisiniz. Şu an " + checkedCount + " personel seçtiniz.");
            return false;
        }
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
