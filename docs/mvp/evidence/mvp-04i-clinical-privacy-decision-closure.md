# MVP-04I Clinical and Privacy Decision Closure

## Decision boundary

The published task `mhcs-core-mvp-04i-clinical-privacy-decision-closure-v1`
was executed with `TARGET="."` from `/var/www/mhcs-core` on `main` against
accepted baseline `2114add25e948f535240441f490936d750cacb68`.

This record captures the exact bounded APP-002 and APP-004 decisions supplied
for a later MVP-04 basic-examination implementation task. It does not
implement product behavior, clinical persistence, FHIR exchange, retention,
deletion, anonymization, or access mechanisms. The immutable approval task,
the original evidence, and the remediation record remain unchanged.

## Attributable approval

The approval owner is **Faliq Adlan, IT Manager**, dated **2026-08-08**.
Faliq Adlan was explicitly authorized to approve clinical policy and
privacy/legal policy for MHCS. The decisions below are scoped to the final
MVP-04 basic-examination/vital-signs assessment slice.

## APP-002 — clinical and interoperability contract

| Decision | Approved contract |
|---|---|
| Clinical authority | Clinical authority is clinician-owned. The Operator-module performer is a nurse. Numeric validation thresholds/ranges are not defined by this record and require the clinical implementation task to use attributable clinical evidence. |
| Mandatory assessment scope | Only blood pressure, temperature, weight, height, and BMI are mandatory for MVP-04 completion. Glucose, total cholesterol, uric acid, and the structured interview are not mandatory. Structured interview mandatory status is **no**. |
| Units | Blood pressure: `mmHg`; temperature: `°C`; height: `cm`; weight: `kg`; BMI: `kg/m²`; glucose: `mg/dL`; total cholesterol: `mg/dL`; uric acid: `mg/dL`. |
| Missing-value semantics | Each mandatory field is complete when it has a value or one allowed missing reason: `unavailable`, `refused`, or `not_applicable`. |
| Safety/clinical copy | `screening result; not a diagnosis`. |
| Completion and queue destination | Mark the assessment complete only when blood pressure, temperature, weight, height, and BMI each have a value or an allowed missing reason; then advance the same ticket to the X-ray stage. |
| Longitudinal ownership | The Member owns the longitudinal record. |
| FHIR/interoperability scope | No FHIR resource, profile, mapping, or exchange artifact is in the final MVP-04 slice. Future local data design must remain compatible with the FHIR standard, but this record makes no FHIR-conformance or interoperability claim. |

The proposed hard-paper interview photograph/upload workflow is not part of
the mandatory completion contract and is not authorized or implemented by this
record; it requires a separate bounded implementation decision if needed.

## APP-004 — privacy and legal contract

| Decision | Approved contract |
|---|---|
| Lawful basis | Explicit patient consent to the examination and clinical record. |
| Retention | Retain for 25 years from the patient’s last visit. |
| Clinical-content access | Doctors, healthcare workers, Madeena C-level executives (including Faliq Adlan), and delegates from Kemenkes or a partner. Access is allowed only for an authorized, documented purpose; no general access. |
| Audit-record access | Doctors, Madeena C-level executives, and authorized C-level executives of an approved Madeena partner. |
| Deletion | After 25 years, securely and irreversibly delete the record only with documented authorization, and record the deletion in the audit trail. |
| Legal holds | Suspend deletion while a legal, investigation, regulatory, or ongoing-care hold exists. |
| Anonymization/de-identification | Retain the original identifiable record under the retention rule. Use anonymized copies only for approved secondary purposes. Anonymization must be irreversible, with no re-identification key stored with the anonymized copy, and the action audited. |
| Secondary use/disclosure | External secondary use must use irreversible anonymized data. Identifiable disclosure requires documented authorization and audit. |

This record does not grant broad external access. The approved audience and
purpose limits remain bounded by the documented authorization and audit rules
above.

## Status and residual risk

The APP-002 and APP-004 decision gate is closed for the bounded decisions
listed above. This is not production privacy/legal approval or implementation
approval. `MVP-GAP-021` remains open for production operationalization,
verification, and any privacy, legal, disclosure, retention, deletion, or
anonymization scope not explicitly covered here. MVP-04H remains limited to
the existing `called` to `in_service` boundary until a separate implementation
task is executed.

## Verification and handoff

- The current closure task, its immutable MVP-04I predecessor, the remediation
  task, and the relevant MVP-04H predecessor validate successfully.
- Direct authority files and prior remediation evidence were inspected before
  recording this decision.
- Graphify was queried for the MVP-04I documentation relationship and the
  current Operator start boundary. Codebase Memory MCP was queried for the
  current `startBasicExamination` service/controller and Operator architecture.
- No product source, migration, route, form, API, test, dependency, commit, or
  push changed.
