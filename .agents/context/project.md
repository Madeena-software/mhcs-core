---
title: MHCS Core Repository Context
document_id: AGENT-CONTEXT-MHCS-CORE-001
version: 1.1
status: supporting-context
language: en-US
last_updated: 2026-10-12
scope:
  - repository orientation and authority/evidence routing
  - Admin-first operational MVP planning
authority_note: Supporting context, not product authority or implementation proof. Approved business sources and scoped human decisions govern intent; observed repository evidence governs implementation claims.
---

# Repository Context

### Document Role

Concise orientation for `Madeena-software/mhcs-core`. Start with the [active MVP map](../../docs/mvp/README.md), then load the sources relevant to the governing task.

## Adoption Model

This is the application repository's context entrypoint. The maintained business working reference is the Google Doc below; local copies of the former business repository are provenance only and are removed after active links are repaired.

## Repository identity

- Repository: `Madeena-software/mhcs-core`; modular application with Member, Operator, Doctor, and Image Gateway ownership.
- Application baseline: `b1a2204a2571920a6c696575a6480aad5a1294fd`.
- Documentation execution: `task/admin-first-doc-rebaseline`, governed by [the validated task](../tasks/admin-first-documentation-rebaseline.md) at `5e47b8d61db27741c2048b7c5131b5a7f0f4ef49`.
- Maintained business working reference: [Stakeholder Discussion Draft v0.2](https://docs.google.com/document/d/1zxmPjB-gevtVuaoTEmAtLjpDWlJf7USnkeigVbPJw0E/edit). No exact source revision or verified immutable snapshot is available; BP-*, D-*, BR-*, and EX-* content is not approved by this reference.
- Historical business-source provenance: `Madeena-software/mhcs-business-docs @ 645058e431f59c4450a136e72f140e6819b79f32`. This is no longer maintained active authority and is not asserted equivalent to the Google Doc.
- Admin-first documentation implementation `7132e7001b704789f6d122bcfad6a5b4e040bc0b` was independently accepted and merged through PR #5 as `8bb0c21e87e6f9e2c32a01ccab83aed37f1e91b7`; this is not application acceptance. The current documentation cleanup is Review Required pending independent review.

## Accepted Admin registration development slice

The immutable accepted development baseline for bounded synthetic/local
Admin-assisted adult Member registration is
`21d279831455d32fe2a707e8d306af861c0bbb08`, governed by
`.agents/tasks/admin-first-manual-member-registration.md` at
`e7ccc686da049e78d7634125237fc794b30a0908`, from implementation baseline
`fb25354311121fc8163256cd0530d9cbcb66bb68`.
[V7/A9 acceptance evidence](../../docs/mvp/evidence/admin-first-manual-member-registration-acceptance.md)
records the externally supplied independent verdict, AC1–AC8 scope, verification
limits and unresolved gaps. This supersedes the older application-baseline
orientation only for that bounded slice. PR #7 integration and release approval
remain separate; no broader gap or Work Package completion is claimed.

## Purpose

### Why MHCS Exists and the Healthcare Problem Being Solved

MHCS coordinates healthcare pathways while participants retain their own authority. The maintained business working reference is the [Stakeholder Discussion Draft v0.2](https://docs.google.com/document/d/1zxmPjB-gevtVuaoTEmAtLjpDWlJf7USnkeigVbPJw0E/edit); its content remains unapproved and its immutable revision is unavailable.

### Intended Future Direction

Broader healthcare orchestration remains strategic scope. The active operational priority is one persistent internal Admin login and a unified dashboard over domain-owned operations. Target scope includes manual registration, planned CSV/XLSX templates/import validation, centralized study monitoring, temporary staff/clinical workspaces, and authorized time-limited Member results; these targets are not implementation claims.

## Current Context State

The human approved the bounded documentation rebaseline after independent review of `74755cb2c0b40c061b4734c1c1fb5b267a7eb805`. [MVP-DEC-044](../../docs/mvp/decision-log.md#admin-first-priority-decision) records the priority and its limited supersession. Earlier beta acceptance, exceptions, and evidence remain historical, with no account removal, migration, clinical access expansion, or production operation implied.

## Intended authority map

### Business sources and decisions

- [Stakeholder Discussion Draft v0.2](https://docs.google.com/document/d/1zxmPjB-gevtVuaoTEmAtLjpDWlJf7USnkeigVbPJw0E/edit); not approved authority. The former business repository revision above is historical provenance only.
- [MVP decisions](../../docs/mvp/decision-log.md): preserve historical approval scope; the new priority does not resolve conflicting clinical policies.

### Product / PRD authority

Business propositions in the Google Doc do not define approved user-story acceptance. The [active scope](../../docs/mvp/beta-scope.md#admin-first-operational-scope) records the human-directed target without inventing mechanisms.

### Requirements and matrices

KEEP / historical authority and reference, unchanged:

- [Implementation plan](../../docs/implementation/mhcs-core-implementation-plan.md): Work Packages and approval/dependency registers.
- [Requirements matrix](../../docs/implementation/mhcs-core-requirements-matrix.md): stable IDs and assignments.
- [Source coverage](../../docs/implementation/mhcs-core-source-coverage.md): ID lifecycle, original source digests, and unresolved provenance.

Their 2026-08-04 evidence classifications are historical, not current application status. Use only relevant references; no exhaustive ledger reconstruction is required for the documentation rebaseline.

### Architecture and repository policy

[Repository contract](../AGENTS.md) and [delivery protocol](../software-workflow.md) control execution. Administration is a presentation surface, not a fifth business domain. [MPIPS conversion](../../docs/mpips/mhcs-dicom-api.md) and [Grabber DICOM ingestion](../../docs/mpips/mhcs-grabber-dicom-ingestion-contract.md) retain independent contract authority.

### Delivery planning

[Roadmap](../../docs/mvp/roadmap.md#admin-first-planning-sequence), [gaps](../../docs/mvp/beta-gap-register.md#admin-first-gaps-and-authority-reconciliation), and [status orientation](../../docs/mvp/work-package-status.md#admin-first-status-orientation) distinguish planned work from dated evidence. Only validated task revisions authorize implementation.

### Release policy

G10 is separate from documentation review, application acceptance, and bounded historical deployment. Production-data use, environment readiness, migration, and release approvals remain open where not explicitly granted.

### Other authority

The former business repository is historical provenance only. The supplied Google Doc is the maintained working draft, but without an exact source revision or verified immutable snapshot no adoption or approval claim may be made from it.

## Observed implementation evidence map

### Source and configuration

At the application baseline, `app/Providers/Filament/AdminPanelProvider.php` declares `/admin` with Member/Operator resources; `routes/api.php` declares authenticated Grabber ingestion, and `routes/web.php` declares Operator study and AI-report actions. These source observations do not prove the unified target dashboard, generic import, temporary workspaces, or finalized-result delivery.

### Data and migrations

Existing schema and identity boundaries remain unchanged. No account transition, import, or production-data operation is performed by this documentation contract.

### Tests and verification

Use the relevant unchanged [historical evidence](../../docs/mvp/work-package-status.md) and the exact task/review revision for any acceptance claim. No application tests were rerun for this documentation rebaseline.

### Version control and CI

The baseline's merged field/AI work is visible in Git; merge and task status alone do not establish application acceptance. No current CI or runtime certification is asserted here.

### Runtime and operational evidence

[Production rehearsal evidence](../../docs/mvp/evidence/prestige-production-rehearsal-data.md) and [deployment evidence](../../docs/mvp/evidence/production-swarm-deployment.md) apply only to their dated, bounded scopes.

## Top-level architecture and boundaries

### Product Intent vs. Implementation

The [scope table](../../docs/mvp/beta-scope.md#admin-first-operational-scope) separates existing evidence, approved direction, and future/unresolved behavior. Current code cannot retroactively approve product policy.

### Core Actors

Admin coordinates domain-owned operations. Members, radiographers, radiologists, and other authorized staff retain distinct identities and assignments. Requester, payer, subject of care, guardian, and result recipient remain separate authority roles.

### Interaction Model

Persistent Admin access is the operational priority. Temporary assignment-scoped staff/clinical workspaces and secure time-limited Member results are target surfaces, with authentication, expiry, revocation, recovery, and disclosure rules still open. Messaging remains a business coordination surface; no channel/provider decision is made here.

### Responsibility Boundary

Member retains identity, booking, and recipient relations; Operator retains site/staff and operational stage responsibilities; Doctor retains clinical judgments and report authorship; Image Gateway retains private imaging, processing, and publication coordination. MPIPS remains a separate private processing boundary. Admin navigation transfers none of these responsibilities.

## Scoped context

### Available scoped context

No local business-source package is maintained here. Use the linked Google Doc as a Draft working reference and the seven-document MVP map and scoped technical evidence for repository context.

## Known Gaps and Open Decisions

### Blocking

[MVP-GAP-027–034](../../docs/mvp/beta-gap-register.md#admin-first-gaps-and-authority-reconciliation) cover Admin assembly/portal transition, temporary clinical workspaces, access and finalization, consent/bypass, privacy/storage, import, documentation review, and application acceptance evidence. These remain open for their affected implementation or review stage. In particular, the business Site Staff access map and the scoped Operator AI-report directive require explicit reconciliation; AI output is not a doctor-finalized report.

### Non-blocking

Business-source open decisions on communication/payment providers, commercial policy, qualifications, specialty workflows, account migration, NPZ details, and FHIR artifacts remain unresolved. The Google Doc's draft content is not an approval for these decisions; they cannot be assumed resolved for dependent application work.

## Repository conventions

Preserve least privilege, server-derived actor/site/shift/case authorization, MRN/NIK separation, private objects, idempotency, validation, and attributable audit. A locator or possession of a link is not identity or clinical authority. Preserve consent and report history and current scoped exceptions without extending them. Escalate conflicts through the authority named in the gap register; never use convenience or implementation presence as approval.

## Context verification

Updated 2026-10-12 against the application baseline and governing documentation task above. Former business source provenance is `Madeena-software/mhcs-business-docs @ 645058e431f59c4450a136e72f140e6819b79f32`; removed local copies are retrievable from the repository documentation baseline indexed in [history](../../docs/history-index.md). The Google Doc remains Draft v0.2; no exact revision or verified immutable snapshot was available. Earlier context snapshot `25a4af4590cd423b1cc91708c3b2f16200c06d6d` is historical. Verification is limited to affected documentation, references, and cited source observations; no application, clinical, legal, production, or release readiness is established.
