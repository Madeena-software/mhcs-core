---
title: Atomic Automatic Operator Ticket Allocation
document_id: MHCS-TASK-OPERATOR-TICKET-ALLOCATION-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-12
scope:
  - automatic site-and-shift ticket-number allocation
  - concurrent Operator check-in integrity
  - local MySQL concurrency evidence
authority_note: This task becomes executable only when its exact content is committed and published as validated.
---

# Executable Task

## Task identity

**Task title:**
`Atomic Automatic Operator Ticket Allocation`

**Task path:**
`.agents/tasks/mvp-operator-automatic-ticket-allocation.md`

**Task contract state:**
`Validated/Published when this exact content is committed`

**Delivery objective / Work Package / MVP:**
`12 August MVP delivery target / safe multi-Operator front-desk ticket issue`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

A blank ticket number is generated as `T-001`, `T-002`, and so on for the
active site and shift. The current `count() + 1` calculation is within an
idempotency transaction that locks only its individual operation ID. Two
different Operators can calculate the same number before either insert; the
database rejects one collision, leaving a normal eligible Member unissued.

The clinic-day flow permits multiple assigned Operators to serve different
patients concurrently. This task serializes only the shared site-and-shift
automatic allocation while retaining the existing Member check-in, ticket,
queue-admission, audit, outbox, and idempotency transaction.

It is a prerequisite for the local synthetic clinic rehearsal. The existing
launch guide at `.agents/tasks/mvp-local-synthetic-rehearsal-launch.md` is not
executable at its `87a6b2a…` baseline after the owner-reviewed `89a6649…` and
localization changes; it will be reconciled only after this task is accepted.

## Baseline and task revision

**Implementation baseline:**
`1052d49a2fe2680ec854c6e295fdb31643a62851` — reviewed localization result and
owner-reviewed current clinic-flow state. The formal accepted baseline remains
`87a6b2ac8649ecb1e692fdaf553d4212a3f00910` pending consolidated review.

**Task revision:**
`Resolve from the commit that publishes this exact task content before execution.`

## Objective

**Objective:**
Make concurrent automatic ticket issuance for distinct eligible Members at one
site and shift allocate distinct sequential ticket numbers and complete both
valid check-ins, without changing manual ticket behavior or existing
clinical/queue/privacy boundaries.

## Authoritative inputs

### Governing authority

- `.agents/context/modules/operator/project.md` — multiple assigned Operators
  may work concurrently; one human-readable ticket number is unique within its
  site and shift; a ticket follows verification and paper consent.
- `docs/mvp/decision-log.md` — MVP-DEC-021 (37-member clinic-day scope) and
  MVP-DEC-023 (paired Operator printing and public LCD policy).
- `docs/mvp/beta-scope.md` — authenticated Operator ticket issue and Printer
  Station are in the clinic-day flow.
- `.agents/context/project.md` — one application database and transactions for
  approved cross-module invariants.
- Owner-reviewed `89a6649…` — blank ticket input invokes automatic allocation;
  this task corrects only its concurrency integrity.

### Requirement traceability

- OPR-015..OPR-030 and MVP-DEC-023 → a private printable site-and-shift ticket
  after the required front-desk gates.
- Operator queue rule → ticket number is unique within site and shift even when
  different assigned Operators work concurrently.
- MVP-DEC-021 → multi-Operator clinic-day use must be reliable before rehearsal
  is deployability evidence.

## Scope

### In scope

- Correct `OperatorCheckInTicketService` blank-number allocation so competing
  allocations for the same existing `shift_schedules` row serialize inside the
  existing idempotency transaction.
- Reuse that row and Laravel `lockForUpdate()` as the allocation lock. Acquire
  it before counting tickets and retain it through ticket insertion. Do not add
  a sequence table, distributed lock, queue, migration, or shared abstraction.
- Add focused sequential and true-concurrent automatic-allocation regression
  coverage, using two distinct fully eligible cases and two assigned Operator
  identities at the same site/shift.
- Add a MySQL separate-process `proc_open` concurrency probe following the
  established pattern in `Mvp04nXrayProtocolConfigurationTest`. It must release
  two prepared requests together and prove successful `T-001` and `T-002`
  issuance with one ticket, queue admission, audit/outbox record, and handled
  idempotency record per Member.

### Out of scope

- Ticket format/reset policy, manual ticket validation, printing/reprinting,
  queue order/stages, LCD content, Member ticket visibility, consent, identity,
  NIK, capture, DICOM, MPIPS, storage, schema, migrations, or dependencies.
- Retrying or hiding a unique-key failure instead of preventing the collision.
- Server deployment, real-member import/data/credentials, release, or local
  rehearsal documentation/execution.

### Preserved behavior

- A supplied manual ticket is normalized, validated, unique, and persisted as
  before; automatic allocation applies only to blank input.
- One booking creates at most one ticket; same-operation replay, changed replay,
  authorization, verification, consent, active-site, and shift boundaries stay
  fail-closed.
- Member `arrived` to `checked_in`, ticket, queue admission/history, audit,
  outbox, and idempotency state retain their existing all-or-nothing outcome.
- The unique database constraint remains the final integrity guard. No protected
  Member, consent, clinical, NPZ, or DICOM data enters test output or logs.

## Dependencies and assumptions

### Dependencies

- `1052d49a2fe2680ec854c6e295fdb31643a62851` and the existing
  `operator_paper_tickets` site/schedule/number unique constraint.
- A disposable local MySQL database and `proc_open` for the mandatory proof.
  A skipped probe is not sufficient acceptance evidence.

### Approved assumptions

- The persisted `shift_schedules` row is a stable existing lock scope for all
  allocations within that site and shift.
- A short row lock around the existing application transaction is appropriate
  for clinic-day ticket allocation; it is not external processing.

### Remaining approval requirements

- Consolidated review of `89a6649…`, localization, and this implementation is
  required before a new accepted baseline.
- The local rehearsal task must be republished against that later baseline.
- Deployment, release, real data/import, and MPIPS remain separately approved.

## Required capabilities

- Repository read and write.
- Local PHP/Laravel test execution using synthetic data.
- Disposable local MySQL plus `proc_open` for concurrency verification.
- Local npm/Vite build execution.

## Execution constraints

### Constraints

- Keep the change in `OperatorCheckInTicketService` and its focused tests;
  reuse current transaction, idempotency, audit, outbox, and lock patterns.
- Lock the existing schedule row only for automatic allocation. Never calculate
  `count() + 1` before that lock or release the lock before ticket insertion.
- Do not turn a unique-key conflict into success, retry with a changed operation
  identity, or change manual-ticket behavior.
- The concurrent test must use different Operator identities, Members/cases/
  bookings, and operation IDs. A sequential simulation is insufficient.
- Do not disclose synthetic passwords, protected identifiers, database DSNs, or
  database passwords. Keep MySQL data local and disposable.

## Acceptance criteria

- [ ] Blank-number check-in locks its existing site/shift schedule before
  allocation and retains the lock through ticket insertion.
- [ ] Two separately assigned Operators concurrently issue blank-number tickets
  for two distinct verified-and-consented Members at one site/shift; both
  succeed with exactly `T-001` and `T-002`, without a unique-key error.
- [ ] Each Member is `checked_in` with exactly one ticket, queue admission and
  history, matching issue/queue audit and outbox records, and handled
  idempotency state; no extra or partial records exist.
- [ ] Manual issue, same-operation replay, changed replay conflict,
  authorization/scope denials, and the database constraint remain unchanged.
- [ ] Focused PHP regressions and the non-skipped MySQL two-process probe pass
  with synthetic data; no migration, dependency, deployment, external call,
  real data, or secret disclosure occurs.

## Verification requirements

### Required checks

- Run focused ticket, consent, identity-verification, arrival, Operator portal,
  and advance-queue suites, including sequential and concurrent coverage.
- Run the new MySQL concurrency probe against a disposable local database. It
  must not be skipped; report driver and outcomes without connection values.
- Run `npm run build` and `git diff --check`.

### Required evidence

The Executor must report the implementation revision; every command actually
run; test totals; MySQL/`proc_open` availability; two concurrent outcomes and
persisted ticket numbers; audit/outbox/idempotency counts; build warnings
separately from failures; changed files; known gaps; and explicit confirmation
that no server, deployment, MPIPS, real data, external mutation, dependency,
or secret disclosure occurred.

## Stop conditions

- Stop if the schedule row cannot be locked inside the existing transaction
  without a new schema, transaction, or architecture decision.
- Stop if a true two-process MySQL proof cannot run on a disposable database;
  do not claim concurrency acceptance from SQLite or a sequential test.
- Stop if preserving idempotency, manual tickets, check-in atomicity,
  queue/audit/outbox, or authorization requires scope expansion.
- Stop if the baseline changes or other pending implementation overlaps
  `OperatorCheckInTicketService` before review.

## Side-effect authorization

### Explicitly authorized side effects

- Repository changes limited to `OperatorCheckInTicketService` and focused
  synthetic ticket-allocation/concurrency tests.
- Disposable local MySQL test data and normal PHP/Vite build artifacts.

Not authorized: Git commit, push, pull request, deployment, release, external
mutation, production/server databases, real data, credentials, MPIPS calls,
dependency changes, migrations, or unrelated code/documentation changes.

## Expected terminal outcome

`IMPLEMENTATION AND VERIFICATION RESULT REQUIRED` — the Executor returns the
immutable implementation revision and MySQL concurrency evidence. The
Planner/Reviewer then performs consolidated review and, only on acceptance,
reconciles the local-rehearsal task to the new accepted baseline.
