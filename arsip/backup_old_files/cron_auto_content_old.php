<?php
/**
 * admin/api/cron_auto_content.php
 * Smart Cron Logic
 */

date_default_timezone_set('Asia/Jakarta'); 

// Security Check for Web Access
if (isset($_GET['key']) && $_GET['key'] === 'adc_cron_secure') {
    if (!defined('IS_CRON')) define('IS_CRON', true);
}

// Block direct web access without key (unless CLI)
if (php_sapi_name() !== 'cli' && !defined('IS_CRON')) {
    http_response_code(403);
    die("Access Denied");
}

require_once __DIR__ . '/../../db.php';

echo "[CRON " . date('Y-m-d H:i:s') . "] Checking Schedule...\n";

// 0. File Locking
$lockFile = __DIR__ . '/auto_content.lock';
$fp = fopen($lockFile, 'w+');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    die(">> Locked. Skipping.\n");
}

// 1. Get Settings
$settings = [];
$stmt = $pdo->query("SELECT * FROM auto_content_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// 2. Check Enabled
if (($settings['ai_schedule_enabled'] ?? '0') !== '1') {
    die(">> Schedule is DISABLED.\n");
}

// 3. Check Active Days
$activeDays = json_decode($settings['ai_schedule_days'] ?? '[]', true);
if (empty($activeDays)) $activeDays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

$todayDay = date('D');
if (!in_array($todayDay, $activeDays)) {
    die(">> Today ($todayDay) is not an active day.\n");
}

// 4. Check Frequency Cap (Daily Limit)
$limit = (int)($settings['ai_schedule_frequency'] ?? 3);
$todayCount = $pdo->query("SELECT COUNT(*) FROM auto_content_keywords WHERE status = 'done' AND DATE(processed_at) = CURDATE()")->fetchColumn();

if ($todayCount >= $limit) {
    die(">> Daily Quota Reached ($todayCount / $limit).\n");
}

// 5. Check Mode Logic
$mode = $settings['ai_schedule_mode'] ?? 'smart';

if ($mode === 'smart') {
    // Smart Hours: 09, 12, 17, 20
    $currentHour = (int)date('H');
    $smartHours = [9, 12, 17, 20];
    
    // Allow execution if we are in the correct hour window (e.g., 09:00 - 09:59)
    if (!in_array($currentHour, $smartHours)) {
        die(">> Not a Smart Hour (Current: $currentHour. Target: " . implode(',', $smartHours) . ")\n");
    }
    
    // Check flood control (prevent multiple posts in the same hour block)
    $lastRun = $pdo->query("SELECT MAX(processed_at) FROM auto_content_keywords WHERE status = 'done'")->fetchColumn();
    if ($lastRun) {
        $lastRunTS = strtotime($lastRun);
        // If last run was less than 50 minutes ago, skip
        if ((time() - $lastRunTS) < 3000) {
            die(">> Already posted recently (Flood Control).\n");
        }
    }

} else {
    // Interval Mode (Once every X days at Specific Time)
    $intervalDays = (int)($settings['ai_schedule_interval'] ?? 1);
    $targetTime = $settings['ai_schedule_time'] ?? '08:00';
    
    // Check Time (Allow execution if current time >= target time, but within same hour)
    // Simpler: Just check if we haven't processed TODAY yet.
    if ($todayCount > 0) {
        die(">> Already ran today (Interval Mode limit is 1/day effectively by logic).\n");
    }
    
    if (date('H:i') < $targetTime) {
        die(">> Too early ($targetTime).\n");
    }
    
    // Check Day Interval
    $lastRun = $pdo->query("SELECT MAX(processed_at) FROM auto_content_keywords WHERE status = 'done'")->fetchColumn();
    if ($lastRun) {
        $lastRunDate = date('Y-m-d', strtotime($lastRun));
        $daysDiff = (strtotime(date('Y-m-d')) - strtotime($lastRunDate)) / 86400;
        
        if ($daysDiff < $intervalDays) {
            die(">> Interval not reached (Diff: $daysDiff days).\n");
        }
    }
}

// 6. Execute Process
echo ">> Conditions Met. Starting Auto Content Process...\n";
define('IS_CRON', true);
require __DIR__ . '/process_auto_content.php';
?>
