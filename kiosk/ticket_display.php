<?php
/**
 * Step 3: Display Generated Ticket
 */

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../classes/MultiWindowPriorityQueue.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// Prepare data
$data = [
    'transaction_type' => $_POST['tier'] === 'P1' ? 'Full Payment' : 
                         ($_POST['tier'] === 'P2' ? 'Down Payment' : 'General'),
    'client_type' => $_POST['client_type'],
    'client_category' => $_POST['client_category'],
    'full_name' => $_POST['full_name'],
    'student_id' => $_POST['student_id'] ?? null,
    'contact' => $_POST['contact'] ?? null,
    'exact_cash' => isset($_POST['exact_cash']),
    'details' => [
        'name' => $_POST['full_name'],
        'student_id' => $_POST['student_id'] ?? null,
        'contact' => $_POST['contact'] ?? null,
        'notes' => $_POST['transaction_details'] ?? ''
    ]
];

// Generate ticket
$queue = new MultiWindowPriorityQueue($pdo);
$result = $queue->enqueue($data);

if (!$result['success']) {
    die("Error generating ticket. Please try again.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Your Ticket - QO-DSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ticket-card {
            background: white;
            border-radius: 30px;
            padding: 60px;
            text-align: center;
            box-shadow: 0 30px 80px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
        }
        .ticket-number {
            font-size: 4rem;
            font-weight: bold;
            color: #333;
            margin: 30px 0;
            letter-spacing: 5px;
        }
        .qr-code {
            width: 200px;
            height: 200px;
            margin: 20px auto;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label { color: #666; }
        .info-value { font-weight: bold; color: #333; }
    </style>
</head>
<body>
    <div class="ticket-card">
        <h3 class="text-muted mb-2">Emilio Aguinaldo College</h3>
        <p class="text-muted">Cashier's Office Queue Ticket</p>
        
        <div class="ticket-number"><?= $result['ticket_id'] ?></div>
        
        <div class="qr-code">
            <!-- Placeholder for QR code - implement with library -->
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($result['qr_data']) ?>" 
                 alt="QR Code" style="max-width: 100%;">
        </div>
        
        <div class="text-start mt-4">
            <div class="info-row">
                <span class="info-label">Priority:</span>
                <span class="info-value text-<?= $result['tier'] === 'P0' ? 'danger' : ($result['tier'] === 'P1' ? 'warning' : 'primary') ?>">
                    <?= $result['tier_name'] ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Est. Wait Time:</span>
                <span class="info-value"><?= $result['estimated_wait_minutes'] ?> minutes</span>
            </div>
            <div class="info-row">
                <span class="info-label">Queue Position:</span>
                <span class="info-value">#<?= $result['queue_position'] ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Eligible Windows:</span>
                <span class="info-value"><?= implode(', ', $result['eligible_windows']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Issued:</span>
                <span class="info-value"><?= date('g:i A', strtotime($result['timestamp'])) ?></span>
            </div>
        </div>
        
        <div class="alert alert-info mt-4">
            <strong>📱 Stay Updated</strong><br>
            Check your status at:<br>
            <strong>qodss.eac.edu.ph/check</strong><br>
            <small>Enter ticket: <?= $result['ticket_id'] ?></small>
        </div>
        
        <div class="mt-4">
            <button onclick="window.print()" class="btn btn-outline-primary">🖨️ Print Ticket</button>
            <a href="../index.php" class="btn btn-primary">Done</a>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 30 seconds
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>
