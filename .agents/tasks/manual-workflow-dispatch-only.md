---
title: Manual-Only GitHub Actions Workflow Dispatch
document_id: MHCS-TASK-MANUAL-WORKFLOW-DISPATCH-ONLY-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-09-01
scope:
  - GitHub Actions trigger policy
  - PR #2 bounded finalization
authority_note: This task authorizes only the bounded trigger-policy change and explicitly authorized Git operations below. It does not authorize deployment or production workflow execution.
---

# Executable Task

**Task title:** Make GitHub Actions workflows manual-only

**Task path:** `.agents/tasks/manual-workflow-dispatch-only.md`

**Task contract state:** `Validated/Published`

**Delivery objective / Work Package / MVP:** PR #2 finalization — workflow trigger policy

**Owner / designated planning authority:** Human decision recorded in the finalization authorization

## Delivery context

Temporarily prevent automatic GitHub Actions invocation while preserving every workflow body and all operational safeguards. Existing failing validations are deferred, not deleted or declared fixed.

## Baseline and task revision

**Implementation baseline:** `7b5e80d215051bda54eb2b2b0aae8f42fc1a9bbf`

**Task revision:** this exact task content at its publication commit

## Objective

**Objective:** Every `.github/workflows/*.yml` workflow is invoked only by `workflow_dispatch`; no automatic trigger is changed elsewhere.

## Authoritative inputs

### Governing authority

- Human finalization authorization for `Madeena-software/mhcs-core`, PR #2, dated 2026-09-01.
- `.agents/AGENTS.md` and `.agents/software-workflow.md`.

### Requirement traceability

- Finalization authorization → manual-only GitHub Actions trigger policy and bounded PR merge.
- Finalization authorization → preserve jobs, commands, safeguards, permissions, environments, concurrency, secrets boundaries, and deployment logic.

## Scope

### In scope

- Audit every `.github/workflows/*.yml` file.
- Remove automatic trigger events while retaining `workflow_dispatch`.
- In the observed baseline, change only `.github/workflows/security-validation.yml` from `pull_request` plus `workflow_dispatch` to `workflow_dispatch` only.
- Publish this task, commit the task and implementation separately, non-force push the feature branch, and merge PR #2 with an ordinary merge commit using exact-head protection.

### Out of scope

- Application code, workflow jobs, commands, validation logic, permissions, environments, concurrency, secrets, deployment logic, or any unrelated file.
- Running failing full validation merely to make it green.
- Triggering any workflow, deployment, production action, release, or branch cleanup.
- Remediation of Mvp04pPublicQueueDisplayTest MySQL 409, Deployment Validator Precision, or MySQL Y2038 technical debt; these remain deferred.

### Preserved behavior

- Every workflow job body and all existing safeguards remain byte-for-byte unchanged outside trigger declarations.
- No production action is authorized.
- Existing failing validations are deferred, not deleted or declared fixed.

## Dependencies and assumptions

### Dependencies

- Feature branch `feat/operator-dicom-batch-download` and PR #2.
- GitHub repository `Madeena-software/mhcs-core`.

### Approved assumptions

- The current remote feature SHA is the implementation baseline only while it remains exactly `7b5e80d215051bda54eb2b2b0aae8f42fc1a9bbf`.

### Remaining approval requirements

- None for the explicitly authorized task actions. Deployment, production workflow execution, release, and branch deletion remain unauthorized.

## Required capabilities

- Repository read/write and local shell execution.
- Git fetch, commit, and non-force push.
- GitHub PR inspection and exact-head ordinary merge.

## Execution constraints

- Use the smallest trigger-only diff.
- Inspect actual YAML `on:` blocks; do not count arbitrary strings in comments or shell commands as triggers.
- Preserve `workflow_dispatch` and every workflow body.
- Do not run or dispatch a workflow.

## Acceptance criteria

- [ ] Every `.github/workflows/*.yml` contains `workflow_dispatch`.
- [ ] No workflow has automatic `push`, `pull_request`, `schedule`/`cron`, `workflow_run`, `repository_dispatch`, `release`, or other automatic event trigger.
- [ ] `security-validation.yml` retains composer install, frontend build, Composer validate, Composer audit, Pint, complete PHP verification, MySQL verification, and deployment validation steps.
- [ ] The implementation diff changes only workflow trigger declarations plus this published task.
- [ ] PR #2 remains open, targets `main`, remains mergeable, and is merged with an ordinary merge commit at the exact verified feature head.

## Verification requirements

### Required checks

- Audit all workflow YAML trigger blocks.
- `git diff --check`.
- Inspect the full implementation diff and verify preserved validation steps.
- After push, verify local and remote feature heads match and the tree is clean.
- Before merge, re-read PR #2 metadata and verify exact head, base, state, and mergeability.
- After merge, verify PR state, merge timestamp, main revision, feature containment, and manual-only trigger policy on `main`.

### Required evidence

Report starting feature SHA, task-publication SHA, implementation SHA, final PR head SHA, merge commit SHA, main SHA, workflow audit result, deferred issues, and confirmation that no validation was falsely reported green and no workflow was triggered.

## Stop conditions

- Stop if branch, baseline, PR state, base, head, or mergeability differs from the authorized state.
- Stop if another automatic trigger requires more than a trigger-only removal or if any workflow body must change.
- Stop if the feature head moves before exact-head merge or if deployment/production execution would be required.

## Side-effect authorization

### Explicitly authorized side effects

- Commit task publication: `docs(task): publish manual workflow trigger policy`.
- Modify the bounded workflow trigger declarations and commit: `ci: make workflows manual dispatch only`.
- Non-force push to `origin/feat/operator-dicom-batch-download`.
- Merge PR #2 into `main` using an ordinary merge commit with the current feature head as expected SHA.

### Not authorized

- Deployment, production mutation, workflow dispatch, release, force push, rebase, squash, manual feature-to-main push, or branch cleanup.

## Expected terminal outcome

`REVIEW REQUIRED` until the exact implementation and merge evidence are observed; then return the requested finalization record. No production workflow execution is authorized.
