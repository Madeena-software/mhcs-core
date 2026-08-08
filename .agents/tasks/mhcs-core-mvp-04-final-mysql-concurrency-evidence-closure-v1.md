---
name: mhcs-core-mvp-04-final-mysql-concurrency-evidence-closure
description: Close the final planned MVP-04 acceptance gap by proving the existing versioned X-ray protocol publication race on disposable MySQL without changing implementation or tests.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04 — Final MySQL Concurrency Evidence Closure

## Objective

For `$TARGET`, close the only identified acceptance-evidence gap for candidate
`f9ab3605fd40254137d08de154fc761f8da788ab`: run the existing MVP-04N
concurrent first-publication probe against an isolated, disposable MySQL 8.4
database with `proc_open` available and prove that the test passes rather than
skips. Re-run the bounded MVP-04N regression checks, inspect the observed race
result, and leave all tracked repository files unchanged.

This is the final planned MVP-04 task. Success accepts the existing MVP-04N
candidate without another product change; the next task-selection cycle must
begin at MVP-05 Image Gateway Study Intake and Correlation. This prioritization
does not claim that every broader MVP-04 Work Package or gap is complete.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `shell`
  - `graphify`
  - `codebase-memory-mcp`
  - `ponytail`
- Ordered model preferences: None.
- Require preferred model: `false`

Graphify, Codebase Memory MCP, and ponytail are mandatory. Keep ponytail at
full level: reuse the existing test, disposable MySQL container pattern,
Composer tree, application configuration, and verification commands. Do not
add a runner, helper, dependency, retry wrapper, fixture, assertion, or product
change for an evidence-only outcome.

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Previously accepted baseline:
  `b07aace0f7771162086c9e91ffbb866031241449`.
- Closure candidate:
  `f9ab3605fd40254137d08de154fc761f8da788ab`, produced by
  `$TARGET/.agents/tasks/mhcs-core-mvp-04n-versioned-xray-protocol-configuration-v1.md`.
- Direct review found the candidate implements the bounded Operator-owned
  versioned protocol mapping, Member scalar-query boundary, exact read/manage
  claims, transactional optimistic publication, idempotency, immutable
  history, audit/outbox evidence, Filament administration, and negative
  coverage required by MVP-04N. No confirmed product defect was found.
- The focused review run passed 57 tests and 2,095 assertions, with exactly one
  skipped test:
  `test_mysql_concurrent_first_publications_leave_one_current_version`. The
  normal `phpunit.xml` uses in-memory SQLite, while that test explicitly
  requires MySQL and `proc_open`; therefore the candidate is not yet accepted
  as concurrency-verified.
- `$TARGET/deployment/verify-mysql.sh` is the approved local safety pattern: it
  creates a uniquely named MySQL 8.4 container with generated credentials and
  a dynamic loopback port, uses a dedicated `mhcs_verification` database, and
  removes the container through an exit trap. Reuse its isolation and cleanup
  pattern without editing it. Do not use the persistent local Compose volume
  or any owner, shared, staging, or production database.
- The refreshed Graphify graph includes the MVP-04N task and focused test; the
  current Codebase Memory index includes
  `OperatorXrayProtocolConfigurationService::publish`, its Filament callers,
  Member query contract, shared stores, and focused tests. Derived graphs are
  discovery aids only; direct source and observed command output remain
  authoritative.
- Related requirements: `OPR-077`, the configuration prerequisite of
  `OPR-132`, and supporting `OPR-110`, `OPR-115`, `OPR-116`, `OPR-117`,
  `OPR-129`, and `OPR-134`.
- Related Work Packages: WP-14 and WP-17. Related open gaps remain
  `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and `MVP-GAP-024`.
- After successful closure, MVP-05 is governed by the study-intake,
  examination-correlation, duplicate/mismatch, status, failure, retry, and
  operational-administration boundary in `$TARGET/docs/mvp/roadmap.md` and
  open Image Gateway gaps including `MVP-GAP-013` and `MVP-GAP-025`. Do not
  implement or plan that slice in this task.

Read completely before verification:

- `$TARGET/AGENTS.md` and `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`,
  `$TARGET/.agents/skills/review-code/SKILL.md`, and
  `$TARGET/.agents/skills/graphify/SKILL.md`;
- `$TARGET/.agents/context/project.md`,
  `$TARGET/.agents/context/modules/member/project.md`, and
  `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04n-versioned-xray-protocol-configuration-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`,
  `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`,
  `$TARGET/docs/mvp/roadmap.md`, `$TARGET/docs/mvp/decision-log.md`,
  `$TARGET/docs/mvp/beta-gap-register.md`, and
  `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/phpunit.xml`, `$TARGET/deployment/verify-mysql.sh`, and
  `$TARGET/docker-compose.local.yml`;
- `$TARGET/app/Modules/Member/Application/Contracts/OperatorServiceOfferingQuery.php`,
  `$TARGET/app/Modules/Member/Application/Services/Mvp04OperatorServiceOfferingQuery.php`,
  `$TARGET/app/Modules/Operator/Application/Services/OperatorXrayProtocolConfigurationService.php`,
  `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`,
  and the two Operator X-ray protocol models;
- `$TARGET/database/migrations/2026_08_08_000004_create_operator_xray_protocol_mappings.php`;
- `$TARGET/tests/Feature/Admin/Mvp04nXrayProtocolConfigurationTest.php`,
  `$TARGET/tests/Feature/Admin/Mvp04OperatorAdministrationTest.php`,
  `$TARGET/tests/Feature/Admin/Mvp03BookingAdministrationTest.php`,
  `$TARGET/tests/Member/Mvp03BookingDomainTest.php`,
  `$TARGET/tests/Security/`, and
  `$TARGET/tests/Architecture/FoundationArchitectureTest.php`.

## Scope and constraints

Included:

- validating this task and the immutable MVP-04N producing task;
- proving the exact candidate SHA, accepted-baseline ancestry, and clean or
  owner-change worktree state before any database command;
- confirming Graphify and Codebase Memory freshness and tracing the existing
  publish path without making requirement claims from derived data alone;
- proving Docker, MySQL 8.4, the PHP MySQL driver, and `proc_open` are available;
- creating only a uniquely named, disposable MySQL container with generated
  credentials, a dynamic `127.0.0.1` port, a dedicated verification database,
  and unconditional trap-based cleanup, following
  `$TARGET/deployment/verify-mysql.sh`;
- running the existing exact concurrency test with a fail-on-skipped option and
  visible test name, then the complete MVP-04N file against that same disposable
  MySQL database;
- re-running the existing bounded SQLite regression command for MVP-04N,
  Operator/Member administration, Member booking, security, and architecture,
  plus Composer validation, Pint test mode, changed-PHP syntax, fresh in-memory
  migration, route listing, privacy inspection, ancestry, and diff checks; and
- reporting exact observed commands, pass/fail/skip counts, database driver,
  race outcomes, cleanup result, graph status, and final worktree state.

Excluded:

- any product, migration, route, model, service, contract, Filament, seeder,
  test, fixture, dependency, configuration, deployment script, documentation,
  context, task, lockfile, or generated tracked-file change;
- altering or copying a tracked verification script, weakening a skip guard or
  assertion, adding a test harness, installing Docker/MySQL/PHP extensions or
  dependencies, changing `phpunit.xml`, or accepting prior output as current
  evidence;
- using `.env` database credentials, `docker-compose.local.yml`, its persistent
  volume, a fixed host port, an existing container, or any database not created
  and destroyed within this verification run;
- real clinical mappings, X-ray start/snapshot, Encounter/FHIR, capture/NPZ,
  Image Gateway, Member mutation, later queue behavior, MVP-05 implementation
  or planning, commit, or push.

The task is evidence-only. If the existing test fails, skips, times out, leaks
a container, or exposes a real defect, report `failed` or `blocked` and stop.
Do not repair the implementation or test in this task.

## Execution policy

- Mode: `single-pass`
- Maximum iterations: `1`
- Approval gates: Stop as `awaiting-approval` if the candidate is not the exact
  reviewed SHA, ancestry is absent, owner work overlaps required files, or the
  only available database/container action could touch non-disposable state.
  Stop as `blocked` if Docker, MySQL 8.4, the PHP MySQL driver, `proc_open`, the
  existing vendor tree, dynamic loopback binding, generated credentials, or
  unconditional cleanup cannot be proven without installation or mutation.
  Do not commit or push.

Use `single-pass` with exactly one iteration or `agentic-loop` with a positive finite limit. The task cannot grant permissions or bypass repository approval requirements.

## Execution procedure

1. Resolve `$TARGET`; read every required authority; verify repository identity,
   exact candidate HEAD, baseline ancestry, worktree state, required tools, and
   validation of this task plus the MVP-04N task. Preserve all owner work.
2. Confirm ponytail full mode and that the existing probe plus disposable
   MySQL pattern are sufficient; no repository change is justified.
3. Inspect Graphify and Codebase Memory. Reuse the current graphs when they
   contain the exact candidate symbols; refresh derived data only if required
   by their declared freshness rules, and record any ignored derived changes.
4. Inspect `deployment/verify-mysql.sh` and prove every safety property before
   starting Docker. Create an equivalent isolated container lifecycle without
   editing repository files. Export only generated testing variables to the
   verification processes and register cleanup before the container starts.
5. In the disposable MySQL environment, prove the active Laravel driver is
   `mysql`, then run the exact existing concurrency method by name with testdox
   or equivalent visible naming and fail-on-skipped behavior. Run the complete
   MVP-04N test file in the same environment with skipped tests treated as
   unsuccessful.
6. Inspect observed output. The race must produce exactly one `success:1` and
   one `xray_protocol_conflict`, with one current mapping, one immutable version,
   and one handled idempotency record; the complete focused file must also
   prove single audit/outbox publication, atomic failure rollback, authorization,
   replay, stale-version, module, privacy, and admin boundaries.
7. Destroy the verification container through the registered trap and prove it
   no longer exists. Never retain or report generated credentials.
8. Run the declared default SQLite regression/static checks and inspect actual
   output. Confirm the tracked worktree remains unchanged and the candidate
   diff still passes `git diff --check`.
9. Report the outcome without editing, staging, committing, or pushing. On
   success, identify `f9ab3605fd40254137d08de154fc761f8da788ab` as ready for
   owner acceptance and state that the next task-selection cycle begins at
   MVP-05. On any skip, failure, unsafe state, or cleanup uncertainty, do not
   accept the candidate.

## Acceptance criteria

- [ ] Both immutable tasks validate; HEAD is exactly
      `f9ab3605fd40254137d08de154fc761f8da788ab`, descends from
      `b07aace0f7771162086c9e91ffbb866031241449`, and has no overlapping owner
      changes.
- [ ] Graphify and Codebase Memory confirm the current publish path and focused
      test while direct source remains the acceptance authority.
- [ ] A uniquely named disposable MySQL 8.4 container uses generated credentials,
      a dynamic loopback port, a dedicated verification database, and proven
      unconditional cleanup; no persistent or owner database is touched.
- [ ] The existing named concurrency test runs on the MySQL driver with
      `proc_open`, is not skipped, and passes with exactly one first-publication
      success, one conflict, one current mapping, one immutable version, and one
      handled idempotency record.
- [ ] The complete MVP-04N file passes on the same disposable MySQL database,
      and the bounded default SQLite regression, migration, route, privacy,
      syntax, Pint, Composer, ancestry, and diff checks pass with observed
      output.
- [ ] The disposable container is absent after verification, generated secrets
      are not printed or persisted, and no tracked file is changed, staged,
      committed, or pushed.
- [ ] Success accepts only the existing MVP-04N candidate and hands the next
      task-selection cycle to MVP-05; it does not claim broader MVP-04 gaps,
      production readiness, clinical conformance, or MVP-05 behavior complete.

## Verification

- Method: Validate both tasks and candidate ancestry; verify current Graphify/Codebase Memory paths; using the isolation and cleanup pattern from `deployment/verify-mysql.sh`, run the exact existing MySQL concurrency method with visible naming and fail-on-skipped behavior, then the complete MVP-04N file; destroy and verify absence of the container; run the bounded default SQLite regression, Composer, Pint, PHP syntax, fresh in-memory migration, route, privacy, ancestry, and diff checks; inspect the final tracked worktree.
- Expected result: The existing concurrency probe executes on disposable MySQL 8.4 without skipping and observes one successful version-1 publication plus one conflict with one durable mapping/version/handled operation, all focused and regression boundaries pass, the container is removed, the tracked worktree stays unchanged, candidate `f9ab3605fd40254137d08de154fc761f8da788ab` is ready for owner acceptance, and MVP-05 is next.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, accepted baseline, candidate SHA, selected runtime/model when
  verifiable, capabilities, exact commands and counts, confirmed database
  driver, named-probe outcome, container lifecycle and cleanup evidence,
  Graphify and Codebase Memory status, ponytail decision, final worktree state,
  unrun checks, residual risks, acceptance readiness, and manual follow-up.
- Treat a skipped probe, non-MySQL driver, unavailable `proc_open`, fixed or
  persistent database, leaked container, hidden failure, repository change,
  unrun mandatory check, or claimed rather than observed evidence as
  unsuccessful.

## Commit review handoff

Do not commit or push. No tracked documentation or product change is expected.
After all checks succeed, report the existing candidate
`f9ab3605fd40254137d08de154fc761f8da788ab` as ready for owner acceptance.
The next review-plan-create-task cycle must select one bounded MVP-05 Image
Gateway Study Intake and Correlation task. Do not create another MVP-04 task
unless a material defect is observed and the owner explicitly reopens MVP-04.
