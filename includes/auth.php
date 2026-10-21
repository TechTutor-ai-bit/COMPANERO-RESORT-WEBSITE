<?php
/**
 * Companero Resort — Authentication
 * Secure login verification with password hashing
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

class Authentication {
    private $db;
    private $error = '';
    private $lastAttemptTime = 0;
    private $attemptCount = 0;
    private $maxAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->loadAttemptData();
    }

    /**
     * Load previous attempt data from session
     */
    private function loadAttemptData() {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }
    }

    /**
     * Check if account is temporarily locked
     */
    private function isLockedOut() {
        $now = time();
        $attempts = $_SESSION['login_attempts'] ?? [];

        // Filter out old attempts
        $recentAttempts = array_filter($attempts, function($time) use ($now) {
            return ($now - $time) < $this->lockoutDuration;
        });

        $_SESSION['login_attempts'] = $recentAttempts;

        if (count($recentAttempts) >= $this->maxAttempts) {
            return true;
        }

        return false;
    }

    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt() {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }
        $_SESSION['login_attempts'][] = time();
    }

    /**
     * Clear failed attempts
     */
    private function clearAttempts() {
        $_SESSION['login_attempts'] = [];
    }

    /**
     * Authenticate user with username and password
     * Returns admin_id on success, false on failure
     */
    public function login($username, $password) {
        // Validate input
        if (empty(trim($username)) || empty(trim($password))) {
            $this->error = 'Please enter both username and password.';
            return false;
        }

        // Check if account is locked due to too many attempts
        if ($this->isLockedOut()) {
            $this->error = 'Too many failed attempts. Please try again in 15 minutes.';
            error_log("Login lockout: IP " . $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            return false;
        }

        // Fetch admin by username or email (using prepared statement)
        $sql = "SELECT id, full_name, username, email, password_hash, role, active 
                FROM admins 
                WHERE (username = ? OR email = ?) 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([trim($username), trim($username)]);
        $admin = $stmt->fetch();

        // Verify password (even if user doesn't exist, to prevent timing attacks)
        if ($admin && password_verify($password, $admin['password_hash']) && $admin['active']) {
            // Password is correct and account is active
            $this->clearAttempts();
            
            // Create session
            SessionManager::create($admin['id'], $admin['username'], $admin['role']);
            
            // Update last login timestamp
            $updateSql = "UPDATE admins SET last_login = NOW() WHERE id = ?";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([$admin['id']]);

            return $admin['id'];
        } else {
            // Invalid credentials or inactive account
            $this->recordFailedAttempt();
            // Generic error message - don't reveal if user exists
            $this->error = 'Invalid username or password.';
            error_log("Failed login attempt: " . ($admin ? 'wrong password' : 'user not found'));
            return false;
        }
    }

    /**
     * Get last error message
     */
    public function getError() {
        return $this->error;
    }

    /**
     * Hash password for storage
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ALGORITHM, ['cost' => PASSWORD_COST]);
    }

    /**
     * Verify password against hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
