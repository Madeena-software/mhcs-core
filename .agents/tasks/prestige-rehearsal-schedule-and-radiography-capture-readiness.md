---
title: Prestige Rehearsal Schedule and Radiography Capture Readiness
document_id: MHCS-TASK-PRESTIGE-REHEARSAL-RADIOGRAPHY-001
version: 1.0
status: validated-on-publication
language: en-US
last_updated: 2026-08-19
scope:
  - private Prestige rehearsal CSV validation and seed convergence
  - two-day Prestige rehearsal booking fixture
  - Operator assigned-shift live booking projection
  - Operator capture paper-ticket reference
  - Image Gateway capture metadata selects
  - browser upload speed and ETA telemetry
authority_note: This task is validated/published only when this exact content is committed and its immutable SHA is supplied in the Executor handoff. It does not authorize live database mutation, deployment, release, external clinical services, or private-data disclosure.
---

# Executable Task

## Task identity

**Task title:** `Prestige Rehearsal Schedule and Radiography Capture Readiness`

**Task path:** `.agents/tasks/prestige-rehearsal-schedule-and-radiography-capture-readiness.md`

**Task contract state:** `Validated/Published upon immutable publication of this exact content`

**Delivery objective / Work Package / MVP:** `27–28 August 2026 Prestige rehearsal and Operator radiography readiness`

**Owner / designated planning authority:** `Faliq Adlan, CTO`

## Delivery context

Prepare the private Prestige rehearsal fixture and the existing Operator
radiography capture flow for the 27–28 August 2026 rehearsal. This is one
coherent rehearsal outcome: both approved schedules must show the same 37
employees, and the Operator must see current capacity and have a usable,
localized capture workflow without weakening Member booking integrity or
exposing private employee data.

## Baseline and task revision

**Implementation baseline:** `0f6b1b56554f57396e9d03dd1b69871ba18702a0`

**Superseded draft:** `.agents/tasks/operator-capture-display-reference.md`

**Task revision:** `The full SHA of the commit containing this exact validated task content, supplied by the Planner after publication.`

## Objective

Make a clean or safely reconcilable Prestige bootstrap converge to two
quota-37 schedules on 27 and 28 August 2026, book all 37 private-source
employees on both schedules through the explicitly authorized fixture path,
and make the existing Operator capture workflow display the current live
capacity, paper-ticket reference, controlled metadata choices, and finite
upload progress telemetry.

## Authoritative inputs

### Governing authority

- Faliq Adlan, CTO, human-approved rehearsal objective supplied for this task
  on 2026-08-19: the same 37 CV Prestige employees are confirmed on both
  rehearsal dates; the private CSV remains ignored and untracked; no live
  database mutation or deployment is authorized.
- `docs/mvp/decision-log.md` MVP-DEC-042: the approved two-day Prestige
  rehearsal fixture exception.
- `.agents/context/project.md`: modular-monolith boundaries, Member data
  ownership, and Image Gateway boundary.
- `.agents/context/modules/member/project.md`: Member booking ownership and
  runtime invariant.
- `.agents/context/modules/operator/project.md`: paper-ticket and Operator
  assigned-shift workflow.
- `.agents/context/modules/image-gateway/project.md`: protected admission,
  capture, and clinical-data boundaries.
- `.agents/context/ui-language.md` and MVP-DEC-037: Indonesian JSON-backed
  browser copy.

### Requirement traceability

- `PRESTIGE-001` through `PRESTIGE-004` → CTO rehearsal objective and
  MVP-DEC-042.
- `OPERATOR-001` through `OPERATOR-003` → Operator module context and
  MVP-DEC-042's radiography workflow readiness objective.
- `IMAGE-001` through `IMAGE-003` → Image Gateway module context and
  MVP-DEC-037.
- `UI-001` → `.agents/context/ui-language.md` and MVP-DEC-037.

## Scope

### In scope

- Keep `research/prestige/data-karyawan-cv-prestige.csv` as the default
  private employee source and allow a private externally supplied path through
  the smallest existing seeder configuration pattern. Validate availability,
  readability, exact row count (37), required fields, and duplicate employee
  identifiers before creating target schedules or bookings. Tests generate a
  synthetic temporary CSV at runtime and never require or inspect the real CSV.
- Replace the Prestige seed-owned schedule definitions with exactly
  `2026-08-27 01:00:00`–`10:00:00` and `2026-08-28 01:00:00`–`10:00:00` in
  `Asia/Jakarta`, both quota 37. Reconcile only clearly Prestige seed-owned
  obsolete schedule state. Inspect downstream foreign-key/clinical records
  first; refuse unsafe reconciliation rather than deleting progressed history.
- Preserve normal `Mvp03BookingService` behavior and its one-active-booking
  invariant. In the authorized `PrestigeClinicSeeder` fixture path only, ensure
  every one of the 37 Members has one confirmed booking on each target
  schedule, preserving the existing Prestige funding/offering/ledger shape.
  The expected fixture counts are 37 unique Members, 37 bookings on each date,
  and 74 total bookings. Re-running the seeder is idempotent.
- Project the current authoritative participating booking count for assigned
  shifts and the actual schedule quota for the denominator. Count confirmed,
  arrived, checked-in, in-progress, and completed bookings; exclude cancelled,
  refunded, no-show, and other non-participating states. Do not mutate or
  repurpose `confirmed_count_at_eligibility`.
- Extend the existing authorized `ImageGatewayCaptureService` admission
  projection to return the joined `operator_paper_tickets.ticket_number` and
  render that value as the visible radiography session reference. Keep the
  admission UUID in routes, lookups, authorization, form/status URLs, audit,
  queue, and idempotency paths, but do not render it as the human-facing
  reference. Do not add a new identifier or public ticket lookup.
- Replace body-part, laterality, and projection input/datalist controls with
  native selects populated from the existing Image Gateway constants, keeping
  CHEST/U/PA defaults, old-input behavior, server validation, frozen metadata,
  DICOM/manifest semantics, and Indonesian localization.
- Extend the existing `XMLHttpRequest.upload` progress handler only. Use
  `loaded`, `total`, and monotonic browser elapsed time to display percentage,
  readable uploaded/total bytes, finite speed, and finite upload-only ETA.
  Handle zero/initial/unknown-length states without NaN or Infinity, show a
  neutral calculating state until speed is meaningful, clear telemetry at 100%,
  preserve beforeunload protection, accessibility live status, and existing
  polling, and do not add a package or endpoint.
- Add focused regression coverage for the synthetic seeder, safe failure and
  idempotency, both-day counts, ordinary Member booking enforcement, assigned
  shift projection, capture ticket reference and UUID preservation, native
  selects and invalid metadata rejection, upload telemetry edge cases, and
  Indonesian copy. Use only existing test infrastructure and synthetic
  browser/image data.

### Out of scope

- Committing, staging, copying, serializing, documenting, or exposing the real
  Prestige CSV or any employee name, NIK, address, credential, or raw source
  content.
- Changing the normal Member runtime booking service or database invariant;
  adding a special route, identifier table, public ticket lookup, or alternate
  capture architecture.
- Broad schedule deletion, clinical-history deletion, destructive live-data
  cleanup, migrations, production database mutation, deployment, release,
  reseeding `fams.mhcsgo.cloud`, AWS/S3/MPIPS mutation, real NPZ/DICOM use, or
  external clinical-service calls.
- New upload libraries, chunking, WebSockets, server-side upload progress,
  endpoints, test frameworks, dependencies, or unrelated refactoring.

### Preserved behavior

- Normal Member booking creation continues to reject a second active booking.
- Existing Prestige offering, funding, ledger, authorization, queue, audit,
  idempotency, manifest, and DICOM semantics remain unchanged except for the
  explicitly scoped fixture/UI projections.
- The paper-ticket number remains the existing authorized operational
  reference; the internal admission UUID remains the protected technical key.
- Existing upload acceptance, retry, beforeunload, polling, accessibility, and
  localization conventions remain in force.

## Dependencies and assumptions

### Dependencies

- The private CSV may be absent from a Git checkout; synthetic test data must
  provide the test input.
- Existing migrations and foreign keys are the authority for determining
  whether an obsolete seed-owned schedule is safe to reconcile.
- Existing `Mvp03BookingService::capacityStatuses()` is the source for
  participating booking-state semantics unless current repository evidence
  requires a bounded extension for terminal completed bookings.

### Approved assumptions

- The two-day double booking is an explicitly authorized rehearsal/bootstrap
  fixture exception and is not a new Member product rule.
- The existing `operator_paper_tickets.ticket_number` joined by the admission
  query is the required human-readable capture reference.
- The current XHR upload flow and existing Indonesian translation registry are
  adequate; no new browser architecture is required.

### Remaining approval requirements

- None for local code, tests, task publication, implementation commit, or push
  when all required checks pass. Deployment, release, live database mutation,
  external-service mutation, and private-data access remain unauthorized.
- If old schedule reconciliation finds downstream clinical/progressed data that
  cannot be proven seed-owned and safely removed, stop and return that cleanup
  to Planner/Reviewer.

## Required capabilities

- Repository read/write, local PHP/Node test and build execution, Git history
  and diff inspection, Graphify/Codebase Memory discovery when useful, and no
  external clinical or production-system access.

## Execution constraints

- Validate and parse the employee source before schedule/booking mutation;
  validation failure must not leave newly created Prestige schedules.
- Use a transaction or equivalent atomic boundary for the seed-owned schedule,
  member, and booking mutation where the existing schema permits it. Do not
  rely on the normal runtime booking service for the fixture exception.
- Scope reconciliation by the Prestige site/offering and seed-owned schedule
  identity. Before deleting anything, verify all dependent records; fail closed
  on progressed or ambiguous data.
- Never print private source rows or credentials in test output, task evidence,
  commit messages, logs, or documentation.
- Prefer existing helpers, constants, status sets, query patterns, translation
  keys, and test fixtures. Do not add an abstraction with one consumer.

## Acceptance criteria

- [ ] Missing, unreadable, malformed, duplicate, or non-37-row employee input
  fails clearly before target schedules are created; a private override path is
  supported; tests use a generated synthetic 37-row CSV.
- [ ] A clean Prestige bootstrap converges to exactly two target schedules on
  27 and 28 August 2026 in the existing site timezone, each with quota 37,
  with no obsolete 14 or 26 August schedule in the intended seeded state.
- [ ] The verified synthetic fixture contains 37 unique Members, 37 confirmed
  bookings on 27 August, 37 on 28 August, 74 total, and remains unchanged on a
  second seed run.
- [ ] Ordinary Member booking tests still prove the one-active-booking rule;
  the two-day duplicate Member assignment exists only in the Prestige seeder.
- [ ] Assigned shifts display live authoritative participating counts and real
  schedule quota; the verified Prestige fixture renders 37 / 37 for both
  target schedules without using the stale eligibility snapshot as numerator.
- [ ] The authorized capture page visibly uses the existing paper-ticket number
  and does not use the admission UUID as its session reference; protected UUID
  routes, authorization, form action, status URL, audit, queue, and idempotency
  behavior remain intact.
- [ ] Body part, laterality, and projection are native selects with CHEST/U/PA
  selected by default; valid values submit, invalid values remain rejected,
  and frozen metadata is unchanged.
- [ ] Active XHR upload progress reports percentage, readable amount, finite
  speed, and finite upload-only ETA when calculable; unknown/initial/zero states
  are neutral and 100% transitions to existing processing status without fake
  ETA or NaN/Infinity.
- [ ] New visible copy is represented in `lang/id.json`; focused affected tests,
  `vendor/bin/phpunit`, `npm run build`, `vendor/bin/pint --test`, and
  `git diff --check` pass. Applicable browser tests are run according to
  repository convention and any timeout is reported as a gap, not a pass.

## Verification requirements

### Required checks

- Focused Prestige, Member booking, Operator assigned-shift/capture, Image
  Gateway metadata, localization, and JavaScript upload tests discovered during
  implementation.
- `TARGET="." vendor/bin/phpunit`
- `TARGET="." npm run build`
- `TARGET="." vendor/bin/pint --test`
- `TARGET="." git diff --check`
- Applicable browser tests, sequentially when database-resetting; no live
  browser or external clinical service is required to prove the synthetic
  fixture.

### Required evidence

Report the exact governing task path and immutable revision, implementation
baseline and commit, changed files, observed commands/results, synthetic
counts only, UI projection results, capture reference behavior, select/telemetry
coverage, and any verification gap. Confirm the real CSV was not staged or
committed without printing its contents.

## Stop conditions

- `origin/main` or the implementation baseline contains overlapping unreviewed
  changes in the affected paths.
- A currently published task or pending review must be resolved before this
  task's dependent work can safely proceed.
- The fixture exception cannot be implemented without weakening normal Member
  booking integrity, authorization, or downstream clinical invariants.
- Obsolete schedule reconciliation would delete or rewrite progressed clinical
  history, or seed ownership cannot be established safely.
- The private CSV would need to be committed, copied into tracked source, or
  exposed in evidence.
- The existing paper-ticket join cannot project `ticket_number` without a new
  identity or access-control decision.
- The six requested outcomes require a new upload architecture, dependency,
  endpoint, deployment, live mutation, or unrelated scope.

## Side-effect authorization

### Explicitly authorized side effects

- Publish this task and the required decision-log/draft-supersession planning
  records, commit them, and push `main`.
- Modify only the bounded implementation, focused tests, and Indonesian
  localization required by this task; commit the implementation and push
  `main` after all required checks pass.
- Run local tests, builds, format/diff checks, and synthetic database fixtures.

Not authorized: deployment, production/live database mutation, reseeding
external environments, AWS/S3/MPIPS/clinical-service calls, dependency
installation, private-source inspection that discloses data, or release.

## Expected terminal outcome

`REVIEW REQUIRED` — return one reviewable implementation revision with truthful
local verification evidence. Final implementation acceptance remains the
Planner/Reviewer responsibility and is not release authorization.
