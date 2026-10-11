# Audit fixes — 11 October 2026

Branch: codex/audit-fixes-2026-10-11, based on the separate original-repository audit branch. No merge or deployment is part of this change. The historical audit report and defect-reproducing probes remain unchanged; run the new regression tests to assess the fixes.

## Finding-to-fix map

| Original finding | Implemented change | Verification |
|---|---|---|
| 1. Student administration | Admin middleware on every user-management/machine-admin action; validated controller and identity changes revoke sessions/reset links | Student read/write denial; real admin create/edit; duplicate email validation |
| 2. Reset Host poisoning | Canonical APP_URL mail links; explicit host allow-list in all environments | Untrusted Host rejected; reset URL assertion |
| 3. Concurrent bookings | User then machine locks inside the transaction; recheck overlap/quota; bounded transaction retries and SQLite busy timeout | Separate-process same-machine and same-user/different-machine races |
| 4. Wrong ledger FK | Explicit reservations FK for fresh installs and legacy repair migration; accounting failure rolls back booking | Fresh migrations, legacy wrong-FK upgrade, ledger-failure rollback |
| 5. Last weekly credit | One duration-based quota check before insertion under lock | Final 60 minutes usable as 20+40 minutes; extra booking rejected |
| 6. Partial multi-slot writes | Distinct inputs and all-or-nothing service transaction | Duplicate/conflicting batches persist no partial booking |
| 7. Unrelated machine/cycle state | Explicit active_reservation_id and lifecycle timestamps; start window/operability checks; release only matching cycle | Early/maintenance starts denied; cancelling future booking preserves another running cycle |
| 8. Incomplete deployment | Real front controller/base controller, factory, tracked writable directories, locked dependencies, cache/queue migrations, Docker build, worker/scheduler services | Real Laravel boot, fresh migration/seed, tests, asset build; container execution remains a release check |
| 9. Missing throttling | Login account+IP/IP limits, reset account/IP limits, signup IP limit | HTTP 429 regression tests |
| 10. Reset sessions/remember token | Atomic token consumption/password change; rotation of remember token and auth-version invalidation; database session deletion | Token reuse/expiry, remembered token rotation and stale-session denial |
| 11. Mail outage semantics | Queued/retried welcome/reset mail, encrypted reset jobs, generic responses; independently expiring links preserve earlier delivered links | Actual database worker and local SMTP capture; refused SMTP connection; older link remains usable |
| 12. Admin forms | Dedicated authenticated add/edit pages, aligned validated fields and visible errors | Phone create/edit workflows; quota edit; duplicate email validation |
| 13. Quota mismatch | Exact integer minutes from reservations; individual limits; effective admin reset via reservation exemptions; finite admin label | Quota charging/refund/override/reset tests, including cancellation after reset |
| 14. Registration UI | Guest auth layout, readable styles, visible adjacent/errors summary, normalization/autocomplete, no invented guest identity | Invalid and successful signup in browser workflows |
| 15. Landscape clipping | Vertical document scrolling, no oversized decorative pseudo-elements, 16px form controls at all widths | 844×390 submit reachability and phone overflow assertions |
| 16. Keyboard/dialog access | Native slot buttons with pressed state; keyboard calendar blocks; named/trapped dialogs, Escape/focus return; labelled forms | Keyboard selection, dialog trapping/closing and navigation focus return |
| 17. UI/server slot mismatch | One campus timezone and server availability cutoff; authoritative slots endpoint; campus calendar clock | Past slot rejected, cross-timezone browser workflow, Sunday midnight boundary |
| 18. No-shows/completion/alerts | Durable started/completed/released state; release even after reservation end; preserved completion recipient; real queued completion email | No-show/refund idempotency, active completion and recipient tests; SMTP completion message |
| 19. Cancellation/redirects | Durable reason/history, idempotent refund, atomic batches, truthful zero-change status and allow-listed internal destinations | External redirect rejected; repeat cancellation refunds once |
| 20. Demo/environment defaults | No credential prefills/fixed example key; private opt-in local demo seed; debug off, campus timezone and production-secure cookies | Real login starts blank/masked; seed/test configuration; documented deployment checks |
| 21. Unsafe Node preview | Removed independent imitation authentication/storage; loopback launcher uses real Laravel | Real backend used for every browser workflow |
| 22. Scale/dead paths/drift | Paginated history/users, aggregated quota, restored machine endpoints/admin page, enforced active cap, polling/refresh notice, honest feature documentation | Page rendering, slots/revision assertions and phone workflows; larger-scale load testing remains external |

## Local verification

The final verification results are recorded below before the branch is pushed. Historical audit/evidence files describe the old defects, not the fixed behavior.

- PHPUnit: 23 tests, 170 assertions, passing.
- Legacy SQLite upgrade: six integration checks passing, including wrong foreign-key repair and repeatable ledger reconciliation.
- Separate-process SQLite races: overlap and quota races pass; exactly one contender books.
- Real database queue/local SMTP: welcome, password reset and cycle-completion templates accepted for student@example.test only. A refused SMTP connection preserves the generic response and the prior usable reset link.
- Frontend production build passes; npm audit and Composer locked audit report no known vulnerabilities at the time checked.
- All 40 browser workflow checks pass across Chromium, Firefox, installed Edge and WebKit (10 checks per engine), including explicit rejection of missing CSRF tokens.
- Browser results: tests/browser/evidence/results.json. Tests use real Laravel forms with CSRF enabled, a test-only fixed campus clock, a Honolulu browser timezone, 320/390px phone layouts and 844×390 landscape.

## Release checks that require the deployment or real devices

This branch fixes application defects; it does not establish compatibility with every browser/OS version or deliver production infrastructure.

- The local host is Windows. Playwright Chromium, Firefox, installed Edge and WebKit are engine/viewport checks. WebKit is not native Safari on an iPhone/Mac. Opera, native Android/iOS versions, macOS/Linux execution, mobile keyboards, assistive technology and older browser versions still need their named device matrix. The later private-test branch pins Vite 6.4.4 and Tailwind 4.1.18 for Node 18.8 builds; modern Tailwind browser requirements still apply. Server Node compatibility does not establish old-browser compatibility.
- The CI template covers Linux/macOS/Windows, MySQL and Docker. It is not activated because the authenticated GitHub token lacks workflow-edit scope; remote execution is still required. MySQL production migrations/concurrent workload and real school-scale load are not proven by SQLite tests.
- No real external email was sent. Production SMTP, TLS/canonical URLs, school/Gmail delivery, SPF/DKIM/DMARC and spam placement remain deployment checks.
- No physical machine-control API/device was supplied. Database cycle state and notification delivery are implemented; actual hardware actuation/telemetry require their own integration and failure handling.
- Back up/rehearse the migration and inspect the dry-run ledger report. Historical deleted records, ambiguous balances or an already-inconsistent production schema need review. The migration preserves the old inferred-completion classification for ended legacy reservations; it does not invent evidence of physical usage.
- Monitor queues, scheduler health, failed mail and backups. A mail transport that accepts a message and then disconnects can cause a retry duplicate; no exactly-once inbox guarantee is claimed.

See README.md for installation, existing-data upgrade and verification commands.
