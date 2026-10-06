<?php
// admin/db_migration_map.php
require_once __DIR__ . '/../db.php';

try {
    echo "Checking visitor_analytics table structure...\n";
    
    // Check if columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM visitor_analytics LIKE 'lat'");
    $latExists = $stmt->fetch();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM visitor_analytics LIKE 'lng'");
    $lngExists = $stmt->fetch();
    
    if (!$latExists) {
        echo "Adding 'lat' column...\n";
        $pdo->exec("ALTER TABLE visitor_analytics ADD COLUMN lat DECIMAL(10, 8) NULL AFTER city");
    } else {
        echo "'lat' column already exists.\n";
    }

    if (!$lngExists) {
        echo "Adding 'lng' column...\n";
        $pdo->exec("ALTER TABLE visitor_analytics ADD COLUMN lng DECIMAL(11, 8) NULL AFTER lat");
    } else {
        echo "'lng' column already exists.\n";
    }
    
    echo "Migration completed successfully.";

} catch (PDOException $e) {
    die("Migration Error: " . $e->getMessage());
}
?>
