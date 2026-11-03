# Companero Resort Website Audit and Remediation

**Updated:** 2026-10-28  
**Scope:** 22 public HTML pages, CSS/JavaScript, PHP/MySQL application scaffold,
forms, admin authentication, setup, metadata, accessibility, and local checks.

## Architecture

The project now consists of the existing multi-page HTML/CSS/JavaScript site
plus a PHP 8+ and MySQL request service. Public inquiries are persisted in
MySQL and shown in an authenticated staff inbox. Stay and table submissions
are **requests only**: staff must verify availability and confirm them
manually. There is no live inventory, guest account, payment, or booking
confirmation workflow.

The resort supplied its public address, contact channels, contact hours, and
`Etc/GMT` time zone. The production host/domain, staff email-alert recipient
and transport, database credentials, current rates/services, booking policies,
privacy contact, and retention period remain unconfirmed. No deployment,
database, or email operation was performed.

## Prioritized findings and status

| Priority | Status | Finding / remediation |
|---|---|---|
| HIGH | Fixed in code; host check pending | Session cookies are `Secure`, `HttpOnly`, and `SameSite=Strict`; production PHP routes reject plain HTTP. Production requires HTTPS and the host must set PHP's HTTPS state correctly. |
| HIGH | Fixed in code; host setup pending | The browser-only admin login and local submission dashboard were removed. The staff inbox now requires a database-backed authenticated session, role checks, CSRF tokens, rate-limited sign-in, and output escaping. |
| HIGH | Fixed in code; end-to-end test pending | Contact, stay, table, and homepage availability flows now reach server-side validation and MySQL persistence. Live submission could not be verified without an isolated database and credentials. |
| HIGH | Fixed in code | The public setup page that created a known default administrator was replaced by a CLI-only setup command. The sample administrator/password seed was removed. |
| HIGH | Open deployment prerequisite | Production `DB_*`, `APP_*`, `RATE_LIMIT_SECRET`, admin setup, and server access-control settings are not configured. Do not publish the PHP app before these are set and tested. |
| HIGH | Open notification prerequisite | Production configuration now requires valid `MAIL_TO` and `MAIL_FROM` values. No destination or provider was provided; the host mail transport must be configured and verified before enabling production. |
| MEDIUM | Partially resolved | The supplied public address, contact details, map search link, 24/7 contact hours, and `Etc/GMT` time zone are now used consistently. The official domain, exact services, rates/menu, booking policies, privacy contact, and inquiry retention period still need confirmation. Fictional reviews, sample menu/prices, unsupported room specs, and unverified service promises were removed. |
| MEDIUM | Open legal prerequisite | The resort must approve the privacy contact, applicable legal text, and guest-data retention/deletion policy before launch. The privacy page now describes database storage, optional email copies, session cookies, and short-lived keyed rate-limit hashes, but is not legal advice. |
| MEDIUM | Fixed in code; host-specific testing pending | Apache rules deny direct access to private source/schema/setup paths and add basic security headers. Nginx or another host needs equivalent rules; no hosting configuration was supplied. |
| LOW | Fixed | The homepage availability widget now carries dates, guest count, and room choice into the complete reservation request form instead of saving an incomplete local-only record. |
| LOW | Fixed | Server validation now checks request types, required values, email/phone formats, UTF-8/length bounds, future dates, check-out ordering, guest counts, and table times. Forms include a honeypot and share rate limits. |
| LOW | Fixed | Added a staff dashboard with counts, filtering/search, mark-read state, super-admin deletion controls, CSRF protection, session timeout, password change, and confirmation on destructive actions. |
| LOW | Fixed | Removed the hard-coded demo login, browser-local guest submissions, fake mailto/tel links, unverified map embed, response-time promise, default admin hash, and fake structured business details. |

## Changes applied

- Added [api/submissions.php](./api/submissions.php) and
  [includes/submissions.php](./includes/submissions.php) for same-origin,
  bounded, server-validated request handling. JSON submissions support the
  JavaScript UI; normal URL-encoded POST supports no-JavaScript form fallback.
- Reworked [includes/config.php](./includes/config.php) and
  [includes/database.php](./includes/database.php) to use environment
  configuration, lazy PDO connections, hidden production errors, and explicit
  HTTPS/origin/time-zone requirements. The application now defaults to
  production mode and fails closed until deployment configuration is complete.
- Added [includes/security.php](./includes/security.php),
  [includes/rate-limiter.php](./includes/rate-limiter.php), and updated
  [includes/session.php](./includes/session.php) and
  [includes/auth.php](./includes/auth.php) with CSRF, secure cookies,
  session-ID rotation, prepared queries, password hashing, and rate limits
  keyed by short-lived HMACs rather than raw IP addresses.
- Added private [admin/login.php](./admin/login.php),
  [admin/dashboard.php](./admin/dashboard.php),
  [admin/logout.php](./admin/logout.php), and
  [admin/password.php](./admin/password.php). Replaced
  [admin/setup.php](./admin/setup.php) with CLI-only first-admin setup that
  requires operator-supplied environment credentials.
- Replaced [database/schema.sql](./database/schema.sql): no seeded admin,
  default password, or `CREATE DATABASE` operation. It defines request,
  administrator, and rate-limit tables.
- Updated [js/main.js](./js/main.js) and request forms to submit to the API,
  show errors/success references, prefill requested stay details, and avoid
  treating browser storage as a booking system. Reworked [js/admin.js](./js/admin.js)
  for staff inbox filtering only.
- Stored per-request notification status so the staff inbox can surface alerts
  that were not configured, failed, or whose outcome is unknown. A successful
  `mail()` return means only that the local transport accepted the message.
- Replaced the fake browser login at [admin.html](./admin.html) with a link to
  PHP authentication. Updated privacy/thank-you copy and
  [README.md](./README.md) with setup and deployment requirements.
- Added Apache [.htaccess](./.htaccess) protection and
  [.gitignore](./.gitignore) rules for environment secrets. Contact links and
  location/hours copy now use the resort details supplied by the owner.
- Removed public sample reviews, sample menu/prices, invented room
  specifications, unsupported service descriptions, and placeholder policy
  promises. Replaced these with accurate stay/dining inquiry instructions and
  contact information. The alternate homepage variants are noindexed.
- Set the application time zone to the supplied `Etc/GMT` value and extended
  PHP time-zone validation to accept its backward-compatible identifier.
- Added focused validation tests at [tests/submissions_test.php](./tests/submissions_test.php).

## Verification

- PHP syntax validation — all PHP source and test files passed `php -l`.
- JavaScript syntax — `node --check js\main.js` and
  `node --check js\admin.js` passed.
- Server-side validation tests — `php tests\submissions_test.php` passed for
  valid contact/stay/table payloads, guest counts, table-time normalization,
  invalid email, and invalid stay-date ordering.
- HTML audit — all 22 public pages passed checks for unique IDs, page headings,
  image alt attributes, local references, fragments, and form actions. Sitemap
  XML parsed successfully.
- Public-copy scan — no user-facing demo/concept/sample/illustrative text,
  pending-confirmation contact details, sample pricing, or fabricated reviews
  remain. The reservation form's textarea hint is normal input guidance.
- PHP local HTTP smoke test — public pages and admin login returned 200; an
  unauthenticated dashboard resolved to the login page; GET on the submission
  API returned 405; malformed form data returned 422; web access to setup
  returned 404.
- Browser request-flow smoke test — reservation query parameters populated
  check-in/out, guests, and room correctly. Submitting a valid synthetic test
  inquiry reached the API and displayed an explicit unavailable-service
  message because the isolated test configuration intentionally used a
  nonexistent database. No guest record was created or email sent.
- Production-config HTTP test — the API, login, and dashboard refused plain
  HTTP with status 400.
- `git diff --check` passed. Git emitted only existing LF/CRLF normalization
  warnings; the pre-existing working-tree changes were preserved.

## Not verified / remaining work

- No application database was connected to, created, or changed. The local
  MySQL service was not used. Database schema application, real submission
  persistence, session login against a seeded account, dashboard actions, and
  backup/restore remain untested.
- No email alert was sent. PHP `mail()` delivery, sender authentication,
  deliverability, and guest-data handling by the mail provider remain
  unverified.
- Hosting behavior is unknown. The included `.htaccess` rules apply to
  compatible Apache 2.4 hosts only; configure equivalent Nginx/server rules,
  HTTPS redirects, headers, document-root protection, and PHP HTTPS detection
  as appropriate.
- The database inbox stores contact details. The operator must choose and
  document an appropriate retention period and protect database backups and
  administrator credentials.
- The public domain and canonical/sitemap URLs cannot be finalized until the
  domain is chosen. Confirm the factual accuracy and licensing/ownership of
  property images before launch. Final rates, services, menu, reservation
  policies, privacy contact, and retention period remain owner decisions.
- No PHP/MySQL integration suite, automated accessibility scan, Lighthouse
  run, dependency vulnerability scan, or cross-browser/assistive-technology
  suite was available. There is no Composer/npm manifest or build pipeline.
- No commits were created. The pre-edit backup remains at
  `C:\Users\kanus\.copilot\session-state\b92e615e-e6b3-4aba-8154-078d275c3a1c\files\companero-before-audit.zip`.

## Safe start

See [README.md](./README.md) for requirements, environment variables, fresh
database setup, CLI administrator creation, local-only HTTP testing, and
production prerequisites. Do not run first-admin setup against an existing
production database without reviewing its contents and administrator accounts.
