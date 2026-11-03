<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

if (!SessionManager::isAuthenticated()) {
    header('Location: login.php', true, 303);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!same_origin_request() || !valid_csrf_token($_POST['csrf_token'] ?? null)) {
        render_error_page(403, 'The password-change request could not be verified.');
    }

    $current = $_POST['current_password'] ?? null;
    $new = $_POST['new_password'] ?? null;
    $confirm = $_POST['confirm_password'] ?? null;
    if (!is_string($current) || !is_string($new) || !is_string($confirm)) {
        $error = 'Enter valid passwords.';
    } elseif (strlen($new) < 14 || strlen($new) > 1024) {
        $error = 'Choose a new password with at least 14 characters.';
    } elseif (!hash_equals($new, $confirm)) {
        $error = 'The new passwords do not match.';
    } else {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ? AND active = TRUE');
            $stmt->execute([SessionManager::getAdminId()]);
            $hash = $stmt->fetchColumn();
            if (!is_string($hash) || !password_verify($current, $hash)) {
                $error = 'The current password is incorrect.';
            } else {
                $stmt = $pdo->prepare('UPDATE admins SET password_hash = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
                $stmt->execute([Authentication::hashPassword($new), SessionManager::getAdminId()]);
                if (!session_regenerate_id(true)) {
                    throw new RuntimeException('Unable to rotate the session identifier after password change.');
                }
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: dashboard.php?password_updated=1', true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Admin password update failed: ' . $exception->getMessage());
            http_response_code(503);
            $error = 'The password could not be changed right now. Please try again later.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    render_error_page(405, 'This request method is not supported.');
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Change Password | Companero Resort</title>
    <meta name="robots" content="noindex,nofollow" />
    <link rel="stylesheet" href="../css/style.css" />
  </head>
  <body class="admin-page">
    <main class="admin-shell">
      <section class="admin-login">
        <a class="logo" href="dashboard.php">COMPANERO <span>RESORT.</span></a>
        <div class="admin-login-card">
          <div class="kicker">Account security</div>
          <h1 class="serif">Change password.</h1>
          <form class="admin-form" method="post" action="password.php">
            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>" />
            <label for="current-password">Current password</label>
            <input id="current-password" name="current_password" type="password" autocomplete="current-password" required maxlength="1024" />
            <label for="new-password">New password</label>
            <input id="new-password" name="new_password" type="password" autocomplete="new-password" required minlength="14" maxlength="1024" />
            <label for="confirm-password">Confirm new password</label>
            <input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" required minlength="14" maxlength="1024" />
            <button class="btn btn-primary" type="submit">Update password</button>
            <p class="admin-error" role="alert"><?= escape_html($error) ?></p>
            <a href="dashboard.php">Return to inbox</a>
          </form>
        </div>
      </section>
    </main>
  </body>
</html>
