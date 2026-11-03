<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/database.php';

try {
    $name = trim(getenv('ADMIN_FULL_NAME') ?: '');
    $username = trim(getenv('ADMIN_USERNAME') ?: '');
    $email = trim(getenv('ADMIN_EMAIL') ?: '');
    $password = getenv('ADMIN_PASSWORD') ?: '';

    if (
        $name === ''
        || strlen($name) > 120
        || !preg_match('/^[A-Za-z0-9._-]{3,100}$/', $username)
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        || strlen($email) > 254
        || strlen($password) < 14
        || strlen($password) > 1024
    ) {
        throw new InvalidArgumentException(
            'Set valid ADMIN_FULL_NAME, ADMIN_USERNAME, ADMIN_EMAIL and ADMIN_PASSWORD values; password must be at least 14 characters.'
        );
    }

    $schemaPath = __DIR__ . '/../database/schema.sql';
    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException('Unable to read database schema.');
    }

    $pdo = Database::connection();
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $notificationColumn = $pdo->query("SHOW COLUMNS FROM submissions LIKE 'notification_status'")->fetch();
    if (!$notificationColumn) {
        $pdo->exec(
            "ALTER TABLE submissions ADD COLUMN notification_status
             ENUM('pending', 'not_configured', 'accepted', 'failed')
             NOT NULL DEFAULT 'not_configured' AFTER read_status"
        );
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($count > 0) {
        throw new RuntimeException('An admin account already exists; refusing to create another initial account.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO admins (full_name, username, email, password_hash, role, active)
         VALUES (?, ?, ?, ?, ?, TRUE)'
    );
    $stmt->execute([
        $name,
        $username,
        $email,
        password_hash($password, PASSWORD_DEFAULT, ['cost' => PASSWORD_COST]),
        'super_admin',
    ]);

    fwrite(STDOUT, "Database schema is ready and the initial administrator was created.\n");
} catch (Throwable $error) {
    error_log('Initial application setup failed: ' . $error->getMessage());
    fwrite(STDERR, 'Setup failed. Check the server environment and PHP error log; no credentials were printed.' . PHP_EOL);
    exit(1);
}
