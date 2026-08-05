---
name: mhcs-core-mvp-03-booking-ownership-schedule-integrity
description: Close MVP-03 booking ownership, booked-schedule integrity, point-comparison, audit-boundary, Filament management, and focused-evidence gaps without expanding product scope.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-03 Remediation — Booking Ownership and Schedule Integrity

## Objective

Remediate commit:

`a1360f4307d7d339779a48fd519755b360f52052`

Fix only these findings:

1. `Mvp03BookingService::create()` accepts caller-supplied Member authority and
   does not bind the booking to the trusted authenticated actor.
2. A schedule with bookings may still change start, end, and quota, allowing a
   generic admin edit to silently alter confirmed appointments or reduce quota
   below current occupancy.
3. `PointAmount::compare()` mishandles two negative values of equal digit length
   and uses PHP numeric-string comparison for equal-length magnitudes, which is
   unsafe beyond native integer precision.
4. Booking audit is permission-gated but not restricted to an exact MVP-03
   action and target-type set.
5. Failed booking attempts do not produce the required sanitized failure audit
   categories.
6. Filament create/edit routes exist, but focused evidence does not establish a
   usable create/edit action path or execution-time permission revocation.

Required outcomes:

```text
authenticated Member
→ trusted context resolves the Member
→ no caller-controlled Member authority
→ atomic personal-points B2C booking remains intact
```

```text
schedule has any booking
→ site/service/start/end/quota frozen
→ closing may stop new bookings
→ confirmed appointment is not silently rewritten
```

This is a narrow MVP-03 remediation. Do not implement MVP-04.

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

Use `a1360f4307d7d339779a48fd519755b360f52052` as the remediation baseline.

Before editing:

1. Resolve `$TARGET` canonically.
2. Confirm the expected `Madeena-software/mhcs-core` repository.
3. Confirm baseline ancestry.
4. Record branch, commit, staged, modified, untracked, and relevant ignored
   files.
5. Preserve all pre-existing work.
6. Stop as `awaiting-approval` if current work overlaps required files.
7. Do not reset, clean, discard, stash, stage, commit, push, deploy, or access
   production.

Read completely:

- `AGENTS.md`;
- `.agents/AGENTS.md`;
- `.agents/skills/agent-task/SKILL.md`;
- `.agents/skills/develop-feature/SKILL.md`;
- `.agents/context/project.md`;
- `.agents/context/modules/member/project.md`;
- `.agents/context/modules/operator/project.md`;
- `.agents/tasks/mhcs-core-mvp-03-controlled-b2c-radiology-booking-v1.md`;
- `docs/mvp/roadmap.md`;
- `docs/mvp/beta-gap-register.md`;
- `docs/mvp/work-package-status.md`;
- `docs/mvp/evidence/mvp-03-controlled-b2c-radiology-booking.md`;
- `app/Http/Controllers/Member/Mvp03BookingController.php`;
- `app/Modules/Member/Application/Services/MemberContextResolver.php`;
- `app/Modules/Member/Application/Services/Mvp03BookingService.php`;
- `app/Modules/Member/Application/Services/Mvp03ScheduleService.php`;
- `app/Modules/Member/Domain/PointAmount.php`;
- `app/Modules/Member/Filament/Resources/Bookings/Pages/ViewBooking.php`;
- `app/Modules/Member/Filament/Resources/ServiceOfferings/**`;
- `app/Modules/Member/Filament/Resources/ShiftSchedules/**`;
- `resources/views/member/booking/**`;
- all focused MVP-03 tests;
- accepted context, audit, authorization, idempotency, outbox, and account-state
  implementation; and
- installed Filament 5 action/page/form/Livewire testing APIs in `vendor/**`.

Use repository and installed-package evidence. Do not infer Filament behavior
from memory.

## Scope and constraints

- Prefer no migration. Enforce booked-schedule immutability in the existing
  service unless a focused test proves a forward migration is unavoidable.
- Preserve B2C-only, personal-points-only, immediate confirmed booking, one
  local order, one-active-booking, capacity locking, and fifth-booking
  eligibility behavior.
- Preserve existing routes and read-only booking/site administration.
- Continue using trusted authenticated context, `MemberContextResolver`, audit,
  outbox, idempotency, and the existing application services.
- Do not add public registration, child booking, B2B, real payments/top-ups,
  cancellation, rescheduling, refunds, no-show, walk-in, Operator workflow,
  Image Gateway ingestion, FHIR, notifications, dependencies, or network
  boundaries.
- Do not modify `.agents/context/**`, `docs/implementation/**`, or published
  tasks.
- Do not run full suites, complete WP suites, MySQL/Docker conformance, npm
  build, Composer audit, deployment, external integrations, or production
  checks.
- Do not commit or push.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `4`
- Approval gates:
  - stop as `awaiting-approval` if baseline ancestry is absent;
  - stop as `awaiting-approval` for overlapping work;
  - stop as `awaiting-approval` if trusted actor-to-Member resolution requires
    changing accepted authentication architecture;
  - stop as `awaiting-approval` if booked-schedule integrity requires a
    destructive migration;
  - stop as `awaiting-approval` if Filament cannot expose bounded create/edit
    actions without bypassing application services;
  - stop as `awaiting-approval` if a route, migration, dependency, external
    adapter, production policy, or broader workflow is required;
  - stop as `awaiting-approval` if focused checks expose a broader accepted
    regression;
  - stop as `awaiting-approval` before destructive or production-affecting work.

## Execution procedure

1. Validate this task.
2. Confirm baseline and repository state.
3. Inspect trusted context and Member-resolution behavior.
4. Inspect installed Filament 5 action/testing APIs.
5. Bind booking creation to the trusted authenticated Member.
6. Enforce booked-schedule immutability and quota integrity.
7. Correct `PointAmount::compare()`.
8. Bound booking audit actions and target types.
9. Add sanitized booking-failure audit categories.
10. Add usable Filament create/edit actions through application services.
11. Add focused tests.
12. Run only declared verification.
13. Correct MVP evidence and gap status.
14. Stop without continuing to MVP-04.

## Required remediation

### 1. Authenticated Member binding

The authoritative booking command must not accept Member ID as authority.

Preferred contract:

```php
createForCurrentMember(
    string $scheduleId,
    ?string $idempotencyKey = null,
    ?string $pointCostAssertion = null,
): array
```

Requirements:

- resolve actor from trusted context;
- require a real authenticated User;
- resolve exactly one Member through `MemberContextResolver`;
- retain adult, identity, profile, account, login-enabled, and mandatory-change
  gates;
- remove Member ID from the controller-to-service call;
- derive idempotency Member identity only after trusted resolution;
- ignore or reject request/route/form/session/Livewire Member and User IDs;
- preserve owner-scoped list/detail and generic Member-facing errors;
- preserve atomic booking, charge, order, audit, outbox, and idempotency.

Tests must prove:

- Member A cannot create for Member B;
- `member_id` and `user_id` request values cannot retarget;
- anonymous, administrator-only, missing-Member, suspended, login-disabled,
  mandatory-change, child, identity-incomplete, and profile-incomplete actors
  fail closed;
- the valid current Member succeeds;
- same replay returns the original result and changed replay conflicts.

### 2. Booked-schedule integrity

When any booking references a schedule, generic schedule editing must not change:

- examination site;
- service offering;
- start;
- end;
- quota.

Only closing availability or a no-op save may remain allowed.

Requirements:

- reload and lock the schedule;
- count all bookings when deciding whether appointment data is frozen;
- compare normalized UTC values;
- reject time/quota/site/service changes after any booking;
- closing does not alter booking, charge, order, or displayed appointment time;
- before unbooked quota updates, reject quota below capacity-consuming count;
- preserve 5..20 quota, explicit-offset input, UTC storage, and overlap rules;
- keep rescheduling/cancellation outside MVP-03;
- rejected edits create no successful update audit.

Tests must cover every frozen field, closing, no-op save, quota integrity, and an
unbooked valid edit.

### 3. Correct point comparison

Fix `PointAmount::compare()` so:

- signs are handled before magnitude comparison;
- absolute strings never contain `-`;
- equal-length magnitude comparison is lexicographic, not numeric conversion;
- values beyond native integer precision compare correctly;
- negative zero remains zero;
- no float/native-integer arithmetic is introduced.

Required cases:

```text
-1.0000 > -2.0000
-2.0000 < -1.0000
-1.0001 < -1.0000
0.0000 == -0.0000
9999999999999999.9999 > 9999999999999999.9998
9999999999999999.9998 < 9999999999999999.9999
```

Retain existing arithmetic and booking-balance tests.

### 4. Bound booking audit

The booking audit table must require:

- exact `member.booking.audit.read`;
- `source = member`;
- exact approved MVP-03 actions;
- exact expected target types and booking association;
- safe selected columns only.

Preferred action set:

```text
member.booking.confirmed
member.booking.failed
member.point-charge
member.imaging-order.create
```

Do not include arbitrary future Member events merely because their target or
metadata contains the booking ID. Metadata remains unselected. Permission
failure returns an empty query or 403 at the server-side query boundary.

Add tests with unrelated events sharing the booking target/metadata and a
sensitive marker in `reason`; prove the marker is absent from HTML and Livewire
serialization.

### 5. Sanitized booking-failure audit

Record `member.booking.failed` after failed booking state has rolled back.

Allowed controlled categories include:

```text
member_unavailable
member_ineligible
active_booking_exists
schedule_unavailable
capacity_full
price_changed
rate_unavailable
insufficient_personal_points
idempotency_conflict
unexpected_failure
```

Requirements:

- use trusted actor and resolved Member when safely available;
- persist no partial booking, charge, order, success audit, outbox, or handled
  idempotency result;
- store only the controlled category;
- do not store raw exception text, trace, credentials, protected identity,
  balance, claims, or request payload;
- avoid duplicate failure audit for one idempotent attempt when possible.

Test insufficient balance, full capacity, stale price, active booking, changed
replay, and a controlled unexpected failure.

### 6. Usable Filament management

Using installed Filament 5 APIs:

- add authorized create/edit actions for service offerings;
- add authorized create/edit actions for schedules;
- keep site references and bookings read-only;
- keep delete, bulk mutation, import, and export absent;
- route every mutation through `Mvp03OfferingService` or
  `Mvp03ScheduleService`;
- re-authorize in the application service at execution time.

Livewire tests must prove:

- read-only administrators see no mutation actions;
- manage administrators can execute create/edit actions;
- permission revoked after mounting an eligible action is rejected at execution;
- booked-schedule rules are enforced through Filament;
- no direct model-save bypass exists;
- no site/booking mutation appears.

## Documentation

Update only:

```text
docs/mvp/evidence/mvp-03-controlled-b2c-radiology-booking.md
docs/mvp/beta-gap-register.md
docs/mvp/roadmap.md
docs/mvp/work-package-status.md
```

Record baseline, execution commit, trusted Member binding, schedule integrity,
point correction, audit scope, failure categories, Filament evidence, exact
commands/results, changed files, unrun checks, and remaining gaps.

Treat `MVP-GAP-011` as unaccepted during remediation. Close it again only after
all corrected checks pass.

## Verification

- Method: Validate the task; inspect the diff; run focused MVP-03 domain,
  Member-route, and Filament tests; run directly affected MVP-01/MVP-02 and
  filtered WP-02/WP-04/architecture regressions; run bounded Pint; run
  `git diff --check`; inspect routes, resources, audit queries, outbox payloads,
  and failure metadata.
- Expected result: Booking derives Member only from trusted context; booked
  schedules cannot be silently rewritten; point comparisons are correct;
  booking audit is exact; failures are sanitized and auditable; Filament
  management is usable and re-authorized; no broader scope is added.

Required:

```bash
git diff --check
```

Do not run full suites, MySQL/Docker, npm build, Composer audit, integrations,
deployment, or production checks.

## Acceptance criteria

- [ ] Baseline ancestry and repository state are confirmed.
- [ ] Published task validation passes.
- [ ] Existing work is preserved.
- [ ] Booking creation no longer accepts caller-controlled Member authority.
- [ ] Trusted context resolves the booked Member.
- [ ] Member A cannot book for Member B.
- [ ] Anonymous and administrator-only actors cannot create Member bookings.
- [ ] Existing eligibility/account/profile gates remain enforced.
- [ ] Idempotent replay and changed-payload conflict remain correct.
- [ ] Booked schedules cannot change site, service, start, end, or quota.
- [ ] Closing a booked schedule preserves the appointment.
- [ ] Quota cannot fall below capacity-consuming bookings.
- [ ] Overlap and quota-range rules remain correct.
- [ ] Negative point comparisons are correct.
- [ ] Large point comparisons avoid native numeric precision loss.
- [ ] Booking audit uses exact approved actions and target types.
- [ ] Unrelated/sensitive audit reasons are absent.
- [ ] Permission denial fails closed at the audit query boundary.
- [ ] Booking failures produce sanitized controlled categories.
- [ ] Failed attempts produce no partial success state.
- [ ] Raw exceptions, payloads, claims, credentials, balances, and protected values are absent from audit.
- [ ] Offering create/edit actions are usable only with manage permission.
- [ ] Schedule create/edit actions are usable only with manage permission.
- [ ] Revoked permission is rejected at execution time.
- [ ] Filament mutations use application services.
- [ ] Site references and bookings remain read-only.
- [ ] No delete, bulk mutation, import, or export is added.
- [ ] Existing successful booking and threshold behavior remains intact.
- [ ] Focused MVP-03 tests pass.
- [ ] Affected MVP-01/MVP-02 regressions pass.
- [ ] Filtered WP-02/WP-04/architecture regressions pass where applicable.
- [ ] Bounded Pint and `git diff --check` pass.
- [ ] Route/resource inspection shows no undeclared expansion.
- [ ] Evidence records only observed results.
- [ ] `MVP-GAP-011` closes only after corrected acceptance passes.
- [ ] WP-05, WP-06, and WP-10 remain partial.
- [ ] No payment, B2B, cancellation/refund, Operator, Image Gateway, FHIR, dependency, deployment, commit, or push is added.

## Stop conditions

Stop as `awaiting-approval` if baseline ancestry is absent, work overlaps,
trusted Member resolution requires auth redesign, schedule integrity requires a
destructive migration, Filament cannot provide bounded actions without service
bypass, sensitive failure data would be required, broader scope is required, a
broader accepted regression appears, or destructive/production work is needed.

## Output

- `succeeded`: all criteria and focused verification pass.
- `failed`: execution occurred but a criterion failed.
- `blocked`: required tooling or evidence is unavailable.
- `awaiting-approval`: a stop condition is reached.
- `exhausted`: iteration limit reached.

## Final report

Report baseline/execution commit, files changed, trusted Member binding,
schedule integrity, point correction, audit scope, failure categories, Filament
behavior, focused tests, targeted regressions, formatting/diff/routes/static
checks, documentation status, unrun checks, remaining gaps, and confirmation
that no broader feature, dependency, deployment, commit, or push was added.

Do not include credentials or protected identifiers.

Do not commit or push.

Stop after this bounded MVP-03 remediation.
