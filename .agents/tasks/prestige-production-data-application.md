---
title: Controlled Prestige Production Data Application
document_id: MHCS-TASK-PRESTIGE-PRODUCTION-DATA-APPLICATION-001
version: 1.0
status: validated-on-publication
language: en-US
last_updated: 2026-08-19
scope:
  - dedicated manual production workflow for the approved Prestige fixture
  - production revision, backup, private-source, and post-seed safety gates
  - sanitized read-only verification of the Prestige production dataset
authority_note: This task authorizes implementation and publication of a dedicated workflow only. It does not authorize dispatching that workflow, running PrestigeClinicSeeder against live production, deploying, or mutating the live database.
---

# Executable Task

## Task identity

**Task title:** `Controlled Prestige Production Data Application`

**Task path:** `.agents/tasks/prestige-production-data-application.md`

**Task contract state:** `Validated/Published upon immutable publication of this exact content`

**Delivery objective / Work Package / MVP:** `Controlled production data application for the accepted Prestige rehearsal fixture`

**Owner / designated planning authority:** `Faliq Adlan, CTO`

## Delivery context

The accepted Prestige rehearsal implementation is complete at the immutable
baseline `4488f37787bc521869a2bb6113507387c5a983c8`. Production application of
that approved dataset was intentionally outside the previous readiness task.
This new task creates one narrowly dedicated, manually triggered GitHub Actions
workflow for applying the already-approved Prestige fixture to the live MHCS
environment with fail-closed production, backup, private-source, and
post-seed verification controls.

The workflow is an operational data-application mechanism, not a general
seeder runner, deployment replacement, or production release authorization.

## Baseline and task revision

**Implementation baseline:** `4488f37787bc521869a2bb6113507387c5a983c8`

**Task revision:** `The full SHA of the commit containing this exact validated task content, supplied by the Planner after publication.`

The expected production application revision for the first execution is the
same exact immutable baseline above. A later production revision MUST NOT be
accepted by this workflow merely because it is newer; changing the expected
revision is a separate planning/review decision.

## Objective

Create `.github/workflows/apply-prestige-production-data.yml`, a dedicated
manual-only workflow that can safely apply `Database\Seeders\PrestigeClinicSeeder`
to the live MHCS environment only after explicit confirmation, exact production
revision verification, a verified database backup, and secure runtime delivery
of the private employee CSV. After the seed, perform sanitized read-only checks
of the complete expected Prestige state and fail if any invariant is absent.

## Authoritative inputs

### Governing authority

- Human-authority production-data application contract supplied for this task:
  dedicated workflow, manual confirmation, exact-version gate, serialized
  production operations, backup-before-seed, private CSV handling, exact
  seeder restriction, post-seed verification, idempotency, and explicit
  prohibition on production execution until separate approval.
- `.agents/AGENTS.md` and `.agents/software-workflow.md` — task readiness,
  evidence, side-effect, acceptance, and separate release-gate requirements.
- `.agents/tasks/prestige-rehearsal-schedule-and-radiography-capture-readiness.md`
  at its accepted lineage — approved Prestige dataset semantics and preserved
  Member/Operator invariants.
- `.agents/context/project.md` and the Member/Operator module context — data
  ownership, deployment topology, and module boundaries.

### Observed implementation and operational inputs

These repository files constrain reuse and feasibility; they do not replace
the human authority above:

- `.github/workflows/deploy-swarm.yml` — self-hosted runner, Docker Swarm
  service names, `VERSION-CURRENT`, immutable image tags, production
  concurrency group, environment secrets, and application readiness checks.
- `.github/workflows/server-setup-db.yml` — established
  `/etc/madeena-mhcs_core-db-backup.sh` backup mechanism and its verified S3
  backup convention.
- `docker-compose.prod.yml` — production app/database services, version-file
  mount, environment-file boundary, and Swarm paths.
- `database/seeders/PrestigeClinicSeeder.php` and
  `tests/Feature/Operator/PrestigeClinicSeederTest.php` — fixed seeder guard,
  private-path override, validation, schedule semantics, idempotency, and
  synthetic regression evidence.

### Requirement traceability

- `Production workflow trigger and confirmation` → human-authority contract,
  TRIGGER and PRODUCTION SAFETY sections.
- `Production revision and serialization gates` → human-authority contract,
  PRODUCTION SAFETY and existing `deploy-swarm.yml` conventions.
- `Backup and private-source handling` → human-authority contract,
  DATABASE BACKUP and PRIVATE PRESTIGE CSV sections, plus the established
  `server-setup-db.yml` mechanism.
- `Exact seed and final-state invariants` → human-authority contract,
  PRODUCTION SEED AUTHORIZATION, APPLICATION CODE, and POST-SEED VERIFICATION
  sections, plus the accepted Prestige task.

## Scope

### In scope

- Add the single workflow `.github/workflows/apply-prestige-production-data.yml`.
- Use `workflow_dispatch` only with one required confirmation input whose exact
  accepted value is `APPLY-PRESTIGE-2026-08-27-28`. Invalid or missing input
  MUST fail before the production mutation job starts.
- Use the existing production serialization boundary exactly:
  `concurrency.group: production-deployment-mhcs_core` with
  `cancel-in-progress: false`. The workflow MUST NOT run concurrently with
  `deploy-swarm.yml`.
- Before backup or seed, verify that the current healthy production
  `mhcs_core_app` runtime is the exact expected revision
  `4488f37787bc521869a2bb6113507387c5a983c8`. Reuse the existing deployment
  paths and evidence, including the current desired app container, its
  immutable service image tag, and the mounted `/var/www/html/VERSION-CURRENT`
  value, or an equivalent established runtime version proof. Missing,
  unknown, or mismatched version evidence MUST fail closed.
- Before the seeder, invoke the established production database backup
  mechanism installed at `/etc/madeena-mhcs_core-db-backup.sh`. Require its
  successful exit and its existing non-empty/integrity/S3 verification before
  continuing. Report only a sanitized backup success/reference. Do not add an
  automatic restore path unless existing repository policy later requires one.
- Securely provide the real private CSV at runtime using one approved
  self-hosted-runner mechanism: an existing protected GitHub Environment secret
  or an existing protected runner-host file. The implementation MUST choose
  one mechanism based on actual runner policy and document only the mechanism,
  not the file contents. If the required protected source is unavailable, it
  MUST fail before seeding.
- Materialize any runtime copy outside the repository workspace with
  restrictive permissions (`umask 077`, mode no broader than `0600`), pass its
  container path through `PRESTIGE_EMPLOYEE_CSV`, and remove host/container
  temporary copies with an exit trap where practical. CSV content MUST never be
  echoed, logged, uploaded as an artifact, or committed.
- Run exactly the existing seeder and no user-selected class:

  ```bash
  docker exec \
    -e MHCS_ALLOW_PRODUCTION_MVP_SEED=true \
    -e PRESTIGE_EMPLOYEE_CSV=/tmp/<private-runtime-csv> \
    "$APP_CONTAINER" \
    php artisan db:seed --class='Database\Seeders\PrestigeClinicSeeder' --force
  ```

  The production authorization flag MUST exist only for this execution
  process and MUST NOT be persisted in `.env`, Swarm configuration, or GitHub
  repository variables. The workflow MUST NOT accept a seeder class, CSV path,
  or arbitrary production command as user input.
- After successful seeding, run read-only verification inside the current app
  container. Verify the active Prestige site, exactly the two intended target
  schedules, exact UTC bounds, quota 37 on each, 37 confirmed bookings on each,
  74 total bookings, 37 distinct Members, and equality of the two Member ID
  sets. Emit only sanitized booleans/counts and schedule values; never emit
  Member IDs, names, NIKs, addresses, birth dates, credentials, or query rows.
- Preserve the existing seeder's idempotent behavior and fail-closed obsolete
  schedule/downstream-data protection. No production second-run test or live
  dispatch is authorized by this task; local synthetic seeder regression is
  the required evidence for idempotency.
- Add focused static workflow validation/regression coverage if existing
  deployment tests do not already cover the new workflow contract. Prefer a
  workflow-only implementation and do not change application code or the
  existing seeder for this task.

### Out of scope

- Any `push`, `pull_request`, `schedule`, or cron trigger for the new workflow.
- A generic arbitrary-seeder workflow, arbitrary command runner, user-supplied
  seeder class, user-supplied production revision, or user-supplied database
  target.
- Changes to `PrestigeClinicSeeder`, application source, migrations, database
  schema, deployment behavior, `deploy-swarm.yml`, Docker topology, or the
  accepted Prestige dataset semantics.
- Provisioning or rotating GitHub secrets, environment approvals, runner-host
  files, Docker credentials, or cloud credentials. The workflow may reference
  an already approved protected mechanism; missing provisioning is a stop
  condition.
- Actually dispatching this workflow, running the seeder against live
  production, mutating the live database, deploying, releasing, or implicitly
  triggering the general production deployment workflow.
- Reading, printing, copying into the repository, staging, committing, or
  exposing the private CSV contents or any employee PII. Local tests MUST use
  generated synthetic CSV data only.
- Automatic restore, destructive cleanup, broad schedule deletion, unrelated
  research-data handling, external clinical-service calls, or new production
  infrastructure.

### Preserved behavior

- `PrestigeClinicSeeder` production authorization, CSV validation, exact-37
  requirement, uniqueness checks, schedule reconciliation safety, and
  idempotency remain unchanged.
- The two approved `Asia/Jakarta` half-open schedule intervals remain:
  `27 Aug [2026-08-27T00:00:00+07:00, 2026-08-28T00:00:00+07:00)` and
  `28 Aug [2026-08-28T00:00:00+07:00, 2026-08-29T00:00:00+07:00)`.
- Their expected stored UTC boundaries remain:
  `2026-08-26 17:00:00` → `2026-08-27 17:00:00` and
  `2026-08-27 17:00:00` → `2026-08-28 17:00:00`.
- The final dataset remains quota 37 per schedule, 37 unique Members, 37
  confirmed bookings per date, the same 37 Members on both dates, and 74
  total bookings.
- The ordinary Member one-active-booking runtime invariant remains unchanged;
  the two-date duplicate assignment remains limited to the approved Prestige
  fixture.
- The real CSV remains ignored and untracked, and workflow logs remain free of
  employee PII and credentials.
- Acceptance of the workflow implementation remains separate from release and
  from authorization to execute it against production.

## Dependencies and assumptions

### Dependencies

- A clean checkout at implementation baseline
  `4488f37787bc521869a2bb6113507387c5a983c8` and the exact published task
  revision.
- The existing self-hosted production runner has Docker access and is a Swarm
  manager for `mhcs_core`; the app and database services are reachable through
  the established deployment paths.
- `/etc/madeena-mhcs_core-db-backup.sh` is installed and configured by the
  existing database-setup workflow. It remains the backup authority for this
  operation.
- A designated operator provisions one approved protected CSV transport and
  any required GitHub Environment protection before production execution.
- The production runtime exposes the current application revision through the
  existing immutable image tag and `VERSION-CURRENT` mount.

### Approved assumptions

- `4488f37787bc521869a2bb6113507387c5a983c8` is the exact expected reviewed
  production application revision for the first workflow execution. A
  mismatch is a safety failure, not permission to select another revision.
- The installed backup script's successful return is the repository-approved
  backup completion signal because it validates the compressed dump and uploads
  it through the established S3/MinIO mechanism.
- A protected runner-host file or protected Environment secret can be made
  available without placing the CSV in the repository workspace. Choosing
  between those two mechanisms is bounded implementation detail; inventing a
  new secret system or infrastructure boundary is not authorized.
- The existing seeder and its synthetic regression tests remain the source of
  truth for fixture idempotency and application-level data validation.

### Remaining approval requirements

- Planner/Reviewer must review and accept the workflow implementation before
  production execution is considered.
- A designated production/release authority must separately approve the exact
  workflow implementation revision and the actual `workflow_dispatch` run.
- Protected CSV transport provisioning and any GitHub Environment approval
  remain operational prerequisites; the Executor must stop if they require new
  authority or permission.

## Required capabilities

- Repository read/write and Git history/diff inspection.
- Local YAML/workflow static validation and existing PHP test execution.
- Existing self-hosted-runner/Docker/Swarm conventions may be inspected, but
  no production credentials, secret values, private CSV contents, workflow
  dispatch, or live database access are required or authorized for local
  implementation and verification.

## Execution constraints

- Reuse the existing `deploy-swarm.yml` self-hosted runner, service names,
  version evidence, environment boundary, backup convention, and concurrency
  group. Do not create an unrelated deployment mechanism or use direct SSH.
- Validate confirmation and exact production version before the backup/seed
  path. A bad confirmation or version mismatch MUST prevent all database
  commands, including any seed and post-seed mutation path.
- Run the verified backup before the seeder and fail closed on missing,
  failed, empty, unverifiable, or unreferenced backup output.
- Keep shell tracing disabled. Never print secrets, CSV contents, command
  environments, employee fields, database rows, or verbose failure dumps that
  could contain PII. Log only target revision, sanitized backup status/reference,
  CSV validation row count, seed status, schedule timestamps/counts, booking
  counts, distinct-member count, set-equality status, and final pass/fail.
- Use restrictive temporary permissions and cleanup traps for all CSV copies.
  Do not place the source in `$GITHUB_WORKSPACE` as a tracked or persistent
  fixture.
- Use the exact `PrestigeClinicSeeder` class and Laravel production force
  option. Do not weaken its production guard, alter `.env`, or run migrations,
  arbitrary Artisan commands, or another seeder as part of the data operation.
- Keep post-seed checks read-only and compare Member sets in memory without
  printing identities. Fail if any expected schedule, quota, count, or set
  invariant is not observed.
- Do not add a dependency merely to parse or run the workflow. Use existing
  repository tooling or an already-installed validator; if a required validator
  is unavailable, stop or use a focused repository-native static test rather
  than installing one without approval.

## Acceptance criteria

- [ ] `.github/workflows/apply-prestige-production-data.yml` exists and has
  only `workflow_dispatch`; it has no push, pull-request, schedule, or cron
  trigger and is not a generic seeder/command workflow.
- [ ] The workflow requires the exact confirmation phrase
  `APPLY-PRESTIGE-2026-08-27-28`; invalid or missing confirmation fails before
  any production mutation job or database command can run.
- [ ] The workflow uses concurrency group
  `production-deployment-mhcs_core` with `cancel-in-progress: false`, so it
  cannot intentionally overlap `deploy-swarm.yml`.
- [ ] Before backup or seed, the workflow proves that the healthy production
  app is exactly revision `4488f37787bc521869a2bb6113507387c5a983c8` using
  established runtime version evidence and fails closed on missing, unknown,
  or mismatched evidence.
- [ ] The established production backup mechanism runs before the seeder and
  the workflow cannot continue after backup failure or failed verification.
  No automatic restore is added.
- [ ] The CSV is supplied only through an approved protected runtime mechanism,
  is never placed in tracked workspace files, is passed through
  `PRESTIGE_EMPLOYEE_CSV`, is protected by restrictive permissions, and is
  removed from temporary host/container locations after success or failure
  where practical.
- [ ] Workflow output contains no employee PII, CSV contents, credentials, or
  Member identity data.
- [ ] The only production data command is the exact
  `Database\Seeders\PrestigeClinicSeeder` with
  `MHCS_ALLOW_PRODUCTION_MVP_SEED=true` scoped to that process and Laravel's
  required `--force` option. No user-supplied class or arbitrary command is
  accepted.
- [ ] Read-only post-seed verification fails the workflow unless the active
  Prestige site exists, exactly the two intended schedules exist, UTC bounds
  match, each quota is 37, each schedule has 37 confirmed bookings, total
  bookings are 74, distinct Members are 37, and both schedules contain the
  same Member set.
- [ ] The accepted full-day local intervals, UTC storage convention, ordinary
  Member one-active-booking invariant, seeder guard/validation/idempotency,
  and obsolete-schedule downstream-data protection remain unchanged.
- [ ] Focused workflow/static checks and the existing synthetic Prestige seeder
  regression pass; no live workflow dispatch, production seed, deployment, or
  live database mutation is performed as implementation evidence.

## Verification requirements

### Required checks before review

1. Validate the workflow syntax with an already-installed repository or
   environment validator; do not install a new dependency solely for this
   task.
2. Run the focused static workflow contract test added or updated for this
   task. It must assert the manual-only trigger, confirmation phrase,
   production concurrency group, exact expected revision gate, backup-before-
   seed ordering, exact seeder class, process-scoped authorization flag,
   private-path handling, cleanup, no arbitrary seeder input, and sanitized
   verification contract.
3. Run `vendor/bin/phpunit tests/Feature/Operator/PrestigeClinicSeederTest.php`
   and any focused deployment/workflow test changed by the implementation.
   Tests MUST use generated synthetic CSV data only.
4. Run `TARGET="." vendor/bin/phpunit`.
5. Run `TARGET="." npm run build` if the repository's required build gate is
   affected or required by current governance.
6. Run `TARGET="." vendor/bin/pint --test` and
   `TARGET="." git diff --check`.
7. Verify the real CSV remains ignored and absent from Git with
   `git check-ignore -v -- research/prestige/data-karyawan-cv-prestige.csv`,
   `git ls-files research`, and
   `git ls-files --stage -- research/prestige/data-karyawan-cv-prestige.csv`,
   without printing its contents.
8. Do not trigger the new workflow, run the seeder against production, deploy,
   or mutate any live database as part of task implementation or verification.

### Required evidence

The Executor MUST report the exact task revision, implementation baseline and
revision, changed files, static/workflow test output, all commands actually
run, observed results, any warnings or tests not run, and the Mvp14/browser
limitation if applicable. Production execution evidence is not expected and
must not be fabricated. Any workflow-run evidence later supplied must contain
only sanitized operational summaries and must identify the exact approved
workflow revision and production version gate result.

## Stop conditions

Stop and return to Planner/Reviewer if:

- the current checkout is dirty, the accepted baseline is not the declared
  immutable revision, or overlapping unreviewed deployment changes exist;
- the workflow cannot prove the exact expected production revision before the
  backup/seed path;
- the established backup script is unavailable, fails, or cannot verify the
  backup without inventing a new backup mechanism;
- no approved protected CSV transport is available without reading, exposing,
  committing, or newly provisioning secrets or infrastructure;
- the task would require changing application code, the seeder, deployment
  concurrency, database schema, or accepted Prestige semantics;
- workflow logs could expose employee PII, credentials, CSV contents, or
  Member identity data;
- the post-seed invariant query cannot prove the exact two schedules/counts/
  same-member-set without printing identities;
- a production trigger, live database mutation, deployment, release, or other
  external side effect would be required; or
- implementation reveals a new authority, architecture, security, privacy,
  data-integrity, or operational decision not covered by this task.

The Executor MUST NOT silently weaken a failed safety gate or reinterpret this
task as permission to run production operations.

## Side-effect authorization

### Explicitly authorized

- Publish this task and its immutable Git revision.
- Implement only the dedicated workflow and focused static/regression tests
  required by this task.
- Run local syntax, static, test, build, format, diff, and privacy checks using
  synthetic data only.
- Commit and push the bounded workflow implementation after local verification
  and review handoff, if repository governance and the governing task permit it.

### Explicitly not authorized

- Dispatching `apply-prestige-production-data.yml` against production.
- Running `PrestigeClinicSeeder` against the live production database.
- Creating, changing, or deleting live production data, schemas, services,
  deployments, backups, secrets, environment approvals, or infrastructure.
- Reading or printing the private employee CSV contents or any employee PII.
- Implicitly triggering `deploy-swarm.yml` or treating implementation acceptance
  as release authorization.

## Expected terminal outcome

### Review Required

Return one reviewable workflow implementation revision with truthful local
verification evidence. Planner/Reviewer must separately accept the workflow
before any production execution decision.

### Planning Required

Use when a secure runtime-source, exact-version, backup, authorization,
architecture, or operational dependency cannot be satisfied without new
authority. Do not publish a nominal implementation or bypass the failed gate.

Acceptance of this workflow task is not production execution or release
authorization.
