<?php
class Database {
    private static $instance = null;
    
    public static function getConnection() {
        if (self::$instance === null) {
            require_once __DIR__ . '/../config/db.php';
            global $pdo;
            self::$instance = $pdo;
        }
        return self::$instance;
    }
}
