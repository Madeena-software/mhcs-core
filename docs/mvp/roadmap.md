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

## MVP-04 — Operator Queue and Attendance

Deliver Operator authentication/authorization, operational queue, scheduling,
check-in, attendance and examination-state transitions, and operational audit
evidence. Include the Operator-owned administration required for this slice:
sites, assignments, protocol and queue exceptions, and operational
configuration. These actions remain within Operator application boundaries.

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
