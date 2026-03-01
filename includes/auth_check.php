<?php
/**
 * Authentication Verification Functions
 */

function verifyCashierAuth() {
    if (!isset($_SESSION['cashier_id']) || !isset($_SESSION['window_id'])) {
        header('Location: index.php');
        exit;
    }
    
    // Check session timeout (30 minutes)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
        session_destroy();
        header('Location: index.php?timeout=1');
        exit;
    }
}

function verifyAdminAuth() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: index.php');
        exit;
    }
    
    $allowedRoles = ['admin', 'head_cashier', 'supervisor'];
    if (!in_array($_SESSION['role'] ?? '', $allowedRoles)) {
        header('Location: ../cashier/dashboard.php');
        exit;
    }
}

function requireRole($roles) {
    if (!is_array($roles)) $roles = [$roles];
    if (!in_array($_SESSION['role'] ?? '', $roles)) {
        http_response_code(403);
        die('Insufficient privileges');
    }
}
?>
