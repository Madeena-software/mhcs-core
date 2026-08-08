# MVP-04G Private Basic-Examination Call Evidence

## Execution boundary

The published task `mhcs-core-mvp-04g-private-basic-examination-call-v1` was
executed with `TARGET="."` from `/var/www/mhcs-core` on branch `main` at
working-tree HEAD `a02e01e75e14ae31607b9731dc44ec8f55e16150`. The accepted
MVP-04F baseline `a02e01e75e14ae31607b9731dc44ec8f55e16150` and prior accepted
baseline `882a438947fc40fc43ba2e4e8864ce5ad18b2569` are ancestors of that
HEAD. The task SHA-256 is
`2bb716fc93e44d5bced1b606770265cc776c37eec21274f5123c89466bdd2b8c`.

The task and all four immutable MVP-04E contracts plus MVP-04F validated
successfully. The approval gate was explicitly satisfied before
implementation with the claimant-only transition, revalidation boundary,
idempotency payload, audit/outbox names, conflict/forbidden behavior, and
private UI requirements.

No commit, push, dependency installation, production configuration, or real
secret was used.

## Bounded implementation

- Added `OperatorWorklistService::callBasicExamination()` and reused the
  existing authorization, idempotency, audit, outbox, transaction, and clock
  boundaries.
- The private POST route
  `operator.basic-examination-worklist.call` is
  `/operator/basic-examination-worklist/{admission}/call`. The request accepts
  only the opaque route admission ID and a UUID `operation_id`.
- Inside one transaction, the route revalidates the account, permission,
  trusted active site, site assignment, assigned shift, admission scope,
  claimant ownership, queue class, stage, and state. Only the claimant's
  eligible `advance` / `basic_examination` / `waiting` admission transitions
  from `waiting` to `called`.
- Claim ownership, `claimed_at`, queue class, stage, ticket, `ready_at`, and
  FIFO-relevant fields are preserved. One `called` history row records
  `waiting` to `called` with the actual `occurred_at`.
- Matching audit and outbox records are written atomically. The idempotency
  consumer is `operator.basic-examination.call` and its exact payload is
  `admission_id`, `operator_profile_id`, and `operator_site_id`.
- The worklist keeps the existing `Claimed by you` indicator and adds the
  smallest private Call form. The controller and middleware return safe 403
  or 409 boundaries without internal detail.

The call path does not start clinical examination, set `in_service`, recall,
skip, release, expose Member/booking/consent/identity/clinical data, or add
public, LCD, audio, or other display behavior. No migration was needed;
existing `operator_queue_admission_history.occurred_at` records the call
occurrence.

## Observed verification

Focused and required suites passed separately with MHCS key environment values
absent:

| Check | Result |
|---|---:|
| MVP-04G private call suite | 6 tests / 66 assertions |
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

- `php artisan migrate:fresh --force --database=sqlite` with an isolated
  in-memory SQLite database; the existing schema applied successfully.
- Schema inspection confirmed `operator_profile_id` and `claimed_at`, plus
  the unique `operator_queue_admission_active_claim_profile_unique` index.
- PHP syntax checks for all `app`, `database`, `routes`, and `tests` PHP files.
- `vendor/bin/pint --test`.
- `composer validate --no-check-publish` (valid; Composer emitted only
  existing PHP deprecation notices).
- Private operator route listing, including the new POST call route.
- Targeted call privacy search and `git diff --check`.
- All six published task validators: MVP-04G, MVP-04F, and the four MVP-04E
  contracts.

Graphify was refreshed with its local AST-only update after source changes and
queried for the private call transition. The final relationship query found
the call service, controller, route, view, tests, authorization, idempotency,
audit, outbox, FIFO, and public-display nodes but was truncated by its query
budget. Its CLI reported that semantic extraction would require a configured
API key; no key or dependency was requested. Codebase Memory MCP was refreshed
in fast mode and reported project `var-www-mhcs-core` with 4,298 nodes and
11,259 edges; final searches located both controller and service call
boundaries. Direct source, task, context, and test evidence remains
authoritative.

## Outcome and residual scope

Outcome: `succeeded` for the bounded MVP-04G task. The worktree is ready for an
owner-controlled commit review; the execution agent did not commit or push.

WP-11, WP-12, and WP-17 remain partially implemented. Queue start/clinical
examination, later queue states, recall/skip/release, walk-ins, public/LCD/
audio behavior, Member visibility, privacy/retention policy, deployment, and
production readiness remain outside this slice. The task-listed open gaps
`MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and `MVP-GAP-024` remain open.
