<?php
// update_article_views.php
require_once 'db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS article_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            INDEX (article_id),
            INDEX (viewed_at)
        );
    ");
    echo "Table article_views created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
