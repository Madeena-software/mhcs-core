# MVP-04B Front-Desk Identity Verification Evidence

Date: 2026-08-06

This is bounded evidence for the published task
`.agents/tasks/mhcs-core-mvp-04b-identity-verification-remediation-v1.md`,
executed with `TARGET="."`. It is not evidence of complete MVP-04, production
readiness, or approval of privacy/retention policy.

## Baseline and runtime

- Canonical target: `/var/www/mhcs-core`.
- Expected remote: `git@github.com:Madeena-software/mhcs-core.git`.
- Branch: `main`.
- Accepted task baseline: `cecbf8e5e6d944cf58a7b73c2db14177f1748b5f`.
- Candidate under remediation: `463387f7eba1eb0420a931256da8db8e4adfdedf`.
- Execution HEAD: `fa23f977b6971456ef04721735a191cd0e1e2553` (published task
  commit); implementation changes remain uncommitted in the working tree.
- Baseline ancestry and clean preflight passed. The published remediation task
  is tracked, unmodified, and remains the execution contract.
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
  -> indexed 4,347 nodes and 10,493 edges
```

The refresh artifact contains 4,347 nodes and 10,493 edges. Final structural
searches found the trusted case resolver, new Member contract methods, Member
implementation, Operator service, portal methods, and focused test. The
forward migration was verified separately because the graph excludes
`database/migrations`. Final traces covered case start, exact lookup to the Member
contract, inline retrieval through the private object boundary, matched
revalidation, and active-site switching through the unresolved-case blocker.
The graph provider's Branch node still reports the older `2e08eae...` base/head
and does not expose the current canonical worktree in the final query, while
Git reports the accepted task HEAD. The artifact/source graph is current; this
Branch metadata discrepancy remains a tooling follow-up, not an
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
- A trusted Member-owned case resolver revalidates actor role and permission,
  active profile, local/stable site correspondence, site and schedule
  assignment, exact open case ownership, booking/arrival correspondence, and
  arrived state on every lookup/view/reveal/retrieval path. It returns only
  opaque case/booking/member references and the audited prior-photo flag.
- Current age-appropriate KTP/KIA and latest approved current profile photo
  are shown first; current view fails closed if either approved current asset
  is missing. Previous approved photos require the same open case, an explicit
  bounded reason, an append-only reveal event, and a fresh inline short-lived
  purpose-bound grant. Historical KTP/KIA and unrelated-member assets are
  denied. Grants, raw object keys, bytes, and permanent storage URLs are not
  persisted or returned.
- `operator_identity_verifications` stores UUID references to the arrival,
  booking, schedule, local site, and Operator profile, state, timestamps,
  bounded reason/category, and start operation identity. Its unique arrival
  and operation identities support one case per arrival and idempotent start.
- `operator_identity_verification_events` stores immutable start, prior-photo
  reveal, cancellation, and terminal-decision transitions without NIK or
  asset payloads. Open claims lock the stable Operator profile row; a nullable
  unique `active_claim_operator_profile_id` column is set only while open,
  cleared on cancellation and terminal decision, backfilled for existing open
  rows by one forward migration, and provides the database rejection boundary
  for a second active claim. One active claimant per arrival remains enforced.
  Cancelled cases can be reclaimed only with the explicit reclaim input.
- Terminal decisions are exactly `matched`, `mismatch_reported`, and
  `insufficient_evidence`; the latter two require bounded reasons, and
  terminal cases cannot be reopened by an Operator. `matched` revalidates
  current evidence before any case/event/audit mutation and does not mutate
  consent, check-in, ticket, queue, clinical, or Encounter state.
- Free-text reasons remain bounded in local case/event records only. Shared
  audit events use controlled categories such as
  `identity_mismatch_reported`, `identity_evidence_insufficient`, and
  `latest_photo_insufficient`; control characters are rejected.
- An open verification case blocks switching away from its site. Cancellation
  or terminal decision releases the block. Site-switch audit metadata contains
  only bounded operational references and no NIK, asset key, bytes, or grant.
- Portal routes are authenticated and bounded to worklist/start/view/lookup,
  prior-photo reveal, inline asset retrieval, decision, and cancellation. No
  consent, check-in, ticket, queue, or administrator-dispute action is present.

## Changed files

- `app/Modules/Member/Application/Contracts/OperatorIdentityVerificationContract.php`
- `app/Modules/Member/Application/Contracts/TrustedOperatorIdentityVerificationContextResolver.php`
- `app/Modules/Member/Application/Services/MemberVerificationAssetService.php`
- `app/Modules/Member/Application/Services/Mvp04OperatorIdentityVerificationService.php`
- `app/Modules/Operator/Application/Services/OperatorIdentityVerificationService.php`
- `app/Modules/Operator/Infrastructure/TrustedOperatorIdentityVerificationContextResolver.php`
- `app/Modules/Operator/OperatorServiceProvider.php`
- `database/migrations/2026_08_06_000002_add_mvp04b_identity_active_claim.php`
- `resources/views/operator/identity-verification.blade.php`
- `tests/Architecture/FoundationArchitectureTest.php`
- `tests/Feature/Operator/Mvp04bIdentityVerificationTest.php`
- The task-permitted roadmap, gap register, work-package status, and MVP-04B
  evidence document.

## Verification evidence

The task validator passed:

```text
python3 .agents/skills/agent-task/scripts/validate_task.py .agents/tasks/mhcs-core-mvp-04b-identity-verification-remediation-v1.md
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
| `vendor/bin/phpunit tests/Architecture/FoundationArchitectureTest.php` | 6 tests, 1,539 assertions passed |
| `vendor/bin/phpunit tests/Feature/Operator/Mvp04bIdentityVerificationTest.php` | 11 tests, 57 assertions passed |

Additional checks passed:

- PHP syntax checks passed for every changed PHP file.
- Pint passed on every changed PHP file.
- Required RefreshDatabase execution applied the full SQLite migration chain,
  including the single MVP-04B forward migration, for every focused suite.
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

## Remediation verification addendum

The remediation implementation is limited to the trusted case resolver and
binding, Member retrieval/current-view contracts, Operator case claim and
decision lifecycle, one forward migration, the Operator view, and focused
regression coverage. The final working-tree diff contains no changes to
manifests, locks, `.agents/context/**`, the predecessor task, or unrelated
modules.

The exact task validator was run with `python3` because `python` is not
installed in the execution environment. PHP syntax, Pint, migration execution
through the SQLite RefreshDatabase path, route/method inspection, targeted
raw-NIK/private-object leakage searches, and `git diff --check` passed.
Browser, full-suite, Docker/MySQL, CI, Composer audit, deployment, production,
and external-system checks were not run under the task contract.

## Final boundary closure addendum — 2026-08-06

Task: `.agents/tasks/mhcs-core-mvp-04b-final-boundary-closure-v1.md`, version 1,
executed with `TARGET="."`. The required baseline and execution HEAD were both
`7074f2eea5e8c7368418dac966f111c4d96ddedd` on `main` in the canonical
`/var/www/mhcs-core` checkout for `git@github.com:Madeena-software/mhcs-core.git`.
The task file was preserved as the only pre-existing untracked input; no reset,
clean, stash, stage, commit, push, deployment, production access, dependency,
or external-system action occurred. No subagents were used.

The initial Codebase Memory artifact was current to `fa23f977...`, not the
execution HEAD: project `mhcs-core`, 4,347 nodes, and 10,493 edges. Direct MCP
architecture/search checks succeeded. The least-expensive valid stale refresh
was `index_repository(mode="fast", persistence=true)`, producing 4,377 nodes
and 10,522 edges. After implementation, the same fast refresh produced 4,382
nodes and 10,591 edges. Final MCP searches found the changed contract,
resolver, Operator service, Member service, and Blade path; inbound traces
confirmed `currentView` callers (`view`, asset retrieval, and matched decision)
and `grantForOperator` callers (the Member retrieval path, controller, and
direct regression test).

The closure changes are deliberately narrow:

- Member `currentView` now returns explicit `available`/`unavailable` evidence
  state. Operator renders unavailable evidence through the existing bounded
  arrival summary, with no protected comparison data, and exposes only
  `mismatch_reported`, `insufficient_evidence`, and cancel while the case is
  open. `matched` and protected asset retrieval require an available view.
- The shared Operator grant boundary independently rechecks the persisted
  authenticatable User, exact open case and booking Member, active portal and
  identity permissions, approved status, current profile or age-appropriate
  KTP/KIA slot, or an explicitly revealed historical profile photo.
- The trusted case resolver now requires both context permissions plus active
  role/profile/site/assignment/arrival/booking state and persisted account
  eligibility (`active`, login enabled, and no mandatory password change).
- Focused regressions cover missing document/profile evidence, safe-page action
  gating, safe decision/site release, direct grant denial for forbidden slots,
  and persisted portal/account revocation. No migration was added or changed.

Ponytail remained active at `full`: existing bounded summaries, contracts,
authorization paths, and storage boundaries were reused; no dependency or new
abstraction was introduced. The implementation and regression test are the
following seven files; the four files named by the task are the only documents
updated:

```text
app/Modules/Member/Application/Contracts/OperatorIdentityVerificationContract.php
app/Modules/Member/Application/Services/MemberVerificationAssetService.php
app/Modules/Member/Application/Services/Mvp04OperatorIdentityVerificationService.php
app/Modules/Operator/Application/Services/OperatorIdentityVerificationService.php
app/Modules/Operator/Infrastructure/TrustedOperatorIdentityVerificationContextResolver.php
resources/views/operator/identity-verification.blade.php
tests/Feature/Operator/Mvp04bIdentityVerificationTest.php
docs/mvp/evidence/mvp-04b-front-desk-identity-verification.md
docs/mvp/roadmap.md
docs/mvp/beta-gap-register.md
docs/mvp/work-package-status.md
```

### Final verification

The task validator passed with:

```text
python3 .agents/skills/agent-task/scripts/validate_task.py .agents/tasks/mhcs-core-mvp-04b-final-boundary-closure-v1.md
Task contract is valid
```

The environment has no `python` executable, so the equivalent required
validator invocation used `python3`. These declared suites passed separately:

```text
vendor/bin/phpunit tests/Feature/Operator/Mvp04bIdentityVerificationTest.php                         16 tests, 84 assertions
vendor/bin/phpunit tests/Feature/Operator/Mvp04OperatorPortalTest.php                                  8 tests, 63 assertions
vendor/bin/phpunit tests/Operator/Mvp04OperatorFoundationTest.php                                     15 tests, 56 assertions
vendor/bin/phpunit tests/Feature/Admin/Mvp04OperatorAdministrationTest.php                              2 tests, 22 assertions
vendor/bin/phpunit tests/Member/Wp04IdentityTest.php --filter 'asset|identifier|verification|grant'    7 tests, 27 assertions
vendor/bin/phpunit tests/Security/Wp02SecurityTest.php                                                 23 tests, 94 assertions
vendor/bin/phpunit tests/Architecture/FoundationArchitectureTest.php                                   6 tests, 1,539 assertions
```

`php -l` passed for every changed PHP file. Pint passed after formatting. The
forward migration check passed with
`DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate:fresh --database=sqlite --no-interaction`,
including both existing MVP-04B migrations. Route inspection showed seven
identity-verification routes. Container inspection resolved the Member
identity contract, trusted resolver contract, and attendance contract to their
intended implementations. Targeted searches found no raw NIK literal,
private-object key, permanent URL, download action, or unbounded reason in the
Operator identity service/view/controller paths. `git diff --check` passed.

Not run by contract: browser/Playwright/Pest, full PHPUnit, complete Work
Package suites, MySQL/Docker conformance, npm or dependency installation,
Composer audit, CI, deployment, production checks, or external integrations.
MVP-04, WP-11, WP-12, WP-17, and the bounded WP-07 status remain partial or
not-started as previously recorded. Production migration approval,
object-storage policy, privacy/retention approval, browser verification, and
broader Operator/clinical/consent/check-in/ticket/queue behavior remain open.
The working tree is ready for owner-controlled review and commit; this task did
not create one.

## Audit identifier sanitizer remediation addendum — 2026-08-07

The published task `mhcs-core-mvp-04b-audit-identifier-sanitizer-remediation-v1.md`
ran with `TARGET="."` against canonical target `/var/www/mhcs-core`. The
reviewed candidate `96f59e9efcf15adf497aaa44e57a8a8f64a071a2` is an ancestor of
execution HEAD `3ca3698ac447dc28afec3b307f8ef54cab30b9fc`; the worktree kept the
published task untracked and unchanged. No commit or push occurred.

The shared sanitizer now accepts a complete canonical UUID as an opaque audit
identifier by using the existing `Illuminate\\Support\\Str::isUuid` validator
before the raw 10–20 digit scalar check. Standalone numeric identifiers still
fail closed. `AuditEvent` target/metadata validation, append-only storage,
identity audit callers, and transaction placement were not otherwise changed.

The deterministic regression and final focused suites passed: WP-02 `24/103`,
MVP-04B identity `16/84`, Operator portal `8/63`, and architecture `6/1,539`
(tests/assertions), all separately with exit status 0. PHP syntax, Pint, and
`git diff --check` passed. The graph was refreshed after the source edit; final
MCP traces verified the sanitizer, `AuditEvent`, Member identity audit paths,
and Operator identity audit helper. The MCP Branch metadata still reports an
older indexed SHA and is retained as a tooling residual.

No later MVP, consent, check-in, ticket, queue, clinical, financial, imaging,
FHIR, deployment, production, dependency, migration, context/specification,
or existing-task work was performed. Browser/full-suite/MySQL/Docker/CI and
production checks remain unrun.
