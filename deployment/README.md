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
  private MPIPS network; and
- validation-only CI/CD policy.

The MPIPS service, its image, resource limits, credentials, storage, and
runtime isolation are not deployed or configured by this repository. The
`MPIPS_NETWORK_NAME` value identifies a pre-provisioned private network owned
by the MPIPS repository. MHCS exposes no MPIPS public route and defines no
MPIPS service.

The source deployment workflow was not copied because its SSH and live
deployment actions are outside WP-02. No production values, hosts, domains,
certificates, credentials, or keys are stored here.

Static validation:

    bash deployment/validate.sh

Docker Compose and image builds require an approved deployment environment and
are not run by this repository task.
