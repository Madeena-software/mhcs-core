---
title: Release Awaiting-AI Claims and Restore Safe Operator Navigation
document_id: MHCS-TASK-OPERATOR-AWAITING-AI-001
version: 1.0
status: draft
language: en-US
last_updated: 2026-08-13
scope:
  - release the clinical claim at durable capture acceptance
  - preserve authorised capture-status polling after that release
  - make reported Operator operational routes safe before site selection
  - direct attendance rows with a returned DICOM to the appropriate result action
authority_note: This task is executable only after this exact content is committed and its immutable task revision is supplied to the Executor.
---

# Executable Task

## Task identity

**Task title:**
`Release Awaiting-AI Claims and Restore Safe Operator Navigation`

**Task path:**
`.agents/tasks/operator-awaiting-ai-release-and-safe-navigation.md`

**Task contract state:**
`Draft — validated/published only when this exact content is committed and its commit SHA is supplied.`

**Delivery objective / Work Package / MVP:**
`Pre-deployment local MVP — resolve reported Operator workflow blockers before another manual rehearsal`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The user-led local rehearsal recorded nine findings at
`docs/mvp/evidence/mvp-local-deployment-readiness.md` in the implementation
baseline. This task addresses only the connected operational-navigation line:
the post-capture claim lifecycle, the capture page's safe observation after
acceptance, safe worklist/site routing, and the attendance handoff to an
already returned DICOM.

Observed code confirms the root cause of the reported later-basic-examination
claim conflict. `ImageGatewayCaptureService::acceptSources()` changes the
admission to `awaiting_ai` but retains `operator_profile_id`. The unique active
claim consequently prevents that Operator from claiming another clinical
ticket. This contradicts the approved Operator rule that `awaiting_ai` consumes
no Operator station. The accepted capture set already preserves its submitting
Operator, so releasing the admission claim need not lose submission attribution.

The same rehearsal also found four direct worklist routes that turn a missing
active-site selection into a 403 or an uncaught `OperatorException`, and found
that an attendance row with an available study still points to the basic
examination worklist. These are safe user-flow defects, not an authorization
policy change.

The reported human-readable display references and the portrait DICOM viewer
redesign are intentionally separate follow-up objectives. They must not be
folded into this operational remediation.

## Baseline and task revision

**Implementation baseline:**
`34b1265683c6a8958723a7c204a0410e33b784b9` — current committed local
rehearsal feedback record, following the accepted FormData snapshot remediation
at `195b1e10020697e0899913a691ab20725c73e8e5`.

**Related accepted predecessor:**
`.agents/tasks/operator-async-capture-status-and-worklist-sync.md @
8afd3dedc9f7e4920d59beb9e94d2e480bd6bc9f`

**Task revision:**
`resolved when published`

## Objective

Make a durably accepted X-ray capture release its Operator clinical-stage
claim while preserving the submitting Operator's authorised status poll; then
make the reported Operator entry points recover safely to site selection or an
authorised DICOM result instead of returning an operational error page.

## Authoritative inputs

### Governing authority

- CTO user-led feedback, recorded verbatim as planning input in
  `docs/mvp/evidence/mvp-local-deployment-readiness.md` §Planner/Reviewer
  feedback handoff, items 1, 3, 4, and 6–9.
- `docs/mvp/decision-log.md` — MVP-DEC-036 and MVP-DEC-041: the member visit
  ends at durable capture acceptance; DICOM results are visible to authorised
  active-site/current-shift Operators; source acceptance, queued processing,
  and safe status polling are one uniform flow.
- `.agents/context/modules/operator/project.md` §Queue rules, §NPZ draft and
  submission flow, and §Read-only image access: `awaiting_ai` consumes no
  station; claims remain atomic; an authorised Operator may view returned
  DICOM only in the active site/current shift.
- `.agents/context/modules/image-gateway/project.md` §Submission boundary,
  §Completion rules, and §Access and distribution: Image Gateway owns capture
  state and makes returned DICOM available only through authorised references.
- `.agents/context/project.md` — module ownership, shared authenticated
  context, and Image Gateway's durable-source/queued-worker boundary.
- `.agents/context/ui-language.md` and `docs/mvp/decision-log.md`
  MVP-DEC-037 — Indonesian UI copy is retrieved from `lang/id.json`.

### Requirement traceability

- `OPR-014`, `OPR-040`, and `OPR-043` → atomic clinical claims, durable
  acceptance, and the `awaiting_ai` transition.
- `OPR-060` and MVP-DEC-036 → authorised active-site/current-shift DICOM
  discovery remains protected while an individual returned study is available.
- `OPR-108` → site, shift, claim, and examination scope must remain server
  enforced; a friendly route recovery must not become an access bypass.
- `UIL-001` → any newly introduced visible message is Indonesian JSON copy.

## Scope

### In scope

- Correct the durable acceptance transaction so it changes the admitted X-ray
  ticket to `awaiting_ai` **and releases its active `operator_profile_id`
  claim** atomically. Preserve the existing capture-set submitting-Operator
  attribution, queue-history attribution, audit/outbox effects, capture ID,
  source/DICOM ownership, idempotency, and queued `ProcessCaptureSet` dispatch.
  Do not release a claim while a source component is still missing or while
  capture acceptance has failed.
- Adapt the existing capture-form and capture-status authorisation so the
  submitting Operator can keep observing that accepted capture after the queue
  claim is released, but cannot alter or re-upload its already complete source
  set. This continued poll must still require the same authenticated active
  site and current-shift assignment. A different Operator must not gain
  capture-page/status authority merely because they share the site; returned
  DICOM visibility remains governed by the existing authorised study routes.
- Preserve the existing client-side capture lock after durable acceptance:
  no file input or submit action becomes usable while it polls `queued` or
  `processing`. Closing the page remains safe after acceptance, and only the
  existing component-only retry path can become editable when a source is
  genuinely incomplete or retryable.
- Ensure the original Operator can atomically claim a later eligible basic or
  X-ray ticket after durable acceptance of the earlier ticket. A real
  concurrent/stale claim conflict must remain non-mutating and recover to its
  appropriate worklist with a clear existing or Indonesian JSON-backed
  operational message, never an HTTP 409/500 error page. Apply the same safe
  handling to the analogous X-ray claim endpoint where it follows the same
  established controller pattern.
- For the following authenticated Operator GET routes, treat only
  `active_site_required` as a redirect to `operator.site` with a safe
  site-selection message: `operator.eligible-shifts`,
  `operator.basic-examination-worklist`,
  `operator.xray-readiness-worklist`, and `operator.study.results`. Preserve
  403 denial for invalid/revoked authorization, a foreign site, or a missing
  current-shift assignment; do not catch all authorization failures as site
  selection.
- Annotate the authorised attendance result before rendering. When a row's
  booking has an already returned DICOM study available to the current
  active-site/current-shift Operator, make its next safe action open that
  study. Otherwise preserve the existing attendance, verification, and basic
  examination actions. Use the existing Image Gateway query/service boundary;
  do not make Member own Image Gateway tables or duplicate DICOM data in the
  Operator module.
- Add or amend only focused Feature/Browser tests and `lang/id.json` entries
  needed to prove the above behaviors.

### Out of scope

- The short human-readable schedule/study display-reference policy, schema,
  generation, or legacy backfill.
- Any DICOM viewer visual redesign, popup/window behaviour, portrait monitor
  layout, Cornerstone feature change, or new viewer dependency.
- MPIPS contract/conversion changes; queue topology; S3/local-disk policy;
  object encryption/retention; source upload concurrency; real MPIPS/S3/AWS
  calls; database reset/reseed; deployment; production mutation; or release.
- New routes, APIs, data exports, public URLs, raw-NPZ access, public DICOM
  access, relaxed active-site/current-shift authorization, or a data migration
  / historical backfill.

### Preserved behavior

- Before durable acceptance, one Operator claim remains required to upload or
  retry only the missing NPZ source component. Invalid/failed/incomplete
  capture does not release that claim and does not permit another capture set.
- After durable acceptance, the browser page remains read-only/polling only;
  it cannot dispatch another capture, repeat MPIPS, or mutate the queue.
- Returned DICOM list/view/download access remains restricted to an
  authenticated Operator whose active site and current shift authorise the
  examination. Standard authenticated `.dcm` download remains unchanged; raw
  NPZ stays unavailable.
- Queue transitions, source bytes, manifests, checksums, DICOM validation,
  audit/history records, current-shift checks, and foreign/revoked denials
  remain fail-closed and unchanged except for the specified release of an
  accepted ticket's active claim.
- Indonesian remains the sole UI locale. Existing visible text is reused where
  sufficient; every new MHCS-authored visible string belongs in `lang/id.json`.

## Dependencies and assumptions

### Dependencies

- The existing `image_gateway_capture_sets.operator_profile_id` already stores
  the submitting Operator independently of
  `operator_queue_admissions.operator_profile_id`; therefore no new persistence
  column or migration is required for this fresh local-MVP flow.
- Existing Image Gateway study authorisation already proves active-site/current-
  shift access; the attendance handoff may reuse that boundary rather than
  reconstructing DICOM authorization from request values.

### Approved assumptions

- The current local rehearsal uses disposable data. This task intentionally
  corrects the forward transition only; it does not alter already accepted
  historic rows. A later clean local rehearsal starts from its approved reset
  procedure.
- A single returned study maps directly to its attendance row for the current
  MVP's one-capture set. If the current authorised query identifies more than
  one returned study for a row, route the action to the existing DICOM results
  worklist rather than selecting a study arbitrarily.

### Remaining approval requirements

- None beyond this task's authority for repository changes and fake-backed
  local verification. Commit, deployment, local reset/reseed, service control,
  live MPIPS/S3/AWS use, external mutation, and release remain unauthorised.

## Required capabilities

- Repository read/write; shell; PHP/Laravel test execution; browser-test and
  frontend-build tooling.
- No secret, AWS/S3, MPIPS, production, deployment, or external-system
  capability is required or authorised.

## Execution constraints

- Reuse the existing capture service, queue admission/history tables,
  authenticated-context checks, controllers, Blade views, JSON copy registry,
  and focused tests. Do not introduce a worker, queue, package, middleware,
  persistence abstraction, or duplicate DICOM store for this task.
- Make the release in the existing durable source-acceptance transaction so a
  concurrent claim cannot observe a partially accepted state. Do not rely on a
  browser-side lock for this invariant.
- Maintain a narrow distinction between a missing active site (recoverable
  site-selection flow) and all other authorization failures (deny as today).
- The attendance handoff must use an authorised Image Gateway query, not an
  unscoped table lookup; it must not expose object keys, bytes, checksums,
  manifests, raw NPZ, or a study that current authorisation would deny.
- Keep logs, test output, and evidence free of credentials, identifiers,
  private-object keys, NPZ/DICOM bytes, and external-service responses.

## Acceptance criteria

- [ ] Once the complete radiograph/gain/manifest/signature set is durably
  accepted, the ticket is `awaiting_ai` with no active queue claim, while the
  capture set and history retain the submitting Operator and exactly one
  worker dispatch/audit/outbox outcome. An incomplete, invalid, or failed
  capture retains its existing claim and retry behavior.
- [ ] The original submitting Operator can poll the accepted capture's safe
  `queued`, `processing`, `ready`, or failure status after claim release;
  the capture controls remain unusable after acceptance. A second same-site
  Operator remains denied that capture-status endpoint, while the existing
  authorised returned-DICOM list/view/download policy remains unchanged.
- [ ] The accepting Operator can claim a later eligible basic or X-ray ticket
  without waiting for MPIPS/DICOM, consistent with `awaiting_ai` consuming no
  station. Competing/stale basic and X-ray claim attempts stay atomic,
  non-mutating, and return a safe worklist response rather than a 409/500 page.
- [ ] Direct navigation to each of the four reported worklists with no active
  site redirects to the site-selection page and exposes a safe Indonesian
  message. Direct navigation with a selected but invalid/revoked site or no
  current-shift authority remains denied.
- [ ] An attendance row with one currently authorised returned study has an
  explicit DICOM-result action to that study; a row with no study preserves its
  previous next action; multiple authorised studies never select one
  arbitrarily.
- [ ] No new public/temporary object link, raw NPZ action, DICOM download
  policy, MPIPS call, storage behavior, schema migration, or viewer behaviour
  is introduced.

## Verification requirements

- Extend `tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php` to
  prove the accepted transition clears the admission claim, retains submitting
  attribution, dispatches once, allows the original safe poll after release,
  denies a second Operator's capture status, and leaves returned-DICOM
  authorisation unchanged.
- Extend the focused atomic basic/X-ray claim tests to prove an Operator may
  claim new eligible work after an accepted `awaiting_ai` ticket, while a real
  concurrent/stale conflict has no mutation and returns a safe redirect/error
  response rather than 409.
- Extend `tests/Feature/Operator/Mvp04OperatorPortalTest.php` (or the smallest
  existing relevant Feature test) for all four no-active-site routes, one
  revoked/foreign denial, and attendance with zero, one, and multiple
  authorised returned studies.
- Extend the existing browser capture rehearsal only if needed to prove the
  accepted-state controls remain disabled while status polling continues; do
  not call live MPIPS, S3, or inspect real private files.
- Run the changed focused Feature/Browser tests, then `vendor/bin/phpunit`,
  `npm run build`, `vendor/bin/pint --test`, and `git diff --check`. Report
  commands actually run, observed results, changed tests, and any limitation.

## Stop conditions

- Stop if releasing the accepted claim cannot preserve submitting-Operator
  attribution and capture-page status authorization without changing the
  approved active-site/current-shift policy or adding a schema migration.
- Stop if an attendance DICOM action requires an unapproved Member/Image
  Gateway ownership change, unscoped query, or new cross-module data copy.
- Stop if the reported routes cannot distinguish missing active site from a
  real authorization failure without broadening access.
- Stop if satisfying the task would require a viewer redesign, display-reference
  policy, MPIPS/storage/deployment change, public/private-object access change,
  or live external call.

## Side-effect authorization

### Explicitly authorised side effects

- Repository changes within scope: application code, existing route/view
  behavior, focused tests, and Indonesian JSON copy.
- Local fake-backed test/build/format/diff commands only.

Not authorised: Git commit, push, pull request, migrations, local database or
private-object reset, process start/stop, live MPIPS/S3/AWS call, deployment,
production/server mutation, release, secret access/disclosure, or raw clinical
file inspection/copying.

## Expected terminal outcome

`REVIEW REQUIRED` — return one immutable implementation revision plus concise,
redacted verification evidence. The Reviewer will determine acceptance before
the separate display-reference, DICOM-viewer, or renewed local-rehearsal work.
