<?php
// tests/system_check.php

$baseUrl = "http://localhost:8000";
$results = [];

function testUrl($name, $url, $expectedCode = 200, $contentCheck = null) {
    global $results;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $pass = ($httpCode == $expectedCode);
    if ($pass && $contentCheck) {
        $pass = (strpos($response, $contentCheck) !== false);
    }

    $results[] = [
        'name' => $name,
        'url' => $url,
        'status' => $httpCode,
        'pass' => $pass,
        'detail' => $pass ? "OK" : "Failed. Expected $expectedCode, got $httpCode. Content check: " . ($contentCheck ? "Missing '$contentCheck'" : "N/A")
    ];
}

echo "Starting System Verification...\n";
echo "Base URL: $baseUrl\n\n";

// 1. Public Pages
testUrl("Homepage", "$baseUrl/index.php", 200, "Arno D Clean");
testUrl("Blog Listing", "$baseUrl/blog.php", 200, "Artikel Terbaru");
testUrl("Sitemap XML", "$baseUrl/sitemap.php", 200, "urlset");

// 2. Friendly URL Test (Using a likely non-existent slug to check rewriting works)
// If rewrite works, it hits article.php and says "Artikel tidak ditemukan" (which is 200 OK usually)
// If rewrite fails, it might give 404 from Apache (Not Found)
testUrl("Friendly URL (Rewrite)", "$baseUrl/blog/test-rewrite-slug", 200, "Artikel tidak ditemukan");

// 3. Security Checks
testUrl("Uploads Dir (Shell Block)", "$baseUrl/uploads/test.php", 403); // Should be rewritten or forbidden
testUrl("Sensitive File (Config)", "$baseUrl/config.php", 403); // Blocked in root .htaccess

// 4. Admin Pages (Login Redirect)
testUrl("Admin Dashboard (Redirect)", "$baseUrl/admin/dashboard.php", 200, "Login Admin"); // Redirects to login usually, or shows login page

// Output Results
$allPass = true;
foreach ($results as $r) {
    $statusIcon = $r['pass'] ? "✅" : "❌";
    echo "[$statusIcon] {$r['name']}\n";
    echo "    URL: {$r['url']}\n";
    if (!$r['pass']) {
        echo "    ERROR: {$r['detail']}\n";
        $allPass = false;
    }
}

echo "\n------------------------------------------------\n";
if ($allPass) {
    echo "ALL TESTS PASSED. System is stable.\n";
} else {
    echo "SOME TESTS FAILED. Please review issues.\n";
}
?>
