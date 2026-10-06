<?php
// update_service_analytics.php
require_once 'db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS service_clicks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id INT NOT NULL,
            clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            INDEX (service_id),
            INDEX (clicked_at)
        );
    ");
    echo "Table service_clicks created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
