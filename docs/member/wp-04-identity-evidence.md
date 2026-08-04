# WP-04 Member identity evidence

## Implemented requirement mapping

| Requirements | Evidence |
|---|---|
| MEM-014..MEM-019 | UUID `users` and Member-owned `members` records; immutable UUID MRN; namespaced external identifiers; nullable email/phone; keyed NIK lookup. |
| MEM-084..MEM-085 | Private verification-asset references, approved-current state, replacement lineage/history, target-specific owner/guardian/administrator access, and bounded allowlisted grants. |
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
- Online adult registration completes against the authenticated existing
  unbound User, creates no second User, attributes registration assets to that
  User, and rejects a User already bound to a Member. Administrator
  registration and child registration require the exact registration
  permission. Adult activation locks the Member and User, requires verified
  current KTP and profile-photo evidence, is idempotent, and records sanitized
  audit evidence.
- Registration asset recording resolves the trusted context through the
  Member authorization provider; caller-supplied context fields are only
  checked as assertions and mismatches fail before demotion, insertion, or
  audit. The operation remains atomic inside registration and transactional
  when called independently.

## Verification-asset state invariants

- Pending KTP, KIA, and profile-photo replacements are never current.
- Approval locks the Member row, demotes the previous approved current asset,
  promotes the replacement, and retains `replaces_id` lineage in one
  transaction.
- Profile photographs have one current slot; KTP and KIA share one current
  identity-document slot. Approval revalidates age eligibility, KTP after age
  17 demotes KIA, stale KIA approval fails closed, and `members.identity_document_type`
  is updated transactionally to the sole approved current KTP or KIA.
- Concurrent MySQL recording and approval workers leave exactly one approved
  current asset in the relevant slot and keep Member document metadata aligned
  with that asset. Grants require an allowlisted audience and do not exceed the
  configured maximum TTL; the exact maximum boundary is accepted and
  excessive TTL is rejected.

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
  than discard identity data. This irreversible boundary is awaiting explicit
  approval; no reversible strategy is claimed.
- `2026_08_04_000008_create_member_identity_tables` is the final Member-table
  migration and is the only migration rolled back by the MySQL verification
  script.

## Observed verification

- `composer validate --strict` — passed.
- `composer audit` — no security vulnerability advisories found.
- `vendor/bin/pint --test` — passed.
- `php artisan test` — 79 tests, 987 assertions; 74 passed and 5 MySQL-only
  tests skipped under the SQLite test configuration.
- `npm run build` — passed; Vite emitted the existing optional `fontaine`
  optimization warning.
- `bash deployment/verify-mysql.sh` — passed: MySQL 8.4 fresh migration;
  Member suite 17 tests/113 assertions; Integration suite 8 tests/49
  assertions; full PHP suite 79 tests/1020 assertions; Member identity
  migration rollback/reapplication; populated legacy users and sessions
  preserved during UUID upgrade; UUID user migration forward-only notice.
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
