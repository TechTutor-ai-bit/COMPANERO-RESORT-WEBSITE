<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/database.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

if (!SessionManager::isAuthenticated()) {
    header('Location: login.php', true, 303);
    exit;
}
Permissions::require('view_dashboard');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!same_origin_request() || !valid_csrf_token($_POST['csrf_token'] ?? null)) {
        render_error_page(403, 'The update request could not be verified.');
    }
    try {
        $pdo = Database::connection();
        $action = $_POST['action'] ?? '';
        if ($action === 'mark_read' && is_string($_POST['id'] ?? null)
            && preg_match('/^[a-f0-9-]{36}$/i', $_POST['id'])) {
            $stmt = $pdo->prepare(
                'UPDATE submissions SET read_status = TRUE, handled_by = ?, handled_at = UTC_TIMESTAMP() WHERE id = ?'
            );
            $stmt->execute([SessionManager::getAdminId(), $_POST['id']]);
        } elseif (!Permissions::isManagerOrHigher()) {
            render_error_page(403, 'You do not have permission to change requests.');
        } elseif ($action === 'delete' && is_string($_POST['id'] ?? null)
            && preg_match('/^[a-f0-9-]{36}$/i', $_POST['id'])) {
            $stmt = $pdo->prepare('DELETE FROM submissions WHERE id = ?');
            $stmt->execute([$_POST['id']]);
        } elseif ($action === 'delete_all' && Permissions::isSuperAdmin()) {
            $pdo->exec('DELETE FROM submissions');
        } else {
            render_error_page(400, 'The requested change is not valid or you are not allowed to perform it.');
        }
        header('Location: dashboard.php?updated=1', true, 303);
        exit;
    } catch (Throwable $exception) {
        error_log('Admin request update failed: ' . $exception->getMessage());
        render_error_page(503, 'The request could not be updated. Please try again later.');
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    render_error_page(405, 'This request method is not supported.');
}

try {
    $pdo = Database::connection();
    $counts = $pdo->query(
        "SELECT COUNT(*) AS all_count,
            SUM(type = 'message') AS message_count,
            SUM(type = 'reservation') AS reservation_count,
            SUM(type = 'table') AS table_count
         FROM submissions"
    )->fetch();
    $submissions = $pdo->query(
        'SELECT id, type, full_name, email, phone, subject, message,
            check_in_date, check_out_date, number_of_guests, special_requests,
            table_date, table_time, table_guests, created_at, read_status, notification_status
         FROM submissions ORDER BY created_at DESC LIMIT 500'
    )->fetchAll();
} catch (Throwable $exception) {
    error_log('Admin inbox could not be loaded: ' . $exception->getMessage());
    render_error_page(503, 'The staff inbox is temporarily unavailable. Please try again later.');
}

$csrf = csrf_token();
$labels = ['message' => 'Message', 'reservation' => 'Reservation request', 'table' => 'Table request'];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Staff Inbox | Companero Resort</title>
    <meta name="robots" content="noindex,nofollow" />
    <link rel="stylesheet" href="../css/style.css" />
  </head>
  <body class="admin-page">
    <main class="admin-shell">
      <header class="admin-header">
        <div>
          <div class="kicker">Companero Resort · Staff inbox</div>
          <h1 class="serif">Guest requests</h1>
          <p class="muted">Requests are not confirmed bookings. Contact guests to verify details and availability.</p>
        </div>
        <div class="admin-actions">
          <a class="btn btn-ghost btn-small" href="../index.html">View site</a>
          <?php if (Permissions::can('edit_content')): ?>
            <a class="btn btn-primary btn-small" href="content.php">Edit site content</a>
          <?php endif; ?>
          <a class="btn btn-ghost btn-small" href="password.php">Change password</a>
          <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrf) ?>" />
            <button class="btn btn-ghost btn-small" type="submit">Log out</button>
          </form>
        </div>
      </header>
      <?php if (MAIL_TO === '' || MAIL_FROM === ''): ?>
        <p class="admin-status" role="status">Email alerts are not configured. Check this inbox regularly until an approved mail destination and delivery service are set up.</p>
      <?php endif; ?>
      <section class="admin-stats" aria-label="Request totals">
        <?php foreach ([
            'all' => ['All requests', 'all_count'],
            'message' => ['Messages', 'message_count'],
            'reservation' => ['Stay requests', 'reservation_count'],
            'table' => ['Table requests', 'table_count'],
        ] as $key => [$label, $countKey]): ?>
          <div><strong><?= (int) ($counts[$countKey] ?? 0) ?></strong><span><?= escape_html($label) ?></span></div>
        <?php endforeach; ?>
      </section>
      <section class="admin-toolbar" aria-label="Filter inbox">
        <div class="admin-tabs" role="group" aria-label="Filter requests">
          <?php foreach (['all' => 'All', 'message' => 'Messages', 'reservation' => 'Stay requests', 'table' => 'Tables'] as $filter => $label): ?>
            <button class="tab<?= $filter === 'all' ? ' active' : '' ?>" type="button" data-admin-filter="<?= escape_html($filter) ?>" aria-pressed="<?= $filter === 'all' ? 'true' : 'false' ?>"><?= escape_html($label) ?></button>
          <?php endforeach; ?>
        </div>
        <label class="visually-hidden" for="admin-search">Search requests</label>
        <input id="admin-search" class="admin-search" type="search" placeholder="Search requests" data-admin-search />
        <?php if (Permissions::isSuperAdmin() && $submissions): ?>
          <form method="post" action="dashboard.php" data-confirm="Delete every request? This cannot be undone.">
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrf) ?>" />
            <input type="hidden" name="action" value="delete_all" />
            <button class="btn btn-ghost btn-small" type="submit">Clear all</button>
          </form>
        <?php endif; ?>
      </section>
      <p class="admin-status" role="status" aria-live="polite">
        <?= isset($_GET['updated']) ? 'Request list updated.' : '' ?>
        <?= isset($_GET['password_updated']) ? 'Password updated.' : '' ?>
        <?= count($submissions) === 500 ? 'Showing the 500 most recent requests.' : '' ?>
      </p>
      <section class="admin-list" data-admin-list aria-label="Guest requests">
        <?php if (!$submissions): ?>
          <div class="admin-empty"><strong>No requests yet</strong><span>Requests submitted through the website will appear here.</span></div>
        <?php endif; ?>
        <?php foreach ($submissions as $submission): ?>
          <?php
          $type = $submission['type'];
          $details = [
              'Name' => $submission['full_name'],
              'Email' => $submission['email'],
              'Phone' => $submission['phone'],
              'Room / occasion' => $submission['subject'],
              'Check-in' => $submission['check_in_date'],
              'Check-out' => $submission['check_out_date'],
              'Guests' => $submission['number_of_guests'] ?? $submission['table_guests'],
              'Table date' => $submission['table_date'],
              'Table time' => $submission['table_time'],
              'Message' => $submission['message'],
              'Special requests' => $submission['special_requests'],
          ];
          $searchable = implode(' ', array_filter(array_map('strval', $details)));
          ?>
          <article class="admin-entry" data-request-type="<?= escape_html($type) ?>" data-request-search="<?= escape_html($searchable) ?>">
            <div class="admin-entry-head">
              <span class="admin-badge"><?= escape_html($labels[$type] ?? 'Request') ?></span>
              <time datetime="<?= escape_html(date(DATE_ATOM, strtotime($submission['created_at']))) ?>"><?= escape_html(date('M j, Y H:i', strtotime($submission['created_at']))) ?></time>
              <?php if (!(bool) $submission['read_status']): ?><span class="admin-unread">New</span><?php endif; ?>
              <?php if (!(bool) $submission['read_status']): ?>
                <form method="post" action="dashboard.php" class="admin-read-form">
                  <input type="hidden" name="csrf_token" value="<?= escape_html($csrf) ?>" />
                  <input type="hidden" name="action" value="mark_read" />
                  <input type="hidden" name="id" value="<?= escape_html($submission['id']) ?>" />
                  <button class="btn btn-ghost btn-small" type="submit">Mark read</button>
                </form>
              <?php endif; ?>
              <?php if (Permissions::isManagerOrHigher()): ?>
                <form method="post" action="dashboard.php" class="admin-delete-form" data-confirm="Delete this request? This cannot be undone.">
                  <input type="hidden" name="csrf_token" value="<?= escape_html($csrf) ?>" />
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= escape_html($submission['id']) ?>" />
                  <button class="admin-delete" type="submit" aria-label="Delete request">×</button>
                </form>
              <?php endif; ?>
            </div>
            <?php if ($submission['notification_status'] === 'failed'): ?>
              <p class="admin-status" role="status">Email alert was not accepted by the configured mail service.</p>
            <?php elseif ($submission['notification_status'] === 'not_configured'): ?>
              <p class="admin-status" role="status">No email alert was configured for this request.</p>
            <?php elseif ($submission['notification_status'] === 'pending'): ?>
              <p class="admin-status" role="status">Email alert status is pending or could not be recorded.</p>
            <?php endif; ?>
            <div class="admin-entry-fields">
              <?php foreach ($details as $label => $value): ?>
                <?php if ($value !== null && $value !== ''): ?>
                  <div><strong><?= escape_html($label) ?></strong><span><?= escape_html($value) ?></span></div>
                <?php endif; ?>
              <?php endforeach; ?>
              <div><strong>Reference</strong><span><?= escape_html($submission['id']) ?></span></div>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
      <p class="admin-status" data-admin-status role="status" aria-live="polite"></p>
    </main>
    <script src="../js/admin.js" defer></script>
  </body>
</html>
