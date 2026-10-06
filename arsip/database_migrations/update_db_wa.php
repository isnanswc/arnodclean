<?php
require_once 'db.php';

try {
    // 1. Create settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT
    )");
    echo "Table 'settings' checked/created.<br>";

    // Seed default settings if not exist
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute(['whatsapp_number', '628123456789']);
    $stmt->execute(['whatsapp_template', 'Hallo, Permisi kak. saya mau pesan layanan [nama layanan]']);
    echo "Default settings seeded.<br>";

    // 2. Add order_count to services
    // Check if column exists first
    $stmt = $pdo->query("SHOW COLUMNS FROM services LIKE 'order_count'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE services ADD COLUMN order_count INT DEFAULT 0");
        echo "Column 'order_count' added to 'services'.<br>";
    } else {
        echo "Column 'order_count' already exists.<br>";
    }

    echo "Database update completed successfully.";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
