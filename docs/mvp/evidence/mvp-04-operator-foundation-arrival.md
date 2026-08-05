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
- Implementation baseline: `eb12e2a6d533adb19b2cef120919b30fdd28e609`, confirmed as an ancestor of `HEAD`.
- Branch and HEAD after execution: `main` at `eb12e2a6d533adb19b2cef120919b30fdd28e609`.
- The remediation remains in the working tree; the published task prohibits staging, committing, and pushing.
- Published task validation passed with `/usr/bin/python3` because `python` is not installed.

Validation command and result:

```text
python3 .agents/skills/agent-task/scripts/validate_task.py .agents/tasks/mhcs-core-mvp-04-operator-arrival-remediation-v1.md
Task contract is valid: /var/www/mhcs-core/.agents/tasks/mhcs-core-mvp-04-operator-arrival-remediation-v1.md
```

## MVP-04A remediation execution

The bounded remediation corrected shared Operator-only and dual-role login
routing, Operator-only mandatory password replacement, exact
`operator.shift.manage` enforcement, recorded-arrival site-switch blocking,
one authoritative attendance/arrival eligibility predicate, explicit
session-bound confirmation, and authoritative schedule redirect behavior.

The execution-path tests prove real `POST /login` behavior, revoked and
inactive access failure, permission separation with execution-time revocation,
first and blocked/resolved site switching, uncharged and unsupported-funding
arrival denial without side effects, confirmation cancellation/expiry, and
tampered schedule input being ignored for final navigation.

## Verification

Passed:

Exact successful PHPUnit commands:

```text
vendor/bin/phpunit tests/Feature/Member/Mvp01MemberAccessTest.php
vendor/bin/phpunit tests/Feature/Admin/Mvp02AdminAccessTest.php tests/Feature/Admin/Mvp02MemberAdministrationTest.php
vendor/bin/phpunit tests/Feature/Member/Mvp03CatalogueBookingTest.php
vendor/bin/phpunit tests/Feature/Admin/Mvp03BookingAdministrationTest.php
vendor/bin/phpunit tests/Member/Mvp03BookingDomainTest.php
vendor/bin/phpunit tests/Feature/Operator/Mvp04OperatorPortalTest.php
vendor/bin/phpunit tests/Operator/Mvp04OperatorFoundationTest.php
vendor/bin/phpunit tests/Feature/Admin/Mvp04OperatorAdministrationTest.php
vendor/bin/phpunit tests/Security/Wp02SecurityTest.php
vendor/bin/phpunit tests/Member/Wp04IdentityTest.php --filter 'identity|registration|activation|recovery|credential'
vendor/bin/phpunit tests/Architecture/FoundationArchitectureTest.php
```

- `tests/Feature/Member/Mvp01MemberAccessTest.php`: 13 tests, 154 assertions.
- MVP-02 admin/account-state files: 32 tests, 283 assertions.
- Non-browser MVP-03 Member/admin/domain files: 21 tests, 257 assertions.
- `tests/Feature/Operator/Mvp04OperatorPortalTest.php`: 8 tests, 63 assertions.
- `tests/Operator/Mvp04OperatorFoundationTest.php`: 9 tests, 41 assertions.
- `tests/Feature/Admin/Mvp04OperatorAdministrationTest.php`: 2 tests, 22 assertions.
- `tests/Security/Wp02SecurityTest.php`: 23 tests, 94 assertions.
- Filtered `tests/Member/Wp04IdentityTest.php`: 17 tests, 113 assertions.
- `tests/Architecture/FoundationArchitectureTest.php`: 6 tests, 1,471 assertions.
- Bounded regression total: 131 tests, 2,498 assertions.
- `vendor/bin/pint` on every changed PHP file.
- `php -l` on every changed PHP file.
- `git diff --check`.
- Static route, permission, audit/outbox, transaction, idempotency, and module-boundary inspection.

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
