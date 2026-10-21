<?php
/**
 * Companero Resort — Session Management
 * Secure session handling with timeout and regeneration
 */

require_once __DIR__ . '/config.php';

class SessionManager {
    
    /**
     * Initialize secure session
     */
    public static function init() {
        // Set session name
        session_name(ADMIN_SESSION_NAME);
        
        // Set secure cookie options for HTTPS (set to true on production)
        $cookie_options = [
            'lifetime' => SESSION_LIFETIME,
            'path' => '/companero-resort-premium/',  // Adjust to your path
            'domain' => '',
            'secure' => false,  // Set to true on HTTPS
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        
        session_set_cookie_params($cookie_options);
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated() {
        return isset($_SESSION['admin_id']) && 
               isset($_SESSION['authenticated']) && 
               $_SESSION['authenticated'] === true &&
               self::isSessionValid();
    }

    /**
     * Validate session isn't stale
     */
    public static function isSessionValid() {
        if (!isset($_SESSION['last_activity'])) {
            return false;
        }

        $elapsed = time() - $_SESSION['last_activity'];
        
        if ($elapsed > SESSION_LIFETIME) {
            self::destroy();
            return false;
        }

        // Update activity timestamp
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Create session after successful login
     */
    public static function create($admin_id, $username, $role) {
        // Regenerate ID to prevent fixation attacks
        session_regenerate_id(true);

        // Store authentication data
        $_SESSION['admin_id'] = (int)$admin_id;
        $_SESSION['username'] = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $_SESSION['role'] = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * Get admin ID from session
     */
    public static function getAdminId() {
        return $_SESSION['admin_id'] ?? null;
    }

    /**
     * Get username from session
     */
    public static function getUsername() {
        return $_SESSION['username'] ?? null;
    }

    /**
     * Get admin role from session
     */
    public static function getRole() {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Destroy session and clear data
     */
    public static function destroy() {
        // Clear session data
        $_SESSION = [];

        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy the session
        session_destroy();
        return true;
    }

    /**
     * Require authentication - redirect to login if not authenticated
     */
    public static function requireAuth() {
        self::init();
        
        if (!self::isAuthenticated()) {
            // Clear any stale session data
            self::destroy();
            header('Location: /companero-resort-premium/admin/login.php');
            exit;
        }
    }
}

// Initialize session management
SessionManager::init();
