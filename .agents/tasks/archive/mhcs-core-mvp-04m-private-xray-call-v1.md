---
name: mhcs-core-mvp-04m-private-xray-call
description: Let the claimant of a private X-ray admission atomically mark it called without starting the examination or adding public display behavior.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04M — Private X-Ray Call

## Objective

For `$TARGET`, extend the accepted MVP-04L private X-ray claim with one
claimant-only queue transition. The Operator who currently holds an eligible
`advance` / `xray` admission may atomically change its state from `waiting` to
`called` at the trusted active site and assigned shift.

The call remains private. Preserve the existing claim owner and claim time,
stage, paper ticket, queue class, site, shift, and FIFO-ready time. Append one
`called` history record plus matching audit, outbox, and idempotency evidence
in the same transaction. Keep the claimant's called row privately visible for
a later X-ray-start slice, while every other Operator sees neither the row nor
the claimant. Do not start the X-ray examination or add public/LCD/audio
calling.

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
full level: reuse the existing `OperatorWorklistService::callBasicExamination()`
pattern, `DatabaseIdempotencyStore`, `OperatorAuthorization`, shift-assignment
checks, queue-admission/history tables, audit/outbox primitives, private route
group, X-ray worklist, and MVP-04L fixture. Do not add a migration, dependency,
permission, policy, queue framework, generic state machine, or second
worklist.

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Accepted MVP-04L baseline:
  `c4aebdae61b4e01cd361bee1265063ba72254d03`, descended from the previous
  accepted baseline `bba316bae09f6882facfe36aacde46996c8efd89` through the
  published MVP-04L task commit `57036d873b5d4c543bad1a2d8ba39085df288efc`.
- MVP-04L atomically reserves one unclaimed `xray` / `waiting` admission,
  preserves stage/state/FIFO fields, applies the global one-live-claim
  constraint across clinical stages, and keeps claimed rows visible only to
  their claimant. It does not call or start X-ray work.
- The Operator authority distinguishes `waiting`, `called`, and `in_service`.
  Every call records the responsible Operator and actual occurrence time.
  Public LCD/audio behavior is a separate deferred boundary.
- Related requirements: `OPR-031`, `OPR-108`, `OPR-109`, `OPR-110`,
  `OPR-115`, `OPR-116`, `OPR-117`, `OPR-129`, and `OPR-134`.
- Related Work Packages: WP-11, WP-12, WP-14, and WP-17.
- Open gaps `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and
  `MVP-GAP-024` remain open.

Before implementation decisions, directly inspect:

- `$TARGET/AGENTS.md`, `$TARGET/.agents/AGENTS.md`,
  `$TARGET/.agents/skills/agent-task/SKILL.md`,
  `$TARGET/.agents/skills/develop-feature/SKILL.md`, and
  `$TARGET/.agents/skills/graphify/SKILL.md`;
- `$TARGET/.agents/context/project.md` and
  `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04g-private-basic-examination-call-v1.md`,
  `$TARGET/.agents/tasks/mhcs-core-mvp-04k-basic-examination-completion-xray-readiness-v1.md`,
  and `$TARGET/.agents/tasks/mhcs-core-mvp-04l-atomic-xray-claim-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`,
  `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`,
  `$TARGET/docs/mvp/roadmap.md`, `$TARGET/docs/mvp/decision-log.md`,
  `$TARGET/docs/mvp/beta-gap-register.md`, and
  `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`,
  `$TARGET/app/Modules/Operator/Application/Services/OperatorShiftAssignmentService.php`,
  `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`,
  `$TARGET/app/Http/Controllers/Operator/PortalController.php`,
  `$TARGET/app/Http/Middleware/EnsureOperatorPortalAccess.php`,
  `$TARGET/app/Shared/Infrastructure/Idempotency/DatabaseIdempotencyStore.php`,
  `$TARGET/app/Shared/Audit/DatabaseAuditStore.php`,
  `$TARGET/app/Shared/Infrastructure/Outbox/DatabaseOutboxStore.php`,
  `$TARGET/routes/web.php`, and
  `$TARGET/resources/views/operator/xray-readiness-worklist.blade.php`;
- `$TARGET/database/migrations/2026_08_07_000003_create_operator_queue_admissions_table.php`,
  `$TARGET/database/migrations/2026_08_08_000001_add_atomic_claim_to_operator_queue_admissions_table.php`,
  `$TARGET/database/migrations/2026_08_08_000003_allow_one_queue_admission_per_ticket_stage.php`,
  `$TARGET/tests/Feature/Operator/Mvp04gPrivateBasicExaminationCallTest.php`,
  `$TARGET/tests/Feature/Operator/Mvp04kBasicExaminationCompletionTest.php`,
  `$TARGET/tests/Feature/Operator/Mvp04lAtomicXrayClaimTest.php`, and
  `$TARGET/tests/Architecture/FoundationArchitectureTest.php`.

Use Graphify first to identify current X-ray claim, queue-state, requirements,
Work Package, gap, and public-display relationships. Reuse a current graph and
incrementally update only when relevant tracked evidence changed. Use Codebase
Memory MCP to verify the canonical index and trace the X-ray worklist, claim,
existing basic-call pattern, controller, route, constraints, and tests; use a
fast refresh only when needed. Both are discovery aids: inspect the exact
authoritative repository files directly before making requirement,
architecture, acceptance, or implementation claims.

## Scope and constraints

Included:

- one `OperatorWorklistService::callXray()` command that uses the established
  idempotency transaction, locks the admission, and rechecks persisted account,
  portal permission, active site/site assignment, active shift assignment,
  admission site/schedule, current claim ownership, `advance` queue class,
  `xray` stage, and `waiting` state before changing only state to `called` plus
  the ordinary update timestamp;
- one private POST route
  `/operator/xray-readiness-worklist/{admission}/call`, controller action, and
  smallest claimant-only Call form using only the route admission ID and one
  operation identifier;
- claimant-only worklist visibility for eligible unclaimed `waiting` rows and
  the current claimant's own `waiting` or `called` row. Keep the opaque
  admission ID solely for the form; never render it or claimant identity as
  worklist data;
- one `called` history event from `waiting` to `called`, one
  `operator.xray.called` audit record, and one `operator.xray-called` outbox
  event within the successful idempotent transaction; and
- focused success, exact replay, changed-payload, competing/non-claimant,
  revoked account/permission/site/assignment, forged active-site,
  cross-site/cross-shift, foreign/stale/non-waiting, rollback, claim/FIFO
  preservation, and privacy tests.

Excluded:

- recall; X-ray start or `in_service`; station selection; Encounter/FHIR;
  protocol snapshot or administration; order correction; NPZ/gain draft,
  preview, upload, validation, or submission; Image Gateway, MPIPS, AI,
  earnings, or Member mutation;
- skip, release, no-show, shift close, walk-ins, public/LCD/audio payload or
  display behavior, Member-visible UI, and clinical, identity, consent, or
  booking data exposure;
- migration or constraint changes, new permissions or roles, shared
  authorization redesign, dependencies, configuration, retention/deletion/
  anonymization behavior, documentation status claims, commit, or push.

The transition must retain the live claim and all immutable queue/FIFO fields.
Exact replay returns the original safe result without duplicate evidence;
reusing an operation ID with a changed admission/site/profile payload
conflicts. Every denial or infrastructure failure must roll back without
Member, booking, consent, identity, clinical, other-Operator, or internal
exception leakage. Preserve all accepted MVP-04F through MVP-04L behavior.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates: The bounded private X-ray `waiting` to `called` transition
  is authorized by this task. Stop as `awaiting-approval` before any excluded
  state transition, schema/constraint change, public call/display behavior,
  clinical or Member data access, Encounter/protocol/capture/Image Gateway
  behavior, privacy-retention operation, or dependency change. Do not commit
  or push.

## Execution procedure

1. Resolve `$TARGET`; verify repository identity, accepted-baseline ancestry,
   clean-or-owner worktree state, required capabilities, and validation of
   this task plus MVP-04L and MVP-04G. Preserve unrelated changes; do not reset,
   clean, stash, discard, stage, commit, or push.
2. Check Graphify and Codebase Memory MCP freshness as stated above, then
   directly inspect every governing authority, service, migration, route,
   view, and test listed in Context and evidence.
3. Record the ponytail choice: reuse the existing basic-call transaction,
   X-ray claim fields and constraints, idempotency store, authorization,
   history, audit/outbox, route, view, and fixture patterns. Verify from the
   migrated schema that no migration is necessary.
4. Add only the X-ray call command, private route/controller/form, claimant
   visibility for `waiting`/`called`, and matching history/audit/outbox
   evidence. Recheck all authorization and scope inside the idempotent
   transaction and preserve claim, ticket, stage, site, shift, and FIFO fields.
5. Add focused regression coverage proving exact replay, changed-payload
   conflict, non-claimant denial, every listed revocation and foreign/stale
   denial, rollback on audit/outbox failure, no sensitive response or payload
   data, and retained MVP-04L claim and MVP-04K readiness behavior.
6. Run the focused MVP-04M suite and MVP-04L/K/J/H/G/F/E regressions, affected
   Operator/security/architecture checks, fresh migration/schema inspection,
   PHP syntax/static checks, Pint, Composer, private route listing, privacy
   searches, Graphify/Codebase-Memory final checks, task validation, and
   `git diff --check`. Inspect actual outputs and final diff, then provide the
   commit-review handoff without committing or pushing.

## Acceptance criteria

- [ ] Only the current authorized claimant at the trusted active site and
      assigned shift can atomically transition one eligible claimed `xray`
      admission from `waiting` to `called`.
- [ ] The successful call retains claim owner/time, ticket, queue class, stage,
      site, schedule, and FIFO-ready time; it creates exactly one called
      history, audit, outbox, and handled idempotency result, while exact replay
      creates none.
- [ ] Competing/non-claimant, changed-payload, revoked, forged, cross-site,
      cross-shift, foreign, stale, non-waiting, and infrastructure-failure paths
      fail closed without partial state or sensitive/internal-detail leakage.
- [ ] The private worklist shows only eligible unclaimed waiting rows and the
      claimant's own waiting/called row in FIFO order; another Operator sees
      neither the claimed/called row nor any claimant, Member, or clinical data.
- [ ] MVP-04L claim and MVP-04K readiness behavior remain intact, and no X-ray
      start, Encounter/protocol/capture, public display/audio, Member, clinical,
      privacy, dependency, documentation-status, commit, or push scope is added.
- [ ] Required focused, prerequisite, migration/schema, formatter, syntax,
      Composer, route, privacy, derived-intelligence, task, and final-diff
      checks pass with observed evidence.

## Verification

- Method: Validate this, MVP-04L, and MVP-04G tasks; run the focused MVP-04M test plus MVP-04L/K/J/H/G/F/E and affected Operator/security/architecture regressions; inspect a fresh migrated schema and private routes; run PHP syntax/static, Pint, Composer, privacy searches, Graphify/Codebase-Memory freshness and source checks, and `git diff --check`.
- Expected result: One trusted current claimant can idempotently and privately change one eligible X-ray admission from `waiting` to `called` while preserving its claim and FIFO identity; every competing, authorization, stale, failure, and privacy path is atomic and leak-free; no X-ray start, public display, clinical, Member, or broader scope is introduced.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, accepted baseline, selected runtime/model when verifiable,
  capabilities, outcome, Graphify and Codebase Memory status/actions/freshness,
  direct authority files, ponytail choice, affected interfaces/files,
  verification evidence, residual risks, deferred scope, and manual follow-up.
- Treat a changed claim, ticket, stage, site, schedule, or FIFO field; duplicate
  evidence; unauthorized success; public/clinical expansion; sensitive
  leakage; an unrun required check; or a commit/push as unsuccessful.

## Commit review handoff

Do not commit or push. Report final worktree state and readiness for an
owner-controlled commit. After the owner supplies a candidate SHA, review its
full chain against accepted baseline
`c4aebdae61b4e01cd361bee1265063ba72254d03`, this task, direct authoritative
repository evidence, and observed verification before accepting a new
baseline or selecting another slice.
