<?php
/**
 * Admin Login Page
 */

session_start();
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - QO-DSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #1a1a2e;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 400px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <h2 class="text-center mb-4">Admin Login</h2>
            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-dark w-100">Login</button>
            </form>
            <div class="text-center mt-3">
                <a href="../cashier/" class="text-muted">Cashier Login</a>
            </div>
        </div>
    </div>
</body>
</html>
