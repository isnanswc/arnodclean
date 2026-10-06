<?php
// admin/api/get_dashboard_ai.php
// DEBUG MODE
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'ai_error.log');

function debugLog($msg) {
    file_put_contents('ai_debug.log', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

// Start buffering to catch any stray output
ob_start();

debugLog("Request Started");

require_once '../../db.php';
debugLog("DB Loaded");
require_once '../includes/auth.php';
debugLog("Auth Loaded");
require_once '../includes/functions.php';
debugLog("Functions Loaded");

// Clean buffer before header
ob_clean();
header('Content-Type: application/json');

// function isLoggedIn() check manually
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    debugLog("Unauthorized Access");
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    debugLog("User Logged In");
    // 1. Fetch AI Settings
    $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings");
    $settings = [];
    while ($row = $stmtSettings->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // 2. Resolve API Key & Provider
    $activeProvider = $settings['ai_active_provider'] ?? 'gemini';
    $apiKey = '';
    $model = '';

    if ($activeProvider === 'groq') {
        $model = $settings['ai_config_groq_model'] ?? 'llama-3.3-70b-versatile';
        $keysJson = $settings['ai_config_groq_keys'] ?? '[]';
        $keys = json_decode($keysJson, true);
        if (is_array($keys) && !empty($keys)) {
            $apiKey = $keys[0]; // TODO: Implement rotation if needed, using first key for now
        }
    } else {
        // Default Gemini
        $activeProvider = 'gemini';
        $model = $settings['ai_config_gemini_model'] ?? 'gemini-1.5-flash';
        $keysJson = $settings['ai_config_gemini_keys'] ?? '[]';
        $keys = json_decode($keysJson, true);
        if (is_array($keys) && !empty($keys)) {
            $apiKey = $keys[0]; 
        }
    }
    
    // Override logic variable for callAI
    $provider = $activeProvider;
    debugLog("Provider: $provider, Key Found: " . (empty($apiKey) ? "NO" : "YES"));

    /* 
    if (empty($apiKey)) {
        throw new Exception("API Key belum dikonfigurasi.");
    } 
    */

    // 2. Calculate Stats
    $totalArticles = $pdo->query("SELECT COUNT(*) FROM articles WHERE status='published'")->fetchColumn();
    
    // Get SEO Scores
    $stmtScores = $pdo->query("SELECT seo_score FROM articles WHERE status='published'");
    $scores = $stmtScores->fetchAll(PDO::FETCH_COLUMN);
    
    $avgScore = 0;
    $countHigh = 0;
    if (count($scores) > 0) {
        $avgScore = round(array_sum($scores) / count($scores), 1);
        foreach ($scores as $s) {
            if ($s >= 80) $countHigh++;
        }
    }
    $highPercent = $totalArticles > 0 ? round(($countHigh / $totalArticles) * 100, 1) : 0;
    debugLog("Stats: Avg=$avgScore, High=$highPercent%");
    
    // 3. Construct Prompt
    $prompt = "Kamu adalah asisten AI yang cerdas, ramah, dan memotivasi untuk pemilik website bernama 'Arno'.\n\n";
    $prompt .= "DATA WEBSITE HARI INI:\n";
    $prompt .= "- Total Artikel: $totalArticles\n";
    $prompt .= "- Rata-rata Skor SEO: $avgScore / 100\n";
    $prompt .= "- Persentase Artikel Bagus (Skor >= 80): $highPercent%\n\n"; // Fixed missing %
    
    $prompt .= "INSTRUKSI:\n";
    $prompt .= "1. Berikan komentar singkat (maksimal 3 kalimat) tentang performa SEO.\n";
    $prompt .= "2. Gunakan gaya bahasa natural, seperti teman kerja yang supportif.\n";
    $prompt .= "3. Jika Rata-rata Skor > 80: Puji Arno scara antusias! Sebutkan bahwa ini adalah pencapaian 'Gold Tier/Piagam Kemenangan'. Ingatkan untuk tidak menghapus artikel-artikel bagus ini.\n";
    $prompt .= "4. Jika Rata-rata Skor < 60: Berikan semangat, katakan bahwa perbaikan kecil bisa berdampak besar.\n";
    $prompt .= "5. Gunakan emoji yang relevan.\n";
    
    // 4. Call AI (or Mock if no key)
    if (empty($apiKey)) {
        // Mock Response for Demo/No Key
        $aiResponse = "Halo Arno! 👋 (Mode Demo)\n\n";
        if ($avgScore >= 80) {
            $aiResponse .= "Wow, performa SEO luar biasa! 🏆 Skor rata-rata $avgScore sangat mengesankan 'Gold Tier'. Pertahankan kualitas ini dan jangan hapus artikel yang sudah bagus ya! 🚀";
        } elseif ($avgScore >= 60) {
            $aiResponse .= "Kerja bagus! Skor rata-rata $avgScore sudah cukup oke, tapi masih bisa ditingkatkan lagi. Coba perbaiki beberapa artikel yang nilainya kurang maksimal. 💪";
        } else {
            $aiResponse .= "Sepertinya kita perlu bersih-bersih SEO nih. Skor rata-rata $avgScore masih perlu perhatian. Yuk perbaiki artikel-artikel lama biar traffic makin kencang! 🧹";
        }
    } else {
        $aiResponse = callAI($prompt, $apiKey, $model, $provider);
    }
    
    echo json_encode(['status' => 'success', 'message' => $aiResponse, 'stats' => ['avg' => $avgScore]]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => "AI Error: " . $e->getMessage()]);
}
