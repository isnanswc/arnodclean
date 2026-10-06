<?php
require 'db.php';
$stmt = $pdo->query('DESCRIBE articles');
echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT);
