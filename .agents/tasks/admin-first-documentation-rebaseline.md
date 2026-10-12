---
title: Admin-first Operational MVP Documentation Rebaseline
document_id: MHCS-TASK-ADMIN-FIRST-DOCUMENTATION-REBASELINE-001
version: 1.1
status: draft
language: en-US
last_updated: 2026-10-12
scope:
  - candidate contract for a later lean Admin-first documentation rebaseline
  - context entrypoint and active MVP planning only
authority_note: Draft for independent Planner/Reviewer review. The human approved task authoring and branch publication, not application implementation or self-approval of unresolved product, clinical, security, or privacy decisions.
---

# Executable Task — Draft

## Task identity

**Task title:** Admin-first Operational MVP Documentation Rebaseline

**Task path:** `.agents/tasks/admin-first-documentation-rebaseline.md`

**Task contract state:** `Draft` — candidate published for review, not Validated/Published or execution-ready.

**Delivery objective / Work Package / MVP:** One bounded documentation rebaseline supporting the Admin-first operational MVP. This contract produces coherent documentation and traceability, not the operational application.

**Owner / designated planning authority:** Human MHCS Product Owner and repository Planner/Reviewer. Clinical, security, privacy/legal, technical, and release decisions retain their designated authorities; this task does not appoint substitutes.

## Delivery context

The human's task-authoring/publication handoff received on 2026-10-12 approves planning around **one persistent internal Admin login and one unified Admin-first operational dashboard**, with Member, Operator, Doctor, and Image Gateway retaining their domain-owned capabilities. The target is eventual real operations with patient data, not a synthetic demonstration.

The existing repository combines August controlled-beta priorities, historical implementation matrices, later operational exceptions, and a September business-context overlay. These are useful but do not provide one consistent current delivery contract. The outcome is a concise context entrypoint and active MVP planning set that makes authority, current implementation, desired MVP behavior, future scope, and unresolved decisions explicit. The extensive implementation ledgers remain unchanged historical authority and reference.

The current authoring execution changes only this candidate task. All dispositions and acceptance criteria below govern a **later, independently validated documentation execution**.

## Baseline and task revision

**Implementation baseline:** `b1a2204a2571920a6c696575a6480aad5a1294fd` in `Madeena-software/mhcs-core`.

**Execution-start observation:** Local `main`, HEAD, and freshly fetched `origin/main` all matched that SHA; the worktree was clean. The supplied reference HEAD is identical, so there is no intervening mainline delta to reconcile. Authoring branch: `task/admin-first-doc-rebaseline`, created from verified `origin/main`.

**Task revision:** The full immutable commit containing this exact file, supplied in the authoring handoff. An immutable Draft revision is not T5 validation. Any material correction must use this same stable path and a newly resolvable governing revision.

**Accepted-baseline limitation:** The SHA above is the observed implementation baseline, not a new A9 acceptance claim. Its merge of Operator–AI PACS work is visible in Git, but this authoring pass did not independently review that application's acceptance, CI, clinical readiness, or deployment. The Planner must reconcile relevant pending execution/review state before validating dependent work.

**Pinned business authority:** `Madeena-software/mhcs-business-docs @ 645058e431f59c4450a136e72f140e6819b79f32`. Remote `refs/heads/main` was checked read-only and matched this object. The separate local business checkout was older (`1f575061cade5d8b9fa6e364c47c67384ea7e8c1`); sources were therefore read with `git show` at the pinned remote revision, without changing that checkout. The three local `.agents/context/business/` files are byte-identical to its `docs/business/` files.

Before later execution, reconcile any drift in either repository and explicitly confirm or republish the baseline. Do not silently substitute a moving branch for either immutable reference.

## Remediation

**Review basis:** `1ad0839c7c88961ee16209d17bee11e4687630eb` on `task/admin-first-doc-rebaseline`.

The human's review-remediation instruction narrows the later refactor to the context entrypoint and necessary active MVP planning documents. The three `docs/implementation/` ledgers become KEEP / historical authority and reference; archival, cache cleanup, and exhaustive source/identifier reconciliation are excluded. Preserve existing authority, decisions, identifiers, evidence, integration and security boundaries, and open approvals. This revision remains Draft and authorizes no documentation implementation.

## Objective

Establish a lean Admin-first priority, scope, gaps, roadmap, and decision history in `.agents/context/project.md` and the six active MVP planning documents listed below. Preserve applicable domain authority, stable identifiers, safety constraints, and historical decisions by reference; distinguish current implementation, desired MVP behavior, future scope, and unresolved proposals without rewriting unchanged source ledgers.

## Authoritative inputs

### Governing authority

1. **Human handoff, 2026-10-12:** Authorizes this candidate and the planning direction summarized below; authorizes substantial later documentation restructuring, including deletion of genuinely obsolete documentation, subject to preserved authority and review gates. It expressly excludes present application implementation, existing-document refactoring, business-repository writes, PR/issue activity, main merge, production mutation, and release.
2. **Repository governance at the implementation baseline:** `AGENTS.md`, `.agents/AGENTS.md`, `.agents/software-workflow.md`; `.agents/prompts/plan-create-task.md` and `.agents/tasks/_template.md` supply orchestration and contract structure, not new product authority.
3. **Business sources at the pinned business revision:** `docs/business/01-business-overview.md`, `docs/business/02-user-stories.md`, `docs/business/03-system-responsibilities.md`. Local exact copies reside in `.agents/context/business/`. The business repository identifies main as approved authority in `.agents/tasks/mhcs-product-model-reset.md`; `.agents/tasks/mhcs-core-context-package-simplification.md` calls these three sources accepted. Candidate branches, research, presentation/demonstrator material, and their proposed defaults do not gain authority by association. Source sections explicitly marked candidate or open remain so even on main.
4. **Existing local authority and lineage:** `docs/mvp/decision-log.md`; the requirement IDs, source lifecycle, work packages, and approval registers in `docs/implementation/`; scoped approval/evidence records in `docs/mvp/evidence/`, `docs/member/`, and `docs/security/`.
5. **Later operational contracts requiring reconciliation:** `.agents/tasks/urgent-operator-field-operations.md`, `.agents/tasks/ai-pacs-integration.md`, and `.agents/tasks/operator-ai-pacs-integration.md`, as present at the implementation baseline. The latter's current task content was published at `039438c91e9f488eeab64e02f5f967d2df5e49a9`; its merged execution does not silently amend all upstream business sources.
6. **Protected integration authority:** `docs/mpips/mhcs-dicom-api.md` (contract v1.2; MPIPS baseline `ae5ef53f0eef89b0033e28f2329579e0cbc0e50e`), `docs/mpips/mhcs-grabber-dicom-ingestion-contract.md` (v1.0), and both JSON examples in `docs/mpips/examples/`. Keep their ownership, authentication, validation, idempotency, privacy, and compatibility meaning intact. The MPIPS baseline is the contract's declared reference; that commit object was not available in the inspected local repositories, so its upstream correspondence was not independently verified.

### Human-approved planning direction

The later documentation must represent these seven connected outcomes without claiming they are already implemented:

1. Admin manages sites, shifts, Members, and examination assignments through domain-owned capabilities.
2. Admin supports manual registration and planned CSV/XLSX bulk import, downloadable templates, and validation. Exact fields, conflict handling, transaction semantics, and identity rules require approved definition; the old one-time roster format is not a generic import specification.
3. The existing authenticated DICOM ingestion pipeline feeds a centralized study worklist; preserve legacy NPZ/MPIPS compatibility and current trust boundaries.
4. Admin monitors incoming studies, processing status, and resulting reports within approved access scope.
5. Members receive authorized finalized results through secure, time-limited links without permanent portal accounts.
6. Radiographers and radiologists receive appropriately scoped temporary workspaces for authorized review and permitted actions.
7. Clinical actors, identities, assignments, reports, and access events remain separately attributable and auditable.

A unified Admin login surface does not authorize shared credentials, anonymous clinical actions, an Admin-owned clinical domain, or elevation of a non-clinical administrator into a signing clinician. Account cardinality and delegation mechanics remain open where consequential.

### Requirement traceability

The following are task acceptance labels, not replacements for existing requirement identifiers.

| Label | Required documentation outcome | Source / traceability to retain |
|---|---|---|
| DR-01 | Record the Admin-first priority and explicit supersession scope | Human handoff; MVP-DEC-001/002/004/005/006/008/010/014/021/028/029/031; business Unified Administration boundary |
| DR-02 | Describe Admin operations and manual/planned bulk registration without inventing import policy | Human handoff; US-ADMIN-003/010/011; US-B2B-001/002; US-STAFF-REG-008; MEM-214/MEM-228; FIELD-OPS-001/002/003 |
| DR-03 | Preserve study-ingestion ownership, worklist monitoring, and processing/report distinctions | Human handoff; US-ADMIN-004; US-STAFF-RAD-002/004/005; FIELD-OPS-007/008/009; INT-DUAL-001 and INT-ASYNC-001; protected MPIPS/Grabber contracts |
| DR-04 | Separate temporary Member result access from staff and clinical workspaces | Human handoff; US-MEMBER-003/009/016/017/018/019; US-STAFF-SHARED-002; US-DOCTOR-SHARED-002/004; US-DOCTOR-RAD-003/007/008 |
| DR-05 | Preserve clinical authorship, identity, authorization, consent, audit, and unresolved approvals | Business access map and Doctor report boundary; APP-001/002/003/004; FIELD-OPS-004/005/006; MVP-DEC-017/018/019/033/035/036/038/041 |
| DR-06 | Preserve source, requirement, decision, gap, work-package, task, and evidence identities through restructuring | Canonical protocol §§8, 12, 15, 22; existing ARCH-/DES-/DOC-/IMG-/MEM-/OPR-/UIL-, WP-, MVP-GAP-, MVP-DEC-, FIELD-OPS-, and INT- identifiers |
| DR-07 | Make orientation, current state, desired state, future scope, and recoverable history discoverable | Human handoff; canonical context and evidence rules; inspected document inventory below |

New requirement proposals must have a source, owner, approval status, dependency, and verification expectation. Existing IDs must not be renumbered or silently reused. Preserve existing merged/retired ID mappings by reference; record replacements for identifiers affected by the edited planning documents. Do not rebuild the unchanged requirement or source-coverage ledgers.

## Verified conflicts and readiness gaps

These observations are grounded in the pinned sources, not approvals to resolve them.

| Issue | Verified conflict or limitation | Required disposition / approval boundary |
|---|---|---|
| C1 — Delivery priority and portals | `docs/mvp/README.md`, `beta-scope.md`, and roadmap prioritize Member/Operator portals; MVP-DEC-006 makes Member login/profile the first slice, while the current business model excludes a permanent Member portal. | Propose superseding their active priority with Admin-first; preserve earlier accepted implementation and historical decisions. Product Owner must approve the exact changed active scope. No portal removal or account migration occurs here. |
| C2 — Clinical workspace scope | MVP-DEC-004/005/010 exclude internal Doctor work and plan external/manual Operator report handling. Business Doctor stories and the human direction require scoped radiologist workspaces. | Record proposed supersession of the blanket MVP exclusion; define which capabilities are current, targeted, or deferred. Do not invent signing, finalization, amendment, clinical authentication, or recipient-release policy. |
| C3 — Clinical content access | Business System Responsibilities access map denies Site Staff AI results and doctor reports. The later Operator–AI PACS task permits scoped screening-PDF downloads and requires an unverified-AI disclaimer; current web routes include download/retry actions. | Preserve both provenance chains and flag the unresolved policy conflict. Product plus clinical/security/privacy authorities must resolve the access matrix. AI PDF availability is not final clinical-result authorization. Admin monitoring does not imply unrestricted report contents. |
| C4 — Consent and stage progression | Business context describes signed paper consent per visit and staged examination. The urgent field task records reusable versioned master consent, lightweight visit confirmation, and optional audited Basic Examination bypass. | Preserve the interim human directive and historical consent records; seek explicit source reconciliation rather than reinstating obsolete rules or generalizing the exception. |
| C5 — Privacy and storage exceptions | MVP-DEC-017's unsupported approval claims were corrected through 018 and the bounded 019 closure. MVP-DEC-038/041 retain plain private objects instead of earlier application encryption; 035/036 change Operator DICOM download/access conditions. | Preserve exact audience, purpose, date, supersession, and environment limits. Do not treat bounded approvals as general production/privacy approval or silently harmonize conflicting encryption/access wording. |
| C6 — Import and real-data scope | MVP-DEC-008 deferred import; 021/025/026/027 and 042/043 authorized limited roster/rehearsal exceptions. `docs/mvp/import-data/README.md` specifies a one-time CSV and credential handoff. | New CSV/XLSX templates/validation are planned capabilities, not satisfied by the old fixture. Product/privacy approval is needed for schema, matching/deduplication, invalid-row/conflict behavior, recipient data, and any migration. No real roster may enter this documentation exercise. |
| C7 — Stale orientation and status | Context points at absent local `docs/business/`, hypothetical module contexts, and an old context revision. MVP status documents stop at `841465faac4e8b1dd3670103052a9c4f075bfd04`; conformance matrices retain a 2026-08-04 snapshot and missing old specification paths. | Repair navigation in the scoped context/MVP documents and identify the implementation ledgers as historical authority/reference. Preserve their source digests and unresolved provenance unchanged; resolving their old missing paths is outside this task. Verify only implementation/acceptance claims changed in active guidance. |
| C8 — Current baseline acceptance | Main includes recent field/AI work beyond the dated status ledger; task frontmatter still says validated-published and does not prove execution acceptance or release. | Planner reconciles relevant exact task revisions, execution results, review verdicts, and overlap. Unknown state remains unknown. This candidate does not reopen or accept those application tasks. |

**Gate assessment:** Business-priority reconciliation reopens B0/P1 for the affected delivery line; R2/A3/D4 must carry the reconciled scope and constraints. T5 is **not passed**. Publication of this Draft closes none of those gates.

## Scope

### In scope — later documentation execution only

The write scope is limited to these existing documents, with edits only where needed for the Admin-first rebaseline:

- `.agents/context/project.md`
- `docs/mvp/README.md`
- `docs/mvp/beta-scope.md`
- `docs/mvp/roadmap.md`
- `docs/mvp/beta-gap-register.md`
- `docs/mvp/decision-log.md`
- `docs/mvp/work-package-status.md`

Within that scope, establish the new priority, scope, dependencies, gaps, and decision history; record authority conflicts as attributed decisions or open proposals. Keep context concise and link to unchanged business sources, historical implementation ledgers, contracts, and evidence. Consolidate duplicated narrative only within these documents, preserving affected identifiers and historical decisions. No separate document hierarchy, requirement-ledger rewrite, new files, relocation, archival, or deletion is required or authorized.

### Out of scope

Application code, routes, UI, tests that change runtime contracts, migrations, configuration, dependencies, accounts, credentials, data import, runtime validation using patient data, production/clinical records, operational execution, deployment, or release. No implementation of Admin, import, links, workspaces, report finalization, or authentication.

All files outside the seven-document write scope remain read-only, including `docs/implementation/`, business-source copies, evidence, import guidance/data, prototypes, integration contracts, and historical design material. Do not edit `Madeena-software/mhcs-business-docs`, MPIPS, root/runtime instruction files, `.agents/AGENTS.md`, `.agents/software-workflow.md`, prompts, templates, manifests, or other governance protocols. Existing `.agents/tasks/` contracts remain unchanged; identify their status/overlap in the documentation instead. Links from protected surfaces that would require mutation must be preserved by retaining their destinations or returned to planning.

### Preserved behavior and authority

- Member, Operator, Doctor, and Image Gateway ownership; existing Admin capabilities and application boundaries.
- Authenticated, scoped, private DICOM/NPZ ingestion, checksum/idempotency semantics, legacy processing compatibility, and separation of processing from clinical finalization.
- MRN/NIK separation; reservation/session locators are neither authentication nor identity proof. Retain actor, assignment, consent, report-version, and access-event attribution.
- No broader clinical data access, report release, credential sharing, or production approval inferred from UI simplification.
- Existing clinical/privacy/security/financial constraints, scoped exceptions, requirement/gap/decision IDs, approvals, evidence, source digests, and immutable task lineage remain recoverable and discoverable.

### Proposed KEEP / UPDATE / ARCHIVE / DELETE disposition

Inventory basis: tracked contents at the implementation baseline. UPDATE is limited to the seven-document write scope; KEEP preserves all other content and paths. There are no ARCHIVE or DELETE actions in this narrowed contract. None of the later documentation updates occurs in this authoring/remediation execution.

| Surface | Disposition | Inspection basis and safe execution condition |
|---|---|---|
| `.agents/context/project.md` | UPDATE | Product-context overlay is useful but misroutes local business/module links and lacks the current application baseline. Replace duplicated narrative with concise pinned authority/evidence routing and explicit Admin-first target status. |
| `.agents/context/business/01-business-overview.md`, `02-user-stories.md`, `03-system-responsibilities.md` | KEEP | Verified exact copies of the pinned accepted business sources; preserve portable source access and open-decision wording. Do not rewrite upstream authority locally. Link reconciliation decisions from the scoped MVP documents rather than forking their meaning. |
| `docs/mvp/README.md` | UPDATE | Active entrypoint still declares controlled beta and portal components. Make the new active planning scope, authority order, navigation, and review status unambiguous. |
| `docs/mvp/beta-scope.md` | UPDATE | Distinguish current application, Admin-first target, and historical beta. Retain the historical adult/rehearsal restrictions as dated scope; do not generalize them into new policy. |
| `docs/mvp/roadmap.md` | UPDATE | Reprioritize the target journey and dependencies, not requirement IDs. Label superseded deadlines/parallel-workstream narrative as historical within the existing document; no new implementation tasks in this contract. |
| `docs/mvp/beta-gap-register.md` | UPDATE | Retain gap IDs, evidence, controls, and closure criteria. Add/reclassify Admin-first authority/readiness gaps without declaring them closed merely because the roadmap changes. |
| `docs/mvp/work-package-status.md` | UPDATE | Update only the planning orientation needed to distinguish the new priority from dated accepted foundations and future obligations. Retain existing status records; verify only claims changed by this rebaseline, without auditing every Work Package. |
| `docs/mvp/decision-log.md` | UPDATE | Preserve all historical rows and approval provenance. Add attributed proposed/approved supersession links; preserve 017→018→019, 020→031, 032→035→036, 038→041, and 042→043 chains and their limited scope. |
| `docs/implementation/mhcs-core-implementation-plan.md` | KEEP / historical authority and reference | Retain Work Package definitions, CON-/DEC-/DEP-/APP-/RISK- registers, historical ordering, and unresolved provenance unchanged. Link from active MVP planning; no rewrite or exhaustive reconciliation. |
| `docs/implementation/mhcs-core-requirements-matrix.md` | KEEP / historical authority and reference | Preserve its 594-requirement snapshot, stable IDs, assignments, and source links unchanged. Active MVP documents may reference relevant rows; no requirement-by-requirement remapping or rewrite. |
| `docs/implementation/mhcs-core-source-coverage.md` | KEEP / historical authority and reference | Preserve the 982-ID lifecycle, heading/statement/design coverage, and source digests unchanged. No consolidation, ledger rewrite, or exhaustive source recovery. |
| `docs/mpips/mhcs-dicom-api.md`, `docs/mpips/mhcs-grabber-dicom-ingestion-contract.md`, `docs/mpips/examples/*.json` | KEEP | Independently authoritative conversion/direct-ingestion contracts and examples. Do not merge them into generic MVP prose or change their contract meaning. |
| `docs/mvp/evidence/*.md` (all existing records), `docs/member/wp-04-identity-evidence.md`, `docs/security/wp-02-security-evidence.md` | KEEP | Dated evidence includes bounded acceptance, incomplete review, remediation, privacy closure, and production records. Preserve bytes and paths; active indexes may explain limitations. Never rewrite them as current proof. |
| `docs/operator/physical-printer-validation-protocol.md` | KEEP | Hardware-specific validation remains an operational dependency; dashboard changes do not remove it. |
| `docs/mvp/local-core-walkthrough.md` | KEEP | Explicitly disposable non-clinical rehearsal; retain its local-only boundary and link it as such, not as the operational MVP or production procedure. |
| `docs/mvp/import-data/README.md` | KEEP | Historical one-time CSV guidance and credential restrictions remain unchanged. Describe planned CSV/XLSX capabilities and pending approval only in active MVP scope/gap documents. |
| `docs/mvp/import-data/.gitignore` and ignored contents | KEEP / DO NOT ACCESS DATA | Existing exclusion protects roster/credential files. Do not weaken it, ingest ignored files, or treat them as deletion candidates. |
| `docs/superpowers/specs/2026-08-09-mvp-workstream-planner-split-design.md` | KEEP / historical reference | Preserve the original design and path. Active MVP guidance may cite MVP-DEC-031 as its supersession; archival and repair of this historical file are outside scope. |
| `docs/operator/reference/claude-design/` HTML, JSX, JS, CSS | KEEP | Design-only prototype bundle, not product or implementation proof; archived task references still point to `photo-booth.jsx` and `style.css`. No design-system refactor or default deletion. |
| `docs/operator/reference/claude-design/graphify-out/cache/stat-index.json` | KEEP | Derived prototype cache is unrelated to the lean planning rebaseline; no cleanup or deletion. |
| `.agents/tasks/*.md`, `.agents/tasks/archive/*.md` excluding this candidate | KEEP, read-only | Preserve exact governing revisions and status history. In particular `operator-capture-display-reference.md` already declares itself superseded; that alone does not justify deleting or relocating a task. |
| All other tracked material not explicitly covered | KEEP | Outside the write scope; preserve unchanged. Any necessary expansion requires Planner review and a republished contract. |

When consolidating narrative or changing a section/anchor in a scoped document, preserve affected identifiers and historical decisions and repair affected references within the write scope. If a reference from an unchanged file would break, retain the target anchor/path or return to planning. Do not repair or exhaustively reconcile unrelated historical references.

## Dependencies and assumptions

### Dependencies

- Independent Planner/Reviewer review of this exact candidate and relevant baseline/overlapping task state.
- Product Owner reconciliation of C1/C2 and the exact active-MVP supersession boundary; technical/security/clinical/privacy owners for affected policy changes.
- Read access to pinned business sources and relevant historical references used by the edited documents. An unavailable source stays an explicit evidence gap; broad source recovery is not required.
- Coordinate changes to the scoped context entrypoint and active MVP documents with any concurrent delivery; do not overwrite unpublished work.

### Approved assumptions

- The human direction authorizes documentation planning for real operational use; it is not patient-data-use or release authorization.
- A unified Admin presentation can preserve domain ownership, as both MVP-DEC-014 and the business Unified Administration boundary require.
- Existing authenticated Grabber routes, an Admin panel, Operator AI report routes, and ingestion service code are observable at the baseline. Their presence alone does not establish the requested centralized dashboard, generic import, temporary workspaces, final-result delivery, or conformance.
- Existing business copy parity was verified; graph results were discovery aids only. Graphify returned historical material and Codebase Memory omitted newer ingestion/AI symbols, so material observations were checked against actual documents, Git, routes, and source. No index output proves current runtime behavior.

### Remaining approval requirements

| Decision / evidence | Owner | Required before |
|---|---|---|
| Approve this documentation contract, reconcile baseline/overlap, and record exact governing revision | Independent Planner/Reviewer with Product Owner for scope changes | T5 and later documentation execution |
| Record exact priority, permanent-portal, Doctor-workspace, and import supersession; decide whether unresolved matters remain explicitly deferred | Product Owner | Promoting new scope/requirements from Draft to Approved |
| Admin/clinical access matrix; staff identity, credentials, assignment, delegation, session expiry/revocation/recovery; accountable clinical actions | Product, clinical, security/privacy, technical authorities | Approving affected access requirements; later implementation |
| Finalized-result definition, signing/finalization/amendment, recipient/guardian authorization, secure-link lifetime/disclosure/revocation and fallback | Clinical and security/privacy authorities with Product Owner | Approving result-delivery requirements; later implementation |
| Reconcile consent/bypass and existing scoped privacy/storage exceptions; define any expanded real-data use, retention, consent, migration policy | Clinical, privacy/legal, security and data owners | Changing those contracts or undertaking real-data operations |
| CSV/XLSX field/template, identity matching, duplicate/conflict/invalid-row handling and atomicity, validation/error disclosure, storage and retention policy | Product, Member-domain and privacy/security owners | Approving generic import specification; later implementation |
| Environment readiness, production data/migration, deployment, and release | Designated release authority and relevant domain owners | Separate G10 work; never authorized here |

These unresolved product mechanisms need not all be designed to perform a documentation-only rebaseline. The Planner may validate documentation work that explicitly preserves them as open/deferred, with owners and triggers, after approving that bounded treatment. The Executor must never choose their outcomes. This authoring pass does not make that validation decision.

## Required capabilities

Repository read and bounded documentation write; read-only Git/history access to both pinned repositories; local shell for document/reference verification. Existing Graphify/Codebase Memory may aid discovery, with direct-source verification. No production, browser automation, clinical service, secret access, dependency installation, or external write capability is required for the later documentation outcome.

## Execution constraints

- Keep one coherent contract and the stable task path. Later Executor chooses document layout and consolidation mechanics within the approved scope.
- Reuse the repository's document structures, stable IDs, and authority/evidence distinctions. Do not create a parallel governance framework or mandatory new documentation toolchain.
- Mark new authority-bearing proposals Draft until approved. Date and attribute supersession; preserve the old decision's scope and reference.
- Do not silently resolve any C1–C8 conflict from existing code, graph output, or the apparent recency of a document.
- Never label an AI screening PDF as a signed clinical report or equate processing completion with Member publication approval.
- Keep all files outside the seven-document write scope unchanged. No archival, deletion, historical-ledger rewrite, or exhaustive repository-wide reconciliation. No patient data, secrets, ignored roster files, or application artifacts may be copied into documentation or commits.

## Acceptance criteria

- [ ] **DR-01:** Active scope and roadmap clearly identify Admin-first operations as the proposed or approved priority, with correct approval status and exact links to every affected prior decision. No historical approval is silently overwritten.
- [ ] **DR-02:** The seven human-directed outcomes are represented with domain owners, current/target/future classification, and visible gaps; planned CSV/XLSX import and templates do not imply existing functionality or approved schema.
- [ ] **DR-03:** DICOM/NPZ ownership and protected contracts remain intact; worklist, processing, AI screening output, doctor finalization, and recipient release are distinct.
- [ ] **DR-04/05:** Persistent Admin access, temporary clinical/staff workspaces, and temporary authorized Member results are distinguished; access, clinical authorship, consent, privacy, audit, and unresolved mechanics remain explicit without inventing policy.
- [ ] **DR-06:** Identifiers and source/decision references used in the edited planning documents are preserved or have explicit scoped successor mappings. Unchanged requirement/source-coverage ledgers retain their historical authority by reference; deferral does not imply completion.
- [ ] **DR-07:** `.agents/context/project.md` routes a reader to valid authority, current observed evidence, active MVP planning, open approvals, and historical material without requiring duplicated full specifications. Current claims identify baseline and evidence limits.
- [ ] **DR-06/07:** Changes are limited to the seven-document write scope and preserve affected section/identifier references. The three implementation ledgers, business sources, approved historical decisions, evidence, contracts, security boundaries, governance, and existing task content remain unchanged.
- [ ] **DR-05/07:** C1–C8 are either resolved by attributable approval/evidence or explicitly open with owner, impact, temporary treatment, and resolution trigger. No unsupported acceptance, production-readiness, compliance, or release claim remains in rewritten active guidance.
- [ ] **DR-06/07:** No newly broken reference is introduced by edits to scoped documents. Check changed references and inbound references to changed anchors only; unrelated historical missing targets remain recorded limitations without a repository-wide repair requirement.

## Verification requirements

### Required checks

1. Record actual execution baseline, governing task revision, working-tree state, business-source SHA, and drift/overlap reconciliation before edits.
2. Review the complete diff and `git diff --check`; verify the changed-file allowlist and unchanged protected evidence, contracts, governance, application, and existing task surfaces against the baseline.
3. Check changed Markdown links, heading anchors, and literal path references in affected documents. Search specifically for inbound references to any changed anchor; retain compatibility for references from read-only files. Do not scan or repair all historical links when their source documents are unchanged.
4. Compare only identifiers and source/decision mappings affected by edits in the scoped documents. Explain removed or changed references through retained records or explicit supersession. Do not enumerate or reconcile the unchanged implementation ledgers or all repository identifiers.
5. Walk the scoped context/MVP navigation to priority, scope, roadmap, gaps, decisions, and referenced historical authority/evidence. Verify only newly added or changed implementation/acceptance claims against relevant evidence; retain unverified status where proof is unavailable.
6. Review C1–C8 and the seven target outcomes against the pinned business sources and human direction. Check that no unresolved clinical/access/import policy became approved through wording alone.
7. Check the diff for credentials, patient data, real roster content, and private operational payloads. Use aggregate/sanitized references only.

Application tests, external clinical calls, deployment, and production probes are not necessary evidence for a documentation-only diff. If a new claim would require those observations, preserve the evidence gap rather than running unauthorized operations.

### Required evidence

Report exact governing task path/revision, baseline, result revision or precise working-tree state, changed-file summary, affected reference/identifier mappings, commands actually run and observed outcomes, scoped reference/ID check results, approvals relied upon, unresolved decisions, deviations, and checks not run. Local document checks are not CI or application acceptance. Keep execution evidence outside this contract to avoid self-certification.

## Stop conditions

Stop the affected work and return to Planner/Reviewer when required authority is missing or contradictory; a dependency or baseline cannot be reconciled; task scope changes materially; acceptance requires application/governance/business-repository mutation; preserving authority would require archival, deletion, or historical-ledger changes outside scope; links cannot be preserved within scope; an unexpected security/privacy/data-integrity issue requires new authority; or a side effect is not authorized.

Recording an already identified open decision as open is permitted by the validated documentation contract. Resolving it by invention is not. Unknown task/acceptance state must not be converted into accepted, obsolete, or executable status.

## Side-effect authorization

### Current authoring/publication execution

The original human handoff authorized creating and publishing `task/admin-first-doc-rebaseline`. The current review-remediation instruction explicitly authorizes updating **this file only**, lightweight verification, local commit(s), and a non-force push to that same existing branch. The reviewed authoring revision is `1ad0839c7c88961ee16209d17bee11e4687630eb`; preserve its ancestry and unrelated work. Commit and push follow successful bounded verification without repeated approval. Stop on incompatible branch drift; never overwrite remote history.

No PR or issue creation/update, merge, deployment, release, production mutation, business-repository mutation, force-push, reset/clean, secret handling, or existing-document refactor is authorized in this execution.

### Later documentation execution

The latest human review instruction narrows the original restructuring direction to the seven existing documents listed in scope. Later edits may begin only after this contract is independently validated and its exact governing revision is supplied. No archival, deletion, implementation-ledger rewrite, or mutation outside that write scope is authorized. Preserve unrelated source, governance, independently authoritative contracts, production records, existing task contracts, and unpublished work.

The current commit/push permission is specific to task-authoring publication; it does not authorize a later Executor to publish refactored documentation, create a PR, merge, mutate external services, or release. Those side effects require applicable separate authorization.

## Expected terminal outcome

**This execution:** Candidate task committed and pushed, still Draft, returned for independent Planner/Reviewer review. Stop; no Executor Launcher or application/documentation-refactor execution.

**Later validated documentation execution:** `Review Required` with the exact resulting revision/state and truthful evidence, or `Planning Required` with the blocking condition and supporting evidence. The Executor does not self-declare final acceptance or production readiness.

## Review and remediation handling

Review against the exact task revision, implementation baseline, pinned business sources, documented human authority, and observed evidence. Resolve baseline/authority gaps before dependent task execution. Bounded corrections update this same stable path; material contract changes require a new immutable publication. New product/application objectives return to planning. Acceptance of the documentation rebaseline does not authorize application implementation, data migration, deployment, or release.
