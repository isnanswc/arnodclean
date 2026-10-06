<?php
// test_geo_simulation.php
require_once 'db.php';

// Simulate a Public IP (Google DNS)
$ip_address = '8.8.8.8'; 
echo "Simulating visit from Public IP: $ip_address\n";

// Logic copied/adapted from tracker.php
$ctx = stream_context_create(['http'=> ['timeout' => 5]]); // More generous timeout for CLI
$url = "http://ip-api.com/json/{$ip_address}?fields=country,city,lat,lon";

echo "Requesting: $url\n";
$details_json = @file_get_contents($url, false, $ctx);

if ($details_json) {
    echo "Response received: " . $details_json . "\n";
    $details = json_decode($details_json, true);
    
    $lat = $details['lat'] ?? null;
    $lng = $details['lon'] ?? null;
    $city = $details['city'] ?? 'Unknown';
    
    echo "\nParsed Data:\n";
    echo "City: $city\n";
    echo "Lat: $lat\n";
    echo "Lng: $lng\n";
    
    if ($lat && $lng) {
        echo "\n[SUCCESS] Coordinates fetched successfully!\n";
    } else {
        echo "\n[FAIL] Coordinates missing in response.\n";
    }

} else {
    echo "\n[ERROR] Failed to contact IP-API. Check internet connection.\n";
}
?>
