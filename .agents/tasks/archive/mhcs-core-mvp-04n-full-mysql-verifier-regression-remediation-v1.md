---
name: mhcs-core-mvp-04n-full-mysql-verifier-regression-remediation
description: Restore the full MySQL verifier by making Operator eligible-shift schedule projections post-2038 portable and removing one stale scaffold assertion.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04N — Full MySQL Verifier Regression Remediation

## Objective

For `$TARGET`, restore the complete disposable-MySQL verification boundary for
the accepted Member/Operator behavior after implementation candidate
`b45d72e94548f8fa4a83975393deff65e1ce4d21` exposed two remaining regressions:

1. preserve valid post-2038 Member schedule instants when the versioned
   `shift_eligible` event is projected into Operator-owned storage; and
2. remove the stale generated feature test that contradicts the already-tested
   intentional `/` to `/login` redirect.

Use one forward Operator schema migration, one focused projection regression,
the existing route authority, and the existing disposable MySQL verifier. Do
not change Member schedule policy, Operator workflow behavior, authentication
routing, or any Image Gateway implementation. This is the single bounded
remediation required before the MVP-04N implementation can be reviewed again;
do not select or implement another MVP capability in this task.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `graphify`
  - `codebase-memory-mcp`
  - `ponytail`
- Ordered model preferences: None.
- Require preferred model: `false`

Graphify, Codebase Memory MCP, and ponytail are mandatory. Keep ponytail at
full level: one forward migration at the owning projection, one focused
regression in the existing Operator foundation suite, deletion of the redundant
scaffold test, and extension of the existing verifier are sufficient. Do not
add a database abstraction, date policy, test runner, container wrapper,
helper framework, or dependency.

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Current planning branch at task publication: `main`.
- Previously accepted implementation baseline:
  `b07aace0f7771162086c9e91ffbb866031241449`.
- Reviewed implementation candidate:
  `b45d72e94548f8fa4a83975393deff65e1ce4d21`, produced by
  `$TARGET/.agents/tasks/mhcs-core-mvp-04n-mysql-cross-database-regression-remediation-v1.md`.
  The current branch may contain later planning-only commits; verify that the
  candidate remains an ancestor of the execution HEAD rather than checking out
  or resetting away owner work.
- The candidate correctly added a forward Member migration for
  `shift_schedules.starts_at`, `shift_schedules.ends_at`, and nullable
  `shift_schedules.eligible_at`; corrected the two database-specific replay
  assertions while preserving ordered projection-list checks; and repaired the
  existing Member portability rollback/reapply probe.
- A fresh run of `$TARGET/deployment/verify-mysql.sh` against its disposable
  MySQL 8.4 container applied all migrations. The exact MVP-04N concurrent
  first-publication probe passed without a skip with 1 test and 9 assertions,
  the Member suite passed with 32 tests and 298 assertions, and the Integration
  suite passed with 8 tests and 49 assertions.
- The same run then failed the full PHP suite: 249 tests were observed, with
  156 passing, 92 errors, one failure, and 2,888 assertions. The 92 errors share
  one product root cause: existing Operator tests legitimately use the 2040
  Member schedule fixture in `$TARGET/tests/Operator/Mvp04Fixtures.php`, but
  MySQL rejects its copied `schedule_starts_at` and `schedule_ends_at` values in
  `operator_eligible_shifts` with `SQLSTATE[22007]` because the historical
  Operator migration still declares those two columns as `TIMESTAMP`.
- Direct source confirms that
  `$TARGET/app/Modules/Operator/Application/Services/EligibleShiftIntakeService.php`
  validates explicit-offset event instants, normalizes them to UTC, and stores
  those Member-owned schedule values as an Operator-owned, versioned projection.
  `$TARGET/app/Modules/Operator/Domain/Models/OperatorEligibleShift.php` casts
  the two values as immutable datetimes. The occurrence field `eligible_at` is
  set from the current clock and is not a copied future schedule instant.
- Direct repository authority requires Member to own shifts and their time
  rules while Operator consumes an idempotent `shift_eligible` event and must
  not take ownership of the Member shift. The correction therefore belongs
  only to the two copied Operator projection columns; it must not mutate the
  Member schedule or broaden Operator ownership.
- The remaining full-suite failure is
  `$TARGET/tests/Feature/ExampleTest.php`, which expects `/` to return 200 but
  observes the intentional 302 redirect. `$TARGET/routes/web.php` defines
  `Route::redirect('/', '/login')`, and
  `$TARGET/tests/Feature/Member/Mvp01MemberAccessTest.php` already asserts that
  exact redirect. A fresh isolated SQLite run reproduced the scaffold failure
  with 1 test and 1 assertion, while the candidate's post-2038 Member schedule
  regression passed with 1 test and 5 assertions. Production routing is not
  defective and must not change.
- The failed MySQL run stopped before its final migration type probes, exited
  unsuccessfully, and its registered trap removed the uniquely named disposable
  container. No persistent database or container was used.
- Graphify was incrementally refreshed for the current branch documentation and
  queried for the remediation task, Member schedule portability, Operator
  eligible-shift projection, Work Packages, MVP decisions, and open gaps. It is
  a discovery aid; six pre-existing graph edges without a `relation` property
  were reported, so every material claim below was checked in the direct files.
- The canonical Codebase Memory MCP project is `var-www-mhcs-core`. Its current
  index includes the candidate migration, the explicit-offset 2040/2050 Member
  regression, `EligibleShiftIntakeService`, Operator fixtures, the root route,
  and both root-route tests. Direct source, migrations, and observed command
  output remain authoritative.
- Related requirements: `MEM-004`, `MEM-046`, `MEM-047`, `MEM-180`,
  `MEM-216`, `MEM-217`, `MEM-218`, `OPR-007`, `OPR-117`, `OPR-129`, and
  `OPR-134`.
- Related Work Packages: WP-06, WP-11, and WP-17. This remediation does not
  complete any Work Package.
- `MVP-GAP-011` remains closed only for the bounded controlled-booking behavior
  if this downstream regression is corrected. Open gaps `MVP-GAP-009`,
  `MVP-GAP-012`, `MVP-GAP-023`, and `MVP-GAP-024` remain open. Do not change
  their status.
- The main workstream owns this Member/Operator remediation. Image Gateway
  storage, processing, MPIPS, AI, publication, and administration remain on
  their separate workstream and are not needed here.

Read completely before implementation:

- `$TARGET/AGENTS.md` and `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`,
  `$TARGET/.agents/skills/fix-bug/SKILL.md`,
  `$TARGET/.agents/skills/graphify/SKILL.md`, and the active ponytail skill
  supplied by the runtime;
- `$TARGET/.agents/context/project.md`,
  `$TARGET/.agents/context/modules/member/project.md`, and
  `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04n-mysql-cross-database-regression-remediation-v1.md`,
  `$TARGET/.agents/tasks/mhcs-core-mvp-04-final-mysql-concurrency-evidence-closure-v1.md`,
  and the current task;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md` and
  `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- all six controlled-beta MVP documents:
  `$TARGET/docs/mvp/README.md`,
  `$TARGET/docs/mvp/beta-scope.md`,
  `$TARGET/docs/mvp/beta-gap-register.md`,
  `$TARGET/docs/mvp/roadmap.md`,
  `$TARGET/docs/mvp/decision-log.md`, and
  `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/database/migrations/2026_08_05_000003_create_mvp03_booking_tables.php`,
  `$TARGET/database/migrations/2026_08_05_000004_create_mvp04_operator_foundation_tables.php`,
  `$TARGET/database/migrations/2026_08_09_000001_make_shift_schedule_instants_mysql_portable.php`,
  and the full current migration order;
- `$TARGET/app/Modules/Member/Application/Services/Mvp03ScheduleService.php`,
  `$TARGET/app/Modules/Member/Domain/Models/ShiftSchedule.php`,
  `$TARGET/app/Modules/Operator/Application/Services/EligibleShiftIntakeService.php`,
  and
  `$TARGET/app/Modules/Operator/Domain/Models/OperatorEligibleShift.php`;
- `$TARGET/tests/Member/Mvp03BookingDomainTest.php`,
  `$TARGET/tests/Operator/Mvp04Fixtures.php`,
  `$TARGET/tests/Operator/Mvp04OperatorFoundationTest.php`,
  `$TARGET/tests/Feature/Member/Mvp01MemberAccessTest.php`,
  `$TARGET/tests/Feature/ExampleTest.php`,
  `$TARGET/tests/Feature/Operator/`,
  `$TARGET/tests/Feature/Admin/`,
  `$TARGET/tests/Security/`, and
  `$TARGET/tests/Architecture/FoundationArchitectureTest.php`; and
- `$TARGET/deployment/verify-mysql.sh`, `$TARGET/phpunit.xml`, and
  `$TARGET/docker-compose.local.yml`.

Confirm exact paths and current behavior from the repository rather than
guessing. Use Graphify first for the task, requirements, Work Packages, gaps,
module ownership, and cross-document relationships. Use Codebase Memory MCP to
trace the eligible-shift intake, projection, root route, and affected test
surface. Open every authoritative source file directly before editing or making
an acceptance claim from either derived index.

## Scope and constraints

Included:

- add exactly one forward migration at
  `$TARGET/database/migrations/2026_08_09_000002_make_operator_eligible_shift_instants_mysql_portable.php`
  that changes only
  `operator_eligible_shifts.schedule_starts_at` and
  `operator_eligible_shifts.schedule_ends_at` to the same smallest
  MySQL/SQLite-compatible datetime type already selected for the Member-owned
  source values. Preserve all existing values, non-nullability, the
  `operator_eligible_site_schedule_index`, model casts, and UTC application
  semantics. Do not rewrite the historical MVP-04 migration;
- implement the migration's narrow, data-preserving `down()`. On MySQL, any
  value outside the old `TIMESTAMP` range must make rollback fail before schema
  mutation rather than truncating, rewriting, deleting, or silently shifting
  data. An empty disposable-database rollback is compatibility evidence, not
  approval to roll back production;
- add only the new migration filename to the existing migration allowlist in
  `$TARGET/tests/Architecture/FoundationArchitectureTest.php`; do not relax,
  restructure, or bypass the architecture assertion;
- add one focused regression to the existing Operator foundation test surface
  proving an explicit-offset post-2038 `shift_eligible` event persists and
  round-trips both schedule instants in UTC on SQLite and MySQL. Preserve the
  existing idempotent replay, changed-payload conflict, stale-version, site,
  authorization, audit, and protected-payload assertions; do not duplicate
  broad downstream workflow fixtures;
- delete `$TARGET/tests/Feature/ExampleTest.php` because it is a generated,
  redundant assertion superseded by the exact redirect coverage in
  `$TARGET/tests/Feature/Member/Mvp01MemberAccessTest.php`. Do not change
  `$TARGET/routes/web.php`, add a welcome page, or add another duplicate root
  route test;
- extend `$TARGET/deployment/verify-mysql.sh` with explicit schema-type and
  rollback/reapply evidence for the new Operator portability migration while
  retaining the existing Member portability probe. Prove the two Operator
  schedule columns are `datetime` after migration, `timestamp` only after a
  safe explicit rollback, and `datetime` after reapplication;
- in the same disposable verifier, add one bounded negative rollback probe that
  persists a synthetic post-2038 Operator projection, proves rollback fails
  closed without changing its data or datetime schema, removes only that
  synthetic row, then completes the empty rollback/reapply probe. Preserve the
  verifier's unique container name, generated credentials, dynamic
  `127.0.0.1` port, dedicated database, MySQL 8.4 image, fail-fast behavior,
  and unconditional trap cleanup;
- preserve every existing transaction, row lock, idempotency key/payload,
  event version, authorization decision, audit/outbox write, privacy filter,
  route, and service behavior. The remediation is schema/test/verifier-only;
- run the focused Operator projection and Member schedule regressions on the
  default SQLite test database, then run the complete existing disposable
  MySQL verifier. The exact MySQL concurrency probe must pass without a skip;
  Member, Integration, and full PHP suites must all pass; both portability
  migration probes must complete; and container cleanup must be observed; and
- run task validation, fresh SQLite migration, full default SQLite PHP suite,
  Composer validation, Pint test mode, changed-PHP syntax, `bash -n`, ShellCheck
  when available, route inspection, schema inspection, privacy inspection,
  accepted-baseline ancestry, `git diff --check`, complete diff review, and
  final worktree inspection.

Excluded:

- changing Member schedule date limits, explicit-offset or UTC behavior,
  overlap, quota, booking, eligibility, or event semantics;
- changing `operator_eligible_shifts.eligible_at`, generic `created_at` or
  `updated_at`, assignment/arrival/history occurrence timestamps, or any other
  unrelated timestamp merely because it uses the historical MySQL type;
- changing `EligibleShiftIntakeService`, `OperatorEligibleShift`, the root
  route, authentication behavior, response status, Member access test, or
  application serialization merely to satisfy a test;
- weakening or removing authorization, transaction, locking, idempotency,
  conflict, stale-event, audit/outbox, privacy, or negative-path coverage;
- rewriting historical migrations, deleting or rewriting production data,
  treating a disposable rollback as production rollback approval, or using a
  persistent/shared/owner database;
- Image Gateway storage, conversion, workers, retries, AI routing, MPIPS
  adapters, publication internals, Image Gateway administration, Doctor
  workflow, a new MVP capability, gap/status closure, documentation completion
  claims, dependency installation, new dependencies, CI/deployment changes,
  commit, or push.

If the complete MySQL or SQLite verifier reveals a different product defect
outside the two observed root causes, stop and report it rather than sweeping
additional behavior into this remediation.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates: Stop as `awaiting-approval` before imposing a date horizon,
  converting any column beyond the two copied Operator schedule instants,
  changing product or route behavior, rewriting a historical migration,
  deleting non-synthetic data, adding a dependency or CI/deployment surface,
  or addressing a newly discovered defect outside the bounded root causes.
  Stop as `blocked` if the existing dependency tree, isolated disposable
  MySQL 8.4 run, PHP MySQL driver, `proc_open`, generated credentials, dynamic
  loopback binding, negative rollback isolation, or unconditional container
  cleanup cannot be proven without installation or unsafe mutation. Do not
  commit or push.

Use `single-pass` with exactly one iteration or `agentic-loop` with a positive finite limit. The task cannot grant permissions or bypass repository approval requirements.

## Execution procedure

1. Resolve `$TARGET`; read every required authority; validate this task and the
   two predecessor tasks; verify repository identity, branch, candidate
   ancestry from the accepted baseline, candidate ancestry to current HEAD,
   and clean-or-owner worktree state. Preserve unrelated work without reset,
   clean, stash, discard, staging, commit, or push.
2. Confirm Graphify and Codebase Memory freshness. Trace the Member schedule to
   versioned eligible-shift projection and the root redirect to its existing
   authoritative test, then inspect the exact source, migrations, and tests
   directly. Record the ponytail decision to change only two projection
   columns, add one regression, delete one redundant test, and reuse the
   existing verifier.
3. Add the one forward Operator projection migration. Preserve values,
   nullability, index, casts, UTC semantics, and a fail-closed MySQL rollback
   guard; do not edit the historical migration or any service/model behavior.
4. Add the focused post-2038 eligible-shift regression to the existing Operator
   foundation suite. Reuse its fixtures and application-service path while
   preserving strict replay/conflict/version/privacy checks. Delete only the
   redundant feature scaffold test.
5. Extend the existing disposable verifier to inspect the two Operator column
   types, exercise the synthetic out-of-range rollback denial without data or
   schema loss, then remove only its synthetic row and prove explicit safe
   rollback/reapplication. Retain and execute the existing Member migration and
   MySQL concurrency probes.
6. Run focused SQLite checks first, then the complete disposable MySQL verifier
   and full default SQLite suite. Inspect the actual driver, test/assertion/skip
   counts, migration state, schema types, denial behavior, synthetic-row
   cleanup, and container cleanup. Stop on any new out-of-scope defect.
7. Run the declared validation, migration, syntax, formatting, Composer, shell,
   route, privacy, ancestry, and diff checks. Inspect the full diff and final
   worktree, then provide the commit-review handoff without committing or
   pushing.

## Acceptance criteria

- [ ] This task and both immutable predecessor tasks validate; the reviewed
      candidate descends from `b07aace0f7771162086c9e91ffbb866031241449`
      and is an ancestor of the current `main` execution HEAD; unrelated owner
      work is preserved.
- [ ] Graphify and Codebase Memory are current enough for the affected docs and
      code, their relevant relationships are recorded, and every material
      requirement or behavior claim is verified from direct repository
      authority.
- [ ] Exactly one forward migration changes only
      `operator_eligible_shifts.schedule_starts_at` and
      `operator_eligible_shifts.schedule_ends_at` from `timestamp` to
      `datetime`, preserving data, non-nullability, index, immutable-datetime
      casts, and UTC semantics on MySQL and SQLite.
- [ ] The migration rollback fails closed before schema mutation when a
      disposable MySQL row contains an out-of-range instant; that row and the
      datetime schema remain unchanged after denial. After deletion of only the
      synthetic row, explicit rollback and reapplication succeed with the
      expected timestamp/datetime types. The existing Member portability probe
      also remains green.
- [ ] A focused application-service regression proves post-2038 explicit-offset
      eligible-shift projection and UTC round-trip on both database drivers,
      while replay, changed-payload, stale-version, transaction, locking,
      authorization, audit/outbox, and protected-data behavior remain unchanged.
- [ ] The redundant generated feature scaffold is absent; the intentional `/`
      to `/login` production route and its existing exact Member access
      regression are unchanged and green.
- [ ] The complete disposable MySQL verifier passes with no test failure or
      error, the exact concurrency probe does not skip, all schema probes
      complete, only generated testing credentials are used, and the uniquely
      named container is proven removed even on failure.
- [ ] The full default SQLite suite and every declared focused/static check pass
      without weakening assertions, introducing a dependency, or exposing
      Member, booking, identity, clinical, credential, database, or container
      data.
- [ ] The final diff contains only the new Operator portability migration, its
      exact architecture allowlist entry, the focused Operator regression,
      deletion of the stale scaffold test, and the bounded verifier extension.
      No product workflow, unrelated timestamp,
      documentation-status, Image Gateway, commit, or push change occurs.
- [ ] The output provides a commit-review handoff with the exact branch and
      baseline/candidate ancestry, changed files and rationale, commands and
      observed results, test/assertion/skip counts, schema and rollback
      evidence, container cleanup, Graphify/Codebase Memory/ponytail evidence,
      residual risks, and an explicit statement that no commit or push occurred.

## Verification

- Method: From `$TARGET`, validate this task and both predecessor tasks; run the focused Operator projection and Member schedule tests plus the exact root-redirect regression on default SQLite; run `php artisan migrate:fresh --env=testing --force`, the full default `php artisan test`, `composer validate --strict`, `vendor/bin/pint --test`, changed-PHP `php -l`, `bash -n deployment/verify-mysql.sh`, ShellCheck when available, route/schema/privacy/ancestry/diff/worktree inspections, and the complete `./deployment/verify-mysql.sh`; inspect MySQL driver, test/assertion/skip counts, both portability type/rollback/reapply probes, the out-of-range rollback denial, synthetic-row removal, and disposable-container cleanup.
- Expected result: All task, focused, full SQLite, static, migration, route, privacy, ancestry, and diff checks pass; MySQL 8.4 completes the concurrency, Member, Integration, full PHP, Member portability, and Operator portability checks with no skip/failure/error; the out-of-range Operator rollback is rejected without data/schema loss, safe rollback/reapplication restores the expected types, the container is removed, only the bounded files change, and no product workflow, unrelated timestamp, documentation-status, Image Gateway, commit, or push change occurs.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or `exhausted`.
- Report the selected runtime/model when verifiable, capabilities, outcome,
  affected files, repository branch and ancestry, Graphify and Codebase Memory
  freshness/actions, ponytail decision, direct-authority checks, verification
  commands and exact observed results, test/assertion/skip counts, schema and
  rollback evidence, synthetic-row and container cleanup, residual risks, and
  manual follow-up.
- Provide a commit-review handoff for the owner. Do not stage, commit, or push.
- Treat exhaustion, a skip, a leaked container, incomplete rollback evidence,
  an unverified patch, or model output alone as unsuccessful.
