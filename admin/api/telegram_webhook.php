<?php
// admin/api/telegram_webhook.php

require_once '../../db.php';
require_once '../includes/auth.php';

// 1. Security Check
$secretToken = $_GET['token'] ?? '';
$stmt = $pdo->prepare("SELECT setting_value FROM auto_content_settings WHERE setting_key = 'tg_webhook_token'");
$stmt->execute();
$validToken = $stmt->fetchColumn();

if (!$validToken || $secretToken !== $validToken) {
    http_response_code(403);
    exit('Forbidden');
}

// 2. Get Webhook Data
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) exit;

$message = $update['message'] ?? null;
if (!$message) exit;

$text = strtolower(trim($message['text'] ?? ''));
$chatId = $message['chat']['id'] ?? '';


// Fetch Settings for Bot and Admin Check
$stmt = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
// $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // Replaced with full fetch

$adminChatId = $settings['tg_chat_id'] ?? '';
$botToken = $settings['tg_bot_token'] ?? '';

// AI Key Logic
$geminiKeys = json_decode($settings['ai_config_gemini_keys'] ?? '[]', true);
$apiKey = !empty($geminiKeys) ? $geminiKeys[0] : ($settings['ai_api_key'] ?? '');

$aiModel = $settings['ai_config_gemini_model'] ?? ($settings['ai_model'] ?? 'gemini-1.5-flash');

if ($chatId != $adminChatId) {
    // Optionally alert admin about unauthorized access attempt
    exit;
}

// 3. Command Handling
if ($text == 'report daily' || $text == '/report') {
    // Requires functions.php to be loaded
    require_once '../includes/functions.php';
    generateAIReport($chatId, $botToken, $apiKey, $pdo, $aiModel);
} else if ($text == '/start') {
    sendTelegram($chatId, "👋 Halo Admin! Bot Interaktif Aktif.\n\nKetik <b>report daily</b> untuk mendapatkan laporan performa website hari ini.", $botToken);
}

// Old function removed to use shared logic in functions.php
