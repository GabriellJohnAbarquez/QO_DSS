<?php
/**
 * Database Configuration - QO-DSS
 * Emilio Aguinaldo College - Cavite
 */

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database credentials
$host = "localhost";
$user = "root";
$password = "";
$dbname = "qodss_eac_cavite";  // Queue Optimization DSS - EAC Cavite
$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

try {
    // Initialize PDO connection
    $pdo = new PDO($dsn, $user, $password);
    
    // Throw exceptions on errors
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set fetch mode to associative array by default
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Disable emulated prepares for true prepared statements
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Set timezone to Philippine Standard Time (UTC+8)
    $pdo->exec("SET time_zone = '+08:00'");
    
    // Set character set
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
} catch (PDOException $e) {
    // Log error securely (don't expose details to users in production)
    error_log("Database connection failed: " . $e->getMessage());
    
    // User-friendly error (customize as needed)
    header('Content-Type: text/html; charset=utf-8');
    die("
        <div style='font-family: Arial, sans-serif; text-align: center; padding: 50px;'>
            <h1 style='color: #dc3545;'>System Maintenance</h1>
            <p>The queue system is temporarily unavailable.</p>
            <p>Please try again in a few minutes or contact the Cashier's Office.</p>
            <hr>
            <small>Error Code: DB_CONN_001</small>
        </div>
    ");
}

// Global database helper function
function getDB() {
    global $pdo;
    return $pdo;
}
?>
