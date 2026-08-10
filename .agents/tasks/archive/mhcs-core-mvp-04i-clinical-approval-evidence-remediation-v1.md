---
name: mhcs-core-mvp-04i-clinical-approval-evidence-remediation
description: Correct unsupported clinical privacy approval claims without altering product behavior or immutable task history.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04I — Clinical Approval Evidence Remediation

## Objective

For `$TARGET`, correct the unsupported clinical privacy claims introduced by
commit `e91a190fc25abd434240d9e661ff416e294edbfc`. Preserve the immutable MVP-04I
task and historical evidence, but add a superseding correction that makes clear
that no specific retention duration, deletion process, anonymization standard,
lawful basis, clinical threshold, unit catalog, FHIR profile, or completion
transition is approved without attributable owner evidence.

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
- Previous accepted baseline: `a7d8f361fa19f5404062b7b543c47a2da2dea658`.
- Review commit: `e91a190fc25abd434240d9e661ff416e294edbfc`.
- Directly inspect the immutable task
  `.agents/tasks/mhcs-core-mvp-04i-basic-examination-clinical-approval-v1.md`,
  the added `docs/mvp/evidence/mvp-04i-basic-examination-clinical-approval.md`,
  and `docs/mvp/decision-log.md`.
- The immutable task explicitly forbids inventing data-retention periods,
  lawful bases, FHIR profiles, or clinical outcome language. Its evidence claims
  a 25-year retention rule and detailed deletion/anonymization process without
  an attributable repository authority. That conflict is material because it
  affects privacy/legal and clinical planning.
- Directly inspect `.agents/context/modules/operator/project.md`,
  `docs/implementation/mhcs-core-implementation-plan.md` (APP-002, APP-004,
  RISK-004), `docs/mvp/beta-gap-register.md` (MVP-GAP-021), and
  `docs/mvp/decision-log.md`. These authorities keep the policy unresolved and
  require explicit approval; they override derived or generated claims.
- Use Graphify to identify the documentation relationship and freshness, and
  Codebase Memory MCP to confirm the current MVP-04H start boundary. Direct
  repository files and observed version-control evidence are authoritative.

## Scope and constraints

Included:

- a documentation-only superseding decision and remediation evidence record;
- a precise statement that the bounded clinical-assessment direction may be
  planned, while all policy details not explicitly approved remain unresolved;
- direct cross-references to APP-002, APP-004, RISK-004, and MVP-GAP-021.

Excluded:

- edits to the immutable MVP-04I task or its original evidence;
- clinical/product code, schema, migrations, routes, forms, APIs, tests,
  queue transitions, Member contracts, audits/outbox, retention jobs, deletion,
  anonymization, dependencies, commits, and pushes;
- replacing missing owner evidence with assumptions or generated policy text.

Preserve the accepted MVP-04H claim/call/start behavior and all product files.
Do not reopen the clinical implementation slice until the corrected approval
record has exact, attributable owner evidence for each policy decision.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates: A new retention, deletion, anonymization, lawful-basis,
  clinical-validation, or interoperability policy may be recorded only when
  the owner supplies explicit, attributable, dated, and scoped evidence. Without
  it, state the missing decision as unresolved. No product edit is authorized.

## Execution procedure

1. Resolve `$TARGET`; verify worktree ownership/state, baseline ancestry, task
   validation, and required capabilities. Preserve unrelated changes; do not
   reset, clean, commit, or push.
2. Use Graphify and Codebase Memory MCP for discovery/freshness, then directly
   inspect all listed authority, task, evidence, source, and Git files before
   making a finding.
3. Confirm the exact conflict: the MVP-04I task prohibits invented privacy
   rules while its generated evidence and decision claim specific rules lacking
   direct attributable authority.
4. Add one new remediation evidence document and one superseding decision-log
   entry. Do not rewrite historical files. The correction must identify the
   unsupported claims, preserve their historical location, state the accurate
   bounded approval status, retain MVP-GAP-021, and list the exact required
   owner evidence for later implementation.
5. Re-check that the correction introduces no policy, implementation claim, or
   product-file change. Validate the prerequisite and remediation tasks, run
   documentation checks and `git diff --check`, then provide commit-review
   handoff without committing or pushing.

## Acceptance criteria

- [ ] Direct repository authority and version-control evidence establish the
      conflict before documentation changes are made.
- [ ] The immutable MVP-04I task and its original evidence remain unchanged;
      the correction is additive, traceable, and supersedes only unsupported
      approval claims.
- [ ] The corrected record explicitly leaves retention duration, deletion,
      anonymization, lawful basis, clinical validation/units, FHIR, and queue
      completion unresolved unless attributable owner evidence exists.
- [ ] No product source, migration, route, test, queue state, clinical data,
      dependency, commit, or push changes occur.
- [ ] Task validation and final diff checks pass with observed evidence.

## Verification

- Method: Validate MVP-04I and this remediation task; directly compare the
  reviewed commit with its baseline; inspect named authority files and the new
  superseding records; confirm no product-file changes and run `git diff --check`.
- Expected result: Unsupported privacy/clinical approval claims are explicitly
  superseded without rewriting immutable history or changing product behavior,
  and later clinical implementation remains gated on exact owner evidence.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, reviewed and accepted baselines, selected runtime/model when
  verifiable, Graphify/Codebase Memory actions or limitations, direct authority
  files, exact correction, verification evidence, residual approval gaps, and
  manual follow-up.
- Include commit-review handoff: compare the documentation-only candidate with
  `e91a190fc25abd434240d9e661ff416e294edbfc`, confirm no product behavior
  changed, and report no commit or push.
