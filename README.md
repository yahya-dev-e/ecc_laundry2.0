# ECC Laundry

A Laravel 12 reservation application for campus laundry. Blade, Alpine.js and Tailwind render the web UI; the SQL database owns users, reservations and the minute-based quota ledger. Start/completion currently update application state: physical machine control requires a separate verified hardware integration.

The original audit is in [audit/REPORT.md](audit/REPORT.md). The fixes and remaining release checks are mapped in [FIXES.md](FIXES.md). This branch is not a production deployment.

## Local installation

Use PHP 8.3+ with PDO SQLite or PDO MySQL, mbstring, OpenSSL, fileinfo, intl and ZIP; Composer 2; Node.js 18.8+ for frontend builds. Dependencies are locked. The server's Node 18.8.0 is supported by the pinned build stack (Vite 6.4.4, Laravel Vite plugin 1.3.0, Tailwind/PostCSS 4.1.18). Laravel/PHP serves the application; Node is not required to serve built production assets.

1. Copy .env.example to .env. Generate your own key with php artisan key:generate after composer install.
2. Run composer install and npm ci --ignore-scripts.
3. Create database/database.sqlite as an empty file, or configure a private MySQL database in .env. Leave DB_DATABASE unset for the default SQLite path.
4. Run php artisan migrate --seed and npm run build.
5. Run php artisan laundry:create-admin to create a private initial administrator.
6. Run php artisan serve --host=127.0.0.1, php artisan queue:work --tries=3 --timeout=60, and php artisan schedule:work in separate terminals.

The normal seeder creates machine inventory only and preserves existing machine states. Demo users require DEMO_SEED_ENABLED=true, a private DEMO_PASSWORD of at least 12 characters, and a local/testing environment. There are no public default passwords. The optional node preview-server.js command launches this same Laravel backend on loopback; it no longer provides separate simulated accounts or reservations.

MAIL_MAILER=log records mail locally; it does not deliver to an inbox. Configure SMTP before claiming external delivery.

## Rules

- Public signup requires only name, email, password and confirmation. All email domains are accepted and reservations are available immediately; email ownership is not verified. Student ID and room are optional legacy/admin metadata.

- Campus timezone defaults to Africa/Casablanca; the server decides available slots.
- Default booking window: today through the date seven days ahead, inclusive, in campus time. On Sunday 11 October, dates through Sunday 18 October are available, including its 23:00–Monday 00:00 slot. Past starts and reservations ending beyond that final midnight are rejected.
- Students receive eight hours per Monday–Sunday week; administrators receive a finite 100 hours. An administrator can set an individual limit.
- Browser bookings are one hour. Validated API/form requests can specify 20–120 minutes, charged in exact integer minutes.
- At most three active/upcoming reservations per account by default. A multi-slot request either commits all reservations and charges or none.
- A cycle starts only during its reserved start window, with 15 minutes of grace, on an operable machine without another active cycle.
- Future unstarted reservations can be cancelled. Cancellations and no-shows retain history and refund once. Completed cycles remain counted.
- Admin quota reset exempts existing reservations for the current week; new reservations still count, including after an exempt reservation is cancelled.
- Rolling advance booking is the default (`LAUNDRY_RESTRICT_CURRENT_WEEK=false`, `LAUNDRY_MAX_ADVANCE_DAYS=7`). Existing installations must update their .env if it explicitly enables the old current-week restriction. Quota remains Monday–Sunday and is checked against each reservation's target week. Set the restriction to true to restore the legacy window.

## Existing installations

Back up the database and test the upgrade on a copy first. Do not run migrate:fresh on existing data.

Run php artisan migrate --force, then php artisan laundry:reconcile-ledger to inspect missing or ambiguous historical accounting. The command is dry-run by default. Review its output before using --apply; it adds only unambiguous missing charge/refund entries and is repeatable. Duplicate/mismatched/orphan entries need manual review. Historical cancellations deleted by old versions cannot be reconstructed.

Legacy ledger amounts are converted from credit units to minutes. The migration corrects the reservation foreign key, normalizes email casing (refusing collisions), preserves identifiable active cycles, and keeps past legacy reservations classified as completed without issuing retroactive absence refunds. That historical classification is inherited data, not proof a physical cycle ran.

The upgrade intentionally refuses automatic rollback: use a reviewed backup/restore plan. Older reset links expire during the transition to the new reset-link store. Existing sessions without a session version must log in again. Stored legacy credits are not the quota source of truth.

## Container integration stack

Set private APP_KEY, DB_PASSWORD and a different DB_ROOT_PASSWORD in .env; use APP_URL=http://localhost:8000 for the local stack.

Run docker compose build, docker compose run --rm app php artisan migrate --seed --force, then docker compose up -d. Create the administrator with docker compose exec app php artisan laundry:create-admin. The worker and scheduler are separate services. MySQL is private to the compose network; the app and Mailpit UI bind loopback.

For production, configure APP_ENV=production, APP_DEBUG=false, a canonical HTTPS APP_URL, secure cookies, private SMTP credentials and a shared persistent APP_KEY for app/worker/scheduler. Terminate TLS at a correctly configured proxy, restrict accepted hostnames there too, and explicitly configure trusted proxies for that infrastructure. Do not broadly trust arbitrary forwarded headers. Mailpit is a local integration capture, not production email.

Run queue workers under a supervisor, restart them when deploying code, monitor failed_jobs, retry failures after resolving the cause, and run the scheduler continuously. Queued email retries three times. SMTP acceptance and database commits cannot provide exactly-once external delivery after an ambiguous transport failure.

## Verification

    composer audit --locked
    npm audit --audit-level=moderate
    npm run build
    php vendor/phpunit/phpunit/phpunit --fail-on-warning
    php tests/integration/upgrade.php
    php tests/integration/concurrency.php
    php tests/integration/cycle-outage.php
    node tests/integration/smtp.mjs
    npm ci --prefix tests/browser
    npm exec --prefix tests/browser -- playwright install chromium firefox webkit
    node tests/browser/run.mjs

Browser tests run on a development/CI machine with Node 22+, independently of the Node 18-compatible server build. Their Playwright dependencies and lockfile live in tests/browser. They create their own SQLite database, use synthetic example.test accounts, and exercise the real public/index.php with CSRF enabled. The fixed clock exists only in the test router. Set BROWSER_ENGINES=chromium,firefox,edge,webkit to include an installed Edge. Optional TEST_PHP_INI and PLAYWRIGHT_MODULE_DIR support external test runtimes.

The concurrency test uses separate PHP processes. It normally creates an isolated SQLite file. MySQL runs require LAUNDRY_TEST_DB=mysql and an explicitly configured disposable database whose name ends in _test; that database is reset.

ci/audit-fixes.yml.example contains a ready-to-enable workflow for Windows/macOS/Linux browser and backend checks, MySQL tests and a container build. Move it into .github/workflows/ with an account/token allowed to edit workflows. The current GitHub token has repo access but no workflow scope, so this template is not activated and no remote result is claimed. Current local results and compatibility limitations are in FIXES.md.

Settings and complaints are not implemented features and are not linked as working pages. Availability polls every 30 seconds; dashboards/calendars offer a refresh notice rather than claiming a clock is real-time data synchronization.

See PRODUCTION-READINESS.md for the 13 October deeper review and remaining server checks. Run php artisan laundry:readiness for a read-only deployment diagnostic; it does not certify external mail delivery or running workers/scheduler. SMTP connection timeout defaults to ten seconds (MAIL_TIMEOUT).

Queued welcome, password-reset and cycle-completion mail is discarded once its underlying event is older than LAUNDRY_MAX_EMAIL_DELAY_MINUTES (default 15). Completion age uses the reservation's scheduled end, including when scheduler completion was delayed. Skipping mail does not cancel a reservation or invalidate an already delivered reset link. Completion recovery only requeues recent alerts. Failed-job history is retained unless explicitly cleared.
