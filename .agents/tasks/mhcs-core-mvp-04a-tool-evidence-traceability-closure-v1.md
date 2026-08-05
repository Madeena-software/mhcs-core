---
name: mhcs-core-mvp-04a-tool-evidence-traceability-closure
description: Verify the committed MVP-04A boundary-closure candidate with Codebase Memory MCP and ponytail, rerun bounded evidence, correct commit traceability, and establish the next accepted baseline without changing product behavior.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04A — Tool Evidence and Traceability Closure

## Objective

Verify the committed MVP-04A boundary-closure candidate:

`f49da5991b21b9a13abb435539db1955362ef639`

against the preceding accepted remediation baseline:

`2e08eae74e49b0ba54461ba8787a0ec8e0ece062`

The product candidate already implements the bounded MVP-04A behavior:

```text
assigned Operator
→ authenticates through the shared User foundation
→ selects one authorized active site
→ views an assigned site-scoped attendance list
→ prepares an explicit physical-arrival confirmation
→ confirms the session-bound arrival command
→ Member booking transitions from confirmed to arrived
→ recorded arrival remains visible in the bounded verification worklist
```

It also contains the following reviewed closure behavior:

- active, unconsumed arrival confirmation blocks switching away from the current
  site;
- cancelled, expired, malformed, stale, and consumed confirmation state does
  not create a permanent site lock;
- recorded arrivals do not block site switching;
- no synthetic `resolved` arrival lifecycle is required;
- local Operator-site identity and stable `operator_site_id` correspondence are
  verified through an Operator-owned resolver;
- attendance query, arrival resolution, and Member booking transition enforce
  the trusted site boundary;
- the public Operator arrival surface exposes confirmation preparation,
  confirmed execution, and cancellation only;
- the low-level arrival mutation is not a public application command.

The preceding execution task required Codebase Memory MCP and ponytail, but the
committed repository evidence does not record sufficient direct invocation
evidence for those tools. It also contains stale working-tree/HEAD statements
and inconsistent test-count reporting.

This task is therefore an **evidence and traceability closure**.

Required observable outcome:

```text
f49da5991b21b9a13abb435539db1955362ef639
→ verified with a current Codebase Memory MCP graph
→ verified under observable ponytail activation
→ focused tests and static checks rerun
→ exact tool operations and results recorded
→ stale commit and test-count evidence corrected
→ accepted as the bounded MVP-04A product baseline when every criterion passes
```

This task does not complete MVP-04, WP-11, WP-12, or WP-17.

Identity verification, consent, `checked_in`, ticket issuance, queue stages,
basic examination, clinical workflow, walk-ins, X-ray execution, NPZ, DICOM,
Cornerstone, MPIPS, Image Gateway, FHIR, CI, deployment, and production
readiness remain outside this closure.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `codebase-memory-mcp`
  - `ponytail`
- Ordered model preferences: None.
- Require preferred model: `false`

Codebase Memory MCP and ponytail are mandatory execution tools for this task.

A Markdown declaration, configuration file, ignored directory, or installed
package name is not proof that either tool is available or active. The
executing runtime must provide direct observable evidence.

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Context and evidence

Use:

`f49da5991b21b9a13abb435539db1955362ef639`

as the verification baseline.

### Preflight

Before planning, querying, editing, or running verification:

1. Resolve `$TARGET` to a canonical absolute path.
2. Confirm the expected `Madeena-software/mhcs-core` repository.
3. Confirm baseline ancestry.
4. Record:
   - current branch;
   - current HEAD;
   - staged files;
   - modified files;
   - untracked files; and
   - relevant ignored paths.
5. Preserve all existing work.
6. Stop as `awaiting-approval` if existing work overlaps required documentation
   or verification files.
7. Do not reset, clean, discard, stash, stage, commit, push, deploy, or access
   production.
8. Validate this published task using:

   ```text
   $TARGET/.agents/skills/agent-task/scripts/validate_task.py
   ```

9. Stop as `blocked` if the validator or required Python runtime is unavailable.
10. Verify Codebase Memory MCP availability directly.
11. Verify ponytail installation and active runtime state directly.
12. Stop as `blocked` if either mandatory tool cannot be verified.

### Codebase Memory MCP index-freshness policy

Every task must ensure that the Codebase Memory MCP graph reflects the canonical
repository and current repository state.

The executing agent must:

1. verify whether an index exists for canonical `$TARGET`;
2. verify that the index belongs to canonical `$TARGET`;
3. verify freshness against:
   - current branch;
   - current HEAD; and
   - relevant working-tree changes;
4. select the least expensive valid action:
   - `no-op` when the existing index is current;
   - incremental refresh when the graph is stale or changed symbols are absent;
   - initial full indexing when no index exists;
   - full rebuild only when:
     - the index is corrupt;
     - the index is incompatible;
     - the index belongs to another repository root;
     - material parser/index configuration changes invalidate it; or
     - incremental recovery fails;
5. record the initial status, chosen action, justification, and resulting status;
6. after any documentation changes, verify that watcher or incremental-refresh
   processing reflects relevant changed files;
7. repeat the required structural and call-path queries against the final
   current graph.

Do not perform an unnecessary full rebuild.

Stop as `blocked` when the graph cannot be made current for canonical `$TARGET`.

### Read completely before planning or verification

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/review-code/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04-operator-foundation-arrival-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04-operator-arrival-remediation-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04a-arrival-boundary-closure-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/docs/mvp/evidence/mvp-04-operator-foundation-arrival.md`;
- `$TARGET/composer.json`;
- `$TARGET/phpunit.xml`;
- `$TARGET/.gitignore`;
- `$TARGET/app/Http/Controllers/Operator/PortalController.php`;
- `$TARGET/app/Modules/Member/Application/Contracts/OperatorAttendanceContract.php`;
- `$TARGET/app/Modules/Member/Application/Contracts/TrustedOperatorSiteContextResolver.php`;
- `$TARGET/app/Modules/Member/Application/Services/Mvp04AttendanceService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorActiveSiteService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorArrivalConfirmationService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorArrivalService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAttendanceService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorShiftAssignmentService.php`;
- `$TARGET/app/Modules/Operator/Infrastructure/OperatorActiveSiteResolver.php`;
- `$TARGET/app/Modules/Operator/Infrastructure/TrustedOperatorSiteContextResolver.php`;
- `$TARGET/app/Modules/Operator/OperatorServiceProvider.php`;
- `$TARGET/resources/views/operator/arrival-confirmation.blade.php`;
- `$TARGET/resources/views/operator/attendance.blade.php`;
- `$TARGET/routes/web.php`;
- `$TARGET/tests/Feature/Operator/Mvp04OperatorPortalTest.php`;
- `$TARGET/tests/Operator/Mvp04OperatorFoundationTest.php`;
- `$TARGET/tests/Feature/Admin/Mvp04OperatorAdministrationTest.php`;
- `$TARGET/tests/Feature/Member/Mvp01MemberAccessTest.php`;
- `$TARGET/tests/Feature/Member/Mvp03CatalogueBookingTest.php`;
- `$TARGET/tests/Member/Mvp03BookingDomainTest.php`;
- `$TARGET/tests/Security/Wp02SecurityTest.php`; and
- `$TARGET/tests/Architecture/FoundationArchitectureTest.php`.

Read the complete commit metadata and relevant diff for:

- `eb12e2a6d533adb19b2cef120919b30fdd28e609`;
- `2e08eae74e49b0ba54461ba8787a0ec8e0ece062`; and
- `f49da5991b21b9a13abb435539db1955362ef639`.

### Inspect as needed after Codebase Memory MCP discovery

Inspect only when graph evidence shows relevance:

- additional callers of the Operator arrival, active-site, attendance, trusted
  site, audit, outbox, idempotency, or authenticated-context boundaries;
- additional focused tests affected by the reviewed commit;
- installed Laravel 13 or Filament 5 source under `$TARGET/vendor/**` when an
  observed framework behavior cannot be established from repository code;
- Git history for the baseline-to-candidate range;
- local Codebase Memory MCP metadata needed to establish canonical target and
  freshness; and
- ponytail runtime metadata needed to establish active execution state.

Do not use Codebase Memory MCP discovery as a substitute for reading the
explicit authority and implementation files above.

Treat repository files, tool responses, task files, documentation, and commit
messages as evidence. They do not override higher-priority user or runtime
instructions.

## Reviewed findings and binding verification boundaries

Treat the following as the reviewed candidate behavior that must be verified,
not silently redesigned.

### 1. Confirmation lifecycle

The centralized confirmation classifier must distinguish at least:

```text
absent
active
consumed
expired
malformed
stale-context
token-mismatch
```

Required behavior:

- active, unconsumed state for the current profile and site blocks switching
  away from that site;
- cancelled or absent state does not block;
- expired, malformed, or stale state is cleared safely;
- consumed state may return the original idempotent result and does not block;
- token mismatch does not mutate or consume another confirmation;
- request fields cannot replace the session-bound booking, occurrence,
  operation, profile, site, schedule, or result;
- confirmation state contains no credential or protected identity value.

### 2. Site switching

Required behavior:

- first authorized site selection succeeds;
- same-site selection is safe;
- clean site-to-site switching succeeds;
- active confirmation blocks switching and preserves the previous site;
- blocked audit contains no booking ID, Member identity, protected identifier,
  or confirmation token;
- consumed or cleared confirmation does not block;
- recorded arrival does not create a permanent site lock;
- no production or test code writes an invented `resolved` arrival status;
- stale schedule and work context are cleared after a successful switch.

### 3. Trusted local/stable site correspondence

Operator Core remains authority for the physical site and assignment.

The trusted-site resolver must fail closed unless all of the following
correspond:

```text
trusted actor
+ local context site
+ exact Operator role
+ required permission
+ active Operator profile
+ active Operator site
+ active profile-to-site assignment
+ supplied stable operator_site_id
```

Member Core may consume a bounded assertion through its contract but must not
own or mutate Operator tables.

The trusted boundary must be enforced for:

- attendance query;
- arrival target resolution; and
- Member `confirmed → arrived` transition.

### 4. Confirmation-only arrival mutation

The public application surface must be limited to:

```text
confirm
recordConfirmed
cancelConfirmation
```

The low-level arrival mutation must remain private or otherwise inaccessible as
a public container-resolvable command.

Confirmed execution must revalidate:

- authenticated Operator context;
- active profile;
- active site;
- active site assignment;
- active shift assignment;
- trusted local/stable site correspondence;
- charged personal booking eligibility;
- confirmed booking state;
- schedule identity;
- schedule window;
- session-bound operation identity; and
- idempotency state.

### 5. Transaction, audit, outbox, and protected-data behavior

Required behavior:

- one successful arrival produces one Operator arrival record;
- Member booking transition, booking-status event, audit, outbox, and
  idempotency remain coherent;
- replay returns the original result without duplicate mutation;
- failure creates no partial success state;
- bounded failure audit uses controlled categories;
- protected identity, credentials, point balances, raw ledger data, and
  confirmation tokens remain absent from Operator-facing output and bounded
  audit metadata.

## Scope and constraints

### Included

- mandatory-tool verification;
- current Codebase Memory MCP index verification;
- structural, caller/callee, path, and impact analysis;
- ponytail active-runtime verification;
- read-only product review;
- focused PHPUnit re-execution;
- static and repository checks;
- correction of tool evidence;
- correction of commit and baseline traceability;
- correction of stale or contradictory test counts;
- bounded MVP-04A acceptance documentation.

### Excluded

Do not add, remove, or alter:

- production behavior;
- public or internal routes;
- database schema or migrations;
- authentication behavior;
- authorization claims or permissions;
- active-site semantics;
- confirmation/session semantics;
- attendance eligibility;
- arrival state-transition rules;
- audit/outbox/idempotency contracts;
- Filament resources or Blade behavior;
- dependencies or lock files;
- Pest, Playwright, or browser-platform files;
- CI workflows;
- identity verification;
- consent;
- `checked_in`;
- ticket issuance;
- queues;
- basic examination or clinical workflow;
- walk-ins or cash;
- NPZ, DICOM, Cornerstone, MPIPS, or Image Gateway behavior;
- FHIR;
- external adapters;
- deployment or production configuration.

No production-code change is expected or permitted by this task.

If the tool-assisted review or focused verification reveals a product defect,
stop as `awaiting-approval`. Report the defect with:

- severity;
- exact path and symbol;
- relevant caller/callee path;
- impact;
- failing or missing evidence; and
- proposed bounded remediation direction.

Do not repair the defect within this task.

Do not modify:

- `.agents/context/**`;
- `docs/implementation/**`;
- accepted requirement assignments;
- source digests;
- Composer/npm manifests or lock files;
- migrations;
- production source or tests; or
- published task files.

Only the documentation files explicitly permitted below may be changed.

Do not commit or push.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates:
  - stop as `blocked` if the task validator is unavailable or fails;
  - stop as `blocked` if Codebase Memory MCP cannot be directly verified;
  - stop as `blocked` if the Codebase Memory MCP index cannot be made current;
  - stop as `blocked` if ponytail cannot be directly verified as active;
  - stop as `blocked` if required local verification tooling is unavailable;
  - stop as `awaiting-approval` if baseline ancestry is absent;
  - stop as `awaiting-approval` if existing work overlaps required files;
  - stop as `awaiting-approval` if a product defect is found;
  - stop as `awaiting-approval` if production-code or test modification appears
    necessary;
  - stop before dependency, migration, browser, CI, external, destructive,
    deployment, or production work.

## Execution procedure

1. Validate the published task.
2. Resolve `$TARGET` and record repository state.
3. Verify baseline ancestry.
4. Verify Codebase Memory MCP availability.
5. Verify index ownership and freshness.
6. Perform the least expensive valid index action.
7. Record initial and resulting index state.
8. Verify ponytail installation and active runtime state.
9. Read every required file completely.
10. Inspect all three relevant commits and the complete candidate diff.
11. Execute the required Codebase Memory MCP structural queries.
12. Trace the confirmation path.
13. Trace the site-switch path.
14. Trace the trusted-site boundary.
15. Identify callers of the low-level arrival mutation.
16. Execute dependency and impact analysis.
17. Verify every binding product invariant.
18. Run the focused PHPUnit commands.
19. Run the static and repository checks.
20. Verify that the final Codebase Memory MCP graph remains current.
21. Repeat relevant structural and call-path queries.
22. Update only the permitted evidence and status documents.
23. Re-read the documentation diff for accuracy and scope.
24. Run `git diff --check`.
25. Stop without starting MVP-04B.

## Required Codebase Memory MCP verification

Record the exact MCP operation names, arguments or query text, and concise
observed results.

At minimum perform:

### 1. Index and project status

Verify:

- canonical repository root;
- project/index identity;
- current branch;
- current HEAD;
- working-tree awareness when supported;
- index freshness;
- chosen index action;
- final index status.

### 2. Structural discovery

Locate and record the graph symbols for:

- `PortalController::confirmArrival`;
- `PortalController::recordArrival`;
- `PortalController::cancelArrival`;
- `OperatorArrivalService::confirm`;
- `OperatorArrivalService::recordConfirmed`;
- private low-level arrival mutation;
- `OperatorArrivalConfirmationService::inspect`;
- `OperatorActiveSiteService::select`;
- unresolved-work confirmation check;
- `OperatorAttendanceService::query`;
- `OperatorAttendanceContract`;
- `TrustedOperatorSiteContextResolver`;
- Operator resolver implementation;
- `Mvp04AttendanceService::query`;
- `Mvp04AttendanceService::resolveBookingForArrival`;
- `Mvp04AttendanceService::transitionConfirmedToArrived`;
- provider binding; and
- focused tests.

### 3. Confirmation execution path

Trace:

```text
attendance form
→ operator.arrivals.confirm route
→ PortalController::confirmArrival
→ OperatorArrivalService::confirm
→ Member arrival resolution
→ confirmation-state storage
→ confirmation view
→ operator.arrivals.store route
→ PortalController::recordArrival
→ OperatorArrivalService::recordConfirmed
→ confirmation inspection
→ private low-level mutation
→ Member confirmed-to-arrived transition
→ audit/outbox/idempotency result
→ authoritative attendance redirect
```

### 4. Site-switch path

Trace:

```text
site-selection request
→ PortalController::selectSite
→ OperatorActiveSiteService::select
→ current trusted site resolution
→ confirmation-state inspection
→ active confirmation blocker or safe clearing
→ session active-site update
→ stale work-context clearing
→ bounded audit
```

### 5. Trusted-site path

Trace:

```text
Operator portal context
→ local Operator site
→ stable operator_site_id
→ Member attendance contract
→ Operator-owned TrustedOperatorSiteContextResolver
→ active profile/site/assignment checks
→ attendance or arrival operation
```

### 6. Caller and visibility analysis

Confirm:

- no public `record` method exists on `OperatorArrivalService`;
- the low-level mutation is private;
- no route, controller, resource, command, job, listener, or service bypasses
  `recordConfirmed`;
- no direct caller supplies unconfirmed mutation values.

### 7. Impact analysis

Identify all direct dependents of:

- `OperatorAttendanceContract`;
- `TrustedOperatorSiteContextResolver`;
- `OperatorArrivalConfirmationService`;
- `OperatorArrivalService`; and
- `OperatorActiveSiteService`.

Confirm that the candidate change surface is bounded to the reviewed MVP-04A
interfaces and tests.

### 8. Final graph verification

After documentation edits:

- verify the graph remains current;
- verify product symbols and call paths are unchanged;
- verify no new public unconfirmed command appears;
- verify no production source or test file changed.

Generic statements such as “Codebase Memory MCP was used” are insufficient.

## Required ponytail verification

Record direct evidence for:

- ponytail installation or runtime identity;
- active state at task start;
- active state during analysis;
- active state during verification;
- active state at task completion;
- whether subagents were used;
- whether ponytail remained active for every subagent.

Do not infer ponytail activity from a configuration file alone.

Stop as `blocked` when continuous active-state evidence cannot be established.

## Focused verification

Use the repository's accepted PHPUnit invocation style.

Run separately:

```text
vendor/bin/phpunit tests/Feature/Operator/Mvp04OperatorPortalTest.php
vendor/bin/phpunit tests/Operator/Mvp04OperatorFoundationTest.php
vendor/bin/phpunit tests/Feature/Admin/Mvp04OperatorAdministrationTest.php
vendor/bin/phpunit tests/Feature/Member/Mvp01MemberAccessTest.php
vendor/bin/phpunit tests/Feature/Member/Mvp03CatalogueBookingTest.php
vendor/bin/phpunit tests/Member/Mvp03BookingDomainTest.php
vendor/bin/phpunit tests/Security/Wp02SecurityTest.php
vendor/bin/phpunit tests/Architecture/FoundationArchitectureTest.php
```

For every command record:

- exact command;
- exit status;
- test count;
- assertion count;
- duration when available;
- warning count;
- skipped count; and
- failure details when present.

Do not combine unrelated command totals into an ambiguous aggregate.

Do not copy counts from prior evidence.

The current run is authoritative for this closure.

### Static and repository checks

Run:

- published-task validation;
- PHP syntax checks for PHP files changed between
  `2e08eae74e49b0ba54461ba8787a0ec8e0ece062` and
  `f49da5991b21b9a13abb435539db1955362ef639`;
- Pint check or equivalent non-mutating formatting verification for the same
  changed PHP files;
- route inspection for the bounded Operator arrival routes;
- reflection or static inspection of `OperatorArrivalService` public methods;
- service-container binding inspection;
- search confirming no `operator_arrivals.status = resolved` production or test
  write exists;
- search confirming no public unconfirmed arrival route or method exists;
- Codebase Memory MCP final graph verification; and
- `git diff --check`.

Do not modify product files to satisfy formatting in this evidence-only task.

A formatting defect that would require product modification is an
`awaiting-approval` finding.

## Documentation and evidence

Only the following files may be changed:

```text
$TARGET/docs/mvp/evidence/mvp-04-operator-foundation-arrival.md
$TARGET/docs/mvp/roadmap.md
$TARGET/docs/mvp/beta-gap-register.md
$TARGET/docs/mvp/work-package-status.md
```

### Evidence document requirements

The evidence must distinguish:

```text
initial MVP-04A implementation
→ eb12e2a6d533adb19b2cef120919b30fdd28e609

MVP-04A remediation
→ 2e08eae74e49b0ba54461ba8787a0ec8e0ece062

MVP-04A boundary-closure product candidate
→ f49da5991b21b9a13abb435539db1955362ef639

current evidence-verification execution
→ current working tree; no execution commit created by the agent

accepted baseline
→ established only after owner-created commit review
```

Record:

- canonical target;
- baseline ancestry;
- branch and HEAD observed during this execution;
- pre-existing worktree state;
- Codebase Memory MCP availability evidence;
- initial index status;
- canonical index root;
- current branch/HEAD freshness evidence;
- index action and justification;
- exact structural queries;
- exact call-path queries;
- exact caller and impact queries;
- final index status;
- ponytail installation/runtime evidence;
- ponytail active-state evidence throughout execution;
- subagent usage;
- exact PHPUnit commands and observed results;
- exact static checks and observed results;
- documentation files changed;
- product source and tests unchanged;
- checks not run;
- residual risks;
- bounded acceptance conclusion.

Correct or remove:

- statements that `f49da599...` is only an uncommitted working-tree change;
- statements that current HEAD remains `2e08eae...` after the closure commit;
- conflicting assertion totals;
- duplicated test totals;
- unsupported Codebase Memory MCP or ponytail claims;
- any statement implying full MVP-04 or production readiness.

### Roadmap, gap register, and Work Package ledger

These documents must:

- identify `f49da5991b21b9a13abb435539db1955362ef639` as the committed
  boundary-closure candidate;
- identify the current evidence-verification state truthfully;
- avoid predicting a future owner-created commit;
- keep MVP-04 incomplete;
- keep `MVP-GAP-009` open;
- keep `MVP-GAP-012` open;
- keep `MVP-GAP-024` open;
- keep WP-11 `partially-implemented`;
- keep WP-12 `partially-implemented`;
- keep WP-17 `partially-implemented`;
- keep WP-07 bounded to the consumed attendance/arrival contract;
- retain all deferred queue, check-in, consent, identity, clinical, imaging,
  FHIR, deployment, and production scope.

## Verification

- Method: Validate the task; verify repository state and ancestry; verify a
  current Codebase Memory MCP graph using the least expensive valid update;
  record exact structural, call-path, caller, and impact queries; verify
  ponytail active state; run the eight focused PHPUnit commands separately; run
  static and repository checks; update only bounded documentation; verify final
  graph freshness; run `git diff --check`.
- Expected result: Product source and tests remain unchanged, all bounded
  product invariants are confirmed, all focused verification passes, direct
  Codebase Memory MCP and ponytail evidence is recorded, stale traceability and
  test-count statements are corrected, and the owner can create a commit for
  subsequent review as the candidate accepted MVP-04A evidence baseline.

Required:

```bash
git diff --check
```

Do not run:

- Pest;
- Playwright;
- browser tests;
- full PHPUnit;
- complete Work Package suites;
- MySQL or Docker conformance;
- npm build;
- dependency installation or upgrade;
- Composer audit;
- external integrations;
- deployment;
- production checks; or
- production operations.

## Acceptance criteria

### Preflight and tools

- [ ] Published task validation passes.
- [ ] Canonical `$TARGET` is resolved.
- [ ] Expected repository identity is confirmed.
- [ ] Baseline ancestry is confirmed.
- [ ] Existing work is preserved.
- [ ] Codebase Memory MCP availability is directly verified.
- [ ] ponytail installation and active state are directly verified.
- [ ] The index belongs to canonical `$TARGET`.
- [ ] The index reflects current branch and HEAD.
- [ ] Relevant working-tree freshness is verified.
- [ ] The least expensive valid index action is used.
- [ ] A current index is not rebuilt unnecessarily.
- [ ] Initial and final index status are recorded.
- [ ] ponytail remains active for the full execution and every subagent.
- [ ] Subagent usage is recorded.
- [ ] No fallback workflow is used.

### Product invariants

- [ ] Confirmation-state classification is centralized.
- [ ] Active unconsumed confirmation blocks leaving the current site.
- [ ] Blocked switch preserves the previous active site.
- [ ] Cancelled, expired, malformed, stale, and consumed states do not create a
      permanent lock.
- [ ] Recorded arrivals do not block site switching.
- [ ] No `resolved` arrival status write exists.
- [ ] Trusted local/stable site correspondence is enforced.
- [ ] Exact role, permission, profile, site, and assignment checks remain.
- [ ] Attendance query enforces the trusted-site boundary.
- [ ] Arrival resolution enforces the trusted-site boundary.
- [ ] Member booking transition enforces the trusted-site boundary.
- [ ] No public unconfirmed arrival method or route exists.
- [ ] No caller bypasses `recordConfirmed`.
- [ ] Confirmation execution revalidates authoritative state.
- [ ] Replay remains idempotent.
- [ ] Failure produces no partial success state.
- [ ] Protected data and confirmation tokens remain absent from bounded output
      and audit metadata.

### Verification and evidence

- [ ] Required structural graph queries are executed and recorded.
- [ ] Required confirmation call-path query is executed and recorded.
- [ ] Required site-switch call-path query is executed and recorded.
- [ ] Required trusted-site call-path query is executed and recorded.
- [ ] Caller and method-visibility analysis is executed and recorded.
- [ ] Dependency and impact analysis is executed and recorded.
- [ ] Final graph verification is executed and recorded.
- [ ] All eight focused PHPUnit commands pass.
- [ ] Test and assertion counts are exact and non-contradictory.
- [ ] Static and repository checks pass.
- [ ] `git diff --check` passes.
- [ ] Product source and tests are unchanged.
- [ ] Only the permitted documentation files change.
- [ ] Evidence identifies all three committed MVP-04A SHAs accurately.
- [ ] Stale working-tree and HEAD claims are corrected.
- [ ] Unsupported tool claims are removed or replaced with direct evidence.
- [ ] MVP-04 and relevant gaps/Work Packages remain open or partial.
- [ ] Checks not run are listed accurately.
- [ ] No dependency, migration, browser, CI, external, deployment, production,
      commit, or push work occurs.

## Stop conditions

Stop as `blocked` when:

- the task validator is unavailable or fails;
- Codebase Memory MCP is unavailable;
- the index cannot be associated with canonical `$TARGET`;
- the index cannot be made current;
- ponytail is unavailable or cannot be verified as active;
- ponytail activity cannot be verified throughout execution;
- required local verification tooling is unavailable.

Stop as `awaiting-approval` when:

- baseline ancestry is absent;
- existing work overlaps required files;
- a focused test fails because of a product defect;
- Codebase Memory MCP reveals a product defect;
- production source or test modification appears necessary;
- a documentation correction would require changing accepted requirements or
  source digests;
- dependency, migration, browser, CI, external, destructive, deployment, or
  production work is required.

Stop as `failed` when:

- a required command executes and fails for a non-approval-gated reason that
  cannot be corrected within the permitted documentation-only scope.

Stop as `exhausted` when:

- the finite iteration limit is reached without satisfying every acceptance
  criterion.

## Output

Allowed outcomes:

- `succeeded`
- `failed`
- `blocked`
- `awaiting-approval`
- `exhausted`

Treat an unverified patch, missing mandatory-tool evidence, inconsistent test
counts, or model output alone as unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

The final report must provide:

- task baseline;
- current execution HEAD;
- working-tree state;
- exact documentation files changed;
- confirmation that product source and tests are unchanged;
- exact Codebase Memory MCP operations and results;
- exact ponytail evidence;
- exact test commands and results;
- static checks;
- checks not run;
- residual risks; and
- confirmation that the documentation-only change is ready for
  owner-controlled commit.

After the owner creates and supplies the resulting commit SHA:

1. review that commit against this task and baseline;
2. verify that only the permitted documentation and task publication changes
   are present;
3. inspect workflow/status evidence when available;
4. determine whether the commit satisfies every acceptance criterion; and
5. establish that commit as the accepted MVP-04A baseline only after the review
   passes.

Do not predict or invent the owner-created commit SHA inside execution evidence.

## Final report

Report:

- selected runtime/model when verifiable;
- terminal outcome;
- canonical target;
- baseline and execution HEAD;
- capabilities;
- Codebase Memory MCP availability evidence;
- initial index status;
- index action and justification;
- exact structural, call-path, caller, and impact queries;
- final index status;
- ponytail installation and continuous active-state evidence;
- subagent usage;
- product invariants verified;
- documentation files changed;
- confirmation that production source and tests were unchanged;
- focused PHPUnit commands and exact counts;
- static and repository checks;
- corrected commit traceability;
- corrected evidence counts;
- checks not run;
- remaining MVP-04 scope;
- residual risks;
- readiness for owner-controlled commit; and
- confirmation that no dependency, migration, browser-platform, later MVP,
  external adapter, CI, deployment, commit, push, or production work occurred.

Do not include credentials, protected identifiers, confirmation tokens, private
prompts, hidden reasoning, or complete transcripts.

Do not commit or push.

Stop after this evidence and traceability closure.
