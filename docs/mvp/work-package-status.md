# Work Package status ledger

Historical MVP-02 evidence-closure note: baseline
03ba160f2080a6924ae64402e48be990cc9c7ffd was confirmed as an ancestor of
execution commit f7a3eaeb54b97642bd61d545ebcbf5e26f69f93c; 32 focused tests
and 283 assertions passed. WP-10 remains partially implemented because broad
Member administration and B2B import are out of scope.

Statuses are evidence-based and describe the current foundation, not complete long-term conformance. Requirement assignments below reproduce the current implementation plan; this ledger does not alter them. \`accepted-foundation\` means the repository contains the consumed, tested foundation and its accepted baseline language, not that every assigned requirement is complete.

Reconciled against `841465faac4e8b1dd3670103052a9c4f075bfd04` on 2026-08-21.
All remaining MVP delivery proceeds sequentially on `mhcs-core` `main`.
MPIPS remains a separate private processing service and the only internal
network service boundary. Requirement assignments and module ownership remain
unchanged.

| Work Package | Title | Requirement assignment | Repository evidence | MVP relevance | Status | Deferred items | Notes |
|---|---|---|---|---|---|---|---|
| WP-01 | Application architecture foundation | ARCH-001..ARCH-002, ARCH-008..ARCH-018, ARCH-037..ARCH-040, ARCH-046 | \`app/Shared/\`, four module providers, \`config/mhcs.php\`, foundation tests, Composer constraints, WP-01 history, and the MVP-02 shared panel provider | Shared contracts, context, events, outbox, idempotency, topology, and persistent panel-claim foundation consumed by MVP-01 onward | accepted-foundation | All product behavior, UI, external adapters, and later module requirements | Foundation is not full product conformance. |
| WP-02 | Security, privacy, audit, and operational hardening | ARCH-028..ARCH-036, ARCH-041, ARCH-043, ARCH-045, MEM-108..MEM-119 | \`app/Shared/Security/\`, audit/logging/storage/authorization boundaries, persistent claim resolver, panel access gates, Image Gateway security policy, deployment files, \`docs/security/wp-02-security-evidence.md\`, \`docs/mvp/evidence/production-swarm-deployment.md\`, \`docs/mvp/evidence/prestige-production-rehearsal-data.md\`, and focused tests | Mandatory boundary for every exposed MVP flow | accepted-foundation | Production security/privacy certification, storage/retention policy, exact MPIPS policy, unresolved credential exposure, and legal/privacy decisions | Current deployment mechanisms and observed deployment/verification evidence exist, and Image Gateway/MPIPS implementation has advanced. This remains a foundation status, not complete Work Package conformance or production security/privacy certification. |
| WP-04 | Member identity, accounts, guardians, and recovery | MEM-014..MEM-019, MEM-084..MEM-085, MEM-213, MEM-219 | Member models/services, UUID and identity migrations, \`tests/Member/Wp04IdentityTest.php\`, MySQL conformance tests, and \`docs/member/wp-04-identity-evidence.md\` | MVP-01 consumes User/Member, authentication, protected identifiers, audit, and ownership foundations | accepted-foundation | Public/online exposure, child/guardian beta flow, identity UI, credential delivery, privacy/retention policy, and booking/financial behavior | Do not reopen WP-04 wholesale; online registration remains unwired and excluded. |
| WP-05 | Member bookings, points, funding, cancellation, and revaluation | MEM-020..MEM-037, MEM-220 | Member-owned four-decimal append-only point ledger, one active local rate, local/testing personal funding, atomic personal B2C charge, confirmed booking, idempotency, safe rollback, trusted Member ownership, controlled failure audit, and signed arbitrary-precision comparison evidence in `app/Modules/Member/` and MVP-03 tests | MVP-03 and later | partially-implemented | Cancellation, rescheduling, postponement, no-show, refunds, revaluation, top-up, real payment, B2B funding, and broader financial behavior | Only the bounded personal-points booking foundation is implemented; no balance column or production funding claim. |
| WP-06 | Member site, schedule, booking data, and eligibility | MEM-001..MEM-009, MEM-038..MEM-064, MEM-097..MEM-101, MEM-120..MEM-124, MEM-134..MEM-146, MEM-216..MEM-218 | Member-side read-only Operator organization/site references, active catalogue, UTC-normalized schedules with site-wide half-open overlap and quota 5..20, booked schedule immutability for site/service/time/quota except close/no-op, booking snapshots, one-active-booking enforcement, five-confirmed eligibility, local imaging order, and focused MVP-03 evidence | MVP-03 | partially-implemented | Clinical assessment, consent, attendance, walk-in, ServiceRequest/FHIR conformance, Operator assignment, and later lifecycle behavior | Site references preserve Operator physical-site authority; this is not Operator workflow or complete WP-06. |
| WP-07 | Member clinical, consent, attendance, walk-in, and cash contracts | MEM-068..MEM-083, MEM-086..MEM-096, MEM-102..MEM-107, MEM-215, MEM-221..MEM-222 | Bounded arrival/check-in and paper-consent contracts plus vital-sign assessment, paper-questionnaire/basic-examination behavior, and X-ray-readiness handoff are implemented with focused evidence; broader clinical, consent, walk-in, cash, retention, deletion, privacy, and FHIR requirements remain open | MVP-04 and later | partially-implemented | Full clinical workflow, general consent administration, walk-ins, cash closing, complete retention/deletion/privacy behavior, and FHIR conformance | Current bounded clinical/visit behavior does not establish full WP-07 conformance. |
| WP-08 | FHIR R5 and clinical interoperability foundation | MEM-125..MEM-133, MEM-223..MEM-227 | No FHIR package, profile, mapper, CapabilityStatement, or validator fixture is present | MVP-05 through MVP-07 when explicitly approved | not-started | R5 guide, profiles, terminology, and all interoperability behavior | No FHIR conformance claim. |
| WP-09 | Doctor repeat entitlement lifecycle | MEM-065..MEM-067 | No repeat entitlement or replacement ServiceRequest workflow is present | Post-MVP clinical scope | deferred-until-post-mvp | Entire package and Doctor Portal boundary | Doctor is external/excluded from initial beta. |
| WP-10 | Member administration, B2B import readiness, and acceptance harness | MEM-010..MEM-013, MEM-147..MEM-209, MEM-214, MEM-228 | Shared Filament admin shell, persistent Member claims, bounded Member list/detail/account-state/audit surface, and MVP-03 Member-owned offering/schedule management plus read-only site and booking visibility, all behind exact claims, execution-time reauthorization, and focused tests; no import contract or broad acceptance harness | MVP-02 and MVP-03 for bounded Member administration; MVP-08 for import | partially-implemented | B2B format, agreement data, bulk import, credential handoff, booking mutations, and broad Member administration UI | MVP-02 remediation evidence remains valid. MVP-03 adds only Member-owned catalogue/schedule mutations and read-only site/booking resources; Operator administration remains separate. |
| WP-11 | Operator authorization, sites, shifts, and staffing | OPR-001..OPR-014, OPR-100..OPR-107, OPR-117..OPR-124, OPR-129 | Shared Operator claims, profiles, physical sites, site assignments, active-site context, eligible-shift intake, manual shift assignment, and the clinic-day operational journey are implemented with focused evidence | MVP-04 for Operator-owned site, assignment, staffing, and clinic-day operations | partially-implemented | Remaining staffing, protocol, and broader Operator requirements | Current operational capability has advanced, but the bounded MVP-04 journey does not close WP-11. |
| WP-12 | Operator attendance, basic examination, and queues | OPR-015..OPR-030, OPR-130..OPR-131 | Site-scoped attendance, arrival/check-in, identity verification, paper consent, ticketing, queue admission, claim, call, start, vital signs, questionnaire, basic-examination completion, X-ray readiness, and X-ray claim/call behavior are implemented with focused evidence | MVP-04 | partially-implemented | Remaining attendance, basic examination, queue, walk-in, and public-display requirements | The clinic-day journey is substantially implemented but broader queue/attendance and long-term requirements remain open. |
| WP-14 | Operator protocol, NPZ drafts, and complete capture submission | OPR-031..OPR-046, OPR-132, OPR-137 | Operator capture form, metadata validation, component upload/status handling, durable complete-capture submission, and the Image Gateway boundary exist with focused evidence | MVP-04/MVP-05 on `mhcs-core` `main` | partially-implemented | Device schema, drafts, broader capture policy, and complete long-term business contract | Current capture/submission behavior is real but does not close every WP-14 requirement. |
| WP-15 | Operator AI status, corrections, repeats, and read-only images | OPR-047..OPR-060 | Authenticated read-only study discovery/viewing and DICOM download exist for authorized Operators, with Indonesian viewer coverage; AI status monitoring, corrections, repeat handoff, and remaining WP-15 behavior are not complete | MVP-05/MVP-06 on `mhcs-core` `main` | partially-implemented | Complete AI Results Status Monitor, corrections, repeat handoff, and remaining WP-15 behavior | MPIPS conversion and DICOM access do not establish AI, correction, or repeat completion. |
| WP-16 | Operator earnings, payouts, and cash reconciliation | OPR-061..OPR-073, OPR-133, OPR-135 | No Operator financial workflow or payout adapter is present | Post-MVP | deferred-until-post-mvp | Entire package | No financial behavior is in the initial beta. |
| WP-17 | Operator administration, contracts, and FHIR boundary | OPR-074..OPR-099, OPR-108..OPR-116, OPR-134, OPR-136, OPR-138 | Bounded Operator site, profile, site-assignment, eligible-shift, shift-assignment, arrival, queue, capture, and private paper-ticket resources plus local application contracts are implemented; FHIR and broader operational administration are absent | `mhcs-core` `main` for Operator ownership | partially-implemented | Remaining contracts, FHIR, protocol, queue exceptions, ticket policy, and operational administration | Requirement assignments and Operator ownership are unchanged; shared administration does not take ownership of Operator records or rules. |
| WP-18 | Doctor authorization, queue, study access, and claims | DOC-001..DOC-005, DOC-060..DOC-068 | Doctor boundary/provider only; no Doctor workflow is present | Excluded from initial beta | deferred-until-post-mvp | Entire package | Explicit Doctor Portal exclusion. |
| WP-19 | Doctor quality decisions and repeat workflow | DOC-006..DOC-019 | No Doctor quality/repeat workflow is present | Excluded from initial beta | deferred-until-post-mvp | Entire package | |
| WP-20 | Doctor report lifecycle and Member publication | DOC-020..DOC-028 | No Doctor report or Member publication workflow is present | MVP-06/MVP-07 only after owner-approved external/manual boundary | deferred-until-post-mvp | Doctor authoring and automated publication contract | Manual Operator handling remains a later fallback. |
| WP-21 | Doctor earnings and automated payouts | DOC-034..DOC-040, DOC-069 | No Doctor earning/payout workflow is present | Post-MVP | deferred-until-post-mvp | Entire package | |
| WP-22 | Doctor contracts, R5 reports, and security audit | DOC-041..DOC-059, DOC-070..DOC-071 | No Doctor contract/report/FHIR implementation is present | Post-MVP | deferred-until-post-mvp | Entire package | |
| WP-23 | Image Gateway storage, manifests, MPIPS, and processing | ARCH-019..ARCH-027, ARCH-042, ARCH-044, IMG-001..IMG-033, IMG-047..IMG-057 | `ImageGatewayCaptureService`, `ProcessCaptureSet`, `MpipsClient`, manifest/security handling, private object persistence, queued MPIPS processing, DICOM result persistence/validation, replay/idempotency behavior, and Operator study access are implemented with focused evidence | MVP-05 on `mhcs-core` `main` | partially-implemented | Full long-term WP-23 requirements, complete production policy, operational administration, retention/compliance closure, and any unresolved release/security controls | Current implementation/evidence advances beyond the former no-storage/no-adapter statement but does not claim full WP-23 conformance. |
| WP-24 | Image Gateway AI, publication, and replacement studies | IMG-034..IMG-046, IMG-058..IMG-059 | No AI, publication, report-return, or replacement-study workflow is present | Image Gateway workstream: contract-first status/publication slices, then MVP-06/MVP-07 provider behavior | not-started | Entire package | Requirement assignments and Image Gateway ownership are unchanged. Approved contracts and fixtures may precede main-workstream consumers; no automated report return or clinical AI claim follows from a contract alone. |
| WP-26 | Member-facing language and public-copy conformance | UIL-001, UIL-013, UIL-032, UIL-041, UIL-055, UIL-068, UIL-071, UIL-074, UIL-077, UIL-112, UIL-118, UIL-133, UIL-140, UIL-151, UIL-153, UIL-158, UIL-161, UIL-165, UIL-169, UIL-175, UIL-185, UIL-197, UIL-208, UIL-227, UIL-236, UIL-242, UIL-246, UIL-250, UIL-255, UIL-262, UIL-267, UIL-275, UIL-285, UIL-293, UIL-310, UIL-326, UIL-329, UIL-339, UIL-347, UIL-352, UIL-356, UIL-373, UIL-385, UIL-392, UIL-407, UIL-422, UIL-441 | Bahasa Indonesia policy, `lang/id.json`, browser-visible localization work, and localization tests exist | MVP-01 onward | partially-implemented | Complete language audit and full requirement coverage | Representative Member, LCD, Operator, capture, and DICOM-viewer copy is verified; complete conformance is not claimed. |
| WP-27 | Approved visual design implementation and visual verification | DES-001..DES-022 | Operator workstation visual implementation exists with browser/rendered verification; the complete approved design reference is not implemented | MVP-01 onward | partially-implemented | Complete design-reference implementation and visual verification | Current rendered evidence is bounded to the implemented Operator surfaces. |
| WP-28 | Final MHCS Core conformance audit | None; verification-only | No final audit has been run; implementation plan says it cannot pass while applicable requirements remain unverified | Release gate only | unverified | All unresolved requirements, evidence, dependencies, and approvals | No completion claim. |

The dated addenda below preserve historical evidence as of their recorded date;
they do not override the current status table above.

## MVP-03 admin, audit, and browser closure addendum — 2026-08-05

The bounded MVP-03 closure task was executed from baseline
`5dee2a1db3595d321c5a4a339d2d6f387111fc64` with `TARGET="."`. WP-05, WP-06,
and WP-10 now have focused evidence for trusted Member ownership, actor-state
denials, exact booking audit association, Member-scoped failure auditing, and
claim-gated Member-owned catalogue/schedule administration. Pest 4 browser
coverage passed in Chromium (3 tests, 38 assertions) with no final-page
console or JavaScript smoke errors. The work-package statuses remain
`partially-implemented`; broader financial, clinical, Operator, Image Gateway,
deployment, and production requirements are not closed.

Numbering gaps such as WP-03, WP-13, and WP-25 are preserved because they are absent from the current 25-package implementation plan; no new Work Package is invented here.

## Clinic-day preflight review — 2026-08-10

The current committed revision
`8ffd6f7e427dea3610582245ece926ea84cc2314` was reviewed as the planning
baseline for the clinic-day core workstream. Current focused Operator/X-ray/
architecture verification passed (63 tests, 2,266 assertions; one MySQL-only
test skipped outside MySQL), and the owner-authorized isolated MySQL verifier
passed fresh migration, representative suites, concurrency, full PHP suite
(248 tests, 3,839 assertions), and guarded rollback/reapplication probes.
The temporary MySQL container was removed after verification.

The historical MVP-04K through MVP-04N task content was later archived, so its
pre-execution immutable-publication history cannot be reconstructed under the
current task contract. This is a documented process limitation, not a claim
that the current functional evidence is absent. The review establishes the
listed committed revision as the planning baseline; it does not close any Work
Package, MVP gap, privacy/deployment approval, or release gate. Evidence:
`docs/mvp/evidence/mvp-04k-through-n-mysql-review.md`.

## MVP-04A remediation addendum — 2026-08-05

The prior remediation is committed at
`2e08eae74e49b0ba54461ba8787a0ec8e0ece062`. The committed closure candidate is
`f49da5991b21b9a13abb435539db1955362ef639`; the evidence-verification task
made only uncommitted documentation changes in the working tree and did not
create an execution commit. WP-07 remains `not-started` except for the exact
bounded attendance/arrival contract used by MVP-04. WP-11, WP-12, and WP-17
remain `partially-implemented`; the corrected confirmation lifecycle, trusted
site resolver, site-switch policy, and confirmation-only arrival surface do
not close their remaining staffing, queue, clinical, FHIR, or broader
Operator-administration requirements.

## MVP-04B front-desk identity verification addendum — 2026-08-06

The bounded MVP-04B slice adds a Member-owned protected identity lookup/view
contract and an Operator-owned verification case with append-only transitions.
The remediation adds a trusted case resolver on every Member-side operation,
case-bound asset grants, fail-closed current evidence, matched-decision
revalidation, a database-backed one-open-case claim, and controlled shared
audit reasons. WP-11, WP-12, and WP-17 remain `partially-implemented`; WP-07 remains
`not-started` except for the bounded contracts consumed by MVP-04. No consent,
check-in, ticket, queue, clinical, FHIR, imaging, deployment, or production
behavior is included.

## MVP-04B final boundary closure addendum — 2026-08-06

The final boundary task strengthens the existing partial MVP-04B slice with a
safe unavailable-evidence page, current-evidence gating for `matched`, direct
asset-grant slot enforcement, and persisted authentication/portal checks. WP-11,
WP-12, and WP-17 remain `partially-implemented`; WP-07 remains `not-started`
except for the bounded contracts consumed by MVP-04. No later MVP, clinical,
deployment, or production behavior is claimed.

## MVP-04B audit identifier sanitizer remediation addendum — 2026-08-07

WP-02 remains `accepted-foundation`; the shared audit sanitizer correction is
verified by the deterministic UUID/raw-scalar regression and the separate
WP-02, MVP-04B, Operator portal, and architecture suites. WP-07 remains
`not-started` except for its bounded consumed contracts, and WP-11, WP-12, and
WP-17 remain `partially-implemented`. No later MVP or production status is
advanced.

## MVP-04C paper consent confirmation addendum — 2026-08-07

The bounded MVP-04C task adds Member-owned paper-consent persistence and one
authorized Operator confirmation path with optional encrypted private upload.
WP-07 remains `not-started` except for its bounded consumed contracts, and
WP-11, WP-12, and WP-17 remain `partially-implemented`. General consent,
clinical, check-in, ticketing, queue, privacy/retention, deployment, and
production requirements remain open.

## MVP-04D verified check-in and paper ticket issue addendum — 2026-08-07

The bounded MVP-04D task adds a Member-owned `arrived` to `checked_in`
transition and an Operator-owned site-and-shift paper ticket with exact
number/site/schedule uniqueness, idempotent issue, private print, and audited
manual reprint. WP-07 remains `not-started` except for its bounded consumed
contracts, and WP-11, WP-12, and WP-17 remain `partially-implemented`. Queue,
clinical, ticket-generation policy, Member/public ticket visibility,
consent-scan access, privacy/retention, deployment, and production requirements
remain open.

## MVP-04E advance queue admission addendum — 2026-08-07

The bounded MVP-04E implementation adds an Operator-owned advance queue
admission and append-only initial history to the existing atomic MVP-04D
check-in/ticket transaction. It also adds a private assigned-shift
basic-examination waiting worklist with deterministic FIFO ordering and only
the approved ticket, site, shift-time, stage, state, and ready-time fields.
The task's explicit approval gate, validator, PHP syntax checks, Composer
metadata validation, diff checks, and Codebase Memory refresh passed. Runtime
closure is blocked because the repository has no `vendor/autoload.php`, so the
focused Laravel suites, Pint, migration, and route-list checks could not run;
no dependency installation was performed. WP-07 remains `not-started` except
for its bounded consumed contracts, and WP-11, WP-12, and WP-17 remain
`partially-implemented`. Queue claims/calls/skips, clinical examination,
walk-ins, public/LCD behavior, Member visibility, privacy/retention policy,
deployment, and production readiness remain open.

## MVP-04F atomic basic-examination claim — 2026-08-08

The bounded MVP-04F task was runtime-verified at working-tree HEAD
`428783e336bc48dba6df55df1715ec896d3b1e98`, descended from accepted baseline
`882a438947fc40fc43ba2e4e8864ce5ad18b2569`. It adds a nullable claiming
Operator/occurrence pair with a database uniqueness guarantee, a private
idempotent claim route, claimant-only worklist visibility, one waiting-to-
waiting history event, and matching audit/outbox evidence. The existing
suspended-account boundary was extended only to the new private claim route;
no generic authorization redesign was made.

MVP-04F passed 7 tests/58 assertions. MVP-04E, MVP-04D, MVP-04C, MVP-04B,
Operator portal, Operator foundation, WP-02 security, and architecture suites
also passed separately. Fresh in-memory SQLite migration, schema inspection,
PHP syntax, Pint, Composer validation, private route listing, privacy search,
Graphify refresh/query, Codebase Memory refresh/source review, task validation,
and `git diff --check` passed. WP-11, WP-12, and WP-17 remain
`partially-implemented`; queue release/call/skip, clinical examination, later
queue states, walk-ins, public/LCD behavior, Member visibility,
privacy/retention, deployment, production readiness, and the four listed
MVP-04 gaps remain open. No commit or push was made.

## MVP-04E runtime verification closure attempt — 2026-08-07

The owner-supplied candidate `26576ef89fe1a06ba0d75ba422f4a4efc2a3eaaa` was
verified as a descendant of the accepted baseline, and both published task
contracts validated. Codebase Memory found the canonical project and traced
the immutable MVP-04E issue/worklist paths. Closure is `blocked` because the
existing Composer vendor tree is absent (`vendor/autoload.php` and
`vendor/bin/pint`); Laravel tests, migration, route, syntax, Pint, Composer,
and privacy checks were not run under this closure task. The candidate and
final documentation-only worktree passed `git diff --check`. WP-07
remains `not-started` except for bounded consumed contracts, and WP-11, WP-12,
and WP-17 remain `partially-implemented`; all listed queue, clinical, public,
privacy/retention, deployment, and production gaps remain open.

## MVP-04E denial-matrix remediation addendum — 2026-08-08

The denial-matrix remediation was runtime-verified before commit at
pre-commit HEAD `6e91fe07feb010f92ae2719d55b67ea670ebbb98` and then committed
as candidate `2545c6a56ccb186f35bbdbe76f3598e9c3d5dcc3`. The committed
candidate contains the isolated test bootstrap's absent-value,
deterministic non-production MHCS key fallbacks and the narrow approved
exception in the existing mandatory-password middleware. The pre-commit SHA
is only the verification context. The correction allows the private
basic-examination worklist to reach its established Operator authorization
boundary for suspended users, producing the required 403 while preserving
fail-closed behavior elsewhere. MVP-04E's no-key suite and all required
regression, security, architecture, syntax, Pint, Composer, route, privacy,
graph/source, task-validation, and diff checks passed. WP-07 remains
`not-started` except for bounded consumed contracts, and WP-11, WP-12, and
WP-17 remain `partially-implemented`; queue actions, clinical examination,
walk-ins, public/LCD behavior, Member visibility, privacy/retention,
deployment, and production readiness remain open.

## MVP-04G private basic-examination call — 2026-08-08

MVP-04G is verified as a bounded private claimant-only `waiting` to `called`
transition for an eligible advance basic-examination admission. It preserves
claim ownership, occurrence and FIFO fields; revalidates account, permission,
site, shift, scope, and state; and atomically records history, audit, outbox,
and idempotency evidence. The private route and smallest Call form expose no
clinical, Member, booking, consent, identity, public, LCD, or audio data.

The focused suite passed 6 tests/66 assertions, and the required MVP-04F,
MVP-04E, MVP-04D, MVP-04C, MVP-04B, Operator, WP-02, and architecture suites
plus syntax, Pint, Composer, route, schema, privacy, graph/source, validator,
and diff checks passed. Evidence:
`docs/mvp/evidence/mvp-04g-private-basic-examination-call.md`.

This bounded slice does not close WP-11, WP-12, or WP-17. Clinical examination,
later queue states/actions, walk-ins, public/LCD/audio behavior, Member
visibility, privacy/retention, deployment, production readiness, and the four
listed MVP-04 gaps remain open. No commit or push was made.

## MVP-04H private basic-examination start — 2026-08-08

MVP-04H is verified as a bounded private claimant-only `called` to
`in_service` transition for an eligible advance basic-examination admission.
It preserves claim ownership, claim time, ticket, stage, ready time, and FIFO
fields; revalidates account, permission, site, shift, scope, claimant, and
state; and atomically records history, audit, outbox, and idempotency evidence.
The private worklist adds only the claimant's opaque Start action and no
clinical, Encounter, Member, booking, consent, identity, public, LCD, or audio
data.

The focused suite passed 6 tests/73 assertions. MVP-04G, MVP-04F, MVP-04E,
MVP-04D, MVP-04C, MVP-04B, Operator, WP-02, and architecture suites plus
schema, syntax, Pint, Composer, route, privacy, graph/source, validator, and
diff checks passed separately. Evidence:
`docs/mvp/evidence/mvp-04h-private-basic-examination-start.md`.

This bounded slice does not close WP-11, WP-12, or WP-17. Clinical
assessment/Encounter/FHIR behavior, later queue states/actions, X-ray,
walk-ins, public/LCD/audio behavior, Member visibility, privacy/retention,
deployment, production readiness, and the four listed MVP-04 gaps remain open.
No commit or push was made.
