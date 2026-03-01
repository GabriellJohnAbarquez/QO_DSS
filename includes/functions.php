<?php
/**
 * Global Utility Functions
 */

/**
 * Sanitize output
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format time duration
 */
function formatDuration($minutes) {
    if ($minutes < 60) return $minutes . ' min';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . 'h ' . $mins . 'm';
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log system activity
 */
function logActivity($action, $details = '', $user = 'SYSTEM') {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO tbl_logs (ticket_id, action_type, details, performed_by)
        VALUES ('SYSTEM', ?, ?, ?)
    ");
    $stmt->execute([$action, $details, $user]);
}
?>
