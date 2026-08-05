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
routes, and Operator-owned administration. MVP-04 remains incomplete:
check-in, ticketing, queue stages, consent, identity decisions, clinical
workflow, and remaining queue/attendance behavior are deferred.

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
identity-decision, clinical, and broader attendance work remains deferred.

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
