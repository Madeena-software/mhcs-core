---
title: Controlled MHCS Core Production Swarm Operations
document_id: MHCS-TASK-PRODUCTION-SWARM-DEPLOYMENT-001
version: 1.4
status: validated-on-publication
language: en-US
last_updated: 2026-08-19
scope:
  - recurring bounded production Swarm configuration and verification work
  - GitHub Actions deployment and non-destructive operational probes
  - existing Nginx/PHP upload-limit policy and ingress verification
authority_note: This stable task authorizes only the recurring operational changes explicitly eligible below. It does not authorize arbitrary production changes, alter approved application behavior, or remove the separate release gate.
---

# Executable Task

## Task identity

**Task title:**
`Controlled MHCS Core Production Swarm Operations`

**Task path:**
`.agents/tasks/production-swarm-deployment.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content. Supply that full task-publication SHA to every Executor.`

**Delivery objective / Work Package / MVP:**
`MVP-09 / controlled production Swarm operations and verification`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

This is the stable task path for small, related MHCS production-deployment
feedback. It intentionally avoids a new filename or task revision for each
eligible operational correction, while preserving a clear boundary for changes
that need new authority.

The previous upload-limit remediation was governed by this path at
`66ebade787a5292bc1ea4842262b8c1c91157673`. Its implementation revision
`75c85976f38e372f808378d5369649a5b7043511` was successfully deployed in
Actions run `32210699919`: Nginx was observed at `201m`; PHP was observed at
`100M` per file and `201M` per request; and the public health endpoint passed.
The current clean deployed baseline is
`a719019930f99762bfedb739c251ad6157548137`, successfully deployed in Actions
run `32211852025`.

The immediate delivery slice adds a manual GitHub Actions production-ingress
probe. It must prove that the existing server accepts a generated 100 MiB
multipart file without submitting clinical content or changing application
behavior.

## Baseline and task revision

**Initial implementation baseline:**
`a719019930f99762bfedb739c251ad6157548137`

**Task revision:**
`The full SHA of the commit containing this exact task content, supplied after publication.`

For the immediate slice, start from the initial baseline. For a later owner
feedback item that qualifies under **Recurring-feedback eligibility**, start
from the latest accepted, clean descendant and record its full SHA as that
execution's immutable baseline. This transition is explicit and only permitted
after the prior execution reached an `ACCEPTED` review verdict under this exact
task revision.

Stop if the working tree is dirty, the current baseline is not an accepted
descendant, or there is overlapping unreviewed deployment work.

## Objective

Maintain and prove the approved MHCS production Swarm delivery properties using
the existing Docker and GitHub Actions mechanisms. Eligible owner feedback may
be implemented under this task without creating a replacement task, provided it
stays within the fixed operational envelope below.

### Immediate objective

Add one manual-only GitHub Actions workflow that sends a generated 100 MiB
multipart probe through the deployed MHCS Nginx ingress and fails on HTTP 413.
It must prove transport to the existing application boundary only; it must not
create an upload endpoint, persist a file, or exercise clinical processing.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — delivery, evidence, acceptance, and release boundaries.
- `.agents/context/project.md` and `.agents/context/modules/image-gateway/project.md` — private-object, Image Gateway, and MPIPS boundaries.
- `config/mhcs.php`, `.env.example`, and `tests/Unit/UploadLimitConfigurationTest.php` — approved 100 MiB-per-file, two-file, 201 MiB request policy.
- `.github/workflows/deploy-swarm.yml`, `docker-compose.prod.yml`, `docker/nginx.conf`, `docker/php.ini`, `Dockerfile` — established production delivery mechanism.
- CTO approval on 19 August 2026 — reuse this stable task for the bounded deployment-operation envelope and add the 100 MiB ingress probe.

### Requirement traceability

- `MVP-GAP-022` → controlled production deployment and observed release evidence.
- `MVP-GAP-023` → CI/deployment evidence must be observed and not overstated.
- Image Gateway approved architecture → raw NPZ stays private; browser submission is accepted only by the MHCS Image Gateway.
- Existing approved upload policy → two 100 MiB files plus 1 MiB multipart overhead; no implicit capacity increase.

## Scope

### In scope

- The existing Docker Swarm deployment package: `Dockerfile`, `docker-compose.prod.yml`, `docker/nginx.conf`, and `docker/php.ini`.
- Production GitHub Actions workflows and tightly focused deployment configuration tests or sanitized evidence records.
- Non-destructive configuration, health, ingress, container, rollout, and private-network verification using the established self-hosted runner and workflow mechanism.
- Minimal fixes to deployment configuration or verification that preserve the approved application policy and topology.
- The immediate manual-only workflow, preferably `.github/workflows/verify-production-upload.yml`, which must:
  - use `workflow_dispatch` only and share `production-deployment-mhcs_core` concurrency with deployment;
  - create a temporary, generated 100 MiB zero-filled or sparse file under `/tmp` and delete it with a shell `trap`;
  - submit it as multipart to `http://127.0.0.1:8013/up` from the existing self-hosted production runner;
  - require a complete upload of at least 100 MiB and the expected HTTP `405`, because `/up` is an existing `GET|HEAD` health route; and
  - fail clearly on `413`, transport failure, timeout, or any unexpected response.

### Recurring-feedback eligibility

An Executor may act directly on later CTO deployment feedback under this exact
task revision only when every condition holds:

1. the change is limited to the in-scope deployment surfaces or their focused verification/evidence;
2. it preserves the approved application upload policy, database, storage, private-object, MPIPS, authorization, and network boundaries;
3. it requires no new dependency, service, public route, secret, cloud account, upstream proxy, or architecture decision;
4. it is independently reviewable with the task's existing acceptance and verification model; and
5. the Executor records the feedback, execution baseline, changed files, and observed evidence in the review handoff.

### Out of scope

- Application routes, upload handlers, validation behavior, clinical workflows, database schema, migrations, queues, storage layout, MPIPS, or any new endpoint.
- Real NPZ, DICOM, Member, credential, object-storage, or clinical data; a probe must be generated dummy bytes only.
- DNS, TLS, WAF/CDN, external/upstream proxy, firewall, IAM, secret, cloud-account, or persistent-data changes.
- New dependencies, templating layers, services, public interfaces, abstractions, or runtime configuration sources.
- Any feedback that fails **Recurring-feedback eligibility**. It requires planning and a new bounded task.

### Preserved behavior

- The application remains limited to two 100 MiB files plus 1 MiB multipart overhead; the probe does not raise that policy.
- `/up` remains a `GET|HEAD` health route. Its expected `405` response to the probe is a non-mutating transport proof, not a failed health check.
- Raw NPZ stays private and inaccessible through public object storage; no object is persisted by the probe.
- Existing Swarm topology, Image Gateway worker isolation, MPIPS privacy boundary, normal health checks, and rollback rules remain unchanged.

## Dependencies and assumptions

### Dependencies

- Initial implementation baseline `a719019930f99762bfedb739c251ad6157548137`.
- A clean working tree and the immutable governing task-publication revision.
- Existing self-hosted Actions runner on the production host, Swarm manager, and published port `8013` used by `deploy-swarm.yml`.
- Existing Nginx/PHP limits: `201m`, `100M`, and `201M`.

### Approved assumptions

- A multipart POST to `/up` reaches the existing Laravel application boundary only after Nginx accepts the request; Laravel returns `405` because the route accepts `GET|HEAD` only.
- A sparse/generated zero file transmits as 100 MiB of nonclinical data while avoiding persistent test fixtures and storage use.
- Existing deployment workflow configuration assertions remain the source of truth for active Nginx and PHP limits.

### Remaining approval requirements

- The Executor may implement, locally validate, and prepare a reviewable implementation under this task.
- A push to `main`, production deployment, or manual production-probe dispatch is **not yet authorized**. The CTO must explicitly authorize the exact reviewed implementation revision because a `main` push triggers the existing deployment workflow.
- Stop for any GitHub branch, environment, runner, or infrastructure approval required by the existing workflow.

## Required capabilities

**Required capabilities:**

- repository read and bounded write;
- Git inspection and one bounded local implementation commit;
- focused PHP test and Docker/Compose validation;
- GitHub Actions workflow inspection; and
- after explicit release approval, authorized push, workflow dispatch, and non-destructive production observation.

## Execution constraints

- Reuse existing workflow, Docker, Nginx, PHP, port, and concurrency conventions. Do not add a helper service or application endpoint for the probe.
- Keep the probe in a separate manual workflow. Do not add a 100 MiB request to every deployment, push, or health check.
- Make the expected response and byte threshold explicit; never treat a non-413 response by itself as proof of a completed 100 MiB upload.
- Do not print request bodies, generated file contents, secrets, full environment/configuration dumps, or application logs containing sensitive data.
- Limit runtime output to byte count, HTTP status, and pass/fail messages.
- Use the existing controlled CI/CD path only. Direct SSH or manual production commands are prohibited.
- Preserve unrelated behavior and stop rather than broadening this task for an unknown upstream 413 or new infrastructure boundary.

## Acceptance criteria

- [ ] A manual-only GitHub Actions workflow exists for the 100 MiB production-ingress probe and cannot overlap the production deployment workflow.
- [ ] The workflow sends generated dummy multipart content of at least 100 MiB through the deployed local Nginx ingress and deletes the temporary file on all exits.
- [ ] The workflow passes only when upload transfer completes and `/up` returns the expected `405`; it fails on `413`, timeout, transport failure, or another status.
- [ ] No application route, persistence path, upload policy, clinical workflow, secret, credential, or new dependency is added or changed.
- [ ] Existing focused upload-limit configuration tests and production build/Compose validation remain passing.
- [ ] A future feedback item may use this task only when all **Recurring-feedback eligibility** conditions are demonstrably met.

## Verification requirements

### Required checks before review

1. Run `vendor/bin/phpunit tests/Unit/UploadLimitConfigurationTest.php`.
2. Run `git diff --check` and inspect the exact workflow diff for manual trigger, shared concurrency, temporary-file cleanup, multipart transfer, byte threshold, and `405`/`413` handling.
3. Run the existing production Docker build and secret-safe `docker compose -f docker-compose.prod.yml config` validation when deployment configuration is touched.
4. After explicit release approval, observe the exact Actions run for the implementation revision and manually dispatch the new probe; report its run identity, HTTP status, uploaded byte count, and conclusion.

### Required evidence

The Executor MUST report the governing task revision, immutable execution
baseline, implementation revision, owner feedback when applicable, changed
files, commands actually run, observed results, checks not run, and known
limitations. Production evidence must distinguish deployment-run observations
from the manual probe run and must not expose sensitive values or payloads.

## Stop conditions

Stop and return to planning if:

- the task revision, accepted execution baseline, or current working-tree state cannot be established;
- the change requires a public endpoint, application behavior change, new dependency, database/migration, secret, or external-infrastructure decision;
- the expected 100 MiB transfer cannot be proven without exposing data or changing the approved upload policy;
- the server returns 413 after the deployed Nginx/PHP limits are proven, indicating an upstream boundary outside this task;
- an eligible feedback item cannot meet the stated acceptance criteria with existing mechanisms; or
- required release, GitHub, runner, or infrastructure approval is missing.

## Side-effect authorization

### Explicitly authorized

- Modify only the in-scope repository surfaces.
- Create one bounded local implementation commit after local verification.
- Record sanitized local and CI evidence in the existing deployment evidence record when a reviewable terminal state is reached.

### Not yet authorized

- Push to `main`, dispatch any workflow against production, deploy, alter production services, write production data, or create external infrastructure resources.

## Expected terminal outcome

### Review Required

Use when a reviewable implementation revision and truthful local evidence exist.
The Reviewer must decide whether it satisfies this exact task revision before a
release is requested.

### Deployed and probe-verified

Use only after the CTO authorizes the exact reviewed revision, the deployment
workflow succeeds, and the manual probe reports a completed at-least-100-MiB
transfer with HTTP `405` rather than `413`.

### Planning Required

Use when feedback falls outside **Recurring-feedback eligibility** or a stop
condition reveals a new upstream, architecture, security, data, or release
boundary.

## Review and recurring remediation handling

Each execution is reviewed against this exact immutable task revision and its
recorded execution baseline. A review verdict of `ACCEPTED` establishes that
implementation revision as the next eligible baseline for later qualifying
feedback. This stable task path avoids needless replacement tasks; it does not
authorize an unbounded production workstream or silently alter approved scope.
