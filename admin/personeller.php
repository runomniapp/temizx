<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Setting.php'; // upload helper için

$employeeModel = new Employee();
$msg = '';

// Personel Ekleme / Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verifyCsrfToken($csrf)) {
        $id = (int)($_POST['id'] ?? 0);
        
        // Haftalık İzin günlerini birleştir (Örn: Sunday, Saturday)
        $offDaysArr = $_POST['off_days'] ?? [];
        $offDaysStr = implode(', ', $offDaysArr);
        
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'status' => trim($_POST['status'] ?? 'active'),
            'work_hours_start' => trim($_POST['work_hours_start'] ?? '08:00:00'),
            'work_hours_end' => trim($_POST['work_hours_end'] ?? '17:00:00'),
            'off_days' => $offDaysStr,
            'employee_type' => trim($_POST['employee_type'] ?? 'general'),
            'daily_wage_full' => (float)($_POST['daily_wage_full'] ?? 0.00),
            'daily_wage_half' => (float)($_POST['daily_wage_half'] ?? 0.00)
        ];
        
        // Fotoğraf Yükleme
        if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
            $uploadRes = Setting::uploadFile($_FILES['photo_file'], 'employees');
            if ($uploadRes['success']) {
                $data['photo'] = $uploadRes['path'];
            } else {
                $msg .= '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 20px;">Fotoğraf Hatası: ' . e($uploadRes['message']) . '</div>';
            }
        }
        
        if ($id > 0) {
            // Mevcut resmi koru yenisi seçilmediyse
            if (!isset($data['photo'])) {
                $currentEmp = $employeeModel->getById($id);
                $data['photo'] = $currentEmp['photo'];
            }
            
            if ($employeeModel->update($id, $data)) {
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Personel bilgileri başarıyla güncellendi!</div>';
            }
        } else {
            if (!isset($data['photo'])) {
                $data['photo'] = 'assets/img/default_employee.png'; // varsayılan
            }
            
            if ($employeeModel->create($data)) {
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Personel başarıyla eklendi!</div>';
            }
        }
    }
}

// Personel Silme
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($employeeModel->delete($id)) {
        $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-xmark"></i> Personel silindi.</div>';
    }
}

$employees = $employeeModel->getAll(false);
?>

<div style="max-width: 1200px; margin: 0 auto;">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 5px;">Personel Yönetimi</h2>
            <p style="color: var(--text-muted);">Sistemdeki temizlik görevlilerini, çalışma saatlerini, izin günlerini ve durumlarını yönetin.</p>
        </div>
        <button onclick="openEmployeeModal()" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Yeni Personel Ekle</button>
    </div>
    
    <?php echo $msg; ?>
    
    <!-- Table -->
    <div class="admin-table-wrapper">
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Fotoğraf</th>
                        <th>Adı Soyadı</th>
                        <th>Telefon</th>
                        <th>Çalışma Saatleri</th>
                        <th>Yövmiye (Tam/Yarım)</th>
                        <th>İzin Günleri</th>
                        <th>Durum</th>
                        <th style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $row): ?>
                        <tr>
                            <td>
                                 <?php 
                                 $photoPath = '../assets/img/profile.png';
                                 if (!empty($row['photo']) && strpos($row['photo'], 'default_') === false) {
                                     $checkPath = '../' . $row['photo'];
                                     if (file_exists($checkPath)) {
                                         $photoPath = $checkPath;
                                     }
                                 }
                                 ?>
                                 <div class="user-avatar" style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; background-color: #f1f5f9; padding: 0; border: 1px solid var(--border);">
                                     <img src="<?php echo e($photoPath); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                 </div>
                            </td>
                            <td>
                                <strong><?php echo e($row['name']); ?></strong>
                               <br><span style="font-size: 0.72rem; color: #64748b; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; margin-top: 3px;"><?php echo $row['employee_type'] === 'furniture' ? '<i class="fa-solid fa-couch" style="color: #8b5cf6;"></i> Koltuk & Yatak Yıkama' : '<i class="fa-solid fa-house-chimney" style="color: #3b82f6;"></i> Genel Temizlik'; ?></span>
                            </td>
                            <td><code><?php echo e(formatPhoneDisplay($row['phone'])); ?></code></td>
                            <td><?php echo date('H:i', strtotime($row['work_hours_start'])); ?> - <?php echo date('H:i', strtotime($row['work_hours_end'])); ?></td>
                            <td>
                                <div>Tam: <strong><?php echo number_format($row['daily_wage_full'] ?? 0, 0, ',', '.'); ?> ₺</strong></div>
                                <div style="font-size: 0.72rem; color: #64748b; margin-top: 2px;">Yarım: <strong><?php echo number_format($row['daily_wage_half'] ?? 0, 0, ',', '.'); ?> ₺</strong></div>
                            </td>
                            <td>
                                <?php 
                                $offs = explode(',', $row['off_days']);
                                $translatedOffs = array_map(function($d) {
                                    return translateDay(trim($d));
                                }, $offs);
                                echo implode(', ', $translatedOffs);
                                ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo e($row['status']); ?>">
                                    <?php 
                                    if ($row['status'] === 'active') echo 'Aktif';
                                    else if ($row['status'] === 'inactive') echo 'Pasif';
                                    else if ($row['status'] === 'on_leave') echo 'İzinli (Raporlu)';
                                    ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <button onclick='openEmployeeModal(<?php echo json_encode($row); ?>)' class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-pencil"></i> Düzenle</button>
                                <a href="personeller.php?delete=<?php echo $row['id']; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem; color: var(--danger); border-color: var(--danger);" onclick="return confirm('Personeli silmek istediğinize emin misiniz?')" ><i class="fa-solid fa-trash"></i> Sil</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Employee Modal -->
<div class="admin-modal" id="employeeModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="empModalTitle">Personel Ekle / Düzenle</h3>
            <span class="modal-close" onclick="closeEmployeeModal()">&times;</span>
        </div>
        <form action="personeller.php" method="POST" enctype="multipart/form-data">
            <?php csrfInput(); ?>
            <input type="hidden" name="id" id="emp_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="emp_name">Ad Soyad *</label>
                    <input type="text" name="name" id="emp_name" class="form-control" placeholder="Ad Soyad" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="emp_phone">Telefon Numarası *</label>
                    <div style="display: flex; align-items: center; background: #fff; border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden;">
                        <div style="display: flex; align-items: center; gap: 6px; background: #f8fafc; padding: 10px 14px; border-right: 1px solid var(--border); font-weight: 700; color: #334155; font-size: 0.95rem; user-select: none;">
                            <span style="font-size: 1.2rem;">🇹🇷</span>
                            <span>+90</span>
                        </div>
                        <input type="text" name="phone" id="emp_phone" class="form-control" placeholder="555 555 55 55" maxlength="14" style="border: none; border-radius: 0; flex: 1; padding: 10px 16px; font-size: 0.95rem;" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="emp_status">Durum</label>
                    <select name="status" id="emp_status" class="form-control">
                        <option value="active">Aktif (Çalışıyor)</option>
                        <option value="inactive">Pasif (Ayrıldı)</option>
                        <option value="on_leave">İzinli (Geçici)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight: 700;">Personel Türü *</label>
                    <input type="hidden" name="employee_type" id="emp_employee_type" value="general">
                    <div style="display: flex; gap: 10px; margin-top: 5px;">
                        <button type="button" id="btn_emp_type_general" class="btn btn-primary" style="padding: 8px 18px; border-radius: var(--radius-pill); font-size: 0.85rem;" onclick="selectEmployeeType('general')">
                            Genel Temizlik
                        </button>
                        <button type="button" id="btn_emp_type_furniture" class="btn btn-outline" style="padding: 8px 18px; border-radius: var(--radius-pill); font-size: 0.85rem;" onclick="selectEmployeeType('furniture')">
                            Koltuk & Yatak Yıkama
                        </button>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="emp_start">Çalışma Başlangıcı</label>
                        <input type="time" name="work_hours_start" id="emp_start" class="form-control" value="08:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="emp_end">Çalışma Bitişi</label>
                        <input type="time" name="work_hours_end" id="emp_end" class="form-control" value="17:00">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Haftalık İzin Günleri *</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 10px; background-color: var(--background); padding: 15px; border-radius: 12px; border: 1px solid var(--border);">
                        <label style="font-weight: 500; cursor: pointer;"><input type="checkbox" name="off_days[]" value="Monday" id="off_Mon"> Pazartesi</label>
                        <label style="font-weight: 500; cursor: pointer;"><input type="checkbox" name="off_days[]" value="Tuesday" id="off_Tue"> Salı</label>
                        <label style="font-weight: 500; cursor: pointer;"><input type="checkbox" name="off_days[]" value="Wednesday" id="off_Wed"> Çarşamba</label>
                        <label style="font-weight: 500; cursor: pointer;"><input type="checkbox" name="off_days[]" value="Thursday" id="off_Thu"> Perşembe</label>
                        <label style="font-weight: 500; cursor: pointer;"><input type="checkbox" name="off_days[]" value="Friday" id="off_Fri"> Cuma</label>
                        <label style="font-weight: 500; cursor: pointer;"><input type="checkbox" name="off_days[]" value="Saturday" id="off_Sat"> Cumartesi</label>
                        <label style="font-weight: 500; cursor: pointer;"><input type="checkbox" name="off_days[]" value="Sunday" id="off_Sun" checked> Pazar</label>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="emp_daily_wage_full">Tam Gün Yövmiye (₺) *</label>
                        <input type="number" step="0.01" name="daily_wage_full" id="emp_daily_wage_full" class="form-control" value="0.00" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="emp_daily_wage_half">Yarım Gün Yövmiye (₺) *</label>
                        <input type="number" step="0.01" name="daily_wage_half" id="emp_daily_wage_half" class="form-control" value="0.00" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="emp_photo">Fotoğraf (Boş bırakılırsa mevcut kalır)</label>
                    <input type="file" name="photo_file" id="emp_photo" class="form-control" style="padding: 10px 20px;">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeEmployeeModal()">İptal</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
function selectEmployeeType(type) {
    document.getElementById("emp_employee_type").value = type;
    const btnGen = document.getElementById("btn_emp_type_general");
    const btnFur = document.getElementById("btn_emp_type_furniture");
    
    if (type === 'general') {
        btnGen.className = "btn btn-primary";
        btnFur.className = "btn btn-outline";
    } else {
        btnGen.className = "btn btn-outline";
        btnGen.blur();
        btnFur.className = "btn btn-primary";
    }
}

function openEmployeeModal(emp = null) {
    // Checkboxes sıfırla
    document.querySelectorAll('[id^="off_"]').forEach(cb => {
        cb.checked = false;
    });
    
    if (emp) {
        document.getElementById("empModalTitle").innerText = "Personeli Düzenle";
        document.getElementById("emp_id").value = emp.id;
        document.getElementById("emp_name").value = emp.name;
        
        let cleanP = (emp.phone || "").replace(/\D/g, "");
        if (cleanP.startsWith("90")) cleanP = cleanP.substring(2);
        if (cleanP.startsWith("0")) cleanP = cleanP.substring(1);
        if (cleanP.length > 10) cleanP = cleanP.substring(0, 10);
        let formP = "";
        if (cleanP.length > 0) formP += cleanP.substring(0, 3);
        if (cleanP.length > 3) formP += " " + cleanP.substring(3, 6);
        if (cleanP.length > 6) formP += " " + cleanP.substring(6, 8);
        if (cleanP.length > 8) formP += " " + cleanP.substring(8, 10);
        document.getElementById("emp_phone").value = formP;
        
        document.getElementById("emp_status").value = emp.status;
        document.getElementById("emp_start").value = emp.work_hours_start.substring(0, 5);
        document.getElementById("emp_end").value = emp.work_hours_end.substring(0, 5);
        selectEmployeeType(emp.employee_type || 'general');
        
        // İzin günlerini seç
        const offs = emp.off_days.split(',').map(d => d.trim());
        offs.forEach(day => {
            const cb = document.querySelector(`input[name="off_days[]"][value="${day}"]`);
            if (cb) cb.checked = true;
        });
        
        document.getElementById("emp_daily_wage_full").value = parseFloat(emp.daily_wage_full || 0).toFixed(2);
        document.getElementById("emp_daily_wage_half").value = parseFloat(emp.daily_wage_half || 0).toFixed(2);
    } else {
        document.getElementById("empModalTitle").innerText = "Yeni Personel Ekle";
        document.getElementById("emp_id").value = "";
        document.getElementById("emp_name").value = "";
        document.getElementById("emp_phone").value = "";
        document.getElementById("emp_status").value = "active";
        document.getElementById("emp_start").value = "08:00";
        document.getElementById("emp_end").value = "17:00";
        selectEmployeeType('general');
        document.getElementById("off_Sun").checked = true; // default pazar
        
        document.getElementById("emp_daily_wage_full").value = "0.00";
        document.getElementById("emp_daily_wage_half").value = "0.00";
    }
    document.getElementById("employeeModal").classList.add("active");
}

function closeEmployeeModal() {
    document.getElementById("employeeModal").classList.remove("active");
}

// Telefon formatlayıcıyı yükle
window.addEventListener("DOMContentLoaded", () => {
    const empPhone = document.getElementById("emp_phone");
    if (empPhone) {
        empPhone.placeholder = "555 555 55 55";
        empPhone.maxLength = 14;
        empPhone.addEventListener("input", () => {
            let value = empPhone.value.replace(/\D/g, "");
            if (value.startsWith("90")) value = value.substring(2);
            if (value.startsWith("0")) value = value.substring(1);
            if (value.length > 10) value = value.substring(0, 10);
            let formatted = "";
            if (value.length > 0) formatted += value.substring(0, 3);
            if (value.length > 3) formatted += " " + value.substring(3, 6);
            if (value.length > 6) formatted += " " + value.substring(6, 8);
            if (value.length > 8) formatted += " " + value.substring(8, 10);
            empPhone.value = formatted;
        });
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
