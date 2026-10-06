<?php
// admin/api/update_order.php
header('Content-Type: application/json');
require_once '../../db.php';
require_once '../includes/auth.php';

// Check auth
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

try {
    // Expect JSON body: { "order": [id1, id2, id3, ...] }
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['order']) || !is_array($input['order'])) {
        throw new Exception("Invalid input format");
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE auto_content_keywords SET process_order = ? WHERE id = ?");
    
    // Update order based on the index in the array
    foreach ($input['order'] as $index => $id) {
        // Enforce integer for security
        $id = (int)$id;
        $order = $index + 1; // Start from 1
        $stmt->execute([$order, $id]);
    }

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'Order updated successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
