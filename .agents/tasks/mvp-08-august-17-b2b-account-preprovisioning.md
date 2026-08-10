---
title: 17 August B2B Account Pre-provisioning
document_id: MHCS-TASK-MVP-08-001
version: 0.1
status: Approve
language: en-US
last_updated: 2026-08-10
scope:
  - 17 August B2B roster account pre-provisioning
  - NIK sign-in and temporary credentials
  - protected identity handling
authority_note: This Draft is not executable until it is committed and published at an immutable Git revision.
---

# Executable Task

## Task identity

**Task title:**  
`17 August B2B Account Pre-provisioning`

**Task path:**  
`.agents/tasks/mvp-08-august-17-b2b-account-preprovisioning.md`

**Task contract state:**  
`Draft`

**Delivery objective / Work Package / MVP:**  
`17 August clinic-day MVP core / MVP-08 / WP-10`

**Owner / designated planning authority:**  
`Faliq Adlan, CTO`

## Delivery context

The 17 August clinic-day candidate needs the supplied 37-adult B2B roster to have
active Member accounts before the later profile-and-asset gate and fixed B2B
booking work. The partner provides a local PDF; the approved one-time input is
the ignored, manually transcribed CSV at `docs/mvp/import-data/README.md`.

This task is one prerequisite for the complete clinic-day MVP local-working
deadline of Tuesday, 11 August 2026 at 12:00 Asia/Bangkok (MVP-DEC-029).
 b
This task delivers account preparation only. It deliberately does not treat
accounts as identity-verified, ticket-eligible, booked, funded, or released.

## Baseline and task revision

**Implementation baseline:**  
`174527b38c12b536fc21c4182358283a28248dd9`

**Task revision:**  
`resolved when published`

The task must be committed before it becomes executable. Its governing identity
will be:

```text
.agents/tasks/mvp-08-august-17-b2b-account-preprovisioning.md @ <commit SHA containing this exact task>
```

## Objective

**Objective:**  
Provide one controlled, developer-run 17 August B2B CSV import that pre-provisions
eligible adult Member accounts with protected NIK sign-in and one-time temporary
credentials, without retaining plaintext credentials in MHCS.

## Authoritative inputs

### Governing authority

- `docs/mvp/beta-scope.md` — approved 17 August clinic-day addendum.
- `docs/mvp/decision-log.md` — MVP-DEC-021, MVP-DEC-025 through MVP-DEC-029.
- `docs/mvp/import-data/README.md` — exact local CSV contract and rejection
  rules.
- `.agents/context/modules/member/project.md` — Member ownership, B2B
  provisioning, protected identifiers, and mandatory password replacement.
- `.agents/context/project.md` — modular-monolith architecture and Image
  Gateway boundary.

### Requirement traceability

- `MEM-163` → `.agents/context/modules/member/project.md`, 17 August addendum,
  and MVP-DEC-021/025/026/027/028/029.
- `MEM-164` → `.agents/context/modules/member/project.md`; reuse existing NIK
  sign-in.
- `MEM-214` → `.agents/context/modules/member/project.md`; this task delivers
  only the approved 17 August account-and-credential sub-scope, not points or
  bookings.
- `MEM-228` → `.agents/context/modules/member/project.md`; use Member-owned
  application boundaries and audit controls.

## Scope

### In scope

- Add a developer-run, explicit local CSV import path for the exact approved
  header: `name,birthplace,birth_date,ktp_address,nik`.
- Preflight the entire CSV before changing data: exact header, required cells,
  UTF-8/readability, strict `YYYY-MM-DD`, exact NIK format, adult eligibility,
  duplicate NIKs in the file, and protected-NIK conflicts already in MHCS.
- Reject the whole import with no Member/User changes when preflight fails.
- For every valid row, create exactly one active, login-enabled adult User and
  linked Member with protected NIK, `registration_source=administrator`,
  `identity_status=pending_verification`, `identity_document_type=ktp`, no
  email or phone, `administrative_gender=unspecified`, and `current_address`
  populated from `ktp_address`.
- Reuse the existing protected-identifier, medical-record-number,
  temporary-credential, authentication, transaction, and audit mechanisms.
- Generate one distinct cryptographically secure temporary password per created
  account, persist only its adaptive hash, and require replacement at first
  successful sign-in.
- Produce a one-time local credential CSV only at an explicitly supplied,
  non-repository path for the designated B2B contact. It may contain only the
  NIK and temporary password needed for that handoff; do not log, audit, echo,
  fixture, or commit it.
- Add focused synthetic-data tests for successful provision, login and forced
  password replacement, every rejection category, no partial import, protected
  duplicate detection, no persisted plaintext password, and no asset/ticket
  eligibility implied by account creation.

### Out of scope

- PDF parsing, browser upload, public registration, or a Filament import UI.
- Real roster import, real credential delivery, deployment, release approval,
  or any repository-tracked credential output.
- Profile, KTP, or photograph upload/review UI; profile/ticket eligibility
  changes; identity verification; consent changes; or any generic registration
  rule change.
- B2B agreements, points, entitlements, fixed bookings, booking changes,
  payments, or financial behavior.
- Operator, Operator administration, LCD, Printer Station, Image Gateway, AI,
  MPIPS, storage, conversion, doctor, result, or Gateway-contract work.
- New birthplace or KTP-address schema fields, a new gender value, or a broad
  Member-identity refactor.

### Preserved behavior

- Normal Member registration continues to require its approved KTP/KIA and
  profile-photo inputs; the import is a dedicated 17 August provisional-account
  path, not a relaxation of normal registration.
- Existing NIK authentication, password replacement, account-state checks,
  protected-identifier handling, authorization, and audit sanitization remain
  fail-closed.
- Imported accounts remain unverified and cannot bypass the later required
  profile, KTP, photo, front-desk verification, consent, or ticket gates.
- No plaintext password, raw PDF, NIK, address, or birthplace may enter logs,
  audit metadata, tests, fixtures, documentation, or Git history.
- The Image Gateway/AI/MPIPS contract and implementation boundary remain owned
  by the separate branch and are not consumed or altered here.

## Dependencies and assumptions

### Dependencies

- The existing Member identity, NIK sign-in, and forced-password-replacement
  foundations remain available at the declared baseline.
- The Member Administrator manually prepares the ignored CSV according to
  `docs/mvp/import-data/README.md`.
- The later profile-and-asset eligibility task and B2B fixed-booking task are
  separate prerequisites for a clinic visit.

### Approved assumptions

- All 17 August rows represent adults; any minor row is a blocking import error.
- The missing gender maps to the existing `unspecified` value.
- `ktp_address` is the Member's initial current address; birthplace is not
  stored in MHCS for the clinic-day import.
- The credential recipient and spreadsheet handoff occur outside MHCS and only
  after release-candidate verification.

### Remaining approval requirements

- Faliq Adlan, CTO, must authorize the commit that publishes this task before
  an Executor starts work.
- Faliq Adlan, CTO, must record release-candidate verification before any real
  roster or credential file is processed.
- Deployment remains a separate release decision.

## Required capabilities

- Repository read and write.
- Local shell and PHP/Pest test execution.
- Local synthetic database execution.

## Execution constraints

- Use the established Member module and shared security services; add no
  dependency, service framework, browser flow, or database field unless this
  task proves one is indispensable.
- Do not use the normal `MemberRegistrationService` as an excuse to mark an
  unsubmitted KTP/photo as present. The dedicated provisional path must retain
  `pending_verification` and create no verification-asset record.
- Validate all input before any database mutation. Create all account records
  in one database transaction; handle credential-output failure without leaving
  a partial account batch or a retained plaintext credential artifact.
- Require an explicit execution flag and a caller-supplied credential-output
  path. Refuse an existing, repository-tracked, or unsafe output target.
- Use only synthetic CSV data and temporary local output in automated tests.
- Do not expose real input values or passwords in exception messages or command
  output.

## Acceptance criteria

- [ ] A valid synthetic CSV creates one active, login-enabled adult User and
  linked pending-verification Member for every row, with a protected NIK,
  generated MRN, `administrator` source, `unspecified` gender, and initial
  current address.
- [ ] A created Member can sign in by NIK with the issued temporary password
  and is forced to replace it before normal Member navigation.
- [ ] The importer rejects malformed/unreadable input, wrong header, missing
  cells, invalid date, minor, invalid NIK, in-file duplicate, and existing
  protected-NIK duplicate without persisting any account from that file.
- [ ] No plaintext credential is stored in the database, logs, audit metadata,
  test fixtures, repository, or command output; the one-time output is written
  only to the explicit local destination after successful provision.
- [ ] Imported accounts create no KTP/photo asset, do not become identity
  verified, and cannot bypass later profile/asset/ticket gates.
- [ ] Existing standard registration and existing Member authentication behavior
  remain covered and unchanged.
- [ ] No Image Gateway, AI, MPIPS, booking, entitlement, financial, Operator,
  LCD, Printer, or deployment behavior is added or changed.

## Verification requirements

### Required checks

- Run the focused new import tests using synthetic CSV/input and temporary
  output paths.
- Run the existing Member identity/access and security tests that cover NIK
  authentication, password replacement, protected identifiers, and audit
  sanitization.
- Run the relevant database migration/test suite on the supported local
  database configuration; use the repository MySQL verification path when the
  change touches MySQL-sensitive behavior.
- Run `git diff --check`.

### Required evidence

The Executor must report:

- exact implementation revision or working-tree state;
- commands actually run and observed results;
- synthetic-only test evidence for each acceptance criterion;
- any migration, credential-output, or MySQL limitation;
- confirmation that no real PDF, roster, NIK, address, or credential was used
  or emitted.

## Stop conditions

- Stop and return to planning if a new data field, gender value, browser import
  UI, B2B commercial/booking decision, profile/asset gate change, or Gateway
  behavior is needed.
- Stop if safe credential-output semantics cannot be achieved without retaining
  plaintext credentials or widening this task's side-effect authority.
- Stop before any real-data input, external credential delivery, deployment,
  release decision, or unapproved commit/push.
- Stop if the implementation baseline changes in a way that overlaps the Member
  identity, authentication, or import surfaces.

## Authorized side effects

- Repository changes strictly required for this task, including focused tests
  and documentation updates that accurately reflect the implementation.
- Local synthetic database/test data and temporary local synthetic credential
  files created by automated tests, provided they are removed and not tracked.

Not authorized: real roster or credential processing, external delivery,
deployment, commit, push, dependency installation, schema changes outside
proven need, or any Image Gateway/AI/MPIPS implementation.

## Expected terminal outcome

`IMPLEMENTATION AND VERIFICATION RESULT REQUIRED` — an Executor returns the
implemented revision and observed synthetic verification evidence for review.
The result does not authorize real import or release.
