---
name: mhcs-core-mvp-04k-basic-examination-completion-xray-readiness
description: Complete an approved vital-signs assessment and atomically advance the same private ticket to X-ray readiness.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04K — Basic-Examination Completion and X-Ray Readiness

## Objective

For `$TARGET`, let the current claimant complete an eligible `in_service`
basic-examination admission only after its approved MVP-04J vital-signs record
exists. Atomically complete the basic stage, release its live Operator claim,
and create one unclaimed `waiting` X-ray-stage admission for the same private
paper ticket, site, and shift. Expose that ready ticket in a private,
authorization-scoped X-ray worklist without implementing X-ray execution.

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
- Accepted baseline: `a225b8719b26057b91dcbb968c4dcec27d156872`.
- Directly inspect the immutable MVP-04J capture and positive-input remediation
  tasks/evidence. Their accepted boundary stores exactly one Member-owned
  vital-signs assessment and one Operator execution for an `in_service`
  claimant-owned admission; it does not complete or advance the queue.
- Directly inspect
  `docs/mvp/evidence/mvp-04i-clinical-privacy-decision-closure.md`. Its bounded
  completion decision requires the approved blood-pressure, temperature,
  height, weight, and BMI fields to have values or allowed missing reasons,
  then advances the same ticket to X-ray. MVP-04J already enforces that record
  invariant; do not re-read or expose clinical values during completion.
- Directly inspect `.agents/context/modules/operator/project.md`, especially
  Basic examination, Queue rules, and NPZ draft/submission flow; the
  requirements matrix for OPR-021 through OPR-026, OPR-031, OPR-108,
  OPR-115 through OPR-117, OPR-129, and OPR-134; and the implementation plan,
  roadmap, decision log, beta-gap register, and work-package status for WP-07,
  WP-11, WP-12, WP-14, WP-17, APP-002, APP-004, RISK-004, MVP-GAP-009, and
  MVP-GAP-021.
- Directly inspect `OperatorWorklistService`, `PortalController`, `routes/web.php`,
  both private worklist views, MVP-04J tables/tests, and the queue-admission and
  atomic-claim migrations. The current schema permits only one admission per
  paper ticket and one live claim per Operator, so stage advancement must
  preserve per-stage uniqueness while releasing the completed live claim.
- Use Graphify for documentation relationships and Codebase Memory MCP for
  route/service/migration/test impact and freshness. Derived indexes are
  discovery aids; inspect the exact repository files before making material
  implementation decisions.

## Scope and constraints

Included:

- one claimant-only, idempotent completion command and private action for an
  advance, basic-examination, `in_service` admission with exactly one valid
  MVP-04J Operator execution linked to its Member assessment;
- one transaction that locks and revalidates the admission, persisted account,
  Operator permission, active site, active shift assignment, claimant,
  assessment/execution association, stage, and state; marks the basic-stage
  admission `completed`; records append-only completion history; releases its
  live `operator_profile_id`/claim time; and creates exactly one unclaimed
  X-ray-stage `waiting` admission and initial history with `ready_at` equal to
  the actual completion occurrence time;
- the minimal additive migration replacing paper-ticket global uniqueness with
  an explicit one-admission-per-ticket-per-stage constraint, while preserving
  foreign keys, FIFO indexes, existing data, and the live one-claim-per-
  Operator invariant;
- a private assigned-shift/site-scoped X-ray waiting worklist containing only
  ticket number, site, shift time, stage, state, and ready time; and
- focused success, missing-assessment, replay, competing completion,
  authorization, constraint, privacy, and audit/outbox rollback coverage.

Excluded:

- X-ray claim/call/start, Encounter or FHIR resources, protocol snapshots,
  NPZ drafts/uploads, Image Gateway submission, public/LCD/audio behavior,
  clinical-value display, deferred glucose/cholesterol/uric-acid/interview
  capture, Member-facing UI, retention/deletion/anonymization mechanisms,
  dependencies, commits, and pushes.

Preserve one human-readable paper ticket across stages, stage-local FIFO,
completed-work attribution in execution/history/audit evidence, Member clinical
ownership, and existing claim/call/start/vital-signs behavior. Completion and
X-ray admission must roll back together on any failure. Audit/outbox/worklist
payloads must contain no Member, booking, clinical value, unit, missing reason,
assessment content, or consent/identity data.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates: The MVP-04I completion decision authorizes only completion
  of the approved MVP-04J bundle and readiness for X-ray. Stop as
  `awaiting-approval` before adding clinical fields/ranges, FHIR/Encounter,
  X-ray execution, protocol/capture behavior, broader access, or destructive
  privacy operations. Do not commit or push.

## Execution procedure

1. Resolve `$TARGET`; verify baseline ancestry, published-task validation,
   worktree ownership/state, required capabilities, and existing database
   constraints. Preserve unrelated work; do not reset, clean, commit, or push.
2. Query Graphify and Codebase Memory MCP and refresh only if relevant tracked
   evidence is stale. Directly inspect all named authority, code, migration,
   route, view, and test files plus actual index/constraint names before edits.
3. Trace the admission lifecycle and choose the smallest existing transaction,
   idempotency, history, audit, outbox, authorization, and private-worklist
   patterns. Record the Ponytail choice and the exact claim-release invariant.
4. Add the minimal schema evolution and implement completion, claim release,
   X-ray waiting admission, private readiness worklist, controller/routes/views,
   and focused tests. Do not load clinical columns to decide completion; the
   existence and binding of the accepted execution/assessment are sufficient.
5. Verify exact replay, changed-payload conflict, concurrent attempts, missing
   or foreign execution, stale/revoked/non-claimant/cross-site/cross-shift
   denial, unique ticket-stage enforcement, released claim reuse, FIFO ready
   time, audit/outbox privacy, and complete rollback on injected failures.
6. Run focused MVP-04K and prerequisite MVP-04J/H regressions, fresh migration
   and schema checks, formatter, syntax/static, route, privacy, Composer,
   Graphify/Codebase-Memory, task, and diff checks. Inspect actual outputs and
   final diff, then provide commit-review handoff against the accepted baseline
   without committing or pushing.

## Acceptance criteria

- [ ] Only the current authorized claimant can complete an eligible basic-stage
      admission with one correctly bound MVP-04J execution/assessment.
- [ ] Completion, append-only history, live-claim release, audit/outbox evidence,
      and one unclaimed X-ray `waiting` admission commit atomically and replay
      without duplication.
- [ ] The same paper ticket/site/shift is preserved; the schema permits exactly
      one admission per ticket per stage and still prevents two live claims for
      one Operator.
- [ ] The private X-ray waiting worklist is assignment/site scoped, FIFO by its
      stage-ready time, and reveals only approved operational ticket fields.
- [ ] Missing, stale, foreign, revoked, conflicting, and failure paths neither
      change queue state nor persist/leak clinical or Member data.
- [ ] No X-ray execution, Encounter/FHIR, protocol, NPZ, Image Gateway, public,
      deferred clinical, dependency, retention, deletion, commit, or push scope
      is included.
- [ ] Focused and prerequisite suites plus required migration, privacy, task,
      and final diff checks pass with observed evidence.

## Verification

- Method: Run the focused MVP-04K completion/X-ray-readiness suite and MVP-04J/H regressions; run fresh migrations and constraint inspection, formatter, syntax/static, route, privacy/leakage, Composer, Graphify/Codebase-Memory, task, and `git diff --check` checks; manually inspect the private completion action, X-ray worklist, histories, audit/outbox metadata, and claim release.
- Expected result: An authorized claimant with one valid MVP-04J record can idempotently complete basic examination, release the live claim, and place the same ticket exactly once into an unclaimed private FIFO X-ray waiting queue, while every denial/failure remains atomic and privacy-safe and no X-ray execution or FHIR behavior is introduced.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, accepted baseline, selected runtime/model when verifiable,
  Graphify/Codebase Memory actions or limitations, direct authority files,
  Ponytail choice, affected interfaces/files, schema and transaction evidence,
  verification results, residual risks, deferred scope, and manual follow-up.
- Include commit-review handoff: compare the candidate with
  `a225b8719b26057b91dcbb968c4dcec27d156872`, confirm exact MVP-04 completion
  and X-ray-readiness scope with no privacy/FHIR expansion, and report no
  commit or push.
