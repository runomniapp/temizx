<?php
require_once __DIR__ . '/header.php';

$db = Database::getConnection();
$msg = '';

// SSS Ekleme/Güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (verifyCsrfToken($csrf)) {
        $id = (int)($_POST['id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $orderNum = (int)($_POST['order_num'] ?? 0);
        $status = isset($_POST['status']) ? 1 : 0;
        
        if ($question && $answer) {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE faqs SET question = ?, answer = ?, order_num = ?, status = ? WHERE id = ?");
                $stmt->execute([$question, $answer, $orderNum, $status, $id]);
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Soru başarıyla güncellendi!</div>';
            } else {
                $stmt = $db->prepare("INSERT INTO faqs (question, answer, order_num, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$question, $answer, $orderNum, $status]);
                $msg = '<div style="background-color: #ecfdf5; color: var(--success); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-check"></i> Soru başarıyla eklendi!</div>';
            }
        }
    }
}

// SSS Silme
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM faqs WHERE id = ?");
    $stmt->execute([$id]);
    $msg = '<div style="background-color: #fef2f2; color: var(--danger); padding: 15px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; margin-bottom: 25px;"><i class="fa-solid fa-circle-xmark"></i> Soru silindi.</div>';
}

$faqs = $db->query("SELECT * FROM faqs ORDER BY order_num ASC")->fetchAll();
?>

<div style="max-width: 1200px; margin: 0 auto;">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 5px;">Sıkça Sorulan Sorular</h2>
            <p style="color: var(--text-muted);">Web sitesindeki yardım merkezinde sergilenen soruları, cevapları ve sıralamayı yönetin.</p>
        </div>
        <button onclick="openFaqModal()" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Yeni Soru Ekle</button>
    </div>
    
    <?php echo $msg; ?>
    
    <!-- Table -->
    <div class="admin-table-wrapper">
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Soru</th>
                        <th>Cevap</th>
                        <th>Sıra</th>
                        <th>Durum</th>
                        <th style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faqs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 50px; color: var(--text-muted);">Kayıt bulunamadı.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($faqs as $row): ?>
                            <tr>
                                <td><strong><?php echo e($row['question']); ?></strong></td>
                                <td style="max-width: 450px; font-size: 0.9rem; line-height: 1.5;"><?php echo e($row['answer']); ?></td>
                                <td><strong><?php echo $row['order_num']; ?></strong></td>
                                <td>
                                    <span class="badge badge-<?php echo $row['status'] == 1 ? 'active' : 'inactive'; ?>">
                                        <?php echo $row['status'] == 1 ? 'Aktif' : 'Pasif'; ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button onclick='openFaqModal(<?php echo json_encode($row); ?>)' class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-pencil"></i> Düzenle</button>
                                    <a href="sss.php?delete=<?php echo $row['id']; ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem; color: var(--danger); border-color: var(--danger);" onclick="return confirm('Silmek istediğinize emin misiniz?')" ><i class="fa-solid fa-trash"></i> Sil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- FAQ Modal -->
<div class="admin-modal" id="faqModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="faqModalTitle">Soru Ekle / Düzenle</h3>
            <span class="modal-close" onclick="closeFaqModal()">&times;</span>
        </div>
        <form action="sss.php" method="POST">
            <?php csrfInput(); ?>
            <input type="hidden" name="id" id="faq_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="faq_question">Soru *</label>
                    <input type="text" name="question" id="faq_question" class="form-control" required placeholder="Örn. Temizlik malzemelerini siz mi getiriyorsunuz?">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="faq_answer">Cevap *</label>
                    <textarea name="answer" id="faq_answer" class="form-control" rows="5" style="border-radius: 20px; resize: none;" required placeholder="Soruya verilecek açıklayıcı yanıt..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label" for="faq_order">Sıralama Numarası</label>
                        <input type="number" name="order_num" id="faq_order" class="form-control" value="0">
                    </div>
                    <div style="margin-top: 30px;">
                        <label><input type="checkbox" name="status" id="faq_status" value="1" checked> Aktif (Sitede Göster)</label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeFaqModal()">İptal</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
function openFaqModal(faq = null) {
    if (faq) {
        document.getElementById("faqModalTitle").innerText = "Soruyu Düzenle";
        document.getElementById("faq_id").value = faq.id;
        document.getElementById("faq_question").value = faq.question;
        document.getElementById("faq_answer").value = faq.answer;
        document.getElementById("faq_order").value = faq.order_num;
        document.getElementById("faq_status").checked = faq.status == 1;
    } else {
        document.getElementById("faqModalTitle").innerText = "Yeni Soru Ekle";
        document.getElementById("faq_id").value = "";
        document.getElementById("faq_question").value = "";
        document.getElementById("faq_answer").value = "";
        document.getElementById("faq_order").value = "0";
        document.getElementById("faq_status").checked = true;
    }
    document.getElementById("faqModal").classList.add("active");
}

function closeFaqModal() {
    document.getElementById("faqModal").classList.remove("active");
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
