---
title: Controlled MHCS Core Production Swarm Deployment — Upload-Limit Remediation
document_id: MHCS-TASK-PRODUCTION-SWARM-DEPLOYMENT-001
version: 1.3
status: validated-on-publication
language: en-US
last_updated: 2026-08-19
scope:
  - bounded remediation of production HTTP request-size configuration
  - effective Nginx and PHP-FPM upload-limit verification
  - controlled Swarm redeployment through the existing GitHub Actions workflow
authority_note: This remediation remains within the approved controlled-production-deployment objective. It authorizes no change to the approved 100 MiB-per-file Image Gateway policy, clinical workflow, storage boundary, MPIPS boundary, or production data. Production deployment remains subject to the explicit gates and stop conditions below.
---

# Executable Task

## Task identity

**Task title:**  
`Controlled MHCS Core Production Swarm Deployment — Upload-Limit Remediation`

**Task path:**  
`.agents/tasks/production-swarm-deployment.md`

**Task contract state:**  
`Validated/Published upon immutable publication of this exact content; the governing task SHA must be supplied to the Executor before execution.`

**Delivery objective / Work Package / MVP:**  
`MVP-09 / Release Gate — bounded production NPZ-upload remediation`

**Owner / designated planning authority:**  
`Faliq Adlan, CTO`

## Delivery context

The existing controlled-production-deployment task was published at
`.agents/tasks/production-swarm-deployment.md @ 01b5d2847749049d52c58cd1f2e8e1b645196add`.
GitHub Actions deployed implementation revision
`b22e76ef0e587a81e011c27b6d6abc66e2572dbc` successfully in run
`31770911997` on 14 August 2026.

The owner reports HTTP 413 while submitting the radiograph/gain NPZ pair in
production. Observed source evidence shows the deployed Nginx configuration
does not set `client_max_body_size`, and the PHP-FPM configuration does not set
`post_max_size` or `upload_max_filesize`. The existing deployment workflow,
application configuration, and unit test deliberately use one policy: two
100 MiB files plus 1 MiB multipart overhead, yielding a 201 MiB request.

This task corrects and verifies those three effective runtime boundaries. It
does not increase the approved application upload policy and does not upload
clinical or NPZ content as a smoke test.

## Baseline and task revision

**Implementation baseline:**  
`b22e76ef0e587a81e011c27b6d6abc66e2572dbc`

This is the immutable revision deployed by successful GitHub Actions run
`31770911997`. It is the remediation baseline, not a new accepted baseline.

**Deployment-template baseline:**  
`Madeena-software/deploy-templates @ 569a30d4a089b0ee404ed6e963fdd2dfd96d3787`

**Task revision:**  
`The full SHA of the commit containing this exact task content, supplied after publication.`

Before implementation, confirm that the governing task-publication commit
descends from the implementation baseline and that the working tree contains
no unrelated changes to the listed deployment surfaces.

## Objective

Restore the approved production NPZ-upload path by configuring Nginx and
PHP-FPM for exactly the current 100 MiB-per-file / 201 MiB-per-request policy,
and prove the effective limits after the controlled Swarm rollout without
altering application upload validation or handling clinical data.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — delivery, evidence, and release boundaries.
- `.agents/context/project.md` and `.agents/context/modules/image-gateway/project.md` — Image Gateway ownership, private-object boundary, and production restrictions.
- `.agents/tasks/production-swarm-deployment.md @ 01b5d2847749049d52c58cd1f2e8e1b645196add` — original controlled-deployment objective and side-effect boundary.
- User-approved 19 August 2026 remediation scope — production 413 diagnosis and bounded task publication.
- `config/mhcs.php`, `.env.example`, and `tests/Unit/UploadLimitConfigurationTest.php` — one 100 MiB file policy, two-file total, and 1 MiB multipart allowance.
- `.github/workflows/deploy-swarm.yml` — current production environment generation and Swarm deployment verification.
- `docker/nginx.conf`, `docker/php.ini`, `Dockerfile`, and `docker-compose.prod.yml` — deployed request path.

### Requirement traceability

- `MVP-GAP-022` → controlled production deployment and observed release evidence.
- `MVP-GAP-023` → deployment/CI evidence must be observed and not overstated.
- Image Gateway approved architecture → raw NPZ remains private; browser submission is validated and durably accepted only by MHCS Image Gateway.
- Existing approved upload policy → exactly two 100 MiB files plus 1 MiB multipart overhead; no implicit capacity increase.

## Scope

### In scope

- Modify `docker/nginx.conf` to set `client_max_body_size 201m;` for the public application server.
- Modify `docker/php.ini` to set `upload_max_filesize = 100M` and `post_max_size = 201M`.
- Extend `tests/Unit/UploadLimitConfigurationTest.php` so the deployment configuration remains aligned with the existing `mhcs.upload` policy.
- Extend `.github/workflows/deploy-swarm.yml` post-deploy verification to assert the active Nginx and PHP-FPM limit values by container inspection, emitting only the expected limit values and pass/fail status.
- Build, test, and redeploy only through the existing controlled GitHub Actions Swarm workflow after its normal safety gates pass.
- Record sanitized remediation evidence in `docs/mvp/evidence/production-swarm-deployment.md` if, and only if, the workflow reaches a reviewable terminal state.

### Out of scope

- Changing `MHCS_MAX_UPLOAD_MB`, image file count, Image Gateway request validation, database schema, application upload handlers, storage, MPIPS, or any clinical workflow.
- A new environment variable, Nginx templating mechanism, service, package, proxy, or abstraction.
- Uploading a real NPZ, DICOM, clinical payload, Member data, or credential as a smoke test.
- Any DNS, TLS, upstream-proxy, firewall, IAM, storage-policy, secret, database, migration, or MPIPS change.
- The duplicate `.agents/tasks/production-swarm-deployment-v1.2.md`; it is not this task's governing path and must not be used as a replacement task revision.

### Preserved behavior

- The application continues to reject files above 100 MiB and requests above the existing 201 MiB envelope.
- Raw NPZ remains private and never becomes downloadable or browser-addressable through object storage.
- Existing authorization, CSRF, checksum, manifest, safe-schema, and durable-acceptance checks remain unchanged.
- The current Swarm service topology, queue isolation, private MPIPS boundary, persistent data, and deployment rollback rules remain unchanged.

## Dependencies and assumptions

### Dependencies

- Implementation baseline `b22e76ef0e587a81e011c27b6d6abc66e2572dbc`.
- A fresh immutable task-publication revision.
- Existing self-hosted Actions runner, active Swarm manager, GitHub Environment/branch approvals, and non-destructive deployment workflow.

### Approved assumptions

- GitHub Actions currently writes `MHCS_MAX_UPLOAD_MB=100`; the same value is already asserted by the existing configuration test.
- `docker/nginx.conf` is copied to the deployed Nginx bind mount, and `docker/php.ini` is copied into the application image.
- The Nginx release-version environment setting forces a new Nginx task on each immutable deployment revision.

### Remaining approval requirements

- The designated owner must explicitly authorize any GitHub Actions production deployment after implementation is reviewed and the exact release revision is identified.
- Stop for any GitHub Environment, branch-protection, runner, or infrastructure approval required by the existing workflow.

## Required capabilities

**Required capabilities:**

- repository read and bounded write;
- Git inspection and one bounded local commit for task publication;
- PHP test, Docker build, and Docker Compose validation;
- GitHub Actions inspection and authorized workflow dispatch;
- non-destructive Swarm/container configuration inspection.

## Execution constraints

- Start from the stated implementation baseline and exact immutable task revision; stop on unrelated overlapping changes.
- Reuse the static values already declared by the production workflow. Do not introduce another limit setting or runtime templating layer.
- Do not weaken the current app-level file-count, per-file, total-size, untrusted-input, or authorization checks.
- Do not read, print, persist, or disclose secret values, clinical payloads, object keys, NPZ/DICOM bytes, or generated credentials.
- Verify effective container configuration by querying only the three relevant limit values; do not dump full Nginx, PHP, or environment configuration.
- Do not deploy or push an implementation release until the remaining deployment approval requirement is satisfied.
- If an upstream proxy still returns 413 after the deployed Nginx/PHP values are proven, stop and return to planning; an upstream-proxy change is outside this task.

## Remediation

**Review basis:**

- Original governing task: `.agents/tasks/production-swarm-deployment.md @ 01b5d2847749049d52c58cd1f2e8e1b645196add`.
- Deployed implementation: `b22e76ef0e587a81e011c27b6d6abc66e2572dbc`, GitHub Actions run `31770911997`, concluded `success`.
- Observed production symptom: owner-reported HTTP 413 while submitting the NPZ pair.
- Observed source: no `client_max_body_size` in `docker/nginx.conf` and no PHP upload/post limit in `docker/php.ini`.

### Required corrections

- Apply the three configuration values in scope without changing the app policy.
- Add focused source-level regression coverage for their alignment with `config('mhcs.upload')`.
- Add a post-deploy effective-configuration assertion for Nginx and PHP-FPM.

### Additional verification

- Confirm the focused test fails before the configuration change and passes after it.
- Confirm the production image and Compose configuration build successfully.
- Confirm the Actions run reports the effective `201m`, `100M`, and `201M` values without exposing other configuration.

## Acceptance criteria

- [ ] Nginx limits the public request body to exactly 201 MiB.
- [ ] PHP-FPM limits individual files to exactly 100 MiB and POST bodies to exactly 201 MiB.
- [ ] The existing application policy remains two 100 MiB files plus 1 MiB multipart overhead; it is not increased.
- [ ] A focused automated test derives the expected values from the existing MHCS upload policy and fails if either production configuration file drifts.
- [ ] Post-deploy verification proves the active Nginx and PHP-FPM values without logging secrets or payload data.
- [ ] No clinical payload, real NPZ/DICOM, object data, credential, or new dependency is used.
- [ ] Existing Swarm service topology, Image Gateway worker isolation, private MPIPS boundary, and normal health verification remain intact.
- [ ] If the reported path still returns 413 after active Nginx/PHP verification, the task stops before any upstream-proxy change.

## Verification requirements

### Required checks

1. Run `vendor/bin/phpunit tests/Unit/UploadLimitConfigurationTest.php` before and after the change.
2. Run the repository's existing production image build and secret-safe `docker compose -f docker-compose.prod.yml config` validation.
3. Run the repository-required CI checks for the changed release revision.
4. After an owner-authorized deployment, use the running containers to assert only:
   - Nginx effective `client_max_body_size` is `201m`;
   - PHP effective `upload_max_filesize` is `100M`; and
   - PHP effective `post_max_size` is `201M`.
5. Run the existing non-destructive public health check. Do not submit a clinical upload as a probe.

### Required evidence

The Executor MUST report the immutable implementation revision, exact governing
task revision, commands actually executed, observed focused-test/build/Compose
results, Actions run identity and conclusion, effective limit assertions,
public-health result, checks not run, and any remaining upstream-proxy or
approval limitation. Evidence must distinguish local, CI, and production
observations.

## Stop conditions

Stop and return to planning if any of the following occurs:

- the task revision or implementation baseline cannot be established;
- a needed value differs from the approved 100 MiB / 201 MiB policy;
- effective Nginx/PHP inspection would require a secret or broad configuration dump;
- deployment approval, GitHub Environment approval, or runner policy is absent;
- image build, focused test, Compose validation, or required CI fails outside this bounded configuration defect;
- deployment would require database, storage, MPIPS, DNS, TLS, upstream-proxy, credential, IAM, or destructive action; or
- a 413 remains after active Nginx/PHP verification, indicating an upstream boundary outside scope.

## Side-effect authorization

### Explicitly authorized side effects

- Create one bounded local Git commit containing this task publication.

### Not yet authorized

- Push a remediation implementation, dispatch production deployment, or mutate production. Those actions require the explicit deployment approval stated above and all existing workflow gates.

## Expected terminal outcome

### Review Required

Use when a reviewable implementation revision and truthful local/CI evidence
exist. A production deployment, if later authorized, must report separately as
`deployed-and-healthy`, `rolled-back-to-previous-release`, or
`deployment-not-attempted-after-preflight-failure`.

### Planning Required

Use when a stop condition or an upstream-proxy boundary prevents the bounded
remediation. Do not enlarge this task to modify an unknown upstream service.

## Review and remediation handling

The Reviewer evaluates the implementation against this exact task revision,
baseline `b22e76ef0e587a81e011c27b6d6abc66e2572dbc`, the original deployment
authority, and observed verification evidence. Acceptance does not authorize
production release. Any remaining upstream-proxy limit, product-size change, or
unrelated deployment finding returns to planning as separate work.
