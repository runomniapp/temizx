<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Employee.php';
require_once __DIR__ . '/Category.php';
require_once __DIR__ . '/WhatsAppService.php';

class Booking {
    private $db;
    
    public function __construct() {
        $this->db = Database::getConnection();
    }
    
    /**
     * Tarihi/saati geçen confirmed rezervasyonları otomatik completed yapar
     */
    public function autoCompleteExpiredBookings() {
        try {
            $this->db->query("
                UPDATE booking_schedule 
                SET status = 'completed' 
                WHERE status = 'confirmed' 
                  AND (
                    date < CURRENT_DATE() 
                    OR (date = CURRENT_DATE() AND (
                      (time_slot = '08-12' AND HOUR(CURRENT_TIME()) >= 12)
                      OR ((time_slot = '13-17' OR time_slot = '08-17') AND HOUR(CURRENT_TIME()) >= 17)
                    ))
                  )
            ");
            
            $this->db->query("
                UPDATE bookings b
                SET b.status = 'completed'
                WHERE b.status = 'confirmed'
                  AND NOT EXISTS (
                    SELECT 1 FROM booking_schedule bs 
                    WHERE bs.booking_id = b.id 
                      AND bs.status IN ('pending', 'confirmed')
                  )
            ");
        } catch (Exception $e) {
            // Sessizce yoksay
        }
    }
    
    /**
     * Yeni rezervasyon oluştur (Otomatik haftalık takvim planlama ve personel atama ile)
     */
    public function createBooking($data) {
        $this->db->beginTransaction();
        try {
            // Fiyat hesaplama
            $totalPrice = 0;
            if (!empty($data['package_id'])) {
                // Abonelik paketi fiyatı
                $stmt = $this->db->prepare("SELECT discounted_price FROM packages WHERE id = ?");
                $stmt->execute([$data['package_id']]);
                $totalPrice = (float)$stmt->fetchColumn();
            } else {
                $priceRow = null;
                if (!empty($data['subcategory_id'])) {
                    // Alt kategori fiyatı
                    $stmt = $this->db->prepare("SELECT price, half_day_price, max_person, person_full_price, person_half_price FROM subcategories WHERE id = ?");
                    $stmt->execute([$data['subcategory_id']]);
                    $priceRow = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    // Ana kategori sabit fiyatı
                    $stmt = $this->db->prepare("SELECT price, half_day_price, max_person, person_full_price, person_half_price FROM categories WHERE id = ?");
                    $stmt->execute([$data['category_id']]);
                    $priceRow = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                
                $price = $priceRow ? (float)$priceRow['price'] : 0.0;
                $halfDayPrice = $priceRow ? (float)$priceRow['half_day_price'] : 0.0;
                $maxPerson = $priceRow ? (int)$priceRow['max_person'] : 1;
                $personFullPrice = $priceRow ? (float)$priceRow['person_full_price'] : 0.0;
                $personHalfPrice = $priceRow ? (float)$priceRow['person_half_price'] : 0.0;
                
                // Zaman dilimine göre birim fiyat belirle
                $timeSlot = $data['booking_time_slot'] ?? '08-17';
                $isHalfDay = ($timeSlot === '08-12' || $timeSlot === '13-17');
                
                $basePrice = $isHalfDay ? $halfDayPrice : $price;
                $extraPersonPrice = $isHalfDay ? $personHalfPrice : $personFullPrice;
                
                $personCount = (int)($data['person_count'] ?? 1);
                $serviceDays = (int)($data['service_days'] ?? 1);
                
                $extraCount = max(0, $personCount - $maxPerson);
                $calculatedPrice = ($basePrice + ($extraCount * $extraPersonPrice)) * $serviceDays;
                
                if (isset($data['total_price']) && $data['total_price'] > 0) {
                    $totalPrice = (float)$data['total_price'];
                } else {
                    $totalPrice = $calculatedPrice;
                }
            }
            
            // Rezervasyonu kaydet
            $stmt = $this->db->prepare("
                INSERT INTO bookings (
                    category_id, subcategory_id, package_id, customer_name, customer_phone, 
                    customer_address, customer_location, customer_email, booking_date, 
                    booking_time_slot, person_count, service_days, total_price, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Varsayılan durum: 'pending' (teklif)
            $status = $data['status'] ?? 'pending';
            
            $stmt->execute([
                $data['category_id'],
                $data['subcategory_id'] ?: null,
                $data['package_id'] ?: null,
                $data['customer_name'],
                $data['customer_phone'],
                $data['customer_address'],
                $data['customer_location'] ?: null,
                $data['customer_email'] ?: null,
                $data['booking_date'],
                $data['booking_time_slot'],
                $data['person_count'] ?? 1,
                $data['service_days'] ?? 1,
                $totalPrice,
                $status
            ]);
            
            $bookingId = $this->db->lastInsertId();
            
            // Programlama (Schedule) Tanımlama
            $isWeekly = !empty($data['package_id']);
            $durationWeeks = 1;
            if ($isWeekly) {
                $stmt = $this->db->prepare("SELECT duration_weeks FROM packages WHERE id = ?");
                $stmt->execute([$data['package_id']]);
                $durationWeeks = (int)$stmt->fetchColumn();
            } else {
                $durationWeeks = (int)($data['service_days'] ?? 1);
            }
            
            $employeeModel = new Employee();
            
            // Periyotlarla takvimi doldur
            for ($i = 0; $i < $durationWeeks; $i++) {
                if ($isWeekly) {
                    $occDate = date('Y-m-d', strtotime("+$i weeks", strtotime($data['booking_date'])));
                } else {
                    $occDate = date('Y-m-d', strtotime("+$i days", strtotime($data['booking_date'])));
                }
                
                $stmt = $this->db->prepare("INSERT INTO booking_schedule (booking_id, date, time_slot, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$bookingId, $occDate, $data['booking_time_slot'], $status]);
                $scheduleId = $this->db->lastInsertId();
                
                // Sadece onaylı rezervasyonlar için otomatik personel ata
                if ($status === 'confirmed') {
                    $catModel = new Category();
                    $cat = $catModel->getById($data['category_id']);
                    $type = $cat ? $cat['service_group'] : 'general';
                    
                    $availableEmps = $employeeModel->getAvailableEmployees($occDate, $data['booking_time_slot'], $type);
                    $assignedCount = 0;
                    $neededCount = (int)($data['person_count'] ?? 1);
                    
                    $stmtAssign = $this->db->prepare("INSERT INTO booking_employees (booking_schedule_id, employee_id) VALUES (?, ?)");
                    foreach ($availableEmps as $emp) {
                        if ($assignedCount >= $neededCount) {
                            break;
                        }
                        $stmtAssign->execute([$scheduleId, $emp['id']]);
                        $assignedCount++;
                    }
                }
            }
            
            $this->db->commit();
            return $bookingId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Detaylı rezervasyon bilgisi getir
     */
    public function getById($id) {
        $this->autoCompleteExpiredBookings();
        $stmt = $this->db->prepare("
            SELECT b.*, 
                   c.name as category_name, c.color as category_color, c.icon as category_icon,
                   sc.name as subcategory_name, 
                   p.name as package_name, p.duration_weeks
            FROM bookings b
            INNER JOIN categories c ON b.category_id = c.id
            LEFT JOIN subcategories sc ON b.subcategory_id = sc.id
            LEFT JOIN packages p ON b.package_id = p.id
            WHERE b.id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Tüm rezervasyonları/teklifleri listele
     */
    public function getAll($filters = []) {
        $this->autoCompleteExpiredBookings();
        $sql = "
            SELECT b.*, 
                   c.name as category_name, c.color as category_color,
                   sc.name as subcategory_name, 
                   p.name as package_name, p.duration_weeks
            FROM bookings b
            INNER JOIN categories c ON b.category_id = c.id
            LEFT JOIN subcategories sc ON b.subcategory_id = sc.id
            LEFT JOIN packages p ON b.package_id = p.id
            WHERE 1=1
        ";
        $params = [];
        
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql .= " AND b.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['search']) && $filters['search'] !== '') {
            $sql .= " AND (b.customer_name LIKE ? OR b.customer_phone LIKE ? OR b.customer_email LIKE ?)";
            $searchVal = "%" . $filters['search'] . "%";
            $params[] = $searchVal;
            $params[] = $searchVal;
            $params[] = $searchVal;
        }
        
        $sql .= " ORDER BY b.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Rezervasyon durumunu güncelle
     */
    public function updateStatus($bookingId, $status) {
        $this->db->beginTransaction();
        try {
            // Ana rezervasyon durumunu güncelle
            $stmt = $this->db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$status, $bookingId]);
            
            // Program (schedule) kayıtlarının durumunu da güncelle
            $stmt = $this->db->prepare("UPDATE booking_schedule SET status = ? WHERE booking_id = ?");
            $stmt->execute([$status, $bookingId]);
            
            // Eğer onaylandıysa ve atanmış personel yoksa otomatik personel atamayı tetikle
            if ($status === 'confirmed') {
                $booking = $this->getById($bookingId);
                $schedules = $this->getSchedule($bookingId);
                $employeeModel = new Employee();
                
                $stmtCheck = $this->db->prepare("SELECT COUNT(*) FROM booking_employees WHERE booking_schedule_id = ?");
                $stmtAssign = $this->db->prepare("INSERT INTO booking_employees (booking_schedule_id, employee_id) VALUES (?, ?)");
                
                foreach ($schedules as $sch) {
                    $stmtCheck->execute([$sch['id']]);
                    if ($stmtCheck->fetchColumn() == 0) {
                        // Atama yapılmamış, otomatik ata
                        $catModel = new Category();
                        $cat = $catModel->getById($booking['category_id']);
                        $type = $cat ? $cat['service_group'] : 'general';
                        
                        $availableEmps = $employeeModel->getAvailableEmployees($sch['date'], $sch['time_slot'], $type);
                        $assignedCount = 0;
                        $neededCount = (int)$booking['person_count'];
                        
                        foreach ($availableEmps as $emp) {
                            if ($assignedCount >= $neededCount) {
                                break;
                            }
                            $stmtAssign->execute([$sch['id'], $emp['id']]);
                            $assignedCount++;
                        }
                    }
                }
            }
            
            $this->db->commit();

            // WhatsApp bildirimi tetikle (durum onaylandıysa)
            if ($status === 'confirmed') {
                WhatsAppService::sendBookingNotifications($bookingId);
            }

            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    /**
     * Rezervasyona ait tüm günleri/takvimi getir
     */
    public function getSchedule($bookingId) {
        $stmt = $this->db->prepare("SELECT * FROM booking_schedule WHERE booking_id = ? ORDER BY date ASC");
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Günleri ve atanan personelleri getir
     */
    public function getScheduleWithEmployees($bookingId) {
        $schedules = $this->getSchedule($bookingId);
        foreach ($schedules as &$sch) {
            $stmt = $this->db->prepare("
                SELECT e.* FROM employees e 
                INNER JOIN booking_employees be ON e.id = be.employee_id 
                WHERE be.booking_schedule_id = ?
            ");
            $stmt->execute([$sch['id']]);
            $sch['employees'] = $stmt->fetchAll();
        }
        return $schedules;
    }
    
    /**
     * Tek bir takvim gününe personel ata/güncelle
     */
    public function assignEmployeesToScheduleSlot($scheduleId, $employeeIds) {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("DELETE FROM booking_employees WHERE booking_schedule_id = ?");
            $stmt->execute([$scheduleId]);
            
            if (!empty($employeeIds)) {
                $stmt = $this->db->prepare("INSERT INTO booking_employees (booking_schedule_id, employee_id) VALUES (?, ?)");
                foreach ($employeeIds as $empId) {
                    $stmt->execute([$scheduleId, $empId]);
                }
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    /**
     * Belirli bir tarih aralığındaki tüm takvim kayıtlarını getir (Takvim görünümü için)
     */
    public function getScheduleRange($startDate, $endDate, $serviceGroup = null) {
        $sql = "
            SELECT bs.*, 
                   b.customer_name, b.customer_phone, b.customer_address, b.person_count, b.service_days, b.total_price, b.category_id, b.subcategory_id,
                   c.name as category_name, c.color as category_color, c.icon as category_icon,
                   sc.name as subcategory_name
            FROM booking_schedule bs
            INNER JOIN bookings b ON bs.booking_id = b.id
            INNER JOIN categories c ON b.category_id = c.id
            LEFT JOIN subcategories sc ON b.subcategory_id = sc.id
            WHERE bs.date BETWEEN ? AND ? AND bs.status != 'cancelled'
        ";
        if ($serviceGroup !== null) {
            $sql .= " AND c.service_group = " . $this->db->quote($serviceGroup);
        }
        $sql .= " ORDER BY bs.date ASC, bs.time_slot ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        $schedules = $stmt->fetchAll();
        
        foreach ($schedules as &$sch) {
            $stmt = $this->db->prepare("
                SELECT e.* FROM employees e 
                INNER JOIN booking_employees be ON e.id = be.employee_id 
                WHERE be.booking_schedule_id = ?
            ");
            $stmt->execute([$sch['id']]);
            $sch['employees'] = $stmt->fetchAll();
        }
        return $schedules;
    }
    
    /**
     * Bugünün işlerini getir
     */
    public function getTodayJobs() {
        $today = date('Y-m-d');
        return $this->getScheduleRange($today, $today);
    }
    
    /**
     * İstatistikleri getir
     */
    public function getStats() {
        $stats = [];
        // Toplam Kazanç (Tamamlanmış veya onaylanmış olanlar)
        $stats['total_revenue'] = $this->db->query("SELECT SUM(total_price) FROM bookings WHERE status IN ('confirmed', 'completed')")->fetchColumn() ?: 0;
        // Toplam Teklif/Rezervasyon
        $stats['total_bookings'] = $this->db->query("SELECT COUNT(*) FROM bookings")->fetchColumn() ?: 0;
        // Bekleyen Teklifler
        $stats['pending_bookings'] = $this->db->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn() ?: 0;
        // Aktif Personel Sayısı
        $stats['active_employees'] = $this->db->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn() ?: 0;
        
        return $stats;
    }
    
    /**
     * Takvim satırını ve bağlı rezervasyon bilgilerini güncelle (Admin Düzenleme Formu)
     */
    public function updateBookingDetailsFromSchedule($scheduleId, $data, $employeeIds) {
        $this->db->beginTransaction();
        try {
            // 1. Get schedule record to find booking_id
            $stmt = $this->db->prepare("SELECT booking_id FROM booking_schedule WHERE id = ?");
            $stmt->execute([$scheduleId]);
            $bookingId = $stmt->fetchColumn();
            
            if (!$bookingId) {
                throw new Exception("Geçersiz takvim kaydı.");
            }
            
            // Get original booking details first
            $orig = $this->getById($bookingId);
            if (!$orig) {
                throw new Exception("Rezervasyon bulunamadı.");
            }
            
            $totalPrice = isset($data['total_price']) ? (float)$data['total_price'] : (float)$orig['total_price'];
            
            // Atama yapıldıysa durumu otomatik onaylandı ('confirmed') yap
            $newStatus = $orig['status'];
            if (!empty($employeeIds)) {
                $newStatus = 'confirmed';
            }
            
            // 2. Update bookings (customer_name, customer_phone, customer_address, person_count, service_days, total_price, category_id, subcategory_id, status)
            $stmt = $this->db->prepare("
                UPDATE bookings 
                SET customer_name = ?, customer_phone = ?, customer_address = ?, person_count = ?, service_days = ?, total_price = ?, category_id = ?, subcategory_id = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['customer_name'],
                $data['customer_phone'],
                $data['customer_address'],
                $data['person_count'],
                $data['service_days'] ?? 1,
                $totalPrice,
                $data['category_id'],
                $data['subcategory_id'] ?: null,
                $newStatus,
                $bookingId
            ]);
            
            // 3. Update ONLY the target schedule slot
            $stmtUpd = $this->db->prepare("UPDATE booking_schedule SET date = ?, time_slot = ?, status = ? WHERE id = ?");
            $stmtUpd->execute([
                $data['date'],
                $data['time_slot'],
                $newStatus,
                $scheduleId
            ]);
            
            // 4. Adjust the number of schedule rows if they changed service_days or package
            $isWeekly = !empty($orig['package_id']);
            $requiredCount = 1;
            if ($isWeekly) {
                $stmtPack = $this->db->prepare("SELECT duration_weeks FROM packages WHERE id = ?");
                $stmtPack->execute([$orig['package_id']]);
                $requiredCount = (int)$stmtPack->fetchColumn() ?: 1;
            } else {
                $requiredCount = (int)($data['service_days'] ?? 1);
            }
            
            $stmt = $this->db->prepare("SELECT id, date FROM booking_schedule WHERE booking_id = ? ORDER BY date ASC, id ASC");
            $stmt->execute([$bookingId]);
            $schedules = $stmt->fetchAll();
            $currentCount = count($schedules);
            
            if ($currentCount < $requiredCount) {
                // Add new slots without shifting the existing ones
                $maxDate = $data['date'];
                foreach ($schedules as $sch) {
                    if ($sch['date'] > $maxDate) {
                        $maxDate = $sch['date'];
                    }
                }
                
                for ($i = $currentCount; $i < $requiredCount; $i++) {
                    $offset = $i - $currentCount + 1;
                    if ($isWeekly) {
                        $occDate = date('Y-m-d', strtotime("+$offset weeks", strtotime($maxDate)));
                    } else {
                        $occDate = date('Y-m-d', strtotime("+$offset days", strtotime($maxDate)));
                    }
                    
                    $stmtIns = $this->db->prepare("INSERT INTO booking_schedule (booking_id, date, time_slot, status) VALUES (?, ?, ?, ?)");
                    $stmtIns->execute([
                        $bookingId,
                        $occDate,
                        $data['time_slot'],
                        $newStatus
                    ]);
                }
            } elseif ($currentCount > $requiredCount) {
                // Delete excess slots from the end
                $deletedCount = 0;
                $toDelete = $currentCount - $requiredCount;
                
                // Fetch schedule IDs in descending order of date to delete the latest ones
                $stmtDesc = $this->db->prepare("SELECT id FROM booking_schedule WHERE booking_id = ? ORDER BY date DESC, id DESC");
                $stmtDesc->execute([$bookingId]);
                $descSchedules = $stmtDesc->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($descSchedules as $schIdToDelete) {
                    if ($deletedCount >= $toDelete) {
                        break;
                    }
                    if ($schIdToDelete != $scheduleId) {
                        $stmtDel = $this->db->prepare("DELETE FROM booking_schedule WHERE id = ?");
                        $stmtDel->execute([$schIdToDelete]);
                        $deletedCount++;
                    }
                }
            }
            
            // 5. Update employee assignments ONLY for the target schedule slot
            $stmtDelEmp = $this->db->prepare("DELETE FROM booking_employees WHERE booking_schedule_id = ?");
            $stmtDelEmp->execute([$scheduleId]);
            
            if (!empty($employeeIds)) {
                $stmtAssign = $this->db->prepare("INSERT INTO booking_employees (booking_schedule_id, employee_id) VALUES (?, ?)");
                foreach ($employeeIds as $empId) {
                    $stmtAssign->execute([$scheduleId, $empId]);
                }
            }
            
            // 6. Update the main booking's booking_date and booking_time_slot to match the first (earliest) schedule slot's date and time_slot
            $stmtFirst = $this->db->prepare("SELECT date, time_slot FROM booking_schedule WHERE booking_id = ? ORDER BY date ASC, id ASC LIMIT 1");
            $stmtFirst->execute([$bookingId]);
            $firstSchedule = $stmtFirst->fetch(PDO::FETCH_ASSOC);
            if ($firstSchedule) {
                $stmtMainDate = $this->db->prepare("UPDATE bookings SET booking_date = ?, booking_time_slot = ? WHERE id = ?");
                $stmtMainDate->execute([
                    $firstSchedule['date'],
                    $firstSchedule['time_slot'],
                    $bookingId
                ]);
            }
            
            $this->db->commit();

            if ($newStatus === 'confirmed') {
                WhatsAppService::sendBookingNotifications($bookingId);
            }

            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Rezervasyonu ve bağlı tüm kayıtları sil
     */
    public function deleteBooking($id) {
        $stmt = $this->db->prepare("DELETE FROM bookings WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Rezervasyonu tüm bağlı alanları ve takvimiyle birlikte güncelle (Tarih öteleme dahil)
     */
    public function updateBooking($id, $data, $employeeIds = []) {
        $this->db->beginTransaction();
        try {
            $orig = $this->getById($id);
            if (!$orig) {
                throw new Exception("Rezervasyon bulunamadı.");
            }
            
            // Manuel girilen fiyatı kullan, yoksa veritabanındaki mevcut fiyatı koru
            $isManualPrice = isset($data['total_price']) && $data['total_price'] !== '';
            $totalPrice = $isManualPrice ? (float)$data['total_price'] : (float)$orig['total_price'];
            
            if (!$isManualPrice) {
                if (!empty($orig['package_id'])) {
                    $stmt = $this->db->prepare("SELECT discounted_price FROM packages WHERE id = ?");
                    $stmt->execute([$orig['package_id']]);
                    $totalPrice = (float)$stmt->fetchColumn();
                } else {
                    $priceRow = null;
                    if (!empty($data['subcategory_id'])) {
                        $stmt = $this->db->prepare("SELECT price, half_day_price, max_person, person_full_price, person_half_price FROM subcategories WHERE id = ?");
                        $stmt->execute([$data['subcategory_id']]);
                        $priceRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $stmt = $this->db->prepare("SELECT price, half_day_price, max_person, person_full_price, person_half_price FROM categories WHERE id = ?");
                        $stmt->execute([$data['category_id']]);
                        $priceRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                    
                    if ($priceRow) {
                        $price = (float)$priceRow['price'];
                        $halfDayPrice = (float)$priceRow['half_day_price'];
                        $maxPerson = (int)$priceRow['max_person'];
                        $personFullPrice = (float)$priceRow['person_full_price'];
                        $personHalfPrice = (float)$priceRow['person_half_price'];
                        
                        $timeSlot = $data['booking_time_slot'] ?? '08-17';
                        $isHalfDay = ($timeSlot === '08-12' || $timeSlot === '13-17');
                        
                        $basePrice = $isHalfDay ? $halfDayPrice : $price;
                        $extraPersonPrice = $isHalfDay ? $personHalfPrice : $personFullPrice;
                        
                        $personCount = (int)($data['person_count'] ?? 1);
                        $serviceDays = (int)($data['service_days'] ?? 1);
                        
                        $extraCount = max(0, $personCount - $maxPerson);
                        $totalPrice = ($basePrice + ($extraCount * $extraPersonPrice)) * $serviceDays;
                    }
                }
            }
            
            $stmt = $this->db->prepare("
                UPDATE bookings 
                SET category_id = ?, subcategory_id = ?, customer_name = ?, customer_phone = ?, 
                    customer_email = ?, customer_address = ?, booking_date = ?, booking_time_slot = ?, 
                    person_count = ?, service_days = ?, total_price = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['category_id'],
                (!empty($data['subcategory_id']) ? $data['subcategory_id'] : null),
                $data['customer_name'],
                $data['customer_phone'],
                (!empty($data['customer_email']) ? $data['customer_email'] : null),
                $data['customer_address'],
                $data['booking_date'],
                $data['booking_time_slot'],
                $data['person_count'] ?? 1,
                $data['service_days'] ?? 1,
                $totalPrice,
                $data['status'],
                $id
            ]);
            
            // Takvim oturumlarını senkronize et (Gün sayısı artırıldıysa yeni kayıt ekle, azaltıldıysa sil, tarihleri güncelle)
            $isWeekly = !empty($orig['package_id']);
            $requiredCount = 1;
            if ($isWeekly) {
                $stmtPack = $this->db->prepare("SELECT duration_weeks FROM packages WHERE id = ?");
                $stmtPack->execute([$orig['package_id']]);
                $requiredCount = (int)$stmtPack->fetchColumn() ?: 1;
            } else {
                $requiredCount = (int)($data['service_days'] ?? 1);
            }
            
            $stmt = $this->db->prepare("SELECT id FROM booking_schedule WHERE booking_id = ? ORDER BY date ASC");
            $stmt->execute([$id]);
            $schedules = $stmt->fetchAll();
            $currentCount = count($schedules);
            
            for ($i = 0; $i < $requiredCount; $i++) {
                if ($isWeekly) {
                    $occDate = date('Y-m-d', strtotime("+$i weeks", strtotime($data['booking_date'])));
                } else {
                    $occDate = date('Y-m-d', strtotime("+$i days", strtotime($data['booking_date'])));
                }
                
                if ($i < $currentCount) {
                    $schId = $schedules[$i]['id'];
                    $stmtUpd = $this->db->prepare("UPDATE booking_schedule SET date = ?, time_slot = ?, status = ? WHERE id = ?");
                    $stmtUpd->execute([
                        $occDate,
                        $data['booking_time_slot'],
                        $data['status'],
                        $schId
                    ]);
                } else {
                    $stmtIns = $this->db->prepare("INSERT INTO booking_schedule (booking_id, date, time_slot, status) VALUES (?, ?, ?, ?)");
                    $stmtIns->execute([
                        $id,
                        $occDate,
                        $data['booking_time_slot'],
                        $data['status']
                    ]);
                }
            }
            
            if ($currentCount > $requiredCount) {
                for ($i = $requiredCount; $i < $currentCount; $i++) {
                    $schId = $schedules[$i]['id'];
                    $stmtDel = $this->db->prepare("DELETE FROM booking_schedule WHERE id = ?");
                    $stmtDel->execute([$schId]);
                }
            }

            // Tüm takvim günlerinin id listesini çek ve hepsine seçili personelleri ata
            $stmtSch = $this->db->prepare("SELECT id FROM booking_schedule WHERE booking_id = ?");
            $stmtSch->execute([$id]);
            $allBookingSchedules = $stmtSch->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($allBookingSchedules)) {
                // Mevcut tüm atamaları sil
                $inClause = implode(',', array_fill(0, count($allBookingSchedules), '?'));
                $stmtDel = $this->db->prepare("DELETE FROM booking_employees WHERE booking_schedule_id IN ($inClause)");
                $stmtDel->execute($allBookingSchedules);
                
                // Eğer çalışan seçildiyse ve status confirmed/completed ise, tüm günlere ata
                if (in_array($data['status'], ['confirmed', 'completed']) && !empty($employeeIds)) {
                    $stmtAssign = $this->db->prepare("INSERT INTO booking_employees (booking_schedule_id, employee_id) VALUES (?, ?)");
                    foreach ($allBookingSchedules as $schId) {
                        foreach ($employeeIds as $empId) {
                            $stmtAssign->execute([$schId, $empId]);
                        }
                    }
                }
            }
            
            $this->db->commit();

            if ($data['status'] === 'confirmed') {
                WhatsAppService::sendBookingNotifications($id);
            }

            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
