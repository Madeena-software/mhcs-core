# WP-02 security and privacy evidence

This artifact records the locally verifiable WP-02 foundation. It is not a
production security certification, legal/privacy approval, clinical approval,
MPIPS verification, or deployment record.

## Assets and actors

- Authentication accounts and password state.
- Protected identifiers and their lookup digests.
- Member-safe projection data.
- Encrypted private object bytes and opaque object metadata.
- Audit evidence, correlation identifiers, and technical logs.
- Image conversion manifests, checksums, validator evidence, and worker policy.
- Actors are members, operators, doctors, administrators, queue workers, the
  Image Gateway worker, and the separate MPIPS service.

## Trust boundaries and entry points

- Browser/request claims are untrusted and cannot construct actor, role,
  permission, site, case, assignment, or purpose context.
- Laravel authentication/session state is the trusted application context.
- Internal module calls use in-process contracts and shared context.
- Only the Image Gateway worker is allowed to cross the future private MPIPS
  boundary.
- Private object access requires a trusted actor, named purpose, audience, and
  unexpired MAC grant.
- No public MPIPS proxy, public private-object disk, or production route was
  added.

## Controls

- Laravel's adaptive password hasher remains the User password boundary, and
  the configured account-state provider denies suspended and mandatory-change
  accounts during Laravel authentication.
- Protected display values use encryption; lookup values use an injected
  deterministic HMAC key. Missing key material fails closed.
- Temporary credentials use random bytes, persist only a hash and mandatory
  replacement state, and support replacement/invalidation.
- Credential verification uses a dummy hash for unknown identifiers, one public
  failure, privacy-safe pair/origin/identifier rate keys, suspension checks,
  and sanitized audit records. Only failed authentication increments the
  identifier-plus-trusted-origin, trusted-origin, and broader identifier-only
  counters; success clears the pair and identifier counters without clearing
  origin abuse evidence. Invalid or inconsistent throttling configuration
  fails closed.
- Audit events are append-only through the application interface, carry
  context/times/source/outcome, reject duplicate IDs and sensitive metadata,
  and participate in the caller's transaction.
- Correlated log context is recursively sanitized; the security logger requires
  a trusted correlation identity.
- Member projections are explicit scalar allowlists, require operator scope,
  and accept no unrestricted model/array serialization or binary implementation.
- Private local objects are encrypted before persistence, use opaque keys, and
  have no permanent URL method.
- Transactional row locking validates table identifiers and trusted context;
  funding values are immutable and B2B/B2C mismatches fail closed.
- Image input bounds are declarative and fail closed when any bound or accepted
  form is missing. No parser, Python runtime, pickle loader, or conversion
  implementation is present.
- Manifest signatures bind conversion identity, all input checksums, version,
  issue time, correlation, and key ID. Permanent acceptance requires explicit
  matching validator evidence.
- Image policy and manifest signer bindings fail closed when injected limits or
  key material are absent.

## Deployment and CI

The applicable external authority is
Madeena-software/deploy-templates, branch main, commit
569a30d4a089b0ee404ed6e963fdd2dfd96d3787, source family templates/prod.
Specializations are provenance-marked in Docker and Compose files.

The versioned configuration defines web, queue, scheduler, and Image Gateway
worker roles, one database, and one cache/queue foundation. The worker may
attach to an externally managed private MPIPS network; MHCS does not define
the MPIPS service, image, credentials, storage, or resource limits. MPIPS
deployment and isolation remain owned by the separate MPIPS repository.

The versioned validation workflow runs Composer validation/audit, formatting,
the complete PHP suite, the frontend build, and deployment validation. It has
no deployment, production, staging, SSH, or secret-writing step.

## Observed verification

- WP-01 baseline before changes: Composer validation, Composer audit, 19
  PHPUnit tests/67 assertions, and Vite build passed.
- `composer validate --strict` passed.
- `composer audit` passed with no security vulnerability advisories.
- `vendor/bin/pint --test` passed.
- `php artisan test` passed: 54 tests/493 assertions.
- `npm run build` passed; Vite reported only the existing optional `fontaine`
  optimization notice.
- `bash deployment/validate.sh` passed, including Docker Compose config
  validation, the Docker image build, and the isolated application
  startup/health smoke test.

## Unresolved decisions and dependencies

- Production image bounds require measured device/operations values.
- Exact MPIPS transport, authentication, idempotency, retry, error mapping,
  result identifiers, and separate-service behavior remain external contracts.
- The MPIPS repository must provision the private network and its own bounded
  service/container policy before the MHCS image worker can use it.
- Object-storage provider, retention, lawful basis, privacy notice, and
  deletion/anonymization procedure require approval.
- Deployment execution and real network/container isolation require the
  approved CI/CD environment.

## Residual risks

- Third-party code can bypass the application logging wrapper; platform log
  collection and dependency review must remain part of deployment controls.
- Application-level append-only audit is not database-level immutability.
- SQLite tests prove transaction behavior but not multi-connection production
  lock scheduling.
- The local encrypted object provider is a testable provider-neutral boundary,
  not proof of cloud bucket policy or key rotation.
- The separate MPIPS service, its repository, and real container sandbox were
  not executed or inspected.
