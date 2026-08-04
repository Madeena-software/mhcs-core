---
name: mhcs-core-mvp-02-shared-admin-shell-member-administration
description: Implement the controlled-beta shared Filament administrator shell and the first Member-owned administration slice with persistent trusted authorization claims, safe Member account views, suspend/restore actions, bounded audit visibility, focused tests, and local/testing-only administrator provisioning.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-02 — Shared Admin Shell and Member Administration Foundation

## Objective

Implement:

```text
MVP-02 — Shared Admin Shell and Member Administration Foundation
```

in `$TARGET`.

Use commit:

`4efe6994a4b4823fba2aca21d1edba8f0bd7808c`

as the accepted implementation baseline.

The controlled-beta administration topology is:

```text
shared administrator interface
└── module-owned administration areas
    └── Member administration for MVP-02
```

The observable outcome is:

```text
authorized administrator
→ generic, rate-limited /admin login
→ shared Filament admin shell
→ Member account list and safe detail
→ approved suspend or restore action with reason
→ bounded Member audit visibility
→ logout
```

MVP-02 must deliver only:

- the shared administrator-facing Filament shell at `/admin`;
- persistent trusted role and permission claims required by the shared
  authenticated application context;
- Member-owned administration required to inspect controlled Member accounts;
- approved Member account-state transitions through the existing
  `AccountStateService`;
- foundational, read-only Member audit visibility;
- local/testing-only synthetic administrator provisioning; and
- focused implementation evidence.

A `succeeded` outcome means the shared shell and this Member-owned
administration slice work through focused tests and the declared targeted
regressions.

It does not mean:

- all Member administration from the end-state specification is implemented;
- Member registration, identity verification, assisted recovery, guardian
  management, age transition, B2B import, service offerings, schedules,
  bookings, payments, points, promotions, or settings are implemented in the
  panel;
- Operator or Image Gateway administration is implemented;
- Doctor Portal or internal doctor workflow exists;
- administrator account provisioning or credential delivery is approved for
  production;
- full RBAC lifecycle management exists;
- full regression, MySQL, Docker, frontend build, deployment, or production
  validation passed; or
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

`4efe6994a4b4823fba2aca21d1edba8f0bd7808c`

as the accepted MVP-01 baseline.

Before changing files:

1. Resolve `$TARGET` to a canonical absolute path.
2. Confirm the expected `Madeena-software/mhcs-core` repository.
3. Record the current branch and commit.
4. Confirm that repository history contains the accepted baseline.
5. Record staged, modified, untracked, and relevant ignored files.
6. Preserve all pre-existing work.
7. Stop as `awaiting-approval` when existing work overlaps files required by
   this task.
8. Do not reset, clean, discard, stash, stage, commit, push, rewrite history,
   open a pull request, or trigger deployment.

Repository evidence at the accepted baseline includes:

- PHP `^8.4`;
- Laravel `^13.8`;
- Filament `^5.0` already declared as a Composer dependency;
- no registered Filament panel provider in `bootstrap/providers.php`;
- a shared web authentication guard using `User`;
- strict account-state authentication through `User::canAuthenticate()` and
  `AccountStateUserProvider`;
- generic, audited, rate-limited credential verification through
  `CredentialVerifier`;
- `LaravelAuthenticatedContextProvider`, which currently derives roles and
  permissions from trusted User attributes but has no accepted persistent
  assignment store;
- Member authorization constants and application services;
- `AccountStateService`, which permits only `active ↔ suspended` transitions,
  locks the User, and writes an append-only audit event;
- the accepted MVP-01 Member portal;
- no shared administrator shell;
- no Member Filament resource;
- no persisted administrator role/permission assignment foundation;
- no production administrator credential-delivery process; and
- no Operator or Image Gateway administration implementation.

Use actual repository evidence if it differs.

Do not reinterpret the baseline from conversation memory.

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
- `$TARGET/docs/mvp/evidence/mvp-01-member-access-and-profile.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-01-member-access-and-profile-v1.md`;
- `$TARGET/.agents/tasks/mhcs-core-mvp-01-password-eligibility-dashboard-gate-v1.md`;
- `$TARGET/composer.json`;
- `$TARGET/bootstrap/providers.php`;
- `$TARGET/bootstrap/app.php`;
- `$TARGET/config/auth.php`;
- `$TARGET/config/mhcs.php`;
- `$TARGET/app/Models/User.php`;
- `$TARGET/app/Providers/AppServiceProvider.php`;
- `$TARGET/app/Shared/Auth/AccountStateUserProvider.php`;
- `$TARGET/app/Shared/Context/LaravelAuthenticatedContextProvider.php`;
- `$TARGET/app/Shared/Authorization/AuthorizationGuard.php`;
- `$TARGET/app/Shared/Security/CredentialVerifier.php`;
- `$TARGET/app/Shared/Audit/DatabaseAuditStore.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberAuthorization.php`;
- `$TARGET/app/Modules/Member/Application/Services/AccountStateService.php`;
- `$TARGET/app/Modules/Member/Application/Services/MemberContextResolver.php`;
- `$TARGET/app/Modules/Member/Domain/Models/Member.php`;
- `$TARGET/database/factories/UserFactory.php`;
- `$TARGET/database/seeders/DatabaseSeeder.php`;
- all current migrations that define `users`, `members`, `sessions`, and
  `audit_events`;
- current Filament and Livewire configuration;
- current tests covering authentication, authorization context, account state,
  audit, and MVP-01; and
- the installed Filament 5 source and test utilities under `vendor/**` when
  needed to confirm the exact supported APIs.

Inspect the installed Filament version before implementing panel, login-page,
resource, table, action, and test APIs.

Do not rely on memory of an earlier Filament major version.

Do not use network research when the installed package source answers the API
question.

## Architecture invariants

Treat these as binding:

1. `mhcs-core` is one modular application and deployment.
2. `/admin` is a shared administrator-facing interface, not an independent
   Admin business domain.
3. Member administrative resources, actions, policies, and audit views remain
   owned by the Member module.
4. Shared navigation does not transfer Member data or business-rule ownership.
5. Operator and Image Gateway resources must not be added in MVP-02.
6. A panel, page, resource, table action, or form must not mutate Member or User
   records directly when an approved application service owns the transition.
7. Trusted roles and permissions are server-derived; request, form, query,
   route, session, Livewire payload, or browser claims are assertions only and
   never create authorization.
8. An administrator-only User remains an ordinary shared application User and
   does not require a Member record.
9. One User may later hold permissions from multiple module administration
   areas, but MVP-02 grants and exposes only the Member administration
   capability.
10. Shared authorization storage must remain generic; Member-specific
    permission names remain owned by the Member module.
11. No generic cross-module database editor is allowed.
12. No raw NIK, KK, identity image, verification object key, encrypted
    identifier, identifier lookup digest, password, temporary credential,
    session identifier, or unrestricted audit metadata may be shown.

## Scope and constraints

- Implement only the shared admin shell and Member administration foundation.
- Application changes may include only the minimum files required for:
  - persistent trusted role and permission assignments;
  - trusted-claim resolution;
  - shared admin-panel access;
  - custom generic/rate-limited admin authentication;
  - the Filament panel provider and shell;
  - Member list and read-only detail;
  - Member suspend/restore actions;
  - bounded Member audit visibility;
  - local/testing-only synthetic administrator provisioning;
  - focused tests; and
  - MVP documentation/evidence updates.
- Preserve all Work Packages, MVP task files, decision history, requirement
  assignments, and accepted MVP-01 behavior.
- Do not modify published task files, including this task.
- Do not modify `.agents/context/**`.
- Do not modify `docs/implementation/**`.
- Do not change requirement assignments, classifications, counts, or source
  digests.
- Do not add or replace Composer or npm dependencies.
- Do not install an authorization package.
- Do not create a second authentication guard unless installed Filament 5
  proves the shared guard cannot safely support the panel.
- Do not modify the WP-04 UUID migration.
- Do not add public registration, admin self-registration, password reset,
  email verification, MFA, remember-me, social login, invitation delivery, or
  production credential delivery.
- Do not add Member creation, Member editing, User email editing, profile
  editing, identity editing, credential reset, identity-document review,
  guardian management, assisted recovery, age transition, account import, or
  bulk operations.
- Do not add service offerings, schedules, bookings, payments, points,
  promotions, settings, Operator resources, Image Gateway resources, Doctor
  resources, or generic audit administration.
- Do not expose arbitrary SQL, JSON editing, raw model editing, generic
  create/edit/delete, mass assignment, or bulk account-state mutation.
- Do not run full-suite, MySQL, Docker, deployment, npm-build,
  Composer-audit, or external integration validation during this task.
- Do not commit, stage, push, deploy, access production, or perform a
  production-affecting operation.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `5`
- Approval gates: stop as `awaiting-approval` whenever any declared stop condition is met.

## Execution procedure

1. Resolve and verify `$TARGET`.
2. Validate baseline ancestry and inspect repository state.
3. Read all required architecture, MVP, Member, authentication,
   authorization, audit, Filament, migration, and test evidence.
4. Inspect the installed Filament 5 APIs and current Laravel middleware/session
   behavior.
5. Design the smallest normalized persistent trusted-claim foundation that
   satisfies this task without inventing module business ownership.
6. Implement the claim foundation and preserve existing authenticated-context
   behavior.
7. Implement the `/admin` panel and its generic, rate-limited authentication.
8. Implement the Member-owned read and account-state administration slice.
9. Implement bounded Member audit visibility.
10. Add the local/testing-only synthetic administrator seeder.
11. Add focused tests and targeted regressions.
12. Run only the declared focused verification.
13. Update the bounded MVP documentation and evidence.
14. Re-read the task against the final diff.
15. Report the outcome without continuing into MVP-03.

## Persistent trusted authorization claims

### Required model

Add a normalized shared authorization-assignment foundation.

Preferred tables:

```text
authorization_role_assignments
authorization_permission_assignments
```

Equivalent names are acceptable when they clearly belong to the shared
authorization foundation.

Required role-assignment fields:

```text
id
user_id
role
assigned_by_user_id nullable
active
created_at
updated_at
```

Required permission-assignment fields:

```text
id
user_id
permission
assigned_by_user_id nullable
active
created_at
updated_at
```

Requirements:

- use UUID-compatible string identifiers consistent with current shared User
  IDs;
- use foreign keys to `users.id`;
- use `restrictOnDelete()` for the assigned User;
- allow `assigned_by_user_id` to be null only for controlled bootstrap/seeding;
- use `nullOnDelete()` or another explicitly justified non-destructive rule for
  the assigning User;
- enforce uniqueness for one active logical assignment per User and role or
  permission;
- index User and active lookup paths;
- preserve existing Users;
- provide a reversible `down()` for only the new assignment tables;
- do not add roles or permissions as comma-separated strings or JSON on
  `users`;
- do not accept wildcard roles or permissions;
- do not add site, organization, case, or clinical scope columns in this MVP;
- do not treat these tables as ownership of Operator site assignments or other
  module-specific scope; and
- do not add a panel UI for assignment management.

If the chosen database cannot enforce an active-only uniqueness predicate
portably across MySQL and SQLite, enforce one row per User/claim and toggle its
`active` flag rather than inserting duplicates.

### Claim resolver

Create an explicit shared contract, for example:

```text
AuthorizationClaimResolver
```

with a database-backed implementation.

It must return:

```text
roles(User|string $user): list<string>
permissions(User|string $user): list<string>
```

Equivalent typed methods are acceptable.

Requirements:

- query only active assignments;
- normalize to unique ordered strings;
- return empty claims when no assignment exists;
- fail closed if persistent storage is unavailable;
- never read roles or permissions from request input;
- never merge route, query, form, Livewire, cookie, or session-supplied claims;
- avoid unbounded repeated queries within one request by using safe
  request-scoped memoization or equivalent;
- do not cache assignments across requests without an explicit invalidation
  design;
- keep the resolver in the shared authorization/authentication foundation; and
- keep Member permission constants in the Member module.

### Authenticated context integration

Update `LaravelAuthenticatedContextProvider` to derive its trusted roles and
permissions through the persistent resolver.

Requirements:

- preserve actor ID, operation ID, hashed session ID, purpose handling, site,
  and case semantics;
- preserve anonymous context behavior;
- preserve existing test providers and domain-service tests;
- do not trust ad hoc Eloquent attributes named `trusted_roles` or
  `trusted_permissions` in production context;
- ensure Member application services called from Filament receive the
  authenticated administrator's persisted trusted claims; and
- add focused regression tests proving caller-supplied claims cannot elevate
  access.

Do not add claims to session storage as the authorization source.

## Member administration permissions

Add explicit Member-owned permission constants.

Preferred permission names:

```text
member.admin.access
member.account.read
member.account.manage
member.audit.read
```

Equivalent stable names are acceptable when documented consistently.

Use:

```text
administrator
```

as the required shared role for this panel slice.

Requirements:

- panel access requires:
  - strict authenticatable account state;
  - role `administrator`; and
  - at least one configured module panel-access permission, initially
    `member.admin.access`;
- Member resource list/detail requires `member.account.read`;
- suspend/restore requires the existing `member.account.manage`;
- Member audit visibility requires `member.audit.read`;
- no one permission silently implies every other permission;
- a role without required permission is denied;
- a permission without the `administrator` role is denied; and
- the browser cannot supply or modify claims.

Add bounded authorization methods or policies where appropriate.

Do not scatter raw permission string comparisons through unrelated UI classes.

## Shared admin-panel access service

Create one shared panel-access service or equivalent policy.

It must verify:

- the target panel ID is the approved shared admin panel;
- User account status is `active`;
- `login_enabled = true`;
- `must_change_password = false`;
- persistent role includes `administrator`; and
- persistent permissions include an approved module admin-access permission
  configured for the shared panel.

Preferred configuration:

```php
'mhcs.admin_panel.access_permissions' => [
    'member.admin.access',
],
```

Equivalent configuration under `config/mhcs.php` is acceptable.

Requirements:

- missing or malformed configuration fails closed;
- the allowlist contains exact strings only;
- no wildcard or prefix permission matching;
- the service can be used by both custom login admission and
  `User::canAccessPanel()`;
- future Operator or Image Gateway tasks may append their exact access
  permission without changing Member ownership; and
- no panel access decision is based on a Member record.

## User and Filament access contract

Implement the installed Filament 5 User access contract on `User` only as
required by the package.

Requirements:

- `canAccessPanel()` delegates to the shared panel-access service;
- it denies unknown panel IDs;
- it denies missing assignments;
- it denies suspended, pending, login-disabled, or mandatory-change Users;
- it does not expose claim arrays through serialization;
- it does not turn every administrator into a Member;
- it preserves existing User casts, hidden attributes, UUID behavior, and
  `canAuthenticate()` semantics; and
- ordinary Member portal authentication remains unchanged.

## Shared Filament panel

Register one Filament panel provider.

Preferred location:

```text
app/Providers/Filament/AdminPanelProvider.php
```

Register it through the current Laravel provider mechanism.

Required panel identity:

```text
id: admin
path: /admin
```

Requirements:

- use the existing shared `web` guard/session foundation;
- use the existing User model;
- do not create a separate administrator user table;
- do not create a separate module identity;
- do not enable registration;
- do not enable password reset;
- do not enable email verification;
- do not enable remember-me;
- do not expose a profile editor unless Filament requires one and it can be
  disabled;
- use the standard CSRF, session, cookie, and authentication middleware
  required by installed Filament 5;
- keep global account-state and mandatory-password middleware effective;
- configure a clear panel name such as `MHCS Administration`;
- create a navigation group labeled `Member`;
- discover or register only shared shell pages and Member resources for MVP-02;
- do not auto-discover future Operator, Image Gateway, or Doctor resources;
- do not expose default widgets that reveal unrelated application data;
- do not add charts or speculative operational metrics; and
- do not create a generic model/resource generator surface.

A minimal landing page may show only safe Member-account counts, or may use a
plain Filament dashboard with no unrelated widgets.

## Admin authentication

### Login route and page

Use the panel's supported login route under:

```text
/admin/login
```

Implement a custom Filament 5 login page or equivalent supported hook so admin
login reuses:

- `CredentialVerifier::verify()` strict account-state behavior;
- existing generic credential failure semantics;
- existing pair/origin/identifier throttling;
- existing dummy-hash behavior;
- existing credential-verification audit; and
- the shared admin-panel access service.

Admin login uses:

```text
email
password
```

Do not offer NIK on the admin login page.

Requirements:

- canonicalize email to lowercase through the existing verifier;
- validate only that email/password input is present and bounded;
- do not reveal whether the email exists;
- do not reveal whether credentials were correct but panel permission was
  absent;
- do not reveal role, permission, account, Member-link, or mandatory-change
  state;
- return one generic Bahasa Indonesia error for all rejected states;
- do not authenticate the User until both credential verification and panel
  access pass;
- regenerate the session ID after successful login;
- clear any untrusted intended external URL;
- redirect only to the panel's approved internal landing route;
- do not store the password or reusable credential result in session;
- do not log or audit the submitted email or password;
- do not add a second rate limiter;
- do not bypass `CredentialVerifier`;
- keep login response behavior compatible with installed Filament 5; and
- use a second `canAccessPanel()` check as defense in depth.

A credential-valid but panel-unauthorized attempt must remain generic to the
browser.

A sanitized panel-access denial audit may record only:

- action;
- outcome;
- target User ID when safely available;
- correlation ID;
- and a non-sensitive reason code.

Do not audit role/permission lists or email.

### Logout

Use Filament's supported POST logout behavior.

Requirements:

- invalidate the session;
- regenerate the CSRF token;
- redirect to `/admin/login` or the approved panel login route;
- do not provide GET logout; and
- do not preserve an untrusted intended destination.

## Member resource

Place Member administration classes under the Member module.

Preferred logical location:

```text
app/Modules/Member/Filament/Resources/Members/**
```

Equivalent Filament 5-compatible module organization is acceptable.

### Query and ownership

The resource query must:

- begin from Member-owned `members`;
- join or eager-load the linked User safely;
- return exactly one row per Member;
- select only fields required by the list/detail/actions;
- avoid loading verification assets, guardians, family, external identifiers,
  or protected NIK fields;
- avoid N+1 queries;
- not use route or request claims to change authorization;
- scope access through exact Member permissions; and
- fail closed for missing or inconsistent User links.

Do not create an unrestricted generic User resource.

### List

Provide a paginated Member list.

Allowed columns:

```text
name
medical_record_number
email
phone
profile_completion
identity_status
account_status
login_enabled
must_change_password
registration_source
created_at
```

A smaller safe subset is acceptable.

Requirements:

- label internal statuses in understandable Bahasa Indonesia;
- profile completion uses the existing derived four-field rule;
- no persisted completion percentage;
- search may support:
  - Member name;
  - medical-record number; and
  - email;
- do not search by raw NIK, KK, encrypted values, or lookup digests;
- filters may support:
  - account status;
  - identity status;
  - login enabled;
  - mandatory password change; and
  - registration source;
- pagination must be bounded;
- do not enable CSV, Excel, PDF, clipboard, bulk export, or print export;
- do not enable bulk actions;
- do not enable create, edit, delete, force-delete, restore-record,
  replicate, or inline editing;
- do not expose raw model JSON; and
- do not place email, phone, or MRN in route parameters.

The route key must remain the internal Member UUID.

### Detail

Provide a read-only detail page.

Allowed sections:

- Member:
  - name;
  - medical-record number;
  - birth date;
  - administrative gender;
  - identity status;
  - identity document type;
  - registration source;
  - profile completion;
  - created and updated timestamps;
- account:
  - email;
  - phone;
  - account status;
  - login enabled;
  - mandatory password change;
- approved account-state actions; and
- bounded audit history.

Do not show:

- raw NIK;
- decrypted NIK;
- KK;
- encrypted NIK;
- NIK/KK lookup digest;
- identity-document images;
- profile photographs;
- private object keys;
- checksums;
- verification asset metadata;
- guardian data;
- family data;
- external identifiers;
- address;
- emergency-contact values;
- password hash;
- remember token;
- session records;
- roles or permissions;
- unrestricted audit metadata;
- or internal exception messages.

Birth date is read-only and must not be editable.

### No mutation forms

Do not register create or edit pages for Member.

Do not provide a general-purpose form.

The resource must return false or deny:

- create;
- edit;
- delete;
- delete any;
- force delete;
- restore;
- replicate; and
- reorder.

## Account-state actions

Expose only:

```text
Suspend
Restore
```

Use `AccountStateService`.

Requirements:

- `Suspend` is visible only when the linked User is `active`;
- `Restore` is visible only when the linked User is `suspended`;
- pending or other states expose neither action;
- the current authenticated administrator cannot suspend their own User through
  this UI;
- each action requires:
  - exact `member.account.manage` permission;
  - explicit confirmation;
  - a non-empty reason;
  - maximum reason length of 1,000 characters;
- User ID is derived from the selected Member relation;
- action input contains no account status target supplied by the browser;
- the action callback calls `AccountStateService::suspend()` or
  `AccountStateService::restore()`;
- the action does not write directly to `users`;
- the service's locked transition and append-only audit remain authoritative;
- domain failures are mapped to a generic admin notification;
- success refreshes the table/detail state;
- the reason is not rendered in URLs;
- no bulk suspend/restore exists; and
- no arbitrary target state exists.

A stale concurrent action must fail safely or return the service's idempotent
current-state behavior.

Suspension must prevent subsequent Member and admin authentication and must be
enforced on the next authenticated request through the existing middleware and
provider rules.

## Bounded Member audit visibility

Provide read-only Member audit visibility.

The view may be:

- a relation-style section on Member detail;
- a Member-owned Filament page;
- or another bounded Filament 5-compatible table.

It must include only events relevant to the selected Member and linked User, or
a Member-owned audit page restricted to Member-source actions.

Allowed fields:

```text
occurred_at
action
outcome
actor_id
target_type label
target_id
reason
correlation_id
```

A smaller safe subset is acceptable.

Requirements:

- require `member.audit.read`;
- order newest first;
- paginate when more than a small bounded number is shown;
- read only from append-only audit storage;
- do not edit, delete, replay, export, or annotate audit events;
- do not display:
  - roles JSON;
  - permissions JSON;
  - session ID;
  - previous/new state digests;
  - unrestricted metadata;
  - raw request data;
  - email;
  - phone;
  - NIK;
  - address;
  - emergency contact;
  - password;
  - credential values;
  - identity assets;
  - or clinical payload;
- do not allow arbitrary target-type input from the browser;
- filter server-side by the selected Member ID and linked User ID;
- preserve correlation IDs for traceability; and
- show a clear empty state.

Do not create a generic application-wide audit explorer.

## Local/testing-only synthetic administrator seeder

Add one dedicated seeder.

Preferred name:

```text
MvpAdminSeeder
```

Requirements:

- refuse to run outside explicitly allowed `local` and `testing`
  environments;
- do not call it automatically from `DatabaseSeeder`;
- create one clearly synthetic administrator-only User;
- do not create a Member record for the administrator;
- use a UUID User ID;
- use a clearly synthetic `.test` email;
- generate a cryptographically secure unique password only when first created;
- hash the password before persistence;
- set:
  - `account_status = active`;
  - `login_enabled = true`;
  - `must_change_password = false`;
- assign active:
  - role `administrator`;
  - permission `member.admin.access`;
  - permission `member.account.read`;
  - permission `member.account.manage`;
  - permission `member.audit.read`;
- use null `assigned_by_user_id` only for this controlled bootstrap;
- print the plaintext credential once to the interactive console;
- do not write plaintext credentials to files, logs, audit, cache, environment
  templates, documentation, tests, or source;
- repeated execution must not reset the password;
- repeated execution must reconcile missing expected claims without creating
  duplicates;
- repeated execution must not silently reactivate intentionally disabled claims
  unless the task documents and tests that behavior;
- report existing account/assignment inconsistencies and stop rather than
  overwriting unrelated data;
- print a clear local/testing-only warning; and
- provide the safe invocation command in evidence without including the
  generated password.

Because no administrator mandatory-password-change flow exists in MVP-02, the
local/testing seeder may set `must_change_password = false`. Record this as a
local/testing-only bootstrap control, not a production credential policy.

Production administrator provisioning and credential delivery remain open.

## Authorization and security invariants

The implementation must prove:

- `/admin` is inaccessible to guests except its login page;
- valid credentials without the administrator role are denied generically;
- administrator role without `member.admin.access` is denied;
- `member.admin.access` without administrator role is denied;
- suspended, pending, login-disabled, and mandatory-change Users are denied;
- an administrator-only User does not require a Member record;
- persistent claims, not browser claims, authorize the panel;
- caller-supplied role or permission input cannot elevate access;
- Member list/detail requires `member.account.read`;
- suspend/restore requires `member.account.manage`;
- audit visibility requires `member.audit.read`;
- resource actions call the Member application service;
- no create/edit/delete or bulk mutation exists;
- no Operator, Image Gateway, or Doctor resource exists;
- raw NIK and protected identity values are absent from rendered HTML,
  Livewire payloads, URLs, logs, notifications, and audit metadata;
- login and action failures do not reveal internal authorization state;
- session ID regenerates after admin login;
- logout invalidates the session;
- CSRF protects state-changing requests;
- suspension takes effect on subsequent authenticated requests;
- account state and audit append occur in the same service transaction;
- no plaintext credential is persisted; and
- no production credential process is claimed.

## Focused tests

Create focused tests under a bounded Admin/Member feature boundary.

Preferred files:

```text
tests/Feature/Admin/Mvp02AdminAccessTest.php
tests/Feature/Admin/Mvp02MemberAdministrationTest.php
```

Equivalent bounded organization is acceptable.

Use installed Filament 5 and Livewire test APIs.

### Claim foundation

Test:

- role assignments resolve only active exact roles;
- permission assignments resolve only active exact permissions;
- duplicates are prevented;
- unknown User returns empty claims;
- browser/request role or permission input does not change resolved claims;
- `LaravelAuthenticatedContextProvider` returns persisted claims;
- anonymous context remains anonymous;
- malformed or unavailable assignment storage fails closed;
- request-scoped memoization does not leak claims between Users; and
- deactivating an assignment affects the next request.

### Admin login and panel access

Test:

- `/admin/login` renders for guests;
- `/admin` redirects guests to the panel login;
- a valid authorized administrator logs in;
- session ID regenerates after login;
- an untrusted external intended URL is ignored;
- unknown email and wrong password use the same generic error;
- credential-valid non-administrator uses the same generic error;
- role-only and permission-only Users are denied;
- suspended User is denied;
- pending User is denied;
- login-disabled User is denied;
- mandatory-change User is denied;
- failed login does not leave an authenticated session;
- existing credential throttling applies;
- no submitted email/password appears in audit metadata or logs;
- POST logout invalidates the session; and
- GET logout does not perform logout.

### Member resource

Test:

- administrator with read permission can access the Member list;
- administrator without read permission is denied;
- list renders only safe approved columns;
- list HTML/Livewire payload does not contain raw NIK, encrypted NIK, lookup
  digest, KK, object keys, password hash, remember token, address, or emergency
  contact;
- search by name works;
- search by MRN works;
- search by email works;
- raw NIK is not configured as a search field;
- account and identity filters remain bounded;
- resource has no create route;
- resource has no edit route;
- create/edit/delete/replicate/bulk actions are absent or denied;
- no Operator, Image Gateway, or Doctor navigation item/resource is present;
  and
- an administrator-only account can use the panel without a Member row.

### Account state

Test:

- active Member account exposes suspend;
- suspended Member account exposes restore;
- pending account exposes neither;
- reason is required;
- an administrator without manage permission cannot execute the action;
- suspend calls the existing service behavior and stores `suspended`;
- restore calls the existing service behavior and stores `active`;
- each transition creates the expected sanitized append-only audit;
- no direct unexpected fields are mutable through the Livewire action payload;
- self-suspension is not exposed;
- bulk state action is absent;
- a suspended Member cannot use the Member portal on the next request; and
- concurrent/stale state behavior remains safe.

### Audit visibility

Test:

- administrator with audit-read permission sees relevant Member/User events;
- administrator without audit-read permission is denied;
- unrelated Member events are absent from a selected Member view;
- safe fields are visible;
- roles, permissions, session IDs, digests, unrestricted metadata, NIK,
  address, emergency contact, and credentials are absent;
- audit is newest first;
- no edit/delete/export action exists; and
- empty state renders safely.

### Seeder

Test:

- seeder refuses outside local/testing;
- creates one administrator-only User;
- creates no Member;
- creates the exact expected active role and permissions;
- password is hashed;
- repeated execution does not reset the password;
- repeated execution creates no duplicate assignments;
- plaintext credential is absent from database, audit, files, and evidence; and
- seeder is not called by `DatabaseSeeder`.

Focused tests may use fixed test passwords inside isolated test code.

Do not commit a reusable development administrator password.

## Targeted regression boundary

When shared authentication, context, or User files change, run only targeted
existing tests needed to prove accepted behavior remains intact.

At minimum, identify and run focused existing tests covering:

- strict `User::canAuthenticate()` behavior;
- `AccountStateUserProvider`;
- `CredentialVerifier` generic failure and throttling;
- `AuthorizationGuard` trusted-context behavior;
- caller claims as assertions only;
- audit sanitization and append-only behavior;
- Member `AccountStateService`;
- MVP-01 email/NIK login;
- MVP-01 restricted password replacement;
- MVP-01 profile/dashboard access;
- suspended Member fail-closed behavior; and
- the accepted direct password-post remediation.

Use filters or individual test methods/files.

Do not run complete WP-02 or WP-04 suites unless focused filtering cannot
select changed behavior reliably.

## MVP documentation updates

Update only documentation required to record MVP-02.

### `docs/mvp/roadmap.md`

Record MVP-02 implementation status and evidence.

Do not describe MVP-03 as started.

Do not renumber later MVP tasks.

### `docs/mvp/beta-gap-register.md`

Preserve stable IDs.

When all MVP-02 acceptance criteria pass:

- update `MVP-GAP-010` to `closed`;
- cite the MVP-02 evidence path in its notes;
- state that closure covers only the shared admin shell and Member
  administration foundation;
- keep Operator administration gap `MVP-GAP-024` open;
- keep Image Gateway administration gap `MVP-GAP-025` open;
- keep production credential-delivery gap `MVP-GAP-020` open;
- keep B2B import gap `MVP-GAP-003` deferred;
- keep identity-verification UI gap `MVP-GAP-006` open; and
- keep all unrelated gaps unchanged.

Add a new stable gap only when implementation discovers a real limitation not
already represented.

### `docs/mvp/work-package-status.md`

Update only affected evidence and MVP relevance.

Expected bounded changes:

- WP-01/WP-02 evidence may mention the persistent trusted-claim and panel-access
  foundation without changing accepted status;
- WP-10 evidence may mention the shared shell and bounded Member account
  administration;
- WP-10 remains `partially-implemented`;
- broad Member administration, B2B import, and acceptance harness remain
  deferred; and
- Operator/Image Gateway Work Packages remain unchanged.

Do not alter requirement assignments.

### MVP-02 evidence

Create:

```text
docs/mvp/evidence/mvp-02-shared-admin-shell-member-administration.md
```

Record:

- accepted baseline and execution commit;
- Filament version observed;
- panel ID and route;
- persistent assignment tables;
- claim-resolution model;
- exact role and permissions;
- login admission and generic failure behavior;
- Member resource safe fields;
- account-state actions and service boundary;
- audit visibility boundary;
- safe local/testing seeder invocation without plaintext credential;
- focused tests and observed results;
- targeted regressions and observed results;
- changed files;
- gaps closed or retained;
- full verification not run; and
- no production-readiness claim.

Do not record generated plaintext credentials.

## Required checks

Run only focused checks.

Required:

```bash
git diff --check
```

Run:

- the focused MVP-02 feature tests;
- targeted shared-authentication/context regressions;
- targeted Member account-state regressions;
- targeted MVP-01 regressions affected by shared changes;
- bounded Pint on changed PHP files;
- the Filament/route list inspection for:
  - `/admin`;
  - `/admin/login`;
  - Member list/detail;
  - logout; and
  - absence of create/edit routes;
- a provider registration inspection;
- a migration status or schema inspection in the test environment only; and
- a static search of changed files for:
  - `password`;
  - `remember_token`;
  - `encrypted_nik`;
  - `nik_lookup_digest`;
  - `family_card`;
  - `private_object_key`;
  - `roles`;
  - `permissions`;
  - `metadata`;
  - `session_id`.

Review matches to ensure no protected value or authorization claim is exposed,
logged, audited, persisted, or trusted incorrectly.

Do not run:

- complete PHPUnit;
- complete WP-02;
- complete WP-04;
- MySQL conformance;
- `deployment/verify-mysql.sh`;
- `deployment/validate.sh`;
- Docker builds;
- migrations outside test/local disposable environments;
- local seeders against non-disposable data;
- `npm run build`;
- Composer audit;
- dependency installation;
- external service checks; or
- deployment verification.

The later integration/release gate will run full validation once after bounded
MVP slices are accepted.

## Acceptance criteria

- [ ] The repository contains the accepted MVP-01 baseline.
- [ ] Existing overlapping work was not overwritten.
- [ ] Filament 5 installed APIs were inspected before implementation.
- [ ] One shared panel exists with ID `admin` at `/admin`.
- [ ] No separate administrator user table or authentication guard was added.
- [ ] No registration, reset, verification, remember-me, or public provisioning route was enabled.
- [ ] Persistent role and permission assignments are normalized and server-derived.
- [ ] Assignment migration preserves existing Users and is safely reversible.
- [ ] Trusted claims resolve only active exact assignments.
- [ ] `LaravelAuthenticatedContextProvider` uses the persistent resolver.
- [ ] Browser, request, route, Livewire, cookie, and session claims cannot elevate access.
- [ ] Panel access requires strict account state, administrator role, and exact module access permission.
- [ ] Missing/malformed panel access configuration fails closed.
- [ ] `User::canAccessPanel()` delegates to the shared access service.
- [ ] Admin login reuses `CredentialVerifier` throttling, dummy hash, generic failure, and audit.
- [ ] Unauthorized and invalid login failures are indistinguishable to the browser.
- [ ] Admin login regenerates the session and ignores untrusted intended URLs.
- [ ] Admin logout is POST-only and invalidates the session.
- [ ] Administrator-only Users do not require Member rows.
- [ ] Member resources reside under Member ownership.
- [ ] Member list/detail require `member.account.read`.
- [ ] Member list/detail show only approved safe fields.
- [ ] NIK, KK, protected identifiers, identity assets, address, emergency contact, credentials, sessions, roles, and permissions are not exposed.
- [ ] Search is limited to name, MRN, and email.
- [ ] Member create/edit/delete/replicate/reorder/bulk/export capabilities are absent.
- [ ] Suspend/restore require `member.account.manage`.
- [ ] Suspend/restore require confirmation and non-empty bounded reason.
- [ ] Suspend/restore call `AccountStateService` and do not write User state directly.
- [ ] Pending states expose no unsupported transition.
- [ ] Self-suspension is not exposed.
- [ ] Account-state transitions remain audited and transactional.
- [ ] Member audit visibility requires `member.audit.read`.
- [ ] Audit visibility is read-only, bounded, relevant, and safe.
- [ ] No generic application-wide audit explorer exists.
- [ ] No Operator, Image Gateway, or Doctor resource/navigation was added.
- [ ] Local/testing-only `MvpAdminSeeder` creates one synthetic administrator safely.
- [ ] The seeder is not called automatically.
- [ ] Repeated seeding does not reset the password or duplicate claims.
- [ ] No plaintext administrator credential is persisted or committed.
- [ ] Focused MVP-02 tests pass.
- [ ] Targeted shared-authentication/context regressions pass.
- [ ] Targeted Member account-state regressions pass.
- [ ] Affected MVP-01 regressions pass.
- [ ] Admin and Member route inspection matches the declared surface.
- [ ] Bounded Pint passes.
- [ ] `git diff --check` passes.
- [ ] MVP roadmap, gap register, Work Package ledger, and evidence are updated accurately.
- [ ] `MVP-GAP-010` is closed only when focused evidence passes.
- [ ] Production credential delivery and unrelated gaps remain open.
- [ ] No dependency, unrelated feature, production configuration, deployment, commit, or push was performed.
- [ ] The final report distinguishes focused evidence from unrun full validation.

## Verification

- Method: Run `git diff --check`, focused MVP-02 Filament and authorization tests, targeted shared-authentication and Member account-state regressions, affected MVP-01 regressions, bounded Pint, and admin-route/provider/safe-surface inspection.
- Expected result: The shared `/admin` shell admits only persistently authorized active administrators, exposes only bounded Member-owned read and account-state administration with safe audit visibility, preserves MVP-01 behavior, and introduces no unrelated module or production scope.

## Stop conditions

Stop as `awaiting-approval` when:

- the accepted baseline is absent from repository history;
- current work overlaps required files;
- a newer accepted RBAC or Filament panel implementation exists and conflicts
  with this task;
- installed Filament 5 APIs cannot support a custom login that reuses
  `CredentialVerifier` without replacing the shared authentication foundation;
- panel access would require weakening `User::canAuthenticate()` or
  `AccountStateUserProvider`;
- persistent claims cannot be integrated into
  `LaravelAuthenticatedContextProvider` without breaking accepted trusted
  context semantics;
- role/permission assignment storage requires site-, organization-, case-, or
  clinical-scope decisions not approved for MVP-02;
- a safe administrator account requires production credential-delivery policy;
- Member list/detail cannot avoid loading or serializing protected identity
  fields;
- suspend/restore cannot use `AccountStateService` through the Filament action;
- safe audit visibility requires exposing unrestricted metadata;
- a required resource belongs to Operator or Image Gateway rather than Member;
- implementation requires a new Composer/npm dependency;
- implementation requires changing UUID strategy, requirement assignments,
  privacy/retention policy, or production deployment policy;
- focused tests expose a material WP-02, WP-04, or MVP-01 regression that cannot
  be corrected within this bounded slice;
- full MySQL, Docker, or deployment validation becomes necessary to establish
  basic correctness; or
- any destructive or production-affecting operation would be required.

When stopped:

- do not create an ad hoc admin boolean on `users`;
- do not store claims in session as authority;
- do not enable Filament registration/reset;
- do not bypass `CredentialVerifier`;
- do not add generic model editing;
- do not expose protected Member data;
- do not continue into MVP-03.

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
- Filament version observed;
- panel ID, paths, and registered provider;
- authorization-assignment tables and claim resolver;
- exact role and permissions used;
- admin login and logout behavior;
- Member resource fields, search, filters, and denied operations;
- account-state action behavior and service boundary;
- audit visibility boundary;
- local/testing seeder class and safe invocation command;
- files changed;
- focused tests and observed results;
- targeted regressions and observed results;
- route/provider/formatting checks;
- MVP documentation and gap changes;
- known limitations;
- unrun full validation;
- approval boundaries that remain open; and
- confirmation that no dependency, production configuration, deployment,
  commit, push, Operator, Image Gateway, Doctor, booking, payment, imaging, or
  result functionality was added.

Do not include generated plaintext credentials.

Do not commit or push.

Stop after MVP-02.
