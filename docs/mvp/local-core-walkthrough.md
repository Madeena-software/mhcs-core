# Local clinic-core walkthrough

This is a synthetic local rehearsal for the core branch only. It stops at
X-ray readiness. Do not use a real roster, credential, NIK, paper form, or
clinical image.

## Set up

In an interactive local terminal, run:

```bash
# Provide APP_KEY and the required MHCS_* key variables from local-only
# secret-managed configuration; do not commit their values.
php artisan migrate:fresh
php artisan db:seed --class=Database\\Seeders\\MvpCoreClinicSeeder
php artisan serve
```

The local-only seeder prints the temporary synthetic account credentials, the
synthetic NIK needed for the front-desk lookup, an attendance URL, and the LCD
URL. Keep those values in the local terminal only; do not copy them into a
spreadsheet, commit, chat, or deployment environment.

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

Stop there. This rehearsal does not capture images, call Image Gateway, wait
for AI, run MPIPS, or create a result.

## Focused verification

```bash
vendor/bin/phpunit \
  tests/Feature/Operator/MvpCoreClinicSeederTest.php \
  tests/Feature/Operator/Mvp04jPrivateVitalSignsCaptureTest.php \
  tests/Feature/Operator/Mvp04kBasicExaminationCompletionTest.php \
  tests/Feature/Operator/Mvp04lAtomicXrayClaimTest.php \
  tests/Feature/Operator/Mvp04mPrivateXrayCallTest.php \
  tests/Feature/Operator/Mvp04pPublicQueueDisplayTest.php
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
- The visual browser walkthrough could not be completed in this execution
  environment: no Chromium/Firefox binary or installed `node_modules` was
  available, and dependency installation is outside this task's authority.
  The required next evidence is to repeat the documented walkthrough in a
  browser-capable local environment and record the Printer Station page plus
  the LCD failure/recovery observation; no browser success is claimed here.
