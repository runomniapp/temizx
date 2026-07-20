<?php
require_once __DIR__ . '/header.php';

$db = Database::getConnection();
$msg = '';

// Yorum Ekleme/Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verifyCsrfToken($csrf)) {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $rating = (int)($_POST['rating'] ?? 5);
        $status = isset($_POST['status']) ? 1 : 0;
        
        if ($name && $comment) {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE reviews SET name = ?, comment = ?, rating = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $comment, $rating, $status, $id]);
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Yorum güncellendi!</div>';
            } else {
                $stmt = $db->prepare("INSERT INTO reviews (name, comment, rating, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $comment, $rating, $status]);
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Yorum eklendi!</div>';
            }
        }
    }
}

// Yorum Silme
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->execute([$id]);
    $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-xmark"></i> Yorum silindi.</div>';
}

$reviews = $db->query("SELECT * FROM reviews ORDER BY id DESC")->fetchAll();
?>

<div style="max-width: 1200px; margin: 0 auto;">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 5px;">Müşteri Yorumları</h2>
            <p style="color: var(--text-muted);">Web sitesindeki referanslar alanında yayınlanan müşteri yorumlarını buradan düzenleyin.</p>
        </div>
        <button onclick="openReviewModal()" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Yeni Yorum Ekle</button>
    </div>
    
    <?php echo $msg; ?>
    
    <!-- Table -->
    <div class="admin-table-wrapper">
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Müşteri Adı</th>
                        <th>Yorum</th>
                        <th>Puan</th>
                        <th>Durum</th>
                        <th style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reviews)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 50px; color: var(--text-muted);">Kayıt bulunamadı.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reviews as $row): ?>
                            <tr>
                                <td><strong><?php echo e($row['name']); ?></strong></td>
                                <td style="max-width: 400px; font-size: 0.9rem; line-height: 1.5;"><?php echo e($row['comment']); ?></td>
                                <td style="color: #ffc107;">
                                    <?php for ($i = 0; $i < $row['rating']; $i++): ?>
                                        <i class="fa-solid fa-star"></i>
                                    <?php endfor; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $row['status'] == 1 ? 'active' : 'inactive'; ?>">
                                        <?php echo $row['status'] == 1 ? 'Aktif' : 'Pasif'; ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button onclick='openReviewModal(<?php echo json_encode($row); ?>)' class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-pencil"></i> Düzenle</button>
                                    <a href="yorumlar.php?delete=<?php echo $row['id']; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem; color: var(--danger); border-color: var(--danger);" onclick="return confirm('Silmek istediğinize emin misiniz?')" ><i class="fa-solid fa-trash"></i> Sil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="admin-modal" id="reviewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="revModalTitle">Yorum Ekle / Düzenle</h3>
            <span class="modal-close" onclick="closeReviewModal()">&times;</span>
        </div>
        <form action="yorumlar.php" method="POST">
            <?php csrfInput(); ?>
            <input type="hidden" name="id" id="rev_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="rev_name">Müşteri Adı Soyadı *</label>
                    <input type="text" name="name" id="rev_name" class="form-control" required placeholder="Ahmet Y.">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="rev_rating">Puan (Yıldız) *</label>
                    <select name="rating" id="rev_rating" class="form-control" required>
                        <option value="5">5 Yıldız</option>
                        <option value="4">4 Yıldız</option>
                        <option value="3">3 Yıldız</option>
                        <option value="2">2 Yıldız</option>
                        <option value="1">1 Yıldız</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="rev_comment">Yorum İçeriği *</label>
                    <textarea name="comment" id="rev_comment" class="form-control" rows="4" style="border-radius: 20px; resize: none;" required placeholder="Müşterinin hizmet ile ilgili geri bildirimi..."></textarea>
                </div>
                
                <div style="margin-top: 10px;">
                    <label><input type="checkbox" name="status" id="rev_status" value="1" checked> Aktif (Sitede Yayınla)</label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeReviewModal()">İptal</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReviewModal(rev = null) {
    if (rev) {
        document.getElementById("revModalTitle").innerText = "Yorumu Düzenle";
        document.getElementById("rev_id").value = rev.id;
        document.getElementById("rev_name").value = rev.name;
        document.getElementById("rev_rating").value = rev.rating;
        document.getElementById("rev_comment").value = rev.comment;
        document.getElementById("rev_status").checked = rev.status == 1;
    } else {
        document.getElementById("revModalTitle").innerText = "Yeni Yorum Ekle";
        document.getElementById("rev_id").value = "";
        document.getElementById("rev_name").value = "";
        document.getElementById("rev_rating").value = "5";
        document.getElementById("rev_comment").value = "";
        document.getElementById("rev_status").checked = true;
    }
    document.getElementById("reviewModal").classList.add("active");
}

function closeReviewModal() {
    document.getElementById("reviewModal").classList.remove("active");
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
