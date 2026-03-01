<?php
/**
 * Admin Main Dashboard
 */

session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/../classes/MetricsCalculator.php';

verifyAdminAuth();

$metrics = new MetricsCalculator($pdo);
$currentStats = $metrics->getFullReport();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - QO-DSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .metric-card {
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-bottom: 20px;
        }
        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-gradient-success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .bg-gradient-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .bg-gradient-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">QO-DSS Admin</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="dss_dashboard.php">DSS Center</a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li>
                    <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
                </ul>
                <span class="navbar-text me-3"><?= $_SESSION['username'] ?> (<?= $_SESSION['role'] ?>)</span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <!-- Metrics Row -->
        <div class="row">
            <div class="col-md-3">
                <div class="metric-card bg-gradient-primary">
                    <div class="metric-value"><?= $currentStats['utilization_rate'] * 100 ?>%</div>
                    <div>Server Utilization (ρ)</div>
                    <small>Target: 70-85%</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card bg-gradient-success">
                    <div class="metric-value"><?= round($currentStats['avg_waiting_time'], 1) ?></div>
                    <div>Avg Wait Time (Wq)</div>
                    <small>minutes</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card bg-gradient-warning">
                    <div class="metric-value"><?= $currentStats['throughput_per_hour'] ?></div>
                    <div>Throughput</div>
                    <small>customers/hour</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card bg-gradient-info">
                    <div class="metric-value"><?= $currentStats['service_level_15min'] ?>%</div>
                    <div>Service Level</div>
                    <small>within 15 min</small>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Today's Queue Pattern</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="queueChart" height="100"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Current Queue by Tier</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="tierChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">Quick Actions</div>
                    <div class="card-body">
                        <button class="btn btn-warning" onclick="togglePeakMode()">
                            Toggle Peak Mode
                        </button>
                        <button class="btn btn-info" onclick="processAgingNow()">
                            Process Aging
                        </button>
                        <a href="simulation.php" class="btn btn-secondary">Run Simulation</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Load charts
        async function loadCharts() {
            const response = await fetch('../api/get_queue_history.php?period=today');
            const data = await response.json();
            
            // Queue pattern chart
            new Chart(document.getElementById('queueChart'), {
                type: 'line',
                data: {
                    labels: data.data.map(d => d.hour + ':00'),
                    datasets: [{
                        label: 'Arrivals',
                        data: data.data.map(d => d.arrivals),
                        borderColor: '#667eea',
                        tension: 0.4
                    }, {
                        label: 'Avg Wait (min)',
                        data: data.data.map(d => d.avg_wait),
                        borderColor: '#f5576c',
                        tension: 0.4
                    }]
                }
            });
            
            // Tier distribution (fetch current)
            const tierData = await fetch('../api/dss_metrics.php?type=quick').then(r => r.json());
            
            new Chart(document.getElementById('tierChart'), {
                type: 'doughnut',
                data: {
                    labels: ['P0 Priority', 'P1 Fast', 'P2 Time-Sensitive', 'P3 Standard', 'P4 General'],
                    datasets: [{
                        data: [5, 15, 20, 30, 30], // Placeholder - replace with actual
                        backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#0dcaf0', '#6c757d']
                    }]
                }
            });
        }
        
        loadCharts();
        
        async function togglePeakMode() {
            const enable = confirm('Enable Peak Mode? This will activate Windows 4-6.');
            const response = await fetch('../api/toggle_peak_mode.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({enable: enable})
            });
            const result = await response.json();
            alert(result.message);
        }
        
        async function processAgingNow() {
            const response = await fetch('../api/process_aging.php?key=EAC_QODSS_CRON_2024');
            const result = await response.json();
            alert(`Aging processed: ${result.promotions_processed} tickets promoted`);
        }
    </script>
</body>
</html>
