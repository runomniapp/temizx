<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../classes/Package.php';
require_once __DIR__ . '/../classes/Setting.php';
require_once __DIR__ . '/../classes/Category.php';

$packageModel = new Package();
$categoryModel = new Category();
$msg = '';

// Kategori verilerini çek
$subscriptionCategories = $categoryModel->getAll(true);
$subscriptionCategories = array_filter($subscriptionCategories, function($cat) {
    return $cat['is_subscription_active'] == 1;
});

// Paket Ekleme/Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verifyCsrfToken($csrf)) {
        $id = (int)($_POST['id'] ?? 0);
        
        $featuresArr = isset($_POST['features']) ? array_filter(array_map('trim', $_POST['features'])) : [];
        $featuresJson = !empty($featuresArr) ? json_encode(array_values($featuresArr), JSON_UNESCAPED_UNICODE) : null;
        
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'duration_weeks' => (int)($_POST['duration_weeks'] ?? 4),
            'normal_price' => (float)($_POST['normal_price'] ?? 0.00),
            'discounted_price' => (float)($_POST['discounted_price'] ?? 0.00),
            'time_slot' => trim($_POST['time_slot'] ?? '08-17'),
            'person_count' => (int)($_POST['person_count'] ?? 1),
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'features' => $featuresJson,
            'is_popular' => isset($_POST['is_popular']) ? 1 : 0,
            'status' => isset($_POST['status']) ? 1 : 0,
            'image' => ''
        ];
        
        // Fotoğraf Yükleme
        if (isset($_FILES['pkg_image']) && $_FILES['pkg_image']['error'] === UPLOAD_ERR_OK) {
            $uploadRes = Setting::uploadFile($_FILES['pkg_image'], 'packages');
            if ($uploadRes['success']) {
                $data['image'] = $uploadRes['path'];
            } else {
                $msg .= '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 20px;">Görsel Hatası: ' . e($uploadRes['message']) . '</div>';
            }
        }
        
        if ($id > 0) {
            if (empty($data['image'])) {
                $currentPkg = $packageModel->getById($id);
                $data['image'] = $currentPkg['image'];
            }
            if ($packageModel->update($id, $data)) {
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Paket başarıyla güncellendi!</div>';
            }
        } else {
            if ($packageModel->create($data)) {
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Paket başarıyla eklendi!</div>';
            }
        }
    }
}

// Paket Silme
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($packageModel->delete($id)) {
        $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-xmark"></i> Paket silindi.</div>';
    }
}

$packages = $packageModel->getAll(false);
?>

<div style="max-width: 1200px; margin: 0 auto;">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 5px;">Abonelik Paketleri</h2>
            <p style="color: var(--text-muted);">Haftalık periyodik temizlikler için sunulan indirimli paket tanımlarını buradan yönetin.</p>
        </div>
        <button onclick="openPackageModal()" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Yeni Paket Ekle</button>
    </div>
    
    <?php echo $msg; ?>
    
    <!-- Table -->
    <div class="admin-table-wrapper">
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Görsel</th>
                        <th>Paket Adı</th>
                        <th>Açıklama</th>
                        <th>Detaylar</th>
                        <th>Normal Fiyat</th>
                        <th>İndirimli Fiyat</th>
                        <th>Seans Başı</th>
                        <th>Durum</th>
                        <th style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($packages)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 50px; color: var(--text-muted);">Paket bulunmuyor.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($packages as $row): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $imgSrc = $row['image'] ? '../' . $row['image'] : 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=80&q=80';
                                    ?>
                                    <img src="<?php echo e($imgSrc); ?>" alt="Package Image" style="width: 60px; height: 44px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border);">
                                </td>
                                <td>
                                    <strong><?php echo e($row['name']); ?></strong>
                                    <?php if (!empty($row['category_name'])): ?>
                                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 3px; font-weight: 500;">
                                            <i class="fa-solid fa-tag"></i> <?php echo e($row['category_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 250px; font-size: 0.9rem;">
                                    <div style="font-weight: 600; margin-bottom: 4px;"><?php echo e($row['description']); ?></div>
                                    <?php 
                                    if (!empty($row['features'])) {
                                        $feats = json_decode($row['features'], true);
                                        if (is_array($feats) && !empty($feats)) {
                                            echo '<ul style="margin: 6px 0 0 0; padding-left: 16px; font-size: 0.8rem; color: var(--text-muted); list-style-type: disc;">';
                                            foreach ($feats as $ft) {
                                                echo '<li>' . e($ft) . '</li>';
                                            }
                                            echo '</ul>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 3px; font-size: 0.85rem;">
                                        <strong><i class="fa-solid fa-arrows-spin"></i> <?php echo $row['duration_weeks']; ?> Seans (Hafta)</strong>
                                        <span style="color: var(--text-muted); font-weight: 600;"><i class="fa-solid fa-users"></i> <?php echo $row['person_count'] ?? 1; ?> Personel</span>
                                        <span style="color: var(--primary); font-weight: 600;">
                                            <i class="fa-solid fa-clock"></i> 
                                            <?php 
                                            if ($row['time_slot'] === '08-17') echo 'Tam Gün';
                                            else if ($row['time_slot'] === '08-12') echo 'Yarım Gün - Sabah';
                                            else if ($row['time_slot'] === '13-17') echo 'Yarım Gün - Ö.S.';
                                            ?>
                                        </span>
                                    </div>
                                </td>
                                <td style="text-decoration: line-through; color: var(--text-muted);"><?php echo formatPrice($row['normal_price']); ?></td>
                                <td><strong style="color: var(--primary); font-size: 1.05rem;"><?php echo formatPrice($row['discounted_price']); ?></strong></td>
                                <td>
                                    <?php 
                                    $perSessionPrice = $row['duration_weeks'] > 0 ? (float)$row['discounted_price'] / (int)$row['duration_weeks'] : 0;
                                    ?>
                                    <strong style="color: var(--success); font-size: 1.05rem;"><?php echo formatPrice($perSessionPrice); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $row['status'] == 1 ? 'active' : 'inactive'; ?>">
                                        <?php echo $row['status'] == 1 ? 'Aktif' : 'Pasif'; ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button onclick="openPackageModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-pencil"></i> Düzenle</button>
                                    <a href="paketler.php?delete=<?php echo $row['id']; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem; color: var(--danger); border-color: var(--danger);" onclick="return confirm('Paketi silmek istediğinize emin misiniz?')" ><i class="fa-solid fa-trash"></i> Sil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Package Add/Edit Modal -->
<div class="admin-modal" id="packageModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title" id="pkgModalTitle">Paket Ekle / Düzenle</h3>
            <span class="modal-close" onclick="closePackageModal()">&times;</span>
        </div>
        <form action="paketler.php" method="POST" enctype="multipart/form-data">
            <?php csrfInput(); ?>
            <input type="hidden" name="id" id="pkg_id">
            
            <div class="modal-body">
                <!-- Drag and Drop Image Uploader -->
                <div class="form-group">
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 700; margin-bottom: 6px;">Paket Görseli</label>
                    <div class="drag-drop-uploader" id="pkg_uploader">
                        <input type="file" name="pkg_image" id="pkg_file_input" accept="image/*" style="display: none;">
                        <div class="uploader-placeholder">
                            <div class="uploader-icon"><i class="fa-regular fa-image" style="color: var(--primary);"></i></div>
                            <div class="uploader-text">Görseli buraya sürükleyin veya <span class="browse-link">göz atın</span></div>
                            <div class="uploader-note">Desteklenen formatlar: JPG, JPEG, PNG</div>
                        </div>
                        <div class="uploader-preview" id="pkg_uploader_preview" style="display: none;">
                            <img src="" alt="Preview" class="preview-img" id="pkg_preview_img">
                            <div class="preview-overlay">
                                <span>Görseli Değiştirmek İçin Tıklayın</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pkg_category_id">İlişkili Hizmet Kategorisi *</label>
                    <select name="category_id" id="pkg_category_id" class="form-control" required onchange="calculatePackageNormalPrice()">
                        <option value="">Seçin...</option>
                        <?php foreach ($subscriptionCategories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    data-price="<?php echo $cat['price']; ?>"
                                    data-half-day-price="<?php echo $cat['half_day_price']; ?>"
                                    data-max-person="<?php echo $cat['max_person']; ?>"
                                    data-person-full-price="<?php echo $cat['person_full_price']; ?>"
                                    data-person-half-price="<?php echo $cat['person_half_price']; ?>">
                                <?php echo e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pkg_name">Paket Adı *</label>
                    <input type="text" name="name" id="pkg_name" class="form-control" placeholder="Örn. 4'lü Paket" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="pkg_description">Paket Açıklaması *</label>
                    <textarea name="description" id="pkg_description" class="form-control" rows="3" placeholder="Paket içeriği ve detaylar..." style="border-radius: 20px; resize: none;" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight: 700; margin-bottom: 6px;">Sunulacak Hizmetler / Dahil Olanlar (Maddeler Halinde)</label>
                    <div id="pkg_features_container" style="display: flex; flex-direction: column; gap: 8px;">
                        <!-- Dinamik maddeler buraya yüklenecek -->
                    </div>
                    <button type="button" class="btn btn-outline" onclick="addFeatureRow()" style="margin-top: 8px; padding: 6px 12px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-plus"></i> Yeni Madde Ekle
                    </button>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1.2fr 0.8fr; gap: 15px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="pkg_duration">Abonelik Süresi *</label>
                        <select name="duration_weeks" id="pkg_duration" class="form-control" required onchange="calculatePackageNormalPrice()">
                            <?php for($i = 2; $i <= 56; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> Seans<?php echo $i === 56 ? ' (1 Yıl)' : ''; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="pkg_time_slot">Zaman Dilimi *</label>
                        <select name="time_slot" id="pkg_time_slot" class="form-control" required onchange="calculatePackageNormalPrice()">
                            <option value="08-17">Tam Gün (08-17)</option>
                            <option value="08-12">Yarım Gün - Sabah (08-12)</option>
                            <option value="13-17">Yarım Gün - Öğleden Sonra (13-17)</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="pkg_person_count">Personel Sayısı *</label>
                        <input type="number" name="person_count" id="pkg_person_count" class="form-control" min="1" value="1" required oninput="calculatePackageNormalPrice()">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="pkg_normal">Normal Toplam Fiyat (₺) *</label>
                        <input type="number" name="normal_price" id="pkg_normal" class="form-control" placeholder="Otomatik hesaplanır..." readonly style="background-color: #f1f5f9; cursor: not-allowed;" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="pkg_discounted">İndirimli Fiyat (₺) *</label>
                        <input type="number" name="discounted_price" id="pkg_discounted" class="form-control" placeholder="5200" step="0.01" required oninput="updateDiscountBadge()">
                        <div id="pkg_discount_badge" style="font-size: 0.8rem; color: var(--success); font-weight: 600; margin-top: 6px; min-height: 18px;"></div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 20px; align-items: center;">
                    <label style="font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="status" id="pkg_status" value="1" style="width: 18px; height: 18px; accent-color: var(--primary);" checked> 
                        Aktif
                    </label>
                    <label style="font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="is_popular" id="pkg_is_popular" value="1" style="width: 18px; height: 18px; accent-color: var(--primary);"> 
                        En Popüler Paket
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closePackageModal()">İptal</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
function initDragDropUploader(containerId, inputId, previewContainerId, previewImgId) {
    const container = document.getElementById(containerId);
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewContainerId);
    const previewImg = document.getElementById(previewImgId);
    const placeholder = container.querySelector(".uploader-placeholder");
    
    container.onclick = (e) => {
        if (e.target.tagName !== 'INPUT') {
            input.click();
        }
    };
    
    container.ondragover = (e) => {
        e.preventDefault();
        container.classList.add("dragover");
    };
    
    container.ondragleave = () => {
        container.classList.remove("dragover");
    };
    
    container.ondrop = (e) => {
        e.preventDefault();
        container.classList.remove("dragover");
        if (e.dataTransfer.files.length > 0) {
            input.files = e.dataTransfer.files;
            handleFile(e.dataTransfer.files[0]);
        }
    };
    
    input.onchange = () => {
        if (input.files.length > 0) {
            handleFile(input.files[0]);
        }
    };
    
    function handleFile(file) {
        if (!file.type.startsWith("image/")) {
            alert("Lütfen geçerli bir görsel dosyası seçin.");
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImg.src = e.target.result;
            placeholder.style.display = "none";
            preview.style.display = "block";
        };
        reader.readAsDataURL(file);
    }
}

// Uploader'ı başlat
document.addEventListener("DOMContentLoaded", () => {
    initDragDropUploader("pkg_uploader", "pkg_file_input", "pkg_uploader_preview", "pkg_preview_img");
});

function openPackageModal(pkg = null) {
    const container = document.getElementById("pkg_features_container");
    container.innerHTML = "";
    
    if (pkg) {
        document.getElementById("pkgModalTitle").innerText = "Paketi Düzenle";
        document.getElementById("pkg_id").value = pkg.id;
        document.getElementById("pkg_category_id").value = pkg.category_id || '';
        document.getElementById("pkg_name").value = pkg.name;
        document.getElementById("pkg_description").value = pkg.description;
        document.getElementById("pkg_duration").value = pkg.duration_weeks;
        document.getElementById("pkg_time_slot").value = pkg.time_slot || '08-17';
        document.getElementById("pkg_person_count").value = pkg.person_count || '1';
        document.getElementById("pkg_normal").value = pkg.normal_price;
        document.getElementById("pkg_discounted").value = pkg.discounted_price;
        document.getElementById("pkg_status").checked = pkg.status == 1;
        document.getElementById("pkg_is_popular").checked = pkg.is_popular == 1;
        
        if (pkg.image) {
            document.getElementById("pkg_preview_img").src = '../' + pkg.image;
            document.getElementById("pkg_uploader_preview").style.display = "block";
            document.getElementById("pkg_uploader").querySelector(".uploader-placeholder").style.display = "none";
        } else {
            document.getElementById("pkg_preview_img").src = '';
            document.getElementById("pkg_uploader_preview").style.display = "none";
            document.getElementById("pkg_uploader").querySelector(".uploader-placeholder").style.display = "block";
        }
        
        // Maddeleri yükle
        if (pkg.features) {
            try {
                const featuresArr = JSON.parse(pkg.features);
                if (Array.isArray(featuresArr)) {
                    featuresArr.forEach(val => addFeatureRow(val));
                }
            } catch (e) {
                console.error("Features JSON parse error:", e);
            }
        }
        
        // Fiyat detaylarını güncelle
        updateDiscountBadge();
    } else {
        document.getElementById("pkgModalTitle").innerText = "Yeni Paket Ekle";
        document.getElementById("pkg_id").value = "";
        document.getElementById("pkg_category_id").value = "";
        document.getElementById("pkg_name").value = "";
        document.getElementById("pkg_description").value = "";
        document.getElementById("pkg_duration").value = "4";
        document.getElementById("pkg_time_slot").value = "08-17";
        document.getElementById("pkg_person_count").value = "1";
        document.getElementById("pkg_normal").value = "";
        document.getElementById("pkg_discounted").value = "";
        document.getElementById("pkg_status").checked = true;
        document.getElementById("pkg_is_popular").checked = false;
        
        document.getElementById("pkg_preview_img").src = '';
        document.getElementById("pkg_uploader_preview").style.display = "none";
        document.getElementById("pkg_uploader").querySelector(".uploader-placeholder").style.display = "block";
        
        document.getElementById("pkg_discount_badge").innerText = "";
        
        // İlk açılışta 1 boş satır ekle
        addFeatureRow();
    }
    document.getElementById("packageModal").classList.add("active");
}

function calculatePackageNormalPrice() {
    const catSelect = document.getElementById("pkg_category_id");
    const selectedOption = catSelect.options[catSelect.selectedIndex];
    if (!selectedOption || selectedOption.value === "") {
        document.getElementById("pkg_normal").value = "";
        updateDiscountBadge();
        return;
    }
    
    // Verileri veri özniteliklerinden oku
    const price = parseFloat(selectedOption.getAttribute("data-price") || 0);
    const halfDayPrice = parseFloat(selectedOption.getAttribute("data-half-day-price") || 0);
    const maxPerson = parseInt(selectedOption.getAttribute("data-max-person") || 1);
    const personFullPrice = parseFloat(selectedOption.getAttribute("data-person-full-price") || 0);
    const personHalfPrice = parseFloat(selectedOption.getAttribute("data-person-half-price") || 0);
    
    // Seçimleri al
    const durationWeeks = parseInt(document.getElementById("pkg_duration").value) || 1;
    const timeSlot = document.getElementById("pkg_time_slot").value;
    const personCount = parseInt(document.getElementById("pkg_person_count").value) || 1;
    
    const isHalfDay = (timeSlot === '08-12' || timeSlot === '13-17');
    const basePrice = isHalfDay ? halfDayPrice : price;
    const extraPersonPrice = isHalfDay ? personHalfPrice : personFullPrice;
    
    const extraCount = Math.max(0, personCount - maxPerson);
    const seansPrice = basePrice + (extraCount * extraPersonPrice);
    const normalTotal = seansPrice * durationWeeks;
    
    document.getElementById("pkg_normal").value = normalTotal.toFixed(2);
    updateDiscountBadge();
}

function updateDiscountBadge() {
    const normalPrice = parseFloat(document.getElementById("pkg_normal").value) || 0;
    const discountedPrice = parseFloat(document.getElementById("pkg_discounted").value) || 0;
    const badge = document.getElementById("pkg_discount_badge");
    
    if (normalPrice <= 0 || discountedPrice <= 0) {
        badge.innerText = "";
        return;
    }
    
    if (discountedPrice >= normalPrice) {
        badge.innerText = "İndirimli fiyat normal fiyata eşit veya daha büyük olamaz.";
        badge.style.color = "var(--danger)";
        return;
    }
    
    const discountAmount = normalPrice - discountedPrice;
    const discountPercent = (discountAmount / normalPrice) * 100;
    
    badge.innerText = `%${discountPercent.toFixed(1)} İndirim (${discountAmount.toFixed(2)} ₺ Tasarruf)`;
    badge.style.color = "var(--success)";
}

function addFeatureRow(value = '') {
    const container = document.getElementById("pkg_features_container");
    const row = document.createElement("div");
    row.className = "pkg-feature-row";
    row.style.display = "flex";
    row.style.gap = "10px";
    row.style.alignItems = "center";
    row.style.marginTop = "4px";
    
    row.innerHTML = `
        <input type="text" name="features[]" class="form-control pkg-feature-input" value="${escapeHtml(value)}" placeholder="Örn. Detaylı mutfak temizliği dahil" required style="font-size: 0.9rem; padding: 8px 16px; flex: 1;">
        <button type="button" onclick="this.parentElement.remove()" class="btn btn-outline" style="padding: 8px 12px; color: var(--danger); border-color: var(--danger); border-radius: 12px;" title="Sil">
            <i class="fa-solid fa-trash-can"></i>
        </button>
    `;
    container.appendChild(row);
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function closePackageModal() {
    document.getElementById("packageModal").classList.remove("active");
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
