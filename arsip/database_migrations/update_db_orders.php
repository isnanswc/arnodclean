<?php
require_once 'db.php';

try {
    $pdo->exec("ALTER TABLE services ADD COLUMN order_count INT DEFAULT 0");
    echo "Column order_count added successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column order_count already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
