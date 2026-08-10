---
title: Local Dummy Clinic-Core Flow
document_id: MHCS-TASK-CORE-LOCAL-001
version: 1.1
status: validated-published
language: en-US
last_updated: 2026-08-10
scope:
  - local synthetic clinic journey
  - public LCD queue display
  - paper questionnaire capture
authority_note: This task is executable only at its immutable publication revision.
---

# Executable Task

## Task identity

**Task title:**  
`Local Dummy Clinic-Core Flow`

**Task path:**  
`.agents/tasks/mvp-core-local-dummy-clinic-flow.md`

**Task contract state:**  
`Validated/Published — remediation`

**Delivery objective / Work Package / MVP:**  
`17 August clinic-day MVP core local proof / MVP-04 and WP-07/WP-10/WP-11/WP-12/WP-17`

**Owner / designated planning authority:**  
`Faliq Adlan, CTO`

## Delivery context

By Tuesday, 11 August 2026 at 12:00 Asia/Bangkok, the core branch must run one
complete clinic journey locally with synthetic data. The journey uses the
existing Member, Member-administration, Operator, and Operator-administration
foundations. It ends at the X-ray readiness boundary; Image Gateway, AI, and
MPIPS are explicitly separate work.

Real B2B roster import and account creation occur only after Wednesday
deployment. They are not part of this local proof.

## Baseline and task revision

**Original implementation baseline:**<br>
`49a2980c0a6c147f6c0fa8f49c49b73f0b17141b`

**Remediation starting revision (reviewed, not accepted):**<br>
`b4f5f153043961b8c03de654c08cef09b3936ee0`

**Task revision:**  
`Resolved from the immutable publication commit`

The published identity must resolve as:

```text
.agents/tasks/mvp-core-local-dummy-clinic-flow.md @ <commit SHA containing this exact task>
```

## Objective

**Objective:**  
Make a synthetic Member booking demonstrably progress through existing
front-desk, ticket/print, public queue, vital-sign, paper-questionnaire, and
X-ray-readiness core flow without implementing or simulating Gateway behavior.

## Authoritative inputs

### Governing authority

- `docs/mvp/beta-scope.md` — 17 August clinic-day flow and security boundary.
- `docs/mvp/decision-log.md` — MVP-DEC-021 through MVP-DEC-030, especially
  MVP-DEC-022, MVP-DEC-023, MVP-DEC-024, MVP-DEC-029, and MVP-DEC-030.
- `docs/mvp/roadmap.md` — core ownership and local deadline.
- `.agents/context/modules/member/project.md` — paper interview subjects,
  private-object boundary, and clinical-record ownership.
- `.agents/context/modules/operator/project.md` — ticket printing, queue, and
  Operator workflow ownership.

### Requirement traceability

- `MEM-068..MEM-083` → Member attendance, consent, and core clinical boundary.
- `MEM-189..MEM-191` → approved assessment and interview completion before
  X-ray readiness.
- `OPR-031..OPR-046` → Operator queue, call, ticket, and assessment flow.
- `MVP-DEC-022/023/030` → restricted assessment, printer/LCD, and photographed
  paper questionnaire behavior.

## Scope

### In scope

- Reuse or minimally compose the existing local-only Member, booking, admin,
  and Operator seeders into one documented, repeatable synthetic clinic setup.
- Preserve the existing private paper-ticket print page as the Printer Station:
  a staff member opens it on the printer laptop and uses the browser print
  dialog. The print view contains only site, shift/date, and ticket number.
- Add a login-free LCD browser page and safe read endpoint for one site. It
  shows only called ticket number, destination/stage, and recent calls; it
  contains no name, NIK, medical-record number, booking ID, image, or other
  Member information.
- Refresh the LCD safely without operator interaction and make Operator basic
  examination and X-ray call actions visible there after their existing
  successful state transitions.
- Add a required questionnaire-completed confirmation and one private JPEG/PNG
  paper-questionnaire photograph to the existing basic-examination flow.
- Store the questionnaire record under Member ownership with booking, site,
  operator, completion time, initial form version `V1`, image object metadata, and audit
  evidence. Reuse `PrivateObjectStore`, private-object cleanup, authorization,
  transaction, idempotency, and upload validation patterns already used for
  paper consent.
- Require a valid questionnaire capture as well as the existing vital-signs
  execution before basic-examination completion releases the ticket to the
  existing X-ray readiness worklist.
- Add focused synthetic tests and a runnable local walkthrough covering the
  entire objective.

### Out of scope

- Real B2B PDF/CSV import, real credentials, server database batching,
  deployment, or release approval.
- New Member self-registration, KTP/photo intake, profile completion, B2B
  bookings, B2B funding, points, payment, or entitlement behavior.
- OCR, AI extraction, structured digital questionnaire-field entry, Member
  questionnaire viewing/download, public file URLs, or public identity data.
- Image Gateway, AI, MPIPS, MinIO/Gateway implementation, NPZ/gain upload,
  capture submission, conversion, doctor, results, or Gateway administration.
- New Printer hardware drivers, printer APIs, a native LCD application, audio,
  WebSocket infrastructure, browser-upload of B2B data, or unrelated refactors.

### Preserved behavior

- Existing authenticated Operator worklists, site/shift authorization, claims,
  idempotency, audit, consent, ticket, and vital-sign behavior remain
  fail-closed.
- The LCD remains public but contains only operationally safe ticket data; the
  Printer Station remains private to the operator session.
- If LCD refresh fails, the display visibly states that its shown calls may be
  stale or disconnected; it must not silently present cached calls as current.
- The paper interview is the source document. Its photograph is private and is
  never exposed through Member, LCD, queue, administrator list, log, or audit
  metadata surfaces.
- The core stops at X-ray readiness and does not claim successful imaging,
  object-storage acceptance, AI, or MPIPS completion.

## Dependencies and assumptions

### Dependencies

- Existing synthetic Member, booking, administrator, and Operator seeders are
  available in local/testing environments.
- The existing private-object configuration is available locally for synthetic
  test uploads.
- A browser on the printer laptop and a separate TV/browser can open their
  respective URLs during later rehearsal.

### Approved assumptions

- The current approved paper interview topics and `yes`/`no`/`unknown`/
  `refused`/`not_applicable` response structure remain on the paper form.
- The initial paper-questionnaire form version is `V1`, reusing the existing
  paper-consent versioning convention.
- The MVP database stores only the form-completed confirmation and one photo,
  not individual answer extraction.
- A small recent-calls list is sufficient for the LCD; its exact presentation
  is implementation discretion as long as no Member identity appears.

### Remaining approval requirements

- Faliq Adlan, CTO, must authorize the commit that publishes this task before
  implementation starts.
- A separate release decision remains required before deployment, real-data
  import, or device rehearsal.

## Required capabilities

- Repository read and write.
- Local PHP/Laravel/Pest execution with synthetic database data.
- Local browser access for the documented manual walkthrough.

## Execution constraints

- Use existing Operator queue-call state/history as the LCD source; do not add
  a second queue, public identity projection, external real-time service, or
  Gateway dependency.
- The public LCD read model must be server-derived and site-scoped. It must
  return no protected identifiers, free text, image key, upload metadata, or
  Member/booking primary key.
- Accept only JPEG/PNG questionnaire images within the established private
  upload size limit. Validate bytes and MIME type before persistence.
- Ensure database and private-object failure paths do not leave a durable
  questionnaire record or orphaned private object.
- Reuse existing access control, audit sanitization, private-object cleanup,
  and idempotency patterns. Do not log uploaded image content or sensitive
  questionnaire data.
- Keep seeders explicitly local/testing only and synthetic. Do not place
  credentials or protected identifiers in committed documentation.

## Acceptance criteria

- [ ] A documented synthetic setup creates the existing dummy Member, booking,
  administrator, and Operator context needed for the local journey.
- [ ] An Operator can complete the existing arrival, identity verification,
  paper-consent, check-in, ticket issue, and Printer Station print flow using
  only synthetic data.
- [ ] A separate login-free LCD page displays only called ticket number,
  destination, and recent calls for its selected site; it never displays
  Member identity or a private object reference.
- [ ] Existing successful call actions update the LCD's safe data without a
  second queue state or manual LCD-side action.
- [ ] A failed LCD refresh visibly reports stale or disconnected data and a
  later successful refresh clears that state without exposing Member data.
- [ ] The Operator cannot complete basic examination until existing vital signs
  and a completed private paper-questionnaire photo have both been recorded.
- [ ] Invalid, missing, oversized, wrong-format, unauthorized, or replayed
  questionnaire submissions leave no completion state or private-object leak.
- [ ] After both conditions are met, the existing workflow releases exactly one
  X-ray-ready admission; no Gateway/AI/MPIPS behavior is invoked or claimed.
- [ ] Focused synthetic automated tests and the documented local walkthrough
  demonstrate the full core objective by the stated deadline.

## Verification requirements

### Required checks

- Run focused tests for LCD visibility/privacy, questionnaire upload and
  cleanup, completion gating, and the existing ticket/vital/X-ray regressions.
- Run the Member and Operator focused suites touched by the implementation.
- Run the synthetic setup/walkthrough from a fresh local database and inspect
  the Printer and LCD pages manually.
- Run `git diff --check` and the relevant migration suite.

### Required evidence

The Executor must report the exact implementation revision, commands actually
run, observed synthetic-only results, manual browser walkthrough steps, and
confirmation that no real roster, credential, interview form, or clinical image
was used.

## Stop conditions

- Stop and return to planning if the work requires Gateway behavior, an
  Image-Gateway-owned storage/process change, B2B import/funding/booking,
  real data, deployment, or a new questionnaire policy beyond MVP-DEC-030.
- Stop if private questionnaire storage cannot preserve the existing access,
  cleanup, and no-public-exposure boundaries.
- Stop if the baseline changes on overlapping Member, Operator queue, ticket,
  or storage surfaces.

## Authorized side effects

- Repository changes and local synthetic test data required for this task.
- Local private-object test artifacts that are removed by the test lifecycle.

Not authorized: real data, server account batching, deployment, commit, push,
dependency installation, or Image Gateway/AI/MPIPS implementation.

## Expected terminal outcome

`IMPLEMENTATION AND VERIFICATION RESULT REQUIRED` — an Executor returns the
implemented revision and observed local synthetic evidence for review.

## Remediation

**Review basis:**

- Governing task revision:
  `.agents/tasks/mvp-core-local-dummy-clinic-flow.md @ b99464d571a336817827ee1082e6510a542529c8`.
- Reviewed implementation revision:
  `b4f5f153043961b8c03de654c08cef09b3936ee0`.
- Reviewer evidence: the task's focused PHPUnit command passed with 36 tests
  and 481 assertions; implementation-only `git diff --check` passed. The
  required fresh-database browser walkthrough was not observed or recorded.

### Required corrections

- Make a failed LCD queue refresh visibly report a stale or disconnected state.
  Clear that state only after a successful safe queue response. Preserve the
  existing ticket-only public projection and automatic refresh.
- Add focused synthetic coverage for the stale/disconnected LCD state and the
  questionnaire rejection and cleanup boundaries: missing completion/photo,
  oversize or unsupported image, unauthorized/replayed input, and a failure
  after private-object storage that leaves neither a questionnaire record nor
  an orphaned private object.
- Run and report the fresh-database synthetic browser walkthrough in
  `docs/mvp/local-core-walkthrough.md`, including the visible LCD failure and
  recovery behavior. Do not use real data or cross the X-ray-readiness
  boundary.

### Additional verification

- Re-run the focused suite listed in `docs/mvp/local-core-walkthrough.md` and
  the added remediation tests.
- Run `git diff --check` on the remediation implementation diff.
- Record the exact implementation revision, commands, synthetic-only results,
  and manual browser observations for Reviewer evaluation.
