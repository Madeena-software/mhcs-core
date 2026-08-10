# Beta gap register

Open gaps are deliberate visibility, not completion claims. A seeded gap may be closed only with repository evidence and the verification required by its owning task.

The register distinguishes parallel delivery ownership from final MVP scope.
Gateway-owned gaps stay in the Image Gateway workstream; Member and Operator
gaps that need an unmerged Gateway contract remain deliberately deferred. Final
beta completion still requires the Gateway branch to merge into `main` and the
merged integration gate to pass.

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
| MVP-GAP-009 | Operator Portal not yet complete | Operator Portal | The bounded foundation, arrival, front-desk identity-verification, paper-consent, verified check-in, and private paper-ticket slice is implemented; the complete Operator MVP remains unavailable | Expose only the bounded authenticated site, attendance, arrival, verification, consent, check-in, and ticket slice | MVP-04 | open | Complete bounded Operator MVP and authorization evidence pass | This task does not close the gap; ticket generation policy, queue, general consent, and clinical behavior remain out of scope. |
| MVP-GAP-010 | Shared admin shell and Member administration foundation | Shared administrator interface / Member administration | Bounded initial controlled admin interface and Member-owned account administration are implemented; broader administration remains out of scope | Maintain controlled repository/seed operations and expose no direct cross-module editing | MVP-02 | closed | Reopen only if the bounded MVP-02 surface regresses | Focused evidence closure executed from baseline `03ba160f2080a6924ae64402e48be990cc9c7ffd` at execution commit `f7a3eaeb54b97642bd61d545ebcbf5e26f69f93c`; 32 focused tests and 283 assertions passed. Closed only for the bounded MVP-02 surface; evidence: `docs/mvp/evidence/mvp-02-shared-admin-shell-member-administration.md`. Shared presentation does not transfer Member ownership; Operator and Image Gateway administration remain separate module gaps. |
| MVP-GAP-011 | Member service request or booking unavailable | Member Portal | Controlled adult B2C personal-points radiology booking is implemented; broader financial, clinical, and operational flows remain out of scope | Controlled adult Members, active catalogue/site/schedule, and local/testing synthetic personal-point funding only | MVP-03 | closed | Reopen if the bounded acceptance evidence regresses or broader scope is approved | Closed after the booking-ownership and schedule-integrity remediation checks passed. The bounded slice now proves trusted Member binding, booked schedule immutability except close/no-op, exact booking audit filtering, controlled failure auditing, arbitrary-precision point comparison, and Filament service-path authorization. Real payment/top-up, B2B, cancellation/refund, Operator, Image Gateway, FHIR, privacy, credential, deployment, and CI gaps remain open. Evidence: `docs/mvp/evidence/mvp-03-controlled-b2c-radiology-booking.md`. |
| MVP-GAP-012 | Queue and attendance workflow incomplete | Operator Portal | The bounded attendance, arrival, identity-verification, paper-consent, verified check-in, and private paper-ticket foundation exists; queue and later examination stages remain unavailable | Do not expose queue, ticket generation policy, public LCD, or clinical behavior | MVP-04 | open | Remaining queue, check-in, ticket, attendance, and audit behavior passes | MVP-04D advances only a consent-confirmed booking to `checked_in`; it adds no queue stage or Member-facing ticket. |
| MVP-GAP-013 | Image Gateway study ingestion or association unavailable | Image Gateway | No study-to-examination traceability flow | No study ingestion exposure | MVP-05 — Image Gateway workstream | deferred | Intake, correlation, idempotency, and failure evidence pass | Gateway-owned gap. Security primitives do not prove intake. |
| MVP-GAP-014 | External teleradiology routing unavailable | Image Gateway / external participant | No external study routing | Keep boundary private and unconfigured | MVP-06 — Image Gateway workstream | deferred | Approved external contract and adapter evidence exist | Gateway-owned gap. No conformance claim. |
| MVP-GAP-015 | Automated report return unavailable | Image Gateway / Operator | No automated report callback | Manual handling is the planned fallback | MVP-06 — Image Gateway workstream | deferred | Supported callback contract and verification pass | Gateway-owned automated-routing gap; Operator manual handling is separately owned. |
| MVP-GAP-016 | Manual Operator report handling not yet implemented | Operator Portal | No report receipt/publication flow | Do not publish results | MVP-06 — main workstream, depends on merged Gateway contract where required | open | Controlled upload, review, publication, and audit tests pass | Main-workstream gap; record the Gateway dependency as deferred rather than implement Gateway work. |
| MVP-GAP-017 | Member result visibility unavailable | Member Portal | No result access | No result route | MVP-07 — main workstream, depends on merged Gateway publication contract | deferred | Ownership, publication state, and private-object tests pass | Main-workstream gap; publication remains Gateway-owned. |
| MVP-GAP-018 | Forward-only UUID migration approval pending | Shared identity/database | Deployment/migration decision remains open | Do not reopen or rewrite WP-04 migration | MVP-09 / approval gate | open | Owner approves migration and compatibility evidence | A fresh beta database does not settle production migration. |
| MVP-GAP-019 | Production object-storage policy unresolved | Image Gateway | Production binary policy is unknown | Use existing provider-neutral private boundary only | Image Gateway workstream, then MVP-09 / approval gate | open | Storage, retention, encryption, and access policy approved | Gateway-owned policy gap. No production bucket is configured. |
| MVP-GAP-020 | Production credential-delivery process unresolved | Member administration | Controlled credentials cannot use an approved production handoff | Local/testing seeder prints a newly generated credential once to its interactive console only | MVP-01 and MVP-08 | open | Delivery, secret-handling, and recovery evidence approved | No email/SMS/print delivery is claimed; synthetic credentials are never recorded in repository evidence. |
| MVP-GAP-021 | Privacy, retention, deletion, and anonymization procedures unresolved | All identity/clinical/object flows | Production data handling not approved | Synthetic/controlled data and no destructive operation | MVP-09 / approval gate | open | Privacy/legal owner approves policy and evidence | Do not invent lawful basis or retention. |
| MVP-GAP-022 | Production deployment not approved | Deployment | No production beta claim | No deployment action | MVP-09 | open | Owner records controlled deployment decision | This task performs no deployment. |
| MVP-GAP-023 | CI/release and deployment evidence gaps remain for the beta model | CI/deployment | No complete MVP integration/release evidence | Run focused checks per task; reserve full pipeline for gates | MVP-09 | open | Integration/release verification produces current evidence | Existing validation scripts are evidence of checks, not deployment approval. |
| MVP-GAP-024 | Operator-owned administration remains incomplete | Operator administration / MVP-04 queue and attendance | Bounded site, profile, assignment, eligible-shift, shift-assignment, and arrival administration exists; protocol, queue exceptions, and broader operational configuration remain unavailable | Expose only the bounded resources behind exact claims and application services | MVP-04 | open | Complete Operator-owned administration, authorization, audit, and focused flow tests pass | The shared administrator interface does not transfer Operator records or rules to a generic owner. |
| MVP-GAP-025 | Image Gateway operational administration required by Image Gateway MVP flows not yet implemented | Image Gateway operational administration / MVP-05 study intake and correlation | No administrator visibility for intake status, correlation failures, retries, or terminal failures | Keep operational access administrator-only and do not expose a separate end-user application or direct Member/Operator mutation | MVP-05 — Image Gateway workstream | open | Image Gateway operational administration, authorization, audit, and failure-flow tests pass | Gateway-owned gap. Storage, processing, retry, publication, and compliance operations remain Image Gateway-owned. |

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
