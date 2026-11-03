<?php
declare(strict_types=1);

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
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        if (APP_ENV === 'production' && PHP_SAPI !== 'cli' && !app_request_is_https()) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            exit('HTTPS is required.');
        }

        session_name(ADMIN_SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => SESSION_COOKIE_PATH,
            'secure' => SESSION_COOKIE_SECURE,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
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
        if (!session_regenerate_id(true)) {
            return false;
        }

        // Store authentication data
        $_SESSION['admin_id'] = (int)$admin_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Strict',
            ]);
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
            header('Location: login.php');
            exit;
        }
    }
}

// Initialize session management
SessionManager::init();
