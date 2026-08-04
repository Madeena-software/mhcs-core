# Controlled-beta scope

## Objective and status

The beta objective is to validate a controlled, auditable MHCS service journey with a small set of known users and operators. It is not public, is not production readiness, and does not approve deployment, privacy/legal policy, external contracts, or deferred Work Package requirements.

The initial target user is an adult Member with an existing account and linked Member record. Accounts may be created through controlled development or beta seed data. Operators and administrators are controlled internal users. Teleradiology physicians and reporting services remain external participants.

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

### Admin Portal

The Admin Portal is part of the MVP. Its planned responsibilities are organization and site management; Member and Operator account management; approved role, permission, organization, and site assignment; service and operational configuration; controlled account activation/suspension/correction; status and failure visibility; audit and operational records; and beta configuration. It must preserve authorization, audit, account-state, and data-ownership rules and must not become an unrestricted database editor.

## External teleradiology boundary

The teleradiology physician is external to MHCS. The MVP includes no Doctor Portal, doctor dashboard, internal doctor assignment, internal doctor queue, internal doctor report authoring, doctor credentialing, doctor scheduling, or doctor-specific exposed workflow. Returned reports may use manual Operator upload/attachment or a later supported automated Gateway contract. Until the automated contract is approved and implemented, manual handling is only a planned beta fallback, not current functionality.

## Supported and unsupported flows

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

