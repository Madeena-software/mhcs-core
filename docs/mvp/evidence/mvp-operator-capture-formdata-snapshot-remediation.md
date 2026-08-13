# MVP evidence: Operator capture FormData snapshot remediation

Date: 2026-08-13
Target: `TARGET="."`
Governing task: `.agents/tasks/operator-capture-formdata-snapshot-remediation.md @ a1ba265e0abf6294daa521835529c7f2b19633c8`
Implementation baseline: `552759acc3c81e8eb6136a2c33c48df91852b796`
Execution state: bounded working-tree changes; commit was not authorised
Terminal state: `REVIEW REQUIRED`

## Red-green browser evidence

- The regression assertion was added before the view change and failed on the
  baseline: `false is true` at the captured-body assertion.
- After the minimal view correction, the same browser command passed: 2 tests,
  40 assertions.
- The fake XHR captured the exact `send` body. The assertion verifies native
  `FormData`, `File` values for `radiograph_npz` and `gain_npz`, the CSRF field,
  and `submission_id` after both file inputs were disabled.
- The existing progress, disabled-control, in-flight unload, queued-status
  unload-release, and polling checks remain in the same browser test.

## Required checks

- `TARGET="." vendor/bin/phpunit tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php --colors=never` — **PASS**, 13 tests and 119 assertions.
- `TARGET="." vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --colors=never` — **PASS**, 2 tests and 40 assertions.
- `TARGET="." vendor/bin/phpunit --colors=never` — **PASS**, 287 passed, 7 skipped, and 4,392 assertions.
- `TARGET="." npm run build` — **PASS**; existing optional-font, browser-externalization, and chunk-size warnings remain non-fatal.
- `TARGET="." vendor/bin/pint --test` — **PASS**.
- `TARGET="." git diff --check` — **PASS**.

## Bounded change and handoff

The capture page constructs one `FormData(form)` snapshot while controls are
enabled, then preserves the existing lock, progress, handlers, and single XHR
send. No feature assertion was added because the browser test proves the
multipart body boundary and the existing feature test already covers paired
source persistence and queueing.

The result contains no credentials, private object keys, checksums, NPZ/DICOM
content, or external responses. It does not claim live NPZ, MPIPS, DICOM,
S3, deployment, release, or second-Operator success. After Planner/Reviewer
acceptance, the user must repeat the local Operator-to-DICOM journey manually.

## Planner/Reviewer acceptance

**Date:** 2026-08-13
**Verdict:** `ACCEPTED`
**Governing task:** `.agents/tasks/operator-capture-formdata-snapshot-remediation.md @ a1ba265e0abf6294daa521835529c7f2b19633c8`
**Implementation baseline:** `552759acc3c81e8eb6136a2c33c48df91852b796`
**Accepted implementation revision:** `79260280cb8a234f6893fa39cacfa60cd162c89a`

The review confirmed one native `FormData` snapshot is created before the
existing control lock and is the same object passed to the existing XHR. The
browser regression assigns synthetic files, captures that actual send body, and
proves both NPZ fields, CSRF, and `submission_id` remain present after inputs
are disabled. Existing upload progress, duplicate prevention, in-flight unload
protection, queued/processing unload release, and polling behavior remain
covered.

Independent Reviewer verification on this revision:

- Image Gateway capture feature test: **PASS**, 13 tests and 119 assertions.
- Browser remediation command: **PASS** (exit status 0); Executor evidence
  records 2 tests and 40 assertions.
- Full PHPUnit suite, run separately: **PASS**, 287 passed, 7 skipped, and
  4,392 assertions.
- Frontend build, formatter, and diff check: **PASS**. Existing optional-font,
  Cornerstone browser-externalisation, and chunk-size build warnings remain
  non-blocking.

The accepted scope is only browser multipart-payload preservation. It makes no
claim about live NPZ submission, MPIPS, returned DICOM, viewer, download, or
second-Operator access. The user may now repeat the existing local manual
Operator-to-DICOM journey.
