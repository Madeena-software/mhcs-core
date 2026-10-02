---
title: Preserve deployed recovery before PNG release
version: 1.0
status: validated-published
last_updated: 2026-10-02
---

# Executable integration contract

Human authority: On 2026-10-02 the requester authorized execution through push, merge to main and deployment, then explicitly selected "Review dan gabungkan perbaikan produksi dahulu, lalu PNG/filter dan deploy" after disclosure of the deployed recovery/main divergence.

## Identity and baseline

Task path: `.agents/tasks/production-recovery-main-integration.md`. The immutable publication SHA supplied in the handoff governs this integration attempt.

- Main integration baseline: `b1a2204a2571920a6c696575a6480aad5a1294fd`.
- Deployed recovery candidate: `d0f99ecd604a2b0aa0f31a62396790b04028c9eb`, from successful deployment run `34089367272` on `diagnostic/operator-eligible-shifts-500`. Deployment success establishes observed deployment, not full task acceptance.
- Existing recovery contract: `.agents/tasks/operator-ai-pacs-production-readiness-recovery.md @ 508147a17644d5b268ae0d11fab0591a14275cad`, implementation baseline `b4701589ca46c0de9c337d6b584335498b4848d2`.
- PNG/filter contract: `.agents/tasks/operator-dicom-png-and-worklist-filters.md @ 036417ad91d16af1be13ab65770915554061f6f4`, original implementation baseline `b1a2204a2571920a6c696575a6480aad5a1294fd`. Its independently reviewed implementation revision will be recorded before integration.
- Execution start: clean isolated worktree at the deployed recovery candidate. Preserve both published histories and the original PNG workspace.

## Objective and authority

Review and preserve the already deployed production recovery in main before integrating and releasing the independently accepted PNG/filter change. This prevents reinstatement of the known stale PHP-FPM route-cache failure, loss of AI PACS runtime/configuration, or removal of deployed MySQL portability migrations.

Controlling inputs: human instructions above; `.agents/AGENTS.md`, `.agents/software-workflow.md`, project context and canonical review procedure; both exact contracts identified above; existing deployment operational policy in `deployment/README.md`.

Traceability: recovery REQ-PROD-REC-001/002/003, REQ-OPS-001..004, REQ-AI-001..006, REQ-REL-001 and engine/database invariants retain their original acceptance obligations. PNG-FILTER-001..006 retain their separate acceptance boundary. Observed deployment is not retroactive product or acceptance authority.

## Scope and preserved behavior

- Review all candidate changes between the main baseline and deployed recovery candidate, distinguishing preceding runtime/portability changes from the recovery implementation itself.
- Close missing recovery reproduction, regression and engine evidence using isolated synthetic environments, existing installed tools and legitimate existing CI/test workflows.
- Repair only bounded defects in recovery verification/isolation or release integration when necessary. Republish materially changed executable obligations before implementation.
- Integrate the accepted recovery candidate through a normal PR merge into main; then integrate the accepted PNG/filter candidate without discarding either history.
- Review the combined immutable main revision and run risk-proportionate integrated regressions/build checks before dispatching the existing production deployment workflow.
- Preserve source/runtime cache synchronization, AI PACS dependencies/configuration, post-2038 MySQL instant compatibility, authorization/privacy, asynchronous imaging, NPZ and direct DICOM paths, clinical disclaimers, and all unrelated user work.

Excluded: new product behavior, symptom-hiding guards, architecture redesign, dependency upgrades, production/private-data inspection, real-patient AI calls, direct production host operations, force pushes, destructive data/schema operations, and unrelated cleanup.

## Acceptance and verification

- [ ] Original recovery mechanism is empirically reproduced in isolated PHP 8.4 FPM with timestamp validation disabled: stale cache -> CLI rewrite on disk -> stale web response -> local graceful FPM reload -> current response.
- [ ] Remediated cache startup serves current artifacts on the first request; no shared mutable cache volume or post-start cache mutation reintroduced.
- [ ] Existing Operator, Operator foundation, Image Gateway/AI and deployment regression obligations are observed passing with actual nonzero counts. Unsafe lifecycle tests must not run in shared workspace or against ambient `.env`/networks.
- [ ] Required MySQL 8.4/post-2038 compatibility evidence is observed in an isolated local or isolated CI database; SQLite is not represented as MySQL evidence.
- [ ] PNG candidate is independently reviewed against its exact contract and all R1-R7 findings closed before its merge.
- [ ] Review records identify exact task/baseline/implementation SHAs and limitations. No source existence, prior deployment success or agent narrative substitutes for verification.
- [ ] Combined tree retains both accepted outcomes, build and applicable integrated tests pass, and no unrelated changes/secrets/private data are introduced.
- [ ] Separate G10 release assessment records human authorization, immutable revision, current verification, existing backup/rollback/health procedure and operational limits before deployment.
- [ ] Actual deployment run and post-deployment functional evidence are observed and attributed to the exact merged revision; report failures truthfully.

## Side effects and stop conditions

The human authorizes task publication, bounded verification/remediation commits, normal feature/integration branch pushes, PR creation/merge into main, synchronization of main, isolated test workflow dispatch and the existing production deployment workflow after review and release gates. No repeated confirmation is needed for those authorized actions. Stage explicit scoped paths only; no credentials, patient data or generated private artifacts.

Use an isolated checkout with synthetic environment and separately isolated database/process resources. Never execute existing fixed-name Docker lifecycle tests blindly. Direct production SSH/container manipulation is prohibited; deployment and any approved functional smoke use versioned workflows and avoid private patient data.

Stop release for unresolved blocking review findings, failed required verification, missing production-equivalent engine evidence, incompatible remote drift, unsafe test isolation, unexpected migration/data changes, or a required new product/architecture/privacy decision. Return the concrete gap to Planner/Reviewer; do not weaken the prior contracts to declare readiness.

Terminal outcomes: REVIEW REQUIRED with observed evidence; Reviewer ACCEPTED only when all obligations pass; deployment only after separate G10. A blocked release does not invalidate independently accepted PNG/filter work.
