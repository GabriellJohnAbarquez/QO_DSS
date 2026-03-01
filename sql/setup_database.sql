-- QO-DSS Database Schema
-- Run this after creating database in dbconfig.php

-- Users table (cashiers, admins)
CREATE TABLE IF NOT EXISTS tbl_users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'head_cashier', 'supervisor', 'cashier') DEFAULT 'cashier',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin (password: admin123 - CHANGE IN PRODUCTION!)
INSERT INTO tbl_users (username, password_hash, full_name, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin'),
('head_cashier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Head Cashier', 'head_cashier');

-- Main tickets table
CREATE TABLE IF NOT EXISTS tbl_tickets (
    ticket_id VARCHAR(20) PRIMARY KEY,
    client_type VARCHAR(50) NOT NULL,
    client_category VARCHAR(50) DEFAULT 'General',
    transaction_category ENUM('P0','P1','P2','P3','P4') NOT NULL,
    transaction_type VARCHAR(100),
    priority_level INT NOT NULL,
    complexity_weight INT NOT NULL,
    arrival_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    service_start_time DATETIME NULL,
    service_end_time DATETIME NULL,
    assigned_window INT NULL,
    status ENUM('Waiting','Serving','Completed','Cancelled','NoShow') DEFAULT 'Waiting',
    aging_promoted BOOLEAN DEFAULT FALSE,
    promotion_reason VARCHAR(255),
    estimated_service_time INT,
    actual_service_time INT NULL,
    actual_wait_time INT NULL,
    exact_cash BOOLEAN DEFAULT FALSE,
    requires_change BOOLEAN DEFAULT TRUE,
    student_id VARCHAR(20) NULL,
    contact_info VARCHAR(50) NULL,
    notes TEXT NULL,
    details JSON NULL,
    override_code VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_tier (status, transaction_category),
    INDEX idx_arrival (arrival_timestamp),
    INDEX idx_window (assigned_window, status),
    INDEX idx_date (DATE(arrival_timestamp))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Window queue mapping
CREATE TABLE IF NOT EXISTS tbl_window_queues (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    window_id INT NOT NULL,
    ticket_id VARCHAR(20) NOT NULL,
    tier_at_entry VARCHAR(5) NOT NULL,
    is_eligible BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT FALSE,
    added_to_queue TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    served_at TIMESTAMP NULL,
    FOREIGN KEY (ticket_id) REFERENCES tbl_tickets(ticket_id) ON DELETE CASCADE,
    UNIQUE KEY unique_window_ticket (window_id, ticket_id),
    INDEX idx_window_eligible (window_id, is_eligible, added_to_queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System settings
CREATE TABLE IF NOT EXISTS tbl_settings (
    setting_id INT PRIMARY KEY DEFAULT 1,
    system_name VARCHAR(100) DEFAULT 'QO-DSS EAC-Cavite',
    aging_threshold_p1 INT DEFAULT 15,
    aging_threshold_p2 INT DEFAULT 20,
    aging_threshold_p3 INT DEFAULT 25,
    aging_threshold_p4 INT DEFAULT 30,
    peak_mode_threshold INT DEFAULT 50,
    max_wait_hard_cap INT DEFAULT 60,
    current_mode ENUM('NORMAL','PEAK') DEFAULT 'NORMAL',
    auto_promote_peak BOOLEAN DEFAULT FALSE,
    announcement_enabled BOOLEAN DEFAULT TRUE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tbl_settings (setting_id) VALUES (1) ON DUPLICATE KEY UPDATE setting_id = setting_id;

-- Audit logs
CREATE TABLE IF NOT EXISTS tbl_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id VARCHAR(20) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    performed_by VARCHAR(50) DEFAULT 'SYSTEM',
    ip_address VARCHAR(45) NULL,
    INDEX idx_ticket (ticket_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_action (action_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DSS Alerts
CREATE TABLE IF NOT EXISTS tbl_dss_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(50) NOT NULL,
    severity ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL,
    message TEXT NOT NULL,
    recommended_action TEXT,
    is_acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by VARCHAR(50) NULL,
    acknowledged_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_acknowledged (is_acknowledged, created_at),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hourly statistics (for forecasting)
CREATE TABLE IF NOT EXISTS tbl_hourly_stats (
    stat_id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    hour_of_day INT NOT NULL,
    day_of_week INT NOT NULL,
    arrival_count INT DEFAULT 0,
    avg_wait_time DECIMAL(10,2) DEFAULT 0,
    avg_service_time DECIMAL(10,2) DEFAULT 0,
    utilization DECIMAL(5,2) DEFAULT 0,
    UNIQUE KEY unique_datetime (date, hour_of_day),
    INDEX idx_day_hour (day_of_week, hour_of_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
