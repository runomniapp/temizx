<?php
require_once __DIR__ . '/Database.php';

class Employee {
    private $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    /**
     * Tüm personelleri getir
     */
    public function getAll($onlyActive = false, $type = null) {
        $sql = "SELECT * FROM employees";
        $where = [];
        if ($onlyActive) {
            $where[] = "status = 'active'";
        }
        if ($type !== null) {
            $where[] = "employee_type = " . $this->db->quote($type);
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * ID'ye göre personel getir
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Yeni personel ekle
     */
    public function create($data) {
        $stmt = $this->db->prepare("INSERT INTO employees (name, phone, photo, status, work_hours_start, work_hours_end, off_days, employee_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $data['name'],
            $data['phone'],
            $data['photo'] ?? 'default_employee.png',
            $data['status'] ?? 'active',
            $data['work_hours_start'] ?? '08:00:00',
            $data['work_hours_end'] ?? '17:00:00',
            $data['off_days'] ?? 'Sunday',
            $data['employee_type'] ?? 'general'
        ]);
    }
    
    /**
     * Personel güncelle
     */
    public function update($id, $data) {
        $stmt = $this->db->prepare("UPDATE employees SET name = ?, phone = ?, photo = ?, status = ?, work_hours_start = ?, work_hours_end = ?, off_days = ?, employee_type = ? WHERE id = ?");
        return $stmt->execute([
            $data['name'],
            $data['phone'],
            $data['photo'],
            $data['status'],
            $data['work_hours_start'],
            $data['work_hours_end'],
            $data['off_days'],
            $data['employee_type'] ?? 'general',
            $id
        ]);
    }
    
    /**
     * Personel sil
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM employees WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * İki zaman diliminin çakışıp çakışmadığını kontrol et
     */
    public static function isOverlap($slot1, $slot2) {
        if ($slot1 === $slot2) {
            return true;
        }
        if ($slot1 === '08-17' || $slot2 === '08-17') {
            return true;
        }
        return false; // Biri 08-12 diğeri 13-17 ise çakışmazlar
    }
    
    /**
     * Personelin belirli bir tarihte ve saat diliminde uygunluğunu kontrol et
     */
    public function checkAvailability($employeeId, $date, $timeSlot) {
        $emp = $this->getById($employeeId);
        if (!$emp || $emp['status'] !== 'active') {
            return false;
        }
        
        // Haftalık izin günü kontrolü (Örn: 'Sunday', 'Monday')
        $dayName = date('l', strtotime($date)); // İngilizce gün adı (Sunday, Monday vb.)
        $offDays = array_map('trim', explode(',', $emp['off_days']));
        if (in_array($dayName, $offDays)) {
            return false;
        }
        
        // Çalışanın o gün atanmış olduğu çakışan işleri kontrol et
        $stmt = $this->db->prepare("
            SELECT bs.time_slot 
            FROM booking_schedule bs 
            INNER JOIN booking_employees be ON bs.id = be.booking_schedule_id
            WHERE be.employee_id = ? AND bs.date = ? AND bs.status != 'cancelled'
        ");
        $stmt->execute([$employeeId, $date]);
        $existingBookings = $stmt->fetchAll();
        
        foreach ($existingBookings as $eb) {
            if (self::isOverlap($eb['time_slot'], $timeSlot)) {
                return false; // Çakışan iş var
            }
        }
        
        return true;
    }
    
    /**
     * Belirli tarih ve saat dilimi için uygun (boşta) personelleri getir
     */
    public function getAvailableEmployees($date, $timeSlot, $type = null) {
        $allActive = $this->getAll(true, $type);
        $available = [];
        
        foreach ($allActive as $emp) {
            if ($this->checkAvailability($emp['id'], $date, $timeSlot)) {
                $available[] = $emp;
            }
        }
        
        return $available;
    }
}
