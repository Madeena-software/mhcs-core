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

This is the application repository's context entrypoint. Business-source copies under `business/` remain unchanged; technical evidence and delivery planning live here, not in the business repository.

## Repository identity

- Repository: `Madeena-software/mhcs-core`; modular application with Member, Operator, Doctor, and Image Gateway ownership.
- Application baseline: `b1a2204a2571920a6c696575a6480aad5a1294fd`.
- Documentation execution: `task/admin-first-doc-rebaseline`, governed by [the validated task](../tasks/admin-first-documentation-rebaseline.md) at `5e47b8d61db27741c2048b7c5131b5a7f0f4ef49`.
- Business authority: `Madeena-software/mhcs-business-docs @ 645058e431f59c4450a136e72f140e6819b79f32`.
- Documentation implementation requires independent review; this context establishes no new accepted application baseline or release approval.

## Purpose

### Why MHCS Exists and the Healthcare Problem Being Solved

MHCS coordinates healthcare pathways while participants retain their own authority. See the [business overview](business/01-business-overview.md) for the problem, journeys, and actor responsibilities.

### Intended Future Direction

Broader healthcare orchestration remains strategic scope. The active operational priority is one persistent internal Admin login and a unified dashboard over domain-owned operations. Target scope includes manual registration, planned CSV/XLSX templates/import validation, centralized study monitoring, temporary staff/clinical workspaces, and authorized time-limited Member results; these targets are not implementation claims.

## Current Context State

The human approved the bounded documentation rebaseline after independent review of `74755cb2c0b40c061b4734c1c1fb5b267a7eb805`. [MVP-DEC-044](../../docs/mvp/decision-log.md#admin-first-priority-decision) records the priority and its limited supersession. Earlier beta acceptance, exceptions, and evidence remain historical, with no account removal, migration, clinical access expansion, or production operation implied.

## Intended authority map

### Business sources and decisions

- [Business overview](business/01-business-overview.md).
- [System responsibilities](business/03-system-responsibilities.md), including module ownership, access map, and open decisions.
- [MVP decisions](../../docs/mvp/decision-log.md): preserve historical approval scope; the new priority does not resolve conflicting clinical policies.

### Product / PRD authority

[User stories](business/02-user-stories.md) define actor intent and acceptance. Open or candidate source sections retain their status. The [active scope](../../docs/mvp/beta-scope.md#admin-first-operational-scope) records the human-directed target without inventing mechanisms.

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

The business repository is read-only in this execution. Local source copies are pinned references; this context and derived graphs do not amend them.

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

The three `business/` files above are the available business-source package. No module-context files are asserted to exist. Use the seven-document MVP map and scoped implementation evidence when needed.

## Known Gaps and Open Decisions

### Blocking

[MVP-GAP-027–034](../../docs/mvp/beta-gap-register.md#admin-first-gaps-and-authority-reconciliation) cover Admin assembly/portal transition, temporary clinical workspaces, access and finalization, consent/bypass, privacy/storage, import, documentation review, and application acceptance evidence. These remain open for their affected implementation or review stage. In particular, the business Site Staff access map and the scoped Operator AI-report directive require explicit reconciliation; AI output is not a doctor-finalized report.

### Non-blocking

Business-source open decisions on communication/payment providers, commercial policy, qualifications, specialty workflows, account migration, NPZ details, and FHIR artifacts remain in [System Responsibilities](business/03-system-responsibilities.md#open-design-decisions). They do not block writing this approved documentation scope, but cannot be assumed resolved for dependent application work.

## Repository conventions

Preserve least privilege, server-derived actor/site/shift/case authorization, MRN/NIK separation, private objects, idempotency, validation, and attributable audit. A locator or possession of a link is not identity or clinical authority. Preserve consent and report history and current scoped exceptions without extending them. Escalate conflicts through the authority named in the gap register; never use convenience or implementation presence as approval.

## Context verification

Updated 2026-10-12 against the application baseline and governing documentation task above. The pinned business remote remained unchanged at preflight; its three local source copies match the reviewed package. Earlier context snapshot `25a4af4590cd423b1cc91708c3b2f16200c06d6d` is historical. Verification is limited to affected documentation, references, and cited source observations; no application, clinical, legal, production, or release readiness is established.
