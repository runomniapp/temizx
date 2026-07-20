<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Booking.php';
require_once __DIR__ . '/../classes/Category.php';

$monthsTr = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];

$employeeModel = new Employee();
$bookingModel = new Booking();
$categoryModel = new Category();

$activeView = isset($_GET['view']) && $_GET['view'] === 'furniture' ? 'furniture' : 'general';
$allEmployees = $employeeModel->getAll(true, $activeView); // Aktif çalışanlar
$allCategories = $categoryModel->getAll(true, $activeView); // Aktif kategoriler

// Alt kategorilerle ilişkilendirilmiş dizi
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

// Hafta kaydırma hesabı (Week Offset)
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
// Pazartesi gününü bul
$mondayTimestamp = strtotime("$weekOffset weeks", strtotime('monday this week'));
$startDate = date('Y-m-d', $mondayTimestamp);
$endDate = date('Y-m-d', strtotime('+6 days', $mondayTimestamp));

// Haftanın günlerini hesapla
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $dateStr = date('Y-m-d', strtotime("+$i days", $mondayTimestamp));
    $weekDates[$i] = [
        'date' => $dateStr,
        'day_num' => date('d', strtotime($dateStr)),
        'day_name' => translateDay(date('l', strtotime($dateStr))),
        'day_eng' => date('l', strtotime($dateStr))
    ];
}

// Bu haftaki tüm takvim işlerini çek (Filtrelenmiş)
$weekJobs = $bookingModel->getScheduleRange($startDate, $endDate, $activeView);

// İşleri çalışanlara ve günlere göre grupla
$groupedJobs = [];
// Ayrıca personelsiz (atanmamış) işler için sanal bir satır oluşturmak üzere atanmamış listesi
$unassignedJobs = [];

foreach ($weekJobs as $job) {
    if (empty($job['employees'])) {
        $unassignedJobs[$job['date']][] = $job;
    } else {
        foreach ($job['employees'] as $emp) {
            $groupedJobs[$emp['id']][$job['date']][] = $job;
        }
    }
}
?>

<div style="max-width: 1200px; margin: 0 auto;">
    <!-- Calendar View Filter Tabs -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 15px; gap: 15px; flex-wrap: wrap;">
        <div style="display: flex; gap: 10px;">
            <a href="takvim.php?week=<?php echo $weekOffset; ?>&view=general" class="btn <?php echo $activeView === 'general' ? 'btn-primary' : 'btn-outline'; ?>" style="padding: 10px 20px; border-radius: var(--radius-pill); font-size: 0.9rem; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-house-chimney"></i> Genel Temizlik Takvimi
            </a>
            <a href="takvim.php?week=<?php echo $weekOffset; ?>&view=furniture" class="btn <?php echo $activeView === 'furniture' ? 'btn-primary' : 'btn-outline'; ?>" style="padding: 10px 20px; border-radius: var(--radius-pill); font-size: 0.9rem; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-couch"></i> Koltuk & Yatak Yıkama Takvimi
            </a>
        </div>
        <a href="rezervasyonlar.php?new=1" class="btn btn-primary" style="padding: 10px 20px; border-radius: var(--radius-pill); font-size: 0.9rem; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-plus"></i> Manuel Rezervasyon / Abonelik Ekle
        </a>
    </div>

    <div class="calendar-section-container">
        
        <!-- Calendar Controls -->
        <div class="calendar-control-bar">
            <div class="calendar-title-info">
                <div style="display: flex; gap: 8px;">
                    <a href="takvim.php?week=<?php echo $weekOffset - 1; ?>&view=<?php echo $activeView; ?>" class="calendar-nav-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    <a href="takvim.php?week=0&view=<?php echo $activeView; ?>" class="btn btn-outline" style="padding: 8px 18px; font-size: 0.85rem;">Bugün</a>
                    <a href="takvim.php?week=<?php echo $weekOffset + 1; ?>&view=<?php echo $activeView; ?>" class="calendar-nav-btn"><i class="fa-solid fa-chevron-right"></i></a>
                </div>
                <h3 class="calendar-period-title">
                    <?php 
                    $startMonth = translateDay(date('F', strtotime($startDate))); // let's use date formats
                    $startFormatted = date('d', strtotime($startDate)) . ' ' . $monthsTr[date('n', strtotime($startDate)) - 1];
                    $endFormatted = date('d', strtotime($endDate)) . ' ' . $monthsTr[date('n', strtotime($endDate)) - 1] . ' ' . date('Y', strtotime($endDate));
                    echo $startFormatted . ' - ' . $endFormatted;
                    ?>
                </h3>
            </div>
            
            <div class="calendar-legend">
                <div class="legend-item">
                    <span class="legend-color" style="background-color: #e0f7ff; border-left: 3px solid #00d2ff;"></span>
                    <span>Sabah (08-12)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color" style="background-color: #f0e6ff; border-left: 3px solid #8c52ff;"></span>
                    <span>Öğleden Sonra (13-17)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color" style="background-color: var(--primary-light); border-left: 3px solid var(--primary);"></span>
                    <span>Tam Gün (08-17)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color" style="background-color: #e6fced; border-left: 3px solid var(--success);"></span>
                    <span>Tamamlandı</span>
                </div>
            </div>
        </div>
        
        <!-- Scheduler Table -->
        <div class="scheduler-table-wrapper">
            <table class="scheduler-table">
                <thead>
                    <tr>
                        <th style="width: 180px;">Personel</th>
                        <?php foreach ($weekDates as $wd): ?>
                            <th>
                                <div style="font-weight: 800; font-size: 1rem;"><?php echo $wd['day_num']; ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;"><?php echo $wd['day_name']; ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <!-- Sanal Atanmamış Satırı (Unassigned Jobs Row) -->
                    <tr style="background-color: #fffaf0;">
                        <td>
                            <div class="employee-cell" style="background-color: transparent;">
                                <div style="width: 32px; height: 32px; border-radius: 50%; background-color: #ff9f4315; color: #ff9f43; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </div>
                                <span style="color: #ff9f43; font-weight: 700;">Atanmamış İşler</span>
                            </div>
                        </td>
                        <?php foreach ($weekDates as $wd): ?>
                            <td class="scheduler-day-column" data-date="<?php echo $wd['date']; ?>" data-employee-id="unassigned">
                                <div style="display: flex; flex-direction: column; gap: 6px; padding: 6px; min-height: 135px; box-sizing: border-box; width: 100%;">
                                    <?php 
                                    if (isset($unassignedJobs[$wd['date']])) {
                                        foreach ($unassignedJobs[$wd['date']] as $job) {
                                            $slotClass = 'slot-full';
                                             if ($job['time_slot'] === '08-12') $slotClass = 'slot-morning';
                                             else if ($job['time_slot'] === '13-17') $slotClass = 'slot-afternoon';
                                             if ($job['status'] === 'completed' || ($job['date'] < date('Y-m-d') && $job['status'] !== 'cancelled')) $slotClass .= ' status-completed';
                                            
                                             $dataAttrs = ' data-job-id="' . (int)$job['id'] . '"';
                                             $dataAttrs .= ' data-customer-name="' . e($job['customer_name']) . '"';
                                             $dataAttrs .= ' data-time-slot="' . $job['time_slot'] . '"';
                                             $dataAttrs .= ' data-booking-id="' . $job['booking_id'] . '"';
                                             $dataAttrs .= ' data-service-days="' . $job['service_days'] . '"';
                                             $dataAttrs .= ' data-package-id="' . ($job['package_id'] ?? '') . '"';
                                             $dataAttrs .= ' data-duration-weeks="' . ($job['duration_weeks'] ?? '') . '"';
                                             
                                             echo '<div class="job-block ' . $slotClass . ' draggable-job" draggable="true"' . $dataAttrs . ' style="border-style: dashed; border-width: 2px; cursor: pointer; margin-bottom: 0;">';
                                             echo '<strong>' . e($job['customer_name']) . '</strong>';
                                             echo '<div style="font-size: 0.7rem; opacity: 0.85; margin-top: 3px;">' . e(translateTimeSlot($job['time_slot'])) . '</div>';
                                             echo '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    
                    <!-- Çalışan Satırları -->
                    <?php foreach ($allEmployees as $emp): ?>
                        <tr>
                            <td>
                                <div class="employee-cell">
                                    <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                        <?php echo strtoupper(substr($emp['name'], 0, 2)); ?>
                                    </div>
                                    <span><?php echo e($emp['name']); ?></span>
                                </div>
                            </td>
                            <?php foreach ($weekDates as $wd): ?>
                                <?php 
                                // İzin günü kontrolü
                                $offDays = array_map('trim', explode(',', $emp['off_days']));
                                $isOff = in_array($wd['day_eng'], $offDays);
                                ?>
                                <td class="scheduler-day-column <?php echo $isOff ? 'off-day' : ''; ?>" data-date="<?php echo $wd['date']; ?>" data-employee-id="<?php echo $emp['id']; ?>" data-employee-name="<?php echo e($emp['name']); ?>" data-is-off="<?php echo $isOff ? '1' : '0'; ?>">
                                    <div style="display: flex; flex-direction: column; gap: 6px; padding: 6px; min-height: 135px; box-sizing: border-box; width: 100%;">
                                        <?php if ($isOff): ?>
                                             <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; font-size: 0.75rem; color: var(--text-muted); font-weight: 600; min-height: 123px; flex: 1;">
                                                 <i class="fa-solid fa-bed" style="font-size: 1rem; color: var(--text-muted);"></i>
                                                 <span>İzinli</span>
                                             </div>
                                        <?php else: ?>
                                             <?php 
                                             if (isset($groupedJobs[$emp['id']][$wd['date']])) {
                                                 foreach ($groupedJobs[$emp['id']][$wd['date']] as $job) {
                                                     $slotClass = 'slot-full';
                                                      if ($job['time_slot'] === '08-12') $slotClass = 'slot-morning';
                                                      else if ($job['time_slot'] === '13-17') $slotClass = 'slot-afternoon';
                                                      if ($job['status'] === 'completed' || ($job['date'] < date('Y-m-d') && $job['status'] !== 'cancelled')) $slotClass .= ' status-completed';
                                                     
                                                      $dataAttrs = ' data-job-id="' . (int)$job['id'] . '"';
                                                      $dataAttrs .= ' data-customer-name="' . e($job['customer_name']) . '"';
                                                      $dataAttrs .= ' data-time-slot="' . $job['time_slot'] . '"';
                                                      $dataAttrs .= ' data-booking-id="' . $job['booking_id'] . '"';
                                                      $dataAttrs .= ' data-service-days="' . $job['service_days'] . '"';
                                                      $dataAttrs .= ' data-package-id="' . ($job['package_id'] ?? '') . '"';
                                                      $dataAttrs .= ' data-duration-weeks="' . ($job['duration_weeks'] ?? '') . '"';
                                                      
                                                      echo '<div class="job-block ' . $slotClass . ' draggable-job" draggable="true"' . $dataAttrs . ' style="cursor: pointer; margin-bottom: 0;">';
                                                      echo '<strong>' . e($job['customer_name']) . '</strong>';
                                                      echo '<div style="font-size: 0.7rem; opacity: 0.85; margin-top: 3px;">' . e(translateTimeSlot($job['time_slot'])) . '</div>';
                                                      echo '</div>';
                                                 }
                                             }
                                             ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    </div>
</div>

<!-- Job Details & Employee Assignment Modal -->
<div class="admin-modal" id="jobDetailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Temizlik İş Detayı</h3>
            <span class="modal-close" onclick="closeJobDetailsModal()">&times;</span>
        </div>
        <form id="assignmentForm">
            <?php csrfInput(); ?>
            <input type="hidden" name="schedule_id" id="modal_schedule_id">
            <input type="hidden" name="category_id" id="edit_category_id">
            <input type="hidden" name="package_id" id="edit_package_id">
            
            <div class="modal-body" style="max-height: 480px; overflow-y: auto;">
                <!-- Job Edit Form Fields -->
                <div style="background-color: var(--background); padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; flex-direction: column; gap: 15px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Müşteri Adı *</label>
                            <input type="text" name="customer_name" id="edit_customer_name" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Telefon *</label>
                            <input type="text" name="customer_phone" id="edit_customer_phone" class="form-control" placeholder="555-555-55-55" maxlength="13" style="font-size: 0.9rem; padding: 8px 16px;" required>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1.2fr 1fr 0.8fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Tarih *</label>
                            <input type="date" name="date" id="edit_date" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Saat Dilimi *</label>
                            <select name="time_slot" id="edit_time_slot" class="form-control" style="font-size: 0.9rem; padding: 8px 16px; height: auto;" required>
                                <option value="08-17">Tam Gün (08-17)</option>
                                <option value="08-12">Sabah (08-12)</option>
                                <option value="13-17">Öğleden Sonra (13-17)</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Gün Sayısı *</label>
                            <input type="number" name="service_days" id="edit_service_days" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" min="1" required>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 6px; font-weight: 700;">Hizmet (Hizmet Değiştir) *</label>
                        <div id="category_pills_container" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px;"></div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Görevli Sayısı *</label>
                            <input type="number" name="person_count" id="edit_person_count" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" min="1" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Toplam Fiyat (₺) *</label>
                            <input type="number" name="total_price" id="edit_total_price" class="form-control" style="font-size: 0.9rem; padding: 8px 16px;" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-group" id="edit_subcategory_container" style="margin-bottom: 0; display: none;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Alt Kategori *</label>
                        <select name="subcategory_id" id="edit_subcategory_id" class="form-control" style="font-size: 0.9rem; padding: 8px 16px; height: auto;"></select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Adres *</label>
                        <textarea name="customer_address" id="edit_customer_address" class="form-control" rows="2" style="font-size: 0.9rem; padding: 10px 16px; border-radius: 14px; resize: none;" required></textarea>
                    </div>
                </div>
                
                <!-- Employee Assignment list -->
                <h4 style="font-weight: 800; font-size: 0.95rem; margin-bottom: 15px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">Personel Ataması</h4>
                
                <div id="employeeSelectionList" style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- Dynamically populated via JS -->
                </div>
                
                <div id="assignErrorAlert" style="display: none; background-color: #fef2f2; color: var(--danger); padding: 12px 16px; border-radius: 12px; font-weight: 600; font-size: 0.85rem; margin-top: 20px;">
                    Hata oluştu!
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeJobDetailsModal()">Kapat</button>
                <button type="submit" class="btn btn-primary" id="saveAssignmentBtn">Atamayı Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Sürükle Bırak Onay Modali -->
<div id="dragDropModal" class="admin-modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="padding: 20px 25px;">
            <h3 style="font-weight: 800; font-size: 1.1rem; margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-arrows-up-down-left-right" style="color: var(--primary);"></i>
                İş Taşımayı Onayla
            </h3>
        </div>
        <form id="dragDropForm" onsubmit="submitDragDrop(event)">
            <input type="hidden" id="drag_schedule_id" name="schedule_id">
            <input type="hidden" id="drag_target_date" name="target_date">
            <input type="hidden" id="drag_target_employee_id" name="target_employee_id">
            
            <div class="modal-body" style="padding: 20px 25px; display: flex; flex-direction: column; gap: 12px;">
                <div style="font-size: 0.9rem;">
                    <span style="font-weight: 600; color: var(--text-muted);">Müşteri:</span>
                    <strong id="drag_info_customer" style="color: var(--text-main); margin-left: 5px;">-</strong>
                </div>
                <div style="font-size: 0.9rem;">
                    <span style="font-weight: 600; color: var(--text-muted);">Yeni Tarih:</span>
                    <strong id="drag_info_date" style="color: var(--text-main); margin-left: 5px;">-</strong>
                </div>
                <div style="font-size: 0.9rem;">
                    <span style="font-weight: 600; color: var(--text-muted);">Yeni Görevli:</span>
                    <strong id="drag_info_employee" style="color: var(--text-main); margin-left: 5px;">-</strong>
                </div>
                
                <div class="form-group" style="margin-top: 10px; margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.85rem; font-weight: 700; margin-bottom: 8px;">Saat Dilimi Seçin *</label>
                    <div style="display: flex; flex-direction: column; gap: 8px;" id="drag_slot_options">
                        <label style="display: flex; align-items: center; gap: 10px; padding: 10px 15px; border: 1px solid var(--border); border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.85rem; margin-bottom: 0;" id="drag_slot_label_08-17">
                            <input type="radio" name="target_time_slot" value="08-17" id="drag_slot_08-17" style="width: 18px; height: 18px; accent-color: var(--primary);" required>
                            Tam Gün (08-17)
                            <span class="badge" id="drag_badge_08-17" style="margin-left: auto;"></span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; padding: 10px 15px; border: 1px solid var(--border); border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.85rem; margin-bottom: 0;" id="drag_slot_label_08-12">
                            <input type="radio" name="target_time_slot" value="08-12" id="drag_slot_08-12" style="width: 18px; height: 18px; accent-color: var(--primary);" required>
                            Sabah (08-12)
                            <span class="badge" id="drag_badge_08-12" style="margin-left: auto;"></span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; padding: 10px 15px; border: 1px solid var(--border); border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.85rem; margin-bottom: 0;" id="drag_slot_label_13-17">
                            <input type="radio" name="target_time_slot" value="13-17" id="drag_slot_13-17" style="width: 18px; height: 18px; accent-color: var(--primary);" required>
                            Öğleden Sonra (13-17)
                            <span class="badge" id="drag_badge_13-17" style="margin-left: auto;"></span>
                        </label>
                    </div>
                </div>
                
                <div id="dragDropErrorAlert" style="display: none; background-color: #fef2f2; color: var(--danger); padding: 10px 14px; border-radius: 10px; font-weight: 600; font-size: 0.8rem; margin-top: 5px;">
                    Hata oluştu!
                </div>
            </div>
            <div class="modal-footer" style="padding: 15px 25px;">
                <button type="button" class="btn btn-outline" onclick="closeDragDropModal()" style="padding: 8px 18px;">İptal</button>
                <button type="submit" class="btn btn-primary" id="confirmDragDropBtn" style="padding: 8px 18px;">Taşımayı Onayla</button>
            </div>
        </form>
    </div>
</div>

<script>
// PHP verilerini JS ortamına al
const activeEmployees = <?php echo json_encode($allEmployees); ?>;
const weekDates = <?php echo json_encode($weekDates); ?>;
const weekJobs = <?php echo json_encode($weekJobs); ?>;
const categoriesData = <?php echo json_encode($categoriesJsonData); ?>;

function renderCategoryPills(activeCatId, selectedSubcatId = 0) {
    const container = document.getElementById("category_pills_container");
    container.innerHTML = "";
    
    categoriesData.forEach(cat => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "btn " + (cat.id === activeCatId ? "btn-primary" : "btn-outline");
        btn.style.padding = "6px 14px";
        btn.style.fontSize = "0.82rem";
        btn.innerText = cat.name;
        
        btn.onclick = function() {
            document.getElementById("edit_category_id").value = cat.id;
            document.querySelectorAll("#category_pills_container button").forEach(b => {
                b.className = "btn btn-outline";
            });
            btn.className = "btn btn-primary";
            handleSubcategoryDisplay(cat, 0);
            recalculateTotalPrice();
        };
        
        container.appendChild(btn);
    });
    
    const activeCat = categoriesData.find(c => c.id === activeCatId) || categoriesData[0];
    document.getElementById("edit_category_id").value = activeCat ? activeCat.id : 0;
    handleSubcategoryDisplay(activeCat, selectedSubcatId);
}

function handleSubcategoryDisplay(category, selectedSubcatId = 0) {
    const subContainer = document.getElementById("edit_subcategory_container");
    const subSelect = document.getElementById("edit_subcategory_id");
    
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

function recalculateTotalPrice() {
    const pkgId = document.getElementById("edit_package_id").value;
    if (pkgId) {
        return; // Abonelik paketi fiyatını sıfırlamayalım, sabit kalsın.
    }
    const catId = parseInt(document.getElementById("edit_category_id").value) || 0;
    const cat = categoriesData.find(c => c.id === catId);
    if (!cat) return;
    
    if (cat.pricing_type === 'discovery') {
        return; // Keşifli fiyatlandırmada ellemeyelim
    }
    
    const timeSlot = document.getElementById("edit_time_slot").value;
    const isHalfDay = (timeSlot === '08-12' || timeSlot === '13-17');
    
    let maxPerson = 1;
    let basePrice = 0;
    let extraPersonPrice = 0;
    
    if (cat.pricing_type === 'subcategory') {
        const subId = parseInt(document.getElementById("edit_subcategory_id").value) || 0;
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
    
    const personCount = parseInt(document.getElementById("edit_person_count").value) || 1;
    const serviceDays = parseInt(document.getElementById("edit_service_days").value) || 1;
    
    const extraCount = Math.max(0, personCount - maxPerson);
    const totalPrice = (basePrice + (extraCount * extraPersonPrice)) * serviceDays;
    
    document.getElementById("edit_total_price").value = totalPrice.toFixed(2);
}

function getBookingDates(startDateStr, daysCount, isWeekly, durationWeeks) {
    const dates = [];
    if (!startDateStr) return dates;
    
    let baseDate = new Date(startDateStr);
    const count = isWeekly ? durationWeeks : daysCount;
    
    for (let i = 0; i < count; i++) {
        let occDate = new Date(baseDate);
        if (isWeekly) {
            occDate.setDate(baseDate.getDate() + (i * 7));
        } else {
            occDate.setDate(baseDate.getDate() + i);
        }
        const yyyy = occDate.getFullYear();
        const mm = String(occDate.getMonth() + 1).padStart(2, '0');
        const dd = String(occDate.getDate()).padStart(2, '0');
        dates.push(`${yyyy}-${mm}-${dd}`);
    }
    return dates;
}

function renderEmployeeSelection(date, timeSlot, job) {
    const assignedIds = job.employees.map(e => parseInt(e.id));
    const listContainer = document.getElementById("employeeSelectionList");
    listContainer.innerHTML = "";
    
    const isWeekly = !!job.package_id;
    const durationWeeks = parseInt(job.duration_weeks) || 1;
    const daysCount = parseInt(document.getElementById("edit_service_days").value) || 1;
    const startDate = document.getElementById("edit_date").value;
    
    const datesToCheck = getBookingDates(startDate, daysCount, isWeekly, durationWeeks);
    
    activeEmployees.forEach(emp => {
        const empId = parseInt(emp.id);
        const isAssigned = assignedIds.includes(empId);
        
        let isOff = false;
        let hasOverlap = false;
        let overlapCustomer = '';
        let overlapDate = '';
        
        datesToCheck.forEach(chkDate => {
            const dateObj = new Date(chkDate);
            const daysEng = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const dayEng = daysEng[dateObj.getDay()];
            
            const offDays = emp.off_days.split(',').map(d => d.trim());
            if (offDays.includes(dayEng)) {
                isOff = true;
            }
            
            // Check overlaps
            weekJobs.forEach(wj => {
                if (wj.date === chkDate && parseInt(wj.booking_id) !== parseInt(job.booking_id) && wj.status !== 'cancelled') {
                    const isAssignedToOther = wj.employees.some(e => parseInt(e.id) === empId);
                    if (isAssignedToOther) {
                        if (isOverlapSlots(wj.time_slot, timeSlot)) {
                            hasOverlap = true;
                            overlapCustomer = wj.customer_name;
                            overlapDate = chkDate;
                        }
                    }
                }
            });
        });
        
        const item = document.createElement("div");
        item.style.display = "flex";
        item.style.alignItems = "center";
        item.style.justifyContent = "space-between";
        item.style.padding = "10px 15px";
        item.style.border = "1px solid var(--border)";
        item.style.borderRadius = "10px";
        
        let statusBadgeHtml = '';
        let isDisabled = false;
        
        if (isOff) {
            statusBadgeHtml = '<span class="badge" style="background-color: #fef2f2; color: var(--danger); font-size: 0.75rem;">İzin Günü</span>';
            isDisabled = true;
        } else if (hasOverlap) {
            const dParts = overlapDate.split('-');
            const dateFormatted = `${dParts[2]}.${dParts[1]}`;
            statusBadgeHtml = `<span class="badge" style="background-color: #fff8e6; color: var(--warning); font-size: 0.75rem;" title="${overlapCustomer} ile çakışıyor (${dateFormatted})">Meşgul (${dateFormatted})</span>`;
            isDisabled = true;
        } else {
            statusBadgeHtml = '<span class="badge" style="background-color: #ecfdf5; color: var(--success); font-size: 0.75rem;">Müsait</span>';
        }
        
        item.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <input type="checkbox" name="employee_ids[]" value="${emp.id}" id="emp_check_${emp.id}" 
                       ${isAssigned ? 'checked' : ''} ${isDisabled ? 'disabled' : ''} 
                       style="width: 18px; height: 18px; accent-color: var(--primary);">
                <label for="emp_check_${emp.id}" style="font-weight: 600; cursor: ${isDisabled ? 'not-allowed' : 'pointer'};">${emp.name}</label>
            </div>
            <div>${statusBadgeHtml}</div>
        `;
        listContainer.appendChild(item);
    });
}

function openJobDetailsModal(job) {
    try {
        document.getElementById("modal_schedule_id").value = job.id;
        document.getElementById("edit_package_id").value = job.package_id || '';
        document.getElementById("edit_customer_name").value = job.customer_name || '';
        document.getElementById("edit_customer_phone").value = job.customer_phone || '';
        document.getElementById("edit_date").value = job.date || '';
        document.getElementById("edit_time_slot").value = job.time_slot || '08-17';
        document.getElementById("edit_person_count").value = job.person_count || 1;
        document.getElementById("edit_service_days").value = job.service_days || 1;
        document.getElementById("edit_total_price").value = parseFloat(job.total_price || 0).toFixed(2);
        document.getElementById("edit_customer_address").value = job.customer_address || '';
        
        // Render category pills
        var catId = parseInt(job.category_id) || 0;
        var subCatId = parseInt(job.subcategory_id) || 0;
        renderCategoryPills(catId, subCatId);
        
        // Render initially
        renderEmployeeSelection(job.date, job.time_slot, job);
        
        // Dynamic changes trigger list update
        document.getElementById("edit_date").onchange = function() {
            renderEmployeeSelection(this.value, document.getElementById("edit_time_slot").value, job);
        };
        document.getElementById("edit_time_slot").onchange = function() {
            renderEmployeeSelection(document.getElementById("edit_date").value, this.value, job);
            recalculateTotalPrice();
        };
        document.getElementById("edit_service_days").onchange = function() {
            renderEmployeeSelection(document.getElementById("edit_date").value, document.getElementById("edit_time_slot").value, job);
            recalculateTotalPrice();
        };
        document.getElementById("edit_person_count").onchange = function() {
            recalculateTotalPrice();
        };
        document.getElementById("edit_subcategory_id").onchange = function() {
            recalculateTotalPrice();
        };
        
        document.getElementById("jobDetailsModal").classList.add("active");
    } catch(err) {
        console.error('Modal açma hatası:', err);
        alert('Modal açılamadı: ' + err.message);
    }
}

// Event delegation: tüm job-block tıklamalarını yakala
document.addEventListener('click', function(e) {
    var block = e.target.closest('.job-block');
    if (!block) return;
    var jobId = parseInt(block.getAttribute('data-job-id'));
    if (!jobId) return;
    var job = weekJobs.find(function(j) { return parseInt(j.id) === jobId; });
    if (job) {
        openJobDetailsModal(job);
    } else {
        console.error('Job bulunamadı, id:', jobId);
    }
});

function isOverlapSlots(slot1, slot2) {
    if (slot1 === slot2) return true;
    if (slot1 === '08-17' || slot2 === '08-17') return true;
    return false;
}

function closeJobDetailsModal() {
    document.getElementById("jobDetailsModal").classList.remove("active");
    document.getElementById("assignErrorAlert").style.display = "none";
}

// Atama formu submit
document.getElementById("assignmentForm").onsubmit = function(e) {
    e.preventDefault();
    const saveBtn = document.getElementById("saveAssignmentBtn");
    const errorAlert = document.getElementById("assignErrorAlert");
    
    saveBtn.disabled = true;
    saveBtn.innerText = "Kaydediliyor...";
    errorAlert.style.display = "none";
    
    const formData = new FormData(this);
    
    fetch('../ajax/assign_employee.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerText = "Atamayı Kaydet";
        
        if (data.success) {
            closeJobDetailsModal();
            location.reload(); // Takvimi güncelle
        } else {
            errorAlert.innerText = data.message;
            errorAlert.style.display = "block";
        }
    })
    .catch(err => {
        saveBtn.disabled = false;
        saveBtn.innerText = "Atamayı Kaydet";
        errorAlert.innerText = "İletişim hatası oluştu.";
        errorAlert.style.display = "block";
        console.error(err);
    });
};

// HTML5 Drag and Drop Handlers
document.addEventListener('dragstart', function(e) {
    var target = e.target.closest('.draggable-job');
    if (!target) return;
    
    target.classList.add('dragging');
    
    // Store values
    e.dataTransfer.setData('text/plain', target.getAttribute('data-job-id'));
    e.dataTransfer.setData('customer-name', target.getAttribute('data-customer-name'));
    e.dataTransfer.setData('time-slot', target.getAttribute('data-time-slot'));
    e.dataTransfer.setData('booking-id', target.getAttribute('data-booking-id'));
    e.dataTransfer.setData('service-days', target.getAttribute('data-service-days'));
    e.dataTransfer.setData('package-id', target.getAttribute('data-package-id'));
    e.dataTransfer.setData('duration-weeks', target.getAttribute('data-duration-weeks'));
});

document.addEventListener('dragend', function(e) {
    var target = e.target.closest('.draggable-job');
    if (target) {
        target.classList.remove('dragging');
    }
});

document.querySelectorAll('.scheduler-day-column').forEach(function(cell) {
    if (cell.getAttribute('data-is-off') === '1') return;
    
    cell.addEventListener('dragover', function(e) {
        e.preventDefault();
        cell.classList.add('drag-hover');
    });
    
    cell.addEventListener('dragleave', function(e) {
        cell.classList.remove('drag-hover');
    });
    
    cell.addEventListener('drop', function(e) {
        e.preventDefault();
        cell.classList.remove('drag-hover');
        
        const jobId = e.dataTransfer.getData('text/plain');
        const customerName = e.dataTransfer.getData('customer-name');
        const originalTimeSlot = e.dataTransfer.getData('time-slot');
        const bookingId = parseInt(e.dataTransfer.getData('booking-id'));
        const serviceDays = parseInt(e.dataTransfer.getData('service-days')) || 1;
        const packageId = e.dataTransfer.getData('package-id');
        const durationWeeks = parseInt(e.dataTransfer.getData('duration-weeks')) || 1;
        
        const targetDate = cell.getAttribute('data-date');
        const targetEmployeeId = cell.getAttribute('data-employee-id');
        const targetEmployeeName = cell.getAttribute('data-employee-name') || 'Atanmamış İşler';
        
        if (!jobId || !targetDate) return;
        
        openDragDropModal(jobId, customerName, originalTimeSlot, bookingId, serviceDays, packageId, durationWeeks, targetDate, targetEmployeeId, targetEmployeeName);
    });
});

function openDragDropModal(jobId, customerName, originalTimeSlot, bookingId, serviceDays, packageId, durationWeeks, targetDate, targetEmployeeId, targetEmployeeName) {
    document.getElementById("drag_schedule_id").value = jobId;
    document.getElementById("drag_target_date").value = targetDate;
    document.getElementById("drag_target_employee_id").value = targetEmployeeId;
    document.getElementById("drag_info_customer").innerText = customerName;
    
    const parts = targetDate.split('-');
    document.getElementById("drag_info_date").innerText = `${parts[2]}.${parts[1]}.${parts[0]}`;
    document.getElementById("drag_info_employee").innerText = targetEmployeeName;
    
    const isWeekly = !!packageId;
    const datesToCheck = getBookingDates(targetDate, serviceDays, isWeekly, durationWeeks);
    
    const slots = ['08-17', '08-12', '13-17'];
    const slotAvailability = {
        '08-17': { available: true, overlapCust: '' },
        '08-12': { available: true, overlapCust: '' },
        '13-17': { available: true, overlapCust: '' }
    };
    
    if (targetEmployeeId !== 'unassigned') {
        const empId = parseInt(targetEmployeeId);
        const empObj = activeEmployees.find(e => parseInt(e.id) === empId);
        
        datesToCheck.forEach(chkDate => {
            const dateObj = new Date(chkDate);
            const daysEng = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const dayEng = daysEng[dateObj.getDay()];
            
            // 1. İzin Günü Kontrolü
            if (empObj) {
                const offDays = empObj.off_days.split(',').map(d => d.trim());
                if (offDays.includes(dayEng)) {
                    slots.forEach(s => {
                        slotAvailability[s].available = false;
                        slotAvailability[s].overlapCust = 'İzin Günü';
                    });
                }
            }
            
            // 2. Çakışma Kontrolü
            weekJobs.forEach(wj => {
                if (wj.date === chkDate && parseInt(wj.booking_id) !== parseInt(bookingId) && wj.status !== 'cancelled') {
                    const isAssigned = wj.employees.some(e => parseInt(e.id) === empId);
                    if (isAssigned) {
                        slots.forEach(s => {
                            if (isOverlapSlots(wj.time_slot, s)) {
                                slotAvailability[s].available = false;
                                slotAvailability[s].overlapCust = wj.customer_name;
                            }
                        });
                    }
                }
            });
        });
    }
    
    slots.forEach(s => {
        const radio = document.getElementById(`drag_slot_${s}`);
        const label = document.getElementById(`drag_slot_label_${s}`);
        const badge = document.getElementById(`drag_badge_${s}`);
        
        const info = slotAvailability[s];
        
        if (info.available) {
            radio.disabled = false;
            label.style.cursor = 'pointer';
            label.style.opacity = '1';
            label.style.borderColor = 'var(--border)';
            badge.style.backgroundColor = '#ecfdf5';
            badge.style.color = '#0d8058';
            badge.innerText = 'Müsait';
        } else {
            radio.disabled = true;
            label.style.cursor = 'not-allowed';
            label.style.opacity = '0.6';
            label.style.borderColor = '#fee2e2';
            badge.style.backgroundColor = '#fef2f2';
            badge.style.color = 'var(--danger)';
            badge.innerText = info.overlapCust === 'İzin Günü' ? 'İzinli' : `Dolu (${info.overlapCust})`;
        }
    });
    
    let selected = false;
    if (slotAvailability[originalTimeSlot].available) {
        document.getElementById(`drag_slot_${originalTimeSlot}`).checked = true;
        selected = true;
    } else {
        for (let s of slots) {
            if (slotAvailability[s].available) {
                document.getElementById(`drag_slot_${s}`).checked = true;
                selected = true;
                break;
            }
        }
    }
    if (!selected) {
        slots.forEach(s => {
            document.getElementById(`drag_slot_${s}`).checked = false;
        });
    }
    
    document.getElementById("dragDropErrorAlert").style.display = "none";
    document.getElementById("dragDropModal").classList.add("active");
}

function closeDragDropModal() {
    document.getElementById("dragDropModal").classList.remove("active");
    document.getElementById("dragDropErrorAlert").style.display = "none";
}

function submitDragDrop(e) {
    e.preventDefault();
    const confirmBtn = document.getElementById("confirmDragDropBtn");
    const errorAlert = document.getElementById("dragDropErrorAlert");
    
    confirmBtn.disabled = true;
    confirmBtn.innerText = "Taşınıyor...";
    errorAlert.style.display = "none";
    
    const formData = new FormData(document.getElementById("dragDropForm"));
    
    fetch('../ajax/update_schedule_drag.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        confirmBtn.disabled = false;
        confirmBtn.innerText = "Taşımayı Onayla";
        
        if (data.success) {
            closeDragDropModal();
            location.reload();
        } else {
            errorAlert.innerText = data.message;
            errorAlert.style.display = "block";
        }
    })
    .catch(err => {
        confirmBtn.disabled = false;
        confirmBtn.innerText = "Taşımayı Onayla";
        errorAlert.innerText = "İletişim hatası oluştu.";
        errorAlert.style.display = "block";
        console.error(err);
    });
}

// Telefon formatlayıcıyı yükle
window.addEventListener("DOMContentLoaded", () => {
    const editPhone = document.getElementById("edit_customer_phone");
    if (editPhone) {
        editPhone.placeholder = "555 555 55 55";
        editPhone.maxLength = 12;
        editPhone.addEventListener("input", () => {
            let value = editPhone.value.replace(/\D/g, "");
            if (value.startsWith("0")) value = value.substring(1);
            if (value.length > 10) value = value.substring(0, 10);
            let formatted = "";
            if (value.length > 0) formatted += value.substring(0, 3);
            if (value.length > 3) formatted += " " + value.substring(3, 6);
            if (value.length > 6) formatted += " " + value.substring(6, 8);
            if (value.length > 8) formatted += " " + value.substring(8, 10);
            editPhone.value = formatted;
        });
        editPhone.addEventListener("keydown", (e) => {
            const allowedKeys = ["Backspace", "Delete", "Tab", "ArrowLeft", "ArrowRight", "Enter", "Control", "a", "c", "v", "x"];
            if (allowedKeys.includes(e.key) || (e.ctrlKey && ["a", "c", "v", "x"].includes(e.key.toLowerCase()))) return;
            if (!/\d/.test(e.key)) e.preventDefault();
        });
    }
});
</script>

<?php 
// Footer sonu
?>

<?php require_once __DIR__ . '/footer.php'; ?>
