# Local Operator-to-DICOM walkthrough

This is one fresh, disposable, non-clinical local integration rehearsal for
the queued capture flow:

```text
Operator capture → durable private source set → queued MPIPS worker → private DICOM
```

It is not deployment, production, or release evidence. Do not use a real
roster, credential, patient, NIK, paper form, or clinical image.

## Setup

Use the native Laravel processes only. Do not use Docker/Compose, a reverse
proxy, a server database, a new queue mechanism, or a direct MPIPS request.

Configure these existing local-only variable names without exposing their
values:

- Application and security: `APP_KEY`, `MHCS_IDENTIFIER_KEY`,
  `MHCS_ACCESS_GRANT_KEY`, `MHCS_MANIFEST_KEY`,
  `MHCS_MANIFEST_KEY_ID`.
- Database: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
  `DB_USERNAME`, `DB_PASSWORD`.
- Private storage: `MHCS_PRIVATE_OBJECT_DISK=local` for this disposable local
  runtime. Production retains the existing private S3 disk and its variables:
  `AWS_ACCESS_KEY_ID`,
  `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`,
  `AWS_USE_PATH_STYLE_ENDPOINT`.
- MPIPS: `MPIPS_BASE_URL` using the documented local default
  `http://127.0.0.1:8014`, `MPIPS_API_KEY`, and `MPIPS_TIMEOUT_SECONDS`.
- Uploads: `MHCS_MAX_UPLOAD_MB=100` is the individual-file limit for NPZ,
  KTP/KIA, profile photo, informed consent, questionnaire, and other supported
  uploads. `config/mhcs.php` derives the two-file multipart request envelope
  from that single setting, including multipart overhead.
- Database queue: `QUEUE_CONNECTION=database` and `DB_QUEUE_RETRY_AFTER`.

WARNING immediately before the next command: `migrate:fresh` destroys every
table in the selected database. Confirm the configured target is an empty,
explicitly disposable local MySQL database, not a shared, staging, or
production database.

```bash
TARGET="." php artisan migrate:fresh --force
TARGET="." php artisan db:seed --class=Database\\Seeders\\MvpCoreClinicSeeder
```

The existing `MvpCoreClinicSeeder` creates five synthetic Members, one primary
Operator, two additional Operators on the same site and current eligible
shift, plus the attendance and LCD links. Generated credentials remain in the
ignored root `credential.txt` with mode `0600`; obtain them locally and never
copy their contents into documentation, evidence, logs, chat, or commands.

For the later local redeployment, set the ignored local
`PHP_CLI_SERVER_WORKERS=4`. The four native HTTP workers are interchangeable
and serve pages, consent/questionnaire uploads, and NPZ uploads. Start those
four workers plus the one Image Gateway queue worker in separate terminals:

```bash
PHP_CLI_SERVER_WORKERS=4 TARGET="." php artisan serve --no-reload --host=127.0.0.1 --port=8013
TARGET="." php artisan queue:work database --queue=image-gateway --timeout=390
```

The `--no-reload` flag is required for Laravel to create all four native PHP
HTTP workers. The worker consumes only the existing `image-gateway` queue and
uses the configured 390-second worker timeout. Do not inspect logs, object
storage, or secrets to diagnose a visible terminal failure.

## Primary Operator journey

1. Open `http://127.0.0.1:8013/operator/login`, sign in with the generated
   primary Operator credential, select the seeded site, and open the seeded
   attendance link.
2. Record the synthetic member's arrival and complete identity verification
   using the seeded local-only identity flow.
3. Confirm the paper consent and upload the required synthetic questionnaire
   image. The consent and questionnaire objects remain private.
4. Issue and print the ticket through the browser print dialog. The printed
   ticket contains only the approved ticket information.
5. Claim and call the ticket from the basic-examination worklist, complete the
   required basic examination and vital signs, then complete the examination.
6. Claim and call the X-ray-ready ticket. Open **Submit radiograph capture**
   and select exactly one CTO-approved, non-clinical local Grabber radiograph
   NPZ and its matching gain NPZ from their existing local location.

   The pair is never copied, renamed, opened, hashed, committed, or recorded.
   Do not document its path, filename, bytes, metadata, or contents. Submit
   the pair once; do not resubmit it.

7. Submit the pair once. The request durably stores the NPZ pair, manifest, and
   signature, then accepts the capture and queues MPIPS. During the XHR, keep
   the controls and native unload protection active and observe byte-level
   upload progress. Once safe status is `queued` or `processing`, the page
   may be closed; reopening resumes safe polling until DICOM is ready. After
   an interruption it identifies only a missing source component and permits
   only that original component to retry. The existing Image Gateway worker
   is the only MPIPS caller.
8. For a successful result, open the DICOM results worklist, open the returned
   study, and confirm the vertical read-only Cornerstone viewer renders with
   automatic VOI and zoom/pan only. Use the normal **Download DICOM**
   attachment action once. Do not render, extract, save, or record DICOM
   content or bytes.

## Second Operator authorization check

1. Sign out and sign in with the generated second Operator credential.
2. Select the same seeded site and current shift, open **DICOM results**, and
   confirm the same returned study is discoverable without exposing patient
   data.
3. Open the study, confirm the same vertical read-only viewer, and perform the
   normal **Download DICOM** attachment action.
4. Confirm the second Operator did not claim, submit, or change queue state.

## Cleanup

Use a `finally` block for the rehearsal. Stop the native web and queue
processes, delete only private objects created by this disposable database
through the existing application private-object store while their database
rows are still available, then clean the disposable database. Confirm both
database and created private-object cleanup pass. Never list a bucket or record
object identifiers. If cleanup cannot be confirmed, stop and return the
sanitised boundary to planning.

Record only sanitised PASS/FAIL observations: seed result, queue completion or
terminal failure, one persisted returned DICOM, primary viewer/download,
second-Operator worklist/viewer/download, and cleanup. This manual rehearsal
must remain clearly separate from automated fake-based tests and from
deployment, production, and release evidence.

## Focused verification

```bash
TARGET="." vendor/bin/phpunit \
  tests/Feature/Operator/MvpCoreClinicSeederTest.php \
  tests/Feature/Operator/Mvp04bIdentityVerificationTest.php \
  tests/Feature/Operator/Mvp04cPaperConsentConfirmationTest.php \
  tests/Feature/Operator/Mvp04dVerifiedCheckInTicketIssueTest.php \
  tests/Feature/Operator/Mvp04eAdvanceQueueAdmissionTest.php \
  tests/Feature/Operator/Mvp04fAtomicBasicExaminationClaimTest.php \
  tests/Feature/Operator/Mvp04gPrivateBasicExaminationCallTest.php \
  tests/Feature/Operator/Mvp04hPrivateBasicExaminationStartTest.php \
  tests/Feature/Operator/Mvp04jPrivateVitalSignsCaptureTest.php \
  tests/Feature/Operator/Mvp04kBasicExaminationCompletionTest.php \
  tests/Feature/Operator/Mvp04lAtomicXrayClaimTest.php \
  tests/Feature/Operator/Mvp04mPrivateXrayCallTest.php \
  tests/Feature/Operator/Mvp04pPublicQueueDisplayTest.php \
  tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php \
  tests/Integration/MemberDatabaseConformanceTest.php

TARGET="." vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --browser chrome
TARGET="." npm run build
TARGET="." vendor/bin/pint --test
TARGET="." git diff --check
```

Automated tests use fakes and never call AWS or MPIPS. The evidence report for
the one local integration rehearsal is
`docs/mvp/evidence/mvp-local-mpips-operator-rehearsal.md`.
