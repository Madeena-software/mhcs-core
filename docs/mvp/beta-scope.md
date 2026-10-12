# Admin-first operational MVP scope

## Admin-first operational scope

The human-directed priority is one persistent internal Admin login and a unified operational dashboard, recorded in [MVP-DEC-044](decision-log.md#admin-first-priority-decision). This is the approved documentation/planning direction for eventual real patient operations. It is not approval to expose unverified clinical, privacy, authentication, import, migration, or production behavior. The [current authority and task status](README.md#current-authority-and-status) define this documentation execution.

| Target outcome | Domain responsibility | Existing baseline evidence / target distinction | Open dependency |
|---|---|---|---|
| Admin manages sites, shifts, Members, and examination assignments | Admin presentation over Member/Operator; Doctor owns clinical assignment authority | The existing `/admin` provider declares Member/Operator resources. A complete unified operational dashboard is a target, not proved by that provider. | MVP-GAP-027: capability/assignment scope and account transition |
| Manual registration and planned CSV/XLSX bulk import with downloadable templates and validation | Member identity/booking with authorized Operator/Admin actions | FIELD-OPS-001/002/003 record field registration/shift capabilities. The historical one-time roster and MEM-214/MEM-228 are references, not a generic CSV/XLSX specification. | MVP-GAP-032: schema, matching, conflict/invalid-row policy, transaction and privacy rules |
| Authenticated DICOM ingestion feeds a centralized worklist | Operator session association; Image Gateway imaging/processing | Authenticated Grabber routes and Operator study worklist routes exist. The centralized Admin worklist is desired behavior. Preserve both direct DICOM and legacy NPZ/MPIPS paths. | MVP-GAP-027; retain FIELD-OPS-007/008/009 and ingestion contracts |
| Admin monitors incoming studies, processing, and report status | Image Gateway processing; Doctor clinical reports | Operator AI-report actions exist under the scoped integration task. A status surface grants no unrestricted access to clinical contents. AI PDFs are unverified screening output. | MVP-GAP-029: access matrix and screening/finalization/publication distinction |
| Members receive authorized finalized results through secure time-limited links without permanent portal accounts | Member recipient/guardian authority, Doctor finalization, Image Gateway publication | Business US-MEMBER-003/009/016/017/018/019 establish the target. Permanent beta portal foundations are historical implementation; their removal or migration is not authorized. | MVP-GAP-027/029: recipient rules, signing, link expiry/revocation/disclosure/recovery |
| Radiographers/radiologists use temporary authorized workspaces | Operator/Doctor role and assignment boundaries | Business US-STAFF-SHARED-002 and US-DOCTOR-SHARED-002/004 support the direction. Clinical authentication, permitted actions, session lifecycle, qualification, and finalization remain open. | MVP-GAP-028/029: credentials and clinical authority |
| Clinical identities, assignments, reports, and access events remain attributable and auditable | All owning domains | Retain existing audit, identity and lineage controls; a shared Admin surface must not collapse responsible clinical actors or approve shared credentials. | MVP-GAP-028/029/031: delegation, access purposes, privacy and audit |

Earlier source observations were at `b1a2204a2571920a6c696575a6480aad5a1294fd`: `app/Providers/Filament/AdminPanelProvider.php`, `routes/api.php`, and `routes/web.php`. Referenced task contracts establish scoped intent and lineage; they do not alone prove runtime correctness, acceptance, or release. No application verification was performed in that earlier documentation execution. The new Draft contract records the current planning cycle’s bounded synthetic Admin regression; it proves no board implementation.

### Product Owner target and first functional slice

The direct 2026-10-13 Product Owner handoff recorded in [MVP-DEC-045–047](decision-log.md#admin-first-product-and-first-functional-slice--2026-10-13) approves the following **target product direction**, not delivery completion. The earlier scope table and dated evidence remain traceability, with these later refinements controlling target planning:

- Central Admin coordinates operations over existing domain owners. One canonical backend **Patient** replaces legacy Member terminology only through separately approved controlled refactoring, without duplicating Patient and Member identities; frontend labels are **Peserta Skrining** or **Pasien** by context. Existing Member models, accounts and accepted registration remain unchanged in the first slice.
- Support internal **SIM + Teleradiology** and external **Teleradiology-only** integration modes. Authenticated Grabber DICOM ingestion remains the ingestion boundary; external SIMRS contracts and correlation need separate design and verification.
- Radiographers complete examination-scoped metadata. Radiologists authenticate individually and receive assigned worklists. AI-only screening and AI + Radiologist services have distinct AI-processing, screening-completion, clinical-finalization and patient-publication states. Admin coordinates escalation of review-required AI examinations; clinical judgment remains attributable to the authorized clinician.
- Secure examination-scoped result links require expiry, revocation, amendment suspension and notification tracking. Mechanisms, clinical release criteria and recipient/privacy rules require separate review. Legacy Operator portals and identities retire eventually through controlled migration, not through this board.

**First priority:** [Draft Admin Facility-Session Operations Board](../../.agents/tasks/admin-facility-session-operations.md), under MVP-GAP-027. Journey: `/admin/login` → `/admin` → managed facility and facility-local date → persisted schedules with service, start/end, status, quota and authorized capacity/booking/arrival summaries → existing authorized facility/schedule create/edit → return and refresh persisted state. Registration and authorized detail pages remain reachable. Consider all facilities under MHCS management with exact current permissions; neither panel access nor selected facility is a grant.

The board performs no booking, arrival, queue or clinical mutation and duplicates no scheduling logic. Unauthorized/unavailable summaries are distinct from verified zero. D1–D3 now have explicit Product Owner approval under MVP-DEC-048–050: managed-facility/history scope, facility-local session-start dates, and independently authorized operational summary categories. X1 Option A is now Product Owner-selected under MVP-DEC-052: include the narrow existing-resource booking-read/count-label/revocation correction. Its mechanism and the D4 query/enforcement/audit details, scoped arrival-reference validation and small-count disclosure remain unapproved and block T5. The task contains the source-backed D4 candidate and AC1–AC8 readiness matrix; no application implementation or policy sign-off is claimed. PR #8 is recommended for supersession only; this cycle does not modify, close or merge it. Patient migration, external SIMRS, radiologist workspace, AI/report safety and publication remain separate contracts. [MVP-GAP-035/036](beta-gap-register.md#ai-report-safety-and-provenance-follow-up) preserve current source findings without asserting any real-patient incident.

### Canonical Patient identity target

[MVP-DEC-051](decision-log.md#product-owner-refinement-approval--2026-10-13) records the complete Product Owner direction. These are target requirements for a separate identity contract, not an Admin-board migration:

- **Patient is the single canonical backend identity entity.** Refactor/migrate legacy Member through controlled compatibility work; do not create a parallel identity that duplicates the same person.
- **No permanent login account is required** for a Patient. Clinical identity and authenticated access remain distinct responsibilities.
- **MRNs are facility-scoped.** The future identifier/matching architecture must preserve historical identifiers and their facility context.
- **NIK may be unavailable at initial intake.** Do not fabricate it or treat absence as a safe identity match.
- **Provisional Patient identity must be supported**, with later reconciliation defined in a separate approved contract.
- **Ambiguous matches require controlled review**; no automatic unsafe merge or implicit identity consolidation.
- **Preserve historical examinations, relationships and references**, including compatibility for existing associations and integrations.
- **Preserve auditability** across identity changes and reconciliation; migration does not authorize rewriting historical evidence.

Current Member implementation binds `user_id`, uses a globally unique MRN field, and accepted adult Admin registration requires NIK. The scoped nonclinical NIK exception is not general intake approval. These differences remain explicit target-versus-current gaps, not historical requirement rewrites or authorization to change the board's registration. Patient account/MRN/matching/provisional-state/schema mechanisms and migration approvals must be planned separately with Product, technical/data and privacy/security review.

### Preserved boundaries and exclusions

- Admin is a presentation/routing surface. Member, Operator, Doctor, and Image Gateway retain domain-owned records, authorization, mutations, and audit.
- Preserve server-derived role/site/shift/case/ownership checks, MRN/NIK separation, consent history, validation, idempotency, private binaries, sanitized logs, and clinical accountability. Locators and link possession confer no clinical identity or signing authority.
- Processing completion, AI output, doctor-finalized reports, and authorized Member release are distinct. No clinical AI/report access exception is generalized by this scope.
- Reusable consent/basic-examination bypass and earlier storage/DICOM-access exceptions retain their scoped task/decision provenance; conflicts with business sources remain open under MVP-GAP-030/031.
- No public self-registration, account deletion/migration, data import, new report-finalization/authentication mechanism, production-data use, deployment, or release is authorized by this documentation rebaseline. CSV/XLSX fields and transaction/error policies are deliberately unspecified until approved.
- Messaging/channel selection, payments/payouts, broader B2B agreements, child/guardian exposure, specialty expansion, FHIR profiles, and full Work Package conformance remain future or separately governed obligations. Reprioritization does not retire them.

See [active gaps](beta-gap-register.md#admin-first-gaps-and-authority-reconciliation) for owners, controls, and resumption triggers; [roadmap](roadmap.md#admin-first-planning-sequence) for planning dependencies.

## Historical controlled-beta scope — retained

Everything below records the August controlled-beta scope and its dated approvals. Its portal-first components, Doctor exclusion, deadlines, storage wording, and rehearsal limits describe that earlier period. MVP-DEC-044 supersedes their active priority only; policy conflicts remain explicitly open. The original sections and anchors remain for historical traceability and are not current readiness claims.

## Objective and status

The beta objective is to validate a controlled, auditable MHCS service journey with a small set of known users and operators. It is not public, is not production readiness, and does not approve deployment, privacy/legal policy, external contracts, or deferred Work Package requirements.

## Current controlled-MVP context — reconciled 2026-08-21

The current operational period is the controlled 27–28 August Prestige
rehearsal. A bounded production deployment, fresh three-target fixture, and
canonical verification now exist. This is bounded operational evidence only:

- MVP completion does not equal full Work Package completion.
- MVP completion does not equal full production, security, privacy, or release
  conformance.
- A bounded deployment does not equal WP-28 completion.

Current observed implementation is broader than the historical pivot: the
bounded Member and Operator slices are accepted, the clinic-day Operator
journey reaches X-ray readiness, and Image Gateway capture, queued MPIPS
processing, DICOM persistence, study viewing, and authenticated download exist.
Report/external-teleradiology workflow, Member publication, generic B2B
capability, complete operational administration, and full conformance remain
open or partial as recorded in the active roadmap, ledger, and gap register.

The initial target user is an adult Member with an existing account and linked Member record. Accounts may be created through controlled development or beta seed data. Operators and administrators are controlled internal users. Teleradiology physicians and reporting services remain external participants.

## Delivery ownership and final scope

The current delivery model is sequential on `mhcs-core` `main`. Member,
Operator, Image Gateway, and shared administration remain modules and ownership
boundaries within the modular monolith. MPIPS remains a separate repository and
private processing service; it is the only internal network service boundary.

The final beta is the integrated product scope. A bounded component or
deployment result is not final beta completion; the remaining integrated
verification, security/privacy, release, and Work Package gates still apply.

## Components

### Member Portal

The first vertical slice is:

\`\`\`text
MVP-01
existing adult Member account
→ login
→ mandatory first-password replacement when required
→ complete permitted profile fields
→ Member dashboard
→ logout
\`\`\`

Later slices may add service catalogue, booking, queue/status visibility, and approved result visibility. The Member Portal initially exposes none of public self-registration, online registration, child or guardian access, identity-document verification, protected-identity editing, payments, reward points, or unrelated clinical workflows.

### Operator Portal

The Operator Portal is the primary internal operational interface. Planned responsibilities are examination requests, scheduling, queues, check-in and attendance, operational examination state, study association, teleradiology status, Image Gateway failure monitoring, controlled report receipt/upload, approved publication, corrections, and audit evidence. These capabilities are incremental and are not claimed implemented by this document.

### Image Gateway

The Image Gateway owns the imaging-system boundary: study intake or identification, examination correlation, identifier and routing validation, ingestion/transfer state, external routing, supported callbacks, retryable and terminal failure visibility, idempotency, auditability, and traceability. No DICOM, PACS, RIS, HL7, FHIR, vendor, or teleradiology conformance is claimed without a later bounded task and repository evidence.

### Shared administrator interface and module-owned administration

The MVP plans one administrator-facing application interface, which may use the
shared Filament `/admin` surface where applicable. This is a shared entry and
presentation surface, not a separate business domain, independently deployed
administrator application, or claim that a complete panel is already
implemented. Separate panels or URLs may be introduced later only through an
explicit architecture decision and implementation task.

One authenticated administrator account may have more than one authorized
module-administration capability. Authorization remains capability- and
module-specific. Shared navigation or presentation does not imply shared data
or business-rule ownership. Each module's administration must invoke its
approved application boundaries for authorization, state transitions,
configuration, projections, and audit behavior. Shared platform administration
is limited to genuinely shared primitives, and a generic administrator must not
directly edit unrelated module tables.

#### Member administration

Member administration owns Member accounts and Member-owned identity
administration; Member-owned service offerings, schedules, bookings, and later
Member commercial configuration when included by an MVP task. It uses Member-
owned application services and authorization and does not directly mutate
Operator or Image Gateway records.

#### Operator administration

Operator administration owns Operator accounts and assignments; Operator-owned
organizations, physical sites, protocol configuration, queue exceptions, and
operational controls. It uses Operator-owned application services and
authorization and does not directly mutate Member or Image Gateway records.

#### Image Gateway operational administration

Image Gateway operational administration owns processing status, conversion
jobs, retry and terminal-failure handling, exceptional compliance operations,
and storage and publication operational visibility. It is administrator-only
operational access, not a separate end-user application. It does not directly
mutate Member bookings or Operator queue ownership.

## External teleradiology boundary

The teleradiology physician is external to MHCS. The MVP includes no Doctor Portal, doctor dashboard, internal doctor assignment, internal doctor queue, internal doctor report authoring, doctor credentialing, doctor scheduling, or doctor-specific exposed workflow. Returned reports may use manual Operator upload/attachment or a later supported automated Gateway contract. Until the automated contract is approved and implemented, manual handling is only a planned beta fallback, not current functionality.

## Initial pivot exposure baseline (historical)

This section records the controlled-beta starting state on 2026-08-05, not
current implementation status. Determine current delivery status from
repository evidence, `roadmap.md`, `work-package-status.md`, and
`beta-gap-register.md`.

Initially supported for implementation: controlled adult Member access and profile completion through MVP-01. The four components are planned for incremental delivery.

Initially unsupported or excluded: public/online registration, B2B bulk import, children and guardians, identity verification UI, service requests and booking, queue/attendance, study ingestion/correlation, external routing, automated report return, Member result visibility, payments, and all internal Doctor workflows.

## Beta data and temporary controls

- Use controlled or synthetic beta data only; never use real credentials, NIK/KK, identity images, patient records, or external teleradiology data in this planning task.
- Use existing account/User-Member ownership and shared authenticated context; do not expose online registration or add a public route.
- Keep protected identifiers and verification assets private, use approved authorization and audit boundaries, and do not expose raw object keys or clinical binaries.
- Use manual Operator report handling only after a later task approves that operational path; do not describe it as implemented here.
- A fresh beta database is permissible only when consistent with project and deployment policy; it does not resolve the forward-only UUID migration approval boundary.
- Production object storage, credential delivery, retention, privacy procedure, deployment, and external integration contracts remain unresolved unless later repository evidence closes them.

## Mandatory security boundaries

Every exposed beta flow must retain server-derived actor, role, site, case, purpose, and ownership authorization; generic enumeration-resistant authentication; protected identifier separation; mandatory password replacement; private encrypted-object access; append-only audit evidence; sanitized logs; idempotency and transaction boundaries; and fail-closed external/image boundaries. No MVP deferral may weaken these controls.

## Expansion and exit criteria

Expand the beta only after the relevant MVP task has focused tests and evidence, the gap register is updated, ownership and authorization are verified, unresolved approval boundaries are identified, and the owner approves the next scope. Before controlled beta deployment, run the required integration/release verification, resolve critical gaps, approve migration/deployment/privacy/retention decisions, and record the deployment decision. Passing MVP tasks alone is never a production-readiness claim.

## Historical 12 August MVP delivery target — Operator priority — approved 2026-08-10

This section records the approved target at that date, not the current status.
Its former parallel Image Gateway branch language is superseded by
MVP-DEC-031; current delivery is sequential on `main`.

Faliq Adlan, CTO, set Wednesday, 12 August as the delivery target for all
active MVP work. The active priority is a usable Operator site flow; this does
not expand an individual task's scope or create a general production-readiness
claim.

The approved Operator flow is:

```text
site selection
→ site-scoped arrival and identity verification
→ paper-consent confirmation
→ ticket issue and paired Printer Station print
→ paired LCD queue for vital signs and X-ray readiness
→ approved vital-sign assessment
→ private paper-questionnaire photograph
→ X-ray readiness
```

The assessment records blood pressure, temperature, height, weight, calculated
BMI, and the structured health questionnaire. Glucose, total cholesterol, and
uric acid are deferred; they are not removed from the target clinical
specification. The paper form remains the structured source. MHCS records only
completion and one private JPEG or PNG image; it performs no OCR or AI
extraction and never exposes the image through Member, LCD, or queue surfaces.

B2B import, real roster data, credential delivery, Member profile completion,
NPZ/gain submission, MinIO acceptance, DICOM conversion, AI, and MPIPS are not
part of this objective. They remain separate approved work or release
decisions.

Member, Operator, Member administration, and Operator administration are owned
and delivered on `main`. Image Gateway and its AI/MPIPS integration are owned
by a separate branch. That separation remains a delivery boundary, not an
exclusion from future integrated verification.
