# MVP-02 Shared Admin Shell and Member Administration Evidence

## Execution

- Accepted baseline: `4efe6994a4b4823fba2aca21d1edba8f0bd7808c`.
- Execution commit observed at task start: `f2bbe19908b31e9489179fc5ffd49697164f1bda`.
- Target: `.`.
- Filament observed: `v5.7.5`.
- No commit, push, dependency change, deployment, or production configuration change was performed.

## Implemented boundary

- Registered provider: `App\Providers\Filament\AdminPanelProvider` in `bootstrap/providers.php`.
- Panel: ID `admin`, path `/admin`, shared `web` guard.
- Explicit resource: Member-owned `MemberResource`; no Operator, Doctor, or Image Gateway resource was registered.
- Persistent claim tables: `authorization_role_assignments` and `authorization_permission_assignments`, with UUID keys, active flags, unique user/claim constraints, assigner foreign keys, and reversible migration.
- Claim resolution reads active persisted claims only, filters empty/wildcard values, memoizes per request-scoped resolver instance, and fails closed on storage errors. Request/session `trusted_*` attributes are not used.
- Exact role: `administrator`.
- Exact permissions: `member.admin.access`, `member.account.read`, `member.account.manage`, and `member.audit.read`.

## Authentication and authorization

The custom `/admin/login` page accepts email and password only and calls `CredentialVerifier::verifyForInteractiveLogin`. Every rejection uses the generic Indonesian message `Email atau kata sandi tidak sesuai.`, including panel-access denial. Successful admission requires an active, login-enabled, non-mandatory-change account, the exact administrator role, and the configured exact panel permission. The session is regenerated and the stored intended URL is cleared before redirecting internally. Filament logout remains POST-only and uses the shared session guard.

## Member surface

The Member resource exposes only safe name, medical record number, email, birth date, administrative gender, identity status/document type, registration source, phone, profile completion, account status, login-enabled, mandatory-password-change, and timestamps. Search is limited to name, medical record number, and email. Filters are bounded to known account, identity, login, password-change, and registration-source values. Address, emergency contact, protected identifiers, identity assets, credentials, sessions, claims, unrestricted metadata, and raw audit fields are not rendered.

Member create/edit/delete/replicate/reorder/bulk/export surfaces are absent. Suspend and restore are confirmation actions with a required reason capped at 1000 characters, reject self-suspension, require `member.account.manage`, and call `AccountStateService`; they do not write User state directly. Unsupported pending transitions are not exposed.

The read-only audit table requires `member.audit.read`, selects only occurrence time, action, outcome, actor ID, target label/ID, reason, and correlation ID, and restricts rows to `source=member` events targeting the selected Member or linked User. No generic application-wide audit explorer was added.

## Seeder

Run only in local/testing environments with:

```text
php artisan db:seed --class=MvpAdminSeeder
```

`Database\Seeders\MvpAdminSeeder` creates one synthetic `.test` administrator without a Member row, hashes a generated credential, prints it only once to an interactive console, and does not run through `DatabaseSeeder`. Repeated execution preserves the password and claims; inconsistent existing account, Member link, or claim state causes a stop rather than repair. No generated plaintext credential is recorded here.

## Verification evidence

- MVP-02 focused tests: `tests/Feature/Admin/Mvp02AdminAccessTest.php` and `tests/Feature/Admin/Mvp02MemberAdministrationTest.php` — 10 tests, 68 assertions, passed.
- MVP-01 regression: `tests/Feature/Member/Mvp01MemberAccessTest.php` — 13 tests, 152 assertions, passed.
- Shared security regression: `tests/Security/Wp02SecurityTest.php` — 23 tests, 94 assertions, passed.
- Member account/identity regression: `tests/Member/Wp04IdentityTest.php` — 17 tests, 113 assertions, passed when run independently.
- `php artisan route:list --path=admin` shows only the admin home redirect, login, POST logout, Member index, and Member view routes.
- `php artisan package:discover` passed.
- `git diff --check` passed.
- PHP syntax checks passed for changed PHP files.

## Changed files

Application changes are limited to the shared claim resolver/context binding, admin panel provider/login/access service, User and Member authorization hooks, Member Filament resource/pages/audit projection, assignment migration, and `MvpAdminSeeder`. Focused tests are under `tests/Feature/Admin/`. MVP documentation changes are limited to the roadmap, gap register, Work Package ledger, and this evidence file.

The full test suite, full WP-02/WP-04 validation, production configuration, deployment, and production-readiness checks were not run or claimed. MVP-GAP-010 is closed only for this bounded shell and Member administration foundation. MVP-GAP-003, MVP-GAP-006, MVP-GAP-020, MVP-GAP-024, MVP-GAP-025, and unrelated gaps remain open/deferred as recorded in the gap register.
