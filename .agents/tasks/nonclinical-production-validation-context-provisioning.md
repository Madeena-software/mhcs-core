---
title: MHCS Core Nonclinical Production Validation Context Provisioning
document_id: MHCS-TASK-NONCLINICAL-VALIDATION-CONTEXT-001
version: 2.0
status: validated-published
language: en-US
last_updated: 2026-08-26
scope:
  - one deterministic nonclinical production validation context
  - normal authenticated Operator-to-Image-Gateway readiness
  - operational progression and active-schedule LCD semantics
  - no production execution or real-NPZ acceptance in this task
authority_note: This is the authoritative umbrella task for the remaining nonclinical real-NPZ acceptance work. It authorizes only the bounded phase explicitly selected for execution; it does not authorize deployment, production mutation, credential access, capture, or release.
---

# Executable Task

## Task identity

**Task title:** Provision one nonclinical production context for real-NPZ validation

**Task path:** `.agents/tasks/nonclinical-production-validation-context-provisioning.md`

**Task contract state:** Validated/Published upon immutable publication of this exact content.

**Delivery objective / Work Package / MVP:** Real NPZ production validation readiness

**Owner / designated planning authority:** Faliq Adlan, CTO

This umbrella supersedes the historical publication at `50e8ff1f3ae1573a3d0d59ffa7aefdfb7286f6ac`. Historical child task files remain immutable supporting specifications and evidence; future execution references this umbrella revision and an explicit phase.

## Delivery context and preserved intent

The objective remains one deterministic, clearly marked nonclinical validation context that reaches the normal authenticated Operator flow and ultimately `ImageGatewayController::captureStore()` for the real-NPZ acceptance path. The context must use normal authorization, domain services, retained operational records, sanitized output, pinned fixture integrity, and separate deployment/runtime authorization.

The system must not call a direct S3, queue, or MPIPS shortcut and label it full application E2E. No automatic production execution is permitted. The solved private-object async-promise incident is historical context, not reopened scope.

## Baseline and task revision

**Current implementation baseline:** `4d6116cd7bfe20b912b59f6a9014a3ca45108118`

**Accepted implementation prerequisites:** listed in Phase A–C below.

**Task revision:** The full SHA of the commit containing this exact task content, supplied by publication metadata.

The task revision and implementation baseline are separate. Future Executor prompts MUST include:

    umbrella_task_path=.agents/tasks/nonclinical-production-validation-context-provisioning.md
    umbrella_task_revision=<LATEST SHA>
    umbrella_phase=<PHASE>

## Accepted prerequisite ledger

### Phase A — Nonclinical Member identity

**Status:** PASS
**Implementation:** `f0c0a7876a796fe331bcb643a4648b3689fb8363`

Guarantees the exact `NonclinicalValidationContext::KEY`, `IdentityStatus::NonclinicalValidation`, `RegistrationSource::NonclinicalValidation`, no NIK/KK/KTP/KIA, no profile verification asset, no misleading verified semantics, and preserved normal Member behavior.

Historical task: `.agents/tasks/nonclinical-validation-member-identity-semantics.md`

### Phase B — Validation booking point funding

**Status:** PASS
**Implementation:** `c985bfff45a750f5ba438ea4758d9181c401632f`

Guarantees exact-validation-Member-only, deterministic purpose-bound funding, normal booking charges, deterministic source reference, replay fail-closed behavior, no direct balance edit, and no production seeder.

Historical task: `.agents/tasks/nonclinical-validation-booking-points-funding.md`

### Phase C — Validation account principals

**Status:** PASS
**Implementation:** `1e469384141317424929592ecfe150d3d16b150e`

Guarantees exactly one deterministic validation Member User and Operator User for the context key; no Member roles/permissions; Operator role exactly `operator`; no Administrator role; and only these Operator permissions:

- `operator.portal.access`
- `operator.attendance.read`
- `operator.arrival.record`
- `operator.identity.verify`

The external password secret contract is `MHCS_REAL_NPZ_VALIDATION_OPERATOR_PASSWORD`. No plaintext credential may appear in source, tests, logs, or task output. Ownership and replay/fail-closed semantics are audit-based.

Historical task: `.agents/tasks/nonclinical-validation-account-provisioning.md`

## Phase D — Operational progression and LCD

**Status:** SPEC_ACCEPTED_IMPLEMENTATION_PENDING
**Authoritative child specification:** `.agents/tasks/nonclinical-validation-operational-progression-and-lcd-schedule-projection.md @ 4d6116cd7bfe20b912b59f6a9014a3ca45108118`

The following semantics are part of this umbrella contract.

### D1–D5 — Exact nonclinical operational semantics

Only the exact canonical validation Member may use these branches. Recognition requires the accepted conjunction: nonclinical identity status and registration source, null genuine identity fields, no verification assets, the exact `mhcs.validation` marker, and `NonclinicalValidationContext::KEY`. Never recognize by email, name, MRN, route/caller/environment flags, or arbitrary state text.

- Attendance must not decrypt null `encrypted_nik`, fabricate NIK, or expose fake digits; it returns an explicit safe nonclinical representation. Normal attendance is unchanged.
- Identity disposition is an explicit Operator action with terminal state `nonclinical_validation`, never `matched`, `verified`, or `identity_verified`. Audit states `nonclinical=true` and `identity_verification_performed=false`. Normal Members retain approved evidence plus matched semantics.
- No patient/member consent, signature, clinical consent evidence, or `examination_consents` row is fabricated. The exact validation state makes patient clinical consent NOT APPLICABLE. Normal check-in remains `matched` plus real confirmed consent; the validation branch requires the exact canonical identity invariant and is fail closed.
- Existing check-in creates the normal paper ticket and `basic_examination` waiting admission. No direct admission fabrication or generalized state bypass is allowed.
- The exact Member traverses normal `waiting → claimed → called → in_service`, then explicit Operator nonclinical-stage completion, `completed`, and normal X-ray waiting admission. It creates no vital signs, vital-sign execution, questionnaire, or clinical finding. Audit states `nonclinical=true`, `clinical_basic_examination_performed=false`, `vital_signs_recorded=false`, and `questionnaire_recorded=false`. Normal Members retain the existing clinical evidence requirements.

### D6 — LCD active schedule projection

The public LCD boundary is the requested Operator site plus its CURRENT ACTIVE SCHEDULE SET—not calendar date, midnight reset, all site history, or one arbitrary schedule. Resolve schedules through `operator_sites.operator_site_id → examination_site_refs.operator_site_id → shift_schedules.examination_site_id`.

An active schedule belongs to the site, is available/open under existing domain semantics, and satisfies `starts_at <= current instant < ends_at`, using repository `Clock` semantics. Multiple overlapping active schedules are eligible. With no active schedules, return `current=[]` and `recent_calls=[]`.

Current calls require the requested site, `admission.member_schedule_id` in the active set, `state=called`, and stage `basic_examination|xray`. Recent calls require the requested site, an active schedule, `history.event_type=called`, and stage `basic_examination|xray`. Calls from ended schedules disappear on normal five-second polling; future schedules remain excluded. Queue/history rows are never deleted, and filtering is not based on `occurred_at >= today`.

## Phase E — Local integrated operational acceptance

**Status:** PENDING

Add a deterministic local test using the exact validation Member proving booking/domain setup, arrival, nonclinical identity disposition, no fake consent, normal check-in ticket, basic-examination waiting, claim, call, start, explicit nonclinical completion, X-ray waiting, X-ray call, and LCD inclusion while the schedule is active. Advance `FrozenClock` past schedule end and prove LCD current/recent are empty while queue admissions and history remain. No NPZ is used in this phase.

## Phase F — Full context provisioner

**Status:** PENDING_AFTER_PHASE_D_E

The original provisioning capability remains the console-only deterministic boundary:

    php artisan mhcs:provision-nonclinical-validation-context

It must compose the accepted primitives: account principals → exact validation Member registration → deterministic eligible site/schedule → bounded point funding → normal booking → Operator profile → site assignment → eligible shift → shift assignment. It must not provision fake identity verification, consent, vital signs, questionnaires, or clinical findings.

Provisioning should stop at the earliest deterministic legitimate state that allows later authenticated Operator execution through Phase D, preferably before claim/call. Re-evaluate any legacy requirement that provisioning itself complete basic examination or create X-ray admission; do not retain it solely because version 1.0 did. Select site/schedule server-side with no arbitrary schedule ID input; require ownership, availability, and capacity; fail closed when no suitable schedule exists. Do not create production catalogue/schedule data merely to pass validation.

The command accepts no arbitrary context, user, Member, Operator, site, admission, role, permission, email, name, or credential input. It uses a fixed context key, existing markers, server-side resolution, normal domain services, fail-closed idempotency, retained records, and sanitized output. It never reads, creates, prints, logs, commits, or tests a secret value; the application stores only a password hash and approved secret injection supplies the later workflow value.

## Phase G — Production deployment

**Status:** NOT_AUTHORIZED

Production is not assumed to contain all accepted revisions. Deployment requires fresh explicit one-time authorization after implementation review. Publication, implementation, merge, or acceptance is not deployment authorization.

## Phase H — Production context provisioning

**Status:** NOT_AUTHORIZED

Provisioning requires fresh explicit one-time production authorization after the exact deployed revision is reviewed. The Operator password comes only through approved secret injection. Provisioned records are retained unless separately authorized lifecycle work says otherwise. No production provisioning occurs in task publication or implementation of Phases D/E.

## Phase I — Real NPZ acceptance

**Status:** BLOCKED_ON_PHASES_D_E_F_G_H_AND_MPIPS

The final path is: real authenticated validation Operator → legitimate active site/schedule → legitimate validation booking → explicit nonclinical progression → X-ray admission claim/call → `ImageGatewayController::captureStore()` → real radiograph and gain NPZ → async private-object persistence → accepted sources → `ProcessCaptureSet` → MPIPS → valid DICOM → terminal study state. A direct service shortcut is not full application E2E.

### Pinned fixtures

Radiograph: Drive ID `1Ft3OALtx_d3ua-z0DSS34jJmywaXjLu2`, filename `TRX_1787726886830.npz`, bytes `73089445`, SHA-256 `605540c9102867eda3a5b54f4f88566d067ba8705fcc20bf870e4a60f80262b9`.

Gain: Drive ID `1kI99se2CjzCgo4qInMEGUuJ-ZJZE3iQY`, filename `TRX_1787726609597.npz`, bytes `17190412`, SHA-256 `38918e436e5329e28b08c844e8df3766a1ab83a1fc3135c83df56370c480b2a9`.

Fail closed on mismatch. Logs use aliases `radiograph_fixture` and `gain_fixture` only; never log original filenames/person/folder context, raw NPZ content, array data, local paths, or credentials.

## Status table

| Area | Status |
|---|---|
| member_identity | PASS |
| booking_points | PASS |
| account_principals | PASS |
| operational_progression_spec | PASS |
| operational_progression_implementation | PENDING |
| lcd_schedule_projection_spec | PASS |
| lcd_schedule_projection_implementation | PENDING |
| local_operational_acceptance | PENDING |
| schedule_context_feasibility | CONDITIONAL |
| operator_profile_site_shift_composition | PENDING |
| full_context_provisioner | PENDING |
| production_deployment | NOT_AUTHORIZED |
| production_context_provisioning | NOT_AUTHORIZED |
| mpips_network_connectivity | PASS |
| mpips_thorax_functional_readiness | PENDING_EXTERNAL_FIX |
| real_npz_acceptance | BLOCKED |
| production_authorization | NONE |

`mpips_network_connectivity=PASS` and `mpips_thorax_functional_readiness=PENDING_EXTERNAL_FIX` are recorded separately. Do not execute final real-NPZ acceptance while the known MPIPS thorax bug remains unresolved unless explicitly intended as a bounded diagnostic. Do not modify MPIPS from `mhcs-core`.

## Acceptance criteria

- [ ] Phase D semantics are implemented and verified without fake identity evidence, consent, clinical measurements, schema mutation, new broad permission, or normal-workflow weakening.
- [ ] Phase E local integration proves the complete nonclinical operational path and schedule-bound LCD expiry while retaining queue/history rows.
- [ ] Phase F, when selected, provisions exactly one deterministic context through accepted domain primitives, stops before capture, is idempotent/fail closed, and emits sanitized output.
- [ ] Production deployment/provisioning and Phase I remain separately authorized and are not claimed by this task.
- [ ] Pinned fixtures fail closed on byte/hash mismatch and are logged only by aliases.

## Verification requirements

- Run focused Member, Operator, LCD, and integrated tests for the selected phase.
- Run static checks for no schema migration, new broad permission, fake clinical records, direct SQL fabrication, public validation endpoint, secret disclosure, automatic execution, or queue/history deletion.
- Run `git diff --check` and report exact revision, commands, observed results, changed tests, gaps, deviations, and blockers.

## Stop conditions

Return to Planner if implementation requires a schema migration, new broad permission, fake identity evidence, fake consent, fake clinical measurements, arbitrary validation context/member, production seeder, generic account provisioning, direct SQL replacing owned services, a public validation endpoint, a secret value in source/logs, automatic deployment/provisioning, unsafe schedule ambiguity, or weakened normal safeguards. Also stop for missing/contradictory authority, scope expansion, unsafe production/external mutation, or inability to resolve the active schedule set deterministically.

## Side-effect authorization

This task authorizes only bounded repository source/test/documentation changes for the explicitly selected phase and local test-harness effects. It does not authorize production records, credentials or secret access, deployment, capture, NPZ/MPIPS/DICOM work, external mutation, release, or automatic execution.

## Governance and terminal outcome

This updated umbrella is authoritative for all remaining nonclinical real-NPZ acceptance work. Historical child tasks are immutable supporting specifications/evidence. Do not create a new `.agents/tasks/*.md` file for each remaining phase or blocker; update this umbrella in place when planning changes are material.

Execution ends in **Review Required** with observed evidence or **Planning Required** on a stop condition. Implementation acceptance is not release authorization.
