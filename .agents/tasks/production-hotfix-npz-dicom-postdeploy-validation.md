---
title: Production Combined NPZ and DICOM Hotfix Post-Deployment Validation
document_id: MHCS-TASK-PRODUCTION-NPZ-DICOM-POSTDEPLOY-VALIDATION-001
version: 1.0
status: Draft
language: en-US
last_updated: 2026-08-30
scope:
  - exact deployed combined hotfix release validation
  - non-mutating or explicitly approved post-deployment NPZ and DICOM evidence
authority_note: This Draft task is a validation contract candidate only. It does not authorize execution, production mutation, deployment, release, or publication until Planner/Reviewer approval and immutable publication.
---

# Executable Task

## Task identity

**Task title:**
`Validate exact deployed NPZ normalization and canonical DICOM presentation`

**Task path:**
`.agents/tasks/production-hotfix-npz-dicom-postdeploy-validation.md`

**Task contract state:**
`Draft — requires Planner/Reviewer review and immutable publication before execution.`

**Delivery objective / Work Package / MVP:**
`Release Gate evidence — post-deployment validation of the combined NPZ and DICOM operator hotfix`

**Owner / designated planning authority:**
`Planner/Reviewer under the approved production-validation authority`

## Delivery context

The exact combined hotfix release is already reported as deployed, but deployment
success does not prove semantic correctness at either the browser upload boundary
or the rendered DICOM presentation boundary. This task exists to obtain reviewable
evidence for both behaviors on the same exact release and to reconcile the known
verification gap: the DICOM pull request passed client tests, failed TypeScript,
and skipped build; the later exact-main deployment build/validation succeeded, but
that does not by itself establish post-deployment behavior.

The two behaviors under validation are:

1. The Operator radiograph NPZ is normalized immediately before upload: the
   exact `processedimage.npy` member is absent from transmitted bytes while
   required metadata and all other intended content remain intact.
2. The Operator DICOM viewer preserves canonical presentation: representative
   PA data remains PA and representative AP data remains AP, without an
   unintended horizontal transformation in the viewer.

This is validation only. It does not redesign or implement either hotfix.

## Baseline and task revision

**Reviewed implementation SHA:**
`2d31192a76252064237739022a53ee39c1547074`

**Expected production release SHA:**
`2d31192a76252064237739022a53ee39c1547074`

**Deployed client image:**
`ghcr.io/madeena-software/mhcs-core-client:client-2d31192a7625`

**Deployment workflow run:**
`33317998144`

**Task revision:**
`Resolved when published; this Draft is not executable.`

The task MUST validate only the pinned release. The task revision and application
release identity are separate identities.

## Objective

**Objective:**
Reconfirm that production runs the pinned release and collect sufficient
non-destructive evidence to determine whether any regression was detected in
browser-side `processedimage` removal and canonical PA/AP DICOM presentation
through the relevant Operator paths.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — evidence, task,
  approval, side-effect, and release-gate boundaries.
- `.agents/context/project.md` — canonical radiograph source, DICOM, storage,
  MPIPS, deployment, and no-SSH boundaries.
- `.agents/context/modules/operator/project.md` — Operator upload and protected
  read-only viewer boundaries.
- `.agents/context/modules/image-gateway/project.md` — canonical NPZ submission,
  private persistence, checksum, MPIPS, and DICOM access boundaries.
- `.agents/tasks/operator-radiograph-npz-normalization-before-upload.md` —
  accepted NPZ normalization contract.
- `.agents/tasks/operator-dicom-canonical-laterality-presentation.md` — accepted
  canonical DICOM presentation contract.

### Observed implementation and operational inputs

- Reviewed release SHA and deployment run recorded above.
- `.github/workflows/deploy-swarm.yml` — existing deployment identity,
  rollout, health, and readiness evidence.
- `resources/js/operator-upload.js` and
  `tests/JavaScript/operator-upload.test.mjs` — NPZ upload boundary and tests.
- `resources/js/operator-dicom-viewer.js`,
  `tests/JavaScript/operator-dicom-viewer.test.mjs`, and
  `tests/JavaScript/operator-dicom-build-check.mjs` — viewer presentation path
  and regression checks.
- Available CI evidence for the DICOM change, including the failed TypeScript
  check and skipped build, plus exact-main deployment build/validation evidence.

## Scope

### In scope

- Reconfirm the running production application/client release identity against
  SHA `2d31192a76252064237739022a53ee39c1547074` and image tag above.
- Execute relevant targeted automated tests for NPZ upload normalization and
  DICOM viewer presentation, plus the relevant broader regression tests.
- Run TypeScript/static checks and the relevant production build or equivalent;
  explicitly reconcile the earlier DICOM-PR TypeScript failure with observed
  exact-main evidence rather than declaring it irrelevant.
- Inspect deployment rollout, service readiness/health, and relevant sanitized
  logs non-destructively where the approved interfaces permit it.
- At the actual outbound upload boundary, inspect a representative Operator
  radiograph payload and prove that serialized/transmitted NPZ bytes do not
  contain `processedimage.npy`, while required metadata, non-target members,
  and matching unchanged gain bytes remain intact.
- Validate representative canonical PA and AP DICOM studies through the
  Operator viewer path, including the transformation/rendering surface, not
  merely metadata labels. Record sanitized evidence that PA remains PA and AP
  remains AP without unintended horizontal mirroring or PA/AP swapping.
- Check relevant upload and viewer regressions and report evidence separately as
  repository/local, deployment, and actual post-deployment functional evidence.

### Out of scope

- Product implementation, redesign, refactoring, or test weakening.
- UI redesign, radiograph-pipeline redesign, DICOM metadata-policy changes not
  required by the two hotfixes, backend migration, or MPIPS changes.
- Production redeployment, restart, rollback, infrastructure or permission
  changes, secret changes, or remote Git mutation.
- Production-data cleanup, historical NPZ/DICOM migration, rewrite, deletion,
  or direct database/object-store/queue manipulation.
- A real production upload that creates persistent state unless a separate,
  explicit human approval authorizes that exact action after read-only methods
  are exhausted.
- Unrelated test, build, TypeScript, dependency, or runtime remediation.

### Preserved behavior

- Existing authenticated Operator upload, CSRF, authorization, site/shift,
  capture, storage, checksum, idempotency, MPIPS, and DICOM access boundaries.
- Gain NPZ bytes, non-target NPZ members, required metadata, and historical
  stored objects remain unchanged.
- DICOM PixelData and server-side DICOM retrieval remain unchanged; viewer
  controls remain view-only and explicit horizontal flip remains explicit.
- Private clinical data, credentials, tokens, and secrets are not logged or
  persisted in task evidence.

## Dependencies and assumptions

### Dependencies

- Production is reachable through approved non-SSH observability and validation
  interfaces.
- Representative NPZ evidence can be obtained at the outbound boundary without
  unauthorized persistent production mutation, or a separately approved
  nonclinical validation context is available.
- Representative PA and AP studies are authorized for validation and can be
  inspected without committing or exposing private DICOM content.
- Relevant client tests, static checks, and build tooling are available at the
  pinned reviewed revision.

### Approved assumptions

- The deployment record above is evidence to reconfirm, not a substitute for
  checking the running release.
- The accepted hotfix contracts define the expected behavior; existing source
  code and tests are evidence of available validation surfaces, not proof of
  production correctness.
- A sanitized rendering/asymmetry comparison or equivalent pixel/presentation
  evidence is required when metadata alone cannot distinguish a transformation.

### Remaining approval requirements

- Planner/Reviewer must approve and immutably publish this task before any
  execution.
- Human approval is required immediately before any state-mutating production
  upload, fixture acquisition that creates production state, or other
  consequential external action.
- Separate release/deployment approval remains required; this task does not
  authorize redeployment or release.

## Required capabilities

- Repository read and local command execution.
- Approved CI/deployment observability and sanitized runtime/log inspection.
- Targeted test, TypeScript/static, and build execution.
- Authorized browser/viewer inspection and representative NPZ/DICOM evidence
  collection without exposing secrets or private clinical data.

## Execution constraints

### Constraints

- Fail closed if the running release, client image, or relevant service identity
  does not match the pinned values.
- Use only existing repository mechanisms and approved non-destructive
  interfaces; do not add dependencies, workflows, routes, probes, or fixtures.
- Validate the NPZ at the real serialized/upload boundary. A unit test or a
  server-side inspection of an already-normalized file alone is insufficient.
- Validate PA/AP at the viewer transformation/presentation surface using
  representative evidence. Metadata labels alone are insufficient.
- Do not deserialize NumPy object/pickle payloads in the browser or MHCS.
- Record only sanitized evidence: no credentials, tokens, cookies, private
  DICOM/NPZ contents, patient identifiers, or raw production payloads.
- Do not use SSH, direct SQL, direct object-store manipulation, direct queue or
  MPIPS invocation, or bypass normal authentication/authorization.

## Acceptance criteria

- [ ] The running production release is independently reconfirmed as the exact
  SHA and client image pinned by this task, with deployment run `33317998144`
  reconciled to that identity.
- [ ] Relevant readiness/health and sanitized runtime/log evidence is recorded,
  with failures or unavailable evidence stated explicitly.
- [ ] Targeted NPZ normalization tests and relevant broader regression tests
  execute against the reviewed revision with observed results recorded.
- [ ] TypeScript/static and relevant build evidence is recorded, and the prior
  TypeScript failure/skipped build is explicitly reconciled rather than omitted.
- [ ] Representative outbound radiograph bytes contain no
  `processedimage.npy`; required metadata and non-target members are preserved,
  and matching gain bytes remain unchanged.
- [ ] Representative PA data remains correctly presented as PA and representative
  AP data remains correctly presented as AP through the Operator viewer, with
  no unintended horizontal transformation or PA/AP presentation swap.
- [ ] Upload and viewer regression checks reveal no regression within the
  defined validation set.
- [ ] Evidence distinguishes local/repository verification, deployment evidence,
  and actual post-deployment functional evidence.
- [ ] The final review conclusion is limited to: no regression was detected
  under this defined validation set, or the exact observed regression/gap.

## Verification requirements

### Required checks

- Exact SHA/image/run identity and deployment rollout/readiness checks.
- Targeted NPZ upload tests, DICOM viewer tests, and relevant broader client/
  integration regression tests.
- TypeScript/static validation and relevant production build/equivalent evidence.
- Outbound NPZ serialization/FormData inspection at the actual upload boundary.
- Representative PA/AP viewer validation using pixel/presentation evidence or
  an equivalent transformation-sensitive comparison.
- Sanitized relevant runtime/log inspection and upload/viewer regression review.
- `git diff --check` and complete changed-file inspection if this task file is
  later modified during task preparation.

### Required evidence

The Executor MUST report:

- exact task revision, application SHA, deployed image, and deployment run;
- commands/checks actually executed and observed results;
- test names and coverage boundaries;
- TypeScript/build result and reconciliation of the known CI gap;
- NPZ outbound member, preservation, byte-count, and checksum evidence;
- sanitized PA/AP presentation evidence showing the transformation surface;
- health/readiness/log observations;
- all skipped or unavailable evidence and why;
- any detected regression, material deviation, or stop condition.

## Stop conditions

Stop and return to Planner/Reviewer if:

- production is not running the pinned SHA/image, or exact identity cannot be
  proven;
- validation requires an unauthorized production mutation, real upload, data
  creation, credential/secret access, or permission expansion;
- the TypeScript failure remains a material unresolved defect for this release;
- outbound NPZ correctness cannot be established at the actual upload boundary;
- PA/AP correctness cannot be established from authorized representative
  evidence or requires inferring presentation from labels alone;
- observed behavior or architecture differs materially from the hotfix contracts;
- validation reveals a regression, security/privacy/data-integrity risk, or
  scope requiring implementation or redeployment;
- a relevant test/build/runtime result is unavailable and acceptance cannot be
  met within the authorized non-mutating scope.

## Side-effect authorization

This Draft task authorizes no execution. If published, it would authorize only
bounded read-only repository, CI, deployment-observability, runtime, and
representative validation checks explicitly described above.

It does **not** authorize:

- product implementation or workflow changes;
- production upload or any persistent production-data creation;
- redeploy, restart, rollback, infrastructure, permission, or secret changes;
- direct database, object-store, queue, or MPIPS operations;
- commit, push, pull request, issue write, merge, or release publication.

Any definitive evidence requiring a state-mutating production upload MUST be
returned for a separate explicit approval decision.

## Expected terminal outcome

`REVIEW REQUIRED` — return the exact evidence set and a bounded conclusion to
Planner/Reviewer. Do not claim production correctness beyond the validation set.
