---
name: mhcs-core-mvp-04n-mysql-conformance-remediation
description: Restore the full MySQL verifier by correcting the remaining bounded Operator cross-driver failures without changing accepted workflow behavior.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04N — Full MySQL Verifier Cross-Driver Conformance Remediation

## Objective

For `$TARGET`, restore the complete disposable MySQL 8.4 verifier after
implementation candidate `ddd492078c16a3caf3fc8dc7e6f1cf8511075840` exposed
three bounded cross-driver failure families in already-accepted MVP-04 behavior:

1. the arrival/identity/consent/check-in/queue chain fails on MySQL and wraps
   its first persistence error as `The arrival could not be recorded`, while
   one portal assertion instead reports `Call to a member function all() on
   array`;
2. the vital-signs positive path observes MySQL's fixed-scale decimal string
   `120.00` where SQLite returns the numerically equivalent `120`; and
3. the X-ray FIFO fixture is rejected because valid 2040 operational instants
   reach historical MySQL `TIMESTAMP` columns, beginning with
   `operator_paper_tickets.issued_at`.

First capture the exact underlying exception and affected schema column for
each persistence failure. Then apply the smallest evidence-backed combination
of forward schema correction and driver-independent assertion correction that
makes the existing behavior pass on SQLite and MySQL. Do not add another MVP
capability, change clinical values or queue semantics, impose a date horizon,
or implement Image Gateway internals.

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
full level: reuse the existing services, tests, migrations, and disposable
verifier; change only columns and assertions proven responsible by observed
MySQL evidence. Do not introduce a date abstraction, decimal library, test
runner, container wrapper, database adapter layer, or dependency.

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Planning branch at task publication: `main`.
- Previously accepted implementation baseline:
  `b07aace0f7771162086c9e91ffbb866031241449`.
- Reviewed candidate:
  `ddd492078c16a3caf3fc8dc7e6f1cf8511075840`, produced by
  `$TARGET/.agents/tasks/mhcs-core-mvp-04n-full-mysql-verifier-regression-remediation-v1.md`.
  Verify that the baseline is an ancestor of the candidate and that the
  candidate remains an ancestor of the execution HEAD. Preserve later owner
  work; do not check out, reset, clean, stash, or discard it.
- The candidate is cleanly scoped to one forward Operator eligible-shift
  portability migration, its architecture allowlist entry, one focused 2040
  projection regression, deletion of the redundant generated root-route test,
  and extension of the existing disposable verifier. Focused SQLite checks
  passed: the projection regression passed with 1 test and 3 assertions, and
  the authoritative root redirect passed with 1 test and 29 assertions.
- The complete default SQLite suite passed with 248 tests, 3,797 assertions,
  and 6 skips. `bash -n deployment/verify-mysql.sh`, the new migration syntax,
  task validation, baseline ancestry, candidate diff check, and disposable
  container cleanup also passed.
- The complete disposable MySQL verifier applied all migrations; passed the
  exact X-ray protocol concurrency probe with 1 test and 9 assertions; passed
  the post-2038 eligible-shift projection with 1 test and 3 assertions; passed
  the Member suite with 32 tests and 298 assertions; and passed Integration
  with 8 tests and 49 assertions. The full PHP suite then failed with 248
  tests observed, 208 passing, 3,512 assertions, 39 errors, and 1 failure.
- The 39 errors include one portal/session error (`all()` called on an array),
  the downstream arrival-based suites reporting the sanitized
  `The arrival could not be recorded` exception, and a direct MySQL
  `SQLSTATE[22007]` rejection of `2040-01-10 03:01:00` for
  `operator_paper_tickets.issued_at`. The single failure is the vital-signs
  database assertion expecting `120` but receiving `120.00`.
- Fresh isolated MySQL reruns reproduced all four representatives separately:
  `$TARGET/tests/Feature/Operator/Mvp04OperatorPortalTest.php`,
  `$TARGET/tests/Feature/Operator/Mvp04bIdentityVerificationTest.php`,
  `$TARGET/tests/Feature/Operator/Mvp04jPrivateVitalSignsCaptureTest.php`, and
  `$TARGET/tests/Feature/Operator/Mvp04lAtomicXrayClaimTest.php`.
- The portal and arrival exceptions are not yet sufficiently diagnosed. The
  generic service exception and the session assertion are symptoms, not
  authority to edit error handling. Obtain the original exception, SQL, and
  exact column before changing schema, session behavior, or tests.
- `$TARGET/app/Modules/Operator/Application/Services/OperatorArrivalService.php`
  wraps unexpected failures only after the existing authorization,
  idempotency, transaction, Member attendance transition, audit, and local
  persistence boundaries. Preserve that sanitized public failure contract.
- `$TARGET/app/Modules/Member/Application/Services/Mvp04VitalSignsService.php`
  intentionally accepts string measurements, calculates BMI, and stores
  decimal columns. The observed `120`/`120.00` mismatch is numerically
  equivalent driver representation; preserve the exact database scale,
  clinical units, missing-reason rules, and BMI calculation. Prefer a
  driver-independent semantic assertion unless a directly exposed product
  contract proves canonical serialization is required.
- `$TARGET/tests/Feature/Operator/Mvp04lAtomicXrayClaimTest.php` deliberately
  passes the same valid 2040 instant through ticket issue, queue readiness,
  history occurrence, and test timestamps. Do not move fixtures below 2038 or
  weaken FIFO/history evidence merely to accommodate historical MySQL types.
- The existing Member and Operator eligible-shift portability migrations prove
  the established forward-migration pattern and fail-closed rollback guard.
  They do not authorize converting unrelated columns. Convert only operational
  instant columns directly reached by the reproduced accepted workflow and
  explicitly modeled as datetimes by repository authority.
- The canonical Codebase Memory project is `var-www-mhcs-core`. Its current
  index includes the candidate, `OperatorArrivalService`, the Member attendance
  and vital-signs services, the X-ray admission fixture path, and the affected
  migrations/tests. Refresh it if the candidate or implementation changes,
  trace each affected caller/callee path, then inspect direct source.
- Graphify was incrementally refreshed to 3,128 nodes and 6,514 edges and
  queried for MVP-04N, MySQL portability, the verifier, requirements, Work
  Packages, roadmap ownership, and gaps. Its integrity check found no dangling,
  missing, collapsed, or self-loop edges. Six pre-existing edges still lack a
  `relation` field, so Graphify remains discovery evidence only and every
  material claim must be checked in direct authority.
- Related requirements: `MEM-068`, `OPR-015..OPR-031`, `OPR-129`, and
  `OPR-134`. This remediation must not claim full satisfaction of any range.
- Related Work Packages: WP-07, WP-11, WP-12, WP-14, and WP-17. None becomes
  complete through this remediation.
- `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-023`, and `MVP-GAP-024` remain open.
  Do not close or reclassify them.
- This is main-workstream Member/Operator remediation. Image Gateway storage,
  processing, MPIPS, AI, publication, retries, and administration remain on
  their separate workstream and are not required.

Read completely before implementation:

- `$TARGET/AGENTS.md` and `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`,
  `$TARGET/.agents/skills/fix-bug/SKILL.md`,
  `$TARGET/.agents/skills/graphify/SKILL.md`, and the active ponytail skill;
- `$TARGET/.agents/context/project.md`,
  `$TARGET/.agents/context/modules/member/project.md`, and
  `$TARGET/.agents/context/modules/operator/project.md`;
- this task, its producing task, and
  `$TARGET/.agents/tasks/mhcs-core-mvp-04n-mysql-cross-database-regression-remediation-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md` and
  `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- all six controlled-beta documents:
  `$TARGET/docs/mvp/README.md`,
  `$TARGET/docs/mvp/beta-scope.md`,
  `$TARGET/docs/mvp/beta-gap-register.md`,
  `$TARGET/docs/mvp/roadmap.md`,
  `$TARGET/docs/mvp/decision-log.md`, and
  `$TARGET/docs/mvp/work-package-status.md`;
- the complete migration order, especially the Member/Operator booking,
  foundation, consent, ticket, queue, vital-signs, claim, and two portability
  migrations under `$TARGET/database/migrations/`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorArrivalService.php`,
  `$TARGET/app/Modules/Member/Application/Services/Mvp04AttendanceService.php`,
  `$TARGET/app/Modules/Member/Application/Services/Mvp04VitalSignsService.php`,
  `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`,
  and every persistence model directly implicated by the reproduced errors;
- `$TARGET/tests/Operator/Mvp04Fixtures.php`,
  `$TARGET/tests/Feature/Operator/Mvp04OperatorPortalTest.php`,
  `$TARGET/tests/Feature/Operator/Mvp04bIdentityVerificationTest.php`,
  `$TARGET/tests/Feature/Operator/Mvp04jPrivateVitalSignsCaptureTest.php`,
  `$TARGET/tests/Feature/Operator/Mvp04lAtomicXrayClaimTest.php`, their sibling
  MVP-04 regression suites, and
  `$TARGET/tests/Architecture/FoundationArchitectureTest.php`; and
- `$TARGET/deployment/verify-mysql.sh`, `$TARGET/phpunit.xml`, and
  `$TARGET/docker-compose.local.yml`.

Use Graphify first for the task, requirements, Work Packages, gaps, ownership,
and cross-document relationships. Use Codebase Memory MCP for code discovery
and caller/callee impact. Open every authoritative source directly before
editing or accepting a derived claim.

## Scope and constraints

Included:

- reproduce the four representative failures in a uniquely named disposable
  MySQL 8.4 database and capture the original exception/SQL/column behind the
  sanitized arrival failure without changing the production exception shown to
  users, logging protected data, or committing a diagnostic bypass;
- determine whether the portal `all()` error is an independent session/test
  harness defect or a consequence of the arrival failure. Correct it only at
  its shared root and retain exact validation/session-error coverage;
- add the smallest forward migration or migrations needed for only the
  operational instant columns proven by the reproduced MySQL failures and
  direct datetime requirements. Preserve values, nullability, defaults,
  indexes, foreign keys, model casts, UTC semantics, and migration order;
- implement narrow, data-preserving rollback behavior. Any value outside the
  old MySQL `TIMESTAMP` range must reject rollback before schema mutation. Do
  not truncate, shift, delete, or rewrite non-synthetic data. Add only exact new
  migration filenames to the existing architecture allowlist;
- retain post-2038 workflow evidence. Add or adjust only focused regressions
  that prove the corrected fields accept and round-trip explicit-offset 2040
  instants on SQLite and MySQL, including a negative out-of-range rollback
  probe with unchanged data/schema after denial;
- correct the vital-signs assertion at the narrowest test boundary so fixed-
  scale MySQL and scale-eliding SQLite representations prove the same numeric
  values without float coercion or reduced precision. Preserve positive,
  missing-reason, invalid-input, BMI, unit, authorization, transaction,
  idempotency, conflict, audit/outbox, and privacy assertions;
- preserve every accepted authorization decision, server-derived actor/site/
  schedule/member binding, row lock, transaction, idempotency payload, replay/
  conflict behavior, queue ownership/FIFO field, audit/outbox write, sanitized
  failure, and privacy filter. Negative tests for suspended/revoked/foreign/
  forged scope and rollback failures must remain green;
- extend the existing verifier only as needed to prove the exact corrected
  schema types, out-of-range rollback denial, safe rollback/reapplication,
  synthetic-row cleanup, and full-suite completion. Preserve its generated
  credentials, unique container, dynamic loopback port, MySQL 8.4 image,
  fail-fast behavior, and unconditional trap cleanup;
- run the four representative MySQL regressions before the complete verifier;
  then run the complete default SQLite suite and complete disposable MySQL
  verifier. The exact concurrency probe must not skip, all focused/Member/
  Integration/full suites must pass, and the container must be absent after
  both success and an intentionally observed failure path; and
- run task validation, fresh SQLite migration, Composer validation, Pint test
  mode, changed-PHP syntax, `bash -n`, ShellCheck when available, route/schema/
  privacy inspection, baseline/candidate ancestry, `git diff --check`, full
  diff review, and final worktree inspection.

Excluded:

- changing accepted arrival, identity, consent, check-in, ticket, queue,
  vital-signs, X-ray claim/call/readiness, protocol, booking, or Member schedule
  semantics;
- moving 2040 fixtures into the legacy `TIMESTAMP` range, imposing a maximum
  schedule date, weakening exact-offset/UTC checks, using floats for decimal
  comparison, or changing clinical units, ranges, BMI calculation, or stored
  decimal scale merely to satisfy one driver;
- converting every `timestamp`, generic audit field, framework table, or
  `created_at`/`updated_at` column speculatively. Each converted column requires
  a reproduced failure plus direct datetime authority and a focused probe;
- exposing raw SQL, exception internals, identifiers, credentials, Member/
  clinical data, or session content through responses, logs, task evidence, or
  verifier output;
- weakening authorization, transaction, locking, idempotency, concurrency,
  audit/outbox, privacy, or negative-path coverage; rewriting historical
  migrations; deleting production data; using a persistent/shared database;
  or treating disposable rollback as production rollback approval;
- changing MVP documentation status, closing gaps, dependency installation,
  new dependencies, CI/deployment behavior, Image Gateway internals, Doctor
  workflow, a new MVP capability, commit, or push.

If diagnosis shows that acceptance requires a broad temporal-schema policy,
an interface/architecture change, or any fourth independent product defect,
stop and report it rather than sweeping it into this remediation.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates: Stop as `awaiting-approval` before changing a public/service
  interface, imposing a date horizon, converting a column without reproduced
  failure and direct datetime authority, changing clinical normalization or
  stored scale, weakening a security/privacy/transaction/idempotency boundary,
  rewriting a historical migration, deleting non-synthetic data, adding a
  dependency, or changing CI/deployment. Stop as `blocked` if an isolated
  disposable MySQL 8.4 run, original-exception capture, generated credentials,
  dynamic loopback binding, negative rollback isolation, or unconditional
  cleanup cannot be proven safely. Do not commit or push.

Use `single-pass` with exactly one iteration or `agentic-loop` with a positive finite limit. The task cannot grant permissions or bypass repository approval requirements.

## Execution procedure

1. Resolve `$TARGET`; read every required authority; validate this task and its
   two immutable predecessor tasks; verify repository identity, branch,
   ancestry, and clean-or-owner worktree state without destructive Git actions.
2. Refresh/query Graphify and Codebase Memory as needed. Trace the arrival,
   Member attendance, vital-signs, ticket/queue, and verifier paths, then
   inspect every direct source, schema, and test before changing anything.
3. Reproduce the four representative failures on isolated MySQL. Capture the
   original exception and exact schema column behind every sanitized failure;
   classify the portal/session error as independent or consequential.
4. Add only the evidence-backed forward schema correction and fail-closed
   rollback guards. Add focused 2040 and rollback regressions and exact
   architecture allowlist entries; preserve historical migrations.
5. Make the smallest driver-independent vital-signs assertion correction and,
   only if independently proven, the smallest shared portal/session test-harness
   correction. Do not change accepted product behavior or weaken assertions.
6. Run representative MySQL checks, neighboring MVP-04 and security/
   architecture checks, the complete default SQLite suite, and the complete
   disposable MySQL verifier. Inspect types, values, counts, skips, rollback
   denial/reapply evidence, synthetic cleanup, and container cleanup.
7. Run all declared static, migration, route, privacy, ancestry, and diff
   checks. Review the complete final diff and produce the commit-review handoff
   without staging, committing, or pushing.

## Acceptance criteria

- [ ] This task and its two predecessor tasks validate; the reviewed candidate
      descends from `b07aace0f7771162086c9e91ffbb866031241449` and remains an
      ancestor of the execution HEAD; unrelated owner work is preserved.
- [ ] Graphify and Codebase Memory are current enough for the affected docs and
      code, relevant paths are recorded, and every material claim is verified
      from direct repository authority.
- [ ] Each of the four representative MySQL failures has an evidence-backed
      root cause. No committed diagnostic bypass exposes internal exceptions or
      protected values, and the public sanitized failure contract is unchanged.
- [ ] Only operational instant columns proven by a reproduced failure and
      direct datetime authority are changed by forward migration. Values,
      nullability, defaults, indexes, foreign keys, casts, and UTC semantics are
      preserved; no historical migration or speculative timestamp is changed.
- [ ] Out-of-range rollback is rejected before mutation with row data and
      datetime schema unchanged; deletion of only synthetic probe rows permits
      explicit safe rollback/reapplication with expected types.
- [ ] Post-2038 arrival and X-ray queue/ticket/history paths pass on SQLite and
      MySQL without moving fixtures below 2038 or changing queue, authorization,
      transaction, locking, idempotency, concurrency, audit/outbox, or privacy
      behavior.
- [ ] Vital-signs assertions compare exact decimal values without float
      coercion and pass for SQLite's scale-eliding and MySQL's fixed-scale
      representation while stored scale, units, BMI, and validation remain
      unchanged.
- [ ] Portal validation/session-error coverage passes on both drivers without
      weakening the explicit-offset rejection or changing user-visible error
      handling. All existing denial, conflict, replay, rollback, and
      cross-scope negative tests remain green.
- [ ] The complete default SQLite suite and complete disposable MySQL verifier
      pass with no failure/error; the exact concurrency probe does not skip;
      all schema/rollback probes complete; and the uniquely named container is
      removed on success and failure.
- [ ] The final diff is limited to evidence-backed migration/allowlist,
      verifier, and affected regression-test changes. It contains no new MVP
      capability, broad temporal refactor, Image Gateway, documentation-status,
      dependency, CI/deployment, commit, or push change.
- [ ] The output provides a commit-review handoff with branch/ancestry, changed
      files and rationale, original root causes, commands and observed counts,
      schema/rollback evidence, container cleanup, Graphify/Codebase Memory/
      ponytail evidence, residual risks, and an explicit no-commit/no-push
      statement.

## Verification

- Method: From `$TARGET`, validate this task and its two predecessors; reproduce the four representative failures on an isolated MySQL 8.4 database; run their corrected focused tests plus neighboring MVP-04, security, and architecture suites on SQLite and MySQL; run `php artisan migrate:fresh --env=testing --force`, the full default `php artisan test`, `composer validate --strict`, `vendor/bin/pint --test`, changed-PHP `php -l`, `bash -n deployment/verify-mysql.sh`, ShellCheck when available, route/schema/privacy/ancestry/diff/worktree inspections, and the complete `./deployment/verify-mysql.sh`; inspect exact root exceptions, types, values, rollback denial/reapplication, test/assertion/skip counts, synthetic cleanup, and container cleanup.
- Expected result: All task, focused, neighboring, full SQLite, static, migration, route, privacy, ancestry, and diff checks pass; MySQL 8.4 completes the exact concurrency, post-2038 projection, Member, Integration, representative Operator, full PHP, and portability probes without skip/failure/error; only evidence-backed operational datetime fields and driver-independent assertions change; rollback denial preserves data/schema; the container is removed; and no accepted behavior, speculative timestamp, Image Gateway, documentation-status, dependency, CI/deployment, commit, or push change occurs.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or `exhausted`.
- Report the selected runtime/model when verifiable, capabilities, outcome,
  affected files/interfaces, branch and ancestry, original root causes,
  Graphify and Codebase Memory freshness/actions, ponytail decision, direct
  authority, commands and exact observed results, test/assertion/skip counts,
  schema and rollback evidence, synthetic/container cleanup, residual risks,
  and manual follow-up.
- Provide a commit-review handoff for the owner. Do not stage, commit, or push.
- Treat exhaustion, any skip in the exact concurrency probe, a leaked
  container, incomplete root-cause or rollback evidence, an unverified patch,
  or model output alone as unsuccessful.
