<?php
// admin/api/go.php
require_once '../../db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        // 1. Increment Order Count
        $stmt = $pdo->prepare("UPDATE services SET order_count = order_count + 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        // 1b. Log Detailed Click
        $stmt = $pdo->prepare("INSERT INTO service_clicks (service_id, ip_address) VALUES (?, ?)");
        $stmt->execute([$id, $_SERVER['REMOTE_ADDR']]);

        // 2. Get Service Name
        $stmt = $pdo->prepare("SELECT title FROM services WHERE id = ?");
        $stmt->execute([$id]);
        $service = $stmt->fetch();
        $serviceName = $service ? $service['title'] : 'Layanan';

        // 3. Get Settings
        // Contact (Phone)
        $stmt = $pdo->prepare("SELECT contact_value FROM contact_info WHERE contact_key = 'whatsapp'");
        $stmt->execute();
        $phone = $stmt->fetchColumn() ?: '6281280666659';

        // Template
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'wa_template'");
        $stmt->execute();
        // Fallback to Order template, containing placeholder
        $wa_template = $stmt->fetchColumn() ?: 'Halo, saya mau pesan [nama layanan]';

        // Format Message
        $message = str_replace('[nama layanan]', $serviceName, $wa_template);
        
        // Build URL
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) == '0') $phone = '62' . substr($phone, 1);
        
        $url = "https://wa.me/" . $phone . "?text=" . urlencode($message);
        
        // Redirect
        header("Location: " . $url);
        exit;
        
    } catch (Exception $e) {
        // Fallback
        header("Location: https://wa.me/?text=Halo");
        exit;
    }
} else {
    header("Location: ../../index.php");
    exit;
}
?>
