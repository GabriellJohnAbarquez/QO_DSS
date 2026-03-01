<?php
/**
 * Main Client Kiosk - Ticket Generation
 * Entry point for students/parents
 */

require_once 'includes/header.php';
require_once 'dbconfig.php';
//test gabo
// Get current queue stats for display
try {
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as waiting,
            AVG(TIMESTAMPDIFF(MINUTE, arrival_timestamp, NOW())) as avg_wait
        FROM tbl_tickets 
        WHERE status = 'Waiting'
    ")->fetch();
} catch (PDOException $e) {
    $stats = ['waiting' => 0, 'avg_wait' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EAC-Cavite Queue System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .kiosk-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .header-logo {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        .header-logo h1 {
            font-size: 2.5rem;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .priority-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            cursor: pointer;
            border-left: 8px solid;
            height: 100%;
        }
        .priority-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.3);
        }
        .priority-card.p0 { border-left-color: #dc3545; }
        .priority-card.p1 { border-left-color: #fd7e14; }
        .priority-card.p2 { border-left-color: #ffc107; }
        .priority-card.p3 { border-left-color: #0dcaf0; }
        .priority-card.p4 { border-left-color: #6c757d; }
        
        .card-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .card-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
        }
        .card-desc {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 15px;
        }
        .badge-custom {
            font-size: 0.85rem;
            padding: 8px 15px;
            border-radius: 20px;
        }
        .stats-bar {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #764ba2;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        .modal-content {
            border-radius: 20px;
            border: none;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 25px;
        }
        .priority-btn {
            padding: 20px;
            font-size: 1.2rem;
            margin-bottom: 15px;
            border-radius: 15px;
            border: 2px solid #dee2e6;
            transition: all 0.3s;
        }
        .priority-btn:hover {
            border-color: #764ba2;
            background-color: #f8f9fa;
        }
        .priority-btn.p0 {
            border-color: #dc3545;
            color: #dc3545;
        }
        .priority-btn.p0:hover {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="kiosk-container">
        <!-- Header -->
        <div class="header-logo">
            <h1>🏫 Emilio Aguinaldo College - Cavite</h1>
            <p class="lead">Cashier's Office Queue System</p>
        </div>
        
        <!-- Live Stats -->
        <div class="stats-bar row">
            <div class="col-md-4">
                <div class="stat-number" id="waitingCount"><?= $stats['waiting'] ?? 0 ?></div>
                <div class="stat-label">Currently Waiting</div>
            </div>
            <div class="col-md-4">
                <div class="stat-number" id="avgWait"><?= round($stats['avg_wait'] ?? 0) ?></div>
                <div class="stat-label">Average Wait (minutes)</div>
            </div>
            <div class="col-md-4">
                <div class="stat-number" id="activeWindows">3</div>
                <div class="stat-label">Active Windows</div>
            </div>
        </div>
        
        <!-- Transaction Categories -->
        <div class="row g-4">
            <!-- P1: Fast Lane -->
            <div class="col-md-4">
                <div class="priority-card p1" onclick="selectCategory('P1')">
                    <div class="card-icon">💵</div>
                    <div class="card-title">Full Payment</div>
                    <div class="card-desc">Complete semester balance payment with exact cash</div>
                    <span class="badge bg-warning badge-custom">⚡ Fast Lane (2-5 min)</span>
                </div>
            </div>
            
            <!-- P1: Exact Payment -->
            <div class="col-md-4">
                <div class="priority-card p1" onclick="selectCategory('P1_Exact')">
                    <div class="card-icon">💰</div>
                    <div class="card-title">Exact Payment</div>
                    <div class="card-desc">Any payment with exact amount, no change needed</div>
                    <span class="badge bg-warning badge-custom">⚡ Fast Lane (1-3 min)</span>
                </div>
            </div>
            
            <!-- P2: Down Payment -->
            <div class="col-md-4">
                <div class="priority-card p2" onclick="selectCategory('P2')">
                    <div class="card-icon">📝</div>
                    <div class="card-title">Down Payment / Enrollment</div>
                    <div class="card-desc">Partial payment for enrollment confirmation</div>
                    <span class="badge bg-info badge-custom">⏰ Time-Sensitive (5-10 min)</span>
                </div>
            </div>
            
            <!-- P3: Installment/Scholarship -->
            <div class="col-md-4">
                <div class="priority-card p3" onclick="selectCategory('P3')">
                    <div class="card-icon">📋</div>
                    <div class="card-title">Installment / Scholarship</div>
                    <div class="card-desc">Payment plans, scholarship verification, adjustments</div>
                    <span class="badge bg-secondary badge-custom">📊 Standard (10-20 min)</span>
                </div>
            </div>
            
            <!-- P4: Documents -->
            <div class="col-md-4">
                <div class="priority-card p4" onclick="selectCategory('P4')">
                    <div class="card-icon">📄</div>
                    <div class="card-title">Documents / Clearance</div>
                    <div class="card-desc">TOR requests, clearance forms, miscellaneous</div>
                    <span class="badge bg-secondary badge-custom">📄 General (5-15 min)</span>
                </div>
            </div>
            
            <!-- P0: Priority (Special) -->
            <div class="col-md-4">
                <div class="priority-card p0" onclick="selectCategory('P0')">
                    <div class="card-icon">⭐</div>
                    <div class="card-title">Priority Lane</div>
                    <div class="card-desc">Senior citizens, PWDs, pregnant women</div>
                    <span class="badge bg-danger badge-custom">❤️ Priority Access</span>
                </div>
            </div>
        </div>
        
        <!-- Footer Info -->
        <div class="text-center text-white mt-5">
            <p class="mb-1">System Status: <span class="badge bg-success">🟢 Operational</span></p>
            <small>Operated by Cashier's Office | EAC-Cavite</small>
        </div>
    </div>
    
    <!-- Priority Status Modal -->
    <div class="modal fade" id="priorityModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Priority Status Check</h5>
                </div>
                <div class="modal-body p-4">
                    <p class="lead mb-4">Are you any of the following?</p>
                    <button class="btn btn-outline-danger btn-lg w-100 priority-btn p0 mb-3" onclick="confirmPriority('Senior')">
                        👴 Senior Citizen (60 years or older)
                    </button>
                    <button class="btn btn-outline-danger btn-lg w-100 priority-btn p0 mb-3" onclick="confirmPriority('PWD')">
                        ♿ Person with Disability
                    </button>
                    <button class="btn btn-outline-danger btn-lg w-100 priority-btn p0 mb-3" onclick="confirmPriority('Pregnant')">
                        🤰 Pregnant Woman
                    </button>
                    <hr class="my-4">
                    <button class="btn btn-outline-secondary btn-lg w-100" onclick="confirmPriority('None')">
                        None of the above
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-5">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
                <h5>Generating your ticket...</h5>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedCategory = '';
        let selectedPriority = 'General';
        
        function selectCategory(cat) {
            selectedCategory = cat;
            
            if (cat === 'P0') {
                // Direct to priority flow
                window.location.href = 'kiosk/details_form.php?tier=P0&priority=P0';
            } else {
                // Show priority check modal
                new bootstrap.Modal(document.getElementById('priorityModal')).show();
            }
        }
        
        function confirmPriority(status) {
            bootstrap.Modal.getInstance(document.getElementById('priorityModal')).hide();
            
            if (status !== 'None') {
                // Upgrade to P0
                window.location.href = `kiosk/details_form.php?tier=${selectedCategory}&priority=P0&category=${status}`;
            } else {
                // Keep original tier
                const tier = selectedCategory.split('_')[0]; // Remove suffix like _Exact
                window.location.href = `kiosk/details_form.php?tier=${tier}&priority=${tier}&category=General`;
            }
        }
        
        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            fetch('api/dss_metrics.php?type=quick')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('waitingCount').textContent = data.waiting || 0;
                    document.getElementById('avgWait').textContent = Math.round(data.avg_wait || 0);
                    document.getElementById('activeWindows').textContent = data.active_windows || 3;
                });
        }, 30000);
    </script>
</body>
</html>
