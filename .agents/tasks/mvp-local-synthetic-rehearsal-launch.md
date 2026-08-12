---
title: Local Synthetic Clinic Rehearsal Launch Guide
document_id: MHCS-TASK-LOCAL-SYNTHETIC-LAUNCH-001
version: 1.1
status: validated-published
language: en-US
last_updated: 2026-08-12
scope:
  - reproducible native local Laravel launch
  - five synthetic Members and two-Operator seed data
  - complete Operator/DICOM rehearsal handoff
authority_note: This task becomes executable only when its exact content is committed and published as validated.
---

# Executable Task

## Task identity

**Task title:**
`Local Synthetic Clinic Rehearsal Launch Guide`

**Task path:**
`.agents/tasks/mvp-local-synthetic-rehearsal-launch.md`

**Task contract state:**
`Validated/Published when this exact content is committed`

**Delivery objective / Work Package / MVP:**
`12 August MVP delivery target / local synthetic clinic handoff / WP-14 and WP-23`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The owner-reviewed clinic flow, Indonesian UI localization, and atomic
automatic-ticket allocation are accepted through the shared DICOM-results
worklist. They must now be runnable by a developer without reading task
history or guessing which seeders, commands, accounts, fixtures, or workflow
steps apply. This task produces one safe native-local launch and rehearsal
guide; it is not server deployment or release work.

## Baseline and task revision

**Implementation baseline:**
`bd8f3c8cfb5d6bcadec3776e921e2a31ca730101` — consolidated accepted local
baseline: owner-reviewed clinic flow, Indonesian UI localization, and atomic
automatic ticket allocation.

**Task revision:**
`Resolve from the commit that publishes this exact task content before execution.`

## Objective

**Objective:**
Provide a verified, copyable local-only setup and rehearsal path that starts a
fresh synthetic database, seeds five dummy Members and two eligible Operators,
launches the native Laravel application, and guides the user from Operator
login through automatic ticket issue, capture, DICOM viewing, second-Operator
discovery, and normal attachment download.

## Authoritative inputs

### Governing authority

- `docs/mvp/decision-log.md` — MVP-DEC-024, MVP-DEC-034, MVP-DEC-035, and MVP-DEC-036.
- `docs/mvp/beta-scope.md` — synthetic-only boundary and separate release gate.
- `docs/mvp/beta-gap-register.md` — MVP-GAP-020, MVP-GAP-022, and MVP-GAP-023 remain open.
- `.agents/context/modules/operator/project.md` and `.agents/context/modules/image-gateway/project.md`.
- Accepted local DICOM-results worklist, Indonesian UI, and automatic-ticket
  allocation implementation at `bd8f3c8cfb5d6bcadec3776e921e2a31ca730101`.
- `.agents/tasks/mvp-operator-automatic-ticket-allocation.md @
  3a50211590cb81c350dda30cdab82efcda8acdb1` — blank ticket input is allocated
  safely within the active site and shift.

### Requirement traceability

- `MVP-DEC-034` → local/testing synthetic capture and DICOM bridge only.
- `MVP-DEC-035` and `MVP-DEC-036` → normal authenticated raw-DICOM download and authorised second-Operator access.
- `OPR-031..OPR-046`, `OPR-057..OPR-060`, and `IMG-060` → complete local Operator flow and read-only DICOM access.

## Scope

### In scope

- Consolidate the current local prerequisites and exact native Laravel commands
  in `README.md` and/or `docs/mvp/local-core-walkthrough.md`, using the
  existing Composer, npm, Vite, Artisan, and `MvpCoreClinicSeeder` mechanisms.
- Clearly distinguish first-time setup from each fresh rehearsal, and warn
  that `migrate:fresh` destroys the selected database and therefore requires an
  explicitly configured empty local database.
- Document the required local-only application keys without showing, creating,
  copying, logging, or committing their values. Tell the user to retain
  one-time synthetic credentials only in the interactive terminal that seeded
  them.
- Document the observed seed result without plaintext credentials: five
  synthetic Members, a primary Operator, a second same-site/current-shift
  Operator, the selected Member's booking, attendance URL, LCD URL, and
  repository-owned NPZ/gain fixtures. The guide must identify one Member as the
  ordered rehearsal journey without implying that the seed creates only one.
- Provide an ordered local rehearsal: primary Operator login and active-site
  selection; arrival, verification, consent, and blank-number automatic ticket
  issue through basic examination and X-ray call; synthetic NPZ/gain capture;
  DICOM viewer/download; then second Operator login, DICOM-results worklist,
  viewer, and download.
- State the minimum synthetic file substitutions required during the rehearsal:
  a locally chosen synthetic JPEG/PNG for consent/questionnaire capture and
  the committed NPZ/gain fixture pair for the capture form.
- Make the native `php artisan serve` endpoint and the build command explicit;
  link the repository’s production-specialized deployment material only to
  say it is not the supported local-rehearsal path.
- Update focused verification instructions to cover the synthetic seeder,
  DICOM access/worklist, two browser journeys, Vite build, and whitespace
  check. Run them against a disposable database where needed.

### Out of scope

- Server, Docker/Compose, reverse-proxy, certificate, queue-worker, or
  production deployment configuration or execution.
- MPIPS calls, asynchronous conversion, real NPZ/gain schema, AI, doctor
  routing, real DICOM results, object-storage changes, external systems, or
  a 37-member server import.
- Real user credentials, NIK, consent/questionnaire material, clinical images,
  or any committed plaintext secret.
- A new Artisan launcher, shell script, dependency, database schema, or
  alternative seeding mechanism when the existing standard commands suffice.

### Preserved behavior

- The existing `MvpCoreClinicSeeder` remains local/testing-only, synthetic,
  repeatable, produces five Members and two eligible Operators, and remains the
  only source of dummy accounts for this rehearsal.
- The documented blank ticket input exercises the existing automatic
  site-and-shift allocation; it does not introduce a ticket format or reset
  policy.
- The synthetic DICOM bridge remains local/testing-only and is not represented
  as MPIPS or as server-ready conversion.
- Private objects, raw NPZ, Member/Doctor access, public LCD privacy, and
  active-site/current-shift access rules remain unchanged.
- A local rehearsal and its documentation do not authorise deployment, release,
  real data, or close the open deployment/privacy/credential gaps.

## Dependencies and assumptions

### Dependencies

- A workstation with the repository’s required PHP, Composer, Node/npm, and a
  supported empty local database configured before any destructive Artisan
  command.
- Local-only secret-managed values for `APP_KEY` and the required `MHCS_*`
  security keys.
- Existing `vendor/`, `node_modules/`, `MvpCoreClinicSeeder`, fixture files,
  and local browser runtime.

### Approved assumptions

- Native Laravel launch via `php artisan serve` is the supported local
  rehearsal path; production-specialized Docker/deployment files are not.
- The synthetic seeder's interactive, one-time credential output is sufficient
  for local login guidance and must not be reproduced in repository documents.

### Remaining approval requirements

- Server deployment, real-member import, credential delivery, production
  storage, privacy/retention operations, and release remain separately
  approved.
- MPIPS integration requires its exact asynchronous API contract and a
  separate approved task.

## Required capabilities

- Repository read and write.
- Local PHP/Laravel, Composer, Node/npm, Vite, and disposable database execution.
- Local Chromium execution for the existing browser tests.

## Execution constraints

- Reuse and correct existing README/walkthrough content; do not create a
  parallel runbook, custom launcher, Docker path, or generated configuration.
- Treat every command that resets a database as destructive: state its exact
  target/precondition and never execute it against an unknown or server
  database.
- Do not inspect, print, copy, commit, or add placeholder values for any real
  secret. Do not record generated synthetic credentials in tests or evidence.
- Keep the guide runnable in `local`/`testing` only and explicit that the DICOM
  comes from the repository-owned synthetic bridge, not MPIPS.
- Verify documentation commands against a disposable local database and the
  native server path; do not claim Docker, production, CI, or server evidence.

## Acceptance criteria

- [ ] A developer can distinguish first-time setup from a fresh rehearsal,
  configure an empty local database and required local-only keys, and run the
  exact existing dependency, build, migration, seed, and native-server
  commands without using a server or production deployment document.
- [ ] The guide explains the destructive nature of the database reset and
  never includes a plaintext secret, generated credential, real identity, or
  real clinical file.
- [ ] The interactive seed output supplies five synthetic Members, both
  Operators, bookings, attendance URL, and LCD URL; the guide directs the user
  to one selected Member without reproducing credentials or implying a
  one-Member seed.
- [ ] The primary-Operator journey leaves the ticket-number input blank and
  confirms the generated private ticket before basic examination; it preserves
  the current automatic site-and-shift allocation behavior.
- [ ] The documented primary-Operator journey reaches synthetic capture,
  read-only vertical DICOM view, and normal attachment download; the second
  Operator can discover the same study from DICOM results and download it.
- [ ] The guide explicitly stops before MPIPS, real conversion, server data,
  and deployment, and preserves the current synthetic-only privacy boundaries.
- [ ] Focused PHP, Chromium, build, and whitespace evidence passes with only
  synthetic data and a disposable local database.

## Verification requirements

### Required checks

- Run the local synthetic seeder test, automatic-ticket allocation regression,
  focused Image Gateway/Operator capture and study-access suite, and existing
  X-ray claim/call regressions.
- Run each existing Chromium DICOM rehearsal journey, including the second
  Operator results-worklist journey, within the runner’s observable command
  window if needed.
- Run `npm run build` and `git diff --check`.
- Execute the documented migration/seed path only against a fresh disposable
  local database; confirm it creates five synthetic Member accounts, two
  eligible Operators, and their bookings without recording credentials.

### Required evidence

The Executor must report the exact implementation revision or working-tree
state, every command actually run, disposable database evidence, synthetic-only
scope, browser/build results, documentation changed, known gaps, and explicit
confirmation that no server, deployment, MPIPS, real data, or secret disclosure
occurred.

## Stop conditions

- Stop if the guide requires an unknown database target, a server, production
  secret, external credential handoff, Docker/Compose, MPIPS, or real data to
  be usable.
- Stop if the existing native local path cannot run without an unapproved new
  package, script, storage backend, queue worker, or configuration mechanism.
- Stop if observed seed behavior would require documenting a plaintext
  credential or protected identifier.

## Side-effect authorization

### Explicitly authorized side effects

- Repository documentation, focused test, and minimal supporting local-only
  changes required to make the existing rehearsal reproducible.
- Local build, browser, and disposable-database operations.

Not authorised: Git commit, push, pull request, deployment, release, server or
external mutation, real data, real credentials, MPIPS calls, dependency change,
or secret disclosure.

## Expected terminal outcome

`IMPLEMENTATION AND VERIFICATION RESULT REQUIRED`
