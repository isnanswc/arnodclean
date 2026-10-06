<?php
require 'db.php';
try {
    // Check if column exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'visitor_analytics' AND COLUMN_NAME = 'created_at'");
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        $pdo->exec("ALTER TABLE visitor_analytics ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Column created_at added successfully.";
    } else {
        echo "Column created_at already exists.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
