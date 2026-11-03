<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/submissions.php';

if (APP_ENV === 'production' && !app_request_is_https()) {
    send_json(['error' => 'HTTPS is required.'], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    send_json(['error' => 'Method not allowed.'], 405);
}

if (!same_origin_request()) {
    render_submission_error('Request origin could not be verified.', 403, false);
}

$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
$expectsJson = $contentType === 'application/json';
if (!$expectsJson && $contentType !== 'application/x-www-form-urlencoded') {
    render_submission_error('Unsupported request format.', 415, $expectsJson);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 16000) {
    render_submission_error('Request is too large.', 413, $expectsJson);
}

$body = '';
if ($expectsJson) {
    $body = file_get_contents('php://input');
    if ($body === false || strlen($body) > 16000) {
        render_submission_error('Request could not be read.', 400, true);
    }
}

try {
    if ($expectsJson) {
        $payload = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
    } else {
        $type = $_POST['type'] ?? null;
        unset($_POST['type']);
        $payload = ['type' => $type, 'fields' => $_POST];
    }
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Invalid request body.');
    }

    $id = Submissions::create($payload);
    $result = [
        'message' => 'Your request was received. The resort will review it and confirm availability with you.',
        'reference' => $id,
    ];
    if ($expectsJson) {
        send_json($result, 201);
    }
    header('Location: ../thank-you.html?reference=' . rawurlencode($id), true, 303);
    exit;
} catch (JsonException | InvalidArgumentException $error) {
    render_submission_error($error->getMessage(), 422, $expectsJson);
} catch (LengthException $error) {
    render_submission_error($error->getMessage(), 429, $expectsJson);
} catch (DomainException $error) {
    render_submission_error($error->getMessage(), 400, $expectsJson);
} catch (Throwable $error) {
    error_log('Public form submission failed: ' . $error->getMessage());
    render_submission_error('We could not save your request. Please try again later or contact the resort.', 503, $expectsJson);
}

function render_submission_error(string $message, int $status, bool $expectsJson): void
{
    if ($expectsJson) {
        send_json(['error' => $message], $status);
    }
    render_error_page($status, $message);
}
