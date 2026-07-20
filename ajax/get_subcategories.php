<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../classes/Category.php';

$categoryId = (int)($_GET['cat_id'] ?? 0);

if (!$categoryId) {
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
    $categoryModel = new Category();
    $subcategories = $categoryModel->getSubcategories($categoryId, false); // Get all (active/inactive)
    
    echo json_encode([
        'success' => true,
        'subcategories' => $subcategories
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Veriler çekilirken hata oluştu: ' . $e->getMessage()
    ]);
}
