<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;

if ($isLocalhost) {
    define('DB_HOST', '127.0.0.1');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'olifa_db');
    define('DB_PORT', '3306');
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'run379app_volkanbagci');
    define('DB_PASS', '3S2tevs0.+');
    define('DB_NAME', 'run379app_temizx');
    define('DB_PORT', '3306');
}

try {
    // Connect directly with dbname for maximum query performance (bypassing extra queries)
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Graceful fallback to create DB if it does not exist (e.g. fresh installation)
    if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");
        } catch (PDOException $ex) {
            die("Veritabanı bağlantı hatası: " . $ex->getMessage());
        }
    } else {
        die("Veritabanı bağlantı hatası: " . $e->getMessage());
    }
}

// Global settings cache
$settings = [];
try {
    // Check if settings table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'settings'");
    if ($tableCheck->rowCount() > 0) {
        $stmt = $pdo->query("SELECT * FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    // Table doesn't exist yet, which is fine before installation
}

// Helper to get system settings
function getSetting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key] : $default;
}

// Helper to update or insert system settings
function updateSetting($key, $value) {
    global $pdo, $settings;
    if (!$pdo) return false;
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $result = $stmt->execute([$key, $value]);
    $settings[$key] = $value;
    return $result;
}

// CSRF Token generation & verification
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
