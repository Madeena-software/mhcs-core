---
title: Exact production DICOM remediation for T-005 and DCM-ZSHNSX90
document_id: MHCS-TASK-PRODUCTION-DICOM-EXACT-CASE-REMEDIATION-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-09-01
scope:
  - exact two-case production DICOM remediation
  - manually dispatched fail-closed workflow and supporting Image Gateway paths
authority_note: This task authorizes only the bounded implementation described here. It does not authorize production dispatch, production mutation, deployment, release, or changes to MPIPS.
---

# Executable Task

## Task identity

**Task title:**
`Provide exact two-case production DICOM remediation`

**Task path:**
`.agents/tasks/production-dicom-exact-case-remediation.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content.`

**Delivery objective / Work Package / MVP:**
`Exact production DICOM remediation — T-005 failed capture and DCM-ZSHNSX90 completed-study regeneration`

**Owner / designated planning authority:**
`Planner/Reviewer under the reconciled task-authoring handoff`

## Delivery context

Create one narrowly scoped, auditable, fail-closed production remediation
facility for exactly two existing radiography/DICOM cases. The facility has one
manual-only GitHub Actions workflow with exactly two bounded selections, while
the two selections retain distinct lifecycle and persistence behavior.

This task authorizes implementation and non-production verification only. Actual
production execution remains a later, fresh human-authorization boundary.

## Baseline and task revision

**Implementation baseline:**
`b257aea7f92fefdcc7e777f8f09a80eae12282dc`

**Task revision:**
`The full immutable commit SHA containing this exact task content, supplied by version-control history after publication.`

The implementation baseline and task revision are separate identities. Do not
use the task-publication commit as the implementation baseline.

## Objective

**Objective:**
Implement one manually dispatched, exact-target production remediation workflow
and the narrow repository-consistent Image Gateway support needed to safely:

1. correct and process the existing failed `T-005` capture; and
2. regenerate and atomically replace the DICOM object for the existing logical
   study `DCM-ZSHNSX90`.

The mechanism is not a generic repair console or reusable arbitrary-record
remediation framework.

## Authoritative inputs

### Governing authority

- Reconciled task-authoring/publication handoff dated 2026-09-01.
- `.agents/AGENTS.md` and `.agents/software-workflow.md`.
- `.agents/context/project.md` — MHCS architecture, runtime, storage, audit,
  deployment, and MPIPS boundaries.
- `.agents/context/modules/image-gateway/project.md` — Image Gateway source,
  manifest, integrity, processing, MPIPS, DICOM, retention, and ownership rules.
- `.agents/tasks/manual-workflow-dispatch-only.md` — current manual-only trigger
  policy.

### Observed implementation evidence

- `app/Modules/ImageGateway/Application/Jobs/ProcessCaptureSet.php` currently
  reads and verifies radiograph/gain/manifest objects, claims processing with a
  lease, validates MPIPS DICOM output, and creates a study for a successful
  capture.
- `app/Modules/ImageGateway/Application/Services/ImageGatewayCaptureService.php`
  currently contains the failed-processing requeue path.
- Existing deployment workflow tests establish repository conventions for
  manual-only triggers, revision guards, sanitized evidence, and serialized
  production concurrency.

Observed implementation is context, not permission to weaken the requirements
below. The Executor must verify current code before selecting reuse or extension.

### Requirement traceability

- Exact two-case reconciled handoff → objective, fixed target identities, one
  umbrella task, two distinct modes, and all case invariants below.
- `.agents/context/project.md` → single-app/module boundaries, controlled
  storage, audit, MPIPS separation, and deployment/release boundaries.
- `.agents/context/modules/image-gateway/project.md` → immutable NPZ sources,
  signed manifests, processing claims/attempts, DICOM validation, and retention.
- `.agents/tasks/manual-workflow-dispatch-only.md` → manual `workflow_dispatch`
  only, with no automatic trigger.

## Scope

### In scope

- One new GitHub Actions workflow, with manual `workflow_dispatch` only.
- Exactly two bounded mode selections, semantically equivalent to:
  `t005_failed_capture_retry` and `dcm_zshnsx90_regenerate`.
- Shared guarded dispatch, deployed-revision verification, fresh authorization
  marker, concurrency protection, sanitized evidence, and read-only preflight.
- A narrow T-005 correction/retry path that reuses the existing capture and both
  existing NPZ objects.
- A narrow DCM-ZSHNSX90 candidate-regeneration and active-object replacement path
  that reuses the existing logical study and relationships.
- Source, checksum, byte-count, manifest, signature, DICOM, idempotency, claim,
  and audit verification.
- Focused automated tests and applicable repository checks using non-production
  fixtures, fakes, mocks, or test storage.

### Out of scope

- Generic detector correction or retry in the Operator UI.
- Arbitrary production capture, admission, study, or record IDs.
- Generic completed-study regeneration or a generalized repair framework.
- New capture/session/admission for T-005 or a duplicate study for
  DCM-ZSHNSX90.
- Broad Image Gateway or study-persistence redesign, except an explicit stop and
  return to planning when the approved architecture cannot meet these invariants.
- MPIPS source or orientation-logic changes.
- Unrelated DICOM presentation or batch-download changes.
- Deployment, release, production dispatch, or mutation of either case.

### Preserved behavior

- Existing patient/member, booking, ticket, admission, site, operator, capture,
  and logical study relationships remain unchanged.
- Existing radiograph and gain NPZ bytes, object identities, and integrity
  evidence remain unchanged.
- Manifest/signature validation, permanent DICOM acceptance, idempotency,
  authorization, audit, and retention controls are not weakened.
- Historical manifest/signature and old DICOM evidence remain retained and
  auditable; no routine deletion is introduced.
- No patient-identifying or unnecessary sensitive content is written to logs or
  task evidence.

## Exact production cases

### T-005 failed capture

The fixed admission ID is:
`46165c59-1fa6-4f58-9485-a515529c0f76`.

The route UUID is an admission ID, not a capture ID. The future execution must
resolve exactly one associated capture and prove it is the intended `T-005`
radiography case before mutation. It must prove the admission/capture/booking/
ticket/site/operator relationships, both NPZ objects and their actual
checksums/byte counts, current detector `BED`, the expected signed pre-remediation
manifest and valid signature, genuinely failed retry-eligible processing, no
successful study, no incompatible claim/lease, and valid attempt budget.

Unexpected state fails closed. After the final race-safe recheck, the path may
coherently correct detector metadata and signed-manifest state from `BED` to
`TRX`, preserve prior integrity evidence and retry history, and invoke the
current DICOM path at most once. It must not alter NPZ bytes, create a capture/
session/admission, falsify attempts, clear MPIPS idempotency state, or bypass
acceptance gates. The resulting state and DICOM integrity must be verified.

### DCM-ZSHNSX90 completed study

The fixed study ID is:
`ed367bcf-4430-496c-a006-f3e8479421d4`.

Its display reference must be exactly `DCM-ZSHNSX90`. The future execution must
prove its exact capture/admission/booking/ticket/site/operator relationships,
original radiograph/gain NPZ integrity, valid signed manifest, existing detector
`TRX`, current DICOM object identity/checksum, and required deterministic DICOM
identifiers. If detector correction or another materially different repair is
needed, stop and return to planning.

The path must regenerate a candidate separately through current corrected MPIPS,
fully validate it and its required deterministic Study/Series/SOP identifiers,
then use a concurrency-safe/atomic transition to make it active. It must retain
the same logical study row, display reference, relationships, and audit history;
must not create a duplicate study or rewind ordinary failed-capture state; and
must retain the old DICOM object with auditable old/new identities and checksums.
The old object must not be deleted or overwritten before candidate validation.

## Dependencies and assumptions

### Dependencies

- The existing Image Gateway storage, manifest/signature, DICOM validation,
  audit, claim/lease, persistence, and MPIPS adapter mechanisms are available or
  can be safely extended within their approved ownership boundaries.
- The actual MPIPS runtime serving production can expose reliable revision
  evidence.
- The current approved schema/storage architecture can retain an old DICOM and
  atomically transition the active association, or the Executor stops for
  planning rather than inventing a new data model.

### Approved assumptions

- The two fixed identifiers and case descriptions above are approved human
  delivery authority, not proof of current production state.
- Existing implementation and tests are evidence of reusable mechanisms, not
  proof that the new remediation is safe or complete.
- The MPIPS orientation fix required by this task is commit
  `f2bf7b9980f9af7649e1a6c45c46aaee7a55a36a`; source changes belong to the MPIPS
  repository and are excluded here.

### Remaining approval requirements

- Fresh explicit human authorization is required immediately before any
  production workflow dispatch or production mutation.
- Deployment/release, infrastructure, permission, secret, schema, and external
  system changes require their applicable separate authorization.
- Task publication and implementation acceptance do not authorize production
  execution.

## Required capabilities

- Repository read/write and local command execution.
- Existing repository test, static-check, and workflow inspection tooling.
- Codebase Memory MCP or equivalent repository intelligence for impact discovery.
- Non-production fixtures/mocks/fakes/test storage for all verification.

## Execution constraints

### Constraints

- Use one workflow file and no automatic trigger: no `push`, `pull_request`,
  `schedule`, `workflow_run`, `repository_dispatch`, release, or equivalent.
- Do not accept arbitrary production IDs or expose a generic mutation interface.
  Fixed literals must be embedded or independently cross-checked before any
  mutation.
- Keep YAML thin: production repair logic belongs in narrow, testable,
  repository-consistent application paths.
- Perform a complete read-only preflight before mutation, including selected
  mode, fixed identity, relationship graph, state, attempts, detector, source
  identity/checksums/bytes, manifest/signature, claims/leases, study/object
  identity, expected UIDs, deployed MHCS revision, and serving MPIPS revision.
- Recheck all race-sensitive state immediately before mutation using an
  appropriate transaction, row lock, lease, compare-and-set, or equivalent
  concurrency mechanism. Any mismatch or drift fails closed.
- Prove the serving MPIPS runtime contains
  `f2bf7b9980f9af7649e1a6c45c46aaee7a55a36a` before either mutation mode.
  Historical source or deployment evidence alone is insufficient.
- Preserve existing Image Gateway integrity, idempotency, DICOM acceptance,
  authorization, and retention controls. Never clear Redis/idempotency state to
  force a result.
- Use minimum-necessary sanitized technical evidence; never log secrets,
  credentials, raw clinical payloads, or unnecessary patient identifiers.
- Do not perform production mutation during implementation or verification.

## Acceptance criteria

- [ ] Exactly one new manually dispatched workflow provides exactly the two
  approved remediation modes.
- [ ] The workflow has no automatic trigger and cannot mutate arbitrary IDs.
- [ ] Both modes complete a read-only, exact-target, integrity, state,
  dependency, runtime-revision, and race preflight and fail closed on mismatch.
- [ ] T-005 resolves the fixed admission to the intended single capture and
  permits only the verified failed `BED`/no-study state.
- [ ] T-005 reuses unchanged NPZ bytes, preserves relationships and retry
  history, and keeps detector metadata and signed manifest state coherent when
  transitioning to `TRX`.
- [ ] T-005 uses the current DICOM path at most once after the bounded correction
  and validates the resulting state and DICOM integrity.
- [ ] DCM-ZSHNSX90 resolves to the fixed study and display reference, uses the
  original verified sources, and requires existing `TRX` detector state.
- [ ] DCM-ZSHNSX90 preserves the same logical study/reference and relationships,
  creates no duplicate logical study, and validates required deterministic UIDs.
- [ ] A replacement DICOM is independently produced and fully validated before
  activation; the previous object remains retained and auditable.
- [ ] Serving production MPIPS revision inclusion of the required orientation
  fix is proven before either mutation path.
- [ ] Idempotency, signature, DICOM acceptance, authorization, retention, and
  concurrency controls are not bypassed or weakened.
- [ ] Focused tests cover successful eligibility, exact-target rejection,
  integrity/signature failures, state/detector/study mismatches, claim/race
  failures, runtime-proof failures, candidate validation failure with old object
  still active, UID mismatch, historical retention, and unrelated-record
  rejection for both modes.
- [ ] Applicable repository checks pass, including `git diff --check`.
- [ ] No production dispatch or mutation is required for implementation
  acceptance.

## Verification requirements

### Required checks

- Focused automated tests for both modes using isolated non-production state.
- Workflow parsing/static checks proving one manual-only workflow, two modes,
  fixed-target guards, least privilege, concurrency, and no automatic triggers.
- T-005 tests for wrong admission/capture/reference, wrong detector, non-failed
  or completed state, existing study, exhausted attempts, source checksum/byte
  mismatch, manifest/signature failure, active claim, idempotency conflict, and
  preservation of NPZ bytes/relationships/history.
- DCM-ZSHNSX90 tests for study/reference/relationship mismatch, detector not
  `TRX`, source or manifest failure, MPIPS proof failure, UID mismatch,
  candidate DICOM validation failure without active-object change, atomic
  transition, old-object retention, and duplicate-study rejection.
- Tests for runtime revision proof, exact mode/ID rejection, race/drift failure,
  and inability to affect unrelated records.
- Relevant Image Gateway unit/integration checks, repository-required lint/
  static checks, and `git diff --check`.

### Required evidence

The Executor MUST report the exact governing task revision, implementation
baseline, changed files, commands and observed results, test boundaries,
workflow trigger/mode audit, sanitized preflight/evidence design, deviations,
stop conditions, and any unavailable checks. Local checks must not be presented
as CI or production evidence.

## Stop conditions

Return to Planner/Reviewer without inventing a workaround when:

- either target cannot be resolved unambiguously or current production state
  differs materially from the approved premise;
- any source bytes, checksums, byte counts, manifest, signature, DICOM object,
  relationship, detector, state, attempt, claim, lease, idempotency, or UID
  premise fails;
- T-005 is not exactly failed `BED` with no successful study, or the completed
  study is not already authoritative `TRX`;
- the serving MPIPS runtime cannot prove inclusion of the required fix;
- current identity/idempotency behavior conflicts with safe processing;
- candidate validation, deterministic identity, atomic activation, or historical
  DICOM retention cannot be achieved under approved architecture;
- a new schema, architecture, product, security, privacy, or permission decision
  is required;
- implementation would broaden the facility beyond the two fixed cases; or
- any production side effect lacks fresh explicit authorization.

## Side-effect authorization

Implementation is limited to repository changes and non-production verification
within this task. It does not authorize production dispatch or mutation.

### Explicitly authorized side effects

- Create the implementation branch from the recorded baseline.
- Commit and non-force push implementation changes only if separately authorized
  by the execution handoff governing that implementation.

### Not authorized by this task

- Production workflow dispatch or mutation of T-005 or DCM-ZSHNSX90.
- Deployment, release, infrastructure, secret, permission, external-system,
  destructive, or history-rewriting operations.

## Expected terminal outcome

`REVIEW REQUIRED` when a reviewable implementation and truthful verification
evidence exist; otherwise `PLANNING REQUIRED` when a stop condition is reached.
Implementation acceptance remains separate from deployment, release, and
production execution authorization.
