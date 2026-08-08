---
name: mhcs-core-mvp-04e-runtime-verification-closure
description: Close the MVP-04E acceptance-evidence gap by running its required existing toolchain checks against the committed implementation without changing product code.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04E — Runtime Verification Closure

## Objective

For `$TARGET`, verify the committed MVP-04E advance-queue-admission
implementation at `26576ef89fe1a06ba0d75ba422f4a4efc2a3eaaa` with the exact
focused Laravel, migration, route, formatting, syntax, privacy, and graph
checks required by its published task. Record only observed results in its
bounded evidence/status documentation. Do not alter product source, schema,
routes, templates, tests, dependencies, lockfiles, or task definitions.

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
the required outcome is evidence only, so reuse the existing Composer vendor
tree, PHP/Laravel commands, database configuration, source, tests, graph, and
documentation. Do not add a dependency, test helper, retry wrapper, fixture,
or product-code workaround.

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Previously accepted baseline: `8ba97255bc1961945d9802a37d504442e3e1cf55`.
- Closure candidate: `26576ef89fe1a06ba0d75ba422f4a4efc2a3eaaa`, which implements
  the published task
  `mhcs-core-mvp-04e-advance-queue-admission-v1.md`.
- The candidate adds an Operator-owned advance admission and initial history to
  the existing Member check-in/paper-ticket transaction, plus a private,
  assigned-shift basic-examination waiting worklist. It must not be accepted
  yet: its committed evidence states that `vendor/autoload.php` was absent, so
  the mandatory focused Laravel suites, Pint, fresh SQLite migration, and route
  verification did not run.
- Existing direct evidence includes
  `$TARGET/app/Modules/Operator/Application/Services/OperatorCheckInTicketService.php`,
  `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`,
  `$TARGET/database/migrations/2026_08_07_000003_create_operator_queue_admissions_table.php`,
  `$TARGET/routes/web.php`, and
  `$TARGET/tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php`.
- Related requirements: `OPR-020`, `OPR-026`, `OPR-108`, `OPR-115`, `OPR-116`,
  `OPR-117`, and `OPR-129`.
- Related Work Packages: WP-11, WP-12, and WP-17.
- Related gaps remain open: `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and
  `MVP-GAP-024`.

Read completely before verification or documentation changes:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/review-code/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04d-verified-check-in-ticket-issue-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-advance-queue-admission-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/docs/mvp/evidence/mvp-04d-verified-check-in-ticket-issue.md`;
- `$TARGET/docs/mvp/evidence/mvp-04e-advance-queue-admission.md`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorCheckInTicketService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorShiftAssignmentService.php`;
- `$TARGET/app/Modules/Member/Application/Services/Mvp04AttendanceService.php`;
- `$TARGET/app/Shared/Infrastructure/Idempotency/DatabaseIdempotencyStore.php`;
- `$TARGET/app/Http/Controllers/Operator/PortalController.php`;
- `$TARGET/routes/web.php`;
- `$TARGET/database/migrations/2026_08_07_000003_create_operator_queue_admissions_table.php`;
- `$TARGET/tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php`;
- `$TARGET/tests/Feature/Operator/Mvp04dVerifiedCheckInTicketIssueTest.php`;
- `$TARGET/tests/Feature/Operator/Mvp04cPaperConsentConfirmationTest.php`;
- `$TARGET/tests/Feature/Operator/Mvp04bIdentityVerificationTest.php`;
- `$TARGET/tests/Feature/Operator/Mvp04OperatorPortalTest.php`;
- `$TARGET/tests/Operator/Mvp04OperatorFoundationTest.php`;
- `$TARGET/tests/Security/Wp02SecurityTest.php`; and
- `$TARGET/tests/Architecture/FoundationArchitectureTest.php`.

Use Codebase Memory MCP to verify canonical project/root and index freshness
before verification. The current MVP-04E graph at the closure candidate has
4,052 nodes and 10,599 edges. Use no refresh if the graph reports the canonical
root and candidate HEAD and includes `OperatorCheckInTicketService::issue` and
`OperatorWorklistService::basicExamination`; use a fast refresh only when the
candidate source changed or a required symbol is absent; use a full re-index
only when the graph is missing or fast recovery fails. Trace ticket issue to
Member check-in, queue admission/history/audit/outbox writes, and the private
worklist route before recording final evidence. Record initial and final graph
status and every refresh action.

## Scope and constraints

Included:

- proving that the existing executable dependency tree is present before
  running framework commands, without installing, updating, or generating it;
- validating the immutable MVP-04E task and confirming candidate ancestry,
  clean-or-owner-change worktree status, and the exact candidate diff;
- running the MVP-04E required checks separately: focused feature suites,
  regression suites, security and architecture suites, fresh isolated SQLite
  migration, route listing, PHP syntax, Pint, Composer metadata validation,
  privacy-sensitive output searches, Codebase Memory traces, and diff checks;
- recording exact pass, failure, blocked, and unrun results only in
  `$TARGET/docs/mvp/evidence/mvp-04e-advance-queue-admission.md`,
  `$TARGET/docs/mvp/roadmap.md`, and
  `$TARGET/docs/mvp/work-package-status.md`; and
- re-reviewing the candidate against the published MVP-04E acceptance criteria
  after every required check passes.

Excluded:

- any production-code, migration, route, view, test, fixture, dependency,
  Composer lockfile, configuration, deployment, database-seed, or task-file
  change;
- `composer install`, `composer update`, dependency download, package-cache
  mutation, tool substitution, disabling tests, weakening assertions, or
  claiming a pass from prior documentation alone;
- queue claims/calls/skips, clinical examination, walk-ins, public/LCD data,
  Member-facing behavior, privacy/retention decisions, and all later MVP work;
- modifying `.agents/context/**` or `docs/implementation/**`; and
- commits or pushes.

The existing MVP-04D and MVP-04E implementation remains immutable evidence.
If any required executable dependency, service, test database isolation, or
verification command is unavailable, stop as `blocked`; do not repair the
environment or product. If a check fails, stop as `failed` with no product
change and record only the observed failure when the documentation boundary is
safe to update.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates: None. Stop as `blocked` unless the agent can prove that all
  framework-mutating verification runs use the isolated SQLite testing database
  before invoking `migrate:fresh`.

Use `single-pass` with exactly one iteration or `agentic-loop` with a positive finite limit. The task cannot grant permissions or bypass repository approval requirements.

## Execution procedure

1. Resolve `$TARGET` canonically. Verify repository identity, candidate HEAD,
   baseline ancestry, immutable task content, and clean or owner-change
   worktree state.
2. Validate this task and the MVP-04E task with the repository validator before
   any documentation edit.
3. Verify ponytail at full level and record that no product change or dependency
   action is justified for an evidence-only closure.
4. Inspect Codebase Memory MCP status. Apply the declared freshness policy and
   trace the candidate issue, Member transition, queue admission/history,
   audit/outbox, worklist, authorization, and route paths.
5. Verify `vendor/autoload.php`, `php artisan`, `vendor/bin/pint`, Composer,
   and the required test database configuration are actually usable. Stop as
   `blocked` without installing or modifying dependencies if any are absent.
6. Run separately: `composer validate --no-check-publish`; the MVP-04E, MVP-04D,
   MVP-04C, MVP-04B, Operator portal, Operator foundation, WP-02 security, and
   architecture test files; PHP syntax for changed PHP files; Pint in test mode;
   `php artisan migrate:fresh --env=testing --database=sqlite --no-interaction`;
   `php artisan route:list --path=operator --no-ansi`; targeted sensitive-data
   searches; and `git diff --check` against the accepted baseline and final
   worktree.
7. Inspect actual outputs against every MVP-04E acceptance criterion. Treat a
   skipped, timed-out, or non-isolated destructive check as unverified, not as a
   pass. Do not make a product fix.
8. Only when every required check passes, update the three allowed MVP evidence/
   status documents with exact commands, results, graph status, unrun checks,
   and remaining gaps. Otherwise record only a bounded observed blocker or
   failure where safe, leaving all product files untouched.
9. Re-read the final diff to confirm it contains only permitted documentation
   updates. Do not commit or push.

## Acceptance criteria

- [ ] The closure candidate is proven to descend from
      `8ba97255bc1961945d9802a37d504442e3e1cf55`, both immutable tasks validate,
      and Codebase Memory MCP verifies the canonical current graph and required
      paths.
- [ ] Existing dependencies and isolated SQLite test configuration are verified
      before framework checks; no dependency installation, cache mutation, or
      non-test database reset occurs.
- [ ] Every focused MVP-04E and required regression, security, architecture,
      migration, route, syntax, Pint, Composer, privacy, and diff check passes
      with observed output; no unrun mandatory check is represented as passing.
- [ ] The candidate continues to provide exactly one atomic, private, FIFO,
      authorization-scoped advance basic-examination admission without exposing
      Member, booking, consent, identity, clinical, public-display, claim/call,
      walk-in, or later-stage behavior.
- [ ] Only the three permitted evidence/status documents may change, and they
      accurately retain all unresolved gaps and verification limits.
- [ ] No product implementation, dependency, commit, or push occurs.

## Verification

- Method: Validate both tasks; confirm the exact candidate ancestry and Codebase Memory graph; after proving existing vendor and isolated SQLite availability, run Composer validation, all MVP-04E required focused/regression/security/architecture suites separately, fresh testing SQLite migration, operator route list, PHP syntax, Pint test mode, privacy-sensitive searches, and diff checks; inspect the final documentation-only diff.
- Expected result: All required commands complete successfully against `26576ef89fe1a06ba0d75ba422f4a4efc2a3eaaa`, proving the accepted MVP-04E transaction, idempotency, FIFO, authorization, rollback, and privacy boundaries; evidence documents record exact observed results and remaining gaps, while no product, dependency, commit, or push change occurs.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or `exhausted`.
- Report target, baseline, closure-candidate SHA, selected runtime/model when
  verifiable, capabilities, outcome, exact commands and outputs, graph and
  ponytail evidence, documentation paths changed, unrun checks, residual risks,
  and manual follow-up.
- Treat an absent vendor tree, unproven SQLite isolation, failed required test,
  skipped required check, product-file diff, or claimed rather than observed
  result as unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report final worktree state and readiness for owner-controlled documentation
commit. After the closure task succeeds, review
`26576ef89fe1a06ba0d75ba422f4a4efc2a3eaaa` against
`8ba97255bc1961945d9802a37d504442e3e1cf55`, the published MVP-04E task, and
the observed closure evidence before accepting a new implementation baseline or
selecting another vertical slice.
