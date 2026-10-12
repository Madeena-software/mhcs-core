# MVP gap register

## Admin-first gaps and authority reconciliation

Priority changed under [MVP-DEC-044](decision-log.md#admin-first-priority-decision); approval extends to the seven-document rebaseline, not unresolved application policies. These new entries map the governing task's C1–C8 conflicts. Owners are designated authority roles, not new appointments. Existing gap IDs/status history below are retained; no historical gap is closed or reopened solely by reprioritization.

| ID / task issue | Gap and impact | Owner | Temporary treatment | Status | Resolution trigger / closure evidence |
|---|---|---|---|---|---|
| MVP-GAP-027 / C1 | Functional Admin facility-session board, broader study worklist and eventual portal transition remain incomplete | Product, Member/Operator technical and security/privacy authorities | MVP-DEC-047 selects the [validated board](../../.agents/tasks/admin-facility-session-operations.md); preserve registration/portals; PR #8 is only recommended for supersession | open | MVP-DEC-053 settles internal D4-A–I/X1 policy. Separate T5 contract sufficiency and explicit sequential execution permissions are recorded; exact publication verification and eventual implementation evidence remain; retention/access policy is a production dependency subject to recorded separate synthetic-development separation review. Board acceptance cannot close broader worklist/transition scope |
| MVP-GAP-028 / C2 | Temporary radiographer/radiologist workspace credentials, qualifications, assignments, permitted actions and session/authentication mechanics remain unresolved | Product, clinical, security/privacy and technical authorities | Old blanket Doctor exclusion no longer defines target planning; expose no new clinical workflow on that basis; keep accountable actors separate | open | Attributable clinical/security authorization and a validated workspace contract with verification |
| MVP-GAP-029 / C3 | Business Site Staff access map conflicts with scoped Operator AI-PDF access; Admin content scope, clinical finalization/amendment, recipient/guardian authority and temporary-result links remain unresolved | Product, clinical and security/privacy authorities | Preserve both authority chains; treat AI output as unverified screening only; grant no new content access or Member publication; do not choose expiry/revocation/recovery defaults | open | Explicit reconciled access/finalization/recipient/link contract and authorization/audit/negative-path evidence before exposure |
| MVP-GAP-030 / C4 | Reusable master consent and Basic Examination bypass in urgent field direction need reconciliation with business per-visit/staged rules | Product and clinical/privacy authorities | Preserve version history, withdrawal blocking, visit confirmation, audit, and zero fabricated measurements/earnings; do not generalize the interim scope | open | Attributed source reconciliation and approved dependent clinical contract |
| MVP-GAP-031 / C5 | Earlier privacy approval correction and storage/DICOM-access exceptions do not establish broad production policy | Clinical, security/privacy/legal and data owners | Retain MVP-DEC-017→018→019, 032→035→036 and 038→041 scope; MVP-DEC-053 permits only specified internal aggregate design; no applicable board read-audit retention scope verified, no new duration/encryption/deletion/production operation | open | Applicable operational read-audit retention/access policy before production/real-data/release; nonblocking for synthetic development only if independent T5 confirms separation; MVP-GAP-019/021/023 remain |
| MVP-GAP-032 / C6 | Generic CSV/XLSX schema/templates, identity matching, duplicate/conflict/invalid-row handling, atomicity and privacy are unapproved | Product, Member-domain and privacy/security owners | Keep the one-time import guidance and Prestige exceptions historical; no real roster, credential creation or invented import rules | open | Approved generic import contract/templates and validation/privacy evidence; related MVP-GAP-003/020 remain separately scoped |
| MVP-GAP-033 / C7 | Original documentation rebaseline and lean-docs cleanup reviewed; new current planning overlay still requires review | Independent Planner/Reviewer | PR #5 acceptance retained; PR #6 merged at `fb25354311121fc8163256cd0530d9cbcb66bb68` with recorded independent acceptance; removed local business copies are historical provenance | prior bounded review resolved; new overlay review required | Independent review of this planning revision, supplied PO decisions, stable IDs and references; preserve original task identities and historical evidence |
| MVP-GAP-034 / C8 | Broader application acceptance and production certification remain incomplete; bounded Admin registration is accepted and integrated | Planner/Reviewer and applicable release/domain authorities | PR #7 integrated at `fc37002d106b839c0b881e7dec7021c91389f501`; accepted registration application `21d279831455d32fe2a707e8d306af861c0bbb08` and its limitations remain pinned in the acceptance record | open beyond bounded registration | Exact task/review and current verification for each broader scope; registration acceptance does not close all application gaps or G10 |

**PR #9 final internal approval:** [MVP-DEC-053](decision-log.md#final-internal-d4-approval--2026-10-13) records attributable Product Owner approval of D4-A–I with explicitly confirmed internal product/technical/security/privacy-policy authority. Domain-owned reads, internal arrival-association validation, fresh exact authorization, trusted managed-facility scope, exact internal operational counts including one, minimized attributable audit/failure withholding and narrow X1 A are internally approved. Separate Reviewer /root/t5_reviewer confirms contract sufficiency; the new human T5 handoff authorizes bounded source/test, isolated synthetic verification and one Draft stacked implementation PR after exact validated publication is verified. Audit retention/access operationalization has no verified applicable policy and remains a production/real-data/release dependency; separate Reviewer confirmed synthetic-development separation. This is not external legal, hospital/clinical, real-data or deployment approval, independent acceptance or completed implementation tests. [Approved policy](../../.agents/tasks/admin-facility-session-operations.md#d4-technical-assessment-and-approved-internal-policy) addresses missing arrival FKs through narrow internal Member validation and undifferentiated missing/outage claims through denial without an outage diagnosis. [AC matrix](../../.agents/tasks/admin-facility-session-operations.md#t5-readiness-matrix) and [review package](../../.agents/tasks/admin-facility-session-operations.md#independent-exact-revision-t5-review-package) prepare independent review. Exact counts still permit participation inference; verify independent gates and scope, without invented suppression thresholds. X1 A correction remains unimplemented; board-only hiding would not satisfy it. T5 CONTRACT VALIDATED; EXACT PUBLICATION VERIFICATION REQUIRED BEFORE EXECUTION.

MVP-DEC-051 strengthens the [separate Patient target](beta-scope.md#canonical-patient-identity-target): single canonical identity without duplication or mandatory permanent login, facility-scoped MRNs, initial NIK absence/provisional identity, controlled ambiguous matches and preserved history/references/audit. Existing Member account/global-MRN/registration constraints must be explicitly reconciled in that later contract; no board migration or relaxation of accepted registration.

These gaps are blocking at their affected application/authority/review stages, not a reason to invent policy during the approved documentation rebaseline. The target outcomes and source links are in [scope](beta-scope.md#admin-first-operational-scope); sequencing is in [roadmap](roadmap.md#admin-first-planning-sequence).

## AI report safety and provenance follow-up

**Observed 2026-10-13 at `fc37002d106b839c0b881e7dec7021c91389f501`.** Read-only source confirmation, not a runtime incident investigation or a new clinical approval. These risks remain separate from the Admin board and are not hidden in its remediation or accepted as safe defaults.

| ID | Finding and impact | Evidence | Owner | Temporary treatment | Status / target phase | Closure evidence / trigger |
|---|---|---|---|---|---|---|
| MVP-GAP-035 | Missing AI findings/impression can be replaced by normal chest narrative and a no-abnormality impression, making absent results appear clinically normal | `app/Modules/ImageGateway/Application/Jobs/ProcessAiPacsStudy.php`: lines 460–466 persist report metadata without findings/impression; lines 733–735 use normal-text null fallbacks; lines 773–774 pass them to derived PDF generation | Image Gateway technical, clinical safety and Product authorities | Record risk now; no clinical edit or new board report exposure. Existing AI disclaimer does not establish correctness of substituted findings. Do not claim any real-patient incident without evidence | open; separate safety contract before affected AI-report assurance or new clinical/publication exposure | Approved unavailable-result and faithful-source/provenance policy; synthetic missing/null/partial/actual-findings tests proving no invented normality; independently reviewed source-to-PDF evidence |
| MVP-GAP-036 | Missing facility provenance can produce Prestige facility/organization/address branding for another examination, misattributing report origin | `app/Modules/ImageGateway/Infrastructure/AiPacs/AiPacsLaravelDerivedPdfGenerator.php`: lines 99–103 contain Prestige/organization/address fallbacks; inspected job provenance at lines 763–780 does not supply these facility fields | Image Gateway technical, facility data/provenance and Product authorities; privacy/clinical review as applicable | Keep distinct provenance risk visible; no branding/code edit here and no assertion of an affected patient/facility. Existing immutable PDF storage preserves an artifact, not proof its facility provenance is correct | open; separate provenance contract before multi-facility reporting/publication assurance | Approved examination-to-facility source and missing-provenance behavior; synthetic two-facility and absent/inconsistent-source tests; independent artifact/lineage review |

Source findings link to existing `IMG-AI-007/008/012`, `INT-PROV-001` and `INT-DISC-001` in their original [AI](../../.agents/tasks/ai-pacs-integration.md) and [Operator integration](../../.agents/tasks/operator-ai-pacs-integration.md) contracts. They do not rewrite those immutable execution revisions. Follow-up planning must determine the faithful extraction/provenance contract and exact verification before any repair task is validated. No report was generated, downloaded, modified, revoked or investigated against production in this cycle. Existing MVP-GAP-029/031 clinical and disclosure policy dependencies remain open.

## Historical beta gap state — retained

The table and addenda below retain the 2026-08-21 synchronization and earlier history. Their portal names, deferred sequence, and scope exclusions refer to that period. MVP-GAP-003/007/008/017 are relevant to the new target but their historical rows are not rewritten; use the active entries above for current planning dependencies. Closed rows remain closed only for their recorded bounded acceptance. MVP-GAP-026 remains an unresolved reported credential-exposure/security conflict; no exception, remediation, or secret handling is authorized here.

Open gaps are deliberate visibility, not completion claims. A seeded gap may be closed only with repository evidence and the verification required by its owning task.

Reconciled against `841465faac4e8b1dd3670103052a9c4f075bfd04` on 2026-08-21.
All remaining MVP delivery proceeds sequentially on `mhcs-core` `main`.
MPIPS remains a separate repository. Its processing service/network boundary is
private and is the only internal network service boundary. A gap is not closed
because implementation exists; the owning evidence and acceptance conditions
still apply.

| ID | Gap | Affected component or flow | Beta impact | Temporary control | Target MVP task or phase | Status | Revisit trigger | Notes |
|---|---|---|---|---|---|---|---|---|
| MVP-GAP-001 | Public registration unavailable | Member Portal | Controlled users only | Existing controlled accounts or local/testing-only synthetic seed data | MVP-01 | open | Owner approves public onboarding scope and security/privacy evidence | No public route is exposed; MVP-01 does not close this gap. |
| MVP-GAP-002 | Online registration remains unwired | Member identity / Member Portal | Online source/state path is not beta entry | Do not expose or depend on the path | MVP-01 or post-MVP identity review | open | Approved route, policy, and verification evidence exist | The source/state path is tracked but not approved for exposure. |
| MVP-GAP-003 | B2B bulk import unavailable | Member administration | No bulk provisioning | Controlled individual account preparation | MVP-08 | deferred | Signed agreement data and import contract exist | Separate later task; do not implement before MVP-01 without reprioritization. |
| MVP-GAP-004 | Initial beta limited to adults | Member Portal | Children cannot enter beta | Existing adult Member only | MVP-01 / post-MVP identity review | open | Child policy, guardian access, and tests are approved | No child flow is exposed. |
| MVP-GAP-005 | Guardian and dependent flows deferred | Member Portal | Dependent access unavailable | No dependent selection or shared credentials | Post-MVP identity review | deferred | Approved guardian workflow and authorization evidence | WP-04 foundation is not a beta exposure claim. |
| MVP-GAP-006 | Identity-verification UI remains incomplete | Member and Operator modules | The bounded Operator front-desk journey exists; Member/public and broader identity journeys remain unavailable | Expose only the authenticated, site-scoped Operator verification slice | MVP-04B / post-MVP identity review | open | Broader UI, privacy, storage, and audit policy evidence pass | MVP-04B does not close Member/public identity exposure or production policy. |
| MVP-GAP-007 | Doctor Portal excluded | Internal clinical workflow | No internal doctor surface | Teleradiology is external | MVP-06 and later owner review | deferred | Owner explicitly changes the actor boundary | This is an approved exclusion. |
| MVP-GAP-008 | Internal doctor assignment and report authoring unavailable | Doctor workflow | No internal MHCS doctor workflow | External physician/reporting boundary | Post-MVP clinical decision | deferred | Approved Doctor scope and clinical contract | Not silently replaced by Operator behavior. |
| MVP-GAP-009 | Operator Portal incomplete | Operator Portal | The clinic-day foundation now includes arrival, identity verification, paper consent, verified check-in, ticketing, queue admission and actions, vital signs, questionnaire, basic-examination completion, X-ray readiness, and capture access; the complete Operator MVP remains unavailable | Expose only the bounded authenticated site-scoped journey and its authorized workstation surfaces | MVP-04 | open | Complete bounded Operator MVP and authorization evidence pass | Keep open: broader queue/attendance, walk-ins, public display, operational administration, and long-term Operator requirements remain incomplete. |
| MVP-GAP-010 | Shared admin shell and Member administration foundation | Shared administrator interface / Member administration | Bounded initial controlled admin interface and Member-owned account administration are implemented; broader administration remains out of scope | Maintain controlled repository/seed operations and expose no direct cross-module editing | MVP-02 | closed | Reopen only if the bounded MVP-02 surface regresses | Focused evidence closure executed from baseline `03ba160f2080a6924ae64402e48be990cc9c7ffd` at execution commit `f7a3eaeb54b97642bd61d545ebcbf5e26f69f93c`; 32 focused tests and 283 assertions passed. Closed only for the bounded MVP-02 surface; evidence: `docs/mvp/evidence/mvp-02-shared-admin-shell-member-administration.md`. Shared presentation does not transfer Member ownership; Operator and Image Gateway administration remain separate module gaps. |
| MVP-GAP-011 | Member service request or booking unavailable | Member Portal | Controlled adult B2C personal-points radiology booking is implemented; broader financial, clinical, and operational flows remain out of scope | Controlled adult Members, active catalogue/site/schedule, and local/testing synthetic personal-point funding only | MVP-03 | closed | Reopen if the bounded acceptance evidence regresses or broader scope is approved | Closed after the booking-ownership and schedule-integrity remediation checks passed. The bounded slice now proves trusted Member binding, booked schedule immutability except close/no-op, exact booking audit filtering, controlled failure auditing, arbitrary-precision point comparison, and Filament service-path authorization. Real payment/top-up, B2B, cancellation/refund, Operator, Image Gateway, FHIR, privacy, credential, deployment, and CI gaps remain open. Evidence: `docs/mvp/evidence/mvp-03-controlled-b2c-radiology-booking.md`. |
| MVP-GAP-012 | Queue and attendance workflow incomplete | Operator Portal | Queue admission, claim/call/start, vital signs, questionnaire, basic-examination completion, X-ray readiness, and X-ray claim/call exist; broader queue, attendance, public-display, and clinical scope remains incomplete | Expose only authorized private Operator workstation behavior; keep broader/public paths closed | MVP-04 | open | Remaining queue, check-in, ticket, attendance, and audit behavior passes | The current clinic journey is substantially advanced but the full gap criteria are not satisfied. |
| MVP-GAP-013 | Image Gateway intake/correlation milestone not formally reconciled | Image Gateway | Durable capture, correlation, queued processing, DICOM result persistence, validation, replay/idempotency, and Operator study access exist; full MVP-05 milestone evidence is not yet reconciled | Keep only the observed authorized capture/study paths exposed | MVP-05 on `mhcs-core` `main` | implementation exists; formal reconciliation pending | Bounded MVP-05 evidence and acceptance review pass | Do not infer full MVP-05 or WP-23 completion from implementation alone. |
| MVP-GAP-014 | External teleradiology routing unavailable | Image Gateway / external participant | No external study routing | Keep boundary private and unconfigured | MVP-06 on `mhcs-core` `main` — Image Gateway / external integration | deferred | Approved external contract and adapter evidence exist | Image Gateway owns the module-side routing boundary; external teleradiology remains an external participant. No conformance claim. |
| MVP-GAP-015 | Automated report return unavailable | Image Gateway / Operator | No automated report callback | Manual handling is the planned fallback | MVP-06 on `mhcs-core` `main` — Image Gateway automated report-return behavior | deferred | Supported callback contract and verification pass | Image Gateway owns automated report-return behavior; Operator manual handling is separately owned. |
| MVP-GAP-016 | Manual Operator report handling not yet implemented | Operator Portal | No report receipt/publication flow | Do not publish results | MVP-06 on `mhcs-core` `main` — Operator with Image Gateway publication/report contract dependency | open | Controlled upload, review, publication, and audit tests pass | Operator owns Operator report handling; Image Gateway owns publication/routing contracts; both are developed sequentially on `mhcs-core` `main`; the dependency is between module contracts, not branches. |
| MVP-GAP-017 | Member result visibility unavailable | Member Portal | No result access | No result route | MVP-07 on `mhcs-core` `main` — Member with Image Gateway publication boundary | deferred | Ownership, publication state, and private-object tests pass | Member owns result visibility; Image Gateway owns the publication boundary; both are developed sequentially on `mhcs-core` `main`. |
| MVP-GAP-018 | Forward-only UUID migration approval pending | Shared identity/database | Deployment/migration decision remains open | Do not reopen or rewrite WP-04 migration | MVP-09 / approval gate | open | Owner approves migration and compatibility evidence | A fresh beta database does not settle production migration. |
| MVP-GAP-019 | Production object-storage policy unresolved | Image Gateway | A working production provider exists, but complete retention, encryption, deletion, compliance, and policy closure are not established | Keep objects private, opaque-keyed, grant-authorized, and subject to the current bounded controls | MVP-09 / approval gate | open | Storage, retention, encryption, deletion, and access policy approved | A working provider does not establish complete production policy conformance. |
| MVP-GAP-020 | Production credential-delivery process unresolved | Member administration | Generic production credential delivery, recovery, and secret-handling remain unproven | Use only the bounded controlled/Prestige operational handling authorized for its fixture; do not generalize it to B2B delivery | MVP-01 and MVP-08 | open | Generic delivery, secret-handling, and recovery evidence approved | Prestige-specific operational credential handling does not close generic credential delivery. No credential value is recorded here. |
| MVP-GAP-021 | Privacy, retention, deletion, and anonymization procedures unresolved | All identity/clinical/object flows | Production data handling not approved | Synthetic/controlled data and no destructive operation | MVP-09 / approval gate | open | Privacy/legal owner approves policy and evidence | Do not invent lawful basis or retention. |
| MVP-GAP-022 | Bounded production deployment decision reconciled | Deployment | A bounded production deployment and observed verification exist for the current operational fixture | Keep release, security, privacy, and conformance gates separate from bounded deployment evidence | MVP-09 | closed (bounded deployment) | A new deployment scope or release decision is requested | Deployment closure does not equal complete production readiness, security/privacy closure, or WP-28 completion. Evidence: `docs/mvp/evidence/prestige-production-rehearsal-data.md`. |
| MVP-GAP-023 | CI/release and deployment evidence gaps remain for the beta model | CI/deployment | Deployment and verification evidence now exists, but complete integration, release, rollback, security/privacy, and conformance evidence remains open | Run focused checks per task; reserve full pipeline and release decision for the applicable gates | MVP-09 | open | Integration/release verification produces complete current evidence | Existing workflow evidence supports the bounded deployment only; it is not a complete release/conformance record. |
| MVP-GAP-024 | Operator-owned administration remains incomplete | Operator administration / MVP-04 queue and attendance | Bounded site, profile, assignment, eligible-shift, shift-assignment, and arrival administration exists; protocol, queue exceptions, and broader operational configuration remain unavailable | Expose only the bounded resources behind exact claims and application services | MVP-04 | open | Complete Operator-owned administration, authorization, audit, and focused flow tests pass | The shared administrator interface does not transfer Operator records or rules to a generic owner. |
| MVP-GAP-025 | Image Gateway operational administration required by Image Gateway MVP flows not yet implemented | Image Gateway operational administration / MVP-05 study intake and correlation | Current capture and processing capabilities exist, but complete administrator visibility for intake status, correlation failures, retries, terminal failures, and compliance operations is not proven | Keep operational access administrator-only and do not expose a separate end-user application or direct Member/Operator mutation | MVP-05 on `mhcs-core` `main` | open | Image Gateway operational administration, authorization, audit, and failure-flow tests pass | Gateway-owned gap. Storage, processing, retry, publication, and compliance operations remain Image Gateway-owned. |
| MVP-GAP-026 | Active Operator credential exposed in public repository/history | Security boundary / Operator authentication | A separate read-only security triage reports an active publicly accessible Operator credential exposure in the current repository/history | No remediation was performed in this documentation task; do not reproduce, rotate, or generalize the exposure as a safe normal MVP deferral | Security remediation / explicit exception decision | open | Explicit security exception or superseding authority is approved, or targeted credential rotation and source cleanup are completed | This is an unresolved conflict with the normal MVP policy. No approved security exception was found or declared here; no credential value or identifying information is recorded. |

The dated addenda below preserve historical gap state as of their recorded date;
they do not override the current table above.

## MVP-03 admin, audit, and browser closure addendum — 2026-08-05

MVP-GAP-011 remains closed for the bounded controlled adult B2C booking slice.
The published closure task verified trusted dual-role Member ownership, the
actor-state denial matrix, exact successful-booking audit association,
Member-scoped failure auditing, permitted Member-owned offering and schedule
admin actions, and a Chromium browser layer with no final-page console or
JavaScript smoke errors. Broader payment, cancellation, Operator, Image
Gateway, privacy, deployment, CI, and production gaps remain unchanged.

## MVP-04A remediation addendum — 2026-08-05

The prior remediation is committed at
`2e08eae74e49b0ba54461ba8787a0ec8e0ece062`. The bounded closure task runs from
that baseline. The committed closure candidate is
`f49da5991b21b9a13abb435539db1955362ef639`; the evidence-verification task
made only uncommitted documentation changes and did not create an execution
commit. It corrects confirmation lifecycle, site switching, trusted
local/stable site correspondence, and the public arrival mutation boundary.
`MVP-GAP-009`, `MVP-GAP-012`, and `MVP-GAP-024` remain open: MVP-04 is still
partial, and queue, check-in, ticketing, clinical, broader attendance, and
complete Operator administration remain out of scope.

## MVP-04B front-desk identity verification addendum — 2026-08-06

The bounded MVP-04B task adds the authenticated Operator identity-verification
slice. Its remediation binds Member-side reads and asset grants to a fresh
trusted open case, fails closed on missing current evidence, enforces one
database-backed open claim per Operator, and keeps free-text reasons out of
shared audit while keeping MVP-GAP-006, MVP-GAP-009, MVP-GAP-012, and
MVP-GAP-024 open.
The complete Operator Portal, Member/public identity journey, consent,
check-in, ticketing, queues, clinical behavior, privacy/retention policy,
production storage policy, deployment, and production readiness remain open.

## MVP-04B final boundary closure addendum — 2026-08-06

The final boundary closure adds explicit unavailable-evidence handling,
persisted account and portal revalidation, and shared-boundary asset-slot
enforcement. MVP-GAP-006, MVP-GAP-009, MVP-GAP-012, and MVP-GAP-024 remain open;
the broader Operator portal, consent, check-in, ticketing, queues, privacy,
deployment, and production gaps are not closed.

## MVP-04B audit identifier sanitizer remediation addendum — 2026-08-07

The shared audit sanitizer no longer rejects valid canonical UUID target or
allowed operational metadata values because one UUID segment is numeric.
Standalone raw numeric identifiers and the existing sensitive-data denials
remain fail-closed. `MVP-GAP-006`, `MVP-GAP-009`, `MVP-GAP-012`, and
`MVP-GAP-024` remain open; this remediation does not close the broader
Operator/Member identity, consent, check-in, queue, privacy, deployment, or
production gaps.

## MVP-04C paper consent confirmation addendum — 2026-08-07

The bounded MVP-04C task adds one Member-owned paper-consent confirmation for
an arrived booking after matched identity verification, with an optional
validated encrypted private scan. `MVP-GAP-006`, `MVP-GAP-009`, `MVP-GAP-012`,
and `MVP-GAP-024` remain open. General consent administration, clinical
behavior, check-in, ticketing, queues, privacy/retention approval, production
storage, deployment, and production readiness remain open.

## MVP-04D verified check-in and paper ticket issue addendum — 2026-08-07

The bounded MVP-04D task adds one Member-owned `arrived` to `checked_in`
transition and one Operator-owned private paper ticket after the existing
matched identity and confirmed `Informed Consent` / `V1` checks. `MVP-GAP-006`,
`MVP-GAP-009`, `MVP-GAP-012`, and `MVP-GAP-024` remain open. Queue ordering,
ticket generation policy, public/Member ticket exposure, clinical behavior,
consent-scan access, privacy/retention approval, production storage,
deployment, and production readiness remain open.

## 12 August MVP delivery target — Operator priority — 2026-08-10

All active MVP work targets the 12 August controlled-deployment candidate.
The Operator site flow is first priority; all listed gaps remain open until
their own task evidence and separate release verification exist.

| Existing gap | 12 August treatment | Required temporary control and closure evidence |
|---|---|---|
| MVP-GAP-003 — B2B bulk import | Deferred from the Operator-priority task | The local CSV contract remains archived for later approved import work; no real roster or credential delivery is in this objective. |
| MVP-GAP-009 and MVP-GAP-012 — Operator and queue flow | Prioritized: site-scoped calling, Printer Station, LCD Station, approved assessment, and X-ray readiness | Preserve site/shift authorization, private ticket data, atomic claims, audit, offline Printer/LCD behavior, and focused flow evidence. |
| MVP-GAP-013 — Image Gateway intake | Separate workstream dependency, not an Operator-priority task blocker | Do not implement MinIO, NPZ/gain validation, conversion, AI, or MPIPS on the core branch. |
| MVP-GAP-019 — object-storage policy | Remains open | Do not claim retention, encryption, or storage-policy closure without approved configuration and release evidence. |
| MVP-GAP-020 — credential delivery | Deferred with B2B import | No plaintext password is retained, logged, or committed. |
| MVP-GAP-021 through MVP-GAP-023 — privacy, deployment, and release evidence | Remain open release gates | Deployment may proceed only after recorded verification, a release decision, and observed evidence. |
