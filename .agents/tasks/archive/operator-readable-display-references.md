---
title: Operator-Readable Schedule and DICOM References
document_id: MHCS-TASK-OPERATOR-DISPLAY-REFERENCE-001
version: 1.0
status: validated-on-publication
language: en-US
last_updated: 2026-08-13
scope:
  - immutable short display references for schedules and returned DICOM studies
  - Operator and schedule-administration primary labels
  - standard DICOM attachment filename
authority_note: This task is validated/published only when this exact file is committed unchanged and its immutable commit SHA is supplied in the Executor handoff. It authorizes no public route or relaxed access control.
---

# Executable Task

## Task identity

**Task title:**
`Operator-Readable Schedule and DICOM References`

**Task path:**
`.agents/tasks/operator-readable-display-references.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content; the governing SHA is supplied externally in the Executor handoff.`

**Delivery objective / Work Package / MVP:**
`Pre-deployment local MVP — resolve the readable-reference finding before the separate DICOM viewer task`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

User-led local-rehearsal feedback requires short, human-readable, unique
references as the primary labels for schedules and returned DICOM studies.
Current Operator pages instead render the internal `shift_schedules.id` and
`image_gateway_studies.id` UUIDs; the standard DICOM attachment filename also
contains the study UUID. These identifiers remain necessary for routes,
authorization, persistence, audit, and local contracts, but are unsuitable as
the operational label.

This task introduces immutable display data only. “Readable public references”
means authenticated user-visible labels, **not** anonymous/public URLs or
unauthenticated data access. The portrait DICOM viewer redesign is a separate
manual/UI task and remains out of scope.

## Baseline and task revision

**Implementation baseline:**
`9c051813ffc74fae57f305af767224264997cbf7` — accepted Operator capture
metadata implementation.

**Related accepted predecessors:**

- `.agents/tasks/operator-awaiting-ai-release-and-safe-navigation.md @ d76086c6bb04007c53fd06886e48cd5e2e95b7f3`
- `.agents/tasks/operator-capture-manifest-metadata.md @ b3115bb1ef133091593951908cbc62beb59cfc4e`

**Task revision:**
`The full SHA of the commit containing this exact task content, supplied by the Planner after publication.`

## Objective

Persist one short immutable unique display reference for every Member schedule
and returned Image Gateway study, then use it as the primary human-facing
label wherever the current Operator or schedule-administration UI represents
that schedule/study. Internal UUIDs continue to identify records and protect
every route and authorization check.

## Authoritative inputs

### Governing authority

- CTO user-led feedback recorded at
  `docs/mvp/evidence/mvp-local-deployment-readiness.md` §Planner/Reviewer
  feedback handoff, item 2: user-visible schedule and study references must
  be short, human-readable, and unique; internal UUIDs/authorization IDs are
  unchanged and not the primary label.
- `.agents/context/modules/operator/project.md` §Queue rules: a queue ticket
  is human-readable while technical identity and authorization remain separate.
- `.agents/context/modules/image-gateway/project.md` §Access and distribution:
  returned DICOM remains accessible only to authorised current-shift Operators
  at the active site.
- `docs/mvp/decision-log.md` MVP-DEC-035 and MVP-DEC-036: standard
  authenticated DICOM download and current-site/current-shift visibility are
  preserved; no public URL is allowed.
- `.agents/context/ui-language.md` and MVP-DEC-037: all new MHCS-authored
  browser text is Indonesian and comes from `lang/id.json`.

### Requirement traceability

- `MVP local-rehearsal feedback item 2` → readable, unique, immutable primary
  labels for schedules and studies.
- `UIL-001` → any new visible label or title is JSON-backed Indonesian copy.
- `OPR-108` and MVP-DEC-036 → identifier presentation must not alter enforced
  active-site/current-shift authorisation or create public access.

## Scope

### In scope

- Add one forward migration that gives both `shift_schedules` and
  `image_gateway_studies` an immutable, indexed/unique `display_reference`.
  It must backfill every already persisted row before the UI can render it;
  it must not rewrite capture sets, manifests, signatures, DICOM bytes,
  object keys, queue jobs, audit records, or UUID primary/foreign keys.
- Use the exact established MRN-style forms `JAD-AB12CD34` for schedules and
  `DCM-AB12CD34` for studies: the prefix plus `Str::upper(Str::random(8))`.
  The eight-character suffix is newly generated random alphanumeric text, not
  a UUID, UUID fragment, or sequential number. The stored value is immutable
  and unique within its own table. Use the database unique constraint and a
  bounded retry for the improbable collision. The migration backfills every
  existing row with the same pattern before the UI can render it.
- Assign a schedule reference in the existing
  `Mvp03ScheduleService::create()` transaction. A later schedule edit must
  never change it. Reuse the existing service/admin creation path; do not add
  a configurable numbering system or an operator-editable field.
- Assign a study reference only in the existing successful
  `ProcessCaptureSet::storeStudy()` transaction alongside the permanent
  `image_gateway_studies` insert. A worker retry or idempotent replay must
  reuse the already stored study/reference and never create a second one.
- Extend existing authorised read models/contracts only as needed to return
  `display_reference` next to the internal ID. Keep the internal ID as the
  route parameter and authorization lookup key.
- Render the display reference—not an internal UUID—as the primary schedule
  label on the Operator assigned-shifts page and attendance header, and as the
  primary study label in the DICOM results list and study page title. Add the
  schedule reference column to the existing Member schedule-administration
  table so its primary administrative label is also readable.
- Use the study display reference as the normal authenticated `.dcm`
  attachment filename. The download route and response protection remain the
  existing internal-study-ID route and active-site/current-shift check.
- Add only the necessary Indonesian labels to `lang/id.json`, including the
  schedule/study reference labels or title format. Do not add a locale switcher
  or hard-code new English UI copy.
- Update only the focused Feature/Browser fixtures that directly insert a
  schedule or study so the revised UI assertions use valid display references.

### Out of scope

- Any DICOM viewer redesign, popup/window behavior, vertical-monitor layout,
  Cornerstone configuration, loading/failure state, visual styling, or viewer
  dependency change.
- New public/anonymous routes, public object URLs, route-model binding by
  display reference, search API, operator-entered reference search, QR/barcode,
  configurable format, or generic identifier framework.
- Changing all booking, admission, ticket, capture, FHIR, DICOM UID,
  authorization, audit, outbox, object-key, manifest, or MPIPS identifiers.
- MPIPS integration, S3/local-disk policy, worker topology, DICOM-byte
  processing, local deployment/reseed, external service calls, deployment,
  production mutation, or release.

### Preserved behavior

- Every schedule UUID and study UUID remains unchanged in storage, URLs,
  controller validation, query scopes, audit/history metadata, local module
  contracts, and authorization decisions.
- A returned DICOM remains visible and downloadable only to an authenticated
  Operator whose active site and current shift authorise it. The change does
  not expose raw NPZ or create a public DICOM endpoint.
- Existing schedule creation/editing rules, future/overlap checks, capture
  acceptance, manifest signing, MPIPS idempotency, study persistence, and
  DICOM MIME/download headers remain unchanged except for the attachment name.
- A missing, foreign, revoked, or unauthorised UUID remains denied exactly as
  before; a display reference never becomes a substitute access credential.

## Dependencies and assumptions

### Dependencies

- `9c051813ffc74fae57f305af767224264997cbf7` is the accepted baseline.
- Existing schedule creation is serialized by the existing site transaction;
  successful study insertion is serialized by the existing capture-set
  transaction. The database provides the final uniqueness enforcement.

### Approved assumptions

- `JAD-AB12CD34` and `DCM-AB12CD34` are approved operational formats for this
  MVP. They reuse the current MRN convention (`MRN-` plus eight uppercase
  random alphanumeric characters), are suitable for operator speech and
  printing, and contain no clinical, patient, site, or UUID content.
- Existing local-MVP data must remain renderable, so the migration backfills
  old rows rather than requiring a reset or leaving an empty primary label.

### Remaining approval requirements

- None beyond repository changes within this task. Commit, push, deployment,
  data reset/reseed, AWS/S3/MPIPS access, production mutation, and release are
  not authorised.

## Required capabilities

- Repository read/write, shell, Laravel migration/testing, frontend build and
  formatter.
- No browser automation, external credentials, AWS/S3, real MPIPS, real DICOM
  inspection, deployment, or production capability is required or authorised.

## Execution constraints

- Reuse the existing schedule service, Image Gateway capture job, query
  services, controllers, Blade views, Filament resource, migrations, and
  `lang/id.json`. Do not introduce a package, generic identity service,
  additional queue/job, counter/sequence table, or new route. Reuse the
  existing MRN generator's compact random-string algorithm locally; do not
  change the existing Member MRN generator or create a shared framework.
- The migration must safely generate non-empty unique references for existing
  rows and have a working down path. It must be safe on the supported local
  test database and current production database driver; do not depend on a
  database-specific extension or an interactive data operation.
- Generate references server-side only. Never accept one from browser input,
  query parameters, headers, or MPIPS response data.
- Maintain the existing raw DICOM attachment behavior. Only its filename may
  change from the internal `capture-<UUID>.dcm` convention to the stored
  `DCM-AB12CD34.dcm` convention.
- Generate references server-side within the existing creation transaction.
  The smallest local private helper(s) are preferred over a new shared
  abstraction. A database unique constraint plus bounded collision retry is
  mandatory; a random value without collision handling is insufficient.

## Acceptance criteria

- [ ] Every pre-existing and newly created schedule has exactly one immutable,
  unique MRN-style `JAD-AB12CD34` display reference, while its UUID remains its database
  primary key, route value, authorization identity, and audit identity.
- [ ] Every pre-existing and newly accepted DICOM study has exactly one
  immutable, unique MRN-style `DCM-AB12CD34` display reference. A `ProcessCaptureSet`
  retry/idempotent replay does not create or change either study/reference.
- [ ] The assigned-shifts page, attendance page, DICOM results list, DICOM
  study page, and schedule administration table make the relevant display
  reference the primary label and do not render the full schedule/study UUID
  as that label.
- [ ] An authorised normal DICOM download sends the existing private,
  authenticated attachment response with a `DCM-AB12CD34.dcm`-style filename; its
  route still uses the internal study UUID. Foreign, inactive-site, revoked,
  or non-current-shift access remains denied.
- [ ] New visible labels/titles are Indonesian entries in `lang/id.json`; no
  new browser-visible English literal is introduced.
- [ ] No public reference is an access credential, no anonymous route/object
  URL exists, no raw NPZ is exposed, and capture/MPIPS/DICOM integrity and
  authorization behavior is unchanged.

## Verification requirements

### Required checks

- Add focused Feature coverage for schedule creation, existing-row migration
  backfill, immutable reference preservation on schedule edit, and a forced
  unique-collision retry using the MRN-style random format.
- Add focused Image Gateway coverage for assigned study references, old-study
  backfill, idempotent/retry preservation, authorised study/result projection,
  and the new attachment filename while maintaining denials.
- Add focused Browser coverage for the Operator assigned-shift, attendance,
  result-list, and DICOM-study primary labels plus the schedule-admin table.
- Run the changed focused tests, `vendor/bin/phpunit`, `npm run build`,
  `vendor/bin/pint --test`, and `git diff --check`. Do not call real MPIPS,
  AWS/S3, or inspect/copy any real NPZ/DICOM file.

### Required evidence

The Executor must report the immutable implementation revision; the exact
commands and observed results; migration result; changed tests; collision-retry
coverage; known gaps; and any material deviation or blocker. Local
checks must not be represented as deployment or live-integration evidence.

## Stop conditions

- Stop if a safe legacy backfill, bounded unique-collision retry, or
  database-enforced uniqueness cannot be implemented with one bounded migration on supported drivers.
- Stop if the change would require a public route, display-reference route
  binding, a relaxed active-site/current-shift check, a new identifier product
  policy, an external service call, or a new queue/worker.
- Stop if an unaccepted revision changes the declared baseline or overlaps the
  schedule/study creation paths before execution starts.
- Stop if the compact reference format conflicts with a governing approved
  authority discovered during execution.

## Side-effect authorization

### Explicitly authorised side effects

- Repository changes limited to one migration, the existing schedule/study
  creation and authorised projection paths, relevant views/Filament resource,
  `lang/id.json`, and focused tests.
- Local migration and test/build/format/diff commands only.

Not authorised: Git commit, push, pull request, deployment, reset/reseed,
production or external-system mutation, credential access/disclosure,
dependency installation, or raw clinical-file handling.

## Expected terminal outcome

`REVIEW REQUIRED` — return one immutable implementation revision with concise,
redacted verification evidence. The Planner/Reviewer determines acceptance
before creating the separate DICOM-viewer task or moving to local rehearsal.

## Remediation

**Review basis:** `1f9763d` — implementation review after the published task
revision `91982bb43f591e818299df37c011de5e9490570c`.

### Required corrections

- Enforce `ShiftSchedule.display_reference` immutability at the existing Member
  model boundary, using the same established immutable-field pattern that
  protects `Member.medical_record_number`. A direct Eloquent update that
  changes an existing schedule reference must fail before persistence; normal
  schedule edits must retain the reference.
- Apply the same bounded database-unique collision retry to every non-test
  runtime schedule-construction path, including `MvpBookingSeeder`. A local
  dummy-data collision must not make the seeder create an invalid schedule or
  fail merely because `JAD-XXXXXXXX` was already assigned. Keep this local to
  the existing seeder/service paths; do not introduce a generic identifier
  framework, a counter, or a new runtime service.
- Add focused tests proving the direct schedule-model mutation is rejected and
  proving the local booking seed path stores a valid unique MRN-style schedule
  reference. Preserve every accepted behavior from the original task.

### Additional verification

- Run the changed focused tests, `vendor/bin/phpunit`, `npm run build`,
  `vendor/bin/pint --test`, and `git diff --check`.
- Report the immutable remediation implementation revision, exact commands and
  observed output, tests added/changed, and any deviation or blocker.

### Browser-evidence correction

**Review basis:** `f4b3e33` — remediation implementation review after task
revision `64b3bd012cb589486b05f77e72c4920f80869090`.

The isolated required browser command fails before display-reference assertions
because two pre-existing test files assert English strings while the approved
Indonesian JSON registry renders the corresponding Indonesian strings. The
observed screenshots show the expected authenticated pages, not a failed route
or a display-reference error. This is a test-expectation correction only.

### Required correction

- In `tests/Browser/Mvp03AdminBookingClosureTest.php` and
  `tests/Browser/Mvp04OperatorWorkstationTest.php`, replace only stale static
  English UI assertions/actions with the already-rendered Indonesian values
  from `lang/id.json` or established Indonesian administration labels. Preserve
  all route, permission, workflow, fixture, and display-reference assertions.
  Do not change application UI, translation registry, authorization, or
  browser-test execution configuration.

### Additional verification

- Run the two corrected browser files in isolation, then rerun the original
  task's focused Feature/Browser command sequentially (not concurrently with
  a database-resetting suite).
- Rerun `vendor/bin/phpunit`, `npm run build`, `vendor/bin/pint --test`, and
  `git diff --check`, reporting exact observed results.
