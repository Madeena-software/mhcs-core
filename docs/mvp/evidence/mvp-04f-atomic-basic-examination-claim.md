# MVP-04F Atomic Basic-Examination Claim Evidence

## Execution boundary

The published task `mhcs-core-mvp-04f-atomic-basic-examination-claim-v1` was
executed with `TARGET="."` from `/var/www/mhcs-core` on branch `main` at
working-tree HEAD `428783e336bc48dba6df55df1715ec896d3b1e98`. The accepted
baseline `882a438947fc40fc43ba2e4e8864ce5ad18b2569` is an ancestor of that
HEAD. The task SHA-256 is
`d13630cc49245d593914fb53f2e9376335f6df13c69d715d7207b0b2f84b8595`.

The task and all four immutable MVP-04E contracts validated successfully. The
approval gate was explicitly satisfied before implementation with the exact
claim fields, uniqueness rule, HTTP conflict/forbidden behavior, idempotency
payload, audit/outbox names, and stage/state/privacy proof.

No commit, push, dependency installation, production configuration, or real
secret was used.

## Bounded implementation

- Added nullable `operator_profile_id` and `claimed_at` to
  `operator_queue_admissions`, with a restrictive Operator-profile foreign
  key and nullable unique index
  `operator_queue_admission_active_claim_profile_unique`. Multiple `NULL`
  values remain valid; one non-NULL profile can own only one active claim.
- Added `OperatorWorklistService::claimBasicExamination()` using the existing
  `OperatorAuthorization`, `OperatorShiftAssignmentService`,
  `DatabaseIdempotencyStore`, `DatabaseAuditStore`, `DatabaseOutboxStore`, and
  clock boundaries. The admission row is locked, portal/account/site/shift
  scope is revalidated, and the claim, history, audit, outbox, and handled
  idempotency result commit in one transaction.
- Added private POST route
  `operator.basic-examination-worklist.claim` at
  `/operator/basic-examination-worklist/{admission}/claim`. The business
  request accepts only the route admission ID and `operation_id`.
- Added the smallest worklist form. It carries only an opaque local admission
  ID and operation identifier; an owner sees `Claimed by you`, while another
  Operator's claimed row is filtered out.
- Added the narrow suspended-account middleware exception for the new POST
  route so it reaches the established Operator 403 boundary, matching the
  existing private GET worklist behavior.

Successful claims persist only `operator_profile_id` and `claimed_at` on the
admission. They append `claimed` history with `waiting` to `waiting`, audit
action `operator.queue-admission.claimed`, and outbox event
`operator.queue-admission-claimed`. The idempotency consumer is
`operator.basic-examination.claim` and its payload is exactly
`admission_id`, `operator_profile_id`, and trusted `operator_site_id`.

Authorized stale/non-claimable conflicts return HTTP 409; unauthorized,
foreign, stale-scope, or another Operator's claim returns HTTP 403 without
internal detail. The claim path does not change `basic_examination`,
`waiting`, `ready_at`, or admission ordering, and does not add call/start,
clinical, station, release, walk-in, public, Member, booking, consent,
identity, or internal-exception data to the private worklist or claim result.

## Observed verification

Focused and required suites passed separately with MHCS key environment values
absent:

| Check | Result |
|---|---:|
| MVP-04F claim suite | 7 tests / 58 assertions |
| MVP-04E advance admission | 6 tests / 61 assertions |
| MVP-04D verified check-in | 9 tests / 83 assertions |
| MVP-04C paper consent | 6 tests / 64 assertions |
| MVP-04B identity verification | 16 tests / 84 assertions |
| Operator portal | 8 tests / 63 assertions |
| Operator foundation | 15 tests / 56 assertions |
| WP-02 security | 24 tests / 103 assertions |
| Architecture | 6 tests / 1,573 assertions |

Additional checks passed:

- `php artisan migrate:fresh --force --database=sqlite` with an isolated
  in-memory SQLite database; the new migration applied successfully.
- Schema inspection showed `operator_profile_id` and `claimed_at`, plus the
  unique `operator_queue_admission_active_claim_profile_unique` index on
  `operator_profile_id`.
- PHP syntax checks for all `app`, `database`, `routes`, and `tests` PHP files.
- `vendor/bin/pint --test`.
- `composer validate --no-check-publish` (valid; Composer emitted only existing
  PHP deprecation notices).
- Private operator route listing, including the new POST claim route.
- Targeted privacy-sensitive search and `git diff --check`.

Graphify was queried before implementation, then refreshed with its local
AST-only update after source changes. Its final relationship query included
the claim method, migration, worklist, authorization, idempotency, audit, and
outbox nodes but was intentionally truncated by the query budget. Its CLI
reported that semantic extraction would require a configured API key; no key
or dependency was requested. Authoritative task/context/docs and source were
inspected directly. Codebase Memory MCP was refreshed in fast mode and
reported project `var-www-mhcs-core` with 4,269 nodes and 11,014 edges; final
searches located both controller and service claim boundaries.

## Outcome and residual scope

Outcome: `succeeded` for the bounded MVP-04F task. The worktree is ready for an
owner-controlled commit review; the execution agent did not commit or push.

WP-11, WP-12, and WP-17 remain partially implemented. Queue release/call/skip,
clinical examination, later queue states, walk-ins, public/LCD behavior,
Member visibility, privacy/retention policy, deployment, and production
readiness remain outside this slice. The task-listed open gaps
`MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and `MVP-GAP-024` remain open.
