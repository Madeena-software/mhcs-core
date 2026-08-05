# MVP-02 Shared Admin Shell and Member Administration Evidence

## Execution

- Accepted remediation baseline: `e4bb004b92645a7392e76c0fca5fa49cfd42d60c`.
- Execution commit observed at task start: `e4bb004b92645a7392e76c0fca5fa49cfd42d60c` (working tree changes were uncommitted).
- Target: `.`.
- Filament observed: `v5.7.5`.
- The published task validator passed before execution.
- No commit, push, route, migration, dependency, deployment, local-deployment, or production configuration change was performed.

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

The read-only audit table requires `member.audit.read` at its server-side query boundary, selects only occurrence time, action, outcome, actor ID, target label/ID, reason, and correlation ID, orders newest first with deterministic tie-breakers, and restricts rows to `source=member` events targeting the selected Member or linked User. Unauthorized Livewire table initialization returns an empty query and cannot retrieve audit values. No generic application-wide audit explorer was added.

Suspend and restore re-authorize the authenticated administrator, Member-to-User linkage, self-target condition, current account state, exact transition, and trimmed reason at action execution time. Unexpected action payload fields cannot choose another User or target state. Successful transitions still use `AccountStateService`, and failed execution-time checks do not create a success audit.

## Seeder

Run only in local/testing environments with:

```text
php artisan db:seed --class=MvpAdminSeeder
```

`Database\Seeders\MvpAdminSeeder` creates one synthetic `.test` administrator without a Member row, hashes a generated credential, prints it only once to an interactive console, and does not run through `DatabaseSeeder`. Repeated execution preserves the password, inserts only missing expected claims, creates no duplicates, and leaves valid existing claims unchanged. Inactive, assigned, duplicate, unrelated, or otherwise inconsistent claims stop reconciliation rather than being reactivated or silently repaired. The seeder remains local/testing-only. No generated plaintext credential is recorded here.

## Verification evidence

- Validator: `python3 .agents/skills/agent-task/scripts/validate_task.py .agents/tasks/mhcs-core-mvp-02-remediation-admin-enforcement-v1.md` — passed.
- MVP-02 focused command: `php artisan test tests/Feature/Admin/Mvp02AdminAccessTest.php tests/Feature/Admin/Mvp02MemberAdministrationTest.php --no-coverage` — 22 tests, 143 assertions, passed.
- Direct MVP-01 regression: `php artisan test tests/Feature/Member/Mvp01MemberAccessTest.php --no-coverage` — 13 tests, 152 assertions, passed.
- Filtered WP-02 regression: `php artisan test tests/Security/Wp02SecurityTest.php --filter='test_(credential_verification_is_generic_rate_limited_and_denies_suspension|laravel_authentication_denies_suspended_and_temporary_accounts|audit_is_append_only_rejects_sensitive_metadata_and_rolls_back_with_state|sensitive_audit_payloads_are_rejected|sensitive_scalar_audit_payloads_are_rejected_under_neutral_keys|audit_and_outbox_follow_local_transaction_rollback|correlated_logs_are_recursive_and_sanitized|caller_claims_cannot_replace_trusted_actor|caller_claims_cannot_replace_trusted_scope)' --no-coverage` — 9 tests, 38 assertions, passed.
- Filtered WP-04 regression: `php artisan test tests/Member/Wp04IdentityTest.php --filter='test_(adult_activation|assisted_recovery|identity_verification_permission)' --no-coverage` — 5 tests, 30 assertions, passed.
- Bounded formatting: `vendor/bin/pint --test` on the five changed PHP files — passed.
- `php artisan route:list --path=admin` shows only 5 routes: admin home redirect, login, POST logout, Member index, and Member view. Provider/resource inspection shows only `AdminPanelProvider` and `MemberResource` in the admin surface.
- `git diff --check` passed. Static review found no newly exposed protected values, credentials, metadata, session IDs, or untrusted claim sources.

## Changed files

Remediation application changes are limited to `app/Modules/Member/Filament/Resources/Members/MemberResource.php`, `app/Modules/Member/Filament/Resources/Members/Pages/ViewMember.php`, and `database/seeders/MvpAdminSeeder.php`. Focused tests changed are `tests/Feature/Admin/Mvp02AdminAccessTest.php` and `tests/Feature/Admin/Mvp02MemberAdministrationTest.php`. MVP documentation changes are limited to the roadmap, gap register, Work Package ledger, and this evidence file.

The full test suite, full WP-02/WP-04 validation, MySQL, Docker, npm, Composer audit, external integrations, production configuration, deployment, and production-readiness checks were not run or claimed. MVP-GAP-010 is closed again only for this bounded remediation after the corrected focused checks passed. MVP-GAP-003, MVP-GAP-006, MVP-GAP-020, MVP-GAP-024, MVP-GAP-025, and unrelated gaps remain open/deferred as recorded in the gap register.
