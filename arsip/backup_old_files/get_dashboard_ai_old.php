<?php
// admin/api/get_dashboard_ai.php
require_once '../../db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    // 1. Fetch AI Settings
    $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings");
    $settings = [];
    while ($row = $stmtSettings->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $apiKey = $settings['ai_api_key'] ?? '';
    // $provider = $settings['ai_provider'] ?? 'gemini';
    // $model = $settings['ai_model'] ?? 'gemini-1.5-flash';
    // FORCE GEMINI FOR NOW IF NOT SET, OR USE user pref
    $provider = 'gemini'; // Force Gemini for consistency as per previous tool use, or use setting
    if (!empty($settings['ai_provider'])) $provider = $settings['ai_provider'];
    
    $model = 'gemini-1.5-flash';
    if (!empty($settings['ai_model'])) $model = $settings['ai_model'];

    if (empty($apiKey)) {
        throw new Exception("API Key belum dikonfigurasi.");
    }

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
    
    // 4. Call AI
    $aiResponse = callAI($prompt, $apiKey, $model, $provider);
    
    echo json_encode(['status' => 'success', 'message' => $aiResponse, 'stats' => ['avg' => $avgScore]]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => "AI Error: " . $e->getMessage()]);
}
