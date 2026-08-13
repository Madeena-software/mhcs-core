---
title: Local Operator-to-MPIPS DICOM Rehearsal
document_id: MHCS-TASK-LOCAL-MPIPS-REHEARSAL-001
version: 1.1
status: validated-published
language: en-US
last_updated: 2026-08-13
scope:
  - reconcile the local Operator walkthrough with the accepted MPIPS and AWS Image Gateway flow
  - prepare and evidence one safe end-to-end local rehearsal using existing dummy accounts
authority_note: This task is executable only after this exact content is committed and its immutable task revision is supplied to the Executor.
---

# Executable Task

## Task identity

**Task title:**
`Local Operator-to-MPIPS DICOM Rehearsal`

**Task path:**
`.agents/tasks/mvp-local-mpips-operator-rehearsal.md`

**Task contract state:**
`Validated/Published when this exact content is committed and its commit SHA is supplied.`

**Delivery objective / Work Package / MVP:**
`12 August MVP delivery target / reconciled local rehearsal after accepted MPIPS + AWS Image Gateway integration`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The accepted Image Gateway implementation at
`0f6f6e3552a4ace5a057e6415eac8057cd03dcee` already performs the intended
flow: an Operator uploads one radiograph NPZ and one gain NPZ; MHCS stores them
privately, queues the Image Gateway job, calls configured local MPIPS, stores
the returned DICOM privately, and lets authorised current-shift Operators view
and download it.  The existing README and local walkthrough still describe the
removed synthetic bridge and a static synthetic DICOM, so they cannot support a
truthful local rehearsal.

This task makes the existing dummy-data path runnable and accurately documented
for one local, non-clinical Operator journey.  It is preparation and evidence
for the later manual local-testing step; it is not deployment, server import,
or production release.

## Baseline and task revision

**Implementation baseline:**
`d09d33e1990cffe6a446bfa0408ed3a427134554` — unaccepted local-rehearsal
implementation to be remediated.  It does not establish an accepted baseline.

**Governing predecessor:**
`.agents/tasks/mpips-aws-image-gateway-integration.md @
31d1ce5dc0196ff15007f2468216e9c06e84485b`

**Task revision:**
`resolved when published`

## Objective

**Objective:**
Provide a safe, exact local runbook and evidence for an existing dummy Operator
to complete the normal queue-to-DICOM journey through configured S3 and local
MPIPS, then prove a second authorised Operator can view and normally download
the returned DICOM.

## Authoritative inputs

### Governing authority

- `docs/mvp/decision-log.md` — MVP-DEC-031, MVP-DEC-033, MVP-DEC-035, and
  MVP-DEC-036.
- `.agents/context/project.md` — private Image Gateway ownership, queued MPIPS
  boundary, encrypted private storage, and same-site/current-shift access.
- `.agents/context/modules/operator/project.md` — Operator queue, paper
  consent/questionnaire, current-shift/site authority, and ticket workflow.
- `.agents/context/modules/image-gateway/project.md` — durable capture and
  partial DICOM-result ownership.
- `docs/mpips/mhcs-dicom-api.md` — MPIPS v1.2 multipart conversion contract.
- `docs/mvp/evidence/mpips-aws-image-gateway-integration.md @
  0f6f6e3552a4ace5a057e6415eac8057cd03dcee` — accepted local integration
  evidence.
- CTO decisions in this conversation: configured AWS S3 is used in local and
  production; local MPIPS is the configured loopback endpoint; the specific
  local Grabber radiograph/gain NPZ pair is an approved non-clinical local
  integration input and must remain outside Git.

### Requirement traceability

- `MVP-DEC-033` → paper and image objects remain private.
- `MVP-DEC-035` → DICOM download is a normal authenticated attachment; raw NPZ
  remains unavailable.
- `MVP-DEC-036` → every authorised same-site/current-shift Operator can view
  and download a returned DICOM.
- `OPR-031..OPR-046`, `OPR-057..OPR-060`, and `IMG-060` → complete Operator
  queue/capture journey, asynchronous conversion, and read-only result access.

## Scope

### In scope

- Update `README.md` and `docs/mvp/local-core-walkthrough.md` to replace every
  synthetic-bridge/static-DICOM instruction with the accepted asynchronous
  MHCS → S3 → queue worker → local MPIPS → DICOM flow.
- Reuse `Database\\Seeders\\MvpCoreClinicSeeder` without changing its dummy
  accounts or creating a 37-member/server-import dataset.  The runbook must
  identify that it supplies one primary and two same-site/current-shift
  Operators plus synthetic clinic bookings, and that local credentials remain
  only in ignored `credential.txt`.
- Document an exact local setup checklist: a disposable MySQL database; all
  existing application and encryption keys; configured private S3 disk and AWS
  values; MPIPS base URL/API key; and database queue settings.  Refer only to
  environment-variable names, never values, endpoints beyond the documented
  loopback default, bucket names, credentials, or object identifiers.
- Document two native processes: the Laravel web server and the existing
  database queue worker consuming only the `image-gateway` queue with the
  approved worker timeout.  Do not add an Artisan command, Compose file,
  process manager, or new queue configuration.
- Document the existing first-Operator sequence: synthetic arrival/identity,
  consent, ticket print, basic examination, synthetic paper-questionnaire
  upload, X-ray call, then upload exactly one approved local Grabber
  radiograph/gain pair.  State that the pair is selected from its existing local
  location and its path, filename, bytes, and metadata are never committed or
  documented.
- Document the existing asynchronous result check: wait for the queue worker
  to produce a returned DICOM or a visible sanitised terminal failure; then
  open the vertical read-only Cornerstone viewer and perform a normal DICOM
  download.  Document the existing second authorised Operator result-worklist,
  viewer, and download check.
- Execute one fresh, disposable, non-clinical local rehearsal following the
  corrected guide.  Use the approved local Grabber pair and a synthetic dummy
  patient only; confirm queue completion, one DICOM, first/second Operator
  viewer and attachment download, then clean the disposable database and
  created private objects in `finally`.
- Create `docs/mvp/evidence/mvp-local-mpips-operator-rehearsal.md` with exact
  revisions, changed files, commands/results, both Operator observations,
  queue outcome, cleanup confirmation, known gaps, and confirmation that no
  secrets, environment values, bucket/object IDs, patient data, NPZ content,
  or DICOM bytes were disclosed.

### Out of scope

- Production or server deployment, 37-member server import, real credentials,
  real patient data, release, bucket/IAM provisioning, reverse proxy, Docker,
  Compose, CI/CD, or any server mutation.
- New NPZ parsing, fixture generation, upload endpoint, MPIPS API, polling
  mechanism, queue framework, retry policy, DICOM parser, viewer tool, raw-NPZ
  download, Member/Doctor result publication, AI routing, or clinical editing.
- Replacing the existing dummy seeders, placing the approved Grabber pair in
  Git, or changing its local path, names, bytes, or metadata.

### Preserved behavior

- The accepted Image Gateway implementation, private encryption, S3 disk
  selection, queue lease/retry behavior, active-site/current-shift access,
  Indonesian UI, vertical read-only Cornerstone viewer, and normal DICOM
  attachment response remain unchanged.
- Existing paper consent/questionnaire objects remain private; raw NPZ remains
  undiscoverable and non-downloadable.
- A failure from S3, queue, or MPIPS remains a sanitised failure state.  The
  guide must not tell an Operator to retry by resubmitting or to inspect logs,
  object storage, or secrets.

## Dependencies and assumptions

### Dependencies

- The local `.env` already contains the user-provided private AWS S3 values and
  local MPIPS credentials; their values must not be read, copied, printed, or
  committed.
- Configured loopback MPIPS remains available and accepts the approved local
  Grabber pair.
- The existing native PHP, MySQL, Node, and browser-test dependencies are
  available locally.

### Approved assumptions

- The existing five synthetic Members satisfy the seeded shift’s eligibility;
  only one is needed for the first walkthrough and no 37-member seed is needed
  locally.
- The CTO-approved Grabber pair is non-clinical and permitted only for this
  local integration rehearsal.

### Remaining approval requirements

- This task does not authorize deployment, release, production/server data,
  bucket/IAM changes, or 37-member import.  Those require separate approval.

## Required capabilities

- Repository read/write; PHP/Laravel, MySQL, npm, and browser test execution.
- Existing configured local S3 and loopback MPIPS access for one reversible,
  synthetic, non-clinical rehearsal only.

## Execution constraints

- Use existing Laravel commands, `MvpCoreClinicSeeder`, database queue, and
  Image Gateway routes.  Prefer documentation changes; do not create code when
  the existing path is sufficient.
- Warn immediately before `migrate:fresh` that it destroys the selected local
  database.  Require an explicitly disposable local target.
- The guide may mention local `credential.txt` but never its contents; it must
  instruct the reader to obtain the generated values locally without exposing
  them in docs, evidence, logs, or chat.
- The guide must name the existing `image-gateway` queue and configured 390
  second worker timeout; do not hard-code a new retry policy or direct MPIPS
  request.
- Do not render, extract, or record the private NPZ/DICOM/patient content in
  documentation or evidence.  The ordinary Operator viewer/download action is
  the only permitted browser result interaction.
- The exact manual walkthrough must not be represented as deployment or
  production evidence.  Automated tests must continue to use fakes and never
  call AWS or MPIPS.

## Acceptance criteria

- [ ] README and the local walkthrough accurately describe the existing
  asynchronous MHCS → S3 → queue → MPIPS → DICOM flow and no longer claim a
  synthetic bridge, fixture-byte validation, synchronous result, or static
  `synthetic-study.dcm` download.
- [ ] The runbook enables a user with existing local secrets and a disposable
  database to seed dummy data, start the native web and `image-gateway` queue
  worker processes, complete the primary Operator journey, and check a
  sanitised result state without adding configuration or infrastructure.
- [ ] The runbook specifies the approved local Grabber pair only as a local,
  non-clinical two-file input, with no committed path/name/content, and retains
  a synthetic dummy patient plus private questionnaire image requirement.
- [ ] One fresh local rehearsal confirms one persisted returned DICOM, the
  submitting Operator’s vertical read-only viewer/ordinary attachment download,
  and the authorised same-site/current-shift second Operator’s results
  discovery/viewer/download; all disposable database/object cleanup succeeds.
- [ ] The new evidence report is redacted as required and all relevant focused
  tests, browser rehearsal, build, formatter, and diff checks pass.

## Verification requirements

### Required checks

- Run `vendor/bin/phpunit` for `MvpCoreClinicSeederTest`, the existing core
  Operator flow tests named by the updated guide, and
  `Mvp14ImageGatewayIntegrationTest`; run
  `vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --browser chrome`.
- Run `npm run build`, `vendor/bin/pint --test`, and `git diff --check`.
- Run one fresh local MySQL migration/seed sequence and the one authorised
  reversible rehearsal.  Record only sanitised pass/fail and cleanup outcome.
- Verify the guide contains no secrets, raw local NPZ path/name, stale
  synthetic-bridge instructions, or a claim that the manual rehearsal is
  deployment/production proof.

### Required evidence

The Executor must report the exact task and implementation revisions; changed
files; all commands and observed results; dummy seed result; queue and
first/second Operator outcomes; cleanup confirmation; known gaps; and explicit
no-disclosure confirmation.  Local evidence must never be presented as
production, deployment, or release evidence.

## Stop conditions

- Stop if completing the guide would require a new local secret, bucket/IAM or
  server change, Docker/Compose, a new queue mechanism, new endpoint, or a
  change to accepted Image Gateway behavior.
- Stop if the selected database is not demonstrably disposable, or the approved
  local Grabber pair is unavailable, appears clinical, or would need to be
  copied, committed, or inspected to proceed.
- Stop if local MPIPS/S3 returns an incompatible or unsafe result, cleanup
  cannot be confirmed, or the outcome would require production/unknown target
  use.  Return only sanitised status and the affected boundary to planning.
- Stop if the walkthrough exposes a secret, patient data, bucket/object ID,
  NPZ content, or DICOM bytes, or cannot truthfully distinguish local rehearsal
  from deployment/release.

## Side-effect authorization

### Explicitly authorized side effects

- Repository changes limited to the runbook, README, local-rehearsal evidence,
  and focused tests only if documentation accuracy cannot otherwise be
  verified.
- Disposable local database creation/reset, seeding, browser/build/test work.
- One reversible local S3/private-object and loopback-MPIPS rehearsal using the
  approved non-clinical local Grabber pair, with required cleanup.

Not authorized: Git commit, push, pull request, deployment, release,
production/server mutation, bucket/IAM change, real-member import, credential
delivery, secret disclosure, or changes outside this task.

## Expected terminal outcome

`REVIEW REQUIRED` — return an immutable implementation revision and redacted
local-rehearsal evidence.  The Reviewer decides acceptance and only then plans
the separate local-testing task.

## Remediation required

### Review basis and verdict

`REMEDIATION REQUIRED` — reviewed against governing task
`.agents/tasks/mvp-local-mpips-operator-rehearsal.md @
3f9ee9de9ffd9715db947a328cacf03341b96621`, accepted implementation baseline
`0f6f6e3552a4ace5a057e6415eac8057cd03dcee`, and implementation revision
`d09d33e1990cffe6a446bfa0408ed3a427134554`.

The documentation and automated checks are reviewable, but the authorised live
rehearsal stopped before capture acceptance: persistence of the approved local
Grabber pair to configured private S3 remained in the network operation for
more than six minutes.  No capture row, private-object metadata row, queue job,
MPIPS request, DICOM, or live first/second-Operator result was produced.  The
small reversible S3 probe from the accepted predecessor is not evidence that a
capture-sized encrypted object can be stored and cleaned up.  The local
rehearsal is therefore not accepted and local deployment/testing must not start.

The execution also changed shared upload-size configuration, native-server
behavior, authentication/consent/questionnaire/Image Gateway upload behavior,
and tests.  The CTO has explicitly approved those changes in this conversation:
one `MHCS_MAX_UPLOAD_MB=100` limit governs each individual upload, and the
native request envelope permits both allowed 100 MiB NPZ files plus multipart
overhead.  This is now approved bounded behavior for local and production
configuration templates; it remains subject to the regression evidence below.

### Required corrections

- Preserve the CTO-approved shared upload configuration from
  `d09d33e1990cffe6a446bfa0408ed3a427134554`: `MHCS_MAX_UPLOAD_MB=100`
  defines a 100 MiB individual upload limit and the native-server request limit
  allows two such NPZ files plus multipart overhead.  Preserve all existing
  fail-closed validation, encrypted-private-object, and upload-purpose checks;
  do not introduce an additional limit setting, remove an existing validation,
  broaden the file types, or alter deployment infrastructure.
- Preserve only the runbook/evidence changes that truthfully describe the
  accepted asynchronous MHCS → private S3 → database queue → local MPIPS →
  private DICOM flow.  Correct the evidence to name its immutable implementation
  revision, state that the first rehearsal stopped at S3, and record only
  observed outcomes.
- Diagnose the capture-sized S3 persistence boundary without reading or
  disclosing environment values, NPZ content, patient data, bucket/object
  identifiers, or service logs containing them.  First use the existing
  `PrivateObjectStore` with a unique non-clinical generated byte string of the
  same approximate size as the approved pair, a bounded local command timeout,
  and `finally` cleanup.  Report only sanitised pass/fail, elapsed outcome, and
  cleanup status.  Do not inspect the Grabber NPZ contents or introduce an S3
  SDK/client, multipart implementation, timeout policy, proxy, IAM/bucket
  change, or new storage abstraction.
- If the bounded same-size probe and cleanup pass, run one further authorised
  fresh local rehearsal using the approved local Grabber pair, existing dummy
  seed, native web server, existing `image-gateway` database worker, and
  existing configured loopback MPIPS.  Verify capture acceptance, one queued
  job, one DICOM, first and authorised second-Operator vertical read-only
  viewer/ordinary attachment download, and full disposable database/private
  object cleanup.  If any stage hangs, fails, or cannot be cleaned up, stop and
  return its sanitised boundary/result to planning; do not retry by changing
  infrastructure or resubmitting the pair.
- Keep README and `docs/mvp/local-core-walkthrough.md` free of raw local NPZ
  paths/names, secrets, bucket/object IDs, patient data, NPZ/DICOM content,
  static synthetic-DICOM claims, and deployment/release claims.

### Additional verification

- Run the relevant existing Operator, Image Gateway, deployment, member, and
  upload-limit regression tests; run
  `tests/Browser/Mvp14OperatorDicomRehearsalTest.php --browser chrome`,
  `npm run build`, `vendor/bin/pint --test`, and `git diff --check`.
- Add no permanent test that calls S3 or MPIPS.  The bounded probes may be
  temporary local harnesses and must be removed before the final revision.
- Update `docs/mvp/evidence/mvp-local-mpips-operator-rehearsal.md` with exact
  task/implementation revisions, commands/results, S3-size-probe and rehearsal
  results, cleanup status, known gaps, and the no-disclosure confirmation.

### Remediation acceptance criteria

- [ ] The CTO-approved shared upload configuration remains one source of truth
  for a 100 MiB individual file and the two-file request envelope; existing
  private-upload validation, purpose checks, and type restrictions remain
  fail-closed.
- [ ] A bounded generated capture-sized private-object probe either proves
  write/read/delete cleanup or returns a sanitised terminal failure without
  infrastructure, secret, or clinical-data exposure.
- [ ] If and only if that probe passes, the approved local Grabber-pair
  rehearsal reaches one DICOM and both authorised Operator view/download
  journeys with confirmed cleanup; otherwise the Executor stops truthfully.
- [ ] All stated regression, browser, build, formatter, and diff checks pass
  with observed evidence.
