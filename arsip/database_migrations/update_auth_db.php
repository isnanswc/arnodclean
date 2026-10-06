<?php
// update_auth_db.php
require_once 'db.php';

try {
    $sql = file_get_contents('auth_schema.sql');
    $pdo->exec($sql);
    echo "Auth Database Updated Successfully!";
} catch (PDOException $e) {
    die("DB Update Failed: " . $e->getMessage());
}
?>
