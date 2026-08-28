---
title: Operator DICOM Canonical Laterality Presentation
document_id: MHCS-TASK-OPERATOR-DICOM-CANONICAL-LATERALITY-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-28
scope:
  - correction of operator DICOM viewer default and reset laterality presentation
  - asymmetric regression coverage distinguishing canonical from horizontally mirrored presentation
  - preservation of explicit view-only controls and protected retrieval boundaries
authority_note: This published task authorizes only the bounded implementation and local verification defined here. It does not authorize MPIPS changes, DICOM PixelData mutation, deployment, or release.
---

# Executable Task

This file defines a bounded software-delivery contract for implementation.

A validated task MUST provide enough authority, scope, acceptance, verification, and stop-condition information for an Executor to proceed without inventing material product, requirement, architecture, scope, or approval decisions.

## Task identity

**Task title:**  
`Operator DICOM Canonical Laterality Presentation`

**Task path:**  
`.agents/tasks/operator-dicom-canonical-laterality-presentation.md`

**Task contract state:**  
`Validated/Published upon immutable publication of this exact content.`

The task file is the executable delivery contract.

Execution and review lifecycle states such as `In Execution`, `Review Required`, `Remediation Required`, and `Accepted` SHOULD normally be tracked by orchestration, review records, repository metadata, or another mechanism that preserves the exact governing task revision.

A lifecycle-status update MUST NOT silently replace the immutable task revision that governed an execution attempt.

When remediation materially changes this executable contract, edit the same stable task path, return it to Draft as needed, and republish it as a new immutable governing task revision before renewed execution.

**Delivery objective / Work Package / MVP:**  
`Operator DICOM viewer canonical laterality presentation repair and regression hardening`

**Owner / designated planning authority:**  
`Planner/Reviewer under designated human authority dated 2026-08-28`

## Delivery context

The Operator DICOM Viewer in `resources/js/operator-dicom-viewer.js` configures:

```javascript
const DEFAULT_VIEW = Object.freeze({ rotation: 0, flipHorizontal: true, flipVertical: false });
```

and `applyDefaultViewport(viewport)` applies `viewport.setCamera({ flipHorizontal: DEFAULT_VIEW.flipHorizontal, flipVertical: DEFAULT_VIEW.flipVertical })`.

The complete viewer presentation path is:

```text
stored DICOM bytes
→ protected Image Gateway DICOM response
→ Cornerstone WADO-URI stack
→ viewport.setStack(...)
→ applyDefaultViewport(...)
→ viewport.setCamera({ flipHorizontal: true, ... })
→ render
```

Consequently, the viewer presentation layer deliberately horizontally reflects the decoded image during initial presentation. As a result, DICOM PixelData that is already in canonical frontal-radiograph orientation is initially presented with an unintended horizontal reflection.

`resetViewport()` reuses `applyDefaultViewport()`, so Reset also restores the mirrored state.

The existing JavaScript test in `tests/JavaScript/operator-dicom-viewer.test.mjs` currently encodes:

```javascript
['camera', { flipHorizontal: true, flipVertical: false }]
```

during reset, which protects the defect rather than catching it.

The unintended default was introduced historically by commit `b37eee839d72f44acf30ef7890ac06a80bb64c26`, which added Reset/rotate/flip/fullscreen viewer controls and introduced the horizontally flipped default state.

Server-side DICOM retrieval paths (`app/Modules/ImageGateway/Application/Services/ImageGatewayCaptureService.php` and `app/Http/Controllers/Operator/ImageGatewayController.php`) return stored DICOM bytes as `application/dicom` without laterality transforms. Therefore, the repair must remain strictly at the MHCS Core viewer-presentation boundary.

Primary reproduction evidence supplied by the human:
- Primary study: `DCM-TPULL5JZ.dcm` (ViewPosition: PA, Rows: 4114, Columns: 3045, PhotometricInterpretation: MONOCHROME2, uncompressed 16-bit PixelData).
- Prior study: `DCM-R8PFMRUN.dcm`.
- In both cases, independent inspection of PixelData confirmed that the MHCS screenshot corresponds to a horizontally flipped representation of the canonical PixelData.
- These real studies are reproduction evidence only; private or clinical DICOM content must not be committed to the repository.

Intended presentation behavior:
- Initial/default viewport: `rotation = 0`, `flipHorizontal = false`, `flipVertical = false`.
- Reset viewport: returns to that same canonical state (`rotation = 0`, `flipHorizontal = false`, `flipVertical = false`) with normal camera reset/fit intact.
- Explicit `Balik horizontal` (horizontal flip): toggles active viewport horizontal state reversibly (`false` → `true` → `false`) as a view-only interaction without mutating stored DICOM bytes or study state.
- Explicit vertical flip & rotate controls: remain functional with existing view-only semantics.
- PA/AP behavior: PA and AP projections must NOT automatically cause a left-right reflection; frontal projections are conventionally presented facing the patient (patient-right on viewer-left, patient-left on viewer-right) when source PixelData is canonical.

## Baseline and task revision

**Implementation baseline:**  
`d02f38408e2baadf944e334aec11d9a260ca2f55`

**Task revision:**  
`The full SHA of the commit containing this exact task content, supplied by the Planner after publication.`

The immutable revision is supplied externally by version-control history upon publication. The task body does not need to embed the commit SHA that contains itself.

The implementation baseline and governing task revision are separate references. Do not change the implementation baseline silently during execution.

## Objective

Correct the Operator DICOM Viewer so that DICOM PixelData that is already in canonical frontal-radiograph orientation is initially displayed and reset without horizontal mirroring (`flipHorizontal: false`), preserve explicit view-only camera controls, correct existing tests that assert mirrored defaults, and add an asymmetric regression fixture that validates presentation semantics by distinguishing canonical from horizontally mirrored presentation.

## Authoritative inputs

### Governing authority

- Human authority direction dated 2026-08-28 identifying the canonical laterality presentation defect and supplying reproduction evidence (`DCM-TPULL5JZ`, `DCM-R8PFMRUN`).
- `.agents/context/project.md` and `.agents/context/modules/operator/project.md` §Read-only image access: Operator DICOM viewer displays returned DICOM with view-only interactions without mutating stored DICOM bytes or changing server-side retrieval.
- `.agents/context/modules/image-gateway/project.md` §Access and distribution & §Submission boundary: Image Gateway stores canonical NPZ and generated DICOM objects and serves protected DICOMs without performing presentation transforms.
- `docs/mvp/decision-log.md` (MVP-DEC-035, MVP-DEC-036, MVP-DEC-037, MVP-DEC-041) and `.agents/tasks/operator-current-tab-dicom-viewer.md`.

### Requirement traceability

- `IMG-006`, `IMG-007`, `IMG-013`, `IMG-028`, `IMG-060`, MVP-DEC-035/036 → Protected DICOM retrieval, download, and immutable storage.
- `OPR-108`, `OPR-118` → Accurate, usable, read-only Operator DICOM study display.
- `UIL-001`, MVP-DEC-037 → Indonesian viewer controls (`Balik horizontal`, `Reset orientasi`, etc.).

## Scope

### In scope

- Remove the unintended default horizontal mirror from the Operator DICOM viewer (`DEFAULT_VIEW` in `resources/js/operator-dicom-viewer.js`).
- Ensure initial rendering is canonical/non-mirrored (`rotation = 0`, `flipHorizontal = false`, `flipVertical = false`).
- Ensure Reset restores canonical/non-mirrored orientation (`rotation = 0`, `flipHorizontal = false`, `flipVertical = false`).
- Preserve explicit horizontal flip functionality (`Balik horizontal` toggles active viewport horizontal flip reversibly).
- Preserve explicit vertical flip functionality.
- Preserve rotate (left/right), zoom, pan, VOI, fullscreen, protected DICOM retrieval, download, authorization, and unrelated viewer behavior.
- Correct existing JavaScript tests in `tests/JavaScript/operator-dicom-viewer.test.mjs` that currently encode `flipHorizontal: true` as the expected reset/default state.
- Add regression coverage that distinguishes an original image from its horizontally mirrored version using a deterministic, non-sensitive asymmetric fixture (where `canonical(source) != horizontalMirror(source)`).
- Ensure the regression validates presentation semantics rather than merely asserting that a constant has a particular textual value.
- Keep DICOM PixelData, Image Gateway storage/retrieval, MPIPS, and conversion behavior unchanged.

### Out of scope

- MPIPS changes;
- NPZ orientation changes;
- TIFF orientation changes;
- DICOM PixelData mutation;
- TIFF-to-DICOM orientation compensation;
- New automatic PA/AP left-right mirroring;
- Changes to ViewPosition semantics;
- Changes to PatientOrientation metadata;
- Unrelated viewer redesign;
- Replacing Cornerstone;
- Dependency upgrades unless independently required and returned to Planner;
- Route/controller/storage redesign;
- AI behavior;
- Clinical interpretation;
- Deployment;
- Release.

### Preserved behavior

- Cornerstone-based current-tab DICOM viewer;
- Automatic VOI;
- Zoom;
- Pan;
- Rotate left/right;
- Explicit horizontal flip;
- Explicit vertical flip;
- Fullscreen;
- Protected inline DICOM endpoint (`operator.study.dicom`);
- Normal DICOM download (`operator.study.download`);
- Same-site/current-shift authorization boundaries;
- Read-only study semantics;
- Original DICOM bytes unchanged.

## Dependencies and assumptions

### Dependencies

- Existing installed `@cornerstonejs/core@5.7.0`, `@cornerstonejs/dicom-image-loader@5.7.0`, and `dicom-parser`.
- Existing test runners (Node test runner, PHPUnit, Vite build, Pint, git diff check).

### Approved assumptions

- Stored DICOM PixelData produced by MPIPS is already in canonical radiograph orientation (facing the patient; patient-right on image-left for frontal views).
- The observed left-right reflection is caused solely by the client-side presentation layer (`DEFAULT_VIEW.flipHorizontal: true`).
- Real patient DICOM files are reproduction evidence only and must not be committed to the repository.

### Remaining approval requirements

- User-led verification using authorized non-public reproduction studies may be performed locally by the user, but private DICOM content must not be committed, logged, or included in repository fixtures.
- Git commit, push, PR creation, deployment, and release remain unauthorized for the future implementation Executor unless explicitly authorized by repository policy or separate human instruction.

## Required capabilities

- Repository read/write, Node test runner (`node --test`), PHPUnit (`vendor/bin/phpunit`), Vite build (`npm run build`), Pint (`vendor/bin/pint --test`), Git check (`git diff --check`).

## Execution constraints

### Constraints

- Apply repository reuse discipline. Keep changes focused strictly on `resources/js/operator-dicom-viewer.js`, `tests/JavaScript/operator-dicom-viewer.test.mjs`, and associated regression fixtures/tests.
- Do not modify backend DICOM serving (`ImageGatewayCaptureService`, `ImageGatewayController`, storage, or routes).
- Do not modify MPIPS or NPZ normalization/conversion.
- Do not introduce metadata-driven heuristics (e.g. checking `ViewPosition === 'PA'` to flip images).
- Use a synthetic/non-clinical asymmetric fixture for regression testing; do not rely on cardiac silhouette or commit private medical data.
- The asymmetric regression fixture must be deterministic, non-sensitive, clearly distinguish left from right, fail if default image presentation becomes horizontally mirrored, and demonstrate that explicit horizontal flip produces the mirrored state.

## Acceptance criteria

- [ ] Opening a DICOM whose PixelData is already canonical does not introduce a horizontal reflection by default.
- [ ] Default camera orientation has `flipHorizontal = false` and `flipVertical = false`.
- [ ] Reset returns rotation to 0 and both flip states to false.
- [ ] Explicit horizontal flip remains functional and reversibly toggles the active viewport.
- [ ] Explicit vertical flip and rotate controls remain functional.
- [ ] No automatic PA/AP left-right mirroring is introduced.
- [ ] No MPIPS, NPZ, TIFF, DICOM-generation, stored DICOM, or PixelData behavior is changed.
- [ ] Existing viewer regressions remain passing after correcting obsolete mirrored-default expectations.
- [ ] An asymmetric regression fixture distinguishes canonical from horizontally mirrored presentation and fails if the viewer again defaults to the mirrored state.
- [ ] Existing protected viewing/download/authorization and unrelated viewer interactions remain unchanged.

## Verification requirements

### Required checks

```bash
TARGET="." node --test tests/JavaScript/operator-dicom-viewer.test.mjs
TARGET="." vendor/bin/phpunit
TARGET="." npm run build
TARGET="." vendor/bin/pint --test
TARGET="." git diff --check
```

- If a browser-level asymmetric regression is added, report its actual observed result.
- If user-led manual reproduction is performed with the real DICOM study, report sanitized PASS/FAIL without logging private data.

### Required evidence

The Executor MUST report:
- Implementation revision or exact working-tree state.
- Exact commands executed and observed terminal outputs.
- Asymmetric regression results demonstrating detection of default vs horizontally mirrored presentation.
- Confirmation that no binary DICOMs, private patient data, or unauthorized side effects were introduced.

## Stop conditions

The Executor MUST stop implementation and return the issue to planning if:
- Correcting the defect appears to require changing MPIPS or DICOM PixelData;
- The observed viewer behavior cannot be explained by the current presentation path after the default flip is removed;
- A broader orientation policy requiring metadata-driven transforms becomes necessary;
- Dependency replacement or viewer-architecture replacement becomes necessary;
- An asymmetric regression cannot be implemented without introducing sensitive/private fixture data;
- Execution reveals materially broader orientation defects outside this bounded objective;
- Implementation would require changing product/clinical orientation policy beyond the behavior already specified here.

## Side-effect authorization

Implementation authorization is bounded to the task's defined execution scope.

### Explicitly authorized side effects

- Modify `resources/js/operator-dicom-viewer.js`, `tests/JavaScript/operator-dicom-viewer.test.mjs`, and add non-sensitive test fixtures/tests within the bounded execution scope.
- Execute local tests and build commands (`node --test`, `vendor/bin/phpunit`, `npm run build`, `vendor/bin/pint`, `git diff --check`).

### Not authorized:

- Git commit;
- Push;
- Pull-request creation;
- Deployment;
- Publication;
- Release;
- Destructive data operations;
- Destructive infrastructure operations;
- Production mutation;
- External-system mutation;
- Dependency installation or replacement;
- Permission expansion;
- Secret access, copying, or disclosure;
- Unrelated repository changes;
- Committing private DICOM or patient data.

## Expected terminal outcome

`REVIEW REQUIRED` — return one reviewable implementation state with observed verification evidence and the asymmetric regression test results for Reviewer evaluation.
