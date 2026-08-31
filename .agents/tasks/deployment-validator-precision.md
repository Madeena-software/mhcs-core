---
title: Deployment Validator Precision
document_id: MHCS-TASK-DEPLOYMENT-VALIDATOR-PRECISION-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-31
scope:
  - precise deployment safety validation
  - deterministic validator regression coverage
authority_note: This task authorizes bounded deployment-validator precision only. It does not authorize deployment, production access, workflow evasion, or weakening security checks.
---

# Executable Task

## Task identity

**Task title:** `Deployment Validator Precision`

**Task path:** `.agents/tasks/deployment-validator-precision.md`

**Task contract state:** `Validated/Published upon immutable publication of this exact content.`

**Delivery objective / Work Package / MVP:** `Make deployment validation reject genuinely unsafe material while accepting evidence-backed safe repository constructs.`

**Owner / designated planning authority:** `Planner/Reviewer under the handoff dated 2026-08-31`

## Delivery context

`deployment/validate.sh` is byte-identical to baseline `d071f7467b749b18e967985ff9ed5b73040f0579` and exits with `deployment contains a forbidden live-environment or secret pattern`. Its broad scan covers `.github`, `docker-compose.prod.yml`, `Dockerfile`, and `docker`, and matches existing workflow identifiers, GitHub API URLs, production diagnostic URLs, and localhost health checks alongside genuinely risky patterns.

## Baseline and task revision

**Implementation baseline:** `768177b7c40f35a47e3f0d4a0bcfdfa726a5acb2`

**Task revision:** `The full SHA of the normal commit containing this exact task content.`

## Authoritative inputs

- Planner/Reviewer diagnostic handoff dated 2026-08-31.
- `.agents/AGENTS.md`, `.agents/software-workflow.md`, and `.agents/context/project.md`.
- Current `deployment/validate.sh`, deployment files, and existing deployment validation tests.

## Scope

### In scope

- Characterize the validator’s intended threat model and current scan roots.
- Add deterministic validator regression tests before changing detection behavior.
- Preserve rejection of default credentials such as `minioadmin`, prohibited `CHANGE_ME` placeholders, embedded AWS-access-key-like values, literal credentials/secrets, and genuinely forbidden live endpoints/configurations defined by repository policy.
- Accept evidence-backed safe constructs such as `SSH_USER` variable names without credentials, explicitly safe GitHub-owned API endpoints, localhost/container health-check URLs, and other confirmed false-positive classes.
- Replace the giant raw grep behavior only as needed with context/path-aware detectors, narrow allowlists, or structured validation that remains fail-closed.

### Out of scope

- Removing `.github` wholesale, removing URL/secret checks, excluding arbitrary files, `|| true`, warning-only behavior, `continue-on-error`, workflow changes made only to evade validation, deployment, production access, release, or production mutation.
- Task A, MySQL validation remediation, and unrelated product or security changes.

### Preserved behavior

- Genuinely unsafe live-environment, secret, credential, and configuration material remains blocking.
- Deployment validation remains deterministic, non-production, and fail-closed.
- Existing deployment workflows are not changed unless independently proven incorrect.

## Dependencies, assumptions, and approvals

### Dependencies

- Baseline remains `768177b7c40f35a47e3f0d4a0bcfdfa726a5acb2`.
- Task A, `mysql-validation-baseline-remediation.md`, is an independent sibling and is neither a prerequisite nor part of this task.

### Approved assumptions

- The current forbidden matches predate stabilization and are not caused by the accepted workflow/test changes.
- The validator’s scan scope and pattern behavior require evidence-based precision work; no particular allowlist or implementation is pre-approved.

### Remaining approval requirements

- Any change to repository security policy, production workflow behavior, deployment architecture, or external systems returns to Planner/Reviewer.
- No production access, deployment, release, or production workflow trigger is authorized.

## Execution constraints

- Write regression tests first and demonstrate both unsafe rejection and safe acceptance.
- Keep detection fail-closed for genuinely unsafe material.
- Use narrow path/context rules and document why each safe exception is permitted.
- Do not make wording-only edits to evade matching.
- Reuse the existing validator/test mechanisms; do not add a new validation framework.

## Acceptance criteria

- [ ] The current legitimate repository state passes `deployment/validate.sh`.
- [ ] Representative default credentials, prohibited placeholders, AWS-key-like values, literal secrets, and genuinely forbidden live endpoints still fail deterministically.
- [ ] Confirmed safe SSH variable names, GitHub-owned API URLs, localhost/container health checks, and other evidence-backed false-positive classes pass.
- [ ] Regression tests cover every retained detector and every safe exception.
- [ ] No unsafe construct is accepted through a broad exclusion or blanket allowlist.
- [ ] No deployment workflow, production configuration, secret, production behavior, or unrelated product behavior is changed.

## Required verification

### Required checks

```text
vendor/bin/phpunit tests/Deployment/Wp02DeploymentTest.php
bash deployment/validate.sh
vendor/bin/phpunit tests/Deployment
git diff --check
```

The Executor MUST extend the focused deployment test coverage before changing detection behavior and report the exact commands and results; no test command may be replaced by a weaker manual check.

### Required evidence

Report the threat-model decision, scan roots, detector/exception mapping, exact unsafe and safe fixtures, focused test output, deployment validator output, existing deployment-suite output, and any unresolved policy question.

## Stop conditions

- Repository policy does not define whether a matched construct is safe or forbidden.
- A safe exception cannot be narrowly scoped without allowing an unsafe equivalent.
- Acceptance requires weakening fail-closed behavior, suppressing a test, or changing a workflow solely to evade validation.
- Work would require production access, deployment, release, external mutation, or unrelated security/product changes.

## Side-effect authorization

### Explicitly authorized side effects

- Modify only the validator and its focused regression tests, plus narrowly necessary local validation fixtures.
- Run local validator and non-production deployment checks.

### Not authorized

- Production access or workflow trigger, deployment, release, workflow evasion, secret access/disclosure, unrelated workflow changes, implementation of Task A, or external-system mutation.

## Task relationship

This is an independent sibling of `.agents/tasks/mysql-validation-baseline-remediation.md`. Neither task changes the accepted result of PR validation stabilization, and neither may absorb unrelated product defects. Discovery of an authoritative policy/security decision returns to Planner/Reviewer before scope expansion.

## Expected terminal outcome

`REVIEW REQUIRED` with fail-closed validator evidence, or `PLANNING REQUIRED` when a policy or security boundary is unresolved.
