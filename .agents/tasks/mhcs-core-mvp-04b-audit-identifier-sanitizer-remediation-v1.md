---
name: mhcs-core-mvp-04b-audit-identifier-sanitizer-remediation
description: Remove the UUID false-positive in shared audit sanitization without weakening raw-identifier, secret, clinical-data, or binary-payload rejection, then restore deterministic MVP-04B verification evidence.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04B — Audit Identifier Sanitizer Remediation

## Objective

Remediate the shared audit-sanitizer defect observed while reviewing commit
`96f59e9efcf15adf497aaa44e57a8a8f64a071a2` so valid canonical UUID values do
not fail audit construction merely because a UUID segment contains 10–12
decimal digits.

Required result for `$TARGET`:

```text
canonical UUID used as audit target/metadata
→ accepted as an opaque local identifier

standalone 10–20 digit identifier or other prohibited sensitive scalar
→ still rejected/redacted by the existing boundary

MVP-04B identity operation
→ audit append remains mandatory
→ no random rollback caused by UUID contents
```

Do not advance to consent, `checked_in`, ticketing, queues, examination,
administrator dispute resolution, walk-ins, cash, imaging, FHIR, later MVPs,
deployment, or production behavior.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `codebase-memory-mcp`
  - `ponytail`
- Ordered model preferences: None.
- Require preferred model: `false`

Codebase Memory MCP and ponytail are mandatory and require direct runtime
evidence.

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Previous accepted baseline: `7074f2eea5e8c7368418dac966f111c4d96ddedd`.
- Reviewed remediation candidate: `96f59e9efcf15adf497aaa44e57a8a8f64a071a2`.
- The current execution HEAD may be a clean descendant containing only
  repository-agent guidance; preserve all owner changes and require the
  reviewed candidate to be an ancestor.
- Review reproduced the root cause directly:

```text
SensitiveDataSanitizer::assertSafe([
    'case_id' => '00000000-0000-4000-8000-123456789012',
])
→ SensitivePayloadException
```

- `SensitiveDataSanitizer::isSensitiveScalar()` currently applies the raw
  `10..20` digit pattern before distinguishing a complete canonical UUID.
- `AuditEvent` applies the sanitizer to target IDs and metadata, while the
  MVP-04B identity flow records case, arrival, booking, schedule, and site UUIDs.
- Independent review runs of the declared focused suites produced changing
  failures such as `Sensitive data is not allowed in a security record` and
  `Sensitive scalar values are not allowed in audit metadata`; a deterministic
  UUID-shaped input proves this is content-dependent rather than a stable
  product assertion failure.
- Related requirements: `ARCH-035`, `MEM-112`, `OPR-115`, and `OPR-116`.
- Related Work Packages: WP-02 and the bounded WP-12/WP-17 MVP-04B slice.
- Related gaps remain open: `MVP-GAP-006`, `MVP-GAP-009`, `MVP-GAP-012`, and
  `MVP-GAP-024`.

Read completely before planning or editing:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/fix-bug/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04b-final-boundary-closure-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/evidence/mvp-04b-front-desk-identity-verification.md`;
- `$TARGET/docs/security/wp-02-security-evidence.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/app/Shared/Security/SensitiveDataSanitizer.php`;
- `$TARGET/app/Shared/Audit/AuditEvent.php`;
- `$TARGET/app/Modules/Member/Application/Services/Mvp04OperatorIdentityVerificationService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorIdentityVerificationService.php`;
- `$TARGET/tests/Security/Wp02SecurityTest.php`; and
- `$TARGET/tests/Feature/Operator/Mvp04bIdentityVerificationTest.php`.

Inspect the complete metadata and diff for
`7074f2eea5e8c7368418dac966f111c4d96ddedd..96f59e9efcf15adf497aaa44e57a8a8f64a071a2`.

Use Codebase Memory MCP to verify the canonical project/root and graph
freshness, locate the sanitizer and audit construction paths, and trace the
affected identity audit callers. Use no refresh when current, the least-cost
incremental or fast refresh when stale, and full indexing only when the graph
is missing or incremental recovery fails. Record initial status, action,
justification, and final status.

## Scope and constraints

Included:

- the shared scalar-sanitization distinction between a complete canonical UUID
  and a standalone raw numeric identifier;
- deterministic regression coverage at the shared audit boundary;
- focused MVP-04B and WP-02 regression verification; and
- bounded evidence/status-document updates describing this remediation.

Excluded:

- weakening or removing sensitive-key checks;
- broadly exempting strings that merely contain UUID-like or numeric fragments;
- allowing standalone NIK/KK/account-number-shaped values, bearer tokens,
  credentials, clinical text, binary signatures, data URLs, objects, resources,
  or control characters;
- changing audit schema, event fields, append-only behavior, transaction
  boundaries, domain identifiers, migrations, public contracts, dependencies,
  manifests, locks, or existing published tasks;
- consent, check-in, ticket, queue, clinical, financial, imaging, FHIR, browser,
  CI, deployment, production, or external-system work.

Preserve unrelated work. Do not reset, clean, discard, stash, stage, commit,
push, deploy, access production, install dependencies, or modify external
systems. Do not modify `.agents/context/**` or `docs/implementation/**`.

Use the existing UUID validation facility already present in the application or
installed framework. Do not add a new identifier abstraction, dependency, or
general sanitizer framework.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `2`
- Approval gates:
  - stop as `blocked` if the task validator, Codebase Memory MCP, ponytail, or
    required focused verification is unavailable;
  - stop as `awaiting-approval` for ancestry failure, overlapping owner changes,
    dependency/migration work, or any required weakening beyond the exact UUID
    distinction;
  - no other approval gate is granted by this task.

## Execution procedure

1. Resolve `$TARGET` canonically; verify repository identity, branch, HEAD,
   worktree state, reviewed-candidate ancestry, task immutability, and required
   capabilities.
2. Validate this task with the repository validator.
3. Verify ponytail at full level and keep it active.
4. Verify Codebase Memory MCP identity/freshness and trace sanitizer → audit →
   MVP-04B identity call paths.
5. Reproduce the defect with a fixed canonical UUID whose final segment is all
   decimal digits, and confirm a standalone 10–20 digit value is rejected.
6. Implement the smallest root-cause correction in the shared sanitizer using
   the existing UUID validator.
7. Add one deterministic focused regression covering UUID audit target/metadata
   acceptance and preservation of the sensitive scalar denials.
8. Run the declared suites and static checks separately; do not parallelize
   stateful test suites.
9. Refresh the graph only if source changes make it stale, then verify the final
   sanitizer callers and identity audit path.
10. Update only the bounded evidence/status documents listed below with exact
    commands, observed results, unrun checks, and residual risks.
11. Inspect the final diff for scope creep or privacy weakening and run
    `git diff --check`.
12. Stop before any later MVP work, commit, or push.

## Acceptance criteria

- [ ] Preflight, ancestry, task validation, Codebase Memory MCP, and ponytail
      checks pass.
- [ ] A complete canonical UUID with a 10–12 digit UUID segment is accepted as
      an audit target and as a value under an allowed operational metadata key.
- [ ] Standalone 10–20 digit values remain rejected by `assertSafeString()` and
      `assertSafe()` even under neutral metadata keys.
- [ ] Existing sensitive-key, secret/token, clinical-text, binary/data-URL,
      object/resource, and control-character denials remain unchanged and green.
- [ ] Audit records remain append-only and retain actor, permissions, site,
      target, action, times, operation/correlation identity, source, outcome,
      and bounded metadata where applicable.
- [ ] The MVP-04B identity suite and WP-02 security suite pass separately with
      no content-dependent sanitizer failure.
- [ ] No audit call is removed, made optional, swallowed, or moved outside its
      existing transaction merely to make tests pass.
- [ ] No excluded scope, dependency, migration, context/specification edit,
      existing-task edit, commit, or push occurs.

## Verification

- Method: Validate the task; run a deterministic sanitizer/AuditEvent regression for a canonical UUID with an all-decimal final segment and negative raw-identifier cases; run `vendor/bin/phpunit tests/Security/Wp02SecurityTest.php`, `vendor/bin/phpunit tests/Feature/Operator/Mvp04bIdentityVerificationTest.php`, `vendor/bin/phpunit tests/Feature/Operator/Mvp04OperatorPortalTest.php`, and `vendor/bin/phpunit tests/Architecture/FoundationArchitectureTest.php` separately; run PHP syntax and Pint on changed PHP files; inspect the affected audit call paths and final Codebase Memory graph; run targeted sensitive-data searches and `git diff --check`.
- Expected result: The task validates; canonical UUID audit targets/metadata no longer fail because of numeric UUID segments; standalone raw numeric identifiers and every existing prohibited sensitive value still fail closed; all declared focused suites and static checks pass; audit append behavior is preserved; evidence is accurate; and no excluded scope is introduced.

Update only these evidence/status documents when their existing content requires
the remediation result:

```text
$TARGET/docs/security/wp-02-security-evidence.md
$TARGET/docs/mvp/evidence/mvp-04b-front-desk-identity-verification.md
$TARGET/docs/mvp/roadmap.md
$TARGET/docs/mvp/beta-gap-register.md
$TARGET/docs/mvp/work-package-status.md
```

Record exact commands, exit statuses, test/assertion counts, warnings, skips,
failures, graph freshness action, and unrun checks. Keep MVP-04 and the listed
gaps/Work Packages open or partial as already recorded.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, baseline, execution HEAD, selected runtime/model when
  verifiable, capabilities, outcome, affected files/interfaces, exact defect
  reproduction and correction, Codebase Memory MCP and ponytail evidence,
  exact verification, documentation updates, unrun checks, residual risks, and
  manual follow-up.
- Treat exhaustion, an unverified patch, weakened sensitive-data rejection,
  missing audit writes, or model output alone as unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report the final worktree and readiness for owner-controlled commit. After the
owner supplies a remediation commit SHA, review it against
`96f59e9efcf15adf497aaa44e57a8a8f64a071a2` and this task before accepting a
new MVP-04B baseline or selecting the next vertical slice.
