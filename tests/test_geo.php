<?php
// test_geo.php
require_once 'db.php';

echo "Checking latest visitor data for coordinates...\n\n";

try {
    $stmt = $pdo->query("SELECT id, ip_address, city, lat, lng, created_at FROM visitor_analytics ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "No data found in visitor_analytics.";
    } else {
        printf("%-5s | %-15s | %-15s | %-10s | %-10s\n", "ID", "IP", "City", "Lat", "Lng");
        echo str_repeat("-", 60) . "\n";
        foreach ($rows as $r) {
            printf("%-5d | %-15s | %-15s | %-10s | %-10s\n", 
                $r['id'], 
                $r['ip_address'], 
                $r['city'], 
                $r['lat'] ?? 'NULL', 
                $r['lng'] ?? 'NULL'
            );
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
