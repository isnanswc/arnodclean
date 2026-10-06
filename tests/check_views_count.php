<?php
require_once 'db.php';
try {
    $count = $pdo->query("SELECT COUNT(*) FROM article_views")->fetchColumn();
    echo "Total Views: " . $count . "\n";
    
    if ($count == 0) {
        echo "Table is empty.\n";
    } else {
        echo "Table has data.\n";
        // Show sample dates
        $stmt = $pdo->query("SELECT viewed_at FROM article_views ORDER BY viewed_at DESC LIMIT 5");
        while($r = $stmt->fetch()) {
            echo "Sample: " . $r['viewed_at'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
