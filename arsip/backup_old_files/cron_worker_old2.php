<?php
// admin/api/cron_worker.php
// "Turbo Mode" Worker Script
// This script is intended to be run by Cron Job EVERY MINUTE (* * * * *)

date_default_timezone_set('Asia/Jakarta');

// Security Check: Key is required to prevent random bots from triggering it
if (isset($_GET['key']) && $_GET['key'] === 'adc_cron_secure') {
    if (!defined('IS_CRON')) define('IS_CRON', true);
}

if (php_sapi_name() !== 'cli' && !defined('IS_CRON')) {
    http_response_code(403);
    die("Access Denied");
}

require_once __DIR__ . '/../../db.php';

// 1. Check if Turbo Mode is ON
$turboMode = $pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key = 'worker_turbo_mode'")->fetchColumn();

if ($turboMode !== '1') {
    echo "[Worker] Turbo Mode is OFF. Sleeping...\n";
    exit;
}

echo "[Worker] Turbo Mode is ON. Processing queue...\n";

// 2. Check Delay (Throttle)
$delayMin = (int)($pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key = 'worker_delay_minutes'")->fetchColumn() ?: 3);
if ($delayMin < 3) $delayMin = 3; // Minimum 3 minutes safety
if ($delayMin > 360) $delayMin = 360; // Max 6 hours cap

$lastProcessed = $pdo->query("SELECT processed_at FROM auto_content_keywords WHERE status IN ('done', 'failed') ORDER BY processed_at DESC LIMIT 1")->fetchColumn();

if ($lastProcessed) {
    $secondsSince = time() - strtotime($lastProcessed);
    $delaySec = $delayMin * 60;
    
    if ($secondsSince < $delaySec) {
        $wait = $delaySec - $secondsSince;
        echo "[Worker] Turbo Mode Active but Sleeping for {$wait}s (Delay: {$delayMin}m).\n";
        exit;
    }
}

echo "[Worker] Turbo Mode is ON. Processing queue...\n";

// 3. Check Queue
$queueCount = $pdo->query("SELECT COUNT(*) FROM auto_content_keywords WHERE status = 'pending'")->fetchColumn();

if ($queueCount == 0) {
    // Turn off Turbo Mode
    $pdo->prepare("UPDATE auto_content_settings SET setting_value = '0' WHERE setting_key = 'worker_turbo_mode'")->execute();
    echo "[Worker] Queue is empty. Turning OFF Turbo Mode.\n";
    
    // --- NOTIFY TELEGRAM: TURBO FINISHED ---
    // Fetch Settings manually since process_auto_content.php isn't loaded yet
    require_once __DIR__ . '/../includes/functions.php'; // for sendTelegram
    $settings = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings WHERE setting_key IN ('tg_notify_enabled', 'tg_bot_token', 'tg_chat_id')")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $tgEnabled = ($settings['tg_notify_enabled'] ?? '0') == '1';
    $tgToken = $settings['tg_bot_token'] ?? '';
    $tgChatId = $settings['tg_chat_id'] ?? '';
    
    if ($tgEnabled && !empty($tgToken) && !empty($tgChatId)) {
        $msg = "🚀 <b>Turbo Mode Completed</b>\n\n";
        $msg .= "✅ Semua antrian artikel telah selesai diproses.\n";
        $msg .= "ℹ️ Turbo Mode otomatis dimatikan untuk menghemat resource server.";
        
        sendTelegram($tgChatId, $msg, $tgToken);
        echo "[Worker] Telegram Notification Sent.\n";
    }
    
    exit;
}

// 3. Process Content (Call the logic from process_auto_content.php)
// We include the file directly. process_auto_content.php detects IS_CRON and runs.
require __DIR__ . '/process_auto_content.php';
?>
