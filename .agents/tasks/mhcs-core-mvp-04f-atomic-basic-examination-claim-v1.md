---
name: mhcs-core-mvp-04f-atomic-basic-examination-claim
description: Let an assigned Operator atomically reserve one private waiting basic-examination admission without starting clinical work or exposing queue data.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04F — Atomic Basic-Examination Claim

## Objective

For `$TARGET`, extend the accepted MVP-04E private advance-admission worklist
with exactly one Operator-owned queue action: an assigned Operator may atomically
claim one eligible `basic_examination` / `waiting` admission at the active site
and assigned shift. A competing Operator cannot claim the same admission, and
an Operator cannot hold more than one claimed clinical-stage admission.

The claim is a private reservation only. It must preserve the admission's
existing `basic_examination` stage and `waiting` state, record the claiming
Operator and occurrence time, append one `claimed` history event, and append
matching local audit/outbox evidence in the same idempotent database boundary.
The claimant can see its own claimed row; other Operators cannot see or claim
it. No call, start, examination value, station label, skip, recall, release,
walk-in, public display, or Member-visible behavior is introduced.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `graphify`
  - `codebase-memory-mcp`
  - `ponytail`
- Ordered model preferences: None.
- Require preferred model: `false`

Graphify, Codebase Memory MCP, and ponytail are mandatory. Keep ponytail at
full level: reuse the existing queue-admission tables, `DatabaseIdempotencyStore`,
`OperatorAuthorization`, `OperatorWorklistService`, private portal route group,
audit/outbox primitives, migration convention, and MVP-04E fixture. Do not add
a queue framework, policy, permission, generic workflow engine, event bus,
dependency, configuration surface, or second queue abstraction.

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Previously accepted baseline: `8ba97255bc1961945d9802a37d504442e3e1cf55`.
- Newly accepted closure baseline: `882a438947fc40fc43ba2e4e8864ce5ad18b2569`,
  descending from MVP-04E remediation candidate
  `2545c6a56ccb186f35bbdbe76f3598e9c3d5dcc3`.
- The accepted closure validates all four MVP-04E task contracts and passes
  repository-wide Pint, Composer validation, PHP syntax, private-route listing,
  MVP-04E (6 tests/61 assertions), MVP-04D (9/83), MVP-04C (6/64), MVP-04B
  (16/84), Operator portal (8/63), Operator foundation (15/56), WP-02 security
  (24/103), and architecture (6/1,573) checks.
- The existing `operator_queue_admissions` row stores ticket, local site,
  Member schedule, queue class, stage, state, and `ready_at`; its companion
  history stores event type, previous/new state, Operator profile, operation
  identity, and occurrence time. It currently admits one advance ticket in
  `basic_examination` / `waiting` state with FIFO order by `ready_at`, then ID.
- Directly inspected Operator rules require each ticket to record its claimed
  Operator and transition history; claiming a waiting ticket is atomic, a
  competing claim fails and refreshes the worklist, and an Operator may hold
  only one claimed clinical-stage ticket. They separately define `called` and
  `in_service`; this slice must not infer either transition from a claim.
- Related requirements: `OPR-026`, `OPR-108`, `OPR-115`, `OPR-116`,
  `OPR-117`, and `OPR-129`.
- Related Work Packages: WP-11, WP-12, and WP-17.
- Open gaps remain: `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and
  `MVP-GAP-024`.

Read completely before planning or changing files:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/skills/fix-bug/SKILL.md`;
- `$TARGET/.agents/skills/graphify/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-advance-queue-admission-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-worklist-authorization-remediation-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-worklist-denial-matrix-remediation-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04e-acceptance-evidence-formatting-closure-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/docs/mvp/evidence/mvp-04e-advance-queue-admission.md`;
- `$TARGET/database/migrations/2026_08_07_000003_create_operator_queue_admissions_table.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorCheckInTicketService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`;
- `$TARGET/app/Http/Controllers/Operator/PortalController.php`;
- `$TARGET/app/Http/Middleware/EnsureOperatorPortalAccess.php`;
- `$TARGET/app/Shared/Infrastructure/Idempotency/DatabaseIdempotencyStore.php`;
- `$TARGET/app/Shared/Audit/DatabaseAuditStore.php`;
- `$TARGET/app/Shared/Infrastructure/Outbox/DatabaseOutboxStore.php`;
- `$TARGET/routes/web.php`;
- `$TARGET/resources/views/operator/basic-examination-worklist.blade.php`;
- `$TARGET/tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php`; and
- `$TARGET/tests/Architecture/FoundationArchitectureTest.php`.

Use Graphify first for the documentation relationship among MVP-04E, WP-11,
WP-12, WP-17, the listed requirements, gaps, and queue rules. The existing
graph may be queried directly when current. If its incremental update is blocked
because the installed CLI lacks semantic-extraction credentials, do not install
dependencies or request credentials: record the limitation and inspect the
authoritative documents above directly before making a planning or implementation
decision. Use Codebase Memory MCP to verify canonical project/index status and
trace the existing check-in, worklist, authorization, idempotency, audit, and
outbox boundaries; use a fast refresh only if source changes or required symbols
are absent. Derived graphs are discovery aids, never authority.

## Scope and constraints

Included:

- one additive migration that gives an admission a nullable claiming
  `operator_profile_id` reference and claim occurrence time, with a database
  uniqueness guarantee that prevents one profile from holding two active claims;
- one minimal Operator application service method using the existing
  idempotency, active-site, portal-permission, active account, site assignment,
  and assigned-shift boundaries to atomically reserve a target unclaimed waiting
  admission;
- one private, authenticated POST claim endpoint and smallest worklist form/UI
  affordance, accepting only an operation identifier and a route admission ID;
- filtering the private worklist so an unclaimed eligible row is claimable,
  the claimant can see its own reservation, and other Operators cannot see or
  claim it;
- one append-only history event (`claimed`, with state retained as `waiting`),
  one audit event, and one outbox event in the same successful transaction; and
- focused tests for success, exact replay, concurrent/competing claims, one
  claim per Operator, active-site/assigned-shift/account/portal denial, stale
  or foreign admission denial, rollback, FIFO preservation, and privacy.

Excluded:

- changing queue stage or state to `called`, `in_service`, `awaiting_ai`,
  `deferred`, or `completed`; calling, recall, skip, release, no-show, shift
  close, station selection/label, clinical assessment/vital signs, X-ray,
  payment, earnings, walk-ins, Member booking mutation, or Member-visible data;
- public/LCD display, ticket-number allocation/reprint behavior, a new role or
  permission, generic policy/middleware redesign, generic queue state machine,
  configuration, dependency, migration rewrites, or FHIR behavior; and
- commits, pushes, deployment, real secrets, and production configuration.

Claims must fail closed without exposing Member, booking, consent, identity,
clinical, other-Operator, or internal exception detail. The existing private
worklist must continue to select only ticket, site, shift times, stage, state,
and ready time plus the opaque local admission ID strictly needed to submit the
claim; it must never expose claimant identity to other Operators. Preserve the
advance FIFO ordering and all accepted MVP-04E admission behavior.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates: Before any migration, authorization/worklist, route/controller,
  view, test, or evidence edit, present the exact proposed persisted claim fields,
  uniqueness strategy, HTTP conflict/forbidden behavior, idempotency payload,
  audit/outbox names, and proof that claim does not change queue stage/state or
  disclose data. Wait for explicit owner approval. Stop as `awaiting-approval`
  for this design decision, or as `blocked` if authoritative source does not
  support the proposed claim representation or requires a broader workflow.

## Execution procedure

1. Resolve `$TARGET` canonically; verify repository identity, clean-or-owner
   worktree state, accepted-baseline ancestry, immutable task content, and all
   required capabilities. Preserve unrelated changes; never reset, clean,
   stash, discard, stage, commit, or push.
2. Validate this task and the four immutable MVP-04E contracts before editing.
3. Verify ponytail at full level and record the existing migration, transaction,
   authorization, worklist, test, audit, and outbox patterns reused. Explain why
   one claim reservation is smaller than a queue state-machine or clinical slice.
4. Query Graphify and Codebase Memory MCP using the declared freshness policy;
   directly inspect every authoritative source/document identified by them
   before deciding fields, transitions, requirements, gaps, or ownership.
5. Run the current MVP-04E suite and inspect the existing admission/history
   schema, worklist projection, private route group, check-in transaction, and
   architecture migration allowlist. Establish the pre-change privacy and FIFO
   baseline without printing any real configuration value.
6. Present the approval-gate design. After explicit approval, add only the
   minimal additive claim migration and update the architecture migration
   allowlist if its existing test requires the new migration to be named.
7. Implement the claim through existing authorization and idempotency boundaries.
   Revalidate portal account, permission, active site, site assignment, and
   assigned shift within the transaction; require an eligible unclaimed waiting
   admission at the trusted active site; make competition and a second active
   claim deterministic conflicts; preserve stage/state and FIFO fields.
8. Add the private endpoint/form and focused regressions. Assert all failure
   responses contain no protected or internal detail, all replays create exactly
   one claim/history/audit/outbox record, rollback leaves no partial claim, and
   no second Operator or second admission is claimed.
9. Run MVP-04F, MVP-04E, MVP-04D, MVP-04C, MVP-04B, Operator portal, Operator
   foundation, WP-02 security, and architecture suites separately; run fresh
   migrations, PHP syntax, Pint test mode, Composer validation, private route
   listing, migration/schema inspection, Graphify/Codebase-Memory final review,
   privacy-sensitive searches, and `git diff --check`.
10. Only after every check passes, update bounded MVP-04F evidence, roadmap, and
    work-package status with exact observed results and unchanged open gaps.
    Re-read the final diff for scope only; do not commit or push.

## Acceptance criteria

- [ ] This task and all four prior MVP-04E contracts validate; authoritative
      documentation/source, Graphify status or limitation, Codebase Memory
      status, baseline ancestry, current schema, and existing boundaries are
      observed before editing.
- [ ] An assigned, active Operator with a trusted active site and assigned shift
      can claim exactly one eligible unclaimed advance admission; the admission
      remains `basic_examination` / `waiting`, retains its FIFO fields, and is
      visible only to that claimant thereafter.
- [ ] Atomic competition, replay, a second claim by the same Operator, inactive
      account, revoked portal permission, revoked/forged active site, revoked
      shift assignment, foreign/stale admission, non-waiting admission, and
      audit/outbox failure all fail closed without partial claim or sensitive
      data/internal-detail leakage.
- [ ] Exactly one append-only claimed-history record, one audit record, and one
      outbox record commit with a successful claim; exact idempotent replay
      returns the original safe result without duplicates.
- [ ] Existing MVP-04E check-in, ticket, admission, history, audit/outbox,
      private FIFO worklist, Member ownership, HTTP authorization behavior,
      schema data, and all deferred queue/clinical boundaries remain unchanged.
- [ ] All required focused, regression, security, architecture, migration,
      syntax, Pint, Composer, route, privacy, graph/source, and diff checks pass
      with observed output; no real secret, dependency, commit, or push occurs.

## Verification

- Method: Validate this and all prior MVP-04E tasks; prove Graphify/Codebase
  Memory status and directly inspect the governing documents/source; run the
  new isolated claim tests plus MVP-04E/MVP-04D/MVP-04C/MVP-04B/Operator portal/
  Operator foundation/WP-02 security/architecture suites separately, fresh
  migration/schema checks, PHP syntax, Pint test mode, Composer validation,
  private-route listing, privacy-sensitive output searches, final graph/source
  review, and `git diff --check`.
- Expected result: One trusted Operator privately and atomically reserves one
  eligible waiting admission without changing its stage/state or FIFO order;
  conflicts, replay, unauthorized access, and rollback are safe and leak-free;
  one history/audit/outbox record exists per successful claim; all existing
  bounded MVP-04E behavior and required verification remain passing; and no
  clinical, public, Member, dependency, commit, or push behavior is added.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, accepted baseline, selected runtime/model when verifiable,
  approval decision, capabilities, outcome, changed files, Graphify and
  Codebase Memory MCP status/actions/freshness, ponytail evidence, exact
  checks/results, unrun checks, residual risks, and manual follow-up.
- Treat an unsupported claim representation, a changed queue stage/state,
  duplicate or competing claim success, missing idempotency/audit/outbox proof,
  sensitive leakage, skipped mandatory verification, or claimed rather than
  observed success as unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report final worktree state and readiness for owner-controlled commit. After
the owner supplies a candidate commit SHA, review it and its full chain against
accepted baseline `882a438947fc40fc43ba2e4e8864ce5ad18b2569`, the four prior
MVP-04E contracts, this task, direct authoritative repository evidence, and
observed runtime evidence before accepting a new baseline or selecting another
vertical slice.
