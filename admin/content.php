<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/content.php';

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

if (!SessionManager::isAuthenticated()) {
    header('Location: login.php', true, 303);
    exit;
}
Permissions::require('edit_content');

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    render_error_page(405, 'This request method is not supported.');
}

$page = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['page'] ?? '') : ($_GET['page'] ?? '');
if (!is_string($page) || ($page !== '' && $page !== 'site' && !SiteContent::isPage($page))) {
    render_error_page(404, 'That page could not be found.');
}

function content_log(PDO $pdo, string $action, string $details): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            SessionManager::getAdminId(),
            $action,
            mb_substr($details, 0, 255),
            mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    } catch (Throwable $exception) {
        error_log('Admin log entry failed: ' . $exception->getMessage());
    }
}

$error = '';
$posted = [];
$fields = [];

try {
    $pdo = Database::connection();
    SiteContent::ensureTable($pdo);
} catch (Throwable $exception) {
    error_log('Content editor could not start: ' . $exception->getMessage());
    render_error_page(503, 'The content editor is temporarily unavailable. Please try again later.');
}

if ($page !== '' && $page !== 'site') {
    $fields = SiteContent::fields($page);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!same_origin_request() || !valid_csrf_token($_POST['csrf_token'] ?? null)) {
        render_error_page(403, 'The update request could not be verified. Reload the page and try again.');
    }
    $action = $_POST['action'] ?? '';
    $adminId = SessionManager::getAdminId();

    try {
        if ($page === '') {
            render_error_page(400, 'Choose a page to edit first.');
        } elseif ($action === 'reset') {
            $stmt = $pdo->prepare('DELETE FROM site_content WHERE page = ?');
            $stmt->execute([$page]);
            content_log($pdo, 'content_reset', 'Restored original text for ' . $page);
            header('Location: content.php?page=' . rawurlencode($page) . '&reset=1', true, 303);
            exit;
        } elseif ($action === 'save') {
            // Build the list of [key, submitted value, original value] to compare.
            $items = [];
            if ($page === 'site') {
                $input = $_POST['site'] ?? [];
                foreach (SiteContent::SITE_FIELDS as $key => $meta) {
                    $raw = is_array($input) && is_string($input[$key] ?? null) ? $input[$key] : '';
                    $value = SiteContent::normalize($raw);
                    $posted[$key] = $value;
                    if ($value === '') {
                        $value = $meta['default'];
                        $posted[$key] = $value;
                    }
                    $problem = SiteContent::validateSiteValue($key, $value);
                    if ($problem !== null && $error === '') {
                        $error = $problem;
                    }
                    $items[] = [$key, $value, $meta['default']];
                }
            } else {
                $input = $_POST['f'] ?? [];
                foreach ($fields as $field) {
                    $key = $field['key'];
                    if (!is_array($input) || !is_string($input[$key] ?? null)) {
                        continue;
                    }
                    $value = SiteContent::normalize($input[$key]);
                    $posted[$key] = $value;
                    if ($value === '') {
                        $value = $field['default'];
                        $posted[$key] = $value;
                    }
                    if (mb_strlen($value) > SiteContent::MAX_LENGTH && $error === '') {
                        $error = 'Each item can be up to ' . SiteContent::MAX_LENGTH . ' characters. Shorten the longer text and try again.';
                    }
                    $items[] = [$key, $value, $field['default']];
                }
            }

            if ($error === '') {
                $before = SiteContent::overrides($pdo, $page);
                $changed = 0;
                $pdo->beginTransaction();
                $upsert = $pdo->prepare(
                    'INSERT INTO site_content (page, content_key, content_value, updated_by) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_by = VALUES(updated_by)'
                );
                $remove = $pdo->prepare('DELETE FROM site_content WHERE page = ? AND content_key = ?');
                foreach ($items as [$key, $value, $default]) {
                    if ($value === $default) {
                        if (isset($before[$key])) {
                            $remove->execute([$page, $key]);
                            $changed++;
                        }
                    } elseif (($before[$key] ?? null) !== $value) {
                        $upsert->execute([$page, $key, $value, $adminId]);
                        $changed++;
                    }
                }
                $pdo->commit();
                if ($changed > 0) {
                    content_log($pdo, 'content_update', $changed . ' change(s) on ' . $page);
                }
                header('Location: content.php?page=' . rawurlencode($page) . '&saved=' . $changed, true, 303);
                exit;
            }
        } else {
            render_error_page(400, 'The requested change is not valid.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Content update failed: ' . $exception->getMessage());
        render_error_page(503, 'The changes could not be saved. Please try again later.');
    }
}

try {
    $counts = SiteContent::overrideCounts($pdo);
    $saved = ($page !== '') ? SiteContent::overrides($pdo, $page) : [];
} catch (Throwable $exception) {
    error_log('Content editor could not load: ' . $exception->getMessage());
    render_error_page(503, 'The content editor is temporarily unavailable. Please try again later.');
}

$csrf = csrf_token();
$title = $page === 'site' ? 'Contact details' : ($page === '' ? 'Site content' : SiteContent::PAGES[$page]);
$status = '';
if (isset($_GET['saved'])) {
    $n = (int) $_GET['saved'];
    $status = $n > 0
        ? 'Saved. ' . $n . ' change' . ($n === 1 ? '' : 's') . ' will show on the website straight away.'
        : 'Nothing was changed.';
} elseif (isset($_GET['reset'])) {
    $status = 'Original text restored for this page.';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= escape_html($title) ?> | Companero Resort Admin</title>
    <meta name="robots" content="noindex,nofollow" />
    <link rel="stylesheet" href="../css/style.css" />
  </head>
  <body class="admin-page">
    <main class="admin-shell">
      <header class="admin-header">
        <div>
          <div class="kicker">Companero Resort · Site content</div>
          <h1 class="serif"><?= escape_html($title) ?></h1>
          <?php if ($page === ''): ?>
            <p class="muted">Change the wording on your website pages. Changes appear on the live site straight away, and you can always restore the original text.</p>
          <?php elseif ($page === 'site'): ?>
            <p class="muted">These details are updated in the footer and on the contact pages across the whole website.</p>
          <?php else: ?>
            <p class="muted">Edit the text below, then press Save. Leave a box empty to use the original text.</p>
          <?php endif; ?>
        </div>
        <div class="admin-actions">
          <?php if ($page !== ''): ?>
            <a class="btn btn-ghost btn-small" href="content.php">All pages</a>
          <?php endif; ?>
          <?php if ($page !== '' && $page !== 'site'): ?>
            <a class="btn btn-ghost btn-small" href="../<?= escape_html($page) ?>.html" target="_blank" rel="noopener">View page</a>
          <?php endif; ?>
          <a class="btn btn-ghost btn-small" href="dashboard.php">Guest requests</a>
          <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrf) ?>" />
            <button class="btn btn-ghost btn-small" type="submit">Log out</button>
          </form>
        </div>
      </header>

      <p class="admin-status" role="status" aria-live="polite"><?= escape_html($status) ?></p>
      <?php if ($error !== ''): ?>
        <p class="admin-error" role="alert"><?= escape_html($error) ?></p>
      <?php endif; ?>

      <?php if ($page === ''): ?>
        <section class="content-pages" aria-label="Pages you can edit">
          <a class="content-page-card" href="content.php?page=site">
            <strong>Contact details</strong>
            <span>Phone numbers, email and address shown across the whole site</span>
            <em><?= (int) ($counts['site'] ?? 0) > 0 ? (int) $counts['site'] . ' edited' : 'Original' ?></em>
          </a>
          <?php foreach (SiteContent::PAGES as $slug => $label): ?>
            <a class="content-page-card" href="content.php?page=<?= escape_html(rawurlencode($slug)) ?>">
              <strong><?= escape_html($label) ?></strong>
              <span><?= escape_html($slug) ?>.html</span>
              <em><?= (int) ($counts[$slug] ?? 0) > 0 ? (int) $counts[$slug] . ' edited' : 'Original' ?></em>
            </a>
          <?php endforeach; ?>
        </section>
      <?php elseif ($page === 'site'): ?>
        <form method="post" action="content.php" class="content-form" data-content-form>
          <input type="hidden" name="csrf_token" value="<?= escape_html($csrf) ?>" />
          <input type="hidden" name="page" value="site" />
          <input type="hidden" name="action" value="save" />
          <section class="content-group">
            <?php foreach (SiteContent::SITE_FIELDS as $key => $meta): ?>
              <?php $current = $posted[$key] ?? ($saved[$key] ?? $meta['default']); ?>
              <div class="content-field">
                <label for="site-<?= escape_html($key) ?>"><?= escape_html($meta['label']) ?></label>
                <input id="site-<?= escape_html($key) ?>" name="site[<?= escape_html($key) ?>]" type="<?= $key === 'email' ? 'email' : 'text' ?>" maxlength="<?= (int) $meta['max'] ?>" value="<?= escape_html($current) ?>" data-default="<?= escape_html($meta['default']) ?>" required />
                <div class="content-meta">
                  <?php if (isset($saved[$key])): ?><span class="admin-badge">Edited</span><?php endif; ?>
                  <button class="content-restore" type="button" data-restore>Restore original</button>
                </div>
              </div>
            <?php endforeach; ?>
          </section>
          <div class="content-savebar">
            <button class="btn btn-primary" type="submit">Save changes</button>
          </div>
        </form>
      <?php else: ?>
        <?php if (!$fields): ?>
          <div class="admin-empty"><strong>No editable text found</strong><span>This page has no editable items yet.</span></div>
        <?php else: ?>
          <form method="post" action="content.php" class="content-form" data-content-form>
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrf) ?>" />
            <input type="hidden" name="page" value="<?= escape_html($page) ?>" />
            <input type="hidden" name="action" value="save" />
            <?php
            $groups = [];
            foreach ($fields as $field) {
                $groups[$field['group']][] = $field;
            }
            ?>
            <?php foreach ($groups as $groupName => $groupFields): ?>
              <section class="content-group" aria-label="<?= escape_html($groupName) ?>">
                <h2 class="content-group-title"><?= escape_html($groupName) ?></h2>
                <?php foreach ($groupFields as $field): ?>
                  <?php
                  $key = $field['key'];
                  $current = $posted[$key] ?? ($saved[$key] ?? $field['default']);
                  $rows = max(2, min(8, (int) ceil(mb_strlen($current) / 70) + substr_count($current, "\n")));
                  $usesEmphasis = str_contains($field['default'], '<em>') || str_contains($field['default'], '<strong>');
                  ?>
                  <div class="content-field">
                    <label for="f-<?= escape_html($key) ?>"><?= escape_html($field['type']) ?> <small><?= escape_html($key) ?></small></label>
                    <textarea id="f-<?= escape_html($key) ?>" name="f[<?= escape_html($key) ?>]" rows="<?= $rows ?>" maxlength="<?= SiteContent::MAX_LENGTH ?>" data-default="<?= escape_html($field['default']) ?>"><?= escape_html($current) ?></textarea>
                    <div class="content-meta">
                      <?php if (isset($saved[$key])): ?><span class="admin-badge">Edited</span><?php endif; ?>
                      <?php if ($usesEmphasis): ?><span class="muted">Keep the &lt;em&gt; and &lt;strong&gt; tags around words that should be highlighted.</span><?php endif; ?>
                      <button class="content-restore" type="button" data-restore>Restore original</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </section>
            <?php endforeach; ?>
            <div class="content-savebar">
              <button class="btn btn-primary" type="submit">Save changes</button>
              <a class="btn btn-ghost" href="content.php">Cancel</a>
            </div>
          </form>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($page !== '' && ($page === 'site' || $fields) && $saved): ?>
        <form method="post" action="content.php" class="content-reset" data-confirm="Restore the original text on <?= $page === 'site' ? 'the contact details' : 'this page' ?>? Your edits will be removed.">
          <input type="hidden" name="csrf_token" value="<?= escape_html($csrf) ?>" />
          <input type="hidden" name="page" value="<?= escape_html($page) ?>" />
          <input type="hidden" name="action" value="reset" />
          <button class="btn btn-ghost btn-small" type="submit">Restore all original text on this page</button>
        </form>
      <?php endif; ?>
    </main>
    <script src="../js/admin.js" defer></script>
    <script src="../js/admin-content.js" defer></script>
  </body>
</html>
