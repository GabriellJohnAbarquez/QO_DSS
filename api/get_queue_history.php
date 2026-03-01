<?php
/**
 * API: Get historical queue data for charts
 * GET /api/get_queue_history.php?period=today|week|month
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../includes/auth_check.php';

$period = $_GET['period'] ?? 'today';

try {
    $data = [];
    
    if ($period === 'today') {
        // Hourly data for today
        $data = $pdo->query("
            SELECT 
                HOUR(arrival_timestamp) as hour,
                COUNT(*) as arrivals,
                AVG(TIMESTAMPDIFF(MINUTE, arrival_timestamp, service_start_time)) as avg_wait
            FROM tbl_tickets
            WHERE DATE(arrival_timestamp) = CURDATE()
            GROUP BY HOUR(arrival_timestamp)
            ORDER BY hour
        ")->fetchAll();
    } else {
        // Daily data for period
        $days = $period === 'week' ? 7 : 30;
        $data = $pdo->prepare("
            SELECT 
                DATE(arrival_timestamp) as date,
                COUNT(*) as arrivals,
                AVG(TIMESTAMPDIFF(MINUTE, arrival_timestamp, service_start_time)) as avg_wait
            FROM tbl_tickets
            WHERE arrival_timestamp >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(arrival_timestamp)
            ORDER BY date
        ")->execute([$days])->fetchAll();
    }
    
    echo json_encode(['success' => true, 'data' => $data, 'period' => $period]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve history']);
}
?>
