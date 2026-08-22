---
title: MHCS Core Production S3 Private-Object Diagnostic
document_id: MHCS-TASK-PRODUCTION-S3-DIAGNOSTIC-001
version: 1.4
status: validated-published
language: en-US
last_updated: 2026-08-22
scope:
  - bounded production S3 private-object diagnosis
  - manual GitHub Actions diagnostic workflow
  - static workflow safety coverage
authority_note: This task authorizes only the repository changes and the separately bounded future diagnostic probe described below. It authorizes no deployment, workflow dispatch in the implementation turn, application fix, or unrelated production operation.
---

# Executable Task

## Task identity

**Task title:**
`MHCS Core Production S3 Private-Object Diagnostic`

**Task path:**
`.agents/tasks/production-s3-private-object-diagnostic.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content.`

**Delivery objective / Work Package / MVP:**
`Production S3 failure-boundary diagnosis for Image Gateway private source persistence`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The accepted production revision is healthy at the ingress boundary, but an
Operator NPZ capture can reach a frozen capture intent while both source
components remain missing. The application uses the S3 private-object disk,
the async source path explicitly sends `ACL=private`, rejected storage promise
reasons are not retained, and the browser hides the POST failure behind later
status polling. ACL incompatibility is possible but unconfirmed; slow WAN
upload remains possible because the existing 100 MiB probe was local to the
production runner. The S3-compatible service is reported to run on the same
physical production server and the current endpoint is reported as
`localhost:9000`. Because the app runs in a Swarm container, the diagnostic
must classify this endpoint without printing it: container loopback is not the
same namespace as the host or an S3 service container.
The existing production compose topology maps `host.docker.internal` to the
host gateway, while the app container's `127.0.0.1:9000` PHP-FPM health check
still refers to the container namespace; the diagnostic must not infer or
repair the S3 endpoint from that health check.

This task creates a manual, sanitized diagnostic that determines the S3
private-object boundary without using real NPZ data or changing clinical or
database state.

The first approved execution of the diagnostic (run `32558225894`) proved the
production revision guard, but no S3 probe executed because the standalone
diagnostic PHP failed during Laravel bootstrap. Its endpoint fields therefore
remained defaults. The harness omitted Composer autoload initialization before
loading `bootstrap/app.php`; the established production verification workflow
demonstrates the working standalone bootstrap sequence.

## Baseline and task revision

**Implementation baseline:**
`b6232a158b3f6884fd9823bc875abc432676b781`

**Task revision:**
`The full SHA of the commit containing this exact task content, supplied after publication.`

The implementation MUST start only after the task commit is pushed and
`origin/main` equals that task revision. The implementation commit is a
separate revision governed by this exact task content.

## Objective

Add one manual GitHub Actions workflow and focused static coverage that can,
after separate Planner review, perform exactly one synthetic private-S3
Put/Get/Delete round trip through the existing production application storage
path and report a sanitized failure-boundary classification.

### Latest diagnostic evidence

Run `32562636176` proved the production revision guard and Laravel bootstrap,
classified the effective endpoint as `other` without an explicit port `9000`,
and passed `HeadBucket` through the current configured endpoint. One synthetic
async PUT failed with a sanitized `unknown` error and cleanup passed. The root
cause remains unconfirmed. The diagnostic key used the isolated
`diagnostics/production-s3/<random>` prefix, while the Image Gateway NPZ path
uses `objects/<capture-id>/<radiograph|gain>`; therefore that synthetic PUT is
not sufficient to rule out prefix-specific S3 authorization or policy
differences. The evidence does not establish ACL incompatibility.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — validated-task, evidence, review, and side-effect boundaries.
- `.agents/context/project.md` and `.agents/context/modules/image-gateway/project.md` — private-object, Image Gateway, and MPIPS boundaries.
- Current accepted production revision `b6232a158b3f6884fd9823bc875abc432676b781`.
- Existing production revision-observability, runner, S3 configuration, and `PrivateObjectStore` implementation.
- CTO/user authorization in the production S3 private-object diagnostic request.

### Requirement traceability

- `S3-DIAG-001` → prove the running production revision before any S3 write.
- `S3-DIAG-002` → use the actual Laravel S3 configuration and private-object path.
- `S3-DIAG-003` → perform read-only reachability and local MinIO authentication checks.
- `S3-DIAG-004` → retain sanitized error classification without creating objects.
- `S3-DIAG-005` → statically enforce workflow and side-effect safety.
- `S3-DIAG-006` → classify the configured endpoint host/port and report a container-loopback conflict without exposing `AWS_ENDPOINT`.

## Scope

### In scope

- `.github/workflows/diagnose-production-s3.yml`, manual-only and runnable only on the existing `self-hosted` production runner.
- `tests/Deployment/ProductionS3DiagnosticWorkflowTest.php` with focused static assertions.
- A pre-probe revision guard requiring exactly `b6232a158b3f6884fd9823bc875abc432676b781`; mismatch MUST stop before any S3 write and emit only `revision_match=false`.
- The pre-probe revision guard MUST require all of: the app container found,
  `/var/www/html/VERSION-CURRENT` exactly equal to the accepted revision, a
  non-empty service image revision exactly equal to it, and a non-empty running
  container image revision exactly equal to it. Any missing or mismatched
  proof MUST emit only `revision_match=false` and exit before the PHP diagnostic
  or any S3/network operation.
- Running the diagnostic PHP inside the current `mhcs_core_app` container, bootstrapping Laravel, requiring `config('mhcs.private_object_disk') === 's3'`, and using the actual configured S3 adapter/client without printing configuration values. Any standalone PHP executed through `docker exec ... php` MUST first load `require 'vendor/autoload.php';` before `require 'bootstrap/app.php';`, followed by the Kernel bootstrap, using the same ordering proven by `.github/workflows/verify-production.yml`.
- Sanitized `HeadBucket` observation through the current configured endpoint.
- Read-only host-gateway resolution, TCP port `9000`, MinIO health, and local
  `HeadBucket` comparison using the effective production credentials and
  bucket only in memory.
- Sanitized endpoint classification from the actual configured endpoint:
  `endpoint_host_class` MUST be one of `localhost`, `loopback_ip`,
  `host_docker_internal`, `docker_service_name`, or `other`; report
  `endpoint_port_is_9000=true|false`; and report
  `container_loopback_endpoint_conflict=true` when the host class is
  `localhost` or `loopback_ip`.
- If `endpoint_host_class` is `localhost` or `loopback_ip` and
  `endpoint_port_is_9000=true`, the workflow MUST stop before `HeadBucket`,
  `GetBucketOwnershipControls`, `PutObject`, `putStreamAsync`, `GetObject`,
  or `DeleteObject`. It MUST report only the sanitized endpoint classification,
  `head_bucket=SKIPPED`, `ownership_controls=SKIPPED`,
  `acl_private_put=SKIPPED`, `private_object_roundtrip=SKIPPED`,
  `cleanup_primary_object=NOT_REQUIRED`,
  `cleanup_metadata_object=NOT_REQUIRED`, `cleanup_verified=NOT_REQUIRED`,
  `root_cause_boundary=s3_endpoint_configuration`,
  `root_cause_class=container_loopback_endpoint_conflict`,
  `root_cause_confirmed=true`, and `s3_probe_executed=false`. This is a
  successful diagnostic stop; no object cleanup is required because no
  diagnostic object was created.
- No synthetic payload, object key, S3 write, object read, object delete, ACL,
  ownership, or application upload.
- The local diagnostic client MUST be constructed in memory from the effective
  production access key, secret key, region, bucket, and path-style behavior,
  overriding only its endpoint to `http://host.docker.internal:9000`.
- Repository-only task/workflow/test changes, one task commit, one implementation commit, and the user-authorized pushes to `main`.

### Out of scope

- Dispatching `diagnose-production-s3.yml` in the implementation turn; deploying, releasing, or changing application services.
- Real NPZ/DICOM/clinical/member/ticket/admission/capture data, production object inspection, database queries or writes, migrations, seeders, queues, MPIPS, or application-runtime state mutation.
- Bucket creation, bucket policy, IAM, ownership-control, ACL, credential, endpoint, region, secret, or cloud-account changes.
- Direct SSH, direct manual production commands from WSL, bucket-wide listing/deletion, unrelated-object search, or a second no-ACL comparison probe.
- Application-code fixes, changes to capture behavior, storage implementation, retry behavior, browser behavior, or error persistence.
- New dependencies, new services, public routes, deployment workflow invocation, or Prestige workflow invocation.

### Preserved behavior

- Raw clinical source objects remain private, opaque-keyed, grant-authorized,
  and integrity-checked.
- MPIPS and all clinical/database/queue workflows remain untouched.
- The production S3 configuration is observed, not reconstructed or printed.
- No probe output contains credentials, bucket, endpoint, object key, request
  IDs, headers, environment dumps, identifiers, filenames, or payload bytes.

## Dependencies and assumptions

### Dependencies

- A clean implementation baseline at `b6232a158b3f6884fd9823bc875abc432676b781`.
- The existing self-hosted production runner and running `mhcs_core_app` container.
- Existing Laravel configuration, AWS SDK, S3 adapter, `PrivateObjectStore`, and grant-key services.
- Planner/Reviewer review before any future production workflow dispatch.

### Approved assumptions

- The existing production revision-observability pattern is the authority for locating the running app container and reading its deployed revision.
- A synthetic in-memory payload is sufficient to exercise the private-object persistence boundary while avoiding clinical content.
- The host-gateway endpoint is intended to be host-local MinIO; it must be
  proven read-only rather than assumed to work.

### Remaining approval requirements

- The repository task/workflow/test implementation and authorized commits/pushes are in scope for this task.
- The future production probe requires Planner/Reviewer review of the pushed implementation and an explicit dispatch decision. This task does not authorize dispatch in the implementation turn.
- Stop for any revision mismatch, unavailable app container, missing S3 configuration, unsafe reachability condition, cleanup failure, or scope expansion.

## Required capabilities

- Repository read and bounded write.
- Codebase Memory MCP or equivalent repository-intelligence inspection.
- Git inspection, focused PHP tests, Pint where applicable, and `git diff --check`.
- User-authorized commit and push to `main`.
- Future, separately reviewed GitHub Actions dispatch only; not part of this implementation turn.

## Execution constraints

- Use `workflow_dispatch` only, `runs-on: self-hosted`, `set -euo pipefail`, and no shell xtrace.
- Do not use SSH, deployment commands, service updates/restarts, database commands, migrations, seeders, Prestige checks, or the existing deployment workflow.
- The revision guard MUST execute before any S3 write and MUST emit only `revision_match=false` on mismatch.
- Standalone diagnostic Laravel bootstrap MUST use this exact order: `require 'vendor/autoload.php';`, `$app = require 'bootstrap/app.php';`, then `$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();`.
- Use the running app container's effective Laravel config and actual S3 client; never hard-code or echo credentials, bucket, endpoint, region, or object key.
- Parse the effective endpoint only in memory for the sanitized host class and
  port boolean. Never print `AWS_ENDPOINT`, change endpoint configuration, or
  restart MinIO/S3 or MHCS.
- Report booleans, approved enumerations, sanitized error class/code, and bounded HTTP status only. Never print raw exception messages, request IDs, response headers, or infrastructure identifiers.
- The diagnostic MUST NOT call `putStreamAsync`, `PutObject`, `GetObject`,
  `DeleteObject`, `ListObjects`, bucket policy/ACL/ownership mutations, or
  application upload paths.
- Never list or broadly delete the bucket. Do not automatically change application code based on the result.
- No new dependency.

## Acceptance criteria

- [ ] This task refinement is committed alone with message `docs: refine S3 local endpoint diagnostic`, pushed, and verified as `origin/main` before implementation begins.
- [ ] A dedicated manual-only self-hosted workflow exists with the exact production revision guard and no deployment, database, SSH, migration, seeder, Prestige, or application mutation path.
- [ ] A missing or mismatched app/container/service/image revision proof stops before any PHP/S3/network operation and emits only `revision_match=false`.
- [ ] The standalone diagnostic PHP loads Composer autoload before `bootstrap/app.php` and bootstraps Laravel in the same order as `verify-production.yml`.
- [ ] The workflow uses the actual running Laravel S3 configuration and reports only sanitized booleans/enumerations/error classifications.
- [ ] The workflow reports the allowed endpoint host class, whether the configured port is `9000`, and `container_loopback_endpoint_conflict=true` for `localhost` or loopback IP endpoints, without exposing `AWS_ENDPOINT`.
- [ ] A proven `localhost|loopback_ip` endpoint on port `9000` short-circuits successfully before all S3 calls and reports `s3_probe_executed=false` with the specified endpoint-configuration root cause.
- [ ] The workflow performs no `putStreamAsync`, S3 write, object read, object delete, ACL, ownership mutation, or application upload.
- [ ] The workflow reports current endpoint classification and `HeadBucket`, host-gateway resolution, bounded TCP `9000`, MinIO health, and local read-only `HeadBucket` using an in-memory endpoint override.
- [ ] Root-cause output confirms only a configured endpoint topology mismatch when all intended-local checks pass; it does not claim the NPZ PUT root cause is resolved.
- [ ] Focused static tests verify workflow dispatch/runner/revision/read-only checks/sanitization/no-side-effect requirements without a new dependency.
- [ ] The diagnostic workflow is not dispatched and no production probe is run during this implementation turn.

## Verification requirements

1. `php artisan test tests/Deployment/ProductionS3DiagnosticWorkflowTest.php --no-coverage`
2. `php artisan test tests/Deployment/ProductionVerificationWorkflowTest.php --no-coverage`
3. `vendor/bin/pint --test`, as applicable.
4. `git diff --check`.
5. Inspect the final diff and status; verify exactly one implementation commit with message `ci: add bounded production S3 diagnostic`, push `main`, fetch, and verify the returned remote `origin/main` SHA.

Do not dispatch `.github/workflows/diagnose-production-s3.yml`.

## Stop conditions

Stop and return to Planner/Reviewer if:

- the task publication SHA, implementation baseline, or clean working tree cannot be established;
- the running production revision differs from the exact required revision;
- the app container or actual S3 configuration cannot be used without reconstructing credentials or printing sensitive values;
- the requested probe requires database, queue, clinical, member, ticket, MPIPS, IAM, bucket-policy, ownership, ACL, credential, deployment, SSH, or application changes;
- the result cannot be sanitized or the read-only local endpoint comparison cannot be proven;
- the implementation would require a new dependency or a second comparison probe; or
- any test, lint, diff check, commit, or push result is not actually observed.

## Delivery handoff

The Planner/Reviewer handoff MUST report:

- task commit SHA and confirmation that `origin/main` matched it before implementation;
- implementation commit SHA and final remote `origin/main` SHA;
- changed files and observed verification results;
- explicit confirmation that the diagnostic workflow was not dispatched and no production S3 probe was run in this turn; and
- any unresolved limitation without claiming production root cause.
