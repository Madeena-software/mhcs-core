# Radiologist Workspace v1 — E6 execution evidence

Historical execution record. The [consolidated R8 evidence](radiologist-workspace-v1-r8.md)
supersedes its clinical-source completeness claim and its browser/MySQL runtime gaps.
The original observations below are retained for provenance.

Status: **REVIEW REQUIRED**. This is a reviewable synthetic-development implementation,
not V7/A9 acceptance or release authorization. Required original-image/browser and MySQL
verification is incomplete; no pass is inferred from test doubles or unavailable tools.

Authority: `.agents/tasks/admin-assigned-radiologist-workspace-v1.md` at
`c8886a8ee0c7f11edae5f888fe42e79b86ef5607`; accepted implementation baseline
`0f27d2b58c804183b907e236aa9735f5fac876ac`. The Product Owner's 2026-10-16
handoff supplies independent T5 PASSED, E6, and ordinary feature-branch commit/push
authorization. The governing task itself is unchanged.

Initial fetched publication equaled the supplied SHA, inherited the exact accepted
baseline, and contained only the task overlay. Initial worktree was clean. The
feature branch did not exist locally or remotely; it was created at the publication.
No PR, merge, force push, dependency installation, operational migration or deployment
was performed. Fixtures use only disposable synthetic databases/private storage.

Implementation source revision: `6448f07ff2fc39843f52fd756b8015e36d270860`. Subsequent evidence-only commits do not change the tested application tree.

## Implemented surfaces

Doctor owns explicit scoped professional eligibility revisions, individually assigned
temporary case workspaces, quality decisions, independent report versions, and one
immutable internal finalization per case. Admin has separate issue/reassign/revoke/
credential capabilities and active facility scopes; sensitive commands verify the
acting administrator's password. Ordinary roles and monitoring do not grant these
capabilities. No new permission defaults or account grants are installed.

Access defaults to 24 elapsed UTC hours and accepts positive whole-hour durations up
to 72 hours, with exact exclusive expiry and no automatic renewal. Every protected
owner call rechecks current account, eligibility, source/handoff, assignment generation
and session credential binding. Eligibility validity offsets are normalized to UTC.
Reassignment increments generation, preserves earlier content/authorship, and clears
the replacement's draft and clinical quality decision. Each finalization checks fresh
credentials inside that action, current expected revision/source/handoff, explicit
attestation, meaningful authored findings/conclusion, and current usable quality.

Repeat reasons are exactly the task's `operator_error`, `equipment_failure`,
`incorrect_order`, `medical_limitation`, `other`; other requires an explanation.
Repeat-required preserves the draft and blocks finalization. No repeat is dispatched.

Member resolves current patient/examination/service associations. Operator owns the
current handoff and protocol. Gateway reuses the bounded original verifier,
checksums, embedded UIDs/patient identity, conversion manifests and SOP claims under
independent Doctor authorization. Actual CR/DX modality is verified against SOP class.
The original checksum already binds those bytes, so deriving modality does not alter
existing handoff-hash material. A legacy handoff with no modality in its snapshot is
re-verified from original bytes; an actual missing/unknown modality denies assignment.

The Operator handoff lock precedes the Doctor guard for clinical commands. Draft,
quality, final report, explicit clinical aggregate state, history and required audit
commit together. The production clinical source inventory consists of the new
Doctor workspace reporting aggregate/version/finalization stores; AI reports are
excluded processing artifacts. Unknown/unregistered/inconsistent/superseded evidence
remains unknown. No missing-final-report or guard-registry heuristic permits reopen.
Future clinical reporting sources must extend this coordinated inventory.

Protected HTML, viewer, image and command responses use the existing send-time
WorkspaceResponse ordering. Source integrity and authorization are checked around
operation audit and again before response headers; required audit commits before
output. No database lock is held while emitting response bytes. Authorized output
already in flight cannot be recalled. HEAD/range/cache paths use the same gates.
There is no new Livewire clinical endpoint, report export, amendment or publication.

The existing Cornerstone viewer is reused in a protected iframe with a separate
clinical report form. Admin's central worklist links directly to Doctor management;
Radiographer management privileges are not a prerequisite. Shared login preserves
only the explicitly allowlisted signed entry query parameters, and clinical login
admission checks the current named assignee before creating the session.

### Routes observed with `php artisan route:list --path=radiologist --json`

| Method | URI | Purpose |
|---|---|---|
| GET/HEAD | `/admin/radiologist-studies/{reference}` | Independently scoped current-handoff locator |
| GET/POST/HEAD | `/admin/radiologist-workspaces/{handoff}` | Admin management and explicit credentialing |
| GET/HEAD | `/radiologist/entry/{workspace}` | Signed locator plus individual authentication |
| GET/HEAD | `/radiologist/workspaces/{workspace}` | Protected clinical report/history workspace |
| GET/HEAD | `/radiologist/workspaces/{workspace}/viewer` | Protected original viewer |
| GET/HEAD | `/radiologist/workspaces/{workspace}/dicom` | Protected original bytes |
| POST | `/radiologist/workspaces/{workspace}/{action}` | Only quality, draft, finalize |

Direct service boundaries: RadiologistWorkspace manage/verifyEligibility/entry/read/
command/dicom/run and managementHandoff; Gateway assignment/handoffForStudy/
inspectClinical; Member forAssignment/resolveClinical/service; Operator handoff
selection/forStudy/protocol and the existing currentness check. These resolve trusted
scope on the server. The Operator `matches` observation returns only a currentness
boolean under the coordinated Doctor guard and acquires no reversed upstream lock.

### Additive migration

`database/migrations/2026_10_16_000001_create_radiologist_workspace_tables.php` creates
`doctor_workspace_managers`, `doctor_eligibility`, `doctor_workspaces`,
`doctor_report_versions`, `doctor_finalizations`, `doctor_workspace_history`.
Up/down was exercised only on disposable SQLite while preserving the original study
and upstream handoff. No operational migration/backfill was run. MySQL migration
and race execution remain unverified.

## Acceptance-to-implementation-to-test coverage

`U` below is `tests/Unit/Doctor/RadiologistCommandsTest.php`, which deliberately binds
`SyntheticRadiologistSource` for command/auth tests. It does **not** verify the actual
Gateway, DICOM parsing isolation, integrity or rendering. `F` is the real-Gateway
`tests/Feature/Doctor/RadiologistWorkspaceTest.php`; both F cases error during upstream
handoff preparation on this host. `M` is the opt-in real-MySQL
`tests/Integration/RadiologistConcurrencyTest.php`; its 12 cases were not executed
successfully here. This mapping is implementation/evidence traceability, not an AC pass.

| AC | Implementation | Observed tests / remaining evidence |
|---|---|---|
| AC1 | Scoped eligibility revisions, capability-separated verification, exact handoff, assignment generation/history | U capability separation, forged verifier, no Radiographer privilege dependency, reassignment, facility/service/modality/validity/evidence changes; M assign/assign pending |
| AC2 | Named individual login, signed locator, server session binding, 24/72-hour policy and post-final read-only | U individual login/forwarded locator, wrong actor/guest/missing session, duration/expiry/no-renewal, account/password/eligibility revocation, final read-only; rendered positive journeys pending |
| AC3 | Independent owner gates and durable operation/disclosure audit | U direct bypass, prepared output, append failure, HEAD/range/cache tests; actual-image disclosure not proved because F is blocked |
| AC4 | Shared actual verifier/manifests/checksums/UID reconciliation and protected Cornerstone viewer | 2 standalone JS parser/modality checks pass without claiming seccomp verification; F direct/converted and actual browser rendered journeys blocked |
| AC5 | Explicit quality, task-controlled reasons, immutable history, repeat_pending behavior | U repeat/draft/final denial plus all five approved reasons and unsupported-reason rejection; real-source browser path pending |
| AC6 | Immutable independently authored version rows, monotonic expected revision, no replacement adoption | U incomplete/unsafe/encoded-markup/blank content, stale saves, forged author, independent replacement and preserved history; F/browser pending |
| AC7 | In-action password verification/throttling, current session/case/revision, unique final report and internal attestation | U meaningful content, required credentials/attestation, recent-flag rejection, failed attempts/throttling, successful idempotent retry and immutable final writes; actual browser/MySQL completion pending |
| AC8 | Shared case guard; explicit complete reporting aggregate and atomic final/status/history/audit | U final/reopen exclusion, inconsistent/unknown/superseded source and commit rollback; M save/save, finalize/finalize, finalize/reassign/reopen/account/eligibility and audit/commit races pending |
| AC9 | No excluded owners/events or mutation routes added | U no AI jobs, unchanged outbox/point-ledger counts, denied legacy download/AI access, immutable finalized edits; source inventory shows no publication/repeat/financial command added |
| AC10 | Existing ownership/hash/byte patterns preserved; additive schema/UI/login changes | Full accepted-baseline comparison below, architecture and JS/build checks; real-image predecessor failures remain environment-blocked |

## Verification results

All results are local, synthetic and observed on source `6448f07ff2fc39843f52fd756b8015e36d270860`.

| Check | Observed result |
|---|---|
| `php -d memory_limit=1G vendor/bin/phpunit --log-junit /tmp/mhcs-rad-final-junit.xml` | 954 tests; 885 passed; 10 failures; 29 errors; 30 skipped; 13549 assertions; exit 2 |
| Accepted-baseline full suite with isolated baseline bootstrap | 900 tests; 845 passed; 10 failures; 27 errors; 18 skipped; 13031 assertions; exit 2 |
| Focused Doctor command/auth plus architecture | 47 passed, 2578 assertions; later optional-Recommendations omission also passed in the lifecycle case and the final full suite |
| Existing Member/Operator login plus Doctor command checks at login refinement | 68 passed, 730 assertions |
| Existing Operator basic-examination suite during failure attribution | 19 passed as part of the 52-case refinement run; baseline singled-out flake test also passed |
| `node --test tests/JavaScript/operator-dicom-viewer.test.mjs tests/JavaScript/radiologist-dicom-modality.test.mjs` | 19 passed, 0 failures/skips |
| `npm run build` | Passed, including DICOM browser bundle check; chunk-size warning remains |
| Installed Pint `--test` on all changed PHP files | Passed |
| `php -l` on all changed PHP files | Passed |
| `git diff --check` | Passed |
| Additive migration up/down | Passed on disposable SQLite; original study/upstream handoff preserved |
| MySQL race/atomicity suite | 12 skipped in full suite; explicit first-race opt-in failed DNS before DB mutation |
| Real-Gateway Doctor feature suite | 2 errors in upstream handoff preparation; neither is passed |
| Real browser direct/converted journey harness | Failed synthetic fixture preparation with DomainException; no original rendered |
| Browser denied state | 403, accessible alert, keyboard focus and screenshot observed |

The 10 failures and 27 errors present at the accepted baseline have exactly the same
names/statuses in the final full run. The only new errors are:

- `Tests\Feature\Doctor\RadiologistWorkspaceTest::test_direct_original_report_journey_and_immutable_finalization_blocks_reopen`
- `Tests\Feature\Doctor\RadiologistWorkspaceTest::test_converted_original_and_repeat_pending_preserves_draft_and_blocks_finalize`

Both fail while creating the upstream Radiographer handoff, before the new clinical
journey, because the inherited bounded verifier cannot execute on this host.
Baseline failures/errors comprise Radiographer/DICOM verification (including cascading
incomplete-QC assertions), missing installed `Mpdf\Mpdf` for existing PDF tests, and
missing Python `PIL` for an existing PDF/image check. No new dependency was installed.
The 30 skips comprise 18 existing opt-in tests and 12 new MySQL race/atomicity cases.
Every passing/failing/error/skipped case name is recorded in the external
`radiologist-v1-e6/verification.json` manifest; raw patient/credential/session data
and captured HTML are not included in that manifest.


The detached accepted-baseline checkout used the installed dependencies but canonicalized
Composer classmap paths back into the baseline checkout. It used its own in-memory
SQLite and storage tree. Initial runs with an incorrect symlink/classmap configuration
or overlapping shared-storage PHP tests were discarded. Final compared runs were
isolated. No CI result is claimed.

An earlier full run also hit the unchanged Operator basic-examination test's broad
`assertDontSee('120')` when its random MRN contained `120`. The existing focused
19-case Operator suite passed on rerun; the same single test passed at the baseline.
This pre-existing substring flake was not changed in this task.

### Browser and MySQL limitations

`APP_ENV=testing node tests/Browser/radiologist-workspace.mjs` launches installed
Chromium but stops when its real upstream fixture cannot complete bounded DICOM
verification. No original image was rendered and no positive clinical report browser
journey passed. The harness does not replace production Gateway or clinical-status
bindings with a success fixture. HTTP 200 alone is never counted as rendering.

The actual browser denied-path check observed 403, an accessible alert, keyboard
focus on the login link, and no patient disclosure. Screenshot:
`tests/Browser/Screenshots/radiologist-denied.png` (ignored generated artifact, also
preserved in the external checkpoint evidence directory). It is denied-state evidence,
not positive original-image evidence.

Installed `/usr/bin/setpriv` is util-linux 2.39.3 and rejects `--seccomp-filter`.
The unchanged accepted-baseline bounded-verifier tests fail for this same reason.
The verifier remains fail-closed; its seccomp/no-new-privileges/resource/permission
boundary was not bypassed or replaced. Docker is unavailable in this WSL runtime.
The `mysql` hostname does not resolve. Explicit opt-in MySQL assignment-race attempt
fails before migration with SQLSTATE HY000/2002; no MySQL race/atomicity pass is claimed.

Supported-runtime rerun commands:

```sh
php -d memory_limit=1G vendor/bin/phpunit
MHCS_RAD_MYSQL_TESTS=1 php -d memory_limit=1G vendor/bin/phpunit tests/Integration/RadiologistConcurrencyTest.php tests/Integration/RadiographerConcurrencyTest.php
APP_ENV=testing php -S 127.0.0.1:18100 -t public tests/Support/radiologist-http.php
# In another terminal, against that isolated test server:
APP_ENV=testing node tests/Browser/radiologist-workspace.mjs
```

The MySQL opt-in is restricted to the disposable `mhcs_rad_synthetic` database using
the predecessor suite's existing configuration; it asserts that database before
migrating. It is destructive to that synthetic database and never an operational
migration command.

## Review obligations and limits

V7 must evaluate all AC1–AC10 rather than interpreting command-test coverage as complete
acceptance. Actual original-image integrity/disclosure and positive direct/converted
browser journeys, supported MySQL migration/concurrency/atomicity, and the failing
predecessor regressions remain required before acceptance. Failure attribution identifies
host limitations but does not waive them. Real-Gateway negative variants, all complete
browser error/accessibility journeys and supported-MySQL execution are not claimed done.

Security-relevant corrections observed during E6 include fresh session/eligibility
checks, UTC validity normalization, allowlisted signed login query preservation,
meaningful-content validation, explicit task reason vocabulary, independent authorship,
source rechecks, and fail-closed inconsistent status. No independent security audit,
production credentialing/institutional approval, legally certified signature or release
readiness is claimed. Audit operational retention/access/holds and broader clinical,
legal, backup/rollback and production controls remain their designated owners' obligations.

Current external checkpoint:
`/home/faliq/.codex/task-state/mhcs-core/CHAT_MEMORY.md`.
Exact per-test status manifest and local artifacts:
`/home/faliq/.codex/task-state/mhcs-core/radiologist-v1-e6/`.

## Git delivery and runtime checkpoint

Ordinary scoped source commits were pushed on `feat/radiologist-workspace-v1`:
`58cc95ba3ddb4481a5051af7b5c4b15e8209a24f`,
`b096653` (UTC validity and boundary tests), `fe0c9cb` (individual login binding),
`d073f3f` (approved repeat reasons), `fcd64be` (owner-read auditing/minimization),
and source revision `6448f07ff2fc39843f52fd756b8015e36d270860` (optional Recommendations).
Fetched/live remote equality and the final evidence-only head are recorded in the
terminal handoff and external checkpoint rather than embedding a self-containing SHA.
No task, accepted baseline, or acceptance state was advanced.

## Installed tools and primary references

Observed: PHP 8.4.1, Node 22.15.0 (permission flag supported), SQLite 3.43.2,
Cornerstone 5.7.0, dicom-parser 1.8.21, Playwright 1.62.1, Vite 8.2.0,
util-linux setpriv 2.39.3. No MySQL backend/version was reachable.
The reused stack-viewer API was checked against the installed package and
[Cornerstone's official stack tutorial](https://www.cornerstonejs.org/docs/tutorials/basic-stack/).
The CR/DX SOP-class check follows the
[DICOM standard SOP-class registry](https://dicom.nema.org/Medical/dicom/current/output/chtml/part04/sect_B.5.html).
The unchanged production verifier's host-isolation check still fails and is not
substituted by these parser/API references.

## Changed application/test files

```text
app/Http/Controllers/Member/AuthenticationController.php
app/Http/Controllers/Radiologist/WorkspaceController.php
app/Modules/Doctor/Application/Contracts/RadiologistAccess.php
app/Modules/Doctor/Application/Services/RadiologistLoginAccess.php
app/Modules/Doctor/Application/Services/RadiologistWorkspace.php
app/Modules/Doctor/DoctorServiceProvider.php
app/Modules/Doctor/Infrastructure/WorkspaceClinicalStatusSource.php
app/Modules/Doctor/README.md
app/Modules/ImageGateway/Application/Contracts/RadiologistStudySource.php
app/Modules/ImageGateway/Application/Services/RadiographerStudyRead.php
app/Modules/ImageGateway/ImageGatewayServiceProvider.php
app/Modules/ImageGateway/Infrastructure/Dicom/verify.cjs
app/Modules/Member/Application/Contracts/InteractiveRadiologistAccess.php
app/Modules/Member/Application/Contracts/RadiologistCaseIdentity.php
app/Modules/Member/Application/Services/InteractiveMemberLoginService.php
app/Modules/Member/Application/Services/RadiographerCaseIdentityRead.php
app/Modules/Member/MemberServiceProvider.php
app/Modules/Operator/Application/Contracts/RadiologistHandoff.php
app/Modules/Operator/Application/Services/RadiologistHandoffRead.php
app/Modules/Operator/OperatorServiceProvider.php
database/migrations/2026_10_16_000001_create_radiologist_workspace_tables.php
resources/views/filament/pages/admin-centralized-study-worklist.blade.php
resources/views/operator/study.blade.php
resources/views/radiographer/manage.blade.php
resources/views/radiologist/binding.blade.php
resources/views/radiologist/manage.blade.php
resources/views/radiologist/unavailable.blade.php
resources/views/radiologist/workspace.blade.php
routes/web.php
tests/Architecture/FoundationArchitectureTest.php
tests/Browser/radiologist-workspace.mjs
tests/Feature/Doctor/RadiologistWorkspaceTest.php
tests/Integration/RadiologistConcurrencyTest.php
tests/JavaScript/radiologist-dicom-modality.test.mjs
tests/Support/RadiographerFixtures.php
tests/Support/RadiologistFixtures.php
tests/Support/SyntheticRadiologistSource.php
tests/Support/radiologist-browser-bootstrap.php
tests/Support/radiologist-browser-fixture.php
tests/Support/radiologist-http.php
tests/Unit/Doctor/RadiologistCommandsTest.php
```
