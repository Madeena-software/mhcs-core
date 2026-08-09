---
name: mhcs-core-mvp-04n-mysql-portability-quality-gate-closure
description: Restore the required formatter gate for the completed MVP-04N MySQL portability migrations without changing their behavior.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04N — MySQL Portability Quality-Gate Closure

## Objective

For `$TARGET`, make the completed MySQL portability work eligible for acceptance
by restoring the mandatory Pint quality gate. Apply only the formatter's
semantics-preserving output to the two named forward migrations, then prove the
current default SQLite suite and disposable MySQL verifier remain green. This
task must not add or alter product behavior.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `docker`
  - `codebase-memory-mcp`
  - `graphify`
- Ordered model preferences: None.
- Require preferred model: `false`

## Runtime inputs

- `TARGET` (required): Repository root containing the published MVP-04N portability work.

## Context and evidence

- Work only on branch `main`. The previous branch-local accepted baseline is
  `b07aace0f7771162086c9e91ffbb866031241449`; the reviewed implementation is
  `c6e9b4f90cb3bb3c57dfc83001ff0559212a7aa9`.
- Read the producing task completely before acting:
  `$TARGET/.agents/tasks/mhcs-core-mvp-04n-mysql-conformance-remediation-v1.md`.
  Its evidence describes the forward MySQL `datetime` portability migrations,
  fail-closed rollback guards, 2040 workflow coverage, and the disposable
  verifier. This closure does not reopen that diagnosis or broaden it.
- Direct review of `c6e9b4f` found the full default SQLite suite passing with
  248 tests, 242 passed, 3,797 assertions, and 6 skips; the full disposable
  MySQL verifier also passed. The remaining failed mandatory check is
  `vendor/bin/pint --test`, which reports only:
  - `$TARGET/database/migrations/2026_08_09_000001_make_shift_schedule_instants_mysql_portable.php`
    (`class_definition`, `fully_qualified_strict_types`, `braces_position`);
  - `$TARGET/database/migrations/2026_08_09_000002_make_operator_eligible_shift_instants_mysql_portable.php`
    (the same three fixers).
- The current required formatter failure is a quality-gate/evidence issue, not
  a newly discovered product defect. Its only acceptable correction is the
  exact automated formatter output for those two files. Any behavior-affecting
  diff is outside scope and must stop for owner review.
- Read all repository authority before implementation decisions:
  - `$TARGET/AGENTS.md` and `$TARGET/.agents/AGENTS.md`;
  - `$TARGET/.agents/skills/agent-task/SKILL.md`,
    `$TARGET/.agents/skills/graphify/SKILL.md`, and the active ponytail skill;
  - `$TARGET/.agents/context/project.md`,
    `$TARGET/.agents/context/modules/member/project.md`, and
    `$TARGET/.agents/context/modules/operator/project.md`;
  - `$TARGET/.agents/tasks/mhcs-core-mvp-04n-mysql-cross-database-regression-remediation-v1.md`
    and `$TARGET/.agents/tasks/mhcs-core-mvp-04n-full-mysql-verifier-regression-remediation-v1.md`;
  - `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md` and
    `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`; and
  - all controlled-beta planning sources:
    `$TARGET/docs/mvp/README.md`, `$TARGET/docs/mvp/beta-scope.md`,
    `$TARGET/docs/mvp/beta-gap-register.md`, `$TARGET/docs/mvp/roadmap.md`,
    `$TARGET/docs/mvp/decision-log.md`, and
    `$TARGET/docs/mvp/work-package-status.md`.
- Related requirements are `MEM-068`, `OPR-015`, `OPR-129`, and `OPR-134`.
  Related Work Packages are WP-07, WP-11, WP-12, and WP-17; this task does not
  claim that any requirement range or Work Package is complete.
- `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-023`, and `MVP-GAP-024` remain open.
  Do not close, revise, or reclassify them.
- Graphify was incrementally refreshed for the producing task and its changed
  documentation to 3,144 nodes and 6,526 edges. It remains discovery evidence
  only: it has a pre-existing six-edge missing-`relation` metadata warning.
  Refresh/query it again if relevant documentation changes, then inspect the
  exact repository documents directly before material claims.
- Codebase Memory MCP's canonical project is `var-www-mhcs-core`; it includes
  the reviewed portability migrations, verifier, Member attendance flow, and
  Operator tests. Refresh it only when relevant implementation files change,
  use it for symbol/caller impact, and inspect direct source before decisions.
- Keep ponytail mode active. The smallest valid change is formatter output to
  two files; do not invent a wrapper, helper, migration, configuration knob,
  test harness, or new dependency.

## Scope and constraints

Included:

- run Pint in write mode only for the two listed migration files and retain
  only its semantics-preserving output;
- inspect the exact final source and diff to prove the migration class shape,
  `up`/`down` operations, column names/types/nullability, guards, literals,
  queries, and exception behavior did not change;
- run the mandatory current quality and runtime checks needed to accept the
  reviewed portability work; and
- provide a commit-review handoff without staging, committing, or pushing.

Excluded:

- any manual migration rewrite, new migration, change to a timestamp/datetime
  conversion, rollback guard, test fixture, schema, model, route, verifier,
  requirement/gap status, documentation status, dependency, CI, deployment,
  Image Gateway internals, Doctor workflow, commit, or push;
- converting additional columns, weakening out-of-range rollback denial,
  lowering 2040 fixtures into the legacy range, or altering the accepted
  Member/Operator authorization, transaction, idempotency, concurrency,
  audit/outbox, privacy, decimal, queue, or clinical behavior; and
- treating this quality-gate closure as completion of MVP-04 or permission to
  advance to MVP-05.

The two migrations are forward-only schema artifacts. Do not alter their
schema semantics or attempt a historical-data rewrite. If the formatter changes
anything other than coding style, if a migration has been applied outside the
task's disposable verification environment, or if a required check reveals a
new material defect, stop and report the issue rather than broadening this
task.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates: Stop as `awaiting-approval` before any non-formatter source change, migration semantic change, historical-data action, new migration, dependency/CI/deployment change, requirement or gap-status change, public-interface change, or commit/push. Stop as `blocked` if the formatter, complete default suite, disposable MySQL verifier, Docker cleanup, Graphify, Codebase Memory MCP, or task validator cannot be run safely. Do not commit or push.

## Execution procedure

1. Resolve `$TARGET`; validate this task and the producing task; inspect branch,
   ancestry, and clean-or-owner worktree state without destructive Git actions.
2. Use Graphify for planning relationships and Codebase Memory MCP for the two
   migration symbols and affected verification paths. Open the authoritative
   task, migration, verifier, requirements, Work Package, and controlled-beta
   sources directly before deciding scope.
3. Re-run `vendor/bin/pint --test` and confirm it reports only the two named
   files and listed fixers. Stop if any additional file or failure appears.
4. Run Pint write mode only with the two explicit migration paths. Inspect the
   complete resulting diff, including the class declaration, imports, callback
   signatures, SQL/query literals, `up` and `down` column changes, and rollback
   exception guards. Revert nothing destructively; stop if the diff is not
   formatter-only.
5. Run the current static and runtime evidence set: `vendor/bin/pint --test`,
   `php -l` for both migrations, `composer validate --strict`, `bash -n
   deployment/verify-mysql.sh`, `php artisan test`, and
   `./deployment/verify-mysql.sh`. Inspect test/assertion/skip output, the
   exact MySQL concurrency probe, portability rollback/reapply probes, and
   disposable-container cleanup.
6. Confirm the final diff contains only the two migration files and formatter
   changes, run `git diff --check`, re-read the unchanged task, and produce the
   commit-review handoff. Do not stage, commit, or push.

## Acceptance criteria

- [ ] The task and producing task validate; execution HEAD descends from
      `b07aace0f7771162086c9e91ffbb866031241449` and contains reviewed commit
      `c6e9b4f90cb3bb3c57dfc83001ff0559212a7aa9`.
- [ ] Pint initially confirms only the two named migration files are nonconformant,
      then passes repository-wide after explicit two-file formatting.
- [ ] The final migration diff is formatter-only: no class/method behavior,
      import meaning, schema conversion, query, literal, exception guard,
      nullability, index, foreign-key, UTC, or rollback behavior changed.
- [ ] `php -l`, Composer validation, the full default SQLite suite, and the
      complete disposable MySQL verifier pass; the exact concurrency probe does
      not skip and the verification container is removed.
- [ ] Graphify and Codebase Memory MCP freshness/actions are recorded, every
      material derived claim is checked against direct repository authority,
      and ponytail evidence confirms no unnecessary code was added.
- [ ] No product behavior, test semantics, documentation/gap status,
      dependency, CI/deployment configuration, Image Gateway/Doctor scope,
      staging, commit, or push change occurs.

## Verification

- Method: From `$TARGET`, validate this task and the producing task; record the initial two-file Pint failure; run explicit two-file Pint write mode followed by repository-wide `vendor/bin/pint --test`, `php -l` for both files, `composer validate --strict`, `bash -n deployment/verify-mysql.sh`, `php artisan test`, `./deployment/verify-mysql.sh`, Graphify/Codebase Memory freshness checks, direct migration/verifier/source review, ancestry/worktree inspection, `git diff --check`, final diff review, and disposable-container cleanup inspection.
- Expected result: Only automated formatting in the two named migrations changes; all static, default SQLite, and disposable MySQL checks pass with no failure/error and no skip in the exact concurrency probe; rollback and 2040 portability evidence remains intact; no container leaks; no product, scope, documentation-status, dependency, CI/deployment, commit, or push change occurs.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or `exhausted`.
- Report the selected runtime/model when verifiable, capabilities, branch and
  ancestry, exact formatter diff, Graphify/Codebase Memory/Ponytail evidence,
  direct-authority confirmation, commands and observed test/assertion/skip
  results, MySQL container cleanup, residual risk, and manual follow-up.
- Provide a commit-review handoff covering the two files and quality-gate
  closure. Do not stage, commit, or push.
- Treat a non-formatter diff, a new failing file, a skipped exact concurrency
  probe, leaked container, or unverified runtime check as unsuccessful.
