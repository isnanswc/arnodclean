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

// 2. Check Queue
$queueCount = $pdo->query("SELECT COUNT(*) FROM auto_content_keywords WHERE status = 'pending'")->fetchColumn();

if ($queueCount == 0) {
    // If queue empty, maybe auto-turn off turbo mode? 
    // Let's decide: Yes, turn it off to save resources.
    $pdo->prepare("UPDATE auto_content_settings SET setting_value = '0' WHERE setting_key = 'worker_turbo_mode'")->execute();
    echo "[Worker] Queue is empty. Turning OFF Turbo Mode.\n";
    
    // Optional: Notify User via Telegram that job is done
    // ... (Code similar to daily report if needed, but keeping it simple for now)
    exit;
}

// 3. Process Content (Call the logic from process_auto_content.php)
// We include the file directly. process_auto_content.php detects IS_CRON and runs.
require __DIR__ . '/process_auto_content.php';
?>
