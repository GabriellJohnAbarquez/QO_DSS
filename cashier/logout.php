<?php
/**
 * Cashier Logout
 */

session_start();

// Log logout if logged in
if (isset($_SESSION['cashier_id'])) {
    require_once __DIR__ . '/../dbconfig.php';
    $pdo->prepare("
        INSERT INTO tbl_logs (ticket_id, action_type, details, performed_by)
        VALUES ('SYSTEM', 'LOGOUT', 'Window {$_SESSION['window_id']}', ?)
    ")->execute([$_SESSION['username']]);
}

session_destroy();
header('Location: index.php');
exit;
?>
