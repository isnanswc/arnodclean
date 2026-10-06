<?php
require_once 'db.php';

try {
    // 1. Update existing nulls to Today
    $pdo->exec("UPDATE article_views SET viewed_at = NOW() WHERE viewed_at IS NULL");
    echo "Success: Updated null viewed_at records.<br>";

    // 2. Try to alter column to TIMESTAMP DEFAULT CURRENT_TIMESTAMP (if supported/desired)
    // Or just ensure it's DATETIME using MODIFY
    $pdo->exec("ALTER TABLE article_views MODIFY viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    echo "Success: Altered viewed_at to TIMESTAMP DEFAULT CURRENT_TIMESTAMP.<br>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
