# Local Operator-to-MPIPS DICOM rehearsal evidence

**Date:** 2026-08-13  
**Target:** `.`  
**Governing task:** `.agents/tasks/mvp-local-mpips-operator-rehearsal.md @ 3f9ee9de9ffd9715db947a328cacf03341b96621`  
**Implementation baseline:** `0f6f6e3552a4ace5a057e6415eac8057cd03dcee`  
**Implementation revision:** uncommitted working tree based on `3f9ee9d`; an immutable implementation revision is unavailable because the task forbids Git commits.  
**Terminal state:** `REVIEW REQUIRED` — local rehearsal stopped at the authorized S3 boundary; Planner/Reviewer follow-up is required.

## Changed files

- `README.md` — replaced the stale synthetic-bridge walkthrough with the
  existing private S3 → database queue → loopback MPIPS → DICOM flow.
- `docs/mvp/local-core-walkthrough.md` — added the bounded local setup,
  primary/second-Operator steps, queue outcome handling, and cleanup rules.
- `.env.example`, `config/mhcs.php`, and `app/Console/Commands/Serve.php` —
  replaced separate upload-size overrides with `MHCS_MAX_UPLOAD_MB`, applying
  100 MB per individual file and a derived 201 MB two-file request envelope.
- `app/Http/Controllers/Operator/PortalController.php` and the Member/Image
  Gateway upload services — applied the shared limit to questionnaire,
  informed-consent, KTP/KIA, profile-photo, and NPZ inputs.
- `tests/Unit/UploadLimitConfigurationTest.php` and focused existing tests —
  covered the shared configuration and updated the 100 MB questionnaire
  boundary.
- `docs/mvp/evidence/mvp-local-mpips-operator-rehearsal.md` — this redacted
  evidence report.

## Verification commands and observed results

| Check | Result |
|---|---|
| `TARGET="." vendor/bin/phpunit` with the seeded Operator, named core flow, Image Gateway integration, Member conformance, upload-limit, and deployment tests | **PASS** — 126 tests reported; 120 passed and 6 skipped; 1,271 assertions. |
| `TARGET="." vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --browser chrome` | **PASS** — 2 tests, 24 assertions; fake MPIPS responses only. |
| Upload configuration preflight | **PASS** — `MHCS_MAX_UPLOAD_MB` resolved to 100 MB per file, 104,857,600 bytes per file, and 210,763,776 bytes (201 MB) for the two-file request envelope. |
| `TARGET="." npm run build` | **PASS** — existing optional-font, browser-externalization, and chunk-size warnings only. |
| `TARGET="." vendor/bin/pint --test` | **PASS**. |
| `TARGET="." git diff --check` | **PASS**. |
| Local configuration preflight | **PASS** — disposable local MySQL, private S3 disk, loopback MPIPS default, database queue, and configured 390-second worker timeout; values were not printed. |
| Local pair availability preflight | **PASS** — two local NPZ inputs were present; names, path, bytes, metadata, and contents were not inspected or recorded. |
| Fresh `migrate:fresh --force` and `MvpCoreClinicSeeder` seed | **PASS** on the clean rerun with `memory_limit=512M`; dummy seed completed. |
| Final disposable database cleanup | **PASS** — schema reset and zero capture/object/queue residue observed. |

The destructive `migrate:fresh` command was preceded by the required warning
and was run only against the explicitly disposable local MySQL target.

## Authorized local rehearsal

Native processes were started with the Laravel web server and the existing
database worker consuming only `image-gateway` with the configured 390-second
timeout. The temporary harness selected the two existing local, non-clinical
Grabber inputs without exposing their names or contents and attempted one
capture submission through the existing Image Gateway service.

Observed outcome:

- Seed: **PASS**.
- Capture acceptance: **STOPPED** at private S3 object persistence. The PHP
  process remained in the S3 network operation for more than six minutes and
  was stopped after observing no capture rows, object rows, or queue jobs.
- MPIPS: **NOT REACHED**; no MPIPS request was made by the blocked attempt.
- Queue completion: **NOT REACHED**.
- Persisted returned DICOM: **NOT REACHED**.
- First Operator vertical read-only viewer and normal attachment download:
  **NOT REACHED**.
- Second authorized same-site/current-shift Operator results worklist,
  viewer, and normal attachment download: **NOT REACHED**.
- Cleanup: **PASS** for the disposable database; no database-tracked capture,
  object, or queue residue remained. Private-object cleanup cannot be
  independently confirmed for the interrupted pre-persistence network call,
  so the task stop condition remains active and no success is claimed.

The local rehearsal is therefore not deployment, production, or release
evidence. The affected boundary is the configured local private S3 upload of
the approved pair; no retry, alternate target, infrastructure change, or
secret access was attempted.

## Known gaps

- The live S3 → queue → MPIPS → DICOM path did not reach queue processing.
- The live first- and second-Operator viewer/download observations remain
  unverified; the required automated fake-based browser coverage remains green.
- An immutable implementation revision still requires an authorized commit;
  this task did not authorize committing, pushing, or creating a pull request.

## Redaction and disclosure confirmation

This report contains no secret values, environment values, credentials, bucket
names, object identifiers, patient data, raw NPZ path or filename, NPZ content,
or DICOM bytes. No raw NPZ or DICOM content was rendered, extracted, parsed, or
recorded. No production, server, bucket/IAM, deployment, release, Docker,
Compose, or 37-member import action was performed.
