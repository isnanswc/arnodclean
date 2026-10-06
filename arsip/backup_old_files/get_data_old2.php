<?php
// admin/api/get_data.php
header('Content-Type: application/json');
require_once '../../db.php';
require_once '../includes/auth.php';

// Ensure user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $type = $_GET['type'] ?? '';
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 10;
    $search = trim($_GET['search'] ?? '');
    
    if ($limit > 100) $limit = 100; // Hard limit header
    $offset = ($page - 1) * $limit;

    // Allowed tables/types
    $allowed_types = ['services', 'benefits', 'testimonials', 'articles', 'tags', 'locations', 'auto_content_keywords', 'activity_logs', 'leads'];
    if (!in_array($type, $allowed_types)) {
        throw new Exception("Invalid type requested.");
    }

    $params = [];
    $whereSQL = "1=1";

    // Filter Logic for Auto Content
    if ($type === 'auto_content_keywords') {
        if (isset($_GET['status']) && in_array($_GET['status'], ['pending', 'processing', 'done', 'failed'])) {
            $whereSQL .= " AND k.status = ?";
            $params[] = $_GET['status'];
        }
    }

    // Search Logic (Generic based on columns)
    if (!empty($search)) {
        $searchParam = "%$search%";
        switch ($type) {
            case 'auto_content_keywords':
                // Join to get slug for preview
                $whereSQL .= " AND (k.keyword LIKE ?)";
                $params[] = $searchParam;
                break;
            case 'services':
            case 'benefits':
                $whereSQL .= " AND (title LIKE ? OR description LIKE ?)";
                $params[] = $searchParam;
                $params[] = $searchParam;
                break;
            case 'testimonials':
                $whereSQL .= " AND (name LIKE ? OR content LIKE ?)";
                $params[] = $searchParam;
                $params[] = $searchParam;
                break;
            case 'articles':
                $whereSQL .= " AND (title LIKE ? OR content LIKE ?)";
                $params[] = $searchParam;
                $params[] = $searchParam;
                break;
            case 'tags':
                $whereSQL .= " AND (name LIKE ?)";
                $params[] = $searchParam;
                break;
            case 'locations':
                $whereSQL .= " AND (name LIKE ? OR address LIKE ?)";
                $params[] = $searchParam;
                $params[] = $searchParam;
                break;
            case 'leads':
                $whereSQL .= " AND (name LIKE ? OR email LIKE ? OR whatsapp LIKE ?)";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                break;
            case 'activity_logs':
                // Search handled separately for UNION query
                break;
        }
    }

    // AI Filter Logic for Leads
    if ($type === 'leads' && isset($_GET['ai_filter'])) {
        $aiFilter = $_GET['ai_filter'];
        if ($aiFilter === 'genuine') {
            $whereSQL .= " AND ai_status = 'genuine'";
        } elseif ($aiFilter === 'spam') {
            $whereSQL .= " AND ai_status = 'spam'";
        }
    }

    // Count Total (Handle Join Alias for auto_content_keywords)
    if ($type === 'auto_content_keywords') {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM auto_content_keywords k WHERE $whereSQL");
    } else {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM $type WHERE $whereSQL");
    }
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();

    // Fetch Data
    // Special sort for Benefits (usually ASC), others DESC
    $orderBy = ($type === 'benefits' || $type === 'tags') ? 'ASC' : 'DESC';
    $orderCol = ($type === 'tags') ? 'name' : 'created_at';
    
    // Locations might not have created_at? Let's check schema. 
    // Usually they do, but if not we fallback to ID.
    // For safety, let's use ID DESC for everything except Benefits/Tags if created_at is missing?
    // Actually, let's stick to standard `ORDER BY id DESC` if column unknown, but our context implies created_at is common.
    // Let's safe-check or just assume created_at exists for main tables. Tags/Location might vary.
    // Refined Order Logic:
    if ($type === 'locations') $orderCol = 'id'; // Locations might not need date sort
    
    if ($type === 'auto_content_keywords') {
         // Custom header for join alias
         // Custom order for Pending
         $orderSQL = "ORDER BY k.created_at DESC";
         if (isset($_GET['status']) && $_GET['status'] === 'pending') {
             $orderSQL = "ORDER BY k.process_order ASC, k.id ASC";
         }

         $sql = "SELECT k.*, a.slug as article_slug 
                 FROM auto_content_keywords k 
                 LEFT JOIN articles a ON k.article_id = a.id 
                 WHERE $whereSQL 
                 $orderSQL 
                 LIMIT ? OFFSET ?";
    } else {
         $sql = "SELECT * FROM $type WHERE $whereSQL ORDER BY $orderCol $orderBy LIMIT ? OFFSET ?";
    }
    
    // Custom Query for Articles to get Tags and Detailed Analytics
    if ($type === 'articles') {
        // Subqueries for Human and Bot views based on URL pattern "slug=..."
        $sql = "SELECT *, 
                       (SELECT GROUP_CONCAT(tag_id) FROM article_tags WHERE article_id = articles.id) as tag_ids,
                       (SELECT COUNT(*) FROM visitor_analytics WHERE page_url LIKE CONCAT('%slug=', articles.slug, '%') AND is_bot = 0) as human_views,
                       (SELECT COUNT(*) FROM visitor_analytics WHERE page_url LIKE CONCAT('%slug=', articles.slug, '%') AND is_bot = 1) as bot_views,
                       LENGTH(content) - LENGTH(REPLACE(content, '<a href', '')) AS raw_link_matches -- Approximation, handled better in PHP loop if needed, but SQL-only is hard for exact count.
                FROM $type WHERE $whereSQL ORDER BY $orderCol $orderBy LIMIT ? OFFSET ?";
    }
    
    // Custom Query for Activity Logs (UNION of activity_logs and login_history)
    if ($type === 'activity_logs') {
        $searchFilter = "";
        $searchParams = [];
        if (!empty($search)) {
            $searchFilter = " AND (action LIKE ? OR details LIKE ? OR username LIKE ?)";
            $searchParams = ["%$search%", "%$search%", "%$search%"];
        }
        
        // Count query
        $countSQL = "
            SELECT COUNT(*) as total FROM (
                (SELECT a.id FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id WHERE 1=1 $searchFilter)
                UNION ALL
                (SELECT l.id FROM login_history l LEFT JOIN users u ON l.user_id = u.id WHERE 1=1 $searchFilter)
            ) AS combined
        ";
        $countStmt = $pdo->prepare($countSQL);
        $idx = 1;
        foreach ($searchParams as $p) $countStmt->bindValue($idx++, $p);
        foreach ($searchParams as $p) $countStmt->bindValue($idx++, $p);
        $countStmt->execute();
        $totalRecords = $countStmt->fetchColumn();
        
        // Data query
        $sql = "
            (SELECT 'activity' as log_type, a.id, a.user_id, a.action, a.details, a.ip_address, a.created_at, u.username, u.avatar 
            FROM activity_logs a 
            LEFT JOIN users u ON a.user_id = u.id
            WHERE 1=1 $searchFilter)
            UNION ALL 
            (SELECT 'login' as log_type, l.id, l.user_id, 'Login' as action, CONCAT('IP: ', l.ip_address) as details, l.ip_address, l.login_at as created_at, u.username, u.avatar
            FROM login_history l 
            LEFT JOIN users u ON l.user_id = u.id
            WHERE 1=1 $searchFilter)
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $idx = 1;
        foreach ($searchParams as $p) $stmt->bindValue($idx++, $p);
        foreach ($searchParams as $p) $stmt->bindValue($idx++, $p);
        $stmt->bindValue($idx++, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue($idx++, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll();
        
        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'limit' => $limit,
                'total_records' => $totalRecords,
                'total_pages' => ceil($totalRecords / $limit)
            ]
        ]);
        exit;
    }
    
    $stmt = $pdo->prepare($sql);
    $idx = 1;
    foreach ($params as $param) $stmt->bindValue($idx++, $param);
    $stmt->bindValue($idx++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($idx++, (int)$offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $data = $stmt->fetchAll();

    // Post-process for Articles to get accurate link count
    if ($type === 'articles') {
        foreach ($data as &$item) {
            // Count <a href matches case-insensitive
            $item['internal_link_count'] = substr_count(strtolower($item['content']), '<a href');
            // Remove raw helper if present (optional)
            unset($item['raw_link_matches']);
        }
    }

    // Extra Data: Status Counts for Auto Content
    $extras = [];
    if ($type === 'auto_content_keywords') {
        $statsStmt = $pdo->query("SELECT status, COUNT(*) as count FROM auto_content_keywords GROUP BY status");
        $stats = $statsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $extras['counts'] = [
            'all' => array_sum($stats),
            'pending' => $stats['pending'] ?? 0,
            'processing' => $stats['processing'] ?? 0,
            'done' => $stats['done'] ?? 0,
            'failed' => $stats['failed'] ?? 0
        ];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'pagination' => [
            'current_page' => $page,
            'limit' => $limit,
            'total_records' => $totalRecords,
            'total_pages' => ceil($totalRecords / $limit)
        ],
        'extras' => $extras
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
