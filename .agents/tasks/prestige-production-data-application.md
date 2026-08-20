---
title: Prestige legacy-schedule production reconciliation
document_id: MHCS-TASK-PRESTIGE-PRODUCTION-DATA-APPLICATION-001
version: 1.9
status: validated-on-publication
language: en-US
last_updated: 2026-08-20
scope:
  - bounded final reconciliation of diagnosed Prestige rehearsal schedules
  - two-phase application and verifier implementation with immutable runtime binding
authority_note: This republication supersedes the prior diagnostic objective at this stable task path. It authorizes only the bounded implementation described here after publication review. It does not authorize production execution, deployment, workflow dispatch, secret provisioning, or production mutation.
---

# Executable Task

## Task identity

**Task title:** Prestige legacy-schedule production reconciliation

**Task path:** .agents/tasks/prestige-production-data-application.md

**Task contract state:** Validated/Published upon immutable publication of this exact content

**Delivery objective / Work Package / MVP:** Preserve progressed Prestige history and reconcile the two unprogressed diagnosed legacy schedules into three 37-member rehearsal targets, with fail-closed and idempotent behavior.

**Owner / designated planning authority:** Faliq Adlan, CTO

## Delivery context

The authoritative read-only diagnostic run 32375201758 observed three legacy schedules, 37 bookings covering the fixed 37-member Prestige cohort, and one progressed historical schedule. The reconciliation preserves progressed history, reuses the two unprogressed schedule identities, creates one target schedule, and preserves existing target-compatible bookings and charges.

## Baseline and task revision

**Implementation baseline:** 6d2dbec77ae25a4f6d6395eead5098c45c2d98db

**Remediation review basis:** Phase A `e7831b9cb2883182462fb7fabc23e097cb791107`; Phase B `6d2dbec77ae25a4f6d6395eead5098c45c2d98db`

**Terminal review verdict:** REMEDIATION REQUIRED

**Current production application runtime before reconciliation:** b5a2306e7d2d1491285edfd0418d25b1cdea568f

**Authoritative diagnostic:** run 32375201758

**Task revision:** The full SHA of the publication commit containing this exact content.

Resolve the task revision before execution and independently review it before implementation.

## Authoritative observed state

Production runtime was b5a2306e7d2d1491285edfd0418d25b1cdea568f; Member accounts were 37/37/37/37 with exact linkage.

- 14-Aug 01:00–10:00 UTC, quota 50, open: 13 bookings (checked_in=4, confirmed=9), 13 distinct Members, 13 ledger entries (13 charges, 0 reversals), progressed counts {local_imaging_orders:0, operator_paper_tickets:4, operator_queue_admissions:8, operator_arrivals:4, operator_identity_verifications:4, member_paper_questionnaires:4, member_vital_signs_assessments:4, image_gateway_capture_sets:0}, 1 eligible shift, 5 assignments.
- 26-Aug 01:00–10:00 UTC, quota 50, open: 12 confirmed bookings, 12 distinct Members, 12 charge ledger entries, 0 reversals, no progressed clinical records, 1 eligible shift, 5 assignments.
- 27-Aug 01:00–10:00 UTC: the same exact 12/confirmed/ledger-only shape.
- 28-Aug 01:00 UTC: absent.
- Global: legacy_schedule_count=3, legacy_booking_count=37, legacy_distinct_members=37, legacy_point_ledger_entries=37, legacy_progressed_schedule_count=1.

## Scope

### Phase A: application/seeder implementation

Modify only:

- database/seeders/PrestigeClinicSeeder.php
- tests/Feature/Operator/PrestigeClinicSeederTest.php
- an already-existing directly relevant regression test only if genuinely necessary.

Implement fail-closed state classification, historical preservation/closure, historical assignment retirement, 26-Aug to Target A in-place reconciliation, 27-Aug to Target B in-place reconciliation, Target C creation, completion to 37/37/37, booking/ledger preservation, and idempotent final-state rerun.

### Phase B: workflow/verifier implementation

Starting from the exact Phase-A commit, modify only:

- .github/workflows/apply-prestige-production-data.yml
- .github/workflows/verify-production.yml
- tests/Deployment/PrestigeProductionWorkflowTest.php
- tests/Deployment/ProductionVerificationWorkflowTest.php

Preserve the diagnostic input, secret and credential controls, mandatory backup-before-seed, cleanup, environment, concurrency, privacy, runtime gate, verify_prestige_members, and read-only canonical verification. Hardcode EXPECTED_REVISION="<PHASE_A_SHA>" with the exact full Phase-A SHA. Preserve confirmation APPLY-PRESTIGE-2026-08-20-28.

### Out of scope

Normal runtime schedule services, booking immutability, duplicate-active-booking behavior, generic migration APIs, schema migrations, unrelated refactors/tests, deployment, workflow dispatch, seeding, backup, secret provisioning, apply retry, manual SQL, production mutation, changing Operator identities, and deleting schedules/bookings/ledger/assignments/clinical records.

## Reconciliation contract

### Historical 14-Aug

Preserve the same row, boundaries 2026-08-14 01:00:00 to 10:00:00 UTC, quota 50, all 13 bookings and statuses, all 13 charge entries, all eight progressed-table counts, occurrence timestamps, and ledger identities/relationships. Change only status open to closed. Preserve five assignment rows and revoke them using established semantics: status=revoked, current mutation timestamp, and bounded non-PII reason. Final historical active assignments=0 and revoked assignments=5. Do not alter Operator identities.

### Target A from 26-Aug in place

Reuse the same schedule and eligible-shift identities. Transform 2026-08-26 01:00:00 to 10:00:00 UTC, quota 50 into 2026-08-19 17:00:00 to 2026-08-26 17:00:00 UTC, quota 37, status open. Preserve 12 existing confirmed bookings, their IDs/timestamps/snapshots, and 12 charge relationships. Create only 25 missing target bookings and charges.

### Target B from 27-Aug in place

Reuse the same schedule and eligible-shift identities. Transform 2026-08-27 01:00:00 to 10:00:00 UTC, quota 50 into 2026-08-26 17:00:00 to 2026-08-27 17:00:00 UTC, quota 37, status open. Preserve 12 existing confirmed bookings and their 12 charge relationships. Create only 25 missing target bookings and charges.

### Target C

Create 2026-08-27 17:00:00 to 2026-08-28 17:00:00 UTC, quota 37, status open, using existing fixture semantics for one eligible shift and five Operator assignments. Create 37 confirmed bookings and charges for the fixed cohort.

### Arithmetic and final model

Targets A/B/C finish at 37 confirmed each: target_total_bookings=111, target_distinct_members=37, target_member_sets_equal=true, target_charge_entries=111. Preserve 24 compatible target bookings/charges; create exactly 87 new target bookings and exactly 87 new target charges; create no reconciliation reversals; preserve 13 historical charges separately.

There are four preserved schedule rows total: one historical closed row and three active target rows. Verification must report target_schedule_count=3 and historical_schedule_preserved=true, not total rows=3. Old 26-Aug, 27-Aug, and 28-Aug starts must be absent after reconciliation.

## Fail-closed, transaction, and idempotency contract

Before any mutation, re-read state inside the seeder transaction or controlled reconciliation boundary. Require the exact observed 14/26/27 shapes above, absent old 28-Aug, global 37 bookings and 37 distinct fixed-cohort Members, and no pre-existing ambiguous Target A/B/C schedules.

Any mismatch must throw a sanitized non-PII exception, perform no legacy reconciliation, and stop the apply. A transaction must prevent partial 14/26/27 commit.

Recognize either the exact pre-reconciliation state or exact final reconciled state. Do not require old 26/27 starts on later runs. Any mixed or partial state fails closed.

## Required tests

Synthetic fixtures only. Prove:

- historical boundaries/quota, closure, booking/status/ledger identity preservation, all eight downstream counts, assignment preservation and revocation;
- 26/27 schedule and eligible-shift identity reuse, preservation of 12 bookings and ledger relationships, and correct target metadata;
- Target C creation; final 37/37/37, total 111, distinct 37, identical Member sets;
- exactly 87 new target bookings and charges, no reconciliation reversals, 24 compatible and 13 historical charges preserved;
- second-run idempotency, mixed/partial fail-closed, changed-precondition fail-closed, and unchanged normal runtime behavior.

Commit Phase A separately with message fix(prestige): reconcile legacy rehearsal schedules and record its exact full SHA as PHASE_A_SHA. Do not deploy.

## Required verifier results

The read-only canonical verifier must require:

    target_schedule_count=3
    target_bounds_match=true
    quota_20_26=37
    quota_27_28=37
    quota_28_29=37
    confirmed_20_26=37
    confirmed_27_28=37
    confirmed_28_29=37
    target_total_bookings=111
    target_distinct_members=37
    target_member_sets_equal=true
    target_charge_entries=111
    historical_schedule_preserved=true
    historical_status_closed=true
    historical_bookings=13
    historical_checked_in=4
    historical_confirmed=9
    historical_distinct_members=13
    historical_point_ledger_entries=13
    historical_charge_entries=13
    historical_reversal_entries=0
    historical_progressed_records_preserved=true
    historical_active_operator_assignments=0
    historical_revoked_operator_assignments=5
    legacy_26_old_absent=true
    legacy_27_old_absent=true
    legacy_28_old_absent=true

Require all eight historical downstream aggregate counts from diagnostic 32375201758 unchanged. Keep verify_prestige_members unchanged and diagnose_prestige_legacy available.

## Two-phase immutable-runtime rule

Phase A is the application/seeder commit. Phase B is the workflow/verifier commit hardcoding the exact Phase-A SHA. Production apply may run only after exact Phase A deployment and canonical runtime verification.

## Bounded remediation v1.9

The v1.8 delivery objective and approved reconciliation strategy remain
unchanged. This republication authorizes only the following bounded
remediation of the reviewed implementation. It does not authorize a generic
migration API, a production operation, or a material redesign.

### R1 — Scoped legacy preconditions

All legacy aggregates MUST be scoped to the exact diagnosed 14-Aug, 26-Aug,
and 27-Aug Prestige schedule rows. Do not use global application booking or
Member counts. Require `legacy_booking_count=37` and
`legacy_distinct_members=37` across those three rows only.

Derive the fixed cohort independently from the exact
`@prestige.madeena-xray.com` User namespace and exact User-to-Member linkage.
Require exactly 37 linked Prestige Members and require the booked-member set
across the three diagnosed rows to equal that cohort without emitting either
set. Unrelated application Members, bookings, schedules, or other data MUST
NOT affect classification or be modified.

Require exact total booking/status shapes: 14-Aug total 13 with 4
`checked_in` and 9 `confirmed`; 26-Aug total 12 with 12 `confirmed`; and
27-Aug total 12 with 12 `confirmed`. No other booking status is allowed.

### R2 — Atomic classification and reconciliation

For the production legacy state, classification, validation, row locking where
supported, and all 14/26/27 reconciliation mutations MUST execute inside one
controlled database transaction. Lock the fixed schedules and directly
relevant mutable eligible-shift and assignment rows where supported. There
MUST be no check-then-mutate gap. Any validation failure MUST commit none of
historical closure, assignment revocation, target rewrites, or Target-C
creation. Add rollback and fail-closed coverage.

### R3 — Production final state preserves history

An authorized production final-state rerun MUST contain all four rows:
historical 14-Aug closed plus Targets A, B, and C. A three-target-only state
after production history existed is invalid and MUST fail closed.

An empty local/testing database MAY retain its separate normal three-target
fixture and local idempotency behavior, but that fresh-fixture state MUST NOT
be accepted as a production reconciliation final state. Keep this distinction
tied to the existing authorized production-seeder context.

Final-state validation MUST independently revalidate the historical exact
boundaries, quota 50, closed status, 13 bookings (4 checked-in and 9
confirmed), 13 distinct Members, 13 total ledger entries, 13 charges, zero
reversals, all eight progressed counts, one eligible shift, five assignments,
zero active assignments, and five revoked assignments. It MUST also revalidate
exact A/B/C boundaries, quota 37, open status, 37 confirmed bookings each,
111 target bookings, 37 distinct target Members, equal 37-member sets, 111
target charges, and zero target reconciliation reversals.

### R4 — Exact Prestige Operator identities

Do not select Target-C Operators with `LIMIT 5` or arbitrary global profile
selection. Derive the exact five existing Prestige Operator profile
identities from the diagnosed existing schedule assignments. Require the
26-Aug and 27-Aug assignment profile sets to be the same exact five
identities; differing or ambiguous sets MUST fail closed. Create Target-C
assignments only for that validated set. Do not output or change Operator
identities. Add coverage proving unrelated Operator profiles are excluded.

### R5 — Complete canonical verifier

Both production workflows MUST verify all eight historical downstream counts:

    local_imaging_orders=0
    operator_paper_tickets=4
    operator_queue_admissions=8
    operator_arrivals=4
    operator_identity_verifications=4
    member_paper_questionnaires=4
    member_vital_signs_assessments=4
    image_gateway_capture_sets=0

They MUST compute independently `historical_point_ledger_entries=13`,
`historical_charge_entries=13`, and `historical_reversal_entries=0`, require
`historical_progressed_records_preserved=true`, and include it in pass/fail
logic. Target schedules MUST verify `status=open`.

Emit these exact canonical names and values: `quota_20_26=37`,
`quota_27_28=37`, `quota_28_29=37`, `confirmed_20_26=37`,
`confirmed_27_28=37`, and `confirmed_28_29=37`. Evaluate
`legacy_26_old_absent`, `legacy_27_old_absent`, and `legacy_28_old_absent`
independently, each scoped to the exact Prestige site and SYN-CHEST-A
offering. Preserve read-only behavior, `verify_prestige_members`,
`diagnose_prestige_legacy`, privacy, and the no-IDs/no-PII output boundary.

### R6 — End-to-end reconciliation tests

Phase-A tests MUST construct and execute the exact sanitized diagnosed shape:
14-Aug with 13 bookings (4 checked-in, 9 confirmed), 13 charges, all eight
progressed counts, one eligible shift, and five assignments; 26-Aug and
27-Aug each with 12 confirmed bookings, 12 charges, zero progressed records,
one eligible shift, and five assignments; no old 28-Aug row; and the fixed
37-member cohort.

They MUST prove historical row, booking, timestamp, status, ledger,
downstream, and assignment identity preservation; in-place 26→A and 27→B
schedule/eligible/12-booking/charge identity reuse; Target-C creation with
the exact five validated Operators; exclusion of unrelated Operators; final
37/37/37, 111 total, 37 distinct, equal sets; 24 preserved and 87 new target
bookings; 24 preserved and 87 new target charges; exactly one new charge per
new booking; no reconciliation reversals; separate historical charges;
production-final rerun idempotency; historical-missing, changed-precondition,
mixed/partial, and transaction-rollback rejection; unrelated application
Member/booking preservation; unchanged Member password hashes; unchanged
normal schedule immutability; and unchanged duplicate-active-booking
behavior.

Phase-B workflow tests MUST cover all eight historical counts,
`historical_progressed_records_preserved`, independent ledger/charge/reversal
totals, exact canonical field names, independent old-start checks, and the
exact full Phase-A pin.

After this remediation publication is independently accepted, remediation
Phase A MUST produce a new immutable SHA from this baseline and remediation
Phase B MUST be a direct child of corrected Phase A with
`EXPECTED_REVISION` hardcoded to that new full SHA. The workflows MUST NOT
remain pinned to `e7831b9cb2883182462fb7fabc23e097cb791107` after corrected
Phase A exists.

## Acceptance criteria

- [ ] Phase A implements the exact preservation, in-place reconciliation, target creation, arithmetic, fail-closed, transaction, and idempotency contract.
- [ ] Phase A tests cover required preservation, identity, count, arithmetic, failure, and unchanged-runtime invariants.
- [ ] Phase B binds apply to the exact full Phase-A SHA and preserves accepted controls.
- [ ] Canonical verification reports historical preservation and three active targets without identifiers or PII.
- [ ] verify_prestige_members and diagnose_prestige_legacy remain available.
- [ ] No generic runtime migration behavior is introduced.
- [ ] Focused tests, repository-required checks, and git diff --check pass.

## Dependencies, approvals, and stop conditions

Phase B depends on the exact Phase-A commit. Execution depends on remediation baseline 6d2dbec77ae25a4f6d6395eead5098c45c2d98db remaining applicable.

After independent acceptance, fresh explicit owner authorization is separately required for Phase-A deployment, exact runtime verification, Member verification, any fresh read-only precondition check, temporary Prestige secrets, APPLY-PRESTIGE-2026-08-20-28, mandatory verified backup, production reconciliation, secret deletion, and final canonical verification.

Stop and return to Planner/Reviewer for missing/contradictory authority, changed baseline, unexpected data shape, mixed/partial state, ambiguous targets, inability to preserve identity or clinical data, scope expansion, security/privacy/data-integrity risk, or any unapproved side effect. No automatic remediation or retry.

## Side-effect authorization and publication

This task authorizes implementation and local verification only after publication review. It does not authorize deployment, dispatch, production execution, backup, secret access/provisioning, seeding, apply retry, manual SQL, or production mutation.

For this publication turn only, the owner authorizes committing and pushing only this same task path as a normal fast-forward to main, from an isolated temporary worktree based on exact origin/main. No other file may be committed or pushed.

This publication changes only .agents/tasks/prestige-production-data-application.md. Do not create a new task or task.md. The primary worktree contains unrelated d6507b5... work and must remain untouched.

## Verification and terminal outcome

Run git diff --check and verify only the task file is changed. Return version, publication SHA, baseline, runtime, diagnostic evidence, reconciliation strategy, arithmetic, historical contract, Phase-A/Phase-B contract, changed file, validation, origin/main, primary-worktree preservation, and confirmation of no implementation/deployment/dispatch/secrets/production mutation.

Terminal: TASK REVISION REVIEW REQUIRED
