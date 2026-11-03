<?php
declare(strict_types=1);

function app_env(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

define('APP_ENV', app_env('APP_ENV', 'production'));
define('APP_URL', rtrim(app_env('APP_URL', 'http://127.0.0.1:8765'), '/'));
define('APP_ORIGIN', rtrim(app_env('APP_ORIGIN', APP_URL), '/'));
define('APP_TIMEZONE', app_env('APP_TIMEZONE', 'Etc/GMT'));
define('APP_NAME', 'Companero Resort');

define('DB_HOST', app_env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) app_env('DB_PORT', '3306'));
define('DB_NAME', app_env('DB_NAME', 'companero_resort'));
define('DB_USER', app_env('DB_USER'));
define('DB_PASSWORD', app_env('DB_PASSWORD'));

define('SESSION_LIFETIME', 3600);
define('ADMIN_SESSION_NAME', 'companero_admin_session');
define('SESSION_COOKIE_SECURE', filter_var(app_env('SESSION_COOKIE_SECURE', 'true'), FILTER_VALIDATE_BOOLEAN));
define('SESSION_COOKIE_PATH', app_env('SESSION_COOKIE_PATH', '/'));

define('PASSWORD_ALGORITHM', PASSWORD_DEFAULT);
define('PASSWORD_COST', 12);
define('MAIL_TO', app_env('MAIL_TO'));
define('MAIL_FROM', app_env('MAIL_FROM'));
define('RATE_LIMIT_SECRET', app_env('RATE_LIMIT_SECRET', APP_ENV === 'production' ? '' : 'local-development-rate-limit-key'));

if (
    !in_array(APP_TIMEZONE, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)
    || !date_default_timezone_set(APP_TIMEZONE)
) {
    throw new RuntimeException('Application time zone is invalid.');
}

function app_request_is_https(): bool
{
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

if (APP_ENV === 'production') {
    $appUrl = parse_url(APP_URL);
    $appOrigin = parse_url(APP_ORIGIN);
    $appPort = is_array($appUrl) ? ($appUrl['port'] ?? 443) : 0;
    $originPort = is_array($appOrigin) ? ($appOrigin['port'] ?? 443) : 0;
    if (
        DB_PASSWORD === ''
        || DB_USER === ''
        || strlen(RATE_LIMIT_SECRET) < 32
        || filter_var(MAIL_TO, FILTER_VALIDATE_EMAIL) === false
        || filter_var(MAIL_FROM, FILTER_VALIDATE_EMAIL) === false
        || !is_array($appUrl)
        || ($appUrl['scheme'] ?? '') !== 'https'
        || empty($appUrl['host'])
        || !is_array($appOrigin)
        || ($appOrigin['scheme'] ?? '') !== 'https'
        || isset($appOrigin['path'])
        || isset($appOrigin['query'])
        || isset($appOrigin['fragment'])
        || ($appUrl['host'] ?? '') !== ($appOrigin['host'] ?? '')
        || !preg_match('/^[A-Za-z0-9_]+$/', DB_NAME)
        || $appPort !== $originPort
        || DB_PORT < 1
        || DB_PORT > 65535
        || !SESSION_COOKIE_SECURE
    ) {
        throw new RuntimeException('Production configuration is incomplete or insecure.');
    }
}
