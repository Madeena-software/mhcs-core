---
title: Production Private Document Storage Verification
document_id: MHCS-TASK-PRODUCTION-PRIVATE-DOCUMENT-VERIFICATION-001
version: 1.1
status: validated-published
language: en-US
last_updated: 2026-08-26
scope:
  - bounded read-only production private-document verification
  - manual GitHub Actions workflow
  - static workflow safety coverage
authority_note: This task authorizes only the repository changes and the separately bounded future read-only diagnostic described below. It authorizes no production workflow dispatch, upload, mutation, deployment, or unrelated change.
---

# Executable Task

## Task identity

**Task title:**
`Production Private Document Storage Verification`

**Task path:**
`.agents/tasks/production-private-document-storage-verification.md`

**Task contract state:**
`Validated/Published`

**Delivery objective / Work Package / MVP:**
`Parallel independent read-only production verification of existing private consent and questionnaire persistence`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

Before any new informed-consent or paper-questionnaire upload, verify whether
one latest existing record of each type demonstrates the complete persistence
chain: database record, member linkage, booking linkage, private object key,
configured production S3/MinIO object, and companion metadata object.

This task is independent from the host-MinIO bind-scope diagnostic. It does
not authorize production execution during the implementation turn.

## Baseline and task revision

**Repository implementation baseline:**
`8fdaf326d2edffbc4549fb207980012ee428148b`

**Running production application revision:**
`b6232a158b3f6884fd9823bc875abc432676b781`

The repository baseline and running production revision are deliberately
different references. The implementation must start from the repository
baseline and the future diagnostic must require the running revision exactly.

**Task revision:**
`The full SHA of the commit containing this exact task content, supplied after publication.`

The task commit is published alone. The Executor must not begin workflow or
test implementation until the task commit has been pushed and verified as
`origin/main`, with its full immutable SHA recorded in the handoff.

## Diagnostic execution invariant

Diagnostic infrastructure, Laravel bootstrap, or required database-query
failure MUST NOT be classified as a member, booking, schedule, linkage, or
storage defect. The diagnostic MUST fail closed when it cannot obtain valid
read-only evidence; `db_linkage_incomplete` is reserved for linkage queries
that successfully prove a required relationship false.

## Objective

Add one separate manual GitHub Actions workflow and focused static coverage
that performs a bounded, sanitized, read-only verification of the latest
existing informed-consent and paper-questionnaire persistence chains.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — repository delivery,
  evidence, task, privacy, and side-effect boundaries.
- `.agents/tasks/_template.md` — executable task contract structure.
- `.agents/context/project.md` and `.agents/context/modules/member/project.md` —
  Member tables and private-object boundaries.
- `app/Modules/Member/Application/Services/Mvp04PaperConsentService.php` and
  `app/Modules/Member/Application/Services/Mvp04PaperQuestionnaireService.php` —
  persisted fields and synchronous `PrivateObjectStore::put()` path.
- `app/Shared/Storage/OpaqueObjectKey.php` and
  `app/Shared/Storage/PlainLocalObjectStore.php` — object-key validation and
  `<object-key>.meta.json` persistence model.
- Existing `.github/workflows/diagnose-production-s3.yml` and
  `tests/Deployment/ProductionS3DiagnosticWorkflowTest.php` — accepted
  production revision guard and Laravel bootstrap patterns.
- CTO/user authorization in the Production Private Document Storage
  Verification request.

### Requirement traceability

- `PRIVATE-DOC-001` → verify at most one latest existing record per document
  type and preserve a latest-row-without-key finding.
- `PRIVATE-DOC-002` → verify the documented database/member/booking/schedule
  relationships and sanitized DB metadata booleans.
- `PRIVATE-DOC-003` → prove the exact running production revision before any
  database query or S3 request.
- `PRIVATE-DOC-004` → use Laravel's configured private S3 disk and actual
  adapter/client; perform only `HeadBucket` and `HeadObject` operations.
- `PRIVATE-DOC-005` → verify main object and companion metadata existence,
  compare `ContentLength`, and make no checksum-content claim.
- `PRIVATE-DOC-006` → emit only approved booleans, enumerations, sanitized
  error families/statuses, and bounded interpretations.
- `PRIVATE-DOC-007` → statically enforce manual-only, read-only, no-disclosure
  workflow safety without a new dependency.

## Scope

### In scope

- `.agents/tasks/production-private-document-storage-verification.md`,
  published alone before implementation.
- `.github/workflows/verify-production-private-documents.yml`, a new separate
  manual-only workflow on the existing `self-hosted` runner with
  `permissions: contents: read`.
- `tests/Deployment/ProductionPrivateDocumentVerificationWorkflowTest.php`, a
  focused static test for workflow structure, ordering, queries, S3 calls,
  privacy, and read-only safety.
- Exact revision proof for a running `mhcs_core_app`: running app container,
  `/var/www/html/VERSION-CURRENT`, service image revision, and running
  container image revision must all equal the running production revision
  above. A missing or mismatched proof emits `revision_match=false` and stops
  before any database query, `HeadBucket`, `HeadObject`, or other production
  inspection.
- Laravel bootstrap inside the running app container in this exact order:
  `require 'vendor/autoload.php';`, `$app = require 'bootstrap/app.php';`, then
  `$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();`.
- Selection of at most one record for each type, without filtering on object
  key before selection, ordered deterministically by `created_at DESC, id DESC`:
  `examination_consents` and `member_paper_questionnaires`.
- Consent checks for `member_id`, `booking_id`, `signer_member_id`, object key,
  checksum, bytes, and format; questionnaire checks for `member_id`,
  `booking_id`, `member_schedule_id`, object key, checksum, bytes, and format.
- Only expected read tables: `examination_consents`,
  `member_paper_questionnaires`, `members`, `bookings`, and
  `shift_schedules`.
- `OpaqueObjectKey::fromString()` validation semantics, represented in the
  embedded diagnostic without inventing another key format.
- Consent formats `image/jpeg`, `image/png`, `application/pdf`; questionnaire
  formats `image/jpeg`, `image/png`.
- Member and booking existence/linkage checks, consent signer/member match,
  and questionnaire schedule existence/booking-schedule match.
- `config('mhcs.private_object_disk')`, `config('filesystems.disks.s3')`, and
  the configured Laravel S3 adapter/client. Configuration values remain in
  memory and are never reconstructed, changed, or printed.
- One sanitized `HeadBucket` first. If it fails, skip all object heads. For a
  selected record with a present and valid key, perform only
  `HeadObject(object_key)` and `HeadObject(object_key.'.meta.json')`.
- Sanitized error families `none`, `authorization`, `not_found`, `transport`,
  `unsupported`, and `unknown`, with either `none` or a three-digit HTTP
  status. No raw exception text or headers.
- `ContentLength` versus the valid DB byte field, producing `true`, `false`, or
  `unknown`. No `GetObject`, ETag-as-SHA-256 claim, metadata-body read, or
  checksum-content claim.
- Required chain booleans, one bounded classification per document type, and
  the exact approved output field names from the request.
- Repository-only commit and push of the task, then repository-only commit and
  push of the implementation to `main`, as explicitly authorized here.

### Out of scope

- Modifying `.github/workflows/diagnose-production-s3.yml`.
- Dispatching this or any production workflow during the implementation turn;
  future production execution requires separate Planner/Reviewer approval.
- Any INSERT, UPDATE, DELETE, TRUNCATE, migration, seeder, application upload,
  object creation, object read, object deletion, list, copy, ACL/policy/
  ownership/IAM mutation, endpoint change, secret change, deployment, restart,
  SSH, MPIPS, NPZ, DICOM, firewall, Docker-network, or service mutation.
- Any population-wide audit, more than one selected record per document type,
  historical completeness claim, document-content claim, signature/answer
  validation, or checksum comparison against object contents.
- Printing any record ID, member/booking/schedule/site/operator identifier,
  name, NIK, MRN, KK, phone, email, timestamp, filename, object key, bucket,
  endpoint, access key, secret, region, environment variable, checksum, byte
  count, S3 header/request ID, or document body.
- New dependencies, application-code changes, storage-model changes, or
  changes to unrelated workflows/tests.

### Preserved behavior

- Existing production workflows and application storage behavior remain
  untouched.
- The diagnostic is fail-closed at the exact revision boundary and remains
  strictly read-only.
- Laravel bootstrap and required database-read failures are explicit
  diagnostic execution failures and never production-data findings.
- Opaque private keys and companion `.meta.json` objects remain private; this
  diagnostic checks existence only.
- A missing latest DB object key is reported as incomplete metadata rather than
  being hidden by selecting an older row.
- A missing record is `no_existing_record`, not an S3 failure.

## Dependencies and assumptions

### Dependencies

- Clean repository baseline `8fdaf326d2edffbc4549fb207980012ee428148b`.
- Existing self-hosted production runner, Docker Swarm app service, running
  `mhcs_core_app`, Laravel bootstrap, configured S3 adapter, and AWS SDK.
- The task publication commit must be pushed and fetched before implementation.
- Separate Planner/Reviewer approval is required before any future production
  workflow dispatch.

### Approved assumptions

- The existing production revision proof in
  `.github/workflows/diagnose-production-s3.yml` is the reusable pattern for
  locating the app container and comparing service/container image revisions.
- `PrivateObjectStore::put()` establishes the synchronous object plus
  companion-metadata persistence model; no alternative storage model is valid.
- Laravel query-builder reads and the configured S3 client's `headBucket` and
  `headObject` operations can be used without exposing values.

### Remaining approval requirements

- Planner/Reviewer approval before dispatching the production workflow or
  performing any production database/S3 inspection.
- No other approval is required for the explicitly authorized repository task,
  workflow, test, commits, and pushes.

## Required capabilities

- Repository read/write and Git inspection.
- Codebase Memory MCP or equivalent repository-intelligence inspection.
- Focused PHP test execution, PHP syntax checking, YAML/Bash checking, Pint,
  and `git diff --check`.
- User-authorized commit and push to `main`.

## Execution constraints

- Workflow trigger is exactly `workflow_dispatch`; runner is `self-hosted`;
  permissions are read-only; no automatic trigger; shell uses
  `set -euo pipefail` and never `set -x`.
- The revision guard must precede every production DB/S3 request and must stop
  with only `revision_match=false` on failure.
- The diagnostic must use the running app's configured S3 client and never
  hard-code or print endpoint, bucket, credentials, region, or environment.
- Only `HeadBucket` and `HeadObject` are allowed S3 operations. Metadata is
  existence-only. The diagnostic must never claim SHA-256 content equality.
- Database access is SELECT-only and limited to the five expected tables.
- Identifiers and sensitive values may exist in memory only; approved sanitized
  output fields are the sole output boundary.
- Do not introduce a dependency or modify the existing S3 topology diagnostic.

## Acceptance criteria

- [ ] The task exists at the stable path, is `Validated/Published`, distinguishes repository baseline `8fdaf326...` from production revision `b6232a...`, and is committed/pushed alone before implementation.
- [ ] The separate workflow is manual-only, self-hosted, read-only, uses strict shell settings, and contains no automatic trigger or xtrace.
- [ ] The exact four-part production revision proof occurs before any DB/S3 work; mismatch emits only `revision_match=false` and stops.
- [ ] Laravel bootstrap order is Composer autoload, `bootstrap/app.php`, then Console Kernel bootstrap.
- [ ] Bootstrap and required database-read failures emit explicit diagnostic execution failure status, stop before later checks, and never emit `db_linkage_incomplete` as an observed data finding.
- [ ] At most one latest row per document type is selected with `created_at DESC, id DESC`, without a non-null-key filter.
- [ ] Consent and questionnaire DB linkage, key-shape, checksum-shape, bytes, and format checks are present with sanitized outputs only.
- [ ] Only the five expected DB tables are queried, with no DB write operation.
- [ ] The configured private S3 disk and actual configured adapter/client are used; no credentials or configuration values are printed.
- [ ] `HeadBucket` occurs once before conditional `HeadObject` calls; each eligible selected object checks both its key and `.meta.json`, with no other S3 operation.
- [ ] S3 error families/statuses, `ContentLength` comparisons, chain booleans, complete-evidence booleans, and approved bounded classifications are emitted.
- [ ] The workflow performs no `GetObject`, `PutObject`, `DeleteObject`, `ListObjects`, upload, deployment, restart, network/firewall change, secret change, or production dispatch.
- [ ] The focused static test proves all requested ordering, limits, table/operation allowlists, privacy prohibitions, and approved outputs without a new dependency.
- [ ] Existing production workflow/test behavior remains unchanged.

## Verification requirements

### Required checks

- `php artisan test tests/Deployment/ProductionPrivateDocumentVerificationWorkflowTest.php --no-coverage`
- `php artisan test tests/Deployment/ProductionVerificationWorkflowTest.php --no-coverage`
- `php artisan test tests/Deployment/ProductionS3DiagnosticWorkflowTest.php --no-coverage`
- `vendor/bin/pint --test`
- `git diff --check`
- YAML syntax, Bash syntax, and embedded PHP syntax checks.
- Final diff/status inspection and confirmation that no production workflow was
  dispatched and no production DB/S3 access was performed in this turn.

### Required evidence

The Executor must report the task commit SHA and parent, the implementation
commit SHA and parent, the fetched `origin/main` SHA after each push, changed
files, all commands and observed results, the exact output-presence booleans
listed in the request, and any verification gap. Local checks must not be
represented as CI or production evidence.

## Stop conditions

Stop and return to Planner/Reviewer if:

- the baseline, clean worktree, task publication SHA, or governing task revision
  cannot be established;
- a required authority, approval, dependency, or repository pattern is missing
  or contradictory;
- implementation would require changing the existing S3 diagnostic, adding a
  dependency, changing application/storage behavior, or expanding scope;
- the workflow cannot guarantee revision-before-DB/S3 ordering or sanitized
  output;
- a production dispatch, production DB/S3 access, secret/config mutation, or
  other prohibited side effect would be required;
- a requested verification command, commit, push, or remote SHA cannot be
  actually observed.

## Side-effect authorization

### Explicitly authorized side effects

- Create and publish this task alone with commit message
  `docs: publish private document storage verification task`.
- Fetch `origin/main` after that push and establish the exact task revision.
- Create the separate workflow and focused static test within this task.
- Commit the implementation with message
  `ci: add private document storage verification` and push `main`.
- Run repository-local tests, syntax/lint checks, and diff inspection.

The task does not authorize dispatching any production workflow or performing
any production database/S3 inspection.

## Expected terminal outcome

### Review Required

The Executor returns the immutable implementation revision and observed
verification evidence to Planner/Reviewer. Final acceptance and any future
production dispatch remain separate decisions.
