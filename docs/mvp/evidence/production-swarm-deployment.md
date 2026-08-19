---
title: Production Swarm Upload-Limit Remediation Evidence
document_id: MHCS-EVIDENCE-PRODUCTION-SWARM-UPLOAD-LIMIT-001
date: 2026-08-19
status: deployed-and-healthy
---

# Deployment evidence

## Traceability

- Governing task: `.agents/tasks/production-swarm-deployment.md`
- Governing task revision: `66ebade787a5292bc1ea4842262b8c1c91157673`
- Implementation baseline: `b22e76ef0e587a81e011c27b6d6abc66e2572dbc`
- Implementation revision: `75c85976f38e372f808378d5369649a5b7043511`
- Workflow: `Deploy to Production (Swarm Mode)`
- Actions run: `32210699919`
- Run URL: https://github.com/Madeena-software/mhcs-core/actions/runs/32210699919
- Trigger: authorized push to `main`
- Conclusion: `success`

## Observed verification

Local preflight:

- `vendor/bin/phpunit tests/Unit/UploadLimitConfigurationTest.php` — passed, 3 tests, 16 assertions.
- `vendor/bin/pint --test tests/Unit/UploadLimitConfigurationTest.php` — passed.
- `git diff --check` — passed.
- `docker build --target app --tag mhcs_core:upload-limit-preflight .` — passed.
- `docker compose -f docker-compose.prod.yml config` with secret-safe dummy validation inputs — passed.
- Baked application image values — `upload_max_filesize=100M`, `post_max_size=201M`.
- Direct application boundary probe — exactly 100 MiB accepted; 100 MiB plus one byte rejected.

Production workflow:

- Build & Validate — passed; production image build and Compose validation passed.
- Deploy to Production — passed; Swarm rollout completed.
- Effective Nginx `client_max_body_size` — `201m`.
- Effective PHP `upload_max_filesize` — `100M`.
- Effective PHP `post_max_size` — `201M`.
- Public `/up` health endpoint — HTTP 200 on port 8013.
- Credential server-file protection — passed.
- Post-deploy verification — 29 passed, 0 failed, 0 warnings.

## Scope and limitations

- No clinical, NPZ, DICOM, Member, credential, or object-storage payload was uploaded.
- Application upload validation, storage, MPIPS, Swarm topology, and private-object boundaries were unchanged.
- The approved policy remains two 100 MiB files plus 1 MiB multipart overhead; no application limit was increased.
- A full local `php artisan test`, full Pint check, and host `npm run build` also exposed pre-existing unrelated baseline failures. The focused test, production Docker build, Compose validation, and the deployment workflow passed. The Docker build's own frontend stage and DICOM bundle check passed.
- Composer audit reported no security advisories. A formal connector-backed security diff report was unavailable; the bounded manual review found no additional in-scope findings.
- No upstream-proxy 413 was observed after active Nginx/PHP verification; no upstream change was attempted.

## Immediate manual production-ingress probe

### Traceability

- Status: `deployed-and-probe-verified`; production deployment and manual probe were authorized and observed below.
- Governing task: `.agents/tasks/production-swarm-deployment.md`
- Governing task revision: `5ea7d2cb542e990d396b4045ae604624823ca7ef`
- Implementation baseline: `a719019930f99762bfedb739c251ad6157548137`
- Implementation revision: `a079b29` (`ci: add manual production upload ingress probe`)
- Owner feedback: none; this is the task's immediate 100 MiB probe objective.
- Changed file: `.github/workflows/verify-production-upload.yml`

### Observed local verification

- `vendor/bin/phpunit tests/Unit/UploadLimitConfigurationTest.php` — passed, 3 tests, 16 assertions.
- `git diff --check` — passed.
- Extracted workflow shell — `bash -n` passed.
- Workflow YAML — parsed successfully with the available Ruby YAML parser.
- `docker compose -f docker-compose.prod.yml config` with secret-safe dummy validation inputs — passed.
- `docker build --target app --tag mhcs_core:verify-production-upload .` — passed.

### Release boundary and limitations

- The workflow is manual-only, shares `production-deployment-mhcs_core` concurrency with deployment, uses the existing self-hosted runner and port `8013`, and sends only generated sparse zero bytes to `/up`.
- Before release approval, no production Actions run or manual probe was observed; both are recorded below after approval.
- The eventual probe must report at least 100 MiB uploaded and HTTP `405`; HTTP `413`, timeout, transport failure, or another status must fail the run.

### Observed Actions verification

- Deployment workflow: run `32214202956` — success; Build & Validate, Deploy to Production, and post-deploy verification passed for head `0f6b1b56554f57396e9d03dd1b69871ba18702a0`.
- Manual probe workflow: run `32214384573` — success; `workflow_dispatch` on `main`.
- Probe result: HTTP `405`; uploaded `104857834` bytes; conclusion `success`.
- No request body, payload contents, secrets, or application logs were recorded.
