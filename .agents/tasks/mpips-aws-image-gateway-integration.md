---
title: MPIPS v1.2 and AWS Image Gateway Integration
document_id: MHCS-TASK-MPIPS-AWS-IMAGE-GATEWAY-001
version: 1.5
status: validated-published
language: en-US
last_updated: 2026-08-13
scope:
  - replace the local synthetic capture bridge with durable asynchronous MPIPS conversion
  - use encrypted AWS S3 private objects in local and production runtime configurations
  - publish each returned DICOM to authorised same-site current-shift Operators
authority_note: This task is executable only after this exact content is committed and the immutable task revision is supplied to the Executor.
---

# Executable Task

## Task identity

**Task title:**
`MPIPS v1.2 and AWS Image Gateway Integration`

**Task path:**
`.agents/tasks/mpips-aws-image-gateway-integration.md`

**Task contract state:**
`Validated/Published when this exact content is committed and its commit SHA is supplied.`

**Delivery objective / Work Package / MVP:**
`12 August MVP delivery target / Image Gateway MPIPS + AWS integration`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The current capture path is an explicitly local/testing-only synthetic bridge:
it accepts repository fixtures, attaches a fixed DICOM immediately, and does
not call MPIPS.  It cannot satisfy the approved asynchronous clinic flow.

MPIPS contract v1.2 now accepts a minimal client manifest.  MHCS sends the two
NPZ files and a small JSON document containing real patient data; MPIPS derives
checksums, conversion identity, gain identity, DICOM UIDs, detector type, and
image spacing when those fields are omitted.  This removes the former need for
MHCS to guess hardware calibration values.

This task replaces that bridge with one durable acceptance path: the browser
stores a complete capture set privately, the ticket moves to `awaiting_ai`, and
an Image Gateway queue worker calls MPIPS.  Each valid returned DICOM becomes
available immediately to every authenticated Operator who has the same active
site and current-shift authority.  It is still unavailable to Members and
Doctors until a later full-examination publication task.

The earlier local-synthetic rehearsal execution is not accepted as a baseline
and is superseded for this overlapping Image Gateway surface.  After this task
is reviewed, a new local rehearsal task will be planned against its accepted
revision; do not revive or edit the prior rehearsal task during this work.

## Baseline and task revision

**Implementation baseline:**
`8c6fd1a9c49011c44d61f78f4c04be00b9ddfc1d` — unaccepted remediation and
evidence implementation to be completed.  It does not establish an accepted
baseline.

**Original execution baseline:**
`2cb939f31e170eeb5fec0e7b1b58cf4d964591e0` — the MPIPS v1.2 authoritative
documentation state governing the first execution attempt.  It does not accept
the prior local-rehearsal divergence.

**Superseded local task:**
`.agents/tasks/mvp-local-synthetic-rehearsal-launch.md @
dcf315b81f0c925f753cff0d4d8c41939e9a0c10`

**Task revision:**
`resolved when published`

## Remediation required

### Review basis and verdict

`REMEDIATION REQUIRED` — reviewed against governing task
`.agents/tasks/mpips-aws-image-gateway-integration.md @
c0b3901841c2d7110c61191ef1258817bea1e113`, implementation baseline
`2cb939f31e170eeb5fec0e7b1b58cf4d964591e0`, and implementation revision
`59edb00d38d7315cfc39737233c6b9d17bd66165`.

Observed review evidence: `composer validate --no-check-publish` passed;
the focused Feature/Localization/Architecture suite passed with 14 tests and
1,910 assertions; `git diff --check` passed.  The combined focused suite had
one browser-test setup error (`SQLSTATE[HY000]: disk I/O error` while creating
its isolated SQLite database), so it is not passing browser evidence.  No
Executor evidence report, fresh isolated-MySQL migration result, complete PHP
suite/build/format result, reversible AWS probe result, or loopback MPIPS probe
result was supplied or found.

The reviewed queue state machine sets `processing_status` to `processing` and
then treats every later delivery with that state as a no-op.  If a worker dies
after that claim and before terminal completion/failure, a normal Laravel
redelivery leaves the capture permanently stuck and never calls MPIPS.  This
violates the durable, idempotent redelivery requirement.  The existing tests
cover completed re-delivery but not this interrupted-attempt path.

### Round-two review result

`REMEDIATION REQUIRED` — the queue-lease correction at
`7e615d011d3dd262a712a7c1f72fb5a19d781b72` is within scope, and the focused
Feature/Localization/Architecture suite passed with 17 tests and 1,925
assertions.  `composer validate --no-check-publish` and `git diff --check`
also passed.  This is insufficient for acceptance.

The required `tests/Browser/Mvp14OperatorDicomRehearsalTest.php` still fails:
one of its two tests stops during `migrate:fresh` with `SQLSTATE[HY000]:
General error: 10 disk I/O error` against its isolated SQLite database.  Its
setup removes the SQLite file before purging its existing connection, and it
does not establish one deterministic disposable database shared by the browser
server and the test's HTTP/database setup.  No fresh MySQL migration, full PHP
suite, build/format, AWS cleanup probe, loopback MPIPS probe, or required
Executor evidence report has been supplied.

### Round-two remediation scope

- Continue from `7e615d011d3dd262a712a7c1f72fb5a19d781b72`; preserve the
  lease-fenced queue recovery and its focused redelivery/duplicate tests, as
  well as all submitted capture, encryption, S3, authorization, viewer, and
  normal DICOM-download behavior.
- Repair only the Mvp14 browser-test isolation.  Before deleting or recreating
  a disposable SQLite file, purge/disconnect the active SQLite connection; use
  one deterministic disposable database shared by the browser server and the
  test's HTTP/database setup; and clean only that disposable database and its
  SQLite sidecar files.  Do not weaken, skip, serially mask, or replace the
  actual browser viewport and attachment-download assertions.
- Prove both Mvp14 browser journeys run against the prepared disposable data:
  the submitting Operator sees a ready vertical read-only Cornerstone viewport
  and normal DICOM download, and the authorised second current-shift Operator
  discovers, views, and downloads that same result.
- Close the original task's verification evidence: fresh SQLite and isolated
  MySQL 8.4 migration coverage; all required focused tests and the applicable
  full PHP suite; `npm run build`; repository formatter checks; `git diff
  --check`; Composer locked-version/audit output; the reversible synthetic AWS
  private-object write/read/delete probe with cleanup; and the local loopback
  MPIPS synthetic capture probe including authorised second-Operator
  viewer/download verification and disposable-data cleanup.

### Round-two remediation acceptance criteria

- [ ] The existing lease-fenced interrupted-redelivery, concurrent-duplicate,
  and completed-replay coverage remains passing; it never creates a second
  DICOM study or completed transition.
- [ ] Both Mvp14 browser tests pass from a fresh disposable SQLite database;
  their server, HTTP, and direct database paths observe the same prepared
  study, without a SQLite disk-I/O error or stale browser data.
- [ ] The focused interruption/redelivery tests, browser rehearsal, fresh
  SQLite and isolated MySQL migration coverage, applicable full PHP suite,
  build, formatter, Composer validation/audit, and diff check all pass with
  observed results.
- [ ] The original task's AWS and loopback MPIPS probes pass with the mandated
  non-clinical data and cleanup confirmations, and the required evidence report
  records every command and result without disclosing secrets, endpoints,
  object keys, patient data, NPZ, or DICOM bytes.

### Round-three review result

`REMEDIATION REQUIRED` — reviewed against governing task
`.agents/tasks/mpips-aws-image-gateway-integration.md @
85b2a0800432901df15567e1d97eb2ac6b4e7149`, implementation baseline
`7e615d011d3dd262a712a7c1f72fb5a19d781b72`, and implementation revision
`8cde93bd44ceed11ef46bc608a4da4b9f1102583`.

The focused Image Gateway, localization, and architecture suite passed with
17 tests and 1,925 assertions.  The required Mvp14 browser rehearsal now
passes with 2 tests and 24 assertions.  `composer validate --no-check-publish`
and `git diff --check 85b2a0800432901df15567e1d97eb2ac6b4e7149
8cde93bd44ceed11ef46bc608a4da4b9f1102583` also pass.  The change is limited
to the Browser test's SQLite connection cleanup.

This is not enough for acceptance.  The original task still has no observed
fresh SQLite and isolated-MySQL migration coverage, applicable full PHP suite,
build, formatter, Composer audit/locked S3-adapter result, reversible AWS
probe, loopback MPIPS probe, or repository evidence report.  Those are all
existing mandatory verification conditions, not new product scope.

### Round-three remediation scope

- Continue from `8cde93bd44ceed11ef46bc608a4da4b9f1102583`.  Preserve all
  submitted capture, encryption, S3 selection, queue-lease recovery,
  authorization, Indonesian UI, read-only vertical viewer, and ordinary DICOM
  attachment-download behavior.  Do not make further implementation changes
  solely to manufacture evidence.
- Re-run and record every original-task required check from this exact
  baseline: fresh SQLite and isolated MySQL 8.4 migrations; focused Image
  Gateway/Operator/authorization/storage/localization/manifest tests; the
  complete applicable PHP suite; the Mvp14 browser rehearsal; `npm run build`;
  `vendor/bin/pint --test`; Composer validation plus `composer audit`; and the
  stated diff check.  A failed check is a real outcome: diagnose only the
  bounded defect, correct it only when it remains within this task, and rerun
  the affected evidence.  Never skip, weaken, serially mask, or mark a check
  passing without its observed result.
- Run the already-authorized reversible synthetic AWS private-object probe and
  the loopback MPIPS synthetic conversion probe exactly as specified below.
  Confirm cleanup in each case.  If either target is unavailable, unconfirmed,
  or contract-incompatible, stop under the existing stop conditions and return
  the sanitised terminal result; do not substitute a public, production,
  unknown, or real-clinical target.
- Create `docs/mvp/evidence/mpips-aws-image-gateway-integration.md` as an
  evidence report for this delivery objective.  It must state the exact task
  and implementation revisions, commands and observed totals/results, changed
  files, dependency/audit result, browser/viewer/download result, probe
  pass/fail and cleanup status, known gaps, and the required no-disclosure
  confirmation.  It must not contain secrets, endpoints, bucket or object
  identifiers, patient data, raw NPZ, or DICOM content.

### Round-three remediation acceptance criteria

- [ ] The 17-test focused regression suite and both Mvp14 browser journeys
  pass from the disposable test state.  The submitting Operator and an
  authorised same-site/current-shift second Operator each observe the ready
  vertical read-only viewer and an ordinary DICOM download.
- [ ] Fresh SQLite and isolated MySQL 8.4 migrations, the complete applicable
  PHP suite, `npm run build`, `vendor/bin/pint --test`, Composer
  validation/audit, and the required diff check pass with observed results.
- [ ] The reversible AWS private-object probe and loopback MPIPS synthetic
  capture probe pass with the mandated cleanup, or an existing stop condition
  produces a sanitised terminal result without an unsafe substitute.
- [ ] `docs/mvp/evidence/mpips-aws-image-gateway-integration.md` contains the
  required observed evidence and no prohibited secret, infrastructure,
  clinical, or binary disclosure.

### Round-four review result

`REMEDIATION REQUIRED` — reviewed against governing task
`.agents/tasks/mpips-aws-image-gateway-integration.md @
e9d606a70a7af6eb1d2df6b48811ad9c2be3a825`, implementation baseline
`8cde93bd44ceed11ef46bc608a4da4b9f1102583`, and the evidence revision
`8c6fd1a9c49011c44d61f78f4c04be00b9ddfc1d`.

The latest evidence records a successful configured-loopback MPIPS conversion,
one stored study, completed-replay idempotency, and authorised second-Operator
viewer/download.  It also records that the local input was a Grabber NPZ pair.
The CTO has explicitly approved that specific local Grabber pair as a
non-clinical integration fixture.  It remains local-only and must not be copied
to, parsed by, or committed in this repository.

The evidence still cannot establish acceptance.  It says MHCS parses returned
patient identity and DICOM UID elements, which is not the implemented boundary:
MHCS validates the DICOM media type, Part-10 marker, and response identifiers,
then derives expected UIDs from the MPIPS job ID.  The report's Markdown
trailing whitespace fails `git diff --check`.  Its claimed full Pint run also
fails on the pre-existing `app/Console/Kernel.php`; the task requires the
repository formatter check to pass.  Finally, its global `*.npz` ignore rule
would impede ordinary future fixture work and is broader than protecting the
approved local input.

### Round-four remediation scope

- Continue from `8c6fd1a9c49011c44d61f78f4c04be00b9ddfc1d`; preserve every
  Image Gateway runtime behavior and all prior observed passing results.  Do
  not change production code, MPIPS contract fields, storage policy, queue
  policy, or DICOM viewer behavior merely to rerun evidence.
- Treat the CTO-approved local Grabber radiograph/gain pair as the sole
  authorised non-clinical live MPIPS fixture for this task.  Use it only from
  its existing local location with a synthetic test patient, the configured
  loopback endpoint, and the configured API key.  Do not commit, copy, upload
  outside the probe, inspect NPZ contents in PHP, print its bytes or metadata,
  or substitute real-member or clinical data.  The probe may use a local
  process memory limit sufficient for the pair; it must not change application
  memory configuration or introduce a parser/dependency.
- Correct `docs/mvp/evidence/mpips-aws-image-gateway-integration.md` so it
  describes only observed MHCS behavior: response content type, Part-10 marker,
  UUID response identifiers, UID derivation, one persisted study, replay
  idempotency, and authorised second-Operator viewer/download.  Record the
  exact executed probe command and result while retaining the existing
  no-secret/no-endpoint/no-bucket/no-object/no-patient/no-binary disclosure
  boundary.  Remove Markdown trailing whitespace.
- Replace the newly added global `*.npz` ignore with at most the minimal
  `research/` directory ignore needed to keep the approved local fixture out of
  Git.  Do not add, remove, or alter any NPZ fixture tracked by the repository.
- Run the repository formatter on the exact pre-existing
  `app/Console/Kernel.php` failure and accept only its mechanical Pint changes;
  do not alter executable behavior, add logic, or refactor the class.  This is
  the minimal correction required for the task's existing full formatter
  verification, not a new product capability.
- Rerun and record the complete task verification after these changes: Composer
  validation/audit and locked S3 adapter, fresh SQLite and isolated MySQL 8.4
  migrations, focused suite, full PHP suite, browser rehearsal, build, complete
  Pint check, diff check, reversible AWS probe, and the successful approved
  Grabber-pair loopback MPIPS probe with full disposable-data cleanup.

### Round-four remediation acceptance criteria

- [ ] The evidence accurately describes MHCS's implemented DICOM response
  boundary, is free of trailing whitespace, and records the authorised local
  Grabber-pair probe without disclosing prohibited data.
- [ ] Only `research/` may remain as a new ignore rule for the local fixture;
  no global NPZ ignore or repository fixture change is introduced.
- [ ] `app/Console/Kernel.php` changes only by the repository formatter and
  the complete `vendor/bin/pint --test` check passes.
- [ ] The full original verification matrix, including the approved Grabber-pair
  MPIPS conversion, one-study/replay result, second-Operator viewer/download,
  AWS cleanup, migrations, tests, build, audit, and diff check, passes with
  observed evidence.

## Objective

**Objective:**
Replace the synthetic capture-to-DICOM bridge with a durable, encrypted S3,
queued MPIPS v1.2 conversion flow that accepts one radiograph/gain NPZ pair,
stores every valid returned DICOM, and exposes it for normal authenticated
viewing and `.dcm` download to any authorised current-shift Operator at the
same active site.

## Authoritative inputs

### Governing authority

- `docs/mvp/decision-log.md` — MVP-DEC-031, MVP-DEC-033, MVP-DEC-035, and
  MVP-DEC-036.
- `.agents/context/project.md` — Image Gateway ownership, private MPIPS-only
  network boundary, durable acceptance, queue-worker rule, and private storage.
- `.agents/context/modules/operator/project.md` — Operator capture ownership,
  active-site/current-shift authority, immutable submitted metadata, and raw
  NPZ restriction.
- `.agents/context/modules/image-gateway/project.md` — capture processing,
  partial DICOM availability, and Image Gateway storage ownership.
- `docs/mpips/mhcs-dicom-api.md` at `2cb939f…` — authoritative v1.2 minimal
  multipart contract, server derivation, status handling, and retries.
- User-approved delivery decision in this planning conversation: use configured
  AWS object storage for both local development runtime and production runtime;
  use the current local MPIPS endpoint for integration verification.

### Requirement traceability

- `MVP-DEC-031` → work is sequential on `main`; no parallel local-rehearsal
  remediation is performed.
- `MVP-DEC-033` → existing private consent/questionnaire objects remain private.
- `MVP-DEC-035` → returned DICOM uses a standard authenticated browser
  attachment response; raw NPZ is never downloadable.
- `MVP-DEC-036` → each successful MPIPS DICOM is visible and downloadable to
  every authorised same-active-site/current-shift Operator.
- `OPR-031..OPR-046`, `OPR-057..OPR-060`, and `IMG-060` → operational capture,
  asynchronous processing, and read-only DICOM access.

## Scope

### In scope

- Replace `SyntheticCaptureGatewayService` and its synthetic-only controller,
  view, route text, fixtures, and focused tests with one production-capable
  Image Gateway capture service.  It accepts exactly one `radiograph_npz` and
  one matching `gain_npz`; it does not parse NPZ arrays in PHP.
- Remove fixture-name/fixture-byte requirements and the local/testing-only
  environment gate.  Validate the HTTP upload boundary before persistence:
  exactly two non-empty `.npz` uploads, ZIP/NPZ signature, 100 MiB maximum per
  file, and 300 MiB maximum request pair.  Reject invalid input without object,
  database, queue, ticket-state, audit, or outbox residue.
- On accepted upload, use the existing encrypted `PrivateObjectStore` to store
  radiograph, gain, the canonical UTF-8 minimal client manifest, and its
  detached internal signature.  Persist checksums, byte counts, immutable
  manifest identity, source Operator/site/booking/admission references, and
  processing state in Image Gateway-owned tables.  Add the minimal migration
  needed to retire the `fixture_pair_id` meaning and represent processing,
  attempt, error, response-ID, and completion data.
- Build the submitted MPIPS manifest from current authoritative data only:
  `patient.member_id`, MRN, name, administrative gender when representable,
  birth date, booking/examination description, submitting Operator, and active
  site.  Include real `performed_at` and `captured_at` timestamps.  Apply the
  explicitly approved temporary mapping `service_request_id = booking_id` and
  `encounter_id = booking_id`; mark neither as a real ServiceRequest or
  Encounter.  Omit detector type, gain ID, image spacing, file metadata,
  conversion IDs, and DICOM UIDs so MPIPS v1.2 resolves them.
- Dispatch one Laravel database-queue Image Gateway job only after the database
  transaction commits.  The web request must never call MPIPS.  The job reads
  only its immutable private objects, reuses their exact bytes and stored
  canonical manifest semantics for every retry, and calls only configured
  `MPIPS_BASE_URL/v1/radiographs/dicom` with `X-MPIPS-API-Key` plus exactly the
  three mandated multipart fields: `radiograph_npz`, `gain_npz`, and `manifest`.
- Implement the v1.2 retry matrix with a maximum of five total attempts,
  2-second exponential base, 30-second cap, and full random jitter.  Retry
  429, 502, 503, 504, network timeout/reset, and 409 only when detail is
  `IDEMPOTENCY_IN_PROGRESS`; do not retry 401, 413, 422, 409 conflict, malformed
  response, or 500.  Persist a controlled processing failure after the budget
  is exhausted.  Never log NPZ/DICOM bytes, patient name, birth date, MRN, or
  the API key.
- On HTTP 200, require `application/dicom`, non-empty DICOM Part-10 preamble
  (`DICM` at byte 128), and non-empty valid-UUID response headers
  `X-Conversion-Job-ID` and `X-Correlation-ID`.  MPIPS v1.2 is the authoritative
  DICOM dataset validator.  MHCS validates this response boundary, derives the
  expected Study/Series/SOP Instance UIDs from the documented MPIPS UUIDv5
  job-ID formulas, and persists those IDs with the response headers, checksum,
  and size.  Make unavailable transfer-syntax, dimensions, and VOI metadata
  nullable and let the existing Cornerstone viewer use its normal automatic
  presentation.  Reject malformed or mismatched transport output without a
  study row or public result.
- Keep the existing read-only vertical Cornerstone viewer and normal attachment
  download route.  Change it from synthetic-only wording and service binding to
  the real Image Gateway service.  Returned studies are listed, viewed, and
  downloaded by any authenticated Operator whose active site and current shift
  authorise the capture; no Member/Doctor route or raw-NPZ route is added.
- Configure the existing encrypted private-object implementation to select a
  configured filesystem disk instead of hard-coding `local`.  Add the Laravel
  S3 adapter dependency (`league/flysystem-aws-s3-v3`) and configure
  `MHCS_PRIVATE_OBJECT_DISK=s3` for local and production runtime templates.
  Preserve encryption-before-storage, opaque object keys, grant checks, and the
  testing disk override.  Do not print or alter `.env` secrets.
- Add `MPIPS_BASE_URL`, timeout, queue timing, and private-object-disk names to
  `.env.example` without values/secrets.  The default local endpoint is
  `http://127.0.0.1:8014`; production receives its private endpoint only from
  deployment environment configuration.
- Update every changed browser-visible MHCS-authored string through `lang/id.json`.
  Do not introduce English fallback UI text or a locale switcher.

### Out of scope

- AWS bucket/IAM creation, production deployment, release, server mutation,
  real-member import, 37-member seed, Docker/Compose, reverse proxy, CI/CD, and
  object-retention/legal-deletion policy.
- Any direct browser, Member, Doctor, or Operator-to-MPIPS call; any public URL,
  presigned DICOM link, raw-NPZ download, OCR, AI/Doctor routing, or Member/
  Doctor result publication.
- Multiple captures/projections per submission, capture metadata configuration
  screens, a new queue framework, client-side concurrency semaphore, MPIPS
  async-job API, service-request/encounter schema, or replacing the temporary
  booking-ID mapping.
- Changing the earlier local rehearsal task, creating credential files, custom
  Artisan server commands, or changing unrelated Operator workflows.

### Preserved behavior

- Paper consent and questionnaire photos remain private encrypted objects and
  keep their existing access controls.
- Object data remains encrypted by MHCS before filesystem/S3 persistence;
  neither database object keys nor S3 objects become public.
- The browser receives a normal authenticated `.dcm` attachment response with
  `Cache-Control: no-store, private` and `X-Content-Type-Options: nosniff`.
- Operator access remains active-site and current-shift constrained.  The
  submitting Operator is not the sole result recipient after a DICOM succeeds.
- Durable NPZ acceptance moves the ticket to `awaiting_ai` before MPIPS returns;
  failure/retry does not resurrect the member’s clinic visit or expose NPZ.
- Existing local/testing-only synthetic tests may be replaced by real-flow tests
  with fake S3 and fake HTTP; no test accesses actual AWS or MPIPS unless the
  explicit live probes below are run.
- Existing `ManifestSigner` security coverage remains meaningful: it signs the
  frozen local submission envelope; the signature is detached and is not added
  to the strict MPIPS minimal client JSON.

## Dependencies and assumptions

### Dependencies

- MPIPS v1.2 at the configured local endpoint is reachable with the existing
  `MPIPS_API_KEY`; it accepts the documented minimal manifest.
- Existing AWS environment variables identify a private bucket usable by this
  application.  Their values are secret and must never appear in source, tests,
  task evidence, or chat.
- A queue worker is available for `image-gateway` after acceptance.  This task
  configures the application contract; the later local rehearsal task will give
  end-user run instructions.

### Approved assumptions

- MPIPS v1.2 is authoritative for detector, gain-ID, image-spacing, checksum,
  deterministic-job, and deterministic-DICOM-UID resolution when those client
  fields are omitted.
- `service_request_id` and `encounter_id` are both represented by the existing
  `booking_id` only for this integration.  A future Member-domain task replaces
  that temporary mapping with true identifiers.
- The configured S3 bucket is the approved private object destination for both
  local development runtime and production runtime.  Automated test runtime
  may continue using `Storage::fake()` and a non-S3 testing disk.

### Remaining approval requirements

- A successful local AWS and local MPIPS probe is integration evidence only; it
  does not authorize deployment, production mutation, release, real data, or
  closing MVP-GAP-019/020/021/022/023.
- Do not run an AWS probe if its endpoint/bucket is not confirmed as the
  user-provided local test target.  Stop rather than trying a production or
  unknown endpoint.

## Required capabilities

- Repository read/write, Composer dependency installation, PHP/Laravel, MySQL,
  npm, and browser test execution.
- Configured AWS S3 access for one reversible synthetic-object smoke probe.
- Configured loopback MPIPS access for one synthetic fixture integration probe.

## Execution constraints

- Begin from the immutable implementation baseline.  Do not modify the old
  local-rehearsal task or claim it accepted.
- Use Laravel’s existing database queue and existing `PrivateObjectStore`,
  `IdempotencyStore`, audit, outbox, authorization, and Indonesian JSON-locality
  conventions.  Add only the MPIPS adapter, queued job, minimal migration, and
  validation code necessary for this outcome.
- Make the S3 disk selection explicit in app configuration.  Never fall back to
  public storage or silently downgrade a configured S3 runtime to local disk.
  Test-only configuration may select a fake local disk deliberately.
- The HTTP client timeout must be configured at 360 seconds.  The `image-gateway`
  worker timeout and database queue retry-after must exceed it (minimum 390 and
  420 seconds respectively) so one MPIPS conversion is not executed twice by
  Laravel’s lease expiry.
- The job receives a capture-set ID only; it re-queries authoritative state,
  locks the row for completion/failure transitions, and must be idempotent if
  Laravel redelivers it.  Store the single canonical manifest JSON before
  dispatch; never rebuild it on retry.
- Do not inspect NPZ internals, enable pickle parsing, write clinical files to
  repository paths, or retain plaintext temporary DICOM files.  Do not add a
  third-party DICOM parser or a general-purpose DICOM parser implementation:
  MPIPS v1.2 validates its DICOM dataset before returning it and MHCS validates
  the documented transport boundary.
- Do not log or expose private object keys, uploads, DICOM bytes, API headers,
  `MPIPS_API_KEY`, AWS values, names, MRNs, birth dates, or full clinical
  metadata.  Operational records may contain capture ID, local submission ID,
  server conversion/correlation IDs, attempt number, sanitized status, and
  duration.
- Preserve the strict MPIPS multipart names and minimal-client JSON shape;
  never attach the internal detached signature or undocumented fields.
- Update/replace tests before deleting synthetic behavior.  Cover success,
  malformed response, terminal response, retryable response, retry stability,
  post-commit dispatch, same-site current-shift second-Operator access, and
  denial across site/shift/no-role boundaries.

## Acceptance criteria

- [ ] An authenticated, authorised X-ray Operator can upload exactly one valid
  radiograph NPZ and one valid gain NPZ.  Acceptance persists encrypted private
  source objects plus an immutable minimal MPIPS manifest, advances the ticket
  to `awaiting_ai`, emits one audit/outbox result, and queues conversion only
  after commit; it does not create a DICOM study synchronously.
- [ ] The submitted multipart request has exactly `radiograph_npz`, `gain_npz`,
  and `manifest` fields and the `X-MPIPS-API-Key` header.  The minimal manifest
  supplies required patient MRN/name and real available MHCS data while omitting
  MPIPS-derived hardware/file/ID fields.  Retries preserve exact source bytes
  and equivalent stored manifest semantics.
- [ ] S3 is the configured private-object disk in local and production runtime
  templates.  Stored objects remain application-encrypted and non-public; the
  test suite uses a fake testing disk instead of AWS.
- [ ] Queue handling follows v1.2: eligible transient failures retry with the
  bounded jitter policy; terminal failures record a sanitised failure and do
  not create a DICOM; duplicate delivery does not create a second study or
  repeat a completed transition.
- [ ] A successful MPIPS response is accepted only after content type, DICOM
  Part-10 preamble, and matching response-header checks pass.  The stored study
  persists the DICOM bytes only once with Study/Series/SOP UIDs derived from
  the documented MPIPS UUIDv5 job-ID formulas; unavailable display metadata is
  nullable and the viewer uses automatic presentation.  Invalid transport
  output is not visible or downloadable.
- [ ] Each stored returned DICOM appears in the existing results worklist and
  vertical read-only viewer, and any same-active-site/current-shift Operator can
  view and download it as a normal authenticated `.dcm` attachment.  Cross-site,
  wrong-shift, Member, Doctor, anonymous, and raw-NPZ access remain denied.
- [ ] The browser UI is Indonesian and all MHCS-authored changed visible copy is
  in `lang/id.json`; synthetic-only UI labels, routes, and acceptance behavior
  no longer remain.
- [ ] The current synthetic-only bridge is removed without modifying the prior
  local-rehearsal task or claiming deployment/release readiness.

## Verification requirements

### Required checks

- Run Composer validation and install the explicitly authorised S3 adapter;
  record the exact locked version and `composer audit` result.  No DICOM parser
  dependency or system package is added.
- Run fresh migration coverage on SQLite and the repository’s isolated MySQL
  8.4 path.  Run focused Image Gateway, Operator capture/worklist, DICOM access,
  storage, authentication/authorization, Indonesian localization, and previous
  manifest-signer tests.  Include queue/HTTP fakes and `Storage::fake()`; no
  automated test may call AWS or MPIPS.
- Run the entire applicable PHP feature/unit suite, `npm run build`, formatter
  checks used by the repository, and `git diff --check`.
- Run a reversible AWS smoke probe only against the user-provided local test
  bucket/endpoint: write a unique synthetic non-clinical object through
  `PrivateObjectStore`, read it through a valid grant, verify exact bytes, and
  delete both ciphertext and metadata in `finally`.  Report only pass/fail and
  cleanup confirmation, never bucket, endpoint, keys, object key, or content.
- Run one local MPIPS probe only against configured loopback MPIPS with the
  repository’s synthetic NPZ pair and a synthetic test patient.  Drive the real
  queued capture job, record the resulting DICOM success/failure status and
  returned IDs without patient data, verify the authorised second-Operator
  viewer/download route, then clean the disposable database and the created
  synthetic private objects.  Do not print headers, secrets, object keys, or
  binary content.  If MPIPS is unavailable or rejects the documented minimal
  request, stop and return the observed sanitised status/body code to planning.

### Required evidence

The Executor must report the implementation revision; exact governing task
revision; all commands actually run; changed/deleted files; migration result;
test totals; dependency versions/audit result; build/format/diff results;
S3 probe pass/fail and cleanup; MPIPS probe pass/fail and sanitised status;
browser access/download result; known gaps; and explicit confirmation that no
secret, AWS value, MPIPS API key, bucket/object name, patient data, raw NPZ, or
DICOM bytes were disclosed.  Local evidence must not be presented as deployment
or production evidence.

## Stop conditions

- Stop if `docs/mpips/mhcs-dicom-api.md` is not v1.2, contradicts its minimal
  client contract, or requires an extra client field not supplied by current
  MHCS authority.
- Stop if the AWS target cannot be established as the explicitly user-provided
  local test target, storage cannot remain encrypted/non-public, or the task
  would require bucket/IAM/server/deployment mutation.
- Stop if MPIPS cannot process the documented minimal request, returns a
  contract-incompatible DICOM response, or the real probe needs real clinical
  data, a secret disclosure, or a non-loopback endpoint.
- Stop if the MPIPS response omits the documented job/correlation identifiers,
  the documented UUIDv5 UID derivation cannot be reproduced deterministically,
  or permanent acceptance would require a third-party DICOM parser, system
  package, or a broader DICOM implementation.
- Stop if multiple capture/projection handling, real ServiceRequest/Encounter
  records, Member/Doctor publication, AI/Doctor routing, or another task’s
  unreviewed local-rehearsal changes become necessary to satisfy acceptance.
- Stop if a migration cannot preserve existing capture/study records or a safe
  rollback/forward migration path cannot be verified.

## Side-effect authorization

### Explicitly authorized side effects

- Repository changes limited to this objective, including the required Composer
  dependencies/lockfile, Image Gateway migration, configuration/template,
  service/job/controller/view/localisation, and focused tests.
- Disposable local database creation/reset and local browser/build/test work.
- One reversible synthetic AWS private-object write/read/delete probe against
  the user-provided local test target.
- One loopback local MPIPS synthetic integration probe using the configured API
  key without displaying, copying, or persisting the secret outside normal
  environment use.

Not authorized: Git commit, push, pull request, deployment, release, bucket or
IAM provisioning/change, server/Docker/reverse-proxy mutation, production or
unknown AWS/MPIPS use, real-member data, secret disclosure, credential delivery,
or changes outside this task.

## Expected terminal outcome

`REVIEW REQUIRED` — return an immutable implementation revision and the full
local synthetic verification evidence.  The Reviewer decides acceptance and,
only after that, plans the reconciled local rehearsal followed by local testing.
