# Reproducing the isolated audit

Read `REPORT.md` before interpreting the results. The probes reproduce both working behavior and defects. A passing defect probe is not a passing security requirement.

Requirements: PHP 8.3 with PDO SQLite, mbstring, fileinfo, curl, zip, intl and OpenSSL; Composer libraries for this Laravel application; Node.js; Playwright with the selected browser engines; built Vite assets. Use an isolated checkout of the audit branch. Do not point any audit command at a production database or mail server.

1. Install dependencies and build assets. At review time a clean Composer resolution was blocked by security advisories; resolve those for a production build. The audit's runtime-library reuse is documented in the report.
2. Create an appropriate local PHP ini if your PHP installation needs extensions enabled. The reviewer used an untracked `audit/.runtime/php.ini`.
3. Run `php audit/setup.php` once. This creates only `audit/.runtime/test.sqlite`, synthetic `example.test` users and `AUDIT-WASHER`. It uses a deliberately predictable audit-only key/password, so never deploy the audit router.
4. Run `php vendor/phpunit/phpunit/phpunit --no-configuration --bootstrap vendor/autoload.php audit/AuditProbeTest.php --testdox`. The synthetic records are wrapped in transactions. The test date is fixed to Monday 12 October 2026 to make quota/window checks repeatable.
5. Start `php -S 127.0.0.1:8765 audit/router.php`. This router substitutes the missing public entry point and base controller; it serves the real Laravel routes and Blade pages.
6. Set `PLAYWRIGHT_MODULE_DIR` to a directory containing the `playwright` package, and `PLAYWRIGHT_BROWSERS_PATH` if using a custom browser directory. Run `node audit/browser-check.mjs`, then `node audit/workflow-check.mjs`.
7. Set `AUDIT_ENGINES=firefox,edge` and `AUDIT_RESULTS=browser-extra-results.json` to select a subset and preserve a separate results file. Firefox uses viewport emulation; it does not implement Chromium's `isMobile` option. The workflow test requires at least one future hourly slot remaining today, since the current app only permits this week's bookings.
8. Run `node audit/smtp-check.mjs`. It binds SMTP only on `127.0.0.1:8766` and launches PHP to send two real notification templates to that local capture. No external email is sent.

The actual reviewer used `php -c audit/.runtime/php.ini` for PHP commands, and Windows Chrome for Testing, Playwright Firefox, and installed Edge. For Firefox, the initial dependency-check utility was unavailable; host dependency validation was skipped for the run and Firefox itself launched successfully. This does not skip application checks.

`browser-check.mjs` records observation fields rather than asserting all behavior is desirable. For example, `visibleValidationError:false`, `destination:/dashboard` on an add-user click, and `successMessage:false` on edit-save are failures of the product even when the action's `ok` field is true. Screenshots are local evidence and ignored by Git; result JSON and textual test summaries are committed. Live session cookies, generated password-reset tokens, captured MIME and the SQLite database are not committed. The helpers contain explicitly synthetic test credentials and a test-only reset-link marker.

The audit router, bootstrap and seed credentials are local testing helpers. Do not wire them into production startup, public routes, or a deployment image.
