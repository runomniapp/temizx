<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../classes/Slider.php';
require_once __DIR__ . '/../classes/Setting.php'; // upload helper için

$sliderModel = new Slider();
$msg = '';

// Slider Ekle / Düzenle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verifyCsrfToken($csrf)) {
        $id = (int)($_POST['id'] ?? 0);
        
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'subtitle' => trim($_POST['subtitle'] ?? ''),
            'button_text' => trim($_POST['button_text'] ?? 'Teklif Al'),
            'button_url' => trim($_POST['button_url'] ?? '#teklif-al'),
            'order_num' => (int)($_POST['order_num'] ?? 0),
            'status' => isset($_POST['status']) ? 1 : 0,
            'image' => ''
        ];
        
        // Fotoğraf Yükleme
        if (isset($_FILES['slide_image']) && $_FILES['slide_image']['error'] === UPLOAD_ERR_OK) {
            $uploadRes = Setting::uploadFile($_FILES['slide_image'], 'sliders');
            if ($uploadRes['success']) {
                $data['image'] = $uploadRes['path'];
            } else {
                $msg .= '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 20px;">Görsel Hatası: ' . e($uploadRes['message']) . '</div>';
            }
        }
        
        if ($id > 0) {
            // Resim yenilenmediyse mevcut olanı koru
            if (empty($data['image'])) {
                $currentSld = $sliderModel->getById($id);
                $data['image'] = $currentSld['image'];
            }
            
            if ($sliderModel->update($id, $data)) {
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Slider başarıyla güncellendi!</div>';
            }
        } else {
            if (empty($data['image'])) {
                $data['image'] = 'assets/img/default_slider.jpg'; // varsayılan
            }
            
            if ($sliderModel->create($data)) {
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Slider başarıyla eklendi!</div>';
            }
        }
    }
}

// Slider Silme
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($sliderModel->delete($id)) {
        $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-xmark"></i> Slider silindi.</div>';
    }
}

$sliders = $sliderModel->getAll(false);
?>

<div style="max-width: 1200px; margin: 0 auto;">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 5px;">Slider Yönetimi</h2>
            <p style="color: var(--text-muted);">Web sitesinin ana sayfasındaki kayan görsel afişleri (sliders) yönetin.</p>
        </div>
        <button onclick="openSliderModal()" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Yeni Slider Ekle</button>
    </div>
    
    <?php echo $msg; ?>
    
    <!-- Table -->
    <div class="admin-table-wrapper">
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Görsel</th>
                        <th>Başlık (Title)</th>
                        <th>Alt Başlık (Subtitle)</th>
                        <th>Buton Metni</th>
                        <th>Sıra</th>
                        <th>Durum</th>
                        <th style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sliders)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 50px; color: var(--text-muted);">Kayıt bulunamadı.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sliders as $row): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $imgSrc = $row['image'];
                                    if ($row['id'] == 1 && $imgSrc === 'assets/img/slider1.jpg') {
                                        $imgSrc = 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=150&q=80';
                                    } else if ($row['id'] == 2 && $imgSrc === 'assets/img/slider2.jpg') {
                                        $imgSrc = 'https://images.unsplash.com/photo-1527515637462-cff94eecc1ac?auto=format&fit=crop&w=150&q=80';
                                    } else {
                                        $imgSrc = '../' . $imgSrc;
                                    }
                                    ?>
                                    <img src="<?php echo e($imgSrc); ?>" alt="Slide Image" style="width: 100px; height: 55px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border);">
                                </td>
                                <td><strong><?php echo e($row['title']); ?></strong></td>
                                <td style="max-width: 250px; font-size: 0.9rem; color: var(--text-muted);"><?php echo e($row['subtitle']); ?></td>
                                <td><code><?php echo e($row['button_text']); ?></code></td>
                                <td><strong><?php echo $row['order_num']; ?></strong></td>
                                <td>
                                    <span class="badge badge-<?php echo $row['status'] == 1 ? 'active' : 'inactive'; ?>">
                                        <?php echo $row['status'] == 1 ? 'Aktif' : 'Pasif'; ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button onclick="openSliderModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-pencil"></i> Düzenle</button>
                                    <a href="slider.php?delete=<?php echo $row['id']; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem; color: var(--danger); border-color: var(--danger);" onclick="return confirm('Silmek istediğinize emin misiniz?')" ><i class="fa-solid fa-trash"></i> Sil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Slider Modal -->
<div class="admin-modal" id="sliderModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="sldModalTitle">Slider Ekle / Düzenle</h3>
            <span class="modal-close" onclick="closeSliderModal()">&times;</span>
        </div>
        <form action="slider.php" method="POST" enctype="multipart/form-data">
            <?php csrfInput(); ?>
            <input type="hidden" name="id" id="sld_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="sld_title">Afiş Başlığı *</label>
                    <input type="text" name="title" id="sld_title" class="form-control" required placeholder="Görsel üstünde kalın başlık">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="sld_subtitle">Alt Başlık Açıklaması *</label>
                    <input type="text" name="subtitle" id="sld_subtitle" class="form-control" required placeholder="Görsel üstünde ince kısa yazı">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="sld_btn_text">Buton Yazısı</label>
                        <input type="text" name="button_text" id="sld_btn_text" class="form-control" placeholder="Teklif Al">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="sld_btn_url">Buton Yönlendirme URL'i</label>
                        <input type="text" name="button_url" id="sld_btn_url" class="form-control" placeholder="#teklif-al">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Slider Görseli</label>
                    <div class="drag-drop-uploader" id="sld_uploader">
                        <input type="file" name="slide_image" id="sld_image" accept="image/*" style="display: none;">
                        <div class="uploader-placeholder">
                            <div class="uploader-icon"><i class="fa-regular fa-image" style="color: var(--primary);"></i></div>
                            <div class="uploader-text">Görseli buraya sürükleyin veya <span class="browse-link">göz atın</span></div>
                            <div class="uploader-note">Desteklenen formatlar: JPG, JPEG, PNG</div>
                        </div>
                        <div class="uploader-preview" id="sld_uploader_preview" style="display: none;">
                            <img src="" alt="Preview" class="preview-img" id="sld_preview_img">
                            <div class="preview-overlay">
                                <span>Görseli Değiştirmek İçin Tıklayın</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="sld_order">Sıralama</label>
                        <input type="number" name="order_num" id="sld_order" class="form-control" value="0">
                    </div>
                    <div style="margin-top: 30px;">
                        <label><input type="checkbox" name="status" id="sld_status" value="1" checked> Aktif</label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeSliderModal()">İptal</button>
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

document.addEventListener("DOMContentLoaded", () => {
    initDragDropUploader("sld_uploader", "sld_image", "sld_uploader_preview", "sld_preview_img");
});

function openSliderModal(sld = null) {
    if (sld) {
        document.getElementById("sldModalTitle").innerText = "Slider Düzenle";
        document.getElementById("sld_id").value = sld.id;
        document.getElementById("sld_title").value = sld.title;
        document.getElementById("sld_subtitle").value = sld.subtitle;
        document.getElementById("sld_btn_text").value = sld.button_text;
        document.getElementById("sld_btn_url").value = sld.button_url;
        document.getElementById("sld_order").value = sld.order_num;
        document.getElementById("sld_status").checked = sld.status == 1;
        
        if (sld.image) {
            let imgSrc = sld.image;
            if (sld.id == 1 && imgSrc === 'assets/img/slider1.jpg') {
                imgSrc = 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=150&q=80';
            } else if (sld.id == 2 && imgSrc === 'assets/img/slider2.jpg') {
                imgSrc = 'https://images.unsplash.com/photo-1527515637462-cff94eecc1ac?auto=format&fit=crop&w=150&q=80';
            } else {
                imgSrc = '../' + imgSrc;
            }
            document.getElementById("sld_preview_img").src = imgSrc;
            document.getElementById("sld_uploader_preview").style.display = "block";
            document.getElementById("sld_uploader").querySelector(".uploader-placeholder").style.display = "none";
        } else {
            document.getElementById("sld_preview_img").src = '';
            document.getElementById("sld_uploader_preview").style.display = "none";
            document.getElementById("sld_uploader").querySelector(".uploader-placeholder").style.display = "block";
        }
    } else {
        document.getElementById("sldModalTitle").innerText = "Yeni Slider Ekle";
        document.getElementById("sld_id").value = "";
        document.getElementById("sld_title").value = "";
        document.getElementById("sld_subtitle").value = "";
        document.getElementById("sld_btn_text").value = "Teklif Al";
        document.getElementById("sld_btn_url").value = "#teklif-al";
        document.getElementById("sld_order").value = "0";
        document.getElementById("sld_status").checked = true;
        
        document.getElementById("sld_preview_img").src = '';
        document.getElementById("sld_uploader_preview").style.display = "none";
        document.getElementById("sld_uploader").querySelector(".uploader-placeholder").style.display = "block";
    }
    document.getElementById("sliderModal").classList.add("active");
}

function closeSliderModal() {
    document.getElementById("sliderModal").classList.remove("active");
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
