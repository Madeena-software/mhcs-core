# Controlled-beta roadmap

This is an implementation sequence, not a date plan. MVP-02 through MVP-09 are provisional and may be reprioritized by owner decision. Dependencies and evidence gates remain binding.

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

## MVP-06 — Operator Teleradiology Workflow

Deliver study routing/export status, external teleradiology tracking,
retry/failure handling, controlled manual report upload as the beta fallback,
automated report return only when a supported contract exists, and Operator
review/publication controls. MVP-06 may extend Operator and Image Gateway
administration only within their existing module ownership.

## MVP-07 — Member Result Visibility

Deliver access to the Member's own published result, strict ownership, safe presentation, publication state, and viewing/download only through approved private-object boundaries where applicable.

## MVP-08 — B2B Account Import

Deliver separately controlled Member and Operator import; validation/rejection reports; idempotency; duplicate detection; temporary credentials; mandatory password replacement; and audit/secret-handling controls. Do not implement before MVP-01 unless the owner reprioritizes it.

## MVP-09 — Beta Hardening and Deployment Readiness

Deliver cross-MVP regression verification, operational runbook, beta monitoring, backup/restore evidence, migration approval resolution, critical gap review, and the controlled beta deployment decision. MVP completion and deployment readiness do not establish production readiness.

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
