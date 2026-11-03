<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    render_error_page(405, 'This request method is not supported.');
}

if (!same_origin_request() || !valid_csrf_token($_POST['csrf_token'] ?? null)) {
    render_error_page(403, 'The sign-out request could not be verified.');
}

SessionManager::destroy();
header('Location: login.php', true, 303);
exit;
