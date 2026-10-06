<?php
require_once __DIR__ . '/../db.php';

try {
    echo "Checking columns...\n";
    
    // Check if content_source exists
    $cols = $pdo->query("SHOW COLUMNS FROM articles LIKE 'content_source'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE articles ADD COLUMN content_source VARCHAR(50) DEFAULT 'Manual'");
        echo "Added content_source column.\n";
    }

    // Check if image_source exists
    $cols = $pdo->query("SHOW COLUMNS FROM articles LIKE 'image_source'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE articles ADD COLUMN image_source VARCHAR(50) DEFAULT NULL");
        echo "Added image_source column.\n";
    }
    
    echo "Done.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
