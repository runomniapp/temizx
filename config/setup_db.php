<?php
require_once __DIR__ . '/db.php';

echo "OLiFA Temizlik Veritabanı Kurulumu Başlatılıyor...\n";

try {
    // 1. users
    $pdo->exec("DROP TABLE IF EXISTS booking_employees");
    $pdo->exec("DROP TABLE IF EXISTS booking_schedule");
    $pdo->exec("DROP TABLE IF EXISTS bookings");
    $pdo->exec("DROP TABLE IF EXISTS category_packages");
    $pdo->exec("DROP TABLE IF EXISTS packages");
    $pdo->exec("DROP TABLE IF EXISTS subcategories");
    $pdo->exec("DROP TABLE IF EXISTS categories");
    $pdo->exec("DROP TABLE IF EXISTS employees");
    $pdo->exec("DROP TABLE IF EXISTS sliders");
    $pdo->exec("DROP TABLE IF EXISTS reviews");
    $pdo->exec("DROP TABLE IF EXISTS faqs");
    $pdo->exec("DROP TABLE IF EXISTS settings");
    $pdo->exec("DROP TABLE IF EXISTS users");

    echo "Eski tablolar temizlendi.\n";

    // 2. Tablo Tanımlamaları
    
    // Users
    $pdo->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL,
        role VARCHAR(20) DEFAULT 'admin',
        status TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Categories
    $pdo->exec("CREATE TABLE categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) UNIQUE NOT NULL,
        icon VARCHAR(50) NOT NULL,
        image VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        color VARCHAR(20) DEFAULT '#0066FF',
        pricing_type VARCHAR(20) DEFAULT 'category', -- 'category' or 'subcategory'
        price DECIMAL(10,2) DEFAULT 0.00,
        is_subscription_active TINYINT DEFAULT 0,
        status TINYINT DEFAULT 1,
        order_num INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Subcategories
    $pdo->exec("CREATE TABLE subcategories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        status TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Packages
    $pdo->exec("CREATE TABLE packages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        duration_weeks INT NOT NULL,
        normal_price DECIMAL(10,2) NOT NULL,
        discounted_price DECIMAL(10,2) NOT NULL,
        status TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Category Packages Link Table
    $pdo->exec("CREATE TABLE category_packages (
        category_id INT NOT NULL,
        package_id INT NOT NULL,
        PRIMARY KEY (category_id, package_id),
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
        FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Employees
    $pdo->exec("CREATE TABLE employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        photo VARCHAR(255) DEFAULT 'default_employee.png',
        status VARCHAR(20) DEFAULT 'active', -- 'active', 'inactive', 'on_leave'
        work_hours_start TIME DEFAULT '08:00:00',
        work_hours_end TIME DEFAULT '17:00:00',
        off_days VARCHAR(100) DEFAULT 'Sunday',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Bookings
    $pdo->exec("CREATE TABLE bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        subcategory_id INT DEFAULT NULL,
        package_id INT DEFAULT NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        customer_address TEXT NOT NULL,
        customer_location VARCHAR(255) DEFAULT NULL,
        customer_email VARCHAR(100) DEFAULT NULL,
        booking_date DATE NOT NULL,
        booking_time_slot VARCHAR(20) NOT NULL, -- '08-17', '08-12', '13-17'
        person_count INT DEFAULT 1,
        total_price DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'confirmed', 'completed', 'cancelled'
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id),
        FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL,
        FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Booking Schedule (Weekly occurrences)
    $pdo->exec("CREATE TABLE booking_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        date DATE NOT NULL,
        time_slot VARCHAR(20) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'completed', 'cancelled'
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Booking Employees
    $pdo->exec("CREATE TABLE booking_employees (
        booking_schedule_id INT NOT NULL,
        employee_id INT NOT NULL,
        PRIMARY KEY (booking_schedule_id, employee_id),
        FOREIGN KEY (booking_schedule_id) REFERENCES booking_schedule(id) ON DELETE CASCADE,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Sliders
    $pdo->exec("CREATE TABLE sliders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        subtitle VARCHAR(255) NOT NULL,
        button_text VARCHAR(50) DEFAULT 'Teklif Al',
        button_url VARCHAR(255) DEFAULT '#teklif-al',
        order_num INT DEFAULT 0,
        status TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Reviews
    $pdo->exec("CREATE TABLE reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        comment TEXT NOT NULL,
        rating INT DEFAULT 5,
        status TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // FAQs
    $pdo->exec("CREATE TABLE faqs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question VARCHAR(255) NOT NULL,
        answer TEXT NOT NULL,
        order_num INT DEFAULT 0,
        status TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Settings
    $pdo->exec("CREATE TABLE settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "Tüm tablolar başarıyla oluşturuldu.\n";

    // 3. DUMMY VERİLERİN EKLEMESİ
    
    // Admin Kullanıcısı (admin / olifa123*)
    $adminPassword = password_hash('olifa123*', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, role, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', $adminPassword, 'admin@olifatemizlikmaras.com.tr', 'admin', 1]);
    echo "Yönetici kullanıcısı oluşturuldu (admin / olifa123*).\n";

    // Kategoriler
    $categories = [
        ['Ev Temizliği', 'ev-temizligi', 'home', 'assets/img/ev-temizligi.jpg', 'Evlerinizi pırıl pırıl yapıyor, size temiz ve ferah yaşam alanları sunuyoruz. Düzenli abonelik indirimlerinden faydalanabilirsiniz.', '#0066FF', 'subcategory', 0.00, 1, 1, 0],
        ['Ofis Temizliği', 'ofis-temizligi', 'briefcase', 'assets/img/ofis-temizligi.jpg', 'Verimli bir çalışma ortamı için profesyonel iş yeri ve ofis temizliği hizmetleri sunuyoruz.', '#00D2FF', 'subcategory', 0.00, 1, 1, 1],
        ['İnşaat Sonrası Temizlik', 'insaat-sonrasi', 'hammer', 'assets/img/insaat-sonrasi.jpg', 'İnşaat ve tadilat sonrası toz, harç ve boya kalıntılarını profesyonel ekibimiz ve ekipmanlarımızla sıfırlıyoruz.', '#FF9F43', 'category', 2000.00, 0, 1, 2],
        ['Villa Temizliği', 'villa-temizligi', 'building', 'assets/img/villa-temizligi.jpg', 'Geniş yaşam alanları için detaylı, hijyenik ve uzman ekibimizle villa temizliği çözümleri.', '#10AC84', 'category', 3000.00, 0, 1, 3],
        ['Cam Temizliği', 'cam-temizligi', 'eye', 'assets/img/cam-temizligi.jpg', 'Ev ve iş yerlerinizin iç-dış cam ve çerçevelerini lekesiz bir şekilde pırıl pırıl temizliyoruz.', '#54A0FF', 'category', 800.00, 0, 1, 4],
        ['Koltuk & Yatak Temizliği', 'koltuk-temizligi', 'coffee', 'assets/img/koltuk-temizligi.jpg', 'Koltuk, yatak ve halılarınızı yerinde profesyonel vakumlu yıkama makinelerimizle derinlemesine temizliyoruz.', '#5F27CD', 'category', 1000.00, 0, 1, 5]
    ];
    $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, image, description, color, pricing_type, price, is_subscription_active, status, order_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($categories as $cat) {
        $stmt->execute($cat);
    }
    echo "Kategoriler eklendi.\n";

    // Alt Kategoriler
    $catEvId = $pdo->query("SELECT id FROM categories WHERE slug = 'ev-temizligi'")->fetchColumn();
    $catOfisId = $pdo->query("SELECT id FROM categories WHERE slug = 'ofis-temizligi'")->fetchColumn();

    $subcategories = [
        [$catEvId, 'Tam Gün (08-17)', 1500.00],
        [$catEvId, 'Yarım Gün - Sabah (08-12)', 900.00],
        [$catEvId, 'Yarım Gün - Öğleden Sonra (13-17)', 900.00],
        [$catOfisId, 'Tam Gün (08-17)', 1800.00],
        [$catOfisId, 'Yarım Gün - Sabah (08-12)', 1100.00],
        [$catOfisId, 'Yarım Gün - Öğleden Sonra (13-17)', 1100.00]
    ];
    $stmt = $pdo->prepare("INSERT INTO subcategories (category_id, name, price, status) VALUES (?, ?, ?, 1)");
    foreach ($subcategories as $sub) {
        $stmt->execute($sub);
    }
    echo "Alt kategoriler eklendi.\n";

    // Paketler
    $packages = [
        ['4\'lü Paket', 'Haftada 1 kez olmak üzere 4 hafta boyunca düzenli temizlik hizmeti sunar.', 4, 6000.00, 5200.00],
        ['8\'li Paket', 'Haftada 2 kez olmak üzere 4 hafta veya haftada 1 kez olmak üzere 8 hafta düzenli temizlik hizmeti.', 8, 12000.00, 9800.00],
        ['12\'li Paket', 'Haftada 3 kez olmak üzere 4 hafta veya haftada 1 kez olmak üzere 12 hafta boyunca profesyonel temizlik.', 12, 18000.00, 14500.00]
    ];
    $stmt = $pdo->prepare("INSERT INTO packages (name, description, duration_weeks, normal_price, discounted_price, status) VALUES (?, ?, ?, ?, ?, 1)");
    foreach ($packages as $pkg) {
        $stmt->execute($pkg);
    }
    echo "Paketler eklendi.\n";

    // Kategori Paket Eşleştirmeleri
    $pkgIds = $pdo->query("SELECT id FROM packages")->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->prepare("INSERT INTO category_packages (category_id, package_id) VALUES (?, ?)");
    foreach ([$catEvId, $catOfisId] as $catId) {
        foreach ($pkgIds as $pkgId) {
            $stmt->execute([$catId, $pkgId]);
        }
    }
    echo "Kategori paket eşleştirmeleri tamamlandı.\n";

    // Personeller
    $employees = [
        ['Ayşe Yılmaz', '+90 555 000 00 01', 'emp1.jpg', 'active', '08:00:00', '17:00:00', 'Sunday'],
        ['Fatma Demir', '+90 555 000 00 02', 'emp2.jpg', 'active', '08:00:00', '17:00:00', 'Sunday'],
        ['Hatice Kaya', '+90 555 000 00 03', 'emp3.jpg', 'active', '08:00:00', '17:00:00', 'Sunday'],
        ['Elif Çelik', '+90 555 000 00 04', 'emp4.jpg', 'active', '08:00:00', '17:00:00', 'Sunday'],
        ['Merve Yıldız', '+90 555 000 00 05', 'emp5.jpg', 'active', '08:00:00', '17:00:00', 'Sunday'],
        ['Zeynep Şahin', '+90 555 000 00 06', 'emp6.jpg', 'active', '08:00:00', '17:00:00', 'Sunday'],
        ['Emine Bulut', '+90 555 000 00 07', 'emp7.jpg', 'active', '08:00:00', '17:00:00', 'Sunday'],
        ['Nur Koç', '+90 555 000 00 08', 'emp8.jpg', 'active', '08:00:00', '17:00:00', 'Sunday']
    ];
    $stmt = $pdo->prepare("INSERT INTO employees (name, phone, photo, status, work_hours_start, work_hours_end, off_days) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($employees as $emp) {
        $stmt->execute($emp);
    }
    echo "Personeller eklendi.\n";

    // Ayarlar (Settings)
    $settings = [
        'site_title' => 'OLiFA Temizlik Maraş - Premium Temizlik Otomasyonu',
        'site_description' => 'Kahramanmaraş\'ın en kaliteli ve profesyonel temizlik şirketi. Ev, ofis, inşaat sonrası, villa ve yerinde koltuk temizliği.',
        'site_keywords' => 'temizlik maraş, kahramanmaraş temizlik, ev temizliği, ofis temizliği, koltuk yıkama, inşaat temizliği maraş',
        'company_name' => 'OLiFA Temizlik Maraş',
        'phone' => '+90 555 123 45 67',
        'whatsapp' => '+90 555 123 45 67',
        'email' => 'info@olifatemizlikmaras.com.tr',
        'address' => 'Haydar Aliyev Bulvarı, No:46/A, Kahramanmaraş',
        'maps_iframe' => '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d50456.88370503524!2d36.87413642167969!3d37.5898235!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x152bc42784cf82e9%3A0x67db238814fc1424!2sKahramanmara%C5%9F%20Merkez!5e0!3m2!1str!2str!4v1700000000000!5m2!1str!2str" width="100%" height="450" style="border:0; border-radius: 20px;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>',
        'work_hours' => 'Pazartesi - Cumartesi: 08:00 - 18:00, Pazar: Kapalı',
        'facebook' => 'https://facebook.com/olifatemizlik',
        'instagram' => 'https://instagram.com/olifatemizlik',
        'logo_path' => 'uploads/system/logo.png',
        'favicon_path' => 'uploads/system/logo.png',
        'footer_text' => '© 2026 OLiFA Temizlik Maraş. Tüm Hakları Saklıdır.'
    ];
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $key => $val) {
        $stmt->execute([$key, $val]);
    }
    echo "Sistem ayarları eklendi.\n";

    // Slider
    $sliders = [
        ['assets/img/slider1.jpg', 'Kusursuz Temizlik, Profesyonel Ekipler', 'Evinizi ve ofisinizi OLiFA güvencesiyle pırıl pırıl temizliyoruz.', 'Teklif Al', '#teklif-al', 1, 1],
        ['assets/img/slider2.jpg', 'Düzenli Aboneliklerle Büyük İndirimler', 'Haftalık veya aylık periyodik ev-ofis temizliği paketlerimizle bütçenizi koruyun.', 'Paketleri İncele', '#paketler', 2, 1]
    ];
    $stmt = $pdo->prepare("INSERT INTO sliders (image, title, subtitle, button_text, button_url, order_num, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($sliders as $sld) {
        $stmt->execute($sld);
    }
    echo "Slider içerikleri eklendi.\n";

    // Yorumlar
    $reviews = [
        ['Ahmet Y.', 'Ev temizliği için aldığım 4\'lü paketten çok memnun kaldım. Gelen personeller (Ayşe ve Fatma hanım) işlerinde son derece titizdiler. Kesinlikle tavsiye ederim.', 5, 1],
        ['Zehra K.', 'Ofisimizin temizliğini haftalık olarak OLiFA ekibine yaptırıyoruz. Hem güvenilirler hem de harika temizliyorlar. Sistem üzerinden takvimi de görebiliyoruz.', 5, 1],
        ['Kemal A.', 'İnşaat tadilatı bittikten sonra ev batmıştı. 3 kişilik ekip gelip 1 günde evi oturulacak hale getirdi. Ellerinize sağlık.', 5, 1]
    ];
    $stmt = $pdo->prepare("INSERT INTO reviews (name, comment, rating, status) VALUES (?, ?, ?, ?)");
    foreach ($reviews as $rev) {
        $stmt->execute($rev);
    }
    echo "Müşteri yorumları eklendi.\n";

    // SSS
    $faqs = [
        ['Temizlik malzemelerini siz mi getiriyorsunuz?', 'Evet, tüm profesyonel temizlik malzemeleri, deterjanlar ve gerekli teknik ekipmanlar ekibimiz tarafından yanımızda getirilmektedir. Ekstra bir malzeme tedarik etmenize gerek yoktur.', 1, 1],
        ['Personelleriniz sigortalı ve güvenilir mi?', 'Evet, çalışanlarımızın tamamı SGK güvencesi altındadır, tüm güvenlik soruşturmaları yapılmış, iş sağlığı ve güvenliği sertifikalarına sahip deneyimli personellerdir.', 2, 1],
        ['Randevuyu erteleyebilir veya iptal edebilir miyim?', 'Rezervasyon saatinizden 24 saat öncesine kadar hiçbir ücret ödemeden randevunuzu erteleyebilir veya iptal edebilirsiniz. Müşteri hizmetlerimiz üzerinden kolayca işlem yapabilirsiniz.', 3, 1]
    ];
    $stmt = $pdo->prepare("INSERT INTO faqs (question, answer, order_num, status) VALUES (?, ?, ?, ?)");
    foreach ($faqs as $faq) {
        $stmt->execute($faq);
    }
    echo "Sıkça sorulan sorular eklendi.\n";

    // 4. ÖRNEK TEKLİFLER VE TAKVİM İŞLERİ
    // Ev Temizliği (Alt Kategori: Tam Gün)
    $subEvId = $pdo->query("SELECT id FROM subcategories WHERE category_id = $catEvId AND name LIKE 'Tam%'")->fetchColumn();
    // Ofis Temizliği (Alt Kategori: Yarım Gün Sabah)
    $subOfisId = $pdo->query("SELECT id FROM subcategories WHERE category_id = $catOfisId AND name LIKE '%Sabah%'")->fetchColumn();

    $pkg4Id = $pdo->query("SELECT id FROM packages WHERE name = '4\'lü Paket'")->fetchColumn();

    // Rezervasyon 1: Bireysel, Tek Seferlik, İnşaat Sonrası, pending
    $stmt = $pdo->prepare("INSERT INTO bookings (category_id, subcategory_id, package_id, customer_name, customer_phone, customer_address, customer_email, booking_date, booking_time_slot, person_count, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $catEvId + 2, // İnşaat Sonrası
        null,
        null,
        'Murat Çelik',
        '+90 555 123 45 88',
        'Yenimahalle, Şehitler Caddesi, No:12 Daire:4, Kahramanmaraş',
        'murat@outlook.com',
        date('Y-m-d', strtotime('+1 day')),
        '08-17',
        3,
        2000.00,
        'pending'
    ]);
    $booking1Id = $pdo->lastInsertId();

    // Schedule for Booking 1 (one-time)
    $stmt = $pdo->prepare("INSERT INTO booking_schedule (booking_id, date, time_slot, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$booking1Id, date('Y-m-d', strtotime('+1 day')), '08-17', 'pending']);

    // Rezervasyon 2: Onaylanmış, Ev Temizliği, Tam Gün, Confirmed, Ayşe ve Fatma atanmış
    $stmt = $pdo->prepare("INSERT INTO bookings (category_id, subcategory_id, package_id, customer_name, customer_phone, customer_address, customer_email, booking_date, booking_time_slot, person_count, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $catEvId,
        $subEvId,
        null,
        'Selma Güler',
        '+90 555 987 65 43',
        'Mimar Sinan Mahallesi, 12. Sokak, Güler Apartmanı No:5, Kahramanmaraş',
        'selma@gmail.com',
        date('Y-m-d', strtotime('+2 days')),
        '08-17',
        2,
        1500.00,
        'confirmed'
    ]);
    $booking2Id = $pdo->lastInsertId();

    // Schedule for Booking 2
    $stmt = $pdo->prepare("INSERT INTO booking_schedule (booking_id, date, time_slot, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$booking2Id, date('Y-m-d', strtotime('+2 days')), '08-17', 'confirmed']);
    $sch2Id = $pdo->lastInsertId();

    // Assign Ayşe (ID 1) and Fatma (ID 2)
    $stmt = $pdo->prepare("INSERT INTO booking_employees (booking_schedule_id, employee_id) VALUES (?, ?)");
    $stmt->execute([$sch2Id, 1]);
    $stmt->execute([$sch2Id, 2]);

    // Rezervasyon 3: Abonelikli, Ofis Temizliği, 4'lü paket, Confirmed, 4 haftalık program, Elif ve Merve atanmış
    $stmt = $pdo->prepare("INSERT INTO bookings (category_id, subcategory_id, package_id, customer_name, customer_phone, customer_address, customer_email, booking_date, booking_time_slot, person_count, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $catOfisId,
        $subOfisId,
        $pkg4Id,
        'Apex Yazılım Ltd. Şti.',
        '+90 344 222 11 00',
        'Teknokent A Blok No:108, Kahramanmaraş',
        'info@apexyazilim.com',
        date('Y-m-d', strtotime('+3 days')),
        '08-12',
        2,
        5200.00,
        'confirmed'
    ]);
    $booking3Id = $pdo->lastInsertId();

    // Create 4 weekly schedule items for the subscription
    for ($i = 0; $i < 4; $i++) {
        $date = date('Y-m-d', strtotime('+' . (3 + ($i * 7)) . ' days'));
        $stmt = $pdo->prepare("INSERT INTO booking_schedule (booking_id, date, time_slot, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$booking3Id, $date, '08-12', 'confirmed']);
        $schId = $pdo->lastInsertId();

        // Assign Elif (ID 4) and Merve (ID 5)
        $stmt = $pdo->prepare("INSERT INTO booking_employees (booking_schedule_id, employee_id) VALUES (?, ?)");
        $stmt->execute([$schId, 4]);
        $stmt->execute([$schId, 5]);
    }

    echo "Örnek teklifler, abonelikler ve takvim işleri oluşturuldu.\n";
    echo "\nVeritabanı başarıyla kuruldu ve tüm veriler eklendi!\n";

} catch (PDOException $e) {
    die("\nKurulum Sırasında Hata Oluştu: " . $e->getMessage() . "\n");
}
