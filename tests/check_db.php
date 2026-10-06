<?php
require 'c:/xampp/htdocs/adc/db.php';
try {
    $stmt = $pdo->query("DESCRIBE visitor_analytics");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('page_url', $columns)) {
        echo "Column page_url EXISTS";
    } else {
        echo "Column page_url MISSING";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
