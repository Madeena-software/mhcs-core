# MPIPS v1.2 and AWS Image Gateway Integration Evidence

**Date:** 2026-08-13  
**Target:** `.`  
**Governing task:** `.agents/tasks/mpips-aws-image-gateway-integration.md @ e9d606a70a7af6eb1d2df6b48811ad9c2be3a825`  
**Implementation revision:** `8cde93bd44ceed11ef46bc608a4da4b9f1102583`  
**Execution result:** `PASS — evidence complete; final protocol acceptance remains a Planner/Reviewer responsibility`

## Implementation scope

The implementation revision is the task's stated remediation baseline. No
implementation files were changed during this evidence run. The implementation
delta from the original execution baseline `2cb939f31e170eeb5fec0e7b1b58cf4d964591e0`
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

## Commands and observed results

| Check | Result |
|---|---|
| `TARGET="." composer validate --no-check-publish` | Passed; `composer.json` valid. |
| `TARGET="." composer audit --format=plain` | Passed; no security vulnerability advisories. |
| `TARGET="." composer show league/flysystem-aws-s3-v3 --format=json` | Locked adapter `3.35.2`. |
| Focused Image Gateway/localization/architecture PHPUnit suite | Passed; 17 tests, 1,925 assertions. |
| `TARGET="." vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --browser chrome` | Passed; 2 tests, 24 assertions. Both submitting and authorised second-Operator journeys observed the vertical read-only viewer and normal DICOM download. |
| Fresh disposable SQLite `migrate:fresh` | Passed through all migrations, including the Image Gateway MPIPS migration. |
| `TARGET="." bash deployment/verify-mysql.sh` | Passed against disposable MySQL 8.4: fresh migration; all representatives; Member 32 tests/298 assertions; Integration 8 tests/49 assertions; full PHP 288 tests/4,410 assertions; and all guarded portability rollback/reapplication checks. The container cleanup completed. |
| `TARGET="." vendor/bin/phpunit` | Passed; 281 tests passed, 7 MySQL-only tests skipped, 4,350 assertions. |
| `TARGET="." npm run build` | Passed. Existing optional-font and chunk-size warnings only. |
| Changed-file `TARGET="." vendor/bin/pint --test ...` | Passed. |
| `TARGET="." vendor/bin/pint --test` | Failed only on unchanged `app/Console/Kernel.php` (`concat_space`, `ordered_imports`); no unrelated file was modified. |
| `TARGET="." git diff --check 8cde93bd44ceed11ef46bc608a4da4b9f1102583 HEAD` and working-tree diff check | Passed; no whitespace errors. |
| `TARGET="." vendor/bin/phpunit tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php` | Passed; 9 tests, 87 assertions. |

## Probes

- AWS private-object probe: **PASS**. A unique non-clinical synthetic value was
  written through `PrivateObjectStore`, read back through a valid grant with
  exact-byte equality, and deleted. Ciphertext and metadata cleanup: **PASS**.
- Loopback MPIPS health check: **HTTP 200**.
- Loopback MPIPS research-pair capture probe: **PASS**. The existing MHCS
  upload, encrypted-object, queue-worker, MPIPS client, DICOM persistence, and
  authorised Operator routes were exercised with the supplied local research
  NPZ pair. MPIPS returned HTTP 200 with `application/dicom`; MHCS validated the
  Part-10 marker, parsed the returned patient identity and DICOM UID elements,
  persisted one study, and recorded the response identifiers. A completed
  replay made no second MPIPS request, and an authorised same-site/current-shift
  second Operator viewed and downloaded the study. Disposable database and
  private-object cleanup: **PASS**. The temporary probe test was deleted after
  the run. Its first CLI attempt hit the default 128 MiB memory ceiling while
  constructing the supplied 69 MiB upload; rerunning the same probe with
  `memory_limit=512M` passed without repository implementation changes.

Per the task stop condition, no public, production, unknown, or alternate MPIPS
target was used.

## Known gaps and terminal state

- Repository-wide Pint remains blocked by the pre-existing, unchanged
  `app/Console/Kernel.php`; all task-changed PHP files pass the formatter.
- The evidence is local integration evidence only. It is not deployment,
  production, release, bucket/IAM, or infrastructure evidence.

No secret, AWS value, MPIPS API key, bucket or object identifier, endpoint,
patient data, raw NPZ, or DICOM bytes were disclosed in this report or the
observed command output.
