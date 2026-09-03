# System Responsibilities

This document defines the current specified technical responsibility slice within
the broader MHCS strategic product. Strategically, MHCS is an Indonesia-led
healthcare orchestration and coordination system that may coordinate Government,
provider, clinical, technological, partner, and delivery-channel capabilities.
The present slice is one examination / radiography / AI / optional Doctor Review
service model.

Within that slice, ownership and collaboration are defined for one `mhcs-core`
application repository containing Member, Operator Core, Doctor, and Image
Gateway modules, plus the separate `mpips` black-box conversion repository,
temporary task-specific staff web workspaces, a persistent unified
administration panel, and a Messaging Interaction Surface delivered through
available Communication Channels.

The current ownership map describes the present specified service architecture;
it does not assert that every future MHCS healthcare capability must be
implemented inside `mhcs-core`. External Government systems, healthcare
facilities, professionals, laboratories, imaging/PACS capabilities, AI, payers,
partners, and channels remain externally owned where applicable, with each
authoritative system retaining authority in its own domain. This document does
not prescribe an integration that has not been authorized.

In the B2B model, the **Business Customer** is the organization that funds and
defines the agreed service scope. The **Authorized B2B Representative** is the
human actor authorized to provide, confirm, monitor, reconcile, or request a
change to that scope; the representative does not gain authority beyond the
Business Customer's agreement.

## Responsibility map

| Module or component | Owns | Receives | Produces |
|---|---|---|---|
| Member module in `mhcs-core` | Member healthcare identity (MRN), demographics, requester/payer/subject-of-care/guardian/recipient relations, catalogue, B2B/B2C booking coordination, repeat entitlements, financial tracking, notifications, and Messaging Interaction Surface orchestration | Member interaction through available Communication Channels, clinical repeat commands, and member-safe result references | Attendance, booking locator, examination snapshot, repeat status, and member-safe information delivered through the Messaging Interaction Surface |
| Operator Core module in `mhcs-core` | Physical sites, Site Staff roles and assignments, consent confirmation, completed paper-questionnaire evidence, staged queues, examination capture, LCD calling, staff earnings, and payouts | Attendance query results, physical identity evidence on-site, image acceptance, and processing status | Site data, queue state, complete image submission, and staff status |
| Grabber | Offline-capable radiography capture | Radiography equipment | Image-capture input |
| Image Gateway module in `mhcs-core` | Private image storage, processing coordination, routing, access, publication, and audit | Complete-submission commands and processing results | Processing jobs, authorized references, completion, and publication status |
| `mpips` repository | Public GitHub repository providing the separate private image-processing boundary | Image-processing input | Processed imaging result and technical status |
| Doctor module in `mhcs-core` | Shared doctor queues across specialties, specialty/modality eligibility, study-level quality decisions (for radiology services), repeat requests, reports, amendments, doctor earnings, and payouts | Eligible and replacement studies, supporting output, and repeat status | Quality events, repeat commands, reports, revisions, earnings, and payout status |
| Unified Administration Panel | Presentation and administrative routing surface over domain-owned capabilities | Global Admin / Super Admin interactions across domains | Domain-owned configuration, provisioning, and monitoring actions |

### Messaging ownership boundary

- **MHCS Core owns:** the communication workflow, notification intent, and
  booking/result coordination represented through the Messaging Interaction
  Surface.
- **External Communication Providers own:** the message delivery mechanism and
  channel-specific capabilities of the selected Communication Channel.

WhatsApp, SMS, voice communication, and other approved channels are examples of
available delivery channels, not separate MHCS product capabilities.

Core product context:

- [MHCS Core product context](../project.md)

## Business-to-technical traceability

The business story remains the authority for human intent. This map records
the corresponding system responsibility and the target implementation boundary
in `mhcs-core`.

| User Story / Business Rule | System Responsibility | Owner | Implementation Boundary |
|---|---|---|---|
| [US-MEMBER-001](02-user-stories.md#member) and [US-MEMBER-012](02-user-stories.md#member) booking initiation and confirmation | Coordinate B2C booking initiation and booking confirmation/status communication through Messaging; receive provisioned B2B bookings | Member Core | Member Core in `mhcs-core` |
| [US-MEMBER-002](02-user-stories.md#member) attendance and identity verification | Provide booking lookup and approved on-site identity-verification handoff before check-in | Member Core + Operator Core | Member Core + Operator Core in `mhcs-core` |
| [US-MEMBER-003](02-user-stories.md#member) result-finalization notification and [US-MEMBER-019](02-user-stories.md#member) temporary result access | Notify the Member through Messaging and provide an authorized temporary result surface when richer access is needed | Member Core + Image Gateway | Member Core + Image Gateway in `mhcs-core` |
| [US-MEMBER-004](02-user-stories.md#member) Doctor Review request and [US-MEMBER-013](02-user-stories.md#member) Doctor Review payment | Coordinate optional Doctor Review requests and applicable payment/eligibility handoff | Member Core + Doctor Core | Member Core + Doctor Core in `mhcs-core` |
| [US-MEMBER-005](02-user-stories.md#member) repeat examination and [US-MEMBER-010](02-user-stories.md#member) repeat entitlement | Coordinate a doctor-requested repeat and maintain its linked zero-cost entitlement | Member Core + Doctor Core | Member Core + Doctor Core in `mhcs-core` |
| [US-MEMBER-006](02-user-stories.md#member) price and fee awareness | Present applicable service choices, pricing, and payment requirements before commitment | Member Core | Member Core in `mhcs-core` |
| [US-MEMBER-007](02-user-stories.md#member) payment action | Record payment coordination against the booking and enforce the applicable payment prerequisite | Member Core | Member Core in `mhcs-core` |
| [US-MEMBER-020](02-user-stories.md#member) payment status, failure, and expiry | Communicate payment outcomes and the resulting booking status through Messaging | Member Core | Member Core in `mhcs-core` |
| [US-MEMBER-008](02-user-stories.md#member) cancellation request | Receive and route a cancellation request under the active approved B2C/B2B authority | Member Core + Unified Administration | Member Core + Unified Administration in `mhcs-core` |
| [US-MEMBER-014](02-user-stories.md#member) rescheduling request | Receive and route a rescheduling request under the active approved authority | Member Core + Unified Administration | Member Core + Unified Administration in `mhcs-core` |
| [US-MEMBER-015](02-user-stories.md#member) refund/payment outcome | Communicate the applicable outcome after a booking change without creating unresolved commercial policy | Member Core | Member Core in `mhcs-core` |
| [US-MEMBER-009](02-user-stories.md#member) completed Doctor report access | Deliver an authorized completed Doctor report through Messaging and the temporary result surface where appropriate | Member Core + Doctor Core | Member Core + Doctor Core in `mhcs-core` |
| [US-MEMBER-011](02-user-stories.md#member) family relationships | Preserve family participation separately from clinical identity and family medical history | Member Core | Member Core in `mhcs-core` |
| [US-MEMBER-016](02-user-stories.md#member), [US-MEMBER-017](02-user-stories.md#member), and [US-MEMBER-018](02-user-stories.md#member) relationship roles | Preserve Requester, Payer, Subject of Care, Guardian, and Result Recipient authority distinctions | Member Core | Member Core in `mhcs-core` |
| [US-B2B-001](02-user-stories.md#authorized-b2b-representative) and [US-B2B-002](02-user-stories.md#authorized-b2b-representative) B2B scope and allocation | Provision covered members and agreed service, site, date, and shift scope for the Business Customer | Member Core | Member Core in `mhcs-core` |
| [US-B2B-003](02-user-stories.md#authorized-b2b-representative) booking confirmation | Confirm provisioned B2B bookings to the Authorized B2B Representative | Member Core | Member Core in `mhcs-core` |
| [US-B2B-004](02-user-stories.md#authorized-b2b-representative) authorized booking change | Route an official B2B cancellation/rescheduling request without granting unilateral Member authority | Member Core + Unified Administration | Member Core + Unified Administration in `mhcs-core` |
| [US-B2B-005](02-user-stories.md#authorized-b2b-representative) entitlement/quota monitoring | Expose applicable covered-service utilization and remaining quota records | Member Core | Member Core in `mhcs-core` |
| [US-B2B-006](02-user-stories.md#authorized-b2b-representative) no-show consequences | Expose the applicable business attendance and quota consequence without inventing a new commercial rule | Member Core | Member Core in `mhcs-core` |
| [US-B2B-007](02-user-stories.md#authorized-b2b-representative) usage reconciliation | Reconcile business-funded service usage against applicable entitlement and financial records | Member Core + Unified Administration | Member Core + Unified Administration in `mhcs-core` |
| [US-STAFF-SHARED-001](02-user-stories.md#site-staff--shared) and [US-STAFF-SHARED-002](02-user-stories.md#site-staff--shared) Site Staff offers and workspace access | Dispatch eligible work and open assignment-scoped temporary Site Workspaces | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-SHARED-003](02-user-stories.md#site-staff--shared) work history | Provide concise completed and upcoming assignment history | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-SHARED-004](02-user-stories.md#site-staff--shared) and [US-STAFF-SHARED-005](02-user-stories.md#site-staff--shared) payout status/history and destination | Track Site Staff payout status, history, destination, and authorized management | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-SHARED-006](02-user-stories.md#site-staff--shared) earnings | Expose applicable Site Staff earnings for completed work | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-SHARED-007](02-user-stories.md#site-staff--shared) payout exception | Report payout failure or suspension and the applicable next status | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-REG-001](02-user-stories.md#site-staff--reception--registration) and [US-STAFF-REG-006](02-user-stories.md#site-staff--reception--registration) booking lookup and identity verification | Locate the visit and perform the approved Reception / Registration identity handoff | Operator Core + Member Core | Operator Core + Member Core in `mhcs-core` |
| [US-STAFF-REG-002](02-user-stories.md#site-staff--reception--registration) consent confirmation | Record one auditable consent confirmation for the visit | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-REG-007](02-user-stories.md#site-staff--reception--registration) ticket issuance | Issue one visit ticket after authorized registration | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-REG-003](02-user-stories.md#site-staff--reception--registration) identity mismatch | Stop check-in and route an unresolved identity mismatch to the authorized exception path | Operator Core + Unified Administration | Operator Core + Unified Administration in `mhcs-core` |
| [US-STAFF-REG-004](02-user-stories.md#site-staff--reception--registration) normal check-in | Complete check-in for an eligible arrival and enter the site queue | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-REG-008](02-user-stories.md#site-staff--reception--registration) assisted walk-in | Create an approved walk-in visit through the Reception / Registration path | Member Core + Operator Core | Member Core + Operator Core in `mhcs-core` |
| [US-STAFF-REG-009](02-user-stories.md#site-staff--reception--registration) on-site payment | Record applicable on-site payment before queue entry | Member Core + Operator Core | Member Core + Operator Core in `mhcs-core` |
| [US-STAFF-REG-005](02-user-stories.md#site-staff--reception--registration) cash reconciliation | Close and reconcile collected cash for the authorized shift | Operator Core + Member Core | Operator Core + Member Core in `mhcs-core` |
| [US-STAFF-EXAM-001](02-user-stories.md#site-staff--basic-examination) basic examination capture | Own eligible Basic Examination work and record the required assessment | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-EXAM-002](02-user-stories.md#site-staff--basic-examination) stage release | Complete and release the Basic Examination stage to the next authorized stage | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-EXAM-003](02-user-stories.md#site-staff--basic-examination) required-item exception | Record an allowed unavailable, refused, or not-applicable reason without inventing a value | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-RAD-001](02-user-stories.md#site-staff--radiography) radiography work claim | Assign eligible Radiography work to the responsible Site Staff member | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-RAD-006](02-user-stories.md#site-staff--radiography) capture review and correction | Support Radiography capture, review, justified retake/omission, and image-set completion | Operator Core | Operator Core in `mhcs-core` |
| [US-STAFF-RAD-002](02-user-stories.md#site-staff--radiography) complete-set submission | Hand the completed capture set from Operator Core to Image Gateway for processing | Operator Core → Image Gateway | Operator Core → Image Gateway in `mhcs-core` |
| [US-STAFF-RAD-003](02-user-stories.md#site-staff--radiography) doctor-requested repeat | Assign and complete a controlled Radiography repeat | Operator Core + Doctor Core | Operator Core + Doctor Core in `mhcs-core` |
| [US-STAFF-RAD-004](02-user-stories.md#site-staff--radiography) submission outcome | Return an accepted or action-needed outcome for the submitted image set | Image Gateway + Operator Core | Image Gateway + Operator Core in `mhcs-core` |
| [US-STAFF-RAD-005](02-user-stories.md#site-staff--radiography) role- and assignment-scoped imaging access | Enforce authorized Radiography image and raw-DICOM access for the active assignment | Image Gateway + Operator Core | Image Gateway + Operator Core in `mhcs-core` |
| [US-DOCTOR-SHARED-001](02-user-stories.md#doctor--shared) and [US-DOCTOR-SHARED-004](02-user-stories.md#doctor--shared) Doctor offer and case claim | Dispatch eligible case offers and assign a claimed case to one responsible Doctor | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-SHARED-002](02-user-stories.md#doctor--shared) Temporary Clinical Workspace | Open assignment-scoped temporary clinical work without a permanent worklist portal | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-SHARED-005](02-user-stories.md#doctor--shared) professional work history | Provide active and completed professional work history | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-SHARED-003](02-user-stories.md#doctor--shared) earnings | Expose Doctor earnings for completed clinical work | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-SHARED-006](02-user-stories.md#doctor--shared) payout history/status | Provide Doctor payout history and status | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-SHARED-007](02-user-stories.md#doctor--shared) payout destination/exception | Manage the authorized Doctor payout destination and exception path | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-RAD-001](02-user-stories.md#doctor--radiologist) DICOM study review | Provide authorized Radiologist study review within clinical scope | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-RAD-006](02-user-stories.md#doctor--radiologist) diagnostic usability decision | Record whether the authorized study is usable or requires a repeat | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-RAD-002](02-user-stories.md#doctor--radiologist) controlled repeat request | Record a clinically necessary repeat request and linked handoff | Doctor Core + Member Core | Doctor Core + Member Core in `mhcs-core` |
| [US-DOCTOR-RAD-003](02-user-stories.md#doctor--radiologist) final report submission | Finalize and submit the clinical report and initiate authorized Member result delivery | Doctor Core + Member Core | Doctor Core + Member Core in `mhcs-core` |
| [US-DOCTOR-RAD-007](02-user-stories.md#doctor--radiologist) report drafting | Preserve a draft clinical report before final submission | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-RAD-008](02-user-stories.md#doctor--radiologist) correction/amendment | Preserve clinical result history and authorship when a correction is issued | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-RAD-004](02-user-stories.md#doctor--radiologist) replacement-study review | Return a replacement study to the authorized Radiologist workflow | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-RAD-005](02-user-stories.md#doctor--radiologist) repeat/final-report earnings | Expose controlled eligibility triggers for repeat assessment and final-report earnings | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-SPECIALIST-001](02-user-stories.md#doctor--authorized-specialist) scope-limited review | Enforce authorized specialty and service scope for specialist case review | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-SPECIALIST-002](02-user-stories.md#doctor--authorized-specialist) appropriate specialty output | Support the authorized specialty output without imposing a radiology-only workflow | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-SPECIALIST-003](02-user-stories.md#doctor--authorized-specialist) output finalization | Finalize the appropriate specialist clinical output with preserved authorship | Doctor Core | Doctor Core in `mhcs-core` |
| [US-DOCTOR-SPECIALIST-004](02-user-stories.md#doctor--authorized-specialist) output amendment | Preserve specialist output history and authorship through an authorized amendment | Doctor Core | Doctor Core in `mhcs-core` |
| [US-ADMIN-001](02-user-stories.md#global-admin--super-admin) Site Staff roles and eligibility | Manage eligible Site Staff roles so operational work is offered only to authorized people | Unified Administration | Unified Administration in `mhcs-core` |
| [US-ADMIN-010](02-user-stories.md#global-admin--super-admin) Site Staff assignment | Assign eligible Site Staff to sites and shifts | Unified Administration | Unified Administration in `mhcs-core` |
| [US-ADMIN-002](02-user-stories.md#global-admin--super-admin) Doctor authorization | Manage Doctor specialty, service, modality, credential, and assignment eligibility | Unified Administration | Unified Administration in `mhcs-core` |
| [US-ADMIN-003](02-user-stories.md#global-admin--super-admin) site configuration and [US-ADMIN-011](02-user-stories.md#global-admin--super-admin) service/business configuration | Present domain-owned site, schedule, service, and business configuration through one audited surface | Unified Administration | Unified Administration in `mhcs-core` |
| [US-ADMIN-004](02-user-stories.md#global-admin--super-admin) operational exceptions | Route authorized processing, access, and reassignment exceptions through one auditable surface | Unified Administration | Unified Administration in `mhcs-core` |
| [US-ADMIN-012](02-user-stories.md#global-admin--super-admin) administrative/financial audit | Provide reviewable administrative, financial, access, and legally required record actions | Unified Administration | Unified Administration in `mhcs-core` |
| [US-ADMIN-005](02-user-stories.md#global-admin--super-admin) authorized B2B booking change | Process an official B2B booking-change request with an audit trail | Unified Administration + Member Core | Unified Administration + Member Core in `mhcs-core` |
| [US-ADMIN-006](02-user-stories.md#global-admin--super-admin) earning/rate policy | Manage configured earning and rate policy without making clinical decisions | Unified Administration + owning domains | Unified Administration + owning domains in `mhcs-core` |
| [US-ADMIN-007](02-user-stories.md#global-admin--super-admin) refund reconciliation | Review refund exceptions through the owning domain without inventing refund policy | Unified Administration + Member Core | Unified Administration + Member Core in `mhcs-core` |
| [US-ADMIN-014](02-user-stories.md#global-admin--super-admin) cash reconciliation | Review on-site cash reconciliation exceptions through the owning domain | Unified Administration + Member Core/Operator Core | Unified Administration + Member Core/Operator Core in `mhcs-core` |
| [US-ADMIN-015](02-user-stories.md#global-admin--super-admin) B2B usage reconciliation | Review business-funded usage discrepancies without changing contract terms | Unified Administration + Member Core | Unified Administration + Member Core in `mhcs-core` |
| [US-ADMIN-008](02-user-stories.md#global-admin--super-admin) payout suspension/resumption | Authorize, record, and audit payout suspension or resumption without changing earned-work records | Unified Administration + owning payout domains | Unified Administration + owning payout domains in `mhcs-core` |
| [US-ADMIN-016](02-user-stories.md#global-admin--super-admin) operational financial exceptions | Resolve authorized payment discrepancies without changing earned-work records | Unified Administration + owning domains | Unified Administration + owning domains in `mhcs-core` |
| [US-ADMIN-009](02-user-stories.md#global-admin--super-admin) identity/access exceptions | Resolve identity and access exceptions without elevating an unqualified role | Unified Administration + owning domains | Unified Administration + owning domains in `mhcs-core` |

This bridge prevents implementation details from becoming an independent source
of human requirements. Detailed mechanics remain in implementation evidence and
applicable authority sources; unresolved commercial, identity, credential, and
workspace-mechanism decisions remain open below.

## Member Core

### Owns

- globally unique medical-record numbers (MRN);
- healthcare identity, member demographics, and relations;
- conceptual separation of Requester/contact, Payer, Subject of care, Guardian, and Result recipient;
- B2B and B2C booking coordination, cancellation rules, pricing snapshots, and payment tracking;
- walk-in registration and on-site payment tracking;
- service choices per examination type or body part;
- zero-cost, doctor-requested repeat entitlements and member-controlled repeat scheduling through the Messaging Interaction Surface;
- member notifications through the Messaging Interaction Surface; and
- Messaging notification followed, where appropriate, by secure temporary result-link delivery to a task-specific web surface; this is not a persistent Member Portal.

Member Core does **not** own front-desk queues, image capture, raw NPZ, permanent
DICOM storage, AI execution, doctor work queues, or Site Staff/Doctor earnings.
It does **not** provide an authenticated member web portal, native mobile apps,
desktop apps, or member login credentials.

### Target handoffs

Member Core supplies authorized attendance, booking locator, and examination
information to Operator Core. It receives temporary image references, AI results,
doctor reports, and amendments through Image Gateway for Messaging notification and
secure temporary result-link delivery where appropriate.

A booking code received through the available Messaging Channel serves as a reservation locator and is not
sufficient proof of patient identity. At front-desk check-in, Reception /
Registration Site Staff
holding the Reception / Registration role verify official physical identity documents
(approved physical identity evidence and comparison) against Member Core records, and record the signed
paper consent and its required private scan once for the visit. Downstream stations
reuse this consent confirmation and do not re-request consent.

Sensitive identity documents are never requested or collected via
ordinary Messaging interaction to preserve privacy.

Member Core accepts authenticated, idempotent repeat requests only from Doctor
Core. It creates one active zero-cost, doctor-only entitlement, notifies the
member through the Messaging Interaction Surface, lets the member choose any compatible site and shift, and
returns entitlement or decline status to Doctor Core. A scheduled repeat consumes
ordinary booking capacity and does not request AI.

Members receive a notification through the Messaging Interaction Surface and, where appropriate, a secure temporary
result surface for viewing or download; on-site print remains an available operational
fallback. Member Core does not store raw NPZ or permanent DICOM copies.

### B2B and B2C target rules

B2B is the initial commercial priority, while direct B2C bookings are coordinated
via the Messaging Interaction Surface:

- B2B enterprise agreements provision members, entitlements, locations, dates,
  and shifts.
- The business funds B2B bookings centrally. The member cannot cancel or reschedule
  a B2B booking; only Global Admin / Super Admin acting on an official business request
  may do so. A B2B no-show remains paid and consumes the business quota.
- For B2C services, members initiate booking and payment coordination through
  the available Messaging Channel.
- Financial transactions, pricing snapshots, and payment statuses are tracked
  with domain integrity in Member Core.

> [!NOTE]
> The legacy rule that Madeena Points is the exclusive member payment instrument
> is undergoing reconciliation. Commercial decisions regarding Madeena Points
> retirement, internal credit conversion, direct rupiah pricing, payment gateways,
> deposit vs full-payment, and refunds remain explicitly open design decisions.

## Operator Core

### Owns

- physical-site master data and staff shift assignment;
- Site Staff role eligibility and assignment:
  1. Reception / Registration: front-desk check-in, approved physical identity verification, booking lookup, paper consent confirmation, ticket issuance, and thermal slip printing;
  2. Basic Examination: claiming basic examination tickets, recording vital signs, point-of-care blood screening, structured interview, and paper questionnaire confirmation;
  3. Radiography: claiming X-ray tickets, image capture review, retake/omission handling, and complete-set submission;
- multi-role support: a person may hold multiple roles when eligible; technical enforcement remains in the application layer;
- station selection rules: station selection (`TU`, `PEMERIKSAAN DASAR`, `SESI FOTO RADIOGRAFI`) routes work and LCD calls but cannot grant or elevate a role;
- MVP/beta transitional compatibility: existing beta accounts may temporarily map to all three operational roles;
- new staff provisioning: Global Admin / Super Admin explicitly selects applicable operational roles;
- one site-and-shift ticket across ready-time FIFO basic examination and X-ray queues;
- atomic stage claims, public number-to-station calls for `PEMERIKSAAN DASAR` and `SESI FOTO RADIOGRAFI`, and paired LCD displays;
- basic examination & vital signs measurements, point-of-care screening, and structured interview capture;
- image-set draft and review;
- one Submit action for the complete capture set;
- processing status, role-scoped image viewing, and read-only AI readiness/status monitoring; and
- configured basic examination and X-ray earnings and automated rupiah payouts.

### Target handoffs

The Operator module hands the completed radiography submission and its active
examination context to the Image Gateway module.

Basic examination completion releases the same ticket to X-ray and makes the completing
worker's stage earning eligible. Gateway acceptance completes X-ray, releases
processing to Image Gateway, and makes the submitting worker's stage earning
eligible. Asynchronous AI completion automatically marks the ticket as completed.

## Grabber

Grabber supports Radiography Site Staff in capturing the image set, including
offline operation where applicable. It does not fetch member data or publish
clinical results. Capture-file and processing details remain in the technical
specifications.

## Image Gateway

### Owns

- controlled acceptance and private storage of submitted image studies;
- MPIPS and AI processing coordination;
- authorized image/result access and publication;
- processing and completion status; and
- the image-submission payment eligibility handoff.

### Completion boundary

Processed images become available only to the authorized Radiography Site Staff
assignment and are published to Member Core and Doctor Core when the complete
study is ready. Incomplete or failed processing remains outside Member and
Doctor publication until the applicable recovery or exception outcome is reached.

## MPIPS

### MHCS responsibility

MPIPS provides the separate image-processing capability used by Image Gateway.
Image Gateway owns processing coordination, storage, completion, publication,
and payment meaning.

The separate `mpips` repository defines the authoritative transport and security contract
for this boundary.

## Doctor Core

### Owns

- shared doctor work queues filtered by specialty authorization and modality eligibility;
- multi-specialty clinical review (radiologists and authorized non-radiologist specialists);
- case claim, release, and Global Admin / Super Admin reassignment;
- study viewing and controlled clinical access;
- explicit, audited DICOM download when clinically necessary;
- radiology-specific workflows: immutable study-level `usable` or `repeat_required` decisions;
- controlled repeat reasons and clinical repeat handoff to Member Core;
- draft, final, corrected, and amended reports; and
- doctor earnings and automated rupiah payouts.

### Report and payment boundary

Non-radiologist specialists review clinical services within their authorized scope
and are not forced into radiology quality decisions, DICOM reviews, or repeat-imaging
workflows. Doctor Core does not copy Operator examination workflows.

An explicit usable decision remains part of the radiology clinical workflow and does not
change completed Site Staff stage earnings. A repeat request preserves the draft,
blocks final submission, and becomes a 25% doctor earning only after Member
Core confirms creation of the repeat entitlement. Each separately accepted
sequential repeat creates another 25% earning.

Submit finalizes a report, creates a 100% final-report earning for the signing
doctor, and starts automatic member publication through the Messaging Interaction Surface. An unfinished draft
creates no earning. Reassignment preserves earnings already triggered by completed work.

A submitted report is immutable. A necessary correction may be issued at any
time, preserves the original, and does not create another payment.

Eligible doctor earnings enter automated daily payouts with no minimum positive
balance. MHCS absorbs transfer fees by default.

## Unified Administration

### Presentation surface

Unified Administration provides a single Global Admin / Super Admin web panel spanning
domain-owned operations. It acts as a role and presentation surface over:

- Member domain: agreement tracking, booking reconciliation, payment monitoring;
- Operator Core domain: site master data, shift schedules, Site Staff roles (Reception / Registration, Basic Examination, Radiography), protocol templates, earning rates;
- Doctor domain: doctor specialty/modality authorizations, queue reassignment, reporting rates;
- Image Gateway domain: submission monitoring, processing errors, storage compliance.

Unified Administration does **not** create a separate monolithic "Admin" business
domain or database schema; domain ownership remains strictly with the respective
modules.

## Payment ownership and triggers

| Payment area | Owning module | Eligibility trigger |
|---|---|---|
| B2B member entitlement | Member Core | Central annual agreement provisions member entitlements; tracked in Member Core financial records |
| B2C member charge | Member Core | Member booking coordination completed through the Messaging Interaction Surface; payment tracked before visit confirmation |
| Site Staff basic-examination earning | Operator Core | Basic examination completion triggers configured stage rate for performing Site Staff member |
| Site Staff radiography earning | Operator Core | Image Gateway acceptance triggers configured stage rate for submitting Site Staff member |
| Doctor repeat-assessment earning | Doctor Core | Member Core confirms one doctor-requested repeat entitlement: 25% of snapshotted final-report rate |
| Doctor final-report earning | Doctor Core | The signing doctor submits the completed report: 100% of snapshotted final-report rate |

Gateway acceptance is the X-ray-stage earning trigger. DICOM completion and
doctor-queue entry do not create additional Site Staff earnings.

## Business-policy classification

Technical specifications may describe enforcement, but these policies are not
silent technical inventions:

| Policy | Classification | Business authority / status |
|---|---|---|
| Advance-booking threshold, booking quota, and walk-in quota | Configurable operating policy | Candidate defaults are documented in Operator Core; Product Authority may revise them. |
| Sequential work-offer response timeout | Configurable operating policy | Candidate default is five minutes; exact operational setting remains configurable. |
| B2B cancellation, reschedule, and no-show treatment | Business authority | Candidate B2B rule is business-funded, non-member-cancellable, and paid on no-show; exceptions require an official business request. |
| B2C cancellation, deposit/full payment, and refunds | Open business decision | Provider, timing, fees, settlement, and refund policy remain unresolved. |
| Stage and Doctor earning triggers/rates | Business authority with configured rates | Completion/acceptance/report events define eligibility; exact rates and payout mechanics remain configured/open where listed. |
| Guardian and Result Recipient authority | Business authority with unresolved evidence mechanics | Relationships and member-safe access are distinct; evidence, legal-status transition, and delivery mechanics remain open. |
| DICOM access | Business/security rule | Least-privilege access is role and assignment scoped; implementation/session mechanics remain implementation evidence or applicable authority. |

This classification is the required bridge for technical-only operational rules:
technical context and implementation sources may reflect these decisions, but may not create
new commercial, clinical, identity, or authorization policy without authority.

## Access map

| User | Raw NPZ | View image | Raw DICOM download | AI result | Doctor report |
|---|---:|---:|---:|---:|---:|
| Member (available Messaging Channel) | No | Member-safe delivery | No | Messaging notification plus secure temporary result surface where appropriate | Messaging notification plus secure temporary result surface where appropriate |
| Reception / Registration Site Staff | No | Only the minimum member/booking information needed for check-in | No raw DICOM | No | No doctor report |
| Basic Examination Site Staff | No | Only information needed for the assigned basic examination | No raw DICOM | No | No doctor report |
| Radiography Site Staff | No | Authorized image view for the active assigned examination | Only where operationally required for the active assigned examination; authenticated attachment | No | No doctor report |
| Doctor (Radiologist / Specialist) | No | Yes, for authorized study | Explicit, audited clinical need | If available | Own workflow |
| Global Admin / Super Admin | Controlled backend access | As required for administration | Controlled backend access | Routing context | Version/audit context |

Radiography Site Staff raw-DICOM downloads are authenticated, non-public attachments
with no permanent public URL and are limited to an active authorized examination.
Members receive member-safe result notifications through the Messaging Interaction Surface and may open a secure
temporary result surface; they do not receive raw DICOM. Site Staff never receive
raw NPZ.

## FHIR R5 boundary

HL7 FHIR R5 `5.0.0` clinical structures apply to:

- patient identity;
- examinations;
- imaging studies; and
- clinical reports.

Queues, payments, retries, storage administration, and other non-clinical
operations use ordinary module contracts and domain events.

Doctor Core does not represent its queue, claims, assignments, deadlines, or
repeat-entitlement state as FHIR `Task`. A repeat creates new linked
`ServiceRequest`, `Appointment`, `Encounter`, and `ImagingStudy` resources while
preserving the original chain.

## Open design decisions

The following are intentionally unresolved by current human authority and
remain open design decisions:

1. **Communication Channel Provider:** Approved provider and channel-specific capability for the available Messaging Channel.
2. **Messaging Conversational Architecture:** Exact conversation flow design, NLP/LLM orchestration layer, automated triage logic, and human-handoff escalation boundaries.
3. **Payment Provider Integration:** Exact payment gateway adapter, payment methods (QRIS, VA, e-wallet), webhook schemas, and timeout/settlement contracts.
4. **Madeena Points Commercial Policy:** Final commercial determination whether Madeena Points are retired, converted to internal loyalty/subsidy credits, or replaced by direct rupiah pricing.
5. **Deposit vs. Full-Payment Policy:** Commercial rules regarding whether Messaging-coordinated bookings require full advance payment, a deposit, or pay-at-site options.
6. **Cancellation & Refund Commercial Terms:** Specific cancellation cutoffs, refund fee policies, and automated refund settlement workflows for Messaging-coordinated bookings.
7. **Clinical Result Delivery Channel Mechanics:** Exact secure temporary result-link, session/authentication, disclosure, retention, and fallback mechanics; the result surface must remain task-specific and must not become a persistent Member Portal.
8. **On-Site Identity Verification Procedure:** Exact permitted evidence, comparison method, data minimization, retention, and storage mechanics at the Reception / Registration station (`TU` compatibility label).
9. **Staff Credential & Regulatory Qualification Rules:** Formal regulatory qualification, certification evidence, and credential verification criteria for Reception / Registration Site Staff, Basic Examination Site Staff, Radiography Site Staff, radiologists, and non-radiologist specialists.
10. **Specialty-Specific Doctor Workflows:** Specific clinical sub-specialty workflows, modality eligibility matrices, and reporting templates for non-radiologist specialists.
11. **Staff Authorization Implementation Mechanism:** Application implementation details for enforcing the three Site Staff roles.
12. **Beta Account Migration Mechanism:** Migration and transition schedule for upgrading existing MVP/beta operator accounts to the granular permission model.
13. **Grabber NPZ Schema:** Whether Grabber NPZ contains TIFF bytes, raw numeric array, or both, and required MPIPS compatibility fields.
14. **FHIR R5 Conformance Artifacts:** Exact canonical URLs, package IDs, profiles, and validator fixtures.

## External references

Doctor-access and report-amendment rules use:

- [DICOM WADO-RS rendered retrieval](https://dicom.nema.org/medical/Dicom/2016d/output/chtml/part18/sect_6.5.8.html);
- [DICOM WADO-RS study retrieval](https://dicom.nema.org/medical/dicom/2017b/output/chtml/part18/sect_6.5.html);
- [HL7 FHIR DiagnosticReport](https://hl7.org/fhir/diagnosticreport.html);
- [Indonesian Ministry of Health Regulation No. 24 of 2022](https://jdih.kemkes.go.id/common/dokumen/2022permenkes024.pdf); and
- [Indonesian Personal Data Protection Law No. 27 of 2022](https://peraturan.bpk.go.id/Details/229798/uu-no-27-); and
- [ACR Practice Parameter for Communication of Diagnostic Imaging Findings](https://www.acr.org/-/media/acr/files/practice-parameters/communicationdiag.pdf).

External requirements must be revalidated before a compliance claim.

## External interaction-model evidence

The following evidence challenges the assumption that messaging should contain
the complete clinical workflow:

| External observation | Implication / trade-off | MHCS working decision |
|---|---|---|
| [Telegram Mini Apps](https://core.telegram.org/bots/webapps) and [LINE MINI Apps](https://developers.line.biz/en/docs/line-mini-app/quickstart/) embed web applications inside messaging products and can support richer task interactions. | A message can be a durable entry point into a richer task surface without requiring a permanent standalone consumer portal; platform policy, authentication, and lifecycle constraints still apply. | Keep the Messaging Interaction Surface as the persistent interaction/orchestration layer and use secure temporary web surfaces for tasks requiring richer UI. Telegram/LINE behavior is a benchmark, not an MHCS platform decision. |
| The [Henan province-wide telepathology evaluation](https://pmc.ncbi.nlm.nih.gov/articles/PMC13010077/) used mobile access alongside a secured platform; clinical records/images remained in protected infrastructure, while case review and reporting remained in the controlled clinical platform. | Mobile messaging/access can improve reach, but diagnostic review and reporting require controlled clinical storage, authorization, audit, and appropriate viewing conditions. | Operators and Doctors receive Messaging dispatch, then work in assignment-scoped temporary workspaces; MHCS remains the system of record and Image Gateway retains clinical binary ownership. |
| [WhatsApp Business Policy](https://whatsappbusiness.com/policy/) requires consent and restricts sharing sensitive identifiers; health-information use may require heightened safeguards depending on applicable regulation. | WhatsApp is suitable for notification, coordination, and minimal-information offers only when policy and applicable safeguards permit; it should not be assumed to be the DICOM viewer or clinical record. | Do not send identity documents or unnecessary clinical detail through ordinary WhatsApp. Notify members and provide a secure temporary result link when a richer result surface is required. |
