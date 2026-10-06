<?php
// admin/api/cron_classify_leads.php

// Define IS_CRON if run from CLI
date_default_timezone_set('Asia/Jakarta');

if (php_sapi_name() === 'cli') {
    if (!defined('IS_CRON')) define('IS_CRON', true);
}

// Logic to check if triggered via HTTP by Admin (e.g., "Scan Now" button)
// We will require a valid session if not CLI
// Connect to DB
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../includes/csrf.php';

// Helper to log steps
function cliLog($msg) {
    echo "[" . date('H:i:s') . "] $msg\n";
}

// 1. Check Authentication (if not CLI)
if (isset($_GET['key']) && $_GET['key'] === 'adc_cron_secure') {
    if (!defined('IS_CRON')) define('IS_CRON', true);
}

if (!defined('IS_CRON')) {
    // Session started by csrf.php or manually
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Fix: Use 'admin_logged_in' or 'admin_id' as per auth.php
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
        exit;
    }
    
    // CSRF Check
    if (!verifyCsrfToken()) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF Token']);
        exit;
    }

    header('Content-Type: application/json');
}

try {
    // 0. File Locking (Prevent Race Conditions)
    $lockFile = __DIR__ . '/classify_leads.lock';
    $fp = fopen($lockFile, 'w+');
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        if (defined('IS_CRON')) cliLog("Process locked. Skipping.");
        else echo json_encode(['status' => 'error', 'message' => 'Process locked by another instance.']);
        exit;
    }

    // 2. Load API Settings using simple query
    $settings = [];
    $stmt = $pdo->query("SELECT * FROM auto_content_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // AI Config Extraction
    $apiKey = '';
    $geminiKeys = json_decode($settings['ai_config_gemini_keys'] ?? '[]', true);
    if (!empty($geminiKeys) && is_array($geminiKeys)) {
        $apiKey = $geminiKeys[0]; // Use first key
    }
    
    if (empty($apiKey)) {
        $apiKey = $settings['ai_api_key'] ?? ''; // Fallback
    }

    if (empty($apiKey)) {
        throw new Exception("API Key AI belum diatur di Settings (Gemini Keys).");
    }

    // 3. Fetch Uncategorized Leads (Limit 15 per batch for token safety)
    $limit = 15;
    $leads = $pdo->query("SELECT id, name, email, message FROM leads WHERE ai_status = 'uncategorized' ORDER BY id DESC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($leads)) {
        $msg = "Tidak ada lead baru untuk diproses.";
        if (defined('IS_CRON')) cliLog($msg);
        else echo json_encode(['status' => 'success', 'message' => $msg]);
        exit;
    }

    if (defined('IS_CRON')) cliLog("Memproses " . count($leads) . " lead...");

    // 4. Construct Batch Prompt
    $prompt = "Tugas Anda adalah mengklasifikasikan pesan masuk (Lead) menjadi 'genuine' (Order/Tanya Jasa) atau 'spam' (Iklan/Judol/Bot/Promosi Gaje).\n\n";
    $prompt .= "Berikan output JSON list dengan format: [ { \"id\": 123, \"status\": \"genuine/spam\", \"confidence\": 90, \"reason\": \"singkat\" } ]\n\n";
    $prompt .= "Berikut daftar pesannya:\n";

    foreach ($leads as $lead) {
        // Sanitize content for prompt
        $msgContent = mb_strimwidth(str_replace(["\n", "\r", '"'], " ", $lead['message']), 0, 300, "...");
        $prompt .= "- ID: {$lead['id']} | Nama: {$lead['name']} | Pesan: \"{$msgContent}\"\n";
    }

    // 5. Call Gemini API
    $payload = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ]
    ];
    
    // System Instruction if available
    if (!empty($settings['ai_system_instruction'])) {
        $payload['system_instruction'] = [
            'parts' => [['text' => $settings['ai_system_instruction']]]
        ];
    }
    
    $model = trim($settings['ai_config_gemini_model'] ?? ($settings['ai_model'] ?? 'gemini-1.5-flash'));
    if (strpos($model, 'models/') === 0) $model = substr($model, 7);
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) throw new Exception("Curl Error: " . curl_error($ch));
    curl_close($ch);

    $resData = json_decode($response, true);
    
    // 6. Parse Response
    if (!isset($resData['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception("Gagal mendapatkan respons AI.");
    }

    $rawText = $resData['candidates'][0]['content']['parts'][0]['text'];
    
    // Extract JSON array
    if (preg_match('/\[.*\]/s', $rawText, $matches)) {
        $jsonArray = json_decode($matches[0], true);
    } else {
        throw new Exception("Format JSON dari AI tidak valid.");
    }

    if (!is_array($jsonArray)) throw new Exception("Output bukan array.");

    // 7. Update Database
    $updateStmt = $pdo->prepare("UPDATE leads SET ai_status = ?, ai_confidence = ?, ai_analysis = ? WHERE id = ?");
    $count = 0;
    
    foreach ($jsonArray as $item) {
        // Validation
        $status = strtolower($item['status'] ?? 'uncategorized');
        if (!in_array($status, ['genuine', 'spam'])) $status = 'uncategorized';
        
        $conf = (int)($item['confidence'] ?? 0);
        $reason = mb_strimwidth($item['reason'] ?? '', 0, 250, "...");
        $id = (int)($item['id'] ?? 0);

        if ($id > 0) {
            $updateStmt->execute([$status, $conf, $reason, $id]);
            $count++;
        }
    }

    $msg = "Berhasil memproses $count lead.";
    if (defined('IS_CRON')) cliLog($msg);
    else echo json_encode(['status' => 'success', 'message' => $msg, 'processed' => $count]);

    // Release Lock
    flock($fp, LOCK_UN);
    fclose($fp);

} catch (Exception $e) {
    if (isset($fp)) { flock($fp, LOCK_UN); fclose($fp); }
    if (defined('IS_CRON')) cliLog("Error: " . $e->getMessage());
    else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
