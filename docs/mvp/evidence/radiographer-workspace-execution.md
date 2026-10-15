# Radiographer Workspace — resumed implementation evidence

Date: 2026-10-15. Governing task:
`.agents/tasks/admin-issued-radiographer-workspace.md` at
`25d396e576ee513bee1a7e2aa2db57a65afdd666`. Application baseline:
`f8f0bb9e2e0cd12d1b797cf6a6689f26336274ba`. Resume revision:
`4f5c7488735f051d6b4a77cebb2a13e039168b6d`. Authorized branch:
`feat/radiographer-workspace` only. The governing task remains byte-identical
(SHA-256 `fc9029853006dce5b38b7b3dd98fc204986e3ea5aa8ab60b93f71dadd50ba68f`).

## Bounded decision and execution authority

The Product Owner's “MHCS — Resume Radiographer Development After Clinical Guard
Stop” accepts checkpoint 0 and approves the minimum Doctor-owned guard contracts,
additive persistence and synthetic tests within the existing task. This explicitly
supersedes the clinical-authority planning stop in the historical checkpoint-0
record. Checkpoints 1–4 resume without changing the governing task or repeating
its planning. Ordinary application changes, evidence, commits and push on this
branch are authorized. New dependencies, external clinical integration, real
patient access, production migration/deployment and main merge remain prohibited.

The approved guard defaults to unknown. Neither a registry row, an absent report,
AI state nor a missing finalization timestamp proves a negative clinical status.
Only current verified coordinated evidence covering the exact case, immutable
binding, handoff and all reporting sources can permit reopen. Ordinary Admin
privileges cannot override unknown status. The shipped source returns unavailable;
real-case reopen is denied. Synthetic test adapters establish no real authority.

## Implemented synthetic-development coverage

| Requirement | Implementation and exercised boundaries |
| --- | --- |
| RAD-URL-001a/b | Explicit issue/revoke/reassign capabilities, managed-facility records, individually eligible named worker selection, copyable signed link, generation invalidation and preserved assignment history; Admin worklist management link and minimized status indicators. |
| RAD-URL-002a/b | Individual existing login with narrowly allowed signed workspace return; fresh active account/professional/facility/exact-case checks; explicit generation propagated through each service transaction; 24/72/168-hour lifetimes, default 24; expiry, revocation, same-actor reassignment and read-only handoff. |
| RAD-URL-003a/b | Existing renderer and controls reused for direct and converted original DICOM, including supplied UIDs. Protected inline-only route; no workspace download/export/AI route. Existing independently authorized Operator behavior remains separate. |
| RAD-URL-004a/b/c | Gateway parser plus immutable source/identity/UID/hash reconciliation; attributable direct-receipt alias proof or signed converted manifest proof; versioned snapshot, SOP claims and source binding. Member identity and current facility mapping independently authorize/audit. Separate provenance-bearing metadata/QC; signed technical defaults/unknown/conflicts remain visible and preserved. Approved B2 checklist and all three QC outcomes; no retake automation. Optional detector values are source metadata/overlay, not a fabricated DICOM measurement. |
| RAD-URL-005a/b | Atomic version-bound idempotent pending-review handoff, one per case cycle; no clinical report/assignment creation. Current-handoff consumer validation rejects superseded cycles. Admin reopen preserves prior history and expiry, clears current QC and starts a new cycle only through the Doctor guard. Unknown/finalized/stale/mismatched/unverified/incomplete/uncoordinated evidence denies reopen. |
| RAD-URL-006 | No writes to original study/capture bytes, manifest/signature, receipt/conversion/queue/locator or AI state. Existing ingestion/conversion/viewer/AI regressions are exercised separately. |
| RAD-URL-007a/b | Required durable minimized audit for grant lifecycle, reads, original delivery, metadata/QC, handoff, reopen and denials; mutation rollback and disclosure withholding on append/commit failure. Protected notes remain in domain history. Explicit PDO cleanup handles a pre-commit-listener failure after Laravel clears its transaction counter. |
| RAD-URL-008a/b | Indonesian workspace/forms/status, labeled keyboard-focusable controls, neutral denied/unavailable responses, private/no-store/nosniff/no-referrer headers, synthetic binary/HTTP/browser/concurrency fixtures only. Original-image delivery can be retained by an authorized client; no download-button claim prevents that. |

Principal ownership surfaces are `RadiographerWorkspace` (Operator),
`RadiographerStudyRead` (Gateway), `RadiographerCaseIdentityRead` (Member) and
`ClinicalFinalizationGuard` (Doctor), exposed through owner application contracts.
One additive migration creates ten owner-prefixed tables. No existing history
is deleted by workspace operations. Qualification and protocol records require
trusted owner input; there is no ordinary workspace UI to manufacture verification.

Protected HTTP inventory:

- `GET /radiographer/entry/{workspace}` — signed individual entry, then session generation.
- `GET /radiographer/workspaces/{workspace}` — protected HTML/viewer/QC.
- `GET /radiographer/workspaces/{workspace}/dicom` — protected inline original.
- `POST /radiographer/workspaces/{workspace}/save` — metadata/QC with source/revision checks.
- `POST /radiographer/workspaces/{workspace}/handoff` — versioned idempotent handoff.
- `GET|POST /admin/radiographer-workspaces/{reference}` — scoped management/read actions.

Web CSRF and bounded route throttling apply. Neither a guessed selector nor a
forwarded signed URL grants access. Member/Gateway direct source reads use the
same audited assignment-generation boundary. Every operation rechecks before
committing its audit/result. Doctor status refresh and reopen coordinate on the
same case guard; reporting writers requiring the workspace lock must take it
before the Doctor lock and validate the current handoff. The guard's absolute
UTC/offset timestamp check and 30-second ceiling supplement, and never replace,
the provider's current verified source coverage and reliable coordination.

## Technical review

A separate read-only reviewer identified generation-check separation, acceptance
of relative evidence timestamps and loss of converted technical-source provenance.
All were corrected. Remediation review confirmed the explicit generation reaches
the locked operation, relative/empty timestamps fail closed and original technical
provenance/conflicts survive verification/correction. The later Member boundary
and optional-detector deltas received a bounded read-only check with no new
important finding. This is supporting technical review, not A9 acceptance.

## Verification boundary

All data, accounts, object storage and database instances used here are synthetic.
The application image's existing Node 24 parser runtime was reused. Tests use the
already installed development vendor tree plus existing production autoload
mappings from the built image; Composer/npm manifests and lockfiles are unchanged.
No new dependency was added. A direct Windows Docker build context encountered an
untracked `public/storage` filesystem-mode error; a clean directory exported from tracked and nonignored
source avoids that local symlink and operational files without changing packaging.
The intermediate stdin-tar build bypassed client-side ignore filtering and its
fixture-absence probe failed; it is not the final verified image. The final build
uses the exported directory and normal `.dockerignore` filtering.

The full suite is also run, including affected authentication tests. Unavailable
image-only Python/WebP fixture capabilities are reported explicitly rather than
converted into passing checks. MySQL tests use their own synthetic private-object
disk; an earlier parallel attempt shared SQLite's fake disk and was invalidated
when that other test process cleared the directory. The final race run uses
independent connections/processes and isolated storage.

## Observed verification results

All commands are local disposable verification, not CI or production execution.
Runtime: PHP **8.4.26**, Node **24.21.0**, locked parser package **1.8.21**;
MySQL **8.4.8**; installed Chromium/Playwright and existing browser renderer.
`/tmp/run-suite.php` in the disposable test container loads its installed dev
PHPUnit plus the existing image's production PSR-4 and PSR-0 mappings. It changes
no repository dependencies or application code.

**Required integrated application/parser/compatibility/security run: passed,
252 tests, 6,176 assertions, zero failures/errors/skips.** Exact selection:

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
```

This includes actual synthetic Part-10 bytes, direct/converted/supplied UIDs,
identity/site/version/checksum/receipt/manifest denials, invalid/stale writes,
QC outcomes and incomplete protocol/checklist denial, handoff replay/read-only,
unknown/finalized/invalid clinical evidence, immutable histories, minimized audit,
append/commit failure and byte withholding. A final negative HTTP regression also proves signed-manifest/DICOM sex conflicts
deny disclosure when the canonical owner sex is unknown. The additive migration up/down/up
check preserves existing study data on disposable SQLite.

**Independent-connection/process MySQL run: passed, 4 tests, 52 assertions.**

```sh
MHCS_RAD_MYSQL_TESTS=1 php vendor/bin/phpunit \
  tests/Integration/RadiographerConcurrencyTest.php --stop-on-error
```

It checks the database name `mhcs_rad_synthetic` before migrations/cleanup, uses
its own private-object disk and exercises duplicate handoff, committed account
revocation against waiting handoff, same-actor reassignment against waiting
old-generation read, and finalized evidence against waiting reopen. The waiting
operations are observed blocked before the writer commits. Duplicate submission
returns one handoff/event; unsafe operations deny after the relevant commit.

**Browser: passed, 3 journeys with rendered pixels**, direct / converted /
supplied-UID converted; **6 handoffs**, all **3 QC outcomes**, **3 unknown-status
reopen denials**, **3 synthetic verified reopens**, **2 revocations** and **1
expiry**. Each original canvas has nonuniform pixel content (observed intensity
range 255), with keyboard focus/control checks and no download/AI links. The
harness uses an isolated container, synthetic SQLite/objects and file sessions;
its HTTP bootstrap/clinical fixture adapters are excluded from the final image.

```sh
RAD_BROWSER_CONTAINER=mhcs-rad-browser node tests/Browser/radiographer-workspace.mjs
```

Captured read-only synthetic workspaces, after renderer readiness:
[direct](artifacts/radiographer-workspace/radiographer-direct.png),
[converted](artifacts/radiographer-workspace/radiographer-converted.png),
[supplied UIDs](artifacts/radiographer-workspace/radiographer-supplied.png).
These 2×2 synthetic pixels prove browser rendering, not clinical display quality.

**Viewer JavaScript:** `node --test tests/JavaScript/operator-dicom-viewer.test.mjs`
passed **17/17**, zero skips. **Assets:** `npm run build` passed, including the
DICOM browser bundle check. **Formatting:** installed `vendor/bin/pint --dirty
--test` passed; `git diff --check` passed. Manifests, lockfiles, governing task and
the accepted Dockerfile have no changes from the resume revision.

**Unfiltered full suite:** `php /tmp/run-suite.php` ran **883 tests, 13,220
assertions; 3 errors, 9 failures, 11 skips**. No Radiographer test failed. Two
`AdminMemberRegistrationTest` WebP fixture cases error because the image GD build
has no `imagewebp`. The `AiPacsPlaywrightReportDownloaderTest` success case errors
and its nine Python worker/readiness/selection/PDF checks fail because `python3`
is absent from this application test image. These source paths are unchanged.
Four MySQL cases are skipped without the explicit disposable-DB flag and passed
in the separate run above; seven other existing skips remain. This is not a
full-green regression claim. No unrelated Python/WebP dependency or packaging
change was introduced to mask those environment limitations.

## Remaining real-authority and production boundaries

Real clinical reopen remains unavailable until an authoritative reporting source
is integrated and validated for complete source coverage, exact versions, freshness
and coordination. Unknown cannot be overridden by ordinary Admin privileges.
No Radiologist account, report, signature, publication, amendment or full Doctor
workflow was created.

Before affected real-data operation/G10: trusted real staff/protocol verification;
audit retention/holds and authorized operational readers; clinical/institutional
and applicable regulatory decisions; calibrated display assurance; deployed
source/parser compatibility; migration/backup/rollback approval; HTTPS, proxy,
session, logging and operational controls; release authorization. Synthetic tests,
technical review and a pushed branch do not satisfy those obligations. CI,
independent A9 acceptance and production execution are not asserted.

## Final build and delivery state

`docker build --target app -t mhcs-radiographer-workspace:verified .` passed from
the clean exported source directory with normal context filtering. Final local
image: `sha256:380f70b20e323a710e39516a4b5ac8edd273b26d96cdadfe4aa2fb8f4ce9adcc`.
A non-root, network-none probe bootstrapped Laravel and resolved the Operator,
Member and Gateway workspace contracts; it confirmed the default clinical source
returns unavailable and the synthetic HTTP fixture file is absent. This is a
local image check, not deployment.

Artifact SHA-256:

- Direct: `83a519452384ecc2617191f11ca5cf86c458f946fdfd85fb9549f5375b45b548`.
- Converted: `b398e4478e22ef4ee03f48d4205aa4ab94683f75841ed6279fa9405f5f4f081e`.
- Supplied UIDs: `d60d153e18b275ef4462fefbb66602ef499aab0acc5ca9ef79c18d2a20fdbe61`.

Technical methodology checked against current official
[Laravel transaction documentation](https://laravel.com/framework/docs/13.x/database#database-transactions)
and [signed URL documentation](https://laravel.com/framework/docs/13.x/urls#signed-urls).
Source and observed tests, not those references, establish implementation claims.
Existing repository-intelligence indexes describe an earlier main revision;
material implementation conclusions were verified against current source and
observed tests rather than inferred from those indexes.

Terminal execution state: **Review Required**. Checkpoints 1–4 implementation
and synthetic evidence are available for independent protocol review. The full
suite's explicit environment failures remain recorded; no A9 acceptance, clinical
operational reopen or G10 approval is claimed. The immutable implementation and
observed pushed branch revision are supplied by the execution handoff; no new
task publication replaces the governing contract.
