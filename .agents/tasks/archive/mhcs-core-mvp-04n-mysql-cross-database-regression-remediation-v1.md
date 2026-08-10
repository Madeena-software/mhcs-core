---
name: mhcs-core-mvp-04n-mysql-cross-database-regression-remediation
description: Restore the existing MySQL verification boundary by fixing shift-schedule date portability, semantic replay assertions, and the stale migration rollback probe.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04N — MySQL Cross-Database Regression Remediation

## Objective

For `$TARGET`, remediate only the MySQL portability regressions observed while
reviewing candidate `4640bbe1dadc834f22e0cd0fed5915163cbef57d`:

1. preserve the already-authorized explicit-offset future schedule behavior for
   dates beyond MySQL `TIMESTAMP`'s 2038 ceiling;
2. compare idempotent JSON-object results semantically without ignoring the
   order of list-valued business data; and
3. make the disposable MySQL verifier test the migration it actually rolls
   back instead of assuming one step removes the historical `members` table.

Use one forward Member schema migration, the existing PHPUnit assertions, and
the existing disposable MySQL script. Do not add a date ceiling, change booking
or protocol behavior, introduce a helper framework or dependency, or select a
new MVP feature.

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
full level: fix the three observed root causes in the existing migration, test,
and verification patterns. Do not add a database abstraction, recursive
canonicalizer, date-range policy, test runner, container wrapper, or dependency.

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Previously accepted baseline:
  `b07aace0f7771162086c9e91ffbb866031241449`.
- Reviewed candidate:
  `4640bbe1dadc834f22e0cd0fed5915163cbef57d`, whose parent is the MVP-04N
  implementation commit `f9ab3605fd40254137d08de154fc761f8da788ab`.
- The focused disposable-MySQL verification at the reviewed candidate passed:
  all migrations applied on MySQL 8.4, the exact concurrent first-publication
  probe passed without skipping with 9 assertions, and the complete MVP-04N
  file passed 7 tests and 88 assertions. The bounded default SQLite regression
  also passed 57 tests and 2,095 assertions with only the separately executed
  MySQL probe skipped.
- A broader MySQL regression run exposed seven `SQLSTATE[22007]` errors when
  existing MVP-03 fixtures inserted legitimate 2040/2050 schedule instants into
  `shift_schedules.starts_at` or `shift_schedules.ends_at`. The owning Member
  service requires an explicit offset, normalizes to UTC, requires a future
  start, and imposes no approved upper date bound; the historical schema uses
  MySQL `TIMESTAMP` for those business instants.
- The same run exposed one assertion failure in
  `Mvp03BookingDomainTest::test_booking_is_atomic_idempotent_and_preserves_snapshots_and_one_active_booking`.
  The first result and replay contained the same JSON object values but MySQL
  returned associative keys in a different order. PHP associative key order is
  not part of the contract, while indexed business lists such as X-ray
  projection order remain significant.
- The reviewed commit already changed the corresponding MVP-04N replay check to
  `assertEqualsCanonicalizing`. That is too permissive for a result containing
  ordered `projection_identifiers`; ordinary semantic equality is sufficient
  to ignore associative-key order while retaining numeric-key/list positions.
- `$TARGET/deployment/verify-mysql.sh` safely creates a unique disposable MySQL
  8.4 container with generated credentials, a dynamic loopback port, a
  dedicated database, and trap-based cleanup. Its final check is stale: after
  all current migrations, `migrate:rollback --step=1` rolls back the X-ray
  protocol migration, not the historical Member identity migration, so the
  script incorrectly expects the `members` table to be absent.
- The reviewed commit modified product migrations and tests even though its
  committed closure task was evidence-only, and the complete repository MySQL
  verifier is not green. The candidate is therefore not accepted; this task is
  the single bounded remediation before another acceptance review.
- Graphify was refreshed for the reviewed tree and contains `MVP-04N Versioned
  X-Ray Protocol Configuration`, `MVP-04 Final MySQL Concurrency Evidence
  Closure`, `Mvp04nXrayProtocolConfigurationTest`, and the beta gap register.
  Codebase Memory is current for the reviewed commit and includes the exact
  migration constraint names, schedule service, protocol publication path, and
  focused tests. Both are discovery aids only; direct source and observed
  command output remain authoritative.
- Related requirements: `MEM-004`, `MEM-046`, `MEM-047`, `MEM-180`, `MEM-216`,
  `MEM-218`, `OPR-077`, and the configuration prerequisite of `OPR-132`.
- Related Work Packages: WP-06, WP-14, and WP-17. This remediation does not
  complete any Work Package.
- `MVP-GAP-011` remains closed only for its bounded controlled-booking behavior
  if this regression is corrected. Open gaps `MVP-GAP-009`, `MVP-GAP-012`,
  `MVP-GAP-023`, and `MVP-GAP-024` remain open. Do not change their status.
- The owner has withdrawn the earlier instruction to force an immediate MVP-05
  transition. This task makes no decision about the MVP selected after its
  acceptance review.

Read completely before implementation:

- `$TARGET/AGENTS.md` and `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`,
  `$TARGET/.agents/skills/fix-bug/SKILL.md`,
  `$TARGET/.agents/skills/graphify/SKILL.md`, and the active ponytail skill
  supplied by the runtime;
- `$TARGET/.agents/context/project.md`,
  `$TARGET/.agents/context/modules/member/project.md`, and
  `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04n-versioned-xray-protocol-configuration-v1.md`
  and
  `$TARGET/.agents/tasks/mhcs-core-mvp-04-final-mysql-concurrency-evidence-closure-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`,
  `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`,
  `$TARGET/docs/mvp/roadmap.md`, `$TARGET/docs/mvp/decision-log.md`,
  `$TARGET/docs/mvp/beta-gap-register.md`, and
  `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/database/migrations/2026_08_05_000003_create_mvp03_booking_tables.php`,
  `$TARGET/database/migrations/2026_08_06_000002_add_mvp04b_identity_active_claim.php`,
  `$TARGET/database/migrations/2026_08_07_000003_create_operator_queue_admissions_table.php`,
  `$TARGET/database/migrations/2026_08_08_000002_create_mvp04j_vital_signs_tables.php`,
  `$TARGET/database/migrations/2026_08_08_000003_allow_one_queue_admission_per_ticket_stage.php`,
  `$TARGET/database/migrations/2026_08_08_000004_create_operator_xray_protocol_mappings.php`,
  and the full current migration order;
- `$TARGET/app/Modules/Member/Application/Services/Mvp03ScheduleService.php`,
  `$TARGET/app/Modules/Member/Application/Services/Mvp03BookingService.php`,
  `$TARGET/app/Modules/Member/Domain/Models/ShiftSchedule.php`, and
  `$TARGET/app/Modules/Operator/Application/Services/OperatorXrayProtocolConfigurationService.php`;
- `$TARGET/tests/Feature/Admin/Mvp03BookingAdministrationTest.php`,
  `$TARGET/tests/Member/Mvp03BookingDomainTest.php`,
  `$TARGET/tests/Feature/Admin/Mvp04nXrayProtocolConfigurationTest.php`,
  `$TARGET/tests/Feature/Admin/Mvp04OperatorAdministrationTest.php`,
  `$TARGET/tests/Security/`, and
  `$TARGET/tests/Architecture/FoundationArchitectureTest.php`; and
- `$TARGET/deployment/verify-mysql.sh`, `$TARGET/phpunit.xml`, and
  `$TARGET/docker-compose.local.yml`.

Confirm exact paths and current behavior from the repository rather than
guessing. Use Graphify first for task, requirement, gap, migration, protocol,
schedule, and test relationships. Use Codebase Memory MCP to trace the schedule
create/update and booking/protocol replay paths, then inspect every affected
source file directly before editing.

## Scope and constraints

Included:

- one forward migration at
  `$TARGET/database/migrations/2026_08_09_000001_make_shift_schedule_instants_mysql_portable.php`
  that changes only the Member-owned `shift_schedules.starts_at`,
  `shift_schedules.ends_at`, and nullable `shift_schedules.eligible_at`
  business-instant columns to the smallest MySQL/SQLite-compatible type that
  stores the already-supported 2040/2050 UTC values. Preserve column
  nullability, indexes, existing values, and UTC application semantics. Do not
  rewrite the historical migration or generic framework timestamps;
- a narrow, data-preserving `down()` for that migration. On MySQL, values that
  cannot fit the old type must cause rollback to fail rather than truncate,
  rewrite, or delete data. A rollback test may use only an empty disposable
  verification database and is not production rollback approval;
- focused proof in the existing MVP-03 schedule/booking tests that explicit-
  offset future schedule instants in 2040 and 2050 persist and round-trip on
  MySQL and SQLite while future, end-after-start, no-overlap, booked-schedule
  immutability, quota, and UTC-normalization behavior remain unchanged;
- replace only the replay assertions that currently encode database-specific
  associative-key order in
  `$TARGET/tests/Member/Mvp03BookingDomainTest.php` and
  `$TARGET/tests/Feature/Admin/Mvp04nXrayProtocolConfigurationTest.php` with
  PHPUnit's existing semantic equality. Keep strict assertions for ordered
  projection lists and other business-significant sequences; retain
  canonicalized comparison only where a value is explicitly an unordered set,
  such as the set of JSON object keys;
- repair `$TARGET/deployment/verify-mysql.sh` so its rollback/reapply probe
  targets the new portability migration explicitly, verifies the affected
  column types or equivalent schema behavior before and after reapplication,
  retains the `members` table, and no longer describes the check as a Member
  identity rollback. Preserve unique container naming, generated secrets,
  dynamic `127.0.0.1` binding, dedicated database, current MySQL 8.4 image,
  fail-fast behavior, and unconditional trap cleanup;
- run the complete existing MySQL verifier, the exact named MVP-04N concurrency
  probe with skipped tests treated as unsuccessful, the complete MVP-04N file,
  and the bounded default SQLite regression used by the preceding closure;
  inspect actual driver, counts, migration state, schema types, and container
  cleanup; and
- Composer validation, Pint test mode, changed-PHP syntax, ShellCheck when
  available, fresh SQLite migration, task validation, ancestry, privacy,
  `git diff --check`, full diff, and final worktree inspection.

Excluded:

- an application-level maximum schedule date, timezone or explicit-offset
  policy change, schedule/booking state change, quota/overlap change, data
  rewrite, deletion, production rollback, or conversion of unrelated business
  or framework timestamps;
- weakening ordered projection assertions, canonicalizing every nested array,
  adding a shared comparison utility, changing idempotency payloads/results,
  changing protocol publication behavior, or editing audit/outbox ordering to
  satisfy a test;
- further edits to the five historical migrations changed by the reviewed
  candidate unless direct fresh/rollback MySQL evidence proves one of those
  exact edits defective and the owner approves expanding this task;
- a persistent database or container, fixed host port, `.env` credential reuse,
  `docker-compose.local.yml` volume, installation, new dependency, CI provider
  configuration, deployment, documentation-status claim, gap closure, new MVP
  feature, commit, or push.

If the complete MySQL verifier reveals a different product defect outside the
three observed root causes, stop and report it rather than sweeping additional
behavior into this task.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates: Stop as `awaiting-approval` before imposing any date horizon,
  changing business behavior, converting an unrelated column, rewriting a
  historical migration, adding a dependency or CI/deployment surface, or
  addressing a newly discovered MySQL defect outside the bounded root causes.
  Stop as `blocked` if an isolated disposable MySQL 8.4 run, PHP MySQL driver,
  `proc_open`, generated credentials, dynamic loopback binding, or unconditional
  cleanup cannot be proven without installation or unsafe mutation. Do not
  commit or push.

Use `single-pass` with exactly one iteration or `agentic-loop` with a positive finite limit. The task cannot grant permissions or bypass repository approval requirements.

## Execution procedure

1. Resolve `$TARGET`; read every required authority; validate this task and the
   two predecessor tasks; verify repository identity, exact reviewed HEAD or
   owner-approved descendant, accepted-baseline ancestry, and clean-or-owner
   worktree state. Preserve unrelated work without reset, clean, stash,
   discard, staging, commit, or push.
2. Confirm Graphify and Codebase Memory freshness, trace the schedule and replay
   paths, inspect direct source and the observed failing test locations, and
   record the ponytail choice: one forward column migration, existing semantic
   assertions, and the existing verifier are sufficient.
3. Add the forward migration for the three shift-schedule business instants.
   Preserve data, indexes, nullability, and UTC semantics; add only focused
   regression coverage needed to prove 2040/2050 persistence and unchanged
   schedule invariants on both database drivers.
4. Correct the two replay assertions so associative JSON-object key order does
   not fail across databases while ordered projection identifiers remain
   strictly order-sensitive. Do not change application serialization merely to
   force a deterministic object-key order.
5. Repair the verifier's migration probe to roll back and reapply the explicit
   portability migration in its empty disposable database, inspect the
   affected schema, and preserve every existing container-safety property.
6. Run the focused checks first, then the complete disposable-MySQL verifier
   and bounded default SQLite regression. Inspect every failure, skip, schema
   state, driver, and cleanup result. Stop on newly discovered out-of-scope
   product defects.
7. Run the declared syntax, formatting, Composer, migration, privacy, task,
   ancestry, and diff checks. Inspect the complete diff and final worktree,
   then provide the commit-review handoff without committing or pushing.

## Acceptance criteria

- [ ] A new forward migration preserves existing schedule data and makes
      `starts_at`, `ends_at`, and nullable `eligible_at` store and round-trip
      authorized 2040/2050 UTC business instants on MySQL 8.4 and SQLite without
      changing schedule policy, nullability, indexes, or unrelated columns.
- [ ] Focused tests prove explicit-offset normalization, future/end ordering,
      overlap rejection, booked-schedule immutability, quota behavior, and
      affected booking behavior remain intact across both database drivers.
- [ ] Booking and protocol exact replays compare JSON objects independent of
      associative-key order, while a reversed ordered projection list still
      fails the relevant assertion and no application payload/result is changed
      merely for test ordering.
- [ ] The MySQL verifier explicitly rolls back and reapplies the portability
      migration on its empty disposable database, observes the expected schema
      transition, keeps `members` present, and retains all existing isolation,
      generated-secret, dynamic-port, fail-fast, and cleanup protections.
- [ ] The exact non-skipped MVP-04N concurrency probe, complete MVP-04N file,
      complete repository MySQL verifier, bounded default SQLite regression,
      fresh migration, syntax, formatting, Composer, task, privacy, ancestry,
      and diff checks all pass with observed evidence.
- [ ] No historical-migration rewrite, date ceiling, unrelated timestamp
      conversion, new helper/dependency, business-scope change, documentation
      status claim, gap closure, MVP selection, deployment, commit, or push is
      introduced.

## Verification

- Method: Validate this and both predecessor tasks; run focused 2040/2050 schedule and semantic replay checks on SQLite and disposable MySQL 8.4; inspect the portability migration's explicit rollback/reapply schema transition; run the exact non-skipped MVP-04N concurrency method, complete MVP-04N file, `deployment/verify-mysql.sh`, and the preceding bounded SQLite regression; run Composer, Pint, changed-PHP syntax, available ShellCheck, fresh SQLite migration, privacy, ancestry, task, and diff checks; inspect container cleanup and the final worktree.
- Expected result: Authorized future schedule instants beyond 2038 persist without policy changes, idempotent JSON-object replay is database-independent while ordered lists remain strict, the verifier tests the actual migration safely, all declared MySQL and SQLite checks pass without skips or leaked containers, and the diff contains only the bounded remediation with no commit or push.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, reviewed and accepted-baseline SHAs, selected runtime/model
  when verifiable, capabilities, outcome, root cause, Graphify and Codebase
  Memory status/actions/freshness, direct authority files, ponytail choice,
  affected files/interfaces, exact verification commands and counts, active
  database drivers, migration/schema transition, container cleanup, final
  worktree state, residual risks, deferred scope, and manual follow-up.
- Treat any unapproved date ceiling; lost or rewritten schedule value;
  order-insensitive projection assertion; persistent/unknown database; leaked
  container; skipped mandatory probe; hidden or unrun check; out-of-scope sweep;
  documentation completion claim; commit; or push as unsuccessful.

## Commit review handoff

Do not commit or push. Report the bounded remediation diff and observed MySQL
and SQLite evidence for owner review. Do not claim candidate acceptance or
choose the next MVP; a later review-plan-create-task cycle must make that
decision from the remediated evidence and the owner's current priorities.
