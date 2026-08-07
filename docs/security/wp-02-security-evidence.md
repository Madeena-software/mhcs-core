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

## MVP-04B audit identifier sanitizer remediation addendum — 2026-08-07

This bounded remediation ran from `7074f2eea5e8c7368418dac966f111c4d96ddedd`
with reviewed candidate `96f59e9efcf15adf497aaa44e57a8a8f64a071a2` as an
ancestor and execution HEAD `3ca3698ac447dc28afec3b307f8ef54cab30b9fc` on
`main`. The canonical target was `/var/www/mhcs-core`, whose remote is
`git@github.com:Madeena-software/mhcs-core.git`. The only pre-existing
untracked path was the published task file; no stage, commit, push, reset,
clean, stash, dependency, migration, external-system, or production action
occurred.

The task validator passed with exit status 0:

```text
python3 .agents/skills/agent-task/scripts/validate_task.py .agents/tasks/mhcs-core-mvp-04b-audit-identifier-sanitizer-remediation-v1.md
Task contract is valid
```

`python` is not installed; `python3` was the available equivalent. Repository
read/write, shell, Codebase Memory MCP, and ponytail capabilities were
available. Ponytail remained active at full level and no subagents were used.

Initial Codebase Memory status was project `mhcs-core` at the canonical root,
with 4,401 nodes and 10,608 edges; its Branch node reported the older
`2e08eae...` SHA instead of the Git HEAD. The least-cost fast refresh completed
but did not repair that Branch metadata, so the permitted full recovery rebuilt
the graph to 4,427 nodes and 10,896 edges. After the sanitizer/test edit, the
required source-change refresh was fast mode and completed at 4,428 nodes and
10,851 edges. Final architecture/search/trace checks succeeded. The provider's
Branch node still reports the older SHA; this is a tooling metadata residual,
not a source-graph or Git identity claim.

Before the fix, these deterministic checks reproduced the defect:

```text
php artisan tinker --execute="App\\Shared\\Security\\SensitiveDataSanitizer::assertSafe(['case_id' => '00000000-0000-4000-8000-123456789012']);"
-> SensitivePayloadException: Sensitive scalar values are not allowed in audit metadata.
php artisan tinker --execute="App\\Shared\\Security\\SensitiveDataSanitizer::assertSafeString('<standalone 12-digit value>');"
-> SensitivePayloadException: Sensitive data is not allowed in a security record.
```

The fix is one shared predicate distinction: `Illuminate\\Support\\Str::isUuid`
recognizes a complete UUID before the raw `10..20` digit check. The existing
secret, sensitive-key, clinical-text, binary/data-URL, object/resource, and
control-character checks remain unchanged. No audit field, append path, or
transaction boundary changed.

The deterministic regression passed with exit status 0: `1` test and `9`
assertions. It appended a canonical UUID as both audit target and allowed
operational metadata, then verified standalone 10-, 12-, and 20-digit values
remain rejected by both `assertSafeString()` and `assertSafe()`.

Final focused verification, each run separately with exit status 0 and no
warnings, skips, or failures reported:

| Command | Result |
|---|---:|
| `vendor/bin/phpunit tests/Security/Wp02SecurityTest.php` | 24 tests, 103 assertions |
| `vendor/bin/phpunit tests/Feature/Operator/Mvp04bIdentityVerificationTest.php` | 16 tests, 84 assertions |
| `vendor/bin/phpunit tests/Feature/Operator/Mvp04OperatorPortalTest.php` | 8 tests, 63 assertions |
| `vendor/bin/phpunit tests/Architecture/FoundationArchitectureTest.php` | 6 tests, 1,539 assertions |
| `php -l app/Shared/Security/SensitiveDataSanitizer.php && php -l tests/Security/Wp02SecurityTest.php` | passed |
| `vendor/bin/pint --test app/Shared/Security/SensitiveDataSanitizer.php tests/Security/Wp02SecurityTest.php` | passed after Pint formatting |
| `git diff --check` | passed |

Pint's first check exited 1 only because it reported three formatting fixers;
`vendor/bin/pint app/Shared/Security/SensitiveDataSanitizer.php tests/Security/Wp02SecurityTest.php`
then exited 0 and the repeated Pint check exited 0. No test warnings, skips,
or failures remained.

MVP-04B audit construction remains mandatory and append-only. The remaining
unrun checks are browser/Playwright/Pest, full PHPUnit, complete Work Package
suites, MySQL/Docker conformance, npm/dependency installation, Composer audit,
CI, deployment, production, and external integrations. MVP-04 and the related
MVP gaps/Work Packages remain partial or open as recorded elsewhere.
