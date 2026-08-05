# MVP-03 Controlled B2C Radiology Booking Evidence

## Execution boundary

- Task: `.agents/tasks/mhcs-core-mvp-03-controlled-b2c-radiology-booking-v1.md`.
- Target: `.` resolved to `/var/www/mhcs-core`.
- Task validator: passed before implementation.
- Required baseline: `67e3ca7c6cfd244ce2700868470a45d1d612e4ed`; ancestry confirmed.
- Execution commit observed: `4c9db6dba7e0398eafc4ad80984d2421cfaf030b` (working tree execution; no commit was created).
- Existing route work in `routes/web.php` was preserved after explicit user approval. No task, context, implementation-plan, dependency, production configuration, deployment, staging, or commit mutation was performed.

## Consumed requirement subset

This execution consumes the bounded MVP portions assigned to:

- WP-05: `MEM-020..MEM-037`, `MEM-220` — personal four-decimal ledger, local/testing funding, atomic B2C charge, confirmed booking, and idempotency foundation only.
- WP-06: `MEM-001..MEM-009`, `MEM-038..MEM-064`, `MEM-097..MEM-101`, `MEM-120..MEM-124`, `MEM-134..MEM-146`, `MEM-216..MEM-218` — site references, service catalogue, schedules, quota, booking snapshots, eligibility, and local imaging order only.
- WP-10: bounded Member offering/schedule mutation and booking/site visibility in the existing shared Filament panel.

Requirement assignments and source digests were not changed.

## Schema and ownership

Migration `2026_08_05_000003_create_mvp03_booking_tables.php` adds Member-side
read-only Operator organization/site references, Member-owned offerings and
schedules, one active point-rate record, append-only point ledger entries,
bookings, and one local imaging order per booking. UUID-compatible string keys,
foreign keys, unique constraints, decimal scale 4, ownership/capacity indexes,
and reversible drop ordering are present.

Operator physical-site authority remains outside Member. The only Member-side
site writes are through the local/testing bootstrap boundary; the Filament site
resource is read-only. A schedule and booking each resolve one persisted site,
and booking site identity is server-derived from the locked schedule.

## Catalogue and schedule behavior

Active offerings and active site references are the only new-booking catalogue
records. New bookings require an open future schedule whose start input has an
explicit offset and is stored in UTC. Same-site open schedules use the required
half-open overlap rule; exact end/start boundaries are accepted. Quota is
restricted to 5 through 20, and the Member application service owns create and
update mutations with authorization and audit.

## Points and booking transaction

The initial active exchange rate is IDR 10,000 per Madeena Point. `PointAmount`
uses integer-scaled decimal arithmetic and fixed four-place formatting; no
binary floating-point arithmetic is used for application calculations. The
ledger is append-only and B2C balance sums only personal entries; business
entries are excluded. Synthetic credit is local/testing-only and idempotent.

The booking command locks the Member, schedule, site, service, and active rate;
checks accepted adult/profile/account gates, one active booking, capacity, the
server-derived price, and personal balance; then writes the confirmed B2C
booking, personal charge, local imaging order, sanitized audit events,
idempotency result, and outbox event in one transaction. Failed booking work
rolls back. Browser values are assertions only; Member, site, price, rate,
funding, type, status, snapshots, and order fields are server-owned.

Booking snapshots preserve service code, four-decimal cost, exchange-rate ID,
AI/doctor behavior, and site identity. Later source edits do not rewrite them.

## Eligibility event

The fifth confirmed booking sets one `eligible_at` marker and emits one
versioned `shift_eligible` outbox event. Its payload contains only the schedule
and site reference IDs, UTC times, confirmed count, quota, and event version;
it contains no Member identity, contact, balance, credential, or protected
identifier. No Operator assignment or notification is created.

## Routes, Member UI, and Filament

The declared Member routes are implemented under the existing authenticated
Member middleware: `/member/services`, `/member/services/{service}`,
`/member/schedules`, `POST /member/bookings`, `/member/bookings`, and
`/member/bookings/{booking}`. The dashboard links to the catalogue and booking
history. Views use approved Bahasa Indonesia labels, explicit final booking
confirmation, owner-scoped detail lookup, and safe status/order summaries.

The shared `/admin` panel adds bounded Member-owned offering, schedule, read-only
site-reference, and read-only booking resources. Exact persistent claims are:
`member.catalogue.read`, `member.catalogue.manage`, `member.schedule.read`,
`member.schedule.manage`, `member.booking.read`, `member.booking.manage`, and
`member.booking.audit.read`. Booking mutations are not exposed; booking audit
is separately gated and filtered to bounded booking actions.

## Seeder

`MvpBookingSeeder` is explicit, local/testing-only, synthetic, idempotent, and
not called by `DatabaseSeeder`. It bootstraps one synthetic organization/site,
two offerings, future non-overlapping schedules, the initial rate, and one
deterministic personal credit for the existing synthetic Member. It stops on
inconsistent existing records, creates no Operator account or provider/payment
record, and prints no credential or protected identifier.

## Observed verification

- `python3 .agents/skills/agent-task/scripts/validate_task.py .agents/tasks/mhcs-core-mvp-03-controlled-b2c-radiology-booking-v1.md` — passed.
- `php artisan test tests/Member/Mvp03BookingDomainTest.php tests/Feature/Member/Mvp03CatalogueBookingTest.php tests/Feature/Admin/Mvp03BookingAdministrationTest.php --compact` — 11 tests, 81 assertions passed.
- `php artisan test tests/Feature/Member/Mvp01MemberAccessTest.php tests/Feature/Admin/Mvp02AdminAccessTest.php tests/Feature/Admin/Mvp02MemberAdministrationTest.php --compact` — 45 tests, 437 assertions passed.
- `php artisan test tests/Feature/FoundationFeatureTest.php tests/Unit/SharedFoundationTest.php tests/Architecture/FoundationArchitectureTest.php --compact` — 23 tests, 1,249 assertions passed after the architecture allowlist was reconciled with the accepted MVP migrations and shared Filament provider.
- `php artisan test tests/Security/Wp02SecurityTest.php --filter='audit|authoriz|transaction|outbox|idempot|decimal|money' --compact` — 6 tests, 22 assertions passed.
- `php artisan test tests/Member/Wp04IdentityTest.php --filter='account|authoriz|audit|state|access' --compact` — 4 tests, 29 assertions passed.
- `php artisan route:list --path=member` and `php artisan route:list --path=admin` — inspected; only the declared Member surface and bounded Member admin resources were added.
- `git diff --check` — passed.
- Bounded Pint and PHP syntax checks on changed PHP files — passed.
- Migration status and rollback checks were run only against the normal fast test database; no Docker/MySQL or production database was accessed.

The first architecture command exposed pre-existing allowlist drift for the
accepted profile/authorization migrations and shared Filament provider; the
architecture test was reconciled to the observed accepted baseline plus the
new MVP-03 migration. It passed afterward. SQLite returns raw decimal columns
as numeric values in some direct-query assertions; application `PointAmount`
and Eloquent decimal casts preserve the required four-place behavior. MySQL
concurrency conformance was not claimed.

## Open boundaries

WP-05, WP-06, and WP-10 remain partial. B2B import, business funding, real
top-up/payment, cancellation, rescheduling, postponement, refunds,
revaluation, no-show, attendance, Operator authentication/assignment/queue,
Image Gateway ingestion, FHIR serialization/conformance, notifications,
production credentials, privacy/retention approval, CI/release completion,
deployment, and production readiness remain open.

Full PHPUnit, npm build, Composer audit, Docker, external integrations,
deployment, and production checks were not run.
