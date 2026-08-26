---
title: MHCS Core Nonclinical Production Validation Context Provisioning
document_id: MHCS-TASK-NONCLINICAL-VALIDATION-CONTEXT-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-26
scope:
  - one deterministic nonclinical production validation context
  - normal authenticated Operator-to-Image-Gateway readiness
  - no NPZ capture or production validation execution
authority_note: This task authorizes implementation of a bounded console-only provisioning capability and focused verification. It does not authorize production execution, credential creation, fixture download, NPZ submission, deployment, or release.
---

# Executable Task

## Task identity

**Task title:** Provision one nonclinical production context for the real NPZ validation

**Task path:** .agents/tasks/nonclinical-production-validation-context-provisioning.md

**Task contract state:** Validated/Published upon immutable publication of this exact content.

**Delivery objective / Work Package / MVP:** Real NPZ production validation readiness

**Owner / designated planning authority:** Faliq Adlan, CTO

## Delivery context

The async private-object promise incident remains closed as:

PRODUCTION PRIVATE OBJECT ASYNC PROMISE ISSUE — SOLVED

The related real-size NPZ validation task is accepted but blocked because the repository has no isolated nonclinical member/operator/admission context that can reach the normal authenticated Image Gateway capture flow.

Observed normal application flow:

MemberRegistrationService::register()
→ Mvp03BookingService::createForCurrentMember()
→ OperatorArrivalService::confirm()
→ OperatorCheckInTicketService::issue()
→ claimBasicExamination()
→ callBasicExamination()
→ startBasicExamination()
→ completeBasicExamination()
→ claimXray()
→ callXray()
→ authenticated ImageGatewayController::captureStore()

Existing normal functionality cannot currently bootstrap this complete chain:

- no suitable public/normal production member-registration route exists;
- the Member admin surface is not a member-creation workflow;
- existing Operator administration does not provision the complete member side;
- no dedicated nonclinical production operator/admission exists.

MvpOperatorSeeder is not an acceptable solution. Its production override is broader than this objective, creates several synthetic users/sites/assignments, uses fixture-oriented credential handling, and requires production seeding authority.

## Baseline and task revision

**Implementation baseline:** 3f2692b8d94da7da951ddcf93afd22c75fabee7d

**Related governing task:** .agents/tasks/production-real-npz-end-to-end-validation.md @ 3f2692b8d94da7da951ddcf93afd22c75fabee7d

**Task revision:** The full SHA of the commit containing this exact task content, supplied by publication metadata.

The task revision and implementation baseline are separate. The task revision must be resolved before an Executor is handed this task.

## Objective

Design and implement the smallest repository capability that provisions exactly one deterministic, clearly marked nonclinical validation context sufficient for the later real-NPZ acceptance task, while stopping before Image Gateway capture.

The resulting context must support:

- a dedicated validation Member identity;
- a separate dedicated validation Operator identity;
- a valid site and schedule relationship;
- normal booking, arrival, check-in, ticket, and advance queue admission;
- normal basic-examination progression;
- normal X-ray claim/call;
- normal Operator login/session and active-site resolution; and
- later use of ImageGatewayController::captureStore() without authorization bypass.

Provisioning itself MUST create no capture, NPZ object, processing job, or MPIPS request.

## Authoritative inputs

### Governing authority

- .agents/AGENTS.md and .agents/software-workflow.md — delivery, evidence, and side-effect boundaries.
- .agents/context/project.md — application architecture and security boundaries.
- .agents/context/modules/member/project.md — Member identity and booking ownership.
- .agents/context/modules/operator/project.md — Operator authorization and queue ownership.
- .agents/context/modules/image-gateway/project.md — Image Gateway submission boundary.
- .agents/tasks/production-real-npz-end-to-end-validation.md @ 3f2692b8d94da7da951ddcf93afd22c75fabee7d — dependent validation objective and runtime separation.

### Observed implementation inputs

- MemberRegistrationService::register() and MemberAuthorization.
- Mvp03BookingService::createForCurrentMember().
- OperatorArrivalService::confirm().
- OperatorCheckInTicketService::issue().
- OperatorWorklistService basic-examination and X-ray transitions.
- InteractiveMemberLoginService, InteractiveOperatorAccessService, and OperatorAuthorization.
- ImageGatewayController and ImageGatewayCaptureService.
- Existing Member and Operator feature tests and deployment evidence.

### Requirement traceability

- NVC-001 → exactly one deterministic nonclinical context is provisioned.
- NVC-002 → normal domain services and authorization remain the only workflow boundaries.
- NVC-003 → credentials, audit, retention, and secure handoff do not expose secrets or raw application identifiers.
- NVC-004 → provisioning stops before Image Gateway capture and all NPZ/MPIPS behavior remains out of scope.

## Scope

### In scope

- One guarded console-only Artisan command as the privileged external boundary.
- One focused application orchestration service if existing service composition requires it.
- Reuse of existing Member and Operator services wherever they own the needed invariant.
- Exactly one fixed semantic context key, such as real-npz-e2e-v1.
- Separate validation Member and Operator identities, clearly marked as nonclinical.
- Deterministic site, schedule, booking, point-balance, arrival, ticket, admission, assignment, and audit handling required by the normal flow.
- Normal Operator authentication and active-site resolution.
- Sanitized command output and secure server-side handoff for the later workflow.
- Focused static/feature tests for all security, idempotency, scope, and no-capture invariants.

### Out of scope

- Any public HTTP/API route or unauthenticated access path.
- NPZ download, fixture integrity checks, capture submission, private-object storage, ProcessCaptureSet, MPIPS, DICOM, or production E2E execution.
- Direct SQL fabrication of Member, booking, ticket, admission, or Operator state.
- Production seeder execution, MHCS_ALLOW_PRODUCTION_MVP_SEED mutation, or generic production seeding.
- Creation of arbitrary users, Members, Operators, sites, schedules, context keys, roles, permissions, or admission IDs from runtime input.
- Deployment, environment/configuration mutation, schema migration, network, MinIO, IAM, bucket, or secret-store changes.
- Automatic scheduling, startup execution, retry, rerun, cleanup, or deletion of retained records.
- Reopening or revalidating the solved async promise incident.

### Preserved behavior and invariants

- OperatorAuthorization remains the source of Operator identity, role, permission, and active-site authorization.
- The normal booking, arrival, ticket, queue, claim, call, and capture controllers/services remain unchanged in behavior.
- No validation capability is reachable through normal HTTP traffic.
- No Administrator permissions are granted to the validation Operator.
- No actual patient, customer, clinical identity, consent, diagnosis, or examination is used.
- Provisioning stops before capture; no capture set, object, worker job, MPIPS request, or DICOM study is created.
- Existing retention policy remains authoritative; no raw database or object deletion is introduced.

## Dependencies and assumptions

### Dependencies

- Existing domain services and relationships can represent the context without a schema migration.
- A separately named deployment secret can be added through the approved secret-management process; its value is not part of this task.
- A fresh explicit one-time production authorization is obtained after implementation review and before executing the command.
- The later real-NPZ task remains separately reviewed and authorized.

### Approved assumptions

- The current repository revision is 3f2692b8d94da7da951ddcf93afd22c75fabee7d.
- Mvp03BookingService requires an active, verified, complete Member and may require Madeena Points.
- Existing domain services emit audit/outbox records for their owned transitions.
- The validation context may be retained as audit evidence when no safe domain cleanup exists.

### Remaining approval requirements

- Planner/Reviewer approval of the implementation and verification evidence.
- Fresh explicit one-time authorization before production provisioning.
- Separate authorization immediately before the later real-NPZ fixture download/submission.
- Any migration, new permission, financial-like balance mechanism, or unresolved identity-data decision returns to planning before implementation.

## Required design decisions

### Console boundary and deterministic identity

Use a console-only command, conceptually:

    php artisan mhcs:provision-nonclinical-validation-context

The command may accept no context argument, or accept and validate only the one exact constant. It MUST reject arbitrary context keys and MUST NOT accept arbitrary user/member/operator/site/admission IDs, emails, names, roles, or permissions.

The fixed context marker must use existing supported fields, operation/source markers, external-identifier namespace, and audit metadata where possible. Do not add a validation_context column unless implementation proves existing fields insufficient; a migration is a planning escalation.

### Identity data

The implementation must inspect the exact registration contract. It MUST NOT use real NIK, KK, name, identity document, profile photograph, patient, customer, consent, diagnosis, or clinical record.

If MemberRegistrationService cannot legitimately accept clearly synthetic validation identity evidence, stop and return to planning with the smallest domain-level extension required. Do not insert Member rows manually or claim synthetic evidence is genuine.

### Domain-service reuse

Use existing services for their owned invariants, including as applicable:

- MemberRegistrationService;
- Mvp03BookingService;
- OperatorArrivalService;
- OperatorCheckInTicketService;
- basic-examination and X-ray worklist services;
- Operator profile, site, site-assignment, and shift-assignment services.

Direct repository access is permitted only where no owning application service exists and the implementation documents the invariant and authorization boundary. Ad-hoc persistence logic is not acceptable merely for convenience.

### Schedule, capacity, and points

Select an existing deterministic production-safe site/schedule when possible. Do not select a real Member booking or create arbitrary production catalogue data unless strictly necessary and separately approved.

If normal booking consumes capacity or points, use the existing authorized domain mechanism with the minimum deterministic amount, audit it, and document the resulting ledger/capacity side effect. Never edit a balance directly or use a real Member's balance. If no safe balance mechanism exists, stop and return to planning.

### Operator identity and credentials

Provision a separate validation-only Operator with the minimum existing role, permission, site assignment, and shift assignment required by the normal flow. It must authenticate through /operator/login, resolve its site through OperatorAuthorization, and never receive Administrator permissions.

new_secret_required=true. Use a purpose-specific named secret selected from repository conventions, conceptually MHCS_REAL_NPZ_VALIDATION_OPERATOR_PASSWORD. The value MUST NOT be created, printed, committed, logged, passed as a command argument, or included in tests. The application stores only a password hash; the later workflow receives the value only through approved GitHub Actions secret injection.

### Production guard and authorization

The capability must fail closed unless the environment, explicit validation intent, exact context version, prerequisite site/schedule state, and absence of inconsistent existing context all match. It must not require a broad permanent flag equivalent to MHCS_ALLOW_PRODUCTION_MVP_SEED.

Capability existence is separate from execution authorization. No schedule, startup hook, deployment-time seed, automatic execution, or reusable generic production-provisioning mode is allowed.

If a dedicated application permission is needed, use the narrowest possible permission, conceptually production.validation-context.provision, and do not grant it to the validation Operator.

### Idempotency and failure handling

Use one stable context identity. A replay may return the existing context only when all immutable properties match, including the Member marker, Operator marker, site/shift assignment, schedule/booking association, ticket/admission identity, expected stage/state, role/permission set, and nonclinical marker.

Partial or inconsistent state must fail closed unless safe documented domain idempotency permits resumption. Never silently create a second context or repair unknown state.

### Final state and secure handoff

Provisioning should stop with one admission ready for the later normal X-ray claim/call flow, preferably before claim/call if the later workflow can perform those actions through normal HTTP routes. It must leave:

- no capture;
- no NPZ object;
- no ProcessCaptureSet job;
- no MPIPS invocation; and
- no fake completed capture.

The later workflow must resolve the admission server-side from the fixed context marker or another repository-native protected mechanism. Do not print user, Member, profile, booking, admission, ticket, medical-record, NIK, KK, credential, or raw audit identifiers into public logs, and do not add a public lookup endpoint.

## Retention and lifecycle

Unless an existing safe domain cleanup is proven, classify provisioned records as RETAINED:

| Record class | Expected state |
|---|---|
| user / Member / verification assets | RETAINED |
| Operator profile / role-permission state | RETAINED |
| site and shift assignment | RETAINED |
| booking / point ledger / ticket / admission / history | RETAINED |
| audit / outbox | RETAINED |
| capture / objects / study | NOT_CREATED |

After the later validation, disabling the validation Operator through an existing authorized account mechanism may be performed only by a separately authorized operation. No automatic raw database disable/delete is allowed. If no normal disable mechanism exists, document the residual reusable-account risk.

## Sanitized runtime output

The command must emit stable fields without raw identifiers or secret values:

    validation_context_key=real-npz-e2e-v1
    environment_guard=PASS|FAIL
    authorization_guard=PASS|FAIL
    validation_member_state=CREATED|EXISTING_VALID|INCONSISTENT|NOT_EXECUTED
    validation_operator_state=CREATED|EXISTING_VALID|INCONSISTENT|NOT_EXECUTED
    operator_minimum_permissions=PASS|FAIL|NOT_EXECUTED
    operator_site_assignment=PASS|FAIL|NOT_EXECUTED
    operator_shift_assignment=PASS|FAIL|NOT_EXECUTED
    booking_state=CREATED|EXISTING_VALID|FAILED|NOT_EXECUTED
    arrival_state=CONFIRMED|EXISTING_VALID|FAILED|NOT_EXECUTED
    ticket_state=ISSUED|EXISTING_VALID|FAILED|NOT_EXECUTED
    basic_examination_state=COMPLETED|EXISTING_VALID|FAILED|NOT_EXECUTED
    xray_admission_state=READY|EXISTING_VALID|FAILED|NOT_EXECUTED
    capture_present=false|true|NOT_OBSERVED
    validation_operator_login_ready=true|false
    audit_marker=PASS|FAIL
    application_records_retention=RETAINED
    validation_context_provisioning=PASS|FAIL|NOT_EXECUTED

## Acceptance criteria

- [ ] The capability is console-only and introduces no HTTP/API route.
- [ ] Exactly one fixed deterministic context is accepted; arbitrary context and record identifiers are unsupported.
- [ ] Exact replay is idempotent; inconsistent or partial state fails closed.
- [ ] Member and Operator identities are separate, clearly nonclinical, and use no real identity data.
- [ ] Existing domain services are reused for registration, booking, arrival, ticket, queue, examination, assignment, and authorization invariants.
- [ ] No direct row fabrication replaces an existing domain service.
- [ ] Any points, capacity, or financial-like ledger side effect is deterministic, minimal, authorized, and audited.
- [ ] The validation Operator has only minimum existing permissions and uses normal authentication and active-site resolution.
- [ ] A purpose-specific credential secret is injected externally; no plaintext credential appears in source, tests, logs, or command arguments.
- [ ] Production guards and explicit execution authorization are fail-closed and do not rely on broad MVP-seeder flags.
- [ ] Provisioning creates no capture, private object, NPZ, processing job, MPIPS request, or DICOM study.
- [ ] The later workflow can resolve the context without public logging of raw application identifiers.
- [ ] Retained records and the absence of capture records are reported truthfully.
- [ ] No deployment, configuration, schema, network, MinIO, IAM, or secret-value mutation is required by the capability.

## Verification requirements

Focused tests must prove:

1. no HTTP route is introduced and the command is console-only;
2. only the exact deterministic context is accepted;
3. arbitrary identifiers and multiple contexts are rejected;
4. exact replay is idempotent and inconsistent partial state fails closed;
5. MvpOperatorSeeder is not called and MHCS_ALLOW_PRODUCTION_MVP_SEED is not mutated;
6. domain services and normal authorization boundaries are reused;
7. no plaintext credential, secret value, NIK, KK, or raw identifier is logged;
8. minimum Operator permissions and normal login/site resolution are preserved;
9. no capture, storage, queue, MPIPS, DICOM, or NPZ operation occurs;
10. audit marker, retention classification, and sanitized output are stable;
11. no automatic execution, deployment mutation, migration, or broad configuration change is required.

Required local checks include focused tests, PHP syntax/formatting as applicable, route inspection proving no public endpoint, git diff --check, and final diff inspection. No production command execution is a local test.

## Stop conditions

Stop and return to planning if any of the following occurs:

- synthetic identity data cannot satisfy current registration semantics without misleading clinical evidence;
- no safe authorized point/balance mechanism exists;
- a schema migration, broad permission, environment mutation, or production seeder is proposed;
- existing services cannot enforce an invariant without ad-hoc direct persistence;
- a second or arbitrary context can be created;
- partial state cannot be safely classified or resumed;
- credential handoff would expose plaintext or require a static source value;
- normal Operator authorization or active-site resolution would be bypassed;
- secure server-side handoff cannot be provided;
- implementation would create capture/NPZ/storage/queue/MPIPS/DICOM state; or
- scope, authority, approval, or retention semantics are unclear.

## Side-effect authorization

### Authorized in this task

- Repository implementation of the bounded provisioning capability and focused tests.
- Local static/feature verification.

### Not authorized in this task

- Creating or executing production context, users, Members, bookings, Operators, sites, or admissions.
- Creating secrets or changing secret values.
- Running seeders, downloading NPZ, submitting NPZ, invoking MPIPS, dispatching production workflows, deploying, or accessing production.
- Deleting or mutating retained application records or storage objects.

After implementation review, a fresh explicit one-time authorization is required before exactly one production provisioning operation. The later real-NPZ task requires its own separate runtime authorization.

## Delivery sequencing

publish this task
→ Planner reviews task
→ Executor implements capability/tests only
→ Planner reviews implementation
→ fresh one-time production provisioning authorization
→ exactly one provisioning operation
→ Planner verifies sanitized context evidence
→ resume real-NPZ workflow implementation/runtime planning

## Expected terminal outcome

Review Required after implementation and verification evidence are available.

Planning Required for any unresolved identity, balance, migration, authority, credential, retention, or side-effect decision. Implementation acceptance does not authorize production execution or release.
