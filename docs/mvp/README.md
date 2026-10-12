# MHCS Admin-first operational MVP

## Current authority and status

The approved planning priority is one persistent internal Admin login and a unified operational dashboard over domain-owned capabilities. This direction does not approve clinical, privacy, security, authentication, import, production-data, or release decisions.

The Admin-first documentation implementation `7132e7001b704789f6d122bcfad6a5b4e040bc0b` was independently accepted and merged through PR #5 as `8bb0c21e87e6f9e2c32a01ccab83aed37f1e91b7`. This accepts the documentation implementation only; it does not establish application acceptance. The current documentation cleanup is governed by [the validated task](../../.agents/tasks/lean-docs-and-business-authority.md) at publication revision `09b0f462fe94976394bb1357077e1a185895a3f2`; its implementation is merged through [PR #6](https://github.com/Madeena-software/mhcs-core/pull/6) at `fb25354311121fc8163256cd0530d9cbcb66bb68`, whose PR records the supplied independent acceptance. This resolves the older cleanup Review Required pointer without rewriting its historical contract.

The maintained business working reference is the [MHCS Business Working Reference](https://docs.google.com/document/d/1zxmPjB-gevtVuaoTEmAtLjpDWlJf7USnkeigVbPJw0E/edit), **Stakeholder Discussion Draft v0.2**. No exact source revision or verified immutable snapshot is available here. Its BP-*, D-*, BR-* and EX-* content remains unapproved and is not executable authority. The former `Madeena-software/mhcs-business-docs` sources at `645058e431f59c4450a136e72f140e6819b79f32` and their local copies are provenance only; equivalence to the Google Doc is not asserted.

The 2026-10-13 direct Product Owner handoff records [MVP-DEC-045–047](decision-log.md#admin-first-product-and-first-functional-slice--2026-10-13): central Admin, eventual canonical Patient, dual integration modes, individually authenticated clinical actors, separated AI/screening/finalization/publication states and secure examination-scoped result delivery. The approved first priority is the [Draft functional Admin Facility-Session Operations Board](../../.agents/tasks/admin-facility-session-operations.md), rather than PR #8's navigation-only candidate. The follow-up [PO approval](decision-log.md#product-owner-refinement-approval--2026-10-13) settles D1–D3 at product level and records the complete [Patient identity target](beta-scope.md#canonical-patient-identity-target). D4 technical/security, [X1 schedule-count mechanisms](../../.agents/tasks/admin-facility-session-operations.md#schedule-count-authorization-disposition--x1) and residual privacy/scope review remain blocking; the task stays Draft before independent T5. [MVP-DEC-052](decision-log.md#x1-selection-and-d4-review-request--2026-10-13) records Product Owner selection of A. The [D4 candidate](../../.agents/tasks/admin-facility-session-operations.md#d4-technical-assessment-and-candidate-contract) and [readiness matrix](../../.agents/tasks/admin-facility-session-operations.md#t5-readiness-matrix) prepare independent review, not technical/security/privacy approval; terminal verdict PLANNING REQUIRED. PR #8 is preserved and only recommended for supersession.

Verified integrated main is `fc37002d106b839c0b881e7dec7021c91389f501` (PR #7 merge). Bounded synthetic/local Admin Member registration is independently accepted at `21d279831455d32fe2a707e8d306af861c0bbb08`, under task revision `e7ccc686da049e78d7634125237fc794b30a0908`; [acceptance scope and limitations](evidence/admin-first-manual-member-registration-acceptance.md) persist. No broader acceptance or release is inferred.

## Active scope and dependencies

The [scope and evidence table](beta-scope.md#admin-first-operational-scope) retains the earlier seven outcomes: Admin-managed sites/shifts/Members/assignments; manual registration and planned import; a centralized worklist; processing/report status monitoring; secure time-limited delivery of finalized results; temporary staff/clinical workspaces; and attributable clinical identities and audit events. Existing code observations are distinguished from targets in that scope. The [gap register](beta-gap-register.md#admin-first-gaps-and-authority-reconciliation) records owners, controls, and resumption triggers; no gap is closed by this documentation work.

The next bounded objective is the functional facility-session board using product-approved D1–D3, after attributable technical/security D4, selected X1 A mechanism approval and residual disclosure/scope review. Patient migration and eventual portal retirement have separate contracts and do not authorize changes in this slice. Later dependencies are to reuse authenticated DICOM and legacy NPZ/MPIPS paths; approve import schema, identity matching, conflicts, transaction and privacy rules before import implementation; approve temporary clinical credentials and assignments; and settle report finalization and recipient/revocation rules before result delivery. Security, privacy, clinical, integration, operational, and release gates remain applicable. See the [roadmap](roadmap.md#admin-first-planning-sequence) for the dependency map and [Work Package status](work-package-status.md#admin-first-status-orientation) for evidence limits.

## Preserved boundaries

- Admin is a presentation surface. Member, Operator, Doctor, and Image Gateway retain domain ownership, authorization, mutations, and audit.
- Preserve server-derived role/site/shift/case checks, MRN/NIK separation, consent history, private binaries, validation, idempotency, and attributable audit.
- Processing completion, AI screening output, doctor-finalized reports, and authorized Member release are separate states. AI output is not a clinically verified report.
- MPIPS remains a separate private processing boundary. Preserve authenticated DICOM ingestion and legacy NPZ/MPIPS compatibility.
- No account removal or migration, generic import policy, new clinical access, production-data operation, deployment, or release is authorized here. Open decisions remain open.

## Maintained planning and authority

| Reference | Use |
|---|---|
| [Scope](beta-scope.md) | Current target, exclusions, and historical controlled-beta scope |
| [Gap register](beta-gap-register.md) | Open decisions, controls, owners, and triggers |
| [Roadmap](roadmap.md) | Dependency sequence and dated milestone history |
| [Decision log](decision-log.md) | Approval scope and unchanged historical decisions |
| [Work Package status](work-package-status.md) | Dated status and evidence limits |
| [Implementation plan](../implementation/mhcs-core-implementation-plan.md), [requirements matrix](../implementation/mhcs-core-requirements-matrix.md), [source coverage](../implementation/mhcs-core-source-coverage.md) | Retained requirement IDs, assignments, approval/dependency registers, source digests, and provenance limits |
| [MPIPS/DICOM API](../mpips/mhcs-dicom-api.md), [Grabber ingestion contract](../mpips/mhcs-grabber-dicom-ingestion-contract.md) | Active technical contract authority |
| [Historical index](../history-index.md) | Disposition and immutable baseline retrieval for removed material |

The [AI safety/provenance follow-ups](beta-gap-register.md#ai-report-safety-and-provenance-follow-up), MVP-GAP-035/036, remain separate from this board. Only an exact validated task revision authorizes implementation. The maintained entrypoint and supporting records do not establish application acceptance, compliance, or release readiness.
