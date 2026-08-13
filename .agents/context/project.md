# MHCS Core Architecture Specification

**Status:** Approved target architecture
**Last reviewed:** 30 July 2026

This document is the architecture authority for the MHCS application. Detailed
business rules remain in the module specifications linked below.

## Repository decision

MHCS uses exactly two product repositories:

1. `mhcs-core`: one modular application containing Member, Operator, Doctor,
   and Image Gateway modules; and
2. `mpips`: one private black-box NPZ-to-DICOM conversion service.

Member, Operator, Doctor, and Image Gateway are modules, not independently
deployed microservices. Their context is partitioned so an agent can load only
the module relevant to its task:

- [Member module](modules/member/project.md)
- [Operator module](modules/operator/project.md)
- [Doctor module](modules/doctor/project.md)
- [Image Gateway module](modules/image-gateway/project.md)
- [UI/UX Design System Export](design/mhcs-core-design.html) (Google Stitch Project: [2877959425967925287](https://stitch.withgoogle.com/projects/2877959425967925287))

The old five-repository deployment model is superseded.

## Why this boundary

Member, Operator, Doctor, and Image Gateway participate in one tightly coupled
clinical and financial workflow. Keeping them in one application allows local
authorization, database transactions, module calls, and domain events instead
of duplicated identities, network retries, and distributed state
reconciliation.

MPIPS remains separate because it has a different processing runtime and trust
boundary. It parses radiography inputs, performs conversion, and can require
independent CPU, memory, file, timeout, and process isolation. It receives no
MHCS business authority.

## Technology stack

The initial Composer constraints are PHP `^8.4`, Laravel `^13.8`, and
Filament `^5.0`.

## Target repository layout

The exact framework paths may follow the selected Laravel version, but the
logical structure is:

```text
mhcs-core/
  .agents/
    context/
      README.md
      project.md
      modules/
        member/project.md
        operator/project.md
        doctor/project.md
        image-gateway/project.md
  app/
    Modules/
      Member/
      Operator/
      Doctor/
      ImageGateway/
    Shared/
  database/
  tests/
    Member/
    Operator/
    Doctor/
    ImageGateway/
    Integration/
```

`Shared` contains only genuinely shared primitives such as identifiers, money,
clock abstractions, audit support, and domain-event infrastructure. Business
rules remain in their owning module; `Shared` must not become a miscellaneous
cross-module service layer.

## Runtime topology

`mhcs-core` is one deployable application that may run several processes from
the same source:

- web processes for member, operator, doctor, and administrator interfaces;
- queue workers for notifications, image orchestration, AI routing, and
  payouts; and
- a scheduler for retries, reconciliation, reminders, and daily doctor payout
  batches.

All processes use one authentication and authorization foundation, one
application database, one cache/queue foundation, and the Image Gateway
module's controlled object storage. Tables remain module-owned even when the
database enforces foreign keys across stable identifiers.

The private MPIPS conversion contract is the only internal network service
boundary. The `mhcs-core` image worker and MPIPS may join a private container
network, but
MPIPS is not published through the user-facing reverse proxy. Browser clients,
Member, Operator, and Doctor modules never call MPIPS directly.

## Module interaction rules

- A module changes only the records it owns.
- Cross-module synchronous work uses explicit application commands or queries,
  without network calls or module credentials.
- Cross-module asynchronous work uses versioned domain events persisted in the
  same database transaction as the source change.
- A queued handler is idempotent because delivery may repeat.
- User identity, session, role, site, and case authorization come from the
  shared authenticated application context.
- Module boundaries do not require duplicated user, site, booking, clinical,
  or payout records.
- External identifiers remain distinct from local primary keys even though the
  modules share one database.
- Payment gateways, AI providers, email providers, object storage, and MPIPS
  remain explicit external adapters.

The application may use one transaction for an approved cross-module invariant,
such as creating a repeat entitlement and its doctor earning event. Long-running
image conversion, AI work, notifications, and payouts remain asynchronous and
must never hold a database transaction open.

## Image Gateway module boundary

The Image Gateway module owns:

- durable plain-byte NPZ and DICOM object storage in a private, opaque-keyed
  store;
- object keys, checksums, immutable submission manifests, and retention;
- image-processing jobs, attempt counts, and final-failure status;
- construction and validation of the DICOM metadata manifest;
- the private MPIPS adapter;
- whole-examination completion;
- authorized image access and temporary links;
- AI and doctor routing; and
- publication and report-version distribution state.

Operator submits a capture through the public `mhcs-core` application. The
request durably persists the radiograph, gain, manifest, and signature to the
configured private store, then atomically accepts the complete source set and
queues MPIPS. Each successful component is immutable; a later same-admission
attempt uploads only a missing component. The Image Gateway queue worker is the
only MPIPS caller, and no application-server-to-application-server file copy or
internal network submission exists inside `mhcs-core`.

## MPIPS black-box contract

MPIPS has one MHCS responsibility: convert one radiograph capture into DICOM.
The Image Gateway worker supplies a patient-free radiograph NPZ, its matching
patient-free gain NPZ, and a separately signed DICOM metadata manifest. MPIPS
returns one DICOM result. The exact transport, authentication, idempotency,
success, and failure contract is owned by the separate `mpips` repository. This
context defines only the `mhcs-core` side of that boundary.

MPIPS is stateless from the MHCS business perspective. Temporary files are
removed after the response or bounded recovery window. MPIPS does not own
permanent storage, retries, member identity, FHIR authority, queues, AI,
publication, or payments.

## Conversion flow

```text
Operator module
  -> local complete-submission command
Image Gateway module
  -> capture intent
  -> durable private NPZ, manifest, and signature persistence
  -> atomic source acceptance and queued MPIPS job
MPIPS
  -> DICOM response
Image Gateway module
  -> validate checksum, DICOM identifiers, and frozen manifest
  -> store DICOM durably
  -> mark capture complete or retry up to the approved limit
  -> when the full set is complete, dispatch images, AI, and doctor work
```

Image Gateway owns retries and must reuse the same conversion job identity.
MPIPS must return the original result or an idempotent equivalent for the same
identity and input. Reusing an identity with different bytes or metadata fails
as a conflict.

## Constraints and hazards

- Direct SSH access to production and staging is prohibited. Do not attempt or
  recommend SSH-based troubleshooting.
- Represent infrastructure and server changes in version-controlled
  configuration, then apply them through the approved CI/CD pipeline.

## Deployment

The
[Madeena deployment-template repository](https://github.com/Madeena-software/deploy-templates)
is the external authority for environment-template implementation. Copy and
specialize the applicable templates in `mhcs-core`; do not duplicate their
implementation details in this context.

## Security boundary

- MPIPS accepts calls only from the `mhcs-core` image worker.
- MPIPS has no access to the MHCS application database, payment credentials, or
  user sessions.
- Input size, dimensions, file count, CPU, memory, execution time, and temporary
  storage are bounded.
- NPZ parsing occurs in an isolated process/container and must not execute
  untrusted pickle payloads.
- The manifest is signed and its checksum is bound to the conversion job.
- Logs contain correlation IDs and sanitized technical status, not NPZ
  contents, clinical payloads, tokens, or patient identifiers.
- The Image Gateway module validates the returned DICOM before permanent
  acceptance.

## Extraction rule

A module may become a network service later only when measured operational
needs require independent deployment, scaling, failure isolation,
regulatory isolation, or team ownership. Repository size, role-specific user
interfaces, or speculative future growth alone are not sufficient reasons.
