---
name: mhcs-core-mvp-04d-verified-check-in-ticket-issue
description: Atomically check in a consent-confirmed, identity-verified arrived booking and issue one private, site-and-shift paper ticket without creating a queue stage or clinical workflow.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04D — Verified Check-in and Paper Ticket Issue

## Objective

For `$TARGET`, add one bounded front-desk completion step: an assigned Operator
may mark an arrived advance booking as `checked_in` and issue its one
site-and-shift paper ticket only after the existing terminal `matched` identity
case and the existing Member-owned `Informed Consent` / `V1` confirmation are
both still valid. The booking transition, ticket issue, idempotency result, and
required Member and Operator audit/outbox evidence must succeed or fail as one
local database transaction.

The browser may invoke its normal print dialog for the issued or reprinted
paper ticket. Server-side evidence must record an issue or reprint request, not
claim that a physical printer completed a print.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `codebase-memory-mcp`
  - `ponytail`
- Ordered model preferences: None.
- Require preferred model: `false`

Codebase Memory MCP and ponytail are mandatory. Keep ponytail at full level:
reuse the current Operator arrival/identity/consent context, Member booking
transition, audit, outbox, idempotency, private portal, and print-template
patterns. Do not add a queue framework, ticket-number generator, dependency,
or generic workflow abstraction.

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Accepted baseline: `8a5c764f8bec97d6ca897bfcf079dc6bde225053`.
- The reviewed commit implements and validates
  `mhcs-core-mvp-04c-paper-consent-confirmation-v1.md` from accepted baseline
  `36ce5ab72a19cbdf5514f0d847ca50400ad3fe7d`. It keeps the booking `arrived`,
  has no ticket or queue schema/route, and is the required predecessor.
- The governing flow in `$TARGET/.agents/context/modules/operator/project.md`
  under `Attendance and identity verification` requires check-in and one
  site-and-shift ticket only after successful verification and consent. The
  paper slip contains only site name, shift/date, and a prominent ticket
  number; it omits the member name and medical-record number. Ticket numbers
  are managed on site through paper slips and are not Member Portal data.
- `$TARGET/.agents/context/modules/member/project.md` under `Arrival identity
  verification` and `Examination consent record` makes Member the booking and
  consent authority. A missing or refused consent blocks ticket issue.
- The completed paper-consent record is the approved member-signed `Informed
  Consent` / `V1` record. Its optional private scan remains out of this task:
  do not retrieve, display, copy, retain differently, or delete it.
- Related requirements: `MEM-185`, `OPR-020`, `OPR-026`, `OPR-108`,
  `OPR-115`, `OPR-116`, `OPR-117`, and `OPR-129`.
- Related Work Packages: WP-07 (Member consent authority), WP-11 (Operator
  ownership and authorization), WP-12 (attendance and ticket foundation), and
  WP-17 (authorization and audit).
- Related gaps remain open: `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and
  `MVP-GAP-024`.

Read completely before planning or changing files:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/skills/fix-bug/SKILL.md` when a reproducible defect is
  encountered;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/context/ui-language.md` for any new visible copy;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04b-front-desk-identity-verification-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04c-paper-consent-confirmation-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/docs/mvp/evidence/mvp-04c-paper-consent-confirmation.md`;
- `$TARGET/app/Modules/Member/Application/Contracts/OperatorAttendanceContract.php`;
- `$TARGET/app/Modules/Member/Application/Contracts/OperatorPaperConsentContract.php`;
- `$TARGET/app/Modules/Member/Application/Services/Mvp04AttendanceService.php`;
- `$TARGET/app/Modules/Member/Application/Services/Mvp04PaperConsentService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorArrivalService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorIdentityVerificationService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorPaperConsentConfirmationService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- `$TARGET/app/Modules/Operator/Infrastructure/TrustedOperatorIdentityVerificationContextResolver.php`;
- `$TARGET/app/Http/Controllers/Operator/PortalController.php`;
- `$TARGET/routes/web.php`;
- `$TARGET/database/migrations/2026_08_07_000001_create_examination_consents_table.php`;
- `$TARGET/tests/Feature/Operator/Mvp04cPaperConsentConfirmationTest.php`;
- `$TARGET/tests/Feature/Operator/Mvp04bIdentityVerificationTest.php`;
- `$TARGET/tests/Feature/Operator/Mvp04OperatorPortalTest.php`; and
- `$TARGET/tests/Security/Wp02SecurityTest.php`.

Use Codebase Memory MCP to verify canonical project/root and index freshness
before discovery. The accepted index after MVP-04C has 4,491 nodes and 11,054
edges. Do not infer a Git SHA from provider branch metadata. Use no refresh if
current source and required symbols are present; fast refresh only when source
changed or required symbols are absent; full re-indexing only when the graph is
missing or fast recovery fails. Search and trace the arrival, matched-case,
paper-consent, booking-transition, idempotency, audit, outbox, portal, and
print/view paths before selecting files. Record initial/final graph status and
any action.

## Scope and constraints

Included:

- one explicit Member application command that revalidates the trusted
  current Operator, active site and shift assignment, arrived booking, terminal
  matched identity case, and confirmed Member-owned consent before changing a
  booking from `arrived` to `checked_in`;
- one Operator-owned, database-backed ticket for that booking, with the
  paper-slip ticket identifier entered by the assigned Operator, unique within
  the trusted local site and Member schedule, and linked privately to the
  booking and its issuing Operator;
- a simple safe ticket-identifier boundary: trim and normalize a non-empty
  ASCII uppercase letter, digit, and hyphen identifier up to 32 characters;
  reject any other character or a duplicate within the same site and shift;
- a single outer local transaction and database constraints so booking status,
  Member status history, one ticket, idempotency outcome, and Member/Operator
  audit/outbox records cannot diverge;
- same-input idempotent replay, changed-input conflict rejection, deterministic
  competing issue behavior, and a distinct, auditable manual reprint request
  for the already-issued ticket; and
- a private authenticated Operator issue/result and print surface. Its print
  view and `window.print()` trigger may contain only the site display name,
  shift date/time label, and ticket number. It must never render Member name,
  medical-record number, NIK, booking ID, consent fields, scan information,
  member ID, or any clinical data.

For this task, the on-site paper slip remains authoritative. The submitting
Operator transcribes its existing number; the system does not invent a new
sequence, allocate a range, or infer a member identifier from it.

Excluded:

- queue ordering, ticket stage/state/history/claims/calls/skips, public LCD,
  walk-ins, no-shows, examination, assessment, vitals, encounter, X-ray,
  imaging, AI, FHIR, payment, cash, Member ticket visibility, or ticket
  administration;
- electronic signatures, consent corrections, representatives, any consent
  scan retrieval/display/download/deletion/retention change, extra upload, or
  additional consent form/version;
- public ticket URLs, ticket numbers in query strings, PDF/server-side print
  generation, printer/spooler integration, a claim that paper printing
  occurred, and any personal or clinical data in a printed slip, browser
  console, shared audit metadata, logs, or public payload;
- changing Member ownership of bookings/consents or Operator ownership of
  tickets, direct Operator mutation of Member bookings, dependencies, external
  systems, deployment, production access, commits, or pushes; and
- modifying existing published tasks, `.agents/context/**`, or
  `docs/implementation/**`.

Preserve current arrival, identity verification, consent confirmation, and
protected asset behavior. A ticket must never exist for a booking unless its
Member status transition committed. A `matched` case or confirmed consent must
not by itself issue a ticket. Any missing, revoked, stale, cross-site,
out-of-window, cancelled, non-matched, no-consent, already-checked-in,
duplicate-ticket, malformed-number, or replay-conflict path must fail closed
with no partial booking, ticket, audit, event, print-request, or queue state.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates:
  - The existing approved paper-consent scope is sufficient only to consume its
    confirmed status; do not change the form, signer, scan access, storage,
    retention, or deletion rules.
  - Stop as `awaiting-approval` if implementation needs a generated ticket
    sequence, paper-slip allocation policy, printer/spooler integration,
    Member ticket exposure, queue/LCD behavior, a different permission, or
    any personal/clinical data on the ticket.
  - Stop as `blocked` if task validation, Codebase Memory MCP, ponytail, or
    the focused verification toolchain is unavailable.
  - Stop as `awaiting-approval` for a different booking transition,
    representative consent, privacy-policy change, migration incompatibility,
    or overlapping-owner-work scope beyond this task.

## Execution procedure

1. Resolve `$TARGET` canonically; verify repository identity, branch, clean or
   owner-change worktree state, baseline ancestry, task immutability, and all
   required capabilities.
2. Validate this task with the repository validator before execution.
3. Verify ponytail at full level and record the existing services, trusted
   resolver, storage boundary, transaction/idempotency primitives, portal
   pattern, and focused tests that will be reused.
4. Confirm the current product remains at the reviewed MVP-04C boundary and
   that the requested behavior is exactly the constrained paper-slip issue
   above. Stop at an approval gate if not.
5. Verify Codebase Memory MCP freshness and trace the arrival-to-`arrived`,
   terminal matched identity-case, paper-consent, Member booking status event,
   and Operator idempotency/audit/outbox paths and their callers.
6. Inspect current schema, migration order, providers, contracts, services,
   routes, templates, print assets, and focused tests before selecting the
   smallest compatible Member command and Operator ticket record.
7. Add the Member-owned trusted check-in command and the Operator-owned ticket
   persistence in one local outer transaction. Revalidate all current account,
   role, permission, active-site, shift, case, consent, booking, and schedule
   facts under locks at mutation time. Use database constraints plus the
   existing idempotency primitive for one ticket/booking and one number/site/
   schedule; preserve same-input replay and reject changed replay.
8. Add only the private authorized Operator issue/result/reprint route(s) and
   templates. Keep ticket issue distinct from physical print completion; make
   the print view minimal and privacy-safe, then invoke the browser print
   dialog without carrying private values in a public URL or query string.
9. Add the smallest focused regressions: successful check-in/issue; exact
   replay; changed replay; competing same-booking and same-number requests;
   rollback when each Member, Operator, audit, or outbox write fails; absent
   consent; unmatched/stale/cross-site case; revoked account, permission,
   site, or shift assignment; invalid ticket input; prior/final booking states;
   no queue/clinical side effects; private print/reprint authorization; and
   printed/audit/outbox payload privacy.
10. Run the required verification separately, inspect the final diff and graph
    paths, then update only the bounded MVP evidence/status documents whose
    facts changed. Keep all listed gaps and later workflows open.
11. Stop before queue behavior, clinical behavior, ticket generation policy,
    consent-scan access, or any excluded work. Do not commit or push.

## Acceptance criteria

- [ ] Task validation, preflight, Codebase Memory MCP, ponytail, and the
      MVP-04C accepted baseline checks pass before product changes.
- [ ] Member remains the sole owner of the trusted `arrived` to `checked_in`
      booking transition and Operator remains the sole owner of its ticket.
- [ ] Only an active, currently assigned Operator with the existing portal and
      identity-verification authority at the active site can issue a ticket;
      all current case, consent, booking, and schedule constraints are
      revalidated at mutation time.
- [ ] A successful issue atomically creates exactly one private Operator ticket
      and one Member booking transition/status history plus required append-only
      audit/outbox evidence; every failed path leaves no partial state.
- [ ] One ticket number is unique within its trusted site and schedule, one
      ticket is unique per booking, same-input replay returns the original
      result, changed-input replay is rejected, and concurrent attempts are
      deterministic.
- [ ] The print/reprint surface is authenticated and site/shift scoped, records
      only a request, triggers the web print dialog, and renders only site,
      shift/date, and ticket number. It exposes no personal, booking, consent,
      scan, or clinical data and creates no Member-facing, public-LCD, or queue
      payload.
- [ ] Existing arrival, matched identity verification, paper-consent, booking,
      protected-asset, authorization, audit, and privacy behavior remains
      covered and unchanged except for the authorized `checked_in` transition.
- [ ] No excluded scope, approval-boundary change, dependency, context/spec
      edit, commit, or push occurs.

## Verification

- Method: Validate the task; run focused Member and Operator check-in/ticket feature and service tests separately for transaction, replay, duplicate/concurrency, authorization, status, consent, privacy, issue/reprint, and print-safe payload behavior; separately run the MVP-04C consent, MVP-04B identity, Operator portal/foundation, WP-02 security, and architecture suites; run PHP syntax and Pint on changed PHP files; inspect migration on a fresh in-memory database, routes, Codebase Memory call paths, sensitive-data searches, and `git diff --check`.
- Expected result: One currently authorized assigned Operator can transcribe and issue exactly one private on-site paper ticket only with the existing matched verification and confirmed consent, while Member atomically records `checked_in`; idempotency and database uniqueness make retries and races deterministic; every invalid path has no side effects; print/reprint output contains only site, shift/date, and ticket number; all focused and regression checks pass without introducing queue, clinical, public, Member-ticket, scan-access, or deployment behavior.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, baseline, execution HEAD, selected runtime/model when
  verifiable, approval-evidence decision, capabilities, outcome, affected
  interfaces/files, Codebase Memory MCP and ponytail evidence, exact checks and
  results, unrun checks, residual risks, and manual follow-up.
- Treat a changed ticket policy, unverified authorization/transaction/privacy
  boundary, unverified patch, or any ticket without a committed Member
  `checked_in` transition as unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report final worktree state and readiness for owner-controlled commit. After
the owner supplies an implementation commit SHA, review it against
`8a5c764f8bec97d6ca897bfcf079dc6bde225053` and this task before accepting a
new baseline or selecting the next vertical slice.
