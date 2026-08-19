---
title: Controlled Prestige Production Data Application
document_id: MHCS-TASK-PRESTIGE-PRODUCTION-DATA-APPLICATION-001
version: 1.3
status: validated-on-publication
language: en-US
last_updated: 2026-08-20
scope:
  - dedicated manual production workflow for the approved Prestige fixture
  - production revision, backup, private-source, and post-seed safety gates
  - protected operator/admin credential handling for the approved production seed
  - sanitized read-only verification of the Prestige production dataset
  - restoration and verification of the established production backup prerequisite
  - optional sanitized read-only verification of the existing Prestige Member accounts
authority_note: This task authorizes implementation and publication of the dedicated workflow, the bounded credential-safety remediation, the backup-prerequisite correction, and the optional read-only Member-account preflight described in revision 1.3. Later production execution remains a separately approved operational action; this task republication and implementation do not themselves dispatch the workflow, deploy, seed, or mutate the live database.
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

Revision 1.1 added an owner-approved credential-safety remediation. At the
expected production revision, `PrestigeClinicSeeder` has fallback values for
operator and administrator credentials when private credential material is
unavailable. The production workflow MUST fail closed before backup or
seeding unless the approved private credential inputs are present, structured,
and staged safely. Revision 1.3 preserves those controls and adds the
owner-approved backup-prerequisite correction and optional read-only
Member-account preflight. The existing seeder and application code remain
unchanged.

## Baseline and task revision

**Implementation baseline:** `1fc5c9440415d0e38e7039f06d2f4362e00ccf21`

**Task revision:** `The full SHA of the commit containing this exact validated task content, supplied by the Planner after publication.`

The implementation baseline and expected production application/runtime
revision are intentionally distinct:

- **Implementation baseline** = `1fc5c9440415d0e38e7039f06d2f4362e00ccf21`
- **Expected production application/runtime revision** =
  `4488f37787bc521869a2bb6113507387c5a983c8`

The expected production application/runtime revision for the first execution
is the exact immutable revision `4488f37787bc521869a2bb6113507387c5a983c8`.
A later production revision MUST NOT be accepted by this workflow merely
because it is newer; changing the expected runtime revision is a separate
planning/review decision.

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

## Approved backup-prerequisite and Member-account preflight

Revision 1.3 remains part of the same controlled Prestige production-data
objective. It adds only the owner-approved correction to the existing backup
setup workflow and an optional, independent read-only proof of the existing
Prestige Member-account population. It does not redefine Member credentials,
change the seeder, or introduce new production account semantics.

### A. Existing Member-account precondition

The production Prestige operation MUST reuse the 37 Prestige Member accounts
that are expected to already exist. Their existence MUST NOT be inferred from
schedules or bookings alone.

Before a renewed Prestige seed, the canonical production verifier MUST
independently prove, read-only and without PII output, that the expected
Prestige Member-account cohort exists. The established account convention is
Member-linked user accounts whose generated account email belongs to the fixed
`@prestige.madeena-xray.com` namespace.

The accepted Member credential contract remains:

- username/login identifier: `NIK`;
- password: the existing Member password, expected from the original Prestige
  fixture to be `NIK`.

The implementation MUST NOT reset Member passwords, recreate existing Member
users, alter Member login semantics, expose NIK values, generated email
local-parts, Member IDs, or password hashes. `PrestigeClinicSeeder` remains
unchanged and continues to reuse existing Member accounts when found.

### B. Canonical optional Member verification

The canonical `.github/workflows/verify-production.yml` MUST add this
`workflow_dispatch` boolean input:

```yaml
verify_prestige_members:
  type: boolean
  required: false
  default: false
```

When `verify_prestige_members=false`, it MUST emit only:

`PRESTIGE_MEMBER_VERIFICATION=skipped`

When `verify_prestige_members=true`, it MUST perform a sanitized read-only
database check through the existing production app container. The query MUST
identify the Prestige Member-account cohort independently of bookings using
the fixed email namespace above and MUST emit no email/local-part, NIK, name,
Member ID, password, or password hash.

The verification MUST require these aggregate results:

```text
prestige_user_accounts=37
prestige_linked_members=37
prestige_active_accounts=37
prestige_login_enabled_accounts=37
```

It MUST also prove, without identity output, that every candidate user has
exactly one linked Member record, no Member linkage is duplicated within the
cohort, every account has `account_status=active`, and every account has
`login_enabled=true`. It MAY emit and require
`prestige_must_change_password_false=37` when that field is part of the
established fixture and can be checked safely.

The verifier MUST NOT inspect password hashes or attempt to prove plaintext
passwords. It MUST emit only sanitized aggregate values. On success it emits
`PRESTIGE_MEMBER_VERIFICATION=pass`; on failure it emits
`PRESTIGE_MEMBER_VERIFICATION=failed` and fails the workflow.

This check is logically independent from the existing `verify_prestige`
schedule/booking invariant check and MUST support:

```text
verify_prestige_members=true
verify_prestige=false
```

so the account precondition can be verified before schedule remediation.

### C. Canonical verifier preservation

The verifier MUST remain `workflow_dispatch` only and MUST preserve its
production concurrency boundary, optional `expected_revision` behavior,
Swarm/service checks, revision consistency, Laravel bootstrap, read-only
database query, optional large-upload probe, and existing optional
`verify_prestige` schedule/booking checks. The Member verification is
read-only and MUST NOT deploy, migrate, seed, modify services, or write
business data. When `verify_prestige=false`, absent Prestige schedules or
bookings MUST NOT fail the generic verification; when
`verify_prestige_members=true`, absent or invalid Member-account evidence MUST
fail it.

### D. Server backup setup correction

The existing `.github/workflows/server-setup-db.yml` currently has a material
environment-scoping defect: required values are supplied to the DB-container
location step, while later consumers reference them under `set -u` without
receiving them. The implementation MUST correct only this scoping defect and
make each value available to every step that consumes it, using the narrowest
safe explicit job-level or consumer-step-level environment scope.

The corrected workflow MUST safely provide, without exposing values:

```text
SUDO_PASSWORD
APP_KEY
DB_DATABASE
DB_USERNAME
DB_PASSWORD
DB_ROOT_PASSWORD
AWS_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY
AWS_BUCKET
AWS_ENDPOINT
AWS_DEFAULT_REGION
```

No secret value may be printed, persisted to outputs, or added to a new
infrastructure mechanism.

### E. Server setup behavior preservation

`server-setup-db.yml` MUST remain `workflow_dispatch` only on `self-hosted`
and MUST preserve all existing behavior and paths:

- discovery of the existing `mhcs_core_db` container;
- application-user grants and `FLUSH PRIVILEGES`;
- application DB-user access verification;
- `/etc/madeena-mhcs_core-db-backup.sh` as the backup script;
- `/etc/madeena-mhcs_core-db-backup.env` as the backup environment file;
- backup environment mode `0600`;
- backup script mode `0700`;
- the established S3/MinIO backup implementation;
- non-empty archive, gzip, and `CREATE TABLE` validation;
- existing S3 copy and retention behavior;
- the marked root cron block with `CRON_TZ=Asia/Jakarta` and daily `02:00`.

It MUST NOT introduce new grants, databases, backup destinations, cron
schedules, or infrastructure semantics.

### F. Backup-prerequisite execution order

After implementation and independent review, the required operational order
is:

1. run `verify-production.yml` with
   `expected_revision=4488f37787bc521869a2bb6113507387c5a983c8`,
   `verify_prestige_members=true`, `verify_prestige=false`, and
   `run_large_upload_probe=false`;
2. require the sanitized 37-account Member verification to pass;
3. run the corrected `server-setup-db.yml`;
4. verify the backup script is a regular non-symlink executable with mode
   `0700`, `bash -n` passes, the backup env is a regular non-symlink file with
   mode `0600` and non-zero size, and exactly one expected managed cron block
   exists with the approved timezone and schedule;
5. run the canonical production verifier again read-only; and
6. only after these prerequisites succeed may the already-authorized Prestige
   production-data workflow be retried in a later reviewed operation.

Setup and Prestige seeding MUST NOT be combined into one execution. The
backup setup workflow MUST NOT be replaced by manual SQL or a new backup
architecture.

### G. Member credential safety

Existing Member accounts MUST be reused. No password reset, user recreation,
credential migration, login-semantic change, or plaintext/hash inspection is
authorized. The existing Operator private credential contract and the
existing production `SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD` contract
remain unchanged. No remediation in this revision may redefine credentials.

### H. Privacy

No workflow output may contain NIK, employee names, generated Member email
values, Member IDs, password values, password hashes, Operator credentials,
administrator credentials, backup environment contents, or private source
contents. Only aggregate counts and sanitized operational metadata may be
logged.

### I. Implementation scope

The allowed implementation files are:

- `.github/workflows/server-setup-db.yml`;
- `.github/workflows/verify-production.yml`; and
- directly relevant deployment/workflow regression tests.

`PrestigeClinicSeeder`, application source, migrations, schema, Member
credential behavior, deployment behavior, and generic infrastructure remain
out of scope. No Prestige seed is retried during implementation or review.

### J. Required regression coverage

Regression coverage MUST prove that `server-setup-db.yml` remains manual-only,
its required environment values are available to every consuming step, no
secret value is printed, grant/flush/access semantics are unchanged, backup
paths and modes `0700`/`0600` are unchanged, the approved cron
timezone/schedule remains, and no new infrastructure behavior is introduced.

Regression coverage for `verify-production.yml` MUST prove that
`verify_prestige_members` is a boolean defaulting to false, false emits the
skipped marker, true performs only a read-only aggregate query, the cohort is
identified independently of bookings, the expected aggregate counts are
`37/37/37/37`, linkage/status/login invariants are enforced, no PII or
password/hash inspection is logged, existing `verify_prestige` behavior and
expected-revision behavior remain unchanged, and the workflow remains
read-only. Existing Prestige credential-safety and `37/37/74` assertions MUST
remain covered.

## Objective

Create the dedicated `.github/workflows/apply-prestige-production-data.yml`
and implement the bounded v1.3 prerequisites that make its later reviewed
operation safe: correct the existing `server-setup-db.yml` environment
scoping, extend the canonical `verify-production.yml` with optional
read-only proof of the 37 existing Prestige Member accounts, and preserve the
exact production revision, backup-before-seed, credential-safety, fixed
seeder, and sanitized `37/37/74` contracts. The apply workflow must remain a
manual-only controlled operation and must not be dispatched by this task.

## Authoritative inputs

### Governing authority

- Human-authority production-data application contract supplied for this task:
  dedicated workflow, manual confirmation, exact-version gate, serialized
  production operations, backup-before-seed, private CSV handling, exact
  seeder restriction, post-seed verification, idempotency, and explicit
  prohibition on production execution until separate approval.
- Owner-approved revisions 1.1 and 1.3: protected `production` Environment
  use, temporary fixed employee/operator secrets, fail-closed credential
  prechecks, disposable bootstrap-credential output, correction of the
  existing backup-workflow environment scoping, and optional read-only
  Member-account preflight.
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
- `.github/workflows/server-setup-db.yml` — existing DB grant/access and
  `/etc/madeena-mhcs_core-db-backup.sh` backup mechanism, including the
  environment-scoping defect that this task bounds for correction.
- `.github/workflows/verify-production.yml` — canonical read-only production
  verifier, established app-container/database access boundary, revision gate,
  and existing optional Prestige schedule/booking verification.
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
- `Backup-prerequisite restoration` → owner-approved revision 1.3 contract,
  existing `server-setup-db.yml` behavior, and the established backup paths,
  validation, S3, retention, and cron conventions.
- `Prestige Member-account preflight` → owner-approved revision 1.3 contract,
  existing `verify-production.yml` read-only boundary, and the established
  `@prestige.madeena-xray.com` account convention.
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
- Correct only the environment-scoping defect in the existing
  `.github/workflows/server-setup-db.yml` so `SUDO_PASSWORD`, `APP_KEY`,
  `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`,
  `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_ENDPOINT`,
  and `AWS_DEFAULT_REGION` are available to every step that consumes them.
  Preserve its manual-only trigger, self-hosted runner, DB grant/access,
  backup, S3/MinIO, validation, retention, and cron behavior exactly.
- Extend `.github/workflows/verify-production.yml` with the optional boolean
  `verify_prestige_members` input, defaulting to false, and the independent
  sanitized read-only 37-account Member verification. It MUST support the
  Member preflight with `verify_prestige=true|false` independently and MUST
  remain read-only.
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
- Add or update focused deployment/workflow regression coverage for both the
  server-setup environment scoping and the optional Member-account verifier.

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
- Member password resets, Member-user recreation, credential migration,
  password-hash inspection, changes to Member login semantics, or any new
  production account behavior.
- Replacing or redesigning the established backup architecture, adding a new
  backup destination, changing DB grants, adding databases, or changing the
  established root cron schedule.
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
- `server-setup-db.yml` remains the sole approved setup path for the existing
  DB grants, backup script, protected backup environment, S3/MinIO behavior,
  retention, and root cron block. The v1.3 change is limited to making its
  existing consumer-step environment values available safely.
- `verify-production.yml` remains read-only. Its generic verification behavior
  and existing optional `verify_prestige` schedule/booking checks remain
  unchanged when `verify_prestige_members=false`; the new Member check is
  optional and independently gated.
- Prestige Member accounts retain the existing NIK login identifier and
  fixture password convention, and existing accounts are reused without
  resets, recreation, credential migration, or hash inspection.
- Acceptance of the workflow implementation remains separate from release and
  from authorization to execute it against production.

## Dependencies and assumptions

### Dependencies

- A clean checkout at implementation baseline
  `1fc5c9440415d0e38e7039f06d2f4362e00ccf21` and the exact published task
  revision. The live runtime gate remains the separate exact revision
  `4488f37787bc521869a2bb6113507387c5a983c8`.
- The existing self-hosted production runner has Docker access and is a Swarm
  manager for `mhcs_core`; the app and database services are reachable through
  the established deployment paths.
- The existing `server-setup-db.yml` is the sole approved mechanism for
  restoring and verifying the backup prerequisite. Its current
  environment-scoping defect must be corrected and accepted before the
  installed `/etc/madeena-mhcs_core-db-backup.sh` and its protected env file
  may be relied upon for the Prestige operation.
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
- The canonical verifier can use the existing app-container/database boundary
  to identify the fixed `@prestige.madeena-xray.com` Member-account cohort
  without emitting identity or credential data.

### Approved assumptions

- `4488f37787bc521869a2bb6113507387c5a983c8` is the exact expected reviewed
  production application revision for the first workflow execution. A
  mismatch is a safety failure, not permission to select another revision.
- After the bounded setup correction, the backup script's successful return is
  the repository-approved backup completion signal because it validates the
  compressed dump and uploads it through the established S3/MinIO mechanism.
- The existing protected `production` Environment can carry the two fixed
  temporary secrets without placing private material in the repository
  workspace. Inventing a new secret system or infrastructure boundary is not
  authorized.
- The existing seeder and its synthetic regression tests remain the source of
  truth for fixture idempotency and application-level data validation.
- The Member-account preflight is an existence/invariant check only; it does
  not prove plaintext passwords and does not authorize credential changes.

### Remaining approval requirements

- Planner/Reviewer must review and accept the workflow implementation before
  production execution is considered.
- Planner/Reviewer must review and accept the bounded server-setup and
  canonical-verifier implementation before the prerequisite execution order
  may begin.
- A designated production/release authority must separately approve the exact
  implementation revision and each later `workflow_dispatch` run.
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
- Keep `server-setup-db.yml` `workflow_dispatch`-only on `self-hosted`. Supply
  each of its existing secret/configuration values to every consuming step
  with explicit narrow scope; do not print, persist, or rename any value.
  Preserve its existing DB grant, `FLUSH PRIVILEGES`, application-access,
  backup, S3/MinIO, validation, retention, and cron operations.
- Keep `verify-production.yml` `workflow_dispatch`-only and read-only. Add
  only the optional `verify_prestige_members` boolean input and its aggregate
  account query. Identify candidates by the fixed
  `@prestige.madeena-xray.com` namespace and Member linkage, not bookings.
  Emit only the required counts and booleans; never inspect password hashes or
  print credentials or identity fields.
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
- For the later reviewed operational sequence, run Member preflight first
  with `verify_prestige_members=true`, then the corrected server setup, then a
  second canonical verifier. Do not combine backup setup with Prestige
  seeding and do not retry the seed until both verifier runs and the backup
  prerequisite checks pass.
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
- [ ] `server-setup-db.yml` remains manual-only and self-hosted; every
  existing required environment value is available to each consuming step
  without any secret value being printed or persisted.
- [ ] The server-setup DB grant, `FLUSH PRIVILEGES`, application-access check,
  backup paths, S3/MinIO validation and copy/retention behavior, `0700`/`0600`
  modes, and one `CRON_TZ=Asia/Jakarta` daily `02:00` managed block remain
  unchanged, with no new infrastructure behavior.
- [ ] `verify-production.yml` has a `verify_prestige_members` boolean input
  with `required: false` and `default: false`; false emits
  `PRESTIGE_MEMBER_VERIFICATION=skipped`.
- [ ] With `verify_prestige_members=true`, the canonical verifier performs
  only a read-only aggregate query that independently identifies the fixed
  `@prestige.madeena-xray.com` cohort and fails unless the sanitized results
  are `prestige_user_accounts=37`, `prestige_linked_members=37`,
  `prestige_active_accounts=37`, and `prestige_login_enabled_accounts=37`,
  with one unique Member linkage per user, active status, and login enabled.
  It emits no PII, credentials, or password/hash data and reports the pass or
  failed marker.
- [ ] The Member-account verification is independent from `verify_prestige`,
  so `verify_prestige_members=true` with `verify_prestige=false` is supported;
  existing generic verification and schedule/booking behavior remain intact.
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
- [ ] Existing Member credentials are reused with NIK login semantics; no
  Member password reset, recreation, migration, login-semantic change, or
  password/hash inspection is introduced.
- [ ] After implementation review, operational prerequisite order is
  Member verifier → corrected server setup → backup-prerequisite metadata
  checks → canonical read-only verifier → later separately reviewed Prestige
  seed; setup and seed are never combined.
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
  and sanitized verification contract. It must also cover the corrected
  `server-setup-db.yml` environment scoping and preserved grant/backup/cron
  behavior, plus the `verify_prestige_members` input, skipped path, read-only
  aggregate path, independent cohort identification, `37/37/37/37` counts,
  no-PII/no-hash contract, and unchanged `verify_prestige` behavior.
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
9. Validate the revised task contract itself: version `1.3`,
   `last_updated: 2026-08-20`, implementation baseline
   `1fc5c9440415d0e38e7039f06d2f4362e00ccf21`, expected production runtime
   `4488f37787bc521869a2bb6113507387c5a983c8`, no stale assumption that the
   backup workflow is usable before its scoping correction, no assumption
   that schedules/bookings prove Member accounts, preserved credential and
   `37/37/74` contracts, and no authorization to reset credentials.
10. Run `git diff --check` and confirm the task revision is the only changed
    repository file during this republication.

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
- the corrected `server-setup-db.yml` cannot make each required value
  available to its consuming steps without exposing or persisting secrets, or
  its established grant, backup, validation, S3/MinIO, retention, or cron
  behavior would need to change;
- the canonical verifier cannot independently identify the fixed Prestige
  Member-account cohort and prove the required aggregate/linkage/status/login
  invariants without PII or password/hash inspection;
- the Member-account preflight fails, or the backup-prerequisite execution
  order would require combining setup with seeding or bypassing a read-only
  verifier;
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
- Implement only the dedicated workflow, the bounded `server-setup-db.yml`
  environment-scoping correction, the optional read-only Member verifier, and
  their focused static/regression tests required by this task.
- Run local syntax, static, test, build, format, diff, and privacy checks using
  synthetic data only.
- Commit and push this task revision only during task republication.
- After implementation and independent review, run the canonical read-only
  Member preflight, the corrected existing server-setup workflow, its safe
  backup-prerequisite metadata/syntax checks, and the canonical read-only
  verifier again in the exact order defined by this task. These operations
  remain separate from the later Prestige seed approval.
- During a later separately approved operational attempt, provision exactly the
  two temporary `production` Environment secrets from the approved private
  sources, dispatch the accepted workflow, independently verify production, and
  delete and verify absence of both secrets after the attempt.

### Explicitly not authorized

- During task republication, dispatching `apply-prestige-production-data.yml`,
  running `PrestigeClinicSeeder` against the live production database, or
  creating/changing/deleting live production data, schemas, services,
  deployments, backups, or infrastructure.
- During task republication or implementation review, dispatching either
  `server-setup-db.yml` or `verify-production.yml`; those later operational
  runs require the published implementation, independent review, and the
  execution sequence defined above.
- Provisioning any secret other than the two fixed temporary production
  Environment secrets explicitly authorized for the later operational attempt.
- Reading or printing the private employee CSV contents or any employee PII.
- Implicitly triggering `deploy-swarm.yml` or treating implementation acceptance
  as release authorization.

## Expected terminal outcome

### Review Required

Return one reviewable implementation revision covering the dedicated workflow,
the bounded backup-workflow correction, the optional read-only Member
preflight, and their truthful local verification evidence. Planner/Reviewer
must separately accept the implementation before any prerequisite execution
or production seed decision.

### Planning Required

Use when a secure runtime-source, exact-version, backup, authorization,
architecture, or operational dependency cannot be satisfied without new
authority. Do not publish a nominal implementation or bypass the failed gate.

Acceptance of this workflow task is not production execution or release
authorization.
