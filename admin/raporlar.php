<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../classes/Employee.php';

$db = Database::getConnection();
$employeeModel = new Employee();

// Fetch all active employees
$allEmployees = $employeeModel->getAll(true);

// Get query parameters or set defaults
$periodType = $_GET['period_type'] ?? 'weekly';
$selectedWeek = $_GET['week'] ?? date('Y') . '-W' . date('W');
$selectedMonth = $_GET['month'] ?? date('Y-m');

$selectedEmpIds = isset($_GET['employee_ids']) ? array_map('intval', $_GET['employee_ids']) : [];

// Determine start and end date
$startDate = '';
$endDate = '';

if ($periodType === 'weekly') {
    if (preg_match('/^(\d{4})-W(\d{2})$/', $selectedWeek, $matches)) {
        $year = (int)$matches[1];
        $week = (int)$matches[2];
        
        $dto = new DateTime();
        $dto->setISODate($year, $week);
        $startDate = $dto->format('Y-m-d'); // Monday
        $dto->modify('+6 days');
        $endDate = $dto->format('Y-m-d'); // Sunday
    } else {
        // Fallback to current week
        $dto = new DateTime();
        $dto->setISODate((int)date('Y'), (int)date('W'));
        $startDate = $dto->format('Y-m-d');
        $dto->modify('+6 days');
        $endDate = $dto->format('Y-m-d');
    }
} else {
    // Monthly
    if (preg_match('/^(\d{4})-(\d{2})$/', $selectedMonth, $matches)) {
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $endDate = date('Y-m-t', strtotime($startDate));
    } else {
        // Fallback to current month
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
    }
}

// Get selected employees
if (!empty($selectedEmpIds)) {
    $inClause = implode(',', array_fill(0, count($selectedEmpIds), '?'));
    $stmtEmp = $db->prepare("SELECT * FROM employees WHERE id IN ($inClause) AND status = 'active' ORDER BY name ASC");
    $stmtEmp->execute($selectedEmpIds);
    $employees = $stmtEmp->fetchAll();
} else {
    $employees = $allEmployees;
    $selectedEmpIds = array_column($employees, 'id');
}

// Generate all dates in the range
$datesArray = [];
$currentDate = $startDate;
while ($currentDate <= $endDate) {
    $datesArray[] = $currentDate;
    $currentDate = date('Y-m-d', strtotime('+1 day', strtotime($currentDate)));
}

// Query schedule data for selected employees in the date range
$scheduleData = [];
if (!empty($selectedEmpIds) && $startDate && $endDate) {
    $inClause = implode(',', array_fill(0, count($selectedEmpIds), '?'));
    $sql = "
        SELECT bs.date, bs.time_slot, be.employee_id
        FROM booking_schedule bs
        INNER JOIN booking_employees be ON bs.id = be.booking_schedule_id
        WHERE bs.date BETWEEN ? AND ? 
          AND be.employee_id IN ($inClause)
          AND bs.status != 'cancelled'
    ";
    $params = array_merge([$startDate, $endDate], $selectedEmpIds);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by employee and date
    foreach ($rows as $row) {
        $empId = $row['employee_id'];
        $date = $row['date'];
        $slot = $row['time_slot'];
        
        if (!isset($scheduleData[$empId])) {
            $scheduleData[$empId] = [];
        }
        if (!isset($scheduleData[$empId][$date])) {
            $scheduleData[$empId][$date] = [];
        }
        $scheduleData[$empId][$date][] = $slot;
    }
}

// Helper function to process cell text & weight
function getDayShiftInfo($slots) {
    if (empty($slots)) {
        return ['text' => '-', 'weight' => 0.0, 'class' => 'empty'];
    }
    
    // De-duplicate slots
    $slots = array_unique($slots);
    
    if (in_array('08-17', $slots)) {
        return ['text' => 'T', 'weight' => 1.0, 'class' => 'full-day'];
    }
    
    if (in_array('08-12', $slots) && in_array('13-17', $slots)) {
        return ['text' => '2Y', 'weight' => 1.0, 'class' => 'two-half-days'];
    }
    
    if (in_array('08-12', $slots) || in_array('13-17', $slots)) {
        return ['text' => 'Y', 'weight' => 0.5, 'class' => 'half-day'];
    }
    
    return ['text' => '-', 'weight' => 0.0, 'class' => 'empty'];
}

// Helper to format Turkish dates
function formatTurkishDate($dateStr) {
    return date('d.m.Y', strtotime($dateStr));
}

function getTurkishDayNameShort($dateStr) {
    $dayOfWeek = date('N', strtotime($dateStr));
    $days = [
        1 => 'Pzt',
        2 => 'Sal',
        3 => 'Çar',
        4 => 'Per',
        5 => 'Cum',
        6 => 'Cmt',
        7 => 'Paz'
    ];
    return $days[$dayOfWeek] ?? '';
}

// Settings fetch values
$companyPhone = getSetting('phone', '-');
$companyEmail = getSetting('email', '-');
$companyAddress = getSetting('address', '-');
$ownerName = getSetting('owner_name', $compName);
$approverName = getSetting('approver_name', 'Onay Personeli');
$rawLogoPath = getSetting('logo_path', 'assets/img/olifa_logo.png');

// Check if logo exists, prep for base64 or absolute path to avoid PDF rendering issues
$logoSrc = '../' . $rawLogoPath;
?>

<!-- html2pdf.js library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
/* Page Layout Styles */
.reports-container {
    max-width: 1300px;
    margin: 0 auto;
    padding-bottom: 50px;
}

/* Glassmorphic Filter Card */
.filter-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    border: 1px solid var(--border);
    border-radius: var(--radius-card);
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: var(--shadow-md);
}

.filter-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 25px;
}

@media (max-width: 992px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
}

.filter-left {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.filter-right {
    display: flex;
    flex-direction: column;
    gap: 15px;
    border-left: 1px solid var(--border);
    padding-left: 25px;
}

@media (max-width: 992px) {
    .filter-right {
        border-left: none;
        padding-left: 0;
        border-top: 1px solid var(--border);
        padding-top: 20px;
    }
}

/* Radio Group Segmented Control */
.segmented-control {
    display: flex;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 12px;
    width: 100%;
}

.segmented-control input[type="radio"] {
    display: none;
}

.segmented-control label {
    flex: 1;
    text-align: center;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    color: var(--text-muted);
    transition: var(--transition);
}

.segmented-control input[type="radio"]:checked + label {
    background: var(--card-bg);
    color: var(--primary);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

/* Employee Checkbox Grid */
.employee-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
    max-height: 150px;
    overflow-y: auto;
    padding: 4px;
}

.employee-pill-checkbox {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f8fafc;
    border: 1px solid var(--border);
    padding: 10px 14px;
    border-radius: 50px;
    cursor: pointer;
    transition: var(--transition);
}

.employee-pill-checkbox:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.employee-pill-checkbox input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
    cursor: pointer;
}

.employee-pill-checkbox span {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-main);
    user-select: none;
}

/* Checkbox selected style */
.employee-pill-checkbox.selected {
    background: var(--primary-light);
    border-color: var(--primary);
}

.employee-pill-checkbox.selected span {
    color: var(--primary);
}

/* Preview Section Container */
.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

/* Paper A4 Sheet Preview Styling - Always Landscape for a cleaner look */
.report-sheet-wrapper {
    overflow-x: auto;
    background: #e2e8f0;
    padding: 40px 20px;
    border-radius: var(--radius-card);
    box-shadow: inset 0 2px 8px rgba(0,0,0,0.05);
    display: flex;
    justify-content: center;
}

.report-sheet {
    background: #ffffff;
    color: #000000;
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
    position: relative;
    width: 297mm;
    min-height: 210mm;
    padding: 15mm;
    border: 1px solid #cbd5e1;
}

/* Visual margin guide (dashed borders) inside the preview, hidden in print */
.margin-guide {
    position: absolute;
    top: 15mm;
    left: 15mm;
    right: 15mm;
    bottom: 15mm;
    border: 1px dashed rgba(37, 99, 235, 0.2);
    pointer-events: none;
}

/* Report Table Styling */
.report-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 25px;
    font-size: 0.72rem;
}

.report-table th, .report-table td {
    border: 1px solid #94a3b8;
    text-align: center;
    padding: 6px 3px;
}

.report-table th {
    background-color: #f1f5f9;
    font-weight: 700;
    color: #0f172a;
}

.report-table td.emp-name {
    text-align: left;
    padding-left: 10px;
    font-weight: 600;
    color: #0f172a;
    white-space: nowrap;
}

/* Badges for Shifts in Table */
.shift-cell {
    font-weight: 800;
    width: 20px;
    height: 20px;
    padding: 0 !important;
}

.shift-val {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.shift-val.full-day {
    background-color: #d1fae5;
    color: #065f46;
}

.shift-val.two-half-days {
    background-color: #fee2e2;
    color: #991b1b;
}

.shift-val.half-day {
    background-color: #ffedd5;
    color: #9a3412;
}

.shift-val.empty {
    color: #cbd5e1;
    font-weight: 400;
}

.total-col {
    background-color: #f8fafc;
    font-weight: 800;
    color: var(--primary);
    white-space: nowrap;
}

/* Report Footer / Signature Section */
.report-signatures {
    margin-top: 40px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 100px;
}

.sig-box {
    border-top: 1px solid #94a3b8;
    padding-top: 8px;
    text-align: center;
    font-size: 0.8rem;
    color: #334155;
}

/* Report Legend Styling */
.report-legend {
    margin-top: 30px;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    font-size: 0.75rem;
    color: #475569;
}

.legend-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 6px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 4px;
    font-weight: 800;
    font-size: 0.7rem;
}

/* Native Print Layout configuration */
@media print {
    /* Hide layout wrappers */
    .admin-sidebar,
    .admin-header,
    .wave-bg-container,
    .page-header,
    .filter-card,
    .preview-header,
    .no-print {
        display: none !important;
    }
    
    body {
        background: white !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    
    .admin-main {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }
    
    .report-sheet-wrapper {
        background: transparent !important;
        padding: 0 !important;
        box-shadow: none !important;
        overflow: visible !important;
        display: block !important;
    }
    
    .report-sheet {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        min-height: auto !important;
    }
    
    @page {
        size: landscape;
        margin: 1.5cm;
    }
}
</style>

<div class="reports-container">
    <!-- Header -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 5px;">Puantaj & Çalışma Raporları</h2>
            <p style="color: var(--text-muted);">Çalışanların haftalık veya aylık puantaj cetvellerini görüntüleyin, önizleyin ve PDF olarak indirin.</p>
        </div>
    </div>

    <!-- Filter Form & Setup Card -->
    <div class="filter-card">
        <form method="GET" action="raporlar.php" id="filterForm">
            <div class="filter-grid">
                <div class="filter-left">
                    <!-- Period Type Selection -->
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700; margin-bottom: 8px; font-size: 0.85rem; display: block;">Rapor Dönemi Türü</label>
                        <div class="segmented-control">
                            <input type="radio" name="period_type" id="type_weekly" value="weekly" <?php echo $periodType === 'weekly' ? 'checked' : ''; ?> onchange="togglePeriodInputs()">
                            <label for="type_weekly">Haftalık</label>
                            
                            <input type="radio" name="period_type" id="type_monthly" value="monthly" <?php echo $periodType === 'monthly' ? 'checked' : ''; ?> onchange="togglePeriodInputs()">
                            <label for="type_monthly">Aylık</label>
                        </div>
                    </div>

                    <!-- Weekly Date Picker -->
                    <div class="form-group" id="weekly_input_wrapper" style="display: <?php echo $periodType === 'weekly' ? 'block' : 'none'; ?>;">
                        <label class="form-label" for="week_picker" style="font-weight: 700; margin-bottom: 6px; font-size: 0.85rem; display: block;">Hafta Seçin</label>
                        <input type="week" name="week" id="week_picker" class="form-control" value="<?php echo $selectedWeek; ?>" style="padding: 10px 16px; border-radius: 12px; font-weight: 600; font-size: 0.9rem;">
                    </div>

                    <!-- Monthly Date Picker -->
                    <div class="form-group" id="monthly_input_wrapper" style="display: <?php echo $periodType === 'monthly' ? 'block' : 'none'; ?>;">
                        <label class="form-label" for="month_picker" style="font-weight: 700; margin-bottom: 6px; font-size: 0.85rem; display: block;">Ay Seçin</label>
                        <input type="month" name="month" id="month_picker" class="form-control" value="<?php echo $selectedMonth; ?>" style="padding: 10px 16px; border-radius: 12px; font-weight: 600; font-size: 0.9rem;">
                    </div>
                    
                    <!-- Dynamic Signatories Fields -->
                    <div style="border-top: 1px solid var(--border); padding-top: 15px; display: flex; flex-direction: column; gap: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-weight: 700; font-size: 0.85rem; margin-bottom: 4px; display: block;">Raporu Hazırlayan</label>
                            <input type="text" id="input_owner_name" class="form-control" style="font-size: 0.85rem; padding: 8px 14px; border-radius: 10px;" value="<?php echo e($ownerName); ?>" placeholder="İşletme Sahibi Adı">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" style="font-weight: 700; font-size: 0.85rem; margin-bottom: 4px; display: block;">Onaylayan Yetkili</label>
                            <input type="text" id="input_approver_name" class="form-control" style="font-size: 0.85rem; padding: 8px 14px; border-radius: 10px;" value="<?php echo e($approverName); ?>" placeholder="Onaylayan Adı">
                        </div>
                    </div>
                </div>

                <div class="filter-right">
                    <!-- Employees Checklist -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <label class="form-label" style="font-weight: 700; font-size: 0.85rem; margin-bottom: 0;">Raporlanacak Çalışanlar</label>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600; color: var(--primary);">
                                <input type="checkbox" id="selectAllEmployees" onchange="toggleSelectAllEmployees(this)" style="accent-color: var(--primary);"> Tümünü Seç
                            </label>
                        </div>
                        <div class="employee-checkbox-grid">
                            <?php foreach ($allEmployees as $emp): ?>
                                <?php $checked = in_array($emp['id'], $selectedEmpIds) ? 'checked' : ''; ?>
                                <label class="employee-pill-checkbox <?php echo $checked ? 'selected' : ''; ?>" id="emp_label_<?php echo $emp['id']; ?>">
                                    <input type="checkbox" name="employee_ids[]" value="<?php echo $emp['id']; ?>" <?php echo $checked; ?> onchange="updatePillClass(this, <?php echo $emp['id']; ?>)">
                                    <span><?php echo e($emp['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: auto; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary" style="padding: 10px 24px; border-radius: 50px; font-weight: 700; font-size: 0.9rem;">
                            <i class="fa-solid fa-sync" style="margin-right: 8px;"></i> Raporu ve Önizlemeyi Göster
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Preview Header and Action Buttons -->
    <div class="preview-header">
        <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-eye" style="color: var(--primary);"></i> Rapor Önizlemesi
        </h3>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn btn-outline" style="padding: 8px 20px; border-radius: 50px; font-weight: 700; font-size: 0.85rem;">
                <i class="fa-solid fa-print" style="margin-right: 8px;"></i> Yazdır
            </button>
            <button onclick="downloadPDF()" class="btn btn-primary" style="padding: 8px 20px; border-radius: 50px; font-weight: 700; font-size: 0.85rem; background-color: #2563eb; border-color: #2563eb;">
                <i class="fa-solid fa-file-pdf" style="margin-right: 8px;"></i> PDF Olarak İndir
            </button>
        </div>
    </div>

    <!-- Sheet Wrapper -->
    <div class="report-sheet-wrapper">
        <div class="print-area-target">
            <div class="report-sheet">
                
                <!-- Margins Visual Guide (Only in web preview, not printed/not in PDF) -->
                <div class="margin-guide no-print"></div>
                
                <!-- Report Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #1e293b; padding-bottom: 15px;">
                    <div style="display: flex; align-items: center; gap: 18px;">
                        <?php if (file_exists('../' . $rawLogoPath)): ?>
                            <img src="../<?php echo e($rawLogoPath); ?>" alt="Logo" style="max-height: 52px; max-width: 140px; object-fit: contain;">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; background-color: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 800; border-radius: 12px; font-size: 1.2rem;">O</div>
                        <?php endif; ?>
                        <div>
                            <h1 style="font-size: 1.3rem; font-weight: 800; margin: 0; color: #0f172a; text-transform: uppercase;">
                                <?php echo $periodType === 'weekly' ? 'HAFTALIK' : 'AYLIK'; ?> ÇALIŞAN PUANTAJ RAPORU
                            </h1>
                            <p style="font-size: 0.8rem; color: #475569; margin: 3px 0 0 0; font-weight: 500;">
                                Dönem: <strong><?php echo formatTurkishDate($startDate); ?></strong> ile <strong><?php echo formatTurkishDate($endDate); ?></strong> arası
                            </p>
                        </div>
                    </div>
                    <div style="text-align: right; font-size: 0.78rem; color: #475569; line-height: 1.45;">
                        <h3 style="font-size: 1.05rem; font-weight: 800; margin: 0 0 4px 0; color: #2563eb;"><?php echo e($compName); ?></h3>
                        <div>Tel: <?php echo e($companyPhone); ?></div>
                        <div>E-posta: <?php echo e($companyEmail); ?></div>
                        <div style="max-width: 250px; font-size: 0.72rem; color: #64748b; margin-top: 2px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;" title="<?php echo e($companyAddress); ?>"><?php echo e($companyAddress); ?></div>
                    </div>
                </div>
                
                <!-- Info Grid -->
                <div style="margin-top: 15px; display: flex; justify-content: space-between; font-size: 0.78rem; color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                    <div>
                        Rapor Oluşturma Tarihi: <strong><?php echo date('d.m.Y H:i'); ?></strong>
                    </div>
                    <div>
                        Toplam Raporlanan Çalışan: <strong><?php echo count($employees); ?></strong>
                    </div>
                </div>

                <!-- Main Puantaj Table -->
                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding-left: 10px; width: 180px;">Çalışan Adı Soyadı</th>
                            <?php foreach ($datesArray as $dt): ?>
                                <th>
                                    <?php if ($periodType === 'weekly'): ?>
                                        <div style="font-weight: 800; font-size: 0.75rem;"><?php echo getTurkishDayNameShort($dt); ?></div>
                                        <div style="font-size: 0.65rem; font-weight: 500; opacity: 0.8;"><?php echo date('d', strtotime($dt)); ?></div>
                                    <?php else: ?>
                                        <!-- Monthly just show day numbers to save space -->
                                        <div style="font-weight: 800; font-size: 0.7rem;"><?php echo date('d', strtotime($dt)); ?></div>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                            <th style="width: 100px; font-size: 0.75rem;">Toplam Gün</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="<?php echo count($datesArray) + 2; ?>" style="padding: 30px; color: #64748b; font-size: 0.85rem;">Raporlanacak çalışan bulunmamaktadır.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $emp): ?>
                                <?php 
                                $empId = $emp['id'];
                                $totalWorkedDays = 0.0;
                                ?>
                                <tr>
                                    <td class="emp-name"><?php echo e($emp['name']); ?></td>
                                    <?php foreach ($datesArray as $dt): ?>
                                        <?php 
                                        $slots = isset($scheduleData[$empId][$dt]) ? $scheduleData[$empId][$dt] : [];
                                        $shiftInfo = getDayShiftInfo($slots);
                                        $totalWorkedDays += $shiftInfo['weight'];
                                        ?>
                                        <td class="shift-cell">
                                            <span class="shift-val <?php echo $shiftInfo['class']; ?>">
                                                <?php echo $shiftInfo['text']; ?>
                                            </span>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="total-col"><?php echo number_format($totalWorkedDays, 1, ',', '.'); ?> Gün</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Legend / Explanations -->
                <div class="report-legend">
                    <strong style="color: #0f172a; font-size: 0.8rem;"><i class="fa-solid fa-circle-info"></i> Rapor Lejantı ve Hesaplama Kuralları</strong>
                    <div class="legend-grid">
                        <div class="legend-item">
                            <span class="legend-badge full-day" style="background-color: #d1fae5; color: #065f46;">T</span>
                            <span><strong>Tam Gün:</strong> 08:00 - 17:00 arası çalışma (1.0 Gün)</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-badge half-day" style="background-color: #ffedd5; color: #9a3412;">Y</span>
                            <span><strong>Yarım Gün:</strong> Sabah (08:00-12:00) veya Öğleden Sonra (13:00-17:00) çalışma (0.5 Gün)</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-badge two-half-days" style="background-color: #fee2e2; color: #991b1b;">2Y</span>
                            <span><strong>İki Yarım Gün:</strong> Aynı günde iki farklı yarım gün iş ataması (1.0 Gün)</span>
                        </div>
                    </div>
                    <div style="margin-top: 8px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 0.7rem; color: #64748b;">
                        * <i>Hesaplama:</i> Toplam Gün = T sayısı + (Y sayısı * 0.5) + (2Y sayısı * 1.0).
                    </div>
                </div>

                <!-- Signature Section -->
                <div class="report-signatures">
                    <div class="sig-box">
                        <strong>Raporu Hazırlayan</strong>
                        <div style="margin-top: 45px; font-weight: 700; color: #0f172a;" id="preview_owner_name"><?php echo e($ownerName); ?></div>
                        <div style="font-size: 0.72rem; color: #64748b; margin-top: 3px;">İşletme Sahibi</div>
                    </div>
                    <div class="sig-box">
                        <strong>Onaylayan Yetkili</strong>
                        <div style="margin-top: 45px; font-weight: 700; color: #0f172a;" id="preview_approver_name"><?php echo e($approverName); ?></div>
                        <div style="font-size: 0.72rem; color: #64748b; margin-top: 3px;">İmza / Kaşe</div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function togglePeriodInputs() {
    const isWeekly = document.getElementById('type_weekly').checked;
    const weeklyWrapper = document.getElementById('weekly_input_wrapper');
    const monthlyWrapper = document.getElementById('monthly_input_wrapper');
    
    if (isWeekly) {
        weeklyWrapper.style.display = 'block';
        monthlyWrapper.style.display = 'none';
    } else {
        weeklyWrapper.style.display = 'none';
        monthlyWrapper.style.display = 'block';
    }
}

function updatePillClass(checkbox, empId) {
    const label = document.getElementById('emp_label_' + empId);
    if (checkbox.checked) {
        label.classList.add('selected');
    } else {
        label.classList.remove('selected');
    }
    updateSelectAllCheckboxState();
}

function toggleSelectAllEmployees(selectAllCheckbox) {
    const checkboxes = document.querySelectorAll('.employee-checkbox-grid input[type="checkbox"]');
    checkboxes.forEach(cb => {
        cb.checked = selectAllCheckbox.checked;
        const empId = cb.value;
        const label = document.getElementById('emp_label_' + empId);
        if (selectAllCheckbox.checked) {
            label.classList.add('selected');
        } else {
            label.classList.remove('selected');
        }
    });
}

function updateSelectAllCheckboxState() {
    const selectAll = document.getElementById('selectAllEmployees');
    const checkboxes = document.querySelectorAll('.employee-checkbox-grid input[type="checkbox"]');
    let allChecked = true;
    checkboxes.forEach(cb => {
        if (!cb.checked) allChecked = false;
    });
    selectAll.checked = allChecked;
}

// Live sync signatory names
document.getElementById('input_owner_name').addEventListener('input', function() {
    document.getElementById('preview_owner_name').innerText = this.value;
});
document.getElementById('input_approver_name').addEventListener('input', function() {
    document.getElementById('preview_approver_name').innerText = this.value;
});

// PDF Generation using html2pdf.js
function downloadPDF() {
    const element = document.querySelector('.report-sheet');
    
    // Hide the margin guide and any other non-printable element inside the sheet before capturing
    const marginGuide = element.querySelector('.margin-guide');
    if (marginGuide) marginGuide.style.display = 'none';
    
    const opt = {
        margin:       10,
        filename:     'puantaj_raporu_<?php echo str_replace("-", "_", $periodType === "weekly" ? $selectedWeek : $selectedMonth); ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };
    
    html2pdf().set(opt).from(element).save().then(() => {
        // Restore margin guide visibility after PDF generation completes
        if (marginGuide) marginGuide.style.display = 'block';
    });
}

// Initial updates on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSelectAllCheckboxState();
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
