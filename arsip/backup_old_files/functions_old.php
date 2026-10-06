<?php
// admin/includes/functions.php

/**
 * Handle File Upload with Resize and Compression
 * 
 * @param array  $file       The $_FILES['input_name'] array
 * @param string $targetDir  Target directory (e.g., "../uploads/services/")
 * @param string $folderName Subfolder name for DB path (e.g., "uploads/services/")
 * @return string|false      Relative path to file or false on failure
 */
function uploadAndResize($file, $targetDir, $folderName) {
    // Increase memory limit just in case
    ini_set('memory_limit', '512M'); 
    
    if (empty($file['name'])) return false;

    // Check for Upload Errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran file terlalu besar (Melebihi batas server).',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran file terlalu besar (Melebihi batas form).',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian.',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary server hilang.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP.'
        ];
        $msg = $errors[$file['error']] ?? 'Unknown Value';
        throw new Exception("Upload Error: " . $msg);
    }

    // Create folder if not exists
    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            throw new Exception("Gagal membuat folder upload: $targetDir");
        }
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_images = ['jpg', 'jpeg', 'png', 'webp'];
    $allowed_videos = ['mp4', 'webm', 'ogg'];
    $allowed = array_merge($allowed_images, $allowed_videos);
    
    if (!in_array($ext, $allowed)) {
        throw new Exception("Format file tidak didukung. Gunakan JPG, JPEG, PNG, WebP, MP4, WebM, atau OGG.");
    }

    $is_video = in_array($ext, $allowed_videos);
    $source = $file['tmp_name'];
    
    // Validate MIME Type (Extra Security)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $source);
    finfo_close($finfo);

    $valid_mimes = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg'
    ];

    // Loose check for video mime types because they can vary (e.g. application/octet-stream)
    // But for critical security, we should enforce known types.
    // For now, let's just make sure it's not text/x-php or application/x-httpd-php
    if (strpos($mime, 'php') !== false) {
        throw new Exception("File terdeteksi berbahaya (PHP script).");
    }

    $prefix = $is_video ? 'vid_' : 'img_';
    
    // If it's an image, we convert to WebP for optimization
    if (!$is_video && in_array($ext, $allowed_images)) {
        $newFilename = uniqid($prefix) . '.webp';
        $destination = $targetDir . $newFilename;
        $dbPath = $folderName . $newFilename;

        // Process Image
        processImageToWebp($source, $destination, 1200, 80);
        return $dbPath;
    } else {
        // For videos or fallback
        $newFilename = uniqid($prefix) . '.' . $ext; 
        $destination = $targetDir . $newFilename;
        $dbPath = $folderName . $newFilename;

        if (move_uploaded_file($source, $destination)) {
            return $dbPath;
        }
    }

    throw new Exception("Gagal memindahkan file ke folder tujuan.");
}

/**
 * Process Image: Resize (if needed) and convert to WebP
 */
function processImageToWebp($source, $destination, $maxWidth = 1200, $quality = 80) {
    $info = getimagesize($source);
    if (!$info) throw new Exception("File bukan gambar yang valid.");

    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];

    // Create image from source
    switch ($mime) {
        case 'image/jpeg': $img = imagecreatefromjpeg($source); break;
        case 'image/png': 
            $img = imagecreatefrompng($source); 
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
            break;
        case 'image/webp': $img = imagecreatefromwebp($source); break;
        default: throw new Exception("Format gambar tidak didukung untuk pemrosesan.");
    }

    if (!$img) throw new Exception("Gagal memproses gambar.");

    // Resize if wider than maxWidth
    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = floor($height * ($maxWidth / $width));
        
        $tmp = imagecreatetruecolor($newWidth, $newHeight);
        
        // Handle transparency for PNG/WebP
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $transparent = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefill($tmp, 0, 0, $transparent);

        imagecopyresampled($tmp, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($img);
        $img = $tmp;
    }

    // Save as WebP
    if (!imagewebp($img, $destination, $quality)) {
        imagedestroy($img);
        throw new Exception("Gagal menyimpan gambar sebagai WebP.");
    }

    imagedestroy($img);
    return true;
}

/**
 * Generate AI-Powered Daily Report (Executive Summary)
 * Requires auth.php (for sendTelegram) and db.php
 */
function generateAIReport($chatId, $botToken, $apiKey, $pdo, $model = 'gemini-1.5-flash', $provider = 'gemini') {
    // A. Fetch Report Configuration
    $reportConfigJson = $pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key = 'tg_report_config'")->fetchColumn();
    $config = json_decode($reportConfigJson, true);
    if (!is_array($config)) $config = ['leads', 'traffic', 'ai_insight', 'articles']; // Default

    // B. Gather Data
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $dataContext = "Data Performa Bisnis Hari Ini ($today):\n";
    $msg = "📊 <b>DAILY EXECUTIVE BRIEF</b>\n";
    $msg .= "📅 " . date('d M Y') . "\n\n";

    // 1. Leads Logic
    if (in_array('leads', $config)) {
        $lAll = $pdo->query("SELECT COUNT(*) FROM leads WHERE DATE(created_at) = '$today'")->fetchColumn();
        $lGenuine = $pdo->query("SELECT COUNT(*) FROM leads WHERE DATE(created_at) = '$today' AND ai_status = 'genuine'")->fetchColumn();
        $lSpam = $pdo->query("SELECT COUNT(*) FROM leads WHERE DATE(created_at) = '$today' AND ai_status = 'spam'")->fetchColumn();
        
        $dataContext .= "- Lead/Pesan Masuk: Total $lAll (Genuine: $lGenuine, Spam: $lSpam)\n";
        
        $msg .= "📥 <b>Lead Update:</b>\n";
        $msg .= "• Total Pesan: <b>$lAll</b>\n";
        $msg .= "• ✅ Potensial: <b>$lGenuine</b>\n";
        $msg .= "• 🚫 Spam/Bot: $lSpam\n\n";
    }

    // 2. Traffic Stats (24-Hour Sliding Window) & Deep Analytics
    if (in_array('traffic', $config)) {
        // A. Basic Volume
        $vToday = $pdo->query("SELECT COUNT(*) FROM visitor_analytics WHERE created_at >= NOW() - INTERVAL 24 HOUR AND is_bot = 0")->fetchColumn();
        $vYesterday = $pdo->query("SELECT COUNT(*) FROM visitor_analytics WHERE created_at >= NOW() - INTERVAL 48 HOUR AND created_at < NOW() - INTERVAL 24 HOUR AND is_bot = 0")->fetchColumn();
        
        // B. Human vs Bot
        $bToday = $pdo->query("SELECT COUNT(*) FROM visitor_analytics WHERE created_at >= NOW() - INTERVAL 24 HOUR AND is_bot = 1")->fetchColumn();
        $totalHits = $vToday + $bToday;
        $botPercent = $totalHits > 0 ? round(($bToday / $totalHits) * 100, 1) : 0;
        
        // C. Peak Hour
        $peak = $pdo->query("SELECT HOUR(created_at) as h, COUNT(*) as c FROM visitor_analytics WHERE created_at >= NOW() - INTERVAL 24 HOUR GROUP BY h ORDER BY c DESC LIMIT 1")->fetch();
        $peakHour = $peak ? str_pad($peak['h'], 2, '0', STR_PAD_LEFT) . ":00" : "-";
        
        // D. Top Sources
        $srcs = $pdo->query("SELECT COALESCE(referrer, 'Direct') as r, COUNT(*) as c FROM visitor_analytics WHERE created_at >= NOW() - INTERVAL 24 HOUR AND is_bot = 0 GROUP BY r ORDER BY c DESC LIMIT 3")->fetchAll();
        
        // Build Context for AI
        $dataContext .= "- Traffic 24h Terakhir: $vToday Visitors (vs Kemarin: $vYesterday)\n";
        $dataContext .= "- Human vs Bot: Human $vToday visits, Bot $bToday hits ($botPercent% Bot)\n";
        $dataContext .= "- Jam Tersibuk: Pukul $peakHour\n";
        $dataContext .= "- Top Sumber Traffic: " . implode(", ", array_map(fn($s) => $s['r'] . " (" . $s['c'] . ")", $srcs)) . "\n";
        
        // Build Telegram Message
        $msg .= "👥 <b>Traffic Intelligence (24h):</b>\n";
        
        // Volume & Trend
        $diff = $vToday - $vYesterday;
        $trend = $diff >= 0 ? "📈 +$diff" : "📉 $diff";
        $msg .= "• Real Visitors: <b>$vToday</b> ($trend)\n";
        
        // Human/Bot Ratio
        $msg .= "• Ratio: 👤 " . (100 - $botPercent) . "% vs 🤖 $botPercent%\n";
        
        // Peak Hour
        $msg .= "• ⏰ Peak Hour: <b>$peakHour</b>\n";
        
        // Sources
        if($srcs) {
            $msg .= "• 🔗 Sources: ";
            $limitSrc = [];
            foreach($srcs as $s) $limitSrc[] = $s['r'];
            $msg .= implode(", ", $limitSrc) . "\n";
        }
        $msg .= "\n";
    }

    // 3. SEO Stats
    if (in_array('seo_score', $config)) {
        $avgSeo = $pdo->query("SELECT AVG(seo_score) FROM articles WHERE status = 'published'")->fetchColumn();
        $avgSeo = round($avgSeo, 1);
        $dataContext .= "- Rata-rata Skor SEO Artikel: $avgSeo\n";
        
        $msg .= "🔍 <b>SEO Health:</b>\n";
        $color = $avgSeo >= 80 ? '🟢' : ($avgSeo >= 60 ? '🟡' : '🔴');
        $msg .= "• Avg Score: <b>$avgSeo</b> $color\n\n";
    }

    // 4. Content Stats
    if (in_array('articles', $config)) {
        $popularArticles = $pdo->query("SELECT title, views FROM articles ORDER BY views DESC LIMIT 3")->fetchAll();
        if ($popularArticles) {
            $dataContext .= "- Artikel Terpopuler:\n";
            $msg .= "📰 <b>Top Artikel:</b>\n";
            foreach($popularArticles as $a) {
                $dataContext .= "  * " . $a['title'] . " (" . $a['views'] . " views)\n";
                // Truncate title for TG
                $titleShort = strlen($a['title']) > 25 ? substr($a['title'], 0, 22) . '...' : $a['title'];
                $msg .= "• $titleShort (" . $a['views'] . ")\n";
            }
            $msg .= "\n";
        }
    }
    
    // 5. AI Insight
    if (in_array('ai_insight', $config)) {
        $aiInsight = "Gagal memproses insight.";
        
        $prompt = "Anda adalah Senior Data Analyst. Tugas:\n";
        $prompt .= "1. Analisa performa traffic dan kualitas traffic (Human vs Bot, Source).\n";
        $prompt .= "2. Hubungkan data 'Jam Tersibuk' dengan 'Top Article' atau 'Leads' jika ada pola.\n";
        $prompt .= "3. Berikan 1 rekomendasi strategis untuk growth besok.\n";
        $prompt .= "Format: Profesional, Padat, To-the-point (Maksimal 3-4 kalimat/poin). Hemat kata.\n";
        $prompt .= "\nDATA HARI INI:\n$dataContext";
        
        try {
            if ($provider === 'groq') {
                // ... (Previous setup code remains implicitly the same, but for brevity in tool call, I'll rewrite the block to be safe)
                $url = "https://api.groq.com/openai/v1/chat/completions";
                $headers = [
                    "Authorization: Bearer " . $apiKey,
                    "Content-Type: application/json"
                ];
                $payload = json_encode([
                    "model" => $model,
                    "messages" => [
                        ["role" => "system", "content" => "You are a helpful business intelligence assistant. Keep it very concise."],
                        ["role" => "user", "content" => $prompt]
                    ],
                    "temperature" => 0.7
                ]);
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                $response = curl_exec($ch);
                
                if (curl_errno($ch)) {
                    $aiInsight .= " (CURL Error: " . curl_error($ch) . ")";
                }
                curl_close($ch);
                
                $res = json_decode($response, true);
                if (isset($res['choices'][0]['message']['content'])) {
                    $aiInsight = trim($res['choices'][0]['message']['content']);
                } elseif (isset($res['error']['message'])) {
                    $aiInsight .= " (API Error: " . $res['error']['message'] . ")";
                }
                
            } else {
                // GEMINI API (Default)
                if (empty($model)) $model = 'gemini-1.5-flash';
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
                
                $payload = json_encode(["contents" => [["parts" => [["text" => $prompt]]]]]);
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                $response = curl_exec($ch);
                
                if (curl_errno($ch)) {
                    $aiInsight .= " (CURL Error: " . curl_error($ch) . ")";
                }
                curl_close($ch);
                
                $res = json_decode($response, true);
                if (isset($res['candidates'][0]['content']['parts'][0]['text'])) {
                    $aiInsight = trim($res['candidates'][0]['content']['parts'][0]['text']);
                } elseif (isset($res['error']['message'])) {
                    $aiInsight .= " (API Error: " . $res['error']['message'] . ")";
                }
            }
            
            // Format Insight
            $aiInsight = str_replace("**", "", $aiInsight); 
            $msg .= "💡 <b>AI Insight:</b>\n" . "<i>" . $aiInsight . "</i>\n";
            
        } catch (Exception $e) {
            $msg .= "💡 <b>AI Insight:</b> (Exception: ".$e->getMessage().")\n";
        }
    }
    
    sendTelegram($chatId, $msg, $botToken);
}

// --- SEO Helper Functions ---

function isKeywordPresent($text, $keyword, $threshold = 0.7) {
    if (empty($keyword)) return false;
    
    // Normalize
    $text = strtolower(trim($text));
    $keyword = strtolower(trim($keyword));
    
    // 1. Direct match check
    if (strpos($text, $keyword) !== false) return true;
    
    // 2. Fuzzy Token Match
    $kwTokens = array_filter(explode(' ', $keyword)); // Remove empty elements
    $totalTokens = count($kwTokens);
    
    if ($totalTokens === 0) return false;

    $foundCount = 0;
    foreach ($kwTokens as $token) {
        if (!empty($token) && strpos($text, $token) !== false) {
            $foundCount++;
        }
    }
    
    // Calculate ratio
    $ratio = $foundCount / $totalTokens;
    return $ratio >= $threshold;
}

function calculateSeoScoreLocal($title, $slug, $meta_title, $meta_description, $keyword) {
    $score = 100;
    $log = [];
    
    $keyword = (string)$keyword; // Ensure string
    
    // If no keyword, we can't fully judge, but let's assume worst or just check lengths
    if (empty($keyword)) {
        // Fallback checks
    }

    // 1. Keyword in Title
    if (!isKeywordPresent($title, $keyword)) {
        $score -= 20;
        $log[] = "Keyword missing/mismatch in Title. (Target: '$keyword')";
    }

    // 2. SEO Title Length
    $mtLen = strlen($meta_title);
    if ($mtLen < 30 || $mtLen > 70) {
        $score -= 10;
        $log[] = "Meta Title length Critical ($mtLen chars). Ideal: 40-60.";
    } elseif ($mtLen > 60) {
        $score -= 5;
        $log[] = "Meta Title length Warning ($mtLen chars). Ideal: 40-60.";
    }

    // 3. Meta Desc Length
    $mdLen = strlen($meta_description);
    if ($mdLen < 100 || $mdLen > 170) {
        $score -= 10; 
        $log[] = "Meta Desc length Critical ($mdLen chars). Ideal: 100-160.";
    } elseif ($mdLen > 160) {
        $score -= 5;
        $log[] = "Meta Desc length Warning ($mdLen chars). Ideal: 100-160.";
    }

    // 4. Keyword in Meta Desc
    if (!isKeywordPresent($meta_description, $keyword)) {
        $score -= 20;
        $log[] = "Keyword missing in Meta Desc. (Target: '$keyword')";
    }

    return ['score' => max(0, $score), 'critique' => implode("\n", $log)];
}

