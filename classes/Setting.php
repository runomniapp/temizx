<?php
require_once __DIR__ . '/Database.php';

class Setting {
    private $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    /**
     * Tüm ayarları anahtar-değer çifti olarak getir
     */
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }
    
    /**
     * Tek bir ayarı güncelle veya ekle
     */
    public function update($key, $value) {
        $stmt = $this->db->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        return $stmt->execute([$key, $value, $value]);
    }
    
    /**
     * Çoklu ayar güncellemesi
     */
    public function updateMany($data) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            foreach ($data as $key => $value) {
                $stmt->execute([$key, $value, $value]);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    /**
     * Güvenli dosya yükleme fonksiyonu (Logo, Favicon, Slider vs.)
     */
    public static function uploadFile($file, $subFolder = 'system') {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'Dosya yükleme hatası oluştu.'
            ];
        }
        
        // Dosya boyutu sınırı (Max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return [
                'success' => false,
                'message' => 'Dosya boyutu 5 MB\'tan büyük olamaz.'
            ];
        }
        
        // Mime Type ve Uzantı kontrolü
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif'
        ];
        
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($file['tmp_name']);
        
        if (!array_key_exists($mimeType, $allowedTypes)) {
            return [
                'success' => false,
                'message' => 'Yalnızca JPG, PNG, WEBP ve GIF formatları yüklenebilir.'
            ];
        }
        
        $ext = $allowedTypes[$mimeType];
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        
        // Hedef dizini kontrol et / oluştur
        $destDir = __DIR__ . '/../uploads/' . $subFolder . '/';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        $destPath = $destDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            return [
                'success' => true,
                'path' => 'uploads/' . $subFolder . '/' . $filename
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Dosya taşınırken bir sorun oluştu.'
        ];
    }
}
