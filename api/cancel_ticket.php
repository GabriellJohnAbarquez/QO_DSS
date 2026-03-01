<?php
/**
 * API: Cancel or no-show ticket
 * POST /api/cancel_ticket.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../includes/auth_check.php';

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = $input['ticket_id'] ?? null;
$reason = $input['reason'] ?? 'No-show';

if (!$ticketId) {
    http_response_code(400);
    echo json_encode(['error' => 'Ticket ID required']);
    exit;
}

try {
    // Update ticket status
    $stmt = $pdo->prepare("
        UPDATE tbl_tickets 
        SET status = 'NoShow', 
            updated_at = NOW(),
            notes = CONCAT(IFNULL(notes,''), ' | Cancelled: ', ?)
        WHERE ticket_id = ?
    ");
    $stmt->execute([$reason, $ticketId]);
    
    // Clean up window queues
    $pdo->prepare("DELETE FROM tbl_window_queues WHERE ticket_id = ?")
        ->execute([$ticketId]);
    
    // Log action
    $pdo->prepare("
        INSERT INTO tbl_logs (ticket_id, action_type, details, performed_by)
        VALUES (?, 'CANCELLED', ?, ?)
    ")->execute([$ticketId, $reason, $_SESSION['user_id'] ?? 'SYSTEM']);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to cancel ticket']);
}
?>
