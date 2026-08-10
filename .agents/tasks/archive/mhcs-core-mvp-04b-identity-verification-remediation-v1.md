---
name: mhcs-core-mvp-04b-identity-verification-remediation
description: Remediate the MVP-04B identity-verification candidate by closing protected-asset contract bypasses, enforcing current evidence before matched decisions, making one-open-case-per-Operator concurrency-safe, sanitizing shared audit, and completing required negative tests.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04B — Identity Verification Remediation

## Objective

Remediate candidate commit:

`463387f7eba1eb0420a931256da8db8e4adfdedf`

against accepted baseline:

`cecbf8e5e6d944cf58a7b73c2db14177f1748b5f`

Required corrected flow:

```text
arrived Member
→ assigned Operator atomically claims one verification case
→ Member Core independently validates the exact open case
→ current approved KTP/KIA and latest approved profile photograph are required
→ prior profile photographs require the explicit audited fallback command
→ protected asset retrieval is bound to the booking's Member and allowed slot
→ Operator records one terminal decision
→ one Operator can never hold two open cases, including concurrent starts
→ free-text reasons never enter shared audit
```

This task remediates MVP-04B only. Do not implement consent, `checked_in`, ticketing, queue stages, basic examination, administrator mismatch resolution, walk-ins, cash, imaging, FHIR, CI, deployment, or production behavior.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `codebase-memory-mcp`
  - `ponytail`
- Ordered model preferences: None.
- Require preferred model: `false`

Codebase Memory MCP and ponytail are mandatory and require direct runtime evidence.

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Context and evidence

Use `463387f7eba1eb0420a931256da8db8e4adfdedf` as the remediation baseline.

### Preflight

1. Resolve `$TARGET` canonically.
2. Confirm `Madeena-software/mhcs-core`.
3. Confirm ancestry from both accepted baseline and candidate commit.
4. Record branch, HEAD, staged, modified, untracked, and relevant ignored paths.
5. Preserve existing work; stop as `awaiting-approval` for overlap.
6. Validate this task.
7. Verify Codebase Memory MCP directly.
8. Verify ponytail directly and keep it active throughout execution.
9. Do not reset, clean, discard, stash, stage, commit, push, deploy, access production, or modify external systems.

### Codebase Memory MCP freshness

- Verify project/index identity and canonical root.
- Verify current branch, HEAD, and relevant working-tree freshness.
- Use `no-op` when current, incremental refresh when stale, initial full indexing when missing, and full rebuild only for corruption, incompatibility, wrong root, material parser/index changes, or failed incremental recovery.
- Record initial status, action, justification, and final status.
- After edits, verify changed symbols and affected call paths.
- Do not rebuild a current index.

### Read completely before planning or editing

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/context/ui-language.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04b-front-desk-identity-verification-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/evidence/mvp-04b-front-desk-identity-verification.md`;
- `$TARGET/docs/mvp/evidence/mvp-04-operator-foundation-arrival.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/app/Http/Controllers/Operator/PortalController.php`;
- `$TARGET/app/Modules/Member/Application/Contracts/OperatorIdentityVerificationContract.php`;
- `$TARGET/app/Modules/Member/Application/Contracts/TrustedOperatorSiteContextResolver.php`;
- `$TARGET/app/Modules/Member/Application/Services/Mvp04OperatorIdentityVerificationService.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberAuthorization.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberVerificationAssetService.php`;
- `$TARGET/app/Modules/Member/MemberServiceProvider.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorIdentityVerificationService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorActiveSiteService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorShiftAssignmentService.php`;
- `$TARGET/app/Modules/Operator/Infrastructure/TrustedOperatorSiteContextResolver.php`;
- `$TARGET/app/Modules/Operator/OperatorServiceProvider.php`;
- `$TARGET/database/migrations/2026_08_06_000001_create_mvp04b_identity_verification_tables.php`;
- `$TARGET/resources/views/operator/identity-verification.blade.php`;
- `$TARGET/resources/views/operator/verification-worklist.blade.php`;
- `$TARGET/routes/web.php`;
- `$TARGET/tests/Feature/Operator/Mvp04bIdentityVerificationTest.php`;
- `$TARGET/tests/Feature/Operator/Mvp04OperatorPortalTest.php`;
- `$TARGET/tests/Operator/Mvp04OperatorFoundationTest.php`;
- `$TARGET/tests/Member/Wp04IdentityTest.php`;
- `$TARGET/tests/Security/Wp02SecurityTest.php`;
- `$TARGET/tests/Architecture/FoundationArchitectureTest.php`.

Read the complete metadata and diff for `cecbf8e...` and `463387f...`.

## Reviewed findings

### 1. Protected Member contract is not independently case-bound

Current Member operations validate Operator role, permission, and site but do not independently prove that the case exists, is open, belongs to the actor, and matches the supplied booking/schedule/site. `currentView(..., includePrevious: true)` also permits direct prior-photo metadata access without the explicit reveal command. `retrieveAsset()` does not bind the supplied Member to the validated booking and can permit historical non-profile assets through its low-level grant path.

Required correction:

- add a Member-owned trusted verification-case resolver contract implemented by Operator Core, analogous to the trusted-site resolver;
- resolve case authority server-side for every Member operation;
- remove caller-controlled prior-photo inclusion from current view;
- bind asset Member to booking Member;
- allow historical assets only for approved non-current profile photographs after a reveal event for that exact open case;
- deny historical KTP/KIA and unrelated assets.

### 2. `matched` can succeed without required current evidence

Current view may return no current document or latest approved profile photo, while the decision service can still record `matched`.

Required correction:

- current view fails closed when either required current approved asset is absent;
- `matched` revalidates current age-appropriate document and latest current profile photo at execution time;
- pending, rejected, non-current, wrong-age, revoked, or unavailable evidence denies `matched` without case/event/audit mutation;
- mismatch and insufficient-evidence remain available with bounded reasons;
- exact-NIK lookup remains optional unless source authority explicitly requires it.

### 3. One-open-case-per-Operator is race-prone

The current service checks for another open case but does not serialize starts for different arrivals owned by the same Operator. Two concurrent transactions can both observe no open case.

Required correction:

- add one forward remediation migration;
- enforce a portable database-level active-claim invariant, preferably a nullable unique `active_claim_operator_profile_id` set only while `open`;
- lock the stable Operator profile row during start/reclaim;
- clear the active-claim key atomically on cancel or terminal decision;
- preserve one-case-per-arrival and operation idempotency;
- add deterministic conflict and rollback tests.

Do not edit the committed MVP-04B migration.

### 4. Free-text reasons can enter shared audit

Current reveal, cancel, mismatch, and insufficient-evidence paths pass user-controlled free text into append-only audit. A user can place NIK-like or object-key-like protected text into audit.

Required correction:

- shared audit receives controlled reason/category codes only;
- free text, when retained, remains only in bounded Operator case/event records;
- free text must not enter shared audit metadata/reason, logs, events, exceptions, or redirects;
- reject control characters and retain the existing length bound;
- add leakage regression tests.

### 5. Required negative and concurrency tests are incomplete

The candidate focused test file has only three broad tests and does not prove the task's full authorization, asset, privacy, concurrency, replay, and revocation matrix.

## Scope and constraints

### Included

- trusted case-context resolver;
- Member contract hardening;
- match-readiness enforcement;
- database-safe active-claim invariant;
- shared-audit sanitization;
- missing focused tests;
- bounded evidence correction.

### Excluded

Do not add consent, check-in, ticket, queue, basic examination, administrator dispute resolution, walk-in/cash, imaging/FHIR, dependencies, browser work, CI, deployment, or production behavior.

Do not modify `.agents/context/**`, `docs/implementation/**`, requirement assignments/source digests, predecessor tasks, manifests/locks, browser files, or committed historical migrations. Use one new forward migration only. Do not commit or push.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates:
  - stop as `blocked` if validation, mandatory tools, current indexing, or required local verification is unavailable;
  - stop as `awaiting-approval` for missing ancestry, overlapping work, dependency changes, destructive/incompatible migration, weakened protected access, public links, external identity services, later MVP scope, CI/deployment, or production work.

## Execution procedure

1. Validate task, ancestry, and worktree.
2. Verify ponytail and current Codebase Memory MCP graph.
3. Read required files and trace bypass paths.
4. Add trusted case-context resolver and binding.
5. Add one forward active-claim migration.
6. Harden Member current-view, reveal, and retrieval methods.
7. Enforce current evidence before `matched`.
8. Sanitize shared audit reasons.
9. Add complete focused tests.
10. Run declared verification.
11. Refresh graph incrementally and repeat callers/path/impact queries.
12. Update bounded evidence/status documents.
13. Inspect final diff for scope and protected-data leakage.
14. Run `git diff --check`.
15. Stop before later MVP-04 work.

## Required implementation

### Trusted case-context resolver

Add a Member-owned contract such as:

```text
TrustedOperatorIdentityVerificationContextResolver
```

Implement it in Operator Core and bind it through the Operator provider. It must return a bounded assertion only when actor, role, permission, active profile/site/site assignment/schedule assignment, recorded arrival, open case, actor ownership, site/schedule/booking/arrival correspondence, and `arrived` booking state all match. It may expose only safe opaque references and whether prior photos were explicitly revealed for the case.

### Member contract hardening

- Remove `includePrevious` from public current view.
- Current view returns current document and latest profile only.
- Reveal is the only prior-photo metadata path.
- Retrieval resolves Member from the validated booking and rejects caller-supplied cross-member values.
- Current document must be approved, current, and age-appropriate.
- Current profile must be approved and current.
- Historical retrieval requires approved non-current profile plus resolver-confirmed reveal.
- Historical identity documents are denied.
- Every operation revalidates exact open case context.

### Match readiness

Before `matched`, revalidate trusted open case, permission, profile, site, assignments, arrival, booking state, current document, and latest current profile. Perform all checks before mutation. Preserve booking `arrived` and create no later-scope state.

### Race-safe active claim

Add a forward migration with nullable unique active-claim key or an equivalent portable constraint. Start/reclaim must lock the Operator profile, set the key atomically, fail one competitor, roll back case/event/audit together, clear the key on cancel/terminal decision, and restore it on reclaim.

### Audit sanitization

Shared audit uses controlled codes only, such as:

```text
latest_photo_insufficient
identity_mismatch_reported
identity_evidence_insufficient
identity_case_cancelled
```

Equivalent controlled codes are acceptable. Free text remains local only.

### Tests

Add focused tests for:

- direct Member current-view without valid open case;
- arbitrary case, wrong actor, and case/booking/schedule/site mismatch;
- direct prior-photo access without reveal;
- historical identity-document denial;
- cross-member asset denial;
- permission/assignment revocation after case start;
- missing current document/profile and pending/rejected/non-current/wrong-age evidence denying matched;
- failed matched causing no partial terminal state;
- database rejection of two open claims for one Operator;
- claimant-row locking and deterministic competing-start failure;
- active-claim clear/reclaim behavior;
- free-text NIK-like and object-key-like values absent from shared audit/logs;
- administrator-only denial, valid dual-role behavior, inactive profile/site assignment, wrong site, missing shift assignment, unknown/wrong-site/wrong-schedule/not-arrived NIK, expired/wrong-purpose assets, replay/conflict, site-switch release, and required regressions.

## Documentation and evidence

Update only:

```text
$TARGET/docs/mvp/evidence/mvp-04b-front-desk-identity-verification.md
$TARGET/docs/mvp/roadmap.md
$TARGET/docs/mvp/beta-gap-register.md
$TARGET/docs/mvp/work-package-status.md
```

Record the remediation baseline, findings corrected, resolver path, ownership, match-readiness checks, active-claim invariant, audit sanitization, MCP/ponytail evidence, exact commands/results, unrun checks, and residual risks. Keep MVP-04 and relevant gaps/Work Packages open/partial.

## Verification

- Method: Validate the task, verify repository and mandatory-tool preflight, trace and remediate each reviewed authorization/privacy/concurrency path, run each declared focused test and static check separately, inspect shared audit for synthetic protected text, verify the final Codebase Memory MCP graph and affected callers, and run `git diff --check`.
- Expected result: The task validates, Member protected-asset operations are bound to one trusted open Operator case and booking Member, matched decisions require current approved evidence, concurrent starts cannot create two open cases for one Operator, free-text identity reasons never enter shared audit, all focused and bounded regression checks pass, and no excluded scope is introduced.

Run the remediation test file separately, then:

```text
vendor/bin/phpunit tests/Feature/Operator/Mvp04bIdentityVerificationTest.php
vendor/bin/phpunit tests/Feature/Operator/Mvp04OperatorPortalTest.php
vendor/bin/phpunit tests/Operator/Mvp04OperatorFoundationTest.php
vendor/bin/phpunit tests/Feature/Admin/Mvp04OperatorAdministrationTest.php
vendor/bin/phpunit tests/Member/Wp04IdentityTest.php --filter 'asset|identifier|verification|grant'
vendor/bin/phpunit tests/Security/Wp02SecurityTest.php
vendor/bin/phpunit tests/Architecture/FoundationArchitectureTest.php
```

Also run task validation, PHP syntax checks, Pint on changed PHP, SQLite forward-migration verification, route/binding/method inspection, targeted NIK/reason/object-key leakage searches, final MCP caller/path/impact verification, and `git diff --check`.

Record exact command, exit status, tests, assertions, duration when available, warnings, skips, and failures.

Do not run Pest, Playwright, browser tests, full PHPUnit, complete Work Package suites, dependency installation, CI, deployment, external integrations, or production checks.

## Acceptance criteria

- [ ] Preflight, validation, ancestry, MCP, and ponytail checks pass.
- [ ] Current graph uses the least expensive valid freshness action.
- [ ] Trusted case-context resolver is Operator-owned and Member-consumed.
- [ ] Every Member operation validates one exact open case.
- [ ] Caller-controlled prior-photo inclusion is removed.
- [ ] Asset retrieval binds booking Member, asset Member, allowed slot, and case.
- [ ] Historical KTP/KIA and unrevealed prior photos are denied.
- [ ] Missing/invalid current evidence denies matched without partial state.
- [ ] One-open-case-per-Operator has a database-level race-safe invariant.
- [ ] Start/reclaim serializes on a stable claimant row.
- [ ] Cancel/terminal clears active claim atomically.
- [ ] Free-text reasons are absent from shared audit/logs.
- [ ] Shared audit uses controlled reason codes only.
- [ ] All missing authorization, lookup, asset, concurrency, privacy, replay, site-switch, and regression tests pass.
- [ ] Evidence remains bounded and accurate.
- [ ] MVP-04 and relevant gaps/Work Packages remain open/partial.
- [ ] No excluded, dependency, browser, CI, deployment, production, commit, or push work occurs.

## Stop conditions

Stop as `blocked` when task validation, mandatory tools, current indexing, or required verification tooling is unavailable.

Stop as `awaiting-approval` when ancestry is absent, work overlaps, a dependency or destructive migration is required, protected access would need weaker security, or later MVP/production scope becomes necessary.

Stop as `failed` for an uncorrectable in-scope verification failure.

Stop as `exhausted` after the iteration limit without all criteria.

## Output

Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or `exhausted`.

An unverified patch, missing mandatory-tool evidence, privacy leakage, or model output alone is unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report baseline, execution HEAD, worktree, changed files, resolver/contract and migration changes, MCP/ponytail evidence, exact tests/checks, unrun checks, residual risks, and readiness for owner-controlled commit.

After the owner supplies the commit SHA, review it against this task and `463387f7eba1eb0420a931256da8db8e4adfdedf` before acceptance.

## Final report

Report outcome, target/baseline/HEAD, MCP initial/action/queries/final state, ponytail evidence, subagent usage, resolver and contract behavior, match-readiness behavior, concurrency invariant, audit sanitization, changed files/migrations, exact tests/checks, documentation updates, checks not run, residual risks, and readiness for owner commit.

Do not include raw NIK, credentials, asset bytes, keys, grants, private prompts, hidden reasoning, or complete transcripts.

Do not commit or push.

Stop after MVP-04B remediation.
