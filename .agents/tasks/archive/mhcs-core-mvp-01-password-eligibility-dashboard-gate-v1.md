---
name: mhcs-core-mvp-01-password-eligibility-dashboard-gate
description: "Close the MVP-01 mandatory-password replacement eligibility gap, enforce the required profile-before-dashboard sequence, add focused regression tests, and correct bounded evidence without expanding MVP scope."
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-01 Remediation — Password Eligibility and Dashboard Gate

## Objective

Remediate the bounded MVP-01 implementation at commit:

`fc4d71560ca3cbddfb885d556fa1f7bed786ce25`

Fix only these accepted-review findings:

1. `POST /password/change-required` currently permits any authenticated,
   active, login-enabled User with `must_change_password = true` to reach the
   Member password-replacement service. The GET action checks for exactly one
   eligible adult Member, but the POST action performs the password mutation
   before checking Member eligibility. A direct POST can therefore clear the
   mandatory-password flag for an unlinked, ambiguous, child, or otherwise
   ineligible User.

2. An authenticated adult Member with an incomplete MVP-01 profile can request
   `/member/dashboard` directly. This bypasses the declared controlled-beta
   sequence in which the required profile fields are completed before dashboard
   access.

3. The focused test and evidence set does not prove the direct-POST rejection
   boundary or the profile-completion dashboard gate.

The observable outcome is:

```text
eligible adult Member with mandatory change
→ password replacement succeeds
→ incomplete profile redirects to profile
→ complete profile may access dashboard
```

and:

```text
unlinked, ambiguous, child, suspended, pending, or login-disabled User
→ cannot clear mandatory password state through the Member route
```

This task is a narrow remediation only.

Do not implement MVP-02 or unrelated MVP-01 enhancements.

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

Read completely before planning or writing:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/docs/mvp/README.md`;
- `$TARGET/docs/mvp/beta-scope.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/evidence/mvp-01-member-access-and-profile.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-01-member-access-and-profile-v1.md`;
- `$TARGET/app/Http/Controllers/Member/AuthenticationController.php`;
- `$TARGET/app/Http/Controllers/Member/DashboardController.php`;
- `$TARGET/app/Http/Middleware/EnforceMandatoryPasswordChange.php`;
- `$TARGET/app/Http/Middleware/EnsureMemberPortalAccess.php`;
- `$TARGET/app/Modules/Member/Application/Services/MandatoryPasswordReplacementService.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberContextResolver.php`;
- `$TARGET/app/Modules/Member/Application/Services/InteractiveMemberLoginService.php`;
- `$TARGET/app/Shared/Security/CredentialVerifier.php`;
- `$TARGET/routes/web.php`;
- `$TARGET/tests/Feature/Member/Mvp01MemberAccessTest.php`; and
- commit `fc4d71560ca3cbddfb885d556fa1f7bed786ce25`.

Use repository evidence and observed command output.

Do not infer successful verification from task text or documentation claims.

## Scope and constraints

- Implement only the two bounded behavior fixes and their focused tests and
  evidence correction.
- Preserve all existing Work Packages and MVP task files.
- Preserve the eight MVP-01 routes and their HTTP methods.
- Preserve strict `User::canAuthenticate()`, ordinary `Auth::attempt()`, and
  strict `CredentialVerifier::verify()` behavior.
- Preserve the dedicated restricted interactive login flow.
- Preserve the profile schema, editable fields, completion calculation, views,
  seeder, and documentation except where the accepted findings require a
  bounded correction.
- Do not modify public or online registration, B2B import, guardians,
  verification assets, recovery, age transition, UUID strategy, Operator,
  Image Gateway, Doctor, bookings, payments, imaging, results, administration,
  deployment, or production policy.
- Do not add or replace Composer or npm dependencies.
- Do not modify `.agents/context/**`, `docs/implementation/**`, or published
  task files.
- Do not commit, stage, push, deploy, access production, or perform a
  production-affecting operation.
- Do not run the complete PHPUnit suite, complete WP-02/WP-04 suites, MySQL,
  Docker, npm build, Composer audit, or deployment validation.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates: stop as `awaiting-approval` whenever any declared stop condition is met.

## Execution procedure

1. Resolve and verify `$TARGET`.
2. Confirm the repository, current branch, current commit, and ancestry from
   `fc4d71560ca3cbddfb885d556fa1f7bed786ce25`.
3. Inspect staged, modified, untracked, and relevant ignored files.
4. Stop as `awaiting-approval` if existing work overlaps the required files.
5. Read the required MVP-01 authentication, Member resolution, dashboard, test,
   and evidence files.
6. Implement the password-replacement eligibility invariant inside a
   transactionally safe Member application boundary.
7. Enforce profile completion before dashboard rendering.
8. Add focused positive and negative regression tests.
9. Run only the declared focused verification.
10. Correct the MVP-01 evidence with observed results.
11. Re-read this task against the final diff and report the outcome.

## Required remediation

### 1. Enforce password-replacement eligibility before mutation

The existing web flow must not rely only on the GET form action to establish
eligibility.

A direct POST must fail closed unless all of these are true at the mutation
boundary:

- the target User exists;
- the target User is the authenticated actor for self-service replacement,
  unless the existing explicitly authorized administrator path applies;
- `account_status = active`;
- `login_enabled = true`;
- `must_change_password = true`;
- exactly one Member is linked through `members.user_id`;
- the linked Member is eligible for the adult-only MVP beta; and
- the supplied current temporary credential is valid.

Perform the state and Member checks under the same database transaction and
appropriate row locks as the password mutation.

Preferred implementation:

- extend `MandatoryPasswordReplacementService` with the minimum Member
  eligibility dependency or an equivalent internal locked query;
- lock the User before checking account and mandatory-change state;
- lock or safely resolve the linked Member before changing the password;
- reject zero, multiple, child, or otherwise ineligible Member relations;
- preserve the existing administrator authorization rule only when it still
  targets an eligible Member;
- preserve operation idempotency and audit behavior;
- do not clear `must_change_password` before every authorization, state,
  ownership, eligibility, and credential check passes; and
- throw the existing generic Member-domain exception on failure.

Do not:

- rely solely on controller prechecks;
- add a request-supplied User or Member ID;
- make a child login-enabled;
- clear a flag manually in the controller;
- create a second password-replacement implementation;
- weaken the existing account-state rules; or
- expose the rejection reason to the browser.

The controller must continue mapping all service failures to the existing
generic current-password error.

After any failed direct POST:

- the password hash is unchanged;
- `must_change_password` remains true;
- account status and login-enabled state are unchanged;
- no success audit exists;
- no handled password-replacement operation exists; and
- the User does not obtain unrestricted Member access.

### 2. Require profile completion before dashboard access

An authenticated unrestricted adult Member whose completion percentage is below
100 must not render `/member/dashboard`.

Implement the smallest bounded gate.

Preferred implementation:

- after resolving the authenticated Member in `DashboardController`, check the
  existing `MemberContextResolver::isComplete()` result;
- redirect an incomplete Member to `member.profile`;
- optionally include a Bahasa Indonesia informational status message; and
- render the dashboard only when completion is 100%.

Do not:

- persist a completion flag or percentage;
- make email or Member phone mandatory;
- prevent saving an incomplete profile draft;
- add a new route;
- add a broad global profile middleware; or
- change the four-field completion rule.

The existing post-login and post-password-change redirects must remain
consistent with this gate.

### 3. Preserve fail-closed session behavior

Do not change the allowed restricted-session route set:

```text
GET  /password/change-required
POST /password/change-required
POST /logout
```

Suspended, pending, and login-disabled Users must remain unable to complete the
Member password-replacement route.

When an ineligible authenticated session reaches the route:

- fail closed;
- do not mutate credentials;
- invalidate or terminate the session where the current flow requires it; and
- do not disclose whether a Member relation exists.

### 4. Focused tests

Update only the focused MVP-01 feature test boundary and, only when necessary,
the directly relevant existing Member password-replacement test.

Add tests proving:

#### Direct password POST eligibility

- an authenticated active/login-enabled `must_change_password` User without a
  linked Member cannot clear the flag;
- an authenticated active/login-enabled `must_change_password` User linked to
  a child Member cannot clear the flag;
- a suspended User cannot clear the flag;
- a pending User cannot clear the flag;
- a login-disabled User cannot clear the flag;
- an invalid current password with an otherwise valid replacement reaches the
  service path and leaves the hash and flag unchanged;
- each rejection preserves the original password hash;
- each rejection creates no password-replacement success audit;
- each rejection creates no handled password-replacement operation; and
- an eligible adult Member still replaces the password successfully.

Use authenticated test sessions directly where needed to prove that POST
authorization is independent of the GET action and interactive login route.

#### Dashboard completion gate

- an incomplete authenticated Member is redirected from
  `/member/dashboard` to `/member/profile`;
- a 25%, 50%, or 75% incomplete profile remains blocked;
- a 100% complete profile renders the dashboard;
- the dashboard still displays only the approved safe fields; and
- profile draft saving remains allowed.

#### Existing regressions

Preserve tests proving:

- strict ordinary authentication rejects mandatory-change accounts;
- restricted sessions cannot access profile or dashboard before replacement;
- email and NIK interactive login still work;
- logout remains POST-only;
- ownership and protected-field behavior remain unchanged; and
- the synthetic seeder remains local/testing-only and idempotent.

Do not broaden the test file into unrelated MVP functionality.

### 5. Evidence correction

Update:

```text
docs/mvp/evidence/mvp-01-member-access-and-profile.md
```

Record:

- remediation baseline and execution commit;
- password-replacement eligibility now enforced at the transactional mutation
  boundary;
- direct POST rejection cases covered;
- dashboard completion gate;
- focused tests and observed results;
- targeted regression tests and observed results;
- files changed;
- full validation not run; and
- no production-readiness claim.

Do not record plaintext credentials or protected identifiers.

Do not falsely claim that `MemberContextResolver::resolveForUserId()` itself
rejects children unless the implementation is explicitly changed to do so.

## Required checks

Run only:

```bash
git diff --check
```

Run:

- the focused MVP-01 feature test file;
- the directly relevant existing password-replacement regression test or file
  only when changed behavior requires it;
- bounded Pint on changed PHP files; and
- a route-list inspection confirming the existing eight routes remain
  unchanged.

Statically inspect changed files for accidental logging, audit, rendering, or
persistence of:

```text
current_password
password
temporaryCredential
encrypted_nik
nik_lookup_digest
```

Do not run:

- the complete PHPUnit suite;
- the complete WP-02 suite;
- the complete WP-04 suite;
- MySQL conformance;
- Docker;
- deployment scripts;
- migrations outside the test environment;
- npm build;
- Composer audit;
- dependency installation;
- the synthetic seeder against non-test data; or
- external integrations.

## Acceptance criteria

- [ ] The remediation baseline exists in repository history.
- [ ] Existing overlapping work was not overwritten.
- [ ] The eight MVP-01 routes and methods remain unchanged.
- [ ] Password replacement checks account state and Member eligibility at the transactional mutation boundary.
- [ ] An unlinked User cannot clear `must_change_password` through direct POST.
- [ ] A child-linked User cannot clear `must_change_password` through direct POST.
- [ ] Suspended, pending, and login-disabled Users cannot clear the flag.
- [ ] Failed eligibility or credential checks preserve the original password hash.
- [ ] Failed checks preserve `must_change_password = true`.
- [ ] Failed checks create no success audit or handled operation.
- [ ] An eligible adult Member still completes password replacement.
- [ ] The controller continues returning a generic failure message.
- [ ] Strict ordinary authentication behavior remains unchanged.
- [ ] Restricted-session route access remains unchanged.
- [ ] An incomplete profile cannot render the dashboard.
- [ ] A complete profile can render the dashboard.
- [ ] Incomplete profile drafts remain saveable.
- [ ] No profile-completion field or percentage is persisted.
- [ ] Member ownership and protected-field controls remain unchanged.
- [ ] Focused direct-POST tests pass.
- [ ] Focused dashboard-gate tests pass.
- [ ] Targeted password and authentication regressions pass.
- [ ] `git diff --check` passes.
- [ ] Bounded Pint passes on changed PHP files.
- [ ] The MVP-01 evidence is corrected with observed results.
- [ ] No dependency, migration, route, unrelated MVP feature, commit, push, or deployment was added.

## Verification

- Method: Run `git diff --check`, the focused MVP-01 feature tests, directly relevant password-replacement regressions, bounded Pint, and route-list inspection.
- Expected result: Ineligible Users cannot mutate mandatory-password state, incomplete profiles cannot render the dashboard, eligible adult Member behavior still works, and no unrelated scope changes are present.

## Stop conditions

Stop as `awaiting-approval` when:

- commit `fc4d71560ca3cbddfb885d556fa1f7bed786ce25` is absent from repository
  history;
- existing work overlaps required files;
- enforcing Member eligibility inside the password-replacement transaction
  would break an approved non-Member password workflow already present in the
  repository;
- the existing service is documented as a shared cross-module password service
  rather than a Member-owned service;
- the correction requires changing `User::canAuthenticate()`,
  `AccountStateUserProvider`, or ordinary `Auth::attempt()` semantics;
- the correction requires a new dependency, migration, route, or schema field;
- the correction requires changing UUID, module ownership, privacy/retention,
  deployment, or requirement policy;
- focused tests reveal a broader WP-02/WP-04 regression outside this bounded
  remediation; or
- any destructive or production-affecting operation would be required.

When stopped:

- do not clear mandatory-password state through a controller workaround;
- do not remove the adult-only gate;
- do not weaken account-state checks;
- do not continue into MVP-02.

Report the exact conflict, affected contract, safest options, and owner decision
required.

## Output

- `succeeded`: all acceptance criteria and focused checks pass.
- `failed`: execution occurred but a required criterion or focused check failed.
- `blocked`: required tooling or evidence is unavailable.
- `awaiting-approval`: an approval gate or stop condition is reached.
- `exhausted`: the iteration limit is reached before completion.

## Final report

Report:

- remediation baseline and execution commit;
- files changed;
- transactional password-eligibility enforcement;
- rejected direct-POST states;
- dashboard completion behavior;
- focused tests and observed results;
- targeted regressions and observed results;
- route-list and formatting checks;
- evidence updates;
- unrun full validation;
- remaining MVP gaps; and
- confirmation that no dependency, migration, route, unrelated feature,
  commit, push, or deployment was performed.

Do not include plaintext credentials.

Do not commit or push.

Stop after this remediation.
