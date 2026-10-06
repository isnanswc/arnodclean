<?php
require 'c:/xampp/htdocs/adc/db.php';
try {
    echo "--- LEADS TABLE ---\n";
    $stmt = $pdo->query("DESCRIBE leads");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        echo $r['Field'] . " (" . $r['Type'] . ")\n";
    }
    echo "\n--- SERVICES TABLE ---\n";
    $stmt = $pdo->query("DESCRIBE services");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        echo $r['Field'] . " (" . $r['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
