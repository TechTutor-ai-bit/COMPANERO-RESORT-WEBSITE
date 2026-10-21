<?php
/**
 * Companero Resort — Role-Based Permissions
 * Permission checking for different admin roles
 */

require_once __DIR__ . '/session.php';

class Permissions {

    /**
     * Available roles
     */
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_STAFF = 'staff';

    /**
     * Role capabilities mapping
     */
    private static $capabilities = [
        'super_admin' => [
            'view_dashboard',
            'view_reservations',
            'view_messages',
            'view_rooms',
            'manage_admins',
            'manage_settings',
            'view_reports',
        ],
        'manager' => [
            'view_dashboard',
            'view_reservations',
            'view_messages',
            'view_rooms',
        ],
        'staff' => [
            'view_dashboard',
            'view_reservations',
            'view_messages',
        ],
    ];

    /**
     * Check if user has specific permission
     */
    public static function can($capability) {
        if (!SessionManager::isAuthenticated()) {
            return false;
        }

        $role = SessionManager::getRole();
        
        if (!isset(self::$capabilities[$role])) {
            return false;
        }

        return in_array($capability, self::$capabilities[$role], true);
    }

    /**
     * Require specific permission
     */
    public static function require($capability) {
        if (!self::can($capability)) {
            http_response_code(403);
            die('You do not have permission to access this page.');
        }
    }

    /**
     * Check if user is super admin
     */
    public static function isSuperAdmin() {
        return SessionManager::getRole() === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Check if user is manager or higher
     */
    public static function isManagerOrHigher() {
        $role = SessionManager::getRole();
        return in_array($role, [self::ROLE_SUPER_ADMIN, self::ROLE_MANAGER], true);
    }

    /**
     * Get all roles
     */
    public static function getAllRoles() {
        return array_keys(self::$capabilities);
    }

    /**
     * Get capabilities for a role
     */
    public static function getCapabilities($role) {
        return self::$capabilities[$role] ?? [];
    }
}
