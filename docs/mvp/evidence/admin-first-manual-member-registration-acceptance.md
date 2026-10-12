# Admin-first manual Member registration — V7 and A9 record

**Recorded:** 2026-10-12
**V7:** Passed — `accepted_with_documented_low_risk_limitation`
**A9:** Passed for the bounded development scope below
**Accepted development revision:** `21d279831455d32fe2a707e8d306af861c0bbb08`

## Identity and attribution

- Repository: `Madeena-software/mhcs-core`.
- Governing task: `.agents/tasks/admin-first-manual-member-registration.md` at
  `e7ccc686da049e78d7634125237fc794b30a0908`.
- Implementation baseline: `fb25354311121fc8163256cd0530d9cbcb66bb68`.
- Original implementation: `7eb4b8cd87ae478cce0e3a7d85c61f2713d3531e`.
- Reviewed remediation and immutable accepted baseline:
  [21d279831455d32fe2a707e8d306af861c0bbb08](https://github.com/Madeena-software/mhcs-core/commit/21d279831455d32fe2a707e8d306af861c0bbb08).
- Implementation and verification evidence: [PR #7](https://github.com/Madeena-software/mhcs-core/pull/7).

The independent verdict was supplied externally in the human handoff titled
“MHCS — Complete V7 Recording and A9 Acceptance”. The handoff states that the
independent reviewer inspected the original implementation and remediation
through GitHub, accepted AC1–AC8 for this bounded slice, and found no further
blocking implementation defects. It explicitly supplies `V7 PASSED` and
`accepted_with_documented_low_risk_limitation`.

This document records that supplied verdict and completes acceptance recording;
it is not a new independent review by the implementation agent. No reviewer
identity or signature was supplied or is invented. At preflight, GitHub's PR
review API returned no review submissions; the supplied external verdict is
not represented as a GitHub APPROVED review or satisfaction of branch-protection
review requirements. No approval review is submitted on the reviewer's behalf.

## Accepted scope and remediation closure

The accepted journey is `/admin` → Members → Create Member → Save → updated
Member directory, using synthetic identities and isolated local/testing storage.
Adult NIK and basic demographics are mandatory. KTP and profile photographs are
independently optional; all four combinations are supported. MRN comes from the
existing Member service. Identity remains `pending_verification`; account remains
`pending_activation`, with login disabled and mandatory password change. Supplied
photos remain private, pending and non-current. No evidence placeholders,
automatic verification or activation are introduced.

The external reviewer confirmed closure of both prior findings:

1. The form and creation handler accept only JPEG/PNG and reject WebP before
   private persistence, including WebP content disguised with a JPEG filename.
2. Pending asset recording requires an in-progress registration operation bound
   to the newly inserted Member, preventing reuse after registration completes.

The prior bounded remediation is closed. Under workflow section 18, further R8
is `not_applicable` for the final accepted verdict. This does not reopen or
replace the exact governing task revision.

| Criterion | Accepted evidence boundary |
|---|---|
| AC1 | Eligible Admin entry; anonymous, ineligible and read-only denial; fresh submission/upload authorization after mount and revocation. |
| AC2 | Four photo combinations with/without verification permission; linked User/Member, protected NIK, independent MRN, NULL/ktp metadata, no-photo zero writes and private pending evidence; directory/detail outcome. |
| AC3 | Pending identity and account, disabled login and Member dashboard denial regardless of verification permission; no automatic activation or verification. |
| AC4 | Replay, changed-payload/actor/entry conflicts, duplicate identities, rollback and attempt cleanup preserving committed evidence; isolated MySQL conformance and six no-photo contention rounds. Concurrent optional-photo submissions remain untested. |
| AC5 | Mandatory demographics and scoped domain guards; JPEG/PNG success, WebP/content rejection, invalid/oversized uploads and limits; private metadata checks within new-registration lifecycle; completed/missing/mismatched operation rejection. |
| AC6 | Minimized list/detail and errors; sensitive state cleared on success; private evidence controls. Browser evidence before remediation is historical, not a fresh browser check. |
| AC7 | Legacy hash/replay, required evidence, review/replacement, identity, activation and nonclinical regressions; complete SQLite PHP suite. No unrelated clinical/operational workflow expansion. |
| AC8 | Exact local/testing bootstrap permission reconciliation and preservation; non-local allowlist excludes registration permission even with the production-seed override. No production seeder execution. |

## Verification evidence

These are the observed remediation-session results against the accepted
implementation, recorded in PR #7. They were not rerun during this evidence-only
acceptance recording. SQLite commands used
`APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL=`. Suites ran
sequentially with synthetic fixtures and isolated testing storage.

| Command/check | Observed result |
|---|---|
| `php artisan test tests/Feature/Admin/Mvp02AdminAccessTest.php tests/Feature/Admin/Mvp02MemberAdministrationTest.php tests/Member/Wp04IdentityTest.php tests/Feature/Admin/AdminMemberRegistrationTest.php --compact` | 119 passed; 1,044 assertions. |
| `php artisan test tests/Member/NonclinicalValidationMemberIdentityTest.php tests/Feature/Validation/NonclinicalValidationContextProvisioningTest.php tests/Architecture tests/Security --compact` | 68 passed; 2,193 assertions. |
| `php artisan test --compact` | 734 total: 727 passed, 7 skipped; 9,636 assertions. |
| Focused four-file set plus `tests/Integration/MemberDatabaseConformanceTest.php --compact --fail-on-skipped` against isolated MySQL 8.4 | 127 passed; 1,093 assertions; zero skips. Disposable loopback-only database/container using the preinstalled image without pulling or installing dependencies. |
| Isolated two-worker no-photo registration contention probes | Six rounds passed: three same-operation and three duplicate-NIK; pending states, one linked account/audit and committed replay preserved. |
| `vendor/bin/pint --test` on the five remediation PHP files; `git diff --check` | Passed. |

No GitHub check runs or commit statuses were present for the reviewed SHA at
acceptance preflight. GitHub's combined status `pending` with zero statuses is
absence of CI evidence, not a passing CI result. Five Member MySQL-dependent
checks skipped in the full SQLite suite passed in the separate MySQL conformance
run; X-ray protocol and check-in ticket MySQL contention were not rerun.

## Limitations, open gaps and release status

The independent reviewer explicitly accepted these non-blocking limitations for
bounded development acceptance:

- Full MySQL suite was not executed.
- Concurrent optional-photo submissions were not exercised.
- Browser smoke was not repeated after remediation.
- No production/release approval is implied.

These limits are acceptable for this development acceptance, not production
certification. No CI, real-data, real-storage fault, clinical, privacy/legal or
production-readiness certification is asserted.

[MVP-GAP-027–034](../beta-gap-register.md#admin-first-gaps-and-authority-reconciliation)
remain open for their broader scopes. This record establishes only the bounded
registration acceptance evidence relevant to MVP-GAP-034; it does not close that
repository-wide gap or other application acceptance gaps. MVP-GAP-031 and its
production privacy/storage/retention dependencies remain unresolved. Admin
worklists/check-in, imports, temporary clinical workspaces, physical verification,
activation and Member result delivery are excluded. The previously reported 19
default-branch Dependabot alerts are not assessed or remediated by this record.

**Release status:** Not approved. G10/separate Release Gate is not passed.
Acceptance grants no production permission, production data access or deployment.

## A9 and integration boundary

Under `.agents/software-workflow.md` sections 19.1–19.2 and Gate A9, the externally
accepted immutable implementation `21d279831455d32fe2a707e8d306af861c0bbb08` is
explicitly established as the next accepted development baseline for this scope.
A9 does not require a merge. The original task baseline remains unchanged.

A subsequent commit containing only this record and a context pointer is
acceptance evidence, not a replacement accepted implementation revision. Its
record accuracy and integration diff still need review under applicable GitHub
policy; no independent acceptance of that later commit is asserted. Implementation
code and the governing task remain unchanged.

PR #7 may proceed to integration review against `main`. Merge authorization is
outstanding. No merge, force-push, branch deletion, deployment or production
operation is authorized or performed by this recording. The next valid integration
action is review of the evidence-only addition and explicit authorization to merge
PR #7, subject to applicable GitHub checks/review requirements. Development planning
may separately continue from the accepted SHA without release approval.
