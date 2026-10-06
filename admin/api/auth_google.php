<?php
// admin/api/auth_google.php
session_start();
require_once '../../db.php';
require_once '../includes/auth.php';

// disable json header, we are redirecting now
// header('Content-Type: application/json');

$id_token = $_POST['credential'] ?? '';

// Fallback 1: Check Cookies (Strategy 3)
if (empty($id_token)) {
    $id_token = $_COOKIE['g_token'] ?? '';
    // Delete cookie immediately to prevent reuse
    if (!empty($id_token)) {
        setcookie('g_token', '', time() - 3600, '/'); 
    }
}

// Fallback 2: Read raw input
if (empty($id_token)) {
    $rawInput = file_get_contents("php://input");
    parse_str($rawInput, $parsed);
    $id_token = $parsed['credential'] ?? '';
}

if (empty($id_token)) {
    // Redirect back with error
    header("Location: ../login.php?error=" . urlencode("No credential provided to server."));
    exit;
}

// Verify Token with Google API (No external lib approach)
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
// Use curl for better reliability than file_get_contents on some servers
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    header("Location: ../login.php?error=" . urlencode("Failed to verify token with Google."));
    exit;
}

$payload = json_decode($response, true);

if (isset($payload['email'])) {
    $email = $payload['email'];
    $name = $payload['name'];
    $picture = $payload['picture'];
    $google_id = $payload['sub'];

    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Update User Info
        $stmt = $pdo->prepare("UPDATE users SET google_id = ?, avatar = ?, username = ? WHERE id = ?");
        $stmt->execute([$google_id, $picture, $name, $user['id']]); // Update username too if wanted, or keep existing
        $userId = $user['id'];
        $role = $user['role'];
    } else {
        // Create New User 
        if ($email === 'arnod.clean@gmail.com' || $email === 'isnanswc@gmail.com') {
             // Create Default Admin if somehow missing
             $stmt = $pdo->prepare("INSERT INTO users (username, email, google_id, avatar, role) VALUES (?, ?, ?, ?, 'admin')");
             $stmt->execute([$name, $email, $google_id, $picture]);
             $userId = $pdo->lastInsertId();
             $role = 'admin';
        } else {
             header("Location: ../login.php?error=" . urlencode("Email $email belum terdaftar di sistem kami."));
             exit;
        }
    }

    // Set Session
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $userId;
    $_SESSION['admin_username'] = $name;
    $_SESSION['admin_email'] = $email;
    $_SESSION['admin_role'] = $role;
    $_SESSION['admin_avatar'] = $picture;

    // Log History
    $stmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
    $_SESSION['login_history_id'] = $pdo->lastInsertId();

    // Log Activity
    logActivity('Auth', 'Login via Google');

    // SUCCESS REDIRECT
    header("Location: ../dashboard.php");
    exit;

} else {
    header("Location: ../login.php?error=" . urlencode("Invalid Token Payload from Google."));
    exit;
}
?>
