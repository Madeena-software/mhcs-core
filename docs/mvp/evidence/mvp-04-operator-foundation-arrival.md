# MVP-04 Operator Foundation and Arrival Evidence

Date: 2026-08-05

This evidence records the bounded MVP-04 Operator foundation and arrival slice.
It is not a completion or production-readiness claim for MVP-04.

## Scope delivered

- Shared User authentication with persisted `operator` role and bounded Operator permissions.
- One-to-one Operator profile, Operator-owned physical-site master, explicit Member site-reference synchronization, and Operator-to-site assignment.
- Server-side active-site session context with execution-time assignment/site/profile checks and audited switching.
- Versioned, idempotent `shift_eligible` intake with stable `operator_site_id`, sanitized payload boundaries, and manual Operator-to-schedule assignment.
- Member-owned attendance query through an explicit application contract. Results are site-scoped and limited to confirmed, personal, charged bookings and safe operational fields.
- Operator arrival record with explicit-offset occurrence time normalized to UTC, idempotent replay/conflict handling, atomic Member `confirmed` to `arrived` transition, audit evidence, and one `operator.member-arrived` outbox event.
- Bounded Blade Operator portal and shared Filament administration for sites, profiles, site assignments, eligible shifts, shift assignments, and read-only arrivals.
- Local/testing-only `MvpOperatorSeeder`, not called by `DatabaseSeeder`, with safe synthetic output and no credential output or reset.

## Baseline and execution

- Target: `.` resolved to `/var/www/mhcs-core`.
- Expected repository remote: `git@github.com:Madeena-software/mhcs-core.git`.
- Baseline and current HEAD at task start: `c0e9348d2d09da83cfcc74efe7e09427e424927`.
- No execution commit was created; changes remain in the working tree as required.
- Published task validation passed with `/usr/bin/python3` because `python` is not installed.

## Verification

Passed:

- `tests/Operator/Mvp04OperatorFoundationTest.php`: 5 tests, 21 assertions.
- `tests/Feature/Operator/Mvp04OperatorPortalTest.php`: 3 tests, 25 assertions.
- `tests/Feature/Admin/Mvp04OperatorAdministrationTest.php`: 2 tests, 22 assertions.
- Targeted MVP-01, MVP-02, non-browser MVP-03, WP-02, WP-04, architecture, and MVP-04 regression command: 122 tests, 2,423 assertions.
- `vendor/bin/pint` on changed PHP files.
- PHP syntax checks on changed PHP files.
- `git diff --check`.
- Operator route inspection, Filament route/resource registration, migration status, provider binding, permissions, audit metadata, outbox payload, and static module-ownership inspection.
- Migration forward check on the normal SQLite database; the MVP-04 migration completed successfully.

Not run by task contract:

- Pest/browser tests and Playwright.
- Full PHPUnit and complete Work Package suites.
- MySQL/Docker conformance, npm build, Composer audit, external adapters, deployment, commit, push, and production operations.

## Open scope and limits

`MVP-GAP-009` remains open. Queue, check-in, ticket, consent, identity-decision,
clinical, walk-in, cash, Image Gateway, FHIR, privacy, CI, deployment, and
production gaps remain open. WP-11, WP-12, and WP-17 are partially implemented;
WP-07 remains not-started except for the exact bounded attendance/arrival
contract consumed here. SQLite verification does not replace MySQL or
production migration evidence.
