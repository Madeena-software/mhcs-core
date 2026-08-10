---
name: mhcs-core-mvp-04-operator-arrival-remediation
description: Correct the reviewed MVP-04A login, authorization, site-switch, arrival, confirmation, test-evidence, and traceability defects without expanding scope.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04A — Operator Foundation and Arrival Remediation

## Objective

Remediate the reviewed implementation at baseline:

`eb12e2a6d533adb19b2cef120919b30fdd28e609`

Required corrected flow:

```text
provisioned Operator-only or dual-role User
→ authenticates through the shared credential foundation
→ reaches the correct authorized surface
→ selects one authorized active site
→ views only eligible attendance for an assigned schedule
→ explicitly confirms physical arrival
→ server derives the authoritative site and schedule
→ eligible confirmed and charged booking moves atomically to arrived
```

This task corrects the following reviewed findings:

1. Operator-only Users cannot complete the real interactive login flow because
   authentication and post-login routing require an eligible Member.
2. Shift-assignment UI and application services enforce different manage
   permissions.
3. Site switching checks `pending` states that production code never creates.
4. Arrival resolution does not reapply the full attendance-eligibility
   predicate.
5. Arrival is submitted without an explicit confirmation step.
6. Post-arrival navigation trusts a caller-supplied schedule ID.
7. Current tests bypass real login and do not isolate critical permissions or
   negative boundaries.
8. MVP-04 evidence contains a truncated baseline and stale execution-state
   wording.

This is remediation only. Do not implement the next MVP-04 slice.

Pest/Playwright/browser-platform work remains deferred.

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

Before editing:

1. Resolve `$TARGET` canonically.
2. Confirm the expected `Madeena-software/mhcs-core` repository.
3. Confirm baseline
   `eb12e2a6d533adb19b2cef120919b30fdd28e609`
   is an ancestor of HEAD.
4. Record branch, HEAD, staged, modified, untracked, and relevant ignored paths.
5. Preserve existing work.
6. Stop as `awaiting-approval` if required files overlap existing work.
7. Do not reset, clean, discard, stash, stage, commit, push, deploy, or access
   production.

Read completely:

- root and `.agents` instructions;
- agent-task and develop-feature skills;
- the published MVP-04A task;
- the reviewed commit and parent diff;
- shared credential, mandatory-password, account-state, claims,
  authenticated-context, audit, outbox, idempotency, and transaction code;
- Member login/profile/dashboard behavior and tests;
- all MVP-04A production files, portal views, Filament resources, tests,
  migration, and evidence;
- accepted MVP-01 through MVP-03 evidence and affected tests;
- installed Laravel 13 and Filament 5 source when API behavior must be verified.

Repository authority and installed source override assumptions.

## Reviewed findings

### F1 — Operator-only login unavailable

The current interactive login service requires a resolved eligible-adult Member.
The login controller also terminates an authenticated session when no Member
exists. An otherwise valid Operator-only User therefore cannot use `POST
/login`.

The accepted model permits Operator-only and dual-role Users. Shared User
identity does not require every Operator to own a Member profile.

### F2 — Shift-assignment permission mismatch

The Filament shift-assignment resource uses the shift-management authorization
path, while `OperatorShiftAssignmentService` uses site/profile assignment
management. One exact permission must own manual eligible-shift assignment at
UI and execution time.

### F3 — Ineffective site-switch blocker

Arrival rows are created as `recorded`; shift assignments are created as
`active`. The switch guard checks only `pending`, so the reviewed blocker cannot
observe production-created unresolved work.

### F4 — Arrival eligibility mismatch

The attendance list requires an assigned site/schedule, `confirmed` status,
personal funding, and a charge ledger entry. Arrival resolution checks only
site, schedule window, and `confirmed`. A direct booking-ID submission can
therefore target a booking absent from authorized attendance.

### F5 — No explicit arrival confirmation

The attendance page posts the mutation immediately. There is no confirmation
page, confirmation state, or equivalent explicit confirmation.

### F6 — Caller controls redirect schedule

The mutation request accepts `schedule_id`, and the controller redirects using
that value instead of the authoritative schedule returned by the service.

### F7 — Missing execution-path proof

Portal tests use `actingAs`; administration tests grant both manage permissions.
The reviewed login, permission-separation, site-switch, ineligible-arrival,
confirmation, and authoritative-redirect boundaries are not proven.

### F8 — Stale evidence

The evidence uses an invalid/truncated baseline SHA and states that no execution
commit exists even though the reviewed implementation is committed.

## Scope and constraints

### Included

- shared interactive login routing for Member-only, Operator-only, and
  dual-role Users;
- mandatory-password replacement for authorized Operator-only Users;
- exact shift-assignment authorization;
- bounded unresolved-work site-switch enforcement;
- unified attendance/arrival eligibility;
- explicit arrival confirmation;
- server-derived post-arrival navigation;
- focused PHPUnit, Laravel feature, Livewire/Filament, security, and
  architecture tests;
- targeted MVP-01 through MVP-04A regressions;
- bounded MVP-04 evidence/status corrections.

### Excluded

Do not add:

- public registration or a second credential store;
- separate Operator passwords or staff-role types;
- identity verification, consent, `checked_in`, ticketing, queue stages,
  clinical work, walk-ins, cash, Encounter creation, or X-ray capture;
- NPZ, DICOM, Cornerstone, MPIPS, or Image Gateway behavior;
- Pest/Playwright/browser work;
- dependencies, migrations, CI workflows, external adapters, deployment, or
  production configuration.

Do not modify:

- `.agents/context/**`;
- `docs/implementation/**`;
- accepted source digests or requirement assignments;
- accepted Member migrations;
- existing Pest/browser files;
- Composer/npm dependency files.

Do not commit or push.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `4`
- Approval gates:
  - stop if baseline ancestry is absent;
  - stop for overlapping work;
  - stop if login correction requires changing credential semantics, Member NIK
    behavior, rate limiting, or account-state policy;
  - stop if Operator-only password replacement requires Member identity;
  - stop if unresolved-work semantics cannot be derived safely from repository
    authority;
  - stop before any dependency, migration, external boundary, CI workflow, or
    later MVP behavior;
  - stop if focused tests expose a broader accepted MVP-01/02/03 defect;
  - stop before destructive or production-affecting work.

## Execution procedure

1. Validate this task.
2. Confirm repository identity, ancestry, and worktree state.
3. Reproduce F1 with a real `POST /login` test before production changes.
4. Inspect shared credential and mandatory-password contracts.
5. Implement trusted actor-aware post-authentication destination resolution.
6. Correct exact shift-assignment authorization.
7. Define and implement the smallest source-supported unresolved-work
   site-switch rule.
8. Refactor attendance and arrival to one authoritative eligibility predicate.
9. Add explicit confirmation and authoritative redirect behavior.
10. Add focused negative and execution-time tests.
11. Run bounded regressions.
12. Correct evidence and status wording.
13. Re-read the final diff against this task.
14. Stop without starting the next MVP-04 slice.

## Required implementation

### 1. Shared interactive authentication

Preserve one shared User and credential verifier.

Implement a bounded destination resolver or equivalent trusted application
boundary after credential verification.

#### Member-only User

- preserve existing email/NIK login behavior;
- complete eligible Member routes to Member dashboard;
- incomplete eligible Member routes to Member profile;
- generic failure behavior remains;
- request input cannot grant Operator authority.

#### Operator-only User

Email/shared-credential login succeeds only when:

- User can authenticate;
- mandatory replacement is not pending;
- active Operator profile exists;
- exact Operator role is active;
- exact portal permission is active.

Then route to `/operator`.

Absence of a Member profile is not a failure. Do not invent NIK login for
Operator-only Users. Inactive profile, revoked role/permission, suspended User,
login-disabled User, and invalid credentials fail closed.

#### Dual-role User

- use trusted persisted roles, profiles, and authorization;
- honor a valid server-side intended route only when authorized;
- otherwise use a deterministic repository-supported destination;
- do not expose an untrusted role selector;
- each surface remains independently authorized.

#### Mandatory password replacement

- shared replacement works for an authorized Operator-only User;
- it must not require Member profile, Member age, or Member display name;
- after replacement, route through the trusted destination resolver;
- preserve password policy, current-password verification, audit, session
  regeneration, and rate-limiting behavior;
- preserve Member-specific wording where applicable and use neutral approved
  wording for Operator-only context.

### 2. Exact shift-assignment permission

Use one exact permission consistently. Preferred:

```text
operator.shift.manage
```

Requirements:

- Filament visibility, create, and revoke use it;
- application-service assign and revoke use it;
- execution-time revocation is observed;
- `operator.assignment.manage` remains for Operator-to-site assignment;
- either permission alone cannot exercise the other mutation boundary;
- read permissions remain independent.

### 3. Site-switch unresolved-work rule

Implement a bounded, source-supported rule.

- first site selection remains possible;
- switching from site A to site B is rejected for unresolved current-site work
  represented by states production code actually creates;
- `recorded` arrivals awaiting verification are treated consistently with the
  worklist;
- do not make all multi-site Operators permanently unable to switch merely
  because historical or unrelated assignments exist;
- completed/resolved work does not block;
- blocked switch preserves the previous site;
- success and controlled denial are audited;
- caller input cannot resolve work;
- do not invent queue/cash states.

If the current slice has no authoritative way to resolve a recorded arrival,
stop as `awaiting-approval` and report safe lifecycle options.

### 4. One attendance-eligibility predicate

List and arrival resolution must share the authoritative eligibility rules:

- active synchronized site;
- booking site snapshot and schedule match;
- occurrence lies within the accepted half-open window;
- status is `confirmed`;
- funding source is supported by this slice;
- required charge exists;
- booking is otherwise eligible;
- Operator assignment and active site are verified;
- Member command validates the trusted context/site correspondence.

Lock and revalidate before mutation.

A direct ineligible booking ID must create no arrival, booking transition,
status event, outbox event, or success audit.

Do not duplicate protected Member data into Operator tables.

### 5. Explicit confirmation

Add one explicit confirmation boundary.

Preferred bounded flow:

```text
POST /operator/arrivals/confirm
→ re-authorize and display a safe summary
→ issue short-lived session-bound confirmation state
→ POST /operator/arrivals
→ re-authorize and revalidate all authoritative data
```

An equivalent server-side flow is acceptable.

Requirements:

- safe operational data only;
- no full NIK, contact, points, payment details, claims, or raw ledger data;
- CSRF required;
- final execution revalidates all state;
- cancellation performs no mutation;
- duplicate final submission remains idempotent;
- no JavaScript dependency is required.

### 6. Authoritative redirect

- final mutation must not require caller `schedule_id`;
- redirect using `schedule_id` returned by `OperatorArrivalService`;
- confirmation input must be rederived/revalidated;
- tampered schedule input cannot alter mutation or navigation.

### 7. Evidence correction

Update only as required:

```text
docs/mvp/evidence/mvp-04-operator-foundation-arrival.md
docs/mvp/roadmap.md
docs/mvp/beta-gap-register.md
docs/mvp/work-package-status.md
```

Record:

- implementation baseline
  `eb12e2a6d533adb19b2cef120919b30fdd28e609`;
- remediation execution commit when available, otherwise the truthful working
  tree state;
- exact commands and results;
- real login, permission separation, site-switch, ineligible-arrival,
  confirmation, and redirect evidence;
- tests not run;
- no Pest/browser/CI/MySQL/deployment claim.

Remove the truncated SHA and stale “no execution commit” statement. Keep MVP-04
and relevant Work Packages partial/open.

## Focused tests

Do not use Pest browser tests.

### Real login

Prove through `POST /login`:

- Operator-only User reaches `/operator`;
- Member-only behavior remains;
- dual-role behavior follows trusted destination rules;
- invalid credentials remain generic;
- inactive profile, missing/revoked role, and missing/revoked portal permission
  fail;
- suspended/login-disabled User fails;
- Operator-only mandatory-password replacement succeeds without a Member;
- Member NIK behavior remains;
- request parameters cannot select or grant a role.

### Permission separation

Prove:

- shift manage without assignment manage can assign/revoke eligible shifts;
- assignment manage without shift manage cannot;
- site assignment still requires assignment manage;
- permission revoked after component mount fails at service execution;
- failure creates no record, success audit, or event.

### Site switching

Prove:

- first selection succeeds;
- clean authorized switch succeeds;
- unauthorized/inactive/revoked site fails;
- unresolved current-site work blocks;
- resolved work does not block;
- blocked switch preserves the previous site;
- success and denial audits are bounded.

### Arrival eligibility

Prove:

- eligible booking succeeds;
- confirmed but uncharged booking fails;
- unsupported funding source fails;
- wrong site and unassigned schedule fail;
- out-of-window and stale/non-confirmed booking fail;
- every failure leaves all mutation/event/outbox/success-audit state unchanged;
- same idempotency replay returns the original;
- changed replay conflicts;
- trusted context site must correspond to the stable Operator site.

### Confirmation and redirect

Prove:

- initial action does not mutate;
- safe confirmation state is shown;
- cancellation does not mutate;
- final confirmation mutates once;
- missing/expired/reused confirmation fails where applicable;
- final request does not require caller schedule ID;
- tampered schedule input is ignored or rejected;
- redirect uses the authoritative returned schedule.

### Targeted regressions

Run focused affected:

- MVP-01 login, mandatory-password, profile, dashboard, logout;
- MVP-02 admin/account-state;
- non-browser MVP-03 Member/admin/domain;
- MVP-04A foundation, portal, and admin;
- filtered WP-02 claims/context/audit/idempotency/security;
- filtered WP-04 identity/account-state;
- affected architecture tests.

## Verification

- Method: Validate the task; reproduce reviewed failures; run focused real-login,
  permission, site-switch, arrival, confirmation, and redirect tests; run
  bounded regressions; run Pint on changed PHP; run PHP syntax checks; inspect
  routes, permissions, audit/outbox payloads, and module boundaries; run
  `git diff --check`.
- Expected result: Operator-only and dual-role Users authenticate safely, exact
  permissions are consistent, switching observes real states, only
  attendance-eligible bookings can arrive, arrival is explicitly confirmed,
  navigation is server-derived, and evidence is accurate.

Required:

```bash
git diff --check
```

Do not run:

- Pest/Playwright;
- full PHPUnit;
- complete Work Package suites;
- MySQL/Docker conformance;
- npm build;
- Composer audit;
- external integrations;
- deployment or production checks.

## Acceptance criteria

- [ ] Baseline ancestry and worktree state are confirmed.
- [ ] Task validation passes.
- [ ] Existing work is preserved.
- [ ] No dependency, migration, browser, CI, external, or production scope is added.
- [ ] Operator-only User completes real shared login.
- [ ] Member login behavior remains unchanged.
- [ ] Dual-role routing is trusted and deterministic.
- [ ] Operator-only password replacement works without Member identity.
- [ ] Invalid/inactive/revoked access fails closed.
- [ ] Request input cannot grant or select authority.
- [ ] One exact permission controls eligible-shift assignment in UI and service.
- [ ] Site and shift assignment permissions remain independent.
- [ ] Execution-time revocation is enforced.
- [ ] First site selection remains possible.
- [ ] Site switching checks production-created unresolved states.
- [ ] Blocked switch preserves the current site and is audited.
- [ ] Attendance list and arrival share one eligibility predicate.
- [ ] Uncharged/unsupported bookings cannot arrive.
- [ ] Wrong site, assignment, state, and window fail closed.
- [ ] Failed arrival creates no partial records or success evidence.
- [ ] Trusted context site matches the stable Operator site.
- [ ] Arrival requires explicit confirmation.
- [ ] Confirmation contains safe data only.
- [ ] Final mutation does not trust caller schedule ID.
- [ ] Redirect uses the authoritative schedule.
- [ ] Idempotency behavior remains correct.
- [ ] Focused new tests pass.
- [ ] Affected MVP-01 through MVP-04A regressions pass.
- [ ] Filtered security/identity/architecture regressions pass.
- [ ] Pint, syntax checks, and `git diff --check` pass.
- [ ] Evidence contains the correct baseline and actual execution state.
- [ ] MVP-04 and relevant Work Packages remain partial/open.
- [ ] Pest/browser files are not modified or run.
- [ ] No later MVP, commit, push, deployment, or production work occurs.

## Stop conditions

Stop as `awaiting-approval` when:

- baseline ancestry is absent;
- required files overlap existing work;
- login remediation requires credential-policy changes;
- Operator-only replacement requires Member identity;
- unresolved-work lifecycle cannot be established from authority;
- a migration/dependency appears necessary;
- a broader accepted MVP regression is found;
- later MVP, external, destructive, or production work is required.

## Output

- `succeeded`
- `failed`
- `blocked`
- `awaiting-approval`
- `exhausted`

## Final report

Report baseline and execution commit, runtime/model when verifiable,
capabilities, findings corrected, files changed, login/destination behavior,
permission separation, site-switch semantics, arrival eligibility,
confirmation/redirect behavior, audit/event/transaction/idempotency behavior,
focused tests, regressions, static checks, evidence corrections, tests not run,
remaining MVP-04 scope, and confirmation that no dependency, migration,
browser-platform, later MVP, external, CI, deployment, commit, push, or
production work was added.

Do not include credentials or protected identifiers.

Do not commit or push.

Stop after this remediation.
