---
title: Uniform Queued Capture Processing and Operator Status Synchronisation
document_id: MHCS-TASK-OPERATOR-ASYNC-CAPTURE-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-13
scope:
  - replace active-request MPIPS conversion with one durable-source queued flow
  - provide safe capture-status polling, byte-level upload progress, and DICOM-result navigation
  - refresh Operator worklists without changing queue ownership rules
  - define the five-worker local HTTP and Image Gateway capacity for the later redeployment
authority_note: This task is executable only after this exact content is committed and its immutable task revision is supplied to the Executor.
---

# Executable Task

## Task identity

**Task title:**
`Uniform Queued Capture Processing and Operator Status Synchronisation`

**Task path:**
`.agents/tasks/operator-async-capture-status-and-worklist-sync.md`

**Task contract state:**
`Validated/Published when this exact content is committed and its commit SHA is supplied.`

**Delivery objective / Work Package / MVP:**
`Pre-deployment local MVP: one Operator-to-DICOM workflow in local and production`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The accepted implementation at
`7bc8b14cfd1e696d5e78000555b97ed6e09a7bf5` performs the first MPIPS call in
the Operator capture HTTP request while source writes are in progress.  The
database job is only a recovery path.  Local manual evidence records that this
long request blocks another browser request when the native PHP server has one
worker; the form also lacks a safe completion-status poll and DICOM redirect.

MVP-DEC-041 replaces that shared processing sequence.  In both environments,
the browser submits the NPZ pair once, MHCS durably accepts the complete source
set on the configured private disk, the clinical ticket advances to
`awaiting_ai`, and the existing Image Gateway queue worker calls MPIPS.  The
only environment difference is the already-configured storage backend: local
filesystem in the disposable local runtime and private S3 in production.

For the later local redeployment, set the ignored local
`PHP_CLI_SERVER_WORKERS=4` for four native PHP HTTP workers and run one
`image-gateway` queue worker. The HTTP workers are interchangeable rather than
route-specific: they collectively provide capacity for normal pages,
consent/questionnaire uploads, and NPZ uploads. The queue worker is the only
MPIPS worker. This five-worker capacity is operational, not a different local
business flow.

This is a new bounded workflow objective.  It must be reviewed and accepted
before the separate local-filesystem redeployment/manual-testing task is
republished.  It is not authority to deploy, reset local data, start services,
or call live MPIPS or S3.

## Baseline and task revision

**Implementation baseline:**
`7bc8b14cfd1e696d5e78000555b97ed6e09a7bf5` — accepted local-readiness
evidence, with the active-request conversion behavior still present.

**Related accepted predecessor:**
`.agents/tasks/private-object-concurrent-capture-transport.md @
10ac3604fd57e647b6d500801f74387521033237`

**Task revision:**
`resolved when published`

## Authoritative inputs

### Governing authority

- `docs/mvp/decision-log.md` — MVP-DEC-035, MVP-DEC-036, MVP-DEC-037,
  MVP-DEC-038, MVP-DEC-040, and approved MVP-DEC-041.
- `.agents/context/project.md` — single application runtime, asynchronous
  work, private MPIPS boundary, and Image Gateway ownership.
- `.agents/context/modules/operator/project.md` — durable capture completes
  the X-ray stage and moves the ticket to `awaiting_ai`.
- `.agents/context/modules/image-gateway/project.md` — durable source
  ownership, per-capture DICOM result availability, and private MPIPS worker.
- `docs/mpips/mhcs-dicom-api.md` — existing MHCS-to-MPIPS multipart contract.
- `docs/mvp/evidence/mvp-local-deployment-readiness.md` — observed local
  blocking, capture feedback, and stale-worklist feedback.

### Requirement traceability

- `ARCH-030`, `ARCH-041`, and `ARCH-042` → private Image Gateway worker is
  the only MPIPS caller.
- `IMG-006`, `IMG-007`, `IMG-013`, `IMG-028`, and `IMG-060` → durable paired
  sources, worker conversion, raw-NPZ denial, and individual DICOM visibility.
- `OPR-040`, `OPR-046`, `OPR-060`, `OPR-108`, and `OPR-118` → accepted capture
  completes the Operator stage; Image Gateway owns binaries; authorised
  current-shift DICOM access remains protected.

## Objective

Replace the active-request MPIPS path with one durable-source and queued-worker
flow for every storage backend, then give Operators clear, Indonesian capture
status and automatically refreshed worklists through the existing application
routes and queue.

## Scope

### In scope

- Change `ImageGatewayCaptureService` so an Operator capture request may write
  only the missing NPZ components plus manifest/signature to the configured
  `PrivateObjectStore`; it must never invoke `MpipsClient`, validate a returned
  DICOM, or retain MPIPS response bytes.
- Preserve concurrent initiation of the two independent source-object writes
  where the selected store supports it.  Completion semantics are identical
  for local filesystem and S3: retain each successful immutable component,
  mark only an unsuccessful component retryable, and never require resubmission
  of a durable sibling.
- Once—and only once—both NPZ components and the matching signed
  manifest/signature are durable, atomically accept the capture, move the
  admission to `awaiting_ai`, record the existing audit/outbox effects, and
  dispatch the existing `ProcessCaptureSet` job after transaction commit on
  `image-gateway`.  A replay must neither duplicate the capture, object,
  admission transition, job effect, nor DICOM.
- Make `ProcessCaptureSet` the only execution path that calls MPIPS, validates
  its response, persists DICOM, manages worker lease/retry/failure state, and
  makes each successful DICOM available through the existing authorised results,
  viewer, and normal `.dcm` attachment download routes.
- Add one authenticated, capture-authorised safe status route and service
  query for the capture page.  It may expose only stable capture ID, safe
  processing state, missing component names, and a ready-results URL; it must
  not disclose raw NPZ/DICOM bytes, object keys, checksums, manifest contents,
  MPIPS details, patient data, or another Operator's capture authority.
- Update the existing capture form to use that status endpoint after a success
  or interrupted request. While a request is active, immediately disable the
  file inputs and submit button, expose accessible upload-start, byte-level
  upload-progress, and processing-status states, retain native unload
  protection, prevent duplicate submissions, and use only `lang/id.json` for
  new visible copy. Use native `XMLHttpRequest` upload-progress events rather
  than `fetch`, because the browser must show actual transmitted-byte progress
  without a new dependency. Poll with native browser APIs after the upload; on
  missing-source state, allow only the missing original component to be
  submitted; on ready DICOM, navigate to the existing results worklist; on a
  terminal processing failure, show a safe retry/status result without claiming
  a DICOM exists.
- Add lightweight native periodic refresh to the existing verification,
  basic-examination, X-ray-readiness, and DICOM-results worklist pages so
  another authorised Operator observes state/result changes without a manual
  browser refresh.  Preserve the current server-side atomic claim/call/start
  checks as the source of truth; polling must not create or mutate queue work.
- Update `docs/mvp/local-core-walkthrough.md` and the affected project/Image
  Gateway context wording to describe the uniform durable-source → queued
  worker → DICOM-result flow. The walkthrough must specify the later local
  runtime's five-worker capacity: `PHP_CLI_SERVER_WORKERS=4` plus one
  `image-gateway` queue worker, without inventing route-specific upload queues.
  Update only the requirement-matrix traceability or verification wording that
  changes with this policy.

### Out of scope

- Local redeployment, changing ignored `.env`, database/object-tree reset,
  starting PHP/queue services, real MPIPS/S3 calls, 37-member import,
  production/server mutation, release, or infrastructure/IAM changes.
- A direct browser-to-MPIPS call, persistent NPZ staging, a new queue or job
  framework, separate upload queue, WebSockets, polling package, service,
  dependency, viewer, DICOM editing controls, public/presigned object URL, or
  raw-NPZ download.
- Changes to MPIPS protocol, conversion algorithm, AI/Doctor/Member
  publication, storage retention, object encryption policy, or the approved
  same-site/current-shift DICOM policy.

### Preserved behavior

- Private objects remain original unencrypted bytes under opaque keys, grants,
  integrity checks, private storage, and no browser object route; all existing
  private-object consumers stay on `PrivateObjectStore`.
- Individual NPZ size limits, request validation, frozen checksum/manifest
  matching, idempotency, component-only retry, DICOM validation, queue lease
  and bounded retry behaviour remain fail-closed.
- A successful source component is immutable.  A retry with changed bytes is
  rejected before a write or worker dispatch; a durable sibling and any study
  are never overwritten.
- Any authenticated Operator whose active site and current shift authorise the
  examination retains read-only vertical Cornerstone viewing and ordinary
  authenticated raw-DICOM attachment download.  Raw NPZ remains unavailable.
- Indonesian remains the only UI locale and every new MHCS-authored visible
  string, including status and accessibility copy, comes from `lang/id.json`.

## Dependencies and assumptions

### Dependencies

- The accepted database queue, `ProcessCaptureSet`, `PrivateObjectStore`,
  MPIPS client, capture tables, authorization services, and current DICOM
  routes are available at the implementation baseline.
- Local configuration will select the existing private filesystem disk only in
  the later redeployment task; production keeps the existing private S3 disk.

### Approved assumptions

- Storage backend selection changes I/O implementation only.  It does not
  change capture state transitions, retry semantics, authorization, routes, or
  whether MPIPS runs asynchronously from the Operator browser.
- The existing MPIPS request/response contract remains valid when invoked by
  the queue worker; no MPIPS API change is needed for this task.

### Remaining approval requirements

- None beyond the task's existing authority for repository changes and local,
  fake-backed verification.  Deployment, local reset/reseed, live MPIPS/S3
  calls, and release remain separately authorised.

## Required capabilities

- Repository read/write, shell, PHP/Laravel test execution, browser test
  execution, and frontend build tooling.
- No production, AWS/S3, MPIPS, credential, or deployment capability is
  required or authorised.

## Execution constraints

- Reuse the existing service, job, queue, routes, Blade layout, translation
  JSON, tests, and storage adapter.  Do not introduce an abstraction or package
  when the existing Laravel and browser primitives suffice.
- Treat upload handling as HTTP work. For the local walkthrough, define ignored
  local `PHP_CLI_SERVER_WORKERS=4` so the four generic HTTP workers provide
  capacity for pages, non-NPZ uploads, and NPZ uploads, plus the existing
  separate `image-gateway` queue worker for MPIPS. Do not claim that a
  route-specific upload worker exists or add a separate queue to simulate one.
- Keep the capture HTTP response bounded by source acceptance.  It may wait for
  configured private-disk writes but never for MPIPS conversion or DICOM
  persistence.
- Use a small native polling interval and cancel it when the page is unloaded
  or reaches a terminal state.  Do not use polling as an authorization bypass,
  a queue-mutating action, or a substitute for server-side atomic claims.
- Keep logs, error responses, tests, documentation, and evidence free of
  secrets, endpoints, object keys, patient data, and NPZ/DICOM contents.
- The Executor may correct directly reported feedback only when it demonstrably
  falls within this task's objective and preserved boundaries.  A change to
  policy, MPIPS protocol, authorised data access, storage retention, deployment,
  or a stated out-of-scope boundary returns to Planner/Reviewer.

## Acceptance criteria

- [ ] For both `local` and `s3` private disks, capture acceptance follows the
  same observable sequence: durable pair plus manifest/signature, accepted
  capture and `awaiting_ai`, one queued worker conversion, then DICOM-ready
  results.  No web-request path calls MPIPS.
- [ ] A successful radiograph/gain pair queues exactly one eligible processing
  job after the durable acceptance transaction; a duplicate/replay cannot
  duplicate source objects, the admission transition, MPIPS conversion, or a
  study.
- [ ] Source writes retain prior successes independently.  A later retry sends
  only a missing same-checksum component, never overwrites a durable component,
  and only queues conversion after the complete durable set exists.
- [ ] The worker alone performs MPIPS conversion, preserves current integrity,
  DICOM validation, lease/retry/failure protections, and publishes each
  completed DICOM to every authorised active-site/current-shift Operator.
- [ ] The capture UI visibly disables all file/submit controls and presents an
  accessible upload-start, real byte-progress, and processing state during
  submission; after source acceptance or a recoverable interrupted request it
  polls safe status, redirects only when DICOM is ready, and exposes no new
  non-JSON Indonesian copy.
- [ ] The four Operator worklists refresh automatically without creating queue
  actions.  Their server-side claims/calls/starts stay atomic and scoped to the
  active site and current shift.
- [ ] The local walkthrough specifies ignored local
  `PHP_CLI_SERVER_WORKERS=4` plus one separate `image-gateway` queue worker.
  These five workers provide capacity for pages, non-NPZ uploads, NPZ uploads,
  and MPIPS without changing local capture state, storage, MPIPS, or
  authorisation behavior relative to production.
- [ ] The viewer remains vertical and read-only, normal DICOM download remains
  an authenticated attachment, and raw NPZ/object data remains inaccessible.
- [ ] Context, walkthrough, and matrix wording no longer describe active
  capture-request MPIPS conversion as the normal path.

## Verification requirements

- Replace the direct-concurrency assertions in
  `tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php` with fake-backed
  tests that prove a capture POST performs no MPIPS request, persists the paired
  sources, advances to `awaiting_ai`, and dispatches exactly one queued job
  after source acceptance.
- Add focused tests for independent source failure/retry, checksum mismatch
  rejection before external work, idempotent replay, worker-only MPIPS request,
  DICOM persistence, and safe capture-status allow/deny/no-leak responses.
- Update the existing browser coverage to verify disabled file controls,
  actual upload-progress state, safe polling/redirect logic, Indonesian JSON
  copy, and lightweight worklist refresh without any live S3/MPIPS call.
- Retain/extend the existing same-site/current-shift DICOM list, viewer, and
  normal attachment-download allow/deny tests, including a result that becomes
  ready while another capture remains pending or failed.
- Run the focused Image Gateway, Operator queue, browser, localization, and
  shared-storage tests; then run `vendor/bin/phpunit`, `npm run build`,
  `vendor/bin/pint --test`, and `git diff --check`.  Tests use local/fake
  storage and fake MPIPS only.
- Record concise redacted verification evidence in a task-appropriate existing
  evidence record or a new narrowly named MVP evidence file.  Do not claim
  local deployment, live MPIPS/S3 conversion, or release completion.

## Stop conditions

- Stop if durable source acceptance cannot be separated from active-request
  MPIPS without a new storage-retention, persistent-staging, or MPIPS-contract
  decision.
- Stop if the required flow would need a browser-to-MPIPS path, public object
  access, a new dependency/service, production/S3/IAM change, secret exposure,
  or a live external call.
- Stop if safe polling or automatic refresh cannot retain active-site/current-
  shift authorization and existing atomic worklist behavior.
- Stop if changing the existing capture status fields would require a data
  migration whose compatibility or historical-data treatment is not already
  clear from the baseline; return that decision to Planner/Reviewer.

## Side-effect authorization

### Explicitly authorised side effects

- Repository changes within this task's scope: application code, routes, views,
  tests, Indonesian JSON copy, context/matrix/walkthrough, and redacted local
  fake-backed verification evidence.

Not authorised: Git commit, push, pull request, local database/object reset,
service start/stop, live MPIPS/S3/AWS call, bucket/IAM change, production/server
mutation, deployment, release, real-member import, secret disclosure, or raw
clinical-file inspection/copying.

## Expected terminal outcome

`REVIEW REQUIRED` — return one immutable implementation revision and redacted
verification evidence.  The Reviewer must accept it before the local filesystem
redeployment and manual-testing task is republished.
