---
title: Admin Facility-Session Operations Execution Evidence
document_id: MHCS-EVIDENCE-ADMIN-FACILITY-SESSION-OPERATIONS-EXECUTION-001
version: 1.0
status: review-required
language: en-US
last_updated: 2026-10-13
---

# Admin Facility-Session Operations — Review Required

This is synthetic local Executor evidence, ready for independent review with explicitly unresolved baseline debt. It establishes no V7/A9 acceptance or G10 release. The prior attempt stopped after two baseline reproductions; the human continuation requests complete comparison and authorizes ordinary commit/push and one Draft stacked PR. All nine unsuccessful Operator cases now reproduce unchanged at the pinned baseline. Application/test content was preserved: its 18-file compact sorted path/SHA-256 manifest remains `84f85ba76c24039c51b7fafafa17b13cedc45d416fd49e07d7315598e705266e` (excluding this record). The immutable implementation SHA and Draft PR URL are resolved in the PR body and handoff after publication.

## Governing identities and independence

- Application baseline: `fc37002d106b839c0b881e7dec7021c91389f501`, unchanged local/remote main at preflight.
- Governing contract: [stable task](../../../.agents/tasks/admin-facility-session-operations.md) at **`9ddf6f800b296353733b5369fd9f42cccae70e93`**, version 1.4, Validated/Published. Task SHA-256: `62e28b125803363769d87bb92caf1f481ed2a7422acb49709be4d4f7e32e015f`; unchanged during execution.
- [Independent T5 evidence](admin-facility-session-operations-t5.md): separate Reviewer `/root/t5_reviewer` verified reviewed `e05882df6dd66cea72ee735702b8cacad144e918` and the final publication delta, P1/R2/A3/D4, all nine human T5 conditions and synthetic development versus production retention obligations. The supplied earlier reviewer provenance was not independently established; GitHub review submissions were empty. The fresh attributable assessment supplies T5, without implying a GitHub approval or implementation acceptance.
- Implementation branch: `task/admin-facility-session-operations`, started from the exact validated publication. The authorized Draft implementation PR targets `task/admin-facility-session-board-planning` and depends on [PR #9](https://github.com/Madeena-software/mhcs-core/pull/9). Planning/application ancestry and the unchanged task blob are verified before publication.
- PR #8 is preserved, without modification, closure or merge. PR #7 registration application/evidence remain unchanged. No merge, deployment, production dataset, real-patient processing or production permission provisioning occurred.

## Changed surfaces and behavior

Member owns session selection, booking/capacity aggregates and a narrow arrival-association integrity read. Operator owns verified facility mapping and distinct recorded arrivals. Admin composes minimized projections using existing exact owning-module authorization and mutation resources.

New surfaces:

- `app/Modules/Member/Application/Contracts/ArrivalReferenceIntegrity.php`
- `app/Modules/Member/Application/Services/AdminArrivalReferenceIntegrity.php`
- `app/Modules/Member/Application/Services/AdminBookingSummaryRead.php`
- `app/Modules/Member/Application/Services/AdminSessionBoardRead.php`
- `app/Modules/Operator/Application/Contracts/AdminSessionArrivals.php`
- `app/Modules/Operator/Application/Services/AdminSessionArrivalRead.php`
- `app/Shared/Authorization/AdminOperationalRead.php`
- `app/Providers/Filament/Pages/AdminFacilitySessionOperations.php`
- `resources/views/filament/pages/admin-facility-session-operations.blade.php`
- `tests/Feature/Admin/AdminFacilitySessionOperationsTest.php`
- `tests/Browser/AdminFacilitySessionOperationsTest.php`
- `tests/Support/AdminOperationsFixtures.php`

Modified surfaces: Member and Operator service-provider bindings; Admin panel registration; `ShiftScheduleResource` and its `ListShiftSchedules` page; the existing Admin landing assertion in `tests/Feature/Admin/Mvp02AdminAccessTest.php`. This evidence record is the only documentation addition on the implementation branch.

The board uses managed facilities, verified synchronized IANA zones, facility-local today and session-start membership in independently constructed local-midnight UTC intervals. Pagination bounds session reads to 25 rows, and booking queries are grouped across those IDs. Existing capacityStatuses and schedule/site mutation services are unchanged. Labels distinguish exact reserved capacity, remaining capacity, booking status totals and distinct recorded arrivals. Inactive facilities and closed historical schedules remain visible through read authorization.

Fresh account/panel/exact capability checks apply before protected queries and again before releasing values. A minimized attributable success audit must commit before the corresponding data is returned; append, lookup, JSON or commit failures withhold data. Outer transactions withhold values because a savepoint cannot prove durable recording. Denied outcome metadata omits unvalidated selection/date/zone; failed sources do not masquerade as zero. Read projections and X1 values stay out of public Livewire state. X1 uses the same fresh booking-read requirement, a per-request batch and the approved Kapasitas Tercadangkan label.

No migrations, dependencies, runtime configuration, new capability definitions/production grants, seeders, routes, clinical/AI/report/Patient/SIMRS changes or portal retirement were introduced. Existing registration acceptance, historical planning evidence and MVP-GAP-035/036 remain intact.

## AC1–AC8 evidence matrix

| AC | Positive evidence | Negative and preservation evidence |
|---|---|---|
| AC1 | Feature GET `/admin`, persisted sessions/service/local times/status/quota and audited refresh; actual browser credential login and board selection | Empty/no-selection states; account eligibility and direct access covered by focused and existing Admin tests; forged selection and deactivation exercised through real browser updates |
| AC2 | Focused local-today/explicit-date, overnight start-day and 23/25-hour DST fixtures; browser two-zone local-midnight and inactive closed-history cases | Malformed dates, invalid/mismatched mapping/zones and next-day exclusion; facility/date filters independently revalidated |
| AC3 | Every BookingStatus, unchanged capacityStatuses, exact totals/reserved/remaining, zero and over-quota warning; grouped 25-session query assertion | Schedule-only denies booking queries; X1 initial/revoked access and snapshot inspection; audit-failed values unavailable rather than zero; no booking mutation |
| AC4 | Arrival-only exact one for duplicate completed booking, without booking totals; owning Operator aggregate and Member integrity validation | Duplicate distinctness, orphan/cross-site/unknown states, independent missing arrival permission; browser corrupt-source unavailable display; no patient summaries |
| AC5 | Actual browser existing schedule quota save → board reload; existing physical facility edit → synchronized display reload; registration/detail routes reachable | Read-only actor receives no mutation links; existing Admin/registration/MySQL conformance tests retain service authorization, immutability and rollback |
| AC6 | Actual HTTP initial and POST Livewire update, browser refresh after grant revocation/deactivation; localized labels and keyboard focus | Missing/inactive/wildcard/unverifiable claims, forged site, fresh account checks and revoked X1 suppress queries/data; protected snapshots/HTML inspected for identifiers and stale aggregate state |
| AC7 | Persisted successful attributable read audits; before/after board refresh domain/outbox counts; 25-session query bound | Audit append/lookup/real JSON and TransactionCommitting fault injection withholds data and rolls back success audit; outer transaction fails safely; durable postcommit revocation produces minimized rejection metadata |
| AC8 | Full SQLite PHP suite; focused Admin, registration, Operator, architecture/security; 141 MySQL board/Admin/registration/conformance passes and five Chromium browser passes | **Baseline debt:** all 7 MySQL Operator failures and 2 errors reproduce with identical assertions/messages at the pinned baseline. X-ray concurrency passed; protected paths remain unchanged. Acceptance requires independent disposition; Draft publication does not waive AC8 |

## Observed local commands and results

Observed installed environment: PHP **8.5.4**, SQLite **3.46.1**, Laravel **13.23.0**, Filament **5.7.8**, Livewire **4.3.5** and Pest browser plugin **4.3.1**. No dependency was installed or changed. Existing vendor metadata predates this execution. Ten installed Filament packages are **5.7.8** while the unchanged lockfile pins **5.7.5**; installed `league/commonmark` is **2.10.0** versus locked **2.9.0**. These local results prove behavior on the installed environment, not those locked versions. Lock-aligned runtime/CI verification remains an explicit review limitation; dependencies were not restored or updated under this bounded authorization.

All data is synthetic. SQLite uses `DB_DATABASE=:memory:` for PHP suites. Browser uses its own PID-named SQLite file and private session directory. MySQL uses an owned disposable `mysql:8.4` image/container on an ephemeral loopback port, observed server **8.4.11**, a dedicated synthetic database on temporary memory-backed storage and temporary credentials outside tracked files/output. Existing local databases and persistent containers are untouched.

SQLite command prefix: `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test`.

| Scope / command suffix | Actual result |
|---|---|
| `tests/Feature/Admin/AdminFacilitySessionOperationsTest.php --compact` | 24 passed; 738 assertions |
| `tests/Feature/Admin/Mvp02AdminAccessTest.php tests/Feature/Admin/Mvp03BookingAdministrationTest.php tests/Feature/Admin/Mvp04OperatorAdministrationTest.php --compact` | 27 passed; 244 assertions |
| `tests/Feature/Admin/AdminMemberRegistrationTest.php tests/Feature/Admin/Mvp02MemberAdministrationTest.php --compact` | 82 passed; 760 assertions |
| `tests/Operator/Mvp04OperatorFoundationTest.php tests/Feature/Operator/Mvp04OperatorPortalTest.php tests/Feature/Operator/Mvp04dVerifiedCheckInTicketIssueTest.php tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php tests/Feature/Operator/Mvp04fAtomicBasicExaminationClaimTest.php --compact` | 66 passed; 1 skipped; 514 assertions |
| `tests/Architecture tests/Security --compact` | 40 passed; 2,075 assertions |
| `--compact` (full PHP suite) | 751 passed; 7 skipped; 10,431 assertions; 758 total, 361,393 ms |

The seven full SQLite skips are five MySQL/proc_open concurrency cases in `MemberDatabaseConformanceTest`, one automatic ticket allocation contention case in `Mvp04dVerifiedCheckInTicketIssueTest`, and one concurrent first X-ray protocol publication case in `Mvp04nXrayProtocolConfigurationTest`. They are skips, not SQLite passes. The MySQL runs below explicitly require no skips.

MySQL command environment: `APP_ENV=testing DB_CONNECTION=mysql DB_URL=` with the dedicated synthetic database/loopback connection supplied from an owned mode-600 temporary environment file. Existing schema was installed using `php artisan migrate:fresh --force --quiet`.

| MySQL / browser command | Actual result |
|---|---|
| `php artisan test tests/Feature/Admin/AdminFacilitySessionOperationsTest.php tests/Feature/Admin/Mvp02AdminAccessTest.php tests/Feature/Admin/Mvp03BookingAdministrationTest.php tests/Feature/Admin/Mvp04OperatorAdministrationTest.php tests/Feature/Admin/AdminMemberRegistrationTest.php tests/Feature/Admin/Mvp02MemberAdministrationTest.php tests/Integration/MemberDatabaseConformanceTest.php --compact --fail-on-skipped` | Final rerun: **141 passed; 1,792 assertions; zero skips**; 235,223 ms |
| `php artisan test tests/Operator/Mvp04OperatorFoundationTest.php tests/Feature/Operator/Mvp04OperatorPortalTest.php tests/Feature/Operator/Mvp04dVerifiedCheckInTicketIssueTest.php tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php tests/Feature/Operator/Mvp04fAtomicBasicExaminationClaimTest.php --compact --fail-on-skipped` | **58 passed; 7 failed; 2 errors; 463 assertions; zero skips**, 67 total, 27,919 ms. Automatic concurrent ticket allocation itself passed: T-001/T-002, four issue/queue audit and outbox records, two handled idempotency records |
| `php artisan test tests/Feature/Admin/Mvp04nXrayProtocolConfigurationTest.php --filter=test_mysql_concurrent_first_publications_leave_one_current_version --compact --fail-on-skipped` | **1 passed; 9 assertions; zero skips**, 8,792 ms |
| `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test tests/Browser/AdminFacilitySessionOperationsTest.php --compact` | Final coherent run: **5 passed; 78 assertions**; 128,681 ms; actual Chromium HTTP/Livewire, keyboard, save-return, unavailable state and logout |

The continuation completed the remaining X-ray first-publication MySQL probe. All seven SQLite-skipped cases were exercised in their supported MySQL scopes; no skip was converted into a SQLite pass. A full MySQL suite was not run. Existing focused/full PHP and browser evidence was reused because all 18 application/test files match the verified manifest; this continuation changed only evidence.

## Baseline debt comparison and publication disposition

The MySQL ticket/queue fixtures sign paper consent on `2040-01-10`. The existing reusable-consent migration creates `member_master_consents.signed_at` as TIMESTAMP; information_schema confirms that actual column type. A disposable direct service diagnostic reproduces **SQLSTATE 22007 / MySQL 1292: Incorrect datetime value: '2040-01-10 00:00:00' for column 'signed_at'**. The existing HTTP controller rolls both legacy and reusable consent back, so subsequent ticket assertions find no ticket. No migration or consent/controller repair was attempted.

A detached worktree at exact `fc37002d106b839c0b881e7dec7021c91389f501` used the same installed vendor packages and an owned disposable MySQL 8.4.11 database. The original five-file 67-test Operator selection was run with `php vendor/phpunit/phpunit/phpunit --bootstrap <owned-baseline-bootstrap> <same-five-Operator-files-above> --fail-on-skipped`. Composer PSR-4 and optimized App/Tests class maps, including subprocess autoload, pointed to baseline source; Reflection checked the provider path. Synthetic TestCase key defaults were supplied to child processes. The initial comparison-harness ticket-concurrency error from missing child-process key material was corrected without changing application/tests. Final baseline: **58 passed, 7 failed, 2 errors, 463 assertions, zero skips**, 27,576 ms. Baseline tracked/untracked state remained clean.

Machine comparison confirms the same nine test identities, failure/error categories, source line numbers and full messages in baseline and implementation. No Admin Board regression was found among these nine cases. D = `Mvp04dVerifiedCheckInTicketIssueTest`; E = `Mvp04eAdvanceQueueAdmissionTest`, both under `tests/Feature/Operator/`.

| Suite / exact test method | Implementation and baseline result |
|---|---|
| D: `test_verified_consent_confirmed_booking_is_checked_in_and_gets_one_private_ticket` | Identical failure: Null ticket (line 56) |
| D: `test_nonclinical_check_in_fails_closed_for_wrong_member_marker_and_contradictory_consent` | Identical failure: Expected arrived; actual checked_in (line 112) |
| D: `test_ticket_number_is_generated_when_the_operator_does_not_submit_one` | Identical failure: Null ticket (line 195) |
| D: `test_issue_replay_is_idempotent_changed_input_and_second_attempt_fail_closed` | Identical failure: Expected one ticket; found zero (line 313) |
| D: `test_database_rejects_duplicate_number_for_the_same_site_and_schedule` | Identical failure: Expected QueryException was not thrown (line 336) |
| E: `test_successful_ticket_issue_creates_one_private_advance_waiting_admission_and_history` | Identical failure: Null ticket (line 41) |
| E: `test_replays_and_competing_attempts_do_not_duplicate_admission_history_or_evidence` | Identical failure: Expected one ticket; found zero (line 87) |
| D: `test_reprint_is_a_distinct_idempotent_private_request_and_revocation_closes_print` | Identical error: Missing ticket route parameter (line 620) |
| E: `test_worklist_is_fifo_and_uses_admission_id_as_stable_tie_breaker` | Identical error: ready_at read on null (line 111) |

The timestamp problem and its consent/ticket/queue symptoms remain baseline debt. The nonclinical state-guard mismatch also remains baseline debt; its root cause was not repaired or conflated with the timestamp error. Controller, consent/attendance services, schema and original tests are unchanged from the pinned baseline. No test expectation, security requirement or schema was weakened. Comparison infrastructure and credentials are disposable and removed after capture.

[Gate E6](../../../.agents/software-workflow.md#gate-e6) explicitly permits failed individual checks and recorded limitations when implementation/evidence are sufficiently reviewable for V7. The task’s stop conditions were rechecked: the board needs no consent/schema repair to function, its own MySQL/HTTP/browser boundaries passed, and no new board-induced scope, authorization, audit or unsafe-environment blocker was established. The user explicitly reauthorized Draft publication with baseline debt visible. **Review Required** therefore describes this bounded implementation; it does not claim AC8 fully satisfied or resolve baseline clinical debt. Independent V7 decides the verdict and any required disposition; A9/G10 remain outstanding.

Changed/new PHP syntax checks, Pint `--test` on all 17 changed/new PHP files, `git diff --check`, unchanged governing task digest, stacked ancestry, bounded surface allowlist, protected-path preservation and high-confidence added-text credential/private-ID pattern checks passed. Repository intelligence reported metadata match/no recorded coverage issue for the relied source; direct source inspection supplied material conclusions. This is not a completeness claim for a derived index.

## Technical review, corrected failures and limits

Distinct read-only technical Reviewer `/root/implementation_review` inspected the governing contract and current source. Initial denied-audit selection metadata and X1 denied/unavailable presentation findings were corrected and reinspected. Final technical verdict: no remaining Important/Critical production findings and no material architecture conflict in the bounded change. It did not assess final immutable acceptance, run the tests or certify CI. The reviewer also identified an invalid revocation test whose grant change rolled back; the test was corrected to persist revocation in TransactionCommitted and its actual result is included above.

The initial MySQL disk-backed run was deliberately interrupted because repeated isolated schema rebuilds were slow; its partial execution is not counted as a pass. Only the owned container was recreated with temporary memory-backed storage, preserving MySQL transaction/SQL settings, and all selected checks were restarted. No crash-recovery/disk-persistence guarantee is claimed from that fixture.

The first complete MySQL selection ran 141 tests: 140 passed, with one failure because MySQL normalized JSON object key order. The permitted audit keys and values were correct. The test now compares the key whitelist without order; the complete selected MySQL command was rerun rather than counting the failed run as a pass.

Early browser runs failed because ignored Filament assets had not been materialized, the synthetic email did not match existing lowercase credential resolution, asynchronous filter updates needed waits, and selectors matched duplicate directory/action links or the Topbar Livewire component. A keyboard assertion was corrected to account for native date-input subfields, and a detail assertion was changed to the existing page’s visible service and URL rather than a reference that page does not render. These fixture/environment/selector issues were corrected using installed assets and existing login behavior. No dependency installation or credential resolver change was made. The initial migration rollback trait encountered a pre-existing indexed-column rollback limitation; new focused tests instead use existing `migrate:fresh` against owned isolated fixtures, without modifying migrations.

Existing Admin regression assertions changed from 246 to 244 because the old redirect assertions became successful board-response assertions; the same 27 tests still run. Full PHP regression was observed before final browser-only coverage additions and whitespace cleanup; production semantics were unchanged afterward. The portable audit-key assertion was subsequently corrected and the focused SQLite/MySQL checks were rerun.

No applicable operational read-audit retention duration was invented. Retention/access operationalization, external/legal/hospital/real-data obligations remain dependencies before production/release; APP-004 clinical retention does not silently govern these new operational read audits. Exact internal counts are approved for this bounded operational purpose, not external publication. Patient target and AI safety/provenance remain separate contracts.

Final protocol state is **Review Required**. Independent V7 assessment of the immutable implementation, explicit AC8/baseline-debt disposition, A9 acceptance and G10 release remain outstanding. No full MySQL suite, production/real-data test, crash-durability/throughput certification or release check is claimed. Installed-versus-locked package drift remains disclosed above. Local results are not CI; no workflow was dispatched. PR #8 remains unchanged; PR #9 stays Draft at the validated SHA and remote main remains the application baseline. Final implementation commit/PR/CI observations are supplied in the publication handoff.
