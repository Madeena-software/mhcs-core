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
