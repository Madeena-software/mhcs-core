---
title: Prestige production rehearsal-data reset and fresh fixture seed
document_id: MHCS-TASK-PRESTIGE-PRODUCTION-DATA-APPLICATION-001
version: 2.2
status: validated-on-publication
language: en-US
last_updated: 2026-08-20
scope:
  - bounded deletion of diagnosed Prestige rehearsal execution data
  - fresh three-target Prestige fixture seed with exact 37-member booking sets
  - two-phase application and verifier implementation with immutable runtime binding
authority_note: This republication supersedes v1.8/v1.9 historical-preservation and in-place-reconciliation strategy. It authorizes only the bounded implementation described here after publication review. It does not authorize production execution, deployment, workflow dispatch, secret provisioning, or production mutation.
---

# Executable Task

## Task identity

**Task title:** Prestige production rehearsal-data reset and fresh fixture seed

**Task path:** `.agents/tasks/prestige-production-data-application.md`

**Task contract state:** Validated/Published upon immutable publication of this exact content

**Delivery objective / Work Package / MVP:** Perform one owner-approved, bounded reset of the diagnosed old Prestige rehearsal execution data, then seed three clean 37-member target schedules. Preserve existing identities and unrelated application data.

**Owner / designated planning authority:** Faliq Adlan, CTO

## Supersession and baseline

The owner has clarified that all old Prestige rehearsal execution data,
including progressed 14-Aug records, is disposable test/rehearsal data. Version
2.0 explicitly supersedes the v1.8/v1.9 strategy of preserving historical
14-Aug clinical history and reconciling 26/27 schedules in place.

**Implementation baseline:** `4bd7546cc93726c271733acf4d7b638d447fdb12`

**Reviewed implementation:** Phase A `fb013e7657484105bd86c046e687d676fb3d253b`;
Phase B/current main `4bd7546cc93726c271733acf4d7b638d447fdb12`.

**Terminal review verdict:** `REMEDIATION REQUIRED`.

**Production runtime before reset:** `b5a2306e7d2d1491285edfd0418d25b1cdea568f`

**Authoritative read-only diagnostic:** `32375201758`

**Task revision:** The full SHA of the publication commit containing this exact content. Resolve it before execution and independently review it before implementation.

This task does not itself authorize production execution. Later execution
requires independent implementation acceptance and fresh explicit owner
authorization for deployment, verification, credentials, backup, reset, seed,
cleanup, and final verification.

## Objective and exact final state

Perform one bounded reset followed by a fresh fixture seed. The final active
schedules are exactly:

| Target | Asia/Jakarta | UTC | quota | status |
|---|---|---|---:|---|
| A | 2026-08-20 00:00:00 → 2026-08-27 00:00:00 | 2026-08-19 17:00:00 → 2026-08-26 17:00:00 | 37 | open |
| B | 2026-08-27 00:00:00 → 2026-08-28 00:00:00 | 2026-08-26 17:00:00 → 2026-08-27 17:00:00 | 37 | open |
| C | 2026-08-28 00:00:00 → 2026-08-29 00:00:00 | 2026-08-27 17:00:00 → 2026-08-28 17:00:00 | 37 | open |

Each target has exactly 37 confirmed bookings. Final verification requires:

```text
schedule_count=3
target_total_bookings=111
target_distinct_members=37
target_member_sets_equal=true
target_charge_entries=111
```

## Preserve

The reset MUST preserve the exact 37 Prestige User accounts and User-to-Member
linkage; all 37 Member rows, password hashes, identity/profile/verification
data not created by old execution, and the fixed member namespace; the five
Prestige Operator identities and site assignments/permissions; Prestige site
identity; `SYN-CHEST-A`; point-exchange-rate/catalogue infrastructure;
Member-level non-booking setup/credit ledger entries; and all unrelated MHCS
data. Do not recreate/reset Member passwords or delete Member/User/Operator
records.

## Authorized deletion scope

Delete only records transitively owned by these exact diagnosed old Prestige
schedules and bookings:

- 2026-08-14 01:00:00–10:00:00 UTC;
- 2026-08-26 01:00:00–10:00:00 UTC; and
- 2026-08-27 01:00:00–10:00:00 UTC.

The old 2026-08-28 01:00:00 UTC schedule was absent in diagnostic `32375201758`
and MUST remain absent at the reset precondition.

Before implementation, re-audit the complete schema dependency DAG and derive a
true child-before-parent order. At minimum, preserve these relationships:

- `image_gateway_studies` → `image_gateway_capture_objects` →
  `image_gateway_capture_sets` → `operator_queue_admissions`;
- `operator_vital_signs_executions` → `member_vital_signs_assessments` →
  bookings/schedules;
- `operator_vital_signs_executions` → `operator_queue_admissions`;
- `operator_identity_verification_events` →
  `operator_identity_verifications` → `operator_arrivals`;
- `operator_queue_admission_history` → `operator_queue_admissions` →
  `operator_paper_tickets`;
- booking-owned consent/questionnaire/etc. → bookings; and
- `operator_shift_assignments` → `operator_eligible_shifts`.

In particular, delete `operator_queue_admissions` before
`operator_paper_tickets`. Then delete every other discovered descendant,
booking-linked charge row, old operator shift assignment, eligible shift, the
37 bookings, and finally the three schedules.

Inspect all current migrations/schema references to `booking_id`,
`shift_schedule_id`, `member_schedule_id`, and `operator_eligible_shift_id` and
establish a complete deepest-first dependency order before acceptance. Do not
rely on cascades, disable foreign keys, truncate tables, delete by Member
alone, or delete by date without exact Prestige site/offering scope.

## Exact pre-reset production gate

Before deletion, revalidate diagnostic `32375201758`: 37 Prestige Users, 37
linked active/login-enabled Members with exact one-to-one linkage; 14-Aug has
13 bookings (4 `checked_in`, 9 `confirmed`), 13 distinct Members, 13 ledger
entries, 13 charges, 0 reversals, and downstream counts
`local_imaging_orders=0`, `operator_paper_tickets=4`,
`operator_queue_admissions=8`, `operator_arrivals=4`,
`operator_identity_verifications=4`, `member_paper_questionnaires=4`,
`member_vital_signs_assessments=4`, `image_gateway_capture_sets=0`; 26-Aug
and 27-Aug each have 12 confirmed bookings, 12 distinct Members, 12
booking-linked charges, 0 reversals, and zero progressed clinical records; old
28-Aug is absent; and old 14/26/27 total 37 bookings and 37 distinct Members
equal to the exact fixed 37-member Prestige cohort.

Any material mismatch MUST fail closed before deletion.

Empty Prestige schedules MAY be accepted for normal local/testing fixture
creation only. With authorized production-seeding semantics, valid states are
only the exact diagnosed pre-reset state or the exact clean post-reset
three-target state. Empty, partial, mixed, or ambiguous production state MUST
fail closed.

## Mandatory backup and atomic reset

Immediately before the destructive transaction, execute a NEW verified
production DB backup using `/etc/madeena-mhcs_core-db-backup.sh`. The order is:
all read/credential/runtime validation → fresh verified backup → destructive
reset transaction → fresh seed → invariant verification. Backup failure means
no deletion and no seed; do not rely only on a previous backup.

The destructive reset transaction MUST begin before the exact old Prestige
roots are locked and validated. It MUST re-read the full exact v2.1 pre-reset
shape inside the transaction, including the three schedules' exact starts,
ends, quotas and statuses; old 28 absence; 13/12/12 bookings and exact status
distributions; exact 37 legacy bookings and distinct Members equal to the fixed
cohort; ledger/charge totals and zero reversals; eight progressed counts;
eligible-shift counts; assignment counts/statuses; and every dependency
invariant required for safe deletion. Resolve IDs only after this validation,
then delete descendants, assert the complete post-delete state, and commit.
No detailed check may rely solely on the pre-transaction classifier; any change
between classification and transactional validation MUST fail closed.

Before commit, assert using captured old IDs that every discovered execution
category is gone: tickets, queue admissions/history, arrivals, identity
verification/events, questionnaires, vitals/executions, consents, local
imaging, booking status events, image-gateway sets/objects/studies, charges,
bookings, eligible shifts, assignments, and schedules. Reassert the preserved
37 Users, 37 linked Members, fixed cohort identities, and Prestige Operators.
Unexpected dependencies, row counts, ownership mismatches, FK failures, or
ambiguous relationships MUST roll back. Never disable FK checks or truncate.
Errors/logs contain no IDs or PII.

## Fresh seed and idempotency

After reset, create fresh Target A/B/C schedule identities using the normal
clean Prestige fixture, deterministic intended Operator selection, one eligible
shift and intended assignments per target. Create 37 confirmed bookings and
exactly one Charge per booking on each target: 111 fresh bookings and 111
fresh charges. All target Member sets equal the same fixed cohort. Preserve
Member-level non-booking setup/credit entries and leave no old booking-linked
charges.

A second normal seeder run recognizes the clean three-target fixture and
creates zero schedules, bookings, charges, or duplicate assignments. Any mixed
or partial state fails closed; reset must not run again after successful seed.

## Synthetic reset fixture and tests

Synthetic tests MUST contain the real legacy graph: fixed 37 Prestige Members;
14-Aug with 13 bookings (4 checked-in, 9 confirmed), 4 paper tickets, 8 queue
admissions, 4 arrivals, 4 identity verifications, 4 questionnaires, 4 vital
assessments, corresponding deeper history/execution rows, 13 charges, one
eligible shift and five assignments; 26-Aug and 27-Aug with 12 confirmed
bookings, 12 charges, zero progressed records, one eligible shift and five
assignments each; old 28-Aug absent. Include unrelated Member, booking,
schedule, Operator, and ledger rows.

Exercise the real reset success path and prove the full old dependency graph,
37 bookings, 37 booking-linked charges, schedules, and execution descendants
are removed; identities, password hashes, Operators, non-booking Prestige
credits, and unrelated rows survive; fresh A/B/C has 111 bookings and charges,
one charge per booking, equal fixed cohort sets, deterministic Operators, and
a second run creates nothing. Inject a mid-cleanup failure and prove complete
rollback, including rows deleted before failure. Add changed-precondition
failure tests. Reversing the admission/paper-ticket order MUST fail the
synthetic FK-constrained test.

## Phase A / Phase B and workflow/verifier tests

Phase A is application/seeder plus synthetic tests. Phase B is apply workflow,
canonical verifier, plus workflow tests. Phase B MUST hardcode the exact full
Phase-A SHA; production may deploy only the accepted Phase-A revision.

Workflow pre-reset gate MUST independently verify the sanitized full diagnostic
shape: exact starts/ends, quota 50, status open, old 28 absence, 13/12/12
bookings and statuses, ledger/charge/reversal totals, eight progressed counts,
eligible shifts, assignment counts/statuses, 37 legacy bookings, 37 distinct
Members, and equality to the fixed cohort. Tests MUST prove
`PRE_RESET_GATE < BACKUP < SEEDER`, and backup failure MUST make the seeder
unreachable.

The canonical clean verifier and apply post-seed verification MUST query all
Prestige `SYN-CHEST-A` schedule rows and require the entire set to be exactly
A/B/C, with each target `status=open`, 37 unique Members, equal target Member
sets equal to the fixed cohort, exactly one Charge per booking, 111 charges,
and zero reversals. Use canonical output names:
`quota_20_26=37`, `quota_27_28=37`, `quota_28_29=37`,
`confirmed_20_26=37`, `confirmed_27_28=37`, `confirmed_28_29=37`.
Old-start checks MUST scope both exact Prestige site and `SYN-CHEST-A` and
independently require `old_14_absent=true`, `old_26_absent=true`,
`old_27_absent=true`, and `old_28_absent=true`. Canonical verify-production
remains strictly read-only.

Workflow tests MUST fail if any of these checks, the exact Phase-A pin, backup
ordering, credential controls, cleanup/environment/concurrency protections,
Member verifier, or no-PII/ID output protections disappear.

## Two-phase remediation

After this task revision is independently accepted, corrected Phase A is
seeder plus seeder tests only and produces a new full `PHASE_A_SHA`. Corrected
Phase B is workflows plus deployment tests only, is a direct child of Phase A,
and pins `EXPECTED_REVISION` to that exact Phase-A SHA. No deployment or
production operation is authorized.

Do not add a generic migration API, schema migration, unrelated refactor,
deployment, workflow dispatch, secret provisioning, manual SQL, or production
operation. No automatic retry.

## Publication scope

This publication turn changes only:

```text
.agents/tasks/prestige-production-data-application.md
```

It MUST NOT implement, deploy, dispatch workflows, provision secrets, access
or mutate production, run backup/reset/seed, create `task.md`, or create a new
task. Require `git diff --check` and confirm no implementation or production
operation occurred.

**Terminal:** TASK REVISION REVIEW REQUIRED.
