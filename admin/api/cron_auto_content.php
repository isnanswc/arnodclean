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

// 5. Check Mode Logic (Smart Forgiving & Distributed)
$mode = $settings['ai_schedule_mode'] ?? 'smart';

if ($mode === 'smart') {
    // Smart Hours: 09, 12, 17, 20
    $schedule = [9, 12, 17, 20];
    $currentHour = (int)date('H');

    // A. Check if we are active yet (Before 09:00, do nothing)
    if ($currentHour < $schedule[0]) {
        die(">> Too early (Start at {$schedule[0]}:00).\n");
    }

    // B. Determine which "Window" we are in
    // Window 0: 09:00 - 11:59
    // Window 1: 12:00 - 16:59
    // ...
    $currentWindowIndex = -1;
    foreach ($schedule as $idx => $startHour) {
        if ($currentHour >= $startHour) {
            $currentWindowIndex = $idx;
        }
    }

    // C. Calculate Distributed Target
    // Example: Limit 10. Slots 4.
    // Per Slot = ceil(10/4) = 3.
    // Window 0 Target: 3
    // Window 1 Target: 6
    // Window 2 Target: 9
    // Window 3 Target: 10 (Max Limit)
    
    $totalWindows = count($schedule);
    $perWindowCap = round($limit / $totalWindows);
    
    // Calculate Cumulative Target for current window
    // If it's the last window, just use the Total Limit to avoid overshooting math
    if ($currentWindowIndex >= ($totalWindows - 1)) {
        $windowTarget = $limit; 
    } else {
        $windowTarget = $perWindowCap * ($currentWindowIndex + 1);
        // Ensure we don't exceed total limit in intermediate math (rare but safe)
        if ($windowTarget > $limit) $windowTarget = $limit;
    }

    // D. Evaluation
    echo ">> Window Info: Index $currentWindowIndex (Start {$schedule[$currentWindowIndex]}:00). Target Accum: $windowTarget. Done Today: $todayCount.\n";

    if ($todayCount >= $windowTarget) {
        die(">> Quota for current time block satisfied. Waiting for next block.\n");
    }

    // E. Flood Control (Reduced to 10 mins to allow catch-up)
    // If we need to post 3 articles in a window, and cron runs every 15 mins,
    // we should allow it to post every run until target met.
    $lastRun = $pdo->query("SELECT MAX(processed_at) FROM auto_content_keywords WHERE status = 'done'")->fetchColumn();
    if ($lastRun) {
        $lastRunTS = strtotime($lastRun);
        // 600 seconds = 10 minutes gap
        if ((time() - $lastRunTS) < 600) {
            die(">> Already posted recently (Flood Control 10m).\n");
        }
    }

} else {
    // Interval Mode logic remains same, but make sure it uses similar forgiving 'already ran today' check just in case.
    // Existing logic was: if ($todayCount > 0) die... which is strict 1 per day.
    // Let's keep Interval Mode simple as requested mostly for Smart Mode.
    
    // Interval Mode (Once every X days at Specific Time)
    $intervalDays = (int)($settings['ai_schedule_interval'] ?? 1);
    $targetTime = $settings['ai_schedule_time'] ?? '08:00';
    
    // Existing logic is fine for strict interval, but let's make time check forgiving
    if ($todayCount > 0) {
        die(">> Already ran today (Interval Mode).\n");
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
