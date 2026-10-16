# Integrated preflight and release-readiness v1 — E6 evidence

**REVIEW REQUIRED.** The mandatory synthetic assessment executed against the pinned candidate. It is reviewable with failures; no V7, A9, integration-readiness waiver, merge, production or G10 approval is issued.

## Identity and scope

Repository: `Madeena-software/mhcs-core`. Evidence branch: `feat/mhcs-integrated-preflight-release-readiness-v1`. Execution date: 2026-10-16, Asia/Jakarta. Assessment only; this entrypoint is the sole tracked change.

| Identity | Immutable value |
|---|---|
| Governing task | `.agents/tasks/mhcs-integrated-preflight-release-readiness-v1.md` |
| T5 publication / actual execution-start HEAD | `c504de35c3c6160244ea34af37aa9c7227c33d02` |
| Governing task blob | `c448d3ab1192b77906811f77a64330a6f3b2794f` |
| Validated application baseline / candidate | `9804642c60b678ca004fd9d69a51c682f352d517` |
| Candidate tree | `38214e8085ac6f632672236a4062abc112f59e88` |
| Accepted Radiologist comparison | `cd5128fdf78c2ad8ef95b62786e24858cc57e7ca` |
| Accepted Radiographer | `0f27d2b58c804183b907e236aa9735f5fac876ac` |
| Main integration anchor | `f8f0bb9e2e0cd12d1b797cf6a6689f26336274ba` |
| External Planner–Reviewer contract blob, read for applicable boundaries | `39adb286f6b28229872898389ce3c7a05f0151a0` |

Every ancestry edge passed; main → candidate is 0 behind / 18 ahead. Publication adds only the governing task. All three predecessor task blobs match their immutable governing revisions. Initial root worktree was clean. Fetched/live planning publication matched the handoff; final source recheck retained the pinned main, Member and planning identities. The evidence branch was absent remotely before publication. No candidate source was advanced or repaired.

## Six outputs and retained evidence

Detailed artifacts are outside Git under **E = `/home/faliq/.codex/task-state/mhcs-core/integrated-preflight-v1/`**. Paths below are exact paths relative to E. `artifact-manifest.json` records SHA-256 and sizes; its digest is recorded below. `publication.json`, produced after ordinary push/fetch verification, records the final evidence commit and local/fetched/live identities. Resolve this entrypoint's immutable containing revision from the final handoff or Git history, rather than substituting a later branch HEAD.

| Output | Exact artifact references |
|---|---|
| 1. Integrated candidate verification | `integrated-report.md`, `verification.json`, `source-identities.log`, `source-routes.log`, `runtime-metadata.log`, `candidate-source-sha256.json`, `baseline-source-sha256.json`, `clinical-security-assessment.md`, `assessment-commands.jsonl` |
| 2. AC-to-test matrix | `coverage-matrix.json`, `coverage-matrix.md`; exact identities and datasets in `verification.json`; default/explicit identity mapping in `skip-coverage.json` |
| 3. Complete PHP comparison | `full-comparison.json`, `candidate-statuses.json`, `baseline-statuses.json`, minimized `candidate-junit.xml`, `baseline-junit.xml`; original nonzero full-run results are retained |
| 4. CI/dependency/advisories | `ci-dependency-assessment.md`, `dependencies.json`, `advisory-applicability.json`, `advisory-details.json`, audit/platform/CI/deployment logs |
| 5. Prioritized G10 blockers | `g10-blockers.json`, `g10-blockers.md`: 18 rows, each with stable ID, severity/rationale, evidence, owner role, measurable closure, approving authority, prerequisites, next action and closure sequence |
| 6. Readiness verdict | `readiness.json`, together with the verdict below; independent review remains required |

## Environment, isolation and preservation

New disposable resources used owner label `mhcs.owner=integrated-preflight-v1-20261016`. Existing containers, databases, ignored files, source checkout and the prior localhost listener were preserved. Pinned detached worktrees included tracked CI/Docker/test inputs; no operational `.env`, cache or storage was copied. Source/vendor were copied into separate native `/native` roots; installed Node packages were mounted read-only. Composer class origins and Node parser resolution were checked. All **824 candidate and 801 baseline tracked files** matched runtime bytes; browser and verification copies also matched all 824 candidate files.

Supported image: `sha256:380f70b20e323a710e39516a4b5ac8edd273b26d96cdadfe4aa2fb8f4ce9adcc`. MySQL image: `sha256:da906917ca4ace3ba55538b7c2ee97a9bc865ef14a4b6920b021f0249d603f3d`. Observed PHP 8.4.26, Node 24.21.0, util-linux 2.41.5, SQLite 3.46.1, MySQL 8.4.8 / REPEATABLE-READ and `pcntl`. Tests ran as uid 33 with unchanged Docker/parser security. Full suites and browser application used no network; MySQL used an owned internal network, alias `mysql`, disposable `mhcs_rad_synthetic` and tmpfs data, with no published database port. Browser clients used installed Playwright 1.62.1 / Chromium 1234 and localhost-only relays on 18116/18117.

Temporary browser launchers changed resource names/ports, installed import resolution, Docker user/cwd and screenshot output only. Assertions and application/test-contract source stayed unchanged. Six synthetic screenshots exclude browser chrome and signed locators; representative Member and original-render screenshots were visually checked. Owned containers/network/relays/worktrees were removed after evidence capture; no prior resource was removed. Read-only discovery used Codebase Memory and a Graphify query; Graphify returned no relevant matches, and material conclusions were verified directly against source.

## Observed verification

| Check | Observed result | Exit |
|---|---|---|
| Full candidate PHP | 1,023 tests; 14,041 assertions; 940 passed, 10 failures, 16 errors, 57 skips | 2 |
| Full exact Radiologist baseline | 968 tests; 13,781 assertions; 905 passed, 9 failures, 16 errors, 38 skips | 2 |
| Focused Admin/Operator/Member/clinical/architecture/security/imaging | 775 tests; 10,906 assertions; 770 passed, 3 errors, 2 default skips | 2 |
| Clinical/architecture/security/verifier subset of focused run | 202 passed / 3,502 assertions, including Member 36 / 206 | Observed subset |
| Explicit MySQL Member/Radiographer/Radiologist races | 50 passed / 556 assertions; zero skips | 0 |
| Explicit SQLite Member races | 19 passed / 180 assertions; zero skips | 0 |
| Additional sequential MySQL conformance | 8 passed / 49 assertions; zero skips | 0 |
| Additional MySQL Admin protocol / Operator ticket races | 2 passed / 26 assertions; zero skips | 0 |
| SQLite and MySQL schema/lifecycle probes | Creation, duplicate-generation rejection, all three down/up cycles and surviving-state/original hash preservation passed | 0 each |
| Direct/converted full actor browser journeys | Both passed; 4 original renders, 2 handoffs, 2 finalizations, 3 separate publications | 0 |
| Additional Radiologist browser journeys | 2 renders/finalizations; repeat-pending, wrong-password, audit failure, revoke and expiry assertions passed | 0 |
| Explicit safe JavaScript tests | 19 passed; zero skips | 0 |
| Separate candidate and baseline Vite builds + bundle checks | Passed; existing large-chunk warning | 0 each |
| Whole-tree Pint / PHP syntax / JS-parser syntax / shell syntax | Pint 541 files; PHP syntax 542 files; remaining syntax checks passed | 0 each |
| Composer metadata / production-only platform | Passed | 0 each |
| Complete development platform | Missing `ext-sockets` required by Pest browser plugin | 2 |
| Composer public advisory query | 11 advisories across 5 installed runtime packages | 1 |
| npm public advisory query | 16 affected graph entries: 2 critical, 10 high, 4 moderate | 1 |
| Extracted deployment static predicates | Existing forbidden URL/live-pattern predicate failed | 1 |
| Exact-candidate GitHub checks/statuses/runs | Successful read: 0 check-runs, 0 combined statuses, 0 workflow runs | 0 query exits |

`assessment-commands.jsonl` records actual verification/setup commands, exit codes, timestamps, durations and log paths; fixture key values are omitted. It is a verification ledger, not an assertion that every interactive read-only discovery command was a check. Builds invoked installed Vite with `--configLoader runner` to avoid writes into read-only Node dependencies, then ran the unchanged package build's DICOM bundle check separately. No package lifecycle/install/update command was run.

**Exact PHP classification:** 25 persistent failure/error identities; one newly observed failure status; no resolved failure/error identities; 19 added skip identities; no removed skip identities. Persistent results comprise 2 GD WebP setup errors, 13 missing-Mpdf errors, 1 missing-Python worker error and 9 missing-Python worker assertion failures. Full identities, dataset variants, status changes and assertion totals are machine-readable in Output 3.

The new failure is `Tests\Feature\Operator\Mvp04kBasicExaminationCompletionTest::test_claimant_completes_basic_examination_and_readies_the_same_ticket_for_xray`, at line 92: broad `assertDontSee('120')` matched `120` in the generated synthetic MRN. The fixture, test and worklist service are unchanged between revisions. Both targeted baseline/candidate reruns passed 1 test / 45 assertions, and the focused candidate run had no assertion failures. The original full-run failure remains a new observed status requiring independent adjudication; these reruns do not erase it or establish a green full suite.

All **57 default-skipped candidate identities**, including the 19 added Member variants, map to explicit passing MySQL identities in `skip-coverage.json`. Default full-suite skips remain recorded as skips. SQLite's separate 19-case run also passed. Synchronization uses actual independent processes/connections, ready/go barriers, held locks, prepared responses and NOWAIT probes; sequential test invocation is not represented as the concurrency itself.

SQLite integrity returned `ok`, foreign-key check returned no violations, foreign-key enforcement was 1 and journal mode was WAL. Lifecycle probes preserved surviving table digests and original-object checksums through all three migrations. The 20 additive tables introduce primary/unique/index constraints without new FKs; owner/currentness checks remain necessary. Down migration intentionally deletes the slice's own tables and history: these tests do not approve operational rollback or imply preservation of intentionally dropped data.

## Integration coverage and limits

The main real-browser flow exercised Admin issue → individually authenticated Radiographer bounded original render/QC/handoff → individually authenticated Radiologist bounded render/manual finalization → separately authorized Admin publication → verified adult Member login/final-only read, for both direct and converted synthetic originals. Finalization alone left zero publications. Checks covered wrong recipient, identity loss, audit failure, invalid lineage, explicit hold/manual republication, stale generation, suspension, revocation and expiry. Original hashes and sealed actual source associations were checked by owner services; immutable final fields and source/history preservation were exercised by feature/race/lifecycle assertions.

Supplemental Doctor browser checks exercised repeat-required draft preservation and finalization denial, incorrect finalization password, audit denial and revoked/expired access. Facility/case, reassignment, professional eligibility, credential/session changes and prepared-output invalidation have actual PHP/race coverage; they are not all separately browser-proven. Doctor's 46 command/auth unit tests use an explicit synthetic imaging source and are **mock-only for imaging**. Actual feature/browser/race evidence is separate. The standalone Radiographer launcher was inspected; its synthetic positive-reopen adapter does not establish real clinical-source completeness. Synthetic 2×2 pixel rendering is neither clinical display certification nor proof of live MPIPS conversion.

The AC1–AC10 matrix distinguishes observed passes, errors, defaults skipped, mock-only evidence, missing source-to-PDF assurance, absent CI and unresolved approval obligations. No failed static check, missing dependency, skipped default case or mock was relabeled as a mandatory integrated pass.

## CI, dependencies and security/clinical findings

Installed Composer packages are 185 versus 191 locked: six missing production packages include Mpdf, PdfParser and their PDF dependencies. Present versions/references match and there are no extras. npm has 157 installed of 199 locked package locations; 42 absent entries are optional platform packages, with no missing nonoptional or installed-version mismatch. Locks/manifests match between compared revisions. Public advisory records include package role, installed/locked version, affected range, upstream patched version, severity and retrieval time. Critical npm ratings affect development `shell-quote`/`concurrently`; runtime advisories also remain. Nested `fflate` 0.7.3 is affected; root 0.8.3 is not that node. No exploit or live compromise was tested.

The [Symfony security notice](https://symfony.com/cve-2026-108082) covers installed HTTP Foundation 8.1.2 and identifies security fix 8.1.7 plus follow-up 8.1.9. The [Filament notice](https://github.com/advisories/GHSA-7m6h-rg42-m449) lists patched 5.8.3; its Composer affected-range metadata differs at the boundary. Output 4 preserves these distinctions. Dependency remediation requires its own bounded authorization; npm's suggested Cornerstone downgrade is not a reviewed compatible fix.

`security-validation.yml` is manual `workflow_dispatch`; it lacks explicit safe JS/browser execution and the new backend opt-ins. The MySQL script's DB differs from the hardcoded new-race fixture DB, so enabling flags alone is insufficient. CI Node 22 and hosted system utilities are not proven equivalent to the observed runtime. Latest ten historical runs failed at other SHAs. No workflow was dispatched. Deployment static validation failed on URL-pattern matches, including the localhost healthcheck and production workflow URLs; no deployment build/smoke branch ran.

| Finding | Current evidence and disposition |
|---|---|
| MVP-GAP-026 | Historical redacted exposure report remains open; current credential validity and rotation/revocation are unknown. No credential value retrieved, searched, tested or used; no fresh active-exposure conclusion. Security-owner attestation is required; cleanup alone would not prove revocation. |
| MVP-GAP-035 | Source stores metadata without findings/impression, then substitutes normal narrative for missing/null values. Nonempty substitutes bypass empty-field extraction. Actual private metadata-validator probe accepts contradictory supplied narrative. No completed fresh PDF rendering or patient-incident claim. |
| MVP-GAP-036 | Job omits facility provenance; generator has fixed facility/organization/address fallbacks. Actual validator accepts missing, two-facility and inconsistent metadata. Rendered two-facility/missing-source PDF assurance remains unproven because prerequisites are absent. |
| Clinical source / reopen | Default `UnknownClinicalSourceInventory` still denies; `WorkspaceClinicalStatusSource` retains `complete_sources=false`. Unknown publication/read/reopen denial tests passed. Positive Member fixtures seal actual closed synthetic lineage only; no operational completeness is inferred. |

## Prioritized G10 closure

The complete 18-row matrix in Output 5 supplies every required owner/approval/dependency/closure field; individuals remain unassigned and no existing gap is closed. Recommended sequence:

1. Obtain secret-free security-owner disposition for **MVP-GAP-026**; handle credible fresh active exposure through separately authorized incident planning.
2. Resolve **MVP-GAP-035/036** clinical/provenance contracts and **IPRR-DEP-001** advisory remediation, with authorized supported dependencies and faithful-source tests.
3. Establish authoritative clinical-source coverage (**IPRR-SOURCE-001**), real professional eligibility (**028**), report/recipient/consent authority (**029/030**), and audit/privacy/storage policy (**031/019/021**).
4. Close runtime/test reproducibility and assertion reliability (**IPRR-TEST-001/002**), exact-ref CI/static policy (**023**) and environment-specific packaging (**IPRR-PACK-001**).
5. Rehearse and approve migrations (**018**), monitoring/incident response/backup/restore/rollback (**IPRR-OPS-001**).
6. Complete independent review and designated institutional, clinical, security, privacy, operations and release approvals (**034**, separate G10). Historical **022** remains closed only for its earlier bounded deployment.

## Readiness and handoff

- **Development-ready:** yes, within existing bounded isolated synthetic authority.
- **Integration-ready:** **not established**. Mandatory synthetic verification executed, but the 25 persistent failures/errors and new assertion-collision status require independent adjudication. No automatic waiver.
- **Production-blocked:** yes; the clinical, security, privacy, dependency, runtime, CI and operational obligations above remain.
- **Release-ready:** no; required G10 evidence and designated approvals have not been obtained.

No source, test contract, migration, dependency, workflow, deployment configuration or default clinical-source binding was changed. No real-data/service access, credential operation, PR, merge, force push, main mutation, deployment or release occurred.

Non-final diagnostics are recorded in Output 1: an unrecognized disposable SQLite filename was rejected by the unchanged inventory guard; supplemental workers required fully exported synthetic configuration; two supplemental successes overlapped and were replaced by sequential final runs; a preliminary comparison parser's empty skip message handling was corrected. Mandatory full/race/browser results above are the retained runs. No test failure was repaired in source.

Next responsibility: independent V7 of the exact task, candidate and evidence revision; publish separately authorized bounded corrective work where warranted. This assessment does not self-accept or authorize release.

Artifact manifest SHA-256: `ec029962bd5cf48fa7956a1e8a09300ccf7950d2d0e02ce7d5dde65351308107`.
