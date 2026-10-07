<?php
// admin-v2/index.php
// Forward to Vue SPA HTML entry with dynamic base path detection
if (file_exists(__DIR__ . '/dist/index.html')) {
    $html = file_get_contents(__DIR__ . '/dist/index.html');
    
    // Detect base prefix (e.g. '/admin-v2/' on domain, or '/arno-dc/admin-v2/' on localhost)
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $baseUri = rtrim(str_replace('\\', '/', $scriptDir), '/') . '/';
    if ($baseUri === '//' || $baseUri === '/' || empty($baseUri)) {
        $baseUri = '/admin-v2/';
    }
    
    if ($baseUri !== '/admin-v2/') {
        $html = str_replace('/admin-v2/', $baseUri, $html);
    }
    
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}
echo "Admin V2 build is not found. Please run npm run build in admin-v2.";
