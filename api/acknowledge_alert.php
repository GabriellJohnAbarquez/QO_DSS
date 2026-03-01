<?php
/**
 * API: Acknowledge DSS alert
 * POST /api/acknowledge_alert.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../includes/auth_check.php';

$input = json_decode(file_get_contents('php://input'), true);
$alertId = $input['alert_id'] ?? null;

if (!$alertId) {
    http_response_code(400);
    echo json_encode(['error' => 'Alert ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE tbl_dss_alerts 
        SET is_acknowledged = 1, 
            acknowledged_by = ?,
            acknowledged_at = NOW()
        WHERE alert_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'] ?? 'UNKNOWN', $alertId]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to acknowledge']);
}
?>
