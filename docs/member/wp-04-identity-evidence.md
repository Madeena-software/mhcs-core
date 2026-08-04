# WP-04 Member identity evidence

## Implemented requirement mapping

| Requirements | Evidence |
|---|---|
| MEM-014..MEM-019 | UUID `users` and Member-owned `members` records; immutable UUID MRN; namespaced external identifiers; nullable email/phone; keyed NIK lookup. |
| MEM-084..MEM-085 | Private verification-asset references, approved-current state, replacement lineage/history, target-specific owner/guardian/administrator access, and short-lived grants. |
| MEM-213 | Atomic child registration, protected family grouping, verified guardian relations, dependent authorization, dependent-recovery rejection, age-17 transition, and audit records. |
| MEM-219 | One shared authentication-eligibility policy, independent account/login state, exact-purpose administrator permissions, asset-state locks, idempotent operations, and rollback tests. |

## Authentication and authorization invariants

- `User::canAuthenticate()` is the shared eligibility policy used by Laravel
  authentication and `CredentialVerifier`; it rejects `pending_activation`,
  suspended, login-disabled, and mandatory-password-replacement accounts.
- Verification-asset grants require the target Member to be the owner, the
  actor to be an active verified guardian for that Member and asset purpose, or
  the actor to have administrator role plus the exact `member.asset.read`
  permission. An asset ID alone is insufficient.
- Administrator operations use separate exact permissions for registration,
  identity verification, asset access, guardian management, account state,
  assisted recovery, and age transition.
- Assisted recovery rejects dependent or login-disabled accounts; dependent
  credentials are issued only by the approved age-17 transition.

## Verification-asset state invariants

- Pending KTP, KIA, and profile-photo replacements are never current.
- Approval locks the Member row, demotes the previous approved current asset,
  promotes the replacement, and retains `replaces_id` lineage in one
  transaction.
- Concurrent MySQL approval workers leave exactly one approved current asset
  for a Member and asset type.

## Schema and migration boundary

- `users` owns UUID authentication IDs, nullable canonical email, password hash,
  account status, login enablement, and mandatory credential replacement state.
- `members` owns the UUID Member ID, one-to-one user binding, family reference,
  opaque UUID MRN, encrypted NIK and keyed NIK digest, demographics, phone,
  document type, identity state, and immutable registration source.
- `families` stores encrypted KK display data and a keyed exact-match digest.
- `member_verification_assets`, `member_guardians`,
  `member_external_identifiers`, and `member_operations` are Member-owned.
- `2026_08_04_000007_migrate_users_to_uuid` remains forward-only. The upgrade
  preservation test observed legacy authentication fields retained and legacy
  session user references remapped to UUIDs; `down()` refuses to run rather
  than discard identity data. Explicit approval is still required for any
  irreversible migration boundary.
- `2026_08_04_000008_create_member_identity_tables` is the final Member-table
  migration and is the only migration rolled back by the MySQL verification
  script.

## Observed verification

- `composer validate --strict` — passed.
- `composer audit` — no security vulnerability advisories found.
- `vendor/bin/pint --test` — passed.
- `php artisan test` — 69 tests, 929 assertions; 67 passed and 2 MySQL-only
  tests skipped under the SQLite test configuration.
- `npm run build` — passed.
- `bash deployment/verify-mysql.sh` — passed: MySQL 8.4 fresh migration;
  Member suite 10 tests/79 assertions; Integration suite 5 tests/26
  assertions; full PHP suite 69 tests/939 assertions; Member identity
  migration rollback/reapplication; UUID user migration forward-only notice.
- `bash deployment/validate.sh` — passed, including Docker build, isolated
  application startup, health check, and static validation.

## Out of scope and residual decisions

- Privacy notice, lawful basis, retention, deletion/anonymization, regulated
  identity-asset procedure, exceptional identity-document eligibility, and
  continued guardian authority under legal exceptions remain approval
  boundaries.
- Document authenticity, OCR, Dukcapil verification, face matching, biometric
  decisions, credential delivery, UI, bookings, points, FHIR, and production
  operations remain out of scope.
- The encrypted local object boundary uses synthetic fixture bytes; production
  object-storage policy and retention remain unresolved.
- MRN/source mutation protection is application/model-level; direct privileged
  SQL can bypass it until a database-specific immutable trigger or policy is
  approved.
