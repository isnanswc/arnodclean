<?php
require_once 'db.php';

try {
    $pdo->exec("ALTER TABLE article_views ADD COLUMN ip_address VARCHAR(45) AFTER article_id");
    echo "Success: Added ip_address column to article_views.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
