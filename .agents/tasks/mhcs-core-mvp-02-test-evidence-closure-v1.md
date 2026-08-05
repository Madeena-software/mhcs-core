---
name: mhcs-core-mvp-02-test-evidence-closure
description: Close the remaining MVP-02 focused-test evidence gaps for execution-time action authorization, claim resolution, login throttling, safe resource search, and seeder reporting without expanding product scope.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-02 — Focused Test Evidence Closure

## Objective

Review and close the remaining focused-test evidence gaps after commit:

`03ba160f2080a6924ae64402e48be990cc9c7ffd`

The production remediation now contains:

- server-side audit-query authorization;
- execution-time suspend/restore checks;
- self-target prevention;
- source-state validation;
- safe target derivation;
- trimmed reason validation; and
- missing bootstrap-claim reconciliation.

Do not redesign those implementations unless a focused test demonstrates a
real defect.

This task exists because the current tests do not yet prove every
security-critical acceptance criterion declared by:

```text
.agents/tasks/mhcs-core-mvp-02-remediation-admin-enforcement-v1.md
```

Required outcome:

```text
declared MVP-02 security boundary
→ focused test reaches the relevant execution path
→ observed state/audit/result proves the boundary
→ evidence is corrected
→ MVP-GAP-010 closes only when all focused checks pass
```

Do not implement MVP-03 or add broader Member administration.

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

`03ba160f2080a6924ae64402e48be990cc9c7ffd`

as the baseline.

Before editing:

1. Resolve `$TARGET`.
2. Confirm the expected repository.
3. Confirm baseline ancestry.
4. Record branch, commit, staged, modified, untracked, and relevant ignored
   files.
5. Preserve all existing work.
6. Stop as `awaiting-approval` if current work overlaps required files.
7. Do not reset, clean, discard, stash, stage, commit, push, deploy, or access
   production.

Read completely:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-02-shared-admin-shell-member-administration-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-02-remediation-admin-enforcement-v1.md`;
- `$TARGET/docs/mvp/evidence/mvp-02-shared-admin-shell-member-administration.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/app/Modules/Member/Filament/Resources/Members/MemberResource.php`;
- `$TARGET/app/Modules/Member/Filament/Resources/Members/Pages/ViewMember.php`;
- `$TARGET/app/Shared/Authorization/DatabaseAuthorizationClaimResolver.php`;
- `$TARGET/app/Providers/Filament/Pages/AdminLogin.php`;
- `$TARGET/database/seeders/MvpAdminSeeder.php`;
- `$TARGET/tests/Feature/Admin/Mvp02AdminAccessTest.php`;
- `$TARGET/tests/Feature/Admin/Mvp02MemberAdministrationTest.php`;
- installed Filament 5 table/action/Livewire test source under
  `$TARGET/vendor/**`.

Use installed-package evidence.

Do not assume that mounting or invoking a hidden Filament action executes its
callback.

## Scope and constraints

- Prefer test and evidence changes only.
- Correct production code only when a new focused test demonstrates a real
  defect.
- Preserve routes, migrations, dependencies, panel configuration, fields,
  filters, search ownership, claim tables, and application-service boundaries.
- Continue using `AccountStateService`.
- Do not add direct User state writes.
- Do not add Member create/edit/delete, identity verification, recovery,
  guardians, imports, bookings, schedules, services, payments, points,
  Operator, Image Gateway, or Doctor behavior.
- Do not modify `.agents/context/**`.
- Do not modify `docs/implementation/**`.
- Do not modify published task files.
- Do not modify:
  - `.env.local`;
  - `docker-compose.local.yml`;
  - `deployment/deploy-local.sh`.
- Do not add dependencies, routes, or migrations.
- Do not run full PHPUnit, full WP-02/WP-04, MySQL, Docker, npm build,
  Composer audit, deployment, or external integrations.
- Do not commit or push.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates:
  - stop as `awaiting-approval` if baseline ancestry is absent;
  - stop as `awaiting-approval` if overlapping work affects required files;
  - stop as `awaiting-approval` if installed Filament 5 cannot construct a
    focused test that reaches the action callback after a stale authorization
    or state change without redesigning production code;
  - stop as `awaiting-approval` if tests expose a broader architectural defect;
  - stop as `awaiting-approval` if a route, migration, dependency, production
    policy, or deployment change is required.

## Execution procedure

1. Validate this task.
2. Confirm baseline and repository state.
3. Inspect installed Filament 5 action-testing semantics.
4. Identify which current tests prove only visibility and which reach action
   execution.
5. Add focused execution-path tests.
6. Add focused claim-resolver tests.
7. Add focused login generic-failure and throttling tests.
8. Add focused safe-resource search and payload tests.
9. Correct the seeder informational message if reconciliation changes claims.
10. Run only the declared focused checks.
11. Update MVP-02 evidence and gap status from observed results.
12. Stop without continuing to MVP-03.

## Required focused tests

### 1. Execution-time action authorization

Tests must prove the callback is reached after the action was initially
eligible.

Use installed Filament 5 APIs to construct stale-state scenarios such as:

#### Permission revoked after mount

```text
administrator has member.account.manage
→ mount eligible suspend action
→ deactivate member.account.manage in persistent storage
→ clear request-scoped claim resolver state
→ execute mounted action
→ state unchanged
→ no success transition audit
```

Do not use an action that was already hidden at mount time as the only proof.

#### Target becomes self after mount

```text
mount suspend for a different active User
→ change the selected Member's server-side User relationship to the acting
  administrator in the disposable test database
→ execute mounted action
→ callback reloads the record
→ self-target is rejected
→ administrator remains active
→ no success transition audit
```

Use a schema-valid setup and restore/rollback through the test database.

#### Source state changes after mount

```text
mount suspend while target is active
→ change target to pending_activation before execution
→ execute mounted action
→ pending state remains unchanged
→ no success transition audit
```

Also test:

- blank reason;
- whitespace-only reason;
- reason longer than 1,000 characters;
- unexpected `user_id` and `target_state` fields cannot retarget;
- valid suspend;
- valid restore;
- trimmed reason is the audited reason;
- suspended Member portal access fails closed.

For every rejection, assert:

- target state;
- unrelated User state;
- absence of the corresponding success audit;
- generic notification or validation result as supported by Filament 5.

### 2. Claim resolver

Directly test one resolver instance and request-scoped behavior:

- active exact roles resolve;
- active exact permissions resolve;
- inactive roles are excluded;
- inactive permissions are excluded;
- blank claims are excluded;
- wildcard claims are excluded;
- unknown User ID returns empty role and permission lists;
- unavailable assignment storage returns empty lists;
- resolving User A and User B through one resolver does not mix claims;
- a new request/scoped resolver observes deactivation;
- request attributes, route values, form input, session values, and Livewire
  payload values cannot add claims.

Do not infer filtering merely because panel access still succeeds.

Assert the exact resolved arrays.

### 3. Admin login

For each rejected state, assert the same exact generic browser message:

- unknown email;
- wrong password;
- credential-valid User with no claims;
- role only;
- panel-access permission only;
- suspended;
- pending activation;
- login disabled;
- mandatory password replacement.

Also prove:

- every failure leaves the session unauthenticated;
- existing pair/origin/identifier throttling applies to `/admin/login`;
- submitted email and password values are absent from audit metadata;
- valid authorized login still succeeds and regenerates session ID.

### 4. Member resource and audit surface

Add focused Livewire tests proving:

- name search returns the intended Member and excludes another;
- MRN search returns the intended Member and excludes another;
- email search returns the intended Member and excludes another;
- actual encrypted NIK value is absent;
- actual NIK lookup digest is absent;
- actual address value is absent;
- actual emergency-contact value is absent;
- actual password hash and remember token are absent;
- actual forbidden audit metadata/claim/session/digest values are absent;
- create/edit/delete/replicate/bulk/export remain absent;
- unauthorized audit table returns zero records and does not serialize a known
  audit reason;
- authorized audit is newest first and excludes unrelated Member events.

### 5. Seeder operator message

When reconciliation inserts one or more missing claims:

- the command output must not state that claims were unchanged;
- it may report that missing bootstrap claims were reconciled;
- it must not print or reset the credential;
- a repeated no-op run may report that the credential and claims were
  unchanged.

Do not expose the credential in tests or evidence.

## Documentation

Update only:

```text
docs/mvp/evidence/mvp-02-shared-admin-shell-member-administration.md
docs/mvp/beta-gap-register.md
docs/mvp/roadmap.md
docs/mvp/work-package-status.md
```

Requirements:

- record the baseline and execution commit;
- record exact focused commands and observed results;
- distinguish execution-path tests from visibility-only tests;
- record claim-resolver, login-throttling, search, payload, and seeder-message
  evidence;
- list changed files accurately;
- record tests not run;
- do not claim production readiness;
- do not record plaintext credentials.

Treat `MVP-GAP-010` as unaccepted during this task.

Close it only after all required focused checks pass.

## Verification

- Method: Validate the task, run focused MVP-02 admin tests, run directly
  affected MVP-01 and filtered WP-02/WP-04 regressions only when production
  files change, run bounded Pint on changed PHP files, run `git diff --check`,
  inspect admin routes/providers/resources, and statically review changed
  files for protected values, credentials, metadata, session IDs, and
  untrusted claims.
- Expected result: Focused tests demonstrably reach execution-time
  authorization after stale permission/state/linkage changes, exact claim
  arrays and generic login failures are proven, throttling and safe search
  boundaries pass, seeder output is accurate, and no route, migration,
  dependency, deployment, or unrelated feature is added.

Required:

```bash
git diff --check
```

Do not run full suites, MySQL, Docker, deployment, npm build, Composer audit, or
external integrations.

## Acceptance criteria

- [ ] Baseline ancestry and repository state are confirmed.
- [ ] Published task validation passes.
- [ ] Existing overlapping work is preserved.
- [ ] No route, migration, dependency, or product scope is added.
- [ ] Permission-revocation test mounts while eligible and rejects at execution.
- [ ] Self-target test mounts while eligible and rejects after server-side linkage change.
- [ ] Source-state test mounts while active and rejects after pending-state change.
- [ ] Hidden-action no-op is not used as the sole execution-time proof.
- [ ] Blank, whitespace, and overlong reasons are tested.
- [ ] Unexpected payload cannot retarget the action.
- [ ] Valid suspend and restore remain successful and audited.
- [ ] Every rejected transition preserves state and creates no success audit.
- [ ] Exact claim arrays are asserted.
- [ ] Unknown User and unavailable storage fail closed.
- [ ] One resolver does not mix claims between Users.
- [ ] New request scope observes deactivation.
- [ ] Browser and Livewire claim values cannot elevate access.
- [ ] All admin-login rejection states use the same exact message.
- [ ] Admin-login throttling is tested.
- [ ] Failed login leaves no authenticated session.
- [ ] Credential input is absent from audit metadata.
- [ ] Name, MRN, and email search are tested.
- [ ] Actual protected/private values are absent from rendered and Livewire output.
- [ ] Unauthorized audit table serializes no known audit value.
- [ ] Authorized audit ordering and target isolation pass.
- [ ] Seeder reconciliation output is accurate and contains no credential.
- [ ] Focused tests pass.
- [ ] Required targeted regressions pass when production files change.
- [ ] Bounded Pint and `git diff --check` pass.
- [ ] Evidence contains only observed results.
- [ ] `MVP-GAP-010` closes only after all criteria pass.
- [ ] No deployment, commit, push, or plaintext credential is added.

## Stop conditions

Stop as `awaiting-approval` when:

- baseline ancestry is absent;
- overlapping work affects required files;
- installed Filament APIs cannot reach the execution callback in a bounded
  test;
- a production redesign is required solely to make the test possible;
- tests expose a broader MVP-01/WP-02/WP-04 regression;
- a route, migration, dependency, local-deployment, or production-policy
  change is required;
- a destructive or production-affecting operation is required.

## Output

- `succeeded`: all acceptance criteria and focused checks pass.
- `failed`: execution occurred but a required criterion failed.
- `blocked`: required tooling or evidence is unavailable.
- `awaiting-approval`: a stop condition or approval gate is reached.
- `exhausted`: iteration limit reached before completion.

## Final report

Report:

- baseline and execution commit;
- whether action callbacks were actually reached;
- stale permission, linkage, and state results;
- reason and retargeting results;
- exact claim resolver results;
- generic login and throttling results;
- search and protected-value results;
- audit results;
- seeder output behavior;
- changed files;
- focused tests and targeted regressions;
- formatting, routes, providers, and static review;
- documentation and gap status;
- tests not run;
- remaining limitations;
- confirmation that no route, migration, dependency, deployment, commit, push,
  or unrelated feature was added.

Do not include plaintext credentials.

Do not commit or push.

Stop after MVP-02 evidence closure.
