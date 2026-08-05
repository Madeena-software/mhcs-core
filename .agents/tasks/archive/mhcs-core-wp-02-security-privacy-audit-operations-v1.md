---
name: mhcs-core-wp-02-security-privacy-audit-operations
description: Implement the MHCS Core security, privacy, audit, untrusted-input, MPIPS-isolation, and deployment-policy foundation on top of WP-01 without adding business workflows, production secrets, or real external-service operations.
version: 1
---

# Task: MHCS Core WP-02 Security, Privacy, Audit, and Operational Hardening

## Objective

Implement the bounded `WP-02 — Security, privacy, audit, and operational hardening` work package in `$TARGET`.

Build on the accepted WP-01 Laravel modular-monolith foundation and implement locally verifiable controls for:

- trusted authenticated context and authorization inputs;
- password, protected-identifier, and temporary-credential handling;
- rate-limited and enumeration-resistant credential verification;
- append-only application audit evidence;
- correlation-aware sanitized logging;
- data minimization and private encrypted-object boundaries;
- short-lived authorized object/result access;
- login suspension without destructive record deletion;
- Member NPZ/DICOM boundary enforcement;
- transactional locking and funding-source separation foundations;
- authenticated and audited future external-adapter calls;
- bounded untrusted-image input policy;
- NPZ process/container isolation policy without parsing NPZ;
- signed manifest and checksum binding;
- DICOM permanent-acceptance gating without implementing DICOM parsing;
- the private MPIPS network and privilege boundary;
- deployment-template specialization and CI/CD policy; and
- security, privacy, architecture, and negative tests.

Implement only:

- `ARCH-028` through `ARCH-036`;
- `ARCH-041`;
- `ARCH-043`;
- `ARCH-045`; and
- `MEM-108` through `MEM-119`.

Do not implement requirements assigned to another work package.

A `succeeded` outcome means the code, configuration, deployment specialization, security evidence, and local/static verification required by WP-02 are complete. It does not mean production deployment occurred, MPIPS was called, production NPZ limits were approved, or legal/privacy/clinical approval was granted.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `network`
- Ordered model preferences: None.
- Require preferred model: `false`

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Context and evidence

Read completely before planning or writing:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/context/modules/doctor/project.md`;
- `$TARGET/.agents/context/modules/image-gateway/project.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-source-coverage.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/.agents/tasks/mhcs-core-wp-01-application-architecture-foundation-v1.md`; and
- all current WP-01 files relevant to authentication context, command/query dispatch, events, outbox, idempotency, logging, storage, configuration, database, deployment, and tests.

Confirm the implementation plan still assigns exactly the declared requirements to WP-02. Stop as `awaiting-approval` if it does not.

Treat the current default branch of `Madeena-software/deploy-templates` as the external authority for environment templates, process topology, container isolation, reverse proxy, and CI/CD.

Before changing deployment or CI files:

1. access that repository read-only;
2. record repository, branch, and resolved commit SHA;
3. inspect applicable instructions and full relevant templates;
4. select only the applicable PHP/Laravel template;
5. preserve source notices or provenance; and
6. specialize it rather than redesign it.

Stop as `blocked` if the deployment authority cannot be read. Stop as `awaiting-approval` if there is no applicable template, template selection is materially ambiguous, or required details would have to be invented.

Use repository evidence and observed command output. Model output and editor diagnostics are not verification.

## Scope and constraints

### Repository safety

Before changes:

1. Resolve `$TARGET` to a canonical absolute path.
2. Confirm the expected Git repository, branch, and current commit.
3. Record staged, modified, untracked, and relevant ignored paths.
4. Record installed PHP, Composer, Node, npm, and deployment-validation tools.
5. Preserve all pre-existing work.
6. Stop as `awaiting-approval` if existing work overlaps required files.
7. Do not reset, clean, discard, stash, stage, commit, push, or rewrite user work.

Do not modify:

- `.agents/`;
- published task files;
- `docs/implementation/`;
- conformance counts or classifications; or
- unrelated business, UI, design, or documentation files.

### Dependency boundary

Use existing dependencies and framework primitives.

Stop as `awaiting-approval` before adding any direct Composer or npm dependency.

Do not add an authentication starter kit, role/permission package, audit package, encryption package, signed-URL package, rate-limit package, security suite, architecture package, storage SDK, MPIPS client, NPZ/NumPy/DICOM/FHIR client, AI/payment/notification client, or deployment library.

Do not change approved PHP, Laravel, or Filament constraints.

### Trusted authenticated context

Extend WP-01 context so authorization decisions derive only from trusted server-side sources.

Preserve when applicable:

- actor ID;
- session ID;
- roles and permissions;
- trusted active site;
- trusted case;
- assignment evidence/version;
- correlation/operation ID; and
- purpose.

Caller-supplied operator ID, role, permission, site, case, assignment, or purpose must never grant access.

Provide explicit trusted assignment and active-site resolver contracts without implementing Operator business data.

Use test-only HTTP/application fixtures to prove query, form, JSON, and header values cannot forge authorization context.

Do not add login pages, permission administration, site-selection UI, Operator assignment records, or business routes.

### Passwords, identifiers, and temporary credentials

Use Laravel's adaptive password hasher.

Implement a protected-identifier service separating:

- encrypted display value; and
- deterministic keyed lookup digest.

The lookup digest must use keyed cryptography with injected key material. Raw NIK/KK must not be logged. Missing/invalid keys must fail closed. Tests use isolated test keys.

Implement a temporary-credential issuer that:

- uses cryptographically secure randomness;
- persists only a password hash and mandatory-change state;
- returns plaintext only for immediate one-time handoff;
- never stores/logs plaintext;
- supports replacement/invalidation; and
- blocks normal use until replacement.

A generic user-schema extension is allowed. Do not implement B2B import, email, SMS, printing, or real handoff.

### Credential verification and suspension

Implement a credential-verification application service that:

- rate limits by a privacy-safe key;
- returns the same public failure for unknown and incorrect credentials;
- uses a dummy-hash path for unknown identifiers;
- denies suspended accounts;
- enforces temporary-password replacement;
- records sanitized audit evidence; and
- exposes no production login route or UI.

Add the minimum generic suspension state. Suspension must not delete the user or imply deletion of future member/medical/financial history.

### Audit infrastructure

Implement generic append-only audit infrastructure with, when applicable:

- event ID and version;
- actor, session, roles, permissions, site, and case;
- target type/ID and action;
- safe previous/new state or digest;
- reason;
- occurred and recorded UTC times;
- correlation/operation ID;
- source module or adapter;
- outcome; and
- sanitized metadata.

Audit storage must reject duplicate IDs, empty action/source, and sensitive payloads. Local state and audit must roll back together.

Expose append only; do not add update/delete operations, UI, or Filament resources.

Do not claim database-level immutability beyond actual enforcement.

### Context propagation and future adapter guard

Internal command/query dispatch must preserve trusted actor, purpose, correlation, site, and case context.

Provide a generic future external-adapter execution boundary requiring:

- authenticated adapter identity/credential-provider contract;
- configured audience;
- trusted actor/purpose/correlation context;
- sanitized request metadata digest;
- attempted/completed audit records;
- timeout/failure classification; and
- fail-closed behavior when prerequisites are missing.

Tests use fakes and make no network request. Do not implement a real adapter.

### Sanitized correlated logging

Implement reusable recursive log-context sanitation.

Security-relevant operations must carry correlation identity. Ordinary logs must not contain submitted passwords, raw NIK/KK, tokens, authorization headers, cookies, credentials, private keys, clinical payloads, NPZ/DICOM bytes, bank details, gateway payloads, or private-object bytes.

Tests must inspect captured records or an isolated log file and prove correlation presence and secret absence.

Report residual risk for third-party code that bypasses the logging boundary.

### Member minimization and binary boundary

Create a Member application-contract boundary for operator-facing projections.

It must require trusted context and named purpose, use explicit allowlisted fields, reject unrestricted model/array serialization, and omit unrelated contact, account, credential, medical, financial, identity-document, and image fields.

Use fixtures only; do not invent final workflow-specific projection fields.

Strengthen architecture tests so Member code cannot contain raw NPZ/DICOM upload, parser, storage, conversion, binary, or client implementations. Opaque references or authorized-grant contracts are allowed.

### Private encrypted objects and temporary access

Implement a provider-neutral private encrypted-object abstraction using isolated local test storage.

It must:

- encrypt before persistence;
- prohibit public visibility;
- use opaque keys;
- reject traversal/absolute paths;
- preserve checksum and encryption metadata;
- require trusted authorization and purpose for retrieval; and
- expose no permanent public URL.

Implement short-lived signed/MAC access grants binding target, actor/audience, purpose, issued time, expiry, correlation, and signature.

Expired, changed, malformed, wrong-audience, wrong-purpose, and wrong-target grants must fail.

Do not configure cloud storage, real identity images, result publication, DICOM download, or public proxy routes.

### Transaction lock and funding separation

Provide a reusable transaction/row-lock foundation for later booking quota, points, and walk-in operations.

It must use a database transaction and explicit row lock, preserve idempotency/context, roll back state/audit/outbox together, and exclude long-running external work. Prove it with neutral test tables.

Provide immutable funding-source values/policy preventing:

- business-funded use of personal funds; and
- consumer-funded use of reserved business funds.

Unknown/mismatched sources fail closed.

Do not create booking, points, wallet, quota, walk-in, payment, ledger, or refund tables/workflows.

### Untrusted-image policy

Implement a declarative fail-closed policy representing independent bounds for:

- file count;
- per-file and total bytes;
- decompressed bytes;
- dimensions;
- field count;
- CPU;
- memory;
- execution time;
- process count;
- temporary storage;
- accepted file/container form; and
- recovery/attempt window when applicable.

Do not invent production values. Production/staging values must come from version-controlled deployment configuration or injected configuration.

Tests use clearly labeled small fixture limits.

Do not parse NPZ, execute Python, load pickle, add image binaries, or implement conversion.

### Manifest signing and DICOM acceptance gate

Implement a generic Image Gateway manifest signer/verifier binding:

- conversion job ID;
- radiograph checksum;
- gain checksum;
- metadata checksum;
- manifest version;
- issued time;
- correlation ID; and
- key ID when applicable.

Use canonical serialization and injected cryptographic key material. Verification must fail for every declared mutation, invalid signature, unknown key, and malformed data.

Implement a provider-neutral permanent-acceptance gate requiring explicit validator evidence and rejecting checksum, identifier, manifest, conversion-identity, and missing-evidence mismatches.

Use fake validators only. Do not implement DICOM parsing, DICOM storage, real manifests, or MPIPS calls.

### MPIPS isolation and ownership

Enforce in code tests and deployment specialization that:

- only the Image Gateway worker may own a future MPIPS adapter;
- browsers, Member, Operator, Doctor, administrator web, and unrelated workers cannot call MPIPS;
- MPIPS is private and absent from the public reverse proxy;
- MPIPS receives no application DB, payment, or user-session credential;
- MPIPS owns no permanent storage, retries, identity, FHIR, queue, AI, publication, or payment behavior;
- temporary storage/recovery is bounded by configuration; and
- no module-to-module network boundary is introduced.

Do not invent exact MPIPS transport, authentication, idempotency, retry, or error mapping.

### Deployment-template specialization

Copy and specialize only applicable files from the resolved `Madeena-software/deploy-templates` revision.

The specialization must:

- record source repository, branch, SHA, and source paths;
- preserve template organization where practical;
- define Laravel web, queue, scheduler, and dedicated image-worker roles;
- preserve one database and one cache/queue foundation;
- place MPIPS only on a private network;
- keep MPIPS off the public reverse proxy;
- express supported CPU, memory, process, execution-time, and temp-storage controls;
- use non-root/least-write posture where supported;
- represent changes in version-controlled configuration;
- follow approved CI/CD paths;
- avoid SSH procedures;
- contain environment names but no secrets; and
- avoid unsupported production assumptions.

Do not copy unrelated templates or redesign the stack.

Do not commit `.env`, production environment files, credentials, keys, domains, hosts, IPs, certificates, or generated secrets.

A local `.env` may be used temporarily and must remain ignored/uncommitted.

### CI/CD policy

Implement or specialize template-authorized CI checks for:

- Composer validation and audit;
- formatting;
- complete PHP tests;
- frontend build;
- architecture/security tests; and
- deployment configuration validation.

Do not trigger deployment or access production/staging.

Do not print secrets or environment dumps.

### Security evidence

Create a bounded `docs/security/` artifact covering:

- assets and actors;
- trust boundaries and entry points;
- abuse cases and privacy risks;
- authentication/authorization assumptions;
- audit/logging;
- private objects/access grants;
- untrusted-input and MPIPS isolation;
- deployment/CI;
- unresolved decisions and dependencies;
- observed verification; and
- residual risks.

Do not modify conformance documents or claim unobserved approvals.

### Out of scope

Outside WP-02:

- production/staging access or deployment;
- SSH;
- real secrets;
- real object storage;
- real MPIPS execution;
- exact MPIPS contract;
- real NPZ parsing or Python/NumPy/pickle;
- real DICOM parsing/storage;
- production input limits not supplied by authority;
- retention/lawful-basis/privacy-notice decisions;
- login/registration/recovery/suspension UI;
- Filament product UI;
- Operator assignment/site business data;
- Member identity records;
- booking, points, wallet, funding, walk-in, payment, FHIR, AI, notification, report, earning, or payout workflows;
- public result routes;
- commits, pushes, pull requests, issues, estimates, or delivery dates.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `12`
- Approval gates: Existing accepted dependencies and read-only access to the public deployment-template repository are in scope when the user explicitly executes this task. Stop as `awaiting-approval` before adding a direct dependency, changing framework constraints, choosing between materially ambiguous templates, inventing production limits/secrets, making legal/privacy/clinical/financial decisions, changing module ownership, implementing a real adapter, exposing a public route, adding business behavior, modifying `.agents/` or conformance documents, triggering CI/deployment, accessing production/staging, using SSH, or performing a destructive operation.

## Execution procedure

1. Resolve `$TARGET` and required capabilities.
2. Read all required instructions, context, conformance documents, WP-01 task, and current implementation.
3. Confirm the exact WP-02 requirement assignment.
4. Record initial Git/runtime/tool state and stop for overlapping changes.
5. Run the complete existing WP-01 verification suite.
6. Read and resolve the approved deployment-template repository and applicable template.
7. Map every WP-02 requirement to planned code/configuration/tests.
8. Implement trusted context and forgery-negative tests.
9. Implement password, protected-identifier, temporary-credential, verification, and suspension foundations.
10. Implement audit, context propagation, adapter guard, and sanitized logging.
11. Implement Member minimization and NPZ/DICOM boundary tests.
12. Implement encrypted private objects and short-lived access grants.
13. Implement transaction/row-lock and funding-source guards.
14. Implement fail-closed untrusted-image limits.
15. Implement manifest signing and DICOM acceptance gate.
16. Strengthen MPIPS ownership/network architecture tests.
17. Copy and specialize only the applicable deployment-template files.
18. Implement/specialize CI validation without triggering it.
19. Create the WP-02 security evidence artifact.
20. Add focused unit, feature, integration, migration, architecture, logging, storage, configuration, and negative tests.
21. Run formatting, Composer validation/audit, full PHP tests, frontend build, deployment static validation, and CI configuration validation.
22. Inspect logs, encrypted test storage, routes, migrations, dependencies, deployment files, environment files, and complete Git diff.
23. Remove temporary clones, local `.env`, generated keys, test databases, logs, caches, build output, and runtime artifacts.
24. Re-run the smallest complete verification set.
25. Re-read this unchanged task file.
26. Stop when all criteria pass, approval is required, progress is blocked, execution fails, or iterations are exhausted.

## Acceptance criteria

- [ ] WP-01 verification passes before WP-02 changes.
- [ ] The exact declared WP-02 requirement assignment is confirmed.
- [ ] Pre-existing work is preserved and `.agents/` plus conformance documents remain unchanged.
- [ ] No unapproved dependency or framework-constraint change occurs.
- [ ] Trusted context cannot be forged through request inputs.
- [ ] Passwords use the framework hasher.
- [ ] Protected display values and keyed lookup digests are separate and fail closed without key material.
- [ ] Raw NIK/KK/password values are absent from logs, audit, database plaintext, cache, files, and fixtures.
- [ ] Temporary credentials use secure randomness, persist only hash/state, and require replacement.
- [ ] Unknown and incorrect credentials have the same public failure and are rate limited.
- [ ] Suspended login is denied without deleting the user.
- [ ] Audit events preserve required context, are append-only through the application interface, reject duplicate IDs, sanitize payloads, and roll back transactionally.
- [ ] Internal calls preserve trusted context; fake external-adapter calls require authentication/audience/audit.
- [ ] Captured logs contain correlation identity and no prohibited sensitive value.
- [ ] Operator-facing Member projections use explicit allowlists and omit extra fixture fields.
- [ ] Private-object bytes are encrypted before private persistence and require authorization/purpose to retrieve.
- [ ] Short-lived grants reject expiry, mutation, wrong audience, purpose, and target.
- [ ] Member code contains no raw NPZ/DICOM parser, storage, upload, binary, or conversion implementation.
- [ ] Transaction/row-lock tests prove commit and rollback with audit/outbox evidence.
- [ ] Funding-source policy rejects both prohibited cross-source combinations and unknown sources.
- [ ] No booking, points, wallet, payment, or other business workflow/table is introduced.
- [ ] Untrusted-input policy represents every required bound and fails closed on missing/invalid/exceeded limits.
- [ ] No production limit value is invented.
- [ ] No Python, NumPy, pickle, NPZ parser, image binary, or conversion code is introduced.
- [ ] Manifest signing binds all required identities/checksums/version/time/correlation and rejects mutations.
- [ ] DICOM acceptance requires explicit fake-validator evidence and rejects all declared mismatches.
- [ ] No DICOM library, parser, storage, or real fixture is added.
- [ ] Only the future Image Gateway worker boundary may own MPIPS access.
- [ ] MPIPS is private, absent from public proxy, credential-minimized, stateless for business data, and bounded in specialized configuration.
- [ ] No exact MPIPS transport/auth/idempotency/retry/error contract is invented.
- [ ] Deployment-template repository, branch, SHA, source paths, and specializations are recorded.
- [ ] Only applicable template files are copied/specialized.
- [ ] Deployment defines web, queue, scheduler, and image-worker roles with one DB and one cache/queue foundation.
- [ ] Deployment configuration contains no secrets, actual `.env`, hosts, domains, IPs, certificates, or generated keys.
- [ ] No SSH instruction or production/staging action occurs.
- [ ] CI configuration validates required checks without triggering deployment.
- [ ] Deployment/container static validation passes.
- [ ] The security artifact records threats, controls, dependencies, evidence, and residual risks without claiming unobserved approval.
- [ ] Formatting, `composer validate --strict`, `composer audit`, full PHP tests, and frontend build pass.
- [ ] Final Git inspection contains no secret, runtime artifact, dependency directory, database, log, cache, or build output.
- [ ] The execution report maps evidence to every assigned WP-02 requirement and distinguishes local verification from external/approval gaps.
- [ ] No business workflow, product UI, real external adapter, real image processing, commit, push, PR, issue, production, staging, or SSH operation occurs.
- [ ] The result does not claim another work package or full MHCS Core conformance.

## Verification

- Method: Inspect the complete Git diff and manifests; run existing WP-01 checks, formatter, `composer validate --strict`, `composer audit`, complete PHP tests, frontend build, architecture and authorization-negative tests, audit transaction tests, captured-log sanitation tests, encrypted-object tests, access-grant tests, row-lock/funding tests, input-limit tests, manifest mutation tests, DICOM gate tests, deployment-template provenance checks, the selected template's static deployment validator, and CI configuration validation without triggering deployment; inspect final Git status, ignored artifacts, environment files, secrets, routes, migrations, network clients, NPZ/DICOM/Python content, and running processes.
- Expected result: The accepted WP-01 modular monolith gains a locally verified security foundation with trusted context, secure credential/identifier handling, append-only audit, sanitized correlated logging, private encrypted objects, temporary grants, transaction/funding guards, fail-closed input policy, manifest binding, DICOM gating, private MPIPS isolation, and deployment/CI configuration specialized from the approved Madeena template, while no business workflow, product UI, real external adapter, production secret/access, SSH, or out-of-scope mutation exists.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or `exhausted`.
- `succeeded`: Every required local/static criterion passes and external/approval gaps are reported honestly.
- `failed`: The result is unsafe, incomplete, leaks data, weakens boundaries, or leaves out-of-scope changes.
- `blocked`: Required instructions, prerequisites, network, deployment authority/template, runtime, or validator are unavailable.
- `awaiting-approval`: Completion needs a dependency, ambiguous template choice, production value, policy decision, ownership change, public interface, real integration, deployment action, or scope change.
- `exhausted`: Iterations end before all criteria pass.
- Report selected model/runtime, capabilities, outcome, initial/final branch and commit.
- Report the resolved deploy-template repository, branch, commit, selected source paths, and specializations.
- Report PHP, Laravel, Filament, Composer, Node, npm, and deployment-validator versions.
- Report dependency changes and all affected files grouped by security area.
- Report observed commands/results and requirement-by-requirement evidence for all declared WP-02 IDs.
- Classify each requirement as locally verified, locally implemented but externally unverified, blocked by production values/contract, awaiting approval, or not implemented.
- Report residual authorization, logging, audit, cryptographic, object-storage, MPIPS, deployment, and operational risks.
- Report `git status --short` and `git diff --name-only`.
- Confirm whether `.agents/`, conformance documents, business behavior, UI, real adapters, image processing, production values, secrets, deployment actions, or SSH instructions changed.
- Confirm no commit, push, PR, issue, production, staging, SSH, or external-system operation occurred.
- Keep runtime values, progress, command output, results, credentials, private prompts, and hidden reasoning outside this immutable task file.
- Do not modify this task file during execution.
