<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

final class RateLimiter
{
    public static function consume(string $bucket, string $identity, int $limit, int $windowSeconds): bool
    {
        if (
            RATE_LIMIT_SECRET === ''
            || $limit < 1
            || $windowSeconds < 1
            || !preg_match('/^[a-z_]{1,32}$/', $bucket)
        ) {
            throw new RuntimeException('Rate limiter configuration is invalid.');
        }

        $identityHash = hash_hmac('sha256', $identity, RATE_LIMIT_SECRET);
        $pdo = Database::connection();
        $expired = 'window_started_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $windowSeconds . ' SECOND)';
        $stmt = $pdo->prepare(
            'INSERT INTO rate_limits (bucket, identity_hash, window_started_at, request_count)
             VALUES (?, ?, UTC_TIMESTAMP(), 1)
             ON DUPLICATE KEY UPDATE
                request_count = IF(' . $expired . ', 1, request_count + 1),
                window_started_at = IF(' . $expired . ', UTC_TIMESTAMP(), window_started_at)'
        );
        $stmt->execute([$bucket, $identityHash]);

        $stmt = $pdo->prepare('SELECT request_count FROM rate_limits WHERE bucket = ? AND identity_hash = ?');
        $stmt->execute([$bucket, $identityHash]);
        if (random_int(1, 100) === 1) {
            $pdo->exec('DELETE FROM rate_limits WHERE window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
        }

        return (int) $stmt->fetchColumn() <= $limit;
    }

    public static function clear(string $bucket, string $identity): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM rate_limits WHERE bucket = ? AND identity_hash = ?'
        );
        $stmt->execute([$bucket, hash_hmac('sha256', $identity, RATE_LIMIT_SECRET)]);
    }

    public static function clientIdentity(): string
    {
        $address = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var($address, FILTER_VALIDATE_IP) ? $address : 'unknown';
    }
}
