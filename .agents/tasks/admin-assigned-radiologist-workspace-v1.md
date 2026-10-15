---
title: Admin-assigned Radiologist Workspace v1 through internal clinical finalization
document_id: AGENT-TASK-RADIOLOGIST-V1-001
version: 0.1
status: Draft
language: en-US
last_updated: 2026-10-15
authority_note: The Product Owner approves the six product outcomes and exclusions recorded below. This Draft does not approve missing clinical, professional-eligibility or security policy, authorize implementation, or establish T5 acceptance.
---

# Admin-assigned Radiologist Workspace v1

## Task identity

**Task path:** `.agents/tasks/admin-assigned-radiologist-workspace-v1.md`

**Task contract state:** **Draft — T5 BLOCKED; not executable or published.**

**Delivery objective / Work Package / MVP:** Radiologist v1, one vertical journey through internal clinical finalization; bounded parts of WP-18, WP-19, WP-20 and WP-22. No whole-Work-Package completion claim.

**Owner / designated planning authority:** Product Owner and independent Planner–Reviewer. Doctor owns clinical workflow; clinical/professional and security/privacy authorities must resolve the decisions below. This document does not appoint or impersonate those authorities.

## Delivery context and objective

Deliver Admin assignment of an eligible individually authenticated Radiologist to an exact current Radiographer handoff, temporary case access, protected original DICOM review, an explicit clinical quality decision, controlled Doctor-authored report drafts and explicit internal finalization with fresh authentication, immutable history and attributable authorship. Integrate finalization with the existing Doctor guard so technical reopen cannot change a clinically finalized case.

All development and verification use isolated synthetic data. Technical Radiographer QC is not a clinical quality decision. AI output is not a Doctor-authored report. Clinical finalization is a distinct state from ingestion, AI processing, screening completion and Member publication.

## Baseline and task revision

**Implementation baseline:** `0f27d2b58c804183b907e236aa9735f5fac876ac` in `Madeena-software/mhcs-core`, supplied by the Product Owner as the immutable accepted Radiographer implementation baseline.

**Planning branch:** `task/radiologist-workspace-development-contract`.

**Task revision:** unresolved while Draft; no publication SHA exists. After decisions and contract validation, identify this stable path at the full immutable commit containing its published content, with observed remote branch equality. The publication revision and implementation baseline are different references. Do not silently advance either.

## Authoritative inputs

### Governing authority

- [Repository delivery contract](../AGENTS.md) and [software workflow](../software-workflow.md), especially §§9.4 and 15.1–15.7. [Existing task template](./_template.md) supplies the contract structure.
- Product Owner handoff **“MHCS — Radiologist v1 T5 Publication”**, supplied in this session on 2026-10-15: six approved outcomes, explicit exclusions, synthetic-only restriction, task-only ordinary commit/push authorization, independent T5 review and mandatory stop before E6. This section records its scope without elevating any technical proposal to approval.
- [MVP-DEC-046](../../docs/mvp/decision-log.md): individual assigned radiologists and separate clinical/publication states; product direction, not approval of clinical/security mechanisms.
- [Requirements matrix](../../docs/implementation/mhcs-core-requirements-matrix.md), [source coverage](../../docs/implementation/mhcs-core-source-coverage.md) and [implementation plan](../../docs/implementation/mhcs-core-implementation-plan.md). Their old implementation-status columns are historical, not evidence of this baseline. DEC-004 and WP-18/WP-22 explicitly retain report/signature and credential dependencies.
- [Radiographer contract](./admin-issued-radiographer-workspace.md) at `25d396e576ee513bee1a7e2aa2db57a65afdd666` defines the upstream handoff. Its B1/B2 professional and checklist approval is specific to Radiographers and technical QC; its temporary-access durations and B3 security approval are not automatically Radiologist policy.
- [Radiographer R8 evidence](../../docs/mvp/evidence/radiographer-workspace-r8-remediation.md) describes baseline implementation evidence and limits. It ends Review Required; the current Product Owner supplies the accepted baseline above. This task does not retrospectively issue an independent A9 verdict for that predecessor.

### Requirement traceability and ownership

All obligations below are planned, not implemented or verified. Partial coverage and exclusions are deliberate under this Product Owner handoff; older combined requirements are not claimed closed.

| Source / IDs | Bounded obligation | Owner | Acceptance / verification |
|---|---|---|---|
| PO outcomes 1–2; DOC-001, bounded DOC-002, DOC-043, DOC-054 | Exact current handoff assignment, professional/service/modality eligibility, independent action authorization and temporary individual access | Doctor; Shared authentication; Admin presentation | AC1–AC3; credential, assignment and access matrix; blocked by B1/B3 |
| PO outcome 3; DOC-041–043, DOC-053, DOC-055, DOC-057–058 | Original study and patient association, private case/purpose-scoped authorized DICOM review, stale/unknown rejection | Gateway imaging verification; Member identity; Doctor clinical authorization | AC3–AC4; direct/converted synthetic DICOM, disclosure and sentinel tests |
| PO outcome 4; DOC-006–013, DOC-021, DOC-047 | Explicit usable/repeat_required decision, immutable decision history; repeat_pending preserves draft and blocks final submission | Doctor | AC5; state, reason and conflict tests |
| PO outcomes 5–6; DOC-020–022 (finalization only), DOC-024, bounded DOC-050, DOC-056, DOC-059 | Draft revisions, internal finalization, authorship, immutable final report and fresh authentication | Doctor; Shared security/audit | AC6–AC8; blocked by B2/B3 |
| PO outcome 6; existing ClinicalStatusSource / ClinicalReopenGuard contracts; DOC-058 | Authoritative complete coordinated clinical status and finalization/reopen exclusion | Doctor with Operator handoff owner | AC8; independent-connection race and failure tests |
| DOC-060–064; approved exclusions | Preserve Member, Operator and Gateway ownership; no hidden publication, AI or financial side effects | All affected owners | AC9–AC10; regression and no-side-effect assertions |

DOC-008–012 supply controlled repeat reasons: `operator_error`, `equipment_failure`, `incorrect_order`, `medical_limitation`, `other`; `other` requires explanation. They do not authorize automatic attribution of blame or retake dispatch. DOC-003–005 shared queue prioritization is outside this individually assigned journey. DOC-014–019, DOC-023, DOC-025–028, DOC-034–040, DOC-044–045, DOC-048–049 and DOC-051–052 remain outside v1: no repeat entitlement/dispatch, Member publication, amendments, AI content or earnings/payout. Bounded DOC-022 finalization does not trigger its earning component. No FHIR profile/signature conformance is claimed.

## Scope

### In scope after legitimate validation and subsequent execution handoff

- Admin issue, revoke and reassign for a named individual and exact current handoff, with separate authorization from monitoring access.
- Temporary authenticated case workspace and protected original DICOM rendering; minimum necessary patient/examination context through owner interfaces.
- Doctor-owned explicit quality decisions, versioned report drafts and internal finalization, with source/handoff/assignment bindings and attributable immutable history.
- Additive persistence, domain commands/queries, routes/UI, authentication integration, audit and synthetic verification necessary for this one objective. Exact file discovery belongs to future execution; it cannot broaden authority.
- Doctor clinical-status adapter and coordinated writers sufficient to satisfy the existing guard without weakening unknown/finalized denial.

### Out of scope

Member publication, result links, notifications; AI report/content access; repeat dispatch, replacement workflow or entitlements; earnings, payment and payout; amendments; externally certified signatures; production deployment and real-patient operations. Also excluded: shared clinical accounts, broad Doctor portal/queue redesign, identity or licensing provider procurement, new permission defaults, original DICOM rewriting, multi-instance PACS expansion, unrelated refactoring, dependency installation and production backfills.

### Preserved behavior

Preserve original bytes/UIDs/checksums, signed source manifests, Radiographer QC/history/handoff, Member identity/consent/booking, Operator capture and direct Grabber/manual NPZ conversion, private storage and independent AI dispatch. Workspace grants must not expand legacy Operator download/AI privileges. Preserve Admin worklist filtering/pagination and minimal monitoring disclosure. Retain existing unknown-status reopen denial and immutable observations. No synthetic verification may touch operational databases, objects, credentials or patients.

## Dependencies, assumptions and remaining decisions

### Observed implementation dependencies

Read-only inspection at the baseline establishes:

- `PendingRadiographerHandoffRead::isCurrent` requires a transaction, locks the Operator workspace and checks exact active handoff/cycle/binding. Its contract requires checking before the Doctor guard lock. It is a version check, not patient identity or Doctor authorization.
- `RadiographerWorkspace::handoff` persists an immutable snapshot of actual imaging identity, protocol, overlay/QC, case/site, generation and revision. Reopen supersedes current eligibility; later consumers must recheck it.
- `ClinicalStatusSource::readWhileLocked` requires coordinated reporting writers under the same case lock and complete current reporting-source coverage. `DoctorServiceProvider` currently binds it to `UnavailableClinicalStatusSource`.
- `ClinicalFinalizationGuard` rejects unverified, stale, incomplete, mismatched, uncoordinated or unknown evidence. Its registry row alone cannot prove absence of a final report. Existing freshness ceiling is 30 seconds and does not replace transaction coordination.
- The R8 `WorkspaceResponse` checks authorization and commits audit immediately before response disclosure. Reuse this ordering principle under independently authorized Doctor context; do not reuse a Radiographer grant as Doctor authority. The audit records authorization/start, not client receipt. Already authorized output in flight cannot be recalled.

These observations constrain compatibility, not professional/security approval. Necessary owner-query extensions must preserve module ownership; direct cross-owner table mutation is prohibited.

### Material blockers — required before T5 publication

| ID | Precise unresolved decision | Required owner / authority | Resumption evidence |
|---|---|---|---|
| B1 — professional eligibility | Approve the Radiologist eligibility mechanism: authoritative credential/qualification source or trusted verification process, who may attest/change/revoke it, required evidence/status/validity and configured service/modality/facility scope. Define trusted synthetic representations and revalidation on changed credentials. An active User, generic Doctor role, Admin assignment or Radiographer verification cannot establish this. | Clinical/professional authority, Doctor owner and authorized security/Product authority | Attributable scoped approval and testable valid/invalid/expired/revoked/unknown criteria for DOC-001/043/054; no invented licensing or specialty rule |
| B2 — clinical report and internal finalization | Approve the minimum report content/required fields and validation, authorship/attestation meaning for internal finalization, finalization completeness conditions beyond usable, and treatment of an earlier doctor's draft on reassignment. Specify whether and how the new assignee can inspect/adopt it while preserving attribution. Excluding certified signatures does not itself decide internal attestation/report structure. | Clinical authority with Doctor owner and Product Owner; security/privacy for attribution/access | Scoped resolution of DEC-004's applicable report/attestation portion, with observable valid/incomplete examples and draft-transfer behavior; excluded financial/publication portions need no resolution here |
| B3 — temporary access and fresh authentication | Approve Radiologist workspace lifetime/default/maximum/renewal and post-finalization access; permitted assignment/revocation/reassignment capabilities and scope; fresh-authentication mechanism/recency and binding to finalizing actor/session/report revision, including replay, logout and account-change invalidation. Define sensitive Admin step-up applicability under DOC-059. No duration, MFA requirement or broad permission grant is selected by this Draft. | Security/privacy and Doctor technical owner with Product Owner | Attributable scoped policy, explicit boundaries and deterministic expiry/stale-proof/failed-auth test cases; do not borrow Radiographer 24/72/168-hour policy without approval |

The approved six product outcomes do not supply these mechanisms. Synthetic-only operation removes real-data exposure from verification but does not remove these development acceptance decisions. No proposal here is an approved assumption. If an existing approved artifact resolves a blocker, cite and reconcile that exact authority instead of seeking duplicate approval.

### Approved assumptions and later obligations

The supplied baseline and six product outcomes may be used; no real professional qualification, institutional approval or production readiness is inferred. Owner fixtures may be synthetic only after their governing mechanism is settled. Clinical usability remains an explicit human decision; technical Layak never substitutes for it.

Operational audit retention/access/holds, legal/institutional clinical obligations, display assurance, production credential verification, migration/backup/rollback and deployment security require their designated owners before affected real-data operation/G10. They are not waived or claimed complete. They need not expand this synthetic slice unless they materially affect development behavior or acceptance.

Independent Planner–Reviewer review of the exact published revision is mandatory before any E6 handoff under the current instruction. This session must stop even if publication later becomes possible. Publication authority is not source-change or release authority.

## Required capabilities

Future execution needs repository read/write, installed PHP/Node/build/test tooling, disposable synthetic DB/private storage, browser rendering and supported MySQL independent-connection concurrency verification. Required unavailable capability is a reported gap, not a passed check. No production secret, real AI/MPIPS call or external communication is required.

## Execution constraints

- Doctor owns eligibility/assignments, clinical quality, reports and finalization; Admin is a presentation surface. Operator owns technical handoff/currentness; Gateway owns original-image verification/storage; Member owns patient/examination identity. Preserve actor and clinical purpose across all owner calls.
- Resolve trusted exact case/site/source/handoff on the server. Freshly authorize account, individual, current eligibility, scope, assignment generation and permitted action at each route and owner boundary. A signed URL/opaque ID is a locator, never clinical authority. Withhold unknown identity, eligibility or currentness.
- Original DICOM requires verified patient association and actual embedded Study/Series/SOP/file-meta identifiers, byte checksum and current source binding. Alias columns, browser tags or a successful earlier Radiographer session do not independently authorize Doctor disclosure. Do not repair conflicting source bytes or silently accept fallback identity.
- Protect HTML/JSON/Livewire/DICOM and every report endpoint. Revalidate at the disclosure ordering point, including prepared replies after revoke/reassign, HEAD/range/cache paths. Durably commit required audit before protected disclosure; no successful fallback if append/commit fails. No locks across network writes. Enumerate all routes and direct service bypass paths in evidence.
- Persist source/handoff/generation and expected revision on quality, draft and finalization commands. Reject stale/conflicting writes; preserve earlier actors/content/history. Authenticated idempotent retries cannot bypass current authorization or create duplicate finalizations. Concurrent reassignment, handoff supersession and technical reopen cannot leave an apparently current clinical result attached to an obsolete handoff.
- Lock ordering must respect Operator current-handoff check before the Doctor case guard; every relevant clinical writer uses coordinated serialization. Final report, attributable history, current clinical status and required audit commit together. Rollback on failure; no absence-of-report heuristic or fixture source in production bindings. Incomplete reporting-source coverage remains unknown.
- Audit assignment/revoke/reassign, protected reads, quality changes, draft revisions, finalization and denials with trusted actor/purpose/case/revision/correlation and minimized outcomes. Store clinical text in access-controlled clinical history, never ordinary logs or audit payloads. No credentials, auth proofs, signed URLs, object keys, raw patient identifiers or DICOM headers in logs/audit.
- Reuse repository authorization/context, private viewer, verified imaging, clock, audit and transaction mechanisms where adequate. New dependencies, infrastructure or parallel frameworks require separate authority. Do not implement policy by choosing convenient defaults for B1–B3.

## Acceptance criteria

The following define the approved outcome and necessary integrity checks. Criteria referencing B1–B3 cannot be finalized or claimed executable until those decisions are incorporated and independently reviewed.

| ID | Observable condition | Negative paths / evidence |
|---|---|---|
| AC1 | Authorized Admin assigns exactly one eligible named Radiologist to the exact current handoff and source; revoke/reassign invalidates prior generation while preserving history. | Monitoring-only Admin, forged scope, unknown/ineligible doctor, stale handoff and concurrent double assignment denied without partial writes; B1/B3 policy cases required. |
| AC2 | The assigned individual authenticates into a temporary case workspace governed by approved lifetime/access policy. | Guest, forwarded/tampered locator, wrong doctor/case, expiry boundary, account suspension, credential revocation, old generation, logout and fixation cases disclose no protected data; B1/B3 required. |
| AC3 | Independent Doctor authorization and durable audit protect each clinical read and actual DICOM response; only necessary authorized patient/examination context is exposed. | Prepared-output revocation race, audit append/commit failure, direct-owner bypass, range/HEAD/cache requests and cross-tab case swapping cannot bypass gates. No AI content access. |
| AC4 | Both accepted direct and converted synthetic original DICOM render and match authoritative patient/source/handoff identity; source bytes remain unchanged. | Wrong patient, embedded UID mismatch, checksum corruption, superseded source/handoff, unsupported/malformed input and unknown identity withhold clinical continuation. Actual browser rendering evidence required. |
| AC5 | Assigned Doctor explicitly records usable or repeat_required with immutable actor/time/source-bound history; repeat_required preserves draft, enters repeat_pending and blocks finalization. | No inherited usable from technical QC; controlled reasons and other explanation validated; stale/unauthorized decision fails. No entitlement, retake dispatch, payment or notification occurs. |
| AC6 | Doctor authors and revises draft under approved content rules with revision conflicts detected and complete attributable history; reassignment follows approved earlier-draft policy. | Unknown/invalid fields, incomplete content, unsafe markup, cross-doctor spoofing, concurrent/stale save and forged authorship fail; no silent overwrite/adoption. B2 required. |
| AC7 | Explicit finalize requires current usable decision, exact reviewed report/source/handoff revisions, eligible assigned individual and approved fresh authentication. One immutable final version records actual author/finalizer and internal attestation. | Missing/failed/stale/replayed/wrong-session proof, repeat_pending, stale revision, reassignment, revoked credentials and duplicate/conflicting finalize cause no unauthorized or duplicate result. B1–B3 required. |
| AC8 | Final report and authoritative clinical status serialize with technical reopen and audit; committed finalization prevents reopen and final edits. | Supported MySQL independent-connection finalize/reopen/reassign races; injection of append/commit/source failures; incomplete/stale/unknown clinical evidence still denies reopen. No test-only source masquerades as complete production evidence. |
| AC9 | No amendment, Member result/publication/link/notification, AI content read or earnings/payment/payout side effect occurs. | Assert zero excluded owner commands, events, outbox entries and accessible routes; finalized report cannot be overwritten/deleted through Admin or stale draft paths. |
| AC10 | Existing Radiographer, Admin, Member/Operator and imaging flows retain their bounded behavior, attribution, access and immutable originals. | Focused regressions and architecture/security checks; synthetic journey and error/accessibility states complete; differences classified against exact baseline. |

## Verification requirements

### Required checks for future authorized execution

- Synthetic functional/service/HTTP tests for AC1–AC10 with complete route/capability inventory and independent Doctor authorization. Cover all approved B1–B3 policy boundaries, injection failures, no-side-effect assertions and sensitive sentinels in responses/logs/audit.
- Real parseable synthetic direct and converted DICOM fixtures; correct and conflicting patient/UID/file-meta/checksum/source versions. Reuse existing verifier fixture coverage; new clinical entry paths must prove their own gates. Include explicit usable/repeat_required and report valid/incomplete examples approved under B2.
- Supported MySQL concurrency tests using independent connections/barriers: assign/assign, revoke or same-actor reassign versus prepared HTML/image/report response, draft-save/save, finalize/finalize, finalize/reassign and finalize/technical-reopen. Prove audit/transaction rollback, lock ordering and obsolete handoff rejection. SQLite alone is insufficient for races.
- Browser journey: Admin assignment → individual login → actual original-image rendering → quality decision → draft revisions → fresh authentication → immutable internal finalization; separate repeat_required, revoke, expiry, denied access and audit-failure paths. Include keyboard/focus/labels and clear loading/unavailable/conflict states. HTTP 200 alone is insufficient.
- Focused existing regression suites: `tests/Feature/Radiographer`, `tests/Integration/RadiographerConcurrencyTest.php`, `tests/ImageGateway/BoundedDicomVerifierTest.php`, relevant Admin centralized worklist/facility operations, Member/authentication and Operator imaging suites, `tests/Architecture` and `tests/Security`. Add focused Doctor tests for this contract. Existing test configuration must be verified isolated before execution.
- Run existing viewer JavaScript tests (`node --test tests/JavaScript/operator-dicom-viewer.test.mjs`), `npm run build` for affected assets, installed Pint on changed PHP and `git diff --check`. Verify additive migration/up/down only on disposable DB; no operational migration/backfill. Broaden checks for affected callers or observed failures.

### Required evidence

Report exact baseline, governing task revision, implementation revision/precise tree state, changed surfaces, authority-to-AC-to-test mapping, installed capabilities/backend versions, actual commands/results/counts/skips, browser artifacts with synthetic data, independent-connection race results, audit/failure ordering, excluded-side-effect evidence, baseline failure comparison, deviations and unresolved risks. CI must be separately observed; source existence or historical results do not pass verification.

## Stop conditions

No implementation before B1–B3 resolution, exact immutable validated publication and independent Planner–Reviewer handoff. Stop and return to planning for missing/conflicting policy, materially changed baseline, unsafe or unavailable owner/identity contract, incomplete clinical source inventory, inability to serialize finalization/reopen or commit required audit, unsupported approved clinical scope, or required dependency/infrastructure/permission expansion. A per-case unsafe input is denied/withheld; systemic inability to satisfy the contract is a planning stop. Never weaken a gate or silently narrow the approved six-outcome objective to bypass a blocker.

## Side-effect authorization

**Current session:** read-only discovery and this task document only. The Product Owner authorizes ordinary task-only commit/push on the existing planning branch **only if the contract can legitimately satisfy T5 readiness**. B1–B3 currently prevent that publication. Preserve the local Draft without staging/committing/pushing it as executable work. No force push, branch rewrite, PR, merge, application source/test/migration/config/dependency change, production access, external communication or deployment is authorized.

**Later execution:** requires independent review/handoff of the exact published task and explicit applicable mutation authorization. No implementation commit/push, dependency installation, production migration, real patient operation or release is implied by this Draft or a future documentation publication.

## Readiness evidence and expected terminal outcome

2026-10-15 read-only checks observed clean initial planning branch and HEAD equal to the supplied baseline; no prior Radiologist task existed. Governance, template, scoped DOC requirements, decision/dependency register, upstream contract/evidence and current handoff/guard/response sources were inspected. Existing Codebase Memory aided narrowing and direct source reads verified material claims. Graphify query returned no matching nodes; no freshness/conformance claim relies on it and no derived graph artifact was created. Supporting project context is older and does not replace the supplied baseline or current sources. No external API behavior change or technical dependency choice is proposed here.

| Gate / boundary | Assessment |
|---|---|
| B0 | Bounded six-outcome product purpose and exclusions supplied by Product Owner. |
| P1 / R2 | Product scope and DOC traceability established; B1–B3 still prevent complete testable eligibility/report/access/finalization rules. |
| A3 | Owner boundaries and existing guard/handoff constraints observed; security-policy and clinical-source implementation acceptance must follow resolved authority. |
| D4 | One bounded synthetic vertical slice and dependencies identified; cannot advance past material unresolved decisions. |
| T5 | **BLOCKED — Draft, unpublished, no immutable governing task revision, not eligible for execution.** |
| E6 / V7 / R8 / A9 | Not started for Radiologist v1; no implementation or self-acceptance. |
| G10 | Excluded and unauthorized. |

**Current terminal outcome:** **PLANNING REQUIRED — B1/B2/B3 authority resolution; independent Planner–Reviewer review required.** No publication SHA or publication remote-equality claim is available. After authority resolution, update this same stable task, verify its complete contract and publish only if legitimate; then report full SHA, exact path/revision and observed remote equality, and stop **REVIEW REQUIRED** without E6 or self-approved T5 review.

Future implementation, if independently authorized, ends **Review Required** with truthful evidence or **Planning Required** with the exact stop condition. Review/remediation remains tied to the exact governing task revision. Material contract corrections require republication at this path; status-only updates cannot replace the governing revision. Independent implementation acceptance and G10 remain separate.
