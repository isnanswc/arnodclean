<?php
// admin/api/save_worker_content.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
checkLogin();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) throw new Exception("Invalid data received.");

    $keyword_id = $data['keyword_id'] ?? null;
    $title = $data['title'] ?? '';
    $content = $data['content'] ?? '';
    $tags_input = $data['tags'] ?? [];
    $meta_title = $data['meta_title'] ?? $title;
    $meta_description = $data['meta_description'] ?? '';
    $image_base64 = $data['image_base64'] ?? null;
    $status = $data['status'] ?? 'published';

    if (!$keyword_id || !$title || !$content) {
        throw new Exception("Missing required fields (ID, Title, or Content).");
    }

    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

    // Handle Image if exists
    $image_path = null;
    if ($image_base64 && strpos($image_base64, 'data:image') === 0) {
        $imgData = explode(',', $image_base64);
        $decoded = base64_decode($imgData[1]);
        
        $uploadDir = __DIR__ . '/../../uploads/articles/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $filename = 'ai_img_' . time() . '_' . uniqid() . '.webp';
        $fullPath = $uploadDir . $filename;
        
        // Use a temporary file to process
        $tmpFile = tempnam(sys_get_temp_dir(), 'ai_');
        file_put_contents($tmpFile, $decoded);
        
        try {
            processImageToWebp($tmpFile, $fullPath, 1200, 80);
            $image_path = 'uploads/articles/' . $filename;
        } catch (Exception $e) {
            // Fallback: if optimization fails, save as is (but try to keep .png if it was png)
            $filename = 'ai_img_' . time() . '_' . uniqid() . '.png';
            file_put_contents(__DIR__ . '/../../uploads/articles/' . $filename, $decoded);
            $image_path = 'uploads/articles/' . $filename;
        }
        unlink($tmpFile);
    }

    // Insert Article
    $stmt = $pdo->prepare("INSERT INTO articles (title, slug, content, image_path, status, seo_title, seo_description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $slug, $content, $image_path, $status, $meta_title, $meta_description]);
    $article_id = $pdo->lastInsertId();

    // Handle Tags
    if (!empty($tags_input)) {
        foreach ($tags_input as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) continue;

            // Find or Create Tag
            $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
            $stmt->execute([$tagName]);
            $tagId = $stmt->fetchColumn();

            if (!$tagId) {
                $stmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
                $stmt->execute([$tagName]);
                $tagId = $pdo->lastInsertId();
            }

            // Link to Article
            $stmt = $pdo->prepare("INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (?, ?)");
            $stmt->execute([$article_id, $tagId]);
        }
    }

    // Mark Keyword as Done
    $pdo->prepare("UPDATE auto_content_keywords SET status = 'done', article_id = ?, processed_at = NOW() WHERE id = ?")
        ->execute([$article_id, $keyword_id]);

    echo json_encode(['status' => 'success', 'article_id' => $article_id]);

} catch (Exception $e) {
    if (isset($keyword_id)) {
        $pdo->prepare("UPDATE auto_content_keywords SET status = 'failed', error_message = ? WHERE id = ?")
            ->execute([$e->getMessage(), $keyword_id]);
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
