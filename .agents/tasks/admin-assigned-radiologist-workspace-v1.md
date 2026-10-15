---
title: Admin-assigned Radiologist Workspace v1 through internal clinical finalization
document_id: AGENT-TASK-RADIOLOGIST-V1-001
version: 1.0
status: Validated/Published
language: en-US
last_updated: 2026-10-15
authority_note: The Product Owner approves the six product outcomes and B1–B3 for isolated synthetic Radiologist v1 development through the attributable Planner–Reviewer handoff recorded below. This publication establishes the bounded contract, not independent T5 review, implementation acceptance, real professional licensing, institutional approval or release authority. E6 remains held pending independent review and authorization.
---

# Admin-assigned Radiologist Workspace v1

## Task identity

**Task path:** `.agents/tasks/admin-assigned-radiologist-workspace-v1.md`

**Task contract state:** **Validated/Published — independent exact-revision T5 review and E6 authorization required.**

**Delivery objective / Work Package / MVP:** Radiologist v1, one vertical journey through internal clinical finalization; bounded parts of WP-18, WP-19, WP-20 and WP-22. No whole-Work-Package completion claim.

**Owner / designated planning authority:** Product Owner and independent Planner–Reviewer. Doctor owns clinical workflow. B1–B3 approvals are attributed to the Product Owner through the Planner–Reviewer handoff below; they do not establish real clinical credentials or appoint institutional authorities.

## Delivery context and objective

Deliver Admin assignment of an eligible individually authenticated Radiologist to an exact current Radiographer handoff, temporary case access, protected original DICOM review, an explicit clinical quality decision, controlled Doctor-authored report drafts and explicit internal finalization with fresh authentication, immutable history and attributable authorship. Integrate finalization with the existing Doctor guard so technical reopen cannot change a clinically finalized case.

All development and verification use isolated synthetic data. Technical Radiographer QC is not a clinical quality decision. AI output is not a Doctor-authored report. Clinical finalization is a distinct state from ingestion, AI processing, screening completion and Member publication.

## Baseline and task revision

**Implementation baseline:** `0f27d2b58c804183b907e236aa9735f5fac876ac` in `Madeena-software/mhcs-core`, supplied by the Product Owner as the immutable accepted Radiographer implementation baseline.

**Planning branch:** `task/radiologist-workspace-development-contract`.

**Task revision:** the immutable publication commit containing version 1.0 of this path, supplied as a full SHA in the publication handoff and verified against the fetched remote branch. Resolve it externally from that handoff and Git history; the document does not embed its own containing commit SHA. Prior Draft revision: `e037185422d8a0e6ca90b6ba13743454784c57aa`, superseded by this contract publication. The publication revision and implementation baseline are different references. Do not silently advance either. The Validated/Published state takes effect at the observed immutable publication; independent T5 review/E6 authorization remains required.

## Authoritative inputs

### Governing authority

- [Repository delivery contract](../AGENTS.md) and [software workflow](../software-workflow.md), especially §§9.4 and 15.1–15.7. [Existing task template](./_template.md) supplies the contract structure.
- Product Owner handoff **“MHCS — Radiologist v1 T5 Publication”**, supplied in this session on 2026-10-15: six approved outcomes, explicit exclusions, synthetic-only restriction, task-only ordinary commit/push authorization, independent T5 review and mandatory stop before E6. This section records its scope without elevating any technical proposal to approval.
- Product Owner / Planner–Reviewer handoff **“MHCS — Close Radiologist v1 T5 and Auto-Publish”**, supplied in this session on 2026-10-15: explicitly approves B1, B2 and B3 below for isolated synthetic development; authorizes this same task update, ordinary commit/push on the existing non-main branch, fetch/equality verification and an external memory checkpoint. Requires stopping REVIEW REQUIRED before E6, independent review and subsequent execution authorization. This attributable scoped decision resolves the earlier Draft blockers without extending professional, clinical, security or release approval to real operations.
- [MVP-DEC-046](../../docs/mvp/decision-log.md): individual assigned radiologists and separate clinical/publication states; product direction, not approval of clinical/security mechanisms.
- [Requirements matrix](../../docs/implementation/mhcs-core-requirements-matrix.md), [source coverage](../../docs/implementation/mhcs-core-source-coverage.md) and [implementation plan](../../docs/implementation/mhcs-core-implementation-plan.md). Their old implementation-status columns are historical, not evidence of this baseline. DEC-004 and WP-18/WP-22 retain broader report/signature and credential dependencies. The new scoped B1–B3 approval resolves their applicable synthetic-development portions here; broader certification, publication, financial and real-credential dependencies remain outside this task.
- [Radiographer contract](./admin-issued-radiographer-workspace.md) at `25d396e576ee513bee1a7e2aa2db57a65afdd666` defines the upstream handoff. Its B1/B2 professional and checklist approval is specific to Radiographers and technical QC; its temporary-access durations and B3 security approval are not automatically Radiologist policy.
- [Radiographer R8 evidence](../../docs/mvp/evidence/radiographer-workspace-r8-remediation.md) describes baseline implementation evidence and limits. It ends Review Required; the current Product Owner supplies the accepted baseline above. This task does not retrospectively issue an independent A9 verdict for that predecessor.

### Requirement traceability and ownership

All obligations below are planned, not implemented or verified. Partial coverage and exclusions are deliberate under this Product Owner handoff; older combined requirements are not claimed closed.

| Source / IDs | Bounded obligation | Owner | Acceptance / verification |
|---|---|---|---|
| PO outcomes 1–2; DOC-001, bounded DOC-002, DOC-043, DOC-054 | Exact current handoff assignment, professional/service/modality eligibility, independent action authorization and temporary individual access | Doctor; Shared authentication; Admin presentation | AC1–AC3; credential, assignment and access matrix; approved B1/B3 boundary tests |
| PO outcome 3; DOC-041–043, DOC-053, DOC-055, DOC-057–058 | Original study and patient association, private case/purpose-scoped authorized DICOM review, stale/unknown rejection | Gateway imaging verification; Member identity; Doctor clinical authorization | AC3–AC4; direct/converted synthetic DICOM, disclosure and sentinel tests |
| PO outcome 4; DOC-006–013, DOC-021, bounded DOC-046, DOC-047 | Explicit usable/repeat_required decision, immutable decision history; repeat_pending preserves draft and blocks final submission | Doctor | AC5; state, reason and conflict tests |
| PO outcomes 5–6; DOC-020–022 (finalization only), DOC-024, bounded DOC-050, DOC-056, DOC-059 | Draft revisions, internal finalization, authorship, immutable final report and fresh authentication | Doctor; Shared security/audit | AC6–AC8; approved B2/B3 boundary tests |
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

### Approved B1–B3 decisions — synthetic development only

Approval provenance is the Product Owner decision conveyed by the Planner–Reviewer handoff “MHCS — Close Radiologist v1 T5 and Auto-Publish” on 2026-10-15. These are approved development mechanisms, not evidence that a real professional, institution, report or environment has been approved. No B1–B3 reapproval is needed for this bounded contract.

**B1 — professional eligibility.** Use individually authenticated Radiologists with explicit Doctor-owned scoped professional eligibility records. Eligibility requires a credential evidence reference, authorized verifier, verification timestamp, current status, validity/revocation and facility/service/modality scope. Credentialing verification requires an explicit designated capability; ordinary Admin or Doctor roles do not grant it. Unknown, expired, revoked, inconsistent or unverified eligibility denies assignment and access. Resolve the trusted verifier and current record on the server; preserve evidence and verification/revocation history. Revalidate eligibility and its scope on every protected action and disclosure. Changes invalidate affected access without deleting history. Synthetic records exercise this mechanism but establish no real licensing or institutional authority. No external licensing provider or automatic role grant is introduced.

**B2 — reporting and internal finalization.** Draft content consists of Findings, Impression/conclusion and optional Recommendations. Drafts remain editable with controlled attributable revisions; an incomplete draft may be saved but cannot finalize. Finalization requires meaningful manually authored findings and conclusion, an explicit current `usable` decision, correct current case/source/handoff binding and attributable internal attestation. Blank, whitespace-only or markup-only content cannot satisfy required content; no fabricated normal findings, auto-filled clinical conclusions or AI-generated clinical conclusions. The clinician explicitly attests to their authored content; validation must not claim to determine clinical truth. No new word-count threshold or clinical reporting template is prescribed. Attestation records the authenticated author/finalizer, exact report revision/content binding, case/source/handoff and time of explicit confirmation. It is internal attestation, not a legally certified electronic signature. Finalized versions are immutable.

On reassignment, preserve the previous Doctor's draft, authorship and history. The replacement Radiologist must create an independently authored draft; do not prepopulate/adopt the earlier draft, relabel its author, silently overwrite it or permit the replacement to finalize it as their own. Clinical-history reads remain independently case/purpose/authorization scoped; preservation itself grants no prior-assignee access. No amendment workflow is introduced.

**B3 — temporary access and fresh authentication.** Default access is 24 elapsed hours from issuance; the maximum is 72 elapsed hours. An explicit requested duration must be positive and no more than 72 hours; there is no automatic renewal. Use the existing UTC clock and enforce `[issued_at, expires_at)`. Revocation, reassignment and account/eligibility changes apply to existing sessions and prepared output at the established disclosure ordering point. After finalization, only authorized read-only access is allowed while the entitlement remains valid; finalization does not extend access or create a new grant. Signed URLs are locators, never bearer authorization.

Each finalization action requires explicit password/credential re-verification using existing secure credential-verification primitives, bound to the currently authenticated Doctor, active session, exact case and report revision. A previous login or generic recent-password session flag cannot satisfy a different finalization. Verify within the action and atomically consume/record the action binding with finalization; no reusable cross-action proof is needed. If a separate challenge is used, it must expire, be single-use and become invalid on any binding change. In all cases enforce throttling, expiry, logout/account-change invalidation and replay resistance; deny failed verification without consuming clinical state or logging credentials. A successful idempotent retry may identify the same committed operation under fresh current authorization, but cannot finalize another report/revision or disclose a historical result after access loss. Do not introduce a new authentication provider, OTP service or signing system.

Admin assignment/revocation/reassignment require separate explicit action capabilities and fresh managed case/facility scope, independent from monitoring. Credential verification uses its own explicitly designated capability under B1. Preserve existing recent/step-up authentication requirements for sensitive Admin actions under DOC-059 and repository security policy; no ordinary role implicitly satisfies them and no new authentication provider or weaker exemption is authorized. Capability identifiers may follow house vocabulary; authority separation cannot change.

### Approved assumptions and later obligations

The supplied baseline, six product outcomes and scoped B1–B3 approvals may be used; no real professional qualification, institutional approval or production readiness is inferred. Owner fixtures must explicitly represent synthetic eligibility, scopes and verifier capabilities under B1. Clinical usability remains an explicit human decision; technical Layak never substitutes for it.

Operational audit retention/access/holds, legal/institutional clinical obligations, display assurance, production credential verification, migration/backup/rollback and deployment security require their designated owners before affected real-data operation/G10. They are not waived or claimed complete. They need not expand this synthetic slice unless they materially affect development behavior or acceptance.

Independent Planner–Reviewer review of the exact published revision is mandatory before any E6 handoff under the current instruction. This session stops after verified publication with REVIEW REQUIRED; it does not execute or self-approve the independent T5 review. Publication authority is not source-change or release authority.

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
- Reuse repository authorization/context, private viewer, verified imaging, clock, audit and transaction mechanisms where adequate. New dependencies, infrastructure or parallel frameworks require separate authority. Apply approved B1–B3; do not substitute Radiographer policy or invent real-world professional/clinical/security approval.

## Acceptance criteria

The following define the approved outcome, B1–B3 mechanisms and necessary integrity checks. All are mandatory for the complete vertical slice; implementation verification and independent review remain future evidence.

| ID | Observable condition | Negative paths / evidence |
|---|---|---|
| AC1 | Authorized Admin assigns exactly one eligible named Radiologist to the exact current handoff and source; revoke/reassign invalidates prior generation while preserving history. | Monitoring-only Admin, forged scope, unknown/unverified/expired/revoked/inconsistent or wrongly scoped eligibility, stale handoff and concurrent double assignment denied without partial writes. Ordinary roles cannot credential a doctor; unauthorized verification/revocation and forged verifier/evidence rejected; audit/history preserved. |
| AC2 | The assigned individual authenticates into a case workspace with default 24-hour, maximum 72-hour access, no automatic renewal and only authorized read-only access after finalization while valid. | Guest, forwarded/tampered locator, wrong doctor/case, expiry boundary, account suspension, credential revocation, old generation, logout and fixation cases disclose no protected data. Zero/negative/over-72-hour duration rejected; exact issuance/expiry boundary, default duration, no auto-renewal, post-finalization read-only and permission changes tested. |
| AC3 | Independent Doctor authorization and durable audit protect each clinical read and actual DICOM response; only necessary authorized patient/examination context is exposed. | Prepared-output revocation race, audit append/commit failure, direct-owner bypass, range/HEAD/cache requests and cross-tab case swapping cannot bypass gates. No AI content access. |
| AC4 | Both accepted direct and converted synthetic original DICOM render and match authoritative patient/source/handoff identity; source bytes remain unchanged. | Wrong patient, embedded UID mismatch, checksum corruption, superseded source/handoff, unsupported/malformed input and unknown identity withhold clinical continuation. Actual browser rendering evidence required. |
| AC5 | Assigned Doctor explicitly records usable or repeat_required with immutable actor/time/source-bound history; repeat_required preserves draft, enters repeat_pending and blocks finalization. | No inherited usable from technical QC; controlled reasons and other explanation validated; stale/unauthorized decision fails. No entitlement, retake dispatch, payment or notification occurs. |
| AC6 | Doctor authors Findings, Impression/conclusion and optional Recommendations with controlled revisions and attributable history. After reassignment the replacement creates an independently authored draft; the earlier draft and author/history remain preserved. | Unknown/invalid fields, unsafe markup, cross-doctor spoofing, concurrent/stale save and forged authorship fail. Incomplete drafts may save but cannot finalize. Blank/whitespace/markup-only findings/conclusion, fabricated defaults, AI conclusions, automatic adoption and finalizing the previous author’s draft fail. |
| AC7 | Explicit finalize requires meaningful manually authored findings/conclusion, current usable decision, exact reviewed report/source/handoff revisions, eligible assigned individual and password/credential re-verification for each action bound to current Doctor/session/case/report revision. One immutable final version records actual author/finalizer and internal attestation, without claiming certified signing. | Missing/failed/expired/replayed/wrong-session/wrong-doctor/wrong-case/wrong-revision verification, ordinary login/recent-password flags, logout/account/password changes, throttling, repeat_pending, stale revision, reassignment, revoked credentials and duplicate/conflicting finalize cause no unauthorized or duplicate result. No reusable proof authorizes another operation; successful retries never bypass current access. |
| AC8 | Final report and authoritative clinical status serialize with technical reopen and audit; committed finalization prevents reopen and final edits. | Supported MySQL independent-connection finalize/reopen/reassign races; injection of append/commit/source failures; incomplete/stale/unknown clinical evidence still denies reopen. No test-only source masquerades as complete production evidence. |
| AC9 | No amendment, Member result/publication/link/notification, AI content read or earnings/payment/payout side effect occurs. | Assert zero excluded owner commands, events, outbox entries and accessible routes; finalized report cannot be overwritten/deleted through Admin or stale draft paths. |
| AC10 | Existing Radiographer, Admin, Member/Operator and imaging flows retain their bounded behavior, attribution, access and immutable originals. | Focused regressions and architecture/security checks; synthetic journey and error/accessibility states complete; differences classified against exact baseline. |

## Verification requirements

### Required checks for future authorized execution

- Synthetic functional/service/HTTP tests for AC1–AC10 with complete route/capability inventory and independent Doctor authorization. Cover all approved B1–B3 policy boundaries, injection failures, no-side-effect assertions and sensitive sentinels in responses/logs/audit.
- Dedicated B1 tests cover verifier capability separation, required evidence fields, trusted actor/time/status, validity/revocation, all facility/service/modality scope combinations, inconsistency and changes during access. B3 frozen-clock tests cover positive duration/default 24 hours/max 72 hours, invalid duration, exact expiry, no auto-renewal and post-finalization read-only. Credential verification tests cover per-action binding, throttling, logout/account/password/session/revision changes, expiry and atomic single-use/replay behavior. These exercise current secure primitives with synthetic accounts, never real credentials.
- Real parseable synthetic direct and converted DICOM fixtures; correct and conflicting patient/UID/file-meta/checksum/source versions. Reuse existing verifier fixture coverage; new clinical entry paths must prove their own gates. Include explicit usable/repeat_required and manually authored report valid/incomplete/blank examples under B2; verify incomplete draft save versus finalization denial, independent replacement authorship and absence of auto-filled/AI findings.
- Supported MySQL concurrency tests using independent connections/barriers: assign/assign, revoke or same-actor reassign versus prepared HTML/image/report response, draft-save/save, finalize/finalize, finalize/reassign, eligibility/account revocation versus finalize and finalize/technical-reopen. Prove audit/transaction rollback, lock ordering and obsolete handoff rejection. SQLite alone is insufficient for races.
- Browser journey: Admin assignment → individual login → actual original-image rendering → quality decision → draft revisions → fresh authentication → immutable internal finalization; separate repeat_required, revoke, expiry, denied access and audit-failure paths. Include keyboard/focus/labels and clear loading/unavailable/conflict states. HTTP 200 alone is insufficient.
- Focused existing regression suites: `tests/Feature/Radiographer`, `tests/Integration/RadiographerConcurrencyTest.php`, `tests/ImageGateway/BoundedDicomVerifierTest.php`, relevant Admin centralized worklist/facility operations, Member/authentication and Operator imaging suites, `tests/Architecture` and `tests/Security`. Add focused Doctor tests for this contract. Existing test configuration must be verified isolated before execution.
- Run existing viewer JavaScript tests (`node --test tests/JavaScript/operator-dicom-viewer.test.mjs`), `npm run build` for affected assets, installed Pint on changed PHP and `git diff --check`. Verify additive migration/up/down only on disposable DB; no operational migration/backfill. Broaden checks for affected callers or observed failures.

### Required evidence

Report exact baseline, governing task revision, implementation revision/precise tree state, changed surfaces, authority-to-AC-to-test mapping, installed capabilities/backend versions, actual commands/results/counts/skips, browser artifacts with synthetic data, independent-connection race results, audit/failure ordering, excluded-side-effect evidence, baseline failure comparison, deviations and unresolved risks. CI must be separately observed; source existence or historical results do not pass verification.

## Stop conditions

B1–B3 are resolved for synthetic development. No implementation before exact immutable validated publication, independent Planner–Reviewer T5 review and subsequent E6 authorization. Stop and return to planning for missing/conflicting policy, materially changed baseline, unsafe or unavailable owner/identity contract, incomplete clinical source inventory, inability to serialize finalization/reopen or commit required audit, unsupported approved clinical scope, or required dependency/infrastructure/permission expansion. A per-case unsafe input is denied/withheld; systemic inability to satisfy the contract is a planning stop. Never weaken a gate or silently narrow the approved six-outcome objective to bypass a blocker.

## Side-effect authorization

**Current session:** read-only discovery, this task update and an external rolling `CHAT_MEMORY.md` checkpoint. The Product Owner explicitly authorizes automatic ordinary task-only commit/push on the existing `task/radiologist-workspace-development-contract` branch, fetch and remote-equality verification without another approval request. Preserve history; do not recreate the branch. Stage/commit only this task file. Keep memory outside the repository. No force push, PR, merge, application source/test/migration/config/dependency change, production access, external messages or deployment is authorized.

**Later execution:** requires independent review of the exact published task and subsequent E6 authorization. The in-scope implementation surfaces above define the proposed bounded execution contract; this publication session cannot mutate them. Implementation commit/push, dependency installation, production migration, real-patient operation and release are not authorized by this documentation publication.

## Readiness evidence and expected terminal outcome

**Prior discovery (2026-10-15):** governance, template, scoped DOC requirements, decision/dependency register, upstream contract/evidence and current handoff/guard/response sources were inspected at the supplied baseline. Existing Codebase Memory aided narrowing; direct source reads verified material claims. Graphify returned no matching nodes; no freshness/conformance claim relies on it and no derived artifact was created. Supporting project context is older and does not override the supplied baseline or current sources.

**This finalization (2026-10-15):** initial clean local and fetched remote planning heads both equal the supplied published Draft `e037185422d8a0e6ca90b6ba13743454784c57aa`. Git ancestry and the baseline-to-head changed-file inventory confirm the only overlay on implementation baseline `0f27d2b58c804183b907e236aa9735f5fac876ac` is this task. Existing `AccountStateUserProvider::validateCredentials` enforces account eligibility before delegating to the existing secure credential provider; `ClinicalStatusSource` retains its complete-source/lock coordination contract. These are reuse evidence, not proof that future Radiologist finalization/security works. No external API or dependency change is proposed.

Publication checks must confirm: all local links resolve; template minimum contract concerns and AC1–AC10 remain present; B1–B3 approvals are reflected in requirements/negative tests; no current unresolved-blocker or Draft-only statements remain; the implementation baseline is unchanged; only this task is staged/committed; whitespace checks pass; exact committed content is retrievable and fetched remote SHA equals local publication SHA. Actual results and full immutable identity are returned in the publication handoff and external memory, without a self-referential commit SHA in the task.

| Gate / boundary | Scoped assessment |
|---|---|
| B0 | Approved bounded six-outcome product purpose and exclusions. |
| P1 / R2 | Approved B1–B3 settle eligibility, report content/authorship, temporary access and per-action credential verification; DOC traceability and AC1–AC10 supply observable verification. Broader partial requirements remain excluded, not claimed complete. |
| A3 | Existing Doctor/Operator/Gateway/Member ownership, independent clinical authority, verified imaging, durable audit and coordinated guard/lock contracts constrain implementation; new auth/signing infrastructure is excluded. |
| D4 | One bounded synthetic vertical slice, accepted immutable implementation baseline, explicit dependencies, preservation, exclusions and risk-proportional verification. No unresolved upstream development decision remains. |
| T5 | **Contract Validated/Published at the verified immutable pushed revision. Independent exact-revision T5 review and E6 authorization remain pending; do not claim unconditional execution eligibility or independent T5 passage while that hold remains.** |
| E6 / V7 / R8 / A9 | Not started for Radiologist v1; no implementation verification or self-acceptance. |
| G10 | Excluded and unauthorized; real-world clinical/security/operational obligations remain separate. |

**Current terminal outcome:** **REVIEW REQUIRED** after automatic task-only commit/push and fetched remote-equality verification. B1–B3 development blockers are closed by the attributable approval above; no remaining material decision blocks contract publication. The remaining routing hold is independent Planner–Reviewer review of the exact publication revision and subsequent E6 authorization. Stop here; no application implementation or self-approved independent T5 verdict.

Future implementation, if independently authorized, ends **Review Required** with truthful evidence or **Planning Required** with the exact stop condition. Review/remediation remains tied to the exact governing task revision. Material contract corrections require republication at this path; status-only updates cannot replace the governing revision. Independent implementation acceptance and G10 remain separate.
