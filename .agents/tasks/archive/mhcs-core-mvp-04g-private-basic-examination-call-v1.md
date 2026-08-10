---
name: mhcs-core-mvp-04g-private-basic-examination-call
description: Let the claimant of a private basic-examination admission atomically mark it called without adding public display or clinical behavior.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04G — Private Basic-Examination Call

## Objective

For `$TARGET`, extend the accepted MVP-04F private claim reservation with one
claimant-only queue transition: the Operator who currently holds an eligible
`basic_examination` admission may atomically change its state from `waiting` to
`called` at the trusted active site and assigned shift.

The call remains private. Preserve the existing claim owner and claim time,
stage, ticket, queue class, and FIFO-ready time. Append one `called` history
record, matching audit/outbox evidence, and exact idempotency evidence in the
same transaction. The claimant may see and call only their own claimed row;
other Operators must neither see nor call it. Do not implement public/LCD or
audio calling, recall, start, assessment, skip, release, no-show, walk-in, or
Member-facing behavior.

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

Graphify, Codebase Memory MCP, and ponytail are mandatory. Keep ponytail at
full level: reuse the existing `OperatorWorklistService` claim boundary,
`DatabaseIdempotencyStore`, Operator authorization/shift checks, admission and
history tables, audit/outbox stores, private route group, worklist view, and
MVP-04F fixture. Do not add a queue framework, policy, permission, generic
state machine, dependency, configuration surface, or second queue abstraction.

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Previously accepted baseline: `882a438947fc40fc43ba2e4e8864ce5ad18b2569`.
- Newly accepted MVP-04F baseline:
  `a02e01e75e14ae31607b9731dc44ec8f55e16150`, descended from the preceding
  baseline and the accepted MVP-04E closure chain.
- MVP-04F validates its contract and all prior MVP-04E contracts, and passes
  Pint, Composer validation, PHP syntax, route listing, MVP-04F (7 tests/58
  assertions), MVP-04E (6/61), MVP-04D (9/83), MVP-04C (6/64), MVP-04B
  (16/84), Operator portal (8/63), Operator foundation (15/56), WP-02 security
  (24/103), architecture (6/1,573), and diff checks.
- MVP-04F adds `operator_profile_id` and `claimed_at` to an admission, with a
  database uniqueness guarantee for one active claim per Operator profile. Its
  private worklist shows only eligible unclaimed entries or a claimant's own
  claimed entry; the current claim leaves state `waiting` unchanged.
- Direct Operator queue rules define `waiting`, `called`, and `in_service` as
  distinct states. Every claim, call, recall, skip, start, and completion
  records the responsible Operator and actual occurrence time. Queue calls are
  distinct from public-display/LCD behavior and must not be conflated here.
- Related requirements: `OPR-026`, `OPR-108`, `OPR-115`, `OPR-116`,
  `OPR-117`, and `OPR-129`.
- Related Work Packages: WP-11, WP-12, and WP-17.
- Open gaps remain: `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and
  `MVP-GAP-024`.

Read completely before planning or changing files:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/skills/fix-bug/SKILL.md`;
- `$TARGET/.agents/skills/graphify/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-advance-queue-admission-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-worklist-authorization-remediation-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-worklist-denial-matrix-remediation-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-acceptance-evidence-formatting-closure-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04f-atomic-basic-examination-claim-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/docs/mvp/evidence/mvp-04e-advance-queue-admission.md`;
- `$TARGET/docs/mvp/evidence/mvp-04f-atomic-basic-examination-claim.md`;
- `$TARGET/database/migrations/2026_08_07_000003_create_operator_queue_admissions_table.php`;
- `$TARGET/database/migrations/2026_08_08_000001_add_atomic_claim_to_operator_queue_admissions_table.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorShiftAssignmentService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`;
- `$TARGET/app/Http/Controllers/Operator/PortalController.php`;
- `$TARGET/app/Http/Middleware/EnsureOperatorPortalAccess.php`;
- `$TARGET/app/Shared/Infrastructure/Idempotency/DatabaseIdempotencyStore.php`;
- `$TARGET/app/Shared/Audit/DatabaseAuditStore.php`;
- `$TARGET/app/Shared/Infrastructure/Outbox/DatabaseOutboxStore.php`;
- `$TARGET/routes/web.php`;
- `$TARGET/resources/views/operator/basic-examination-worklist.blade.php`;
- `$TARGET/tests/Feature/Operator/Mvp04fAtomicBasicExaminationClaimTest.php`; and
- `$TARGET/tests/Architecture/FoundationArchitectureTest.php`.

Use Graphify first to identify the direct relationship among queue rules,
MVP-04F, WP-12, related requirements, gaps, and public-display exclusions.
Reuse the current graph when it contains the MVP-04F evidence and current queue
rules. If an incremental update is blocked because the installed Graphify CLI
lacks semantic-extraction credentials, do not install dependencies or request
credentials: record the limitation and directly inspect the authoritative files
above. Use Codebase Memory MCP to verify canonical project/index status and
trace the current claim, controller, route, authorization, idempotency, audit,
outbox, migration, and test paths; use a fast refresh only when a required
symbol is absent or source changed. Derived graphs are discovery aids, never
authority.

## Scope and constraints

Included:

- one claimant-only `waiting` to `called` transition for an existing claimed
  `advance` / `basic_examination` admission;
- one minimal idempotent application-service method, private authenticated POST
  endpoint, and worklist action affordance using only a route admission ID and
  operation identifier;
- one append-only `called` history record, audit event, and outbox event that
  commit atomically with the state transition; and
- focused tests for success, exact replay, competing/non-claimant attempts,
  stale/non-waiting/foreign admission, revoked account/portal/site/shift scope,
  audit/outbox rollback, claim preservation, FIFO preservation, and no-leak
  failure behavior.

Excluded:

- public/LCD or audio calling, public payloads, ticket display pairing, recall,
  start or `in_service`, clinical assessment/vital signs, station selection,
  skip, release, no-show, shift close, X-ray, walk-ins, payment, earnings,
  Member booking mutation, Member-visible data, or FHIR behavior;
- a new permission, shared middleware/policy redesign, generic queue state
  machine, migration rewrite, configuration, dependency, or test framework; and
- commits, pushes, deployment, real secrets, and production configuration.

Every operation must derive actor, permission, active site, assigned shift, and
claim ownership from the authenticated application context. Caller-supplied IDs
must never grant access. The action must fail closed without Member, booking,
consent, identity, clinical, other-Operator, or internal exception detail. The
private worklist may continue to expose only ticket, site, shift time, stage,
state, ready time, and opaque admission ID needed for the post; it must not
expose claimant identity to another Operator.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates: Before changing migration/schema, service, controller, route,
  view, test, or evidence, present the exact `waiting` to `called` transition,
  claimant/scope checks, HTTP conflict/forbidden behavior, idempotency payload,
  audit/outbox names, and proof that public calling, `in_service`, clinical
  behavior, and claim release remain excluded. Wait for explicit owner approval.
  Stop as `awaiting-approval` if it is absent or `blocked` if authoritative
  source requires a broader queue/public-display workflow.

## Execution procedure

1. Resolve `$TARGET` canonically; verify repository identity, clean-or-owner
   worktree state, accepted-baseline ancestry, immutable task content, and
   required capabilities. Preserve unrelated changes; do not reset, clean,
   stash, discard, stage, commit, or push.
2. Validate this task, MVP-04F, and all four MVP-04E contracts before editing.
3. Verify ponytail at full level and record the existing claim, idempotency,
   authorization, worklist, audit/outbox, route, and fixture patterns reused.
4. Query Graphify and Codebase Memory MCP using the declared freshness policy;
   directly inspect the authoritative documents and implementation paths they
   identify before making requirements, ownership, transition, or scope claims.
5. Run the current MVP-04F suite and inspect claim migration/schema, worklist
   projection, route group, claim transaction, and architecture migration
   allowlist. Establish the pre-change claim/FIFO/privacy baseline.
6. Present the approval-gate design. After explicit approval, implement only
   the claimant-owned `waiting` to `called` state change through existing
   authorization and idempotency boundaries; add a migration only if an exact
   call occurrence field is demonstrably absent and necessary.
7. Revalidate portal account, permission, active site, site assignment, assigned
   shift, admission scope, active claim ownership, stage, and current state
   inside the transaction. Ensure a non-claimant, competing operation, replay
   mismatch, or stale admission cannot change state or create partial evidence.
8. Add the smallest private form/action and focused tests. Assert exact replay
   creates no duplicate history/audit/outbox records; errors contain no protected
   or internal detail; and the claimant, claim time, queue class, stage, ready
   time, and FIFO ordering are unchanged.
9. Run MVP-04G, MVP-04F, MVP-04E, MVP-04D, MVP-04C, MVP-04B, Operator portal,
   Operator foundation, WP-02 security, and architecture suites separately;
   run fresh migrations, PHP syntax, Pint test mode, Composer validation,
   private route listing, migration/schema inspection, Graphify/Codebase-Memory
   final review, privacy-sensitive searches, and `git diff --check`.
10. Only after every check passes, update bounded MVP-04G evidence, roadmap, and
    work-package status with exact observed results and unchanged open gaps.
    Re-read final scope and do not commit or push.

## Acceptance criteria

- [ ] This task, MVP-04F, and all four MVP-04E contracts validate; baseline
      ancestry, current claim schema, Graphify status or limitation, Codebase
      Memory status, and authoritative source/documentation are observed before
      editing.
- [ ] Only the active claimant with trusted portal, site, and assigned-shift
      scope can atomically transition its eligible claimed admission from
      `waiting` to `called`; the claim owner/time, queue class, stage, ticket,
      ready time, and FIFO ordering remain unchanged.
- [ ] Non-claimant, competing, invalid/replayed operation, inactive account,
      revoked portal permission, revoked/forged site, revoked shift, foreign or
      stale admission, non-waiting state, and audit/outbox failure fail closed
      with no partial state/evidence or protected/internal-detail leakage.
- [ ] Exactly one `called` history record, one audit event, and one outbox event
      commit with a successful call; exact replay safely returns its original
      result and creates no duplicate record.
- [ ] Existing MVP-04F claim reservation, MVP-04E admission/check-in/ticket
      behavior, Member ownership, private worklist scope, authorization,
      idempotency, and all deferred public/clinical queue boundaries remain
      unchanged.
- [ ] All stated focused, regression, security, architecture, migration, syntax,
      Pint, Composer, route, privacy, graph/source, and diff checks pass with
      observed output; no dependency, real secret, commit, or push occurs.

## Verification

- Method: Validate this and all prerequisite MVP-04E/MVP-04F tasks; establish
  Graphify/Codebase-Memory status and directly inspect authority files; run
  MVP-04G plus MVP-04F/MVP-04E/MVP-04D/MVP-04C/MVP-04B/Operator portal/Operator
  foundation/WP-02 security/architecture suites separately, fresh migration and
  schema checks, PHP syntax, Pint test mode, Composer validation, private-route
  listing, privacy-sensitive output searches, final graph/source review, and
  `git diff --check`.
- Expected result: Only the trusted current claimant privately changes one
  eligible admission from `waiting` to `called`; one idempotent history/audit/
  outbox record is appended, all competing and unauthorized paths are safe and
  leak-free, accepted claim/admission/FIFO behavior remains intact, and no
  public, clinical, Member, dependency, commit, or push behavior is added.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, accepted baseline, selected runtime/model when verifiable,
  approval decision, capabilities, outcome, changed files, Graphify and
  Codebase Memory MCP status/actions/freshness, ponytail evidence, exact
  checks/results, unrun checks, residual risks, and manual follow-up.
- Treat a changed claim, stage, or FIFO field; duplicate evidence; non-claimant
  success; public/clinical expansion; sensitive leakage; skipped required check;
  or claimed rather than observed success as unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report final worktree state and readiness for owner-controlled commit. After
the owner supplies a candidate commit SHA, review it and its full chain against
accepted baseline `a02e01e75e14ae31607b9731dc44ec8f55e16150`, this task, all
MVP-04F and MVP-04E contracts, direct authoritative repository evidence, and
observed runtime evidence before accepting a new baseline or selecting another
vertical slice.
