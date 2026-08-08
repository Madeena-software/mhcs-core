---
name: mhcs-core-mvp-04l-atomic-xray-claim
description: Let an assigned Operator privately and atomically reserve one X-ray-ready admission without starting examination or capture work.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04L — Atomic X-Ray Claim

## Objective

For `$TARGET`, extend the accepted MVP-04K private X-ray readiness worklist
with one Operator-owned reservation action. An authorized Operator at the
trusted active site and assigned shift may atomically claim one unclaimed
`xray` / `waiting` admission. The claim retains its stage, state, ticket, and
FIFO-ready time, records the claimant and occurrence time, and produces one
append-only history, audit, and outbox trail in the existing idempotent
transaction boundary.

This is a private claim only. It must not call or start the X-ray admission,
create an Encounter, select a station, snapshot a protocol, accept drafts or
files, invoke Image Gateway, or expose Member or clinical data.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `graphify`
  - `codebase-memory-mcp`
  - `ponytail`
- Ordered model preferences: None.
- Require preferred model: `false`

Graphify, Codebase Memory MCP, and ponytail are required. Keep ponytail at
full level: reuse the existing `operator_queue_admissions` claim columns and
constraints, `DatabaseIdempotencyStore`, `OperatorAuthorization`,
`OperatorWorklistService`, audit/outbox primitives, private portal routes, and
MVP-04K fixture. Do not add a migration, dependency, permission, policy,
generic queue engine, workflow abstraction, or second worklist.

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Accepted baseline and review candidate: `bba316bae09f6882facfe36aacde46996c8efd89`,
  descending from `a225b8719b26057b91dcbb968c4dcec27d156872`.
- MVP-04K completes a claimant-owned basic-examination admission only after
  its correctly bound MVP-04J execution exists, releases the live claim, and
  atomically creates one unclaimed `xray` / `waiting` admission for the same
  paper ticket, site, and shift.
- The Operator authority requires a waiting ticket claim to be atomic, permits
  only one claimed clinical-stage ticket per Operator, preserves stage FIFO,
  and requires an audit trail for claims. It separately defines X-ray call,
  start, protocol, capture, durable acceptance, and AI work; none is in this
  task.
- Related requirements: `OPR-031`, `OPR-108`, `OPR-115`, `OPR-116`,
  `OPR-117`, `OPR-129`, and `OPR-134`. Related Work Packages: WP-11,
  WP-12, WP-14, and WP-17. Open gaps `MVP-GAP-009`, `MVP-GAP-012`,
  `MVP-GAP-021`, and `MVP-GAP-024` remain open.

Before implementation decisions, directly inspect:

- `$TARGET/AGENTS.md`, `$TARGET/.agents/AGENTS.md`,
  `$TARGET/.agents/skills/agent-task/SKILL.md`,
  `$TARGET/.agents/skills/develop-feature/SKILL.md`, and
  `$TARGET/.agents/skills/graphify/SKILL.md`;
- `$TARGET/.agents/context/project.md` and
  `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04f-atomic-basic-examination-claim-v1.md`,
  `$TARGET/.agents/tasks/mhcs-core-mvp-04g-private-basic-examination-call-v1.md`,
  `$TARGET/.agents/tasks/mhcs-core-mvp-04h-private-basic-examination-start-v1.md`,
  `$TARGET/.agents/tasks/mhcs-core-mvp-04j-private-vital-signs-capture-v1.md`,
  and `$TARGET/.agents/tasks/mhcs-core-mvp-04k-basic-examination-completion-xray-readiness-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`,
  `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`,
  `$TARGET/docs/mvp/roadmap.md`, `$TARGET/docs/mvp/decision-log.md`,
  `$TARGET/docs/mvp/beta-gap-register.md`, and
  `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`,
  `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`,
  `$TARGET/app/Modules/Operator/Application/Services/OperatorShiftAssignmentService.php`,
  `$TARGET/app/Http/Controllers/Operator/PortalController.php`,
  `$TARGET/app/Shared/Infrastructure/Idempotency/DatabaseIdempotencyStore.php`,
  `$TARGET/app/Shared/Audit/DatabaseAuditStore.php`,
  `$TARGET/app/Shared/Infrastructure/Outbox/DatabaseOutboxStore.php`,
  `$TARGET/routes/web.php`, and
  `$TARGET/resources/views/operator/xray-readiness-worklist.blade.php`;
- `$TARGET/database/migrations/2026_08_07_000003_create_operator_queue_admissions_table.php`,
  `$TARGET/database/migrations/2026_08_08_000001_add_atomic_claim_to_operator_queue_admissions_table.php`,
  `$TARGET/database/migrations/2026_08_08_000003_allow_one_queue_admission_per_ticket_stage.php`,
  `$TARGET/tests/Feature/Operator/Mvp04kBasicExaminationCompletionTest.php`,
  `$TARGET/tests/Feature/Operator/Mvp04fAtomicBasicExaminationClaimTest.php`, and
  `$TARGET/tests/Architecture/FoundationArchitectureTest.php`.

Use Graphify first to identify the current MVP-04K, Operator queue,
requirements, Work Package, gap, and X-ray-boundary relationships; reuse the
graph if current and update only if relevant tracked evidence changed. Use
Codebase Memory MCP to verify the canonical index and trace the existing
claim, service, controller, route, constraint, and test paths; fast-refresh
only if needed. Both are discovery aids only: inspect the named repository
files directly before requirement, architecture, or implementation claims.

## Scope and constraints

Included:

- one `OperatorWorklistService::claimXray()` command that uses the established
  idempotency transaction, locks the target admission, rechecks persisted
  account, portal permission, active site, active shift assignment, admission
  site/schedule, unclaimed owner, `xray` stage, and `waiting` state, then sets
  only `operator_profile_id` and `claimed_at`;
- one private POST route
  `/operator/xray-readiness-worklist/{admission}/claim`, controller action,
  and smallest form on the existing worklist; the operation identifier and
  route admission ID are the only caller inputs;
- claimant-only worklist visibility: show eligible unclaimed rows and the
  current claimant's own row, while hiding claimed rows from every other
  Operator. Include the opaque admission ID solely for the form and never
  render it as worklist data;
- one `claimed` history event retaining `waiting` state, one
  `operator.xray.claimed` audit record, and one `operator.xray-claimed` outbox
  event within the successful idempotent transaction; and
- focused success, exact-replay, changed-payload, competing-claim, one-live-
  claim, revocation, foreign/stale-state, rollback, FIFO, and privacy tests.

Excluded:

- any state transition to `called`, `in_service`, `awaiting_ai`, `deferred`,
  or `completed`; X-ray call, recall, start, skip, release, no-show, shift
  close, station selection, Encounter/FHIR, protocol, earnings, clinical
  values, Member mutation, NPZ/gain drafts or uploads, Image Gateway, MPIPS,
  AI, public/LCD/audio behavior, or Member-visible UI;
- any migration or modification of the existing ticket-stage or one-live-claim
  constraints, a new permission or role, generic authorization redesign,
  dependency, configuration, retention/deletion/anonymization mechanism,
  documentation status claim, commit, or push.

The existing global one-live-claim-per-Operator constraint must apply across
basic-examination and X-ray reservations. Replays return the original safe
result; reusing an operation ID with a changed admission/site/profile payload
conflicts. Fail closed without exposing Member, booking, consent, identity,
clinical, other-Operator, or internal-exception data. Preserve all accepted
MVP-04F through MVP-04K behavior and the existing X-ray readiness ordering by
`ready_at`, then immutable admission ID.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates: The bounded private X-ray claim is authorized by this task.
  Stop as `awaiting-approval` before any excluded state transition, clinical or
  Member data access, schema/constraint change, protocol/Encounter/capture/
  Image Gateway behavior, public exposure, privacy-retention operation, or
  dependency change. Do not commit or push.

## Execution procedure

1. Resolve `$TARGET`; verify baseline ancestry, clean-or-owner worktree state,
   required capabilities, and validation of this and its MVP-04K predecessor.
   Preserve unrelated changes; do not reset, clean, stash, discard, commit, or
   push.
2. Check Graphify and Codebase Memory MCP freshness as stated above, then
   directly inspect every governing authority, service, migration, route,
   view, and test listed in Context and evidence.
3. Record the ponytail choice: reuse the existing live-claim fields,
   constraint, idempotency store, and basic-claim pattern; add no schema or
   queue abstraction. Verify from the migrated schema that no new migration is
   necessary.
4. Add only the X-ray claim command, private route/controller/form, claimant
   visibility, and audit/outbox/history evidence. Recheck all authorization
   inside the idempotent transaction and preserve `xray` / `waiting` and FIFO
   fields on success.
5. Add focused regression coverage proving exact replay, changed-payload
   conflict, competing claims, cross-stage single-live-claim protection,
   revoked account/permission/site/assignment and foreign/stale/state denials,
   audit/outbox rollback, no sensitive response or payload data, and retained
   MVP-04K completion/readiness behavior.
6. Run the focused MVP-04L suite and MVP-04K/J/H/G/F/E regressions, fresh
   migration/schema inspection, PHP syntax/static checks, Pint, Composer,
   private route listing, privacy searches, Graphify/Codebase-Memory final
   checks, task validation, and `git diff --check`. Inspect actual outputs and
   final diff, then provide the commit-review handoff without committing or
   pushing.

## Acceptance criteria

- [ ] Only an authorized assigned Operator at the trusted active site can
      atomically claim one eligible unclaimed `xray` / `waiting` admission.
- [ ] A successful claim retains stage, state, ticket, site, schedule, and
      FIFO-ready time; it creates exactly one claimant/time, history, audit,
      outbox, and handled idempotency result, while exact replay creates none.
- [ ] Competing, changed-payload, second-live-claim, revoked, cross-site,
      cross-shift, foreign, stale, and non-waiting paths fail closed without
      partial state or sensitive/internal-detail leakage.
- [ ] The private readiness worklist remains assignment/site scoped and FIFO;
      it exposes only operational ticket fields, its own opaque form ID, and
      the claimant's reservation, never a claimant identity or Member/clinical
      data to another Operator.
- [ ] MVP-04K completion/X-ray readiness and all deferred X-ray execution,
      protocol, clinical, Member, Image Gateway, public, privacy, dependency,
      commit, and push boundaries remain unchanged.
- [ ] Required focused, prerequisite, migration/schema, formatter, syntax,
      Composer, route, privacy, derived-intelligence, task, and final-diff
      checks pass with observed evidence.

## Verification

- Method: Validate this and MVP-04K tasks; run the focused MVP-04L test plus MVP-04K/J/H/G/F/E and affected architecture regressions; inspect a fresh migrated schema and private routes; run PHP syntax/static, Pint, Composer, privacy searches, Graphify/Codebase-Memory freshness and source checks, and `git diff --check`.
- Expected result: One trusted Operator can idempotently and privately reserve one eligible X-ray waiting admission without changing its stage/state or FIFO order; every competing, authorization, stale, failure, and privacy path is atomic and leak-free; no X-ray execution or broader scope is introduced.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, accepted baseline, selected runtime/model when verifiable,
  Graphify and Codebase Memory status/actions/freshness, direct authority
  files, ponytail choice, affected interfaces/files, verification evidence,
  residual risks, deferred scope, and manual follow-up.
- Treat missing transaction/idempotency/audit/outbox evidence, a changed queue
  state, a duplicate/competing claim success, privacy leakage, an unrun
  required check, or a commit/push as unsuccessful.

## Commit review handoff

Do not commit or push. Report final worktree state and readiness for an
owner-controlled commit. After the owner supplies a candidate SHA, review its
full chain against baseline `bba316bae09f6882facfe36aacde46996c8efd89`, this
task, direct authoritative repository evidence, and observed verification
before accepting a new baseline or selecting another slice.
