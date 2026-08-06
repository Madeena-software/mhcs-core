---
name: mhcs-core-mvp-04b-final-boundary-closure
description: Close the remaining MVP-04B identity-verification boundaries for evidence-unavailable portal decisions, lower-level asset grants, persisted account/portal authority, and focused regression evidence.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04B — Final Boundary Closure

## Objective

Close the remaining bounded MVP-04B defects on baseline:

`7074f2eea5e8c7368418dac966f111c4d96ddedd`

Preserve the accepted remediation directions already present in that baseline:

- Operator-owned trusted verification-case resolution;
- Member-side case, booking, site, schedule, arrival, and assignment binding;
- caller-controlled prior-photo inclusion removal;
- current-evidence revalidation before `matched`;
- database-backed one-open-case-per-Operator enforcement;
- claimant-row locking; and
- controlled shared-audit reason codes.

Required final flow:

```text
open case + current evidence available
→ protected comparison view
→ matched / mismatch_reported / insufficient_evidence

open case + required current evidence unavailable
→ protected view remains closed
→ matched unavailable
→ mismatch_reported / insufficient_evidence / cancel remain usable

any Operator asset grant
→ authenticatable account + portal and identity permissions
→ exact open case and assigned Operator
→ exact booking Member
→ allowed current slot or explicitly revealed historical profile photo only
```

Do not implement consent, `checked_in`, ticketing, queue stages, examination,
administrator dispute resolution, walk-ins, cash, imaging, FHIR, dependencies,
browser-platform work, CI, deployment, or production behavior.

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

Use `7074f2eea5e8c7368418dac966f111c4d96ddedd` as the closure baseline.

### Preflight

1. Resolve `$TARGET` canonically and confirm `Madeena-software/mhcs-core`.
2. Confirm ancestry from `cecbf8e...`, `463387f...`, `fa23f977...`, and `7074f2e...`.
3. Record branch, HEAD, staged, modified, untracked, and relevant ignored paths.
4. Preserve existing work; stop as `awaiting-approval` for overlap.
5. Validate this task.
6. Verify Codebase Memory MCP directly.
7. Verify ponytail directly and keep it active throughout execution.
8. Do not reset, clean, discard, stash, stage, commit, push, deploy, access production, or modify external systems.

### Codebase Memory MCP freshness

- Verify project/index identity, canonical root, branch, HEAD, and working-tree freshness.
- Use `no-op` when current, incremental refresh when stale, and initial full indexing only when missing.
- Rebuild only for corruption, incompatibility, wrong root, material parser/index changes, or failed incremental recovery.
- Record initial status, action, justification, and final status.
- After edits, verify changed symbols, callers, and paths.

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
- `$TARGET/.agents/tasks/mhcs-core-mvp-04b-identity-verification-remediation-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/evidence/mvp-04b-front-desk-identity-verification.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/app/Http/Controllers/Operator/PortalController.php`;
- `$TARGET/app/Models/User.php`;
- `$TARGET/app/Modules/Member/Application/Contracts/OperatorIdentityVerificationContract.php`;
- `$TARGET/app/Modules/Member/Application/Contracts/TrustedOperatorIdentityVerificationContextResolver.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberAuthorization.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberVerificationAssetService.php`;
- `$TARGET/app/Modules/Member/Application/Services/Mvp04OperatorIdentityVerificationService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorIdentityVerificationService.php`;
- `$TARGET/app/Modules/Operator/Infrastructure/TrustedOperatorIdentityVerificationContextResolver.php`;
- `$TARGET/app/Modules/Operator/OperatorServiceProvider.php`;
- `$TARGET/app/Shared/Context/AuthenticatedContext.php`;
- `$TARGET/app/Shared/Storage/AccessGrant.php`;
- `$TARGET/app/Shared/Storage/EncryptedLocalObjectStore.php`;
- `$TARGET/app/Shared/Storage/PrivateObjectStore.php`;
- `$TARGET/database/migrations/2026_08_06_000001_create_mvp04b_identity_verification_tables.php`;
- `$TARGET/database/migrations/2026_08_06_000002_add_mvp04b_identity_active_claim.php`;
- `$TARGET/resources/views/operator/identity-verification.blade.php`;
- `$TARGET/resources/views/operator/verification-worklist.blade.php`;
- `$TARGET/routes/web.php`;
- `$TARGET/tests/Feature/Operator/Mvp04bIdentityVerificationTest.php`;
- `$TARGET/tests/Feature/Operator/Mvp04OperatorPortalTest.php`;
- `$TARGET/tests/Operator/Mvp04OperatorFoundationTest.php`;
- `$TARGET/tests/Member/Wp04IdentityTest.php`;
- `$TARGET/tests/Security/Wp02SecurityTest.php`; and
- `$TARGET/tests/Architecture/FoundationArchitectureTest.php`.

Read the complete metadata and diffs for `463387f...`, `fa23f977...`, and `7074f2e...`.

## Reviewed findings

### 1. Evidence-unavailable cases are not usable through the normal portal

The Member protected view correctly fails closed when the current document or
profile photograph is unavailable. The Operator service converts that condition
to `identity_view_unavailable`, and the controller redirects to the worklist.
The normal page therefore cannot record `mismatch_reported`,
`insufficient_evidence`, or cancellation for that open case.

Required correction:

- keep the Member protected comparison view fail-closed;
- distinguish evidence-unavailable from authorization/case failure;
- render a safe open-case page for evidence-unavailable only;
- show no protected asset, raw identifier, object reference, exact-NIK lookup,
  or previous-photo action;
- hide or disable `matched`;
- keep mismatch, insufficient evidence, and cancel usable;
- keep site switching blocked until cancel or terminal decision;
- general authorization/case failures must still deny or redirect.

### 2. Lower-level Operator asset grant has an allowed-slot bypass

`MemberVerificationAssetService::grantForOperator()` revalidates the case and
Member, but any approved current verification-asset type is grantable. The
higher-level contract restricts slots, but the public lower-level method remains
directly container-resolvable.

Required correction:

- enforce the same allowed-slot rules inside the lower-level method, or replace
  it with a narrower internal contract that cannot represent unsupported slots;
- permit only current age-appropriate KTP/KIA, current profile photo, or an
  explicitly revealed approved historical profile photo;
- deny wrong-age current documents, unsupported current types, historical
  identity documents, unrelated assets, and unrevealed prior photos;
- preserve case, booking Member, permission, assignment, purpose, audience, and TTL checks.

### 3. Trusted case resolver omits persisted portal/account checks

The resolver rechecks role, identity permission, profile, site, assignments,
case, arrival, and booking, but does not independently recheck an authenticatable
User account or active persisted `operator.portal.access`.

Required correction:

- require an authenticatable User account;
- require active persisted `operator.portal.access` and `operator.identity.verify`;
- require both permissions in the supplied context;
- keep all existing exact case and assignment checks.

### 4. Focused evidence is incomplete for these remaining boundaries

Add direct feature/service tests for every correction in this task.

## Scope and constraints

Included:

- safe evidence-unavailable page state;
- matched-action gating;
- lower-level grant slot enforcement;
- resolver account/portal revalidation;
- focused tests and bounded evidence updates.

Excluded:

- state-enum changes;
- exact-NIK becoming mandatory;
- active-claim schema or migration changes;
- consent, check-in, tickets, queues, assessment, administrator resolution;
- dependencies, browser work, CI, deployment, production;
- task archive organization.

Do not modify `.agents/context/**`, `docs/implementation/**`, requirement
assignments/source digests, predecessor task contents, manifests/locks,
committed migrations, or archived-task paths. No migration is expected.

The archive moves in `fa23f977...` are owner-controlled pre-existing state; do
not alter or reinterpret them in this task.

Do not commit or push.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates:
  - stop as `blocked` if validation, mandatory tools, current indexing, or required verification is unavailable;
  - stop as `awaiting-approval` for missing ancestry or overlap;
  - stop before migration, dependency, weakened asset security, public links, later MVP scope, CI/deployment, or production work.

## Execution procedure

1. Validate task, ancestry, and worktree.
2. Verify ponytail and current Codebase Memory MCP graph.
3. Read required files.
4. Trace evidence-unavailable page handling.
5. Trace every caller of `grantForOperator`.
6. Trace trusted resolver authorization.
7. Implement the smallest coherent closure.
8. Add exact focused tests.
9. Run declared verification.
10. Refresh/verify graph and repeat caller/path/impact queries.
11. Update bounded evidence/status documents.
12. Inspect final diff for protected-data leakage and scope.
13. Run `git diff --check`.
14. Stop before later MVP-04 work.

## Required implementation

### Safe evidence-unavailable page

Use an explicit safe state equivalent to:

```text
case
safe_summary
evidence_status = unavailable
view = null
allowed_decisions = mismatch_reported, insufficient_evidence
```

The safe summary must come from an existing bounded arrival/Member projection.
No protected asset route may be rendered. `matched` must remain unavailable.
Mismatch, insufficient evidence, and cancel must revalidate authority and work
through normal portal forms.

### Lower-level asset grant

Every Operator grant must independently enforce:

```text
authenticatable actor
+ portal and identity permissions
+ exact open case
+ booking Member
+ approved allowed slot
+ current/reveal state
+ approved audience and TTL
```

No public/container-resolvable broader grant bypass may remain.

### Resolver authority

Require persisted authenticatable User, exact Operator role, active portal and
identity permissions, active profile/site/site assignment/schedule assignment,
exact open active claim, recorded arrival, and `arrived` booking.

### Tests

Add focused tests proving:

- missing current profile and missing/pending/rejected/wrong-age current document render a safe open page;
- safe page exposes no asset route, lookup, prior-photo action, or matched action;
- safe page permits mismatch, insufficient evidence, and cancel;
- terminal failure clears the claim and releases site switching;
- general permission/case/assignment failures do not receive the safe fallback;
- direct `grantForOperator()` denies wrong-age current documents, unsupported
  current types, historical identity documents, unrelated assets, and
  unrevealed prior photos;
- direct contract access fails after portal-permission revocation;
- direct contract access fails for suspended, login-disabled, and
  mandatory-password-change accounts;
- accepted happy path, prior-photo fallback, MVP-04A, WP-04, WP-02, and
  architecture regressions remain green.

## Documentation and evidence

Update only:

```text
$TARGET/docs/mvp/evidence/mvp-04b-front-desk-identity-verification.md
$TARGET/docs/mvp/roadmap.md
$TARGET/docs/mvp/beta-gap-register.md
$TARGET/docs/mvp/work-package-status.md
```

Record the closure baseline, safe evidence-unavailable behavior, grant hardening,
resolver account/portal checks, mandatory-tool evidence, exact tests/checks,
unrun checks, and residual risks. Keep MVP-04 and relevant gaps/Work Packages
open or partial.

## Verification

- Method: Validate the task, verify repository and mandatory-tool preflight, trace and close the evidence-unavailable portal path and every lower-level Operator asset-grant caller, revalidate persisted account and portal authority in the trusted case resolver, run each declared focused test and static check separately, verify the final Codebase Memory MCP graph and affected callers, inspect protected-data output, and run `git diff --check`.
- Expected result: The task validates, an open case with missing current evidence can be completed through mismatch or insufficient-evidence actions without exposing protected assets or permitting matched, every Operator asset grant independently enforces the allowed slot and trusted case, suspended or portal-revoked accounts fail closed at the cross-module boundary, all focused regressions pass, and no excluded scope is introduced.

Run:

```text
vendor/bin/phpunit tests/Feature/Operator/Mvp04bIdentityVerificationTest.php
vendor/bin/phpunit tests/Feature/Operator/Mvp04OperatorPortalTest.php
vendor/bin/phpunit tests/Operator/Mvp04OperatorFoundationTest.php
vendor/bin/phpunit tests/Feature/Admin/Mvp04OperatorAdministrationTest.php
vendor/bin/phpunit tests/Member/Wp04IdentityTest.php --filter 'asset|identifier|verification|grant'
vendor/bin/phpunit tests/Security/Wp02SecurityTest.php
vendor/bin/phpunit tests/Architecture/FoundationArchitectureTest.php
```

Also run task validation, PHP syntax checks, Pint on changed PHP, route/binding
inspection, public-method/caller inspection for Operator grants, targeted
NIK/reason/object-key/grant leakage searches, final MCP caller/path/impact
verification, and `git diff --check`.

Record exact command, exit status, tests, assertions, duration when available,
warnings, skips, and failures.

Do not run Pest, Playwright, browser tests, full PHPUnit, complete Work Package
suites, migrations, dependency installation, CI, deployment, external
integrations, or production checks.

## Acceptance criteria

- [ ] Preflight, validation, ancestry, MCP, and ponytail checks pass.
- [ ] Current graph uses the least expensive valid freshness action.
- [ ] Protected Member view remains fail-closed on missing evidence.
- [ ] Safe portal fallback is limited to evidence-unavailable only.
- [ ] Safe fallback permits mismatch, insufficient evidence, and cancel.
- [ ] Safe fallback exposes no matched action or protected asset route.
- [ ] Terminal failure clears the active claim and releases site switching.
- [ ] Lower-level Operator grant independently enforces allowed slots.
- [ ] No broader public/container-resolvable grant bypass remains.
- [ ] Resolver rechecks authenticatable account and portal permission.
- [ ] Direct revocation/account-state tests fail closed.
- [ ] Focused and bounded regressions pass.
- [ ] Evidence remains bounded and accurate.
- [ ] MVP-04 and relevant gaps/Work Packages remain open/partial.
- [ ] No migration, dependency, browser, CI, deployment, production, archive,
      commit, or push work occurs.

## Stop conditions

Stop as `blocked` when task validation, mandatory tools, current indexing, or
required verification tooling is unavailable.

Stop as `awaiting-approval` when ancestry is absent, work overlaps, a migration
or dependency is required, asset security must be weakened, or later
MVP/production scope becomes necessary.

Stop as `failed` for an uncorrectable in-scope verification failure.
Stop as `exhausted` after the finite iteration limit without all criteria.

## Output

Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
`exhausted`.

An unverified patch, missing tool evidence, protected-data leakage, or model
output alone is unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report baseline, execution HEAD, worktree, changed files, boundary changes,
MCP/ponytail evidence, exact tests/checks, unrun checks, residual risks, and
readiness for owner-controlled commit.

After the owner supplies a commit SHA, review it against this task and
`7074f2eea5e8c7368418dac966f111c4d96ddedd` before accepting the final MVP-04B
baseline.

## Final report

Report outcome; target, baseline, and execution HEAD; MCP initial/action/final
evidence; ponytail and subagent usage; safe evidence-unavailable behavior;
lower-level grant hardening; resolver account/portal checks; files changed;
exact tests/checks; documentation updates; unrun checks; residual risks;
readiness for owner commit; and confirmation that excluded scope, archive
moves, commit, and push did not occur.

Do not include raw NIK, credentials, asset bytes, object keys, access grants,
private prompts, hidden reasoning, or complete transcripts.

Do not commit or push.

Stop after the final bounded MVP-04B closure.
