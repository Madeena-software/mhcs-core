---
title: Operator Current-Tab DICOM Viewer Reliability
document_id: MHCS-TASK-OPERATOR-CURRENT-TAB-DICOM-VIEWER-001
version: 1.0
status: validated-on-publication
language: en-US
last_updated: 2026-08-13
scope:
  - browser-safe Cornerstone bundle bootstrap
  - reliable current-tab read-only DICOM study screen
  - removal of the monitor-popup product flow
authority_note: This task is validated/published only when this exact content is committed unchanged and its immutable SHA is supplied in the Executor handoff. It does not authorise dependency changes, private-object inspection, external calls, deployment, or release.
---

# Executable Task

## Task identity

**Task title:** `Operator Current-Tab DICOM Viewer Reliability`

**Task path:** `.agents/tasks/operator-current-tab-dicom-viewer.md`

**Task contract state:** `Validated/Published upon immutable publication of this exact content`

**Delivery objective / Work Package / MVP:** `Local MVP Operator-to-DICOM completion`

**Owner / designated planning authority:** `Faliq Adlan, CTO`

## Delivery context

The current production-built browser bundle aborts before the viewer runs:
Cornerstone's VTK dependency pulls in `xmlbuilder2`, whose Node `events`
dependency is externalised by Vite. `XMLBuilderCBImpl extends
undefined.EventEmitter`, producing the observed `Class extends value undefined`
error. The browser consequently makes no protected DICOM request.

The CTO also supersedes the previous monitor-popup decision: the current tab is
the sole polished Operator study surface. It must be portrait-friendly,
read-only, and reliable, with a normal DICOM attachment download and no popup
action.

## Baseline and task revision

**Implementation baseline:** `4cbde95affe73139006f43f4f68d863c7dd03ace`

**Superseded task:** `.agents/tasks/archive/operator-portrait-dicom-viewer.md @ b0b4597250137655ce38e03c93a45fb1104a41b4`; its popup-specific objective is superseded only here.

**Task revision:** `The full SHA of the commit containing this exact validated task content, supplied by the Planner after publication.`

## Objective

Deliver a browser-safe, polished current-tab Cornerstone DICOM study screen
that boots without the Node-`EventEmitter` bundle failure, requests the existing
protected inline DICOM only after it boots, and never leaves an Operator at an
indefinite loading state.

## Authoritative inputs

### Governing authority

- CTO local-testing feedback in `docs/mvp/evidence/mvp-local-deployment-readiness.md` §New user-led feedback for Planner/Reviewer: remove the popup flow, make the current tab the primary study UI, and resolve the non-rendering viewer.
- Sanitized local-browser evidence supplied by the CTO on 2026-08-13: `Class extends value undefined` from the production bundle and no inline-DICOM network request.
- `.agents/context/modules/image-gateway/project.md` §Access and distribution: authorised current-shift Operators view returned DICOM through the protected application route and download it as a standard authenticated `.dcm` attachment.
- `docs/mvp/decision-log.md` MVP-DEC-035, MVP-DEC-036, MVP-DEC-037, and MVP-DEC-041.
- `.agents/context/ui-language.md`: Indonesian JSON-backed browser copy.

### Requirement traceability

- `IMG-006`, `IMG-007`, `IMG-013`, `IMG-028`, `IMG-060`, MVP-DEC-035/036 → protected same-site/current-shift DICOM study and ordinary attachment download.
- `OPR-108`, `OPR-118` → usable read-only Operator result access.
- `UIL-001`, MVP-DEC-037 → Indonesian loading, ready, failure, navigation, and accessibility copy.

## Scope

### In scope

- Correct the browser build's Node-`events` incompatibility with the smallest local browser compatibility module and Vite resolution necessary for the existing installed Cornerstone/VTK packages to evaluate. The compatibility module may provide only the EventEmitter surface required to evaluate the unused XML-builder dependency; do not introduce a Node polyfill framework or new package.
- Keep Cornerstone code out of the common application bootstrap until a DICOM study page is present, and make a failed dynamic viewer-module bootstrap move that page from loading to the existing safe Indonesian unavailable/error state. This must not affect non-study Operator pages.
- Remove the monitor-popup control, popup fallback/status, named-window logic, popup CSS, and popup-specific tests/copy. Do not replace them with a new route, browser window, token, query parameter, or monitor-only layout.
- Redesign only the existing current-tab study page as a focused portrait-friendly read-only surface: compact study header with the `DCM-…` reference and read-only guidance; dominant centred dark vertical image stage; visible loading/ready/error state; normal download and return-to-results actions. Suppress broad workstation navigation on this one focused study page while retaining the explicit return action.
- Bound every viewer bootstrap, protected DICOM-load, parse, and viewport-render wait so an unsettled promise cannot leave “Memuat DICOM…” indefinitely. The timeout/error UI must be Indonesian, safe, and retain download/return actions; it must not expose exception text, URL, storage key, credential, raw DICOM, or clinical metadata.
- Preserve automatic VOI, wheel zoom, pointer pan, safe resize, the existing protected inline endpoint, the normal literal `download` anchor, display reference, and same-site/current-shift authorization.
- Add focused JavaScript, feature, localization, and build-level coverage for: no popup control/logic; safe failed dynamic bootstrap; bounded loading failure; existing protected inline/download URLs; Indonesian copy; and unchanged active-site/current-shift denial. A static production-bundle check must fail if the browser build again externalizes `events` or lacks the viewer bootstrap path.
- Update only the relevant local walkthrough/readiness evidence to replace the popup checklist with the current-tab outcome and record the observed bundle-root cause without private data.

### Out of scope

- DICOM mutation or editing: window/level, brightness, contrast, annotation, measurement, crop, rotation, flip, inversion, export, AI finding, or clinical decision support.
- Changing Cornerstone, VTK, DICOM-loader, or any other package version; dependency installation; a generic Node-polyfill plugin; package-manager lockfile churn; Vite/framework replacement; route/controller/service/schema/queue/storage/MPIPS/AWS changes.
- Public or temporary object URLs, direct browser storage access, raw NPZ access, DICOM-content inspection/parsing outside the existing protected viewer, production deployment, release, or external calls.

### Preserved behavior

- Browser access still uses only `operator.study.show`, `operator.study.dicom`, and `operator.study.download` with their internal UUID routes, existing session authentication, active-site/current-shift authorisation, and no-store/private headers.
- The raw-DICOM download remains a standard authenticated attachment link, not a JavaScript fetch/blob workflow.
- The viewer remains read-only: automatic VOI, zoom, and pan only.
- Failure of the viewer must not alter capture, processing, DICOM persistence, authorisation, or result-list state.

## Dependencies and assumptions

### Dependencies

- Existing installed `@cornerstonejs/core@5.7.0`, `@cornerstonejs/dicom-image-loader@5.7.0`, and transitive `@kitware/vtk.js@36.4.1` / `xmlbuilder2@4.0.3`.
- The known local browser suite may still hang. It is not permission to change browser-runner configuration.

### Approved assumptions

- The reported error is a browser bundle-evaluation failure, not an MPIPS, storage, DICOM endpoint, or returned-DICOM-content failure: no DICOM request is issued before the exception.
- The existing paperless local test may use synthetic data only; manual confirmation of the actual configured returned DICOM remains user-led and must not inspect or copy binary content.

### Remaining approval requirements

- User-led browser confirmation is required before the current-tab viewer can be accepted as product-ready. Commit, deployment, release, AWS/S3/MPIPS calls, production mutation, credential access, and private-object inspection remain unauthorised.

## Required capabilities

- Repository read/write, Vite/Node/PHP checks, local browser when available, and sanitized manual-browser evidence.

## Execution constraints

- Reuse the existing study blade, viewer module, app entry, Vite configuration, translation registry, and focused tests. Use CSS and small DOM code; no component framework or parallel viewer.
- The local EventEmitter compatibility module must not implement server APIs, expose globals, or be used outside Vite's browser resolution of the existing transitive dependency.
- The study page must not render the internal UUID as primary visible copy; the existing `DCM-…` reference remains its human label.
- Do not add logs that include private values. Browser-visible errors are generic Indonesian translation values only.
- Do not execute a live MPIPS/AWS call or inspect/copy NPZ/DICOM/private object bytes. Browser verification may only request the existing protected endpoint through the normal screen.

## Acceptance criteria

- [ ] A production Vite build no longer produces the `Class extends value undefined` failure caused by externalised Node `events`; the current-tab viewer module can begin execution and the protected inline-DICOM request is observable in an authorised manual browser run.
- [ ] The study screen has no monitor-popup action or popup-specific UI/logic. It is a focused, portrait-friendly current-tab, Indonesian, read-only surface with DCM reference, automatic VOI, zoom/pan guidance, normal attachment download, and return action.
- [ ] Any dynamic-module, loader, parser, protected-fetch, stack, or render failure leaves loading for a safe Indonesian unavailable/error state within the configured bounded wait and preserves download/return actions.
- [ ] Existing protected UUID routes, no-store/private inline response, normal `.dcm` attachment disposition, and same-site/current-shift allow/deny boundaries are unchanged.
- [ ] Focused checks, full PHP suite, production build, formatter, and diff check pass. Any browser-runner hang is recorded exactly; it is not represented as a passing browser test.

## Verification requirements

### Required checks

```bash
TARGET="." node --test tests/JavaScript/operator-dicom-viewer.test.mjs
TARGET="." vendor/bin/phpunit tests/Feature/Operator/OperatorPortraitDicomViewerTest.php tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php tests/Feature/Localization/MvpApplicationIndonesianUiLocalizationTest.php
TARGET="." vendor/bin/phpunit
TARGET="." npm run build
TARGET="." vendor/bin/pint --test
TARGET="." git diff --check
```

- Run the existing focused browser test only if it starts normally without configuration change. Otherwise record the exact hang/output and stop it safely; do not call it a pass.
- After implementation, the user performs one authorised local manual study run: the Network tab must show a protected inline-DICOM request after app bootstrap, the viewer must show ready or a safe error state rather than indefinite loading, and normal download must remain available. Return sanitized PASS/FAIL and screenshots only.

### Required evidence

Report implementation revision, changed files, all command results, the bundle-root-cause check, browser-runner result/gap, and the sanitized manual checklist. Do not report private IDs, binary data, private URLs, credentials, storage keys, or external responses.

## Stop conditions

- Stop if fixing browser evaluation requires a package upgrade/replacement, generic polyfill dependency, lockfile change, Node/server global in the browser, or a different viewer architecture.
- Stop if the expected local compatibility surface is insufficient and a broader API is needed; return the exact missing browser API to Planner/Reviewer.
- Stop if a real DICOM still fails after the bootstrap defect is removed but diagnosis would require inspecting/copying private bytes or calling MPIPS/AWS; return sanitized browser/network status to Planner/Reviewer.

## Side-effect authorization

### Explicitly authorised side effects

- Modify the bounded Vite/browser compatibility, study-viewer UI, Indonesian registry, focused tests, and local walkthrough/readiness evidence; run local tests/build/browser checks within the stated constraints.

Not authorised: Git commit, package installation/upgrade, lockfile change, deployment,
production/external mutation, MPIPS/AWS request, credential access, or private-object
inspection/copying.

## Expected terminal outcome

`REVIEW REQUIRED` — return one reviewable implementation revision with truthful
local verification evidence and a sanitized user manual current-tab checklist.
