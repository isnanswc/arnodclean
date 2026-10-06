<?php
// admin/api/get_report_data.php
session_start();
require_once __DIR__ . '/../../db.php';
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

// Calculate Previous Period for Growth
$start = new DateTime($startDate);
$end = new DateTime($endDate);
$diff = $start->diff($end)->days + 1;
$prevStartDate = date('Y-m-d', strtotime("$startDate - $diff days"));
$prevEndDate = date('Y-m-d', strtotime("$endDate - $diff days"));

try {
    // Helper function for basic stats
    function getBasicStats($pdo, $s, $e) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_views, 
                COUNT(DISTINCT ip_address) as unique_visitors,
                SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bot_count
            FROM visitor_analytics 
            WHERE visited_at BETWEEN ? AND ?
        ");
        $stmt->execute([$s, $e]);
        return $stmt->fetch();
    }

    // 1. Current Stats
    $curr = getBasicStats($pdo, $startDate, $endDate);
    
    // 2. Previous Stats (For Growth)
    $prev = getBasicStats($pdo, $prevStartDate, $prevEndDate);

    // Growth Calculation Helper
    function calcGrowth($currVal, $prevVal) {
        if ($prevVal == 0) return $currVal > 0 ? 100 : 0;
        return round((($currVal - $prevVal) / $prevVal) * 100, 1);
    }
    
    $growth = [
        'total_views' => calcGrowth($curr['total_views'], $prev['total_views']),
        'unique_visitors' => calcGrowth($curr['unique_visitors'], $prev['unique_visitors']),
        'bot_count' => calcGrowth($curr['bot_count'], $prev['bot_count'])
    ];


    // 3. Advanced Metrics (Current Period Only)
    // Bounce Rate & Duration
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as pages_viewed,
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
        if($s['pages_viewed'] == 1) $bounces++;
        $totalDuration += $s['duration_sec'];
    }
    
    $bounceRate = $totalSessions > 0 ? round(($bounces / $totalSessions) * 100, 1) : 0;
    $avgDuration = $totalSessions > 0 ? round($totalDuration / $totalSessions) : 0; 
    
    // Returning Rate
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
    $returningRate = $curr['unique_visitors'] > 0 ? round(($returningCount / $curr['unique_visitors']) * 100, 1) : 0;

    // 4. Conversion Rate (Leads)
    // Assuming 'leads' table has 'created_at'
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    $totalLeads = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) FROM visitor_analytics WHERE visited_at BETWEEN ? AND ? AND is_bot = 0");
    $stmt->execute([$startDate, $endDate]);
    $uniqueHuman = $stmt->fetchColumn();
    
    $conversionRate = $uniqueHuman > 0 ? round(($totalLeads / $uniqueHuman) * 100, 2) : 0;


    // 5. Charts: Traffic Trends
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
    $trafficChart = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
     // 6. Peak Traffic (Heatmap)
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
    
    // 7. Top Pages
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

    // 8. Device Stats
    $stmt = $pdo->prepare("
        SELECT device, COUNT(*) as count 
        FROM visitor_analytics 
        WHERE visited_at BETWEEN ? AND ?
        GROUP BY device
    ");
    $stmt->execute([$startDate, $endDate]);
    $devices = $stmt->fetchAll();

    // 9. Top Cities (Geo)
    // 9. Top Cities (Geo)
    $stmt = $pdo->prepare("
        SELECT city, COUNT(DISTINCT ip_address) as visitors
        FROM visitor_analytics
        WHERE visited_at BETWEEN ? AND ? AND city != 'Unknown'
        GROUP BY city
        ORDER BY visitors DESC
        LIMIT 5
    ");
    $stmt->execute([$startDate, $endDate]);
    $topCities = $stmt->fetchAll();
    
    // 10. Top Traffic Sources (Referrer)
    // Handle NULL or 'Direct'
    $stmt = $pdo->prepare("
        SELECT COALESCE(referrer, 'Direct') as source, COUNT(*) as visits
        FROM visitor_analytics
        WHERE visited_at BETWEEN ? AND ? 
        AND is_bot = 0 
        GROUP BY source
        ORDER BY visits DESC
        LIMIT 5
    ");
    $stmt->execute([$startDate, $endDate]);
    $topSources = $stmt->fetchAll();

    // 11. WA Clicks Heatmap (Day x Hour)
    $stmtWaHeatmap = $pdo->prepare("
        SELECT 
            DAYOFWEEK(clicked_at) - 1 as day_index, 
            HOUR(clicked_at) as hour_index,
            COUNT(*) as clicks
        FROM wa_clicks
        WHERE DATE(clicked_at) BETWEEN ? AND ?
        GROUP BY day_index, hour_index
    ");
    $stmtWaHeatmap->execute([$startDate, $endDate]);
    $waHeatmap = $stmtWaHeatmap->fetchAll(PDO::FETCH_ASSOC);

    // 12. Detailed WA Clicks Log with Article Title Matching
    $stmtWaLogs = $pdo->prepare("
        SELECT w.*, a.title as article_title
        FROM wa_clicks w
        LEFT JOIN articles a ON (
            w.page_url LIKE CONCAT('%/blog/', a.slug, '%')
            OR w.page_url LIKE CONCAT('%slug=', a.slug, '%')
            OR w.page_url LIKE CONCAT('%/', a.slug)
        )
        WHERE DATE(w.clicked_at) BETWEEN ? AND ?
        ORDER BY w.clicked_at DESC
        LIMIT 300
    ");
    $stmtWaLogs->execute([$startDate, $endDate]);
    $rawWaLogs = $stmtWaLogs->fetchAll(PDO::FETCH_ASSOC);

    $waLogs = [];
    foreach ($rawWaLogs as $idx => $row) {
        $src = $row['source'];
        $page = $row['page_url'] ?? '/';
        $artTitle = $row['article_title'] ?? null;
        
        $btnLabel = '';
        if (!empty($artTitle)) {
            $btnLabel = 'Tombol WA di Artikel: "' . $artTitle . '"';
        } elseif ($src === 'floating_widget') {
            if ($page === '/' || strpos($page, 'index') !== false) {
                $btnLabel = 'Float Button di Halaman Home';
            } else {
                $btnLabel = 'Float Button Melayang (' . $page . ')';
            }
        } elseif ($src === 'form_crm') {
            $btnLabel = 'Form CRM (Pemesanan/Kontak)';
        } elseif ($src === 'hero_button') {
            $btnLabel = 'Tombol Konsultasi (Hero Banner)';
        } elseif ($src === 'service_card') {
            $btnLabel = 'Tombol WA di Kartu Layanan';
        } elseif ($src === 'header_button') {
            $btnLabel = 'Tombol WA di Header Navigasi';
        } elseif ($src === 'footer_button') {
            $btnLabel = 'Tombol WA di Footer';
        } else {
            $btnLabel = 'Tombol WA (' . $src . ')';
        }

        $waLogs[] = [
            'no' => $idx + 1,
            'id' => $row['id'],
            'ip_address' => $row['ip_address'] ?? 'Unknown',
            'source' => $src,
            'button_label' => $btnLabel,
            'article_title' => $artTitle,
            'page_url' => $page,
            'referrer' => $row['referrer'] ?? 'Direct',
            'location' => ($row['city'] && $row['city'] !== 'Unknown') ? ($row['city'] . ', ' . $row['country']) : ($row['country'] ?? 'Unknown'),
            'device' => $row['device'] ?? 'Desktop',
            'clicked_at' => $row['clicked_at']
        ];
    }

    // 13. Linear Regression Forecast & Smart AI Insight
    $forecast = [];
    $n = count($trafficChart);
    if ($n > 1) {
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0;
        foreach($trafficChart as $i => $point) {
            $x = $i;
            $y = $point['total'];
            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumXX += ($x * $x);
        }
        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        if ($denominator != 0) {
            $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
            $intercept = ($sumY - ($slope * $sumX)) / $n;
            $lastDate = new DateTime($trafficChart[$n-1]['date']);
            for($j = 1; $j <= 7; $j++) {
                $lastDate->modify('+1 day');
                $nextX = $n - 1 + $j;
                $predictedY = ($slope * $nextX) + $intercept;
                $forecast[] = [
                    'date' => $lastDate->format('Y-m-d'),
                    'val' => max(0, round($predictedY))
                ];
            }
        }
    }

    $insights = [];
    $totalViews = $curr['total_views'];
    $botViews = $curr['bot_count'];
    $botPercentage = ($totalViews > 0) ? round(($botViews / $totalViews) * 100) : 0;
    
    if ($botPercentage > 50) {
        $insights[] = "<span class='text-danger fw-bold'><i class='fa-solid fa-triangle-exclamation'></i> High Bot Activity:</span> $botPercentage% kunjungan adalah Robot. Abaikan lonjakan total view, fokus pada data Unik.";
    } elseif ($botPercentage > 20) {
        $insights[] = "Terdeteksi aktivitas robot moderat ($botPercentage%).";
    } else {
        $insights[] = "Kualitas traffik sangat bagus (<strong>" . (100-$botPercentage) . "% Manusia</strong>).";
    }

    if ($growth['total_views'] > 10) {
         $insights[] = "Traffik organik tumbuh <strong class='text-success'>signifikan (+{$growth['total_views']}%)</strong>!";
    } elseif ($growth['total_views'] < -10) {
        $insights[] = "Traffik terlihat <strong class='text-danger'>menurun ({$growth['total_views']}%)</strong>. Perlu strategi konten baru.";
    }

    if (!empty($topCities)) {
        $insights[] = "Basis audiens utama Anda di <strong>{$topCities[0]['city']}</strong> ({$topCities[0]['visitors']} orang).";
    }

    $totalWaClicksCount = count($waLogs);
    if ($totalWaClicksCount > 0) {
        $insights[] = "Terdeteksi <strong class='text-success'>$totalWaClicksCount Konversi Klik WhatsApp</strong> pada periode ini!";
    }

    $smartSummary = "<strong>Analisa AI:</strong> " . implode(" ", $insights);

    // Response
    echo json_encode([
        'summary' => [
            'total_views' => $curr['total_views'],
            'unique_visitors' => $curr['unique_visitors'],
            'bot_count' => $curr['bot_count'],
            'bounce_rate' => $bounceRate,
            'avg_duration_sec' => $avgDuration,
            'returning_rate' => $returningRate,
            'total_leads' => $totalLeads,
            'conversion_rate' => $conversionRate,
            'total_wa_clicks' => $totalWaClicksCount
        ],
        'growth' => $growth,
        'smart_summary' => $smartSummary,
        'forecast' => $forecast,
        'charts' => [
            'traffic' => $trafficChart,
            'heatmap' => $heatmap,
            'wa_heatmap' => $waHeatmap
        ],
        'top_pages' => $topPages,
        'devices' => $devices,
        'top_cities' => $topCities,
        'top_sources' => $topSources,
        'wa_logs' => $waLogs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
