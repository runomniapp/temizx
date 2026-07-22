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
    echo json_encode(['success' => false, 'message' => 'CSRF güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.']);
    exit;
}

$categoryId = (int)($_POST['category_id'] ?? 0);
$subcategoryId = isset($_POST['subcategory_id']) && $_POST['subcategory_id'] !== '' ? (int)$_POST['subcategory_id'] : null;
$packageId = isset($_POST['package_id']) && $_POST['package_id'] !== '' ? (int)$_POST['package_id'] : null;

$customerName = trim($_POST['customer_name'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');
$customerAddress = trim($_POST['customer_address'] ?? '');
$customerLocation = trim($_POST['customer_location'] ?? '');
$customerEmail = trim($_POST['customer_email'] ?? '');
$bookingDate = $_POST['booking_date'] ?? '';
$bookingTimeSlot = $_POST['booking_time_slot'] ?? '';
$personCount = (int)($_POST['person_count'] ?? 1);

// Temel doğrulama
if (!$categoryId || !$customerName || !$customerPhone || !$customerAddress || !$bookingDate || !$bookingTimeSlot) {
    echo json_encode(['success' => false, 'message' => 'Lütfen zorunlu alanları doldurun.']);
    exit;
}

// Telefon formatı temizleme ve doğrulama (örnek: 0555 123 4567)
if (strlen(preg_replace('/[^0-9]/', '', $customerPhone)) < 10) {
    echo json_encode(['success' => false, 'message' => 'Lütfen geçerli bir telefon numarası girin.']);
    exit;
}

try {
    $bookingModel = new Booking();
    
    // Verileri paketle
    $bookingData = [
        'category_id' => $categoryId,
        'subcategory_id' => $subcategoryId,
        'package_id' => $packageId,
        'customer_name' => $customerName,
        'customer_phone' => $customerPhone,
        'customer_address' => $customerAddress,
        'customer_location' => $customerLocation,
        'customer_email' => $customerEmail,
        'booking_date' => $bookingDate,
        'booking_time_slot' => $bookingTimeSlot,
        'person_count' => $personCount,
        'status' => 'pending' // Varsayılan olarak yönetici onayına düşer (Teklif olarak)
    ];
    
    $bookingId = $bookingModel->createBooking($bookingData);

    // Yöneticiye WhatsApp yeni teklif bildirimi gönder
    require_once __DIR__ . '/../classes/WhatsAppService.php';
    WhatsAppService::sendNewBookingAdminNotification($bookingId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Rezervasyon talebiniz başarıyla alındı! En kısa sürede sizinle iletişime geçeceğiz.',
        'booking_id' => $bookingId
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Rezervasyon oluşturulurken bir hata oluştu: ' . $e->getMessage()
    ]);
}
