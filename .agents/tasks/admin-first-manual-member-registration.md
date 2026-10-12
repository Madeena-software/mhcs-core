---
title: Admin-first Manual Member Registration
document_id: MHCS-TASK-ADMIN-FIRST-MANUAL-MEMBER-REGISTRATION-001
version: 1.1
status: draft
language: en-US
last_updated: 2026-10-12
scope:
  - first bounded Admin functional slice proposal
  - existing Admin and Operator implementation audit
authority_note: Draft for independent review. This publication authorizes no application implementation, capability provisioning, account migration, production operation, or release.
---

# Draft Implementation Task

## Task identity

**Task title:** Admin-first Manual Member Registration

**Task path:** `.agents/tasks/admin-first-manual-member-registration.md`

**Task contract state:** `Draft`. Draft completeness validation is distinct from T5 executable validation. Do not route this revision to an Executor.

**Delivery objective / Work Package / MVP:** First Admin-first operational slice, limited to administrator-assisted Member registration and the existing Member directory; relevant WP-04 identity and WP-10 shared administration foundations. This does not complete either Work Package or MVP-GAP-027.

**Owner / designated planning authority:** MHCS human Product Owner and Planner/Reviewer; Member-domain and security/privacy authorities retain their approval boundaries.

## Delivery context and current state

The human Planner handoff dated 2026-10-12 requests a reuse-first audit and a new Draft on a dedicated branch, followed by validation, commit and push for independent review. It authorizes no application edits during authoring, PR, merge, deployment or external business-document mutation.

The checkout and remote `main` were observed at `fb25354311121fc8163256cd0530d9cbcb66bb68`, with a clean tracked working tree before authoring. Dedicated branch: `task/admin-first-manual-member-registration`.

That SHA is the supplied verified main baseline, not newly established application acceptance. The recent history contains documentation merges #5 and #6. `.agents/context/project.md` and `docs/mvp/README.md` still describe the lean-docs implementation as Review Required; no independent acceptance record for #6 or the recent application work was established in this audit. This Draft neither re-reviews nor accepts that work. Pending authority/acceptance reconciliation remains an implementation-publication prerequisite, not a reason to prevent independent Draft discovery.

The target is one persistent Admin dashboard. Existing portal/account flows must remain intact while later contracts settle their transition. Administration remains a presentation surface over Member, Operator, Doctor and Image Gateway ownership.

**Remediation basis:** Independent review of Draft `32a23c2fe3cc99176e854a4bc7f271bf8dd574bb` and the human-approved product decisions supplied on 2026-10-12. Adult registration requires NIK, name, birth date and administrative gender; KTP and profile photographs are independently optional. Every new registration in this slice starts `pending_verification`, regardless of the registering Admin's verification permission. Identity verification is a separate explicit Admin action. Members need no permanent dashboard login in this MVP. These decisions supersede the earlier Draft's mandatory-photo and permission-driven auto-verification proposal only within this bounded slice; they do not approve unrelated flow changes or permission provisioning.

## Baseline and task revision

**Implementation baseline:** `fb25354311121fc8163256cd0530d9cbcb66bb68`.

**Task revision:** The full commit SHA containing this Draft is supplied in the Planner handoff. Any future validated publication must resolve its own exact immutable revision and explicitly reconfirm the implementation baseline.

## Observed implementation audit

All source paths below were inspected against the baseline. These are implementation observations, not approval or runtime conformance claims.

| Area | Existing code to reuse | Missing functionality / constraint |
|---|---|---|
| Admin shell and access | `app/Providers/Filament/AdminPanelProvider.php`; `app/Providers/Filament/Pages/AdminLogin.php`; `app/Shared/Authorization/AdminPanelAccessService.php`; `app/Models/User.php` | `/admin` registers Member/Operator resources. Panel access requires an eligible account, `administrator` role and an exact configured access permission. This proves neither a unified operational dashboard nor a complete Admin directory. Preserve password-change and account-state checks. |
| Manual Member registration | `app/Modules/Member/Application/Services/MemberRegistrationService.php::register`; `MemberAuthorization::registration`; `MemberRegistrationData`; `MemberVerificationAssetService::recordForRegistration`; `MedicalRecordNumberGenerator`; `ProtectedIdentifierService` | Standard DTO assets are non-null; validation, document-type insertion, asset calls and payload hashing dereference both. Registration sets identity/account activation from `member.identity.verify`; asset recording independently auto-approves and synchronizes identity status. This conflicts with the newly approved optional-photo/pending-only behavior. A narrowly separated adult Admin-assisted entry point and null-aware handling are needed, plus the missing Filament create/form adapter. Existing standard callers must retain their contract. |
| Operator field registration | `app/Modules/Operator/Application/Services/OperatorFieldOperationsService.php::{searchMembers,registerAndAdmitMember,addExistingMemberToShift,createShift}` | Existing field flow requires Operator identity and site/shift assignments. It writes an `operator_field` Member with `identity_document_type=nik`, disabled login, and its own booking/admission path. It is materially different from evidence-backed standard registration; copying it into Admin would silently choose identity/funding policy. Preserve it unchanged. |
| Arrival and identity verification | `OperatorArrivalService::{confirm,recordConfirmed}`; `OperatorArrivalConfirmationService`; `OperatorIdentityVerificationService::{start,view,lookupByNik,decide}`; `app/Modules/Member/Application/Services/Mvp04OperatorIdentityVerificationService.php` | Confirmation tokens, claims, exact evidence and Member-owned verification contracts exist. Their interactive path requires `OperatorAuthorization::portal/identity`, an active profile, authorized active site and shift assignment. Standard Member registration verification is distinct from an arrived booking's verification case. |
| Attendance and check-in | `OperatorAttendanceService::query`; `OperatorCheckInTicketService::issue`; `OperatorWorklistService`; `app/Modules/Member/Application/Services/Mvp04AttendanceService.php`; `OperatorAttendanceContract` | Member owns attendance/state transitions; Operator owns ticket/queue coordination. Ticket issue requires a matched case, assignment, consent-withdrawal checks, idempotency and attributable audit. The attendance service also queries Image Gateway studies; it is not a harmless Admin directory query. Admin-only access and a centralized operational worklist are missing. |
| Sites, shifts and assignments | Existing Filament `OperatorSites`, `OperatorSiteAssignments`, `OperatorEligibleShifts`, `OperatorShiftAssignments`, Member `ExaminationSites` and `ShiftSchedules` resources; `OperatorSiteService`, `OperatorSiteAssignmentService`, `OperatorShiftAssignmentService`, `EligibleShiftIntakeService`, `Mvp03ScheduleService` | Admin CRUD/read foundations exist with domain services and exact permissions. Keep Operator local site UUID distinct from its external `operator_site_id` and Member reference; keep eligible-shift intake and assignment rules. These resources do not give an Admin an interactive Operator identity. |
| Admin user management | `User`, `authorization_role_assignments`, `authorization_permission_assignments`, `DatabaseAuthorizationClaimResolver`, `database/seeders/MvpAdminSeeder.php`; Member directory account-state actions call `AccountStateService` | Admin bootstrap and role/permission records exist. The MVP Admin seeder does not grant `member.registration.manage` or `member.identity.verify`; existing Admin access is not registration authorization. No general Admin-user CRUD resource is registered in the inspected panel. Member suspend/restore is not Admin directory management. Provisioning, invitation/recovery and role management are separate work. |
| Radiographer data | `app/Modules/Operator/Domain/Models/OperatorProfile.php`; Operator foundation migration; `OperatorProfileResource` and `OperatorProfileService` | Generic Operator profile contains User linkage, display name, employee code and active status, with site/shift assignments. No distinct Radiographer qualification model was found in inspected application/schema sources. Creating a profile currently provisions Operator claims; do not relabel it or invoke it merely to let an Admin call portal services. |
| Radiologist data | `app/Modules/Doctor/README.md`; `DoctorServiceProvider`; Doctor application/domain/presentation boundary | The inspected Doctor module is a boundary skeleton, without a concrete Radiologist profile, case assignment or reporting model. AI-PACS `radiographerName` provenance and service `includes_doctor` flags are not professional identity models. Clinical directory/workspace design requires separate authority. |

Source routes in `routes/web.php` and `app/Http/Controllers/Operator/PortalController.php` expose the existing Operator journey. An Admin navigation link to those routes would retain Operator gating, not implement Admin-only operations.

The existing Graphify graph was used only for discovery: its build predates this baseline. Codebase Memory was freshly indexed in fast mode without a repository artifact. Exact-path coverage reported no recorded gaps for the checked source/services/tests, but migrations were excluded and read directly. Graph call edges were not treated as exhaustive or authoritative; direct source searches and reads underpin the conclusions above. No graph was committed.

## Objective and selected journey

**Objective:** An authorized Admin opens `/admin`, opens Members, manually registers one adult Member with mandatory NIK and demographics and zero, one or two optional photographs, and sees the resulting pending Member and MRN in the existing directory/detail view, without activation or effective Member dashboard access.

This is the smallest coherent Admin-only workflow that can reuse the existing Member application service with a bounded domain adaptation. It requires no Operator impersonation, new staff credentials, booking/funding decisions or clinical content access.

**Preferred journey deferred:** `/admin` → site/shift → register/select Member → verify arrival/check-in → refreshed operational worklist. A normal Admin lacks the Operator role/profile/assignments required by the existing services. Silently granting a second role or weakening `portal()` would conflict with the handoff's one-Admin direction and MVP-GAP-027. The field registration path also selects a different identity/admission policy. Obtain an approved Admin operational actor/capability/assignment contract before planning that successor. The selected directory is not claimed to be the requested operational worklist.

## Authoritative inputs and traceability

### Governing authority

- Human Planner handoff dated 2026-10-12: product direction, reuse-first constraint, audit scope, Draft delivery and explicit side-effect limits.
- Human-approved remediation decisions dated 2026-10-12: mandatory NIK/demographics, independently optional photographs, pending-only registration, separate explicit verification and no required permanent Member dashboard login. The earlier Draft is not authority for contradictory behavior.
- `.agents/AGENTS.md`, `.agents/software-workflow.md`, `.agents/prompts/plan-create-task.md` and `.agents/tasks/_template.md`.
- `docs/mvp/decision-log.md` MVP-DEC-044; `docs/mvp/beta-scope.md` Admin-first operational scope; `docs/mvp/roadmap.md` Admin-first planning sequence; `docs/mvp/beta-gap-register.md` MVP-GAP-027–034.
- `docs/implementation/mhcs-core-requirements-matrix.md` and `docs/implementation/mhcs-core-implementation-plan.md`, retained requirement/ownership references. Their historical implementation-status columns and removed context-source paths are not current evidence or newly approved behavior.

The maintained Google business Doc remains Draft with unresolved immutable provenance. This task does not fetch, edit, approve or adopt its propositions. The human decisions above are approved intent; the technical adaptation and remaining authorization boundaries below are Draft for independent review.

### Requirement traceability

| Obligation / acceptance | Source and owning boundary |
|---|---|
| AC1–AC3: manual Admin registration and directory outcome | Human handoff and approved remediation decisions; MVP-DEC-044; MEM-002/003/038/039/041; Member owns registration/identity, existing shared Admin presentation |
| AC4: replay, conflicts and rollback | Necessary data-integrity invariant; existing Member operation contract; MEM-058/118 reference |
| AC5–AC6: minimized presentation, private evidence and audit | Preserved repository controls; MEM-109/113/114/122; MVP-GAP-031 remains open for production/storage-policy reconciliation |
| AC7: preserve unrelated flows and domain ownership | Human exclusions; MEM-122; MVP-GAP-027–030/032; repository reuse discipline |

No historical requirement is marked implemented, verified or accepted by this Draft. The acceptance IDs are local to this task, not a new global requirement registry.

**Scoped authority discrepancy:** MEM-040 describes mandatory current identity/profile assets at registration; the approved human decision permits neither asset in this adult Admin-assisted slice. MEM-043's privacy/history controls still apply to supplied assets, but no current/approved evidence is invented. Record this task's bounded exception in the governing validated publication; do not globally rewrite the historical matrix or infer that unrelated online, walk-in, child, seed or nonclinical paths now permit missing evidence. Any unresolved authority-precedence question returns to Planner/Reviewer before T5.

## Scope

### In scope, after approval and validated publication only

- Add a create entry/form to the existing Member Filament resource and return to its safe list/detail presentation. Use the existing panel and layout; no second dashboard or generic wizard.
- Support the adult Admin-assisted path only (17 or older by the existing domain Clock rule). Required data: name, birth date, administrative gender and NIK. Independently optional uploads: KTP photograph and profile photograph. All four combinations (neither, KTP only, profile only, both) must work. Optional existing fields: email, phone and KK. No guardian, external identifier, affiliation or office-location editing in this slice.
- Validate mandatory NIK as 16 decimal digits at both this new domain entry point and the UI; reject missing, blank and malformed values. Validate demographics, non-future adult birth date and optional KK/email/phone server-side. Preserve existing service/test support for historical synthetic identifiers in unrelated paths. Normalize empty optional fields to null.
- Validate supplied private uploads server-side before persistence using existing limits; accept valid JPEG/PNG images, not arbitrary files or caller-provided object keys. Missing/invalid upload limits fail closed for an attempted upload; the no-upload path needs no dummy object or storage write. Do not fabricate placeholder evidence or treat absence as review approval.
- Require current eligible Admin panel access, `member.account.read` and `member.registration.manage` at entry and again at submission. Use trusted session claims and `MemberAuthorization`; do not manufacture a context. Do not require an Operator role/profile/site/shift.
- Map to the scoped adult Admin-assisted entry point in `MemberRegistrationService` described below, with server-fixed `RegistrationSource::Administrator`, `password=null` and no guardians/external identifiers. Persist only supplied evidence through `PrivateObjectStore` and `VerificationAssetInput`; use `member.registration` purpose and the real authenticated actor. Registration never calls verification, activation, recovery or credential-handoff services.
- Preserve operation identity for retry of the same logical submission and let Member Core enforce request-hash conflicts. Coordinate newly created private objects on failure/replay: delete only unreferenced objects owned by that attempt; never delete evidence referenced by a committed Member. Do not add a parallel persistence/idempotency service.
- Render service-returned identity/account status and MRN; show a bounded error without raw identifiers, SQL, private keys or credentials. Reset sensitive form/upload state after success. Normal field inputs may be shown only to the authorized Admin inside the form.

### Anticipated affected surfaces

`MemberResource.php`, `Pages/ListMembers.php`, one new create page or equivalent existing-resource action, `MemberRegistrationData`, `MemberRegistrationService`, `MemberVerificationAssetService`, and focused domain/Admin feature tests. Reuse existing Member data/services and private storage; no schema or lockfile change is expected. Permission provisioning is a separate approval boundary below, not an implicit implementation side effect. Review the route-absence assertions in `Mvp02MemberAdministrationTest` when adding create access: preserve read-only denial, edit/delete/bulk/export denial and safe directory fields. Existing standard/online/child/seed/nonclinical callers, asset review/replacement, activation, Member identity tests, Admin access tests and architecture/security tests are regression surfaces.

### Smallest safe domain compatibility proposal

1. Keep `MemberRegistrationService::register` as the legacy mandatory-evidence boundary. Add one explicit adult Admin-assisted entry method in the same service; its name is Executor discretion. It must revalidate source `Administrator`, adult age, trusted Admin/registration permission, mandatory valid NIK, no password and no guardian/external-identifier payload before any mutation. The UI cannot select a caller-controlled bypass flag or strip verification permission from a forged context. Existing `register` callers with source `Administrator` still retain legacy behavior; source alone cannot select the new policy.
2. Make the two `MemberRegistrationData` asset slots nullable without changing existing positional/named constructor callers. Nullable representation does not authorize absent assets: legacy `register` must explicitly reject null in either slot before dereference. The scoped new entry validates any supplied identity asset as KTP and supplied photo as ProfilePhoto; unrelated online/walk-in/child paths retain their exact evidence and age/guardian checks. No new registration service, DTO hierarchy, mode registry or policy framework is needed.
3. Reuse the existing transaction, User/Member creation, identifier protection, MRN generator, operation storage and audit in a shared internal implementation. Select pending-only policy internally from the distinct entry, not a public downgrade parameter. Force Member identity `pending_verification`, User `pending_activation`, `login_enabled=false`, and `must_change_password=true`, regardless of `member.identity.verify`. Keep a schema-compatible opaque password hash only as internal authentication persistence; never accept, emit or deliver Member credentials. Do not call activation or change old account flows.
4. Handle the non-null `members.identity_document_type` without a schema relaxation or fake asset: use the existing `nik` label when no KTP is supplied, and `ktp` when it is supplied. This records the declared registration input type, not verified evidence. Profile-only registration still uses `nik`. Preserve `registration_source=administrator`; never borrow the field path's verified identity or admission behavior.
5. Add a narrowly scoped pending-registration recording method to `MemberVerificationAssetService`, reusing its trusted registration-context matching, genuine-Member check, private-object validation, type validation, private `record(..., approved:false)`, history and audit. Restrict that new entry to this adult Administrator registration context. Keep existing `recordForRegistration` and `review` behavior for legacy callers. Supplied assets must be `pending`, `is_current=false`, with null reviewer/review time; `syncIdentityStatus` must leave this newly created Member pending. With no upload, create zero asset rows and make no storage call.
6. Make this path's operation hash null-aware: absent slots are explicit null; supplied slots retain type/checksum identity. Bind a fixed internal Admin-assisted discriminator to this new path so a legacy operation cannot replay a differently governed registration. Keep legacy hash encoding unchanged for durable existing replays. Reusing a key with changed photo presence/content or another actor/path must fail safely. Reuse the existing operation table/transaction; no migration or separate idempotency store.
7. Registration/asset audit retains authenticated actor and purpose, pending outcome and actual optional-asset presence, without raw NIK/KK, file data or credentials. Cleanup covers only objects created by that attempt and not referenced by committed rows, including replay and partial upload failure; no-upload registration creates nothing to clean up.

This is a bounded policy adaptation authorized by the new product decisions, not a global relaxation. Equivalent smaller implementation details are allowed if they meet every boundary above; approval of the executable contract is still required.

### Out of scope / must not be implemented yet

- Site/shift selection, new bookings, funding/points, arrival verification, check-in, tickets, queue advancement or a centralized study/worklist/status dashboard.
- Admin, Radiographer or Radiologist directory CRUD, role assignment, qualifications, professional authentication, case assignment or Operator-profile auto-provisioning.
- Temporary Radiographer/Radiologist workspaces, report review/signing/amendment, AI-report disclosure, finalized-result publication and Member result links.
- CSV/XLSX templates/imports, child/guardian registration UI, identity editing/merging, recovery or credential handoff.
- Portal removal, account migration, disabling existing accounts, password delivery, new Member login routes, unrelated registration-policy changes or any activation-policy change.
- Separate physical Admin identity verification, approving supplied evidence, automatic verification from NIK/photo possession, or bypassing existing evidence-dependent verification/activation invariants.
- Consent/basic-examination bypass, fabricated evidence/measurements, changes to private storage/encryption/retention, Image Gateway/Cornerstone3D/MPIPS changes or real patient data.
- Cloning/installing/vendoring/copying another hospital application, new libraries, dependencies, infrastructure or framework abstractions.

### Preserved behavior and domain boundaries

- `User` remains authentication; `Member` retains demographics, independent MRN, protected NIK/KK and evidence. Existing linked User creation is required internal persistence, not a new Member access policy. No plaintext generated password is surfaced or retained by the UI.
- The new adult Admin-assisted entry always leaves identity/evidence pending and login disabled as specified above. The observed standard `register` entry's permission-driven approval and account flags remain unchanged for unrelated callers. Preserve effective authentication gates in `User`, `AccountStateUserProvider`, mandatory password replacement and all existing portals; no existing account migration is authorized.
- Registration verification is not arrival-case matching, check-in, clinical approval or consent. No booking, consent, ticket, points or clinical row is created.
- Preserve unique NIK digest, UUID/User linkage, independent registration source/account state, atomic database operations, replay/conflict behavior and actor/purpose audit.
- Operator retains assignments/attendance coordination, Doctor retains clinical responsibility, Image Gateway retains imaging/processing. Existing portals, field exceptions, integration paths and account-state actions remain unchanged.

## Dependencies, assumptions and remaining approvals

Observed dependencies: Laravel `^13.8` and Filament `^5.0` are declared in `composer.json`; Cornerstone3D 5.7.0 is declared in `package.json` and remains untouched. Existing services, bindings, test fixtures, private storage and authorization assignment tables can support the bounded adaptation; no installation or dependency update is proposed. Use the installed APIs and inspect local vendor code/current official documentation during execution where API behavior needs confirmation.

### Admin permission provisioning audit and boundary

`MemberAuthorization::registration` requires the trusted `administrator` role plus `member.registration.manage`. `config/mhcs.php` panel access permissions do not substitute for this domain permission. `DatabaseAuthorizationClaimResolver` resolves exact active persisted assignments. The MVP Admin seeder's `PERMISSIONS` omits registration-manage and identity-verify; its `reconcileClaims` rejects permissions outside that allowlist and non-bootstrap assignment provenance. Seeders such as `MvpMemberSeeder`/Prestige use synthetic registration contexts, not a live Admin grant. No existing interactive registration-permission provisioning service was identified in the inspected Admin/bootstrap surfaces.

**Proposed minimum provisioning, requiring explicit approval:** authorize adding only `member.registration.manage` to the existing local/testing `MvpAdminSeeder` bootstrap allowlist, with its existing idempotent reconciliation and bootstrap provenance. This would let an explicitly approved bootstrap Admin receive the permission while preserving its other claims and credentials. Do not grant `member.identity.verify`, wildcard access, Operator roles or any new permission to all current Admins. Do not rerun a seeder on live data under this task. If this provisioning option is approved, republish the validated task with `MvpAdminSeeder` and its existing bootstrap reconciliation tests as bounded source changes; executing the grant remains limited to approved local/testing fixtures. Until then, tests may create synthetic eligible Admin assignments and UI access requires an already legitimately granted permission. A real Admin grant needs a separately authorized actor, target account/environment and attribution/audit process; a manual database write or existing portal access is not authorization.

### Separate verification dependency

`MemberVerificationAssetService::review/syncIdentityStatus`, `AdultActivationService`, and the Operator physical verification contracts rely on approved/current evidence or an arrived-booking/assignment context. They do not establish an authorized Admin verification mechanism for a photo-free registration. A later task must define an explicit permission-gated physical identity check, accountable verifier, evidence/attestation provenance, operation identity, audit/history and failure/conflict controls that work without uploaded photographs. Verification must remain distinct from account activation and never imply Member dashboard access. This dependency does not block creating pending identities, but blocks any later transition to verified under the new photo-free workflow. Do not implement it here.

| Item | Classification / owner | Resolution trigger |
|---|---|---|
| Optional photos, mandatory NIK/demographics, initially pending identity, separate verification and no required permanent Member login | Product decision resolved by the supplied human approval | Do not reopen these decisions or retain contradictory mandatory-photo/auto-verification criteria |
| Validate the bounded domain adaptation and scoped MEM-040 discrepancy | Blocking for T5; independent Planner/Reviewer and relevant Member/security authorities | Approve the distinct entry, legacy preservation, pending-only assets and safe account flags in this exact contract; no globally weakened evidence requirement |
| Authorize a concrete registration-permission provisioning path | Blocking for T5 operational readiness; designated human/security authority | Approve the local bootstrap-only proposal above or document an already authorized grant path. Republish any newly authorized provisioning scope; no silent seed change or live permission mutation |
| Confirm new evidence-upload use of the existing private storage boundary under retained MEM-114 versus scoped storage exceptions | Blocking for T5 where applicable; security/privacy and technical authorities | Confirm applicable bounded authorization for synthetic/local implementation. Broader production lawful basis/storage policy is not inferred and remains blocked under MVP-GAP-031/G10 |
| Reconcile supplied baseline with pending review/acceptance records | Blocking before implementation publication; Planner/Reviewer, MVP-GAP-034 | Establish scoped usable implementation baseline and any required acceptance evidence; merging alone does not satisfy this |
| Admin operational role/site/shift authority, field-registration identity/funding policy | Blocking only for deferred preferred journey; Product, Member/Operator and security/privacy authorities, MVP-GAP-027/030 | Separate approved contract before Admin arrival/check-in work; no assumptions or temporary privilege grants here |
| Temporary access, report finalization/recipient access, import and portal migration decisions | Deferred, non-blocking for this narrow Draft; existing gap-register owners | Resolve before their separate later tasks; preserve exclusions here |

The approved product decisions are authoritative for this slice; unresolved technical/authorization items are not approved assumptions. Real operations require separate production-data, environment and release approval.

## Required capabilities and execution constraints

Future Executor: repository read/write, local PHP/Filament test execution and local private-storage fakes. Browser capability for a synthetic UI smoke check where available. No external account/business-system access is required.

Use installed Laravel/Filament and existing domain services. Do not use default Eloquent resource creation to persist the User/Member/evidence separately, write those tables in UI code, bypass `MemberAuthorization`, forge trusted claims, extend field exceptions or add a parallel registration service. The bounded Member domain adaptation is in scope; migrations and permission changes remain unauthorized unless the governing publication explicitly resolves the provisioning boundary above. Keep method-level authorization effective after Livewire mount and use established exception/notification patterns and Indonesian UI conventions.

## Acceptance criteria

- [ ] **AC1:** Eligible Admin with account-read and registration-manage permissions reaches the registration form from `/admin` → Members; anonymous, non-Admin, suspended/login-disabled/password-change-required and read-only accounts cannot submit. Revocation after mount is enforced before upload/domain mutation.
- [ ] **AC2:** Valid mandatory NIK/demographics creates exactly one linked User and Member through the scoped Member Core entry for all four photo combinations. Zero uploads means zero asset rows/storage writes; otherwise only supplied photos become private pending assets with no reviewer/current approval. Success returns to the safe Member list/detail with an independent MRN, `pending_verification` and attributable registration audit. No Operator profile or fabricated evidence is required.
- [ ] **AC3:** With and without `member.identity.verify`, every new scoped registration remains `pending_verification`; User stays `pending_activation`, `login_enabled=false`, `must_change_password=true` and cannot authenticate or access a Member dashboard. No verification/activation/recovery service is invoked, role/permission assigned or Member credential accepted/delivered. Photo possession does not verify identity.
- [ ] **AC4:** Same-operation/same-payload retry returns the original result without duplicate Users/Members/evidence/audit in each photo combination. Changed NIK/data/photo presence/content, another actor or legacy/scoped entry under the same operation is rejected. Duplicate NIK/email, concurrent duplicate submission, first/second private-object write failure and domain failure preserve safe rollback and attempt-object cleanup. Committed evidence and legacy operation replays survive.
- [ ] **AC5:** Missing/blank/non-16-digit NIK is rejected at both UI and scoped service boundaries. Invalid/future/under-17 birth date, malformed optional KK/email, wrong supplied asset slot/type, non-image/forged MIME or oversized upload and attempted upload with invalid limits are rejected before registration. Omitted photos are valid, never represented by placeholders. The new service rejects non-Administrator source, child, password, guardian/external payload or unauthorized caller; legacy `register` still rejects missing required assets.
- [ ] **AC6:** Existing list/detail output and validation errors contain no raw NIK/KK, private object key/download URL, file body, password, token, claim/session data or SQL. Sensitive input/upload state is cleared after success; no public evidence URL or inline database binary is introduced.
- [ ] **AC7:** Existing standard Administrator/online/walk-in/child/seed/nonclinical registration paths, asset review/replacement, activation and account-state actions retain their requirements, approval behavior and operation replay encoding. Old portals, Operator site/shift/assignment/arrival/check-in services, Doctor and Image Gateway retain their behavior. No booking, ticket, consent, queue, ledger or clinical mutation occurs from registration; no manual identity-status database update substitutes for later controlled verification.

## Verification requirements

### Observed planning evidence

During initial Draft authoring on the unmodified application baseline, the following local command completed successfully (historical planning evidence retained from `32a23c2fe3cc99176e854a4bc7f271bf8dd574bb`; not rerun or presented as evidence for the new proposed behavior):

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test tests/Feature/Admin/Mvp02AdminAccessTest.php tests/Feature/Admin/Mvp02MemberAdministrationTest.php tests/Member/Wp04IdentityTest.php --compact
```

Observed result: **49 tests passed, 396 assertions**. This verifies existing tested foundations only; no new Admin registration UI exists or was tested. It is local SQLite evidence, not CI, MySQL, browser, production or whole-application acceptance.

### Required future implementation checks

- Add domain and Filament/Livewire Admin tests for AC1–AC7 using real persisted role/permission claims and fake private storage. Cover the four optional-photo combinations crossed with presence/absence of Admin verification permission, no-upload zero storage writes, pending/non-current evidence and identity, explicit disabled login, success → directory and mount-to-submit revocation. Do not substitute a mocked success return for the end-to-end domain path.
- Cover mandatory NIK failure at UI and service, scoped-entry guards, legacy null rejection and unchanged legacy photo/approval rules; exercise direct pending-asset entry authorization/type/private-object checks. Verify the no-photo path cannot become verified/active through registration, fake evidence or a reused legacy operation.
- Cover same-payload retries for each photo combination, changed asset presence/checksum/actor/path conflicts, duplicate NIK/email, partial upload failure, transaction/audit failure, no orphan User/Member/family/operation/asset rows and attempt-owned object cleanup without deleting committed evidence. Preserve old hash/replay tests; run existing identity, online, child, activation, asset history and nonclinical regressions.
- Run `php artisan test tests/Member/NonclinicalValidationMemberIdentityTest.php tests/Feature/Validation/NonclinicalValidationContextProvisioningTest.php --compact` under isolated testing configuration. If the bootstrap grant is explicitly approved into the validated contract, extend the existing `Mvp02AdminAccessTest` reconciliation coverage: exact registration permission added once, credentials/other claims preserved, identity-verify absent, and unauthorized/non-bootstrap grants still rejected. Do not weaken those assertions simply to make provisioning pass.
- Rerun the planning command above plus the new test file; update only the old create-route assumptions required by this slice while retaining read-only and edit/delete/bulk/export negative checks.
- Run `php artisan test tests/Architecture tests/Security --compact` with explicit testing/isolated database configuration; run `vendor/bin/pint --test` on changed PHP files.
- Exercise concurrent duplicate registration against an isolated local MySQL test database using the repository's existing integration/conformance setup when available. SQLite alone cannot substantiate row-lock/concurrency claims. If unavailable, report the gap and obtain Reviewer disposition before acceptance; never point tests at production.
- Perform synthetic browser/manual UI smoke checks: Admin login → Members → create with mandatory NIK and no photos → pending directory/detail; then register another synthetic Member with optional uploads. Check validation feedback, keyboard labels, pending status, no effective Member access and no credential/public evidence exposure. Record unavailable browser verification explicitly.
- Report exact governing task revision, baseline, implementation state, commands/results, test changes, deviations and verification gaps. No dependency install, production seed/migration or external integration run is needed for this slice.

## Stop conditions

Stop and return **Planning Required** if the contract is still Draft; an approval above is unresolved; baseline acceptance/reconciliation is required and unavailable; unrelated registration/verification/activation semantics must change; fake evidence or auto-verification is proposed; adult/guardian scope must expand; an unapproved permission/assignment or schema change is needed; private-object cleanup or legacy replay cannot be preserved; verification reveals a materially different authority/security/data-integrity boundary; or completion requires excluded behavior or an unapproved side effect. The approved optional-photo/pending-only adaptation is not itself a stop condition. Do not broaden this task into physical identity verification or the preferred check-in workflow.

## Side-effect authorization

**Current Planner authoring:** Human explicitly authorizes this Draft file, dedicated branch, validation, commit and push for independent review. Authoring must not edit application code/tests/configuration or external business documents. No PR, merge, deployment, production mutation or release is authorized.

**Future Executor:** None while Draft. After an independently approved validated revision exists, its bounded local implementation/test mutations may proceed under that revision. This Draft grants no future commit, push, PR, permission provisioning, external mutation, dependency installation or release authorization.

## Expected terminal outcome and review handling

**Current terminal state:** Draft ready for independent review; T5 not passed and no Executor launcher. Reviewer resolves blocking decisions and either approves/revises this stable path for validated publication or returns it to planning. Structural Draft validation must not be reported as implementation approval.

**Future implementation:** Review Required with observed evidence, or Planning Required at a stop condition. Reviewer evaluates the exact validated task revision and bounded implementation; an accepted immutable revision is separate from G10 release authorization. Bounded remediation uses this same task path; new operational/clinical objectives require separate planning.

## Draft validation record

Remediation assessment on 2026-10-12: B0 and the listed P1 product decisions have explicit human approval. R2 records the scoped MEM-040 discrepancy without weakening unrelated requirements; A3 proposes the bounded domain compatibility change. D4 remains a coherent registration-only slice. Independent technical-contract review, explicit provisioning authorization, applicable storage authorization and baseline reconciliation remain open, so T5 is **not passed**. Structural checks cover required contract concerns, seven acceptance criteria, approved input/status decisions, baseline references and the same-file-only diff. No application acceptance, independent review, operational gap closure or release approval is asserted.
