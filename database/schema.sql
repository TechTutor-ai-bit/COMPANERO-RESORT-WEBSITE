-- Companero Resort — Database Schema
-- Create database and tables for admin authentication and submissions

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS companero_resort CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE companero_resort;

-- ============================================
-- ADMINS TABLE — Admin accounts with roles
-- ============================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'manager', 'staff') NOT NULL DEFAULT 'staff',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    INDEX (username),
    INDEX (email),
    INDEX (role),
    INDEX (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUBMISSIONS TABLE — Form submissions
-- ============================================
CREATE TABLE IF NOT EXISTS submissions (
    id VARCHAR(36) PRIMARY KEY,
    type ENUM('message', 'reservation', 'table') NOT NULL,
    full_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(20),
    subject VARCHAR(255),
    message LONGTEXT,
    check_in_date DATE,
    check_out_date DATE,
    number_of_guests INT,
    number_of_rooms INT,
    special_requests LONGTEXT,
    table_date DATE,
    table_time TIME,
    table_guests INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_status BOOLEAN DEFAULT FALSE,
    admin_notes LONGTEXT,
    handled_by INT,
    handled_at TIMESTAMP NULL DEFAULT NULL,
    INDEX (type),
    INDEX (created_at),
    INDEX (read_status),
    FOREIGN KEY (handled_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN ACTIVITY LOG — Audit trail
-- ============================================
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details LONGTEXT,
    ip_address VARCHAR(45),
    user_agent LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX (admin_id),
    INDEX (created_at),
    INDEX (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SAMPLE SUPER ADMIN — Change password immediately!
-- ============================================
-- Default credentials for first setup:
-- Username: admin
-- Password: CompaneroResort2024! (CHANGE THIS IMMEDIATELY)
-- 
-- To update the password after first login, use:
-- UPDATE admins SET password_hash = PASSWORD_HASH('newpassword') WHERE username = 'admin';
--
-- OR use the setup script provided.

INSERT INTO admins (full_name, username, email, password_hash, role, active) 
VALUES (
    'Administrator',
    'admin',
    'admin@companero-resort.local',
    '$2y$12$3IQj5vW0Z.4Y4X8P2D9U6eBxCmK1LqZ.8NvRt5UjW.hY2aBcK4oMi',  -- CompaneroResort2024! (bcrypt cost 12)
    'super_admin',
    TRUE
) ON DUPLICATE KEY UPDATE password_hash = password_hash;
