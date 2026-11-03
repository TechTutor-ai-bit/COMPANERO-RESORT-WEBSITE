# Companero Resort Website

This project contains the Companero Resort website and a PHP/MySQL request
inbox. Public forms submit guest inquiries to the backend; staff must verify
availability and confirm every stay or table request manually. The system does
not manage inventory, take payments, or confirm bookings.

## Requirements

- PHP 8.0 or newer with PDO MySQL, session, and standard mail support if email
  alerts are desired.
- MySQL 8.0 or a compatible MariaDB server.
- HTTPS in production.
- An Apache 2.4 host that honors `.htaccess`, or equivalent server rules to
  deny direct web access to `includes/`, `database/`, `logs/`, hidden files, and
  `admin/setup.php`. The included access rules do not configure Nginx.
- Configure and verify a Content Security Policy on the host after confirming
  the required third-party image/font origins. No restrictive CSP is guessed
  because the current pages load remote image and font assets.

There is no Composer/npm dependency manifest or automated package build.

## Configuration

Set these as environment variables in the hosting control panel or process
manager. Do not commit credentials or secrets:

| Variable | Purpose |
|---|---|
| `APP_ENV` | Defaults to `production`; set to `development` only for local testing. |
| `APP_URL` | Full HTTPS site URL, e.g. `https://your-approved-domain.example`. Replace with the real approved domain. |
| `APP_ORIGIN` | Exact origin only (scheme, host, optional port), e.g. `https://your-approved-domain.example`; must match `APP_URL`. |
| `APP_TIMEZONE` | IANA time zone for server-side booking-date checks and staff display. The resort supplied `Etc/GMT`; override only if the owner confirms a different zone. |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Database connection. Create a dedicated least-privilege app account; do not use MySQL root. |
| `RATE_LIMIT_SECRET` | At least 32 random characters, unique to this deployment. |
| `SESSION_COOKIE_SECURE` | Keep `true` in production; only set `false` for local HTTP testing. |
| `SESSION_COOKIE_PATH` | Usually `/`; change only if the site is hosted under a subpath. |
| `MAIL_TO`, `MAIL_FROM` | Approved staff-alert recipient and sender email. Both are required in production. The PHP host must have a working mail transport. |

Email alerts use PHP's configured `mail()` transport. Delivery depends on the
host's mail setup, sender-domain authorization, and spam controls. The public
resort email is listed on the Contact page, but the staff-alert recipient and
mail provider have not been confirmed or tested. The database inbox remains
the source of truth; do not enable production email until delivery is verified.
The dashboard records whether the local mail transport accepted the message;
this does not prove final delivery.

## Database and first administrator

1. Create an empty UTF-8 `companero_resort` database (or set `DB_NAME` to the
   host-created database) and configure the environment variables above.
2. Provide `ADMIN_FULL_NAME`, `ADMIN_USERNAME`, `ADMIN_EMAIL`, and
   `ADMIN_PASSWORD` to a trusted CLI process. The initial password must be at
   least 14 characters. Do not put credentials in source code or commit them.
3. Run `php admin/setup.php` from the project directory using that environment.
   The command is CLI-only, creates the schema, and refuses to create another
   initial account if an administrator already exists. The database user must
   be able to create the tables. After setup, remove the temporary administrator
   environment variables.
4. Sign in at `/admin/login.php` over HTTPS and change the initial password.
   The staff inbox is at `/admin/dashboard.php`.

The setup command does not create a database or alter an existing admin
account. Review existing production data and administrator accounts separately
before any migration; this project has not connected to or changed a live
database.

The production PHP routes reject non-HTTPS requests. Configure the web server
or trusted TLS-terminating proxy to enforce HTTPS and set PHP's `HTTPS` server
flag correctly; do not rely on an untrusted forwarded-protocol header.

## Local development

Run the site through PHP rather than a static-only server so the API and admin
routes are executed. Configure an isolated development database and least-
privilege account first. For local HTTP only, use:

```powershell
$env:APP_ENV = 'development'
$env:APP_URL = 'http://127.0.0.1:8765'
$env:APP_ORIGIN = 'http://127.0.0.1:8765'
$env:SESSION_COOKIE_SECURE = 'false'
php -S 127.0.0.1:8765 -t .
```

Set the `DB_*` and initial `ADMIN_*` values through your local environment
manager before running setup. Never reuse local credentials in production.
Do not use the development server as a production host.

## Data and privacy

- Guest inquiries are stored in the MySQL database and visible only in the
  authenticated staff inbox. User-provided content is validated server-side and
  encoded on output.
- Email notifications are optional and can duplicate guest details in email.
  Configure an approved recipient only after confirming mail delivery and the
  privacy implications.
- No payment-card data should be submitted or stored.
- The resort has supplied its public address, contact channels, 24/7 contact
  hours, and `Etc/GMT` time zone. The production domain, current services and
  room details, menu/prices, reservation policies, privacy contact, and
  inquiry retention period still require owner confirmation before launch.
- Content that depended on unverified reviews, rates, room specifications,
  menu items, and service promises has been removed from public pages.
- Configure production canonical URLs, sitemap entries, cache policy, and a
  Content Security Policy only after the actual domain and external assets are
  verified.

## Editing website text (admin "Site content")

Managers and super admins can change the wording on the public pages and the
site-wide contact details from `admin/content.php` (button "Edit site content"
on the staff dashboard). Staff accounts cannot edit content.

- Pick a page, change the text boxes, press **Save**. Changes show on the live
  site straight away. **Restore original** puts back the wording from the HTML
  file; an empty box also means "use the original".
- **Contact details** (main phone, second phone/WhatsApp, email, address) are
  updated in the footer and contact pages on every page.
- Saved text is stored in the `site_content` table and applied by
  `js/content.js` through `api/content.php`. The original wording stays in the
  HTML files, so if the database or script is unavailable the pages simply show
  the original text. Saved text is inserted as plain text only (`<em>`,
  `<strong>` and line breaks are the only formatting supported).
- Every save and reset is recorded in `admin_logs`.

Deploying: upload the new files, then create the table by running
`database/migrations/2026-11-site-content.sql` (the editor also creates it
automatically when the database user is allowed to create tables).

Making more text editable: add `data-edit="unique-key"` (letters, numbers and
dashes; unique within the page) to any plain-text element inside `<main>`.
Meta titles/descriptions, images, and text inside forms are not editable here,
and words styled with other tags (for example links inside a sentence) are left
as written in the HTML file.

## Validation

Run PHP syntax checks on every PHP file with `php -l`, and JavaScript syntax
checks with `node --check js\main.js` and `node --check js\admin.js`, `node --check js\content.js` and `node --check js\admin-content.js`. A real
end-to-end form, login, database, and email test requires an isolated MySQL
database and a configured mail transport.
