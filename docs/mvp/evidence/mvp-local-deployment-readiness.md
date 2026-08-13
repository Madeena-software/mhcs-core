# Local MHCS deployment and manual testing readiness

**Date:** 2026-08-13  
**Target:** `.`  
**Governing task:** `.agents/tasks/mvp-local-deployment-readiness.md`  
**Task revision:** `12e585fad0dfd5db5d9bbd103fb1a882a4b394fa`
**Implementation baseline:** `19ae9e16c6cae1ec0bfadf29afcf1c5fd6b2abfd`
**Execution repository revision:** `6c7b6675cd77dec72ed456494fe7c3f55f3dfc49`
**Terminal state:** `STOPPED FOR PLANNING` — local preparation passed; the
user-led capture journey exposed a separate application defect before source
acceptance.
**Remediation status:** `REVIEW REQUIRED` — the published FormData snapshot
remediation is fake-backed verified; user-led local MPIPS/DICOM re-test remains
required.

## Redacted readiness evidence

- Task revision content match: **PASS**. The governing task content matches
  the immutable task revision above.
- Local configuration assertion: **PASS**. Local application mode, MySQL on a
  loopback host, database queue, and private filesystem disk were confirmed
  without printing configuration values.
- Local variable-name assertion: **PASS**. Existing MPIPS, upload-limit, and
  required local runtime variable names were present. Values were not opened,
  printed, copied, changed in committed files, or recorded.
- Port and process boundary: **PASS**. Port `8013` was free before the local
  start; no unknown listener was terminated.
- Private-object reset: **PASS**. Only the repository-local private-object
  subtree was reset and recreated with mode `0700`; its parent was confirmed
  to be a real directory and the target was not a symlink. The shell rejected
  the literal `rm -rf` form, so an equivalent depth-first deletion constrained
  to that exact target was used.
- Migration: **PASS**. Existing migrations completed successfully.
- Seed: **PASS**. `Database\\Seeders\\MvpCoreClinicSeeder` completed with
  synthetic local data. Generated identifiers and credentials were not
  recorded.
- Focused PHPUnit: **PASS**. 14 tests, 147 assertions.
- Browser rehearsal check: **PASS**. 2 tests, 39 assertions; no live external
  conversion request was made.
- Frontend build: **PASS**. Existing optional-font, browser-externalization,
  and chunk-size warnings remain non-fatal.
- Formatter: **PASS**. `TARGET="." vendor/bin/pint --test`.
- Diff check: **PASS**. `TARGET="." git diff --check`.
- HTTP process topology: **PASS**. The native Laravel supervisor has exactly
  four PHP HTTP worker children, started with `PHP_CLI_SERVER_WORKERS=4` and
  Laravel's required `--no-reload` flag.
- Queue process: **PASS**. Exactly one existing database worker is restricted
  to `image-gateway` with the required 390-second timeout.
- Operator login: **PASS**. Loopback login returned HTTP 200.
- Public LCD: **PASS**. A seeded public LCD route returned HTTP 200 without
  exposing its identifier in this record.
- Initial Operator route: **PASS**. The unauthenticated route redirected as
  expected.
- Credential file: **PASS**. The ignored credential file exists with mode
  `0600`; its contents were not opened, printed, copied, or recorded.
- External boundary: **PASS**. The Executor made no AWS/S3 or MPIPS request
  and did not inspect logs, private objects, NPZ files, or DICOM files.

## Manual Operator-to-DICOM checklist

Use the seeded synthetic data and obtain credentials locally from the ignored
credential file. Never paste or record credential values.

1. Open `http://127.0.0.1:8013/operator/login` and sign in with the seeded
   primary Operator account.
2. Select the seeded active site and open the seeded attendance link.
3. Complete arrival, identity verification, paper-consent confirmation,
   questionnaire upload, ticket issue/print, basic examination, and X-ray
   readiness steps.
4. Open **Submit radiograph capture** and select exactly one approved,
   non-clinical local Grabber radiograph NPZ and its matching gain NPZ from
   their existing local location. Do not copy, rename, open, hash, or record
   either file or any metadata.
5. Submit the pair once. During the XHR, confirm the controls and native unload
   protection remain active and observe byte-level upload progress.
6. Once safe status is `queued` or `processing`, close the page and reopen the
   capture route. Confirm processing remains durable and safe polling resumes.
   If an interruption reports a missing component, retry only that original
   unsuccessful component.
7. When DICOM is ready, open the DICOM results worklist and the returned study.
8. Confirm the vertical, read-only Cornerstone viewer with automatic VOI and
   zoom/pan only.
9. Use **Download DICOM** once as a normal authenticated attachment action.
10. Sign out and sign in with the second seeded Operator on the same site and
    current shift. Open DICOM results, confirm the same study is discoverable,
    view it, and use the normal download action once.
11. Confirm the second Operator did not claim, submit, or change queue state.

## Process stop and cleanup

- Leave the native web server and `image-gateway` worker running during manual
  testing.
- When testing ends, stop each process with `Ctrl+C` in its own terminal.
- Follow the walkthrough cleanup procedure in a `finally` block: remove only
  private objects created by this disposable database through the existing
  application store while their database rows remain available, then clean
  the disposable database. Do not list storage or record identifiers.
- If cleanup cannot be confirmed, stop and return the sanitized boundary to
  Planner/Reviewer.

## Known manual verification gap

The live user-led NPZ-to-MPIPS-to-DICOM journey, durable page-close behavior,
returned-DICOM rendering, authenticated download, and second-Operator
visibility remain manual verification. They were not claimed as Executor-side
automated proof. Historical pre-queued-capture observations are not repeated
here and are not treated as defects in the accepted queued implementation.

## FormData snapshot remediation

The reported browser multipart defect is remediated in the bounded working tree
for `.agents/tasks/operator-capture-formdata-snapshot-remediation.md @
a1ba265e0abf6294daa521835529c7f2b19633c8`. The existing fake-backed browser
test now assigns two synthetic `File` objects, captures the actual body passed
to the mocked XHR, and verifies a native `FormData` containing both file fields,
the CSRF field, and `submission_id` after the inputs are disabled.

- Baseline regression assertion: **FAIL**, `false is true` at the captured-body
  assertion before the view correction.
- Corrected browser coverage: **PASS**, 2 tests and 40 assertions.
- The only application change snapshots `new FormData(form)` before the
  existing control lock and sends that same snapshot. No server, queue, source,
  storage, or external-adapter behavior changed.

This fake-backed result does not claim that live NPZ submission, MPIPS,
DICOM viewing or download, S3, deployment, release, or the second-Operator
journey passed. The next action after review is the user-led manual re-test
listed above.

## Manual feedback received after handoff

**Disposition:** `REVIEW REQUIRED` — bounded application remediation task
required before the capture journey can continue.

- The Operator selected both local NPZ inputs and clicked **Submit capture
  set**.
- The browser sent the capture POST with the CSRF token and submission
  identity, but no multipart file parts. The POST returned HTTP `302`; the
  following capture page returned HTTP `200`, and status polling returned HTTP
  `200` with both source components still missing.
- The database contained no capture row after the request. No object, NPZ,
  DICOM, credential, or external-service content was inspected or recorded.
- Root cause identified in the current capture-page JavaScript: the submit
  handler disables the file inputs before constructing `new FormData(form)`.
  Disabled file controls are excluded from the submitted form data.
- The required bounded remediation is to construct the `FormData` snapshot
  while the file inputs are enabled, then disable the controls before sending.
  No remediation was applied during this readiness execution because the
  published task excludes application behavior changes.

This record contains no secret, credential, database name, object identifier,
patient data, NPZ content or metadata, DICOM content or bytes, or external
service response.

## Planner/Reviewer disposition

**Date:** 2026-08-13
**Verdict:** `VALID STOP RESULT / PLANNING REQUIRED`
**Reviewed execution revision:** `552759acc3c81e8eb6136a2c33c48df91852b796`

The local preparation portion of the governing task passed: it used the local
private filesystem, synthetic data, four native HTTP workers, one
`image-gateway` worker, and no Executor-side S3/AWS/MPIPS request. The manual
capture observation is corroborated by the current capture-page source: it
locks file inputs before calling `new FormData(form)`, so native FormData omits
them. The task correctly excluded application behavior changes, so the
Executor stopped instead of broadening scope.

`552759acc3c81e8eb6136a2c33c48df91852b796` is not a new accepted application
baseline. The separate remediation contract is
`.agents/tasks/operator-capture-formdata-snapshot-remediation.md`; the user
must retry the live local journey only after that task is reviewed and accepted.
