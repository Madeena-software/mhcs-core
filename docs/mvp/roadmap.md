# Controlled-beta roadmap

This is an implementation sequence, not a date plan. MVP-02 through MVP-09 are provisional and may be reprioritized by owner decision. Dependencies and evidence gates remain binding.

## MVP-00 — Pivot to Controlled Beta Delivery

Documentation and planning only. Establish the relationship between Work Packages and MVP tasks, the four-component boundary, the Doctor Portal exclusion, the gap register, decision log, and status ledger.

## MVP-01 — Member Access and Profile

First implementation target:

\`\`\`text
Member login
→ mandatory password replacement
→ profile completion
→ dashboard
→ logout
\`\`\`

Deliver focused ownership and authentication tests. Exclude account import, public/online registration, child/guardian workflows, and unrelated clinical or financial behavior.

Consumes the accepted WP-01 shared application foundation, WP-02 security/authentication foundations, and WP-04 User/Member, protected-identifier, audit, and ownership foundations. It must not reopen WP-04 wholesale.

## MVP-02 — Admin Portal Foundation

Deliver the minimum Admin Portal to view/manage approved account state; manage organizations and examination sites; manage Member and Operator assignments; configure later radiology-service operations; and view foundational audit information. It is not an unrestricted database editor.

## MVP-03 — Member Radiology Service Request

Deliver the Member-visible service catalogue, examination-site selection where applicable, examination request or booking, and Member request status.

## MVP-04 — Operator Queue and Attendance

Deliver Operator authentication/authorization, operational queue, scheduling, check-in, attendance and examination-state transitions, and operational audit evidence.

## MVP-05 — Image Gateway Study Intake and Correlation

Deliver the study intake or identification boundary, examination-to-study association, duplicate/mismatch handling, Image Gateway status visibility, and Operator/Admin failure visibility.

## MVP-06 — Operator Teleradiology Workflow

Deliver study routing/export status, external teleradiology tracking, retry/failure handling, controlled manual report upload as the beta fallback, automated report return only when a supported contract exists, and Operator review/publication controls.

## MVP-07 — Member Result Visibility

Deliver access to the Member's own published result, strict ownership, safe presentation, publication state, and viewing/download only through approved private-object boundaries where applicable.

## MVP-08 — B2B Account Import

Deliver separately controlled Member and Operator import; validation/rejection reports; idempotency; duplicate detection; temporary credentials; mandatory password replacement; and audit/secret-handling controls. Do not implement before MVP-01 unless the owner reprioritizes it.

## MVP-09 — Beta Hardening and Deployment Readiness

Deliver cross-MVP regression verification, operational runbook, beta monitoring, backup/restore evidence, migration approval resolution, critical gap review, and the controlled beta deployment decision. MVP completion and deployment readiness do not establish production readiness.

