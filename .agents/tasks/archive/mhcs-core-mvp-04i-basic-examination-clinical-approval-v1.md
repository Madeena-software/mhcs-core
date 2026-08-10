---
name: mhcs-core-mvp-04i-basic-examination-clinical-approval
description: Obtain and record the explicit clinical and privacy decisions required before any basic-examination assessment data is implemented.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04I — Basic-Examination Clinical Approval Gate

## Objective

For `$TARGET`, establish whether the owner has explicitly approved the clinical
and privacy contract required to implement basic examination and vital-signs
assessment for an already `in_service` admission. If the required approvals are
absent, stop as `awaiting-approval` without changing product code, schema,
routes, tests, or product behavior. If supplied, record only the owner-approved
decision and an exact bounded contract for a later implementation task.

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
- Accepted baseline: `a7d8f361fa19f5404062b7b543c47a2da2dea658` (MVP-04H).
- Directly inspect the immutable predecessor task
  `.agents/tasks/mhcs-core-mvp-04h-private-basic-examination-start-v1.md` and
  `docs/mvp/evidence/mvp-04h-private-basic-examination-start.md`. The accepted
  state transition is only `called` to `in_service`; it deliberately records no
  clinical assessment, Encounter, or next-stage routing.
- Directly inspect `.agents/context/modules/operator/project.md`, especially
  `Basic examination & vital signs assessment` and `Queue rules`. The source
  defines one mandatory assessment bundle, unavailable/refused/not-applicable
  reasons, interview response constraints, Member longitudinal ownership, and
  transaction expectations. It is the clinical source authority.
- Directly inspect `docs/implementation/mhcs-core-requirements-matrix.md` for
  OPR-020 through OPR-025 and OPR-108, OPR-115 through OPR-117, and OPR-129;
  `docs/implementation/mhcs-core-implementation-plan.md` for WP-07, WP-11,
  WP-12, WP-17, APP-002, APP-004, and RISK-004; and
  `docs/mvp/roadmap.md`, `docs/mvp/decision-log.md`,
  `docs/mvp/beta-gap-register.md`, and `docs/mvp/work-package-status.md`.
- `MVP-GAP-021` states that privacy, retention, deletion, and anonymization
  procedures for clinical flows remain unresolved. The approval register names
  APP-002 (clinical/interoperability) and APP-004 (privacy/legal) as separate
  decision authorities. Do not infer either approval from existing MVP-04 work.
- Use Graphify first to locate the documentation relationships and reuse/update
  its graph only when relevant tracked documentation is newer. Use Codebase
  Memory MCP to check canonical index identity/freshness and inspect the current
  `OperatorWorklistService`, start route/controller, existing assessment tables
  if any, and related tests. Derived tools are discovery aids only; direct
  repository documents and observed source/configuration are authority.

## Scope and constraints

Included:

- a read-only approval and contract review for the exact basic-examination
  assessment bundle following an MVP-04H `in_service` transition;
- collection of explicit owner-provided APP-002 and APP-004 approval evidence;
- only after both approvals are explicit, a minimal decision-log/evidence
  record of the approved contract, owner, date, scope, constraints, and later
  task inputs.

Excluded:

- all product code, migrations, clinical tables, forms, routes, APIs, tests,
  audit/outbox events, queue transitions, Encounter/FHIR resources, and
  Member-contract changes;
- recording or handling actual member clinical data, synthetic assessment data,
  retention/deletion implementation, public/LCD/audio behavior, walk-ins,
  X-ray/NPZ work, dependencies, commits, and pushes;
- treating documentation, a generic user message, Graphify, Codebase Memory,
  or this task itself as clinical or privacy approval.

No caller may supply, persist, or inspect clinical values in this task. Preserve
the accepted claim, call, and start behavior unchanged. Do not invent clinical
thresholds, validation ranges, data-retention periods, lawful bases, FHIR
profiles, or outcome language.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates: APP-002 clinical/interoperability approval and APP-004
  privacy/legal approval must be explicit, attributable, dated, and scoped to
  the exact clinical assessment data before any record is written or any later
  implementation is planned in detail. If either is absent, stop as
  `awaiting-approval`; do not ask the runtime to fabricate approval or make
  product changes. A later implementation task requires separate owner approval
  before schema or product edits.

## Execution procedure

1. Resolve `$TARGET`; verify repository identity, accepted-baseline ancestry,
   immutable predecessor-task validation, worktree ownership/state, and required
   capabilities. Preserve unrelated changes; do not reset, clean, commit, or
   push.
2. Run Graphify documentation discovery and Codebase Memory MCP discovery,
   recording current/freshness status or a tool limitation. Directly read every
   authoritative document and source file identified before making a material
   claim.
3. Confirm from source that MVP-04H reaches `in_service` without storing
   clinical data, and inventory only the existing adjacent contracts/tables;
   do not create fixtures or probe with clinical values.
4. Present the precise approval request: approval of the mandatory assessment
   bundle, allowed missing-value reasons, clinician-owned validation/units,
   longitudinal Member ownership/contract, retention/deletion handling, and
   whether a later completion may create the next queue stage. Ask the owner to
   supply attributable APP-002 and APP-004 evidence.
5. If either approval is unavailable, report `awaiting-approval` with the exact
   missing authority and the no-change evidence; stop immediately.
6. If both approvals are supplied, directly verify their scope against the
   source requirements. Record only a concise, factual decision/evidence entry
   in the existing MVP documentation pattern, including approved limits and
   explicit unresolved items. Do not implement the assessment or alter any
   product file.
7. Validate the changed decision/evidence document if one was authorized,
   re-read the task scope, run `git diff --check`, and provide a commit-review
   handoff. Do not commit or push.

## Acceptance criteria

- [ ] The MVP-04H predecessor task validates; accepted-baseline ancestry,
      Graphify/Codebase-Memory status, and direct repository authority are
      observed before any decision or documentation change.
- [ ] The result distinguishes APP-002 from APP-004 and never treats a missing,
      generic, stale, unattributed, or broader approval as authorization for
      clinical assessment data.
- [ ] Without both approvals, the task ends `awaiting-approval` with no product
      code, migration, schema, route, test, clinical data, or behavior change.
- [ ] With both explicit approvals, the only change is an accurate bounded
      decision/evidence record that identifies the approved contract, owner,
      date, constraints, retained open decisions, and the required inputs for a
      separate clinical implementation task.
- [ ] Claim/call/start behavior, Member ownership, queue state, privacy
      boundary, open gaps, dependencies, commits, and pushes remain unchanged.

## Verification

- Method: Validate MVP-04H and this task; inspect the named source authorities,
  approval register, gap register, current start implementation, and any
  owner-supplied approval evidence; then run documentation/task validation as
  applicable and `git diff --check`.
- Expected result: The task either stops safely awaiting explicit APP-002 and
  APP-004 approval with no repository product change, or records only a
  verifiable approved clinical contract for a later task while leaving all
  clinical implementation and existing MVP-04 behavior untouched.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, accepted baseline, selected runtime/model when verifiable,
  Graphify and Codebase Memory MCP freshness/actions or limitations, direct
  authority files, the exact approval state, affected documentation if any,
  verification evidence, residual risks, deferred scope, and manual follow-up.
- Include commit-review handoff: compare any documentation-only candidate with
  accepted baseline `a7d8f361fa19f5404062b7b543c47a2da2dea658`, confirm no
  product behavior changed, and report no commit or push.
