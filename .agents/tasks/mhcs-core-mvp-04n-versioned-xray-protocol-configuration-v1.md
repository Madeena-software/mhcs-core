---
name: mhcs-core-mvp-04n-versioned-xray-protocol-configuration
description: Let an authorized global Operator administrator publish immutable service-specific X-ray projection mappings without starting examinations or inventing clinical defaults.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-04N — Versioned X-Ray Protocol Configuration

## Objective

For `$TARGET`, add the smallest Operator-owned prerequisite for a later X-ray
start: an authorized global Operator administrator can publish the first or
next immutable X-ray protocol version for one existing Member service offering.
Each published version snapshots that service's current code and an ordered,
non-empty set of distinct projection identifiers, becomes the current mapping
for that service, and leaves every earlier version readable and unchanged.

Expose the capability through the shared Filament admin panel with independent
read and manage permissions. Publish through one transactional, idempotent
Operator application service with optimistic version checking, audit, and
outbox evidence. Do not seed or infer real clinical projection mappings, start
an examination, snapshot a protocol into an examination, or claim FHIR or
device conformance.

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

Graphify, Codebase Memory MCP, and ponytail are required. Keep ponytail at
full level: reuse the existing shared admin panel, Operator authorization,
Member local-contract boundary, UUID models, application-service mutation
pattern, database idempotency, clock, audit/outbox stores, and focused admin
tests. Use one service-specific current mapping plus immutable version history;
do not add a reusable template catalogue, generic versioning framework,
terminology subsystem, dependency, API, or second admin panel.

## Runtime inputs

- `TARGET` (required): Repository root for `mhcs-core`.

## Context and evidence

- Canonical repository: `Madeena-software/mhcs-core`.
- Accepted MVP-04M baseline:
  `b07aace0f7771162086c9e91ffbb866031241449`, descended from the previous
  accepted baseline `c4aebdae61b4e01cd361bee1265063ba72254d03`.
- MVP-04M completes only the private claimant-owned X-ray `waiting` to `called`
  transition. It deliberately excludes X-ray start, protocol configuration or
  snapshot, Encounter/FHIR, and capture behavior.
- Operator authority requires a global administrator to configure versioned
  service-to-projection mappings. Examination start later snapshots the active
  version, and an unmapped service must block start instead of inviting an
  Operator to guess required captures.
- Member remains authoritative for the service offering, requested service,
  body part, laterality, booking, Appointment, and ServiceRequest. Operator owns
  the protocol configuration and future examination snapshot.
- Related requirements: `OPR-077`, the configuration prerequisite of
  `OPR-132`, and supporting `OPR-110`, `OPR-115`, `OPR-116`, `OPR-117`,
  `OPR-129`, and `OPR-134`. This task does not claim the start/snapshot portion
  of `OPR-132` or FHIR requirements `OPR-136` and `OPR-138` complete.
- Related Work Packages: WP-14 and WP-17. This bounded prerequisite does not
  close either Work Package.
- Open gaps `MVP-GAP-009` and `MVP-GAP-024` are narrowed but remain open.
  Queue gap `MVP-GAP-012` and privacy gap `MVP-GAP-021` remain open and are not
  changed by this task.
- The FHIR Implementation Guide identity/package and Encounter mapping remain
  unresolved, and the Grabber/NPZ schema is not approved. Those dependencies
  block an honest X-ray-start/capture task but do not block a local, synthetic,
  service-specific protocol-version administration slice.

Before implementation decisions, directly inspect:

- `$TARGET/AGENTS.md`, `$TARGET/.agents/AGENTS.md`,
  `$TARGET/.agents/skills/agent-task/SKILL.md`,
  `$TARGET/.agents/skills/develop-feature/SKILL.md`, and
  `$TARGET/.agents/skills/graphify/SKILL.md`;
- `$TARGET/.agents/context/project.md`,
  `$TARGET/.agents/context/modules/operator/project.md`, and
  `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-04m-private-xray-call-v1.md`,
  `$TARGET/.agents/tasks/mhcs-core-mvp-04h-private-basic-examination-start-v1.md`,
  and `$TARGET/.agents/tasks/mhcs-core-mvp-04j-private-vital-signs-capture-v1.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`,
  `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`,
  `$TARGET/docs/mvp/roadmap.md`, `$TARGET/docs/mvp/decision-log.md`,
  `$TARGET/docs/mvp/beta-gap-register.md`, and
  `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/app/Modules/Member/Application/Contracts/README.md`,
  `$TARGET/app/Modules/Member/Application/Services/Mvp03OfferingService.php`,
  `$TARGET/app/Modules/Member/Domain/Models/ServiceOffering.php`, and
  `$TARGET/app/Modules/Member/MemberServiceProvider.php`;
- `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`,
  `$TARGET/app/Modules/Operator/Application/Services/OperatorSiteService.php`,
  `$TARGET/app/Modules/Operator/Domain/Models/OperatorSite.php`,
  `$TARGET/app/Modules/Operator/Filament/Resources/OperatorSites/OperatorSiteResource.php`,
  its page classes, and
  `$TARGET/app/Modules/Operator/OperatorServiceProvider.php`;
- `$TARGET/app/Providers/Filament/AdminPanelProvider.php`,
  `$TARGET/app/Shared/Infrastructure/Idempotency/DatabaseIdempotencyStore.php`,
  `$TARGET/app/Shared/Audit/DatabaseAuditStore.php`,
  `$TARGET/app/Shared/Infrastructure/Outbox/DatabaseOutboxStore.php`, and
  `$TARGET/app/Shared/Time/Clock.php`;
- `$TARGET/database/migrations/2026_07_30_000002_create_service_offerings_table.php`,
  current Operator migrations, `$TARGET/database/seeders/MvpOperatorSeeder.php`,
  `$TARGET/tests/Feature/Admin/Mvp04OperatorAdministrationTest.php`,
  `$TARGET/tests/Feature/Admin/Mvp03BookingAdministrationTest.php`,
  `$TARGET/tests/Member/Mvp03BookingDomainTest.php`, and
  `$TARGET/tests/Architecture/FoundationArchitectureTest.php`.

Confirm exact existing paths from the repository rather than guessing if a
listed migration or supporting file has moved. Use Graphify first to identify
current protocol, projection, administration, module-ownership, Work Package,
gap, and unresolved-dependency relationships. Reuse a current graph and update
it incrementally only if relevant tracked documentation changed. Use Codebase
Memory MCP to verify the canonical index and trace the service-offering,
Member-contract, Operator authorization/admin-resource, idempotency, audit,
outbox, seeder, migration, and test patterns; use a fast refresh only when
needed. Both are discovery aids: inspect the exact authoritative repository
files directly before making requirement, architecture, or implementation
decisions.

## Scope and constraints

Included:

- one migration at
  `$TARGET/database/migrations/2026_08_08_000004_create_operator_xray_protocol_mappings.php`
  for an Operator-owned current mapping per Member service offering and
  append-only version history. Use UUID identities, a stable restricted
  reference to `service_offerings`, a database uniqueness boundary for one
  current mapping per service and one history row per mapping/version, the
  authoritative service-code snapshot, ordered projection JSON, the positive
  version, publishing actor/time, and ordinary timestamps needed by existing
  model conventions;
- minimal Operator models at
  `$TARGET/app/Modules/Operator/Domain/Models/OperatorXrayProtocolMapping.php`
  and
  `$TARGET/app/Modules/Operator/Domain/Models/OperatorXrayProtocolVersion.php`.
  The current row may advance, but a published version must never be updated or
  deleted through the application or admin panel;
- a read-only local Member contract at
  `$TARGET/app/Modules/Member/Application/Contracts/OperatorServiceOfferingQuery.php`,
  its minimal Member implementation at
  `$TARGET/app/Modules/Member/Application/Services/Mvp04OperatorServiceOfferingQuery.php`,
  and its binding in `$TARGET/app/Modules/Member/MemberServiceProvider.php`.
  Return only safe service-offering scalars needed for selection and
  publication; never return an Eloquent model across the module boundary;
- one
  `$TARGET/app/Modules/Operator/Application/Services/OperatorXrayProtocolConfigurationService.php`
  publish command. It must derive the actor and exact manage claim from the
  authenticated database context, resolve and recheck the Member service
  through the local query, normalize and validate a non-empty ordered list of
  distinct bounded projection identifiers without assigning clinical meaning,
  and accept a caller-generated operation UUID plus the expected current
  version (`0` only for the first publication);
- one database transaction that reserves idempotency, locks or otherwise
  serializes the service mapping, rejects a stale expected version, appends the
  next immutable version, advances the current mapping, records one
  `operator.xray-protocol.published` audit entry, records one
  `operator.xray-protocol-published` outbox event, and completes idempotency.
  Exact replay returns the original version without duplicate rows or
  evidence; a reused operation ID with any changed service, expected version,
  or projection payload conflicts;
- exact independent claims `operator.protocol.read` and
  `operator.protocol.manage` in
  `$TARGET/app/Modules/Operator/Application/Services/OperatorAuthorization.php`.
  Grant them only to the explicit synthetic global administrator in
  `$TARGET/database/seeders/MvpOperatorSeeder.php`; do not grant protocol
  management to ordinary Operator profiles;
- one Filament resource at
  `$TARGET/app/Modules/Operator/Filament/Resources/OperatorXrayProtocolMappings/OperatorXrayProtocolMappingResource.php`
  with the minimum list, view, create-first-version, and publish-next-version
  pages under its `Pages/` directory, explicitly registered in
  `$TARGET/app/Providers/Filament/AdminPanelProvider.php`. Read permission may
  inspect safe current/version history; manage permission is required to
  publish. Editing must publish a new version, never rewrite history. Provide
  no delete, restore, bulk mutation, export, or direct model-write action;
- focused coverage at
  `$TARGET/tests/Feature/Admin/Mvp04nXrayProtocolConfigurationTest.php` plus the
  smallest architecture assertions necessary to prove module direction. Cover
  first and next publication, immutable history/current selection, exact
  replay, changed-payload conflict, stale/concurrent publication, missing or
  changed Member service evidence, invalid/empty/duplicate projection input,
  read/manage separation, ordinary-Operator denial, revoked account/permission
  denial, audit/outbox failure rollback, no direct Member mutation, safe
  rendered/event/audit data, and absence of delete/export/bulk actions.

Use the current Member service UUID as the stable local reference and snapshot
its authoritative code into every published version. Preserve administrator-
supplied projection order. Tests and seed data may use clearly synthetic
identifiers such as `PROJECTION_A`; no production mapping, modality, anatomy,
laterality, clinical terminology, or implied default may be introduced.

The operation is global administration, so no site or shift supplied by the
browser may grant access. Audit and outbox data may contain only the stable
service reference/code, protocol version, normalized projection identifiers,
actor/purpose, operation ID, and timestamps required by the existing stores;
they must contain no Member, booking, identity, consent, examination, image,
object-link, credential, or internal-exception data. Every denial and
infrastructure failure must leave the current mapping, version history,
idempotency, audit, and outbox unchanged.

Excluded:

- X-ray claim/call changes; examination start or `in_service`; protocol
  snapshot into an examination; Encounter/FHIR resources or mappings;
  Appointment/ServiceRequest/booking mutation; body-part or laterality
  correction; queue, ticket, public display, or Member-facing behavior;
- real projection mappings, clinical defaults, template sharing/inheritance,
  approval workflow, terminology/code-system claims, clinical validation, or
  a reusable protocol-template catalogue beyond the required service-specific
  versioned mapping;
- NPZ/gain draft, preview, omission, validation, upload, capture manifest,
  submission, Image Gateway, MPIPS, AI, Doctor, earnings, payouts, or cash;
- Member service creation/update, direct Operator imports of Member models,
  network calls between modules, new dependencies, a generic event/versioning/
  authorization framework, documentation status claims, commit, or push.

Preserve all accepted MVP-03 and MVP-04 behavior. Stop rather than inventing a
real clinical mapping, FHIR identity/profile, Encounter contract, device
schema, start transition, or cross-module write requirement.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `3`
- Approval gates: The bounded synthetic protocol-configuration capability is
  authorized by this task. Stop as `awaiting-approval` before adding any real
  clinical projection mapping/default, shared-template semantics, X-ray start
  or snapshot behavior, Member mutation, Encounter/FHIR/conformance artifact,
  capture/device/Image-Gateway behavior, public/member UI, dependency, or
  excluded schema/interface. Do not commit or push.

## Execution procedure

1. Resolve `$TARGET`; verify repository identity, accepted-baseline ancestry,
   clean-or-owner worktree state, required capabilities, and validation of
   this task plus MVP-04M. Preserve unrelated changes; do not reset, clean,
   stash, discard, stage, commit, or push.
2. Check Graphify and Codebase Memory MCP freshness as stated above, then
   directly inspect every governing authority, implementation pattern,
   migration, seeder, resource, and test listed in Context and evidence.
3. Record the ponytail choice: one service-specific current mapping and
   immutable history, one Member scalar query contract, one Operator publish
   service, the existing shared stores/panel, and one focused suite. Confirm
   that no template catalogue, terminology system, API, dependency, or start
   workflow is needed for this bounded result.
4. Add the migration, models, Member query contract/binding, exact Operator
   claims, and transactional publish service. Enforce safe input,
   optimistic/concurrent versioning, exact idempotency, immutable history,
   module ownership, rollback, audit, and outbox boundaries before exposing UI.
5. Add only the bounded Filament resource/pages and synthetic-admin permission
   seed. Route every mutation through the publish service and keep history
   read-only with no deletion, bulk mutation, or export surface.
6. Add the focused negative/regression coverage. Run the focused suite and
   affected Member, Operator-admin, security, architecture, migration/schema,
   authorization, route/resource, PHP syntax/static, Pint, Composer, privacy,
   Graphify/Codebase-Memory, task, and final-diff checks. Inspect actual output
   and the complete diff, then provide the commit-review handoff without
   committing or pushing.

## Acceptance criteria

- [ ] An authenticated global administrator with exact manage permission can
      publish the first or next protocol version for one current Member service
      through the Operator application service; read-only and ordinary Operator
      actors cannot mutate it.
- [ ] Each success creates one immutable history row, advances one current
      service mapping, snapshots the authoritative service code and ordered
      distinct projection identifiers, and writes exactly one audit, outbox,
      and handled-idempotency result in the same transaction.
- [ ] Exact replay returns the original version with no duplicates; changed
      payload, stale expected version, concurrent publication, missing/changed
      Member service evidence, invalid input, revoked authority, and audit/
      outbox failures fail closed with no partial state or sensitive detail.
- [ ] Operator reads Member service metadata only through the bounded local
      scalar query contract, owns only protocol records, and cannot create,
      update, or delete Member services, bookings, orders, or other Member data.
- [ ] The admin resource exposes only safe current/version history and
      create/publish actions under exact read/manage claims, with no history
      rewrite, delete, restore, bulk mutation, export, real clinical defaults,
      patient data, or direct model mutation.
- [ ] MVP-04M X-ray call and existing Member/Operator administration behavior
      remain intact, and no start/snapshot, Encounter/FHIR, capture/device,
      public/member UI, dependency, documentation-status, commit, or push scope
      is introduced.
- [ ] Required focused, regression, migration/schema, authorization, privacy,
      formatter, syntax/static, Composer, derived-intelligence, task, and
      final-diff checks pass with observed evidence.

## Verification

- Method: Validate this and MVP-04M tasks; run the focused MVP-04N suite plus affected Member offering, Operator administration, security, and architecture regressions; inspect a fresh migrated schema and registered admin resource/routes; run PHP syntax/static, Pint, Composer, privacy searches, Graphify/Codebase-Memory freshness and direct-source checks, and `git diff --check`.
- Expected result: One exactly authorized global administrator can idempotently publish a concurrency-safe current X-ray protocol version for an existing Member service while every prior version remains immutable, all denial/failure paths are atomic and leak-free, and no real clinical mapping, examination start, Member mutation, FHIR, capture, or broader scope is introduced.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or
  `exhausted`.
- Report target, accepted baseline, selected runtime/model when verifiable,
  capabilities, outcome, Graphify and Codebase Memory status/actions/freshness,
  direct authority files, ponytail choice, affected interfaces/files,
  verification evidence, residual risks, deferred scope, and manual follow-up.
- Treat an overwritten version; unauthorized or stale success; duplicate or
  partial evidence; direct Member mutation/model coupling; sensitive leakage;
  a real clinical default; any start/FHIR/capture expansion; an unrun required
  check; or a commit/push as unsuccessful.

## Commit review handoff

Do not commit or push. Report final worktree state and readiness for an
owner-controlled commit. After the owner supplies a candidate SHA, review its
full chain against accepted baseline
`b07aace0f7771162086c9e91ffbb866031241449`, this task, direct authoritative
repository evidence, and observed verification before accepting a new
baseline or selecting another slice.
