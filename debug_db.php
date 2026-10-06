<?php
// debug_db.php
// Keamanan: Hanya izinkan akses dari localhost
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($clientIp, ['127.0.0.1', '::1']) || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);
if (!$isLocal) {
    http_response_code(403);
    die("<h1>403 Forbidden</h1><p>Akses ditolak. Halaman debug hanya diizinkan melalui localhost.</p>");
}

require_once 'db.php';

$message = "";
$status = "Menunggu Pengecekan...";

// Check Connection
try {
    if ($pdo) {
        $status = "<span style='color:green; font-weight:bold;'>KONEKSI BERHASIL!</span>";
    }
} catch (Exception $e) {
    $status = "<span style='color:red; font-weight:bold;'>KONEKSI GAGAL: " . $e->getMessage() . "</span>";
}

// Handle Reset
if (isset($_POST['reset_password'])) {
    $username = 'admin'; // Default target
    $new_password = 'admin123';
    $hash = password_hash($new_password, PASSWORD_DEFAULT);

    try {
        // Check if admin exists
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            // Update
            $update = $pdo->prepare("UPDATE admins SET password = ? WHERE username = ?");
            if ($update->execute([$hash, $username])) {
                $message = "Sukses! Password user 'admin' berhasil di-reset menjadi: <strong>$new_password</strong>";
            } else {
                $message = "Gagal mengupdate password.";
            }
        } else {
            // Create
            $insert = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
            if ($insert->execute([$username, $hash])) {
                 $message = "User 'admin' tidak ditemukan, berhasil dibuat baru dengan password: <strong>$new_password</strong>";
            } else {
                $message = "Gagal membuat user baru.";
            }
        }
    } catch (PDOException $e) {
        $message = "Error Reset Password: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug DB & Reset Password</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f8; display: flex; justify-content: center; padding-top: 50px; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h2 { margin-top: 0; color: #333; }
        .box { background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px solid #eee; margin-bottom: 20px; }
        .label { font-weight: 600; color: #555; display: inline-block; width: 100px; }
        .btn { display: block; width: 100%; padding: 12px; border: none; border-radius: 6px; background: #dc3545; color: white; font-size: 16px; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #c82333; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid transparent; }
        .alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <h2>Debug Tools</h2>
    
    <div class="box">
        <h4>Status Koneksi Database</h4>
        <div><span class="label">Host:</span> <?php echo $host; ?></div>
        <div><span class="label">DB Name:</span> <?php echo $dbname; ?></div>
        <div style="margin-top: 10px;"><span class="label">Status:</span> <?php echo $status; ?></div>
    </div>

    <div class="box">
        <h4>Reset Password Admin</h4>
        <p style="font-size: 14px; color: #666; margin-bottom: 15px;">
            Gunakan fitur ini jika Anda gagal login. Password user <strong>'admin'</strong> akan diubah menjadi <code>admin123</code>.
        </p>
        
        <?php if($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post">
            <button type="submit" name="reset_password" class="btn" onclick="return confirm('Yakin ingin mereset password user admin?');">
                Reset Password ke 'admin123'
            </button>
        </form>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="index.php" style="text-decoration: none; color: #007bff; margin-right: 15px;">Buka Website</a>
        <a href="admin/" style="text-decoration: none; color: #007bff;">Buka Admin</a>
    </div>
</div>

</body>
</html>
