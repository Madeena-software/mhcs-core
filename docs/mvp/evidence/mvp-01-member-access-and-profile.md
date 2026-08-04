# MVP-01 Member Access and Profile Evidence

## Boundary

- Accepted planning baseline: `35148a39694ef137adb263dab609d456b42d8c76`.
- Execution commit: `b8eef9a7846ec4c836bff3eeb726f06480bfafbc`.
- Execution target: `/var/www/mhcs-core` (`TARGET="."`).
- No commit, staging, push, deployment, production access, dependency change, or task-file runtime mutation was performed.

## Remediation evidence — password eligibility and dashboard gate

- Remediation baseline: `fc4d71560ca3cbddfb885d556fa1f7bed786ce25`.
- Execution commit: `734868d6c4d3e2bee16a24022f4d246d9d3c1637`; remediation changes remain uncommitted as required.
- Password replacement now enforces actor authorization, active account state, login enablement, mandatory-change state, exactly one linked Member, adult eligibility, and current-credential validity inside the transactional mutation boundary. `MemberContextResolver::resolveForUserId()` remains an ownership resolver; the service performs the locked adult-eligibility check explicitly.
- Direct POST rejection coverage includes unlinked, child-linked, suspended, pending, login-disabled, and invalid-current-credential states. Rejections preserve the original password hash, mandatory-change flag, account state, and login-enabled state, create no success audit, and leave no handled password-replacement operation.
- Eligible adult password replacement remains successful.
- Incomplete profiles at 0%, 25%, 50%, and 75% redirect from the dashboard to the profile. A 100% profile renders the approved safe dashboard fields, and incomplete profile drafts remain saveable.
- Focused test: `php artisan test tests/Feature/Member/Mvp01MemberAccessTest.php` — 13 tests, 152 assertions passed.
- Targeted regression: `php artisan test tests/Member/Wp04IdentityTest.php --filter=test_child_registration_requires_verified_guardian_and_age_transition_ends_access_atomically` — 1 test, 18 assertions passed.
- Bounded Pint on changed PHP files — passed.
- Route-list inspection — the existing eight MVP-01 routes and HTTP methods remain unchanged.
- `git diff --check` — passed.
- Static inspection of changed files for credential and protected-identifier handling — completed; no new logging, audit, rendering, or persistence of sensitive values was added.
- Full PHPUnit, WP-02/WP-04 suites, MySQL, Docker, deployment, npm build, Composer audit, dependency installation, synthetic seeding outside tests, and external integrations were not run.
- No production-readiness claim is made.

## Implemented behavior

- Routes: `login`, `login.store`, `password.change-required`, `password.change-required.update`, `member.profile`, `member.profile.update`, `member.dashboard`, and `logout`.
- Login uses one `identifier` field for canonical email or protected NIK lookup and reuses the existing verifier throttling, dummy-hash, generic failure, and audit boundary.
- Strict `User::canAuthenticate()`, ordinary Laravel authentication, and strict `CredentialVerifier::verify()` behavior remain unchanged.
- Active adult Members with `must_change_password = true` receive a restricted authenticated session. The central web middleware permits only password replacement and POST logout until the flag is cleared.
- Password replacement uses `MandatoryPasswordReplacementService`, generates its operation ID server-side, regenerates the session, and does not render or persist plaintext credentials.
- Profile ownership starts from authenticated `users.id`; the Member resolver rejects missing, ambiguous, child, or unrelated ownership. Editable fields are limited to email, phone, current address, and emergency-contact fields.
- The forward migration adds nullable `current_address`, `emergency_contact_name`, `emergency_contact_relationship`, and `emergency_contact_phone` fields. Completion is derived from those four fields at 0/25/50/75/100 percent.
- The dashboard displays only Member name, medical-record number, completion, identity status, account status, and a clearly unavailable future-service placeholder.
- Logout is POST-only, CSRF-protected by Laravel's web middleware, invalidates the session, and regenerates the CSRF token.
- `MvpMemberSeeder` is limited to local/testing environments, creates synthetic adult Member/User records with protected identifiers and private synthetic assets, generates unique hashed temporary credentials, and does not reset existing credentials.

## Verification observed

- `python3 .agents/skills/agent-task/scripts/validate_task.py .agents/tasks/mhcs-core-mvp-01-member-access-and-profile-v1.md` — passed.
- `php artisan test tests/Feature/Member/Mvp01MemberAccessTest.php` — 10 tests, 97 assertions passed.
- `php artisan test tests/Feature/Member/Mvp01MemberAccessTest.php tests/Security/Wp02SecurityTest.php tests/Member/Wp04IdentityTest.php` — 50 tests, 304 assertions passed.
- `vendor/bin/pint` on changed PHP files — passed.
- `php artisan route:list` inspection — all eight required routes and HTTP methods present.
- `git diff --check` — passed.
- Static review of changed files for credential and protected-identifier terms — completed; test-only known passwords are confined to focused tests, and no generated credential is recorded in this document.

## Known limitations and open boundaries

- Full PHPUnit, MySQL, Docker, deployment, `npm run build`, Composer audit, and external-service validation were not run, as required by the task.
- Public/online registration, password reset, email verification, B2B import, production credential delivery, child/guardian access, identity-verification UI, shared administration, Operator, Image Gateway, bookings, payments, imaging, and results remain out of scope.
- The forward-only UUID migration, privacy/retention policy, production object-storage policy, production credential handoff, and deployment approval remain open gaps.
- No production-readiness claim is made.
