---
title: Operator Clinic Roster and Required Paper Evidence
document_id: MHCS-TASK-OPERATOR-CLINIC-ROSTER-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-11
scope:
  - active-shift Operator roster
  - required paper consent and questionnaire evidence
  - Operator workstation presentation
authority_note: This task is executable only at its immutable publication revision.
---

# Executable Task

## Task identity

**Task title:**
`Operator Clinic Roster and Required Paper Evidence`

**Task path:**
`.agents/tasks/mvp-operator-clinic-roster-paper-evidence.md`

**Task contract state:**
`Validated/Published`

**Delivery objective / Work Package / MVP:**
`12 August MVP delivery target / Operator priority / clinic-day readiness`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

An authorised Operator needs one clear clinic-day workstation: the active
site's selected-shift roster, the existing private queue actions, required
paper evidence, and an uncomplicated path from arrival to X-ray readiness.
The clinic-day roster contains 37 booked members, but no code may assume that
count. The supplied Claude design is visual reference only; it is not a source
of application behavior or a frontend implementation dependency.

## Baseline and task revision

**Implementation baseline:**
`c398d5b72e552f266754965bfa7bf796cd635b3e`

**Task revision:**
`Resolved from the immutable publication commit`

## Objective

**Objective:**
Give an assigned Operator a site- and shift-scoped visual workstation that
lists every eligible clinic-day member, uses the existing staged queue, requires
private signed-consent and questionnaire photographs at their respective gates,
and reaches X-ray readiness without invoking Image Gateway or MPIPS.

## Authoritative inputs

### Governing authority

- `docs/mvp/decision-log.md` — MVP-DEC-021, MVP-DEC-022, MVP-DEC-023, MVP-DEC-028, MVP-DEC-030, MVP-DEC-032, and MVP-DEC-033.
- `.agents/context/modules/operator/project.md` — attendance, queue, paper evidence, and read-only image-access rules.
- `.agents/context/modules/member/project.md` — Member-owned consent and private-object boundary.
- `docs/operator/reference/claude-design/` — visual reference only.

### Requirement traceability

- `OPR-015..OPR-020` → attendance, verified consent, required private scan, ticket issuance, and printing.
- `OPR-021..OPR-030` → basic examination, staged queue, and X-ray readiness.
- `OPR-090` → required paper-consent private scan recording.

## Scope

### In scope

- Render every eligible booking for the current Operator's authorised active site and selected assigned shift; the roster must not impose a 37-member limit or expose members from another site or shift.
- Present the existing front-desk, waiting queue, basic-examination, and X-ray-readiness actions with the hierarchy, queue emphasis, and dark visual language demonstrated by the supplied Claude reference.
- Make the existing paper informed-consent image required before the Member check-in transition, ticket issuance, and print action.
- Preserve the existing required questionnaire image before basic-examination completion and make that requirement obvious in the workstation.
- Reuse the existing Laravel controllers, Blade views, Member contracts, encrypted private-object store, authorisation, idempotency, audit, and queue services.
- Add focused tests for roster scope, required consent upload, required questionnaire upload, and the visible Operator journey.

### Out of scope

- Image Gateway intake, NPZ/gain upload, MinIO, MPIPS, DICOM conversion, DICOM viewer/download endpoint, AI, doctor workflows, and result publication.
- Any real member roster, credential, clinical image, printer, TV, external storage, server, deployment, or database mutation.
- React, a JavaScript framework, copying the reference source, a second queue, a second upload store, OCR, or AI extraction.

### Preserved behavior

- Attendance, site/shift authorisation, identity verification, tickets, print privacy, staged FIFO claims, LCD privacy, and questionnaire encrypted storage remain fail-closed.
- Consent and questionnaire images remain private, purpose-bound, encrypted, non-public, and unavailable to the LCD.
- The user-visible flow still ends at X-ray readiness.

## Dependencies and assumptions

### Dependencies

- Accepted Operator workstation baseline at `c398d5b72e552f266754965bfa7bf796cd635b3e`.
- Existing synthetic clinic seeder and Browser Chromium runtime.

### Approved assumptions

- The 37 members are all eligible bookings for the selected clinic-day shift; the roster is not a global member directory.
- The supplied design files remain visual reference only.

### Remaining approval requirements

- Deployment, server database import, credential delivery, real data, device rehearsal, and Image Gateway work require separate approval.

## Required capabilities

- Repository read and write.
- Local PHP/Laravel/PHPUnit/Pest/Browser execution with synthetic data.

## Execution constraints

- Use server-derived active-site and selected-shift records; never trust an arbitrary site, shift, booking, or member identifier from the browser.
- A missing or invalid consent image must fail before the existing ticket transaction creates any Member check-in, ticket, queue admission, audit, or outbox record.
- Preserve the current valid upload bounds and private-object handling; do not make the paper images downloadable or cacheable.
- Do not install dependencies or add a frontend stack.

## Acceptance criteria

- [ ] An assigned Operator sees all and only the selected active site's eligible shift roster; 37 eligible bookings are all visible without a special-case limit.
- [ ] The roster makes each member's existing operational state and next safe action understandable without exposing it to the public LCD.
- [ ] A missing consent image prevents consent confirmation, check-in, ticket issue, and printing, with no partial record or private-object orphan.
- [ ] A valid consent image remains privately stored and the existing check-in/ticket/print path still succeeds exactly once.
- [ ] A missing questionnaire image prevents basic-examination completion; a valid private image permits the existing transition to X-ray readiness.
- [ ] The Operator journey visually follows the supplied reference's front-desk and queue hierarchy while using only existing Laravel/Blade mechanisms.
- [ ] Existing site, shift, claim, queue, print, LCD, authentication, and private-object safeguards remain covered by focused tests.

## Verification requirements

### Required checks

- Run focused Operator consent, ticket, roster/worklist, basic-examination, X-ray, LCD, and portal tests.
- Run the Chromium Operator workstation journey using the existing local browser runtime.
- Run `git diff --check`.

### Required evidence

The Executor must report the implementation revision or exact working-tree
state, commands actually run, synthetic-only results, tests changed, browser
observations, known gaps, and confirmation that no real roster, credentials,
images, Gateway, MPIPS, or deployment action was used.

## Stop conditions

- Stop if the roster requires a global member directory, unauthorised site/shift access, or a new identity/queue/persistence model.
- Stop if making consent upload required would break the existing Member-owned atomic check-in boundary or cannot remove partial uploads on failure.
- Stop if the visual implementation requires copying the reference source or adding a frontend dependency.
- Stop if the task expands into Image Gateway, DICOM, MPIPS, deployment, server import, or real data.

## Side-effect authorization

### Explicitly authorized side effects

- Repository changes and synthetic local test data necessary for this task.

Not authorised: Git commit, push, pull request, deployment, release, server
database import, real credentials, real clinical data, dependency installation,
or external-system mutation.

## Expected terminal outcome

`IMPLEMENTATION AND VERIFICATION RESULT REQUIRED`
