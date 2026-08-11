# MHCS Core Image Gateway Module Specification

**Status:** Approved target module
**Last reviewed:** 30 July 2026

This document defines the Image Gateway module in the approved `mhcs-core`
modular application. The overall repository and runtime boundary is defined by the
[MHCS Core architecture](../../project.md).

## Purpose

Image Gateway is the controlled module between operational capture, permanent
image storage, MPIPS processing, optional AI, doctor review, and result
publication.

It has administrator-only operational access and queue workers, not a separate
end-user application or independently deployed MHCS service.

## Intended consumers

- The Operator module submits completed capture sets locally.
- MPIPS receives authorised processing work.
- The Doctor module receives eligible studies and returns reports.
- The Member module receives member-safe image and result references.
- Administrators receive final-failure notifications and manage exceptional
  compliance actions.

## MHCS Core topology

Image Gateway is a module and set of queue workers in the single `mhcs-core`
repository and runtime. It shares the application database and deployment
foundation while exclusively owning binary-storage metadata, conversion jobs,
processing state, and authorized file access.

Operator, Member, and Doctor use local module contracts and domain events.
They do not call Image Gateway over a network boundary. The Image Gateway
worker is the only MHCS Core caller of the separate private MPIPS conversion
contract.

## Submission boundary

The Image Gateway module receives:

- one or more patient-free radiograph NPZ captures for one examination;
- the gain NPZ object required by each radiograph capture, correlated by its
  frozen gain identity;
- a frozen member/examination metadata snapshot;
- the globally unique medical-record ID;
- organisation and examination identity; and
- traceability information for the submitting operator.

Durable acceptance of the complete set is the event that allows the Operator
module to complete the X-ray stage and move the ticket to asynchronous AI
waiting.

The binary should be stored once. Downstream systems should exchange immutable
object references, checksums, identifiers, and status rather than repeatedly
copying the same clinical file between application servers.

## Processing coordination

- Every submitted radiograph NPZ must be processed with its matching gain NPZ.
- The Image Gateway worker builds a signed DICOM metadata manifest and invokes
  the private MPIPS conversion once for each capture.
- Successful capture results are preserved if a sibling capture fails.
- Only the failed capture is retried.
- A failed capture receives three total processing attempts.
- If it still fails, an administrator is notified.
- Email is the required initial notification channel.
- Telegram is a later enhancement.

The exact retry timing belongs to technical planning.

## Private MPIPS adapter

The separate `mpips` repository owns the exact black-box transport contract.
The Image Gateway worker supplies the patient-free radiograph and gain inputs
plus the separately signed manifest, then receives one DICOM result inside an
asynchronous job.

Image Gateway validates the result against the input checksums and frozen
manifest before permanent acceptance. It owns retry count and timing, reuses the
same conversion identity, and rejects a replay whose bytes or manifest differ.

## Completion rules

The examination image set is complete only when every submitted capture has
successfully produced DICOM.

Only then does Image Gateway:

- make the complete image set available to Member, Operator, and Doctor modules
  as authorised references; and
- start each selected result workflow.

A partially successful image set remains hidden from the member until the
examination is resolved.

## Permanent storage

Image Gateway owns long-term storage for:

- original NPZ files;
- matching gain NPZ files;
- generated DICOM files;
- checksums and object identity;
- processing and publication history; and
- report versions needed for traceability.

The approved policy is indefinite retention for audit and future reprocessing,
with no routine user deletion. Each organisation is isolated in a separate
storage namespace.

Only an authorised compliance administrator may delete or anonymise a record
when legally required. The action must be fully audited.

## Access and distribution

- Raw radiograph and gain NPZ are available only to the Image Gateway module
  and MPIPS.
- Member, Operator, and Doctor modules receive references rather than
  permanent file copies.
- Temporary authorised links protect image access, except the standard
  authenticated Operator raw-DICOM attachment download defined below.
- Members view images and export TIFF, JPG, or PDF; they do not download raw
  DICOM.
- An assigned Operator may explicitly download raw DICOM for an authorised
  active-site current-shift examination or an explicitly reopened repeat or
  correction case as a standard authenticated `.dcm` attachment. Operators
  never download raw NPZ.
- Authorised doctors may explicitly download raw DICOM when clinically
  necessary; the download is audit logged.

## AI and doctor routing

- Basic MPIPS processing applies to every examination.
- AI is requested only when selected by the booked service.
- Doctor review is requested only when selected.
- The AI provider is selected by application code, not by the member.
- A successful AI result becomes visible to the member automatically and emits
  one idempotent readiness event that automatically completes the matching Operator ticket.
- If AI processing fails, Image Gateway invokes the configured fallback. AI
  report delivery or terminal fallback failure updates Member publication and
  Operator ticket completion status but creates no Operator earning by itself.
- For a doctor-selected service, placing the DICOM study in the Doctor module
  dashboard queue starts review and creates no Operator earning.
- A Doctor module `repeat_required` decision starts a Member module repeat
  entitlement but does not authorize Image Gateway to create a repeat itself.
- A doctor-only repeat does not rerun AI. The original study and any successful
  original AI result remain unchanged.
- A submitted doctor report becomes visible automatically.
- AI and doctor outputs are independent and neither waits for the other.
- A doctor may see available AI output but may finish first.
- Corrected doctor reports preserve history and are redistributed as the new
  current version.

## Doctor replacement-study contract

The Image Gateway module preserves original and replacement studies as separate linked
records. When all captures for a doctor-requested repeat have produced the
replacement DICOM study, it emits one idempotent `ReplacementStudyReady`
domain event for the existing Doctor case.

The event identifies the Doctor case, Member repeat entitlement,
replacement `ServiceRequest`, `Appointment`, `Encounter`, and `ImagingStudy`,
the original study and order, occurrence time, and source version. Image
Gateway sends authorized references rather than another permanent file copy.

The same event ID and content return the original outcome. A changed replay,
unknown entitlement, broken original-study lineage, or non-doctor repeat fails
closed. The event is persisted with the source transition before queued
delivery.

The Doctor module returns the case to the requesting doctor when still
authorized or to the shared eligible queue otherwise. Image Gateway does not choose the
doctor, change queue ownership, calculate the doctor's 25% repeat-assessment
earning, or calculate Operator stage earnings.

## FHIR R5 boundary

The target interoperability contract uses HL7 FHIR R5 `5.0.0`. Image Gateway
maintains the authoritative mapping from DICOM studies to `ImagingStudy`, uses
`ImagingSelection` for selected instances or frames, carries AI findings as
profiled `Observation` or `DiagnosticReport` resources, and uses
`DocumentReference` only for clinical documents that are not represented by a
more specific resource. Applicable changes and access are represented with
`Provenance` and `AuditEvent` in addition to local immutable audit records.

Processing status, retry control, storage operations, notifications, and
payment-eligibility events remain ordinary module operations. Profiles,
mappings, validation, and verification fixtures remain to be specified.

## Does not own

The Image Gateway module does not own:

- member booking, service rules, or member payments;
- front-desk queues;
- operator earnings records;
- NPZ-to-DICOM algorithms;
- the doctor work queue;
- the clinical decision to request or cancel a repeat;
- repeat entitlement, member notification, or repeat scheduling;
- doctor earnings; or
- clinical approval of AI output.

## Open design decisions

Exact upload contracts, storage layout, idempotency, FHIR R5 mappings,
authorization, audit events, MPIPS error mapping, retry timing, deployment, and
verification fixtures remain to be specified.
