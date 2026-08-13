# MVP evidence: queued capture status and worklist synchronisation

Date: 2026-08-13
Scope: `TARGET="."`, local fake-backed verification only.

## Observed verification

- `TARGET="." vendor/bin/phpunit tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php --colors=never` — 13 passed, 119 assertions.
- `TARGET="." vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --colors=never` — 2 passed, 34 assertions.
- Operator, localization, and Image Gateway regression group — 141 tests, 140 passed, 1 skipped, 1,528 assertions.
- `TARGET="." vendor/bin/phpunit --colors=never` — 294 tests, 287 passed, 7 skipped, 4,392 assertions.
- `TARGET="." npm run build` — passed.
- `TARGET="." vendor/bin/pint --test` — passed.
- `php -r 'json_decode(file_get_contents("lang/id.json"), true, 512, JSON_THROW_ON_ERROR);'` and `git diff --check` — passed.

The tests observe source persistence without a web-request MPIPS call, one queued
worker dispatch after durable acceptance, worker-only fake MPIPS conversion,
component retry/checksum protection, safe status authorization, native browser
progress/disable/poll behavior, Indonesian copy, and four non-mutating worklist
refresh markers. No secrets, patient data, object keys, checksums, NPZ/DICOM
contents, live MPIPS/S3 calls, deployment, service start, or data reset were
used.

## Terminal state

`REVIEW REQUIRED`. This evidence does not claim local deployment, live
MPIPS/S3 conversion, release, or acceptance.
