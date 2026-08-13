---
title: Operator Portrait DICOM Viewer and Monitor Popup
document_id: MHCS-TASK-OPERATOR-PORTRAIT-DICOM-VIEWER-001
version: 1.0
status: draft
language: en-US
last_updated: 2026-08-13
scope:
  - polished read-only Operator DICOM study screen
  - portrait-oriented named browser monitor popup
  - existing protected DICOM delivery and normal attachment download
authority_note: This task becomes executable only when this exact content is committed unchanged and its immutable commit SHA is supplied in the Executor handoff. It does not authorize public image access, clinical image editing, deployment, or external calls.
---

# Executable Task

## Task identity

**Task title:**
`Operator Portrait DICOM Viewer and Monitor Popup`

**Task path:**
`.agents/tasks/operator-portrait-dicom-viewer.md`

**Task contract state:**
`Draft — publish unchanged and resolve the immutable task SHA before execution.`

**Delivery objective / Work Package / MVP:**
`Pre-deployment local MVP — resolve the final product-facing DICOM viewer feedback item`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The accepted Operator/Image Gateway flow already supplies a protected
Cornerstone study page and normal raw-DICOM download. The local-rehearsal
feedback records that this surface is visually inadequate, can remain at
“Loading DICOM” without a useful failure state, and is not suitable for the
dedicated vertical monitor used by Operators.

The CTO selected the current-page fallback with an explicit **Open on monitor**
action. It must open the existing authorised study page in a named browser
window that is visually optimised for a portrait display. If the browser blocks
the popup, the same study remains usable in the current tab. The viewer is
strictly read-only: automatic VOI, zoom, and pan are retained; no window/level,
brightness, contrast, annotation, measurement, rotation, inversion, crop, or
image mutation is permitted.

## Baseline and task revision

**Implementation baseline:**
`720fb297c329d20aed516ca3418c0ad8f016b511` — accepted readable display
references and Indonesian UI/browser-test correction. Its known local browser
runner hang remains a verification limitation, not a product failure.

**Related accepted predecessors:**

- `.agents/tasks/operator-readable-display-references.md @ 91982bb43f591e818299df37c011de5e9490570c`, including accepted remediation at `f4b3e33` and browser assertion correction at `720fb297c329d20aed516ca3418c0ad8f016b511`.
- `.agents/tasks/operator-awaiting-ai-release-and-safe-navigation.md @ d76086c6bb04007c53fd06886e48cd5e2e95b7f3`.

**Task revision:**
`Resolved when this Draft is committed unchanged; the Planner supplies that full SHA before Executor handoff.`

## Objective

Deliver a polished, Indonesian, read-only DICOM study screen that presents the
existing Cornerstone viewport well in the current tab and can explicitly open
the same protected study in a named portrait-monitor popup, while preserving
the existing DICOM authorization and ordinary authenticated download.

## Authoritative inputs

### Governing authority

- `docs/mvp/evidence/mvp-local-deployment-readiness.md` §Manual
  Operator-to-DICOM checklist items 7–10 and §Planner/Reviewer feedback handoff
  item 5: a vertical, read-only Cornerstone viewer; a polished result surface;
  useful failure state; and the approved use of a separate browser window for a
  vertical monitor.
- CTO design decision in this planning conversation: use option 1—remain in the
  current tab with an explicit **Open on monitor** action; opening the existing
  study in a popup is allowed; redesign the UI/UX using the supplied local
  references.
- `docs/operator/reference/claude-design/photo-booth.jsx` and
  `docs/operator/reference/claude-design/style.css` §Photo Booth: visual
  reference only for the compact viewer toolbar, centred dark imaging stage,
  restrained status treatment, and portrait image framing. Its simulated
  workflow and all editing tooling are not product authority and are excluded.
- `/var/www/mhcs-operator-core/claude-design/photo-booth.jsx` and
  `/var/www/mhcs-operator-core/claude-design/style.css` §Photo Booth: the same
  local visual reference; do not copy its DICOM-edit implementation.
- `.agents/context/modules/image-gateway/project.md` §Completion rules and
  §Access and distribution: each returned DICOM is viewable and downloadable
  only by an authenticated current-shift Operator authorised for the active
  site; raw NPZ remains unavailable.
- `docs/mvp/decision-log.md` MVP-DEC-035 and MVP-DEC-036: retain the existing
  standard authenticated `.dcm` attachment and current-site/current-shift
  visibility; no public URL is allowed.
- `docs/mvp/decision-log.md` MVP-DEC-037 and `.agents/context/ui-language.md`:
  every new MHCS-authored browser string, including popup-blocked and viewer
  failure status, is an Indonesian `lang/id.json` entry.

### Requirement traceability

- `MVP local-rehearsal feedback item 5` → product-facing portrait DICOM viewer,
  useful failure state, and separate-monitor presentation.
- `MVP local-rehearsal checklist items 7–10` → a returned DICOM remains
  viewable and normally downloadable by any authorised same-site/current-shift
  Operator.
- `MVP-DEC-035`, `MVP-DEC-036`, `OPR-108` → no public access, raw NPZ exposure,
  or relaxed authorisation.
- `UIL-001`, `MVP-DEC-037` → Indonesian JSON-backed UI copy.

## Scope

### In scope

- Redesign the existing `operator.study` screen as a professional read-only
  study surface. Reuse the current study display reference, existing protected
  inline DICOM endpoint, installed Cornerstone packages, and standard download
  endpoint. The principal visual hierarchy is: concise study/status header,
  dominant centred dark image stage, small read-only interaction guidance, and
  ordinary download/back actions.
- Make the image stage visually and dimensionally suitable for a portrait
  monitor: it must use the available vertical browser space, centre the image
  without stretching/cropping it, and stay usable when the same page is viewed
  in a normal current-tab viewport. Request a portrait popup size, but do not
  rely on browsers honouring that size; the CSS must adapt to the actual window.
- Add one explicit native **Open on monitor** control on the current study
  page. On a user gesture it opens the **existing** `operator.study.show` URL
  in one stable named window (for example `mhcs-dicom-monitor`) and focuses an
  already-open window of that name. Do not create a second route, route
  parameter, query flag, token, or public study URL.
- Make that named-window rendering compact and monitor-focused: suppress the
  normal broad workstation navigation, retain the display reference, viewer
  state, read-only guidance, and normal DICOM download, and dedicate the
  remaining space to the vertical image stage. Detect the named monitor window
  locally (for example by its `window.name`), not with a server-side access
  bypass or a public URL variant.
- If the browser blocks the popup, focus fails, or JavaScript is unavailable,
  retain the fully functional current-tab viewer and show a clear Indonesian
  non-error status telling the Operator to continue in the current tab or
  allow the popup. The control must be keyboard reachable and expose an
  accessible label/status.
- Make loading and failure states deterministic and useful. While the protected
  DICOM request is pending, show an explicit loading state. On parsing, fetch,
  rendering, or viewport-initialisation failure, replace the indefinite loading
  presentation with an Indonesian error state that leaves the normal download
  and back/result actions available. A later successful render must identify
  the ready state. Do not disclose storage keys, HTTP diagnostic bodies,
  credentials, raw DICOM content, or internal IDs in the browser.
- Preserve and make visible the current read-only interaction contract:
  automatic VOI; pointer drag to pan; wheel to zoom. Resize/reflow the existing
  Cornerstone rendering engine safely when the page/window changes size so the
  portrait popup does not leave a stale or blank canvas. Do not add a clinical
  editing toolbar or enable an installed Cornerstone tools package merely
  because it is present.
- Put all new or changed browser-facing MHCS copy—including action labels,
  tooltip/accessible labels, popup fallback, loading/ready/failure state, and
  interaction guidance—in `lang/id.json`. Existing technical term `DICOM` may
  remain unchanged.
- Add/adjust focused Feature, localization, and browser coverage for the
  current-tab surface, normal `download` anchor, named popup request/fallback
  contract, Indonesian copy, ready/error state transitions, and the unchanged
  active-site/current-shift denial boundaries. Tests may simulate the viewer
  loader; they must not inspect/copy a real clinical DICOM.

### Out of scope

- Window/level, brightness, contrast, annotation, measurement, crop, rotation,
  flip, inversion, LUT presets, image export, editing, DICOM mutation,
  diagnostics, AI findings, or any clinical decision feature.
- A popup-only workflow, a new monitor-specific route/layout/controller,
  window-specific authentication/token, public/temporary object URL, direct
  browser storage access, raw NPZ access, or changing result visibility.
- New package/dependency installation, Cornerstone upgrade/replacement,
  generic viewer framework, queue/worker/storage/MPIPS changes, migration,
  API contract change, test-runner repair, local deployment, production
  mutation, or release.
- Recreating the reference prototype’s simulated patient workflow, edit
  palette, AI panel, or its example patient data.

### Preserved behavior

- The existing `operator.study.show`, `operator.study.dicom`, and
  `operator.study.download` routes continue to use the internal study UUID and
  their current authentication, active-site/current-shift authorization,
  no-store/private headers, and denial behaviour. The human display reference
  is presentation only.
- The DICOM payload continues to be read only from the existing protected
  inline endpoint. The raw-DICOM action remains a literal standard browser
  attachment link with the existing `download` behavior; it is not converted
  to JavaScript fetch, blob, popup download, or a special link workflow.
- Current-tab viewing is always available regardless of whether a popup can be
  opened. Popup failure is not a capture/DICOM failure.
- Automatic VOI, zoom, and pan remain the only image interactions. Existing
  capture, MPIPS, Image Gateway persistence, study bytes/checksum, worklists,
  display references, queue state, and authorization policy remain unchanged.

## Dependencies and assumptions

### Dependencies

- The accepted baseline supplies an existing protected study page, installed
  Cornerstone core/image loader, display references, and authorised normal
  DICOM download.
- The same-origin browser session is available to the popup. Browser popup
  policy may block a new window even after a user action; that case is an
  expected UI fallback, not an authorization change.

### Approved assumptions

- A stable named browser window is sufficient for the dedicated vertical
  monitor MVP. Its requested dimensions are a preference only; actual monitor
  size/orientation is browser and operating-system controlled.
- The reference designs establish visual direction only. They do not authorize
  image editing, simulated workflows, copy reuse, or patient-data display.
- The existing documented local browser-runner hang does not itself authorize
  changing Playwright configuration. If it prevents browser execution again,
  record the exact gap and obtain the required manual visual evidence instead.

### Remaining approval requirements

- None beyond repository changes within this task. Git commit, push, pull
  request, deployment, test-data reset/reseed, AWS/S3/MPIPS calls, production
  mutation, real DICOM/NPZ inspection, and release remain unauthorised.

## Required capabilities

- Repository read/write, shell, PHP/Laravel tests, frontend build and
  formatter.
- Browser automation when the existing local runner can execute; otherwise a
  local browser for the prescribed manual visual check.
- No AWS/S3, MPIPS, production, external network, credential, or real clinical
  file capability is required or authorised.

## Execution constraints

- Reuse `resources/views/operator/study.blade.php`,
  `resources/js/operator-dicom-viewer.js`, the existing Operator layout only as
  necessary, `lang/id.json`, installed Cornerstone code, and the existing
  focused tests. Prefer CSS and a few local DOM operations over a new component
  system, route, or dependency.
- The named popup must use the exact existing protected study URL. The study
  UUID may remain in that already-authorised URL but must not become the primary
  page label or be copied into new UI copy. Do not add an identifier to the
  popup name that leaks study/member/site information.
- Opening the monitor must happen only after the explicit user action. Do not
  auto-open, retry-open, or repeatedly focus windows from polling/loading code.
- Keep the popup safe under browser limitations: an absent popup object or a
  `closed` popup must not throw, change processing state, or remove current-tab
  controls. Do not use `window.opener` data for authorization or clinical data;
  the popup reloads the server-authorised study normally.
- Maintain keyboard focus visibility, semantic button/link elements, status
  and alert roles, sufficient text contrast, and non-colour-only readiness and
  error cues.
- Do not log, expose, snapshot, or check in DICOM bytes, raw NPZ, storage keys,
  credentials, real patient/member data, or external response bodies.

## Acceptance criteria

- [ ] An authorised Operator opening a returned study sees a polished,
  Indonesian, read-only DICOM screen with its short `DCM-…` display reference,
  dominant dark image stage, explicit load/ready/error state, standard DICOM
  download, and a return-to-results action.
- [ ] The viewport fits and remains legible in a narrow/tall browser window;
  actual image pixels are not stretched or cropped by the portrait layout. The
  normal current-tab layout also remains usable.
- [ ] **Open on monitor** is a user-operated accessible control that opens and
  focuses one stable named browser window at the existing study URL. That popup
  shows a compact portrait-monitor viewer rather than full workstation chrome,
  while retaining the same protected DICOM payload, state, display reference,
  guidance, and standard download.
- [ ] If the popup is blocked or JavaScript is unavailable, the current-tab
  viewer and standard download remain available; JavaScript-enabled blocked
  popups give a clear Indonesian fallback message.
- [ ] The viewer never stays indefinitely at “Memuat DICOM…” after a known
  loader/parser/fetch/render/viewport error. It instead presents an Indonesian
  non-sensitive failure message with the existing download and results actions
  still operable.
- [ ] Only automatic VOI, zoom, and pan are exposed. No image-adjustment,
  annotation, measurement, editing, or DICOM-mutating UI/API is introduced.
- [ ] The unchanged existing protected routes deny unauthorised, foreign-site,
  inactive/revoked/current-shift-invalid, and non-Operator access; no public
  DICOM/NPZ/object URL or relaxed authorization appears.
- [ ] All new visible/accessible MHCS text is Indonesian and registered in
  `lang/id.json`; no new hard-coded browser-visible English is introduced.

## Verification requirements

### Required checks

- Add focused Feature/localization coverage confirming the redesigned study
  markup uses the existing authorised routes and normal `download` anchor,
  contains no new public URL or image-edit controls, and renders all changed
  Indonesian copy from `lang/id.json`.
- Add focused JavaScript/unit-or-browser coverage for the named-window request,
  blocked-popup fallback, ready/error state transition, and safe resize path.
  Mock the viewport/loader where needed; do not require or read real DICOM
  bytes.
- Run the focused tests, then `vendor/bin/phpunit`, `npm run build`,
  `vendor/bin/pint --test`, and `git diff --check`.
- When the existing browser runner works, run the focused DICOM rehearsal
  browser test and extend it only for this task’s current-tab and portrait
  popup contract. If the known runner hang recurs, do not alter runner
  configuration: capture the exact command/output/process limitation and
  complete the manual check below instead.
- Manual visual check with the seeded/synthetic local study only: open the
  study in the current tab; use **Buka di monitor**; confirm the named popup is
  portrait-oriented and compact, the image is centred and intact after resize,
  zoom/pan works, automatic VOI is shown, no edit controls appear, a forced
  loader failure replaces loading with the useful Indonesian error state, and
  **Unduh DICOM** behaves as a normal browser download. Confirm a same-site,
  current-shift second Operator can view/download the same result, while an
  unauthorised boundary remains denied. Record no credentials, IDs, file bytes,
  private keys, screenshots containing personal data, or external payloads.

### Required evidence

The Executor must report the immutable implementation revision; exact commands
and observed results; changed tests; browser/manual evidence actually observed;
the final popup/fallback behaviour; known browser-runner limitations; and any
deviation or blocker. Local checks and synthetic/manual evidence must not be
represented as production or real-clinical integration evidence.

## Stop conditions

- Stop if meeting the portrait popup requirement would require a new public or
  unauthorised route, relaxed current-site/current-shift policy, a token/link
  that bypasses the session, raw NPZ exposure, direct storage access, or a
  viewer dependency change.
- Stop if the supplied visual references are found to require image editing or
  simulated workflow behaviour to reproduce the required UI; return for a
  narrower approved design decision instead.
- Stop if the existing same-origin protected study route cannot be rendered in
  a named popup without a material authentication/authorization change.
- Stop if a required live MPIPS/AWS call, real DICOM/NPZ handling, deployment,
  or browser-runner configuration change becomes necessary to satisfy an
  acceptance criterion.
- Stop if the baseline changes in overlapping viewer, route, authorization, or
  localisation files before execution and cannot be reconciled safely.

## Side-effect authorization

### Explicitly authorised side effects

- Repository changes only in the existing Operator study view/viewer/layout as
  needed, `lang/id.json`, and focused tests.
- Local test/build/format/diff commands and synthetic/local browser manual
  verification only.

Not authorised: Git commit, push, pull request, deployment, database reset or
reseed, external calls, AWS/S3/MPIPS access, production mutation, dependency
installation, credential access/disclosure, or real clinical-file handling.

## Expected terminal outcome

`REVIEW REQUIRED` — return one immutable implementation revision with concise,
redacted verification evidence. The Planner/Reviewer determines acceptance and
whether the local-deploy/manual-test task can resume; acceptance is not deploy
or release authorization.
