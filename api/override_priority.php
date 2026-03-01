<?php
/**
 * API: Manual priority override
 * POST /api/override_priority.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../classes/MultiWindowPriorityQueue.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Only admin or supervisor can override
if (!in_array($_SESSION['role'] ?? '', ['admin', 'supervisor', 'head_cashier'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient privileges']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = $input['ticket_id'] ?? null;
$newTier = $input['new_tier'] ?? null;
$reason = $input['reason'] ?? '';

if (!$ticketId || !$newTier) {
    http_response_code(400);
    echo json_encode(['error' => 'Ticket ID and new tier required']);
    exit;
}

try {
    $queue = new MultiWindowPriorityQueue($pdo);
    $success = $queue->overridePriority(
        $ticketId, 
        $newTier, 
        $reason, 
        $_SESSION['user_id'] ?? 'UNKNOWN'
    );
    
    echo json_encode(['success' => $success]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Override failed']);
}
?>
