<?php
// test_image_gen.php
require_once 'db.php';
require_once 'admin/includes/functions.php';

// Check API Key
$settings = $pdo->query("SELECT setting_value FROM auto_content_settings WHERE setting_key = 'ai_api_key'")->fetchColumn();
$apiKey = $settings ?: '';

echo "<h3>Testing Image Generation...</h3>";

if (empty($apiKey)) {
    echo "❌ API Key belum diset di database.<br>";
} else {
    echo "✅ API Key found.<br>";
}

$prompt = "Luxury leather sofa in a modern living room, cinematic lighting, 8k resolution";
echo "Prompt: <i>$prompt</i><hr>";

// 1. Test Google Imagen
echo "<b>1. Testing Google Imagen (via Gemini API)...</b><br>";
try {
    $imgModel = 'imagen-3.0-generate-001'; 
    $imgUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$imgModel}:generateContent?key=" . $apiKey;
    $imgPayload = ['contents' => [['parts' => [['text' => $prompt]]]]];
    
    $chImg = curl_init($imgUrl);
    curl_setopt($chImg, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chImg, CURLOPT_POST, true);
    curl_setopt($chImg, CURLOPT_POSTFIELDS, json_encode($imgPayload));
    curl_setopt($chImg, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($chImg, CURLOPT_SSL_VERIFYPEER, false);
    $imgRes = curl_exec($chImg);
    curl_close($chImg);
    
    $json = json_decode($imgRes, true);
    if (isset($json['error'])) {
        echo "❌ Error: " . ($json['error']['message'] ?? 'Unknown') . "<br>";
    } elseif (isset($json['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
        echo "✅ Success! Image generated.<br>";
        $b64 = $json['candidates'][0]['content']['parts'][0]['inlineData']['data'];
        echo '<img src="data:image/jpeg;base64,'.$b64.'" width="300"><br>';
    } else {
        echo "⚠️ No image data returned. Raw: " . substr($imgRes, 0, 100) . "...<br>";
    }

} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

echo "<hr><b>2. Testing Pollinations.ai (Free Fallback)...</b><br>";
try {
    $seed = rand(100,999);
    $pUrl = "https://image.pollinations.ai/prompt/" . urlencode($prompt) . "?model=flux&width=1280&height=720&seed=$seed&nologo=true";
    echo "URL: <a href='$pUrl' target='_blank'>$pUrl</a><br>";
    
    $bin = @file_get_contents($pUrl);
    if ($bin && strlen($bin) > 1000) {
        echo "✅ Success! Image fetched from Pollinations.<br>";
        $b64 = base64_encode($bin);
        echo '<img src="data:image/jpeg;base64,'.$b64.'" width="300"><br>';
    } else {
        echo "❌ Failed to fetch from Pollinations.<br>";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}
?>
