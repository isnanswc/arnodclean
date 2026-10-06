<?php
// admin/includes/csrf.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF Token and store in session
 * @return string The token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 * @param string $token Token from form
 * @return bool True if valid
 */
function verifyCsrfToken($token = null) {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    // If token not passed, check Header
    if (empty($token)) {
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        $token = $headers['X-CSRF-Token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    }
    
    if (empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}
?>
