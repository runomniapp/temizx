<?php
require_once __DIR__ . '/Database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Admin girişi yap
     */
    public function login($username, $password) {
        // Brute force protection check
        if ($this->isLocked()) {
            return [
                'success' => false,
                'message' => 'Çok fazla başarısız giriş denemesi. Lütfen 10 dakika sonra tekrar deneyin.'
            ];
        }
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? AND status = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Reset failed login attempts on successful login
            unset($_SESSION['failed_logins']);
            unset($_SESSION['lock_time']);
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];
            
            return [
                'success' => true,
                'message' => 'Giriş başarılı.'
            ];
        }
        
        // Log failed attempt
        $this->registerFailedAttempt();
        return [
            'success' => false,
            'message' => 'Hatalı kullanıcı adı veya şifre!'
        ];
    }
    
    /**
     * Oturumu sonlandır
     */
    public function logout() {
        unset($_SESSION['admin_logged_in']);
        unset($_SESSION['admin_user_id']);
        unset($_SESSION['admin_username']);
        unset($_SESSION['admin_role']);
        session_destroy();
        return true;
    }
    
    /**
     * Oturum açık mı kontrol et
     */
    public function check() {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
    
    /**
     * Giriş yapılmış olmasını zorunlu kıl
     */
    public function requireLogin() {
        if (!$this->check()) {
            header("Location: /admin/login.php");
            exit;
        }
    }
    
    /**
     * Kilitlenme durumunu kontrol et
     */
    private function isLocked() {
        if (isset($_SESSION['lock_time']) && time() < $_SESSION['lock_time']) {
            return true;
        }
        // Lock time expired, clear it
        if (isset($_SESSION['lock_time']) && time() >= $_SESSION['lock_time']) {
            unset($_SESSION['lock_time']);
            unset($_SESSION['failed_logins']);
        }
        return false;
    }
    
    /**
     * Başarısız denemeyi kaydet
     */
    private function registerFailedAttempt() {
        if (!isset($_SESSION['failed_logins'])) {
            $_SESSION['failed_logins'] = 0;
        }
        
        $_SESSION['failed_logins']++;
        
        if ($_SESSION['failed_logins'] >= 5) {
            $_SESSION['lock_time'] = time() + 600; // 10 minutes lock
        }
    }
}
