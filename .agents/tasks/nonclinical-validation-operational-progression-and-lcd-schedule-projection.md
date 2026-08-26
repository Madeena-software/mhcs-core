---
title: Nonclinical Operational Validation Progression and LCD Schedule Projection
document_id: MHCS-TASK-NONCLINICAL-OPERATIONAL-LCD-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-26
scope:
  - exact nonclinical validation Member operational progression
  - active-schedule-scoped public LCD projection
  - no production provisioning or NPZ validation execution
authority_note: This task authorizes application semantics and local verification only. It does not authorize production mutation, provisioning, capture, deployment, or release.
---

# Executable Task

## Task identity

**Task title:** Define nonclinical operational progression and LCD schedule projection

**Task path:** .agents/tasks/nonclinical-validation-operational-progression-and-lcd-schedule-projection.md

**Task contract state:** Validated/Published upon immutable publication of this exact content.

**Delivery objective / Work Package / MVP:** Real NPZ production validation readiness

**Owner / designated planning authority:** Faliq Adlan, CTO

## Delivery context

The exact nonclinical validation Member for `NonclinicalValidationContext::KEY = real-npz-e2e-v1` must traverse the normal Operator operational structure to X-ray readiness without fabricating patient identity, identity evidence, consent, vital signs, questionnaire data, or clinical findings. Normal Members must retain the existing clinical workflow.

The public LCD must also project only calls belonging to the site's currently active schedule set. Calls and history from ended schedules remain retained in the database but must disappear from the projection without a reset job, midnight reset, or deletion.

This task prepares the later real-NPZ production acceptance path. It does not provision or execute that path.

## Baseline and task revision

**Implementation baseline:** 1e469384141317424929592ecfe150d3d16b150e

**Blocked related task:** .agents/tasks/nonclinical-production-validation-context-provisioning.md @ 50e8ff1f3ae1573a3d0d59ffa7aefdfb7286f6ac

**Accepted implementation inputs:**

- Nonclinical member identity implementation: f0c0a7876a796fe331bcb643a4648b3689fb8363
- Point funding implementation: c985bfff45a750f5ba438ea4758d9181c401632f
- Account provisioning implementation: 1e469384141317424929592ecfe150d3d16b150e

**Task revision:** The full SHA of the commit containing this exact task content, supplied by publication metadata.

## Objective

Implement the smallest explicit, audited nonclinical semantics that allow the exact validation Member to progress through attendance, Operator identity disposition, check-in, basic-examination queue progression, and X-ray readiness, while correcting public LCD current/recent calls to use the site's active schedule set.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — delivery, evidence, and side-effect boundaries.
- `.agents/context/project.md` — architecture, module ownership, Clock, audit, and security boundaries.
- `.agents/context/modules/member/project.md` — Member identity, attendance, and consent ownership.
- `.agents/context/modules/operator/project.md` — Operator identity, check-in, queue, and site ownership.
- `.agents/tasks/nonclinical-production-validation-context-provisioning.md @ 50e8ff1f3ae1573a3d0d59ffa7aefdfb7286f6ac` — dependent production-validation objective.

### Observed implementation inputs

- `MemberContextResolver::isExactNonclinicalValidationIdentity()` and `NonclinicalValidationContext`.
- `Mvp04AttendanceService` and `Mvp04OperatorIdentityVerificationService`.
- `OperatorIdentityVerificationService`, `TrustedOperatorIdentityVerificationContextResolver`, and `OperatorCheckInTicketService`.
- `OperatorWorklistService` and existing basic-examination/X-ray transitions.
- `PublicQueueDisplayController`, `Clock`, `FrozenClock`, schedule relationships, and existing LCD tests.

### Requirement traceability

- NOP-001 → exact validation identity is the only identity eligible for nonclinical semantics.
- NOP-002 → nonclinical attendance, identity, consent, check-in, and examination progression are explicit and audited without clinical fabrication.
- NOP-003 → normal Member clinical safeguards and identity semantics remain unchanged.
- LCD-001 → LCD current/recent calls are scoped to the requested site's active schedule set using repository schedule semantics and Clock.
- LCD-002 → queue/history rows remain intact and no reset or deletion is introduced.

## Scope

### In scope

- Safe attendance rendering for the exact validation Member when `encrypted_nik` is null.
- Explicit `nonclinical_validation` identity evidence/terminal semantics and an explicit Operator action; never `matched`, `verified`, or `identity_verified`.
- A distinct audit event with `validation_context=real-npz-e2e-v1`, `nonclinical=true`, and `identity_verification_performed=false`.
- Explicit NOT APPLICABLE clinical-consent semantics for the exact terminal validation state, without an `examination_consents` row.
- Check-in acceptance only for `matched` plus normal confirmed consent, or `nonclinical_validation` plus the exact validation identity invariant.
- Explicit Operator-controlled nonclinical basic-examination completion that creates no vital-sign assessment, vital-sign execution, or paper questionnaire, then creates the normal X-ray waiting admission.
- Public LCD filtering by requested site and the set of schedules that are available/open and satisfy `starts_at <= now < ends_at`, using the repository `Clock` where feasible.
- Overlapping active schedules, empty projection when no schedule is active, and preservation of existing LCD ticket/destination privacy and polling.
- Focused Member/Operator/LCD tests and at least one local integrated acceptance test covering the complete nonclinical path and schedule expiry.

### Out of scope

- Production provisioning or execution; creation of Members, Operators, accounts, sites, schedules, bookings, points, secrets, or production records.
- NPZ upload, private-object storage, capture, MPIPS, DICOM, Image Gateway submission, deployment, release, or external-system mutation.
- Schema migrations, new permissions, broad production flags, arbitrary context/member/caller flags, or direct SQL fabrication.
- Midnight/date-based reset, cron/reset jobs, queue/history deletion, or cleanup of retained operational history.

### Preserved behavior

- Normal Members require genuine NIK behavior, approved identity evidence and `matched`, real confirmed patient consent, one vital-sign execution, and one paper questionnaire.
- Normal Members cannot access the nonclinical identity, consent, check-in, or examination-completion branches.
- LCD exposes only queue ticket number and destination; it never exposes Member name, NIK, MRN, booking/schedule/member/operator IDs, or validation context.
- Existing authentication, Operator site authorization, queue state transitions, audit/history retention, and five-second browser polling remain intact.

## Dependencies and assumptions

### Dependencies

- Existing Member-owned exact validation invariant remains available and authoritative.
- Existing Operator services and schedule relationships can represent the behavior without schema mutation.
- Repository `Clock` semantics are usable in production code or deterministic tests.

### Approved assumptions

- `operator_identity_verifications.state` is currently a bounded unconstrained string, so `nonclinical_validation` requires no migration.
- Basic/X-ray queue actions use existing Operator portal ownership and do not require an additional RBAC grant.
- The LCD route can resolve its `OperatorSite`, then schedules through `operator_site_id → examination_site_refs.operator_site_id → shift_schedules.examination_site_id`.

### Remaining approval requirements

- Planner/Reviewer approval of implementation and verification evidence.
- Any schema migration, new permission, global clinical-safeguard weakening, or unresolved ownership/identity decision returns to Planner before implementation.
- Separate authorization remains required for production provisioning and later real-NPZ capture.

## Required design decisions

### Exact identity recognition

Use the existing Member-owned exact invariant. Recognition must require the accepted conjunction: `IdentityStatus::NonclinicalValidation`, `RegistrationSource::NonclinicalValidation`, null genuine identity fields, no verification assets, the exact `mhcs.validation` marker, and `NonclinicalValidationContext::KEY`. Do not recognize by email, name, MRN, route parameter, caller flag, or environment flag. An almost-valid or arbitrary Member must fail closed.

### Attendance and identity

Normal attendance remains unchanged. For the exact validation Member, do not decrypt null `encrypted_nik`; return the repository-compatible safe shape `nik=null`, `masked_nik=null`, and `identity_status=nonclinical_validation` (or equivalent), with no fabricated digits.

The Operator must open the identity case and explicitly confirm the nonclinical validation context. The terminal state is `nonclinical_validation`, not an identity verification result. Emit a distinct audit event and do not write matched/verified claims.

### Consent and check-in

Do not create fake consent or signature records. The trust boundary must have two explicit fail-closed branches: normal `matched` plus confirmed consent, or `nonclinical_validation` plus the exact validation identity invariant and no fabricated consent. Do not generalize `state != matched` and do not allow manually corrupted state on a normal Member.

### Basic examination progression

Retain normal `waiting → claimed → called → in_service` transitions. Add an explicit Operator-controlled “complete nonclinical validation stage” branch only for the exact validation Member. It must complete the basic-examination admission, create the normal X-ray waiting admission, and audit `nonclinical=true`, `clinical_basic_examination_performed=false`, `vital_signs_recorded=false`, and `questionnaire_recorded=false`. It must not create clinical measurement or questionnaire records.

### LCD active schedule set

Resolve the route's local site and all schedules related through the existing site relationship. The active set contains schedules that belong to that site, are available/open under current schedule-domain semantics, and satisfy `starts_at <= current instant < ends_at`. Filter both current calls and recent called history by site, active `member_schedule_id`, and stages `basic_examination` or `xray`; retain existing limits/order.

If the active set is empty, return `current=[]` and `recent_calls=[]`. When schedules change, ended calls disappear naturally, future calls remain excluded, overlapping active schedules are all eligible, and no database rows are deleted. Do not use local wall-clock `now()`, calendar-date filtering, midnight reset, or a single arbitrary schedule.

## Expected implementation areas

- Member: `Mvp04AttendanceService`, `Mvp04OperatorIdentityVerificationService`, and existing identity contracts/resolvers.
- Operator: `OperatorIdentityVerificationService`, `TrustedOperatorIdentityVerificationContextResolver`, `OperatorCheckInTicketService`, and `OperatorWorklistService`.
- LCD: `PublicQueueDisplayController`.
- Tests: focused Member/Operator validation tests, LCD projection tests, and one integrated nonclinical operational acceptance test.

These are ownership guides, not permission to add cross-module shortcuts or unrelated abstractions.

## Acceptance criteria

- [ ] Exact validation attendance is safe with null NIK, shows explicit nonclinical status, and never fabricates an identifier; normal attendance is unchanged.
- [ ] Exact validation identity can be opened and explicitly confirmed, writes only `nonclinical_validation`, emits the distinct nonclinical audit, and never claims identity verification; normal identity behavior is unchanged.
- [ ] No fake examination consent or signature is created; normal Members still require confirmed consent; arbitrary/corrupted Members cannot use the nonclinical branch.
- [ ] Exact validation check-in creates the normal paper ticket and basic-examination waiting admission through existing mechanisms.
- [ ] Exact validation can be claimed, called, started, and explicitly completed without vital signs or questionnaire records; the audit states clinical examination was not performed; X-ray waiting admission is created.
- [ ] Normal basic-examination completion still requires exactly one vital-sign execution and one questionnaire and cannot use nonclinical completion.
- [ ] LCD current/recent projection returns empty for no active schedule, excludes ended/future schedules and other sites, includes all overlapping active schedules, and keeps historical rows intact.
- [ ] LCD behavior follows the Clock-controlled schedule interval, not date/midnight boundaries; existing five-second polling and privacy payload remain functional.
- [ ] An integrated local test covers exact validation Member → arrival → nonclinical identity → no consent → check-in → basic queue → X-ray → LCD, then advances Clock past schedule end and proves LCD is empty while queue/history rows remain.

## Verification requirements

### Required checks

- Focused Member, Operator, LCD, and integrated feature tests covering every acceptance criterion.
- Static inspection proving no schema migration, new operator permission, production provisioner, NPZ/MPIPS call, public lookup/backdoor, or database/history deletion was added.
- `git diff --check` and the repository's applicable test command(s).

### Required evidence

The Executor must report the exact implementation revision or working-tree state, commands and observed results, tests added/changed, known gaps, deviations, and blockers. Local results must not be represented as CI or production evidence.

## Stop conditions

Stop and return to Planner if any of these occurs:

- schema migration, new permission, fake NIK/KTP/profile evidence, fake consent, fake vital signs/questionnaire, or global clinical-safeguard weakening is required;
- arbitrary Members could activate validation semantics or recognition cannot use the exact invariant;
- active schedule ownership cannot be resolved deterministically with repository Clock semantics;
- implementation requires production/external mutation, secret access, deployment, NPZ, MPIPS, DICOM, or scope expansion;
- an authority, architecture, dependency, or acceptance decision is missing or contradictory.

## Side-effect authorization

### Explicitly authorized side effects

- Application source and local test changes within this bounded objective.
- Local test database/fixture effects created by the repository's existing test harness.

No production action, production record creation, secret access, deployment, release, commit, push, or external-system mutation is authorized by implementation execution of this task.

## Expected terminal outcome

**Review Required** when the bounded implementation and observed verification evidence are available. Use **Planning Required** on any stop condition. The Executor does not self-declare acceptance or release readiness.

## Task publication scope

This publication creates exactly this documentation task file. It does not modify application code, tests, migrations, workflows, production data, or external systems.
