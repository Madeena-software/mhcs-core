---
title: Security Validation Vite Build Ordering
document_id: MHCS-TASK-SECURITY-VALIDATION-VITE-BUILD-ORDER-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-29
scope:
  - GitHub Actions security-validation workflow ordering
  - focused workflow-order regression coverage
authority_note: This published task authorizes only the bounded CI workflow repair and repository-side regression coverage described here. It does not authorize application, dependency, deployment, production, or external-system changes.
---

# Executable Task

## Task identity

**Task title:**
`Security Validation Vite Build Ordering`

**Task path:**
`.agents/tasks/security-validation-vite-build-order.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content.`

**Delivery objective / Work Package / MVP:**
`CI validation maintenance — make Vite-backed PHP verification executable`

**Owner / designated planning authority:**
`Planner/Reviewer under the approved CI maintenance objective`

## Delivery context

At implementation baseline `2d31192a76252064237739022a53ee39c1547074`,
`.github/workflows/security-validation.yml` runs `php artisan test` before its
later `Build frontend` step. The PHP suite renders Blade views using Vite, so
the test run can fail with `Illuminate\\Foundation\\ViteManifestNotFoundException`
when `public/build/manifest.json` has not yet been produced. The later build is
then skipped because the earlier verification failed.

This is a separate CI ordering defect from the already-merged DICOM laterality
hotfix, which changed only `resources/js/operator-dicom-viewer.js` and
`tests/JavaScript/operator-dicom-viewer.test.mjs`.

## Baseline and task revision

**Implementation baseline:**
`2d31192a76252064237739022a53ee39c1547074`

**Task revision:**
`The full SHA of the commit containing this exact task content, supplied by publication metadata.`

The implementation baseline and governing task revision are separate
identities. The task revision MUST resolve to the immutable publication commit
before execution begins.

## Objective

**Objective:**
Repair the `Security and validation` workflow so frontend dependencies and
assets are successfully prepared, including `public/build/manifest.json`,
before any PHP test suite that can render Vite-backed views, while preserving
the existing Composer, MySQL, and deployment validation gates.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — bounded task, evidence, review, and side-effect requirements.
- `.agents/context/project.md` — repository architecture and deployment boundaries.
- Approved human maintenance objective for CI Vite build ordering.

### Observed implementation inputs

- `.github/workflows/security-validation.yml` at the implementation baseline — current step ordering and validation commands.
- `tests/Deployment/Wp02DeploymentTest.php` and related `tests/Deployment/*WorkflowTest.php` files — established workflow text and ordering assertion convention.
- Current repository history — the DICOM laterality hotfix is already merged and is outside this defect.

### Requirement traceability

- `CI-VITE-ORDER-001` → approved human maintenance objective and current workflow evidence: frontend build completion MUST precede `php artisan test`.
- `CI-VITE-PRESERVE-001` → existing workflow and repository validation convention: Composer audit, PHP verification, isolated MySQL verification, and deployment validation MUST remain present.

## Scope

### In scope

- Restructure `.github/workflows/security-validation.yml` so `npm ci --ignore-scripts` and `npm run build` complete before `php artisan test`.
- Preserve the existing checkout, PHP/Node setup, Composer install/validate/audit, Pint, isolated MySQL verification, and deployment validation coverage.
- Add one focused `tests/Deployment/` regression test only if following the established workflow-test convention; it MUST assert the build-before-PHP-test invariant and presence of the preserved validation gates.

### Out of scope

- DICOM viewer implementation or laterality behavior.
- Localization changes, Laravel/application behavior, NPZ normalization, browser normalization harness, or the production normalized-radiograph release task.
- Deployment scripts unless direct evidence proves they are necessary.
- Composer or npm dependency changes, MPIPS, production infrastructure, deployment, workflow dispatch, or production mutation.
- Modifying PHP tests to suppress the Vite-manifest exception, adding fake manifests, or adding test-only artifacts that mask workflow ordering.

### Preserved behavior

- `npm run build` MUST successfully complete before `php artisan test` starts.
- Composer audit, Pint, complete PHP verification, isolated MySQL verification, and deployment policy validation remain required.
- Existing validation is not weakened, skipped, or replaced merely to make CI green.
- Application and deployment behavior remain unchanged.

## Dependencies and assumptions

### Dependencies

- A clean checkout at `2d31192a76252064237739022a53ee39c1547074`.
- Existing Composer and npm lockfiles and installed repository tooling.
- Existing `tests/Deployment/` workflow assertion pattern if regression coverage is added.

### Approved assumptions

- Building the existing frontend with the repository's current `package.json` and lockfile produces the Vite manifest required by the Blade-rendering PHP tests.
- A workflow-order invariant assertion is more durable than asserting irrelevant exact step indexes.

### Remaining approval requirements

- Implementation may begin only under this exact immutable published task revision.
- Normal implementation review is required before acceptance.
- No deployment, production workflow dispatch, or release approval is granted by this task.

## Required capabilities

- Repository read/write limited to the workflow and focused workflow regression test.
- Shell, Git, Composer, npm, PHP, and existing test execution.
- No production credentials, deployment access, MPIPS access, or external-system mutation.

## Execution constraints

- Use the smallest coherent workflow change and established repository test patterns.
- Do not add dependencies or new testing mechanisms.
- The ordering assertion, if added, MUST prove frontend preparation/build exists, `npm run build` precedes `php artisan test`, PHP verification remains present, Composer audit remains present, and deployment validation remains present.
- Equivalent workflow restructuring is acceptable only when the dependency invariant is preserved.
- Do not alter application source, DICOM files, deployment scripts, lockfiles, or unrelated tests.

## Acceptance criteria

- [ ] The workflow successfully prepares frontend dependencies and runs `npm run build` before `php artisan test`.
- [ ] The frontend build is capable of producing `public/build/manifest.json` before PHP verification; no fake or test-only manifest is added.
- [ ] `php artisan test` remains present and runs as a required validation step.
- [ ] `composer audit` remains present and required.
- [ ] Isolated MySQL verification and deployment validation remain present and required.
- [ ] Existing validation is not weakened or skipped, and no application behavior changes are introduced.
- [ ] If a repository-side regression test is added, it checks the invariant and preserved gates without brittle irrelevant positional assertions.
- [ ] The previously observed `ViteManifestNotFoundException` caused by build ordering is no longer reproducible in the workflow sequence, subject to the reported local/CI evidence.

## Verification requirements

### Required checks before review

- `git diff --check`.
- The focused workflow-order regression test, if added.
- Clean dependency preparation consistent with CI:
  `composer install --no-interaction --prefer-dist --no-progress`,
  `npm ci --ignore-scripts`, and `npm run build`.
- `vendor/bin/pint --test`.
- `php artisan test` with the built Vite manifest available.
- `bash deployment/verify-mysql.sh`.
- `bash deployment/validate.sh`.

If a complete local check is materially impossible because of an existing
environment constraint, report the exact limitation and do not claim success.

### Required evidence

The Executor MUST report the exact governing task revision, implementation
baseline, implementation revision or working-tree state, changed files,
commands and observed results, whether the focused regression test was added,
the availability of `public/build/manifest.json` during PHP verification, any
limitations, and confirmation that no deployment, workflow dispatch,
production, or application behavior action occurred.

## Stop conditions

Stop and return `PLANNING REQUIRED` if:

- the governing task revision or implementation baseline cannot be verified;
- the workflow cannot build the required manifest without changing application or dependency scope;
- a deployment-script, infrastructure, Composer, npm, MPIPS, or application change appears necessary;
- an existing PHP test has an independent defect unrelated to asset ordering;
- required validation must be weakened, skipped, or replaced;
- the regression-test convention cannot be used without introducing a new testing mechanism; or
- execution would require deployment, workflow dispatch, production access, secret access, or another unapproved side effect.

The Executor MUST NOT silently reinterpret the task into a test suppression or
application workaround.

## Side-effect authorization

### Explicitly authorized after publication

- Modify only `.github/workflows/security-validation.yml` and, if justified by the established convention, one focused workflow regression test under `tests/Deployment/`.
- Run the specified local dependency, build, static, PHP, MySQL-verification, and deployment-policy checks.

### Explicitly unauthorized

- DICOM, application, localization, NPZ, browser harness, MPIPS, dependency,
  deployment-script, infrastructure, production, or unrelated test changes.
- Deployment, production or deployment-workflow dispatch, secret access or
  disclosure, external-system mutation, force push, or history rewrite.
- Git commit or push unless separately authorized by the delivery operator.

## Expected terminal outcome

### Review Required — Security Validation Vite Build Ordering Implemented

Use when the bounded workflow change and any justified focused regression test
are complete with truthful verification evidence. Stop for Planner/Reviewer
inspection and acceptance; do not dispatch CI or deploy.

### Planning Required

Use when a stop condition prevents safe completion within this task.
