<?php
/**
 * System Settings
 */

session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../dbconfig.php';

verifyAdminAuth();

// Get current settings
$settings = $pdo->query("SELECT * FROM tbl_settings WHERE setting_id = 1")->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        UPDATE tbl_settings SET
            aging_threshold_p1 = ?,
            aging_threshold_p2 = ?,
            aging_threshold_p3 = ?,
            aging_threshold_p4 = ?,
            peak_mode_threshold = ?,
            max_wait_hard_cap = ?
        WHERE setting_id = 1
    ");
    $stmt->execute([
        $_POST['aging_p1'],
        $_POST['aging_p2'],
        $_POST['aging_p3'],
        $_POST['aging_p4'],
        $_POST['peak_threshold'],
        $_POST['hard_cap']
    ]);
    
    header('Location: settings.php?saved=1');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Settings - QO-DSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>System Configuration</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_GET['saved'])): ?>
                            <div class="alert alert-success">Settings saved successfully!</div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <h5 class="mb-3">Aging Thresholds (Table 2.1)</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>P1 → P0 (minutes)</label>
                                    <input type="number" name="aging_p1" class="form-control" 
                                           value="<?= $settings['aging_threshold_p1'] ?>" min="5" max="60">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>P2 → P1 (minutes)</label>
                                    <input type="number" name="aging_p2" class="form-control" 
                                           value="<?= $settings['aging_threshold_p2'] ?>" min="5" max="60">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>P3 → P2 (minutes)</label>
                                    <input type="number" name="aging_p3" class="form-control" 
                                           value="<?= $settings['aging_threshold_p3'] ?>" min="5" max="60">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>P4 → P3 (minutes)</label>
                                    <input type="number" name="aging_p4" class="form-control" 
                                           value="<?= $settings['aging_threshold_p4'] ?>" min="5" max="60">
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h5 class="mb-3">Peak Mode Settings</h5>
                            <div class="mb-3">
                                <label>Queue Length Threshold (trigger peak mode suggestion)</label>
                                <input type="number" name="peak_threshold" class="form-control" 
                                       value="<?= $settings['peak_mode_threshold'] ?>" min="10" max="200">
                            </div>
                            
                            <div class="mb-3">
                                <label>Maximum Wait Hard Cap (force P0 after minutes)</label>
                                <input type="number" name="hard_cap" class="form-control" 
                                       value="<?= $settings['max_wait_hard_cap'] ?>" min="30" max="120">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
