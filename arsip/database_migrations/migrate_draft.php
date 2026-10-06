<?php
require 'db.php';

try {
    // 1. Add status column to articles table
    $pdo->exec("ALTER TABLE articles ADD COLUMN status ENUM('published', 'draft') DEFAULT 'published' AFTER views");
    echo "Column 'status' added to 'articles' table.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'status' already exists in 'articles' table.\n";
    } else {
        echo "Error adding column 'status': " . $e->getMessage() . "\n";
    }
}

// 2. Ensure ai_auto_publish setting exists in auto_content_settings
$default_publish = '1';
$stmt = $pdo->prepare("INSERT IGNORE INTO auto_content_settings (setting_key, setting_value) VALUES ('ai_auto_publish', ?)");
$stmt->execute([$default_publish]);
echo "Setting 'ai_auto_publish' ensured in 'auto_content_settings'.\n";
?>
