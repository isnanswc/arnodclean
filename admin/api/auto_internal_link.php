<?php
// admin/api/auto_internal_link.php
header('Content-Type: application/json');
require_once '../../db.php';
require_once '../includes/auth.php';

// Ensure user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$id = $_POST['id'] ?? '';
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ID Required']);
    exit;
}

try {
    // 1. Get Current Article
    $stmt = $pdo->prepare("SELECT id, title, content, slug FROM articles WHERE id = ?");
    $stmt->execute([$id]);
    $article = $stmt->fetch();

    if (!$article) {
        throw new Exception("Article not found");
    }

    // 2. Count existing links (Simple check to respect "limit 5" roughly, 
    // but user said "add internal link... limit 5". We will strictly add 5 NEW links or up to 5.)
    // Logic: Just append a block of 5 links.
    
    // 3. Find 5 Candidates (Prioritize Orphans/Low Incoming Links)
    // We count how many times each potential candidate's slug appears in other articles (incoming links).
    // Sort by incoming_count ASC (orphans first), then RAND().
    
    $linksNeeded = 5;

    $stmtLinks = $pdo->prepare("
        SELECT id, title, slug, 
        (SELECT COUNT(*) FROM articles a2 WHERE a2.content LIKE CONCAT('%', a1.slug, '%') AND a2.id != a1.id) as incoming_map
        FROM articles a1 
        WHERE id != ? AND status = 'published' 
        ORDER BY incoming_map ASC, RAND() 
        LIMIT ?
    ");
    $stmtLinks->bindValue(1, $id, PDO::PARAM_INT);
    $stmtLinks->bindValue(2, $linksNeeded, PDO::PARAM_INT);
    $stmtLinks->execute();
    $candidates = $stmtLinks->fetchAll();

    if (count($candidates) === 0) {
        throw new Exception("No other articles found to link to.");
    }

    // 4. Generate HTML
    $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $baseUrl = rtrim(dirname($_SERVER['PHP_SELF'], 3), '/\\'); // Go up to root (admin/api/ -> ../../)
    
    $html = "\n\n<div class='read-also-box' style='margin-top: 30px; padding: 20px; background: #f8f9fa; border-left: 4px solid #0d6efd;'>";
    $html .= "<h5 style='margin-bottom: 15px; font-weight: bold;'>Baca Juga:</h5>";
    $html .= "<ul style='margin-bottom: 0;'>";
    
    foreach ($candidates as $c) {
        $link = "$baseUrl/blog/" . $c['slug'];
        $html .= "<li><a href='$link' title='{$c['title']}'>{$c['title']}</a></li>";
    }
    
    $html .= "</ul></div>";

    // 5. Append to Content
    $newContent = $article['content'] . $html;
    
    // 6. Save
    $updateStmt = $pdo->prepare("UPDATE articles SET content = ? WHERE id = ?");
    $updateStmt->execute([$newContent, $id]);

    $addedCount = count($candidates);
    logActivity("Auto Link", "Added $addedCount internal links to article ID: $id");

    echo json_encode([
        'status' => 'success', 
        'message' => "Berhasil menambahkan $addedCount internal link.",
        'new_count' => substr_count(strtolower($newContent), '<a href')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
