<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/content.php';

/**
 * Public, read-only: returns text staff have customised for one page plus the
 * site-wide contact details. Any failure returns empty data so the pages simply
 * keep their original wording.
 */
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    send_json(['error' => 'Method not allowed.'], 405);
}

$page = $_GET['page'] ?? '';
if (!is_string($page) || !SiteContent::isPage($page)) {
    send_json(['error' => 'Unknown page.'], 404);
}

$payload = ['page' => new stdClass(), 'site' => new stdClass()];
try {
    $pdo = Database::connection();
    $pageValues = SiteContent::overrides($pdo, $page);
    $siteValues = SiteContent::overrides($pdo, 'site');
    $payload['page'] = $pageValues === [] ? new stdClass() : $pageValues;
    $payload['site'] = $siteValues === [] ? new stdClass() : array_intersect_key($siteValues, SiteContent::SITE_FIELDS);
} catch (Throwable $exception) {
    error_log('Site content could not be loaded: ' . $exception->getMessage());
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
