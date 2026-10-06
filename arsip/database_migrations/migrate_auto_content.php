<?php
// migrate_auto_content.php
require_once 'db.php';

try {
    // 1. Create auto_content_settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS auto_content_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // 2. Create auto_content_keywords table
    $pdo->exec("CREATE TABLE IF NOT EXISTS auto_content_keywords (
        id INT AUTO_INCREMENT PRIMARY KEY,
        keyword VARCHAR(255) NOT NULL,
        status ENUM('pending', 'processing', 'done', 'failed') DEFAULT 'pending',
        error_message TEXT,
        article_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME
    )");

    // 3. Insert default settings
    $defaults = [
        'ai_api_key' => '',
        'ai_provider' => 'gemini', // gemini or openai
        'ai_model' => 'gemini-1.5-flash',
        'ai_prompt_template' => "Tulis artikel blog profesional dalam Bahasa Indonesia tentang: {keyword}. Format dalam HTML dengan tag h2, h3, p, ul. Pastikan isinya informatif, edukatif, dan ramah SEO untuk jasa cuci sofa Arno D Clean.",
        'auto_publish' => '1'
    ];

    foreach ($defaults as $key => $val) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO auto_content_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $val]);
    }

    echo "<h1>Migration Success</h1><p>Database tables for Auto Content have been prepared.</p><a href='admin/auto_content.php'>Go to Admin Auto Content</a>";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage());
}
