---
title: Operator Capture FormData Snapshot Remediation
document_id: MHCS-TASK-OPERATOR-CAPTURE-FORMDATA-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-13
scope:
  - repair the browser-only NPZ multipart submission ordering defect
  - preserve the accepted queued-capture workflow and local manual rehearsal
authority_note: This task is executable only after this exact content is committed and its immutable task revision is supplied to the Executor.
---

# Executable Task

## Task identity

**Task title:**
`Operator Capture FormData Snapshot Remediation`

**Task path:**
`.agents/tasks/operator-capture-formdata-snapshot-remediation.md`

**Task contract state:**
`Validated/Published when this exact content is committed and its commit SHA is supplied.`

**Delivery objective / Work Package / MVP:**
`Local Operator-to-DICOM manual-test remediation`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The local rehearsal at
`docs/mvp/evidence/mvp-local-deployment-readiness.md` exposed a browser-only
defect before any source was stored or external service contacted. The capture
page disables file controls before constructing `new FormData(form)`. Disabled
controls are excluded by the native browser FormData algorithm, so the existing
capture POST contains its CSRF and submission fields but no radiograph or gain
parts.

The repair is intentionally small: snapshot the existing form data while the
controls are enabled, then preserve the existing control lock, progress,
unload warning, status polling, and XHR send path. This restores the approved
uniform local/private-filesystem and production/private-S3 queued flow without
changing either flow's business sequence.

## Baseline and task revision

**Implementation baseline:**
`552759acc3c81e8eb6136a2c33c48df91852b796` — valid local-rehearsal stop
result and documentation update. The last accepted application behavior remains
`19ae9e16c6cae1ec0bfadf29afcf1c5fd6b2abfd`; no application change was made
by the stopped rehearsal.

**Relevant accepted predecessor:**
`.agents/tasks/operator-async-capture-status-and-worklist-sync.md @
8afd3dedc9f7e4920d59beb9e94d2e480bd6bc9f`

**Task revision:**
`resolved when published`

## Authoritative inputs

### Governing authority

- `docs/mvp/decision-log.md` — MVP-DEC-040 and MVP-DEC-041.
- `.agents/context/project.md` — durable private sources, queued MPIPS, and
  browser/MPIPS separation.
- `.agents/context/modules/operator/project.md` — active-site/current-shift
  Operator flow.
- `.agents/context/modules/image-gateway/project.md` — private source and
  returned-DICOM access policy.

### Requirement traceability

- `ARCH-030`, `ARCH-041`, `ARCH-042` → durable source acceptance and
  worker-only MPIPS flow in MVP-DEC-041.
- `IMG-006`, `IMG-007`, `IMG-013`, `IMG-028`, `IMG-060` → private objects,
  Image Gateway processing, and authorised DICOM access.
- `OPR-040`, `OPR-046`, `OPR-060`, `OPR-108`, `OPR-118` → capture submission,
  queue progression, and current-shift operator workflow.
- User-led local observation recorded in
  `docs/mvp/evidence/mvp-local-deployment-readiness.md` → browser multipart
  submission defect requiring this bounded remediation.

## Objective

Ensure an Operator's selected radiograph NPZ and matching gain NPZ are retained
in the existing multipart XHR payload even though the capture controls lock
immediately after submission. Prove the behavior with fake-backed browser
coverage, then return the local journey for user-led MPIPS/DICOM verification.

## Scope

### In scope

- In `resources/views/operator/xray-capture.blade.php`, construct one
  `FormData` snapshot before `setControls(true)` disables either file input.
  Use that unchanged snapshot in the existing `request.send(...)` call.
- Preserve the existing submission order after the snapshot: set `active` and
  `uploading`, disable controls, show native progress, register XHR handlers,
  and send exactly one request. Do not replace native `FormData` with custom
  file appending or a new upload abstraction.
- Amend `tests/Browser/Mvp14OperatorDicomRehearsalTest.php` so its existing
  mocked XHR captures the body passed to `send`. Before dispatching submit,
  assign two synthetic `File` objects through each file input's `DataTransfer`.
  Assert the captured body is `FormData` and contains both expected file fields
  as `File` values after the controls are disabled; also retain its checks for
  byte-level progress, disabled controls, in-flight unload protection, and
  queued-status unload release.
- Add the smallest relevant feature assertion only if browser coverage alone
  cannot prove that an ordinary multipart capture post still persists and
  queues the paired sources. Reuse
  `tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php`; do not add a
  parallel capture test harness.
- Update `docs/mvp/evidence/mvp-local-deployment-readiness.md` to mark the
  reported defect as remediated by fake-backed verification and to require a
  user-led manual re-test. Do not claim that the live NPZ, MPIPS, DICOM viewer,
  download, or second-Operator journey passed.
- Create concise redacted evidence in
  `docs/mvp/evidence/mvp-operator-capture-formdata-snapshot-remediation.md`.

### Out of scope

- MPIPS, S3/AWS, private-object storage, DICOM validation/viewer/download,
  queue-worker, admission-state, route, authorization, retry, migration, or
  localization changes.
- Local database/object reset, service start/stop, live NPZ submission,
  external MPIPS/S3/AWS request, object inspection/listing, or manual-browser
  workaround.
- New upload libraries, queues, workers, process managers, abstractions, or
  unbounded implementation of future feedback.

### Preserved behavior

- The browser remains the uploader to MHCS only; the Image Gateway queue
  worker remains the only MPIPS caller after durable source acceptance.
- Local filesystem and production S3 retain the same accepted-capture and
  source-only-retry semantics.
- Before source outcome is known, controls remain disabled and native unload
  protection remains active. Once safe status is `queued` or `processing`, the
  capture stays non-resubmittable but the unload warning releases; polling
  continues only while the page stays open.
- No selected file, private object key, checksum, secret, NPZ/DICOM content,
  or external response may be placed in tests, documentation, logs, or evidence.

## Dependencies and assumptions

### Dependencies

- Browser test support already used by
  `tests/Browser/Mvp14OperatorDicomRehearsalTest.php`.
- The existing capture page's native `FormData`, `XMLHttpRequest`, and control
  helpers; no new dependency is required.

### Approved assumptions

- A `FormData` instance snapshots successful file controls when constructed,
  whereas disabled file inputs are excluded. The manual observation and the
  current view source independently establish this condition.

### Remaining approval requirements

- User-led MPIPS/DICOM manual re-test remains required after review. This task
  provides no deployment or release approval.

## Required capabilities

- Repository read/write for the named view, browser test, feature test if
  needed, and redacted evidence files.
- Local PHP, browser, Node, formatter, and Git-diff checks using only fake
  storage and fake MPIPS.

## Execution constraints

- Write the browser regression assertion first and observe it fail on the
  baseline because the captured `FormData` lacks the two file fields. Then make
  the smallest view-only ordering change and rerun the same test.
- The `FormData` snapshot must be constructed once before `setControls(true)`;
  `request.send` must receive that snapshot rather than a second FormData
  instance or the form itself.
- Reuse the existing JavaScript variables, XHR request, browser test, and
  synthetic fixture conventions. Do not add application or browser-test
  dependencies.
- Run verification commands sequentially because the suite shares local
  private-storage state.
- Do not read `.env`, `credential.txt`, private objects, NPZ, DICOM, or logs
  for content. Do not make any live external call.

## Acceptance criteria

- [ ] Submitting a capture with both selected synthetic file inputs produces
  one native `FormData` body containing `radiograph_npz`, `gain_npz`, the CSRF
  field, and the existing `submission_id`, even after the page disables the
  inputs.
- [ ] The page still disables both file inputs and the submit control
  immediately, retains byte-level progress and duplicate prevention, and keeps
  native unload protection only while the POST XHR is in flight.
- [ ] A safe `queued` or `processing` response still releases the unload
  warning, preserves non-resubmittable controls, and leaves polling possible
  while the page is open.
- [ ] The existing fake-backed server-side paired-source acceptance/queue test
  remains passing; no source, external adapter, or worker behavior changes.
- [ ] The resulting evidence asks the user to retry the live local journey and
  makes no claim of live MPIPS, DICOM, S3, deployment, or release success.

## Verification requirements

### Required checks

Run sequentially, using local/fake-backed behavior only:

```bash
TARGET="." vendor/bin/phpunit tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php --colors=never
TARGET="." vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --colors=never
TARGET="." vendor/bin/phpunit --colors=never
TARGET="." npm run build
TARGET="." vendor/bin/pint --test
TARGET="." git diff --check
```

### Required evidence

The Executor must report:

- immutable implementation revision or exact working-tree state;
- the red browser assertion failure observed before the view correction and
  passing results after it;
- commands actually run and their observed results;
- exact changed files and the user-led manual re-test boundary;
- known verification gaps, blockers, or deviations without any secret,
  private object, NPZ/DICOM, or external-service information.

## Stop conditions

- Stop if the browser regression cannot be made to prove the actual XHR body
  contains both files without a new browser test dependency or real capture.
- Stop if the smallest repair requires a change beyond FormData/control-lock
  ordering, or if it affects durable-source, queue, authorization, retry, or
  external-adapter behavior.
- Stop if verification requires local reset, service manipulation, a secret,
  private file inspection, live NPZ, MPIPS, S3/AWS request, or manual capture.
- Stop if unrelated manual feedback appears; return it to Planner/Reviewer as
  a separate decision.

## Side-effect authorization

### Explicitly authorised side effects

- Repository changes limited to the named capture view, relevant existing
  browser/feature test, and redacted local/remediation evidence records.
- Local fake-backed tests, frontend build, formatter, and diff check.

Not authorised: Git commit, push, pull request, local data/object reset,
service start/stop, live external call, secret disclosure, production/server
mutation, release, dependency installation, or unrelated application changes.

## Expected terminal outcome

`REVIEW REQUIRED` — return one immutable implementation revision and redacted
verification evidence. The Reviewer must accept the result before the user
retries the local MPIPS/DICOM journey.
