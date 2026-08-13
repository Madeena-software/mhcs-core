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
  full PHPUnit (312 tests; 305 passed; 7 skipped), production build/static
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
