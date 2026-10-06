<?php
// admin/api/debug_cron.php
// Script untuk mengecek jam server dan masalah cron

date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../db.php';

$stmt = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings WHERE setting_key IN ('tg_report_time', 'last_daily_report_date')");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo "<h2>DEBUG CRON STATUS</h2>";

echo "<h3>1. Waktu</h3>";
echo "Server Time (WIB): " . date('Y-m-d H:i:s') . "<br>";
echo "Jam Sekarang: " . date('H:i') . "<br>";
echo "Target Laporan: " . ($settings['tg_report_time'] ?? 'BELUM DISET') . "<br>";

$match = (date('H:i') == ($settings['tg_report_time'] ?? ''));
echo "Status Match: " . ($match ? "<b style='color:green'>MATCH (Seharusnya Jalan)</b>" : "<b style='color:red'>TIDAK MATCH (Menunggu Jam)</b>") . "<br>";

echo "<h3>2. Status Laporan</h3>";
echo "Tanggal Report Terakhir di DB: " . ($settings['last_daily_report_date'] ?? 'KOSONG') . "<br>";
echo "Tanggal Hari Ini: " . date('Y-m-d') . "<br>";

if (($settings['last_daily_report_date'] ?? '') === date('Y-m-d')) {
    echo "Status: <b style='color:red'>SUDAH LAPOR HARI INI (Blocked)</b><br>";
    echo "<form method='post'><button name='reset_date' type='submit'>RESET REPORT STATUS NOW</button></form>";
} else {
    echo "Status: <b style='color:green'>BELUM LAPOR (Ready)</b><br>";
}

if (isset($_POST['reset_date'])) {
    $pdo->query("UPDATE auto_content_settings SET setting_value = NULL WHERE setting_key = 'last_daily_report_date'");
    echo "<br><b>SUCCESS: Report status reset! Silakan coba jalankan Cron lagi.</b><br>";
    // Refresh page
    echo "<meta http-equiv='refresh' content='2'>";
}
?>
