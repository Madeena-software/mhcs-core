# MVP-04H Private Basic-Examination Start Evidence

## Execution boundary

The published task `mhcs-core-mvp-04h-private-basic-examination-start-v1` was
executed with `TARGET="."` from `/var/www/mhcs-core` on branch `main` at
working-tree HEAD `c3b2537960ef7e82e9a068f73e414ba0ae40ff50`. This is the
accepted MVP-04G baseline and is an ancestor of the candidate worktree. The
task SHA-256 is
`6883d8f9079ea5cf6970060ab52551041fcddbd1649adb72ccedf1773b8c999c`.

The 04H task and prerequisite MVP-04G, MVP-04F, and all four immutable MVP-04E
contracts validated successfully. The owner explicitly approved the required
implementation gate before source, test, and documentation changes.

No dependency installation, production configuration, real secret, commit, or
push was performed for this task.

## Bounded implementation

- Added `OperatorWorklistService::startBasicExamination()` with purpose
  `operator.basic-examination.start`. It uses the existing authorization,
  idempotency, transaction, audit, outbox, clock, admission-history,
  active-site, and assigned-shift boundaries.
- The private POST route
  `operator.basic-examination-worklist.start` is
  `/operator/basic-examination-worklist/{admission}/start`. The request accepts
  only the opaque route admission ID and UUID `operation_id`.
- Inside the idempotent transaction, the admission is locked and the account,
  permission, trusted active site, site correspondence, assigned shift,
  admission scope, claimant ownership, queue class, stage, and `called` state
  are revalidated. Only the current trusted claimant may transition
  `advance` / `basic_examination` from `called` to `in_service`.
- The state update preserves `operator_profile_id`, `claimed_at`, queue class,
  stage, paper ticket, `ready_at`, and FIFO fields. It appends exactly one
  `started` history row from `called` to `in_service` with actual
  `occurred_at`.
- Matching append-only audit action `operator.queue-admission.started` and
  version-1 outbox event `operator.queue-admission-started` are written in the
  same transaction. The exact idempotency payload is
  `admission_id`, `operator_profile_id`, and `operator_site_id`.
- Added the smallest private Start form and a narrow suspended-account
  middleware exception. Called claimant rows use the generic
  `Current claimed admission` label and an opaque Start action, preserving the
  accepted MVP-04G no-ticket rendering boundary while keeping database ticket
  and FIFO fields unchanged.

The start path does not create an Encounter, record clinical values, capture or
submit an examination, advance the stage, add recall/skip/release/walk-in
behavior, expose Member/booking/consent/identity data, or add public/LCD/audio
behavior. No migration was needed: the existing state column accepts
`in_service`, and existing `operator_queue_admission_history.occurred_at`
records the start occurrence.

## Observed verification

Required suites passed separately with no process-injected MHCS keys:

| Check | Result |
|---|---:|
| MVP-04H private start | 6 tests / 73 assertions |
| MVP-04G private call | 6 tests / 66 assertions |
| MVP-04F atomic claim | 7 tests / 58 assertions |
| MVP-04E advance admission | 6 tests / 61 assertions |
| MVP-04D verified check-in | 9 tests / 83 assertions |
| MVP-04C paper consent | 6 tests / 64 assertions |
| MVP-04B identity verification | 16 tests / 84 assertions |
| Operator portal | 8 tests / 63 assertions |
| Operator foundation | 15 tests / 56 assertions |
| WP-02 security | 24 tests / 103 assertions |
| Architecture | 6 tests / 1,573 assertions |

Additional checks passed:

- Fresh in-memory SQLite migration. Schema inspection confirmed
  `operator_profile_id`, `claimed_at`, and `occurred_at`; no new migration was
  added.
- PHP syntax checks for all `app`, `database`, `routes`, and `tests` PHP files.
- `vendor/bin/pint --test`.
- `composer validate --no-check-publish --no-interaction`; Composer reported
  the existing PHP deprecation notices and `composer.json` was valid.
- Private operator route listing showed 45 routes, including the POST start
  route.
- Targeted privacy search, task validation for 04H/04G/04F and the four 04E
  contracts, and `git diff --check`.

Graphify was queried before implementation, refreshed when the tracked 04G
documentation was newer, and refreshed again after the 04H source changes.
The final local AST-only graph update reported 2,529 nodes and 6,015 edges;
the final relationship query located the 04H focused tests, worklist, route,
idempotency, audit, outbox, and public-display boundary nodes but was
truncated by its query budget. The CLI reported that semantic extraction would
require a configured API key; no key or dependency was requested.

Codebase Memory MCP was refreshed in fast mode for canonical project
`var-www-mhcs-core`, reporting 4,336 nodes and 11,473 edges. Raw graph search
located the changed controller/service route strings, while semantic symbol
enrichment returned empty result arrays despite match counts after the refresh;
direct source, task/context, and observed test output remain authoritative.

## Outcome and residual scope

Outcome: `succeeded` for the bounded MVP-04H task. The candidate diff is
compared with accepted baseline `c3b2537960ef7e82e9a068f73e414ba0ae40ff50`
and contains only the private start transition, its route/controller/middleware
and bounded worklist UI, focused test, and required evidence/status updates.
The worktree is ready for owner-controlled commit review; the execution agent
did not commit or push.

WP-11, WP-12, and WP-17 remain partially implemented. Clinical basic
examination/vital-sign capture, Encounter/FHIR behavior, later queue states and
actions, X-ray workflow, walk-ins, public/LCD/audio behavior, Member
visibility, privacy/retention approval, deployment, and production readiness
remain outside this slice. `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and
`MVP-GAP-024` remain open.
