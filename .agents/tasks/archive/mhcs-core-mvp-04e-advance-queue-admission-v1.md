---
name: mhcs-core-mvp-04e-advance-queue-admission
description: Atomically admit a newly checked-in advance booking to a private, FIFO basic-examination waiting list without adding clinical, claim, call, walk-in, or public-display behavior.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04E — Advance Queue Admission

## Objective

For `$TARGET`, extend the accepted MVP-04D verified check-in transaction so a
successfully issued private paper ticket creates exactly one Operator-owned,
append-only-recorded queue admission for the `basic_examination` stage in the
`waiting` state. An assigned Operator may view only the current active site's
assigned-shift advance-booking waiting list, ordered FIFO by successful
check-in time with a deterministic immutable tie-breaker. The private worklist
shows only the paper ticket number, site, shift time, stage, state, and ready
time; it contains no Member, booking, consent, identity, clinical, or queue
position data.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `codebase-memory-mcp`
  - `ponytail`
- Ordered model preferences: None.
- Require preferred model: `false`

Codebase Memory MCP and ponytail are mandatory. Keep ponytail at full level:
reuse the existing `DatabaseIdempotencyStore` outer transaction,
`OperatorCheckInTicketService`, Operator authorization and shift-assignment
checks, audit/outbox primitives, migration convention, and private portal
pattern. Do not add a queue framework, dependency, event bus, generic state
machine, queue-number generator, or configuration surface.

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Accepted baseline: `8ba97255bc1961945d9802a37d504442e3e1cf55`.
- The reviewed MVP-04D commit is accepted. It atomically performs the
  Member-owned `arrived` to `checked_in` transition, creates one
  `operator_paper_tickets` record, preserves issue/reprint idempotency, and
  renders a private paper ticket. Its evidence is at
  `$TARGET/docs/mvp/evidence/mvp-04d-verified-check-in-ticket-issue.md`.
- The governing queue rules in
  `$TARGET/.agents/context/modules/operator/project.md` require an immutable
  site-and-shift ticket, initial advance-booking FIFO by successful check-in
  time, `basic examination & vital signs` as the first physical stage, a
  `waiting` state, and append-only transition history. They also reserve claims,
  calls, skips, recalls, clinical work, walk-ins, and public LCD behavior for
  later work.
- Member remains the authority for bookings, consent, identifiers, and clinical
  history. Operator owns only its local queue admission record and its private
  worklist. The Operator must not directly mutate a Member booking.
- Related requirements: `OPR-020`, `OPR-026`, `OPR-108`, `OPR-115`, `OPR-116`,
  `OPR-117`, and `OPR-129`.
- Related Work Packages: WP-11, WP-12, and WP-17.
- Related gaps remain open: `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and
  `MVP-GAP-024`.

Read completely before planning or changing files:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/skills/fix-bug/SKILL.md` when a reproducible defect is
  encountered;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04c-paper-consent-confirmation-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04d-verified-check-in-ticket-issue-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/docs/mvp/evidence/mvp-04d-verified-check-in-ticket-issue.md`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorCheckInTicketService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorShiftAssignmentService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`;
- `$TARGET/app/Modules/Member/Application/Contracts/OperatorAttendanceContract.php`;
- `$TARGET/app/Modules/Member/Application/Services/Mvp04AttendanceService.php`;
- `$TARGET/app/Shared/Infrastructure/Idempotency/DatabaseIdempotencyStore.php`;
- `$TARGET/app/Http/Controllers/Operator/PortalController.php`;
- `$TARGET/routes/web.php`;
- `$TARGET/database/migrations/2026_08_07_000002_create_operator_paper_tickets_table.php`;
- `$TARGET/tests/Feature/Operator/Mvp04dVerifiedCheckInTicketIssueTest.php`; and
- `$TARGET/tests/Architecture/FoundationArchitectureTest.php`.

Use Codebase Memory MCP to verify canonical project/root and index freshness
before discovery. The review graph for `mhcs-core` at the accepted baseline has
4,011 nodes and 10,479 edges. Use no refresh if the current source and the
required check-in/ticket symbols are present; use a fast refresh only when the
source changed or a required symbol is absent; use a full re-index only when
the graph is missing or fast recovery fails. Trace `OperatorCheckInTicketService::issue`,
`OperatorAttendanceContract::transitionArrivedToCheckedIn`, the existing
idempotency/audit/outbox paths, active-site/shift authorization, ticket
persistence, and portal routes before selecting files. Record initial and final
graph status and every refresh action.

## Scope and constraints

Included:

- an Operator-owned queue-admission record that references exactly one existing
  `operator_paper_tickets` row, records the trusted local site and Member
  schedule, fixed queue class `advance`, stage `basic_examination`, state
  `waiting`, and the successful ticket-issue time as its ready time;
- one append-only initial queue-history record and one constrained Operator
  audit/outbox admission event, each created exactly once with the queue entry;
- extension of the existing ticket-issue transaction so the Member check-in,
  paper ticket, queue admission, queue history, Member/Operator audit/outbox
  evidence, and idempotency result commit or roll back together;
- database constraints and transaction-safe checks guaranteeing one queue entry
  per paper ticket and no duplicate initial history/admission event on replay
  or competing issue attempts;
- a single private authenticated Operator basic-examination worklist for an
  assigned shift at the active site, ordered by `ready_at` ascending and then a
  stable immutable identifier, displaying only the approved minimal queue
  fields; and
- revalidation of current account, role, portal access, active site, active
  site assignment, and current shift assignment on every admission and worklist
  read. Denied, cross-site, unassigned, revoked, malformed, or unavailable
  requests must fail closed and reveal no queue row.

For this task, a queue entry is the immutable initial admission of an existing
advance-booking paper ticket. Its ready time is the successful check-in time;
do not manufacture a new order, sequence, priority score, or Member-facing
position.

Excluded:

- Member booking, consent, identity, asset, account, clinical-history, points,
  or notification changes; retrospective backfill of existing tickets; and any
  direct Operator mutation of Member-owned data;
- queue claims, calls, recalls, skips, temporary-unavailable behavior,
  `in_service`, `awaiting_ai`, `deferred`, `completed`, stage progression,
  station selection, basic-examination/vital-sign data, encounters, X-ray,
  Image Gateway, AI, FHIR, cash closing, or no-show behavior;
- walk-ins, cross-class priority, shift-close behavior, queue exceptions,
  ticket-number generation, Member ticket visibility, public routes, LCD
  display/pairing, audio, PDF/print changes, queue-position exposure, and any
  Member, booking, consent, identity, or clinical value in the worklist;
- new permissions, dependencies, external systems, deployment, production
  access, commits, or pushes; and
- modifying existing published tasks, `.agents/context/**`, or
  `docs/implementation/**`.

Preserve every accepted MVP-04A through MVP-04D behavior. A failed queue
admission or queue audit/outbox/history write must leave the Member booking at
`arrived`, create no ticket, and leave no idempotency result marked handled.
A successful existing ticket replay must return the original result without a
second queue entry or admission evidence. The private worklist must never
become a public queue or a clinical-record view.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates:
  - This is a critical WP-12 workflow change. Before product code or migrations,
    present the narrow transaction, ownership, privacy, and FIFO plan and wait
    for explicit clinical, privacy, operations, and product approval.
  - Stop as `awaiting-approval` if implementation needs a new queue policy,
    walk-in ordering, a claim/call/skip action, clinical data, a public display,
    a Member-visible queue/ticket, a new permission, a different booking
    transition, or a retention/privacy-policy decision.
  - Stop as `blocked` if task validation, Codebase Memory MCP, ponytail, or the
    focused verification toolchain is unavailable.
  - Stop as `awaiting-approval` for migration incompatibility or overlapping
    owner-work scope beyond this task.

Use `single-pass` with exactly one iteration or `agentic-loop` with a positive finite limit. The task cannot grant permissions or bypass repository approval requirements.

## Execution procedure

1. Resolve `$TARGET` canonically; verify repository identity, branch, clean or
   owner-change worktree state, baseline ancestry, task immutability, and all
   required capabilities.
2. Validate this task with the repository validator before execution.
3. Verify ponytail at full level and record the existing transaction,
   idempotency, authorization, ticket, audit/outbox, migration, private-portal,
   and focused-test patterns that will be reused.
4. Verify Codebase Memory MCP freshness and trace the complete accepted
   MVP-04D issue path, its callers, constraints, and no-existing-queue result.
5. Present the exact minimal design and wait at the required approval gate.
   Confirm that this remains advance-booking admission only, with no clinical,
   public, or walk-in behavior.
6. Inspect schema conventions, migration ordering, architecture migration
   allowlist, current service boundaries, controller/routes/templates, and
   focused fixture setup before selecting the smallest compatible record and
   query changes.
7. Add only the minimal Operator-owned queue-admission and append-only initial
   history persistence. Extend the existing outer idempotency transaction so
   ticket issue and admission are atomic; use database constraints, row locks,
   and the existing replay result rather than a second idempotency flow.
8. Add one private, authorized, assigned-shift worklist route and template.
   Render only ticket number, site, shift time, stage, state, and ready time in
   deterministic FIFO order. Do not add queue mutation controls or public data.
9. Add the smallest focused regressions: successful atomic admission; exact and
   changed-input replay; competing same-ticket attempts; queue/history/audit/
   outbox failure rollback; sorted FIFO and stable ties; site/shift/account/
   permission revocation; cross-site and unassigned read denial; no Member or
   clinical payload; no claim/call/clinical/public side effect; and existing
   MVP-04D behavior unchanged.
10. Run the required verification separately, inspect the final diff and graph
    paths, then update only bounded MVP evidence/status documents whose facts
    changed. Keep all listed gaps and later workflows open.
11. Stop before any excluded queue action, clinical behavior, walk-in rule,
    public display, Member change, or deployment. Do not commit or push.

## Acceptance criteria

- [ ] Task validation, preflight, Codebase Memory MCP, ponytail, explicit
      approval, and the MVP-04D accepted-baseline checks pass before product
      changes.
- [ ] Each successful MVP-04D ticket issue atomically creates exactly one
      Operator-owned `advance` / `basic_examination` / `waiting` queue entry,
      its one append-only admission-history record, and required audit/outbox
      evidence; any failure leaves no partial Member, ticket, queue, history,
      audit, outbox, or handled-idempotency state.
- [ ] Existing ticket issue idempotency, Member check-in ownership, paper-ticket
      privacy, ticket-number uniqueness, and authorization remain unchanged;
      replays and concurrent attempts cannot duplicate admission.
- [ ] An active assigned Operator can read only its active-site, assigned-shift
      advance waiting entries in ascending ready-time order with a deterministic
      immutable tie-breaker; all authorization and scope failures fail closed.
- [ ] The worklist exposes only ticket number, site, shift time, stage, state,
      and ready time, and has no queue mutation controls, public/LCD surface,
      position, Member, booking, consent, identity, or clinical payload.
- [ ] No excluded scope, dependency, context/spec edit, commit, or push occurs.

## Verification

- Method: Validate the task; run focused MVP-04E admission/worklist, MVP-04D ticket/check-in, MVP-04C consent, MVP-04B identity, Operator portal/foundation, WP-02 security, and architecture suites separately; run PHP syntax and Pint on changed PHP files; inspect a fresh SQLite migration, routes, Codebase Memory call paths, sensitive-data searches, deterministic ordering, transaction rollback, and `git diff --check`.
- Expected result: Only an authorized assigned Operator's successful verified check-in atomically creates one private advance basic-examination waiting admission with one history/audit/outbox trail; retries and races do not duplicate it, authorization and all failures leave no partial state, the worklist is FIFO and privacy-safe, all regression checks pass, and no clinical, claim/call, walk-in, public-display, Member-data, or deployment behavior is introduced.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or `exhausted`.
- Report target, baseline, execution HEAD, selected runtime/model when
  verifiable, approval-evidence decision, capabilities, outcome, affected
  interfaces/files, Codebase Memory MCP and ponytail evidence, exact checks and
  results, unrun checks, residual risks, and manual follow-up.
- Treat an unapproved plan, a missing queue history/audit/outbox record, an
  unverified authorization/transaction/privacy boundary, an unverified patch,
  or any queue entry without the committed existing ticket/check-in transaction
  as unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report final worktree state and readiness for owner-controlled commit. After
the owner supplies an implementation commit SHA, review it against
`8ba97255bc1961945d9802a37d504442e3e1cf55` and this task before accepting a
new baseline or selecting the next vertical slice.
