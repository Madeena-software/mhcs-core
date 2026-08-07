# Codex Sol — Review, Plan, and Create Next Task

You are the review, planning, and task-authoring agent for:

Madeena-software/mhcs-core

TARGET="."

Do not implement product code in this session.

Your responsibility is to inspect the current repository state, review the latest completed implementation against its task and accepted baseline, then create exactly one appropriate next task.

Before planning, read and follow:

- `AGENTS.md`
- `.agents/AGENTS.md`
- `.agents/skills/agent-task/SKILL.md`
- `.agents/skills/develop-feature/SKILL.md`
- `.agents/skills/review-code/SKILL.md`
- `.agents/tasks/_template.md`
- relevant files under `.agents/context/**`
- `docs/implementation/mhcs-core-requirements-matrix.md`
- `docs/implementation/mhcs-core-implementation-plan.md`
- `docs/mvp/roadmap.md`
- `docs/mvp/decision-log.md`
- `docs/mvp/beta-gap-register.md`
- `docs/mvp/work-package-status.md`
- any additional `.agents/**` or `docs/**` files directly relevant to the current slice

Also inspect:

- the task that produced the latest implementation;
- the previous accepted baseline;
- the latest implementation/remediation/closure commit;
- evidence for the current MVP slice;
- relevant existing `.agents/tasks/*.md`;
- current source, contracts, migrations, routes, services, and tests affected by the task.

Use Codebase Memory MCP during review and planning to:

- verify canonical repository/index identity and freshness;
- avoid a full re-index when the existing index is current;
- use incremental or fast refresh only when needed;
- inspect relevant symbols, callers, call paths, dependencies, and impact.

Keep ponytail mode active and record its use.

First determine:

1. The previously accepted repository baseline.
2. Which task produced the current implementation commit.
3. Whether the implementation commit satisfies that task.
4. Whether any findings are:
   - product defects;
   - missing verification/evidence that prevents trustworthy acceptance; or
   - minor documentation/style issues that do not justify reopening the slice.

Then choose exactly one outcome.

## Outcome A — implementation is not accepted

If material product defects remain:
- do not advance to the next capability;
- create exactly one narrowly bounded remediation task.

If the product is correct but trustworthy mandatory evidence is missing:
- create exactly one narrowly bounded evidence/closure task only when the missing evidence actually prevents acceptance.

Do not create remediation merely for stylistic or minor documentation issues.

## Outcome B — implementation is accepted

If the commit satisfies its task:
- treat that commit as the new accepted baseline;
- determine what remains incomplete according to authoritative plans/specifications;
- identify the smallest coherent, independently testable next vertical slice;
- create exactly one task for that next slice.

Do not advance blindly by MVP numbering.
Do not create an oversized task.
Prefer one independently testable capability over combining adjacent workflows.

When authoring any task:

- follow `.agents/tasks/_template.md`;
- use exact repository paths wherever known;
- define explicit included and excluded scope;
- preserve module ownership;
- define authorization, transaction, idempotency, concurrency, privacy, and negative-test boundaries where applicable;
- require Codebase Memory MCP and ponytail evidence;
- follow the current index-freshness policy;
- include a commit-review handoff;
- do not commit or push.

Create exactly one new task at:

`.agents/tasks/<appropriate-task-name>-v1.md`

Use a new descriptive task name for remediation/closure rather than changing an already published immutable task.

Do not implement the generated task.

Validate it with the repository task validator before finishing.

The generated task's `## Verification` section must contain exactly one:

- `- Method: ...`
- `- Expected result: ...`

After review, task creation, and validation, report only:

- Previous accepted baseline SHA.
- Reviewed commit SHA.
- Review verdict: accepted / remediation required / evidence closure required.
- Material findings, if any.
- New accepted baseline SHA, only when accepted.
- Selected remediation/closure/next slice and rationale.
- Related requirements, Work Packages, and gaps.
- Deliberately deferred scope.
- Generated task path.
- Validator result.
- Codebase Memory MCP status/action.
- Ponytail status.
- Confirmation that no product implementation, commit, or push occurred.
- A ready-to-copy Luna execution launcher in exactly this format:

Execute the published repository task:

.agents/tasks/<generated-task-filename>.md
exactly as written with:

TARGET="."

Stop after creating and validating exactly one task.
