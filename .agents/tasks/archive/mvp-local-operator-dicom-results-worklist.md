---
title: Local Multi-Operator DICOM Results Worklist
document_id: MHCS-TASK-LOCAL-OPERATOR-DICOM-WORKLIST-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-12
scope:
  - local/testing synthetic DICOM results worklist
  - active-site/current-shift multi-Operator study access
  - normal authenticated DICOM download
authority_note: This task becomes executable only when its exact content is committed and published as validated.
---

# Executable Task

## Task identity

**Task title:**
`Local Multi-Operator DICOM Results Worklist`

**Task path:**
`.agents/tasks/mvp-local-operator-dicom-results-worklist.md`

**Task contract state:**
`Validated/Published when this exact content is committed`

**Delivery objective / Work Package / MVP:**
`12 August MVP delivery target / local Operator DICOM-result rehearsal / WP-14 and WP-23`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The accepted local synthetic capture flow lets only the submitting Operator open
its one generated study. MVP-DEC-036 instead authorises every Operator whose
active site and current shift authorise the examination to view and normally
download each returned DICOM. Before MPIPS integration, make that policy
locally demonstrable with the existing synthetic study fixture and a discoverable
Operator results worklist.

## Baseline and task revision

**Implementation baseline:**
`4a20fa0b24ba27084975ebaece76561c80f4e06d`

**Task revision:**
`Resolve from the commit that publishes this exact task content before execution.`

## Objective

**Objective:**
Enable two authorised current-shift Operators at the same active site to find,
open, and normally download the same accepted local synthetic DICOM study from
an Operator results worklist, while preserving all existing privacy and
synthetic-only boundaries.

## Authoritative inputs

### Governing authority

- `docs/mvp/decision-log.md` — MVP-DEC-024, MVP-DEC-034, MVP-DEC-035, and MVP-DEC-036.
- `.agents/context/modules/operator/project.md` — Operator read-only image access.
- `.agents/context/modules/image-gateway/project.md` — per-capture Operator availability and private distribution.
- `docs/implementation/mhcs-core-requirements-matrix.md` — IMG-020, IMG-057, IMG-060, and OPR-057..OPR-060.
- Accepted local synthetic Image Gateway/DICOM rehearsal at `b4fc1add261e1a0a675b16c904737db1a85e22f7`.

### Requirement traceability

- `MVP-DEC-036` and `IMG-060` → each accepted returned study is available to any authorised active-site/current-shift Operator without waiting for sibling captures.
- `IMG-020` → Member and Doctor availability remains complete-set-gated.
- `OPR-057..OPR-060` → read-only vertical viewer and normal `.dcm` attachment; raw NPZ remains unavailable.
- `IMG-057` → this task introduces no MPIPS transport or conversion behavior.

## Scope

### In scope

- Add an authenticated Operator DICOM-results worklist that lists accepted
  local synthetic studies visible to the current active site and current-shift
  assignment, with a safe empty state and a link to the existing study page.
- Use the existing `operator_shift_assignments` plus
  `operator_eligible_shifts` association for the study's member schedule as
  the repository's current-shift authorisation rule. Remove only the
  submitting-Operator restriction from study/viewer/download authorisation.
- Preserve active-site scoping and ensure a same-site, eligible second
  Operator can view and download the study without claiming, submitting, or
  altering the capture.
- Extend local/testing synthetic seeding or its existing reusable fixture only
  as needed to demonstrate two active Operators for one eligible shift.
- Add the smallest dashboard or existing Operator-navigation entry that makes
  the results worklist discoverable.
- Extend the local walkthrough with the second Operator's worklist/viewer/
  attachment-download check.
- Add focused PHP and Chromium tests covering the second Operator's worklist,
  viewer, and attachment download, plus unauthenticated, cross-site, revoked,
  and raw-NPZ denial paths.

### Out of scope

- MPIPS API calls, callbacks, polling, job workers, retries, real NPZ/gain
  parsing, real DICOM generation, or real per-capture result arrival.
- Member or Doctor partial-result visibility, AI/doctor routing, completed-set
  publication changes, FHIR, external object storage, production storage,
  deployment, real data, or server database import.
- Image editing, annotations, measurements, manual VOI controls, public URLs,
  raw-NPZ access, new frontend frameworks, or dependency changes.

### Preserved behavior

- Synthetic capture and study association remain local/testing-only and retain
  their shared Gateway environment guard.
- Image Gateway remains the sole owner of encrypted NPZ, gain, and DICOM
  objects; the Operator receives only authorised references and bytes.
- A study/viewer/download still requires authentication, active-site selection,
  and an active eligible assignment for that exact member schedule. A different
  site, revoked/ineligible assignment, unknown study, and raw-NPZ request fail
  closed.
- Member and Doctor access stays unchanged and no new queue or result state is
  introduced.

## Dependencies and assumptions

### Dependencies

- Current accepted baseline at `b4fc1add261e1a0a675b16c904737db1a85e22f7` and
  authority update at `4a20fa0b24ba27084975ebaece76561c80f4e06d`.
- Existing local/testing synthetic fixture pair, DICOM fixture, encrypted
  `PrivateObjectStore`, Operator authorisation, and Cornerstone viewer.

### Approved assumptions

- An `active` Operator shift assignment joined to an `eligible` assignment for
  the study's member schedule is the existing implementation meaning of
  current-shift authorisation for this local rehearsal.
- The existing one-study synthetic bridge is sufficient to demonstrate
  multi-Operator discovery; this task does not claim a real MPIPS result list.

### Remaining approval requirements

- The exact MPIPS asynchronous transport contract, representative real inputs,
  and a separate integration task remain required before any real conversion
  path or incremental result arrival is enabled.
- Deployment, server data, real images, and credential handoff remain outside
  this task.

## Required capabilities

- Repository read and write.
- Local PHP/Laravel, disposable database, Node/Vite, and Chromium execution.

## Execution constraints

- Add a focused failing test before each behaviour change; prefer the existing
  `SyntheticCaptureGatewayService`, Operator authorisation, private-object,
  route, Blade, fixture, and browser-test patterns over new abstractions.
- Do not add a migration, storage system, queue, event, dependency, or browser
  framework unless an observed implementation constraint makes it unavoidable;
  stop for planning if it does.
- Never expose an object key, raw NPZ, patient data, or public/signed URL in
  the worklist, browser state, logs, or error response.
- Viewer and downloaded bytes must remain the same authorised private DICOM
  object. Keep the viewer read-only with automatic VOI and zoom/pan only.

## Acceptance criteria

- [ ] A local/testing synthetic DICOM-results worklist is discoverable from
  the Operator workstation and lists only studies authorised for the active
  site and current-shift assignment, with no study-data leakage in its empty
  or denial states.
- [ ] A second active eligible Operator at the same site can discover a study
  submitted by another Operator, open the existing vertical read-only viewer,
  and trigger a normal authenticated `.dcm` attachment download.
- [ ] The submitting Operator retains the same viewer/download capability;
  capture ownership, queue state, and all existing synthetic upload safeguards
  remain unchanged.
- [ ] Unauthenticated, cross-site, revoked/ineligible-current-shift, unknown
  study, and raw-NPZ requests fail closed; Member and Doctor partial-result
  access is not added.
- [ ] The local synthetic walkthrough and focused PHP/Chromium evidence prove
  the two-Operator results discovery, viewer, and download journey without
  MPIPS, external systems, real data, or deployment.

## Verification requirements

### Required checks

- Run focused synthetic capture/study access tests including second-Operator
  allow and all required denial cases.
- Run the existing Operator X-ray claim/call regression tests and the local
  synthetic seeder test.
- Run a Chromium test that logs in as the second eligible Operator, reaches the
  worklist, opens the study, renders the Cornerstone viewport, and activates
  the attachment download.
- Run `npm run build`, relevant migration tests, and `git diff --check`.

### Required evidence

The Executor must report the exact implementation revision or working-tree
state, commands actually run, observed allow/deny results, synthetic data used,
changed tests, build result, known gaps, and confirmation that no MPIPS,
external system, real data, deployment, or server database action occurred.

## Stop conditions

- Stop if satisfying this task requires MPIPS transport, callback/polling,
  queues, real result-arrival simulation, a real DICOM generator, real input
  schema, or an external system.
- Stop if current-shift authorisation cannot be enforced by the existing
  active assignment plus eligible schedule relationship without a new approved
  policy decision.
- Stop if the policy requires Member/Doctor partial visibility, a public URL,
  raw-NPZ access, or an unapproved persistence/dependency change.

## Side-effect authorization

### Explicitly authorized side effects

- Repository code, view, route, local/testing synthetic fixture/seeder,
  walkthrough, and test changes required by this task.
- Local build, browser, and disposable-database operations.

Not authorised: Git commit, push, pull request, deployment, release, external
or server mutation, MPIPS call, real data, real images, or dependency change.

## Expected terminal outcome

`IMPLEMENTATION AND VERIFICATION RESULT REQUIRED`
