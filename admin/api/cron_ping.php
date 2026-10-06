<?php
// admin/api/cron_ping.php
// Script super simpel untuk tes apakah Cron Job cPanel jalan atau tidak.

date_default_timezone_set('Asia/Jakarta');
$logFile = __DIR__ . '/cron_ping.log';
$time = date('Y-m-d H:i:s');

// 1. Coba tulis log
$message = "[$time] PING BERHASIL! Cron Job berjalan dengan baik.\n";
file_put_contents($logFile, $message, FILE_APPEND);

// 2. Output text (biasanya dikirim ke email user oleh cPanel)
echo "Cron Ping Success at $time";
?>
