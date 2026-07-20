<?php
require_once __DIR__ . '/Database.php';

class Category {
    private $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    /**
     * Tüm kategorileri getir
     */
    public function getAll($onlyActive = true, $serviceGroup = null) {
        $sql = "SELECT * FROM categories";
        $where = [];
        if ($onlyActive) {
            $where[] = "status = 1";
        }
        if ($serviceGroup !== null) {
            $where[] = "service_group = " . $this->db->quote($serviceGroup);
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY order_num ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Slug değerine göre kategori getir
     */
    public function getBySlug($slug) {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }
    
    /**
     * ID'ye göre kategori getir
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Kategoriye ait alt kategorileri getir
     */
    public function getSubcategories($categoryId, $onlyActive = true) {
        $sql = "SELECT * FROM subcategories WHERE category_id = ?";
        if ($onlyActive) {
            $sql .= " AND status = 1";
        }
        $sql .= " ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Yeni kategori oluştur
     */
    public function create($data) {
        $stmt = $this->db->prepare("INSERT INTO categories (name, slug, icon, image, description, color, pricing_type, service_group, price, half_day_price, max_person, person_full_price, person_half_price, is_subscription_active, status, order_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['icon'],
            $data['image'],
            $data['description'],
            $data['color'] ?? '#0066FF',
            $data['pricing_type'] ?? 'category',
            $data['service_group'] ?? 'general',
            $data['price'] ?? 0.00,
            $data['half_day_price'] ?? 0.00,
            $data['max_person'] ?? 1,
            $data['person_full_price'] ?? 0.00,
            $data['person_half_price'] ?? 0.00,
            $data['is_subscription_active'] ?? 0,
            $data['status'] ?? 1,
            $data['order_num'] ?? 0
        ]);
    }
    
    /**
     * Kategori güncelle
     */
    public function update($id, $data) {
        $stmt = $this->db->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, image = ?, description = ?, color = ?, pricing_type = ?, service_group = ?, price = ?, half_day_price = ?, max_person = ?, person_full_price = ?, person_half_price = ?, is_subscription_active = ?, status = ?, order_num = ? WHERE id = ?");
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['icon'],
            $data['image'],
            $data['description'],
            $data['color'],
            $data['pricing_type'],
            $data['service_group'] ?? 'general',
            $data['price'],
            $data['half_day_price'],
            $data['max_person'] ?? 1,
            $data['person_full_price'] ?? 0.00,
            $data['person_half_price'] ?? 0.00,
            $data['is_subscription_active'],
            $data['status'],
            $data['order_num'],
            $id
        ]);
    }
    
    /**
     * Kategori sil
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM categories WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Alt kategori kaydet (Ekle veya Güncelle)
     */
    public function saveSubcategory($categoryId, $name, $price, $halfDayPrice = 0.00, $maxPerson = 1, $personFullPrice = 0.00, $personHalfPrice = 0.00, $subId = null) {
        if ($subId) {
            $stmt = $this->db->prepare("UPDATE subcategories SET name = ?, price = ?, half_day_price = ?, max_person = ?, person_full_price = ?, person_half_price = ? WHERE id = ? AND category_id = ?");
            return $stmt->execute([$name, $price, $halfDayPrice, $maxPerson, $personFullPrice, $personHalfPrice, $subId, $categoryId]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO subcategories (category_id, name, price, half_day_price, max_person, person_full_price, person_half_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            return $stmt->execute([$categoryId, $name, $price, $halfDayPrice, $maxPerson, $personFullPrice, $personHalfPrice]);
        }
    }
    
    /**
     * Alt kategori sil
     */
    public function deleteSubcategory($subId) {
        $stmt = $this->db->prepare("DELETE FROM subcategories WHERE id = ?");
        return $stmt->execute([$subId]);
    }
    
    /**
     * Kategorinin desteklediği paketleri getir
     */
    public function getPackages($categoryId) {
        $stmt = $this->db->prepare("SELECT p.* FROM packages p INNER JOIN category_packages cp ON p.id = cp.package_id WHERE cp.category_id = ? AND p.status = 1");
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Kategori paket eşleştirmelerini senkronize et
     */
    public function syncPackages($categoryId, $packageIds) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("DELETE FROM category_packages WHERE category_id = ?");
            $stmt->execute([$categoryId]);
            
            if (!empty($packageIds)) {
                $stmt = $this->db->prepare("INSERT INTO category_packages (category_id, package_id) VALUES (?, ?)");
                foreach ($packageIds as $packageId) {
                    $stmt->execute([$categoryId, $packageId]);
                }
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
