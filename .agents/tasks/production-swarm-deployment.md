---
title: Controlled MHCS Core Production Swarm Deployment
document_id: MHCS-TASK-PRODUCTION-SWARM-DEPLOYMENT-001
version: 1.2
status: validated-on-publication
language: en-US
last_updated: 2026-08-14
scope:
  - production deployment packaging based on Madeena deploy-templates
  - Docker Swarm release to the MHCS Core production server
  - pre-deploy backup and migration safety gates
  - MHCS-specific queue, storage, MPIPS, security, and runtime configuration
  - post-deploy health verification and bounded rollback
  - one-time current MVP synthetic production bootstrap on a provably fresh database
  - protected credential-server.txt handoff to the designated owner
authority_note: This task authorizes a controlled production infrastructure deployment of MHCS Core only after this exact task content is immutably published and all release preconditions below pass. It additionally authorizes one bounded first-production bootstrap using the repository-current MVP seeders on a provably fresh production database, plus a private credential-server.txt handoff to the designated owner. It does not authorize real-member or clinical data import, destructive database reset, uncontrolled repeat seeding, public credential disclosure, MPIPS deployment, storage-policy changes, privacy-policy closure, or broader clinical go-live claims.
---

# Executable Task

## Task identity

**Task title:**  
`Controlled MHCS Core Production Swarm Deployment`

**Task path:**  
`.agents/tasks/production-swarm-deployment.md`

**Task contract state:**  
`Validated/Published upon immutable publication of this exact content; the governing task SHA must be supplied to the Executor before execution.`

**Delivery objective / Work Package / MVP:**  
`MVP-09 / Release Gate — controlled MHCS Core production-server deployment`

**Owner / designated planning authority:**  
`Faliq Adlan, CTO`

## Delivery context

MHCS Core already contains production-oriented Docker assets derived from the Madeena production deployment templates, but the repository does not yet contain the standard Docker Swarm deployment workflows and the current production specialization must be reconciled before a safe release.

The deployment reference is the immutable Madeena template revision:

`Madeena-software/deploy-templates @ 569a30d4a089b0ee404ed6e963fdd2dfd96d3787`

specifically:

- `templates/prod/README.md`;
- `templates/prod/standard-deploy-swarm.yml`;
- `templates/prod/standard-server-setup.yml`;
- `templates/prod/standard-server-setup-db.yml`;
- `templates/prod/standard-download-backup.yml` when needed for backup verification/recovery;
- `templates/prod/standard-docker-compose.prod.yml`;
- `templates/prod/standard-dockerfile`;
- `templates/prod/standard-dockerignore`;
- `templates/prod/standard-entrypoint.sh`;
- `templates/prod/standard-nginx.conf`;
- `templates/prod/standard-php.ini`;
- `templates/prod/standard-supervisord.conf`; and
- `templates/prod/validate-boilerplate.sh` where applicable.

The template is a reference implementation, not authority to overwrite MHCS-specific architecture. MHCS-specific queue topology, Image Gateway isolation, private S3 storage, MPIPS boundary, scheduler role, security configuration, and repository governance remain controlling.

Two current production-specialization defects are known and MUST be addressed before deployment:

1. `docker-compose.prod.yml` currently starts the Image Gateway worker with `--queue=image`, while production code dispatches `ProcessCaptureSet` jobs to `image-gateway`. The dedicated production worker MUST consume `image-gateway`.
2. The generic deployment template runs `php artisan migrate --force --seed`, while the current MHCS `DatabaseSeeder` creates only `test@example.com` and is not the intended MHCS beta bootstrap. For a provably fresh first production deployment, this task explicitly authorizes the repository-current `Database\Seeders\MvpCoreClinicSeeder` chain instead. Normal subsequent deployments run `php artisan migrate --force` without seeding.
3. The repository-current MVP seeders and `Mvp03PointService::creditPersonalForLocalTesting()` are intentionally restricted to `local/testing`, and `MvpCredentialFile` writes only in `local`. A bounded production-bootstrap gate is therefore required so the exact current MVP seed flow can run in production only when an explicit one-shot authorization flag is supplied.
4. The current `MvpMemberSeeder` generates Member passwords but does not write them to a credential report, while the existing local-only `MvpCredentialFile` targets `credential.txt`. The production-bootstrap specialization MUST keep local `credential.txt` behavior unchanged and write all newly generated production-bootstrap credentials to the separate protected `credential-server.txt` report.

## Baseline and task revision

**Implementation baseline:**  
`a6ca0c15bc0d827abac7f016c9b5ebeec57b5255`

This is the latest verified `main` revision inspected during planning on 14 August 2026.

**Deployment-template baseline:**  
`Madeena-software/deploy-templates @ 569a30d4a089b0ee404ed6e963fdd2dfd96d3787`

**Task revision:**  
`The full SHA of the commit containing this exact task content, supplied after publication.`

Before material work, verify that the task-publication commit descends from the implementation baseline and that no unrelated implementation has been silently added between the baseline and task publication.

Do not silently move either baseline.

## Objective

Reconcile the MHCS Core production deployment package with the pinned Madeena Docker Swarm production templates, preserve all MHCS-specific architecture and security boundaries, publish only the bounded deployment changes required by this task, and perform one controlled production-server deployment with verified database safety, immutable release identity, healthy Swarm services, a functioning dedicated `image-gateway` worker, one authorized current-MVP synthetic bootstrap when the production database is provably fresh, a protected `credential-server.txt` owner handoff, and sanitized deployment evidence.

The deployment is an infrastructure/application-runtime release. It is not authorization to import real Member data or declare unrestricted clinical production readiness.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` — repository AI delivery contract and side-effect boundaries.
- `.agents/software-workflow.md` — normative delivery protocol and separate Release Gate.
- `.agents/tasks/_template.md` — executable task contract.
- `.agents/context/project.md` — MHCS Core architecture and repository context.
- `.agents/context/modules/image-gateway/project.md` — Image Gateway ownership, private object storage, and worker-only MPIPS boundary.
- `docs/mvp/beta-gap-register.md` — production deployment/release gaps and preserved open policy gaps.
- `.env.example` and `config/mhcs.php` — runtime variable names and fail-closed production configuration requirements.
- `Dockerfile`, `docker-compose.prod.yml`, `.dockerignore`, `docker/entrypoint.sh`, `docker/php.ini`, `docker/nginx.conf`, and `docker/supervisord.conf` — current MHCS production specialization.
- `app/Modules/ImageGateway/Application/Services/ImageGatewayCaptureService.php` and `app/Modules/ImageGateway/Application/Jobs/ProcessCaptureSet.php` — current `image-gateway` dispatch and worker timeout behavior.
- `database/seeders/MvpAdminSeeder.php`, `MvpMemberSeeder.php`, `MvpBookingSeeder.php`, `MvpOperatorSeeder.php`, `MvpCoreClinicSeeder.php`, and `MvpCredentialFile.php` — current synthetic beta bootstrap chain and credential-file mechanism.
- `app/Modules/Member/Application/Services/Mvp03PointService.php` — current synthetic point-credit guard required by the MVP seed chain.
- `Madeena-software/deploy-templates/templates/prod/** @ 569a30d4a089b0ee404ed6e963fdd2dfd96d3787` — reusable production deployment reference.
- The human instruction publishing and executing this task — authorization for the bounded production deployment side effects defined below.

### Requirement traceability

- `MVP-GAP-022` → production deployment requires an explicit controlled deployment decision and observed release evidence.
- `MVP-GAP-023` → CI/release/deployment evidence must be observed and must not be overstated.
- `MVP-GAP-019` → production object-storage policy MUST NOT be silently declared closed by this deployment.
- `MVP-GAP-021` → privacy, retention, deletion, and anonymization policy MUST NOT be silently declared closed by this deployment.
- `MVP-GAP-020` → this owner-authorized synthetic bootstrap may provide a one-time protected credential handoff for the seeded beta accounts; it does not establish the general production credential-delivery process.
- Image Gateway approved architecture → private S3 objects, dedicated queue worker, and worker-only private MPIPS transport.
- Current implementation → capture processing jobs are queued on `image-gateway` and require the dedicated production worker to consume that exact queue.

## Scope

### In scope

- Inspect and reconcile the current MHCS deployment files against the pinned `deploy-templates/templates/prod` reference.
- Add or specialize the standard GitHub Actions workflows required for MHCS production deployment, normally:
  - `.github/workflows/deploy-swarm.yml`;
  - `.github/workflows/server-setup-deploy.yml`;
  - `.github/workflows/server-setup-db.yml`; and
  - `.github/workflows/download-backup.yml` only when useful for the approved backup/recovery path.
- Preserve existing MHCS Docker specializations rather than blindly copying the generic template.
- Correct the dedicated Image Gateway production worker so it consumes only the `image-gateway` queue.
- Preserve a separate normal queue worker, scheduler, application, database, reverse-proxy, and any currently justified cache role.
- Ensure only the Image Gateway worker joins the approved private MPIPS network unless current approved architecture explicitly requires another service.
- Ensure production environment generation covers all current fail-closed MHCS runtime requirements using secret/variable names only. Never print or persist secret values in repository files, evidence, chat output, or logs beyond the secret-managed production environment file.
- Require and validate at minimum, as applicable to the current configuration:
  - `APP_KEY`, `APP_DOMAIN`, `REMOTE_PATH`, `SSH_USER`;
  - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`;
  - approved S3/MinIO access key, secret, bucket, endpoint, and region/path-style configuration;
  - `MHCS_IDENTIFIER_KEY`;
  - `MHCS_ACCESS_GRANT_KEY`;
  - `MHCS_MANIFEST_KEY`;
  - `MHCS_MANIFEST_KEY_ID`;
  - approved asset-grant TTL/audiences;
  - approved production login-throttling values;
  - `MHCS_MAX_UPLOAD_MB`;
  - all required production image-policy values from `config/mhcs.php`;
  - `MHCS_IMAGE_WORKER_CPU_LIMIT`;
  - `MHCS_IMAGE_WORKER_MEMORY_LIMIT`;
  - `MHCS_IMAGE_WORKER_PIDS_LIMIT`;
  - `MHCS_IMAGE_WORKER_EXECUTION_TIMEOUT_SECONDS`;
  - `MHCS_IMAGE_WORKER_TMPFS_SIZE`;
  - `MHCS_PRIVATE_OBJECT_DISK=s3`;
  - approved `MPIPS_BASE_URL` on the private deployment network;
  - `MPIPS_API_KEY`;
  - `MPIPS_TIMEOUT_SECONDS`;
  - `IMAGE_GATEWAY_WORKER_TIMEOUT`;
  - `DB_QUEUE_RETRY_AFTER`; and
  - `MPIPS_NETWORK_NAME` or the repository-equivalent external network selector.
- Fail closed if required production configuration is absent. It is acceptable to check secret/variable **names and presence**; do not retrieve or display secret values.
- Set the deployed application to `APP_ENV=production`, `APP_DEBUG=false`, Indonesian locale, database queue/session/cache behavior as currently approved, and a production-safe log level.
- Ensure the deployment environment file on the server is readable only by the deployment/runtime authority required by the chosen topology; target mode `0600` when compatible with the runner/runtime ownership model.
- Use an immutable application image tag tied to the implementation/release commit, not `latest` as the only release identity. `latest` MAY exist as a convenience alias, but the deployed Swarm service MUST remain traceable to an immutable commit-derived tag.
- Preserve or implement Swarm health checks, update/rollback policy, resource constraints, persistent storage, and start-first/stop-first behavior appropriate to each MHCS service.
- Run repository tests and deployment validation before production mutation.
- Inspect all pending database migrations that would execute in production. Stop before deployment if any pending migration is destructive, irreversible, or incompatible with safe rollback and no approved release decision covers it.
- Before any migration on an existing production database, create and verify a restorable backup using the approved Madeena backup mechanism or an already-established equivalent. Do not continue on backup failure.
- If this is provably the first deployment and the target database/storage is new and contains no pre-existing application data, record that fact as observed evidence rather than fabricating a backup requirement for nonexistent data. Never assume a database is new merely because the stack is absent.
- Run `php artisan migrate --force` for schema changes. Do not use generic `migrate --seed` or the default `DatabaseSeeder` as the production bootstrap.
- When and only when the target production database is **provably fresh for MHCS application data**, run the repository-current `Database\Seeders\MvpCoreClinicSeeder` exactly once after migration. This is an explicitly authorized synthetic production bootstrap so the controlled production environment is not empty.
- Implement the smallest production-bootstrap gate needed for the current seed chain. Production execution MUST require both `APP_ENV=production` and an explicit one-shot flag such as `MHCS_ALLOW_PRODUCTION_MVP_SEED=true`. The flag MUST be supplied only to the seed command and MUST NOT be persisted in the normal production `.env`. Local/testing behavior remains unchanged.
- Extend only the environment guards necessary for the current seed chain to honor that explicit one-shot production-bootstrap gate, including the guards in the MVP seeders and `Mvp03PointService::creditPersonalForLocalTesting()`. Do not generalize synthetic funding or seeding for ordinary production runtime use.
- Extend `MvpCredentialFile` so the authorized production bootstrap writes `credential-server.txt` to an explicitly supplied writable private path (for example `storage/app/private/bootstrap/credential-server.txt`) with mode `0600`; create its private parent directory with mode `0700` when absent. Preserve the existing local `credential.txt` behavior unchanged. Add `credential-server.txt` to repository ignore rules as defense in depth even though the production file is expected under private storage. The production credential path MUST NOT be web-served.
- Extend `MvpMemberSeeder` so every newly created synthetic Member password is appended to the same credential file. A fresh full bootstrap is expected to report the primary admin/operator account, five synthetic Members, and the two additional synthetic Operators: eight unique login accounts in total. The existing synthetic identity/profile objects created by the Member seeder remain stored through the configured production `PrivateObjectStore` and therefore use the approved private S3 boundary.
- The production bootstrap command MUST use the explicit class, for example `php artisan db:seed --class='Database\Seeders\MvpCoreClinicSeeder' --force`, under the one-shot gate. Do not run `migrate:fresh`, truncate, drop, or destructive reset operations.
- Do not automatically rerun the production bootstrap on subsequent deployments. If the database is not provably fresh, migration may proceed but the seed step MUST be skipped unless a separately reviewed remediation explicitly authorizes reconciliation.
- Treat `credential-server.txt` as a first-bootstrap output. If a seeded account already exists, do not rotate or regenerate its password merely to reconstruct a missing credential file. If the credential file is unavailable after the first bootstrap, report that recovery/reissue requires a separate credential-remediation decision.
- Run the one-time deploy-permissions workflow only when its state is not already satisfied.
- Install/verify the automated database backup workflow where appropriate before treating the environment as operationally ready.
- Deploy the final bounded release through the repository's GitHub Actions/self-hosted-runner Docker Swarm mechanism.
- Perform non-destructive production smoke verification after deployment.
- If service health or smoke verification fails, perform a bounded application/service rollback to the immediately previous immutable image/stack state when technically safe. Do not reverse database migrations unless their down-path safety is separately proven and authorized.
- Write sanitized release evidence to `docs/mvp/evidence/production-swarm-deployment.md` or the nearest existing repository-conventional production-release evidence path.
- Update `docs/mvp/beta-gap-register.md` only when observed evidence justifies an exact status change. A successful controlled deployment MAY close the deployment-approval/deployment-execution portion of `MVP-GAP-022`; it MUST NOT silently close `MVP-GAP-019`, `MVP-GAP-021`, or broader `MVP-GAP-023` requirements that remain unproven.

### Out of scope

- Application feature changes, UI changes, domain behavior changes, or unrelated refactoring.
- Changes to Member, Operator, Doctor, or Image Gateway business logic except the narrowly authorized production-seed environment guard in `Mvp03PointService`; no normal production booking, point, authorization, queue, or clinical behavior may change.
- Deploying, rebuilding, reconfiguring, or modifying the separate `mpips` repository/service.
- Sending a real radiograph, gain NPZ, DICOM, Member record, or clinical payload to MPIPS as a deployment test.
- Real Member, operator, staff, clinic, or historical-data import. The authorized production bootstrap contains only the repository-current synthetic MVP data.
- The deferred 37-member or B2B import flow.
- Generic production `DatabaseSeeder` execution, uncontrolled demo/test seeding, or synthetic account creation outside the explicitly authorized one-time `MvpCoreClinicSeeder` production bootstrap.
- `migrate:fresh`, database drop/recreate, volume deletion, object-store deletion, bucket recreation, stack removal as a shortcut, or any destructive reset.
- Secret creation, secret rotation, key replacement, IAM-policy changes, S3 bucket-policy changes, firewall changes, DNS changes, TLS issuance, or MPIPS credential changes unless separately authorized. The task-created synthetic account passwords in `credential-server.txt` are bootstrap outputs, not infrastructure-secret changes.
- Closing unresolved privacy/retention/deletion/anonymization policy merely because deployment succeeds.
- Claiming production clinical go-live, regulatory readiness, privacy approval, or full beta completion unless those claims are independently supported by approved authority and evidence.
- Bypassing branch protection, required review, GitHub environment approval, runner policy, or infrastructure approval.
- Force push, history rewrite, broad process kill, broad Docker prune of running resources, volume prune, or destructive host cleanup.

### Preserved behavior

- MHCS remains one modular application with Member, Operator, Doctor, and Image Gateway modules.
- Image Gateway remains the owner of binary storage metadata, processing state, and controlled file access.
- Raw radiograph/gain NPZ remains private and is never exposed directly to browsers.
- The dedicated Image Gateway worker is the only MHCS process allowed to cross the private MPIPS conversion boundary.
- Production private objects remain on the approved private S3-compatible store with opaque keys and authorization/integrity controls.
- Existing authorization, audit, idempotency, storage, queue, and domain behavior remains unchanged.
- The ordinary queue worker MUST NOT be used as a substitute for the dedicated Image Gateway worker.
- Existing accepted migrations and application data MUST be preserved.
- Normal production runtime MUST remain unable to invoke the synthetic seed path without the explicit one-shot production-bootstrap flag.
- `credential-server.txt` is a production-bootstrap secret handoff, separate from local `credential.txt`: it remains ignored, uncommitted, non-public, permission-restricted, and excluded from normal logs/artifacts.
- Existing-account credentials are never silently changed on a rerun; the complete credential report is guaranteed only for the initial fresh bootstrap where all eight accounts are newly created in the same controlled execution.
- Existing production data, if any, MUST survive deployment and application rollback.

## Dependencies and assumptions

### Dependencies

- `mhcs-core` implementation baseline `a6ca0c15bc0d827abac7f016c9b5ebeec57b5255`.
- This exact task published at an immutable repository revision.
- `Madeena-software/deploy-templates @ 569a30d4a089b0ee404ed6e963fdd2dfd96d3787`.
- A functioning self-hosted GitHub Actions runner associated with the intended production Docker Swarm manager, or an equivalent repository-approved execution route.
- Docker Swarm active on the target deployment manager.
- A dedicated `REMOTE_PATH`, never a shared root such as `/var/www`, `/srv`, `/opt`, or `/var`.
- Production MySQL 8.4 storage and credentials.
- Approved private S3/MinIO-compatible object storage and credentials.
- The approved external private MPIPS Docker network and already-deployed MPIPS service.
- Existing DNS/TLS/reverse-proxy route for `APP_DOMAIN`, unless a repository-approved upstream proxy already maps the configured application port.

### Approved assumptions

- The Madeena production template is the reusable deployment reference, while MHCS-specific deployment architecture may legitimately extend it with queue, scheduler, Image Gateway worker, private network, and security variables.
- A configuration or workflow change required solely to make the existing approved MHCS runtime deployable is part of this bounded production-deployment objective.
- The task may use repository/GitHub secret **names and presence checks** as evidence without reading secret values.
- The production deployment is a controlled infrastructure/application release. On the first provably fresh production database, the owner explicitly wants the repository-current synthetic MVP clinic dataset seeded so the deployed environment is immediately usable for controlled testing.
- The protected `credential-server.txt` generated by that first bootstrap is intended to be handed directly to the designated owner.

### Remaining approval requirements

- This task's publication and execution by the designated owner authorizes the bounded production deployment side effects listed below.
- If GitHub branch protection, GitHub Environment protection, server sudo policy, storage/IAM policy, or infrastructure policy requires an additional human approval, stop at that approval gate. Do not bypass it.
- Any required new privacy, retention, deletion, anonymization, IAM, storage-policy, DNS, TLS, or MPIPS architecture decision remains outside this task and must return to planning.

## Required capabilities

**Required capabilities:**

- repository read;
- repository write limited to the deployment/release surfaces authorized below;
- Git inspection and bounded commit/push capability;
- shell command execution;
- PHP/Composer/Node test and build execution;
- Docker image build and Docker Compose/Swarm validation;
- GitHub repository and GitHub Actions workflow inspection/dispatch;
- access to the configured self-hosted runner and production Swarm through the existing deployment mechanism;
- non-destructive HTTPS/TCP smoke checks to approved production endpoints;
- sanitized evidence writing;
- protected credential-file handling and private owner handoff.

The task does not require reading secret values from GitHub Secrets or disclosing the server environment file.

## Execution constraints

### Constraints

- Start from the exact implementation baseline plus the immutable task-publication commit only. Preserve unrelated work and stop on overlapping changes.
- Read `.agents/AGENTS.md`, the exact governing task revision, the referenced architecture/context, and the pinned template files before changing deployment configuration.
- Do not copy the generic template verbatim when it conflicts with MHCS architecture.
- Do not weaken fail-closed production configuration from `config/mhcs.php` merely to make deployment pass.
- Do not add defaults for production-only secret or safety values when the application intentionally requires explicit injection.
- Do not print `.env`, GitHub secret values, database passwords, S3 keys, MPIPS API keys, MHCS security keys, or generated credentials.
- Do not use `set -x` in secret-bearing workflow steps.
- Do not write secrets to repository artifacts, Actions artifacts, test fixtures, screenshots, or sanitized evidence. `credential-server.txt` is the sole task-authorized credential handoff artifact and MUST remain outside Git/versioned evidence.
- Do not run generic or automatic production seeders. The only authorized production seed is one explicit `MvpCoreClinicSeeder` bootstrap on a provably fresh database under the one-shot production-seed gate.
- Do not deploy using an image that cannot be traced to an immutable commit.
- Do not deploy if required pre-deploy tests, Docker validation, backup gate, migration inspection, or runtime-configuration presence checks fail.
- Do not treat a successful container start as proof of the Image Gateway path; verify the `image-gateway` worker command/queue binding separately.
- Do not send clinical payloads to prove MPIPS connectivity. Network existence/readiness may be checked non-destructively without conversion input.
- Do not perform a database migration rollback as an automatic response to application rollback.
- Do not delete the existing stack or volumes to force a clean deployment.
- Apply repository reuse discipline and preserve current MHCS service boundaries.

## Execution procedure

1. **Resolve immutable task and repository identity.**
   - Confirm repository is `Madeena-software/mhcs-core`.
   - Record the full current HEAD, branch, task revision, and implementation baseline.
   - Confirm baseline ancestry.
   - Inspect staged, modified, untracked, and relevant ignored files.
   - Stop on unrelated or overlapping work that cannot be safely preserved.

2. **Load deployment authority and references.**
   - Read the repository AI contract and relevant project/Image Gateway context.
   - Read the current deployment files.
   - Read the pinned `deploy-templates/templates/prod` reference at `569a30d4a089b0ee404ed6e963fdd2dfd96d3787`.
   - Record material differences rather than assuming the generic template is directly applicable.

3. **Inspect current production/release state.**
   - Inspect `.github/workflows/**` and current Actions status for the target release revision.
   - Inspect current deployment workflows if any.
   - Using names/status only, determine whether required GitHub secret/variable names exist.
   - Determine whether the target production stack/database already exists.
   - Determine whether this is an existing-data deployment or provably fresh environment.
   - For a proposed first bootstrap, prove absence of existing MHCS application data before authorizing the seed step; do not infer freshness merely from an absent Swarm stack.
   - Determine whether the production node is an active Swarm manager.
   - Determine whether the approved external MPIPS network exists.
   - Do not mutate production during this step.

4. **Reconcile production deployment files.**
   - Create/specialize the required Madeena deployment workflows.
   - Preserve MHCS-specific Docker assets and only change them where necessary for safe Swarm deployment.
   - Correct `image-worker` to consume `image-gateway`.
   - Preserve the scheduler and current justified runtime roles.
   - Keep only the Image Gateway worker attached to the private MPIPS network.
   - Ensure environment generation contains all current MHCS production-required variables without hard-coded secret values.
   - Ensure server `.env` handling is non-logging and permission-restricted.
   - Remove generic `--seed` behavior from the MHCS deploy path.
   - Add a separate, explicit first-production bootstrap path for `MvpCoreClinicSeeder` guarded by a one-shot production-seed authorization flag.
   - Adapt only the current MVP seeder guards, the synthetic point-credit guard, credential writer, and missing Member credential append required to make that exact seed chain work safely in production.
   - Ensure the production credential output path is writable despite the read-only application filesystem and is permission-restricted to `0600`.
   - Use an immutable commit-derived image tag for the released stack.
   - Ensure rollback can identify the immediately previous immutable application image/stack state.

5. **Run pre-release repository verification.**
   - Run the smallest current repository-approved task/deployment validation if available.
   - Run the existing security-validation checks applicable to the changed deployment surface.
   - Run the full PHPUnit suite unless a repository-documented blocker exists; record exact counts/results.
   - Run JavaScript tests relevant to the production bundle when present.
   - Run `npm ci`/`npm run build` or the repository-equivalent deterministic frontend build path.
   - Build the production Docker image from the final release commit candidate.
   - Validate the final production Compose/Swarm configuration without printing secrets.
   - Run the pinned template's validation script when it is applicable after MHCS specialization; treat template-only assumptions as reference checks, not authority to break MHCS.

6. **Inspect production migrations before mutation.**
   - Identify migrations pending against the target production database without altering schema.
   - Inspect each pending migration for destructive drops, narrowing changes, irreversible transforms, long blocking operations, or incompatibility with the previous application release.
   - Stop if safe rollback/forward-only handling requires an unapproved decision.

7. **Establish backup/recovery readiness.**
   - For an existing production database, create a fresh pre-deploy backup using the approved Madeena backup mechanism or current equivalent.
   - Verify the backup is non-empty, structurally valid, and present in the approved private backup store without exposing contents or credentials.
   - Record only sanitized backup evidence such as timestamp, verification PASS, and approved storage class/prefix identity when non-sensitive.
   - If the environment is provably fresh, record why no pre-existing-data backup exists and do not destroy/recreate resources to make it appear fresh.

8. **Publish the bounded deployment implementation.**
   - Review the diff and ensure only authorized deployment/release/evidence surfaces changed.
   - Create one bounded commit for the deployment implementation if repository policy permits.
   - Push without force to the allowed branch.
   - Do not bypass required review or branch protection.
   - If a protected-branch merge or environment approval is required, stop in the correct approval state until that approval exists.

9. **Run one-time production server setup only if required.**
   - Dispatch the specialized deploy-permissions setup if not already satisfied.
   - Dispatch/install the database backup setup if required and technically applicable.
   - Verify the dedicated deploy root, persistent database path, application private storage/log paths, Docker access, and required permissions.
   - Do not broaden sudo permissions beyond the minimum template/repository-approved commands.

10. **Deploy the immutable release.**
    - Dispatch the MHCS `deploy-swarm.yml` workflow for the exact release revision.
    - Build/tag the image using the immutable release commit identity.
    - Validate required environment-variable presence without echoing values.
    - Confirm Swarm manager state and required external MPIPS network before stack mutation.
    - Deploy/update the stack using the MHCS-specialized Compose file.
    - Run `php artisan migrate --force` only after the backup/fresh-environment gate passes.
   - If and only if this is the provably fresh first MHCS production bootstrap, invoke `Database\Seeders\MvpCoreClinicSeeder` once with the explicit one-shot production-seed flag and an explicit private `credential-server.txt` path.
   - Do not persist the one-shot seed flag in `.env`; normal app/queue/scheduler/image-worker processes must start without it.
   - Do not invoke the default `DatabaseSeeder`.

11. **Verify production runtime.**
    - Verify expected replicas/tasks are running for database, application, normal queue, scheduler, Image Gateway worker, Nginx/reverse proxy, and any currently justified cache service.
    - Verify application and database health checks.
    - Inspect the actual `image-worker` command and prove it consumes `image-gateway`.
    - Verify the image-worker has the intended resource limits and private MPIPS network attachment.
    - Run a no-secret config assertion inside the application that returns PASS/FAIL only for:
      - production environment;
      - debug disabled;
      - private object disk `s3`;
      - required MHCS security keys present;
      - approved asset-grant settings present;
      - approved login-throttling values present;
      - all fail-closed image-policy values present;
      - MPIPS API key present;
      - MPIPS base URL not loopback in production;
      - Image Gateway worker timeout present;
      - database queue retry-after greater than the worker execution timeout; and
      - queue connection set to the approved production queue backend.
    - Confirm migrations are current.
    - When first-bootstrap seeding was authorized, verify without printing passwords that the expected synthetic admin/operator, five Members, two additional Operators, synthetic site/schedules, and current seeded clinic-flow records exist as produced by `MvpCoreClinicSeeder`.
    - Verify the production one-shot seed flag is absent from the normal runtime environment after seeding.
    - Verify the protected credential file exists, is mode `0600`, is outside any web-served path, and contains credential entries for all eight unique freshly generated login accounts.
    - Perform HTTPS smoke verification against the approved public health endpoint such as `/up` and expect HTTP 200.
    - Verify the application domain resolves through the existing production ingress path.
    - Do not log in with a real user or upload clinical data as part of this task.

12. **Rollback on bounded runtime failure.**
    - If the new application/service release fails health or smoke verification, restore the immediately previous immutable application image/Swarm service state when safe.
    - Preserve the database and persistent storage.
    - Do not automatically reverse migrations.
    - If migrations make application rollback unsafe, stop, preserve evidence, and return `Planning Required` rather than improvising destructive recovery.

13. **Perform protected credential handoff when seeding occurred.**
    - Read `credential-server.txt` only after successful seeding and permission verification.
    - Do **not** echo it in GitHub Actions, upload it as an Actions artifact, write it into repository evidence, commit it, or include it in a PR/comment.
    - In the Antigravity Executor's final private response to the designated owner, include a clearly marked **Sensitive credential handoff — credential-server.txt** section containing the exact file contents. This disclosure is explicitly authorized by the owner for this task.
    - Also report the protected server-side file path and mode, but do not expose unrelated `.env` or infrastructure secrets.
    - If the runtime cannot return the credential file privately without placing it in shared/public logs or artifacts, do not disclose it through that channel; leave the file secured at the approved server path and report only that a direct secure handoff is required.

14. **Record sanitized evidence.**
    - Record task revision, release commit, template revision, Actions run identity, backup gate result, migration result, service health, image-worker queue binding, public health result, rollback status, tests actually run, tests not run, and remaining gaps.
    - Do not record secret values, production database names if repository policy treats them as sensitive, private object names, clinical identifiers, raw logs containing secrets, or credential material.
    - Update gap status only to the degree actually proven.

15. **Return for review.**
    - The Executor does not self-declare final protocol acceptance or broader production readiness.
    - Return the immutable implementation/release revision and observed evidence for Reviewer evaluation.

## Acceptance criteria

- [ ] The exact governing task revision and implementation baseline are verified before mutation.
- [ ] The MHCS deployment implementation is traceably based on `Madeena-software/deploy-templates @ 569a30d4a089b0ee404ed6e963fdd2dfd96d3787` without blindly replacing MHCS-specific architecture.
- [ ] Production deployment workflows exist and are specialized for MHCS Core.
- [ ] The final deployment diff contains no unrelated application feature changes.
- [ ] The dedicated production Image Gateway worker consumes exactly the `image-gateway` queue and remains the only MHCS service attached to the private MPIPS network unless approved architecture states otherwise.
- [ ] The ordinary queue worker, scheduler, app, database, proxy, and other justified runtime roles remain operationally distinct.
- [ ] All required production MHCS infrastructure secret/configuration **names** are present and fail-closed checks pass without disclosure in logs/evidence; the separately authorized bootstrap `credential-server.txt` handoff follows its dedicated private channel.
- [ ] `APP_ENV=production` and `APP_DEBUG=false` are proven by a non-secret assertion.
- [ ] Production private storage resolves to the approved S3-compatible disk and no public/raw NPZ exposure is introduced.
- [ ] Production deployment does not run the default `DatabaseSeeder`, generic `migrate --seed`, `migrate:fresh`, or any destructive database reset.
- [ ] On a provably fresh first production database, `php artisan db:seed --class='Database\Seeders\MvpCoreClinicSeeder' --force` (or the exact repository-equivalent class invocation) runs exactly once under the explicit one-shot production-seed gate; otherwise the seed step is skipped.
- [ ] The one-shot production-seed authorization is absent from the normal persisted production environment after bootstrap, so synthetic seeding/funding remains fail-closed during ordinary runtime.
- [ ] The first bootstrap produces the current repository-defined synthetic clinic dataset and eight unique login credentials: one admin/primary Operator, five Members, and two additional Operators.
- [ ] The resulting `credential-server.txt` is stored at an approved private writable path with mode `0600`, is not committed, logged, or uploaded as CI evidence, and is returned only through the final private Antigravity owner handoff.
- [ ] All pending migrations are inspected before execution and no unapproved destructive/irreversible migration is applied.
- [ ] An existing production database has a fresh verified pre-deploy backup, or the environment is positively proven to be a new empty deployment and that fact is recorded.
- [ ] Repository-required tests/builds and deployment validation pass, or execution stops before production mutation.
- [ ] The production image deployed to Swarm has an immutable commit-derived release identity.
- [ ] The final Swarm stack reports healthy/running expected services and the app/database health checks pass.
- [ ] The Image Gateway worker's actual runtime command, queue name, timeout, resource bounds, and private network attachment are verified.
- [ ] A non-secret runtime assertion proves `DB_QUEUE_RETRY_AFTER` is greater than the Image Gateway worker timeout.
- [ ] The approved public health endpoint returns HTTP 200 through the production ingress path.
- [ ] No clinical payload, NPZ, DICOM, Member data, or real credential is used as a deployment smoke test.
- [ ] If deployment fails, rollback is bounded to the prior application/service release; database/storage are preserved and migration rollback is not improvised.
- [ ] Sanitized deployment evidence is written and truthfully distinguishes local validation, GitHub Actions evidence, production runtime evidence, skipped checks, and remaining gaps.
- [ ] The deployment does not claim closure of privacy, retention, storage-policy, or broader beta/release gaps without independent evidence.

## Verification requirements

### Required checks

The Executor MUST choose the exact repository-current commands after inspection, but verification MUST include the following classes of checks:

1. Repository state and ancestry:
   - `git status --short`
   - `git rev-parse HEAD`
   - `git merge-base --is-ancestor <implementation-baseline> HEAD`
   - bounded diff review.

2. Deployment configuration:
   - YAML/workflow syntax validation;
   - `docker compose -f docker-compose.prod.yml config` or an equivalent secret-safe validation path;
   - pinned template validation where applicable;
   - explicit inspection proving `image-worker` uses `--queue=image-gateway`.

3. Application verification before deployment:
   - full PHPUnit suite unless a documented repository blocker exists;
   - relevant JavaScript tests;
   - deterministic production frontend build;
   - production Docker image build;
   - existing security-validation workflow/checks applicable to the release revision.

4. Migration safety:
   - target production `migrate:status` or equivalent non-mutating inspection;
   - manual/static inspection of each pending migration before `migrate --force`.

5. Backup and first-bootstrap safety:
   - verified fresh backup for existing production data;
   - backup integrity check and approved remote-store presence;
   - positive proof of a fresh MHCS application database before any production seed;
   - explicit one-shot seed flag present only on the `MvpCoreClinicSeeder` command;
   - post-seed checks for expected synthetic records without password output;
   - `credential-server.txt` existence, `0600` mode, private path, and eight credential entries without exposing their values in CI evidence.

6. Swarm/runtime verification:
   - Swarm active and manager-capable;
   - expected stack/service task states;
   - app and database health status;
   - normal queue running;
   - scheduler running;
   - `image-gateway` worker running on the exact queue;
   - private MPIPS network attached only where approved;
   - immutable release image identity;
   - no-secret application runtime assertion;
   - migration current-state confirmation.

7. Public smoke verification:
   - HTTPS `GET`/`HEAD` to the repository/framework health endpoint through `APP_DOMAIN` with expected HTTP 200.

### Required evidence

The Executor MUST report:

- governing task path and immutable task revision;
- implementation baseline;
- deployment-template revision;
- deployment implementation/release commit;
- branch and bounded changed-file list;
- GitHub Actions workflow run identity and observed conclusion;
- exact tests/checks executed and observed results;
- Docker image immutable tag/ID in non-secret form;
- backup gate result;
- migration inspection and migration execution result;
- Swarm service/health summary;
- image-worker queue/network/resource verification;
- public health result;
- whether the first-production MVP seed ran or was skipped, and why;
- credential handoff status and protected server path/mode (but not credential values in sanitized evidence);
- whether rollback was required and its exact bounded outcome;
- tests/checks not run;
- remaining release/policy gaps;
- any blocker or deviation that could affect Reviewer acceptance.

## Stop conditions

The Executor MUST stop and return to planning or the required approval gate when any of the following occurs:

- The exact task revision cannot be established.
- The implementation baseline is not an ancestor of the execution state or unrelated work materially overlaps the deployment files.
- The pinned deployment-template revision cannot be read or materially differs from the task's stated reference without a new planning decision.
- Required GitHub secret/variable names are missing and cannot be configured without additional authority.
- Any required MHCS production fail-closed value is absent.
- The configured MPIPS production URL is loopback, public when architecture requires private transport, or the approved private network is absent.
- The dedicated image worker cannot be isolated to `image-gateway` without an application/architecture change outside this task.
- A required production backup cannot be created and verified for an existing database.
- Pending migrations include a destructive, irreversible, or rollback-incompatible change requiring an unapproved release decision.
- Repository tests, build, deployment validation, or security checks fail in a way not strictly attributable to a bounded deployment-file defect.
- Docker Swarm is not active or the target node is not an approved manager.
- Branch protection, environment approval, runner policy, sudo policy, or infrastructure policy requires human approval not yet granted.
- Production deployment would require deleting a stack, volume, bucket, database, or existing data.
- Production seeding is requested but the database cannot be positively proven fresh, the explicit one-shot seed gate cannot be enforced, or the current seed chain would require scope beyond the bounded guard/credential adaptations authorized here.
- Any target synthetic MVP account or seed-state record already exists before the intended first bootstrap in a way that would make the generated `credential-server.txt` incomplete or require password rotation/reconstruction.
- Deployment would require changing MPIPS, IAM/bucket policy, privacy/retention policy, DNS, TLS, or unrelated infrastructure without separate authority.
- A secret appears in shared/public logs, sanitized evidence, diff, generated repository content, or Actions artifacts. The explicitly authorized final private `credential-server.txt` handoff to the designated owner is not considered a violation; all other exposure must stop and be contained.
- The new release fails and safe application rollback cannot be performed because of migration/schema incompatibility.
- Any unexpected security, privacy, data-integrity, clinical-safety, or operational risk requires a new authority decision.

The Executor MUST NOT reinterpret these stop conditions as permission to weaken validation or bypass the release gate.

## Side-effect authorization

This task explicitly authorizes only the following side effects after all applicable preconditions pass:

- modify/create the bounded MHCS production deployment workflow and runtime configuration files required by this task;
- modify only the current MVP seeder environment guards, `Mvp03PointService` synthetic-funding environment guard, `MvpCredentialFile`, and Member credential append behavior required for the explicit one-time production bootstrap;
- modify sanitized production deployment evidence and the exact deployment-gap record when justified by observed evidence;
- create one or more bounded Git commits needed to publish the deployment implementation and evidence;
- push those commits without force to an allowed repository branch;
- dispatch the specialized one-time server-setup workflow when required;
- dispatch/install the approved database-backup setup when required;
- dispatch the MHCS production deployment workflow for the exact immutable release revision;
- create/update the MHCS Docker Swarm stack and services on the approved production manager;
- create/update the dedicated deployment root and application-owned persistent directories using the minimum approved permissions;
- write the production server environment file from GitHub-managed secrets without exposing its contents;
- build/tag the MHCS application image with an immutable commit-derived tag;
- run `php artisan migrate --force` after migration inspection and backup/fresh-environment gates pass;
- on a provably fresh first production database, run `Database\Seeders\MvpCoreClinicSeeder` exactly once with the explicit one-shot production-seed authorization and private credential-file path;
- read the resulting protected `credential-server.txt` once for direct final handoff to the designated owner, without placing it in GitHub Actions logs, artifacts, repository evidence, or version control;
- perform non-destructive health, service, network-existence, and public endpoint checks; and
- roll back application/service image state to the immediately previous immutable release when the new release fails and rollback is schema-safe.

This task does **not** authorize force push, branch-protection bypass, destructive data reset, `migrate:fresh`, generic/repeated production seeding, volume deletion, bucket deletion, MPIPS deployment/change, disclosure of infrastructure secrets, real-data import, or automatic database migration rollback. The one-time gated `MvpCoreClinicSeeder` bootstrap and direct private `credential-server.txt` disclosure to the designated owner are the only seeding/credential exceptions explicitly authorized here.

## Expected terminal outcome

### Review Required

Use when:

- the bounded deployment implementation is immutably published;
- production deployment completed or was safely rolled back;
- all actually applicable checks have truthful observed evidence; and
- the Reviewer can assess the resulting release state.

Report separately whether production is:

- `deployed-and-healthy`;
- `rolled-back-to-previous-release`; or
- `deployment-not-attempted-after-preflight-failure`.

`Review Required` does not mean broader clinical production readiness is accepted.

### Planning Required

Use when a stop condition prevents safe completion, including missing authority, unsafe migration, missing secrets/policy, infrastructure incompatibility, failed backup, blocked GitHub Environment approval, or rollback incompatibility.

Report the exact blocker, affected gate, and observed evidence. Do not improvise destructive recovery or broaden the task.

## Review and remediation handling

The Reviewer MUST evaluate:

- exact task revision;
- implementation baseline;
- deployment implementation/release commit;
- pinned deployment-template revision;
- changed deployment files;
- pre-release tests and build evidence;
- backup and migration evidence;
- GitHub Actions run evidence;
- actual production Swarm state;
- public health result;
- first-production MVP seed decision/result and proof of the one-shot gate;
- protected credential-file handling/handoff status without copying credential values into review evidence;
- image-worker `image-gateway` queue isolation;
- any rollback event;
- sanitized documentation; and
- remaining release/policy gaps.

A successful controlled production deployment MAY support closure of `MVP-GAP-022` to the bounded extent actually proven. It MUST NOT automatically close object-storage policy, privacy/retention, real-data readiness, full CI/release coverage, or broader beta gaps.

If review finds a bounded deployment/configuration defect within this same objective, update and republish this same stable task path for remediation. Materially new infrastructure, security, privacy, migration, or application scope returns to planning as separate work.
