<?php
// api/v2/data.php
// REST API Data Endpoint for Admin V2

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require authentication for data endpoints
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../admin/includes/functions.php';

$type = $_GET['type'] ?? '';

try {
    switch ($type) {
        case 'dashboard':
            $range = $_GET['range'] ?? '7days';
            $params = [];
            
            // Date condition based on range
            if ($range === 'today') {
                $condition = "visited_at = :date";
                $params['date'] = date('Y-m-d');
            } elseif ($range === '30days') {
                $condition = "visited_at >= DATE(NOW()) - INTERVAL 30 DAY";
            } elseif ($range === 'month') {
                $condition = "visited_at LIKE :month";
                $params['month'] = date('Y-m') . '%';
            } else {
                $range = '7days';
                $condition = "visited_at >= DATE(NOW()) - INTERVAL 7 DAY";
            }

            // 1. KPI Stats
            $humanTotal = (int)$pdo->query("SELECT COUNT(*) FROM visitor_analytics WHERE is_bot = 0")->fetchColumn();
            $botTotal = (int)$pdo->query("SELECT COUNT(*) FROM visitor_analytics WHERE is_bot = 1")->fetchColumn();
            
            $stmtRangeHuman = $pdo->prepare("SELECT COUNT(*) FROM visitor_analytics WHERE $condition AND is_bot = 0");
            $stmtRangeHuman->execute($params);
            $rangeHuman = (int)$stmtRangeHuman->fetchColumn();

            $todayHuman = (int)$pdo->query("SELECT COUNT(*) FROM visitor_analytics WHERE visited_at = CURDATE() AND is_bot = 0")->fetchColumn();
            $genuineLeads = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE ai_status = 'genuine'")->fetchColumn();
            $aiQueueCount = (int)$pdo->query("SELECT COUNT(*) FROM auto_content_keywords WHERE status = 'pending'")->fetchColumn();
            
            $totalVisitors = (int)$pdo->query("SELECT COUNT(*) FROM visitor_analytics")->fetchColumn();
            $totalLeads = (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
            $totalWaClicks = (int)$pdo->query("SELECT COUNT(*) FROM wa_clicks")->fetchColumn();
            $totalArticles = (int)$pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();
            $totalServices = (int)$pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();

            // 2. Traffic Trend Chart (Split Human vs Bot by day)
            $stmtTrend = $pdo->prepare("
                SELECT visited_at, 
                       SUM(CASE WHEN is_bot = 0 THEN 1 ELSE 0 END) as human_count, 
                       SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bot_count 
                FROM visitor_analytics 
                WHERE $condition 
                GROUP BY visited_at 
                ORDER BY visited_at ASC
            ");
            $stmtTrend->execute($params);
            $trendLabels = [];
            $humanVisits = [];
            $botVisits = [];
            while ($row = $stmtTrend->fetch()) {
                $trendLabels[] = date('d M', strtotime($row['visited_at']));
                $humanVisits[] = (int)$row['human_count'];
                $botVisits[] = (int)$row['bot_count'];
            }

            // 3. Popular Articles (Top 5 by views)
            $stmtPop = $pdo->query("SELECT id, title, views FROM articles ORDER BY views DESC LIMIT 5");
            $popularArticles = [];
            while ($row = $stmtPop->fetch()) {
                $popularArticles[] = [
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'views' => (int)$row['views']
                ];
            }

            // 4. Reader Trend (article_views)
            $artViewLabels = [];
            $artViewData = [];
            try {
                $avCond = str_replace('visited_at', 'DATE(viewed_at)', $condition);
                $stmtAv = $pdo->prepare("SELECT DATE(viewed_at) as viewed_date, COUNT(*) as count FROM article_views WHERE $avCond GROUP BY DATE(viewed_at) ORDER BY viewed_date ASC");
                $stmtAv->execute($params);
                while ($row = $stmtAv->fetch()) {
                    $artViewLabels[] = date('d M', strtotime($row['viewed_date']));
                    $artViewData[] = (int)$row['count'];
                }
            } catch (Exception $e) {}

            // 5. Device Breakdown
            $stmtDevice = $pdo->query("SELECT device, COUNT(*) as count FROM visitor_analytics GROUP BY device");
            $deviceBreakdown = [];
            while ($row = $stmtDevice->fetch()) {
                $deviceBreakdown[] = [
                    'device' => $row['device'] ?: 'Unknown',
                    'count' => (int)$row['count']
                ];
            }

            // 6. Popular Services (Top 5 Genuine Orders)
            $stmtFavSvc = $pdo->query("
                SELECT s.id, s.title, COUNT(l.id) as orders_count 
                FROM services s 
                LEFT JOIN leads l ON s.id = l.service_id AND l.ai_status = 'genuine' 
                GROUP BY s.id 
                ORDER BY orders_count DESC 
                LIMIT 5
            ");
            $favoriteServices = [];
            while ($row = $stmtFavSvc->fetch()) {
                $favoriteServices[] = [
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'orders' => (int)$row['orders_count']
                ];
            }

            // 7. Server Health Stats
            $dbSize = (float)$pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema = (SELECT DATABASE())")->fetchColumn();
            $diskFree = @disk_free_space("/");
            $diskTotal = @disk_total_space("/");
            $diskUsage = ($diskTotal > 0) ? round(100 - (($diskFree / $diskTotal) * 100), 1) : 0;
            $serverHealth = [
                'php_version' => PHP_VERSION,
                'db_size_mb' => $dbSize,
                'disk_usage_percent' => $diskUsage,
                'status' => 'operational'
            ];

            // 8. SEO Ranking (10 Tiers) & AI Data
            $seoRanksMeta = [
                100 => ['tier' => 100, 'label' => 'Perfect', 'color' => '#006400', 'bg' => '#d1e7dd'],
                90  => ['tier' => 90,  'label' => 'Excellent', 'color' => '#008000', 'bg' => '#d4edda'],
                80  => ['tier' => 80,  'label' => 'Very Good', 'color' => '#16a34a', 'bg' => '#dcfce7'],
                70  => ['tier' => 70,  'label' => 'Good', 'color' => '#65a30d', 'bg' => '#ecfccb'],
                60  => ['tier' => 60,  'label' => 'Fair', 'color' => '#ca8a04', 'bg' => '#fef9c3'],
                50  => ['tier' => 50,  'label' => 'Below Average', 'color' => '#d97706', 'bg' => '#fef3c7'],
                40  => ['tier' => 40,  'label' => 'Poor', 'color' => '#ea580c', 'bg' => '#ffedd5'],
                30  => ['tier' => 30,  'label' => 'Bad', 'color' => '#c2410c', 'bg' => '#ffedd5'],
                20  => ['tier' => 20,  'label' => 'Very Bad', 'color' => '#dc2626', 'bg' => '#fee2e2'],
                10  => ['tier' => 10,  'label' => 'Critical', 'color' => '#991b1b', 'bg' => '#fee2e2'],
            ];

            $seoTiers = [];
            foreach ($seoRanksMeta as $tier => $meta) {
                $seoTiers[$tier] = [
                    'tier' => $tier,
                    'label' => $meta['label'],
                    'color' => $meta['color'],
                    'bg' => $meta['bg'],
                    'count' => 0,
                    'articles' => []
                ];
            }

            $stmtAllArticles = $pdo->query("SELECT id, title, seo_score FROM articles WHERE status = 'published' ORDER BY seo_score DESC");
            $allPublished = $stmtAllArticles->fetchAll(PDO::FETCH_ASSOC);
            $totalPublished = count($allPublished);
            $goodCount = 0;
            $scoresSum = 0;
            $problematic = [];

            foreach ($allPublished as $art) {
                $score = (int)($art['seo_score'] ?? 0);
                $scoresSum += $score;
                if ($score >= 80) {
                    $goodCount++;
                } else {
                    $problematic[] = [
                        'id' => (int)$art['id'],
                        'title' => $art['title'],
                        'seo_score' => $score
                    ];
                }

                if ($score == 100) $tier = 100;
                else $tier = (int)(floor($score / 10) * 10);
                if ($tier < 10) $tier = 10;

                if (isset($seoTiers[$tier])) {
                    $seoTiers[$tier]['count']++;
                    if (count($seoTiers[$tier]['articles']) < 10) {
                        $seoTiers[$tier]['articles'][] = [
                            'id' => (int)$art['id'],
                            'title' => $art['title'],
                            'seo_score' => $score
                        ];
                    }
                }
            }

            $avgSeoScore = $totalPublished > 0 ? round($scoresSum / $totalPublished, 1) : 0;
            $majorityGood = $totalPublished > 0 && ($goodCount / $totalPublished) >= 0.5;

            $seoAiMessage = $majorityGood
                ? "Selamat! Ini adalah titik terbaik karena mayoritas artikel (≥ 80) sudah mencapai standar optimal Google."
                : "Perhatian diperlukan. Terdapat beberapa artikel yang belum mencapai standar kualitas SEO optimal (< 80).";
            $seoAiStatus = $majorityGood ? "success" : "warning";

            // 9. Recent Visitors Table (Last 6)
            $stmtRecentVis = $pdo->query("SELECT id, ip_address, city, country, device, is_bot, created_at, visited_at FROM visitor_analytics ORDER BY created_at DESC LIMIT 6");
            $recentVisitors = $stmtRecentVis->fetchAll(PDO::FETCH_ASSOC);

            // 10. Recent Leads & Activities
            $recentLeads = $pdo->query("SELECT l.id, l.name, l.whatsapp as phone, s.title as service_interested, l.status, l.created_at FROM leads l LEFT JOIN services s ON l.service_id = s.id ORDER BY l.created_at DESC LIMIT 5")->fetchAll();
            $recentActivities = $pdo->query("SELECT id, action, details, ip_address, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 6")->fetchAll();

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'range' => $range,
                    'stats' => [
                        'human_total' => $humanTotal,
                        'bot_total' => $botTotal,
                        'range_human' => $rangeHuman,
                        'today_human' => $todayHuman,
                        'genuine_leads' => $genuineLeads,
                        'ai_queue_count' => $aiQueueCount,
                        'total_visitors' => $totalVisitors,
                        'total_leads' => $totalLeads,
                        'total_wa_clicks' => $totalWaClicks,
                        'total_articles' => $totalArticles,
                        'total_services' => $totalServices,
                    ],
                    'trend' => [
                        'labels' => $trendLabels,
                        'human' => $humanVisits,
                        'bot' => $botVisits,
                    ],
                    'popular_articles' => $popularArticles,
                    'reader_trend' => [
                        'labels' => $artViewLabels,
                        'data' => $artViewData,
                    ],
                    'devices' => $deviceBreakdown,
                    'favorite_services' => $favoriteServices,
                    'server_health' => $serverHealth,
                    'seo' => [
                        'avg_score' => $avgSeoScore,
                        'total_articles' => $totalPublished,
                        'good_count' => $goodCount,
                        'ai_message' => $seoAiMessage,
                        'ai_status' => $seoAiStatus,
                        'tiers' => array_values($seoTiers),
                        'problematic' => $problematic
                    ],
                    'recent_visitors' => $recentVisitors,
                    'recent_leads' => $recentLeads,
                    'recent_activities' => $recentActivities,
                ]
            ]);
            break;

        case 'dashboard_map':
            $range = $_GET['range'] ?? '7days';
            $filter = $_GET['filter'] ?? 'all';
            $where = "lat IS NOT NULL AND lng IS NOT NULL AND lat != 0 AND lng != 0";
            $params = [];

            if ($range === 'today') {
                $where .= " AND visited_at = ?";
                $params[] = date('Y-m-d');
            } elseif ($range === '30days') {
                $where .= " AND visited_at >= DATE(NOW()) - INTERVAL 30 DAY";
            } elseif ($range === 'month') {
                $where .= " AND visited_at LIKE ?";
                $params[] = date('Y-m') . '%';
            } else {
                $where .= " AND visited_at >= DATE(NOW()) - INTERVAL 7 DAY";
            }

            if ($filter === 'human') {
                $where .= " AND is_bot = 0";
            } elseif ($filter === 'bot') {
                $where .= " AND is_bot = 1";
            }

            $stmtMap = $pdo->prepare("SELECT id, lat, lng, city, country, is_bot, device, visited_at FROM visitor_analytics WHERE $where ORDER BY visited_at DESC LIMIT 1500");
            $stmtMap->execute($params);
            echo json_encode([
                'status' => 'success',
                'data' => $stmtMap->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        case 'dashboard_ai':
            // Delegate to get_dashboard_ai.php logic
            $totalArticles = (int)$pdo->query("SELECT COUNT(*) FROM articles WHERE status='published'")->fetchColumn();
            $stmtScores = $pdo->query("SELECT seo_score FROM articles WHERE status='published'");
            $scores = $stmtScores->fetchAll(PDO::FETCH_COLUMN);
            $avgScore = 0;
            $countHigh = 0;
            if (count($scores) > 0) {
                $avgScore = round(array_sum($scores) / count($scores), 1);
                foreach ($scores as $s) {
                    if ($s >= 80) $countHigh++;
                }
            }
            $highPercent = $totalArticles > 0 ? round(($countHigh / $totalArticles) * 100, 1) : 0;

            // Fetch AI Settings
            $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings");
            $settings = [];
            while ($row = $stmtSettings->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            $activeProvider = $settings['ai_active_provider'] ?? 'gemini';
            $apiKey = '';
            $model = '';
            if ($activeProvider === 'groq') {
                $model = $settings['ai_config_groq_model'] ?? 'llama-3.3-70b-versatile';
                $keys = json_decode($settings['ai_config_groq_keys'] ?? '[]', true);
                if (is_array($keys) && !empty($keys)) $apiKey = $keys[0];
            } else {
                $activeProvider = 'gemini';
                $model = $settings['ai_config_gemini_model'] ?? 'gemini-1.5-flash';
                $keys = json_decode($settings['ai_config_gemini_keys'] ?? '[]', true);
                if (is_array($keys) && !empty($keys)) $apiKey = $keys[0];
            }

            $prompt = "Kamu adalah asisten AI yang cerdas, ramah, dan memotivasi untuk pemilik website bernama 'Arno'.\n\n";
            $prompt .= "DATA WEBSITE HARI INI:\n";
            $prompt .= "- Total Artikel: $totalArticles\n";
            $prompt .= "- Rata-rata Skor SEO: $avgScore / 100\n";
            $prompt .= "- Persentase Artikel Bagus (Skor >= 80): $highPercent%\n\n";
            $prompt .= "INSTRUKSI:\n";
            $prompt .= "1. Berikan komentar singkat (maksimal 3 kalimat) tentang performa SEO.\n";
            $prompt .= "2. Gunakan gaya bahasa natural, seperti teman kerja yang supportif.\n";
            $prompt .= "3. Jika Rata-rata Skor > 80: Puji Arno secara antusias! Sebutkan bahwa ini adalah pencapaian 'Gold Tier/Piagam Kemenangan'. Ingatkan untuk tidak menghapus artikel-artikel bagus ini.\n";
            $prompt .= "4. Jika Rata-rata Skor < 60: Berikan semangat, katakan bahwa perbaikan kecil bisa berdampak besar.\n";
            $prompt .= "5. Gunakan emoji yang relevan.\n";

            if (empty($apiKey)) {
                $aiResponse = "Halo Arno! 👋\n\n";
                if ($avgScore >= 80) {
                    $aiResponse .= "Wow, performa SEO luar biasa! 🏆 Skor rata-rata $avgScore sangat mengesankan (Gold Tier). Pertahankan kualitas ini dan jangan hapus artikel yang sudah optimal ya! 🚀";
                } elseif ($avgScore >= 60) {
                    $aiResponse .= "Kerja bagus! Skor rata-rata $avgScore sudah cukup oke, tapi masih bisa ditingkatkan lagi. Coba perbaiki beberapa artikel yang nilainya kurang maksimal. 💪";
                } else {
                    $aiResponse .= "Sepertinya kita perlu bersih-bersih SEO nih. Skor rata-rata $avgScore masih butuh perhatian. Yuk optimalkan artikel-artikel lama biar traffic makin kencang! 🧹";
                }
            } else {
                if (function_exists('callAI')) {
                    $aiResponse = callAI($prompt, $apiKey, $model, $activeProvider);
                } else {
                    $aiResponse = "Halo Arno! Rata-rata skor SEO website adalah $avgScore / 100 dengan $highPercent% artikel di level optimal.";
                }
            }

            echo json_encode([
                'status' => 'success',
                'message' => $aiResponse,
                'stats' => ['avg' => $avgScore, 'total' => $totalArticles, 'high_percent' => $highPercent]
            ]);
            break;

        case 'services':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $stmt = $pdo->query("SELECT * FROM services ORDER BY created_at DESC");
                $services = $stmt->fetchAll();
                echo json_encode([
                    'status' => 'success',
                    'data' => $services
                ]);
                break;
            }

            // DELETE action (via DELETE method or POST action=delete)
            $action = $_REQUEST['action'] ?? '';
            if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $action === 'delete') {
                $id = (int)($_REQUEST['id'] ?? 0);
                if (!$id) throw new Exception("ID Layanan tidak valid.");

                $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
                $stmt->execute([$id]);
                $svc = $stmt->fetch();

                if ($svc) {
                    if (!empty($svc['image_path'])) {
                        $fullImg = __DIR__ . '/../../' . $svc['image_path'];
                        if (file_exists($fullImg)) @unlink($fullImg);
                    }
                    $delStmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
                    $delStmt->execute([$id]);

                    if (function_exists('logActivity')) {
                        logActivity("Delete Layanan", "Menghapus layanan: " . ($svc['title'] ?? "ID $id"));
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Layanan berhasil dihapus.'
                ]);
                break;
            }

            // CREATE or UPDATE (POST)
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = (int)($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price_start = trim($_POST['price_start'] ?? '');
                $image_path = trim($_POST['current_image'] ?? '');

                if (empty($title)) {
                    throw new Exception("Nama / Judul layanan wajib diisi.");
                }

                // Handle file upload if provided
                if (!empty($_FILES['image']['name'])) {
                    $uploadDir = __DIR__ . '/../../uploads/services/';
                    $dbFolder = 'uploads/services/';
                    $uploadedPath = uploadAndResize($_FILES['image'], $uploadDir, $dbFolder);
                    if ($uploadedPath) {
                        // Delete previous image if updating
                        if ($id && !empty($image_path)) {
                            $oldFile = __DIR__ . '/../../' . $image_path;
                            if (file_exists($oldFile)) @unlink($oldFile);
                        }
                        $image_path = $uploadedPath;
                    }
                }

                if ($id) {
                    $stmt = $pdo->prepare("UPDATE services SET title = ?, description = ?, price_start = ?, image_path = ? WHERE id = ?");
                    $stmt->execute([$title, $description, $price_start, $image_path, $id]);
                    if (function_exists('logActivity')) {
                        logActivity("Update Layanan", "Memperbarui layanan: $title");
                    }
                    $msg = "Layanan berhasil diperbarui.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO services (title, description, price_start, image_path) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $price_start, $image_path]);
                    $id = (int)$pdo->lastInsertId();
                    if (function_exists('logActivity')) {
                        logActivity("Create Layanan", "Menambahkan layanan baru: $title");
                    }
                    $msg = "Layanan baru berhasil ditambahkan.";
                }

                $stmtNew = $pdo->prepare("SELECT * FROM services WHERE id = ?");
                $stmtNew->execute([$id]);
                $savedService = $stmtNew->fetch();

                echo json_encode([
                    'status' => 'success',
                    'message' => $msg,
                    'data' => $savedService
                ]);
                break;
            }
            break;

        case 'benefits':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $stmt = $pdo->query("SELECT * FROM benefits ORDER BY created_at ASC");
                $benefits = $stmt->fetchAll();
                echo json_encode(['status' => 'success', 'data' => $benefits]);
                break;
            }

            $action = $_REQUEST['action'] ?? '';
            if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $action === 'delete') {
                $id = (int)($_REQUEST['id'] ?? 0);
                if (!$id) throw new Exception("ID Keunggulan tidak valid.");

                $stmt = $pdo->prepare("SELECT * FROM benefits WHERE id = ?");
                $stmt->execute([$id]);
                $b = $stmt->fetch();
                if ($b) {
                    if (!empty($b['image_path'])) {
                        $fullImg = __DIR__ . '/../../' . $b['image_path'];
                        if (file_exists($fullImg)) @unlink($fullImg);
                    }
                    $pdo->prepare("DELETE FROM benefits WHERE id = ?")->execute([$id]);
                    if (function_exists('logActivity')) {
                        logActivity("Delete Keunggulan", "Menghapus keunggulan: " . ($b['title'] ?? "ID $id"));
                    }
                }
                echo json_encode(['status' => 'success', 'message' => 'Keunggulan berhasil dihapus.']);
                break;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = (int)($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $media_type = trim($_POST['media_type'] ?? 'image');
                $image_path = trim($_POST['current_image'] ?? '');

                if (empty($title)) throw new Exception("Judul keunggulan wajib diisi.");

                if (!empty($_FILES['image']['name'])) {
                    $uploadDir = __DIR__ . '/../../uploads/benefits/';
                    $dbFolder = 'uploads/benefits/';
                    $uploadedPath = uploadAndResize($_FILES['image'], $uploadDir, $dbFolder);
                    if ($uploadedPath) {
                        if ($id && !empty($image_path)) {
                            $oldFile = __DIR__ . '/../../' . $image_path;
                            if (file_exists($oldFile)) @unlink($oldFile);
                        }
                        $image_path = $uploadedPath;
                    }
                }

                if ($id) {
                    $stmt = $pdo->prepare("UPDATE benefits SET title = ?, description = ?, image_path = ?, media_type = ? WHERE id = ?");
                    $stmt->execute([$title, $description, $image_path, $media_type, $id]);
                    if (function_exists('logActivity')) logActivity("Update Keunggulan", "Memperbarui keunggulan: $title");
                    $msg = "Keunggulan berhasil diperbarui.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO benefits (title, description, image_path, media_type) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $image_path, $media_type]);
                    $id = (int)$pdo->lastInsertId();
                    if (function_exists('logActivity')) logActivity("Create Keunggulan", "Menambahkan keunggulan baru: $title");
                    $msg = "Keunggulan baru berhasil ditambahkan.";
                }

                $saved = $pdo->query("SELECT * FROM benefits WHERE id = $id")->fetch();
                echo json_encode(['status' => 'success', 'message' => $msg, 'data' => $saved]);
                break;
            }
            break;

        case 'testimonials':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $stmt = $pdo->query("SELECT * FROM testimonials ORDER BY created_at DESC");
                $testimonials = $stmt->fetchAll();
                echo json_encode(['status' => 'success', 'data' => $testimonials]);
                break;
            }

            $action = $_REQUEST['action'] ?? '';
            if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $action === 'delete') {
                $id = (int)($_REQUEST['id'] ?? 0);
                if (!$id) throw new Exception("ID Testimoni tidak valid.");

                $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = ?");
                $stmt->execute([$id]);
                $t = $stmt->fetch();
                if ($t) {
                    if (!empty($t['image_path'])) {
                        $fullImg = __DIR__ . '/../../' . $t['image_path'];
                        if (file_exists($fullImg)) @unlink($fullImg);
                    }
                    $pdo->prepare("DELETE FROM testimonials WHERE id = ?")->execute([$id]);
                    if (function_exists('logActivity')) {
                        logActivity("Delete Testimoni", "Menghapus testimoni: " . ($t['name'] ?? "ID $id"));
                    }
                }
                echo json_encode(['status' => 'success', 'message' => 'Testimoni berhasil dihapus.']);
                break;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $rating = (float)($_POST['rating'] ?? 5.0);
                $platform = trim($_POST['platform'] ?? 'Website');
                $image_path = trim($_POST['current_image'] ?? '');

                if (empty($name)) throw new Exception("Nama pelanggan wajib diisi.");
                if (empty($content)) throw new Exception("Isi ulasan / testimoni wajib diisi.");

                if (!empty($_FILES['image']['name'])) {
                    $uploadDir = __DIR__ . '/../../uploads/testimonials/';
                    $dbFolder = 'uploads/testimonials/';
                    $uploadedPath = uploadAndResize($_FILES['image'], $uploadDir, $dbFolder);
                    if ($uploadedPath) {
                        if ($id && !empty($image_path)) {
                            $oldFile = __DIR__ . '/../../' . $image_path;
                            if (file_exists($oldFile)) @unlink($oldFile);
                        }
                        $image_path = $uploadedPath;
                    }
                }

                if ($id) {
                    $stmt = $pdo->prepare("UPDATE testimonials SET name = ?, location = ?, content = ?, rating = ?, image_path = ?, platform = ? WHERE id = ?");
                    $stmt->execute([$name, $location, $content, $rating, $image_path, $platform, $id]);
                    if (function_exists('logActivity')) logActivity("Update Testimoni", "Memperbarui testimoni: $name");
                    $msg = "Testimoni berhasil diperbarui.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO testimonials (name, location, content, rating, image_path, platform) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $location, $content, $rating, $image_path, $platform]);
                    $id = (int)$pdo->lastInsertId();
                    if (function_exists('logActivity')) logActivity("Create Testimoni", "Menambahkan testimoni baru: $name");
                    $msg = "Testimoni baru berhasil ditambahkan.";
                }

                $saved = $pdo->query("SELECT * FROM testimonials WHERE id = $id")->fetch();
                echo json_encode(['status' => 'success', 'message' => $msg, 'data' => $saved]);
                break;
            }
            break;

        case 'locations':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $stmt = $pdo->query("SELECT * FROM locations ORDER BY id DESC");
                $locations = $stmt->fetchAll();
                echo json_encode(['status' => 'success', 'data' => $locations]);
                break;
            }

            $action = $_REQUEST['action'] ?? '';
            if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $action === 'delete') {
                $id = (int)($_REQUEST['id'] ?? 0);
                if (!$id) throw new Exception("ID Lokasi tidak valid.");

                $stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
                $stmt->execute([$id]);
                $locName = $stmt->fetchColumn() ?: "ID $id";

                $pdo->prepare("DELETE FROM locations WHERE id = ?")->execute([$id]);
                if (function_exists('logActivity')) {
                    logActivity("Delete Lokasi", "Menghapus lokasi: $locName");
                }
                echo json_encode(['status' => 'success', 'message' => 'Lokasi berhasil dihapus.']);
                break;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $rawInput = file_get_contents('php://input');
                $postData = json_decode($rawInput, true) ?: $_POST;

                $id = (int)($postData['id'] ?? 0);
                $name = trim($postData['name'] ?? '');
                $address = trim($postData['address'] ?? '');
                $latitude = !empty($postData['latitude']) ? (float)$postData['latitude'] : null;
                $longitude = !empty($postData['longitude']) ? (float)$postData['longitude'] : null;
                $iframe_link = trim($postData['iframe_link'] ?? '');

                if (empty($name)) throw new Exception("Nama lokasi wilayah wajib diisi.");

                if ($id) {
                    $stmt = $pdo->prepare("UPDATE locations SET name = ?, address = ?, latitude = ?, longitude = ?, iframe_link = ? WHERE id = ?");
                    $stmt->execute([$name, $address, $latitude, $longitude, $iframe_link, $id]);
                    if (function_exists('logActivity')) logActivity("Update Lokasi", "Memperbarui lokasi: $name");
                    $msg = "Lokasi berhasil diperbarui.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO locations (name, address, latitude, longitude, iframe_link) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $address, $latitude, $longitude, $iframe_link]);
                    $id = (int)$pdo->lastInsertId();
                    if (function_exists('logActivity')) logActivity("Create Lokasi", "Menambahkan lokasi baru: $name");
                    $msg = "Lokasi baru berhasil ditambahkan.";
                }

                $saved = $pdo->query("SELECT * FROM locations WHERE id = $id")->fetch();
                echo json_encode(['status' => 'success', 'message' => $msg, 'data' => $saved]);
                break;
            }
            break;

        case 'tags':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $stmt = $pdo->query("
                    SELECT t.*, COUNT(at.article_id) as article_count 
                    FROM tags t 
                    LEFT JOIN article_tags at ON t.id = at.tag_id 
                    GROUP BY t.id 
                    ORDER BY t.id DESC
                ");
                $tags = $stmt->fetchAll();
                echo json_encode(['status' => 'success', 'data' => $tags]);
                break;
            }

            $action = $_REQUEST['action'] ?? '';
            if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $action === 'delete') {
                $id = (int)($_REQUEST['id'] ?? 0);
                if (!$id) throw new Exception("ID Tag tidak valid.");

                $stmt = $pdo->prepare("SELECT name FROM tags WHERE id = ?");
                $stmt->execute([$id]);
                $tagName = $stmt->fetchColumn() ?: "ID $id";

                // Remove pivot associations
                $pdo->prepare("DELETE FROM article_tags WHERE tag_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM tags WHERE id = ?")->execute([$id]);

                if (function_exists('logActivity')) {
                    logActivity("Delete Tag", "Menghapus tag: $tagName");
                }
                echo json_encode(['status' => 'success', 'message' => 'Tag berhasil dihapus.']);
                break;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $rawInput = file_get_contents('php://input');
                $postData = json_decode($rawInput, true) ?: $_POST;

                $id = (int)($postData['id'] ?? 0);
                $name = trim($postData['name'] ?? '');
                $slug = trim($postData['slug'] ?? '');

                if (empty($name)) throw new Exception("Nama tag wajib diisi.");

                if (empty($slug)) {
                    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
                }

                if ($id) {
                    $stmt = $pdo->prepare("UPDATE tags SET name = ?, slug = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $id]);
                    if (function_exists('logActivity')) logActivity("Update Tag", "Memperbarui tag: $name");
                    $msg = "Tag berhasil diperbarui.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
                    $stmt->execute([$name, $slug]);
                    $id = (int)$pdo->lastInsertId();
                    if (function_exists('logActivity')) logActivity("Create Tag", "Menambahkan tag baru: $name");
                    $msg = "Tag baru berhasil ditambahkan.";
                }

                $saved = $pdo->query("SELECT * FROM tags WHERE id = $id")->fetch();
                echo json_encode(['status' => 'success', 'message' => $msg, 'data' => $saved]);
                break;
            }
            break;

        case 'articles':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $id = (int)($_GET['id'] ?? 0);
                if ($id) {
                    // Fetch single article with its tags
                    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
                    $stmt->execute([$id]);
                    $article = $stmt->fetch();
                    if (!$article) throw new Exception("Artikel tidak ditemukan.");

                    $tagsStmt = $pdo->prepare("SELECT tag_id FROM article_tags WHERE article_id = ?");
                    $tagsStmt->execute([$id]);
                    $article['tag_ids'] = $tagsStmt->fetchAll(PDO::FETCH_COLUMN);

                    echo json_encode(['status' => 'success', 'data' => $article]);
                    break;
                }

                // Fetch list of articles
                $stmt = $pdo->query("
                    SELECT a.id, a.title, a.slug, a.status, a.views, a.image_path, a.seo_score, a.created_at, a.content_source,
                           GROUP_CONCAT(t.name SEPARATOR ', ') as tag_names
                    FROM articles a
                    LEFT JOIN article_tags at ON a.id = at.article_id
                    LEFT JOIN tags t ON at.tag_id = t.id
                    GROUP BY a.id
                    ORDER BY a.created_at DESC
                ");
                $articles = $stmt->fetchAll();
                echo json_encode(['status' => 'success', 'data' => $articles]);
                break;
            }

            $action = $_REQUEST['action'] ?? '';
            if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $action === 'delete') {
                $id = (int)($_REQUEST['id'] ?? 0);
                if (!$id) throw new Exception("ID Artikel tidak valid.");

                $stmt = $pdo->prepare("SELECT title, image_path FROM articles WHERE id = ?");
                $stmt->execute([$id]);
                $art = $stmt->fetch();

                if ($art) {
                    if (!empty($art['image_path'])) {
                        $fullImg = __DIR__ . '/../../' . $art['image_path'];
                        if (file_exists($fullImg)) @unlink($fullImg);
                    }
                    $pdo->prepare("DELETE FROM article_tags WHERE article_id = ?")->execute([$id]);
                    $pdo->prepare("DELETE FROM articles WHERE id = ?")->execute([$id]);

                    if (function_exists('logActivity')) {
                        logActivity("Delete Artikel", "Menghapus artikel: " . ($art['title'] ?? "ID $id"));
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'Artikel berhasil dihapus.']);
                break;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = (int)($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $slug = trim($_POST['slug'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'published';
                $seo_title = trim($_POST['seo_title'] ?? '') ?: null;
                $seo_description = trim($_POST['seo_description'] ?? '') ?: null;
                $focus_keyword = trim($_POST['focus_keyword'] ?? '') ?: null;
                $image_path = trim($_POST['current_image'] ?? '');

                if (empty($title)) throw new Exception("Judul artikel wajib diisi.");

                if (empty($slug)) {
                    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
                }

                // Handle image upload
                if (!empty($_FILES['image']['name'])) {
                    $uploadDir = __DIR__ . '/../../uploads/articles/';
                    $dbFolder = 'uploads/articles/';
                    $uploadedPath = uploadAndResize($_FILES['image'], $uploadDir, $dbFolder);
                    if ($uploadedPath) {
                        if ($id && !empty($image_path)) {
                            $oldFile = __DIR__ . '/../../' . $image_path;
                            if (file_exists($oldFile)) @unlink($oldFile);
                        }
                        $image_path = $uploadedPath;
                    }
                }

                // Calculate SEO score if helper function exists
                $seo_score = null;
                $seo_audit_log = null;
                if (function_exists('calculateSeoScoreLocal')) {
                    $seoResult = calculateSeoScoreLocal($title, $slug, $seo_title ?: $title, $seo_description, $focus_keyword);
                    $seo_score = $seoResult['score'] ?? 75;
                    $seo_audit_log = json_encode(['critique' => $seoResult['critique'] ?? []]);
                }

                if ($id) {
                    $stmt = $pdo->prepare("
                        UPDATE articles 
                        SET title = ?, slug = ?, content = ?, image_path = ?, status = ?, 
                            seo_title = ?, seo_description = ?, focus_keyword = ?, 
                            seo_score = ?, seo_audit_log = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $slug, $content, $image_path, $status, $seo_title, $seo_description, $focus_keyword, $seo_score, $seo_audit_log, $id]);
                    if (function_exists('logActivity')) logActivity("Update Artikel", "Memperbarui artikel: $title");
                    $msg = "Artikel berhasil diperbarui.";
                } else {
                    $source = 'Admin V2 (' . ($_SESSION['admin_username'] ?? 'Admin') . ')';
                    $stmt = $pdo->prepare("
                        INSERT INTO articles (title, slug, content, image_path, status, seo_title, seo_description, focus_keyword, seo_score, seo_audit_log, content_source) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$title, $slug, $content, $image_path, $status, $seo_title, $seo_description, $focus_keyword, $seo_score, $seo_audit_log, $source]);
                    $id = (int)$pdo->lastInsertId();
                    if (function_exists('logActivity')) logActivity("Create Artikel", "Membuat artikel: $title");
                    $msg = ($status === 'published') ? 'Artikel berhasil diterbitkan.' : 'Artikel disimpan sebagai Draft.';
                }

                // Sync tags
                $pdo->prepare("DELETE FROM article_tags WHERE article_id = ?")->execute([$id]);
                $tagsInput = $_POST['tags'] ?? [];
                if (!is_array($tagsInput)) {
                    $tagsInput = json_decode($tagsInput, true) ?: [];
                }
                if (!empty($tagsInput)) {
                    $tagInsert = $pdo->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)");
                    foreach ($tagsInput as $tId) {
                        $tagInsert->execute([$id, (int)$tId]);
                    }
                }

                $saved = $pdo->query("SELECT * FROM articles WHERE id = $id")->fetch();
                echo json_encode(['status' => 'success', 'message' => $msg, 'data' => $saved]);
                break;
            }
            break;

        case 'leads':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $tab = $_GET['tab'] ?? 'leads';

                if ($tab === 'wa_tracker') {
                    $totalWaClicks = (int)$pdo->query("SELECT COUNT(*) FROM wa_clicks")->fetchColumn();
                    $stmtToday = $pdo->prepare("SELECT COUNT(*) FROM wa_clicks WHERE DATE(clicked_at) = CURDATE()");
                    $stmtToday->execute();
                    $todayWaClicks = (int)$stmtToday->fetchColumn();

                    $sources = $pdo->query("SELECT source, COUNT(*) as count FROM wa_clicks GROUP BY source ORDER BY count DESC")->fetchAll();
                    $pages = $pdo->query("SELECT page_url, COUNT(*) as count FROM wa_clicks WHERE page_url IS NOT NULL AND page_url != '' GROUP BY page_url ORDER BY count DESC LIMIT 15")->fetchAll();
                    
                    $search = trim($_GET['search'] ?? '');
                    $logSql = "SELECT * FROM wa_clicks ";
                    $logParams = [];
                    if (!empty($search)) {
                        $logSql .= " WHERE source LIKE ? OR page_url LIKE ? OR ip_address LIKE ? ";
                        $logParams = ["%$search%", "%$search%", "%$search%"];
                    }
                    $logSql .= " ORDER BY clicked_at DESC LIMIT 50 ";
                    $logStmt = $pdo->prepare($logSql);
                    $logStmt->execute($logParams);
                    $logs = $logStmt->fetchAll();

                    echo json_encode([
                        'status' => 'success',
                        'data' => [
                            'total_clicks' => $totalWaClicks,
                            'today_clicks' => $todayWaClicks,
                            'sources' => $sources,
                            'pages' => $pages,
                            'logs' => $logs
                        ]
                    ]);
                    break;
                }

                // Default tab: CRM Leads
                $statusFilter = $_GET['status'] ?? '';
                $aiFilter = $_GET['ai_filter'] ?? '';
                $search = trim($_GET['search'] ?? '');

                $sql = "
                    SELECT l.*, s.title as service_name 
                    FROM leads l 
                    LEFT JOIN services s ON l.service_id = s.id 
                    WHERE 1=1 
                ";
                $params = [];

                if (!empty($statusFilter) && $statusFilter !== 'all') {
                    $sql .= " AND l.status = ? ";
                    $params[] = $statusFilter;
                }

                if (!empty($aiFilter) && $aiFilter !== 'all') {
                    $sql .= " AND l.ai_status = ? ";
                    $params[] = $aiFilter;
                }

                if (!empty($search)) {
                    $sql .= " AND (l.name LIKE ? OR l.email LIKE ? OR l.whatsapp LIKE ? OR l.message LIKE ?) ";
                    $params[] = "%$search%";
                    $params[] = "%$search%";
                    $params[] = "%$search%";
                    $params[] = "%$search%";
                }

                $sql .= " ORDER BY l.created_at DESC ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $leads = $stmt->fetchAll();

                // Counters
                $totalLeads = (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
                $newLeads = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
                $contactedLeads = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'contacted'")->fetchColumn();
                $closedLeads = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'closed'")->fetchColumn();
                $spamLeads = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'spam' OR ai_status = 'spam'")->fetchColumn();
                $unsortedLeads = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE ai_status = 'uncategorized'")->fetchColumn();
                $totalWaClicks = (int)$pdo->query("SELECT COUNT(*) FROM wa_clicks")->fetchColumn();
                $todayWaClicks = (int)$pdo->query("SELECT COUNT(*) FROM wa_clicks WHERE DATE(clicked_at) = CURDATE()")->fetchColumn();

                $topSourceStmt = $pdo->query("SELECT source FROM wa_clicks GROUP BY source ORDER BY COUNT(*) DESC LIMIT 1");
                $topSource = $topSourceStmt->fetchColumn() ?: '-';

                // Fetch services for dropdown
                $services = $pdo->query("SELECT id, title FROM services ORDER BY title ASC")->fetchAll();

                echo json_encode([
                    'status' => 'success',
                    'data' => $leads,
                    'services' => $services,
                    'summary' => [
                        'total' => $totalLeads,
                        'new' => $newLeads,
                        'contacted' => $contactedLeads,
                        'closed' => $closedLeads,
                        'spam' => $spamLeads,
                        'unsorted' => $unsortedLeads,
                        'total_wa_clicks' => $totalWaClicks,
                        'today_wa_clicks' => $todayWaClicks,
                        'top_source' => $topSource
                    ]
                ]);
                break;
            }

            $action = $_REQUEST['action'] ?? '';
            if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $action === 'delete') {
                $id = (int)($_REQUEST['id'] ?? 0);
                if (!$id) throw new Exception("ID Lead tidak valid.");

                $pdo->prepare("DELETE FROM leads WHERE id = ?")->execute([$id]);
                if (function_exists('logActivity')) logActivity("Delete Lead", "Menghapus lead ID: $id");
                echo json_encode(['status' => 'success', 'message' => 'Data lead berhasil dihapus.']);
                break;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $rawInput = file_get_contents('php://input');
                $postData = json_decode($rawInput, true) ?: $_POST;
                $action = $postData['action'] ?? $_GET['action'] ?? '';

                if ($action === 'scan_ai') {
                    // Trigger lead AI classification
                    $url = 'http://localhost:8080/arno-dc/admin/api/cron_classify_leads.php?key=adc_cron_secure';
                    $context = stream_context_create([
                        'http' => ['timeout' => 20, 'ignore_errors' => true]
                    ]);
                    $scanRes = @file_get_contents($url, false, $context);
                    $jsonRes = json_decode($scanRes, true);
                    $msg = $jsonRes['message'] ?? 'Scan AI selesai dijalankan.';
                    echo json_encode(['status' => 'success', 'message' => $msg]);
                    break;
                }

                $id = (int)($postData['id'] ?? 0);
                $status = $postData['status'] ?? 'new';

                if ($id) {
                    $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $id]);
                    if (function_exists('logActivity')) logActivity("Update Lead Status", "Mengubah status lead ID $id menjadi $status");
                    echo json_encode(['status' => 'success', 'message' => 'Status lead berhasil diperbarui.']);
                    break;
                }
            }
            break;

        case 'auto_content':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Fetch settings
                $stmtSet = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings");
                $settings = [];
                while ($row = $stmtSet->fetch()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }

                // Fetch keywords queue
                $stmtKw = $pdo->query("
                    SELECT k.*, a.title as article_title, a.slug as article_slug 
                    FROM auto_content_keywords k 
                    LEFT JOIN articles a ON k.article_id = a.id 
                    ORDER BY k.id DESC
                ");
                $keywords = $stmtKw->fetchAll();

                // Counters
                $countPending = (int)$pdo->query("SELECT COUNT(*) FROM auto_content_keywords WHERE status = 'pending'")->fetchColumn();
                $countProcessing = (int)$pdo->query("SELECT COUNT(*) FROM auto_content_keywords WHERE status = 'processing'")->fetchColumn();
                $countDone = (int)$pdo->query("SELECT COUNT(*) FROM auto_content_keywords WHERE status = 'done'")->fetchColumn();
                $countFailed = (int)$pdo->query("SELECT COUNT(*) FROM auto_content_keywords WHERE status = 'failed'")->fetchColumn();

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'settings' => $settings,
                        'keywords' => $keywords,
                        'summary' => [
                            'pending' => $countPending,
                            'processing' => $countProcessing,
                            'done' => $countDone,
                            'failed' => $countFailed,
                            'total' => count($keywords)
                        ]
                    ]
                ]);
                break;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $rawInput = file_get_contents('php://input');
                $postData = json_decode($rawInput, true) ?: $_POST;
                $action = $postData['action'] ?? $_GET['action'] ?? '';

                if ($action === 'add_keywords') {
                    $rawKws = $postData['keywords'] ?? '';
                    $lines = preg_split('/[\r\n,]+/', $rawKws);
                    $added = 0;
                    $insertStmt = $pdo->prepare("INSERT INTO auto_content_keywords (keyword) VALUES (?)");
                    foreach ($lines as $line) {
                        $kw = trim($line);
                        if (!empty($kw)) {
                            $insertStmt->execute([$kw]);
                            $added++;
                        }
                    }
                    if (function_exists('logActivity')) logActivity("Auto Content", "Menambahkan $added kata kunci ke antrean AI");
                    echo json_encode(['status' => 'success', 'message' => "$added kata kunci berhasil ditambahkan ke antrean."]);
                    break;
                }

                if ($action === 'retry_keyword') {
                    $kwId = (int)($postData['id'] ?? 0);
                    $pdo->prepare("UPDATE auto_content_keywords SET status = 'pending', error_message = NULL WHERE id = ?")->execute([$kwId]);
                    echo json_encode(['status' => 'success', 'message' => 'Status kata kunci di-reset ke pending.']);
                    break;
                }

                if ($action === 'reset_failed') {
                    $affected = $pdo->exec("UPDATE auto_content_keywords SET status = 'pending', error_message = NULL WHERE status = 'failed'");
                    if (function_exists('logActivity')) logActivity("Reset Failed Queue", "Reset $affected antrean gagal ke status pending");
                    echo json_encode(['status' => 'success', 'message' => "$affected antrean gagal berhasil di-reset ke pending."]);
                    break;
                }

                if ($action === 'clear_queue') {
                    $affected = $pdo->exec("DELETE FROM auto_content_keywords WHERE status != 'done'");
                    if (function_exists('logActivity')) logActivity("Clear Queue", "Membersihkan $affected antrean aktif");
                    echo json_encode(['status' => 'success', 'message' => "$affected antrean aktif berhasil dibersihkan."]);
                    break;
                }

                if ($action === 'delete_keyword') {
                    $kwId = (int)($postData['id'] ?? 0);
                    $pdo->prepare("DELETE FROM auto_content_keywords WHERE id = ?")->execute([$kwId]);
                    echo json_encode(['status' => 'success', 'message' => 'Kata kunci berhasil dihapus dari antrean.']);
                    break;
                }

                if ($action === 'update_turbo') {
                    $mode = ($postData['mode'] ?? '0') === '1' ? '1' : '0';
                    $delay = isset($postData['delay']) ? max(3, min(360, (int)$postData['delay'])) : 3;

                    $stmt = $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES ('worker_turbo_mode', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    $stmt->execute([$mode]);

                    $stmtDelay = $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES ('worker_delay_minutes', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    $stmtDelay->execute([$delay]);

                    echo json_encode(['status' => 'success', 'message' => 'Mode Turbo & jeda waktu berhasil diperbarui.']);
                    break;
                }

                if ($action === 'save_schedule') {
                    $configs = [
                        'ai_schedule_enabled' => ($postData['ai_schedule_enabled'] ?? '0') === '1' ? '1' : '0',
                        'ai_schedule_mode' => $postData['ai_schedule_mode'] ?? 'smart',
                        'ai_schedule_frequency' => $postData['ai_schedule_frequency'] ?? '3',
                        'ai_schedule_interval' => $postData['ai_schedule_interval'] ?? '1',
                        'ai_schedule_time' => $postData['ai_schedule_time'] ?? '08:00',
                        'ai_schedule_days' => isset($postData['ai_schedule_days']) ? (is_array($postData['ai_schedule_days']) ? json_encode($postData['ai_schedule_days']) : $postData['ai_schedule_days']) : '[]'
                    ];

                    $saveStmt = $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    foreach ($configs as $k => $v) {
                        $saveStmt->execute([$k, (string)$v]);
                    }

                    if (function_exists('logActivity')) logActivity("Update Schedule", "Memperbarui jadwal Auto Content AI");
                    echo json_encode(['status' => 'success', 'message' => 'Pengaturan jadwal berhasil disimpan.']);
                    break;
                }

                if ($action === 'save_settings') {
                    $settingsToSave = $postData['settings'] ?? [];
                    $saveStmt = $pdo->prepare("
                        INSERT INTO auto_content_settings (setting_key, setting_value) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                    ");
                    foreach ($settingsToSave as $k => $v) {
                        $saveStmt->execute([$k, is_array($v) ? json_encode($v) : (string)$v]);
                    }
                    if (function_exists('logActivity')) logActivity("Update AI Settings", "Memperbarui konfigurasi Auto Content AI");
                    echo json_encode(['status' => 'success', 'message' => 'Pengaturan AI berhasil disimpan.']);
                    break;
                }

                if ($action === 'run_single') {
                    // Call process_auto_content directly
                    // Include process_auto_content logic or trigger via curl/internal
                    define('IS_CRON', true);
                    ob_start();
                    require_once __DIR__ . '/../../admin/api/process_auto_content.php';
                    $out = ob_get_clean();
                    
                    // Decode output if it was json or plain
                    $jsonOut = json_decode($out, true);
                    if ($jsonOut) {
                        echo json_encode($jsonOut);
                    } else {
                        echo json_encode(['status' => 'success', 'message' => $out ?: 'Item processed']);
                    }
                    break;
                }
            }
            break;

        case 'users':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $sql = "
                    SELECT u.id, u.username, u.email, u.role, u.avatar, u.created_at,
                           (SELECT login_at FROM login_history WHERE user_id = u.id ORDER BY login_at DESC LIMIT 1) as last_login
                    FROM users u
                    ORDER BY u.created_at DESC
                ";
                $users = $pdo->query($sql)->fetchAll();

                echo json_encode([
                    'status' => 'success',
                    'data' => $users,
                    'current_user_id' => (int)($_SESSION['admin_id'] ?? 0),
                    'current_user_role' => $_SESSION['admin_role'] ?? 'admin'
                ]);
                break;
            }

            // Role check: Only admin can manage users
            if (($_SESSION['admin_role'] ?? '') !== 'admin') {
                http_response_code(403);
                throw new Exception("Hanya administrator yang memiliki akses ke manajemen user.");
            }

            $action = $_REQUEST['action'] ?? '';
            if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $action === 'delete') {
                $id = (int)($_REQUEST['id'] ?? 0);
                if (!$id) throw new Exception("ID User tidak valid.");

                if ($id === (int)$_SESSION['admin_id']) {
                    throw new Exception("Tidak dapat menghapus akun Anda sendiri yang sedang aktif.");
                }

                $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $u = $stmt->fetch();

                if ($u) {
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                    if (function_exists('logActivity')) {
                        logActivity('Delete User', "Menghapus user ID: $id ({$u['username']})");
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'User berhasil dihapus.']);
                break;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $rawInput = file_get_contents('php://input');
                $postData = json_decode($rawInput, true) ?: $_POST;
                $action = $postData['action'] ?? '';

                if ($action === 'add') {
                    $username = trim($postData['username'] ?? '');
                    $email = trim($postData['email'] ?? '');
                    $password = (string)($postData['password'] ?? '');
                    $role = in_array($postData['role'] ?? '', ['admin', 'editor']) ? $postData['role'] : 'editor';

                    if (empty($username) || empty($email) || empty($password)) {
                        throw new Exception("Username, Email, dan Password wajib diisi.");
                    }

                    // Check duplicate email
                    $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $chk->execute([$email]);
                    if ($chk->fetch()) {
                        throw new Exception("Email '$email' sudah digunakan oleh akun lain.");
                    }

                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $avatar = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&background=2563eb&color=fff";

                    $ins = $pdo->prepare("INSERT INTO users (username, email, password, role, avatar) VALUES (?, ?, ?, ?, ?)");
                    $ins->execute([$username, $email, $hashed, $role, $avatar]);
                    $newId = $pdo->lastInsertId();

                    if (function_exists('logActivity')) {
                        logActivity('Create User', "Menambah user baru: $username ($email)");
                    }

                    echo json_encode(['status' => 'success', 'message' => 'User baru berhasil ditambahkan.', 'id' => $newId]);
                    break;
                }

                if ($action === 'edit') {
                    $id = (int)($postData['id'] ?? $postData['user_id'] ?? 0);
                    if (!$id) throw new Exception("ID User tidak valid.");

                    $username = trim($postData['username'] ?? '');
                    $role = in_array($postData['role'] ?? '', ['admin', 'editor']) ? $postData['role'] : 'editor';
                    $password = (string)($postData['password'] ?? '');

                    if (empty($username)) throw new Exception("Username tidak boleh kosong.");

                    if (!empty($password)) {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare("UPDATE users SET username = ?, role = ?, password = ? WHERE id = ?");
                        $upd->execute([$username, $role, $hashed, $id]);
                    } else {
                        $upd = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                        $upd->execute([$username, $role, $id]);
                    }

                    // If self edit, update session
                    if ($id === (int)$_SESSION['admin_id']) {
                        $_SESSION['admin_username'] = $username;
                        $_SESSION['admin_role'] = $role;
                    }

                    if (function_exists('logActivity')) {
                        logActivity('Update User', "Mengubah data user ID $id ($username)");
                    }

                    echo json_encode(['status' => 'success', 'message' => 'Data user berhasil diperbarui.']);
                    break;
                }
            }
            break;

        case 'activity_logs':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
                $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 15;
                if ($limit > 100) $limit = 100;
                $offset = ($page - 1) * $limit;
                $search = trim($_GET['search'] ?? '');

                $searchFilter = "";
                $searchParams = [];
                if (!empty($search)) {
                    $searchFilter = " AND (a.action LIKE ? OR a.details LIKE ? OR u.username LIKE ?)";
                    $searchParams = ["%$search%", "%$search%", "%$search%"];
                }

                $searchFilterLogin = "";
                $searchParamsLogin = [];
                if (!empty($search)) {
                    $searchFilterLogin = " AND ('Login' LIKE ? OR CONCAT('IP: ', l.ip_address) LIKE ? OR u.username LIKE ?)";
                    $searchParamsLogin = ["%$search%", "%$search%", "%$search%"];
                }

                // Count total
                $countSQL = "
                    SELECT COUNT(*) as total FROM (
                        (SELECT a.id FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id WHERE 1=1 $searchFilter)
                        UNION ALL
                        (SELECT l.id FROM login_history l LEFT JOIN users u ON l.user_id = u.id WHERE 1=1 $searchFilterLogin)
                    ) AS combined
                ";
                $countStmt = $pdo->prepare($countSQL);
                $cIdx = 1;
                foreach ($searchParams as $p) $countStmt->bindValue($cIdx++, $p);
                foreach ($searchParamsLogin as $p) $countStmt->bindValue($cIdx++, $p);
                $countStmt->execute();
                $totalRecords = (int)$countStmt->fetchColumn();

                // Fetch combined data
                $sql = "
                    (SELECT 'activity' as log_type, a.id, a.user_id, a.action, a.details, a.ip_address, a.created_at, u.username, u.avatar 
                    FROM activity_logs a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    WHERE 1=1 $searchFilter)
                    UNION ALL 
                    (SELECT 'login' as log_type, l.id, l.user_id, 'Login' as action, CONCAT('IP: ', l.ip_address) as details, l.ip_address, l.login_at as created_at, u.username, u.avatar 
                    FROM login_history l 
                    LEFT JOIN users u ON l.user_id = u.id 
                    WHERE 1=1 $searchFilterLogin)
                    ORDER BY created_at DESC 
                    LIMIT ? OFFSET ?
                ";

                $stmt = $pdo->prepare($sql);
                $sIdx = 1;
                foreach ($searchParams as $p) $stmt->bindValue($sIdx++, $p);
                foreach ($searchParamsLogin as $p) $stmt->bindValue($sIdx++, $p);
                $stmt->bindValue($sIdx++, (int)$limit, PDO::PARAM_INT);
                $stmt->bindValue($sIdx++, (int)$offset, PDO::PARAM_INT);
                $stmt->execute();
                $logs = $stmt->fetchAll();

                // KPI Summaries
                $totalActs = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
                $todayActs = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                $totalLogins = (int)$pdo->query("SELECT COUNT(*) FROM login_history")->fetchColumn();
                $todayLogins = (int)$pdo->query("SELECT COUNT(*) FROM login_history WHERE DATE(login_at) = CURDATE()")->fetchColumn();

                echo json_encode([
                    'status' => 'success',
                    'data' => $logs,
                    'pagination' => [
                        'current_page' => $page,
                        'limit' => $limit,
                        'total_records' => $totalRecords,
                        'total_pages' => ceil($totalRecords / $limit) ?: 1
                    ],
                    'summary' => [
                        'total_activities' => $totalActs,
                        'today_activities' => $todayActs,
                        'total_logins' => $totalLogins,
                        'today_logins' => $todayLogins
                    ]
                ]);
            }
            break;

        case 'reporting':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');

                // Calculate Previous Period for Growth
                $start = new DateTime($startDate);
                $end = new DateTime($endDate);
                $diff = $start->diff($end)->days + 1;
                $prevStartDate = date('Y-m-d', strtotime("$startDate - $diff days"));
                $prevEndDate = date('Y-m-d', strtotime("$endDate - $diff days"));

                // Helper for basic stats
                $stmtBasic = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_views, 
                        COUNT(DISTINCT ip_address) as unique_visitors,
                        SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bot_count
                    FROM visitor_analytics 
                    WHERE visited_at BETWEEN ? AND ?
                ");
                $stmtBasic->execute([$startDate, $endDate]);
                $curr = $stmtBasic->fetch();

                $stmtPrev = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_views, 
                        COUNT(DISTINCT ip_address) as unique_visitors,
                        SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bot_count
                    FROM visitor_analytics 
                    WHERE visited_at BETWEEN ? AND ?
                ");
                $stmtPrev->execute([$prevStartDate, $prevEndDate]);
                $prev = $stmtPrev->fetch();

                function calcGrowthPct($cVal, $pVal) {
                    if ((int)$pVal === 0) return (int)$cVal > 0 ? 100 : 0;
                    return round((((int)$cVal - (int)$pVal) / (int)$pVal) * 100, 1);
                }

                $growth = [
                    'total_views' => calcGrowthPct($curr['total_views'] ?? 0, $prev['total_views'] ?? 0),
                    'unique_visitors' => calcGrowthPct($curr['unique_visitors'] ?? 0, $prev['unique_visitors'] ?? 0),
                    'bot_count' => calcGrowthPct($curr['bot_count'] ?? 0, $prev['bot_count'] ?? 0)
                ];

                // WA Clicks
                $stmtWa = $pdo->prepare("SELECT COUNT(*) FROM wa_clicks WHERE DATE(clicked_at) BETWEEN ? AND ?");
                $stmtWa->execute([$startDate, $endDate]);
                $totalWaClicks = (int)$stmtWa->fetchColumn();

                // Conversion Rate
                $uniqueHuman = (int)($curr['unique_visitors'] ?? 0);
                $conversionRate = $uniqueHuman > 0 ? round(($totalWaClicks / $uniqueHuman) * 100, 2) : 0;

                // Bounce Rate
                $stmtBounce = $pdo->prepare("
                    SELECT COUNT(*) as pages_viewed
                    FROM visitor_analytics
                    WHERE visited_at BETWEEN ? AND ? AND is_bot = 0
                    GROUP BY ip_address, visited_at
                ");
                $stmtBounce->execute([$startDate, $endDate]);
                $sessions = $stmtBounce->fetchAll();
                $totalSessions = count($sessions);
                $bounces = 0;
                foreach ($sessions as $s) {
                    if ($s['pages_viewed'] == 1) $bounces++;
                }
                $bounceRate = $totalSessions > 0 ? round(($bounces / $totalSessions) * 100, 1) : 0;

                // Trends Daily
                $stmtTrend = $pdo->prepare("
                    SELECT 
                        visited_at as date, 
                        COUNT(*) as total, 
                        COUNT(DISTINCT ip_address) as unique_visits,
                        SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bots,
                        SUM(CASE WHEN is_bot = 0 THEN 1 ELSE 0 END) as humans
                    FROM visitor_analytics
                    WHERE visited_at BETWEEN ? AND ?
                    GROUP BY visited_at
                    ORDER BY visited_at ASC
                ");
                $stmtTrend->execute([$startDate, $endDate]);
                $trendRows = $stmtTrend->fetchAll();

                // 7-day Forecast based on average recent trend
                $forecast = [];
                if (count($trendRows) >= 3) {
                    $recentPoints = array_slice($trendRows, -7);
                    $sumRecent = array_sum(array_column($recentPoints, 'total'));
                    $avgRecent = max(1, round($sumRecent / count($recentPoints)));
                    
                    $lastDate = end($trendRows)['date'];
                    for ($i = 1; $i <= 7; $i++) {
                        $fDate = date('Y-m-d', strtotime("$lastDate + $i days"));
                        // Add slight organic variance (±10%)
                        $variance = sin($i) * ($avgRecent * 0.1);
                        $forecast[] = [
                            'date' => $fDate,
                            'val' => max(0, round($avgRecent + $variance))
                        ];
                    }
                }

                // Heatmaps (Day of week 0-6 x Hour 0-23)
                $stmtHeatmap = $pdo->prepare("
                    SELECT 
                        DAYOFWEEK(created_at) - 1 as day_index, 
                        HOUR(created_at) as hour_index,
                        COUNT(*) as visits
                    FROM visitor_analytics
                    WHERE visited_at BETWEEN ? AND ?
                    GROUP BY day_index, hour_index
                ");
                $stmtHeatmap->execute([$startDate, $endDate]);
                $heatmapVisitor = $stmtHeatmap->fetchAll(PDO::FETCH_ASSOC);

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
                $heatmapWa = $stmtWaHeatmap->fetchAll(PDO::FETCH_ASSOC);

                // Detailed WA Click Logs (with button labels & article titles)
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
                    $src = $row['source'] ?? '';
                    $page = $row['page_url'] ?? '/';
                    $artTitle = $row['article_title'] ?? null;
                    
                    $btnLabel = '';
                    if (!empty($artTitle)) {
                        $btnLabel = 'Tombol WA di Artikel: "' . $artTitle . '"';
                    } elseif ($src === 'floating_widget') {
                        $btnLabel = ($page === '/' || strpos($page, 'index') !== false) 
                            ? 'Float Button di Halaman Home' 
                            : 'Float Button Melayang (' . $page . ')';
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
                        $btnLabel = 'Tombol WA (' . ($src ?: 'Umum') . ')';
                    }

                    $waLogs[] = [
                        'no' => $idx + 1,
                        'id' => (int)$row['id'],
                        'clicked_at' => $row['clicked_at'],
                        'button_label' => $btnLabel,
                        'page_url' => $page,
                        'ip_address' => $row['ip_address'] ?? '-',
                        'location' => trim(($row['city'] ?? '') . ', ' . ($row['country'] ?? ''), ', ') ?: 'Unknown',
                        'device' => $row['device'] ?: 'Unknown'
                    ];
                }

                // Top Pages
                $stmtPages = $pdo->prepare("
                    SELECT page_url, COUNT(*) as views 
                    FROM visitor_analytics 
                    WHERE visited_at BETWEEN ? AND ? AND is_bot = 0 AND page_url IS NOT NULL AND page_url != ''
                    GROUP BY page_url 
                    ORDER BY views DESC 
                    LIMIT 10
                ");
                $stmtPages->execute([$startDate, $endDate]);
                $topPages = $stmtPages->fetchAll();

                // Top Cities
                $stmtCities = $pdo->prepare("
                    SELECT city, COUNT(DISTINCT ip_address) as visitors
                    FROM visitor_analytics
                    WHERE visited_at BETWEEN ? AND ? AND city IS NOT NULL AND city != 'Unknown' AND city != ''
                    GROUP BY city
                    ORDER BY visitors DESC
                    LIMIT 6
                ");
                $stmtCities->execute([$startDate, $endDate]);
                $topCities = $stmtCities->fetchAll();

                // Devices
                $stmtDevices = $pdo->prepare("
                    SELECT device, COUNT(*) as count 
                    FROM visitor_analytics 
                    WHERE visited_at BETWEEN ? AND ? 
                    GROUP BY device
                ");
                $stmtDevices->execute([$startDate, $endDate]);
                $devices = $stmtDevices->fetchAll();

                // Top Traffic Sources (Referrer)
                $stmtSources = $pdo->prepare("
                    SELECT COALESCE(NULLIF(referrer, ''), 'Direct / Langsung') as source, COUNT(*) as visits
                    FROM visitor_analytics
                    WHERE visited_at BETWEEN ? AND ? AND is_bot = 0
                    GROUP BY source
                    ORDER BY visits DESC
                    LIMIT 5
                ");
                $stmtSources->execute([$startDate, $endDate]);
                $topSources = $stmtSources->fetchAll();

                // Telegram report settings
                $stmtTg = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings WHERE setting_key IN ('tg_report_time', 'tg_report_config', 'tg_bot_token', 'tg_chat_id')");
                $tgSettings = [];
                while ($row = $stmtTg->fetch()) {
                    $tgSettings[$row['setting_key']] = $row['setting_value'];
                }

                // AI Insight summary
                $viewsNum = (int)($curr['total_views'] ?? 0);
                $growthViews = $growth['total_views'];
                $growthSign = $growthViews >= 0 ? "+$growthViews%" : "$growthViews%";
                $smartSummary = "Dalam periode $startDate hingga $endDate, website mencatat <strong>$viewsNum</strong> total kunjungan ($growthSign vs periode lalu) dengan <strong>$uniqueHuman</strong> pengunjung unik dan <strong>$totalWaClicks</strong> klik WhatsApp (konversi <strong>$conversionRate%</strong>).";

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'summary' => [
                            'total_views' => (int)($curr['total_views'] ?? 0),
                            'unique_visitors' => $uniqueHuman,
                            'bot_count' => (int)($curr['bot_count'] ?? 0),
                            'total_wa_clicks' => $totalWaClicks,
                            'conversion_rate' => $conversionRate,
                            'bounce_rate' => $bounceRate
                        ],
                        'growth' => $growth,
                        'smart_summary' => $smartSummary,
                        'trends' => $trendRows,
                        'forecast' => $forecast,
                        'charts' => [
                            'traffic' => $trendRows,
                            'heatmap' => $heatmapVisitor,
                            'wa_heatmap' => $heatmapWa
                        ],
                        'wa_logs' => $waLogs,
                        'top_pages' => $topPages,
                        'top_cities' => $topCities,
                        'devices' => $devices,
                        'top_sources' => $topSources,
                        'telegram_config' => [
                            'time' => $tgSettings['tg_report_time'] ?? '08:00',
                            'config' => json_decode($tgSettings['tg_report_config'] ?? '["leads","traffic"]', true) ?: ['leads', 'traffic'],
                            'has_bot' => !empty($tgSettings['tg_bot_token']) && !empty($tgSettings['tg_chat_id'])
                        ]
                    ]
                ]);
                break;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $rawInput = file_get_contents('php://input');
                $postData = json_decode($rawInput, true) ?: $_POST;
                $action = $postData['action'] ?? '';

                if ($action === 'save_telegram_config') {
                    $reportTime = $postData['time'] ?? '08:00';
                    $reportConfig = json_encode($postData['config'] ?? ['leads', 'traffic']);

                    $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES ('tg_report_time', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
                        ->execute([$reportTime]);
                    $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES ('tg_report_config', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
                        ->execute([$reportConfig]);

                    echo json_encode(['status' => 'success', 'message' => 'Pengaturan laporan Telegram berhasil disimpan.']);
                    break;
                }

                if ($action === 'send_report_now') {
                    // Trigger Telegram Report Execution
                    $_GET['key'] = 'adc_cron_secure';
                    if (!defined('IS_CRON')) define('IS_CRON', true);
                    ob_start();
                    require_once __DIR__ . '/../../admin/api/cron_daily_report.php';
                    $cronLog = ob_get_clean();

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Laporan Telegram berhasil dikirim ke grup/channel.',
                        'log' => $cronLog
                    ]);
                    break;
                }
            }
            break;

        case 'settings':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Fetch site_settings
                $siteSettings = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
                // Fetch contact_info
                $contactInfo = $pdo->query("SELECT contact_key, contact_value FROM contact_info")->fetchAll(PDO::FETCH_KEY_PAIR);
                // Fetch auto_content_settings
                $aiSettings = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'hero' => [
                            'hero_title' => $siteSettings['hero_title'] ?? '',
                            'hero_description' => $siteSettings['hero_description'] ?? '',
                            'hero_btn_primary' => $siteSettings['hero_btn_primary'] ?? '',
                            'hero_btn_secondary' => $siteSettings['hero_btn_secondary'] ?? '',
                            'hero_bg_image' => $siteSettings['hero_bg_image'] ?? ''
                        ],
                        'identity' => [
                            'phone' => $contactInfo['phone'] ?? '',
                            'email' => $contactInfo['email'] ?? '',
                            'address' => $contactInfo['address'] ?? '',
                            'whatsapp' => $contactInfo['whatsapp'] ?? '',
                            'site_logo' => $siteSettings['site_logo'] ?? '',
                            'site_icon' => $siteSettings['site_icon'] ?? ''
                        ],
                        'whatsapp' => [
                            'wa_template_general' => $siteSettings['wa_template_general'] ?? '',
                            'wa_template' => $siteSettings['wa_template'] ?? ''
                        ],
                        'telegram' => [
                            'tg_bot_token' => $aiSettings['tg_bot_token'] ?? '',
                            'tg_chat_id' => $aiSettings['tg_chat_id'] ?? '',
                            'tg_report_time' => $aiSettings['tg_report_time'] ?? '08:00',
                            'tg_report_config' => json_decode($aiSettings['tg_report_config'] ?? '["leads","traffic"]', true) ?: ['leads', 'traffic'],
                            'tg_notify_enabled' => ($aiSettings['tg_notify_enabled'] ?? '0') === '1',
                            'tg_log_notify_enabled' => ($aiSettings['tg_log_notify_enabled'] ?? '0') === '1',
                            'tg_site_url' => $aiSettings['tg_site_url'] ?? '',
                            'tg_webhook_token' => $aiSettings['tg_webhook_token'] ?? ''
                        ],
                        'ai' => [
                            'ai_active_provider' => $aiSettings['ai_active_provider'] ?? 'gemini',
                            'ai_config_gemini_keys' => json_decode($aiSettings['ai_config_gemini_keys'] ?? '[]', true) ?: ($aiSettings['ai_api_key'] ? [$aiSettings['ai_api_key']] : []),
                            'ai_config_groq_keys' => json_decode($aiSettings['ai_config_groq_keys'] ?? '[]', true) ?: [],
                            'ai_config_gemini_model' => $aiSettings['ai_config_gemini_model'] ?? ($aiSettings['ai_model'] ?? 'gemini-2.5-flash'),
                            'ai_config_groq_model' => $aiSettings['ai_config_groq_model'] ?? 'llama-3.3-70b-versatile',
                            'ai_system_instruction' => $aiSettings['ai_system_instruction'] ?? '',
                            'ai_prompt_template' => $aiSettings['ai_prompt_template'] ?? '',
                            'auto_publish' => ($aiSettings['auto_publish'] ?? '1') === '1',
                            'ai_generate_image' => ($aiSettings['ai_generate_image'] ?? '1') === '1',
                            'ai_image_priority' => json_decode($aiSettings['ai_image_priority'] ?? '["pollinations","huggingface","pexels","google"]', true) ?: ['pollinations','huggingface','pexels','google'],
                            'ai_huggingface_token' => $aiSettings['ai_huggingface_token'] ?? '',
                            'ai_pexels_key' => $aiSettings['ai_pexels_key'] ?? '',
                            'ai_image_keep_people' => ($aiSettings['ai_image_keep_people'] ?? '1') === '1'
                        ],
                        'system' => [
                            'site_meta_title' => $siteSettings['site_meta_title'] ?? 'Arno D Clean - Jasa Cuci Kasur & Sofa Tangerang',
                            'site_meta_description' => $siteSettings['site_meta_description'] ?? 'Jasa cuci sofa, kasur, dan karpet profesional di Tangerang. Bersih, wangi, dan bebas tungau.',
                            'log_retention_days' => (int)($siteSettings['log_retention_days'] ?? 30)
                        ]
                    ]
                ]);
                break;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $raw = file_get_contents('php://input');
                $json = json_decode($raw, true);
                if ($json && is_array($json)) {
                    $_POST = array_merge($_POST, $json);
                }

                $action = $_POST['action'] ?? '';

                // Action: Test AI Key or Get Live Models
                if ($action === 'test_ai_key' || $action === 'get_live_models') {
                    $provider = $_POST['provider'] ?? 'gemini';
                    $apiKey = trim($_POST['api_key'] ?? '');
                    
                    // Fallback to saved key if empty
                    if (empty($apiKey)) {
                        if ($provider === 'groq') {
                            $savedGroq = json_decode($pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key = 'ai_config_groq_keys'")->fetchColumn() ?: '[]', true);
                            $apiKey = $savedGroq[0] ?? '';
                        } else {
                            $savedGemini = json_decode($pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key = 'ai_config_gemini_keys'")->fetchColumn() ?: '[]', true);
                            $apiKey = $savedGemini[0] ?? ($pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key = 'ai_api_key'")->fetchColumn() ?: '');
                        }
                    }

                    if (empty($apiKey)) {
                        throw new Exception("API Key tidak boleh kosong. Masukkan minimal 1 API Key.");
                    }

                    if ($provider === 'groq') {
                        $url = "https://api.groq.com/openai/v1/models";
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $apiKey]);
                        $res = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curlErr = curl_error($ch);
                        curl_close($ch);

                        if ($curlErr) throw new Exception("Gagal menghubungi Groq: " . $curlErr);
                        $resData = json_decode($res, true);
                        if ($httpCode !== 200 || !empty($resData['error'])) {
                            $errMsg = $resData['error']['message'] ?? 'Koneksi API Groq gagal.';
                            echo json_encode(['status' => 'error', 'message' => $errMsg]);
                            break;
                        }
                        
                        $rawModels = $resData['data'] ?? [];
                        $models = [];
                        foreach ($rawModels as $m) {
                            $mId = $m['id'];
                            // Filter useful text models
                            if (strpos($mId, 'whisper') === false && strpos($mId, 'guard') === false) {
                                $models[] = [
                                    'id' => $mId,
                                    'name' => ucwords(str_replace(['-', '_', '/'], ' ', $mId)),
                                    'is_recommended' => (strpos($mId, 'llama-3.3') !== false || strpos($mId, 'qwen') !== false || strpos($mId, 'gpt-oss') !== false)
                                ];
                            }
                        }
                        if (empty($models)) {
                            foreach ($rawModels as $m) {
                                $models[] = ['id' => $m['id'], 'name' => $m['id'], 'is_recommended' => false];
                            }
                        }

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Berhasil memuat ' . count($models) . ' model Groq secara langsung dari API!',
                            'models' => $models
                        ]);
                        break;
                    } else {
                        // Use v1beta to get all newest and active Gemini models
                        $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($apiKey);
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                        $res = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curlErr = curl_error($ch);
                        curl_close($ch);

                        if ($curlErr) throw new Exception("Gagal menghubungi Google: " . $curlErr);
                        $resData = json_decode($res, true);
                        if ($httpCode !== 200 || !empty($resData['error'])) {
                            $errMsg = $resData['error']['message'] ?? 'Koneksi API Gemini gagal.';
                            echo json_encode(['status' => 'error', 'message' => $errMsg]);
                            break;
                        }

                        $models = [];
                        $priorityModels = ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-flash-latest', 'gemini-2.5-flash-lite', 'gemini-3-flash-preview', 'gemini-pro-latest'];
                        
                        foreach ($resData['models'] ?? [] as $m) {
                            if (!empty($m['supportedGenerationMethods']) && in_array('generateContent', $m['supportedGenerationMethods'])) {
                                $mId = str_replace('models/', '', $m['name']);
                                // Exclude TTS or audio/image specialized if we need text generation
                                if (strpos($mId, 'tts') !== false || strpos($mId, 'image') !== false || strpos($mId, 'transcribe') !== false) {
                                    continue;
                                }
                                $dispName = $m['displayName'] ?? $mId;
                                $isRec = in_array($mId, $priorityModels) || (strpos($mId, '2.5-flash') !== false) || (strpos($mId, 'latest') !== false);
                                
                                $models[] = [
                                    'id' => $mId,
                                    'name' => $dispName,
                                    'description' => $m['description'] ?? '',
                                    'is_recommended' => $isRec
                                ];
                            }
                        }

                        // Sort recommended first
                        usort($models, function($a, $b) {
                            if ($a['is_recommended'] === $b['is_recommended']) {
                                return strcmp($a['id'], $b['id']);
                            }
                            return $a['is_recommended'] ? -1 : 1;
                        });

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Berhasil memuat ' . count($models) . ' model Gemini aktif secara langsung dari Google API!',
                            'models' => $models
                        ]);
                        break;
                    }
                }

                // Action: Set Telegram Webhook
                if ($action === 'set_telegram_webhook') {
                    $botToken = trim($_POST['tg_bot_token'] ?? $pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key='tg_bot_token'")->fetchColumn() ?: '');
                    $siteUrl = rtrim(trim($_POST['tg_site_url'] ?? $pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key='tg_site_url'")->fetchColumn() ?: ''), '/');
                    $secret = trim($_POST['tg_webhook_token'] ?? $pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key='tg_webhook_token'")->fetchColumn() ?: '');

                    if (empty($botToken) || empty($siteUrl) || empty($secret)) {
                        throw new Exception("Bot Token, Site URL, dan Webhook Secret Token wajib diisi untuk registrasi webhook.");
                    }

                    $webhookUrl = $siteUrl . "/admin/api/telegram_webhook.php?token=" . urlencode($secret);
                    $apiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl);
                    $ch = curl_init($apiUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                    $res = curl_exec($ch);
                    $resData = json_decode($res, true);
                    curl_close($ch);

                    if ($resData && ($resData['ok'] ?? false)) {
                        echo json_encode(['status' => 'success', 'message' => 'Telegram Webhook berhasil diaktifkan: ' . ($resData['description'] ?? 'OK')]);
                    } else {
                        $err = $resData['description'] ?? 'Gagal mengatur webhook Telegram.';
                        echo json_encode(['status' => 'error', 'message' => 'Telegram Error: ' . $err]);
                    }
                    break;
                }

                // Action: Test Telegram Notification
                if ($action === 'test_telegram_notification') {
                    $botToken = trim($_POST['tg_bot_token'] ?? $pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key='tg_bot_token'")->fetchColumn() ?: '');
                    $chatId = trim($_POST['tg_chat_id'] ?? $pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key='tg_chat_id'")->fetchColumn() ?: '');

                    if (empty($botToken) || empty($chatId)) {
                        throw new Exception("Bot Token dan Chat ID wajib diisi.");
                    }

                    $testMsg = "🚀 <b>Test Notifikasi Admin V2</b>\n\nKoneksi Bot Telegram Arno D-Clean berhasil terhubung pada " . date('d M Y H:i:s') . " WIB.\n\nSistem siap menerima notifikasi pesanan dan rekapan performa.";
                    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
                    $ch = curl_init($apiUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, [
                        'chat_id' => $chatId,
                        'text' => $testMsg,
                        'parse_mode' => 'HTML'
                    ]);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                    $res = curl_exec($ch);
                    $resData = json_decode($res, true);
                    curl_close($ch);

                    if ($resData && ($resData['ok'] ?? false)) {
                        echo json_encode(['status' => 'success', 'message' => 'Pesan test berhasil terkirim ke Telegram!']);
                    } else {
                        $err = $resData['description'] ?? 'Gagal mengirim pesan test.';
                        echo json_encode(['status' => 'error', 'message' => 'Telegram Error: ' . $err]);
                    }
                    break;
                }

                $tab = $_POST['tab'] ?? '';

                // Handle file uploads helper
                $handleUpload = function($fileKey, $destDir, $prefix) {
                    if (empty($_FILES[$fileKey]['name'])) return null;
                    $file = $_FILES[$fileKey];
                    if ($file['error'] !== UPLOAD_ERR_OK) return null;
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'ico'])) return null;
                    if (!file_exists($destDir)) mkdir($destDir, 0755, true);
                    $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . $ext;
                    $targetPath = $destDir . '/' . $filename;
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        return str_replace(__DIR__ . '/../../', '', $targetPath);
                    }
                    return null;
                };

                if ($tab === 'hero') {
                    $keys = ['hero_title', 'hero_description', 'hero_btn_primary', 'hero_btn_secondary'];
                    $saveStmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value, label) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    foreach ($keys as $k) {
                        if (isset($_POST[$k])) {
                            $saveStmt->execute([$k, (string)$_POST[$k], ucwords(str_replace('_', ' ', $k))]);
                        }
                    }

                    $uploadedHero = $handleUpload('hero_bg_image', __DIR__ . '/../../uploads/hero', 'hero_bg');
                    if ($uploadedHero) {
                        $saveStmt->execute(['hero_bg_image', $uploadedHero, 'Hero Background Image']);
                    }

                    if (function_exists('logActivity')) logActivity("Update Settings", "Mengubah tampilan Hero");
                    echo json_encode(['status' => 'success', 'message' => 'Pengaturan Hero berhasil disimpan.']);
                    break;
                }

                if ($tab === 'identity') {
                    $contactKeys = ['phone', 'email', 'address', 'whatsapp'];
                    $contactStmt = $pdo->prepare("INSERT INTO contact_info (contact_key, contact_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE contact_value = VALUES(contact_value)");
                    foreach ($contactKeys as $k) {
                        if (isset($_POST[$k])) {
                            $contactStmt->execute([$k, (string)$_POST[$k]]);
                        }
                    }

                    $siteStmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value, label) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    $uploadedLogo = $handleUpload('site_logo', __DIR__ . '/../../uploads/settings', 'logo');
                    if ($uploadedLogo) {
                        $siteStmt->execute(['site_logo', $uploadedLogo, 'Website Logo']);
                    }
                    $uploadedIcon = $handleUpload('site_icon', __DIR__ . '/../../uploads/settings', 'icon');
                    if ($uploadedIcon) {
                        $siteStmt->execute(['site_icon', $uploadedIcon, 'Website Icon']);
                    }

                    if (function_exists('logActivity')) logActivity("Update Settings", "Mengubah identitas & kontak website");
                    echo json_encode(['status' => 'success', 'message' => 'Identitas dan kontak berhasil disimpan.']);
                    break;
                }

                if ($tab === 'whatsapp') {
                    $keys = ['wa_template_general', 'wa_template'];
                    $saveStmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value, label) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    foreach ($keys as $k) {
                        if (isset($_POST[$k])) {
                            $label = ($k === 'wa_template') ? 'Template Pesan Layanan' : 'Template Pesan Umum';
                            $saveStmt->execute([$k, (string)$_POST[$k], $label]);
                        }
                    }
                    if (function_exists('logActivity')) logActivity("Update Settings", "Mengubah template WhatsApp");
                    echo json_encode(['status' => 'success', 'message' => 'Template WhatsApp berhasil disimpan.']);
                    break;
                }

                if ($tab === 'telegram') {
                    $tgKeys = ['tg_bot_token', 'tg_chat_id', 'tg_report_time', 'tg_site_url', 'tg_webhook_token'];
                    $saveStmt = $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    foreach ($tgKeys as $k) {
                        if (isset($_POST[$k])) {
                            $saveStmt->execute([$k, (string)$_POST[$k]]);
                        }
                    }
                    if (isset($_POST['tg_report_config'])) {
                        $cfg = is_array($_POST['tg_report_config']) ? json_encode($_POST['tg_report_config']) : (string)$_POST['tg_report_config'];
                        $saveStmt->execute(['tg_report_config', $cfg]);
                    }
                    if (isset($_POST['tg_notify_enabled'])) {
                        $saveStmt->execute(['tg_notify_enabled', $_POST['tg_notify_enabled'] ? '1' : '0']);
                    }
                    if (isset($_POST['tg_log_notify_enabled'])) {
                        $saveStmt->execute(['tg_log_notify_enabled', $_POST['tg_log_notify_enabled'] ? '1' : '0']);
                    }
                    if (function_exists('logActivity')) logActivity("Update Settings", "Mengubah konfigurasi notifikasi Telegram");
                    echo json_encode(['status' => 'success', 'message' => 'Pengaturan Telegram berhasil disimpan.']);
                    break;
                }

                if ($tab === 'ai') {
                    $aiKeys = ['ai_active_provider', 'ai_config_gemini_model', 'ai_config_groq_model', 'ai_system_instruction', 'ai_prompt_template', 'ai_huggingface_token', 'ai_pexels_key'];
                    $saveStmt = $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    foreach ($aiKeys as $k) {
                        if (isset($_POST[$k])) {
                            $saveStmt->execute([$k, (string)$_POST[$k]]);
                        }
                    }
                    if (isset($_POST['ai_config_gemini_keys'])) {
                        $keys = is_array($_POST['ai_config_gemini_keys']) ? json_encode($_POST['ai_config_gemini_keys']) : (string)$_POST['ai_config_gemini_keys'];
                        $saveStmt->execute(['ai_config_gemini_keys', $keys]);
                    }
                    if (isset($_POST['ai_config_groq_keys'])) {
                        $keys = is_array($_POST['ai_config_groq_keys']) ? json_encode($_POST['ai_config_groq_keys']) : (string)$_POST['ai_config_groq_keys'];
                        $saveStmt->execute(['ai_config_groq_keys', $keys]);
                    }
                    if (isset($_POST['ai_image_priority'])) {
                        $prio = is_array($_POST['ai_image_priority']) ? json_encode($_POST['ai_image_priority']) : (string)$_POST['ai_image_priority'];
                        $saveStmt->execute(['ai_image_priority', $prio]);
                    }
                    if (isset($_POST['auto_publish'])) {
                        $saveStmt->execute(['auto_publish', $_POST['auto_publish'] ? '1' : '0']);
                    }
                    if (isset($_POST['ai_generate_image'])) {
                        $saveStmt->execute(['ai_generate_image', $_POST['ai_generate_image'] ? '1' : '0']);
                    }
                    if (isset($_POST['ai_image_keep_people'])) {
                        $saveStmt->execute(['ai_image_keep_people', $_POST['ai_image_keep_people'] ? '1' : '0']);
                    }
                    if (function_exists('logActivity')) logActivity("Update Settings", "Mengubah pengaturan AI Brain & Strategi");
                    echo json_encode(['status' => 'success', 'message' => 'Pengaturan AI Brain & Strategi berhasil disimpan.']);
                    break;
                }

                if ($tab === 'system') {
                    $saveStmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value, label) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    if (isset($_POST['site_meta_title'])) {
                        $saveStmt->execute(['site_meta_title', (string)$_POST['site_meta_title'], 'Site Meta Title']);
                    }
                    if (isset($_POST['site_meta_description'])) {
                        $saveStmt->execute(['site_meta_description', (string)$_POST['site_meta_description'], 'Site Meta Description']);
                    }
                    if (isset($_POST['log_retention_days'])) {
                        $days = max(7, (int)$_POST['log_retention_days']);
                        $saveStmt->execute(['log_retention_days', (string)$days, 'Log Retention Days']);
                    }
                    if (function_exists('logActivity')) logActivity("Update Settings", "Mengubah pengaturan sistem & SEO");
                    echo json_encode(['status' => 'success', 'message' => 'Pengaturan Sistem & SEO Global berhasil disimpan.']);
                    break;
                }

                throw new Exception("Tab pengaturan '$tab' tidak valid.");
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Type '$type' tidak dikenali."]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
