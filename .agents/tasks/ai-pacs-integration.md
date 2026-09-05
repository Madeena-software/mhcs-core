---
title: AI PACS Integration and Indonesian Report Processing
document_id: MHCS-TASK-AI-PACS-INTEGRATION-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-09-05
scope:
  - AI PACS integration delivery contract
  - Asynchronous DICOM dispatch and AI result polling
  - Immutable original PDF and derived Indonesian PDF storage
  - Full provenance, audit, idempotency, and error handling
  - Stable public integration interface for Operator workflows
  - Clinical distinction between AI output and doctor clinical reports
authority_note: >
  This published task authorizes only the bounded implementation, tests, and verification defined here.
  It does not authorize deployment, release, live production PHI transmission, PR creation, or merging.
---

# Executable Task

This file defines a bounded software-delivery contract for implementation.

A validated task MUST provide enough authority, scope, acceptance, verification, and stop-condition information for an Executor to proceed without inventing material product, requirement, architecture, scope, or approval decisions.

A task is not a generic coding recipe. Implementation technique remains the Executor's responsibility within the constraints established here.

## Task identity

**Task title:**
AI PACS Integration and Indonesian Report Processing

**Task path:**
`.agents/tasks/ai-pacs-integration.md`

**Task contract state:**
`Validated/Published` upon immutable publication of this exact content.

Execution and review lifecycle states remain separate from this immutable task revision. Material remediation must update this stable path and republish it as a new immutable governing revision before renewed execution.

**Delivery objective / Work Package / MVP:**
AI PACS Integration and Indonesian Localization Work Package (Image Gateway Domain)

**Owner / designated planning authority:**
Product Authority (`Madeena-software/mhcs-business-docs` @ 645058e…) and Human Direction

## Delivery context

In Indonesia, preventive healthcare screening and diagnostic examinations often take place at distributed physical examination facilities (e.g., Puskesmas, clinics, mobile units) where certified diagnostic specialists (such as radiologists) are not physically co-located. MHCS Core orchestrates end-to-end clinical workflows connecting Members, Site Staff, and Doctors.

Within MHCS Core, the **Image Gateway** module owns durable private persistence of radiographs, atomic submission acceptance, queued MPIPS processing coordination, DICOM storage/viewer access, result publication, and audit trails.

To support high-throughput chest screening, MHCS Core integrates with an external AI PACS ("Yizhun AI" / "Insight ChestDR" platform) to perform automated chest radiograph analysis and report generation. The external AI PACS analyzes DICOM radiographs and outputs an AI Image Report PDF. However, the external AI PACS is a third-party vendor system with several specific architectural characteristics:
1. It is served as a vendor React Single Page Application (SPA) over cleartext HTTP (`http://124.225.183.175:8361/`);
2. It lacks an official, public, documented external REST API for study submission and report extraction, requiring browser-level automation (Playwright) or validated reverse-engineered protocol adapters;
3. Its native PDF reports are formatted in Chinese/English with partial localization, requiring transformation into an Indonesian-localized MHCS screening report matching the agreed design reference (`05_final_indonesia_v10`);
4. Crucially, AI outputs are screening aids and clinical decision-support artifacts only. They must never be represented automatically as doctor-finalized or clinically verified diagnostic reports, which remain strictly doctor-owned within Doctor Core.

This task establishes the delivery contract authorizing the implementation of the AI PACS integration pipeline inside `mhcs-core`, strictly decoupled from the urgent Operator field-operations workstream (`task/urgent-operator-field-operations`).

## Baseline and task revision

**Implementation baseline:**
`cb61e62aaf2ad4bd59b142633d8d53c482dabcba` (`main` branch)

**Task revision:**
The full SHA of the commit containing this exact task content, supplied upon publication.

The task revision and implementation baseline are separate references. The baseline is immutable and must not be changed silently during execution. Parallel work on `task/urgent-operator-field-operations` (branch commit `854100e246e25585fe135d6946eaa80b7558ad1f`) is independent and must not be merged or rebased into this delivery line.

## Objective

Authorizes a later implementation workstream to integrate external AI PACS (Yizhun AI / Insight ChestDR) into `mhcs-core` Image Gateway, accepting existing MHCS DICOM studies, executing asynchronous processing, retrieving original AI reports, persisting them immutably in `PrivateObjectStore`, generating Indonesian-localized derived PDFs adhering to the agreed design reference (`05_final_indonesia_v10`), maintaining full provenance and audit trails, and exposing a stable integration contract for operator workflows without altering existing NPZ/MPIPS/DICOM or doctor reporting pathways.

## Authoritative inputs

### Governing authority

- `Madeena-software/mhcs-business-docs` @ 645058e…:
  - `docs/business/01-business-overview.md` — End-to-end screening pathways, role boundaries, and actor responsibilities.
  - `docs/business/02-system-responsibilities.md` — Image Gateway ownership of private durable image storage, atomic acceptance, queued processing, AI and doctor routing, publication, and audit; Doctor Core ownership of final clinical reports and doctor earnings; strict separation of AI screening output from doctor-finalized diagnostic reports.
  - `docs/business/03-system-responsibilities.md#business-to-technical-traceability` — Mapping of system responsibilities and implementation boundaries.
- `.agents/context/project.md` — MHCS Core Product Context, authority map, and repository conventions.
- `.agents/AGENTS.md` and `.agents/software-workflow.md` — Repository AI Delivery Contract and Software Delivery Protocol.

### Technical references (Observed evidence, not governing authority)

- `https://github.com/Madeena-software/ai-report-download-automation` (observed revision `21e062029e67f3ef7a59d01aecc11f042c2d68f6`):
  - Implementation reference for headless Chromium/Playwright automation against the Yizhun PACS SPA (`PacsBrowser`, selector fallbacks, session management, viewport capture, PDF Blob hook via `URL.createObjectURL`, retry loops, diagnostics).
- `https://github.com/Madeena-software/ai-pacs-indonesian-localization` (observed revision `2045c5a091cbb03ab5939be46f663f2ff90d853f` / `15f3e69ab1c71c9ef1447b2ce193be0f86dd113b`):
  - Medical terminology dictionary, Indonesian localization glossary, classification logic, and UI element references.
- Google Drive folder `05_final_indonesia_v10` (`https://drive.google.com/drive/folders/1Zw95DNnIpL0nYDLg9R4svJZN8Tu-UBT0`):
  - Agreed visual, structural, and textual design reference for the derived Indonesian MHCS screening report PDF.
- DICOM fixture folder (`https://drive.google.com/drive/folders/1IUEuSlEu3MUtxuDqflM6AJLhVm4RRv6r`):
  - De-identified DICOM test fixtures for offline and synthetic testing.
- Observed AI PACS runtime evidence:
  - Origin `http://124.225.183.175:8361/` serves Nginx 1.17.8 and React SPA (`window.name="yizhun_web_ct"`, `webpackJsonpyizhun_pacs`).
  - Cleartext HTTP transport over public IP; no authenticated HTTPS endpoint currently exposed.

### Requirement traceability

- `IMG-AI-001` → Accept existing MHCS DICOM reference: System accepts an existing authorized DICOM study generated by `ProcessCaptureSet` without altering the NPZ or MPIPS pipelines.
- `IMG-AI-002` → Asynchronous queue execution: AI PACS processing is dispatched via Laravel queue jobs (`ProcessAiPacsStudy`), with lease tracking, retry limits, and failure boundaries.
- `IMG-AI-003` → Non-false completion semantics: AI processing failure must never falsely mark radiography capture sets complete, nor prevent Operator shift progression.
- `IMG-AI-004` → Strict environment variable authentication: AI PACS client authenticates strictly using `AI_PACS_USERNAME`, `AI_PACS_PASSWORD`, and `AI_PACS_URL`. Credentials are never logged, printed, hardcoded, or committed.
- `IMG-AI-005` → DICOM upload and report retrieval: Adapter uploads/registers DICOM, monitors study processing status, and retrieves the original AI Image Report PDF.
- `IMG-AI-006` → Immutable private original storage: Original AI PDF is stored immutably in `PrivateObjectStore` under dedicated purpose/prefix.
- `IMG-AI-007` → Comprehensive provenance tracking: Bidirectional lineage links Member → Examination → Radiography Session → DICOM Study → AI Job → Original PDF → Derived Indonesian PDF.
- `IMG-AI-008` → Derived Indonesian PDF generation: Reformat original AI results into an Indonesian screening report matching `05_final_indonesia_v10`, without altering or overwriting the original PDF.
- `IMG-AI-009` → Idempotency and duplicate prevention: Database unique constraints and `IdempotencyStore` prevent duplicate AI jobs for the same DICOM study.
- `IMG-AI-010` → Structured audit trail: State transitions, auth attempts, uploads, downloads, errors, and retrievals are recorded via `AuditStore`.
- `IMG-AI-011` → Stable integration interface contract: Stable public application service interface is exposed for consumption by Operator and Member modules.
- `IMG-AI-012` → Clinical authority separation: AI reports are permanently labeled as AI decision support and cannot automatically finalize or replace a Doctor clinical report.

## Scope

### In scope

- **AI PACS Queue Infrastructure:**
  - Create asynchronous queue job (`ProcessAiPacsStudy` or equivalent) within Image Gateway.
  - Job claiming, leasing, exponential backoff, retry budget (default 3 tries), and timeout configuration.
- **AI PACS Client & Adapter:**
  - Configurable adapter boundary supporting browser automation (Playwright/Node or Python bridge) or evidenced API endpoints.
  - Secure credential consumption from environment variables (`AI_PACS_USERNAME`, `AI_PACS_PASSWORD`, `AI_PACS_URL`).
  - Resilient authentication, session retention, selector fallbacks, and diagnostic screenshot capture on failure.
  - DICOM transmission, study calculation polling, and original AI Image Report PDF download.
- **Private Storage & Provenance:**
  - Persist downloaded original AI PDF as an immutable private object via `PrivateObjectStore`.
  - Database schema/migration for `image_gateway_ai_jobs` and `image_gateway_ai_reports` storing study ID, job status, error details, original object key/checksum, derived object key/checksum, and full provenance IDs.
- **Derived Indonesian PDF Generation:**
  - Extraction of AI findings, measurements, heatmap/annotated image, and summary from the original PDF/data.
  - Reformatting into a clean, professional Indonesian PDF conforming to `05_final_indonesia_v10`.
  - Storing derived PDF as a separate immutable object in `PrivateObjectStore`, preserving forward and backward links.
  - Explicit prominent labeling: "Laporan Hasil Analisis Kecerdasan Buatan (Bukan Pengganti Diagnosis Dokter)" / "AI Screening Analysis (Not a Doctor's Final Clinical Report)".
- **Audit & Idempotency:**
  - Audit logging of all AI job lifecycle events through `AuditStore` (`ai_job_dispatched`, `ai_pacs_authenticated`, `ai_pacs_study_uploaded`, `ai_pacs_report_downloaded`, `ai_pdf_derived`, `ai_job_failed`).
  - Idempotent job submission preventing concurrent or repeated processing for the same DICOM study.
- **Integration Contract:**
  - Define a clean, stable public interface/service in Image Gateway (`AiPacsServiceContract` or `ImageGatewayAiService`) exposing methods:
    - `dispatchAiAnalysis(string $studyId): AiJobReference`
    - `getAiAnalysisStatus(string $studyId): AiAnalysisStatus`
    - `getOriginalReportObject(string $studyId): ?PrivateObject`
    - `getDerivedReportObject(string $studyId): ?PrivateObject`
- **Testing & Verification:**
  - Comprehensive unit, integration (mocked external service), idempotency, provenance, failure isolation, and visual comparison tests.

### Out of scope

- Modifying the existing NPZ capture, upload, normalization, or MPIPS conversion pipeline.
- Modifying, merging, or rebasing `task/urgent-operator-field-operations` (branch `854100e246e25585fe135d6946eaa80b7558ad1f`).
- Direct integration into Operator UI views (which will consume the stable contract in a subsequent task).
- Doctor clinical report generation or modification of Doctor Core review workflows or earnings calculations.
- Direct public unauthenticated download endpoints for DICOM or PDF files.
- Live production PHI transmission to external unencrypted endpoints.
- Modifications to `Madeena-software/mhcs-business-docs`.
- Adding GitHub repository secrets during task authoring.

### Preserved behavior

- Existing NPZ upload, normalization, and validation rules.
- Existing `ProcessCaptureSet` job execution and MPIPS conversion contract.
- Existing DICOM storage, metadata schema, and Cornerstone viewer functionality.
- Existing prebooked visit flows, front-desk registration, and signed paper consent capture.
- Existing Operator authentication, active-site/current-shift tenancy boundaries, and role permissions.
- Existing Admin console and audit log retrieval.

## Dependencies and assumptions

### Dependencies

- PHP 8.2+ runtime with existing Laravel 11 application architecture.
- Working MySQL database with migration execution capabilities.
- Configured Redis or database queue driver for Laravel background jobs.
- `PrivateObjectStore` for secure, grant-controlled object persistence.
- `AuditStore` and `IdempotencyStore` shared infrastructure.
- Local Playwright runtime (Chromium) or HTTP adapter client for AI PACS interaction.
- PDF generation/manipulation tool (e.g. Dompdf, TCPDF, Puppeteer, or Python reportlab) compatible with repository PHP/system environment.

### Approved assumptions

- The external AI PACS is reachable at `http://124.225.183.175:8361/` but operates over cleartext HTTP; live tests with real patient data require transport security resolution.
- Existing MPIPS-generated DICOM files conform to standard DICOM PS 3.10 and are accepted by the Yizhun Chest DR platform.
- AI analysis results are decision-support outputs that enhance screening efficiency; they do not trigger doctor report fees or replace accredited radiologist review.
- The derived Indonesian PDF design follows `05_final_indonesia_v10` as the authoritative aesthetic and structural reference.

### Remaining approval requirements

- **Transport Security & PHI Policy:** Explicit designated human approval before any production or real-patient PHI is transmitted over cleartext HTTP to the external AI PACS.
- **GitHub Secrets Configuration:** Approval and manual/CLI provisioning of `AI_PACS_USERNAME`, `AI_PACS_PASSWORD`, and `AI_PACS_URL` in CI/CD environments without printing or exposing values.
- **Code Review & Release:** Final implementation acceptance by Reviewer and separate release authorization before deployment.

## Required capabilities

- Repository read and write access on branch `task/ai-pacs-integration`.
- PHP CLI and Composer for testing and static analysis.
- Database migration execution on local/testing MySQL.
- Network access for local testing against sandboxed/mocked AI PACS endpoints.
- Ability to run Playwright or headless browser instances for adapter verification.

## Execution constraints

### Constraints

- **Repository Reuse Discipline:** Reuse `PrivateObjectStore`, `AuditStore`, `IdempotencyStore`, `Clock`, `CorrelationId`, and `LocalId` primitives. Do not introduce parallel storage or queue mechanisms.
- **Credential Hygiene:** Consume `AI_PACS_USERNAME`, `AI_PACS_PASSWORD`, and `AI_PACS_URL` strictly from environment configuration (`config('services.ai_pacs')` backed by `.env`). Never print, log, or commit credentials.
- **Immutability of Source:** The original AI PDF retrieved from PACS must be saved immutably. The derived Indonesian PDF must be stored as a separate private object with explicit forward/backward provenance references.
- **Visual Fidelity:** The derived PDF must strictly adhere to the layout, typography, section hierarchy, and branding established in `05_final_indonesia_v10`.
- **Clinical Safety Disclaimer:** Every page of the derived PDF must feature prominent disclaimers in Bahasa Indonesia stating that the report is an AI analysis and not a final medical diagnosis by a certified physician.
- **Failure Resilience:** Failures during AI PACS processing (auth failure, timeouts, rate limits, corrupt PDF) must be recorded with actionable error codes, retried up to configured limits, and cleanly marked as failed without halting or invalidating the underlying radiography capture or DICOM generation.

## Acceptance criteria

- [ ] `ProcessAiPacsStudy` queue job accepts an authorized MHCS DICOM reference and processes it asynchronously.
- [ ] AI PACS client authenticates strictly using environment variables (`AI_PACS_USERNAME`, `AI_PACS_PASSWORD`, `AI_PACS_URL`) without leaking credentials in logs or exceptions.
- [ ] AI PACS client uploads DICOM, polls study status with configurable timeout and retry backoff, and downloads the original AI Image Report PDF.
- [ ] Original AI PDF is immutably stored in `PrivateObjectStore` under dedicated purpose/prefix.
- [ ] Derived PDF generator transforms AI output into a localized Indonesian screening report matching `05_final_indonesia_v10` styling, branding, and content.
- [ ] Derived PDF is stored as an independent private object, preserving full bidirectional provenance links to the original PDF, AI job, DICOM study, radiography session, examination, and member.
- [ ] Derived PDF prominently includes Bahasa Indonesia clinical disclaimers clarifying that AI output is not a doctor's final diagnostic report.
- [ ] AI job execution is idempotent; duplicate dispatch requests for the same DICOM study return the existing job reference without duplicate processing.
- [ ] Comprehensive audit events are emitted for all lifecycle stages via `AuditStore`.
- [ ] A stable public integration service interface (`ImageGatewayAiServiceContract`) is published for downstream consumption by Operator modules.
- [ ] AI job failure is recorded with structured error diagnostics, and does not falsely complete or fail the radiography session.
- [ ] All required automated tests pass, and `git diff --check` passes with no whitespace or syntax violations.

## Verification requirements

### Required checks

1. **Adapter Unit Tests:**
   - Client authentication with credentials from config.
   - Handling of invalid credentials, expired session, and auth retry.
   - Construction of direct viewer URLs and study identifier extraction.
   - PDF byte validation (header, size, EOF marker).
2. **Mocked Integration Tests:**
   - Full flow simulation: dispatch → upload → poll → download → store → derive.
   - Handling of transient network timeouts, HTTP 5xx errors, and exponential backoff.
   - Selector fallback and diagnostic artifact generation if browser automation is used.
3. **Idempotency & Concurrency Tests:**
   - Multiple concurrent dispatch calls for the same study produce exactly one AI job and one set of stored artifacts.
4. **Provenance & Immutability Tests:**
   - Verification that original PDF cannot be overwritten by derived PDF.
   - Verification of relationship integrity: Member ID, Examination ID, Session ID, DICOM Study UID, AI Job ID, Original Key, Derived Key.
5. **Non-False Completion & Safety Tests:**
   - Simulation of AI processing failure confirms radiography session remains intact and is not falsely marked complete.
   - Verification that AI report cannot be submitted to doctor earnings or masquerade as a finalized Doctor report.
6. **Regression Tests:**
   - Existing tests for NPZ upload, normalization, MPIPS client, DICOM viewer, and Operator flows remain green.
7. **Visual / Structural Inspection:**
   - Verification of derived PDF output against `05_final_indonesia_v10` reference structure.
8. **Code Quality:**
   - `git diff --check`
   - Repository PHPUnit suite execution for Image Gateway.

### Required evidence

The Executor MUST report:
- Exact implementation revision and working-tree status.
- Verification commands executed and observed test suite results.
- New database tables/migrations created.
- Stored object keys and provenance records created during tests.
- Confirmation that no credentials or patient PHI are logged or exposed.
- Known limitations, deviations, or residual risks.

## Stop conditions

The Executor MUST stop implementation and return to planning if:
1. **Unapproved PHI Transmission:** Execution would require sending real patient PHI over cleartext HTTP without an approved encrypted tunnel or proxy.
2. **Architecture Mutation:** Implementation requires a new external database, message broker, unauthorized cloud service, or changes to core clinical authority models.
3. **Pipeline Incompatibility:** Existing NPZ upload, MPIPS conversion, or DICOM viewer flows cannot be preserved without breaking changes.
4. **Credential Exposure:** An implementation approach requires persisting credentials in code, database records, or version control.
5. **Operator Branch Contamination:** Execution would require modifying or rebasing onto `task/urgent-operator-field-operations`.
6. **Release / Deployment Action:** Execution reaches a state attempting PR creation, main branch merging, or production deployment.

## Side-effect authorization

### Explicitly authorized side effects

- Local commits on the dedicated branch `task/ai-pacs-integration`.
- Automatic push to `origin/task/ai-pacs-integration` (no force push).
- Execution of local database migrations and tests in isolated test/dev environments.
- Creation of local mock fixtures and test artifacts.

### Prohibited side effects

- Committing or pushing directly to `main` or any other branch.
- Creating a pull request, merging, or rebasing.
- Deploying to staging or production environments.
- Adding or modifying GitHub repository secrets during this task-authoring phase.
- Mutating external PACS production data or uploading real patient DICOM files.
- Modifying files in `Madeena-software/mhcs-business-docs`.

## Expected terminal outcome

### Review Required

Use when all acceptance criteria are satisfied, automated tests pass, provenance and idempotency are proven, and truthful verification evidence is reported.

### Planning Required

Use when a stop condition is triggered (e.g. transport security policy block or external API unavailability).

## Review and remediation handling

The Reviewer evaluates implementation against this exact task revision, the baseline (`cb61e62aaf2ad4bd59b142633d8d53c482dabcba`), and observed verification evidence.

If bounded corrections are required, update and republish this same task file (`.agents/tasks/ai-pacs-integration.md`) with a new immutable commit SHA before renewed execution.
