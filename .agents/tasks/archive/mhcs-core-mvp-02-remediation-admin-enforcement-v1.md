---
name: mhcs-core-mvp-02-remediation-admin-enforcement
description: Enforce MVP-02 audit and account-action authorization at server-side execution boundaries, reconcile missing bootstrap claims safely, complete focused evidence, and correct MVP status without expanding scope.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-02 Remediation — Admin Enforcement and Evidence

## Objective

Remediate commit:

`e4bb004b92645a7392e76c0fca5fa49cfd42d60c`

Fix only these findings:

1. Member audit authorization currently controls whether the embedded table is
   rendered, but the registered audit table query does not independently
   require `member.audit.read`.

2. Suspend and restore authorization, expected-state checks, and self-target
   prevention currently control action visibility but are not repeated
   immediately before mutation.

3. `MvpAdminSeeder` stops when an expected bootstrap claim is missing instead
   of safely reconciling the missing claim while preserving the password.

4. The focused test evidence does not establish all security-critical MVP-02
   acceptance boundaries, while `MVP-GAP-010` is recorded as closed.

Required outcome:

```text
authorized administrator
→ safe Member list and detail
→ audit rows only after server-side audit authorization
→ suspend/restore only after execution-time authorization
→ no self-suspension through Member administration
```

and:

```text
repeated local/testing bootstrap
→ preserve password
→ add only missing expected active claims
→ create no duplicates
→ never reactivate intentionally inactive claims
→ stop on ambiguous or unrelated inconsistency
```

This is a narrow remediation only.

Do not implement MVP-03 or broaden Member administration.

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

`e4bb004b92645a7392e76c0fca5fa49cfd42d60c`

as the remediation baseline.

Before changing files:

1. Resolve `$TARGET` to a canonical absolute path.
2. Confirm the expected `Madeena-software/mhcs-core` repository.
3. Confirm that the baseline exists in repository history.
4. Record the current branch and commit.
5. Record staged, modified, untracked, and relevant ignored files.
6. Preserve all pre-existing work.
7. Stop as `awaiting-approval` when existing work overlaps files required by
   this task.
8. Do not reset, clean, discard, stash, stage, commit, push, deploy, or access
   production.

The local setup files introduced before the MVP-02 implementation are outside
this remediation. Do not modify:

```text
.env.local
docker-compose.local.yml
deployment/deploy-local.sh
```

Read completely before planning or editing:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-02-shared-admin-shell-member-administration-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-02-remediation-admin-enforcement-v1.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/docs/mvp/evidence/mvp-02-shared-admin-shell-member-administration.md`;
- `$TARGET/app/Modules/Member/Filament/Resources/Members/MemberResource.php`;
- `$TARGET/app/Modules/Member/Filament/Resources/Members/Pages/ListMembers.php`;
- `$TARGET/app/Modules/Member/Filament/Resources/Members/Pages/ViewMember.php`;
- `$TARGET/app/Modules/Member/Filament/Resources/Members/MemberAuditRecord.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberAuthorization.php`;
- `$TARGET/app/Modules/Member/Application/Services/AccountStateService.php`;
- `$TARGET/app/Shared/Authorization/AdminPanelAccessService.php`;
- `$TARGET/app/Shared/Authorization/AuthorizationClaimResolver.php`;
- `$TARGET/app/Shared/Authorization/DatabaseAuthorizationClaimResolver.php`;
- `$TARGET/app/Shared/Context/LaravelAuthenticatedContextProvider.php`;
- `$TARGET/app/Providers/Filament/Pages/AdminLogin.php`;
- `$TARGET/database/seeders/MvpAdminSeeder.php`;
- `$TARGET/database/seeders/DatabaseSeeder.php`;
- `$TARGET/tests/Feature/Admin/Mvp02AdminAccessTest.php`;
- `$TARGET/tests/Feature/Admin/Mvp02MemberAdministrationTest.php`;
- directly affected existing MVP-01, WP-02, and WP-04 tests; and
- installed Filament 5 action, table, Livewire, and test APIs under
  `$TARGET/vendor/**`.

Use repository and installed-package evidence.

Do not infer Filament behavior from memory.

## Scope and constraints

- Change only the minimum MVP-02 implementation, focused tests, and evidence
  required to close the listed findings.
- Preserve the existing `/admin` panel, routes, resource fields, filters,
  search, persistent claim tables, and module ownership.
- Continue using `AccountStateService` as the authoritative account-state
  transition boundary.
- Do not add direct User state writes in Filament.
- Preserve:
  - `User::canAuthenticate()`;
  - `User::canAccessPanel()`;
  - `CredentialVerifier`;
  - `AccountStateUserProvider`;
  - `LaravelAuthenticatedContextProvider`;
  - shared guard/session behavior; and
  - accepted MVP-01 behavior.
- Do not add routes.
- Do not add migrations.
- Do not add or replace Composer or npm dependencies.
- Do not add role or permission management UI.
- Do not add Member creation, editing, deletion, identity verification,
  assisted recovery, guardian management, bulk import, bookings, schedules,
  services, payments, points, promotions, or settings.
- Do not add Operator, Image Gateway, or Doctor resources.
- Do not modify `.agents/context/**`.
- Do not modify `docs/implementation/**`.
- Do not modify any published task file.
- Do not modify:
  - `.env.local`;
  - `docker-compose.local.yml`;
  - `deployment/deploy-local.sh`.
- Do not run:
  - complete PHPUnit;
  - complete WP-02;
  - complete WP-04;
  - MySQL conformance;
  - Docker;
  - npm build;
  - Composer audit;
  - deployment verification;
  - external integrations; or
  - production checks.
- Do not stage, commit, push, deploy, or access production.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `4`
- Approval gates:
  - stop as `awaiting-approval` when baseline ancestry is absent;
  - stop as `awaiting-approval` when existing work overlaps required files;
  - stop as `awaiting-approval` when installed Filament 5 cannot enforce the
    required query/action boundary without redesigning the panel;
  - stop as `awaiting-approval` when safe seeder reconciliation cannot
    distinguish missing from intentionally inactive claims;
  - stop as `awaiting-approval` when the fix requires a route, migration,
    dependency, production policy, or local deployment change;
  - stop as `awaiting-approval` when focused tests reveal a broader
    MVP-01/WP-02/WP-04 regression outside this bounded task;
  - stop as `awaiting-approval` before any destructive or
    production-affecting operation.

## Execution procedure

1. Resolve and verify `$TARGET`.
2. Validate this task with the repository task validator.
3. Confirm baseline ancestry and repository state.
4. Read all required context and installed Filament 5 APIs.
5. Inspect the current audit table lifecycle and Livewire serialization path.
6. Inspect the current suspend/restore action execution path.
7. Inspect the current `MvpAdminSeeder` repeated-run behavior.
8. Implement server-side audit-query authorization.
9. Implement execution-time suspend/restore authorization.
10. Implement safe missing-claim reconciliation in `MvpAdminSeeder`.
11. Add the focused security-critical tests declared below.
12. Run only the required focused verification.
13. Update MVP-02 evidence and status documents from observed results.
14. Re-read this task against the final diff.
15. Stop without continuing to MVP-03.

## Required implementation

### 1. Audit authorization at the query boundary

`member.audit.read` must be checked server-side before any Member audit row is
queried or serialized.

Requirements:

- keep the audit section absent for administrators without audit permission;
- independently guard the table/query lifecycle;
- without permission:
  - abort the audit interaction with 403; or
  - return a guaranteed empty query;
- prevent audit retrieval through:
  - Livewire snapshots;
  - table state;
  - searching;
  - sorting;
  - pagination; or
  - crafted method calls;
- preserve `source = member`;
- preserve the selected Member and linked User target restriction;
- preserve approved safe audit columns;
- keep Member detail available to an administrator with
  `member.account.read` but without `member.audit.read`;
- do not expose metadata, roles, permissions, session IDs, digests, protected
  identifiers, address, emergency contact, credentials, or clinical values;
- do not trust Livewire payload claims.

Add a focused Livewire test proving an account-read-only administrator cannot
retrieve a known audit value.

### 2. Suspend and restore execution-time authorization

For both suspend and restore, immediately before calling
`AccountStateService`:

- require exact `member.account.manage`;
- resolve the authenticated administrator server-side;
- reject missing or inconsistent Member-to-User linkage;
- reject self-targeting;
- suspend only when the current source state is `active`;
- restore only when the current source state is `suspended`;
- reject pending and unsupported states;
- validate a trimmed, non-empty reason;
- enforce maximum reason length of 1,000;
- ignore or reject unexpected User ID and target-state payload fields;
- call only:
  - `AccountStateService::suspend()`; or
  - `AccountStateService::restore()`;
- preserve generic failure notification behavior;
- refresh table/detail state after success when required by Filament 5;
- keep bulk actions absent.

Do not rely only on action visibility.

Add Livewire tests proving:

- missing manage permission cannot execute;
- direct self-suspend invocation cannot execute;
- pending state cannot execute either transition;
- blank reason fails;
- overlong reason fails;
- unexpected payload cannot retarget the action;
- suspend succeeds for an eligible different active Member;
- restore succeeds for an eligible suspended Member;
- success creates the existing sanitized audit event;
- rejection changes no state and creates no success audit; and
- suspension prevents the Member from using the Member portal on the next
  request.

### 3. Seeder reconciliation

When the synthetic administrator already exists:

- preserve its password hash;
- preserve account-state consistency checks;
- preserve the no-Member requirement;
- add a missing expected role or permission only when no row exists for that
  exact claim;
- create no duplicates;
- use `assigned_by_user_id = null` for a newly reconciled bootstrap claim;
- leave valid active expected assignments unchanged;
- do not reactivate an inactive expected assignment;
- stop when an expected assignment exists but is inactive;
- stop on unexpected assigner, duplicate, unrelated, or ambiguous assignment
  state;
- do not print a credential during repeated execution;
- perform reconciliation transactionally.

Add tests proving:

- missing expected role is inserted;
- missing expected permission is inserted;
- password hash remains unchanged;
- repeated execution creates no duplicates;
- inactive role remains inactive and causes a stop;
- inactive permission remains inactive and causes a stop;
- unrelated or inconsistent assignments cause a stop;
- the seeder refuses outside `local` and `testing`; and
- `DatabaseSeeder` does not invoke `MvpAdminSeeder`.

### 4. Complete focused acceptance coverage

Add bounded tests for:

#### Claim resolution

- active exact roles and permissions resolve;
- inactive assignments are excluded;
- blank and wildcard claims are excluded or rejected;
- unknown User returns empty claims;
- unavailable assignment storage fails closed;
- one request-scoped resolver does not mix two Users;
- deactivation is observed by the next request;
- request, route, form, session, and Livewire claims cannot elevate access.

#### Admin login

- unknown email and wrong password return the same generic error;
- credential-valid non-administrator returns the same generic error;
- role-only User is denied;
- access-permission-only User is denied;
- suspended User is denied;
- pending User is denied;
- login-disabled User is denied;
- mandatory-change User is denied;
- failed login leaves no authenticated User;
- existing credential throttling applies;
- submitted email and password are absent from audit metadata.

#### Member resource

- administrator without `member.account.read` is denied list and detail;
- administrator-only User requires no Member row;
- approved safe values are visible;
- actual protected/private test values are absent from HTML and Livewire
  payload;
- search by name works;
- search by MRN works;
- search by email works;
- create, edit, delete, replicate, bulk, and export remain absent;
- Operator, Image Gateway, and Doctor resources/navigation remain absent.

#### Audit

- authorized audit events are newest first;
- unrelated Member events are absent;
- unauthorized Livewire interaction returns no audit data;
- approved safe fields remain visible;
- forbidden audit fields and values remain absent;
- no edit, delete, or export action exists.

Keep tests in the existing focused MVP-02 files unless one additional narrowly
named test file materially improves clarity.

## Documentation updates

Update only:

```text
docs/mvp/evidence/mvp-02-shared-admin-shell-member-administration.md
docs/mvp/beta-gap-register.md
docs/mvp/roadmap.md
docs/mvp/work-package-status.md
```

Record:

- remediation baseline and execution commit;
- server-side audit-query authorization;
- execution-time account-action authorization;
- self-target behavior;
- seeder reconciliation behavior;
- exact focused commands and observed results;
- accurate changed files;
- tests not run;
- no deployment or production-readiness claim.

Treat `MVP-GAP-010` as not accepted during remediation.

Close it again only after all corrected focused checks pass. Otherwise leave it
open with the exact blocker.

Do not record plaintext credentials.

## Focused tests

Run only the tests necessary to establish this remediation.

At minimum:

- focused MVP-02 admin access tests;
- focused MVP-02 Member administration tests;
- directly affected MVP-01 authentication and account-state tests;
- filtered WP-02 authorization, context, credential, and audit tests;
- filtered WP-04 account-state tests.

Use individual files or filters.

Do not run full suites.

## Verification

- Method: Validate the published task, inspect the final diff, run
  `git diff --check`, run the focused MVP-02 tests, run only directly affected
  MVP-01 and filtered WP-02/WP-04 regressions, run bounded Pint on changed PHP
  files, inspect `/admin` routes/providers/resources, and statically review
  changed files for protected values, credentials, metadata, session IDs, and
  untrusted role/permission claims.
- Expected result: Member audit data is inaccessible without server-side
  `member.audit.read`, suspend/restore cannot execute without execution-time
  authorization or against the current administrator, missing expected
  bootstrap claims are reconciled without resetting credentials or
  reactivating inactive claims, focused regressions pass, and no route,
  migration, dependency, local-deployment, or unrelated feature change is
  introduced.

Required commands include:

```bash
git diff --check
```

Run bounded Pint only on changed PHP files.

Inspect routes to confirm no route expansion.

Do not run MySQL, Docker, deployment, npm build, Composer audit, or external
integrations.

## Acceptance criteria

- [ ] Baseline ancestry is confirmed.
- [ ] Repository state is recorded and overlapping work is preserved.
- [ ] This published task passes the repository validator.
- [ ] No route, migration, dependency, or broad administration scope is added.
- [ ] Audit rows require `member.audit.read` at the server-side query boundary.
- [ ] Account-read-only administrators cannot retrieve audit values through Livewire.
- [ ] Audit remains bounded to the selected Member and linked User.
- [ ] Suspend and restore re-authorize at execution time.
- [ ] Missing manage permission cannot execute either action.
- [ ] Self-suspension cannot execute through a crafted action call.
- [ ] Pending and unsupported states cannot execute transitions.
- [ ] Reason validation is trimmed, required, and limited to 1,000 characters.
- [ ] Unexpected payload cannot select another User or target state.
- [ ] Successful transitions still use `AccountStateService`.
- [ ] Successful transitions create the existing sanitized audit event.
- [ ] Rejected transitions preserve state and create no success audit.
- [ ] Suspended Member access fails closed on the next request.
- [ ] Seeder preserves the existing password hash.
- [ ] Seeder inserts only missing expected claims.
- [ ] Seeder creates no duplicate assignments.
- [ ] Seeder does not reactivate intentionally inactive claims.
- [ ] Seeder stops on ambiguous, unrelated, or inconsistent assignments.
- [ ] Seeder remains local/testing-only.
- [ ] `DatabaseSeeder` does not invoke `MvpAdminSeeder`.
- [ ] Claim-resolver focused boundaries are tested.
- [ ] Admin-login generic failure and account-state boundaries are tested.
- [ ] Member resource read denial and safe-value absence are tested.
- [ ] Audit authorization, ordering, and target isolation are tested.
- [ ] Existing MVP-01 behavior remains intact.
- [ ] Targeted WP-02 and WP-04 regressions pass.
- [ ] Bounded Pint passes.
- [ ] `git diff --check` passes.
- [ ] Route and provider inspection confirms no route/resource expansion.
- [ ] Evidence records only observed results.
- [ ] `MVP-GAP-010` is closed only after all corrected checks pass.
- [ ] No plaintext credential, protected identifier, deployment, commit, or push is added.

## Stop conditions

Stop as `awaiting-approval` when:

- the remediation baseline is absent;
- current work overlaps required files;
- installed Filament 5 cannot support query or action execution-time
  authorization without redesigning the panel;
- safe seeder reconciliation cannot distinguish missing from inactive claims;
- the fix requires a route, migration, dependency, production policy, or local
  deployment change;
- focused tests reveal a broader MVP-01/WP-02/WP-04 regression outside this
  bounded task;
- any destructive or production-affecting operation is required.

When stopped, report:

- exact conflict;
- affected files or contracts;
- work completed before stopping;
- safest options;
- owner decision required;
- repository state.

## Output

- `succeeded`: all acceptance criteria and focused verification pass.
- `failed`: execution occurred but a required criterion or focused check failed.
- `blocked`: required tooling or evidence is unavailable.
- `awaiting-approval`: an approval gate or stop condition is reached.
- `exhausted`: the iteration limit is reached before completion.

## Final report

Report:

- remediation baseline and execution commit;
- implementation outcome;
- files changed;
- audit-query authorization behavior;
- action execution-time authorization behavior;
- self-target behavior;
- seeder reconciliation behavior;
- focused tests and observed results;
- targeted regressions and observed results;
- route, provider, static-review, formatting, and diff checks;
- documentation and gap changes;
- tests not run;
- remaining MVP gaps;
- approval boundaries still open;
- confirmation that no route, migration, dependency, local deployment,
  production configuration, deployment, commit, push, or unrelated feature was
  added.

Do not include plaintext credentials.

Do not commit or push.

Stop after this remediation.
