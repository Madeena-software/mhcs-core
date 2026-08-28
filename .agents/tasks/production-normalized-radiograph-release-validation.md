---
title: Production Normalized Radiograph Release and Validation
document_id: MHCS-TASK-PRODUCTION-NORMALIZED-RADIOGRAPH-RELEASE-001
version: 1.2
status: validated-published
language: en-US
last_updated: 2026-08-29
scope:
  - exact accepted MHCS release deployment
  - one controlled production validation of browser-side normalized radiograph upload
  - post-deployment Image Gateway and MPIPS evidence
authority_note: This published task authorizes only the bounded release preflight, manual deployment, and one controlled production validation described here, subject to explicit approvals and all stop conditions. Publication does not itself authorize execution, deployment, production validation, production mutation, or historical-object changes.
---

# Executable Task

## Task identity

**Task title:**
`Release and validate normalized radiograph production flow`

**Task path:**
`.agents/tasks/production-normalized-radiograph-release-validation.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content.`

**Delivery objective / Work Package / MVP:**
`Release Gate — deploy and validate the accepted browser-side radiograph NPZ normalization through the production Operator → Image Gateway → MPIPS flow`

**Owner / designated planning authority:**
`Planner/Reviewer under the approved release and validation authority`

## Delivery context

The application release candidate `d6df79cd2c7028c46741c0bdf8d148d6d9220561`
implements the approved operator radiograph normalization task. It removes only
the exact `processedimage.npy` ZIP member from future `radiograph_npz` uploads,
leaves `gain_npz` unchanged, and sends the resulting bytes through the existing
authenticated capture flow. Local MHCS verification and synthetic MPIPS
compatibility verification have passed. This task exists because implementation
acceptance is not production release authorization and because a direct server
multipart upload of an already-normalized file would not prove the new browser
boundary.

The historical
`.agents/tasks/production-real-npz-end-to-end-validation.md` task remains
valid at its original immutable revision for its pre-normalization exact-source
fixture contract. Its fixture size and SHA semantics are not changed or
retroactively reinterpreted. Its current `curl` submission path proves the
authenticated application/Image Gateway/MPIPS path, but, unchanged, does not
prove browser-side normalization before HTTP upload.

The existing
`.agents/tasks/production-swarm-deployment.md` task governs recurring bounded
Swarm operations and its eligible operational envelope; it does not
automatically govern this feature release. This release requires its own
bounded authority because it pins a feature revision, requires proof of the
manual deployment result, and authorizes one controlled clinical-data-free
normalized-radiograph validation with source-integrity and MPIPS evidence.

Planning review identified that no currently governed production workflow can
prove the browser/client boundary. The prerequisite
`.agents/tasks/production-normalized-radiograph-browser-validation-harness.md`
therefore governs implementation of that capability separately. This release
task is published only after the harness is published, implemented, reviewed,
and accepted at the immutable revisions recorded below. Publication does not
itself authorize execution or side effects.

## Baseline and task revision

**Application release candidate / deployment revision:**
`d6df79cd2c7028c46741c0bdf8d148d6d9220561`

**Browser validation harness governing revision:**
`b99146a4ca7c7bb71a5b0e7de36dceb632c7dddc`

**Accepted browser validation harness implementation revision:**
`ccc7cbf7397ca19819fbf6cb63c8cf628f3db88c`

**Release-task governing revision:**
`The full SHA of the commit containing this exact task content, supplied after publication.`

This published task governs only the exact release candidate and harness
identities recorded here. Its publication revision is resolved externally from
the publication commit and MUST NOT be embedded in this file.

## Objective

**Objective:**
Deploy exactly the accepted MHCS revision, prove that production is running that
revision, then perform one authorized nonclinical production validation through
the real operator browser upload boundary proving that an original radiograph
containing `processedimage.npy` is normalized before transmission, stored as
normalized canonical bytes with matching checksum and byte count, processed with
unchanged gain by normal MPIPS conversion, and exposed only through the
existing authenticated DICOM result boundary.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — release separation, evidence, authorization, and side-effect boundaries.
- `.agents/context/project.md` and `.agents/context/modules/image-gateway/project.md` — browser, private storage, checksum, retention, Image Gateway, and MPIPS boundaries.
- `.agents/context/modules/operator/project.md` — authenticated Operator capture flow, nonclinical validation boundary, and source submission invariants.
- `.agents/tasks/operator-radiograph-npz-normalization-before-upload.md` at governing revision `69f5e5f8ba3d17f6ef88df232aae293c1f0fb2a6` — accepted implementation contract.
- Accepted implementation revision `d6df79cd2c7028c46741c0bdf8d148d6d9220561` — exact release candidate.
- Approved release/validation decision authorizing this bounded production sequence.

### Observed implementation and operational inputs

- `.github/workflows/deploy-swarm.yml` — existing manual-only production deployment, rollout, health, and MPIPS environment checks.
- `.github/workflows/provision-production-nonclinical-validation-context.yml` — existing separately governed nonclinical validation-context provisioning.
- `.github/workflows/validate-production-real-npz-end-to-end.yml` — historical direct multipart validation; useful for application/MPIPS evidence but insufficient for browser normalization evidence when unchanged.
- `.agents/tasks/production-swarm-deployment.md` — existing recurring deployment-operation boundary, not the release authority for this feature.
- `.agents/tasks/production-real-npz-end-to-end-validation.md` — historical exact-original fixture contract; MUST remain unchanged.
- `.agents/tasks/production-normalized-radiograph-browser-validation-harness.md` — prerequisite harness contract; it MUST be published and accepted before this task is published.
- `resources/js/operator-upload.js`, `package.json`, and accepted build output — browser normalization and existing client tooling.
- Accepted implementation of the browser-validation harness task — required client-boundary evidence mechanism; mere dependency availability is insufficient.
- Existing Image Gateway/operator production-validation context and relevant tests — source persistence, checksum, processing, and authenticated DICOM evidence surfaces.

### Fixture inputs

The controlled run MAY use the existing governed fixture-acquisition mechanism
and these exact nonclinical-validation inputs, without changing the historical
task's declarations:

- Radiograph: `TRX_1787726886830.npz`, historical original size `73089445`, SHA256
  `605540c9102867eda3a5b54f4f88566d067ba8705fcc20bf870e4a60f80262b9`.
- Gain: `TRX_1787726609597.npz`, size `17190412`, SHA256
  `38918e436e5329e28b08c844e8df3766a1ab83a1fc3135c83df56370c480b2a9`.

The normalized radiograph size and SHA256 MUST be computed during the run from
the exact released client implementation. They MUST NOT be guessed or
hardcoded from the historical original fixture. No fixed percentage reduction
is required; the transmitted radiograph MUST be smaller than the original.

## Scope

### In scope

- Release preflight for the exact accepted MHCS revision.
- Manual dispatch of `.github/workflows/deploy-swarm.yml` for exactly
  `d6df79cd2c7028c46741c0bdf8d148d6d9220561` after the task is published and
  release approval is present.
- Sanitized recording of the deployment workflow run ID, selected ref/SHA,
  image/application revision, rollout result, health result, and MPIPS
  environment/health result.
- Post-deployment proof from the deployed service/container and application
  health boundary that the exact authorized revision is running.
- One controlled production validation using one authorized radiograph/gain
  pair and the dedicated nonclinical validation identity/context.
- Actual execution of the separately governed, accepted browser-validation
  harness against the deployed Operator page and exact application revision.
- Client-side capture of the original radiograph member set and bytes, the
  normalized transmitted radiograph bytes, the exact target-member removal,
  material size reduction, non-target preservation, and unchanged gain bytes.
- Normal authenticated submission through the existing Operator capture flow,
  Image Gateway private persistence and checksum identity, queue handoff,
  normal MPIPS conversion, and authenticated DICOM result retrieval.
- Sanitized read-only observations needed to bind transmitted source bytes to
  stored radiograph/gain byte counts and SHA256 values.
- Ephemeral local fixture and normalized-file cleanup after the run.

### Out of scope

- Any production execution before the explicit approvals and execution gates
  in this published task are satisfied.
- Deployment or validation of any MHCS revision other than the exact accepted
  revision named above.
- Direct SSH deployment, manual server mutation, direct object-store access,
  direct queue/MPIPS invocation, direct SQL mutation, or bypassing Operator
  authentication and authorization.
- Modification of MPIPS, MPIPS deployment, MPIPS configuration, or MPIPS data.
- Modification of `.agents/tasks/production-real-npz-end-to-end-validation.md`
  or reinterpretation of its historical original-fixture evidence.
- Historical NPZ/DICOM object migration, rewrite, deletion, or cleanup.
- Database schema changes, application behavior changes, new public routes,
  new secrets, new infrastructure, new IAM/network permissions, or unrelated
  dependency remediation.
- Repeated production validation runs, automatic retries, arbitrary fixture
  submissions, or a fixed performance/reduction claim.
- A direct `curl` upload of an already-normalized fixture as a substitute for
  browser/client-boundary evidence.

### Preserved behavior

- Existing manual-only deployment workflow, Swarm topology, authentication,
  authorization, CSRF, active-site/current-shift checks, and retry/state rules.
- Only `radiograph_npz` is normalized; `gain_npz` remains byte-identical.
- Exact lower-case `processedimage.npy` is removed completely. Similar names,
  non-target members, names, and payloads remain unchanged logically.
- The normalized radiograph is the canonical stored, checksummed, persisted,
  idempotent, and MPIPS-submitted source for this future capture.
- Private object storage remains private and opaque; raw NPZ is not exposed at
  a public result boundary.
- Existing normal MPIPS-to-DICOM conversion and authenticated DICOM retrieval.
- Historical already-stored objects remain unchanged.

## Dependencies and assumptions

### Dependencies

- Published immutable revision of this task and explicit release/validation
  approval immediately before side effects.
- Clean MHCS checkout whose accepted revision is exactly the authorized SHA.
- GitHub Actions self-hosted runner, deployment environment, required secrets,
  Swarm manager, application health boundary, and MPIPS private connectivity.
- Existing nonclinical validation context is already provisioned and usable;
  provisioning is separately governed and is not silently performed by this
  task.
- Exact fixture acquisition mechanism and integrity declarations remain
  available without exposing credentials or fixture contents.
- The browser-validation harness task has been published, implemented, reviewed,
  and accepted at the immutable revisions recorded in this task. Its accepted
  implementation can load the production Operator
  page, observe the actual multipart request, and retain only sanitized
  evidence. Playwright/Pest availability alone does not satisfy this dependency.

### Approved assumptions

- `workflow_dispatch` of `deploy-swarm.yml` is the only approved deployment
  path; a normal push to `main` does not itself prove or authorize production
  deployment because the workflow is currently manual-only.
- The deployment workflow's `GITHUB_SHA`-derived image/version and its
  post-deploy service/container/health checks can identify the exact released
  application revision. If they cannot, the run fails closed.
- The existing historical fixture pair is suitable for one nonclinical
  validation, but its normalized radiograph identity is computed at runtime.
- The resulting validation capture/study may remain as explicitly marked
  nonclinical validation evidence under existing retention policy.
- MPIPS accepts the normalized production radiograph contract without a
  `processedimage` member, as established by the prior local synthetic test;
  production evidence must still observe the real normal conversion.

### Remaining approval requirements

- Human/designated release approval for the exact accepted SHA before manual
  deployment dispatch.
- Human/designated one-time authorization immediately before fixture download,
  browser submission, normal queue processing, and sanitized production
  observation.
- Planner/Reviewer confirmation that the exact browser-validation harness
  publication and accepted implementation revisions recorded above remain
  unchanged before release execution.
- Separate approval remains required for any production data deletion,
  historical-object operation, infrastructure/IAM change, new dependency, or
  MPIPS change; this task does not grant it.

## Required capabilities

- Repository and Git inspection without secret disclosure.
- GitHub Actions manual dispatch and run observation with sanitized output.
- Approved authenticated browser/headless-browser execution from the existing
  production-validation mechanism.
- Existing fixture acquisition and SHA256/byte-count verification.
- Read-only sanitized observation of deployed revision, application source
  state, Image Gateway capture/object metadata, processing state, MPIPS state,
  and authenticated DICOM result metadata.
- Local archive-member inspection that does not deserialize NumPy arrays,
  execute pickle, or interpret object payloads.

## Execution constraints

### Phase A — release preflight

Before dispatching deployment, the Executor MUST verify and report, without
printing secrets:

- the application release candidate and selected deployment ref are exactly
  `d6df79cd2c7028c46741c0bdf8d148d6d9220561`;
- the deployment workflow is dispatched for/selects exactly that application
  release candidate, without requiring the current control-plane checkout
  `HEAD` to equal it;
- the accepted browser-validation harness available to the validation control
  plane is exactly `ccc7cbf7397ca19819fbf6cb63c8cf628f3db88c` and is governed by
  task revision `b99146a4ca7c7bb71a5b0e7de36dceb632c7dddc`;
- no later application revision is silently substituted;
- MHCS working tree is clean and no unrelated revision is substituted;
- `.github/workflows/deploy-swarm.yml` remains `workflow_dispatch` only and
  uses its existing production concurrency group;
- required runner, environment, deployment secret presence, and deployment
  prerequisites exist, with values never printed or persisted;
- MPIPS private connectivity/environment and health are available before the
  release;
- the existing nonclinical validation context is available, authorized, and
  not expired; and
- the last known good deployed MHCS revision is recorded for rollback reporting.

If any preflight item cannot be proved, stop before deployment.

### Phase B — controlled deployment

After publication and release approval, dispatch the existing deployment
workflow for the exact accepted SHA. The Executor MUST NOT deploy through SSH,
manual Docker commands, direct server mutation, or a normal-push assumption.

The deployment run MUST fail closed unless its checkout/ref, generated
`GITHUB_SHA`/application version, deployed image or `VERSION-CURRENT`, running
application container, rollout state, and health state all identify the exact
authorized SHA. Record only sanitized run identity, revision-match booleans,
rollout/health enums, and failure family.

After deployment, verify the public application health boundary and the
deployed service/container revision independently enough to prove production is
actually serving the authorized revision. Do not begin NPZ validation if the
revision or health result is ambiguous.

### Phase C — normalized-radiograph validation

Use one bounded authorized validation pair through the accepted
browser-validation harness. Before submission:

1. Acquire the exact governed radiograph and gain fixtures into ephemeral
   workspace storage and verify filename, byte count, and SHA256. Emit only
   fixed pass/fail fields and numeric sizes/checksums where the approved
   evidence format permits them; never log NPZ contents or credentials.
2. Confirm by ZIP-container inspection that the selected original radiograph
   contains exactly `processedimage.npy`, and record its original byte count.
3. Execute the deployed browser/client path. Capture the actual
   `radiograph_npz` multipart part before transmission or at the client request
   boundary, and compute the normalized byte count and SHA256 from those bytes.
4. Prove the transmitted radiograph is smaller than the original, lacks
   `processedimage.npy`, preserves all required/non-target member names and
   payloads logically, and leaves the gain bytes byte-identical to the verified
   input. The client evidence MUST show the actual `FormData`/multipart part,
   not merely a separately normalized file.
5. Submit exactly once through the authenticated Operator capture page using
   the existing CSRF, identity, site, shift, ticket, and nonclinical context.
   Upload telemetry MUST be based on the actual transmitted multipart request.
6. Observe sanitized application evidence that both sources are accepted and
   the complete capture is handed to Image Gateway processing. Bind the stored
   radiograph object's byte count and SHA256 to the normalized transmitted
   bytes, and bind the stored gain object's byte count and SHA256 to the
   unchanged gain bytes. Do not read or expose raw object contents beyond the
   approved byte/checksum proof.
7. Observe normal Image Gateway queue/worker handoff to MPIPS and successful
   DICOM production. Retrieve the result through the existing authenticated
   DICOM boundary and verify the response is a valid DICOM result, including
   the existing content-type and `DICM` checks where applicable.
8. Verify that no historical capture/object was overwritten or deleted. Treat
   the new nonclinical validation record and resulting study as retained unless
   an existing approved retention process says otherwise.

The exact normalized SHA256 is a run output, not a task constant. The result
MUST distinguish original fixture identity, client-transmitted identity,
stored-object identity, unchanged gain identity, MPIPS processing state, and
authenticated DICOM result state.

### Client-boundary evidence decision

The accepted browser-validation harness is the required client-boundary
mechanism. It MUST execute the exact deployed application's browser module and
observe the actual File/FormData used by the real Operator submission. If the
harness is unavailable, its accepted revision cannot be proved, or it cannot
observe the actual multipart bytes without replacing application behavior, the
release execution MUST stop before fixture acquisition and return to
planning. Direct `curl` of a pre-normalized file is never an
acceptable substitute.

### Sanitized evidence

Report only approved fixed enums, booleans, numeric byte counts, durations,
revision identifiers, workflow run IDs, and checksums required to bind the
controlled validation. Do not print passwords, tokens, cookies, secret values,
patient identifiers, raw NPZ/DICOM payloads, object keys, database dumps, or
unbounded application logs.

## Acceptance criteria

- [ ] This task is published at an immutable revision before any execution.
- [ ] The browser-validation harness task is published, implemented, reviewed,
  and accepted at the exact immutable revisions recorded in this task.
- [ ] The exact authorized MHCS revision `d6df79cd2c7028c46741c0bdf8d148d6d9220561` is deployed through the existing manual workflow.
- [ ] Production independently reports the exact expected application revision and passes deployment/health verification.
- [ ] The last known good deployed revision is recorded and no rollback ambiguity remains.
- [ ] The original authorized radiograph contains the exact `processedimage.npy` member.
- [ ] The actual browser/client multipart `radiograph_npz` is smaller than the original and no longer contains `processedimage.npy`.
- [ ] Similarly named members and all required non-target names/payloads remain logically equivalent.
- [ ] The actual transmitted gain is byte-identical to the verified gain fixture.
- [ ] MHCS accepts and persists the transmitted normalized radiograph as the canonical source.
- [ ] Stored radiograph byte count and SHA256 exactly match the normalized transmitted bytes.
- [ ] Stored gain byte count and SHA256 exactly match the unchanged gain bytes.
- [ ] No original-heavy fallback occurs after normalization failure; the client-boundary evidence proves the normalized part was the submitted part.
- [ ] Image Gateway reaches normal MPIPS processing and MPIPS succeeds without MPIPS changes.
- [ ] A valid DICOM result is produced and retrieved through the existing authenticated result boundary.
- [ ] No historical NPZ/object is overwritten, rewritten, deleted, or otherwise mutated.
- [ ] Temporary downloaded/normalized files are cleaned up, while any retained validation record/study is explicitly reported as nonclinical and retained.
- [ ] All evidence is sanitized and distinguishes deployment, client-boundary, storage-integrity, and MPIPS/DICOM observations.

## Verification requirements

### Required checks before review

- Verify MHCS `git status --short`, exact `HEAD`, exact selected deployment SHA,
  and deployment workflow manual-only state.
- Verify production deployment workflow run identity, exact revision matches,
  rollout conclusion, application health, and MPIPS pre/post health evidence.
- Verify both historical fixture identifiers, filenames, sizes, and SHA256
  values before the single submission.
- Verify original archive target presence, actual client-transmitted archive
  target absence, material size reduction, and non-target member/payload
  preservation without NumPy/pickle deserialization.
- Verify actual browser/client `FormData`/multipart integration, unchanged gain,
  and actual upload telemetry bytes.
- Verify Image Gateway source acceptance, private stored-object byte/checksum
  identity, capture completion, queue/MPIPS handoff, successful normal DICOM,
  authenticated DICOM retrieval, and no historical-object mutation.
- Run relevant local static/configuration checks that remain applicable to the
  deployed accepted revision; do not represent local checks as production
  evidence.
- Record exact commands, workflow run IDs, revisions, sanitized observations,
  durations, failure families, cleanup result, known gaps, and the exact
  normalized radiograph size/SHA generated during the run.

### Required evidence

The Executor MUST distinguish:

- deployment workflow evidence from post-deployment revision/health evidence;
- historical fixture identity from normalized transmitted identity;
- client-boundary evidence from direct server/application evidence;
- stored radiograph/gain checksum evidence from manifest or metadata claims;
- Image Gateway handoff from MPIPS success and DICOM result evidence; and
- ephemeral workspace cleanup from retained nonclinical application records.

## Stop conditions

Stop before or during execution and return `PLANNING REQUIRED` if:

- the task’s immutable publication revision is unresolved, the prerequisite
  harness is not published and accepted at the exact immutable revisions
  recorded here, or required release/one-time validation authority is missing;
- MHCS is not at the exact authorized revision, the deployment workflow cannot
  prove the exact SHA, or production health/rollout is ambiguous;
- the deployment requires direct SSH/manual server mutation or unapproved
  secret, runner, infrastructure, IAM, or network changes;
- MPIPS connectivity/health is unavailable before release or normal processing
  fails after submission;
- the approved nonclinical validation context or safe fixture acquisition
  mechanism is unavailable or expired;
- the real browser/client normalization boundary cannot be demonstrated, or
  evidence would require substituting a direct `curl` upload;
- the original radiograph lacks the exact target, the normalized transmission
  still contains it, the normalized bytes are not smaller, or non-target
  preservation cannot be proven;
- gain bytes, stored checksums, or stored byte counts differ from the verified
  client inputs without an approved explanation;
- MHCS stores the original heavy radiograph, cannot bind storage identity to
  transmitted normalized bytes, or silently falls back after normalization
  failure;
- authentication, authorization, CSRF, privacy, private storage, retention,
  or audit controls would need weakening;
- a historical object would be overwritten/deleted, destructive cleanup is
  proposed, or validation scope expands beyond one controlled pair; or
- a new dependency, architecture decision, MPIPS change, or unrelated release
  change is required.

## Release rollback

- Record the last known good deployed MHCS revision before dispatch.
- If deployment rollout, health, or exact-revision verification fails, stop
  before fixture acquisition or NPZ validation.
- Do not automatically roll back or mutate production unless the existing
  deployment governance explicitly authorizes that exact rollback operation.
- Report the deployment run ID, exact observed deployed state, failure family,
  and whether any validation side effect began.

## Cleanup and data lifecycle

- Downloaded fixture files and generated normalized files MUST be stored only
  in an ephemeral authorized workspace and deleted after the run, with cleanup
  status reported.
- The validation capture/study MAY remain only as explicitly marked
  nonclinical validation evidence under existing production-validation
  retention policy.
- No direct deletion, object-store cleanup, database cleanup, overwrite, or
  historical-object mutation is authorized by this task.
- A failed run MUST report whether a validation record was created and its
  retained/cleanup state; it MUST NOT silently retry or delete production data.

## Side-effect boundaries

### This published task MAY authorize

- one manual dispatch of `.github/workflows/deploy-swarm.yml` for the exact
  accepted SHA;
- one controlled nonclinical production normalized-radiograph validation;
- read-only/sanitized production observations required to prove revision,
  storage identity, processing, and DICOM result state; and
- temporary fixture acquisition and ephemeral workspace cleanup.

### The published task MUST keep unauthorized

- execution before explicit approvals;
- execution before the browser-validation harness dependency is accepted and
  recorded here;
- direct SSH/manual production mutation;
- automatic deployment from a push assumption, repeated runs, retries, or
  arbitrary fixture uploads;
- production database/object-store deletion or historical-object mutation;
- MPIPS code/configuration/deployment changes;
- new secrets, secret disclosure, infrastructure, IAM, or network permissions;
- application/schema/route changes unrelated to this release; and
- force push, history rewrite, deployment, or release actions outside the
  existing approved workflow.

## Expected terminal outcome

### Review Required

Use only after the exact revision has been deployed and the one bounded
validation has produced truthful sanitized evidence for Planner/Reviewer.
Executor evidence MUST NOT self-declare release acceptance.

### Planning Required

Use when a stop condition, missing client-boundary proof, unavailable
validation context, unresolved release authority, deployment mismatch, storage
integrity mismatch, or MPIPS/DICOM failure prevents safe completion.

Planning publication itself does not authorize execution automatically.
