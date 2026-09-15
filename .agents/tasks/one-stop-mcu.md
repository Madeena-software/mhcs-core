---
title: One Stop Service MCU Screening Workflow
document_id: MHCS-TASK-ONE-STOP-MCU-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-09-15
scope:
  - additive One Stop Service MCU screening episode
  - existing participant verification and registration reuse
  - persisted MCU measurements and printable/downloadable PDF
  - preservation of existing staged radiography and clinical workflows
authority_note: >
  This task records the bounded new pathway explicitly directed by the Human
  owner. It does not replace or alter the Product Authority's existing
  examination-to-radiography service pathway. Planner/Reviewer inspection is
  required before Executor handoff under the task-authoring handoff.
---

# Executable Task

## Task identity

**Task title:** One Stop Service MCU Screening Workflow

**Task path:** `.agents/tasks/one-stop-mcu.md`

**Task contract state:** Validated/Published on publication of this exact content; awaiting Planner/Reviewer inspection before Executor handoff.

**Delivery objective / Work Package / MVP:** Additive One Stop MCU screening episode.

**Owner / designated planning authority:** Human owner direction in the task-authoring handoff; Product Authority remains `Madeena-software/mhcs-business-docs`.

## Delivery context

The Human directs a One Stop Service MCU screening flow that can finish after saved examination results and a printable/downloadable screening PDF, without representing the existing radiography or Doctor workflow as complete. The current approved Product Authority defines the existing booking → examination → radiography → processing → result pathway and says future pathways require their own validation and authorization. This task captures that separate, additive pathway under the Human's explicit direction; it does not revise the current pathway.

## Baseline and task revision

**Implementation baseline:** `b1a2204a2571920a6c696575a6480aad5a1294fd` (`main`, verified against `origin/main` before branch creation).

**Task revision:** Full SHA of the commit containing this exact task content; resolved on publication. The task revision is distinct from the implementation baseline.

## Objective

Enable an appropriately authorized Operator/Site Staff user to verify or register an eligible participant using established MHCS identity and registration semantics, record and persist the required MCU screening examination, and generate a printable/downloadable PDF from the saved examination. Completing this MCU episode MUST NOT complete, bypass, or otherwise change the normal radiography or downstream clinical workflow.

## Authoritative inputs

### Governing authority

- Human Direction in the task-authoring handoff dated 2026-09-15: the additive One Stop MCU flow, required source-form data, PDF outcome, acceptance criteria, exclusions, and implementation/side-effect limits.
- `Madeena-software/mhcs-business-docs` @ `645058e431f59c4450a136e72f140e6819b79f32`:
  - `docs/business/01-business-overview.md` — current examination-service pathway, actor journeys, and requirement that future pathways receive their own validation and authorization.
  - `docs/business/02-user-stories.md` — current Member and Site Staff interaction requirements, including registration, check-in, and basic examination.
  - `docs/business/03-system-responsibilities.md` — Member and Operator ownership, role boundaries, and separation of staged workflow responsibilities.
- `.agents/AGENTS.md`, `.agents/software-workflow.md`, and `.agents/context/project.md` — repository delivery contract, authority map, and system boundaries.
- `.agents/tasks/urgent-operator-field-operations.md` — established on-the-spot registration, check-in, consent, operator authorization, and preservation semantics already present at the implementation baseline.
- `.agents/tasks/operator-ai-pacs-integration.md` and the existing Image Gateway PDF/report implementation — observed examples of protected operator PDF delivery and PDF-generation patterns; technical evidence only, not product authority.

### Requirement traceability

- `MCU-H1` → Human Direction: participant/examination identity, examination date/time, and required measurements and context below.
- `MCU-H2` → Human Direction: deterministic BMI and highest-valid-PEF calculations; preserve the source-form Microtoise concept without unsupported reinterpretation.
- `MCU-H3` → Human Direction: persisted examination-backed PDF, screening disclaimer, print/download flow, and manual signature areas without a digital-signature subsystem.
- `MCU-H4` → Human Direction plus Product Authority current pathway: additive completion must not alter or falsely complete radiography or downstream clinical stages.
- `MCU-H5` → Product Authority stories `US-STAFF-REG-001`, `US-STAFF-REG-002`, `US-STAFF-REG-004`, `US-STAFF-REG-006`, `US-STAFF-REG-007`, `US-STAFF-REG-008`, `US-STAFF-EXAM-001`, and existing Operator task: established identity, registration, check-in, consent, examination, site/operator authorization, and least-privilege semantics.

## Scope

### In scope

- An Operator entry point for the distinct One Stop MCU episode, reusing existing participant verification, eligibility, registration/check-in, site, and operator concepts where applicable.
- Persisted MCU examination data associated with the correct participant, examination, authorized site, and examining operator, with validation appropriate to the captured values.
- Required participant/examination identity: participant/patient name; date of birth and/or age from canonical data; sex; existing patient ID/MRN; examination date; examination time.
- Required measurements and context:
  - blood pressure systolic and diastolic, mmHg;
  - weight, kg; height, cm; temperature, °C;
  - BMI/IMT, kg/m², deterministically derived as weight (kg) / height² (m²), with height converted to metres;
  - Microtoise, retaining the source-form concept and using an existing repository/domain convention if one applies; do not guess its meaning or silently map it to another field;
  - GCU glucose, total cholesterol, and uric acid, each mg/dL;
  - fasting/non-fasting context, fasting duration when applicable, and last-meal time when applicable;
  - Peak Flow Meter attempts I–III in L/min and highest valid result, calculated as the maximum valid attempt, never the average;
  - notes/follow-up, examining operator identity, and examiner and participant signature areas.
- A PDF generated from persisted MCU examination data containing the identity, examination metadata, all captured results and context, notes, and signature areas. Include the disclaimer: `HASIL SKRINING MERUPAKAN PEMERIKSAAN AWAL DAN BUKAN PENETAPAN DIAGNOSIS MEDIS.` The signature areas may be completed manually after printing; no electronic-signature subsystem is authorized.
- A suitable authorized operator print/download flow after save, using existing site/facility branding where available rather than hardcoding one facility.
- Relevant audit linkage when required by established MHCS patterns.
- Focused automated tests and implementation documentation needed for traceability.

### Out of scope

- Changes to radiography stages, DICOM/MPIPS, AI PACS, Image Gateway processing, Doctor Core, or doctor diagnosis/report finalization.
- Marking existing radiography tickets or stages complete because an MCU examination or PDF is complete.
- Replacing or weakening normal Registration → Basic Examination → Radiography → downstream clinical workflows.
- A parallel identity, authentication, registration, or general workflow framework.
- Citizen/member permanent portal, new messaging/WhatsApp behavior, payment behavior, or digital/electronic signatures.
- Deployment, production-data migration, production mutation, PR creation, merge, release, unrelated refactoring, or changes to another repository.

### Preserved behavior

- Existing Member identity/MRN and registration, walk-in, eligibility, consent, and participant verification semantics remain authoritative and are reused where suitable.
- Site Staff authorization remains scoped to the existing authenticated identity, role, site, shift/assignment, and applicable participant/examination access boundaries.
- Existing sequential Registration, Basic Examination, Radiography, DICOM, AI PACS, and Doctor workflows remain functional and semantically unchanged.
- MCU episode completion does not imply completion of another clinical or imaging stage.

## Dependencies and assumptions

### Dependencies

- Implementation begins from the stated immutable `main` baseline, subject to Executor preflight and Planner/Reviewer resolution of any material drift.
- Existing Member and Operator identity, registration, authorization, persistence, audit, and UI mechanisms are available for reuse as observed at the baseline.
- The source screening form supplied with the Human Direction is the field and presentation reference.

### Approved assumptions

- Human Direction authorizes this separate One Stop MCU workflow while the Product Authority's existing staged pathway remains unchanged.
- The supplied screening disclaimer and measurements describe screening results, not a medical diagnosis.
- Site/facility identity should follow an existing reusable configuration/branding mechanism when present.

### Remaining approval requirements

- Planner/Reviewer inspection of this published task is required before Executor handoff, as directed by the task-authoring handoff.
- Any material clinical policy, identity/eligibility exception, digital-signature need, new consequential dependency, or architecture/product decision not resolved by this contract requires return to planning and designated approval.
- Normal review and any separate Release Gate remain applicable; this task does not authorize release.

## Required capabilities

- Repository read/write, local Git, and test execution.

## Execution constraints

- Reuse suitable existing Member, Operator, registration, authorization, audit, and PDF patterns. Do not introduce new identity/authentication models or a general workflow framework.
- Preserve units and source-form labels. Validate inputs at the trust boundary. Derive BMI and highest valid PEF deterministically from persisted inputs/results.
- Generate the PDF from persisted data, not only unsaved form state. Protect participant/examination access using existing site/operator authorization boundaries.
- Do not invent a clinical threshold, diagnosis, treatment recommendation, or interpretation for measurements.
- Do not choose a technical meaning for Microtoise without supporting repository/domain evidence. If no safe representation can be established without a material product decision, stop and return it to planning.
- Do not add a PDF library or other consequential dependency unless existing capabilities are inadequate and the required approval is obtained.

## Acceptance criteria

- [ ] An appropriately authorized Operator/Site Staff user can enter One Stop MCU without altering the normal screening pathway.
- [ ] An eligible existing participant can be verified/checked in using established MHCS semantics; applicable walk-in registration reuses canonical participant and registration models and does not duplicate identity.
- [ ] The operator can enter, validate, save, and retrieve every required MCU identity field, measurement, unit, GCU fasting/last-meal context, PEF attempt, note/follow-up, operator identity, and signature-area requirement listed in scope.
- [ ] BMI is derived correctly from weight and height in canonical units, with invalid or unusable height handled safely.
- [ ] Highest valid PEF equals the maximum valid attempt among I–III; invalid/missing attempts are not treated as valid measurements and values are never averaged.
- [ ] Saved data remain associated with the correct participant/examination and authorized site/operator context; unauthorized actors cannot read or mutate another participant's MCU examination.
- [ ] The PDF is generated from the saved examination, includes participant/examination metadata, all captured screening results/context and notes, both manual signature areas, and the specified screening-not-diagnosis disclaimer.
- [ ] An authorized operator can print or download that PDF through the intended flow after save; current site/facility identity is used where an established mechanism exists.
- [ ] Completing the MCU episode leaves existing radiography tickets/stages and downstream Doctor workflow states unchanged; all existing supported workflows remain functional.

## Verification requirements

### Required checks

- Focused unit/feature/integration tests for value validation, BMI boundaries/conversion, highest-valid-PEF behavior, persistence/participant association, site/operator authorization, and unauthorized access/mutation denial.
- PDF generation and content verification against persisted data, including required fields, disclaimer, signature areas, and browser print/download response.
- Relevant Operator UI/HTTP workflow verification from participant selection/check-in through save and PDF access.
- Targeted regression tests for Member registration/identity and existing Basic Examination → Radiography progression, plus affected DICOM, AI PACS, and Doctor workflow tests.
- Frontend build/type/lint checks if affected; backend tests, static analysis, and formatting checks if affected; repository-required full verification when triggered by policy.
- `git diff --check` and final scope/diff review.

### Required evidence

The Executor reports the exact governing task revision and implementation baseline, implementation revision/state, commands and observed results, tests changed, verification gaps, deviations, and blockers. Do not report unrun checks as passing.

## Stop conditions

The Executor MUST stop and return to planning if:

- Microtoise cannot be represented without inventing or materially changing its meaning;
- participant eligibility, consent, identity, clinical policy, ownership, or authorization requires a decision beyond existing approved semantics and this task;
- implementation would complete, bypass, or change the existing radiography/Doctor flow;
- suitable persistence/PDF/authorization patterns are unavailable and a material architecture or dependency decision is needed;
- baseline drift, a blocking dependency, security/privacy/data-integrity risk, or scope expansion invalidates safe execution.

## Side-effect authorization

The implementation is limited to this task's scope. It does not authorize commits, pushes, PRs, merges, deployment, release, production mutation, destructive operations, dependency installation, or external-system mutation. Any such action requires separate authorization under repository policy.

## Expected terminal outcome

Return a reviewable implementation revision/state and observed verification evidence (`Review Required`), or a precise planning escalation if a stop condition is reached. The Executor does not self-declare acceptance or release readiness.
