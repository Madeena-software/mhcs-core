---
name: mhcs-core-mvp-03-controlled-b2c-radiology-booking
description: Implement the bounded MVP-03 Member radiology catalogue, site and schedule selection, personal-points-funded B2C booking, booking status, and Member-owned administration without real payment gateways, B2B import, Operator workflow, or broader financial behavior.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-03 — Controlled B2C Radiology Booking

## Objective

Implement the first complete Member radiology-request vertical slice on the
accepted MVP-02 baseline:

`67e3ca7c6cfd244ce2700868470a45d1d612e4ed`

Required Member flow:

```text
existing controlled adult Member
→ login and complete the accepted MVP-01 gates
→ browse active radiology services
→ select an active examination site
→ select an open future schedule
→ review point cost and service behavior
→ confirm one B2C booking
→ personal Madeena Points are charged atomically
→ booking becomes confirmed
→ one local imaging order is created
→ Member views current booking status
```

Required administration flow:

```text
authorized Member administrator
→ manage Member-owned service offerings
→ manage Member-owned bookable schedules and quotas
→ view read-only examination-site references
→ view bounded booking detail and booking audit
```

This task consumes bounded portions of:

- `WP-05 — Member bookings, points, funding, cancellation, and revaluation`;
- `WP-06 — Member site, schedule, booking data, and eligibility`; and
- `WP-10 — Member administration`.

This task does not complete those Work Packages.

The initial controlled booking path is:

- adult existing Members only;
- B2C authority only;
- personal Madeena Points only;
- local/testing synthetic point funding only;
- immediate atomic charge and confirmation;
- no external payment provider;
- no B2B reservation/import;
- no cancellation, reschedule, postponement, refund, no-show, walk-in, or
  revaluation flow;
- no Operator login, staffing, queue, or attendance flow;
- no FHIR conformance claim.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
- Ordered model preferences: None.
- Require preferred model: `false`

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Context and evidence

Use:

`67e3ca7c6cfd244ce2700868470a45d1d612e4ed`

as the implementation baseline.

Before editing:

1. Resolve `$TARGET` to a canonical absolute path.
2. Confirm the expected `Madeena-software/mhcs-core` repository.
3. Confirm baseline ancestry.
4. Record branch, current commit, staged, modified, untracked, and relevant
   ignored files.
5. Preserve all pre-existing work.
6. Stop as `awaiting-approval` when existing work overlaps required files.
7. Do not reset, clean, discard, stash, stage, commit, push, deploy, or access
   production.

Read completely before planning or editing:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/context/ui-language.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-source-coverage.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/docs/mvp/evidence/mvp-01-member-access-and-profile.md`;
- `$TARGET/docs/mvp/evidence/mvp-02-shared-admin-shell-member-administration.md`;
- `$TARGET/.agents/tasks/archive/mhcs-core-mvp-01-member-access-and-profile-v1.md`;
- `$TARGET/.agents/tasks/archive/mhcs-core-mvp-02-shared-admin-shell-member-administration-v1.md`;
- `$TARGET/.agents/tasks/archive/mhcs-core-mvp-02-remediation-admin-enforcement-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-02-test-evidence-closure-v1.md`;
- current Member models, migrations, controllers, middleware, routes, Blade
  views, services, factories, seeders, audit, authorization, outbox,
  idempotency, clock, money or decimal primitives, and focused tests;
- current Filament 5 panel, resources, actions, forms, tables, and installed
  testing APIs under `$TARGET/vendor/**`; and
- current database-engine and migration conventions.

Confirm that the implementation plan still assigns:

- `MEM-020..MEM-037` and `MEM-220` to WP-05; and
- `MEM-001..MEM-009`, `MEM-038..MEM-064`, `MEM-097..MEM-101`,
  `MEM-120..MEM-124`, `MEM-134..MEM-146`, and `MEM-216..MEM-218` to WP-06.

Do not alter requirement assignments, classifications, counts, or source
digests.

Use repository and observed command evidence. Do not infer implementation from
task text or documentation alone.

## Source-derived product constraints

Treat these as binding:

1. Member owns service offerings, bookable schedules, bookings, charges,
   personal/business point provenance, booking status, Appointment authority,
   and the local imaging-order authority.
2. Operator owns the physical site master. Member may store first-class,
   read-only site references/projections but must not become the physical-site
   source authority.
3. Every schedule and booking belongs to exactly one site.
4. Active schedules for the same site cannot overlap.
5. One Member identity may have at most one active booking across all sites,
   shifts, and services.
6. Every booking preserves B2B or B2C authority and funding provenance.
7. A B2C booking may consume only personal points.
8. Business-funded points must never fund this MVP-03 B2C path.
9. Point quantities and prices use four decimal places.
10. Point history is immutable. Charges are append-only ledger entries; no
    balance column or destructive ledger edits may be authoritative.
11. Service code, point cost, exchange-rate reference, AI inclusion, and doctor
    inclusion are immutable booking snapshots.
12. The initial point exchange rate is IDR 10,000 per Madeena Point.
13. A confirmed booking and its local imaging order are created through one
    authoritative transaction.
14. Booking quota must remain correct under concurrent requests.
15. The advance-booking eligibility threshold is five confirmed Members.
16. The configured advance-booking quota is between five and twenty.
17. Members may book an open schedule before an Operator is assigned.
18. Crossing from four to five confirmed bookings emits one idempotent,
    versioned `shift_eligible` domain event for later Operator consumption.
19. Later cancellations must not automatically revoke eligibility or remove an
    Operator assignment; cancellation is outside this task.
20. Member-facing MHCS-authored copy follows the approved Bahasa Indonesia
    policy.
21. No FHIR profile conformance may be claimed before approved R5 profiles and
    fixtures exist.
22. NIK, KK, protected identity values, credentials, raw ledger metadata,
    internal role/permission claims, and unrestricted audit metadata must not
    appear in Member or ordinary admin output.

## Scope and constraints

### Included scope

Implement only:

- first-class Member-side read-only Operator organization/site references;
- Member-owned service offerings;
- Member-owned bookable schedules;
- five-confirmed-member eligibility threshold;
- advance-booking quota between five and twenty;
- one active personal-points exchange rate;
- immutable personal/business-aware point ledger foundation;
- local/testing-only synthetic personal-point funding;
- B2C personal-points-funded booking creation;
- one-active-booking enforcement;
- capacity enforcement;
- immutable booking snapshots;
- one local imaging-order record per confirmed booking;
- Member catalogue, schedule selection, booking confirmation, booking list, and
  booking detail/status;
- Member-owned Filament administration required for offerings, schedules, and
  bounded booking visibility;
- focused tests, targeted regressions, and MVP evidence.

### Excluded scope

Do not implement:

- public or online registration;
- child or guardian booking;
- B2B agreement/import/provisioning;
- business-funded reservation allocation;
- real point top-up UI;
- cash top-up;
- payment gateway or bank adapter;
- pending-payment checkout;
- payment-expiry scheduling;
- cancellation;
- rescheduling;
- postponement;
- no-show;
- refunds;
- compensating refund entries;
- exchange-rate revaluation;
- promotions;
- repeat entitlements;
- walk-ins;
- attendance;
- identity verification at arrival;
- Operator authentication, site administration, staffing, assignments, queue,
  check-in, or shift operations;
- Image Gateway ingestion;
- FHIR serialization, profiles, validators, or exchange;
- notifications or external communication;
- production credential delivery;
- production point funding;
- production deployment.

Do not add or replace Composer or npm dependencies.

Do not modify:

- `.agents/context/**`;
- `docs/implementation/**`;
- published task files;
- accepted WP-04 UUID migrations;
- local deployment policy except for a directly required non-production test
  fix, which requires approval.

Do not create a generic cross-module database editor.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `5`
- Approval gates:
  - stop as `awaiting-approval` when baseline ancestry is absent;
  - stop as `awaiting-approval` when overlapping work affects required files;
  - stop as `awaiting-approval` if the requirements matrix or implementation
    plan assignments changed;
  - stop as `awaiting-approval` if the exact existing enum/state vocabulary
    conflicts with this task;
  - stop as `awaiting-approval` if a safe site-reference model cannot preserve
    Operator physical-site authority;
  - stop as `awaiting-approval` if the existing money/decimal primitives cannot
    represent four-decimal points without a dependency or incompatible schema
    rewrite;
  - stop as `awaiting-approval` if correct one-active-booking or capacity
    enforcement requires destructive migration of non-test data;
  - stop as `awaiting-approval` before adding a dependency, external adapter,
    route outside the declared surface, or production configuration;
  - stop as `awaiting-approval` if focused tests reveal a broader accepted
    MVP-01/MVP-02/WP-02/WP-04 regression;
  - stop as `awaiting-approval` before any destructive or
    production-affecting operation.

## Execution procedure

1. Validate this task with the repository task validator.
2. Resolve and verify `$TARGET`.
3. Confirm baseline ancestry and repository state.
4. Read all required architecture, Member, Operator, MVP, requirements,
   implementation-plan, source, test, and Filament evidence.
5. Map the smallest exact requirement subset consumed by this vertical slice.
6. Inspect existing identifier, decimal/money, transaction, row-lock, audit,
   outbox, idempotency, authorization, and seeder conventions.
7. Design the minimum normalized schema without weakening expected-state
   requirements.
8. Implement site-reference and catalogue foundations.
9. Implement schedule creation/update through a Member application service.
10. Implement point-rate, ledger, balance query, and local/testing funding
    foundation.
11. Implement atomic B2C booking creation and local imaging-order creation.
12. Implement the Member-facing catalogue and booking routes/views.
13. Implement bounded Member-owned Filament resources.
14. Implement local/testing synthetic catalogue, site-reference, schedule, and
    point funding.
15. Add focused tests and targeted regressions.
16. Run only declared verification.
17. Update bounded MVP documentation and evidence.
18. Re-read this task against the final diff.
19. Stop without continuing to MVP-04.

## Data and ownership model

Use UUID primary identifiers consistent with accepted repository conventions.

### Operator organization and examination-site references

Implement a Member-side reference/projection boundary for stable Operator-owned
site identity.

Minimum behavior:

- organization/site reference records are read-only to Member business
  operations except through one explicit synchronization/bootstrap application
  boundary;
- Member administration cannot create or edit the physical-site master;
- references preserve:
  - stable Operator organization identifier;
  - stable Operator site identifier;
  - code;
  - display name;
  - time zone;
  - active status;
  - source version or synchronization marker when required by current
    architecture;
- inactive sites disappear from new Member booking availability;
- deactivation does not delete existing bookings;
- site IDs are server-derived from selected persisted schedules, not trusted
  from a separate browser field during booking;
- the local/testing seeder may provision clearly synthetic references;
- do not add Operator accounts, assignments, or Operator admin UI.

Use names consistent with current context and implementation plan. A
Member-owned projection name such as `operator_organization_refs` and
`examination_site_refs` is acceptable. Do not name a Member projection as
though it were the Operator physical-site source authority.

### Service offerings

Implement Member-owned service offerings with at least the source-required
fields:

- UUID ID;
- immutable unique code after first booking use;
- name;
- includes AI;
- includes doctor review;
- point price with four decimal places;
- active status;
- timestamps;
- optional optimistic/concurrency version only when repository conventions
  require it.

Rules:

- point price cannot be negative;
- an inactive offering cannot receive a new booking;
- a service used by a booking cannot be hard-deleted;
- later changes do not rewrite booking snapshots;
- ordinary Member output contains only Member-safe service information;
- no raw internal configuration or clinical payload is exposed.

### Shift schedules

Implement Member-owned bookable schedules.

Required fields and behavior:

- UUID ID;
- examination-site reference;
- service offering;
- starts-at instant;
- ends-at instant;
- advance-booking quota;
- status using the exact source/requirement vocabulary found in repository
  evidence;
- timestamps;
- immutable eligibility timestamp or equivalent idempotency marker when the
  five-confirmed-member threshold is first crossed.

Rules:

- timestamps require an explicit offset at input and persist canonically in UTC;
- `ends_at` must be after `starts_at`;
- new Member booking requires a future open schedule;
- quota must be an integer from 5 through 20;
- the minimum eligibility threshold is fixed at five confirmed Members in this
  task and is not a free-form per-schedule setting;
- no two active/open schedules for the same site may overlap, regardless of
  service offering;
- overlap uses half-open intervals:
  - overlap exists when `new_start < existing_end` and
    `new_end > existing_start`;
  - a schedule ending exactly when another begins does not overlap;
- activating or updating a schedule must evaluate overlap transactionally;
- a schedule with bookings cannot be hard-deleted;
- closing a schedule prevents new bookings but does not rewrite existing
  bookings;
- Member admin mutations use an application service, not direct Filament model
  writes;
- every mutation is authorized and audited.

Do not model Operator staff shifts or assignments here. A Member bookable
schedule is not an Operator staffing record.

### Point exchange rate

Implement the minimum rate-version foundation required for immutable booking
snapshots.

Required behavior:

- UUID ID;
- integer rupiah-per-point;
- status/effective timestamp using the exact repository vocabulary;
- configured-by administrator reference when created through administration;
- one active rate at a time;
- initial local/testing value: `10000`;
- booking snapshots the active rate ID;
- no rate revaluation operation is implemented in this task;
- changing an active rate after point balances or bookings exist requires the
  future revaluation workflow and is therefore not exposed here;
- local/testing bootstrap may create the initial active rate once.

### Point ledger

Implement an immutable Member-owned point ledger foundation.

Required behavior:

- UUID ID;
- Member ID;
- optional booking ID;
- funding source that distinguishes at least personal from business;
- explicit entry type using a bounded exact vocabulary;
- four-decimal signed point delta;
- immutable source/reference identity;
- optional reversal reference reserved for future compensating entries;
- created timestamp;
- append-only persistence;
- no authoritative mutable balance column.

For this task:

- local/testing funding creates only personal credit;
- booking creation creates only personal booking charge;
- B2C balance is the sum of personal entries only;
- business entries, when present in tests, are excluded from B2C spendable
  balance;
- no refund, reservation, release, revaluation, or forfeiture command is
  exposed;
- no ordinary administrator may directly create, edit, or delete ledger rows;
- no Member can supply a point delta, funding source, price, or ledger type;
- point arithmetic uses decimal-safe application behavior and database decimal
  columns, never binary floating point;
- round only according to existing approved decimal conventions; this task must
  not invent a new conversion or revaluation rule.

Use exact enum values already specified by the requirement registry or existing
implementation conventions. When no exact value exists, use the smallest
self-describing bounded values and document them in evidence; do not introduce
generic arbitrary strings.

### Bookings

Implement Member-owned B2C booking records.

Required fields include:

- UUID ID;
- Member ID;
- shift schedule ID;
- service offering ID;
- booking type/authority fixed to B2C for this route;
- funding provenance fixed to personal for this route;
- status;
- service code snapshot;
- point-cost snapshot with four decimal places;
- exchange-rate ID snapshot;
- includes-AI snapshot;
- includes-doctor snapshot;
- site ID snapshot or stable schedule relation sufficient to preserve site
  identity;
- created/confirmed timestamps;
- timestamps and optional version fields required by repository conventions.

Required status for successful MVP-03 creation is the existing approved
`confirmed` state.

Do not expose a generic status editor.

One-active-booking invariant:

- active states are the approved Member states:
  - `pending_payment`;
  - `confirmed`;
  - `arrived`;
  - `checked_in`;
  - `in_progress`;
  - `postponed`;
- this route creates only `confirmed`;
- a Member with any active booking cannot create another;
- enforce through a transaction, row lock, and database-supported invariant
  where feasible;
- use Member ID, never plaintext NIK;
- a suspended account preserves its booking but cannot use the Member portal.

Capacity:

- count capacity-consuming active bookings according to the approved booking
  lifecycle;
- lock the schedule before count and insert;
- reject when confirmed/active capacity reaches quota;
- failed charge/order/audit/outbox work rolls back the booking and capacity
  use;
- no overbooking under supported transactional tests;
- SQLite-focused tests must not claim MySQL concurrency conformance;
- reserve final MySQL race validation for the later integration/release gate
  unless a bounded local MySQL test already exists and can run without Docker.

Snapshots:

- obtain service, site, schedule, price, rate, AI, and doctor behavior from
  locked persisted records;
- browser values are assertions only;
- later offering/rate/site changes do not alter the booking snapshot.

### Local imaging order

Create one Member-owned local imaging order for each confirmed booking in the
same authoritative transaction.

Required behavior:

- stable UUID;
- unique booking relation;
- Member relation;
- schedule/site/service relation or immutable references;
- requested service-code snapshot;
- status using a minimal exact local vocabulary;
- authored timestamp;
- replacement lineage field reserved only when supported by current
  requirements;
- no Encounter reference before arrival;
- no DICOM UID, ImagingStudy, report, or clinical result;
- no FHIR JSON or profile-conformance claim.

Name the local model/table clearly as an internal imaging order or service
request without implying that unconstrained relational data itself is a
FHIR-conformant `ServiceRequest`.

## Application services

### Catalogue query

Provide a Member application query that returns only:

- active offerings;
- active site references;
- future open compatible schedules;
- schedule start/end in the site's time zone for presentation;
- quota;
- confirmed count;
- remaining capacity;
- whether the five-confirmed threshold has been reached;
- point price;
- AI/doctor inclusion labels.

Do not return:

- NIK/KK;
- protected identity values;
- internal claims;
- raw ledger rows;
- audit metadata;
- other Members;
- Operator assignment details;
- hidden inactive catalogue records.

### Create B2C booking

Provide one explicit command/service.

Input may include only:

- selected schedule ID;
- optional idempotency/request identity when the current architecture requires
  it;
- explicit confirmation of the displayed point cost/version when used as a
  stale-price assertion.

The authenticated context supplies the User and Member.

The command must:

1. require an active authenticated Member eligible for the accepted portal;
2. resolve exactly one Member from the authenticated User;
3. require completed profile and existing MVP-01 gates;
4. reject child/dependent flow in this MVP;
5. lock the Member;
6. reject an existing active booking;
7. lock and validate the schedule;
8. derive and lock the site reference and service offering;
9. reject inactive site, inactive offering, closed/past schedule, mismatch, or
   exhausted quota;
10. resolve and lock the active point exchange rate;
11. calculate personal spendable balance from immutable ledger entries;
12. reject insufficient personal points without consuming capacity;
13. exclude business-funded points from the spendable calculation;
14. append the personal booking charge;
15. create one confirmed B2C booking with immutable snapshots;
16. create one local imaging order;
17. append sanitized audit evidence;
18. emit required versioned domain/outbox events in the same transaction;
19. when confirmed count crosses from four to five:
    - persist the schedule eligibility marker atomically;
    - emit exactly one idempotent `shift_eligible` event;
20. return a Member-safe result;
21. roll back every local write on any failure.

Reusing the same idempotency key and same normalized input must return the
original booking. Reusing it with changed input must fail as a conflict when
the current accepted idempotency foundation is used.

Do not accept from the browser:

- Member/User ID;
- point amount;
- point price;
- exchange-rate ID;
- funding source;
- booking type;
- booking status;
- service snapshot;
- site ID separate from schedule authority;
- AI/doctor flags;
- eligibility state.

### Booking queries

Provide owner-scoped Member queries:

- list the authenticated Member's bookings;
- view one authenticated Member booking;
- show service, site, schedule, cost, booking type, current status, and safe
  order reference;
- never reveal another Member's booking;
- never expose ledger rows, protected identifiers, claims, raw audit, or
  clinical/internal metadata.

## Authorization

Add exact Member-owned permissions using existing persistent claim
infrastructure.

Preferred exact permissions:

```text
member.catalogue.read
member.catalogue.manage
member.schedule.read
member.schedule.manage
member.booking.read
member.booking.manage
member.booking.audit.read
```

Use an equivalent bounded naming scheme only when existing repository
conventions require it.

Rules:

- Member portal catalogue and owner booking actions use authenticated Member
  ownership, not administrator permissions.
- Admin catalogue read/manage requires exact administrator role and exact
  permission.
- Admin schedule read/manage requires exact administrator role and exact
  permission.
- Admin booking read requires exact administrator role and exact permission.
- This task does not expose general admin booking mutation.
- Audit visibility requires a separate exact permission.
- Caller-supplied claims never grant access.
- Panel access remains governed by `member.admin.access`.
- Update `MvpAdminSeeder` only to reconcile the new expected Member
  administration permissions safely:
  - preserve password;
  - add only missing expected active claims;
  - do not reactivate inactive claims;
  - stop on unrelated/inconsistent state;
  - remain local/testing-only;
  - do not print the credential on repeat runs.

## Filament administration

Keep all resources Member-owned under the existing shared `/admin` panel.

### Site references

Provide a read-only resource or bounded selector behavior.

- show code, name, time zone, active state, and source identifier only;
- no create/edit/delete;
- no Operator account/assignment/staffing fields;
- no direct source synchronization UI;
- no cross-module generic editing.

### Service offerings

Provide bounded list, create, view, and edit.

- use Member application service for mutation;
- validate code uniqueness and immutability after booking use;
- validate four-decimal non-negative price;
- manage AI/doctor inclusion flags;
- activate/deactivate;
- no hard delete after use;
- require reason for deactivation when current audit conventions require it;
- no bulk mutation/import/export;
- audit every mutation.

### Schedules

Provide bounded list, create, view, and edit/activate/close behavior.

- use Member application service;
- select only active persisted site references and active offerings;
- require explicit-offset time input or safe time-zone conversion with an
  unambiguous stored UTC result;
- enforce start/end, future/open, quota 5..20, and no-overlap invariants
  server-side;
- display confirmed count, remaining quota, and threshold state;
- no staffing assignment fields;
- no Operator shift mutation;
- no bulk create/update/delete;
- audit every mutation.

### Bookings

Provide read-only list/detail for authorized Member administrators.

Display only safe operational/financial summary:

- booking ID;
- Member name and MRN;
- service snapshot;
- site and schedule;
- booking type;
- funding source label;
- point-cost snapshot;
- status;
- AI/doctor snapshot;
- created/confirmed times;
- safe order reference;
- bounded relevant audit when separately authorized.

Do not display:

- NIK/KK;
- identity documents;
- addresses or emergency contacts;
- password/session values;
- claims;
- raw ledger metadata;
- unrestricted audit metadata;
- other clinical content.

Do not add admin create/edit/delete/status-change/refund/cancel/reschedule
actions in this task.

## Member-facing routes and views

Add only the minimum route surface under existing Member portal middleware.

Preferred routes:

```text
GET  /member/services
GET  /member/services/{service}
GET  /member/schedules
POST /member/bookings
GET  /member/bookings
GET  /member/bookings/{booking}
```

Equivalent route names are acceptable when consistent with current conventions.

Requirements:

- all routes require accepted Member portal access;
- incomplete profile redirects to profile;
- suspended/login-disabled users fail closed;
- Member dashboard links to services and bookings;
- Member-safe Bahasa Indonesia copy;
- CSRF protection;
- validation errors do not expose internal state;
- booking confirmation clearly shows:
  - service;
  - site;
  - schedule;
  - point cost;
  - AI and/or doctor review inclusion;
- explicit final confirmation is required before spending points;
- double submission is idempotent or safely conflicts;
- another Member's booking returns 404 or equivalent non-disclosing denial;
- no public route or API is added.

Do not implement a JavaScript SPA or add frontend dependencies. Use the accepted
Blade/Laravel approach.

## Schedule eligibility event

When a schedule first reaches five confirmed Members:

- persist one immutable `eligible_at` timestamp or equivalent idempotency
  marker;
- append one versioned outbox/domain event in the same transaction;
- event name should clearly communicate Member-owned bookable-shift
  eligibility;
- payload contains only stable IDs and sanitized operational facts:
  - schedule ID;
  - site reference ID;
  - starts/ends timestamps;
  - confirmed count;
  - quota;
  - event version;
- no Member identity, NIK, contact, point balance, or credentials;
- replay or concurrent fifth booking must not create duplicate eligibility
  events;
- no Operator handler, invitation, assignment, SMS, push, or escalation is
  implemented here.

## Audit

Use existing append-only audit infrastructure.

Audit at least:

- site-reference bootstrap/synchronization;
- service create/update/activate/deactivate;
- schedule create/update/activate/close;
- point-rate bootstrap;
- local/testing personal-point funding;
- B2C booking success/failure categories;
- point charge;
- imaging-order creation;
- threshold eligibility crossing;
- admin booking view when current access policy requires it.

Do not record:

- raw protected identifiers;
- passwords;
- point balance snapshots when unnecessary;
- request/session claims;
- raw form payloads;
- internal exception traces;
- unrestricted ledger or audit metadata.

Booking charge, booking, order, audit, outbox, and idempotency evidence must
follow the same transaction.

## Local/testing synthetic data

Add one explicit local/testing-only seeder, preferred:

```text
MvpBookingSeeder
```

It may create:

- one clearly synthetic Operator organization reference;
- one clearly synthetic active examination-site reference;
- two active synthetic service offerings;
- future non-overlapping schedules;
- one active point exchange rate at IDR 10,000 per point;
- a personal-point funding record and ledger credit for the existing synthetic
  MVP Member, or a clearly specified target Member;
- no B2B funding;
- no Operator account;
- no real payment/provider reference;
- no protected real identity data.

Rules:

- refuse outside `local` and `testing`;
- do not run automatically from `DatabaseSeeder`;
- idempotent;
- do not reset Member credentials;
- do not duplicate credits, schedules, rates, or offerings;
- do not silently alter inconsistent existing records;
- print no credential;
- print no raw protected identifier;
- report only safe synthetic IDs and next-step URLs when useful.

Use a deterministic operation/source identity so repeated execution cannot
credit points twice.

## Migrations

Create forward migrations following accepted repository conventions.

Requirements:

- UUID-compatible keys;
- foreign keys and unique constraints;
- decimal point columns with scale 4;
- indexes for active catalogue, upcoming schedules, Member active bookings,
  capacity counting, ledger summation, booking ownership, order lookup, and
  outbox/idempotency use;
- reversible `down()` ordering;
- no alteration of accepted identity migrations;
- no destructive rewrite of existing data;
- no database-generated floating point;
- no production data reset.

One-active-booking enforcement may use:

- transaction and Member row lock as the authoritative portable boundary; and
- a database constraint/index only when supported safely by both test and
  target databases without encoding unsupported lifecycle assumptions.

Document database-engine differences honestly.

## Focused tests

Create focused tests under bounded Member booking/admin boundaries.

Preferred files:

```text
tests/Feature/Member/Mvp03CatalogueBookingTest.php
tests/Feature/Admin/Mvp03BookingAdministrationTest.php
tests/Member/Mvp03BookingDomainTest.php
```

Equivalent bounded organization is acceptable.

### Catalogue and site tests

Prove:

- only active offerings/sites/future open schedules appear;
- inactive site prevents new availability;
- inactive offering prevents new availability;
- schedule belongs to one persisted site reference;
- Member cannot select an arbitrary site separate from schedule;
- safe catalogue contains no protected identity or internal claims;
- site references are read-only in Member admin;
- no Operator admin resource is added.

### Schedule tests

Prove:

- explicit-offset input normalizes to UTC;
- end must be after start;
- quota below 5 is rejected;
- quota above 20 is rejected;
- quota 5 and 20 are accepted;
- overlapping active/open schedules at one site are rejected;
- exact boundary end/start is allowed;
- schedules at different sites may overlap;
- closed/inactive schedules cannot receive new bookings;
- schedule admin requires exact permissions;
- browser claims cannot authorize schedule management;
- schedule mutation uses the application boundary and writes sanitized audit.

### Point tests

Prove:

- four-decimal values persist exactly;
- personal and business funding are separated;
- B2C spendable balance excludes business entries;
- ledger is append-only;
- no admin/member route mutates raw ledger rows;
- local/testing funding is idempotent;
- insufficient personal balance fails without partial writes;
- price is server-derived;
- active rate is snapshotted;
- no binary-float drift appears in charged and remaining values.

### Booking tests

Prove:

- authenticated eligible Member can create one B2C booking;
- booking is immediately `confirmed`;
- booking uses personal funding only;
- point charge, booking, order, audit, outbox, and idempotency commit together;
- failure rolls all of them back;
- one Member cannot hold two active bookings across different schedules/sites;
- another Member may book independently;
- capacity cannot exceed quota in focused transactional tests;
- schedule/service/site/rate snapshots are immutable after source edits;
- unexpected payload cannot alter Member, price, rate, funding source, type,
  status, site, AI, doctor, or order fields;
- duplicate same request returns original result or a safe idempotent response;
- duplicate changed request conflicts;
- another Member cannot view the booking;
- suspended Member cannot use booking routes;
- profile-incomplete Member is redirected;
- booking output contains no protected identity, credentials, claims, raw
  ledger, or unrestricted audit.

### Eligibility threshold tests

Prove:

- confirmed counts one through four do not mark eligible;
- the fifth confirmed booking marks the schedule eligible;
- exactly one `shift_eligible` outbox/domain event exists;
- event contains only approved stable operational data;
- sixth and later bookings do not duplicate eligibility;
- concurrent/replayed fifth-booking behavior remains idempotent within the
  supported test boundary;
- no Operator assignment or notification is created.

### Admin tests

Prove:

- exact read/manage permissions are independent;
- offerings and schedules appear only with read permission;
- mutations require manage permission at execution time;
- booking admin is read-only;
- safe fields only;
- no create/edit/delete/cancel/refund/reschedule/status mutation for bookings;
- no bulk mutation/import/export;
- bounded booking audit requires separate permission;
- no Operator, Image Gateway, or Doctor resource is introduced by MVP-03.

### Seeder tests

Prove:

- refuses outside local/testing;
- creates only synthetic references/catalogue/rate/funding;
- does not create credentials or Operator users;
- repeated execution creates no duplicates or double credit;
- inconsistent state causes a stop;
- not invoked by `DatabaseSeeder`;
- output contains no password, NIK, KK, or protected value.

## Targeted regression boundary

Run focused existing tests affected by changed files.

At minimum:

- accepted MVP-01 Member access/profile tests;
- accepted MVP-02 admin access/Member administration tests;
- filtered WP-02 audit, authorization, transaction, outbox, idempotency, and
  decimal/money tests where present;
- filtered WP-04 Member identity/account-state tests;
- architecture/module-boundary tests affected by new Member records and
  Operator site references.

Do not run complete WP suites unless focused filtering cannot safely select the
affected behavior.

## Documentation updates

Update only bounded MVP documentation:

```text
docs/mvp/roadmap.md
docs/mvp/beta-gap-register.md
docs/mvp/work-package-status.md
docs/mvp/decision-log.md
docs/mvp/evidence/mvp-03-controlled-b2c-radiology-booking.md
```

Requirements:

### Roadmap

- record MVP-03 implementation status;
- do not describe MVP-04 as started;
- preserve later numbering.

### Gap register

- preserve stable IDs;
- close `MVP-GAP-011` only when the complete bounded Member catalogue,
  booking, local order, and status acceptance passes;
- state that closure covers only controlled adult B2C personal-points booking
  with synthetic local/testing funding;
- keep B2B import, real payment/top-up, cancellation/refund, Operator,
  Image Gateway, production credential, privacy, deployment, and CI gaps open;
- add a stable gap only for a real newly discovered limitation not already
  represented.

### Work Package ledger

- mark WP-05 `partially-implemented` only for personal ledger, atomic B2C
  charge, and confirmed-booking foundation;
- mark WP-06 `partially-implemented` only for site references, offerings,
  schedules, quota, booking snapshots, eligibility, and local order;
- extend WP-10 evidence for bounded offering/schedule/booking administration;
- keep every deferred item explicit.

### Decision log

Record an approved controlled-beta implementation decision only if repository
task rules permit implementation evidence to record it:

```text
MVP-03 controlled booking uses local/testing synthetic personal-point funding;
no real top-up/payment adapter is exposed.
```

This does not approve production point funding or financial operations.

### Evidence

Record:

- baseline and execution commit;
- requirement subset consumed;
- schema and ownership boundaries;
- route and admin surface;
- point and booking transaction behavior;
- threshold event behavior;
- exact focused commands and observed results;
- targeted regressions;
- migration and formatting checks;
- tests not run;
- database-engine limitations;
- open gaps;
- no production-readiness claim.

Do not include protected identifiers, credentials, or synthetic plaintext
secrets.

## Verification

- Method: Validate the task; inspect the final diff; run focused MVP-03
  Member/domain/admin tests; run directly affected MVP-01 and MVP-02 tests;
  run filtered WP-02/WP-04/architecture regressions; run bounded Pint on
  changed PHP files; run `git diff --check`; inspect routes, Filament
  resources, migrations, authorization permissions, audit metadata, outbox
  payloads, and local/testing seeder output.
- Expected result: An existing controlled adult Member can spend only personal
  synthetic points to atomically create one confirmed B2C radiology booking
  and local imaging order for an active offering/site/schedule; capacity,
  one-active-booking, snapshots, schedule overlap, quota 5..20, and
  five-confirmed eligibility remain enforced; Member-owned administration is
  bounded; and no real payment, B2B, Operator, FHIR, deployment, or unrelated
  scope is added.

Required:

```bash
git diff --check
```

Run bounded Pint only on changed PHP files.

Run route inspection for `/member` and `/admin`.

Run migration status/rollback checks only against the normal fast test
database. Do not run Docker or production databases.

Do not run:

- full PHPUnit;
- complete WP-02/WP-04 suites;
- MySQL/Docker conformance unless already available without environment changes;
- npm build;
- Composer audit;
- external integrations;
- deployment or production checks.

## Acceptance criteria

- [ ] Baseline ancestry and repository state are confirmed.
- [ ] Published task validation passes.
- [ ] Existing work is preserved.
- [ ] Requirement assignments and source digests remain unchanged.
- [ ] No dependency, network boundary, production configuration, or generic editor is added.
- [ ] Operator physical-site authority remains intact.
- [ ] Member site references are read-only outside the explicit bootstrap/synchronization boundary.
- [ ] Service offerings preserve code, price, AI, doctor, active state, and snapshots.
- [ ] Point prices and ledger values use four decimal places without float drift.
- [ ] Active/open schedules at one site cannot overlap.
- [ ] Exact boundary schedules do not falsely overlap.
- [ ] Schedule quota accepts only 5 through 20.
- [ ] Minimum confirmed eligibility threshold is fixed at five.
- [ ] One active booking per Member is enforced by Member ID.
- [ ] Capacity cannot exceed quota in the supported transactional boundary.
- [ ] B2C booking consumes only personal points.
- [ ] Business-funded points cannot fund a B2C booking.
- [ ] Insufficient personal points produce no partial booking, charge, order, audit, outbox, or idempotency success.
- [ ] Browser payload cannot choose Member, site, price, rate, funding source, type, status, AI, doctor, or order fields.
- [ ] Booking snapshots remain unchanged after source edits.
- [ ] Successful booking creates one confirmed booking.
- [ ] Successful booking creates one immutable personal charge.
- [ ] Successful booking creates one local imaging order.
- [ ] Booking, charge, order, audit, outbox, and idempotency commit atomically.
- [ ] Duplicate same request is idempotent.
- [ ] Changed replay conflicts.
- [ ] Fifth confirmed booking marks schedule eligible exactly once.
- [ ] Eligibility event contains no Member identity or protected value.
- [ ] No Operator assignment/notification is created.
- [ ] Member can browse services, sites, schedules, own bookings, and safe status.
- [ ] Another Member cannot view or mutate the booking.
- [ ] Suspended and profile-incomplete Members fail closed through accepted gates.
- [ ] Member-facing copy follows approved Bahasa Indonesia policy.
- [ ] Member admin can manage only offerings and Member-owned schedules with exact permissions.
- [ ] Site references and bookings remain read-only in admin.
- [ ] Booking audit is separately authorized and bounded.
- [ ] No NIK, KK, identity asset, password, session, claim, raw ledger, or unrestricted audit value is exposed.
- [ ] Seeder is local/testing-only, idempotent, and cannot double credit points.
- [ ] Seeder creates no Operator account or real provider/payment record.
- [ ] Focused MVP-03 tests pass.
- [ ] Affected MVP-01 and MVP-02 regressions pass.
- [ ] Filtered WP-02/WP-04/architecture regressions pass.
- [ ] Bounded Pint passes.
- [ ] `git diff --check` passes.
- [ ] Route and Filament inspection confirms only declared surface expansion.
- [ ] Evidence records only observed results.
- [ ] `MVP-GAP-011` closes only after all bounded acceptance passes.
- [ ] WP-05, WP-06, and WP-10 remain partial rather than complete.
- [ ] No B2B, real payment, cancellation, refund, Operator flow, FHIR, deployment, commit, or push is added.

## Stop conditions

Stop as `awaiting-approval` when:

- baseline ancestry is absent;
- overlapping work affects required files;
- requirement assignments changed;
- an exact required state/enum conflicts with current authoritative evidence;
- site-reference design would transfer physical-site authority to Member;
- four-decimal point safety cannot be implemented without a dependency or
  incompatible migration;
- one-active-booking or capacity safety requires destructive data migration;
- existing production/non-test data cannot be migrated forward safely;
- a payment, notification, external provider, FHIR, or Operator implementation
  becomes necessary;
- focused tests reveal a broader accepted regression;
- a route, dependency, network boundary, deployment, or production policy
  change is required;
- a destructive or production-affecting operation is required.

When stopped, report:

- exact conflict;
- affected requirement and files;
- work completed;
- safe options;
- owner decision required;
- repository state.

## Output

- `succeeded`: all acceptance criteria and focused verification pass.
- `failed`: implementation ran but a required criterion failed.
- `blocked`: required tooling or repository evidence is unavailable.
- `awaiting-approval`: an approval gate or stop condition is reached.
- `exhausted`: iteration limit is reached before completion.

## Final report

Report:

- baseline and execution commit;
- requirement subset consumed;
- files changed;
- schema and ownership boundaries;
- site-reference behavior;
- catalogue and schedule behavior;
- point-rate and ledger behavior;
- booking transaction and snapshot behavior;
- local imaging-order behavior;
- eligibility threshold and event behavior;
- Member route/UI behavior;
- Filament administration behavior;
- authorization and audit behavior;
- seeder behavior;
- focused tests and observed results;
- targeted regressions and observed results;
- migration, formatting, diff, route, provider, and static-review checks;
- documentation and gap changes;
- tests not run;
- remaining MVP-03 and broader Work Package gaps;
- confirmation that no real payment, B2B, cancellation/refund, Operator,
  Image Gateway, FHIR, deployment, commit, push, or unrelated feature was
  added.

Do not include credentials or protected identifiers.

Do not commit or push.

Stop after this bounded MVP-03 task.
