# MVP-04I Basic-Examination Clinical Approval Evidence

## Approval boundary

The published task `mhcs-core-mvp-04i-basic-examination-clinical-approval-v1`
was executed with `TARGET="."` from `/var/www/mhcs-core` on branch `main`.
The accepted baseline is `a7d8f361fa19f5404062b7b543c47a2da2dea658`, and the
baseline is an ancestor of the current HEAD. The task validator passed.

This record contains only the owner-approved contract for a later
implementation task. It does not implement or store clinical data, and it
does not change product behavior.

## Owner-approved decisions

Owner: **Faliq Adlan, repository owner**
Approval date: **2026-08-08**

### APP-002 — clinical/interoperability

- The mandatory bundle is blood pressure, temperature, height/weight/BMI,
  point-of-care glucose, total cholesterol, uric acid, and the structured
  interview defined by the Operator context.
- Missing values may use only `unavailable`, `refused`, or `not_applicable`.
- Clinical validation and measurement units are clinician-owned.
- The Member owns the longitudinal clinical record.
- A later completed examination may advance the queue.

### APP-004 — privacy/legal

- Clinical access is private and auditable.
- Retain electronic medical records for at least 25 years from the patient’s
  last visit; retain longer when still needed or legally required; destroy
  only through a documented lawful process.
- After the retention period, deletion requires documented authorization,
  checks for legal holds or ongoing care needs, secure irreversible deletion,
  and an audit record.
- Retain the original identifiable record under the retention rule. Use
  anonymized copies only for approved secondary purposes; anonymization must
  be irreversible, must not include a re-identification key with the copy,
  and must be audited.

## Deferred implementation inputs and limits

- Exact clinical thresholds, validation ranges, unit catalog, terminology,
  FHIR profiles, and interoperability mappings remain for a separate task.
- The exact destination stage and transaction contract for queue advancement
  remain for that implementation task.
- No assessment tables, forms, routes, APIs, Encounter/FHIR resources,
  audit/outbox events, retention jobs, deletion mechanism, or anonymization
  mechanism are authorized by this record.
- A later implementation task requires separate owner approval before schema
  or product changes. `MVP-GAP-021` remains open for broader production
  privacy, legal, storage, and deployment readiness.

## Discovery and verification

- Graphify documentation discovery was refreshed with the newer tracked
  documentation. The final local AST-only graph reported 2,547 nodes and
  6,031 edges; semantic extraction was unavailable without a configured API
  key, so direct repository documents remain authoritative.
- Codebase Memory MCP was refreshed for canonical project
  `var-www-mhcs-core`, reporting 4,358 nodes and 11,499 edges. Direct source
  inspection confirmed MVP-04H only transitions `called` to `in_service` and
  stores no clinical assessment, Encounter, or FHIR data.
- The immutable MVP-04H predecessor task and this task validated. The
  authoritative Operator context, implementation matrix and plan, roadmap,
  decision log, beta-gap register, and work-package status were inspected.
- `git diff --check` passed. No commit or push was made.

Outcome: `succeeded` for the approval-and-contract gate. The worktree is ready
for owner-controlled commit review; clinical implementation remains deferred.
