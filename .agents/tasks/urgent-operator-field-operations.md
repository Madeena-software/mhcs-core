---
title: Urgent Operator Field Operations, Four-Digit Radiography-Session Lookup, and Additive DICOM Ingestion
document_id: MHCS-TASK-OPERATOR-FIELD-OPS-001
version: 1.1
status: validated-published
language: en-US
last_updated: 2026-09-05
scope:
  - Operator field autonomy (shift creation, adding existing members, on-the-spot registration)
  - Per-member basic-examination bypass with audit and zero earning
  - Reusable informed consent and visit confirmation
  - Four-digit radiography-session code lifecycle (0000–9999)
  - Authenticated Grabber manifest lookup scoped to active session
  - Authenticated additive DICOM-source ingestion
  - Mandatory preservation of existing legacy NPZ upload and processing path
authority_note: This task authorizes bounded repository implementation under the validated interim human-authority snapshot. Synchronization of Madeena-software/mhcs-business-docs is explicitly deferred and out of scope. Existing NPZ upload and MPIPS conversion paths must remain fully operational.
---

# Executable Task

This file defines a bounded software-delivery contract for implementation.

A validated task MUST provide enough authority, scope, acceptance, verification, and stop-condition information for an Executor to proceed without inventing material product, requirement, architecture, scope, or approval decisions.

A task is not a generic coding recipe. Implementation technique remains the Executor's responsibility within the constraints established here.

## Task identity

**Task title:**  
`Urgent Operator Field Operations, Four-Digit Radiography-Session Lookup, and Additive DICOM Ingestion`

**Task path:**  
`.agents/tasks/urgent-operator-field-operations.md`

**Task contract state:**  
`Validated/Published upon immutable publication of this exact content.`

The task file is the executable delivery contract.

Execution and review lifecycle states such as `In Execution`, `Review Required`, `Remediation Required`, and `Accepted` SHOULD normally be tracked by orchestration, review records, repository metadata, or another mechanism that preserves the exact governing task revision.

A lifecycle-status update MUST NOT silently replace the immutable task revision that governed an execution attempt.

When remediation materially changes this executable contract, edit the same stable task path, return it to Draft as needed, and republish it as a new immutable governing task revision before renewed execution.

**Delivery objective / Work Package / MVP:**  
`Urgent Operator Field Operations & Additive DICOM Ingestion Umbrella Delivery Contract`

**Owner / designated planning authority:**  
`Faliq Adlan, Human Owner / Planner-Reviewer under Interim Authority Snapshot`

## Delivery context

In distributed healthcare field environments (e.g., mobile screening units, Puskesmas, on-site industrial screening clinics), certified operators encounter dynamic operational realities that cannot be constrained by prior central administrative scheduling or rigid pre-booked admission queues. Operators require bounded operational autonomy to establish shifts on-the-spot, admit existing members, or register walk-in members directly at the point of care. Furthermore, operational clinical triage requires the ability to bypass basic examination workflows when medically or operationally justified without fabricating clinical measurements, while preserving rigorous patient identity and consent verification.

At the imaging capture boundary, the Madeena DDR capture computer running MPIPS locally can generate valid DICOM directly. Rather than routing all imaging through intermediate NPZ normalization on the operator terminal, the system requires a direct, authenticated Grabber ingestion path. Active radiography sessions must be discoverable via an easy four-digit operational locator code (`0000`–`9999`), enabling the DDR Grabber to retrieve the minimal demographic manifest required by `docs/mpips/examples/mhcs-dicom-manifest.minimal.example.json` and subsequently upload the generated DICOM study directly into MHCS Core private storage.

### Human-Authority Decision & Documentation Deferral

The human owner has explicitly directed that synchronization of `Madeena-software/mhcs-business-docs` be skipped for now because this is an urgent operational requirement.

For this bounded task only, the validated interim human-authority snapshot detailed below supersedes conflicting business or context statements strictly within the scope of this task. Neither the task nor the Executor claims that `mhcs-business-docs` has been updated or aligned. Documentation synchronization is recorded as **deferred and out of scope**.

### Additive Invariant & Preservation of Existing Lineage

This delivery contract is strictly additive. The existing real-NPZ upload, MPIPS conversion, and queue processing pipeline (governed by `.agents/tasks/production-real-npz-end-to-end-validation.md`, `app/Modules/ImageGateway/Application/Services/ImageGatewayCaptureService.php`, and `app/Modules/ImageGateway/Application/Jobs/ProcessCaptureSet.php`) MUST remain completely operational. The existing prebooked and sequential examination workflows, existing Admin capabilities, and database integrity must be preserved without breaking changes.

## Baseline and task revision

**Implementation baseline:**  
`cb61e62aaf2ad4bd59b142633d8d53c482dabcba`

**Task revision:**  
`The full SHA of the commit containing this exact task content on branch task/urgent-operator-field-operations.`

The implementation baseline is the verified repository revision from which execution begins. The task revision is the exact immutable content identity governing execution and must be resolvable before execution handoff.

The implementation baseline and governing task revision are separate references. Do not change the implementation baseline silently during execution.

## Objective

Deliver an end-to-end, additive operational capability in `mhcs-core` enabling authorized Operator field autonomy (shift creation, adding existing members, on-the-spot member registration with NIK/MRN isolation), per-member basic-examination bypass, reusable informed consent with lightweight visit confirmation, active four-digit radiography-session locators (`0000`–`9999`), authenticated Grabber minimal manifest resolution, and authenticated Grabber additive DICOM ingestion, while guaranteeing complete operational preservation and regression safety for the legacy NPZ upload and processing path.

## Authoritative inputs

### Governing authority

1. **Interim Human Authority Snapshot (Urgent Operational Directive):**
   - **Point 1 (Operator field autonomy):** Authorized Operator may create a shift for a site within their permitted scope, add an existing Member, or register a new Member on the spot with 7 mandatory fields: name, gender, NIK, birth date, phone number, affiliation/institution/organization name, and office location. Existing NIK/member identity must not be silently duplicated. Internal MHCS MRN remains the clinical/system identifier; NIK must never become DICOM PatientID.
   - **Point 2 (Basic-examination bypass):** After verification, authorized Operator may freely select per Member to either continue to Basic Examination or skip Basic Examination directly to the radiography-session queue. Skip requires no additional approval. Operator, timestamp, and explicit skipped state must be recorded. Examination measurements or completion must not be fabricated. A skipped examination must not trigger Basic Examination earnings. The normal Basic Examination path remains available.
   - **Point 3 (Reusable informed consent):** Preserve existing consent capability and history. Use one reusable, versioned master informed-consent record for the same defined screening/radiography scope. Do not require a new full signature at every visit while a matching active consent remains valid. Record a lightweight visit confirmation before procedure. Require new consent when prior consent is withdrawn/superseded, guardian authority changes, or service/procedure/material risks change. Never overwrite historical consent versions. Withdrawal prevents progression until valid consent exists.
   - **Point 4 (Four-digit radiography-session code):** Each active radiography session receives an easy four-digit code (`0000`–`9999`). The code is an operational locator, not authentication, identity proof, MRN, NIK, or permanent identifier. Must be unique within active site-and-shift scope. Expires or becomes unusable when session completes, is cancelled, or shift closes. Resolve attempts must be rate-limited and audited; failure responses must not facilitate enumeration.
   - **Point 5 (Grabber manifest lookup):** Authenticated and authorized Grabber may submit the four-digit code. Resolution is scoped by Grabber identity, permitted site, active shift, and eligible radiography-session state. Return only the minimum fields required by `docs/mpips/examples/mhcs-dicom-manifest.minimal.example.json`. Include internal MHCS MRN, name, sex, birth date, study description, and required capture metadata. Do not expose NIK, phone number, affiliation, office location, or unrelated health information. Enforce object-level authorization independently of code correctness.
   - **Point 6 (Additive DICOM ingestion):** Support DICOM generated locally by MPIPS on the Madeena DDR computer. Add an authenticated Grabber-to-MHCS-Core DICOM upload boundary. Bind uploaded DICOM to the correct active examination/session. Validate file, manifest/patient/study association, permitted state, checksum, idempotency, duplicate behavior, and private storage. A failed upload must not falsely complete the session.
   - **Point 7 (Mandatory preservation invariant):** Work is additive, not replacement. Do not remove, disable, rename incompatibly, or weaken any existing feature. The existing NPZ upload contract, UI, API, persistence, queue processing, MPIPS conversion, DICOM result handling, retry behavior, and authorization remain operational. Existing Members may still follow normal prebooked/sequential flow. Existing Admin capabilities remain available. Regression verification must explicitly prove the legacy NPZ path still works alongside the new DICOM-source path.
2. **Canonical Delivery Framework:**
   - `.agents/AGENTS.md` and `.agents/software-workflow.md` — delivery protocol, gate definitions, evidence obligations, side-effect boundaries.
   - `.agents/context/project.md` — repository orientation, product boundaries, actor responsibilities.
3. **Integration & Data Contracts:**
   - `docs/mpips/examples/mhcs-dicom-manifest.minimal.example.json` — authoritative minimal manifest schema.
   - `docs/mpips/mhcs-dicom-api.md` — authoritative DICOM integration contract and metadata rules.
4. **Preserved Tasks & Existing Lineage:**
   - `.agents/tasks/production-real-npz-end-to-end-validation.md` — closed Image Gateway real-NPZ validation contract and fixtures.
   - Existing migration history in `database/migrations/` and test suites in `tests/`.

### Requirement traceability

- `FIELD-OPS-001` → Operator shift creation within permitted site scope.
- `FIELD-OPS-002` → Adding existing Member to an active shift.
- `FIELD-OPS-003` → On-the-spot Member registration (7 fields, NIK deduplication, MRN internal identifier, NIK never DICOM PatientID).
- `FIELD-OPS-004` → Reusable informed consent, master consent versioning, lightweight visit confirmation, and withdrawal blocking.
- `FIELD-OPS-005` → Per-Member Basic Examination bypass, auditable skipped state, zero fabricated data, zero earning.
- `FIELD-OPS-006` → Four-digit active radiography-session locator code (`0000`–`9999`) lifecycle, site/shift uniqueness, rate-limiting, anti-enumeration.
- `FIELD-OPS-007` → Authenticated Grabber manifest lookup returning minimal JSON (`docs/mpips/examples/mhcs-dicom-manifest.minimal.example.json`) with strict privacy boundaries.
- `FIELD-OPS-008` → Authenticated Grabber DICOM upload boundary, validation, idempotency, private object storage, and examination binding.
- `FIELD-OPS-009` → Mandatory preservation of legacy NPZ upload, MPIPS conversion, queue processing (`ProcessCaptureSet`), and result viewing.
- `FIELD-OPS-010` → Non-destructive schema migrations and backwards-compatible data models.
- `FIELD-OPS-011` → Dual-path synthetic operational rehearsal (both NPZ and DICOM pathways verified end-to-end).
- `FIELD-OPS-012` → Bounded queue-ticket thermal-print profile (57×47P roll consumable, ~48mm printable width, dynamic cut-to-content height, privacy preservation, automated contract coverage, and physical printer validation dependency).

## Scope

### In scope

The delivery contract is structured as one coherent umbrella task that MAY be implemented and verified across four sequential execution slices:

#### Execution Slice 1: Operator Field Autonomy, Member Registration, Reusable Consent & Bypass Flow
- **Shift Management:** UI and backend capability for an authorized Operator to create an operational shift for a site within their permitted scope (`OperatorSiteAssignment`).
- **Member Addition:** UI and service capability to look up an existing Member by NIK or identifier and add them directly to the active shift.
- **On-The-Spot Registration:** Registration form capturing: (1) full name, (2) gender, (3) NIK, (4) birth date, (5) phone number, (6) affiliation/organization name, and (7) office location. Enforce unique NIK constraints; resolve existing member if NIK already exists. Generate unique internal MHCS MRN. Ensure NIK is strictly isolated from clinical/imaging identifiers.
- **Reusable Informed Consent:** Data model and service supporting a versioned master informed consent for the screening scope. If a valid, active consent exists for the member, bypass full re-signing and record an auditable, lightweight visit confirmation. Support explicit consent withdrawal, which immediately blocks procedure progression.
- **Basic Examination Bypass:** Operator toggle/action per Member after verification to either proceed to Basic Examination or bypass directly to the radiography-session queue (`stage` = `xray`, `state` = `waiting`). Record operator ID, timestamp, and explicit `skipped` status in queue admission / examination records. Ensure zero fabricated clinical vitals/measurements and zero Basic Examination operator earnings. Preserve the standard Basic Examination flow for members who do not bypass.

#### Execution Slice 2: Four-Digit Session Locator & Authenticated Grabber Manifest Lookup
- **Locator Generation & Lifecycle:** Allocate a unique four-digit code (`0000`–`9999`) upon admission to active radiography readiness. Ensure uniqueness within the active site and active shift. Transition code to expired/invalid when the session completes, is cancelled, or the shift closes.
- **Rate-Limiting & Security:** Enforce rate-limiting and generic fail-closed error responses on locator lookup to prevent enumeration attacks.
- **Grabber Authentication & Authorization:** Implement authenticated boundary for the DDR Grabber client (scoped to Grabber identity, assigned site, and active shift).
- **Minimal Manifest Endpoint:** Manifest lookup endpoint by four-digit code returning strictly the structure defined in `docs/mpips/examples/mhcs-dicom-manifest.minimal.example.json`:
  - `examination`: `study_description`
  - `patient`: `medical_record_number` (internal MHCS MRN, NOT NIK), `name`, `sex`, `birth_date`
  - `capture`: `detector_type`, `body_part_examined`, `laterality`, `projection`
  - Explicitly exclude NIK, phone number, affiliation, office location, and general medical history from the manifest response.

#### Execution Slice 3: Additive Authenticated DICOM-Source Ingestion Boundary
- **DICOM Upload API:** Authenticated endpoint accepting DICOM binary files uploaded directly from the DDR Grabber.
- **Validation & Integrity:** Validate MIME type / DICOM magic bytes, file size, SHA-256 checksum, and session state.
- **Idempotency & Binding:** Ensure idempotent submissions using client submission IDs / SHA-256 digests. Bind the uploaded DICOM directly to the target examination and queue admission.
- **Private Storage:** Store the DICOM file securely in private object storage (`PrivateObjectStore`) using existing opaque key and encryption conventions.
- **Terminal State Management:** Update examination and queue admission state to `completed` or `awaiting_ai` upon successful DICOM ingestion. Ensure partial or failed uploads leave the session in a safe, retryable state without false completion.

#### Execution Slice 4: Cross-Path Regression, Queue-Ticket Thermal Printing & Synthetic Operational Rehearsal
- **Queue-Ticket Thermal Printing:** Refine the operator queue-ticket print view and layout contract for field operations:
  - Consumable specification: target thermal paper roll 57×47P (interpreted as 57 mm nominal paper width and 47 mm roll diameter, not a fixed ticket length).
  - Print profile: 57-mm thermal print layout with dynamic cut-to-content height, approximately 48-mm safe printable content width, and suppression of browser/A4 headers, footers, and margins (`@page { margin: 0; }`).
  - Privacy preservation: include only operationally necessary ticket data (site name, schedule/shift window, prominent queue ticket number, and issue timestamp); strictly exclude NIK, phone number, consent details, or clinical information.
  - Driver & protocol neutrality: do not prescribe proprietary or unsupported printer drivers/protocols; support standard browser/web printing dialog while recording specific printer-model/driver uncertainty as an explicit validation dependency.
- **Legacy NPZ Regression:** Run full existing test suites verifying that NPZ upload via `ImageGatewayCaptureService`, `ProcessCaptureSet`, MPIPS conversion, and DICOM study results remain fully functional.
- **Dual-Path Rehearsal:** Execute synthetic end-to-end tests exercising both paths:
  - Path A: Prebooked Member → Attendance → Basic Examination → NPZ Upload → MPIPS → Study Result.
  - Path B: Field Shift → On-The-Spot Member → Reusable Consent → Bypass Basic Examination → 4-Digit Code → Grabber Manifest Lookup → DDR DICOM Upload → Study Result.
- **Migration & Schema Safety:** Verify migrations on clean database and verify non-destructive upgrade from existing schema.

### Out of scope

- Updating `Madeena-software/mhcs-business-docs` during this task (explicitly deferred by human owner).
- External AI PACS browser automation.
- Uploading DICOM to external test/staging endpoint `http://124.225.183.175:8361/`.
- Accessing or using external credentials or production patient data.
- AI PDF retrieval or MHCS PDF reformatting.
- Treating AI output as a doctor-finalized clinical report.
- Removing, deprecating, or migrating away from NPZ ingestion.
- Production deployment, deployment mutation, or release execution (Release Gate G10).
- Unrelated refactoring or UI redesign.

### Preserved behavior

- **NPZ Ingestion Pipeline:** The complete NPZ upload endpoint, multipart form handling (`radiograph`, `gain`, `manifest`), `ImageGatewayCaptureService`, `ProcessCaptureSet` async job, MPIPS conversion client, and retry mechanisms must remain operational.
- **Sequential Examination Flow:** Standard flow (`Booking` → `OperatorArrival` → `IdentityVerification` → `PaperConsent` → `PaperTicket` → `BasicExamination` → `Xray` → `Study`) remains available and unchanged for scheduled members.
- **Admin & Filament Capabilities:** All existing administrative panels, user management, and configuration resources remain fully functional.
- **Data Integrity:** Existing records in `examination_consents`, `operator_queue_admissions`, `operator_paper_tickets`, `members`, and `shift_schedules` must remain intact and valid.

## Dependencies and assumptions

### Dependencies

- Repository baseline `cb61e62aaf2ad4bd59b142633d8d53c482dabcba`.
- Laravel framework 13.x, PHP 8.4+, Filament 5.x.
- MySQL / SQLite portable migration and index standards.
- `PrivateObjectStore`, `AuditStore`, and `IdempotencyStore` infrastructure.
- Authoritative manifest example in `docs/mpips/examples/mhcs-dicom-manifest.minimal.example.json`.
- Physical operational thermal printer hardware and driver validation dependency: target consumable is 57×47P thermal paper roll (57 mm nominal paper width, 47 mm roll diameter). Specific field thermal printer model and driver environment are unprescribed; physical hardware validation with the actual operational printer is required before staging/production deployment.

### Approved assumptions

- The urgent operational requirement warrants proceeding under the validated interim human-authority snapshot while documentation synchronization is deferred.
- Four-digit code space (`0000`–`9999`) provides 10,000 slots, which is more than sufficient for concurrent active radiography sessions within any single operational site and shift.
- Grabber clients authenticate via standard API tokens or pre-shared credentials scoped to site identity.
- Synthetic non-clinical data is used for all tests, verification, and rehearsals.

### Remaining approval requirements

- Designated human review and approval prior to staging/production deployment (Release Gate G10).
- Human owner review and approval for subsequent synchronization of `Madeena-software/mhcs-business-docs`.

## Required capabilities

- Repository read and write.
- Local command and test execution (`php artisan`, `./vendor/bin/pest`, `composer`).
- Database migration execution against local test databases.
- Codebase Memory MCP and Graphify analysis where applicable.

## Execution constraints

### Architecture & Reuse Discipline (Ponytail)
- Apply Ponytail reuse discipline: leverage existing Eloquent models, domain services, and database tables.
- Reuse `operator_queue_admissions` and `operator_queue_admission_history` for queue lifecycle transitions.
- Reuse `PrivateObjectStore` for all DICOM object persistence.
- Reuse `AuditStore` for all audit trail logging (shift creation, member registration, consent actions, bypass decisions, code lookups, DICOM uploads).
- Reuse `IdempotencyStore` for DICOM upload deduplication.

### Clinical & Privacy Boundaries
- **NIK Hygiene:** NIK is captured for civil identity and deduplication only. NIK MUST NEVER be passed as DICOM PatientID (`(0010,0020)`), embedded in DICOM headers, or exposed in the Grabber minimal manifest.
- **Patient Identifier:** Internal MHCS Medical Record Number (MRN) remains the sole clinical patient identifier in DICOM manifests and studies.
- **Data Minimization:** Manifest lookup response must strictly match `docs/mpips/examples/mhcs-dicom-manifest.minimal.example.json`. No demographic or contact details beyond name, sex, and birth date may be returned.
- **Anti-Enumeration:** Four-digit code lookup endpoints must be rate-limited, audit failed attempts, and return uniform `404 Not Found` or `422 Unprocessable` responses for invalid, expired, or out-of-scope codes.

### Clinical Workflow Integrity
- **Non-Fabrication:** Bypassing Basic Examination must explicitly record a `skipped` state. It MUST NOT create mock vital signs, fictitious BMI values, or completed questionnaire records.
- **Earning Exclusion:** Basic Examination operator earning triggers must only fire upon genuine, completed vital signs and questionnaire capture. Bypassed admissions must yield zero Basic Examination earnings.
- **Consent Enforcement:** A procedure cannot proceed if the member has no valid master consent, or if existing consent has been withdrawn.

### Queue-Ticket Thermal Print & Privacy Boundaries
- **57-mm Thermal Print Profile:** Printable ticket layout must be tailored to standard 57-mm thermal paper rolls (roll specification 57×47P: 57 mm nominal width, 47 mm roll diameter, continuous cut-to-content feed). Width constraint must adhere to ~48 mm safe printable area without horizontal clipping or unreadable line wraps.
- **Dynamic Content Height:** The ticket layout must not impose a fixed page height; vertical size must be dynamic, cutting/stopping based on content length.
- **Header/Footer Suppression:** Standard browser headers and footers (URL, page numbers, date/time timestamps) must be suppressed in print stylesheets (`@page { margin: 0; }`).
- **Ticket Privacy Minimization:** Thermal queue slips must expose only operational queuing essentials (site name, schedule/shift window, prominent queue ticket number, and issue timestamp). Slips MUST NOT contain member NIK, telephone/contact number, consent records, date of birth, MRN, or clinical details.
- **Driver Independence:** Do not assume or hardcode vendor-specific ESC/POS, CUPS, or proprietary printer drivers into Core; maintain standard web print dialog compatibility while documenting printer driver validation as an external hardware dependency.

## Acceptance criteria

- [ ] An authorized Operator can create an operational shift for a site within the Operator's permitted operational scope.
- [ ] An authorized Operator can look up and add an existing Member to the active shift.
- [ ] An authorized Operator can register a new Member on the spot capturing all 7 required fields: full name, gender, NIK, birth date, phone number, affiliation/organization name, and office location.
- [ ] On-the-spot registration generates a unique internal MHCS MRN and prevents silent duplication of existing NIKs.
- [ ] A reusable, versioned master informed-consent record is supported; active valid consent avoids repeated full signing across visits for matching screening scope.
- [ ] A lightweight visit confirmation is recorded and audited before procedure progression.
- [ ] Consent withdrawal immediately halts further workflow progression until new valid consent is established.
- [ ] After verification, an authorized Operator can freely choose per Member to continue to Basic Examination or bypass directly to radiography readiness.
- [ ] The bypass decision requires no secondary approval and records the operator, timestamp, and explicit skipped state.
- [ ] Bypassed admissions create zero fabricated examination measurements and trigger zero Basic Examination earnings.
- [ ] The existing sequential Basic Examination flow remains fully functional; in the same shift, one Member can complete Basic Examination while another bypasses it.
- [ ] Each active radiography session receives a unique four-digit locator code (`0000`–`9999`) scoped to the active site and shift.
- [ ] The four-digit code expires or becomes unusable when the session completes, is cancelled, or the shift closes.
- [ ] An authenticated Grabber can resolve the four-digit code, scoped to permitted site, active shift, and eligible session state.
- [ ] Grabber manifest lookup returns only the minimum fields specified in `docs/mpips/examples/mhcs-dicom-manifest.minimal.example.json` using internal MRN, strictly excluding NIK, phone number, and affiliation.
- [ ] Locator resolve attempts are rate-limited, audited, and return uniform error responses that resist enumeration.
- [ ] Authenticated Grabber DICOM upload boundary accepts valid DDR DICOM files, verifies checksum and idempotency, and stores the file in private object storage.
- [ ] The uploaded DICOM is bound to the target examination and transitions the admission state appropriately.
- [ ] Invalid, mismatched, duplicate, unauthorized, expired, or cross-shift DICOM upload attempts fail safely without falsely completing the session.
- [ ] Legacy NPZ upload, `ProcessCaptureSet` queue processing, MPIPS conversion, and DICOM study viewing remain fully operational and pass regression tests.
- [ ] Operator queue-ticket print view conforms to 57-mm thermal paper profile (target consumable 57×47P: 57 mm nominal width, 47 mm roll diameter) with dynamic cut-to-content height, ~48 mm safe printable width, and suppression of browser headers/footers.
- [ ] Operator queue-ticket print view preserves strict privacy by displaying only operational queuing essentials (site, schedule window, prominent ticket number, timestamp) and strictly excluding NIK, phone number, consent details, MRN, and clinical information.
- [ ] Database migrations are non-destructive and compatible with both fresh schemas and existing schema upgrades.
- [ ] Synthetic end-to-end operational rehearsal successfully verifies both the legacy NPZ pathway and the new DICOM-source pathway.

## Verification requirements

### Required checks

1. **Unit & Domain Tests:**
   - Operator shift creation and authorization scope checks.
   - On-the-spot member registration, NIK uniqueness, and MRN assignment.
   - Master consent versioning, visit confirmation, and withdrawal blocking.
   - Four-digit code generation, collision resistance, and expiration logic.
   - Minimal manifest builder and demographic filtering.
2. **Feature & Workflow Tests:**
   - Basic Examination bypass workflow, queue transitions, and earning exclusion.
   - Grabber manifest resolution with authentication, authorization, and rate limiting.
   - Grabber DICOM upload validation, idempotency, checksum matching, and private storage binding.
   - Concurrent code resolution and anti-enumeration defenses.
   - Queue-ticket thermal print automated layout/print-contract tests: verify 57-mm thermal styling profile (~48 mm printable safe width, dynamic content height, browser header/footer suppression `@page { margin: 0; }`), correct operational fields (site, schedule window, prominent ticket number, timestamp), and complete exclusion of sensitive data (NIK, phone number, consent details, MRN, clinical info).
3. **Preservation & Legacy Regression Tests:**
   - Complete execution of existing Image Gateway tests (`tests/ImageGateway/Wp02ImageGatewayTest.php`, `tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php`, etc.).
   - Full regression of prebooked member booking, arrival, verification, consent, and sequential examination flow.
4. **Migration & Schema Verification:**
   - `php artisan migrate:fresh --seed` (or test equivalent) verifying clean schema creation.
   - Migration rollback and re-application verification.
5. **Code Quality & Static Analysis:**
   - Route inspection (`php artisan route:list`) ensuring all new endpoints are registered and protected.
   - Formatting and code style compliance (`./vendor/bin/pint --test` or equivalent).
6. **Synthetic Operational Rehearsal & Physical Printer Validation:**
   - End-to-end rehearsal executing dual pathways with synthetic data:
     - Scenario 1: Standard prebooked Member → Basic Examination → NPZ Upload → MPIPS Conversion → DICOM Result.
     - Scenario 2: Operator-Created Shift → On-The-Spot Registration → Reusable Consent → Bypass Basic Examination → 4-Digit Code → Grabber Manifest Lookup → DDR DICOM Upload → DICOM Result.
   - Documented physical-printer validation protocol: verification procedure using the actual operational thermal printer (57×47P roll consumable) prior to production deployment to confirm readable physical printout, absence of horizontal clipping, correct roll feed/cut, and proper dynamic height.

### Required evidence

The Executor MUST report:
- Exact implementation commit SHA and working-tree status.
- Specific test suites executed and exact observed test pass counts (including thermal print contract tests).
- Confirmation of non-destructive migration execution.
- Verification output demonstrating legacy NPZ upload path preservation.
- Confirmation of documented physical-printer validation procedure and unprescribed driver dependency status.
- Confirmation that no real patient identity or external network calls were used.
- Known limitations or non-blocking observations for Reviewer consideration.

## Stop conditions

The Executor MUST stop implementation and return the issue to planning when:
- A material conflict arises between the interim human-authority snapshot and existing application security constraints.
- Implementation would require breaking, replacing, or deprecating the existing NPZ upload or MPIPS conversion pipeline.
- Four-digit code design cannot guarantee uniqueness within active site/shift scope without cross-tenant collisions.
- Any requirement emerges to sync `Madeena-software/mhcs-business-docs` or access external production endpoints during this task.
- Implementation baseline suffers material drift that alters interface boundaries or feasibility.
- Execution requires an unauthorized side effect (e.g., git push, PR creation, release).

## Side-effect authorization

### Explicitly authorized side effects for Executor

- Local modifications to repository code, database migrations, routes, resources, views, and tests within `Madeena-software/mhcs-core`.
- Local execution of test runners (`php artisan test`, `pest`), migration commands, and code formatting tools.
- Creation of local feature branches and local Git commits necessary to record verified implementation progress.

### Explicitly NOT authorized

- Git push to remote repository.
- Creation or modification of remote branches, pull requests, or issues.
- Deployment to staging, production, or any remote environment.
- External network mutation or uploading DICOM to external servers (including `http://124.225.183.175:8361/`).
- Accessing or storing external secrets, credentials, or production patient data.
- Modification of `Madeena-software/mhcs-business-docs`.

## Expected terminal outcome

### Review Required

The Executor's work concludes with `Review Required` once all slices are implemented, acceptance criteria are satisfied, and observed test evidence confirms both the new operational capabilities and the preserved legacy NPZ pathway.

The Executor reports:
- Exact implementation revision.
- Observed test and rehearsal evidence.
- Verification of zero regression on the NPZ pipeline.

The Executor does NOT self-declare final protocol acceptance.

## Review and remediation handling

The Reviewer evaluates implementation against this exact task contract, the implementation baseline (`cb61e62aaf2ad4bd59b142633d8d53c482dabcba`), the interim authority snapshot, and observed verification evidence.

If review requires bounded corrections within the same delivery objective, this stable task file is updated and republished under a new immutable revision. Materially new scope or architectural changes return to Delivery Planning.
