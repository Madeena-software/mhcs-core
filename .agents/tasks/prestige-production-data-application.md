---
title: Controlled Prestige Production Data Application
document_id: MHCS-TASK-PRESTIGE-PRODUCTION-DATA-APPLICATION-001
version: 1.2
status: validated-on-publication
language: en-US
last_updated: 2026-08-20
scope:
  - dedicated manual production workflow for the approved Prestige fixture
  - production revision, backup, private-source, and post-seed safety gates
  - protected operator/admin credential handling for the approved production seed
  - sanitized read-only verification of the Prestige production dataset
authority_note: This task authorizes implementation and publication of a dedicated workflow and the bounded credential-safety remediation described in revision 1.1. Later production execution remains a separately approved operational action; this task republication and implementation do not themselves dispatch the workflow, deploy, seed, or mutate the live database.
---

# Executable Task

## Task identity

**Task title:** `Controlled Prestige Production Data Application`

**Task path:** `.agents/tasks/prestige-production-data-application.md`

**Task contract state:** `Validated/Published upon immutable publication of this exact content`

**Delivery objective / Work Package / MVP:** `Controlled production data application for the accepted Prestige rehearsal fixture`

**Owner / designated planning authority:** `Faliq Adlan, CTO`

## Delivery context

The accepted Prestige rehearsal must run at the immutable production
application revision `4488f37787bc521869a2bb6113507387c5a983c8`. Production
application of that approved dataset was intentionally outside the previous
readiness task. This task creates one narrowly dedicated, manually triggered
GitHub Actions workflow for applying the already-approved Prestige fixture to
the live MHCS environment with fail-closed production, backup, private-source,
and post-seed verification controls.

The workflow is an operational data-application mechanism, not a general
seeder runner, deployment replacement, or production release authorization.

Revision 1.1 adds an owner-approved credential-safety remediation. At the
expected production revision, `PrestigeClinicSeeder` has fallback values for
operator and administrator credentials when private credential material is
unavailable. The production workflow MUST fail closed before backup or
seeding unless the approved private credential inputs are present, structured,
and staged safely. The existing seeder and application code remain unchanged.

## Baseline and task revision

**Implementation baseline:** `ffbbaced4ebda7fcc01ab710c4ace5d055d917e5`

**Task revision:** `The full SHA of the commit containing this exact validated task content, supplied by the Planner after publication.`

The expected production application/runtime revision for the first execution
is the exact immutable revision
`4488f37787bc521869a2bb6113507387c5a983c8`. It is intentionally distinct
from the implementation baseline above. A later production revision MUST NOT
be accepted by this workflow merely because it is newer; changing the expected
runtime revision is a separate planning/review decision.

## Approved credential-safety remediation

This revision remains part of the same controlled Prestige production-data
objective. It adds only the bounded transport and fail-closed credential
controls required to prevent fallback production credentials during the
approved seed.

### A. Protected production Environment

The mutation job MUST declare `environment: production`. The existing GitHub
Environment is an approved protected mechanism restricted exactly to the
`main` branch. No new generic environment or secret infrastructure is
authorized.

### B. Employee CSV secret

The workflow MUST use only the fixed Environment secret
`PRESTIGE_EMPLOYEE_CSV`, exposed as `${{ secrets.PRESTIGE_EMPLOYEE_CSV }}`.
It MUST fail closed when the secret is unavailable or empty, stage it only in
a temporary `/tmp` runner file with mode `0600`, validate exactly 37 rows
without printing contents, copy it into the app container, establish
`www-data:www-data` ownership and mode `0600`, verify that ownership and mode,
and remove both temporary copies through EXIT cleanup. The secret MUST NOT be
persisted in the workspace, artifacts, outputs, summaries, caches, images, or
permanent runner paths.

### C. Operator credential secret

The workflow MUST use only the fixed Environment secret
`PRESTIGE_OPERATOR_CREDENTIALS`, exposed as
`${{ secrets.PRESTIGE_OPERATOR_CREDENTIALS }}`. Its approved private source is
the ignored local file `research/prestige/operator.txt`; its contents MUST
never be printed, logged, or committed.

The workflow MUST fail closed when this secret is unavailable or empty. It
MUST materialize the value only into a restrictive temporary runner file with
mode `0600`, validate enough structure to prove the seeder will not enter its
fallback-credential branch, and never print email addresses or password
values. The validation MUST require at least one valid email line and one
non-empty `password:` line, matching the existing unmodified seeder's parser.

For the seed only, the workflow MUST copy the temporary file into the current
app container at exactly:

`/var/www/html/research/prestige/operator.txt`

It MAY create the parent directories when needed. The file MUST be readable
by the normal application user, owned as `www-data:www-data`, and have mode
`0600`. Ownership and mode MUST be verified without printing contents.
The container copy and runner temporary copy MUST be removed by EXIT cleanup.
The task does not authorize changing `PrestigeClinicSeeder` or adding another
path abstraction.

### D. Production administrator credential precheck

Before backup or seeding, the workflow MUST prove inside the current app
container that `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` are both
present and non-empty. It MUST NOT print their values or provide fallback
administrator values. If either is absent, the workflow MUST fail before the
backup and before seeding.

### E. Temporary bootstrap credential output

For this one authorized Prestige operation, the workflow MUST pass the
process-scoped variable:

`MHCS_BOOTSTRAP_CREDENTIAL_PATH=/tmp/mhcs-prestige-bootstrap-credentials.txt`

only to the `PrestigeClinicSeeder` process. It MUST NOT persist this value in
`.env`, Swarm service configuration, repository variables, or GitHub
variables. Any resulting file MUST be mode `0600`, its contents MUST never be
printed or read by the workflow, and it MUST be removed on EXIT using
sufficient privilege.

### F. Fixed seeder execution

The existing application seeder remains unchanged and MUST execute as the
normal application user, never root. The only allowed production data
invocation remains the fixed class:

`Database\\Seeders\\PrestigeClinicSeeder`

with process-scoped:

- `MHCS_ALLOW_PRODUCTION_MVP_SEED=true`;
- `PRESTIGE_EMPLOYEE_CSV=/tmp/mhcs-prestige-employee.csv`;
- `MHCS_BOOTSTRAP_CREDENTIAL_PATH=/tmp/mhcs-prestige-bootstrap-credentials.txt`.

No user-selectable seeder class, path, or arbitrary command is permitted.

### G. Fail-closed ordering

The required order is:

```text
exact production revision/health
→ employee secret presence/staging/validation
→ Operator credential secret presence/staging/validation
→ production SUPER_ADMIN credential presence
→ secure container staging
→ verified production backup
→ fixed PrestigeClinicSeeder
→ sanitized verification
```

No database mutation may occur before the verified backup succeeds.

### H. Secret lifecycle

For a separately approved operational execution, the designated operator MAY
temporarily provision exactly `PRESTIGE_EMPLOYEE_CSV` and
`PRESTIGE_OPERATOR_CREDENTIALS` in the protected `production` Environment,
dispatch the accepted workflow, independently verify production, and delete
both secrets after the attempt whether the seed succeeds or fails. The
operator MUST verify that both secrets are absent afterward. Secret values
MUST never appear in console output, repository files, Git history, artifacts,
caches, job summaries, or workflow outputs.

This task revision does not authorize performing that provisioning or
dispatch during implementation, review, or task publication.

### I. Post-seed data contract

The existing sanitized verification remains mandatory:

```text
site_active=true
schedule_count=2
schedule_bounds_match=true
quota_27=37
quota_28=37
confirmed_27=37
confirmed_28=37
total_bookings=74
distinct_members=37
member_sets_equal=true
verification_passed=true
```

No Member IDs, employee PII, emails, or credentials may be emitted.

### J. Preserved scope boundary

Application source, `PrestigeClinicSeeder`, migrations, schema, deployment,
generic secret infrastructure, generic seeder/command runners, direct SQL,
manual production mutation, and unrelated credential rotation remain out of
scope.

## Objective

Create `.github/workflows/apply-prestige-production-data.yml`, a dedicated
manual-only workflow that can safely apply `Database\Seeders\PrestigeClinicSeeder`
to the live MHCS environment only after explicit confirmation, exact production
revision verification, a verified database backup, and secure runtime delivery
of the private employee CSV and operator credential material. Before backup,
the workflow must also prove that production administrator credentials are
present without supplying fallback values. After the seed, perform sanitized
read-only checks of the complete expected Prestige state and fail if any
invariant is absent.

## Authoritative inputs

### Governing authority

- Human-authority production-data application contract supplied for this task:
  dedicated workflow, manual confirmation, exact-version gate, serialized
  production operations, backup-before-seed, private CSV handling, exact
  seeder restriction, post-seed verification, idempotency, and explicit
  prohibition on production execution until separate approval.
- Owner-approved revision 1.1 credential-safety remediation: protected
  `production` Environment use, temporary fixed employee/operator secrets,
  fail-closed credential prechecks, disposable bootstrap-credential output,
  and deletion/absence verification after the later approved attempt.
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
- `database/seeders/MvpCredentialFile.php` — existing production bootstrap
  credential output path override and restrictive file behavior.
- `database/seeders/PrestigeClinicSeeder.php` and
  `tests/Feature/Operator/PrestigeClinicSeederTest.php` — fixed seeder guard,
  fixed `research/prestige/operator.txt` parser path, validation, schedule
  semantics, idempotency, and synthetic regression evidence.
- Ignored private sources `research/prestige/operator.txt` and
  `research/prestige/data-karyawan-cv-prestige.csv` — operational inputs only;
  their contents are never repository evidence.

### Requirement traceability

- `Production workflow trigger and confirmation` → human-authority contract,
  TRIGGER and PRODUCTION SAFETY sections.
- `Production revision and serialization gates` → human-authority contract,
  PRODUCTION SAFETY and existing `deploy-swarm.yml` conventions.
- `Backup and private-source handling` → human-authority contract,
  DATABASE BACKUP and PRIVATE PRESTIGE CSV sections, plus the established
  `server-setup-db.yml` mechanism.
- `Credential-safety remediation` → owner-approved revision 1.1 contract,
  protected Environment, operator/admin prechecks, and existing
  `MvpCredentialFile.php` path behavior.
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
- Securely provide the real private CSV at runtime using the fixed protected
  GitHub `production` Environment secret `PRESTIGE_EMPLOYEE_CSV`. The
  implementation MUST document only the mechanism, not the file contents. If
  the protected secret is unavailable, it MUST fail before seeding.
- Materialize any runtime copy outside the repository workspace with
  restrictive permissions (`umask 077`, mode no broader than `0600`), pass its
  container path through `PRESTIGE_EMPLOYEE_CSV`, and remove host/container
  temporary copies with an exit trap where practical. CSV content MUST never be
  echoed, logged, uploaded as an artifact, or committed.
- Securely provide the private operator credential material through the fixed
  protected Environment secret `PRESTIGE_OPERATOR_CREDENTIALS`, sourced from
  the ignored local `research/prestige/operator.txt` only during the later
  approved operational provisioning step. Validate at least one email line and
  one non-empty `password:` line without emitting either value. Stage it in a
  temporary mode-0600 runner file and, only for the seed, at
  `/var/www/html/research/prestige/operator.txt` inside the current app
  container, with normal-user-readable ownership and mode 0600. Remove both
  copies on EXIT.
- Before backup or seeding, prove `SUPER_ADMIN_EMAIL` and
  `SUPER_ADMIN_PASSWORD` are present and non-empty inside the current app
  container without printing values or supplying fallbacks.
- Pass `MHCS_BOOTSTRAP_CREDENTIAL_PATH=/tmp/mhcs-prestige-bootstrap-credentials.txt`
  only to the fixed Prestige seeder process. Verify any resulting file is
  mode 0600 without reading its contents and remove it with sufficient
  privilege on EXIT.
- Run exactly the existing seeder and no user-selected class:

  ```bash
  docker exec \
    -e MHCS_ALLOW_PRODUCTION_MVP_SEED=true \
    -e PRESTIGE_EMPLOYEE_CSV=/tmp/mhcs-prestige-employee.csv \
    -e MHCS_BOOTSTRAP_CREDENTIAL_PATH=/tmp/mhcs-prestige-bootstrap-credentials.txt \
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
- Generic secret infrastructure, generic secret rotation, runner-host CSV
  transport, Docker credentials, or cloud credentials. The later approved
  operational attempt may provision exactly the temporary
  `PRESTIGE_EMPLOYEE_CSV` and `PRESTIGE_OPERATOR_CREDENTIALS` secrets in the
  existing protected `production` Environment, then MUST delete and verify
  absence of both after the attempt. No other secret provisioning is in scope.
- Dispatching this workflow during task implementation, review, or publication;
  running the seeder against live production without the separate operational
  approval; mutating the live database outside the accepted workflow;
  deploying, releasing, or implicitly triggering the general production
  deployment workflow.
- Reading, printing, copying into the repository, or exposing the private CSV
  or operator credential contents, employee PII, emails, or credentials.
  Approved temporary `/tmp` runner/container staging for the later operation
  is the only permitted copy. Local tests MUST use generated synthetic CSV
  data only.
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
  employee PII and credentials. The ignored operator source remains outside
  Git and its contents remain private.
- Operator and administrator credentials MUST be supplied explicitly for the
  approved operation; no seeder fallback credentials may be used. Bootstrap
  credential output remains temporary, mode 0600, and disposable.
- Acceptance of the workflow implementation remains separate from release and
  from authorization to execute it against production.

## Dependencies and assumptions

### Dependencies

- A clean checkout at implementation baseline
  `ffbbaced4ebda7fcc01ab710c4ace5d055d917e5` and the exact published task
  revision. The live runtime gate remains the separate exact revision
  `4488f37787bc521869a2bb6113507387c5a983c8`.
- The existing self-hosted production runner has Docker access and is a Swarm
  manager for `mhcs_core`; the app and database services are reachable through
  the established deployment paths.
- `/etc/madeena-mhcs_core-db-backup.sh` is installed and configured by the
  existing database-setup workflow. It remains the backup authority for this
  operation.
- The existing GitHub `production` Environment is restricted exactly to the
  `main` branch. A designated operator may provision the two approved fixed
  Environment secrets only for the later operational attempt and must delete
  and verify absence of both after the attempt.
- The ignored private `research/prestige/operator.txt` source is available to
  the designated operator without exposing its contents. Its structure can be
  validated as one or more email lines plus a non-empty `password:` line.
- The current app container exposes non-empty `SUPER_ADMIN_EMAIL` and
  `SUPER_ADMIN_PASSWORD` values to the seeder process without logging them.
- The production runtime exposes the current application revision through the
  existing immutable image tag and `VERSION-CURRENT` mount.

### Approved assumptions

- `4488f37787bc521869a2bb6113507387c5a983c8` is the exact expected reviewed
  production application revision for the first workflow execution. A
  mismatch is a safety failure, not permission to select another revision.
- The installed backup script's successful return is the repository-approved
  backup completion signal because it validates the compressed dump and uploads
  it through the established S3/MinIO mechanism.
- The existing protected `production` Environment can carry the two fixed
  temporary secrets without placing private material in the repository
  workspace. Inventing a new secret system or infrastructure boundary is not
  authorized.
- The existing seeder and its synthetic regression tests remain the source of
  truth for fixture idempotency and application-level data validation.

### Remaining approval requirements

- Planner/Reviewer must review and accept the workflow implementation before
  production execution is considered.
- A designated production/release authority must separately approve the exact
  workflow implementation revision and the actual `workflow_dispatch` run.
- The existing `production` Environment policy remains an operational
  prerequisite. The designated operator must provision exactly the two fixed
  temporary secrets only for the separately approved attempt and must delete
  and verify absence of both afterward.

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
  Apply the same restrictions to operator credentials and bootstrap output.
  Do not place any private source in `$GITHUB_WORKSPACE` as a tracked or
  persistent fixture.
- Stage `PRESTIGE_OPERATOR_CREDENTIALS` as a temporary mode-0600 file, require
  at least one valid email line and one non-empty `password:` line, and copy it
  only to `/var/www/html/research/prestige/operator.txt` in the current app
  container. Ensure normal-user-readable ownership, mode 0600, metadata-only
  verification, and EXIT cleanup of both copies.
- Before the backup, use read-only metadata checks inside the current app
  container to prove `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` are
  present and non-empty. Never print either value and never provide a
  fallback.
- Pass `MHCS_BOOTSTRAP_CREDENTIAL_PATH` only to the fixed seeder process,
  verify the resulting `/tmp/mhcs-prestige-bootstrap-credentials.txt` mode
  without reading it, and remove it on EXIT with sufficient privilege.
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
- [ ] The CSV is supplied only through the fixed protected `production`
  Environment secret `PRESTIGE_EMPLOYEE_CSV`, is never placed in tracked
  workspace files, is passed through the fixed container path, is protected by
  restrictive permissions, and is removed from temporary host/container
  locations after success or failure where practical.
- [ ] The operator credential material is supplied only through the fixed
  protected `production` Environment secret
  `PRESTIGE_OPERATOR_CREDENTIALS`, is staged temporarily at mode 0600,
  validates at least one email line and one non-empty `password:` line without
  logging either value, is copied only to the exact seeder path in the current
  app container, and is removed from runner/container temporary locations.
- [ ] Before backup or seed, the workflow fails closed unless
  `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` are present and non-empty in
  the current app container; it supplies no fallback administrator values and
  emits neither value.
- [ ] The fixed seeder process receives
  `MHCS_BOOTSTRAP_CREDENTIAL_PATH=/tmp/mhcs-prestige-bootstrap-credentials.txt`,
  any resulting file is mode 0600 and content is never read or printed, and
  EXIT cleanup removes it with sufficient privilege.
- [ ] Workflow output contains no employee PII, CSV contents, credentials, or
  Member identity data.
- [ ] The only production data command is the exact
  `Database\Seeders\PrestigeClinicSeeder` with
  `MHCS_ALLOW_PRODUCTION_MVP_SEED=true` scoped to that process and Laravel's
  required `--force` option, plus the fixed employee CSV and bootstrap
  credential paths. No user-supplied class, path, or arbitrary command is
  accepted, and the seeder does not run as root.
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
  seed ordering, fixed Environment secrets, operator/admin fail-closed
  credential gates, exact seeder class, process-scoped authorization and
  bootstrap path, private-path handling, cleanup, no arbitrary seeder input,
  and sanitized verification contract.
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
   `git check-ignore -v -- research/prestige/operator.txt`,
   `git ls-files research`, and
   `git ls-files --stage -- research/prestige/data-karyawan-cv-prestige.csv`,
   without printing either private file's contents.
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
- the existing protected `production` Environment or either fixed temporary
  secret is unavailable at the separately approved operational attempt;
- employee/operator staging or structure validation cannot prove safe private
  delivery without reading, exposing, or persisting secret contents;
- `SUPER_ADMIN_EMAIL` or `SUPER_ADMIN_PASSWORD` is absent, empty, or would
  require a fallback value;
- bootstrap credential output cannot be kept at the fixed private temporary
  path with mode 0600 and removed on EXIT;
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
- Commit and push this task revision only during task republication.
- During a later separately approved operational attempt, provision exactly the
  two temporary `production` Environment secrets from the approved private
  sources, dispatch the accepted workflow, independently verify production, and
  delete and verify absence of both secrets after the attempt.

### Explicitly not authorized

- During task republication, dispatching `apply-prestige-production-data.yml`,
  running `PrestigeClinicSeeder` against the live production database, or
  creating/changing/deleting live production data, schemas, services,
  deployments, backups, or infrastructure.
- Provisioning any secret other than the two fixed temporary production
  Environment secrets explicitly authorized for the later operational attempt.
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
