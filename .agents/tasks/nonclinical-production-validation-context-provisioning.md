---
title: MHCS Core Nonclinical Production Validation Context Provisioning
document_id: MHCS-TASK-NONCLINICAL-VALIDATION-CONTEXT-001
version: 2.1
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

**Current reviewed repository baseline:** `6b046ed73b7ab69f87064f5356479cb226f6e655`

Historical accepted implementation/evidence revisions remain supporting references, including the Phase D child-specification revision `4d6116cd7bfe20b912b59f6a9014a3ca45108118`.

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

**Status:** PASS
**Authoritative child specification:** `.agents/tasks/nonclinical-validation-operational-progression-and-lcd-schedule-projection.md @ 4d6116cd7bfe20b912b59f6a9014a3ca45108118`

Accepted implementation lineage:

- Initial Phase D implementation: `2b9bdb33b53205afdc5463219895a249785801fd`
- Functional remediation: `7615d56d3f2e779d94f93b28c444e51b25010db5`
- Focused evidence: `9b2c3b99d6a4d7d43a2966e921b7d8cf70242303`
- Final acceptance evidence and current reviewed repository baseline: `6b046ed73b7ab69f87064f5356479cb226f6e655`

Phase D proves safe null-NIK attendance; explicit `nonclinical_validation` identity disposition; no fake identity evidence or consent; preserved normal matched-plus-consent behavior; canonical nonclinical check-in without consent; normal ticket/basic waiting admission; explicit nonclinical basic-stage completion; no vital-sign or questionnaire fabrication; X-ray waiting progression; active-schedule-set LCD filtering; future/ended schedule exclusion; overlapping active schedules; site isolation; `Clock`-based schedule transition; retained queue/history; and LCD privacy.

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

**Status:** PASS

**Accepted evidence revision:** `6b046ed73b7ab69f87064f5356479cb226f6e655`

The integrated local test proves canonical validation Member → normal arrival → actual identity case → explicit `nonclinical_validation` decision → zero `examination_consents` → normal check-in → `basic_examination` waiting → claim → call → start → explicit nonclinical completion → zero clinical evidence rows → X-ray waiting → X-ray claim → X-ray call → LCD visible while the schedule is active → LCD empty after schedule end → queue/history retained. No NPZ, ImageGateway, MPIPS, or production action was involved.

## Phase F — Full context provisioner

**Status:** PLANNING_REQUIRED

**phase_f_blocker:** `SHIFT_ASSIGNMENT_SYSTEM_PROVENANCE`

Source verification found that the current Operator composition cannot safely
satisfy this phase unchanged:

- `OperatorProfileService::create()` reconciles additional grants
  (`operator.site.read`, `operator.assignment.read`, `operator.shift.read`,
  and `operator.audit.read`), so it is not suitable for the validation
  Operator, whose exact contract remains role `operator` with only
  `operator.portal.access`, `operator.attendance.read`,
  `operator.arrival.record`, and `operator.identity.verify`.
- Normal Operator profile/site/shift administration requires authenticated
  Administrator manage authorization. The validation Operator must not receive
  Administrator or manage permissions, and no fabricated `Auth::user()` or
  Administrator context is allowed.
- `operator_site_assignments.assigned_by_user_id` is nullable, but
  `operator_shift_assignments.assigned_by_user_id` is currently required and
  references `users.id`. The fixed console-only trusted system provisioning
  context has no truthful application User actor for that required provenance.

### Phase F planning resolution

Plan only the following narrow schema exception: make
`operator_shift_assignments.assigned_by_user_id` nullable while retaining its
foreign key to `users.id`. This does not change normal Operator authorization,
grant a permission, add a public API, introduce an arbitrary context, alter
normal Administrator assignment provenance, or weaken normal routes. It avoids
false provenance, a third synthetic User, temporary Administrator grants, and
fake authentication. No broader schema change is approved.

Normal `OperatorShiftAssignmentService::assign()` continues to write the
authenticated Administrator user ID. Only the exact fixed nonclinical
validation system-provisioning boundary may create the validation shift
assignment with `assigned_by_user_id = null`; that boundary must emit explicit
audit metadata identifying `validation_context=real-npz-e2e-v1`,
`nonclinical=true`, `provisioning_actor=system`, and
`human_assignment_performed=false` (using an existing repository-safe naming
convention where applicable). NULL represents fixed system provisioning, not
an unknown human assignment.

Phase F planning must define a validation-specific, system-only Operator-owned
capability, such as
`NonclinicalValidationOperatorContextProvisioningService`, or narrowly
equivalent methods in existing Operator application services. It exposes no
HTTP route, requires the fixed purpose
`production.validation-context.operator-context-provision` and role `system`,
and operates only on the exact Operator User returned and proven by
`NonclinicalValidationAccountProvisioningService`. Command input must not
accept arbitrary user, profile, site, schedule, eligible-shift, role,
permission, email, name, or context-key values; trusted services may pass
server-resolved IDs internally.

The validation-specific capability may create exactly one profile for the
accepted validation Operator User, without calling generic `create()` when it
would reconcile grants and without adding, removing, or reconciling claims.
After provisioning it must assert exactly role `operator` and only the four
accepted permissions listed above. It must not adopt an unrelated existing
profile unless exact audit/marker evidence proves validation ownership.

Resolve the site only from the selected schedule
(`shift_schedule → examination_site_ref → operator_site_id → active
operator_sites`), with no caller-supplied site ID. Create or replay exactly
one validation site assignment and one assignment to an existing legitimate
`operator_eligible_shifts` projection (`member_schedule_id` equal to the
selected schedule, stable site, `sync_status=eligible`, and consistent times).
Fail closed when no eligible candidate exists; do not create catalogue or
schedule data, invoke `MvpOperatorSeeder`, or manufacture an eligible shift.
The normal booking contract and normal
`Mvp03BookingService::createForCurrentMember()` flow remain unchanged.

The original provisioning capability remains the console-only deterministic boundary:

    php artisan mhcs:provision-nonclinical-validation-context

It must compose the accepted primitives: account principals → exact validation Member registration → deterministic eligible site/schedule → bounded point funding → normal booking → Operator profile → site assignment → eligible shift → shift assignment. It must not provision fake identity verification, consent, vital signs, questionnaires, or clinical findings.

Provisioning should stop at the earliest deterministic legitimate state that allows later authenticated Operator execution through Phase D, preferably before claim/call. Re-evaluate any legacy requirement that provisioning itself complete basic examination or create X-ray admission; do not retain it solely because version 1.0 did. Select site/schedule server-side with no arbitrary schedule ID input; require ownership, availability, and capacity; fail closed when no suitable schedule exists. Do not create production catalogue/schedule data merely to pass validation.

The command accepts no arbitrary context, user, Member, Operator, site, admission, role, permission, email, name, or credential input. It uses a fixed context key, existing markers, server-side resolution, normal domain services, fail-closed idempotency, retained records, and sanitized output. The production value for `MHCS_REAL_NPZ_VALIDATION_OPERATOR_PASSWORD` is supplied only through approved runtime secret injection and may be read/consumed in memory by the privileged provisioner solely for the accepted account primitive's `Hash::make` on first provisioning and `Hash::check` on exact replay. It must not be accepted as a CLI argument, hard-coded, committed, printed, logged, audited, included in sanitized output, returned by the service, exposed through HTTP/API, or persisted in plaintext; only the password hash may persist. Tests use runtime-generated synthetic credential material and never the production secret value. Task publication and source implementation do not create the GitHub/production secret; secret creation/injection remains a separately controlled production operation.

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
| operational_progression_implementation | PASS |
| lcd_schedule_projection_spec | PASS |
| lcd_schedule_projection_implementation | PASS |
| local_operational_acceptance | PASS |
| schedule_context_feasibility | CONDITIONAL |
| operator_profile_site_shift_composition | PLANNING_REQUIRED |
| full_context_provisioner | PLANNING_REQUIRED |
| phase_f_blocker | SHIFT_ASSIGNMENT_SYSTEM_PROVENANCE |
| production_deployment | NOT_AUTHORIZED |
| production_context_provisioning | NOT_AUTHORIZED |
| mpips_network_connectivity | PASS |
| mpips_thorax_functional_readiness | PENDING_EXTERNAL_FIX |
| real_npz_acceptance | BLOCKED |
| production_authorization | NONE |

`mpips_network_connectivity=PASS` and `mpips_thorax_functional_readiness=PENDING_EXTERNAL_FIX` are recorded separately. Do not execute final real-NPZ acceptance while the known MPIPS thorax bug remains unresolved unless explicitly intended as a bounded diagnostic. Do not modify MPIPS from `mhcs-core`.

## Acceptance criteria

- [x] Phase D semantics are implemented and verified without fake identity evidence, consent, clinical measurements, schema mutation, new broad permission, or normal-workflow weakening.
- [x] Phase E local integration proves the complete nonclinical operational path and schedule-bound LCD expiry while retaining queue/history rows.
- [ ] Phase F, when selected, provisions exactly one deterministic context through accepted domain primitives, stops before capture, is idempotent/fail closed, and emits sanitized output.
- [ ] Production deployment/provisioning and Phase I remain separately authorized and are not claimed by this task.
- [ ] Pinned fixtures fail closed on byte/hash mismatch and are logged only by aliases.

## Verification requirements

- Run focused Member, Operator, LCD, and integrated tests for the selected phase.
- Run static checks for no schema change beyond the single approved nullable
  `operator_shift_assignments.assigned_by_user_id` migration, no new broad
  permission, fake clinical records, direct SQL fabrication, public validation
  endpoint, secret disclosure, automatic execution, or queue/history deletion.
- Run `git diff --check` and report exact revision, commands, observed results, changed tests, gaps, deviations, and blockers.

## Stop conditions

Return to Planner if implementation requires any schema change beyond the single
approved nullable `operator_shift_assignments.assigned_by_user_id` migration,
new broad permission, Administrator grant to the validation Operator, a third
validation principal, fake assignment provenance, fake identity evidence,
fake consent, fake clinical measurements, arbitrary schedule/site/user input,
fake eligible shift, production seeder, generic system bypass, direct SQL
replacing owned services, a public validation endpoint, a secret value in
source/logs, automatic deployment/provisioning, unsafe schedule ambiguity, or
weakened normal safeguards. Also stop for missing/contradictory authority,
scope expansion, unsafe production/external mutation, or inability to resolve
the active schedule set deterministically.

## Side-effect authorization

This task authorizes only bounded repository source/test/documentation changes for the explicitly selected phase and local test-harness effects. It does not authorize production records, credentials or secret access, deployment, capture, NPZ/MPIPS/DICOM work, external mutation, release, or automatic execution.

## Governance and terminal outcome

This updated umbrella is authoritative for all remaining nonclinical real-NPZ acceptance work. Historical child tasks are immutable supporting specifications/evidence. Do not create a new `.agents/tasks/*.md` file for each remaining phase or blocker; update this umbrella in place when planning changes are material.

Execution ends in **Review Required** with observed evidence or **Planning Required** on a stop condition. Implementation acceptance is not release authorization.
