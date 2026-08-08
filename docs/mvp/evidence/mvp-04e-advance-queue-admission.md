# MVP-04E Advance Queue Admission Evidence

## Execution boundary

The published task `mhcs-core-mvp-04e-advance-queue-admission-v1` was executed
with `TARGET="."` from `/var/www/mhcs-core` on branch `main` at execution
HEAD `8ba97255bc1961945d9802a37d504442e3e1cf55`. The accepted baseline is the
same commit and is an ancestor of the execution HEAD. The task contract
validated successfully. Its SHA-256 remained
`d1c2662c27999ce818b29e288889937b08a0c05c3c958871b515ed709bd6ff4a`.

The required approval gate was satisfied by the explicit user decision
`approve` after presenting the narrow transaction, ownership, privacy, and
FIFO plan. The implementation remains limited to advance-booking admission;
it adds no clinical data, walk-in rule, claim/call/skip action, public display,
Member-visible queue, new permission, dependency, commit, or push.

## Implemented scope

- Added `operator_queue_admissions` and
  `operator_queue_admission_history` with foreign keys, one-ticket uniqueness,
  one initial admission-history event, append-only application usage, and FIFO
  indexes.
- Extended the existing `OperatorCheckInTicketService` idempotency transaction
  so Member check-in, paper ticket, queue admission, initial history, audit,
  outbox, and handled idempotency state commit or roll back together.
- Added the authenticated private
  `operator.basic-examination-worklist` route and view. It rechecks portal,
  account, role, permission, active site, site assignment, and assigned-shift
  scope, then returns only ticket number, site, shift times, stage, state, and
  ready time ordered by `ready_at` and immutable admission ID.
- Added focused regression coverage for admission, replay/competition, FIFO
  ties, audit/outbox rollback, authorization/scope revocation, and payload
  privacy.

## Verification evidence

Passed:

- `python3 .agents/skills/agent-task/scripts/validate_task.py ...`;
- PHP syntax checks for every changed PHP file;
- `composer validate --no-check-publish --no-interaction`;
- `git diff --check`, including the final evidence update;
- task immutability/hash check; and
- Codebase Memory MCP initial discovery and final fast refresh.

Initial graph discovery reported 4,025 nodes and 10,492 edges with the
required MVP-04D symbols present. The final fast refresh reported 4,043 nodes
and 10,604 edges. The final trace for
`OperatorCheckInTicketService::issue` includes the Member check-in contract,
`DatabaseIdempotencyStore::run`, assignment validation, audit, outbox, and
portal authorization paths. The final graph indexes and traces include
`OperatorWorklistService::basicExamination` and its portal controller caller.

The new worklist projection and template were also inspected for sensitive
fields; no Member, booking, consent, identity, clinical, or queue-position
value is selected or rendered. The existing Operator worklist remains
unchanged.

## Blocked verification

The focused MVP-04E, MVP-04D, MVP-04C, MVP-04B, Operator portal, Operator
foundation, architecture, and WP-02 security suites were each attempted with
`php artisan test`. Every command stopped before Laravel boot because
`/var/www/mhcs-core/vendor/autoload.php` is missing. `vendor/bin/pint` is also
unavailable, so Pint, fresh SQLite migration, and route-list checks could not
run. Dependency installation is excluded by the task and was not performed.

Therefore this execution outcome is `blocked`, not a runtime-verified success.
The implementation requires an owner-controlled environment with the declared
Composer dependencies installed before acceptance can be claimed. MVP-04E's
broader WP-12 queue/examination gaps and the task-listed later workflows remain
open.

## MVP-04E runtime verification closure attempt — 2026-08-07

The closure task was executed with `TARGET="."` at candidate HEAD
`26576ef89fe1a06ba0d75ba422f4a4efc2a3eaaa`, descending from accepted baseline
`8ba97255bc1961945d9802a37d504442e3e1cf55`. The only initial worktree change
was the supplied untracked closure task. Both the closure task and the
published MVP-04E task validated successfully; their observed SHA-256 values
are `8dba49ce25014336e774de068a276bfb40052a5ae2fced78b4a96703deb78885` and
`d1c2662c27999ce818b29e288889937b08a0c05c3c958871b515ed709bd6ff4a`.

Codebase Memory MCP confirmed project `var-www-mhcs-core`, with 4,066 nodes
and 10,612 edges. No refresh was applied because the candidate source was
unchanged and the required issue/worklist symbols were present. Traces covered
ticket issue through Member check-in, idempotency, assignment, audit/outbox,
and authorization, plus the private worklist controller path.

The required runtime prerequisite is unavailable: `vendor/autoload.php` and
`vendor/bin/pint` are absent, and `php artisan --version` fails before Laravel
boot. PHP `8.4.21`, Composer `2.7.1`, SQLite/PDO-SQLite, and the isolated
`phpunit.xml` settings (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) were
observed. Per the task contract, no dependency installation or framework
verification was attempted after this blocker.

Closure outcome: `blocked`. Composer validation, focused/regression/security/
architecture suites, fresh testing migration, operator route listing, PHP
syntax, Pint, and privacy searches remain unrun under this closure task. The
candidate and final documentation-only worktree both passed `git diff --check`.
The owner must provide the existing dependency tree and rerun this closure
task; no product change is indicated.

## MVP-04E denial-matrix remediation — 2026-08-08

The published denial-matrix remediation task was executed with `TARGET="."` at
HEAD `6e91fe07feb010f92ae2719d55b67ea670ebbb98`. The task SHA-256 remained
`7678324acd7d3fca117feb74516b01ea2681aa4b502d572224ace3897f493cf4`, and the
three published MVP-04E task contracts validated successfully. The required
approval gate was satisfied by the explicit approvals `approve denial-matrix
patch` and `approve shared middleware correction`.

The default isolated test bootstrap now supplies fixed non-production MHCS
identifier, object-encryption, and access-grant values only when the
corresponding config values are absent; it does not write environment files or
override supplied values. The exact remaining 302 was isolated to the shared
`EnforceMandatoryPasswordChange` middleware: a suspended account was redirected
to login before the existing Operator 403 boundary ran. The approved minimal
correction allows only the named private basic-examination worklist route to
reach that existing boundary for suspended users; all other fail-closed
behavior remains unchanged.

With `APP_KEY`, `MHCS_IDENTIFIER_KEY`, `MHCS_OBJECT_ENCRYPTION_KEY`,
`MHCS_ACCESS_GRANT_KEY`, `MHCS_MANIFEST_KEY`, and `MHCS_MANIFEST_KEY_ID` absent,
MVP-04E passed 6 tests and 61 assertions. The independently observed denial
matrix was revoked shift `200` with an empty worklist, revoked site `403`,
revoked portal permission `403`, forged active-site session `403`, and
suspended account `403`; the focused assertions verified no worklist or
internal detail leakage.

Required verification passed separately: MVP-04E (6 tests/61 assertions),
MVP-04D (9/83), MVP-04C (6/64), MVP-04B (16/84), Operator portal (8/63),
Operator foundation (15/56), WP-02 security (24/103), and architecture
(6/1,573). PHP syntax, Pint test mode, Composer validation, operator route
listing, targeted worklist privacy search, Codebase Memory discovery/source
review, task validation, and `git diff --check` also passed. The worklist
projection remains limited to ticket, site, shift times, stage, state, and
ready time. No production configuration, dependency manifest, commit, or push
was changed.

The relevant Codebase Memory project is `var-www-mhcs-core`; searches found
`basicExaminationWorklist`, `basicExamination`, `portalSite`, and
`OperatorAuthorization`. The approved test-only bootstrap correction and
route-boundary middleware correction are the only implementation changes in
this remediation. MVP-04E remains bounded: queue claims/calls/skips, clinical
examination, walk-ins, public/LCD behavior, Member visibility,
privacy/retention policy, deployment, and production readiness remain open.
