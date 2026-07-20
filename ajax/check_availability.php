<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Category.php';

$date = $_GET['date'] ?? '';
$categoryId = (int)($_GET['category_id'] ?? 0);
$personCount = (int)($_GET['person_count'] ?? 1);

if (!$date || !$categoryId) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz parametreler.']);
    exit;
}
$categoryModel = new Category();
$cat = $categoryModel->getById($categoryId);
$type = $cat ? $cat['service_group'] : 'general';

$employeeModel = new Employee();
$slots = ['08-17', '08-12', '13-17'];
$availability = [];

foreach ($slots as $slot) {
    $availableEmps = $employeeModel->getAvailableEmployees($date, $slot, $type);
    $availableCount = count($availableEmps);
    
    // Eğer talep edilen kişi sayısından daha az personel varsa o slot doludur
    $availability[$slot] = [
        'available' => $availableCount >= $personCount,
        'count' => $availableCount
    ];
}

echo json_encode([
    'success' => true,
    'date' => $date,
    'availability' => $availability
]);
