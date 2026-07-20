<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();

// Zaten giriş yapmışsa yönlendir
if ($auth->check()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verifyCsrfToken($csrf)) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if ($username && $password) {
            $result = $auth->login($username, $password);
            if ($result['success']) {
                redirect('index.php');
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Lütfen tüm alanları doldurun.';
        }
    } else {
        $error = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
    }
}

$logoPath = '../' . getSetting('logo_path', 'assets/img/olifa_logo.png');
$compName = getSetting('company_name', 'OLiFA Temizlik');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli Girişi | <?php echo e($compName); ?></title>
    <link rel="icon" type="image/png" href="<?php echo e($logoPath); ?>">
    
    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <style>
        body {
            background-color: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            padding: 50px 40px;
            background-color: var(--card-bg);
            border-radius: var(--radius-card);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <img src="<?php echo e($logoPath); ?>" alt="<?php echo e($compName); ?>" style="height: 60px; margin-bottom: 30px;">
        
        <h2 style="font-size: 1.45rem; font-weight: 800; margin-bottom: 8px;">Yönetim Paneli Girişi</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 35px;">Lütfen yönetici bilgilerinizi girerek oturum açın.</p>
        
        <?php if ($error): ?>
            <div style="background-color: #fef2f2; color: var(--danger); padding: 12px 16px; border-radius: 12px; font-weight: 600; font-size: 0.85rem; margin-bottom: 25px; display: flex; gap: 8px; align-items: center; text-align: left;">
                <i class="fa-solid fa-triangle-exclamation" style="flex-shrink: 0;"></i> 
                <span><?php echo e($error); ?></span>
            </div>
        <?php endif; ?>
        
        <form action="login.php" method="POST" style="text-align: left;">
            <?php csrfInput(); ?>
            
            <div class="form-group">
                <label class="form-label" for="username">Kullanıcı Adı</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-user" style="position: absolute; left: 24px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.95rem;"></i>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Kullanıcı adı" style="padding-left: 55px;" required>
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 35px;">
                <label class="form-label" for="password">Şifre</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 24px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.95rem;"></i>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Şifre" style="padding-left: 55px;" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px 24px;">Giriş Yap</button>
        </form>
    </div>

</body>
</html>
