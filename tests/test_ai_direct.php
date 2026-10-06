<?php
// admin/test_ai_direct.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting AI Test...\n";

try {
    require_once '../db.php';
    echo "DB Connected.\n";
    require_once 'includes/functions.php';
    echo "Functions Loaded.\n";

    // 1. Fetch AI Settings manually
    $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings");
    $settings = [];
    while ($row = $stmtSettings->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // 2. Resolve API Key & Provider
    $activeProvider = $settings['ai_active_provider'] ?? 'gemini';
    $apiKey = '';
    $model = '';

    echo "Active Provider: $activeProvider\n";

    if ($activeProvider === 'groq') {
        $model = $settings['ai_config_groq_model'] ?? 'llama-3.3-70b-versatile';
        $keysJson = $settings['ai_config_groq_keys'] ?? '[]';
        $keys = json_decode($keysJson, true);
        if (is_array($keys) && !empty($keys)) {
            $apiKey = $keys[0]; 
        }
    } else {
        $model = $settings['ai_config_gemini_model'] ?? 'gemini-1.5-flash';
        $keysJson = $settings['ai_config_gemini_keys'] ?? '[]';
        $keys = json_decode($keysJson, true);
        if (is_array($keys) && !empty($keys)) {
            $apiKey = $keys[0]; 
        }
    }

    echo "API Key Length: " . strlen($apiKey) . "\n";
    echo "Model: $model\n";
    
    if (empty($apiKey)) {
        die("API Key is EMPTY!\n");
    }

    $provider = $activeProvider;
    
    // 2. Call AI
    echo "Calling AI...\n";
    $response = callAI("Say hello!", $apiKey, $model, $provider);
    
    echo "Response: " . $response . "\n";

} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
