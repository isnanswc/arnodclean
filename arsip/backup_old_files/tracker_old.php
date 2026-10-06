<?php
// includes/tracker.php
// Must be included AFTER db.php

if (!isset($_SESSION)) session_start();

// Simple check to prevent tracking simple reloads in same session instantaneously
if (!isset($_SESSION)) session_start();

$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$today = date('Y-m-d');
$current_page = $_SERVER['REQUEST_URI'] ?? '/';

// Use array to track visited pages in this session to prevent spamming F5
if (!isset($_SESSION['visited_pages'])) {
    $_SESSION['visited_pages'] = [];
}

// Only proceed if this specific page hasn't been visited in this SESSION (prevent refresh spam)
// AND check DB to ensure it hasn't been logged today for this IP (persistence)
if (!in_array($current_page, $_SESSION['visited_pages'])) {
    
    // Check DB for duplicate (IP + Date + Page)
    $stmt = $pdo->prepare("SELECT id FROM visitor_analytics WHERE ip_address = ? AND visited_at = ? AND page_url = ?");
    $stmt->execute([$ip_address, $today, $current_page]);
    
    if ($stmt->rowCount() == 0) {
        // Fetch Location (Basic free API, sanitized)
        $country = 'Unknown';
        $city = 'Unknown';
        $lat = null;
        $lng = null;
        
        // Timeout set to 1s to prevent site slowdown
        $ctx = stream_context_create(['http'=> ['timeout' => 1]]);

        // Added fields: lat, lon
        $details_json = @file_get_contents("http://ip-api.com/json/{$ip_address}?fields=country,city,lat,lon", false, $ctx);
        
        if ($details_json) {
            $details = json_decode($details_json, true);
            if ($details) {
                $country = $details['country'] ?? 'Unknown';
                $city = $details['city'] ?? 'Unknown';
                $lat = $details['lat'] ?? null;
                $lng = $details['lon'] ?? null;
            }
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
        
        // Page Tracking Logic (Moved up, already defined)
        // $current_page = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Capture Referrer
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
        
        // Optimize Referrer (Store only domain if possible, or full URL)
        // For privacy/cleanliness, we often just want the domain.
        if ($referrer) {
            $parsed_ref = parse_url($referrer);
            if (isset($parsed_ref['host'])) {
                // If it's our own domain, ignore it (Internal traffic)
                if ($parsed_ref['host'] == $_SERVER['HTTP_HOST']) {
                   $referrer = 'Direct/Internal';
                } else {
                   $referrer = $parsed_ref['host']; // Just store 'google.com', 'facebook.com'
                }
            }
        } else {
            $referrer = 'Direct';
        }

        $stmt = $pdo->prepare("INSERT INTO visitor_analytics (ip_address, page_url, referrer, country, city, lat, lng, device, is_bot, visited_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ip_address, $current_page, $referrer, $country, $city, $lat, $lng, $device, $is_bot, $today]);
        
        $_SESSION['visited_pages'][] = $current_page;
        // $_SESSION['visited_today'] = true; // No longer used as global flag
    }
}
?>
