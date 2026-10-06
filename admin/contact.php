<?php
// admin/contact.php
require_once '../db.php';
require_once 'includes/auth.php';
checkLogin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($_POST['contact'] as $key => $value) {
        $stmt = $pdo->prepare("UPDATE contact_info SET contact_value = ? WHERE contact_key = ?");
        $stmt->execute([$value, $key]);
    }
    $message = "Informasi kontak berhasil diperbarui.";
}

// Ambil semua kontak
$contacts = $pdo->query("SELECT * FROM contact_info")->fetchAll();
$contact_map = [];
foreach ($contacts as $c) {
    $contact_map[$c['contact_key']] = $c;
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Informasi Kontak</h1>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>

<div class="card shadow-sm" style="max-width: 800px;">
    <div class="card-body">
        <form method="POST">
            <?php foreach ($contact_map as $key => $data): ?>
            <div class="mb-3 row">
                <label class="col-sm-3 col-form-label fw-bold"><?php echo htmlspecialchars($data['label']); ?></label>
                <div class="col-sm-9">
                    <?php if ($key == 'address'): ?>
                        <textarea class="form-control" name="contact[<?php echo $key; ?>]" rows="3"><?php echo htmlspecialchars($data['contact_value']); ?></textarea>
                    <?php else: ?>
                        <input type="text" class="form-control" name="contact[<?php echo $key; ?>]" value="<?php echo htmlspecialchars($data['contact_value']); ?>">
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="row">
                <div class="col-sm-9 offset-sm-3">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-2"></i>Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
