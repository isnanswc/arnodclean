<?php
require_once 'db.php';

try {
    // Add ai_status column
    $pdo->exec("ALTER TABLE leads ADD COLUMN ai_status ENUM('genuine','spam','uncategorized') DEFAULT 'uncategorized'");
    echo "Added ai_status column.\n";
} catch (PDOException $e) {
    echo "ai_status column might already exist: " . $e->getMessage() . "\n";
}

try {
    // Add ai_analysis column (reasoning)
    $pdo->exec("ALTER TABLE leads ADD COLUMN ai_analysis TEXT DEFAULT NULL");
    echo "Added ai_analysis column.\n";
} catch (PDOException $e) {
    echo "ai_analysis column might already exist: " . $e->getMessage() . "\n";
}

try {
    // Add ai_confidence column
    $pdo->exec("ALTER TABLE leads ADD COLUMN ai_confidence TINYINT DEFAULT 0");
    echo "Added ai_confidence column.\n";
} catch (PDOException $e) {
    echo "ai_confidence column might already exist: " . $e->getMessage() . "\n";
}

echo "Database patch completed.";
?>
