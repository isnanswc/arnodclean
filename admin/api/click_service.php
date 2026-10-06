<?php
// admin/api/click_service.php
header('Content-Type: application/json');
require_once '../../db.php';

if (isset($_POST['id'])) {
    $id = $_POST['id'];
    
    try {
        // 1. Increment Order Count
        $stmt = $pdo->prepare("UPDATE services SET order_count = order_count + 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        // 2. Get Service Name for Template
        $stmt = $pdo->prepare("SELECT title FROM services WHERE id = ?");
        $stmt->execute([$id]);
        $service = $stmt->fetch();
        $serviceName = $service ? $service['title'] : 'Layanan';

        // 3. Get Site Settings (WA Template & Number)
        $settings_db = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR); // Use site_settings table as seen in index.php
        
        // Fallback or DB specific
        // Note: verify table name. index.php uses 'site_settings' (key/value/label), settings.php uses 'settings' (flat?).
        // Let's re-check index.php Step 12. It uses `site_settings`.
        // Let's re-check settings.php Step 22. It uses `settings`.
        // Wait, there might be a mismatch or duplicate table or I misread.
        // Step 12 index.php: `SELECT * FROM site_settings` (id, setting_key, setting_value, label)
        // Step 22 settings.php: `SELECT setting_key, setting_value FROM settings`
        // ERROR DETECTED: The settings page might be saving to a different table than index.php reads?
        // Let's check update_schema.sql Step 13. It creates `site_settings`. 
        // It seems settings.php Step 22 was writing to `settings` but index.php reads `site_settings`.
        // I need to fix settings.php too! Or ensure I read from the correct one.
        // Given `update_schema.sql` defines `site_settings`, I should probably use `site_settings`.
        
        // Let's try to read from `site_settings` first.
        $wa_template = $settings_db['wa_template'] ?? 'Halo, saya mau pesan [nama layanan]';
        
        // The phone number might be in `contact_info` table or `site_settings`?
        // Index.php Step 12: `SELECT * FROM contact_info`. $contact['whatsapp'].
        $contacts_db = $pdo->query("SELECT * FROM contact_info")->fetchAll();
        $contact = [];
        foreach ($contacts_db as $c) {
            $contact[$c['contact_key']] = $c['contact_value'];
        }
        $phone = $contact['whatsapp'] ?? '6281280666659';

        // Format Message
        $message = str_replace('[nama layanan]', $serviceName, $wa_template);
        
        // Build URL
        $url = "https://wa.me/" . cleanupNumber($phone) . "?text=" . urlencode($message);
        
        echo json_encode(['status' => 'success', 'url' => $url]);
        
    } catch (Exception $e) {
        // If error, just return a fallback generic link
        echo json_encode(['status' => 'error', 'url' => "https://wa.me/?text=Halo"]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No ID provided']);
}

function cleanupNumber($number) {
    $number = preg_replace('/[^0-9]/', '', $number);
    if (substr($number, 0, 1) == '0') {
        $number = '62' . substr($number, 1);
    }
    return $number;
}
?>
