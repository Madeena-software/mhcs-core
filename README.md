# MHCS Core

MHCS Core is one Laravel modular monolith containing the Member, Operator,
Doctor, and Image Gateway modules. The Shared boundary contains only genuine
cross-cutting primitives and infrastructure; business rules remain in their
owning module.

## Stack

- PHP `^8.4`
- Laravel `^13.8`
- Filament `^5.0` (installed; product panels are added by later work packages)

## Local Operator-to-MPIPS rehearsal

The supported local rehearsal uses the existing native Laravel web process,
database queue worker, private S3 disk, and loopback MPIPS service. It is a
disposable, non-clinical integration rehearsal only; it is not deployment,
production, or release evidence. Do not use Docker, Compose, a reverse proxy,
a server database, real patients, or real credentials.

### First-time setup

Install the existing dependencies and build the frontend once:

```bash
TARGET="." composer install
TARGET="." cp .env.example .env
TARGET="." npm install
TARGET="." npm run build
```

Configure `.env` locally without showing, copying, logging, or committing any
value. The required application and encryption variable names are:

- `APP_KEY`
- `MHCS_IDENTIFIER_KEY`
- `MHCS_OBJECT_ENCRYPTION_KEY`
- `MHCS_ACCESS_GRANT_KEY`
- `MHCS_MANIFEST_KEY`
- `MHCS_MANIFEST_KEY_ID`

Use the configured private S3 disk and AWS variables:
`MHCS_PRIVATE_OBJECT_DISK`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`,
`AWS_DEFAULT_REGION`, `AWS_BUCKET`, and `AWS_USE_PATH_STYLE_ENDPOINT`.
Configure MPIPS with `MPIPS_BASE_URL` (the documented local default is
`http://127.0.0.1:8014`), `MPIPS_API_KEY`, and `MPIPS_TIMEOUT_SECONDS`.
Set `MHCS_MAX_UPLOAD_MB=100` for the maximum size of every individual upload
(NPZ, KTP/KIA, profile photo, informed consent, questionnaire, and other
supported files). The two-file NPZ request receives a derived native PHP
request limit of 201 MB to allow multipart overhead; each file remains capped
at 100 MB.
Configure `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`,
and `DB_PASSWORD` for an explicitly disposable local MySQL database. Use the
existing database queue settings `QUEUE_CONNECTION=database` and
`DB_QUEUE_RETRY_AFTER`; do not point any setting at a shared, staging, or
production target.

### Fresh rehearsal setup

WARNING immediately before the next command: `migrate:fresh` destroys every
table in the selected database. Confirm that the configured `DB_*` target is an
empty, disposable local database before continuing.

```bash
TARGET="." php artisan migrate:fresh --force
TARGET="." php artisan db:seed --class=Database\\Seeders\\MvpCoreClinicSeeder
```

`MvpCoreClinicSeeder` is the existing local/testing-only seed. It supplies five
synthetic Members, one primary Operator, two additional Operators assigned to
the same site and current eligible shift, and the attendance/LCD links. The
generated local credentials are written only to ignored `credential.txt` with
mode `0600`; obtain them locally and never print or copy their contents.

### Native processes

Run the following in separate local terminals using the same environment:

```bash
TARGET="." php artisan serve --host=127.0.0.1 --port=8013
TARGET="." php artisan queue:work database --queue=image-gateway --timeout=390
```

The worker consumes only the existing `image-gateway` queue and uses the
configured 390-second Image Gateway worker timeout. Do not add a queue, retry
policy, Artisan command, process manager, or infrastructure.

Open `http://127.0.0.1:8013/operator/login` and follow the ordered
[local Operator-to-MPIPS walkthrough](docs/mvp/local-core-walkthrough.md).

### Focused verification

Automated tests use fakes and must not call AWS or MPIPS. Run them only with
synthetic data and disposable test databases:

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

The manual rehearsal and its redacted results are recorded in
`docs/mvp/evidence/mvp-local-mpips-operator-rehearsal.md`. No NPZ path or
filename, patient data, private-object identifier, secret, or DICOM bytes may
appear in that report.

WP-01 provides the application foundation, module boundaries, local contracts,
transactional events, and idempotency infrastructure. Member, Operator,
Doctor, Image Gateway business workflows, user-facing surfaces, and external
adapters are implemented by later work packages.
