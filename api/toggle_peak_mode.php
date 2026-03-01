<?php
/**
 * API: Toggle peak mode
 * POST /api/toggle_peak_mode.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../classes/MultiWindowPriorityQueue.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'head_cashier'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$enable = $input['enable'] ?? false;

try {
    $queue = new MultiWindowPriorityQueue($pdo);
    $newMode = $queue->setPeakMode($enable);
    
    echo json_encode([
        'success' => true,
        'mode' => $newMode,
        'message' => "System switched to $newMode mode"
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to toggle mode']);
}
?>
