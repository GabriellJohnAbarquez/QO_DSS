<?php
/**
 * Check ticket status by ID
 */

require_once __DIR__ . '/../dbconfig.php';

$ticketId = $_GET['id'] ?? '';
$status = null;

if ($ticketId) {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               TIMESTAMPDIFF(MINUTE, t.arrival_timestamp, NOW()) as total_wait_min
        FROM tbl_tickets t
        WHERE t.ticket_id = ?
    ");
    $stmt->execute([$ticketId]);
    $status = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Check Status - QO-DSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        .status-container {
            max-width: 600px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container status-container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Check Ticket Status</h4>
            </div>
            <div class="card-body">
                <form method="GET" class="mb-4">
                    <div class="input-group input-group-lg">
                        <input type="text" name="id" class="form-control" 
                               placeholder="Enter Ticket ID (e.g., T-20240301-0001)" 
                               value="<?= htmlspecialchars($ticketId) ?>" required>
                        <button type="submit" class="btn btn-primary">Check</button>
                    </div>
                </form>
                
                <?php if ($status): ?>
                    <div class="alert alert-<?= 
                        $status['status'] === 'Serving' ? 'success' : 
                        ($status['status'] === 'Waiting' ? 'warning' : 'info') 
                    ?>">
                        <h5>Ticket: <?= $status['ticket_id'] ?></h5>
                        <p class="mb-1"><strong>Status:</strong> <?= $status['status'] ?></p>
                        <p class="mb-1"><strong>Type:</strong> <?= $status['transaction_category'] ?></p>
                        
                        <?php if ($status['status'] === 'Waiting'): ?>
                            <p class="mb-1"><strong>Waiting for:</strong> <?= $status['total_wait_min'] ?> minutes</p>
                            <p class="mb-0"><strong>Estimated service:</strong> <?= $status['estimated_service_time'] ?> min</p>
                        <?php elseif ($status['status'] === 'Serving'): ?>
                            <p class="mb-0"><strong>Please proceed to Window <?= $status['assigned_window'] ?></strong></p>
                        <?php else: ?>
                            <p class="mb-0"><strong>Completed at:</strong> <?= $status['service_end_time'] ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($status['status'] === 'Waiting'): ?>
                        <div class="progress" style="height: 30px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 style="width: 100%">
                                Refresh for updates
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($ticketId): ?>
                    <div class="alert alert-danger">Ticket not found. Please check your ticket ID.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <a href="../index.php" class="btn btn-light">← Back to Main</a>
        </div>
    </div>
    
    <?php if ($status && $status['status'] === 'Waiting'): ?>
    <script>
        // Auto-refresh every 10 seconds if still waiting
        setTimeout(() => location.reload(), 10000);
    </script>
    <?php endif; ?>
</body>
</html>
