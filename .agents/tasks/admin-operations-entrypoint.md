---
title: Admin Operations Entry Point — Candidate
document_id: MHCS-TASK-ADMIN-OPERATIONS-ENTRYPOINT-001
version: 1.0
status: draft
language: en-US
last_updated: 2026-10-12
scope:
  - MVP-GAP-027 operational capability planning
  - bounded Admin operations navigation candidate
authority_note: Draft for Product Owner decisions and independent Planner/Reviewer review. Publication of this candidate for review does not validate it or authorize implementation, permission provisioning, portal transition, production access, or release.
---

# Candidate Implementation Task

## Task identity

**Task title:** Admin operations entry point over existing domain resources

**Task path:** `.agents/tasks/admin-operations-entrypoint.md`

**Task contract state:** `Draft`; not Validated/Published and not an Executor handoff.

**Delivery objective / Work Package / MVP:** Next Admin-first operational slice under MVP-GAP-027; shared administration foundations in WP-10/WP-17 and existing WP-11 site/staff assignment capabilities. This slice does not complete those Work Packages or close MVP-GAP-027.

**Owner / designated planning authority:** MHCS Product Owner and Planner/Reviewer; Member/Operator technical and security/privacy authorities retain their applicable approval responsibilities.

## Delivery context

The human planning request authorizes repository inspection, one candidate task, a dedicated branch, scoped task-document commit/push, and Draft PR. It expressly prohibits application edits, merge, deployment, production-data access, portal removal and production permission provisioning.

The approved Admin-first direction is one persistent internal Admin entry and unified domain-owned operations. Existing resources already cover sites, schedules, Members and staff assignments. Reimplementing their business services would duplicate working boundaries. The smallest proposed successor is an operations landing page with independently authorized links to those resources. It contains no new record lists, counts, clinical contents or workflow actions.

### Verified delivery state

- Local `main`, `origin/main` and remote `refs/heads/main` were observed at `fc37002d106b839c0b881e7dec7021c91389f501`; tracked/untracked working tree was clean before authoring, with zero ahead/behind commits. Existing local branches were preserved. GitHub returned no open PRs at preflight; this does not prove no off-repository parallel work exists.
- [PR #6](https://github.com/Madeena-software/mhcs-core/pull/6) is MERGED at `fb25354311121fc8163256cd0530d9cbcb66bb68`. Its body records independent acceptance of the bounded lean-docs cleanup and anchor remediation, governed by `.agents/tasks/lean-docs-and-business-authority.md @ 09b0f462fe94976394bb1357077e1a185895a3f2`. Its merge is an ancestor of current main. It is not pending because older text says Review Required.
- [PR #7](https://github.com/Madeena-software/mhcs-core/pull/7) is MERGED at current main. The exact registration contract is `.agents/tasks/admin-first-manual-member-registration.md @ e7ccc686da049e78d7634125237fc794b30a0908`, with original baseline `fb25354311121fc8163256cd0530d9cbcb66bb68`.
- The [registration acceptance record](../../docs/mvp/evidence/admin-first-manual-member-registration-acceptance.md) establishes externally supplied V7/A9 acceptance for synthetic/local AC1–AC8 at `21d279831455d32fe2a707e8d306af861c0bbb08`. No reviewer identity or GitHub APPROVED review is inferred; GitHub returned empty review/check arrays for both PRs.
- Between accepted registration implementation `21d279831455d32fe2a707e8d306af861c0bbb08` and current main, only the acceptance record and context pointer differ. Main has the same tree as PR #7 evidence head `8d16d75e8402ec9b911c5dec551979ff0462d5a7`. The accepted application code is therefore integrated unchanged; this is not blanket acceptance of every application capability.
- No pending local implementation or open PR was observed on this dependent delivery line. The next unresolved state is **PENDING AUTHORITY APPROVAL / PENDING PLANNING REPAIR**, not repeated PR #6/#7 execution or acceptance. No G10 or production readiness is established.

### Documentation reconciliation findings

These are recorded findings, not new review verdicts or edits to historical evidence:

| Finding | Current interpretation / required reconciliation |
|---|---|
| `.agents/context/project.md` Repository identity; `docs/mvp/README.md` Current authority and status; `docs/mvp/roadmap.md` Admin-first planning sequence; `docs/mvp/decision-log.md` opening; `docs/mvp/work-package-status.md` opening still call PR #6 cleanup Review Required | Superseded by the observed merged PR #6 and supplied acceptance. Reconcile status pointers separately; do not re-open the cleanup or rewrite its governing contract. |
| MVP-GAP-033 still says review required and refers to pinned business copies | Preserve its identity and audit trail. Reconcile against PR #5/#6 acceptance and lean-docs removal/provenance; do not assert the old local business copies are maintained authority. |
| PR #7 body and registration acceptance record retain pre-merge integration-review/merge-authorization language | Historical recording-time state; current MERGED state resolves integration. Retain the exact governing and accepted implementation revisions and their limitations. |
| The previous registration task described broader cleanup review states as unresolved | Historical assessment at that task revision; leave the validated task unchanged. It does not reopen PR #6. |
| MVP-GAP-034 broadly describes later application acceptance as unknown | PR #7 provides bounded registration closure only. Other acceptance/production evidence remains open. |
| Historical ledgers contain old E0/not-started and portal-first/Doctor-exclusion descriptions | Historical requirements/status references, not proof of current absence or current approval. MVP-DEC-044 changes priority only; preserve stable IDs and domain/security boundaries. |

No active source is bulk-rewritten by this candidate. The current main SHA and this reconciliation table take precedence over stale supporting status when interpreting this planning cycle.

## Baseline and task revision

**Implementation baseline:** `fc37002d106b839c0b881e7dec7021c91389f501` (verified integrated main). Bounded registration acceptance remains pinned separately to `21d279831455d32fe2a707e8d306af861c0bbb08`.

**Task revision:** The immutable commit containing this Draft is supplied in the PR and planning handoff. It is a candidate identity, not a governing execution revision. After decisions and independent review, validation must use this same stable path and a newly resolvable immutable Validated/Published revision. No execution launcher is issued now.

## Observed implementation audit

Paths are relative to repository root and were inspected at the implementation baseline. Source presence and today's focused tests establish only the stated implementation evidence, not missing product approval, whole-package acceptance or production fitness.

| Area | Reusable implementation and observed boundaries | Evidence classification / remaining gap |
|---|---|---|
| Admin entry and authorization | `app/Providers/Filament/AdminPanelProvider.php` registers 12 Member/Operator resources. `app/Shared/Authorization/AdminPanelAccessService.php` requires active/login-enabled account, no mandatory password change, administrator role and exact configured panel permission. `config/mhcs.php` currently allows `member.admin.access`. `DatabaseAuthorizationClaimResolver` resolves active persisted exact claims and fails closed; `LaravelAuthenticatedContextProvider` derives actor/site context. | Current `Mvp02AdminAccessTest` passes, including `/admin` redirect to `/admin/members`. Existing resource navigation is implemented; a unified operations landing page is missing in this inspected panel. Panel access does not grant all resource permissions. |
| Sites/locations | `app/Modules/Operator/Filament/Resources/OperatorSites/OperatorSiteResource.php` create/edit delegates to `OperatorSiteService`. The service locks updates, preserves stable site/organization identity, synchronizes Member references through `OperatorSiteReferenceSynchronizer`, and records audit/outbox. `app/Modules/Member/Filament/Resources/ExaminationSites/ExaminationSiteReferenceResource.php` is read-only under `member.schedule.read`. | Current Admin and Operator foundation tests pass for bounded resource access, site creation/audit, reference synchronization and deactivation preserving existing schedules. These are not two independent editable site stores. General tenant/site-restricted Admin policy is unresolved. |
| Shifts/schedules | `app/Modules/Member/Filament/Resources/ShiftSchedules/ShiftScheduleResource.php` delegates create/edit to `Mvp03ScheduleService`. It validates active references, explicit-offset future intervals, non-overlap, quota 5–20, booked-field immutability and closure handling/audit. `EligibleShiftIntakeService` holds an idempotent Operator projection, separate from Member schedule ownership. | Current Admin and foundation tests pass for service-backed creation/edit reauthorization and eligibility replay/site mismatch. Field/rehearsal exceptions are not general quota or eligibility approval. No new scheduling engine is needed. |
| Staff assignments | Existing `OperatorProfiles`, `OperatorSiteAssignments`, `OperatorEligibleShifts` and `OperatorShiftAssignments` Filament resources reuse `OperatorSiteAssignmentService` and `OperatorShiftAssignmentService`. Site assignment uses `operator.assignment.manage`; shift assignment uses `operator.shift.manage`, requires active eligible shift/profile/site and site membership, records assigning actor and audit, and supports bounded revocation. | Current Admin/foundation tests pass for separate read/manage/shift permissions and service constraints. Admin resource authorization is capability-based and the inspected lists/options do not enforce an Admin site/organization assignment predicate. Operator assignment is a different boundary. |
| Member registration/identity | Existing `MemberResource`/`Pages/CreateMember.php`, `MemberRegistrationService`, `MemberVerificationAssetService`, MRN/protected-identifier services and account-state services. PR #7 accepts mandatory adult NIK/demographics, independently optional JPEG/PNG photos, private pending/non-current evidence, pending identity/account and disabled login. | Accepted bounded implementation plus today's registration/administration tests. Registration does not book/admit a Member, physically verify identity, activate login or create an Operator profile. Those are separate actions/policies. Historical Operator field registration/admission in `OperatorFieldOperationsService` is a distinct scoped flow, not an Admin shortcut. |
| Examination assignment | `OperatorShiftAssignmentService` assigns staff to eligible schedules; `OperatorWorklistService` claims basic-examination/X-ray queue work for an authentic Operator profile at its trusted active site/assigned shift. `app/Modules/Member/Filament/Resources/Bookings/BookingResource.php` is read-only; a Member booking is not a Doctor/case assignment. Doctor provider supplies only its module boundary. | Current atomic basic-examination claim tests pass for ownership, replay and competing actor denial. No claim of a general Admin case-assignment or Doctor/radiologist assignment workflow. Its actors, qualification, clinical authority and consent boundaries remain decisions under MVP-GAP-028/029. |
| Arrival/check-in/queue | `OperatorArrivalService` confirms and records assigned-site/schedule arrival through `OperatorAttendanceContract`. `OperatorCheckInTicketService` coordinates matched identity, consent/nonclinical scoped exception, ticket allocation, Member transition, queue admission, audit/outbox and idempotency. `OperatorWorklistService` limits basic-examination work to trusted site/shift and claim ownership. `OperatorArrivals/OperatorArrivalResource.php` permits Admin read via `operator.audit.read`, no create/edit/delete. | Current Operator tests pass for arrival, ticket/queue replay, rollback, revocation and FIFO. One MySQL concurrency test is skipped on SQLite. Admin arrival audit visibility is implemented; Admin check-in/queue mutation or a centralized clinical/study worklist is not proved or authorized by these resources. |
| Audit/privacy | Existing domain mutations use `AuditStore`/`AuditEvent` and appropriate transaction/idempotency/outbox paths. Member detail/audit tests cover safe fields, target isolation and fresh callback authorization; claims/context are server-derived. | Inspected sources and focused tests support those bounded controls. Navigation exposes no new patient contents. Read-access logging completeness, wider cross-domain scope and real-data privacy readiness are not certified. Preserve existing scoped AI/storage/consent exceptions without extending them. |

### Current observed verification

All commands ran sequentially at current main before authoring, with synthetic fixtures, testing storage and `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=`. No application source changed.

| Command | Observed result |
|---|---|
| `php artisan test tests/Feature/Admin/Mvp02AdminAccessTest.php tests/Feature/Admin/Mvp03BookingAdministrationTest.php tests/Feature/Admin/Mvp04OperatorAdministrationTest.php --compact` | 27 passed; 246 assertions. |
| `php artisan test tests/Operator/Mvp04OperatorFoundationTest.php tests/Feature/Operator/Mvp04OperatorPortalTest.php tests/Feature/Operator/Mvp04dVerifiedCheckInTicketIssueTest.php tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php tests/Feature/Operator/Mvp04fAtomicBasicExaminationClaimTest.php --compact` | 67 total: 66 passed, 1 skipped; 514 assertions. Skipped: MySQL concurrent blank-ticket allocation; not counted as passing. |
| `php artisan test tests/Feature/Admin/AdminMemberRegistrationTest.php tests/Feature/Admin/Mvp02MemberAdministrationTest.php --compact` | 82 passed; 760 assertions. |

Total: 175 passed, 1 skipped, 1,520 assertions across three commands. Earlier registration acceptance evidence includes full SQLite and focused isolated MySQL results; those historical results were inspected, not rerun or represented as current CI. No browser, full suite, MySQL, real storage, external integration or production verification was performed in this planning cycle.

Codebase Memory generation `2026-10-12T13:52:33Z` supported discovery; bounded scope coverage reported no recorded issues, which is not proof of completeness. Direct sources controlled material conclusions. Graphify discovery included removed historical documentation and a truncated result; it was used only to locate documents, not as current authority or exhaustive evidence. No graph rebuild or new dependency was needed.

## Objective

**Proposed objective:** An eligible Admin can begin at `/admin`, see one operations landing page and open only the existing resource directories independently allowed by current persisted capabilities, without assuming an Operator identity or changing operational records.

This is a navigation slice over already implemented operations. It does not add operational mutations or claim a complete dashboard/worklist. Adding record counts, schedule-specific roster views or actions would require a separate authority assessment.

## Authoritative inputs

### Governing authority

- [.agents/AGENTS.md](../AGENTS.md), [software-workflow.md](../software-workflow.md), [Planner procedure](../prompts/plan-create-task.md) and [task template](./_template.md).
- [MVP-DEC-044](../../docs/mvp/decision-log.md#admin-first-priority-decision), [Admin-first scope](../../docs/mvp/beta-scope.md#admin-first-operational-scope), [roadmap](../../docs/mvp/roadmap.md#admin-first-planning-sequence), [MVP-GAP-027 and adjacent authority gaps](../../docs/mvp/beta-gap-register.md#admin-first-gaps-and-authority-reconciliation).
- [Historical requirement matrix](../../docs/implementation/mhcs-core-requirements-matrix.md) and [implementation plan approval register](../../docs/implementation/mhcs-core-implementation-plan.md): use stable ownership/invariant references; historical status/source paths are not fresh implementation evidence.
- Exact accepted registration contract/revision and [V7/A9 record](../../docs/mvp/evidence/admin-first-manual-member-registration-acceptance.md) above constrain preservation only.
- The current human request authorizes candidate publication only. Decisions D1–D4 below are proposed and unsigned; existing implementation supplies no approval for them.

### Requirement traceability

| Reference | Obligation for this candidate | Owner / verification |
|---|---|---|
| MVP-GAP-027 + MVP-DEC-044 | One persistent Admin operational entry over domain-owned capabilities; proposed navigation boundary requires D1–D4 approval | Product Owner; AC1–AC5 |
| OPR-075 / OPR-076 | Preserve Operator site and audited staff-to-shift ownership; no duplicate mutation path or generalized case assignment | Operator technical authority; AC2/AC5/AC7 |
| ARCH-038 / ARCH-039 / ARCH-040 | Preserve module ownership, shared primitives and single authentication foundation | Technical/security authority; AC1/AC4/AC6/AC7 |
| Exact PR #7 contract AC1–AC8 | Preserve accepted registration, identity/account/evidence and local-only provisioning boundaries | Member/security authorities; AC5/AC7 |
| MVP-GAP-028/029/031 | No clinical identity/content/result/private-object expansion by navigation | Clinical/security/privacy authorities when affected; AC3/AC4/AC6 |

The candidate ACs are Draft refinement, not newly approved requirement IDs. No historical matrix status is changed and no new requirement is invented to justify current code.

## Scope

### In scope, after validation only

- One Filament operations landing page at the existing Admin entry; replace the current default Members redirect only after D1 approval.
- Independently gated index links to exactly: Members, Operator sites, Member schedules, Operator profiles, Operator site assignments, Operator eligible shifts, Operator shift assignments, Member bookings and Operator arrivals. Use each existing resource's authoritative access check and URL generation, plus existing panel/account gates.
- A safe empty state when no included resource is authorized. No patient/record queries, aggregate counts or clinical status previews on the landing page.
- Focused request/Livewire authorization and compatibility tests; a bounded synthetic browser smoke and review evidence.

### Out of scope

- New site/schedule/staff/case mutation, Admin booking/admission/check-in, queue reassignment/override, physical verification, activation, financial policy, CSV/XLSX import, protocol changes, centralized imaging/report worklist or clinical data access.
- New roles/permissions, seeder/grant changes, organization/site tenancy enforcement, Operator impersonation or automatic Operator profile creation.
- Member/Operator/Doctor portal disabling, redirects or migration, account deletion/credential/session changes, temporary staff credentials and result links.
- Migrations, dependencies, production data, production permission provisioning, merge/deploy/release, broad documentation cleanup or historical ledger reconstruction.

### Preserved behavior

- Existing resource URLs, independent read/manage permissions, business services, audit/transaction/idempotency/outbox paths, private storage and safe errors.
- Existing Member/Operator login, password-change, account-state, staff/site/shift/case/ownership boundaries and separate clinical actors.
- Accepted pending-only Member registration and strictly local/testing registration bootstrap exception. All prior task revisions and acceptance identities remain unchanged.

## Dependencies and assumptions

### Blocking decisions before validation

No recommendation below is an approved default. Record answers with designated authority and exact approved artifact revision, then revise this stable task before review/publication.

| Decision | Minimum Product Owner decision / proposed bounded option | Gate / consequence |
|---|---|---|
| D1 — Admin capabilities and entry | Approve a navigation-only operations landing page as the next slice and replacement for `/admin` → Members. Approve using existing exact per-resource permissions with no new grants, role bundles or permission provisioning; read/manage remain independent. | P1/R2/T5 blocker. If substantive new operational actions are required, return to planning rather than add them here. |
| D2 — Admin site/shift scope | May already-authorized Admins continue the existing capability-wide resource visibility for this navigation slice, or must site/organization/shift restrictions exist before any new entry? Existing Admin lists are not constrained by Operator assignments. | P1/A3/T5 blocker. Restriction-first requires a new bounded domain authorization contract; this Draft cannot invent a filter, grant all-site access or claim existing scopes are tenant-safe. |
| D3 — Responsible actor | Approve retaining the authenticated Admin as the actor for existing Admin services while arrival/check-in/examination claims remain with separately authorized assigned Operator/clinical identities. No shared credentials, impersonation or synthetic staff identity. Confirm existing audit boundaries suffice for a navigation-only page; no new patient read surface is added. | P1/A3/T5 blocker; technical/security review of attribution and authorization. Delegated Admin clinical/Operator action requires its own authority and task. |
| D4 — Account/portal transition | Approve an additive entry only, preserving all current accounts and portals for this slice. Name the owner and later gate for deciding Member/staff account migration, temporary credentials and result-link transition. | P1/D4/T5 blocker for preservation boundary. Migration/authentication/link policies remain blockers for future dependent work, not decisions this candidate makes. |

The planning outcome is **PLANNING REQUIRED for implementation** until these decisions are approved; a credible bounded Draft is available for that review. B0 is supported by MVP-DEC-044; P1 is the earliest incomplete gate for this precise behavior. R2 refinement and A3/D4 review follow the decisions; T5 has not passed. E6/V7/A9 for this new slice have not started.

### Dependencies

- Verified immutable main and preserved PR #7 accepted code; refresh/reconcile baseline if main changes before validation.
- Independent Planner/Reviewer contract review after D1–D4 decisions; technical/security review under APP-001 and product/admin review under APP-003. APP-004 must be reassessed if a new identity/disclosure/real-data surface appears; current proposal excludes it.
- Existing resources and persisted claims. No new index, external business-document approval, data import or service integration is needed for the proposed navigation slice.
- No observed open PR overlaps at preflight. Future work touching Admin provider, home routing, resources or access tests must be sequenced/reconciled; preserve unrelated branches and edits.

### Approved assumptions

MVP-DEC-044 establishes the Admin-first planning direction and domain-owned administration. Existing source/testing observations support resource reuse. No scope, actor, visibility or transition policy proposed in D1–D4 is assumed approved.

### Remaining approval requirements

After eventual validation, V7/R8/A9 remain Planner/Reviewer responsibilities. Git publication of implementation requires explicit authorization separate from today's task-document authorization. Production permission/data, clinical/privacy and G10/release approvals remain separate; nothing in this task supplies them.

## Required capabilities

Repository read/write and local shell, installed PHP/Filament test tooling and an isolated synthetic browser environment. Codebase Memory/Graphify may aid discovery; current sources and observed checks control claims. No production, external patient data or new dependency installation is required.

## Execution constraints

- No execution while Draft. Execute only the exact approved/published task revision against its immutable baseline.
- Reuse the current Filament panel, resources, URL generation, authorization/context and error patterns. No parallel Admin domain, custom permission resolver, service layer or workflow engine.
- Recheck panel/account and each resource authorization on requests and Livewire interactions; cached link visibility is no grant. Untrusted form/query/session/route claims cannot broaden capabilities or actor/site/shift scope.
- Render only approved labels/index links and safe empty state. No patient queries, raw NIK, MRN, private-object keys, staff/case identifiers, credentials, clinical contents or audit payloads on this new page.
- Do not call attendance/worklist services to build navigation; they carry Operator/clinical context and potentially imaging content.
- Keep existing mutation callbacks and domain audits unchanged. Do not log sensitive claims or create success business events for merely showing links.
- Follow existing localized UI, keyboard access, focus and accessible navigation conventions; no novel design system.

## Acceptance criteria

All ACs are proposed and depend on approved D1–D4 plus preserved authority references above.

- [ ] **AC1 — Entry:** Eligible Admin login and GET `/admin` reach the operations landing page using the shared authentication foundation. Direct Members and all existing resource routes still work. The old explicit-Members-redirect test is updated only for this approved entry behavior; unauthorized login/account behavior is preserved.
- [ ] **AC2 — Independent capabilities:** Each of the nine listed index links appears only when both panel access and that existing resource's read check succeed. No read grants imply manage grants; no new permission/grant/seed changes are introduced. Test each resource independently and mixed capabilities, including arrivals requiring `operator.audit.read` rather than a fabricated attendance-read shortcut.
- [ ] **AC3 — Empty and fail-closed paths:** An otherwise panel-eligible Admin with no included resource reads sees only a safe empty state. Missing/inactive/wildcard claims, unavailable claim storage, non-Admin, suspended/pending/login-disabled/password-change accounts, and post-mount revocation cannot reveal or follow an unauthorized resource through the landing page. A hidden link alone is insufficient: direct target-route denial remains enforced.
- [ ] **AC4 — Actor and scope:** Forged actor/role/permission/site/shift/case inputs do not broaden access. Navigation creates no Operator profile or assignment, changes no active-site/session identity and grants no Operator/Doctor execution rights. Existing assigned Operator worklists and negative actor/site/shift tests continue to pass.
- [ ] **AC5 — Domain preservation:** The landing page offers no creation/edit/activation/assignment/check-in/queue/clinical actions. Existing target resources retain their bounded actions behind their exact services/permissions. Opening the page changes no domain rows, authorization assignments, identity states, audit success transitions or outbox messages.
- [ ] **AC6 — Safe presentation:** Landing response and Livewire state contain only approved link/label data, with no patient/clinical/private-object/claim payload or counts. Accessible localized navigation and empty state work with keyboard focus; there is no link to imaging/report contents or new public route.
- [ ] **AC7 — Compatibility:** Member/Operator portal routes, account authentication and PR #7 registration remain unchanged. Existing Admin access, Member registration/administration, site/schedule/assignment, arrival/check-in and claim/queue regressions pass. No schema/dependency/config/seeder or production permission changes occur.

## Verification requirements

### Required checks after validation and implementation

- Request and Livewire tests for AC1–AC6, including per-resource permission isolation, post-mount revocation, denied direct resource routes, unavailable claim storage, spoofed inputs, safe empty state and absence of domain writes. Use current Admin test fixtures and synthetic data; do not create tests that merely mirror link implementation.
- Run today's three focused commands above plus the new landing-page tests, sequentially using isolated SQLite testing storage. Preserve the MySQL concurrency skip as an explicit limitation. No new concurrency/mutation is proposed; MySQL concurrency certification is not needed to claim navigation acceptance.
- Bounded synthetic browser smoke for eligible login, mixed permissions, empty state, keyboard navigation, Members/registration link compatibility and logout; no shared live database/storage.
- Run the existing full PHP suite sequentially before V7 to cover account/portal compatibility; report skipped tests separately. Run Pint on changed PHP only and `git diff --check`.
- Inspect route/source/diff evidence for unchanged portal endpoints, services, config/seeders/schema and no new patient queries/content, claims or permissions. If available browser/testing isolation cannot be established, report the gap and request Reviewer disposition rather than claim a pass.

### Required evidence

Executor reports exact governing task, baseline and implementation revision/working-tree identity; changed surfaces; actual commands/results; AC evidence; negative tests; observed skips/gaps; deviations and unresolved risks. Local checks are not CI. Reviewer independently evaluates the exact contract and evidence; Executor does not accept itself.

## Stop conditions

- Task is still Draft, D1–D4 is unresolved/contradictory, required approval is missing, or governing task revision is unavailable.
- Baseline changed or overlapping Admin/auth changes make the contract unsafe without reconciliation.
- Site/organization-scoped Admin enforcement is required, or navigation requires a permission bundle, new grant or change to current resource authorization.
- An Admin mutation, clinical/study preview, delegated staff action, new patient query, queue override or account/portal transition becomes necessary to satisfy the objective.
- Required ACs cannot be met within this navigation boundary; architecture, privacy, actor attribution, security or compatibility demands a material new decision.
- Any unapproved commit/push/PR, dependency installation, production access, permission provisioning or external/irreversible action is needed.

Stop and return to Planner/Reviewer; do not reinterpret the Draft into a broader operational objective.

## Side-effect authorization

**Authorized now by the human planning request:** A dedicated feature branch, creation of this candidate task document, scoped task-document commit/push and Draft PR for review. Read-only repository/GitHub inspection and isolated synthetic local verification are included. No other tracked file changes are authorized by this candidate publication.

**Future Executor:** No implementation authority while Draft. A later Validated/Published revision may authorize bounded local source/tests and isolated verification only. Today's authorization does not cover implementation commits/pushes/PRs. None are authorized here.

No merge, force-push, branch deletion, deployment/release, production data/permissions, credential disclosure, portal removal, external business-document edits or unrelated changes.

## Expected terminal outcome

**This planning cycle:** Draft candidate available for decisions and independent review; **Planning Required / T5 blocked** for implementation. Candidate URL and full immutable commit are reported externally. No Executor launcher.

**After later validation:** Executor ends in **Review Required** with truthful AC evidence, or **Planning Required** when a stop condition occurs. Neither means acceptance or release.

## Review and remediation handling

**Exact next gate:** Product Owner disposition of D1–D4 at P1, followed by R2/A3/D4 reconciliation and independent Planner/Reviewer T5 contract review against this immutable Draft and `fc37002d106b839c0b881e7dec7021c91389f501`. Record applicable technical/security approval, update this same path, resolve the exact immutable Validated/Published revision, and only then issue an Executor launcher.

Independent review may request bounded Draft remediation. Changes that require tenancy enforcement, operational mutation, clinical authority or portal/account migration return to planning rather than expand this contract. No decision, validation or approval is supplied by simply opening or merging a documentation PR. Any merge remains separately unauthorized in this cycle.

## Execution evidence

No implementation of this candidate has occurred. Existing capability checks are recorded above; document/link/diff checks and candidate publication identity are supplied in the PR/handoff. Future execution/review evidence belongs in its own review record, preserving the exact governing task revision and baseline.
