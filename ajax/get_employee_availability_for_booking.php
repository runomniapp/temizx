<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Category.php';

// Auth check
require_once __DIR__ . '/../classes/Auth.php';
$auth = new Auth();
if (!$auth->check()) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']);
    exit;
}

$bookingId = isset($_GET['booking_id']) && $_GET['booking_id'] !== '' ? (int)$_GET['booking_id'] : 0;
$startDate = $_GET['date'] ?? '';
$timeSlot = $_GET['time_slot'] ?? '';
$serviceDays = (int)($_GET['service_days'] ?? 1);
$categoryId = (int)($_GET['category_id'] ?? 0);

if (!$startDate || !$timeSlot || !$categoryId) {
    echo json_encode(['success' => false, 'message' => 'Eksik parametreler.']);
    exit;
}

try {
    $categoryModel = new Category();
    $cat = $categoryModel->getById($categoryId);
    $type = $cat ? $cat['service_group'] : 'general';

    $employeeModel = new Employee();
    $allEmployees = $employeeModel->getAll(true, $type); // Filtered by service group

    // Calculate all dates of this booking duration
    $dates = [];
    for ($i = 0; $i < $serviceDays; $i++) {
        $dates[] = date('Y-m-d', strtotime("+$i days", strtotime($startDate)));
    }

    $db = Database::getConnection();
    
    // Conflicting schedules: date is in $dates, slot overlaps with $timeSlot, status is not cancelled
    $conflicts = [];
    if (!empty($dates)) {
        $placeholders = implode(',', array_fill(0, count($dates), '?'));
        
        $sql = "
            SELECT be.employee_id, bs.date, bs.time_slot, b.customer_name 
            FROM booking_schedule bs
            INNER JOIN booking_employees be ON bs.id = be.booking_schedule_id
            INNER JOIN bookings b ON bs.booking_id = b.id
            WHERE bs.date IN ($placeholders) 
              AND bs.status != 'cancelled'
        ";
        
        $params = $dates;
        if ($bookingId > 0) {
            $sql .= " AND bs.booking_id != ? ";
            $params[] = $bookingId;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Evaluate availability for each employee
    $result = [];
    foreach ($allEmployees as $emp) {
        $isOff = false;
        $hasOverlap = false;
        $overlapCustomer = '';
        $overlapDate = '';

        foreach ($dates as $chkDate) {
            // Check off day
            $dayName = date('l', strtotime($chkDate)); // English day name (Sunday, Monday...)
            $offDays = array_map('trim', explode(',', $emp['off_days']));
            if (in_array($dayName, $offDays)) {
                $isOff = true;
                $overlapDate = $chkDate;
                break;
            }

            // Check overlap
            foreach ($conflicts as $conflict) {
                if ((int)$conflict['employee_id'] === (int)$emp['id'] && $conflict['date'] === $chkDate) {
                    if (Employee::isOverlap($conflict['time_slot'], $timeSlot)) {
                        $hasOverlap = true;
                        $overlapCustomer = $conflict['customer_name'];
                        $overlapDate = $chkDate;
                        break 2;
                    }
                }
            }
        }

        // Check if currently assigned to this booking
        $isCurrentlyAssigned = false;
        if ($bookingId > 0) {
            $stmtCurrentlyAssigned = $db->prepare("
                SELECT COUNT(*) 
                FROM booking_schedule bs
                INNER JOIN booking_employees be ON bs.id = be.booking_schedule_id
                WHERE bs.booking_id = ? AND be.employee_id = ?
            ");
            $stmtCurrentlyAssigned->execute([$bookingId, $emp['id']]);
            $isCurrentlyAssigned = $stmtCurrentlyAssigned->fetchColumn() > 0;
        }

        $result[] = [
            'id' => (int)$emp['id'],
            'name' => $emp['name'],
            'photo' => $emp['photo'],
            'phone' => $emp['phone'],
            'is_off' => $isOff,
            'has_overlap' => $hasOverlap,
            'overlap_customer' => $overlapCustomer,
            'overlap_date' => $overlapDate,
            'is_assigned' => $isCurrentlyAssigned
        ];
    }

    echo json_encode([
        'success' => true,
        'employees' => $result
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
