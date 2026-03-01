<?php
/**
 * Cashier Login Page
 */

session_start();
if (isset($_SESSION['cashier_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cashier Login - QO-DSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 400px;
            margin: 0 auto;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="login-card">
            <h2 class="text-center mb-4">Cashier Login</h2>
            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control form-control-lg" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control form-control-lg" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Window Assignment</label>
                    <select name="window_id" class="form-select form-select-lg" required>
                        <option value="">Select Window...</option>
                        <option value="1">Window 1 - Express</option>
                        <option value="2">Window 2 - Flexible</option>
                        <option value="3">Window 3 - General</option>
                        <option value="4">Window 4 - Tuition (Peak)</option>
                        <option value="5">Window 5 - Tuition (Peak)</option>
                        <option value="6">Window 6 - Clearance (Peak)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
            </form>
            <div class="text-center mt-3">
                <a href="../admin/" class="text-muted">Admin Login</a>
            </div>
        </div>
    </div>
</body>
</html>
