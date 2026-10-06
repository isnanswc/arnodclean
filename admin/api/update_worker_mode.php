<?php
// admin/api/update_worker_mode.php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../includes/auth.php';

// Ensure Admin is logged in (ajax)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? '0'; // 1 or 0
    $mode = ($mode === '1') ? '1' : '0';

    try {
        // Check for delay
        $delay = isset($_POST['delay']) ? (int)$_POST['delay'] : null;
        if ($delay !== null) {
            if ($delay < 3) $delay = 3;
            if ($delay > 360) $delay = 360; // Max 6 Hours
            $stmtDelay = $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES ('worker_delay_minutes', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmtDelay->execute([$delay]);
        }

        $stmt = $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES ('worker_turbo_mode', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$mode]);
        
        echo json_encode(['status' => 'success', 'mode' => $mode, 'delay' => $delay]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
