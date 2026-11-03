<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/rate-limiter.php';
require_once __DIR__ . '/session.php';

final class Authentication
{
    private string $error = '';

    public function login(string $username, string $password): bool
    {
        $username = trim($username);
        if ($username === '' || $password === '' || strlen($username) > 254 || strlen($password) > 1024) {
            $this->error = 'Invalid username or password.';
            return false;
        }

        $identity = RateLimiter::clientIdentity();
        if (!RateLimiter::consume('admin_login', $identity, 5, 900)) {
            $this->error = 'Too many sign-in attempts. Please wait and try again.';
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, username, password_hash, role, active FROM admins
             WHERE username = ? OR email = ? LIMIT 1'
        );
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();

        static $dummyHash = null;
        if ($dummyHash === null) {
            $dummyHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        }
        $passwordMatches = password_verify($password, $admin['password_hash'] ?? $dummyHash);

        if (!$admin || !$passwordMatches || !(bool) $admin['active']) {
            $this->error = 'Invalid username or password.';
            return false;
        }

        if (!SessionManager::create((int) $admin['id'], $admin['username'], $admin['role'])) {
            throw new RuntimeException('Unable to establish an authenticated session.');
        }

        $stmt = $pdo->prepare('UPDATE admins SET last_login = UTC_TIMESTAMP() WHERE id = ?');
        $stmt->execute([(int) $admin['id']]);
        RateLimiter::clear('admin_login', $identity);

        return true;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => PASSWORD_COST]);
    }
}
