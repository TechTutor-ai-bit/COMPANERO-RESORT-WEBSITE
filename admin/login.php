<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!same_origin_request() || !valid_csrf_token($_POST['csrf_token'] ?? null)) {
        render_error_page(403, 'The sign-in request could not be verified. Reload the page and try again.');
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!is_string($username) || !is_string($password)) {
        $error = 'Invalid username or password.';
    } else {
        try {
            $auth = new Authentication();
            if ($auth->login($username, $password)) {
                header('Location: dashboard.php', true, 303);
                exit;
            }
            $error = $auth->getError();
        } catch (Throwable $exception) {
            error_log('Admin sign-in failed: ' . $exception->getMessage());
            http_response_code(503);
            $error = 'Sign-in is temporarily unavailable. Please try again later.';
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
    <title>Admin Sign In | Companero Resort</title>
    <meta name="robots" content="noindex,nofollow" />
    <link rel="stylesheet" href="../css/style.css" />
  </head>
  <body class="admin-page">
    <main class="admin-shell">
      <section class="admin-login">
        <a class="logo" href="../index.html">COMPANERO <span>RESORT.</span></a>
        <div class="admin-login-card">
          <div class="kicker">Staff inbox</div>
          <h1 class="serif">Sign in.</h1>
          <p class="muted">Guest requests are private. Sign in with an administrator account.</p>
          <form class="admin-form" method="post" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>" />
            <label for="admin-user">Username or email</label>
            <input id="admin-user" name="username" autocomplete="username" required maxlength="254" />
            <label for="admin-password">Password</label>
            <input id="admin-password" name="password" type="password" autocomplete="current-password" required maxlength="1024" />
            <button class="btn btn-primary" type="submit">Sign in</button>
            <p class="admin-error" role="alert"><?= escape_html($error) ?></p>
          </form>
        </div>
      </section>
    </main>
  </body>
</html>
