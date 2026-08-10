---
name: mhcs-core-mvp-04c-paper-consent-confirmation
description: Add the approved, Member-owned paper-consent confirmation and optional private signed-paper upload for an identity-verified arrived booking, without checking in the booking or issuing a ticket.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04C — Paper Consent Confirmation and Optional Scan

## Objective

For `$TARGET`, implement one bounded front-desk prerequisite: an assigned
Operator may record one approved paper-consent confirmation for an arrived,
identity-verified advance booking through a Member-owned command, with one
optional private upload of the signed paper form. The result is an immutable,
auditable consent record that is safe to consume later by the separate check-in
and ticket slice.

Do not change a booking to `checked_in`, issue or print a ticket, create a
queue item, or begin any clinical or examination workflow.

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
reuse the existing Member attendance, trusted Operator context, audit, outbox,
idempotency, and portal patterns; do not add a consent framework or dependency.

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Accepted baseline: `36ce5ab72a19cbdf5514f0d847ca50400ad3fe7d`.
- The preceding MVP-04B audit-sanitizer remediation is accepted. Its focused
  WP-02, MVP-04B identity, Operator portal, and architecture suites passed.
- The current product evidence has arrived bookings and terminal identity
  decisions, but no examination-consent schema, Member consent command, or
  Operator consent-confirmation route. Confirm this from source rather than
  relying on this statement.
- Member owns consent. The governing requirements state that paper consent is
  confirmed once per visit before ticket issue; it records an applicable active
  form version, permitted signer, signature confirmation and occurrence time,
  responsible operator, site, and booking. See
  `$TARGET/.agents/context/modules/member/project.md` under `Examination
  consent record` and `Arrival identity verification`.
- Operator may perform the front-desk steps only for the assigned active site;
  consent confirmation follows identity verification and precedes check-in.
  See `$TARGET/.agents/context/modules/operator/project.md` under `Attendance
  and identity verification`.
- `APP-002` (clinical/interoperability) and `APP-004` (privacy/legal/regulated
  text/identity) are approval boundaries in
  `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`. For this
  narrow task, the owner decision below supplies the approved scope; do not ask
  the owner to repeat it in a rigid form.
- Owner-approved policy, recorded during task authoring on 2026-08-07:
  - Paper form: `Informed Consent`, version `V1`.
  - The member signs the offline paper form; only the member themselves may be
    recorded as signer.
  - Only an Operator currently assigned to the authenticated active site may
    record it, using the existing `operator.identity.verify` permission and
    the same trusted identity-verification boundary.
  - Store form version, member-signer confirmation, signature-confirmation
    flag, signing time, Operator, site, booking, recording time, idempotency
    ID, and one optional photo/scan of the signed paper form.
  - The optional photo/scan is private encrypted evidence, purpose-bound to
    consent confirmation, never placed in shared audit metadata or logs, and
    never exposed through a public URL, Member page, queue, or general
    administrator browser. This task adds no retention job or deletion flow.
- Related requirements: `MEM-062`, `MEM-185`, `MEM-218`, `MEM-221`,
  `OPR-018`, `OPR-019`, `OPR-090`, `OPR-108`, `OPR-115`, `OPR-116`, and
  `OPR-129`.
- Related Work Packages: WP-07 (Member consent authority), WP-12 (Operator
  attendance), and WP-17 (Operator authorization and audit).
- Related gaps remain open: `MVP-GAP-009`, `MVP-GAP-012`, `MVP-GAP-021`, and
  `MVP-GAP-024`.

Read completely before planning or changing files:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/skills/fix-bug/SKILL.md` when a reproducible defect is
  encountered;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/context/ui-language.md` if any Member-visible copy is
  introduced;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04b-front-desk-identity-verification-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04b-final-boundary-closure-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04b-audit-identifier-sanitizer-remediation-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/app/Modules/Member/Application/Contracts/OperatorAttendanceContract.php`;
- `$TARGET/app/Modules/Member/Application/Services/Mvp04AttendanceService.php`;
- `$TARGET/app/Modules/Member/Application/Services/Mvp04OperatorIdentityVerificationService.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberAuthorization.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAttendanceService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorIdentityVerificationService.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorWorklistService.php`;
- `$TARGET/app/Http/Controllers/Operator/PortalController.php`;
- `$TARGET/routes/web.php`;
- `$TARGET/tests/Operator/Mvp04OperatorFoundationTest.php`;
- `$TARGET/tests/Feature/Operator/Mvp04OperatorPortalTest.php`;
- `$TARGET/tests/Feature/Operator/Mvp04bIdentityVerificationTest.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberVerificationAssetService.php`;
- `$TARGET/app/Shared/Storage/EncryptedLocalObjectStore.php`; and
- `$TARGET/tests/Security/Wp02SecurityTest.php`.

Use Codebase Memory MCP to verify the canonical project/root and index
freshness before code discovery. The accepted graph currently contains the
sanitizer remediation symbols and regression at 4,428 nodes and 10,851 edges;
the provider Branch metadata must not be treated as Git-SHA proof. Use no
refresh when source is current, fast refresh only when source changed or the
required symbols are absent, and full re-indexing only when the graph is
missing or fast recovery fails. Search and trace the existing arrival,
identity-decision, trusted-context, audit, outbox, and portal call paths before
selecting files to change. Record initial/final graph status and any action.

## Scope and constraints

Included:

- one Member-owned, database-backed paper-consent confirmation record for an
  arrived booking with a terminal `matched` identity-verification case;
- exact storage of the approved `Informed Consent` / `V1` form reference and
  rejection of every other form/version in this bounded slice;
- a Member application contract that receives trusted Operator context rather
  than trusting browser-supplied operator, site, booking, member, or status
  identifiers;
- a narrowly authorized Operator portal action that uses the current assigned
  active site, existing `operator.identity.verify` permission, and actual
  confirmation occurrence time;
- one optional signed-paper upload, accepted only as JPEG, PNG, or PDF no
  larger than 10 MiB after server-side MIME/content validation, encrypted with
  the existing protected storage primitive, and associated only with its
  consent record;
- atomic persistence of the Member consent record, its idempotency/result
  identity, Member booking-side audit, and any required local outbox event;
- append-only consent/audit evidence, a unique database-backed once-per-booking
  invariant, and same-input idempotent replay with a conflicting replay
  rejected; and
- focused Member, Operator, authorization, privacy, concurrency, audit, and
  route/feature coverage plus bounded evidence updates.

For this narrow slice, permit only the member themselves as signer. The signed
paper remains the source document. The upload is optional, is not a replacement
for paper consent, and is not retrieved or displayed by this task after upload.

Excluded:

- booking `checked_in` transition, ticket allocation, printing/reprinting,
  public display, queue ordering/claims, walk-ins, no-shows, basic examination,
  vitals, encounter, X-ray, capture, image processing, FHIR, payment, or
  clinical decision behavior;
- electronic signatures, consent-form content, scan/image retrieval,
  retention/deletion policy, representative/guardian consent, consent
  corrections, general consent administration, multiple uploads, and any form
  or version other than `Informed Consent` / `V1`;
- changing the ownership of Member identity, booking, consent, or Operator
  queue records; exposing protected identity data; copying identity assets;
- modifying existing published tasks, `.agents/context/**`, or
  `docs/implementation/**`; and
- dependencies, external systems, deployment, production access, commits, or
  pushes.

Preserve existing arrival and MVP-04B identity behavior. A matched identity
decision alone must not change booking state or produce a ticket. Any missing,
failed, mismatched, cancelled, insufficient-evidence, forged, cross-site,
stale-assignment, inactive-account, revoked-permission, out-of-window, or
already-consented condition must fail closed with no partial consent, audit, or
event state. Do not put signer details, identity documents, scans, NIK, free
text, secrets, or clinical payloads into shared audit metadata or logs.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates:
  - The owner-approved policy in this task is sufficient for this exact,
    paper-only `Informed Consent` / `V1` scope. Do not ask for a duplicate
    approval message or create a separate claim.
  - Stop as `awaiting-approval` if the requested form/version, signer,
    permission, stored metadata, upload formats/size, access purpose, or
    retention/deletion behavior differs from the approved policy above.
  - Stop as `blocked` if task validation, Codebase Memory MCP, ponytail, or the
    focused verification toolchain is unavailable.
  - Stop as `awaiting-approval` for representative/guardian, FHIR, policy,
    migration incompatibility, or overlapping-owner-work scope beyond this
    task.

## Execution procedure

1. Resolve `$TARGET` canonically; verify repository identity, branch, clean or
   owner-change worktree state, baseline ancestry, task immutability, and all
   required capabilities.
2. Validate this task with the repository validator before execution.
3. Verify ponytail at full level and record which existing primitives and tests
   are reused.
4. Confirm the requested implementation remains exactly within the embedded
   owner-approved policy. Stop at the approval gate only if it differs.
5. Verify Codebase Memory MCP freshness and trace the Member attendance and
   MVP-04B identity paths, their callers, their trusted-context checks, and
   their audit/outbox transaction boundaries.
6. Inspect the exact current schema, migrations, providers, routes, portal
   views, contracts, services, and focused tests before selecting the smallest
   compatible Member-owned persistence and application boundary.
7. Implement the approved confirmation once, with database-enforced
   uniqueness, idempotency conflict detection, locking for competing attempts,
   and one transaction for consent state, audit, and required outbox evidence.
   If a photo/scan is supplied, validate it before encrypted private storage;
   failed storage must not leave retrievable evidence or a partial consent.
8. Expose only the approved authorized Operator action. Revalidate persisted
   account, portal authority, role/claim, active-site assignment, arrived
   booking, terminal matched case, and Member-owned site/schedule binding at
   mutation time.
9. Add the smallest focused regressions: confirmation with and without an
   upload; exact replay; changed replay; concurrent duplicate; wrong form or
   version; invalid MIME/content/size; cross-site/forged or stale context;
   non-matched identity decisions; booking-state preservation; private-upload
   inaccessibility; audit privacy; and transaction rollback.
10. Run the required verification separately, inspect the final diff and graph
    paths, then update only the bounded evidence/status documents whose facts
    changed. Keep all listed gaps and later workflows open.
11. Stop before check-in, ticketing, queue, scan retrieval, or any excluded
    work. Do not commit or push.

## Acceptance criteria

- [ ] Task validation, owner-approved scope, Codebase Memory MCP, ponytail,
      and preflight checks pass before product changes.
- [ ] The Member module remains the sole owner of approved consent data and
      exposes one explicit application contract to an authorized Operator flow.
- [ ] Only a currently assigned and authorized Operator at the trusted active
      site can confirm approved paper consent for an arrived booking with a
      terminal matched identity case.
- [ ] The persisted record contains only approved paper-confirmation metadata,
      the exact `Informed Consent` / `V1` form reference, trusted
      actor/site/booking linkage, actual occurrence/recording identity, and at
      most one separately encrypted private upload reference. Shared audit and
      logs contain no scan, identity-document, NIK, free text, secret, or
      clinical payload.
- [ ] An optional upload accepts only one validated JPEG, PNG, or PDF up to
      10 MiB; it is private, encrypted, purpose-bound, not generally
      retrievable, and rolls back or is unrecoverable on any failed operation.
- [ ] A database-backed once-per-booking rule, same-input replay, conflicting
      replay denial, and concurrent-attempt behavior are deterministic; audit,
      event, and consent persistence roll back together on failure.
- [ ] The booking remains `arrived`; no ticket, queue item, public-display
      payload, check-in, examination, or Member-visible consent surface exists.
- [ ] Negative authorization, site, case-state, account/permission, form,
      privacy, and transaction tests pass without side effects.
- [ ] No excluded scope, changed owner-approved policy, dependency, context/spec
      edit, commit, or push occurs.

## Verification

- Method: Validate the task; run the focused Member and Operator consent/arrival/identity feature and service tests separately, including upload/no-upload, MIME/content/size, idempotency, concurrent duplicate, transaction-rollback, authorization, stale-context, private-access, audit-privacy, and booking-state negative cases; run the current MVP-04B identity, Operator portal, WP-02 security, and architecture suites separately; run PHP syntax and Pint on changed PHP files; inspect final Codebase Memory call paths, targeted sensitive-data searches, and `git diff --check`.
- Expected result: The task validates; only approved, trusted, identity-verified, arrived bookings receive one Member-owned `Informed Consent` / `V1` confirmation with atomic audit/idempotency evidence and an optional validated encrypted private paper upload; every invalid or concurrent path fails closed without changing the booking or creating a ticket/queue item; focused regressions and existing bounded suites pass; no sensitive material is exposed; and no excluded scope is introduced.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, baseline, execution HEAD, selected runtime/model when
  verifiable, approval-evidence decision, capabilities, outcome, affected
  interfaces/files, Codebase Memory MCP and ponytail evidence, exact checks and
  results, unrun checks, residual risks, and manual follow-up.
- Treat a changed owner-approved policy, unverified upload boundary,
  unverified patch, weakened authorization, missing transaction/audit evidence,
  a booking state change, ticket/queue creation, or model output alone as
  unsuccessful.

## Commit review handoff

The execution agent must not commit or push.

Report final worktree state and readiness for owner-controlled commit. After
the owner supplies an implementation commit SHA, review it against
`36ce5ab72a19cbdf5514f0d847ca50400ad3fe7d` and this task before accepting a
new baseline or selecting the next vertical slice.
