<?php
require_once 'db.php';
echo "Searching for articles...\n";
$stmt = $pdo->query("SELECT id, title, slug, status FROM articles WHERE title LIKE '%sofa kulit%' OR slug LIKE '%sofa-kulit%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
?>
