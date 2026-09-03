---
title: MHCS Core Product Context
document_id: AGENT-CONTEXT-MHCS-CORE-001
version: 1.0
status: approved-context
language: en-US
last_updated: 2026-09-02
scope:
  - repository-level AI orientation
  - product authority mapping
  - product intent and boundaries
authority_note: This file is supporting, refreshable repository context. Approved repository authority in Madeena-software/mhcs-business-docs governs intended behavior. Observed repository evidence in Madeena-software/mhcs-core governs claims about current implementation reality. Neither silently overrides the other, and this context replaces neither.
---

# Repository Context

This file is the repository-level context entrypoint for AI-assisted software delivery when adopted into the **MHCS Core** application repository (`Madeena-software/mhcs-core/.agents/context/project.md`).

It provides a verified orientation map of MHCS Core, identifies where authoritative product information lives, summarizes product intent, human workflows, and boundaries, and routes agents to authoritative sources.

### Document Role

This document provides **MHCS Core product context**.

It is intended for:
- AI coding agents;
- software engineers;
- technical planners.

It explains:
- product purpose and clinical problem domain;
- human workflows;
- actor responsibilities;
- authority boundaries;
- product intent.

It explicitly does **NOT** define or mandate:
- programming language or runtime;
- web or application framework;
- database engine or schema;
- API implementation;
- deployment details;
- source-code structure.

Technical implementation details remain owned by the implementation repository (`Madeena-software/mhcs-core`). This document guides implementation by defining what problem MHCS Core solves, whom it serves, and what boundaries must be preserved.

## Adoption Model

This document is designed to be adopted into:

`Madeena-software/mhcs-core/.agents/context/project.md`

It provides product intent and business orientation for MHCS Core. It does not
replace source code, tests, migrations, runtime evidence, or implementation
documentation in that repository.

---

## Repository identity

**Name:**  
`Madeena-software/mhcs-core`

**Repository type:**  
Healthcare orchestration application platform

**Primary responsibility:**  
Connecting Members, physical healthcare facilities, Site Staff, and Doctors through coordinated screening, diagnostic examination, and clinical review workflows.

---

## Purpose

**MHCS Core** is an Indonesia-led healthcare orchestration platform that connects Members, healthcare facilities (such as Puskesmas and clinics), Site Staff, and Doctors through coordinated screening, diagnostic, and review workflows.

### Why MHCS Exists and the Healthcare Problem Being Solved

In Indonesia, healthcare access and diagnostic interpretation face structural geographical and specialist distribution challenges. Millions of individuals undergo preventive screening or medical examinations at distributed physical locations where certified diagnostic specialists (such as radiologists and specialist physicians) are not physically co-located. The traditional healthcare pathway suffers from:
1. **Fragmented service handoffs:** Disconnect between registration, vital-sign assessment, image capture, processing, and clinical interpretation.
2. **Specialist shortages at examination sites:** Non-radiologist operators capture imaging, but high-quality interpretation requires remote specialist review.
3. **Friction in citizen engagement:** Requiring patients to install dedicated mobile applications or remember portal credentials causes steep drop-offs in preventive screening participation.
4. **Safety and consent gaps:** Clinical identity, guardian authorization, and explicit patient consent must be verified on-site with high fidelity, not inferred through loose online interactions.

MHCS solves these challenges not by replacing health infrastructure or clinical judgement, but by orchestrating existing healthcare capabilities into continuous, reliable, end-to-end service pathways.

### Intended Future Direction

The strategic MHCS product coordinates broader healthcare journeys across prevention, screening, clinical findings, referral, treatment, and continuous monitoring. The current core operating slice focuses on:
```text
booking → on-site check-in & consent → basic examination → radiography capture → processing coordination → finalized result & optional specialist review
```
Future pathways will expand this orchestration to additional modalities, multi-specialty clinical consultations, and health-system interoperability, with each participant retaining authority within its own domain.

---

## Current Context State

**Current state:**  
`mhcs-business-docs` is the Product Authority Repository for MHCS Core.

**Relevant summary:**  
`Madeena-software/mhcs-core` is the application implementation repository. Its
implementation must conform to the approved business and product definitions
without redefining product intent or safety boundaries for technical
convenience.

---

## Intended authority map

Map the intended-authority responsibilities defined by the delivery protocol to the actual approved sources used by MHCS Core:

### Business sources and decisions

- `docs/business/01-business-overview.md`: End-to-end business flow, strategic positioning, detailed actor journeys, and operating model.
- `docs/business/03-system-responsibilities.md`: System responsibilities, ownership boundaries, access controls, payment/earning triggers, and interoperability boundary.

### Product / PRD authority

- `docs/business/01-business-overview.md`: High-level product intent, service episodes, and actor responsibilities.
- `docs/business/02-user-stories.md`: 86 human-centric user stories detailing user intent, acceptance criteria, and interaction surfaces.

### Requirements and matrices

- `docs/business/02-user-stories.md`: User story catalogue with acceptance criteria.
- `docs/business/03-system-responsibilities.md#business-to-technical-traceability`: Traceability map connecting user stories to system responsibilities and implementation boundaries.

### Architecture and repository policy

- Architecture specifications, ADRs, module designs, and technical standards remain owned by and located within `Madeena-software/mhcs-core`.

### Delivery planning

- Governing validated tasks located in `.agents/tasks/` in the respective active repository.

### Release policy

- Operational release procedures and deployment gates remain governed by repository-specific delivery policy in `mhcs-core`.

### Other authority

- **Source Repository:** `Madeena-software/mhcs-business-docs` (governing Product Authority Repository).
- Provenance reference: The context package in `docs/project.md` and `docs/business/` provides complete orientation for `mhcs-core` without requiring external repository access during execution.

---

## Observed implementation evidence map

Map the repository evidence in `mhcs-core` used to establish what currently exists, what changed, and what has actually been verified:

### Source and configuration

- Application source code, module service providers, domain services, and configuration in `Madeena-software/mhcs-core`.

### Data and migrations

- Database migrations, schema definitions, and model relationships in `Madeena-software/mhcs-core`.

### Tests and verification

- Unit, feature, integration, and contract tests in `Madeena-software/mhcs-core`.

### Version control and CI

- Git repository commit history and CI workflow runs in `Madeena-software/mhcs-core`.

### Runtime and operational evidence

- Runtime logs, staging environment observations, and integration test executions.

---

## Top-level architecture and boundaries

### Product Intent vs. Implementation

When designing and building MHCS Core, distinguish between what is stable product intent and what is changeable implementation detail:

| Category | Product Intent (Stable) | Technical Implementation (Changeable) |
|---|---|---|
| **Goals & Value** | Seamless screening coordination, verified patient identity, zero-cost doctor-requested repeat exams, fast and safe result delivery. | Service architecture, queue mechanisms, background workers, caching strategies. |
| **Actors & Roles** | Clear role boundaries (Member, B2B Representative, Site Staff, Doctor, Global Admin); independent Site Staff operational roles; distinction between Radiologist and Specialist. | Role and permission implementation mechanisms (e.g. database tables, permission libraries, token claims). |
| **Human Workflows** | Physical identity verification on-site, signed paper consent scan, sequential stage releases, controlled clinical review handoffs. | UI libraries, form validation engines, component libraries, state management. |
| **Authority Boundaries** | Separation of Requester, Payer, Subject of Care, Guardian, and Result Recipient; clinical binary ownership; least-privilege image access. | File storage drivers, object store configurations, database foreign keys. |
| **Interaction Surfaces** | Messaging Interaction Surface as a persistent citizen touchpoint; temporary task-specific workspaces for staff and doctors; no permanent citizen portal. | Web frameworks, HTTP routes, WebSocket transports, frontend build tools. |

The software implementation must faithfully follow product intent. Implementation mechanisms may evolve as technology advances, but product intent, actor authority, and safety boundaries must remain intact.

### Core Actors

1. **Member:** The citizen or patient receiving care. Interacts primarily through the persistent Messaging Interaction Surface for booking coordination, reminders, and result notifications. The underlying Communication Channel may be WhatsApp, SMS, voice communication, or another approved channel. Opens a secure temporary result surface when richer viewing or download is needed.
   - **Requester / Contact:** The person coordinating the booking conversation.
   - **Payer:** The person or entity funding the service.
   - **Subject of Care:** The person physically undergoing examination.
   - **Guardian:** The legally authorized guardian (for minors or dependent care), verified on-site.
   - **Result Recipient:** The authorized recipient of the finalized clinical result.
2. **Authorized B2B Representative:** The human representative of a Business Customer (enterprise, insurer, or institutional employer) that centrally funds and provisions member examination packages. Determines member scope, service, site, date, and shift allocations. Holds sole authority to request official B2B booking changes.
3. **Site Staff:** Operational personnel deployed at physical examination facilities, working in temporary Site Workspaces across three independently assignable roles:
   - **Reception / Registration:** Performs booking lookup, physical photo-identity verification, signed paper-consent confirmation, walk-in registration, on-site payment tracking, and visit ticket issuance.
   - **Basic Examination:** Claims eligible tickets, records vital signs measurement, point-of-care blood screening, structured health interview, and releases stage to radiography.
   - **Radiography:** Claims X-ray tickets, operates imaging capture, reviews image quality, conducts justified retakes or omissions, and submits complete image sets for processing.
4. **Doctor:** Qualified medical professionals working in assignment-scoped temporary Clinical / DICOM Workspaces:
   - **Radiologist:** Reviews authorized raw/processed DICOM imaging studies, evaluates diagnostic usability, orders controlled repeats when clinically necessary (triggering zero-cost member repeat entitlements), drafts, and finalizes diagnostic radiology reports.
   - **Authorized Specialist:** Non-radiologist clinical specialists (e.g. pulmonologists, cardiologists) who review cases within their accredited specialty and modality scope and produce specialty-appropriate clinical consultations or endorsements.
5. **Global Admin / Super Admin:** System administrators managing platform configuration and governance via Persistent Admin Web (site management, staff qualification checks, financial audits, B2B enterprise provisioning, and exception routing).

### Interaction Model

```text
Persistent Coordination             Scoped Operational Execution           Administrative Control
┌───────────────────────┐           ┌─────────────────────────────┐        ┌──────────────────────┐
│       Messaging       │ ────────> │  Temporary Workspaces       │        │ Persistent Admin Web │
│ (Citizen coordination,│           │  - Temporary Result Surface │        │  (Multi-domain governance,│
│  staff dispatch,      │           │  - Temporary Site Workspace │        │   auditing, configuration,│
│  case notifications)  │           │  - Temporary Clinical /     │        │   exception handling)│
└───────────────────────┘           │    DICOM Workspace          │        └──────────────────────┘
                                    └─────────────────────────────┘
```

1. **Messaging Interaction Surface:** Persistent human coordination capability between MHCS and participants. It supports B2C booking initiation, appointment confirmations, visit reminders, dispatch offers to staff, case availability notifications to doctors, and result readiness alerts. Delivery may use an available Communication Channel such as WhatsApp, SMS, voice communication, or another approved channel, depending on availability, user accessibility, and approved integrations. Ordinary chat is never used to collect sensitive physical identity documents or to store permanent clinical diagnostic records.
2. **Temporary Web Workspace (Temporary Result Surface):** Secure, time-bounded result viewing and report downloading for Members via time-limited signed links. Does not require a permanent username/password portal account.
3. **Temporary Site Workspace:** Task-focused operational interface for Site Staff during an active shift (registration, queue status, vital-sign recording, radiography capture submission). Scoped strictly to the active site, shift, and assigned role.
4. **Temporary Clinical / DICOM Workspace:** Diagnostic study review and clinical reporting environment for Doctors (DICOM viewing, image manipulation, quality evaluation, repeat requests, report drafting, finalization). Scoped per claimed case; closes upon case completion or release.
5. **Persistent Admin Web:** Unified administrative governance across all operational domains.

### Responsibility Boundary

- **MHCS Core Owns:**
  - End-to-end workflow orchestration across screening stages;
  - Role, credential, and assignment authorization enforcement;
  - Patient consent, identity verification, and booking management;
  - Staged queue progression and physical site operational handoffs;
  - Clinical binary storage coordination, access tokening, and audit;
  - Result dispatch orchestration and temporary surface lifecycle;
  - Financial tracking: B2B quotas, B2C payments, staff/doctor earnings.
- **MHCS Core Does Not Define:**
  - External clinical policy or diagnostic standards;
  - Medical judgement (diagnostic conclusions remain solely owned by interpreting Doctors);
  - External processing internals (AI inference and image conversion mechanics operate across a protected boundary, e.g. MPIPS);
  - Hardware internals (Grabber/X-ray machine physical drivers operate at equipment level).

---

## Scoped context

Additional scoped context may exist under `.agents/context/` in `mhcs-core` when partitioned by module or domain:

### Available scoped context

- `Member Core` → `modules/member/project.md` (when partitioned in `mhcs-core`)
- `Operator Core` → `modules/operator/project.md` (when partitioned in `mhcs-core`)
- `Doctor Core` → `modules/doctor/project.md` (when partitioned in `mhcs-core`)
- `Image Gateway` → `modules/image-gateway/project.md` (when partitioned in `mhcs-core`)

---

## Known Gaps and Open Decisions

### Blocking

- None.

### Non-blocking

The following unresolved choices remain in the business authority. They affect
provider selection, payment and refund policy, identity and credential rules,
clinical specialization, implementation boundaries, and interoperability
conformance; none changes the product direction stated above.

The detailed decision register is maintained in
`docs/business/03-system-responsibilities.md`:
1. **Communication Channel Provider:** Approved provider, gateway, and hosting model for the available Messaging Channel.
2. **Messaging Conversational Architecture:** LLM architecture and conversational triage design.
3. **Payment Gateway Provider & Webhook Contracts:** Supported methods (QRIS, VA, e-wallet) and webhook contracts.
4. **Madeena Points Commercial Policy:** Retirement, conversion to loyalty credits, or direct rupiah pricing.
5. **Deposit vs. Full-Payment Policy:** Advance payment vs deposit vs pay-at-site options for Messaging-coordinated bookings.
6. **Cancellation & Refund Terms:** Cancellation cutoffs and automated settlement workflows.
7. **Clinical Result Delivery Channel Mechanics:** Session, authentication, retention, and fallback mechanics for the temporary result surface.
8. **On-Site Identity Verification Procedure:** Minimum data capture and comparison mechanics at Reception / Registration.
9. **Staff Credential & Regulatory Qualification Rules:** Verification criteria for operational roles and doctors.
10. **Specialty-Specific Doctor Workflows:** Clinical workflows and reporting templates for non-radiologist specialists.
11. **Staff Authorization Implementation Mechanism:** Application implementation details for enforcing the three Site Staff roles.
12. **Beta Account Migration Mechanism:** Migration schedule for legacy MVP/beta accounts.
13. **Grabber NPZ Schema:** TIFF bytes vs raw numeric array and compatibility fields.
14. **FHIR R5 Conformance Artifacts:** Canonical URLs, profiles, and validator fixtures.

---

## Repository conventions

1. **Preserve Business Intent Over Technical Convenience:**
   Do not alter user-facing workflows, payment rules, or clinical steps merely to fit default framework abstractions or convenience patterns.
2. **Preserve Actor Authority and Least Privilege:**
   Enforce role boundaries strictly. Non-clinical site staff must never access clinical AI narrative contents or doctor reports; doctors only access studies assigned within their accredited specialty; members access only their authorized results.
3. **Preserve Safety and Verification Integrity:**
   Never bypass physical identity verification, consent confirmation, or doctor quality review gates. A reservation code is a booking locator, not proof of identity.
4. **Preserve Citizen Simplicity:**
   Do not introduce mandatory account creation, usernames, or passwords for Members. Honor the Messaging + Temporary Result Surface model.
5. **Conflict Resolution:**
   When technical requirements or existing codebase assumptions conflict with business intent, **stop and review the intended business behavior first**. Technical specifications serve business intent, not the reverse.

---

## Context verification

This context is supporting, refreshable repository knowledge.

**Last verified:**  
2026-09-02

**Verified against repository revision:**  
`25a4af4590cd423b1cc91708c3b2f16200c06d6d` (context creation snapshot)

**Verified sources:**  
- `docs/business/01-business-overview.md`
- `docs/business/02-user-stories.md`
- `docs/business/03-system-responsibilities.md`

**Known verification limitations:**  
Application code and technical architecture specifications are maintained separately in `Madeena-software/mhcs-core`.
