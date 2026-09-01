---
title: PR Validation Stabilization
document_id: MHCS-TASK-PR-VALIDATION-STABILIZATION-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-31
scope:
  - Security and validation workflow ordering
  - deterministic Prestige fixture test time
authority_note: This task preserves substantive validation and authorizes only the bounded CI/test stabilization described below.
---

# Executable Task

## Task identity

**Task title:** `PR Validation Stabilization`

**Task path:** `.agents/tasks/pr-validation-stabilization.md`

**Task contract state:** `Validated/Published upon immutable publication of this exact content.`

**Delivery objective:** Make PR #2 validation trustworthy on the existing feature branch without weakening regression, security, build, or deployment-policy checks.

**Owner / designated planning authority:** `Planner/Reviewer under the human-approved task-authoring handoff dated 2026-08-31`

## Baseline and task revision

**Implementation baseline:** `d071f7467b749b18e967985ff9ed5b73040f0579`

**Task revision:** `The full SHA of the normal commit containing this exact task content.`

## Authoritative inputs

- Human-approved execution handoff dated 2026-08-31.
- `.agents/AGENTS.md` and `.agents/software-workflow.md`.
- `.agents/context/project.md`.
- Existing `.github/workflows/security-validation.yml` and Prestige test implementation.

## Scope

### In scope

- Ensure the frontend build completes before PHP tests that render Vite-backed pages.
- Reproduce and diagnose `PrestigeWebTestMembersSeederTest::test_operator_can_see_both_subjects_before_manual_workflow`.
- If confirmed as a test-time wall-clock defect, derive its request timestamp deterministically from the fixed fixture schedule using existing test conventions.
- Preserve Composer validate, Composer audit, Pint, complete PHP verification, MySQL verification, frontend build, and deployment-policy validation.
- Run the required local verification, publish normal commits, push non-force to `origin feat/operator-dicom-batch-download`, and inspect the new PR #2 validation workflow run.

### Out of scope

- Removing meaningful checks, `continue-on-error`, skipped/suppressed PHPUnit failures, production feature changes, schema/migration/dependency changes, DICOM/MPIPS/NPZ changes, deployment, release, production mutation, force push, main push, merge, or generated `public/build` assets.
- Production access or triggering any production workflow.
- Production-code attendance changes unless investigation proves a genuine production defect; in that case stop and return the stack trace and minimal proposal to Planner/Reviewer.

### Preserved behavior

- Composer validation/audit, Pint, complete PHP verification, MySQL verification, frontend build, and deployment-policy validation remain active and blocking.
- Genuine regression, security, build, and deployment-policy failures remain CI blockers.
- Existing Prestige fixture semantics remain unchanged except for deterministic test-time selection of a timestamp inside its schedule when that is the confirmed root cause.

## Dependencies and approvals

- The implementation baseline remains `d071f7467b749b18e967985ff9ed5b73040f0579` until execution begins.
- Ordinary non-force push is authorized only to `origin feat/operator-dicom-batch-download` after local verification.
- No production access or production workflow trigger is authorized.
- Any required production-code broadening, dependency/schema change, or unrelated failure returns to Planner/Reviewer.

## Execution constraints

- Reproduce the focused Prestige failure before modification and capture the complete stack trace.
- Trace the exact `->all()` caller before classifying the failure.
- Make the smallest correction, rerun the focused test, then run nearby regressions.
- Prefer workflow orchestration correction only; do not fake `manifest.json` or alter substantive validation.
- Keep logical changes separable where both corrections are required.

## Acceptance criteria

- [ ] Frontend build completes before `php artisan test` in Security and validation workflow execution.
- [ ] Composer validate, Composer audit, Pint, complete PHP verification, MySQL verification, frontend build, and deployment-policy validation remain active.
- [ ] The Prestige focused test is deterministic and green, or execution stops with evidence of a genuine production defect.
- [ ] DICOM focused tests remain green and no unrelated baseline failure is silently fixed.
- [ ] The pushed PR #2 workflow is inspected from its actual run and all required steps conclude successfully, or every failure is reported accurately.
- [ ] No production access, production mutation, deployment, release, force push, main push, merge, dependency/lockfile, schema/migration, or generated-asset change occurs.

## Required verification

```text
vendor/bin/phpunit tests/Feature/Operator/PrestigeWebTestMembersSeederTest.php
vendor/bin/phpunit tests/Feature/Operator/OperatorPortraitDicomViewerTest.php
vendor/bin/phpunit tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php
vendor/bin/phpunit
npm run build
vendor/bin/pint --test
git diff --check
```

Also inspect the actual new PR #2 `Security and validation` GitHub Actions run after the authorized push, including each required step conclusion.

## Stop conditions

- The `->all()` failure is a genuine production defect rather than a test-time fixture-clock defect.
- A fix would weaken or remove a substantive validation gate, broaden into production behavior, or require dependency/schema/architecture changes.
- Any required check fails for an unrelated baseline reason; report it to Planner/Reviewer without automatically fixing it.
- Any action would access production, trigger production workflows, force-push, merge, deploy, release, or mutate external production state.

## Expected terminal outcome

`REVIEW REQUIRED — PR VALIDATION STABILIZED`
