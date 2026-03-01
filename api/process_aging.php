<?php
/**
 * API: Process aging promotions
 * GET/POST /api/process_aging.php
 * Should be called by cron job every minute
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../classes/MultiWindowPriorityQueue.php';

// Simple API key check for cron job
$apiKey = $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
$validKey = 'EAC_QODSS_CRON_2024'; // Change in production

if ($apiKey !== $validKey && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $queue = new MultiWindowPriorityQueue($pdo);
    $promotions = $queue->processAging();
    
    echo json_encode([
        'success' => true,
        'promotions_processed' => count($promotions),
        'promotions' => $promotions,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Aging process failed']);
}
?>
