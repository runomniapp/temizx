<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Booking.php';

class WhatsAppService {
    private static $sentBookings = [];

    public static function getServiceUrl() {
        return getSetting('whatsapp_service_url', 'http://localhost:3099');
    }

    /**
     * WhatsApp Servisinin durumunu kontrol et
     */
    public static function getStatus() {
        $ch = curl_init(self::getServiceUrl() . '/status');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }

        return [
            'status' => 'OFFLINE',
            'message' => 'WhatsApp Node.js servisine ulaşılamıyor.'
        ];
    }

    /**
     * Bir rezervasyon için WhatsApp bildirimlerini tetikle
     * (Müşteriye onay + teşekkür mesajı, Atanan çalışanlara görev bildirimi)
     */
    public static function sendBookingNotifications($bookingId) {
        if (empty($bookingId)) {
            return ['success' => false, 'message' => 'Geçersiz rezervasyon ID.'];
        }

        // Aynı PHP isteği içinde tekerrür tetiklemeyi önle
        if (in_array($bookingId, self::$sentBookings)) {
            return [
                'success' => true,
                'message' => 'Bu rezervasyon için bu istekte zaten bildirim tetiklendi.'
            ];
        }
        self::$sentBookings[] = $bookingId;
        try {
            $db = Database::getConnection();
            $bookingModel = new Booking();

            // Rezervasyon bilgilerini al
            $booking = $bookingModel->getById($bookingId);
            if (!$booking) {
                return [
                    'success' => false,
                    'message' => 'Rezervasyon bulunamadı.'
                ];
            }

            // Hizmet adını belirle
            $serviceName = $booking['category_name'];
            if (!empty($booking['subcategory_name'])) {
                $serviceName .= ' - ' . $booking['subcategory_name'];
            } elseif (!empty($booking['package_name'])) {
                $serviceName .= ' (' . $booking['package_name'] . ')';
            }

            // Atanan çalışanları sorgula (booking_schedule ve booking_employees üzerinden)
            $stmtEmps = $db->prepare("
                SELECT DISTINCT e.id, e.name, e.phone 
                FROM employees e
                INNER JOIN booking_employees be ON e.id = be.employee_id
                INNER JOIN booking_schedule bs ON be.booking_schedule_id = bs.id
                WHERE bs.booking_id = ?
            ");
            $stmtEmps->execute([$bookingId]);
            $employees = $stmtEmps->fetchAll(PDO::FETCH_ASSOC);

            // Payload hazırla
            $payload = [
                'booking_id' => (int)$booking['id'],
                'customer' => [
                    'name' => $booking['customer_name'],
                    'phone' => $booking['customer_phone'],
                    'address' => $booking['customer_address']
                ],
                'booking_date' => $booking['booking_date'],
                'time_slot' => $booking['booking_time_slot'],
                'service_name' => $serviceName,
                'employees' => array_map(function($emp) {
                    return [
                        'name' => $emp['name'],
                        'phone' => $emp['phone']
                    ];
                }, $employees)
            ];

            // Node.js HTTP POST Gönderimi
            $ch = curl_init(self::getServiceUrl() . '/send-booking');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $resultData = json_decode($response, true);
                return [
                    'success' => true,
                    'data' => $resultData
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'WhatsApp servisi yanıt vermedi: ' . ($curlError ?: 'HTTP Code ' . $httpCode)
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'WhatsApp bildirim hatası: ' . $e->getMessage()
            ];
        }
    }
}
