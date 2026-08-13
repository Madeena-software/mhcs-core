# Local Operator-to-MPIPS DICOM rehearsal evidence

**Date:** 2026-08-13
**Target:** `.`
**Governing task:** `.agents/tasks/mvp-local-mpips-operator-rehearsal.md @ 71f78d79addcee302b66a1b59aa75431dc389ae8`
**Implementation baseline:** `d09d33e1990cffe6a446bfa0408ed3a427134554`
**Observed implementation revision:** `71f78d79addcee302b66a1b59aa75431dc389ae8` before this evidence update; no implementation files changed in this execution.
**Immutable implementation revision:** unavailable for this execution because the task forbids Git commits.
**Terminal state:** `REVIEW REQUIRED` — the authorized live rehearsal did not start because the approved local Grabber radiograph/gain pair was unavailable; Planner/Reviewer follow-up is required.

## Changed and verified files

Changed in this execution:

- `docs/mvp/evidence/mvp-local-mpips-operator-rehearsal.md` — updated with
  current revisions, observed checks, the blocked probe preflight, and the
  redacted terminal outcome.

Verified from the implementation baseline without modification:

- `README.md` — describes the existing native web, private S3, database queue,
  loopback MPIPS, asynchronous DICOM flow, and non-production boundary.
- `docs/mvp/local-core-walkthrough.md` — describes the bounded setup, dummy
  seed, `image-gateway` worker, approved local pair requirement, Operator
  checks, sanitised failure handling, and cleanup boundary.

## Verification commands and observed results

| Check | Result |
|---|---|
| `TARGET="." vendor/bin/phpunit` with the seeded Operator, named core flow, Image Gateway integration, and Member conformance tests | **PASS** — 123 tests reported; 117 passed and 6 skipped; 1,198 assertions. |
| `TARGET="." vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --browser chrome` | **PASS** — 2 tests, 24 assertions; fake MPIPS responses only. |
| `TARGET="." npm run build` | **PASS** — existing optional-font, browser-externalization, and chunk-size warnings only. |
| `TARGET="." vendor/bin/pint --test` | **PASS**. |
| `TARGET="." git diff --check` before this evidence update | **PASS**. |
| Local configuration preflight | **PASS** — required application/encryption, private-storage, loopback-MPIPS, disposable-local-database, and database-queue settings were present; values were not printed. |
| Approved local Grabber-pair availability preflight | **STOPPED** — the approved two-file non-clinical pair was unavailable in the local workspace; repository fixtures were not substituted. |
| Bounded generated capture-sized `PrivateObjectStore` probe | **NOT RUN** — the task requires the approved pair to be available before proceeding; no S3/MPIPS operation was attempted. |
| Fresh disposable MySQL migration/seed | **NOT RUN** — stopped before destructive setup under the task stop condition. |
| Native web server and `image-gateway` worker | **NOT STARTED**. |
| Local S3 → queue → loopback MPIPS rehearsal | **NOT RUN**. |

No command read, printed, or recorded environment values, credentials, bucket
or object identifiers, NPZ content, DICOM bytes, or patient data.

## Authorized local rehearsal

The rehearsal stopped before database reset, seed, capture submission, or
private-object persistence because the approved local Grabber radiograph/gain
pair was unavailable. The repository-owned synthetic fixtures were not used as
a substitute, and no direct MPIPS request, queue job, capture row, private
object, DICOM result, first-Operator viewer/download action, or second-Operator
worklist/viewer/download action occurred.

Cleanup is **NOT APPLICABLE** for this execution: no disposable database,
native process, S3 object, queue job, or application capture was created.

The local rehearsal remains separate from automated fake-based tests and is not
deployment, production, or release evidence.

## Known gaps and required follow-up

- The approved non-clinical local Grabber pair must be made available through
  the existing local location without copying, renaming, inspecting, or
  committing it.
- The bounded generated same-size private-object probe remains pending.
- The fresh disposable seed, native web/queue processes, queue completion,
  returned DICOM, and both authorized Operator view/download journeys remain
  unverified.
- An authorized commit is required before an immutable implementation revision
  can be returned; this task does not authorize committing, pushing, or creating
  a pull request.

## Redaction and disclosure confirmation

This report contains no secret values, environment values, credentials, bucket
names, object identifiers, patient data, raw NPZ path or filename, NPZ content,
or DICOM bytes. No raw NPZ or DICOM content was rendered, extracted, parsed, or
recorded. No production, server, bucket/IAM, deployment, release, Docker,
Compose, or 37-member import action was performed.
