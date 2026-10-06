<?php
// admin/login_local.php
// Keamanan: Hanya izinkan akses dari localhost
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($clientIp, ['127.0.0.1', '::1']) || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);
if (!$isLocal) {
    http_response_code(403);
    die("<h1>403 Forbidden</h1><p>Akses ditolak. Fitur dev login hanya diizinkan melalui localhost.</p>");
}

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once 'includes/auth.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bypass_login'])) {
    $user = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    if (!$user) $user = $pdo->query("SELECT * FROM users LIMIT 1")->fetch();

    if ($user) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_email'] = $user['email'];
        $_SESSION['admin_role'] = $user['role'];
        $_SESSION['admin_avatar'] = $user['avatar'] ?? "https://ui-avatars.com/api/?name=".urlencode($user['username']);
        header("Location: dashboard.php"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dev Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: #eef2f7; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: sans-serif; }
        .card { border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 350px; text-align: center; border: none; }
    </style>
</head>
<body>
    <div class="card p-5">
        <i class="fa-solid fa-user-shield fs-1 text-primary mb-4"></i>
        <h3>Dev Login</h3>
        <p class="text-muted small mb-4">Klik tombol untuk masuk sebagai Admin.</p>
        <form method="POST">
            <button type="submit" name="bypass_login" class="btn btn-primary w-100 rounded-pill py-2">Masuk Sekarang</button>
        </form>
    </div>
</body>
</html>
