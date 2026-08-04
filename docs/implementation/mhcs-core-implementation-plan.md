# MHCS Core remediated implementation plan

This bounded future implementation plan performs no product implementation and does not establish full MHCS Core product conformance. Mocks, stubs, interfaces, prototypes, and planning artifacts do not satisfy production requirements.

## Baseline metadata

| Field | Value |
|---|---|
| Declared source context commit | `e9f5e9f76b09f0327f50c88e926813566efd60c0` |
| Source-commit correspondence | unverified — declared object unavailable locally; no direct comparison performed. |
| Draft baseline commit | `8bf34637bea1420b9968bb6d995f1703770e1b51` |
| Current target commit / branch | `423df7b0c1b95d41a28e084ea0d8c13bff818788` / main |
| Analysis date | 2026-08-04 |
| Initial working tree | Clean at task start; no staged, modified, or untracked paths. Draft outputs existed unchanged from the supplied baseline. |
| Repository evidence | `E0: No application source, Composer/frontend manifests, configuration, migrations, routes, queues, adapters, storage, or executable tests exist in the target repository; conformance documents and approved context are not implementation evidence.` |

### Mandatory specification digests

| Repository-relative specification path | SHA-256 |
|---|---|
| .agents/context/project.md | e79fb274984b349abf4b261c1c31d3edb77265ae1800afb512392e2a79ff8180 |
| .agents/context/modules/member/project.md | 03a12418e3823c6781855a3166a9a30b0ffef8205e879a7cb8f470bd4ac42280 |
| .agents/context/modules/operator/project.md | db828511b28b1aa7983ffa51852fcd8f38f8dd804deb4374ffb69054e5b708be |
| .agents/context/modules/doctor/project.md | bc6bdac5657e01756f8494a15dd4b424ba6d24d5b89cdc05ce51d7ec04512480 |
| .agents/context/modules/image-gateway/project.md | 779bd0ab8b467ee06c20dcd4f9154eaba3ef2ac74b4145db082452934faf3af3 |
| .agents/context/ui-language.md | e6ddcc96295b0990101a5b2b0b38c9886a37a550fe35fab6a2d11aee2f225124 |
| .agents/context/design/mhcs-core-design.html | 365e199b98eb00add13f15ef82221dba3fb778fe5610bbbebeac4e67e3b177a6 |

## Reconciled inventory
- Previous draft identifiers: 982.
- Remediated active requirements: 594; all applicable and not-started; verified: 0.
- Prefix counts: {'ARCH': 41, 'DES': 22, 'DOC': 66, 'IMG': 59, 'MEM': 225, 'OPR': 134, 'UIL': 47}.
- Source counts: {'.agents/context/project.md': 41, '.agents/context/design/mhcs-core-design.html': 22, '.agents/context/modules/doctor/project.md': 66, '.agents/context/modules/image-gateway/project.md': 59, '.agents/context/modules/member/project.md': 225, '.agents/context/modules/operator/project.md': 134, '.agents/context/ui-language.md': 47}.
- Authority counts: {'Architecture / Shared': 41, 'Design reference': 22, 'Doctor': 66, 'Image Gateway': 59, 'Member': 225, 'Operator': 134, 'UI language': 47}.
- Applicability: applicable 594, not-applicable 0, ambiguous 0.
- Classification: not-started 594, all other classifications 0.
- ID lifecycle dispositions: {'rewritten': 549, 'retired-non-normative': 10, 'moved-to-register': 7, 'merged-into': 416}; work-package totals previous/remediated: 25 / 25.

## Ordered critical path

1. WP-01 — architecture, technology baseline, shared primitives, transactions, events, and ownership.
2. WP-02 — security, privacy, audit, untrusted-input bounds, deployment, MPIPS isolation, and operations.
3. WP-04/WP-05/WP-06/WP-07 — Member identity, money/booking invariants, site eligibility, attendance, consent, clinical records, and cash.
4. WP-11/WP-12/WP-14 — Operator authorization, staffing, public-safe queues, protocol snapshots, basic examination, and capture submission.
5. WP-08/WP-17/WP-22 — FHIR R5 packages, resource ownership, terminology, mapping validation, and module interoperability.
6. WP-23 — durable NPZ/DICOM storage, signed manifests, MPIPS conversion, retries, checksums, and completion.
7. WP-09/WP-15/WP-18/WP-19/WP-24 — repeat entitlement, AI routing, Doctor queue/quality, replacement lineage, and publication.
8. WP-16/WP-21 — Operator/Doctor earning, payout, cash, provider confirmation, and reconciliation.
9. WP-10/WP-20/WP-26/WP-27 — administration, reports, language conformance, visual implementation, and rendered review.
10. WP-28 — final audit; it cannot pass while any applicable requirement remains unverified.

## Conflict register
| ID | Conflict | Authority/evidence | Affected scope | Disposition |
|---|---|---|---|---|
| CON-001 | Design prototype contains English/X-ray/sample strings while UI policy governs MHCS-authored member/public copy. | UI-language authority and design-reference boundary. | DES-*; UIL-*; WP-26/WP-27 | Apply UI policy to public/member copy; preserve professional/regulated/third-party text and record exceptions. |
| CON-002 | MPIPS transport/auth/idempotency is owned by separate repository while local architecture requires private worker boundary. | Architecture MPIPS contract/security sections. | ARCH-041..044; IMG-057; WP-02/WP-23 | Preserve both boundaries; DEC-002/DEP-001 remain open. |

## Decision register
| ID | Unresolved decision | Affected scope | Required authority | Interim treatment |
|---|---|---|---|---|
| DEC-001 | B2B import format, agreement data shape, credential handoff. | MEM-214/MEM-228; WP-10 | Product, financial, privacy/legal | Do not invent format. |
| DEC-002 | MPIPS exact transport, auth, idempotency, retry timing, error mapping. | ARCH-042..044/IMG-057; WP-02/WP-23 | Architecture/security and MPIPS authority | Dependency only; no external call. |
| DEC-003 | FHIR guide canonical URL, package/version, profiles, terminology, fixtures. | FHIR rows; WP-08/WP-17/WP-22/WP-24 | Clinical/interoperability | No profile-conformance claim until artifacts exist. |
| DEC-006 | Privacy notice, lawful basis, and retention/deletion procedure for mandatory identity assets. | MEM-212; WP-04/WP-07 | Privacy/legal | Do not invent a policy; retain the question as an approval gate. |
| DEC-004 | Report structure/signatures, notifications, payment/bank verification, payout schedule. | Report/financial rows; WP-16/WP-20/WP-21/WP-22 | Clinical, financial, legal/privacy, security | Implement after approval. |
| DEC-005 | Measured Grabber/NPZ safety limits and adapter limits. | ARCH-032..035/OPR-126; WP-02/WP-14/WP-23 | Security/operations/device owners | Prototype files are not proof. |

## External-dependency register
| ID | Dependency | Affected scope | Required evidence | Status |
|---|---|---|---|---|
| DEP-001 | Private MPIPS contract and isolated worker network. | ARCH-041..044/IMG-057..058 | Versioned contract, auth, idempotency/conflict, bounded resources, observed tests. | Unavailable locally; unverified. |
| DEP-002 | Payment gateway, bank/payout provider, cash contracts. | Financial rows; WP-05/WP-07/WP-16/WP-21 | Signed confirmations, replay/idempotency, reconciliation, fees, destination rules. | Not connected. |
| DEP-003 | Object storage, deployment-template authority, Grabber/device inputs. | ARCH-037/045; OPR-126/132; IMG-056/057 | Versioned configuration, storage policy, measured schema/limits, review. | Not present. |
| DEP-004 | MHCS FHIR R5 guide and terminology/profile package. | FHIR rows across modules | Package/version/canonical IDs, profiles, CapabilityStatements, terminology, fixtures. | Unresolved. |
| DEP-005 | Notification, AI, external communication providers. | Notification/AI/payout rows | Authenticated adapters, privacy-safe templates, fallback/idempotency evidence. | Not connected. |

## Approval register
| ID | Approval boundary | Affected scope | Required evidence |
|---|---|---|---|
| APP-001 | Architecture, ownership, shared boundary, security, deployment. | ARCH-*; WP-01/WP-02/WP-17 | Approved boundary decision and review record. |
| APP-002 | Clinical and interoperability review. | Consent, clinical metadata, FHIR, report, AI, repeat, safety. | Clinical review, profiles, safety-language review. |
| APP-003 | Product and financial review. | Points, booking, cancellation, earnings, payout, cash, admin. | Approved rates, funding, payout, refund, reconciliation decisions. |
| APP-004 | Privacy, legal, regulated text, identity. | Identity, guardian, consent, object access, notifications, LCD, language. | Retention/privacy review and disclosure decisions. |

## Risk register
| ID | Risk | Affected scope | Mitigation/evidence |
|---|---|---|---|
| RISK-001 | No product implementation evidence; all active rows are not-started. | All rows; WP-28 | Final audit fails until every applicable row is verified. |
| RISK-002 | Declared source commit cannot be directly compared locally. | All source rows; WP-28 | Preserve digests/unverified status; compare later. |
| RISK-003 | Prototype content could leak into production copy/behavior. | DES-*/UIL-*; WP-26/WP-27 | Keep samples/scripts as evidence; apply policy-driven review. |
| RISK-004 | External contracts and material clinical/financial/privacy decisions remain open. | Register-referenced rows | Do not silently resolve; require approval and contract evidence. |

## Bounded work packages

Each active applicable requirement has exactly one primary package below. Ranges are inclusive only for contiguous IDs; WP-28 is verification-only.
### WP-01 — Application architecture foundation
Objective / bounded scope: Establish the single modular runtime, shared transaction/event boundary, explicit local contracts, and technology baseline.
Requirement assignments: ARCH-001..ARCH-002, ARCH-008..ARCH-018, ARCH-037..ARCH-040, ARCH-046
Prerequisites: Nothing; approved architecture baseline.
Excluded scope: No product feature behavior, external adapters, or UI implementation.
Expected repository changes: Shared foundation, module boundaries, database/queue/event interfaces.
Affected modules/interfaces: Architecture/shared contract definitions and process topology.
Risk level: High
Approval needs: Architecture/security review for any boundary change.
External dependencies: Local repository only; deployment-template reference remains external.
Verification methods: Contract tests, transaction/outbox/idempotency tests, process smoke tests.
Completion evidence: Architecture evidence, contracts, observed test output, and versioned configuration.
Suggested versioned task filename: `mhcs-core-wp-01-application-architecture-foundation-v1.md`

### WP-02 — Security, privacy, audit, and operational hardening
Objective / bounded scope: Implement authenticated context, privacy controls, audit records, bounded untrusted input, and deployment/SSH constraints.
Requirement assignments: ARCH-028..ARCH-036, ARCH-041, ARCH-043, ARCH-045, MEM-108..MEM-119
Prerequisites: WP-01; approved privacy/legal/security decisions where required.
Excluded scope: Business workflow implementation and real production access.
Expected repository changes: Auth/session, authorization context, audit, object access, NPZ isolation, logs, CI policy.
Affected modules/interfaces: Shared auth, all modules, Image Gateway worker, deployment policy.
Risk level: Critical
Approval needs: Security, privacy, legal, and clinical approval as applicable.
External dependencies: Object storage, MPIPS isolation, production deployment pipeline.
Verification methods: Threat-model review, authorization tests, log inspection, resource-bound tests.
Completion evidence: Audited access controls, sanitized logs, negative tests, and review approvals.
Suggested versioned task filename: `mhcs-core-wp-02-security-privacy-audit-operations-v1.md`

### WP-04 — Member identity, accounts, guardians, and recovery
Objective / bounded scope: Implement member/user separation, identity assets, login, guardian access, age transition, and assisted recovery.
Requirement assignments: MEM-014..MEM-019, MEM-084..MEM-085, MEM-213, MEM-219
Prerequisites: WP-01, WP-02; privacy/legal policy.
Excluded scope: B2B import format and production credential handoff.
Expected repository changes: users/members, verification assets, guardians, login/recovery flows.
Affected modules/interfaces: Member and shared authentication.
Risk level: Critical
Approval needs: Privacy/legal and identity-policy approval.
External dependencies: Private object storage, KTP/KIA policy, notification/credential handoff.
Verification methods: Registration, guardian authorization, login enumeration, recovery, retention tests.
Completion evidence: Account/identity schema, authorization tests, audit records, and retention evidence.
Suggested versioned task filename: `mhcs-core-wp-04-member-identity-accounts-guardians-recovery-v1.md`

### WP-05 — Member bookings, points, funding, cancellation, and revaluation
Objective / bounded scope: Implement B2B/B2C funding separation, immutable ledger, booking states, cutoffs, refunds, and rate changes.
Requirement assignments: MEM-020..MEM-037, MEM-220
Prerequisites: WP-01, WP-02, WP-04; rate/funding decisions.
Excluded scope: Real payment operations and signed B2B import.
Expected repository changes: Bookings, ledger, rate versions, revaluation, cancellation/refund commands.
Affected modules/interfaces: Member, Shared money/clock, Operator query boundary.
Risk level: Critical
Approval needs: Financial/product approval and revaluation review.
External dependencies: Payment gateway, B2B agreement data, clock/scheduler.
Verification methods: Concurrency, ledger invariants, round-half-up, cutoff, refund, and rollback tests.
Completion evidence: Immutable ledger evidence, reconciled balances, booking state history, and tests.
Suggested versioned task filename: `mhcs-core-wp-05-member-bookings-points-funding-cancellation-v1.md`

### WP-06 — Member site, schedule, booking data, and eligibility
Objective / bounded scope: Implement Member-owned schedules, site references, service snapshots, capacity, and booking/order lineage.
Requirement assignments: MEM-001..MEM-009, MEM-038..MEM-064, MEM-097..MEM-101, MEM-120..MEM-124, MEM-134..MEM-146, MEM-216..MEM-218
Prerequisites: WP-01, WP-05; site master contract.
Excluded scope: Operator physical-site implementation.
Expected repository changes: Schedules, offerings, quotas, bookings, ServiceRequest lineage.
Affected modules/interfaces: Member with Operator site query.
Risk level: High
Approval needs: Product/operations approval for eligibility and quota values.
External dependencies: Operator site master and scheduler.
Verification methods: Overlap, capacity race, lifecycle, and order-lineage tests.
Completion evidence: Schema constraints, state transitions, and concurrency evidence.
Suggested versioned task filename: `mhcs-core-wp-06-member-site-schedule-booking-data-eligibility-v1.md`

### WP-07 — Member clinical, consent, attendance, walk-in, and cash contracts
Objective / bounded scope: Implement assessment history, consent, attendance/exact-NIK, identity views, walk-in transaction, and cash close.
Requirement assignments: MEM-068..MEM-083, MEM-086..MEM-096, MEM-102..MEM-107, MEM-215, MEM-221..MEM-222
Prerequisites: WP-02, WP-04, WP-05, WP-06; clinical/privacy decisions.
Excluded scope: Operator UI styling and external systems.
Expected repository changes: Clinical history, consent, protected lookup/upload references, walk-in, cash reconciliation.
Affected modules/interfaces: Member and Operator local contracts.
Risk level: Critical
Approval needs: Clinical, privacy, and compliance approval.
External dependencies: Private storage, authenticated Operator session, cash ledger.
Verification methods: Contract, idempotency, privacy, history, consent, and reconciliation tests.
Completion evidence: Audited commands, immutable history, protected views, and observed tests.
Suggested versioned task filename: `mhcs-core-wp-07-member-clinical-consent-attendance-walk-in-v1.md`

### WP-08 — FHIR R5 and clinical interoperability foundation
Objective / bounded scope: Implement strict R5 mapping boundaries, identifiers, profiles, terminology, metadata, and validation gates.
Requirement assignments: MEM-125..MEM-133, MEM-223..MEM-227
Prerequisites: WP-01, WP-02; Implementation Guide decisions.
Excluded scope: Older FHIR adapters and unspecified profiles.
Expected repository changes: FHIR mappers, profile metadata, OperationOutcome, validator fixtures.
Affected modules/interfaces: Shared interoperability plus owning modules.
Risk level: Critical
Approval needs: Clinical/interoperability approval.
External dependencies: FHIR package, canonical URLs, terminology services/fixtures.
Verification methods: Positive/negative profile, version, terminology, linkage, and retry tests.
Completion evidence: Validated fixtures, mappings, CapabilityStatements, and approval record.
Suggested versioned task filename: `mhcs-core-wp-08-fhir-r5-and-clinical-interoperability-foundation-v1.md`

### WP-09 — Doctor repeat entitlement lifecycle
Objective / bounded scope: Implement idempotent Doctor-to-Member repeat command, zero-point entitlement, booking handoff, and events.
Requirement assignments: MEM-065..MEM-067
Prerequisites: WP-01, WP-05, WP-07, WP-08; Doctor quality decision.
Excluded scope: Clinical repeat decision itself and Doctor earnings calculation.
Expected repository changes: Entitlement, replacement ServiceRequest, decline/reminder events.
Affected modules/interfaces: Member/Doctor cross-module transaction.
Risk level: Critical
Approval needs: Clinical/product approval for controlled reasons.
External dependencies: Doctor case and Member booking state.
Verification methods: Conflict replay, lineage, atomic earning/event, scheduling, and decline tests.
Completion evidence: Stable IDs, event history, and cross-module transaction evidence.
Suggested versioned task filename: `mhcs-core-wp-09-doctor-repeat-entitlement-lifecycle-v1.md`

### WP-10 — Member administration, B2B import readiness, and acceptance harness
Objective / bounded scope: Implement authorized admin operations and prepare later signed import/acceptance execution.
Requirement assignments: MEM-010..MEM-013, MEM-147..MEM-209, MEM-214, MEM-228
Prerequisites: WP-02, WP-04, WP-05, WP-06, WP-07, WP-08, WP-09.
Excluded scope: Inventing an import format or resolving open decisions.
Expected repository changes: Admin actions, audit views, acceptance fixtures and task handoffs.
Affected modules/interfaces: Member admin and shared authorization.
Risk level: High
Approval needs: Product, financial, privacy, and FHIR approvals.
External dependencies: Signed agreement data and external package decisions.
Verification methods: Admin authorization/audit and acceptance-suite inspection.
Completion evidence: Admin audit trail, documented open decisions, and validated future-task inputs.
Suggested versioned task filename: `mhcs-core-wp-10-member-administration-b2b-import-readiness-v1.md`

### WP-11 — Operator authorization, sites, shifts, and staffing
Objective / bounded scope: Implement Operator permission/site context, Organization/Location, assignments, eligibility, and staffing workflow.
Requirement assignments: OPR-001..OPR-014, OPR-100..OPR-107, OPR-117..OPR-124, OPR-129
Prerequisites: WP-01, WP-02, WP-06.
Excluded scope: Member-owned bookings/quotas and payout execution.
Expected repository changes: Operator accounts, site master, assignments, shift events, candidate offers.
Affected modules/interfaces: Operator and shared auth.
Risk level: High
Approval needs: Operations/product approval.
External dependencies: SMS/Push/email provider and site staffing data.
Verification methods: Authorization, stale-event, assignment, timeout, escalation, and site-switch tests.
Completion evidence: Site/assignment audit, event versioning, and observed workflow tests.
Suggested versioned task filename: `mhcs-core-wp-11-operator-authorization-sites-shifts-staffing-v1.md`

### WP-12 — Operator attendance, basic examination, and queues
Objective / bounded scope: Implement arrival/consent/check-in, assessment station, FIFO staged queue, claims, walk-in boundary, and shift close.
Requirement assignments: OPR-015..OPR-030, OPR-130..OPR-131
Prerequisites: WP-02, WP-06, WP-07, WP-11.
Excluded scope: Image processing and doctor review.
Expected repository changes: Tickets, stages, claims, audit events, public-safe data boundary.
Affected modules/interfaces: Operator with Member contracts.
Risk level: Critical
Approval needs: Clinical/privacy/operations approval.
External dependencies: Member attendance, print dialog, clock, queue display consumer.
Verification methods: Atomic claim, FIFO, no-show, privacy, assessment, and shift-end tests.
Completion evidence: Queue history, audit, public-display payload tests, and observed concurrency.
Suggested versioned task filename: `mhcs-core-wp-12-operator-attendance-examination-queues-v1.md`

### WP-14 — Operator protocol, NPZ drafts, and complete capture submission
Objective / bounded scope: Implement versioned protocol, safe NPZ validation, non-persistent drafts, and Gateway submission.
Requirement assignments: OPR-031..OPR-046, OPR-132, OPR-137
Prerequisites: WP-02, WP-07, WP-08, WP-12; Grabber schema.
Excluded scope: Physical exposure/device behavior and MPIPS algorithm.
Expected repository changes: Protocol snapshots, draft lifecycle, capture manifest, submission ID.
Affected modules/interfaces: Operator and Image Gateway local contract.
Risk level: Critical
Approval needs: Clinical/device/security approval.
External dependencies: Grabber NPZ/gain schema, Image Gateway acceptance.
Verification methods: Schema/content/checksum, omission, idempotency, retry, cleanup tests.
Completion evidence: Immutable manifest, bounded validation, and durable-acceptance evidence.
Suggested versioned task filename: `mhcs-core-wp-14-operator-protocol-npz-capture-submission-v1.md`

### WP-15 — Operator AI status, corrections, repeats, and read-only images
Objective / bounded scope: Implement asynchronous status monitor, authorized viewer, queue corrections, and repeat handoff.
Requirement assignments: OPR-047..OPR-060
Prerequisites: WP-12, WP-14, WP-24.
Excluded scope: AI execution and doctor clinical decision.
Expected repository changes: Read-only viewer, readiness events, status/publication records.
Affected modules/interfaces: Operator with Image Gateway/Doctor events.
Risk level: High
Approval needs: Clinical/security/product approval.
External dependencies: AI provider, Image Gateway references.
Verification methods: Event idempotency, access scope, no-download, correction, repeat tests.
Completion evidence: Access audit, event history, and status evidence.
Suggested versioned task filename: `mhcs-core-wp-15-operator-ai-status-corrections-repeats-v1.md`

### WP-16 — Operator earnings, payouts, and cash reconciliation
Objective / bounded scope: Implement stage earnings, gateway payouts, bank destination verification, and cash close.
Requirement assignments: OPR-061..OPR-073, OPR-133, OPR-135
Prerequisites: WP-02, WP-07, WP-12.
Excluded scope: Member/Doctor financial records.
Expected repository changes: IDR earning records, payout state machine, reconciliation.
Affected modules/interfaces: Operator financial boundary.
Risk level: Critical
Approval needs: Financial/security approval.
External dependencies: Payment gateway, bank verification, signed confirmations.
Verification methods: Earning trigger, signature/replay, retry/reconciliation, and cash tests.
Completion evidence: Immutable earning/payout snapshots and provider confirmation evidence.
Suggested versioned task filename: `mhcs-core-wp-16-operator-earnings-payouts-cash-v1.md`

### WP-17 — Operator administration, contracts, and FHIR boundary
Objective / bounded scope: Implement audited admin operations, local module contracts, payment event controls, and Operator R5 mappings.
Requirement assignments: OPR-074..OPR-099, OPR-108..OPR-116, OPR-134, OPR-136, OPR-138
Prerequisites: WP-01, WP-02, WP-08, WP-11..WP-16.
Excluded scope: Member ownership and external production calls.
Expected repository changes: Admin authorization, contracts, Encounter/Organization mappings.
Affected modules/interfaces: Operator, shared interoperability.
Risk level: High
Approval needs: FHIR/security/operations approval.
External dependencies: FHIR IG, providers, deployment templates.
Verification methods: Contract, stale-version, OperationOutcome, profile, and security tests.
Completion evidence: Audited admin evidence and validated external-boundary fixtures.
Suggested versioned task filename: `mhcs-core-wp-17-operator-administration-contracts-fhir-v1.md`

### WP-18 — Doctor authorization, queue, study access, and claims
Objective / bounded scope: Implement doctor scope, priority queue, atomic claims/reassignment, and authorized study access.
Requirement assignments: DOC-001..DOC-005, DOC-060..DOC-068
Prerequisites: WP-01, WP-02, WP-08.
Excluded scope: Report writing and repeat clinical decision.
Expected repository changes: Doctor queue, claims, drafts/access references.
Affected modules/interfaces: Doctor and shared auth.
Risk level: Critical
Approval needs: Clinical/security approval.
External dependencies: DICOM access gateway and credential source.
Verification methods: Race, scope, reassignment, link expiry, and audit tests.
Completion evidence: Claim history, access logs, and observed conflict result.
Suggested versioned task filename: `mhcs-core-wp-18-doctor-authorization-queue-study-access-v1.md`

### WP-19 — Doctor quality decisions and repeat workflow
Objective / bounded scope: Implement immutable usable/repeat decisions, repeat_pending, controlled reasons, and replacement lineage.
Requirement assignments: DOC-006..DOC-019
Prerequisites: WP-08, WP-09, WP-18.
Excluded scope: Member scheduling implementation and Operator capture.
Expected repository changes: Quality decisions, repeat requests, linked clinical records.
Affected modules/interfaces: Doctor with Member/Image Gateway.
Risk level: Critical
Approval needs: Clinical approval.
External dependencies: Member entitlement and replacement-study event.
Verification methods: Decision immutability, reason, idempotency, sequential repeat, and decline tests.
Completion evidence: Clinical decision lineage and cross-module event evidence.
Suggested versioned task filename: `mhcs-core-wp-19-doctor-quality-decisions-and-repeat-workflow-v1.md`

### WP-20 — Doctor report lifecycle and member publication
Objective / bounded scope: Implement drafts, final reports, immutable versions, corrections/amendments, and notifications.
Requirement assignments: DOC-020..DOC-028
Prerequisites: WP-08, WP-19.
Excluded scope: AI report authoring and member-facing copy policy implementation.
Expected repository changes: Report versions, signatures, DiagnosticReport mapping, notifications.
Affected modules/interfaces: Doctor/Image Gateway/Member boundary.
Risk level: Critical
Approval needs: Clinical/legal/communications approval.
External dependencies: Notification provider and FHIR profiles.
Verification methods: Report state, signature, correction, publication, and privacy tests.
Completion evidence: Signed/versioned reports, notification audit, and validator output.
Suggested versioned task filename: `mhcs-core-wp-20-doctor-report-lifecycle-and-member-publication-v1.md`

### WP-21 — Doctor earnings and automated payouts
Objective / bounded scope: Implement doctor earning triggers, daily payout aggregation, destination verification, and reconciliation.
Requirement assignments: DOC-034..DOC-040, DOC-069
Prerequisites: WP-02, WP-19, WP-20.
Excluded scope: Operator earnings and gateway implementation details.
Expected repository changes: Doctor ledger, payout state/snapshot, daily scheduler.
Affected modules/interfaces: Doctor financial boundary.
Risk level: Critical
Approval needs: Financial/security approval.
External dependencies: Payment gateway and bank verification.
Verification methods: Trigger, daily aggregation, signed confirmation, retry, and reconciliation tests.
Completion evidence: Earning/payout evidence and no-duplicate transfer proof.
Suggested versioned task filename: `mhcs-core-wp-21-doctor-earnings-and-automated-payouts-v1.md`

### WP-22 — Doctor contracts, R5 reports, and security audit
Objective / bounded scope: Implement Doctor application operations, cross-module event contracts, DiagnosticReport, and clinical audit controls.
Requirement assignments: DOC-041..DOC-059, DOC-070..DOC-071
Prerequisites: WP-02, WP-08, WP-18..WP-21.
Excluded scope: Credential/signature decisions not yet approved.
Expected repository changes: Command/event contracts, profiles, audit, step-up authentication.
Affected modules/interfaces: Doctor and shared interoperability.
Risk level: Critical
Approval needs: Clinical/security/FHIR approval.
External dependencies: FHIR IG, credential/signature provider, DICOM gateway.
Verification methods: Contract, profile, authorization, log, and negative tests.
Completion evidence: Validated report fixtures and audit evidence.
Suggested versioned task filename: `mhcs-core-wp-22-doctor-contracts-r5-reports-security-v1.md`

### WP-23 — Image Gateway storage, manifests, MPIPS, and processing
Objective / bounded scope: Implement complete submission acceptance, object ownership, signed manifests, MPIPS orchestration, retries, and retention.
Requirement assignments: ARCH-019..ARCH-027, ARCH-042, ARCH-044, IMG-001..IMG-033, IMG-047..IMG-057
Prerequisites: WP-02, WP-07, WP-14, WP-16.
Excluded scope: MPIPS repository implementation and clinical AI behavior.
Expected repository changes: Storage metadata, object refs, conversion jobs, retry/failure state.
Affected modules/interfaces: Image Gateway worker.
Risk level: Critical
Approval needs: Security/privacy/clinical approval.
External dependencies: MPIPS, object storage, email provider, Grabber schema.
Verification methods: Checksum, idempotency, retry-three, retention, access, and failure tests.
Completion evidence: Durable objects, immutable manifests, and final-failure audit.
Suggested versioned task filename: `mhcs-core-wp-23-image-gateway-storage-manifests-mpips-v1.md`

### WP-24 — Image Gateway AI, publication, and replacement studies
Objective / bounded scope: Implement AI selection/fallback, publication, readiness events, doctor routing, and replacement-study event.
Requirement assignments: IMG-034..IMG-046, IMG-058..IMG-059
Prerequisites: WP-08, WP-09, WP-15, WP-19, WP-20, WP-23.
Excluded scope: AI provider model/clinical approval and Doctor decision.
Expected repository changes: AI result state, publication, readiness/replacement events.
Affected modules/interfaces: Image Gateway with Member/Operator/Doctor.
Risk level: Critical
Approval needs: Clinical/product/security approval.
External dependencies: AI provider, notification, module events.
Verification methods: Fallback, idempotency, lineage, publication, and no-AI-repeat tests.
Completion evidence: Event and publication evidence, never AI clinical approval by itself.
Suggested versioned task filename: `mhcs-core-wp-24-image-gateway-ai-publication-replacement-v1.md`

### WP-26 — Member-facing language and public-copy conformance
Objective / bounded scope: Implement approved Indonesian terminology, statuses, safety/consent, privacy-safe notifications, and copy review.
Requirement assignments: UIL-001, UIL-013, UIL-032, UIL-041, UIL-055, UIL-068, UIL-071, UIL-074, UIL-077, UIL-112, UIL-118, UIL-133, UIL-140, UIL-151, UIL-153, UIL-158, UIL-161, UIL-165, UIL-169, UIL-175, UIL-185, UIL-197, UIL-208, UIL-227, UIL-236, UIL-242, UIL-246, UIL-250, UIL-255, UIL-262, UIL-267, UIL-275, UIL-285, UIL-293, UIL-310, UIL-326, UIL-329, UIL-339, UIL-347, UIL-352, UIL-356, UIL-373, UIL-385, UIL-392, UIL-407, UIL-422, UIL-441
Prerequisites: WP-02, WP-07, WP-12, WP-20, WP-27.
Excluded scope: Professional-only/internal terms where allowed and formal text that must remain verbatim.
Expected repository changes: Translation/copy catalog, status mapping, review checklist.
Affected modules/interfaces: UI language / all visible surfaces.
Risk level: High
Approval needs: Clinical/legal/product/content approval.
External dependencies: Notification channels and approved formal consent/report text.
Verification methods: Copy search, rendered UI, accessibility, privacy, and state accuracy review.
Completion evidence: Approved copy review record and rendered evidence.
Suggested versioned task filename: `mhcs-core-wp-26-member-facing-language-and-public-copy-conforman-v1.md`

### WP-27 — Approved visual design implementation and visual verification
Objective / bounded scope: Implement the approved member/operator visual surfaces and interactions without treating prototype behavior as production proof.
Requirement assignments: DES-001..DES-022
Prerequisites: WP-02, WP-12, WP-14, WP-15, WP-26.
Excluded scope: New visual system, unsupported behavior, and prototype alerts/simulated data.
Expected repository changes: Blade/Filament/UI components, responsive states, viewer affordances.
Affected modules/interfaces: Member, Operator, shared UI.
Risk level: High
Approval needs: Design/accessibility/privacy/product approval.
External dependencies: Approved HTML reference and browser test environment.
Verification methods: Rendered desktop/narrow states, interaction, accessibility, and content/privacy review.
Completion evidence: Screenshots/observations, accessible states, and state-to-domain mapping.
Suggested versioned task filename: `mhcs-core-wp-27-approved-visual-design-implementation-and-visual-v1.md`

### WP-28 — Final MHCS Core conformance audit
Objective / bounded scope: Re-read the unchanged seven-file local specification baseline, validate every implementation task used by the program, reconcile every matrix/source/design identifier, inspect repository evidence, execute required verification, and review conflicts, approvals, blockers, and external dependencies.
Requirement assignments: None; package is verification-only.
Prerequisites: Every preceding work package and its validated task; approved decisions and external contracts; clean verification environment.
Excluded scope: No feature implementation, conflict resolution, production access, credentials, or task-file mutation.
Expected repository changes: None; this package is read-only verification and reporting.
Affected modules/interfaces: Entire mhcs-core repository and every declared external adapter boundary.
Risk level: Critical.
Approval needs: Explicit conformance sign-off from architecture, clinical, security, privacy, legal, financial, product, and interoperability owners where applicable.
External dependencies: All providers and MPIPS contracts must be available for their required verification, without production/staging access.
Verification methods: Re-read unchanged source baseline; validate every task; reconcile identifiers and exactly-one primary assignments; inspect source/design coverage; execute all required tests, static checks, security checks, integration fixtures, and rendered UI checks; review unresolved conflicts and blockers.
Completion evidence: It can succeed only when every applicable row is `verified`, no applicable row is not-started/in-progress/implemented-unverified/blocked, every not-applicable decision has approved rationale, all dependencies reconcile, and final Git scope is clean except intended conformance outputs.
Suggested versioned task filename: `mhcs-core-final-conformance-audit-v1.md`

## Final conformance-audit package

WP-28 must re-read the unchanged seven-file specification baseline, validate every implementation task, reconcile every previous and active identifier/lifecycle disposition, inspect direct repository evidence, execute each requirement-specific verification, review conflicts/decisions/dependencies/approvals/risks, confirm exactly one primary package per active applicable requirement, and fail while any applicable requirement is not verified. It may succeed only when every applicable requirement has direct evidence and observed verification.

## Verification and terminal boundary

- Mechanical checks must validate unique IDs, allowed statuses, complete matrix schema, valid source locators, lifecycle coverage for all 982 previous IDs, valid work-package references, exactly one primary package per active applicable row, and reconciled counts across all documents.
- Final Git verification must show only the three permitted Markdown outputs changed; no application, framework, context, task, dependency, test, CI, migration, infrastructure, cache, snapshot, or generated artifact change is allowed.
- This plan is not an implementation task and cannot claim full MHCS Core conformance.
