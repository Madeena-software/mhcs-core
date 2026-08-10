---
name: mhcs-core-mvp-04j-private-vital-signs-capture
description: Capture the approved private MVP-04 vital-signs bundle for a claimant-owned in-service basic-examination admission.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04J — Private Vital-Signs Capture

## Objective

For `$TARGET`, let the claimant assigned to an eligible `in_service`
basic-examination admission record the approved private vital-signs bundle:
blood pressure, temperature, height, weight, and calculated BMI. Store a
Member-owned longitudinal record through an explicit local contract, with an
Operator-owned execution record, in one idempotent transaction. Keep the data
FHIR-aligned internally without creating FHIR resources, profiles, APIs, or
exchange behavior.

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
- Accepted baseline: `c542b07cab53ef93f43a62f491ae06511150f674`.
- Directly inspect the MVP-04H start task/evidence and the latest MVP-04I
  clinical/privacy closure evidence. The current owner decision for this slice
  makes blood pressure, temperature, height, weight, and BMI mandatory;
  glucose, total cholesterol, uric acid, and the structured interview are
  deferred. Every mandatory field has either a value or one of
  `unavailable`, `refused`, or `not_applicable`.
- The current owner decision fixes units as `mmHg`, `°C`, `cm`, `kg`, and
  `kg/m²`; it supplies no clinical thresholds or ranges. Validate structure,
  units, and missing-value semantics only—do not invent clinical ranges,
  diagnoses, or safety assertions beyond `screening result; not a diagnosis`.
- Directly inspect `.agents/context/modules/operator/project.md`, the current
  requirements matrix (OPR-021 through OPR-025, OPR-108, OPR-115 through
  OPR-117, and OPR-129), the implementation plan (WP-07, WP-11, WP-12,
  WP-17, APP-002, APP-004, and RISK-004), roadmap, beta-gap register, and
  work-package status. The current owner decision narrows the MVP-04 mandatory
  bundle; it does not close the deferred requirements or MVP-GAP-021.
- Directly inspect `OperatorWorklistService::startBasicExamination()`, its
  controller/routes/view/tests, queue-admission migrations, and the existing
  `OperatorAttendanceContract` pattern. The start boundary is claimant-only,
  idempotent, transactional, audited, and outboxed; it stores no assessment.
- Use Graphify for documentation relationships and Codebase Memory MCP for
  route/service/caller/contract discovery and freshness. Derived results are
  not authority: inspect each cited document and source file directly before
  deciding implementation details.

## Scope and constraints

Included:

- Member-owned longitudinal vital-signs persistence and an explicit local
  Operator-to-Member command/contract; an Operator-owned execution association
  identifying the authorized performer, site, admission, occurrence time, and
  idempotency operation;
- a claimant-only private Operator form/route for an `in_service`, advance,
  basic-examination admission, with fixed approved units, required value-or-
  missing-reason semantics, and server-calculated BMI when height and weight
  are values;
- one atomic, replay-safe submission that rechecks persisted account,
  Operator permission, active site, active shift assignment, claim ownership,
  admission scope, and `in_service` state; writes append-only audit and outbox
  evidence without clinical values in audit/outbox metadata;
- focused positive, replay, validation, authorization, privacy, rollback, and
  FHIR-alignment tests. FHIR alignment means stable observation-like subject,
  performer, effective-time, value, unit, and missing-data semantics only.

Excluded:

- glucose, cholesterol, uric acid, interview capture, queue completion or
  X-ray advancement, Encounter/FHIR resources, profiles, terminology codes,
  validators, APIs, external exchange, public/LCD data, Member-facing UI,
  clinical ranges/diagnosis, retention/deletion/anonymization mechanisms,
  broader access audiences, dependencies, commits, and pushes.

Preserve claim/call/start behavior, queue order, ticket identity, Member
ownership, consent boundaries, and existing authorization/audit contracts. A
clinical record must never be revealed in the worklist, redirect status,
validation errors, audit metadata, outbox payloads, or unauthorized responses.
Use the smallest existing Laravel, local-contract, audit, outbox, idempotency,
and Blade patterns; no new abstraction or dependency. Do not add a `ponytail:`
comment unless a real simplification ceiling remains.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates: The approved MVP-04I closure governs only the listed bundle
  and fixed units. Stop as `awaiting-approval` before adding any clinical
  range, additional assessment field, completion transition, FHIR artifact,
  access-audience change, retention/deletion behavior, or scope that conflicts
  with the owner decision. Do not commit or push.

## Execution procedure

1. Resolve `$TARGET`; validate baseline ancestry and prerequisite tasks, check
   worktree ownership/state and capabilities, then read repository instructions
   and the direct authority files. Preserve unrelated work.
2. Query Graphify and Codebase Memory MCP; refresh only if relevant tracked
   evidence or code is newer. Directly inspect the exact files identified,
   including route/controller/service callers, migrations, contracts, tests,
   UI language, and applicable approved Operator UI references.
3. Trace the existing start-to-submission boundary and choose the smallest
   existing local-contract pattern that gives Member longitudinal ownership and
   one atomic database transaction. Record the Ponytail choice and why no FHIR
   package/resource is needed for this bounded capture.
4. Implement the private schema, local command, service, route, form, and
   tests. Treat all form input as untrusted; calculate BMI server-side only
   from stored height and weight values, keep missing reasons distinct from
   values, and prevent duplicate/cross-admission writes.
5. Verify the complete vertical slice, including replay, failed audit/outbox
   rollback, invalid/mixed value-reason payloads, non-claimant/revoked/
   cross-site/cross-shift denial, and absence of clinical leakage. Run the
   smallest relevant suite plus prerequisite regressions, formatter, migration,
   route, privacy, static/syntax, Composer, Graphify/Codebase-Memory, task,
   and diff checks; inspect actual output.
6. Re-read the unchanged task, inspect the final diff for scope creep and
   unapproved FHIR/clinical/privacy behavior, and provide commit-review handoff
   against `c542b07cab53ef93f43a62f491ae06511150f674`. Do not commit or push.

## Acceptance criteria

- [ ] Only the approved vital-signs bundle is captured privately with fixed
      units, value-or-missing-reason semantics, and server-calculated BMI.
- [ ] The record is Member-owned through an explicit local contract, while the
      Operator execution remains attributable, authorized, atomic, idempotent,
      auditable, and outboxed without leaking clinical values.
- [ ] Every submission revalidates account, permission, active site, shift,
      claimant, admission scope, and `in_service` state; forbidden/conflicting
      requests neither persist data nor disclose it.
- [ ] The design is FHIR-aligned internally without FHIR resources, profiles,
      terminology mappings, validation packages, APIs, or external exchange.
- [ ] No deferred assessment, queue completion, X-ray, retention/deletion,
      broader privacy policy, dependency, commit, or push work is included.
- [ ] Focused and prerequisite regressions plus required checks pass with
      observed output and no unrelated worktree changes.

## Verification

- Method: Run the focused MVP-04J feature/service tests and prerequisite MVP-04H regressions; run fresh migrations, formatter, syntax/static, route, privacy/leakage, task, Graphify/Codebase-Memory, Composer, and `git diff --check` checks; inspect the final schema, local contract, audit/outbox metadata, and private UI manually.
- Expected result: An authorized claimant can record exactly one private, replay-safe, Member-owned vital-signs assessment for an eligible in-service admission; BMI is server-calculated, every mandatory field has a value or approved reason, all denial/rollback paths persist and reveal no clinical data, and no FHIR integration or deferred behavior is added.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, accepted baseline, selected runtime/model when verifiable,
  Graphify/Codebase Memory actions or limitations, direct authority files,
  Ponytail choice, affected interfaces/files, migration and transaction design,
  verification evidence, residual risks, deferred scope, and manual follow-up.
- Include commit-review handoff: compare the candidate with
  `c542b07cab53ef93f43a62f491ae06511150f674`, confirm the exact scope and no
  clinical leakage/FHIR integration, and report no commit or push.
