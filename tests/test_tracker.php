<?php
require 'db.php'; // Include DB once

// Simulate Visits
// 1. Visit Home
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Test Browser)';
$_SERVER['REQUEST_URI'] = '/';
// Mock device detection if needed, or rely on tracker defaults
require 'includes/tracker.php';
echo "Visited Home. ";

// 2. Visit Article List
$_SERVER['REQUEST_URI'] = '/articles.php';
require 'includes/tracker.php';
echo "Visited Articles. ";

// 3. Visit Article Detail
$_SERVER['REQUEST_URI'] = '/article.php?slug=test-article';
require 'includes/tracker.php';
echo "Visited Article Detail. ";

// Check DB
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT page_url FROM visitor_analytics WHERE ip_address = ? AND visited_at = ?");
$stmt->execute(['127.0.0.1', $today]);
echo "\n\nDB Records for today:\n";
foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $url) {
    echo "- $url\n";
}

