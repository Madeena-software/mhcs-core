---
name: mhcs-core-mvp-01-member-access-and-profile
description: "Implement the first controlled-beta Member vertical slice: email-or-NIK login, restricted mandatory password replacement, Member-owned profile completion, dashboard, logout, and local synthetic beta accounts on the accepted MVP-00 and WP-04 foundations."
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-01 Member Access and Profile

## Objective

Implement `MVP-01 — Member Access and Profile` in `$TARGET`.

Use commit:

`35148a39694ef137adb263dab609d456b42d8c76`

as the accepted planning baseline.

The user-visible vertical slice is:

```text
existing adult Member account
→ login with email or NIK
→ mandatory password replacement when required
→ complete permitted profile fields
→ Member dashboard
→ logout
```

This is the first functional controlled-beta slice.

The observable outcome is a Member-facing Blade application that:

- authenticates an existing adult Member by email or NIK using one generic
  identifier field;
- preserves the existing generic, enumeration-resistant, rate-limited
  credential-verification behavior;
- admits an active, login-enabled Member with
  `must_change_password = true` only into a restricted first-login session;
- allows that restricted session to access only mandatory password replacement
  and logout;
- changes the password through the existing Member password-replacement
  boundary without storing or logging plaintext credentials;
- resolves the authenticated Member server-side from `users.id`;
- allows only approved profile fields to be updated;
- prevents updates to NIK, medical-record number, birth date, identity state,
  account state, registration source, and other protected fields;
- shows a minimal Member dashboard with safe identity and account summaries;
- logs out through a CSRF-protected POST action;
- provides controlled local/testing-only synthetic Member accounts for beta
  development; and
- updates the MVP documentation with bounded implementation evidence and any
  remaining gaps.

A `succeeded` outcome means this vertical slice works through focused feature
tests and the declared targeted regression checks.

It does not mean:

- public or online registration exists;
- B2B bulk import exists;
- child or guardian access exists;
- identity verification UI exists;
- the shared administrator interface exists;
- Operator Portal exists;
- Image Gateway workflow exists;
- service catalogue, booking, queues, teleradiology, or results exist;
- credential delivery is production-approved;
- the forward-only UUID migration is approved for production;
- full regression, MySQL, Docker, deployment, or production validation passed;
  or
- MHCS is production-ready.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
- Ordered model preferences: None.
- Require preferred model: `false`

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Baseline and evidence boundary

Treat commit:

`35148a39694ef137adb263dab609d456b42d8c76`

as the accepted MVP-00 baseline.

The current execution commit may be that commit or a descendant that publishes
this task.

Before changing files:

1. Resolve `$TARGET` to a canonical absolute path.
2. Confirm the expected `mhcs-core` repository.
3. Record the current branch and commit.
4. Confirm that repository history contains the accepted baseline.
5. Record staged, modified, untracked, and relevant ignored files.
6. Preserve all pre-existing work.
7. Stop as `awaiting-approval` when existing work overlaps files required by
   this task.
8. Do not reset, clean, discard, stash, stage, commit, push, rewrite history,
   open a pull request, or trigger deployment.

Repository evidence at the accepted baseline includes:

- a UUID `User` model with `account_status`, `login_enabled`, and
  `must_change_password`;
- `User::canAuthenticate()`, which permits only active, login-enabled accounts
  whose mandatory-password-change flag is clear;
- `AccountStateUserProvider`, which preserves that strict rule for ordinary
  Laravel credential validation;
- `CredentialVerifier`, which owns generic email-or-NIK credential lookup,
  failure responses, throttling, and credential-verification audit;
- `MemberCredentialIdentifierResolver`, which resolves protected NIK lookup
  without making NIK a primary key;
- `MandatoryPasswordReplacementService`, which verifies the temporary
  credential, replaces it, clears `must_change_password`, records audit, and
  preserves idempotency;
- a one-to-one User/Member foundation;
- `members.phone`, but no current-address or emergency-contact fields;
- protected NIK display and lookup values;
- immutable medical-record number, registration source, and NIK lookup digest;
- no Member web routes beyond the current root route; and
- no implemented Member login, profile, dashboard, or logout UI.

Do not reinterpret those facts as permission to weaken the accepted WP-02 or
WP-04 boundaries.

## Context and evidence

Read completely before planning or writing:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/skills/develop-feature/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/docs/mvp/README.md`;
- `$TARGET/docs/mvp/beta-scope.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/work-package-status.md`;
- `$TARGET/.agents/tasks/mhcs-core-wp-04-member-identity-accounts-guardians-recovery-v1.md`;
- `$TARGET/docs/member/wp-04-identity-evidence.md`;
- `$TARGET/app/Models/User.php`;
- `$TARGET/app/Shared/Auth/AccountStateUserProvider.php`;
- `$TARGET/app/Shared/Security/CredentialVerifier.php`;
- `$TARGET/app/Shared/Security/CredentialVerificationResult.php`;
- `$TARGET/app/Shared/Security/TemporaryCredentialIssuer.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberCredentialIdentifierResolver.php`;
- `$TARGET/app/Modules/Member/Application/Services/MandatoryPasswordReplacementService.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberAuthorization.php`;
- `$TARGET/app/Modules/Member/Domain/Models/Member.php`;
- `$TARGET/database/migrations/2026_08_04_000008_create_member_identity_tables.php`;
- `$TARGET/routes/web.php`;
- current authentication configuration, middleware registration, session
  configuration, controllers, views, factories, seeders, and tests relevant to
  the flow; and
- the current design and Member-facing language evidence that applies to the
  new screens.

Use repository evidence and observed command output.

Do not treat task text, generated summaries, or editor diagnostics alone as
verification.

## Scope and constraints

- Implement only the MVP-01 Member vertical slice.
- Application changes may include only the minimum Member-owned or shared
  authentication files required for:
  - login;
  - restricted mandatory password replacement;
  - Member profile storage and update;
  - dashboard;
  - logout;
  - focused tests;
  - local/testing-only synthetic seed accounts; and
  - MVP documentation updates.
- Preserve the existing Work Packages, MVP-00 documents, decision history, gap
  IDs, and accepted WP-01/WP-02/WP-04 foundations.
- Do not modify published task files, including this task.
- Do not modify `.agents/context/**`.
- Do not modify `docs/implementation/**`.
- Do not change requirement assignments, classifications, counts, or source
  digests.
- Do not commit, stage, push, deploy, access production, or perform a
  production-affecting operation.
- Do not add or replace Composer or npm dependencies.
- Do not install an authentication starter kit.
- Do not add public self-registration, online registration, password reset,
  email verification, social login, MFA, remember-me, or production credential
  delivery.
- Do not add Member administration, Operator administration, Image Gateway
  administration, or shared administrator-interface functionality.
- Do not add Operator Portal, Image Gateway workflow, Doctor Portal, booking,
  payment, points, queue, imaging, teleradiology, or results behavior.
- Do not reopen WP-04 registration, guardian, age-transition, asset, recovery,
  or UUID work except for the narrow integration required by this Member login
  flow.
- Do not weaken `User::canAuthenticate()` or the ordinary Laravel
  `Auth::attempt()` rule.
- Do not make a temporary-password account an unrestricted authenticated
  account.
- Do not store plaintext passwords or temporary credentials in the database,
  session, cache, files, audit events, logs, exceptions, browser storage, or
  source control.
- Do not expose raw NIK, encrypted NIK, NIK lookup digest, KK, identity-object
  keys, or protected verification assets.
- Do not use a route-supplied, form-supplied, or session-supplied Member ID as
  the authority for profile or dashboard access.
- Do not run full-suite, Docker, MySQL, deployment, Composer-audit, or broad
  frontend validation during this task.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `5`
- Approval gates: stop as `awaiting-approval` whenever any declared stop condition is met.

## Execution procedure

1. Resolve and verify `$TARGET`.
2. Validate baseline ancestry and inspect repository state.
3. Read all required MVP, Member, authentication, design, and test evidence.
4. Identify the smallest safe extension for interactive Member login without
   weakening strict credential verification.
5. Implement the database, application, middleware, route, view, and
   local-seeder changes required by this task.
6. Add focused feature and targeted regression tests.
7. Run only the declared focused verification.
8. Update the bounded MVP documentation and evidence.
9. Re-read the task against the final diff.
10. Report the outcome without continuing into MVP-02.

## Required routes

Implement these routes:

```text
GET   /login
POST  /login
GET   /password/change-required
POST  /password/change-required
GET   /member/profile
PATCH /member/profile
GET   /member/dashboard
POST  /logout
```

Use stable route names.

Preferred names:

```text
login
login.store
password.change-required
password.change-required.update
member.profile
member.profile.update
member.dashboard
logout
```

Equivalent names are acceptable only when they are internally consistent and
tested.

Requirements:

- `/login` is guest-only.
- `/password/change-required` requires the restricted or ordinary authenticated
  User described below.
- `/member/profile` and `/member/dashboard` require an authenticated,
  unrestricted Member session.
- `/logout` requires authentication and accepts POST only.
- State-changing routes require CSRF protection.
- Do not accept a Member ID in any MVP-01 route.
- Do not use a user-controlled post-login redirect URL.
- A request for an unsupported registration or password-reset route must not
  create an accidental public onboarding path.

## Interactive login admission

### Identifier and credential behavior

The login form must use exactly one user-facing identifier input:

```text
identifier
```

The identifier may be:

- registered email; or
- NIK.

Use one password input:

```text
password
```

Requirements:

- canonicalize email consistently with the existing verifier;
- resolve NIK through the existing protected lookup foundation;
- keep KK unavailable as a login identifier;
- reuse the existing credential throttling configuration;
- reuse generic failure behavior;
- do not reveal whether an identifier exists;
- do not reveal whether an account is suspended, pending, login-disabled,
  missing a Member record, or requires password replacement;
- do not include the identifier or NIK in audit metadata or logs;
- do not duplicate plaintext NIK into `users`;
- do not use NIK as a local primary key; and
- use the existing credential-verification audit boundary.

The user-facing invalid-login response must be the same for:

- unknown email;
- unknown NIK;
- wrong password;
- pending account;
- suspended account;
- login-disabled account;
- account without an eligible linked Member; and
- other rejected Member-login states.

A rate-limited response may use the existing generic verifier result but must
not reveal account existence.

### Preserve strict authentication semantics

The existing strict paths must remain strict:

- `User::canAuthenticate()` must continue returning `false` when
  `must_change_password = true`;
- ordinary `Auth::attempt()` must continue rejecting mandatory-change accounts;
- existing callers of `CredentialVerifier::verify()` must continue receiving
  the existing strict behavior unless repository evidence proves a compatible
  extension is safer; and
- suspended, pending, and login-disabled accounts must remain rejected by all
  login paths.

Do not solve first-login admission by changing `canAuthenticate()` to allow
temporary-password accounts.

### Dedicated restricted-login admission

Add the smallest explicit application/security contract needed for interactive
login admission.

A valid interactive login result must distinguish:

```text
normal_member_session
password_change_required
failure
```

Equivalent strongly typed states are acceptable.

The implementation should preferably:

- reuse or internally refactor the existing credential lookup, password check,
  throttling, failure audit, and success audit;
- preserve the public behavior of the strict verifier;
- avoid duplicating rate-limit keys or creating a weaker parallel
  authentication implementation; and
- keep the extension usable only by the interactive login controller or a
  similarly bounded application service.

State rules:

#### Normal Member session

A valid password may create a normal Member session only when:

- the User exists;
- the User is `active`;
- `login_enabled = true`;
- `must_change_password = false`; and
- exactly one eligible Member record is linked through `members.user_id`.

#### Password-change-required session

A valid password may create a restricted session only when:

- the User exists;
- the User is `active`;
- `login_enabled = true`;
- `must_change_password = true`; and
- exactly one eligible adult Member record is linked through `members.user_id`.

The restricted result is not an unrestricted authentication success.

#### Failure

Reject when:

- the User does not exist;
- the password is wrong;
- the account is pending;
- the account is suspended;
- login is disabled;
- no linked Member exists;
- the Member relationship is ambiguous or inconsistent;
- the Member is outside the initial adult beta; or
- required security configuration is invalid.

### Session establishment

After successful normal or restricted admission:

- establish the Laravel authenticated session explicitly;
- regenerate the session ID immediately;
- do not preserve a user-controlled intended URL;
- do not store the supplied password in the session;
- do not store a reusable credential-verification result containing sensitive
  data;
- redirect a restricted session to `/password/change-required`;
- redirect a normal session to `/member/profile` when the required profile is
  incomplete;
- otherwise redirect a normal session to `/member/dashboard`.

A failed login must not authenticate a User or retain a stale authenticated
session.

## Restricted mandatory-password-change session

Implement middleware or an equivalent central web guard that enforces:

- an authenticated User with `must_change_password = true` may access only:
  - `GET /password/change-required`;
  - `POST /password/change-required`; and
  - `POST /logout`;
- every other route request by that authenticated User redirects to
  `/password/change-required` or fails closed;
- a restricted User cannot access Member profile or dashboard;
- a restricted User cannot use an existing intended URL to bypass the guard;
- the guard is evaluated server-side on every request;
- clearing a client cookie or changing a request parameter cannot clear the
  mandatory-change state;
- an authenticated User whose flag is already clear must not remain on the
  mandatory-change form; and
- suspension or login disablement detected after session establishment causes
  logout or fail-closed denial rather than continued access.

The central guard may be added to the web middleware stack only for this exact
purpose.

Do not redesign the shared authentication architecture.

## Mandatory password replacement

### Form

The password-replacement page must use Member-facing Bahasa Indonesia copy and
must include:

```text
current_password
password
password_confirmation
```

The form must not display or preload the temporary credential.

### Validation

Require:

- current password present;
- replacement password confirmed;
- replacement password different from the current password;
- minimum 12 characters;
- at least one letter;
- at least one number; and
- no leading or trailing whitespace-only value.

Do not add a network-dependent compromised-password check.

Use generic error copy for an invalid current credential.

Do not disclose hashes, temporary-credential state, or account internals.

### Application boundary

Use the existing `MandatoryPasswordReplacementService` rather than directly
clearing `must_change_password` in a controller.

Requirements:

- resolve `userId` from the authenticated session;
- generate the operation ID server-side;
- do not accept `userId` or operation ID from the request;
- pass the submitted current temporary password only in memory;
- preserve transaction and idempotency behavior;
- clear `must_change_password` only after successful password replacement;
- retain the current credential and flag after any validation or service
  failure;
- preserve account status and login-enabled state;
- record sanitized audit evidence;
- never include password values in audit or logs; and
- map domain failure to a generic form error without leaking account state.

After successful replacement:

- refresh the authenticated User state;
- invalidate the old session identifier by regenerating the session;
- do not log the User out merely because replacement succeeded;
- redirect to `/member/profile` when the required profile is incomplete;
- otherwise redirect to `/member/dashboard`.

The old temporary password must no longer authenticate.

## Authenticated Member resolution

Create one explicit Member-context resolver or equivalent bounded query.

It must:

- begin with the authenticated `users.id`;
- resolve the linked Member through `members.user_id`;
- return only the Member required by the current request;
- never trust a Member ID from route, query, form, header, or session input;
- reject missing or ambiguous ownership;
- reject child/dependent access for this adult-only beta;
- preserve Member/User one-to-one ownership;
- avoid broad Member serialization; and
- avoid exposing protected NIK fields.

Use the same resolver for:

- profile edit;
- profile update;
- dashboard; and
- profile-completion calculation.

If an authenticated User no longer has an eligible linked Member:

- fail closed;
- invalidate or terminate the Member session where appropriate; and
- show a generic unavailable response without identity details.

## Profile data model

Add a new forward migration for the minimum Member-owned profile fields.

Do not edit the published WP-04 migration.

Add nullable fields equivalent to:

```text
members.current_address
members.emergency_contact_name
members.emergency_contact_relationship
members.emergency_contact_phone
```

Preferred database types:

```text
current_address                  text nullable
emergency_contact_name           string nullable
emergency_contact_relationship   string nullable
emergency_contact_phone          string nullable
```

Use the repository's accepted migration conventions.

Requirements:

- preserve existing Member rows;
- preserve UUID and foreign-key strategy;
- do not add duplicate name, birth date, NIK, MRN, registration source, account
  state, or identity state columns;
- do not store profile data in `users` except email;
- do not add JSON when explicit fields are sufficient;
- do not add address normalization, geocoding, or external reference data;
- use a safe reversible `down()` for only the new fields; and
- do not alter the forward-only UUID migration boundary.

Do not add `profile_completed_at` unless repository evidence proves that a
persisted completion snapshot is required. Prefer deriving completion from
current data to avoid stale state.

## Profile edit and update

### Editable fields

The Member may edit only:

```text
email
phone
current_address
emergency_contact_name
emergency_contact_relationship
emergency_contact_phone
```

Ownership:

- `users.email` owns email;
- `members.phone` owns Member phone;
- the new Member fields own current address and emergency contact.

Email and Member phone remain optional, consistent with the Member
specification.

### Required fields for beta profile completion

A profile is complete for MVP-01 when all of these are non-empty:

```text
current_address
emergency_contact_name
emergency_contact_relationship
emergency_contact_phone
```

Email and Member phone are optional contact channels and must not prevent a
Member without those channels from reaching complete status.

Calculate the completion score from the four required groups:

```text
0%, 25%, 50%, 75%, or 100%
```

Do not persist the percentage.

### Validation

Validate:

- email:
  - nullable;
  - trimmed and canonicalized to lowercase;
  - syntactically valid;
  - unique among non-null User emails, excluding the authenticated User;
- phone:
  - nullable;
  - trimmed;
  - string;
  - maximum 32 characters;
- current address:
  - required for completion, but the edit form may save an incomplete draft;
  - trimmed;
  - maximum 1,000 characters;
- emergency contact name:
  - required for completion, but the edit form may save an incomplete draft;
  - trimmed;
  - maximum 255 characters;
- emergency contact relationship:
  - required for completion, but the edit form may save an incomplete draft;
  - trimmed;
  - maximum 100 characters;
- emergency contact phone:
  - required for completion, but the edit form may save an incomplete draft;
  - trimmed;
  - maximum 32 characters.

Do not invent an external phone-normalization or address-validation policy.

The update may save an incomplete profile and must then keep the completion
state below 100%.

### Atomic update

Update User email and Member-owned profile fields in one database transaction.

Requirements:

- lock or otherwise safely update the authenticated User and Member;
- use an explicit field allowlist;
- do not pass the entire request payload to `fill()`, `update()`, or
  `forceFill()`;
- do not rely on `Member::$guarded = []` as authorization;
- reject or ignore unexpected fields before persistence;
- preserve immutable Member model protections;
- do not update:
  - User ID;
  - Member ID;
  - NIK;
  - NIK lookup digest;
  - encrypted NIK;
  - KK/family identity;
  - MRN;
  - name;
  - birth date;
  - administrative gender;
  - identity document type;
  - identity status;
  - account status;
  - login-enabled state;
  - mandatory-password-change state;
  - registration source;
  - guardian relations;
  - verification assets; or
  - external identifiers;
- record one sanitized profile-update audit event;
- audit only safe metadata such as changed field names and completion state;
- do not audit field values, email, phone, address, or emergency-contact data;
- render validation errors without echoing secrets or protected identifiers;
  and
- escape all displayed values through Blade.

After update:

- redirect back to profile with a success state when incomplete; or
- redirect to dashboard when the profile reaches 100%.

## Member dashboard

Implement a minimal Member dashboard.

Display only:

- Member name;
- medical-record number;
- profile-completion percentage or complete/incomplete status;
- identity status;
- account status; and
- clearly disabled or informational placeholders for future radiology services.

Do not display:

- NIK;
- KK;
- encrypted identifiers;
- identifier lookup digests;
- verification-object keys;
- identity images;
- raw audit metadata;
- internal role or permission arrays;
- temporary credentials;
- session identifiers; or
- another Member's data.

The future-service placeholders must not:

- submit forms;
- create bookings;
- imply a service is available;
- expose fake results; or
- use routes that do not exist.

Use explicit copy such as “Belum tersedia pada tahap beta ini.”

## Member-facing UI

Use Blade and existing Laravel/Vite/Tailwind primitives.

Do not add a frontend framework or dependency.

Create a small reusable Member layout where appropriate.

Requirements:

- Member-facing copy is in Bahasa Indonesia;
- field labels and errors are understandable;
- forms have explicit labels;
- keyboard focus remains visible;
- validation errors are associated with fields;
- success and error messages are visible;
- pages are usable on mobile and desktop;
- status text is not communicated by color alone;
- destructive or state-changing actions use forms and CSRF;
- no inline script stores credentials;
- no remote fonts, trackers, or third-party scripts are added;
- no unsupported logo, medical claim, or production claim is invented; and
- visual work remains bounded to functional beta screens rather than a broad
  design-system implementation.

Required pages:

```text
login
mandatory password replacement
Member profile
Member dashboard
```

The logout action must be visible from authenticated pages.

## Logout

Implement logout as a CSRF-protected POST action.

On logout:

- call Laravel logout;
- invalidate the session;
- regenerate the CSRF token;
- clear restricted-session state by relying on server-side User state and
  session invalidation;
- redirect to `/login`; and
- do not preserve a reusable post-login intended destination.

GET `/logout` must not perform logout.

## Local/testing-only synthetic Member accounts

Add one dedicated seeder for local and test environments.

Preferred name:

```text
MvpMemberSeeder
```

Requirements:

- refuse to run outside explicitly allowed local/testing environments;
- do not call it automatically from `DatabaseSeeder`;
- create two or three clearly synthetic adult Members;
- use UUID User and Member identifiers;
- use clearly synthetic names, emails, NIK values, and identity assets;
- never use real patient or employee data;
- preserve protected NIK encryption and keyed lookup;
- allocate valid unique MRNs through the existing generator;
- create coherent one-to-one User/Member records;
- create active, login-enabled Users with
  `must_change_password = true`;
- use a cryptographically secure unique temporary password per newly created
  account;
- hash the temporary password before persistence;
- emit each newly generated plaintext credential only once to the interactive
  console output;
- do not write plaintext credentials to files, logs, audit, cache, or source;
- do not reset an existing seeded account's password on a repeated run;
- be idempotent by stable synthetic identity or an equivalent safe key;
- preserve account, identity, verification-asset, and Member invariants;
- use existing application/domain services when practical;
- when direct seeding is necessary, confine it to this local/testing-only
  seeder and reuse existing protected-identifier, MRN, private-object, UUID,
  transaction, and hashing primitives;
- use synthetic private asset bytes only when verification assets are required;
  and
- print a clear message that credentials are development-only and unavailable
  again after the first successful seed.

Do not place fixed plaintext passwords in tests, documentation, environment
templates, source code, or the final report.

Focused tests may use known test passwords inside isolated test code, but
production or reusable development credentials must not be committed.

## Authorization and security invariants

The implementation must prove:

- only the authenticated User's linked Member is resolved;
- no IDOR path exists because no Member ID is accepted;
- mandatory-change sessions cannot access profile or dashboard;
- normal Member sessions cannot edit another Member;
- suspended, pending, and login-disabled accounts cannot log in;
- a User without an eligible Member cannot enter the Member Portal;
- NIK login remains generic and protected;
- raw NIK is absent from HTML, redirects, logs, exceptions, and audit metadata;
- profile updates cannot mutate protected or immutable fields;
- email uniqueness is enforced without preventing multiple null emails;
- session ID is regenerated after login and password replacement;
- logout invalidates the session and regenerates the CSRF token;
- state-changing forms require CSRF;
- Blade output is escaped;
- rate limiting remains active;
- password values never appear in audit or logs; and
- no public registration path is introduced.

## Focused tests

Create focused tests under the Member feature-test boundary.

Preferred file:

```text
tests/Feature/Member/Mvp01MemberAccessTest.php
```

Equivalent bounded organization is acceptable.

At minimum, test:

### Login

- GET `/login` renders for guests.
- An active linked adult Member logs in with canonical email.
- The same Member logs in with NIK.
- Unknown identifier and wrong password return the same generic error.
- A suspended User is rejected.
- A pending User is rejected.
- A login-disabled User is rejected.
- A User without a linked Member is rejected generically.
- Login regenerates the session identifier.
- Login does not honor an untrusted external intended URL.
- Rate-limited login does not reveal account existence.

### Restricted first login

- A valid active/login-enabled mandatory-change account enters a restricted
  session.
- The restricted User is redirected to password replacement.
- The restricted User cannot access `/member/profile`.
- The restricted User cannot access `/member/dashboard`.
- The restricted User cannot bypass the guard with query, route, or intended
  URL changes.
- The restricted User can POST logout.
- Ordinary strict `Auth::attempt()` still rejects the temporary account.
- Existing strict `CredentialVerifier::verify()` behavior remains compatible.

### Password replacement

- The form renders only for the appropriate authenticated state.
- An invalid current temporary password fails generically.
- A weak or unconfirmed replacement password fails validation.
- Failure leaves the old credential and mandatory-change flag intact.
- Success changes the hash and clears `must_change_password`.
- Success preserves account status and login-enabled state.
- The old temporary password no longer works.
- The replacement password works through the normal Member-login path.
- Success regenerates the session ID.
- Success redirects to profile when required profile fields are incomplete.
- Passwords are absent from audit metadata.

### Profile

- The authenticated Member sees only their own profile.
- No Member ID is present in profile routes.
- Email, phone, address, and emergency-contact fields update atomically.
- Nullable email and phone remain valid.
- Duplicate non-null email is rejected.
- An incomplete draft may be saved.
- Completion percentage is derived correctly.
- A complete profile redirects to the dashboard.
- Unexpected request fields cannot mutate NIK, MRN, name, birth date,
  registration source, identity status, or account status.
- Profile audit metadata contains safe changed-field names only.
- Another Member remains unchanged.

### Dashboard

- The dashboard shows name, MRN, profile completion, identity status, and
  account status.
- The dashboard does not render NIK, encrypted NIK, lookup digests, asset keys,
  passwords, or another Member's data.
- Future-service placeholders are non-functional and clearly unavailable.

### Logout

- POST logout ends the session and redirects to login.
- GET logout does not perform logout.
- A logged-out session cannot access Member routes.

### Seeder

- the seeder refuses to run outside allowed environments;
- synthetic accounts preserve User/Member ownership and protected NIK storage;
- generated accounts require mandatory password replacement;
- repeated execution does not reset existing credentials; and
- plaintext generated credentials are not persisted.

Use factories or focused helpers for tests.

Do not add real NIK, KK, KTP, KIA, profile photographs, or patient data to
fixtures.

## Targeted regression boundary

When shared authentication files are changed, run only the existing targeted
tests needed to prove that the accepted behavior remains intact.

At minimum, identify and run focused existing tests covering:

- generic credential verification;
- rate limiting;
- suspended, pending, and login-disabled denial;
- strict Laravel authentication denial for mandatory-change accounts;
- temporary credential hashing and replacement;
- Member email-or-NIK resolution;
- Member mandatory-password-replacement service; and
- protected identifier handling.

Use test filters or individual files.

Do not run the complete WP-02 or WP-04 suites unless the focused tests cannot
select the changed behavior reliably.

## MVP documentation updates

Update only the MVP documentation required to record this implementation.

At minimum:

### `docs/mvp/roadmap.md`

Record MVP-01 implementation status and evidence without describing MVP-02 as
started.

Do not renumber later MVP tasks.

### `docs/mvp/beta-gap-register.md`

Preserve stable IDs.

Do not close:

- public registration;
- online registration;
- B2B import;
- adult-only beta;
- guardians;
- identity-verification UI;
- Operator;
- Image Gateway;
- result visibility;
- production credential delivery;
- privacy/retention;
- UUID approval; or
- deployment gaps.

Update only entries whose impact or temporary control is materially clarified
by MVP-01.

Add a new stable gap only when the implementation discovers a real limitation
not already represented.

### MVP-01 evidence

Create a bounded evidence document such as:

```text
docs/mvp/evidence/mvp-01-member-access-and-profile.md
```

Record:

- baseline and execution commit;
- implemented routes;
- authentication-state model;
- restricted-session behavior;
- profile fields and completion rule;
- synthetic-seeder usage without plaintext credentials;
- focused tests and observed results;
- targeted regressions and observed results;
- files changed;
- known limitations;
- full verification not run; and
- no production-readiness claim.

Do not record generated plaintext credentials.

## Required checks

During implementation, run only focused checks.

Required:

```bash
git diff --check
```

Run the new focused MVP-01 feature tests.

Run targeted existing security and Member tests only for changed shared
authentication and password-replacement behavior.

Run Laravel Pint only against changed PHP files when the installed Pint command
supports bounded paths.

Inspect the route list for the new MVP-01 routes.

Perform a static search of changed files for:

```text
password
current_password
temporaryCredential
encrypted_nik
nik_lookup_digest
```

Review matches to ensure no credential or protected value is logged, audited,
rendered, or persisted incorrectly.

Do not run:

- the complete PHPUnit suite;
- the complete WP-02 suite;
- the complete WP-04 suite;
- MySQL conformance;
- `deployment/verify-mysql.sh`;
- `deployment/validate.sh`;
- Docker builds;
- database resets outside the test environment;
- the local synthetic seeder against non-test data;
- `npm run build`;
- Composer audit;
- dependency installation;
- external service checks; or
- deployment verification.

The later integration/release gate will run full validation once after the
bounded MVP slice is accepted.

## Acceptance criteria

- [ ] The repository contains the accepted MVP-00 baseline.
- [ ] Existing overlapping work was not overwritten.
- [ ] The eight required routes exist with the declared HTTP methods.
- [ ] Login uses one generic email-or-NIK identifier input.
- [ ] Generic failure and existing credential throttling are preserved.
- [ ] Strict `User::canAuthenticate()` behavior remains unchanged.
- [ ] Strict ordinary `Auth::attempt()` continues rejecting mandatory-change accounts.
- [ ] Active, login-enabled mandatory-change Members can enter only a restricted session.
- [ ] Restricted sessions can access only password replacement and logout.
- [ ] Suspended, pending, login-disabled, unlinked, and ineligible accounts are rejected.
- [ ] Session IDs regenerate after login and password replacement.
- [ ] No plaintext password is stored in session, cache, files, logs, audit, or source.
- [ ] Mandatory password replacement uses the existing application service.
- [ ] The password flag clears only after successful replacement.
- [ ] The old temporary password stops working after replacement.
- [ ] Profile and dashboard resolve Member ownership server-side from the authenticated User.
- [ ] No Member ID is accepted by MVP-01 routes or forms.
- [ ] A forward migration adds only the bounded Member profile fields.
- [ ] Existing Member rows and WP-04 migrations are preserved.
- [ ] Only approved profile fields are editable.
- [ ] Email and Member phone remain optional.
- [ ] Completion is derived from address and emergency-contact fields.
- [ ] User email and Member profile updates are atomic.
- [ ] Protected and immutable identity/account fields cannot be changed through the profile request.
- [ ] Profile audit contains safe metadata and no profile values.
- [ ] The dashboard shows only the approved safe summary.
- [ ] NIK, KK, encrypted identifiers, lookup digests, asset keys, and credentials are absent from views.
- [ ] Logout is POST-only, CSRF-protected, and invalidates the session.
- [ ] Member-facing copy is in Bahasa Indonesia.
- [ ] The local/testing-only seeder creates synthetic adult Member accounts safely.
- [ ] The seeder is not invoked automatically.
- [ ] The seeder does not persist or commit plaintext credentials.
- [ ] Focused MVP-01 tests pass.
- [ ] Targeted authentication and WP-04 regression tests pass.
- [ ] The new route list is correct.
- [ ] `git diff --check` passes.
- [ ] MVP roadmap, gap register, and evidence are updated accurately.
- [ ] No public registration, import, admin, Operator, Image Gateway, booking, payment, imaging, or result behavior was added.
- [ ] No dependency, production configuration, deployment, commit, or push was performed.
- [ ] The final report distinguishes focused evidence from unrun full validation.

## Verification

- Method: Run `git diff --check`, the focused MVP-01 feature tests, the targeted authentication and password-replacement regressions, bounded Pint on changed PHP files, and inspect the MVP-01 route list and allowed scope.
- Expected result: All focused checks pass, the declared Member flow works without weakening strict authentication or ownership boundaries, and no unrelated MVP or production behavior is introduced.

## Stop conditions

Stop as `awaiting-approval` when:

- the accepted baseline is absent from repository history;
- current work overlaps required files;
- the task requires weakening `User::canAuthenticate()` or ordinary
  `Auth::attempt()` semantics;
- restricted login cannot reuse the existing generic lookup, throttling, and
  audit behavior without creating a materially separate weaker verifier;
- a safe mandatory-change guard requires replacing the shared authentication
  provider rather than applying the bounded extension described here;
- Member ownership cannot be resolved unambiguously from the authenticated
  User;
- the profile schema conflicts with newer authoritative Member profile fields;
- a migration would discard or rewrite existing Member data;
- local synthetic accounts cannot preserve protected NIK and User/Member
  invariants without modifying production policy;
- an existing approved password policy conflicts with the bounded validation in
  this task;
- implementation requires a new Composer or npm dependency;
- implementation requires public registration, password reset, email
  verification, B2B import, or production credential delivery;
- implementation requires changing UUID strategy, module ownership,
  requirement assignments, privacy/retention policy, or production deployment
  policy;
- an unresolved legal or identity-policy decision is required;
- any required change extends into Operator, Image Gateway, Doctor, booking,
  payment, imaging, or results behavior;
- focused tests expose a material WP-02 or WP-04 regression that cannot be
  corrected within this Member-login slice;
- full MySQL or Docker validation becomes necessary to establish basic
  correctness; or
- a destructive or production-affecting operation would be required.

When stopped:

- do not partially expose an unsafe login route;
- do not bypass the existing credential verifier;
- do not clear mandatory-password state manually;
- do not seed inconsistent Member records;
- do not continue into another MVP task.

Report:

- the exact conflict;
- affected files or contracts;
- work completed before the stop;
- safest options;
- owner decision required; and
- repository state.

## Output

- `succeeded`: all acceptance criteria and focused checks pass.
- `failed`: execution occurred but a required criterion or focused check failed.
- `blocked`: required tooling or evidence is unavailable.
- `awaiting-approval`: an approval gate or stop condition is reached.
- `exhausted`: the iteration limit is reached before completion.

## Final report

Report:

- accepted baseline and execution commit;
- implementation outcome;
- routes added;
- login-state model;
- restricted-session enforcement;
- profile schema and completion rule;
- dashboard fields;
- seeder class and safe invocation command;
- files changed;
- focused tests run and observed results;
- targeted regressions run and observed results;
- route-list and formatting checks;
- MVP documentation and gap changes;
- known limitations;
- unrun full validation;
- approval boundaries that remain open; and
- confirmation that no dependency, production configuration, deployment,
  commit, push, or unrelated MVP functionality was added.

Do not include generated plaintext credentials in the final report.

Do not commit or push.

Stop after MVP-01.
