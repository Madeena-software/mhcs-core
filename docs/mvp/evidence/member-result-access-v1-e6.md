# Member Result Access v1 — E6 implementation evidence

Status: **REVIEW REQUIRED**. E6 implementation and local verification complete;
independent V7/R8/A9 and separate release authorization remain required.

Terminal review state and final observed results are recorded below. This is Executor
evidence, not independent V7/R8/A9 acceptance or release authorization.

## Governing scope

- Task: `.agents/tasks/admin-authorized-member-result-access-v1.md` at
  `3ea425531f4836af38b08b06acd7ef3728b1e00c`.
- Accepted application baseline: `cd5128fdf78c2ad8ef95b62786e24858cc57e7ca`.
- Human handoff: “MHCS — E6 EXECUTION AUTHORIZED: Member Result Access v1”,
  supplied 2026-10-16 Asia/Jakarta, declares independent exact-revision T5 PASSED,
  approves bounded synthetic implementation/testing and ordinary commit/push to
  `feat/member-result-access-v1`. No PR, merge, force-push, production, operational
  migration, real data, dependency installation or external messaging authority.
- Initial clean checkout was the exact task commit; its sole diff from the accepted
  baseline was the task document. The feature branch was created from that commit.
  The task itself and existing incomplete-source clinical flag are unchanged.

## Implemented boundaries

Member owns publication, recipient validation, lifecycle and safe presentation.
Doctor owns the final-only projection and exact report/finalization/author/digest
verification. Operator supplies its exact current handoff and recorded subject
identity through an owner contract. Admin has no clinical-table queries.

Publication requires an active individual Admin, explicit action permission and an
active persisted `member_result_managers` facility binding. Permissions are never
seeded or default-granted. Locked assignment values govern the final decision,
including under MySQL REPEATABLE READ. Initial prechecks disclose no report.

`member.result-publication.publish` permits exact-version preview and publication;
`.hold`, `.suspend` and `.revoke` authorize their respective withdrawals. A safe
status-only view permits a scoped authorized withdrawal even when current clinical
or identity evidence no longer supports report preview.

Publication stores only references, generation, integrity digests, publisher, times
and current state. A per-case revision and unique operation keys serialize decisions.
Identical current retries preserve the original grant and expiry; stale decisions
cannot resurrect withdrawn grants. A new explicit publication creates a fresh opaque
ID/generation; old locators remain invalid. Revoke is terminal for the old grant.
Decision history is appended with actor, action, time, revision and payload digest;
mutation, history and required audit share one transaction.

Only the exact current booking Subject of Care may read. Current approved KTP and
profile photo, valid verification attribution, an unambiguous account binding,
verified identity, current active login and age at least 18 are all required.
Current subject name, MRN, birth date and administrative sex must match the original
verified handoff identity. Verification asset replacement changes the stored identity
digest and withholds old publication access. Roles, payer, requester, creator, family,
guardian and staff authority confer no alternative recipient entitlement.

The only Member endpoint is a signed examination-scoped locator, capped by its
publication's original 24-hour expiry and bound to its exact publication/generation.
The existing login flow preserves only the local result entry destination and signed
query. Login does not authorize the report. A login password fingerprint, refreshed
after mandatory password replacement, detects credential changes. Before sending,
the response reloads persisted session state and independently repeats current
recipient, publication, expiry and lineage checks with committed audit. No database
lock is held while transmitting bytes. Already delivered content cannot be recalled.

Member HTML contains findings, conclusion, optional recommendations, examination
code/facility/scheduled time (explicitly labelled as schedule, not acquisition) and verified author/finalizer attribution/time. Text is escaped.
All success/denial paths use private no-store, no-referrer and restrictive content
headers. Errors are generic; no draft, report history, raw objects, AI, financial,
identity-document, credential or clinician-workspace data is rendered.

## Clinical source coverage and synthetic boundary

`UnknownClinicalSourceInventory` deliberately denies every application-default
positive publication/read. This preserves the lack of authoritative historical or
external source coverage; the existing clinical source still reports
`complete_sources=false`. No environment flag, default fixture marker or workspace
issuance is treated as coverage authority.

The isolated test/bootstrap binds `ClosedSyntheticClinicalInventory` only after a
fresh disposable synthetic journey establishes one exact case, original study,
upstream handoff, authored versions and real finalization, with zero AI jobs. It seals
and rechecks actual source/manifest/protocol/handoff/report/finalization evidence,
original object bytes and hashes. It does not replace Doctor's finalization reader
or upstream handoff verification with a service mock. Source records are re-read
under locks when inside the authorization transaction. Any unknown, changed or
conflicting evidence denies. Production enablement requires a separately approved,
trustworthy coordinated source inventory; this synthetic feature grants no such
approval or production capability.

## Routes and additive schema

| Route | Behavior |
|---|---|
| `GET/HEAD /member/results/entry/{publication}` | Signed locator, existing Member login, independent current authorization, final-only protected response |
| `GET/HEAD /admin/member-results/{case}` | Explicit scoped publication preview, or minimized withdrawal status when preview unavailable |
| `POST /admin/member-results/{case}` | Exact capability, CSRF, current revision/key and explicit publish/hold/suspend/revoke decision |

The Radiologist Admin management page links to the separate Member publication
surface after finalization. The Doctor management projection adds only the existing
case reference for that navigation. No finalization hook publishes automatically.

`2026_10_16_000002_create_member_result_publication_tables.php` adds
`member_result_managers`, `member_result_cases`, `member_result_publications` and
`member_result_history`. No historical migration was edited, and no migration was
run on an existing operational database. Creation, constraints and down/up are tested
only in disposable synthetic databases.

## Acceptance traceability

`F` below is `Tests\Feature\Member\MemberResultAccessTest`; `C` is
`Tests\Integration\MemberResultConcurrencyTest`; `B` is
`tests/Browser/member-result-access.mjs`.

| AC | Implementation and named verification |
|---|---|
| AC1 | Exact Admin capability/facility gates and owner-only report projection; F `test_missing_publication_capability_denies_before_doctor_projection`; C `test_invalidation_committed_after_discovery_before_authorization_denies_publish` permission/role/facility cases; B separate Admin decision |
| AC2 | Exact immutable final/report/author/digest/current-handoff and verified recipient checks; F `test_manual_exact_version_publication_and_existing_member_login`, `test_original_subject_identity_drift_before_publication_denies`; C identity/lineage invalidation; B proves finalization leaves zero publications before manual decision |
| AC3 | Unique case/generation and case/operation/revision, appended history and current pointer; F `test_idempotency_preserves_expiry_and_history_and_revoke_is_terminal`; C `test_publish_publish_is_one_immutable_generation_and_retry`, `test_publish_withdrawal_cannot_both_use_same_revision` |
| AC4 | Signed opaque entry through existing login; F manual-login test and `test_mandatory_password_replacement_returns_to_result_with_fresh_credential`, `test_unsigned_link_and_external_login_return_disclose_nothing`, invalidation data for tampering/generation/expiry; B correct and copied wrong-recipient login |
| AC5 | Current exact adult self identity, approved assets/account/password; F `test_independent_current_authorization_denies` data cases and `test_exact_eighteenth_birthday_uses_trusted_date`; B wrong-recipient and missing-identity withholding |
| AC6 | Prepared-response recheck and persisted-session reload; F `test_prepared_response_rechecks_committed_revocation_including_head`, `test_prepared_response_withholds_after_persisted_session_invalidation`; C prepared-release invalidation cases; B refresh after withholding |
| AC7 | Escaped final-only HTML; F `test_report_template_escapes_authored_markup_and_exposes_only_approved_fields` and manual-login sentinel assertions; B real final fields, absence of raw/AI/draft/financial endpoints and screenshots |
| AC8 | Hold/suspend/revoke decisions, terminal old generation; F idempotency and `test_withdrawal_only_capability_and_invalid_lineage_still_allow_revocation`; C publish/withdraw and prepared-release cases; B active-lineage denial, explicit hold/manual republication, stale generation and suspend/revoke/expiry denial |
| AC9 | Required durable minimized audit and atomic history; F `test_audit_failure_rolls_back_mutation_and_withholds_prepared_body` plus audit sentinel assertions; C `test_atomic_audit_commit_failure_rolls_back`; B audit-failure denial |
| AC10 | Actual disposable SQLite and MySQL independent processes with socket barriers, committed invalidation before prepared release, rollback and schema lifecycle; all C tests, including `test_schema_teardown_and_recreation` |
| AC11 | B real Admin issue → Radiographer original rendering/QC/handoff → individual Radiologist original rendering/authored finalization → separate Admin publication → existing Member login and final rendering, for direct and converted originals; keyboard focus, generic errors and legitimate recovery observed |
| AC12 | Existing Doctor/Radiographer/architecture/security and full exact-baseline comparisons; B zero AI jobs and unchanged source checks; no excluded business side effects |

## Runtime and observed checks

Supported installed image `mhcs-radiographer-workspace:verified`: PHP 8.4.26,
Node 24.21.0, util-linux 2.41.5, SQLite 3.46.1. Disposable MySQL image `mysql:8.4`
reports 8.4.8 and REPEATABLE-READ. No dependency installation or verifier bypass.
Default Docker security remains enabled; no privileged or seccomp-unconfined run.
The disposable HTTP server runs with `php -d opcache.enable=0 -S` because the image
sets `opcache.validate_timestamps=0`. Earlier hot-copied source did not invalidate
HTTP bytecode; final browser evidence is collected only after server restart against
the final files. This changes no repository/production configuration or verifier limit.

Source copies exclude `.env`; installed vendor/node dependencies are reused without installation.
For final full suites, source and Composer bootstrap metadata are copied into native
container storage to reduce host mount overhead; installed packages are reused through
read-only mounts/symlinks. The installed `dicom-parser` package is copied unchanged
to its native path so Node filesystem permissions remain exact. Both full suites use
the same bootstrap arrangement.
Databases are only synthetic SQLite files or `mhcs_rad_synthetic` on the isolated
internal `mhcs-member-e6-net` network, backed by disposable MySQL tmpfs. The browser
uses a localhost-only byte relay to the actual PHP server because Docker Desktop
loopback is not directly available from this host; no authorization or content is
substituted. The upstream regression container has separate tmpfs storage. Backend
race fixtures use separate private-object disks to avoid cross-run interference.

An initial full-baseline attempt lacked built assets; an initial candidate package
lacked Docker configuration files. Those incomplete packaging attempts are discarded.
Early prepared-response tests passed a session manager instead of a store; early
parallel test fixtures shared a private-object disk. Corrected harness runs, rather
than those incomplete attempts, govern the final results below. SQLite WAL is
configured per connection and verified after schema creation. Every SQLite race uses
a new database file, avoiding replacement of a WAL file while an old connection may
retain it. MySQL uses disposable `migrate:fresh`. An early isolated password test lacked the mounted Node dependencies;
its corrected run traverses actual source verification and passes.

Final observed local command results:

| Check | Observed result |
|---|---|
| Supported focused Doctor/Radiographer/architecture/security/verifier PHP | 166 passed, 3,296 assertions, zero skips, exit 0 |
| `MHCS_MEMBER_BACKEND=mysql` Member lifecycle/race suite | 19 passed, 180 assertions, zero skips, exit 0; actual MySQL 8.4.8 REPEATABLE-READ |
| `MHCS_RAD_MYSQL_TESTS=1` existing Doctor/Radiographer races | 31 passed, 376 assertions, zero skips, exit 0 |
| Exact password-replacement/result-return test in supported isolated runtime | 1 passed, 9 assertions, exit 0 |
| Synthetic identity evidence through existing approved asset service plus login/result | 1 passed, 28 assertions, exit 0 |
| Explicit viewer JavaScript checks | 19 passed, zero failed/skipped, exit 0 |
| Host `npm run build` | Exit 0, DICOM bundle check passed; existing optional font/chunk/plugin-time warnings |
| Final full candidate: `php -d memory_limit=1G run-tests.php --do-not-cache-result --log-junit storage/member-candidate.xml` in `/native` | 1,023 tests: 941 passed, 9 failures, 16 errors, 57 skips; 14,040 assertions; exit 2 |
| Exact baseline, same native command/bootstrap | 968 tests: 905 passed, 9 failures, 16 errors, 38 skips; 13,781 assertions; exit 2 |
| Failure/error comparison by full test identity and status | Same 25 identities; zero new/resolved failures/errors. All 19 additional default skips are the explicit Member backend suite, independently passed on MySQL |
| Final focused Member PHP suite | 36 passed, 206 assertions, zero skips, exit 0 |
| Final `MHCS_MEMBER_BACKEND=sqlite` lifecycle/race suite | 19 passed, 180 assertions, zero skips, exit 0; actual SQLite 3.46.1, independent processes, fresh WAL files |
| Final MySQL recheck after fixture/source finalization (`publish_publish` and `schema_teardown`) | 2 passed, 19 assertions, zero skips, exit 0 |
| Changed PHP/Blade syntax, changed-PHP Pint, browser JavaScript syntax and staged diff checks | All passed; 29 PHP/Blade files; 21 application/schema/view/route hashes match both runtime copies |
| Final supported browser: `MHCS_RAD_CONTAINER=mhcs-member-e6 MHCS_MEMBER_ARTIFACTS=/home/faliq/.codex/task-state/mhcs-core/member-e6 node tests/Browser/member-result-access.mjs` | Exit 0: 2 direct/converted journeys, 4 original renderings, 2 real QC/handoffs, 2 finalizations, 3 separate manual publications, 1 manual republication/stale-generation/active-lineage case; wrong recipient, identity denial and audit failure twice each; suspend/revoke/expiry denial |

No CI result or independent acceptance is asserted. The final implementation SHA and
local/fetched/live remote equality are recorded in the handoff and external checkpoint,
not as a self-containing revision in this file.

The 25 identical baseline failures/errors concern existing WebP/Mpdf and Python/PDF
browser tooling. They are not repaired or relabelled as passing. Default opt-in skips
are not race evidence: the explicit Member SQLite/MySQL runs and existing MySQL
regressions supply the zero-skip race results separately.

Final artifacts outside Git are under
`/home/faliq/.codex/task-state/mhcs-core/member-e6/`: baseline/candidate/focused/SQLite
JUnit files, MySQL and upstream logs, exact failure-identity comparison, source hashes,
build/JavaScript/browser logs and `member-direct-final.png` /
`member-converted-final.png`. A scan found no usable signed locator or raw HTTP
credential headers in retained textual evidence. Screenshots contain only synthetic
final-report viewport content, with no browser chrome or locator field. Original
images are 2×2 synthetic pixels: rendering evidence is not diagnostic-quality or
clinical-display certification.

A read-only supporting technical review found and prompted repairs for original
identity drift, MySQL snapshot claims after authorization locking, and stale result
credentials after mandatory password replacement. A recheck found these repairs in
source; runtime tests above independently supply the execution evidence. This review
is not independent Planner–Reviewer acceptance.

No PR, merge, force-push, operational migration, deployment, production/real-patient
access, external message, notification, PDF, amendment, guardian access, financial
operation, dependency installation or permission provisioning was performed. Local
synthetic fixture grants do not provision any operational account. Broader source
coverage, privacy/legal/clinical/retention/rollout and production readiness remain open.

## Changed-file inventory

```text
app/Http/Controllers/Member/AuthenticationController.php
app/Http/Controllers/Member/ResultController.php
app/Modules/Doctor/Application/Contracts/ClinicalSourceInventory.php
app/Modules/Doctor/Application/Contracts/FinalReport.php
app/Modules/Doctor/Application/Services/FinalReportRead.php
app/Modules/Doctor/Application/Services/RadiologistWorkspace.php
app/Modules/Doctor/DoctorServiceProvider.php
app/Modules/Doctor/Infrastructure/UnknownClinicalSourceInventory.php
app/Modules/Member/Application/Contracts/ResultPublication.php
app/Modules/Member/Application/Services/InteractiveMemberLoginService.php
app/Modules/Member/Application/Services/MemberResultAccess.php
app/Modules/Member/MemberServiceProvider.php
app/Modules/Operator/Application/Contracts/ResultHandoff.php
app/Modules/Operator/Application/Services/ResultHandoffRead.php
app/Modules/Operator/OperatorServiceProvider.php
database/migrations/2026_10_16_000002_create_member_result_publication_tables.php
docs/mvp/evidence/member-result-access-v1-e6.md
resources/views/member/results/manage.blade.php
resources/views/member/results/report.blade.php
resources/views/member/results/unavailable.blade.php
resources/views/radiologist/manage.blade.php
routes/web.php
tests/Architecture/FoundationArchitectureTest.php
tests/Browser/member-result-access.mjs
tests/Feature/Member/MemberResultAccessTest.php
tests/Integration/MemberResultConcurrencyTest.php
tests/Support/ClosedSyntheticClinicalInventory.php
tests/Support/MemberResultFixtures.php
tests/Support/member-result-browser-bootstrap.php
tests/Support/member-result-browser-fixture.php
tests/Support/member-result-http.php
```
