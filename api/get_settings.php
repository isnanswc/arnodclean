<?php
// api/get_settings.php
header('Content-Type: application/json');
require_once '../db.php';

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('whatsapp_number', 'whatsapp_template')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Defaults
    $response = [
        'whatsapp_number' => $settings['whatsapp_number'] ?? '',
        'whatsapp_template' => $settings['whatsapp_template'] ?? 'Halo, saya mau pesan [nama layanan]'
    ];
    
    echo json_encode(['status' => 'success', 'data' => $response]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB Error']);
}
?>
