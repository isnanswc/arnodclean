<?php
require_once 'db.php';
echo "Searching for articles (broad)...\n";
$stmt = $pdo->query("SELECT id, title, slug, status FROM articles ORDER BY id DESC LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
?>
