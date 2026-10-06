<?php
require_once 'db.php';
// Simulate a lead submission
$_POST = [
    'name' => 'Antigravity Test',
    'email' => 'test@example.com',
    'whatsapp' => '628123456789',
    'service_id' => 1,
    'message' => 'Halo, ini adalah pesan uji coba untuk sistem Lead Management!'
];
ob_start();
include 'api/submit_lead.php';
$res = ob_get_clean();
echo "API Result: " . $res . "\n";

// Check DB
$stmt = $pdo->query("SELECT * FROM leads ORDER BY id DESC LIMIT 1");
$lead = $stmt->fetch(PDO::FETCH_ASSOC);
if ($lead && $lead['name'] == 'Antigravity Test') {
    echo "Verification SUCCESS: Lead saved in DB.\n";
} else {
    echo "Verification FAILED: Lead not found in DB.\n";
}
unlink(__FILE__);
