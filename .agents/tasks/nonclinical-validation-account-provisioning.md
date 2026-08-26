---
title: MHCS Core Nonclinical Validation Account Provisioning
document_id: MHCS-TASK-NONCLINICAL-VALIDATION-ACCOUNT-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-26
scope:
  - exactly one nonclinical validation Member principal
  - exactly one nonclinical validation Operator principal
  - no Member, OperatorProfile, booking, or validation-context workflow state
authority_note: This task authorizes only a bounded console/application capability for the two fixed shared User principals. It does not authorize production execution, secret creation, deployment, or the full validation-context provisioner.
---

# Executable Task

## Task identity

**Task title:** Provision the fixed nonclinical validation Member and Operator accounts

**Task path:** .agents/tasks/nonclinical-validation-account-provisioning.md

**Task contract state:** Validated/Published upon immutable publication of this exact content.

**Delivery objective / Work Package / MVP:** Real NPZ production validation readiness — account/principal ownership blocker

**Owner / designated planning authority:** Faliq Adlan, CTO

## Delivery context

Gate D is blocked only because the repository has no production-safe application boundary for creating the two shared User principals needed by the later nonclinical validation-context provisioner. Factories, seeders, fixtures, and direct persistence are not acceptable production provisioning mechanisms, and `OperatorProfileResource` requires an existing eligible User.

This task defines only that account/principal boundary. The later governing provisioner will compose it with `MemberRegistrationService::registerNonclinicalValidation()`, existing Operator profile/site/shift management, and normal booking/worklist services.

## Baseline and task revision

**Implementation baseline:** c985bfff45a750f5ba438ea4758d9181c401632f

**Related blocked governing task:** .agents/tasks/nonclinical-production-validation-context-provisioning.md @ 50e8ff1f3ae1573a3d0d59ffa7aefdfb7286f6ac

**Accepted identity implementation:** f0c0a7876a796fe331bcb643a4648b3689fb8363

**Accepted points implementation:** c985bfff45a750f5ba438ea4758d9181c401632f

**Task revision:** The full SHA of the commit containing this exact task content, supplied by publication metadata.

The task revision and implementation baseline are separate. The task revision must be resolved before an Executor is handed this task.

## Objective

Implement the smallest production-safe internal application capability that provisions exactly two fixed purpose-specific shared Users for `NonclinicalValidationContext::KEY` (`real-npz-e2e-v1`): one validation Member principal and one validation Operator principal. The capability must be deterministic, fail closed on inconsistent state, and reusable by the later privileged context provisioner.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — delivery, evidence, authorization, and side-effect boundaries.
- `.agents/context/project.md` and the Member, Operator, and Image Gateway scoped context — ownership and security boundaries.
- `.agents/tasks/nonclinical-production-validation-context-provisioning.md @ 50e8ff1f3ae1573a3d0d59ffa7aefdfb7286f6ac` — blocked parent objective and strict exclusions.
- This task publication request — bounded account/principal blocker remediation.

### Observed implementation inputs

- `App\Shared\Validation\NonclinicalValidationContext`.
- `InteractiveOperatorAccessService`, `OperatorAuthorization`, `DatabaseAuthorizationClaimResolver`, and existing User/account models.
- `OperatorIdentityVerificationService`, `OperatorArrivalService`, `OperatorCheckInTicketService`, `OperatorWorklistService`, and `ImageGatewayCaptureService`.
- Existing authorization tables and audit infrastructure.

### Requirement traceability

- NVA-001 → exactly two fixed, clearly nonclinical shared principals are owned for `real-npz-e2e-v1`.
- NVA-002 → the validation Operator can later use normal authentication and the normal Operator-to-Image-Gateway authorization path without Administrator access.
- NVA-003 → credentials, identifiers, audit metadata, and console output are secret-safe and sanitized.
- NVA-004 → account provisioning creates no Member/domain workflow state and no capture or production-validation state.
- NVA-005 → replay is exact and idempotent; partial or unknown state fails closed.

## Scope

### In scope

- One narrowly owned internal application service, following repository conventions, for the fixed validation Member and Operator principals.
- Fixed context `NonclinicalValidationContext::KEY` only; no caller-supplied context.
- Fixed synthetic, reserved/non-routable identities derived from that context, such as repository-convention-compatible `mhcs-real-npz-e2e-v1-member@invalid` and `mhcs-real-npz-e2e-v1-operator@invalid`.
- Existing `users`, `authorization_role_assignments`, `authorization_permission_assignments`, password-hashing, and audit mechanisms where sufficient; no schema migration.
- Validation Member state: `account_status=active`, `login_enabled=true`, `must_change_password=false`, with no roles or permissions. Generate a cryptographically strong internal credential, hash it, and discard plaintext immediately; do not return or log it.
- Validation Operator state: `account_status=active`, `login_enabled=true`, `must_change_password=false`, exactly role `operator`, and exactly these existing minimum permissions:
  - `operator.portal.access`
  - `operator.attendance.read`
  - `operator.arrival.record`
  - `operator.identity.verify`
- Operator password supplied only through repository-consistent secret/config injection under `MHCS_REAL_NPZ_VALIDATION_OPERATOR_PASSWORD`, with `new_secret_required=true`; persist only its hash.
- Exactly one operator role assignment and exactly the four approved operator permission assignments for the fixed Operator.
- Trusted system authorization using the established `AuthorizationGuard` or equivalent: role `system`, valid trusted actor, valid operation context, and a narrow purpose such as `production.validation-context.account-provision`.
- Sanitized audit events and stable semantic output only.

### Out of scope

- `Member` row or `MemberRegistrationService::registerNonclinicalValidation()`.
- `OperatorProfile`, `OperatorSite`, site assignment, eligible shift, or shift assignment.
- Booking, points, point ledger, arrival, ticket, admission, examination, queue, capture, NPZ, MPIPS, DICOM, or any other validation workflow state.
- Public HTTP/API route, generic user-creation API/command, generic role/permission grant API, or arbitrary caller input.
- `MvpOperatorSeeder`, `MvpMemberSeeder`, `PrestigeClinicSeeder`, or `MHCS_ALLOW_PRODUCTION_MVP_SEED`.
- Secret creation, secret reading from production, secret-value selection, GitHub Actions secret mutation, secret injection, deployment, production access, or environment/configuration mutation.
- Automatic execution, scheduling, retry orchestration, cleanup, deletion, or automatic Operator disabling.
- Schema migration. If one is required, stop and return to Planner.

### Preserved behavior

- `AuthorizationClaimResolver` remains the source of role and permission claims.
- Normal Operator authentication, active-site resolution, assignment checks, identity verification, ticket, queue, and Image Gateway authorization remain unchanged.
- The validation Operator receives no Administrator role, no other role, and no manage, audit, identity, or unrelated permission beyond the exact set above.
- No existing production User is repurposed and no unrelated password is overwritten.
- Retained account state remains subject to a separately reviewed lifecycle operation.

## Dependencies and assumptions

### Dependencies

- The accepted baseline and blocked parent task above remain the current planning inputs.
- Existing User and authorization schema supports the fixed principals without migration.
- A separately managed `MHCS_REAL_NPZ_VALIDATION_OPERATOR_PASSWORD` secret is available only when a separately authorized later provisioning operation runs; its value is not part of this task.
- The later full context provisioner composes this capability with existing Member and Operator-owned domain services.

### Approved assumptions

- The normal Operator flow requires the four permissions listed above: portal access; attendance query; arrival recording; and identity verification. Source inspection showed ticket issuance calls `OperatorAuthorization::identity()`, while basic/X-ray worklist and Image Gateway paths additionally rely on `portal()`/active-site and do not require site/assignment/shift read or manage claims.
- Accounts are retained by default; disabling or invalidating the Operator is a separate authorized lifecycle action.

### Remaining approval requirements

- Planner/Reviewer approval of implementation and verification evidence.
- Fresh explicit one-time authorization before any production account provisioning.
- Separate authorization for creation/injection of the external Operator secret.
- Any schema, permission, financial-like, identity-data, or architecture decision not covered here returns to planning.

## Required capabilities

- Repository read/write and local test execution.
- Codebase Memory MCP for implementation discovery and impact tracing.
- Existing repository authorization, hashing, persistence, and audit mechanisms.

## Execution constraints

- The external boundary must be console-only and accept no arbitrary context, email, name, User ID, role, permission, password target, or other record identifier. The future `mhcs:provision-nonclinical-validation-context` command remains the privileged orchestration boundary.
- Express the service intent specifically for the two fixed validation principals; do not expose `createUser(email, password, roles, permissions)` or generic RBAC mutation.
- Fail closed if either principal is missing, duplicated, unexpected, inconsistent, partially provisioned, has unknown grants, has invalid password state, or cannot be proven owned by the fixed context. Never silently repair unknown state.
- Exact replay must resolve both principals server-side, verify immutable account and grant state, verify the Operator secret against its stored hash without exposing plaintext, and return `EXISTING_VALID` without creating or resetting anything. Do not regenerate/reset the Member credential on replay.
- The Operator password plaintext must never appear in source, tests, command arguments, logs, sanitized output, audit metadata, or error output.
- Only the exact operator role and four permissions above may be active. If normal source evidence proves another permission is required, stop and return to Planner rather than overgranting.
- Use transactions/locking and existing idempotency/audit patterns where they own the relevant invariant. Do not add a new framework or dependency.
- Audit metadata may contain only the fixed context key, `nonclinical=true`, and `principal_type=member|operator`; omit raw User IDs, emails unless strictly necessary, password/hash, Member/OperatorProfile IDs, site/schedule IDs, and all clinical data.

## Acceptance criteria

- [ ] The capability is internal and console-only; no HTTP/API route or generic user/RBAC mutation surface exists.
- [ ] Only the fixed `real-npz-e2e-v1` context and the two fixed principals are supported; arbitrary context, email, roles, permissions, and identifiers are rejected or impossible.
- [ ] Trusted system purpose and valid actor/operation context are required; ordinary Member, Operator, and Administrator HTTP contexts are rejected, and environment flags alone are insufficient.
- [ ] Exactly one active/login-enabled/no-forced-password-change validation Member User is created with no roles or permissions.
- [ ] Exactly one active/login-enabled/no-forced-password-change validation Operator User is created with exactly role `operator` and exactly the four approved permissions; Administrator and all manage/audit/unrelated grants are absent.
- [ ] The Operator password is externally injected, hashed, and never returned, logged, committed, included in tests/arguments, or written to audit metadata; wrong-secret replay fails closed.
- [ ] Exact replay returns `EXISTING_VALID`, creates no duplicate User, role, or permission assignment, and does not reset the Member credential.
- [ ] Partial, duplicate, unexpected, missing, extra, invalid, or contradictory state fails closed without repurposing or overwriting another account.
- [ ] No Member, OperatorProfile, OperatorSite, site/shift assignment, booking, point ledger, arrival, ticket, admission, queue, capture, NPZ, MPIPS, DICOM, or migration is created.
- [ ] `MvpOperatorSeeder`, `MvpMemberSeeder`, `PrestigeClinicSeeder`, and `MHCS_ALLOW_PRODUCTION_MVP_SEED` are not used.
- [ ] Retained account state and the absence of workflow/capture state are reported truthfully using sanitized stable semantic fields.

## Verification requirements

### Required checks

- Focused unit/feature tests cover trusted system purpose, ordinary-context rejection, fixed identities, account authentication state, absent/invalid plaintext output, exact role/permission grants, secret hashing and verification, exact replay, wrong-secret replay, partial/duplicate/inconsistent state, and fail-closed extra/missing grants.
- Static/source checks prove no public route, generic User/RBAC capability, seeder use, broad production-seeder flag, migration, Member row, Operator profile/site/shift state, booking/points state, or Image Gateway/NPZ/MPIPS behavior is introduced.
- `git diff --check` passes.

### Required evidence

The Executor MUST report:

- implementation revision or exact working-tree state;
- commands and checks actually executed with observed results;
- changed files;
- sanitized account/principal state and grant-state evidence without raw identifiers or secrets;
- proof that no excluded domain, workflow, capture, migration, secret-value, deployment, or production side effect occurred;
- any unresolved limitation or stop condition.

## Stop conditions

Stop and return to Planner if implementation requires a schema migration, new non-approved permission, Member row, Operator profile/site/shift state, booking/points mechanism, real identity data, public route, generic account/RBAC capability, broad seeder flag, secret-value mutation, or an unresolved authorization/architecture decision.

Stop on any duplicate, partial, unexpected, or unprovable fixed-principal state; do not silently repair or reuse it.

## Explicitly authorized side effects

During implementation and local verification only:

- source, test, and narrowly scoped internal application changes required by this task;
- local test-database records used by focused tests, cleaned according to existing test conventions.

No production account creation, production database mutation, secret creation/injection, deployment, network, object-storage, IAM, GitHub Actions, or release action is authorized.

## Expected terminal outcome

The task is complete only when the fixed two-principal account boundary is implemented and verified, without creating any Member/domain workflow or capture state, and Reviewer has enough observed evidence to accept or reject the immutable implementation revision.
