<?php
/**
 * API: Get DSS metrics for admin dashboard
 * GET /api/dss_metrics.php?type=full|quick
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../classes/MetricsCalculator.php';
require_once __DIR__ . '/../classes/DSSAnalytics.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Admin only
if (!in_array($_SESSION['role'] ?? '', ['admin', 'head_cashier', 'supervisor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? 'full';

try {
    $metrics = new MetricsCalculator($pdo);
    $analytics = new DSSAnalytics($pdo);
    
    if ($type === 'quick') {
        // Quick stats for kiosk display
        $data = [
            'waiting' => $pdo->query("SELECT COUNT(*) FROM tbl_tickets WHERE status = 'Waiting'")->fetchColumn(),
            'avg_wait' => round($pdo->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, arrival_timestamp, NOW())) FROM tbl_tickets WHERE status = 'Waiting'")->fetchColumn() ?? 0, 1),
            'active_windows' => $pdo->query("SELECT COUNT(DISTINCT assigned_window) FROM tbl_tickets WHERE status = 'Serving'")->fetchColumn()
        ];
    } else {
        // Full metrics
        $data = [
            'metrics' => $metrics->getFullReport(),
            'forecast' => $analytics->generateForecast(),
            'recommendations' => $analytics->getStaffingRecommendations(),
            'bottlenecks' => $analytics->analyzeBottlenecks(),
            'alerts' => $pdo->query("SELECT * FROM tbl_dss_alerts WHERE is_acknowledged = 0 ORDER BY created_at DESC LIMIT 5")->fetchAll()
        ];
    }
    
    echo json_encode($data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve metrics']);
}
?>
