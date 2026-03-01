<?php
/**
 * API: Get current status for cashier dashboard
 * GET /api/cashier_status.php?window=1
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../includes/auth_check.php';

$windowId = $_GET['window'] ?? ($_SESSION['window_id'] ?? null);

if (!$windowId) {
    http_response_code(400);
    echo json_encode(['error' => 'Window ID required']);
    exit;
}

try {
    // Current serving ticket
    $current = $pdo->prepare("
        SELECT t.*, 
               TIMESTAMPDIFF(MINUTE, t.service_start_time, NOW()) as serving_duration
        FROM tbl_tickets t
        WHERE t.assigned_window = ? AND t.status = 'Serving'
        LIMIT 1
    ")->execute([$windowId])->fetch();
    
    // Queue for this window
    $queue = $pdo->prepare("
        SELECT t.ticket_id, t.transaction_category, t.priority_level,
               t.client_type, t.client_category,
               TIMESTAMPDIFF(MINUTE, t.arrival_timestamp, NOW()) as waiting_minutes
        FROM tbl_tickets t
        INNER JOIN tbl_window_queues wq ON t.ticket_id = wq.ticket_id
        WHERE wq.window_id = ? 
        AND t.status = 'Waiting' 
        AND wq.is_eligible = 1
        ORDER BY t.priority_level DESC, t.arrival_timestamp ASC
        LIMIT 10
    ")->execute([$windowId])->fetchAll();
    
    // Recent alerts
    $alerts = $pdo->query("
        SELECT * FROM tbl_dss_alerts 
        WHERE is_acknowledged = 0 
        AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY created_at DESC
        LIMIT 3
    ")->fetchAll();
    
    // Window stats
    $stats = $pdo->prepare("
        SELECT 
            COUNT(*) as served_today,
            AVG(actual_service_time) as avg_service_time
        FROM tbl_tickets
        WHERE assigned_window = ?
        AND DATE(service_end_time) = CURDATE()
        AND status = 'Completed'
    ")->execute([$windowId])->fetch();
    
    echo json_encode([
        'window_id' => $windowId,
        'current_ticket' => $current,
        'queue' => $queue,
        'queue_count' => count($queue),
        'alerts' => $alerts,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
