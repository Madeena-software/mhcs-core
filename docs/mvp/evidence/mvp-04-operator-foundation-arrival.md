# MVP-04 Operator Foundation and Arrival Evidence

Date: 2026-08-06

This is bounded evidence for the MVP-04 Operator foundation and MVP-04A
arrival-boundary closure. It is not a completion or production-readiness claim
for MVP-04.

## Scope delivered

- Shared User authentication with persisted `operator` role and bounded
  Operator permissions.
- Operator-owned profiles, physical sites, site assignments, active-site
  session context, eligible-shift intake, and manual shift assignment.
- Site-scoped attendance query for confirmed, personal, charged bookings with
  safe operational fields.
- Confirmation-bound arrival preparation, confirmed execution, cancellation,
  UTC-normalized occurrence handling, idempotent replay, atomic Member
  `confirmed` to `arrived` transition, audit evidence, and one
  `operator.member-arrived` outbox event.
- Bounded Operator portal and module-owned administration for the implemented
  site, attendance, arrival, and verification-worklist surfaces.

The closure policy is deliberately narrow:

- Only a structurally valid, unexpired, unconsumed confirmation for the active
  Operator profile and current local site is unresolved work.
- Such a confirmation blocks switching away from that site and preserves the
  previous site with a bounded `active_site_blocked` audit failure.
- Cancelled, expired, malformed, stale, or consumed confirmation state does not
  block switching and is cleared where appropriate.
- A recorded arrival is a completed arrival command and remains a later
  verification-worklist entry; it is not a verification claim and does not
  permanently lock the Operator to a site.
- The Member boundary accepts the trusted local-site context only through the
  Operator-owned `TrustedOperatorSiteContextResolver`; actor, role, permission,
  profile, assignment, local site, and supplied stable `operator_site_id` must
  correspond.
- `OperatorArrivalService` exposes `confirm`, `recordConfirmed`, and
  `cancelConfirmation`; the low-level unconfirmed mutation is private.

The preceding MVP-04A evidence did not claim identity verification, consent,
check-in, ticketing, queue claim, clinical workflow, walk-in, cash, FHIR, Image
Gateway, or later MVP behavior. The bounded identity-verification addition is
recorded in `mvp-04b-front-desk-identity-verification.md`.

## Baseline, candidate, and execution state

- `eb12e2a6d533adb19b2cef120919b30fdd28e609` is the initial MVP-04
  implementation commit.
- `2e08eae74e49b0ba54461ba8787a0ec8e0ece062` is the closure baseline and the
  committed preceding remediation. It is an ancestor of the candidate.
- `f49da5991b21b9a13abb435539db1955362ef639` is the committed boundary
  candidate reviewed by this evidence task and is the current `main` HEAD.
- `TARGET="."` resolved canonically to `/var/www/mhcs-core`.
- Expected remote: `git@github.com:Madeena-software/mhcs-core.git`.
- Branch: `main`; current HEAD: `f49da5991b21b9a13abb435539db1955362ef639`.
- No execution commit was created. Documentation edits from this task remain
  in the working tree, as required by the task; no stage, commit, push, reset,
  clean, or stash operation was performed.
- Before documentation edits, the only existing untracked path was the
  user-requested published task file:
  `.agents/tasks/mhcs-core-mvp-04a-tool-evidence-traceability-closure-v1.md`.
  No source or test overlap was present. The generated
  `.codebase-memory/` directory is ignored and was not added to the change.

Task validation passed with the available interpreter:

```text
python3 .agents/skills/agent-task/scripts/validate_task.py .agents/tasks/mhcs-core-mvp-04a-tool-evidence-traceability-closure-v1.md
Task contract is valid: /var/www/mhcs-core/.agents/tasks/mhcs-core-mvp-04a-tool-evidence-traceability-closure-v1.md
```

The `python` command is not installed; this is why the equivalent `python3`
command was used. The task file was not modified.

## Codebase Memory MCP and ponytail evidence

The Codex runtime exposed these Codebase Memory MCP operations and they were
used directly; no grep-only substitute was used for code discovery:

```text
get_architecture(project="/var/www/mhcs-core", ...)
  -> rejected the path identity and reported the available project `mhcs-core`
get_architecture(project="mhcs-core", aspects=["overview","routes","languages"], path=".")
  -> succeeded; initial graph summary: 4,101 nodes, 9,805 edges
get_graph_schema(project="mhcs-core")
  -> succeeded; Branch metadata and graph schema observed
index_repository(repo_path="/var/www/mhcs-core", mode="fast", name="mhcs-core", persistence=false)
  -> indexed; 4,147 nodes, 9,850 edges
index_repository(repo_path="/var/www/mhcs-core", mode="moderate", name="mhcs-core", persistence=false)
  -> indexed; 4,147 nodes, 9,850 edges
index_repository(repo_path="/var/www/mhcs-core", mode="full", name="mhcs-core", persistence=false)
  -> indexed; 4,147 nodes, 9,850 edges
```

Structural searches used before editing included:

```text
search_graph(project="mhcs-core", name_pattern="(PortalController|OperatorArrivalService|OperatorArrivalConfirmationService|OperatorActiveSiteService|OperatorAttendanceService|Mvp04AttendanceService)", limit=100, include_connected=true)
search_graph(project="mhcs-core", name_pattern="(OperatorAttendanceContract|TrustedOperatorSiteContextResolver|OperatorActiveSiteResolver|OperatorServiceProvider)", limit=100, include_connected=true)
search_graph(project="mhcs-core", name_pattern="(confirmArrival|recordArrival|cancelArrival|selectSite|query|resolveBookingForArrival|transitionConfirmedToArrived)", limit=200, include_connected=true)
search_graph(project="mhcs-core", name_pattern="operator", limit=200, include_connected=true)
```

These queries located the Operator portal/controller, confirmation lifecycle,
active-site service, Member attendance implementation and contracts, trusted
resolver implementation and provider binding, bounded routes, and focused
tests. `get_code_snippet` was then used for the exact
`OperatorArrivalService` and `Mvp04AttendanceService` qualified names before
the checks below.

Call-path operations used before editing included outbound and inbound
`trace_path` queries for:

```text
PortalController::confirmArrival
PortalController::recordArrival
OperatorArrivalService::confirm
OperatorArrivalService::recordConfirmed
OperatorActiveSiteService::select
TrustedOperatorSiteContextResolver::matches
Mvp04AttendanceService::query
Mvp04AttendanceService::resolveBookingForArrival
Mvp04AttendanceService::transitionConfirmedToArrived
OperatorArrivalService::recordUnconfirmed (inbound)
```

The observed paths include portal confirmation into confirmed execution,
trusted-site checks at all Member attendance/arrival boundaries, active-site
confirmation inspection before switching, and the private low-level mutation
under confirmed execution. The graph reports `recordUnconfirmed` as exported in
one broad search, but the source snippet and PHP reflection both show it is
private; the source/reflection result is authoritative for visibility.

Impact queries used before editing were:

```text
MATCH (impl)-[r:IMPLEMENTS]->(contract)
WHERE contract.qualified_name =~ '.*(OperatorAttendanceContract|TrustedOperatorSiteContextResolver).*'
RETURN impl.qualified_name, contract.qualified_name
  -> Mvp04AttendanceService implements OperatorAttendanceContract

MATCH (caller)-[r:IMPORTS]->(callee)
WHERE callee.qualified_name =~ '.*(OperatorAttendanceContract|TrustedOperatorSiteContextResolver|OperatorArrivalConfirmationService|OperatorArrivalService|OperatorActiveSiteService).*'
RETURN caller.qualified_name, callee.qualified_name
  -> three trusted-resolver/attendance import rows, including file-node duplicates

MATCH (caller)-[r:CALLS]->(callee)
WHERE callee.qualified_name =~ '.*(OperatorAttendanceContract|TrustedOperatorSiteContextResolver|OperatorArrivalConfirmationService|OperatorArrivalService|OperatorActiveSiteService).*'
RETURN caller.qualified_name, callee.qualified_name
  -> no rows from this provider query; trace_path was used for call-path evidence

MATCH (n)-[r:WRITES]->(target)
WHERE n.file_path =~ '^(app|tests)/.*'
  AND (target.name =~ '.*resolved.*' OR target.qualified_name =~ '.*resolved.*')
RETURN n.qualified_name, target.qualified_name
  -> no rows
```

The final artifact metadata at `.codebase-memory/artifact.json` reports commit
`f49da5991b21b9a13abb435539db1955362ef639`, 4,147 nodes, and 9,850 edges. The
final symbol searches and path traces still resolve the candidate's Operator
and Member symbols. The provider's `Branch` node, however, continued to report
`base_sha` and `head_sha` as `2e08eae74e49b0ba54461ba8787a0ec8e0ece062` after
fast, moderate, and full refreshes, while its canonical root remained
`/var/www/mhcs-core` and Git reported HEAD `f49da...`. This is recorded as a
Codebase Memory metadata discrepancy; the artifact and source graph content
are current, but the Branch metadata must be refreshed by the graph provider
before it can be treated as authoritative branch-head metadata.

The final post-edit graph verification used the exact qualified names returned
by `search_graph`:

```text
search_graph(project="mhcs-core", name_pattern="transitionConfirmedToArrived", label="Method", file_pattern="app/Modules/Member/Application/Services/Mvp04AttendanceService.php", limit=50, include_connected=true)
  -> found Mvp04AttendanceService.transitionConfirmedToArrived
search_graph(project="mhcs-core", name_pattern="select", label="Method", file_pattern="app/Modules/Operator/Application/Services/OperatorActiveSiteService.php", limit=50, include_connected=true)
  -> found OperatorActiveSiteService.select
search_graph(project="mhcs-core", name_pattern="recordConfirmed", limit=50, include_connected=true)
  -> found OperatorArrivalService.recordConfirmed
search_graph(project="mhcs-core", name_pattern="recordUnconfirmed", limit=50, include_connected=true)
  -> found the low-level method node; source/reflection still report it private
trace_path(project="mhcs-core", function_name="mhcs-core.app.Modules.Member.Application.Services.Mvp04AttendanceService.Mvp04AttendanceService.transitionConfirmedToArrived", direction="outbound", depth=3, include_tests=true, mode="calls")
  -> succeeded; observed trusted-context assertion, eligible-booking query, site/window checks, audit/event mutation path
trace_path(project="mhcs-core", function_name="mhcs-core.app.Modules.Operator.Application.Services.OperatorActiveSiteService.OperatorActiveSiteService.select", direction="outbound", depth=3, include_tests=true, mode="calls")
  -> succeeded; observed confirmation inspection, unresolved-work check, authorization, session, and audit path
trace_path(project="mhcs-core", function_name="mhcs-core.app.Modules.Operator.Application.Services.OperatorArrivalService.OperatorArrivalService.recordConfirmed", direction="outbound", depth=3, include_tests=true, mode="calls")
  -> succeeded; observed portal/site authorization, confirmation inspect/store, and bounded arrival path
trace_path(project="mhcs-core", function_name="mhcs-core.app.Modules.Member.Application.Services.Mvp04AttendanceService.Mvp04AttendanceService.resolveBookingForArrival", direction="inbound", depth=3, include_tests=true, mode="calls")
  -> succeeded; callers include confirmed Operator paths, PortalController.confirmArrival, and mismatch tests
```

The provider rejected two initial short-name trace probes and supplied the
exact-name hint; those probes were not treated as evidence, and the qualified
trace calls above were then run successfully.

Ponytail evidence:

- The `ponytail` skill was read and its full runtime mode was active for
  planning, source review, verification, and documentation closure.
- The direct runtime instruction reported `PONYTAIL MODE ACTIVE — level: full`.
- `command -v ponytail` and `type -a ponytail` found no separate CLI binary;
  activation was provided and observed at the Codex runtime/skill layer.
- No subagents were spawned. No non-ponytail execution path was used.

## Focused verification

Each required PHPUnit command was run separately:

| Command | Result |
|---|---:|
| `vendor/bin/phpunit tests/Feature/Operator/Mvp04OperatorPortalTest.php` | 8 tests, 63 assertions passed |
| `vendor/bin/phpunit tests/Operator/Mvp04OperatorFoundationTest.php` | 15 tests, 56 assertions passed |
| `vendor/bin/phpunit tests/Feature/Admin/Mvp04OperatorAdministrationTest.php` | 2 tests, 22 assertions passed |
| `vendor/bin/phpunit tests/Feature/Member/Mvp01MemberAccessTest.php` | 13 tests, 154 assertions passed |
| `vendor/bin/phpunit tests/Feature/Member/Mvp03CatalogueBookingTest.php` | 3 tests, 30 assertions passed |
| `vendor/bin/phpunit tests/Member/Mvp03BookingDomainTest.php` | 14 tests, 180 assertions passed |
| `vendor/bin/phpunit tests/Security/Wp02SecurityTest.php` | 23 tests, 94 assertions passed |
| `vendor/bin/phpunit tests/Architecture/FoundationArchitectureTest.php` | 6 tests, 1,493 assertions passed |

The required run therefore passed 84 tests and 2,092 assertions. No warnings
or skipped tests were reported in these runs.

Static and boundary checks passed:

- `php -l` passed for all nine PHP files changed between `2e08...` and
  `f49...`.
- `vendor/bin/pint --test` passed for those same nine files without modifying
  them.
- `php artisan route:list --path=operator` passed. Public arrival routes are
  limited to `POST operator/arrivals`, `POST operator/arrivals/confirm`, and
  `POST operator/arrivals/cancel`; no public unconfirmed arrival route exists.
- Reflection reported `confirm`, `recordConfirmed`, and
  `cancelConfirmation` as the public arrival-service operations; explicit
  reflection reported `recordUnconfirmed` as private.
- Container binding inspection resolved
  `OperatorAttendanceContract` to
  `App\\Modules\\Member\\Application\\Services\\Mvp04AttendanceService`
  and `TrustedOperatorSiteContextResolver` to
  `App\\Modules\\Operator\\Infrastructure\\TrustedOperatorSiteContextResolver`.
- Search for writes to `operator_arrivals.status = resolved` found only the
  negative assertion in `tests/Operator/Mvp04OperatorFoundationTest.php`; no
  production or test write exists.
- Search for public unconfirmed arrival methods/routes found no public
  `record()` method and no unconfirmed arrival route.
- `git diff --check` passed.

The source and test files were not changed by this closure execution. Only the
four task-permitted documentation files were edited.

## Not run and residual limits

Per the task contract, the following were not run: Pest, Playwright/browser
tests, full PHPUnit, complete Work Package suites, MySQL/Docker conformance,
the npm build, Composer audit, external adapters, CI/release checks,
deployment, and production operations. The focused checks use the repository's
local SQLite test setup and do not replace MySQL or production migration
evidence.

The Codebase Memory Branch metadata discrepancy described above remains a
tooling follow-up. MVP-GAP-009, MVP-GAP-012, and MVP-GAP-024 remain open. MVP-04
remains partial; WP-11, WP-12, and WP-17 remain partially implemented; WP-07
remains not-started except for the exact bounded attendance/arrival contract
consumed here. Queue, check-in, ticketing, consent, identity decisions,
clinical behavior, FHIR, Image Gateway, privacy, deployment, CI, and production
work remain outside this closure.

No dependency, migration, browser-platform, later-MVP, external-adapter, CI,
deployment, production, commit, or push work was added.
