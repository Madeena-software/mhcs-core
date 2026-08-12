---
title: Application-Wide Indonesian UI Localization
document_id: MHCS-TASK-UI-ID-LOCALIZATION-001
version: 1.2
status: validated-published
language: en-US
last_updated: 2026-08-12
scope:
  - Bahasa Indonesia as the MHCS browser UI default
  - one JSON registry for MHCS-authored visible copy
  - Member, public LCD, and Operator presentation
authority_note: This task becomes executable only when its exact content is committed and published as validated.
---

# Executable Task

## Task identity

**Task title:**
`Application-Wide Indonesian UI Localization`

**Task path:**
`.agents/tasks/mvp-application-indonesian-ui-localization.md`

**Task contract state:**
`Validated/Published when this exact content is committed`

**Delivery objective / Work Package / MVP:**
`12 August MVP delivery target / application-wide Indonesian UI copy`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

MHCS must present one consistent Bahasa Indonesia experience to Members, the
public LCD, and Operators. Wording must be editable centrally when clinical or
operational terminology needs correction. The existing Laravel JSON translation
mechanism is sufficient; this task does not introduce language selection,
another frontend framework, or new clinical/queue behavior.

The owner reviewed commit `89a6649c9c130f8e9f1f846e79954ae3eb02b277` as the
starting implementation state. Its non-localization changes are deliberately
preserved but remain outside this task and will be included in the consolidated
review after localization completes.

## Baseline and task revision

**Implementation baseline:**
`89a6649c9c130f8e9f1f846e79954ae3eb02b277` — owner-reviewed starting state.
The formal accepted baseline remains
`87a6b2ac8649ecb1e692fdaf553d4212a3f00910` until the later consolidated review.

**Task revision:**
`Resolve from the commit that publishes this exact task content before execution.`

## Objective

**Objective:**
Make every MHCS-authored browser-visible Member, public LCD, and Operator UI
string render in Bahasa Indonesia from the single editable `lang/id.json`
registry, without changing the underlying workflow or authorization behavior.

## Authoritative inputs

### Governing authority

- `docs/mvp/decision-log.md` — MVP-DEC-037.
- `.agents/context/ui-language.md` — application-wide language, terminology,
  public-display, accessibility, and professional-term rules.
- `.agents/context/modules/operator/project.md` — Operator queue, public LCD,
  privacy, and active-site/current-shift boundaries.
- `.agents/context/project.md` — approved Laravel modular-application
  architecture.
- Owner direction recorded on 12 August 2026: all internal Operator screens
  are included; localization completes before returning to local rehearsal.

### Requirement traceability

- MVP-DEC-037 → Indonesian default locale and central JSON-managed browser copy.
- OPR-031..OPR-046 and OPR-057..OPR-060 → preserve the existing Operator,
  queue, consent, capture, DICOM-viewer, and download behavior while changing
  only its presentation language.
- MVP-DEC-023 → public LCD remains ticket-only and uses the approved Indonesian
  public destinations `PEMERIKSAAN DASAR` and `SESI FOTO RADIOGRAFI`.
- MVP-DEC-035 and MVP-DEC-036 → raw-DICOM access policy remains unchanged;
  only its visible labels are localized.

## Scope

### In scope

- Make Bahasa Indonesia both the default and fallback application locale in
  `config/app.php` and `.env.example`. Remove a route-specific locale override
  only when the application-wide configuration makes it redundant.
- Retain one complete JSON registry at `lang/id.json`. Add every
  MHCS-authored, browser-visible string used by the Member, Operator, public
  LCD, and welcome views, including titles, headings, navigation, buttons,
  labels, hints, empty states, flash/status copy, table headings, alt text,
  accessible names, and Blade-provided JavaScript copy.
- Replace those visible literals with Laravel `__()` lookups. Use source-string
  JSON keys and named placeholders for dynamic presentation text, so a wording
  correction changes `lang/id.json` rather than source templates or
  controllers.
- Localize browser-rendered controller success, validation, and error messages.
  Reuse Laravel validation and error presentation paths; translate messages at
  their UI boundary rather than changing domain exception categories,
  authorization rules, audit events, persistence values, or API contracts.
- Translate UI-rendered state labels and queue destinations without changing
  their stored enum/state values. Member/public surfaces must use the approved
  radiography terminology and must not display `X-ray`.
- Cover the existing Member, public LCD, Operator workflow, read-only DICOM
  viewer, and raw-DICOM download presentation. `DICOM`, `FHIR`, `NPZ`, formal
  consent names, identifiers, and other approved technical/legal terms may
  remain unchanged when translation would reduce precision.
- Add focused automated coverage proving the Indonesian default, JSON registry,
  and representative Member, LCD, and Operator/DICOM page copy. Update existing
  assertions only where their English copy expectation changes.

### Out of scope

- A locale selector, English UI, second translation registry, `lang/id/*.php`
  files, a custom translation layer, or a frontend/i18n dependency.
- Changes to queue ordering, ticket-number allocation, attendance time rules,
  NIK visibility, consent timing/upload limits, seed behavior, capture,
  DICOM access, authorization, database schema, routes, or controller/service
  business logic from `89a6649`.
- MPIPS integration, asynchronous conversion, AI, Doctor workflows, object
  storage behavior, server deployment, real data, or the deferred local
  rehearsal execution.
- Translating user-entered or trusted dynamic content such as names, NIK,
  medical-record numbers, document contents, DICOM metadata, route IDs,
  audit data, logs, and API-only payloads.

### Preserved behavior

- Member, Operator, public LCD, and DICOM authorization boundaries remain
  exactly as at the starting revision.
- The public LCD stays login-free, ticket-number-only, and free of personal or
  clinical information.
- The existing raw-DICOM browser attachment response remains a normal download;
  this task changes only its visible UI label.
- Existing form names, submitted values, database values, route names, test
  selectors, CSS/JavaScript behavior, and accessible structural semantics stay
  stable unless a presentation-only translation change requires an assertion
  update.
- `89a6649` is not accepted or modified by this task merely because it is the
  starting revision.

## Dependencies and assumptions

### Dependencies

- The exact starting revision `89a6649c9c130f8e9f1f846e79954ae3eb02b277`.
- Existing Laravel translation helpers and the existing `lang/id.json` file.
- Existing synthetic tests and fixtures; no real account, clinical object, or
  external integration is required.

### Approved assumptions

- Laravel JSON translation files and `__()` are the established framework
  mechanism for a large editable collection of presentation strings.
- “Every displayed UI string” means every MHCS-authored string rendered in a
  browser. Dynamic trusted data is not copied into translation data.
- `lang/id.json` remains the single source of editable Bahasa Indonesia copy.

### Remaining approval requirements

- Consolidated review of `89a6649` plus the localization implementation is
  required before a new accepted baseline or the deferred local-rehearsal task.
- Deployment, release, real-member import, MPIPS, and real clinical data remain
  separately approved work.

## Required capabilities

- Repository read and write.
- Local PHP/Laravel test execution using synthetic data.
- Local npm/Vite build execution.

## Execution constraints

### Constraints

- Keep `lang/id.json` valid JSON and the sole editable registry for
  MHCS-authored browser copy. Do not duplicate Indonesian application copy in
  Blade templates, controllers, or a second language-file format.
- Use Laravel's existing translation helper directly. Do not add middleware,
  a locale abstraction, a translation package, or a JavaScript i18n framework.
- Convert presentation text at its render boundary. Domain exception categories,
  persistence values, audit-event identifiers, and protected-data rules must
  not be renamed for localization.
- Use named replacements rather than string concatenation when a translated
  message contains dynamic display data.
- Keep professional UI terms accurate in Bahasa Indonesia. Preserve accepted
  technical/legal terms where appropriate and apply the public radiography
  terminology exactly.
- Do not use localization as a reason to refactor, fix, accept, or alter the
  non-localization behavior introduced in `89a6649`.
- Reuse existing Blade layouts, controllers, routes, and feature-test patterns.
  No new dependency, migration, schema change, queue, storage path, or external
  request is authorized.

## Acceptance criteria

- [ ] The normal application locale and fallback locale are Indonesian, and
  `lang/id.json` is valid JSON containing all MHCS-authored browser copy used
  by Member, public LCD, Operator, DICOM viewer, and DICOM-download surfaces.
- [ ] Every static and formatted MHCS-authored text rendered by those surfaces
  uses a Laravel translation lookup; a wording change is achievable by editing
  `lang/id.json` without editing presentation source.
- [ ] Member, public LCD, and all internal Operator pages render Bahasa
  Indonesia, including browser-visible validation, status, empty-state, and
  error copy. Dynamic trusted records are not added to `lang/id.json`.
- [ ] Public queue destinations are exactly `PEMERIKSAAN DASAR` and `SESI FOTO
  RADIOGRAFI`; Member/public content contains no MHCS-authored `X-ray` text.
- [ ] Operator DICOM viewer and normal raw-DICOM download remain reachable by
  the same authorized paths and use Indonesian presentation labels without
  changing DICOM bytes, HTTP attachment behavior, or access checks.
- [ ] No route, form field, stored value, queue/state transition, protected-data
  boundary, dependency, migration, or non-localization behavior changes.
- [ ] Focused regression and localization evidence passes using only synthetic
  data, while the later consolidated review remains explicitly pending.

## Verification requirements

### Required checks

- Add and run a focused localization feature test that verifies the Indonesian
  default/fallback configuration, parses `lang/id.json`, and renders
  representative Member, public LCD, Operator dashboard/worklist, DICOM-viewer,
  and DICOM-download UI copy using the existing synthetic setup.
- Run the affected existing suites for Member access, Operator portal, public
  LCD, basic-examination/X-ray readiness, synthetic capture, study access, and
  DICOM results worklist. Update only copy assertions that legitimately change.
- Perform a repository text audit over `resources/views/member`,
  `resources/views/operator`, `resources/views/lcd`, `resources/views/welcome.blade.php`,
  and user-facing controller response paths. Resolve every remaining
  MHCS-authored visible literal by a `lang/id.json` lookup or document it as an
  allowed dynamic/technical/legal exception in the Executor evidence.
- Run `npm run build` and `git diff --check`.

### Required evidence

The Executor must report the exact implementation revision or working-tree
state; every command actually run; test totals; rendered-route observations;
the JSON-audit result and each allowed exception; modified translation keys;
known gaps; and explicit confirmation that no non-localization behavior,
deployment, real data, MPIPS call, external request, dependency, or secret was
introduced.

## Stop conditions

- Stop if any browser-visible text cannot be centrally managed through
  `lang/id.json` and `__()` without a new translation mechanism or another
  language-file format.
- Stop if localizing a message would require changing domain state, route/form
  contracts, authorization, persistence, protected-data handling, or another
  non-localization behavior from `89a6649`.
- Stop if a public/member surface needs terminology not governed by
  `.agents/context/ui-language.md` or MVP-DEC-037.
- Stop if the starting revision changes, `89a6649` is not available, or a
  required regression demonstrates a behavior change outside this task.

## Side-effect authorization

### Explicitly authorized side effects

- Repository changes limited to translation configuration, `lang/id.json`,
  Member/public/Operator presentation and response-boundary localization,
  focused tests, and necessary copy-assertion updates.
- Local synthetic tests and Vite build artifacts produced by the normal test
  lifecycle.

Not authorized: Git commit, push, pull request, deployment, release, real
data, production configuration, dependency installation, external calls,
MPIPS, schema changes, or non-localization functional changes.

## Remediation

### First remediation closure

The first remediation governed by
`.agents/tasks/mvp-application-indonesian-ui-localization.md @ b07f08ab83847c203b6171bf65088992c88ec5a2`
was implemented at `63249efc91a2045067f36ecb31eee46709cde236`.

- Its eleven Image Gateway capture messages are registered in `lang/id.json`.
- The visible attendance `NIK:` label now uses a registered lookup.
- The rejected-fixture route test renders the Indonesian fixture-identity
  message and retains the no-side-effect assertions.
- The Reviewer observed the required focused suite pass: 145 tests and 1,574
  assertions. `npm run build` and `git diff --check` also passed, with the
  existing Cornerstone browser-externalization and large-chunk warnings only.

**R8 — Remediation closure:** not passed. The required controller-path audit
found a second, bounded gap in the same objective: 49 exact
`OperatorException` messages can still reach an Operator browser response via
`PortalController`'s `__($exception->getMessage())` paths but have no
`lang/id.json` entry. The first remediation's corrections remain closed.

### Original review basis

- Governing task revision:
  `.agents/tasks/mvp-application-indonesian-ui-localization.md @ e6e663a02bde563e13b706501b1f1becfd35d269`.
- Implementation baseline: `89a6649c9c130f8e9f1f846e79954ae3eb02b277`.
- Reviewed implementation revision:
  `8c3200d87287fe49b20b8235a192a9f93897af5e`.
- Observed evidence: the focused localization/Member/Operator/Image Gateway
  suite passed 145 tests and 1,548 assertions; `npm run build` and
  `git diff --check` passed. The build retained the existing Cornerstone
  browser-externalization and large-chunk warnings.

### Required corrections

- Add Indonesian `lang/id.json` values for every exact message in this list.
  Keep these source-string keys unchanged: they are existing domain messages
  rendered at the browser boundary, not new domain behavior.

  - `OperatorActiveSiteService.php`: `An authorized active site is required.`;
    `That site is not authorized for this Operator.`; `Select an authorized
    active site before continuing.`; `Site switching is blocked while arrival
    work is unresolved.`; `Site switching is blocked while identity
    verification is unresolved.`
  - `OperatorAttendanceService.php`: `The requested attendance list is
    unavailable.`
  - `OperatorArrivalService.php`: `An arrival operation identity is required.`;
    `The arrival confirmation is no longer valid.`; `The Operator is not
    assigned to this schedule.`; `The requested arrival is unavailable.`;
    `The arrival confirmation is missing or invalid.`; `Arrival time requires
    an explicit offset.`; `Arrival time is invalid.`
  - `OperatorAuthorization.php`: `Operator access is unavailable.`; `Select an
    active site before continuing.`; `Select an authorized active site before
    continuing.`; `Identity verification authorization is unavailable.`;
    `Operator administration authorization is required.`; `Operator
    administration authorization is unavailable.`
  - `OperatorIdentityVerificationService.php`: `The Operator profile is
    unavailable.`; `The arrived Member is unavailable for verification.`; `The
    Operator is not assigned to this schedule.`; `The verification operation
    conflicts with existing work.`; `This arrived Member is already claimed
    for verification.`; `This verification case is terminal and cannot be
    reopened.`; `Reclaiming a cancelled verification case requires explicit
    confirmation.`; `This Operator already has an open verification case.`;
    `The identity verification view is unavailable.`; `The requested
    verification asset is unavailable.`; `The verification decision is
    invalid.`; `A reason is required for this verification decision.`; `A
    terminal verification decision cannot be changed.`; `Current approved
    identity evidence is unavailable.`; `A terminal verification case cannot
    be cancelled.`; `The verification case is no longer open.`; `The active
    site is unavailable.`; `A valid verification operation is required.`; `A
    bounded reason is required.`
  - `OperatorPaperConsentConfirmationService.php`: `The paper-consent booking
    is unavailable.`; `A valid paper-consent operation is required.`; `Only a
    matched identity case can confirm paper consent.`
  - `OperatorCheckInTicketService.php`: `The check-in schedule is unavailable.`;
    `The Operator is not assigned to this shift.`; `The Operator is no longer
    assigned to this shift.`; `The paper ticket could not be loaded after
    issue.`; `The check-in case is unavailable.`; `Only a matched identity
    case can issue a paper ticket.`; `A valid ticket operation is required.`;
    `Ticket number must contain only letters, numbers, and hyphens up to 32
    characters.`

- Extend `MvpApplicationIndonesianUiLocalizationTest` with the above exact
  list. Assert that each key exists in `lang/id.json`, has a non-English
  Indonesian value, and that one representative `PortalController` error route
  renders its Indonesian value rather than its English domain message. Reuse
  the existing synthetic fixtures and test patterns; do not add a translation
  abstraction or alter exception categories.
- Retain the prior Image Gateway rejected-fixture test and its no-side-effect
  assertions. It remains the runtime proof for the Image Gateway error path.
- Repeat the text audit for Member, Operator, LCD, welcome, and all
  browser-facing controller response paths. In particular, audit every
  `__($exception->getMessage())` path in `PortalController` and
  `ImageGatewayController`; an exact exception message is closed only when it
  has an Indonesian `lang/id.json` value, is deliberately aborted before
  rendering, or is documented as an allowed dynamic/technical/legal exception.

### Additional verification

- Re-run the original task's localization, Member, Operator, public-LCD,
  basic-examination, X-ray readiness, synthetic-capture, study-access, and
  DICOM-results checks; include the negative synthetic-capture assertion and
  the representative Operator-controller error assertion.
- Re-run `npm run build` and `git diff --check`. Report warnings separately
  from failures.

### Unchanged remediation boundaries

- This remediation changes only localization configuration, `lang/id.json`,
  browser presentation/response copy, and tests. It must not modify the
  `89a6649` queue, consent, NIK-data, timing, seeding, or ticket-allocation
  behavior.
- The independently observed automatic-ticket allocation concurrency concern
  is outside this localization objective and is not authorised by this
  remediation.

## Expected terminal outcome

`IMPLEMENTATION AND VERIFICATION RESULT REQUIRED` — the Executor returns the
implementation revision and observed synthetic localization evidence. The
Planner/Reviewer then performs one consolidated review of the non-accepted
`89a6649` starting state and the localization result before scheduling the
deferred local rehearsal.
