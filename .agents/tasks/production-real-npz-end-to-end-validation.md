---
title: MHCS Core Real NPZ Production End-to-End Validation
document_id: MHCS-TASK-REAL-NPZ-END-TO-END-VALIDATION-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-26
scope:
  - one production end-to-end Image Gateway validation using two pinned NPZ fixtures
  - fixture integrity, source acceptance, processing handoff, MPIPS, and terminal-state evidence
authority_note: This task authorizes repository planning and later bounded implementation only. A fresh explicit one-time user authorization is required before fixture download into a validation environment or production submission. It does not reopen the solved async promise incident or authorize deployment, configuration changes, direct database mutation, or manual storage operations.
---

# Executable Task

## Task identity

**Task title:**
`Validate the real-size radiograph/gain NPZ pair through the production Image Gateway flow`

**Task path:**
`.agents/tasks/production-real-npz-end-to-end-validation.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content.`

**Delivery objective / Work Package / MVP:**
`Real NPZ production flow acceptance validation`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The production private-object async promise issue is solved and must remain
closed. The accepted fix is revision
`2d3de5920493001039b7d6a1c5641a835327ba83`, deployed by run `32942249362` and
directly validated by run `32948479799` with fulfilled single and concurrent
promises, successful persistence, and stabilized cleanup.

**Future-upload supersession:** The approved 2026-08-28 operator normalization
decision supersedes this task's exact-original-radiograph assumption for future
operator uploads. Future radiograph submissions may arrive as canonical NPZ
bytes with only the lower-case `processedimage.npy` member removed before HTTP
upload; the gain fixture and this task's historical evidence remain unchanged.
This task retains its original pinned-fixture purpose and evidence and is not
being executed, rewritten, or retroactively reinterpreted by that decision.

This separate task addresses the remaining question: whether the exact pinned
real-size radiograph/gain NPZ pair can traverse the intended production
Image Gateway flow from operator submission through source acceptance,
`ProcessCaptureSet`, MPIPS when required, DICOM acceptance, and an observable
terminal application state. A failure must be attributed to the observed
boundary and must not be retroactively classified as the solved promise bug.

## Baseline and task revision

**Implementation baseline:**
`bf6f15bd4cc47b799f75043c986398824c5cc0f9`

**Task revision:**
`The full SHA of the commit containing this exact task content, supplied after publication.`

The implementation baseline is the repository revision from which the future
workflow and static test are implemented. The task revision is the immutable
content identity governing that execution and must be resolved before handoff.

## Objective

**Objective:**
Using exactly the two pinned Drive fixtures below, establish whether one
isolated, authorized production Image Gateway capture can complete the real
application path: validated uploads → both private source objects → accepted
capture → queued `ProcessCaptureSet` → MPIPS conversion when required → valid
DICOM study and observable terminal processing state.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — delivery, evidence, and side-effect boundaries.
- `.agents/context/project.md` and `.agents/context/modules/image-gateway/project.md` — Image Gateway ownership, private storage, MPIPS, retention, and completion rules.
- `app/Modules/ImageGateway/Application/Services/ImageGatewayCaptureService.php` — observed upload, source-persistence, acceptance, queue, and status path.
- `app/Modules/ImageGateway/Application/Jobs/ProcessCaptureSet.php` — observed private-object read, MPIPS, response validation, DICOM persistence, retry, and terminal-state path.
- `.agents/tasks/production-private-object-async-promise-post-deploy-validation.md` — closed async promise evidence boundary.
- Production deployment run `32942249362` and async validation run `32948479799` — observed evidence that the prior promise issue is solved.
- User-selected Google Drive folder `1Zn0JC4Rvg1-07ljSwA5hckSmO0FBidIv` and the two exact file records below — immutable acceptance inputs for this task.

### Pinned fixture inputs

The implementation MUST use exactly these files and MUST verify file ID,
byte size, and SHA-256 before submission:

| Role | Drive file ID | Source filename | Bytes | SHA-256 |
|---|---|---|---:|---|
| radiograph | `1Ft3OALtx_d3ua-z0DSS34jJmywaXjLu2` | `TRX_1787726886830.npz` | `73089445` | `605540c9102867eda3a5b54f4f88566d067ba8705fcc20bf870e4a60f80262b9` |
| gain | `1kI99se2CjzCgo4qInMEGUuJ-ZJZE3iQY` | `TRX_1787726609597.npz` | `17190412` | `38918e436e5329e28b08c844e8df3766a1ab83a1fc3135c83df56370c480b2a9` |

If either exact file ID, size, or checksum does not match, stop. Do not
substitute a fixture, upload it, or automatically select another file.

### Requirement traceability

- `REAL-NPZ-E2E-001` → both exact fixtures pass integrity verification before submission.
- `REAL-NPZ-E2E-002` → both real-size sources are accepted and persisted through the normal Image Gateway submission path.
- `REAL-NPZ-E2E-003` → the accepted capture advances to the normal processing queue and MPIPS boundary when required.
- `REAL-NPZ-E2E-004` → the resulting DICOM/terminal state is observed without exposing sensitive fixture or application values.
- `REAL-NPZ-E2E-005` → the validation uses an isolated authorized context and records retention/cleanup truthfully.

## Scope

### In scope

- A new manual-only validation workflow and focused static test, using the
  repository's existing deployment and Image Gateway workflow conventions.
- Ephemeral acquisition of exactly the two pinned Drive files into the
  authorized validation environment, with file-ID provenance and local
  byte/checksum verification before any submission.
- One normal authenticated operator capture submission using a dedicated,
  isolated validation operator/site/admission context already authorized by
  the application; the implementation must use the existing controller/service
  path rather than direct database fabrication or manual object writes.
- Observation of source acceptance, capture status, queue/handoff state,
  `ProcessCaptureSet`/equivalent state, MPIPS reachability state, DICOM study
  acceptance, and bounded terminal processing state through approved application
  interfaces or sanitized operational evidence.
- Numeric acquisition/submission/source-completion/handoff/terminal durations.
- Fixed sanitized failure-family reporting and a truthful retention/cleanup
  result.

### Out of scope

- Any application bug fix, async-promise revalidation, storage root-cause
  diagnostic, deployment, rollback, restart, endpoint, MinIO, IAM, bucket
  policy, secret, schema, upload-limit, network, firewall, or MPIPS
  configuration change.
- Replacing either fixture with synthetic content, another Drive file, an
  actual patient/member/customer record, or an actual clinical examination.
- Committing NPZ binaries, storing them permanently in the repository, or
  publishing them as workflow artifacts without separate explicit review.
- Printing original folder names, raw NPZ bytes, embedded metadata, local
  paths, authentication data, raw IDs, checksums, exception messages, traces,
  request IDs, object keys, or environment contents.
- Direct SQL/DB writes or deletes, manual S3 operations, SSH file transfer,
  arbitrary browsing or mutation of existing production records, or automatic
  deletion behind application/domain APIs.
- Automatic retry, rerun, repair, redeploy, fixture substitution, or cleanup
  of a failed validation beyond the normal bounded application behavior.

### Preserved behavior and invariants

- The solved status `PRODUCTION PRIVATE OBJECT ASYNC PROMISE ISSUE — SOLVED`
  remains unchanged regardless of this task's result.
- The normal `ImageGatewayController::captureStore()` →
  `ImageGatewayCaptureService::submit()` → `advance()` path remains the only
  submission boundary. Both source writes must be initiated and settled by the
  application, and acceptance requires both source statuses to succeed.
- `ProcessCaptureSet` remains the normal worker boundary. It must read the
  stored radiograph, gain, manifest, and signature, call `MpipsClient` when
  required, validate the response, and persist a valid DICOM through existing
  application behavior.
- Production records follow the application's retention policy. No routine
  deletion is promised; if safe domain cleanup is unavailable, the uniquely
  marked validation record remains as audit evidence.
- Fixture files exist only in ephemeral authorized workspace storage and are
  removed from that workspace after the run when safe, without claiming
  production records were deleted.

## Dependencies and assumptions

### Dependencies

- An approved way to acquire the two exact Drive files without long-lived new
  credentials. Prefer an existing authenticated connector or a safely
  link-based download that proves the exact file IDs; if neither is available,
  implementation stops and returns to planning.
- A dedicated validation operator/site/admission context that is not tied to an
  actual patient/member/customer and is valid for the normal operator capture
  authorization. If the current application cannot provide this context
  without direct DB fabrication or unsafe production mutation, stop.
- Current production application revision and runtime configuration must be
  observed immediately before any eventual submission. The implementation
  must not assume the current revision remains `2d3de592...`.
- Normal queue-worker and MPIPS availability are prerequisites to interpreting
  a complete terminal flow; their absence is evidence, not permission to
  change infrastructure.

### Approved assumptions

- The fixtures are real-size acceptance inputs of approximately 73.1 MB and
  17.2 MB; small synthetic replacements do not satisfy this task.
- The observed source path accepts `.npz` ZIP uploads, checks per-file size and
  header, computes SHA-256, persists both objects and metadata, then queues
  `ProcessCaptureSet` after source acceptance.
- Image Gateway's approved storage policy retains accepted application records
  indefinitely unless an authorized compliance administrator performs a fully
  audited action; this task does not authorize that deletion.

### Remaining approval requirements

- Workflow implementation and static-test changes require normal Executor and
  Planner/Reviewer execution and acceptance.
- A fresh explicit one-time user authorization is required after implementation
  acceptance and immediately before downloading fixtures or submitting them to
  production.
- That runtime authorization may cover only the two exact fixture downloads,
  local integrity checks, one isolated normal application submission, normal
  queue/MPIPS processing caused by that submission, sanitized observation, and
  ephemeral workspace cleanup.
- No rerun is authorized. A failed or ambiguous run returns to Planner/Reviewer.

## Required capabilities

- Repository read/write and local static-test execution for the later workflow implementation.
- Access to the approved fixture-acquisition mechanism without exposing Drive credentials.
- Approved authenticated access to the normal operator submission/status boundary.
- Sanitized observation of queue-worker/MPIPS/terminal application state.
- Codebase Memory MCP or equivalent repository intelligence when materially useful.

## Execution constraints

### Constraints

- Use a manual-only workflow with least-privilege permissions and no automatic
  schedule, push, pull-request, rerun, or retry behavior.
- Verify exact Drive file ID, source filename, byte count, and SHA-256 before
  upload. Emit only `radiograph_fixture_integrity=PASS|FAIL` and
  `gain_fixture_integrity=PASS|FAIL` plus boolean match fields.
- Keep fixture downloads and any multipart submission in ephemeral authorized
  workspace storage. Do not upload fixtures as artifacts or log their contents.
- Use only a dedicated validation identity/context. Do not bypass application
  authorization, create records with direct SQL, or use an actual clinical
  account.
- Submit both files through the existing operator capture flow. Do not call
  storage, S3, MPIPS, or queue internals directly to simulate application
  progress.
- Report only fixed enums and numeric durations. Suggested failure families:
  `fixture_integrity`, `authorization`, `validation_input`, `upload`,
  `private_storage`, `source_acceptance`, `application_transition`, `queue`,
  `mpips`, `timeout`, and `unknown`.
- Do not classify performance against a threshold unless an approved product
  requirement supplies one; functional completion is primary.
- Treat every production record/object/job created by the normal flow as
  retained unless a documented domain cleanup operation is explicitly
  authorized and observed safe. Never invent cleanup by deleting rows or
  objects directly.

## Acceptance criteria

- [ ] The eventual validation uses exactly the two pinned fixtures and proves file ID, filename, byte count, and SHA-256 before submission.
- [ ] `radiograph_fixture_integrity=PASS` and `gain_fixture_integrity=PASS`, with all size/checksum match booleans true.
- [ ] `real_npz_submission_started=true` only after both fixture integrity checks pass and the isolated authorized context is confirmed.
- [ ] Both `radiograph_source_state` and `gain_source_state` become `accepted` through the normal application flow, without a source becoming missing or rejected due to persistence failure.
- [ ] `capture_sources_complete=true` and `processing_handoff_observed=true` are supported by observed application evidence.
- [ ] `ProcessCaptureSet` or the current equivalent is reported as `queued` or `processed`, and MPIPS is reported as `reached` when the normal flow requires it.
- [ ] A successful terminal result includes a valid DICOM study/terminal application state observed through approved sanitized evidence.
- [ ] Numeric durations are reported for acquisition, each submission, source completion, handoff, and terminal processing when reached; no unapproved performance threshold is asserted.
- [ ] Failure results identify the observed boundary with a fixed sanitized failure family and do not automatically attribute the result to the solved async promise incident.
- [ ] Retention/cleanup reporting is truthful: ephemeral fixture workspace cleanup is distinguished from retained application records and production objects.
- [ ] The incident remains closed as `PRODUCTION PRIVATE OBJECT ASYNC PROMISE ISSUE — SOLVED`; only a successful broader result may be reported as `REAL NPZ PRODUCTION FLOW — END-TO-END VALIDATED`.

## Verification requirements

### Required checks

- Static tests must prove manual-only trigger, least-privilege permissions,
  exact pinned fixture identifiers/size/checksum constants, pre-submit
  integrity gating, no fixture artifacts, sanitized output, no direct DB or
  storage/MPIPS/queue simulation, and no automatic retry/rerun.
- Static tests must prove the workflow distinguishes repository revision from
  running production revision and fails closed when required runtime identity,
  fixture integrity, or authorization evidence is unavailable.
- Static tests must prove the workflow records all required stable state enums,
  numeric timing fields, failure families, and retention/cleanup distinction.
- Local checks must include YAML parsing, complete embedded script syntax,
  focused workflow tests, relevant Image Gateway/Deployment tests, formatting,
  `git diff --check`, and final two-file diff inspection for the later
  implementation task.
- Eventual runtime execution must be exactly one explicitly authorized run and
  must not be represented as local or static evidence.

### Required evidence

The Executor/Reviewer record MUST include the governing task revision,
implementation revision, changed files, commands and observed results, exact
fixture constants used, sanitized runtime fields, numeric durations, failure
family if applicable, retention/cleanup outcome, known gaps, and confirmation
that no unauthorized production or Drive operation occurred.

At minimum, runtime output should use these stable fields:

```text
radiograph_fixture_download=PASS|FAIL
gain_fixture_download=PASS|FAIL
radiograph_fixture_size_match=true|false
gain_fixture_size_match=true|false
radiograph_fixture_sha256_match=true|false
gain_fixture_sha256_match=true|false
real_npz_submission_started=true|false
radiograph_source_state=accepted|failed|missing|unknown|NOT_EXECUTED
gain_source_state=accepted|failed|missing|unknown|NOT_EXECUTED
capture_sources_complete=true|false|NOT_OBSERVED
processing_handoff_observed=true|false|NOT_OBSERVED
processing_job_state=queued|processed|failed|NOT_OBSERVED
mpips_state=reached|not_reached|not_required|NOT_OBSERVED
terminal_application_state=completed|failed|processing|queued|NOT_OBSERVED
fixture_acquisition_duration_ms=<number|NOT_OBSERVED>
radiograph_submission_duration_ms=<number|NOT_OBSERVED>
gain_submission_duration_ms=<number|NOT_OBSERVED>
source_completion_duration_ms=<number|NOT_OBSERVED>
processing_handoff_duration_ms=<number|NOT_OBSERVED>
terminal_processing_duration_ms=<number|NOT_OBSERVED>
failure_family=<family|none|NOT_OBSERVED>
application_retention=<RETAINED|DOMAIN_CLEANUP_CONFIRMED|NOT_OBSERVED>
workspace_cleanup=PASS|FAIL|NOT_EXECUTED
real_npz_end_to_end_validation=PASS|FAIL|NOT_EXECUTED
```

## Stop conditions

Stop and return to planning if fixture ID/size/checksum verification fails;
Drive acquisition needs new long-lived credentials or unsafe sharing; the
dedicated validation context is unavailable; the running revision is not
observed; normal application authorization cannot be used; the flow requires
direct DB/storage/queue mutation; cleanup semantics are unclear; a scope or
permission expansion is needed; or the result is ambiguous. Do not substitute
fixtures, repair production, rerun, redeploy, or reopen the solved promise
incident automatically.

## Side-effect authorization

### Explicitly authorized side effects

- Publication of this task only.

### Not authorized by this task publication

- Workflow implementation, Drive download, production submission, production
  record/object/job creation, queue execution, MPIPS invocation, deployment,
  direct database mutation, manual S3 operation, SSH, cleanup of retained
  application records, or any external-system mutation.

After implementation review and a fresh explicit runtime authorization, only
the bounded two-fixture download, one isolated normal application submission,
resulting normal processing, sanitized observation, and ephemeral workspace
cleanup may occur.

## Expected terminal outcome

### Review Required

Use after the bounded workflow/static implementation and truthful verification
evidence are available for Planner/Reviewer evaluation. Implementation
acceptance is not runtime authorization.

### Planning Required

Use when fixture acquisition, validation identity, runtime authorization,
cleanup/retention, application state, or observed failure requires a new
authority decision or broader side effect.
