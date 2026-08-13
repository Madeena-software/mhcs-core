---
title: Plain Private Objects and Concurrent Capture Transport
document_id: MHCS-TASK-PRIVATE-OBJECT-CONCURRENT-CAPTURE-001
version: 1.1
status: validated-published
language: en-US
last_updated: 2026-08-13
scope:
  - remove MHCS application-side encryption from every private object
  - make initial NPZ S3 persistence and MPIPS submission concurrent
  - retain independent successful components and retry only a missing component
authority_note: This task is executable only after this exact content is committed and its immutable task revision is supplied to the Executor.
---

# Executable Task

## Task identity

**Task title:**
`Plain Private Objects and Concurrent Capture Transport`

**Task path:**
`.agents/tasks/private-object-concurrent-capture-transport.md`

**Task contract state:**
`Validated/Published when this exact content is committed and its commit SHA is supplied.`

**Delivery objective / Work Package / MVP:**
`Pre-deployment local MVP: economical private-object persistence and resilient Operator NPZ submission`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The prior local rehearsal attempt, governed by
`.agents/tasks/mvp-local-mpips-operator-rehearsal.md @
116d32a15138d00f6c28949bfc9597c168704338`, is **not accepted**.  Its
application-encryption and sequential private-S3 assumptions no longer match
MVP-DEC-038, and its committed follow-up contains unreviewed, out-of-scope S3
probe scripts.  This is a new material transport objective, not remediation of
that rehearsal task.

MVP-DEC-038 authorises a pre-deployment cutover only.  It removes MHCS
application-side encryption for every private object but retains private S3,
opaque keys, authenticated grants, authorization, checksums, TLS, and ordinary
authenticated DICOM download.  It also requires one initial Operator capture
request to persist radiograph and gain to S3 concurrently with the private
MPIPS submission, without reading either 100 MiB file into application memory.

## Baseline and task revision

**Implementation baseline:**
`a2ef4139eae9ac088cdde272e3946f39f6f439a2` — pending, unaccepted state.
The Executor must remove the unreviewed probe scripts and unrelated changes
listed below; this revision does not establish an accepted baseline.

**Accepted predecessor:**
`0f6f6e3552a4ace5a057e6415eac8057cd03dcee` — accepted MPIPS/AWS Image
Gateway implementation before the failed local rehearsal.

**Task revision:**
`resolved when published`

## Authoritative inputs

- `docs/mvp/decision-log.md` — MVP-DEC-033, MVP-DEC-035, MVP-DEC-036, and
  approved MVP-DEC-038.
- `.agents/context/project.md` and
  `.agents/context/modules/image-gateway/project.md` — update their outdated
  encrypted/worker-only wording as part of this task.
- `.agents/context/modules/operator/project.md` — Operator queue and
  same-site/current-shift authority.
- `docs/mpips/mhcs-dicom-api.md` — current multipart contract.
- `.agents/tasks/mpips-aws-image-gateway-integration.md @
  31d1ce5dc0196ff15007f2468216e9c06e84485b` — accepted authorization,
  DICOM validation, viewer, and normal-download behavior.

## Required outcome

### 1. Private objects without application encryption

- Replace the shared private-object implementation, not individual upload
  call sites.  Every object routed through `PrivateObjectStore`—including
  KTP/profile assets, paper consent, paper questionnaire, radiograph NPZ,
  gain NPZ, manifest, signature, and DICOM—must be stored as its original
  bytes, never AES-GCM/base64-wrapped by MHCS.
- Remove the obsolete object-encryption configuration and key requirement
  (`MHCS_OBJECT_ENCRYPTION_KEY` and only its object-storage use).  Preserve
  identifier protection, manifest signing, access-grant signing, authentication,
  authorization, opaque object keys, integrity checks, and all non-object
  security controls.
- Retain S3 private access.  Do not make a bucket/object public, introduce
  browser S3 credentials or presigned/public download URLs, weaken a grant,
  expose raw NPZ, or remove the authorised standard DICOM attachment response.
- This is a fresh pre-deployment cutover.  Do not implement mixed
  encrypted/plain compatibility or silently make existing encrypted objects
  unreadable.  The local/pre-deployment database and private object set must
  be reset before use; stop if a retained private production dataset is found
  or requested.

### 2. Concurrent initial capture and component recovery

- The Operator capture page must submit with native browser JavaScript,
  retaining the selected `File` objects in the page only until a terminal UI
  result.  All new visible Indonesian warning, progress, retry, and error copy
  must use `lang/id.json`.
- Before a request starts, warn that the page must remain open until the
  upload status is complete.  While a request is active, prevent accidental
  navigation with the native browser unload warning.  This is a warning, not
  a claim that a closed browser can preserve an unsent file.
- Use the existing server-upload temporary files/streams during the active
  request.  Do not call `UploadedFile::get()`, serialise NPZ bytes into a job,
  copy them into a durable server staging area, or retain them in application
  memory after the request ends.
- Start the radiograph S3 write, gain S3 write, and one authenticated MHCS to
  MPIPS multipart submission concurrently from separate streams of the same
  active temporary files.  Reuse the already locked AWS SDK/Guzzle packages;
  do not add an SDK, queue system, direct browser-to-MPIPS path, or a new
  network service.  Browser clients still never call MPIPS.
- Persist a capture intent before external work.  Persist each successful S3
  input object independently and exactly once.  Persist an accepted returned
  DICOM only after current MPIPS transport, signed-manifest, checksum, and
  DICOM validation all pass.  The admission advances to `awaiting_ai` only
  once both input NPZ objects and required manifest/signature are durable.
- A successful component is immutable: retrying a failed radiograph must not
  upload gain again or call MPIPS again when those components already
  succeeded; retrying gain follows the same rule.  If MPIPS failed while both
  source objects are durable, reuse the existing Image Gateway worker/retry
  path for MPIPS only.  If DICOM persistence fails during the still-active
  request, retry that persistence from the returned bytes only while the
  request remains active; after that request ends, a later recovery may invoke
  MPIPS only because no durable DICOM exists.
- If the page closes or the request aborts, retain only already successful S3
  objects and recorded capture state.  On the next authorised same-admission
  attempt, show exactly which radiograph or gain file is missing and allow the
  Operator to upload only that file.  Never pretend a browser can recover a
  file that it no longer holds.  Do not create a second capture, duplicate
  object, or duplicate DICOM for the same submission identity.
- Preserve the approved DICOM policy: every validated returned DICOM is
  visible and normally downloadable to any authenticated Operator at the same
  active site and current shift.  The vertical read-only Cornerstone viewer
  remains read-only; raw NPZ remains unavailable.

### 3. Remove invalid rehearsal artefacts and repair authority/context

- Delete `research/scratch/generate-s3-presigned.php`,
  `research/scratch/test-dicom-suite.php`, and
  `research/scratch/test-s3-integration.php`.  They are not an approved test
  harness and must not remain tracked.
- Restore `.gitignore` and `.env.example` changes introduced after the
  accepted baseline unless this task explicitly requires them.  Keep the
  accepted 100 MiB individual-upload and two-file request-envelope policy;
  do not disclose or change actual local AWS/MPIPS values.
- Replace the failed local-rehearsal evidence with a factual sanitised status;
  do not present S3 probes, the aborted rehearsal, or this implementation as
  deployment/release proof.
- Update the project and Image Gateway context to record the approved plain
  private-object policy and that the active MHCS capture request may perform
  concurrent private S3/MPIPS work; the queue remains the recovery path after
  durable source acceptance.  Update the applicable requirement-matrix
  verification/evidence wording only where this policy changes it.

## Out of scope

- Deployment, production/server data, the 37-member import, bucket/IAM
  provisioning, S3 tier/region/provider changes, public links, credentials,
  reverse proxy, Docker/Compose, CI/CD, or release.
- Persistent server-side staging, a new upload service, polling/websockets,
  NPZ parsing, DICOM editing, Member/Doctor result publication, AI routing,
  or a second viewer.
- Historical encrypted-object migration.  A request to retain an encrypted
  dataset is a planning stop condition.

## Acceptance criteria

- [ ] All private-object writes and reads use original bytes with no MHCS
  object-encryption key, AES-GCM/base64 object wrapper, or encryption metadata;
  grant authorization, opaque keys, checksums, deletion, and private S3 access
  remain enforced for every existing private-object consumer.
- [ ] The initial capture uses temporary file streams and demonstrably starts
  both NPZ S3 writes and the authenticated MPIPS multipart request concurrently;
  no focused test or implementation path reads either NPZ wholly into PHP
  memory or queues its bytes/path beyond request lifetime.
- [ ] The capture record exposes safe component state.  A retry of only a
  failed radiograph/gain retains prior successes and never repeats an already
  successful S3 write or MPIPS conversion; duplicate/replayed submission IDs
  create no duplicate capture, object, or DICOM.
- [ ] The Indonesian Operator UI warns before active submission, shows safe
  component-specific completion/failure state, supplies only the missing-file
  retry, and uses native unload protection while work is active.
- [ ] Current active-site/current-shift Operators can still discover, view,
  and normally download a completed DICOM; unauthorised users and all raw-NPZ
  routes remain denied.
- [ ] No unapproved probe scripts, presigned/public URL flow, secrets,
  endpoint/bucket/object IDs, NPZ/DICOM bytes, or patient data remain in the
  repository, evidence, UI copy, or executor report.
- [ ] A fresh disposable local run with dummy data and the approved local
  non-clinical pair proves the normal first submission and a component-only
  retry.  It is redacted and is not deployment/release evidence.

## Verification requirements

- Add focused fake-backed tests for plain private storage across the Member
  assets, consent/questionnaire, Image Gateway source objects, and DICOM;
  tests must prove grants/authorization/integrity remain fail-closed.
- Add Image Gateway feature tests proving concurrent dispatch initiation,
  partial component persistence, component-only retry, idempotent replay,
  MPIPS-only recovery after durable inputs, DICOM validation, and same-site /
  current-shift viewer and normal-download access.  Fakes must not call AWS or
  MPIPS.
- Add/update browser coverage for Indonesian warning/progress/retry behavior
  and the existing read-only viewer/download journey.
- Run the focused Member, Operator, Image Gateway, deployment, and security
  suites affected by the shared storage change; then `vendor/bin/phpunit`,
  `npm run build`, `vendor/bin/pint --test`, and `git diff --check`.
- Run one sanitised, disposable local rehearsal only after automated checks:
  seed existing dummy accounts, use the user-approved local non-clinical pair
  without copying, parsing, naming, or disclosing it, verify first submission
  plus one component-only retry and both authorised Operator DICOM actions,
  then clean the disposable database/private objects.

## Execution constraints

- Use the existing `PrivateObjectStore`, Laravel filesystem/AWS SDK, MPIPS
  client, capture tables, idempotency store, and queue.  Extend them only as
  required; do not add an abstraction, package, worker, or service when the
  installed primitives suffice.
- Preserve all validation at trust boundaries, 100 MiB per-file limit,
  two-file request envelope, manifest signing, private MPIPS authentication,
  DICOM validation, and sanitised errors.
- Do not run manual S3 scripts, create a bucket, change S3 configuration, read
  or print environment values, or emit presigned links.  Only the single
  authorised reversible local rehearsal may contact configured S3/MPIPS.
- The Executor may directly fix any implementation/test/UI feedback that is
  demonstrably within this task's required outcome, preserves the above
  authority, and is verified before reporting.  Return to Planner if feedback
  changes policy, authorization, storage retention, external infrastructure,
  data migration, or a named out-of-scope boundary.

## Stop conditions

- Stop if existing private data must survive the encryption cutover, if the
  browser/server would need durable staging or unsafely retain raw NPZ after
  request completion, or if a result cannot be retried without a new policy.
- Stop if concurrency requires public browser access, a new dependency/service,
  AWS/bucket/IAM change, secret disclosure, or a direct browser-to-MPIPS call.
- Stop if the configured local S3/MPIPS path is unavailable, returns an unsafe
  result, or disposable cleanup cannot be confirmed.  Return sanitised observed
  boundary status only.

## Side-effect authorization

### Explicitly authorized side effects

- Repository changes within this task's stated scope, including migrations,
  tests, Indonesian JSON copy, context/matrix/evidence, and removal of the
  listed invalid tracked scripts.
- One disposable, local, non-clinical dummy-data S3/MPIPS rehearsal with
  confirmed cleanup after automated verification passes.

Not authorised: Git commit, push, pull request, deployment, release,
production/server mutation, bucket/IAM changes, real-member import, credential
delivery, secret disclosure, or non-reversible external writes.

## Expected terminal outcome

`REVIEW REQUIRED` — return one immutable implementation revision with redacted
verification and local-rehearsal evidence.  The Reviewer decides acceptance
before the separate local deployment/readiness task may proceed.

## Remediation required

### Review basis and verdict

`REMEDIATION REQUIRED` — reviewed against this governing task at
`5748a88ff349cc1695d172379acbe1d2cd02566e`, accepted predecessor
`0f6f6e3552a4ace5a057e6415eac8057cd03dcee`, and implementation revision
`ecb107c8995704921a992ca0e25adb31c40e26d7`.

The implementation removes the unapproved encrypted wrapper and probe scripts,
uses private opaque S3 keys, retains grant and integrity enforcement, and adds
an Operator warning/partial-file UI.  Its reported automated checks are useful
but do not establish the required local rehearsal.

Two defects prevent acceptance:

1. A capture intent freezes the radiograph and gain checksums in the signed
   manifest, but a later missing-component retry accepts any NPZ bytes.  This
   can make the durable source object differ from the immutable manifest and,
   when an earlier concurrent MPIPS conversion succeeded, from the returned
   DICOM.  The retry must reject a changed component before any S3 or MPIPS
   operation.
2. `docs/mvp/local-core-walkthrough.md` names
   `MHCS_IMAGE_PER_FILE_BYTES` and `MHCS_IMAGE_TOTAL_BYTES`, which are not
   configured by the application.  The approved one-source upload setting is
   `MHCS_MAX_UPLOAD_MB=100`; the two-file request envelope is derived by
   `config/mhcs.php`.  The inaccurate guide cannot prepare a truthful local
   run.

### Required corrections

- Preserve each checksum recorded when a capture intent is created.  Before a
  missing radiograph or gain is streamed to S3 or MPIPS on a later request,
  compare its checksum with that capture component's recorded checksum.  If it
  differs, reject the request with a sanitised validation result, leave every
  existing component/state/DICOM unchanged, and do not issue any new S3 or
  MPIPS request.  A retry with the original bytes remains allowed.
- Add focused fake-backed coverage for: (a) a missing-component retry with
  original bytes preserves the existing gain and DICOM and sends no second
  MPIPS request; and (b) a changed retry component is rejected before external
  work and creates no additional object, capture, DICOM, or MPIPS request.
- Correct `docs/mvp/local-core-walkthrough.md` to name only
  `MHCS_MAX_UPLOAD_MB=100`, explain that it is the individual-file limit, and
  state that the application derives the two-file multipart request envelope.
  Do not introduce, document, or depend on replacement upload-limit variables.
- Add a focused non-network test with the already locked AWS SDK/Guzzle
  primitives demonstrating that both S3 source operations and the MPIPS
  multipart operation are initiated before the capture waits for their
  outcomes.  A local filesystem fake that completes each write inline is not
  evidence of concurrent S3 initiation.  Do not add a dependency, a storage
  abstraction, or a live S3/MPIPS probe for this test.
- Perform the one authorised disposable local rehearsal after all automated
  checks pass.  The approved non-clinical input files are, for this execution
  only, radiograph
  `research/kambing-260714/kambing/BED_1783222264263.npz` and matching gain
  `research/kambing-260714/gain/BED_1783219207291.npz`.  Use the existing
  files directly.  Do not copy, rename, parse, inspect, commit, log, or
  disclose their paths, filenames, bytes, metadata, or contents outside this
  task's execution instruction.  The report must record only redacted pass or
  fail, component-only retry outcome, first/second authorised Operator
  viewer/download observations, and disposable database/private-object cleanup.

### Remediation acceptance criteria

- [ ] A changed missing-component retry is rejected before external work; an
  original-byte retry alone is accepted and preserves all completed components
  and DICOM without a second MPIPS conversion.
- [ ] A non-network focused test demonstrates actual concurrent initiation of
  both S3 source uploads and MPIPS submission, rather than merely a successful
  final state under synchronous local fakes.
- [ ] The local walkthrough exposes the single supported 100 MiB upload setting
  and the derived pair envelope accurately.
- [ ] The fresh local rehearsal uses the approved pair under the existing
  side-effect boundary and records redacted normal submission, component-only
  retry, same-site/current-shift viewer/download, and cleanup evidence; or an
  existing stop condition is observed and reported truthfully.
- [ ] All original task verification remains passing, including full PHPUnit,
  browser, build, formatter, and diff checks.
