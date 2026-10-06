<?php
// api/track_order.php
header('Content-Type: application/json');
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    
    if ($id && is_numeric($id)) {
        try {
            // 1. Detect Bot
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $is_bot = 0;
            $bot_signatures = [
                'bot', 'crawl', 'spider', 'slurp', 'google', 'bing', 'msn', 'yandex', 'baidu', 
                'ahrefs', 'semrush', 'dotbot', 'exabot', 'screaming', 'facebook', 'twitter', 
                'linkedin', 'telegram', 'whatsapp', 'petal', 'pinterest', 'duckduckgo'
            ];
            
            $ua_lower = strtolower($user_agent);
            foreach ($bot_signatures as $sig) {
                if (strpos($ua_lower, $sig) !== false) {
                    $is_bot = 1;
                    break;
                }
            }

            // 2. Insert into service_clicks (Detailed Log)
            $stmt = $pdo->prepare("INSERT INTO service_clicks (service_id, ip_address, is_bot, clicked_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([
                $id, 
                $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 
                $is_bot
            ]);

            // 3. Increment counter ONLY if Human (Generic)
            if ($is_bot === 0) {
                $stmt = $pdo->prepare("UPDATE services SET order_count = order_count + 1 WHERE id = ?");
                $stmt->execute([$id]);
            }

            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            error_log($e->getMessage()); // Log internally
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database Error']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
}
?>
