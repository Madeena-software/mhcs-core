---
name: mhcs-core-mvp-04e-acceptance-evidence-formatting-closure
description: Close the MVP-04E candidate’s inherited formatter debt and commit-traceability evidence without changing product behavior.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04E — Acceptance Evidence and Formatting Closure

## Objective

For `$TARGET`, close the remaining non-functional acceptance evidence for the
reviewed MVP-04E denial-matrix candidate:

`2545c6a56ccb186f35bbdbe76f3598e9c3d5dcc3`

which descends from accepted baseline:

`8ba97255bc1961945d9802a37d504442e3e1cf55`.

The candidate’s runtime behavior is already observed to pass: deterministic
test-only absent-value MHCS key fallbacks let the isolated suite boot, and a
narrow route exception lets a suspended Operator reach the existing fail-closed
Operator authorization boundary, which returns HTTP 403. It is not yet an
accepted baseline because the required repository-wide `vendor/bin/pint --test`
check fails on four Member Filament files that are unchanged from the accepted
baseline, and the published MVP-04E evidence must accurately connect runtime
verification to the committed candidate SHA.

Make only the smallest closure:

```text
four inherited style-only Member files
    -> existing Pint formatter, scoped to those exact paths
    -> repository-wide Pint check passes

MVP-04E evidence/status documents
    -> distinguish pre-commit verification at 6e91fe0 from
       committed candidate 2545c6a
    -> no claim that 6e91fe0 itself contains the remediation
```

Do not add, remove, or change product behavior, authorization, queue behavior,
configuration semantics, dependencies, routes, schema, migrations, or tests.

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
reuse the installed Pint formatter and the existing three MVP-04E evidence
documents. Do not add a formatter configuration, a script, a dependency, an
abstraction, or a broad formatting sweep. Run Pint only on the four named
inherited files, then use repository-wide Pint solely as verification.

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Accepted baseline: `8ba97255bc1961945d9802a37d504442e3e1cf55`.
- Reviewed candidate: `2545c6a56ccb186f35bbdbe76f3598e9c3d5dcc3`, whose
  parent is `6e91fe07feb010f92ae2719d55b67ea670ebbb98`.
- The immutable contracts
  `mhcs-core-mvp-04e-advance-queue-admission-v1`,
  `mhcs-core-mvp-04e-worklist-authorization-remediation-v1`, and
  `mhcs-core-mvp-04e-worklist-denial-matrix-remediation-v1` all validate.
- At the candidate, the default MVP-04E suite passes 6 tests and 61 assertions;
  MVP-04D passes 9/83, MVP-04C 6/64, MVP-04B 16/84, Operator portal 8/63,
  Operator foundation 15/56, WP-02 security 24/103, and architecture 6/1,573.
  Composer validation, PHP syntax for candidate-touched PHP files, operator
  route listing, and `git diff --check` also pass.
- Repository-wide `vendor/bin/pint --test` currently fails only on these files:
  - `$TARGET/app/Modules/Member/Filament/Resources/ServiceOfferings/Pages/EditServiceOffering.php`;
  - `$TARGET/app/Modules/Member/Filament/Resources/Bookings/BookingAuditRecord.php`;
  - `$TARGET/app/Modules/Member/Filament/Resources/Bookings/BookingResource.php`; and
  - `$TARGET/app/Modules/Member/Filament/Resources/ExaminationSites/ExaminationSiteReferenceResource.php`.
  They have no diff from accepted baseline to candidate, and their latest
  modifying commit is `a1360f4307d7d339779a48fd519755b360f52052`.
- The candidate changes only the existing test bootstrap, existing mandatory
  password middleware, the published denial-matrix task, and bounded MVP-04E
  evidence/status documents. It does not alter the worklist controller,
  authorization service, worklist query, routes, or queue persistence.
- Current Codebase Memory MCP project `var-www-mhcs-core` reports 4,134 nodes
  and 10,777 edges and contains `basicExaminationWorklist`, `basicExamination`,
  `portalSite`, `portal`, and `canAuthenticate`. Its traces show the suspended
  account is rejected by existing `OperatorAuthorization::portal` after the
  named route reaches `EnsureOperatorPortalAccess`. No refresh is currently
  required; use a fast refresh only if the index is stale or a required symbol
  is absent.
- Related requirements: `OPR-026`, `OPR-108`, `OPR-115`, `OPR-116`,
  `OPR-117`, and `OPR-129`.
- Related Work Packages: WP-11, WP-12, and WP-17.
- Open gaps unchanged by this closure: `MVP-GAP-009`, `MVP-GAP-012`,
  `MVP-GAP-021`, and `MVP-GAP-024`.

Read completely before planning or changing files:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/review-code/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-advance-queue-admission-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-worklist-authorization-remediation-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-worklist-denial-matrix-remediation-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/docs/mvp/evidence/mvp-04e-advance-queue-admission.md`;
- `$TARGET/app/Http/Middleware/EnforceMandatoryPasswordChange.php`;
- `$TARGET/app/Http/Middleware/EnsureOperatorPortalAccess.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- the four listed Member Filament files; and
- `$TARGET/tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php`.

## Scope and constraints

Included:

- applying the repository’s existing Pint configuration to only the four named
  inherited Member Filament files;
- confirming the formatter creates a style-only diff with no changed public
  method signature, authorization call, relation, label, route, persistence,
  query, or behavioral branch;
- correcting the bounded MVP-04E evidence, roadmap, and work-package-status
  text so it identifies pre-commit verification at `6e91fe0` and the committed
  remediation candidate `2545c6a`; and
- rerunning the stated formatter, runtime, static, route, graph, and diff
  evidence before proposing candidate acceptance.

Excluded:

- all product behavior, including the test-only fallback values, mandatory
  password middleware condition, Operator authorization, worklist controller,
  queue query, routes, tests, queue actions, check-in, clinical workflow,
  walk-ins, public/LCD behavior, Member features, schemas, migrations,
  audit/outbox, and configuration semantics;
- formatting any file other than the four named inherited files;
- formatter configuration, dependencies, dependency lockfiles, environment
  files, secret use, deployment, commits, and pushes; and
- changing requirements, gap ownership, or work-package implementation status.

The formatter result must remain a non-functional textual/style change. If Pint
would modify another file, do not apply it. If any required source change is
not purely formatter output or any candidate runtime behavior regresses, stop
and report `blocked` rather than widening this closure.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `1`
- Approval gates: Before executing Pint with write mode or editing evidence,
  present the exact four source paths and exact three evidence/status paths,
  state that the source change is formatter-only and the documentation change
  is SHA-traceability-only, and wait for explicit owner approval. Stop as
  `awaiting-approval` if approval is absent. Stop as `blocked` for any required
  behavioral, configuration, test, dependency, or out-of-scope formatting
  change.

## Execution procedure

1. Resolve `$TARGET` canonically; verify repository identity, clean-or-owner
   worktree state, candidate ancestry, immutable task content, and required
   capabilities. Preserve all existing work; do not reset, clean, stash,
   discard, stage, commit, or push.
2. Validate this task and the three immutable MVP-04E contracts with
   `$TARGET/.agents/skills/agent-task/scripts/validate_task.py` before editing.
3. Verify ponytail at full level. Record why the installed formatter applied to
   four exact inherited paths is the smallest solution and why no product
   change is needed.
4. Query Codebase Memory MCP for the stated project, candidate symbols, and the
   `basicExaminationWorklist -> portal -> canAuthenticate` path. Record current
   index status and use no refresh unless freshness or a required symbol cannot
   be established; then use only a fast refresh.
5. Prove the four Pint findings and their inherited provenance with a
   repository-wide Pint test and baseline-to-candidate path diff. Verify the
   candidate’s touched middleware, test bootstrap, evidence/status, route, and
   focused MVP-04E test paths before the approval gate.
6. At the approval gate, present the exact seven proposed files. After approval,
   run Pint in write mode on only the four named files. Inspect the resulting
   diff before making any documentation correction; stop if it contains a
   semantic change or an additional source path.
7. Correct only SHA traceability in the three named documents: retain the
   observed pre-commit execution context at `6e91fe0` where applicable, and
   identify `2545c6a56ccb186f35bbdbe76f3598e9c3d5dcc3` as the committed
   candidate containing the fallback and middleware correction. Do not change
   claimed test counts, scope, requirement status, or future-gap status unless
   an observed rerun proves an existing statement wrong.
8. Rerun repository-wide Pint test, Composer validation, PHP syntax for the
   seven changed PHP/Markdown-adjacent source paths as applicable, operator
   route listing, MVP-04E, MVP-04D, MVP-04C, MVP-04B, Operator portal, Operator
   foundation, WP-02 security, and architecture suites separately. Recheck the
   graph/source path, privacy-sensitive worklist projection, `git diff --check`,
   and final path allowlist.
9. Re-read the complete final diff and task unchanged. Report candidate
   acceptance readiness only when every required check passes and the diff is
   limited to the seven permitted paths. Do not commit or push.

## Acceptance criteria

- [ ] This task and the three immutable MVP-04E contracts validate; the
      candidate ancestry, inherited Pint provenance, graph status, and exact
      affected paths are observed before editing.
- [ ] The four named Member files are the only formatted source files, and
      their diff is generated by the existing Pint configuration with no
      behavioral, authorization, data, UI-copy, route, or interface change.
- [ ] Each MVP-04E evidence/status document accurately distinguishes the
      `6e91fe0` pre-commit verification context from committed candidate
      `2545c6a`, without overstating acceptance or changing open gaps.
- [ ] Repository-wide Pint test passes; MVP-04E and all stated regression,
      security, architecture, Composer, syntax, route, graph/source, privacy,
      diff, and path-allowlist checks pass with observed output.
- [ ] The default isolated MVP-04E denial matrix remains 6 passing tests / 61
      assertions: revoked shift is empty HTTP 200 while revoked site, portal
      permission, forged session, and suspended account are HTTP 403 without
      worklist or internal-detail leakage.
- [ ] No product behavior, production configuration, real secret, dependency,
      generated formatter configuration, commit, or push is created, modified,
      exposed, or used.

## Verification

- Method: Validate all four task contracts; establish baseline/candidate
  ancestry and inherited formatter provenance; query the current Codebase
  Memory graph and authorization path; run scoped Pint write mode only after
  approval, then repository-wide Pint test, Composer validation, PHP syntax,
  operator route list, the MVP-04E/MVP-04D/MVP-04C/MVP-04B/Operator portal/
  Operator foundation/WP-02 security/architecture suites separately,
  privacy-sensitive worklist searches, final path allowlist, and
  `git diff --check`.
- Expected result: Only the four inherited Member source files receive
  formatter-only changes; the three bounded evidence/status documents identify
  the committed candidate correctly; every required check passes; the private
  MVP-04E denial matrix remains unchanged and passing; and no product,
  configuration, dependency, commit, or push behavior changes.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, accepted baseline, reviewed candidate, selected runtime/model
  when verifiable, approval decision, capabilities, outcome, affected paths,
  exact before/after Pint result, task validation, Codebase Memory MCP and
  ponytail evidence, exact checks/results, unrun checks, residual risks, and
  manual follow-up.
- Treat any non-style source change, any path outside the seven-path allowlist,
  a failed repository-wide Pint test, inaccurate candidate traceability, a
  failed MVP-04E denial matrix, skipped mandatory verification, or a claimed
  rather than observed passing result as unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report the final worktree state and readiness for owner-controlled commit. After
the owner supplies a closure commit SHA, review it and its full chain against
accepted baseline `8ba97255bc1961945d9802a37d504442e3e1cf55`, the three prior
MVP-04E contracts, this task, and observed runtime evidence before accepting a
new baseline or selecting another vertical slice.
