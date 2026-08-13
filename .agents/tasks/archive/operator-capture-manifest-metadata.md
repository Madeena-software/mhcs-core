---
title: Add Operator Capture Metadata to the MPIPS Manifest
document_id: MHCS-TASK-OPERATOR-CAPTURE-METADATA-001
version: 1.0
status: validated
language: en-US
last_updated: 2026-08-13
scope:
  - operator capture-metadata form
  - immutable metadata persistence for an NPZ capture set
  - MPIPS minimal-manifest enrichment
authority_note: This validated task is executable only when its exact immutable commit revision is supplied to the Executor.
---

# Executable Task

## Task identity

**Task title:**
`Add Operator Capture Metadata to the MPIPS Manifest`

**Task path:**
`.agents/tasks/operator-capture-manifest-metadata.md`

**Task contract state:**
`Validated/Published — the governing immutable task revision is supplied in the Planner publication handoff after this exact content is committed.`

**Delivery objective / Work Package / MVP:**
`Pre-deployment local MVP — enable an Operator to supply the clinical capture metadata used in each new MPIPS DICOM conversion.`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The accepted awaiting-AI remediation established `f96d5151419b55bf41e9acc44844c300bdcd6e0a` as the current baseline. The next user-approved feedback item is a metadata form on the Operator NPZ-pair submission page. Its selected values must become the signed MPIPS minimal manifest, rather than leaving the current server-only defaults for `study_description` and the capture block.

The Operator enters metadata once for a new capture set. It is durably frozen before manifest creation, so a component-only retry, browser refresh, or later queue attempt cannot alter the DICOM metadata paired with already-stored NPZ bytes. This is a narrow Image Gateway persistence and existing Operator capture-form change; it does not change the asynchronous MPIPS workflow, its source uploads, or DICOM access.

## Baseline and task revision

**Implementation baseline:**
`f96d5151419b55bf41e9acc44844c300bdcd6e0a` — accepted implementation of `.agents/tasks/operator-awaiting-ai-release-and-safe-navigation.md @ d76086c6bb04007c53fd06886e48cd5e2e95b7f3`.

**Task revision:**
`Provided by the Planner publication handoff after this exact validated content is committed.`

## Objective

Allow an authorised current-shift Operator to complete and submit a required, Indonesian capture-metadata form with an NPZ pair for a new X-ray admission; persist it immutably and construct the signed MPIPS manifest with those exact values.

## Authoritative inputs

### Governing authority

- CTO user feedback on 2026-08-13: the Operator NPZ-pair page must collect `examination.study_description`, `capture.detector_type`, `capture.body_part_examined`, `capture.laterality`, and `capture.projection`; the body-part list is a curated list.
- `docs/mpips/mhcs-dicom-api.md` §§3–4 and §9 — the MPIPS minimal client manifest accepts those fields; MPIPS accepts `BED`, `THORAX`, and `TRX`, and validates a supplied detector against the NPZ/calibration metadata.
- DICOM PS3.16 Annex L — DICOM Body Part Examined defined terms; the approved scope below is the selected human projection-radiography subset, not the entire cross-modality catalogue.
- DICOM PS3.3 DX Anatomy Imaged Module — the DICOM laterality and view-position concepts used by this capture metadata.
- `.agents/context/project.md` — Image Gateway owns manifest construction, signed immutable manifest objects, capture sets, and the only MPIPS call from the worker.
- `.agents/context/modules/operator/project.md` — capture submission is an authorised current-site/current-shift clinical action; returned DICOM remains read-only and protected.
- `.agents/context/modules/image-gateway/project.md` — source acceptance and manifest persistence precede queued MPIPS processing.
- `.agents/context/ui-language.md` and `docs/mvp/decision-log.md` MVP-DEC-037 — every MHCS-authored visible string is retrieved from `lang/id.json`.

### Requirement traceability

- `OPR-040` and `OPR-043` → authorised X-ray capture submission and durable accepted-source transition.
- `OPR-060` → downstream DICOM metadata and result access remain governed by Image Gateway.
- `OPR-108` → site, shift, claim, and capture submission remain server enforced.
- `UIL-001` and MVP-DEC-037 → Indonesian JSON-backed form copy, labels, option labels, instructions, and validation text.

## Scope

### In scope

- Add required fields to the existing Operator NPZ-pair form, before its file inputs, for a **new** capture set:
  - `examination.study_description`: text input, default `CHEST RADIOGRAPH`, required, trimmed, maximum 64 characters;
  - `capture.detector_type`: select with exactly `BED` and `TRX`, no `THORAX` option, required, initially unselected;
  - `capture.body_part_examined`: required searchable native combobox (an input backed by a datalist, not a dependency) containing exactly `ABDOMEN`, `ANKLE`, `CHEST`, `CLAVICLE`, `CSPINE`, `CTSPINE`, `ELBOW`, `FEMUR`, `FOOT`, `HAND`, `HIP`, `HUMERUS`, `KNEE`, `LSPINE`, `PELVIS`, `RIB`, `SCAPULA`, `SHOULDER`, `SKULL`, `TSPINE`, and `WRIST`; default `CHEST`;
  - `capture.laterality`: required searchable native combobox containing exactly `R`, `L`, `U`, and `B`; default `U`;
  - `capture.projection`: required searchable native combobox containing exactly `AP`, `PA`, `LL`, `RL`, `RLD`, `LLD`, `RLO`, and `LLO`; default `PA`.
- Add only the minimum new nullable JSON capture-set column and migration required to store the five validated metadata values for a newly created capture set. Store the structured data as the two exact manifest sections, `examination` and `capture`; do not store it in browser session, an object key, a query string, or a separate table.
- Validate every field server-side in the existing capture controller/service before a new capture set, private object, manifest/signature, outbox event, or MPIPS job can be created. Reject absent, overlong, tampered, or out-of-enum values with Indonesian JSON-backed errors and preserve entered form input.
- Read the frozen metadata from the capture set when constructing the existing signed manifest. The resulting stored `manifest` object must contain the exact submitted `examination.study_description`, `capture.detector_type`, `capture.body_part_examined`, `capture.laterality`, and `capture.projection`, together with the existing server-owned patient, operator, site, identifiers, timestamps, checksums, and signature.
- On a component-only retry of a capture set created by this task, show the stored metadata as read-only/frozen and use it without accepting a replacement from the request. A client that supplies different metadata for that existing capture set must not change the database row, signed manifest, source checksums, job identity, or queued work.
- For a pre-existing capture set whose metadata column is null, preserve the existing retry and manifest behavior. Do not rebuild its existing manifest or invent retrospective metadata.
- Register every newly visible label, option label, instruction, accessibility text, and validation/error message in `lang/id.json`. The manifest code values remain exact technical values; their Indonesian display labels must be resolved through the existing translation helper.
- Add focused tests in the existing Image Gateway integration and Operator capture-form test locations; add a migration-schema assertion only if that is the established local test convention.

### Out of scope

- Changes to MPIPS, its API contract, detector calibration, gain selection, detector inference, NPZ parsing, or live MPIPS requests.
- Adding `THORAX` to the Operator UI enum, even though MPIPS accepts it.
- A comprehensive DICOM anatomy catalogue, protocol administration redesign, body-part/laterality compatibility engine, free-text enum extension, or new configuration interface.
- Changes to the accepted await-AI claim release, status polling, worklist refresh, worker topology, storage backend, private-object encryption policy, upload concurrency, retries, DICOM viewer, result list/display references, or raw DICOM download policy.
- Historic backfill, rewrite, or deletion of capture metadata, existing manifest/signature objects, NPZ, DICOM, queue jobs, or studies.
- Deployment, data reset/reseed, service control, production mutation, AWS/S3 access, real MPIPS calls, or release.

### Preserved behavior

- The existing capture authorisation, active-site/current-shift checks, capture claim rules, durable source writes, immutable manifest signature, component-only NPZ retry, asynchronous `ProcessCaptureSet` dispatch, and MPIPS idempotency remain unchanged.
- A new capture still uploads exactly one radiograph NPZ and one matching gain NPZ; the browser never calls MPIPS directly.
- The MPIPS worker reads the already persisted manifest object only. It does not depend on browser form state or modify operator-supplied metadata.
- Existing DICOM read-only viewer, standard attachment download, active-site/current-shift result access, and raw-NPZ denial remain unchanged.
- No visible UI copy is hard-coded in English in Blade, controller, validation, or client-side JavaScript.

## Dependencies and assumptions

### Dependencies

- `f96d5151419b55bf41e9acc44844c300bdcd6e0a` is the accepted implementation baseline.
- `docs/mpips/mhcs-dicom-api.md` contract version 1.2 remains available and its current minimal-manifest names remain unchanged.
- The existing `image_gateway_capture_sets` row is created before `ensureManifest()` and already owns immutable manifest/signature persistence.

### Approved assumptions

- The approved body-part list is intentionally a limited adult human projection-radiography list. It is not a claim that every DICOM Body Part Examined term is supported by the available detector or by current MHCS services.
- `BED` and `TRX` are the only Operator-selectable detector modes. MPIPS's existing NPZ/calibration validation remains the authority that can reject a technically incompatible chosen detector.
- The form defaults are convenience values, not a bypass: `study_description`, detector type, and all enum values remain required server-side.
- The current local-MVP data is disposable, so a null metadata column on an old incomplete capture is preserved rather than backfilled or converted.

### Remaining approval requirements

- None beyond this task's authority for repository changes and fake-backed local verification. Commit, deployment, local reset/reseed, service control, live MPIPS/S3/AWS use, external mutation, and release remain unauthorised.

## Required capabilities

- Repository read/write; shell; PHP/Laravel migration and focused test execution; frontend build and formatting tools.
- No external credential, AWS/S3, live MPIPS, production, deployment, or private clinical-file capability is required or authorised.

## Execution constraints

- Reuse the existing `ImageGatewayController`, `ImageGatewayCaptureService`, capture-set table, immutable manifest/signature mechanism, Blade form, Laravel validation, translation registry, and focused Feature tests. Do not introduce a package, new worker, new queue, new endpoint, new persistence service, or a generic metadata/configuration framework.
- Persist the validated metadata in the same initial capture-set creation path as the submission identity. A retry must read the original stored values; it must not derive them from fresh request input or a later service-offering name.
- Keep the metadata column nullable exclusively for compatibility with prior capture rows. A new capture must not be created without complete validated metadata.
- Do not expose stored metadata through an unauthorised route, logs, object keys, query parameters, public link, raw NPZ action, or DICOM download name.
- Keep the manifest's existing deterministic ordering, signing, checksums, source/object validation, and MPIPS retry identity intact. Do not submit the resolved MPIPS manifest model or client-derived DICOM identifiers.
- Use native HTML controls and the existing Blade/JavaScript page behavior. Body part, laterality, and projection are searchable native comboboxes; the two-value detector control remains an ordinary select. Do not add a UI package or a custom client-side state layer.

## Acceptance criteria

- [ ] An authorised Operator opening a new capture sees the five required Indonesian-labelled metadata controls before the NPZ inputs. The body-part, laterality, and projection controls are searchable and start at `CHEST`, `U`, and `PA`; all controls submit only the approved enum values and use the specified defaults; `BED` and `TRX` are selectable, while `THORAX` is not.
- [ ] A valid new submission durably stores the structured metadata once and the persisted signed manifest contains the exact submitted study description, detector type, body part, laterality, and projection. The normal NPZ pair, source acceptance, queue dispatch, and queued MPIPS processing still occur exactly once.
- [ ] Missing, too-long, or tampered metadata is rejected server-side before any capture set, private object, manifest/signature, audit/outbox effect, or queue dispatch is created. Errors and retained form values are Indonesian JSON-backed.
- [ ] A component-only retry for a new incomplete capture presents the original metadata as frozen and uses those values. A forged replacement request cannot change the capture metadata, signed manifest bytes/checksum/signature, source checksums, or job identity.
- [ ] A pre-task capture row with null metadata retains its current retry/manifest behavior without a migration backfill or manifest rewrite.
- [ ] The DICOM returned by the existing fake-backed MPIPS test path remains authorised, read-only, and downloadable under the unchanged current-site/current-shift policy.
- [ ] No new public object access, raw NPZ access, MPIPS browser call, S3/AWS call, viewer behavior, worker, package, or unrelated protocol-management behavior is introduced.

## Verification requirements

- Add focused Feature coverage proving valid form defaults/rendering and every exact enum boundary, including rejection of `THORAX` and invalid body part/laterality/projection values.
- Add focused Image Gateway integration coverage that decodes the persisted manifest object and proves exact supplied metadata, immutable capture-set data, signature/checksum persistence, and one worker dispatch.
- Add retry coverage proving original metadata is retained after a partial source write and a changed retry payload is non-mutating; cover a null-metadata legacy capture without backfill/rewrite.
- Run the changed focused Feature tests, `vendor/bin/phpunit`, `npm run build`, `vendor/bin/pint --test`, and `git diff --check`. Do not call MPIPS, S3, AWS, or inspect/copy real NPZ/DICOM files.
- The Executor must report the immutable implementation revision, commands actually run, observed results, tests changed, migration result, known gaps, and any blocker. Local checks must not be represented as deployment or live-integration evidence.

## Stop conditions

- Stop if the current MPIPS contract rejects one of the approved emitted values or requires a new detector/body-part/projection decision beyond this task.
- Stop if immutable retry semantics cannot be achieved with the existing capture set and one bounded nullable metadata column, or would require rewriting a pre-existing signed manifest.
- Stop if the change requires a public/raw-private object route, browser-to-MPIPS call, live external system call, storage-policy change, or new worker/queue topology.
- Stop if an unrelated unaccepted implementation revision changes the declared baseline or overlapping capture/manifest behavior before execution begins.

## Side-effect authorization

### Explicitly authorised side effects

- Repository changes within scope: one capture-set migration, existing application/controller/service/view code, `lang/id.json`, and focused tests.
- Local fake-backed migration and test/build/format/diff commands only.

Not authorised: Git commit, push, pull request, deployment, local data reset/reseed, production or external-service mutation, real MPIPS/S3/AWS calls, credential access/disclosure, dependency installation, or raw clinical-file inspection/copying.

## Expected terminal outcome

`REVIEW REQUIRED` — return one immutable implementation revision with concise redacted evidence. The Planner/Reviewer will determine acceptance before a local rehearsal or subsequent display-reference/DICOM-viewer work.
