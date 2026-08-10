# Controlled-beta scope

## Objective and status

The beta objective is to validate a controlled, auditable MHCS service journey with a small set of known users and operators. It is not public, is not production readiness, and does not approve deployment, privacy/legal policy, external contracts, or deferred Work Package requirements.

The initial target user is an adult Member with an existing account and linked Member record. Accounts may be created through controlled development or beta seed data. Operators and administrators are controlled internal users. Teleradiology physicians and reporting services remain external participants.

## Delivery ownership and final scope

The main workstream owns Member, Member-owned administration, Operator, and
Operator-owned administration. The Image Gateway workstream owns Image Gateway
contracts, storage, processing, MPIPS and AI integration, publication behavior,
and Image Gateway operational administration. This delivery split does not
remove Image Gateway from the final MVP or change module ownership.

The final beta is the integrated product scope. It requires the Image Gateway
branch to merge into `main` and the merged workflow to pass the documented
integration gate; a workstream-local completion claim is not final beta
completion.

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

## 17 August clinic-day MVP addendum — approved 2026-08-10

Faliq Adlan, CTO, approved a bounded controlled deployment candidate for use on
Monday, 17 August 2026, with deployment targeted for Wednesday, 12 August. This
addendum supersedes the historical synthetic-data-only control only for the
defined 37-member B2B clinic-day flow. It does not alter the long-term Work
Package requirements or turn the controlled release into a general
production-readiness claim.

The approved flow is:

```text
B2B roster import and fixed booking
→ NIK login and forced temporary-password replacement
→ required profile, KTP, and photo completion
→ site-scoped identity verification and paper-consent confirmation
→ ticket issue and paired Printer Station print
→ paired LCD queue for Vital Signs and X-ray
→ approved vital-sign assessment
→ complete NPZ and matching-gain submission
→ durable MinIO acceptance
→ private background MPIPS conversion
```

The member visit is operationally complete after MinIO durably accepts the
complete capture set. Staff do not wait for AI, doctor activity, or MPIPS
conversion. An adult whose profile, KTP, or photograph is incomplete cannot
receive a ticket. A minor stops the import because guardian/dependent flows
remain excluded.

The assessment records blood pressure, temperature, height, weight, calculated
BMI, and the structured health questionnaire. Glucose, total cholesterol, and
uric acid are deferred from this release; they are not removed from the target
clinical specification.

Real roster data and credential delivery are allowed only after the deployment
has passed the approved release checks. The import produces unique random
temporary passwords, stores only their hashes, and makes first-password
replacement mandatory. A one-time credential spreadsheet may be handed to one
designated B2B contact outside the application; it must not be committed,
logged, retained by MHCS, or distributed to all members as a shared document.

The B2B partner supplies the roster as a local PDF under
`docs/mvp/import-data/`. For the 17 August import, a Member Administrator
manually transcribes it into the ignored CSV input defined by
`docs/mvp/import-data/README.md`. MHCS does not parse the PDF or accept the
roster as a browser upload.

The 17 August CSV maps its omitted administrative gender to the existing
`unspecified` Member value, maps `ktp_address` to the initial current address,
and does not persist birthplace. This avoids a schema change while retaining
the raw PDF as the local source for birthplace.

Members complete their profile, KTP, and photograph at the clinic on 17 August
before ticket issue. Pre-provisioning an account does not waive that gate.

The provided Docker server, HTTPS endpoint, and MinIO service are dependencies.
MHCS and MPIPS remain separate containers joined only through their external
private Docker network; MPIPS is not publicly exposed. The existing mandatory
security boundaries continue to apply without exception.

Before the real import, the release candidate must prove one synthetic end-to-
end flow: member login and profile gate, check-in, ticket print, LCD call,
vital-sign capture, NPZ/gain upload, MinIO durable acceptance, and MPIPS DICOM
conversion. The real Printer Station and LCD Station must be paired and tested,
and staff must complete a dry rehearsal. Failed upload remains an X-ray-stage
exception; permanent conversion failure must be visible to an administrator.

Member, Operator, Member administration, and Operator administration are owned
and delivered on `main`. Image Gateway and its AI/MPIPS integration are owned
by a separate branch. The branch must merge and pass the integrated release
gate before the 17 August clinic-day use; separation is a delivery
boundary, not an exclusion.
