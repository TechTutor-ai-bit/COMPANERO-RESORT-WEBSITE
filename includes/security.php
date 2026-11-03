<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

function csrf_token(): string
{
    require_once __DIR__ . '/session.php';
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function valid_csrf_token($token): bool
{
    require_once __DIR__ . '/session.php';
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function same_origin_request(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '' && isset($_SERVER['HTTP_REFERER'])) {
        $parts = parse_url($_SERVER['HTTP_REFERER']);
        if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
            $origin = $parts['scheme'] . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $origin .= ':' . $parts['port'];
            }
        }
    }

    $expectedOrigin = normalize_origin(APP_ORIGIN);
    $requestOrigin = normalize_origin($origin);
    return $expectedOrigin !== ''
        && $requestOrigin !== ''
        && hash_equals($expectedOrigin, $requestOrigin);
}

function normalize_origin(string $origin): string
{
    $parts = parse_url($origin);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
        return '';
    }

    $scheme = strtolower($parts['scheme']);
    $host = strtolower($parts['host']);
    $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

    return $scheme . '://' . $host . ':' . $port;
}

function escape_html($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function send_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function render_error_page(int $status, string $message): void
{
    http_response_code($status);
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Request unavailable</title><body><main><h1>Request unavailable</h1><p>';
    echo escape_html($message);
    echo '</p><p><a href="../index.html">Return to the website</a></p></main></body></html>';
    exit;
}
