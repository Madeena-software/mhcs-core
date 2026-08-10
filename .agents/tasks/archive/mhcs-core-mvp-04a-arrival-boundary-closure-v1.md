---
name: mhcs-core-mvp-04a-arrival-boundary-closure
description: Close the remaining MVP-04A site-switch lifecycle, trusted site-context, confirmation-boundary, test, and evidence defects without adding later Operator scope.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04A — Arrival Boundary Closure

## Objective

Close the remaining review findings on baseline:

`2e08eae74e49b0ba54461ba8787a0ec8e0ece062`

The preceding remediation correctly added:

- real Operator-only shared login;
- Operator-only mandatory password replacement;
- deterministic dual-role routing;
- exact `operator.shift.manage` enforcement;
- unified charged/personal attendance eligibility;
- explicit session-bound arrival confirmation; and
- authoritative post-arrival navigation.

This closure task addresses only the remaining defects:

1. a `recorded` arrival blocks site switching permanently because no production
   flow can move it to the test-only `resolved` state;
2. an unconsumed arrival confirmation is not treated as unresolved work and may
   survive a site switch;
3. the Member attendance contract verifies only that trusted context has some
   site, not that the context's local Operator site corresponds to the supplied
   stable `operator_site_id`;
4. the public application service still exposes a direct `record()` method that
   bypasses the required confirmation boundary;
5. evidence still describes the remediation as uncommitted at the previous
   baseline rather than identifying commit
   `2e08eae74e49b0ba54461ba8787a0ec8e0ece062`.

Required corrected policy:

```text
unconsumed, unexpired arrival confirmation for active site A
→ unresolved MVP-04A arrival command
→ switching away from site A is denied

cancelled, expired, or successfully consumed confirmation
→ no unresolved arrival command
→ switching may proceed when other authorization checks pass

recorded arrival
→ completed arrival command
→ remains visible in the later-verification worklist
→ does not permanently lock the Operator to the site
```

The worklist remains informational in MVP-04A. Identity verification, consent,
check-in, ticketing, queue claims, and a verification-ownership lifecycle remain
deferred to later MVP-04 slices.

Pest/Playwright/browser-platform work remains deferred.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `codebase-memory-mcp`
  - `ponytail`
- Required Codex tools:
  - Codebase Memory MCP must be configured, reachable, and actively used for
    repository indexing, structural discovery, dependency/call-path analysis,
    and impact verification.
  - ponytail must be installed and active for the full Codex execution,
    including any spawned subagents.
- Ordered model preferences: None.
- Require preferred model: `false`
- Tool fallback policy: `forbidden`. Do not silently replace Codebase Memory MCP
  with grep-only exploration or continue without ponytail.

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Mandatory Codex tool usage

Before repository analysis or editing:

1. Confirm the Codex runtime exposes Codebase Memory MCP.
2. Confirm ponytail is installed and active.
3. Use Codebase Memory MCP to index or refresh the `$TARGET` repository graph.
4. Query the graph for the relevant Operator arrival, active-site,
   authenticated-context, Member attendance, provider-binding, route, and test
   symbols before broad manual file exploration.
5. Use graph-based caller/callee, dependency, and impact queries to identify the
   bounded change surface.
6. Keep ponytail active throughout planning, implementation, verification, and
   any subagent execution.
7. Record the exact Codebase Memory MCP operations used and the observed
   ponytail activation evidence.

Minimum Codebase Memory MCP usage:

- repository index or index-status verification;
- structural search for the primary services and contracts;
- caller/callee or path tracing for the arrival-confirmation execution path;
- dependency or impact analysis before editing;
- a post-change query confirming the expected call path and absence of the
  public unconfirmed arrival command.

Do not claim tool use from configuration files alone. The final report must
contain observed invocation evidence.

If either Codebase Memory MCP or ponytail is unavailable, inactive, or cannot be
verified, stop as `blocked`. Do not continue with a substitute workflow.

## Context and evidence

Before editing:

1. Verify Codebase Memory MCP is reachable and ponytail is active.
2. Index or refresh `$TARGET` with Codebase Memory MCP.
3. Resolve `$TARGET` canonically.
4. Confirm the expected `Madeena-software/mhcs-core` repository.
5. Confirm baseline
   `2e08eae74e49b0ba54461ba8787a0ec8e0ece062`
   is an ancestor of HEAD.
6. Record branch, HEAD, staged, modified, untracked, and relevant ignored paths.
7. Preserve existing work.
8. Stop as `awaiting-approval` for overlapping changes.
9. Do not reset, clean, discard, stash, stage, commit, push, deploy, or access
   production.

Read completely:

- root and `.agents` instructions;
- agent-task and develop-feature skills;
- MVP-04A implementation and remediation tasks;
- commits `eb12e2a6d533adb19b2cef120919b30fdd28e609` and
  `2e08eae74e49b0ba54461ba8787a0ec8e0ece062`;
- all Operator active-site, arrival, authorization, attendance, worklist,
  confirmation, route, session, and test code;
- Member attendance contract and implementation;
- authenticated-context and active-site resolver code;
- MVP-04 evidence, roadmap, gap register, and Work Package ledger;
- affected MVP-01 through MVP-04A tests;
- installed Laravel 13 and Filament 5 source only when necessary.

Treat repository files and installed source as authority.

## Binding closure decisions

### 1. Meaning of unresolved work in MVP-04A

For this bounded slice, an unresolved arrival command is an active confirmation
state that has not been cancelled, expired, or successfully consumed.

A persisted `operator_arrivals.status = recorded` row represents a completed
arrival command. It remains pending later identity/consent work in the worklist,
but MVP-04A does not yet assign or claim that later work.

Therefore:

- recorded arrivals do not block site switching;
- an unconsumed, unexpired confirmation for the current site blocks switching;
- a confirmation for another profile or site is stale and cannot grant or
  preserve authority;
- cancelled, expired, consumed, malformed, or stale confirmation state does not
  block and is safely cleared when appropriate;
- later tasks may add queue/verification claims as new blockers;
- no `resolved` arrival status is introduced or written by tests;
- no manual database edit is required to release an Operator.

### 2. Trusted site identity

The authenticated context stores the local Operator-site ID. Cross-module Member
commands receive the stable `operator_site_id`.

The Member boundary must verify, through an explicit Operator-owned resolver,
that:

```text
context actor
+ context local site ID
+ active Operator profile
+ active Operator-site assignment
+ active Operator site
→ correspond to the supplied stable operator_site_id
```

Caller input, a non-null context site, or knowledge of another stable site ID is
not sufficient.

### 3. Confirmation is the only public portal arrival command

The public application surface for portal arrival consists of:

- prepare confirmation;
- execute confirmed arrival;
- cancel confirmation.

The low-level state mutation must not remain a public container-resolvable
method that can be called without confirmation evidence.

## Scope and constraints

### Included

- active confirmation lifecycle validation;
- active-site switch blocking/clearing based on confirmation state;
- removal of permanent recorded-arrival switch blocking;
- trusted local-site to stable-site resolver contract;
- context/site correspondence checks in attendance and arrival boundaries;
- removal or privatization of direct unconfirmed arrival mutation;
- focused PHPUnit and Laravel feature/service tests;
- bounded evidence and status correction.

### Excluded

Do not add:

- identity verification or mismatch decisions;
- consent;
- `checked_in`;
- ticket issuance;
- queue stages, queue claims, calls, skips, or recalls;
- basic examination, vital signs, or clinical workflow;
- walk-ins, cash, or Encounter creation;
- X-ray capture, NPZ, DICOM, Cornerstone, MPIPS, or Image Gateway behavior;
- a persisted verification claim;
- a new arrival status lifecycle;
- a migration;
- dependencies;
- Pest/Playwright/browser work;
- CI workflows;
- external adapters;
- deployment or production configuration.

Do not modify:

- `.agents/context/**`;
- `docs/implementation/**`;
- accepted requirement assignments or source digests;
- Composer/npm dependency files;
- existing Pest/browser files;
- accepted migrations.

Do not commit or push.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates:
  - stop as `blocked` if Codebase Memory MCP is unavailable, cannot index/query
    the repository, or its use cannot be evidenced;
  - stop as `blocked` if ponytail is unavailable, inactive, disabled during
    execution, or cannot be evidenced;
  - stop if baseline ancestry is absent;
  - stop for overlapping work;
  - stop if closure requires a migration or new persisted workflow state;
  - stop if repository authority contradicts the binding unresolved-work
    decision above;
  - stop if context/site validation requires Member Core to directly own or
    mutate Operator tables;
  - stop before later MVP, dependency, CI, external, destructive, or production
    work;
  - stop if focused regressions reveal a broader accepted MVP defect.

## Execution procedure

1. Validate this task.
2. Verify ponytail is active.
3. Index or refresh the repository with Codebase Memory MCP.
4. Use Codebase Memory MCP structural and call-path queries to map the bounded
   change surface.
5. Confirm repository identity, ancestry, and worktree state.
6. Reproduce the permanent site-lock behavior.
7. Reproduce switching with an unconsumed confirmation.
8. Reproduce direct cross-module use with a mismatched context site and stable
   Operator site ID.
9. Implement the bounded confirmation-lifecycle switch policy.
10. Add an explicit Operator-owned trusted-site resolver contract.
11. Enforce context/site correspondence at every affected Member attendance and
    arrival entry point.
12. Remove or privatize direct unconfirmed arrival mutation.
13. Add focused tests.
14. Run bounded regressions and static checks.
15. Refresh/requery Codebase Memory MCP and verify the final call path and
    dependency impact.
16. Correct evidence.
17. Re-read the final diff against this task.
18. Stop without starting MVP-04B.

## Required implementation

### 1. Confirmation-state lifecycle

Centralize confirmation-state parsing and validation in one service or value
object.

Minimum state fields already present:

- token;
- booking ID;
- occurrence time;
- idempotency key;
- Operator profile ID;
- local Operator site ID;
- schedule ID;
- expiration;
- consumed flag and result when completed.

Required classifications:

```text
active
consumed
expired
cancelled/absent
malformed
stale-context
```

Rules:

- only a structurally valid, unexpired, unconsumed state for the authenticated
  profile and current local site is `active`;
- expired state is cleared before returning a controlled expiration result;
- cancelled state is removed;
- consumed state may return its prior idempotent result, but is not unresolved;
- malformed state is cleared and fails safely;
- profile/site mismatch cannot be executed;
- no request field can alter the session-bound booking, occurrence, operation,
  profile, site, or schedule;
- no credential or protected Member value is stored in confirmation state.

### 2. Active-site switching

Update `OperatorActiveSiteService` or a bounded collaborator.

Required behavior:

#### First selection

- succeeds for an authorized active assignment;
- does not require confirmation state;
- audits success.

#### Same-site selection

- is a safe no-op or reselection;
- does not create a false switch blocker;
- preserves valid state.

#### Switch to another site

Before changing the session site:

1. resolve the currently trusted active site;
2. inspect confirmation state;
3. when a valid active confirmation belongs to the current profile and current
   site, deny the switch;
4. preserve the previous active site;
5. audit a controlled `active_site_blocked` failure;
6. do not expose booking, Member, or confirmation-token data in audit metadata.

When confirmation is consumed, expired, absent, malformed, or safely cancelled:

- it does not block;
- expired/malformed/stale state is cleared;
- switching proceeds after normal site authorization;
- stale schedule/work context is cleared;
- confirmation state that cannot be valid at the new site is cleared;
- success is audited.

Remove the query that treats every `recorded` arrival as unresolved.

Do not add `resolved` writes to production or tests.

### 3. Trusted Operator site resolver

Add a narrow contract owned at the cross-module boundary, for example:

```text
TrustedOperatorSiteContextResolver
```

Equivalent naming is acceptable.

The implementation belongs to Operator Core.

The resolver must accept:

- trusted `AuthenticatedContext`;
- supplied stable `operator_site_id`;
- required Operator permission or purpose when necessary.

It must fail closed unless:

- actor ID exists;
- context local site ID exists;
- exact Operator role exists;
- required permission exists;
- active Operator profile belongs to the actor;
- active site assignment links that profile to the context local site;
- active Operator site local ID equals context site ID;
- that site's stable ID equals the supplied stable ID.

Return only a bounded assertion/result. Do not expose Operator tables to Member
as mutable records.

Register the contract through the Operator provider using scoped lifetime.

### 4. Member attendance and arrival boundary

Enforce trusted site correspondence in:

- attendance query;
- arrival target resolution;
- authoritative `confirmed → arrived` transition.

Update the contract signature where required so arrival resolution receives the
trusted context.

Rules:

- query still requires `operator.attendance.read`;
- confirmation/arrival resolution requires `operator.arrival.record`;
- transition requires `operator.arrival.record`;
- context purpose may be normalized through accepted conventions;
- a valid context for site A plus stable site B fails non-disclosingly;
- no attendance rows, arrival record, booking transition, status event, outbox
  event, or success audit is created on mismatch;
- the Member implementation does not directly decide Operator assignments;
- existing schedule assignment checks remain in Operator Core;
- safe fields and protected-data boundaries remain unchanged.

### 5. Confirmation-only mutation surface

Refactor `OperatorArrivalService`:

- public `confirm(...)`;
- public `recordConfirmed(...)`;
- public `cancelConfirmation(...)`;
- low-level record implementation is private or otherwise inaccessible as an
  unconfirmed application command.

Requirements:

- `recordConfirmed` revalidates portal, site, assignment, eligibility, and
  confirmation state immediately before mutation;
- idempotency remains based on session-bound operation identity;
- repeated consumed confirmation returns the original result while valid state
  is retained;
- failed execution does not mark confirmation consumed;
- failed execution creates no partial arrival/Member/event/outbox state;
- tests no longer call a public unconfirmed `record()` method.

Do not create a second route or administrative bypass.

### 6. Evidence correction

Update only as required:

```text
docs/mvp/evidence/mvp-04-operator-foundation-arrival.md
docs/mvp/roadmap.md
docs/mvp/beta-gap-register.md
docs/mvp/work-package-status.md
```

Evidence must state:

- closure baseline:
  `2e08eae74e49b0ba54461ba8787a0ec8e0ece062`;
- the prior remediation is committed at that SHA;
- closure execution commit when available, otherwise truthful working-tree
  state;
- exact confirmation-lifecycle policy;
- recorded arrivals do not represent a verification claim;
- exact context/site resolver boundary;
- exact focused commands and observed results;
- Pest/browser, full PHPUnit, MySQL, CI, deployment, and production checks not
  run;
- MVP-04, WP-11, WP-12, and WP-17 remain partial/open.

Remove statements that describe commit `2e08eae...` as uncommitted work.

## Focused tests

Do not use Pest or Playwright.

### Confirmation lifecycle and site switching

Prove:

- first authorized site selection succeeds;
- same-site reselection succeeds;
- clean site A → site B switch succeeds;
- active unconsumed confirmation at site A blocks switching to site B;
- blocked switch preserves site A;
- blocked switch creates bounded failure audit;
- cancellation permits switch;
- expiration clears state and permits switch;
- malformed state clears and permits switch safely;
- consumed confirmation does not block;
- a recorded arrival does not block after successful command completion;
- no test writes `operator_arrivals.status = resolved`;
- stale confirmation for another profile/site cannot execute;
- successful switch clears confirmation state that cannot apply at the new site.

### Trusted site correspondence

Prove:

- attendance query succeeds when local context site and stable site correspond;
- site-A context plus site-B stable ID is denied;
- arrival confirmation resolution is denied on mismatch;
- Member transition is denied on mismatch;
- mismatch creates no attendance disclosure or mutation side effects;
- inactive site/profile/assignment fails;
- caller-supplied stable ID cannot grant authority;
- audit failure metadata contains no protected identifiers or tokens.

### Confirmation-only service surface

Prove:

- no public `record()` method exists on `OperatorArrivalService`;
- confirmation preparation performs no mutation;
- confirmed execution mutates once;
- repeated confirmed execution returns the original result;
- cancellation performs no mutation;
- changed request input cannot replace session-bound values;
- final redirect remains authoritative;
- failed revalidation leaves all state unchanged.

### Targeted regressions

Run focused affected:

- MVP-01 login and password replacement;
- non-browser MVP-03 booking/domain tests affected by Member attendance changes;
- all MVP-04A foundation, portal, and administration tests;
- filtered WP-02 context/authorization/audit/idempotency/security tests;
- affected architecture tests.

Do not run complete suites unless required filtering is unavailable.

## Verification

- Method: Validate the task; verify ponytail activation; index/query the
  repository through Codebase Memory MCP; run focused confirmation/site-switch
  tests; run trusted context/site tests; run confirmation-only service tests;
  run bounded regressions; run Pint on changed PHP files; run PHP syntax checks;
  inspect routes, service visibility, container bindings, audit metadata,
  transaction and module boundaries; use post-change Codebase Memory MCP
  call-path and impact queries; run `git diff --check`.
- Expected result: An active confirmation blocks leaving its site, completed
  arrivals do not create permanent site lock, the Member boundary proves local
  and stable Operator site correspondence, no public unconfirmed arrival command
  exists, and evidence accurately identifies the reviewed commit.

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

- [ ] Codebase Memory MCP availability is verified.
- [ ] ponytail availability and active state are verified.
- [ ] Codebase Memory MCP indexed or refreshed the target repository.
- [ ] Structural, call-path, and impact queries were used before editing.
- [ ] Post-change Codebase Memory MCP queries confirm the intended final path.
- [ ] The final report records observed Codebase Memory MCP and ponytail evidence.
- [ ] No grep-only or non-ponytail fallback was used.
- [ ] Baseline ancestry and worktree state are confirmed.
- [ ] Task validation passes.
- [ ] Existing work is preserved.
- [ ] No dependency, migration, browser, CI, external, or production scope is added.
- [ ] Confirmation state has one centralized validator/classifier.
- [ ] Active confirmation blocks switching away from its site.
- [ ] Blocked switch preserves the current site.
- [ ] Cancelled and expired confirmation no longer block.
- [ ] Consumed confirmation does not block.
- [ ] Recorded arrival does not permanently block site switching.
- [ ] No production or test code writes a synthetic `resolved` arrival status.
- [ ] Stale/malformed confirmation is safely cleared.
- [ ] Switch audit contains no booking, Member, or token data.
- [ ] Operator-owned resolver proves local/stable site correspondence.
- [ ] Attendance query enforces site correspondence.
- [ ] Arrival resolution enforces site correspondence.
- [ ] Member booking transition enforces site correspondence.
- [ ] Site mismatch creates no disclosure or mutation side effects.
- [ ] Exact role, permission, profile, site, and assignment checks remain.
- [ ] `OperatorArrivalService` exposes no public unconfirmed record command.
- [ ] Confirmation execution revalidates all authoritative state.
- [ ] Idempotent replay remains correct.
- [ ] Existing safe-field and protected-data boundaries remain.
- [ ] Focused closure tests pass.
- [ ] Affected MVP regressions pass.
- [ ] Pint, syntax checks, static inspection, and `git diff --check` pass.
- [ ] Evidence identifies commit `2e08eae...` truthfully.
- [ ] MVP-04 and relevant Work Packages remain partial/open.
- [ ] Pest/browser files are not modified or run.
- [ ] No later MVP, commit, push, deployment, or production work occurs.

## Stop conditions

Stop as `blocked` when:

- Codebase Memory MCP is unavailable, cannot index/query `$TARGET`, or its use
  cannot be verified;
- ponytail is unavailable, inactive, disabled, or its activation cannot be
  verified.

Stop as `awaiting-approval` when:

- baseline ancestry is absent;
- required files overlap existing work;
- closure requires a migration or persisted verification claim;
- authority contradicts the bounded confirmation policy;
- safe context/site matching cannot be implemented without Member ownership of
  Operator records;
- a broader accepted MVP regression is discovered;
- later MVP, dependency, external, destructive, or production work is required.

## Output

- `succeeded`
- `failed`
- `blocked`
- `awaiting-approval`
- `exhausted`

## Final report

Report:

- baseline and execution commit;
- runtime/model when verifiable;
- capabilities;
- Codebase Memory MCP availability, index/refresh result, exact structural
  queries, call-path queries, impact queries, and post-change verification;
- ponytail installation/activation evidence and confirmation it remained active
  for the full execution and any subagents;
- files changed;
- confirmation-state lifecycle;
- site-switch policy and audit behavior;
- trusted local/stable site resolver;
- attendance and arrival boundary changes;
- confirmation-only mutation surface;
- transaction, idempotency, and protected-data behavior;
- focused tests and regressions;
- static checks;
- evidence corrections;
- tests not run;
- remaining MVP-04 scope;
- confirmation that no dependency, migration, browser-platform, later MVP,
  external adapter, CI, deployment, commit, push, or production work was added.

Do not include credentials, protected identifiers, or confirmation tokens.

Do not commit or push.

Stop after this closure.
