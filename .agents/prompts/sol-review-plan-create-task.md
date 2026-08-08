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

If `.agents/skills/graphify/SKILL.md` exists, read and follow it for Graphify-specific usage. Do not treat its absence as permission to invent Graphify results.

Also inspect:

- the task that produced the latest implementation;
- the previous accepted baseline;
- the latest implementation/remediation/closure commit;
- evidence for the current MVP slice;
- relevant existing `.agents/tasks/*.md`;
- current source, contracts, migrations, routes, services, and tests affected by the task.

## Intelligence routing

Use Graphify for documentation-oriented discovery and relationship analysis.

Use Graphify during review and planning to:

- identify requirements, architecture decisions, Work Packages, MVPs, gaps, module responsibilities, and related documentation relevant to the current slice;
- trace relationships across `.agents/context/**`, `docs/implementation/**`, `docs/mvp/**`, and other relevant Markdown documentation;
- narrow the documentation set before broad manual reading;
- identify source documents that must be opened directly before making requirement, architecture, acceptance, or planning claims;
- refresh or update the graph when it is stale relative to relevant documentation changes;
- reuse a current graph rather than rebuilding it unnecessarily.

Use Codebase Memory MCP for implementation-oriented code intelligence.

Use Codebase Memory MCP during review and planning to:

- verify canonical repository/index identity and freshness;
- avoid a full re-index when the existing index is current;
- use incremental or fast refresh only when needed;
- inspect relevant symbols, callers, call paths, dependencies, routes, services, tests, and implementation impact;
- map authoritative requirements and architecture constraints to the current implementation surface.

Keep ponytail mode active and record its use.

## Derived-intelligence authority boundary

Graphify and Codebase Memory MCP are derived indexes and discovery aids. They are not repository authority.

Before making an acceptance finding, requirement claim, architecture claim, gap claim, Work Package claim, or task requirement:

1. Use Graphify or Codebase Memory MCP to identify the relevant evidence.
2. Open and inspect the exact authoritative repository files directly.
3. Base the final claim on the repository files and observed implementation evidence.

If Graphify, Codebase Memory MCP, generated evidence, and repository files disagree:

- repository instructions and authoritative repository files take precedence;
- current implementation behavior must be determined from source, configuration, migrations, routes, tests, command output, and version-control state;
- do not silently reconcile material inconsistencies;
- report any inconsistency that affects architecture, security, authorization, privacy, data integrity, product behavior, acceptance, or next-slice planning.

## Intelligence freshness policy

For both Graphify and Codebase Memory MCP:

- reuse an existing graph/index when it is current;
- refresh incrementally when relevant tracked files changed;
- do not perform a full rebuild when current derived state is already usable;
- record any refresh or update performed;
- never treat stale derived state as authoritative;
- if freshness cannot be established, inspect the repository directly before making material claims and report the limitation.

## Review and planning procedure

First determine:

1. The previously accepted repository baseline.
2. Which task produced the current implementation commit.
3. Whether the implementation commit satisfies that task.
4. Whether any findings are:
   - product defects;
   - missing verification/evidence that prevents trustworthy acceptance; or
   - minor documentation/style issues that do not justify reopening the slice.

Use this evidence order:

1. Use Graphify to identify the requirements, decisions, architecture constraints, Work Packages, gaps, module responsibilities, and documentation most relevant to:
   - the task being reviewed;
   - the reviewed commit;
   - any material finding; and
   - candidate next slices.
2. Open and read the exact authoritative repository files identified by Graphify.
3. Use Codebase Memory MCP to map those requirements and boundaries to the current implementation, tests, callers, routes, dependencies, and change impact.
4. Inspect the exact source, tests, migrations, routes, configuration, and version-control evidence needed to verify behavior.
5. Expand documentation or source inspection only when the gathered evidence leaves a material question unresolved.

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
- use Graphify to identify relationships among remaining requirements, Work Packages, gaps, decisions, module boundaries, and candidate next slices;
- verify the selected candidate against the exact authoritative repository documents;
- use Codebase Memory MCP to verify the relevant implementation boundary, dependency surface, and likely impact;
- identify the smallest coherent, independently testable next vertical slice;
- create exactly one task for that next slice.

Do not advance blindly by MVP numbering.
Do not create an oversized task.
Prefer one independently testable capability over combining adjacent workflows.

## Task authoring requirements

When authoring any task:

- follow `.agents/tasks/_template.md`;
- use exact repository paths wherever known;
- define explicit included and excluded scope;
- preserve module ownership;
- define authorization, transaction, idempotency, concurrency, privacy, and negative-test boundaries where applicable;
- require Codebase Memory MCP and ponytail evidence;
- require Graphify evidence when documentation relationships, requirements, architecture decisions, Work Packages, gaps, or cross-document planning materially constrain the task;
- require direct inspection of authoritative repository files before implementation decisions are made from derived graph results;
- follow the current Graphify and Codebase Memory MCP freshness policy;
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

## Final report

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
- Graphify status/action and freshness.
- Codebase Memory MCP status/action and freshness.
- Ponytail status.
- Confirmation that repository authority was checked directly for material claims derived from Graphify or Codebase Memory MCP.
- Confirmation that no product implementation, commit, or push occurred.
- A ready-to-copy Luna execution launcher in exactly this format:

Execute the published repository task:

.agents/tasks/<generated-task-filename>.md
exactly as written with:

TARGET="."

Stop after creating and validating exactly one task.
