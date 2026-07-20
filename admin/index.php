<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../classes/Booking.php';

$bookingModel = new Booking();

// Onaylama veya İptal etme işlemleri
$msg = '';
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    if ($action === 'approve') {
        if ($bookingModel->updateStatus($id, 'confirmed')) {
            $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Teklif başarıyla onaylandı ve personeller atandı!</div>';
        }
    } else if ($action === 'cancel') {
        if ($bookingModel->updateStatus($id, 'cancelled')) {
            $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-xmark"></i> Teklif iptal edildi.</div>';
        }
    }
}

// İstatistikleri çek
$stats = $bookingModel->getStats();

// Bugünün işlerini çek
$todayJobs = $bookingModel->getTodayJobs();

// Son bekleyen teklifleri çek
$pendingBookings = $bookingModel->getAll(['status' => 'pending']);
?>

<div style="max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 30px;">
    
    <!-- Hoşgeldin ve Profil Özet Kartı (Mockup Sol Blok) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <!-- Welcome Card -->
        <div class="card" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 1px solid rgba(37, 99, 235, 0.15); display: flex; align-items: center; gap: 20px; padding: 25px; border-radius: var(--radius-card); position: relative; overflow: hidden; box-shadow: var(--shadow-sm);">
            <div style="width: 70px; height: 70px; border-radius: 50%; background-color: var(--primary); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 800; border: 4px solid #ffffff; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                <?php echo strtoupper(substr($adminUsername, 0, 2)); ?>
            </div>
            <div>
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--primary);">Hoş Geldiniz,</span>
                <h3 style="font-size: 1.35rem; font-weight: 800; margin-top: 2px;"><?php echo e($adminUsername); ?> W.</h3>
                <a href="ayarlar.php" class="btn btn-primary" style="padding: 6px 16px; font-size: 0.78rem; margin-top: 10px; border-radius: 12px; background-color: #ffffff; color: var(--primary); border: 1px solid rgba(37, 99, 235, 0.15);">Ayarları Yönet</a>
            </div>
        </div>
        
        <!-- Diğer Hızlı Analiz Kartları (Mockup Yan Bloklar) -->
        <div class="card" style="display: flex; align-items: center; justify-content: space-between; padding: 25px;">
            <div>
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Toplam Ciro</span>
                <h3 style="font-size: 1.85rem; font-weight: 900; color: var(--primary); margin-top: 5px;"><?php echo formatPrice($stats['total_revenue']); ?></h3>
                <span style="font-size: 0.75rem; color: var(--success); font-weight: 600; margin-top: 5px; display: block;"><i class="fa-solid fa-arrow-trend-up"></i> +%12 geçen aya göre</span>
            </div>
            <div style="width: 50px; height: 50px; border-radius: 50%; background-color: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-wallet"></i>
            </div>
        </div>
        
        <div class="card" style="display: flex; align-items: center; justify-content: space-between; padding: 25px;">
            <div>
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Rezervasyonlar</span>
                <h3 style="font-size: 1.85rem; font-weight: 900; color: var(--text-main); margin-top: 5px;"><?php echo $stats['total_bookings']; ?></h3>
                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500; margin-top: 5px; display: block;">Aktif ve tamamlanan</span>
            </div>
            <div style="width: 50px; height: 50px; border-radius: 50%; background-color: #f0fdf4; color: var(--success); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>
        </div>
    </div>
    
    <?php echo $msg; ?>
    
    <!-- Today's Jobs Grid / Tables Section -->
    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 30px; margin-bottom: 40px; align-items: start;">
        
        <!-- Left: Today's Jobs -->
        <div class="admin-table-wrapper" style="margin-bottom: 0;">
            <div class="table-header-bar">
                <h3 style="font-weight: 800; font-size: 1.15rem;"><i class="fa-solid fa-calendar-day" style="color: var(--primary); margin-right: 8px;"></i> Bugünün Temizlik İşleri</h3>
                <span class="badge badge-confirmed" style="font-size: 0.75rem;"><?php echo date('d.m.Y'); ?></span>
            </div>
            
            <div style="overflow-x: auto;">
                <?php if (empty($todayJobs)): ?>
                    <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                        <i class="fa-solid fa-mug-hot" style="font-size: 2rem; margin-bottom: 15px; color: var(--border);"></i>
                        <p style="font-weight: 500;">Bugün planlanmış bir temizlik işi bulunmuyor.</p>
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Zaman</th>
                                <th>Müşteri</th>
                                <th>Kategori</th>
                                <th>Atanan Personeller</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todayJobs as $job): ?>
                                <tr>
                                    <td>
                                        <strong style="display: block;"><?php echo e(translateTimeSlot($job['time_slot'])); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo e($job['customer_name']); ?></strong>
                                        <span style="display: block; font-size: 0.8rem; color: var(--text-muted);"><?php echo e($job['customer_phone']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: <?php echo e($job['category_color']); ?>15; color: <?php echo e($job['category_color']); ?>;">
                                            <?php echo e($job['category_name']); ?>

                                        </span>
                                    </td>
                                    <td>
                                        <?php if (empty($job['employees'])): ?>
                                            <span style="color: var(--danger); font-weight: 600;"><i class="fa-solid fa-triangle-exclamation"></i> Atanmamış!</span>
                                        <?php else: ?>
                                            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                                <?php foreach ($job['employees'] as $emp): ?>
                                                    <span class="badge" style="background-color: #f1f5f9; color: var(--text-main); padding: 4px 10px; font-weight: 600;">
                                                        <?php echo e($emp['name']); ?>

                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Right: Recent Activity / Quick Stats (Mockup Replica) -->
        <div class="card" style="padding: 25px; display: flex; flex-direction: column; justify-content: space-between; height: 100%;">
            <div>
                <h3 style="font-weight: 800; font-size: 1.1rem; margin-bottom: 20px; color: var(--text-main);">Rezervasyon Dağılımı</h3>
                
                <!-- SVG Donut Chart Mockup -->
                <div style="position: relative; width: 140px; height: 140px; margin: 0 auto 25px auto; display: flex; align-items: center; justify-content: center;">
                    <svg width="140" height="140" viewBox="0 0 36 36" style="transform: rotate(-90deg); width: 100%; height: 100%;">
                        <circle cx="18" cy="18" r="15.915" fill="none" stroke="#f1f5f9" stroke-width="3.5"></circle>
                        <circle cx="18" cy="18" r="15.915" fill="none" stroke="var(--primary)" stroke-width="3.5" stroke-dasharray="75 25" stroke-dashoffset="0" stroke-linecap="round"></circle>
                    </svg>
                    <div style="position: absolute; text-align: center;">
                        <span style="font-size: 1.5rem; font-weight: 900; color: var(--text-main); display: block;">75%</span>
                        <span style="font-size: 0.65rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Onay Oranı</span>
                    </div>
                </div>
            </div>
            
            <div style="border-top: 1px solid var(--border); padding-top: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background-color: var(--primary);"></span>
                        Onaylanan Rezervasyonlar
                    </span>
                    <strong style="font-size: 0.85rem; color: var(--text-main);">75%</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="display: flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background-color: #cbd5e1;"></span>
                        Bekleyen Teklifler
                    </span>
                    <strong style="font-size: 0.85rem; color: var(--text-main);">25%</strong>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Pending Quotes Table -->
    <div class="admin-table-wrapper">
        <div class="table-header-bar">
            <h3 style="font-weight: 800; font-size: 1.15rem;"><i class="fa-solid fa-clock-rotate-left" style="color: var(--warning); margin-right: 8px;"></i> Onay Bekleyen Yeni Teklifler</h3>
            <span class="badge badge-pending" style="font-size: 0.75rem;"><?php echo count($pendingBookings); ?> Bekliyor</span>
        </div>
        
        <div style="overflow-x: auto;">
            <?php if (empty($pendingBookings)): ?>
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
                        <?php foreach ($pendingBookings as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($row['customer_name']); ?></strong>
                                    <span style="display: block; font-size: 0.8rem; color: var(--text-muted);"><?php echo e($row['customer_phone']); ?></span>
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
                                    <a href="index.php?action=approve&id=<?php echo $row['id']; ?>" class="btn btn-primary" style="padding: 6px 14px; font-size: 0.8rem; background-color: var(--success); box-shadow: none;">Onayla</a>
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

<?php require_once __DIR__ . '/footer.php'; ?>
