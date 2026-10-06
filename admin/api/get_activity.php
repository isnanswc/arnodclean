<?php
// admin/api/get_activity.php
require_once '../../db.php';
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit;
}

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = 20;

$stmt = $pdo->prepare("
    (SELECT 'activity' as log_type, a.id, a.user_id, a.action, a.details, a.created_at, u.username, u.email, u.avatar 
    FROM activity_logs a 
    LEFT JOIN users u ON a.user_id = u.id)
    UNION ALL 
    (SELECT 'login' as log_type, l.id, l.user_id, 'Login' as action, CONCAT('IP: ', l.ip_address) as details, l.login_at as created_at, u.username, u.email, u.avatar
    FROM login_history l 
    LEFT JOIN users u ON l.user_id = u.id)
    ORDER BY created_at DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = '';
foreach($logs as $log) {
    $avatar = $log['avatar'] ?? ''; 
    $username = htmlspecialchars($log['username'] ?? 'Unknown');
    $action = htmlspecialchars($log['action']);
    $details = htmlspecialchars($log['details']);
    $date = date('d M H:i', strtotime($log['created_at']));
    
    $markerClass = ($log['log_type'] == 'login') ? 'border-success' : 'border-primary'; 
    $badgeClass = ($log['log_type'] == 'login') ? 'bg-success bg-opacity-10 text-success border-success' : 'bg-light text-secondary border';

    $avatarHtml = $avatar 
        ? "<img src='".htmlspecialchars($avatar)."' class='rounded-circle me-2' width='20' height='20'>"
        : "<i class='fa-solid fa-user-circle me-2 text-secondary'></i>";

    $html .= "
    <div class='timeline-item pb-4 ps-4 border-start border-2 border-primary position-relative field-activity'>
        <div class='timeline-marker bg-white border border-2 $markerClass rounded-circle position-absolute' style='width:12px; height:12px; left:-7px; top:0;'></div>
        
        <div class='d-flex justify-content-between align-items-start'>
            <div>
                <div class='fw-bold text-dark d-flex align-items-center'>
                    $avatarHtml
                    $username
                    <span class='badge $badgeClass ms-2 fw-normal' style='font-size: 0.7rem;'>$action</span>
                </div>
                <p class='mb-1 mt-1 text-muted small'>$details</p>
            </div>
            <small class='text-muted' style='font-size: 0.75rem;'>$date</small>
        </div>
    </div>";
}

echo $html;
?>
