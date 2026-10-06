<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'db.php';
echo "STATUS_COUNTS:\n";
print_r($pdo->query("SELECT status, COUNT(*) FROM articles GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR));
echo "\nCONTACT_INFO:\n";
print_r($pdo->query("SELECT * FROM contact_info")->fetchAll(PDO::FETCH_KEY_PAIR));
echo "\nSITE_SETTINGS:\n";
print_r($pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'wa%'")->fetchAll(PDO::FETCH_KEY_PAIR));
