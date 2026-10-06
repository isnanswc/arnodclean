<?php
// admin/api/get_report_data.php
session_start();
require_once '../../db.php';
header('Content-Type: application/json');

// Auth Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Params
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

try {
    // 1. Summary Cards
    // ----------------------------------------------------------------
    
    // Total & Unique
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_views, 
            COUNT(DISTINCT ip_address) as unique_visitors,
            SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bot_count
        FROM visitor_analytics 
        WHERE visited_at BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $basicStats = $stmt->fetch();
    
    // Bounce Rate & Duration (Complex logic: Session defined by IP + Date)
    // We treat 1 day = 1 session per IP for simplicity in this lighter version
    $stmt = $pdo->prepare("
        SELECT 
            ip_address, 
            visited_at,
            COUNT(*) as pages_viewed,
            MIN(created_at) as start_time,
            MAX(created_at) as end_time,
            TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) as duration_sec
        FROM visitor_analytics
        WHERE visited_at BETWEEN ? AND ? AND is_bot = 0
        GROUP BY ip_address, visited_at
    ");
    $stmt->execute([$startDate, $endDate]);
    $sessions = $stmt->fetchAll();
    
    $totalSessions = count($sessions);
    $bounces = 0;
    $totalDuration = 0;
    
    foreach($sessions as $s) {
        if($s['pages_viewed'] == 1) {
            $bounces++;
        }
        $totalDuration += $s['duration_sec'];
    }
    
    $bounceRate = $totalSessions > 0 ? round(($bounces / $totalSessions) * 100, 1) : 0;
    $avgDuration = $totalSessions > 0 ? round($totalDuration / $totalSessions) : 0; // seconds
    
    // Returning Visitors
    // Count IPs in this period that were seen BEFORE this period
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT va.ip_address) 
        FROM visitor_analytics va
        WHERE va.visited_at BETWEEN ? AND ? 
        AND va.is_bot = 0
        AND EXISTS (
            SELECT 1 FROM visitor_analytics old 
            WHERE old.ip_address = va.ip_address 
            AND old.visited_at < ?
        )
    ");
    $stmt->execute([$startDate, $endDate, $startDate]);
    $returningCount = $stmt->fetchColumn();
    $returningRate = $basicStats['unique_visitors'] > 0 ? round(($returningCount / $basicStats['unique_visitors']) * 100, 1) : 0;


    // 2. Charts: Traffic Trends
    // ----------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT 
            visited_at as date, 
            COUNT(*) as total, 
            COUNT(DISTINCT ip_address) as unique_visits,
            SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bots
        FROM visitor_analytics
        WHERE visited_at BETWEEN ? AND ?
        GROUP BY visited_at
        ORDER BY visited_at ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $trafficChart = $stmt->fetchAll();
    
    
    // 3. Peak Traffic (Heatmap Data)
    // ----------------------------------------------------------------
    // 0 = Sunday, 6 = Saturday. Hour 0-23
    $stmt = $pdo->prepare("
        SELECT 
            DAYOFWEEK(created_at) - 1 as day_index, 
            HOUR(created_at) as hour_index,
            COUNT(*) as visits
        FROM visitor_analytics
        WHERE visited_at BETWEEN ? AND ?
        GROUP BY day_index, hour_index
    ");
    $stmt->execute([$startDate, $endDate]);
    $heatmap = $stmt->fetchAll();
    

    // 4. Top Pages
    // ----------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT page_url, COUNT(*) as views 
        FROM visitor_analytics 
        WHERE visited_at BETWEEN ? AND ?
        GROUP BY page_url 
        ORDER BY views DESC 
        LIMIT 10
    ");
    $stmt->execute([$startDate, $endDate]);
    $topPages = $stmt->fetchAll();

    
    // 5. Device Stats
    // ----------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT device, COUNT(*) as count 
        FROM visitor_analytics 
        WHERE visited_at BETWEEN ? AND ?
        GROUP BY device
    ");
    $stmt->execute([$startDate, $endDate]);
    $devices = $stmt->fetchAll();
    
    
    // Response
    echo json_encode([
        'summary' => [
            'total_views' => $basicStats['total_views'],
            'unique_visitors' => $basicStats['unique_visitors'],
            'bot_count' => $basicStats['bot_count'],
            'bounce_rate' => $bounceRate,
            'avg_duration_sec' => $avgDuration,
            'returning_rate' => $returningRate
        ],
        'charts' => [
            'traffic' => $trafficChart,
            'heatmap' => $heatmap
        ],
        'top_pages' => $topPages,
        'devices' => $devices
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
