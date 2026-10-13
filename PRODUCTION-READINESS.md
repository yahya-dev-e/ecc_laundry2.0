# Deeper production-readiness review — 13 October 2026

Repository: yahya-dev-e/ecc_laundry2.0. Branch: codex/private-school-test-2026-10-11. Review starts at 068502e and includes the fixes accompanying this report. Main and the production server were not modified.

## Decision

The branch is ready for another review and isolated server rehearsal, not an unconditional production release. Automated checks can establish specific behavior, not the absence of every possible error. The original screenshot/error notification was not supplied, so this review does not claim to identify its exact cause.

Confirmed product decisions from Anwar:

- Public signup asks for name, email, password and confirmation. Student ID and dorm room are removed.
- Any email domain is accepted.
- New accounts can reserve immediately, without email confirmation.
- Existing optional profile data stays in the database; administrative editing permits empty values.
- The rolling today-through-seven-days-ahead window, Monday-based quotas, Node 18.8 support and private-test option remain.

## Confirmed findings and changes

| Finding | Result |
|---|---|
| Students without an ID or dorm room could not sign up | Removed both public fields and their required validation; submitted hidden/profile fields cannot sneak into account creation. Database already allows NULL. |
| Administrative edits still required profile fields omitted by new signups | Made these administrative fields optional; retained existing data. Initial administrator provisioning permits empty profile fields too. |
| Wrong-password/login validation errors were not rendered | Login now uses the common visible feedback component; wrong credentials remain on the branded form. |
| Duplicate-account responses were generic; concurrent signup could bypass prevalidation and hit a unique constraint | Added actionable sign-in/reset guidance and converted account-write unique conflicts into validation responses for signup and administration. |
| Array-valued emails triggered unsafe casts in normalizers/rate limiters/private access checks | Shared scalar-safe email handling, followed by ordinary validation. |
| Redisplaying malformed old input could crash Blade input attributes | Form fields and login display only scalar old values; password values are never repopulated. |
| Reset-form email query input could be malformed | Validate the query before rendering. Booking preselection parameters are validated as well. |
| Long bcrypt passwords could silently truncate, and null bytes could fail hashing | New password validation rejects values over 72 bytes and null bytes, including account creation/reset/admin password changes and interactive provisioning. Existing login behavior is retained for legacy passwords. |
| Default framework HTTP errors looked unrelated and could display arbitrary exception text | Added standalone branded French error pages for common 4xx/5xx responses, including expired-session 419. They do not require a working database or built Vite assets and do not echo exception details. JSON responses remain JSON. Production still requires APP_DEBUG=false. |
| Stale authenticated sessions returned login HTML to JSON availability polling | Expired-version JSON requests now receive 401 with a readable message. Normal navigation still redirects to login. |
| At the exact 15-minute deadline both start and no-show release were allowed | Starting ends exclusively at the deadline; release owns that boundary, eliminating scheduler-order-dependent outcomes at that instant. |
| Event enqueue failure after commit could report a failed cycle start/completion even though state changed | Catch/report post-commit event failures without undoing or misreporting the committed lifecycle transition. |
| A failed completion-event enqueue could permanently lose the completion email | Periodic cycle checks redispatch actually started/completed, unnotified reservations. Existing listener locking and marker suppress normal duplicate delivery. Inferred legacy completions are excluded. |
| Short calendar reservations displayed a minimum 30-minute duration | Preserve actual duration (e.g. 20 minutes); visual minimum card height remains independent. |
| Merging contiguous blocks could hide cancellation of a future reservation beside a running reservation | Merge only rows sharing cancellation eligibility; future bookings retain their own selectable block. |
| Calendar modal data used ad-hoc JavaScript escaping | Use Blade's JavaScript encoder for machine/user labels and color. Removed invented fallback email addresses. |
| Browser back/forward cache could preserve disabled submit state | Reset form submission flags on pageshow; booking restoration also refreshes availability. Physical-device back/forward behavior remains a device check. |
| SMTP had no explicit connection timeout | Added mail configuration with MAIL_TIMEOUT=10 seconds by default, configurable through .env. This does not prove external delivery. |
| Deployment configuration was easy to mistake for production-ready | Added read-only laundry:readiness to flag debug/HTTPS/session/test-mode/mail/queue/assets/database/schema/migration issues without printing secrets or student rows. Worker/scheduler health is explicitly still manual. |

## Verification

- Backend: 41 tests, 295 assertions pass, including twelve new readiness tests.
- Dedicated transaction integration: simulated event enqueue failure after real commits preserves started/completed state; the next scheduler check rediscovers the unnotified completion.
- Legacy partial-index migration recovery: seven checks pass on isolated SQLite, including foreign-key repair and idempotent ledger reconciliation.
- Independent PHP-process race tests: exactly one competing booking succeeds for same-machine overlap and same-user quota.
- Local SMTP: three synthetic welcome/reset/completion messages accepted through the actual database worker; refused connection preserves prior usable reset links and generic public responses. No real email was sent.
- Production frontend build passes with the previously verified Node 18.8.0/npm 8.18.0 runtime.
- Expanded browser matrix: all 48 checks pass (twelve per engine) across Chromium, Firefox, Edge and WebKit, with no page errors. Coverage includes duplicate signup, visible wrong-password feedback, signup without ID/room, branded 404/419 pages, phone overflow, admin forms and reservation/cancellation keyboard flows. Results are recorded in tests/browser/evidence/results.json.
- Initial expanded browser run hit the real ten-signups/hour limiter because all engines reused one fixture database/IP. The runner now gives each engine fresh synthetic database and rate-limit state; application throttling was not relaxed.
- laundry:readiness was exercised locally and correctly flags development mode, HTTP/nonsecure cookies and log-only mail. Those are expected for this local test configuration. It made no changes.

## Still required before production

1. **Production-schema rehearsal:** run migrations against a sanitized copy of the actual MySQL schema/data, inspect quota/ledger conversion, then exercise bookings and authentication. Local SQLite results are not MySQL certification. Docker's daemon was unavailable on the laptop; no new MySQL server result is claimed.
2. **Backup and restore:** take and test a restore before the data-changing upgrade. The historical migration converts ledger units and is not generally rerunnable after every possible partial failure; the known long-index failure has a specific recovery. Keep old/new application writers from concurrently using the production database during the upgrade.
3. **Server configuration:** verify PHP extensions, HTTPS/canonical URL, secure cookies, debug disabled, private-test flag disabled for a general launch, production-built assets, document root and protected .env/storage. Node 18 compatibility was tested on the laptop, not by direct inspection of the school server.
4. **Worker and scheduler:** verify they survive restart and process no-shows, completion events and queued mail. Monitor failed jobs and recovery backlog. Completion recovery can enqueue duplicate attempts; the listener prevents ordinary duplicate sends, but SMTP acceptance followed by disconnect can still produce duplicate inbox delivery.
5. **Real email:** configure production SMTP and test actual delivery to the user-approved addresses after notifying the user. Check reset links use the deployed HTTPS origin, including school/Gmail inbox/spam behavior. No public user mail was sent in this review.
6. **Physical phone:** test signup/login/reset, keyboard, portrait/landscape, booking and cancellation on an actual Android/iPhone over the deployed route. Chromium/Firefox/Edge/WebKit viewport tests do not prove every OS/browser version.
7. **Hardware and load:** no physical machine API was provided. Database cycle state does not prove hardware actuation. Real school-scale traffic/load and operational failure recovery need a controlled server exercise.

Any-email signup with immediate reservations is intentional per the user. It does not establish ownership of a supplied email address or prevent a person creating multiple accounts; the current limits are per account/IP rather than a verified student identity.

## Safe next server check

Pull the updated branch only into the isolated test checkout. Run:

```bat
php artisan config:clear
php artisan view:clear
php artisan laundry:readiness
```

This command is read-only and safe to share: it prints check labels, not credentials. A private test environment is expected to fail general-release checks such as PRIVATE_TEST_ENABLED. Do not disable safeguards merely to make the report green. Passing this command does not replace the manual items above.

Server follow-up on 13 October: the release readiness output confirmed the live database has NOT NULL student_id/room_number columns, unlike the repository's fresh-install schema. Added 2026_10_13_000001_make_profile_fields_optional.php to change those fields to nullable while retaining string type/length, collation and existing values. Already-nullable columns are skipped. A regression covers existing values, repeat execution and two new accounts with NULL profiles. This migration is now required for that live schema; automatic rollback to NOT NULL is refused because new accounts may lack those values.

The user-reported release build and configuration checks passed on Windows Server using Node 18.8. Missing reset/queue tables and pending migrations remain unexecuted at this point. Apache still serves the old checkout. Verify the live migration history before executing the upgrade.

Follow-up verification: 42 backend tests / 298 assertions pass. Previous 48 browser checks remain the recorded browser result; the schema-only follow-up was verified by the new migration regression.

Migration-history follow-up: the live database contains transactions even though its creation migration is pending. The migration now validates and adopts an existing ledger without recreating it; missing required columns stop the upgrade. Automatic down() refuses to delete an adopted ledger. Legacy foreign-key repair uses the actual constraint name on MySQL and the required column-based operation on SQLite. Verification: 43 backend tests / 300 assertions pass, plus seven partial-upgrade integration checks. The actual MySQL table definition still needs inspection before migration.

Ledger follow-up: the live read-only query found 88 entries referencing deleted reservations, and no booking_id foreign key. Added 2026_10_13_000002_preserve_legacy_ledger_links.php to retain every ledger row, archive dangling IDs in legacy_booking_id, clear invalid active references and add the missing reservation constraint. No refunds or replacement reservations are invented. The earlier upgrade still converts legacy ledger units to minutes. Verification: 44 backend tests / 303 assertions pass.

During the live maintenance upgrade, the first four pending migrations succeeded, but optional-profile migration stopped because student_id was entirely absent. The earlier readiness failure had been interpreted as NOT NULL, although the diagnostic also fails for a missing column. The migration now adds missing nullable profile columns (and a unique student_id index) or relaxes existing fields, preserving accounts. Targeted legacy migration tests pass, including missing columns and repeat execution. Resume with ordinary migrate --force; completed migrations are recorded and must not be rolled back/replayed manually.
