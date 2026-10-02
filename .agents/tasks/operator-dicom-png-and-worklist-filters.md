---
title: Operator DICOM PNG Download and Worklist Filters
document_id: MHCS-TASK-OPERATOR-DICOM-PNG-FILTERS-001
version: 1.1
status: validated-published
language: en-US
last_updated: 2026-10-02
scope:
  - full-image PNG download from the Operator DICOM results list
  - patient/reference, date, and status filters on four Operator worklists
  - focused export, authorization, refresh, selection, and UI verification
authority_note: Human-approved bounded delivery contract, executable upon immutable publication of this exact content. The human authorized task publication and, after successful verification, automatic implementation commits and non-force pushes to the designated feature branch only. Deployment and other external-system mutations remain unauthorized.
---

# Executable Task

## Task identity

**Task title:** Operator DICOM PNG Download and Worklist Filters

**Task path:** `.agents/tasks/operator-dicom-png-and-worklist-filters.md`

**Task contract state:** Validated/Published upon immutable publication of this exact content. The Planner handoff supplies its full publication SHA; execution and review remain tied to that exact revision.

**Delivery objective:** Make the existing Operator results and operational lists easier to search and use, with full-image PNG export available directly from DICOM results.

**Owner / designated planning authority:** Human requester and Planner/Reviewer in the current conversation.

## Delivery context

The human requester explicitly asked for a PNG download column in the DICOM results list and filters on other index pages. Subsequent answers selected full original-image extent, patient/examination-code search, date and status filtering, and exactly four Operator lists: DICOM results, verification, basic examination, and radiography readiness. Member/Admin lists are excluded.

Observed source provides protected DICOM access, individual `.dcm` download, selected-study ZIP download, AI report actions, and these four lists. No PNG action or filter panel was observed in their current views. These observations describe the candidate implementation, not acceptance of previous tasks.

## Baseline and task revision

**Implementation baseline:** `b1a2204a2571920a6c696575a6480aad5a1294fd`, approved by the human for this bounded successor on 2026-10-02. Local `main` was clean before task drafting; the feature branch initially differs from this baseline only in this task file.

**Prior delivery state:** The baseline contains the merged AI PACS integration. No pending Executor result or explicit outstanding review/remediation was identified for this conversation. The absence of reviews/checks on its merged PR does not by itself establish pending review. Historical full-task AI PACS acceptance and CI evidence remain unverified; this publication records human approval of the selected successor baseline and focused local compatibility evidence, not retrospective acceptance of the entire prior task.

**Task revision:** The full SHA of the commit containing this exact validated content, supplied externally in the Planner handoff. Resolve that SHA before implementation; a previous Draft commit does not govern execution.

**Expected remediation execution-start state:** The publication commit containing this v1.1 task on `task/operator-dicom-png-and-worklist-filters`, with a clean working tree and product source identical to the reviewed candidate `1ed8005e57af1bc941b1ebd4d040b3e89138c555`. The original implementation baseline remains unchanged. Preserve the candidate and fix it incrementally; do not reset or rewrite its published history. If additional implementation commits intervene, preserve them and return material drift to Planner/Reviewer.

### Review and same-task remediation, 2026-10-02

**Verdict:** REMEDIATION REQUIRED. The reviewed candidate is not accepted as a new baseline.

**Review identity:** Original execution governed by this task at `1c2a03c7f83f92c849589dc7b5a7d8874028d632`; actual execution-start HEAD reported and verified in ancestry is that publication commit; original implementation baseline is `b1a2204a2571920a6c696575a6480aad5a1294fd`; candidate is `1ed8005e57af1bc941b1ebd4d040b3e89138c555`. The candidate changes 13 in-scope files and leaves the original task content unchanged. Candidate remote branch identity was verified.

The following findings close existing obligations; they introduce no new product objective. Before changing implementation, resolve this v1.1 task's exact publication SHA supplied by the Planner.

1. **R1 — PNG presentation and pixel decoding (blocking).** `resources/js/operator-dicom-png.js:85` treats a valid numeric window center of zero as absent. Browser reproduction with pixel values `[0,1,2]`, window center `0`, width `2` produces `[0,255,255]`, whereas the default LINEAR presentation requires `[255,255,255]`. Source: DICOM PS3.3 C.11.2, https://dicom.nema.org/medical/dicom/current/output/chtml/part03/sect_c.11.2.html . The custom renderer also bypasses installed presentation/LUT handling, while `extractPixelData` guesses encapsulated fragment positions and selects only one fragment instead of using the installed DICOM frame extraction path. Reuse the established installed decoding/rendering mechanisms where adequate; preserve valid zero values and prove equivalence to the existing default study presentation. Cover supported grayscale/photometric, rescale, default VOI, byte-offset and compressed-frame cases. Do not add new formats or invent an unsupported-frame policy; stop for a material compatibility decision if needed.
2. **R2 — Export lifecycle/failure containment (blocking).** `downloadStudyAsPng` has unbounded initialization, fetch/body-read, decode and `toBlob` waits; initialization failures are swallowed; every attempt adds a global metadata provider that is never removed, and cleanup is success-only. `app.js` has an uncaught/unbounded PNG-module import, leaving inert buttons on bootstrap failure. Bound waits with existing mechanisms, abort outstanding retrieval where possible, handle genuine initialization/import failures, remove temporary providers and release canvases/URLs/listeners in failure-safe cleanup, and always restore a useful localized UI. Do not log private URLs or raw exception diagnostics.
3. **R3 — Filtered selection (blocking).** The legacy Blade select-all `change` listener still selects all checkboxes, after the new filter handler's `click` selects visible rows. Browser reproduction with `q=alpha` and two rows yields `{hidden:false,checked:true}` and `{hidden:true,checked:true}`. Consolidate selection handling so hidden rows stay unselected and are excluded from submission. Verify filtering, select-all on/off, individual selection, reset/unhide, empty selections and actual serialized ZIP form data. Preserve server-side authorization revalidation.
4. **R4 — Empty/reset/date behavior (blocking).** `applyFilters` returns early when there are no data rows, before synchronizing URL/state/count. Browser reproduction shows reset leaving `?q=alpha` in the URL after rows disappear; refresh consequently restores the unwanted filter. Update filter state and reset even on an initially empty list or a list that becomes empty. `parseDateOnly` accepts arbitrary suffixes/impossible dates, and reversed date ranges silently look like ordinary no matches. Provide explicit localized invalid-range feedback and safe handling of malformed URL date/status values while retaining inclusive timezone-consistent semantics.
5. **R5 — Existing verification states (blocking).** Verification filter options `verified`, `pending_verification`, and `refused` do not match the `verification_state` field used for rows. The existing service exposes `open`, `matched`, `nonclinical_validation`, `mismatch_reported`, `insufficient_evidence`, and `cancelled`, plus the list's `unclaimed` fallback; `pending_verification` is a separate arrival-status field. Align available filter values with existing eligible/displayed states without inventing new states or changing workflow eligibility. Verify real fixtures for each applicable state, including absent/ineligible states and refresh.
6. **R6 — Translation registry (blocking).** PNG status/error messages and filter counters are hardcoded in JavaScript despite matching some keys in `lang/id.json`. Supply all new visible and accessibility text through the existing Laravel translation registry and safe Blade-to-JavaScript data. Add verification that editing registry values changes rendered statuses/counters/errors. Preserve generic errors and private-data non-disclosure.
7. **R7 — Evidence closure and isolated verification (blocking).** The browser PNG test checks file length, signature, filename and dimensions but never decodes/asserts PNG pixels, image edges or orientation. The filter tests exercise pure predicates, not actual DOM interaction, selection, URL/reset or refresh. Add focused runnable checks reproducing R1-R6 and an actual synthetic asymmetric PNG download with expected decoded pixel samples, all edges, orientation and supported photometric/rescale behavior. Cover denied/unavailable retrieval, timeout, repeated download/cleanup and bootstrap failure. Demonstrate regressions fail against the candidate before fixes. Inspect test network targets first and use an explicit local test allowlist; never run `node --test tests/JavaScript/*.test.mjs` for this task, because it includes a production-login test.

**Observed review verification:** Fresh production build and DICOM bundle check passed; the eight scoped PHP suites passed 139 tests/1,125 assertions; targeted Pint and diff checks passed. A JavaScript glob run returned 41 passes, including the synthetic PNG test, but also executed an existing production-login test; it is not acceptable isolated task-verification evidence and must not be repeated. This unintended external verification is disclosed, not authorized retroactively. The three browser reproductions above demonstrate defects despite these passing tests. Local browser checks used synthetic data only. No product source was modified by the Reviewer.

**Continuation:** Remediate this same task, preserving its original objective, authority, baseline, scope, authorization and acceptance boundary. Return `REVIEW REQUIRED` with the old/new task identities distinguished and all findings/evidence gaps explicitly closed. Historical acceptance is not rewritten; this v1.1 revision governs only renewed execution.

### Readiness and observed baseline evidence

- Human approved the design, bounded commit/push workflow, and execution on 2026-10-02 ("saya acc agar bisa di eksekusi executor").
- Planner checked current source and the four list routes/views, protected DICOM retrieval, batch selection, installed image-rendering facilities, relevant authority, and prior task/history identity.
- Fresh isolated local verification on 2026-10-02: `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= QUEUE_CONNECTION=sync MHCS_PRIVATE_OBJECT_DISK=local vendor/bin/phpunit tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php tests/Feature/Operator/OperatorPortraitDicomViewerTest.php tests/Feature/Operator/OperatorFieldOperationsSlice1Test.php tests/Feature/Operator/OperatorFieldOperationsSlice2Test.php tests/Feature/Operator/OperatorFieldOperationsSlice3Test.php tests/Feature/Operator/OperatorFieldOperationsSlice4Test.php tests/ImageGateway/ImageGatewayAiDispatchTest.php` passed: 133 tests, 1,071 assertions.
- `node --test tests/JavaScript/operator-dicom-viewer.test.mjs` passed: 17 tests, zero failures.
- These checks establish local compatibility evidence for the selected baseline. They do not verify the new PNG/filter feature, real browser export, live services, production, or the full historical AI PACS task. The Executor must supply the new-feature verification below.
- No product source was changed during planning. No unresolved product or architecture decision was identified within the approved bounded scope.

## Objective

An authorized Operator can download one full-image PNG directly from each DICOM results row and narrow each of the four existing Operator lists with a consistent, compact filter panel without changing clinical workflow, list eligibility, or access scope.

## Authoritative inputs

### Governing authority

- Human request and explicit clarification answers in the current conversation dated 2026-10-02: PNG column in DICOM results; full image rather than viewer screenshot; patient/examination-reference, date and status filters; exactly four Operator lists.
- Human continuation instruction dated 2026-10-02, following the explicit automatic-commit/push proposal: proceed with bounded automatic commits and feature-branch pushes after verification, without force-push, merge to `main`, or deployment.
- Human final execution approval dated 2026-10-02: "saya acc agar bisa di eksekusi executor", approving this task's publication and execution from the selected baseline.
- `.agents/AGENTS.md`, `.agents/software-workflow.md`, `.agents/prompts/plan-create-task.md`, and `.agents/tasks/_template.md`.
- `.agents/context/project.md` for orientation and authority routing; it is supporting context, not technical approval.
- `docs/mvp/decision-log.md`: MVP-DEC-035/036 for protected Operator DICOM access and MVP-DEC-037 for Indonesian registry-backed browser copy.
- `.agents/tasks/operator-ai-pacs-integration.md @ 039438c91e9f488eeab64e02f5f967d2df5e49a9` for prior task identity and preservation obligations only; it does not authorize this successor.
- `.agents/tasks/urgent-operator-field-operations.md` for preserving existing Operator workflow, session locator, consent, and legacy NPZ/direct-DICOM behavior.

### Requirement traceability

- PNG-FILTER-001 → Human request: a per-study PNG download column in `/operator/studies`.
- PNG-FILTER-002 → Human answer "Gambar asli penuh": full image extent at native image dimensions, independent of viewer zoom/pan/rotation/flip.
- PNG-FILTER-003 → Human filter answer: patient/examination-reference search, date and status filters.
- PNG-FILTER-004 → Human scope answer: DICOM results, verification, basic examination, and radiography readiness lists only.
- PNG-FILTER-005 → MVP-DEC-035/036: preserve authenticated Operator site/shift/examination authorization for every image access.
- PNG-FILTER-006 → MVP-DEC-037 and existing behavior: Indonesian accessible controls, preserved clinical actions, refresh and authorized batch-download semantics.

## Scope

### In scope

- A localized, accessible "Unduh PNG" action in a dedicated DICOM results table column, with a pending state, duplicate-click protection, bounded failure handling, and useful generic Indonesian errors.
- Export the complete image at its native columns-by-rows resolution, using the existing default DICOM presentation/VOI policy. Exclude application UI, patient-information overlays, cropping, viewport zoom/pan, and user rotation/flip. Preserve source DICOM bytes and canonical image orientation. PNG is a rendered image derivative; it does not promise preservation of all DICOM metadata or source bit depth.
- A safe `.png` filename based on the existing `DCM-...` study reference, without adding patient names or identifiers to the filename.
- Filter panels on `/operator/studies`, `/operator/verification-worklist`, `/operator/basic-examination-worklist`, and `/operator/xray-readiness-worklist`.
- Case-insensitive patient-name search plus existing human-readable examination/ticket/study/session references and medical-record references applicable to each list. Search must not expose hidden identifiers or broaden the authorized dataset. A session code remains an exact string, including leading zeroes.
- Optional inclusive date-from/date-to controls on the row's existing accepted/occurrence/ready timestamp, with a clear localized label identifying that timestamp. Use the application's configured user-facing timezone consistently, and handle invalid or inverted date ranges explicitly.
- A status selector based on existing displayed states: AI/report availability state for DICOM results, verification state for verification, and operational state for basic examination/radiography readiness, including the existing DICOM-processing-failed presentation where applicable. Do not invent clinical states.
- Combine active filters, retain their values across the existing automatic refresh, preserve existing ordering, show a filtered no-results state, and provide reset to the unfiltered authorized list.
- A compact responsive layout with explicit labels and keyboard access, aligned table headers/cells/empty-state colspans, and preserved clinical and AI action controls.
- Select-all and ZIP submission must operate only on the currently displayed filtered DICOM rows. Hidden/nonmatching selections must not remain accidentally exportable.
- Fix local markup issues only where needed to keep these controls correct, including invalid nesting of the existing AI retry form inside the batch-selection form.

### Out of scope

- Member/Admin lists, new dashboards, whole-application restyling, PNG batch/ZIP export, or new product limits.
- Clinical interpretation, AI narrative changes, DICOM rewriting, MPIPS/Grabber protocol changes, or workflow/state transitions.
- New dependencies, framework changes, persistent PNG storage, database/schema migrations, export queues, public URLs, direct object-store access, or permission expansion.
- Production/private-data inspection, live AI PACS/MPIPS/S3 calls, remote publication other than the explicitly authorized feature-branch pushes, deployment, or release.

### Preserved behavior

- Existing site/shift/examination authorization and private/no-store image delivery; filters only narrow eligible records.
- Existing viewer, individual `.dcm` and selected-study ZIP downloads, AI report/retry actions, and the AI screening disclaimer.
- Existing claim/call/start/complete/bypass operations, consent rules, audit, session locators, and both NPZ and direct-DICOM ingestion paths.
- Existing automatic worklist refresh, ordering, empty states, and unrelated user work.

## Dependencies and assumptions

- Installed Cornerstone/DICOM decoding and browser canvas facilities may be reused; no dependency installation is authorized. The installed code and upstream render-to-canvas API indicate a feasible reuse path, but export correctness remains an Executor verification obligation.
- Relevant implementation surfaces include the four worklist Blade views, `resources/js/app.js`, `resources/js/operator-dicom-viewer.js`, their controller/service projections, `routes/web.php`, `lang/id.json`, and existing focused tests. This is a discovery map, not a fixed file allowlist.
- Applicable upstream reference: https://www.cornerstonejs.org/docs/api/core/namespaces/utilities/functions/rendertocanvascpu/ . Verify against the installed version rather than assuming current documentation exactly matches it.
- The current single-image study workflow defines the export unit. If supported data requires a new multi-frame or volumetric selection policy, stop for a product decision.
- Filter dates do not authorize historical or foreign-shift access. Existing list eligibility remains authoritative even if a date filter yields no rows.

### Remaining approval requirements

- The human approved continuation of the proposed task and bounded automatic-commit/push workflow on 2026-10-02. Material changes to its product, date/status, image-export, or side-effect boundaries still require Planner/Reviewer handling.
- The human subsequently approved execution from the selected baseline. No further per-commit or pre-execution approval is required within this exact published contract.
- Local task commits and the bounded Executor commit/push workflow below are authorized by the current human instruction; do not inherit permissions from older tasks.
- Resolve the exact immutable governing task revision before implementation. A commit containing an earlier Draft does not satisfy T5 or authorize implementation.

## Required capabilities

Repository source inspection, bounded local editing after publication, existing PHP/Node test and build tooling, and a local browser using synthetic data for actual PNG-download verification.

## Execution constraints

Reuse current authorization, query, loader, refresh, translation and test patterns. The Executor retains technical discretion between equivalent implementations within this contract. Avoid a parallel viewer or generic filter framework.

Image retrieval must pass through the existing protected application boundary. Browser-supplied study IDs and filter values confer no access authority. Bound decode/render/export waits; clean up temporary canvases, object URLs, listeners and loading state on success and failure. Fail safely rather than downloading a blank, truncated or unrelated image. Do not log private image data or internal exception details.

Use only synthetic/de-identified fixtures. Test commands must be isolated from production configuration and must not contact real external services.

## Acceptance criteria

- [ ] Every authorized DICOM results row has a working localized PNG action; denied retrieval produces no PNG or sensitive error details.
- [ ] Downloaded bytes are a valid PNG, with width/height matching the source image and all image edges visible, unaffected by viewer interactions or device-pixel ratio.
- [ ] Synthetic pixel-content checks demonstrate correct image identity, orientation, grayscale/photometric handling and default presentation; the file is not a renamed DICOM or viewport screenshot.
- [ ] Original DICOM bytes, filenames, authorized viewer access, `.dcm`/ZIP downloads and AI controls remain correct.
- [ ] Each of the four lists supports search, date range, status, combinations, reset and filtered-empty results with correct row semantics.
- [ ] Search retains session-code leading zeroes; malformed dates/statuses are handled safely and no filter expands eligible site/shift data.
- [ ] Automatic refresh retains filters and newly arriving records obey them; clinical actions remain separate valid forms.
- [ ] Select-all and ZIP submission include exactly displayed filtered selections, with existing server authorization revalidation preserved.
- [ ] Controls, loading/error states, labels, table structure and compact layout remain accessible and usable at desktop/mobile widths; all new visible copy uses `lang/id.json`.
- [ ] No dependency, schema, persistent-image, external-service, production or unrelated workflow change is introduced.

## Verification requirements

### Required checks

- Focused tests for each list's filter combinations, date boundaries, statuses, reset, empty results and foreign-site/shift denial, using existing test conventions.
- Runnable JavaScript checks for PNG load/decode/render failure containment, export independence from viewport transformations, and cleanup; filter/refresh/selection behavior as applicable to the chosen implementation.
- Inspect each selected test's network/configuration behavior first. Use an explicit list of local synthetic tests, initially `node --test tests/JavaScript/operator-dicom-viewer.test.mjs tests/JavaScript/operator-worklist-filters.test.mjs tests/JavaScript/operator-dicom-png-browser.test.mjs`, plus inspected in-scope local regressions. Do not use a blanket JavaScript test glob or execute production-login/production-validation tests.
- Actual synthetic browser download: inspect PNG signature, native dimensions and expected nonblank asymmetric pixel content, including image edges and orientation. A mocked canvas or static source assertion alone does not prove export correctness.
- Relevant existing Operator DICOM/AI authorization, individual/ZIP export, verification, basic examination and radiography regressions. Existing suites include `Mvp14ImageGatewayIntegrationTest.php`, `OperatorPortraitDicomViewerTest.php`, `operator-dicom-viewer.test.mjs`, localization tests and affected Operator field-operation tests; discover any additional material coverage.
- `npm run build`, applicable `vendor/bin/pint --test`, and `git diff --check`; complete other repository-required checks proportionate to the final change.
- Report browser/runtime limitations truthfully. If the actual image-export check cannot be run, return the verification gap for review rather than asserting PNG acceptance.

### Required evidence

Return actual execution-start HEAD/branch/working-tree state; exact governing task revision and implementation baseline; final revision or precise working-tree diff; changed files; exact executed commands and observed results; PNG dimensions/content and negative-access evidence; four-list filter/refresh/selection evidence; regressions and skipped checks; deviations, risks and stop conditions. Local evidence is not CI or final acceptance.

## Stop conditions

- The exact published task revision or implementation baseline cannot be resolved, or a material pending execution/review/remediation affecting this task is discovered.
- Access cannot be preserved, image export would require a new dependency/service/storage/schema, or supported image types require unapproved frame/VOI/orientation policy.
- A filter would expose foreign/historical unauthorized records, require new clinical states, or alter clinical action eligibility.
- Task baseline drift, overlapping user work, or a material architecture/product conflict makes execution unsafe.
- Requested work expands to other lists/modules, bulk PNG export, live private data, deployment or external side effects.

## Side-effect authorization

### Authorized planning actions

- Inspect repository/review state, create or revise this task, and commit only bounded planning changes locally on `task/operator-dicom-png-and-worklist-filters`.
- Create that local feature branch from the verified candidate baseline if absent. Preserve an existing branch and stop if its history or work conflicts; do not reset or replace it.
- A Draft commit is a durable planning snapshot only. Do not push a Draft as execution-ready or start implementation before T5 passes.
- After human approval and task validation, publish this exact task by local commit and normal non-force push of `task/operator-dicom-png-and-worklist-filters` to `origin`, including initial remote branch creation/upstream tracking. Supply the observed immutable publication SHA in the handoff.

### Authorized Executor actions after validation/publication

- Make local source/test/translation edits and run synthetic local verification only within this task's scope.
- Automatically commit verified in-scope implementation on `task/operator-dicom-png-and-worklist-filters`, without repeated per-commit human confirmation.
- After the applicable verification for the committed slice passes and `git diff --check` is clean, automatically push only that feature branch to `origin` in `Madeena-software/mhcs-core`. An initial push may create the same named remote branch and establish upstream tracking; subsequent pushes must be normal fast-forward pushes.
- Stage explicit in-scope paths only. Exclude unrelated work, credentials, patient data, generated private files, and `CHAT_MEMORY.md`. Do not commit merely to work around failed or missing verification.
- If the remote branch has advanced incompatibly, preserve both histories and return the conflict to Planner/Reviewer. Never force-push or rewrite published history.
- Return exact task/implementation commit SHAs, verification commands/results, pushed branch, and observed push result. A successful local commit does not prove a successful push; a push does not constitute implementation acceptance.

### Not authorized

- Commits or pushes to `main` or any other branch, force-push (including `--force-with-lease`), published-history rewriting, PR/issue writes, merge to `main`, deployment, or release.
- Dependency installation, production/external-service mutation beyond the bounded Git push above, private-data inspection, permission changes, destructive cleanup, or unrelated changes.

## Expected terminal outcome

`REVIEW REQUIRED` after valid publication and bounded execution, with observed PNG, filter, authorization and preservation evidence. The Executor must not self-declare implementation acceptance or release readiness.
