<?php
/**
 * Admin Login Processing
 */

session_start();
require_once __DIR__ . '/../dbconfig.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$username = $_POST['username'];
$password = $_POST['password'];

try {
    $stmt = $pdo->prepare("
        SELECT user_id, username, password_hash, role 
        FROM tbl_users 
        WHERE username = ? AND role IN ('admin', 'head_cashier', 'supervisor')
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        header('Location: dashboard.php');
        exit;
    } else {
        header('Location: index.php?error=1');
        exit;
    }
    
} catch (PDOException $e) {
    die("Login error");
}
?>
