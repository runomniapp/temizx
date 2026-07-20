<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helper.php';

$compName = getSetting('company_name', 'OLiFA Temizlik');
$phone = getSetting('phone', '');
$whatsapp = getSetting('whatsapp', '');
$email = getSetting('email', '');
$address = getSetting('address', '');
$workHours = getSetting('work_hours', '');
$facebook = getSetting('facebook', '#');
$instagram = getSetting('instagram', '#');
$footerText = getSetting('footer_text', '© 2026 OLiFA Temizlik. Tüm Hakları Saklıdır.');
$logoPath = getSetting('logo_path', 'assets/img/olifa_logo.png');

// En popüler 4 kategoriyi getir
$db = Database::getConnection();
$footerCats = $db->query("SELECT * FROM categories WHERE status = 1 ORDER BY order_num ASC LIMIT 4")->fetchAll();
?>

    <!-- Footer -->
    <footer>
        <div class="container footer-grid">
            <!-- Company Info Column -->
            <div>
                <img src="<?php echo e($logoPath); ?>" alt="<?php echo e($compName); ?>" style="height: 50px; margin-bottom: 20px;">
                <p style="margin-bottom: 25px; font-size: 0.95rem;"><?php echo e(getSetting('site_description', 'Kahramanmaraş\'ın en kaliteli temizlik hizmetleri.')); ?></p>
                <div style="display: flex; gap: 15px;">
                    <?php if ($facebook && $facebook !== '#'): ?>
                        <a href="<?php echo e($facebook); ?>" target="_blank" class="contact-btn" style="width: 36px; height: 36px; font-size: 0.9rem;" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <?php endif; ?>
                    <?php if ($instagram && $instagram !== '#'): ?>
                        <a href="<?php echo e($instagram); ?>" target="_blank" class="contact-btn" style="width: 36px; height: 36px; font-size: 0.9rem;" title="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Services Column -->
            <div>
                <h3 class="footer-col-title">Hizmetlerimiz</h3>
                <ul class="footer-links">
                    <?php foreach ($footerCats as $cat): ?>
                        <li><a href="index.php#hizmetler" class="footer-link"><?php echo e($cat['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Quick Links Column -->
            <div>
                <h3 class="footer-col-title">Hızlı Menü</h3>
                <ul class="footer-links">
                    <li><a href="index.php" class="footer-link">Ana Sayfa</a></li>
                    <li><a href="index.php#hakkimizda" class="footer-link">Hakkımızda</a></li>
                    <li><a href="index.php#hizmetler" class="footer-link">Hizmetler</a></li>
                    <li><a href="index.php#paketler" class="footer-link">Paketler</a></li>
                    <li><a href="index.php#sss" class="footer-link">S.S.S.</a></li>
                </ul>
            </div>
            
            <!-- Contact Column -->
            <div>
                <h3 class="footer-col-title">İletişim Bilgileri</h3>
                <ul class="footer-links" style="font-size: 0.95rem;">
                    <?php if ($address): ?>
                        <li style="color: var(--text-muted); display: flex; gap: 10px; align-items: flex-start;">
                            <i class="fa-solid fa-location-dot" style="margin-top: 5px; color: var(--primary);"></i>
                            <span><?php echo e($address); ?></span>
                        </li>
                    <?php endif; ?>
                    <?php if ($phone): ?>
                        <li>
                            <a href="tel:<?php echo e(str_replace(' ', '', $phone)); ?>" class="footer-link" style="display: flex; gap: 10px; align-items: center;">
                                <i class="fa-solid fa-phone" style="color: var(--primary);"></i>
                                <span><?php echo e($phone); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($email): ?>
                        <li>
                            <a href="mailto:<?php echo e($email); ?>" class="footer-link" style="display: flex; gap: 10px; align-items: center;">
                                <i class="fa-solid fa-envelope" style="color: var(--primary);"></i>
                                <span><?php echo e($email); ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($workHours): ?>
                        <li style="color: var(--text-muted); display: flex; gap: 10px; align-items: center;">
                            <i class="fa-solid fa-clock" style="color: var(--primary);"></i>
                            <span><?php echo e($workHours); ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <div class="container footer-bottom">
            <p><?php echo e($footerText); ?></p>
            <p style="font-size: 0.8rem;">Developed with ❤️ by Antigravity</p>
        </div>
    </footer>

    <!-- Floating WhatsApp Widget -->
    <?php if ($whatsapp): ?>
        <a href="https://wa.me/<?php echo e(preg_replace('/[^0-9]/', '', $whatsapp)); ?>" target="_blank" class="floating-whatsapp" title="WhatsApp Destek Hattı">
            <i class="fa-brands fa-whatsapp"></i>
        </a>
    <?php endif; ?>

</body>
</html>
