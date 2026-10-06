<?php
header('Content-Type: application/json');
require_once '../../db.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

try {
    // 1. Average SEO Score (Published Only)
    $avgStmt = $pdo->query("SELECT AVG(seo_score) as avg_score, COUNT(*) as total FROM articles WHERE status='published'");
    $avgData = $avgStmt->fetch();
    $avgScore = $avgData['avg_score'] ? round($avgData['avg_score']) : 0;
    $totalPublished = $avgData['total'];

    // 2. Score Distribution
    $distStmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN seo_score >= 100 THEN 1 ELSE 0 END) as perfect,
            SUM(CASE WHEN seo_score >= 80 AND seo_score < 100 THEN 1 ELSE 0 END) as good,
            SUM(CASE WHEN seo_score >= 60 AND seo_score < 80 THEN 1 ELSE 0 END) as warning,
            SUM(CASE WHEN seo_score < 60 THEN 1 ELSE 0 END) as critical
        FROM articles WHERE status='published'
    ");
    $distRaw = $distStmt->fetch(PDO::FETCH_ASSOC);
    $distribution = [
        'labels' => ['Perfect (100)', 'Good (80-99)', 'Warning (60-79)', 'Critical (<60)'],
        'data' => [
            (int)$distRaw['perfect'], 
            (int)$distRaw['good'], 
            (int)$distRaw['warning'], 
            (int)$distRaw['critical']
        ],
        'colors' => ['#198754', '#0dcaf0', '#ffc107', '#dc3545'] // Bootstrap success, info, warning, danger
    ];

    // 3. Needs Improvement (Bottom 5 by Score)
    $worstStmt = $pdo->query("SELECT id, title, seo_score, views FROM articles WHERE status='published' ORDER BY seo_score ASC LIMIT 5");
    $worstArticles = $worstStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Common Issues (Simple aggregation from audit log ?)
    // Parsing text JSON in SQL is hard, so we skip for now or do simple %LIKE% counts if needed.
    
    echo json_encode([
        'status' => 'success',
        'health' => [
            'score' => $avgScore,
            'total_articles' => $totalPublished
        ],
        'distribution' => $distribution,
        'worst_articles' => $worstArticles
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
