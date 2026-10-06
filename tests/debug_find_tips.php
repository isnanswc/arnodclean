<?php
require_once 'db.php';
$stmt = $pdo->query("SELECT id, title, slug, status FROM articles WHERE title LIKE '%tips jitu%' OR slug LIKE '%tips-jitu%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
