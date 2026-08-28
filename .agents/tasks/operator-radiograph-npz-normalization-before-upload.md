---
title: Operator Radiograph NPZ Normalization Before Upload
document_id: MHCS-TASK-OPERATOR-RADIOGRAPH-NPZ-NORMALIZATION-001
version: 1.0
status: draft
language: en-US
last_updated: 2026-08-28
scope:
  - browser-side normalization of future operator radiograph NPZ uploads
  - preservation of the existing Image Gateway to private storage to MPIPS flow
authority_note: This Draft task is planning authority only. It is not Validated/Published and does not authorize implementation, deployment, production mutation, historical-object changes, or MPIPS changes.
---

# Executable Task

## Task identity

**Task title:**
`Normalize radiograph NPZ before operator upload`

**Task path:**
`.agents/tasks/operator-radiograph-npz-normalization-before-upload.md`

**Task contract state:**
`Draft`

**Delivery objective / Work Package / MVP:**
`Operator capture upload-size reduction while preserving the Image Gateway → private storage → MPIPS conversion flow`

**Owner / designated planning authority:**
`Planner/Reviewer under the approved human decision dated 2026-08-28`

## Delivery context

The designated human authority approved removal of the redundant `processedimage`
NPZ entry from future radiograph submissions before network upload. The change
reduces operator-to-MHCS upload time while preserving the existing authenticated
capture flow and normal MPIPS conversion. The supplied planning evidence shows a
material reduction from approximately 71.8 MB to 9.9 MB on example radiographs;
that observation is not a universal percentage threshold.

This task is one coherent browser-to-processing outcome. It does not authorize
MPIPS implementation changes or production validation.

## Baseline and task revision

**Implementation baseline:**
`2e088c668fb4cc262e45767198aa44badb07aac7`

**Task revision:**
`resolved when published`

The task remains Draft. The exact immutable governing task revision must be
resolved before any future Validated/Published handoff.

## Objective

**Objective:**
Before the operator browser transmits a selected radiograph NPZ to MHCS, safely
construct a normalized NPZ that omits the redundant `processedimage` archive
member and upload that normalized file through the existing capture flow, while
preserving all other required radiograph content and existing Image
Gateway/MPIPS behavior.

## Authoritative inputs

### Governing authority

- Approved human decision dated 2026-08-28, recorded in the governing planning request.
- `.agents/AGENTS.md` and `.agents/software-workflow.md` — delivery, evidence, and side-effect boundaries.
- `.agents/context/project.md` — MHCS architecture, canonical source, storage, and MPIPS boundary.
- `.agents/context/modules/image-gateway/project.md` — Image Gateway submission, persistence, idempotency, retention, and access boundaries.
- `.agents/tasks/production-real-npz-end-to-end-validation.md` — historical real-NPZ validation purpose and explicitly superseded future-upload assumption.

### Observed implementation inputs

- `resources/js/operator-upload.js` — current browser `FormData`/`XMLHttpRequest` upload and progress path.
- `app/Http/Controllers/Operator/ImageGatewayController.php` — current authenticated HTTP application boundary.
- `app/Modules/ImageGateway/Application/Services/ImageGatewayCaptureService.php` — source acceptance and persistence path.
- `app/Modules/ImageGateway/Application/Jobs/ProcessCaptureSet.php` — current private-object and MPIPS handoff path.
- `app/Modules/ImageGateway/Infrastructure/MpipsClient.php` — observed radiograph/gain MPIPS request contract.
- `tests/JavaScript/operator-upload.test.mjs` and relevant Image Gateway/operator integration tests — existing verification surfaces.

### Requirement traceability

- `OPERATOR-RADIOGRAPH-NPZ-001` → approved 2026-08-28 decision; browser removes only `processedimage.npy` from `radiograph_npz` before upload.
- `OPERATOR-RADIOGRAPH-NPZ-002` → approved decision; normalized bytes are the canonical stored, checksummed, persisted, idempotent, and MPIPS-submitted radiograph source.
- `OPERATOR-RADIOGRAPH-NPZ-003` → approved decision; gain NPZ, historical objects, access boundaries, and normal MPIPS conversion remain unchanged.
- `OPERATOR-RADIOGRAPH-NPZ-004` → approved decision; malformed/ambiguous archives fail closed before transmission without heavy-file fallback or pickle/object deserialization.

## Scope

### In scope

- Browser/operator-client normalization for `radiograph_npz` only, at the ZIP archive-member boundary, before the multipart HTTP request begins.
- Removal of the exact lower-case `processedimage.npy` member when it exists, with no `None`, `null`, empty-array, placeholder, or zero-filled replacement.
- Already-normalized radiographs without that member remaining uploadable without artificial failure and without unnecessary rewriting when safe to pass through unchanged.
- Fail-closed handling and clear pre-upload operator status/error behavior for malformed archives, archive corruption, or materially ambiguous duplicate target entries; never silently upload the original heavy file after normalization failure.
- Integration with `resources/js/operator-upload.js`, using the normalized `File`/`Blob` in the actual `FormData` submitted by the existing capture flow.
- Upload progress, byte count, size, speed, and related telemetry based on bytes actually transmitted, including evidence of material size reduction without a hardcoded reduction threshold.
- Preservation of all non-target member names and payloads, including `rawimage`, `gainid`, `id`, `xrayparams`, `cameraparams`, and any other non-target members; archive compression metadata may differ when rebuilt.
- Preservation of the source filename as an `.npz` under the existing MHCS submission contract.
- Focused JavaScript tests, FormData integration coverage, upload telemetry regression coverage, relevant Image Gateway/operator tests, frontend build checks, and reconciliation of affected real-NPZ validation assumptions.
- Strictly necessary documentation/context synchronization resulting from the implemented behavior.

### Out of scope

- MPIPS algorithm, service, or repository changes; detector image processing; or changes to `rawimage`.
- Gain NPZ normalization or content changes.
- Browser-side NumPy deserialization, NumPy object-array interpretation, pickle execution, or server-side NumPy/pickle parsing.
- Server-side workaround intended merely to reduce upload size.
- Migration, rewrite, deletion, or direct object-store manipulation of historical NPZ objects.
- Production deployment, production validation execution, fixture upload to production, upload-limit changes, secrets access, or destructive data operations.
- Unrelated UI redesign, Image Gateway refactoring, database migration, or clinical metadata semantic changes.

### Preserved behavior

- Existing authenticated Operator capture flow, CSRF, authentication, authorization, and retry/state behavior remain intact.
- `ImageGatewayController` remains the HTTP application boundary; existing server-side source validation, private-object persistence, checksum/byte-count identity, capture acceptance/idempotency, queue handoff, and `ProcessCaptureSet`/current equivalent behavior continue to apply to the normalized bytes.
- MPIPS remains the normal image-processing and NumPy parsing boundary. Normal radiograph conversion succeeds using the normalized radiograph plus matching unchanged gain NPZ.
- DICOM validation and persistence, patient-free NPZ requirements, and no raw NPZ exposure to Member/Doctor remain unchanged.
- Historical already-stored NPZ objects are not mutated.

## Dependencies and assumptions

### Dependencies

- The current browser upload path remains materially recognizable as the `resources/js/operator-upload.js` FormData/XHR path at execution preflight.
- A safe ZIP/archive-member mechanism is available in the browser or can be introduced as a small purpose-specific dependency after verifying necessity, security surface, browser suitability, licence compatibility, and repository fit.
- Existing MPIPS normal radiograph contract continues to accept the radiograph source fields without `processedimage`, as established by the supplied planning evidence.

### Approved assumptions

- NPZ is treated as a ZIP container only for locating and removing the approved member; the implementation does not need to deserialize NumPy arrays.
- The canonical target spelling is lower-case `processedimage`, normally represented by the member `processedimage.npy`; similarly named members are not silently generalized into deletion.
- If actual repository or fixture evidence materially contradicts that archive-member representation, the Executor stops rather than guessing.
- The normalized radiograph bytes, not the detector-produced pre-normalization bytes, are the canonical source artifact for future uploads.

### Remaining approval requirements

- This Draft task requires Planner/Reviewer review and publication before implementation.
- Any new browser dependency requires the task's dependency criteria and normal repository approval; a material unresolved architecture/security decision is a stop condition.
- Implementation acceptance does not authorize deployment, production validation, production mutation, or historical-object operations.

## Required capabilities

- Repository read/write and local JavaScript, integration, and build verification.
- Browser-compatible ZIP/archive inspection and reconstruction using an existing adequate repository mechanism where available.
- Codebase Memory MCP or equivalent repository intelligence when materially useful.

## Execution constraints

### Constraints

- Perform normalization only for `radiograph_npz`; pass `gain_npz` bytes through untouched.
- Operate only at the ZIP archive-member boundary. Do not execute or interpret pickle/object-array contents in browser or `mhcs-core`.
- Remove the exact `processedimage.npy` member completely when present; do not replace it with any placeholder.
- Treat malformed, corrupt, materially ambiguous, or duplicate-target archives as unsafe and fail before network transmission. Never fall back to the selected original heavy file.
- Preserve non-target member names and payload content logically; if rebuilding changes compression metadata, that is acceptable.
- Reuse existing browser/repository mechanisms first. Do not install or modify dependencies during planning. A future dependency is permitted only when its necessity, security surface, browser suitability, licence compatibility, and repository fit are verified.
- Keep the existing HTTP/server/queue/storage/MPIPS boundaries and do not add a server-side normalization workaround.
- Do not broaden implementation into gain behavior, MPIPS changes, historical migrations, production operations, or unrelated UI work.

## Acceptance criteria

- [ ] Selecting a radiograph NPZ containing `processedimage` results in the HTTP multipart upload containing a radiograph NPZ without that member.
- [ ] The transmitted `radiograph_npz` bytes are the normalized bytes, not the original heavy file.
- [ ] A radiograph already lacking `processedimage` remains uploadable without artificial failure.
- [ ] Gain NPZ bytes remain untouched.
- [ ] All non-target radiograph NPZ members remain logically equivalent.
- [ ] Malformed or ambiguous normalization fails before transmission and does not silently upload the heavy original.
- [ ] Upload progress and size telemetry reflect the actual transmitted multipart payload sufficiently to demonstrate the size benefit.
- [ ] Existing server-side NPZ validation, source persistence, checksum, capture acceptance, and processing flow operate against normalized bytes.
- [ ] Normal MPIPS conversion succeeds using the normalized radiograph plus matching gain NPZ.
- [ ] No browser or `mhcs-core` server code executes or deserializes NumPy pickle/object payloads as part of normalization.
- [ ] Existing unrelated Operator/Image Gateway behavior remains unchanged.
- [ ] Historical already-stored NPZ objects are not mutated.
- [ ] Automated tests demonstrate target removal, non-target preservation, already-normalized handling, malformed/ambiguous failure, FormData integration, and gain preservation.
- [ ] Relevant JavaScript, Image Gateway, integration, and frontend build checks continue to pass.

## Verification requirements

### Required checks

- JavaScript unit tests for ZIP-member removal, exact target spelling, preservation, already-normalized pass-through, malformed/corrupt input, and duplicate-target fail-closed behavior.
- Integration-level browser/client evidence that `FormData` receives the normalized radiograph `File`/`Blob` and unchanged gain file before XHR transmission.
- Regression coverage proving upload telemetry uses actual transmitted bytes and existing upload progress behavior remains intact.
- Relevant existing Image Gateway/operator integration tests and frontend build.
- `git diff --check` and complete changed-file inspection.
- MPIPS compatibility evidence grounded in the actual production radiograph contract, including successful normal conversion with normalized radiograph and matching gain, without changing MPIPS.
- Explicit evidence that gain bytes are unchanged and malformed normalization cannot trigger silent heavy-file fallback.
- Report the exact implementation revision, changed-file list, commands, observed results, known gaps, and any dependency decision.

### Required evidence

The Executor/Reviewer record must distinguish unit, integration, local build, and
MPIPS compatibility evidence. If a realistic fixture is added, it must be
small, synthetic, non-clinical, and must not include real Drive NPZ files or
production data. A synthetic fixture with a deliberately large
`processedimage` may demonstrate material transmitted-size reduction; no fixed
86% threshold is required.

## Stop conditions

The Executor must stop and return to planning if:

- the actual NPZ archive structure materially contradicts the verified `processedimage.npy` assumption;
- removing `processedimage` breaks required MPIPS normal conversion;
- safe normalization would require browser-side pickle execution or deserialization;
- implementation requires an unapproved major dependency or architecture/security decision;
- normalization cannot be completed before HTTP transmission;
- the current upload flow has materially changed from the planning baseline;
- preservation of non-target members cannot be demonstrated;
- a server-side-only solution is proposed for the upload-size objective;
- scope expands into gain NPZ normalization or MPIPS algorithm changes;
- a required authority, approval, dependency, or side-effect authorization is missing.

## Side-effect authorization

This Draft task authorizes no implementation or external side effect. After
publication, authorization remains bounded to the implementation and local
verification scope defined above.

The task does not authorize deployment, release, production mutation, fixture
upload to production, destructive data operations, historical NPZ rewriting,
direct object-store operations, secrets access, or MPIPS repository changes.

## Expected terminal outcome

### Review Required

Use only after the bounded implementation and truthful verification evidence
are available for Planner/Reviewer evaluation. The Executor does not self-
declare acceptance or release authorization.

### Planning Required

Use when a stop condition prevents safe completion within this task or requires
new authority, architecture, dependency, security, scope, or side-effect
approval.
