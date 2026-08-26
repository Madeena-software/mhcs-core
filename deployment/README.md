# MHCS Core deployment specialization

Source authority: https://github.com/Madeena-software/deploy-templates,
default branch main, commit
569a30d4a089b0ee404ed6e963fdd2dfd96d3787.

The applicable source family is templates/prod. The files in this directory
specialize its Laravel/PHP production organization for the MHCS modular
monolith:

Source paths reviewed and specialized where applicable:

- `templates/prod/standard-dockerfile`
- `templates/prod/standard-docker-compose.prod.yml`
- `templates/prod/standard-entrypoint.sh`
- `templates/prod/standard-nginx.conf`
- `templates/prod/standard-php.ini`
- `templates/prod/standard-supervisord.conf`
- `templates/prod/standard-dockerignore`
- `templates/prod/standard-deploy-swarm.yml` (reviewed, not copied because it
  performs SSH/live deployment)
- `templates/prod/validate-boilerplate.sh` (reviewed for required validation)

- web, queue, scheduler, and dedicated Image Gateway worker roles;
- one application database and one cache/queue service;
- an attachment from the Image Gateway worker to the externally managed,
  private MPIPS network.

The MPIPS service, its image, resource limits, credentials, storage, and
runtime isolation are not deployed or configured by this repository. The
`MPIPS_NETWORK_NAME` value identifies a pre-provisioned private network owned
by the MPIPS repository. MHCS exposes no MPIPS public route and defines no
MPIPS service.

## Current operational topology — reconciled 2026-08-21

Development workstation / Codex / WSL is used for the repository, tests, Git,
and `gh` CLI. It is not the production host. Direct SSH or manual host
operations from the development workstation are prohibited.

Production runs on `simama-production-server` through the GitHub Actions
self-hosted runner and Docker Swarm. Versioned workflows provide these
operational roles:

- deployment and post-deployment health checks;
- read-only production verification;
- backup and server setup where applicable; and
- the bounded Prestige production-data workflow with separate apply and
  canonical verification steps.

The Image Gateway worker is the only MHCS caller across the private MPIPS
network boundary. MHCS exposes no public MPIPS route. Workflow implementation,
host values, credentials, environment secrets, and private data remain outside
this README.

The application image is built and published outside production. Production
consumes only `ghcr.io/madeena-software/mhcs-core` by immutable digest.
`source_sha` identifies the application source embedded in that image and may
differ from the workflow/control-plane `GITHUB_SHA`. Production pulls use
dedicated read-only GHCR credentials; there is no production build fallback.

The current bounded deployment and verification evidence is recorded in
`docs/mvp/evidence/production-swarm-deployment.md` and
`docs/mvp/evidence/prestige-production-rehearsal-data.md`; it does not by
itself establish complete production, security, privacy, or release
conformance.

Static validation:

    bash deployment/validate.sh

Docker Compose and image builds require an approved deployment environment and
are not run by this repository task.
