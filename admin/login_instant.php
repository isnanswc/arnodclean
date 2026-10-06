<?php
// admin/login_instant.php
// Keamanan: Hanya izinkan akses dari localhost untuk mencegah eksploitasi remote di server live
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($clientIp, ['127.0.0.1', '::1']) || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);
if (!$isLocal) {
    http_response_code(403);
    die("<h1>403 Forbidden</h1><p>Akses ditolak. Fitur dev login hanya diizinkan melalui localhost.</p>");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db.php';
require_once 'includes/auth.php';

// Redirect jika sudah login
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

// Handle Login Bypass
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bypass_login'])) {
    try {
        // Ambil user pertama dengan role admin
        $stmt = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
        $user = $stmt->fetch();
        
        // Jika tidak ada user admin, ambil user pertama yang ada
        if (!$user) {
            $user = $pdo->query("SELECT * FROM users LIMIT 1")->fetch();
        }

        if ($user) {
            // Berhasil Login (Tanpa Verifikasi)
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['admin_avatar'] = $user['avatar'] ?? "https://ui-avatars.com/api/?name=".urlencode($user['username']);
            
            // Simpan History Login (Opsional)
            try {
                $stmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $_SERVER['HTTP_USER_AGENT'] ?? 'LocalDev']);
            } catch (Exception $e) {}

            // Log Aktivitas
            if (function_exists('logActivity')) {
                logActivity('Auth', 'Login via Local Dev Bypass');
            }

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Gagal login: Tidak ada data user ditemukan di database.";
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dev Login - Arno D Clean</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background: #eef2f7;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            text-align: center;
        }
        .card-body {
            padding: 50px 40px;
        }
        .btn-bypass {
            background: #2C73D2;
            color: white;
            border: none;
            padding: 15px 30px;
            font-weight: 600;
            border-radius: 50px;
            font-size: 1.1rem;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 8px 20px rgba(44, 115, 210, 0.3);
        }
        .btn-bypass:hover {
            background: #2462b5;
            transform: scale(1.05);
            box-shadow: 0 12px 25px rgba(44, 115, 210, 0.4);
            color: white;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            background: rgba(44, 115, 210, 0.1);
            color: #2C73D2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px auto;
            font-size: 2.5rem;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="card">
            <div class="card-body">
                <div class="icon-circle">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <h3 class="fw-bold mb-2">Dev Login</h3>
                <p class="text-muted small mb-5">Mode pengembangan lokal. Klik tombol di bawah untuk masuk sebagai Admin secara instan.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger small mb-4"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <button type="submit" name="bypass_login" value="1" class="btn btn-bypass">
                        <i class="fa-solid fa-rocket"></i> Masuk Sekarang
                    </button>
                </form>

                <div class="mt-4">
                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-triangle-exclamation"></i> Only for Localhost</span>
                </div>
            </div>
        </div>
        <p class="text-center mt-4 text-muted small">
            ADC Content Management System
        </p>
    </div>

</body>
</html>
