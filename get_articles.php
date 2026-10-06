<?php
// get_articles.php
header('Content-Type: application/json');
require_once 'db.php';

try {
    // 1. Input Validation & Sanitization
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 6;
    
    // Hard limit to prevent abuse
    if ($limit > 50) $limit = 50; 
    if ($page < 1) $page = 1;

    $search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
    // Trim and allow only safe characters for search if strictly needed, 
    // but PDO prepared statements handle SQL injection. 
    // We just trim for logic reasons.
    $search = trim($search);

    $tagId = filter_input(INPUT_GET, 'tag', FILTER_VALIDATE_INT);
    // If tag is not an ID but a name, we might need different logic. 
    // For now assuming we pass Tag ID. If we want to pass Tag Name, we'd change this.
    // Let's support Tag ID for precision.

    $offset = ($page - 1) * $limit;

    // 2. Build Query
    $params = [];
    $whereClauses = ["a.status = 'published'"]; // Default to published only

    if (!empty($search)) {
        // Updated Search: Title OR Content OR Tag Name
        $whereClauses[] = "(a.title LIKE ? OR a.content LIKE ? OR EXISTS (SELECT 1 FROM article_tags at_s JOIN tags t_s ON at_s.tag_id = t_s.id WHERE at_s.article_id = a.id AND t_s.name LIKE ?))";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%"; // For tag name
    }

    if (!empty($tagId)) {
        // We need to join article_tags for filtering
        // Note: The main query already does a LEFT JOIN, 
        // to filter WE MUST check if the article has the specific tag.
        // Efficient way: Exists or Join.
        // Let's add a condition on the joined table or subquery.
        $whereClauses[] = "EXISTS (SELECT 1 FROM article_tags at_filter WHERE at_filter.article_id = a.id AND at_filter.tag_id = ?)";
        $params[] = $tagId;
    }

    $whereSQL = implode(" AND ", $whereClauses);

    // 3. Count Total (for pagination)
    $countSql = "SELECT COUNT(*) FROM articles a WHERE $whereSQL";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();

    // 4. Fetch Data
    // We select GROUP_CONCAT(t.name) separately or logic remains similar to original blog.php
    $sql = "SELECT a.id, a.title, a.slug, a.image_path, a.created_at, LEFT(a.content, 600) as snippet,
                   GROUP_CONCAT(t.name) as tag_names,
                   GROUP_CONCAT(t.id) as tag_ids
            FROM articles a 
            LEFT JOIN article_tags at ON a.id = at.article_id 
            LEFT JOIN tags t ON at.tag_id = t.id 
            WHERE $whereSQL
            GROUP BY a.id 
            ORDER BY a.created_at DESC 
            LIMIT ? OFFSET ?";
    
    // Params for main query need to be appended with limit/offset
    // PDO limitations: LIMIT/OFFSET in some drivers rely on bindValue with INT type
    $stmt = $pdo->prepare($sql);
    
    // Bind all WHERE params first
    $paramIndex = 1;
    foreach ($params as $val) {
        $stmt->bindValue($paramIndex++, $val);
    }
    
    // Bind Limit and Offset as INT
    $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $articles = $stmt->fetchAll();

    // 5. Structure Response
    $response = [
        'status' => 'success',
        'pagination' => [
            'current_page' => (int)$page,
            'limit' => (int)$limit,
            'total_records' => (int)$totalRecords,
            'total_pages' => ceil($totalRecords / $limit)
        ],
        'data' => array_map(function($a) {
            // Further sanitization of output not strictly necessary for JSON 
            // as JSON is data, but good practice to ensure consistency.
            // We do NOT escape html here, frontend should do it to prevent XSS.
            // However, we can strip tags from snippet here to keep payload clean.
            // Ensure good snippet
            $rawContent = html_entity_decode($a['snippet']);
            $cleanContent = strip_tags($rawContent);
            // safe shorten
            $a['snippet'] = mb_substr($cleanContent, 0, 200);

            $a['image_path'] = $a['image_path'] ? $a['image_path'] : null; // Ensure null if empty
            return $a;
        }, $articles)
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error']);
    // Log error securely, don't expose DB errors to user in production
    error_log($e->getMessage()); 
}
?>
