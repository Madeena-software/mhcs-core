---
title: Lean Documentation and Business Authority Transition
document_id: MHCS-TASK-LEAN-DOCS-BUSINESS-AUTHORITY-001
version: 0.2
status: Draft
language: en-US
last_updated: 2026-10-12
scope:
  - audit and simplify active repository documentation
  - transition maintained business reference to the designated Google Doc
authority_note: Draft planning contract only. The Google Doc remains Stakeholder Discussion Draft v0.2; its BP-*, D-*, BR-*, and EX-* content is not approved by this task.
---

# Executable Task

## Task identity

**Task title:** Lean Documentation and Business Authority Transition

**Task path:** `.agents/tasks/lean-docs-and-business-authority.md`

**Task contract state:** Draft; not authorized for execution until independently reviewed and published at an immutable revision.

**Delivery objective / Work Package / MVP:** One bounded documentation-only cleanup and authority-reference transition.

**Owner / designated planning authority:** Human MHCS Product Owner and repository Planner/Reviewer. Business, clinical, privacy, security, technical, and operational authorities retain their existing decision rights.

## Delivery context

The maintained business working reference is [MHCS Business Working Reference](https://docs.google.com/document/d/1zxmPjB-gevtVuaoTEmAtLjpDWlJf7USnkeigVbPJw0E/edit), currently Stakeholder Discussion Draft v0.2. `Madeena-software/mhcs-business-docs` is no longer maintained as active business authority. The local `.agents/context/business/` package and 62 tracked paths under `docs/` contain a mix of active guidance, technical/clinical authority, operational evidence, prototypes, historical plans, and duplicated records. The objective is a substantially smaller, consumer-verified active tree with historical retrieval pinned to Git.

This task does not approve draft business propositions or retire binding technical, clinical, privacy, security, or operational requirements. It does not rewrite historical validated task contracts. Historical document retrieval should use the exact baseline revision identified below rather than a new archive copy.

## Baseline and task revision

**Implementation baseline:** `8bb0c21e87e6f9e2c32a01ccab83aed37f1e91b7` (`origin/main` at task authoring).

**Task revision:** Resolve only after independent validation and publication. This Draft is not a T5-ready Executor contract.

The Admin-first documentation implementation `7132e7001b704789f6d122bcfad6a5b4e040bc0b` was independently accepted and merged through PR #5 as `8bb0c21e87e6f9e2c32a01ccab83aed37f1e91b7`. Treat that accepted merge as the baseline; do not repeat a full review of the prior implementation. Reconcile only material changes or new evidence since that accepted baseline. Preserve `.agents/tasks/admin-first-documentation-rebaseline.md` and all other historical task contracts unchanged.

**Historical Git reference for current documentation:** `Madeena-software/mhcs-core @ 8bb0c21e87e6f9e2c32a01ccab83aed37f1e91b7`. For every approved deletion, verify that this exact object contains the file and record the path plus full SHA in the new historical index. Do not use a moving branch/tag as the historical locator.

## Objective

Audit all tracked content under `docs/` and the active inbound references to `.agents/context/business/`; then reduce active documentation only by deleting from the exact candidate list below. All other tracked files are KEEP under this task. Any additional deletion requires a new task revision that is independently reviewed and approved before execution. Establish one concise Admin-first entrypoint that brings together active scope, roadmap, status, dependencies, and open decisions. Point business-source references to the designated Google Doc with its draft status and approval boundary explicit.

The preferred active documentation size is approximately 12–15 files, but this is a preference. Necessary authority and evidence take precedence.

## Authoritative inputs

### Governing authority

- Human handoff dated 2026-10-12: this objective, the Google Doc URL and draft status, permission to delete obsolete/duplicated/historical repository documentation, prohibition on automatic approval of BP-*, D-*, BR-*, and EX-* content, and prohibition on rewriting old validated task contracts.
- Repository contract and protocol: `.agents/AGENTS.md`, `.agents/software-workflow.md`, `.agents/prompts/plan-create-task.md`, and `.agents/tasks/_template.md`.
- Current Admin-first planning and recorded decisions at the implementation baseline, including `docs/mvp/README.md`, `docs/mvp/decision-log.md`, `.agents/context/project.md`, and the exact governing history of `.agents/tasks/admin-first-documentation-rebaseline.md`.
- Current local technical and operational authority, including MPIPS/DICOM contracts, stable identifiers, clinical/privacy/security decisions, and any operational evidence shown by the audit to remain required.

The supplied Google Doc is a discussion draft, not approved authority. Its contents may be linked as a working reference but MUST NOT be converted into approved requirements, decisions, or executable authority. Any future approved adoption claim based on that Doc requires an exact source revision or a verified immutable snapshot with provenance. If neither is available, retain its Draft status and explicitly state the provenance limitation; do not claim its contents were approved or adopted. The former business repository and local source copies are provenance/history only after reference repair.

### Requirement traceability

- Documentation provenance and approval state → human handoff and the immutable Git references in the historical index.
- Admin-first objective and still-open decisions → existing repository decisions and human direction; do not invent behavior or resolve open clinical/access/privacy questions.
- MPIPS/DICOM compatibility and stable requirement identifiers → existing local technical authority and source evidence.

## Scope

### In scope

- Inventory every tracked file under `docs/` and classify its status, unique information, actual consumers, active-baseline need, proposed disposition, and exact historical Git reference.
- Inspect references from application/source, build/configuration, tests, active and historical tasks, deployment/operations material, and other documentation. Use graph/index output only to guide discovery; verify findings against repository sources.
- Consolidate active Admin-first scope, roadmap, status, dependencies, and open decisions in `docs/mvp/README.md` as the concise entrypoint; remove separate active planning documents only after content and inbound links are reconciled.
- Repair active references to `.agents/context/business/`, preserve provenance, then remove `.agents/context/business/` if no active consumer or unique required content remains.
- Create `docs/history-index.md` as a small historical index linking removed material to `8bb0c21e87e6f9e2c32a01ccab83aed37f1e91b7` and exact paths. Do not create `docs/archive/` copies.
- Delete only the 39 exact `docs/` paths and three exact `.agents/context/business/` paths enumerated below, and only when all acceptance gates pass. All other tracked files are KEEP. If a candidate contains binding requirements, identifiers, approval records, or required operational evidence that cannot be preserved in active, authoritative and traceable form, retain that candidate and return the blocked deletion to review. The 12–15 target does not authorize expanding the list.

### Known reference repair targets at baseline

- `.agents/context/project.md`: replace its active business-authority pointer and links to the local business copies; update the authority map and related open-decision links without changing the meaning or approval state of those decisions.
- `docs/mvp/README.md`: replace its pinned former-repository authority wording and links to `.agents/context/business/` with the supplied Google Doc URL and explicit Draft v0.2 status.
- Search the whole repository for additional active inbound references before removal. Known historical task references in `.agents/tasks/admin-first-documentation-rebaseline.md`, `.agents/tasks/ai-pacs-integration.md`, `.agents/tasks/operator-ai-pacs-integration.md`, and `.agents/tasks/urgent-operator-field-operations.md` are not to be rewritten; preserve their historical meaning and resolve them through their immutable task/source Git revisions. Classify all other hits by current consumer and lifecycle before deciding whether repair is required.

### Proposed deletion candidates

These are the complete deletion candidate list, not automatic deletion instructions. There are 39 exact `docs/` paths and three exact local business-copy paths. For each path, the Executor must record historical/authority status, unique information, all current consumers, active-baseline need, replacement or blocker, and `path @ 8bb0c21e87e6f9e2c32a01ccab83aed37f1e91b7`. No other tracked path may be deleted under this task.

| Exact candidate paths | Preliminary status and rationale | Required replacement or blocker |
|---|---|---|
| `.agents/context/business/01-business-overview.md`; `.agents/context/business/02-user-stories.md`; `.agents/context/business/03-system-responsibilities.md` | The three local copies of the former business repository's source package; not active business authority after this transition. | First record the former repository and immutable source revision as historical provenance and link the Google Doc as the current working draft, clearly stating they are not asserted equivalent. Repair active consumers before removal. These are the only `.agents/context/business/` files authorized as deletion candidates; any other path is KEEP. |
| `docs/implementation/mhcs-core-implementation-plan.md`; `docs/implementation/mhcs-core-requirements-matrix.md`; `docs/implementation/mhcs-core-source-coverage.md` | Large historical planning/coverage snapshots; potentially contain unique work-package history, stable IDs, source digests, and unresolved provenance. | Remove only if active dependencies and identifier lookups are represented by a concise retained index and historical Git links; otherwise retain the minimum binding reference material. Never silently retire identifiers or obligations. |
| `docs/operator/reference/claude-design/admin-app.jsx`; `admin-dashboard.jsx`; `admin-data.js`; `admin-screens.jsx`; `admin-shell.jsx`; `admin-team-exports.jsx`; `admin.css`; `admin.html`; `app.jsx`; `data.js`; `front-desk.jsx`; `graphify-out/cache/stat-index.json`; `index.html`; `photo-booth.jsx`; `shared.jsx`; `style.css`; `tweaks-panel.jsx` | Historical design/prototype bundle and derived cache, not presumed runtime authority. | Verify no source/build/test/deployment consumer; historical task references remain unchanged and resolve through their original Git history. Remove the cache with the bundle if confirmed derived and unused. |
| `docs/superpowers/specs/2026-08-09-mvp-workstream-planner-split-design.md` | Historical design artifact; candidate for immutable-history-only retrieval. | Verify no active protocol or delivery dependency; keep required decisions in approved current sources, not by promoting this design. |
| `docs/mvp/beta-gap-register.md`; `docs/mvp/beta-scope.md`; `docs/mvp/roadmap.md`; `docs/mvp/work-package-status.md` | Redundant/separate planning documents proposed for consolidation into `docs/mvp/README.md`; some contain still-current gaps and dated evidence. | Consolidate every still-active scope, status, dependency, risk, and open decision with clear status/date; preserve stable identifiers and evidence links. Retain any file whose information cannot be represented accurately in the concise entrypoint. |
| `docs/mvp/evidence/mvp-01-member-access-and-profile.md`; `mvp-02-shared-admin-shell-member-administration.md`; `mvp-03-controlled-b2c-radiology-booking.md`; `mvp-04-operator-foundation-arrival.md`; `mvp-04b-front-desk-identity-verification.md`; `mvp-04c-paper-consent-confirmation.md`; `mvp-04d-verified-check-in-ticket-issue.md`; `mvp-04e-advance-queue-admission.md`; `mvp-04f-atomic-basic-examination-claim.md`; `mvp-04g-private-basic-examination-call.md`; `mvp-04h-private-basic-examination-start.md`; `mvp-operator-workstation-ui.md`; `mvp-operator-capture-formdata-snapshot-remediation.md`; `mvp-operator-async-capture-status-and-worklist-sync.md` (all under `docs/mvp/evidence/`) | Dated historical execution/review records; proposed for history-only retrieval where they are not required active operational evidence. | Inspect individually. Retain or distill active clinical/privacy/security/operational controls and current unresolved review outcomes. Keep evidence required by a live task or operation. Do not rewrite historical records as current proof. |

Candidate paths not listed in this table are KEEP and are not authorized for deletion by this task. The full tracked-file inventory must include and explicitly classify every remaining `docs/` file, including both MPIPS example manifests, import guidance/data, local walkthrough, printer protocol, Member/Security evidence, and the remaining clinical, privacy, deployment, rehearsal, and integration evidence.

### Explicitly retained unless a later reviewed task proves otherwise

- `.agents/AGENTS.md`, `.agents/software-workflow.md`, prompts, templates, task contracts, and historical task files; this task must not rewrite old validated task contracts.
- `README.md`, `.agents/context/project.md`, `docs/mvp/README.md`, and `docs/mvp/decision-log.md`, updated only as needed for this objective.
- `docs/mpips/mhcs-dicom-api.md`, `docs/mpips/mhcs-grabber-dicom-ingestion-contract.md`, `docs/mpips/examples/mhcs-dicom-manifest.example.json`, and `docs/mpips/examples/mhcs-dicom-manifest.minimal.example.json`.
- `docs/operator/physical-printer-validation-protocol.md`, `docs/member/wp-04-identity-evidence.md`, and `docs/security/wp-02-security-evidence.md` unless the audit establishes a safe replacement preserving all active obligations/evidence.
- All tracked files not included in the exact 42-path candidate list are KEEP under this task, including operational/clinical/privacy/security evidence required by a live task, current deployment procedure, open review, or current safety control.

The following KEEP documentation is present at baseline and must be explicitly classified for references and active need, but is not a deletion set: `docs/mvp/import-data/.gitignore`; `docs/mvp/import-data/README.md`; `docs/mvp/local-core-walkthrough.md`; `docs/mvp/evidence/mpips-aws-image-gateway-integration.md`; `docs/mvp/evidence/mvp-04i-basic-examination-clinical-approval.md`; `docs/mvp/evidence/mvp-04i-clinical-approval-evidence-remediation.md`; `docs/mvp/evidence/mvp-04i-clinical-privacy-decision-closure.md`; `docs/mvp/evidence/mvp-04j-bmi-positive-input-remediation.md`; `docs/mvp/evidence/mvp-04j-private-vital-signs-capture.md`; `docs/mvp/evidence/mvp-04k-through-n-mysql-review.md`; `docs/mvp/evidence/mvp-local-deployment-readiness.md`; `docs/mvp/evidence/mvp-local-mpips-operator-rehearsal.md`; `docs/mvp/evidence/prestige-production-rehearsal-data.md`; and `docs/mvp/evidence/production-swarm-deployment.md`. The four planning-source candidates remain until their replacement entrypoint is complete and verified. No KEEP path may be deleted under this task.

### Out of scope

- Application/source-code behavior, tests, migrations, configuration, build behavior, or runtime changes.
- Editing Google Docs, the former business repository, MPIPS, or any external system.
- Approving, rejecting, or rewriting draft BP-*, D-*, BR-*, or EX-* content.
- Rewriting, deleting, or changing the historical meaning of existing validated task contracts.
- Retiring or weakening clinical, privacy, security, safety, integration, identifier, or operational requirements.
- Creating a general documentation archive or copying all deletions elsewhere.
- PR creation, merge, deployment, release, or production-data operation.

### Preserved behavior and authority

- Draft business content remains explicitly unapproved and cannot authorize implementation.
- Clinical, privacy, security, operational, and technical obligations remain binding until changed by their designated authority.
- MPIPS/DICOM contract meaning, example compatibility, stable identifiers, and historical task traceability remain intact.
- Historical evidence remains distinguishable from current implementation and current operational status.

## Dependencies and assumptions

### Dependencies

- Independent review and resolution of the prior Admin-first documentation rebaseline's acceptance/review state before this task is validated.
- A full tracked-doc inventory and reference audit at the exact implementation baseline; candidate decisions must be revisited if the baseline changes.
- Read-only access to source/build/test/deployment/task/docs consumers. No Google Doc access is required to preserve its supplied URL and status.

### Approved assumptions

- The supplied Google Doc is the maintained business working reference and remains Stakeholder Discussion Draft v0.2.
- The former business repository is no longer maintained as active business authority.
- Human permission to delete obsolete, duplicate, and historical repository documentation covers the candidate removals only after this task is independently validated.

### Remaining approval requirements

- Independent Planner/Reviewer validation and publication are required before execution.
- Any newly discovered decision to change active clinical, privacy, security, technical, or business authority must return to its designated authority; this task cannot approve it.

## Required capabilities

- Repository read/write and Git history inspection.
- `rg`/Git reference search and documentation-oriented Graphify discovery where useful.
- No external Google Docs write access, application test environment, or production access.

## Execution constraints

- Classify all tracked `docs/` files, not only the named candidates. Include file status, unique content, actual consumers by category and location, active need, decision, and exact immutable history reference.
- Check literals/links and generated/configured references directly; a graph result is not proof of no consumer.
- Repair active links before deleting their targets. Leave old validated task files byte-for-byte unchanged; their references are historical and must remain retrievable by their original Git revisions.
- Preserve source provenance for removed local business copies, including the former repository identity and immutable historical source revision if recorded in this baseline. Do not claim the Google Doc content is equivalent to or approved in place of those sources.
- When a required source revision is not independently available, state the limit and retain sufficient provenance; do not invent a digest or equivalence claim.
- Record every deletion as exact path plus immutable SHA in the small historical index. Do not use a moving branch name as the only locator.
- Keep the new active entrypoint concise and distinguish approved direction, observed status, planned work, and unresolved decisions.

## Acceptance criteria

- [ ] Every tracked file under `docs/` has a completed disposition record with the required status, uniqueness, consumer, active-need, replacement/blocker, and exact historical Git reference fields.
- [ ] The disposition record confirms all 62 baseline tracked paths and distinguishes the 39 explicitly named `docs/` candidates, the 23 other baseline KEEP paths, and the three local business-copy candidates. If Git reports a different count at execution, reconcile the inventory; a changed inventory does not expand deletion authority.
- [ ] Every deletion is one of the exact authorized candidate paths listed above and individually justified. All other tracked files are KEEP; deleting any requires a new independently reviewed and approved task revision.
- [ ] `docs/mvp/README.md` is the concise active Admin-first entrypoint and contains the current scope, roadmap, status, dependencies, and unresolved decisions without presenting planned work as implemented.
- [ ] Active links from README, `.agents/context/project.md`, and maintained docs point to current retained sources or the Google Doc, and resolve successfully.
- [ ] `.agents/context/business/` is removed only after its active inbound references are repaired and its source provenance is recorded in active form; if a blocking consumer or missing provenance remains, retain it and return the blocker.
- [ ] `docs/history-index.md` identifies each removed path and links it to the full immutable revision `8bb0c21e87e6f9e2c32a01ccab83aed37f1e91b7`.
- [ ] Google Doc references clearly state “Stakeholder Discussion Draft v0.2”; no BP-*, D-*, BR-*, or EX-* content is marked approved or used as executable authority.
- [ ] Required MPIPS/DICOM contracts and examples, stable identifiers, clinical/privacy/security decisions, approval records, operational evidence, and historical task traceability remain available and semantically intact. Removing a file must not retire any obligation: preserve still-binding requirements and identifiers in active authoritative form, with clear owners/status and traceability to source, implementation, and verification. Immutable Git retrieval may support historical provenance, but cannot be the sole location for a still-binding requirement or an active traceability/approval record.
- [ ] Any future approved adoption claim based on the Google Doc cites an exact source revision or verified immutable snapshot and its provenance; otherwise the Doc remains labeled Draft with the provenance limitation explicit.
- [ ] Existing validated task contracts are unchanged; any unresolved historical reference is served through immutable Git history rather than rewriting the contract.
- [ ] The final active documentation inventory and count are reported. The 12–15-file preference is met when possible without dropping required evidence or authority and does not authorize deleting any KEEP path.

## Verification requirements

### Required checks

- Compare tracked-path inventories before and after and list every deletion, addition, and edit.
- Search all repository text for each removed path, `.agents/context/business/`, the former repository name, and the Google Doc reference; inspect every remaining hit and classify it as active, historical, or intentionally retained.
- Verify all local Markdown links in changed active documents and `docs/history-index.md` resolve; verify historical paths with `git cat-file -e 8bb0c21e87e6f9e2c32a01ccab83aed37f1e91b7:<path>`.
- Verify protected contracts, examples, historical task contracts, and retained evidence are unchanged except where this task explicitly permits a concise active index/reference update; show checksums or Git diff evidence.
- No application tests are required because application code is out of scope. Any discovered source/build/test dependency is a stop condition, not a reason to expand into code changes.

### Required evidence

Report the exact implementation revision, inventory and disposition artifact, each removed path and historical SHA, reference-search scope/results, link and history checks, preserved-file comparison, active file count, deviations, blockers, and checks not run.

## Stop conditions

Stop and return to planning if:

- material repository changes or new evidence since the accepted baseline cannot be reconciled;
- a proposed deletion would remove unique binding requirements, stable identifiers, clinical/privacy/security decisions, live task inputs, or required operational evidence without an authority-approved replacement;
- a source, build, test, deployment, or active validated-task consumer depends on a candidate file and cannot be repaired within this documentation-only scope;
- the Google Doc draft status or its identity conflicts with supplied human authority;
- a decision is needed to approve, reject, interpret, or resolve business/clinical policy;
- preserving historical retrieval requires modifying old validated task contracts or relying on unavailable Git objects;
- any application or external-system mutation is required;
- the current baseline differs materially from the declared baseline and reference/deletion decisions have not been revalidated.

The Executor must not silently broaden the task or reduce required evidence to reach the preferred file count.

## Side-effect authorization

This task, once validated, authorizes repository documentation edits, reference repairs, the historical index, and deletion only of the exact 39 listed `docs/` candidate paths and three listed `.agents/context/business/` paths when the acceptance gates pass. All other tracked files are KEEP. Any additional deletion requires a new task revision independently reviewed and approved before execution. This task does not authorize application or external-system mutation.

### Explicitly authorized side effects

- Repository-local documentation edits and deletion of obsolete, duplicated, or historical documentation, including `.agents/context/business/`, after all stated gates pass.
- Git commit only if separately authorized by the eventual execution handoff; no push, PR, merge, deployment, or release is authorized by this task.

## Expected terminal outcome

### Review Required

Return a reviewable documentation diff and truthful evidence for every acceptance criterion, including preserved authority/evidence, path-by-path deletion traceability, reference closure, and exact historical retrieval.

### Planning Required

Return the specific unresolved authority, consumer, provenance, baseline, or evidence blocker and the paths affected. Do not perform a disputed deletion.

## Review and remediation handling

Review against this exact task revision, the declared implementation baseline, human authority, and observed repository evidence. Bounded corrections may update this same task path and require a new immutable task revision before execution resumes. Acceptance does not authorize release.
