<?php
// ... (Detect Cron Logic) ...
if (php_sapi_name() === 'cli') {
    if (!defined('IS_CRON')) define('IS_CRON', true);
}

if (!defined('IS_CRON')) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!defined('IS_CRON')) {
    checkLogin();
}

function logMsg($msg) {
    if (defined('IS_CRON')) echo date('Y-m-d H:i:s') . " - " . $msg . "\n";
}

// ... (callGemini, extractJSON, isKeywordPresent, calculateSeoScoreLocal - reused) ...
// --- AI Helper Functions ---

function generateContentWithFailover($prompt, $systemInst, $settings) {
    global $pdo; // Need PDO for logging critical alerts if needed
    
    // Config Extraction
    $primaryProvider = $settings['ai_active_provider'] ?? 'gemini';
    $primaryKeys = [];
    $primaryModel = '';

    // Prepare Providers Loop (Primary -> Backup)
    $providersToTry = [$primaryProvider];
    // Add backup provider if primary fails? User wants explicit switch or notification.
    // "If not usable, switch to api below it" -> Implies trying secondary provider.
    $secondaryProvider = ($primaryProvider === 'gemini') ? 'groq' : 'gemini'; // Simple toggle logic
    $providersToTry[] = $secondaryProvider;

    $finalError = "";

    foreach ($providersToTry as $currentProvider) {
        $keys = [];
        $model = '';

        // Load Config for Current Provider
        if ($currentProvider === 'groq') {
            $keys = json_decode($settings['ai_config_groq_keys'] ?? '[]', true);
            $model = $settings['ai_config_groq_model'] ?? 'llama-3.3-70b-versatile';
        } else {
            $keys = json_decode($settings['ai_config_gemini_keys'] ?? '[]', true);
            if (empty($keys) && !empty($settings['ai_api_key'])) $keys = [$settings['ai_api_key']]; // Fallback
            $model = $settings['ai_config_gemini_model'] ?? 'gemini-1.5-flash';
        }

        // Clean Empty Keys
        $keys = array_filter($keys);
        if (empty($keys)) {
             logMsg("Skipping $currentProvider (No keys configured)");
             continue;
        }

        foreach ($keys as $idx => $apiKey) {
            try {
                // logMsg("Attempting $currentProvider with Key ...".substr($apiKey, -4));
                if ($currentProvider === 'groq') {
                    return _rawCallGroq($apiKey, $model, $prompt, $systemInst);
                } else {
                    return callGemini($apiKey, $model, $prompt, $systemInst);
                }
            } catch (Exception $e) {
                $err = $e->getMessage();
                // Check if Limit Reached
                if (strpos(strtolower($err), '429') !== false || strpos(strtolower($err), 'quota') !== false || strpos(strtolower($err), 'exhausted') !== false) {
                    logMsg("⚠️ API LIMIT: $currentProvider Key #".($idx+1)." exhausted. Switching to next key/provider...");
                    // Continue to next key loop
                } else {
                    logMsg("API Error ($currentProvider): $err");
                }
                $finalError = $err;
            }
        }
    }
    
    // If we reached here, ALL providers/keys failed.
    // Send Critical Alert
    $alertMsg = "🚨 <b>CRITICAL AI FAILURE</b>\nSemua API Key (Gemini & Groq) telah mencapai batas limit atau error!\nHarap tambahkan API Key baru di pengaturan.";
    if (isset($settings['tg_bot_token'], $settings['tg_chat_id'], $settings['tg_notify_enabled']) && $settings['tg_notify_enabled'] == '1') {
        sendTelegram($settings['tg_chat_id'], $alertMsg, $settings['tg_bot_token']);
    }
    
    throw new Exception("ALL AI PROVIDERS FAILED. Please update API Keys. Last Error: " . $finalError);
}

function _rawCallGroq($apiKey, $model, $prompt, $systemInstruction = null) {
    // Groq API Implementation (OpenAI Compatible)
    $url = "https://api.groq.com/openai/v1/chat/completions";
    
    $messages = [];
    if ($systemInstruction) {
        $messages[] = ['role' => 'system', 'content' => $systemInstruction];
    }
    $messages[] = ['role' => 'user', 'content' => $prompt];

    $payload = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) throw new Exception("Groq Curl Error: $err");
    
    $resData = json_decode($response, true);
    if (isset($resData['error'])) {
        throw new Exception("Groq API Error: " . ($resData['error']['message'] ?? 'Unknown'));
    }

    return $resData['choices'][0]['message']['content'] ?? null;
}

function callGemini($apiKey, $model, $prompt, $systemInstruction = null) {
    if (strpos($model, 'models/') === 0) {
        $model = substr($model, 7);
    }
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
    
    $safetySettings = [
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
    ];

    $payload = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'safetySettings' => $safetySettings,
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
        ]
    ];
    if ($systemInstruction) {
        $payload['system_instruction'] = ['parts' => [['text' => $systemInstruction]]];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) throw new Exception("Curl Error: $err");
    
    $resData = json_decode($response, true);
    if (isset($resData['error'])) {
         $msg = $resData['error']['message'] ?? 'Unknown Error';
         $code = $resData['error']['code'] ?? 0;
         throw new Exception("Gemini Error ($code): $msg");
    }
    if (!isset($resData['candidates'][0]['content']['parts'][0]['text'])) {
        $errMsg = json_encode($resData);
        throw new Exception("Gemini Invalid Response: $errMsg");
    }

    return $resData['candidates'][0]['content']['parts'][0]['text'];
}

function extractJSON($rawText) {
    if (preg_match('/\{.*\}/s', $rawText, $matches)) {
        return json_decode($matches[0], true);
    }
    return null;
}

// Functions isKeywordPresent and calculateSeoScoreLocal are in functions.php

// --- Main Standard Logic ---

try {
    // 1. Init Settings
    $settings = [];
    $stmt = $pdo->query("SELECT * FROM auto_content_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Check Config Presence (Optional, fail fast)
    $actProvider = $settings['ai_active_provider'] ?? 'gemini';
    // We defer key check to the actual generation call for flexibility

    // 2. Fetch Queue
    $kw = $pdo->query("SELECT * FROM auto_content_keywords WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1")->fetch();
    if (!$kw) {
        if (defined('IS_CRON')) echo "Antrian kosong.\n";
        else echo json_encode(['status' => 'success', 'message' => "Antrian kosong."]);
        exit;
    }

    $pdo->prepare("UPDATE auto_content_keywords SET status = 'processing' WHERE id = ?")->execute([$kw['id']]);
    logMsg("Memproses keyword: " . $kw['keyword']);

    // --- SELF-LEARNING: FETCH RULES ---
    $rulesStmt = $pdo->query("SELECT rule_content FROM ai_learning_rules ORDER BY hit_count DESC LIMIT 5");
    $learnedRules = $rulesStmt->fetchAll(PDO::FETCH_COLUMN);
    $rulesText = "";
    if (!empty($learnedRules)) {
        $rulesText = "\n\nPAST MISTAKES & RULES (LEARNED FROM EXPERIENCE):\n";
        foreach ($learnedRules as $idx => $rule) {
            $rulesText .= ($idx+1) . ". " . $rule . "\n";
        }
    }

    // 3. Prepare Context
    $availableTags = $pdo->query("SELECT name FROM tags")->fetchAll(PDO::FETCH_COLUMN);
    $tagContext = !empty($availableTags) ? "Available Tags: " . implode(', ', $availableTags) : "";
    
    $template = $settings['ai_prompt_template'] ?? '';
    if (strpos($template, '{keyword}') === false) {
        $basePrompt = $template . "\n\nMain Topic: " . $kw['keyword'];
    } else {
        $basePrompt = str_replace('{keyword}', $kw['keyword'], $template);
    }
    
    // $model = trim($settings['ai_model'] ?? 'gemini-1.5-flash'); // REMOVED (Handled in Failover)

    // 4. Construct Prompt (Chain of Thought + Learned Rules)
    $fullPrompt = "You are an Elite SEO Content Creator. Target Keyword: '{$kw['keyword']}'.
    
    STRICT RULES:
    1. You MUST include the exact phrase '{$kw['keyword']}' in the Title.
    2. You MUST include the exact phrase '{$kw['keyword']}' in the Meta Description.
    3. Do NOT translate or alter the keyword. Use it verbatim.
    4. Use ONLY <h2> and <h3> tags for sub-headings in the content. NEVER use <h1> tags inside content.
    5. Include a short 'FAQ / Pertanyaan Umum' section with 2-3 common questions at the end of the content.
    $rulesText
    
    Chain-of-Thought Steps (Execute internally, output JSON only):
    1. PLAN: Define user intent for '{$kw['keyword']}'.
    2. TITLE: Draft a title (max 60 chars) that includes '{$kw['keyword']}'.
    3. META: Draft a description (120-160 chars) that includes '{$kw['keyword']}'.
    4. WRITE: Write the full content (min 600 words, HTML format).
    5. TAGS: Choose relevant tags (Max 15 tags, max 5 words per tag).
    
    $tagContext
    
    User Instructions: $basePrompt
    
    REQUIRED OUTPUT FORMAT (Raw JSON only, no markdown code blocks):
    {
        \"title\": \"...\",
        \"content\": \"<p>...</p>...\",
        \"tags\": [\"tag1\", \"tag2\"],
        \"meta_title\": \"...\",
        \"meta_description\": \"...\"
    }";

    $systemInst = $settings['ai_system_instruction'] ?? "Professional Writer. Output strict JSON. Respect SEO Keywords.";

    // Force Strict Mode for Groq/Llama
    if (($settings['ai_active_provider'] ?? '') === 'groq') {
        $systemInst = "You are a JSON Generator. Output ONLY valid JSON data. Do NOT use markdown. Do NOT use real line breaks inside strings; use \\n for new lines. Start with { and end with }.";
    }

    // 5. Generate Content
    logMsg("Sending Request to AI Provider (" . ($settings['ai_active_provider']??'gemini') . ")...");
    $rawResponse = generateContentWithFailover($fullPrompt, $systemInst, $settings);
    
    // Enhanced JSON Extraction/Cleaning
    $cleanResponse = preg_replace('/^```json\s*|\s*```$/', '', trim($rawResponse)); // Strip Markdown
    $start = strpos($cleanResponse, '{');
    $end = strrpos($cleanResponse, '}');
    
    if ($start !== false && $end !== false) {
        $jsonStr = substr($cleanResponse, $start, ($end - $start) + 1);
        
        // Attempt 1: Direct Decode
        $result = json_decode($jsonStr, true);
        
        // Attempt 2: Control Char Cleanup (Common Llama Issue)
        if ($result === null) {
            // Remove Control Characters (0-31) except newlines? No, remove all generic control chars
            $cleanerStr = preg_replace('/[\x00-\x1F\x7F]/u', '', $jsonStr); 
            $result = json_decode($cleanerStr, true);
        }

        // Attempt 3: Newline injection Fix (Blindly replace newlines with space if structure allows?)
        // Too risky. Just capture error.
        
        if ($result === null) {
            $jsonErr = json_last_error_msg();
        }
    } else {
        $result = null;
        $jsonErr = "No JSON structure found ({...})";
    }

    if (!$result || !isset($result['title']) || !isset($result['content'])) {
         // Log the raw response for debugging
         $debugSnippet = substr($rawResponse, 0, 500); 
         logMsg("JSON Parsing Failed ($jsonErr). Raw: " . $debugSnippet);
         throw new Exception("AI Response Invalid JSON ($jsonErr). Debug: " . htmlspecialchars($debugSnippet));
    }



    // 6. Score & Audit
    $title = $result['title'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    $meta_title = $result['meta_title'] ?? $title;
    $meta_description = $result['meta_description'] ?? '';

    $audit = calculateSeoScoreLocal($title, $slug, $meta_title, $meta_description, $kw['keyword']);
    $finalScore = $audit['score'];
    $auditLogJSON = json_encode(['critique' => $audit['critique']]);
    
    logMsg("Generated: $title (SEO Score: $finalScore)");

    // --- SELF-LEARNING: GENERATE NEW RULE (If Score < 100) ---
    if ($finalScore < 100) {
        logMsg("Score < 100 (" . $audit['critique'] . "). Generating new rule...");
        try {
            $critique = $audit['critique'];
            $rulePrompt = "Based on this SEO error critique: \"$critique\". Create ONE short, strict rule (max 15 words) for an AI writer to prevent this mistake in the future. Example: 'Always include the keyword in the first sentence.'";
            
            // Use Failover (uses active provider's model)
            $ruleContent = generateContentWithFailover($rulePrompt, null, $settings);
            $ruleContent = trim(str_replace(['"', "'", "*"], '', $ruleContent)); // Clean up
            
            // Save Rule
            $pdo->prepare("INSERT INTO ai_learning_rules (rule_content, triggered_by_keyword) VALUES (?, ?)")
                ->execute([$ruleContent, $kw['keyword']]);
                
            logMsg("New Rule Learned: $ruleContent");
        } catch (Exception $ruleEx) {
            logMsg("Failed to generate rule: " . $ruleEx->getMessage());
        }
    } else {
        // Optional: Increase hit_count for successful rules? 
        // For now, let's just keep it simple.
    }

    // 7. Save Article
    $content = $result['content'];
    $status = ($settings['ai_auto_publish'] ?? '1') == '1' ? 'published' : 'draft';
    
    // Auto-save keywords as focus_keyword
    $focusKw = $title;
    $aiSource = 'AI (' . ucfirst($settings['ai_active_provider'] ?? 'System') . ')';

    $stmt = $pdo->prepare("INSERT INTO articles (title, slug, content, status, created_at, seo_title, seo_description, seo_score, seo_audit_log, focus_keyword, content_source) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $title, $slug, $content, $status, $meta_title, $meta_description,
        $finalScore, $auditLogJSON, $focusKw, $aiSource
    ]);
    $articleId = $pdo->lastInsertId();

    // 8. Tags & Images (Standard)
    if (!empty($result['tags'])) {
        $tags = $result['tags'];
        // Strict Validation: Max 15 tags
        if (count($tags) > 15) {
            $tags = array_slice($tags, 0, 15);
        }

        foreach ($tags as $tagName) {
             $tagName = trim($tagName);
             if (empty($tagName)) continue;

             // Strict Validation: Max 5 words
             if (str_word_count($tagName) > 5) continue;

             $tagIdStmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
             $tagIdStmt->execute([$tagName]);
             $tId = $tagIdStmt->fetchColumn();
             if (!$tId) {
                 $tslug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $tagName)));
                 $pdo->prepare("INSERT INTO tags (name, slug, created_at) VALUES (?, ?, NOW())")->execute([$tagName, $tslug]);
                 $tId = $pdo->lastInsertId();
             }
             $pdo->prepare("INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (?, ?)")->execute([$articleId, $tId]);
        }
    }

    $imagePath = '';
    if (($settings['ai_generate_image'] ?? '0') == '1') {
         try {
            $uploadDir = __DIR__ . '/../../uploads/articles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $imgGenerated = false;
            
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $imgGenerated = false;

            // --- IMAGE GENERATION STRATEGY (DYNAMIC) ---
            $prioJson = $settings['ai_image_priority'] ?? '["pollinations","huggingface","pexels","google"]';
            $providers = json_decode($prioJson, true);
            if (!is_array($providers)) $providers = ['pollinations','huggingface','pexels','google'];

            $savePath = '';
            $activeImageSource = null;

            // --- SMART IMAGE PROMPT (Gemini) ---
            // --- SMART IMAGE PROMPT (Gemini) ---
            $imagePrompt = $title; // Default fallback
            try {
                $keepPeople = ($settings['ai_image_keep_people'] ?? '1') == '1';
                $peopleInstruction = $keepPeople 
                    ? "Include people or living beings if relevant." 
                    : "STRICTLY FORBIDDEN: Do NOT include any people, humans, faces, body parts, or animals. Focus purely on scenery, objects, architecture, or abstract concepts.";

                $promptGenOps = "Create a detailed, photorealistic, cinematic image prompt in English for an article titled: '$title'. $peopleInstruction Max 40 words. Output ONLY the raw prompt text, no quotes.";
                
                $smartPrompt = generateContentWithFailover($promptGenOps, null, $settings);
                if (!empty($smartPrompt) && strlen($smartPrompt) > 10) {
                    $imagePrompt = trim(str_replace(['"', "'", "*"], '', $smartPrompt));
                    
                    // Forcefully append negative constraint for providers
                    if (!$keepPeople) {
                         $imagePrompt .= ", no people, no humans, no face, no animals, empty scene";
                    }
                    
                    logMsg("Smart Image Prompt: $imagePrompt");
                }
            } catch (Exception $ePrompt) {
                logMsg("Smart Prompt Failed, using title. Error: " . $ePrompt->getMessage());
            }

            foreach ($providers as $provider) {
                if ($imgGenerated) break; // Stop if already successful

                try {
                    switch ($provider) {
                        case 'pollinations':
                            // Pollinations.ai (Free, Fast, No Key)
                            $seed = rand(100,999);
                            $pUrl = "https://image.pollinations.ai/prompt/" . urlencode($imagePrompt) . "?model=flux&width=1280&height=720&seed=$seed&nologo=true";
                            $bin = @file_get_contents($pUrl);
                            if ($bin && strlen($bin) > 1000) {
                                // Check Rate Limit
                                if (md5($bin) === '15c7a9d4c45dc7d9d4b16d46f68c87fa') {
                                    logMsg("Pollinations Rate Limit Hit. Trying next provider...");
                                } else {
                                    $fname = 'ai_poll_' . time() . uniqid() . '.jpg';
                                    file_put_contents($uploadDir . $fname, $bin);
                                    processImageToWebp($uploadDir . $fname, $uploadDir . str_replace('.jpg','.webp',$fname), 1200, 80);
                                    $imagePath = 'uploads/articles/' . str_replace('.jpg','.webp',$fname);
                                    @unlink($uploadDir . $fname);
                                    $imgGenerated = true;
                                    $activeImageSource = 'Pollinations';
                                }
                            }
                            break;

                        case 'huggingface':
                            // Hugging Face (Backup 1)
                            $hfToken = $settings['ai_huggingface_token'] ?? '';
                            if (!empty($hfToken)) {
                                $hfModel = "stabilityai/stable-diffusion-xl-base-1.0"; 
                                $hfUrl = "https://api-inference.huggingface.co/models/$hfModel";
                                $ch = curl_init($hfUrl);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $hfToken", "Content-Type: application/json"]);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['inputs' => $imagePrompt]));
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                $hfBin = curl_exec($ch);
                                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                curl_close($ch);

                                if ($httpCode == 200 && strlen($hfBin) > 1000) {
                                    // Check JSON error response
                                    $jsonCheck = json_decode($hfBin, true);
                                    if (!isset($jsonCheck['error'])) {
                                        $fname = 'ai_hf_' . time() . uniqid() . '.jpg';
                                        file_put_contents($uploadDir . $fname, $hfBin);
                                        processImageToWebp($uploadDir . $fname, $uploadDir . str_replace('.jpg','.webp',$fname), 1200, 80);
                                        $imagePath = 'uploads/articles/' . str_replace('.jpg','.webp',$fname);
                                        @unlink($uploadDir . $fname);
                                        $imgGenerated = true;
                                        $activeImageSource = 'HuggingFace';
                                    }
                                }
                            }
                            break;

                        case 'pexels':
                            // Pexels (Backup 2 - Real Photos)
                            $pexelsKey = $settings['ai_pexels_key'] ?? '';
                            if (!empty($pexelsKey)) {
                                // Extract a short English keyword for better Pexels search results
                                $pexelsSearchTerm = "cleaning service"; // default safe fallback
                                try {
                                    $pexPrompt = "Extract 1 or 2 main English keywords from this title for a stock photo search. Title: '$title'. Output ONLY the English keywords, no quotes, no extra words. Example: 'sofa cleaning' or 'laundry'.";
                                    $pkw = generateContentWithFailover($pexPrompt, null, $settings);
                                    if (!empty($pkw) && strlen($pkw) < 40) {
                                        $pexelsSearchTerm = trim(str_replace(['"', "'", "*"], '', $pkw));
                                    }
                                } catch (Exception $e) { /* ignore and use fallback */ }
                                
                                logMsg("Pexels Search Term: $pexelsSearchTerm (from title: $title)");
                                $query = urlencode($pexelsSearchTerm);
                                $pexUrl = "https://api.pexels.com/v1/search?query=$query&per_page=1&orientation=landscape";
                                $ch = curl_init($pexUrl);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: $pexelsKey"]);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                $pexRes = curl_exec($ch);
                                curl_close($ch);
                                $pexData = json_decode($pexRes, true);
                                
                                $photoUrl = $pexData['photos'][0]['src']['landscape'] ?? null;
                                if ($photoUrl) {
                                    $bin = @file_get_contents($photoUrl);
                                    if ($bin) {
                                        $fname = 'stock_px_' . time() . uniqid() . '.jpg';
                                        file_put_contents($uploadDir . $fname, $bin);
                                        processImageToWebp($uploadDir . $fname, $uploadDir . str_replace('.jpg','.webp',$fname), 1200, 80);
                                        $imagePath = 'uploads/articles/' . str_replace('.jpg','.webp',$fname);
                                        @unlink($uploadDir . $fname);
                                        $imgGenerated = true;
                                        $activeImageSource = 'Pexels';
                                    }
                                }
                            }
                            break;

                        case 'google':
                            // Google Imagen
                            try {
                                $imgModel = 'imagen-3.0-generate-001'; 
                                $imgUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$imgModel}:generateContent?key=" . $apiKey;
                                $imgPayload = ['contents' => [['parts' => [['text' => "$imagePrompt, 16:9, high resolution."]]]]];
                                
                                $chImg = curl_init($imgUrl);
                                curl_setopt($chImg, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($chImg, CURLOPT_POST, true);
                                curl_setopt($chImg, CURLOPT_POSTFIELDS, json_encode($imgPayload));
                                curl_setopt($chImg, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                                curl_setopt($chImg, CURLOPT_SSL_VERIFYPEER, false);
                                $imgRes = curl_exec($chImg);
                                curl_close($chImg);
                                
                                $imgDataJson = json_decode($imgRes, true);
                                $b64 = $imgDataJson['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
                                if ($b64) {
                                     $bin = base64_decode($b64);
                                     $fname = 'img_' . time() . uniqid() . '.webp';
                                     $tmp = tempnam(sys_get_temp_dir(), 'img');
                                     file_put_contents($tmp, $bin);
                                     processImageToWebp($tmp, $uploadDir . $fname, 1200, 80);
                                     $imagePath = 'uploads/articles/' . $fname;
                                     $imgGenerated = true;
                                     $activeImageSource = 'Google Imagen';
                                     unlink($tmp);
                                }
                            } catch (Exception $ex) {}
                            break;
                    }
                } catch (Exception $e) {
                    logMsg("Provider $provider failed: " . $e->getMessage());
                }
            }

            if ($imgGenerated) $pdo->prepare("UPDATE articles SET image_path = ?, image_source = ? WHERE id = ?")->execute([$imagePath, $activeImageSource, $articleId]);
        } catch (Exception $e) { /* Log Image Error */ }
    }

    $pdo->prepare("UPDATE auto_content_keywords SET status = 'done', article_id = ?, processed_at = NOW() WHERE id = ?")->execute([$articleId, $kw['id']]);

    // --- TELEGRAM NOTIFICATION STAGE ---
    $tgEnabled = ($settings['tg_notify_enabled'] ?? '0') == '1';
    $tgToken = $settings['tg_bot_token'] ?? '';
    $tgChatId = $settings['tg_chat_id'] ?? '';
    
    if ($tgEnabled && !empty($tgToken) && !empty($tgChatId)) {
        $siteUrl = rtrim($settings['tg_site_url'] ?? 'http://localhost', '/');
        $articleUrl = $siteUrl . "/article.php?slug=" . $slug;
        $editUrl = $siteUrl . "/admin/write.php?id=" . $articleId;
        
        $msg = "🤖 <b>New Content Generated!</b>\n\n";
        $msg .= "📝 <b>$title</b>\n";
        $msg .= "📊 SEO Score: <b>$finalScore</b>/100\n";
        $msg .= "📂 Tag: " . (implode(', ', $result['tags'] ?? ['-'])) . "\n";
        $msg .= "🔗 <a href='$articleUrl'>Baca Artikel</a> | <a href='$editUrl'>Edit Admin</a>\n";
        
        if ($finalScore < 70) $msg .= "\n⚠️ <i>SEO Score Low. Need manual review.</i>";
        
        // Helper function is in auth.php/functions.php, which is already required
        if (function_exists('sendTelegram')) {
            sendTelegram($tgChatId, $msg, $tgToken);
        }
    }

    $msg = "Article Created. Score: $finalScore. ($title)";
    logMsg($msg);
    if (!defined('IS_CRON')) echo json_encode(['status' => 'success', 'message' => $msg]);

} catch (Exception $e) {
    if (isset($kw['id'])) {
         $pdo->prepare("UPDATE auto_content_keywords SET status = 'failed', error_message = ?, processed_at = NOW() WHERE id = ?")->execute([$e->getMessage(), $kw['id']]);
    }
    logMsg("ERROR: " . $e->getMessage());
    if (!defined('IS_CRON')) echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
