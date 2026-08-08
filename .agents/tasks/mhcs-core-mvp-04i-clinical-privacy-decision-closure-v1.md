---
name: mhcs-core-mvp-04i-clinical-privacy-decision-closure
description: Capture attributable clinical and privacy decisions required to authorize the final MVP-04 basic-examination implementation task.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04I — Clinical and Privacy Decision Closure

## Objective

For `$TARGET`, obtain and record exact, attributable, dated APP-002 and APP-004
decisions required before planning or implementing the final MVP-04 basic
examination and vital-signs assessment slice. If any required decision is not
provided, stop as `awaiting-approval` without product or policy changes.

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
- Accepted baseline: `2114add25e948f535240441f490936d750cacb68`.
- Directly inspect the immutable MVP-04I approval and remediation tasks and
  their evidence under `.agents/tasks/` and `docs/mvp/evidence/`. The
  remediation supersedes unsupported policy claims and keeps MVP-GAP-021 open.
- Directly inspect `.agents/context/modules/operator/project.md` for the
  mandatory assessment bundle, missing-value reasons, response semantics,
  Member longitudinal ownership, and queue requirement; it does not supply
  clinical thresholds, units, retention, deletion, or anonymization policy.
- Directly inspect `docs/implementation/mhcs-core-requirements-matrix.md` for
  OPR-020 through OPR-025 and OPR-108, OPR-115 through OPR-117, and OPR-129;
  `docs/implementation/mhcs-core-implementation-plan.md` for APP-002, APP-004,
  RISK-004, WP-07, WP-11, WP-12, and WP-17; and the current roadmap, decision
  log, beta-gap register, and work-package status.
- Use Graphify for documentation relationship discovery and Codebase Memory MCP
  to verify the current MVP-04H `called` to `in_service` boundary and absence of
  assessment persistence. Derived tools are discovery aids; direct repository
  files and attributable owner evidence are authority.

## Scope and constraints

Included:

- a structured request for the following exact decisions and, only after all
  are supplied, an additive decision/evidence record suitable for a later
  implementation task;
- APP-002: clinical authority, allowed measurement units and validation/range
  authority, safety/clinical copy, exact completion semantics and destination
  stage, and whether any interoperability/FHIR artifact is in or out of the
  final MVP-04 slice;
- APP-004: lawful basis, retention trigger/duration, access/audit audience,
  deletion and legal-hold rules, anonymization/de-identification standard, and
  secondary-use/disclosure constraints.

Excluded:

- product code, schema, migrations, forms, routes, APIs, clinical values,
  synthetic clinical fixtures, state transitions, Member contracts, FHIR,
  retention/deletion/anonymization mechanisms, dependencies, commits, and
  pushes;
- deriving a decision from a generic approval, generated documentation, prior
  unsupported MVP-04I record, Graphify, or Codebase Memory MCP.

Preserve all existing MVP-04 behavior. Do not fabricate a policy, a clinical
threshold, a unit, or a retention period. A blank or incomplete response is not
approval.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates: Every APP-002 and APP-004 decision listed in scope must be
  explicit, attributable to the authorized owner, dated, and scoped to this
  bounded assessment. If any is missing or ambiguous, stop as
  `awaiting-approval`. Recording a supplied decision is permitted only after
  direct scope verification; product changes always require a separate owner
  approval and task.

## Execution procedure

1. Resolve `$TARGET`; verify baseline ancestry, task validation, worktree
   ownership/state, and required capabilities. Preserve unrelated work; do not
   reset, clean, commit, or push.
2. Use Graphify and Codebase Memory MCP for discovery/freshness, then directly
   inspect the listed authority files, previous approval/remediation records,
   current start implementation, and Git evidence.
3. Present the exact APP-002 and APP-004 decision request from Scope and
   constraints. Require an attributable response for each item; do not infer
   acceptance from prior general wording.
4. If any item is missing, report `awaiting-approval`, list only the missing
   decision identifiers, and stop without repository changes.
5. If all items are supplied, compare each decision to the Operator authority
   and document only what was approved in one additive decision-log/evidence
   record. Cross-reference MVP-GAP-021 and retain any broader unresolved scope.
6. Validate this task and all MVP-04I prerequisite tasks, inspect the final
   documentation diff, run `git diff --check`, and provide a commit-review
   handoff. Do not commit or push.

## Acceptance criteria

- [ ] Direct authority and prior remediation evidence are inspected before any
      decision is requested or recorded.
- [ ] Every listed APP-002 and APP-004 item has attributable, dated, scoped
      owner evidence, or the task ends `awaiting-approval` without changes.
- [ ] Any authorized record is additive, precise, traceable, and does not revive
      the unsupported 25-year, deletion, or anonymization claims.
- [ ] No product behavior, clinical data, schema, route, test, dependency,
      commit, or push changes occur.
- [ ] Prerequisite and current task validation plus final diff checks pass.

## Verification

- Method: Validate the MVP-04I approval/remediation tasks and this task; inspect
  named authority files and attributable owner evidence; then inspect any
  additive record and run `git diff --check`.
- Expected result: The exact missing clinical/privacy decisions are either
  captured without unsupported inference, enabling a later final MVP-04 task,
  or the task safely awaits the specific missing approval with no product change.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, accepted baseline, selected runtime/model when verifiable,
  Graphify/Codebase Memory actions or limitations, direct authority files,
  decision matrix, approval state, affected documentation if any, verification
  evidence, residual risks, and manual follow-up.
- Include commit-review handoff: compare any documentation-only candidate with
  `2114add25e948f535240441f490936d750cacb68`, confirm no product behavior
  changed, and report no commit or push.
