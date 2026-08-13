# MVP evidence: queued capture status and worklist synchronisation

Date: 2026-08-13
Target: `TARGET="."`
Governing task: `.agents/tasks/operator-async-capture-status-and-worklist-sync.md @ 8afd3dedc9f7e4920d59beb9e94d2e480bd6bc9f`
Remediation baseline: `91304d969daa54fbcf42eb28f97d2f77d78d8265`
Execution base revision: `8afd3dedc9f7e4920d59beb9e94d2e480bd6bc9f` (remediation changes remain uncommitted; commit was not authorised)
Terminal state: `REVIEW REQUIRED`

## Observed remediation verification

- Browser regression red check on the reviewed implementation: **FAIL as
  expected** because `queued` still prevented `beforeunload`.
- `TARGET="." vendor/bin/phpunit tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php --colors=never` — **PASS**, 13 tests, 119 assertions.
- `TARGET="." vendor/bin/phpunit tests/Feature/Operator --colors=never` — **PASS**, 134 passed, 1 skipped, 1,366 assertions.
- Localization/shared checks — **PASS**, 11 tests, 180 assertions.
- `TARGET="." vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --colors=never` — **PASS**, 2 tests, 39 assertions.
- `TARGET="." vendor/bin/phpunit --colors=never` — **PASS**, 287 passed, 7 skipped, 4,392 assertions.
- `TARGET="." npm run build` — **PASS**; existing optional-font, browser-externalization, and chunk-size warnings remain.
- `TARGET="." vendor/bin/pint --test` — **PASS**.
- Indonesian JSON parse and `TARGET="." git diff --check` — **PASS**.

The browser coverage proves that native unload protection remains active while
the NPZ upload and until safe status is known, then releases immediately for
`queued` while polling remains possible and file controls remain disabled. The
changed warning copy states the upload/navigation boundary and safe continued
processing after acceptance.

All checks used local/fake-backed application behavior. No secrets, patient
data, object keys, checksums, NPZ/DICOM contents, live MPIPS/S3 calls,
deployment, service start, or data reset were used. This evidence does not
claim release or final implementation acceptance.
