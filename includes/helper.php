<?php
/**
 * OLiFA Temizlik - Yardımcı Fonksiyonlar ve Güvenlik Filtreleri
 */

/**
 * XSS korumalı çıktı yazdırma
 */
function e($value) {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Sayfayı yönlendir
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Para birimini Türkçe formatta biçimlendir
 */
function formatPrice($price) {
    $priceVal = (float)$price;
    // Eğer kuruş kısmı 00 ise küsuratsız göster
    if (floor($priceVal) == $priceVal) {
        return number_format($priceVal, 0, ',', '.') . ' ₺';
    }
    return number_format($priceVal, 2, ',', '.') . ' ₺';
}

/**
 * İngilizce gün isimlerini Türkçeye çevir
 */
function translateDay($dayName) {
    $days = [
        'Monday'    => 'Pazartesi',
        'Tuesday'   => 'Salı',
        'Wednesday' => 'Çarşamba',
        'Thursday'  => 'Perşembe',
        'Friday'    => 'Cuma',
        'Saturday'  => 'Cumartesi',
        'Sunday'    => 'Pazar'
    ];
    return $days[$dayName] ?? $dayName;
}

/**
 * Zaman dilimlerini Türkçeye çevir
 */
function translateTimeSlot($slot) {
    $slots = [
        '08-17' => 'Tam Gün (08:00 - 17:00)',
        '08-12' => 'Sabah (08:00 - 12:00)',
        '13-17' => 'Öğleden Sonra (13:00 - 17:00)'
    ];
    return $slots[$slot] ?? $slot;
}

/**
 * SEO Meta etiketlerini dinamik olarak yazdır
 */
function renderSeoTags($pageTitle = '', $pageDesc = '', $pageKeywords = '') {
    $defaultTitle = getSetting('site_title', 'OLiFA Temizlik Maraş');
    $defaultDesc = getSetting('site_description', 'Profesyonel Temizlik Hizmetleri');
    $defaultKeywords = getSetting('site_keywords', 'temizlik, ev temizliği, ofis temizliği');
    
    $title = $pageTitle ? $pageTitle . ' | ' . getSetting('company_name', 'OLiFA') : $defaultTitle;
    $desc = $pageDesc ?: $defaultDesc;
    $keywords = $pageKeywords ?: $defaultKeywords;
    
    echo '    <title>' . e($title) . '</title>' . PHP_EOL;
    echo '    <meta name="description" content="' . e($desc) . '">' . PHP_EOL;
    echo '    <meta name="keywords" content="' . e($keywords) . '">' . PHP_EOL;
    echo '    <meta property="og:title" content="' . e($title) . '">' . PHP_EOL;
    echo '    <meta property="og:description" content="' . e($desc) . '">' . PHP_EOL;
    echo '    <meta property="og:type" content="website">' . PHP_EOL;
    echo '    <meta property="og:url" content="http://' . ($_SERVER['HTTP_HOST'] ?? 'www.olifatemizlikmaras.com.tr') . ($_SERVER['REQUEST_URI'] ?? '') . '">' . PHP_EOL;
}

/**
 * Menü öğesinin aktifliğini kontrol et
 */
function isMenuActive($pageName) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return ($currentPage === $pageName) ? 'active' : '';
}

/**
 * Rastgele ve güvenli CSRF token inputu yazdır
 */
function csrfInput() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    echo '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}
