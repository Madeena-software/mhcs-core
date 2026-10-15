# Radiographer Workspace — R8 remediation — Review Required

Date: 2026-10-15. Governing T5: `.agents/tasks/admin-issued-radiographer-workspace.md`
at `25d396e576ee513bee1a7e2aa2db57a65afdd666`. Reviewed implementation:
`702e6303cad5c6c9f7319e44d9989b3890c83ab2`. Original implementation baseline:
`f8f0bb9e2e0cd12d1b797cf6a6689f26336274ba`. Branch:
`feat/radiographer-workspace`. The human's “Execute R8 Remediation” instruction
authorizes these scoped corrections, synthetic verification, ordinary commit and
push. This report supplements and corrects the disclosure claims in the earlier
execution evidence; it does not republish T5 or establish A9 acceptance.

## Changes and security boundary

**R8-01:** Gateway internal parsing now records `radiographer-workspace.dicom-inspected`.
HTML inspection, metadata saves, handoffs and direct owner reads no longer claim
HTTP image delivery. Required owner read/inspection audits remain transactional.
Only the DICOM response emission gate records
`radiographer-workspace.dicom-delivery-authorized`; other worker responses record
`radiographer-workspace.response-disclosure-authorized`. These names describe the
observed server operation, not successful client receipt or rendering. HEAD does
not record image delivery. No historical audit events are rewritten.

**R8-02:** The controller wraps prepared protected HTML, DICOM, mutation replies
and management responses with one response emission boundary. Immediately before
headers/content, that boundary freshly checks persisted account, assignment
generation, expiry, professional/facility eligibility and exact-case authority,
then durably commits the required audit. Management replies revalidate their
existing owner-managed capability/facility boundary. Failures replace content and
headers with a neutral denial/unavailable response. HEAD remains bodyless even
when denied late. Mutations already committed before a later revocation remain
committed; their protected response is withheld rather than falsely rolled back.

The final authorization/audit commit is the disclosure-start ordering point.
Revocation committed before that gate denies prepared output. Network writes
occur after the transaction releases its locks; no transaction spans client I/O.
A subsequent revocation cannot retract already-authorized output in flight, and
server audit cannot prove client receipt. Physical-byte synchronization would
require a different transport coordination contract. The deterministic tests
prove the stated ordering boundary, not that stronger guarantee.

Verification also reproduced Node `EMFILE` when the 32-descriptor parser process
inherits request/DB/test handles. Its finite descriptor ceiling is calibrated to
64, with a failing-before/passing-after test that opens 16 inherited handles.
Memory, CPU, wall time, filesystem permissions, network denial, byte/output bounds
and error redaction remain enforced. Unit/integration fixtures freeze both test
time and the injected Clock to avoid observed disposable-host backward clock
adjustments; production clocks and validity checks are unchanged.

## Changed files

- `app/Http/Controllers/Radiographer/WorkspaceController.php`
- `app/Http/Controllers/Radiographer/WorkspaceResponse.php` (new)
- `app/Modules/ImageGateway/Application/Services/RadiographerStudyRead.php`
- `app/Modules/ImageGateway/Infrastructure/Dicom/BoundedDicomVerifier.php`
- `tests/Feature/Radiographer/RadiographerDisclosureTest.php` (new)
- `tests/Feature/Radiographer/RadiographerIntegrityTest.php`
- `tests/Feature/Radiographer/RadiographerSecurityTest.php`
- `tests/Feature/Radiographer/RadiographerWorkspaceTest.php`
- `tests/ImageGateway/BoundedDicomVerifierTest.php`
- `tests/Integration/RadiographerConcurrencyTest.php`
- `tests/Support/radiographer-browser-bootstrap.php`
- This evidence report.

No dependency, migration, product contract, original DICOM, production packaging
or unrelated application feature changed. Governing task SHA-256 remains
`fc9029853006dce5b38b7b3dd98fc204986e3ea5aa8ab60b93f71dadd50ba68f`.

## Observed verification

Local disposable evidence only: application image PHP 8.4.26, Node 24.21.0,
installed/locked dicom-parser 1.8.21 and MySQL 8.4.8. The existing development
vendor tree and production autoload mappings were reused with `/tmp/run-suite.php`,
as in the previous execution. No dependency install or external patient/AI call.
Tracked repository sources and `.github` workflows were copied into the test
containers: image packaging intentionally omits some test inputs. An initial run
without those workflow files is invalid for baseline parity and is superseded.

- **Before correction:** all seven original disclosure regressions failed:
  inspection produced five false delivery events; prepared HTML/image/JSON survived
  revocation; emission ignored audit append/commit failure. The inherited-handle
  test separately failed at 32 descriptors and passed at 64.
- **Required combined security/HTTP/DICOM/regression selection:** 262 tests,
  6,251 assertions, zero failures/errors/skips. Selection below.
- **MySQL:** 11 tests, 151 assertions, zero failures/errors/skips, using only
  disposable `mhcs_rad_synthetic` and its isolated private-object disk. Six new
  cases prepare HTML/DICOM, synchronize an independent child connection, wait for
  committed revoke/same-actor reassignment/account suspension, then invoke actual
  response `send(false)`. Each denies without patient/image bytes or a disclosure
  success event. A success control observes one committed delivery authorization
  and transaction level zero during output. Existing duplicate handoff, waiting
  revocation/reassignment and finalization/reopen races also pass.
- **Browser:** three rendered synthetic direct/converted/supplied-UID journeys,
  six handoffs, all three QC states, three unknown-status reopen denials, three
  verified synthetic reopens, two revocations and one expiry. Pixel variation 255
  in each journey. Existing browser harness and synthetic-only HTTP bootstrap.
- **JavaScript:** 17/17 viewer tests passed, zero skips. `npm run build` passed
  including DICOM bundle checks; existing bundle-size warning remains.
- **Formatting:** `vendor/bin/pint --dirty --test` and `git diff --check` passed.
- **Read-only technical review:** confirmed a late-denied HEAD body regression;
  corrected with request preparation preservation and a regression assertion.
  No other concrete bypass was reported. This is supporting review, not A9.

Required selection:

```sh
php /tmp/run-suite.php tests/Feature/Radiographer \
  tests/ImageGateway/BoundedDicomVerifierTest.php \
  tests/Feature/Admin/AdminCentralizedStudyWorklistTest.php \
  tests/Feature/Admin/AdminFacilitySessionOperationsTest.php \
  tests/Feature/Operator/OperatorFieldOperationsSlice3Test.php \
  tests/Feature/Operator/OperatorPortraitDicomViewerTest.php \
  tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php \
  tests/Feature/Operator/OperatorAiPacsIntegrationTest.php \
  tests/Architecture tests/Security
MHCS_RAD_MYSQL_TESTS=1 php /tmp/run-suite.php tests/Integration/RadiographerConcurrencyTest.php
RAD_BROWSER_CONTAINER=mhcs-rad-browser-r8 node tests/Browser/radiographer-workspace.mjs
node --test tests/JavaScript/operator-dicom-viewer.test.mjs
npm run build
```

## R8-03 full-suite classification

Identical disposable application-image capability/configuration, complete tracked
test inputs, fresh processes and separate storage were used for reviewed baseline
and remediation. Both used `php /tmp/run-suite.php --log-junit <temporary-path>`.
Parsed JUnit test identities were compared, rather than comparing only totals.

| Revision | Tests | Assertions | Errors | Failures | Skips |
| --- | ---: | ---: | ---: | ---: | ---: |
| Reviewed `702e6303cad5c6c9f7319e44d9989b3890c83ab2` | 883 | 13,220 | 3 | 9 | 11 |
| R8 remediation | 900 | 13,295 | 3 | 9 | 18 |

**Exact failing-test set parity: no added or removed failing tests.** Two
AdminMemberRegistrationTest WebP fixture cases error because the image lacks
`imagewebp`. AiPacsPlaywrightReportDownloaderTest has one error and nine failures
because `python3` is absent. Seven additional default skips are the new opt-in
MySQL cases, which pass in the explicit run above; four existing MySQL skips and
seven unrelated existing skips remain. No full-green claim is made.

An additional clean exported-source diagnostic with host PHP 8.4.1, working GD
WebP and Python ran the two affected suites: 77 tests, 648 assertions, one failure
because Python lacks Pillow. This further isolates missing capabilities, but does
not replace the exact baseline-parity evidence. No unrelated application or
production dependency changes were made to mask these failures.

Laravel's documented response lifecycle sends the returned response after the
middleware chain; the installed Symfony Response implementation confirms the
headers/content order used by the gate. See the current official
[Laravel lifecycle documentation](https://laravel.com/framework/docs/13.x/lifecycle).
Actual source and tests establish the claims above; repository-intelligence
indexes were stale and were not treated as implementation evidence.

## Delivery and limitations

Terminal state: **Review Required**. The ordinary remediation commit containing
this report is the review candidate; its full SHA and observed remote equality
are supplied in the consolidated execution handoff. Independent A9 acceptance
is pending. CI was not observed. Full suite retains the classified baseline
environment failures. Audit delivery means durable authorization/start, not
confirmed receipt; output already authorized/in flight cannot be recalled.

No merge, deployment, production mutation or real patient access occurred.
Existing real clinical-source, operational audit-policy and G10 obligations remain
as recorded in the earlier execution report; this remediation grants none of them.
