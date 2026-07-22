<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Booking.php';

class WhatsAppService {
    private static $sentBookings = [];

    public static function getProviderType() {
        return getSetting('whatsapp_provider_type', 'cloud_api');
    }

    public static function getServiceUrl() {
        return getSetting('whatsapp_service_url', 'http://localhost:3099');
    }

    /**
     * WhatsApp Servisinin durumunu kontrol et
     */
    public static function getStatus() {
        $provider = self::getProviderType();

        if ($provider === 'cloud_api') {
            $token = getSetting('whatsapp_cloud_token', '');
            $phoneId = getSetting('whatsapp_cloud_phone_id', '');

            if (empty($token) || empty($phoneId)) {
                return [
                    'status' => 'OFFLINE',
                    'provider' => 'cloud_api',
                    'message' => 'Meta WhatsApp Cloud API erişim anahtarları girilmemiş.'
                ];
            }

            // Meta Graph API ile telefon numarasını ve token geçerliliğini test et
            $url = "https://graph.facebook.com/v19.0/{$phoneId}?access_token=" . urlencode($token);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                return [
                    'status' => 'READY',
                    'provider' => 'cloud_api',
                    'info' => [
                        'name' => $data['display_phone_number'] ?? ($data['verified_name'] ?? 'Meta Business Line'),
                        'phone' => $data['display_phone_number'] ?? ($data['id'] ?? '-')
                    ]
                ];
            } else {
                $data = json_decode($response, true);
                return [
                    'status' => 'OFFLINE',
                    'provider' => 'cloud_api',
                    'lastError' => $data['error']['message'] ?? ('HTTP Code ' . $httpCode)
                ];
            }
        }

        // Fallback to Node.js Microservice
        $ch = curl_init(self::getServiceUrl() . '/status');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $res = json_decode($response, true);
            $res['provider'] = 'web_js';
            return $res;
        }

        return [
            'status' => 'OFFLINE',
            'provider' => 'web_js',
            'message' => 'WhatsApp Node.js servisine ulaşılamıyor.'
        ];
    }

    /**
     * Meta Cloud API üzerinden tekil metin mesajı gönder
     */
    public static function sendCloudApiMessage($toPhone, $messageText) {
        $token = getSetting('whatsapp_cloud_token', '');
        $phoneId = getSetting('whatsapp_cloud_phone_id', '');

        if (empty($token) || empty($phoneId)) {
            return ['success' => false, 'message' => 'WhatsApp Cloud API Token veya Phone ID eksik.'];
        }

        // Numarayı E.164 temizle (905XXXXXXXXX)
        $cleanPhone = preg_replace('/[^0-9]/', '', $toPhone);
        if (strlen($cleanPhone) === 10 && strpos($cleanPhone, '5') === 0) {
            $cleanPhone = '90' . $cleanPhone;
        } elseif (strlen($cleanPhone) === 11 && strpos($cleanPhone, '05') === 0) {
            $cleanPhone = '90' . substr($cleanPhone, 1);
        }

        $url = "https://graph.facebook.com/v19.0/{$phoneId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $messageText
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $resultData = json_decode($response, true);

        if ($httpCode === 200 || $httpCode === 201) {
            return ['success' => true, 'data' => $resultData];
        }

        $errMsg = $resultData['error']['message'] ?? ($err ?: 'HTTP Code ' . $httpCode);
        return ['success' => false, 'message' => $errMsg, 'raw' => $resultData];
    }

    /**
     * Bir rezervasyon için WhatsApp bildirimlerini tetikle
     */
    public static function sendBookingNotifications($bookingId) {
        if (empty($bookingId)) {
            return ['success' => false, 'message' => 'Geçersiz rezervasyon ID.'];
        }

        // Tekerrür tetiklemeyi önle
        if (in_array($bookingId, self::$sentBookings)) {
            return [
                'success' => true,
                'message' => 'Bu rezervasyon için bildirim zaten tetiklendi.'
            ];
        }
        self::$sentBookings[] = $bookingId;

        try {
            $db = Database::getConnection();
            $bookingModel = new Booking();

            $booking = $bookingModel->getById($bookingId);
            if (!$booking) {
                return ['success' => false, 'message' => 'Rezervasyon bulunamadı.'];
            }

            // Hizmet adı
            $serviceName = $booking['category_name'];
            if (!empty($booking['subcategory_name'])) {
                $serviceName .= ' - ' . $booking['subcategory_name'];
            } elseif (!empty($booking['package_name'])) {
                $serviceName .= ' (' . $booking['package_name'] . ')';
            }

            // Atanan çalışanlar
            $stmtEmps = $db->prepare("
                SELECT DISTINCT e.id, e.name, e.phone 
                FROM employees e
                INNER JOIN booking_employees be ON e.id = be.employee_id
                INNER JOIN booking_schedule bs ON be.booking_schedule_id = bs.id
                WHERE bs.booking_id = ?
            ");
            $stmtEmps->execute([$bookingId]);
            $employees = $stmtEmps->fetchAll(PDO::FETCH_ASSOC);

            $provider = self::getProviderType();

            // --- 1. META CLOUD API GÖNDERİMİ ---
            if ($provider === 'cloud_api') {
                $sentCount = 0;
                $empNames = array_map(function($e) { return $e['name']; }, $employees);
                $empStr = !empty($empNames) ? implode(', ', $empNames) : 'Ekip Atanıyor';
                $dateFormatted = date('d.m.Y', strtotime($booking['booking_date']));
                $timeSlotStr = translateTimeSlot($booking['booking_time_slot']);

                // Müşteri Mesajı
                $custMsg = "✨ *OLiFA TEMİZLİK BİLDİRİMİ*\n\n"
                    . "Sayın *" . $booking['customer_name'] . "*,\n"
                    . "Rezervasyonunuz başarıyla onaylanmıştır.\n\n"
                    . "🧹 *Hizmet:* " . $serviceName . "\n"
                    . "📅 *Tarih:* " . $dateFormatted . "\n"
                    . "⏰ *Saat Dilimi:* " . $timeSlotStr . "\n"
                    . "👷‍♂️ *Görevli Ekip:* " . $empStr . "\n\n"
                    . "Bizi tercih ettiğiniz için teşekkür ederiz!";

                $custResult = self::sendCloudApiMessage($booking['customer_phone'], $custMsg);
                if ($custResult['success']) $sentCount++;

                // Çalışan Mesajları
                foreach ($employees as $emp) {
                    if (empty($emp['phone'])) continue;

                    $empMsg = "📋 *YENİ GÖREV ATAMASI (OLiFA TEMİZLİK)*\n\n"
                        . "Sayın *" . $emp['name'] . "*,\n"
                        . "Yeni bir temizlik görevi atanmıştır:\n\n"
                        . "📅 *Tarih:* " . $dateFormatted . "\n"
                        . "⏰ *Saat:* " . $timeSlotStr . "\n"
                        . "👤 *Müşteri:* " . $booking['customer_name'] . "\n"
                        . "📞 *Müşteri Tel:* " . $booking['customer_phone'] . "\n"
                        . "📍 *Adres:* " . $booking['customer_address'] . "\n"
                        . "🧹 *Hizmet:* " . $serviceName . "\n\n"
                        . "Lütfen belirtilen saatte adreste bulununuz.";

                    $empResult = self::sendCloudApiMessage($emp['phone'], $empMsg);
                    if ($empResult['success']) $sentCount++;
                }

                return [
                    'success' => true,
                    'message' => "WhatsApp Cloud API ile {$sentCount} mesaj başarıyla iletildi."
                ];
            }

            // --- 2. NODE.JS MİKROSERVİS GÖNDERİMİ ---
            $payload = [
                'booking_id' => (int)$booking['id'],
                'customer' => [
                    'name' => $booking['customer_name'],
                    'phone' => $booking['customer_phone'],
                    'address' => $booking['customer_address'],
                    'location' => $booking['customer_location'] ?? ''
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

            $ch = curl_init(self::getServiceUrl() . '/send-booking');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                return ['success' => true, 'data' => json_decode($response, true)];
            } else {
                return ['success' => false, 'message' => 'Node.js servisi yanıt vermedi: ' . ($curlError ?: 'HTTP Code ' . $httpCode)];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'WhatsApp bildirim hatası: ' . $e->getMessage()];
        }
    }

    /**
     * Yeni müşteri teklifi/randevusu oluşturulduğunda Yöneticiye WhatsApp uyarısı gönder
     */
    public static function sendNewBookingAdminNotification($bookingId) {
        if (empty($bookingId)) return ['success' => false, 'message' => 'Geçersiz ID'];

        $adminPhone = getSetting('admin_whatsapp_phone', '');
        if (empty($adminPhone)) {
            $adminPhone = getSetting('whatsapp', getSetting('phone', ''));
        }

        if (empty($adminPhone)) {
            return ['success' => false, 'message' => 'Yönetici bildirim numarası tanımlanmamış.'];
        }

        try {
            $bookingModel = new Booking();
            $booking = $bookingModel->getById($bookingId);
            if (!$booking) return ['success' => false, 'message' => 'Rezervasyon bulunamadı.'];

            $serviceName = $booking['category_name'];
            if (!empty($booking['subcategory_name'])) {
                $serviceName .= ' - ' . $booking['subcategory_name'];
            } elseif (!empty($booking['package_name'])) {
                $serviceName .= ' (' . $booking['package_name'] . ')';
            }

            $dateFormatted = date('d.m.Y', strtotime($booking['booking_date']));
            $timeSlotStr = translateTimeSlot($booking['booking_time_slot']);
            $locationStr = '';
            if (!empty($booking['customer_location'])) {
                $locUrl = (strpos($booking['customer_location'], 'http') === 0)
                    ? $booking['customer_location']
                    : "https://www.google.com/maps/search/?api=1&query=" . urlencode($booking['customer_location']);
                $locationStr = "\n📍 *Harita Konumu:* " . $locUrl;
            }

            $adminMsg = "🚨 *YENİ MÜŞTERİ RANDEVU TALEBİ / TEKLİFİ!*\n\n"
                . "Sayın Yönetici, web sitenizden yeni bir randevu talebi oluşturuldu:\n\n"
                . "👤 *Müşteri Adı:* " . $booking['customer_name'] . "\n"
                . "📞 *Müşteri Tel:* " . $booking['customer_phone'] . "\n"
                . "🧹 *Hizmet:* " . $serviceName . "\n"
                . "📅 *Talep Tarihi:* " . $dateFormatted . "\n"
                . "⏰ *Saat Dilimi:* " . $timeSlotStr . "\n"
                . "🏠 *Adres:* " . $booking['customer_address']
                . $locationStr . "\n"
                . "💰 *Hesaplanan Tutar:* " . $totalPriceStr . "\n\n"
                . "⚠️ *LÜTFEN SİSTEMDEN ONAYLAYIN:*\n"
                . "Yönetim paneline girerek personelleri atayınız ve teklifi onaylayınız.\n\n"
                . "🔗 *Onaylama Linki:*\n" . $openLink;

            $provider = self::getProviderType();
            if ($provider === 'cloud_api') {
                return self::sendCloudApiMessage($adminPhone, $adminMsg);
            } else {
                $ch = curl_init(self::getServiceUrl() . '/send-admin-alert');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'phone' => $adminPhone,
                    'message' => $adminMsg
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                $response = curl_exec($ch);
                curl_close($ch);

                return ['success' => true];
            }

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
