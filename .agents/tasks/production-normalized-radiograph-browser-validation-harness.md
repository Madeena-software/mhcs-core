---
title: Production Normalized Radiograph Browser Validation Harness
document_id: MHCS-TASK-PRODUCTION-NORMALIZED-RADIOGRAPH-BROWSER-HARNESS-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-28
scope:
  - manual-only browser validation harness implementation
  - static and local verification of the harness contract
authority_note: This published task authorizes only the bounded repository implementation and local verification of the browser-validation harness defined here. It does not authorize production deployment, production workflow dispatch, fixture download, NPZ submission, production data mutation, or MPIPS changes.
---

# Executable Task

## Task identity

**Task title:**
`Implement normalized radiograph production browser-validation harness`

**Task path:**
`.agents/tasks/production-normalized-radiograph-browser-validation-harness.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content.`

**Delivery objective / Work Package / MVP:**
`Release prerequisite — prove the deployed Operator browser upload contains the normalized radiograph File`

**Owner / designated planning authority:**
`Planner/Reviewer under the approved release-validation planning authority`

## Delivery context

The accepted MHCS application revision
`d6df79cd2c7028c46741c0bdf8d148d6d9220561` normalizes only the browser
`radiograph_npz` upload. The existing production real-NPZ workflow submits
files directly with `curl`, so it cannot prove that application JavaScript
removed `processedimage.npy` before HTTP transmission. This prerequisite task
defines a small manual-only harness that can later observe the actual browser
File used by the deployed Operator page.

This task is deliberately separate from
`.agents/tasks/production-normalized-radiograph-release-validation.md`:
harness implementation and review establish a reusable capability; the release
task separately governs deployment and one authorized production validation.

## Baseline and task revision

**Implementation baseline:**
`ee95351a7d0ecc9951c7ee95ba617a1f037660ea`

**Application revision the future harness may validate:**
`d6df79cd2c7028c46741c0bdf8d148d6d9220561`

**Task revision:**
`The full SHA of the commit containing this exact task content, supplied after publication.`

The harness/control-plane revision, deployed application revision, and this
task's governing revision are distinct identities. The future harness MUST NOT
require its own `GITHUB_SHA` to equal the deployed application SHA.

## Objective

**Objective:**
Implement and locally verify a reviewable, manual-only Playwright-based
production-validation harness that loads the real deployed Operator page,
proves the supplied deployed application revision before upload, observes the
actual `radiograph_npz` File placed into the browser request by application
JavaScript, and emits only sanitized evidence suitable for the later release
validation task.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — task readiness, bounded execution, evidence, and side-effect boundaries.
- `.agents/context/project.md`, `.agents/context/modules/operator/project.md`, and `.agents/context/modules/image-gateway/project.md` — browser, Operator, private storage, checksum, MPIPS, and retention boundaries.
- `.agents/tasks/operator-radiograph-npz-normalization-before-upload.md` at governing revision `69f5e5f8ba3d17f6ef88df232aae293c1f0fb2a6` — application normalization contract.
- `.agents/tasks/production-normalized-radiograph-release-validation.md` — dependent release task; it remains Draft and cannot publish until this prerequisite is accepted.
- Accepted application revision `d6df79cd2c7028c46741c0bdf8d148d6d9220561` — application behavior the harness must observe, not reproduce.

### Observed implementation inputs

- `package.json` — existing `playwright` dependency.
- `composer.json` — existing `pestphp/pest-plugin-browser` capability.
- `tests/JavaScript/https-login-production.test.mjs` — existing direct Node Playwright production-page pattern.
- `.github/workflows/security-validation.yml` — existing manual validation and repository check conventions.
- `.github/workflows/validate-production-real-npz-end-to-end.yml` and `tests/Deployment/ProductionRealNpzEndToEndValidationWorkflowTest.php` — existing sanitized revision, fixture, authenticated-flow, and terminal-evidence patterns; their historical direct-upload semantics remain unchanged.

### Fixture inputs

The later release validation MAY reuse the historical task's exact fixture
identities, filenames, sizes, and SHA256 values. This harness implementation
MUST NOT download, commit, upload, or persist those NPZ files. It MUST use small
synthetic/local test data or byte fixtures only for local harness tests.

## Scope

### In scope

- A dedicated manual-only workflow, preferably
  `.github/workflows/validate-production-normalized-radiograph-browser.yml`.
- A focused browser/client test or script, preferably
  `tests/JavaScript/production-normalized-radiograph-browser.test.mjs`.
- A focused static workflow test, preferably
  `tests/Deployment/ProductionNormalizedRadiographBrowserValidationWorkflowTest.php`.
- Existing Node Playwright usage; no new browser framework or ZIP library.
- Workflow inputs equivalent to `expected_application_revision`,
  `authorization_marker`, and, only if technically necessary, an explicit
  mode that distinguishes a non-mutating browser preflight from the one
  authorized validation.
- `workflow_dispatch` only, with an appropriate production-validation
  concurrency group preventing overlapping normalized-radiograph runs.
- Exact deployed application-revision verification before fixture acquisition
  or upload. The harness/control-plane `GITHUB_SHA` MUST remain separately
  reported and MUST NOT substitute for the expected deployed revision.
- Actual browser navigation through the deployed Operator login, session,
  CSRF, active-site, shift/admission, capture form, and built JavaScript path.
- Test-only observation instrumentation installed before the application upload
  script executes, without replacing the application's normalization or
  transmitted File.
- Sanitized observation of the actual `FormData`/XHR boundary: submitted
  radiograph bytes, gain bytes, member presence, member preservation, byte
  counts, SHA256 values, upload telemetry, and fail-closed behavior.
- Local tests proving the harness contract without production access or fixture
  download.

### Out of scope

- Production deployment, deployment workflow dispatch, production health
  checks, production fixture acquisition, NPZ upload, queue processing, MPIPS
  processing, DICOM retrieval, or production data mutation.
- Any application source, application behavior, MPIPS code/configuration, or
  historical validation task change.
- Puppeteer, Selenium, Cypress, another browser framework, another ZIP library,
  or a new dependency when existing Playwright/Pest capability is sufficient.
- Reimplementing or independently invoking the NPZ normalizer as proof. The
  harness must observe the File created and submitted by the deployed
  application JavaScript.
- Direct `curl` upload, direct storage/object access, direct queue/MPIPS call,
  direct SQL mutation, SSH, or manual production server mutation.
- Committing NPZ binaries, real fixture contents, cookies, tokens, passwords,
  DICOM payloads, object keys, or unbounded logs.

### Preserved behavior

- The deployed application revision remains a runtime input; the harness does
  not deploy or change it.
- Existing login, authentication, authorization, CSRF, active-site/current-
  shift, capture, upload, retry, and result boundaries remain in use.
- Only `radiograph_npz` is inspected for target removal; `gain_npz` is observed
  unchanged.
- Browser code remains ZIP-container-only: no NumPy array deserialization,
  pickle execution, object-array interpretation, or image processing.
- Existing historical real-NPZ workflow and its exact-original fixture contract
  remain unchanged.
- Harness output contains only approved sanitized evidence.

## Dependencies and assumptions

### Dependencies

- Published immutable harness task revision before implementation execution.
- Clean MHCS checkout at the task baseline, with existing Playwright and Pest
  browser dependencies available; no dependency installation or replacement is
  expected.
- An approved future production runner with browser support, application URL,
  required secret presence, and a separately provisioned nonclinical context.
- The later release task's exact application revision and accepted release
  authority; this harness task does not grant either.

### Approved assumptions

- `tests/JavaScript/https-login-production.test.mjs` is an adequate starting
  pattern for direct Node Playwright navigation and HTTPS/session checks.
- Browser-side observation can read the actual `FormData` File's bytes and
  compute approved evidence without changing the request body or interpreting
  NumPy payloads.
- Existing sanitized read-only application/container observation can later bind
  client-transmitted bytes to MHCS stored-object checksums and byte counts.

### Remaining approval requirements

- Implementation may begin only under this exact immutable published task revision.
- Normal implementation review and acceptance of the harness at an immutable
  revision before the dependent release task may be published.
- Separate release and one-time production-validation approvals remain
  mandatory; no production side effect is authorized here.

## Required capabilities

- Repository read/write limited to the dedicated harness surfaces and focused
  tests.
- Node and existing Playwright execution for local/browser-contract tests.
- PHP test execution for static workflow assertions.
- Git and diff inspection.
- No production credentials, fixture access, MPIPS access, deployment access,
  or external-system mutation.

## Execution constraints

### Workflow contract

The future workflow implementation MUST:

- contain `workflow_dispatch` and no `push`, `pull_request`, `schedule`,
  `workflow_run`, or deployment trigger;
- use least-privilege `contents: read` permissions and an explicit concurrency
  boundary that prevents overlapping normalized-radiograph validation runs;
- accept and validate an exact `expected_application_revision` and a full
  authorization marker before any fixture acquisition or upload;
- report harness/control-plane SHA separately from the expected deployed
  application revision;
- prove the deployed service/container/application revision and health before
  any upload begins, and fail closed on mismatch or ambiguity;
- perform at most one authorized upload and never automatically rerun,
  resubmit, substitute fixtures, or fall back to the original heavy File; and
- clean any temporary local files with a trap while retaining only sanitized
  evidence.

### Browser contract

The future browser script MUST:

- load the actual deployed Operator page and use its real login/session/CSRF,
  site, shift/admission, capture form, and JavaScript bundle;
- install observation instrumentation before application upload execution,
  while leaving the application's `FormData`, normalized File, and XHR send
  behavior intact;
- inspect the actual `radiograph_npz` File submitted by the application, not a
  separately normalized copy;
- compute only byte count, SHA256, exact ZIP member names, target absence,
  non-target logical preservation, material size reduction, and gain identity;
- observe actual upload progress/telemetry values based on transmitted bytes;
- prove that normalization failure results in no upload request and no
  original-heavy fallback; and
- avoid NumPy/pickle deserialization, object-array interpretation, image
  processing, payload logging, or private-data exposure.

### Production evidence contract

The future harness MAY use existing sanitized/read-only patterns to bind the
client observation to MHCS evidence after a later release task authorizes the
run. It MUST be able to report separately:

- harness/control-plane revision and deployed application revision;
- original radiograph size/SHA/member evidence;
- actual transmitted normalized radiograph size/SHA/member evidence;
- unchanged gain size/SHA evidence;
- source acceptance and stored object size/SHA evidence;
- queue handoff, MPIPS processing, DICOM result, and authenticated result
  boundary evidence; and
- failure family, bounded durations, and cleanup state.

This implementation task does not authorize or perform any of those production
observations.

## Acceptance criteria

- [ ] The dedicated workflow is manual-only and cannot run on push, pull request, schedule, deployment, or workflow completion.
- [ ] The workflow uses least privilege and prevents overlapping normalized-radiograph validation runs.
- [ ] Authorization inputs are explicit, and full validation requires the exact marker.
- [ ] Harness/control-plane SHA is distinct from the supplied deployed application SHA.
- [ ] Deployed application revision and health are verified before fixture acquisition or upload; mismatch fails closed.
- [ ] Existing Playwright is reused; no new browser framework, ZIP library, or unapproved dependency is added.
- [ ] The real deployed Operator login and capture path is exercised.
- [ ] Instrumentation observes the actual application-created `radiograph_npz` File/FormData boundary without changing the bytes or normalization behavior.
- [ ] Actual submitted radiograph evidence proves exact `processedimage.npy` removal, smaller transmitted bytes, and non-target preservation.
- [ ] Actual submitted gain evidence proves byte identity with the verified input.
- [ ] Normalization failure proves no upload request and no original-heavy fallback.
- [ ] The harness permits at most one authorized upload and cleans ephemeral files.
- [ ] Output is limited to sanitized revisions, run IDs, booleans, enums, byte counts, approved SHA256 values, bounded durations, and failure families.
- [ ] The implementation changes no application or MPIPS behavior and does not alter historical source objects or the historical validation task.

## Verification requirements

### Required checks before review

- Focused JavaScript tests for actual FormData/File observation, exact target
  removal, non-target preservation, unchanged gain, size/SHA evidence, and
  fail-closed no-request behavior.
- Static PHP workflow tests for manual trigger, concurrency, authorization,
  revision separation/guard, no automatic triggers, one-upload bound, cleanup,
  least privilege, sanitized output, and absence of direct mutation paths.
- Existing frontend/build checks required by the changed surfaces.
- `git diff --check`, complete changed-file inspection, and confirmation no
  NPZ binaries or unrelated dependencies are present.
- Local-only execution of the harness test path; no production URL, secret,
  fixture download, workflow dispatch, or external mutation.

### Required evidence

The Executor MUST report the exact governing task revision, implementation
baseline, implementation revision, changed files, commands and observed
results, dependency decision, local-only limitations, and confirmation that no
production operation or MPIPS/application modification occurred.

## Stop conditions

Stop and return `PLANNING REQUIRED` if:

- the task is Draft or its immutable governing revision is unresolved;
- implementation requires a new browser/ZIP dependency, architecture choice,
  secret, infrastructure, or production permission;
- the actual application-created File/FormData boundary cannot be observed
  without replacing behavior or reimplementing normalization;
- exact deployed-revision proof, authorization gating, or no-upload-on-failure
  behavior cannot be implemented safely;
- browser instrumentation would deserialize NumPy/pickle/object payloads or
  expose sensitive content;
- implementation would modify application behavior, MPIPS, historical task
  semantics, production data, or production infrastructure; or
- scope expands into release deployment or production validation.

## Side-effect authorization

### Explicitly authorized after publication

- Modify only the dedicated browser-validation workflow, browser script/test,
  and focused static workflow test required by this harness objective.
- Run local Node/PHP/frontend checks and use existing dependencies.
- Create one implementation commit and push it normally to `main` after local
  verification.

### Explicitly unauthorized

- Any deployment, production workflow dispatch, fixture download, NPZ upload,
  production data mutation, queue/MPIPS/DICOM operation, SSH, secret access or
  disclosure, infrastructure change, MPIPS change, application behavior
  change, force push, or history rewrite.

## Expected terminal outcome

### Review Required — Browser Validation Harness Pushed

Use after the bounded harness implementation is committed and pushed with
truthful local verification. Stop for Planner/Reviewer inspection and
acceptance. Do not continue to the dependent release task or production.

### Planning Required

Use when any stop condition or unresolved dependency prevents a safe bounded
implementation.
