<?php
// db.php
date_default_timezone_set('Asia/Jakarta');
// Konfigurasi Database — Gunakan konstanta dari config.php
require_once __DIR__ . '/config.php';

// Gunakan konstanta dari config.php (tidak hardcode lagi)
$host = DB_HOST;
$dbname = DB_NAME;
$username = DB_USER;
$password = DB_PASS;


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    // Log error to file/system
    error_log($e->getMessage());

    // Cek apakah diakses dari localhost / CLI untuk kemudahan debugging developer
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocal = in_array($clientIp, ['127.0.0.1', '::1', 'localhost']) || (php_sapi_name() === 'cli');
    if ($isLocal) {
        die("<h1>Error Koneksi Database (Lokal)</h1><p><strong>Pesan:</strong> " . htmlspecialchars($e->getMessage()) . "</p><p>Pastikan MySQL berjalan dan database <code>" . htmlspecialchars($dbname) . "</code> tersedia. Anda dapat membuat file <code>config.local.php</code> untuk menyesuaikan user/password lokal XAMPP.</p>");
    }

    if ($e->getCode() == 1049) {
        die("<h1>Error Konfigurasi Database</h1><p>Database tidak ditemukan.</p>");
    }
    // Production Mode: Hide raw errors
    die("<h1>Gangguan Sistem</h1><p>Maaf, sedang terjadi gangguan koneksi database. Silahkan coba beberapa saat lagi.</p>");
}

// End of file (Omitted closing tag to prevent whitespace issues)
