# Local Operator-to-MPIPS DICOM rehearsal evidence

**Date:** 2026-08-13
**Target:** `.`
**Governing task:** `.agents/tasks/private-object-concurrent-capture-transport.md`
**Task revision:** `10ac3604fd57e647b6d500801f74387521033237`
**Implementation baseline:** `a2ef4139eae9ac088cdde272e3946f39f6f439a2`
**Terminal state:** `REVIEW REQUIRED`

## Sanitised status

The approved disposable local rehearsal was started after automated checks.
Seeded login and site selection passed. The browser then stopped in the
existing seeded identity/consent workflow before capture; no NPZ capture, S3
write, MPIPS request, queue processing, DICOM persistence, viewer, or download
was reached.

The disposable database was reset afterward. Private-object cleanup cannot be
confirmed because the rehearsal stopped before application-owned object rows
could be enumerated and deleted through the store. Per the task stop condition,
the configured external-storage boundary is returned to planning/operations;
no bucket listing, object identifier, endpoint, credential, or secret was read.

The three unapproved rehearsal scripts named by the task were removed. No
probe harness, presigned URL flow, secret, endpoint, bucket/object identifier,
patient data, NPZ bytes, or DICOM bytes was added to the repository or report.

## Automated verification

The following fake-backed or local repository checks were run against `TARGET="."`:

| Check | Result |
|---|---|
| Remediation storage, Image Gateway, and upload-limit tests | **PASS** — 38 tests; 229 assertions |
| `vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --no-progress` | **PASS** — 2 tests; 25 assertions |
| Full PHPUnit suite | **PASS** — 293 tests; 286 passed; 7 skipped; 4,387 assertions |
| `npm run build` | **PASS** — existing optional-font, browser-externalization, and chunk-size warnings only |
| `vendor/bin/pint --test` | **PASS** |
| `git diff --check` | **PASS** |

These automated checks are not deployment, production, or release evidence.

## Rehearsal boundary

The normal first submission, component-only retry, authenticated DICOM viewer,
and authenticated DICOM download remain unverified in the live rehearsal. The
Reviewer must decide acceptance and the required external-storage cleanup
follow-up under the task's explicit side-effect boundary.

No commit, push, pull request, deployment, release, production mutation,
bucket/IAM change, credential delivery, or real-member import was performed.
