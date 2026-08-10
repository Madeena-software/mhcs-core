---
name: mhcs-core-mvp-04b-front-desk-identity-verification
description: Implement the bounded MVP-04B front-desk identity-verification slice for arrived Members, including exact-NIK lookup, protected current identity views, prior-photo fallback, atomic verification claims, and terminal Operator decisions without consent, check-in, ticketing, or queue expansion.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04B — Front-Desk Identity Verification

## Objective

Implement the next bounded MVP-04 slice on accepted baseline:

`cecbf8e5e6d944cf58a7b73c2db14177f1748b5f`

Required flow:

```text
arrived Member in current-site worklist
→ assigned Operator atomically starts one verification case
→ Operator may confirm physical NIK through exact protected lookup
→ current KTP/KIA and latest approved profile photograph are shown
→ previous approved profile photographs remain hidden by default
→ previous photographs require an explicit insufficient-latest-photo action
→ Operator records matched, mismatch_reported, or insufficient_evidence
→ later consent/check-in work may consume matched only
```

This task implements only:

- bounded portions of `OPR-016..OPR-017`;
- bounded portions of `MEM-073..MEM-075`;
- Operator verification claim and decision state;
- Member-owned protected identity lookup/view contracts; and
- privacy-safe portal and evidence updates.

This task does **not** implement:

- consent;
- `checked_in`;
- ticket issue/printing/reprint;
- queue stages;
- basic examination;
- administrator mismatch resolution;
- walk-ins or cash;
- X-ray, NPZ, DICOM, Cornerstone, MPIPS, Image Gateway, or FHIR; or
- production readiness.

MVP-04, WP-11, WP-12, WP-17, and WP-07 remain incomplete.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `codebase-memory-mcp`
  - `ponytail`
- Ordered model preferences: None.
- Require preferred model: `false`

Codebase Memory MCP and ponytail are mandatory. Configuration alone is not
runtime evidence.

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Context and evidence

Use:

`cecbf8e5e6d944cf58a7b73c2db14177f1748b5f`

as the implementation baseline.

### Preflight

Before planning or editing:

1. Resolve `$TARGET` canonically.
2. Confirm `Madeena-software/mhcs-core`.
3. Confirm baseline ancestry.
4. Record branch, HEAD, staged, modified, untracked, and relevant ignored paths.
5. Preserve existing work.
6. Stop as `awaiting-approval` for overlapping work.
7. Validate the task.
8. Verify Codebase Memory MCP directly.
9. Verify ponytail directly and keep it active throughout execution.
10. Do not reset, clean, discard, stash, stage, commit, push, deploy, access
    production, or modify external systems.

### Codebase Memory MCP freshness

For canonical `$TARGET`:

- verify project/index identity;
- verify current branch, HEAD, and relevant working-tree freshness;
- use `no-op` when current;
- use incremental refresh when stale;
- create an initial full index when missing;
- rebuild fully only for corruption, incompatibility, wrong root, material
  parser/index configuration invalidation, or failed incremental recovery;
- record initial state, action, justification, and final state;
- after implementation, verify changed symbols and paths are present.

Do not rebuild a current index.

### Read completely before planning or editing

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/context/ui-language.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/docs/mvp/evidence/mvp-04-operator-foundation-arrival.md`;
- `$TARGET/docs/member/wp-04-identity-evidence.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04-operator-foundation-arrival-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04-operator-arrival-remediation-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04a-arrival-boundary-closure-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04a-tool-evidence-traceability-closure-v1.md`;
- `$TARGET/.agents/tasks/archive/mhcs-core-wp-04-member-identity-accounts-guardians-recovery-v1.md`;
- `$TARGET/config/mhcs.php`;
- `$TARGET/routes/web.php`;
- `$TARGET/app/Http/Controllers/Operator/PortalController.php`;
- `$TARGET/app/Modules/Member/Application/Contracts/OperatorAttendanceContract.php`;
- `$TARGET/app/Modules/Member/Application/Contracts/TrustedOperatorSiteContextResolver.php`;
- `$TARGET/app/Modules/Member/Application/Services/Mvp04AttendanceService.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberAuthorization.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberVerificationAssetService.php`;
- `$TARGET/app/Modules/Member/Domain/Models/Member.php`;
- `$TARGET/app/Modules/Member/Domain/Models/MemberVerificationAsset.php`;
- `$TARGET/app/Modules/Member/MemberServiceProvider.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorActiveSiteService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAttendanceService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorArrivalService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorShiftAssignmentService.php`;
- `$TARGET/app/Modules/Operator/Domain/Models/OperatorArrival.php`;
- `$TARGET/app/Modules/Operator/OperatorServiceProvider.php`;
- `$TARGET/app/Shared/Context/AuthenticatedContext.php`;
- `$TARGET/app/Shared/Security/ProtectedIdentifierService.php`;
- `$TARGET/app/Shared/Storage/AccessGrant.php`;
- `$TARGET/app/Shared/Storage/PrivateObjectStore.php`;
- `$TARGET/database/migrations/2026_08_04_000008_create_member_identity_tables.php`;
- `$TARGET/database/migrations/2026_08_05_000004_create_mvp04_operator_foundation_tables.php`;
- `$TARGET/resources/views/operator/verification-worklist.blade.php`;
- `$TARGET/tests/Member/Wp04IdentityTest.php`;
- `$TARGET/tests/Security/Wp02SecurityTest.php`;
- `$TARGET/tests/Feature/Operator/Mvp04OperatorPortalTest.php`;
- `$TARGET/tests/Operator/Mvp04OperatorFoundationTest.php`;
- `$TARGET/tests/Feature/Admin/Mvp04OperatorAdministrationTest.php`; and
- `$TARGET/tests/Architecture/FoundationArchitectureTest.php`.

Read the relevant complete diff and metadata for:

- `f49da5991b21b9a13abb435539db1955362ef639`;
- `cecbf8e5e6d944cf58a7b73c2db14177f1748b5f`.

### Inspect as needed after graph discovery

Inspect additional callers, providers, migrations, factories, seeders, tests,
Blade partials, and installed Laravel/Filament source only when graph or test
evidence shows relevance.

Codebase Memory MCP does not replace direct reading of the files above.

## Source-derived boundaries

### Ownership

Member Core owns:

- NIK protection and exact lookup;
- Member identity;
- KTP/KIA and profile-photograph assets;
- asset metadata/history;
- private object access; and
- safe identity projections.

Operator Core owns:

- arrival;
- front-desk verification case/claim;
- Operator decision and transition history;
- site-scoped worklist; and
- Operator audit.

Operator Core must not persist:

- raw/encrypted NIK;
- NIK digest;
- asset bytes;
- private object keys;
- access-grant secrets;
- permanent asset URLs; or
- unrestricted Member models.

### Human decision only

Do not add OCR, face matching, biometric scoring, document-authenticity
inference, Dukcapil calls, AI identity decisions, or automatic matching.

### Exact NIK lookup

- Full NIK is entered from physical KTP/KIA.
- Use POST body only.
- Never retain it in URL, session, flash input, log, audit, event, exception,
  Operator table, or operation result.
- Member Core canonicalizes/hashes through the accepted service.
- Resolve at most one eligible `arrived` booking for the trusted active site and
  assigned schedule.
- Unknown, cross-site, ineligible, or unauthorized inputs use one controlled
  non-enumerating failure.
- Never return raw NIK.

### Protected verification view

- Show current approved age-appropriate KTP/KIA.
- Show latest approved current profile photograph first.
- Previous approved profile photographs are hidden initially.
- Previous photographs require:
  - the same open case;
  - explicit fallback action;
  - mandatory bounded reason;
  - new short-lived purpose-bound access; and
  - audit evidence.
- No public disk, permanent URL, raw object key, generic asset browser, or
  unrelated-case access.

### Decisions

Allowed Operator decisions:

```text
matched
mismatch_reported
insufficient_evidence
```

- `matched` is a human front-desk result only.
- It does not create consent, check-in, ticket, assessment, or Encounter state.
- Mismatch and insufficient evidence block later check-in.
- Operator cannot reverse a terminal decision.
- Administrator resolution is deferred.
- Mismatch and insufficient evidence require a bounded reason.

## Scope and constraints

### Included

- `operator.identity.verify`;
- Member exact-NIK lookup contract;
- Member protected identity-view contract;
- current document/latest photo access;
- explicit prior-photo fallback;
- Operator verification case and append-only transitions;
- atomic claim;
- one open case per Operator and one active claimant per arrival;
- terminal Operator decisions and cancellation;
- open-case site-switch blocker;
- bounded Operator portal;
- one forward migration;
- focused tests and evidence.

### Excluded

Do not implement:

- consent or consent scan;
- `checked_in`;
- ticket allocation, print, or reprint;
- queue ticket/stages;
- basic examination;
- station labels;
- administrator dispute resolution;
- optional on-site photograph;
- walk-in, cash, no-show, shift close, LCD, notifications;
- imaging, FHIR, dependencies, browser platform, CI, deployment, production.

Do not modify:

- `.agents/context/**`;
- `docs/implementation/**`;
- accepted requirement assignments or source digests;
- predecessor tasks;
- Composer/npm manifests or lock files;
- existing browser files;
- accepted historical migrations.

Do not commit or push.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates:
  - stop as `blocked` if validation or mandatory tools fail;
  - stop as `blocked` if the graph cannot be made current;
  - stop as `awaiting-approval` if ancestry is absent or work overlaps;
  - stop as `awaiting-approval` if safe asset access requires weakened grants,
    public links, real storage configuration, a dependency, OCR, biometrics, or
    external identity systems;
  - stop as `awaiting-approval` for destructive/incompatible migration;
  - stop before consent, check-in, ticket, queue, admin dispute, clinical,
    imaging, FHIR, CI, deployment, production, or destructive work.

## Execution procedure

1. Validate the task and repository state.
2. Verify ponytail and Codebase Memory MCP freshness.
3. Read required authority and source files.
4. Map arrival/worklist, active-site, NIK, asset-grant, object-access, audit,
   and test paths.
5. Confirm requirement and ownership boundaries.
6. Add bounded Member contracts.
7. Add Operator verification persistence and service.
8. Add exact-NIK and protected-view behavior.
9. Add prior-photo fallback.
10. Add active-case site-switch blocking.
11. Add bounded routes/controller/Blade UI.
12. Add focused tests.
13. Run declared verification.
14. Refresh/verify the graph and repeat affected path/impact queries.
15. Update bounded evidence/status documents.
16. Inspect final diff for scope and protected-data leakage.
17. Run `git diff --check`.
18. Stop before consent/check-in/ticket/queue work.

## Required implementation

### 1. Authorization

Add exact permission:

```text
operator.identity.verify
```

Require, at every operation:

- authenticated User;
- exact Operator role;
- portal permission;
- identity-verification permission;
- active profile;
- active site;
- active site assignment;
- active assignment to target schedule.

Recheck at execution time. Administrator-only authority grants no identity
access. Dual-role accounts act only through valid Operator context.

### 2. Member contract

Add a narrow Member-owned contract, for example:

```text
OperatorIdentityVerificationContract
```

Equivalent naming is acceptable.

Required operations:

- exact NIK lookup;
- current verification view;
- previous profile-photo reveal;
- one authorized inline asset retrieval.

The contract must receive trusted context, stable `operator_site_id`, schedule,
booking/case references as applicable, explicit-offset time, and bounded
purpose.

It must:

- enforce Operator role/permission and trusted local/stable site;
- use `ProtectedIdentifierService`;
- require eligible site/schedule booking in `arrived`;
- return only safe operational identity fields;
- reuse approved asset/grant/private-object boundaries;
- audit lookup/view/reveal/retrieval by actor, site, booking, purpose, and
  operation;
- fail without enumeration.

Safe projection may include only:

- booking/schedule IDs;
- bounded Member identity reference;
- display name;
- MRN;
- masked NIK;
- site;
- service;
- booking state;
- safe asset-slot metadata.

### 3. Protected asset response

Asset retrieval requires the same active case, Operator, site, schedule,
permission, purpose, and unexpired grant.

Response requirements:

- inline content;
- `Cache-Control: no-store, private`;
- no public/permanent URL;
- no raw object key;
- no download action;
- no Operator persistence of bytes or grants;
- failure after expiry, cancellation, terminal decision, site change,
  assignment revocation, or permission revocation.

Use synthetic bytes only.

### 4. Operator persistence

Add one forward migration for:

```text
operator_identity_verifications
operator_identity_verification_events
```

Equivalent names are acceptable.

Case must preserve:

- UUID;
- arrival, booking, schedule, site, and Operator-profile references;
- state;
- started and decided times;
- bounded reason/category where required;
- operation/idempotency identity;
- timestamps.

States:

```text
open
matched
mismatch_reported
insufficient_evidence
cancelled
```

Rules:

- one case per arrival;
- one active claimant per arrival;
- one open case per Operator;
- start/claim is transactional and locked;
- same-operation replay is idempotent;
- conflicting replay fails;
- cancelled case may be reclaimed through one explicit guarded transition;
- terminal decisions cannot be reopened by Operator;
- raw NIK/assets are never stored.

Append immutable events for start, cancel, prior-photo reveal, and terminal
decision.

### 5. Operator service and portal

Add one bounded service, for example:

```text
OperatorIdentityVerificationService
```

Public operations may be organized as:

```text
start
lookupByNik
currentView
revealPreviousPhotos
retrieveAsset
decideMatched
reportMismatch
reportInsufficientEvidence
cancel
```

All authority is server-derived.

Portal behavior:

- worklist exposes safe verification state;
- unclaimed arrived row exposes start action;
- competing claim shows controlled unavailable state;
- active page shows safe Member summary, current document, latest photo,
  exact-NIK form, prior-photo fallback, terminal decisions, and cancel;
- previous-photo/mismatch/insufficient actions require confirmation and reason;
- full NIK is never prefilled or echoed;
- no consent/check-in/ticket/queue/admin-resolution action appears;
- no asset download or permanent link appears.

### 6. Site switching

An `open` verification case is unresolved Operator work.

- Switching away is denied and audited.
- Current site is preserved.
- Audit contains no Member identity, NIK, booking detail, asset ID/key, or grant.
- Cancel or terminal decision releases the Operator claim.
- Existing arrival-confirmation blocker remains unchanged.
- Recorded arrival without open case remains non-blocking.

### 7. Tests

Add focused tests for:

- role/permission/profile/site/site-assignment/shift-assignment enforcement;
- administrator-only denial and valid dual-role behavior;
- runtime permission/assignment revocation;
- matching, unknown, wrong-site, wrong-schedule, not-arrived NIK lookup;
- absence of raw NIK from response/session/URL/audit/log/database;
- current document/latest photo only by default;
- explicit audited prior-photo reveal;
- rejected/pending/non-current/unrelated/expired/wrong-purpose asset denial;
- atomic competing claims;
- one-open-case-per-Operator;
- idempotent replay/conflict;
- cancellation and terminal transitions;
- mandatory reasons;
- terminal decision immutability;
- no consent/check-in/ticket/queue mutation;
- open-case site-switch blocking and release;
- MVP-04A, WP-04 identity, WP-02 security, and architecture regressions.

## Documentation and evidence

Create:

```text
$TARGET/docs/mvp/evidence/mvp-04b-front-desk-identity-verification.md
```

Update only as required:

```text
$TARGET/docs/mvp/roadmap.md
$TARGET/docs/mvp/beta-gap-register.md
$TARGET/docs/mvp/work-package-status.md
$TARGET/docs/mvp/evidence/mvp-04-operator-foundation-arrival.md
```

Record:

- baseline;
- task path/version;
- MCP initial/action/final evidence;
- ponytail evidence;
- requirement subset;
- ownership/schema/permission;
- NIK and asset privacy;
- case/decision/site-switch behavior;
- exact commands/results;
- changed files;
- unrun checks;
- deferred scope and residual risks.

Keep MVP-04 and relevant gaps/Work Packages open/partial. Do not claim consent,
check-in, ticket, queue, clinical, imaging, FHIR, CI, deployment, or production
completion.

## Verification

- Method: Validate the task, execute the bounded implementation procedure, run each declared focused test and static check separately, inspect the authorization and protected-data boundaries, verify final Codebase Memory MCP graph freshness and affected call paths, and run `git diff --check`.
- Expected result: The task validates successfully, all bounded acceptance criteria and required checks pass, raw NIK and protected asset data remain confined to approved boundaries, no excluded scope is added, and the final worktree contains only the intended MVP-04B changes.

Run new focused tests separately, then:

```text
vendor/bin/phpunit tests/Feature/Operator/Mvp04OperatorPortalTest.php
vendor/bin/phpunit tests/Operator/Mvp04OperatorFoundationTest.php
vendor/bin/phpunit tests/Feature/Admin/Mvp04OperatorAdministrationTest.php
vendor/bin/phpunit tests/Member/Wp04IdentityTest.php --filter 'asset|identifier|verification|grant'
vendor/bin/phpunit tests/Security/Wp02SecurityTest.php
vendor/bin/phpunit tests/Architecture/FoundationArchitectureTest.php
```

Also run:

- task validation;
- PHP syntax checks for changed PHP;
- Pint on changed PHP;
- route inspection;
- SQLite migration/test inspection;
- container-binding/method-visibility inspection;
- targeted raw-NIK leakage search;
- targeted object-key/permanent-URL leakage search;
- final MCP path/impact verification;
- `git diff --check`.

Record each test command separately with command, exit status, tests, assertions,
duration when available, warnings, skips, and failures.

Required MCP evidence:

- project/index status;
- structural discovery for current and new boundaries;
- paths for worklist → case start, NIK → Member lookup, view → asset grant,
  prior-photo reveal, decision, and site-switch blocker;
- caller analysis for asset retrieval and terminal decisions;
- contract/service impact analysis;
- final graph freshness and changed-symbol visibility.

Do not run:

- Pest/Playwright/browser;
- full PHPUnit;
- complete Work Package suites;
- MySQL/Docker unless a migration incompatibility requires approval;
- npm build;
- Composer audit;
- dependency installation;
- external integrations;
- deployment or production checks.

## Acceptance criteria

- [ ] Preflight, validation, ancestry, and worktree checks pass.
- [ ] Codebase Memory MCP is current using the least expensive valid action.
- [ ] ponytail remains active and subagent usage is recorded.
- [ ] Member retains NIK/asset ownership; Operator retains case/decision ownership.
- [ ] Exact persisted `operator.identity.verify` is enforced at execution time.
- [ ] Active role/profile/site/site-assignment/shift-assignment are required.
- [ ] Administrator-only and unauthorized/cross-site access fail closed.
- [ ] Exact NIK uses POST and is never returned, logged, audited, flashed, or
      persisted.
- [ ] Lookup is site/schedule/booking/purpose scoped and non-enumerating.
- [ ] Current KTP/KIA and latest photo are shown first.
- [ ] Previous photos require explicit reason, short-lived access, and audit.
- [ ] No public disk, permanent URL, raw key, download action, or generic asset
      browser exists.
- [ ] Claim is atomic, idempotent, and conflict-safe.
- [ ] One arrival has one active claimant; one Operator has one open case.
- [ ] Terminal decisions are exactly matched, mismatch reported, and
      insufficient evidence.
- [ ] Required reasons and terminal immutability are enforced.
- [ ] Open case blocks site switching; cancel/terminal decision releases it.
- [ ] No consent, checked-in, ticket, queue, admin-dispute, clinical, or imaging
      state is created.
- [ ] Focused and bounded regression tests pass.
- [ ] Syntax, Pint, routes, migration, bindings, privacy searches, MCP checks,
      and `git diff --check` pass.
- [ ] Evidence/status documents remain bounded and accurate.
- [ ] No dependency, browser, CI, deployment, production, commit, or push work
      occurs.

## Stop conditions

Stop as `blocked` when validation, mandatory tools, current indexing, or
required local verification tooling is unavailable.

Stop as `awaiting-approval` when:

- ancestry is absent or work overlaps;
- a dependency, weakened grant, public link, real storage, OCR, biometric, or
  external identity service is required;
- migration is destructive/incompatible;
- privacy/legal/exceptional identity policy must be invented;
- consent, check-in, ticket, queue, administrator dispute, clinical, imaging,
  FHIR, CI, deployment, or production scope becomes necessary;
- bounded regressions reveal a broader accepted defect.

Stop as `failed` for an uncorrectable in-scope verification failure.

Stop as `exhausted` after the finite iteration limit without all criteria.

## Output

Allowed outcomes:

- `succeeded`
- `failed`
- `blocked`
- `awaiting-approval`
- `exhausted`

Missing mandatory-tool evidence, protected-data leakage, an unverified patch,
or model output alone is unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report baseline, execution HEAD, worktree, changed files, contracts/schema,
mandatory-tool evidence, exact tests/checks, unrun checks, residual risks, and
readiness for owner-controlled commit.

After the owner supplies a commit SHA, review it against this task and baseline
before accepting it as the next bounded baseline.

Do not predict the future SHA.

## Final report

Report:

- runtime/model when verifiable;
- outcome;
- canonical target, baseline, and execution HEAD;
- capabilities;
- MCP initial state/action/queries/final state;
- ponytail evidence and subagent usage;
- files changed;
- permission and ownership behavior;
- NIK and asset privacy;
- case/decision/site-switch lifecycle;
- migration;
- exact tests and checks;
- documentation updates;
- tests not run;
- remaining MVP-04 scope;
- residual risks;
- readiness for owner-controlled commit;
- confirmation that no excluded scope, commit, or push occurred.

Do not include raw NIK, credentials, asset bytes, object keys, access grants,
private prompts, hidden reasoning, or complete transcripts.

Do not commit or push.

Stop after this bounded MVP-04B slice.
