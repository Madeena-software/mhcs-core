---
name: mhcs-core-mvp-04e-worklist-denial-matrix-remediation
description: Make the MVP-04E worklist denial matrix pass from the default isolated test harness without weakening authorization, FIFO, or privacy assertions.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04E — Worklist Denial-Matrix Remediation

## Objective

For `$TARGET`, make the private `operator.basic-examination-worklist` denial
matrix run from the default isolated test harness and return HTTP 403 for every
revoked or invalid authorization condition that the published MVP-04E test
requires, while preserving the existing authorized FIFO read and all queue,
privacy, and Member-ownership boundaries.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `codebase-memory-mcp`
  - `ponytail`
- Ordered model preferences: None.
- Require preferred model: `false`

Codebase Memory MCP and ponytail are mandatory. Keep ponytail at full level:
reuse the existing `Tests\\TestCase`, test-only configuration convention,
`EnsureOperatorPortalAccess`, `OperatorAuthorization`,
`OperatorWorklistService`, `PortalController`, and MVP-04E test. Do not add a
permission, middleware class, policy, queue abstraction, dependency, test
fixture framework, runtime secret, or production configuration surface.

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Previously accepted baseline:
  `8ba97255bc1961945d9802a37d504442e3e1cf55`.
- Reviewed remediation commit:
  `6e91fe07feb010f92ae2719d55b67ea670ebbb98`, descended from the accepted
  baseline through MVP-04E candidate
  `26576ef89fe1a06ba0d75ba422f4a4efc2a3eaaa`.
- The reviewed remediation correctly adds an `OperatorException` HTTP-403
  branch to `$TARGET/app/Http/Controllers/Operator/PortalController.php`, but
  it is not accepted: the default MVP-04E suite stops before assertions because
  `mhcs.security.identifier_key`, `object_key`, and `grant_key` are unset, and
  with non-production process-only material supplied, 5 of 6 tests pass while
  `test_worklist_rechecks_account_portal_site_and_shift_scope_without_leaking_rows`
  still observes one HTTP 302 where it requires 403.
- `$TARGET/tests/TestCase.php` already supplies a non-production `app.key`
  when absent, but does not supply the three separate test-only MHCS key
  materials. `$TARGET/config/mhcs.php` deliberately obtains real key material
  only from environment variables; production configuration must remain
  untouched.
- The test's four denial assertions cover revoked shift assignment (approved
  empty 200 worklist), revoked active-site assignment (403), revoked portal
  permission (403), forged/unknown active-site session (403), and suspended
  account (403). Determine the exact remaining 302 from isolated observed test
  evidence; do not assume which denial is failing.
- Related requirements: `OPR-026`, `OPR-108`, `OPR-115`, `OPR-116`,
  `OPR-117`, and `OPR-129`.
- Related Work Packages: WP-11, WP-12, and WP-17.
- Related gaps remain open: `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and
  `MVP-GAP-024`.

Read completely before planning or changing files:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/skills/fix-bug/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-advance-queue-admission-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-worklist-authorization-remediation-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/docs/mvp/evidence/mvp-04e-advance-queue-admission.md`;
- `$TARGET/config/mhcs.php`;
- `$TARGET/tests/TestCase.php`;
- `$TARGET/tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php`;
- `$TARGET/app/Http/Middleware/EnsureOperatorPortalAccess.php`;
- `$TARGET/app/Http/Controllers/Operator/PortalController.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`; and
- `$TARGET/routes/web.php`.

Use Codebase Memory MCP before editing to verify canonical repository identity
and current index status. The current graph is incomplete even after a full
refresh (258 nodes and 267 edges, with required source symbols absent). Record
that status, use the source/test fallback permitted by `AGENTS.md` for this
slice, and do not repeatedly re-index unless the tool provides new recovery
evidence. When symbols are available, trace the route, controller,
authorization, worklist, and test callers/callees; otherwise record the graph
limitation and inspect those exact paths directly.

## Scope and constraints

Included:

- supplying deterministic, non-secret, test-only MHCS key material through the
  existing test bootstrap only when the corresponding test configuration values
  are absent, without writing `.env`, mutating the process environment, or
  overriding explicitly supplied values;
- splitting or otherwise isolating the existing worklist denial assertions only
  as needed to identify the exact 302 response without weakening coverage;
- the smallest existing-boundary fix required to make that observed denial
  return 403; and
- updating bounded MVP-04E evidence/status documents only after all required
  checks pass.

Excluded:

- production key values, `.env`/`.env.example` changes, secret generation,
  secret logging, configuration caching, deployment, or dependency changes;
- new authorization permissions, shared middleware/policy redesign, route
  changes, queue mutation, claim/call/skip action, clinical assessment,
  walk-in behavior, public/LCD display, Member mutation, schema, migration,
  audit/outbox, idempotency, or FIFO-order changes; and
- commits and pushes.

The test-only fallback values must be fixed non-production fixtures, never a
production credential, and must exist only in the test application config. An
authorized assigned Operator must retain the current private worklist behavior:
only active-site, active assigned-shift `advance` / `basic_examination` /
`waiting` rows ordered by `ready_at` then admission ID, exposing only the
approved ticket, site, shift, stage, state, and ready-time fields. Revoked shift
assignment remains the existing empty successful worklist; active-site,
permission, forged-session, and suspended-account denials must expose no data
or internal detail.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates: Before editing test bootstrap, tests, or product code, present the exact test-only fallback and one observed denial-path correction, including the proof that no production key/configuration or authorization scope changes; wait for explicit owner approval. Stop as `awaiting-approval` for any production configuration, secret, permission, shared middleware/policy, or broader workflow change.

Use `single-pass` with exactly one iteration or `agentic-loop` with a positive finite limit. The task cannot grant permissions or bypass repository approval requirements.

## Execution procedure

1. Resolve `$TARGET` canonically; verify repository identity, clean-or-owner-
   change worktree state, candidate ancestry, immutable task content, and all
   required capabilities.
2. Validate this task, the MVP-04E task, and the prior authorization-remediation
   task with the repository validator before editing.
3. Verify ponytail at full level and record the existing test bootstrap,
   controller, middleware, authorization, worklist, and test patterns reused;
   explain why a test-only fallback and one route-boundary correction are the
   smallest solution.
4. Check the Codebase Memory MCP status and follow its declared fallback. Trace
   available graph paths or inspect the exact listed source/test paths directly.
5. Run the MVP-04E test first with no injected configuration and record the
   missing-key failure. Prove the tests use the isolated SQLite configuration;
   do not read, print, or use any real environment secret.
6. Present the exact non-production test-config fallback and the isolated
   denial-matrix diagnosis plan at the approval gate. After approval, add the
   fallback only in the existing test bootstrap and only for absent values.
7. Run the MVP-04E test again without process-injected key material. Isolate the
   four denial states into independently observable assertions if necessary,
   then correct only the source path responsible for the remaining 302. Do not
   weaken a 403 assertion or change the approved empty-worklist case.
8. Run the MVP-04E, MVP-04D, MVP-04C, MVP-04B, Operator portal, Operator
   foundation, WP-02 security, and architecture suites separately; run PHP
   syntax, Pint test mode, Composer validation, operator route listing,
   privacy-sensitive output searches, Codebase Memory/source path review, and
   `git diff --check`.
9. Only after all required checks pass, update
   `$TARGET/docs/mvp/evidence/mvp-04e-advance-queue-admission.md`,
   `$TARGET/docs/mvp/roadmap.md`, and
   `$TARGET/docs/mvp/work-package-status.md` with exact observed evidence and
   remaining gaps. Re-read the final diff for permitted scope only. Do not
   commit or push.

## Acceptance criteria

- [ ] This task and both immutable MVP-04E remediation tasks validate; the
      candidate ancestry, isolated SQLite configuration, graph status/fallback,
      and relevant source/test paths are observed before editing.
- [ ] The default MVP-04E test command starts with no supplied real or process-
      injected key values and reaches its assertions using only absent-value,
      deterministic test-only MHCS configuration.
- [ ] Each required active-site, portal-permission, forged-session, and
      suspended-account denial is independently proven to return HTTP 403 with
      no worklist, Member, booking, consent, identity, clinical, or internal
      error detail; revoked shift assignment remains an empty HTTP-200 worklist.
- [ ] The accepted authorized worklist, queue admission/check-in transaction,
      Member ownership, ticket privacy, route contract, schema, audit/outbox,
      idempotency, and FIFO ordering are unchanged.
- [ ] All required focused/regression/security/architecture, syntax, Pint,
      Composer, route, privacy, and diff checks pass with observed output;
      only permitted test-bootstrap, focused-test, minimal boundary, and bounded
      evidence/status files change.
- [ ] No production configuration, real secret, dependency, commit, or push is
      created, modified, exposed, or used.

## Verification

- Method: Validate all three immutable MVP-04E tasks; prove default isolated test configuration; run MVP-04E first without injected keys and then after the approved test-only bootstrap correction, followed separately by MVP-04D, MVP-04C, MVP-04B, Operator portal, Operator foundation, WP-02 security, and architecture suites, PHP syntax and Pint test mode, Composer validation, operator route list, privacy-sensitive searches, Codebase Memory/source-path review, and `git diff --check`.
- Expected result: The default MVP-04E suite passes every denial-matrix and private FIFO assertion, all required regressions pass with no real key material or production configuration, all denial boundaries return the required status without leakage, and no queue, clinical, Member, deployment, dependency, commit, or push behavior changes.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or `exhausted`.
- Report target, accepted baseline, reviewed commit, selected runtime/model when
  verifiable, approval decision, capabilities, outcome, affected files,
  Codebase Memory MCP and ponytail evidence, exact checks/results, unrun checks,
  residual risks, and manual follow-up.
- Treat a missing test-only key fallback, any process/production key dependency,
  a remaining 302 or data leak in the denial matrix, a changed queue or
  check-in boundary, skipped mandatory verification, or claimed rather than
  observed test success as unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report final worktree state and readiness for owner-controlled commit. After
the owner supplies a remediation commit SHA, review it and its full chain
against accepted baseline `8ba97255bc1961945d9802a37d504442e3e1cf55`, the
published MVP-04E task, both earlier remediation tasks, this task, and observed
runtime evidence before accepting a new baseline or selecting another vertical
slice.
