<?php
/**
 * API: Call next ticket for window
 * POST /api/dequeue.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../classes/MultiWindowPriorityQueue.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Verify cashier is logged in
if (!isset($_SESSION['cashier_id']) || !isset($_SESSION['window_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$windowId = $_SESSION['window_id'];

try {
    $queue = new MultiWindowPriorityQueue($pdo);
    $ticket = $queue->dequeue($windowId);
    
    if ($ticket) {
        // Generate announcement
        require_once __DIR__ . '/../classes/TicketGenerator.php';
        $announcement = TicketGenerator::generateAnnouncement(
            $ticket['ticket_id'], 
            $windowId
        );
        
        echo json_encode([
            'success' => true,
            'ticket' => $ticket,
            'announcement' => $announcement,
            'window_id' => $windowId
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'ticket' => null,
            'message' => 'No tickets available for this window'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Dequeue error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve ticket']);
}
?>
