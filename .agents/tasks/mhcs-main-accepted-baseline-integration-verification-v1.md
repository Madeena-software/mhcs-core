---
title: MHCS Main-to-Accepted-Baseline Integration Verification v1
document_id: MHCS-TASK-MAIN-ACCEPTED-INTEGRATION-001
version: 1.0
status: Validated/Published
language: en-US
last_updated: 2026-10-17
authority_note: Independent Planner/Reviewer accepted the exact Draft content; Product Owner explicitly authorizes automatic task-only commit/push on the existing branch. Independent exact-publication T5 and a corresponding independent Executor handoff remain required before E6. No source remediation, PR/merge, deployment or release is authorized.
---

# MHCS Main-to-Accepted-Baseline Integration Verification v1

## Task identity

**Task path:** `.agents/tasks/mhcs-main-accepted-baseline-integration-verification-v1.md`.

**Task contract state:** Validated/Published through the authorized immutable task-only publication. **Governing task revision:** this stable path at the full containing publication commit, resolved and verified in the publication return and external checkpoint. Publication becomes effective only once that exact immutable identity is observed. Independent exact-publication T5 review and the corresponding independent Executor handoff remain mandatory before E6; this publisher does not self-certify T5 or issue an execution launcher.

**Delivery objective / parent:** one MVP-oriented integration-evidence slice across the accepted Admin → Radiographer → Radiologist → Member development sequence and existing Operator/Image Gateway boundaries. Determine readiness for an independently controlled development-integration PR/review process. No Work Package completion or G10 reassessment is implied.

**Owner / designated planning authority:** Product Owner for the supplied objective and synthetic-verification permission; independent Planner/Reviewer for task review, T5, Executor handoff and evidence review. Existing module, security/privacy, clinical and operations/release authorities retain their responsibilities; no individual appointment is inferred.

## Delivery context and objective

Determine whether exact accepted development candidate `d6a8eca29baf565c42292a2d3a8d04f0da44b48b` is sufficiently verified against exact main `f8f0bb9e2e0cd12d1b797cf6a6689f26336274ba` to proceed to authorized development-integration PR review. Produce reproducible integration evidence and a defensible readiness recommendation. An adverse recommendation is a valid complete assessment when all mandatory checks were safely executed and their results truthfully reported. Unavailable mandatory verification cannot be relabeled completed or passed.

Accepted synthetic implementation, integration readiness and production release remain separate. This task does not reopen or self-repeat predecessor V7/A9, create a merge result, authorize a PR/merge, or grant production approval.

## Baseline and task revision

Repository: `Madeena-software/mhcs-core`; authoring worktree: `/var/www/mhcs-core`.

| Identity | Exact immutable revision |
|---|---|
| Main comparison / integration base | `f8f0bb9e2e0cd12d1b797cf6a6689f26336274ba` |
| Accepted development / implementation baseline and candidate under test | `d6a8eca29baf565c42292a2d3a8d04f0da44b48b` |
| Earlier Member candidate assessed historically | `9804642c60b678ca004fd9d69a51c682f352d517` |
| Prior assessment task publication | `c504de35c3c6160244ea34af37aa9c7227c33d02` |
| Prior assessment governing task blob | `c448d3ab1192b77906811f77a64330a6f3b2794f` |
| Prior assessment evidence revision | `cfe110efefdd0884e86e0be21d02f8c2565dd8af` |
| Link-first governing task publication | `641a8b2c420bbcd12b41c5df66766219e270c91b` |
| Link-first governing task blob | `6bbe465b8551fab85d8d486f710c6f6ac808535a` |

Historical Draft-authoring observation, 2026-10-17 Asia/Jakarta: HEAD and local/origin candidate branch `feat/mhcs-admin-issued-task-links-v1` equal the candidate; local/origin main and read-only live remote main equal the comparison SHA. Live remote candidate also matches. Merge base equals exact main; main...candidate is **0 behind / 24 ahead**, **120 changed files: 103 added / 17 modified**, including three added migrations. Initial primary worktree is clean. Other registered worktrees, including stale/prunable registrations, are preserved; no cleanup is authorized. Appendices inventory all changed paths and intervening commits, not additional authoring changes.

The current Product Owner handoff explicitly supplies independent V7/A9 acceptance of the exact candidate for bounded synthetic development. Existing E6 evidence and external checkpoint still end REVIEW REQUIRED because they predate that declaration. Preserve both records and their provenance; do not invent a verdict artifact or reaccept the candidate. Independent review must confirm the applicable acceptance reference/snapshot before publication and execution; the attributable current handoff supplies the acceptance declaration, while the original independent verdict artifact is not supplied. Its absence is a provenance limitation, not an automatic revocation of the supplied acceptance. A material contradiction requires authority/evidence closure.

**Draft provenance and publication-start observation, 2026-10-17 Asia/Jakarta:** independently reviewed Draft commit `f3afea50b384b1751c9706464fbaf1ecafe1f0ba`, task blob `d1ae4c70d14edcc40ee085a38d672d7c84afd782`, is a one-file addition directly above accepted application candidate `d6a8eca29baf565c42292a2d3a8d04f0da44b48b`. Clean local HEAD, origin tracking and live remote branch match that Draft commit; local task bytes match its reviewed blob. The current **“MHCS — AUTOMATIC TASK PUBLICATION HANDOFF”** supplies independent acceptance of B0/P1/R2/A3/D4, AC1–AC10, the exact comparison and complete inventories, plus explicit Product Owner authorization for one ordinary task-only publication commit/push on `feat/mhcs-admin-issued-task-links-v1`. This resolves the earlier Draft-review/publication-permission holds only; exact-publication T5 and E6 remain pending. The 120 paths/24 commits describe main-to-application-candidate scope, excluding the later Draft and publication overlays.

At E6, record actual execution-start HEAD, branch, worktree changes, exact task publication and tested tree identities separately. Any later task-only publication is a documentation overlay; neither it nor a moving branch replaces either test SHA. Material source/authority drift requires planning reconciliation before dependent execution.

## Authoritative inputs

### Governing authority

- Direct Product Owner **“MHCS — INTEGRATION VERIFICATION TASK AUTHORING HANDOFF”**, supplied 2026-10-17 Asia/Jakarta: sections 1–8, exact SHAs, accepted development scope, A–F verification obligations, isolation and evidence boundaries. Its original Draft-only stop is historical and superseded only for publication by **“MHCS — AUTOMATIC TASK PUBLICATION HANDOFF”**: independent acceptance of exact Draft commit/blob above and explicit automatic task-only commit/push authority, followed by independent exact-publication T5 and a separately controlled E6 handoff. Neither instruction approves the historical business draft or operational use.
- [Root adapter](../../AGENTS.md), [repository contract](../AGENTS.md), [normative workflow](../software-workflow.md), [planning/review procedure](../prompts/plan-create-task.md), [task template](_template.md), and [project context](../context/project.md). Context is orientation and contains older delivery-state claims; current verified identities and applicable approved authority control.
- External `faliqadlan/agent-work-governance/Planner-Reviewer Contract.md`, exact Git blob `39adb286f6b28229872898389ce3c7a05f0151a0`. Existing external cached bytes at `/home/faliq/.codex/task-state/mhcs-core/admin-task-links-v1/governance-contract.md` were rehashed to that blob during authoring. Use those exact bytes or retrieve that immutable blob read-only; the mutable branch URL is not its identity. It grants no additional side effects.
- Immutable predecessor contracts: [Radiographer](admin-issued-radiographer-workspace.md) @ `25d396e576ee513bee1a7e2aa2db57a65afdd666`; [Radiologist](admin-assigned-radiologist-workspace-v1.md) @ `c8886a8ee0c7f11edae5f888fe42e79b86ef5607`; [Member](admin-authorized-member-result-access-v1.md) @ `3ea425531f4836af38b08b06acd7ef3728b1e00c`; [link-first task](admin-managed-staff-onboarding-lifecycle-v1.md) @ `641a8b2c420bbcd12b41c5df66766219e270c91b`. Preserve their scoped policies and exclusions; deferred staff-onboarding D1–D8/STAFF scope is not resurrected.
- [MVP decisions](../../docs/mvp/decision-log.md), [active scope](../../docs/mvp/beta-scope.md), [gap register](../../docs/mvp/beta-gap-register.md), [requirements](../../docs/implementation/mhcs-core-requirements-matrix.md) and [source coverage](../../docs/implementation/mhcs-core-source-coverage.md), only as materially relevant to predecessor traceability and detected authority conflicts. Do not rewrite approved records or treat implementation as retroactive requirements authority.

### Existing assessment and evidence to reuse

- [Prior assessment task](mhcs-integrated-preflight-release-readiness-v1.md) at the exact publication/blob above; [its evidence](../../docs/mvp/evidence/mhcs-integrated-preflight-release-readiness-v1.md) at `cfe110efefdd0884e86e0be21d02f8c2565dd8af`; [latest link-first evidence](../../docs/mvp/evidence/admin-task-links-v1-e6.md) at the accepted candidate.
- Relevant predecessor R8/E6 evidence linked from those records; external `/home/faliq/.codex/task-state/mhcs-core/CHAT_MEMORY.md` and minimized `integrated-preflight-v1/` and `admin-task-links-v1/` artifacts. These are historical results and discovery aids, not fresh checks against main and this candidate.
- Reuse inspected isolation, exact-source packaging, commands, explicit test selectors, comparison formats and browser/race harnesses. The old immutable task tested Member against Radiologist; it does not authorize substituting this main/candidate pair. Do not overwrite old assessment evidence or repeat its broad G10 analysis merely to create documents.

### Requirement traceability

These assessment obligations preserve existing IDs; no new product behavior is approved.

| Source / retained coverage | Obligation and responsible boundary | Acceptance |
|---|---|---|
| Current PO §5 A; protocol authority/revision rules | Immutable refs, commit/path inventory, predecessor task and acceptance provenance; Executor + independent Reviewer | AC1 |
| Current PO §5 B; protocol E6 evidence rules | Equivalent complete main/candidate regression and per-identity attribution; Executor | AC2–AC3 |
| Current PO §5 C; Radiographer RAD-URL-001–008/W1–W5.1, Radiologist B1–B3/AC1–AC10, Member AC1–AC12 | Owner bindings, security, clinical lifecycle and independent disclosure; Admin/Operator/Doctor/Member/Image Gateway | AC4–AC5 |
| Current PO §5 D; predecessor persistence/audit/disclosure contracts | Disposable two-backend migration, constraints, lifecycle, atomicity, independent races and runtime/build compatibility | AC6–AC7 |
| Current PO §5 E; link-first LINK-001–007/AC1–AC10 | Copied-link individual authentication, continuous synthetic journey, browser denials and exact-ref CI/static deployment coverage | AC5, AC8 |
| Current PO §5 F, §§6–8; protocol T5/E6/V7/A9/G10 | Complete evidence package, impact-based verdict, isolated execution and independent review without release/merge authority | AC9–AC10 |

## Scope

### In scope after publication, independent T5 and Executor handoff

Read-only analysis of exact source/history/authority and available exact-ref CI status; complete equivalent PHP regression on both revisions; focused affected-module/security/architecture checks; candidate cross-module integration and actual synthetic browser journey; disposable SQLite and MySQL 8.4 migration/constraint/lifecycle/audit/race checks; frontend/DICOM bundle/runtime compatibility; static deployment validation; external evidence and recommendation. Inspect additional relevant paths as needed for this single outcome without changing them.

### Out of scope

Application, tests, fixtures, migrations, dependencies, locks, workflow, deployment configuration, permissions or operational documentation changes; source remediation; new staff provisioning/credentials/eligibility policy; new clinical-source inventory; operational migration/rollback; whole-repository security audit or repeated G10 exercise. During E6, no commit, push, PR, merge, rebase/history rewrite, branch publication, workflow dispatch, deployment, release, external notifications, dependency install/replacement or arbitrary execution outside the inspected test environment. The separate task-only publication exception below does not expand E6 permissions. No production/operational DB, real patient/professional identity, operational `.env`, private storage, real credentials or live services.

### Preserved behavior and security invariants

- Admin coordinates through owner contracts; Member retains identity/recipient authority, Operator technical QC/handoff, Doctor authored clinical decisions/final reports, and Image Gateway private original imaging/provenance. Validate boundaries without transferring ownership.
- Signed URLs remain entry locators. Individual authentication, account/session/credential validity, professional eligibility and scope, exact current assignment/generation, expiry/revocation and current case/source authorization remain independent. No blanket grants or authentication/audit/parser/container bypass.
- Technical QC → handoff → authored finalization → independent exact-version Member publication. No implicit publication, draft/AI substitution, amendment, repeat dispatch or guardian expansion. Preserve immutable original bytes/digests, reports, append-only history and attributable audit.
- Fail closed on stale/prepared disclosures, manager changes, identity/eligibility/lineage loss and audit append/commit failure. Preserve existing transaction/lock order and disclosure-start checks; no lock across client I/O or claim that delivered bytes can be recalled.
- Application-default `UnknownClinicalSourceInventory` denial and `WorkspaceClinicalStatusSource::complete_sources=false` remain. Positive completeness belongs only to the existing explicitly closed synthetic fixture, never a replacement production binding or inferred historical clinical inventory.
- Preserve unrelated work, existing branches/worktrees, previously accepted scope and historical evidence. Retain no secrets, signed locators, session values, raw protected requests or actual patient data in artifacts/screenshots/tool output.

## Dependencies, assumptions and remaining approvals

**Dependencies:** exact local Git objects and authority/task bytes; confirmed predecessor acceptance provenance; already installed verified PHP/Node/Composer/npm dependencies, bounded-parser-capable runtime, browser/WebGL and disposable SQLite/MySQL 8.4 support. Historical supported application image `sha256:380f70b20e323a710e39516a4b5ac8edd273b26d96cdadfe4aa2fb8f4ce9adcc` and MySQL image `sha256:da906917ca4ace3ba55538b7c2ee97a9bc865ef14a4b6920b021f0249d603f3d` are reuse leads, not asserted current runtime identities. Inspect installed resources afresh. Availability is unverified in this authoring step.

**Approved assumptions:** supplied acceptance is bounded synthetic development only; historical failures, opt-in skips, flake, dependency/runtime gaps and G10 blockers remain investigation inputs. No suite-green, integration-ready, dependency parity, fresh CI or production-safe assumption is approved.

**Remaining approvals:** the current handoff supplies independent acceptance of the exact Draft and explicit Product Owner publication permission; verify the resulting immutable publication identity, then obtain independent exact-publication T5 and the corresponding independent Executor handoff activating the approved isolated verification scope. Preserve the attributable acceptance provenance above. Later independent evidence review determines readiness. Any limitation waiver needs its designated independent authority; source remediation, PR/merge and every production/release action require separate authorization. No unresolved product decision is identified for this publication; the independent T5/E6 gates remain outstanding.

## Required capabilities and execution safety

Required after E6 handoff: read-only Git/source and permitted CI metadata access, inspected isolated test/container/browser execution using existing dependencies, disposable DBs and external artifact writing. Graphify/document and Codebase Memory/implementation discovery are supporting aids; verify material claims against exact source. No index rebuild or new infrastructure is required for this task.

Before running any entrypoint, inspect its code, bootstrap, environment overrides, mounted paths, network destinations, setup/teardown, destructive operations and actual runtime/dependency resolution. In particular, the race fixtures can override DB configuration to host `mysql`, DB `mhcs_rad_synthetic`, run `migrate:fresh`, disable foreign-key checks during disposable cleanup and delete test rows. A testing environment name or hardcoded DB name alone does not prove isolation. Resolve them only inside resources positively owned by this attempt; otherwise stop.

Use separated exact-source copies with native tracked paths/modes (including export-ignored `.github` inputs), separate caches/storage/object/session/DB state and each revision's own built assets. Exclude `.env`, private/operational storage and credential configuration. Keep tracked source bytes immutable; run only inspected entrypoints with generated outputs in disposable writable locations. Mount installed dependencies read-only and verify Composer classmaps/Node paths resolve the intended copy, not the live worktree or other revision. Record installed-versus-locked/platform parity for each revision; incompatible installed dependencies are a limitation/blocker, not permission to install.

Use existing container/parser isolation, least privilege and no Docker socket inside test containers. Disable network for application/build/full-suite checks where compatible; allow DB only on an owned internal test network with no exposed DB port. Browser application access must be localhost-only with other origins blocked. No operational endpoint is permitted. Read-only CI metadata access is separate from the test network. Inspect build scripts before invocation; no setup/install lifecycle or hidden download. Resource/path/port-only launcher adaptations and evidence parsers may live externally; record their bytes/digests and commands, preserve assertions, and run test code only in the inspected environment. Missing assertion coverage is a reported gap, not authority to edit tests.

Dispose only of resources created and positively identified as owned by this attempt. Do not reuse historical cleanup names, delete shared volumes/listeners/worktrees or prune stale registrations. Retain minimized diagnostic logs, comparisons and synthetic screenshots only under `/home/faliq/.codex/task-state/mhcs-core/main-accepted-baseline-integration-v1/`; preserve earlier artifact directories. No repository evidence file is authorized by this task.

## Acceptance criteria

Assessment acceptance concerns evidence completeness and truth, not a predetermined favorable verdict. Every mandatory check below must execute safely for a complete recommendation; failures remain failures. If capability or authority prevents execution, preserve partial evidence and return the blocked/planning outcome.

| ID | Observable mandatory condition / evidence |
|---|---|
| AC1 | Reverify both exact SHAs/trees, merge base/ancestry, all 24 commits and the complete 120-path A/M inventory with ownership/risk, including all three migrations. Reconcile task publication/blobs, accepted revisions and supplied independent V7/A9 provenance. Record authority discrepancies without rewriting records. Explain any count mismatch and stop dependent work if the premise changed. |
| AC2 | Execute the full configured PHP suite separately on exact main and candidate in equivalent isolated environments. Record actual commands/selectors, versions, source manifests, exits/signals, tests/assertions/failures/errors/skips, discovery and complete JUnit. A closed report/footer does not prove a clean process exit. |
| AC3 | Compare full test identities, including dataset variants, not totals. Partition common identities into unchanged/newly failing/resolved/other changed states; separately list added/removed identities and new tests. Reconcile all default opt-in skips to explicit runs or explicit gaps. Investigate the historical 25 failure/error identities and Operator MRN assertion flake without automatic waiver or erasing initial outcomes through reruns. |
| AC4 | Inspect and exercise affected routes, service-provider/contract bindings and focused Admin/Operator/Doctor/Member/Image Gateway, architecture and security regressions. Cover authorization, professional eligibility, scope/session, signed entry/intended return, current assignment, revocation/reassignment/expiry, fresh finalization credentials, immutable originals/reports, protected prepared responses and durable audit. Identify actual source-backed synthetic integration versus mock-only evidence. |
| AC5 | Execute continuous actual Admin → copied Radiographer link → individual login/QC/handoff → copied Radiologist link → individual login/original render/authored finalization → separate Admin publication → verified Member final-only access, for direct and signed-converted synthetic originals. Verify actual nonuniform rendering/digests, clipboard readback and denied/unavailable clipboard fallback, keyboard/recovery states, no automatic publication, and named stale/wrong-recipient/identity/eligibility/audit/revoked/expired/lineage denials. Map PHP/race versus browser coverage explicitly. |
| AC6 | Execute all three additive migrations up/down/up on disposable SQLite and MySQL 8.4, including upgrade from synthetic main schema, unique constraints, surviving main/upstream lifecycle/history/object preservation and reproducible reapplication. Execute independent-connection race/audit-atomicity suites with zero silent opt-in skips; document synchronization/committed invalidations, lock ordering and audit/commit rollback evidence. SQLite WAL/integrity/FK checks and MySQL version/isolation/schema results are explicit. Dropped slice-owned tables lose their data by design; no operational rollback claim. |
| AC7 | Execute affected safe JS, frontend builds/DICOM bundle checks, bounded-parser runtime constraints and relevant syntax/format/deployment-policy tests using installed dependencies. Build both refs with their own compatible inputs. Report source/runtime/Docker compatibility and installed/locked/platform gaps; no weakened sandbox or installed dependency. |
| AC8 | Inspect existing CI/deployment validation statically and query available checks/statuses/workflow runs by exact ref read-only. Report targeting/triggers, coverage and runtime/dependency parity gaps; unavailable/absent CI is explicit and local results remain local. Classify failed static policy predicates without triggering Docker deployment validation, image pulls/builds, CI workflows or operational smoke tests. |
| AC9 | Deliver externally retained report, AC-to-check matrix, source/commit/path inventory, machine-readable per-test/skip comparison, commands/results/diagnostic manifest and impact-based findings/limitations with owner role, evidence, closure condition and required independent approval. Reference existing G10 findings only where relevant to integration impact; do not silently close or recreate them. |
| AC10 | Recommend exactly one of the four integration dispositions below, tied to mandatory evidence and blockers. Return exact task/main/candidate/start/output identities, full repository change inventory and owned-resource cleanup result for independent review. Preserve previous acceptance and separate G10; no self-approved T5, V7/A9, PR/merge or release. |

## Verification requirements

### Ordered checks and reuse

1. **Identity/authority/safety first:** pin publication/task blob and both tested refs; verify predecessor publication and acceptance references. Record HEAD/worktrees and actual refs without fetching/replacing test candidates. Read the inspected harnesses/configuration and demonstrate resource/network/mount/cleanup ownership before executing. Hash source manifests before and after checks. Material drift or unsafe environment stops dependent execution.
2. **Inventory/bindings:** reconcile appendices to exact `git diff --name-status` and commit range. Inspect routes, provider registration, owner interfaces and additive migration ordering/constraints. Verify that candidate changes to instructions/architecture/security contracts do not self-authorize their own acceptance; compare applicable accepted governing authority.
3. **Equivalent full PHP comparison:** run installed `vendor/bin/phpunit --colors=never --log-junit <owned-output>` for each exact ref, using inspected synthetic configuration and the same compatible dependencies/runtime. Run serially where shared dependencies/resources can interfere. Preserve complete results, process exits and full dataset identities. Reconcile discovery, added/removed tests, status changes and infrastructure failures. Targeted flake/attribution reruns are supplemental and retain original results.
4. **Focused integration:** use existing candidate selectors `tests/Feature/Admin`, relevant `tests/Feature/Operator`, `tests/Member`, `tests/Feature/Member`, `tests/Feature/Radiographer`, `tests/Feature/Doctor`, `tests/Unit/Doctor`, `tests/ImageGateway/BoundedDicomVerifierTest.php`, `tests/Architecture` and `tests/Security`, after inspecting actual entrypoints. Include the task-link management delta. Map these checks to predecessor/LINK criteria and owner routes/providers; run applicable common regressions on main for attribution. Mocked imaging-source commands do not prove Gateway preparation/rendering.
5. **Two-backend lifecycle and races:** inspect and explicitly run `tests/Integration/MemberResultConcurrencyTest.php` with `MHCS_MEMBER_BACKEND=sqlite`, then separately `MHCS_MEMBER_BACKEND=mysql`; run both `RadiographerConcurrencyTest.php` and `RadiologistConcurrencyTest.php` with `MHCS_RAD_MYSQL_TESTS=1` only after proving disposable `mysql` identity. Include the four link-first prepared-management cases. Account for every full-suite skip, run other applicable existing opt-ins explicitly, and report unrelated/unavailable skips separately. Default skipping or `deployment/verify-mysql.sh` alone is not concurrency proof. Exercise actual independent processes/connections/barriers and audit failures, plus all three candidate migrations against isolated synthetic main schema; protect surviving state and original hashes.
6. **Actual browser:** inspect/reuse `tests/Browser/admin-task-links.mjs`, `member-result-access.mjs`, `radiologist-workspace.mjs`, `radiographer-workspace.mjs` and their synthetic bootstraps. Use the continuous task-link harness and supplemental existing assertions for missing QC/repeat/denial coverage without duplicating equivalent runs. Preserve actual Gateway bounded parsing and separate sessions; label closed synthetic inventory and mocks. Verify application-default unknown-source denial separately. Store only minimized synthetic viewport screenshots and sanitized observations, never locators/browser chrome/session values. Synthetic 2×2 rendering is not clinical certification or live conversion proof.
7. **Build/runtime/static:** use inspected explicit safe JS selectors, including candidate `tests/JavaScript/operator-dicom-viewer.test.mjs` and `tests/JavaScript/radiologist-dicom-modality.test.mjs`, plus each ref's installed frontend build and DICOM bundle script. No broad JS/browser glob: prior history includes a live-production-login entrypoint. Record bounded verifier runtime/seccomp constraints; inspect Dockerfile compatibility statically. Use installed PHP lint/Pint in check mode and relevant architecture/deployment-policy tests, distinguishing pre-existing outcomes. If npm is unavailable but the same installed package script commands can run safely, record that exact equivalent and limitation. Do not invoke the whole deployment validator: it can build/smoke-test images; extract only inspected safe static predicates and report omitted runtime checks.
8. **CI/parity and synthesis:** read exact-ref available status and workflow metadata without dispatch/download execution. Inspect coverage of JS/browser/new races, PHP/Node/extensions/util-linux/MySQL requirements and ref selection. Compare installed packages/platform with each lockfile without setup/install/update or credential reads. Carry prior dependency/security/clinical/operational findings by exact evidence reference and assess only their integration impact. A new finding gets evidence and escalation, never source remediation under this task.

### Historical results are investigation inputs

The old preflight compared Member `9804642...` against Radiologist `cd5128f...`, not main. Latest link-first evidence compared candidate against Member: 1,060 versus 1,023 tests, 25 persistent failure/error identities, 61 versus 57 default skips, and a completed candidate JUnit/footer with surrounding shell later returning 143. These counts must not be projected onto main. Prior 25 identities included WebP/Mpdf/Python-related outcomes; the prior Operator `assertDontSee('120')` MRN collision remains a flake lead. Use historical named identities to attribute fresh results, not excuse them. Preserve original failures and any incomplete exit evidence. Prior browser/MySQL passes are useful harness evidence, not fresh results for this task.

### Required external evidence

Under the named task-state directory, retain one concise report linking machine-readable full identities/statuses/datasets, skip mapping, command ledger with observed exit/signal/counts, source hashes/modes, runtime/dependency/image/backend identity, complete inventory and sanitized diagnostic artifacts. For each AC/check use **passed**, **failed**, **blocked/not run**, **skipped** or **mock-only** as actually observed. Record assertion counts where provided; otherwise explicitly unavailable. Include setup failures and superseded runs with reasons; do not select only favorable evidence. No repository evidence entrypoint or task self-certification is permitted.

Classify findings by actual integration effect, with owner role, evidence, severity rationale, dependency, measurable closure and approving authority. Existing failures are not automatically non-blocking because they occur on main. A ready recommendation requires executed mandatory boundaries to pass, no unresolved integration-blocking security/data/clinical/authority defect, and independent adjudication of retained limitations. Do not infer clinical-source completeness, credential rotation or production privacy/operational approval. If a required proof needs prohibited access, record unknown and escalate without reading secrets or operating live systems.

## Integration verdict and terminal outcomes

Recommend exactly one:

1. **Ready for authorized development-integration PR review:** all mandatory checks executed; integration boundaries pass; no unresolved blocker or unapproved limitation. This recommendation creates no PR or merge permission.
2. **Ready with specifically documented non-blocking limitations requiring independent approval:** mandatory checks executed and critical integration boundaries pass; each retained limitation has evidence, impact, owner/closure and designated approval pending. This is conditional, not a waiver. Independent Reviewer decides whether it can be accepted.
3. **Remediation/evidence closure required:** all mandatory checks executed, but observed failures, regressions or authority conflicts preclude a favorable integration recommendation. A complete adverse report may be REVIEW REQUIRED; any source fix needs a separately authorized governing task.
4. **Blocked due to unavailable mandatory verification, unsafe environment or unresolved authority:** preserve partial results and return PLANNING REQUIRED, exact missing check/authority/capability and resumption condition. Do not claim complete E6 solely from documenting missing mandatory execution.

**Publisher terminal: VALIDATED TASK PUBLISHED — INDEPENDENT EXACT-PUBLICATION T5 REVIEW REQUIRED.** Return the full publication commit and task blob, path, branch, starting state, exact one-file publication delta, document/link/inventory/ref/diff checks, local/fetched/tracking/live equality, content equality, final worktree status, deviations and remaining limitations; update external checkpoint preserving history and stop. No self-approved T5, execution launcher or tests.

**Future Executor terminal:** REVIEW REQUIRED with complete external evidence and a recommendation, or PLANNING REQUIRED with partial evidence and a valid stop. Independent Reviewer evaluates exact task/main/candidate/evidence identities, accepts or requires bounded evidence/contract correction, and controls further handoff. This verification does not change the accepted application baseline or grant PR/merge/G10 authorization. Contract amendments use this stable path and a new authorized immutable publication before renewed execution.

## Stop conditions

Stop dependent/affected work for wrong or unresolved governing revision, material baseline/authority drift, unverified acceptance provenance, unsafe resource or endpoint ownership, need for operational/secret/patient/professional data, missing mandatory supported runtime/DB/browser capability, dependency installation/replacement, altered assertions/source/configuration, weakened authentication/audit/seccomp/container restrictions, required scope/product/architecture decision, or any prohibited side effect. Report exact evidence, owner and resumption condition. Unrelated safe collection may continue only within this contract and without crossing the blocker. Ordinary executed test failures are findings to finish attributing, not authority to fix code or discard adverse results. Credible new metadata indicating active credential exposure requires prompt escalation to the user/security authority without values or external notification.

## Side-effect authorization

**Task publication now:** current Product Owner explicitly authorizes read-only preflight; publication-only changes to this existing task; stage only this path; one ordinary task-only commit and push on existing non-main branch `feat/mhcs-admin-issued-task-links-v1`; fetch and verify exact local/FETCH_HEAD/tracking/live equality, published task bytes/blob, one-file boundary and clean worktree; external `CHAT_MEMORY.md` update preserving history. No repeated commit/push confirmation is required within this scope. No new branch/worktree, PR, merge, force-push/history rewrite, application-source change, test/build/migration/browser execution, dependencies, deployment or release. Preserve unrelated work; stop for material starting-state conflict.

**Conditional future E6:** Product Owner approves isolated synthetic verification, ephemeral resources positively owned by this attempt, permitted read-only analysis and external evidence, activated only by validated T5 and a corresponding independent Executor handoff. Limited external launchers/parsers and disposable generated outputs are for executing inspected existing tests, not new product/test behavior. No Git write/publication, repository source/evidence change, installation, operational access or release action. The task-publication commit/push permission above is confined to publication and does not carry into E6; do not borrow predecessor permissions.

## Authoring provenance and publication verification

Authoring used read-only exact Git identities/live refs, history and diff; verified the prior assessment and link-first task blobs; consulted the hash-verified external governance cache; read predecessor evidence and canonical contract/template. Graphify query returned no matching nodes and was not relied upon or rebuilt. Codebase Memory located existing race helpers; direct fixture reads confirmed opt-in DB overrides and destructive disposable setup. No runtime capability/pass was inferred.

Before publication, verify version 1.0 / Validated/Published metadata, stable task identity, required template concerns, all referenced local paths, both full test SHAs, exact inventory/commit coverage, whitespace and the sole task-file delta. Compare the complete diff to the independently accepted Draft: objective, AC1–AC10, appendices, security constraints and execution/evidence boundaries must remain substantively unchanged. After ordinary commit/push, fetch and verify local publication HEAD, FETCH_HEAD, origin tracking and live branch equality; exact committed task bytes/blob; parent equal to the reviewed Draft; sole changed file; no application-source modification; clean final tree; and external checkpoint preservation. These are publication checks, not independent T5 or integration checks. The independent Reviewer must inspect that exact immutable publication and complete T5 before issuing the separate bounded Executor handoff.

## Appendix A — Complete integration changed-file inventory

Observed exact main → accepted candidate diff; **120 paths, 103 A / 17 M**. A = added, M = modified. Risk labels identify verification priorities, not independently validated defects or permission to edit. Tests/fixtures may exercise high-risk boundaries; evidence/contracts require provenance review. Reverify at E6.

| Architecture / ownership group | Paths |
|---|---:|
| Delivery contracts | 5 |
| Runtime/deployment | 3 |
| HTTP / shared authentication | 5 |
| Doctor | 17 |
| Image Gateway | 6 |
| Member | 9 |
| Operator | 10 |
| Module-owned persistence | 3 |
| Historical evidence | 11 |
| Imaging presentation | 1 |
| Admin / owner presentation | 14 |
| HTTP routing | 1 |
| Module / architecture regression | 12 |
| Synthetic browser verification | 4 |
| Cross-module race verification | 3 |
| Synthetic test support | 16 |

| Change | Path | Ownership / boundary | Integration risk |
|---|---|---|---|
| A | `.agents/tasks/admin-assigned-radiologist-workspace-v1.md` | Delivery contracts | Authority/task provenance; review impact |
| A | `.agents/tasks/admin-authorized-member-result-access-v1.md` | Delivery contracts | Authority/task provenance; review impact |
| A | `.agents/tasks/admin-issued-radiographer-workspace.md` | Delivery contracts | Authority/task provenance; review impact |
| A | `.agents/tasks/admin-managed-staff-onboarding-lifecycle-v1.md` | Delivery contracts | Authority/task provenance; review impact |
| A | `.agents/tasks/mhcs-integrated-preflight-release-readiness-v1.md` | Delivery contracts | Authority/task provenance; review impact |
| M | `Dockerfile` | Runtime/deployment | Parser/runtime compatibility; high |
| M | `app/Http/Controllers/Member/AuthenticationController.php` | HTTP / shared authentication | Session, signed entry, response disclosure; high |
| A | `app/Http/Controllers/Member/ResultController.php` | HTTP / shared authentication | Session, signed entry, response disclosure; high |
| A | `app/Http/Controllers/Radiographer/WorkspaceController.php` | HTTP / shared authentication | Session, signed entry, response disclosure; high |
| A | `app/Http/Controllers/Radiographer/WorkspaceResponse.php` | HTTP / shared authentication | Session, signed entry, response disclosure; high |
| A | `app/Http/Controllers/Radiologist/WorkspaceController.php` | HTTP / shared authentication | Session, signed entry, response disclosure; high |
| A | `app/Modules/Doctor/Application/Contracts/ClinicalReopenGuard.php` | Doctor | Eligibility, clinical finalization, audit; high |
| A | `app/Modules/Doctor/Application/Contracts/ClinicalSourceInventory.php` | Doctor | Eligibility, clinical finalization, audit; high |
| A | `app/Modules/Doctor/Application/Contracts/ClinicalStatusSource.php` | Doctor | Eligibility, clinical finalization, audit; high |
| A | `app/Modules/Doctor/Application/Contracts/FinalReport.php` | Doctor | Eligibility, clinical finalization, audit; high |
| M | `app/Modules/Doctor/Application/Contracts/README.md` | Doctor | Eligibility, clinical finalization, audit; high |
| A | `app/Modules/Doctor/Application/Contracts/RadiologistAccess.php` | Doctor | Eligibility, clinical finalization, audit; high |
| A | `app/Modules/Doctor/Application/Services/ClinicalFinalizationGuard.php` | Doctor | Eligibility, clinical finalization, audit; high |
| A | `app/Modules/Doctor/Application/Services/FinalReportRead.php` | Doctor | Eligibility, clinical finalization, audit; high |
| A | `app/Modules/Doctor/Application/Services/RadiologistLoginAccess.php` | Doctor | Eligibility, clinical finalization, audit; high |
| A | `app/Modules/Doctor/Application/Services/RadiologistWorkspace.php` | Doctor | Eligibility, clinical finalization, audit; high |
| M | `app/Modules/Doctor/DoctorServiceProvider.php` | Doctor | Eligibility, clinical finalization, audit; high |
| M | `app/Modules/Doctor/Domain/README.md` | Doctor | Eligibility, clinical finalization, audit; high |
| M | `app/Modules/Doctor/Infrastructure/README.md` | Doctor | Eligibility, clinical finalization, audit; high |
| A | `app/Modules/Doctor/Infrastructure/UnavailableClinicalStatusSource.php` | Doctor | Eligibility, clinical finalization, audit; high |
| A | `app/Modules/Doctor/Infrastructure/UnknownClinicalSourceInventory.php` | Doctor | Eligibility, clinical finalization, audit; high |
| A | `app/Modules/Doctor/Infrastructure/WorkspaceClinicalStatusSource.php` | Doctor | Eligibility, clinical finalization, audit; high |
| M | `app/Modules/Doctor/README.md` | Doctor | Eligibility, clinical finalization, audit; high |
| A | `app/Modules/ImageGateway/Application/Contracts/RadiographerStudySource.php` | Image Gateway | Original integrity, bounded parsing, private reads; high |
| A | `app/Modules/ImageGateway/Application/Contracts/RadiologistStudySource.php` | Image Gateway | Original integrity, bounded parsing, private reads; high |
| A | `app/Modules/ImageGateway/Application/Services/RadiographerStudyRead.php` | Image Gateway | Original integrity, bounded parsing, private reads; high |
| M | `app/Modules/ImageGateway/ImageGatewayServiceProvider.php` | Image Gateway | Original integrity, bounded parsing, private reads; high |
| A | `app/Modules/ImageGateway/Infrastructure/Dicom/BoundedDicomVerifier.php` | Image Gateway | Original integrity, bounded parsing, private reads; high |
| A | `app/Modules/ImageGateway/Infrastructure/Dicom/verify.cjs` | Image Gateway | Original integrity, bounded parsing, private reads; high |
| A | `app/Modules/Member/Application/Contracts/InteractiveRadiographerAccess.php` | Member | Identity, independent publication, recipient disclosure; high |
| A | `app/Modules/Member/Application/Contracts/InteractiveRadiologistAccess.php` | Member | Identity, independent publication, recipient disclosure; high |
| A | `app/Modules/Member/Application/Contracts/RadiographerCaseIdentity.php` | Member | Identity, independent publication, recipient disclosure; high |
| A | `app/Modules/Member/Application/Contracts/RadiologistCaseIdentity.php` | Member | Identity, independent publication, recipient disclosure; high |
| A | `app/Modules/Member/Application/Contracts/ResultPublication.php` | Member | Identity, independent publication, recipient disclosure; high |
| M | `app/Modules/Member/Application/Services/InteractiveMemberLoginService.php` | Member | Identity, independent publication, recipient disclosure; high |
| A | `app/Modules/Member/Application/Services/MemberResultAccess.php` | Member | Identity, independent publication, recipient disclosure; high |
| A | `app/Modules/Member/Application/Services/RadiographerCaseIdentityRead.php` | Member | Identity, independent publication, recipient disclosure; high |
| M | `app/Modules/Member/MemberServiceProvider.php` | Member | Identity, independent publication, recipient disclosure; high |
| A | `app/Modules/Operator/Application/Contracts/PendingRadiographerHandoff.php` | Operator | Assignment, technical QC/handoff, audit; high |
| A | `app/Modules/Operator/Application/Contracts/RadiographerAccess.php` | Operator | Assignment, technical QC/handoff, audit; high |
| A | `app/Modules/Operator/Application/Contracts/RadiologistHandoff.php` | Operator | Assignment, technical QC/handoff, audit; high |
| A | `app/Modules/Operator/Application/Contracts/ResultHandoff.php` | Operator | Assignment, technical QC/handoff, audit; high |
| A | `app/Modules/Operator/Application/Services/PendingRadiographerHandoffRead.php` | Operator | Assignment, technical QC/handoff, audit; high |
| A | `app/Modules/Operator/Application/Services/RadiographerLoginAccess.php` | Operator | Assignment, technical QC/handoff, audit; high |
| A | `app/Modules/Operator/Application/Services/RadiographerWorkspace.php` | Operator | Assignment, technical QC/handoff, audit; high |
| A | `app/Modules/Operator/Application/Services/RadiologistHandoffRead.php` | Operator | Assignment, technical QC/handoff, audit; high |
| A | `app/Modules/Operator/Application/Services/ResultHandoffRead.php` | Operator | Assignment, technical QC/handoff, audit; high |
| M | `app/Modules/Operator/OperatorServiceProvider.php` | Operator | Assignment, technical QC/handoff, audit; high |
| A | `database/migrations/2026_10_15_000001_create_radiographer_workspace_tables.php` | Module-owned persistence | Additive schema, constraints, lifecycle; high |
| A | `database/migrations/2026_10_16_000001_create_radiologist_workspace_tables.php` | Module-owned persistence | Additive schema, constraints, lifecycle; high |
| A | `database/migrations/2026_10_16_000002_create_member_result_publication_tables.php` | Module-owned persistence | Additive schema, constraints, lifecycle; high |
| A | `docs/mvp/evidence/admin-task-links-v1-e6.md` | Historical evidence | Provenance and bounded claims; review impact |
| A | `docs/mvp/evidence/artifacts/radiographer-workspace/radiographer-converted.png` | Historical evidence | Provenance and bounded claims; review impact |
| A | `docs/mvp/evidence/artifacts/radiographer-workspace/radiographer-direct.png` | Historical evidence | Provenance and bounded claims; review impact |
| A | `docs/mvp/evidence/artifacts/radiographer-workspace/radiographer-supplied.png` | Historical evidence | Provenance and bounded claims; review impact |
| A | `docs/mvp/evidence/member-result-access-v1-e6.md` | Historical evidence | Provenance and bounded claims; review impact |
| A | `docs/mvp/evidence/mhcs-integrated-preflight-release-readiness-v1.md` | Historical evidence | Provenance and bounded claims; review impact |
| A | `docs/mvp/evidence/radiographer-workspace-checkpoint0.md` | Historical evidence | Provenance and bounded claims; review impact |
| A | `docs/mvp/evidence/radiographer-workspace-execution.md` | Historical evidence | Provenance and bounded claims; review impact |
| A | `docs/mvp/evidence/radiographer-workspace-r8-remediation.md` | Historical evidence | Provenance and bounded claims; review impact |
| A | `docs/mvp/evidence/radiologist-workspace-v1-e6.md` | Historical evidence | Provenance and bounded claims; review impact |
| A | `docs/mvp/evidence/radiologist-workspace-v1-r8.md` | Historical evidence | Provenance and bounded claims; review impact |
| M | `resources/js/operator-dicom-viewer.js` | Imaging presentation | Original rendering/modality compatibility; high |
| A | `resources/views/components/task-access-status.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| A | `resources/views/components/task-link.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| M | `resources/views/filament/pages/admin-centralized-study-worklist.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| A | `resources/views/member/results/manage.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| A | `resources/views/member/results/report.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| A | `resources/views/member/results/unavailable.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| M | `resources/views/operator/study.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| A | `resources/views/radiographer/layout.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| A | `resources/views/radiographer/manage.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| A | `resources/views/radiographer/qc.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| A | `resources/views/radiologist/binding.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| A | `resources/views/radiologist/manage.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| A | `resources/views/radiologist/unavailable.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| A | `resources/views/radiologist/workspace.blade.php` | Admin / owner presentation | Copied locators, status, disclosure/accessibility; high |
| M | `routes/web.php` | HTTP routing | Middleware, signatures, authorization/bindings; high |
| M | `tests/Architecture/FoundationArchitectureTest.php` | Module / architecture regression | Coverage and executed boundary attribution; verification impact |
| A | `tests/Browser/admin-task-links.mjs` | Synthetic browser verification | Actual integrated journey and safe network targeting; high |
| A | `tests/Browser/member-result-access.mjs` | Synthetic browser verification | Actual integrated journey and safe network targeting; high |
| A | `tests/Browser/radiographer-workspace.mjs` | Synthetic browser verification | Actual integrated journey and safe network targeting; high |
| A | `tests/Browser/radiologist-workspace.mjs` | Synthetic browser verification | Actual integrated journey and safe network targeting; high |
| M | `tests/Deployment/ProductionDockerBuildResilienceTest.php` | Runtime/deployment | Parser/runtime compatibility; high |
| A | `tests/Deployment/check-dicom-runtime.php` | Runtime/deployment | Parser/runtime compatibility; high |
| A | `tests/Feature/Admin/TaskLinkManagementTest.php` | Module / architecture regression | Coverage and executed boundary attribution; verification impact |
| A | `tests/Feature/Doctor/RadiologistWorkspaceTest.php` | Module / architecture regression | Coverage and executed boundary attribution; verification impact |
| A | `tests/Feature/Member/MemberResultAccessTest.php` | Module / architecture regression | Coverage and executed boundary attribution; verification impact |
| A | `tests/Feature/Radiographer/ClinicalFinalizationGuardTest.php` | Module / architecture regression | Coverage and executed boundary attribution; verification impact |
| A | `tests/Feature/Radiographer/RadiographerDisclosureTest.php` | Module / architecture regression | Coverage and executed boundary attribution; verification impact |
| A | `tests/Feature/Radiographer/RadiographerIntegrityTest.php` | Module / architecture regression | Coverage and executed boundary attribution; verification impact |
| A | `tests/Feature/Radiographer/RadiographerSecurityTest.php` | Module / architecture regression | Coverage and executed boundary attribution; verification impact |
| A | `tests/Feature/Radiographer/RadiographerWorkspaceTest.php` | Module / architecture regression | Coverage and executed boundary attribution; verification impact |
| A | `tests/ImageGateway/BoundedDicomVerifierTest.php` | Module / architecture regression | Coverage and executed boundary attribution; verification impact |
| A | `tests/Integration/MemberResultConcurrencyTest.php` | Cross-module race verification | Independent connections, invalidation/audit atomicity; high |
| A | `tests/Integration/RadiographerConcurrencyTest.php` | Cross-module race verification | Independent connections, invalidation/audit atomicity; high |
| A | `tests/Integration/RadiologistConcurrencyTest.php` | Cross-module race verification | Independent connections, invalidation/audit atomicity; high |
| A | `tests/JavaScript/radiologist-dicom-modality.test.mjs` | Module / architecture regression | Coverage and executed boundary attribution; verification impact |
| A | `tests/Support/ClosedSyntheticClinicalInventory.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/MemberResultFixtures.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/RadiographerFixtures.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/RadiologistFixtures.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/SyntheticClinicalStatusSource.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/SyntheticDicom.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/SyntheticRadiologistSource.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/member-result-browser-bootstrap.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/member-result-browser-fixture.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/member-result-http.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/radiographer-browser-bootstrap.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/radiographer-browser-fixture.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/radiographer-http.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/radiologist-browser-bootstrap.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/radiologist-browser-fixture.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Support/radiologist-http.php` | Synthetic test support | Fixture isolation, mocks versus actual sources; high |
| A | `tests/Unit/Doctor/RadiologistCommandsTest.php` | Module / architecture regression | Coverage and executed boundary attribution; verification impact |

## Appendix B — Complete intervening commit inventory

Exact `main..candidate` range, oldest first; **24 commits**. Subjects are observed Git metadata, not proof of scope, acceptance or successful verification. Inspect each relevant patch/authority link during E6.

| Commit | Observed subject |
|---|---|
| `6def35612acd2b7d0712fc3657ab0611f2257eb6` | docs: finalize radiographer workspace development draft |
| `25d396e576ee513bee1a7e2aa2db57a65afdd666` | docs: publish approved radiographer workspace contract |
| `1d078fb64bd3cae11227aa28874c748ada96e962` | feat: add bounded DICOM parser checkpoint and record image build stop |
| `4f5c7488735f051d6b4a77cebb2a13e039168b6d` | fix: package required GD and verify bounded parser in application image |
| `702e6303cad5c6c9f7319e44d9989b3890c83ab2` | feat: complete radiographer workspace with fail-closed clinical guard |
| `0f27d2b58c804183b907e236aa9735f5fac876ac` | fix: remediate radiographer disclosure audit and revocation races |
| `e037185422d8a0e6ca90b6ba13743454784c57aa` | feat: add initial draft for Admin-assigned Radiologist Workspace v1 |
| `c8886a8ee0c7f11edae5f888fe42e79b86ef5607` | docs: publish approved Radiologist v1 development contract |
| `58cc95ba3ddb4481a5051af7b5c4b15e8209a24f` | feat: add scoped Radiologist workspace and immutable internal reporting |
| `b0966530586cfab5b7a83bac92b25af6992e5612` | fix: normalize credential validity and extend Radiologist boundary checks |
| `fe0c9cb208eaf47263e17571b1d5d1b33b11dfeb` | fix: bind interactive Radiologist login to current assignment |
| `d073f3f363feba6a8dc8922e5cd627b83f30cda2` | fix: enforce task-approved clinical repeat reasons |
| `fcd64becc401a7e2954e2c0050aa65bb52c2bc98` | fix: audit assignment owner reads and minimize handoff context |
| `6448f07ff2fc39843f52fd756b8015e36d270860` | fix: allow drafts to omit optional recommendations |
| `3f81ac44b9f296ca2b2c893400ebf314bd535b88` | docs: record Radiologist E6 coverage and verification limits |
| `cd5128fdf78c2ad8ef95b62786e24858cc57e7ca` | Fix radiologist credential revocation and clinical lock ordering |
| `3ea425531f4836af38b08b06acd7ef3728b1e00c` | docs: publish bounded member result access v1 task |
| `9804642c60b678ca004fd9d69a51c682f352d517` | feat(member): add manually published final result access |
| `c504de35c3c6160244ea34af37aa9c7227c33d02` | docs: publish integrated preflight and release-readiness task |
| `cfe110efefdd0884e86e0be21d02f8c2565dd8af` | docs: record integrated preflight release-readiness evidence |
| `07a580004ff72ab7074c36d00a5ad355d78541d7` | feat(task): create initial draft for Admin-managed Staff Onboarding & Lifecycle v1 |
| `e3db4799873f1b910ffce8df5c0bea709763f596` | Implement code changes to enhance functionality and improve performance |
| `641a8b2c420bbcd12b41c5df66766219e270c91b` | docs: publish validated admin task-link contract v1.0 |
| `d6a8eca29baf565c42292a2d3a8d04f0da44b48b` | feat: improve admin-issued task-link journey |
