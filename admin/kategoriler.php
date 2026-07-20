<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../classes/Category.php';
require_once __DIR__ . '/../classes/Package.php';

$categoryModel = new Category();
$packageModel = new Package();
$msg = '';

// Kategori Kayıt/Güncelleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_category') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verifyCsrfToken($csrf)) {
        $id = (int)($_POST['id'] ?? 0);
        
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'icon' => trim($_POST['icon'] ?? 'home'),
            'image' => trim($_POST['image'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'color' => trim($_POST['color'] ?? '#0066FF'),
            'pricing_type' => trim($_POST['pricing_type'] ?? 'category'),
            'service_group' => trim($_POST['service_group'] ?? 'general'),
            'price' => (float)($_POST['price'] ?? 0.00),
            'half_day_price' => (float)($_POST['half_day_price'] ?? 0.00),
            'max_person' => (int)($_POST['max_person'] ?? 1),
            'person_full_price' => (float)($_POST['person_full_price'] ?? 0.00),
            'person_half_price' => (float)($_POST['person_half_price'] ?? 0.00),
            'is_subscription_active' => isset($_POST['is_subscription_active']) ? 1 : 0,
            'status' => isset($_POST['status']) ? 1 : 0,
            'order_num' => (int)($_POST['order_num'] ?? 0)
        ];
        
        // Eğer görsel yüklenmişse güncelle
        if (isset($_FILES['cat_image']) && $_FILES['cat_image']['error'] === UPLOAD_ERR_OK) {
            $uploadRes = Setting::uploadFile($_FILES['cat_image'], 'categories');
            if ($uploadRes['success']) {
                $data['image'] = $uploadRes['path'];
            }
        }
        
        if ($id > 0) {
            // Güncelleme
            // Mevcut resmi koru eğer yenisi yüklenmediyse
            if (empty($data['image'])) {
                $currentCat = $categoryModel->getById($id);
                $data['image'] = $currentCat['image'];
            }
            
            if ($categoryModel->update($id, $data)) {
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Kategori başarıyla güncellendi!</div>';
            }
        } else {
            // Ekleme
            if (empty($data['image'])) {
                $data['image'] = 'assets/img/default_category.jpg'; // default placeholder
            }
            if ($categoryModel->create($data)) {
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Kategori başarıyla eklendi!</div>';
            }
        }
    }
}

// Kategori Silme
if (isset($_GET['delete_cat'])) {
    $id = (int)$_GET['delete_cat'];
    if ($categoryModel->delete($id)) {
        $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-xmark"></i> Kategori silindi.</div>';
    }
}

// Alt Kategori Kayıt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sub') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verifyCsrfToken($csrf)) {
        $catId = (int)$_POST['category_id'];
        $subId = isset($_POST['sub_id']) && $_POST['sub_id'] !== '' ? (int)$_POST['sub_id'] : null;
        $name = trim($_POST['sub_name'] ?? '');
        $price = (float)($_POST['sub_price'] ?? 0.00);
        $halfDayPrice = (float)($_POST['sub_half_day_price'] ?? 0.00);
        $maxPerson = (int)($_POST['sub_max_person'] ?? 1);
        $personFullPrice = (float)($_POST['sub_person_full_price'] ?? 0.00);
        $personHalfPrice = (float)($_POST['sub_person_half_price'] ?? 0.00);
        
        if ($name && $catId) {
            if ($categoryModel->saveSubcategory($catId, $name, $price, $halfDayPrice, $maxPerson, $personFullPrice, $personHalfPrice, $subId)) {
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Alt kategori başarıyla kaydedildi!</div>';
            }
        }
    }
}

// Alt Kategori Silme
if (isset($_GET['delete_sub'])) {
    $subId = (int)$_GET['delete_sub'];
    if ($categoryModel->deleteSubcategory($subId)) {
        $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-xmark"></i> Alt kategori silindi.</div>';
    }
}

// Abonelik Paket Eşleştirme Kayıt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_packages') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verifyCsrfToken($csrf)) {
        $catId = (int)$_POST['category_id'];
        $pkgIds = $_POST['package_ids'] ?? [];
        if ($categoryModel->syncPackages($catId, $pkgIds)) {
            $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Abonelik paketleri başarıyla güncellendi!</div>';
        }
    }
}

$categories = $categoryModel->getAll(false);
$packages = $packageModel->getAll(true);
?>

<div style="max-width: 1200px; margin: 0 auto;">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 5px;">Hizmet Kategorileri</h2>
            <p style="color: var(--text-muted);">Müşterilerin rezervasyon yapabileceği hizmet türlerini, alt kategorileri ve abonelik paketlerini yönetin.</p>
        </div>
        <button onclick="openCategoryModal()" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Yeni Kategori Ekle</button>
    </div>
    
    <?php echo $msg; ?>
    
    <!-- Table -->
    <div class="admin-table-wrapper">
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Görsel</th>
                        <th>Kategori Adı</th>
                        <th>Slug</th>
                        <th>Fiyatlandırma Tipi</th>
                        <th>Abonelik</th>
                        <th>Durum</th>
                        <th style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $row): ?>
                        <tr>
                            <td>
                                <img src="../<?php echo e($row['image']); ?>" alt="<?php echo e($row['name']); ?>" style="width: 60px; height: 44px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border);">
                            </td>
                            <td>
                                <strong style="display: flex; flex-direction: column; align-items: flex-start; gap: 4px;">
                                    <span style="display: inline-flex; align-items: center; gap: 8px;">
                                        <span style="display: inline-block; width: 14px; height: 14px; border-radius: 4px; background-color: <?php echo e($row['color']); ?>;"></span>
                                        <?php echo e($row['name']); ?>
                                    </span>
                                    <span style="font-size: 0.72rem; color: #64748b; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; margin-left: 22px;"><?php echo $row['service_group'] === 'furniture' ? '<i class="fa-solid fa-couch" style="color: #8b5cf6;"></i> Koltuk & Yatak Yıkama' : '<i class="fa-solid fa-house-chimney" style="color: #3b82f6;"></i> Genel Temizlik'; ?></span>
                                </strong>
                            </td>
                            <td><code><?php echo e($row['slug']); ?></code></td>
                            <td>
                                <?php if ($row['pricing_type'] === 'subcategory'): ?>
                                    <span style="font-weight: 600; color: var(--primary);">Alt Kategorilere Göre</span>
                                <?php elseif ($row['pricing_type'] === 'discovery'): ?>
                                    <span style="font-weight: 600; color: var(--warning);"><i class="fa-solid fa-compass"></i> Keşif Sonrası</span>
                                <?php else: ?>
                                    <span style="font-weight: 600; display: flex; flex-direction: column; gap: 2px;">
                                        <span>Taban Tam: <?php echo formatPrice($row['price']); ?> (Azami <?php echo $row['max_person']; ?> Pers.)</span>
                                        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Taban Yarım: <?php echo formatPrice($row['half_day_price']); ?></span>
                                        <span style="font-size: 0.75rem; color: var(--primary); font-weight: 600;">Ek Pers: Tam: <?php echo formatPrice($row['person_full_price']); ?> / Yarım: <?php echo formatPrice($row['person_half_price']); ?></span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['is_subscription_active'] == 1): ?>
                                    <span class="badge badge-confirmed"><i class="fa-solid fa-arrows-spin"></i> Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Kapalı</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $row['status'] == 1 ? 'active' : 'inactive'; ?>">
                                    <?php echo $row['status'] == 1 ? 'Aktif' : 'Pasif'; ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; justify-content: flex-end; gap: 6px;">
                                    <?php if ($row['pricing_type'] === 'subcategory'): ?>
                                        <button onclick="openSubcategoryModal(<?php echo $row['id']; ?>, '<?php echo e($row['name']); ?>')" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem; border-color: var(--primary); color: var(--primary);" title="Alt Kategoriler"><i class="fa-solid fa-list-ul"></i> Alt Kategoriler</button>
                                    <?php endif; ?>
                                    
                                    <?php if ($row['is_subscription_active'] == 1): ?>
                                        <button onclick="openSyncPackagesModal(<?php echo $row['id']; ?>, <?php echo htmlspecialchars(json_encode($categoryModel->getPackages($row['id'])), ENT_QUOTES, 'UTF-8'); ?>)" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem; border-color: var(--success); color: var(--success);" title="Abonelik Paketleri Eşleştir"><i class="fa-solid fa-box-open"></i> Paket Eşleştir</button>
                                    <?php endif; ?>
                                    
                                    <button onclick="openCategoryModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-pencil"></i> Düzenle</button>
                                    <a href="kategoriler.php?delete_cat=<?php echo $row['id']; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem; color: var(--danger); border-color: var(--danger);" onclick="return confirm('Kategoriyi silmek istediğinize emin misiniz?')"><i class="fa-solid fa-trash"></i> Sil</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Category Add/Edit Modal -->
<div class="admin-modal" id="categoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="catModalTitle">Kategori Ekle / Düzenle</h3>
            <span class="modal-close" onclick="closeCategoryModal()">&times;</span>
        </div>
        <form action="kategoriler.php" method="POST" enctype="multipart/form-data">
            <?php csrfInput(); ?>
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="id" id="cat_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="cat_name">Kategori Adı *</label>
                    <input type="text" name="name" id="cat_name" class="form-control" required onkeyup="generateSlug(this.value)">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="cat_slug">Slug * (Benzersiz link adı)</label>
                    <input type="text" name="slug" id="cat_slug" class="form-control" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="cat_icon">İkon Adı (FontAwesome)</label>
                        <select name="icon" id="cat_icon" class="form-control">
                            <option value="home">Ev (home)</option>
                            <option value="briefcase">Çanta (briefcase)</option>
                            <option value="hammer">Çekiç (hammer)</option>
                            <option value="building">Bina (building)</option>
                            <option value="eye">Cam / Göz (eye)</option>
                            <option value="coffee">Koltuk / Kahve (coffee)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cat_color">Tema Rengi</label>
                        <input type="color" name="color" id="cat_color" class="form-control" style="height: 50px; padding: 4px 10px;" value="#0066FF">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Kategori Görseli</label>
                    <div class="drag-drop-uploader" id="cat_uploader">
                        <input type="file" name="cat_image" id="cat_image_file" accept="image/*" style="display: none;">
                        <div class="uploader-placeholder">
                            <div class="uploader-icon"><i class="fa-regular fa-image" style="color: var(--primary);"></i></div>
                            <div class="uploader-text">Görseli buraya sürükleyin veya <span class="browse-link">göz atın</span></div>
                            <div class="uploader-note">Desteklenen formatlar: JPG, JPEG, PNG</div>
                        </div>
                        <div class="uploader-preview" id="cat_uploader_preview" style="display: none;">
                            <img src="" alt="Preview" class="preview-img" id="cat_preview_img">
                            <div class="preview-overlay">
                                <span>Görseli Değiştirmek İçin Tıklayın</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="cat_description">Açıklama</label>
                    <textarea name="description" id="cat_description" class="form-control" rows="3" style="border-radius: 20px; resize: none;" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="cat_pricing_type">Fiyatlandırma Tipi</label>
                    <select name="pricing_type" id="cat_pricing_type" class="form-control" onchange="togglePriceInput(this.value)">
                        <option value="category">Sabit Kategori Fiyatı</option>
                        <option value="subcategory">Alt Kategorilere Göre</option>
                        <option value="discovery">Keşifle Fiyat Belirlenecek</option>
                    </select>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 15px;" id="price_group">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="cat_price">Taban İşlem Ücreti (Tam Gün - ₺)</label>
                            <input type="number" name="price" id="cat_price" class="form-control" step="0.01" value="0.00">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="cat_half_day_price">Taban İşlem Ücreti (Yarım Gün - ₺)</label>
                            <input type="number" name="half_day_price" id="cat_half_day_price" class="form-control" step="0.01" value="0.00">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="cat_max_person">Azami Personel Sayısı</label>
                            <input type="number" name="max_person" id="cat_max_person" class="form-control" min="1" value="1">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="cat_person_full_price">Kişi Başı Tam Ek (₺)</label>
                            <input type="number" name="person_full_price" id="cat_person_full_price" class="form-control" step="0.01" value="0.00">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="cat_person_half_price">Kişi Başı Yarım Ek (₺)</label>
                            <input type="number" name="person_half_price" id="cat_person_half_price" class="form-control" step="0.01" value="0.00">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="cat_service_group">Hizmet Grubu *</label>
                    <select name="service_group" id="cat_service_group" class="form-control" required>
                        <option value="general">Genel Temizlik Kategorisi</option>
                        <option value="furniture">Koltuk-Yatak vb. Temizlik Kategorisi</option>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="cat_order_num">Sıra Numarası</label>
                        <input type="number" name="order_num" id="cat_order_num" class="form-control" value="0">
                    </div>
                    <div style="display: flex; gap: 20px; align-items: center; margin-top: 25px;">
                        <label><input type="checkbox" name="is_subscription_active" id="cat_is_sub" value="1"> Abonelik Açık</label>
                        <label><input type="checkbox" name="status" id="cat_status" value="1" checked> Aktif</label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeCategoryModal()">İptal</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Subcategory Management Modal -->
<div class="admin-modal" id="subModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Alt Kategoriler: <span id="sub_cat_name_title"></span></h3>
            <span class="modal-close" onclick="closeSubModal()">&times;</span>
        </div>
        <div class="modal-body" style="max-height: 480px; overflow-y: auto;">
            <!-- Subcategory Form -->
            <form action="kategoriler.php" method="POST" style="background-color: var(--background); padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; flex-direction: column; gap: 15px;">
                <?php csrfInput(); ?>
                <input type="hidden" name="action" value="save_sub">
                <input type="hidden" name="category_id" id="sub_cat_id">
                <input type="hidden" name="sub_id" id="sub_id">
                
                <div style="display: grid; grid-template-columns: 1.2fr 0.9fr 0.9fr; gap: 12px;">
                    <div>
                        <label class="form-label" for="sub_name" style="font-size: 0.8rem; font-weight: 700;">Alt Kategori Adı *</label>
                        <input type="text" name="sub_name" id="sub_name" class="form-control" placeholder="Örn. 2+1 Ev Temizliği" style="padding: 8px 15px;" required>
                    </div>
                    <div>
                        <label class="form-label" for="sub_price" style="font-size: 0.8rem; font-weight: 700;">Tam Gün Fiyat (₺) *</label>
                        <input type="number" name="sub_price" id="sub_price" class="form-control" placeholder="1500" style="padding: 8px 15px;" step="0.01" required>
                    </div>
                    <div>
                        <label class="form-label" for="sub_half_day_price" style="font-size: 0.8rem; font-weight: 700;">Yarım Gün Fiyat (₺) *</label>
                        <input type="number" name="sub_half_day_price" id="sub_half_day_price" class="form-control" placeholder="750" style="padding: 8px 15px;" step="0.01" required>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px; align-items: flex-end;">
                    <div>
                        <label class="form-label" for="sub_max_person" style="font-size: 0.8rem; font-weight: 700;">Azami Personel *</label>
                        <input type="number" name="sub_max_person" id="sub_max_person" class="form-control" min="1" value="1" style="padding: 8px 15px;" required>
                    </div>
                    <div>
                        <label class="form-label" for="sub_person_full_price" style="font-size: 0.8rem; font-weight: 700;">Kişi Başı Tam Ek (₺) *</label>
                        <input type="number" name="sub_person_full_price" id="sub_person_full_price" class="form-control" placeholder="300" style="padding: 8px 15px;" step="0.01" required>
                    </div>
                    <div>
                        <label class="form-label" for="sub_person_half_price" style="font-size: 0.8rem; font-weight: 700;">Kişi Başı Yarım Ek (₺) *</label>
                        <input type="number" name="sub_person_half_price" id="sub_person_half_price" class="form-control" placeholder="150" style="padding: 8px 15px;" step="0.01" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Kaydet</button>
                </div>
            </form>
            
            <!-- Subcategory List -->
            <table class="admin-table" style="box-shadow: none; border: 1px solid var(--border); border-radius: 12px;">
                <thead>
                    <tr>
                        <th>Alt Kategori</th>
                        <th>Tam Gün Fiyat</th>
                        <th>Yarım Gün Fiyat</th>
                        <th>Azami Pers.</th>
                        <th>Tam Ek Personel</th>
                        <th>Yarım Ek Personel</th>
                        <th style="text-align: right;">Aksiyonlar</th>
                    </tr>
                </thead>
                <tbody id="subTableBody">
                    <!-- Loaded dynamically via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Sync Packages Modal -->
<div class="admin-modal" id="syncModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Abonelik Paket Eşleştirme</h3>
            <span class="modal-close" onclick="closeSyncModal()">&times;</span>
        </div>
        <form action="kategoriler.php" method="POST">
            <?php csrfInput(); ?>
            <input type="hidden" name="action" value="sync_packages">
            <input type="hidden" name="category_id" id="sync_cat_id">
            
            <div class="modal-body">
                <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 0.9rem;">Bu kategoride aktif olacak abonelik paketlerini seçin.</p>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($packages as $pkg): ?>
                        <div style="display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid var(--border); border-radius: 10px;">
                            <input type="checkbox" name="package_ids[]" value="<?php echo $pkg['id']; ?>" id="sync_pkg_<?php echo $pkg['id']; ?>" style="width: 18px; height: 18px; accent-color: var(--primary);">
                            <label for="sync_pkg_<?php echo $pkg['id']; ?>" style="font-weight: 600; cursor: pointer;"><?php echo e($pkg['name']); ?> (<?php echo formatPrice($pkg['discounted_price']); ?>)</label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeSyncModal()">İptal</button>
                <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
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

document.addEventListener("DOMContentLoaded", () => {
    initDragDropUploader("cat_uploader", "cat_image_file", "cat_uploader_preview", "cat_preview_img");
});

function generateSlug(val) {
    let slug = val.toLowerCase()
        .replace(/ğ/g, 'g')
        .replace(/ü/g, 'u')
        .replace(/ş/g, 's')
        .replace(/ı/g, 'i')
        .replace(/ö/g, 'o')
        .replace(/ç/g, 'c')
        .replace(/[^a-z0-9 -]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
    document.getElementById("cat_slug").value = slug;
}

function togglePriceInput(val) {
    const group = document.getElementById("price_group");
    const inputs = group.querySelectorAll("input");
    if (val === 'subcategory' || val === 'discovery') {
        group.style.opacity = '0.5';
        inputs.forEach(i => i.disabled = true);
    } else {
        group.style.opacity = '1';
        inputs.forEach(i => i.disabled = false);
    }
}

function openCategoryModal(cat = null) {
    if (cat) {
        document.getElementById("catModalTitle").innerText = "Kategoriyi Düzenle";
        document.getElementById("cat_id").value = cat.id;
        document.getElementById("cat_name").value = cat.name;
        document.getElementById("cat_slug").value = cat.slug;
        document.getElementById("cat_icon").value = cat.icon;
        document.getElementById("cat_color").value = cat.color;
        document.getElementById("cat_description").value = cat.description;
        document.getElementById("cat_pricing_type").value = cat.pricing_type;
        document.getElementById("cat_service_group").value = cat.service_group || 'general';
        document.getElementById("cat_price").value = cat.price;
        document.getElementById("cat_half_day_price").value = cat.half_day_price || "0.00";
        document.getElementById("cat_max_person").value = cat.max_person || "1";
        document.getElementById("cat_person_full_price").value = cat.person_full_price || "0.00";
        document.getElementById("cat_person_half_price").value = cat.person_half_price || "0.00";
        document.getElementById("cat_order_num").value = cat.order_num;
        document.getElementById("cat_is_sub").checked = cat.is_subscription_active == 1;
        document.getElementById("cat_status").checked = cat.status == 1;
        
        if (cat.image) {
            document.getElementById("cat_preview_img").src = '../' + cat.image;
            document.getElementById("cat_uploader_preview").style.display = "block";
            document.getElementById("cat_uploader").querySelector(".uploader-placeholder").style.display = "none";
        } else {
            document.getElementById("cat_preview_img").src = '';
            document.getElementById("cat_uploader_preview").style.display = "none";
            document.getElementById("cat_uploader").querySelector(".uploader-placeholder").style.display = "block";
        }
        
        togglePriceInput(cat.pricing_type);
    } else {
        document.getElementById("catModalTitle").innerText = "Yeni Kategori Ekle";
        document.getElementById("cat_id").value = "";
        document.getElementById("cat_name").value = "";
        document.getElementById("cat_slug").value = "";
        document.getElementById("cat_icon").value = "home";
        document.getElementById("cat_color").value = "#0066FF";
        document.getElementById("cat_description").value = "";
        document.getElementById("cat_pricing_type").value = "category";
        document.getElementById("cat_service_group").value = "general";
        document.getElementById("cat_price").value = "0.00";
        document.getElementById("cat_half_day_price").value = "0.00";
        document.getElementById("cat_max_person").value = "1";
        document.getElementById("cat_person_full_price").value = "0.00";
        document.getElementById("cat_person_half_price").value = "0.00";
        document.getElementById("cat_order_num").value = "0";
        document.getElementById("cat_is_sub").checked = false;
        document.getElementById("cat_status").checked = true;
        
        document.getElementById("cat_preview_img").src = '';
        document.getElementById("cat_uploader_preview").style.display = "none";
        document.getElementById("cat_uploader").querySelector(".uploader-placeholder").style.display = "block";
        
        togglePriceInput('category');
    }
    document.getElementById("categoryModal").classList.add("active");
}

function closeCategoryModal() {
    document.getElementById("categoryModal").classList.remove("active");
}

// Alt kategoriler listesi yükleme
function openSubcategoryModal(catId, catName) {
    document.getElementById("sub_cat_id").value = catId;
    document.getElementById("sub_cat_name_title").innerText = catName;
    
    document.getElementById("sub_id").value = "";
    document.getElementById("sub_name").value = "";
    document.getElementById("sub_price").value = "";
    document.getElementById("sub_half_day_price").value = "";
    document.getElementById("sub_max_person").value = "1";
    document.getElementById("sub_person_full_price").value = "0.00";
    document.getElementById("sub_person_half_price").value = "0.00";
    
    const tbody = document.getElementById("subTableBody");
    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Yükleniyor...</td></tr>';
    
    // PHP kategorisindeki alt kategorileri JS nesnesi üzerinden bulup çizelim
    const categoriesList = <?php echo json_encode($categories); ?>;
    
    // getSubcategories(catId)'yi Javascript endpoint ile çekelim
    fetch(`../ajax/get_subcategories.php?cat_id=${catId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                tbody.innerHTML = "";
                if (data.subcategories.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted);">Alt kategori tanımlanmamış.</td></tr>';
                } else {
                    data.subcategories.forEach(sub => {
                        const tr = document.createElement("tr");
                        tr.innerHTML = `
                            <td><strong>${sub.name}</strong></td>
                            <td><strong style="color: var(--primary);">${parseFloat(sub.price).toLocaleString('tr-TR')} ₺</strong></td>
                            <td><strong style="color: var(--primary);">${parseFloat(sub.half_day_price || 0).toLocaleString('tr-TR')} ₺</strong></td>
                            <td><strong>${sub.max_person}</strong></td>
                            <td><strong style="color: var(--text-muted);">${parseFloat(sub.person_full_price || 0).toLocaleString('tr-TR')} ₺</strong></td>
                            <td><strong style="color: var(--text-muted);">${parseFloat(sub.person_half_price || 0).toLocaleString('tr-TR')} ₺</strong></td>
                            <td style="text-align: right;">
                                <button onclick='editSub(${JSON.stringify(sub)})' class="btn btn-outline" style="padding: 4px 8px; font-size: 0.75rem;"><i class="fa-solid fa-pencil"></i></button>
                                <a href="kategoriler.php?delete_sub=${sub.id}" class="btn btn-outline" style="padding: 4px 8px; font-size: 0.75rem; color: var(--danger); border-color: var(--danger);" onclick="return confirm('Alt kategoriyi silmek istediğinize emin misiniz?')"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
                document.getElementById("subModal").classList.add("active");
            } else {
                alert("Alt kategoriler yüklenirken hata oluştu.");
            }
        });
}

function editSub(sub) {
    document.getElementById("sub_id").value = sub.id;
    document.getElementById("sub_name").value = sub.name;
    document.getElementById("sub_price").value = sub.price;
    document.getElementById("sub_half_day_price").value = sub.half_day_price || "0.00";
    document.getElementById("sub_max_person").value = sub.max_person || "1";
    document.getElementById("sub_person_full_price").value = sub.person_full_price || "0.00";
    document.getElementById("sub_person_half_price").value = sub.person_half_price || "0.00";
}

function closeSubModal() {
    document.getElementById("subModal").classList.remove("active");
}

function openSyncPackagesModal(catId, assignedPackages) {
    document.getElementById("sync_cat_id").value = catId;
    
    // Checkboxes sıfırla
    document.querySelectorAll('[id^="sync_pkg_"]').forEach(cb => {
        cb.checked = false;
    });
    
    // Atananları seç
    assignedPackages.forEach(pkg => {
        const cb = document.getElementById(`sync_pkg_${pkg.id}`);
        if (cb) cb.checked = true;
    });
    
    document.getElementById("syncModal").classList.add("active");
}

function closeSyncModal() {
    document.getElementById("syncModal").classList.remove("active");
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
