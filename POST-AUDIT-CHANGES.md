# Complete post-audit change record

Created: 11 October 2026. Updated: 13 October 2026. Repository: yahya-dev-e/ecc_laundry2.0.

This records the implemented website changes after audit commit `24bfc898160f6ab5ce804e2220d46e5d180e384b`, through the rolling-window, Node 18 compatibility and 13 October production-readiness updates on `codex/private-school-test-2026-10-11`. It includes behavior, infrastructure, tests, deployment preparation and the server steps reported in this conversation. The appendix lists every changed tracked file. It is a change record, not a claim that every feature has been verified in production.

## Branches and scope

- Original repository base: `0d68592d6c364044d3bd56cded1eadc01752a7e6`.
- Architecture/auth/mobile audit: `codex/architecture-auth-mobile-audit-2026-10-11`, audit commit `24bfc89`.
- Audit fixes: `codex/audit-fixes-2026-10-11`, commit `10415f1`.
- Private test preparation: `codex/private-school-test-2026-10-11`, commit `d8d6a83`.
- MySQL migration recovery and hyphenated test database support: `847d604`, on the private test branch.
- Rolling-window update and this document: subsequent commit on the private test branch, inheriting all the above fixes.
- Node 18 compatibility update: subsequent commit on the same private test branch; final dependency stack described below supersedes the initial Vite 7 setup.
- No merge into main, no pull request and no production deployment performed by the agent. The original fork workspace was not edited for these fixes. Historical audit reports/probes remain historical evidence.

## Authentication and account security

- Login and registration normalize email by trimming whitespace and lowercasing it.
- Registration now requires only name, unique email, password and confirmation. On 13 October, the user explicitly requested removing student ID and dorm room from signup, accepting all email domains and allowing reservations immediately without verification. Public signup ignores submitted role/profile fields and always creates a student. Existing ID/room data is retained for optional administrative editing.
- Newly created users are refreshed from the database before authentication so the database's session-version default is present.
- Successful login/signup regenerate the session identifier and store the user's authentication version.
- Logout is POST-only, invalidates the session and regenerates the CSRF token.
- Account/IP login throttling, broader IP login throttling, password-reset account/IP throttling and signup IP throttling were added. Login tests verify HTTP 429 after repeated failures.
- Session-version middleware rejects stale sessions after credential/identity changes. Remember-me authentication establishes a current version only after a valid remembered login; password reset rotates the remember token.
- Sessions use encrypted storage, HTTP-only cookies and SameSite=Lax. Production defaults to secure cookies; temporary loopback/LAN HTTP testing explicitly requires secure cookies disabled.
- Canonical Host validation and framework trusted-host configuration reject unapproved Host headers. APP_URL provides canonical mail/reset links rather than attacker-controlled request hosts.
- Administrator middleware protects every user-management route and machine-administration operation. Students cannot gain access by sending direct requests.
- Private initial administrator provisioning replaces reliance on a shared public demo password.

## Password reset and email

- Password reset uses a dedicated password_reset_links table with SHA-256 token hashes, creation/expiry/consumption timestamps and per-link expiry of 60 minutes.
- Tokens are random 64-character strings; raw tokens are not stored in the reset-link table.
- Reset verifies the account and link under database locks. Password change and token consumption are atomic.
- Successful reset consumes all outstanding links for that account, rotates the remember token, increments session_version and removes database-backed sessions. Reused/expired tokens fail.
- Issuing a new reset request no longer destroys an earlier delivered usable link simply because the new email fails.
- Forgot-password responses remain generic for known/unknown accounts and queue/transport errors, avoiding misleading delivery guarantees and account enumeration in the response text.
- SendResetLink is a queued encrypted job, dispatched after commit, with three attempts and retry delays. Welcome messages are also queued after commit with retries.
- Welcome text acknowledges queued delivery rather than promising inbox arrival.
- Real cycle-finished email notification and listener were added. The listener retains the actual reservation recipient and records a notification marker to suppress normal duplicate processing.
- Scheduler prunes old expired reset links daily.
- Local SMTP integration captures rendered welcome/reset/completion mail and tests a refused connection. No real external recipient was emailed by the agent.
- Private testing defaults to MAIL_MAILER=log. This writes mail content to the private server's logs; it does not send to inboxes. Protect those logs, including reset URLs, as private application data.
- Transport acceptance followed by an ambiguous disconnect can still cause retry duplicates; exactly-once external inbox delivery is not guaranteed.

## Reservation creation and accounting

- Reservation is the canonical model; Booking remains a compatibility alias.
- Single and multi-slot creation share one service transaction. User then machine locks establish consistent ordering; overlap, quota, machine operability and active limits are rechecked inside the transaction.
- Batches are sorted and require distinct, non-overlapping starts. Any invalid/conflicting slot or failed ledger write rolls back all bookings/charges in that batch.
- Requests accept at most 24 starts, with an enforced default maximum of three active/upcoming reservations per account.
- Browser slots are one hour; validated service/API requests may use 20–120 minutes. Accounting uses exact integer minutes rather than inconsistent credit/hour approximations.
- The last remaining weekly allocation can be used correctly, including 20 + 40 minutes of a final hour.
- Each booking has a quota_minutes snapshot. Quota is calculated from noncancelled, nonreleased, nonwaived reservations for the relevant Monday–Sunday week.
- Students default to eight hours/week; administrators to a finite 100 hours/week. Nullable individual overrides are supported.
- Admin reset exempts existing reservations in the current week using quota_waived. Future new reservations still count; cancelling an exempt booking does not create extra quota.
- Ledger charges/refunds have unique event keys, preventing ordinary duplicate accounting events. Failed accounting aborts the booking transaction.
- SQLite busy timeout/transaction configuration and bounded transaction retries were added. Independent-process tests verify same-machine overlap and same-user quota races on SQLite.

## Rolling seven-day booking window — latest requested change

- Default changed from today-through-Sunday to today through the calendar date seven days ahead, inclusive, in Africa/Casablanca time.
- Example: Sunday 11 October permits dates through Sunday 18 October. This means today plus seven future dates, not an exact 168-hour cutoff from the current minute.
- Past starts remain forbidden. A reservation may end exactly at midnight after the final eligible date, but cannot extend beyond it. The final 23:00–00:00 hourly slot is available when otherwise free.
- Shared BookingWindow supplies the upper bound to availability, service validation, request validation, booking form date bounds and calendar click-to-book dates, removing separate conflicting boundary calculations.
- Calendar click-to-book links now reject an already-started current-hour slot rather than treating the entire current hour as still bookable.
- Availability remains server-authoritative; final writes recheck the window and conflicts.
- Quotas still reset each Monday, not on a rolling seven-day basis. A next-week reservation uses next week's allocation; the slots response shows remaining minutes for the selected date's week.
- Defaults: LAUNDRY_RESTRICT_CURRENT_WEEK=false and LAUNDRY_MAX_ADVANCE_DAYS=7. The legacy current-week restriction remains available through true.
- Existing .env files are not overwritten by git pull. The server must explicitly set the restriction to false and clear its configuration cache to activate the new behavior.
- Added Sunday-to-next-Sunday tests, final-midnight acceptance, out-of-window rejection and service rejection of a reservation extending past the boundary. Legacy current-week behavior remains covered explicitly; target-week quota tests remain in place.

## Cycle lifecycle, cancellation and machine state

- Added started_at, completed_at, cancelled_at, released_at, cancellation_reason and explicit machines.active_reservation_id.
- Starting requires the correct reserved window (15-minute grace), an operable machine and no already-active cycle. Early or maintenance starts fail.
- Cancelling a future reservation no longer resets another reservation's running machine cycle.
- Completion operates on the explicit active reservation, only when its end is reached; it records completion and clears the matching machine cycle.
- No-show release handles unstarted reservations even when their end time has already passed. Repeated release/cancellation produces at most one refund.
- Future unstarted cancellations retain history and reason instead of deleting evidence. Ownership checks prevent students cancelling/starting others' reservations.
- Batch cancellation is atomic. Zero-change/repeated cancellation responses are truthful. Redirect destinations are restricted to supported internal pages.
- Scheduled cycle checks and no-show release run every minute. Deployment needs an actual scheduler and queue worker.
- Machine status administration accepts operational status changes; administrators cannot fake an active cycle by selecting in-use or overwrite a genuinely active cycle.
- Machine metrics aggregate statuses; slots query actual machines and active reservations.
- No physical-machine actuation API was supplied or implemented. These are application/database lifecycle changes.

## Database schema, legacy upgrades and repair tools

- Ledger booking_id foreign key now explicitly references reservations on fresh installs; upgrade repairs the legacy wrong bookings reference.
- Added user session versions and optional individual weekly limits; reservation duration/exemption/lifecycle fields; active reservation relationship; ledger event keys; reset-link table; cache and database queue support tables.
- Legacy duration is backfilled from stored start/end values. Historical ended reservations retain the old inferred-completed classification, avoiding invented retroactive no-show refunds. That classification is not physical attendance evidence.
- Identifiable legacy in-progress machine cycles retain their active reservation association.
- Existing email addresses are normalized; case-insensitive duplicates cause upgrade refusal for manual resolution instead of automatic merging.
- Ledger amounts are converted from credits to minutes. This data-changing upgrade deliberately refuses automatic rollback and needs backup/rehearsal before production use.
- laundry:reconcile-ledger is dry-run by default. --apply repairs only unambiguous missing charges/refunds with idempotent event keys, while ambiguous duplicates/mismatches/orphans require review.
- MySQL test installation exposed an overlong autogenerated availability index. It now has the explicit short name reservations_availability_index.
- Recovery skips user/reservation columns already committed before that specific failed index statement. The observed failure can be resumed without deleting the database. This is not a blanket promise that every possible partially executed data migration can be rerun safely.
- Integration coverage now includes a simulation of those partially committed columns and verifies the explicit index fits MySQL's 64-character identifier limit.

## Administration

- Dedicated user create/edit controller and forms replace mismatched/incomplete administration flows.
- Required and unique identity fields, password changes, roles and individual quotas are validated; edit permits leaving the password unchanged.
- Identity/password/role changes revoke sessions and outstanding reset links.
- Last-administrator protection locks administrator rows in a consistent order before changing the account.
- User lists are paginated (25 per page) with aggregated usage rather than repeated per-row quota reads.
- Added real machine administration page and protected status endpoints.
- Administrator labels show finite effective allowances rather than implying unlimited access.

## Interface, mobile and accessibility

- Preserved the branded teal login design; removed credential prefills and kept passwords masked. Password visibility control has a usable touch target and does not expose a fallback password value.
- Registration/forgot/reset use a shared guest layout, reusable labelled fields, autocomplete and visible field/summary validation errors. Removed invented guest identity content.
- Removed oversized decorative auth pseudo-elements causing viewport overflow and missed touches. Forms scroll vertically in phone landscape; inputs use 16px text to avoid common mobile zoom issues.
- Responsive application navigation shows administration only to administrators, uses a POST logout form, includes skip navigation and a no-JavaScript navigation fallback.
- Mobile menu is a named focus-trapped dialog with Escape close and focus return, including an explicit focus step for WebKit behavior.
- Booking page loads database machines and authoritative availability, uses keyboard-operable native pressed-state slot buttons, loading/error/retry feedback, quota/active-limit messaging, disabled invalid submission, sticky phone confirmation and safe-area spacing. A no-JavaScript manual-time fallback is present.
- Availability polls every 30 seconds. Selections are reconciled against refreshed availability.
- Calendar reservation blocks are keyboard accessible; details dialog is named, trapped, scrollable, closable by Escape and returns focus. Close target enlarged.
- Campus calendar clock derives from server time plus elapsed time, formatted for campus timezone instead of assuming the browser's timezone.
- Calendar dynamic names are safely JavaScript encoded. Window information and quota labels reflect configuration.
- Dashboard history is paginated (20 per page), displays durable status and valid start/cancel actions with dynamic quota values. The old active-countdown presentation was replaced as part of the dashboard rewrite; legacy countdown JavaScript remains in the repository.
- Calendar revision endpoint reports aggregate reservation/machine state with no-store caching. UI polls and offers a refresh notice rather than forcing a reload. No live broadcast subscription is claimed.
- Pagination view is included locally so its Tailwind styles remain available in packaged/container builds.
- Private-test login hides signup prompts and adds a private-test/demonstration notice. Server-side registration denial remains authoritative.

## Runtime, dependencies and operational tooling

- Restored Laravel public front controller, public rewrite configuration, base Controller and tracked placeholders for writable cache/storage directories.
- Upgraded locked framework to Laravel 12.69.3 and PHPUnit 11.5.57; restored User factory. Composer lock is committed for reproducible installs.
- Initial audit fixes upgraded to Vite 7 / Tailwind 4. The later Node 18 request replaces that build stack with pinned Vite 6.4.4, laravel-vite-plugin 1.3.0 and Tailwind 4.1.18 through @tailwindcss/postcss 4.1.18. Alpine/focus support and Tailwind 4 markup remain. Removed unused Axios. Package lock records exact dependencies.
- The final application build supports the server's existing Node 18.8.0 and npm 8.18.0; a Node upgrade and laptop asset transfer are no longer required. Initial laptop build/ZIP transfer remains recorded below as completed history.
- Removed @tailwindcss/vite and configured Tailwind's compatible PostCSS plugin instead. Application package declares Node >=18.8.0. Compatible direct dependencies are pinned to prevent a later npm update silently reintroducing the newer major requirements.
- Playwright is separated into tests/browser/package.json with its own lockfile, so production npm ci does not install browser-test tooling that requires newer Node. Browser tests remain a development/CI operation on Node 22+. Root build installation needs no engine-warning suppression, --force or --legacy-peer-deps.
- Preview launcher uses fileURLToPath(new URL('.', import.meta.url)) instead of import.meta.dirname, making its working-directory handling valid on Node 18.
- CI example adds a Windows Node 18.8.0 engine-strict install/build job, while browser jobs retain modern Node and explicitly install their separate test package. The CI template is still unactivated. Docker can keep its Node 22 asset builder: the same compatible dependencies also run there.
- Unsafe standalone Node imitation of the website/authentication was removed; preview launcher uses real Laravel and binds loopback.
- .env.example has no fixed application key, debug disabled, campus timezone, explicit session/queue/cache/mail defaults and private-test options. A new private APP_KEY is generated per deployment.
- Standard machine seeding uses firstOrCreate so existing operational state is preserved. Demo users require explicit local/testing opt-in and a privately supplied password; no shared fixed demo credentials.
- Added multistage PHP 8.3 Apache/Node asset Docker build, public document root, required extensions and writable directory ownership.
- Compose defines separate app, worker, scheduler, MySQL 8.4 and Mailpit services, restricted bindings/private database network and required secret configuration.
- Added CI example for backend/browser checks on Windows/macOS/Linux, MySQL and container build. It remains ci/audit-fixes.yml.example, not an active GitHub workflow, because the authenticated token lacks workflow-edit scope.
- README rewritten with actual installation, rules, upgrade/reconciliation, runtime and verification requirements. FIXES.md maps all 22 original findings to fixes and evidence. PRIVATE-TEST.md provides Windows/Laragon isolation instructions.

## Private school test site

- Separate private-test branch inherits the audit fixes; no main checkout/deployment change is needed.
- Server-side optional PrivateTestAccess checks configured client IP/CIDR networks, blocks signup GET/POST, rejects nonallowlisted login/reset submissions and rejects already-authenticated nonallowlisted sessions on web routes.
- Empty account allowlist fails closed. Default network list is loopback only. Restriction is disabled by default for normal deployments.
- Allowed test emails configured by the user: anwar.mounir@centrale-casablanca.ma and yahya.elomari@centrale-casablanca.ma. No passwords or application keys are recorded here.
- laundry:create-tester asks interactively for name/password/confirmation, requires at least 12 password characters, permits an optional admin role, refuses existing accounts and sends no email.
- Provisioning requires private-test mode, allowlisted email and an isolated database name ending in _test or -test. Hyphen support was added after the user's database choice.
- Proposed permanent school test listener uses a separate Apache port, TLS and narrowly scoped firewall rules. No public DNS change or router forwarding is required. A separate database/user prevents test bookings affecting real reservations.
- Network middleware gates application web routes. Static assets and health checks require listener/firewall isolation; the account gate does not make every public file private.

## Server actions reported in chat, separate from committed code

These were carried out or reported by the user on the Windows Server; the agent does not have direct server inspection evidence.

- Server screenshot showed Laragon 6, Apache 2.4.62, PHP 8.3.13, MySQL 8.0.30, IP 172.28.2.10, and live checkout C:\laragon\www\ecc_laundryV2 on main.
- User cloned the private branch into C:\laragon\private\laundry-test, installed production Composer dependencies successfully and copied .env.example to .env.
- Created separate database ecc-laundry-private-test and laundry_test account with database-specific privileges.
- Database login initially failed through Laravel. Direct MySQL login succeeded; corrected .env quoting then allowed migrations to begin. No password is retained here.
- Initial migration failed on MySQL's index-name limit; recovery fix was pushed with pull/migrate/seed instructions. User subsequently reported creation of both test accounts.
- User generated the private application key and created Anwar's admin test account and Yahya's student test account.
- Frontend production build was generated on the laptop, zipped as laundry-test-frontend.zip, copied by the user and extracted into the test copy's public/build.
- Loopback PHP development-server test was instructed at 127.0.0.1:8081. User reported that the site seemed to work.
- Temporary phone test instructions bound the development server to 172.28.2.10:8081, added phone 10.100.4.49/32 to the test network allowlist and proposed a TCP firewall rule restricted to that phone. Successful execution/connectivity was not confirmed. User deferred the phone connection issue.
- This temporary HTTP development listener is for test-only passwords and supervised testing. Permanent Apache/TLS deployment, scheduled tasks and school-network connectivity are not confirmed complete.

## Verification and remaining limits

- Original fixes verification: 23 backend tests / 170 assertions; six legacy upgrade checks; independent-process SQLite races; actual database queue/local SMTP tests; 40 browser workflow checks across Chromium, Firefox, Edge and WebKit; production frontend build; dependency audits at that time.
- Private-test gate/provisioning and migration recovery verification: 28 tests / 199 assertions; seven normal legacy upgrade checks and seven partial-index-recovery checks.
- Rolling-window verification: 29 tests / 210 assertions, all passing; frontend production build passing.
- Node compatibility verification uses the official Windows x64 Node 18.8.0 executable, whose SHA-256 matched the published Node distribution checksum, and npm 8.18.0. A clean npm ci --engine-strict, npm run build and npm audit --audit-level=moderate pass; npm reports zero known vulnerabilities at the time checked. A loopback Vite development-server smoke test also compiles and serves the application CSS with Node 18.8.0. This verifies the laptop Windows runtime; server-specific network/antivirus/OS differences still require running the supplied commands there.
- After the compatible build, all 40 browser workflow checks were rerun and passed across Chromium, Firefox, installed Edge and WebKit, using the newly built assets and the rolling-window code. tests/browser/evidence/results.json records that run. Backend code was not changed by the Node compatibility update; the latest backend result remains 29 tests / 210 assertions.
- Browser checks use real Laravel with CSRF enabled, a fixed test-only campus clock, Honolulu browser timezone, 320/390px phone viewports and 844×390 landscape. They cover signup/errors/login/logout, mobile menu focus, keyboard booking/calendar/cancellation, admin phone forms, overflow and missing-CSRF rejection.
- Chromium/Firefox/Edge/WebKit automation is not proof of every native Safari/Opera, Android/iOS, macOS/Linux or old-browser combination. Real phone workflow testing remains outstanding.
- Fresh MySQL installation reached migrations on the user's server; a real MySQL identifier bug was observed and fixed. Full MySQL concurrency/load/production upgrade certification is still outstanding; local regression tests used SQLite.
- No external SMTP delivery, school/Gmail inbox placement, SPF/DKIM/DMARC, physical laundry hardware, real school-scale load, production backups/restore, deployed scheduler/worker health, Docker execution or remote CI run is certified by these changes.
- The library/sports/music/study-room unified reservation architecture discussed earlier was not implemented in this laundry fixes branch.

## Activate the rolling window on the existing test server

From C:\laragon\private\laundry-test, stop the temporary development server if running, then run `git pull --ff-only`. In that test copy's .env set:

```dotenv
LAUNDRY_RESTRICT_CURRENT_WEEK=false
LAUNDRY_MAX_ADVANCE_DAYS=7
```

For the rolling-window change alone, run `php artisan config:clear` and restart the test server using the same chosen bind address/port. No new migration is required.

The later Node 18 compatibility update **does** replace the frontend dependencies/build. After pulling that commit, run the following on the server with its existing Node 18.8.0 and npm 8.18.0:

```bat
npm ci
npm run build
php artisan config:clear
```

Then restart the test server. npm ci replaces node_modules according to the committed lockfile; it does not touch .env, the database, PHP dependencies or main. Keep the separate tests/browser package for a modern development/CI machine. Laravel/PHP continues serving the website; this does not turn it into a Node web server.

## Additional deep review — 13 October 2026

Full findings, evidence and remaining release checks are in PRODUCTION-READINESS.md. This pass starts from Node-18-compatible commit 068502e.

- Removed ID/room signup requirements and made their admin/initial-administrator fields optional. Added explicit duplicate-account sign-in/reset guidance.
- Login now displays validation errors. Signup/admin unique-constraint races become form validation rather than generic server errors.
- Added scalar-safe email normalization across auth, throttles and private access; safe rendering of malformed old input; reset-email query and booking preselection validation.
- Added password checks against bcrypt truncation beyond 72 bytes and null-byte hashing failures for new credentials. Legacy login behavior remains.
- Added branded French HTML error pages (including 419, 429 and 500) without echoing exception details. They work without Vite assets or a database. Common French validation messages and shared feedback stay on the form; JSON errors retain JSON responses.
- Session-version expiry now returns 401 to JSON polling instead of redirecting to login HTML.
- Resolved the exact grace-deadline overlap between cycle start and no-show release.
- Post-commit event/queue failures no longer falsely report that a committed cycle start/completion failed. Periodic checks recover actual completed-but-unnotified reservations, excluding inferred legacy history.
- Corrected twenty-minute calendar labels and prevented running blocks from swallowing future cancellation controls. Replaced ad-hoc modal escaping with JavaScript-safe encoding; removed invented fallback email addresses.
- Reset disabled submission flags on browser pageshow; restored booking pages refresh slot availability.
- Added configurable SMTP connection timeout (MAIL_TIMEOUT, default 10 seconds).
- Added read-only laundry:readiness configuration/schema diagnostic. Passing it does not certify the workers, scheduler, external email or production migration.
- Latest verification: 41 backend tests / 295 assertions pass; 48 browser workflow checks pass across Chromium, Firefox, Edge and WebKit with no page errors; Node 18.8 production build passes; local SMTP, migration recovery, independent-process booking races and committed-cycle queue-outage integrations pass.
- Expanded browser signup tests initially exhausted the shared test IP limit. Each engine now receives a fresh guarded synthetic database/rate-limit state; application throttles remain enabled.
- Server follow-up confirmed the live profile columns are NOT NULL. Added 2026_10_13_000001_make_profile_fields_optional.php to permit NULL without removing existing metadata, preserving string type/length and collation; already-nullable columns are skipped. New regression covers existing-value preservation, repeat execution and multiple NULL profiles. No external email was sent or main branch changed.
- The user prepared a separate laundry-release checkout, copied the existing production .env and built its frontend successfully on the server. Release configuration checks pass. Reset/queue tables and pending migrations remain outstanding; Apache still points to the old checkout at this stage.

The next server check found an existing transactions table whose creation migration is still pending. Its migration now validates and adopts an existing ledger instead of recreating it; dropping an adopted ledger is refused. Foreign-key repair uses actual MySQL constraint names and SQLite column-based removal. Verification reached 43 backend tests / 300 assertions, with seven legacy partial-upgrade checks passing.

The live ledger check confirmed 88 orphan references and no reservation foreign key. Added 2026_10_13_000002_preserve_legacy_ledger_links.php to archive original dangling IDs in legacy_booking_id, retain every ledger row, clear invalid active references and restore the reservation constraint. The full upgrade still applies its earlier ledger-unit conversion. Verification: 44 backend tests / 303 assertions pass.

During the live maintenance upgrade, the optional-profile migration found student_id absent, rather than merely nonnullable. The migration now adds absent optional fields or relaxes existing ones, preserving accounts. A regression covers absent columns and repeated execution. The preceding four migrations already completed and are recorded in migration history; normal migrate resumes pending work.

The user subsequently completed the production MySQL migration, passed readiness, switched Apache and reported the public site working. A new user-requested policy drops email delivery older than 15 minutes (LAUNDRY_MAX_EMAIL_DELAY_MINUTES). Guards run when welcome/reset/completion messages execute; cycle age uses scheduled end, and recovery excludes expired alerts. Existing reset links and reservations remain valid. Added stale/recent-mail tests; backend total is now 48 tests / 316 assertions. No schema migration or frontend rebuild is needed for this policy.

14 October: production signup logs identified a users.role schema mismatch (MySQL error 1265 for student). Added a role-column compatibility migration preserving existing roles, plus a readiness check and restrictive-enum signup regression. Signup continues to force student regardless of submitted role. Backend verification reached 49 tests / 324 assertions.

## Complete tracked-file inventory (updated)

The following appendix is generated from Git against the audit commit. It includes dependency locks, tests and configuration as well as product code. A means added; M means modified. Consult the Git diff for exact line-level changes; the sections above explain their intended effect.

| Status | File |
|---|---|
| A | `.docker/8.3/Dockerfile` |
| A | `.dockerignore` |
| M | `.env.example` |
| A | `.gitattributes` |
| M | `.gitignore` |
| A | `app/Console/Commands/CreateAdministrator.php` |
| A | `app/Console/Commands/CreatePrivateTester.php` |
| A | `app/Console/Commands/ReadinessCheck.php` |
| A | `app/Console/Commands/ReconcileLedger.php` |
| M | `app/Enums/MachineType.php` |
| A | `app/Http/Controllers/Admin/UserController.php` |
| M | `app/Http/Controllers/Auth/AuthController.php` |
| M | `app/Http/Controllers/Auth/PasswordResetController.php` |
| M | `app/Http/Controllers/BookingController.php` |
| A | `app/Http/Controllers/Controller.php` |
| M | `app/Http/Controllers/DashboardController.php` |
| M | `app/Http/Controllers/MachineController.php` |
| A | `app/Http/Middleware/PrivateTestAccess.php` |
| A | `app/Http/Middleware/RequireAdmin.php` |
| A | `app/Http/Middleware/RequireCanonicalHost.php` |
| A | `app/Http/Middleware/ValidateSessionVersion.php` |
| M | `app/Http/Requests/StoreBookingRequest.php` |
| M | `app/Http/Requests/UpdateMachineStatusRequest.php` |
| M | `app/Jobs/CheckActiveCycles.php` |
| A | `app/Jobs/SendResetLink.php` |
| M | `app/Listeners/SendCycleAlertNotification.php` |
| M | `app/Models/Machine.php` |
| M | `app/Models/Reservation.php` |
| M | `app/Models/Transaction.php` |
| M | `app/Models/User.php` |
| A | `app/Notifications/CycleFinishedNotification.php` |
| M | `app/Notifications/ResetPasswordNotification.php` |
| M | `app/Notifications/WelcomeNotification.php` |
| M | `app/Providers/AppServiceProvider.php` |
| A | `app/Rules/SafePassword.php` |
| M | `app/Services/BookingService.php` |
| A | `app/Services/BookingWindow.php` |
| M | `app/Services/MachineSchedulerService.php` |
| A | `app/Support/AccountInput.php` |
| M | `bootstrap/app.php` |
| A | `bootstrap/cache/.gitignore` |
| A | `ci/audit-fixes.yml.example` |
| M | `compose.yaml` |
| M | `composer.json` |
| A | `composer.lock` |
| M | `config/app.php` |
| M | `config/database.php` |
| M | `config/laundry.php` |
| A | `config/mail.php` |
| A | `config/private_test.php` |
| A | `config/session.php` |
| A | `database/factories/UserFactory.php` |
| M | `database/migrations/2026_01_01_000003_create_transactions_table.php` |
| A | `database/migrations/2026_10_11_000001_harden_reservations_and_accounts.php` |
| A | `database/migrations/2026_10_11_000002_create_cache_and_queue_tables.php` |
| M | `database/seeders/DatabaseSeeder.php` |
| M | `database/seeders/MachineSeeder.php` |
| A | `FIXES.md` |
| A | `lang/fr/validation.php` |
| M | `package-lock.json` |
| M | `package.json` |
| M | `phpunit.xml` |
| A | `POST-AUDIT-CHANGES.md` |
| M | `postcss.config.js` |
| M | `preview-server.js` |
| A | `PRIVATE-TEST.md` |
| A | `PRODUCTION-READINESS.md` |
| A | `public/.htaccess` |
| A | `public/index.php` |
| M | `README.md` |
| M | `resources/css/app.css` |
| M | `resources/js/app.js` |
| A | `resources/views/admin/machines.blade.php` |
| A | `resources/views/admin/user-form.blade.php` |
| M | `resources/views/admin/users.blade.php` |
| M | `resources/views/auth/forgot-password.blade.php` |
| M | `resources/views/auth/login.blade.php` |
| M | `resources/views/auth/register.blade.php` |
| M | `resources/views/auth/reset-password.blade.php` |
| M | `resources/views/bookings/create.blade.php` |
| M | `resources/views/calendar.blade.php` |
| A | `resources/views/components/form-feedback.blade.php` |
| A | `resources/views/components/form-field.blade.php` |
| A | `resources/views/components/live-revision.blade.php` |
| M | `resources/views/dashboard.blade.php` |
| A | `resources/views/errors/401.blade.php` |
| A | `resources/views/errors/402.blade.php` |
| A | `resources/views/errors/403.blade.php` |
| A | `resources/views/errors/404.blade.php` |
| A | `resources/views/errors/419.blade.php` |
| A | `resources/views/errors/429.blade.php` |
| A | `resources/views/errors/4xx.blade.php` |
| A | `resources/views/errors/500.blade.php` |
| A | `resources/views/errors/503.blade.php` |
| A | `resources/views/errors/5xx.blade.php` |
| A | `resources/views/errors/layout.blade.php` |
| M | `resources/views/layouts/app.blade.php` |
| A | `resources/views/layouts/auth.blade.php` |
| M | `resources/views/layouts/navigation.blade.php` |
| A | `resources/views/vendor/pagination/tailwind.blade.php` |
| M | `routes/console.php` |
| M | `routes/web.php` |
| A | `storage/app/private/.gitignore` |
| A | `storage/app/public/.gitignore` |
| A | `storage/framework/cache/data/.gitignore` |
| A | `storage/framework/sessions/.gitignore` |
| A | `storage/framework/views/.gitignore` |
| A | `storage/logs/.gitignore` |
| A | `tests/browser/evidence/.gitignore` |
| A | `tests/browser/evidence/results.json` |
| A | `tests/browser/package-lock.json` |
| A | `tests/browser/package.json` |
| A | `tests/browser/regression.mjs` |
| A | `tests/browser/router.php` |
| A | `tests/browser/run.mjs` |
| A | `tests/browser/seed.php` |
| A | `tests/Feature/AuthSecurityTest.php` |
| M | `tests/Feature/BookingTest.php` |
| M | `tests/Feature/MachineAvailabilityTest.php` |
| A | `tests/Feature/PrivateTestAccessTest.php` |
| A | `tests/Feature/ReadinessTest.php` |
| A | `tests/integration/concurrency.php` |
| A | `tests/integration/contender.php` |
| A | `tests/integration/cycle-outage.php` |
| A | `tests/integration/mail.php` |
| A | `tests/integration/smtp.mjs` |
| A | `tests/integration/upgrade.php` |
| M | `tests/TestCase.php` |
| M | `tests/Unit/BookingServiceTest.php` |
| M | `vite.config.js` |
| A | `database/migrations/2026_10_13_000001_make_profile_fields_optional.php` |
| A | `tests/Feature/LegacyProfileMigrationTest.php` |
| A | `database/migrations/2026_10_13_000002_preserve_legacy_ledger_links.php` |
| A | `app/Support/MailDeliveryWindow.php` |
| A | `tests/Feature/MailDeliveryWindowTest.php` |
| A | `database/migrations/2026_10_14_000001_allow_application_account_roles.php` |
| A | `tests/Feature/LegacyRoleMigrationTest.php` |
