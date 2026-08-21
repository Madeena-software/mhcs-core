---
title: MHCS Public LCD Responsive Display
document_id: MHCS-TASK-PUBLIC-LCD-RESPONSIVE-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-21
scope:
  - Public LCD responsive presentation
  - MVP-04 Operator public queue display
authority_note: This task authorizes only the bounded presentation change described below. Queue semantics, public privacy, and production state remain unchanged.
---

# Executable Task

**Task title:** Make the public MHCS clinic queue LCD responsive and readable

**Task path:** `.agents/tasks/mvp-public-lcd-responsive-display.md`

**Task contract state:** `Validated/Published`

**Delivery objective / Work Package / MVP:** MVP-04 / WP-12 — Public LCD presentation

**Owner / designated planning authority:** Planner, Operator Core delivery

## Delivery context

The public LCD can split a ticket such as `T-002` at the hyphen and does not
reliably use common TV, monitor, ultrawide, or portrait viewports. Repair the
presentation while preserving the existing login-free, site-scoped public
queue contract and Bahasa Indonesia copy.

## Baseline and task revision

**Implementation baseline:** `3f60a4cc0f30ed13c184694564e41c487d4cdb8b`

**Task revision:** this exact task content at its publication commit; the
immutable governing SHA is supplied in the Executor handoff.

## Objective

**Objective:** Make the public MHCS clinic queue LCD reliably fill and adapt to
common monitor and TV viewports while preserving existing queue behavior,
privacy, Bahasa Indonesia copy, simultaneous station calls, and ticket
readability.

## Authoritative inputs

### Governing authority

- `.agents/context/modules/operator/project.md` — Operator specification,
  public LCD, site/shift, destination, recent-call, stale-state, and privacy
  boundaries.
- `docs/mvp/decision-log.md` — MVP-DEC-023 paired browser public LCD policy;
  MVP-DEC-031 sequential delivery on `main`; MVP-DEC-037 Bahasa Indonesia
  browser UI and translation policy.
- `docs/mvp/beta-scope.md`, `docs/mvp/roadmap.md`,
  `docs/mvp/beta-gap-register.md`, and `docs/mvp/work-package-status.md` —
  current MVP-04/WP-12 scope and open public-display gap.
- `.agents/context/ui-language.md` — Bahasa Indonesia and public-visible
  terminology requirements.

### Requirement traceability

- Public LCD specification and MVP-DEC-023 → login-free, read-only,
  site-scoped, number-only display with exactly two public destinations and
  five recent calls.
- MVP-DEC-037 and UI language policy → preserve Indonesian browser copy and
  approved destination labels `PEMERIKSAAN DASAR` and `SESI FOTO RADIOGRAFI`.
- MVP-DEC-031 → implementation remains on `main`; no parallel delivery branch.

## Scope

### In scope

- Adjust the public LCD presentation, primarily
  `resources/views/lcd/queue.blade.php`, so the layout fluidly uses the
  available viewport rather than remaining capped at 1680px.
- Make ticket numbers, including `T-002`, stay on one line and fit within
  their cards without overflow or wrapping at a hyphen.
- Make header, clock, current calls, recent calls, destinations, and empty or
  stale states fit without collision, clipping, or horizontal scrolling.
- Support the representative viewport matrix below with a readable stacked
  narrow/portrait fallback.
- Add or update only focused presentation/browser evidence needed to verify the
  contract, expected in `tests/Browser/Mvp04PublicQueueResponsiveTest.php`,
  plus focused assertions in
  `tests/Feature/Operator/Mvp04pPublicQueueDisplayTest.php` when needed.

### Out of scope

- Controller, route, database, queue algorithm, ticket allocation, or polling
  changes unless the Executor reaches the stop condition below.
- Deployment, production mutation or queries, real patient/member data, real
  queue manipulation, authentication changes, Printer Station changes, or
  Operator redesign outside the LCD.
- Member, Doctor, Image Gateway, audio calling, public waiting list, WebSocket,
  frontend framework, new dependency, or unrelated translation redesign.

### Preserved behavior

- The page remains login-free and the endpoint remains site-scoped.
- Public calls expose only ticket number and destination; no Member identity,
  NIK, MRN, booking ID, assessment, clinical image, result, or waiting
  position.
- Public destinations remain exactly `PEMERIKSAAN DASAR` and
  `SESI FOTO RADIOGRAFI`.
- Current calls, ordering, polling behavior, disconnected/stale indicator,
  and five most recent call-history records remain unchanged unless existing
  implementation evidence proves a bounded presentation-only adjustment is
  necessary.
- Recent Calls are not deduplicated by ticket number; repeated tickets may be
  legitimate calls at different stages.
- Existing visual identity and approved Indonesian copy remain intact.

## Dependencies and assumptions

### Dependencies

- Existing Laravel Blade view and public queue feature tests.
- Existing Browser test runtime and `setViewportSize(width, height)` support.
- Synthetic queue fixtures only; no network dependency installation.

### Approved assumptions

- The current queue endpoint is the presentation's source of truth; the task
  does not create a second read model or alter queue data.
- CSS layout primitives and container-aware sizing are preferred over
  JavaScript viewport calculations.

### Remaining approval requirements

- Release, deployment, production access, and any controller/database change
  remain outside this task and require return to planning or the applicable
  approval gate.

## Required capabilities

- Repository read/write and local test execution.
- Existing browser runtime.
- Codebase Memory MCP for implementation impact discovery when needed.

## Execution constraints

- Use the smallest coherent presentation-only change and existing repository
  patterns; do not add a frontend framework, dependency, migration, or new
  service.
- Prefer CSS Grid/Flexbox, `clamp()`, intrinsic sizing, and container-aware
  constraints over JavaScript viewport calculations.
- Do not hard-code CSS only for the listed resolutions; behavior must be fluid
  across the supported range.
- Use synthetic data and preserve public privacy at every rendered state.

## Acceptance criteria

- [ ] At minimum, a rendered `T-002` remains on one line and never overflows
      its call card.
- [ ] Destination labels remain readable and do not collide with ticket
      numbers.
- [ ] The page uses the available viewport rather than an unnecessary 1680px
      cap; header, clock, current calls, and recent calls remain within the
      viewport.
- [ ] Landscape viewports have no horizontal scrolling, accidental overlap,
      or clipping at: `1280x720`, `1366x768`, `1536x960`, `1920x1080`,
      `2560x1080`, `2560x1440`, and `3840x2160`.
- [ ] Large displays scale content appropriately; short 720p displays reduce
      content instead of producing oversized cards; ultrawide displays remain
      balanced rather than excessively stretching one section.
- [ ] `1080x1920` degrades to a readable stacked layout without clipping.
- [ ] Current Calls render correctly for zero calls, one call, and simultaneous
      Basic Examination and Radiography calls.
- [ ] Recent Calls render correctly for zero through five records, including
      legitimate repeated ticket numbers.
- [ ] Rendered verification covers: no current/no recent calls; one Basic
      Examination call; one Radiography call; simultaneous Basic Examination
      and Radiography calls; five recent calls; and a longer ticket identifier
      supported by the current ticket-number contract.
- [ ] The disconnected/stale indicator remains visible when refresh fails.
- [ ] Existing login-free site scoping, public field/privacy boundary,
      destination labels, ordering, polling, and five-record limit remain
      covered by focused feature/regression tests.
- [ ] Existing visual identity and Bahasa Indonesia copy are preserved.

## Verification requirements

### Required checks

- [ ] Run the focused Public LCD feature test
      `tests/Feature/Operator/Mvp04pPublicQueueDisplayTest.php`.
- [ ] Add and run the focused Browser responsive test
      `tests/Browser/Mvp04PublicQueueResponsiveTest.php` using synthetic queue
      data, the existing browser runtime, and `setViewportSize(width, height)`.
- [ ] Reproduce the observed `T-002` case at or near `1536x960` and verify
      rendered geometry: no document overflow; single-line ticket; ticket and
      destination bounds inside the card; non-colliding header/clock; current
      and recent panels inside the viewport; and the expected layout mode.
- [ ] Capture fresh rendered/DOM geometry evidence for every viewport class:
      no horizontal overflow; ticket fit; panel bounds; no header/clock
      collision; visible current/recent panels; and appropriate stacked mode.
- [ ] Run existing relevant Operator queue-display regressions and report the
      exact commands and observed results.
- [ ] Run `git diff --check`.

If the local browser runtime is unavailable, report the verification gap; do
not download or install a new dependency.

### Required evidence

The Executor MUST report the exact implementation revision or working-tree
state, commands and observed results, changed files, viewport evidence,
verification gaps, and any deviation or blocker. Local evidence MUST NOT be
reported as CI or production evidence.

## Stop conditions

Stop and return to planning if responsiveness requires a controller, route,
database, queue-state, authentication, privacy, dependency, or architecture
change; if the implementation baseline is no longer applicable; if any
acceptance criterion cannot be met within presentation scope; or if a new
authority, security, privacy, or production approval is required.

The Executor MUST NOT change queue semantics, deduplicate recent calls, add
patient data, or silently reinterpret this task.

## Side-effect authorization

This task authorizes only application/test presentation changes and local
verification. It does not authorize commit, push, pull request, deployment,
production access or mutation, dependency installation, secret access, or
unrelated repository changes.

### Explicitly authorized side effects

- None beyond the bounded local file changes and verification above.

## Expected terminal outcome

**Review Required** when the presentation changes and truthful browser/feature
evidence are available for review. Use **Planning Required** when a stop
condition is reached. The Executor does not self-declare acceptance or release.
