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
            // Compress employee images to 150x150 square thumbnail and discard the large original
            if ($subFolder === 'employees') {
                self::compressImageToThumbnail($destPath, $mimeType, 150);
            }
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

    /**
     * Compress and crop an image to a square thumbnail, saving it back directly.
     */
    public static function compressImageToThumbnail($filePath, $mimeType, $targetSize = 150) {
        if (!function_exists('imagecreatefromstring')) {
            return false; // GD extension is not enabled
        }
        
        $imgData = @file_get_contents($filePath);
        if ($imgData === false) {
            return false;
        }
        
        $src = @imagecreatefromstring($imgData);
        if (!$src) {
            return false;
        }
        
        $src_w = imagesx($src);
        $src_h = imagesy($src);
        
        // Create square thumbnail canvas
        $thumb = imagecreatetruecolor($targetSize, $targetSize);
        
        // Preserve transparency for PNG and WEBP
        if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefilledrectangle($thumb, 0, 0, $targetSize, $targetSize, $transparent);
        }
        
        // Calculate crop bounds for exact centering without distortion
        $src_x = 0;
        $src_y = 0;
        if ($src_w > $src_h) {
            $src_x = ($src_w - $src_h) / 2;
            $src_w = $src_h;
        } else {
            $src_y = ($src_h - $src_w) / 2;
            $src_h = $src_w;
        }
        
        // Perform centered resize
        imagecopyresampled($thumb, $src, 0, 0, $src_x, $src_y, $targetSize, $targetSize, $src_w, $src_h);
        
        // Overwrite the original file with the compressed thumbnail
        if ($mimeType === 'image/png') {
            imagepng($thumb, $filePath, 8); // PNG compression 0-9
        } elseif ($mimeType === 'image/webp') {
            imagewebp($thumb, $filePath, 80); // WEBP quality 0-100
        } else {
            imagejpeg($thumb, $filePath, 80); // JPEG quality 0-100
        }
        
        imagedestroy($src);
        imagedestroy($thumb);
        return true;
    }
}
