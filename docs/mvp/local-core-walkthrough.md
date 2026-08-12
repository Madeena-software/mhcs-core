# Local clinic-core walkthrough

This is a synthetic local rehearsal for the core branch only. It continues
through the local/testing Image Gateway capture, shared Operator DICOM-results
worklist, and normal DICOM download.
Do not use a real roster, credential, NIK, paper form, or clinical image.

## First-time setup

Use the repository's native local Laravel path. Do not use the production
deployment material, Docker/Compose, a reverse proxy, a queue worker, MPIPS,
or a server database for this rehearsal. The production-specialized
[`deployment/README.md`](../../deployment/README.md) is not the supported
local path.

Install the existing dependencies and build the frontend once:

```bash
composer install
cp .env.example .env
npm install
TARGET="." npm run build
```

Configure `.env` with local-only secret-managed values before running Artisan.
Do not show, generate, copy, log, or commit the values. The required key names
are `APP_KEY`, `MHCS_IDENTIFIER_KEY`, `MHCS_OBJECT_ENCRYPTION_KEY`,
`MHCS_ACCESS_GRANT_KEY`, `MHCS_MANIFEST_KEY`, and `MHCS_MANIFEST_KEY_ID`.
Configure `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, and `DB_PASSWORD` to an explicitly disposable local database.
Never use a staging, production, or shared server target.

## Fresh rehearsal setup

`migrate:fresh` destroys every table in the selected database. Confirm the
configured `DB_*` values point to an empty disposable local database before
running these commands:

In an interactive local terminal, run:

```bash
TARGET="." php artisan migrate:fresh --force
TARGET="." php artisan db:seed --class=Database\\Seeders\\MvpCoreClinicSeeder
```

`MvpCoreClinicSeeder` is the only dummy-account source for this rehearsal. It
creates one primary Operator and two additional Operators assigned to the same
site and current eligible shift, plus the attendance and LCD URLs. One-time
local credentials are written to the ignored root `credential.txt` with mode
`0600`; passwords are never printed to the terminal or copied into this guide,
tests, logs, chat, or deployment configuration.

The POC schedule is displayed from 13 August 2026 through 22 August 2026.
Local/testing attendance preserves the 22 August end boundary but allows the
flow to begin today even when the displayed schedule start is tomorrow.

The minimum file substitutions are:

- use a locally chosen synthetic JPEG or PNG for the paper-questionnaire
  capture; never use a real consent form or clinical image;
- use exactly
  `resources/fixtures/image-gateway/synthetic-radiograph-01.npz` and
  `resources/fixtures/image-gateway/synthetic-gain-01.npz` for synthetic
  capture.

Start the native Laravel server in the same local environment:

```bash
TARGET="." php artisan serve --host=127.0.0.1 --port=8013
```

Open `http://127.0.0.1:8013/operator/login`.

## Rehearse the core journey

1. Sign in with the synthetic Operator account printed by the seeder, open
   `/operator/site`, and select **Synthetic MVP-03 site**.
2. On a separate TV/browser, open the LCD URL printed by the seeder. It needs
   no login and shows only ticket numbers and destinations.
3. Open the attendance URL printed by the seeder. Confirm arrival, open the
   verification worklist, start verification, and perform the synthetic NIK
   lookup.
4. Confirm the paper consent, issue a ticket, then open its **Print** page on
   the printer laptop. The browser print dialog is the one-click printer step.
5. On the basic-examination worklist, claim the ticket and call it. Confirm
   the ticket and destination appear on the separate LCD browser. If the LCD
   request fails, confirm it visibly shows **Queue disconnected — shown calls
   may be stale.**; after the next successful refresh, confirm that warning
   clears and the safe ticket-only calls remain.
6. Start the examination and record blood pressure, temperature, height, and
   weight. BMI is calculated by MHCS.
7. Complete the approved paper health questionnaire outside MHCS, photograph
   it with a synthetic JPEG or PNG, and use **Upload paper questionnaire**.
   The application records only completion and stores that image privately.
8. Complete the basic examination. The ticket becomes X-ray ready. Claim and
   call it from the X-ray readiness worklist; confirm the LCD updates again.
9. Open **Submit synthetic capture** for the called admission. Select exactly
   the committed pair from the repository:
   `resources/fixtures/image-gateway/synthetic-radiograph-01.npz` and
   `resources/fixtures/image-gateway/synthetic-gain-01.npz`. The uploaded
   filenames may differ; the local synthetic bridge validates the exact fixture
   bytes and rejects arbitrary BED or NPZ contents. Confirm the
   browser warns before navigation while either file is selected, then submit
   the complete capture set once.
10. Confirm the accepted study opens as a vertical, read-only DICOM view with
    automatic VOI and zoom/pan only. Click **Download DICOM** and confirm the
    browser downloads `synthetic-study.dcm` as an attachment.
11. Sign out, sign in with `mvp-operator-two@example.test` using its one-time
    credential from `credential.txt`, and select **Synthetic MVP-03 site**.
12. Open **DICOM results** from the Operator navigation. Confirm the same
    accepted study appears without patient data, open it, and confirm the
    vertical read-only viewer renders.
13. Click **Download DICOM** and confirm the second Operator downloads the
    same `synthetic-study.dcm` attachment. Confirm no claim, submission, or
    queue-state change is made by the second Operator.
14. Repeat the DICOM results-worklist, viewer, and download checks with
    `mvp-operator-three@example.test`. Confirm this third Operator also has
    read-only access and cannot change the claim, submission, or queue state.

Stop there. This rehearsal uses only repository-owned synthetic fixtures in
`local` or `testing`; it does not run MPIPS, convert NPZ bytes, wait for AI, or
create a clinical result. It does not authorize server data, deployment,
release, real credentials, real identity, or real clinical files.

## Focused verification

```bash
TARGET="." vendor/bin/phpunit \
  tests/Feature/Operator/MvpCoreClinicSeederTest.php \
  tests/Feature/Operator/Mvp04jPrivateVitalSignsCaptureTest.php \
  tests/Feature/Operator/Mvp04kBasicExaminationCompletionTest.php \
  tests/Feature/Operator/Mvp04lAtomicXrayClaimTest.php \
  tests/Feature/Operator/Mvp04mPrivateXrayCallTest.php \
  tests/Feature/Operator/Mvp04pPublicQueueDisplayTest.php \
  tests/Feature/Operator/Mvp14SyntheticCaptureGatewayTest.php \
  tests/Integration/MemberDatabaseConformanceTest.php

TARGET="." vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --browser chrome
TARGET="." npm run build
TARGET="." git diff --check
```

## Remediation evidence — 10 August 2026

- A fresh SQLite database was migrated and seeded with
  `Database\\Seeders\\MvpCoreClinicSeeder` using synthetic-only data. The
  seeder produced one synthetic booking, operator site, eligible schedule,
  attendance URL, and LCD URL. No real roster, credential, NIK, paper form, or
  clinical image was used or recorded here.
- The local LCD page returned `200 OK`, included the disconnected/stale status
  marker, and its safe endpoint returned only `current` and `recent_calls` with
  empty arrays before any ticket was called. The native Node test executes the
  rendered LCD script through a failed refresh and a later successful safe
  response, verifying that the visible stale state appears and clears.
- The exact remediation-focused command was:

  ```bash
  TARGET="." vendor/bin/phpunit tests/Feature/Operator/MvpCoreClinicSeederTest.php tests/Feature/Operator/Mvp04jPrivateVitalSignsCaptureTest.php tests/Feature/Operator/Mvp04kBasicExaminationCompletionTest.php tests/Feature/Operator/Mvp04lAtomicXrayClaimTest.php tests/Feature/Operator/Mvp04mPrivateXrayCallTest.php tests/Feature/Operator/Mvp04pPublicQueueDisplayTest.php
  ```

  It passed with 40 tests and 520 assertions. The exact LCD behavior check was:

  ```bash
  TARGET="." node --test tests/JavaScript/lcd-queue.test.js
  ```

  It passed with 1 test. The fresh SQLite `migrate:fresh --force` step,
  `git diff --check`, and bounded Pint also passed.
- The fresh-database Chrome journey was exercised with synthetic fixtures:

  ```bash
  TARGET="." vendor/bin/pest tests/Browser/MvpCoreLocalClinicFlowTest.php --browser chrome
  ```

  It passed with 1 test and 16 assertions. The journey verified the private
  Printer Station ticket, absence of member identifiers, ticket-only LCD
  calls, the visible stale warning after a failed refresh, and warning
  clearance after refresh recovery. The broader pre-existing Browser suite
  was also started with the task command, but its administrator closure flow
  did not complete in this environment; no unrelated browser test was
  changed.

## Review disposition — 10 August 2026

The remediation implementation at
`65a21bbcd005d81888abb1b6db8b4e939e80f97f` is accepted as the local
clinic-core baseline for this bounded slice.

- Governing task revision:
  `.agents/tasks/mvp-core-local-dummy-clinic-flow.md @ 6274e74a82578554ad8272a8a4fce75c1ee151d4`.
- Accepted scope: synthetic Operator arrival, verification, consent, ticket
  print, safe LCD calls, vital signs, private paper-questionnaire capture, and
  X-ray readiness only.
- Observed review evidence: the 40-test / 520-assertion focused PHP suite, the
  LCD JavaScript test, fresh migrations, remediation-range `git diff --check`,
  and the focused Laravel Pest Browser Chrome journey (1 test, 16 assertions).
- Accepted limitation: the separate pre-existing
  `Mvp03AdminBookingClosureTest` browser flow did not provide a verifiable
  completion result in this environment. The designated planning authority
  classified it as non-blocking for this core task because it is outside the
  accepted Operator clinic-core objective. It remains an MVP-03 evidence gap.

This acceptance is not deployment, real-data, Gateway, AI, MPIPS, or release
authorization.
