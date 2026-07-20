<?php
require_once __DIR__ . '/Database.php';

class Slider {
    private $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    /**
     * Tüm slider içeriklerini getir
     */
    public function getAll($onlyActive = true) {
        $sql = "SELECT * FROM sliders";
        if ($onlyActive) {
            $sql .= " WHERE status = 1";
        }
        $sql .= " ORDER BY order_num ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * ID'ye göre slider getir
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM sliders WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Yeni slider ekle
     */
    public function create($data) {
        $stmt = $this->db->prepare("INSERT INTO sliders (image, title, subtitle, button_text, button_url, order_num, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $data['image'],
            $data['title'],
            $data['subtitle'],
            $data['button_text'] ?? 'Teklif Al',
            $data['button_url'] ?? '#teklif-al',
            $data['order_num'] ?? 0,
            $data['status'] ?? 1
        ]);
    }
    
    /**
     * Slider güncelle
     */
    public function update($id, $data) {
        $stmt = $this->db->prepare("UPDATE sliders SET image = ?, title = ?, subtitle = ?, button_text = ?, button_url = ?, order_num = ?, status = ? WHERE id = ?");
        return $stmt->execute([
            $data['image'],
            $data['title'],
            $data['subtitle'],
            $data['button_text'],
            $data['button_url'],
            $data['order_num'],
            $data['status'],
            $id
        ]);
    }
    
    /**
     * Slider sil
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM sliders WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
