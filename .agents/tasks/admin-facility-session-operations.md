---
title: Functional Admin Facility-Session Operations Board — Draft
document_id: MHCS-TASK-ADMIN-FACILITY-SESSION-OPERATIONS-001
version: 1.1
status: draft
language: en-US
last_updated: 2026-10-13
scope:
  - MVP-GAP-027 first functional Admin operations slice
authority_note: Product Owner approved priority/journey and D1–D3 product decisions. Technical/security D4, small-count disclosure and schedule-count disposition X1 remain unapproved; contract remains Draft pending independent validation. Publication for review is not T5 validation or execution authority.
---

# Draft Implementation Task

## Task identity

**Task title:** Functional Admin Facility-Session Operations Board

**Task path:** `.agents/tasks/admin-facility-session-operations.md`

**Task contract state:** `Draft`; not Validated/Published, not implementation-ready.

**Delivery objective / Work Package / MVP:** MVP-oriented usable Admin facility/date → persisted sessions → authorized operational summaries → existing management workflows → refreshed board. Bounded MVP-GAP-027 slice; WP-06/10/11/17 foundations remain reference obligations, not completed packages.

**Owner / designated planning authority:** Product Owner and independent Planner/Reviewer; Member/Operator technical/data owners and security/privacy authorities approve their respective policies. No clinical or release approval is inferred.

## Delivery context

The Product Owner's 2026-10-13 direct handoff, “MHCS — Publish Admin-First Planning Rebaseline and First Functional Task”, approves the functional-board priority and documentation-only publication. [MVP-DEC-045–047](../../docs/mvp/decision-log.md#admin-first-product-and-first-functional-slice--2026-10-13) record that authority separately from the unapproved external business working draft. The follow-up direct handoff, “MHCS — PR #9 Planning Refinement After Product Owner Approval”, approves D1–D3 at product level and clarifies the separate Patient target, recorded in MVP-DEC-048–051. Technical/security refinements are proposals, not approved defaults; product approval does not satisfy D4, privacy review or implementation authority.

PR #8 proposes links only. A board displaying persisted schedules and separately protected booking/arrival aggregates changes the objective, data-disclosure risk and acceptance boundary. This is a distinct task, not a duplicate or remediation of `.agents/tasks/admin-operations-entrypoint.md @ bc9d25724319de068d354c39fa3fbd4b9d0140c2`. Recommend supersession of that candidate; preserve its immutable identity and historical evidence. Do not modify, close or merge PR #8 in this cycle.

### Verified delivery state — 2026-10-13

- Local `main` and fetched `origin/main`: `fc37002d106b839c0b881e7dec7021c91389f501`; initially clean tracked/untracked working tree. New `task/admin-facility-session-board-planning` branch created directly from verified main, not PR #8.
- PR #6 merged at `fb25354311121fc8163256cd0530d9cbcb66bb68`; its PR body records supplied independent lean-docs acceptance under `.agents/tasks/lean-docs-and-business-authority.md @ 09b0f462fe94976394bb1357077e1a185895a3f2`. Stale current Review Required pointers are reconciled, not a new acceptance verdict.
- PR #7 merged at integrated main. Accepted bounded synthetic/local registration application: `21d279831455d32fe2a707e8d306af861c0bbb08`, governed by `.agents/tasks/admin-first-manual-member-registration.md @ e7ccc686da049e78d7634125237fc794b30a0908`, original baseline `fb25354311121fc8163256cd0530d9cbcb66bb68`. Diff to main changes only acceptance/context records, preserving accepted application code. [Acceptance evidence and limitations](../../docs/mvp/evidence/admin-first-manual-member-registration-acceptance.md) remain unchanged; merge does not imply release.
- At original publication preflight, only open PR observed was #8, Draft at `bc9d25724319de068d354c39fa3fbd4b9d0140c2`. No pending application diff on this dependent line was observed. This does not certify absence of off-repository parallel work.
- Refinement preflight: clean existing branch/HEAD/fetched branch and PR #9 at `5966c8b749aa68c0fe9688abd60ef2f83e9ccc83`; local/fetched/remote main remains `fc37002d106b839c0b881e7dec7021c91389f501`. Open PRs #8 and #9 remain Draft; no new branch or PR is created.
- State: **PENDING TECHNICAL/SECURITY REVIEW / PENDING AUTHORITY APPROVAL**. B0 and D1–D3 product decisions supplied; earliest unresolved P1 concern is material small-count disclosure policy if required. R2 traceability review, A3 D4 query/enforcement/audit and X1 disposition, and D4 delivery-readiness review remain open; T5 blocked. This cycle publishes review material only, not executable work.

## Baseline and task revision

**Implementation baseline:** `fc37002d106b839c0b881e7dec7021c91389f501`, verified integrated main. This is a proposed immutable implementation baseline, not blanket acceptance of unrelated capabilities.

**Task revision:** Immutable commit containing this Draft is identified in its Draft PR and Planner handoff. It is a review identity only. Before execution, obtain approvals, reconcile intervening main changes, and publish a new exact immutable `Validated/Published` revision at this stable path. Do not hand the current Draft to an Executor.

## Objective

Provide useful operational visibility at `/admin`: an eligible Admin logs in through `/admin/login`, selects an authorized managed facility and operational date, sees persisted schedules/service/start/end/status/quota and independently authorized capacity/booking/arrival summaries, reaches existing authorized facility/schedule creation/editing, and returns to refreshed persisted state. Retain registration and authorized detail access.

## Authoritative inputs

### Governing authority

- Direct Product Owner handoffs recorded in MVP-DEC-045–051; [scope](../../docs/mvp/beta-scope.md#product-owner-target-and-first-functional-slice), [roadmap](../../docs/mvp/roadmap.md#current-baseline-and-review-sequence--2026-10-13), and [gaps](../../docs/mvp/beta-gap-register.md#admin-first-gaps-and-authority-reconciliation).
- `.agents/AGENTS.md`, `.agents/software-workflow.md`, canonical task template and Planner procedure. Domain ownership under MVP-DEC-014 and existing architecture requirements remains binding.
- [Requirements matrix](../../docs/implementation/mhcs-core-requirements-matrix.md#admin-first-functional-planning-overlay--2026-10-13), historical ID lifecycle/source coverage and applicable WP-06/10/11/17 references. Draft additions do not alter historical assignments or certify implementation.
- Registration acceptance record above; exact original governing task remains unchanged. External Google Doc remains Draft with unresolved immutable provenance, not executable authority.

### Requirement traceability

Acceptance IDs AC1–AC8 are local to this task, following the accepted registration task's convention; no new global requirement prefix or fabricated historical approval is introduced.

| Obligation | Legitimate source / existing requirement references | Owner / verification |
|---|---|---|
| AC1–AC3: login, facility/date and persisted session/capacity visibility | MVP-DEC-047 journey and product D1–D3 in MVP-DEC-048–050; MEM-150/151/152/156 (sites, offerings, schedule/quota and bookings); ARCH-015 (trusted actor context) | Admin presentation, Member schedules/bookings; request/Livewire and date/aggregate fixtures |
| AC4: independently authorized recorded arrivals | MVP-DEC-047/050; OPR-002/075 (global site administration/synchronization), OPR-116 (attributable audit invariant) | Operator read boundary; arrival/mapping/permission fixtures |
| AC5: existing authorized management and registration/detail links | MVP-DEC-047; MEM-150/152, OPR-075; accepted registration preservation | Owning resources/services; save-return synthetic browser and regression |
| AC6–AC7: exact authorization, no operational mutations, minimized disclosure/read audit | Human exclusions and repository security/data-integrity invariants; ARCH-011/012/015, MEM-109/113/114/118 | Technical/data and security/privacy authorities; denials, payload inspection and database evidence |
| AC8: compatibility and bounded delivery | Human preservation/exclusions; MVP-DEC-014; registration acceptance | Independent Reviewer; focused/full regressions and unchanged protected paths |

Historical requirement wording is not silently broadened. CapacityStatuses is observed implementation, not proof of approval for disclosure. D1–D3 product approvals are recorded; residual disclosure/enforcement policy, D4 technical/security review, X1 approval and this traceability overlay need independent disposition before T5.

## Scope

### In scope

- One central Admin board and facility/date selection with persisted schedules, minimal service metadata and independent optional summaries.
- Existing authorized facility/schedule create/edit and directory/detail/registration navigation; reload persisted state after returning or explicit refresh. No embedded scheduling editor or new scheduler.
- Domain-owned read queries and narrow aggregate authorization/read-audit adaptation needed for this board, subject to D4 and residual privacy review. Option A would include the tightly bounded existing schedule-count correction only if X1 is explicitly approved and reflected in the validated contract; it is currently a recommendation, not authorized implementation scope. Include meaningful request/Livewire/database/browser tests.
- Accessible Indonesian operational copy, keyboard labels/focus, timezone/offset visibility and distinguishable empty/denied/unavailable states.

### Out of scope

Patient migration/schema/model renaming; new tenant model, capabilities/grants/permission provisioning; account/portal retirement; imports; booking/arrival/check-in/queue/clinical actions; staff/case assignment; radiologist workspace or authentication changes; AI/report fixes or previews; AI escalation workflow; patient results/links; external SIMRS integration; new service catalog editing; public surfaces; dependency installation; production access/data, external systems, release or deployment.

### Preserved behavior

Preserve accepted Admin Member registration, pending identity/account and optional private evidence rules; existing authorized Member/booking/site/schedule details; legacy Member/Operator portals and real Operator assignment checks; authenticated Grabber and NPZ/MPIPS; private imaging/report boundaries, audit/outbox/idempotency and schedule mutation guards. No synthetic Operator identity or repurposed `operator.active_site_id` session context. Existing task revisions and historical acceptance records stay intact. Existing resource authorization is preserved until X1 approval; any approved change is restricted to the booking-derived schedule count, not schedule read/manage or mutation services. Patient target (account independence, facility-scoped MRNs, optional initial NIK, provisional identities and reviewed ambiguous matches) is separate and does not relax accepted adult Admin Member registration.

## Dependencies and assumptions

### Dependencies

- D1–D3 below are product-approved; D4, X1 and residual technical/security/privacy decisions are blocking before T5; neither panel permission nor a chosen facility satisfies them.
- Existing domain sources and stable mapping must support bounded Admin aggregate queries without invoking Operator mutation/attendance flows. A narrow owning-module query may be needed; do not introduce a parallel service framework.
- Isolated synthetic SQLite, disposable supported MySQL and local browser environment for required verification. Reconcile concurrent changes to Admin/auth/schedules before validation/execution; PR #8 must not be implemented alongside this board without explicit planning disposition.
- Broader Patient/SIMRS/radiologist/AI/publication work has separate contracts. MVP-GAP-035/036 are separate safety/provenance risks: no new AI exposure here and no closure by board acceptance.

### Approved assumptions

The supplied priority/journey, D1–D3 product decisions and preservation/exclusions are product-approved. D1 permits authorized historical inactive facilities but grants no new booking/schedule mutations; D2 fixes facility-local session-start membership; D3 fixes summary categories and the Kapasitas Tercadangkan label using current approved domain capacity semantics. Existing panel/resource permissions, services, models and status sets below are source observations. They do not grant new disclosure or choose unresolved policy. All MHCS-managed facilities are the initial central operating model subject to current exact authorization, not an invented tenant permission system.

### Remaining approval requirements

| Decision | Recorded product approval / remaining review | Owner / deadline |
|---|---|---|
| D1 — Managed-facility and data scope | **PO approved**, MVP-DEC-048: all trusted persisted MHCS-managed facilities under existing capabilities, authorized inactive-facility history, no selector grant/tenant system/new mutation authority. Technical/data/security must verify Member-reference mapping and exact capability-wide cross-facility enforcement; minimal service/reference disclosure remains reviewed, not blanket policy approval | Product decision recorded; Member/Operator technical/data and security/privacy residual review before T5 |
| D2 — Operational date | **PO approved**, MVP-DEC-049: verified facility IANA local start date, overnight starting date, half-open UTC boundaries/DST and safe invalid/missing/inconsistent zone failure. Exact conversion/default/filter-restoration implementation and negative-test evidence still require technical review | Product decision recorded; technical/data review before T5 |
| D3 — Aggregates/disclosure | **PO approved**, MVP-DEC-050: persisted quota, Kapasitas Tercadangkan using current approved domain capacity statuses, totals by booking status, distinct bookings with recorded arrivals, independent reads and denied/unavailable/zero distinction. Query details, minimal service disclosure and any small-count restrictions remain subject to review; no privacy waiver | Product decision recorded; Member/Operator data/security/privacy residual review before T5 |
| D4 — Query/enforcement and read audit | **Not approved.** Determine domain-owned query interfaces, fresh initial/subsequent Livewire authorization, protected-query denial, cross-facility scope, minimization/source-unavailable handling; approve exact audit event definitions/safe metadata and audit failure behavior. Proposed append-failure withholding rule is not yet approved | Attributable authorized Member/Operator technical and security/privacy review, then independent Planner/Reviewer; A3/D4 before T5 |
| X1 — Existing schedule-count discrepancy | **Not dispositioned.** Option A bounded resource count correction recommended below, or Option B separate blocking remediation dependency. Explicit approval must settle compatibility/disclosure/permission policy and be reflected in the contract; board-only hiding does not resolve it | Technical/data and security/privacy with Product acknowledgment of affected-user behavior, independent Planner/Reviewer; before T5 |

After dispositions, independent Planner/Reviewer evaluates the exact revised contract and authority at P1/R2/A3/D4/T5. Publishing this Draft is not self-approval. Product Owner direction does not substitute for technical/clinical/privacy acceptance. Future implementation commit/push/PR authority must be separately explicit; this planning authorization does not carry over. G10 remains separate.

## Required capabilities

For eventual validated execution: repository read/bounded source and test write, local PHP/Filament/Livewire testing, isolated synthetic SQLite/MySQL, synthetic local browser, diff and payload inspection. Available intelligence tools are aids with direct-source verification. No production database, external AI PACS, Google Drive write, or real-patient capability required.

## Execution constraints

D1–D3 product rules are approved through MVP-DEC-048–050. Detailed interfaces, capability enforcement, small-count disclosure, read-audit definitions/failure behavior and X1 scope below remain **Draft technical/security proposals** pending attributable review; do not implement this Draft.

### Reuse and observed implementation boundaries

Source inspected at the immutable baseline:

- `app/Providers/Filament/AdminPanelProvider.php`, `app/Shared/Authorization/AdminPanelAccessService.php`: current `/admin/login`, declared Member/Operator resources; active/login-enabled administrator, no mandatory password change and exact configured panel permission. Source tests confirm `/admin` currently redirects to Members; no board is present in this inspected provider.
- `MemberAuthorization` / `OperatorAuthorization` and persisted claim/context providers: retain exact fresh checks and unavailable-store fail-closed behavior. Panel entry alone grants no resource access.
- `ShiftScheduleResource`, `Mvp03ScheduleService`, `Mvp03BookingService::capacityStatuses()`: Member owns schedules and capacity rules; create/edit already enforce explicit-offset UTC intervals, future/non-overlap/quota validation, booked-field immutability and closure behavior. Reuse these unchanged mutation paths.
- `OperatorSiteResource`, `OperatorSiteService`, `Mvp04OperatorSiteReferenceService` and `OperatorSiteReferenceSynchronizer`: Operator owns physical facility; Member owns synchronized examination-site reference. Stable `operator_site_id` is not either table's local primary key.
- `BookingResource` read and separately gated audit; `OperatorArrivalResource` read-only via `operator.audit.read`. The latter's `safeArrivalSummary` exposes Member detail through an attendance contract; a numeric board summary must not invoke/load that detail.
- `AuditStore` / `AuditEvent`: reuse sanitized attributable append-only evidence; no new logging framework or domain success events for a read.

### Schedule-count authorization disposition — X1

**Source-confirmed issue:** `ShiftScheduleResource::table()` defines `confirmed_count`, labelled `Terkonfirmasi`, with a booking relation query using `capacityStatuses()`; `canViewAny()` and `ListShiftSchedules::authorizeAccess()` require schedule-read but no independent booking-read check. The status subset includes more than confirmed bookings. Hiding it only on the new board leaves the existing resource exposure intact.

**Recommendation: Option A, pending approval.** Include a tightly bounded count authorization/presentation correction in the eventual board contract: require fresh `member.booking.read` before executing or serializing the existing count, use **Kapasitas Tercadangkan**, distinguish denied/unavailable/verified-zero, and clear stale count state after revocation. Preserve the internal `confirmed_count` attribute where compatibility needs it; no global field rename, status-set change, new grant, permission bundle, schema or scheduling-service edit. Mere column visibility or a check performed only at mount is insufficient; protected queries and subsequent Livewire requests must enforce the approved boundary.

| Assessment | Evidence / proposed compatibility boundary |
|---|---|
| Permissions/disclosure | `MemberAuthorization::scheduleRead()` and `bookingRead()` require separate exact claims. Product D3 requires booking-read for booking-derived figures. Count exposure can reveal activity even without patient names; small-count/cross-facility policy stays in D4/security/privacy review |
| Affected users | Schedule-read-only and schedule-manage-without-booking-read Admins retain permitted schedule metadata and existing authorized management workflows but lose numeric booking-derived counts. Admins with both reads retain the same capacity-status values, subject to privacy policy. Arrival access, Operator roles/assignments and Member self-service are not broadened; no real-account permission inventory or production provisioning was performed |
| Backward compatibility | Deliberate tightening of one Admin UI field requires explicit X1 authorization. Resource URLs, schedule read/manage checks, quota/status/source records and services stay intact. Preserve underlying booking eligibility/capacity and booked-schedule immutability; no automatic grants to restore old disclosure |
| Connected implementation | Current graph identifies `capacityStatuses()` consumers in `Mvp03CatalogueService::schedules()`, `Mvp03ScheduleService::update()` and this resource table, verified by direct source. Do not modify the shared status helper or those domain paths to implement a UI read restriction |
| Existing evidence / missing tests | `Mvp03BookingAdministrationTest::test_exact_read_permissions_expose_bounded_resources_and_booking_is_read_only` uses schedule-read **and** booking-read. Its Livewire create/edit test includes schedule read/manage without booking-read, proving workflow access, not denial of count queries. Source review of this file found no direct protected-count-query/serialization denial assertion; original 27-test regression result does not close X1 |

**Required evidence if A is approved:** resource and board tests with schedule-read-only, schedule-manage without booking-read, both reads, neither read, post-mount booking-read revocation and unavailable claim/query sources. Spy/inspect booking queries and complete HTML/Livewire payloads to prove no protected query or stale count for denied access; authorized counts cover every capacity/non-capacity status, nonzero/zero and duplicate-join fixtures. Existing management tests and booking/capacity service regressions must remain valid. No writes/grants/portal changes.

**Option B alternative:** establish a separate validated blocking remediation dependency if independent technical/security review requires broader scope or a separate risk/acceptance boundary. Pin its exact contract, implementation and independent acceptance; reconcile the board baseline after remediation before board readiness. No separate task or implementation is created here. Until approved A is reflected in this contract or approved B is explicitly governed as a blocking dependency, **X1 is unresolved and T5 remains blocked**. Recommendation is not disposition, and this Planner does not approve its own permission-policy change.

### Exact authorization boundary

| Board surface / navigation | Required existing authorization, in addition to current panel access |
|---|---|
| Minimal facility selector, schedules, service label, times/status/quota | `MemberAuthorization::scheduleRead()` / `member.schedule.read`; minimal reference metadata only, not address/organization/private site detail or broad service catalogue |
| Kapasitas Tercadangkan / remaining capacity and booking status/total summary | Schedule-read **and** `MemberAuthorization::bookingRead()` / `member.booking.read`; neither manage nor panel access substitutes |
| Arrival aggregate for a displayed session | Schedule-read **and** `OperatorAuthorization::auditRead()` / `operator.audit.read`; independent of booking-read; no attendance/queue mutation authority |
| Schedule creation/edit navigation | Existing `ShiftScheduleResource` route and create/edit check, `member.schedule.manage`; board visibility still requires schedule-read; mutation service reauthorizes |
| Physical facility directory/detail, create/edit navigation | Existing `OperatorSiteResource` checks, `operator.site.read` for directory/detail and `operator.site.manage` for create/edit; do not grant either implicitly |
| Registration, Member and booking detail | Existing resource/action checks unchanged, including `member.registration.manage`, existing Member read/detail checks and separate `member.booking.audit.read` for booking audit; no board patient previews |

Reauthorize on initial load, every Livewire callback/filter/refresh and before queries/links; never trust actor/role/capabilities/site/timezone from client state. If panel eligibility or schedule-read is absent/revoked, board data is denied with safe navigation to otherwise authorized existing resources. Optional section denial performs no protected query and renders explicit unauthorized state; query/source failure renders unavailable, never zero. Clear stale data after permissions/facility/date changes; no cross-actor cache. Direct target routes retain independent authorization.

### Facility and date selection

- Explicit facility selection from permitted persisted references, stable deterministic ordering by display name then ID. Before selection show a prompt, not a synthetic all-facility result. Resolve server-side; reject invalid/forged/unknown values without enumerating records. Never modify Operator active-site state.
- Date is strictly `YYYY-MM-DD`, rejecting impossible dates, timestamp input and unapproved filter fields. After first facility selection, default to **today in that facility's timezone** using the shared clock. Keep an explicitly chosen calendar date on a facility switch, recalculate its UTC boundaries, and show the current facility/timezone. Returning from an authorized workflow may restore validated non-sensitive facility/date filters; revalidate on the server and query persisted state.
- Resolve valid IANA zone from the selected synchronized reference, with Operator ownership preserved. Missing/invalid or mismatched mapping/timezone yields unavailable; do not assume Asia/Jakarta, app or browser time. Construct local midnight and next calendar midnight independently and convert both to UTC: half-open `[start, next_start)` avoids inclusive-next-day errors and handles 23/25-hour DST days without adding fixed 24 hours.
- Show schedules whose `starts_at` is in that UTC range and whose `examination_site_id` is the selected Member reference ID. Include open and closed schedules and past dates. Overnight sessions belong once to their **start's facility-local date**, with full end date/offset shown; this is a session-day board, not a wall-clock attendance/day-of-occurrence report. Sort by start then stable schedule ID; show both offsets on DST transitions. Inactive facilities/schedules remain clearly historical read views; existing services alone determine edit feasibility. No extra timezone or schedule mutation.

### Aggregate definitions

All aggregates are for the displayed persisted session IDs, **not** booking-created/confirmed date or arrival-occurrence date. Facility/date selection determines sessions first. No global totals or patient list.

- **Quota:** persisted schedule quota. **Kapasitas Tercadangkan (Reserved Capacity):** count each Member booking row once for `shift_schedule_id` using exactly `Mvp03BookingService::capacityStatuses()` — observed `pending_payment`, `confirmed`, `arrived`, `checked_in`, `in_progress`, `postponed`. `completed`, `cancelled`, `no_show` do not consume capacity in this current method. This is reserved-capacity accounting, not number of examined patients or clinical completion. Do not change the status set or imitate it in another scheduler.
- **Remaining capacity:** `max(quota - reserved, 0)` when authorized counts are available; show an explicit over-capacity warning if reserved capacity exceeds quota. Never clamp reserved capacity itself or imply a closed schedule accepts bookings. Both figures require booking-read; otherwise show unauthorized/unavailable rather than deriving zero from missing data.
- **Booking summary:** one count per current known `BookingStatus` and a total count across those booking rows for each session, independent of the capacity subset. No point/funding/identity details. Count booking IDs once before joins; do not sum multiplied relation rows. Unknown status or invalid data makes affected summary unavailable and is escalated, not silently categorized as normal/zero. A verified no-row query produces zero, unlike denial/error.
- **Arrival summary:** count distinct non-null `booking_id` among Operator arrival rows with `status = recorded`, matching `member_schedule_id` to displayed session and `operator_site_id` to the verified Operator **local primary key**. Resolve Member reference's stable `operator_site_id` to `OperatorSite.operator_site_id`, then its local ID; never equate stable/local IDs. Count a recorded arrival even after booking advances to checked-in/in-progress/completed; do not infer attendance from booking status or queue state. Replay/duplicate rows for one booking count once, irrespective of multiple operators. No Member join needed for the summary; it is not clinical throughput. Mismatched/orphan/unknown-state source data must not be attributed to another facility or silently certify completeness: mark affected aggregate unavailable and report a safe integrity condition.
- Show per-section last successful retrieval time (UTC instant, rendered facility-local) and source/state; refresh after return. Counts are committed read-time observations, may change after refresh, do not reserve capacity or certify a cross-domain transactional snapshot. Query schedule/booking together with a consistent bounded read as supported by the current database; avoid join fan-out and per-row detail loading. Arrival read is independently authorized; no new write locks, schema, reconciliation worker or realtime polling required.

### Disclosure and read audit

Board contains only permitted facility label/state/timezone, schedule reference, minimal service label, dates/times/status/quota and authorized aggregate values. No patient names, NIK, MRN, photos, booking/arrival identities, clinical values, DICOM/PDF URLs, object keys, raw authorization claims, tokens or exceptions in HTML, Livewire state, exports or logs. Existing authorized details remain separate. D3 product approval does not waive small-count/disclosure restrictions; their decision remains in D4/security/privacy review; no privacy approval inferred from numeric-only display.

Proposed D4: append attributable board read audit for each data-bearing initial/filter/refresh request, with server-derived actor/session/correlation, permitted facility reference, selected date/timezone, read sections and outcome. Use sanctioned opaque references and sanitized reason codes; do not log counts, patient identifiers or denied facility metadata. Audit append failure withholds affected data and reports unavailable; do not manufacture successful read or mutation events. Optional denied/unavailable sections have truthful outcomes; missing trustworthy actor/claim context fails closed. Reuse existing audit schema and sanitizer; define exact event source/action/metadata with owning authorities before T5. Read audit is the only proposed persistent board side effect, distinct from operational mutations.

## Acceptance criteria

D1–D3 product outcomes below are approved; technical/security realization, small-count controls and X1 remain subject to independent approval. Boxes remain unchecked until independently reviewed implementation evidence exists.

- [ ] **AC1 — Usable entry and selection:** Eligible Admin uses `/admin/login` → `/admin`, selects managed facility/date and sees persisted service, start/end/status/quota/session reference. No selection, no schedules and ineligible account cases are distinct and safe; existing authorized directories/registration remain reachable.
- [ ] **AC2 — Facility-local dates:** Strict validation, local today, UTC half-open membership, two facilities in different zones, UTC/local midnight, next-day exclusion, overnight start-day membership, DST 23/25-hour dates/offsets and invalid/mismatched zones meet the approved rule. Forged/stale site/date inputs fail safely; inactive/closed/history views meet D1/D2.
- [ ] **AC3 — Booking/capacity:** The approved **Kapasitas Tercadangkan** label distinguishes reserved capacity from confirmed patients/completed examinations/clinical throughput. Total/by-status and reserved/remaining semantics match synthetic persisted bookings for every status, zero and over-quota cases, with no join multiplication. Schedule-read alone never exposes booking-derived values, even through Livewire state. Unknown/source-failed counts are unavailable, not zero. No booking writes. X1 must be approved before readiness; if A is approved, the existing schedule resource must meet the same fresh booking-read/query-denial/payload boundary, not only the board. If B is chosen, its blocking contract and evidence must be pinned and baseline reconciled.
- [ ] **AC4 — Arrivals:** Arrival-audit permission alone, combined with board schedule-read, permits only the approved numeric summary; booking-read does not grant arrival access. Distinct replay/duplicate booking handling, progressed bookings, stable/local site-ID separation, cross-facility/mismatched/orphan data and unavailable sources behave as approved. No patient summaries or arrival/check-in/queue writes.
- [ ] **AC5 — Existing workflows and fresh state:** Authorized facility/schedule creation/edit navigation delegates to existing resources/services; synthetic save → return shows persisted changes and revalidated filters. Read-only/Admin-without-manage actors cannot mutate. Registration and authorized detail pages preserve their accepted behavior; booked schedule immutability and independent service authorization remain enforced.
- [ ] **AC6 — Fresh exact authorization and minimized disclosure:** Test each capability independently, mixed grants, missing/inactive/wildcard claims, unavailable claim store, non-Admin, suspended/pending/login-disabled/password-change accounts, forged actor/site/role/permission state, direct-route access and post-mount revocation. No unauthorized query/payload/cached values, including the existing schedule count if A is approved; no synthetic Operator/assignment or broader grant. Accessible localized labels, focus and independent denied/unavailable states work without exposing private data.
- [ ] **AC7 — Auditable read-only integrity:** Approved attributable read/outcome events exist; audit failure withholds data. Opening/filtering/refreshing board changes only approved read audit; booking/arrival/queue/clinical/identity/permission, mutation-audit and outbox rows remain unchanged. No PHI/counts/secret payloads in logs/audit. Refresh reports truthful read-time evidence, not guaranteed realtime state.
- [ ] **AC8 — Regression and review boundaries:** Accepted Admin registration, legacy portals, existing Admin resource/service and Operator assignment/arrival/check-in/queue flows pass scoped and full regressions; isolated MySQL and synthetic browser prove date/count/no-write and save-return behavior. No application change beyond the approved board and explicitly dispositioned X1 boundary, schema/runtime-config/seeder/dependency/grant/clinical change or production access. Independent Reviewer assesses exact immutable implementation against the exact validated contract; no self-acceptance/release.

## Verification requirements

### Required checks after validation and implementation

1. Meaningful request/Livewire tests for AC1–AC7 using existing Admin synthetic fixtures, including X1 existing-resource coverage if A is approved (or evidence of the governed blocking dependency if B is selected). Verify protected queries are not executed for missing capabilities and inspect full HTML/Livewire/error payloads; hidden controls alone do not prove authorization.
2. Regression commands, sequentially with isolated testing storage and `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=`:
   - `php artisan test tests/Feature/Admin/Mvp02AdminAccessTest.php tests/Feature/Admin/Mvp03BookingAdministrationTest.php tests/Feature/Admin/Mvp04OperatorAdministrationTest.php --compact`
   - `php artisan test tests/Feature/Admin/AdminMemberRegistrationTest.php tests/Feature/Admin/Mvp02MemberAdministrationTest.php --compact`
   - `php artisan test tests/Operator/Mvp04OperatorFoundationTest.php tests/Feature/Operator/Mvp04OperatorPortalTest.php tests/Feature/Operator/Mvp04dVerifiedCheckInTicketIssueTest.php tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php tests/Feature/Operator/Mvp04fAtomicBasicExaminationClaimTest.php --compact`
   - New board suite plus `tests/Architecture` and `tests/Security`, then full `php artisan test --compact`; report all skips separately, not as passes.
3. Isolated supported MySQL synthetic board/date/aggregate tests and focused schedule/booking/arrival/registration database conformance; inspect persisted UTC values, distinct counts, cross-facility filtering, no domain/outbox/claim writes, audit-only deltas and existing write-service rollback/immutability. No production DB or existing development dataset. Reuse existing disposable DB tooling/images if available; installation/network/infrastructure side effects need separate authority. Report unsupported contention checks rather than imply concurrency certification.
4. Bounded synthetic browser: actual eligible login, facility/date filtering including multi-zone fixtures, persisted rows/authorized summaries, permission variants, denial/unavailable states, keyboard navigation, existing site and schedule save-return, registration/detail accessibility and logout. Disposable isolated local test DB/private storage only; no patient/credential recording in browser artifacts. Missing environment blocks required evidence unless independently dispositioned; do not silently skip.
5. Pint on changed PHP only, `git diff --check`, route/source/diff review for preserved endpoints/resources/services and protected paths. Verify bounded query count with multiple sessions to catch per-row patient loading or join fan-out. No speculative throughput target.

### Required evidence

Executor reports exact validated task revision, implementation baseline and immutable implementation revision or precise working-tree identity, changed surfaces, actual command output/counts/skips, added tests, per-AC positive/negative/database/browser evidence, safe audit/query/no-write evidence, deviations and unresolved risks. Local tests are not CI or acceptance. CI state must be observed, not inferred. Production readiness remains unverified.

### Original publication checks — 5966c8b749aa68c0fe9688abd60ef2f83e9ccc83

At unchanged integrated baseline, the first three-file Admin regression command above actually passed **27 tests, 246 assertions** with synthetic SQLite testing fixtures. This confirms only existing tested Admin boundaries, not this unimplemented board. Source/claim/resource/date/status inspection and immutable registration diff support the Draft. No full suite, MySQL, browser, external AI or production check was run in this planning cycle. Graphify's existing graph was built at `c7c237dfdd9785b127ca3228f5bf527cfe8ca552`, so documentation findings were directly verified; Codebase Memory coverage for cited primary code reported no recorded gap/metadata match, with direct-source confirmation rather than completeness claims.

Documentation verification also passed: exact nine-document allowlist, 87 local links/anchors, task-template/Draft/eight-AC/four-decision structure, registered requirement IDs, byte-preserved historical sections and original matrix, untouched code/acceptance evidence, high-confidence added-text secret/private-ID pattern scan, and `git diff --check`. These are planning checks, not independent approval or application certification.

### Refinement verification

Against prior planning revision `5966c8b749aa68c0fe9688abd60ef2f83e9ccc83`, the bounded refinement check passed: nine-document allowlist, 100 local links/anchors, direct D1–D3 product provenance, Draft/eight-AC structure, explicitly unapproved D4/X1, complete Patient target, preserved historical sections/original matrix/AI follow-ups/application and acceptance evidence, and high-confidence added-text secret/private-ID patterns. `git diff --check` passed. Current source and the focused existing test file were inspected for X1; graph coverage reported metadata match/no recorded issue for the bounded cited paths, with direct-source confirmation. No application tests were rerun in this documentation-only refinement; original test evidence above remains historical and does not prove the proposed correction.

## Stop conditions

Stop and return to Planner/Reviewer if task remains Draft, D4, X1, residual technical/security/privacy decisions or independent review are unresolved, exact governing task/baseline is unavailable or materially changed, required facility/timezone mapping or approval cannot be established, authorization requires new grants/tenant scope/synthetic Operator, aggregates need patient detail or clinical/publication access, schema/config/permission/portal changes become necessary, existing resource discrepancy requires broader remediation, read-audit policy/sanitizer cannot support approved disclosure, verification environment is unsafe/unavailable, or a materially new privacy/security/data/clinical risk changes scope. Do not choose policy defaults, silently weaken required evidence or implement separate AI repairs.

## Side-effect authorization

**Current refinement cycle:** Human explicitly authorizes bounded planning/product/requirement/context documents, commit and ordinary push on the existing `task/admin-facility-session-board-planning` branch, and revision of existing Draft PR #9. No new branch/PR is created. Original publication authority is historical; this continuation does not authorize implementing the recommended X1 correction. No application/test/migration/runtime-config/seeder changes or Executor launcher. PR #8 remains untouched. No merge/deploy/release, production/real-patient access, permission provisioning or external Google business-document changes.

**Eventual implementation:** None granted by this Draft. Before validation/handoff, obtain explicit bounded source/test and isolated-verification authority and disposition any commit/push/PR/infrastructure needs. No future side effect is inferred from current planning authorization.

## Expected terminal outcome

Current Planner cycle ends with **Draft documentation PR / independent review and approval required**. No implementation execution, T5 pass or Executor launcher.

After future validation, Executor ends **Review Required** with actual evidence or **Planning Required** with verified stop evidence; Executor does not self-accept. Independent implementation review and immutable A9 recording follow separately; release requires G10.

## Review and remediation handling

Independent Reviewer inspects the exact Draft publication against verified main, supplied PO direction, unchanged PR #8 candidate, immutable registration acceptance, source reuse, recorded product D1–D3, D4, small-count/data-scope controls and X1. Obtain attributable authorized D4 review and explicit X1 A/B disposition, retaining approved product decisions; reconcile residual P1 disclosure policy and R2/A3/D4 before independent T5 validation. Revise this same stable path for bounded board-contract corrections and publish a new exact immutable contract only after gates pass. Do not relabel this Draft as Validated because it is committed or the PR exists. Materially distinct objectives return to Delivery Planning; preserve task and execution identities.

## Execution evidence

No board implementation or self-certified acceptance exists. Future evidence is reported outside this planning contract, preserving exact governing task, baseline, implementation revision, checks, independent verdict and any remediation/accepted baseline. Separate AI findings are tracked in MVP-GAP-035/036, not claimed resolved by this task.
