# Local MHCS deployment and manual testing readiness

**Date:** 2026-08-13  
**Target:** `.`  
**Governing task:** `.agents/tasks/mvp-local-deployment-readiness.md`  
**Task revision:** `597e4859bd344c2e40d432dd927bca264a1662b2`  
**Implementation baseline:** `6f91c7b0c830c6bbbdc358ccfafe2ee25a16a47a`  
**Execution repository revision:** `597e4859bd344c2e40d432dd927bca264a1662b2`  
**Terminal state:** `USER TESTING READY`

## Redacted readiness evidence

- Local configuration-name check: **PASS**. Existing application/security,
  disposable database, database queue, private object storage, AWS, MPIPS,
  and upload-limit variable names were present. No configuration value was
  opened, printed, copied, changed, or committed.
- Database safety boundary: **PASS**. The selected repository-local database
  was reset with the task-authorized `migrate:fresh`; no shared, staging, or
  production database was used.
- Migration: **PASS**. Existing migrations completed successfully.
- Seed: **PASS**. `Database\\Seeders\\MvpCoreClinicSeeder` completed and created
  the synthetic clinic/operator data. Seed output identifiers and credentials
  were not recorded.
- Frontend build: **PASS**. `TARGET="." npm run build` completed. Existing
  optional-font, browser-externalization, and chunk-size warnings remain.
- Focused checks: **PASS**. The required focused PHPUnit set completed with
  126 tests, 120 passed, 6 skipped, and 1,225 assertions.
- Browser startup check: **PASS**. The fake-backed Operator DICOM browser check
  completed with 2 tests and 25 assertions; it did not call S3 or MPIPS.
- Formatter: **PASS**. `TARGET="." vendor/bin/pint --test`.
- Diff check: **PASS**. `TARGET="." git diff --check`.
- Web process: **PASS**. Native Laravel server is listening on
  `127.0.0.1:8013`.
- Queue process: **PASS**. Native database worker is running only for the
  `image-gateway` queue with the task-required 390-second timeout.
- Loopback readiness: **PASS**. Operator login returned `200`, the seeded
  public LCD returned `200`, and the initial Operator route redirected as
  expected.
- Credential file: **PASS**. Ignored root `credential.txt` exists with mode
  `0600`. Its contents were not opened, printed, copied, or recorded.

## Manual Operator-to-DICOM checklist

Use the existing seeded synthetic data and obtain credentials locally from the
ignored `credential.txt`. Never paste or record credential values.

1. Open `http://127.0.0.1:8013/operator/login` and sign in with the seeded
   primary Operator account.
2. Select the seeded active site and open the seeded attendance link.
3. Complete arrival, identity verification, paper-consent confirmation,
   ticket issue/print, basic examination, paper questionnaire, and X-ray steps.
4. Keep **Submit radiograph capture** open until the status is terminal.
5. Select exactly the CTO-approved local radiograph NPZ and matching gain NPZ
   from their existing local location. Do not copy, rename, open, hash, or
   record either file or any metadata.
6. Submit once. If the page identifies one missing component after an
   interrupted request, retry that component only once.
7. As a second authorised Operator on the same site and current shift, sign in,
   open DICOM results, and open the returned study.
8. Confirm the vertical read-only Cornerstone viewer, with automatic VOI and
   only zoom/pan controls.
9. Use **Download DICOM** once as a normal authenticated browser download.
10. Confirm the second Operator did not claim, submit, or change queue state.

The live capture, configured private storage/MPIPS path, viewer rendering, and
authenticated DICOM download remain **manual verification**. They are not
claimed as automatically proven by this report.

## Process stop and cleanup

- Leave the web server and `image-gateway` worker running for manual testing.
- When testing ends, stop each native process with `Ctrl+C` in its own terminal.
- Follow the walkthrough cleanup procedure in a `finally` block: remove only
  private objects created by this disposable database through the existing
  application store while rows remain available, then clean the disposable
  database. Do not list buckets or record object identifiers.
- If cleanup cannot be confirmed, stop and return the sanitized boundary to
  Planner/Reviewer.

## Known limitations and boundary

- No Executor-driven capture, NPZ inspection, DICOM parsing, direct S3/MPIPS
  request, object-storage probe, automation workaround, or external cleanup
  was performed.
- The seeded database is disposable and currently prepared for user-led
  manual testing; it is not production data.
- This evidence is local readiness evidence only. It is not deployment,
  production, release, clinical, or real-data evidence.
- No secret, credential, bucket/object identifier, patient data, NPZ content,
  NPZ metadata, DICOM content, or binary bytes are included here.

## Manual feedback received after handoff

**Date:** 2026-08-13  
**Disposition:** `REVIEW REQUIRED` — separate bounded remediation task needed  
**Source:** user-led local manual testing  
**Scope:** capture-page responsiveness and processing feedback only

### Observed findings

1. While the capture upload was still processing, opening the local root route
   in another browser tab remained loading. A fresh loopback request did not
   return within the local three-second observation window. The current native
   PHP development server was running with one request worker, while the
   capture POST path performs synchronous upload/MPIPS work before returning.
2. During capture submission, the two file controls remained usable. The page
   showed a text status but did not provide a visual progress indicator and did
   not disable the file inputs.
3. The current browser response handling displays a generic completion message
   when no component is reported missing. It does not poll for DICOM readiness
   or automatically navigate to the DICOM results worklist.

### Sanitised runtime evidence

The manually tested capture admission was observed in the local database as:

| Field | Observation |
|---|---|
| Admission state | `called` |
| Capture status | `capturing` |
| Processing status | `pending` |
| Radiograph component | `pending` |
| Gain component | `pending` |
| MPIPS status | `pending` |
| DICOM status | `pending` |
| Processing attempts | `0` |
| Last error | none recorded |

No identifier for the admission, object, study, bucket, or external service
was recorded.

### Expected remediation behavior

- Disable both file inputs and the submit button immediately after submission.
- Show an indeterminate native loading/progress state while the request is
  active; use byte-level progress only if the upload transport supports it.
- Prevent accidental navigation or duplicate submission while active.
- Expose a safe capture-status read path and poll until source acceptance,
  processing retry, terminal failure, or DICOM readiness is known.
- Navigate to the authorised DICOM results worklist only when a study is ready.
- Keep the backend's existing idempotency, private-object, authorization, and
  retry protections unchanged.

### Boundary and disposition

This feedback was documented only. No application behavior was changed, no
Executor-driven capture or direct S3/MPIPS probe was performed, and no NPZ or
DICOM file was inspected. The findings return to Planner/Reviewer for a new
validated remediation task; they are not claimed as fixed by this readiness
evidence.

## Additional selectable storage feedback

The manual local workflow should support an optional storage choice: the
existing application local filesystem/private-object mechanism or S3. The
selected backend must be controlled by local configuration and must not change
the private-object contract or production configuration.

### Expected local-only behavior

- Select either the existing local private-object disk or S3 through local
  configuration.
- When `local` is selected, AWS credentials, bucket configuration, and an S3
  endpoint must not be required.
- When `s3` is selected, retain the existing private S3 behavior and
  configuration requirements.
- Continue using the existing `PrivateObjectStore` boundary, opaque object
  keys, authorization grants, checksum/integrity checks, and browser
  non-disclosure rules.
- Keep MPIPS as a separate private conversion dependency unless a separate
  approved local MPIPS substitute is provided; local object storage alone
  does not prove DICOM conversion.
- Make cleanup deterministic by deleting only objects belonging to the
  disposable local database and confirming the local store is clean.
- Verify the same Operator capture, queue, DICOM persistence, read-only viewer,
  and authenticated download journey in both selectable modes.

### Boundary and disposition

This is a new local-environment capability request, not a configuration change
performed during this rehearsal. It requires a separate validated remediation
task and explicit acceptance criteria. Production/server deployment,
unapproved S3 replacement, bucket/IAM changes, and real-data use remain out of
scope.

## Additional concurrency feedback

The local workflow also needs explicit concurrency handling so a long capture
does not block unrelated Operator tabs.

### Required distinction

- **Web workers:** the native local web server needs more than one request
  worker for concurrent browser tabs during local manual testing. A capture
  request occupying one worker must not prevent login, worklists, or another
  local route from responding.
- **Queue workers:** the existing `image-gateway` queue worker processes MPIPS
  and DICOM work after capture acceptance. Adding queue workers alone will not
  unblock a browser request while the controller still waits synchronously.

### Preferred remediation behavior

Accept and durably record the upload, enqueue the processing job, return a
quick non-terminal response, and let the capture page poll a safe status route
until DICOM is ready or processing reaches a terminal failure. Scale queue
workers separately only when measured throughput requires it.

For local testing, multiple native web workers may be used as an interim
development-runtime safeguard, but this does not replace asynchronous capture
processing or status polling.

## Multi-Operator synchronization feedback

During manual testing with more than one authenticated Operator, one browser
could retain an older server-rendered worklist snapshot after another Operator
claimed a ticket. The shared database and atomic claim protection remained the
source of truth, but the already-open page did not automatically refresh.

### Expected remediation behavior

- Refresh the relevant Operator worklists automatically while they are open,
  using lightweight polling or an approved realtime mechanism.
- Remove or update tickets after another Operator claims or advances them.
- Preserve the existing server-side authorization and atomic claim checks so a
  stale page can never create a duplicate claim.
- Prefer polling for the MVP; add WebSockets/realtime infrastructure only when
  measured latency or scale requires it.
