# MPIPS v1.2 and AWS Image Gateway Integration Evidence

**Date:** 2026-08-13
**Target:** `.`
**Governing task:** `.agents/tasks/mpips-aws-image-gateway-integration.md @ 31d1ce5dc0196ff15007f2468216e9c06e84485b`
**Implementation revision:** `8c6fd1a9c49011c44d61f78f4c04be00b9ddfc1` (runtime implementation baseline)
**Verification revision:** `31d1ce5dc0196ff15007f2468216e9c06e84485b` plus the uncommitted round-four evidence, ignore, and formatter changes
**Execution result:** `REVIEW REQUIRED — local evidence complete; final protocol acceptance remains a Planner/Reviewer responsibility`

## Implementation scope

The runtime implementation remains the task's round-four remediation baseline;
no production Image Gateway behavior was changed during this evidence run. The
implementation delta from the original execution baseline
`2cb939f31e170eeb5fec0e7b1b58cf4d964591e0`
contains:

- `.env.example`
- `app/Http/Controllers/Operator/ImageGatewayController.php`
- `app/Modules/ImageGateway/Application/Jobs/ProcessCaptureSet.php`
- `app/Modules/ImageGateway/Application/Services/ImageGatewayCaptureService.php`
- `app/Modules/ImageGateway/Infrastructure/MpipsClient.php`
- `app/Shared/Storage/EncryptedLocalObjectStore.php`
- `composer.json`, `composer.lock`, `config/filesystems.php`, `config/mhcs.php`,
  `config/queue.php`, and `phpunit.xml`
- `database/migrations/2026_08_12_000001_update_image_gateway_for_mpips.php`
- `lang/id.json`
- `resources/js/operator-dicom-viewer.js`
- `resources/views/operator/study-results.blade.php`
- `resources/views/operator/study.blade.php`
- `resources/views/operator/xray-capture.blade.php`
- `resources/views/operator/xray-readiness-worklist.blade.php`
- `tests/Architecture/FoundationArchitectureTest.php`
- `tests/Browser/Mvp14OperatorDicomRehearsalTest.php`
- `tests/Feature/FoundationFeatureTest.php`
- `tests/Feature/Localization/MvpApplicationIndonesianUiLocalizationTest.php`
- `tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php`
- deleted `tests/Feature/Operator/Mvp14SyntheticCaptureGatewayTest.php`

Round-four remediation files changed in this execution:

- `.gitignore` — narrowed the local NPZ ignore to `research/**/*.npz`.
- `app/Console/Kernel.php` — Pint-only import and concatenation formatting.
- `docs/mvp/evidence/mpips-aws-image-gateway-integration.md` — corrected
  observed evidence and verification results.

## Commands and observed results

| Check | Result |
|---|---|
| `TARGET="." composer validate --no-check-publish` | Passed; `composer.json` valid. |
| `TARGET="." composer install --no-interaction --prefer-dist` | Passed; lock file verified and nothing to install, update, or remove. |
| `TARGET="." composer audit --format=plain` | Initial run could not reach Packagist because DNS resolution failed; no advisory result was claimed from that run. |
| `TARGET="." composer audit --locked --format=plain` | Passed; no security vulnerability advisories. |
| `TARGET="." composer show league/flysystem-aws-s3-v3 --format=json` | Locked adapter `3.35.2`. |
| `TARGET="." vendor/bin/phpunit tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php tests/Feature/Localization/MvpApplicationIndonesianUiLocalizationTest.php tests/Architecture/FoundationArchitectureTest.php` | Passed; 17 tests, 1,925 assertions. |
| `TARGET="." vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --browser chrome` | Passed; 2 tests, 24 assertions. Both submitting and authorised second-Operator journeys observed the vertical read-only viewer and normal DICOM download. |
| `mkdir -p storage/framework/testing && DB_CONNECTION=sqlite DB_DATABASE=storage/framework/testing/mpips-task-migrations.sqlite DB_URL= CACHE_STORE=array QUEUE_CONNECTION=sync SESSION_DRIVER=array APP_ENV=testing APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan migrate:fresh --force` | Passed through all migrations, including the Image Gateway MPIPS migration. |
| `TARGET="." bash deployment/verify-mysql.sh` | Passed against disposable MySQL 8.4: fresh migration; all representatives; Member 32 tests/298 assertions; Integration 8 tests/49 assertions; full PHP 288 tests/4,410 assertions; and all guarded portability rollback/reapplication checks. The container cleanup completed. |
| `TARGET="." vendor/bin/phpunit` | Passed; 281 tests passed, 7 MySQL-only tests skipped, 4,350 assertions. |
| `TARGET="." npm run build` | Passed. Existing optional-font and chunk-size warnings only. |
| `TARGET="." vendor/bin/pint --test` | Passed after Pint's mechanical formatting of `app/Console/Kernel.php`; no executable behavior changed. |
| `TARGET="." git diff --check` | Passed; no whitespace errors. |
| `TARGET="." vendor/bin/phpunit tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php` | Passed; 9 tests, 87 assertions. |

## Probes

- AWS private-object probe: **PASS**. A unique non-clinical synthetic value was
  written through `PrivateObjectStore`, read back through a valid grant with
  exact-byte equality, and deleted. Ciphertext and metadata cleanup: **PASS**.
- Exact AWS probe command:

  ```text
  TARGET="." php artisan tinker --execute='$disk = config("mhcs.private_object_disk"); if ($disk !== "s3") { throw new RuntimeException("configured private object disk is not s3"); } $context = new \App\Shared\Context\AuthenticatedContext(actorId: \App\Shared\Identity\LocalId::fromString((string) \Illuminate\Support\Str::uuid()), operationId: \App\Shared\Context\CorrelationId::random(), roles: ["operator"], siteId: \App\Shared\Identity\LocalId::fromString((string) \Illuminate\Support\Str::uuid()), purpose: "image-gateway.capture.submit"); $contents = "mhcs-private-object-probe-".(string) \Illuminate\Support\Str::uuid(); $store = app(\App\Shared\Storage\PrivateObjectStore::class); $object = null; try { $object = $store->put($contents, $context, "image-gateway.capture.submit"); $grant = $store->grant($object, $context, "operator-study", "image-gateway.capture.submit", new \DateTimeImmutable("+5 minutes")); $read = $store->get($grant, $context, "operator-study", "image-gateway.capture.submit"); if (!hash_equals($contents, $read)) { throw new RuntimeException("private object bytes did not round-trip"); } echo "AWS probe: PASS".PHP_EOL; } finally { if ($object !== null) { $store->delete($object); } echo "cleanup: PASS".PHP_EOL; }'
  ```
- Loopback MPIPS health check: **HTTP 200**.
- Loopback MPIPS research-pair capture probe: **PASS**. The exact executed
  command was:

  ```text
  TARGET="." php -d memory_limit=512M vendor/bin/pest tests/Browser/Mvp14LocalMpipsProbeTest.php --filter='approved local MPIPS conversion probe'
  ```

  It ran with `memory_limit=512M` against configured loopback MPIPS and the
  CTO-approved local research pair. MPIPS returned HTTP 200 with
  `application/dicom`; MHCS validated the non-empty Part-10 marker and valid
  UUID response identifiers, derived the expected Study/Series/SOP Instance
  UIDs from the MPIPS job ID, persisted one study, and recorded the response
  identifiers. The probe passed with 1 test and 19 assertions. A completed
  replay made no second MPIPS request, and an authorised same-site/current-shift
  second Operator viewed and downloaded the study. Disposable database and
  private-object cleanup: **PASS**. The temporary probe test was deleted after
  the run. No patient identity or DICOM dataset fields were parsed by MHCS.

The first temporary probe variant added browser automation after the direct
conversion path and failed with `ERR_TOO_MANY_REDIRECTS` after 5 application
assertions. It was a probe-harness isolation failure; the final disposable
probe above passed after removing that redundant browser layer. No production
implementation change was made for it.

Per the task stop condition, no public, production, unknown, or alternate MPIPS
target was used.

## Known gaps and terminal state

- The evidence is local integration evidence only. It is not deployment,
  production, release, bucket/IAM, or infrastructure evidence.

No secret, AWS value, MPIPS API key, bucket or object identifier, endpoint,
patient data, raw NPZ, or DICOM bytes were disclosed in this report or the
observed command output.
