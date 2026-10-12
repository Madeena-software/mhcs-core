# Admin-first operational MVP roadmap

## Admin-first planning sequence

The approved priority is the [Admin-first operational scope](beta-scope.md#admin-first-operational-scope), not the old MVP-01→MVP-09 date sequence. [MVP-DEC-044](decision-log.md#admin-first-priority-decision) records that planning supersession while preserving milestone IDs, accepted foundations, domain ownership, and historical evidence. This is a dependency map, not an executable application task or deadline commitment.

| Planning outcome | Reuse / traceability | Prerequisite and next gate |
|---|---|---|
| First functional slice: Admin Facility-Session Operations Board | MVP-DEC-047/048–050/052/053; existing site/schedule services, accepted registration; WP-06/10/11/17 | [Draft task](../../.agents/tasks/admin-facility-session-operations.md); D1–D3/X1 A and internal D4-A–I approved; independent T5 review and future execution permissions required; retention/access/external obligations production dependencies; MVP-GAP-027 stays open. Preserve portals |
| Canonical Patient and controlled legacy identity/portal migration | MVP-DEC-045/046; WP-04 identity, MRN/NIK and compatibility obligations | MVP-DEC-051 complete identity target: no permanent account requirement, facility-scoped MRNs, optional initial NIK/provisional identity, reviewed ambiguous matches and preserved history/audit; separate migration/transition contract; no identity or permission changes in board |
| External SIMRS / Teleradiology-only mode | MVP-DEC-045; existing Grabber/DICOM boundary | Separate approved external identity/correlation, data authority, authentication and privacy contract; no external integration in the board |
| Centralized study intake/worklist and processing visibility | Authenticated direct DICOM and legacy NPZ/MPIPS contracts; FIELD-OPS-007/008/009; INT-DUAL-001/INT-ASYNC-001 | Reuse owning-module boundaries; distinguish processing state from clinical output; MVP-GAP-027/029 |
| CSV/XLSX templates and bulk registration | MEM-214/MEM-228, US-B2B-001/002; historical MVP-08/Prestige are limited references | Approve schema, identity matching, invalid-row/conflict handling, transaction and privacy rules; MVP-GAP-032 |
| Temporary staff/clinical workspaces | US-STAFF-SHARED-002, US-DOCTOR-SHARED-002/004; Doctor Work Packages remain reference obligations | Approve credentials, assignments, permitted actions, authentication/session lifecycle and clinical accountability; MVP-GAP-028/029 |
| Authorized finalized Member results | US-MEMBER-003/009/019 and US-DOCTOR-RAD-003/007/008 | Approved finalization/amendment and recipient/guardian rules precede release; approve link expiry/revocation/disclosure/recovery; MVP-GAP-029 |
| Real operational readiness | Existing security/privacy, consent, storage and bounded deployment evidence | Close applicable clinical/security/privacy/data/environment gates with current evidence; MVP-GAP-030/031/034 and existing release gaps; separate G10 |

Registration and centralized worklist planning may advance only within their approved boundaries; import, workspace, result-delivery, and release implementations require their own validated contracts after relevant decisions. No dependency is satisfied merely by this documentation update. Preserve existing paths, integration behavior, audit, and account data while planning transitions.

The prior Admin-first documentation implementation was independently accepted through PR #5 and merged as `8bb0c21e87e6f9e2c32a01ccab83aed37f1e91b7`; this does not accept application behavior. The lean-docs cleanup governed by task publication `09b0f462fe94976394bb1357077e1a185895a3f2` is merged through PR #6 at `fb25354311121fc8163256cd0530d9cbcb66bb68`; the PR records independent acceptance. No application milestone is newly accepted or completed. Keep the [three implementation ledgers](README.md#maintained-planning-and-authority) as historical authority/reference rather than rewriting their extensive assignments.

### Current baseline and review sequence — 2026-10-13

Integrated local/remote main is `fc37002d106b839c0b881e7dec7021c91389f501` (PR #7 merge). The bounded registration accepted application revision is `21d279831455d32fe2a707e8d306af861c0bbb08`, governed by `.agents/tasks/admin-first-manual-member-registration.md @ e7ccc686da049e78d7634125237fc794b30a0908`; [acceptance limitations](evidence/admin-first-manual-member-registration-acceptance.md) persist. Integration changes only the evidence/context pointers relative to that accepted application code.

PR #8 remains Draft at `bc9d25724319de068d354c39fa3fbd4b9d0140c2`; recommend supersession without changing it. [MVP-DEC-053](decision-log.md#final-internal-d4-approval--2026-10-13) records attributable Product Owner approval of D4-A–I with explicitly confirmed internal product/technical/security/privacy-policy authority. Domain-owned reads, internal arrival-association validation, fresh exact authorization, trusted managed-facility scope, exact internal operational counts including one, minimized attributable audit/failure withholding and narrow X1 A are internally approved. Independent T5 contract review and explicit future source/test/isolated-verification permissions remain outstanding; the task stays Draft. Audit retention/access operationalization has no verified applicable policy and remains a production/real-data/release dependency; independent review must confirm synthetic-development separation. This is not external legal, hospital/clinical, real-data or deployment approval, independent acceptance or completed implementation tests. The [exact-revision review package](../../.agents/tasks/admin-facility-session-operations.md#independent-exact-revision-t5-review-package) pins baseline and authority, maps AC1–AC8 and resolves source limitations through internal validation and denied unverifiable claims. PENDING INDEPENDENT T5 CONTRACT REVIEW; no launcher.

Patient migration, external SIMRS, radiologist workspace, AI/reporting and patient publication remain independent dependent work. Individual radiologist authentication refines the earlier temporary-workspace target; shared credentials are not approved. Clinical and privacy/security decisions precede exposure. [AI fallback risks](beta-gap-register.md#ai-report-safety-and-provenance-follow-up) require separate Image Gateway/clinical/provenance planning before affected reporting/publication claims; they are outside board implementation and remain visible.

## Historical controlled-beta roadmap — retained

The remainder is the August 2026 roadmap and addenda, including their contemporaneous status, future tense, deadlines, and direct-main/workstream instructions. They retain their evidence meaning and IDs but do not select the current next task. The Admin-first planning sequence above supersedes the old active ordering only; no merge, deployment, clinical scope, or release permission follows from historical prose.

This is the active bounded-MVP roadmap, not a global linear task queue or a
date plan. MVP-02 through MVP-09 remain provisional and may be reprioritized by
owner decision. Dependencies and evidence gates remain binding. Current
delivery proceeds sequentially on `mhcs-core` `main`; the historical
parallel-workstream model is retained only where explicitly labelled.

## CURRENT PLANNING SNAPSHOT — reconciled 2026-08-21

| MVP | Current status |
|---|---|
| MVP-01 | Bounded implementation accepted. |
| MVP-02 | Bounded implementation accepted. |
| MVP-03 | Bounded implementation accepted. |
| MVP-04 | Advanced partial implementation: the clinic-day Operator journey is substantially implemented; broader long-term Operator requirements remain open. |
| MVP-05 | Substantial Image Gateway/MPIPS implementation exists; formal milestone/status reconciliation remains incomplete. |
| MVP-06 | Open: report and external-teleradiology workflow is not complete. |
| MVP-07 | Open: Member result publication is not complete. |
| MVP-08 | Generic B2B capability remains incomplete; Prestige is a bounded operational exception, not generic WP-10 completion. |
| MVP-09 | Partial: bounded production deployment and verification exist; complete security/privacy/release/conformance remains open. |

The next planning priority is to finish the bounded MVP-04/MVP-05 status
reconciliation and then select the next evidence-ready MVP-06 or MVP-07 slice;
generic B2B completion and final conformance remain separate future work.

## Delivery ownership

| Milestone | Delivery ownership |
|---|---|
| MVP-04 | `mhcs-core` `main` — Operator |
| MVP-05 | `mhcs-core` `main` — Image Gateway |
| MVP-06 | `mhcs-core` `main` — Operator and Image Gateway integration |
| MVP-07 | `mhcs-core` `main` — Member and Image Gateway publication boundary |
| MVP-08 | `mhcs-core` `main` — bounded B2B capability |
| MVP-09 | `mhcs-core` `main` — integrated verification and deployment readiness |

## Historical contract-first cross-workstream model — superseded

The following records the former delivery model and is retained for traceability
only. MVP-DEC-031 superseded it on 2026-08-11; current delivery is sequential
on `mhcs-core` `main`.

1. The Image Gateway workstream owns and versions each Gateway contract and
   its shared fixtures.
2. An approved contract-only change may merge into `main` before the complete
   Gateway implementation; the main workstream must not consume an unmerged
   contract or implement Gateway internals.
3. Main-workstream consumers and Gateway-owned providers may then be developed
   independently against the same merged contract and fixtures.
4. Final beta completion still requires the complete Image Gateway branch to
   merge and the integrated workflow gate to pass.

## MVP-00 — Pivot to Controlled Beta Delivery

Documentation and planning only. Establish the relationship between Work Packages and MVP tasks, the shared administrator interface and module-owned administration boundary, the Doctor Portal exclusion, the gap register, decision log, and status ledger.

## MVP-01 — Member Access and Profile

First implementation target:

\`\`\`text
Member login
→ mandatory password replacement
→ profile completion
→ dashboard
→ logout
\`\`\`

Deliver focused ownership and authentication tests. Exclude account import, public/online registration, child/guardian workflows, and unrelated clinical or financial behavior.

Consumes the accepted WP-01 shared application foundation, WP-02 security/authentication foundations, and WP-04 User/Member, protected-identifier, audit, and ownership foundations. It must not reopen WP-04 wholesale.

Implementation evidence is recorded in `docs/mvp/evidence/mvp-01-member-access-and-profile.md`. The bounded Member login, mandatory password replacement, profile, dashboard, logout, and local/testing synthetic-account slice is implemented and focused-tested.

## MVP-02 — Shared Admin Shell and Member Administration Foundation

Deliver only:

- the shared administrator-facing shell or navigation needed for MVP;
- Member module administration needed to manage controlled Member accounts;
- approved account-state actions through existing application boundaries;
- Member-owned administrative views required for the initial beta; and
- foundational audit visibility relevant to that slice.

MVP-02 must not claim ownership of all Operator and Image Gateway
administration or create a generic cross-module database editor.

The bounded shared shell, persistent administrator claims, Member account
administration, and relevant audit visibility are implemented and focused-tested.
The published admin-enforcement remediation was completed against the recorded
baseline; query authorization, execution-time account-action checks, and safe
bootstrap-claim reconciliation are covered by the evidence below. Evidence:
`docs/mvp/evidence/mvp-02-shared-admin-shell-member-administration.md`.
The focused evidence-closure task ran from baseline
`03ba160f2080a6924ae64402e48be990cc9c7ffd` at execution commit
`f7a3eaeb54b97642bd61d545ebcbf5e26f69f93c`; its 32 focused tests and 283
assertions passed. This remains a bounded MVP-02 evidence claim, not a
production-readiness claim.
The bounded MVP-03 controlled adult B2C radiology-booking slice is implemented
and focused-tested. It uses local/testing synthetic personal-point funding,
Member-owned catalogue and schedule administration, read-only site references,
atomic booking/order creation, and safe Member booking status. Evidence:
`docs/mvp/evidence/mvp-03-controlled-b2c-radiology-booking.md`.

## MVP-03 — Member Radiology Service Request

The controlled first slice is implemented: an eligible adult Member can browse
active services and sites, select an open future schedule, spend only local/test
personal Madeena Points, create one confirmed B2C booking and local imaging
order atomically, and view the safe booking status. Real payment, B2B,
cancellation/refund, Operator, Image Gateway, FHIR, and production behavior
remain later work.

The booking-ownership and schedule-integrity remediation was executed from
baseline `a1360f4307d7d339779a48fd519755b360f52052` in the working tree. The
corrected evidence binds booking ownership to the trusted authenticated Member
context, freezes booked schedule site/service/time/quota fields except for
close or no-op updates, compares signed four-decimal point amounts without
numeric coercion, restricts booking audit queries, records controlled failure
categories, and verifies Filament service-bound mutations with execution-time
authorization. MVP-04 is now started through the bounded Operator foundation
and arrival slice below; it remains incomplete.

The follow-up admin, audit, and browser closure task was executed from baseline
`5dee2a1db3595d321c5a4a339d2d6f387111fc64` with `TARGET="."`. It adds the
bounded Member-owned offering and schedule create/edit action surface, exact
audit association and actor-state regression coverage, and Pest 4 browser
coverage. Its focused MVP-03 suite passed with 21 tests and 257 assertions;
the Chromium browser suite passed with 3 tests and 38 assertions. This remains
bounded evidence, not a claim of production readiness or completion of MVP-04.

## MVP-04 — Operator Queue and Attendance

Started with the bounded Operator foundation and arrival slice. The current
slice implements shared Operator access, Operator-owned sites and profiles,
site assignments and active-site context, eligible-shift intake, manual shift
assignment, site-scoped attendance, physical arrival recording, the Member
`confirmed` to `arrived` transition, a verification worklist, bounded portal
routes, bounded front-desk identity verification, and Operator-owned
administration, Member-owned `arrived` to `checked_in` transition after
confirmed consent, and one private Operator paper-ticket issue/reprint slice.
MVP-04 remains incomplete: queue stages, clinical workflow, and remaining
queue/attendance behavior are deferred.

Pest/Playwright browser-platform work is deferred to post-MVP hardening.

### MVP-04A remediation addendum — 2026-08-05

The published Operator foundation and arrival remediation is committed at
`2e08eae74e49b0ba54461ba8787a0ec8e0ece062`. The follow-up closure task uses
that SHA as its baseline. The committed closure candidate is
`f49da5991b21b9a13abb435539db1955362ef639`; the evidence-verification task
itself made only uncommitted documentation changes in the working tree and did
not create an execution commit. It centralizes confirmation lifecycle
validation, blocks only an active unconsumed confirmation during site
switching, makes recorded arrivals non-blocking worklist entries, proves
local/stable Operator site correspondence through an Operator-owned resolver,
and removes the public unconfirmed arrival mutation. Evidence:
`docs/mvp/evidence/mvp-04-operator-foundation-arrival.md`.
MVP-04 remains incomplete and its queue, check-in, ticketing, consent,
clinical, and broader attendance work remains deferred. The bounded MVP-04B
front-desk identity-verification slice is recorded below.

### MVP-04B front-desk identity verification — 2026-08-06

From baseline `cecbf8e5e6d944cf58a7b73c2db14177f1748b5f`, the bounded
front-desk flow supports an assigned Operator claiming an arrived worklist
entry, exact protected NIK lookup, current KTP/KIA and latest approved photo
views, explicit prior-photo fallback, terminal human decisions, protected
inline asset retrieval, and an open-case site-switch blocker. Member retains
NIK and asset ownership; Operator retains case and decision ownership. Consent,
check-in, tickets, queues, clinical behavior, and production readiness remain
deferred. The remediation additionally binds every Member-side operation to a
fresh trusted open case, fails closed on missing current evidence, denies
historical identity documents and unrelated-member assets, revalidates evidence
before `matched`, uses a database-backed one-open-case claim, and keeps
free-text reasons out of shared audit. Evidence:
`docs/mvp/evidence/mvp-04b-front-desk-identity-verification.md`.

## MVP-05 — Image Gateway Study Intake and Correlation

Deliver the study intake or identification boundary, examination-to-study
association, duplicate/mismatch handling, Image Gateway status visibility, and
Operator-owned and Image Gateway-owned operational failure visibility. Include
the Image Gateway operational administration required for this slice: intake
status, correlation failures, retry visibility, and terminal failures.

This is Image Gateway work on `mhcs-core` `main`. The module and its workers
remain the owning implementation boundary; formal milestone reconciliation and
operational administration are still incomplete.

## MVP-06 — Operator Teleradiology Workflow

Deliver study routing/export status, external teleradiology tracking,
retry/failure handling, controlled manual report upload as the beta fallback,
automated report return only when a supported contract exists, and Operator
review/publication controls. MVP-06 may extend Operator and Image Gateway
administration only within their existing module ownership.

Operator behavior, Gateway routing/processing, and the remaining publication
boundary are now delivered sequentially on `mhcs-core` `main`. External
teleradiology remains an external participant and the supported report path is
not complete.

## MVP-07 — Member Result Visibility

Deliver access to the Member's own published result, strict ownership, safe presentation, publication state, and viewing/download only through approved private-object boundaries where applicable.

Member presentation and the Image Gateway publication boundary remain separate
module responsibilities within `mhcs-core` `main`. Member result publication
remains open until its approved behavior and evidence are complete.

## MVP-08 — B2B Account Import

Deliver separately controlled Member and Operator import; validation/rejection reports; idempotency; duplicate detection; temporary credentials; mandatory password replacement; and audit/secret-handling controls. Do not implement before MVP-01 unless the owner reprioritizes it.

## MVP-09 — Beta Hardening and Deployment Readiness

Deliver merged cross-MVP regression and integration verification, operational
runbook, beta monitoring, backup/restore evidence, migration approval
resolution, critical gap review, and the controlled beta deployment decision.
The bounded deployment and verification evidence now exist, but this milestone
remains partial until the remaining production, security, privacy, release, and
conformance gates are complete. MVP completion and deployment readiness do not
establish production readiness.

No roadmap task creates a generic cross-module database editor. Shared
administrator navigation is presentation; module-owned resources and business
rules remain behind their approved application boundaries.

## MVP-04B final boundary closure addendum — 2026-08-06

The final boundary task keeps MVP-04B bounded while closing the remaining
evidence-unavailable and protected-grant gaps. Missing current evidence now
renders only a safe arrival summary with failure/cancel actions; matched and
protected asset retrieval remain fail-closed. The shared grant and trusted
case resolver revalidate the authenticatable account, portal/identity
permissions, exact case/booking Member, and allowed asset slot. Evidence:
`docs/mvp/evidence/mvp-04b-front-desk-identity-verification.md`.

MVP-04, consent, check-in, ticketing, queues, clinical behavior, deployment,
and production readiness remain open or partial.

## MVP-04C paper consent confirmation — 2026-08-07

The bounded MVP-04C slice adds one Member-owned `Informed Consent` / `V1`
confirmation for an arrived booking after a terminal matched identity case.
Only the currently assigned Operator at the trusted active site may invoke the
flow. The existing idempotency, audit, outbox, transaction, and encrypted
private-object boundaries are reused; an optional validated JPEG, PNG, or PDF
scan is stored privately and is not retrievable by this slice. The booking
remains `arrived`. Evidence:
`docs/mvp/evidence/mvp-04c-paper-consent-confirmation.md`.

This does not implement general consent administration, electronic signatures,
representative or guardian consent, correction, retrieval, retention or
deletion, check-in, ticketing, queues, examination, clinical behavior, or
Member-visible consent. MVP-04 and the broader consent/clinical/queue gaps
remain open or partial.

### MVP-04D verified check-in and paper ticket issue — 2026-08-07

From accepted baseline `8a5c764f8bec97d6ca897bfcf079dc6bde225053`, the bounded
MVP-04D slice lets the currently assigned Operator enter the existing on-site
paper number and atomically consume a terminal matched identity case plus
Member-owned `Informed Consent` / `V1`. Member owns the `arrived` to
`checked_in` transition and status history; Operator owns one site-and-shift
ticket, idempotent issue, authenticated print, and auditable manual reprint
request. The print surface contains only site, shift/date/time, and ticket
number. Evidence: `docs/mvp/evidence/mvp-04d-verified-check-in-ticket-issue.md`.

Ticket generation, queue stages, claims, calls, LCD/public exposure, clinical
workflow, Member ticket visibility, consent-scan access, printer integration,
production deployment, and privacy/retention approval remain out of scope.

### MVP-04E advance queue admission — 2026-08-07

The bounded MVP-04E implementation extends the accepted MVP-04D transaction so
one successfully issued advance-booking paper ticket creates one private
Operator queue admission, one initial history record, and matching audit/
outbox evidence for the `basic_examination` / `waiting` stage. An authenticated
assigned Operator can read the active site's private FIFO worklist using only
ticket number, site, shift time, stage, state, and ready time. No queue action,
clinical value, walk-in rule, public display, or Member-visible queue was
added. Source-level checks and Codebase Memory verification passed, but runtime
verification is blocked by the missing Composer vendor tree; MVP-04E is not
claimed closed until the focused suites, Pint, migration, and route checks run
in an owner-controlled dependency-complete environment.

### MVP-04B audit identifier sanitizer remediation addendum — 2026-08-07

The shared audit boundary now distinguishes complete canonical UUIDs from
standalone raw numeric identifiers using the existing UUID validator. The
deterministic WP-02 regression and the bounded MVP-04B, Operator portal, and
architecture suites passed separately; audit append-only behavior and all
MVP-04B audit callers remain unchanged. MVP-04B remains a bounded partial
slice. Validation, static checks, focused test counts, graph refresh evidence,
unrun checks, and residual risks are recorded in the WP-02 and MVP-04B evidence
addenda for this date.

### MVP-04E runtime verification closure attempt — 2026-08-07

The committed closure candidate is
`26576ef89fe1a06ba0d75ba422f4a4efc2a3eaaa`, descended from the accepted
MVP-04D baseline. Task validation, candidate ancestry, worktree scope, and
Codebase Memory traces passed. Runtime closure is blocked because the existing
Composer vendor tree is absent: `vendor/autoload.php`, `vendor/bin/pint`, and
Laravel boot are unavailable. No dependency installation, product change,
commit, or push was performed; the required framework suites and other closure
checks remain unrun.

### MVP-04E denial-matrix remediation — 2026-08-08

The denial-matrix remediation was runtime-verified before commit at
pre-commit HEAD `6e91fe07feb010f92ae2719d55b67ea670ebbb98` and then committed
as candidate `2545c6a56ccb186f35bbdbe76f3598e9c3d5dcc3`. The committed
candidate contains the deterministic test-only MHCS key fallbacks and approved
narrow middleware boundary; the pre-commit SHA is only the verification
context. MVP-04E and all required MVP-04D, MVP-04C, MVP-04B, Operator, WP-02,
and architecture suites passed separately without process-injected MHCS keys.
Revoked shift remains an empty 200 worklist; revoked site, permission, forged
active-site session, and suspended account return 403 without sensitive or
internal detail. The bounded MVP-04E slice is verified but not expanded: queue
actions, clinical examination, walk-ins, public/LCD behavior, Member
visibility, privacy/retention policy, deployment, and production readiness
remain open.

### MVP-04F atomic basic-examination claim — 2026-08-08

The bounded MVP-04F slice adds one private, authenticated Operator claim
action for an eligible advance `basic_examination` / `waiting` admission. The
claim is atomic and idempotent, records the claiming Operator and occurrence
time, appends one `claimed` history event with `waiting` retained, and writes
matching local audit/outbox evidence in the same database boundary. A nullable
unique Operator-profile index prevents a second active claim; competing or
unauthorized requests fail closed, and another Operator cannot see the claimed
row. The private worklist remains FIFO and contains no Member, booking,
consent, identity, clinical, public, or claimant-identity data. No queue stage
or state transition was introduced. Evidence:
`docs/mvp/evidence/mvp-04f-atomic-basic-examination-claim.md`.

MVP-04F does not close WP-11, WP-12, or WP-17. Queue release/call/skip,
clinical examination, later queue states, walk-ins, public/LCD behavior,
Member visibility, privacy/retention approval, deployment, production
readiness, and `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and `MVP-GAP-024`
remain open.

### MVP-04G private basic-examination call — 2026-08-08

The bounded MVP-04G slice adds one private, authenticated Operator Call action
for the active claimant's eligible advance `basic_examination` / `waiting`
admission. The transition is atomic and idempotent, revalidates authorization
and scope, preserves claim and FIFO fields, appends one `called` history event,
and writes matching local audit/outbox evidence. The private worklist contains
only the existing claimant indicator and the smallest Call form; no clinical,
Member, booking, consent, identity, public, LCD, or audio behavior was added.
Evidence: `docs/mvp/evidence/mvp-04g-private-basic-examination-call.md`.

MVP-04G does not close WP-11, WP-12, or WP-17. Clinical examination and later
queue actions/states, walk-ins, public/LCD behavior, Member visibility,
privacy/retention approval, deployment, production readiness, and
`MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and `MVP-GAP-024` remain open.

### MVP-04H private basic-examination start — 2026-08-08

The bounded MVP-04H slice adds one private, authenticated Operator Start action
for the active claimant's eligible advance `basic_examination` / `called`
admission. The transition is atomic and idempotent, revalidates authorization
and scope, preserves claim and FIFO fields, appends one `started` history event,
and writes matching local audit/outbox evidence. The private worklist adds only
the claimant's opaque Start form; no clinical, Encounter, Member, booking,
consent, identity, public, LCD, or audio behavior was added. Evidence:
`docs/mvp/evidence/mvp-04h-private-basic-examination-start.md`.

MVP-04H does not close WP-11, WP-12, or WP-17. Clinical assessment and
Encounter/FHIR creation, later queue actions/states, X-ray workflow, walk-ins,
public/LCD/audio behavior, Member visibility, privacy/retention approval,
deployment, production readiness, and `MVP-GAP-009`, `MVP-GAP-012`,
`MVP-GAP-021`, and `MVP-GAP-024` remain open.

## Historical 12 August MVP delivery target — Operator priority — approved scope, not yet executed

This section preserves the target and sequencing language recorded at the time;
MVP-DEC-031 superseded its separate Image Gateway branch instructions.

All active MVP work targets the 12 August controlled-deployment candidate. The
next coherent delivery objective on this branch is the Operator site flow: it
covers the Member and Operator core behavior on `main` through X-ray readiness.
Image Gateway, AI, and MPIPS remain separately owned and do not block this
objective. The target is not by itself deployment, real-data import, or release
approval.

Delivery must be sequenced as follows:

1. Verify the current main baseline and preserve the separate Image Gateway
   contract boundary.
2. Complete and verify the dedicated Operator entry and workstation: site
   selection, attendance, identity and paper-consent gate, ticket issue/print,
   station-scoped queue calls, Printer Station, LCD Station, approved limited
   assessment, private paper questionnaire, and X-ray readiness.
3. Run focused synthetic verification and the bounded Browser proof, then make
   the separate release decision before any deployment or real-data use.

The Operator-priority task excludes B2B import, credential delivery, Member profile
completion, Gateway capture intake, NPZ/gain validation, MinIO storage, DICOM
conversion, doctor/AI/result workflows, walk-ins, cash and payments, points,
operator payouts, automated staffing, dynamic booking quotas, self-service
rescheduling, repeat examinations, and glucose, cholesterol, and uric-acid
screening. These exclusions remain tracked in the gap register.

### Consolidated Planner/Reviewer instruction — single workstream (MVP-DEC-031)

**MVP-DEC-020 is superseded.** Per Faliq Adlan, CTO (2026-08-11), all remaining
MVP delivery — including Image Gateway, AI, and MPIPS integration — proceeds
sequentially on `main`. There is no separate Image Gateway feature branch.

```text
Read and follow `.agents/prompts/plan-create-task.md`.

Assess the current repository delivery state, resolve any pending execution,
review, remediation, approval, or baseline state first, and then perform the
next legitimate Planner/Reviewer action for the 12 August MVP target on `main`.

Priority order:
1. Close all pending verified tasks (clinic-flow remediation, workstation UI).
2. Continue Operator core through X-ray readiness if not yet complete.
3. Implement Image Gateway, MinIO storage, NPZ/gain validation, MPIPS/AI
   integration, retry handling, and administrator-visible terminal failures
   directly on `main`, respecting the approved module boundaries in project.md.
4. Run the integrated clinic-day rehearsal on `main` before the real B2B import.

Do not create a separate Image Gateway branch. All delivery and release gates
remain required. This is not deployment, real-data import, or release approval.
```
