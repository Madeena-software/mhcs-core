---
title: Controlled Prestige Production Data Application
document_id: MHCS-TASK-PRESTIGE-PRODUCTION-DATA-APPLICATION-001
version: 1.5
status: validated-on-publication
language: en-US
last_updated: 2026-08-20
scope:
  - dedicated manual production workflow for the approved three-schedule Prestige fixture
  - bounded Prestige seeder schedule and booking reconciliation for the rehearsal
  - production revision, backup, private-source, and post-seed safety gates
  - protected operator/admin credential handling for the approved production seed
  - sanitized read-only verification of the Prestige production dataset
  - restoration and verification of the established production backup prerequisite
  - optional sanitized read-only verification of the existing Prestige Member accounts
authority_note: This task authorizes a two-phase implementation of the owner-requested three-schedule rehearsal: Phase A produces and tests the application revision containing the seeder, and Phase B produces and tests the workflows pinned to the exact accepted Phase-A revision. Existing backup, protected-environment, credential, privacy, and Member-account safety controls remain mandatory, while server-setup-db.yml is preserved and not in implementation scope. Later deployment and production execution remain separately approved operational actions; this task republication and implementation do not themselves dispatch workflows, deploy, seed, or mutate the live database.
---

# Executable Task

## Task identity

**Task title:** `Controlled Prestige Production Data Application`

**Task path:** `.agents/tasks/prestige-production-data-application.md`

**Task contract state:** `Validated/Published upon immutable publication of this exact content`

**Delivery objective / Work Package / MVP:** `Controlled production data application for the accepted Prestige rehearsal fixture`

**Owner / designated planning authority:** `Faliq Adlan, CTO`

## Delivery context

The owner-requested Prestige rehearsal uses the same existing 37 Prestige
Members in three non-overlapping schedules: one full local-calendar interval
from 20 through 26 August 2026, followed by 27 August and 28 August 2026.
The current pre-change production runtime is
`4488f37787bc521869a2bb6113507387c5a983c8`; it contains the old two-schedule
seeder and MUST NOT be used for the expanded three-schedule mutation. Phase A
must first produce the application revision containing the new seeder. Phase B
then updates the manually triggered workflows to require that exact Phase-A
revision. Production application remains outside implementation and review
execution.

The workflow is an operational data-application mechanism, not a general
seeder runner, deployment replacement, or production release authorization.

Revision 1.1 added the owner-approved credential-safety remediation. The
current pre-change runtime's `PrestigeClinicSeeder` has fallback values for
operator and administrator credentials when private credential material is
unavailable. The production workflow MUST fail closed before backup or
seeding unless the approved private credential inputs are present, structured,
and staged safely. Revision 1.5 preserves those controls, the established
verified backup prerequisite, and the optional read-only Member-account
preflight while authorizing the bounded two-phase seeder and workflow/verifier
changes described below. The normal runtime booking service remains unchanged.

## Baseline and task revision

**Implementation baseline:** `12dd1ccd0763d48fd581fe3dec9eb53f5a794c05`

**Task revision:** `The full SHA of the commit containing this exact validated task content, supplied by the Planner after publication.`

The implementation baseline, current pre-change production runtime, and
three-schedule application runtime are intentionally distinct:

- **Implementation baseline** = `12dd1ccd0763d48fd581fe3dec9eb53f5a794c05`
- **Current pre-change production runtime** =
  `4488f37787bc521869a2bb6113507387c5a983c8`
- **Three-schedule application runtime** = the exact immutable Phase-A
  application implementation commit produced under this task and subsequently
  accepted by Planner/Reviewer.

Phase B MUST hardcode the full Phase-A SHA as the apply workflow's
`EXPECTED_REVISION` after Phase A has been committed. The apply workflow MUST
reject the current pre-change runtime for the expanded mutation. A Phase-A SHA
MUST NOT remain a placeholder in the Phase-B workflow and MUST NOT be replaced
by a mutable branch name or an unrelated newer revision.

## Two-phase implementation contract

Implementation may occur in one Executor session, but it MUST produce two
distinct commits based on the implementation baseline.

### Phase A — application revision

Phase A may modify only:

- `database/seeders/PrestigeClinicSeeder.php`;
- `tests/Feature/Operator/PrestigeClinicSeederTest.php`; and
- an existing directly relevant normal runtime duplicate-booking regression
  test, only if modification is genuinely necessary.

Phase A MUST NOT modify either workflow or any workflow test. It MUST implement
exactly the three schedules, quota/count/set invariants, existing-Member reuse,
no password reset or recreation, private CSV and credential behavior,
production seeder guard, idempotent booking reconciliation, obsolete-schedule
safety, and unchanged normal runtime duplicate-active-booking behavior defined
by this task. A reusable local-date-range/window helper is preferred.

After Phase-A tests pass, commit Phase A separately with an immutable full SHA.
The Executor MUST record:

```text
THREE_SCHEDULE_APP_REVISION=<PHASE_A_SHA>
```

Suggested Phase-A commit message:
`fix(prestige): add three rehearsal schedules`

Phase A MUST NOT be deployed by implementation.

### Phase B — workflow revision

Starting from the Phase-A commit, Phase B may modify only:

- `.github/workflows/apply-prestige-production-data.yml`;
- `.github/workflows/verify-production.yml`;
- `tests/Deployment/PrestigeProductionWorkflowTest.php`;
- `tests/Deployment/ProductionVerificationWorkflowTest.php`; and
- another existing directly relevant workflow test, only if necessary.

Phase B MUST hardcode:

```yaml
EXPECTED_REVISION="<PHASE_A_SHA>"
```

where `<PHASE_A_SHA>` is the exact Phase-A commit just produced. It MUST NOT
continue accepting `4488f37787bc521869a2bb6113507387c5a983c8` for the expanded
three-schedule mutation. Phase B MUST preserve the exact confirmation,
three-schedule verification, Member-account verification, backup, secret,
credential, cleanup, and privacy contracts in this task.

After Phase-B tests pass, commit Phase B separately with an immutable full SHA.
Suggested Phase-B commit message:
`fix(workflow): apply three-schedule Prestige fixture`

The final topology is:

```text
Phase-A SHA
  → deployable application revision containing the three-schedule seeder
Phase-B SHA
  → main workflow revision pinned to the accepted Phase-A SHA
```

Neither phase authorizes deployment, workflow dispatch, secret provisioning,
or production mutation.

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
This task does not authorize changing the private credential path, parser,
fallback behavior, or adding another path abstraction.

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

The bounded schedule and booking changes authorized by this task MUST remain
inside the existing application seeder. It MUST execute as the normal
application user, never root. The only allowed production data invocation
remains the fixed class:

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

The three-schedule sanitized verification remains mandatory:

```text
site_active=true
schedule_count=3
schedule_bounds_match=true
quota_20_26=37
quota_27=37
quota_28=37
confirmed_20_26=37
confirmed_27=37
confirmed_28=37
total_bookings=111
distinct_members=37
member_sets_equal=true
verification_passed=true
```

The required closed-open local intervals use `Asia/Jakarta`, and the
UTC-persisted boundaries are:

```text
20–26 Aug:
  local [2026-08-20T00:00:00+07:00, 2026-08-27T00:00:00+07:00)
  starts_at = 2026-08-19 17:00:00
  ends_at   = 2026-08-26 17:00:00
27 Aug:
  local [2026-08-27T00:00:00+07:00, 2026-08-28T00:00:00+07:00)
  starts_at = 2026-08-26 17:00:00
  ends_at   = 2026-08-27 17:00:00
28 Aug:
  local [2026-08-28T00:00:00+07:00, 2026-08-29T00:00:00+07:00)
  starts_at = 2026-08-27 17:00:00
  ends_at   = 2026-08-28 17:00:00
```

All three schedules MUST have quota 37, 37 confirmed bookings, and identical
sets of the same 37 existing Members. The three intervals MUST NOT overlap.

No Member IDs, employee PII, emails, or credentials may be emitted.

### J. Preserved scope boundary

Migrations, schema, deployment, generic secret infrastructure, generic
seeder/command runners, direct SQL, manual production mutation, unrelated
application changes, and unrelated credential rotation remain out of scope.

## Approved backup-prerequisite and Member-account preflight

The prior publication remains part of the same controlled Prestige
production-data objective. It preserves the established backup prerequisite
and adds the owner-requested three-schedule fixture contract plus an independent,
read-only proof of the existing Prestige Member-account population. It does
not redefine Member credentials or introduce new production account
semantics.

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
local-parts, Member IDs, or password hashes. The bounded seeder update MUST
continue to reuse existing Member accounts when found.

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
prestige_member_linkage_exact=true
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
database query, optional large-upload probe, and the existing optional
`verify_prestige` schedule/booking check updated to the exact three-schedule
invariant. The Member verification is read-only and MUST NOT deploy, migrate,
seed, modify services, or write business data. When `verify_prestige=false`,
absent Prestige schedules or
bookings MUST NOT fail the generic verification; when
`verify_prestige_members=true`, absent or invalid Member-account evidence MUST
fail it.

### D. Existing backup prerequisite

The established production backup mechanism remains mandatory and outside the
seeder's data contract. Before any Prestige database mutation, the apply
workflow MUST use the existing verified backup path, including the installed
`/etc/madeena-mhcs_core-db-backup.sh` mechanism, non-empty archive/gzip and
`CREATE TABLE` validation, established S3/MinIO upload, and retention cleanup.
The existing `server-setup-db.yml`, its protected inputs, backup paths, grants,
and cron behavior are preserved and are not implementation scope for this
task. No new backup architecture, destination, grant, or cleanup mechanism is
authorized.

### E. Backup-prerequisite execution order

After Phase A and Phase B implementation and independent review, the eventual
operational order is:

1. separately authorize and deploy the exact accepted Phase-A SHA;
2. run `verify-production.yml` with
   `expected_revision=<PHASE_A_SHA>`, `verify_prestige_members=true`,
   `verify_prestige=false`, and `run_large_upload_probe=false`;
3. require the sanitized 37-account Member verification to pass;
4. require the established backup prerequisite to remain available without
   changing `server-setup-db.yml`;
5. separately authorize and temporarily provision the two protected Prestige
   secrets;
6. dispatch the accepted Phase-B/main workflow using
   `APPLY-PRESTIGE-2026-08-20-28`;
7. require the apply workflow's mandatory verified database backup;
8. require the apply workflow to execute the Phase-A three-schedule seeder;
9. require 37/37/37 confirmed bookings, 111 total bookings, 37 distinct
   Members, and identical Member sets;
10. delete and verify absence of both temporary secrets; and
11. run the canonical verifier with `expected_revision=<PHASE_A_SHA>`,
    `verify_prestige=true`, and `verify_prestige_members=true`.

Server-setup/backup-prerequisite establishment and verification MUST be
separate from the Prestige apply operation. The actual database backup
execution remains inside `apply-prestige-production-data.yml` and MUST succeed
immediately before the seeder is allowed to mutate Prestige data. The backup
setup workflow MUST NOT be replaced by manual SQL or a new backup architecture.

### F. Member credential safety

Existing Member accounts MUST be reused. No password reset, user recreation,
credential migration, login-semantic change, or plaintext/hash inspection is
authorized. The existing Operator private credential contract and the
existing production `SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD` contract
remain unchanged. No remediation in this revision may redefine credentials.

### G. Privacy

No workflow output may contain NIK, employee names, generated Member email
values, Member IDs, password values, password hashes, Operator credentials,
administrator credentials, backup environment contents, or private source
contents. Only aggregate counts and sanitized operational metadata may be
logged.

### H. Implementation scope

The bounded implementation is split into the two commits defined above:

- Phase A is limited to `database/seeders/PrestigeClinicSeeder.php`,
  `tests/Feature/Operator/PrestigeClinicSeederTest.php`, and an existing
  directly relevant normal runtime duplicate-booking regression test only if
  genuinely necessary.
- Phase B starts from Phase A and is limited to
  `.github/workflows/apply-prestige-production-data.yml`,
  `.github/workflows/verify-production.yml`,
  `tests/Deployment/PrestigeProductionWorkflowTest.php`,
  `tests/Deployment/ProductionVerificationWorkflowTest.php`, and another
  existing directly relevant workflow test only if genuinely necessary.

Phase A MUST NOT modify workflows. Phase B MUST NOT modify the seeder or its
Phase-A tests. Each phase MUST be committed separately and reviewed within its
bounded scope.

No unrelated application changes, migrations, schema changes, deployment
changes, `server-setup-db.yml` changes, Member credential changes, or generic
infrastructure changes are authorized. No Prestige seed is run against
production during implementation or review.

### I. Required regression coverage

Regression coverage for `PrestigeClinicSeeder` MUST prove exactly three
schedules, the exact three UTC windows, quota 37 for each, 37 confirmed
bookings for each, 111 total bookings, 37 distinct Members, identical Member
sets, idempotent rerun, existing Member reuse, no password reset, and no
change to normal runtime duplicate-active-booking rejection.

Regression coverage for the apply workflow MUST prove the exact new
confirmation phrase, rejection of the stale
`APPLY-PRESTIGE-2026-08-27-28` phrase, the three-schedule post-seed invariant,
the 111/37 totals, all Member-set equality semantics, and preservation of
backup, secret, credential, cleanup, and privacy controls. Regression
coverage for `verify-production.yml` MUST prove the same three-schedule
invariant for `verify_prestige`, preserve the independent
`verify_prestige_members` 37/37/37/37 account check, and require both checks
for successful post-apply verification.

## Objective

Implement the owner-requested three-schedule Prestige rehearsal in two bounded
commits: Phase A updates `PrestigeClinicSeeder` and its seeder regression to
produce one 20–26 August schedule plus 27 August and 28 August schedules for
the same 37 existing Members; Phase B updates the dedicated apply workflow and
`verify-production.yml` to pin runtime verification and the apply mutation to
the exact accepted Phase-A SHA. Preserve the exact three-schedule invariant,
backup-before-seed, credential-safety, privacy, and normal runtime booking
contracts. The workflows must remain manual-only and neither phase may be
dispatched, deployed, or used to mutate production by implementation itself.

## Authoritative inputs

### Governing authority

- Owner-requested three-schedule production-data contract supplied for this
  task: one 20–26 August 2026 schedule plus 27 August and 28 August 2026,
  full Asia/Jakarta calendar boundaries, exact UTC persistence, the same 37
  existing Members in all schedules, idempotent booking reconciliation, exact
  37/37/37/111/37 totals, and no production execution until separate
  approval.
- Owner-approved revisions 1.1, 1.3, and 1.5: protected `production` Environment
  use, temporary fixed employee/operator secrets, fail-closed credential
  prechecks, disposable bootstrap-credential output, established verified
  backup handling, and optional read-only Member-account preflight. Revision
  1.5 preserves those controls while making the Phase-A application revision
  an explicit dependency of the Phase-B workflows.
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
  `/etc/madeena-mhcs_core-db-backup.sh` backup mechanism, inspected only to
  preserve the established verified backup, S3/MinIO, and retention behavior;
  this task does not authorize changes to that workflow.
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
  PRODUCTION SAFETY, the Phase-A runtime handoff, and existing
  `deploy-swarm.yml` conventions.
- `Backup and private-source handling` → human-authority contract,
  DATABASE BACKUP and PRIVATE PRESTIGE CSV sections, plus the established
  `server-setup-db.yml` mechanism.
- `Credential-safety remediation` → owner-approved revision 1.1 contract,
  protected Environment, operator/admin prechecks, and existing
  `MvpCredentialFile.php` path behavior.
- `Verified backup prerequisite preservation` → owner-approved production
  safety contract, existing `server-setup-db.yml` behavior, and the
  established backup paths, validation, S3/MinIO upload, and retention
  conventions.
- `Prestige Member-account preflight` → owner-approved revision 1.3 contract,
  existing `verify-production.yml` read-only boundary, and the established
  `@prestige.madeena-xray.com` account convention.
- `Three-schedule seed and final-state invariants` → owner-requested contract,
  PRODUCTION SEED AUTHORIZATION, APPLICATION CODE, and POST-SEED VERIFICATION
  sections, plus the accepted Prestige task lineage.
- `Normal runtime booking preservation` → existing booking-domain behavior and
  the existing duplicate-active-booking regression coverage.
- `Phase-A/Phase-B sequencing` → owner-requested two-commit implementation
  contract and the exact immutable Phase-A-to-Phase-B runtime dependency.

## Scope

### In scope

- Update the dedicated apply workflow
  `.github/workflows/apply-prestige-production-data.yml` and the canonical
  read-only verifier `.github/workflows/verify-production.yml` in their
  bounded Phase-B commit.
- Use `workflow_dispatch` only with one required confirmation input whose exact
  accepted value is `APPLY-PRESTIGE-2026-08-20-28`. Invalid or missing input,
  including the stale `APPLY-PRESTIGE-2026-08-27-28` phrase, MUST fail before
  the production mutation job starts.
- Use the existing production serialization boundary exactly:
  `concurrency.group: production-deployment-mhcs_core` with
  `cancel-in-progress: false`. The workflow MUST NOT run concurrently with
  `deploy-swarm.yml`.
- Before backup or seed, Phase B MUST verify that the current healthy
  production `mhcs_core_app` runtime is the exact Phase-A SHA hardcoded in
  `EXPECTED_REVISION`. Reuse the existing deployment paths and evidence,
  including the current desired app container, its immutable service image
  tag, and the mounted `/var/www/html/VERSION-CURRENT` value, or an equivalent
  established runtime version proof. Missing, unknown, or mismatched version
  evidence MUST fail closed. The current pre-change runtime
  `4488f37787bc521869a2bb6113507387c5a983c8` MUST be rejected for the expanded
  three-schedule mutation.
- Update `database/seeders/PrestigeClinicSeeder.php` to configure exactly the
  three required full-day local-calendar windows. Prefer one clear reusable
  local-date-range helper; do not spread timezone arithmetic literals through
  the seeder. Reuse existing Members, matching schedules, and matching
  Member/schedule bookings; create only missing records; and keep reruns
  idempotent. The existing 27 and 28 August boundaries remain unchanged, while
  one schedule spans 20 through 26 August inclusive.
- Extend or update `.github/workflows/verify-production.yml` with the optional
  boolean `verify_prestige_members` input, defaulting to false, and preserve
  its independent sanitized read-only 37-account Member verification. Its
  `verify_prestige` check MUST use the exact same three-schedule invariant as
  the apply workflow. A successful post-apply canonical verification MUST
  require both `verify_prestige=true` and `verify_prestige_members=true`.
- Before the seeder, invoke the established production database backup
  mechanism installed at `/etc/madeena-mhcs_core-db-backup.sh`. Require its
  successful exit and its existing non-empty/integrity/S3 verification before
  continuing. Preserve the established S3/MinIO upload and retention cleanup;
  report only a sanitized backup success/reference. Do not add an automatic
  restore path or change `server-setup-db.yml`.
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
- Run exactly the fixed seeder class and no user-selected class:

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
  container. Require `site_active=true`, `schedule_count=3`,
  `schedule_bounds_match=true`, quota and confirmed counts of 37 for 20–26,
  27, and 28 August, `total_bookings=111`, `distinct_members=37`, and
  `member_sets_equal=true` with all three sets identical. Emit only sanitized
  booleans/counts and schedule values; never emit Member IDs, names, NIKs,
  addresses, birth dates, credentials, or query rows.
- Preserve the existing seeder's production guard, exact-37 CSV validation,
  private credential behavior, idempotent behavior, and fail-closed
  obsolete-schedule/downstream-data protection. No production second-run test
  or live dispatch is authorized by this task; local synthetic seeder
  regression is the required evidence for idempotency.
- Add or update focused tests for the seeder, apply workflow, canonical
  verifier, and the existing normal runtime duplicate-active-booking behavior.

### Out of scope

- Any `push`, `pull_request`, `schedule`, or cron trigger for the new workflow.
- A generic arbitrary-seeder workflow, arbitrary command runner, user-supplied
  seeder class, user-supplied production revision, or user-supplied database
  target.
- Changes to application code other than the bounded
  `PrestigeClinicSeeder` schedule/booking fixture behavior, migrations,
  database schema, deployment behavior, `deploy-swarm.yml`, Docker topology,
  or unrelated application semantics.
- Changes to `.github/workflows/server-setup-db.yml`; it is preserved as the
  existing backup/setup mechanism and is not an implementation target for this
  revision.
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
  idempotency remain unchanged except for the bounded three-window fixture
  update authorized here.
- The 27 and 28 August `Asia/Jakarta` half-open intervals and their UTC
  boundaries remain unchanged. The new first interval is exactly:
  `20–26 Aug [2026-08-20T00:00:00+07:00, 2026-08-27T00:00:00+07:00)` with
  stored UTC `2026-08-19 17:00:00` → `2026-08-26 17:00:00`.
- The final dataset remains quota 37 per schedule, 37 unique Members in each
  schedule, 37 confirmed bookings per schedule, identical Member sets across
  all three schedules, and 111 total bookings.
- The ordinary Member one-active-booking runtime invariant remains unchanged;
  the three-date duplicate assignment remains limited to the approved
  fixture/seeder path.
- The real CSV remains ignored and untracked, and workflow logs remain free of
  employee PII and credentials. The ignored operator source remains outside
  Git and its contents remain private.
- Operator and administrator credentials MUST be supplied explicitly for the
  approved operation; no seeder fallback credentials may be used. Bootstrap
  credential output remains temporary, mode 0600, and disposable.
- The existing `server-setup-db.yml` and installed backup mechanism remain the
  approved setup path for DB grants, the protected backup environment,
  S3/MinIO behavior, retention, and the managed cron block. This task changes
  none of those controls.
- `verify-production.yml` remains read-only. Its generic verification behavior
  and independent `verify_prestige_members` account check remain unchanged;
  only the optional `verify_prestige` schedule/booking check is updated to the
  three-schedule invariant and the Phase-A runtime gate.
- Prestige Member accounts retain the existing NIK login identifier and
  fixture password convention, and existing accounts are reused without
  resets, recreation, credential migration, or hash inspection.
- Acceptance of the workflow implementation remains separate from release and
  from authorization to execute it against production.

## Dependencies and assumptions

### Dependencies

- A clean checkout at implementation baseline
  `12dd1ccd0763d48fd581fe3dec9eb53f5a794c05` and the exact published task
  revision. Phase B's live runtime gate is the exact Phase-A SHA produced and
  accepted during Phase A; the current pre-change runtime
  `4488f37787bc521869a2bb6113507387c5a983c8` is not an acceptable apply gate.
- The existing self-hosted production runner has Docker access and is a Swarm
  manager for `mhcs_core`; the app and database services are reachable through
  the established deployment paths.
- The established `server-setup-db.yml` and installed
  `/etc/madeena-mhcs_core-db-backup.sh` backup path are available as the
  existing prerequisite mechanism. This task may preserve and consume that
  mechanism but does not modify its environment scoping or other behavior.
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

- The Phase-A commit SHA is the exact reviewed application revision required
  for the three-schedule workflow. A missing, unaccepted, or mismatched Phase-A
  SHA is a safety failure, not permission to select the current pre-change
  runtime or another revision.
- The backup script's successful return is the repository-approved backup
  completion signal because it validates the compressed dump and uploads it
  through the established S3/MinIO mechanism; retention cleanup remains part
  of that existing path.
- The existing protected `production` Environment can carry the two fixed
  temporary secrets without placing private material in the repository
  workspace. Inventing a new secret system or infrastructure boundary is not
  authorized.
- The existing seeder and its synthetic regression tests remain the source of
  truth for fixture idempotency and application-level data validation.
- The Member-account preflight is an existence/invariant check only; it does
  not prove plaintext passwords and does not authorize credential changes.

### Remaining approval requirements

- Planner/Reviewer must review and accept Phase A before Phase B may be
  implemented against its SHA.
- Planner/Reviewer must review and accept Phase B before production execution
  is considered.
- A designated production/release authority must separately approve deployment
  of the Phase-A application revision and the final three-schedule
  `workflow_dispatch` run.
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

- Execute Phase A first and keep its write set limited to the seeder, its
  focused seeder regression, and a genuinely necessary existing runtime
  duplicate-booking regression. Commit Phase A separately and record its full
  SHA before beginning Phase B.
- Start Phase B from the Phase-A commit and keep its write set limited to the
  apply workflow, canonical verifier, and focused workflow tests. Hardcode the
  recorded Phase-A SHA in `EXPECTED_REVISION`; do not modify the seeder or
  Phase-A tests in Phase B.
- Reuse the existing `deploy-swarm.yml` self-hosted runner, service names,
  version evidence, environment boundary, backup convention, and concurrency
  group. Do not create an unrelated deployment mechanism or use direct SSH.
- Keep `server-setup-db.yml` and its established backup behavior unchanged;
  it is a read-only dependency, not an implementation target. Preserve its DB
  grants, `FLUSH PRIVILEGES`, application-access checks, backup paths,
  S3/MinIO upload, validation, retention, and cron controls.
- The eventual post-apply canonical verifier MUST preserve
  `prestige_user_accounts=37`, `prestige_linked_members=37`,
  `prestige_active_accounts=37`, `prestige_login_enabled_accounts=37`, and
  `prestige_member_linkage_exact=true` in its independent Member check.
- Keep `verify-production.yml` `workflow_dispatch`-only and read-only. Preserve
  the optional `verify_prestige_members` boolean input and its aggregate
  account query. Identify candidates by the fixed
  `@prestige.madeena-xray.com` namespace and Member linkage, not bookings.
  Emit only the required counts and booleans; never inspect password hashes or
  print credentials or identity fields. Its `verify_prestige` check MUST
  require the exact three windows, 37/37/37 quota, 37/37/37 confirmed counts,
  111 total bookings, 37 distinct Members, and equality of all three Member
  sets.
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
  option. The only seeder logic change permitted is the bounded three-window
  schedule/booking fixture reconciliation. Do not weaken its production guard,
  alter `.env`, reset or recreate Members, or run migrations, arbitrary
  Artisan commands, or another seeder as part of the data operation.
- Keep post-seed checks read-only and compare Member sets in memory without
  printing identities. Fail if any expected three-schedule window, quota,
  confirmed count, total, distinct-member, or all-set-equality invariant is not
  observed.
- For the later reviewed operational sequence, deploy the accepted Phase-A
  SHA first, run Member preflight with `expected_revision=<PHASE_A_SHA>`,
  `verify_prestige_members=true`, and `verify_prestige=false`, require the
  established backup prerequisite, then run the separately approved Phase-B
  three-schedule apply. After it succeeds, run the canonical verifier with
  `expected_revision=<PHASE_A_SHA>`, `verify_prestige=true`, and
  `verify_prestige_members=true`. Do not combine backup-prerequisite
  establishment with the Prestige apply; the apply's own backup remains
  immediately before the seeder, and do not retry the seed until the required
  checks pass.
- Preserve normal runtime booking behavior: the ordinary one-active-booking
  rule MUST continue to reject duplicate active bookings where currently
  required. The fixture/seeder exception is not permission to weaken the
  normal booking service.
- Do not add a dependency merely to parse or run the workflow. Use existing
  repository tooling or an already-installed validator; if a required validator
  is unavailable, stop or use a focused repository-native static test rather
  than installing one without approval.

## Acceptance criteria

- [ ] `.github/workflows/apply-prestige-production-data.yml` exists and has
  only `workflow_dispatch`; it has no push, pull-request, schedule, or cron
  trigger and is not a generic seeder/command workflow.
- [ ] The workflow requires the exact confirmation phrase
  `APPLY-PRESTIGE-2026-08-20-28`; invalid or missing confirmation, including
  `APPLY-PRESTIGE-2026-08-27-28`, fails before any production mutation job or
  database command can run.
- [ ] The workflow uses concurrency group
  `production-deployment-mhcs_core` with `cancel-in-progress: false`, so it
  cannot intentionally overlap `deploy-swarm.yml`.
- [ ] Phase A is a separate commit containing only the bounded seeder and
  permitted Phase-A tests; its exact full SHA is recorded and subsequently
  accepted by Planner/Reviewer before Phase B begins.
- [ ] Phase B starts from Phase A, is a separate commit containing only the
  permitted workflows and workflow tests, and hardcodes
  `EXPECTED_REVISION="<PHASE_A_SHA>"`. The apply workflow rejects the current
  pre-change runtime `4488f37787bc521869a2bb6113507387c5a983c8` for the
  expanded mutation.
- [ ] Before backup or seed, the Phase-B workflow proves that healthy
  production `mhcs_core_app` is exactly the accepted Phase-A revision using
  established runtime version evidence and fails closed on missing, unknown,
  or mismatched evidence.
- [ ] `PrestigeClinicSeeder` configures exactly three schedules: one
  20–26 August interval and the unchanged 27 August and 28 August intervals,
  using the exact closed-open Asia/Jakarta and UTC boundaries in this task.
  It reuses existing Members and matching records, creates missing bookings
  only, remains idempotent, and does not reset Member passwords.
- [ ] `verify-production.yml` has a `verify_prestige_members` boolean input
  with `required: false` and `default: false`; false emits
  `PRESTIGE_MEMBER_VERIFICATION=skipped`.
- [ ] With `verify_prestige_members=true`, the canonical verifier performs
  only a read-only aggregate query that independently identifies the fixed
  `@prestige.madeena-xray.com` cohort and fails unless the sanitized results
  are `prestige_user_accounts=37`, `prestige_linked_members=37`,
  `prestige_active_accounts=37`, `prestige_login_enabled_accounts=37`, and
  `prestige_member_linkage_exact=true`, with one unique Member linkage per
  user, active status, and login enabled.
  It emits no PII, credentials, or password/hash data and reports the pass or
  failed marker.
- [ ] The Member-account verification is independent from `verify_prestige`,
  so `verify_prestige_members=true` with `verify_prestige=false` is supported;
  existing generic verification remains intact. After a successful apply,
  canonical verification requires both inputs true.
- [ ] The canonical `verify_prestige` check uses the exact same sanitized
  three-schedule post-apply invariant as the apply workflow:
  `schedule_count=3`, exact 20–26/27/28 UTC bounds, quota 37/37/37,
  confirmed 37/37/37, `total_bookings=111`, `distinct_members=37`, and all
  three Member sets equal, with no Member identifiers emitted.
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
  Prestige site exists, exactly the three intended schedules exist, all three
  UTC bounds match, each quota is 37, each schedule has 37 confirmed bookings,
  total bookings are 111, distinct Members are 37, and all three schedules
  contain the identical Member set.
- [ ] The accepted full-day local intervals, UTC storage convention, ordinary
  Member one-active-booking invariant, seeder guard/validation/idempotency,
  existing-Member reuse, no-password-reset rule, and obsolete-schedule
  downstream-data protection remain enforced.
- [ ] Existing Member credentials are reused with NIK login semantics; no
  Member password reset, recreation, migration, login-semantic change, or
  password/hash inspection is introduced.
- [ ] After implementation and independent review, operational order is:
  separately authorized Phase-A deployment → canonical Member preflight with
  `expected_revision=<PHASE_A_SHA>` and `verify_prestige_members=true` /
  `verify_prestige=false` → established backup-prerequisite verification →
  separately authorized Phase-B apply → mandatory apply backup immediately
  before seeding → canonical verification with
  `expected_revision=<PHASE_A_SHA>`, `verify_prestige=true`, and
  `verify_prestige_members=true`.
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
  the exact new confirmation phrase, rejection of the old phrase, and the
  sanitized three-schedule verification contract. It must also prove that
  `server-setup-db.yml` and its backup/grant/S3/MinIO/retention safety remain
  untouched by this task, plus the `verify_prestige_members` input, skipped
  path, read-only aggregate path, independent cohort identification,
  `37/37/37/37` counts, no-PII/no-hash contract, exact three-schedule
  `verify_prestige` invariant, and required both-check post-apply contract.
3. Run `vendor/bin/phpunit tests/Feature/Operator/PrestigeClinicSeederTest.php`
   and the focused deployment/workflow tests changed by the implementation.
   Tests MUST use generated synthetic CSV data only. Seeder coverage MUST
   assert exact local/UTC windows, 37/37/37 quotas and confirmations, 111
   bookings, 37 distinct Members, identical Member sets, idempotent rerun,
   existing Member reuse, and unchanged normal duplicate-active-booking
   rejection.
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
9. Validate the revised task contract itself: version `1.5`,
   `last_updated: 2026-08-20`, implementation baseline
   `12dd1ccd0763d48fd581fe3dec9eb53f5a794c05`, current pre-change production
   runtime `4488f37787bc521869a2bb6113507387c5a983c8`, the accepted Phase-A
   SHA as the three-schedule runtime, and Phase B's hardcoded
   `EXPECTED_REVISION` gate rejecting the old runtime. Validate the exact
   20–26/27/28 local and UTC windows, 3 schedules, 37/37/37 quotas and
   confirmations, 111 total bookings, 37 distinct Members, identical Member
   sets, the new confirmation phrase, rejection of the old phrase, mandatory
   backup inside the apply workflow immediately before the seeder, unchanged
   `server-setup-db.yml`, preserved credential/privacy safeguards, unchanged
   normal runtime duplicate-booking behavior, no Member credential changes,
   no production authorization, and `git diff --check`.
10. Run `git diff --check` and confirm the task revision is the only changed
    repository file during this republication.

### Required evidence

The Executor MUST report the exact task revision, implementation baseline,
current pre-change runtime, Phase-A and Phase-B implementation SHAs when
implementation occurs, changed files, static/workflow test output, all
commands actually run, observed results, any warnings or tests not run, and
the Mvp14/browser limitation if applicable. Production execution evidence is not
expected and must not be fabricated. Any workflow-run evidence later supplied
must contain
only sanitized operational summaries and must identify the exact approved
workflow revision and production version gate result.

## Stop conditions

Stop and return to Planner/Reviewer if:

- the current checkout is dirty, the accepted baseline is not the declared
  immutable revision, or overlapping unreviewed deployment changes exist;
- Phase A is not a distinct committed application revision, its full SHA is
  not recorded, or Planner/Reviewer has not accepted it before Phase B begins;
- Phase B does not start from the accepted Phase-A commit, does not form a
  distinct second commit, broadens either phase's write set, or leaves
  `EXPECTED_REVISION` as a placeholder or mutable reference;
- the workflow cannot prove the exact accepted Phase-A production revision
  before the backup/seed path, or accepts the current pre-change runtime
  `4488f37787bc521869a2bb6113507387c5a983c8` for the expanded mutation;
- the established backup script is unavailable, fails, or cannot verify the
  backup without inventing a new backup mechanism;
- the existing protected `production` Environment or either fixed temporary
  secret is unavailable at the separately approved operational attempt;
- the established backup mechanism, its validation, S3/MinIO upload,
  retention, or protected inputs cannot be verified without changing
  `server-setup-db.yml` or inventing a new backup path;
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
- the task would require application changes outside the bounded
  `PrestigeClinicSeeder` schedule/booking fixture update, deployment
  concurrency changes, database schema changes, or an unapproved change to
  accepted Prestige semantics;
- workflow logs could expose employee PII, credentials, CSV contents, or
  Member identity data;
- the post-seed invariant query cannot prove the exact three schedules/counts,
  exact windows, or all-three same-member-set invariant without printing
  identities;
- normal runtime duplicate-active-booking behavior would need to be weakened
  to satisfy the fixture's three-schedule booking contract;
- a production trigger, live database mutation, deployment, release, or other
  external side effect would be required; or
- implementation reveals a new authority, architecture, security, privacy,
  data-integrity, or operational decision not covered by this task.

The Executor MUST NOT silently weaken a failed safety gate or reinterpret this
task as permission to run production operations.

## Side-effect authorization

### Explicitly authorized

- Publish this task and its immutable Git revision.
- Implement only the bounded changes required by this task in two distinct
  commits: Phase A for the seeder and its permitted tests, then Phase B from
  the accepted Phase-A commit for the dedicated apply workflow, canonical
  read-only Member/schedule verifier, and permitted workflow tests. Do not
  change `server-setup-db.yml`.
- Run local syntax, static, test, build, format, diff, and privacy checks using
  synthetic data only.
- Commit Phase A separately, record its exact full SHA as
  `THREE_SCHEDULE_APP_REVISION=<PHASE_A_SHA>`, then commit Phase B separately
  with the apply workflow hardcoded to that SHA. These implementation commits
  are distinct from this task republication and do not authorize deployment or
  production execution.
- Commit and push this task revision only during task republication.
- After implementation and independent review, run the canonical read-only
  Member preflight, verify the established backup prerequisite, and run the
  canonical read-only verifier in the exact order defined by this task. These
  operations remain separate from the later three-schedule Prestige seed
  approval.
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

Return two reviewable implementation revisions: a Phase-A application commit
and a Phase-B workflow/verifier commit pinned to the exact accepted Phase-A
SHA, each with truthful local verification evidence and bounded changed-file
scope. Planner/Reviewer must separately accept both phases before any
prerequisite execution or production seed decision.

### Planning Required

Use when a secure runtime-source, exact-version, backup, authorization,
architecture, or operational dependency cannot be satisfied without new
authority. Do not publish a nominal implementation or bypass the failed gate.

Acceptance of this workflow task is not production execution or release
authorization.
