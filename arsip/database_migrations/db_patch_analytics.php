<?php
require 'db.php';

try {
    echo "Patching visitor_analytics table...\n";
    
    // Add page_url column if not exists
    $stmt = $pdo->query("SHOW COLUMNS FROM visitor_analytics LIKE 'page_url'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE visitor_analytics ADD COLUMN page_url VARCHAR(255) DEFAULT '/' AFTER ip_address");
        echo "Added column 'page_url'.\n";
    } else {
        echo "Column 'page_url' already exists.\n";
    }

    echo "Done.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
