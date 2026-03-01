<?php
/**
 * API: Get status for TV display
 * GET /api/tv_status.php
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../dbconfig.php';

try {
    // Get all windows and their current status
    $windows = [];
    $windowData = $pdo->query("
        SELECT DISTINCT window_id FROM tbl_window_queues 
        WHERE DATE(added_to_queue) = CURDATE()
        UNION 
        SELECT DISTINCT assigned_window FROM tbl_tickets 
        WHERE status = 'Serving' AND DATE(service_start_time) = CURDATE()
        ORDER BY window_id
    ")->fetchAll();
    
    foreach ($windowData as $win) {
        $windowId = $win['window_id'];
        
        // Currently serving
        $serving = $pdo->prepare("
            SELECT ticket_id, transaction_category 
            FROM tbl_tickets 
            WHERE assigned_window = ? AND status = 'Serving'
        ")->execute([$windowId])->fetch();
        
        // Next 3 in queue
        $next = $pdo->prepare("
            SELECT t.ticket_id
            FROM tbl_tickets t
            INNER JOIN tbl_window_queues wq ON t.ticket_id = wq.ticket_id
            WHERE wq.window_id = ? AND t.status = 'Waiting'
            ORDER BY t.priority_level DESC, t.arrival_timestamp ASC
            LIMIT 3
        ")->execute([$windowId])->fetchAll(PDO::FETCH_COLUMN);
        
        $windows[] = [
            'id' => $windowId,
            'current' => $serving['ticket_id'] ?? null,
            'current_tier' => $serving['transaction_category'] ?? null,
            'next' => $next,
            'status' => $serving ? 'serving' : 'available'
        ];
    }
    
    // Overall stats
    $totalWaiting = $pdo->query("
        SELECT COUNT(*) FROM tbl_tickets WHERE status = 'Waiting'
    ")->fetchColumn();
    
    $maxWait = $pdo->query("
        SELECT MAX(TIMESTAMPDIFF(MINUTE, arrival_timestamp, NOW()))
        FROM tbl_tickets WHERE status = 'Waiting'
    ")->fetchColumn() ?? 0;
    
    echo json_encode([
        'windows' => $windows,
        'total_waiting' => $totalWaiting,
        'max_wait' => $maxWait,
        'timestamp' => date('Y-m-d H:i:s'),
        'mode' => $pdo->query("SELECT current_mode FROM tbl_settings WHERE setting_id = 1")
                    ->fetchColumn()
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve status']);
}
?>
