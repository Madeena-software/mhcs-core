---
title: Functional Admin Facility-Session Operations Board — Draft
document_id: MHCS-TASK-ADMIN-FACILITY-SESSION-OPERATIONS-001
version: 1.0
status: draft
language: en-US
last_updated: 2026-10-13
scope:
  - MVP-GAP-027 first functional Admin operations slice
authority_note: Product Owner approved priority and journey; proposed technical, aggregate, date, disclosure and audit refinements remain Draft pending designated approvals and independent Planner/Reviewer review. Publication for review is not T5 validation or execution authority.
---

# Draft Implementation Task

## Task identity

**Task title:** Functional Admin Facility-Session Operations Board

**Task path:** `.agents/tasks/admin-facility-session-operations.md`

**Task contract state:** `Draft`; not Validated/Published, not implementation-ready.

**Delivery objective / Work Package / MVP:** MVP-oriented usable Admin facility/date → persisted sessions → authorized operational summaries → existing management workflows → refreshed board. Bounded MVP-GAP-027 slice; WP-06/10/11/17 foundations remain reference obligations, not completed packages.

**Owner / designated planning authority:** Product Owner and independent Planner/Reviewer; Member/Operator technical/data owners and security/privacy authorities approve their respective policies. No clinical or release approval is inferred.

## Delivery context

The Product Owner's 2026-10-13 direct handoff, “MHCS — Publish Admin-First Planning Rebaseline and First Functional Task”, approves Option A and documentation-only publication. [MVP-DEC-045–047](../../docs/mvp/decision-log.md#admin-first-product-and-first-functional-slice--2026-10-13) record that authority separately from the unapproved external business working draft. New refinements below are proposals, not approved defaults.

PR #8 proposes links only. A board displaying persisted schedules and separately protected booking/arrival aggregates changes the objective, data-disclosure risk and acceptance boundary. This is a distinct task, not a duplicate or remediation of `.agents/tasks/admin-operations-entrypoint.md @ bc9d25724319de068d354c39fa3fbd4b9d0140c2`. Recommend supersession of that candidate; preserve its immutable identity and historical evidence. Do not modify, close or merge PR #8 in this cycle.

### Verified delivery state — 2026-10-13

- Local `main` and fetched `origin/main`: `fc37002d106b839c0b881e7dec7021c91389f501`; initially clean tracked/untracked working tree. New `task/admin-facility-session-board-planning` branch created directly from verified main, not PR #8.
- PR #6 merged at `fb25354311121fc8163256cd0530d9cbcb66bb68`; its PR body records supplied independent lean-docs acceptance under `.agents/tasks/lean-docs-and-business-authority.md @ 09b0f462fe94976394bb1357077e1a185895a3f2`. Stale current Review Required pointers are reconciled, not a new acceptance verdict.
- PR #7 merged at integrated main. Accepted bounded synthetic/local registration application: `21d279831455d32fe2a707e8d306af861c0bbb08`, governed by `.agents/tasks/admin-first-manual-member-registration.md @ e7ccc686da049e78d7634125237fc794b30a0908`, original baseline `fb25354311121fc8163256cd0530d9cbcb66bb68`. Diff to main changes only acceptance/context records, preserving accepted application code. [Acceptance evidence and limitations](../../docs/mvp/evidence/admin-first-manual-member-registration-acceptance.md) remain unchanged; merge does not imply release.
- Only open PR observed at preflight: #8, Draft at `bc9d25724319de068d354c39fa3fbd4b9d0140c2`. No pending application diff on this dependent line was observed. This does not certify absence of off-repository parallel work.
- State: **PENDING PLANNING REPAIR / PENDING AUTHORITY APPROVAL**. B0 direction supplied; earliest unresolved gate P1 (D1–D3 refinements), R2 traceability Draft, A3 read/query/audit boundary awaiting D1/D4, D4 sequence proposed, T5 blocked. This cycle publishes review material only, not executable work.

## Baseline and task revision

**Implementation baseline:** `fc37002d106b839c0b881e7dec7021c91389f501`, verified integrated main. This is a proposed immutable implementation baseline, not blanket acceptance of unrelated capabilities.

**Task revision:** Immutable commit containing this Draft is identified in its Draft PR and Planner handoff. It is a review identity only. Before execution, obtain approvals, reconcile intervening main changes, and publish a new exact immutable `Validated/Published` revision at this stable path. Do not hand the current Draft to an Executor.

## Objective

Provide useful operational visibility at `/admin`: an eligible Admin logs in through `/admin/login`, selects an authorized managed facility and operational date, sees persisted schedules/service/start/end/status/quota and independently authorized capacity/booking/arrival summaries, reaches existing authorized facility/schedule creation/editing, and returns to refreshed persisted state. Retain registration and authorized detail access.

## Authoritative inputs

### Governing authority

- Direct Product Owner handoff recorded in MVP-DEC-045–047; [scope](../../docs/mvp/beta-scope.md#product-owner-target-and-first-functional-slice), [roadmap](../../docs/mvp/roadmap.md#current-baseline-and-review-sequence--2026-10-13), and [gaps](../../docs/mvp/beta-gap-register.md#admin-first-gaps-and-authority-reconciliation).
- `.agents/AGENTS.md`, `.agents/software-workflow.md`, canonical task template and Planner procedure. Domain ownership under MVP-DEC-014 and existing architecture requirements remains binding.
- [Requirements matrix](../../docs/implementation/mhcs-core-requirements-matrix.md#admin-first-functional-planning-overlay--2026-10-13), historical ID lifecycle/source coverage and applicable WP-06/10/11/17 references. Draft additions do not alter historical assignments or certify implementation.
- Registration acceptance record above; exact original governing task remains unchanged. External Google Doc remains Draft with unresolved immutable provenance, not executable authority.

### Requirement traceability

Acceptance IDs AC1–AC8 are local to this task, following the accepted registration task's convention; no new global requirement prefix or fabricated historical approval is introduced.

| Obligation | Legitimate source / existing requirement references | Owner / verification |
|---|---|---|
| AC1–AC3: login, facility/date and persisted session/capacity visibility | MVP-DEC-047 journey; MEM-150/151/152/156 (sites, offerings, schedule/quota and bookings); ARCH-015 (trusted actor context) | Admin presentation, Member schedules/bookings; request/Livewire and date/aggregate fixtures |
| AC4: independently authorized recorded arrivals | MVP-DEC-047; OPR-002/075 (global site administration/synchronization), OPR-116 (attributable audit invariant) | Operator read boundary; arrival/mapping/permission fixtures |
| AC5: existing authorized management and registration/detail links | MVP-DEC-047; MEM-150/152, OPR-075; accepted registration preservation | Owning resources/services; save-return synthetic browser and regression |
| AC6–AC7: exact authorization, no operational mutations, minimized disclosure/read audit | Human exclusions and repository security/data-integrity invariants; ARCH-011/012/015, MEM-109/113/114/118 | Technical/data and security/privacy authorities; denials, payload inspection and database evidence |
| AC8: compatibility and bounded delivery | Human preservation/exclusions; MVP-DEC-014; registration acceptance | Independent Reviewer; focused/full regressions and unchanged protected paths |

Historical requirement wording is not silently broadened. CapacityStatuses is observed implementation, not proof of approval for disclosure. D1–D4 and this traceability overlay need independent disposition before T5.

## Scope

### In scope

- One central Admin board and facility/date selection with persisted schedules, minimal service metadata and independent optional summaries.
- Existing authorized facility/schedule create/edit and directory/detail/registration navigation; reload persisted state after returning or explicit refresh. No embedded scheduling editor or new scheduler.
- Domain-owned read queries and narrow aggregate authorization/read-audit adaptation needed for this board, subject to D1–D4; meaningful request/Livewire/database/browser tests.
- Accessible Indonesian operational copy, keyboard labels/focus, timezone/offset visibility and distinguishable empty/denied/unavailable states.

### Out of scope

Patient migration/schema/model renaming; new tenant model, capabilities/grants/permission provisioning; account/portal retirement; imports; booking/arrival/check-in/queue/clinical actions; staff/case assignment; radiologist workspace or authentication changes; AI/report fixes or previews; AI escalation workflow; patient results/links; external SIMRS integration; new service catalog editing; public surfaces; dependency installation; production access/data, external systems, release or deployment.

### Preserved behavior

Preserve accepted Admin Member registration, pending identity/account and optional private evidence rules; existing authorized Member/booking/site/schedule details; legacy Member/Operator portals and real Operator assignment checks; authenticated Grabber and NPZ/MPIPS; private imaging/report boundaries, audit/outbox/idempotency and schedule mutation guards. No synthetic Operator identity or repurposed `operator.active_site_id` session context. Existing task revisions and historical acceptance records stay intact.

## Dependencies and assumptions

### Dependencies

- D1–D4 below are blocking before T5; neither panel permission nor a chosen facility satisfies them.
- Existing domain sources and stable mapping must support bounded Admin aggregate queries without invoking Operator mutation/attendance flows. A narrow owning-module query may be needed; do not introduce a parallel service framework.
- Isolated synthetic SQLite, disposable supported MySQL and local browser environment for required verification. Reconcile concurrent changes to Admin/auth/schedules before validation/execution; PR #8 must not be implemented alongside this board without explicit planning disposition.
- Broader Patient/SIMRS/radiologist/AI/publication work has separate contracts. MVP-GAP-035/036 are separate safety/provenance risks: no new AI exposure here and no closure by board acceptance.

### Approved assumptions

Only the supplied priority/journey and preservation/exclusions are product-approved. Existing panel/resource permissions, services, models and status sets below are source observations. They do not grant new disclosure or choose unresolved policy. All MHCS-managed facilities are the initial central operating model subject to current exact authorization, not an invented tenant permission system.

### Remaining approval requirements

| Decision | Proposed bounded rule requiring disposition | Owner / deadline |
|---|---|---|
| D1 — Managed-facility and data scope | Use authorized persisted Member examination-site references for selection under `member.schedule.read`; include active/inactive managed facilities with explicit state for historical viewing; Operator resource links keep independent site read/manage checks. Existing Admin capability-wide scope is observed, not an invented organization assignment. Confirm these references define MHCS-managed facilities and cross-facility visibility is permitted for each capability; if restrictions are required, replan before adding them | Product, Member/Operator technical/data and security/privacy; P1/A3 before T5 |
| D2 — Operational date | Use selected facility's valid synchronized IANA timezone, strict date and local start-date membership as specified below, including overnight and inactive facilities. No browser/app/server timezone fallback | Product and technical/data owners; P1 before T5 |
| D3 — Aggregates/disclosure | Approve booking-status counts, existing occupied-capacity semantics and distinct recorded-arrival count; separate capability gates including capacity; no patient-level content or suppression threshold invented. Approve minimal service label without broad catalogue disclosure. Decide any small-count privacy rule | Product, Member/Operator data and security/privacy; P1/R2 before T5 |
| D4 — Query and read audit | Approve owning-module aggregate interface, minimized per-request read audit, audit-failure fail-closed behavior and fresh authorization on requests. Disposition observed existing schedule count exposure separately; this board must not inherit it or silently broaden unrelated resource changes | Member/Operator technical and security/privacy with independent Planner/Reviewer; A3/D4 before T5 |

After dispositions, independent Planner/Reviewer evaluates the exact revised contract and authority at P1/R2/A3/D4/T5. Publishing this Draft is not self-approval. Product Owner direction does not substitute for technical/clinical/privacy acceptance. Future implementation commit/push/PR authority must be separately explicit; this planning authorization does not carry over. G10 remains separate.

## Required capabilities

For eventual validated execution: repository read/bounded source and test write, local PHP/Filament/Livewire testing, isolated synthetic SQLite/MySQL, synthetic local browser, diff and payload inspection. Available intelligence tools are aids with direct-source verification. No production database, external AI PACS, Google Drive write, or real-patient capability required.

## Execution constraints

All detailed rules in this section are **Draft proposals** pending D1–D4; do not implement them as approved defaults.

### Reuse and observed implementation boundaries

Source inspected at the immutable baseline:

- `app/Providers/Filament/AdminPanelProvider.php`, `app/Shared/Authorization/AdminPanelAccessService.php`: current `/admin/login`, declared Member/Operator resources; active/login-enabled administrator, no mandatory password change and exact configured panel permission. Source tests confirm `/admin` currently redirects to Members; no board is present in this inspected provider.
- `MemberAuthorization` / `OperatorAuthorization` and persisted claim/context providers: retain exact fresh checks and unavailable-store fail-closed behavior. Panel entry alone grants no resource access.
- `ShiftScheduleResource`, `Mvp03ScheduleService`, `Mvp03BookingService::capacityStatuses()`: Member owns schedules and capacity rules; create/edit already enforce explicit-offset UTC intervals, future/non-overlap/quota validation, booked-field immutability and closure behavior. Reuse these unchanged mutation paths.
- `OperatorSiteResource`, `OperatorSiteService`, `Mvp04OperatorSiteReferenceService` and `OperatorSiteReferenceSynchronizer`: Operator owns physical facility; Member owns synchronized examination-site reference. Stable `operator_site_id` is not either table's local primary key.
- `BookingResource` read and separately gated audit; `OperatorArrivalResource` read-only via `operator.audit.read`. The latter's `safeArrivalSummary` exposes Member detail through an attendance contract; a numeric board summary must not invoke/load that detail.
- `AuditStore` / `AuditEvent`: reuse sanitized attributable append-only evidence; no new logging framework or domain success events for a read.

**Observed discrepancy:** `ShiftScheduleResource` currently queries occupied booking count under schedule-read without a separate booking-read gate. Existing source is not permission-policy approval. Proposed board requires booking-read for occupied capacity as well as booking summaries; existing resource remediation is outside this objective unless independently and explicitly dispositioned before validation.

### Exact authorization boundary

| Board surface / navigation | Required existing authorization, in addition to current panel access |
|---|---|
| Minimal facility selector, schedules, service label, times/status/quota | `MemberAuthorization::scheduleRead()` / `member.schedule.read`; minimal reference metadata only, not address/organization/private site detail or broad service catalogue |
| Occupied/remaining capacity and booking status/total summary | Schedule-read **and** `MemberAuthorization::bookingRead()` / `member.booking.read`; neither manage nor panel access substitutes |
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

- **Quota:** persisted schedule quota. **Occupied capacity:** count each Member booking row once for `shift_schedule_id` using exactly `Mvp03BookingService::capacityStatuses()` — observed `pending_payment`, `confirmed`, `arrived`, `checked_in`, `in_progress`, `postponed`. `completed`, `cancelled`, `no_show` do not consume capacity in this current method. This is reserved-capacity accounting, not number of examined patients or clinical completion. Do not change the status set or imitate it in another scheduler.
- **Remaining capacity:** `max(quota - occupied, 0)` when authorized counts are available; show an explicit over-capacity warning if occupied exceeds quota. Never clamp occupied itself or imply a closed schedule accepts bookings. Both figures require booking-read; otherwise show unauthorized/unavailable rather than deriving zero from missing data.
- **Booking summary:** one count per current known `BookingStatus` and a total count across those booking rows for each session, independent of the capacity subset. No point/funding/identity details. Count booking IDs once before joins; do not sum multiplied relation rows. Unknown status or invalid data makes affected summary unavailable and is escalated, not silently categorized as normal/zero. A verified no-row query produces zero, unlike denial/error.
- **Arrival summary:** count distinct non-null `booking_id` among Operator arrival rows with `status = recorded`, matching `member_schedule_id` to displayed session and `operator_site_id` to the verified Operator **local primary key**. Resolve Member reference's stable `operator_site_id` to `OperatorSite.operator_site_id`, then its local ID; never equate stable/local IDs. Count a recorded arrival even after booking advances to checked-in/in-progress/completed; do not infer attendance from booking status or queue state. Replay/duplicate rows for one booking count once, irrespective of multiple operators. No Member join needed for the summary; it is not clinical throughput. Mismatched/orphan/unknown-state source data must not be attributed to another facility or silently certify completeness: mark affected aggregate unavailable and report a safe integrity condition.
- Show per-section last successful retrieval time (UTC instant, rendered facility-local) and source/state; refresh after return. Counts are committed read-time observations, may change after refresh, do not reserve capacity or certify a cross-domain transactional snapshot. Query schedule/booking together with a consistent bounded read as supported by the current database; avoid join fan-out and per-row detail loading. Arrival read is independently authorized; no new write locks, schema, reconciliation worker or realtime polling required.

### Disclosure and read audit

Board contains only permitted facility label/state/timezone, schedule reference, minimal service label, dates/times/status/quota and authorized aggregate values. No patient names, NIK, MRN, photos, booking/arrival identities, clinical values, DICOM/PDF URLs, object keys, raw authorization claims, tokens or exceptions in HTML, Livewire state, exports or logs. Existing authorized details remain separate. Pending small-count/disclosure policy is D3; no privacy approval inferred from numeric-only display.

Proposed D4: append attributable board read audit for each data-bearing initial/filter/refresh request, with server-derived actor/session/correlation, permitted facility reference, selected date/timezone, read sections and outcome. Use sanctioned opaque references and sanitized reason codes; do not log counts, patient identifiers or denied facility metadata. Audit append failure withholds affected data and reports unavailable; do not manufacture successful read or mutation events. Optional denied/unavailable sections have truthful outcomes; missing trustworthy actor/claim context fails closed. Reuse existing audit schema and sanitizer; define exact event source/action/metadata with owning authorities before T5. Read audit is the only proposed persistent board side effect, distinct from operational mutations.

## Acceptance criteria

All are proposed for approval with D1–D4; boxes remain unchecked until independently reviewed implementation evidence exists.

- [ ] **AC1 — Usable entry and selection:** Eligible Admin uses `/admin/login` → `/admin`, selects managed facility/date and sees persisted service, start/end/status/quota/session reference. No selection, no schedules and ineligible account cases are distinct and safe; existing authorized directories/registration remain reachable.
- [ ] **AC2 — Facility-local dates:** Strict validation, local today, UTC half-open membership, two facilities in different zones, UTC/local midnight, next-day exclusion, overnight start-day membership, DST 23/25-hour dates/offsets and invalid/mismatched zones meet the approved rule. Forged/stale site/date inputs fail safely; inactive/closed/history views meet D1/D2.
- [ ] **AC3 — Booking/capacity:** Approved total/by-status and occupied/remaining semantics match synthetic persisted bookings for every status, zero and over-quota cases, with no join multiplication. Schedule-read alone never exposes booking-derived values, even through Livewire state. Unknown/source-failed counts are unavailable, not zero. No booking writes.
- [ ] **AC4 — Arrivals:** Arrival-audit permission alone, combined with board schedule-read, permits only the approved numeric summary; booking-read does not grant arrival access. Distinct replay/duplicate booking handling, progressed bookings, stable/local site-ID separation, cross-facility/mismatched/orphan data and unavailable sources behave as approved. No patient summaries or arrival/check-in/queue writes.
- [ ] **AC5 — Existing workflows and fresh state:** Authorized facility/schedule creation/edit navigation delegates to existing resources/services; synthetic save → return shows persisted changes and revalidated filters. Read-only/Admin-without-manage actors cannot mutate. Registration and authorized detail pages preserve their accepted behavior; booked schedule immutability and independent service authorization remain enforced.
- [ ] **AC6 — Fresh exact authorization and minimized disclosure:** Test each capability independently, mixed grants, missing/inactive/wildcard claims, unavailable claim store, non-Admin, suspended/pending/login-disabled/password-change accounts, forged actor/site/role/permission state, direct-route access and post-mount revocation. No unauthorized query/payload/cached values; no synthetic Operator/assignment or broader grant. Accessible localized labels, focus and independent denied/unavailable states work without exposing private data.
- [ ] **AC7 — Auditable read-only integrity:** Approved attributable read/outcome events exist; audit failure withholds data. Opening/filtering/refreshing board changes only approved read audit; booking/arrival/queue/clinical/identity/permission, mutation-audit and outbox rows remain unchanged. No PHI/counts/secret payloads in logs/audit. Refresh reports truthful read-time evidence, not guaranteed realtime state.
- [ ] **AC8 — Regression and review boundaries:** Accepted Admin registration, legacy portals, existing Admin resource/service and Operator assignment/arrival/check-in/queue flows pass scoped and full regressions; isolated MySQL and synthetic browser prove date/count/no-write and save-return behavior. No application change beyond the approved board boundary, schema/runtime-config/seeder/dependency/grant/clinical change or production access. Independent Reviewer assesses exact immutable implementation against the exact validated contract; no self-acceptance/release.

## Verification requirements

### Required checks after validation and implementation

1. Meaningful request/Livewire tests for AC1–AC7 using existing Admin synthetic fixtures. Verify protected queries are not executed for missing capabilities and inspect full HTML/Livewire/error payloads; hidden controls alone do not prove authorization.
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

### Observed planning-cycle checks

At unchanged integrated baseline, the first three-file Admin regression command above actually passed **27 tests, 246 assertions** with synthetic SQLite testing fixtures. This confirms only existing tested Admin boundaries, not this unimplemented board. Source/claim/resource/date/status inspection and immutable registration diff support the Draft. No full suite, MySQL, browser, external AI or production check was run in this planning cycle. Graphify's existing graph was built at `c7c237dfdd9785b127ca3228f5bf527cfe8ca552`, so documentation findings were directly verified; Codebase Memory coverage for cited primary code reported no recorded gap/metadata match, with direct-source confirmation rather than completeness claims.

Documentation verification also passed: exact nine-document allowlist, 87 local links/anchors, task-template/Draft/eight-AC/four-decision structure, registered requirement IDs, byte-preserved historical sections and original matrix, untouched code/acceptance evidence, high-confidence added-text secret/private-ID pattern scan, and `git diff --check`. These are planning checks, not independent approval or application certification.

## Stop conditions

Stop and return to Planner/Reviewer if task remains Draft, D1–D4/independent review are unresolved, exact governing task/baseline is unavailable or materially changed, required facility/timezone mapping or approval cannot be established, authorization requires new grants/tenant scope/synthetic Operator, aggregates need patient detail or clinical/publication access, schema/config/permission/portal changes become necessary, existing resource discrepancy requires broader remediation, read-audit policy/sanitizer cannot support approved disclosure, verification environment is unsafe/unavailable, or a materially new privacy/security/data/clinical risk changes scope. Do not choose policy defaults, silently weaken required evidence or implement separate AI repairs.

## Side-effect authorization

**Current cycle:** Human explicitly authorizes one planning branch from verified main, bounded planning/product/requirement/context documents, commit, ordinary push to that new branch and one Draft PR. No application/test/migration/runtime-config/seeder changes or Executor launcher. PR #8 remains untouched. No merge/deploy/release, production/real-patient access, permission provisioning or external Google business-document changes.

**Eventual implementation:** None granted by this Draft. Before validation/handoff, obtain explicit bounded source/test and isolated-verification authority and disposition any commit/push/PR/infrastructure needs. No future side effect is inferred from current planning authorization.

## Expected terminal outcome

Current Planner cycle ends with **Draft documentation PR / independent review and approval required**. No implementation execution, T5 pass or Executor launcher.

After future validation, Executor ends **Review Required** with actual evidence or **Planning Required** with verified stop evidence; Executor does not self-accept. Independent implementation review and immutable A9 recording follow separately; release requires G10.

## Review and remediation handling

Independent Reviewer inspects the exact Draft publication against verified main, supplied PO direction, unchanged PR #8 candidate, immutable registration acceptance, source reuse and D1–D4. Approvers record attributable scoped dispositions before independent P1/R2/A3/D4/T5 validation. Revise this same stable path for bounded board-contract corrections and publish a new exact immutable contract only after gates pass. Do not relabel this Draft as Validated because it is committed or the PR exists. Materially distinct objectives return to Delivery Planning; preserve task and execution identities.

## Execution evidence

No board implementation or self-certified acceptance exists. Future evidence is reported outside this planning contract, preserving exact governing task, baseline, implementation revision, checks, independent verdict and any remediation/accepted baseline. Separate AI findings are tracked in MVP-GAP-035/036, not claimed resolved by this task.
