# Local MHCS deployment and manual testing readiness

**Target:** `.`
**Governing task:** `.agents/tasks/mvp-local-deployment-readiness.md`
**Terminal state:** `USER TESTING READY`

**Execution revision:** repository `81b59b0e242d444842607ad55c46e534397ed722`;
working-tree changes remain uncommitted because this task does not authorize a
Git commit.

## Redacted execution evidence

- Task content and local scope: **PASS**.
- Local mode, loopback MySQL, database queue, and local private filesystem:
  **PASS**; values were not disclosed.
- Exact disposable database/object reset, migrations, and seed: **PASS**.
- Fresh seed: **PASS** — five synthetic Members; exactly two called X-ray
  admissions, privately owned one each by the primary and second Operator;
  both have no capture set or DICOM; repeat seed is idempotent.
- Native topology: **PASS** — four loopback HTTP workers and one
  `image-gateway` queue worker with timeout 390.
- Loopback login, public LCD, initial Operator route, and credential mode:
  **PASS**; credential contents were not read or recorded.
- JavaScript tests, focused PHPUnit, full PHPUnit, frontend build, formatter,
  and diff check: **PASS** when observed; no browser automation was used as a
  readiness gate.
- External boundary: **PASS** — no AWS/S3 or MPIPS call, log inspection,
  private-object inspection, NPZ inspection, or DICOM inspection occurred.

## Sanitized viewer root-cause evidence

Before this task, the production browser bundle externalized Node’s `events`
module while the transitive XML builder evaluated a class extending
`undefined.EventEmitter`; the application therefore stopped during bootstrap
before requesting the protected DICOM route. The current build resolves the
local browser compatibility module, defers the viewer chunk, emits no
`events` externalization warning, and passes the static bundle check. This is
build evidence only; the authorised user-led study run remains required for
product acceptance.

Changed files: `README.md`, `database/seeders/MvpCoreClinicSeeder.php`,
`tests/Feature/Operator/MvpCoreClinicSeederTest.php`,
`docs/mvp/local-core-walkthrough.md`, and
`docs/mvp/evidence/mvp-local-deployment-readiness.md`.

## User-led checklist

1. Primary Operator: sign in, select site/current shift, open the already-called
   X-ray capture, and confirm no capture set/DICOM exists.
2. Submit the approved local non-clinical radiograph/gain NPZ pair once.
   Observe progress and disabled inputs; at `queued`/`processing`, close and
   reopen and confirm durable polling. Retry only a reported missing component.
3. Confirm the short `DCM-…` reference, current-tab portrait read-only viewer,
   automatic VOI, zoom/pan only, and normal **Unduh DICOM** attachment download.
4. If loading fails, confirm the safe Indonesian error state from
   “Memuat DICOM…”, with download and return actions. Do not manufacture failure.
5. Second same-site/current-shift Operator: discover, view, and download the
   first study; open the second Operator’s own called X-ray capture; confirm it
   is ready for a separate pair and unauthorised access is safely denied.

Return only sanitized PASS/FAIL, symptoms, and non-sensitive screenshots.
Manual findings return to Planner/Reviewer and do not authorize release.

## Known gap

The live NPZ upload, durable page-close/reopen behavior, MPIPS conversion,
returned DICOM viewer, download, and second-Operator journey remain
manual evidence. Historical viewer/reference feedback is superseded by the
current candidate and is not a current defect; any new symptom is a separate
Planner/Reviewer finding.

## New user-led feedback for Planner/Reviewer

The following feedback is recorded for planning only. No application behavior
was changed under this readiness task.

1. The session ticket currently exposes an opaque UUID-like value. Replace it
   with a short human-readable display reference using the established `MRN-`,
   `DCM-`, and `JAD-` style. Keep the internal UUID unchanged for routing,
   authorization, audit, and idempotency.
2. The Operator DICOM study page should remain the primary, polished
   current-tab UI/UX surface with read-only portrait presentation, automatic
   VOI, and zoom/pan only. This is a product/UI task for Planner/Reviewer, not
   a readiness-task fix.
3. The user reports that the DICOM image remains at the loading state and does
   not render. Treat this as an unresolved viewer/runtime finding requiring
   Planner/Reviewer triage. Preserve the safe Indonesian failure state and the
   normal DICOM download path while investigating; do not claim DICOM display
   success from this readiness evidence.

These findings contain no credentials, private-object data, NPZ/DICOM content,
external responses, or raw identifiers.

## Current-tab viewer remediation evidence — 2026-08-14

The published task .agents/tasks/operator-current-tab-dicom-viewer.md is
being executed in the existing study view and viewer module. The viewer now
uses Cornerstone’s protected stack-loading path once, so the viewport does not
perform a separate uncached loader request. Loading, ready, and safe error
copy update every visible status location. The study surface now follows the
approved Operator workstation direction: compact top bar, study context panel,
dominant vertical stage, read-only tool/workflow panel, responsive portrait
stacking, and persistent DICOM download/return actions. The existing protected
routes and authorization boundaries are unchanged.

Observed verification:

- Focused JavaScript, focused Operator/Image Gateway/localization PHPUnit,
  full PHPUnit (313 tests; 306 passed; 7 skipped), production build/static
  bundle check, formatter, and diff check: **PASS**.
- timeout 60s env TARGET=. vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --browser chrome --colors=never:
  **NOT PASS** — exited with status 124 after the timeout and produced no
  test output. No browser-rendering success is claimed from this run.
- The authorised user-led current-tab checklist remains required for final
  product confirmation.

## Current protected DICOM failure remediation — 2026-08-14

The authorised browser report exposed a second, server-side failure after the
viewer bundle began requesting the protected DICOM route. A missing private
object metadata read raised an uncaught JSON syntax exception and returned
HTTP 500. The private-object boundary now normalizes storage-read failures and
the Image Gateway returns its existing safe denial path; no private bytes,
storage credentials, or external object-store calls were used. A synthetic
missing-object feature regression passes. The actual local study still needs
the configured database and local private-object tree to be reset/re-uploaded
together before a real 200 DICOM response can be confirmed.

## Evidence report — Operator DICOM viewer and protected retrieval — 2026-08-14

### Scope

This report records the implementation, verification, and user-led browser
observations from the current-tab DICOM viewer task. It is sanitized: no
credentials, private object keys, NPZ/DICOM bytes, clinical metadata, or
external-service responses are included.

### Implemented

- Replaced the previous study-page flow with the current-tab, read-only
  workstation-style DICOM surface based on the approved local design
  references.
- Removed monitor-popup behavior and retained automatic VOI, zoom, pan,
  protected inline viewing, normal attachment download, and return navigation.
- Made Cornerstone load lazily through the browser-safe bundle path and added
  bounded bootstrap, protected-load, parsing, and rendering waits.
- Added safe Indonesian loading, ready, unavailable, and error states without
  exposing exception text or private storage details.
- Normalized private-object metadata/read failures so they no longer escape as
  HTTP 500; the existing protected denial path is used instead.
- Removed the HTML `download` attribute from the attachment link. Successful
  DICOM responses still use the server `Content-Disposition` filename, while
  an error response is no longer saved as `download.htm`.

### Automated evidence

All checks were run with `TARGET="."`:

- JavaScript viewer tests: **PASS** — 5/5.
- Focused Operator/Image Gateway/localization tests: **PASS** — 25/25,
  404 assertions.
- Full PHPUnit suite: **PASS** — 313 tests, 306 passed, 7 skipped.
- Production Vite build and DICOM browser bundle check: **PASS**.
- Pint formatter and `git diff --check`: **PASS**.
- The existing browser rehearsal was previously stopped after its 60-second
  timeout with no output; it is not reported as a pass.

### User-led browser evidence

- Before storage-failure normalization, the protected DICOM request returned
  HTTP 500 because a missing private-object metadata read raised a JSON syntax
  exception.
- After remediation and redeployment, both the protected inline DICOM route
  and the normal attachment route returned HTTP 403 for the previously opened
  study.
- The deployed process was confirmed to serve this repository with
  `MHCS_PRIVATE_OBJECT_DISK=local`, no configuration cache, and the supplied
  study record absent from the current database after redeployment. This
  explains why redeployment and browser refresh did not restore the old study.

### Result and remaining limitation

The browser/build failure and misleading HTML download behavior are addressed.
The current evidence does not demonstrate a real 200 DICOM response or a
rendered clinical image. The remaining issue is runtime data alignment: the
current database, local private-object tree, and study URL must come from the
same local capture run. A refresh cannot recreate missing state, and another
redeploy will not restore an old study unless its database and private-object
storage are restored together.

**Terminal assessment:** implementation verified; user-led real-study retrieval
remains **NOT VERIFIED** pending a matching local database/private-object set.
