<?php
/**
 * Companero Resort — Configuration
 * Database credentials and application settings
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');  // Change this on production
define('DB_NAME', 'companero_resort');
define('DB_PORT', 3306);

// Application Settings
define('APP_NAME', 'Companero Resort');
define('APP_URL', 'http://localhost/companero-resort-premium');
define('SESSION_LIFETIME', 3600); // 1 hour in seconds
define('ADMIN_SESSION_NAME', 'companero_admin_session');

// Security Settings
define('PASSWORD_ALGORITHM', PASSWORD_BCRYPT);
define('PASSWORD_COST', 12);

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Never show errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Ensure logs directory exists
$logs_dir = __DIR__ . '/../logs';
if (!is_dir($logs_dir)) {
    @mkdir($logs_dir, 0755, true);
}

// Set secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
