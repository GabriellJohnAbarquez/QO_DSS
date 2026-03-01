<?php
/**
 * System Simulation Interface
 * For dry run testing as specified in thesis
 */

session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../dbconfig.php';

verifyAdminAuth();
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Simulation - QO-DSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">🧪 System Simulation (Dry Run)</span>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">← Back</a>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Test Scenarios</div>
                    <div class="card-body">
                        <button class="btn btn-primary w-100 mb-2" onclick="runScenario('logical')">
                            Phase 1: Logical Verification
                        </button>
                        <button class="btn btn-warning w-100 mb-2" onclick="runScenario('stress')">
                            Phase 2: Stress Test
                        </button>
                        <button class="btn btn-info w-100 mb-2" onclick="runScenario('dss')">
                            Phase 3: DSS Calibration
                        </button>
                        <hr>
                        <button class="btn btn-danger w-100" onclick="resetSimulation()">
                            Reset Test Data
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Simulation Results</div>
                    <div class="card-body">
                        <div id="simulationLog" class="bg-dark text-light p-3" style="height: 400px; overflow-y: auto; font-family: monospace;">
                            <p class="text-muted">Select a test scenario to begin...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        async function runScenario(type) {
            const log = document.getElementById('simulationLog');
            log.innerHTML += `<p>> Starting ${type} test...</p>`;
            
            try {
                const response = await fetch(`../tests/test_simulation.php?scenario=${type}`);
                const result = await response.json();
                
                log.innerHTML += `<p class="text-success">> Test completed: ${result.passed ? 'PASSED' : 'FAILED'}</p>`;
                log.innerHTML += `<p>> Details: ${JSON.stringify(result.details, null, 2)}</p>`;
                
            } catch (e) {
                log.innerHTML += `<p class="text-danger">> Error: ${e.message}</p>`;
            }
            
            log.scrollTop = log.scrollHeight;
        }
        
        function resetSimulation() {
            if (confirm('Clear all test data?')) {
                document.getElementById('simulationLog').innerHTML = '<p class="text-muted">Simulation data cleared.</p>';
            }
        }
    </script>
</body>
</html>
