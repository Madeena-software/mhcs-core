# Centralized Study Worklist — execution evidence

Date: 2026-10-13. Executor outcome: **REVIEW REQUIRED**, for independent V7/A9 evaluation. This record establishes observed local evidence, not independent acceptance, CI or release approval.

Implementation baseline: `c63577f2f677998ebbcc88ff0f407db028ffb539`. Independently reviewed Draft: `66d1d23da0a9b9302693d1c1dbd34060afd50c1b`. Governing task: `.agents/tasks/centralized-study-worklist.md @ fe8753490d361702e034a364240cb104e779c22c`. That approved publication was ordinary-pushed and fetched; local and remote task content/revision matched before application edits. SW-D1/D2 were recorded from the explicit Product Owner synthetic-development continuation; the original eight acceptance criteria were preserved. Resolve the implementation revision as the immutable commit containing this evidence and application changes; the delivery handoff supplies its full SHA and fetched remote HEAD.

## Implementation and scope

`/admin/study-worklist` is linked from each authorized Board session and Admin navigation. It reuses facility/date/session directory reads, including later directory pages and inactive historical facilities. One selected session yields at most 25 X-ray admissions ordered by creation time and ID, with explicit refresh and previous/next controls. Scope changes reset intake pagination. Member owns facility/session/booking validation; Operator owns admission/ticket validation; Image Gateway owns capture/study/AI association checks and the final public projection. Each owner uses a narrow application contract and fresh existing authorization. Necessary internal tuples are local service values, not public Livewire properties.

All four capabilities and current Admin eligibility are required for rows. Failure conservatively withholds the page projection; it never invents a successful empty list. The existing audit gate commits successful attributable scoped reads before return and reauthorizes after commit; its verified-context handling now also covers Member study-reference reads. Only operational references/context and persisted states reach the view. DICOM-record availability claims neither storage reachability nor image access. AI readiness is visibly unverified and expressly separate from radiologist approval, diagnosis and patient publication. No schema, permissions, dependencies, routes, runtime/CI configuration, ingestion behavior, report generators or clinical actions were changed.

## AC evidence map

| Criterion | Observed evidence |
|---|---|
| AC1 | Initial HTTP Board link and worklist; Livewire and Chromium selection, progressed/no-capture admissions, 27-row pagination, stable repeat reads, refresh, empty selection and later session directory page. |
| AC2 | All five processing and five AI states, no capture, incomplete legacy receipt, absent study/job, unknown status, and accepted direct DICOM with absent NPZ success flags. Processing failure is independent from record existence. Browser verifies localized unverified-AI warning. |
| AC3 | Owner-contract checks reject booking snapshot, ticket/session, admission/session/state, capture/site, study format, AI member/capture/status, stale mapping, orphan ticket and duplicate booking/capture associations. Gateway batches are bounded; query count does not grow per intake. |
| AC4 | Four missing-capability variants, guest/Member/Doctor/Operator, panel/manage-only and inactive/password-change/login-disabled actors; direct owner bypass attempts and query tracing; real HTTP/Livewire JSON and Chromium revocation/forgery. Revocation during Gateway source read and after final audit commit withholds values. |
| AC5 | Sentinel identities, internal row IDs, private keys/filenames/errors/findings/URLs absent from HTML, JSON, Livewire snapshot, audits and captured log events. Dynamic references escape markup. Query tracing excludes report, capture-object and Member identity tables. Read tests prevent stray remote requests and assert no queued/dispatched jobs. |
| AC6 | Successful audit exists before return, with actor attribution and verified facility/session/date context. Owner/final audit append failure, final commit failure, nested transaction, source exception and authorization revocation withhold. Query tracing permits only audit writes, with no domain/outbox mutation. |
| AC7 | New copy and state labels resolve from `lang/id.json`; native labeled selectors and keyboard-tested refresh/pagination. Facility-local midnight, overnight, spring/fall DST, invalid date/timezone, inactive history and session-scope reset tests. |
| AC8 | Local SQLite, HTTP/Livewire, Chromium, architecture/security, installed-runtime and static checks below. MySQL worklist/Board checks passed; affected regression results are recorded below. Existing fixture corrections and excluded behavior remain untouched. Prior unsuccessful runs are retained as limitations, not suppressed. |

## Observed checks

SQLite configuration: `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=`. No ambient application database was used. Tests build isolated synthetic fixtures with the existing Board pattern; successful reads are outside test transactions.

- `php artisan test tests/Feature/Admin/AdminCentralizedStudyWorklistTest.php --compact`: **52 passed, 2,317 assertions, exit 0** on final implementation/tests.
- `php artisan test tests/Feature/Admin tests/Architecture tests/Security tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php tests/Feature/Operator/OperatorFieldOperationsSlice3Test.php tests/Feature/Operator/OperatorFieldOperationsSlice4Test.php tests/Feature/Operator/OperatorAiPacsIntegrationTest.php tests/ImageGateway/ImageGatewayAiDispatchTest.php --compact`: **296 tests, 295 passed, 1 skipped, 6,903 assertions, exit 0**. This run loaded the preceding 51-worklist-case test revision; the subsequently strengthened final-audit tests passed in the final 52-case focused run. The sole skip is `Mvp04nXrayProtocolConfigurationTest::test_mysql_concurrent_first_publications_leave_one_current_version`, which requires MySQL and `proc_open`; it is not waived by this task.
- `vendor/bin/pest /var/www/mhcs-core/tests/Browser/AdminCentralizedStudyWorklistTest.php`: **2 passed, 30 assertions, exit 0** against separate synthetic file-backed SQLite/session directories and real Chromium. Includes login, Board navigation, paging, refresh, AI warning, keyboard focus, forged scope, corrupt state, revocation, logout and direct-route protection.
- `vendor/bin/pest /var/www/mhcs-core/tests/Browser/AdminFacilitySessionOperationsTest.php`: final separate run **5 passed, 78 assertions, exit 0**. Original unchanged-baseline run also passed **5/78**. Detached exact `c63577f...` checkout with copied installed dependencies/public assets passed **5/78**.
- Changed-file PHP syntax: **18 `.php` paths checked, exit 0**, including two Blade paths. Focused `vendor/bin/pint --test <changed PHP paths>`: **passed, exit 0**. `git diff --check`: **exit 0**. Translation/state-key and forbidden-surface inventory assertions passed. Blade rendering is additionally exercised through HTTP/Livewire/browser tests.

MySQL setup reused the isolation approach of `deployment/verify-mysql.sh`, with `mysql:8.4 --pull never --tmpfs /var/lib/mysql:rw`, loopback-only ephemeral port, generated ephemeral credentials, `mhcs_study_verification` database, explicit testing environment and no ambient DB URL. Observed server **8.4.11**, installed image ID `sha256:b3b90af2a6552ae30c266fdb7d5dd55f3afb72404bb78d37fe8a23eb857fd3fb`. `/var/lib/mysql` was confirmed as `tmpfs`. Actual schema types: `shift_schedules.starts_at=datetime`, `operator_queue_admissions.created_at=datetime`, `image_gateway_capture_sets.accepted_at=timestamp`, `image_gateway_ai_jobs.status=varchar`. No schema modification was needed; capture/job synthetic timestamps remain within MySQL's supported range.

- `php artisan test tests/Feature/Admin/AdminCentralizedStudyWorklistTest.php tests/Feature/Admin/AdminFacilitySessionOperationsTest.php --fail-on-skipped --compact`: **76 passed, 3,085 assertions, zero skips, exit 0** (606,864 ms). Includes all final 52 worklist cases and 24 Board cases, date/pagination behavior, tuple rejection and real database audit rollback/commit-failure paths.
- `php artisan test tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php tests/Feature/Operator/OperatorFieldOperationsSlice3Test.php tests/Feature/Operator/OperatorFieldOperationsSlice4Test.php tests/Feature/Operator/OperatorAiPacsIntegrationTest.php tests/ImageGateway/ImageGatewayAiDispatchTest.php --fail-on-skipped --compact`: **65 passed, 619 assertions, zero skips, exit 0** (155,069 ms).
- Entire isolated MySQL orchestration: **exit 0**. Its owned container was removed after verification; the preceding superseded disk-backed container and its owned anonymous volume were also removed. No production database or external credentials were used.

Installed versus locked versions were inspected: Filament **5.7.8 / 5.7.5**, CommonMark **2.10.0 / 2.9.0**, Livewire **4.3.5 / 4.3.5**, Pest Browser **4.3.1 / 4.3.1**. No dependency was installed or lockfile modified. These are installed-runtime checks, not lock-aligned certification. Current official [Filament custom-page documentation](https://filamentphp.com/docs/5.x/navigation/custom-pages) and [Livewire component/state documentation](https://livewire.laravel.com/docs/4.x/components) were consulted. Codebase Memory discovery/coverage was supporting intelligence; direct source and observed execution support material claims.

## Unsuccessful checks and limits

- Initial focused test bootstrap collided with the framework's inherited `component()` helper; the new test helper was renamed. No application workaround was required.
- The first SQLite regression run had one failing synthetic revocation assertion: the fixture changed permissions inside the read transaction, and rollback restored them. It was corrected to simulate independently changing authorization claims; real persisted revocation is separately exercised by HTTP/Livewire and browser requests. Current focused and regression runs pass.
- Initial new Chromium keyboard assertion incorrectly assumed a date input has one Tab stop. Chromium has multiple date subfields. The corrected test checks facility-to-date and session-to-refresh focus, and keyboard refresh. Both worklist journeys pass.
- Two combined worklist/Board browser runs reported `ResizeObserver loop completed with undelivered notifications` in the existing Board save/return/registration journey, at Member registration. Neither is hidden or labeled harmless. Before/after evidence is mixed: original and exact-baseline Board runs pass, final changed Board-alone run passes, and final worklist-alone run passes. Independent Reviewer must disposition this intermittent combined-run failure; this execution does not establish its cause or waive it. No browser expectation was suppressed and no registration code was changed.
- Isolated-baseline harness attempts first encountered cross-filesystem hard-link failure, a wrong-root duplicate Composer autoloader, and missing ignored published Filament assets. Copying existing dependencies/assets and using the exact checkout/config resolved the harness; no third-party installation or repository artifact change occurred.
- Initial disk-backed MySQL verification was superseded and terminated without a passing result after slow repeated schema resets. Its owned container and anonymous volume were removed. Final verification uses an independently created disposable MySQL 8.4 container with temporary in-memory data storage, explicit testing environment, synthetic key/credentials and owned database only. The final command results below govern backend evidence.

No full application suite, full MySQL migration/rollback probe, all Operator browser suites, CI/workflow dispatch, production/storage/external-provider check, real-patient processing, clinical validation or release verification was performed. Existing reports of three other browser failures were not reclassified or claimed fixed. Operational audit retention/access, external privacy/legal, hospital/clinical, real-data, MVP-GAP-028/029/031/035/036 and release approvals remain separate dependencies.

Only ordinary commit/push to `task/centralized-study-worklist-planning` is authorized. No PR, merge, deployment, production operation or unrelated change occurred. This implementation requires independent review and has no Executor acceptance verdict.

## Complete changed-file inventory

Relative to implementation baseline, including reviewed Draft/task publication and this evidence:

- `.agents/tasks/centralized-study-worklist.md`
- `app/Modules/ImageGateway/Application/Contracts/AdminStudyWorklist.php`
- `app/Modules/ImageGateway/Application/Services/AdminStudyWorklistRead.php`
- `app/Modules/ImageGateway/ImageGatewayServiceProvider.php`
- `app/Modules/Member/Application/Contracts/AdminStudyReferences.php`
- `app/Modules/Member/Application/Services/AdminStudyReferenceRead.php`
- `app/Modules/Member/MemberServiceProvider.php`
- `app/Modules/Operator/Application/Contracts/AdminStudyIntakes.php`
- `app/Modules/Operator/Application/Services/AdminStudyIntakeRead.php`
- `app/Modules/Operator/OperatorServiceProvider.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Providers/Filament/Pages/AdminCentralizedStudyWorklist.php`
- `app/Providers/Filament/Pages/AdminFacilitySessionOperations.php`
- `app/Shared/Authorization/AdminOperationalRead.php`
- `docs/mvp/evidence/centralized-study-worklist-execution.md`
- `lang/id.json`
- `resources/views/filament/pages/admin-centralized-study-worklist.blade.php`
- `resources/views/filament/pages/admin-facility-session-operations.blade.php`
- `tests/Browser/AdminCentralizedStudyWorklistTest.php`
- `tests/Feature/Admin/AdminCentralizedStudyWorklistTest.php`
- `tests/Support/StudyWorklistFixtures.php`
