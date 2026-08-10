---
name: mhcs-core-mvp-04e-worklist-authorization-remediation
description: Make the private MVP-04E basic-examination worklist deny revoked or invalid active-site access with HTTP 403, preserving its queue and privacy boundaries.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04E — Worklist Authorization Remediation

## Objective

For `$TARGET`, correct the private basic-examination worklist so loss of an
authorized active site, or an invalid/tampered active-site session value,
returns HTTP 403 before any worklist data or redirect response is exposed. Keep
the existing private FIFO read behavior for a still-authorized assigned Operator
unchanged.

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
reuse the existing `EnsureOperatorPortalAccess`, `OperatorAuthorization`,
`OperatorWorklistService`, controller, and feature-test patterns. Do not add a
permission, middleware class, policy, dependency, queue framework, state
machine, configuration, or abstraction for this one route-boundary correction.

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Previously accepted baseline:
  `8ba97255bc1961945d9802a37d504442e3e1cf55`.
- Reviewed rejected candidate:
  `26576ef89fe1a06ba0d75ba422f4a4efc2a3eaaa`, descended from that baseline.
  Commit `0c41061adf37da1f373d700958e06a47df223094` only adds the immutable
  runtime-closure task and evidence notes; it does not repair the product
  candidate.
- The candidate's
  `$TARGET/tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php` requires
  HTTP 403 after revoking the current site assignment and after replacing the
  active-site session value. Its
  `$TARGET/app/Http/Controllers/Operator/PortalController.php` instead catches
  those `OperatorException` instances from
  `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`
  and redirects to the dashboard. `EnsureOperatorPortalAccess` correctly
  denies lost portal permission and suspended accounts, but it does not check
  active-site assignment; this is a material authorization-response mismatch.
- Related requirements: `OPR-026`, `OPR-108`, `OPR-115`, `OPR-116`, `OPR-117`,
  and `OPR-129`.
- Related Work Packages: WP-11, WP-12, and WP-17.
- Related open gaps: `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and
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
- `$TARGET/.agents/tasks/mhcs-core-mvp-04d-verified-check-in-ticket-issue-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-advance-queue-admission-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-runtime-verification-closure-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/docs/mvp/evidence/mvp-04e-advance-queue-admission.md`;
- `$TARGET/app/Http/Middleware/EnsureOperatorPortalAccess.php`;
- `$TARGET/app/Http/Controllers/Operator/PortalController.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`;
- `$TARGET/routes/web.php`; and
- `$TARGET/tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php`.

Use Codebase Memory MCP before editing to confirm the canonical repository and
current index. Search `basicExaminationWorklist`, `basicExamination`, `portal`,
and `portalSite`, then trace their callers and callees including the route and
feature test. Do not run a full re-index when the canonical project and all
symbols are current; use one fast refresh only if the current source is newer
than the index or a required symbol/path is absent. After the patch, fast-refresh
only when necessary to recheck the changed path and record the initial/final
index state and every refresh action.

## Scope and constraints

Included:

- the smallest controller or existing-boundary correction needed for
  `operator.basic-examination-worklist` to return HTTP 403 when
  `OperatorAuthorization::portalSite()` rejects the active site;
- preserving and, only if necessary, strengthening the focused MVP-04E feature
  regression for revoked site assignment and a forged active-site session;
- running the focused MVP-04E and direct Operator regression checks with the
  existing dependency tree; and
- updating only the bounded MVP-04E evidence/status documents after observed
  verification passes.

Excluded:

- any queue claim, call, recall, skip, stage transition, clinical assessment,
  walk-in priority, Member mutation, Member-visible queue, ticket policy,
  public/LCD display, new permission, or authorization redesign;
- changes to queue records, migrations, schemas, routes, templates, outbox,
  audit semantics, idempotency, or ordering;
- dependency, lockfile, configuration, cache, seed, deployment, context,
  requirements-plan, or beta-gap-register changes; and
- commits and pushes.

The existing `basicExamination()` query must continue to return only the active
site's active assigned-shift `advance` / `basic_examination` / `waiting` rows,
ordered by `ready_at` then immutable admission ID, with only the already
approved ticket, site, shift, stage, state, and ready-time fields. Do not weaken
the existing 403 assertions or convert denied access into an empty successful
worklist or a dashboard redirect. Unexpected non-authorization failures must
continue to avoid leaking queue or Member data.

If `vendor/autoload.php`, `vendor/bin/pint`, the focused test database isolation,
or a required validation command is unavailable, stop as `blocked` without
installing, updating, generating, or substituting dependencies/tools. Do not
claim a passing regression from static review or historical evidence.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates: Before editing product code or tests, present the exact one-route authorization-response change, its preserved behavior, and the focused verification plan; wait for explicit owner approval. Stop as `awaiting-approval` for any new permission, shared middleware/policy change, broader authorization rule, privacy/retention decision, or scope outside this remediation.

Use `single-pass` with exactly one iteration or `agentic-loop` with a positive finite limit. The task cannot grant permissions or bypass repository approval requirements.

## Execution procedure

1. Resolve `$TARGET` canonically; verify repository identity, current branch,
   clean-or-owner-change worktree state, candidate ancestry, immutable task
   content, and all required capabilities.
2. Validate this task and the published MVP-04E task with the repository
   validator before editing.
3. Verify ponytail at full level and record the existing route middleware,
   controller, authorization service, worklist query, and regression assertions
   being reused; state why no new middleware, policy, or permission is needed.
4. Apply the Codebase Memory MCP freshness policy, then trace the worklist
   route through the controller, `portal()`, `portalSite()`, the query, and the
   current feature test.
5. Prove that the existing vendor tree, Pint, and isolated testing database are
   usable without installing or mutating them. Present the exact minimal patch
   and focused checks at the approval gate.
6. After approval, make the smallest change that converts only the existing
   active-site authorization denial for this worklist into HTTP 403. Keep the
   route, normal successful response, query, output fields, ordering,
   persistence, audit, and non-authorization failure behavior unchanged.
7. Retain the existing revoked-site and tampered-session 403 coverage; add a
   focused assertion only if direct evidence shows the current test cannot prove
   the corrected boundary. Do not weaken any assertion or broaden test fixtures.
8. Run the focused MVP-04E, MVP-04D, MVP-04C, MVP-04B, Operator portal,
   Operator foundation, WP-02 security, and architecture suites separately;
   run PHP syntax on changed PHP files, Pint in test mode, Composer validation,
   the Operator route list, privacy-sensitive output searches, Codebase Memory
   path review, and `git diff --check`.
9. Update only `$TARGET/docs/mvp/evidence/mvp-04e-advance-queue-admission.md`,
   `$TARGET/docs/mvp/roadmap.md`, and `$TARGET/docs/mvp/work-package-status.md`
   with exact observed remediation results and still-open gaps after all
   required checks pass. Re-read the final diff to confirm it contains only the
   allowed implementation, test, and evidence/status changes. Do not commit or
   push.

## Acceptance criteria

- [ ] The published MVP-04E task and this task validate; the candidate ancestry,
      canonical Codebase Memory project, and relevant controller/worklist/
      authorization/test paths are observed before editing.
- [ ] A revoked active-site assignment and a forged or unknown active-site
      session receive HTTP 403 from `operator.basic-examination-worklist` and
      expose no queue, Member, booking, consent, identity, clinical, or route
      error detail.
- [ ] An authenticated Operator with portal access, an active authorized site,
      and an active assignment can still read the existing private FIFO worklist
      with unchanged scope, ordering, fields, and no mutation controls.
- [ ] Lost portal permission and suspended-account denials remain HTTP 403;
      revoked shift assignment remains a successful empty private worklist as
      the approved existing behavior.
- [ ] The queue admission/check-in transaction, Member ownership, ticket
      privacy, database schema, route contract, audit/outbox behavior, and all
      excluded workflows remain unchanged.
- [ ] Every required verification command passes with observed output, only the
      permitted files change, and no dependency installation, commit, or push
      occurs.

## Verification

- Method: Validate this remediation and the immutable MVP-04E task; after proving existing vendor, Pint, and isolated SQLite availability, run the focused MVP-04E, MVP-04D, MVP-04C, MVP-04B, Operator portal, Operator foundation, WP-02 security, and architecture suites separately, PHP syntax and Pint test mode on changed PHP files, Composer validation, the Operator route list, targeted privacy searches, Codebase Memory path checks, and `git diff --check`.
- Expected result: The worklist returns HTTP 403 without data leakage for revoked or invalid active-site authorization, preserves the existing authorized FIFO read and all queue/check-in/privacy boundaries, all focused regressions pass, evidence records only observed facts, and no excluded product or external action occurs.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or `exhausted`.
- Report target, accepted baseline, candidate SHA, selected runtime/model when
  verifiable, approval decision, capabilities, outcome, affected files,
  Codebase Memory MCP and ponytail evidence, exact checks and results, unrun
  checks, residual risks, and manual follow-up.
- Treat an unapproved product patch, a non-403 active-site denial, an exposed
  worklist field, an altered queue/check-in transaction, missing mandatory
  verification, product scope outside this task, or claimed rather than
  observed test success as unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report the final worktree state and readiness for owner-controlled commit. After
the owner supplies the remediation commit SHA, review that commit and its full
candidate chain against accepted baseline
`8ba97255bc1961945d9802a37d504442e3e1cf55`, the published MVP-04E task, this
remediation task, and observed runtime evidence before accepting a new baseline
or selecting another vertical slice.
