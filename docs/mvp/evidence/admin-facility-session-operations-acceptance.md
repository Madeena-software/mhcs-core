# Admin Facility-Session Operations — V7 and A9 record

**Recorded:** 2026-10-13

**V7:** PASSED — `accepted_with_documented_low_risk_limitation`

**A9:** PASSED for bounded development acceptance

**R8:** Not applicable

**G10:** Not approved

**Accepted application revision:** `1ec07b154d1c7db3cd4fe389f3219091ea8ca595`

## Identity and attribution

- Repository: `Madeena-software/mhcs-core`.
- Governing [task](../../../.agents/tasks/admin-facility-session-operations.md):
  `9ddf6f800b296353733b5369fd9f42cccae70e93` (version 1.4, Validated/Published).
- Original application baseline: `fc37002d106b839c0b881e7dec7021c91389f501`.
- Independently reviewed and accepted implementation:
  [1ec07b154d1c7db3cd4fe389f3219091ea8ca595](https://github.com/Madeena-software/mhcs-core/commit/1ec07b154d1c7db3cd4fe389f3219091ea8ca595).
- [PR #10](https://github.com/Madeena-software/mhcs-core/pull/10):
  `task/admin-facility-session-operations`, stacked on the validated planning
  branch in [PR #9](https://github.com/Madeena-software/mhcs-core/pull/9).
- Published [execution evidence](admin-facility-session-operations-execution.md)
  and [T5 record](admin-facility-session-operations-t5.md) remain unchanged.

The human handoff “MHCS — Finalize V7/A9 Acceptance and Publish Evidence” supplies
the independent ChatGPT Planner/Reviewer's decision above and explicit Product
Owner approval of the baseline-debt exception below. This Executor records that
externally supplied verdict; it performs no new implementation review or
self-acceptance. No reviewer identity or signature beyond the supplied role is
invented. At recording preflight, PR #10 had zero GitHub review submissions;
the external verdict is not a GitHub APPROVED review or proof that integration
review requirements are satisfied.

## Accepted scope and AC disposition

The bounded accepted journey is eligible Admin login → `/admin` → managed
facility/local date → persisted sessions and independently authorized operational
summaries → existing authorized facility/schedule/registration/detail workflows
→ refreshed persisted state. Member and Operator retain owning reads and mutation
services. X1 Option A protects the existing schedule capacity count with fresh
booking-read authorization and the approved **Kapasitas Tercadangkan** label.
Minimized attributable read audit must commit before disclosure; failures
withhold values. No schema, dependency, runtime configuration, grants, clinical
or unrelated application changes are accepted by this record.

The following dispositions record the supplied bounded verdict against the
published AC evidence matrix; they do not replace the immutable task criteria.

| Criterion | Disposition and evidence boundary |
|---|---|
| AC1 | Accepted: eligible entry, persisted session selection, safe empty/denied states and actual browser login/filtering. |
| AC2 | Accepted: facility-local today/date, UTC half-open membership, two-zone midnight, overnight and 23/25-hour DST fixtures; invalid/mismatched scope withheld. |
| AC3 | Accepted: exact booking totals/statuses/reserved/remaining, grouped counts, zero/over-quota cases, independent booking gate and X1 query/payload protection. |
| AC4 | Accepted: independently authorized distinct recorded arrivals, internal relation validation, duplicate/progressed bookings and corrupt/cross-facility/unavailable cases. |
| AC5 | Accepted: existing authorized management and registration/detail paths; actual schedule/facility save-return; service authorization and immutability preserved. |
| AC6 | Accepted: fresh exact capabilities/account eligibility, real HTTP/Livewire revocation/deactivation, forged/missing/unverifiable claims, minimized snapshots and keyboard/localized UI evidence. |
| AC7 | Accepted: attributable read audits, audit/source/commit failure withholding, bounded queries and audit-only database deltas; no domain/outbox mutation. |
| AC8 | Accepted for this development slice with the explicit Product Owner-approved baseline-debt exception below. Full SQLite and scoped MySQL/browser evidence support the board/X1; unsuccessful Operator cases remain unsuccessful. |

## Product Owner-approved baseline-debt exception

All **nine unsuccessful MySQL Operator tests (7 failures, 2 errors)** reproduce
identically on original baseline `fc37002d106b839c0b881e7dec7021c91389f501`.
The published comparison records identical test identities, classifications,
source lines and full messages: both baseline and implementation have **58
passed, 7 failed, 2 errors, 463 assertions, zero skips** in the same 67-test scope.
The execution record lists each case and the isolated comparison method.

The Product Owner explicitly approves this exception only for **development
acceptance of Admin Board/X1 Option A**. It neither accepts the affected Operator
workflows nor establishes production readiness, waives their failures, weakens
their test expectations or authorizes their repair. Existing year-2040 consent
TIMESTAMP rejection and consent/ticket/queue symptoms remain baseline debt;
the separate nonclinical `arrived` versus `checked_in` mismatch also remains
unresolved. No new board regression was found among these nine cases in the
published comparison.

## Existing verification evidence

These are previously observed local synthetic results from the execution record,
reused without rerunning unchanged application suites during acceptance recording.
The source record retains commands, fixtures, corrected runs and timing limits.

| Check | Published result |
|---|---|
| Board HTTP/Livewire/query/payload/audit suite | 24 passed; 738 assertions. |
| Existing Admin / registration / architecture-security | 27 / 82 / 40 passed; 244 / 760 / 2,075 assertions. |
| SQLite Operator scope | 66 passed; 1 skipped; 514 assertions. |
| Full SQLite PHP suite | 751 passed; 7 skipped; 10,431 assertions (758 total). |
| MySQL 8.4.11 board/Admin/registration/conformance | 141 passed; 1,792 assertions; zero skips. |
| MySQL Operator scope and baseline comparison | Both: 58 passed; 7 failed; 2 errors; 463 assertions; zero skips. |
| MySQL first X-ray publication concurrency | 1 passed; 9 assertions; zero skips. |
| Actual Chromium browser | 5 passed; 78 assertions, including save-return, permissions, filtering, unavailable states, keyboard and logout. |
| Changed PHP and implementation diff | Syntax/Pint, whitespace/scope, task preservation and added-secret/private-ID pattern checks passed. |

All seven full-SQLite skips were exercised in their supported MySQL scopes;
they remain SQLite skips. The full PHP run preceded final browser-only additions
and whitespace cleanup; the portable audit-key assertion was subsequently
corrected and focused SQLite/MySQL checks rerun, as disclosed in the source record.
No full MySQL suite was run. At acceptance preflight, the accepted SHA had zero
GitHub check runs and zero commit statuses; combined status `pending` with zero
statuses is absence of CI evidence. Local results are not CI.

## Limitations, integration and release boundary

- Baseline Operator failures remain unresolved under the narrow exception above.
- Installed-versus-locked dependency drift remains: ten Filament packages at
  5.7.8 versus locked 5.7.5; Commonmark 2.10.0 versus locked 2.9.0.
  Lock-aligned runtime/CI verification remains outstanding; dependencies are unchanged.
- No observed GitHub CI checks; full MySQL suite not run. Disposable MySQL
  memory-backed fixtures do not establish crash/disk durability or throughput.
- Production, clinical, privacy/legal, hospital, real-patient and release
  requirements remain separate. Operational read-audit retention/access,
  deletion/holds and external obligations remain production dependencies;
  APP-004 clinical retention is not extended. Broader MVP gaps, Patient target
  and AI safety/provenance work are not closed by this bounded acceptance.

Under workflow Gate A9, `1ec07b154d1c7db3cd4fe389f3219091ea8ca595` is the
immutable accepted development revision for this scope. The later evidence-only
commit does not replace that application revision and requires independent
Planner/Reviewer verification of recording accuracy and the publication diff.
The governing task and historical execution evidence remain unchanged.

PR #10 remains the single Draft stacked implementation PR; PR #9's validated
planning revision/dependency and legacy PR #8 are preserved. Integration requires
separate review, applicable GitHub requirements and explicit merge authorization.
**G10 is not approved:** no merge, rebase, branch deletion, deployment, production
access or release is authorized or performed by this recording.
