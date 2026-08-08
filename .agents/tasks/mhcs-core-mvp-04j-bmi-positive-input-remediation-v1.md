---
name: mhcs-core-mvp-04j-bmi-positive-input-remediation
description: Reject invalid height and weight values before private MVP-04J BMI calculation or persistence.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04J — BMI Positive-Input Remediation

## Objective

For `$TARGET`, correct MVP-04J vital-signs input handling so height and weight
can produce a BMI only when both are finite, strictly positive measurements.
Reject zero, negative, and non-finite values before BMI calculation and before
any Member or Operator record is persisted. This is a mathematical and data-
integrity invariant, not a new clinical threshold or range.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `codebase-memory-mcp`
  - `graphify`
- Ordered model preferences: None.
- Require preferred model: `false`

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Previous accepted baseline: `c542b07cab53ef93f43a62f491ae06511150f674`.
- Review commit: `6c21b4a667eaab6d90957563c2fc695d7096fbdf`.
- Directly inspect the immutable MVP-04J task/evidence and
  `Mvp04VitalSignsService::normalize()`. The reviewed implementation accepts
  any numeric height/weight except height exactly zero, then calculates BMI;
  consequently negative height or weight can persist an invalid derived BMI.
- Directly inspect `PortalController::recordBasicExaminationVitalSigns()`, the
  private vital-signs Blade form, the Member contract, the two MVP-04J tables,
  `OperatorWorklistService::recordBasicExaminationVitalSigns()`, and the
  focused MVP-04J tests. The controller is a trust boundary and the Member
  contract must independently protect its persistence boundary.
- Directly inspect the Operator context and MVP-04I closure evidence. The
  approved units and value-or-missing-reason semantics remain fixed; no
  clinical threshold or range is authorized. Positive height and weight are
  necessary solely to make the specified BMI calculation meaningful.
- Use Graphify for relevant documentation relationships and Codebase Memory
  MCP for the controller/service/contract/test paths and freshness. Derived
  tools identify evidence only; inspect exact repository files before acting.

## Scope and constraints

Included:

- the smallest server-side controller and Member-contract validation necessary
  to reject zero, negative, and non-finite height/weight values when a value is
  supplied;
- focused regression coverage proving those inputs produce a normal validation
  failure with no Member assessment, Operator execution, audit event, outbox
  event, or handled idempotency result; and
- any minimal private-form constraint that accurately mirrors server behavior.

Excluded:

- numerical clinical ranges or thresholds; changes to blood pressure,
  temperature, units, missing reasons, BMI formula/rounding, persistence
  shape, queue state, completion/X-ray transition, FHIR artifacts, access
  scope, retention/deletion behavior, dependencies, commits, and pushes.

Preserve successful positive measurements, approved missing-reason paths,
claimant-only authorization, transaction/idempotency behavior, privacy-safe
metadata, and all existing MVP-04J paths. Do not rely on browser constraints:
the contract must reject invalid values if called directly.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates: None. This narrow correction enforces a mathematical BMI
  precondition and must not introduce clinical policy. Stop as
  `awaiting-approval` if the work would require a clinical range, a changed
  unit, additional field, or behavior outside this input-integrity boundary.

## Execution procedure

1. Resolve `$TARGET`; verify ancestry, published-task validation, worktree
   ownership/state, and capabilities. Preserve unrelated work; do not reset,
   clean, commit, or push.
2. Query Graphify and Codebase Memory MCP, refresh only if relevant evidence is
   stale, then directly inspect all authority, source, migration, route, form,
   and test files named above.
3. Reproduce or establish from source the invalid path for zero/negative/non-
   finite height and weight. Select the smallest existing Laravel validation
   and service-normalization pattern; record the Ponytail rationale.
4. Apply the validation at both trust boundaries and add focused regression
   tests for rejected values and retained positive/missing-reason behavior.
5. Run the focused remediation and MVP-04J suites plus relevant prerequisite,
   migration, formatter, syntax/static, route, privacy, Composer, Graphify/
   Codebase-Memory, task, and diff checks. Inspect actual outputs and final
   diff; provide commit-review handoff against the review commit without
   committing or pushing.

## Acceptance criteria

- [ ] Zero, negative, and non-finite height/weight input cannot reach BMI
      calculation or either vital-signs table.
- [ ] Both the HTTP boundary and direct Member contract reject invalid supplied
      BMI inputs without a clinical threshold or range.
- [ ] Rejected inputs create no assessment, execution, audit/outbox success
      evidence, or handled idempotency result, and reveal no clinical data.
- [ ] Positive measurements and all approved missing-reason paths remain
      unchanged; BMI remains server-calculated with existing units and rounding.
- [ ] Focused regression, prerequisite checks, and final diff checks pass with
      no scope creep, commit, or push.

## Verification

- Method: Run focused MVP-04J remediation and regression tests covering zero, negative, non-finite, positive, and missing-reason height/weight paths; then run the required migration, formatter, syntax/static, privacy, route, Composer, Graphify/Codebase-Memory, task, and `git diff --check` checks.
- Expected result: Invalid BMI inputs fail safely before persistence or derived calculation, valid MVP-04J capture behavior remains intact, and no clinical policy, FHIR, queue, or privacy-scope behavior changes.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, reviewed baseline, selected runtime/model when verifiable,
  Graphify/Codebase Memory actions or limitations, direct authority files,
  Ponytail choice, affected files, verification evidence, residual risks, and
  manual follow-up.
- Include commit-review handoff: compare the candidate with
  `6c21b4a667eaab6d90957563c2fc695d7096fbdf`, confirm the correction is
  limited to BMI input integrity, and report no commit or push.
