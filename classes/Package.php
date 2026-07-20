<?php
require_once __DIR__ . '/Database.php';

class Package {
    private $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    /**
     * Tüm paketleri getir
     */
    public function getAll($onlyActive = true) {
        $sql = "SELECT p.*, c.name as category_name FROM packages p LEFT JOIN categories c ON p.category_id = c.id";
        if ($onlyActive) {
            $sql .= " WHERE p.status = 1";
        }
        $sql .= " ORDER BY p.duration_weeks ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * ID'ye göre paket getir
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM packages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Yeni paket oluştur
     */
    public function create($data) {
        $isPopular = !empty($data['is_popular']) ? 1 : 0;
        if ($isPopular) {
            $this->db->exec("UPDATE packages SET is_popular = 0");
        }
        
        $stmt = $this->db->prepare("INSERT INTO packages (name, description, duration_weeks, normal_price, discounted_price, time_slot, person_count, image, category_id, features, is_popular, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $res = $stmt->execute([
            $data['name'],
            $data['description'],
            $data['duration_weeks'],
            $data['normal_price'],
            $data['discounted_price'],
            $data['time_slot'] ?? '08-17',
            $data['person_count'] ?? 1,
            $data['image'] ?? null,
            $data['category_id'] ?: null,
            $data['features'] ?? null,
            $isPopular,
            $data['status'] ?? 1
        ]);
        if ($res) {
            $packageId = $this->db->lastInsertId();
            if (!empty($data['category_id'])) {
                $stmtPivot = $this->db->prepare("INSERT IGNORE INTO category_packages (category_id, package_id) VALUES (?, ?)");
                $stmtPivot->execute([$data['category_id'], $packageId]);
            }
            return $packageId;
        }
        return false;
    }
    
    /**
     * Paket güncelle
     */
    public function update($id, $data) {
        $isPopular = !empty($data['is_popular']) ? 1 : 0;
        if ($isPopular) {
            $stmtClean = $this->db->prepare("UPDATE packages SET is_popular = 0 WHERE id != ?");
            $stmtClean->execute([$id]);
        }
        
        $stmt = $this->db->prepare("UPDATE packages SET name = ?, description = ?, duration_weeks = ?, normal_price = ?, discounted_price = ?, time_slot = ?, person_count = ?, image = ?, category_id = ?, features = ?, is_popular = ?, status = ? WHERE id = ?");
        $res = $stmt->execute([
            $data['name'],
            $data['description'],
            $data['duration_weeks'],
            $data['normal_price'],
            $data['discounted_price'],
            $data['time_slot'],
            $data['person_count'],
            $data['image'],
            $data['category_id'] ?: null,
            $data['features'] ?? null,
            $isPopular,
            $data['status'],
            $id
        ]);
        if ($res) {
            $stmtDel = $this->db->prepare("DELETE FROM category_packages WHERE package_id = ?");
            $stmtDel->execute([$id]);
            if (!empty($data['category_id'])) {
                $stmtPivot = $this->db->prepare("INSERT INTO category_packages (category_id, package_id) VALUES (?, ?)");
                $stmtPivot->execute([$data['category_id'], $id]);
            }
        }
        return $res;
    }
    
    /**
     * Paket sil
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM packages WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
