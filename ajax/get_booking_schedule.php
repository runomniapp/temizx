<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/Booking.php';

$bookingId = (int)($_GET['id'] ?? 0);

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz parametre.']);
    exit;
}

// Yetki kontrolü
require_once __DIR__ . '/../classes/Auth.php';
$auth = new Auth();
if (!$auth->check()) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']);
    exit;
}

try {
    $bookingModel = new Booking();
    $booking = $bookingModel->getById($bookingId);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Rezervasyon bulunamadı.']);
        exit;
    }
    
    $schedule = $bookingModel->getScheduleWithEmployees($bookingId);
    
    echo json_encode([
        'success' => true,
        'booking' => $booking,
        'schedule' => $schedule
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Veriler çekilirken hata oluştu: ' . $e->getMessage()
    ]);
}
