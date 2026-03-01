<?php
/**
 * API: Mark ticket as completed
 * POST /api/complete_ticket.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../classes/MultiWindowPriorityQueue.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (!isset($_SESSION['cashier_id']) || !isset($_SESSION['window_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = $input['ticket_id'] ?? null;

if (!$ticketId) {
    http_response_code(400);
    echo json_encode(['error' => 'Ticket ID required']);
    exit;
}

try {
    $queue = new MultiWindowPriorityQueue($pdo);
    $success = $queue->completeService($ticketId, $_SESSION['window_id']);
    
    echo json_encode(['success' => $success]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to complete ticket']);
}
?>
