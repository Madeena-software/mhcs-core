---
title: Admin-first Manual Member Registration
document_id: MHCS-TASK-ADMIN-FIRST-MANUAL-MEMBER-REGISTRATION-001
version: 1.0
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

## Baseline and task revision

**Implementation baseline:** `fb25354311121fc8163256cd0530d9cbcb66bb68`.

**Task revision:** The full commit SHA containing this Draft is supplied in the Planner handoff. Any future validated publication must resolve its own exact immutable revision and explicitly reconfirm the implementation baseline.

## Observed implementation audit

All source paths below were inspected against the baseline. These are implementation observations, not approval or runtime conformance claims.

| Area | Existing code to reuse | Missing functionality / constraint |
|---|---|---|
| Admin shell and access | `app/Providers/Filament/AdminPanelProvider.php`; `app/Providers/Filament/Pages/AdminLogin.php`; `app/Shared/Authorization/AdminPanelAccessService.php`; `app/Models/User.php` | `/admin` registers Member/Operator resources. Panel access requires an eligible account, `administrator` role and an exact configured access permission. This proves neither a unified operational dashboard nor a complete Admin directory. Preserve password-change and account-state checks. |
| Manual Member registration | `app/Modules/Member/Application/Services/MemberRegistrationService.php::register`; `MemberAuthorization::registration`; `MemberRegistrationData`; `MemberVerificationAssetService::recordForRegistration`; `MedicalRecordNumberGenerator`; `ProtectedIdentifierService` | Administrator-assisted registration, private evidence, MRN, protected NIK/KK, transaction, operation replay and audit already exist. `app/Modules/Member/Filament/Resources/Members/MemberResource.php` has only list/view pages and `canCreate() === false`; `Pages/ListMembers.php` has no create header action. No production caller of the standard registration service was found in the inspected `app/` source; test/seed callers exist. A private upload/form adapter is missing. |
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

**Objective:** An authorized Admin opens `/admin`, opens Members, manually registers one adult Member with required private identity evidence, and sees the resulting Member and MRN in the existing directory/detail view.

This is the smallest coherent Admin-only workflow supported by an existing authoritative application service. It requires no Operator impersonation, new staff credentials, booking/funding decisions or clinical content access.

**Preferred journey deferred:** `/admin` → site/shift → register/select Member → verify arrival/check-in → refreshed operational worklist. A normal Admin lacks the Operator role/profile/assignments required by the existing services. Silently granting a second role or weakening `portal()` would conflict with the handoff's one-Admin direction and MVP-GAP-027. The field registration path also selects a different identity/admission policy. Obtain an approved Admin operational actor/capability/assignment contract before planning that successor. The selected directory is not claimed to be the requested operational worklist.

## Authoritative inputs and traceability

### Governing authority

- Human Planner handoff dated 2026-10-12: product direction, reuse-first constraint, audit scope, Draft delivery and explicit side-effect limits.
- `.agents/AGENTS.md`, `.agents/software-workflow.md`, `.agents/prompts/plan-create-task.md` and `.agents/tasks/_template.md`.
- `docs/mvp/decision-log.md` MVP-DEC-044; `docs/mvp/beta-scope.md` Admin-first operational scope; `docs/mvp/roadmap.md` Admin-first planning sequence; `docs/mvp/beta-gap-register.md` MVP-GAP-027–034.
- `docs/implementation/mhcs-core-requirements-matrix.md` and `docs/implementation/mhcs-core-implementation-plan.md`, retained requirement/ownership references. Their historical implementation-status columns and removed context-source paths are not current evidence or newly approved behavior.

The maintained Google business Doc remains Draft with unresolved immutable provenance. This task does not fetch, edit, approve or adopt its propositions. The narrower product details below are proposed for independent approval, not silently promoted from code.

### Requirement traceability

| Obligation / acceptance | Source and owning boundary |
|---|---|
| AC1–AC3: manual Admin registration and directory outcome | Human handoff; MVP-DEC-044; MEM-002/003/038/039/040/041/043; Member owns registration/identity, existing shared Admin presentation |
| AC4: replay, conflicts and rollback | Necessary data-integrity invariant; existing Member operation contract; MEM-058/118 reference |
| AC5–AC6: minimized presentation, private evidence and audit | Preserved repository controls; MEM-109/113/114/122; MVP-GAP-031 remains open for production/storage-policy reconciliation |
| AC7: preserve unrelated flows and domain ownership | Human exclusions; MEM-122; MVP-GAP-027–030/032; repository reuse discipline |

No historical requirement is marked implemented, verified or accepted by this Draft. The acceptance IDs are local to this task, not a new global requirement registry.

## Scope

### In scope, after approval and validated publication only

- Add a create entry/form to the existing Member Filament resource and return to its safe list/detail presentation. Use the existing panel and layout; no second dashboard or generic wizard.
- Support the standard adult path only (17 or older by the existing domain Clock rule). Required data: name, birth date, administrative gender, NIK, KTP evidence and separate profile photograph. Optional existing fields: email, phone and KK. No guardian, external identifier, affiliation or office-location editing in this slice.
- Validate form fields and private uploads server-side before persistence, using existing configuration limits; require valid JPEG/PNG images for this proposed first UI, not arbitrary files or caller-provided object keys. Missing/invalid limits fail closed. Normalize empty optional fields to null. Validate NIK and optional KK as 16 decimal digits in this new UI; preserve existing service/test support for historical synthetic identifiers.
- Require current eligible Admin panel access, `member.account.read` and `member.registration.manage` at entry and again at submission. Use trusted session claims and `MemberAuthorization`; do not manufacture a context. Do not require an Operator role/profile/site/shift.
- Map to `MemberRegistrationData` with server-fixed `RegistrationSource::Administrator`, `password=null`, no guardians/external identifiers, and invoke `MemberRegistrationService::register`. Persist evidence through `PrivateObjectStore` and `VerificationAssetInput`; use `member.registration` purpose and the real authenticated actor.
- Preserve operation identity for retry of the same logical submission and let Member Core enforce request-hash conflicts. Coordinate newly created private objects on failure/replay: delete only unreferenced objects owned by that attempt; never delete evidence referenced by a committed Member. Do not add a parallel persistence/idempotency service.
- Render service-returned identity/account status and MRN; show a bounded error without raw identifiers, SQL, private keys or credentials. Reset sensitive form/upload state after success. Normal field inputs may be shown only to the authorized Admin inside the form.

### Anticipated affected surfaces

`MemberResource.php`, `Pages/ListMembers.php`, one new create page or equivalent existing-resource action, and focused Admin feature tests. Reuse existing Member data/services and private storage; no schema, role/permission provisioning, service-policy or lockfile changes are expected. Review the route-absence assertions in `Mvp02MemberAdministrationTest` when adding create access: preserve read-only denial, edit/delete/bulk/export denial and safe directory fields. Existing service callers, Member identity tests, Admin access tests and architecture/security tests are regression surfaces, not candidates for unrelated refactoring.

### Out of scope / must not be implemented yet

- Site/shift selection, new bookings, funding/points, arrival verification, check-in, tickets, queue advancement or a centralized study/worklist/status dashboard.
- Admin, Radiographer or Radiologist directory CRUD, role assignment, qualifications, professional authentication, case assignment or Operator-profile auto-provisioning.
- Temporary Radiographer/Radiologist workspaces, report review/signing/amendment, AI-report disclosure, finalized-result publication and Member result links.
- CSV/XLSX templates/imports, child/guardian registration UI, identity editing/merging, recovery or credential handoff.
- Portal removal, account migration, disabling existing accounts, password delivery, new Member login routes or changes to registration/activation policy.
- Consent/basic-examination bypass, fabricated evidence/measurements, changes to private storage/encryption/retention, Image Gateway/Cornerstone3D/MPIPS changes or real patient data.
- Cloning/installing/vendoring/copying another hospital application, new libraries, dependencies, infrastructure or framework abstractions.

### Preserved behavior and domain boundaries

- `User` remains authentication; `Member` retains demographics, independent MRN, protected NIK/KK and evidence. Existing linked User creation is required internal persistence, not a new Member access policy. No plaintext generated password is surfaced or retained by the UI.
- Registration with `member.identity.verify` currently approves evidence and sets `verified`; without it the service leaves evidence pending and the account pending activation. The UI must not override this. With `password=null`, the service sets mandatory password change; `canAuthenticate()` remains false. Preserve the returned flags even when `login_enabled` is true; do not equate that flag with effective login access or change it to enforce a future account policy.
- Registration verification is not arrival-case matching, check-in, clinical approval or consent. No booking, consent, ticket, points or clinical row is created.
- Preserve unique NIK digest, UUID/User linkage, independent registration source/account state, atomic database operations, replay/conflict behavior and actor/purpose audit.
- Operator retains assignments/attendance coordination, Doctor retains clinical responsibility, Image Gateway retains imaging/processing. Existing portals, field exceptions, integration paths and account-state actions remain unchanged.

## Dependencies, assumptions and remaining approvals

Observed dependencies: Laravel `^13.8` and Filament `^5.0` are declared in `composer.json`; Cornerstone3D 5.7.0 is declared in `package.json` and remains untouched. Existing services, bindings, test fixtures, private storage and authorization assignment tables suffice; no installation or dependency update is proposed. Use the installed APIs and inspect local vendor code/current official documentation during execution where API behavior needs confirmation.

| Item | Classification / owner | Resolution trigger |
|---|---|---|
| Approve this narrower adult-registration slice instead of the preferred complete check-in journey | Blocking for T5; human Product Owner / independent Planner/Reviewer | Approve exact scope and proposed form/failure/acceptance behavior before validated publication |
| Confirm existing permission-driven evidence approval and linked User flags remain appropriate for the new Admin entry point; confirm permission eligibility | Blocking for T5; Member-domain and security/privacy authorities | Review the preserved semantics above. If changed policy is required, replan; do not patch it inside this task |
| Confirm new evidence-upload use of the existing private storage boundary under retained MEM-114 versus scoped storage exceptions | Blocking for T5 where applicable; security/privacy and technical authorities | Confirm applicable bounded authorization for synthetic/local implementation. Broader production lawful basis/storage policy is not inferred and remains blocked under MVP-GAP-031/G10 |
| Reconcile supplied baseline with pending review/acceptance records | Blocking before implementation publication; Planner/Reviewer, MVP-GAP-034 | Establish scoped usable implementation baseline and any required acceptance evidence; merging alone does not satisfy this |
| Admin operational role/site/shift authority, field-registration identity/funding policy | Blocking only for deferred preferred journey; Product, Member/Operator and security/privacy authorities, MVP-GAP-027/030 | Separate approved contract before Admin arrival/check-in work; no assumptions or temporary privilege grants here |
| Temporary access, report finalization/recipient access, import and portal migration decisions | Deferred, non-blocking for this narrow Draft; existing gap-register owners | Resolve before their separate later tasks; preserve exclusions here |

No unresolved item above is treated as an approved implementation assumption. Real operations require separate production-data, environment and release approval.

## Required capabilities and execution constraints

Future Executor: repository read/write, local PHP/Filament test execution and local private-storage fakes. Browser capability for a synthetic UI smoke check where available. No external account/business-system access is required.

Use installed Laravel/Filament and existing domain services. Do not use default Eloquent resource creation to persist the User/Member/evidence separately, write those tables in UI code, bypass `MemberAuthorization`, forge trusted claims, extend field exceptions or add a parallel registration service. No migrations or permission changes are authorized. Keep method-level authorization effective after Livewire mount and use established exception/notification patterns and Indonesian UI conventions.

## Acceptance criteria

- [ ] **AC1:** Eligible Admin with account-read and registration-manage permissions reaches the registration form from `/admin` → Members; anonymous, non-Admin, suspended/login-disabled/password-change-required and read-only accounts cannot submit. Revocation after mount is enforced before upload/domain mutation.
- [ ] **AC2:** Valid adult input with separate KTP and profile-photo uploads creates exactly one linked User and Member through Member Core, an MRN distinct from NIK, private evidence and attributable registration audit; success returns to a safe Member list/detail showing that MRN and actual service statuses. No Operator profile is required.
- [ ] **AC3:** Both with and without `member.identity.verify`, identity/evidence/account flags match the service semantics above. No roles or permissions are assigned, no credential is returned and effective Member authentication stays blocked by mandatory password change.
- [ ] **AC4:** Same-operation/same-payload retry returns the original result without duplicate Users/Members/evidence/audit. Changed payload under the same operation, duplicate NIK/email, concurrent duplicate submission, private-object write failure and domain failure produce a bounded failure with database rollback and safe attempt-object cleanup. Committed evidence survives replay/failure cleanup.
- [ ] **AC5:** Missing/invalid evidence, non-image or forged MIME upload, oversized file, missing upload limits, invalid/future/under-17 birth date, malformed NIK/KK and invalid optional email are rejected server-side without registration. Under-17 rejection does not replace the existing guardian domain workflow.
- [ ] **AC6:** Existing list/detail output and validation errors contain no raw NIK/KK, private object key/download URL, file body, password, token, claim/session data or SQL. Sensitive input/upload state is cleared after success; no public evidence URL or inline database binary is introduced.
- [ ] **AC7:** Existing account-state actions, old portals, Operator site/shift/assignment/arrival/check-in services, Doctor boundary, Image Gateway and integration contracts retain their behavior. No booking, ticket, consent, queue, ledger or clinical mutation occurs from registration.

## Verification requirements

### Observed planning evidence

On the unmodified application baseline, the following local command completed successfully:

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test tests/Feature/Admin/Mvp02AdminAccessTest.php tests/Feature/Admin/Mvp02MemberAdministrationTest.php tests/Member/Wp04IdentityTest.php --compact
```

Observed result: **49 tests passed, 396 assertions**. This verifies existing tested foundations only; no new Admin registration UI exists or was tested. It is local SQLite evidence, not CI, MySQL, browser, production or whole-application acceptance.

### Required future implementation checks

- Add focused Filament/Livewire Admin tests for AC1–AC7 using real persisted role/permission claims and fake private storage; cover mount-to-submit revocation, both evidence-approval permission states, success → directory, retry/conflict and committed-object preservation. Do not substitute a mocked success return for the end-to-end domain path.
- Rerun the planning command above plus the new test file; update only the old create-route assumptions required by this slice while retaining read-only and edit/delete/bulk/export negative checks.
- Run `php artisan test tests/Architecture tests/Security --compact` with explicit testing/isolated database configuration; run `vendor/bin/pint --test` on changed PHP files.
- Exercise concurrent duplicate registration against an isolated local MySQL test database using the repository's existing integration/conformance setup when available. SQLite alone cannot substantiate row-lock/concurrency claims. If unavailable, report the gap and obtain Reviewer disposition before acceptance; never point tests at production.
- Perform one synthetic browser/manual UI smoke check: Admin login → Members → create with evidence → directory/detail. Check validation feedback, keyboard labels and no credential/public evidence exposure. Record unavailable browser verification explicitly.
- Report exact governing task revision, baseline, implementation state, commands/results, test changes, deviations and verification gaps. No dependency install, production seed/migration or external integration run is needed for this slice.

## Stop conditions

Stop and return **Planning Required** if the contract is still Draft; an approval above is unresolved; baseline acceptance/reconciliation is required and unavailable; existing registration semantics must change; missing evidence must be bypassed; adult/guardian scope must expand; a new role/permission/assignment or schema change is needed; private-object cleanup cannot preserve committed evidence; verification reveals a materially different authority/security/data-integrity boundary; or completion requires any excluded behavior or unapproved side effect. Do not broaden this task to implement the preferred workflow.

## Side-effect authorization

**Current Planner authoring:** Human explicitly authorizes this Draft file, dedicated branch, validation, commit and push for independent review. Authoring must not edit application code/tests/configuration or external business documents. No PR, merge, deployment, production mutation or release is authorized.

**Future Executor:** None while Draft. After an independently approved validated revision exists, its bounded local implementation/test mutations may proceed under that revision. This Draft grants no future commit, push, PR, permission provisioning, external mutation, dependency installation or release authorization.

## Expected terminal outcome and review handling

**Current terminal state:** Draft ready for independent review; T5 not passed and no Executor launcher. Reviewer resolves blocking decisions and either approves/revises this stable path for validated publication or returns it to planning. Structural Draft validation must not be reported as implementation approval.

**Future implementation:** Review Required with observed evidence, or Planning Required at a stop condition. Reviewer evaluates the exact validated task revision and bounded implementation; an accepted immutable revision is separate from G10 release authorization. Bounded remediation uses this same task path; new operational/clinical objectives require separate planning.

## Draft validation record

Planner assessment on 2026-10-12: the required task-contract concerns, seven observable acceptance criteria, source references and bounded authoring diff were checked. B0 has the human planning direction; P1/R2 remain proposed for the selected narrower behavior; A3 has a reusable Member implementation boundary but applicable evidence/storage authorization still needs confirmation; D4 is bounded as a proposal; T5 is **not passed**. The remaining approvals are listed above. No application acceptance, independent review, operational gap closure or release approval is asserted.
