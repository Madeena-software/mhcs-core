---
title: Operator and AI PACS Production Readiness Recovery
document_id: AGENT-TASK-OPERATOR-AI-PACS-RECOVERY-001
version: 1.0
status: Validated/Published
language: en-US
last_updated: 2026-09-07
scope:
  - Production readiness recovery for authenticated Operator/on-the-spot and AI PACS flows
  - Isolation and reproduction of web runtime stale route-cache failure mechanism
  - Evidence-supported remediation of deployment and cache lifecycle invariants
  - Rejection of symptom-hiding patches (Route::has, link removal, broad exception swallowing)
  - Comprehensive regression verification of Operator journeys, DICOM ingestion, and AI PACS pipelines
  - Governed Release Gate for Human-authorized production deployment and functional acceptance
authority_note: This task authorizes bounded repository reproduction, remediation, and verification under approved repository authority and implementation baseline b4701589ca46c0de9c337d6b584335498b4848d2. Production deployment remains a separate Human-authorized release gate.
---

# Executable Task

This file defines a bounded software-delivery contract for implementation.

A validated task MUST provide enough authority, scope, acceptance, verification, and stop-condition information for an Executor to proceed without inventing material product, requirement, architecture, scope, or approval decisions.

A task is not a generic coding recipe. Implementation technique remains the Executor's responsibility within the constraints established here.

## Task identity

**Task title:**
`Operator and AI PACS Production Readiness Recovery`

**Task path:**
`.agents/tasks/operator-ai-pacs-production-readiness-recovery.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content.`

The task file is the executable delivery contract.

Execution and review lifecycle states such as `In Execution`, `Review Required`, `Remediation Required`, and `Accepted` SHOULD normally be tracked by orchestration, review records, repository metadata, or another mechanism that preserves the exact governing task revision.

A lifecycle-status update MUST NOT silently replace the immutable task revision that governed an execution attempt.

When remediation materially changes this executable contract, edit the same stable task path, return it to Draft as needed, and republish it as a new immutable governing task revision before renewed execution.

**Delivery objective / Work Package / MVP:**
`Operator & AI PACS Production Readiness Recovery Umbrella Delivery Contract`

**Owner / designated planning authority:**
`Faliq Adlan, Human Owner / Planner-Reviewer under Interim Authority Snapshot and Product Authority`

## Delivery context

Following the production deployment of baseline `b4701589ca46c0de9c337d6b584335498b4848d2` (via production deployment run `34068701723`), basic infrastructure health checks and `/up` succeeded, but functional production acceptance failed. Specifically, authenticated Operator navigation to `GET /operator/eligible-shifts` failed with HTTP 500.

To investigate the failure without modifying production state or exposing sensitive healthcare data, a safe, read-only GitHub Actions diagnostic workflow was dispatched on branch `diagnostic/operator-eligible-shifts-500` (run `34075264515`). The diagnostic run collected definitive runtime logs, environment configuration, database structure, and process state.

This delivery contract establishes the complete path to recovery: establishing and reproducing the precise production failure mechanism in an isolated local environment, implementing the evidence-supported remediation for the deployment and cache lifecycle, verifying that all Operator and AI PACS invariants remain intact, and preparing the release gate for Human-authorized production deployment and functional smoke acceptance.

### Reviewed Incident Evidence and State Breakdown

The observed production evidence distinguishes four explicit states:

1. **Confirmed Immediate Production Failure:**
   - `GET /operator/eligible-shifts` returns HTTP 500.
   - The production web runtime's active route collection lacks the named route `operator.shifts.create`.
   - This causes an `Illuminate\Routing\Exceptions\RouteNotFoundException: Route [operator.shifts.create] not defined.` exception, wrapped by `Illuminate\View\ViewException` during the rendering of `resources/views/operator/eligible-shifts.blade.php`.
   - Production web request trace confirmed the failure occurs when evaluating `route('operator.shifts.create')` in the shift selection view.

2. **Related Evidence:**
   - In addition to `operator.shifts.create`, the named route `operator.basic-examination-worklist.bypass` was also observed to be missing from the active production web runtime route collection. Both routes were introduced in the urgent Operator field-operations workstream.

3. **Verified Exclusions and Structural Evidence:**
   - **Application Container Revision:** Confirmed deployed image digest aligns across web app, queue, scheduler, and image-worker containers (`sha256:d8c6b90757271c667468132924faef860b0ecbe2a4db335c029fb3e89bc56187`), corresponding to repository commit `b4701589ca46c0de9c337d6b584335498b4848d2`.
   - **Database Migrations:** All migrations are recorded as executed in production, specifically including:
     - `2026_09_05_000001_add_on_the_spot_and_bypass_fields_to_core_tables.php`
     - `2026_09_05_000002_create_radiography_session_codes_table.php`
     - `2026_09_06_000001_create_ai_pacs_jobs_table.php`
   - **Database Schema Alignment:** Detailed schema inspection of 10/10 relevant tables (`operators`, `sites`, `shifts`, `operator_site`, `members`, `member_registrations`, `radiography_sessions`, `radiography_session_codes`, `dicom_studies`, `ai_pacs_jobs`) confirmed exact structural alignment with the local MySQL 8.4 schema baseline. Column counts, types, nullability, defaults, indexes, and foreign keys match 100%. Zero database schema drift exists.
   - **Data and Identity Invariants:** Inspected Operator records in production confirmed no null or orphan anomalies. Operator record `id=2` exists with valid user `id=2` and an active site assignment.
   - **Source Code Verification:** In the repository source at baseline `b470158...`, `routes/web.php` explicitly defines both `operator.shifts.create` (line 197) and `operator.basic-examination-worklist.bypass` (line 240).
   - **CLI vs Web Runtime Asymmetry:** When running in a fresh CLI container process, `php artisan route:list` cleanly lists both routes. Direct CLI rendering of `resources/views/operator/eligible-shifts.blade.php` succeeds without error. The failure is isolated strictly to the long-running web runtime environment.

4. **Strongly Supported (Not Yet Reviewer-Closed) Mechanism:**
   - The primary supported failure mechanism is a stale in-memory PHP-FPM OPcache holding an older copy of the persisted Laravel route cache file (`bootstrap/cache/routes-v7.php`).
   - Production Docker Swarm mounts a persistent Docker named volume `app_cache` at `/var/www/html/bootstrap/cache`.
   - PHP-FPM runs with OPcache enabled (`opcache.enable=1`), but CLI OPcache is disabled (`opcache.enable_cli=0`).
   - The production OPcache configuration sets `opcache.validate_timestamps=0` for maximum performance, instructing PHP not to check disk timestamps for cached script modifications.
   - The production deployment workflow initiates and stabilizes the PHP-FPM web runtime container before executing cache generation commands (`php artisan route:cache`, `php artisan config:cache`, `php artisan view:cache`) via `docker exec` in separate CLI processes.
   - Because `bootstrap/cache` is backed by the persistent `app_cache` volume, PHP-FPM loaded the pre-existing `routes-v7.php` artifact from disk into memory upon startup. When the deployment subsequently ran `php artisan route:cache` in CLI mode, the new route file was written to disk, but the running PHP-FPM master and worker pools never invalidated their in-memory OPcache because timestamp validation is disabled and PHP-FPM was never gracefully reloaded.
   - **Explicit Reviewer Boundary:** This mechanism is strongly supported by observed deployment architecture and runtime evidence, but it has **not yet completed Reviewer acceptance**. Before remediation can be accepted, the mechanism must be empirically proven in the required local reproduction gate.

## Baseline and task revision

**Implementation baseline:**
`b4701589ca46c0de9c337d6b584335498b4848d2`

**Diagnostic evidence branch:**
`diagnostic/operator-eligible-shifts-500`

**Diagnostic evidence remote head:**
`ad4b9044b1251626c8a1884db952a2dd11388180`

**Relevant GitHub Actions evidence:**
- Production deployment run: `34068701723` (failed functional acceptance at `GET /operator/eligible-shifts`)
- Read-only production diagnostic run: `34075264515` (confirmed web route collection missing `operator.shifts.create`, confirmed schema and migration alignment)

**Task revision:**
`The full Git commit SHA containing this exact task content on branch diagnostic/operator-eligible-shifts-500, resolved upon publication.`

The implementation baseline is the verified repository revision from which execution begins. The task revision is the exact immutable content identity governing execution and must be resolvable before execution handoff.

The implementation baseline and governing task revision are separate references. Do not change the implementation baseline silently during execution.

## Objective

Deliver one coherent umbrella recovery for MHCS Core production readiness:
1. Establish and empirically reproduce the production web runtime failure mechanism in an isolated local environment;
2. Implement only the evidence-supported remediation for the deployment and cache lifecycle;
3. Fully verify preserved invariants across authenticated Operator field operations and AI PACS workflows; and
4. Fulfill the release gate requirements for Human-authorized production deployment and functional acceptance.

Diagnosis, reproduction, remediation, verification, and release acceptance belong to this single delivery objective and must not be fragmented into independent tasks unless the Planner later identifies a materially separate objective.

## Authoritative inputs

### Governing authority

1. **Interim Human Authority Snapshot (Urgent Operator Field Operations):**
   - Authorized Operator field autonomy: shift creation, adding existing members, walk-in member registration on-the-spot with civil NIK deduplication and internal MRN isolation.
   - Per-member basic-examination bypass with audit logging and zero earnings fabrication.
   - Four-digit radiography-session locators (`0000`–`9999`) unique to active shift.
   - Minimal Grabber manifest lookup (`docs/mpips/examples/mhcs-dicom-manifest.minimal.example.json`).
   - Additive Grabber DICOM ingestion with mandatory preservation of legacy NPZ upload and MPIPS conversion.
2. **AI PACS Production Readiness Contract (`MHCS-TASK-OPERATOR-AI-PACS-INTEGRATION-001` & `MHCS-TASK-AI-PACS-001`):**
   - Dual-source asynchronous AI evaluation (legacy NPZ and additive direct DICOM).
   - Strict non-blocking asynchronous radiography capture progression; capture completion must never fail due to AI PACS timeout, network error, or worker delay.
   - Operator results UI with state-aware AI status and prominent medical disclaimer: `Keluaran AI — belum diverifikasi tenaga medis`.
   - Authorized private-storage streaming for derived Indonesian PDF (`05_final_indonesia_v10`), denying unauthenticated requests via repository web-auth conventions and cross-site requests via HTTP 403.
   - Provenance tracking and immutability from member → examination → radiography session → DICOM → AI job → original PDF → derived PDF.
   - Operator-authorized retry mechanics without duplicate job dispatch or state corruption.
3. **Repository Delivery Protocol & Contracts:**
   - `.agents/AGENTS.md` and `.agents/software-workflow.md` — canonical delivery gates (T5 Task Readiness, V7 Verification, A9 Acceptance, Separate Release Gate R10).
   - `.agents/prompts/plan-create-task.md` — canonical planning and review orchestration procedure.
   - `AGENTS.md` (root Codex runtime adapter).
   - `.agents/context/project.md` — project orientation and operational boundaries.
4. **Empirical Production Evidence:**
   - GitHub Actions deployment run `34068701723`.
   - GitHub Actions diagnostic run `34075264515`.
   - Diagnostic reproduction baseline on branch `diagnostic/operator-eligible-shifts-500` (`tests/Feature/OperatorEligibleShiftsProductionDiagnosticTest.php`).

### Requirement traceability

- `REQ-PROD-REC-001` → Reproduction of production runtime route-cache desynchronization under `opcache.validate_timestamps=0`.
- `REQ-PROD-REC-002` → Evidence-supported remediation of container deployment and cache lifecycle preventing runtime staleness.
- `REQ-PROD-REC-003` → Rejection of symptom-hiding workarounds (`Route::has()`, view link suppression, exception swallowing).
- `REQ-OPS-001` → Operator shift creation, eligible shifts listing, and attendance (`operator.shifts.create`, `operator.eligible-shifts`).
- `REQ-OPS-002` → Walk-in on-the-spot member registration with NIK deduplication.
- `REQ-OPS-003` → Basic examination bypass with audit trail and zero earnings.
- `REQ-OPS-004` → Four-digit radiography-session locators (`0000`–`9999`) and additive DICOM ingestion.
- `REQ-AI-001` → Dual-source AI job triggering from legacy NPZ and direct Grabber DICOM.
- `REQ-AI-002` → Asynchronous isolation: capture session completion independent of AI job state.
- `REQ-AI-003` → Operator results UI integration with state-aware status and disclaimer `Keluaran AI — belum diverifikasi tenaga medis`.
- `REQ-AI-004` → Authorized streaming of derived Indonesian PDF from private object storage.
- `REQ-AI-005` → Immutable provenance for original and derived report artifacts.
- `REQ-AI-006` → Safe retry mechanics without duplicate active jobs.
- `REQ-REL-001` → Governed Release Gate requiring explicit Human approval, authorized deployment path, and authenticated functional smoke validation.

## Scope

### In scope

- **Diagnostic Reproduction Gate:**
  - Build an isolated local production-equivalent reproduction demonstrating the OPcache route-cache failure mechanism itself using synthetic, deidentified data.
  - Conclusively verify that FPM with `opcache.validate_timestamps=0` fails when disk cache is updated out-of-band via CLI, and recovers immediately upon graceful FPM reload/restart.
- **Evidence-Supported Remediation:**
  - Implement a robust, permanent deployment and cache lifecycle solution that guarantees PHP-FPM always runs with synchronized, freshly compiled cache artifacts upon deployment.
  - Prevent recurrence for all future route, configuration, view, and code changes.
- **Strict Rejection of Symptom-Hiding:**
  - Explicitly prohibit and reject `Route::has()` guards, removing navigation links, or swallowing exceptions as the primary remediation.
- **End-to-End Regression Verification:**
  - Verify all Operator field operations flows in local test environment.
  - Verify AI PACS pipeline (job creation, processing, storage, PDF generation, disclaimer display, streaming).
  - Verify legacy NPZ upload and conversion pipeline alongside direct DICOM ingestion.
  - Verify MySQL 8.4 engine compatibility and schema consistency.
- **Release Gate Preparation:**
  - Define exact requirements for the subsequent Human-authorized production deployment and functional smoke validation.

### Out of scope

- Direct mutation of production containers, services, databases, or cache volumes during this task slice.
- Symptom-hiding patches that mask cache desynchronization.
- Reopening the deferred MPIPS Grabber round-trip workstream.
- Modifying business requirements or clinical protocols.
- Synchronizing `Madeena-software/mhcs-business-docs` (explicitly deferred by Human authority).
- Modifying production secrets or accessing production credentials.
- Extracting PHI, real DICOM files, or clinical patient reports.
- Real-patient AI evaluation or transmission.

### Preserved behavior

- **Operator Field Operations Invariants:**
  - Authorized Operator login, authentication, and site selection.
  - Shift creation, eligible shifts listing, and attendance recording.
  - On-the-spot registration with civil NIK isolation and internal MRN assignment.
  - Basic examination bypass with audit trail and zero-earnings integrity.
  - Four-digit radiography-session locators (`0000`–`9999`) and Grabber manifest lookup.
  - Additive direct DICOM ingestion without breaking legacy NPZ conversion.
- **Imaging and Capture Invariants:**
  - Existing legacy NPZ upload, queue worker processing (`ProcessCaptureSet`), and MPIPS conversion must remain 100% operational.
  - Non-blocking asynchronous capture progression: radiography capture and session completion MUST NOT wait for or fail on AI PACS processing.
- **AI PACS Invariants:**
  - External AI PACS client dispatch, status polling, and retry mechanisms.
  - Generation and private storage of derived Indonesian PDF (`05_final_indonesia_v10` standard via mPDF).
  - State-aware UI presentation with mandatory non-clinical disclaimer: `Keluaran AI — belum diverifikasi tenaga medis`.
  - Immutable provenance tracing from member through examination, session, DICOM, AI job, original PDF, to derived PDF.
  - AI failure must NEVER falsely complete the radiography session or corrupt examination state.
  - AI outputs must NEVER be represented as doctor-verified or final clinical diagnostic reports.
- **Database & Architecture Invariants:**
  - Full MySQL 8.4 engine compatibility (including post-2038 datetime timestamps).
  - Zero unapproved database schema alterations.
  - Established web-authentication conventions and site-scoped authorization (HTTP 403 for cross-site access).

## Dependencies and assumptions

### Dependencies

- **Implementation Baseline:** Repository revision `b4701589ca46c0de9c337d6b584335498b4848d2`.
- **Diagnostic Evidence:** Branch `diagnostic/operator-eligible-shifts-500` @ `ad4b9044b1251626c8a1884db952a2dd11388180`.
- **Production Deployment Configuration:** `docker/web/Dockerfile`, `docker-compose.prod.yml`, and `.github/workflows/deploy.yml`.
- **Local Isolated Execution Environment:** Docker and PHP-FPM runtime with configurable OPcache directives for empirical reproduction.

### Approved assumptions

- The application source code at `b470158...` correctly defines `operator.shifts.create` and `operator.basic-examination-worklist.bypass` in `routes/web.php`.
- The production HTTP 500 error is a runtime environment / deployment lifecycle defect, not a code defect, database schema discrepancy, or data corruption issue.
- All diagnostic reproductions and local testing must use exclusively synthetic, deidentified test fixtures. No production credentials or data will be used.
- Production access is permitted solely through GitHub Actions workflows, subject to explicit human authorization for any state-changing actions.

### Remaining approval requirements

1. **Reviewer Acceptance Gate:** Mechanism-level reproduction evidence and remediation code must be reviewed and accepted prior to initiating release procedures.
2. **Release Authorization Gate:** Deployment to production requires explicit Human approval.
3. **Production State Mutation:** Any production service reload, container restart, cache clearance, or deployment requires explicit Human approval.

## Required capabilities

- Repository read and write.
- Local shell and command execution.
- Isolated local Docker / PHP-FPM execution for OPcache reproduction.
- Local PHP 8.2 and Laravel test execution suite.
- GitHub Actions workflow inspection (read-only) and authorized deployment dispatch.

## Execution constraints

### Constraints

1. **Ponytail Reuse Discipline:** Implement the minimal, robust root-cause fix. Avoid unnecessary abstractions, new dependencies, or speculative refactoring.
2. **Mechanism-Level Reproduction Isolation:**
   - Must use synthetic / deidentified data only.
   - Must run in an isolated local environment (do not use production data, production credentials, production storage, production Docker contexts, or private production services).
3. **Remediation Acceptance Boundary:**
   - Remediation must address the deployment and cache lifecycle root cause.
   - The task strictly rejects symptom-hiding (`Route::has()` checks in views, suppressing UI links, broad exception catching).
   - Eventual fix must eliminate lifecycle desynchronization and guarantee that PHP-FPM running containers always see current cache artifacts.
4. **Security and Secret Hygiene:**
   - Secret values or production credentials MUST NEVER be written to the task, code, logs, or commit history.
   - AI PACS environment variables may be referenced ONLY by their standardized names: `AI_PACS_USERNAME`, `AI_PACS_PASSWORD`, and `AI_PACS_URL`.
   - Never extract, log, or persist Protected Health Information (PHI) or production DICOM files.
5. **Clinical Safety Boundary:**
   - AI outputs are screening aids only and must always be labeled with `Keluaran AI — belum diverifikasi tenaga medis`.
   - AI job failure must never falsely mark a radiography session as completed.

## Acceptance criteria

### Diagnostic Reproduction Gate

- [ ] Isolated local reproduction demonstrates the exact failure mechanism:
  - An older/stale route-cache artifact lacking `operator.shifts.create` is loaded by PHP-FPM running with production-equivalent OPcache settings (`opcache.enable=1`, `opcache.validate_timestamps=0`).
  - The updated route cache is written to disk via CLI (`php artisan route:cache`) without reloading FPM.
  - The cached file on disk contains `operator.shifts.create`.
  - The running PHP-FPM request path continues to return HTTP 500 (`RouteNotFoundException`).
  - A local-only graceful PHP-FPM reload/restart immediately resolves the error, allowing `GET /operator/eligible-shifts` to return HTTP 200.

### Remediation Gate

- [ ] Deployment / cache lifecycle remediation is implemented so that PHP-FPM is guaranteed to compile and execute fresh route and configuration caches upon deployment.
- [ ] No symptom-hiding patterns (`Route::has()`, link removal, broad exception swallowing) are introduced as the primary fix.
- [ ] The remediation prevents recurrence across any subsequent route, configuration, or view modifications.

### Functional Operator Field Operations Invariants

- [ ] Authenticated Operator can access `GET /operator/eligible-shifts` and render shift management UI.
- [ ] Route `operator.shifts.create` is accessible and functional for authorized operators.
- [ ] Route `operator.basic-examination-worklist.bypass` is accessible and functional for authorized operators.
- [ ] On-the-spot walk-in registration functions with civil NIK deduplication and internal MRN generation.
- [ ] Four-digit radiography-session locators (`0000`–`9999`) resolve correctly for authorized Grabber manifest requests.
- [ ] Additive Grabber direct DICOM ingestion successfully ingests studies and binds them to active sessions.
- [ ] Legacy NPZ upload and MPIPS queue conversion pipeline (`ProcessCaptureSet`) remains 100% operational with zero regressions.

### AI PACS Pipeline Invariants

- [ ] Dual-source AI job triggering functions correctly for both legacy NPZ and direct DICOM captures.
- [ ] Non-blocking asynchronous capture progression is preserved: radiography sessions complete immediately regardless of AI job status.
- [ ] Operator results UI displays state-aware AI evaluation status adjacent to `[Lihat DICOM]`.
- [ ] Prominent non-clinical disclaimer is displayed: `Keluaran AI — belum diverifikasi tenaga medis`.
- [ ] Derived Indonesian PDF (`05_final_indonesia_v10`) generates correctly and is streamed from private storage.
- [ ] Unauthenticated requests are denied via repository web-auth conventions (redirect or 401); cross-site operator requests return HTTP 403 Forbidden.
- [ ] AI job retry mechanism functions without duplicate active jobs or state corruption.
- [ ] AI failure does not falsely complete radiography sessions or clinical workflows.

### Engine and Database Invariants

- [ ] Full MySQL 8.4 engine compatibility is preserved across all tables.
- [ ] Post-2038 timestamp compatibility (`datetime` columns for operator claims and idempotency) is maintained.
- [ ] Zero unapproved database schema changes.

### Governed Release Gate

- [ ] Local verification and regression testing complete with 100% passing tests.
- [ ] Reviewer acceptance of reproduction evidence and remediation implementation is formally recorded.
- [ ] Explicit Human approval is obtained prior to production deployment.
- [ ] Production deployment is executed exclusively via the authorized GitHub Actions workflow.
- [ ] Post-deployment functional smoke validation verifies authenticated Operator login, eligible shifts rendering, and controlled AI PACS journeys.
- [ ] The exact deployed Git commit SHA is recorded in the release record.

## Verification requirements

### Required checks

1. **Local OPcache Mechanism Reproduction Test:** Run isolated test confirming the stale route cache under `opcache.validate_timestamps=0` and recovery upon reload.
2. **Operator Field Operations Test Suite:** Execute all feature tests for Operator field operations (`tests/Feature/Operator*`).
3. **AI PACS Integration Test Suite:** Execute all feature tests for AI PACS integration (`tests/Feature/AiPacs*`).
4. **Imaging Gateway & Legacy NPZ Test Suite:** Execute tests verifying legacy NPZ upload and conversion (`tests/Feature/ImageGateway*`, `tests/Feature/Radiography*`).
5. **Code Style & Syntax Verification:** Execute `git diff --check` and PHP linting (`php -l`) on all touched files.
6. **Production Deployment Smoke Validation:** Post-deployment authenticated smoke test against production verifying `GET /operator/eligible-shifts` returns HTTP 200.

### Required evidence

The Executor must report:
- Implementation revision and exact working-tree state.
- Local reproduction test execution output showing both failure and reload recovery.
- Full test suite output demonstrating zero regressions across Operator, AI PACS, and legacy imaging modules.
- Verification that no symptom-hiding workarounds were introduced.
- Post-deployment functional smoke validation evidence and exact deployed commit SHA.

## Stop conditions

The Executor MUST stop implementation and return the issue to planning when:
- Local reproduction fails to reproduce the observed failure mechanism or uncovers an unanticipated root cause.
- Remediation requires unapproved architectural changes or introduces new external service dependencies.
- Implementation would require executing state-changing operations on production without explicit Human approval.
- Any regression is observed in the legacy NPZ upload pipeline, basic examination bypass integrity, or clinical safety invariants.
- A required authority decision is missing or ambiguous.

## Side-effect authorization

Implementation authorization is strictly bounded to the tasks defined in this document.

### Explicitly authorized side effects

- Creation, modification, and commit of this task document `.agents/tasks/operator-ai-pacs-production-readiness-recovery.md` on branch `diagnostic/operator-eligible-shifts-500`.
- Non-force push of this task publication commit to `origin diagnostic/operator-eligible-shifts-500`.
- Local execution of Docker containers and test scripts for isolated OPcache mechanism reproduction using synthetic data.
- Local execution of PHPUnit feature and unit tests.
- Implementation of deployment and cache lifecycle fixes in repository deployment workflows and container definitions.
- Dispatch of authorized read-only diagnostic or test workflows in GitHub Actions.

### Explicitly prohibited side effects

- Direct production service restarts, reloads, or container manipulation without explicit Human approval.
- Direct production database mutations, schema alterations, or migration reruns.
- Clearing production cache volumes or storage buckets without explicit Human approval.
- Production deployment without prior Reviewer acceptance and explicit Human release approval.
- Modifying or committing production secrets or credentials.
- Force pushing or rewriting Git history on any branch.
- Creating pull requests or merging into `main` without Human authorization.
- Extracting or logging real patient data, DICOM files, or clinical reports.

## Expected terminal outcome

### Review Required

Upon completing:
1. Isolated local reproduction of the OPcache failure mechanism;
2. Evidence-supported remediation of the deployment/cache lifecycle; and
3. Full local regression verification across Operator, AI PACS, and imaging modules.

The Executor will report all reproduction logs, test results, and diffs for Reviewer evaluation.

### Release Gate

Following Reviewer acceptance:
1. Human approval is solicited for production deployment;
2. Deployment is executed via GitHub Actions;
3. Authenticated production smoke validation is performed; and
4. The exact deployed commit SHA is recorded.
