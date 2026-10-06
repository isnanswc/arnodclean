<?php
// admin/includes/auth.php
if (!defined('IS_CRON') && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fungsi untuk cek apakah user sudah login
function checkLogin() {
    // Check if logged in AND all required session variables are set
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['admin_role'])) {
        // If session is incomplete (e.g. from old login), force logout
        session_destroy();
        header("Location: login.php");
        exit;
    }
    // Perform log cleanup periodically
    autoCleanupLogs();
}

// Fungsi Log Aktivitas
function logActivity($action, $details = '', $link = '') {
    global $pdo;
    if (isset($_SESSION['admin_id'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['admin_id'], 
                $action, 
                $details, 
                $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
            ]);

            // --- Telegram Global Log Notification ---
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings WHERE setting_key IN ('tg_log_notify_enabled', 'tg_bot_token', 'tg_chat_id', 'tg_site_url')");
            $tgSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            if (($tgSettings['tg_log_notify_enabled'] ?? '0') == '1') {
                $tgChatId = $tgSettings['tg_chat_id'] ?? '';
                $tgBotToken = $tgSettings['tg_bot_token'] ?? '';
                $tgBaseUrl = rtrim($tgSettings['tg_site_url'] ?? '', '/');

                if (!empty($tgChatId) && !empty($tgBotToken)) {
                    // Ensure tgBaseUrl has protocol for clickable links
                    if (!empty($tgBaseUrl) && strncmp($tgBaseUrl, 'http', 4) !== 0) {
                        $tgBaseUrl = 'http://' . $tgBaseUrl;
                    }

                    $user = $_SESSION['admin_username'] ?? 'Admin';
                    $msg = "🔔 <b>Activity Log Alert</b>\n\n";
                    $msg .= "👤 <b>User:</b> " . htmlspecialchars($user) . "\n";
                    $msg .= "⚡ <b>Aksi:</b> " . htmlspecialchars($action) . "\n";
                    $msg .= "📝 <b>Detail:</b> " . htmlspecialchars($details) . "\n";
                    
                    if (!empty($link)) {
                        // Ensure link has base URL if it's relative
                        if (strncmp($link, 'http', 4) !== 0 && !empty($tgBaseUrl)) {
                            $link = $tgBaseUrl . '/' . ltrim($link, '/');
                        }
                        $msg .= "\n🔗 <b>Link:</b> " . $link;
                    }
                    
                    $msg .= "\n\n📍 <b>IP:</b> " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown');
                    sendTelegram($tgChatId, $msg, $tgBotToken);
                }
            }
        } catch (Exception $e) { /* Ignore log errors */ }
    }
}

/**
 * Send Telegram Notification (Global Helper)
 * Supports multiple Chat IDs (comma separated)
 */
function sendTelegram($chatIdInput, $message, $botToken) {
    if (empty($chatIdInput) || empty($message) || empty($botToken)) return false;
    
    // Split IDs by comma, semicolon, newline, or space
    $chatIds = preg_split('/[\s,;]+/', $chatIdInput, -1, PREG_SPLIT_NO_EMPTY);
    // Slice to max 10 to prevent abuse
    $chatIds = array_slice($chatIds, 0, 10);
    
    $success = true;
    
    foreach ($chatIds as $chatId) {
        if (empty($chatId)) continue;
        
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $params = [
            'chat_id' => $chatId, 
            'text' => $message, 
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200) $success = false;
    }
    
    return $success;
}

// Fungsi Pembersihan Log Otomatis
function autoCleanupLogs() {
    global $pdo;
    // Run only once per session to save resources
    if (isset($_SESSION['last_cleanup']) && $_SESSION['last_cleanup'] > (time() - 3600)) return;
    
    $retention = 30; // Default
    try {
        $stmt = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'log_retention_days'");
        $val = $stmt->fetchColumn();
        if ($val !== false) $retention = (int)$val;
        
        // Delete old activity logs
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$retention]);
        
        // Delete old login history
        $stmt = $pdo->prepare("DELETE FROM login_history WHERE login_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$retention]);
        
        $_SESSION['last_cleanup'] = time();
    } catch (Exception $e) { /* Ignore cleanup errors */ }
}
// End of file (Omitted closing tag to prevent whitespace issues)
