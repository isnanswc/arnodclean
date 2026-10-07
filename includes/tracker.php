<?php
// includes/tracker.php
// Must be included AFTER db.php

if (!isset($_SESSION)) session_start();

// Extract real client IP (Cloudflare / Reverse Proxy / Direct)
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip_headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP'];
foreach ($ip_headers as $hdr) {
    if (!empty($_SERVER[$hdr])) {
        $parts = explode(',', $_SERVER[$hdr]);
        $test_ip = trim($parts[0]);
        if (filter_var($test_ip, FILTER_VALIDATE_IP)) {
            $client_ip = $test_ip;
            break;
        }
    }
}
$ip_address = $client_ip;
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$today = date('Y-m-d');
$current_page = $_SERVER['REQUEST_URI'] ?? '/';

// Use array to track visited pages in this session to prevent spamming F5
if (!isset($_SESSION['visited_pages'])) {
    $_SESSION['visited_pages'] = [];
}

// Only proceed if this specific page hasn't been visited in this SESSION (prevent refresh spam)
// AND check DB to ensure it hasn't been logged today for this IP (persistence)
if (!in_array($current_page, $_SESSION['visited_pages'])) {
    
    try {
        // Check DB for duplicate (IP + Date + Page)
        $stmt = $pdo->prepare("SELECT id FROM visitor_analytics WHERE ip_address = ? AND visited_at = ? AND page_url = ?");
        $stmt->execute([$ip_address, $today, $current_page]);
        
        if ($stmt->rowCount() == 0) {
            // Fetch Location (Sanitized)
            $country = 'Unknown';
            $city = 'Unknown';
            $lat = null;
            $lng = null;
            
            // Skip external ip-api call for private or localhost IPs
            $is_private_ip = filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
            
            if (!$is_private_ip) {
                $details_json = null;
                $geo_url = "http://ip-api.com/json/{$ip_address}?fields=country,city,lat,lon";
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $geo_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'ArnoDC-Tracker/1.0');
                    $details_json = curl_exec($ch);
                    curl_close($ch);
                } else {
                    $ctx = stream_context_create(['http'=> ['timeout' => 2]]);
                    $details_json = @file_get_contents($geo_url, false, $ctx);
                }
                
                if ($details_json) {
                    $details = json_decode($details_json, true);
                    if ($details && ($details['status'] ?? '') !== 'fail') {
                        $country = $details['country'] ?? 'Unknown';
                        $city = $details['city'] ?? 'Unknown';
                        $lat = $details['lat'] ?? null;
                        $lng = $details['lon'] ?? null;
                    }
                }
            } else {
                $country = 'Local';
                $city = 'Localhost';
            }
            
            // Detect Device Type (Simple)
            $device = 'Desktop';
            if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent)) {
                $device = 'Mobile';
            }

            // --- Bot Detection ---
            $is_bot = 0;
            $bot_signatures = [
                'bot', 'crawl', 'spider', 'slurp', 'google', 'bing', 'msn', 'yandex', 'baidu', 'ahrefs', 
                'semrush', 'dotbot', 'exabot', 'screaming', 'facebook', 'twitter', 'linkedin', 'telegram', 
                'whatsapp', 'petal', 'pinterest', 'duckduckgo'
            ];
            $ua_lower = strtolower($user_agent);
            foreach ($bot_signatures as $sig) {
                if (strpos($ua_lower, $sig) !== false) {
                    $is_bot = 1;
                    break;
                }
            }
            
            // Capture Referrer
            $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
            
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

            $stmt = $pdo->prepare("INSERT INTO visitor_analytics (ip_address, page_url, referrer, country, city, lat, lng, device, is_bot, visited_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$ip_address, $current_page, $referrer, $country, $city, $lat, $lng, $device, $is_bot, $today]);
            
            $_SESSION['visited_pages'][] = $current_page;
        }
    } catch (Exception $e) {
        // Suppress analytics error so visitors never see broken pages
        error_log("Tracker error: " . $e->getMessage());
    }
}
?>
