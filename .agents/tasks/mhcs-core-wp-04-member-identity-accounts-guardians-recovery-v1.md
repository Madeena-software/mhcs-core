---
name: mhcs-core-wp-04-member-identity-accounts-guardians-recovery
description: Implement the bounded MHCS Core Member identity, account, verification-asset, guardian, age-transition, login-integration, and assisted-recovery foundation on the accepted WP-01 and WP-02 baseline without adding final UI, B2B import, production credential delivery, FHIR mapping, or booking and financial workflows.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core WP-04 Member Identity, Accounts, Guardians, and Recovery

## Objective

Implement the bounded `WP-04 — Member identity, accounts, guardians, and recovery` work package in `$TARGET` on top of the accepted WP-01 and WP-02 baseline.

Implement only these requirement assignments:

- `MEM-014` through `MEM-019`;
- `MEM-084` through `MEM-085`;
- `MEM-213`; and
- `MEM-219`.

The observable outcome is a locally verifiable Member identity foundation that:

- separates authentication/login state in `users` from healthcare identity and demographics in Member-owned records;
- uses stable local identifiers, an immutable globally unique MHCS medical-record number, and optional external patient identifiers that never replace local primary keys;
- creates adults and children through one atomic Member registration boundary while preserving different login eligibility;
- protects NIK and KK using the accepted WP-02 encrypted-display and keyed-lookup foundation;
- stores KTP, KIA, and profile photographs through the accepted private encrypted-object boundary, preserving current status and replacement history without public URLs;
- represents verified guardian relationships explicitly, attributes dependent actions to the acting guardian, and never shares child credentials;
- supports the standard age-17 transition from dependent access to independently activated access after approved KTP verification;
- integrates registered email or NIK identities with the accepted generic, enumeration-resistant credential-verification foundation;
- provides an audited assisted-recovery application contract for members without email or phone, using current approved identity evidence and the accepted temporary-credential foundation;
- preserves independent account, member, verification, guardian, and suspension state histories; and
- proves the declared identity and authorization invariants through schema, application, negative, transaction, and regression tests.

A `succeeded` outcome means the assigned WP-04 code, migrations, tests, and bounded evidence are complete and all required verification passes. It does not mean privacy/legal retention policy is approved, final member/admin UI exists, a real identity document was processed, a credential was delivered through email/SMS/print, or production deployment occurred.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
  - `network`
- Ordered model preferences: None.
- Require preferred model: `false`

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Context and evidence

Read completely before planning or writing:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-source-coverage.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- `$TARGET/.agents/tasks/mhcs-core-wp-01-application-architecture-foundation-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-wp-02-security-privacy-audit-operations-v1.md`;
- the complete current implementation and tests for WP-01 and WP-02; and
- all current files relevant to `User`, authentication, protected identifiers, temporary credentials, credential verification, authenticated context, authorization, audit, private encrypted objects, access grants, transactions, events, outbox, idempotency, clocks, migrations, factories, and architecture tests.

Treat `dbfe6c09deaf4d05bdd67b7656a4678cd2f3b387` as the accepted WP-02 implementation baseline. The current execution commit may be a descendant that publishes this task. Stop as `awaiting-approval` if the target history does not contain that accepted baseline or if current repository evidence materially contradicts the declared prerequisites.

Confirm that the implementation plan still assigns exactly `MEM-014..MEM-019`, `MEM-084..MEM-085`, `MEM-213`, and `MEM-219` to WP-04. Stop as `awaiting-approval` if the assignment changed, overlaps another active task, or requires silently reclassifying requirements.

The following source-derived constraints are binding:

- `users` owns authentication credentials and login state; Member-owned records own healthcare identity and demographics.
- Every adult Member receives both a user and a member record.
- A child receives a member record and a login-disabled user record; guardians act through their own verified accounts.
- Email and phone are optional. Login uses one generic identifier that may be email or NIK. KK is never a login identifier.
- NIK is mandatory but is not a local primary key. The standard identity-document path is KTP at age 17 or older and KIA below age 17 where applicable.
- Every registration requires a separate profile photograph.
- `users.id`, `members.id`, and optional external patient identifiers have distinct purposes.
- The MHCS medical-record number is immutable and globally unique.
- Guardian relations are explicit verified relations, not shared credentials or relations inferred only from KK.
- At age 17, approved KTP verification permits independent access and ordinary guardian access ends unless a separately approved legal authority continues it.
- Suspension controls login access and does not delete the Member identity or its history.
- Privacy notice, lawful basis, retention, deletion/anonymization procedure, exceptional identity-document eligibility, and continued guardian legal authority remain approval boundaries and must not be invented.

Use repository evidence and observed command output. Model output, generated summaries, editor diagnostics, and task text alone are not verification.

## Scope and constraints

### Repository safety

Before changes:

1. Resolve `$TARGET` to a canonical absolute path.
2. Confirm the expected repository, branch, current commit, and ancestry from the accepted WP-02 baseline.
3. Record staged, modified, untracked, and relevant ignored paths.
4. Record installed PHP, Composer, Node, npm, database, and Docker/Compose tooling relevant to verification.
5. Preserve all pre-existing work.
6. Stop as `awaiting-approval` if existing work overlaps required files or if a safe migration would overwrite or discard non-test data.
7. Do not reset, clean, discard, stash, stage, commit, push, rewrite history, open pull requests, trigger deployment, or mutate production/staging.

Do not modify:

- `.agents/`;
- this or any other published task file;
- `docs/implementation/`;
- requirement counts, assignments, classifications, or source digests;
- deployment topology or CI policy unless a direct WP-04 regression requires a minimal approved correction; or
- unrelated Operator, Doctor, Image Gateway, booking, points, clinical, imaging, report, payout, design, or language files.

### Dependency and framework boundary

Use existing dependencies and framework primitives.

Stop as `awaiting-approval` before:

- adding or replacing a direct Composer or npm dependency;
- adding an authentication starter kit, role/permission package, identity package, audit package, storage SDK, notification provider, FHIR library, or document-processing library;
- changing PHP, Laravel, Filament, database-engine, or frontend constraints; or
- changing module ownership or introducing a network boundary.

Do not process KTP/KIA content, perform OCR, call Dukcapil, infer identity validity from image bytes, or implement biometric/face matching.

### Identifier and primary-key model

Implement the approved distinction among:

- authentication identifier;
- Member identifier;
- immutable MHCS medical-record number; and
- optional external patient identifiers.

Reconcile the current scaffold with the approved UUID-oriented identity model. New identity-domain records must use stable opaque UUID identifiers unless current accepted architecture supplies an equivalent approved identifier abstraction.

If the current numeric `users.id` scaffold cannot be migrated safely while preserving existing references and non-test data, stop as `awaiting-approval`. Do not run destructive resets or silently retain an incompatible identity model.

The medical-record number must:

- be globally unique and immutable;
- be generated by one explicit application/domain contract;
- use an opaque collision-resistant value without encoding NIK, date of birth, site, gender, or other sensitive/business meaning; and
- be enforced by database uniqueness and mutation-negative tests.

External patient identifiers must:

- remain optional integration metadata;
- record an explicit namespace/system and value;
- never serve as the local primary key, login identifier, or medical-record number; and
- make no FHIR profile or terminology conformance claim in WP-04.

### User and Member separation

Implement one-to-one authentication and Member identity records.

`users` owns only authentication/login concerns, including when applicable:

- local authentication ID;
- optional unique email;
- password hash or securely disabled credential state;
- account status;
- mandatory password-change state; and
- framework session/authentication fields.

Member-owned records own identity and demographics, including when applicable:

- Member ID;
- user ID;
- family reference;
- immutable medical-record number;
- protected NIK display and lookup values;
- identity-document type;
- name;
- birth date;
- administrative gender;
- immutable registration source; and
- optional phone.

Do not retain a second authoritative demographic name, birth date, NIK, KK, phone, or medical-record number in `users`.

Email and phone must be optional. Multiple null email values must remain valid while non-null canonical emails remain unique.

Registration source must be immutable metadata limited to the approved source meanings `online`, `walk_in`, and `administrator`. WP-04 supplies the reusable identity-registration boundary; it does not implement the later walk-in transaction or B2B import workflow.

### Protected NIK and KK

Reuse the accepted WP-02 `ProtectedIdentifierService` or its approved replacement. Do not fork cryptography or add reversible/plain lookup columns.

For NIK and KK:

- store encrypted authorized-display values separately from deterministic keyed lookup digests;
- require non-empty canonical input and database uniqueness where the approved model requires it;
- never use raw values in logs, audit metadata, URLs, events, exceptions, analytics, or operation results;
- never use KK as a credential or infer guardian authorization solely from a matching KK; and
- fail closed when key material is missing or invalid.

Implement only the minimum family identity record needed for protected KK grouping, child registration, guardian verification, and assisted recovery. Do not implement shared wallets, clinical family history, family bookings, or inferred access.

Do not invent unsupported Dukcapil validation, NIK semantics, marriage exceptions, document-expiry rules, or exceptional identity-document eligibility. Standard-path validation may enforce only source-supported age/document relationships and required presence. Unsupported exceptions stop as `awaiting-approval` or fail closed at the application boundary.

### Atomic Member registration

Provide one explicit Member application command/service for atomic registration.

The registration transaction must, as applicable:

- validate the standard adult or child registration path;
- create or bind one authentication record;
- create exactly one Member record;
- create or match the protected family identity by KK where required;
- allocate one immutable globally unique medical-record number;
- protect and persist NIK and KK;
- persist required private verification-asset references and metadata;
- create required verified guardian links for a child;
- append sanitized audit evidence in the same transaction;
- preserve idempotency or reject replay/conflict through an explicit operation identity; and
- roll back all local state, audit, outbox, and idempotency evidence together on failure.

The operation must reject:

- duplicate protected NIK;
- duplicate medical-record number;
- more than one Member for one user;
- a missing required KTP/KIA or profile photograph;
- a child without an eligible previously verified guardian;
- a self-guardian relation;
- a guardian without their own active verified Member account;
- caller-supplied actor/admin authority not present in trusted context; and
- an attempted registration that would silently merge ambiguous identities.

Adult registration may accept a password through a trusted application boundary and persist only its adaptive hash. Child registration must create no usable or handed-off child credential and must remain unable to authenticate independently before approved age transition.

Do not implement public registration pages, final request validation/copy, email verification, SMS, printing, notification delivery, B2B import, bulk matching, or production credential handoff.

### Account and Member states

Preserve independent state dimensions rather than one overloaded status.

Account state controls login only and must support the approved progression and audited transitions among `pending_activation`, `active`, and `suspended`, including restoration from suspension where authorized.

Member identity existence, registration source, verification-asset history, guardian history, and external identifiers must survive account suspension.

Do not hard-delete users, members, families, verification assets, guardian relations, or audit records through WP-04 application services.

Implement explicit transition guards so:

- pending or child-login-disabled accounts cannot authenticate normally;
- suspended accounts cannot authenticate;
- ordinary activation requires the applicable approved identity evidence;
- temporary/recovery credentials cannot be used as unrestricted sessions before mandatory replacement;
- unauthorized transitions fail closed; and
- duplicate/replayed transition commands do not create contradictory state or duplicate audit evidence.

### Identity verification assets

Reuse the accepted WP-02 `PrivateObjectStore`, opaque object keys, encrypted persistence, access-grant, trusted-context, purpose, checksum, and correlation boundaries.

Persist Member verification-asset metadata separately from object bytes. The metadata must support:

- Member reference;
- type distinguishing at least KTP, KIA, and profile photograph;
- opaque private-object reference;
- checksum and safe size/format metadata already produced by the private-object boundary when applicable;
- review state;
- current/non-current state;
- uploader;
- reviewer and review time when approved/rejected;
- replacement lineage; and
- creation/recorded times.

Enforce:

- one current identity document appropriate to the standard age path;
- one approved current profile photograph;
- preservation of all prior profile photographs;
- preservation of replaced identity-document history;
- transactional replacement so zero or multiple current approved assets are not observable;
- no public disk, permanent public URL, inline database blob, or direct object key in unauthorized projections; and
- authorized retrieval only through short-lived access grants with trusted actor, purpose, audience, target, expiry, and correlation checks.

Use synthetic fixture bytes only. Do not add real KTP/KIA/profile images, image recognition, OCR, biometric comparison, or cloud object-storage configuration.

### Guardians and dependent access

Implement explicit Member-owned guardian relations with at least:

- child Member ID;
- guardian Member ID;
- verified status/history;
- verifying administrator identity;
- start time;
- end time when access ends; and
- audit evidence for creation, activation, ending, and exceptional attempts.

A valid guardian must have their own previously verified Member identity and authenticating account. More than one active verified guardian may be linked to one child, and each valid guardian has equal authorization at this identity boundary.

Provide a guardian/dependent authorization contract that:

- starts from trusted authenticated actor context;
- resolves the actor's Member identity server-side;
- verifies an active guardian relation to the selected child;
- attributes every allowed dependent action to the acting guardian;
- rejects caller-supplied guardian identity, child credentials, shared passwords, common-KK-only claims, ended relations, self-relations, and unrelated Members; and
- returns only the minimum identity result needed by later Member workflows.

Do not implement bookings, points, results, clinical records, final dependent-profile UI, or broad Member serialization. Later work packages will call this authorization boundary.

### Age-17 transition

Provide an idempotent audited application command for the standard independent-access transition.

The transition must require:

- the Member is at least 17 according to the trusted clock and stored birth date;
- a current approved KTP asset;
- trusted authorized administrator or approved activation context;
- a child/pending account that is not already independently active;
- secure credential establishment or one-time temporary credential issuance through the accepted WP-02 foundation; and
- one atomic update of account activation and ordinary guardian-ending state.

On success:

- independent authentication becomes possible only after required password establishment/replacement;
- ordinary guardian access ends at the recorded transition time;
- prior guardian and asset history remains queryable for audit; and
- repeated execution returns the original result or a safe already-completed result without duplicating state or audit events.

Do not implement continued guardian access based on legal incapacity, court order, or other exceptional authority without a separately approved policy and evidence. Stop as `awaiting-approval` before implementing such continuation.

### Login integration and mandatory password replacement

Integrate persisted Member identities with the accepted WP-02 credential-verification foundation rather than creating a second login engine.

Prove that:

- one generic identifier accepts canonical email or NIK;
- email and NIK resolve to the same user when both belong to that Member;
- KK never resolves as a login identifier;
- unknown identifiers and incorrect passwords produce the same public failure;
- rate limiting, dummy-hash behavior, suspension denial, and sensitive logging protections remain intact;
- child/pending accounts cannot authenticate independently; and
- account suspension leaves the Member identity and verification history intact.

Provide the minimum application service needed to complete mandatory password replacement after a valid one-time temporary/recovery credential. It must verify the current temporary credential through an isolated secure path, persist only the replacement hash, clear mandatory-change state atomically, invalidate prior temporary credentials, audit the transition, and never expose the existing hash or log either plaintext password.

Do not add final login/password-change Blade pages, social login, phone login, passwordless login, remember-device policy, email/SMS delivery, or session UI.

### Assisted recovery

Provide an administrator-assisted recovery application contract for a Member who may lack email and phone.

Recovery must:

- require trusted authenticated administrator context and a named recovery purpose;
- resolve the target through protected exact-match lookup without returning raw NIK/KK;
- require current approved identity-document and profile-photo evidence plus applicable protected KK/family evidence;
- reject incomplete, mismatched, stale, or unapproved evidence;
- prevent unauthorized callers from learning whether an account exists;
- issue or replace one random temporary credential through the accepted WP-02 issuer;
- persist only the password hash and mandatory-replacement state;
- return plaintext only once to the authorized immediate caller;
- never log, audit, store, enqueue, or include the plaintext in events;
- preserve suspension unless an independently authorized state transition restores access;
- invalidate prior recovery credentials; and
- append sanitized audit evidence for attempted, rejected, and successful recovery.

Use synthetic fixtures and application services only. Do not implement real document/face verification, credential printing, email/SMS/WhatsApp delivery, customer-support UI, public account-discovery endpoints, or retention/deletion policy.

### Audit, events, and data minimization

Reuse the accepted append-only audit and sanitized logging foundations.

Audit identity-domain operations when applicable, including:

- registration;
- activation and suspension transitions;
- verification-asset upload metadata, review, and replacement;
- guardian creation and ending;
- age transition;
- assisted recovery; and
- mandatory password replacement.

Audit metadata must use local opaque IDs, safe action/outcome/reason codes, digests, and correlation IDs. It must never contain raw NIK/KK, passwords, object bytes, public object links, clinical payloads, or unrestricted model serialization.

Emit versioned domain events only when an asynchronous or later-module consumer is justified by the approved architecture. Persist emitted events through the accepted outbox in the same transaction. Do not invent external consumers or send notifications.

Provide explicit safe projections/results. Do not return unrestricted Eloquent models or arrays containing credentials, encrypted protected values, raw lookup digests, permanent object keys, or unrelated demographics.

### Migration and compatibility rules

Implement forward migrations and model/factory updates that work on a clean database and preserve accepted WP-01/WP-02 tests.

Do not edit or rewrite historical migrations merely to make a fresh test database pass if doing so would make an already-applied environment inconsistent. If a forward migration for primary-key or nullability changes cannot be proven safe, stop as `awaiting-approval` with the exact migration conflict.

Migrations must provide database constraints where portable and application-level transaction/locking guards where cross-database constraints are unavailable. Tests must prove both success and negative/conflict behavior.

Do not seed production-like identities, real NIK/KK, or real document bytes. Factories and fixtures must use clearly synthetic values.

### Evidence artifact

Create a bounded `docs/member/wp-04-identity-evidence.md` artifact containing only:

- implemented WP-04 requirement mapping;
- schema and ownership summary;
- state and authorization invariants;
- private-asset and recovery boundaries;
- migration approach and compatibility notes;
- observed verification commands and results;
- unresolved privacy/legal/identity decisions; and
- residual risks.

Do not claim privacy/legal approval, identity-document authenticity, biometric verification, production storage policy, or notification delivery.

### Out of scope

Outside WP-04:

- final Blade, Livewire, or Filament Member/admin UI;
- final Indonesian UI copy, design-system implementation, rendered visual review, or accessibility acceptance;
- B2B import format, bulk import, business agreement matching, or production credential document handoff;
- bookings, schedules, quotas, attendance, walk-ins, points, wallets, payments, refunds, promotions, cash closing, or revaluation;
- clinical assessments, consent, family medical history, FHIR resources/profiles, imaging results, AI, doctor reports, or publication;
- Operator/Doctor/Image Gateway business workflows;
- real object storage, real KTP/KIA/profile images, OCR, biometric/face matching, Dukcapil calls, or document-authenticity decisions;
- privacy notice, lawful basis, retention, deletion, anonymization, or exceptional legal-guardian policy;
- email, SMS, WhatsApp, printing, external notifications, or customer-support tooling;
- production/staging access, deployment, SSH, real secrets, or infrastructure redesign; and
- commits, pushes, pull requests, issues, estimates, or delivery dates.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `12`
- Approval gates: Stop as `awaiting-approval` before adding dependencies, changing framework/database constraints, changing module ownership or public contracts, performing a destructive or data-losing migration, resolving privacy/legal/retention/document-eligibility/continued-guardian policy, inventing a medical-record-number business format, implementing real identity verification or credential delivery, adding final UI or public routes, modifying `.agents/` or `docs/implementation/`, triggering CI/deployment, accessing production/staging, using SSH, committing/pushing, or performing any destructive repository operation.

When the runtime supports delegated agents, the primary agent may use read-only specialists for identity-schema review, privacy/authorization review, and test-plan review. One primary writer owns integration and shared files. Delegation does not change scope, approval gates, iteration limits, or verification requirements. Continue without delegation when it is unavailable.

## Execution procedure

1. Resolve `$TARGET`, required capabilities, repository identity, clean-state evidence, and accepted-baseline ancestry.
2. Read all required instructions, source context, conformance documents, published tasks, and current WP-01/WP-02 implementation completely.
3. Confirm the exact WP-04 requirement assignment and map every assigned row to code, migration, tests, and evidence.
4. Run the complete accepted baseline verification before changes and record any pre-existing failures.
5. Inspect current user/auth schema, identifier services, private-object store, audit, context, transaction, event/outbox, idempotency, factories, and architecture tests.
6. Plan a forward-compatible identity schema and migration path. Stop for approval if existing non-test data or primary-key references cannot be preserved safely.
7. Implement identity value objects/enums/contracts and the user/Member/family/external-identifier data model.
8. Implement atomic adult and child registration using protected identifiers, private verification assets, MRN generation, audit, idempotency, and rollback.
9. Implement verification-asset review/replacement and authorized short-lived retrieval boundaries.
10. Implement verified guardian relations and trusted guardian/dependent authorization.
11. Implement the standard age-17 independent-access transition and ordinary guardian ending.
12. Integrate email/NIK login with the accepted verifier and implement mandatory temporary-password replacement.
13. Implement administrator-assisted recovery without external delivery or account enumeration.
14. Add schema, domain, application, authorization, transaction, idempotency, privacy, logging, migration, and architecture tests.
15. Create the bounded WP-04 identity evidence artifact from observed repository and command evidence.
16. Run focused WP-04 tests, then the complete verification suite. Inspect actual output and remediate only from evidence.
17. Re-read the unchanged task, inspect the final diff for scope creep and sensitive-data exposure, and stop with the appropriate terminal outcome.

## Acceptance criteria

- [ ] The current history contains the accepted WP-02 baseline and the implementation plan still assigns exactly `MEM-014..MEM-019`, `MEM-084..MEM-085`, `MEM-213`, and `MEM-219` to WP-04.
- [ ] Authentication/login state is owned by `users`; healthcare identity and demographics are owned by Member records without duplicate demographic authority in `users`.
- [ ] User, Member, family, verification-asset, guardian, and external-identifier records use stable opaque identifiers consistent with the approved identity model, with a safe forward migration or an explicit `awaiting-approval` stop before data loss.
- [ ] Every Member has exactly one linked authentication record and every authentication record created through WP-04 has at most one Member identity.
- [ ] Email and phone are optional; non-null canonical emails remain unique; NIK is mandatory and unique through its keyed lookup digest.
- [ ] The medical-record number is generated by one explicit contract, globally unique, immutable, opaque, and database constrained.
- [ ] Optional external patient identifiers are namespaced integration metadata and never replace local IDs, login identifiers, or MRNs.
- [ ] NIK and KK use separate encrypted-display and keyed-lookup values, fail closed without valid keys, and never appear raw in logs, audit, URLs, events, or operation results.
- [ ] Atomic adult registration creates the required user, Member, MRN, protected identifiers, identity assets, audit evidence, and idempotency state, with complete rollback on failure.
- [ ] Atomic child registration creates no usable child credential, requires KIA, profile photograph, KK, and at least one eligible previously verified guardian, and rejects self/unverified/common-KK-only guardian claims.
- [ ] Registration source is immutable and limited to approved values without implementing the later walk-in or B2B workflows.
- [ ] One current age-appropriate identity-document asset and one approved current profile photograph are enforced while all replacement history is preserved.
- [ ] Verification bytes remain encrypted and private; metadata stores opaque references; access requires a valid short-lived grant and trusted context; no permanent public URL exists.
- [ ] Guardian relations are explicit, verified, auditable, may support multiple equal active guardians, and never share or expose child credentials.
- [ ] Guardian/dependent authorization derives the acting guardian from trusted server context and rejects caller-supplied authority, ended relations, unrelated Members, and KK-only inference.
- [ ] The standard age-17 transition requires approved KTP evidence, activates independent access through secure credential establishment/replacement, ends ordinary guardian access atomically, preserves history, and is idempotent.
- [ ] Registered email and NIK work through the accepted generic credential verifier, while KK, child/pending accounts, suspended accounts, unknown identifiers, and incorrect passwords behave according to the accepted security invariants.
- [ ] Mandatory temporary-password replacement validates the current one-time credential through a restricted path, stores only the replacement hash, invalidates the prior credential, clears mandatory-change state atomically, and audits without plaintext.
- [ ] Assisted recovery requires trusted administrator purpose and approved identity/profile/KK evidence, does not disclose account existence to unauthorized callers, issues plaintext only once, stores only a hash, preserves suspension unless separately restored, and audits sanitized outcomes.
- [ ] Account suspension and restoration affect login only and do not erase Member, MRN, external identifier, asset, guardian, or audit history.
- [ ] Database and application constraints reject duplicate NIK, duplicate MRN, duplicate user/Member binding, contradictory current assets, invalid guardians, unauthorized transitions, replay conflicts, and partial transaction state.
- [ ] No final UI, B2B import, credential delivery, FHIR, booking, financial, clinical, imaging, notification, production-access, or other out-of-scope behavior is introduced.
- [ ] `docs/member/wp-04-identity-evidence.md` accurately records implemented scope, observed verification, unresolved approvals, and residual risks without unsupported compliance claims.
- [ ] Focused WP-04 tests and the complete repository verification suite pass with no real identifiers, secrets, document bytes, or credentials committed or printed.

## Verification

- Method: Run the canonical task validator before execution; inspect repository status and accepted-baseline ancestry; run focused Member identity/security/migration tests; then run `composer validate --strict`, `composer audit`, `vendor/bin/pint --test`, `php artisan test`, `npm run build`, and `bash deployment/validate.sh`; inspect migration behavior, captured logs/audit records, generated artifacts, and the final diff for scope and sensitive-data violations.
- Expected result: The task validates; the repository starts from the accepted baseline without overlapping work; every assigned WP-04 requirement has direct implementation and negative-test evidence; clean and rollback migration paths are observed without data loss; all focused and complete checks pass; deployment regression validation remains green; the evidence artifact matches observed results; and no approval-gated or out-of-scope behavior is present.

## Output

- Allowed outcomes: `succeeded`, `failed`, `blocked`, `awaiting-approval`, or `exhausted`.
- Report the selected runtime/model when verifiable, available capabilities, initial and final commit/status evidence, affected schemas/interfaces/files, requirement-to-evidence mapping, verification commands and observed results, migration and compatibility evidence, residual privacy/security risks, unresolved approvals, and manual follow-up.
- Treat an unvalidated task, unsafe migration, missing required approval, unavailable baseline, overlapping user work, failed test/build/audit, unexecuted required verification, model output alone, or iteration exhaustion as unsuccessful.
- Do not edit this task with runtime values, progress, command output, or results.
