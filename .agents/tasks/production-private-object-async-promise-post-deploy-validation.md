---
title: MHCS Core Private-Object Async Promise Post-Deploy Validation
document_id: MHCS-TASK-PRIVATE-OBJECT-ASYNC-PROMISE-VALIDATION-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-26
scope:
  - post-deploy production validation of the private-object async promise fix
  - bounded synthetic S3-backed PrivateObjectStore probes
authority_note: This task authorizes publication and later bounded validation planning only. A fresh explicit authorization is required before one production workflow dispatch. It does not authorize implementation, deployment, release, or infrastructure change.
---

# Executable Task

## Task identity

**Task title:**
`Validate the deployed PrivateObjectStore asynchronous promise contract in production`

**Task path:**
`.agents/tasks/production-private-object-async-promise-post-deploy-validation.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content.`

**Delivery objective / Work Package / MVP:**
`Production private-object async promise contract validation`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The fix for the production caller-visible async rejection was implemented,
accepted, and deployed at revision
`2d3de5920493001039b7d6a1c5641a835327ba83` by deployment run `32942249362`.
Normal deployment health passed, but the original production storage behavior
has not been directly re-observed after the fix. This task defines one separate,
narrow post-deploy validation to close that evidence gap without repurposing the
historical pre-fix diagnostic.

## Baseline and task revision

**Implementation baseline:**
`2d3de5920493001039b7d6a1c5641a835327ba83`

**Task revision:**
`The full SHA of the commit containing this exact task content, supplied after publication.`

The task revision and deployed implementation revision are distinct. The exact
immutable task revision must be resolved before any Executor implementation or
workflow dispatch.

## Objective

**Objective:**
Prove, on the deployed revision
`2d3de5920493001039b7d6a1c5641a835327ba83`, that successful S3-backed
`PrivateObjectStore::putStreamAsync()` calls fulfill with `PrivateObject`
values, including a concurrently initiated radiograph/gain pair, and that
object and metadata persistence plus exact-key cleanup succeed without an
application-level `TypeError` or caller-visible rejection.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — delivery, evidence, and side-effect boundaries.
- `.agents/context/project.md` — MHCS architecture and private opaque object-storage boundary.
- `.agents/tasks/production-private-storage-root-cause-investigation.md` — prior investigation and diagnostic evidence.
- `.agents/tasks/production-private-object-async-promise-contract-fix.md` — accepted corrective objective and preserved storage invariants.
- Production deployment run `32942249362` and deployed revision `2d3de5920493001039b7d6a1c5641a835327ba83` — observed deployment evidence.

### Requirement traceability

- `PRIVATE-OBJECT-ASYNC-VALIDATION-001` → the deployed async promise fulfills with a `PrivateObject`.
- `PRIVATE-OBJECT-ASYNC-VALIDATION-002` → independently initiated radiograph and gain promises both fulfill before caller settlement completes.
- `PRIVATE-OBJECT-ASYNC-VALIDATION-003` → primary object and `.meta.json` HeadObject checks and exact-key cleanup pass.
- `PRIVATE-OBJECT-ASYNC-VALIDATION-004` → validation is fail-closed to the exact deployed revision and emits sanitized evidence only.

## Scope

### In scope

- A new manual-only workflow, conceptually `.github/workflows/validate-production-private-object-async-promise-fix.yml`.
- A focused static test, conceptually `tests/Deployment/ProductionPrivateObjectAsyncPromiseValidationWorkflowTest.php`.
- P0 exact production revision guard using sanitized booleans:
  `app_container_resolved`, `version_current_match`, `service_revision_match`,
  `container_revision_match`, and `revision_match`.
- P1 one small deterministic, nonclinical synthetic stream through the actual
  `PrivateObjectStore::putStreamAsync()` path.
- P2 one concurrently initiated radiograph/gain pair, settled with
  `Utils::settle([$radiographPromise, $gainPromise])->wait()`.
- PrivateObject type/value inspection, HeadObject metadata and object checks,
  expected byte-size checks, fixed sanitized error families, and exact-key
  cleanup with post-delete HeadObject absence verification.
- Static safeguards for trigger, revision parsing, promise-state inspection,
  cleanup/failure semantics, sensitive-output safety, and prohibited operations.

### Out of scope

- Repeating the `89,660,664`-byte radiograph or `17,713,052`-byte gain probe,
  patient/member data, clinical records, Google Drive data, or actual NPZ files.
- `GetObject`, `ListObjects`, `ListObjectsV2`, prefix or bucket-wide cleanup.
- Database or clinical workflow access, captures, admissions, members, users,
  operator HTTP capture flow, MPIPS processing, or `ProcessCaptureSet` queuing.
- Deployment, rollback, restart, endpoint, MinIO, IAM, bucket-policy, secret,
  database-schema, Docker/Swarm, network/firewall, or upload-limit changes.
- Automatic repair, retry, redeploy, or infrastructure-hypothesis reopening.
- Modification of the historical diagnostic workflow or its static test.
- Executing the workflow as part of implementation or verification of this task.

### Preserved behavior

- The validation is observational except for known synthetic S3 writes, metadata
  writes, deletes, and HeadObject checks for keys created by that run.
- No raw bucket, endpoint, credential, object key, UUID, request ID, exception
  message, trace, payload, checksum, task/container ID, or clinical identifier
  is emitted.
- A persistence success with a rejected promise is a validation failure, not a
  success classification.
- Production state outside the deterministic synthetic validation keys remains
  untouched.

## Dependencies and assumptions

### Dependencies

- The running production application remains at the exact implementation
  baseline above.
- The accepted diagnostic's digest-aware safe image-revision parsing semantics
  and configured storage access patterns remain available for reuse.
- A separately reviewed and accepted implementation of the new workflow and
  static test exists before any production dispatch.

### Approved assumptions

- Small deterministic synthetic streams exercise the real S3-backed promise
  contract sufficiently; large representative clinical-like payloads are not
  required for this specific regression.
- The validation uses only the existing production PrivateObjectStore boundary
  and does not need database, MPIPS, or operator-flow participation.

### Remaining approval requirements

- Workflow implementation and static-test changes require normal repository
  task execution and Planner/Reviewer acceptance under a separate execution
  step; this publication turn authorizes neither implementation nor dispatch.
- After implementation is reviewed and accepted, a fresh explicit user
  authorization is required for one production `workflow_dispatch`.
- That runtime authorization permits only bounded synthetic S3 writes,
  exact-key cleanup, and HeadObject verification through the accepted workflow.
- No automatic rerun is authorized. A failed validation returns to
  Planner/Reviewer; it does not authorize repair or redeployment.

## Required capabilities

- Repository read/write and local static-test execution for the later workflow implementation.
- GitHub Actions workflow review and one explicitly authorized workflow dispatch.
- Existing production application/storage access only through the accepted validation workflow.
- Codebase Memory MCP or equivalent repository intelligence when materially useful.

## Execution constraints

### Constraints

- Use `workflow_dispatch` only, with least-privilege permissions and fail-closed
  behavior before any production probe.
- Require exact revision equality with
  `2d3de5920493001039b7d6a1c5641a835327ba83`.
- Reuse the accepted digest-aware image revision parser. Empty or invalid image
  references must produce sanitized false/mismatch output rather than aborting
  before revision evidence is emitted.
- Start both pair promises before settlement. Inspect every promise state and
  require fulfilled values to be `PrivateObject` instances.
- Use fixed error families only: `none`, `authorization`, `not_found`,
  `transport`, `unsupported`, and `unknown`; safe HTTP status may be emitted.
- Track every synthetic key locally and delete only that run's primary and
  `.meta.json` keys in always-executed cleanup. Verify absence with HeadObject.
- Cleanup failure must emit `overall_cleanup=FAIL`,
  `cleanup_incident=true`, and terminate nonzero.
- Do not add dependencies or mutate application, infrastructure, configuration,
  secrets, database, or clinical data.

## Acceptance criteria

- [ ] The new validation workflow is manual-only and fails closed unless all sanitized revision guards match the exact deployed fix revision.
- [ ] The single actual `PrivateObjectStore::putStreamAsync()` probe reports `single_async_state=fulfilled` and `single_async_value_private_object=true`.
- [ ] Single-object and metadata HeadObject checks pass and the single size check is true.
- [ ] Concurrent radiograph and gain promises are both started before `Utils::settle()` and both report `fulfilled` with `PrivateObject` values.
- [ ] Radiograph and gain object/metadata HeadObject checks pass and both size checks are true.
- [ ] No application TypeError or caller-visible rejection is observed; persistence alone cannot yield an overall pass.
- [ ] Exact-key cleanup passes for all synthetic objects and metadata, including after probe failure, and cleanup failure makes the workflow nonzero.
- [ ] Overall success is emitted only as `promise_fix_runtime_validation=PASS` when revision, fulfillment, PrivateObject values, persistence, size, and cleanup conditions all pass.
- [ ] The original production incident may be closed as `PRODUCTION PRIVATE OBJECT ASYNC PROMISE ISSUE — SOLVED` only after a post-fix run establishes every required condition above.

## Verification requirements

### Required checks

- Static tests must prove the exact revision guard, digest-aware parser, invalid/empty image-ref safety, actual async storage call, single and concurrent state/value inspection, rejection-aware pass logic, exact-key cleanup, always-run cleanup, cleanup nonzero behavior, and sanitized output.
- Static tests must prove absence of `GetObject`, `ListObjects`/`ListObjectsV2`, database access, clinical workflow, MPIPS, deployment/restart/network/configuration mutation, and sensitive output fields.
- Later runtime evidence must report these stable fields, using `NOT_EXECUTED` for probes blocked by the revision guard:

  `validation_revision`, `revision_match`, `single_async_state`,
  `single_async_value_private_object`, `single_object_head`,
  `single_metadata_head`, `single_size_match`, `single_error_family`,
  `radiograph_async_state`, `gain_async_state`,
  `radiograph_value_private_object`, `gain_value_private_object`,
  `radiograph_object_head`, `gain_object_head`,
  `radiograph_metadata_head`, `gain_metadata_head`,
  `radiograph_size_match`, `gain_size_match`, `pair_result`,
  `single_cleanup`, `pair_cleanup`, `overall_cleanup`, `cleanup_incident`,
  and `promise_fix_runtime_validation`.

### Required evidence

The Executor/Reviewer record MUST include the exact governing task revision,
implementation revision, changed files, commands and observed results, static
test evidence, any authorized runtime run and its sanitized output, known gaps,
and confirmation that no unauthorized production operation occurred.

## Stop conditions

Stop and return to Planner/Reviewer if the deployed revision does not match,
the workflow cannot fail closed, cleanup cannot be proven, a required static
safety property is missing, the probe needs broader side effects, any secret or
configuration mutation is proposed, a promise is rejected, cleanup fails, or
the result requires repair, retry, redeploy, or infrastructure changes.

## Side-effect authorization

### Explicitly authorized side effects

- Publication of this task only.

### Not authorized by this task publication

- Workflow implementation, workflow dispatch, production access, production
  S3 operations, deployment, release, restart, repair, retry, or external
  system mutation.

The later validated implementation task and fresh explicit runtime authorization
must define any permitted execution side effects.

## Expected terminal outcome

### Review Required

Use after a separately authorized implementation and observed evidence are
available for Planner/Reviewer evaluation. A passing validation supplies the
missing post-fix evidence; it does not itself authorize release.

### Planning Required

Use when the revision guard fails, validation fails, cleanup fails, evidence is
incomplete, or the required boundary expands. Do not automatically close the
incident or repair production.
