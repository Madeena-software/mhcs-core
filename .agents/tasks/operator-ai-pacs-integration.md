---
title: MHCS Core Operator–AI PACS Integration
document_id: MHCS-TASK-OPERATOR-AI-PACS-INTEGRATION-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-09-06
scope:
  - Ancestry-preserving merge of completed AI PACS candidate into urgent Operator field-operations workstream
  - Dual-source AI job triggering (legacy NPZ -> ProcessCaptureSet and additive DDR Grabber DICOM ingestion)
  - Strict preservation of non-blocking asynchronous radiography capture progression
  - Operator results worklist UI integration with state-aware AI status and medical disclaimer
  - Authorized private-storage streaming for derived Indonesian/MHCS PDF (Unduh Laporan AI)
  - Immutability and provenance tracking for original and derived report artifacts
  - Operator-authorized retry mechanics without duplicate dispatch or state corruption
  - Comprehensive regression verification across all Operator field operations and legacy imaging pipelines
authority_note: This task authorizes bounded repository integration of the completed AI PACS candidate into the urgent Operator workstream without rebasing, squashing, or rewriting published history. AI outputs are screening aids only and must never be represented as doctor-finalized or clinically verified medical reports.
---

# Executable Task

This file defines a bounded software-delivery contract for implementation.

A validated task MUST provide enough authority, scope, acceptance, verification, and stop-condition information for an Executor to proceed without inventing material product, requirement, architecture, scope, or approval decisions.

A task is not a generic coding recipe. Implementation technique remains the Executor's responsibility within the constraints established here.

## Task identity

**Task title:**
`MHCS Core Operator–AI PACS Integration`

**Task path:**
`.agents/tasks/operator-ai-pacs-integration.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content.`

The task file is the executable delivery contract.

Execution and review lifecycle states such as `In Execution`, `Review Required`, `Remediation Required`, and `Accepted` SHOULD normally be tracked by orchestration, review records, repository metadata, or another mechanism that preserves the exact governing task revision.

A lifecycle-status update MUST NOT silently replace the immutable task revision that governed an execution attempt.

When remediation materially changes this executable contract, edit the same stable task path, return it to Draft as needed, and republish it as a new immutable governing task revision before renewed execution.

**Delivery objective / Work Package / MVP:**
`Urgent Operator Field Operations & AI PACS Integration Delivery Contract`

**Owner / designated planning authority:**
`Faliq Adlan, Human Owner / Planner-Reviewer under Interim Authority Snapshot and Product Authority`

## Delivery context

Two critical workstreams in `mhcs-core` have achieved independent candidate completion from the common `main` lineage (`cb61e62aaf2ad4bd59b142633d8d53c482dabcba`):

1. **Urgent Operator Field Operations (`task/urgent-operator-field-operations` @ `5a3626b1d5e2624ec7818ca88545e36d320f0294`):**
   Completed Slices 1–4, establishing operator shift autonomy, walk-in member registration with civil NIK deduplication, informed consent reuse, basic examination bypass, 4-digit radiography session locators, additive Grabber DICOM ingestion, 57-mm thermal ticket printing, and full legacy NPZ pipeline preservation.
2. **AI PACS Integration (`task/ai-pacs-integration` @ `4e716a34505fac810f47b5b24b4bbcb348105b8f`):**
   Completed Slices 1–3, establishing external Yizhun AI PACS client integration, authenticated browser report downloading via Playwright, derived Indonesian/MHCS PDF report generation (`05_final_indonesia_v10` standard via mPDF), private storage persistence, immutable provenance tracking, and asynchronous queue worker dispatch.

The urgent field-operations mission now requires connecting the completed AI PACS candidate into the Operator workstream so that screening field units can access state-aware AI analysis status and download derived Indonesian/MHCS screening report PDFs directly from their operational results interface.

This integration must unify both lineages while strictly honoring core clinical and technical boundaries:
- **Ancestry Preservation:** Both published branches have diverged from `main`; the integration must use a normal merge commit without rebasing, squashing, or rewriting published history.
- **Dual DICOM Source Support:** Radiography studies originating from either legacy NPZ processing (`ProcessCaptureSet`) or additive Grabber DICOM ingestion (`GrabberDicomIngestionService`) must seamlessly trigger asynchronous AI evaluation.
- **Asynchronous Isolation:** Capture completion, DICOM availability, and operator examination flow must never be blocked or delayed by AI PACS latency, network timeouts, or processing errors.
- **In-Place Operator UI:** AI status and report actions must be embedded directly in the existing Operator DICOM results interface (adjacent to `[Lihat DICOM]`), not in a separate dashboard.
- **Clinical Safety Disclaimer:** AI outputs are screening aids only. Every user-facing AI action must display the prominent disclaimer: `Keluaran AI — belum diverifikasi tenaga medis.` AI outputs must never be presented as doctor-verified or final clinical diagnostic reports.
- **Secure Authorized Streaming:** Derived PDFs stored in private object storage must be streamed through an authorized, site-scoped Operator endpoint without ever exposing public URLs or raw object keys.

## Baseline and task revision

**Implementation baseline:**
`5a3626b1d5e2624ec7818ca88545e36d320f0294` (the validated urgent Operator candidate on branch `task/urgent-operator-field-operations`)

**Integration source candidate:**
`4e716a34505fac810f47b5b24b4bbcb348105b8f` (the validated AI PACS candidate on branch `task/ai-pacs-integration`)

**Common ancestor:**
`cb61e62aaf2ad4bd59b142633d8d53c482dabcba` (`origin/main`)

**Task revision:**
The full SHA of the commit containing this exact task content on the dedicated integration task branch.

The implementation baseline is the verified repository revision from which execution begins. The task revision is the exact immutable content identity governing execution and must be resolvable before execution handoff.

The implementation baseline and governing task revision are separate references. Do not change the implementation baseline silently during execution.

## Objective

Authorizes a later Executor to integrate the completed AI PACS candidate history (`4e716a34505fac810f47b5b24b4bbcb348105b8f`) into the urgent Operator baseline (`5a3626b1d5e2624ec7818ca88545e36d320f0294`) via an ancestry-preserving merge; resolve architecture and test conflicts; connect asynchronous AI PACS processing to DICOM studies produced by both legacy NPZ conversion and direct Grabber ingestion; integrate state-aware AI status presentation and authorized Indonesian derived PDF downloading (`Unduh Laporan AI`) with mandatory medical disclaimers into the existing Operator results UI; enforce private-storage streaming authorization and audit logging; maintain immutability and provenance; provide an operator-authorized retry mechanism without duplicating jobs; and verify zero regression across all urgent Operator capabilities and legacy imaging pipelines.

## Authoritative inputs

### Governing authority

1. **Interim Human Authority Snapshot (Urgent Field Operations):**
   - Authorizes field shift autonomy, on-the-spot walk-in registration, reusable master informed consent, basic examination bypass with zero earning, 4-digit radiography locators, additive Grabber DICOM ingestion, and thermal ticket printing.
   - Mandates strict preservation of legacy NPZ upload and MPIPS conversion workflows.
2. **Product Authority (`Madeena-software/mhcs-business-docs` @ `645058e431f59c4450a136e72f140e6819b79f32`):**
   - `docs/business/01-business-overview.md` — Screening pathways, actor roles, and operator responsibilities.
   - `docs/business/02-user-stories.md` — Operator worklist interaction, study viewing, and screening result delivery.
   - `docs/business/03-system-responsibilities.md` — Image Gateway ownership of private durable image storage, atomic acceptance, queued processing, AI integration, audit; Doctor Core ownership of final clinical diagnostic reports and doctor earnings; strict separation of AI screening output from doctor-finalized diagnostic reports.
3. **Governing Workstream Tasks:**
   - `.agents/tasks/urgent-operator-field-operations.md` @ `5a3626b1d5e2624ec7818ca88545e36d320f0294`
   - `.agents/tasks/ai-pacs-integration.md` @ `4e716a34505fac810f47b5b24b4bbcb348105b8f`
4. **Canonical Delivery Framework:**
   - `.agents/AGENTS.md` and `.agents/software-workflow.md` — Delivery protocol, gate definitions, evidence obligations, side-effect boundaries.
   - `.agents/context/project.md` — Repository orientation, architectural boundaries, actor responsibilities.
5. **Integration & Data Contracts:**
   - `docs/mpips/mhcs-grabber-dicom-ingestion-contract.md` — Grabber DICOM upload specification.
   - `docs/mpips/examples/mhcs-dicom-manifest.minimal.example.json` — Minimal manifest contract.
   - Google Drive folder `05_final_indonesia_v10` — Agreed visual, structural, and textual design reference for the derived Indonesian MHCS screening report PDF.

### Requirement traceability

- `INT-MERGE-001` → Ancestry-Preserving Merge: Integrate `4e716a34505fac810f47b5b24b4bbcb348105b8f` into `5a3626b1d5e2624ec7818ca88545e36d320f0294` with a standard merge commit without rewriting history.
- `INT-DUAL-001` → Dual-Path AI Dispatch: Connect asynchronous AI evaluation to studies from both legacy NPZ (`ProcessCaptureSet`) and direct Grabber DICOM (`GrabberDicomIngestionService`).
- `INT-ASYNC-001` → Non-Blocking Asynchronous Processing: Radiography capture completion and operator workflow progression must never wait for AI PACS processing or fail on AI timeout.
- `INT-UI-001` → Integrated Operator Results Worklist: Embed AI status and report access into the existing Operator DICOM-results UI adjacent to `[Lihat DICOM]`.
- `INT-UI-002` → State-Aware Presentation: Display granular, non-misleading status (`AI not queued`, `queued`, `processing`, `report ready`, `failed`, `unavailable`) rather than misleading buttons.
- `INT-DISC-001` → Mandatory Medical Disclaimer: Display `Keluaran AI — belum diverifikasi tenaga medis.` prominently adjacent to AI actions; never present AI output as a verified clinical report.
- `INT-AUTH-001` → Authorized Private PDF Streaming: Stream derived PDFs through an authenticated, site-authorized Operator action; forbid public URLs; audit downloads without logging PHI.
- `INT-PROV-001` → Provenance & Immutability: Store original AI PACS PDF and derived Indonesian/MHCS PDF immutably in `PrivateObjectStore` with verifiable provenance.
- `INT-FAIL-001` → Safe Failure & Idempotent Retry: Ensure AI failure does not delete DICOM or alter radiography completion; provide operator-authorized retry without duplicate jobs.
- `INT-PRESERVE-001` → Complete Capability Preservation: All urgent Operator capabilities (Slices 1–4), legacy NPZ processing, DICOM viewer, and clinical boundaries remain fully operational.

## Scope

The task scope defines the coherent integration delivery objective and acceptance boundary.

### In scope

1. **Ancestry-Preserving Branch Merge & Conflict Resolution:**
   - Execute a git merge of `task/ai-pacs-integration` (`4e716a34505fac810f47b5b24b4bbcb348105b8f`) into the validated urgent Operator candidate (`5a3626b1d5e2624ec7818ca88545e36d320f0294`).
   - Resolve merge conflicts cleanly:
     - `tests/Architecture/FoundationArchitectureTest.php`: Combine the migration allowlists from both branches and include `AiPacsClient.php` in the network client allowlist.
     - Ensure all database migration timestamps and dependencies order logically and execute without collision.
2. **Dual-Source Asynchronous AI Dispatch:**
   - Connect `ImageGatewayAiServiceContract` / `ProcessAiPacsStudy` triggering to DICOM studies produced by:
     - **Path A (Legacy NPZ):** `ProcessCaptureSet` completion where a valid DICOM study is registered.
     - **Path B (Additive DDR DICOM):** `GrabberDicomIngestionService` upon successful DICOM study validation and storage.
   - Guarantee asynchronous dispatch: DICOM ingestion and radiography capture set completion return immediately without waiting for AI PACS HTTP communication or report generation.
3. **Operator Results Worklist UI Integration:**
   - Enhance the existing Operator radiography/DICOM results interface (e.g., `resources/views/operator/xray-capture.blade.php`, `xray-readiness-worklist.blade.php`, and associated portal views).
   - Add a dedicated `Laporan AI` column adjacent to the existing `DICOM` (`[Lihat DICOM]`) action.
   - Implement state-aware status rendering:
     - **AI not queued:** Render neutral status (e.g. `Belum Masuk Antrean`).
     - **Queued:** Render informative queued badge (e.g. `Menunggu Antrean AI`).
     - **Processing:** Render in-progress badge (e.g. `Sedang Dianalisis AI`).
     - **Report ready:** Render authorized action button `[Unduh Laporan AI]`.
     - **Failed with actionable status/retry:** Render failure badge with operator retry action if retryable (e.g. `Analisis Gagal — Coba Lagi`), or terminal failure notice.
     - **Unavailable:** Render clear unavailable notice when AI processing is not configured or study is ineligible.
4. **Mandatory Medical Disclaimer:**
   - Prominently render the exact required disclaimer adjacent to the AI report action:
     `Keluaran AI — belum diverifikasi tenaga medis.`
   - Ensure the UI explicitly communicates that AI outputs are screening aids and not doctor-verified diagnostic reports.
5. **Authorized Private-Storage PDF Streaming Action:**
   - Implement an authorized controller action (e.g. `OperatorAiReportController@downloadDerivedReport`) accessible strictly to authenticated Operators.
   - Enforce object-level authorization: Operator must be assigned to the operational site and shift governing the admission and study.
   - Stream the derived Indonesian/MHCS PDF from `PrivateObjectStore` via binary response with appropriate MIME type (`application/pdf`) and filename header.
   - Never generate or expose public URLs, pre-signed URLs, or raw storage paths.
   - Audit every download event in `AuditStore` (recording operator ID, study ID, timestamp, and IP address) without logging PDF payload bytes or patient PHI.
6. **Provenance & Immutability Invariant:**
   - Verify that the original AI PACS PDF and derived Indonesian/MHCS PDF remain stored immutably in `PrivateObjectStore`.
   - Maintain explicit database foreign keys and provenance links between the study, AI job, original PDF object key, and derived PDF object key.
7. **Failure Containment & Operator Retry:**
   - Ensure that AI PACS timeouts, calculation errors, or downloader failures:
     - Never delete or hide the DICOM study.
     - Never mark the radiography capture set falsely completed or failed.
     - Never prevent the Operator from opening and inspecting the DICOM study via `[Lihat DICOM]`.
     - Never mark an admission falsely completed in the clinical sense.
   - Provide an operator-authorized retry button/action for retryable failures that dispatches a new attempt via `ImageGatewayAiServiceContract::retryStudy` without creating duplicate concurrent jobs or overwriting existing historical records.
8. **Comprehensive Regression & Verification:**
   - Execute all unit, feature, integration, and architecture tests from both merged lineages.
   - Conduct a controlled synthetic/deidentified dual-path rehearsal confirming end-to-end functionality.

### Out of scope

- Rebasing, squashing, or rewriting published commit history on either source branch.
- Creating a separate standalone AI PACS dashboard or admin portal (all operator interactions belong in the existing Operator results interface).
- Modifying Doctor Core clinical workflows, doctor worklists, doctor reporting interfaces, or doctor earning triggers.
- Treating AI outputs as final clinical diagnostic reports or doctor-verified documents.
- Accessing or modifying GitHub repository secrets.
- Creating pull requests or merging into `main`.
- Deploying or redeploying to staging or production environments.
- Communicating with external production AI PACS endpoints during CI or testing.
- Transferring real patient PHI over external networks.
- Modifying `Madeena-software/mhcs-business-docs` (synchronization remains deferred).
- Deprecating or removing the legacy NPZ upload or MPIPS conversion pipeline.

### Preserved behavior

- **Urgent Operator Field Operations:** All capabilities delivered in Slices 1–4 of `task/urgent-operator-field-operations` must remain fully operational (shift creation, walk-in member registration, reusable consent, basic examination bypass, 4-digit locators, Grabber DICOM upload, 57-mm thermal queue-ticket printing).
- **Legacy NPZ Pipeline:** Multipart NPZ upload, `ImageGatewayCaptureService`, `ProcessCaptureSet` background job, MPIPS conversion, and DICOM study creation must remain 100% functional.
- **Sequential Examination Flow:** Standard prebooked examination flows (`Booking` → `OperatorArrival` → `IdentityVerification` → `PaperConsent` → `PaperTicket` → `BasicExamination` → `Xray` → `Study`) remain unchanged.
- **DICOM Viewer:** Existing `[Lihat DICOM]` action, Cornerstone viewer integration, and canonical laterality display must continue to function seamlessly.
- **Private Object Storage Integrity:** All objects stored in `PrivateObjectStore` remain immutable, secure, and accessible only through authorized purpose-scoped grants.
- **Audit Logging Integrity:** All critical actions continue to generate immutable audit trails in `AuditStore`.

## Dependencies and assumptions

### Dependencies

- Target branch: `task/urgent-operator-field-operations` @ `5a3626b1d5e2624ec7818ca88545e36d320f0294`.
- Source branch: `task/ai-pacs-integration` @ `4e716a34505fac810f47b5b24b4bbcb348105b8f`.
- Common base: `cb61e62aaf2ad4bd59b142633d8d53c482dabcba` (`origin/main`).
- Laravel 13.x, PHP 8.4+, Filament 5.x, mPDF library.
- MySQL local/test database containers.
- Local `PrivateObjectStore`, `AuditStore`, and `IdempotencyStore`.

### Approved assumptions

- The ancestry-preserving merge must combine both branch histories cleanly into a single unified candidate branch.
- Merge conflicts are expected in `tests/Architecture/FoundationArchitectureTest.php` due to parallel migration and client additions; resolution must include both sets of changes rather than discarding either.
- The Operator results interface has access to study-level or admission-level models that can query or join the latest AI job status.
- External network calls to Yizhun AI PACS are mocked in automated tests using `Http::fake()` and synthetic DICOM fixtures.
- The 3 environment variable names `AI_PACS_USERNAME`, `AI_PACS_PASSWORD`, and `AI_PACS_URL` are sufficient for AI PACS authentication; their values are configured locally and never committed.

### Remaining approval requirements

- Designated human review and approval prior to staging/production deployment (Release Gate G10).
- Separate human authorization for any future GitHub secret configuration, pull-request creation, or merge into `main`.
- Separate human approval for subsequent documentation alignment in `Madeena-software/mhcs-business-docs`.

## Required capabilities

- Repository read and write.
- Local command and test execution (`php artisan`, `./vendor/bin/pest`, `composer`).
- Local Git branch manipulation and commit authoring.
- Database migration execution against local test databases.
- Codebase Memory MCP and Graphify analysis where applicable.

## Execution constraints

### Architecture & Reuse Discipline (Ponytail)
- Apply Ponytail reuse discipline: reuse existing models (`OperatorQueueAdmission`, `ImageGatewayStudy`), services (`ImageGatewayAiService`, `GrabberDicomIngestionService`), and infrastructure (`PrivateObjectStore`, `AuditStore`).
- Do not introduce redundant database tables, alternative queue brokers, or parallel PDF generation engines.
- Reuse the existing mPDF-based `AiPacsLaravelDerivedPdfGenerator` implementation established in `task/ai-pacs-integration`.

### Security, Privacy & Access Control Boundaries
- **Private Storage Streaming:** Derived report PDFs must be streamed directly through an authenticated controller action reading from `PrivateObjectStore`. Never expose direct filesystem paths or public URLs.
- **Strict Authorization:** The streaming endpoint must verify that the requesting user has an active Operator profile assigned to the site and shift governing the requested study. Requests from unauthenticated users or out-of-scope operators must return `401 Unauthorized` or `403 Forbidden`.
- **Audit Trail:** All PDF download actions must be logged in `AuditStore` recording operator ID, study ID, timestamp, and client IP without recording PDF byte payloads or patient PHI.
- **Credential Hygiene:** Never print, log, return, or commit AI PACS credentials. Reference only the environment variable names: `AI_PACS_USERNAME`, `AI_PACS_PASSWORD`, `AI_PACS_URL`.

### Clinical Integrity Boundaries
- **Screening Aid Only:** AI outputs are non-diagnostic screening aids. They must never be labeled, formatted, or exposed as doctor-verified medical reports.
- **Mandatory Disclaimer:** The disclaimer `Keluaran AI — belum diverifikasi tenaga medis.` must appear prominently wherever AI report status or download actions are rendered.
- **Non-False Completion:** AI failure must never falsely mark a radiography capture set or clinical admission as complete, nor block an Operator from viewing the DICOM study.
- **Doctor Core Separation:** AI outputs do not trigger doctor earnings or populate doctor diagnostic records; doctor final reports remain strictly governed by Doctor Core.

## Execution Slices

To maintain rigorous traceability and coherent delivery, implementation must proceed across six structured execution slices:

### Slice 1: Ancestry-Preserving Merge & Conflict Resolution
- Merge `4e716a34505fac810f47b5b24b4bbcb348105b8f` into `5a3626b1d5e2624ec7818ca88545e36d320f0294` using a standard merge commit preserving both commit histories.
- Resolve conflicts in `tests/Architecture/FoundationArchitectureTest.php` (combining migration allowlists and network client allowlists).
- Review and verify all AI PACS database migrations (`create_image_gateway_ai_tables`, `add_pacs_identifiers`, `add_derived_metadata`) alongside urgent Operator migrations.
- Execute migrations on disposable test database, verifying clean schema creation, rollback safety, and zero data loss.
- Run architecture checks and Pint formatting verification.

### Slice 2: Dual-Source Asynchronous AI Dispatch
- Integrate `ImageGatewayAiServiceContract` triggering into:
  - `ProcessCaptureSet` (triggering upon successful NPZ -> DICOM study registration).
  - `GrabberDicomIngestionService` (triggering upon successful direct DDR DICOM upload).
- Guarantee asynchronous behavior: DICOM ingestion and radiography capture completion must never wait for AI PACS or fail when AI dispatch fails.
- Verify that AI dispatch failure or queuing failure does not impede radiography completion.

### Slice 3: Operator UI Results Integration & Medical Disclaimer
- Extend the Operator DICOM results view (e.g. `resources/views/operator/xray-capture.blade.php`, `xray-readiness-worklist.blade.php`) to display a dedicated `Laporan AI` column adjacent to `[Lihat DICOM]`.
- Implement state-aware rendering:
  - `AI not queued`: Non-misleading neutral indicator.
  - `Queued`: Informative queued indicator (`Menunggu Antrean AI`).
  - `Processing`: In-progress indicator (`Sedang Dianalisis AI`).
  - `Report ready`: Action button labeled `Unduh Laporan AI`.
  - `Failed`: Failure status with operator retry action if retryable.
  - `Unavailable`: Clear unavailable notification.
- Render the prominent disclaimer: `Keluaran AI — belum diverifikasi tenaga medis.` adjacent to AI elements.

### Slice 4: Authorized Private-Storage PDF Streaming & Audit
- Implement the authenticated Operator streaming controller action (`OperatorAiReportController` or equivalent).
- Enforce site and shift authorization: only authenticated operators assigned to the study's site can download the derived PDF.
- Stream derived Indonesian/MHCS PDF from `PrivateObjectStore` without exposing storage keys or public URLs.
- Audit all download attempts in `AuditStore` without logging PDF payloads or PHI.
- Verify HTTP 401 for unauthenticated and HTTP 403 for unauthorized/cross-site operators.

### Slice 5: Operator Retry Action & Failure Containment
- Implement operator retry endpoint/action for failed AI jobs.
- Enforce idempotency: prevent duplicate concurrent AI jobs for the same study.
- Ensure retries do not overwrite historical immutable records or alter original DICOM studies.
- Verify that terminal and retryable failure states correctly contain errors without affecting the DICOM viewer or admission workflow.

### Slice 6: Comprehensive Verification & Synthetic Dual-Path Rehearsal
- Run full regression suites for:
  - AI PACS client, downloader, and mPDF generator tests.
  - Urgent Operator field operations Slices 1–4.
  - Legacy NPZ upload and MPIPS conversion pipeline.
  - Grabber additive DICOM ingestion and locator resolution.
- Execute a controlled synthetic end-to-end rehearsal verifying:
  ```text
  DICOM available (via NPZ and DDR Grabber)
  → AI job queued asynchronously
  → AI processing completes
  → original PDF stored immutably
  → derived Indonesian/MHCS PDF created
  → Operator sees report-ready status
  → authorized download succeeds
  → DICOM viewer remains usable
  ```

## Acceptance criteria

### Merge & Architecture Acceptance
- [ ] Git commit history confirms a normal merge commit uniting `5a3626b1d5e2624ec7818ca88545e36d320f0294` and `4e716a34505fac810f47b5b24b4bbcb348105b8f` without rebasing or squashing.
- [ ] `tests/Architecture/FoundationArchitectureTest.php` passes, accommodating all migrations and authorized network clients.
- [ ] Database migrations execute cleanly on fresh databases and upgrade existing schemas without data loss.

### Dual-Source AI Triggering Acceptance
- [ ] DICOM studies created via legacy NPZ processing (`ProcessCaptureSet`) asynchronously dispatch an AI evaluation job.
- [ ] DICOM studies created via additive Grabber DICOM ingestion (`GrabberDicomIngestionService`) asynchronously dispatch an AI evaluation job.
- [ ] Capture set completion and Grabber DICOM ingestion return successfully regardless of AI PACS queue latency, offline vendor status, or dispatch errors.

### Operator UI & Disclaimer Acceptance
- [ ] Operator results worklist renders a dedicated `Laporan AI` column adjacent to the `[Lihat DICOM]` action.
- [ ] When AI report is ready, an action button labeled `Unduh Laporan AI` is displayed.
- [ ] When AI is queued, processing, failed, or unavailable, clear state-aware badges are displayed instead of misleading download buttons.
- [ ] The exact disclaimer `Keluaran AI — belum diverifikasi tenaga medis.` is clearly displayed adjacent to AI actions.
- [ ] AI results are never presented as doctor-verified or final clinical diagnostic reports.

### Authorized Download & Security Acceptance
- [ ] An authenticated Operator assigned to the relevant site can successfully download the derived Indonesian/MHCS PDF.
- [ ] An unauthenticated user attempting to download the PDF receives HTTP 401.
- [ ] An authenticated Operator assigned to a different site/context receives HTTP 403.
- [ ] The derived PDF is streamed directly from private storage; no permanent or public storage URL is generated or exposed.
- [ ] Every download attempt is recorded in `AuditStore` without logging PDF binary payloads or patient PHI.

### Failure Containment & Provenance Acceptance
- [ ] AI processing failure (timeout, calculation error, download error) never alters, deletes, or hides the DICOM study.
- [ ] AI processing failure never prevents the Operator from opening the DICOM viewer via `[Lihat DICOM]`.
- [ ] AI processing failure never marks a radiography session or admission falsely completed.
- [ ] Operator-authorized retry correctly re-dispatches failed studies without creating duplicate active jobs or corrupting immutable records.
- [ ] The original AI PACS PDF and derived Indonesian/MHCS PDF remain immutably stored in `PrivateObjectStore` with preserved provenance links.

### Capability Preservation Acceptance
- [ ] All urgent Operator field operations (shift creation, walk-in registration, reusable consent, basic examination bypass, 4-digit locators, DDR DICOM ingestion, 57-mm thermal printing) remain fully functional.
- [ ] Legacy NPZ upload, MPIPS conversion, and DICOM study generation pass all existing regression tests.
- [ ] Controlled synthetic rehearsal successfully demonstrates end-to-end DICOM availability, async AI processing, report download, and DICOM viewing across both capture paths.

## Verification requirements

### Required checks

1. **Unit & Domain Tests:**
   - AI PACS client and adapter tests (`tests/ImageGateway/AiPacsClientTest.php`).
   - Authentication, upload, polling, timeout, retry, and report-download tests (`tests/ImageGateway/AiPacsPlaywrightReportDownloaderTest.php`).
   - Derived Indonesian/MHCS PDF rendering and formatting tests (`tests/ImageGateway/AiPacsLaravelDerivedPdfGeneratorTest.php`).
   - Immutability and provenance verification (`tests/ImageGateway/AiPacsDerivedPdfIntegrationTest.php`).
   - AI dispatch and queue execution tests (`tests/ImageGateway/ImageGatewayAiDispatchTest.php`, `tests/ImageGateway/ProcessAiPacsStudyIntegrationTest.php`).
2. **Feature & Integration Tests:**
   - Dual-source triggering tests verifying dispatch from both `ProcessCaptureSet` and `GrabberDicomIngestionService`.
   - Operator results UI rendering tests verifying column placement, state-aware badges, button labels, and disclaimer text.
   - Authorized PDF download tests:
     - Authorized operator success (HTTP 200, valid PDF stream).
     - Unauthenticated denial (HTTP 401).
     - Unauthorized cross-site denial (HTTP 403).
     - Audit log generation in `AuditStore`.
   - Failure containment tests (simulated AI PACS timeout/error preserving DICOM study and admission state).
   - Operator retry endpoint tests (idempotency, single active job, state transition).
3. **Regression Tests:**
   - Urgent Operator field operations feature suites (`tests/Feature/Operator/OperatorFieldOperationsSlice1Test.php` through `Slice4Test.php`).
   - Legacy NPZ upload and processing tests (`tests/ImageGateway/Wp02ImageGatewayTest.php`, `tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php`).
   - Direct DICOM ingestion and locator resolution tests.
   - Thermal queue-ticket print contract tests.
4. **Architecture & Static Analysis:**
   - `tests/Architecture/FoundationArchitectureTest.php`.
   - `./vendor/bin/pint --test`.
   - `git diff --check`.
5. **Controlled Synthetic Rehearsal:**
   - End-to-end execution with synthetic de-identified DICOM:
     ```text
     DICOM available
     → AI job queued asynchronously
     → AI processing completes
     → original PDF stored immutably
     → derived Indonesian/MHCS PDF created
     → Operator sees report-ready status
     → authorized download succeeds
     → DICOM viewer remains usable
     ```

### Required evidence

The Executor MUST report:
- Exact merge commit SHA and working-tree status.
- Verification that commit history contains both parent branches without rewriting history.
- Specific test suites executed and exact observed test pass counts.
- Database migration execution and rollback evidence.
- Confirmation of authorized PDF streaming security tests (401 and 403 enforcement).
- Confirmation of non-blocking asynchronous dispatch for both NPZ and Grabber paths.
- Confirmation that no live production AI PACS calls or real patient data were used.
- Pint formatting and `git diff --check` clean status.

## Stop conditions

The Executor MUST stop implementation and return the issue to planning when:
- Git merge produces structural or business conflicts that cannot be resolved without altering approved behavior or rewriting published history.
- Migration execution causes data loss, table naming collisions, or irreversible schema corruption.
- Asynchronous AI dispatch cannot be decoupled from radiography capture completion without major architectural restructuring.
- Private storage streaming requires exposing public URLs or bypassing authentication/authorization.
- Any requirement arises to sync `Madeena-software/mhcs-business-docs` or access external production endpoints.
- Execution requires an unauthorized side effect (e.g. git push, PR creation, merge to `main`, production deployment, modifying GitHub secrets).

## Side-effect authorization

### Explicitly authorized side effects for Executor

- Local git merge commit uniting `5a3626b1d5e2624ec7818ca88545e36d320f0294` and `4e716a34505fac810f47b5b24b4bbcb348105b8f`.
- Local modifications to repository code, views, routes, controllers, services, migrations, and tests within `Madeena-software/mhcs-core`.
- Local execution of test runners (`php artisan test`, `pest`), migrations, and code formatting tools (`pint`).
- Local git commits on the integration branch necessary to record verified implementation progress.

### Explicitly NOT authorized

- Rebasing, squashing, or rewriting published commits from either source branch.
- Git push to remote repository.
- Creation or modification of GitHub secrets.
- Creation of pull requests or issues.
- Merging into `main`.
- Deployment or redeployment to staging or production.
- Contacting production AI PACS or transferring real patient data.
- Modifying `Madeena-software/mhcs-business-docs`.

## Expected terminal outcome

### Review Required

The Executor's work concludes with `Review Required` once all slices are implemented, all acceptance criteria are met, and observed test evidence confirms dual-source AI processing, secure operator report downloads, and zero regression across both lineages.

The Executor reports:
- Exact merge commit and final implementation commit SHAs.
- Observed test, migration, and rehearsal evidence.
- Verification of zero regression on urgent Operator and legacy NPZ pathways.

The Executor does NOT self-declare final protocol acceptance.

## Review and remediation handling

The Reviewer evaluates implementation against this exact task contract, the validated baselines (`5a3626b1d5e2624ec7818ca88545e36d320f0294` and `4e716a34505fac810f47b5b24b4bbcb348105b8f`), and observed verification evidence.

If review requires bounded corrections within the same delivery objective, this stable task file is updated and republished under a new immutable revision. Materially new scope or architectural changes return to Delivery Planning.
