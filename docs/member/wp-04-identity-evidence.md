# WP-04 Member identity evidence

## Implemented requirement mapping

| Requirements | Evidence |
|---|---|
| MEM-014..MEM-019 | UUID `users` and Member-owned `members` records; immutable UUID MRN; namespaced external identifiers; nullable email/phone; keyed NIK lookup. |
| MEM-084..MEM-085 | `member_verification_assets` metadata, private-object references, KTP/KIA/profile-photo review and replacement history, short-lived grant retrieval. |
| MEM-213 | Atomic child registration, protected family grouping, verified guardian relations, dependent authorization, age-17 transition, and audit records. |
| MEM-219 | Independent account/login state, Member identity state, asset state, guardian state, suspension/restore transitions, idempotent operations, and rollback tests. |

## Schema and ownership summary

- `users` owns UUID authentication IDs, nullable canonical email, password hash,
  account status, login enablement, and mandatory credential replacement state.
- `members` owns the UUID Member ID, one-to-one user binding, family reference,
  opaque UUID MRN, encrypted NIK and keyed NIK digest, demographics, phone,
  document type, identity state, and immutable registration source.
- `families` stores encrypted KK display data and a keyed exact-match digest.
- `member_verification_assets`, `member_guardians`,
  `member_external_identifiers`, and `member_operations` are Member-owned.
- A forward migration maps legacy numeric user IDs to UUIDs and rewrites session
  references. No existing local database file or non-test identity data was
  present before migration execution.

## State and authorization invariants

- Adults receive one UUID user and one UUID Member; children receive a
  login-disabled user with a random stored hash and no handed-off credential.
- Email and NIK resolve through the shared generic credential verifier. KK does
  not resolve as a login identifier; unknown and incorrect credentials share the
  public failure result.
- Guardian authorization resolves the acting guardian from trusted context,
  requires an active verified relation and account, and returns only the two
  Member IDs, purpose, and authorization time.
- Age-17 transition requires age, current approved KTP, trusted administrator
  context, and an idempotency identity; it activates login with mandatory
  credential replacement and ends ordinary guardian relations atomically.
- Suspension changes login access only. Member, asset, guardian, MRN, external
  identifier, and audit history remain present.
- MRN, registration source, and NIK lookup binding are protected by the Member
  model's mutation guard; database uniqueness and foreign keys protect identity
  cardinality and lookup uniqueness.

## Private assets and recovery boundaries

- Registration and replacement store only opaque private-object references plus
  checksum, size, format, review, current, uploader, reviewer, and lineage
  metadata. Bytes remain behind the accepted encrypted private-object store.
- Asset retrieval requires trusted context, purpose, audience, correlation, and
  a short-lived access grant. No public URL or unrestricted Member projection is
  introduced.
- Assisted recovery requires trusted administrator context, protected exact-match
  NIK and KK evidence, current approved identity and profile-photo assets, and
  an operation identity. It preserves suspension, stores only a hash, returns a
  temporary credential once, and stores no plaintext credential in audit or
  operation results.

## Migration approach and compatibility notes

- Historical migrations were preserved. `2026_08_04_000007_migrate_users_to_uuid`
  performs a forward copy of users and sessions, preserving auth fields and
  remapping session user references; it is intentionally forward-only.
- `2026_08_04_000008_create_member_identity_tables` adds Member identity,
  family, external identifier, verification-asset, guardian, and operation
  tables with UUID-style opaque keys and portable constraints.
- Clean SQLite migrations and deployment MySQL migration smoke both passed.
- No Composer or npm dependency, framework constraint, module boundary, route,
  UI, external adapter, or deployment policy was added or changed.

## Observed verification

- `python3 .agents/skills/agent-task/scripts/validate_task.py .agents/tasks/mhcs-core-wp-04-member-identity-accounts-guardians-recovery-v1.md` — passed.
- Accepted baseline ancestry — `dbfe6c09deaf4d05bdd67b7656a4678cd2f3b387` is an ancestor of `HEAD`.
- Focused WP-04 suite — 5 tests, 59 assertions, passed.
- Complete PHPUnit suite — 59 tests, 883 assertions, passed.
- `composer validate --strict` — passed.
- `composer audit` — no security advisories.
- `vendor/bin/pint --test` — passed.
- `npm run build` — passed.
- `bash deployment/validate.sh` — passed, including Docker build, MySQL migration
  smoke, isolated startup, health check, and static validation.
- Final migration status — all migrations through `2026_08_04_000008` ran.

## Unresolved privacy/legal/identity decisions

- Privacy notice, lawful basis, retention, deletion/anonymization, and regulated
  identity-asset procedure remain approval boundaries.
- Exceptional identity-document eligibility and continued guardian authority
  under incapacity, court order, or other legal exceptions are not implemented.
- Document authenticity, OCR, Dukcapil verification, face matching, and biometric
  decisions remain out of scope.

## Residual risks

- The accepted local encrypted-object boundary is used with synthetic fixture
  bytes only; production object-storage policy and retention remain unresolved.
- MRN/source mutation protection is application/model-level; direct privileged
  SQL can bypass it until a database-specific immutable trigger/policy is
  approved.
- No credential delivery, printing, email/SMS, public registration, login UI,
  recovery UI, or production deployment behavior was added.
