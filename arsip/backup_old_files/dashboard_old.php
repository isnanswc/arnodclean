<?php
// admin/dashboard.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';
require_once 'includes/auth.php';
checkLogin();

// 1. Get Range from GET
$range = $_GET['range'] ?? '7days';

// Helper to get date condition
function getDateCondition($range, &$params) {
    switch ($range) {
        case 'today':
            $params['date'] = date('Y-m-d');
            return "visited_at = :date";
        case '30days':
            $params['days'] = 30;
            return "visited_at >= DATE(NOW()) - INTERVAL :days DAY";
        case 'month':
            $params['month'] = date('Y-m') . '%';
            return "visited_at LIKE :month";
        case '7days':
        default:
            $params['days'] = 7;
            return "visited_at >= DATE(NOW()) - INTERVAL :days DAY";
    }
}

$params = [];
$condition = getDateCondition($range, $params);

// Initialize all analytics variables to prevent undefined notices
$count_total = 0; $count_today = 0; $count_range = 0; $count_unique_total = 0; $ai_queue_count = 0;
$chart_Label = []; $chart_Data = [];
$device_Label = []; $device_Data = [];
$article_Label = []; $article_Data = [];
$artView_Label = []; $artView_Data = [];
$service_Label = []; $service_Data = [];
$recent = [];
$php_version = PHP_VERSION;
$db_size = 0; $disk_usage = 0;

try {
    // 1. Counters
    $count_total = $pdo->query("SELECT COUNT(*) FROM visitor_analytics")->fetchColumn();
    
    $stmtToday = $pdo->prepare("SELECT COUNT(*) FROM visitor_analytics WHERE visited_at = ?");
    $stmtToday->execute([date('Y-m-d')]);
    $count_today = $stmtToday->fetchColumn();

    $stmtRange = $pdo->prepare("SELECT COUNT(*) FROM visitor_analytics WHERE $condition");
    $stmtRange->execute($params);
    $count_range = $stmtRange->fetchColumn();

    $count_unique_total = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_analytics")->fetchColumn();

    // AI Queue Count
    $ai_queue_count = $pdo->query("SELECT COUNT(*) FROM auto_content_keywords WHERE status = 'pending'")->fetchColumn();

    // 2. Chart: Visits based on range (Split Human vs Bot)
    $chartParams = [];
    $chartCond = getDateCondition($range, $chartParams);
    // Fetch totals by day
    $stmt = $pdo->prepare("SELECT visited_at, SUM(CASE WHEN is_bot = 0 THEN 1 ELSE 0 END) as human_count, SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bot_count FROM visitor_analytics WHERE $chartCond GROUP BY visited_at ORDER BY visited_at ASC");
    $stmt->execute($chartParams);
    $human_Data = [];
    $bot_Data = [];
    while($row = $stmt->fetch()) {
        $chart_Label[] = date('d M', strtotime($row['visited_at']));
        $human_Data[] = $row['human_count'];
        $bot_Data[] = $row['bot_count'];
    }

    // 3. Chart: Device
    $stmt = $pdo->query("SELECT device, COUNT(*) as count FROM visitor_analytics GROUP BY device");
    while($row = $stmt->fetch()) {
        $device_Label[] = $row['device'];
        $device_Data[] = $row['count'];
    }
    
    $recent = $pdo->query("SELECT * FROM visitor_analytics ORDER BY created_at DESC LIMIT 5")->fetchAll();
    
    // 4. Popular Articles Chart
    $stmt = $pdo->query("SELECT title, views FROM articles ORDER BY views DESC LIMIT 5");
    if($stmt) {
        while($row = $stmt->fetch()) {
            $article_Label[] = substr($row['title'], 0, 20) . '...';
            $article_Data[] = $row['views'];
        }
    }

    // 5. Article Views Trend (Safe check)
    try {
        $avParams = [];
        $avCond = str_replace('visited_at', 'viewed_at', getDateCondition($range, $avParams));
        $stmtAv = $pdo->prepare("SELECT DATE(viewed_at) as viewed_date, COUNT(*) as count FROM article_views WHERE $avCond GROUP BY DATE(viewed_at) ORDER BY viewed_date ASC");
        $stmtAv->execute($avParams);
        while($row = $stmtAv->fetch()) {
            $artView_Label[] = date('d M', strtotime($row['viewed_date']));
            $artView_Data[] = $row['count'];
        }
    } catch (PDOException $e) { }

    // 6. Popular Services (Based on Leads Count)
    $stmtSv = $pdo->query("
        SELECT s.title, COUNT(l.id) as real_order_count 
        FROM services s 
        LEFT JOIN leads l ON s.id = l.service_id AND l.ai_status = 'genuine'
        GROUP BY s.id 
        ORDER BY real_order_count DESC 
        LIMIT 5
    ");
    if($stmtSv) {
        while($row = $stmtSv->fetch()) {
             $service_Label[] = $row['title'];
             $service_Data[] = $row['real_order_count'];
        }
    }
    
    // 7. Server Stats
    $db_size = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema = (SELECT DATABASE())")->fetchColumn();
    $disk_free = @disk_free_space("/");
    $disk_total = @disk_total_space("/");
    if ($disk_total > 0) {
        $disk_usage = round(100 - (($disk_free / $disk_total) * 100), 1);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
}

require_once 'includes/header.php'; 
?>

<!-- Modern Dashboard CSS -->
<style>
    :root {
        --primary-color: #0d6efd;
        --secondary-color: #6c757d;
        --success-color: #198754;
        --info-color: #0dcaf0;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
        --light-bg: #f8f9fa;
        --card-radius: 16px;
        --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        --font-main: 'Inter', system-ui, -apple-system, sans-serif;
    }
    
    body {
        background-color: #f3f4f6;
        font-family: var(--font-main);
        color: #1f2937;
    }

    h1, h2, h3, h4, h5, h6 { font-weight: 700; letter-spacing: -0.025em; }

    /* Card Styling */
    .card {
        border: none;
        border-radius: var(--card-radius);
        box-shadow: var(--card-shadow);
        background: #ffffff;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        margin-bottom: 0; 
    }
    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.06);
    }
    .card-header {
        background: transparent;
        border-bottom: 1px solid rgba(0,0,0,0.03);
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .card-header h5 { margin: 0; font-size: 1.1rem; color: #111827; }
    .card-body { padding: 1.5rem; }

    /* Stat Cards */
    .stat-card-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }
    .stat-card-value {
        font-size: 2rem;
        font-weight: 800;
        color: #111827;
        line-height: 1;
        margin-bottom: 0.25rem;
    }
    .stat-icon-wrapper {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem;
    }

    /* Table Styling */
    .table-modern { margin-bottom: 0; }
    .table-modern thead th {
        background-color: #f9fafb;
        color: #6b7280;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e5e7eb;
    }
    .table-modern tbody td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
        font-size: 0.9rem;
        color: #374151;
    }
    .table-modern tbody tr:last-child td { border-bottom: none; }
    .table-modern tr:hover { background-color: #f9fafb; }

    /* Utils */
    .bg-gradient-primary-soft { background: linear-gradient(135deg, rgba(13, 110, 253, 0.1) 0%, rgba(13, 110, 253, 0.05) 100%); color: var(--primary-color); }
    .bg-gradient-secondary-soft { background: linear-gradient(135deg, rgba(108, 117, 125, 0.1) 0%, rgba(108, 117, 125, 0.05) 100%); color: var(--secondary-color); }
    .bg-gradient-success-soft { background: linear-gradient(135deg, rgba(25, 135, 84, 0.1) 0%, rgba(25, 135, 84, 0.05) 100%); color: var(--success-color); }
    .bg-gradient-warning-soft { background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 193, 7, 0.05) 100%); color: var(--warning-color); }
    .bg-gradient-info-soft { background: linear-gradient(135deg, rgba(13, 202, 240, 0.1) 0%, rgba(13, 202, 240, 0.05) 100%); color: #0891b2; }
    .bg-gradient-danger-soft { background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%); color: var(--danger-color); }
    
    .status-badge {
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        background-color: #e5e7eb;
        color: #374151;
    }
    .status-badge.human { background-color: #dbeafe; color: #1e40af; }
    .status-badge.bot { background-color: #f3f4f6; color: #4b5563; }
</style>

<!-- Header Section -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-4 pb-4 mb-3">
    <div>
        <h1 class="h3 fw-bold mb-1">Dashboard</h1>
        <p class="text-muted mb-0">Overview of your system performance today.</p>
    </div>
    <div class="d-flex gap-2">
        <select class="form-select border-0 shadow-sm" style="width: auto; font-weight: 500;" onchange="window.location.href='?range=' + this.value">
            <option value="today" <?php echo $range == 'today' ? 'selected' : ''; ?>>Hari Ini</option>
            <option value="7days" <?php echo $range == '7days' ? 'selected' : ''; ?>>7 Hari Terakhir</option>
            <option value="30days" <?php echo $range == '30days' ? 'selected' : ''; ?>>30 Hari Terakhir</option>
            <option value="month" <?php echo $range == 'month' ? 'selected' : ''; ?>>Bulan Ini</option>
        </select>
        <button type="button" class="btn btn-white shadow-sm fw-semibold" onclick="window.location.reload()">
            <i class="fa-solid fa-arrows-rotate text-primary"></i>
        </button>
    </div>
</div>

<!-- Stats Grid -->
<div class="row mb-4">
    <!-- SEO Overview Added Here -->
        <div class="col-12 mb-4">
            <h4 class="mb-3 text-primary border-bottom pb-2"><i class="fa-solid fa-rocket me-2"></i>SEO Performance & Health</h4>
            <div class="row g-4">
                <!-- Health Score Card -->
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center d-flex flex-column justify-content-center align-items-center">
                            <h6 class="text-muted mb-3">Rata-rata Skor SEO</h6>
                            <div class="position-relative d-inline-block">
                                <svg viewBox="0 0 36 36" class="circular-chart" width="120" height="120">
                                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#eee" stroke-width="3" />
                                    <path class="circle" id="seoCirclePath" stroke-dasharray="0, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#4caf50" stroke-width="3" />
                                </svg>
                                <div class="position-absolute top-50 start-50 translate-middle">
                                    <span class="display-6 fw-bold" id="avgSeoScore">0</span>
                                </div>
                            </div>
                            <p class="text-muted mt-3 small">Dari <span id="totalArticles" class="fw-bold">0</span> Artikel Terbit</p>
                        </div>
                    </div>
                </div>

                <!-- Distribution Chart -->
                <div class="col-md-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 fw-bold">Distribusi Kualitas SEO</div>
                        <div class="card-body">
                            <div style="height: 200px; position: relative;">
                                <canvas id="seoDistChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Worst Performers -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0 fw-bold text-danger">Perlu Perbaikan Segera</div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush" id="worstSeoList">
                                <div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <!-- End SEO Overview -->

<div class="row g-4 mb-4">
    <!-- Human Total -->
    <div class="col-sm-6 col-lg-4 col-xl-2">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon-wrapper bg-gradient-primary-soft">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="text-end">
                        <i class="fa-solid fa-circle-info text-muted small" data-bs-toggle="tooltip" title="Jumlah total pengunjung manusia asli (mengabaikan bot/crawler)."></i>
                    </div>
                </div>
                <?php $human_total = $pdo->query("SELECT COUNT(*) FROM visitor_analytics WHERE is_bot = 0")->fetchColumn(); ?>
                <div class="stat-card-value"><?php echo number_format($human_total); ?></div>
                <div class="stat-card-title">Human Visitors</div>
            </div>
        </div>
    </div>
    
    <!-- Bot Traffic -->
    <div class="col-sm-6 col-lg-4 col-xl-2">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon-wrapper bg-gradient-secondary-soft">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div class="text-end">
                        <i class="fa-solid fa-circle-info text-muted small" data-bs-toggle="tooltip" title="Jumlah kunjungan yang terdeteksi sebagai robot/crawler mesin pencari (Googlebot, Bingbot, dll)."></i>
                    </div>
                </div>
                 <?php $bot_total = $pdo->query("SELECT COUNT(*) FROM visitor_analytics WHERE is_bot = 1")->fetchColumn(); ?>
                <div class="stat-card-value text-secondary"><?php echo number_format($bot_total); ?></div>
                <div class="stat-card-title">Bot Traffic</div>
            </div>
        </div>
    </div>

    <!-- Active Range Human -->
    <div class="col-sm-6 col-lg-4 col-xl-2">
         <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon-wrapper bg-gradient-warning-soft">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>
                </div>
                <?php 
                    $rangeLabels = ['today'=>'Hari Ini', '7days'=>'7 Hari', '30days'=>'30 Hari', 'month'=>'Bulan Ini'];
                    $curLabel = $rangeLabels[$range] ?? 'Statistik';
                    $stmtRangeHuman = $pdo->prepare("SELECT COUNT(*) FROM visitor_analytics WHERE $condition AND is_bot = 0");
                    $stmtRangeHuman->execute($params);
                    $count_range_human = $stmtRangeHuman->fetchColumn();
                ?>
                <div class="stat-card-value text-dark"><?php echo number_format($count_range_human); ?></div>
                <div class="stat-card-title"><?php echo $curLabel; ?> (Asli)</div>
            </div>
        </div>
    </div>
    
    <!-- Today Human -->
    <div class="col-sm-6 col-lg-4 col-xl-2">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon-wrapper bg-gradient-success-soft">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>
                    <div class="text-end">
                        <i class="fa-solid fa-circle-info text-muted small" data-bs-toggle="tooltip" title="Jumlah pengunjung manusia hari ini."></i>
                    </div>
                </div>
                <?php 
                    $stmtTodayHuman = $pdo->prepare("SELECT COUNT(*) FROM visitor_analytics WHERE visited_at = ? AND is_bot = 0");
                    $stmtTodayHuman->execute([date('Y-m-d')]);
                    $count_today_human = $stmtTodayHuman->fetchColumn();
                ?>
                <div class="stat-card-value text-success"><?php echo number_format($count_today_human); ?></div>
                <div class="stat-card-title">Hari Ini (Asli)</div>
            </div>
        </div>
    </div>
    
    <!-- Genuine Orders -->
    <div class="col-sm-6 col-lg-4 col-xl-2">
        <div class="card h-100">
             <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon-wrapper bg-gradient-info-soft">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>
                    <div class="text-end">
                        <i class="fa-solid fa-circle-info text-muted small" data-bs-toggle="tooltip" title="Jumlah total pesanan/leads yang lolos verifikasi AI (Status: Genuine)."></i>
                    </div>
                </div>
                <?php $count_genuine_leads = $pdo->query("SELECT COUNT(*) FROM leads WHERE ai_status = 'genuine'")->fetchColumn(); ?>
                <div class="stat-card-value text-dark"><?php echo number_format($count_genuine_leads); ?></div>
                <div class="stat-card-title">Total Pesanan</div>
            </div>
        </div>
    </div>

    <!-- AI Queue -->
    <div class="col-sm-6 col-lg-4 col-xl-2">
         <div class="card h-100 border-0" style="background-color: #fff1f2;">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div class="stat-icon-wrapper bg-gradient-danger-soft">
                        <i class="fa-solid fa-microchip"></i>
                    </div>
                </div>
                <div class="stat-card-value text-danger"><?php echo number_format($ai_queue_count); ?></div>
                <div class="stat-card-title text-danger" style="opacity: 0.7;">Antrian AI</div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Row -->
<div class="row g-4 mb-4">
    <!-- Traffic Chart -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <h5>Traffic Overview</h5>
                <small class="text-muted fw-bold"><?php echo $range == 'today' ? '24 Jam Terakhir' : 'Trend Kunjungan'; ?></small>
            </div>
            <div class="card-body">
                <div style="height: 300px; position: relative;">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
            
            <!-- Nested Row: Articles & Trend -->
            <div class="row g-0 border-top mt-4">
                <div class="col-md-6 border-end p-4">
                    <h6 class="fw-bold text-gray-800 mb-3"><i class="fa-solid fa-fire text-warning me-2"></i>Artikel Terpopuler</h6>
                    <div style="height: 200px; position: relative;">
                        <canvas id="articleChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6 p-4">
                    <h6 class="fw-bold text-gray-800 mb-3"><i class="fa-solid fa-arrow-trend-up text-info me-2"></i>Tren Pembaca</h6>
                     <div style="height: 200px; position: relative;">
                        <canvas id="artViewChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Sidebar -->
    <div class="col-lg-4">
        <!-- Devices -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Device Usage</h5>
            </div>
            <div class="card-body p-4 d-flex justify-content-center">
                <div style="height: 220px; width: 220px; position: relative;">
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Popular Services -->
        <div class="card mb-4">
             <div class="card-header">
                <h5>Layanan Favorit</h5>
            </div>
            <div class="card-body">
                <div style="height: 180px; position: relative;">
                    <canvas id="serviceChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Server Health (Compact) -->
        <div class="card bg-dark text-white border-0">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-4">
                    <i class="fa-solid fa-server fa-lg me-3 text-success"></i>
                    <div>
                        <h6 class="mb-0 fw-bold">Server Status</h6>
                        <small class="opacity-75">Systems Operational</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Disk Usage</span>
                        <span class="fw-bold"><?php echo $disk_usage; ?>%</span>
                    </div>
                    <div class="progress bg-secondary bg-opacity-25" style="height: 4px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $disk_usage; ?>%"></div>
                    </div>
                </div>
                
                <div class="row g-2 text-center small mt-3">
                    <div class="col-6">
                        <div class="p-2 rounded bg-white bg-opacity-10">
                            <div class="opacity-75 mb-1">PHP Ver</div>
                            <div class="fw-bold"><?php echo $php_version; ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded bg-white bg-opacity-10">
                            <div class="opacity-75 mb-1">DB Size</div>
                            <div class="fw-bold"><?php echo $db_size; ?> MB</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

                <!-- Recent Visitors Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Pengunjung Terakhir</h5>
                        <a href="#" class="btn btn-sm btn-white border shadow-sm small fw-semibold">Lihat Semua</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-4">Waktu</th>
                                    <th>IP Address</th>
                                    <th>Lokasi</th>
                                    <th>Perangkat</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($recent)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada data pengunjung.</td></tr>
                                <?php else: ?>
                                <?php foreach($recent as $r): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?php echo date('d M', strtotime($r['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($r['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="font-monospace text-muted small bg-light px-2 py-1 rounded border-0 fw-bold"><?php echo $r['ip_address']; ?></span>
                                    </td>
                                    <td>
                                        <?php if($r['country'] != 'Unknown'): ?>
                                            <span class="me-1"><?php echo $r['country'] == 'Indonesia' ? '🇮🇩' : '🌍'; ?></span>
                                        <?php endif; ?>
                                        <span class="fw-medium text-dark"><?php echo $r['city']; ?></span>
                                        <span class="text-muted small ms-1"><?php echo $r['country']; ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                            $icon = $r['device']=='Mobile' ? 'fa-mobile-screen' : 'fa-desktop';
                                            echo "<div class='text-secondary'><i class='fa-solid $icon me-2'></i><span class='fw-medium'>{$r['device']}</span></div>";
                                        ?>
                                    </td>
                                    <td>
                                        <?php if($r['is_bot']): ?>
                                            <span class="status-badge bot"><i class="fa-solid fa-robot me-1"></i> Bot</span>
                                        <?php else: ?>
                                            <span class="status-badge human"><i class="fa-solid fa-user me-1"></i> Human</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Interactive Map Section -->
                <div class="card mb-4 overflow-hidden">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1"><i class="fa-solid fa-map-location-dot me-2 text-primary"></i>Peta Persebaran Pengunjung</h5>
                            <small class="text-muted">Visualisasi lokasi pengunjung berdasarkan IP Address</small>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Map filters">
                            <input type="radio" class="btn-check" name="mapFilter" id="mapAll" value="all" checked autocomplete="off">
                            <label class="btn btn-outline-secondary" for="mapAll">Semua</label>

                            <input type="radio" class="btn-check" name="mapFilter" id="mapHuman" value="human" autocomplete="off">
                            <label class="btn btn-outline-primary" for="mapHuman"><i class="fa-solid fa-user me-1"></i> Human</label>

                            <input type="radio" class="btn-check" name="mapFilter" id="mapBot" value="bot" autocomplete="off">
                            <label class="btn btn-outline-secondary" for="mapBot"><i class="fa-solid fa-robot me-1"></i> Bot</label>
                        </div>
                    </div>
                    <div class="card-body p-0 position-relative">
                        <div id="visitorMap" style="height: 450px; width: 100%; z-index: 1;"></div>
                        <!-- Current Range Indicator -->
                        <div class="position-absolute top-0 end-0 m-3 bg-white px-3 py-2 rounded shadow-sm border" style="z-index: 400; font-size: 0.8rem;">
                            <span class="fw-bold text-muted">Jangkauan: </span> 
                            <span class="text-dark fw-bold"><?php echo htmlspecialchars($range); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
    // --- Tooltips Initialization ---
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof bootstrap !== 'undefined') {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    });

    // --- Interactive Map Logic ---
    let map, markersLayer;
    const mapContainer = document.getElementById('visitorMap');

    function initMap() {
        if (!mapContainer) return;

        // Initialize Map
        map = L.map('visitorMap').setView([-2.5489, 118.0149], 5); // Indonesia Center

        // Standard Tiles
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);

        markersLayer = L.layerGroup().addTo(map);
        loadMapData('all'); // Initial Load
    }

    function loadMapData(filter) {
        if (!map) return;
        
        // Clear existing markers
        markersLayer.clearLayers();

        // Show loading state could go here

        fetch(`api/get_map_data.php?range=<?php echo $range; ?>&filter=${filter}`)
            .then(response => response.json())
            .then(res => {
                if (res.status === 'success') {
                    res.data.forEach(point => {
                        // Color coding
                        const color = point.is_bot == 1 ? '#6c757d' : '#0d6efd';
                        const fillColor = point.is_bot == 1 ? '#e9ecef' : '#dbeafe';

                        const circleMarker = L.circleMarker([point.lat, point.lng], {
                            radius: 6,
                            fillColor: fillColor,
                            color: color,
                            weight: 1,
                            opacity: 1,
                            fillOpacity: 0.8
                        });

                        const deviceIcon = point.device === 'Mobile' ? '<i class="fa-solid fa-mobile-screen"></i>' : '<i class="fa-solid fa-desktop"></i>';
                        const typeBadge = point.is_bot == 1 
                            ? '<span class="badge bg-secondary">Bot</span>' 
                            : '<span class="badge bg-primary">Human</span>';

                        const popupContent = `
                            <div class="text-center p-2">
                                <div class="mb-1">${typeBadge}</div>
                                <div class="small fw-bold text-dark">${point.city || 'Unknown'}, ${point.country || 'Unknown'}</div>
                                <div class="small text-muted mb-2">${point.visited_at}</div>
                                <div class="small text-secondary">${deviceIcon} ${point.device}</div>
                            </div>
                        `;

                        circleMarker.bindPopup(popupContent);
                        markersLayer.addLayer(circleMarker);
                    });
                }
            })
            .catch(err => console.error('Map loading error:', err));
    }

    // Filter Listeners
    document.querySelectorAll('input[name="mapFilter"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            if (e.target.checked) {
                loadMapData(e.target.value);
            }
        });
    });

    // Initialize Map on Load
    document.addEventListener('DOMContentLoaded', initMap);

    // Global Defaults for Modern Chart.js (Existing)
    Chart.defaults.font.family = "'Inter', system-ui, -apple-system, sans-serif";

    Chart.defaults.color = '#6b7280';
    
    // Common Options to prevent layout thrashing
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false, // Critical for fixed height containers
        plugins: { 
            legend: { 
                position: 'top', 
                align: 'end', 
                labels: { usePointStyle: true, boxWidth: 8, font: { weight: 500 } } 
            },
            tooltip: { 
                backgroundColor: '#ffffff',
                titleColor: '#111827',
                bodyColor: '#4b5563',
                borderColor: '#e5e7eb',
                borderWidth: 1,
                padding: 12,
                cornerRadius: 12,
                displayColors: true,
                titleFont: { weight: 'bold' },
                shadowOffsetX: 0,
                shadowOffsetY: 4,
                shadowBlur: 10,
                shadowColor: 'rgba(0,0,0,0.1)' 
            }
        },
        scales: {
             y: { 
                beginAtZero: true, 
                grid: { borderDash: [4, 4], color: '#f3f4f6', drawBorder: false },
                ticks: { padding: 10 }
            },
            x: { 
                grid: { display: false },
                ticks: { padding: 10 }
            }
        },
        elements: {
            line: { tension: 0.4, borderCapStyle: 'round' },
            point: { radius: 0, hoverRadius: 6, hitRadius: 20 }
        }
    };

    // 1. Traffic Chart (Area) - Wrapper must have height!
    new Chart(document.getElementById('trafficChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_Label); ?>,
            datasets: [
                {
                    label: 'Human Visitors',
                    data: <?php echo json_encode($human_Data); ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.05)',
                    borderWidth: 3,
                    fill: true,
                     pointBackgroundColor: '#0d6efd',
                },
                {
                    label: 'Bot Traffic',
                    data: <?php echo json_encode($bot_Data); ?>,
                    borderColor: '#9ca3af',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    fill: false,
                    pointHoverBackgroundColor: '#9ca3af'
                }
            ]
        },
        options: {
            ...commonOptions,
            plugins: { ...commonOptions.plugins, legend: { display: true, align: 'end' } }
        }
    });

    // 2. Device Chart (Doughnut)
    new Chart(document.getElementById('deviceChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($device_Label); ?>,
            datasets: [{
                data: <?php echo json_encode($device_Data); ?>,
                backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: { 
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } } 
            }
        }
    });

    // 3. Article Chart (Horizontal Bar)
    new Chart(document.getElementById('articleChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($article_Label); ?>,
            datasets: [{
                label: 'Views',
                data: <?php echo json_encode($article_Data); ?>,
                backgroundColor: '#10b981',
                borderRadius: 6,
                barThickness: 12
            }]
        },
        options: {
            ...commonOptions,
            indexAxis: 'y',
            plugins: { legend: { display: false } }, 
            scales: { x: { grid: { borderDash: [4, 4], color: '#f3f4f6' } }, y: { grid: { display: false } } }
        }
    });

    // 4. Trend Chart (Line)
    new Chart(document.getElementById('artViewChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($artView_Label); ?>,
            datasets: [{
                label: 'Readers',
                data: <?php echo json_encode($artView_Data); ?>,
                borderColor: '#0dcaf0',
                backgroundColor: 'rgba(13, 202, 240, 0.1)',
                borderWidth: 2,
                fill: true,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#0dcaf0',
                pointRadius: 4
            }]
        },
        options: {
             ...commonOptions,
             plugins: { legend: { display: false } },
             scales: { y: { display: false }, x: { display: false } } // Sparkline style
        }
    });

    // 5. Service Chart (Bar)
    new Chart(document.getElementById('serviceChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($service_Label); ?>,
            datasets: [{
                label: 'Orders',
                data: <?php echo json_encode($service_Data); ?>,
                backgroundColor: '#3b82f6',
                borderRadius: 6,
                barThickness: 20
            }]
        },
        options: commonOptions
    });

    // --- Load SEO Stats ---
    function loadSeoStats() {
        $.ajax({
            url: 'api/get_seo_stats.php',
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    // 1. Health Score
                    const score = res.health.score;
                    $('#avgSeoScore').text(score);
                    $('#totalArticles').text(res.health.total_articles);
                    
                    // Animate Circle
                    const circle = document.getElementById('seoCirclePath');
                    // Color logic
                    const color = score >= 80 ? '#198754' : (score >= 60 ? '#ffc107' : '#dc3545');
                    circle.setAttribute('stroke', color);
                    setTimeout(() => {
                         circle.setAttribute('stroke-dasharray', `${score}, 100`);
                    }, 500);

                    // 2. Distribution Chart
                    const ctx = document.getElementById('seoDistChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: res.distribution.labels,
                            datasets: [{
                                data: res.distribution.data,
                                backgroundColor: res.distribution.colors,
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'right', labels: { boxWidth: 12 } }
                            },
                            cutout: '70%'
                        }
                    });

                    // 3. Worst list
                    const list = $('#worstSeoList');
                    list.empty();
                    if(res.worst_articles.length === 0) {
                         list.html('<div class="p-3 text-center text-success"><i class="fa-solid fa-check-circle me-2"></i>Semua artikel bagus!</div>');
                    } else {
                        res.worst_articles.forEach(art => {
                             list.append(`
                                <a href="articles.php?edit=${art.id}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div class="text-truncate me-2" style="max-width: 200px;">
                                        <small class="fw-bold d-block text-truncate">${art.title}</small>
                                        <span class="badge bg-light text-dark"><i class="fa-solid fa-eye me-1"></i>${art.views}</span>
                                    </div>
                                    <span class="badge ${art.seo_score < 60 ? 'bg-danger' : 'bg-warning'} rounded-pill">${art.seo_score}</span>
                                </a>
                             `);
                        });
                    }
                }
            },
            error: function(err) {
                console.error("SEO Stats Error", err);
            }
        });
    }

    $(document).ready(function() {
        loadSeoStats();
    });
</script>

<?php require_once 'includes/footer.php'; ?>
