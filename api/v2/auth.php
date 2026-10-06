<?php
// api/v2/auth.php
// REST API Authentication Endpoint for Admin V2

// Allowed CORS Origins (Vite Dev Server, Mobile IP, & Same Origin)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../admin/includes/auth.php';

$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $postData = json_decode($rawInput, true) ?: $_POST;
    if (empty($action) && isset($postData['action'])) {
        $action = $postData['action'];
    }
}

try {
    switch ($action) {
        case 'me':
            // Check current active session
            if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
                // Fetch fresh user data from DB if possible
                $user = null;
                try {
                    $stmt = $pdo->prepare("SELECT id, username, email, role, avatar FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['admin_id'] ?? 0]);
                    $user = $stmt->fetch();
                } catch (Exception $e) {}

                if ($user) {
                    echo json_encode([
                        'status' => 'success',
                        'logged_in' => true,
                        'user' => [
                            'id' => (int)$user['id'],
                            'username' => $user['username'],
                            'email' => $user['email'],
                            'role' => $user['role'],
                            'avatar' => $user['avatar'] ?: "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=2563eb&color=fff"
                        ]
                    ]);
                    exit;
                } else {
                    echo json_encode([
                        'status' => 'success',
                        'logged_in' => true,
                        'user' => [
                            'id' => (int)($_SESSION['admin_id'] ?? 1),
                            'username' => $_SESSION['admin_username'] ?? 'admin',
                            'email' => $_SESSION['admin_email'] ?? 'admin@arnod-clean.com',
                            'role' => $_SESSION['admin_role'] ?? 'admin',
                            'avatar' => $_SESSION['admin_avatar'] ?? "https://ui-avatars.com/api/?name=Admin&background=2563eb&color=fff"
                        ]
                    ]);
                    exit;
                }
            }
            echo json_encode([
                'status' => 'success',
                'logged_in' => false,
                'user' => null
            ]);
            break;

        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Metode request harus POST.");
            }

            $identity = trim($postData['identity'] ?? $postData['email'] ?? $postData['username'] ?? '');
            $password = (string)($postData['password'] ?? '');

            if (empty($identity) || empty($password)) {
                throw new Exception("Username/Email dan kata sandi wajib diisi.");
            }

            // 1. Find user by email OR username in users table
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$identity, $identity]);
            $user = $stmt->fetch();

            // 2. If not found in users table, check admins table as fallback
            $isAdminFallback = false;
            if (!$user) {
                try {
                    $stmtAdm = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
                    $stmtAdm->execute([$identity]);
                    $adm = $stmtAdm->fetch();
                    if ($adm && password_verify($password, $adm['password'])) {
                        $user = [
                            'id' => $adm['id'],
                            'username' => $adm['username'],
                            'email' => $adm['username'] . '@arnod-clean.com',
                            'password' => $adm['password'],
                            'role' => 'admin',
                            'avatar' => null
                        ];
                        $isAdminFallback = true;
                    }
                } catch (Exception $e) {}
            }

            if ($user && !empty($user['password']) && ($isAdminFallback || password_verify($password, $user['password']))) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_role'] = $user['role'];
                $_SESSION['admin_avatar'] = $user['avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=2563eb&color=fff";

                // Log login history
                try {
                    $stmtHist = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
                    $stmtHist->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $_SERVER['HTTP_USER_AGENT'] ?? 'Admin V2 Client']);
                } catch (Exception $e) {}

                // Log activity
                if (function_exists('logActivity')) {
                    logActivity('Auth', 'Login via Admin V2');
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Login berhasil!',
                    'user' => [
                        'id' => (int)$user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'avatar' => $_SESSION['admin_avatar']
                    ]
                ]);
            } else {
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Email atau Password yang Anda masukkan salah.'
                ]);
            }
            break;

        case 'dev_login':
            // Only allow on localhost
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $isLocal = in_array($clientIp, ['127.0.0.1', '::1']) || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);
            if (!$isLocal) {
                http_response_code(403);
                throw new Exception("Dev login hanya diizinkan melalui localhost.");
            }

            // Get first admin user
            $user = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch();
            if (!$user) {
                $user = $pdo->query("SELECT * FROM users LIMIT 1")->fetch();
            }

            if (!$user) {
                throw new Exception("Tidak ada user ditemukan di database.");
            }

            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['admin_avatar'] = $user['avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=2563eb&color=fff";

            if (function_exists('logActivity')) {
                logActivity('Auth', 'Login via Dev Bypass (Admin V2)');
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Dev bypass login berhasil!',
                'user' => [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'avatar' => $_SESSION['admin_avatar']
                ]
            ]);
            break;

        case 'logout':
            // Clear session
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();

            echo json_encode([
                'status' => 'success',
                'message' => 'Logout berhasil'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Action tidak dikenali.'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
