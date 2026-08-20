---
title: Prestige legacy-schedule production diagnostic
document_id: MHCS-TASK-PRESTIGE-PRODUCTION-DATA-APPLICATION-001
version: 1.7
status: validated-on-publication
language: en-US
last_updated: 2026-08-20
scope:
  - bounded read-only diagnostic for four obsolete Prestige schedules
  - sanitized aggregate evidence for final reconciliation planning
authority_note: This republication supersedes the prior implementation objective at this stable task path. It authorizes only the bounded verifier workflow and its existing test, does not authorize seeding, cleanup, migration, deployment, workflow dispatch, secret provisioning, or production mutation, and remains subject to independent task-revision review.
---

# Executable Task

## Task identity

**Task title:** `Prestige legacy-schedule production diagnostic`

**Task path:** `.agents/tasks/prestige-production-data-application.md`

**Task contract state:** `Validated/Published upon immutable publication of this exact content`

**Delivery objective / Work Package / MVP:** `Characterize obsolete Prestige schedule data so Planner/Reviewer can select one final reconciliation strategy from sanitized evidence.`

**Owner / designated planning authority:** `Faliq Adlan, CTO`

## Delivery context

The failed production apply run `32365225831` reached the accepted
`PrestigeClinicSeeder` only after the exact runtime gate, private CSV
validation, Operator credential validation, SUPER_ADMIN precheck, and
mandatory database backup succeeded. The seeder then failed closed because an
obsolete Prestige schedule has downstream records.

The current main is `b7953a930461ad180a6c8419345585bffb692bce` and the current
production application runtime is
`b5a2306e7d2d1491285edfd0418d25b1cdea568f`. The diagnostic MUST NOT guess
which records to delete or migrate. Existing records are evidence, not a
verifier failure.

## Baseline and task revision

**Implementation baseline:** `b7953a930461ad180a6c8419345585bffb692bce`

**Current production runtime:** `b5a2306e7d2d1491285edfd0418d25b1cdea568f`

**Failed apply evidence:** `32365225831`

**Task revision:** `The full SHA of the commit containing this exact validated task content, supplied after publication.`

The task revision is resolved by the publication commit and MUST be reviewed
before any implementation execution.

## Objective

Add one optional, manually selected, sanitized read-only diagnostic to the
existing production verifier. When selected, it characterizes only the four
known obsolete Prestige schedules and reports aggregate evidence sufficient to
distinguish empty, rehearsal, point-ledger-only, and progressed schedules.

## Authoritative inputs

- Failed apply evidence: run `32365225831`.
- Current main: `b7953a930461ad180a6c8419345585bffb692bce`.
- Current production runtime: `b5a2306e7d2d1491285edfd0418d25b1cdea568f`.
- Existing task contract at this stable path, republished as v1.6.
- Existing verifier and its current read-only guard in `.github/workflows/verify-production.yml`.

## Scope

### In scope

- `.github/workflows/verify-production.yml`
- `tests/Deployment/ProductionVerificationWorkflowTest.php`
- Optional `workflow_dispatch` boolean input:

```yaml
diagnose_prestige_legacy:
  type: boolean
  required: false
  default: false
```

- Sanitized diagnostic execution through the current production app
  container, using the existing verifier invocation and guard patterns.

### Out of scope

- `database/seeders/PrestigeClinicSeeder.php`
- `.github/workflows/apply-prestige-production-data.yml`
- `.github/workflows/server-setup-db.yml`
- `deploy-swarm.yml`
- schema migrations and runtime booking logic
- seeding, retrying the Prestige apply, deleting, migrating, or reconciling
  records
- workflow dispatch, deployment, secret provisioning, backup execution, or
  any production mutation
- arbitrary SQL input or user-selected schedule/table identifiers

### Preserved behavior

When `diagnose_prestige_legacy=false`, the verifier MUST emit
`PRESTIGE_LEGACY_DIAGNOSTIC=skipped` and existing behavior MUST remain
unchanged. Preserve workflow-dispatch-only execution, expected-revision and
revision-consistency checks, service/Swarm health, Laravel bootstrap, the
generic database read-only check, upload probe, `verify_prestige_members`,
`verify_prestige`, and the existing three-schedule verification.

## Diagnostic invocation

The enabled path MUST support exactly this canonical read-only invocation:

```text
expected_revision=b5a2306e7d2d1491285edfd0418d25b1cdea568f
run_large_upload_probe=false
verify_prestige=false
verify_prestige_members=true
diagnose_prestige_legacy=true
```

It MUST execute through the current production application container and
MUST emit `PRESTIGE_LEGACY_DIAGNOSTIC=pass` when the diagnostic safely
completes, including when data exists.

It MUST fail only when production/runtime access fails, the read-only
diagnostic cannot execute safely, or an unexpected structural condition makes
aggregate evidence unreliable.

## Legacy schedule scope

Inspect only Prestige site/offering schedules whose `starts_at` is one of:

| Label | `starts_at` |
|---|---|
| `legacy_2026_08_14` | `2026-08-14 01:00:00` |
| `legacy_2026_08_26` | `2026-08-26 01:00:00` |
| `legacy_2026_08_27` | `2026-08-27 01:00:00` |
| `legacy_2026_08_28` | `2026-08-28 01:00:00` |

Emit one sanitized aggregate row for each known start value, even when the
schedule does not exist. Do not inspect unrelated schedules or emit schedule
IDs.

## Per-schedule aggregate report

For each label, report safe schedule metadata:

- `exists`, `starts_at`, `ends_at`, `quota`, and `status`;
- total bookings;
- booking counts grouped by booking status;
- distinct Member count;
- whether all booked Members belong to the existing fixed Prestige Member
  namespace cohort;
- whether the booked Member set has exactly 37 unique Members.

Report aggregate counts only for bookings and these downstream records:

- `local_imaging_orders`
- `operator_paper_tickets`
- `operator_queue_admissions`
- `operator_arrivals`
- `operator_identity_verifications`
- `member_paper_questionnaires`
- `member_vital_signs_assessments`
- `image_gateway_capture_sets`
- `operator_eligible_shifts`
- `operator_shift_assignments`

For booking IDs belonging to each legacy schedule, report only aggregate
point-ledger evidence: `point_ledger_entries` count and, if safely available,
charge-entry count and reversal-entry count. Do not emit booking IDs, ledger
IDs, Member IDs, or `source_reference` values.

For every schedule derive:

```text
has_bookings=true|false
has_point_ledger=true|false
has_progressed_clinical_records=true|false
```

`has_progressed_clinical_records` is true when any of the eight clinical and
operational tables from `local_imaging_orders` through
`image_gateway_capture_sets` has a non-zero count. Eligible-shift and
assignment rows are reported separately and do not constitute clinical
progress.

## Global sanitized summary

Emit aggregate totals:

```text
legacy_schedule_count=<n>
legacy_booking_count=<n>
legacy_distinct_members=<n>
legacy_point_ledger_entries=<n>
legacy_progressed_schedule_count=<n>
```

## Read-only and privacy guarantees

The diagnostic MUST NOT INSERT, UPDATE, DELETE, seed, migrate, alter
services, execute a backup, provision secrets, write business data, or use a
write transaction. It MUST use fixed diagnostic scope and no arbitrary SQL
input.

Never emit PII or secrets, including NIK, names, addresses, birth dates,
emails or generated email local-parts, Member IDs, booking IDs, schedule IDs,
ledger IDs, passwords/hashes, credentials, or private CSV contents. Only the
fixed schedule dates, sanitized labels, aggregate counts, and status values
may be emitted.

## Verification and acceptance evidence

The implementation MUST add or update focused tests proving:

- the optional input defaults to false and emits `skipped`;
- the enabled invocation and expected revision are present;
- all four fixed schedule starts and aggregate fields are covered;
- no identifiers or PII are emitted;
- read-only and preserved-verifier contracts remain enforced; and
- `git diff --check` passes.

After implementation and independent review, the designated operator MAY run
the canonical verifier invocation above, collect only sanitized aggregate
evidence, and STOP. The diagnostic result is the decision gate for final
reconciliation design. No cleanup or migration is authorized yet.

## Remaining approval requirements and stop conditions

- Planner/Reviewer MUST review the exact publication revision before
  execution.
- Production execution requires separate operational authorization.
- No apply retry, cleanup, migration, seeding, secret provisioning,
  deployment, workflow dispatch, or production mutation is authorized by this
  task.
- If the diagnostic would require broader schedule scope, a new table,
  unsanitized output, a write, or a reconciliation decision, stop and return
  to Planner/Reviewer.

## Delivery boundary

Only this file may be committed or pushed for this publication:

`.agents/tasks/prestige-production-data-application.md`

Do not create a new task or `task.md`. Suggested publication message:

`docs(task): diagnose legacy Prestige schedule data`

## Publication return contract

Return the task version, publication SHA, baseline, production runtime,
diagnostic scope, aggregate fields, privacy/read-only guarantees, changed
file, confirmation that no new task/task.md exists, validation result,
confirmation of no workflow dispatch, no secrets, and no production mutation,
plus HEAD/origin-main and worktree state.

**Terminal:** `TASK REVISION REVIEW REQUIRED`
