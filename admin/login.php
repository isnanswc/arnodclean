<?php
// admin/login.php
session_start();
require_once '../db.php';
require_once 'includes/auth.php'; // For potential helper functions

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

// Handle Standard Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_type']) && $_POST['login_type'] == 'password') {
    $input = trim($_POST['email']); // Can be email or username
    $password = $_POST['password'];
    
    // Allow login by Email OR Username
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$input, $input]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Success
        session_regenerate_id(true); // Prevent Session Fixation
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_email'] = $user['email'];
        $_SESSION['admin_role'] = $user['role'];
        $_SESSION['admin_avatar'] = $user['avatar'] ?? "https://ui-avatars.com/api/?name=".urlencode($user['username']);
        
        // Log History
        $stmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        $_SESSION['login_history_id'] = $pdo->lastInsertId();

        // Log Activity
        logActivity('Auth', 'Login via Password');

        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Email atau Password salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Arno D Clean</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #2C73D2, #84DCCF);
            height: 100vh;
            display: grid;
            place-items: center;
            font-family: 'Poppins', sans-serif;
        }
        .login-card {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .btn-google {
            background: white;
            border: 1px solid #ddd;
            color: #333;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s;
        }
        .btn-google:hover {
            background: #f8f9fa;
            border-color: #ccc;
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: #aaa;
            font-size: 0.85rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #eee;
        }
        .divider:not(:empty)::before { margin-right: .5em; }
        .divider:not(:empty)::after { margin-left: .5em; }
    </style>
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const clientId = document.getElementById("g_id_onload").getAttribute("data-client_id");
            if (clientId.includes("PLACEHOLDER")) {
                // Determine if we are on a live site or local
                if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                    // Show warning on live site
                     const container = document.querySelector('.login-card');
                     const alertDiv = document.createElement('div');
                     alertDiv.className = 'alert alert-warning small';
                     alertDiv.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <b>Google Login Belum Dikonfigurasi!</b><br>Harap ganti CLIENT_ID di kode `admin/login.php`.';
                     container.insertBefore(alertDiv, container.querySelector('#g_id_onload'));
                }
            }
        });
    </script>
</head>
<body>

    <div class="login-card">
        <div class="text-center mb-4">
            <h4 class="fw-bold text-primary">Arno D Clean</h4>
            <p class="text-muted small">Welcome Back, Admin!</p>
        </div>


        <!-- Google Login Button Container -->
        <div id="g_id_onload"
             data-client_id="217632069548-u6542mpsr5u7opuh7426igjk5n4ojlhv.apps.googleusercontent.com"
             data-context="signin"
             data-ux_mode="popup"
             data-callback="handleCredentialResponse"
             data-auto_prompt="false"
             data-auto_select="false">
        </div>
        
        <script>
            // Force disable auto-select to ensure user can choose account
            window.onload = function() {
                if(google && google.accounts && google.accounts.id) {
                    google.accounts.id.disableAutoSelect();
                }
            }
        </script>
        
        <div class="g_id_signin"
             data-type="standard"
             data-shape="pill"
             data-theme="outline"
             data-text="signin_with"
             data-size="large"
             data-logo_alignment="left"
             data-width="320">
        </div>

        <div class="divider">ATAU LOGIN MANUAL</div>

        <form method="POST">
            <input type="hidden" name="login_type" value="password">
            <div class="mb-3">
                <input type="text" class="form-control" name="email" required placeholder="Email Address or Username">
            </div>
            <div class="mb-3">
                <div class="input-group">
                    <input type="password" class="form-control" name="password" id="passwordInput" required placeholder="Password">
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 rounded-pill">
                Masuk
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Password Toggle
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#passwordInput');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the eye slash icon
            this.querySelector('i').classList.toggle('fa-eye-slash');
            this.querySelector('i').classList.toggle('fa-eye');
        });

        function handleCredentialResponse(response) {
            // STRATEGY 3: Cookie Transport
            // Some servers strip POST data on redirects or have strict WAF rules.
            // We will save the token in a cookie and let the backend read it.
            
            // 1. Set Cookie (Expire in 10 minutes)
            const d = new Date();
            d.setTime(d.getTime() + (10*60*1000));
            let expires = "expires="+ d.toUTCString();
            document.cookie = "g_token=" + response.credential + ";" + expires + ";path=/";

            // 2. Redirect to backend
            window.location.href = 'api/auth_google.php';
        }

        // Trigger SweetAlert if PHP error exists
        <?php if($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Login Gagal',
            text: '<?php echo $error; ?>',
            confirmButtonColor: '#d33',
            confirmButtonText: 'Coba Lagi'
        });
        <?php endif; ?>
    </script>
</body>
</html>
