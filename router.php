<?php
// router.php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Check if file exists (serve directly)
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Simulate RewriteRule ^blog/([a-zA-Z0-9-]+)$ article.php?slug=$1
if (preg_match('#^/blog/([a-zA-Z0-9-]+)$#', $uri, $matches)) {
    $_GET['slug'] = $matches[1];
    include __DIR__ . '/article.php';
    return;
}

// Default index? Apache usually serves index.php for /
if ($uri === '/' || $uri === '/index.php') {
    include __DIR__ . '/index.php';
    return;
}

// If no match
http_response_code(404);
echo "404 Not Found (Router)";
?>
