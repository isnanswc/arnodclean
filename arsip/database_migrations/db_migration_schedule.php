<?php
// admin/db_migration_schedule.php
require_once __DIR__ . '/../db.php';

echo "Migrating Auto Content Settings for Smart Schedule...\n\n";

try {
    // 1. Ensure table exists (redundant safety)
    $pdo->query("SELECT 1 FROM auto_content_settings LIMIT 1");

    // 2. Define new default settings
    $newSettings = [
        'ai_schedule_mode' => 'smart',      // 'smart', 'interval', 'custom'
        'ai_schedule_frequency' => '3',     // Articles per day limit
        'ai_schedule_days' => '["Mon","Tue","Wed","Thu","Fri","Sat","Sun"]', // Active days
        'ai_schedule_hours' => '["09:00", "12:00", "17:00", "20:00"]', // Custom/Smart hours
        'ai_huggingface_token' => '',       // NEW: Hugging Face Backup
        'ai_pexels_key' => '',              // NEW: Pexels Backup
        'ai_image_priority' => json_encode(['pollinations', 'huggingface', 'pexels', 'google']), // Provider Order
        // NEW: Multi-Provider AI Brain
        'ai_active_provider' => 'gemini',    // 'gemini' or 'groq'
        'ai_config_gemini_keys' => '[]',     // JSON list of API keys
        'ai_config_gemini_model' => 'gemini-1.5-flash',
        'ai_config_groq_keys' => '[]',
        'ai_config_groq_model' => 'llama3-70b-8192'
    ];

    $stmCheck = $pdo->prepare("SELECT COUNT(*) FROM auto_content_settings WHERE setting_key = ?");
    $stmInsert = $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmGet = $pdo->prepare("SELECT setting_value FROM auto_content_settings WHERE setting_key = ?");
    $stmUpdate = $pdo->prepare("UPDATE auto_content_settings SET setting_value = ? WHERE setting_key = ?");

    foreach ($newSettings as $key => $defaultVal) {
        $stmCheck->execute([$key]);
        if ($stmCheck->fetchColumn() == 0) {
            echo "Adding setting: $key ... ";
            $stmInsert->execute([$key, $defaultVal]);
            echo "Done.\n";
        } else {
            echo "Setting $key already exists. Skipped.\n";
        }
    }

    // 3. Migrate Old Key to New List (One-time)
    $stmGet->execute(['ai_api_key']);
    $oldKey = $stmGet->fetchColumn();

    $stmGet->execute(['ai_config_gemini_keys']);
    $currentKeysJson = $stmGet->fetchColumn();
    $currentKeys = json_decode($currentKeysJson, true) ?? [];

    if (!empty($oldKey) && empty($currentKeys)) {
        echo "Migrating old `ai_api_key` to `ai_config_gemini_keys`... ";
        $newKeyList = json_encode([$oldKey]);
        $stmUpdate->execute([$newKeyList, 'ai_config_gemini_keys']);
        echo "Done.\n";
    }

    echo "\nMigration completed successfully.";

} catch (PDOException $e) {
    die("Migration Error: " . $e->getMessage());
}
?>
