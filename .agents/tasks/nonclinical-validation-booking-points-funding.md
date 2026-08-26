---
title: MHCS Core Nonclinical Validation Booking Points Funding
document_id: MHCS-TASK-NONCLINICAL-VALIDATION-POINT-FUNDING-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-26
scope:
  - one bounded nonclinical validation booking funding primitive
  - exact real-npz-e2e-v1 Member only
  - preservation of normal booking and ledger semantics
authority_note: This task authorizes implementation and focused verification of a production-safe, purpose-bound synthetic validation funding primitive. It does not authorize production execution, provisioning, deployment, account creation, operator setup, NPZ handling, or Image Gateway behavior.
---

# Executable Task

## Task identity

**Task title:** Add bounded funding for one nonclinical validation booking

**Task path:** `.agents/tasks/nonclinical-validation-booking-points-funding.md`

**Task contract state:** Validated/Published upon immutable publication of this exact content.

**Delivery objective / Work Package / MVP:** Real NPZ production validation readiness — Gate B

**Owner / designated planning authority:** Faliq Adlan, CTO

## Delivery context

The accepted nonclinical production validation-context task is blocked at Gate B. `Mvp03BookingService::createForCurrentMember()` requires sufficient personal Madeena Points and records the normal personal charge. The only existing credit helper is local/testing-only or requires the forbidden broad `MHCS_ALLOW_PRODUCTION_MVP_SEED` override.

This task adds the smallest Member/Points-owned application capability to fund exactly one normal booking for the fixed nonclinical validation context `real-npz-e2e-v1`. The capability is synthetic system-validation funding, not payment, cash, promotion, refund, or customer settlement.

## Baseline and task revision

**Implementation baseline:** `f0c0a7876a796fe331bcb643a4648b3689fb8363`

**Related blocked governing task:** `.agents/tasks/nonclinical-production-validation-context-provisioning.md @ 50e8ff1f3ae1573a3d0d59ffa7aefdfb7286f6ac`

**Related accepted identity implementation:** `f0c0a7876a796fe331bcb643a4648b3689fb8363`

**Task revision:** The full immutable SHA of the publication commit containing this exact task content.

## Objective

Implement one internal/application-level Points capability that:

- resolves or verifies only the fixed nonclinical validation Member;
- derives the legitimate cost of one internally selected normal booking candidate;
- creates one deterministic validation Credit when required;
- is exactly replay-safe before or after the corresponding normal booking; and
- leaves the later provisioner responsible for calling the unchanged normal booking service.

The capability MUST stop before booking creation.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — delivery, evidence, and side-effect boundaries.
- `.agents/context/project.md` — module ownership, application boundaries, and security constraints.
- `.agents/context/modules/member/project.md` — Member and Points ownership.
- `.agents/tasks/nonclinical-production-validation-context-provisioning.md @ 50e8ff1f3ae1573a3d0d59ffa7aefdfb7286ac` — dependent provisioning objective and Gate B blocker.
- `.agents/tasks/nonclinical-validation-member-identity-semantics.md @ eaf46358f62c1449995066c4449f94165a720105` — accepted exact validation identity semantics.

### Observed implementation inputs

- `Mvp03PointService::personalBalance()` and `creditPersonalForLocalTesting()`.
- `Mvp03BookingService::createForCurrentMember()`.
- `MemberContextResolver` and `NonclinicalValidationContext`.
- Existing point ledger, audit, idempotency, and Member tests.

### Requirement traceability

- NVPF-001 → only the exact `real-npz-e2e-v1` nonclinical Member can receive the funding.
- NVPF-002 → funding is exactly one normal booking cost and preserves the normal booking charge.
- NVPF-003 → deterministic ledger idempotency and fail-closed inconsistent-state behavior.
- NVPF-004 → no public/general credit capability and no unrelated production or Image Gateway behavior.

## Scope

### In scope

- One Member/Points-owned application service or bounded extension to `Mvp03PointService`.
- Exact validation Member verification using `NonclinicalValidationContext::KEY` and the `mhcs.validation` marker.
- Deterministic validation-specific funding source reference.
- One existing `point_ledger_entries` Credit with `funding_source=personal` when that preserves current ledger semantics.
- Trusted system/purpose authorization consistent with the accepted nonclinical registration boundary.
- Focused behavioral and regression tests.

### Out of scope

- Any HTTP/API route or general Artisan credit command.
- Arbitrary Member IDs, amounts, or source references from callers or runtime input.
- `MHCS_ALLOW_PRODUCTION_MVP_SEED` and `creditPersonalForLocalTesting()` in the validation path.
- Direct balance updates, arbitrary ledger inserts from the future provisioner, payment/top-up/refund/promotion semantics, or another Member's balance.
- Booking creation, schedule provisioning, site/catalogue changes, account or Operator provisioning, roles, permissions, shifts, passwords, or secret handling.
- Migrations, schema changes, deployment, production access, startup/scheduled funding, retries, cleanup, reversal, NPZ, Image Gateway, PrivateObjectStore, S3, MPIPS, DICOM, or ProcessCaptureSet.

### Preserved behavior and invariants

- `Mvp03BookingService::createForCurrentMember()` remains unchanged in behavior and still checks the exact current personal balance, schedule, site/service, quota, and active exchange rate.
- Normal booking still creates the ordinary personal `Charge` ledger entry and audit/outbox behavior.
- Genuine verified Members retain ordinary Point and booking semantics.
- Pending, ordinary, wrong-marker, ambiguous, or inconsistent Members cannot use this capability.
- No validation identity becomes a genuine verified identity and no verification asset or PrivateObject is created.
- No new financial subsystem or shared miscellaneous service layer is introduced.

## Dependencies and assumptions

### Dependencies

- The fixed shared `App\Shared\Validation\NonclinicalValidationContext` remains authoritative for `real-npz-e2e-v1` and `mhcs.validation`.
- The future provisioner will semantically select a valid schedule/service candidate and will call the unchanged normal booking service after funding.
- One active point exchange rate and a valid service point price must already exist.

### Approved assumptions

- The existing point ledger can represent a synthetic validation Credit without a schema change.
- Funding is retained as audit evidence; the corresponding normal booking charge ordinarily leaves the isolated validation balance at zero.
- The deterministic validation Member has no legitimate prior personal balance. Any unexpected positive balance is inconsistent state, not a reason to top up.

### Remaining approval requirements

- Planner/Reviewer approval of the implementation and verification evidence.
- Separate explicit authorization before any production funding or provisioning execution.
- Any schema, migration, payment-like, financial configuration, account, Operator, or production-operation requirement returns to planning.

## Required design decisions

### Application boundary and authorization

Use the existing Member/Points application boundary. A dedicated service is permitted only if needed by current ownership and conventions.

The capability MUST require a trusted system context and a purpose equivalent to `member.nonclinical-validation.point-funding`. Ordinary Member, Operator, Administrator HTTP requests, request parameters, and public routes MUST NOT invoke it.

### Exact Member invariant

Before any ledger mutation, independently verify all of:

- `identity_status=nonclinical_validation`;
- `registration_source=nonclinical_validation`;
- exactly one `mhcs.validation=NonclinicalValidationContext::KEY` marker;
- `identity_document_type`, `encrypted_nik`, and `nik_lookup_digest` are NULL;
- no `member_verification_assets` exist; and
- the marker belongs to the same Member being funded.

Prefer server-side marker resolution. If the internal architecture requires a Member ID, it must be checked against the complete invariant and cannot be caller-selected through a public or generic interface.

### Funding amount and candidate interaction

Do not accept an arbitrary amount. Independently load the point cost from the internally resolved service/schedule candidate or another bounded domain object produced by the normal selection path.

Fail closed when there is no valid candidate, no active exchange rate, an invalid cost, or unexpected existing balance. Do not mutate point rates or catalogue data.

The intended isolated lifecycle is:

```text
validation Credit:  +X
normal booking:     -X
expected residual:   0
```

The funding capability does not create the booking and must not automatically credit again after the charge.

### Idempotency and inconsistent state

Use one stable validation-owned source reference, composed from the canonical context key, for example:

`nonclinical-validation:real-npz-e2e-v1:booking-funding-v1`

An exact replay before or after the corresponding booking must return the existing valid funding record without creating a second Credit. The funding ledger entry, not the current balance alone, is the idempotency authority.

Fail closed if multiple entries exist, ownership/source/type/amount differs, the Member has an unrelated positive balance, the ledger cannot prove isolated state, the booking charge does not match the funding contract, or another credit would be needed. Retain partial funding and require Planner review; do not silently reverse, delete, retry, or repair it.

### Ledger and audit semantics

Reuse `point_ledger_entries`; use `funding_source=personal` and `PointEntryType::Credit` only if that accurately preserves the existing ledger model. Do not invent payment, refund, promotion, or purchase identifiers.

Emit a distinct sanitized audit action equivalent to `member.point-funding.nonclinical-validation`, with only safe metadata such as `validation_context=NonclinicalValidationContext::KEY`, `nonclinical=true`, and `purpose=booking_validation`. Avoid raw Member, ledger, booking, credential, identity, or NPZ identifiers where unnecessary.

### Schema boundary

Expected result: `schema_migration_required=false`. If implementation proves a schema change necessary, stop and return to Planner; do not create a migration under this task.

## Acceptance criteria

- [ ] The exact nonclinical validation Member can receive one deterministic validation Credit through the new internal capability.
- [ ] Normal, verified, pending, wrong-marker, ambiguous, inconsistent, identity-bearing, and asset-bearing Members are rejected.
- [ ] The funded amount is independently derived from the legitimate selected booking/service cost; no arbitrary amount is accepted.
- [ ] Exactly one deterministic Credit is created, and exact replay before or after the corresponding booking creates no second Credit.
- [ ] Unexpected balance, conflicting source reference, differing amount, invalid rate, and mismatched booking state fail closed without repair.
- [ ] The normal booking path remains unchanged and creates its ordinary Charge; one matching Credit plus one Charge leaves zero isolated balance while retaining both records.
- [ ] Trusted-purpose authorization is required; no HTTP route, general command, arbitrary Member/amount/sourceReference interface, or production seed override exists.
- [ ] `MHCS_ALLOW_PRODUCTION_MVP_SEED` and `creditPersonalForLocalTesting()` are not used by the validation path.
- [ ] No account, Operator, schedule, catalogue, migration, Image Gateway, NPZ, MPIPS, DICOM, or PrivateObject behavior is introduced.

## Verification requirements

### Required checks

- Focused tests for all Member invariant, amount derivation, idempotency, inconsistency, authorization, retention, and zero-balance cases above.
- Existing Member/Points and normal booking tests.
- Static/source checks proving no public route, arbitrary production interface, seed-flag use, `creditPersonalForLocalTesting()` use, migration, account/Operator behavior, or Image Gateway/NPZ behavior was added.
- `git diff --check`.

### Required evidence

The Executor MUST report:

- implementation revision or exact working-tree state;
- commands and observed results for every check;
- schema migration result;
- proof that only the exact validation context is supported;
- proof that normal booking charge behavior is preserved; and
- any partial-state or unresolved failure condition.

## Stop conditions

Stop and return to Planner/Reviewer if:

- a schema or migration is required;
- the exact validation Member cannot be independently resolved and verified;
- a safe cost cannot be derived from the normal booking candidate;
- an active rate or valid service cost is unavailable;
- an existing balance or ledger state is inconsistent;
- payment-like semantics, another Member's balance, direct arbitrary ledger mutation, or the broad production seed override appears necessary;
- account/Operator, schedule/catalogue, production, deployment, secret, NPZ, Image Gateway, MPIPS, or other out-of-scope behavior is required; or
- the task's authority or acceptance boundary is materially insufficient.

## Terminal state

The implementation must stop before booking creation. A successful implementation provides only the bounded funding primitive and evidence; it does not authorize funding execution, context provisioning, deployment, or later real-NPZ validation.
