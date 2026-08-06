---
name: mhcs-core-mvp-03-admin-audit-browser-closure
description: Close remaining MVP-03 admin, audit, actor-state, schedule-hydration, and real-browser evidence gaps with a bounded Pest 4 browser-testing layer.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-03 — Admin, Audit, and Browser Evidence Closure

## Objective

Close the remaining bounded acceptance gaps after commit:

`5dee2a1db3595d321c5a4a339d2d6f387111fc64`

The existing remediation correctly introduced trusted Member resolution,
booked-schedule field freezing, arbitrary-precision point comparison,
controlled booking-failure categories, exact success-event audit filters, and
application-service-backed Filament create/edit pages.

Fix only these remaining gaps:

1. Booking creation rejects every actor carrying `administrator`, rather than
   only an administrator-only actor with no eligible Member.
2. Offering and schedule routes exist, but list resources expose no
   discoverable create/edit actions.
3. Schedule edit tests replace timestamps with explicit-offset strings and do
   not prove ordinary naturally hydrated edit/close behavior.
4. Production booking failures are Member-targeted, while the Booking detail
   audit expects Booking-targeted failure events that production never emits.
5. Direct service tests are missing for the required negative actor states.
6. No real-browser test proves that the Filament and Member flows are actually
   discoverable and usable through rendered HTML, JavaScript, sessions, and
   browser navigation.

Required outcomes:

```text
eligible authenticated Member
→ books only for self
→ unrelated administrator claims do not grant or remove ownership
```

```text
authorized Member administrator
→ sees usable create/edit actions
→ mutations execute through Member application services
```

```text
successful Booking detail
→ exact associated success audit only
failed attempt
→ sanitized Member-scoped audit
→ never attached to an unrelated successful Booking
```

Add one bounded real-browser layer using Pest 4 Browser Testing, which is
Playwright-based. Do not add Laravel Dusk or a separate direct Playwright
Test suite in this task.

Do not implement MVP-04 or broaden MVP-03.

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

Use `5dee2a1db3595d321c5a4a339d2d6f387111fc64` as the closure baseline.

Before editing:

1. Resolve `$TARGET` canonically.
2. Confirm `Madeena-software/mhcs-core`.
3. Confirm baseline ancestry.
4. Record branch, commit, staged, modified, untracked, and relevant ignored
   paths.
5. Preserve all existing work.
6. Stop as `awaiting-approval` if work overlaps required files.
7. Do not reset, clean, discard, stash, stage, commit, push, deploy, or access
   production.

Read completely:

- `AGENTS.md`;
- `.agents/AGENTS.md`;
- `.agents/skills/agent-task/SKILL.md`;
- `.agents/skills/develop-feature/SKILL.md`;
- `.agents/context/project.md`;
- `.agents/context/modules/member/project.md`;
- `.agents/tasks/mhcs-core-mvp-03-controlled-b2c-radiology-booking-v1.md`;
- `.agents/tasks/mhcs-core-mvp-03-booking-ownership-schedule-integrity-v1.md`;
- `docs/mvp/evidence/mvp-03-controlled-b2c-radiology-booking.md`;
- `docs/mvp/beta-gap-register.md`;
- `app/Modules/Member/Application/Services/Mvp03BookingService.php`;
- `app/Modules/Member/Application/Services/Mvp03ScheduleService.php`;
- `app/Modules/Member/Application/Services/MemberContextResolver.php`;
- `app/Modules/Member/Filament/Resources/ServiceOfferings/**`;
- `app/Modules/Member/Filament/Resources/ShiftSchedules/**`;
- `app/Modules/Member/Filament/Resources/Bookings/Pages/ViewBooking.php`;
- focused MVP-03 tests;
- `$TARGET/composer.json` and `$TARGET/composer.lock`;
- `$TARGET/package.json` and the active npm lock file;
- `$TARGET/phpunit.xml`;
- `$TARGET/.gitignore`;
- existing CI workflow files under `$TARGET/.github/workflows/**`, when present;
- installed Filament 5 action, page, form-state, and Livewire APIs under
  `vendor/**`; and
- official Pest 4 Browser Testing and Playwright installation/runtime
  requirements available to the execution environment.

Use repository and installed-package evidence. Do not infer Filament defaults.

## Scope and constraints

- Prefer tests, resource actions, and narrowly required corrections.
- Preserve trusted ownership, B2C personal points, atomic booking,
  idempotency, booked-schedule immutability, point arithmetic, current routes,
  schema, read-only site/booking administration, and accepted MVP-01/MVP-02.
- Do not add routes, migrations, runtime dependencies, generic editors,
  payments, cancellation/refunds, B2B, Operator, Image Gateway, FHIR,
  notifications, or production behavior.
- The only permitted dependency changes are development-only testing
  dependencies required for Pest 4 Browser Testing:
  - `pestphp/pest:^4`;
  - `pestphp/pest-plugin-laravel:^4`;
  - `pestphp/pest-plugin-browser:^4`;
  - the Playwright npm package required by the Pest browser plugin; and
  - their lock-file transitive dependencies.
- Do not upgrade to Pest 5 or PHPUnit 13 in this task.
- Do not add `laravel/dusk` or a second `@playwright/test` suite.
- Preserve existing PHPUnit test classes; do not mass-convert them to Pest.
- Do not modify `.agents/context/**`, `docs/implementation/**`, or published
  tasks.
- Do not run full PHPUnit, complete WP suites, MySQL/Docker, npm build,
  Composer audit, integrations, deployment, or production checks.
- Browser tests must use an isolated testing environment and synthetic data.
- Do not commit screenshots, traces, videos, browser profiles, or plaintext
  credentials.
- Do not commit or push.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates:
  - stop as `awaiting-approval` if ancestry is absent or work overlaps;
  - stop as `awaiting-approval` if authoritative policy explicitly prohibits a
    User from being both an eligible Member and administrator;
  - stop as `awaiting-approval` if discoverable actions require direct model
    mutation or route expansion;
  - stop as `awaiting-approval` if safe timestamp hydration requires redesign;
  - stop as `awaiting-approval` if failure association requires a migration or
    generic audit explorer;
  - stop as `awaiting-approval` if Composer cannot resolve Pest 4 while
    retaining the repository's PHP 8.4 and PHPUnit 12 compatibility;
  - stop as `awaiting-approval` if browser installation requires a system,
    Docker, production, or privileged environment change;
  - stop as `awaiting-approval` if a broader accepted regression appears;
  - stop before destructive or production-affecting work.

## Execution procedure

1. Validate this task.
2. Confirm baseline and repository state.
3. Inspect authoritative shared-user and dual-role policy.
4. Inspect installed Filament action and form hydration behavior.
5. Correct administrator-only versus eligible dual-role Member behavior.
6. Add discoverable offering and schedule create/edit actions.
7. Prove and correct naturally hydrated schedule edits.
8. Align Booking detail audit with production event association.
9. Add missing direct actor-state tests.
10. Install and configure the bounded Pest 4 browser-testing foundation.
11. Add focused real-browser smoke tests for the declared Member and
    administrator journeys.
12. Run only declared verification.
13. Correct evidence and gap status.
14. Stop without continuing to MVP-04.

## Required closure work

### 1. Administrator-only denial without blanket role denial

Trusted actor-to-Member ownership remains authoritative.

- Require Auth User and trusted actor equality.
- Resolve exactly one Member through `MemberContextResolver`.
- Retain active account, login-enabled, mandatory-change, adult, identity, and
  profile gates.
- Administrator-only User with no Member remains denied.
- An eligible Member carrying administrator claims must follow authoritative
  repository policy; do not deny solely from role presence unless that policy
  explicitly requires it.
- No administrator permission grants ownership of another Member.
- Caller-supplied Member/User IDs remain non-authoritative.

Tests:

- administrator-only User with no Member denied;
- ordinary Member succeeds;
- eligible dual-role Member behavior follows authoritative policy;
- dual-role or ordinary Member cannot target another Member.

Stop for approval if policy is ambiguous or explicitly forbids dual-role.

### 2. Discoverable Filament actions

Using installed Filament 5 APIs:

- Offering list exposes authorized `CreateAction`.
- Offering table or view exposes authorized `EditAction`.
- Schedule list exposes authorized `CreateAction`.
- Schedule table or view exposes authorized `EditAction`.
- Read-only administrators see no mutation actions.
- Manage administrators see and execute actions.
- Every mutation remains routed through `Mvp03OfferingService` or
  `Mvp03ScheduleService`.
- Permission revocation after mounting an eligible action fails at execution.
- Site references and Bookings remain read-only.
- No delete, bulk, import, or export is added.

Do not treat manually entering `/create` or `/{record}/edit` as a discoverable
administrator action.

### 3. Naturally hydrated schedule editing

Use real Filament edit-page state without replacing all fields.

Tests must prove:

- mount unbooked schedule, change only quota, save;
- mount booked open schedule, change only status to closed, save;
- naturally hydrated start/end values are safely accepted/normalized;
- unchanged UTC timestamps remain unchanged;
- closing preserves the Member appointment;
- booked frozen fields remain protected;
- explicit-offset creation remains required.

Use an installed Filament-supported state formatter or field configuration. Do
not weaken explicit-offset validation for newly supplied times.

### 4. Production-coherent audit association

Production failures have no committed Booking ID and are Member-scoped.

Keep:

```text
member.booking.failed
→ Member target
→ controlled category only
```

For individual Booking detail audit:

- include only exact successful associated events:
  - `member.booking.confirmed`;
  - `member.point-charge`;
  - `member.imaging-order.create`;
- require `source = member` and `outcome = success`;
- require exact target type and association;
- keep safe selected columns;
- permission failure returns empty/403;
- exclude `member.booking.failed` unless production has a genuine committed
  Booking association without inventing a rolled-back ID.

Tests must invoke real failures, assert Member target and controlled reason,
assert no sensitive content, and prove those failures do not appear in a
successful Booking detail. Remove tests that fabricate a Booking-targeted
failure event shape not produced by the service.

Do not add a generic audit/failure explorer.

### 5. Direct actor-state evidence

Add direct `Mvp03BookingService` tests for:

- anonymous actor;
- administrator-only actor;
- authenticated User with no Member;
- suspended User;
- login-disabled User;
- mandatory-password-change User;
- child/dependent Member;
- identity-incomplete Member;
- profile-incomplete Member;
- valid ordinary Member;
- valid dual-role Member, subject to authoritative policy.

For every denial assert:

- no Booking;
- no point charge;
- no local imaging order;
- no success audit;
- no outbox success;
- no handled idempotency result;
- only controlled sanitized failure evidence when trusted Member context is
  safely available.

Do not rely only on HTTP middleware tests.


### 6. Pest 4 real-browser evidence

Add one browser-testing layer using Pest 4 and
`pestphp/pest-plugin-browser`. The plugin is Playwright-based.

Dependency and runner rules:

- retain PHP `^8.4`;
- retain PHPUnit 12 compatibility;
- do not upgrade to Pest 5 or PHPUnit 13;
- follow the official Pest 4 installation contract;
- keep existing PHPUnit test classes unchanged;
- prove that the existing PHPUnit suite subset still runs after Pest
  installation;
- add only the browser plugin's required Playwright npm dependency;
- install only the Chromium browser for this focused local execution unless the
  environment already supports the other engines without additional system
  changes;
- add generated screenshot/trace/video locations to `.gitignore`;
- do not commit generated browser artifacts;
- do not create testing-only authentication routes or production service
  providers.

Preferred browser test file:

```text
tests/Browser/Mvp03AdminBookingClosureTest.php
```

Equivalent bounded organization is acceptable.

Real-browser tests must use visible UI interaction rather than navigating
directly to hidden endpoints as their sole proof.

Required browser scenarios:

#### Administrator catalogue and schedule journey

- open `/admin/login`;
- authenticate with a synthetic testing-only administrator;
- navigate from the visible Filament navigation to service offerings;
- assert the offering Create action is visible with manage permission;
- create one offering through the visible action and rendered form;
- return to the list and open the visible Edit action;
- update the offering through the rendered form;
- navigate to schedules;
- create one schedule through the visible action;
- edit only quota on an unbooked schedule without manually replacing hydrated
  start/end fields;
- create or use a booked schedule and close it by changing only status;
- verify unchanged appointment timestamps;
- verify there are no visible site or Booking mutation actions;
- assert no JavaScript errors or unexpected console errors.

#### Read-only administrator journey

- authenticate with read permissions but no manage permissions;
- navigate to offerings and schedules;
- assert Create and Edit actions are absent;
- confirm direct mutation URLs fail closed or redirect safely;
- confirm site references and Bookings remain read-only.

#### Member booking smoke journey

- authenticate through the real Member login form;
- navigate from the Member dashboard to services;
- open an active service;
- select a visible schedule;
- confirm personal-points spending;
- observe the confirmed booking detail;
- verify the booking appears in history;
- verify another Member cannot open that Booking;
- verify protected identity, claim, credential, and raw audit markers are absent;
- assert no JavaScript or unexpected console errors.

Browser tests supplement rather than replace:

- domain/application-service tests;
- HTTP feature tests;
- Livewire/Filament component tests;
- authorization and audit regressions.

Execution strategy:

- focused closure run: Chromium only;
- future CI recommendation: Chromium smoke on pull requests, Firefox/WebKit on
  nightly or release gates;
- do not claim cross-browser coverage unless those engines are actually run.

## Documentation

Update only:

```text
docs/mvp/evidence/mvp-03-controlled-b2c-radiology-booking.md
docs/mvp/beta-gap-register.md
docs/mvp/roadmap.md
docs/mvp/work-package-status.md
```

Record baseline, execution commit, dual-role policy result, discoverable
actions, schedule hydration, production audit association, direct actor-state
coverage, Pest/browser dependency versions, exact browser scenarios and
browser engines actually run, exact commands/results, changed files,
generated artifacts excluded, unrun checks, and remaining gaps.

Treat `MVP-GAP-011` as unaccepted during execution. Close only after all
criteria pass.

## Verification

- Method: Validate the task; resolve and install only the permitted Pest 4
  browser dependencies; run focused MVP-03 domain, Member, Filament, and
  Chromium browser tests; prove the existing PHPUnit test subset still runs;
  run directly affected MVP-01/MVP-02 and filtered authorization/audit
  regressions; run bounded Pint; run `git diff --check`; inspect routes,
  action surfaces, browser artifacts, audit queries, and failure events.
- Expected result: Eligible Members remain self-bound without an unsupported
  blanket role denial, Filament mutations are discoverable and service-backed
  in both Livewire and a real Chromium browser, normal hydrated schedule edits
  work, the Member booking smoke journey completes, failure audit is
  production-coherent, all negative actor states fail closed, and existing
  PHPUnit-focused behavior remains intact.

Required:

```bash
git diff --check
```

Run the focused browser suite with Chromium only. Do not run full suites,
MySQL/Docker, npm build, Composer audit, external integrations, deployment,
or production checks.

## Acceptance criteria

- [ ] Baseline ancestry and repository state are confirmed.
- [ ] Published task validation passes.
- [ ] Existing work is preserved.
- [ ] Trusted Member ownership remains authoritative.
- [ ] Administrator-only User with no Member is denied.
- [ ] Eligible dual-role behavior follows authoritative policy.
- [ ] No actor can target another Member.
- [ ] Offering create action is discoverable only with manage permission.
- [ ] Offering edit action is discoverable only with manage permission.
- [ ] Schedule create action is discoverable only with manage permission.
- [ ] Schedule edit action is discoverable only with manage permission.
- [ ] All Filament mutations use application services.
- [ ] Site references and Bookings remain read-only.
- [ ] No delete, bulk, import, or export is added.
- [ ] Hydrated unbooked schedule edit succeeds.
- [ ] Hydrated booked-schedule close succeeds.
- [ ] Unchanged UTC appointment times remain unchanged.
- [ ] Booked frozen fields remain protected.
- [ ] Booking detail shows only exact successful associated events.
- [ ] Member-scoped failures are not attached to successful Bookings.
- [ ] Failure categories remain controlled and sanitized.
- [ ] Anonymous actor is tested.
- [ ] Administrator-only actor is tested.
- [ ] Missing-Member actor is tested.
- [ ] Suspended actor is tested.
- [ ] Login-disabled actor is tested.
- [ ] Mandatory-change actor is tested.
- [ ] Child actor is tested.
- [ ] Identity-incomplete actor is tested.
- [ ] Profile-incomplete actor is tested.
- [ ] Valid Member behavior remains successful.
- [ ] Every denial produces no partial success state.
- [ ] Pest 4 is used without upgrading to Pest 5 or PHPUnit 13.
- [ ] Existing PHPUnit test classes remain unchanged and executable.
- [ ] Only development testing dependencies are added.
- [ ] Chromium browser installation succeeds without privileged or production changes.
- [ ] Browser artifacts are ignored and not committed.
- [ ] Real-browser admin login succeeds with synthetic testing-only data.
- [ ] Real-browser offering Create and Edit actions are discoverable and executable.
- [ ] Real-browser schedule Create and Edit actions are discoverable and executable.
- [ ] Real-browser quota-only edit preserves hydrated timestamps.
- [ ] Real-browser booked-schedule close preserves the appointment.
- [ ] Real-browser read-only admin sees no mutation actions.
- [ ] Real-browser Member booking smoke journey succeeds.
- [ ] Another Member cannot open the booking in the browser journey.
- [ ] Browser pages expose no protected values and report no JavaScript errors.
- [ ] Focused MVP-03 tests pass.
- [ ] Affected MVP-01/MVP-02 regressions pass.
- [ ] Filtered authorization/audit regressions pass.
- [ ] Bounded Pint passes.
- [ ] `git diff --check` passes.
- [ ] No route, migration, runtime dependency, or broader scope is added.
- [ ] Evidence records only observed results.
- [ ] `MVP-GAP-011` closes only after all criteria pass.
- [ ] WP-05, WP-06, and WP-10 remain partial.
- [ ] No deployment, commit, push, or protected value is added.

## Stop conditions

Stop as `awaiting-approval` when ancestry is absent, work overlaps, dual-role
policy is ambiguous/prohibitive, actions require direct mutation or route
expansion, hydration requires redesign, failure association requires migration
or generic audit UI, broader regressions appear, or destructive/production work
is required.

## Output

- `succeeded`: all criteria and focused verification pass.
- `failed`: execution occurred but a criterion failed.
- `blocked`: required tooling or evidence is unavailable.
- `awaiting-approval`: a stop condition is reached.
- `exhausted`: iteration limit reached.

## Final report

Report baseline and execution commit, changed files, dual-role policy and
behavior, discoverable actions, schedule hydration, audit association,
actor-state coverage, focused tests/regressions, formatting/diff/route/resource
checks, documentation/gap status, tests not run, remaining gaps, and
confirmation that no route, migration, dependency, broader scope, deployment,
commit, or push was added.

Do not include credentials or protected identifiers.

Do not commit or push.

Stop after this MVP-03 closure task.
