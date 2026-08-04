# MHCS Core implementation plan

This ordered plan is a future implementation program derived from the local conformance matrix. It is not an implementation task execution and does not establish full MHCS Core product conformance. Mocks, stubs, interfaces, prototypes, and planning artifacts do not satisfy production requirements.

## Baseline metadata

| Field | Value |
|---|---|
| Declared source context commit | `e9f5e9f76b09f0327f50c88e926813566efd60c0` |
| Source-commit correspondence | `unverified` — declared SHA unavailable locally; no external repository accessed |
| Target commit / branch | `e3c01162f892aacf2aa5d997d8ac5be3c25dfe8a` / `main` |
| Analysis date | `2026-08-04` |
| Local specification digests | Recorded consistently in the matrix and source-coverage index |
| Initial working tree | Clean; only permitted output directory was created by this task |

## Reconciled inventory

- Total requirements: **982**; all `applicable`; all `not-started`; `verified`: 0.
- Prefix counts: `ARCH` 36, `MEM` 212, `OPR` 128, `DOC` 66, `IMG` 55, `UIL` 463, `DES` 22.
- Primary assignment is one matrix work-package value per requirement row. The final audit package is verification scope, not a second primary assignment.

| Source | Count |
|---|---:|
| ARCH | 36 |
| MEM | 212 |
| OPR | 128 |
| DOC | 66 |
| IMG | 55 |
| UIL | 463 |
| DES | 22 |

| Owning module / authority | Count |
|---|---:|
| Architecture / Shared | 36 |
| Design reference | 22 |
| Doctor | 66 |
| Image Gateway | 55 |
| Member | 212 |
| Operator | 128 |
| UI language | 463 |

| Applicability | Count |
|---|---:|
| applicable | 982 |
| not-applicable | 0 |
| ambiguous | 0 |

| Implementation classification | Count |
|---|---:|
| not-started | 982 |
| in-progress | 0 |
| implemented-unverified | 0 |
| blocked | 0 |
| verified | 0 |

## Ordered critical path

1. WP-01 and WP-02: shared architecture, authentication context, security, privacy, audit, and operational boundaries.
2. WP-04, WP-05, WP-06, and WP-07: Member identity, money/booking invariants, site data, attendance, clinical records, consent, and walk-in contracts.
3. WP-11 and WP-12: Operator authorization, staffing, queues, basic examination, attendance, and public-safe operations.
4. WP-08: FHIR R5 profile/terminology foundation; it gates every clinical interoperability package.
5. WP-14 and WP-23: validated capture submission, durable storage, MPIPS conversion, retries, and retention.
6. WP-09, WP-15, WP-18, and WP-19: repeat entitlement, Operator/Doctor queues, quality decisions, and replacement lineage.
7. WP-16 and WP-21: Operator and Doctor earning/payout boundaries after their earning events exist.
8. WP-20, WP-22, and WP-24: reports, publication, AI routing, and cross-module events.
9. WP-10, WP-17, WP-26, and WP-27: administration, module contracts, language, visual surfaces, and final rendered review.
10. WP-28: final conformance audit; it cannot pass while any applicable row is not `verified`.

## Work packages

### WP-01 — Application architecture foundation

**Objective:** Establish the single modular runtime, shared transaction/event boundary, explicit local contracts, and technology baseline.
**Requirement assignments:** ARCH-001..ARCH-018
**Prerequisites:** Nothing; approved architecture baseline.
**Excluded scope:** No product feature behavior, external adapters, or UI implementation.
**Expected repository changes:** Shared foundation, module boundaries, database/queue/event interfaces.
**Affected modules/interfaces:** Architecture/shared contract definitions and process topology.
**Risk level:** High
**Approval needs:** Architecture/security review for any boundary change.
**External dependencies:** Local repository only; deployment-template reference remains external.
**Verification methods:** Contract tests, transaction/outbox/idempotency tests, process smoke tests.
**Completion evidence:** Architecture evidence, contracts, observed test output, and versioned configuration.
**Suggested task filename:** `mhcs-core-wp-01-application-architecture-foundation-v1.md`

### WP-02 — Security, privacy, audit, and operational hardening

**Objective:** Implement authenticated context, privacy controls, audit records, bounded untrusted input, and deployment/SSH constraints.
**Requirement assignments:** ARCH-028..ARCH-036, MEM-108..MEM-119
**Prerequisites:** WP-01; approved privacy/legal/security decisions where required.
**Excluded scope:** Business workflow implementation and real production access.
**Expected repository changes:** Auth/session, authorization context, audit, object access, NPZ isolation, logs, CI policy.
**Affected modules/interfaces:** Shared auth, all modules, Image Gateway worker, deployment policy.
**Risk level:** Critical
**Approval needs:** Security, privacy, legal, and clinical approval as applicable.
**External dependencies:** Object storage, MPIPS isolation, production deployment pipeline.
**Verification methods:** Threat-model review, authorization tests, log inspection, resource-bound tests.
**Completion evidence:** Audited access controls, sanitized logs, negative tests, and review approvals.
**Suggested task filename:** `mhcs-core-wp-02-security-privacy-audit-operations-v1.md`

### WP-04 — Member identity, accounts, guardians, and recovery

**Objective:** Implement member/user separation, identity assets, login, guardian access, age transition, and assisted recovery.
**Requirement assignments:** MEM-014..MEM-019, MEM-084..MEM-085
**Prerequisites:** WP-01, WP-02; privacy/legal policy.
**Excluded scope:** B2B import format and production credential handoff.
**Expected repository changes:** users/members, verification assets, guardians, login/recovery flows.
**Affected modules/interfaces:** Member and shared authentication.
**Risk level:** Critical
**Approval needs:** Privacy/legal and identity-policy approval.
**External dependencies:** Private object storage, KTP/KIA policy, notification/credential handoff.
**Verification methods:** Registration, guardian authorization, login enumeration, recovery, retention tests.
**Completion evidence:** Account/identity schema, authorization tests, audit records, and retention evidence.
**Suggested task filename:** `mhcs-core-wp-04-member-identity-accounts-guardians-recovery-v1.md`

### WP-05 — Member bookings, points, funding, cancellation, and revaluation

**Objective:** Implement B2B/B2C funding separation, immutable ledger, booking states, cutoffs, refunds, and rate changes.
**Requirement assignments:** MEM-020..MEM-037
**Prerequisites:** WP-01, WP-02, WP-04; rate/funding decisions.
**Excluded scope:** Real payment operations and signed B2B import.
**Expected repository changes:** Bookings, ledger, rate versions, revaluation, cancellation/refund commands.
**Affected modules/interfaces:** Member, Shared money/clock, Operator query boundary.
**Risk level:** Critical
**Approval needs:** Financial/product approval and revaluation review.
**External dependencies:** Payment gateway, B2B agreement data, clock/scheduler.
**Verification methods:** Concurrency, ledger invariants, round-half-up, cutoff, refund, and rollback tests.
**Completion evidence:** Immutable ledger evidence, reconciled balances, booking state history, and tests.
**Suggested task filename:** `mhcs-core-wp-05-member-bookings-points-funding-cancellation-v1.md`

### WP-06 — Member site, schedule, booking data, and eligibility

**Objective:** Implement Member-owned schedules, site references, service snapshots, capacity, and booking/order lineage.
**Requirement assignments:** MEM-001..MEM-009, MEM-038..MEM-064, MEM-097..MEM-101, MEM-120..MEM-124, MEM-134..MEM-146
**Prerequisites:** WP-01, WP-05; site master contract.
**Excluded scope:** Operator physical-site implementation.
**Expected repository changes:** Schedules, offerings, quotas, bookings, ServiceRequest lineage.
**Affected modules/interfaces:** Member with Operator site query.
**Risk level:** High
**Approval needs:** Product/operations approval for eligibility and quota values.
**External dependencies:** Operator site master and scheduler.
**Verification methods:** Overlap, capacity race, lifecycle, and order-lineage tests.
**Completion evidence:** Schema constraints, state transitions, and concurrency evidence.
**Suggested task filename:** `mhcs-core-wp-06-member-site-schedule-booking-data-eligibility-v1.md`

### WP-07 — Member clinical, consent, attendance, walk-in, and cash contracts

**Objective:** Implement assessment history, consent, attendance/exact-NIK, identity views, walk-in transaction, and cash close.
**Requirement assignments:** MEM-068..MEM-083, MEM-086..MEM-096, MEM-102..MEM-107
**Prerequisites:** WP-02, WP-04, WP-05, WP-06; clinical/privacy decisions.
**Excluded scope:** Operator UI styling and external systems.
**Expected repository changes:** Clinical history, consent, protected lookup/upload references, walk-in, cash reconciliation.
**Affected modules/interfaces:** Member and Operator local contracts.
**Risk level:** Critical
**Approval needs:** Clinical, privacy, and compliance approval.
**External dependencies:** Private storage, authenticated Operator session, cash ledger.
**Verification methods:** Contract, idempotency, privacy, history, consent, and reconciliation tests.
**Completion evidence:** Audited commands, immutable history, protected views, and observed tests.
**Suggested task filename:** `mhcs-core-wp-07-member-clinical-consent-attendance-walk-in-v1.md`

### WP-08 — FHIR R5 and clinical interoperability foundation

**Objective:** Implement strict R5 mapping boundaries, identifiers, profiles, terminology, metadata, and validation gates.
**Requirement assignments:** MEM-125..MEM-133
**Prerequisites:** WP-01, WP-02; Implementation Guide decisions.
**Excluded scope:** Older FHIR adapters and unspecified profiles.
**Expected repository changes:** FHIR mappers, profile metadata, OperationOutcome, validator fixtures.
**Affected modules/interfaces:** Shared interoperability plus owning modules.
**Risk level:** Critical
**Approval needs:** Clinical/interoperability approval.
**External dependencies:** FHIR package, canonical URLs, terminology services/fixtures.
**Verification methods:** Positive/negative profile, version, terminology, linkage, and retry tests.
**Completion evidence:** Validated fixtures, mappings, CapabilityStatements, and approval record.
**Suggested task filename:** `mhcs-core-wp-08-fhir-r5-and-clinical-interoperability-foundation-v1.md`

### WP-09 — Doctor repeat entitlement lifecycle

**Objective:** Implement idempotent Doctor-to-Member repeat command, zero-point entitlement, booking handoff, and events.
**Requirement assignments:** MEM-065..MEM-067
**Prerequisites:** WP-01, WP-05, WP-07, WP-08; Doctor quality decision.
**Excluded scope:** Clinical repeat decision itself and Doctor earnings calculation.
**Expected repository changes:** Entitlement, replacement ServiceRequest, decline/reminder events.
**Affected modules/interfaces:** Member/Doctor cross-module transaction.
**Risk level:** Critical
**Approval needs:** Clinical/product approval for controlled reasons.
**External dependencies:** Doctor case and Member booking state.
**Verification methods:** Conflict replay, lineage, atomic earning/event, scheduling, and decline tests.
**Completion evidence:** Stable IDs, event history, and cross-module transaction evidence.
**Suggested task filename:** `mhcs-core-wp-09-doctor-repeat-entitlement-lifecycle-v1.md`

### WP-10 — Member administration, B2B import readiness, and acceptance harness

**Objective:** Implement authorized admin operations and prepare later signed import/acceptance execution.
**Requirement assignments:** MEM-010..MEM-013, MEM-147..MEM-212
**Prerequisites:** WP-02, WP-04, WP-05, WP-06, WP-07, WP-08, WP-09.
**Excluded scope:** Inventing an import format or resolving open decisions.
**Expected repository changes:** Admin actions, audit views, acceptance fixtures and task handoffs.
**Affected modules/interfaces:** Member admin and shared authorization.
**Risk level:** High
**Approval needs:** Product, financial, privacy, and FHIR approvals.
**External dependencies:** Signed agreement data and external package decisions.
**Verification methods:** Admin authorization/audit and acceptance-suite inspection.
**Completion evidence:** Admin audit trail, documented open decisions, and validated future-task inputs.
**Suggested task filename:** `mhcs-core-wp-10-member-administration-b2b-import-readiness-v1.md`

### WP-11 — Operator authorization, sites, shifts, and staffing

**Objective:** Implement Operator permission/site context, Organization/Location, assignments, eligibility, and staffing workflow.
**Requirement assignments:** OPR-001..OPR-014, OPR-100..OPR-107, OPR-117..OPR-124
**Prerequisites:** WP-01, WP-02, WP-06.
**Excluded scope:** Member-owned bookings/quotas and payout execution.
**Expected repository changes:** Operator accounts, site master, assignments, shift events, candidate offers.
**Affected modules/interfaces:** Operator and shared auth.
**Risk level:** High
**Approval needs:** Operations/product approval.
**External dependencies:** SMS/Push/email provider and site staffing data.
**Verification methods:** Authorization, stale-event, assignment, timeout, escalation, and site-switch tests.
**Completion evidence:** Site/assignment audit, event versioning, and observed workflow tests.
**Suggested task filename:** `mhcs-core-wp-11-operator-authorization-sites-shifts-staffing-v1.md`

### WP-12 — Operator attendance, basic examination, and queues

**Objective:** Implement arrival/consent/check-in, assessment station, FIFO staged queue, claims, walk-in boundary, and shift close.
**Requirement assignments:** OPR-015..OPR-030
**Prerequisites:** WP-02, WP-06, WP-07, WP-11.
**Excluded scope:** Image processing and doctor review.
**Expected repository changes:** Tickets, stages, claims, audit events, public-safe data boundary.
**Affected modules/interfaces:** Operator with Member contracts.
**Risk level:** Critical
**Approval needs:** Clinical/privacy/operations approval.
**External dependencies:** Member attendance, print dialog, clock, queue display consumer.
**Verification methods:** Atomic claim, FIFO, no-show, privacy, assessment, and shift-end tests.
**Completion evidence:** Queue history, audit, public-display payload tests, and observed concurrency.
**Suggested task filename:** `mhcs-core-wp-12-operator-attendance-examination-queues-v1.md`

### WP-14 — Operator protocol, NPZ drafts, and complete capture submission

**Objective:** Implement versioned protocol, safe NPZ validation, non-persistent drafts, and Gateway submission.
**Requirement assignments:** OPR-031..OPR-046
**Prerequisites:** WP-02, WP-07, WP-08, WP-12; Grabber schema.
**Excluded scope:** Physical exposure/device behavior and MPIPS algorithm.
**Expected repository changes:** Protocol snapshots, draft lifecycle, capture manifest, submission ID.
**Affected modules/interfaces:** Operator and Image Gateway local contract.
**Risk level:** Critical
**Approval needs:** Clinical/device/security approval.
**External dependencies:** Grabber NPZ/gain schema, Image Gateway acceptance.
**Verification methods:** Schema/content/checksum, omission, idempotency, retry, cleanup tests.
**Completion evidence:** Immutable manifest, bounded validation, and durable-acceptance evidence.
**Suggested task filename:** `mhcs-core-wp-14-operator-protocol-npz-capture-submission-v1.md`

### WP-15 — Operator AI status, corrections, repeats, and read-only images

**Objective:** Implement asynchronous status monitor, authorized viewer, queue corrections, and repeat handoff.
**Requirement assignments:** OPR-047..OPR-060
**Prerequisites:** WP-12, WP-14, WP-24.
**Excluded scope:** AI execution and doctor clinical decision.
**Expected repository changes:** Read-only viewer, readiness events, status/publication records.
**Affected modules/interfaces:** Operator with Image Gateway/Doctor events.
**Risk level:** High
**Approval needs:** Clinical/security/product approval.
**External dependencies:** AI provider, Image Gateway references.
**Verification methods:** Event idempotency, access scope, no-download, correction, repeat tests.
**Completion evidence:** Access audit, event history, and status evidence.
**Suggested task filename:** `mhcs-core-wp-15-operator-ai-status-corrections-repeats-v1.md`

### WP-16 — Operator earnings, payouts, and cash reconciliation

**Objective:** Implement stage earnings, gateway payouts, bank destination verification, and cash close.
**Requirement assignments:** OPR-061..OPR-073
**Prerequisites:** WP-02, WP-07, WP-12.
**Excluded scope:** Member/Doctor financial records.
**Expected repository changes:** IDR earning records, payout state machine, reconciliation.
**Affected modules/interfaces:** Operator financial boundary.
**Risk level:** Critical
**Approval needs:** Financial/security approval.
**External dependencies:** Payment gateway, bank verification, signed confirmations.
**Verification methods:** Earning trigger, signature/replay, retry/reconciliation, and cash tests.
**Completion evidence:** Immutable earning/payout snapshots and provider confirmation evidence.
**Suggested task filename:** `mhcs-core-wp-16-operator-earnings-payouts-cash-v1.md`

### WP-17 — Operator administration, contracts, and FHIR boundary

**Objective:** Implement audited admin operations, local module contracts, payment event controls, and Operator R5 mappings.
**Requirement assignments:** OPR-074..OPR-099, OPR-108..OPR-116, OPR-125..OPR-128
**Prerequisites:** WP-01, WP-02, WP-08, WP-11..WP-16.
**Excluded scope:** Member ownership and external production calls.
**Expected repository changes:** Admin authorization, contracts, Encounter/Organization mappings.
**Affected modules/interfaces:** Operator, shared interoperability.
**Risk level:** High
**Approval needs:** FHIR/security/operations approval.
**External dependencies:** FHIR IG, providers, deployment templates.
**Verification methods:** Contract, stale-version, OperationOutcome, profile, and security tests.
**Completion evidence:** Audited admin evidence and validated external-boundary fixtures.
**Suggested task filename:** `mhcs-core-wp-17-operator-administration-contracts-fhir-v1.md`

### WP-18 — Doctor authorization, queue, study access, and claims

**Objective:** Implement doctor scope, priority queue, atomic claims/reassignment, and authorized study access.
**Requirement assignments:** DOC-001..DOC-005, DOC-060..DOC-066
**Prerequisites:** WP-01, WP-02, WP-08.
**Excluded scope:** Report writing and repeat clinical decision.
**Expected repository changes:** Doctor queue, claims, drafts/access references.
**Affected modules/interfaces:** Doctor and shared auth.
**Risk level:** Critical
**Approval needs:** Clinical/security approval.
**External dependencies:** DICOM access gateway and credential source.
**Verification methods:** Race, scope, reassignment, link expiry, and audit tests.
**Completion evidence:** Claim history, access logs, and observed conflict result.
**Suggested task filename:** `mhcs-core-wp-18-doctor-authorization-queue-study-access-v1.md`

### WP-19 — Doctor quality decisions and repeat workflow

**Objective:** Implement immutable usable/repeat decisions, repeat_pending, controlled reasons, and replacement lineage.
**Requirement assignments:** DOC-006..DOC-019
**Prerequisites:** WP-08, WP-09, WP-18.
**Excluded scope:** Member scheduling implementation and Operator capture.
**Expected repository changes:** Quality decisions, repeat requests, linked clinical records.
**Affected modules/interfaces:** Doctor with Member/Image Gateway.
**Risk level:** Critical
**Approval needs:** Clinical approval.
**External dependencies:** Member entitlement and replacement-study event.
**Verification methods:** Decision immutability, reason, idempotency, sequential repeat, and decline tests.
**Completion evidence:** Clinical decision lineage and cross-module event evidence.
**Suggested task filename:** `mhcs-core-wp-19-doctor-quality-decisions-and-repeat-workflow-v1.md`

### WP-20 — Doctor report lifecycle and member publication

**Objective:** Implement drafts, final reports, immutable versions, corrections/amendments, and notifications.
**Requirement assignments:** DOC-020..DOC-033
**Prerequisites:** WP-08, WP-19.
**Excluded scope:** AI report authoring and member-facing copy policy implementation.
**Expected repository changes:** Report versions, signatures, DiagnosticReport mapping, notifications.
**Affected modules/interfaces:** Doctor/Image Gateway/Member boundary.
**Risk level:** Critical
**Approval needs:** Clinical/legal/communications approval.
**External dependencies:** Notification provider and FHIR profiles.
**Verification methods:** Report state, signature, correction, publication, and privacy tests.
**Completion evidence:** Signed/versioned reports, notification audit, and validator output.
**Suggested task filename:** `mhcs-core-wp-20-doctor-report-lifecycle-and-member-publication-v1.md`

### WP-21 — Doctor earnings and automated payouts

**Objective:** Implement doctor earning triggers, daily payout aggregation, destination verification, and reconciliation.
**Requirement assignments:** DOC-034..DOC-040
**Prerequisites:** WP-02, WP-19, WP-20.
**Excluded scope:** Operator earnings and gateway implementation details.
**Expected repository changes:** Doctor ledger, payout state/snapshot, daily scheduler.
**Affected modules/interfaces:** Doctor financial boundary.
**Risk level:** Critical
**Approval needs:** Financial/security approval.
**External dependencies:** Payment gateway and bank verification.
**Verification methods:** Trigger, daily aggregation, signed confirmation, retry, and reconciliation tests.
**Completion evidence:** Earning/payout evidence and no-duplicate transfer proof.
**Suggested task filename:** `mhcs-core-wp-21-doctor-earnings-and-automated-payouts-v1.md`

### WP-22 — Doctor contracts, R5 reports, and security audit

**Objective:** Implement Doctor application operations, cross-module event contracts, DiagnosticReport, and clinical audit controls.
**Requirement assignments:** DOC-041..DOC-059
**Prerequisites:** WP-02, WP-08, WP-18..WP-21.
**Excluded scope:** Credential/signature decisions not yet approved.
**Expected repository changes:** Command/event contracts, profiles, audit, step-up authentication.
**Affected modules/interfaces:** Doctor and shared interoperability.
**Risk level:** Critical
**Approval needs:** Clinical/security/FHIR approval.
**External dependencies:** FHIR IG, credential/signature provider, DICOM gateway.
**Verification methods:** Contract, profile, authorization, log, and negative tests.
**Completion evidence:** Validated report fixtures and audit evidence.
**Suggested task filename:** `mhcs-core-wp-22-doctor-contracts-r5-reports-security-v1.md`

### WP-23 — Image Gateway storage, manifests, MPIPS, and processing

**Objective:** Implement complete submission acceptance, object ownership, signed manifests, MPIPS orchestration, retries, and retention.
**Requirement assignments:** ARCH-019..ARCH-027, IMG-001..IMG-033, IMG-047..IMG-055
**Prerequisites:** WP-02, WP-07, WP-14, WP-16.
**Excluded scope:** MPIPS repository implementation and clinical AI behavior.
**Expected repository changes:** Storage metadata, object refs, conversion jobs, retry/failure state.
**Affected modules/interfaces:** Image Gateway worker.
**Risk level:** Critical
**Approval needs:** Security/privacy/clinical approval.
**External dependencies:** MPIPS, object storage, email provider, Grabber schema.
**Verification methods:** Checksum, idempotency, retry-three, retention, access, and failure tests.
**Completion evidence:** Durable objects, immutable manifests, and final-failure audit.
**Suggested task filename:** `mhcs-core-wp-23-image-gateway-storage-manifests-mpips-v1.md`

### WP-24 — Image Gateway AI, publication, and replacement studies

**Objective:** Implement AI selection/fallback, publication, readiness events, doctor routing, and replacement-study event.
**Requirement assignments:** IMG-034..IMG-046
**Prerequisites:** WP-08, WP-09, WP-15, WP-19, WP-20, WP-23.
**Excluded scope:** AI provider model/clinical approval and Doctor decision.
**Expected repository changes:** AI result state, publication, readiness/replacement events.
**Affected modules/interfaces:** Image Gateway with Member/Operator/Doctor.
**Risk level:** Critical
**Approval needs:** Clinical/product/security approval.
**External dependencies:** AI provider, notification, module events.
**Verification methods:** Fallback, idempotency, lineage, publication, and no-AI-repeat tests.
**Completion evidence:** Event and publication evidence, never AI clinical approval by itself.
**Suggested task filename:** `mhcs-core-wp-24-image-gateway-ai-publication-replacement-v1.md`

### WP-26 — Member-facing language and public-copy conformance

**Objective:** Implement approved Indonesian terminology, statuses, safety/consent, privacy-safe notifications, and copy review.
**Requirement assignments:** UIL-001..UIL-463
**Prerequisites:** WP-02, WP-07, WP-12, WP-20, WP-27.
**Excluded scope:** Professional-only/internal terms where allowed and formal text that must remain verbatim.
**Expected repository changes:** Translation/copy catalog, status mapping, review checklist.
**Affected modules/interfaces:** UI language / all visible surfaces.
**Risk level:** High
**Approval needs:** Clinical/legal/product/content approval.
**External dependencies:** Notification channels and approved formal consent/report text.
**Verification methods:** Copy search, rendered UI, accessibility, privacy, and state accuracy review.
**Completion evidence:** Approved copy review record and rendered evidence.
**Suggested task filename:** `mhcs-core-wp-26-member-facing-language-and-public-copy-conforman-v1.md`

### WP-27 — Approved visual design implementation and visual verification

**Objective:** Implement the approved member/operator visual surfaces and interactions without treating prototype behavior as production proof.
**Requirement assignments:** DES-001..DES-022
**Prerequisites:** WP-02, WP-12, WP-14, WP-15, WP-26.
**Excluded scope:** New visual system, unsupported behavior, and prototype alerts/simulated data.
**Expected repository changes:** Blade/Filament/UI components, responsive states, viewer affordances.
**Affected modules/interfaces:** Member, Operator, shared UI.
**Risk level:** High
**Approval needs:** Design/accessibility/privacy/product approval.
**External dependencies:** Approved HTML reference and browser test environment.
**Verification methods:** Rendered desktop/narrow states, interaction, accessibility, and content/privacy review.
**Completion evidence:** Screenshots/observations, accessible states, and state-to-domain mapping.
**Suggested task filename:** `mhcs-core-wp-27-approved-visual-design-implementation-and-visual-v1.md`

### WP-28 — Final MHCS Core conformance audit

**Objective:** Re-read the unchanged seven-file local specification baseline, validate every implementation task used by the program, reconcile every matrix/source/design identifier, inspect repository evidence, execute required verification, and review conflicts, approvals, blockers, and external dependencies.
**Requirement assignments:** Audit scope is all matrix identifiers; it is not a second primary assignment.
**Prerequisites:** Every preceding work package and its validated task; approved decisions and external contracts; clean verification environment.
**Excluded scope:** No feature implementation, conflict resolution, production access, credentials, or task-file mutation.
**Expected repository changes:** None; this package is read-only verification and reporting.
**Affected modules/interfaces:** Entire mhcs-core repository and every declared external adapter boundary.
**Risk level:** Critical.
**Approval needs:** Explicit conformance sign-off from architecture, clinical, security, privacy, legal, financial, product, and interoperability owners where applicable.
**External dependencies:** All providers and MPIPS contracts must be available for their required verification, without production/staging access.
**Verification methods:** Re-read unchanged source baseline; validate every task; reconcile identifiers and exactly-one primary assignments; inspect source/design coverage; execute all required tests, static checks, security checks, integration fixtures, and rendered UI checks; review unresolved conflicts and blockers.
**Completion evidence:** It can succeed only when every applicable row is `verified`, no applicable row is not-started/in-progress/implemented-unverified/blocked, every not-applicable decision has approved rationale, all dependencies reconcile, and final Git scope is clean except intended conformance outputs.
**Suggested task filename:** `mhcs-core-final-conformance-audit-v1.md`

## Conflict and ambiguity register

| ID | Conflict/ambiguity | Affected requirements/packages | Decision required |
|---|---|---|---|
| C-01 | The design prototype uses member/public strings such as X-Ray, PASIEN, and Pemeriksaan Baru while the UI policy requires Sesi Foto Radiografi, Member/Anda, and public queue labels. | UIL, DES; WP-26/WP-27 | Apply UI-language authority to member/public copy; preserve only formal/vendor/professional exceptions after review. |
| C-02 | The design sample includes full-looking NIK and clinical values in operator/member surfaces while privacy requirements require masking and purpose-scoped access. | MEM, OPR, DES; WP-02/WP-07/WP-12/WP-27 | Confirm safe fixture policy and field-level visibility by surface. |
| C-03 | Prototype text says send directly to PACS, while architecture makes Image Gateway acceptance and private MPIPS the controlled boundary. | ARCH, OPR, IMG, DES; WP-14/WP-23/WP-27 | Treat prototype transport text as non-authoritative; approve production adapter contract. |
| C-04 | FHIR canonical/package/profile identifiers, report structure, consent wording, privacy basis, payment gateway, Grabber schema, and MPIPS transport are unresolved. | MEM, OPR, DOC, IMG, UIL; WP-08/WP-10/WP-14/WP-16/WP-20/WP-22/WP-23 | Obtain named owner approvals; do not silently select a value. |
| C-05 | Prose-only specification paragraphs not represented by list-item rows remain manual audit items in the exact source map. | All affected source headings; WP-28 | Normalize any missed atomic obligation before final conformance audit. |

## External-dependency register

| Dependency | Affected work | Required evidence / boundary |
|---|---|---|
| MPIPS | WP-14/WP-23/WP-24 | Private transport, auth, idempotency, checksum, failure, cleanup, and bounded recovery contract. |
| Grabber/device | WP-14 | Representative patient-free NPZ/gain files, safe schema, limits, and handoff behavior. |
| Object storage | WP-02/WP-07/WP-14/WP-23 | Private namespaces, encryption, retention, temporary links, deletion/anonymization controls. |
| AI provider/fallback | WP-15/WP-24 | Provider selection, result schema, failure/fallback, readiness, publication, and clinical governance. |
| Payment/bank gateway | WP-05/WP-16/WP-21 | Destination verification, signatures, idempotency, retry, fee, reconciliation, and sandbox contract. |
| Email/SMS/Push | WP-10/WP-11/WP-20/WP-24 | Notification templates, delivery status, privacy-safe content, and retry behavior. |
| FHIR Implementation Guide/terminology | WP-08/WP-17/WP-20/WP-22/WP-24 | Canonicals, package/version, profiles, ValueSets, CapabilityStatements, fixtures, and validators. |
| Legal/clinical/privacy/security/product owners | WP-02/WP-04/WP-05/WP-07/WP-19/WP-20/WP-26/WP-27 | Approved policies and decisions before collection, clinical publication, payments, or visible copy are enabled. |

## Approval register

- Architecture: module boundaries, shared transactions, service extraction, and deployment boundary.
- Security/privacy: identity assets, NIK/KK handling, NPZ isolation, logs, links, adapters, and public displays.
- Clinical: consent, basic examination, FHIR mappings, quality/repeat decisions, report/signature, AI/publication behavior.
- Legal/compliance: privacy notice, lawful basis, retention/deletion, regulated consent, report corrections, and external obligations.
- Financial/product: points, B2B funding, refunds, revaluation, payout fee policy, and open import data.
- Interoperability: FHIR R5 package, canonical/profile/terminology decisions and validators.
- Design/accessibility/content: responsive behavior, Indonesian terminology, claims, and fixture data.

## Risk register

| Risk | Consequence | Mitigation / residual risk |
|---|---|---|
| Empty product repository | No current behavior can be verified; every row remains not-started. | Implement packages incrementally; residual conformance risk remains maximal until WP-28. |
| Source SHA unavailable | Local context cannot be proven to match declared source revision. | Preserve local digests and unverified status; compare source revision in a controlled future audit. |
| Prose-only obligations | Atomic requirements may still need normalization before implementation. | Exact source map calls them out; WP-28 must resolve all before claiming conformance. |
| External contracts unresolved | Adapter behavior, retries, signatures, and limits cannot be tested truthfully. | Obtain sandbox/contract evidence; no production/staging access or credentials used here. |
| Prototype sample data/behavior | UI implementation could leak data or imply unsupported behavior. | Treat prototype as visual reference only; enforce policy and rendered privacy review. |

## Terminal boundary

A successful future WP-28 audit will mean only that this conformance-analysis baseline and all subsequent implementation evidence reconcile. It must not be described as full MHCS Core product conformance until every applicable requirement is verified.
