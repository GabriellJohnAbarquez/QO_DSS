<?php
/**
 * Cashier Profile Page
 */

session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../dbconfig.php';

verifyCashierAuth();

// Get cashier stats
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_served,
        AVG(actual_service_time) as avg_service,
        AVG(actual_wait_time) as avg_customer_wait
    FROM tbl_tickets
    WHERE assigned_window = ?
    AND DATE(service_end_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND status = 'Completed'
")->execute([$_SESSION['window_id']])->fetch();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>My Performance Stats</h4>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-4">
                            <div class="col-md-4">
                                <div class="display-4"><?= $stats['total_served'] ?? 0 ?></div>
                                <small class="text-muted">Customers Served (30 days)</small>
                            </div>
                            <div class="col-md-4">
                                <div class="display-4"><?= round($stats['avg_service'] ?? 0) ?>m</div>
                                <small class="text-muted">Avg Service Time</small>
                            </div>
                            <div class="col-md-4">
                                <div class="display-4"><?= round($stats['avg_customer_wait'] ?? 0) ?>m</div>
                                <small class="text-muted">Avg Customer Wait</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h5>Change Password</h5>
                        <form action="change_password.php" method="POST">
                            <div class="mb-3">
                                <label>Current Password</label>
                                <input type="password" name="current" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>New Password</label>
                                <input type="password" name="new" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </form>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
