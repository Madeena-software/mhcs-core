# MHCS Core

MHCS Core is one Laravel modular monolith containing the Member, Operator,
Doctor, and Image Gateway modules. The Shared boundary contains only genuine
cross-cutting primitives and infrastructure; business rules remain in their
owning module.

## Stack

- PHP `^8.4`
- Laravel `^13.8`
- Filament `^5.0` (installed; product panels are added by later work packages)

## Local synthetic rehearsal

The supported rehearsal path is a native local Laravel process using the
repository-owned synthetic seeders and fixtures. It does not use Docker,
Compose, a reverse proxy, a queue worker, MPIPS, or a deployment environment.
The production-specialized material under [`deployment/`](deployment/README.md)
is not the supported path for this rehearsal.

### First-time setup

Install the existing PHP and frontend dependencies, then create a local
environment file:

```bash
composer install
cp .env.example .env
npm install
npm run build
```

Before running Artisan, configure `.env` from local-only secret-managed
values. This guide intentionally does not show, generate, copy, log, or commit
any value. The required application key names are:

- `APP_KEY`
- `MHCS_IDENTIFIER_KEY`
- `MHCS_OBJECT_ENCRYPTION_KEY`
- `MHCS_ACCESS_GRANT_KEY`
- `MHCS_MANIFEST_KEY`
- `MHCS_MANIFEST_KEY_ID`

Also configure `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, and `DB_PASSWORD` for an explicitly disposable local database.
Do not point these settings at staging, production, or a shared server.

### Fresh rehearsal

`migrate:fresh` is destructive: it drops every table in the selected database
before recreating the schema. Confirm the configured `DB_*` target is an empty,
disposable local database before running these exact commands:

```bash
php artisan migrate:fresh --force
php artisan db:seed --class=Database\\Seeders\\MvpCoreClinicSeeder
```

`MvpCoreClinicSeeder` is local/testing-only and repeatable. Its interactive
terminal output identifies the synthetic Member booking, primary Operator,
second same-site/current-shift Operator, attendance URL, and LCD URL. It emits
one-time synthetic credentials only in that terminal; keep them there and do
not copy them into documentation, chat, logs, or deployment configuration.

For this POC, the displayed synthetic schedule begins on 13 August 2026 and
ends on 22 August 2026. Local/testing attendance keeps the end boundary but
allows the flow to begin before the displayed start date.

The rehearsal uses the repository-owned pair below and no clinical file:

- `resources/fixtures/image-gateway/synthetic-radiograph-01.npz`
- `resources/fixtures/image-gateway/synthetic-gain-01.npz`

Use a locally chosen synthetic JPEG or PNG—not a real consent form or clinical
image—when the paper-questionnaire step asks for a file.

Start the native Laravel server on the documented local endpoint:

```bash
php artisan serve --host=127.0.0.1 --port=8013
```

Open `http://127.0.0.1:8013/operator/login`, then follow the ordered
[local clinic-core walkthrough](docs/mvp/local-core-walkthrough.md). It covers
primary-Operator login and site selection, arrival through X-ray capture,
read-only DICOM viewing and download, then second-Operator results discovery
and download.

### Focused verification

Run these checks with synthetic data and disposable databases only:

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

TARGET="." vendor/bin/pest \
  tests/Browser/Mvp14OperatorDicomRehearsalTest.php --browser chrome

TARGET="." npm run build
TARGET="." git diff --check
```

The DICOM browser file contains both the primary journey and the second
Operator results-worklist journey. Stop after the synthetic download. This
guide does not authorize MPIPS, real conversion, server data, deployment,
release, or secret disclosure.

WP-01 provides the application foundation, module boundaries, local contracts,
transactional events, and idempotency infrastructure. Member, Operator,
Doctor, Image Gateway business workflows, user-facing surfaces, and external
adapters are implemented by later work packages.
