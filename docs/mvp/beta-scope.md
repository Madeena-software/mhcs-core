# Controlled-beta scope

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
