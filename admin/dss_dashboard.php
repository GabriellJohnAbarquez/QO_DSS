<?php
/**
 * Decision Support System Dashboard
 * Real-time alerts, recommendations, and forecasting
 */

session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../classes/DSSAnalytics.php';

verifyAdminAuth();

$analytics = new DSSAnalytics($pdo);
$recommendations = $analytics->getStaffingRecommendations();
$forecast = $analytics->generateForecast();
$bottlenecks = $analytics->analyzeBottlenecks();
?>
<!DOCTYPE html>
<html>
<head>
    <title>DSS Center - QO-DSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .alert-card {
            border-left: 5px solid;
            margin-bottom: 15px;
        }
        .alert-high { border-left-color: #dc3545; background: #f8d7da; }
        .alert-medium { border-left-color: #ffc107; background: #fff3cd; }
        .alert-low { border-left-color: #0dcaf0; background: #d1ecf1; }
        .forecast-bar {
            height: 30px;
            border-radius: 15px;
            background: #e9ecef;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .forecast-fill {
            height: 100%;
            border-radius: 15px;
            transition: width 0.3s;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">🤖 Decision Support Center</a>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">← Back to Admin</a>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Active Recommendations -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">🚨 Active Recommendations</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recommendations)): ?>
                            <p class="text-muted">No active recommendations. System operating normally.</p>
                        <?php else: ?>
                            <?php foreach ($recommendations as $rec): ?>
                                <div class="alert-card p-3 rounded alert-<?= strtolower($rec['priority']) ?>">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= $rec['type'] ?></strong>
                                        <span class="badge bg-<?= $rec['priority'] === 'HIGH' ? 'danger' : ($rec['priority'] === 'MEDIUM' ? 'warning' : 'info') ?>">
                                            <?= $rec['priority'] ?>
                                        </span>
                                    </div>
                                    <p class="mb-1"><?= $rec['message'] ?></p>
                                    <small class="text-muted">Action: <?= $rec['action'] ?></small>
                                    <?php if (isset($rec['estimated_impact'])): ?>
                                        <br><small class="text-success">Impact: <?= $rec['estimated_impact'] ?></small>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-dark mt-2" onclick="acknowledgeAlert(this)">
                                        Acknowledge
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Bottleneck Analysis -->
                <div class="card mt-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">🔍 Bottleneck Analysis</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($bottlenecks as $bottleneck): ?>
                            <div class="mb-3">
                                <strong><?= $bottleneck['type'] ?></strong>
                                <p class="mb-1"><?= $bottleneck['description'] ?></p>
                                <small class="text-muted"><?= $bottleneck['recommendation'] ?? '' ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Forecast & Predictions -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">📊 2-Hour Forecast</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($forecast as $hour): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span><?= $hour['hour'] ?>:00 - <?= $hour['hour'] + 1 ?>:00</span>
                                    <span class="badge bg-<?= $hour['confidence'] === 'HIGH' ? 'success' : ($hour['confidence'] === 'MEDIUM' ? 'warning' : 'secondary') ?>">
                                        <?= $hour['confidence'] ?> confidence
                                    </span>
                                </div>
                                <div class="forecast-bar">
                                    <div class="forecast-fill bg-primary" style="width: <?= min(100, $hour['predicted_arrivals'] * 2) ?>%"></div>
                                </div>
                                <div class="row text-center small">
                                    <div class="col-4">
                                        <strong><?= $hour['predicted_arrivals'] ?></strong><br>arrivals
                                    </div>
                                    <div class="col-4">
                                        <strong><?= round($hour['predicted_avg_wait']) ?>m</strong><br>wait
                                    </div>
                                    <div class="col-4">
                                        <strong><?= round($hour['predicted_utilization'] * 100) ?>%</strong><br>util
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Manual Controls -->
                <div class="card mt-4">
                    <div class="card-header">Manual Overrides</div>
                    <div class="card-body">
                        <button class="btn btn-warning w-100 mb-2" onclick="location.href='window_management.php'">
                            Reconfigure Windows
                        </button>
                        <button class="btn btn-info w-100 mb-2" onclick="location.href='alerts.php'">
                            View Alert History
                        </button>
                        <button class="btn btn-secondary w-100" onclick="exportReport()">
                            Export DSS Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function acknowledgeAlert(btn) {
            btn.closest('.alert-card').style.opacity = '0.5';
            btn.disabled = true;
            btn.textContent = 'Acknowledged';
        }
        
        function exportReport() {
            alert('Report export functionality - implement as needed');
        }
    </script>
</body>
</html>
