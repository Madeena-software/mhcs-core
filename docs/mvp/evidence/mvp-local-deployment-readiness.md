# Local MHCS deployment and manual testing readiness

**Date:** 2026-08-13
**Target:** `.`
**Governing task:** `.agents/tasks/mvp-local-deployment-readiness.md`
**Task revision:** `12e585fad0dfd5db5d9bbd103fb1a882a4b394fa`
**Implementation baseline:** `19ae9e16c6cae1ec0bfadf29afcf1c5fd6b2abfd`
**Execution repository revision:** `195b1e10020697e0899913a691ab20725c73e8e5`
**Terminal state:** `USER TESTING READY`

## Redacted readiness evidence

- Task revision content match: **PASS**. The governing task content matches
  the immutable task revision above.
- Local configuration assertion: **PASS**. Local application mode, MySQL on a
  loopback host, database queue, and private filesystem disk were confirmed
  without printing configuration values.
- Local variable-name assertion: **PASS**. Existing MPIPS, upload-limit, and
  required local runtime variable names were present. Values were not opened,
  printed, copied, changed in committed files, or recorded.
- Port and process boundary: **PASS**. Port `8013` was already owned by the
  Executor-started local Laravel server; no unknown listener was terminated.
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
- Browser rehearsal check: **PASS**. 2 tests, 40 assertions; no live external
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
automated proof. The earlier multipart submission observation is superseded by
the accepted FormData snapshot remediation at the current execution revision;
the live journey still requires user re-test.

This record contains no secret, credential, database name, object identifier,
patient data, NPZ content or metadata, DICOM content or bytes, or external
service response.

## Planner/Reviewer feedback handoff

The following user-led findings are recorded for planning and review only.
They are outside this local-readiness task, were not implemented here, and
must not be treated as accepted product behavior.

1. **Attendance-to-DICOM next action.** When a member's examination already
   has a returned DICOM study, the attendance page still offers **Open basic
   examination worklist** instead of the next appropriate DICOM/result action.
2. **Readable public references.** User-visible schedule and study references
   should use short, human-readable, unique display references. Internal
   UUIDs and authorization identifiers must remain unchanged and must not be
   exposed as the primary user-facing label.
3. **Basic-examination claim conflict.** Claiming a later ticket while an
   earlier ticket already has DICOM produced an HTTP 500/Conflict instead of a
   safe, user-facing operational response. The supplied trace points to
   `PortalController::claimBasicExamination`.
4. **DICOM-processing interaction lock.** After capture acceptance, the
   current capture page should prevent unsafe competing actions while waiting
   for DICOM, with behavior consistent with the existing NPZ upload lock.
5. **DICOM viewer.** The current viewer remains visually inadequate for the
   product-facing result screen, does not meet the requested portrait-monitor
   presentation, and may remain on **Loading DICOM** without a useful failure
   state. Requested direction: a polished read-only viewer in a browser popup,
   optimized for a vertical display, using the existing design references in
   `docs/operator/reference/claude-design/` and the `/var/www/mhcs-operator-core`
   repository. The viewer may open in a separate browser window because it is
   intended for a dedicated vertically oriented monitor.
6. **No active site: eligible shifts.** `/operator/eligible-shifts` raises an
   internal `OperatorException` instead of presenting a safe site-selection
   state. The supplied trace identifies `OperatorAuthorization.php:137`.
7. **No active site: basic worklist.**
   `/operator/basic-examination-worklist` returns HTTP 403 before a site is
   selected; it should provide the intended site-selection flow.
8. **No active site: X-ray readiness worklist.**
   `/operator/xray-readiness-worklist` returns HTTP 403 before a site is
   selected; it should provide the intended site-selection flow.
9. **No active site: DICOM studies.** `/operator/studies` returns HTTP 403
   before a site is selected; it should provide the intended site-selection
   flow.

### Planning boundary

These findings require a new validated task or bounded remediation issued by
Planner/Reviewer. No application code, migration, authorization policy,
viewer implementation, or product behavior was changed during this local
readiness execution. The attached user error reports were used only to
capture sanitized route and failure-class evidence; request cookies,
credentials, identifiers, and payload values are intentionally omitted.
