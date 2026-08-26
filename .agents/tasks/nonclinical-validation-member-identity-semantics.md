---
title: MHCS Core Nonclinical Validation Member Identity Semantics
document_id: MHCS-TASK-NONCLINICAL-MEMBER-IDENTITY-SEMANTICS-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-26
scope:
  - explicit nonclinical validation Member identity semantics
  - safe registration and narrowly scoped booking eligibility
  - no production provisioning or Image Gateway behavior
authority_note: This task authorizes design and implementation of explicit nonclinical identity semantics and local verification only. It does not authorize production migration, provisioning, credentials, NPZ, Image Gateway, MPIPS, deployment, or release.
---

# Executable Task

## Task identity

**Task title:** Add explicit nonclinical validation Member identity semantics

**Task path:** .agents/tasks/nonclinical-validation-member-identity-semantics.md

**Task contract state:** Validated/Published upon immutable publication of this exact content.

**Delivery objective / Work Package / MVP:** Real NPZ production validation readiness prerequisite

**Owner / designated planning authority:** Faliq Adlan, CTO

## Delivery context

The accepted nonclinical production validation-context provisioning task is blocked at its Member identity feasibility gate.

Current registration requires a non-empty NIK, age-appropriate KTP/KIA verification input, and profile-photo verification input. MemberVerificationAssetService records verification assets through PrivateObject state and normal identity verification is derived from approved/current KTP or KIA plus profile photo. Mvp03BookingService requires identity_status=verified as well as active account, adult eligibility, and complete profile state.

Those semantics cannot truthfully represent a software/system validation subject without fake national identity, fake identity documents, fake profile photography, or unauthorized private verification objects.

This task defines the smallest explicit domain extension for one nonclinical validation subject. Normal human Member registration and verification semantics must remain unchanged.

The related tasks remain separate:

- .agents/tasks/nonclinical-production-validation-context-provisioning.md @ 50e8ff1f3ae1573a3d0d59ffa7aefdfb7286f6ac
- .agents/tasks/production-real-npz-end-to-end-validation.md @ 3f2692b8d94da7da951ddcf93afd22c75fabee7d

## Baseline and task revision

**Implementation baseline:** 50e8ff1f3ae1573a3d0d59ffa7aefdfb7286f6ac

**Task revision:** The full SHA of the commit containing this exact task content, supplied by publication metadata.

The task revision and implementation baseline are separate. The task revision must be resolved before implementation handoff.

## Objective

Add an explicit, nonclinical Member identity mode that can represent exactly the fixed validation subject purpose real-npz-e2e-v1 without claiming genuine national identity, KTP/KIA evidence, profile-photo verification, or clinical identity.

The extension must provide a controlled registration boundary for the later provisioner, preserve ordinary Member semantics, and allow only the exact marked validation subject to satisfy the identity portion of the normal booking pipeline.

## Authoritative inputs

### Governing authority

- .agents/AGENTS.md and .agents/software-workflow.md — delivery, evidence, and side-effect boundaries.
- .agents/context/project.md — application architecture and security boundaries.
- .agents/context/modules/member/project.md — Member identity, verification, profile, and booking ownership.
- .agents/tasks/nonclinical-production-validation-context-provisioning.md @ 50e8ff1f3ae1573a3d0d59ffa7aefdfb7286f6ac — blocked dependent objective.
- .agents/tasks/production-real-npz-end-to-end-validation.md @ 3f2692b8d94da7da951ddcf93afd22c75fabee7d — later validation objective.

### Observed implementation inputs

- MemberRegistrationData and MemberRegistrationService.
- RegistrationSource, IdentityStatus, VerificationAssetType, and VerificationAssetInput.
- MemberVerificationAssetService and its identity-status synchronization.
- Mvp03BookingService and MemberContextResolver.
- Member external-identifier registration and existing Member/admin/operator display surfaces.
- Existing Member registration, verification, booking, and database-conformance tests.

### Requirement traceability

- NVI-001 → nonclinical identity is explicit and never represented as verified human identity.
- NVI-002 → normal human registration, verification, NIK protection, and booking eligibility remain unchanged.
- NVI-003 → only the fixed validation marker and registration mode can use the narrow booking exception.
- NVI-004 → validation identity registration creates no verification assets or PrivateObject data.

## Scope

### In scope

- A dedicated nonclinical identity status/value selected according to repository conventions.
- A dedicated registration source/value for the fixed validation purpose.
- A dedicated Member application registration contract for the fixed validation context.
- Minimal nullable schema changes only if source inspection confirms they are required for truthful representation.
- Unique use of member_external_identifiers namespace mhcs.validation and value real-npz-e2e-v1.
- Narrow booking eligibility policy for the exact conjunction of validation status, validation source, exact marker, expected account state, and absence of contradictory genuine identity fields.
- Fail-closed isolation from ordinary verification-asset operations.
- Required Member/admin/display labels for the nonclinical state where existing surfaces assume only pending_verification or verified.
- Focused tests and migration verification if a migration is required.

### Out of scope

- Fake NIK, KK, KTP, KIA, profile photograph, patient/customer identity, clinical consent, diagnosis, or medical identity evidence.
- Treating synthetic data as genuine identity evidence.
- Creating member_verification_assets or PrivateObject data for the validation identity.
- Public registration routes, public lookup routes, generic synthetic-Member APIs, or arbitrary validation identity creation.
- Weakening normal booking eligibility for pending/unverified Members.
- Changing normal Member registration, KTP/KIA, profile-photo, approval, NIK uniqueness, or asset-grant behavior.
- Operator, Image Gateway, NPZ, MPIPS, DICOM, production provisioning, production migration execution, deployment, secrets, or real-NPZ workflow changes.
- Adding a generic patient-type framework, validation_context column, is_test flag, or metadata framework unless required by verified repository evidence and returned to planning.

### Preserved behavior and invariants

- A genuine Member with approved/current age-appropriate KTP/KIA and profile photo remains identity_status=verified.
- Online, walk-in, and administrator human registration retain their current authorization and required identity inputs.
- Normal Member verification-asset review, replacement, grants, synchronization, protected NIK storage, and duplicate NIK digest protection remain unchanged.
- Normal pending/unverified Members remain booking-ineligible.
- No ordinary Member can gain validation eligibility by changing one field.
- No validation identity can be transformed into a genuine verified identity by uploading synthetic or later fake verification assets.
- No Operator, Image Gateway, storage, queue, MPIPS, or NPZ behavior is introduced.

## Required design decisions

### Explicit identity and registration semantics

Select concise repository-consistent values for:

- a separate IdentityStatus value, conceptually nonclinical_validation;
- a separate RegistrationSource value, conceptually nonclinical_validation or validation.

Do not redefine verified and do not use pending_verification as a proxy for the validation subject.

The new registration source must be inaccessible from normal public registration routes and callable only from the later guarded validation-context provisioner.

### Schema safety

Inspect the current migrations and database constraints. If necessary, make only the genuine identity fields nullable for the explicit nonclinical mode:

- members.identity_document_type;
- members.encrypted_nik;
- members.nik_lookup_digest.

Do not rewrite existing rows. Preserve non-null genuine NIK digest uniqueness. Confirm SQLite and MySQL-compatible nullable-unique behavior and ensure non-null genuine identities remain protected.

If a migration is not required, do not create one. If a migration is required beyond this narrow nullable-field change, stop and return to planning.

### Registration boundary

Extend the existing Member application ownership rather than adding an ad-hoc database inserter.

A dedicated method/data contract is permitted, conceptually:

- MemberRegistrationService::registerNonclinicalValidation();
- NonclinicalValidationMemberRegistrationData.

The exact names must follow repository conventions.

The boundary must:

- require the fixed context real-npz-e2e-v1;
- require a stable deterministic operation identity;
- require no NIK, KK, KTP/KIA, or profile-photo VerificationAssetInput;
- create no member_verification_assets;
- call no PrivateObjectStore;
- emit normal audit evidence plus a clear nonclinical registration marker;
- be idempotent;
- fail closed on duplicate or contradictory marker;
- support no arbitrary synthetic Member creation;
- expose no public HTTP endpoint.

Synthetic structural fields that remain required must be deterministic and unmistakably nonclinical. They must not be real personal identity data.

### Marker and secure uniqueness

Use the existing Member external-identifier mechanism where semantically valid:

- namespace: mhcs.validation;
- value: real-npz-e2e-v1.

Require at most one Member for this exact marker. If the marker belongs to a normal Member, has a different source/status, has contradictory genuine identity fields, or appears on multiple Members, fail closed. Do not repair automatically.

### Account and booking eligibility

Separate identity verification from technical participation in the isolated validation pipeline.

If the validation account needs active/login-enabled state, document why and constrain it to the explicit nonclinical path. Account activation must not grant clinical identity trust.

Encapsulate identity eligibility in one Member-domain/application policy or helper. The validation exception must require all strong signals:

- explicit nonclinical identity status;
- explicit nonclinical registration source;
- exact mhcs.validation=real-npz-e2e-v1 marker;
- expected active account and adult/profile requirements;
- no genuine verification assets or contradictory genuine identity fields.

Do not change booking logic to accept every status other than pending_verification.

The validation subject may use a deterministic synthetic adult birth date only when clearly marked nonclinical and not presented as a real person.

### Verification-asset isolation

MemberVerificationAssetService must reject verification-asset operations for the nonclinical validation identity, or otherwise preserve the explicit nonclinical status without allowing it to become pending_verification or verified.

Choose the smallest robust implementation. Tests must prove:

- no validation verification asset is created;
- validation status cannot be approved through the ordinary asset path;
- normal genuine Member asset behavior remains unchanged.

### Display and administration safety

Find existing Member/admin/operator surfaces that assume only pending_verification and verified. Update only the minimal labels or formatting needed to ensure the nonclinical state is displayed clearly as Nonclinical validation and never as Verified Patient, Verified Identity, KTP-verified, or KIA-verified.

Do not expand UI scope beyond the affected display surfaces.

### Audit

Preserve all normal audit behavior for genuine registration. The explicit path must emit an audit event equivalent to:

- action: member.nonclinical-validation.registered;
- metadata: validation_context=real-npz-e2e-v1 and nonclinical=true.

Do not include NIK, KK, credentials, NPZ data, private-object keys, or raw identifiers in logs or audit metadata where avoidable.

## Retention and lifecycle

The identity extension creates only the records required for the explicit nonclinical Member identity and its normal account/audit/external-marker state. Verification assets and PrivateObjects are NOT_CREATED.

Records created by the later provisioning task remain governed by that task and are normally RETAINED. This task does not authorize cleanup, disablement, production migration, or deletion.

## Acceptance criteria

- [ ] Normal online, walk-in, and administrator Member registration semantics are unchanged.
- [ ] The validation identity has an explicit nonclinical status, never verified.
- [ ] The validation identity has an explicit validation registration source.
- [ ] Validation registration requires no NIK, KK, KTP/KIA, or profile-photo asset.
- [ ] No verification asset or PrivateObject is created for validation registration.
- [ ] The exact mhcs.validation=real-npz-e2e-v1 marker is unique and required.
- [ ] Duplicate, contradictory, partial, or inconsistent marker state fails closed.
- [ ] Validation registration is not reachable through a normal public Member route.
- [ ] Normal pending Members remain booking-ineligible.
- [ ] Normal verified Members remain booking-eligible.
- [ ] Only the exact valid validation conjunction can satisfy the identity portion of booking eligibility.
- [ ] Arbitrary synthetic/nonverified Members cannot use the exception.
- [ ] Validation identities cannot acquire genuine verification status through the asset path.
- [ ] Existing non-null NIK digest uniqueness and all existing rows remain valid.
- [ ] A migration, if required, is narrow, non-destructive, and locally tested on supported database semantics.
- [ ] No Operator, Image Gateway, NPZ, MPIPS, production, deployment, secret, or provisioning behavior is added.

## Verification requirements

Focused tests must prove:

1. normal KTP/KIA/profile-photo registration remains unchanged;
2. validation registration needs no NIK, KK, KTP/KIA, or profile-photo asset;
3. validation registration creates no verification asset or PrivateObject;
4. status and source are explicit nonclinical values;
5. the exact external marker exists and cannot be duplicated;
6. duplicate/inconsistent marker state fails closed;
7. no public route exposes validation registration;
8. normal pending booking remains rejected;
9. normal verified booking remains accepted;
10. only the exact validation conjunction is booking-eligible;
11. inconsistent validation status/source/marker is rejected;
12. validation identities cannot gain genuine verification through asset operations;
13. genuine verification, asset grants, protected NIK storage, and duplicate NIK protection remain unchanged;
14. existing rows need no rewrite;
15. no Operator/ImageGateway/NPZ/MPIPS behavior is added;
16. no production action occurs during tests.

If a migration is introduced, also verify forward migration, existing-row preservation, nullable-field semantics, non-null NIK uniqueness, and defined rollback behavior. Do not run a production migration.

Required local checks include focused tests, materially affected Member/booking tests, PHP syntax/formatting, route inspection proving no public registration route, vendor/bin/pint --test, git diff --check, and final diff inspection.

## Stop conditions

Stop and return to planning if:

- truthful synthetic identity semantics cannot be represented without fake evidence;
- implementation requires a broad schema redesign or generic metadata/type framework;
- a migration would rewrite or weaken existing genuine identity data;
- non-null genuine NIK uniqueness cannot be preserved;
- validation registration can be reached through public HTTP;
- arbitrary synthetic Members or markers can be created;
- normal pending/unverified booking behavior would be broadened;
- validation assets could become genuine verified identity;
- direct Member/user DB fabrication is required instead of an owned application boundary;
- PrivateObject, Image Gateway, NPZ, MPIPS, production, deployment, or secret scope is required; or
- authority, security, retention, or compatibility semantics are unclear.

## Side-effect authorization

### Authorized in this task

- Repository implementation of the explicit Member identity semantics and focused tests.
- A narrowly scoped migration only if required by the current schema and approved by this task.
- Local verification only.

### Not authorized in this task

- Production migration or production access.
- Creating Member records, verification assets, PrivateObjects, secrets, or validation context.
- Running the provisioner, seeders, or production workflows.
- Deployment, NPZ download/submission, Image Gateway, MPIPS, DICOM, S3, or storage operations.
- Changes to Operator, Image Gateway, deployment, network, IAM, or production configuration.

## Delivery sequencing

publish this identity-semantics task
→ Planner reviews task
→ Executor implements identity semantics and tests
→ Planner reviews implementation
→ separately authorize/deploy migration if required
→ resume nonclinical provisioning feasibility Gate B, Gate C, and Gate D
→ provision context with separate authorization
→ resume real-NPZ validation planning

## Expected terminal outcome

Review Required after implementation and verification evidence are available.

Planning Required for any unresolved schema, identity, booking, authorization, migration, or side-effect decision. Implementation acceptance does not authorize production execution or release.
