<?php
// api/submit_lead.php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

// Safe include sendTelegram
if (file_exists(__DIR__ . '/../admin/includes/auth.php')) {
    require_once __DIR__ . '/../admin/includes/auth.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$whatsapp = trim($_POST['whatsapp'] ?? '');
$service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT) ?: null;
$message = trim($_POST['message'] ?? '');
$honeypot = $_POST['honeypot'] ?? '';
$page_url = trim($_POST['page_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '/'));
$referrer = trim($_POST['referrer'] ?? '');

// 1. Honeypot check (Anti-Bot)
if (!empty($honeypot)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bot activity detected.']);
    exit;
}

// 2. Client Metadata
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Rate Limiting
try {
    $stmtLimit = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmtLimit->execute([$ip]);
    if ($stmtLimit->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']);
        exit;
    }
} catch (Exception $e) {}

if (empty($name) || (empty($whatsapp) && empty($email))) {
    echo json_encode(['status' => 'error', 'message' => 'Nama dan Kontak (WA/Email) wajib diisi.']);
    exit;
}

// Device detection
$device = 'Desktop';
if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent)) {
    $device = 'Mobile';
}

if (empty($referrer)) {
    $rawRef = $_SERVER['HTTP_REFERER'] ?? null;
    if ($rawRef) {
        $parsedRef = parse_url($rawRef);
        $referrer = isset($parsedRef['host']) ? ($parsedRef['host'] === ($_SERVER['HTTP_HOST'] ?? '') ? 'Direct/Internal' : $parsedRef['host']) : 'Direct';
    } else {
        $referrer = 'Direct';
    }
}

// Location lookup
$country = 'Unknown';
$city = 'Unknown';
$ctx = stream_context_create(['http' => ['timeout' => 1]]);
$details_json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,city", false, $ctx);
if ($details_json) {
    $details = json_decode($details_json, true);
    if ($details) {
        $country = $details['country'] ?? 'Unknown';
        $city = $details['city'] ?? 'Unknown';
    }
}

try {
    // 3. Save Lead into database (CRM)
    $stmt = $pdo->prepare("INSERT INTO leads (name, email, whatsapp, service_id, message, ip_address, referrer, page_url, device, country, city, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())");
    $stmt->execute([$name, $email, $whatsapp, $service_id, $message, $ip, $referrer, $page_url, $device, $country, $city]);
    $leadId = $pdo->lastInsertId();

    // Fetch Service Name
    $serviceName = "Umum / Tanya Layanan";
    if ($service_id) {
        $stmtS = $pdo->prepare("SELECT title FROM services WHERE id = ?");
        $stmtS->execute([$service_id]);
        $sTitle = $stmtS->fetchColumn();
        if ($sTitle) $serviceName = $sTitle;
    }

    // 4. Track WA Click Event automatically for this Lead Form
    try {
        $stmtWa = $pdo->prepare("INSERT INTO wa_clicks (lead_id, service_id, source, page_url, referrer, ip_address, device, country, city, clicked_at) VALUES (?, ?, 'form_crm', ?, ?, ?, ?, ?, ?, NOW())");
        $stmtWa->execute([$leadId, $service_id, $page_url, $referrer, $ip, $device, $country, $city]);

        if ($service_id) {
            $pdo->prepare("UPDATE services SET order_count = order_count + 1 WHERE id = ?")->execute([$service_id]);
        }
    } catch (Exception $e) {}

    // 5. Generate Format Pesan WhatsApp
    $waNumber = '6281280666659'; // Default
    try {
        $stmtContact = $pdo->query("SELECT contact_value FROM contact_info WHERE contact_key = 'whatsapp'");
        $val = $stmtContact->fetchColumn();
        if ($val) {
            $waNumber = preg_replace('/[^0-9]/', '', $val);
            if (substr($waNumber, 0, 1) === '0') {
                $waNumber = '62' . substr($waNumber, 1);
            }
        }
    } catch (Exception $e) {}

    $waMessageText = "Halo Admin Arno D Clean,\n\nSaya baru saja mengisi form di website. Berikut detail pemesanan saya:\n\n"
        . "👤 *Nama:* " . $name . "\n"
        . ($whatsapp ? "📱 *WhatsApp:* " . $whatsapp . "\n" : "")
        . ($email ? "✉️ *Email:* " . $email . "\n" : "")
        . "🛠️ *Layanan:* " . $serviceName . "\n"
        . ($message ? "📝 *Pesan/Alamat:* " . $message . "\n" : "")
        . "\nMohon konfirmasi pesanan saya. Terima kasih!";

    $waUrl = "https://wa.me/" . $waNumber . "?text=" . urlencode($waMessageText);

    // 6. Telegram Notification (If enabled)
    try {
        $stmtSet = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings WHERE setting_key IN ('tg_bot_token', 'tg_chat_id')");
        $settings = $stmtSet->fetchAll(PDO::FETCH_KEY_PAIR);
        $botToken = $settings['tg_bot_token'] ?? '';
        $chatId = $settings['tg_chat_id'] ?? '';
        
        if (!empty($botToken) && !empty($chatId) && function_exists('sendTelegram')) {
            $tgMsg = "🔔 <b>PESAN BARU (LEAD CRM)</b>\n\n";
            $tgMsg .= "👤 <b>Nama:</b> " . htmlspecialchars($name) . "\n";
            if ($whatsapp) $tgMsg .= "📱 <b>WhatsApp:</b> " . htmlspecialchars($whatsapp) . "\n";
            if ($email) $tgMsg .= "✉️ <b>Email:</b> " . htmlspecialchars($email) . "\n";
            $tgMsg .= "🛠️ <b>Layanan:</b> " . htmlspecialchars($serviceName) . "\n";
            $tgMsg .= "📝 <b>Pesan:</b>\n<i>" . htmlspecialchars($message) . "</i>\n\n";
            $tgMsg .= "📍 <b>Cek di Panel Admin:</b>\n" . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/admin/leads.php";

            sendTelegram($chatId, $tgMsg, $botToken);
        }
    } catch (Exception $e) {}

    echo json_encode([
        'status' => 'success',
        'message' => 'Terima kasih! Data Anda tersimpan dan Anda langsung dihubungkan ke WhatsApp.',
        'lead_id' => $leadId,
        'wa_url' => $waUrl
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengirim pesan: ' . $e->getMessage()]);
}
