---
title: Centralized Study Worklist — Operational Monitoring
document_id: MHCS-TASK-CENTRALIZED-STUDY-WORKLIST-001
version: 1.0
status: draft
language: en-US
last_updated: 2026-10-13
scope:
  - managed-facility session radiography intake monitoring
  - minimized domain-owned operational reads
authority_note: Draft reconnaissance contract; no implementation authority. Independent T5 validation and the scoped disclosure decisions below are required before execution.
---

# Executable Task

## Task identity

**Task title:** `Centralized Study Worklist — Operational Monitoring`

**Task path:** `.agents/tasks/centralized-study-worklist.md`

**Task contract state:** `Draft — TASK REVIEW REQUIRED`

**Delivery objective / Work Package / MVP:** First functional study-monitoring slice under MVP-GAP-027; Member/Operator intake association and Image Gateway processing visibility. No broader Work Package completion.

**Owner / designated planning authority:** Product Owner and internal technical/security/privacy authorities for the disclosure decisions; independent Planner/Reviewer for T5 and subsequent V7/A9. Clinical authorities retain clinical policy ownership.

## Delivery context and baseline

The direct human handoff “MHCS — Plan Centralized Study Worklist” authorizes reconnaissance and one task-only branch, ordinary commit and push for independent review. It excludes application implementation in this run and requires independent T5 before implementation.

**Implementation baseline:** `c63577f2f677998ebbcc88ff0f407db028ffb539`.

Observed local immutable commit and remote `main` agree on 2026-10-13. This integrates PR #10's Admin board and PR #11's Operator MySQL fixture correction. The [board acceptance record](../../docs/mvp/evidence/admin-facility-session-operations-acceptance.md) remains the bounded development acceptance of application revision `1ec07b154d1c7db3cd4fe389f3219091ea8ca595`; this task neither reaccepts that slice nor certifies the fixture correction. Repository context/roadmap still contain older pending-board wording: use immutable source and the acceptance record for current reality, without rewriting historical documents here. No dependent board implementation is awaiting this task.

**Task revision:** Exact full Git commit containing this Draft, supplied in the review handoff. On validation, resolve the exact published task-content revision separately from the implementation baseline; never execute from a branch name alone.

## Objective

Provide an authenticated, read-only Admin worklist for existing radiography intakes in one selected managed-facility session, including intakes awaiting capture, and their persisted capture, processing, DICOM-record and AI-job progress. Reuse the Admin board's facility/local-date/session selection patterns. A session selection bounds the first useful slice; this is not a universal patient/examination registry or an all-time cross-facility study search.

## Authoritative inputs and traceability

- [Repository contract](../AGENTS.md), [delivery protocol](../software-workflow.md), [task convention](_template.md); [project context](../context/project.md) is supporting orientation.
- Direct handoff above → objective, read-only/exclusion boundaries, Draft publication and independent validation prerequisite.
- [Active scope](../../docs/mvp/beta-scope.md#admin-first-operational-scope) and [roadmap](../../docs/mvp/roadmap.md#admin-first-planning-sequence) → centralized intake/processing target over existing owners.
- [MVP-DEC-044–046, 048–049, 053](../../docs/mvp/decision-log.md) → Admin priority, distinct AI/clinical/publication concepts, managed facilities and local session-start date, existing board read safeguards. DEC-053 approves the specified board aggregates/internal integrity checks; it does **not** approve examination-level disclosure or extend Operator content permissions.
- [MVP-GAP-027/028/029/031/035/036](../../docs/mvp/beta-gap-register.md) → broader transition, staff credentials, content/finalization/privacy, audit operationalization and AI safety/provenance remain open. AC1–AC8 address only this operational slice.
- `FIELD-OPS-007/008/009` in [field-operations contract](urgent-operator-field-operations.md) and [Grabber ingestion contract](../../docs/mpips/mhcs-grabber-dicom-ingestion-contract.md) → preserve authenticated direct-DICOM association and legacy capture/viewing.
- [MPIPS contract](../../docs/mpips/mhcs-dicom-api.md) and [queued capture contract](archive/operator-async-capture-status-and-worklist-sync.md) → MHCS queue/retry ownership; MPIPS synchronous conversion is not an MPIPS waiting queue. Existing integration obligations are preservation references, not new Admin disclosure authority.
- `INT-DUAL-001` / `INT-ASYNC-001` in [Operator AI integration contract](operator-ai-pacs-integration.md) → preserve both ingestion paths and nonblocking AI dispatch; neither extends Admin content access.

## Source-backed reconnaissance and actual reuse

Paths below were inspected at the implementation baseline; code existence is not a fresh runtime test result.

| Existing surface | Reuse and limitation |
|---|---|
| `app/Providers/Filament/Pages/AdminFacilitySessionOperations.php`, `app/Providers/Filament/AdminPanelProvider.php` | Existing authenticated Admin landing page, facility/date filters, pagination, manual refresh and fresh render-time withholding. Add an authorized worklist entry/page; preserve `/admin` board and existing resources. |
| `app/Modules/Member/Application/Services/AdminSessionBoardRead.php`, `AdminArrivalReferenceIntegrity.php`; `app/Modules/Operator/Application/Services/AdminSessionArrivalRead.php` | Persisted facility timezone, half-open UTC session-start date, 25-session pages and trusted stable-site/organization/timezone/version/active mapping. Existing Member integrity read checks booking–schedule–facility tuples without patient summaries. Reuse technique through a specifically scoped examination-association contract; do not relabel the arrival contract or fetch Member internals from another domain. |
| `app/Modules/Operator/Application/Services/OperatorAttendanceService.php`, `OperatorWorklistService.php` | Attendance already uses Member and Gateway contracts; examination/readiness lists use persisted tickets/admissions and assignment-scoped Operator access. These operational services also contain mutations and identity-rich projections: do not call them as an Admin or synthesize an Operator profile/shift assignment. |
| `app/Modules/ImageGateway/Application/Contracts/OperatorStudyQuery.php`, `Application/Services/ImageGatewayCaptureService.php` under the same module | Existing study contract is consumed by attendance; controller results use the service directly. `studies()` supplies patient name/MRN and internal IDs, reads report records and assumes Operator site/assignment scope. `processingState()` maps retrying to processing and prioritizes an existing study. Neither is an Admin authorization boundary nor a complete intake list. Preserve both and add a minimized Gateway-owned read contract. |
| `app/Modules/ImageGateway/Application/Jobs/ProcessCaptureSet.php` | Durable legacy source acceptance queues conversion; worker persists `processing`, `retrying`, `completed`, `failed` and the returned study, then contains AI-dispatch failure. Read existing records; never invoke conversion, repair leases or dispatch jobs from monitoring. |
| `app/Modules/Operator/Application/Services/GrabberDicomIngestionService.php` | Direct upload also persists accepted capture and study linked to admission/booking/session/site, with completed processing and successful DICOM state. It does not require successful radiograph/gain NPZ components. Do not apply the legacy missing-source check to direct DICOM. Preserve API validation/idempotency/queue/locator behavior. |
| `app/Modules/ImageGateway/Domain/AiJobStatus.php`, `Application/Services/ImageGatewayAiService.php` under the same module | Persisted AI states exist; `getStatus()` alone does not validate Admin scope and returns internal/error/correlation details. Reuse state constants and read only minimized job status after owner validation. Do not reuse report access/retry/dispatch methods. |
| `app/Shared/Authorization/AdminOperationalRead.php`, `app/Modules/Member/Application/Services/MemberAuthorization.php`, `app/Modules/Operator/Application/Services/OperatorAuthorization.php` | Existing fresh account/panel/exact-capability checks and available/denied/unavailable envelope; read audit commits before disclosure, followed by fresh authorization. Reuse without making Shared depend on modules. Existing `operator.audit.read` is not automatically permission to disclose Gateway statuses. |
| `app/Shared/Audit/AuditEvent.php`, `app/Shared/Security/SensitiveDataSanitizer.php` | Attributable actor/session/correlation and sanitized metadata. Reuse facility/session-scoped audit; never log row identities, findings, private keys/URLs or source exception contents. |
| `app/Modules/Member/Domain/Models/LocalImagingOrder.php`, `Application/Services/Mvp03BookingService.php` under the same module | Existing authored booking-owned orders are not a universal finalized examination model. First slice starts at existing X-ray admissions, not every authored order or booking; no new canonical identity or state inference. |

Gateway capture/study/AI migrations already provide the associations and persisted states. Individual foreign keys do not establish consistency of a multi-column booking/session/site tuple. No schema change is needed for this proposal; owner reads must validate the full chain.

Genuinely missing: the Admin operational page, minimized Operator intake and Gateway status contracts, bounded Member association validation for those reads, their explicit disclosure disposition, and verification of those new boundaries. No replacement ingestion system, status machine, queue, storage service or clinical workflow is needed.

## Scope and execution constraints

### In scope

- One Admin page linked from the existing board/navigation; facility → facility-local session-start date → one verified persisted session → at most 25 intake rows per page, stable ordering by admission creation time then ID, manual refresh and previous/next navigation. Use existing session-directory pagination to reach later sessions. Changes of scope reset intake pagination; do not silently retain another facility/session's rows.
- Operator owns the intake read: existing `stage=xray` admissions across their persisted states, including `awaiting_ai`, with ticket reference and validated booking/session/site tuple. Do not filter on current booking `arrived` or an active assigned Operator; historical authorized monitoring must not disappear after intake progresses. One row per admission; no invented universal examination identifier. Present the existing ticket reference qualified by the selected session reference, plus an existing study display reference when associated.
- Member owns trusted facility/schedule/booking validation; Operator owns admission/ticket/site validation; Gateway owns capture/study/job validation and status projection. Cross-module reads use narrow `Application/Contracts` interfaces and provider bindings. Admin composes safe outputs only; neither presentation nor Shared joins domain tables. No new cross-domain internal-class dependencies or copies of patient projections.
- Internal tuples may contain necessary UUIDs for correlation, passed only between owner reads. Require each owner read to revalidate caller and requested persisted scope; browser-supplied tuples/context/permissions are not trusted. Minimized tuples stay out of public Livewire properties/snapshots. No owner uses another module's tables as a shortcut; validate through its contract.
- Whitelist public rows to ticket/session reference, study display reference if present, capture receipt state, recorded processing state, DICOM-record availability and recorded AI state. Facility label/timezone, service/session reference/times and observation time are allowed context. Escape dynamic labels/references. No names, NIK, MRN, DOB, gender, booking/member/admission/capture/job UUIDs, locator codes, private object keys, checksums, DICOM UIDs, filenames, PACS IDs, findings, URLs or provider errors in the page/payload/logs. Facility/session selector IDs remain allowed locators and never authorize access.

### Read semantics (presentation only; no transitions)

| Persisted evidence | Required operational meaning |
|---|---|
| Valid X-ray intake, no capture | Capture not yet received; processing and AI not yet applicable. Do not claim that a job is queued. |
| Nonaccepted capture / incomplete legacy receipt | Receipt incomplete, not durably accepted; do not infer source success or processing completion. |
| Capture `status=accepted` with `accepted_at` | Capture received. Direct-DICOM receipt does not depend on NPZ flags. |
| Accepted capture `processing_status=pending` | Awaiting worker / queued for processing under the existing after-commit dispatch flow; this is not proof of queue-backend health. `pending` is the persisted value, not a new `queued` transition. |
| `processing` / `retrying` | Processing in progress / retry pending, preserving the distinction through localized labels. |
| `completed` / `failed` | Recorded processing completed / failed, independently from DICOM and AI. Do not infer clinical completion or silently convert unknown values to queued. |
| Valid associated `image_gateway_studies` row of DICOM format | DICOM record available; no assertion that object storage is currently reachable, no viewer/download/grant action. No study means no DICOM record only after a successful scoped read. |
| No AI job for a valid study | AI not queued (recorded absence), not AI-negative, screening-complete or clinical-finalized. |
| AI job `queued`, `processing`, `retryable_failure`, `terminal_failure`, `report_ready` | Recorded AI job state only. Render `report_ready` as output marked ready but unverified; always explain AI processing is not radiologist approval or patient publication. Do not query AI report contents/metadata or require PDF access to show job state. |
| Unknown status, inconsistent tuple, malformed linkage or owner read failure | Withhold the affected projection as unavailable; never return guessed normality, an empty successful list or a clinical conclusion. Revoked/unverified authorization is denied, without diagnosing an outage. |

### Out of scope and preserved behavior

No report authoring/signoff/amendment, escalation action, diagnosis, findings/default-normal text, AI PDF/preview, patient publication/link, image access, retry/cancel/requeue, credential/grant changes, new staff workspace, Patient migration, new external/unassociated intake, import, schema, dependency, runtime/CI changes, production access or deployment. No export, polling, global search, new filters/status counts or notification system in the first slice.

Preserve Member registration/booking/capacity/consent/history, board/X1 acceptance, Operator assignments/attendance/queue/locators and current portals, direct-DICOM and legacy NPZ/MPIPS contracts, private storage and existing authorized DICOM/AI routes. Existing AI safety/provenance defects remain separate; no hidden repair or broader acceptance. Domain and outbox rows are unchanged by every worklist request; only necessary operational read audits may be written.

## Authorization, disclosure decisions and dependencies

**Proposed minimum gate (unapproved extension):** fresh eligible Admin with `member.admin.access`, `member.schedule.read`, `member.booking.read` and `operator.audit.read`, verified through existing owning authorization mechanisms, for all intake/status rows. Schedule directory retains its existing schedule-read boundary; lacking any row capability returns denied without executing protected intake/Gateway queries. All required gates apply to every owner read, initial HTTP load, Livewire action/render, filter, page and refresh, and immediately before public serialization. Panel access alone, manage-only capabilities, role name, selected facility or knowledge of a reference is insufficient. No new permissions/assignments are created.

Requiring booking-read for row linkage is deliberate minimization relative to reusing identity-rich Operator paths, but it does not itself supply authority. Before T5, obtain an attributable internal Product/technical/security/privacy disposition on:

1. **SW-D1 — Disclosure and existing-capability use:** approve or reject the exact gate above for minimized per-intake references, capture/DICOM existence and AI-job states across trusted managed facilities, including inactive historical facilities. Confirm the single-session admission boundary and all-or-none row gate. If approved, implement those exact gates; if rejected or a new grant is required, return to planning rather than provisioning it. Board aggregate approval is insufficient.
2. **SW-D2 — Integrity and read-audit boundary:** approve the owner-contract tuple validation, public field whitelist, unavailable/denied withholding, and minimized attributable facility/session/date/purpose/outcome audit committed before disclosure. Confirm synthetic-development separation from still-unresolved operational audit retention/access and real-data/release policy. Ticket/study references can still reveal participation and must be assessed explicitly; no invented anonymization or small-count exception.

These are the material unresolved decisions for this operational slice; independent T5 is not a substitute for designated-authority approval. Clinical finalization, clinical content access, recipient rules, temporary credentials and result publication remain independently deferred under GAP-028/029. GAP-035/036 do not block a status-only synthetic slice if SW-D1/D2 and T5 confirm isolation: no report generator/report table read/PDF access or claim of AI correctness/facility provenance. Retention, hospital/legal/privacy and real-data/G10 approval remain separate before any operational use with real records.

**Required capabilities after validation:** scoped repository writes, installed PHP/Filament/Livewire runtime, synthetic test fixtures, owned disposable SQLite and MySQL 8.4, available Pest/Chromium browser tooling. No external imaging provider, storage account, clinical system, production database or credentials are required. Consult current official framework documentation if implementation materially depends on uncertain/version-sensitive API behavior; do not change dependencies to bypass runtime limits.

## Acceptance criteria

- [ ] **AC1 — Useful bounded journey:** eligible Admin reaches the worklist from the retained board; selects verified facility/local date/session, sees existing X-ray intake references including no-capture and progressed cases, pages beyond 25 rows and refreshes changed persisted status. Valid empty selection is distinct from denied/unavailable. No rows from other facilities/sessions; board and existing navigation remain functional. Traces to handoff, GAP-027, SW-D1.
- [ ] **AC2 — Honest progress:** synthetic direct-DICOM and legacy records exercise every receipt/processing/AI state above, missing job/study and unknown/inconsistent cases. Direct DICOM is completed/available despite absent NPZ success flags; processing failure cannot masquerade as complete. AI-ready remains explicitly unverified and never means screening, doctor signoff or publication. Traces to handoff, DEC-046, FIELD-OPS-009, SW-D1/D2.
- [ ] **AC3 — Owner integrity:** contracts validate facility stable/local mapping, session membership, booking snapshot/schedule and ticket/admission/capture/study/job consistency without cross-module table reads or identity-rich methods. Corrupt/cross-facility tuples, orphaned/duplicate associations and stale mappings withhold affected results. Stable pagination does not duplicate rows on unchanged data. Traces to module ownership and SW-D2.
- [ ] **AC4 — Exact fresh authorization:** guest/Member/Doctor/Operator and panel-only/manage-only/missing-capability callers obtain no rows; protected queries do not run. Forged URL, Livewire scope/page/context, deactivation and permission/role revocation fail safely through real HTTP/Livewire requests. No new disclosure after revocation, including internal tuple exposure in snapshots or service bypass. Traces to SW-D1 and existing read safeguards.
- [ ] **AC5 — Disclosure isolation:** HTML, JSON, Livewire state, audit and logs contain only approved fields; negative fixtures carry sentinel identifiers/findings/keys/URLs and prove absence. Page executes no AI-report-table read, private-object access, download/grant, remote call or worker dispatch. Labels cannot inject markup. Traces to exclusions, GAP-029/035/036, SW-D2.
- [ ] **AC6 — Audit and failure:** attributable minimized successful read audit commits before values return; append/source/commit failure, nested transaction and authorization-source uncertainty withhold safely. Rejected reads do not record forged target IDs or private payloads. Repeated filter/page/refresh changes only allowed audit rows, never domain/outbox/jobs. Traces to SW-D2 and read-only objective.
- [ ] **AC7 — Operational presentation:** all authored visible copy comes from `lang/id.json`; Indonesian receipt/status/AI warning, labeled keyboard-operable controls and observation time are clear. Dates use verified facility timezone and session-start membership, including midnight, overnight, DST and invalid timezone cases; no ambient browser/server timezone fallback. Traces to DEC-037/049 and handoff.
- [ ] **AC8 — Reviewable evidence and preservation:** SQLite, scoped MySQL, HTTP/Livewire, browser and architecture/security evidence below is observed and tied to exact revisions; no new ingestion/board/Operator/AI regressions. Pre-existing failures/skips are identified with unchanged-baseline comparison, never suppressed or accepted by this task. Traces to repository verification contract and FIELD-OPS-007/008/009.

## Verification requirements and known limitations

Use existing `tests/Support/AdminOperationsFixtures.php`, `tests/Feature/Admin/AdminFacilitySessionOperationsTest.php`, `tests/Browser/AdminFacilitySessionOperationsTest.php` and Operator/Gateway synthetic fixture patterns. Add focused worklist contract/HTTP/Livewire tests and one browser journey; keep secrets and diagnostic harnesses ephemeral. Safely construct malformed relation fixtures without weakening production constraints. Never use ambient application DB settings or real rosters.

- **SQLite:** `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test` with explicit focused worklist test paths; rerun `tests/Feature/Admin`, `tests/Architecture`, `tests/Security`, plus `tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php`, `OperatorFieldOperationsSlice3Test.php`, `OperatorFieldOperationsSlice4Test.php`, `OperatorAiPacsIntegrationTest.php` and `tests/ImageGateway/ImageGatewayAiDispatchTest.php`. Add other touched/shared-fixture consumers only as needed. Preserve legitimate backend-only skips and report identities/reasons.
- **MySQL:** run the complete new worklist/board scopes and affected ingestion/AI regressions on owned disposable MySQL 8.4 using explicitly isolated testing configuration and `--fail-on-skipped`. Reuse isolation from `deployment/verify-mysql.sh`; its entire full-suite/migration-probe script is not required for a schema-free read slice. Verify actual schema/types, SQL/date/pagination behavior, tuple rejection and audit rollback/commit failure on this backend. No SQLite substitution. Use compatible synthetic clocks from the merged fixture correction; do not revive 2040 consent TIMESTAMP failures or weaken expectations. Record version/image, setup, command, exit status/counts; compare any failure at the unchanged baseline before classification.
- **HTTP/Livewire/security:** exercise actual initial routes and update requests, not only direct calls or mocked permission arrays. Query tracing proves no protected reads when denied, bounded page/batched owner queries without per-row growth, no report/identity/private-object reads and audit-only database deltas. Include fresh revocation between owner reads and serialization, forged scope, invalid dates/page values, mapping changes, SQL/source outage and audit append/commit failures. Do not run successful disclosure inside a test transaction when the read gate rejects nested transactions; use the established board test pattern.
- **Browser:** `vendor/bin/pest tests/Browser/<worklist-test>.php tests/Browser/AdminFacilitySessionOperationsTest.php` against isolated file-backed synthetic SQLite and available Chromium/Pest tooling. Verify actual login/navigation/filter/session/page/refresh, denied/unavailable states, revocation on update, localized AI warning, keyboard focus and logout/direct-route protection. `tests/Browser` is outside the default `phpunit.xml` suites and must run explicitly. If unavailable, report the exact environment failure and required manual checklist; independent Reviewer must disposition the evidence gap before acceptance. Historical DEC-039's Operator browser exception is not a blanket worklist waiver.
- **Static/preservation:** changed PHP syntax, focused Pint, `git diff --check`, existing architecture/security suites and contract dependency inspection. Diff must contain no schema/config/lock/grant changes or clinical/ingestion mutations. Record installed versus locked versions rather than claiming lock-aligned verification.

Planning verification here is source/Git/reference inspection only: no application, database, browser or CI suite was run. Graphify discovery was truncated and its freshness was not established; Codebase Memory was ready but its caller trace missed observed service/controller consumers. Direct source reads are the evidence basis. Neither graph proves absence or completeness.

Historical board evidence reports Filament/Commonmark installed-versus-locked drift, no CI checks at its accepted SHA, no full MySQL run and bounded Operator baseline failures; these are dated limits, not a current-baseline result. PR #11 changes fixtures but merge alone proves no test pass. `security-validation.yml` is manual `workflow_dispatch` and does not explicitly run the browser suite; this task-only push creates no assumed CI evidence. No workflow dispatch or production workflow is authorized. The Executor reports actual checks/statuses when available, all unsuccessful test identities, skips, browser/runtime gaps and any necessary independent disposition.

## Stop conditions

Stop and return to Planner/Reviewer if SW-D1/D2 or independent exact-revision T5 is missing; baseline changes require reconciliation; trusted mapping/association cannot be validated through owner contracts; required gates require a new grant or impersonation; disclosure requires identity/clinical/PDF fields; status semantics require new transitions; existing schema cannot support the bounded read; verification requires production/external credentials or unauthorized installation; security/privacy/clinical ambiguity or material failure cannot be resolved within this scope. Never silently expand to all bookings, external studies, object access or a Patient migration to satisfy acceptance.

## Side-effect authorization and terminal outcome

**This run:** only this Draft task on `task/centralized-study-worklist-planning`, ordinary commit and push for independent review, as explicitly authorized by the human handoff. No separate planning PR, duplicate documentation, application edits, grant provisioning, merge, deployment or production/external clinical operation.

**Future execution:** this Draft grants no implementation or publication permissions. After designated SW-D1/D2 disposition and independent T5, publish a validated exact revision and record explicit bounded source/test/synthetic-environment and any commit/push/PR permissions before execution. G10/real-data approval remains separate.

**Expected terminal outcome now:** `TASK REVIEW REQUIRED`, exact Draft path/commit/branch; wait for independent T5. No Executor launcher. Future implementation ends `Review Required` with exact governing task/baseline/implementation revisions, changed files, observed commands/results and gaps, or `Planning Required` at a stop condition. Executor cannot self-declare acceptance. Bounded review corrections update this same stable task path and republish; materially different authority/objective returns to planning.
