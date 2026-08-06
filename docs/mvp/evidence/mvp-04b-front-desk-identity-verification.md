# MVP-04B Front-Desk Identity Verification Evidence

Date: 2026-08-06

This is bounded evidence for the published task
`.agents/tasks/mhcs-core-mvp-04b-front-desk-identity-verification-v1.md`,
executed with `TARGET="."`. It is not evidence of complete MVP-04, production
readiness, or approval of privacy/retention policy.

## Baseline and runtime

- Canonical target: `/var/www/mhcs-core`.
- Expected remote: `git@github.com:Madeena-software/mhcs-core.git`.
- Branch: `main`.
- Accepted task baseline: `cecbf8e5e6d944cf58a7b73c2db14177f1748b5f`.
- Execution HEAD: `cecbf8e5e6d944cf58a7b73c2db14177f1748b5f`.
- Baseline ancestry and clean preflight passed. The published task file was
  pre-existing user work and remains unmodified and untracked.
- No stage, commit, push, reset, clean, stash, deployment, production access,
  dependency change, or external-system write occurred.
- Required capabilities were available: repository read/write, shell,
  Codebase Memory MCP, and ponytail. The active runtime model was not exposed
  to the execution report.

## Codebase Memory MCP and ponytail

The initial graph check rejected the filesystem path as a project identifier
and identified the registered project as `mhcs-core`. The corrected project
check succeeded. The graph was stale against the accepted HEAD, so the least
expensive valid action was a fast refresh:

```text
index_repository(repo_path="/var/www/mhcs-core", mode="fast", name="mhcs-core", persistence=false)
  -> indexed 4,294 nodes and 10,319 edges
```

The final artifact reports the current execution commit, root, and the same
4,294-node/10,319-edge index. Final structural searches found the new Member
contract, Member implementation, Operator service, portal methods, and focused
test. Final traces covered case start, exact lookup to the Member contract,
inline retrieval through the private object boundary, and active-site switching
through the unresolved-case blocker. The graph provider's Branch node still
reports the older `2e08eae...` base/head while Git reports the accepted task
HEAD; its canonical root is correct and the artifact/source graph is current.
This Branch metadata discrepancy remains a tooling follow-up, not an
implementation claim.

Ponytail remained active at full level. Existing protected identifier, audit,
private-object, grant, active-site, arrival, and assignment primitives were
reused. No subagents were used.

## Bounded implementation

Member Core owns the protected NIK lookup, Member identity projection, age-
appropriate KTP/KIA selection, approved asset history, and private object
access. Operator Core owns the arrival-scoped verification case, claimant,
append-only transitions, decision state, portal, and Operator audit.

Added behavior:

- Exact permission `operator.identity.verify` is required in addition to
  authenticated User, exact Operator role, active profile, active site and
  site assignment, and active target-schedule assignment. Administrator-only
  context is rejected; valid dual-role Operator context remains allowed.
- Full NIK is accepted only in the lookup POST body. Member Core canonicalizes
  and hashes it through `ProtectedIdentifierService`, and returns only a safe
  projection with a masked identifier. Unknown, cross-site, wrong-schedule,
  not-arrived, and unauthorized cases use the same controlled unavailable
  failure.
- Current age-appropriate KTP/KIA and latest approved current profile photo
  are shown first. Previous approved photos require the same open case, an
  explicit bounded reason, an append-only reveal event, and a fresh inline
  short-lived purpose-bound grant. Grants, raw object keys, bytes, and
  permanent storage URLs are not persisted or returned.
- `operator_identity_verifications` stores UUID references to the arrival,
  booking, schedule, local site, and Operator profile, state, timestamps,
  bounded reason/category, and start operation identity. Its unique arrival
  and operation identities support one case per arrival and idempotent start.
- `operator_identity_verification_events` stores immutable start, prior-photo
  reveal, cancellation, and terminal-decision transitions without NIK or
  asset payloads. Open claims are transactionally locked; one open case per
  Operator and one active claimant per arrival are enforced by the service.
  Cancelled cases can be reclaimed only with the explicit reclaim input.
- Terminal decisions are exactly `matched`, `mismatch_reported`, and
  `insufficient_evidence`; the latter two require bounded reasons, and
  terminal cases cannot be reopened by an Operator. A matched decision does
  not mutate consent, check-in, ticket, queue, clinical, or Encounter state.
- An open verification case blocks switching away from its site. Cancellation
  or terminal decision releases the block. Site-switch audit metadata contains
  only bounded operational references and no NIK, asset key, bytes, or grant.
- Portal routes are authenticated and bounded to worklist/start/view/lookup,
  prior-photo reveal, inline asset retrieval, decision, and cancellation. No
  consent, check-in, ticket, queue, or administrator-dispute action is present.

## Changed files

- `app/Modules/Member/Application/Contracts/OperatorIdentityVerificationContract.php`
- `app/Modules/Member/Application/Services/Mvp04OperatorIdentityVerificationService.php`
- `app/Modules/Member/Application/Services/MemberAuthorization.php`
- `app/Modules/Member/Application/Services/MemberVerificationAssetService.php`
- `app/Modules/Member/MemberServiceProvider.php`
- `app/Modules/Operator/Application/Services/OperatorIdentityVerificationService.php`
- `app/Modules/Operator/Application/Services/OperatorAuthorization.php`
- `app/Modules/Operator/Application/Services/OperatorActiveSiteService.php`
- `app/Modules/Operator/Application/Services/OperatorWorklistService.php`
- `app/Http/Controllers/Operator/PortalController.php`
- `routes/web.php`
- `config/mhcs.php`
- `database/migrations/2026_08_06_000001_create_mvp04b_identity_verification_tables.php`
- `resources/views/operator/identity-verification.blade.php`
- `resources/views/operator/verification-worklist.blade.php`
- `tests/Feature/Operator/Mvp04bIdentityVerificationTest.php`
- `tests/Architecture/FoundationArchitectureTest.php`
- The task-permitted roadmap, gap register, work-package status, and MVP-04
  evidence documents.

## Verification evidence

The task validator passed:

```text
python3 .agents/skills/agent-task/scripts/validate_task.py .agents/tasks/mhcs-core-mvp-04b-front-desk-identity-verification-v1.md
Task contract is valid
```

Each required PHPUnit command was run separately:

| Command | Result |
|---|---:|
| `vendor/bin/phpunit tests/Feature/Operator/Mvp04OperatorPortalTest.php` | 8 tests, 63 assertions passed |
| `vendor/bin/phpunit tests/Operator/Mvp04OperatorFoundationTest.php` | 15 tests, 56 assertions passed |
| `vendor/bin/phpunit tests/Feature/Admin/Mvp04OperatorAdministrationTest.php` | 2 tests, 22 assertions passed |
| `vendor/bin/phpunit tests/Member/Wp04IdentityTest.php --filter 'asset\|identifier\|verification\|grant'` | 7 tests, 27 assertions passed |
| `vendor/bin/phpunit tests/Security/Wp02SecurityTest.php` | 23 tests, 94 assertions passed |
| `vendor/bin/phpunit tests/Architecture/FoundationArchitectureTest.php` | 6 tests, 1,522 assertions passed |
| `vendor/bin/phpunit tests/Feature/Operator/Mvp04bIdentityVerificationTest.php` | 3 tests, 34 assertions passed |

Additional checks passed:

- PHP syntax checks passed for every changed PHP file.
- Pint passed on every changed PHP file.
- `php artisan migrate --database=sqlite --force` applied the full migration
  chain, including the single MVP-04B forward migration. Its temporary local
  SQLite artifact was removed afterward.
- `php artisan route:list --path=operator` showed the bounded identity routes
  and no unconfirmed arrival route.
- Container inspection resolved the Member contract to
  `Mvp04OperatorIdentityVerificationService` and the Operator service. PHP
  reflection confirmed the seven bounded service operations are public.
- Targeted raw-NIK search found no 10–20 digit literal in the new identity
  implementation, controller, route, or view. The lookup controller does not
  flash input; existing arrival confirmation input flashing is unrelated.
- Targeted Operator identity search found no private object key, permanent URL,
  storage URL, download action, or grant in the Operator service/controller/UI.
- `git diff --check` passed.

## Deferred and residual scope

MVP-04 remains partial. WP-11, WP-12, and WP-17 remain partially implemented;
WP-07 remains not-started except for the bounded contracts consumed by this
slice. The following remain deferred or open: consent, `checked_in`, ticketing,
queue stages, examination, walk-ins, cash, administrator dispute resolution,
clinical behavior, imaging, FHIR, production storage/retention policy,
privacy/legal approval, CI, deployment, MySQL/Docker conformance, and browser
verification. Pest, Playwright, full PHPUnit, complete Work Package suites,
MySQL/Docker, npm, Composer audit, CI, deployment, external integrations, and
production checks were not run as prohibited by the task contract.

The remaining graph Branch metadata discrepancy and production migration,
object-storage, privacy, and retention approvals are residual risks. The
working tree is ready for owner-controlled review and commit; this execution
did not create or predict a commit.
