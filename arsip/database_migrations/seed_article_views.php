<?php
require_once 'db.php';

// Check if we need to seed
$count = $pdo->query("SELECT COUNT(*) FROM article_views WHERE viewed_at < DATE(NOW())")->fetchColumn();
if ($count > 5) {
    echo "Data already exists for past days.\n";
    exit;
}

echo "Seeding article views for the last 7 days...\n";

$article_ids = $pdo->query("SELECT id FROM articles LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
if (empty($article_ids)) {
    // Fallsback if no articles
    exit("No articles to seed views for.\n");
}

for ($i = 6; $i >= 1; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    // Random 3-10 views per day
    $views = rand(3, 10);
    
    echo "Date $date: Adding $views views\n";
    
    for ($j = 0; $j < $views; $j++) {
        $aid = $article_ids[array_rand($article_ids)];
        $hour = rand(8, 22);
        $minute = rand(0, 59);
        $second = rand(0, 59);
        $datetime = "$date $hour:$minute:$second";
        
        $stmt = $pdo->prepare("INSERT INTO article_views (article_id, ip_address, viewed_at) VALUES (?, '127.0.0.1', ?)");
        $stmt->execute([$aid, $datetime]);
    }
}
echo "Done.\n";
?>
