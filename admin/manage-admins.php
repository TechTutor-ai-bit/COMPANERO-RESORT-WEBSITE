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

Permissions::require('manage_admins');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!same_origin_request() || !valid_csrf_token($_POST['csrf_token'] ?? null)) {
        render_error_page(403, 'The request could not be verified.');
    }

    $fullName = trim(is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : '');
    $username = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
    $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if ($fullName === '' || strlen($fullName) > 120 ||
        !preg_match('/^[A-Za-z0-9._-]{3,100}$/', $username) ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $error = 'Please enter valid account details.';
    } elseif (!in_array($role, ['manager', 'staff'], true)) {
        $error = 'Select a valid administrator role.';
    } elseif (strlen($password) < 14 || strlen($password) > 1024) {
        $error = 'The password must contain at least 14 characters.';
    } elseif (!hash_equals($password, $confirmPassword)) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $pdo = Database::connection();

            $stmt = $pdo->prepare(
                'INSERT INTO admins
                (full_name, username, email, password_hash, role, active)
                VALUES (?, ?, ?, ?, ?, TRUE)'
            );

            $stmt->execute([
                $fullName,
                $username,
                $email,
                Authentication::hashPassword($password),
                $role
            ]);

            header('Location: manage-admins.php?created=1', true, 303);
            exit;

        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'That username or email address is already registered.';
            } else {
                error_log('Administrator creation failed: ' . $exception->getMessage());
                $error = 'The account could not be created. Please try again.';
            }
        } catch (Throwable $exception) {
            error_log('Administrator creation failed: ' . $exception->getMessage());
            $error = 'The account could not be created. Please try again.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    render_error_page(405, 'This request method is not supported.');
}

try {
    $pdo = Database::connection();

    $admins = $pdo->query(
        'SELECT id, full_name, username, email, role, active, created_at, last_login
         FROM admins ORDER BY created_at DESC'
    )->fetchAll();

} catch (Throwable $exception) {
    error_log('Administrator list could not be loaded: ' . $exception->getMessage());
    render_error_page(503, 'Administrator accounts are temporarily unavailable.');
}

if (isset($_GET['created'])) {
    $success = 'Administrator account created successfully.';
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Administrators | Companero Resort</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-page">
<main class="admin-shell">

    <header class="admin-header">
        <div>
            <div class="kicker">Companero Resort - Administration</div>
            <h1 class="serif">Manage administrators</h1>
            <p class="muted">Create and review staff and manager accounts.</p>
        </div>
        <div class="admin-actions">
            <a class="btn btn-ghost btn-small" href="dashboard.php">Back to dashboard</a>
            <a class="btn btn-ghost btn-small" href="password.php">Change password</a>
        </div>
    </header>

    <section class="admin-login-card">
        <h2>Create administrator</h2>

        <form class="admin-form" method="post" action="manage-admins.php">
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrf) ?>">

            <label for="full-name">Full name</label>
            <input id="full-name" name="full_name" type="text" maxlength="120" autocomplete="name" required>

            <label for="username">Username</label>
            <input id="username" name="username" type="text" minlength="3" maxlength="100"
                   pattern="[A-Za-z0-9._-]+" autocomplete="username" required>

            <label for="email">Email address</label>
            <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required>

            <label for="role">Administrator role</label>
            <select id="role" name="role" required>
                <option value="manager">Manager</option>
                <option value="staff">Staff</option>
            </select>

            <label for="password">Initial password</label>
            <input id="password" name="password" type="password" minlength="14" maxlength="1024"
                   autocomplete="new-password" required>

            <label for="confirm-password">Confirm password</label>
            <input id="confirm-password" name="confirm_password" type="password"
                   minlength="14" maxlength="1024" autocomplete="new-password" required>

            <button class="btn btn-primary" type="submit">Create administrator</button>

            <?php if ($error !== ''): ?>
                <p class="admin-error" role="alert"><?= escape_html($error) ?></p>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <p class="admin-status" role="status"><?= escape_html($success) ?></p>
            <?php endif; ?>
        </form>
    </section>

    <section class="admin-list">
        <h2>Administrator accounts</h2>

        <?php if (!$admins): ?>
            <p>No administrator accounts found.</p>
        <?php endif; ?>

        <?php foreach ($admins as $admin): ?>
            <article class="admin-entry">
                <div class="admin-entry-head">
                    <strong><?= escape_html($admin['full_name']) ?></strong>
                    <span class="admin-badge">
                        <?= escape_html(ucwords(str_replace('_', ' ', $admin['role']))) ?>
                    </span>
                </div>

                <div class="admin-entry-fields">
                    <div><strong>Username</strong><span><?= escape_html($admin['username']) ?></span></div>
                    <div><strong>Email</strong><span><?= escape_html($admin['email']) ?></span></div>
                    <div>
                        <strong>Account status</strong>
                        <span><?= (bool) $admin['active'] ? 'Active' : 'Inactive' ?></span>
                    </div>
                    <div><strong>Created</strong><span><?= escape_html($admin['created_at']) ?></span></div>
                    <div>
                        <strong>Last login</strong>
                        <span><?= escape_html($admin['last_login'] ?? 'Never logged in') ?></span>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

</main>
</body>
</html>
