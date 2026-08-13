---
title: Operator Workstation Entry and Clinic Flow UI
document_id: MHCS-TASK-OPERATOR-WORKSTATION-UI-001
version: 0.2
status: validated-published — remediation
language: en-US
last_updated: 2026-08-11
scope:
  - dedicated Operator login entry point
  - Operator clinic workstation navigation
  - existing core-flow usability
authority_note: This task is executable only at its immutable publication revision.
---

# Executable Task

## Task identity

**Task title:**
`Operator Workstation Entry and Clinic Flow UI`

**Task path:**
`.agents/tasks/mvp-operator-workstation-ui.md`

**Task contract state:**
`Validated/Published — remediation`

**Delivery objective / Work Package / MVP:**
`12 August MVP delivery target / Operator priority / MVP-04 site flow`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The accepted clinic-core behavior is reachable through a generic shared login and link-oriented Operator dashboard. That is inadequate for an Operator at an examination site. All active MVP delivery targets 12 August; the Operator site flow is the first priority. This task gives the existing secure Operator flow a dedicated entry point and sequential workstation interface without changing domain behavior. Deployment and release authorization remain separate.

## Baseline and task revision

**Implementation baseline:**
`65a21bbcd005d81888abb1b6db8b4e939e80f97f`

**Task revision:**
`Resolved from the immutable publication commit`

## Objective

**Objective:**
Allow an authorized Operator to sign in through a dedicated Operator page and run the existing site workflow in order: select site, check in and verify, consent and ticket printing, basic examination, and X-ray readiness.

## Authoritative inputs

### Governing authority

- `docs/mvp/beta-scope.md` — 12 August MVP delivery target, Operator priority, and security boundary.
- `docs/mvp/decision-log.md` — MVP-DEC-022, MVP-DEC-023, MVP-DEC-029, and MVP-DEC-030.
- `docs/mvp/roadmap.md` — MVP-04 ownership on the main workstream.
- `.agents/context/project.md` — shared authentication foundation and separate Image Gateway dependency.
- `.agents/context/modules/operator/project.md` — Operator portal, site, queue, ticket, and assessment ownership.
- `.agents/context/design/mhcs-core-design.html` — Operator workstation design reference.

### Requirement traceability

- `OPR-031..OPR-046` → Operator portal, queue, ticket, and assessment flow.
- `MVP-DEC-023` → paired Printer Station and public LCD queue.
- `MVP-DEC-022/030` → limited basic examination and private paper questionnaire capture.
- `MVP-DEC-028/029` → 12 August MVP delivery target, Operator priority, and locally runnable Member and Operator clinic-day core.

## Scope

### In scope

- Add a distinct `/operator/login` page and entry route for Operator sign-in. Reuse existing user, credential, session, rate-limit, password-change, and Operator authorization mechanisms; do not create a second identity store or authentication foundation.
- Permit only an eligible active Operator to complete this entry; preserve the existing generic login failure for every other account.
- Replace the link-oriented `/operator` dashboard with a workstation whose next safe action is unmistakable: select an assigned site, open the assigned-shift attendance list, verify an arrival, record consent and issue/print the ticket, work the basic-examination queue, then work the X-ray-ready queue.
- Surface current site and relevant queue/worklist counts from existing server-derived records. Reuse existing routes and forms; do not create parallel queue, attendance, case, ticket, or clinical state.
- Apply the existing Operator design reference where it helps staff identify primary action, active site, queue state, and safe navigation.
- Add focused feature and browser coverage for the dedicated Operator entry, role denial, site selection, and visible ordered clinic workflow.

### Out of scope

- Separate Member-admin and Operator-admin panels or administration login pages; `/admin/login` remains unchanged.
- Member login redesign, registration, B2B import, profile work, or Member administration changes.
- Image Gateway, NPZ/gain upload, object-storage acceptance, DICOM conversion, Cornerstone viewer, AI, MPIPS, doctor routing, results, or Gateway administration.
- New API, WebSocket, queue, persistence, authorization, or authentication infrastructure.
- Deployment, release, real accounts, real clinical data, or device rehearsal.

### Preserved behavior

- `/login` remains the current Member/shared entry point and `/admin/login` remains the current shared administrator entry point.
- Existing Operator access remains fail-closed for inactive users, profiles, revoked grants, unauthorized sites, and unauthorized shifts.
- Printer Station remains private; the LCD remains login-free and ticket-only.
- The local core ends at X-ray readiness and invokes no Image Gateway, AI, or MPIPS behavior.

## Dependencies and assumptions

### Dependencies

- Accepted clinic-core baseline `65a21bbcd005d81888abb1b6db8b4e939e80f97f`.
- Existing local/testing synthetic clinic seeders and focused Browser runtime are available without a network download.

### Approved assumptions

- A branded Operator page may reuse the approved shared authentication foundation; it does not require a second login system.
- The workstation uses existing server-rendered Laravel presentation and routes instead of a new frontend stack.
- Member and Operator administration-panel separation is deferred to a later approved task.

### Remaining approval requirements

- No implementation approval beyond this published task.
- A separate release decision remains required before the 12 August deployment, real data, credentials, or device rehearsal.

## Required capabilities

- Repository read and write.
- Local PHP/Laravel/PHPUnit/Pest execution with synthetic data.
- Local Laravel Pest Browser Chrome using the already-available runtime only.

## Execution constraints

### Constraints

- Reuse `InteractiveMemberLoginService`, `CredentialVerifier`, existing middleware, `OperatorAuthorization`, `OperatorActiveSiteService`, and existing Operator routes where adequate.
- Do not grant role, permission, site, or shift access merely to make a synthetic journey work.
- Keep errors generic; do not disclose whether an email belongs to an Operator, Member, or administrator.
- Preserve password-change routing, session regeneration, intended-path validation, access logging, and throttling.
- Use server-derived site, queue, and worklist data. Do not expose protected identifiers, consent/questionnaire files, Member images, or private keys.
- Prefer existing Blade, controller, and CSS patterns. Do not add a frontend package, design system, or JavaScript framework.

## Acceptance criteria

- [ ] `/operator/login` clearly identifies the Operator workstation and is visually distinct from Member and administrator entry pages.
- [ ] An eligible active Operator signs in at `/operator/login` and reaches `/operator`; Member-only and administrator-only accounts cannot use it or learn why they were denied.
- [ ] Password replacement, session regeneration, generic failure, and access rechecks remain effective on the dedicated entry path.
- [ ] The workstation presents one obvious next step: select an assigned site when none is active, otherwise open the assigned-shift attendance journey.
- [ ] The existing clinic sequence is visible and ordered: arrival and verification, consent/ticket/Printer Station, basic examination, then X-ray readiness.
- [ ] LCD behavior remains a separate login-free safe display driven by the existing queue state.
- [ ] No new domain records, queues, identity store, roles, permissions, Gateway behavior, NPZ input, or DICOM viewer is introduced.
- [ ] Focused synthetic tests and one fresh Laravel Pest Browser Chrome journey demonstrate the dedicated entry and primary workstation path.

## Verification requirements

### Required checks

- Run focused authentication and Operator portal tests covering the dedicated entry, role/profile/permission denial, password replacement, site selection, and existing core-route regressions.
- Run the focused clinic-core suite in `docs/mvp/local-core-walkthrough.md`.
- Run a fresh synthetic Laravel Pest Browser Chrome journey scoped to the new Operator login and workstation path. Do not require or change the unrelated MVP-03 browser suite.
- Run `git diff --check` and the relevant migration suite. No migration is expected; report one if implementation proves otherwise.

### Required evidence

The Executor must report the exact implementation revision, commands actually run, synthetic-only results, browser observations, tests added or changed, known verification gaps, and confirmation that no real credential, roster, paper form, clinical image, Gateway, AI, or MPIPS behavior was used.

## Stop conditions

- Stop if the task requires separate administrator panels, a Member login redesign, a new identity/authorization model, or an administrator-access policy decision.
- Stop if an Operator-only entry would weaken generic failure, throttling, session, password-change, or authorization behavior.
- Stop if the workflow requires NPZ upload, DICOM access/viewing, Gateway storage, AI, MPIPS, or an unmerged Gateway contract.
- Stop if the baseline changes on authentication, Operator portal, queue, ticket, consent, or site-selection surfaces.

## Side-effect authorization

### Explicitly authorized side effects

- Repository changes and synthetic local test data necessary for this task.
- Local test artifacts removed by the test lifecycle.

Not authorized: Git commit, push, pull request, deployment, release, real data, dependency installation or replacement, network browser/package downloads, permission expansion, administrator-panel separation, or Image Gateway/AI/MPIPS implementation.

## Expected terminal outcome

`IMPLEMENTATION AND VERIFICATION RESULT REQUIRED` — an Executor returns the implemented revision and observed synthetic evidence for review.

## Remediation

**Review basis:**

- Governing task revision: `.agents/tasks/mvp-operator-workstation-ui.md @ 6a9de19f5b8c86bb52fc22eba2a6aec09e640ffa`.
- Owner-authored implementation revision: `1518206` (`15182062c5d239325097732987dc9ffe6bc63012`).
- Reviewed working-tree state: `1518206` (HEAD, origin/main at time of review).
- Reviewer evidence: code review confirms structural conformance — dedicated `/operator/login` route and view, `storeOperatorLogin` reusing `InteractiveMemberLoginService` and `InteractiveOperatorAccessResolver`, generic failure for non-Operator accounts, ordered workstation `<ol>` (steps 1–5), no new domain or Gateway behavior. Feature tests and a Browser Chrome test file were added. **PHPUnit/Pest run results, Browser Chrome observations, and `git diff --check` output were not observed or reported. No evidence file exists.**

### Required corrections

- Run the focused authentication and Operator portal feature suite (`tests/Feature/Operator/Mvp04OperatorPortalTest.php`) and report pass/fail counts and assertions.
- Run the Browser Chrome journey (`tests/Browser/Mvp04OperatorWorkstationTest.php`) with the existing lockfile-resolved Chromium binary only; stop and report if the binary is unavailable without a network download.
- Run `git diff --check` on the implementation diff and report the result.
- Create `docs/mvp/evidence/mvp-operator-workstation-ui.md` recording the exact implementation revision (`1518206`), commands run, synthetic-only test results, browser observations, and confirmation that no real credential, Gateway, AI, or MPIPS behavior was used.

### Additional verification

- No structural implementation change is required; the correction is verification-closure only.
- If the browser runtime is unavailable offline, stop and report that finding; do not download packages or change any lockfile.

### Implementation baseline for remediation

The remediation starts from the owner-committed implementation at `1518206`. No new implementation is expected; only verification and evidence creation are authorized.
