---
name: mhcs-core-mvp-04h-private-basic-examination-start
description: Add one private, claimant-only start transition for an already-called basic-examination admission without entering clinical assessment.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04H — Private Basic-Examination Start

## Objective

For `$TARGET`, add one private, idempotent Operator queue transition for the
current claimant's eligible `basic_examination` admission: atomically change
`called` to `in_service`, with complete local history, audit, and outbox
evidence. This is only queue coordination; it must not record a clinical
assessment, create an Encounter, or advance the ticket to another stage.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `codebase-memory-mcp`
  - `graphify`
- Ordered model preferences: None.
- Require preferred model: `false`

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Accepted baseline: `c3b2537960ef7e82e9a068f73e414ba0ae40ff50` (MVP-04G).
- Inspect the immutable predecessor task
  `.agents/tasks/mhcs-core-mvp-04g-private-basic-examination-call-v1.md`, its
  evidence `docs/mvp/evidence/mvp-04g-private-basic-examination-call.md`, and
  current roadmap/status/gap records before changing behavior.
- Repository authority for this slice is
  `.agents/context/modules/operator/project.md`, especially Queue rules:
  supported states include `called` and `in_service`, and every start records
  the responsible Operator and actual occurrence time. Read it directly.
- Also directly inspect `docs/implementation/mhcs-core-requirements-matrix.md`
  for OPR-026, OPR-108, OPR-115, OPR-116, OPR-117, and OPR-129;
  `docs/implementation/mhcs-core-implementation-plan.md` for WP-11/WP-12/WP-17;
  and `docs/mvp/roadmap.md`, `docs/mvp/decision-log.md`,
  `docs/mvp/beta-gap-register.md`, and `docs/mvp/work-package-status.md`.
- Read current source and tests directly, including
  `app/Modules/Operator/Application/Services/OperatorWorklistService.php`,
  `app/Http/Controllers/Operator/PortalController.php`,
  `app/Http/Middleware/EnsureOperatorPortalAccess.php`,
  `app/Http/Middleware/EnforceMandatoryPasswordChange.php`, `routes/web.php`,
  `resources/views/operator/basic-examination-worklist.blade.php`, and
  `tests/Feature/Operator/Mvp04gPrivateBasicExaminationCallTest.php`.
- Use Graphify first to locate the documentation relationships and determine
  whether its existing graph is current. Use an incremental update only when
  tracked relevant documentation is newer. Use Codebase Memory MCP to verify
  the canonical index/freshness, then inspect the worklist, claim, and call
  symbols plus their routes, callers, and tests. Derived graphs are discovery
  aids only; direct repository files and observed commands remain authority.

## Scope and constraints

Included:

- one private POST action, controller handler, service operation, smallest
  claimant-only worklist form, and focused feature test for `called` to
  `in_service`;
- transactional reuse of the existing authorization, idempotency, audit,
  outbox, clock, admission-history, active-site, and assigned-shift patterns;
- one `started` history record with actual occurrence time, matching audit and
  versioned outbox evidence, and an opaque idempotency operation identifier;
- bounded evidence/roadmap/status updates only after all checks pass.

Excluded:

- clinical values, interview, vital signs, diagnoses, consent, Encounter or
  FHIR creation, capture, submission, or completion;
- recall, skip, release, no-show, queue correction, walk-ins, next-stage
  routing, public/LCD/audio display, Member visibility, and data-retention or
  deployment work;
- new queue schema, dependencies, broad authorization redesign, and changes to
  accepted admission, claim, or call behavior.

The action must use only trusted authenticated context, not caller-supplied
operator, site, shift, claim, or scope identifiers. It must preserve the claim
owner and claim time, queue class, stage, ticket, ready time, and FIFO fields.
Non-claimants must not see actionable or identifying information for a claimed
admission. Keep the existing worklist's bounded fields and do not add Member,
booking, identity, consent, clinical, or claimant-identity data.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates: Before editing source, migrations, routes, views, tests, or
  documentation, present the smallest implementation plan and affected files
  to the owner and wait for explicit approval. Do not create an Encounter,
  clinical behavior, public display, walk-in behavior, or a broader queue
  state/action without a separate explicit owner approval. Stop as
  `awaiting-approval` if approval is absent.

## Execution procedure

1. Resolve `$TARGET`; verify repository identity, worktree ownership/state,
   accepted-baseline ancestry, immutable predecessor-task validation, and
   required capabilities. Preserve unrelated changes; do not reset, clean,
   commit, or push.
2. Read all authoritative files listed above. Run Graphify documentation
   discovery and Codebase Memory MCP symbol/path discovery; record their
   freshness action or limitation, then directly inspect the source files they
   identify before making implementation decisions.
3. Trace the existing claim and call paths end to end. Confirm that current
   admission-history occurrence time and the existing transaction/outbox
   boundary already support this one state change; do not add schema or a new
   abstraction unless direct evidence proves it necessary and approval covers it.
4. Present the minimal plan and affected-file list for approval. If approved,
   implement only a current-claimant `called` to `in_service` operation using
   the existing `OperatorWorklistService` patterns, with a new purpose/event
   name scoped to start and no clinical payload.
5. Revalidate trusted portal, account, permission, active site, current shift
   assignment, admission site/schedule scope, claimant ownership, queue class,
   stage, and `called` state inside the idempotent transaction. Lock the
   admission before transition and fail closed without sensitive detail.
6. On success, atomically persist only the state change, one `started` history
   row, one audit event, and one versioned outbox event. Exact replay returns
   the original result without duplicate evidence; altered replay conflicts;
   audit/outbox failure rolls back every write.
7. Add the smallest private form and route. Provide a focused
   `tests/Feature/Operator/Mvp04hPrivateBasicExaminationStartTest.php` covering
   positive preservation, exact/changed replay, privacy, claimant competition,
   authorization revocations, stale/foreign/unclaimed/non-called admissions,
   rollback, and no clinical/public data exposure.
8. Run the focused suite and the MVP-04G/MVP-04F/MVP-04E/MVP-04D/MVP-04C/MVP-04B,
   Operator portal/foundation, WP-02 security, and architecture regressions
   separately. Also run the smallest relevant migration/schema, PHP syntax,
   Pint, Composer, route-list, privacy-search, graph/source, task-validation,
   and `git diff --check` checks.
9. Only when every acceptance criterion and verification check passes, update
   bounded MVP-04H evidence, roadmap, and work-package status with observed
   results and unchanged open gaps. Re-read final scope; do not commit or push.

## Acceptance criteria

- [ ] This task and prerequisite MVP-04G/MVP-04F/MVP-04E contracts validate;
      baseline ancestry, Graphify/Codebase-Memory freshness, and direct
      authority/source evidence are observed before implementation.
- [ ] Only the active trusted claimant can atomically change its eligible
      `advance` / `basic_examination` admission from `called` to `in_service`;
      claim, class, stage, ticket, ready time, and FIFO fields remain unchanged.
- [ ] A successful start writes exactly one `started` history row with actual
      occurrence time, one matching append-only audit event, and one versioned
      outbox event in the same transaction; exact replay is duplicate-free and
      changed replay conflicts.
- [ ] Invalid operation identifiers; non-claimants; inactive account;
      revoked permission, site, or shift; forged active-site context; foreign,
      stale, unclaimed, wrong-scope, or non-called admissions; and audit/outbox
      failure all fail closed with no partial state/evidence or protected detail.
- [ ] The existing MVP-04G call, MVP-04F claim, MVP-04E admission/check-in/
      ticket behavior, Member ownership, privacy boundary, and private worklist
      remain intact. No clinical, Encounter/FHIR, public, Member, audio/LCD,
      next-stage, dependency, commit, or push behavior is added.
- [ ] Focused and stated regression, syntax, formatting, Composer, route,
      migration/schema, privacy, graph/source, validator, and diff checks pass
      with observed evidence.

## Verification

- Method: Validate prerequisite and MVP-04H tasks; establish Graphify and
  Codebase Memory MCP freshness and directly inspect authority files; run the
  MVP-04H focused suite plus MVP-04G/MVP-04F/MVP-04E/MVP-04D/MVP-04C/MVP-04B,
  Operator portal/foundation, WP-02 security, and architecture suites
  separately; then run relevant schema/migration, PHP syntax, Pint, Composer,
  route-list, privacy-search, graph/source, and `git diff --check` checks.
- Expected result: The trusted current claimant privately and idempotently moves
  one eligible admission from `called` to `in_service`, producing one atomic
  history/audit/outbox record while preserving prior queue behavior and adding
  no clinical, public, Member, dependency, commit, or push behavior.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, accepted baseline, selected runtime/model when verifiable,
  Graphify and Codebase Memory MCP freshness/actions, direct authority files,
  approval state, affected interfaces/files, verification evidence, residual
  risks, deferred scope, and manual follow-up.
- Include commit-review handoff: compare the candidate diff with accepted
  baseline `c3b2537960ef7e82e9a068f73e414ba0ae40ff50`, verify only this bounded
  state transition changed, and report no commit or push.
