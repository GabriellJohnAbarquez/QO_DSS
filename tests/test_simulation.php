<?php
/**
 * System Simulation Tests
 * Implements dry run methodology from thesis
 */

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../classes/MultiWindowPriorityQueue.php';

header('Content-Type: application/json');

$scenario = $_GET['scenario'] ?? 'logical';
$queue = new MultiWindowPriorityQueue($pdo);

switch ($scenario) {
    case 'logical':
        // Phase 1: Logical Verification
        $result = runLogicalVerification($queue, $pdo);
        break;
        
    case 'stress':
        // Phase 2: Stress Test
        $result = runStressTest($queue, $pdo);
        break;
        
    case 'dss':
        // Phase 3: DSS Calibration
        $result = runDSSCalibration($queue, $pdo);
        break;
        
    default:
        $result = ['error' => 'Unknown scenario'];
}

echo json_encode($result);

// ============ TEST FUNCTIONS ============

function runLogicalVerification($queue, $pdo) {
    $pdo->beginTransaction(); // Rollback after test
    
    $testData = [
        ['tier' => 'P0', 'category' => 'Senior', 'count' => 2],
        ['tier' => 'P1', 'type' => 'Full Payment', 'count' => 10],
        ['tier' => 'P2', 'type' => 'Down Payment', 'count' => 8],
        ['tier' => 'P3', 'type' => 'Installment', 'count' => 15],
        ['tier' => 'P4', 'type' => 'Documents', 'count' => 15]
    ];
    
    $tickets = [];
    foreach ($testData as $test) {
        for ($i = 0; $i < $test['count']; $i++) {
            $data = [
                'transaction_type' => $test['type'] ?? 'General',
                'client_type' => 'Student',
                'client_category' => $test['category'] ?? 'General'
            ];
            
            $result = $queue->enqueue($data);
            if ($result['success']) {
                $tickets[] = $result;
            }
        }
    }
    
    // Verify tier ordering in windows
    $windows = [1, 2, 3];
    $orderingCorrect = true;
    
    foreach ($windows as $windowId) {
        $windowTickets = $pdo->query("
            SELECT t.ticket_id, t.priority_level
            FROM tbl_tickets t
            JOIN tbl_window_queues wq ON t.ticket_id = wq.ticket_id
            WHERE wq.window_id = $windowId AND t.status = 'Waiting'
            ORDER BY wq.added_to_queue
        ")->fetchAll();
        
        $lastPriority = 999;
        foreach ($windowTickets as $t) {
            if ($t['priority_level'] > $lastPriority) {
                $orderingCorrect = false;
                break 2;
            }
            $lastPriority = $t['priority_level'];
        }
    }
    
    $pdo->rollBack();
    
    return [
        'scenario' => 'logical_verification',
        'passed' => $orderingCorrect && count($tickets) === 50,
        'tickets_created' => count($tickets),
        'ordering_correct' => $orderingCorrect,
        'details' => [
            'by_tier' => array_count_values(array_column($tickets, 'tier'))
        ]
    ];
}

function runStressTest($queue, $pdo) {
    $pdo->beginTransaction();
    
    $startTime = microtime(true);
    
    // Rapid injection: 100 tickets
    for ($i = 0; $i < 100; $i++) {
        $queue->enqueue([
            'transaction_type' => 'Full Payment',
            'client_type' => 'Student'
        ]);
    }
    
    $injectionTime = microtime(true) - $startTime;
    
    // Test response time
    $ajaxStart = microtime(true);
    $status = $queue->getMetrics();
    $responseTime = microtime(true) - $ajaxStart;
    
    $pdo->rollBack();
    
    return [
        'scenario' => 'stress_test',
        'passed' => $responseTime < 0.5 && $injectionTime < 30,
        'injection_time_seconds' => round($injectionTime, 2),
        'response_time_seconds' => round($responseTime, 3),
        'tickets_per_second' => round(100 / $injectionTime, 2)
    ];
}

function runDSSCalibration($queue, $pdo) {
    $pdo->beginTransaction();
    
    // Create bottleneck: 60 tickets, only 1 window "active"
    for ($i = 0; $i < 60; $i++) {
        $queue->enqueue([
            'transaction_type' => 'Installment',
            'client_type' => 'Student'
        ]);
    }
    
    // Artificially set wait times
    $pdo->exec("
        UPDATE tbl_tickets 
        SET arrival_timestamp = DATE_SUB(NOW(), INTERVAL 20 MINUTE)
        WHERE status = 'Waiting'
    ");
    
    // Process aging
    $promotions = $queue->processAging();
    
    // Check if DSS alerts generated
    $alerts = $queue->generateDSSAlerts();
    $bottleneckDetected = false;
    
    foreach ($alerts as $alert) {
        if ($alert['type'] === 'PEAK_SUGGESTION' || $alert['type'] === 'WAIT_TIME') {
            $bottleneckDetected = true;
            break;
        }
    }
    
    $pdo->rollBack();
    
    return [
        'scenario' => 'dss_calibration',
        'passed' => $bottleneckDetected && count($promotions) > 0,
        'promotions_triggered' => count($promotions),
        'alerts_generated' => count($alerts),
        'bottleneck_detected' => $bottleneckDetected
    ];
}
?>
