---
name: mhcs-core-conformance-remediation
description: Remediate the existing MHCS Core conformance baseline by re-auditing all authoritative sources, correcting requirement extraction and lifecycle traceability, and regenerating the three planning documents without implementing product features.
version: 1
---

# Task: MHCS Core Conformance Remediation

## Objective

Remediate the draft conformance baseline in `$TARGET` that was introduced by `$DRAFT_BASELINE_COMMIT`.

Re-read the complete approved MHCS Core specification baseline associated with `$SOURCE_CONTEXT_COMMIT`, audit every existing requirement and source-coverage decision, and replace the three documents under `$OUTPUT_DIR` with a corrected, internally consistent conformance baseline.

The remediated baseline must:

- extract normative obligations from prose, tables, diagrams, examples marked as mandatory, acceptance criteria, and list items rather than relying on Markdown bullets alone;
- exclude links, headings, examples, descriptive rationale, prototype data, and fragments that are not independently implementable obligations;
- normalize every active requirement into a complete and observable obligation;
- account explicitly for every requirement identifier from the draft matrix;
- consolidate duplicate or overlapping requirements without silently losing history;
- distinguish active requirements from decisions, conflicts, dependencies, examples, verification evidence, and non-normative references;
- re-audit the `UIL-*` requirement family for inflation caused by examples, repetition, explanatory prose, and duplicated terminology rules;
- re-audit the `DES-*` family so prototype data and illustrative behavior are not treated as production requirements;
- correct applicability and implementation classifications using repository evidence;
- regenerate work-package boundaries, dependencies, counts, and the ordered critical path; and
- preserve the distinction between conformance analysis and product implementation.

This task performs documentation remediation and conformance analysis only.

It must not implement Laravel application features or claim full MHCS Core product conformance.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
- Ordered model preferences: None.
- Require preferred model: `false`

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.
- `DRAFT_BASELINE_COMMIT` (required): Full 40-character hexadecimal Git commit SHA containing the draft conformance outputs to remediate.
- `SOURCE_CONTEXT_COMMIT` (required): Full 40-character hexadecimal Git commit SHA declaring the `mhcs-business-docs` source revision for the local specification baseline.
- `SOURCE_REPOSITORY` (optional, default: Madeena-software/mhcs-business-docs): Repository identifier used only for optional read-only provenance verification when the runtime already provides access.
- `OUTPUT_DIR` (optional, default: docs/implementation): Safe repository-relative directory containing the three draft conformance documents.

## Context and evidence

Read and follow all applicable repository instructions before analysis, including:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`; and
- any additional instruction file whose scope includes `$OUTPUT_DIR`.

Read the published predecessor task as historical evidence:

- `$TARGET/.agents/tasks/mhcs-core-conformance-v1.md`.

Do not modify the predecessor task or this remediation task.

Treat the following local files as the mandatory approved specification baseline:

- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/context/modules/doctor/project.md`;
- `$TARGET/.agents/context/modules/image-gateway/project.md`;
- `$TARGET/.agents/context/ui-language.md`; and
- `$TARGET/.agents/context/design/mhcs-core-design.html`.

Treat these existing draft artifacts as required remediation inputs:

- `$OUTPUT_DIR/mhcs-core-requirements-matrix.md`;
- `$OUTPUT_DIR/mhcs-core-source-coverage.md`; and
- `$OUTPUT_DIR/mhcs-core-implementation-plan.md`.

Stop as `blocked` if any mandatory instruction, specification, predecessor task, or draft artifact is missing or unreadable.

Treat `$SOURCE_CONTEXT_COMMIT` as declared provenance unless direct read-only comparison with `$SOURCE_REPOSITORY` at that exact commit is available.

Use this source-path mapping when provenance comparison is possible:

- `.agents/context/project.md` to `docs/technical/mhcs-core/project.md`;
- `.agents/context/modules/member/project.md` to `docs/technical/mhcs-core/modules/member/project.md`;
- `.agents/context/modules/operator/project.md` to `docs/technical/mhcs-core/modules/operator/project.md`;
- `.agents/context/modules/doctor/project.md` to `docs/technical/mhcs-core/modules/doctor/project.md`;
- `.agents/context/modules/image-gateway/project.md` to `docs/technical/mhcs-core/modules/image-gateway/project.md`;
- `.agents/context/ui-language.md` to `docs/technical/mhcs-core/ui-language.md`; and
- `.agents/context/design/mhcs-core-design.html` to `docs/technical/mhcs-core/design/mhcs-core-design.html`.

Do not require external access when the runtime does not already provide it.

Record source-commit correspondence as:

- `verified` when all mapped source files at `$SOURCE_CONTEXT_COMMIT` are directly compared and match the local files;
- `unverified` when direct comparison is unavailable; or
- `mismatch` when at least one directly compared file differs.

Stop as `awaiting-approval` before modifying the draft artifacts when correspondence is `mismatch`.

The local files and their SHA-256 digests remain the actual analyzed baseline.

Inspect repository evidence rather than treating specification text or planning documents as proof of implementation.

Planning documents, task files, links, interfaces, mocks, stubs, prototypes, fixtures, and unexecuted tests do not establish product implementation.

## Scope and constraints

### Baseline preconditions

Validate all of the following before writing:

1. `$DRAFT_BASELINE_COMMIT` and `$SOURCE_CONTEXT_COMMIT` are full 40-character hexadecimal Git commit SHAs.
2. `$DRAFT_BASELINE_COMMIT` exists in the target repository.
3. The current `HEAD` contains `$DRAFT_BASELINE_COMMIT` as an ancestor.
4. The three draft artifacts exist at `$DRAFT_BASELINE_COMMIT`.
5. The three draft artifacts have not changed between `$DRAFT_BASELINE_COMMIT` and current `HEAD`.
6. The mandatory local specification files have not changed between `$DRAFT_BASELINE_COMMIT` and current `HEAD`.
7. The current specification digests match the digests recorded consistently in the draft artifacts.
8. The draft matrix contains the previously reported requirement families and identifier history needed for reconciliation.

Stop as `awaiting-approval` when the draft outputs or specification baseline changed after `$DRAFT_BASELINE_COMMIT`.

Stop as `blocked` when ancestry, commit objects, required files, or recorded digests cannot be established.

### Output-path safety

Before writing:

1. Resolve `$TARGET` to its canonical absolute path.
2. Reject `$OUTPUT_DIR` when it is absolute, empty, `.` alone, or contains a `..` path component.
3. Resolve `$OUTPUT_DIR` against the canonical target.
4. Confirm the resolved output directory remains strictly inside `$TARGET`.
5. Confirm it is not `$TARGET/.agents` and is not nested under `$TARGET/.agents`.
6. Confirm symbolic-link resolution cannot redirect an output outside `$TARGET`.
7. Stop as `blocked` when containment cannot be proven.

Writes are limited to replacing these three existing files:

- `$OUTPUT_DIR/mhcs-core-requirements-matrix.md`;
- `$OUTPUT_DIR/mhcs-core-source-coverage.md`; and
- `$OUTPUT_DIR/mhcs-core-implementation-plan.md`.

Do not create additional repository files.

Temporary analysis scripts or data may be created only outside `$TARGET` and must be removed before completion.

### Pre-existing repository changes

Before analysis:

- record the initial commit, branch, staged paths, modified paths, and untracked paths;
- preserve all pre-existing repository work;
- do not reset, discard, stage, commit, rewrite, or modify pre-existing changes; and
- stop as `awaiting-approval` when a pre-existing change overlaps any of the three permitted output files.

At completion:

- compare the final working tree with the initial snapshot;
- distinguish task-introduced changes from pre-existing changes; and
- confirm that task-introduced changes are limited to the three permitted output files.

### Full-source audit

Audit the complete contents of every mandatory specification.

Do not equate “normative” with “formatted as a bullet.”

Inspect all of the following:

- headings;
- paragraphs;
- tables;
- lists;
- code blocks and flow diagrams that define behavior;
- stated invariants;
- prohibitions;
- ownership declarations;
- defaults;
- exact values and limits;
- state transitions;
- error and retry behavior;
- acceptance criteria;
- security and privacy constraints;
- explicit examples introduced as mandatory or exact;
- cross-references whose target contains the actual obligation; and
- implementation-relevant design structures and interactions.

A prose section may be excluded only after sentence-level review establishes that it contains no independent obligation.

The generic rationale “prose-only section” is prohibited.

Every excluded heading or design item must have a section-specific rationale explaining why its content is descriptive, duplicated, referential, illustrative, superseded, or otherwise non-normative.

### Normative-requirement test

Treat a source statement as an active requirement only when it defines at least one independently verifiable obligation concerning:

- required behavior;
- prohibited behavior;
- data ownership;
- data structure;
- authorization;
- security;
- privacy;
- retention;
- transactionality;
- concurrency;
- idempotency;
- state transition;
- external boundary;
- user-visible language;
- visual or interaction behavior;
- exact terminology;
- required evidence; or
- another observable target-state constraint.

Do not create an active requirement from:

- a heading alone;
- a link alone;
- a source-file reference alone;
- a repository path alone;
- a standards citation alone;
- an example not marked as mandatory;
- sample member, patient, operator, doctor, NIK, clinical, payment, or imaging data;
- design-prototype simulation;
- explanatory rationale without an obligation;
- a statement that merely introduces a following list;
- an incomplete list fragment;
- a trailing conjunction;
- an acceptance criterion that only repeats an already extracted obligation;
- an open question or unresolved decision; or
- an external dependency name without a required system behavior.

Place open questions and unresolved decisions in the decision or conflict register, not in the active requirements matrix, unless the specification independently requires a concrete resolution action.

### Requirement normalization

Every active requirement must:

- identify the obligated subject or owning boundary;
- use a complete declarative obligation such as “must,” “must not,” “may only,” or another unambiguous target-state formulation;
- preserve material conditions, exceptions, limits, and exact values;
- be independently verifiable;
- avoid raw Markdown links as the normalized requirement;
- avoid copying formatting artifacts;
- avoid ending with `;`, `; and`, `; or`, `and`, or `or`;
- avoid depending on neighboring rows to complete its meaning;
- cite a stable source path and locator; and
- state a requirement-specific verification method rather than a generic placeholder.

A source statement containing multiple independently verifiable obligations must be split.

Statements that form one indivisible invariant must remain together.

### Existing-ID reconciliation

Account for every requirement ID present in the draft matrix.

For each previous ID, do exactly one of the following:

- retain it with the same material meaning;
- retain it and rewrite only for normalization without changing material meaning;
- merge it into another active ID;
- split it into multiple active IDs;
- supersede it with a corrected active ID;
- retire it as non-normative;
- retire it as a duplicate; or
- move its content to a conflict, decision, dependency, evidence, or design-reference register.

Do not silently delete, reuse, or renumber a previous identifier.

Create an **ID lifecycle ledger** in the remediated source-coverage document with these fields:

- previous ID;
- disposition;
- replacement or surviving ID;
- previous source locator;
- corrected source locator;
- rationale; and
- affected work package.

Allowed dispositions are:

- `retained`;
- `rewritten`;
- `merged-into`;
- `split-into`;
- `superseded-by`;
- `retired-non-normative`;
- `retired-duplicate`; or
- `moved-to-register`.

Rows that remain active without material or normalization changes may be summarized by contiguous ID ranges, provided every ID is still mechanically accountable.

For a split, preserve the previous ID for the first materially equivalent obligation when practical and issue new sequential IDs for additional obligations.

Never assign a retired ID to unrelated content.

### Duplicate and overlap handling

Detect duplication:

- within one source;
- between acceptance criteria and parent rules;
- between module specifications;
- between repository-wide architecture and module details;
- between UI-language examples and governing terminology rules; and
- between design samples and language policy.

Keep the requirement under the explicit owning authority when ownership is defined.

Cross-reference secondary sources in the source-coverage index rather than duplicating the active requirement.

When two sources impose distinct obligations, retain both.

When two sources conflict, preserve both in the conflict register and do not select a winner unless an explicit authority rule resolves the conflict.

### UI-language remediation

Perform a dedicated audit of every previous `UIL-*` row.

Consolidate repeated examples and explanatory prose into the governing requirement when they do not create distinct obligations.

Distinguish:

- policy scope;
- required primary language;
- approved service and object terminology;
- prohibited member/public terminology;
- exact public queue labels;
- capitalization rules;
- regulated or verbatim-text exceptions;
- clinical-risk and `radiasi pengion` wording;
- AI-versus-doctor separation;
- required-versus-recommended repeat wording;
- error, notification, onboarding, and help-content rules;
- privacy-safe copy;
- examples of compliant wording; and
- examples of prohibited wording.

Examples belong in notes or verification fixtures unless the policy explicitly makes the exact text mandatory.

Do not treat every example sentence, synonym, screen instance, table row, or repeated mention as a separate requirement.

Preserve exact requirements for:

- `Sesi Foto Radiografi`;
- `Foto Radiografi`;
- `Gambar Radiografi`;
- `Pemeriksaan Dasar`;
- `PEMERIKSAAN DASAR`;
- `SESI FOTO RADIOGRAFI`; and
- the prohibition on MHCS-authored member/public use of `X-ray`.

Do not rewrite signed doctor reports, regulated consent, legal text, clinical text, or required third-party verbatim content as though the UI-language policy authorized modification.

### Design remediation

Perform a dedicated audit of every previous `DES-*` row and every implementation-relevant item in the approved design export.

Distinguish:

- design-system rules;
- screen and surface structure;
- role-specific navigation;
- states;
- interaction patterns;
- responsive behavior;
- accessibility behavior;
- visual hierarchy;
- tokens;
- illustrative prototype content;
- simulated integrations; and
- sample data.

Do not treat prototype names, identifiers, NIK values, clinical text, radiograph simulations, `alert()` calls, `window.print()`, connection labels, sample statuses, or mock external integrations as proof or mandatory production behavior unless another authoritative specification explicitly requires them.

Where design copy conflicts with the UI-language policy, record the conflict and apply the explicit authority relationship without duplicating the conflicting prototype string as an active requirement.

### Applicability and implementation classification

Use only these applicability values:

- `applicable`;
- `not-applicable`; or
- `ambiguous`.

Use only these implementation classifications:

- `not-started`;
- `in-progress`;
- `implemented-unverified`;
- `verified`;
- `blocked`; or
- `not-applicable`.

Use `ambiguous` when applicability cannot be determined without an unresolved product, architecture, clinical, legal, financial, privacy, security, or interoperability decision.

Use `blocked` only when an applicable requirement cannot currently proceed or be verified because a concrete prerequisite, approval, credential, environment, external contract, or authority decision is unavailable.

Do not classify every external-facing future requirement as `blocked` merely because implementation has not begun.

Use `not-started` when no meaningful product implementation exists and no current prerequisite prevents beginning the bounded work.

Use `verified` only with direct product evidence and observed requirement-specific verification output.

The three planning documents, specifications, tasks, and this remediation work are not product implementation evidence.

### Remediated requirements matrix

Replace:

`$OUTPUT_DIR/mhcs-core-requirements-matrix.md`

The active matrix must contain one independently verifiable requirement per row and these fields:

- Requirement ID;
- source path;
- stable source locator;
- normative basis;
- normalized requirement;
- owning module or cross-cutting authority;
- applicability;
- implementation classification;
- repository evidence;
- requirement-specific verification;
- dependencies or blockers;
- primary work package; and
- notes.

The matrix may use multiple tables by prefix.

Every active row must use the same schema.

Do not retain retired IDs as active matrix rows.

### Remediated source-coverage index

Replace:

`$OUTPUT_DIR/mhcs-core-source-coverage.md`

The source-coverage index must contain:

- baseline metadata;
- specification digests;
- source-commit correspondence;
- a complete heading and statement coverage ledger for every Markdown specification;
- a complete implementation-relevant design coverage ledger;
- active requirement mappings;
- section-specific non-normative rationales;
- duplicate-source cross-references;
- conflict mappings;
- decision mappings;
- external-dependency mappings;
- the complete ID lifecycle ledger;
- a work-package lifecycle ledger when package IDs or boundaries change; and
- exact reconciliation totals.

No substantive heading may remain merely as an unspecified manual audit item.

A remaining human decision must be identified as a named decision with affected requirements and work packages.

### Remediated implementation plan

Replace:

`$OUTPUT_DIR/mhcs-core-implementation-plan.md`

The implementation plan must contain:

- baseline metadata;
- specification digests;
- source-commit correspondence;
- previous and remediated requirement totals;
- counts by prefix;
- counts by source;
- counts by authority;
- counts by applicability;
- counts by implementation classification;
- counts by ID-lifecycle disposition;
- conflicts;
- unresolved decisions;
- external dependencies;
- approval requirements;
- risks;
- ordered critical path;
- bounded work packages;
- requirement assignments;
- prerequisites;
- affected modules and interfaces;
- expected repository changes;
- excluded scope;
- verification methods;
- completion evidence;
- suggested versioned implementation-task filenames; and
- a final conformance-audit package.

Do not force the remediated plan to preserve the previous requirement count or exactly 28 work packages.

Preserve a previous work-package ID when its material scope remains stable.

Record package merges, splits, retirement, or scope changes in the work-package lifecycle ledger.

Each active applicable requirement must have exactly one primary work package.

Requirements with applicability `ambiguous` must identify the decision package or approval gate that resolves them.

### Out of scope

The following are outside this task:

- Laravel or Filament installation;
- application-source implementation;
- migration changes;
- test implementation;
- dependency installation or updates;
- configuration changes;
- CI changes;
- infrastructure changes;
- deployment changes;
- changes under `$TARGET/.agents/`;
- authoring implementation tasks;
- changing approved context;
- resolving material product decisions without approval;
- production or staging access;
- credentials;
- real external-system calls;
- payment or payout operations;
- direct SSH;
- commits;
- pushes;
- pull requests;
- time estimates; and
- delivery-date commitments.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `12`
- Approval gates: Any context change, task-file change, application or framework change, write outside the three permitted output files, baseline mismatch, source-commit mismatch, silent resolution of a material conflict, or decision affecting product, architecture, clinical behavior, legal obligations, finance, privacy, security, or interoperability requires explicit user approval. Stop as `awaiting-approval` instead of performing the gated action.

## Execution procedure

1. Resolve `$TARGET`, `$DRAFT_BASELINE_COMMIT`, `$SOURCE_CONTEXT_COMMIT`, `$SOURCE_REPOSITORY`, and `$OUTPUT_DIR`.
2. Verify required runtime capabilities.
3. Validate the two commit inputs as full 40-character hexadecimal SHAs.
4. Resolve the canonical target and validate output-path containment.
5. Read all applicable repository instructions and the predecessor task.
6. Validate draft-baseline ancestry and required file existence.
7. Compare current specifications and draft outputs with `$DRAFT_BASELINE_COMMIT`.
8. Record the initial working-tree snapshot and stop for overlapping changes.
9. Calculate current SHA-256 digests for all seven mandatory specifications.
10. Reconcile those digests with all three draft artifacts.
11. Attempt read-only source correspondence verification against `$SOURCE_REPOSITORY` at `$SOURCE_CONTEXT_COMMIT` only when access already exists.
12. Stop for approval on a source mismatch.
13. Parse the complete draft matrix and inventory every previous requirement ID, source locator, classification, and work-package assignment.
14. Parse the draft source-coverage index and implementation plan.
15. Build a complete source statement ledger for all mandatory Markdown files.
16. Build a complete implementation-relevant design-item ledger.
17. Audit normative content sentence by sentence, including prose, tables, diagrams, and acceptance criteria.
18. Apply the normative-requirement test.
19. Normalize each active requirement into a complete independently verifiable obligation.
20. Detect and reconcile duplicate, overlapping, referential, fragmented, and non-normative draft rows.
21. Perform the dedicated `UIL-*` remediation.
22. Perform the dedicated `DES-*` remediation.
23. Build the complete ID lifecycle ledger covering every previous ID.
24. Re-evaluate applicability and implementation classifications against repository evidence.
25. Build named conflict, decision, dependency, approval, and risk registers.
26. Regenerate work-package assignments from the corrected active matrix.
27. Preserve stable package IDs where material scope remains unchanged.
28. Record all work-package lifecycle changes.
29. Rewrite the requirements matrix.
30. Rewrite the source-coverage index.
31. Rewrite the implementation plan.
32. Run mechanical consistency checks using temporary scripts outside `$TARGET`.
33. Verify unique IDs, allowed statuses, complete schemas, valid source locators, and exactly one primary package for each active applicable requirement.
34. Verify every previous ID is active or appears in the lifecycle ledger.
35. Verify every source heading and design item is mapped or has a specific rationale.
36. Verify all counts reconcile across the three outputs.
37. Verify every work-package reference resolves.
38. Verify no normalized requirement is a raw link, heading, fragment, trailing conjunction, example-only statement, or generic verification placeholder.
39. Inspect the final repository diff.
40. Confirm task-introduced changes are limited to the three permitted output files.
41. Remove temporary analysis artifacts outside the repository.
42. Re-read this unchanged task file.
43. Stop when every acceptance criterion passes, approval is required, execution is blocked, execution fails, or the iteration limit is exhausted.

## Acceptance criteria

- [ ] Both commit inputs are valid full Git SHAs and `$DRAFT_BASELINE_COMMIT` is an ancestor of current `HEAD`.
- [ ] The three draft artifacts and seven mandatory specifications match the expected draft baseline.
- [ ] Output-path containment is proven and no output resolves under `$TARGET/.agents`.
- [ ] Initial staged, modified, and untracked paths are recorded and preserved.
- [ ] Source-commit correspondence is accurately recorded as `verified`, `unverified`, or `mismatch`.
- [ ] A source mismatch is not silently accepted.
- [ ] Every mandatory Markdown specification is audited beyond list-item extraction.
- [ ] Every substantive heading has statement-level requirement mappings or a section-specific non-normative rationale.
- [ ] No heading remains only as an unspecified manual audit item.
- [ ] Every implementation-relevant design item is mapped or specifically excluded.
- [ ] Every active matrix row represents an independently verifiable normative obligation.
- [ ] Every active requirement identifies its obligated subject or owning boundary.
- [ ] Every active requirement preserves material conditions, exceptions, limits, and exact values.
- [ ] No active requirement is only a link, heading, path, citation, example, prototype datum, introduction, incomplete fragment, or trailing conjunction.
- [ ] Every active requirement has a stable source locator and requirement-specific verification method.
- [ ] Acceptance criteria that only repeat a parent obligation are treated as verification evidence or cross-references rather than duplicate active requirements.
- [ ] Open questions and unresolved decisions are represented in named registers rather than misclassified as product requirements.
- [ ] Every previous requirement ID is accounted for as active or through an allowed lifecycle disposition.
- [ ] No retired ID is reused for unrelated content.
- [ ] Every merge, split, supersession, retirement, or register move has an explicit rationale and replacement mapping.
- [ ] Duplicate obligations are consolidated under the explicit owning authority.
- [ ] Distinct obligations from different sources remain independently traceable.
- [ ] Conflicting obligations are recorded without silent resolution.
- [ ] All previous `UIL-*` rows are audited and repeated examples or explanatory prose are consolidated.
- [ ] Exact MHCS terminology and public queue labels remain explicitly required.
- [ ] UI-language exceptions for signed, regulated, clinical, legal, and third-party verbatim content remain explicit.
- [ ] All previous `DES-*` rows and design items are audited.
- [ ] Prototype data, simulations, sample integrations, and illustrative scripts are not treated as production requirements.
- [ ] Applicability uses only `applicable`, `not-applicable`, or `ambiguous`.
- [ ] Implementation classification uses only `not-started`, `in-progress`, `implemented-unverified`, `verified`, `blocked`, or `not-applicable`.
- [ ] `blocked` and `ambiguous` entries identify the concrete dependency or decision.
- [ ] Planning artifacts are not treated as product implementation evidence.
- [ ] Every active applicable requirement has exactly one primary work package.
- [ ] Every work package has bounded scope, prerequisites, exclusions, affected interfaces, verification, completion evidence, and a suggested versioned task filename.
- [ ] Work-package lifecycle changes are explicitly recorded.
- [ ] The final audit package cannot succeed while any applicable requirement remains unverified or any unresolved ambiguity affects conformance.
- [ ] Previous and remediated counts reconcile with the ID lifecycle ledger.
- [ ] Prefix, source, authority, applicability, classification, and work-package counts reconcile across all three files.
- [ ] Every requirement, lifecycle, conflict, decision, dependency, and package reference resolves.
- [ ] All three outputs state that remediation does not establish full product conformance.
- [ ] Task-introduced repository changes are limited to the three permitted Markdown files.
- [ ] No application, framework, context, task, dependency, configuration, migration, test, CI, infrastructure, deployment, cache, snapshot, or generated artifact change remains.

## Verification

- Method: Validate baseline ancestry, file digests, and output-path safety; parse the draft and remediated artifacts with temporary scripts; reconcile every previous and active identifier, source statement, design item, lifecycle disposition, status, count, conflict, decision, dependency, and work package; inspect requirement text for fragments and non-normative rows; and compare initial and final Git state to confirm that only the three permitted output files changed.
- Expected result: The three remediated documents form a complete, source-grounded, internally consistent conformance baseline in which normative prose is captured, non-requirements and duplicates are removed with explicit lifecycle history, every active requirement is atomic and verifiable, work packages are regenerated from corrected evidence, and no product implementation or out-of-scope repository mutation occurred.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or `exhausted`.
- `succeeded`: Every acceptance criterion and verification requirement passes. This confirms completion of conformance-baseline remediation only.
- `failed`: The remediation is internally inconsistent, required mechanical verification fails, or unsafe task-introduced changes remain.
- `blocked`: A required instruction, specification, draft artifact, commit object, capability, safe path, or material evidence source is missing or unreadable.
- `awaiting-approval`: Completion requires accepting a changed baseline, source mismatch, approval-gated mutation, or material decision.
- `exhausted`: The finite iteration limit is reached before every acceptance criterion and verification requirement passes.
- Report the selected runtime or model when verifiable.
- Report available capabilities.
- Report the terminal outcome.
- Report current target commit and branch.
- Report `$DRAFT_BASELINE_COMMIT`.
- Report `$SOURCE_CONTEXT_COMMIT`.
- Report source-commit correspondence for `$SOURCE_REPOSITORY`.
- Report the three modified file paths.
- Report previous and remediated requirement totals.
- Report counts by prefix, applicability, classification, and ID-lifecycle disposition.
- Report previous and remediated work-package totals.
- Report unresolved conflicts, decisions, dependencies, approvals, and risks.
- Report mechanical verification evidence.
- Report `git status --short` and `git diff --name-only`.
- Confirm whether any task-introduced file exists outside `$OUTPUT_DIR`.
- Report residual risks and manual follow-up.
- Keep runtime values, progress, command output, execution results, secrets, private prompts, and hidden reasoning outside this immutable task file.
- Do not modify this task file during execution.
