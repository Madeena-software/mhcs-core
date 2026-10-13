---
title: Operator MySQL Consent Fixture Compatibility
document_id: MHCS-TASK-OPERATOR-MYSQL-CONSENT-FIXTURE-COMPATIBILITY-001
version: 1.0
status: draft
language: en-US
last_updated: 2026-10-13
scope:
  - synthetic Operator date fixture compatibility
  - focused MySQL and SQLite verification
authority_note: Draft for independent Planner/Reviewer validation. Publication does not authorize implementation or clinical behavior changes.
---

# Executable Task

## Task identity

**Task title:** `Operator MySQL Consent Fixture Compatibility`

**Task path:** `.agents/tasks/operator-mysql-consent-fixture-compatibility.md`

**Task contract state:** `Draft`

**Delivery objective / Work Package / MVP:** Bounded Operator synthetic-fixture compatibility maintenance; not broad MySQL validation remediation or Admin Board work.

**Owner / designated planning authority:** Product Owner for the 2030 decision; independent Planner/Reviewer for T5 validation and subsequent V7/A9 review.

## Delivery context

The Product Owner handoff “MHCS — Prepare Operator MySQL Fixture Compatibility Task” selects 2030 instead of 2040 for affected synthetic Operator fixtures. Published [execution evidence](../../docs/mvp/evidence/admin-facility-session-operations-execution.md#baseline-debt-comparison-and-publication-disposition) records MySQL rejection of `2040-01-10` in `member_master_consents.signed_at`, followed by consent/ticket/queue symptoms. The original five-file selection had 58 passes, 7 failures, 2 errors, 463 assertions and zero skips (67 tests), identically on the original board baseline and implementation. These are historical observations, not a fresh run at this task's baseline.

The [Admin Board acceptance](../../docs/mvp/evidence/admin-facility-session-operations-acceptance.md) remains intact, including its bounded development exception. The [historical MySQL task](mysql-validation-baseline-remediation.md) remains unchanged at its older implementation baseline `976863ab50fd8fbf9104b6892c63c72b36550198`; its broad full-suite/bootstrap objective and authorizations are not inherited here.

## Baseline and task revision

**Implementation baseline:** `63808b3672454431b290c17d74f0d24886be3c4e`

Remote `origin/main` was fetched and verified at this SHA on 2026-10-13. It integrates the accepted board slice; this task does not redefine its accepted application revision `1ec07b154d1c7db3cd4fe389f3219091ea8ca595`.

**Task revision:** Exact full Git SHA containing this content, supplied by publication handoff. Independent validation must identify the subsequent exact immutable Validated/Published revision before execution. Baseline and task revision are distinct; do not silently change either.

## Objective

Restore meaningful MySQL coverage by correcting only incompatible synthetic Operator dates to coherent 2030 values, preserving test semantics and classifying remaining failures without changing application behavior.

## Authoritative inputs

### Governing authority

- Direct Product Owner handoff named above: 2030 selection, narrow scope, exclusions and independent validation prerequisite.
- [Repository contract](../AGENTS.md), [delivery protocol](../software-workflow.md), and [task template](_template.md); [project context](../context/project.md) is supporting orientation only.
- Existing schema and source constrain compatibility: `database/migrations/2026_09_04_000002_create_reusable_informed_consent_tables.php` declares `member_master_consents.signed_at` as TIMESTAMP. Verify actual isolated MySQL column types; do not change them.
- Published execution/acceptance records above supply comparison evidence, not authority to alter clinical policy.

### Requirement traceability

AC1–AC5 below trace to the direct handoff's fixture correction, preservation, verification and escalation requirements. This is test maintenance under existing contracts; no new clinical/product requirement or broader Work Package completion is asserted.

## Scope

### In scope

- Inventory affected tests, fixture helpers and all consumers, consent dates, frozen clocks, schedules, arrival/queue/expiry and related timestamps. Initial surfaces are `tests/Operator/Mvp04Fixtures.php` and the five-file selection below; additional test-only surfaces require demonstrated shared-fixture or semantic coupling.
- Replace incompatible 2040 values with coherent 2030 values only where existing schema and scenario semantics justify it. Preserve offsets, relative ordering, durations, expiry, invalid-input intent and time-zone relationships. Update date-valued expected results only to reflect the justified fixture shift, never to relax a behavioral assertion.
- Verify affected consent persistence through existing application paths, rerun the original selection, check SQLite consumers, and compare residual failures with published evidence.
- Diagnose the `arrived` versus `checked_in` mismatch separately using safe synthetic evidence and read-only source analysis; do not correct a state machine under this contract.

### Out of scope

Application behavior/source changes, schema/migrations, dependencies, runtime/CI/deployment configuration, production access, real patient data, deployment/release, global 2040 replacement, test suppression/weakening, Admin Board changes or reopening its acceptance, historical task/evidence rewrites, and the historical broad MySQL full-suite remediation objective.

### Preserved behavior

Consent, check-in, ticket, queue, idempotency, authorization and fail-closed assertions remain intact, including counts, uniqueness, privacy, audit/outbox and concurrency checks. Preserve intentional 2040/post-2038 boundary and DST scenarios and unrelated dates. In particular retain `test_eligible_shift_intake_normalizes_post_2038_explicit_offset_projection_instants` and all Admin Board date/DST fixtures. A shared helper correction must not erase the boundary exercised by its consumers.

## Dependencies and assumptions

- Independent T5 validation and exact immutable Validated/Published revision are prerequisites to any fixture implementation; this Draft is publication-only.
- Available installed test runtime, supported PHP/MySQL drivers, disposable MySQL 8.4 and isolated SQLite fixtures are required; no dependency installation is authorized.
- Published timestamp rejection is an evidence-backed starting hypothesis. Do not assume every unsuccessful test shares that root cause or that 2030 fixes the nonclinical state discrepancy.
- Inspect all consumers before changing a shared fixture. Reconcile intervening baseline changes with Planner/Reviewer; preserve unrelated work.
- Remaining approvals: independent task validation before bounded implementation, independent V7/A9 afterward, and separate authorization for implementation Git publication or any expanded scope. No release authority is granted.

## Required capabilities

Repository read/write, Git inspection, local shell/PHP test execution and owned disposable MySQL 8.4/SQLite environments. Repository intelligence is supporting only; verify conclusions against current source. No production or external clinical-system capability is required.

## Execution constraints

Use established fixtures and test patterns. Keep mutations to justified synthetic test/fixture dates and minimal test-only verification of consent persistence if existing assertions do not establish it. Keep diagnostic harnesses ephemeral, secret-free and synthetic; do not persist application instrumentation. Do not catch/ignore SQL failures, modify SQL mode, skip tests, change expected clinical states, or replace MySQL coverage with SQLite. Isolate subprocess autoload and synthetic key material correctly. Destructive schema setup is permitted only in a verified owned disposable test database; never use ambient application connection settings.

## Acceptance criteria

- [ ] **AC1 — Compatible dates:** A before/after inventory gives each changed scenario, linked date/clock values, schema justification and preserved temporal relationships. Actual MySQL 8.4 consent writes and stored values prove affected 2030 dates persist without SQLSTATE 22007/MySQL 1292.
- [ ] **AC2 — Semantics preserved:** Diff review and relevant regressions show unchanged behavioral assertions/test identities, intentional 2040 boundaries/DST and unrelated scenarios. No unauthorized application, schema, configuration, dependency, Admin Board or historical artifact change.
- [ ] **AC3 — Original coverage:** The complete original five-file 67-test selection is rerun against isolated MySQL 8.4 with zero skips; report exact command, exit code, counts, assertions and every unsuccessful identity. Desired outcome is zero failures/errors; legitimate failures must remain visible.
- [ ] **AC4 — SQLite regressions:** The same selection and all consumers affected by shared-fixture changes are rerun on isolated SQLite. No new failure or skip is introduced. Existing driver-specific skips remain explicitly reported as skips, not passes.
- [ ] **AC5 — Evidence and disposition:** Every residual failure/error is compared by identity, message and cause with the published nine-case table and classified as fixture portability debt, genuine clinical/product defect, authorization/data-integrity defect, schema/architecture issue, environment issue or unresolved. The nonclinical state discrepancy has its own evidence and disposition. Out-of-scope defects/unresolved material blockers return to Planner/Reviewer; classification does not constitute acceptance or permission to suppress a defect.

## Verification requirements

### Required checks

Run this exact selection on isolated MySQL 8.4 using testing connection settings and `--fail-on-skipped`; record server version, image ID/digest, schema setup and relevant actual column types without credentials:

```text
php artisan test tests/Operator/Mvp04OperatorFoundationTest.php tests/Feature/Operator/Mvp04OperatorPortalTest.php tests/Feature/Operator/Mvp04dVerifiedCheckInTicketIssueTest.php tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php tests/Feature/Operator/Mvp04fAtomicBasicExaminationClaimTest.php --compact --fail-on-skipped
```

Rerun the same five files on SQLite (`APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=`), omitting `--fail-on-skipped` only because the published selection includes one MySQL-only concurrency skip. Run additional affected shared-helper consumers on SQLite, including consent and queue regressions as applicable. Retain and exercise the explicit post-2038 projection test in the MySQL selection. Run syntax/Pint checks on changed PHP and `git diff --check`; verify the protected-source/board/schema/config/dependency/historical-artifact diff is empty. No full MySQL gate is mandated or claimed by this narrower task.

### Required evidence

Report governing task SHA, implementation baseline/revision, changed-file list, fixture inventory, executed commands/results, runtime/image identity, actual consent persistence, SQLite skips, failure comparison/classifications, deviations and verification gaps. Capture safe exception/guard evidence for any remaining `arrived`/`checked_in` mismatch with fixture state and relevant source location; never change its expected assertion to obtain a pass. Published baseline results are comparison evidence, not new current-baseline results or CI.

## Stop conditions

Stop correction and return to Planner/Reviewer if the baseline or governing revision is unsafe/ambiguous, isolation or required runtime is unavailable, temporal/boundary semantics cannot be preserved, acceptance needs test weakening, or evidence requires clinical, authorization, data-integrity, schema, architecture, dependency, application or other prohibited changes. Safe bounded diagnosis may finish gathering review evidence; it must not silently become behavioral remediation. Zero failures is a target, not authority to cross these boundaries.

## Side-effect authorization

**Current publication handoff:** Create one dedicated task branch from the verified baseline; author only this Draft document; run focused documentation checks; stage/commit only this path and ordinary-push the branch for independent review. No force push, merge or separate planning PR; keep eventual implementation on the same coherent delivery line where safely reconciled by Planner/Reviewer.

**Future execution, only after independent validation:** Bounded synthetic test/fixture edits and local isolated verification, including owned disposable containers/databases and cleanup of owned temporary resources. Implementation commit/push/PR creation requires an explicit subsequent handoff; this publication authorization does not carry over to application work. No production, real-data, deployment, release, dependency installation, secret disclosure or unrelated mutation.

## Expected terminal outcome

Publication now: **TASK REVIEW REQUIRED** with exact branch, commit, task path, single-file diff and observed documentation checks. No implementation launcher or acceptance claim.

Future execution: **REVIEW REQUIRED** with reviewable fixture changes and truthful evidence, or **PLANNING REQUIRED** on a stop condition. Residual genuine defects are escalated separately; the Executor does not self-accept implementation.

## Review and remediation handling

Independent Planner/Reviewer validates this Draft and publishes an exact governing revision before implementation. Review subsequent work against that revision and the pinned baseline. Bounded fixture-contract remediation uses this stable path and a new immutable governing revision when materially changed; distinct behavior defects require separate planning. Acceptance and release remain separate. Execution evidence belongs in the handoff/review record, not as self-certification in this planning contract.
