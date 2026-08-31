---
title: MySQL Validation Baseline Remediation
document_id: MHCS-TASK-MYSQL-VALIDATION-BASELINE-REMEDIATION-001
version: 1.1
status: validated-published
language: en-US
last_updated: 2026-08-31
scope:
  - deterministic MySQL compatibility verification
  - pre-existing MySQL test and fixture validation debt
authority_note: This task authorizes bounded MySQL validation remediation only. It does not authorize schema changes, product changes, production access, or weakening any validation gate.
---

# Executable Task

## Task identity

**Task title:** `MySQL Validation Baseline Remediation`

**Task path:** `.agents/tasks/mysql-validation-baseline-remediation.md`

**Task contract state:** `Validated/Published upon immutable publication of this exact content.`

**Delivery objective / Work Package / MVP:** `Restore deterministic, meaningful MySQL compatibility verification without suppressing legitimate defects.`

**Owner / designated planning authority:** `Planner/Reviewer under the handoff dated 2026-08-31`

## Delivery context

After `.agents/tasks/pr-validation-stabilization.md` was accepted at `768177b7c40f35a47e3f0d4a0bcfdfa726a5acb2`, the MySQL gate became reachable. Run `33377188823` executed `bash deployment/verify-mysql.sh` against `mysql:8.4`, completed migrations, and failed in the final verification with 21 failures, 1 pass, 482 warnings, 7,583 assertions, and exit code 2. The baseline run did not reach this gate because complete PHP verification failed first.

Observed families include `Mvp04dVerifiedCheckInTicketIssueTest`, `Mvp04kBasicExaminationCompletionTest`, `Mvp04pPublicQueueDisplayTest`, `Mvp14ImageGatewayIntegrationTest`, `PrestigeClinicSeederTest`, `NonclinicalValidationAccountProvisioningTest`, and `NonclinicalValidationContextProvisioningTest`.

## Baseline and task revision

**Implementation baseline:** `976863ab50fd8fbf9104b6892c63c72b36550198`

**Original implementation baseline:** `768177b7c40f35a47e3f0d4a0bcfdfa726a5acb2`

**Previous governing task revision:** `4b1e436df6385ce74c56fb92a75579efcc1f9da9`

**Task revision:** `The full SHA of the normal commit containing this exact task content.`

## Authoritative inputs

- Planner/Reviewer handoff dated 2026-08-31 and diagnostic run `33377188823`.
- `.agents/AGENTS.md`, `.agents/software-workflow.md`, and `.agents/context/project.md`.
- `.agents/tasks/pr-validation-stabilization.md` @ `02fa715785b7aa3941525d4ca3c3b5e3802d3528`.
- `deployment/verify-mysql.sh`, current migrations/schema, and affected tests.

## Scope

### In scope

- Establish reproducible MySQL image/runtime identity and determine whether CI digest `sha256:b3b90af2…` versus local digest `sha256:da906917…` materially changes results.
- Trace and correct root causes in affected MySQL verification and test/bootstrap paths.
- Make SQLite-only setup such as `PRAGMA defer_foreign_keys = ON` driver-aware without swallowing SQL errors.
- Correct invalid test/fixture data when it violates an authoritative existing schema contract, including over-length `display_reference` values.
- Re-evaluate authorization/provisioning failures only after upstream bootstrap and fixture failures are corrected.
- Preserve real MySQL execution, default PHPUnit coverage, and the blocking MySQL gate.

### Out of scope

- Deleting, skipping, weakening, or suppressing tests; `continue-on-error`; replacing MySQL with SQLite; ignoring SQL errors; or changing assertions solely for green CI.
- Schema/migration changes unless Planner/Reviewer separately authorizes them after evidence shows the schema violates product requirements.
- DICOM batch runtime, MPIPS, storage architecture, production configuration, deployment, release, credentials, or unrelated product behavior.

### Preserved behavior

- `deployment/verify-mysql.sh` remains a real MySQL 8.4 compatibility gate and remains blocking.
- Default SQLite/PHPUnit verification remains active.
- Authoritative schema constraints and application behavior are not weakened to accommodate broken fixtures.

## Dependencies, assumptions, and approvals

### Dependencies

- Baseline remains `768177b7c40f35a47e3f0d4a0bcfdfa726a5acb2`.
- Task B, `deployment-validator-precision.md`, is an independent sibling task and is neither a prerequisite nor part of this task.

### Approved assumptions

- The 21 failures are pre-existing validation debt newly exposed by workflow stabilization; this is not proof that every exact failure occurred at the baseline because the baseline MySQL gate was skipped.
- MySQL image tag/digest divergence is an investigation target, not an assumed root cause.

## Remediation

**Review basis:** `976863ab50fd8fbf9104b6892c63c72b36550198`

### Accepted partial corrections

Preserve unless contrary evidence is discovered:

- SQLite-only PRAGMA is driver-guarded.
- Over-length test fixture display references are corrected against the existing schema contract.
- Associative JSON metadata comparisons no longer rely on object-key order.
- Validation-context ownership uses canonical `App\\Models\\User` / `User::class` representation rather than a non-portable escaped literal.

These are partial remediation findings, not final implementation acceptance.

### Remaining diagnostic findings

Current MySQL full-suite evidence contains 15 failures grouped into three causal boundaries:

1. `NonclinicalValidationContextProvisioningTest`
   - 13 failures.
   - The command exits 1 instead of 0.
   - The user-facing command catches the underlying exception and reports `SAFE_PROVISIONING_FAILURE`.
   - The exact underlying exception is currently hidden.
2. `Mvp04dVerifiedCheckInTicketIssueTest`
   - 1 MySQL-only failure.
   - The worker subprocess terminates with `InvalidArgumentException`.
   - The exact originating input and call stack must be captured before correction.
3. `Mvp04pPublicQueueDisplayTest`
   - 1 MySQL-only failure.
   - `callBasicExamination` returns HTTP 409 / `queue_call_conflict` after claim.
   - The exact conflicting queue/admission state is currently unresolved.

### Authorized diagnostic instrumentation

The remediation execution MAY add narrow non-production diagnostic instrumentation to reveal these root causes:

- For the provisioning command, add a focused test or harness that exposes the original caught exception, including class, message, relevant stack frame, and safe non-secret context. Bypass the command-level catch only within the focused diagnostic boundary; preserve `SAFE_PROVISIONING_FAILURE` for normal command execution.
- For the worker subprocess, capture child stdout, stderr, exit code, and the relevant stack trace; identify the exact input reaching `InvalidArgumentException` and reproduce it independently where practical.
- For the public queue flow, capture safe state around claim, queue/admission state, call, and `queue_call_conflict`; identify the exact guard, transition, uniqueness rule, transaction behavior, or constraint producing 409. Do not change production 409 behavior during diagnosis.
- Record local and CI MySQL image identity and server version, including `SELECT VERSION()` or an equivalent non-production probe where available.

Do not expose secrets, credentials, production data, or unnecessary patient/member data.

### Root-cause classification gate

Classify each remaining boundary before behavioral correction as one of:

- same-task portability or test debt;
- genuine product defect;
- authorization or product-contract decision;
- schema or migration change required;
- architecture change required;
- environment or CI policy decision required; or
- unresolved.

Continue implementation only for same-task portability or test debt. Return to Planner for all other classifications; continue diagnosis without guessing when unresolved.

### Remaining approval requirements

- Any schema, migration, dependency, architecture, product, or production change returns to Planner/Reviewer.
- No production access, deployment, release, or production workflow trigger is authorized.

## Execution constraints

- Reproduce each failure cluster before correction and preserve complete diagnostics.
- Use driver-aware behavior rather than broad exception swallowing.
- Fix test/fixture data only when the existing authoritative schema contract is clear.
- Do not classify cascading authorization/provisioning failures until upstream failures are resolved.
- Reuse existing repository mechanisms; do not add a new database or test framework.
- Use diagnostic instrumentation only within the explicitly authorized non-production boundaries in the Remediation section.
- Do not alter user-facing safe-failure behavior or public queue 409 behavior merely to expose or suppress a defect.

## Acceptance criteria

- [ ] MySQL image/runtime identity is reproducible or the documented digest difference is shown not to affect results.
- [ ] SQLite-only setup does not execute against MySQL, while genuine SQL errors remain visible.
- [ ] Affected fixtures satisfy existing authoritative schema constraints without unjustified schema expansion.
- [ ] Authorization/provisioning failures are either corrected at root cause or returned as genuine separate defects with evidence.
- [ ] `deployment/verify-mysql.sh` completes successfully against real MySQL, including migrations, representative checks, portability probes, integration checks, and the full PHP suite.
- [ ] Default PHPUnit verification remains successful and no DICOM/MPIPS/storage/product behavior is changed.
- [ ] No skipped/ignored/soft-failed validation or unauthorized side effect is introduced.
- [ ] The three remaining causal boundaries have focused evidence and an explicit classification before correction.

## Required verification

### Required checks

```text
vendor/bin/phpunit tests/Feature/Operator/Mvp04dVerifiedCheckInTicketIssueTest.php tests/Feature/Operator/Mvp04kBasicExaminationCompletionTest.php tests/Feature/Operator/Mvp04pPublicQueueDisplayTest.php tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php tests/Feature/Operator/PrestigeClinicSeederTest.php tests/Feature/Validation/NonclinicalValidationAccountProvisioningTest.php tests/Feature/Validation/NonclinicalValidationContextProvisioningTest.php
vendor/bin/phpunit
bash deployment/verify-mysql.sh
vendor/bin/pint --test
git diff --check
```

Run relevant deployment and validation test suites as applicable.

### Required evidence

Report exact image ID/digest, PHP/MySQL versions, database configuration without secrets, migration result, every failure cluster and root cause, focused results, complete PHPUnit result, MySQL script result, and all known gaps.

## Stop conditions

- Evidence requires schema/migration, dependency, architecture, product, or authorization-policy changes.
- A failure cannot be classified without new authority or equivalent runtime evidence.
- The only proposed resolution weakens, skips, suppresses, or replaces meaningful validation.
- Work would touch DICOM/MPIPS/storage architecture, production, deployment, release, or unrelated product behavior.

## Side-effect authorization

### Explicitly authorized side effects

- Modify only affected validation/test/bootstrap files and narrowly necessary local configuration within this task.
- Run local verification and non-production MySQL containers.

### Not authorized

- Production or external-system mutation, deployment, release, production workflow trigger, credentials/secrets, schema/migration changes without renewed approval, or implementation of Task B.

## Task relationship

This is an independent sibling of `.agents/tasks/deployment-validator-precision.md`. Neither task changes the accepted result of PR validation stabilization, and neither may absorb unrelated product defects. Discovery of an authoritative schema/product defect returns to Planner/Reviewer before scope expansion.

## Expected terminal outcome

`REVIEW REQUIRED` with observed MySQL compatibility evidence, or `PLANNING REQUIRED` when a stop condition is reached.
