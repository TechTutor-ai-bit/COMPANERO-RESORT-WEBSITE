CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'manager', 'staff') NOT NULL DEFAULT 'staff',
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_admins_role (role),
    INDEX idx_admins_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submissions (
    id CHAR(36) PRIMARY KEY,
    type ENUM('message', 'reservation', 'table') NOT NULL,
    full_name VARCHAR(162) NOT NULL,
    email VARCHAR(254) NULL,
    phone VARCHAR(32) NULL,
    subject VARCHAR(120) NULL,
    message TEXT NULL,
    check_in_date DATE NULL,
    check_out_date DATE NULL,
    number_of_guests TINYINT UNSIGNED NULL,
    number_of_rooms TINYINT UNSIGNED NULL,
    special_requests TEXT NULL,
    table_date DATE NULL,
    table_time TIME NULL,
    table_guests TINYINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_status BOOLEAN NOT NULL DEFAULT FALSE,
    notification_status ENUM('pending', 'not_configured', 'accepted', 'failed') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    handled_by INT NULL,
    handled_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_submissions_type_created (type, created_at),
    INDEX idx_submissions_read_created (read_status, created_at),
    CONSTRAINT fk_submissions_admin FOREIGN KEY (handled_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_logs_admin_created (admin_id, created_at),
    CONSTRAINT fk_admin_logs_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket VARCHAR(32) NOT NULL,
    identity_hash CHAR(64) NOT NULL,
    window_started_at DATETIME NOT NULL,
    request_count INT UNSIGNED NOT NULL,
    PRIMARY KEY (bucket, identity_hash),
    INDEX idx_rate_limits_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_content (
    page VARCHAR(40) NOT NULL,
    content_key VARCHAR(60) NOT NULL,
    content_value TEXT NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (page, content_key),
    CONSTRAINT fk_site_content_admin FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
