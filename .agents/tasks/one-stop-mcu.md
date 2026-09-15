---
title: One Stop Service MCU Screening Workflow
document_id: MHCS-TASK-ONE-STOP-MCU-001
version: 1.2
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
  owner. The remediation/republication handoff dated 2026-09-15 makes
  `Form_Skrining_Rumah_Skrining_CV_Prestige_NIK(1).pdf` the superseding
  source-form reference for this screening output. It does not replace or
  alter the Product Authority's existing examination-to-radiography service
  pathway. Planner/Reviewer inspection is required before renewed Executor
  handoff.
---

# Executable Task

## Task identity

**Task title:** One Stop Service MCU Screening Workflow

**Task path:** `.agents/tasks/one-stop-mcu.md`

**Task contract state:** Validated/Published on publication of this exact content; this remediation revision requires Planner/Reviewer inspection before renewed Executor handoff.

**Delivery objective / Work Package / MVP:** Additive One Stop MCU screening episode.

**Owner / designated planning authority:** Human owner direction in the task-authoring handoff; Product Authority remains `Madeena-software/mhcs-business-docs`.

## Delivery context

The Human directs a One Stop Service MCU screening flow that can finish after saved examination results and a printable/downloadable screening PDF, without representing the existing radiography or Doctor workflow as complete. The current approved Product Authority defines the existing booking → examination → radiography → processing → result pathway and says future pathways require their own validation and authorization. This task captures that separate, additive pathway under the Human's explicit direction; it does not revise the current pathway.

## Baseline and task revision

**Implementation baseline:** `b1a2204a2571920a6c696575a6480aad5a1294fd` (`main`, verified against `origin/main` before branch creation).

**Task revision:** Full SHA of the commit containing this exact task content; resolved on publication. The task revision is distinct from the implementation baseline.

**Remediation review basis:** Implementation candidate `31d75aa054290dd443b9b22a5c463a46b467b0dc`; bounded corrections remain within the existing One Stop MCU objective. This revision does not authorize product-code changes until it is published and inspected.

## Objective

Enable an appropriately authorized Operator/Site Staff user to verify or register an eligible participant using established MHCS identity and registration semantics, record and persist the required MCU screening examination, and generate a printable/downloadable PDF from the saved examination. Completing this MCU episode MUST NOT complete, bypass, or otherwise change the normal radiography or downstream clinical workflow.

## Authoritative inputs

### Governing authority

- Human Direction in the task-authoring handoff dated 2026-09-15: the additive One Stop MCU flow, required source-form data, PDF outcome, acceptance criteria, exclusions, and implementation/side-effect limits.
- Human Direction in the task remediation/republication handoff dated 2026-09-15: `Form_Skrining_Rumah_Skrining_CV_Prestige_NIK(1).pdf` supersedes the previous screening-form reference for this output. The handoff supplies required identity fields, exact Rumah Skrining branding/contact details, consultation footer, mandatory three-attempt PEF rule, verification requirements, and side-effect boundaries.
- Human Direction in the preceding task remediation/republication handoff dated 2026-09-15: Microtoise is the instrument/method used to measure body height, not a separate clinical measurement; use one canonical numeric height and preserve the term as method/equipment context. This clarification remains in force.
- `Madeena-software/mhcs-business-docs` @ `645058e431f59c4450a136e72f140e6819b79f32`:
  - `docs/business/01-business-overview.md` — current examination-service pathway, actor journeys, and requirement that future pathways receive their own validation and authorization.
  - `docs/business/02-user-stories.md` — current Member and Site Staff interaction requirements, including registration, check-in, and basic examination.
  - `docs/business/03-system-responsibilities.md` — Member and Operator ownership, role boundaries, and separation of staged workflow responsibilities.
- `.agents/AGENTS.md`, `.agents/software-workflow.md`, and `.agents/context/project.md` — repository delivery contract, authority map, and system boundaries.
- `.agents/tasks/urgent-operator-field-operations.md` — established on-the-spot registration, check-in, consent, operator authorization, and preservation semantics already present at the implementation baseline.
- `.agents/tasks/operator-ai-pacs-integration.md` and the existing Image Gateway PDF/report implementation — observed examples of protected operator PDF delivery and PDF-generation patterns; technical evidence only, not product authority.

### Requirement traceability

- `MCU-H1` → Human Direction and superseding source-form decision: participant/examination identity, canonical NIK display in the PDF, examination date/time, and required measurements and context below.
- `MCU-H2` → Human Direction and remediation handoffs: deterministic BMI and highest-PEF calculations; body height is one canonical measurement in centimetres, Microtoise is method/equipment context, and all three valid PEF attempts are mandatory.
- `MCU-H3` → Human Direction and superseding source-form decision: persisted examination-backed PDF, Rumah Skrining identity/contact content, consultation follow-up footer, screening disclaimer, print/download flow, and manual signature areas without a digital-signature subsystem.
- `MCU-H4` → Human Direction plus Product Authority current pathway: additive completion must not alter or falsely complete radiography or downstream clinical stages.
- `MCU-H5` → Product Authority stories `US-STAFF-REG-001`, `US-STAFF-REG-002`, `US-STAFF-REG-004`, `US-STAFF-REG-006`, `US-STAFF-REG-007`, `US-STAFF-REG-008`, `US-STAFF-EXAM-001`, and existing Operator task: established identity, registration, check-in, consent, examination, site/operator authorization, and least-privilege semantics.

## Scope

### In scope

- An Operator entry point for the distinct One Stop MCU episode, reusing existing participant verification, eligibility, registration/check-in, site, and operator concepts where applicable.
- Persisted MCU examination data associated with the correct participant, examination, authorized site, and examining operator, with validation appropriate to the captured values.
- Required participant/examination identity: participant/patient name; date of birth and/or age from canonical data; sex; canonical participant NIK displayed in the screening PDF; examination date; examination time. NIK is an output identity field only. Internal MHCS identity, clinical linkage, database relationships, imaging identity, and existing system semantics continue to use established canonical identifiers, including MRN where applicable. Do not rename or replace MRN, use NIK as DICOM PatientID, create an MCU-specific NIK source, or ask the operator to re-enter a canonical NIK. If canonical NIK is unavailable, use established identity-validation behavior; do not fabricate NIK or invent an exception policy.
- Required measurements and context:
  - blood pressure systolic and diastolic, mmHg;
  - weight, kg; height, cm; temperature, °C;
  - BMI/IMT, kg/m², deterministically derived as weight (kg) / the single canonical body-height value² (m²), with height recorded in centimetres and converted to metres for calculation;
  - body height is the only numeric height measurement. Microtoise identifies the height-measurement instrument/method, not another physiological measurement; do not ask the operator to enter a duplicate Microtoise value or persist a second numeric height;
  - GCU glucose, total cholesterol, and uric acid, each mg/dL;
  - fasting/non-fasting context, fasting duration when applicable, and last-meal time when applicable;
  - three mandatory valid Peak Flow Meter attempts I–III in L/min and highest result, calculated as max(I, II, III), never the average. Each attempt must be present and a valid positive value; the examination cannot be saved if any attempt is missing or invalid;
  - notes/follow-up, examining operator identity, and examiner and participant signature areas.
- A PDF generated from persisted MCU examination data containing canonical participant NIK in the source-form identity block rather than MRN; examination metadata; all captured results and context; notes; and signature areas. Show the single persisted body-height result and preserve `Microtoise` as height-measurement method/equipment context; do not show a duplicate numeric height. Include the current Rumah Skrining identity/contact block: `Rumah Skrining CV Prestige`, `oleh PT Madeena`, `Jl. Lowanu No.68-72, Sorosutan, Kec. Umbulharjo, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55162`, and `Kontak Rumah Skrining: +62 897-7067-528`. Include the informational consultation/follow-up footer `Konsultasi hasil skrining via WhatsApp: dr. Noor Istichawari, M.M. (dr. Nunung), +62 822-3107-9219`. Include the disclaimer: `HASIL SKRINING MERUPAKAN PEMERIKSAAN AWAL DAN BUKAN PENETAPAN DIAGNOSIS MEDIS.` The signature areas may be completed manually after printing; no electronic-signature subsystem is authorized.
- A suitable authorized operator print/download flow after save. Reuse existing site/facility configuration or branding mechanisms when they represent this Rumah Skrining content correctly; otherwise use bounded presentation/configuration for this screening output only. Do not globally hardcode Rumah Skrining branding into unrelated sites or workflows.
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
- The superseding `Form_Skrining_Rumah_Skrining_CV_Prestige_NIK(1).pdf` source-form decision in the Human remediation/republication handoff dated 2026-09-15 governs screening-PDF identity and presentation. The handoff records the applicable fields and exact required branding/contact/footer content; preserve its one-page structure where practical without requiring pixel-perfect reproduction.

### Approved assumptions

- Human Direction authorizes this separate One Stop MCU workflow while the Product Authority's existing staged pathway remains unchanged.
- The supplied screening disclaimer and measurements describe screening results, not a medical diagnosis.
- The participant's canonical NIK is read from established MHCS identity/registration data when the form requires it; no separate MCU NIK record or manual re-entry is needed.
- The specified Rumah Skrining branding/contact block and consultation footer are authorized informational content for this One Stop MCU screening output only.
- Site/facility identity should follow an existing reusable configuration/branding mechanism when present.

### Remaining approval requirements

- Planner/Reviewer inspection of this republished remediation task is required before renewed Executor handoff.
- Any material clinical policy, identity/eligibility exception (including unavailable canonical NIK), digital-signature need, new consequential dependency, or architecture/product decision not resolved by this contract requires return to planning and designated approval.
- Normal review and any separate Release Gate remain applicable; this task does not authorize release.

## Required capabilities

- Repository read/write, local Git, and test execution.

## Execution constraints

- Reuse suitable existing Member, Operator, registration, authorization, audit, and PDF patterns. Do not introduce new identity/authentication models or a general workflow framework.
- Preserve units and source-form labels. Validate inputs at the trust boundary. Derive BMI and highest PEF deterministically from persisted inputs/results. Require all three PEF attempts as valid positive L/min values and reject missing or invalid attempts. Persist one canonical numeric body-height value in centimetres; treat Microtoise only as method/equipment context associated with that height, without a duplicate clinical measurement.
- Read NIK only from the established canonical participant/registration model for display in the screening PDF. Do not persist a duplicate MCU-specific NIK or change internal MRN relationships/semantics; do not use NIK as DICOM PatientID. If canonical NIK is unavailable where required, follow established identity-validation behavior and stop for planning if that leaves a policy decision.
- Include the exact Rumah Skrining identity/contact and informational consultation/follow-up footer specified in scope. Keep this presentation bounded to the One Stop MCU screening output; do not introduce outbound WhatsApp/API behavior or globally change unrelated site branding.
- Generate the PDF from persisted data, not only unsaved form state. Protect participant/examination access using existing site/operator authorization boundaries.
- Do not invent a clinical threshold, diagnosis, treatment recommendation, or interpretation for measurements.
- Use the approved Microtoise clarification: `Microtoise` means the instrument/method used to measure body height. If execution discovers a genuinely different clinical requirement or authoritative source showing that the supplied form uses `Microtoise` to mean something materially different from the established height-measuring instrument, stop and return to planning.
- Do not add a PDF library or other consequential dependency unless existing capabilities are inadequate and the required approval is obtained.

## Acceptance criteria

- [ ] An appropriately authorized Operator/Site Staff user can enter One Stop MCU without altering the normal screening pathway.
- [ ] An eligible existing participant can be verified/checked in using established MHCS semantics; applicable walk-in registration reuses canonical participant and registration models and does not duplicate identity.
- [ ] The operator can enter, validate, save, and retrieve every required MCU identity field, measurement, unit, GCU fasting/last-meal context, all three mandatory PEF attempts, note/follow-up, operator identity, and signature-area requirement listed in scope.
- [ ] A participant's canonical NIK is displayed in the PDF identity block; MRN remains the internal MHCS identity/linkage and is not substituted for the form's NIK field. No MCU-specific NIK persistence, MRN replacement, or DICOM PatientID change is introduced. Missing canonical NIK is not fabricated or manually re-entered for MCU.
- [ ] BMI is derived correctly from weight and height in canonical units, with invalid or unusable height handled safely.
- [ ] The operator records one canonical body-height value in cm; BMI uses that persisted value; the UI/PDF may identify Microtoise only as height-measurement method/equipment context; no duplicate or conflicting numeric height is persisted or presented.
- [ ] Save succeeds with three valid positive PEF attempts. Save is rejected when all are missing, when any one or two attempts are missing (including each individual I, II, or III case), or when any attempt is zero, negative, or invalid. Highest PEF equals max(I, II, III) and is never an average.
- [ ] Saved data remain associated with the correct participant/examination and authorized site/operator context; unauthorized actors cannot read or mutate another participant's MCU examination.
- [ ] The PDF is generated from the saved examination and includes canonical participant NIK (not MRN as the source-form identity field); all measurements including PEF I, II, III and highest; one persisted numeric height with Microtoise only as method/equipment context; notes; examiner identity; participant/examiner manual-signature areas; and the exact screening-not-diagnosis disclaimer.
- [ ] The PDF includes `Rumah Skrining CV Prestige`, `oleh PT Madeena`, `Jl. Lowanu No.68-72, Sorosutan, Kec. Umbulharjo, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55162`, `Kontak Rumah Skrining: +62 897-7067-528`, and the informational consultation footer `Konsultasi hasil skrining via WhatsApp: dr. Noor Istichawari, M.M. (dr. Nunung), +62 822-3107-9219`.
- [ ] An authorized operator can print or download that PDF through the intended flow after save. Rumah Skrining content applies only to this screening output and does not globally replace unrelated site/facility branding.
- [ ] No WhatsApp integration, automated messaging, booking, medical-chat, diagnosis, escalation workflow, or outbound WhatsApp/API mutation is introduced.
- [ ] Completing the MCU episode leaves existing radiography tickets/stages and downstream Doctor workflow states unchanged; all existing supported workflows remain functional.

## Verification requirements

### Required checks

- Focused unit/feature/integration tests for value validation, BMI boundaries/conversion, highest-valid-PEF behavior, persistence/participant association, site/operator authorization, and unauthorized access/mutation denial.
- PEF tests: three valid attempts save successfully; missing I, missing II, missing III, all missing, and zero/negative/invalid attempts are each rejected; highest equals max(I, II, III) and not the average.
- Identity/privacy tests: PDF NIK is sourced from canonical participant identity; expected NIK appears and MRN is not substituted in the source-form identity block; internal participant/MRN relationships stay unchanged; no MCU-specific NIK storage exists; an unauthorized operator cannot obtain another participant's PDF/NIK.
- PDF generation and extracted/rendered content verification against persisted data, including Rumah Skrining CV Prestige, PT Madeena, the exact address and contact number, participant NIK, all screening measurements, Microtoise context, all three PEF attempts and highest result, notes, examiner and participant manual-signature areas, screening disclaimer, dr. Noor Istichawari, M.M. (dr. Nunung), consultation WhatsApp number, and browser print/download response.
- Where practical, compare output structure with the superseding one-page source form without requiring pixel-perfect reproduction.
- Verify that only one canonical numeric height is persisted, BMI derives from it, the PDF contains the expected persisted height, and Microtoise appears only as method/equipment context when represented; verify that no duplicate clinical height value is introduced.
- Relevant Operator UI/HTTP workflow verification from participant selection/check-in through save and PDF access.
- Targeted regression tests for Member registration/identity and existing Basic Examination → Radiography progression, plus affected DICOM, AI PACS, and Doctor workflow tests.
- Frontend build/type/lint checks if affected; backend tests, static analysis, and formatting checks if affected; repository-required full verification when triggered by policy.
- `git diff --check` and final scope/diff review.
- Changed-scope review and relevant tests confirm that no WhatsApp integration or outbound API action is introduced.

### Required evidence

The Executor reports the exact governing task revision and implementation baseline, implementation revision/state, commands and observed results, tests changed, verification gaps, deviations, and blockers. Do not report unrun checks as passing.

## Stop conditions

The Executor MUST stop and return to planning if:

- execution discovers a genuinely different clinical requirement or an authoritative source showing that the supplied form uses `Microtoise` to mean something materially different from the established height-measuring instrument;
- participant eligibility, consent, identity, clinical policy, ownership, or authorization requires a decision beyond existing approved semantics and this task;
- implementation would complete, bypass, or change the existing radiography/Doctor flow;
- suitable persistence/PDF/authorization patterns are unavailable and a material architecture or dependency decision is needed;
- baseline drift, a blocking dependency, security/privacy/data-integrity risk, or scope expansion invalidates safe execution.

## Side-effect authorization

The implementation is limited to this task's scope. It does not authorize commits, pushes, PRs, merges, deployment, release, production mutation, destructive operations, dependency installation, or external-system mutation. Any such action requires separate authorization under repository policy.

## Expected terminal outcome

Return a reviewable implementation revision/state and observed verification evidence (`Review Required`), or a precise planning escalation if a stop condition is reached. The Executor does not self-declare acceptance or release readiness.
