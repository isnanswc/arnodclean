<?php
// api/track_wa_click.php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

// Safe session start
if (!isset($_SESSION)) {
    session_start();
}

$source = trim($_REQUEST['source'] ?? 'button');
$page_url = trim($_REQUEST['page_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '/'));
$service_id = filter_var($_REQUEST['service_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$lead_id = filter_var($_REQUEST['lead_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$custom_text = trim($_REQUEST['custom_text'] ?? '');

$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Detect Device Type
$device = 'Desktop';
if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent)) {
    $device = 'Mobile';
}

// Referrer
$referrer = $_SERVER['HTTP_REFERER'] ?? null;
if ($referrer) {
    $parsed_ref = parse_url($referrer);
    if (isset($parsed_ref['host'])) {
        if ($parsed_ref['host'] == ($_SERVER['HTTP_HOST'] ?? '')) {
            $referrer = 'Direct/Internal';
        } else {
            $referrer = $parsed_ref['host'];
        }
    }
} else {
    $referrer = 'Direct';
}

// Basic Location (ip-api)
$country = 'Unknown';
$city = 'Unknown';

$is_private_ip = filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
if (!$is_private_ip) {
    $ctx = stream_context_create(['http' => ['timeout' => 1]]);
    $details_json = @file_get_contents("http://ip-api.com/json/{$ip_address}?fields=country,city", false, $ctx);
    if ($details_json) {
        $details = json_decode($details_json, true);
        if ($details && ($details['status'] ?? '') !== 'fail') {
            $country = $details['country'] ?? 'Unknown';
            $city = $details['city'] ?? 'Unknown';
        }
    }
} else {
    $country = 'Local';
    $city = 'Localhost';
}

try {
    // 1. Insert into wa_clicks
    $stmt = $pdo->prepare("INSERT INTO wa_clicks (lead_id, service_id, source, page_url, referrer, ip_address, device, country, city, clicked_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$lead_id, $service_id, $source, $page_url, $referrer, $ip_address, $device, $country, $city]);

    // 2. If service_id set, increment order_count & service_clicks
    if ($service_id) {
        try {
            $pdo->prepare("UPDATE services SET order_count = order_count + 1 WHERE id = ?")->execute([$service_id]);
            $pdo->prepare("INSERT INTO service_clicks (service_id, ip_address) VALUES (?, ?)")->execute([$service_id, $ip_address]);
        } catch (Exception $e) {
            // Ignore if service table differs
        }
    }

    // 3. Resolve Target WhatsApp Number & Template
    $waNumber = '6281280666659';
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

    // Dynamic Default Text if none provided
    if (empty($custom_text)) {
        try {
            $stmtSet = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'wa_template'");
            $t = $stmtSet->fetchColumn();
            if ($t) {
                $custom_text = $t;
            }
        } catch (Exception $e) {}
    }

    if (empty($custom_text)) {
        $custom_text = "Halo Admin Arno D Clean, saya mau konsultasi & pesan layanan.";
    }

    $wa_url = "https://wa.me/" . $waNumber . "?text=" . urlencode($custom_text);

    echo json_encode([
        'status' => 'success',
        'message' => 'WhatsApp click tracked',
        'wa_url' => $wa_url
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
