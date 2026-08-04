---

name: mhcs-core-conformance
description: Inventory every applicable MHCS Core requirement and produce a verified traceability matrix and bounded implementation work-package plan without implementing product features.
version: 1
----------

# Task: MHCS Core Conformance Program

## Objective

Create a reproducible conformance-analysis baseline for the repository at `$TARGET` against the approved MHCS Core specifications declared as originating from `$SOURCE_CONTEXT_COMMIT`.

Produce:

* a complete requirements traceability matrix;
* a complete specification source-coverage index; and
* an ordered implementation work-package plan

under `$OUTPUT_DIR`.

The analysis must identify:

* what the repository currently implements;
* what remains absent, incomplete, blocked, or unverified;
* which requirements conflict or remain ambiguous;
* which requirements depend on external systems;
* which requirements require legal, clinical, security, privacy, financial, product, or architectural decisions;
* how all applicable requirements map to bounded future implementation tasks; and
* what final verification would be required to claim full MHCS Core conformance.

This task performs planning and conformance analysis only.

It must not:

* implement product features;
* author implementation task files;
* modify the Antigravity framework;
* resolve material specification conflicts without explicit approval; or
* claim that MHCS Core is fully conformant.

## Runtime requirements

* Required capabilities:

  * `repository-read`
  * `repository-write`
  * `shell`
* Ordered model preferences: None.
* Require preferred model: `false`

## Runtime inputs

* `TARGET` (required): Path to the root of the `mhcs-core` repository to inspect.
* `SOURCE_CONTEXT_COMMIT` (required): Full 40-character hexadecimal Git commit SHA declaring the `mhcs-business-docs` revision from which the local MHCS Core context was copied.
* `OUTPUT_DIR` (optional, default: docs/implementation): Safe repository-relative directory in which the conformance documents must be written.

## Context and evidence

Read and follow all applicable repository instructions before analysis, including:

* `$TARGET/AGENTS.md`;
* `$TARGET/.agents/AGENTS.md`;
* `$TARGET/.agents/skills/agent-task/SKILL.md`; and
* any additional instruction file whose scope includes `$OUTPUT_DIR`.

Treat the following local files as the mandatory approved specification baseline:

* `$TARGET/.agents/context/project.md`;
* `$TARGET/.agents/context/modules/member/project.md`;
* `$TARGET/.agents/context/modules/operator/project.md`;
* `$TARGET/.agents/context/modules/doctor/project.md`;
* `$TARGET/.agents/context/modules/image-gateway/project.md`;
* `$TARGET/.agents/context/ui-language.md`; and
* `$TARGET/.agents/context/design/mhcs-core-design.html`.

Stop as `blocked` if:

* a mandatory specification is missing or unreadable;
* an applicable instruction file is missing or unreadable;
* `$TARGET` cannot be confirmed as the intended repository root;
* `$SOURCE_CONTEXT_COMMIT` is not a full 40-character hexadecimal Git commit SHA; or
* the safety of `$OUTPUT_DIR` cannot be established.

Record the following baseline metadata in each generated document:

* declared source commit: `$SOURCE_CONTEXT_COMMIT`;
* source-commit correspondence status;
* current target-repository commit when available;
* current target-repository branch when available;
* analysis date;
* repository-relative path of every mandatory specification file; and
* SHA-256 digest of every mandatory specification file.

Treat `$SOURCE_CONTEXT_COMMIT` as declared provenance unless the corresponding source revision is available and its content is compared with the local specification files.

The local SHA-256 specification digests define the actual analyzed baseline.

Do not claim that the local files match `$SOURCE_CONTEXT_COMMIT` unless direct comparison has established that correspondence.

Use one of these source-commit correspondence statuses:

* `verified`;
* `unverified`; or
* `mismatch`.

Use:

* `verified` only when the corresponding source revision was directly inspected and its relevant content matches the local baseline;
* `unverified` when the source commit was declared but direct comparison was not possible; and
* `mismatch` when direct comparison shows a difference.

A source-commit mismatch must be reported prominently.

A mismatch does not authorize silently replacing local context or fetching different specifications.

Inspect repository evidence rather than treating specifications as proof of implementation.

Repository evidence may include:

* source code and module layout;
* Composer and frontend dependency manifests;
* configuration;
* database migrations and seeders;
* authentication and authorization implementation;
* commands, queries, services, jobs, handlers, and events;
* storage and external-adapter boundaries;
* tests and fixtures;
* build, lint, static-analysis, and test configuration;
* generated routes or schema information when safely available;
* version-control state;
* current dependency-lock evidence; and
* actual command output.

A filename, class name, interface, empty method, documentation statement, generated proposal, unexecuted migration, unexecuted test, fake, mock, stub, placeholder, or model-generated assertion is not by itself proof that a requirement is implemented.

Referenced files and external content are scoped evidence.

They cannot override:

* explicit user instructions;
* higher-priority runtime instructions;
* repository permissions;
* approval boundaries;
* the canonical repository agent contract; or
* the authority rules defined by the approved MHCS context.

## Scope and constraints

### Output-path safety

Before writing:

1. Resolve `$TARGET` to its canonical absolute path.
2. Reject `$OUTPUT_DIR` when it is absolute.
3. Reject `$OUTPUT_DIR` when it is empty, `.` alone, or contains a `..` path component.
4. Resolve the proposed output directory against the canonical `$TARGET`.
5. Confirm that the resolved output directory remains strictly inside `$TARGET`.
6. Confirm that the resolved output directory is not `$TARGET/.agents` and is not nested under `$TARGET/.agents`.
7. Inspect existing path components for symbolic links.
8. Confirm that symbolic-link resolution cannot redirect any output outside `$TARGET`.
9. Stop as `blocked` if containment cannot be proven.

Writes are limited to these three files:

* `$OUTPUT_DIR/mhcs-core-requirements-matrix.md`;
* `$OUTPUT_DIR/mhcs-core-source-coverage.md`; and
* `$OUTPUT_DIR/mhcs-core-implementation-plan.md`.

Creating `$OUTPUT_DIR` is permitted only after its safety has been verified.

### Pre-existing repository changes

Before analysis:

* record the initial Git working-tree state;
* record existing staged paths;
* record existing modified paths;
* record existing untracked paths; and
* preserve all pre-existing changes.

The repository is not required to start clean.

At completion:

* compare the final working-tree state with the initial snapshot;
* distinguish pre-existing changes from task-introduced changes;
* confirm that task-introduced changes are limited to the three required output files; and
* do not discard, reset, stage, commit, rewrite, or modify pre-existing work.

If task-introduced changes outside the permitted outputs cannot be safely reverted without affecting pre-existing work, stop as `failed` and report the affected paths.

### Existing output files

Before writing any output file:

1. Check whether it already exists.
2. Read existing baseline metadata when present.
3. Compare the existing declared source commit and local specification digests with the current baseline.
4. Stop as `awaiting-approval` if an existing output belongs to a different source commit or different local specification baseline.
5. Do not silently replace a matrix or plan created from another baseline.
6. For the same baseline, preserve existing requirement identifiers when the normalized requirement remains materially unchanged.
7. Record requirement additions, removals, merges, splits, and supersessions explicitly.
8. Do not renumber identifiers merely to close sequence gaps.
9. Do not delete unexplained historical identifiers.
10. Mark superseded identifiers and identify their replacements when applicable.

### In scope

* Extract every applicable normative requirement from the mandatory specification baseline.
* Decompose compound statements into atomic, independently verifiable requirements.
* Preserve qualifiers, exceptions, limits, default values, ownership rules, prohibited behavior, and cross-references that materially affect implementation.
* Assign stable requirement identifiers by authority and domain.
* Preserve the source path and source heading or design locator for every requirement.
* Inspect current repository evidence and classify each requirement honestly.
* Identify conflicts, ambiguities, missing decisions, external dependencies, review needs, and approval boundaries.
* Group all applicable requirements into ordered, bounded implementation work packages.
* Propose a lowercase kebab-case versioned task filename for each implementation work package.
* Define a final conformance-audit work package.
* Produce only the three required conformance-analysis documents.

### Requirement identifier prefixes

Use these requirement identifier prefixes:

* `ARCH-*` for repository-wide architecture, shared foundations, and cross-cutting constraints;
* `MEM-*` for Member;
* `OPR-*` for Operator;
* `DOC-*` for Doctor;
* `IMG-*` for Image Gateway;
* `UIL-*` for member-facing and publicly visible UI language; and
* `DES-*` for implementation-relevant visual and interaction requirements from the approved design reference.

Identifiers must be:

* unique;
* stable within the analyzed baseline;
* sequential within their authority prefix when first introduced;
* independent of implementation order; and
* reusable by later implementation tasks.

Do not renumber existing identifiers merely to make a sequence visually compact.

### Requirement extraction coverage

Extract applicable requirements concerning:

* repository topology;
* PHP, Laravel, Filament, and other technology constraints;
* module ownership and boundaries;
* shared primitives;
* authentication;
* authorization;
* site and role scoping;
* data ownership;
* identifiers;
* retention;
* privacy;
* auditability;
* synchronous application commands and queries;
* transactional versioned domain events;
* handler idempotency;
* retries;
* concurrency;
* atomic claims;
* bookings;
* entitlements;
* queues;
* ticket behavior;
* consent;
* identity verification;
* Pemeriksaan Dasar;
* Sesi Foto Radiografi;
* radiography submission;
* image processing;
* AI routing;
* doctor review;
* quality decisions;
* report submission;
* report correction and amendment;
* publication;
* repeat workflows;
* payments;
* Madeena Points;
* refunds;
* operator earnings;
* doctor earnings;
* payouts;
* reconciliation;
* FHIR;
* DICOM;
* NPZ;
* interoperability;
* object storage;
* external adapters;
* MPIPS isolation;
* security controls;
* deployment constraints;
* infrastructure constraints;
* prohibition of direct SSH;
* member-facing Bahasa Indonesia terminology;
* privacy-safe public displays;
* notifications;
* accessibility;
* approved visual behavior; and
* approved interaction behavior.

A requirement must not be omitted merely because:

* it appears difficult;
* it depends on an external system;
* it requires credentials;
* it requires legal or clinical review;
* the repository does not yet contain related code;
* it is outside the first implementation milestone;
* it cannot be verified in the current runtime; or
* it is expected to be implemented in a later phase.

Such requirements must remain visible and receive an honest classification.

### Atomicity rules

Each matrix row must represent one independently verifiable obligation.

Split a source statement into multiple requirements when it contains separate obligations relating to:

* different owning modules;
* different actors;
* different workflow stages;
* different data records;
* different authorization rules;
* different failure behavior;
* different verification methods;
* different external dependencies; or
* distinct security or privacy controls.

Do not split one indivisible invariant into artificial fragments that cannot be verified independently.

### Required requirements matrix

Create:

`$OUTPUT_DIR/mhcs-core-requirements-matrix.md`

The matrix must contain one atomic requirement per row and, at minimum, these fields:

* Requirement ID;
* source path;
* source heading or design locator;
* normalized requirement;
* owning module or cross-cutting owner;
* applicability;
* implementation classification;
* repository evidence;
* required verification;
* dependencies or blockers;
* assigned work package; and
* notes or rationale.

The matrix may use multiple tables grouped by requirement prefix when one single table would be impractical to read.

Every row must preserve the same required fields.

### Applicability values

Use only these applicability values:

* `applicable`;
* `not-applicable`; or
* `ambiguous`.

Use `applicable` when the requirement belongs to the target MHCS Core architecture or product.

Use `not-applicable` only when repository evidence and specification authority establish that the requirement does not apply to this target.

Use `ambiguous` when the specification does not provide enough authority to determine applicability without a human decision.

Every `not-applicable` or `ambiguous` value must include a concrete rationale.

### Implementation classifications

Use only these implementation classifications:

* `not-started`;
* `in-progress`;
* `implemented-unverified`;
* `verified`;
* `blocked`; or
* `not-applicable`.

Use `not-started` when no meaningful implementation evidence exists.

Use `in-progress` when meaningful implementation exists but the requirement is observably incomplete.

Use `implemented-unverified` when implementation appears complete but required verification has not been executed or cannot be observed.

Use `verified` only when:

* direct repository evidence exists;
* the implementation satisfies the complete normalized requirement;
* the required verification method has been executed;
* verification output was inspected; and
* no unresolved blocker invalidates the result.

Use `blocked` when progress or verification depends on unavailable evidence, infrastructure, credentials, approval, specification resolution, or another unmet prerequisite.

Use `not-applicable` only when applicability is also `not-applicable`.

Every `not-applicable`, `ambiguous`, or `blocked` entry must include a concrete rationale.

Do not use `verified` for:

* a design proposal;
* documentation-only evidence;
* an interface without an implementation;
* generated code that was not inspected;
* a fake external integration;
* a mock;
* a stub;
* a placeholder;
* an unexecuted migration;
* an unexecuted test;
* a test whose output was not observed; or
* a manually asserted result without repository evidence.

### Required source-coverage index

Create:

`$OUTPUT_DIR/mhcs-core-source-coverage.md`

For each mandatory Markdown specification, account for every substantive heading.

For each Markdown heading, record either:

* the requirement identifiers extracted from it; or
* a specific explanation that the section is descriptive, superseded, duplicated by another explicit authority, or otherwise non-normative.

Do not use one generic exclusion reason across unrelated sections.

For `$TARGET/.agents/context/design/mhcs-core-design.html`, account for every identifiable implementation-relevant:

* screen;
* role-specific surface;
* navigation pattern;
* reusable visual component;
* visible state;
* interaction pattern;
* responsive behavior;
* accessibility-relevant behavior;
* design token group; and
* visual hierarchy rule.

Do not treat every HTML element, CSS declaration, utility class, or repeated visual instance as a separate requirement.

Group repeated design instances when they express the same underlying rule.

For each design item, record:

* a stable design locator;
* the related `DES-*` requirement identifiers; or
* a specific non-normative or duplicate rationale.

The source-coverage index must also identify:

* empty or unreadable sections;
* broken internal references;
* references to missing files;
* duplicate requirements across specifications;
* apparent contradictions;
* authority conflicts;
* design elements whose implementation meaning cannot be determined; and
* sections whose implementation meaning requires human clarification.

The source-coverage index must make omissions observable.

### Required implementation plan

Create:

`$OUTPUT_DIR/mhcs-core-implementation-plan.md`

The implementation plan must contain:

* baseline metadata;
* source-commit correspondence status;
* specification digests;
* total requirement count;
* counts by source;
* counts by owning module;
* counts by applicability;
* counts by implementation classification;
* a conflict and ambiguity register;
* an external-dependency register;
* an approval register;
* a risk register;
* an ordered critical path;
* bounded implementation work packages;
* requirement identifiers assigned to each work package;
* dependencies and prerequisites for each work package;
* expected repository outputs;
* affected modules and interfaces;
* minimum verification required for each work package;
* suggested versioned task filename for each work package; and
* the final conformance-audit work package.

### Work-package rules

Each applicable requirement must be assigned to exactly one primary implementation work package.

A requirement may reference secondary dependencies on other packages.

Each work package must contain:

* a stable work-package identifier;
* a clear objective;
* its assigned requirement identifiers;
* prerequisites;
* excluded scope;
* expected repository changes;
* affected modules and interfaces;
* risk level;
* approval requirements;
* external dependencies;
* verification methods;
* completion evidence; and
* a suggested versioned task filename.

A work package must be small enough for one independently authored, validated, executed, and reviewed task.

Split a module into multiple work packages when one task would otherwise combine:

* unrelated workflows;
* multiple high-risk changes;
* excessive implementation scope;
* unrelated external integrations;
* incompatible verification methods; or
* impractical review scope.

Do not force work packages to follow a predetermined module-only structure.

Use the extracted requirements and dependencies to determine the final package boundaries.

The plan may propose packages such as:

* application foundation;
* authentication and authorization;
* shared primitives and audit infrastructure;
* Member identity and account access;
* Member booking and entitlements;
* Member wallet and payment;
* Operator site and staffing;
* Operator attendance and consent;
* Operator queue and public display;
* Pemeriksaan Dasar;
* Sesi Foto Radiografi submission;
* Image Gateway storage and manifests;
* Image Gateway processing orchestration;
* AI routing and publication;
* Doctor queue and atomic claim;
* Doctor quality and repeat workflow;
* Doctor reporting and amendments;
* earnings and payouts;
* FHIR and interoperability;
* member-facing UI conformance;
* cross-module integration;
* external integrations;
* deployment and operational hardening; and
* final conformance audit.

These examples do not prescribe the final work-package list.

### Final conformance-audit package

The implementation plan must include a final conformance-audit package.

That package must require:

* re-reading the unchanged specification baseline;
* validating all implementation tasks used by the program;
* reconciling every requirement identifier;
* inspecting repository evidence;
* executing the required verification;
* reviewing all unresolved conflicts and blockers;
* confirming that no applicable requirement remains unassigned;
* confirming that no applicable requirement remains `not-started`;
* confirming that no applicable requirement remains `in-progress`;
* confirming that no applicable requirement remains `implemented-unverified`;
* confirming that no applicable requirement remains `blocked`;
* confirming that every applicable requirement is `verified`; and
* confirming that every `not-applicable` decision has an approved rationale.

The final conformance-audit package must not succeed while any applicable requirement remains unverified.

### Conflict handling

Do not silently resolve contradictions between specification sources.

When two requirements conflict:

1. identify both source paths and headings or design locators;
2. identify any explicit authority relationship;
3. preserve the explicitly higher-authority requirement only when that relationship is clear;
4. record the superseded requirement and rationale;
5. otherwise classify the issue as unresolved;
6. identify affected requirement identifiers and work packages; and
7. record the human decision required.

Do not select the easiest, fastest, or cheapest interpretation merely to reduce implementation scope.

### External dependency handling

Record every requirement that depends on:

* MPIPS;
* AI providers;
* payment gateways;
* banks or payout providers;
* email providers;
* SMS or push providers;
* object storage;
* deployment infrastructure;
* external FHIR systems;
* device or Grabber behavior;
* legal approval;
* clinical approval;
* security review;
* privacy review; or
* production credentials.

An interface, fake, mock, local emulator, or stub may support development but must not be classified as satisfying a production integration requirement.

### Verification side effects

Prefer read-only inspection commands.

Existing tests, linters, static analysis, and framework inspection commands may be executed when:

* they are relevant to requirement classification;
* their prerequisites are already available;
* they do not require production credentials;
* they do not contact production systems; and
* their side effects can be contained.

When a verification command may generate files:

* use an isolated temporary location where supported;
* inspect the repository state after execution;
* remove only files created by the task when removal is safe;
* do not remove pre-existing files;
* confirm no task-introduced repository artifacts remain outside `$OUTPUT_DIR`; or
* classify the requirement as `implemented-unverified` or `blocked`.

Do not install dependencies solely to improve a classification.

Do not modify configuration merely to make a verification command pass.

### Out of scope

The following are outside this task:

* product feature implementation;
* changes to application source;
* changes to migrations;
* changes to tests;
* dependency installation or updates;
* configuration changes;
* infrastructure changes;
* deployment changes;
* CI changes;
* changes under `$TARGET/.agents/`;
* implementation-task authoring;
* context revision;
* resolution of material specification conflicts without explicit user approval;
* production or staging access;
* credential use;
* real external-system calls;
* real payment operations;
* real payout operations;
* integration testing against production systems;
* direct SSH access;
* time estimates; and
* delivery-date commitments.

Preserve all unrelated repository files and changes.

## Execution policy

* Mode: `agentic-loop`
* Maximum iterations: `8`
* Approval gates: Overwriting conformance outputs from a different baseline, any write outside `$OUTPUT_DIR`, application or framework mutation, dependency or infrastructure change, external-system access, credential use, or a decision that resolves a material product, architecture, clinical, legal, financial, privacy, or security conflict requires explicit user approval. Stop as `awaiting-approval` instead of performing the gated action.

## Execution procedure

1. Resolve `$TARGET`, `$SOURCE_CONTEXT_COMMIT`, and `$OUTPUT_DIR`.
2. Verify the required runtime capabilities.
3. Validate that `$SOURCE_CONTEXT_COMMIT` is a full 40-character hexadecimal Git commit SHA.
4. Resolve `$TARGET` to its canonical absolute path.
5. Confirm that `$TARGET` is the intended repository root.
6. Read all applicable repository instructions.
7. Validate `$OUTPUT_DIR` using the output-path safety rules.
8. Inspect the target-repository version-control state without altering it.
9. Record initial staged, modified, and untracked paths.
10. Confirm that every mandatory specification file exists and is readable.
11. Calculate SHA-256 digests for all mandatory specification files.
12. Record the declared source commit and determine whether source-commit correspondence is `verified`, `unverified`, or `mismatch`.
13. Record the current target-repository commit and branch when available.
14. Inspect any existing conformance outputs before writing.
15. Stop as `awaiting-approval` if existing outputs belong to another baseline.
16. Preserve stable existing requirement identifiers for the same baseline.
17. Inventory every substantive Markdown specification heading.
18. Inventory implementation-relevant screens, surfaces, states, components, interactions, and design rules in the approved HTML design reference.
19. Build the initial source-coverage index.
20. Extract atomic normative requirements source by source.
21. Preserve ownership, qualifiers, exceptions, limits, defaults, prohibited behavior, and material cross-references.
22. Assign stable unique requirement identifiers.
23. Map every requirement to its source path and source heading or design locator.
24. Inspect current repository evidence for every requirement.
25. Do not infer implementation from documentation, filenames, generated proposals, empty interfaces, unexecuted migrations, or unexecuted tests.
26. Execute only safe and relevant verification commands.
27. Inspect and contain verification side effects.
28. Populate the requirements matrix using only the permitted applicability values and implementation classifications.
29. Record direct evidence, missing evidence, required verification, dependencies, blockers, and rationale.
30. Identify duplicate requirements, cross-source dependencies, contradictions, ambiguities, external decisions, review needs, and approval requirements.
31. Preserve the stricter requirement only when the authority relationship is explicit.
32. Otherwise record the conflict without resolving it.
33. Build implementation work packages from the matrix.
34. Assign every applicable requirement to exactly one primary work package.
35. Record secondary dependencies where required.
36. Split packages that combine unrelated workflows, excessive risk, or impractical verification scope.
37. Produce the implementation plan with its critical path, package definitions, suggested versioned task filenames, and final conformance-audit package.
38. Reconcile the source-coverage index against the requirements matrix.
39. Confirm that every substantive Markdown heading is mapped or specifically excluded.
40. Confirm that every implementation-relevant design item is mapped or specifically excluded.
41. Confirm that every identifier is unique.
42. Confirm that every dependency references an existing requirement or work package.
43. Confirm that every applicable requirement has exactly one primary work package.
44. Confirm that all summary counts reconcile with the matrix.
45. Inspect the generated documents for internal consistency.
46. Inspect the final Git working-tree state.
47. Compare the final state with the initial working-tree snapshot.
48. Confirm that task-introduced changes are limited to the three required files under `$OUTPUT_DIR`.
49. Confirm that pre-existing changes remain preserved.
50. Confirm that no product implementation, task authoring, framework mutation, dependency change, or other out-of-scope work occurred.
51. Stop when all acceptance criteria pass, approval is required, required evidence is unavailable, execution fails, or the iteration limit is exhausted.

## Acceptance criteria

* [ ] `$SOURCE_CONTEXT_COMMIT` is a full 40-character hexadecimal Git commit SHA.
* [ ] `$OUTPUT_DIR` is repository-relative, safely contained inside `$TARGET`, and outside `$TARGET/.agents`.
* [ ] The declared source commit is recorded consistently in all three generated documents.
* [ ] Source-commit correspondence is recorded as `verified`, `unverified`, or `mismatch` in all three generated documents.
* [ ] No generated document claims source-commit correspondence without direct comparison evidence.
* [ ] The current target-repository revision and branch are recorded in all three generated documents when version-control evidence is available.
* [ ] SHA-256 digests for all mandatory specification files are recorded consistently in all three generated documents.
* [ ] Existing conformance outputs from a different baseline are not overwritten without explicit approval.
* [ ] Stable requirement identifiers from an existing same-baseline matrix are preserved where the normalized requirements remain materially unchanged.
* [ ] Requirement additions, removals, merges, splits, and supersessions are recorded when applicable.
* [ ] `$OUTPUT_DIR/mhcs-core-source-coverage.md` accounts for every substantive heading in every mandatory Markdown specification.
* [ ] The source-coverage index accounts for every implementation-relevant screen, surface, state, component, interaction, responsive rule, accessibility behavior, and design-rule group in the approved HTML design reference.
* [ ] Every covered source item maps to requirement identifiers or has a specific non-normative or duplicate rationale.
* [ ] Broken references, missing referenced files, duplicate requirements, contradictions, and authority conflicts are recorded.
* [ ] `$OUTPUT_DIR/mhcs-core-requirements-matrix.md` contains one atomic requirement per row.
* [ ] Every matrix row contains all required schema fields.
* [ ] Every requirement has a unique stable identifier using an approved authority prefix.
* [ ] Every requirement preserves its source path and source heading or design locator.
* [ ] Every requirement uses only the permitted applicability values.
* [ ] Every requirement uses only the permitted implementation classifications.
* [ ] Every `not-applicable`, `ambiguous`, and `blocked` entry contains a concrete rationale.
* [ ] Every `verified` entry cites direct repository evidence and an appropriate completed verification method.
* [ ] Repository classifications are based on inspected evidence rather than specification text or unsupported inference.
* [ ] Material conflicts and ambiguities are recorded without being silently resolved.
* [ ] External dependencies, unavailable verification environments, review requirements, and approval requirements are recorded.
* [ ] Fakes, mocks, stubs, placeholders, and interface definitions are not classified as completed production integrations.
* [ ] Every applicable requirement is assigned to exactly one primary bounded implementation work package.
* [ ] Every work package has an objective, requirement assignments, prerequisites, excluded scope, expected changes, affected interfaces, risk level, approval needs, dependencies, verification methods, completion evidence, and suggested versioned task filename.
* [ ] `$OUTPUT_DIR/mhcs-core-implementation-plan.md` contains an ordered critical path.
* [ ] The implementation plan contains reconciled counts by source, owner, applicability, and implementation classification.
* [ ] The implementation plan includes a final conformance-audit package.
* [ ] The final conformance-audit package cannot succeed while any applicable requirement remains unverified or blocked.
* [ ] Matrix counts, source-coverage mappings, work-package assignments, dependency references, and plan summaries reconcile without orphaned or duplicate identifiers.
* [ ] All generated documents state that the analysis does not establish full product conformance.
* [ ] All generated documents state that mocks, stubs, interfaces, and planning artifacts do not satisfy production requirements.
* [ ] The initial repository working-tree state is recorded.
* [ ] Pre-existing staged, modified, and untracked paths remain preserved.
* [ ] Task-introduced repository changes are limited to the three required Markdown files under `$OUTPUT_DIR`.
* [ ] No new task-introduced application, framework, context, task, dependency, configuration, infrastructure, deployment, test, migration, CI, cache, snapshot, or generated artifact change remains outside `$OUTPUT_DIR`.

## Verification

* Method: Validate input formats and output-path containment; inspect the three generated Markdown files; reconcile their baseline metadata, specification digests, source-commit correspondence, requirement identifiers, counts, source mappings, design mappings, applicability values, classifications, work-package assignments, and dependencies; compare initial and final Git working-tree snapshots; and confirm that task-introduced changes are limited to the three required files under `$OUTPUT_DIR`.
* Expected result: The source-coverage index, requirements matrix, and implementation plan form a complete and internally consistent conformance-analysis baseline for the local specification digests associated with declared commit `$SOURCE_CONTEXT_COMMIT`; every applicable requirement is traceable to exactly one bounded future implementation work package; all gaps, conflicts, provenance limits, approvals, and external dependencies are explicit; pre-existing repository work is preserved; and no product implementation or out-of-scope mutation occurred.

## Output

* Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or `exhausted`.
* `succeeded`: Every acceptance criterion and verification requirement passes. This confirms completion of the conformance analysis only, not product implementation or full MHCS Core conformance.
* `failed`: The generated analysis is internally inconsistent, required verification fails, unsafe task-introduced repository changes remain, or an unrecoverable execution error prevents a trustworthy result.
* `blocked`: A required specification, instruction, repository capability, safe output path, or material evidence source is missing, unreadable, or cannot be established.
* `awaiting-approval`: Completion requires overwriting a different-baseline output, an approval-gated mutation, external access, credential use, or an explicit human decision that cannot be represented solely as an unresolved conflict or blocker.
* `exhausted`: The finite iteration limit is reached before all acceptance criteria and verification requirements pass.
* Report the selected runtime or model when verifiable.
* Report available capabilities.
* Report the terminal outcome.
* Report the generated file paths.
* Report the declared source commit.
* Report source-commit correspondence status.
* Report the analyzed local specification digests.
* Report the target-repository revision and branch when available.
* Report the total requirement count.
* Report counts by requirement prefix and implementation classification.
* Report the proposed work-package count.
* Report verification evidence.
* Report unresolved conflicts and external dependencies.
* Report any source-provenance limitation.
* Report residual risks and required manual follow-up.
* Keep runtime values, mutable progress, command output, execution results, secrets, and hidden reasoning outside this immutable task file.
* Do not modify this task file during execution.
