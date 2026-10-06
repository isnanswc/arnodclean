<?php
// admin/api/get_article_analytics.php
require_once '../../db.php';
require_once '../includes/auth.php'; // Auth Helper

// Check Login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

// 1. Get Article Info (including Slug)
$stmt = $pdo->prepare("SELECT views, title, slug, content, image_path FROM articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    echo json_encode(['status' => 'error', 'message' => 'Article not found']);
    exit;
}

// 2. Get Views per Day (Last 30 Days) from visitor_analytics
// We match page_url LIKE '%slug=...'
$slug = $article['slug'];
$stmt = $pdo->prepare("
    SELECT DATE(visited_at) as date, is_bot, COUNT(*) as count 
    FROM visitor_analytics 
    WHERE page_url LIKE CONCAT('%slug=', ?, '%') 
      AND visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(visited_at), is_bot
    ORDER BY date ASC
");
$stmt->execute([$slug]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process into [date => [human=>x, bot=>y]]
$daily_stats = [];
foreach($rows as $r) {
    $date = $r['date'];
    if(!isset($daily_stats[$date])) $daily_stats[$date] = ['human'=>0, 'bot'=>0];
    
    if($r['is_bot']) $daily_stats[$date]['bot'] += $r['count'];
    else $daily_stats[$date]['human'] += $r['count'];
}

// Fill in missing dates
$chart_labels = [];
$human_data = [];
$bot_data = [];

$period = new DatePeriod(
    new DateTime('-30 days'),
    new DateInterval('P1D'),
    new DateTime('+1 day')
);

foreach ($period as $date) {
    $d = $date->format('Y-m-d');
    $chart_labels[] = $date->format('d M');
    
    $h = $daily_stats[$d]['human'] ?? 0;
    $b = $daily_stats[$d]['bot'] ?? 0;
    
    $human_data[] = $h;
    $bot_data[] = $b;
}

// 3. Simple Stats (Total Human/Bot in period)
// We already have daily stats, let's sum them up for quick stats
$total_human_week = 0;
$total_bot_week = 0;
$total_human_month = 0;
$total_bot_month = 0;

$week_date = date('Y-m-d', strtotime('-7 days'));

foreach ($period as $date) {
    $d = $date->format('Y-m-d');
    $h = $daily_stats[$d]['human'] ?? 0;
    $b = $daily_stats[$d]['bot'] ?? 0;
    
    $total_human_month += $h;
    $total_bot_month += $b;
    
    if ($d >= $week_date) {
        $total_human_week += $h;
        $total_bot_week += $b;
    }
}

echo json_encode([
    'status' => 'success',
    'article' => [
        'title' => $article['title'],
        'content' => $article['content'],
        'slug' => $article['slug'],
        'total_views' => $article['views'], // Legacy Total
        'image' => $article['image_path']
    ],
    'period_stats' => [
        'week_human' => $total_human_week,
        'week_bot' => $total_bot_week,
        'month_human' => $total_human_month,
        'month_bot' => $total_bot_month
    ],
    'chart' => [
        'labels' => $chart_labels,
        'human' => $human_data,
        'bot' => $bot_data
    ]
]);
?>
