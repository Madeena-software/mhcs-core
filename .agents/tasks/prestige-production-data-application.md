---
title: Prestige production rehearsal-data reset and fresh fixture seed
document_id: MHCS-TASK-PRESTIGE-PRODUCTION-DATA-APPLICATION-001
version: 2.1
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

**Implementation baseline:** `eabf45759cd7a6135ca592d93e6231d8154253e1`

Lineage note: `eabf45759cd7a6135ca592d93e6231d8154253e1` is an unrelated viewer
commit already present in main; it is part of the implementation baseline but
is OUT OF SCOPE for the Prestige reset.

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

Where present, delete booking-owned `point_ledger_entries`,
`booking_status_events`, local imaging orders, operator paper tickets, queue
admissions, arrivals, identity verifications, member paper questionnaires,
member vital-sign assessments, operator vital-sign executions, image-gateway
capture sets and descendants, examination consent, and every other dependency
whose ownership is unambiguously rooted in the exact old schedule/booking IDs.
Then delete old operator shift assignments, eligible shifts, the 37 bookings,
and finally the three schedules.

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

## Mandatory backup and atomic reset

Immediately before the destructive transaction, execute a NEW verified
production DB backup using `/etc/madeena-mhcs_core-db-backup.sh`. The order is:
all read/credential/runtime validation → fresh verified backup → destructive
reset transaction → fresh seed → invariant verification. Backup failure means
no deletion and no seed; do not rely only on a previous backup.

Within one controlled transaction where supported, revalidate and lock exact
old roots, resolve exact IDs, delete deepest descendants first, delete old
booking-linked charge rows, bookings, operator assignments, eligible shifts,
and schedules. Unexpected dependencies, row counts, ownership mismatches, FK
failures, or ambiguous relationships MUST roll back. Errors/logs contain no
PII. Verify all old schedules, bookings, booking-linked charges, and downstream
execution rows are absent while the 37 User/Member identities remain intact.

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

## Phase A / Phase B and tests

Phase A is application/seeder plus synthetic tests. Phase B is apply workflow,
canonical verifier, plus workflow tests. Phase B MUST hardcode the exact full
Phase-A SHA; production may deploy only the accepted Phase-A revision.

Tests MUST prove exact pre-reset acceptance; changed-shape fail-closed before
deletion; all old descendants/schedules/bookings/charges deleted; preservation
of 37 User/Member identities, password hashes, profile data, Operator
identities, Member non-booking setup, and unrelated data; no broad deletion;
FK-safe order; rollback after injected cleanup failure; fresh 37/37/37,
111/111 arithmetic and equal member sets; and second-run idempotency.

Workflow tests MUST prove exact Phase-A pin, fresh backup before reset, backup
failure preventing deletion, old-data fail-closed gate, private credential
controls, canonical clean-fixture verification, old starts absent, preserved
37-member verification, optional read-only diagnostic, and no PII/IDs in output.

Do not add a generic migration API, schema migration, unrelated refactor,
deployment, workflow dispatch, secret provisioning, manual SQL, or production
operation. No automatic retry.

## Publication scope

This publication turn changes only:

```text
.agents/tasks/prestige-production-data-application.md
```

It MUST NOT implement, deploy, dispatch, provision secrets, access or mutate
production, create `task.md`, or create a new task. Require `git diff --check`
and confirm no implementation or production operation occurred.

**Terminal:** TASK REVISION REVIEW REQUIRED.
