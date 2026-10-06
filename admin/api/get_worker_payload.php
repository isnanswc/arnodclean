<?php
// admin/api/get_worker_payload.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../includes/auth.php';
checkLogin();

try {
    // 1. Fetch AI Settings
    $settings_db = $pdo->query("SELECT * FROM auto_content_settings")->fetchAll();
    $settings = [];
    foreach ($settings_db as $s) {
        $settings[$s['setting_key']] = $s['setting_value'];
    }

    // 2. Fetch one pending keyword
    $kw = $pdo->query("SELECT * FROM auto_content_keywords WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // 3. Fetch tags for context
    $tags = $pdo->query("SELECT name FROM tags")->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'status' => 'success',
        'settings' => $settings,
        'keyword' => $kw,
        'available_tags' => $tags
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
