---
title: MHCS Integrated Preflight and Release-Readiness v1
document_id: AGENT-TASK-MHCS-INTEGRATED-PREFLIGHT-001
version: 1.0
status: Validated/Published
language: en-US
last_updated: 2026-10-16
authority_note: Product Owner authorizes task-only T5 publication. Independent exact-revision review and subsequent E6 authorization are required before synthetic execution. Assessment does not grant acceptance, merge, production or release authority.
---

# MHCS Integrated Preflight and Release-Readiness v1

## Task identity

**Task path:** `.agents/tasks/mhcs-integrated-preflight-release-readiness-v1.md`.

**Task contract state:** Validated/Published at the verified immutable task-only publication. Independent exact-revision T5 review and subsequent E6 authorization remain mandatory. This publisher supplies neither an independent review verdict nor an execution launcher.

**Delivery objective / parent:** one integrated synthetic candidate verification and prioritized G10 gap assessment, continuing the accepted Admin → Radiographer → Radiologist → Member development sequence. Bounded cross-module verification of WP-02/14/18/19/20/22/23/24; no whole-Work-Package closure or MHCS planning restart.

**Owner / designated planning authority:** Product Owner for this bounded direction; independent Planner–Reviewer for exact task review, E6 handoff and later implementation/evidence review. Member, Operator, Doctor, Image Gateway, security/privacy, clinical/facility and operations/release authorities retain their existing responsibilities. These role names do not appoint an individual or invent approval.

## Delivery context and objective

Establish whether the exact accepted synthetic candidate remains coherent across all three clinical-workspace slices and the existing Admin/Operator workflow, and identify the concrete work required before safe operational use. Produce fresh, reproducible integrated evidence and an ordered G10 blocker matrix. Preserve accepted development baselines and independent review. Do not merge branches, deploy, fix application behavior, enable patient publication, or turn synthetic A9 acceptance into G10 approval.

## Baseline and task revision

Repository: `Madeena-software/mhcs-core`.

| Identity | Exact immutable revision |
|---|---|
| Main comparison anchor | `f8f0bb9e2e0cd12d1b797cf6a6689f26336274ba` |
| Accepted Radiographer synthetic baseline | `0f27d2b58c804183b907e236aa9735f5fac876ac` |
| Accepted Radiologist synthetic baseline | `cd5128fdf78c2ad8ef95b62786e24858cc57e7ca` |
| Accepted Member baseline / implementation baseline / candidate under test | `9804642c60b678ca004fd9d69a51c682f352d517` |

**Planning branch:** `task/mhcs-integrated-preflight-release-readiness`, created from exactly the Member baseline. Its sole publication delta is this file. The application candidate is the Member SHA, not a newly merged tree or an unpinned branch head.

**Governing task revision:** this path at the full immutable containing commit reported in the publication handoff and external checkpoint after local/fetched/live remote equality verification. Publication status becomes effective only once that identity is observed. Retrieve those exact committed bytes for independent review and E6; never substitute a later revision. The containing SHA need not be self-embedded.

The current Product Owner handoff explicitly supplies the three accepted baselines. Earlier evidence and external memory ending REVIEW REQUIRED are historical Executor records, not contradictory new acceptance verdicts. This task records the newer human declaration without inventing the independent review record or reaccepting those implementations. Historical verification limitations remain relevant.

## Authoritative inputs

### Governing authority

- [Repository contract](../AGENTS.md), [delivery protocol](../software-workflow.md), [task template](./_template.md), [planning/review procedure](../prompts/plan-create-task.md) and [project context](../context/project.md). Context is supporting orientation; its older baseline/pending-work language does not replace the current human handoff or immutable evidence.
- Direct Product Owner direction **“MHCS — Integrated Preflight & Release-Readiness v1”**, supplied 2026-10-16 Asia/Jakarta: accepted SHAs above, A–E verification/assessment scope, task-only branch publication and automatic ordinary commit/push, external checkpoint update, no merge/release authority, and mandatory REVIEW REQUIRED before independent T5 review/E6 authorization. This attributable record preserves the request's scope; it is not a broad approval of the historical business draft.
- Immutable predecessor contracts: [Radiographer](./admin-issued-radiographer-workspace.md) @ `25d396e576ee513bee1a7e2aa2db57a65afdd666`; [Radiologist](./admin-assigned-radiologist-workspace-v1.md) @ `c8886a8ee0c7f11edae5f888fe42e79b86ef5607`; [Member](./admin-authorized-member-result-access-v1.md) @ `3ea425531f4836af38b08b06acd7ef3728b1e00c`. Their bounded policies, exclusions and acceptance criteria remain unchanged.
- [MVP decisions](../../docs/mvp/decision-log.md), especially MVP-DEC-044–046 and scoped predecessor approvals; [active scope](../../docs/mvp/beta-scope.md), [roadmap](../../docs/mvp/roadmap.md), [gap register](../../docs/mvp/beta-gap-register.md), [requirements](../../docs/implementation/mhcs-core-requirements-matrix.md), [source coverage](../../docs/implementation/mhcs-core-source-coverage.md) and [implementation plan](../../docs/implementation/mhcs-core-implementation-plan.md). Historical status columns are not current implementation evidence. No broad documentation rebaseline or new authority decision is required for this assessment.

### Supporting evidence and implementation inputs

- [Radiographer R8 evidence](../../docs/mvp/evidence/radiographer-workspace-r8-remediation.md) at its accepted SHA; [Radiologist R8 evidence](../../docs/mvp/evidence/radiologist-workspace-v1-r8.md) at its accepted SHA; [Member E6 evidence](../../docs/mvp/evidence/member-result-access-v1-e6.md) at the Member SHA. Read earlier execution/checkpoint records only where needed to reconcile a claim. Historical results are comparison leads, not fresh passes.
- Current source, routes, module/provider bindings, additive migrations, permissions, constraints and tests at the candidate. Inspect owner reads, authentication, prepared-response authorization, audit and lock ordering across modules, rather than relying on test names alone.
- `.github/workflows/security-validation.yml`, `deployment/verify-mysql.sh`, `deployment/validate.sh`, deployment policy tests, `Dockerfile`, deployment configuration, `phpunit.xml`, Composer/npm manifests and locks, and installed dependency metadata. Read source safely; never load operational `.env`, credentials, patient objects or production databases.
- External `/home/faliq/.codex/task-state/mhcs-core/CHAT_MEMORY.md` and relevant minimized prior synthetic artifacts. Preserve history; distinguish facts, historical results and proposals. Memory and derived intelligence are supporting aids only.

### Requirement traceability

| Legitimate source / retained IDs | Bounded assessment obligation | AC |
|---|---|---|
| Current PO A; predecessor RAD-URL-001–008; DOC-024/058 | Baseline ancestry, owner boundaries, immutable originals/reports, current exact authorization and integration integrity | AC1–AC3 |
| Current PO B; Radiographer contract verification; Radiologist AC1–AC10; Member AC1–AC12 | Full synthetic journey, independent actor/recipient gates, denial paths, transactions and races | AC2–AC5 |
| Current PO C; repository E6 evidence rules and G10 | Exact-candidate CI coverage, reproducibility, installed/locked parity and read-only public advisories | AC6 |
| Current PO D; MVP-GAP-026/029/031/034/035/036; IMG-AI-007/008/012 and INT-PROV-001/INT-DISC-001 from existing AI contracts | Evidence-backed security/clinical/operational blocker assessment; no assumed remediation | AC7–AC8 |
| Current PO E; protocol E6/V7/A9/G10 separation | Six required outputs, ordered closure conditions and independent review handoff | AC9–AC10 |

The Member contract's manual exact-version publication governs this synthetic journey. Historical DOC-023/IMG-043 automatic visibility does not authorize automatic publication. AI visibility, amendments, guardian access, financial actions and broader certification remain excluded. Requirements are assessed only within existing approved scope; findings do not create implementation authority.

## Scope

### In scope after independent review and E6 authorization

Read-only source/history/configuration analysis; isolated synthetic full and focused PHP/JavaScript/build/static checks; SQLite integrity and rollback; MySQL 8.4 schema and independent-connection race verification; real browser rendering and complete synthetic actor journey; CI/dependency compatibility/advisory assessment; precise evidence reports and prioritized G10 closure proposals. Reuse existing harnesses. Temporary orchestration, source packaging and evidence parsers may be created outside tracked application code, solely for these checks; preserve their commands/digests and disclose adaptations.

### Out of scope

Application, test-contract, migration, dependency, workflow, deployment configuration or permission changes; bug remediation; broad documentation rewrite; PRs, merges, rebases, force-pushes or main mutation; production workflows or deployment creation; operational migrations/backfills; real patient/staff/credential access; live AI/MPIPS/storage/notification calls; credential rotation, source/history cleanup, account/permission changes; inventing clinical-source authority or enabling patient publication. Findings become ordered recommendations for separately authorized work.

### Preserved behavior and invariants

- Original DICOM bytes, UIDs, checksums, signed manifests and source associations; append-only QC/handoff, clinical decisions, authored report versions/finalizations, publication generations/history and attributable audit.
- Independent exact actor/case/site/action/recipient authority. Signed locators never confer bearer authorization. Revocation, reassignment, expiry, identity/credential changes and inconsistent sources remain fail closed at the existing disclosure-start gate. No locks across client I/O; audit is authorization/start, not proof of receipt, and delivered bytes cannot be recalled.
- Individual professional eligibility, separate technical QC and clinical decisions, manually authored finalization, separate manual Member publication, least privilege and owner contracts. No default grants or borrowed role authority.
- `UnknownClinicalSourceInventory` stays application-default deny; `WorkspaceClinicalStatusSource` stays `complete_sources=false`. Positive source closure is confined to the existing sealed, actual closed synthetic inventory in isolated test bootstraps. Prove default unknown inventory denies publication/read and unknown clinical coverage denies reopen. Never replace the default binding or enable operational publication to pass a check.
- All unrelated accepted behavior, existing user changes and repository history. No secret or patient content in tools, evidence, screenshots, audit or logs.

## Dependencies, assumptions and approvals

**Dependencies:** pinned candidate/task/predecessor revisions; existing installed dependencies; supported bounded-verifier runtime; disposable SQLite and MySQL 8.4; browser/WebGL support; isolated fixtures and current public advisory access. Publication discovery observed installed image `mhcs-radiographer-workspace:verified` ID prefix `380f70b20e32` and `mysql:8.4` ID prefix `da906917ca4a`. Reverify full image IDs/digests, capabilities and fixture isolation in E6; image presence is not a runtime pass. Prior evidence identifies PHP 8.4.26, Node 24.21.0, util-linux 2.41.5, SQLite 3.46.1 and MySQL 8.4.8 as a working synthetic combination. Host setpriv limitations remain a reason to use the supported verifier runtime, never weaken its sandbox.

**Approved assumptions:** the supplied baselines are accepted only for their bounded synthetic development scopes; Member contains the predecessor work by ancestry. The candidate is expected to be production-blocked while source authority and other G10 obligations remain open. The assessment may establish a reviewable production-blocked result without fixing blockers; it may not substitute blocked/skipped mandatory verification with a pass. Existing full-suite failures require fresh attribution, not an automatic waiver.

**Remaining approvals:** independent exact-publication T5 review and explicit E6 authorization before any execution. The later E6 handoff must identify evidence branch/output authority and any ordinary evidence commit/push permission; none is implied by this task's T5 publication authorization. V7/A9 acceptance and all clinical/security/privacy/operational/G10 approvals remain with their designated authorities. Unavailable rotation attestation, CI execution authority or production access is reported as an unresolved dependency, never inferred or acquired silently.

## Required capabilities and execution constraints

After E6 authorization: Git/source reads, isolated shell/test/container/browser execution, existing dependency metadata reads, read-only public advisory queries and bounded evidence-file writing. Use Graphify for relevant documentation discovery and Codebase Memory for implementation discovery, verifying material conclusions directly. Do not rebuild derived indexes in the tracked tree for ceremony or scan secret-bearing paths. Independent review is a separate responsibility from the publisher/Executor.

Use an isolated source copy or worktree tied to exact SHAs, excluding `.env`, production storage and auth configuration. No dependency installation/update, Composer setup hooks, npm lifecycle scripts or operational credentials. Reuse installed packages read-only. Confirm Composer classmaps/autoload and Node resolution actually point to each tested checkout, not another candidate. Package tracked `.github`/Docker/test inputs even where export-ignore rules omit them. Build each baseline's own assets. Use separate writable caches, storage and object disks; no hot-copied stale opcode cache or shared WAL files. Record discarded/invalid runs separately.

Use only disposable databases, network/ports and containers created for this execution; verify ownership before destructive test reset and clean up only owned resources. Never reuse a running prior container just because its name matches a harness. Browser harness names/ports are constrained in source: inspect `member-result-access.mjs` and its fixture/bootstrap before orchestration, resolve collisions safely, and stop rather than repointing it at an operational service. Keep Docker default security, bounded parser CPU/memory/time/FD/filesystem/network limits and actual owner verification intact. No privileged or seccomp-unconfined workaround.

Network access is limited to authorized repository reads and public advisory/documentation queries without application data, secrets or private source uploads. Static workflow inspection does not authorize dispatch. Do not run broad JavaScript globs: `tests/JavaScript/https-login-production.test.mjs` and production-named harnesses require exclusion from this synthetic task.

## Acceptance criteria

These are future evidence obligations, not publication-time passes. Assessment completion and integration readiness are distinct.

| ID | Observable required result | Evidence / failure treatment |
|---|---|---|
| AC1 | Exact main → Radiographer → Radiologist → Member ancestry and task/candidate identity are verified; routes/providers, permissions, migration order, uniqueness/FKs and cross-module caller/lock conflicts are assessed. | Record full SHAs, trees, merge-base/ancestry results and changed-surface inventory. Explain every discrepancy; do not merge or silently advance candidate. |
| AC2 | Complete PHP verification runs on candidate and exact accepted Radiologist comparison baseline in equivalent supported isolated environments. | Machine-readable full test identities including datasets/statuses, commands/exit codes/assertions/skips; list new, persistent, resolved and changed-status failures/errors. Compare skip identities too. Historical 25 failures/errors and 19 additional Member opt-in skips are expectations to verify, not results to reuse. |
| AC3 | Focused Admin/Operator/Radiographer/Radiologist/Member, architecture/security/imaging regressions and preservation checks execute. | Named tests tied to predecessor criteria and current invariants; verify immutable image/report/history digests and no excluded side effects. No totals-only or mock-only integration claim. |
| AC4 | Disposable SQLite integrity/constraints/transaction rollback and MySQL 8.4 migration lifecycle plus independently synchronized races execute. | Separate backend/version/isolation/schema/audit/state evidence, zero-skip explicit race runs, migration down/up preservation, lock ordering and failure injection. Sequential calls or default opt-in skips do not pass concurrency. |
| AC5 | Real browser completes Admin issue → individual Radiographer original render/QC/handoff → individual Radiologist original render/authored finalization → separate Admin publication → verified adult Member login/final-only view for both direct and converted synthetic originals. | Assert actual nonuniform rendered pixels, original hashes, immutable final fields, no auto-publication, keyboard/error states and wrong-recipient/revoked/expired/inconsistent-source/audit-failure denials, including prepared responses. Synthetic 2×2 rendering is not clinical display certification or live MPIPS conversion proof. |
| AC6 | CI/dependency readiness and safe static/build/formatting/syntax checks are assessed against this exact candidate. | Installed-versus-locked Composer/npm parity, public advisory IDs/affected versions/severity/time/coverage, actual check outcomes and reproducibility/coverage gaps. No dependency changes, advisory suppression or production workflow dispatch. |
| AC7 | MVP-GAP-026 receives a secret-free status assessment and immediate escalation if credible active-exposure evidence exists. | Distinguish reported history, verified remediation, active indication and unknown. No credential retrieval, reproduction, login probe or rotation. Missing proof cannot close the gap. |
| AC8 | MVP-GAP-035/036, authoritative source inventory and identity/recipient/consent/clinical/privacy/retention/operational controls are independently assessed. | Source/test/approval evidence and explicit missing authority; source defaults remain denying. No invented clinical safety, provenance or institutional approval; no operational enablement. |
| AC9 | All six required outputs are complete, linked to exact revisions and mapped to observable tests/findings. | Blocker rows include severity rationale, evidence, responsible owner role, exact closure condition, dependency and recommended sequence. Prioritize bounded actionable fixes over another global rebaseline. |
| AC10 | Readiness classification and independent-review handoff accurately reflect observed evidence and unresolved gates. | No green/full-suite/CI/release claim from skipped, mocked, historical or unavailable checks; no self-acceptance or release authorization. |

## Verification requirements

### Ordered checks and minimum evidence

1. **Identity and safety preflight.** Pin this task's publication SHA, candidate SHA and all anchors. Reverify ancestry and source hashes before and after execution. Record fetched/live refs separately; branch drift is not authority to change the pinned candidate. Verify fixture isolation, actual runtime/images/backends, parser sandbox capability, no operational endpoints and safe installed dependency reuse before running tests. Check MVP-GAP-026's safe evidence status early; obey the escalation boundary below.
2. **Integration inventory.** Inspect `routes/`, provider registration, migrations `2026_10_15_000001_create_radiographer_workspace_tables.php`, `2026_10_16_000001_create_radiologist_workspace_tables.php`, `2026_10_16_000002_create_member_result_publication_tables.php`, owner contracts/services, fresh capability/facility checks, signed entry/session paths, response gates and coordinated audit/locking. Detect duplicate routes/providers, migration/constraint ordering and stale cross-module assumptions. Record findings without repairing them.
3. **Complete comparison.** Run the complete configured PHP suites on Member candidate and exact Radiologist baseline with the same installed packages, sandbox, inputs and backend/config. Preserve JUnit and compare by full identity/status, not totals; verify suite discovery counts, exceptions and infrastructure failures. Run serially when dependencies/resources can interfere. If a failure predates Radiologist, consult pinned Radiographer/main history or perform a bounded equivalent diagnostic to attribute it; do not claim fresh main/full-Radiographer results from historical reports. Every failed/error identity needs classification and owner, including environment/dependency causes.
4. **Focused regression.** Run `tests/Feature/Admin`, relevant `tests/Feature/Operator` and `tests/Member`, `tests/Feature/Member`, `tests/Feature/Radiographer`, `tests/Feature/Doctor`, `tests/Unit/Doctor`, `tests/ImageGateway/BoundedDicomVerifierTest.php`, `tests/Architecture` and `tests/Security` with inspected synthetic configuration. Include actual Gateway preparation, current identity assets/session/password paths and Operator capture/view/AI-isolation preservation. Record exact selectors and results; mock service tests are labeled separately from real integrated checks.
5. **SQLite/MySQL.** Reuse `tests/Integration/MemberResultConcurrencyTest.php` explicitly with `MHCS_MEMBER_BACKEND=sqlite` and separately `MHCS_MEMBER_BACKEND=mysql`; use `MHCS_RAD_MYSQL_TESTS=1` for `RadiographerConcurrencyTest.php` and `RadiologistConcurrencyTest.php`. Read each harness's DB override first; the current fixtures use dedicated `mhcs_rad_synthetic`/`mysql` and must resolve only to this run's disposable service. Require real independent processes/connections, barriers and committed invalidation before prepared release. Cover handoff/assign/save/finalize/publish conflicts; revoke/reassign/account/eligibility/identity/expiry/lineage changes; audit append/commit failure; SQLite WAL integrity (`integrity_check`, `foreign_key_check`) and rollback; MySQL 8.4 schema creation/constraints/down/up and lock ordering. Explicitly account for every default opt-in skip. `deployment/verify-mysql.sh` alone is insufficient: it does not enable these newer opt-ins. No migrations on existing environments.
6. **Actual browser.** Reuse inspected `tests/Browser/radiographer-workspace.mjs`, `radiologist-workspace.mjs`, `member-result-access.mjs` and their real synthetic bootstraps. The Member harness already spans the full actor chain; retain upstream harnesses for their additional QC, repeat-pending, revocation and expiry coverage. Use actual bounded parsing/owner handoff, individual login, fresh per-action finalization authentication and separate manual publication. Assert exact original/report/source digests, rendering, final-only disclosure and named denial paths. Verify default unknown source inventory denial independently of positive closed synthetic fixtures. Retain sanitized screenshots of synthetic viewports without browser chrome, signed locators, session values or raw requests. No external service is contacted.
7. **JavaScript/build/static.** Minimum explicit safe command: `node --test tests/JavaScript/operator-dicom-viewer.test.mjs tests/JavaScript/radiologist-dicom-modality.test.mjs`; inspect and include other relevant purely synthetic JS tests by exact path. Run `npm run build` with DICOM bundle check; PHP lint and installed Pint, identifying pre-existing formatting separately; JS syntax for inspected browser/fixture code; shell syntax and deployment-policy tests. Review `deployment/validate.sh` before use: it can build and smoke-test images when Docker exists. Perform its static policy predicates/tests separately without build/install/deploy; do not represent omitted image build/smoke as passed. Record exact static commands and compose validation with synthetic placeholders only. Include `git diff --check` for evidence changes.
8. **CI/dependencies.** Assess the manual `workflow_dispatch` trigger, default checkout/ref selection and exact-candidate targeting, permissions, PHP/Node/extensions/util-linux/browser/MySQL prerequisites, fixture isolation, timeout, missing JS/browser/new race coverage, full-suite failures and audit/build ordering. The current workflow uses PHP 8.4, Node 22, `composer install`, `npm ci --ignore-scripts`, Composer audit, Pint, full PHP, MySQL script and deployment validator; source inspection does not prove any step succeeded. Compare both lockfiles to installed package versions, missing/extra packages and platform support, including production and development dependencies. Use read-only metadata and public advisory queries for locked and installed versions; retain advisory identifiers, affected/fixed ranges, severity, direct/transitive applicability, retrieval date and query failures. Do not run install/update/fix/ignore commands or expose auth/config. Distinguish public advisory results, GitHub alert summaries and independently verified applicability; the earlier 20-alert push message is only a historical lead. Recommend exact-candidate reproducible CI changes for later authorization; no workflow dispatch/change here.

### Security and clinical blocker assessment

For **MVP-GAP-026**, read only existing redacted finding/remediation records, commit/path metadata and secret-free credential-rotation/revocation attestations if already available. Do not fetch or open suspected secret-bearing blobs, search raw history for credential values, log in with the reported credential, request production auth data, or print/copy/persist any credential. Source cleanup alone cannot prove rotation/revocation or removal from history. If proof requires prohibited access, mark remediation **unverified**, name the security-owner evidence needed and preserve the blocker. Credible fresh metadata/attestation indicating active public exposure requires immediate user/security-owner escalation through the authorized handoff, without values or account identifiers; halt affected verification and return PLANNING REQUIRED for incident authority. No unsolicited external messages or live rotation.

For **MVP-GAP-035/036**, inspect the actual source-to-derived-report path and existing synthetic tests without reading real reports. At publication discovery, `ProcessAiPacsStudy.php` still supplies normal findings/impression when missing and does not pass facility fields to the derived generator; `AiPacsLaravelDerivedPdfGenerator.php` still supplies default facility branding/address. These are current source findings, not a demonstrated patient incident or newly executed safety test. Independently establish missing/null/partial-content and two-facility/missing/inconsistent-provenance coverage; missing coverage is a finding, never an inferred pass. No generated clinical content, source defaults or branding may be changed under this task.

Assess the missing authoritative historical/external clinical-source inventory separately for real Member publication and positive Radiographer reopen. Assess identity/recipient/consent authority, professional verification, report attestation versus certified signature, privacy/access minimization, audit retention/reader policy/legal holds, storage/session/log controls, operational ownership, monitoring/incident response, backup/restore/rollback, runtime packaging and environment-specific compatibility. Separate source behavior, executed synthetic tests and approved operational policy. Unknown/unavailable real-world evidence stays open; do not access production to manufacture closure. Prioritize existing failures, dependency advisories and CI parity alongside clinical/security risks.

### Required outputs

Use one concise repository evidence entrypoint, `docs/mvp/evidence/mhcs-integrated-preflight-release-readiness-v1.md`, after E6 grants evidence-write authority; retain minimized machine-readable artifacts outside Git under `/home/faliq/.codex/task-state/mhcs-core/integrated-preflight-v1/`. Do not edit this task as self-certification. Reports must contain or link:

1. Integrated candidate verification report: full task/candidate/baseline SHAs and trees, runtime/dependency/source identity, ancestry, route/provider/migration/permission/constraint findings, actual commands/exits/counts/skips and deviations.
2. AC-to-test coverage for the complete synthetic journey, linked to predecessor RAD obligations, Radiologist AC1–AC10 and Member AC1–AC12 where applicable. Distinguish actual browser/direct/converted proof, real owner integration and mocks; list missing coverage.
3. Precise baseline-versus-candidate full test comparison with machine-readable identities/statuses, new/persistent/resolved failures/errors and skip accounting, not merely aggregate equality.
4. CI/dependency compatibility and public advisory findings, installed/locked drift, coverage limits and a concrete reproducibility/CI recommendation without mutation.
5. G10 blocker matrix: severity with rationale, exact source/test/approval evidence or explicit unknown, owner role (individual unassigned if unknown), exact measurable closure condition and approving authority, dependency, next bounded fix/evidence action and recommended sequence. Do not silently close existing gap IDs. Address urgent exposure/clinical risks first, then dependent verification/runtime/CI fixes and operational approval work as evidence warrants.
6. Explicit readiness disposition: **development-ready** means bounded synthetic development may continue under accepted contracts, not that all tests pass; **integration-ready** requires all mandatory integrated checks to execute successfully with any persistent baseline failures explicitly adjudicated by independent review; **production-blocked** identifies unmet G10 requirements even if development/integration are ready; **release-ready** requires all applicable G10 evidence and designated approvals. Report development/integration status alongside production-blocked where appropriate. This contract grants no release approval, so it cannot independently establish release-ready. Unknown evidence is not clearance.

Each check must be labeled passed, failed, blocked/not run, skipped or mock-only as observed. No mandatory check is passed by substitution. Existing reproducible failures can support a complete assessment with an adverse readiness verdict; missing mandatory execution makes the verification package incomplete and invokes the stop boundary. Findings and proposed fixes do not change accepted application baselines.

## Stop conditions

Before E6, stop after publication REVIEW REQUIRED. After E6 authorization, stop affected work and return PLANNING REQUIRED for a mismatched governing revision/baseline, credible active credential exposure, unsafe fixture/resource identity, required secret/real-data/external access, missing mandatory runtime/browser/MySQL capability, need to install/change dependencies or disable guards, inability to complete mandatory checks safely, new authority/architecture/scope decisions, or any prohibited side effect. Preserve partial truthful evidence and exact resumption condition; independent safe assessment may continue only while it does not depend on or cross the blocker. Expected test failures or documented G10 gaps are assessment findings, not permission to repair them or abandon safe evidence collection. Never weaken clinical-source denial, audit, authorization or parser controls.

## Side-effect authorization

**T5 publication now:** read-only discovery; create the named planning branch from exact Member SHA; write/stage only this task; ordinary task-only commit and push to that non-main branch; fetch and verify local/fetched/live remote equality; update existing external `CHAT_MEMORY.md` preserving historical entries. The current PO explicitly authorizes these routine actions without repeated approval. No application execution, dependency installation, PR, merge, deployment/workflow trigger, production/real-patient operation or permission/credential change.

**Future E6:** independent review and subsequent explicit handoff must activate the bounded synthetic execution/evidence scope, identify the exact published task revision and grant evidence-output/Git permissions. Proposed permitted mutations are owned disposable synthetic resources, temporary isolated harness orchestration, build/test outputs and the evidence package only. No application remediation, dependency/version/config/workflow change or external operational mutation is authorized by assessment. No E6 commit/push authority is borrowed from the task-only T5 permission.

## T5 readiness evidence and terminal outcome

Publication discovery on 2026-10-16 observed a clean Member checkout; fetched/live main matched the supplied main SHA and fetched/live Member matched the candidate; all ancestry edges above passed; main...Member was exactly 0 behind / 18 ahead. All three current predecessor task files matched their immutable governing bytes. Current workflow/scripts, inventory bindings, report reader, clinical-status adapter, AI fallbacks, harnesses, requirements/gaps and prior evidence were inspected. Codebase Memory identified current paths; direct source verifies material claims. Graphify returned unrelated prototype nodes and is not evidence. No graph rebuild, dependency install, test execution, credential retrieval or operational check was needed for publication.

| Readiness concern | Evidence / boundary |
|---|---|
| B0/P1/R2 | Current attributable PO direction defines one assessment and its A–E outputs; unchanged predecessor policies and stable requirement/gap IDs supply bounded traceability. No new product/clinical policy required. |
| A3/D4 | Existing ownership, verifier, response/audit, inventory and synthetic fixture boundaries retained; exact candidate and comparison baseline, ordered checks and actionable outputs defined. |
| T5 contract/publication | Validate template concerns, local links, SHA/task ancestry, sole task delta, whitespace, immutable bytes and local/fetched/live equality. Containing revision must be resolved in handoff. No unresolved material decision blocks publication; independent exact-revision review/E6 hold remains. |
| E6/V7/A9/G10 | No fresh application/runtime/CI verification or acceptance in T5. Mandatory execution and all release obligations remain separate. |

**Publisher terminal outcome: REVIEW REQUIRED.** Return full immutable publication SHA, sole changed-file list, observed T5 checks and blockers; update external checkpoint and stop. Independent Planner–Reviewer reviews that exact publication and promptly issues E6 authorization if supported. Do not emit an active Executor Launcher now.

Future Executor ends REVIEW REQUIRED with the six outputs and truthful readiness verdict, or PLANNING REQUIRED with partial evidence, exact blocker and resumption condition. Independent Reviewer evaluates the exact task/candidate/evidence revisions; bounded contract remediation republishes this stable path, while unrelated fixes return to delivery planning. Neither assessment acceptance nor synthetic A9 authorizes merge, deployment, production publication or G10 release.
