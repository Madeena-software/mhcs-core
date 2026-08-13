---
title: Operator Capture Display Reference
document_id: MHCS-TASK-OPERATOR-CAPTURE-DISPLAY-REFERENCE-001
version: 1.0
status: draft
language: en-US
last_updated: 2026-08-13
scope:
  - Operator NPZ capture-page display identifier
  - existing paper-ticket reference reuse
authority_note: Draft task. It becomes executable only after this exact content is committed unchanged, published as validated, and its immutable SHA is supplied to the Executor.
---

# Executable Task

## Task identity

**Task title:** `Operator Capture Display Reference`

**Task path:** `.agents/tasks/operator-capture-display-reference.md`

**Task contract state:** `Draft`

**Delivery objective / Work Package / MVP:** `Local MVP Operator capture usability`

**Owner / designated planning authority:** `Faliq Adlan, CTO`

## Delivery context

The NPZ capture page currently renders the internal X-ray-admission UUID in its
human-facing summary. The same authorised admission already has a paper-ticket
number, which is the established human operational reference. Show that existing
reference instead; keep the UUID solely in the existing protected route, form
action, authorisation, audit, and idempotency paths.

## Baseline and task revision

**Implementation baseline:** `4cbde95affe73139006f43f4f68d863c7dd03ace`

**Task revision:** `resolved when published`

## Objective

Make the Operator's NPZ capture page identify the active radiography session by
its existing short paper-ticket number, never by its internal admission UUID.

## Authoritative inputs

### Governing authority

- CTO local-testing feedback recorded in `docs/mvp/evidence/mvp-local-deployment-readiness.md` §New user-led feedback for Planner/Reviewer: the capture-page opaque UUID must not be a user-facing reference.
- `.agents/context/modules/operator/project.md` §Attendance and identity verification and §Queue rules: one site-and-shift paper-ticket number is the human operational reference.
- `.agents/context/modules/image-gateway/project.md` §Access and distribution: capture access remains bound to the authenticated Operator, active site, current shift, and claimed admission.
- `docs/mvp/decision-log.md` MVP-DEC-037 and `.agents/context/ui-language.md`: Indonesian JSON-backed UI copy.

### Requirement traceability

- `OPR-046`, `OPR-060`, `OPR-118` → bounded Operator capture workflow and protected internal identities.
- `UIL-001`, MVP-DEC-037 → Indonesian browser UI with no raw internal identifier presented as product copy.

## Scope

### In scope

- Extend the existing Image Gateway capture-form projection only as necessary to return the already-stored paper `ticket_number` for the authorised admission.
- Replace the visible `Radiography admission <UUID>` capture-page summary with the existing paper-ticket reference and existing translated label. Do not print the admission UUID elsewhere on that page.
- Keep the existing UUID in its current route, form action, status endpoint, hidden `submission_id`, server validation, authorisation, audit, queue, and idempotency contracts. The `submission_id` remains hidden and UUID-shaped because it is a non-display idempotency key.
- Add focused coverage proving the authorised page displays the ticket number, omits the admission UUID from visible page content, and preserves the existing protected UUID route/form contract.

### Out of scope

- A new ticket-reference column, migration, random-reference generator, ticket-number allocation change, paper-print redesign, route slug, URL rewrite, authorisation change, or data backfill.
- NPZ upload, capture metadata, MPIPS, queue, storage, DICOM viewer, DICOM download, worker, dependency, or deployment changes.

### Preserved behavior

- Paper ticket numbers remain unique per site and shift and unchanged through the queue lifecycle.
- The existing Operator-only active-site/current-shift/claim checks remain the only access control for the capture form.
- All new or changed browser-visible MHCS copy uses `__()` and `lang/id.json`; reuse an existing translation key where it is semantically exact.

## Dependencies and assumptions

### Dependencies

- The existing `operator_paper_tickets.ticket_number` join used by `ImageGatewayCaptureService::admission()`.

### Approved assumptions

- The paper ticket number is already the short human operational reference, so a second display-reference persistence mechanism is unnecessary.

### Remaining approval requirements

- None beyond this task's existing authority. Commit, deployment, production mutation, and external calls remain unauthorised.

## Required capabilities

- Repository read/write, Laravel/PHP tests, build, formatter, and diff checks.

## Execution constraints

- Reuse `ImageGatewayCaptureService::admission()` and the current capture form; do not create a parallel identifier service or query.
- Do not disclose a live ticket, Member identity, admission UUID, private object, NPZ, DICOM, secret, or external response in tests, documentation, or evidence.
- Do not add a package or modify the schema.

## Acceptance criteria

- [ ] An authorised Operator sees the existing short paper-ticket number on the NPZ capture page; the internal admission UUID is not rendered as the page's visible session reference.
- [ ] The existing UUID route, protected form action/status URL, hidden UUID submission idempotency key, active-site/current-shift/claim checks, and capture behavior remain unchanged.
- [ ] Focused coverage proves the presentation change and preserved protected contract; Indonesian localization, full PHP suite, build, formatter, and diff check pass.

## Verification requirements

### Required checks

```bash
TARGET="." vendor/bin/phpunit tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php
TARGET="." vendor/bin/phpunit tests/Feature/Localization/MvpApplicationIndonesianUiLocalizationTest.php
TARGET="." vendor/bin/phpunit
TARGET="." npm run build
TARGET="." vendor/bin/pint --test
TARGET="." git diff --check
```

### Required evidence

Report the implementation revision, changed files, command results, a sanitized
description of the capture-page result, and any gap. Do not report identifiers,
credentials, binary files, private-object data, or external calls.

## Stop conditions

- Stop if the paper-ticket number cannot be obtained through the current authorised admission query without a schema or identity-policy change.
- Stop if the change would expose a new identifier to a broader audience, change the protected UUID route contract, or require an external call.

## Side-effect authorization

### Explicitly authorised side effects

- Modify only the existing capture-form projection/view and focused related tests/localization if required; run local checks.

Not authorised: Git commit, deployment, production/external mutation, migration,
dependency installation, private-object inspection, NPZ/DICOM access, or unrelated
changes.

## Expected terminal outcome

`REVIEW REQUIRED` — return one reviewable implementation revision with truthful
local verification evidence. The Reviewer decides acceptance.
