<?php
/**
 * Cashier Login Processing
 */

session_start();
require_once __DIR__ . '/../dbconfig.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$windowId = $_POST['window_id'] ?? '';

try {
    $stmt = $pdo->prepare("
        SELECT user_id, username, password_hash, role, is_active 
        FROM tbl_users 
        WHERE username = ? AND role = 'cashier'
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        if (!$user['is_active']) {
            die("Account is disabled. Contact administrator.");
        }
        
        $_SESSION['cashier_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['window_id'] = $windowId;
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        // Log login
        $pdo->prepare("
            INSERT INTO tbl_logs (ticket_id, action_type, details, performed_by)
            VALUES ('SYSTEM', 'LOGIN', 'Window $windowId', ?)
        ")->execute([$user['username']]);
        
        header('Location: dashboard.php');
        exit;
    } else {
        header('Location: index.php?error=1');
        exit;
    }
    
} catch (PDOException $e) {
    die("Login error. Please try again.");
}
?>
