---
title: Operator DICOM Batch Download
document_id: MHCS-TASK-OPERATOR-DICOM-BATCH-DOWNLOAD-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-31
scope:
  - Operator DICOM-results worklist selection and batch download
  - protected multi-study DICOM archive retrieval
  - focused UI, security, archive-integrity, and regression verification
authority_note: This published task authorizes only the bounded implementation and local verification defined here. It does not authorize deployment, release, storage redesign, dependency changes, or product changes outside this objective.
---

# Executable Task

This file defines a bounded software-delivery contract for implementation.

## Task identity

**Task title:**  
`Operator DICOM Batch Download`

**Task path:**  
`.agents/tasks/operator-dicom-batch-download.md`

**Task contract state:**  
`Validated/Published upon immutable publication of this exact content.`

Execution and review lifecycle states remain separate from this immutable task revision. Material remediation must update this stable path and republish it as a new immutable governing revision before renewed execution.

**Delivery objective / Work Package / MVP:**  
`Bounded Operator DICOM-results worklist usability and protected batch export`

**Owner / designated planning authority:**  
`Planner/Reviewer under the human-approved task-authoring handoff dated 2026-08-31`

## Delivery context

The Operator DICOM results worklist currently displays an unnecessary Dimensions column and offers only individual study access/download. Operators need to select displayed studies and retrieve them together while retaining the existing protected viewer and single-study download behavior.

## Baseline and task revision

**Implementation baseline:**  
`700c7f59a9111cd6a006ebc5dc586ef2dafb1ec7`

**Task revision:**  
`The full SHA of the commit containing this exact task content, supplied by the Planner after publication.`

The task revision and implementation baseline are separate references. The baseline is immutable and must not be changed silently during execution.

## Objective

Update the Operator DICOM-results worklist and its protected server path so an authenticated Operator can select individual currently displayed studies, select or deselect all currently displayed studies, and invoke one Indonesian/localized batch action corresponding to `Unduh terpilih`. The operation must return one ZIP containing one unchanged `.dcm` file per selected authorized study, while preserving individual viewing and single-study download.

## Authoritative inputs

### Governing authority

- Human-approved task-authoring handoff dated 2026-08-31, including the approved product behavior, security rules, preserved behavior, and scope boundaries in this task.
- `docs/mvp/decision-log.md` — MVP-DEC-035 (authenticated Operator raw-DICOM attachment download), MVP-DEC-036 (active-site/current-shift Operator access to individually validated DICOM), and MVP-DEC-037 (Bahasa Indonesia browser UI and `lang/id.json` registry).
- `.agents/context/project.md` — approved MHCS modular architecture and private Image Gateway ownership.
- `.agents/context/modules/operator/project.md` — `Read-only image access` and Operator authorization rules.
- `.agents/context/modules/image-gateway/project.md` — `Access and distribution` and private-object boundary.
- `.agents/context/ui-language.md` — Indonesian UI, translation registry, accessibility, and button-label conventions.
- `.agents/software-workflow.md`, `.agents/AGENTS.md`, and repository task conventions.

### Requirement traceability

- `OPR-108` → Operator context `Security and audit requirements`: enforce Operator permission, active site, current shift, and examination scope on every operation.
- `IMG-060` → Image Gateway context and MVP-DEC-036: each validated/stored returned DICOM is available only to an authenticated Operator whose active site and current shift authorize that examination.
- `OPR-118` and `IMG-028` → Operator/Image Gateway ownership boundaries: Operator receives references and raw DICOM through protected access; raw NPZ and permanent Operator-owned DICOM storage remain unavailable.
- `MVP-DEC-035`, `MVP-DEC-036`, `MVP-DEC-037` → authenticated raw-DICOM download, current authorization scope, and Indonesian browser copy.

## Scope

### In scope

- Remove the Dimensions/Dimensi header and cell from the Operator DICOM results worklist, including any now-unneeded rows/columns projection or type declarations if safe and local.
- Add an accessible per-study selection control keyed by a study reference/identifier, without treating the browser value as authorization.
- Add an accessible select-all control covering exactly the studies currently rendered in the worklist, with correct select/deselect behavior and responsive existing worklist conventions.
- Add one localized Indonesian batch-download action using the repository translation registry and a repository-consistent equivalent of `Unduh terpilih`.
- Add a protected batch endpoint/controller/service path using existing authenticated Operator, active-site, and current-shift authorization boundaries.
- Revalidate every submitted study independently on the server before producing the archive. If any requested member is malformed, unavailable, foreign-site, foreign-shift, or otherwise unauthorized, fail the whole batch and do not export the authorized subset.
- Return one ZIP archive containing one safe `.dcm` entry for every selected authorized study, with deterministic human-usable names derived from existing study metadata/reference conventions. Preserve DICOM bytes exactly.
- Handle duplicate submitted identifiers deterministically without authorization bypass or inconsistent archive behavior; do not accidentally authorize duplicates.
- Add focused feature/integration and view/UI regression coverage for positive, empty, malformed, duplicate, unauthorized, unavailable, archive-name, byte-integrity, and preserved individual-access behavior as appropriate to the observed implementation surfaces.

### Out of scope

- DICOM viewer redesign, orientation/laterality changes, DICOM generation/conversion, PixelData transformation, MPIPS, NPZ upload/normalization, AI, Doctor, Member, or clinical workflow changes.
- Public URLs, object-store URLs, temporary bypass tokens, direct storage access, raw NPZ access, or weakening any protected access boundary.
- New ZIP/archive package, dependency installation, lockfile changes, new storage infrastructure, background jobs, queues, persistence/schema changes, export records, or migrations. If existing runtime capabilities cannot safely satisfy the objective, stop and return to planning.
- New product-level selection limits unless approved authority or a verified technical constraint requires one and Planner/Reviewer approves it.
- Deployment, production mutation, production validation, release, or modification of `.agents/tasks/production-hotfix-npz-dicom-postdeploy-validation.md`.

### Preserved behavior

- Existing Operator authentication, active-site/current-shift authorization, and current worklist eligibility semantics.
- Existing `operator.study.show`, `operator.study.dicom`, and `operator.study.download` routes and behavior, including individual `.dcm` attachment delivery.
- Existing human-readable `DCM-...` study reference presentation, read-only viewer, responsive/accessibility behavior, DICOM generation/conversion, complete file bytes, image storage/object identity, MPIPS, NPZ, canonical orientation, and unrelated Operator workflows.
- Image Gateway remains the sole durable owner of DICOM objects; the batch response must not create a second persistent study copy.

## Dependencies and assumptions

### Dependencies

- The implementation baseline remains materially consistent with the observed `study-results.blade.php`, `ImageGatewayController`, `ImageGatewayCaptureService`, protected routes, and existing protected DICOM tests.
- Existing PHP/Laravel runtime and repository mechanisms can construct and safely clean up a temporary or streamed archive without a new dependency.
- Existing study metadata contains sufficient approved reference/filename material for safe deterministic `.dcm` entry names.

### Approved assumptions

- Submitted study identifiers are untrusted references only. Authorization is determined exclusively by the server-side authenticated Operator context and existing authorized-study query/access boundary.
- A batch is atomic with respect to authorization: any invalid member fails the entire request; no authorized subset is returned.
- Duplicate identifiers may be rejected or normalized to one archive entry, but the choice must be deterministic, fail safely, and must not weaken authorization.
- A bounded temporary archive/file is acceptable only when already supported by the runtime and safely cleaned up on success and failure.

### Remaining approval requirements

- Any need for a dependency, schema/persistence change, infrastructure change, selection limit, changed authorization policy, new archive security policy, or material architecture decision returns to Planner/Reviewer.
- Git commit, push, pull request, deployment, production mutation, and release are not authorized for the implementation Executor.
- Implementation acceptance remains separate from release authorization.

## Required capabilities

- Repository read/write and local PHP/Laravel, feature/integration, frontend, and static verification.
- Codebase Memory MCP or equivalent repository intelligence when materially useful.
- Local archive inspection sufficient to verify ZIP entries and exact DICOM payload bytes.

## Execution constraints

- Reuse the existing authorization and private-object retrieval mechanism. Do not authorize browser-supplied IDs, storage keys, object URLs, or temporary tokens.
- Revalidate every selected study before archive construction and fail closed for empty, malformed, duplicate-inconsistent, unavailable, foreign-site, foreign-shift, or unauthorized input.
- Do not partially export a batch. Error responses must not expose storage keys, private paths, raw DICOM, credentials, or internal exception details.
- ZIP entry names must be basename-only, deterministic, safe against traversal/control characters, and free of unnecessary patient/private metadata. Resolve collisions deterministically without changing DICOM bytes.
- Do not mutate, re-encode, transform, or append data to DICOM payloads. Do not persist a second study copy.
- Use repository-consistent HTTP/error semantics and translation/accessibility patterns. Do not invent unrelated UX.
- Do not add a product-level selection cap or new archive dependency. A technical impossibility is a stop condition, not permission to broaden scope.

## Acceptance criteria

- [ ] The Dimensions/Dimensi worklist column is absent from rendered header, rows, and empty-state table structure.
- [ ] Each displayed study has an accessible selection control; selection is scoped to currently rendered studies.
- [ ] Select-all selects all currently displayed studies and toggles them off without selecting hidden/non-displayed studies.
- [ ] The localized batch action is present and invokes the protected batch operation; the empty selection does not return an archive.
- [ ] A valid selection returns exactly one ZIP archive with one `.dcm` entry for each selected authorized study.
- [ ] Each archive entry has a safe deterministic human-usable filename derived from approved existing study metadata/reference conventions; collisions are handled deterministically.
- [ ] Extracted archive bytes for every entry exactly equal the corresponding individually authorized DICOM bytes.
- [ ] The server independently authorizes every requested study using the authenticated Operator, active site, and current-shift boundary; browser identifiers confer no authority.
- [ ] Any invalid, malformed, unavailable, foreign-site, foreign-shift, unauthorized, or otherwise failed member causes the whole batch to fail without returning an authorized subset.
- [ ] Duplicate identifiers cannot bypass authorization or create inconsistent authorization behavior.
- [ ] Batch errors do not expose private paths, storage keys, raw DICOM, credentials, or sensitive internal exception details.
- [ ] Existing individual viewing, inline DICOM, and single-study attachment download remain available and behaviorally unchanged.
- [ ] No public URL, direct storage access, persistence/schema change, new dependency, new archive package, or unrelated workflow change is introduced.
- [ ] Indonesian/localized copy is registered through the existing translation registry, and selection controls/action preserve existing accessibility and responsive behavior.

## Verification requirements

### Required checks

- Focused feature/integration tests covering authorized multi-study ZIP creation, exact entry count/names/bytes, empty/malformed/duplicate input, unauthorized and mixed-authority all-or-nothing failure, and preserved individual routes.
- Focused rendered-worklist/UI tests covering removed Dimensions column, per-study controls, select-all current-display semantics, localized action, empty state, and accessibility-relevant labels.
- Existing relevant Operator/Image Gateway DICOM authorization/view/download regression tests.
- Repository-standard PHP test, frontend/build, formatting/static, and diff checks as applicable to the changed files; report exact commands and observed results.
- Direct archive inspection in tests or a local check proving safe entry names, no traversal entries, unchanged DICOM bytes, and cleanup of any temporary archive mechanism.

### Required evidence

The Executor MUST report:

- exact implementation revision or working-tree state and governing task revision;
- commands actually executed and observed results;
- positive ZIP contents and byte-integrity evidence;
- negative authorization/failure evidence, including mixed authorized/unauthorized atomic failure;
- UI/localization/accessibility regression evidence;
- any tests not run, known gaps, material deviations, or stop conditions;
- confirmation that no private DICOM, patient data, credentials, unauthorized dependency, schema change, or external side effect was introduced.

## Stop conditions

The Executor MUST stop and return the issue to planning when:

- the baseline no longer safely represents the worklist or authorization architecture;
- existing authorization cannot be reused with equivalent strength;
- safe ZIP construction requires a new package, dependency, storage infrastructure, queue/background export, persistence/schema change, or material architecture decision;
- a selection limit, duplicate policy, filename policy, error contract, or other product-level decision is required but not authorized;
- any path would expose public/direct storage access, bypass tokens, raw NPZ, private paths, or sensitive exception details;
- all-or-nothing authorization or exact DICOM byte preservation cannot be demonstrated;
- a required authority, dependency, or acceptance decision is missing or contradictory;
- execution would broaden scope into DICOM transformation, MPIPS/NPZ, viewer behavior, another module, deployment, production, or release.

## Side-effect authorization

### Explicitly authorized side effects

- Modify only the files genuinely necessary for this bounded worklist/batch-download behavior, its localized copy, focused tests, and minor local helpers.
- Execute local tests, archive checks, frontend/build, formatting/static, and diff verification commands.
- Create the requested feature branch and publish this task revision as authorized by the task-authoring handoff.

### Not authorized

- Product implementation outside this task; Git push, pull-request creation, deployment, production mutation, or release by the implementation Executor.
- Dependency installation/replacement, lockfile changes, schema migrations, persistent export records, new storage/queue infrastructure, public URLs, direct object-store access, or permission expansion.
- Secret access/copying/disclosure, private DICOM or patient-data fixtures, destructive data/infrastructure operations, or unrelated repository changes.

## Expected terminal outcome

`REVIEW REQUIRED` — return one reviewable implementation state with observed verification evidence for the UI, protected all-or-nothing authorization, ZIP safety, exact DICOM byte preservation, and preserved individual access behavior.
