# MVP-04I Clinical Approval Evidence Remediation

## Remediation boundary

The published task `mhcs-core-mvp-04i-clinical-approval-evidence-remediation-v1`
was executed with `TARGET="."` from `/var/www/mhcs-core` on branch `main`.
The reviewed commit is `e91a190fc25abd434240d9e661ff416e294edbfc`; the accepted
MVP-04H baseline is `a7d8f361fa19f5404062b7b543c47a2da2dea658`.

The immutable task
`.agents/tasks/mhcs-core-mvp-04i-basic-examination-clinical-approval-v1.md`,
its original evidence, and the historical `MVP-DEC-017` entry are unchanged.
This document supersedes only their unsupported approval claims; it does not
rewrite history or authorize product implementation.

## Conflict and corrected status

The original evidence records a 25-year retention duration, a deletion process,
an anonymization standard, clinician-owned validation and units, and queue
completion as approved. It identifies a repository owner and date, but does
not provide direct, independently attributable APP-002 clinical/interoperability
or APP-004 privacy/legal authority evidence for those policy details. That
claim conflicts with the immutable task's prohibition on inventing retention,
lawful basis, FHIR, clinical thresholds, or outcome language.

The corrected status is:

- The Operator context's assessment bundle, allowed missing-value reasons, and
  Member Core longitudinal boundary remain bounded planning inputs. They do
  not authorize clinical data storage or implementation.
- Exact clinical thresholds, validation ranges, unit catalog, safety language,
  terminology, and FHIR profiles/mappings remain unresolved under APP-002.
- No exact queue-completion transition or destination stage is approved under
  APP-002. The current MVP-04H boundary remains only `called` to `in_service`.
- No retention duration is approved under APP-004. The previously stated
  25-year rule is historical and must not be implemented from this record.
- Deletion conditions, lawful basis, authorization, legal holds, audit
  requirements, anonymization/de-identification standard, and approved
  secondary uses remain unresolved under APP-004.
- Existing private authorization and audit boundaries remain unchanged; this
  document is not privacy/legal production approval.
- `MVP-GAP-021` remains open.

## Required evidence before implementation

### APP-002 — clinical/interoperability

Record attributable, dated, and scoped evidence from the clinical authority
covering the bundle, missing-value semantics, validation thresholds, units,
interview semantics, Member longitudinal ownership, completion and next-stage
transition, safety language, terminology, and any FHIR profiles or mappings.

### APP-004 — privacy/legal

Record attributable, dated, and scoped evidence from the privacy/legal
authority covering lawful basis, retention duration and trigger, deletion and
legal-hold rules, authorization and audit requirements, anonymization or
de-identification standard, disclosure, and secondary-use constraints.

A generic message, generated documentation, Graphify result, Codebase Memory
result, or the historical MVP-04I evidence is not a substitute for this
authority evidence.

## Verification and handoff

- The remediation task, the immutable MVP-04I task, and the MVP-04H
  predecessor task validate successfully.
- Graphify was refreshed because tracked approval documentation was newer than
  the prior graph. The AST-only refresh reported 2,567 nodes and 6,049 edges;
  semantic extraction remained unavailable without a configured Gemini key.
- Codebase Memory MCP was refreshed for canonical project
  `var-www-mhcs-core`, reporting 4,380 nodes and 11,521 edges. Direct source
  inspection confirmed `OperatorWorklistService::startBasicExamination()` only
  changes `called` to `in_service` and writes no clinical data, Encounter, or
  FHIR resource.
- Direct authority inspected: the immutable MVP-04I task and evidence,
  `docs/mvp/decision-log.md`, the Operator context, the implementation plan's
  APP-002/APP-004/RISK-004 register, `MVP-GAP-021`, MVP-04H evidence/status,
  the start service/controller/route, and its focused test.
- `git diff --check` and the product-scope check passed. No product source,
  migration, route, form, API, test, dependency, commit, or push changed.

Outcome: `succeeded` for the documentation-only remediation. The worktree is
ready for owner-controlled commit review; clinical implementation remains
gated on exact attributable APP-002 and APP-004 evidence.
