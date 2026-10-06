<?php
// Keamanan: Hanya izinkan akses dari localhost
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($clientIp, ['127.0.0.1', '::1']) || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);
if (!$isLocal) {
    http_response_code(403);
    die("<h1>403 Forbidden</h1><p>Akses ditolak.</p>");
}

require_once 'db.php';

echo "<h2>Checking AI Keys in Database...</h2>";

$stmt = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings WHERE setting_key LIKE '%key%' OR setting_key LIKE '%token%'");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
    echo "No settings found with 'key' or 'token' in the name.<br>";
} else {
    echo "<table border='1' cellpadding='5'><tr><th>Key</th><th>Value (Truncated)</th></tr>";
    foreach ($results as $row) {
        $val = $row['setting_value'];
        $displayVal = strlen($val) > 10 ? substr($val, 0, 10) . '...' : $val; // Hide full key for security
        echo "<tr><td>{$row['setting_key']}</td><td>{$displayVal}</td>}</tr>";
    }
    echo "</table>";
}

// Also check specifically for the old single key
$oldKey = $pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key = 'ai_api_key'")->fetchColumn();
echo "<br><b>Legacy 'ai_api_key':</b> " . ($oldKey ? "FOUND (" . substr($oldKey, 0, 5) . "...)" : "Not Found/Empty");

?>
