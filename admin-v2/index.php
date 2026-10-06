<?php
// admin-v2/index.php
// Forward to Vue SPA HTML entry
if (file_exists(__DIR__ . '/dist/index.html')) {
    readfile(__DIR__ . '/dist/index.html');
    exit;
}
echo "Admin V2 build is not found. Please run npm run build in admin-v2.";
