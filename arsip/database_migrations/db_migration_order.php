<?php
// admin/db_migration_order.php
require_once __DIR__ . '/../db.php';

try {
    echo "Checking auto_content_keywords table structure...\n";
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM auto_content_keywords LIKE 'process_order'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "Adding 'process_order' column...\n";
        $pdo->exec("ALTER TABLE auto_content_keywords ADD COLUMN process_order INT DEFAULT 0 AFTER status");
        
        // Initialize order based on ID to preserve current valid order
        $pdo->exec("SET @rn = 0; UPDATE auto_content_keywords SET process_order = (@rn:=@rn+1) ORDER BY id ASC;");
        
        echo "Column added and initialized.\n";
    } else {
        echo "'process_order' column already exists.\n";
    }

    echo "Migration completed successfully.";

} catch (PDOException $e) {
    die("Migration Error: " . $e->getMessage());
}
?>
