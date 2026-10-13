---
title: Admin-Issued Temporary Case Access and Radiographer Workspace
document_id: MHCS-TASK-ADMIN-RADIOGRAPHER-WORKSPACE-001
version: 0.1
status: Draft — Review Required
language: en-US
last_updated: 2026-10-13
role: Planner / Reconnaissance
authority_note: Planning proposal only. Product direction is supplied by the Product Owner; proposed access, QC, persistence and handoff mechanics remain unapproved. T5 has not passed. No implementation or publication is authorized.
---

# Proposed task

## Task identity and delivery context

**Task path:** `.agents/tasks/admin-issued-radiographer-workspace.md`
**Task contract state:** **Draft — Review Required**
**Delivery mode:** MVP-oriented, one functional Radiographer case workspace.
**Parent scope:** MVP-GAP-028, with bounded dependencies on MVP-GAP-027/029/031; existing imaging/Operator and future Doctor requirements remain independently owned.
**Designated authorities:** Product Owner; Operator/Radiography professional authority; Member, Operator, Image Gateway and Doctor technical owners; security/privacy authority. These are responsibility roles, not newly appointed people.

Admin remains the persistent operational back-office. Admin issues and distributes case-scoped web links so participants can complete assigned interactions without separate permanent dashboards. Public reachability permits a safe access entrypoint, never anonymous medical-data disclosure. The first proposed outcome is authorized Radiographer image inspection, technical QC, examination-specific metadata and a durable handoff for later radiologist review. Radiologist reporting and Member publication are separate contracts.

## Baseline, delivery state and source provenance

**Requested reconnaissance / proposed implementation baseline:** `f8f0bb9e2e0cd12d1b797cf6a6689f26336274ba`.
**Observed checkout before this Draft:** `a0083eda96241af8fcd8eb859d967bcf4b43c3b7`, clean.
**Observed tracked tree at both revisions:** `74f2516332052903291ddb15c70d37b629e62d14`; `git diff --stat` was empty. Therefore direct checkout source inspection represents the requested baseline's tracked contents. Commit identity, integration history and acceptance are still distinct. No checkout, rebase, commit or branch change was performed.
**Governing task revision:** resolved when published; unavailable while this file is an uncommitted Draft. Never hand this revision to an Executor.

The [centralized-worklist acceptance record](../../docs/mvp/evidence/centralized-study-worklist-acceptance.md) records bounded synthetic-development V7/A9 acceptance of `36d689c05e3fab1f5cff909439a7b68aff7cb205`, governed by `.agents/tasks/centralized-study-worklist.md @ fe8753490d361702e034a364240cb104e779c22c`. The [board acceptance record](../../docs/mvp/evidence/admin-facility-session-operations-acceptance.md) records its earlier bounded acceptance. These records resolve the older pending-board/worklist orientation for this reconnaissance; no fresh acceptance or CI certification is made. No dependent unfinished implementation was identified in these records. Remote integration status was not queried. Before future publication, verify the intended baseline and reconcile intervening changes without silently repinning it.

| Source | Classification and use |
|---|---|
| Direct Product Owner request, “MHCS — Plan Admin-Issued Temporary Case Access and Radiographer Workspace”, supplied 2026-10-13 | Current product clarification and authorization to prepare this Draft only. Attachment SHA-256: `7b1acda725dda1b83e8fcbd7da4149c066b884d87bf71e421dfe85c16d947e9c`. The clarification does not approve tokens, OTP, sessions, schema or clinical-security implementation. |
| [Repository contract](../AGENTS.md), [software workflow](../software-workflow.md), [planning procedure](../prompts/plan-create-task.md), [task template](_template.md) | Delivery governance, independent review, bounded tasks and separate release. Loaded for planning. |
| [Project context](../context/project.md) | Supporting orientation; older pending-state language is reconciled with exact acceptance records and current source rather than treated as current proof. |
| [MVP decisions](../../docs/mvp/decision-log.md), especially MVP-DEC-044–046 | Approved Admin-first and imaging/attribution/state-separation target direction. Later board-specific approvals, including MVP-DEC-053 and worklist SW-D1/D2, do not authorize workspace content or mutations. Historical decisions/exceptions retain their scope. |
| [Active scope](../../docs/mvp/beta-scope.md), [gap register](../../docs/mvp/beta-gap-register.md), [roadmap](../../docs/mvp/roadmap.md) | Dependency/authority map. MVP-GAP-028/029/031 remain open; MVP-GAP-035/036 constrain future AI/report exposure. Earlier Doctor exclusion is historical target scope, not a veto on the clarified direction. |
| [Grabber integration contract](../../docs/mpips/mhcs-grabber-dicom-ingestion-contract.md), [MPIPS conversion contract](../../docs/mpips/mhcs-dicom-api.md), [field-operations task](urgent-operator-field-operations.md), [Operator AI integration task](operator-ai-pacs-integration.md) | Preserve authenticated direct DICOM, manual NPZ/MPIPS, INT-DUAL-001/INT-ASYNC-001 and FIELD-OPS-007/008/009. These do not establish workspace authority. |
| [Maintained business model v0.2](https://docs.google.com/document/d/1zxmPjB-gevtVuaoTEmAtLjpDWlJf7USnkeigVbPJw0E/edit) | Successfully fetched through Google Drive on 2026-10-13; provider modified time `2026-09-22T01:40:27.767Z`. Stakeholder Discussion Draft, not approved authority; live content is not an immutable adopted requirements baseline. BP identifiers below are candidate semantic references. No external document was edited or full snapshot persisted. |
| Exact tracked application source and tests at the requested baseline | Observed implementation and test definitions; no application tests, browsers or live systems were exercised in this planning cycle. Historical accepted evidence is attributed to its record. |

## Product decisions already settled

1. Persistent Admin operations and Admin-distributed case URLs are the intended interaction model. A full permanent Radiographer, Radiologist or Member dashboard is not necessary for assigned interactions.
2. Link possession is not identity, professional authorization or consent. Clinical actions remain individually attributable; Radiologists authenticate individually and retain clinical responsibility.
3. Authenticated Grabber direct DICOM is the primary automatic capture path. The manual NPZ/MPIPS route remains available. Radiographers review received DICOM; no manual DICOM upload prerequisite is introduced.
4. Radiographers require image inspection, technical-quality review, examination-specific metadata and handoff. Operational receipt, QC, AI processing, clinical finalization and publication remain separate.
5. Existing Operator workflows continue. Their transition or retirement, Patient migration, external SIMRS integration and broader portal changes require separate governed work.
6. Member delivery requires publication of the appropriate output/version and authorization of the recipient. No result link may turn AI readiness into clinical finalization or recipient authorization.

These decisions need no blanket reapproval. The unresolved decisions below concern the concrete implementation and exposure boundary.

## Verified reusable capabilities and limits

Paths are relative to the repository and observed at the requested baseline.

| Workflow | Exact implementation evidence | Reuse / limitation |
|---|---|---|
| Admin worklist and examination context | `app/Providers/Filament/Pages/AdminCentralizedStudyWorklist.php`; `app/Modules/ImageGateway/Application/Contracts/AdminStudyWorklist.php`; `app/Modules/ImageGateway/Application/Services/AdminStudyWorklistRead.php`; `app/Modules/Operator/Application/Contracts/AdminStudyIntakes.php`; `app/Modules/Operator/Application/Services/AdminStudyIntakeRead.php`; `app/Modules/Member/Application/Contracts/AdminStudyReferences.php`; `app/Modules/Member/Application/Services/AdminStudyReferenceRead.php`; `tests/Feature/Admin/AdminCentralizedStudyWorklistTest.php` | Existing `/admin/study-worklist` selects managed facility/local date/session, paginates minimized X-ray intakes and shows independent persisted receipt/processing/DICOM/AI states. Owner contracts validate associations and exact fresh capabilities. Public rows exclude internal admission/study IDs, identities and image/report URLs. A new issuance action must resolve the case server-side through owners; neither expose all internal tuples nor infer issuance permission from monitoring permission. |
| Automatic direct DICOM | `routes/api.php`; `app/Http/Middleware/AuthenticateGrabberClient.php`; `app/Http/Controllers/Grabber/GrabberDicomUploadController.php`; `app/Modules/Operator/Application/Services/GrabberDicomIngestionService.php`; `app/Modules/Operator/Application/Services/RadiographySessionLocatorService.php`; `tests/Feature/Operator/OperatorFieldOperationsSlice3Test.php` | Authenticated client/site/shift and locator correlation; payload size/signature/checksum checks; optional supplied MRN match; idempotency; private storage; capture/study persistence, queue history/audit/outbox, locator invalidation. Queue becomes `awaiting_ai`; AI dispatch is attempted afterward and failure is contained. These are technical events, not human QC. Four-digit locators and Grabber machine credentials are unsuitable workspace credentials. Signature checks are not full diagnostic-adequacy validation. |
| Manual NPZ/MPIPS | `app/Http/Controllers/Operator/ImageGatewayController.php` (`captureShow`, `captureStore`, `captureStatus`); `app/Modules/ImageGateway/Application/Services/ImageGatewayCaptureService.php` (`submit`, `acceptSources`, metadata validation); `app/Modules/ImageGateway/Application/Jobs/ProcessCaptureSet.php`; `resources/views/operator/xray-capture.blade.php`; `tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php`; `tests/Feature/Operator/OperatorAiPacsIntegrationTest.php` | Existing radiograph/gain/manifest capture, durable acceptance, asynchronous conversion/retry and derived study. Preserve the manual UI and worker behavior. Metadata is captured before acceptance; `metadata_editable` is false once capture exists. Existing pre-capture metadata is not a post-receipt QC edit contract. |
| Protected DICOM viewer | `routes/web.php`; `app/Http/Controllers/Operator/ImageGatewayController.php` (`study`, `dicomResponse`); `app/Modules/ImageGateway/Application/Services/ImageGatewayCaptureService.php` (`authorizedStudiesQuery`, `readDicom`); `resources/views/operator/study.blade.php`; `resources/js/operator-dicom-viewer.js`; `tests/Feature/Operator/OperatorPortraitDicomViewerTest.php`; `tests/JavaScript/operator-dicom-viewer.test.mjs` | Cornerstone consumes an application URL via `data-image-url`; pan/zoom/VOI/rotation/flip/fullscreen and loading/failure behavior can be reused. Controller returns private no-store DICOM bytes after Operator/profile/site/shift checks. Current service authorization is broader site/shift scope, not an exact Radiographer case assignment. The Blade shell hardcodes Operator navigation/download URLs; it cannot be copied unchanged into the new workspace. Viewer controls do not establish professional display/calibration certification. |
| Private object delivery | `app/Shared/Storage/PrivateObjectStore.php`; `app/Shared/Storage/AccessGrant.php`; `app/Shared/Storage/PrivateObject.php` | Existing server-issued signed object grants bind target, actor, audience, purpose, correlation and ordered validity times. Keep grants and opaque storage keys server-side, after domain authorization. They are not browser-login credentials and have no persisted case revocation/assignment mechanism themselves. Existing object TTL is not a workspace/session expiry policy. |
| Staff identity, assignment and audit | `app/Modules/Operator/Domain/Models/OperatorProfile.php`; `app/Modules/Operator/Application/Services/OperatorAuthorization.php`; `app/Shared/Context/AuthenticatedContext.php`; `app/Shared/Authorization/AdminOperationalRead.php`; `app/Shared/Audit/AuditEvent.php`; `app/Shared/Security/SensitiveDataSanitizer.php` | Existing user-bound active Operator profiles, site/shift assignments, purpose-scoped contexts and attributable sanitized audits. Admin read pattern commits audit before disclosure and freshly rechecks permission. Existing DICOM service reads do not supply equivalent dedicated workspace read/QC audit. Profile/role labels alone do not prove Radiographer qualification. Ingestion clears the queue claimant, so a post-ingestion workspace must not depend on that current claim. |
| Laravel browser identity/session | `config/auth.php`; `config/session.php`; `bootstrap/app.php`; `app/Http/Controllers/Member/AuthenticationController.php`; `app/Modules/Member/Application/Services/InteractiveMemberLoginService.php`; `app/Shared/Security/TemporaryCredentialIssuer.php` | Existing web session guard/User provider, session rotation on login/password change and logout invalidation. Current interactive login only routes eligible Members/Operators and restricts intended destinations to those paths. Reusing Auth primitives does not make arbitrary workspace or Doctor login eligible. TemporaryCredentialIssuer replaces a user's password and requires password change; it is not an invitation token and should not be repurposed to mutate existing staff credentials. No new auth framework is necessary for this server-rendered proposal. |
| Radiologist assignment/reporting | `app/Modules/Doctor/README.md`; `app/Modules/Doctor/DoctorServiceProvider.php`; scoped Doctor subdirectory READMEs; `routes/web.php` | Doctor module currently registers its ownership boundary only; the inspected module/routes have no functioning individual Doctor login, offer/claim, interpretation, report finalization or amendment workflow. The ingestion contract's mention of Doctor consultation tools is not implementation proof. Separately deliver these capabilities with clinical policy; do not simulate a Doctor using an Operator/Admin context. |
| Member results | `routes/web.php`; `app/Modules/Member/Application/Services/InteractiveMemberLoginService.php`; `app/Modules/ImageGateway/Application/Contracts/ImageGatewayAiServiceContract.php`; `app/Http/Controllers/Operator/OperatorAiReportController.php` | Existing Member login/profile/booking and private Operator AI-report route are not an authorized published Member result surface. No functioning Member publication/result-version route was identified in the inspected route/module surfaces. Result recipient, publication, amendment and recovery contracts remain separate. |

**Persistence limitation:** `database/migrations/2026_08_11_000001_create_image_gateway_capture_tables.php` has unique capture per admission and unique study per capture; `database/migrations/2026_08_13_000002_add_capture_metadata_to_image_gateway_capture_sets.php` adds capture metadata. Existing schema is not a multi-instance PACS or a QC/history/temporary-entitlement model. Do not promise full multi-series browsing or complete-view verification from one stored object. The first supported examination/image-set contract must be approved; cases exceeding it must be withheld/escalated, not silently marked complete.

## Business Draft reconciliation and staged workflow

| Business Draft reference | Meaning in that Draft | Reconciliation for this proposal |
|---|---|---|
| BP-018 | Eligible authorized radiography work claim; station selection cannot grant role | Bind an individually verified worker to a case through an approved assignment policy. Existing shift assignment helps establish context but is not the proposed case entitlement. |
| BP-020 | Imaging session produces capture set | Existing Grabber acquisition and manual NPZ capture remain input paths. |
| BP-021 / BP-022 | Technical adequacy/completeness review, with justified retake/omission before submission | Post-receipt QC needs its own decision/evidence. Record inadequacy/escalation; automated retake, omission and clinical repeat are outside this first slice. Professional criteria must be supplied, not invented. |
| BP-023 / BP-024 | Technically accepted complete set is submitted; Gateway establishes durable acceptance | Current Grabber durably accepts bytes before the proposed human workspace QC. For this deployment, distinguish technical transport receipt from later human acceptance/handoff of the selected set. This mapping is a proposed reconciliation requiring scoped Product/professional/technical disposition, not a rewrite or assertion that existing ingestion fulfills BP-021/023. |
| BP-025 / BP-026 | Processing and traceable downstream context | Direct DICOM already produces a study; legacy NPZ requires conversion. Neither processing success nor a study reference proves QC, diagnosis or publication. |
| BP-028 / BP-029 | Eligible Doctor offer/claim and attributable professional review/report | Future Doctor-owned slice consumes the QC handoff. First slice records ready/pending review, not an assigned Doctor, finalized report or completed consultation. AI success is not the Draft's prerequisite for Doctor review. |
| BP-031 / BP-032 | Independent output/version publication and authorized recipient notification/temporary access | Separate future publication and result-access contracts. A QC handoff is never publication authority. |

Proposed operational stages, not new approved state names:

```text
Grabber → authenticated durable DICOM receipt ─┐
Manual NPZ → durable receipt → conversion ────┤
                                             ↓
                         associated private DICOM available
                                             ↓
                Admin issues exact-case Radiographer assignment/link
                                             ↓
               individual authentication + fresh case authorization
                                             ↓
                  inspect → save QC and examination metadata
                                             ↓
                 eligible QC handoff → pending radiologist review
                                             ↓
                    separately authorized Doctor workflow
                                             ↓
              separately authorized output publication and delivery
```

AI processing remains an independent existing asynchronous branch. Preserve automatic dispatch and `awaiting_ai`; do not gate ingestion or move existing queue completion on QC in this slice. QC approval must reference the exact study/image-set version reviewed; a newer/corrected study cannot silently inherit it. Handoff records must survive link expiry/reassignment as history without granting the former worker continuing access. Exact correction/reopen behavior requires decision W4.

Post-receipt metadata must not silently rewrite original DICOM, capture manifest, ingestion evidence or an AI report already produced. Existing validation vocabulary includes study description, detector type, body part, laterality and projection; reuse only fields whose meaning is appropriate to the approved post-receipt contract. Minimum mandatory fields, discrepant-value handling and downstream propagation are not yet approved.

## Minimum viable access design — recommendation, not approval

### Alternatives

| Candidate | Benefit | Limitation / disposition |
|---|---|---|
| Laravel temporary signed URL identifying a persisted case entitlement, followed by individual login and a session | Reuses URL signing and current web guard; signature/expiry plus server-side assignment revocation; no raw invitation secret needs database storage | Recommended first candidate, conditional on W1–W3. Signed URL is a locator/entry capability only; the policy must still validate actor, assignment, action and scope. Persisted generation/revocation is required because signature validity alone cannot revoke/reassign. |
| High-entropy opaque invitation secret, stored as a digest, exchanged after independent verification for a scoped session | Can support account-light/passwordless entry and atomic one-time exchange | More lifecycle, delivery/recovery and verifier work. Candidate only if existing credential login cannot meet the approved staff model. Passwordless, OTP channel, duration, verification assurance and account creation need explicit decisions. |
| Ordinary case URL plus existing individual login and persisted assignment | Smallest mechanics if a protected locator and assignment fully satisfy the approved access model | Valid alternative if owners approve; removes link-signing complexity but still needs expiration/revocation on entitlement, safe identifiers and anti-enumeration. Link itself authenticates nobody. |
| One unrestricted bearer link, a shared staff login, Grabber token or four-digit locator reused for clinical access | Convenient entry | Reject: lacks separate identity/action authority and safe case lifecycle; would broaden existing machine/operational trust. |

### Proposed access contract

1. An exact authorized Admin action resolves the examination/admission and its current study through domain owners, selects an eligible participant, and creates a persisted entitlement. Candidate record semantics: issuer, individual participant, case/site scope, permitted action set, validity, revocation/assignment generation and audit references. This is a semantic contract, not approved table design. Reuse adequate owned persistence if found; any minimal additive schema requires approved A3/T5 scope. Do not store revocation only in browser/session memory.
2. Admin receives a minimal copyable URL for distribution through an already approved operational channel. No email/SMS/WhatsApp automation or vendor choice in the first slice. Link/notification success does not prove delivery, identity or authorization. Do not put patient names, MRNs/NIK, clinical content, private storage locations or role grants in the URL.
3. An unauthenticated visit receives only a neutral verification/login entrypoint. It must not reveal case existence or PHI. GET must not consume a one-time credential or perform issuance/QC/handoff; protect against link scanners. After individual verification, rotate the session and retain only the necessary case reference server-side; use a clean workspace URL. Never accept browser-supplied actor, permissions or trusted context. Login return destinations must be same-origin and allowlisted.
4. Each workspace read, DICOM request, metadata save and handoff rechecks current account/eligibility, entitlement validity/revocation/generation, participant match, permitted action, domain associations and reviewed study revision. Ordinary login/session lifetime cannot override case expiry. Reassignment/reissue invalidates old case access, including already-open sessions, without deleting history or unnecessarily logging out unrelated work. A forwarded link fails for another actor. Exact retry/reissue/recovery semantics are W3 policy.
5. Deliver DICOM from an application endpoint after exact-case authorization and an Image Gateway owner read. Issue internal PrivateObjectStore grants only after those checks. Return approved bytes with private/no-store and nosniff behavior; prevent intermediary caching. Keep raw keys, storage/presigned URLs and grants out of HTML/JSON. Current Operator authorizers cannot be bypassed with an invented profile/context; the new policy must enforce an exact case before any shared binary read.
6. Reuse the viewer engine with a caller-provided authorized image URL and minimized approved examination context. Change only the reusable view boundary needed to remove hardcoded Operator navigation/download actions. No new viewer package or duplicated renderer. Render explicit unavailable/loading/failed states; failure cannot become QC approval. DICOM bytes themselves may contain patient headers: suppressing labels does not remove that disclosure, so W2 must authorize the actual image/header content.
7. QC save and handoff use validation, CSRF protection, transaction/version checks and attributable AuditStore events. Audit issuance, verification outcomes, scoped reads, saves, handoff, revocation/reissue and rejected actions with approved minimized identifiers; exclude link signatures/secrets, credentials, DICOM headers/bytes, patient identifiers and free-text QC from shared logs. Proposed successful protected read audit commits before disclosure; mutations and their required audit commit together or do not take effect. Fail closed on source/integrity/audit failure. Concrete events and read granularity need W5 approval.
8. HTTPS and appropriate secure/HttpOnly/SameSite session cookies, neutral anti-enumeration responses, rate limiting and URL/log/referrer hygiene are required design controls. Verify middleware, proxy and logging behavior; no production configuration is asserted from repository defaults. Prevent invitation/signature capture in app/proxy/analytics logs, use no-referrer/no-store entry responses and exclude external analytics/resources on access entry pages. Signing alone does not prevent URL leakage. Already delivered bytes cannot be recalled; revocation stops subsequent reads/actions and should clear the active viewer on detected denial.
9. A signed authenticated locator may be revisited by its assigned actor while valid; replay must not create new authority or duplicate handoff. If the opaque-exchange alternative is selected, consume atomically only after successful verification; concurrent replay cannot mint another session. No reuse or replay policy is silently inferred from an API idempotency primitive.

Laravel 13.x supports built-in cookie sessions, session rotation, signed expiring URLs and request-forgery protection. These are framework capabilities, not the repository's approved clinical policy. Current official references checked 2026-10-13: [authentication](https://laravel.com/framework/docs/13.x/authentication), [signed URLs](https://laravel.com/framework/docs/13.x/urls#signed-urls), [session](https://laravel.com/framework/docs/13.x/session), [CSRF protection](https://laravel.com/framework/docs/13.x/csrf). Existing installed UrlGenerator/SessionGuard source was also inspected. No example expiry value or configuration default becomes a workspace requirement.

### Participant policies stay separate

| Participant | Policy authority | Allowed boundary proposed here |
|---|---|---|
| Admin | Owning operational domain + exact issuance/revocation capability | Issue/distribute/manage assignments and see approved operational handoff status; no clinical signing authority. Existing four worklist-read capabilities do not automatically permit issuance or image access. |
| Radiographer | Operator/Radiography professional and access authority | Exact assigned case image inspection, approved technical metadata/QC and handoff only; no site-wide list, report finalization or Member publication. |
| Radiologist | Doctor clinical/identity/assignment authority | Separate individually authenticated review, interpretation, signing/amendment contract. Reuse session/URL primitives only after its own policy approval. |
| Member / authorized recipient | Member recipient/guardian authority + Gateway/Doctor publication authority | Separate exact published output/version access. No reuse of staff image/QC permissions. Withhold on publication hold, revocation, recipient mismatch or amendment suspension; republish/reissue/notification rules require approval. |

## Owner contracts and change reach

Names for missing contracts below are descriptive responsibilities, not mandated new classes/frameworks.

| Owner | Existing inputs to reuse | Missing bounded responsibility for review |
|---|---|---|
| Member | AdminStudyReferences, existing booking/schedule/site associations | Approved minimized examination identity/context and trusted case correlation; no generic cross-domain patient-table joins. Existing minimized worklist disclosure is not identity disclosure authority. |
| Operator | AdminStudyIntakes, active staff/site/shift resolution, queue/capture association | Exact Admin issuance authority; case participant assignment/eligibility; owned technical QC, metadata and pending-review handoff/history. Queue claimant is not this entitlement. |
| Image Gateway | Study/capture associations, private objects/grants, existing viewer byte handling | Fresh exact-case image read, immutable source revision reference and approved metadata/handoff integration. Preserve ingestion and AI state/dispatch. |
| Doctor | Registered ownership boundary only | Review-ready handoff consumer/assignment/reporting in a later task. First slice may record a pending handoff through the agreed owner boundary without pretending a consumer exists. |
| Shared | AuthenticatedContext, Clock, AuditStore, sanitizer, Laravel Auth/session/validation | Technical primitives only. No shared unrestricted clinical access model or domain-table queries in Shared. |
| Admin presentation | Existing worklist/board and domain service conventions | Minimal authorized issue/revoke/reissue entry and handoff indicator; no direct writes to other domains. |

Potential reach when implementation is authorized: Admin worklist/view; owner contracts/provider bindings; case-access controller/middleware; owner persistence and services; reusable viewer Blade boundary and existing JS if necessary; localized labels; synthetic HTTP/service/concurrency/browser fixtures and regressions. Auth login routing requires assessment if the recommended existing-login option is selected. No exports/config changes are assumed; add only what the approved contract actually needs. Existing unrelated callers and public response shapes must remain compatible. A lack of durable entitlement/QC history cannot be hidden by promising zero migrations.

## Scope, dependencies and minimum delivery sequence

### Proposed in scope

- One assigned Radiographer workspace for a supported existing examination/study after durable receipt, accessible from an Admin-issued URL.
- Authorized issuance, revocation and safe reissue/reassignment according to approved W1/W3 policy; neutral invalid-access handling.
- Individual verification/session entry, exact-case protected image inspection, approved technical QC and examination metadata, atomic/versioned handoff recorded as pending review.
- Minimal Admin visibility of assignment/QC/handoff, required audit and negative-path evidence, preservation of ingestion/manual/Operator behavior.

### Out of scope and preserved behavior

- No application implementation now. No migration, route, auth grant, dependency, production read, workflow dispatch, PR, commit, push, merge, deployment or release is authorized by this planning request.
- No new full role portal, generic clinical-token framework, external identity provider, notification provider or independent microservice.
- No Doctor login/clinical authoring/finalization/signature/amendment implementation; no Member result publication/delivery; no Patient migration, Operator retirement or credential mutation.
- No manual DICOM upload prerequisite, retake/omission execution, clinical-repeat automation, multi-instance PACS redesign, new AI-processing gate or changes to Grabber queue/locator/idempotency/dispatch behavior.
- No AI-PDF exposure or generator repair in this workspace. MVP-GAP-035 invented-normality and MVP-GAP-036 facility-branding/provenance remain separate safety work; AI job state may be displayed only within separately approved disclosure. Do not inherit Operator AI-report access as Radiographer permission.
- Preserve existing manual NPZ metadata/source snapshots, DICOM bytes/private storage, clinical/consent/report history, working Operator and Member routes, accepted Admin registration/board/worklist and unrelated user changes.

### Sequence

1. Independent P1/R2/A3/D4 review of this Draft; resolve W1–W5 below and supported image-set limits. Reuse settled product direction; do not restart the whole protocol.
2. Refine this same task with approved requirements, actor/action matrix, persistence/owner and exact handoff contracts, synthetic scope and verification. Publish only after T5 review and separately authorized immutable publication. One coherent functional slice covers entry → images → QC/metadata → pending-review handoff; a link-only shell is insufficient.
3. Execute the Radiographer slice with existing capture/viewer primitives after that authorization. Return Review Required with observed evidence; do not self-accept or declare production readiness.
4. Separately authorize Doctor identity/assignment/reporting, then publication/recipient/version handling. Reuse proven URL/session primitives as needed without granting one participant another's authority. AI-report safety/provenance remediation precedes any dependent report assurance/exposure.

## Remaining material decisions and designated owners

| ID | Decision genuinely needed | Owner / latest gate |
|---|---|---|
| W1 | Exact Admin issuer/revoker/reassigner capability and permissible case/facility scope; how an existing or newly verified worker becomes the eligible individual Radiographer and how staff qualification is evidenced | Product + Operator/Radiography professional + security/technical owners; P1/R2 before T5. Existing role/portal grants are not expanded by this Draft. |
| W2 | Approved Radiographer disclosure/action matrix: DICOM including embedded headers, minimum patient/examination context, metadata edits and downloads; image-set/examination kinds supported by the single-study schema/viewer | Professional/clinical + Product + security/privacy + Member/Gateway technical owners; P1/A3 before T5. Default proposal excludes downloads and AI/report content, not approved new policy. |
| W3 | Existing individual credential login versus an independently verified passwordless candidate; entitlement lifetime/expiry anchor, verification/session assurance, revocation/reassignment/reissue/recovery and replay behavior | Security/privacy + Product + identity/Operator technical owners; A3 before T5. No duration, OTP channel, shared identity or account grant is chosen. Clinical Doctor identity remains separately attributable. |
| W4 | Minimum metadata and technical QC criteria; allowed inadequate/escalated outcomes; exact study/version binding, correction/reopen semantics and pending-review handoff ownership/consumer; approve Grabber-first receipt versus BP-021/023 release mapping and preserve independent AI dispatch | Product + Radiography professional + Operator/Gateway/Doctor technical owners; P1/R2/A3/D4 before T5. No signing/publication criteria or retake rules invented. |
| W5 | Attributable event vocabulary, successful read granularity, fail-closed read/write audit contract, data minimization/access and scope of synthetic development; applicable retention/holds/access operations for real data | Security/privacy/data + technical owners; A3/T5 for development scope. Real-data retention/external obligations are G10 dependencies; synthetic separation may be nonblocking only after explicit independent review. No earlier board or clinical-retention approval is generalized. |
| W6 | Radiologist qualification/assignment, clinical finalization/amendment; Member recipient/guardian verification, publication eligibility/version suspension and notification/recovery | Doctor clinical + Member recipient + Product/security/privacy authorities; blocking their separate slices, not this workspace if no clinical/report/recipient exposure is introduced. |
| W7 | AI missing/partial-result behavior and faithful facility provenance under MVP-GAP-035/036 | Image Gateway technical + clinical safety + Product/facility-data authority; before affected AI-report or publication exposure. Nonblocking here only while AI report access/generation changes are excluded. |

No named new approver, licensing rule, policy duration, OTP provider, clinical signing rule or publication criterion is invented. If one person holds multiple decision rights, record their attributable scoped approval; do not infer that from a product instruction alone.

## Draft atomic requirements and acceptance criteria

These local requirement IDs identify proposed obligations for independent R2 review, not a rewritten global registry. All criteria are **Draft and unchecked**. Approval of W1–W5 and their concrete contracts is needed before they can govern execution.

| Requirement / AC | Proposed observable acceptance | Source, owner and verification |
|---|---|---|
| RAD-URL-001 / AC1 | An eligible Admin can issue a URL for one domain-validated case and eligible named participant; permitted participant reaches only that workspace without requiring a separate permanent dashboard. Monitoring-only/forged/cross-facility issuance is denied. | Current clarification, MVP-DEC-044/046, W1/W3; Operator/Admin; issuance and actual HTTP/browser entry tests. |
| RAD-URL-002 / AC2 | Guest visits reveal no case/PHI; link possession or forwarding cannot authorize another actor. Correct individual verification rotates the session; every subsequent HTML/JSON/image/write request verifies exact actor/case/action authority. Expired/revoked/reassigned/deactivated access is denied even in an existing session. | Clarification and repository trust boundaries, W1–W3; Operator/security; session/forgery/revocation tests. |
| RAD-URL-003 / AC3 | A received direct-DICOM study opens through the existing renderer and protected application byte route without manual upload or NPZ prerequisites. A converted manual NPZ study is equally usable under assignment. Private locations/grants never reach public payloads. Missing/unreadable/unsupported images show unavailable and cannot support successful handoff. | Grabber/MPIPS preservation, clarified QC, W2/W4; Gateway/viewer; direct/manual synthetic fixtures and real browser rendering. |
| RAD-URL-004 / AC4 | The authorized worker saves the approved examination-specific metadata and technical QC evidence, bound to the reviewed immutable study/set revision, with their actor and time. Invalid fields, cross-case IDs, client-authored actor IDs, stale revisions and conflicting updates cannot overwrite valid state or original ingestion metadata/DICOM. | Clarification, BP-021 candidate mapping, W2/W4; Operator/Gateway; validation, version/concurrency and preservation tests. |
| RAD-URL-005 / AC5 | Only an approved acceptable/completeness outcome with required metadata permits an atomic attributable pending-review handoff. Inadequate/incomplete/escalated cases remain explicit and unhanded. Duplicate submission is safe; expiry/reassignment/study-change races fail safely. No fabricated Doctor assignment/report is created. | Clarified handoff, BP-023/028 mapping proposed under W4; Operator/Doctor boundary; handoff and race tests. |
| RAD-URL-006 / AC6 | Durable receipt, human QC, AI processing, pending Doctor review, clinical finalization and publication are distinguishable. QC neither re-ingests a study nor changes existing Grabber/NPZ queue/AI dispatch. AI-ready alone never means QC-approved/finalized/published. | MVP-DEC-046, INT-DUAL-001/INT-ASYNC-001; all owners; exact database/state-delta and regression checks. |
| RAD-URL-007 / AC7 | Issuance/access/QC/handoff/revocation events follow approved W5, identify responsible actors/cases without raw link/credential/clinical content leakage, and required audit failure prevents disclosure or successful mutation. GET/scanner/replay cannot create unintended work; rate limits and session/CSRF/return-URL boundaries hold. | Repository accountability/private-object rules, W3/W5; owner services/security; audit/failure/HTTP negative tests. |
| RAD-URL-008 / AC8 | Expired/mismatched/forwarded/unavailable flows are neutral, usable and localized, with keyboard-operable controls and clear image/QC/handoff state. Existing Admin worklist/board, Operator capture/viewer, Member auth/booking, direct DICOM and NPZ/AI behaviors remain compatible; no new clinical/publication exposure. | Clarification and preserved slices; presentation/all owners; focused regressions, browser and source/response inventories. |

## Required verification for a later authorized Executor

Use isolated synthetic data, private-object doubles and existing test infrastructure. No production systems, real patient data, external messages or real AI provider calls. Report actual commands, backend/package versions, results, counts, skips and limitations; historical results cannot substitute for new boundary tests.

**Required negative-path matrix:**

- Issuer lacks exact issuance capability; monitor-only or manage-only actor; nonexistent/corrupt/unowned admission–booking–session–facility–study tuple; ineligible/unverified/deactivated worker; forged actor/site/case/action parameters.
- Guest, wrong participant, forwarded/tampered/guessed/expired/not-yet-valid/revoked/reassigned URL; stale session after entitlement/account/qualification revocation; mixed-case tabs and old link generation. No PHI, case-existence leak or private-object query before successful authorization.
- Signature/secret in app/proxy/analytics/referrer paths; off-origin/open redirect; session fixation; logout/relogin as another person; CSRF failure; verification guessing/rate limit. If opaque exchange is approved: scanner GET, parallel consume, failed verification and replay after consumption.
- Forged DICOM study/object selector; correct actor/wrong case; revoked permission immediately before byte delivery; missing object/checksum failure/decoder timeout/corrupt/unsupported DICOM; no raw storage URL/grant leakage. Verify actual headers and renderer failure state, not only button visibility.
- Metadata extra fields, invalid vocabulary/length, escaped markup and stale revision; save racing reassignment/revocation; duplicate/conflicting handoff; incomplete QC/metadata and inadequacy; changed image revision invalidating prior approval. Prove rollback of domain mutation/history on required audit/commit failure.
- Missing/failing owner source and broken association; audit append/commit failure for reads and writes; old successful values withheld after denial; preserved history on reissue. Assert exact allowed database writes and zero unintended queue, AI/report, consent, Patient or Member-publication mutation.

**Existing regression anchors:** `tests/Feature/Admin/AdminCentralizedStudyWorklistTest.php`, `tests/Feature/Admin/AdminFacilitySessionOperationsTest.php`, `tests/Feature/Operator/OperatorFieldOperationsSlice3Test.php`, `tests/Feature/Operator/OperatorPortraitDicomViewerTest.php`, `tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php`, `tests/Feature/Operator/OperatorAiPacsIntegrationTest.php`, `tests/JavaScript/operator-dicom-viewer.test.mjs`, and the existing architecture/security and auth tests selected for affected callers. New workspace fixtures must exercise both direct and converted study paths.

Require HTTP tests of each protected route (including DICOM and mutations), service-bypass tests, SQLite functional coverage and supported MySQL transaction/concurrency coverage where production locking/uniqueness matters. Actual browser evidence must cover Admin issuance → individual entry → rendered synthetic DICOM → QC/metadata → pending-review handoff, keyboard behavior and revoked/expired denial. Run existing JS checks and `npm run build` if viewer assets change; use installed tools only. Record baseline failures separately; the historical combined-browser ResizeObserver limitation and installed/locked dependency drift from the worklist acceptance record are not silently waived for this task. Formatter/static/architecture checks and `git diff --check` complete the changed scope.

## Gate assessment and stop conditions

| Gate | Draft assessment |
|---|---|
| B0 | Product clarification establishes bounded purpose and preserved constraints; no fresh overall gate certification. |
| P1 | Earliest materially incomplete gate: concrete Radiographer eligibility/actions, supported exam/image set and QC/handoff/correction semantics require W1/W2/W4. |
| R2 | Proposed RAD-URL-001–008 map sources/owners/tests; not approved global requirements or passed R2. |
| A3 | Reuse and trust boundaries identified; W3/W5 and concrete entitlement/QC/handoff persistence remain unapproved. |
| D4 | One functional workspace proposed; clinical/report/publication slices separated. Readiness depends on preceding decisions and baseline verification. |
| T5 | **NOT PASSED. Draft — Review Required.** Blocking policy, independent evaluation and immutable publication remain. |
| E6/V7/R8/A9/G10 | No workspace execution, verification, acceptance or release in this planning cycle. |

Future Executor must stop for missing/contradictory approvals, unsupported exam/image set, inadequate owner contracts, unsafe baseline drift, scope expansion, new disclosure/identity grants, or unapproved side effects. In particular stop if access requires bypassing Operator authorization, fabricating a staff/Doctor profile, treating cleared queue claimant as active QC ownership, mutating received DICOM/AI report provenance, or implementing clinical/publication policy to make a handoff appear complete. No silent retake/multi-instance/AI lifecycle redesign.

## Side-effect authorization and review handoff

**Authorized now:** read-only reconnaissance and creation of this one proposed Draft file. No application or test mutation; no external document edits, secrets, production access, workflow dispatch, Git commit/push, PR, merge or deployment. Existing approved decisions and historical records remain unchanged.

**Required next action:** independent P1/R2/A3/D4/T5 evaluation of this Draft and attributable disposition of W1–W5. Record approval changes in this same stable task path; retain exact source/baseline identity. Separate publication authorization is required before creating a governing immutable task revision. No Executor launcher is emitted. Independent review may return refinements or a narrower contract; it must not infer T5 from this file's existence.

**Planning verification observed:** clean preflight; baseline object/tree identity and empty tracked diff; direct source/route/schema/test-definition review; external Draft fetch with provider timestamp; current official Laravel documentation; repository intelligence narrowed to exact source and verified directly. Codebase Memory final coverage generation `2026-10-13T11:54:06Z` reports matching metadata/no recorded issue for principal PHP/JS paths, with partial Blade parsing in the study view at lines 3/170–171/274 and capture view at lines 3/5–79, read directly. Graphify's existing September graph references obsolete context paths and returns truncated discovery; it was not used as authority or rebuilt. Neither graph is proof of completeness. Application tests/browser/CI, remote integration and production configuration remain unverified in this cycle.

**Expected terminal outcome:** **PLANNING REVIEW REQUIRED. No implementation authorized.** A later authorized Executor must end Review Required with observed evidence or Planning Required on a stop; final implementation acceptance and release are separate responsibilities.
