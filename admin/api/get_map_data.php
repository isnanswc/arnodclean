<?php
// admin/api/get_map_data.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../includes/auth.php';

// Check auth
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $range = $_GET['range'] ?? '7days';
    $filter = $_GET['filter'] ?? 'all'; // all, human, bot

    // Time condition
    $params = [];
    $where = "lat IS NOT NULL AND lng IS NOT NULL";

    switch ($range) {
        case 'today':
            $where .= " AND visited_at = ?";
            $params[] = date('Y-m-d');
            break;
        case '30days':
            $where .= " AND visited_at >= DATE(NOW()) - INTERVAL 30 DAY";
            break;
        case 'month':
            $where .= " AND visited_at LIKE ?";
            $params[] = date('Y-m') . '%';
            break;
        case '7days':
        default:
            $where .= " AND visited_at >= DATE(NOW()) - INTERVAL 7 DAY";
            break;
    }

    // Bot Filter
    if ($filter == 'human') {
        $where .= " AND is_bot = 0";
    } elseif ($filter == 'bot') {
        $where .= " AND is_bot = 1";
    }

    // Limit points to prevent map overload (cluster plugin handles thousands, but let's be safe)
    $sql = "SELECT id, lat, lng, city, country, is_bot, device, visited_at 
            FROM visitor_analytics 
            WHERE $where 
            ORDER BY visited_at DESC 
            LIMIT 2000";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
