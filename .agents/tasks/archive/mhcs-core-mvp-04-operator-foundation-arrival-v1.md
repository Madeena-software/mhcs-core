---
name: mhcs-core-mvp-04-operator-foundation-arrival
description: Implement the first bounded MVP-04 slice for Operator access, physical-site authority, active-site context, eligible-shift intake, manual assignment, attendance, and arrival recording.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04 — Operator Foundation and Arrival

## Objective

Implement the first bounded vertical slice of MVP-04 on baseline:

`c0e9348d2d09da83cfcc74efe7e09427e4249277`

Required operational flow:

```text
global administrator
→ provisions an Operator-owned physical site
→ provisions an Operator account
→ assigns the Operator to the site
→ receives an eligible Member-owned schedule reference
→ manually assigns the Operator to that schedule
```

```text
assigned Operator
→ signs in through the shared MHCS authentication foundation
→ selects one authorized active site
→ views the bounded attendance list for an eligible schedule
→ records a Member's physical arrival using actual occurrence time
→ Member-owned booking moves from confirmed to arrived
→ arrival appears in the Operator verification worklist
```

This task establishes:

- Operator-owned physical-site authority;
- Operator account/profile and site assignment;
- shared authenticated Operator access;
- exactly one active site context per Operator session;
- idempotent intake of Member `shift_eligible` events;
- manual schedule assignment;
- a bounded Member attendance query;
- arrival recording;
- Member booking transition to `arrived`;
- Operator and administrator audit evidence; and
- the initial Operator portal and Operator-owned administration surface.

This task is **MVP-04 slice A**.

It does not close MVP-04. Identity-document/face comparison, consent,
`checked_in`, ticket issuance, staged queue, basic examination and vital signs,
public LCD, walk-ins, X-ray execution, and Image Gateway submission remain in
later bounded MVP-04/MVP-05 tasks.

Pest/Playwright installation, optimization, browser-database work, and browser
suite execution are explicitly deferred until the post-MVP test-platform and
release-hardening phase.

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

`c0e9348d2d09da83cfcc74efe7e09427e4249277`

as the implementation baseline.

Before editing:

1. Resolve `$TARGET` to a canonical absolute path.
2. Confirm the expected `Madeena-software/mhcs-core` repository.
3. Confirm baseline ancestry.
4. Record branch, current commit, staged, modified, untracked, and relevant
   ignored paths.
5. Preserve all existing work.
6. Stop as `awaiting-approval` if current work overlaps required files.
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
- `$TARGET/docs/mvp/evidence/mvp-03-controlled-b2c-radiology-booking.md`;
- accepted MVP-01, MVP-02, and MVP-03 task files;
- current shared authentication, claim resolution, authenticated context,
  audit, outbox, idempotency, clock, account-state, and transaction
  implementation;
- current Member site-reference, schedule, booking, local-order, and catalogue
  implementation;
- current Operator module provider, routes, models, services, migrations, tests,
  and configuration;
- current shared Filament `/admin` panel and module resource conventions;
- current PHPUnit test organization and commands; and
- installed Laravel 13 and Filament 5 source required to verify the selected
  implementation APIs.

Treat repository files and installed-package source as evidence.

Do not use external examples to override repository authority.

Confirm the relevant requirements remain assigned as follows:

- WP-11:
  - `OPR-001..OPR-014`;
  - `OPR-100..OPR-107`;
  - `OPR-117..OPR-124`;
  - `OPR-129`.
- WP-12 foundations relevant to this slice:
  - `OPR-015`;
  - bounded pre-verification worklist behavior only.
- WP-17 administration/security foundations relevant to this slice:
  - `OPR-074..OPR-076`;
  - `OPR-085`;
  - `OPR-108..OPR-110`;
  - `OPR-115..OPR-116`;
  - `OPR-134`.
- Member cross-module attendance and arrival requirements:
  - `MEM-068..MEM-075`;
  - `MEM-108`;
  - `MEM-113`;
  - `MEM-178..MEM-182`;
  - the arrival portion of `MEM-186`.

Do not alter requirement IDs, assignments, source digests, or expected-state
specifications.

## Source-derived boundaries

Treat these as binding:

1. Operator Core is the source authority for physical-site master data.
2. Member Core remains authority for Member identities, schedules, bookings,
   points, Member status, and Member-owned site references.
3. One shared MHCS User/authentication foundation is used. No separate Operator
   identity or credential store is created.
4. Operator is one permission/role. Front desk, basic examination, and
   radiography are station labels, not separate permissions or staff roles.
5. A User may hold both administrator and Operator authority when persisted
   claims and assignments permit it.
6. An Operator may be assigned to multiple sites but has exactly one active
   site context per session.
7. Caller-supplied Operator, site, schedule, booking, Member, role, permission,
   or active-site values never grant authority.
8. Site switching must be explicit and audited.
9. A site switch is rejected while the Operator owns unresolved operational
   work. In this first slice, an unresolved arrival command or active assignment
   operation is sufficient to fail closed; later queue-claim and cash-shift
   blockers are deferred.
10. Member schedules and bookings reference stable Operator site identity.
11. Disabling a site prevents new Member availability for that site but does
    not delete or silently cancel existing bookings.
12. Member Core emits one versioned idempotent `shift_eligible` event when the
    confirmed threshold is first crossed.
13. Operator Core consumes that event idempotently and does not mutate the
    Member schedule.
14. Operator assignment belongs to Operator Core.
15. Attendance is obtained through a local Member query/contract, never by
    directly treating Member tables as Operator-owned records.
16. Attendance is scoped to the authenticated Operator's active site,
    authorized schedule/assignment, and requested occurrence time.
17. Attendance contains only the minimum operational fields.
18. The attendance list exposes masked NIK only when the accepted protected
    identifier boundary supports it; full NIK never appears in a list, URL,
    log, audit reason, or Operator-owned table.
19. Exact NIK lookup, identity images, identity decisions, and consent are not
    implemented by this task.
20. Recording arrival uses the actual occurrence time, requires an explicit
    offset, and persists canonically in UTC.
21. Replayed arrival with the same operation identity and normalized input
    returns the original result.
22. Reuse of the same operation identity with changed input fails as conflict.
23. Operator Core records its arrival operation, while Member Core performs the
    authoritative booking-state transition.
24. A booking may move from `confirmed` to `arrived` only once through this
    bounded flow.
25. A delayed valid arrival event whose occurrence time is within the schedule
    window preserves both occurrence and recording time.
26. This task does not create an Encounter.
27. This task does not mark a booking `checked_in`.
28. No ticket is issued before identity verification and consent.
29. Operator-owned records and Member-owned records remain distinct even in one
    database and runtime.
30. Cross-module synchronous work uses explicit local application contracts,
    not network calls or module credentials.
31. Cross-module asynchronous work uses versioned events and idempotent
    consumers.
32. Every mutation is authorized at execution time and audited.
33. Operator-facing professional terminology may remain precise; any
    Member-visible copy follows the approved Bahasa Indonesia policy.
34. No Pest or browser testing work is part of this task.

## Scope and constraints

### Included scope

Implement only:

- Operator profile linked one-to-one to shared User;
- Operator-owned physical-site master;
- Operator-to-site assignment;
- active site context in the authenticated session;
- safe site switch;
- Operator-owned eligible-shift projection/reference;
- idempotent `shift_eligible` event consumption;
- manual Operator-to-schedule assignment;
- bounded attendance query through Member application boundary;
- physical-arrival recording;
- Member booking `confirmed → arrived` transition;
- Operator pending-verification worklist;
- bounded Operator portal;
- bounded Operator-owned Filament administration;
- synthetic local/testing seed data;
- focused PHPUnit, Livewire, and architecture/security tests;
- targeted regressions;
- MVP documentation and evidence.

### Excluded scope

Do not implement:

- Pest/Playwright installation, upgrade, optimization, or browser execution;
- Laravel Dusk;
- public Operator registration;
- separate Operator credentials or identity;
- automated sequential Operator assignment;
- invitations, countdowns, SMS, push, email, or escalation;
- actual KTP/KIA or profile-photo display;
- exact-NIK lookup;
- identity match/mismatch decision;
- administrator identity-dispute resolution;
- consent recording or consent scans;
- `checked_in`;
- ticket issuance or printing;
- public LCD;
- staged queue claims/calls/skips/recalls;
- basic examination or vital signs;
- structured clinical interview;
- walk-in registration;
- cash top-up or cash closing;
- X-ray examination start;
- Encounter creation;
- protocol capture execution;
- NPZ upload;
- Image Gateway submission;
- AI status;
- earnings or payouts;
- FHIR serialization or conformance;
- external adapters;
- notifications;
- production storage;
- production credentials;
- deployment.

Do not:

- add or upgrade Composer/npm dependencies;
- modify existing Pest or browser-test files;
- run browser tests;
- add CI workflows;
- create a generic cross-module database editor;
- modify `.agents/context/**`;
- modify `docs/implementation/**`;
- modify published tasks;
- rewrite accepted Member migrations;
- access production;
- commit or push.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `5`
- Approval gates:
  - stop as `awaiting-approval` if baseline ancestry is absent;
  - stop as `awaiting-approval` if existing work overlaps required files;
  - stop as `awaiting-approval` if requirement assignments or source digests
    changed;
  - stop as `awaiting-approval` if the current shared login architecture cannot
    support a non-Member Operator without a broader authentication redesign;
  - stop as `awaiting-approval` if establishing Operator site authority requires
    destructive migration of existing Member site references;
  - stop as `awaiting-approval` if stable site synchronization cannot preserve
    existing Member bookings and schedules;
  - stop as `awaiting-approval` if the exact `shift_eligible` event currently
    emitted by Member is incompatible with the approved Operator contract;
  - stop as `awaiting-approval` if arrival transition requires consent,
    identity-image access, ticket issuance, or Encounter creation;
  - stop as `awaiting-approval` before adding a dependency, route outside the
    declared surface, external adapter, CI workflow, or production
    configuration;
  - stop as `awaiting-approval` if focused tests expose a broader accepted
    MVP-01/MVP-02/MVP-03/WP-02/WP-04 regression;
  - stop before destructive or production-affecting work.

## Execution procedure

1. Validate this task with the repository task validator.
2. Resolve and verify `$TARGET`.
3. Confirm baseline ancestry and repository state.
4. Read all required authority and implementation evidence.
5. Map the exact bounded requirement subset consumed by this task.
6. Inspect current User, claims, session, Member site references, schedules,
   bookings, outbox, idempotency, audit, and Filament conventions.
7. Design the minimum normalized Operator schema.
8. Implement Operator profile and physical-site authority.
9. Implement Operator site assignments and active-site context.
10. Implement safe site synchronization to Member references.
11. Implement eligible-shift event intake and manual assignment.
12. Implement Member attendance query contract.
13. Implement arrival recording and Member booking transition.
14. Implement the bounded Operator portal and administration.
15. Implement local/testing synthetic seed data.
16. Add focused tests.
17. Run only the declared verification.
18. Update MVP evidence, roadmap, gap register, and Work Package status.
19. Re-read this task against the final diff.
20. Stop without implementing the next MVP-04 slice or MVP-05.

## Required data model

Use UUID-compatible primary identifiers and accepted repository conventions.

### Operator profiles

Implement an Operator-owned profile linked to one shared User.

Minimum fields:

- ID;
- User ID, unique;
- active status;
- optional display/employee code that is synthetic-safe and does not duplicate
  Member identity;
- timestamps.

Rules:

- credentials and login state remain on `users`;
- role and permissions remain persisted through accepted shared claims;
- disabling an Operator profile denies Operator portal access without deleting
  User, Member, booking, audit, or assignment history;
- a User may also be an administrator or Member;
- no separate password is stored;
- no public registration exists;
- no hard delete after assignment or audit use.

### Operator physical sites

Implement the Operator-owned site master.

Minimum fields:

- ID;
- stable site identifier used by Member references;
- organization identifier/reference;
- code;
- display name;
- address fields only when already defined by authoritative source;
- time zone;
- operational status;
- source/version field when required by synchronization conventions;
- timestamps.

Rules:

- Operator is the only business authority that creates or changes physical-site
  identity and operational status;
- Member receives a local reference/projection through an explicit application
  boundary;
- existing Member site-reference stable IDs must remain valid;
- existing Member schedules and bookings must not be rewritten or deleted;
- disabling a site:
  - prevents new Member availability for that site;
  - leaves existing bookings intact;
  - emits sanitized audit and versioned event evidence;
- reactivation is execution-time authorized and audited;
- no hard delete after reference/use;
- no Member resource may mutate the Operator site master directly.

### Operator site assignments

Implement assignment of an Operator profile to a physical site.

Minimum fields:

- ID;
- Operator profile ID;
- site ID;
- active status;
- assigned-by User ID;
- assigned/revoked timestamps;
- reason when revoked or changed;
- timestamps.

Rules:

- one active assignment per Operator/site pair;
- multiple active sites per Operator are allowed;
- assignment grants eligibility to select a site but does not itself choose the
  active site;
- inactive Operator or inactive site fails closed;
- no caller-supplied role/permission adds assignment;
- revocation is execution-time authorized and audited;
- revocation does not rewrite historical arrival attribution.

### Eligible shift references

Implement an Operator-owned projection/reference created from the Member
`shift_eligible` event.

Minimum fields:

- ID;
- Member schedule ID, unique;
- stable Operator site identifier;
- schedule start/end;
- confirmed count observed at eligibility;
- quota;
- event version;
- source event ID, unique;
- eligible timestamp;
- synchronization status;
- timestamps.

Rules:

- the projection is not the Member schedule authority;
- duplicate event ID returns the original projection;
- duplicate schedule eligibility does not create another active projection;
- changed replay for the same event ID fails as conflict;
- unknown site fails closed and records sanitized failure evidence;
- payload does not contain Member identity;
- later booking count changes do not automatically revoke eligibility;
- this task does not implement sequential invitation or notification.

### Operator shift assignments

Implement manual assignment of one or more Operators to an eligible Member
schedule.

Minimum fields:

- ID;
- eligible-shift reference ID;
- Operator profile ID;
- assigned-by User ID;
- status;
- assigned/revoked timestamps;
- reason;
- timestamps.

Rules:

- assignment requires active Operator, active site assignment, active site, and
  matching eligible shift;
- one active assignment per Operator/eligible-shift pair;
- multiple Operators may be assigned to one eligible shift;
- assignment to another site is rejected;
- assignment is audited;
- revocation preserves historical attribution;
- no Member schedule, quota, booking, or points value is mutated;
- no automatic invitation/timeout/escalation is implemented.

### Operator arrivals

Implement an append-only or correction-safe Operator arrival record.

Minimum fields:

- ID;
- booking ID;
- Member schedule ID;
- Operator site ID;
- Operator profile ID;
- actual occurrence time in UTC;
- recorded time;
- operation/idempotency identity;
- source;
- status;
- optional correction/supersession reference only when current conventions
  require it;
- timestamps.

Rules:

- one effective first arrival per booking;
- duplicate same operation and normalized input returns original result;
- changed replay conflicts;
- booking and schedule are derived from the Member attendance result;
- site and Operator are derived from authenticated context;
- no Member name, NIK, contact, points, or clinical data is duplicated into
  Operator arrival records;
- arrival records are not deleted;
- arrival does not create a ticket, Encounter, consent, or identity decision.

## Authorization and active-site context

Use persisted shared claims and the shared authenticated context.

Preferred exact role:

```text
operator
```

Preferred exact permissions:

```text
operator.portal.access
operator.site.read
operator.site.manage
operator.assignment.read
operator.assignment.manage
operator.attendance.read
operator.arrival.record
operator.audit.read
```

Equivalent bounded names are acceptable only when repository naming conventions
require them.

Rules:

- `/operator` requires active User, login enabled, no mandatory password
  replacement, active Operator profile, exact Operator role, and exact portal
  permission;
- administrator-only User without Operator role/profile cannot access the
  Operator portal;
- a dual-role User may access both authorized surfaces;
- active site must be one persisted active site assignment;
- active site selection is explicit;
- active site is stored server-side;
- route, form, query, session payload, or Livewire values cannot fabricate an
  assignment;
- every Operator application service re-resolves trusted actor, profile,
  claims, and active site at execution time;
- a revoked permission, assignment, profile, or site fails closed immediately;
- site switching is audited;
- site switching clears stale Operator work context;
- current Member/admin authentication remains unchanged.

## Member local application contracts

### Site synchronization

Provide one explicit Operator-to-Member local application command.

Input is server-derived Operator site data.

Required behavior:

- create or update the Member site reference keyed by stable Operator site
  identity;
- preserve Member reference ID used by schedules/bookings where possible;
- update only safe reference fields;
- propagate active/inactive state;
- do not create Member schedules;
- do not mutate bookings;
- idempotent same-version replay;
- changed stale version fails as conflict;
- audit both owning and receiving boundaries as required by current conventions.

### Attendance query

Provide one Member-owned local query.

Input:

- active Operator site identity from trusted context;
- eligible Member schedule ID;
- requested `at` time with explicit offset.

Required behavior:

- normalize `at` to UTC;
- verify the schedule belongs to the active site;
- verify the Operator has an active shift assignment;
- return only confirmed, paid/charged, non-cancelled bookings whose schedule
  contains `at`;
- query is side-effect free;
- return safe operational fields only:
  - booking ID;
  - Member ID only when required for subsequent local commands;
  - Member display name;
  - MRN only when approved for private Operator worklist;
  - masked NIK only when supported safely;
  - service label/code;
  - schedule start/end;
  - booking status;
  - arrival state;
- do not return:
  - full NIK;
  - KK;
  - identity asset contents;
  - email;
  - phone;
  - address;
  - account state;
  - point balance;
  - payment details;
  - password/session/claims;
  - unrestricted audit or ledger metadata;
- another site returns no data or a non-disclosing denial;
- arbitrary site/schedule input cannot elevate access;
- audit the attendance access with bounded metadata.

### Record arrival

Provide one Operator application command that coordinates with one Member-owned
booking-state command.

Input may include only:

- booking ID selected from authorized attendance;
- actual occurrence timestamp with explicit offset;
- idempotency key.

Server derives:

- actor;
- Operator profile;
- active site;
- schedule;
- Member/booking authority;
- recording time;
- source.

Required behavior:

1. require valid Operator context;
2. require active site assignment;
3. re-query/lock the eligible attendance booking;
4. require booking status `confirmed`;
5. require schedule/site match;
6. validate occurrence time against the bounded accepted schedule window;
7. create the Operator arrival record;
8. invoke the Member local command to move booking to `arrived`;
9. write sanitized audit;
10. write versioned event/outbox evidence when current architecture requires it;
11. write idempotency result;
12. commit all local database work atomically where one database transaction
    permits it;
13. return a safe result;
14. roll back on failure.

The Member command:

- remains the only authority that updates booking status;
- accepts trusted Operator actor/site context;
- requires expected source state `confirmed`;
- records actual occurrence and recording time through accepted event/history
  conventions;
- rejects duplicate changed input;
- does not create an Encounter;
- does not mark `checked_in`;
- does not issue a ticket.

## Operator portal

Use Blade/Laravel and existing shared auth conventions.

Preferred bounded routes:

```text
GET  /operator
GET  /operator/site
POST /operator/site
GET  /operator/eligible-shifts
GET  /operator/attendance/{schedule}
POST /operator/arrivals
GET  /operator/verification-worklist
```

Equivalent bounded naming is acceptable.

Requirements:

- no public route;
- authentication through existing shared credential foundation;
- generic login failures remain unchanged;
- portal access fails closed for inactive/revoked context;
- active-site selection lists only active persisted assignments;
- dashboard shows active site and assigned eligible shifts;
- attendance is private and site scoped;
- arrival action requires explicit confirmation;
- verification worklist shows arrived records pending later identity/consent;
- no identity images or full NIK are displayed;
- no ticket number exists yet;
- no queue stage mutation exists yet;
- no clinical form exists yet;
- Member-visible copy remains separate;
- operator UI may use precise professional terminology;
- CSRF and safe validation errors are required.

## Filament administration

Use the existing shared `/admin` panel with Operator-owned resources.

### Operator sites

Provide bounded list, create, view, edit/activate/deactivate.

- use Operator application service;
- exact read/manage permissions;
- execution-time authorization;
- safe fields;
- no hard delete after use;
- sync Member reference through explicit command;
- audit changes;
- no bulk import/export.

### Operator profiles

Provide bounded list, create/link, view, activate/suspend.

- link to existing shared User or create through an approved account service if
  current architecture permits;
- do not store credentials in Operator table;
- no plaintext credential in documentation/evidence;
- no public registration;
- no hard delete after use;
- audit changes;
- no bulk import/export.

### Site assignments

Provide bounded list, assign, revoke.

- select only active Operator profiles and active sites;
- require reason for revocation;
- execution-time authorization;
- no self-granted permission;
- no browser-supplied target retargeting;
- audit changes;
- no bulk mutation.

### Eligible shifts and manual assignments

Provide:

- read-only eligible-shift list from consumed Member events;
- manual assign/revoke actions through Operator application service;
- matching-site enforcement;
- no mutation of Member schedule/quota/bookings;
- no sequential automation;
- no notification controls;
- bounded audit.

### Arrival visibility

Provide read-only list/detail for authorized administrators.

Show only:

- arrival ID;
- booking ID;
- safe Member name/MRN when authorized;
- site;
- schedule;
- Operator;
- occurrence time;
- recording time;
- status;
- bounded relevant audit when separately authorized.

Do not expose protected identifiers, contact information, points, payment,
credentials, claims, or unrestricted metadata.

## Local/testing synthetic data

Add an explicit local/testing-only seeder, preferred:

```text
MvpOperatorSeeder
```

It may create:

- one synthetic Operator-owned site corresponding to an existing synthetic
  Member site reference;
- one synthetic Operator User/profile;
- exact Operator role and bounded permissions;
- one active Operator-site assignment;
- one eligible-shift projection from an existing synthetic Member schedule or a
  correctly shaped synthetic `shift_eligible` event;
- one manual Operator-shift assignment.

Rules:

- refuse outside `local` and `testing`;
- do not run automatically from `DatabaseSeeder`;
- idempotent;
- no real identity/contact data;
- no plaintext credential in output/evidence;
- do not reset existing credentials;
- stop on inconsistent state;
- do not create identity images, consent, ticket, queue, clinical measurements,
  walk-ins, cash, or external records;
- report only safe synthetic IDs and routes.

## Migrations

Create forward migrations following accepted conventions.

Requirements:

- UUID-compatible identifiers;
- foreign keys and unique constraints;
- indexes for:
  - Operator User lookup;
  - active site lookup;
  - active site assignments;
  - active-site context validation;
  - eligible Member schedule lookup;
  - source event idempotency;
  - active Operator-shift assignment;
  - booking arrival lookup;
  - operation/idempotency identity;
- reversible `down()` ordering;
- no destructive rewrite;
- safe forward compatibility with existing Member site references;
- no changes to accepted identity or MVP-03 migrations;
- no production data reset.

If safe site backfill/synchronization cannot be established from existing stable
Operator site identifiers, stop for approval.

## Audit and events

Use accepted append-only audit and versioned outbox/event infrastructure.

Audit at least:

- Operator profile create/activate/suspend;
- Operator site create/update/activate/deactivate;
- Member site-reference synchronization;
- Operator-site assignment/revocation;
- active-site selection/switch;
- eligible-shift event intake/replay/conflict;
- manual shift assignment/revocation;
- attendance access;
- arrival success and controlled failure categories;
- Member booking transition to arrived;
- administrator read of arrival audit when policy requires it.

Do not record:

- full NIK/KK;
- identity assets;
- passwords;
- session IDs;
- raw claims;
- request payloads;
- point balances;
- payment details;
- clinical notes;
- raw exception traces;
- unrestricted metadata.

Preferred versioned event names:

```text
operator.site.changed
operator.shift-assigned
operator.member-arrived
```

Equivalent exact names are acceptable when consistent with current conventions.

Event payloads contain only stable IDs, times, version, site/schedule/booking
references, and sanitized operational facts.

## Focused tests

Do not use Pest browser tests in this task.

Use PHPUnit, Laravel feature tests, direct application-service tests,
Livewire/Filament tests, architecture tests, and security tests.

Preferred bounded files:

```text
tests/Operator/Mvp04OperatorFoundationTest.php
tests/Feature/Operator/Mvp04OperatorPortalTest.php
tests/Feature/Admin/Mvp04OperatorAdministrationTest.php
```

Equivalent organization is acceptable.

### Operator access tests

Prove:

- anonymous access denied;
- Member-only User denied;
- administrator-only User without Operator profile/role denied;
- valid Operator allowed;
- dual-role Operator/admin follows exact claims;
- suspended User denied;
- login-disabled User denied;
- mandatory-password-change User denied;
- inactive Operator profile denied;
- inactive site assignment denied;
- inactive site denied;
- caller claims cannot create role, permission, assignment, or active site;
- a new request scope observes revocation.

### Site authority tests

Prove:

- Operator site is the physical-site authority;
- Member has only a synchronized reference;
- Member resource cannot mutate Operator site;
- create/update syncs safe fields;
- same version replay is idempotent;
- stale/changed replay conflicts;
- deactivation removes new Member availability at that site;
- existing schedules/bookings remain;
- another site's availability is unaffected;
- no hard delete after use;
- audit contains no protected data.

### Active-site tests

Prove:

- Operator may have multiple assignments;
- exactly one active site exists in a session;
- only assigned active sites are selectable;
- caller-supplied site cannot select an unauthorized site;
- switch is audited;
- revoked assignment/site fails at execution time;
- active site changes clear stale Operator context;
- another site's attendance remains inaccessible.

### Eligible-shift intake tests

Prove:

- valid `shift_eligible` event creates one projection;
- duplicate same event returns the existing result;
- changed replay conflicts;
- duplicate schedule does not create duplicate active eligibility;
- unknown/inactive site fails closed;
- payload contains no Member identity;
- later count reduction does not revoke eligibility;
- no invitation, notification, or automatic assignment occurs.

### Manual assignment tests

Prove:

- exact manage permission required;
- active Operator/site assignment required;
- matching site required;
- multiple Operators may be assigned to one shift;
- duplicate active assignment rejected/idempotent;
- permission revoked after mount is rejected at execution;
- assignment revocation requires reason;
- historical attribution remains;
- Member schedule/quota/booking remains unchanged;
- no browser payload retargeting.

### Attendance tests

Prove:

- only active-site assigned Operator may query;
- explicit-offset `at` is required and normalized;
- only confirmed/charged/non-cancelled in-window bookings appear;
- another site/schedule does not appear;
- query has no side effect;
- repeated query is stable;
- safe fields only;
- full NIK, KK, email, phone, address, account state, point balance, payment,
  password, session, claims, raw ledger, and unrestricted audit are absent;
- masked NIK appears only when supported by accepted identifier service;
- access audit is bounded.

### Arrival tests

Prove:

- valid assigned Operator records arrival;
- booking moves `confirmed → arrived` through Member service;
- Operator arrival and Member transition commit together;
- occurrence time with offset is stored in UTC;
- recording time is distinct;
- duplicate same operation returns original;
- changed replay conflicts;
- caller-supplied Operator/site/Member/status cannot retarget;
- wrong site denied;
- unassigned schedule denied;
- inactive Operator/site/assignment denied;
- booking not in confirmed state denied;
- out-of-window occurrence denied;
- no ticket, consent, identity decision, Encounter, queue stage, or clinical
  record is created;
- every failure creates no partial arrival or status change;
- failure audit uses controlled categories and no raw input/exception.

### Portal tests

Prove:

- bounded routes only;
- dashboard shows active site and assigned shifts;
- attendance and arrival are site scoped;
- verification worklist contains arrived/pending-verification entries;
- no full NIK, identity image, consent, ticket number, clinical value, point, or
  payment data is rendered;
- direct access to another site/booking fails non-disclosingly;
- CSRF and validation behavior remain safe.

### Admin tests

Prove:

- exact read/manage permissions are independent;
- Operator site/profile/assignment mutations use application services;
- read-only administrators see no mutation actions;
- execution-time revocation fails;
- eligible shifts are read-only except Operator assignment actions;
- arrivals are read-only;
- no delete, bulk mutation, import, or export;
- no Member schedule/booking/points mutation action;
- no Image Gateway or Doctor resource added.

### Seeder tests

Prove:

- refuses outside local/testing;
- idempotent;
- no duplicate site/profile/assignment/eligibility;
- no credential reset or credential output;
- no real/protected identity data;
- stops on inconsistent state;
- not called by `DatabaseSeeder`;
- creates no ticket, consent, clinical, walk-in, cash, or external data.

## Targeted regression boundary

Run only focused existing tests affected by changed files.

At minimum:

- accepted MVP-01 Member access/profile tests;
- accepted MVP-02 admin/Member administration tests;
- accepted MVP-03 domain, Member, and Filament tests excluding
  `tests/Browser/**`;
- filtered WP-02 authorization, audit, transaction, outbox, idempotency, and
  protected-data tests;
- filtered WP-04 User/Member identity/account-state tests;
- affected architecture/foundation tests.

Do not run Pest browser tests.

Do not run full Work Package suites unless focused filtering cannot safely
select the affected behavior.

## Documentation updates

Update only bounded MVP documentation:

```text
docs/mvp/roadmap.md
docs/mvp/beta-gap-register.md
docs/mvp/work-package-status.md
docs/mvp/decision-log.md
docs/mvp/evidence/mvp-04-operator-foundation-arrival.md
```

Requirements:

### Roadmap

- mark MVP-04 as started, not complete;
- record this as Operator foundation and arrival slice;
- preserve later MVP numbering;
- state that Pest/browser platform work is deferred to post-MVP hardening.

### Gap register

- keep `MVP-GAP-009` open until the complete bounded Operator MVP passes;
- keep queue/attendance gap open for remaining check-in/ticket/queue behavior;
- do not close public LCD, identity verification, consent, walk-in, clinical,
  Image Gateway, privacy, CI, deployment, or release gaps;
- record only a new stable gap when a genuinely new limitation is discovered.

### Work Package status

- WP-11 may become `partially-implemented` for Operator access, sites, active
  site context, eligibility intake, and manual assignment;
- WP-12 may become `partially-implemented` only for physical arrival and
  pending-verification worklist;
- WP-17 may become `partially-implemented` only for bounded Operator-owned
  administration and local contracts;
- WP-07 remains not started except any exact Member attendance/arrival contract
  portion actually implemented and tested;
- do not mark any package complete.

### Decision log

Record the owner decision:

```text
Pest/Playwright browser-platform optimization and expanded browser coverage are
deferred until all planned MVP implementation slices are complete. Product MVP
tasks use PHPUnit, feature, service, Livewire, security, and architecture tests;
existing browser tests are not modified or executed unless a later explicit
task requires them.
```

### Evidence

Record:

- baseline and execution commit;
- exact requirement subset;
- ownership and local-contract boundaries;
- schema;
- routes/admin surface;
- authorization and active-site behavior;
- event intake;
- assignment;
- attendance;
- arrival transaction;
- exact focused commands/results;
- targeted regressions;
- formatting/static/migration checks;
- tests not run;
- explicit statement that Pest/browser tests were not run;
- SQLite/MySQL limitations;
- open MVP-04 scope;
- no production-readiness claim.

Do not include credentials or protected identifiers.

## Verification

- Method: Validate the task; inspect the final diff; run focused Operator
  domain/service, portal, and Filament tests; run directly affected MVP-01,
  MVP-02, and non-browser MVP-03 tests; run filtered WP-02/WP-04/architecture
  regressions; run bounded Pint on changed PHP files; run PHP syntax checks;
  run `git diff --check`; inspect routes, migrations, providers, permissions,
  audit metadata, outbox payloads, and static module ownership.
- Expected result: A persisted assigned Operator can use shared authentication,
  select one authorized active site, view only that site's eligible attendance,
  and atomically record a Member arrival that moves the Member-owned booking to
  `arrived`; Operator physical-site authority, eligibility intake, manual
  assignment, and bounded administration remain secure; and no deferred
  check-in, ticket, queue, clinical, browser-platform, Image Gateway, or
  production scope is added.

Required:

```bash
git diff --check
```

Run bounded Pint only on changed PHP files.

Inspect `/operator` and `/admin` routes.

Run migration status and rollback checks only against the normal fast testing
database.

Do not run:

- Pest browser tests;
- Playwright;
- full PHPUnit;
- complete WP suites;
- MySQL/Docker conformance;
- npm build;
- Composer audit;
- external integrations;
- deployment;
- production checks.

## Acceptance criteria

- [ ] Baseline ancestry and repository state are confirmed.
- [ ] Published task validation passes.
- [ ] Existing work is preserved.
- [ ] Requirement assignments and source digests remain unchanged.
- [ ] No Composer/npm dependency is added or upgraded.
- [ ] Existing Pest/browser files remain unchanged.
- [ ] No Pest/Playwright/browser test is run.
- [ ] Shared User/authentication remains authoritative.
- [ ] Operator profile does not duplicate credentials.
- [ ] Exact Operator role and permissions are persisted.
- [ ] Administrator-only and Member-only Users cannot enter Operator portal.
- [ ] Valid dual-role behavior follows persisted claims and assignments.
- [ ] Operator physical-site master is authoritative.
- [ ] Member receives only a synchronized site reference.
- [ ] Existing Member schedules/bookings remain valid through site synchronization.
- [ ] Site deactivation prevents new availability without deleting bookings.
- [ ] Operator may have multiple site assignments.
- [ ] Session has exactly one active site.
- [ ] Caller input cannot fabricate active site or assignment.
- [ ] Revoked profile, permission, assignment, or site fails at execution time.
- [ ] `shift_eligible` intake is versioned and idempotent.
- [ ] Changed event replay conflicts.
- [ ] Unknown site eligibility fails closed.
- [ ] Manual shift assignment requires exact authority and matching site.
- [ ] Multiple Operators may be assigned to one eligible shift.
- [ ] Assignment does not mutate Member schedule, quota, booking, or points.
- [ ] Attendance query is Member-owned and side-effect free.
- [ ] Attendance is restricted to active site and assigned eligible schedule.
- [ ] Attendance returns only safe operational fields.
- [ ] Full NIK, KK, contact, account, point, payment, credential, claim, ledger, and unrestricted audit data are absent.
- [ ] Arrival requires explicit-offset occurrence time and stores UTC.
- [ ] Valid arrival moves booking from confirmed to arrived through Member authority.
- [ ] Operator arrival and Member transition commit atomically.
- [ ] Duplicate same arrival is idempotent.
- [ ] Changed replay conflicts.
- [ ] Wrong site, assignment, state, or window fails closed.
- [ ] Failed arrival creates no partial state.
- [ ] No consent, identity decision, ticket, Encounter, staged queue, clinical record, walk-in, cash, or Image Gateway record is created.
- [ ] Operator portal exposes only the declared bounded surface.
- [ ] Operator-owned Filament administration uses application services.
- [ ] Arrivals and eligible shifts remain bounded/read-only except exact assignment actions.
- [ ] No delete, bulk mutation, import, or export is added.
- [ ] Local/testing seeder is synthetic, idempotent, and credential-safe.
- [ ] Focused MVP-04 tests pass.
- [ ] Affected MVP-01, MVP-02, and non-browser MVP-03 regressions pass.
- [ ] Filtered WP-02/WP-04/architecture regressions pass.
- [ ] Bounded Pint and PHP syntax checks pass.
- [ ] `git diff --check` passes.
- [ ] Route, migration, provider, permission, audit, event, and ownership inspection passes.
- [ ] Evidence records only observed results.
- [ ] `MVP-GAP-009` and remaining queue/attendance gaps remain open.
- [ ] WP-11, WP-12, and WP-17 are at most partially implemented.
- [ ] No MVP-05, browser-platform, external-adapter, deployment, commit, push, or production work is added.

## Stop conditions

Stop as `awaiting-approval` when:

- baseline ancestry is absent;
- overlapping work affects required files;
- requirement assignments changed;
- shared login cannot safely support non-Member Operators;
- safe site-authority migration/synchronization cannot preserve existing
  Member records;
- current `shift_eligible` contract is incompatible;
- arrival cannot be separated from identity/consent/ticket/Encounter;
- a dependency, undeclared route, external adapter, CI workflow, or production
  policy change is required;
- focused tests reveal a broader accepted regression;
- destructive or production-affecting work is required.

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
- `blocked`: required tooling or evidence is unavailable.
- `awaiting-approval`: an approval gate or stop condition is reached.
- `exhausted`: iteration limit is reached before completion.

## Final report

Report:

- baseline and execution commit;
- selected runtime/model when verifiable;
- capabilities;
- requirement subset consumed;
- files changed;
- Operator profile/auth behavior;
- physical-site authority and Member synchronization;
- active-site behavior;
- eligibility intake;
- manual assignment;
- attendance contract;
- arrival transaction;
- portal/admin surface;
- authorization, audit, event, and idempotency behavior;
- seeder behavior;
- focused tests and results;
- targeted regressions and results;
- formatting, syntax, diff, route, migration, provider, and static checks;
- documentation and gap status;
- tests not run;
- explicit confirmation that Pest/browser tests were not run;
- remaining MVP-04 and Work Package scope;
- confirmation that no dependency, browser-platform, MVP-05, external adapter,
  deployment, commit, push, or production work was added.

Do not include credentials or protected identifiers.

Do not commit or push.

Stop after this first bounded MVP-04 slice.
