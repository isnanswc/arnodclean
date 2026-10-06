<?php
require_once '../../db.php';
require_once '../includes/auth.php';

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

// 1. Get Service Info
$stmt = $pdo->prepare("SELECT order_count, title, description, image_path, price_start FROM services WHERE id = ?");
$stmt->execute([$id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    echo json_encode(['status' => 'error', 'message' => 'Service not found']);
    exit;
}

// 2. Get Clicks per Day (Last 30 Days) - Split Human vs Bot
$stmt = $pdo->prepare("
    SELECT 
        DATE(clicked_at) as date, 
        SUM(CASE WHEN is_bot = 0 THEN 1 ELSE 0 END) as human_count,
        SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bot_count
    FROM service_clicks 
    WHERE service_id = ? AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(clicked_at) 
    ORDER BY date ASC
");
$stmt->execute([$id]);
$daily_data = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

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
    
    if (isset($daily_data[$d])) {
        $human_data[] = (int)$daily_data[$d]['human_count'];
        $bot_data[] = (int)$daily_data[$d]['bot_count'];
    } else {
        $human_data[] = 0;
        $bot_data[] = 0;
    }
}

// 3. Stats Summary
// - Total Human Clicks (30 Days)
// - Total Bot Clicks (30 Days)
// - Genuine Leads (Total Lifetime for this service)
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN is_bot = 0 AND clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as human_week,
        COUNT(CASE WHEN is_bot = 0 AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as human_month,
        COUNT(CASE WHEN is_bot = 1 AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as bot_month
    FROM service_clicks 
    WHERE service_id = ?
");
$stmt->execute([$id]);
$click_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Leads Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE service_id = ? AND ai_status = 'genuine'");
$stmt->execute([$id]);
$lead_count = $stmt->fetchColumn();

echo json_encode([
    'status' => 'success',
    'service' => [
        'title' => $service['title'],
        'total_orders' => $service['order_count'], // This is now Human Clicks Cumulative
        'price' => $service['price_start'],
        'image' => $service['image_path']
    ],
    'period_stats' => [
        'human_week' => $click_stats['human_week'],
        'human_month' => $click_stats['human_month'],
        'bot_month' => $click_stats['bot_month'],
        'genuine_leads' => $lead_count
    ],
    'chart' => [
        'labels' => $chart_labels,
        'human_data' => $human_data,
        'bot_data' => $bot_data
    ]
]);
?>
