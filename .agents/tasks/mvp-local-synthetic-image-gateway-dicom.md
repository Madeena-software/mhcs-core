---
title: Local Synthetic Image Gateway and Operator DICOM Rehearsal
document_id: MHCS-TASK-LOCAL-IMAGE-GATEWAY-001
version: 1.1
status: validated-published
language: en-US
last_updated: 2026-08-11
scope:
  - local/testing synthetic NPZ-plus-gain submission
  - Image Gateway durable private storage and synthetic study association
  - Operator read-only vertical DICOM viewer and raw-DICOM download
authority_note: This task is executable only at its immutable publication revision.
---

# Executable Task

## Task identity

**Task title:**
`Local Synthetic Image Gateway and Operator DICOM Rehearsal`

**Task path:**
`.agents/tasks/mvp-local-synthetic-image-gateway-dicom.md`

**Task contract state:**
`Validated/Published when this exact content is committed`

**Delivery objective / Work Package / MVP:**
`12 August MVP delivery target / local synthetic clinic rehearsal / WP-14 and WP-23`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The accepted local Operator rehearsal currently stops at X-ray readiness. This
task extends that *synthetic-only* journey through the existing X-ray call to
one complete NPZ-plus-gain submission, private Image Gateway acceptance, a
read-only vertical DICOM view, and authorised raw-DICOM download. It makes the
Operator and Gateway seams locally demonstrable without implementing, calling,
or simulating MPIPS conversion.

## Baseline and task revision

**Implementation baseline:**
`95e9de6ce9c16aa331b478adc34bd8e4b1b86d0e`

**Task revision:**
`Resolve from the commit that publishes this exact task content before execution.`

## Objective

**Objective:**
Enable a synthetic local clinic journey from the claimed X-ray admission to
durable private capture-set acceptance, one locally associated synthetic DICOM
study, a vertical read-only Cornerstone viewer, and a standard authenticated
raw-DICOM download, while leaving real Grabber parsing and MPIPS integration
as the only subsequent conversion work.

## Authoritative inputs

### Governing authority

- `docs/mvp/decision-log.md` — MVP-DEC-024, MVP-DEC-031, MVP-DEC-034, and MVP-DEC-035.
- `.agents/context/modules/operator/project.md` — NPZ submission and read-only DICOM access.
- `.agents/context/modules/image-gateway/project.md` — Gateway ownership, private storage, and distribution boundary.
- `docs/implementation/mhcs-core-requirements-matrix.md` — OPR-031..OPR-046, OPR-057..OPR-060, IMG-001, IMG-006..IMG-033, IMG-050, and IMG-057.
- [Cornerstone.js installation](https://www.cornerstonejs.org/docs/getting-started/installation/) and [DICOM image-loader API](https://www.cornerstonejs.org/docs/api/dicomimageloader/globals/) — approved viewer integration reference.

### Requirement traceability

- `OPR-031..OPR-046` → bounded Operator capture, complete submission, durable acceptance, and no raw-DICOM browser upload.
- `IMG-006..IMG-033` → Gateway-owned capture-set storage, checksum identity, authorised distribution, and raw-NPZ prohibition.
- `OPR-057..OPR-060` → automatic VOI display, zoom/pan-only read-only view, and standard authenticated raw-DICOM download.
- `IMG-050` and `IMG-057` → no MHCS NPZ-to-DICOM algorithm and no MPIPS call in this task.

## Scope

### In scope

- Extend the existing claimed/called X-ray admission with an authenticated,
  site- and current-shift-authorised capture form accepting one or more
  repository-owned synthetic radiograph `.npz` fixtures and their matching
  repository-owned synthetic gain `.npz` fixture.
- Keep unsubmitted file input browser-session-only; warn before navigation,
  refresh, close, or sign-out while files are selected, and never persist an
  unsubmitted upload server-side.
- Verify the synthetic fixture identity, ZIP/NPZ container form, configured
  byte/count bounds, gain-to-radiograph pairing, stable submission ID, and
  checksums. Reject renamed, altered, missing, duplicated, cross-case,
  cross-site, cross-shift, and replay-conflict inputs without durable objects
  or state changes.
- Add the minimal Image Gateway-owned migrations, models/contracts, and
  private encrypted-object metadata needed to persist an immutable capture
  set, its radiograph/gain objects, checksums, submitting Operator, site,
  booking/admission, timestamps, and processing status.
- Reuse `PrivateObjectStore`, `IdempotencyStore`, audit, outbox, trusted
  Operator authorisation, and the existing queue admission as the sole
  submission source. Do not create a second queue, storage system, identity
  model, or public image URL.
- Atomically record durable acceptance and advance the existing X-ray stage
  only after every capture and matching gain object is encrypted, stored, and
  checksum-verified; clean up stored objects if a later database/audit/outbox
  action fails.
- In `local` and `testing` only, attach exactly one committed repository-owned
  synthetic DICOM Part-10 fixture to a durably accepted synthetic capture set.
  This association must be explicit in the Gateway record, checksum-verified,
  private, idempotent, and fail closed in every other environment.
- Add an authenticated raw-DICOM attachment response and an authenticated
  Operator study page. Restrict both to the assigned
  Operator's active-site current-shift examination or an explicitly reopened
  repeat/correction case. Never issue a raw-NPZ link.
- Add a Vite-built Cornerstone integration using only pinned
  `@cornerstonejs/core`, `@cornerstonejs/dicom-image-loader`,
  `@cornerstonejs/tools`, and their direct parser dependency. Use a single
  vertical column of one or more viewports, automatic DICOM Window Center/Width
  or VOI LUT display, and zoom/pan only. Do not render controls or bind tools
  for manual window/level, contrast, brightness, rotation, annotations,
  measurements, inversion, or saved presentation state.
- Extend the local synthetic clinic walkthrough and fixtures so one dummy
  member can reach upload, accepted study, viewer, and raw-DICOM download.
- Add focused PHP and Chromium tests for the acceptance, cleanup, access,
  viewer, and download boundaries.

### Out of scope

- MPIPS API, private-network transport, real NPZ-to-DICOM conversion,
  real Grabber NPZ/gain schema support, conversion retry worker, AI, doctor
  routing, result publication, FHIR resources, MinIO, or production storage.
- Any synthetic bridge, synthetic DICOM association, fixture, default, or
  fallback outside `local` and `testing`.
- Real member/clinical data, B2B import, server database changes, deployment,
  release, credential delivery, physical-device rehearsal, or external system
  mutation.
- Member or Doctor DICOM viewers/downloads, raw-NPZ download, OCR, image
  editing, annotations, measurements, contrast/brightness/window-level
  controls, JavaScript framework migration, CDN scripts, or unrelated refactor.

### Preserved behavior

- Existing login, active-site selection, attendance, consent, ticket, print,
  LCD privacy, basic examination, questionnaire, queue claim/call, and
  X-ray-readiness safeguards remain fail closed.
- Image Gateway is the exclusive durable owner of accepted NPZ, gain, and
  DICOM objects. Operator stores only authorised references and status.
- Private objects stay encrypted, opaque, non-cacheable, non-public, and out
  of LCD, audit metadata, browser logs, and error messages.
- The later MPIPS adapter replaces the local/testing synthetic association;
  this task introduces no conversion algorithm or MPIPS compatibility claim.

## Dependencies and assumptions

### Dependencies

- Accepted Operator roster/paper-evidence implementation at
  `95e9de6ce9c16aa331b478adc34bd8e4b1b86d0e`.
- Existing local/testing synthetic clinic seeder and `PrivateObjectStore`.
- A repository-owned synthetic radiograph NPZ, matching gain NPZ, and valid
  synthetic DICOM Part-10 fixture with no person, patient, or clinical data.
- The local Chromium runtime and Node/npm registry access necessary to install
  the explicitly allowed pinned Cornerstone dependencies.

### Approved assumptions

- The synthetic NPZ/gain fixture pair is a local/testing identity fixture, not
  a claim of Grabber schema support or image validation.
- The synthetic bridge supplies a prebuilt fixture after capture acceptance;
  it does not derive DICOM bytes from NPZ inputs and does not emulate MPIPS.
- `local` and `testing` are the only environments where the bridge may run.

### Remaining approval requirements

- A later approved MPIPS contract, representative real Grabber NPZ/gain
  schema, and separate integration task are required before any server or
  production-like conversion path is enabled.
- Deployment, server database import, real data, real clinical images,
  credentials, storage configuration, and release remain separately approved.

## Required capabilities

- Repository read and write.
- Local PHP/Laravel, database migration, Node/npm, Vite, and Chromium execution.
- Network access only for the explicitly approved npm package installation.

## Execution constraints

- Test-driven implementation: add a focused failing test before each behavior
  change, then make the smallest compatible change pass it.
- Pin only the four approved Cornerstone-related packages in `package.json`
  and lockfile; do not add a framework, CDN, browser extension, or another
  viewer/storage package.
- Enforce authorisation from trusted current session, active site, assigned
  shift, admission, and immutable capture-set/study references; never trust
  browser-supplied site, booking, object key, study, or environment identity.
- The synthetic bridge must have an explicit environment guard at the shared
  Gateway boundary, not only a hidden UI condition.
- A failed validation, duplicate conflict, database transaction, storage
  write, audit append, outbox write, grant, or viewer/download authorisation
  must leave no partial durable record/object/link and must not advance the
  X-ray stage.
- Viewer bytes and downloaded bytes must come from the same authorised
  private-object reference; no second DICOM copy or public route is allowed.
- Use server-provided DICOM VOI metadata. Browser interaction is limited to
  zoom and pan; assert that prohibited tools and controls are absent.

## Acceptance criteria

- [ ] A synthetic dummy Operator can claim/call the existing X-ray admission,
  select the repository-owned NPZ-plus-gain fixture pair, receive a browser
  navigation warning while it is unsubmitted, and submit one complete set.
- [ ] Only the exact authorised local/testing fixture pair within configured
  limits is accepted; invalid/missing/renamed/altered/pair-mismatched,
  cross-site/shift/case, duplicate-conflict, and replay cases fail closed with
  no accepted study, stage advance, partial database row, or orphaned object.
- [ ] A successful submission produces one immutable, encrypted,
  checksum-verified Gateway capture set and advances the existing X-ray stage
  exactly once; the Operator owns no durable raw NPZ/gain object.
- [ ] The local/testing synthetic bridge associates one known synthetic DICOM
  fixture only after durable acceptance and is impossible to invoke outside
  `local` or `testing`; it does not call MPIPS or convert NPZ bytes.
- [ ] An assigned current-shift active-site Operator can open the study in a
  vertical Cornerstone DICOM view with automatic VOI and zoom/pan only; all
  manual image-editing functions and controls are unavailable.
- [ ] The same authorised Operator can explicitly trigger a normal browser
  `.dcm` attachment download; cross-site/shift/case, unauthenticated, and
  raw-NPZ requests fail closed.
- [ ] The existing synthetic local clinic walkthrough now covers login through
  DICOM viewer/download without real data, MPIPS, external storage, or
  deployment; focused PHP, JavaScript/build, and Chromium evidence passes.

## Verification requirements

### Required checks

- Run focused Image Gateway security/acceptance, Operator X-ray, capture,
  study-access/download, and private-object cleanup tests.
- Run the local synthetic seeder and the full synthetic clinic journey in a
  fresh disposable database.
- Run the focused Chromium journey through the actual Cornerstone viewport and
  authorised raw-DICOM download; verify no prohibited tools/control appear.
- Run the production-environment guard test proving the synthetic bridge is
  unavailable outside local/testing.
- Run `npm run build`, `git diff --check`, and the relevant migration suite.

### Required evidence

The Executor must report the exact implementation revision or working-tree
state; pinned package versions and lockfile change; every command actually
run; synthetic-only fixtures/data used; focused test/browser/build results;
the observed viewer/download boundaries; known gaps; and confirmation that no
MPIPS, real NPZ schema, real clinical data, server, deployment, or external
storage action occurred.

## Stop conditions

- Stop if real Grabber schema support, NPZ parsing/conversion, a real DICOM
  generator, MPIPS transport, MinIO, or a server environment is required to
  satisfy any acceptance criterion.
- Stop if a valid synthetic DICOM Part-10 fixture cannot be rendered by the
  approved packages without adding unapproved dependencies or a CDN.
- Stop if the existing private-object grant cannot bind viewer and download
  access to actor, site, study, purpose, and expiry without a new approved
  security model.
- Stop if the task requires an altered Member identity/booking model, a second
  queue, raw-NPZ access, a public object URL, or any expanded imaging workflow.

## Side-effect authorization

### Explicitly authorized side effects

- Repository changes, migrations, synthetic fixtures, and local/testing data
  required by this task.
- One npm installation that pins only `@cornerstonejs/core`,
  `@cornerstonejs/dicom-image-loader`, `@cornerstonejs/tools`, and the direct
  DICOM parser dependency in the existing lockfile.
- Local build, test, browser, and disposable synthetic database operations.

Not authorised: Git commit, push, pull request, deployment, release, server
database mutation, real data, real clinical image, MPIPS call, external object
storage mutation, or any dependency beyond those named above.

## Expected terminal outcome

`IMPLEMENTATION AND VERIFICATION RESULT REQUIRED`
