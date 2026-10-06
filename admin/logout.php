<?php
require_once '../db.php';
require_once 'includes/auth.php';
// session_start(); // Handled in auth.php
logActivity("Logout", "User keluar dari sistem");
session_destroy();
header("Location: login.php");
exit;
