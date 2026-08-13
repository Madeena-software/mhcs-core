# Local Operator-to-MPIPS DICOM rehearsal evidence

**Date:** 2026-08-13
**Target:** `.`
**Governing task:** `.agents/tasks/mvp-local-mpips-operator-rehearsal.md @ 116d32a15138d00f6c28949bfc9597c168704338`
**Implementation baseline:** `d09d33e1990cffe6a446bfa0408ed3a427134554`
**Observed implementation revision:** `116d32a15138d00f6c28949bfc9597c168704338` before this evidence update; no application or test implementation files changed.
**Immutable implementation revision for this evidence update:** not created; the task authorizes repository changes but does not authorize a Git commit.
**Terminal state:** `REVIEW REQUIRED` — the bounded capture-sized private-object probe timed out, so the task stop condition prevented database reset, capture submission, and live Operator rehearsal.

## Changed and verified files

Changed in this execution:

- `docs/mvp/evidence/mvp-local-mpips-operator-rehearsal.md` — updated with
  the exact task revision, current observed revision, verification results,
  bounded probe stop, and redacted terminal outcome.
- `research/scratch/test-s3-integration.php` — reduced to a bounded,
  redacted CRUD probe using the repository root `.env`; it does not create a
  bucket or disclose endpoint, bucket, credentials, object keys, or payloads.

Verified without modification:

- `README.md` — describes the existing native web, private S3, database queue,
  loopback MPIPS, asynchronous DICOM flow, and non-production boundary.
- `docs/mvp/local-core-walkthrough.md` — describes the bounded setup, dummy
  seed, `image-gateway` worker, approved local pair requirement, Operator
  checks, sanitised failure handling, and cleanup boundary.

## Verification commands and observed results

| Check | Result |
|---|---|
| `TARGET="." vendor/bin/phpunit tests/Feature/Operator/MvpCoreClinicSeederTest.php tests/Feature/Operator/Mvp04bIdentityVerificationTest.php tests/Feature/Operator/Mvp04cPaperConsentConfirmationTest.php tests/Feature/Operator/Mvp04dVerifiedCheckInTicketIssueTest.php tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php tests/Feature/Operator/Mvp04fAtomicBasicExaminationClaimTest.php tests/Feature/Operator/Mvp04gPrivateBasicExaminationCallTest.php tests/Feature/Operator/Mvp04hPrivateBasicExaminationStartTest.php tests/Feature/Operator/Mvp04jPrivateVitalSignsCaptureTest.php tests/Feature/Operator/Mvp04kBasicExaminationCompletionTest.php tests/Feature/Operator/Mvp04lAtomicXrayClaimTest.php tests/Feature/Operator/Mvp04mPrivateXrayCallTest.php tests/Feature/Operator/Mvp04pPublicQueueDisplayTest.php tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php tests/Integration/MemberDatabaseConformanceTest.php` | **PASS** — 123 tests; 117 passed, 6 skipped; 1,198 assertions. |
| `TARGET="." timeout --signal=TERM 180s vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --browser chrome` | **PASS** — 2 tests; 24 assertions; fake MPIPS responses only. |
| `TARGET="." npm run build` | **PASS** — existing optional-font, browser-externalization, and chunk-size warnings only. |
| `TARGET="." vendor/bin/pint --test` | **PASS**. |
| `TARGET="." git diff --check` | **PASS**. |
| Local configuration preflight | **PASS** — required application/encryption, private-storage, loopback-MPIPS, disposable-local-database, and database-queue settings were present; values were not printed. |
| Approved local Grabber-pair availability preflight | **PASS** — the approved two-file non-clinical pair was available locally; its path, names, bytes, and metadata were not recorded. |
| `TARGET="." timeout --signal=TERM 90s php research/scratch/test-s3-integration.php` | **PASS** — small-object SDK write/read/delete; `S3_PROBE=PASS CLEANUP=PASS`. |
| Bounded Laravel `Storage::disk('s3')` small-object probe | **PASS** — application-configured disk write/read/delete; `LARAVEL_S3_PROBE=PASS CLEANUP=PASS`. |
| Bounded generated capture-sized `PrivateObjectStore` probe with default PHP memory | **STOPPED** before storage — the temporary harness exceeded its 128 MiB memory limit; no object was returned. |
| Bounded generated capture-sized `PrivateObjectStore` probe with `memory_limit=512M` and a 180-second command bound | **STOPPED** — the existing private-storage operation did not complete within the bound; no write/read/delete result was observable. |
| Fresh disposable MySQL migration/seed | **NOT RUN** — prohibited after the storage-probe stop; no destructive database command was issued. |
| Native web server and `image-gateway` worker | **NOT STARTED**. |
| Local S3 → queue → loopback MPIPS rehearsal | **NOT RUN**. |

The probe used only a generated non-clinical byte string through the existing
`PrivateObjectStore`; it did not read, parse, hash, render, or disclose the
approved local NPZ pair. The command was bounded, but its outer timeout ended
the process before the application-level `finally` block could confirm cleanup.
The state of any remote object created before the timeout is therefore not
confirmed; no bucket listing, object identifier, service log, or secret was
read to investigate it.

## Authorized local rehearsal

The rehearsal stopped before database reset, seed, capture submission, queue
processing, private DICOM persistence, or either Operator result interaction.
Consequently there is no observed dummy-seed result, queue outcome, returned
DICOM, primary-Operator viewer/download result, second-Operator worklist/
viewer/download result, or rehearsal cleanup result to report.

Rehearsal database and application-created private-object cleanup are **NOT
APPLICABLE** because the rehearsal was not started. Probe cleanup is **NOT
CONFIRMED** because the bounded storage operation was terminated before its
`finally` cleanup status could be observed. The local rehearsal remains
separate from automated fake-based tests and is not deployment, production, or
release evidence.

## Known gaps and required follow-up

- The capture-sized private-storage boundary must be repaired or otherwise
  made demonstrably bounded before this rehearsal can proceed.
- The fresh disposable seed, native web/queue processes, queue completion,
  returned DICOM, and both authorized Operator view/download journeys remain
  unverified.
- Cleanup of any remote object possibly created by the timed-out probe is not
  confirmed. Do not investigate by listing storage or reading identifiers;
  return this boundary to planning/operations with the configured storage
  owner.
- An authorized commit is required before an immutable implementation revision
  containing this evidence update can be returned; this task does not
  authorize committing, pushing, or creating a pull request.

## Redaction and disclosure confirmation

This report contains no secret values, environment values, credentials, bucket
names, object identifiers, patient data, raw NPZ path or filename, NPZ content,
or DICOM bytes. No raw NPZ or DICOM content was rendered, extracted, parsed,
or recorded. No production, server, bucket/IAM, deployment, release, Docker,
Compose, or 37-member import action was performed.
