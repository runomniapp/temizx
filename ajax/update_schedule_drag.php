<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/Booking.php';
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
if (!$auth->check()) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']);
    exit;
}

$scheduleId = (int)($_POST['schedule_id'] ?? 0);
$targetDate = trim($_POST['target_date'] ?? '');
$targetTimeSlot = trim($_POST['target_time_slot'] ?? '');
$targetEmployeeId = $_POST['target_employee_id'] ?? ''; // 'unassigned' or integer

if (!$scheduleId || !$targetDate || !$targetTimeSlot) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz parametreler.']);
    exit;
}

try {
    $bookingModel = new Booking();
    
    // Fetch original schedule row to find the booking ID
    $stmt = $pdo->prepare("SELECT booking_id FROM booking_schedule WHERE id = ?");
    $stmt->execute([$scheduleId]);
    $bookingId = $stmt->fetchColumn();
    
    if (!$bookingId) {
        echo json_encode(['success' => false, 'message' => 'Takvim kaydı bulunamadı.']);
        exit;
    }
    
    // Fetch original booking
    $booking = $bookingModel->getById($bookingId);
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Rezervasyon bulunamadı.']);
        exit;
    }
    
    // Prepare data
    $data = [
        'customer_name' => $booking['customer_name'],
        'customer_phone' => $booking['customer_phone'],
        'customer_address' => $booking['customer_address'],
        'person_count' => (int)$booking['person_count'],
        'service_days' => (int)$booking['service_days'],
        'total_price' => (float)$booking['total_price'],
        'date' => $targetDate,
        'time_slot' => $targetTimeSlot,
        'category_id' => (int)$booking['category_id'],
        'subcategory_id' => (int)$booking['subcategory_id']
    ];
    
    $employeeIds = [];
    if ($targetEmployeeId !== 'unassigned' && $targetEmployeeId !== '') {
        $employeeIds[] = (int)$targetEmployeeId;
    }
    
    $result = $bookingModel->updateBookingDetailsFromSchedule($scheduleId, $data, $employeeIds);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'İş başarıyla taşındı!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Güncelleme yapılırken bir hata oluştu.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
