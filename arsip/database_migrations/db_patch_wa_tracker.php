<?php
// db_patch_wa_tracker.php
require_once __DIR__ . '/db.php';

echo "<pre>\n";
echo "Starting Database Patch for WhatsApp Click Tracker & CRM...\n";

try {
    // 1. Create wa_clicks table
    $sqlWaClicks = "CREATE TABLE IF NOT EXISTS `wa_clicks` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `lead_id` INT NULL DEFAULT NULL,
      `service_id` INT NULL DEFAULT NULL,
      `source` VARCHAR(50) NOT NULL DEFAULT 'button',
      `page_url` VARCHAR(255) DEFAULT NULL,
      `referrer` VARCHAR(255) DEFAULT NULL,
      `ip_address` VARCHAR(45) DEFAULT NULL,
      `device` VARCHAR(50) DEFAULT 'Desktop',
      `country` VARCHAR(100) DEFAULT 'Unknown',
      `city` VARCHAR(100) DEFAULT 'Unknown',
      `clicked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX (`source`),
      INDEX (`clicked_at`),
      INDEX (`lead_id`),
      INDEX (`page_url`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sqlWaClicks);
    echo "[SUCCESS] Table 'wa_clicks' checked/created.\n";

    // 2. Ensure leads table has tracking columns
    $columnsToAdd = [
        'ip_address' => "VARCHAR(45) DEFAULT NULL",
        'referrer' => "VARCHAR(255) DEFAULT NULL",
        'page_url' => "VARCHAR(255) DEFAULT NULL",
        'device' => "VARCHAR(50) DEFAULT 'Desktop'",
        'country' => "VARCHAR(100) DEFAULT 'Unknown'",
        'city' => "VARCHAR(100) DEFAULT 'Unknown'",
        'ai_status' => "ENUM('genuine','spam','uncategorized') DEFAULT 'uncategorized'",
        'ai_analysis' => "TEXT DEFAULT NULL",
        'ai_confidence' => "TINYINT DEFAULT 0"
    ];

    foreach ($columnsToAdd as $col => $definition) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `leads` LIKE '$col'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `leads` ADD COLUMN `$col` $definition");
            echo "[SUCCESS] Added column '$col' to 'leads'.\n";
        } else {
            echo "[INFO] Column '$col' already exists in 'leads'.\n";
        }
    }

    echo "\nDatabase Patch Completed Successfully!\n";

} catch (PDOException $e) {
    echo "[ERROR] DB Patch Failed: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
?>
