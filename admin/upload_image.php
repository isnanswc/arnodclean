<?php
// admin/upload_image.php
require_once '../db.php';
require_once 'includes/auth.php';
checkLogin();

require_once 'includes/functions.php';

if (isset($_FILES['file']['name'])) {
    try {
        // Use the secure upload function from functions.php
        // This function handles extension validation (Allowing images only for articles usually)
        // But the original script allowed anything. Let's stick to what uploadAndResize allows (Images + Videos)
        
        $uploadedPath = uploadAndResize(
            $_FILES['file'], 
            '../uploads/articles/content/', 
            '../uploads/articles/content/' // Return path format
        );

        if ($uploadedPath) {
            echo $uploadedPath;
        } else {
            http_response_code(500);
            echo 'Upload failed: Unknown error';
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo 'Error: ' . $e->getMessage();
    }
} else {
    http_response_code(500);
    echo 'No file uploaded or invalid request.';
}
?>
