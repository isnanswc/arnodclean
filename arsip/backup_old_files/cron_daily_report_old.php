<?php
// admin/api/cron_daily_report.php

// Define IS_CRON if run from CLI
date_default_timezone_set('Asia/Jakarta');

if (php_sapi_name() === 'cli') {
    if (!defined('IS_CRON')) define('IS_CRON', true);
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

function cliLog($msg) {
    $logMsg = "[" . date('Y-m-d H:i:s') . "] $msg" . PHP_EOL;
    // Echo to screen (for manual run) and Append to file (for debugging)
    echo $logMsg;
    file_put_contents(__DIR__ . '/cron_debug.log', $logMsg, FILE_APPEND);
}

// Security Check for Web Access
if (isset($_GET['key']) && $_GET['key'] === 'adc_cron_secure') {
    if (!defined('IS_CRON')) define('IS_CRON', true);
}

if (!defined('IS_CRON')) {
    checkLogin(); // Enforce Admin Login
    
    // For Manual Trigger (Force), Enforce CSRF
    if (isset($_GET['force']) || isset($_POST['force'])) {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            die("Error: Invalid CSRF Token"); // AJAX will catch this
        }
    }
}

try {
    // 0. File Locking (Prevent Race Conditions)
    $lockFile = __DIR__ . '/daily_report.lock';
    $fp = fopen($lockFile, 'w+');
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        // Locked by another process
        if (defined('IS_CRON')) cliLog("Process locked. Skipping.");
        exit;
    }

    if (defined('IS_CRON')) cliLog("Memulai Daily Executive Report...");

    // 1. Fetch Settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings WHERE setting_key IN ('tg_chat_id', 'tg_bot_token', 'ai_api_key', 'tg_report_time', 'ai_model')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Fetch full settings for new config structure
    $allSettings = [];
    $stmtAll = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings");
    while ($row = $stmtAll->fetch()) {
        $allSettings[$row['setting_key']] = $row['setting_value'];
    }

    $chatId = $settings['tg_chat_id'] ?? '';
    $botToken = $settings['tg_bot_token'] ?? '';
    
    // Legacy support + New Config
    // A.1 Determine AI Provider & Credentials
    $provider = $allSettings['ai_active_provider'] ?? 'gemini';
    $apiKey = '';
    $aiModel = '';

    if ($provider === 'groq') {
        $groqKeys = json_decode($allSettings['ai_config_groq_keys'] ?? '[]', true);
        if (!empty($groqKeys)) $apiKey = $groqKeys[0]; // Use first key
        $aiModel = $allSettings['ai_config_groq_model'] ?? 'llama-3.3-70b-versatile';
    } else {
        // Gemini (Default)
        $geminiKeys = json_decode($allSettings['ai_config_gemini_keys'] ?? '[]', true);
        if (!empty($geminiKeys)) $apiKey = $geminiKeys[0];
        if (empty($apiKey)) $apiKey = $settings['ai_api_key'] ?? ''; // Fallback
        $aiModel = $allSettings['ai_config_gemini_model'] ?? ($settings['ai_model'] ?? 'gemini-1.5-flash');
    }

    $reportTime = $settings['tg_report_time'] ?? '';
    
    $lastReportParams = $pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key = 'last_daily_report_date'")->fetchColumn(); 


    // Validate Auth
    if (empty($chatId) || empty($botToken)) {
        throw new Exception("Telegram Bot Token atau Chat ID belum dikonfigurasi.");
    }
    
    // 2. Schedule Check (Only if running via Cron/CLI without force flag)
    // If run manually via browser, we might want to force it? Let's check a GET param 'force'
    $force = isset($_GET['force']);
    
    if (defined('IS_CRON') && !$force) {
        if (empty($reportTime)) {
            cliLog("Jadwal laporan otomatis dimatikan.");
            exit;
        }
        
        // Cek waktu sekarang (format HH:mm)
        $currentTime = date('H:i');
        
        // Exact match check (Worker runs every 60s, so this should hit)
        if ($currentTime !== $reportTime) {
            cliLog("Belum waktunya laporan. Jadwal: $reportTime, Sekarang: $currentTime.");
            exit;
        }

        // Cek apakah sudah lapor hari ini
        $todayStr = date('Y-m-d');
        if ($lastReportParams === $todayStr) {
            cliLog("Laporan untuk tanggal $todayStr sudah dikirim.");
            exit;
        }
    }


    // 3. Generate Report
    require_once __DIR__ . '/../includes/functions.php';
    generateAIReport($chatId, $botToken, $apiKey, $pdo, $aiModel, $provider);

    // Update Last Report Date
    $todayStr = date('Y-m-d');
    $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES ('last_daily_report_date', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
        ->execute([$todayStr]);


    $msg = "Laporan terkirim ke: $chatId";
    if (defined('IS_CRON')) cliLog($msg);
    else echo $msg;

    // Release Lock
    flock($fp, LOCK_UN);
    fclose($fp);

} catch (Exception $e) {
    if (isset($fp)) { flock($fp, LOCK_UN); fclose($fp); }
    $err = "Error: " . $e->getMessage();
    if (defined('IS_CRON')) cliLog($err);
    else echo $err;
}
?>
