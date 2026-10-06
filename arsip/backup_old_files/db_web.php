<?php
// db.php
// Konfigurasi Database
$host = 'localhost';
$dbname = 'uqzgndlp_arnod_clean_db';
$username = 'uqzgndlp_arno';
$password = 'Arno000000000!';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Jika koneksi gagal, tampilkan pesan error
    // check if it's a "Unknown database" error
    if ($e->getCode() == 1049) {
        die("<h1>Error Konfigurasi Database</h1><p>Database ($dbname) tidak ditemukan.</p>");
    }
    // Production Mode: Hide raw errors
    error_log($e->getMessage()); // Log error to file/system
    die("<h1>Gangguan Sistem</h1><p>Maaf, sedang terjadi gangguan koneksi database. Silahkan coba beberapa saat lagi.</p>");
}

// End of file (Omitted closing tag to prevent whitespace issues)
