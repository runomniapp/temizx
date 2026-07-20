<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/Booking.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek yöntemi.']);
    exit;
}

// CSRF doğrulama
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'CSRF güvenlik doğrulaması başarısız.']);
    exit;
}

$scheduleId = (int)($_POST['schedule_id'] ?? 0);
$employeeIds = $_POST['employee_ids'] ?? []; // Array of ids

if (!$scheduleId) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz takvim kaydı.']);
    exit;
}

// Check authorization
require_once __DIR__ . '/../classes/Auth.php';
$auth = new Auth();
if (!$auth->check()) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']);
    exit;
}

try {
    $bookingModel = new Booking();
    
    // Ensure ids are integers
    $employeeIds = array_map('intval', $employeeIds);
    
    $data = [
        'customer_name' => trim($_POST['customer_name'] ?? ''),
        'customer_phone' => trim($_POST['customer_phone'] ?? ''),
        'customer_address' => trim($_POST['customer_address'] ?? ''),
        'person_count' => (int)($_POST['person_count'] ?? 1),
        'service_days' => isset($_POST['service_days']) ? (int)$_POST['service_days'] : 1,
        'total_price' => isset($_POST['total_price']) ? (float)$_POST['total_price'] : null,
        'date' => trim($_POST['date'] ?? ''),
        'time_slot' => trim($_POST['time_slot'] ?? ''),
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'subcategory_id' => (int)($_POST['subcategory_id'] ?? 0)
    ];
    
    if (empty($data['customer_name']) || empty($data['customer_phone']) || empty($data['date']) || empty($data['time_slot']) || empty($data['category_id'])) {
        echo json_encode(['success' => false, 'message' => 'Lütfen zorunlu alanları doldurun.']);
        exit;
    }
    
    $result = $bookingModel->updateBookingDetailsFromSchedule($scheduleId, $data, $employeeIds);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Rezervasyon bilgileri ve personel atamaları başarıyla güncellendi!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Bilgiler güncellenirken bir sorun oluştu.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu: ' . $e->getMessage()
    ]);
}
