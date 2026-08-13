---
title: Local MHCS Deployment Readiness
document_id: MHCS-TASK-LOCAL-DEPLOYMENT-READINESS-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-13
scope:
  - start the existing MHCS application locally with dummy data and Operator access
  - hand off a safe, observable local testing environment to the user
authority_note: This task is executable only after this exact content is committed and its immutable task revision is supplied to the Executor.
---

# Executable Task

## Task identity

**Task title:**
`Local MHCS Deployment Readiness`

**Task path:**
`.agents/tasks/mvp-local-deployment-readiness.md`

**Task contract state:**
`Validated/Published when this exact content is committed and its commit SHA is supplied.`

**Delivery objective / Work Package / MVP:**
`Local deployment readiness before user-led Operator testing`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The accepted Image Gateway integration provides the normal Operator upload →
private S3 → database queue → local MPIPS → DICOM path.  The local rehearsal
has a valid stopped result because the CTO-approved local Grabber NPZ pair is
currently absent; no substitute is allowed.  The application can still be
deployed locally now with existing dummy data so the user can test login,
Operator queue workflow, uploads other than the unavailable NPZ pair, and UI
behavior.

This task starts the existing native local application and its Image Gateway
worker with a disposable dummy-data database, verifies readiness, and provides
a redacted handoff.  It does not claim that the missing-pair DICOM journey was
tested, and it does not pre-authorise future unspecified user-feedback fixes.

## Baseline and task revision

**Implementation baseline:**
`71f78d79addcee302b66a1b59aa75431dc389ae8` — local-rehearsal task revision
with CTO-approved upload-limit behavior and the stopped-pair evidence.

**Accepted functional baseline:**
`0f6f6e3552a4ace5a057e6415eac8057cd03dcee` — MPIPS + AWS Image Gateway
integration accepted for local integration evidence.

**Related stopped execution:**
`.agents/tasks/mvp-local-mpips-operator-rehearsal.md @
71f78d79addcee302b66a1b59aa75431dc389ae8` — approved local Grabber pair
unavailable; no capture, queue, MPIPS, or DICOM live rehearsal result exists.

**Task revision:**
`resolved when published`

## Objective

**Objective:**
Make the existing MHCS application available on local loopback with synthetic
dummy accounts and the existing `image-gateway` worker, then hand it to the
user for local UI/workflow testing without deployment or external-data claims.

## Authoritative inputs

### Governing authority

- `.agents/context/project.md` — single local application, private Image
  Gateway/MPIPS boundary, database queue, and private storage ownership.
- `.agents/context/modules/operator/project.md` — Operator account, active-site
  and current-shift workflow authority.
- `README.md` and `docs/mvp/local-core-walkthrough.md` — current local setup
  and user journey instructions.
- `docs/mvp/evidence/mpips-aws-image-gateway-integration.md` — accepted local
  integration evidence, not deployment approval.
- `docs/mvp/evidence/mvp-local-mpips-operator-rehearsal.md` — current stopped
  result for the unavailable approved Grabber pair.
- CTO decisions in this conversation: use local dummy Members; configured S3
  and local MPIPS are used when the approved pair exists; local deployment and
  later user testing are desired; any user feedback must return through
  Planner/Reviewer tasking before implementation.

### Requirement traceability

- `OPR-031..OPR-046` → locally available Operator queue workflow.
- `MVP-DEC-033` → private upload objects remain protected.
- `MVP-DEC-035/036` → DICOM access policy is preserved, without claiming an
  unperformed live DICOM conversion.

## Scope

### In scope

- Verify, without printing values, that the local `.env` has the existing
  application/encryption keys, disposable MySQL connection, database queue,
  configured private S3 disk, and local MPIPS configuration required by the
  current runbook.  Do not read, copy, display, or alter values or secrets.
- Confirm that the selected MySQL database is expressly local and disposable;
  warn before `migrate:fresh`, then run the existing migrations and
  `Database\\Seeders\\MvpCoreClinicSeeder`.
- Build existing frontend assets if required and start only the existing native
  loopback web server on `127.0.0.1:8013` and the existing database worker
  restricted to the `image-gateway` queue with its configured 390-second
  timeout.  Record redacted process/readiness status and leave the local
  processes available for the user after successful handoff.
- Verify local reachability of the Operator login, the seeded attendance path,
  and the public LCD path without exposing credentials, synthetic NIK, booking
  IDs, or other protected data.  Confirm `credential.txt` exists locally with
  restrictive permissions; do not print, open, or copy it.
- Create `docs/mvp/evidence/mvp-local-deployment-readiness.md` with exact task
  and implementation revisions; migration/seed/build/process/readiness results;
  known limitations; handoff URL category (without secrets); and explicit
  non-disclosure and non-deployment confirmation.
- Hand off the updated README/walkthrough as the user test guide.  Explicitly
  state that the user can test all locally available UI flows, but the two-NPZ
  live DICOM conversion awaits restoration of the approved local Grabber pair.

### Out of scope

- Any production/server deployment, reverse proxy, Docker/Compose, process
  manager, CI/CD, database import of 37 Members, bucket/IAM change, or release.
- Any capture substitute, NPZ fixture creation/copy/rename/inspection,
  direct MPIPS request, S3 probe, DICOM conversion attempt, or change to the
  accepted Image Gateway implementation.
- Implementing user feedback that has not yet been supplied and reviewed as a
  bounded task; changing application behavior, schema, dependencies, queue
  policy, storage policy, or authorization.

### Preserved behavior

- Existing authenticated Operator, dummy seed, active-site/current-shift,
  private-object, Indonesian UI, Image Gateway queue, and DICOM access behavior
  remain unchanged.
- Raw NPZ remains unavailable to browsers.  No fake DICOM, synchronous MPIPS
  call, or static-result replacement is introduced.
- All local work remains non-clinical and must not be represented as production,
  deployment, release, or complete live-DICOM evidence.

## Dependencies and assumptions

### Dependencies

- Existing local PHP, MySQL, Node, and browser runtime are available.
- The user has already supplied necessary local `.env` values; the Executor has
  no authority to request, regenerate, disclose, or modify them.

### Approved assumptions

- The existing five synthetic Members and three seeded Operators are sufficient
  for local UI testing; no 37-member server seed is needed.
- The approved local Grabber pair remains unavailable for this task; its later
  return is a prerequisite for a live conversion rehearsal, not for local app
  availability.

### Remaining approval requirements

- User feedback must be supplied after testing and reviewed by the Planner;
  only then can a new bounded remediation task authorise a fix.
- Deployment, real data, server import, and release require separate approval.

## Required capabilities

- Repository read/write, local PHP/Laravel/MySQL/npm process execution, and
  loopback HTTP/browser readiness checks.

## Execution constraints

- Use existing Laravel commands and configuration.  Do not write a launch
  script, create a Docker file, add a dependency, or modify `.env`.
- Do not run `migrate:fresh` until the target is confirmed disposable; stop if
  it is unclear.  Do not run a capture, S3, or MPIPS probe.
- Keep all server/worker output redacted.  Never expose environment values,
  credentials, token-like strings, bucket/object identifiers, synthetic
  personal data, NPZ contents, or DICOM bytes.
- The handoff must include a clear process stop instruction for the local web
  server and queue worker, without modifying deployment/runtime configuration.

## Acceptance criteria

- [ ] The native web process and `image-gateway` worker are running locally and
  the Operator login, seeded attendance, and public LCD routes are reachable.
- [ ] The local database was freshly migrated and seeded only after confirming
  it was disposable; local credentials remain unexposed and protected.
- [ ] The evidence report truthfully records readiness and the unavailable-pair
  limitation, with no secret, clinical, binary, or infrastructure disclosure.
- [ ] The user receives a clear local test handoff and understands that later
  feedback is routed to a separate Planner-created remediation task.

## Verification requirements

### Required checks

- Run `npm run build`, `vendor/bin/pint --test`, and `git diff --check`.
- Run the focused existing seed/Operator/Image Gateway browser verification
  needed to establish the local UI still starts; do not run tests that call
  AWS or MPIPS.
- Record redacted database migration/seed and local process/readiness results.

### Required evidence

The Executor must report exact task/implementation revisions, commands and
observed results, process readiness, changed files, known limitations, stop
instructions, and explicit no-disclosure/non-production confirmation.

## Stop conditions

- Stop if the database is not demonstrably disposable, local secrets/config are
  missing, the app cannot start, or a change outside documentation/evidence is
  needed.
- Stop if local deployment would need the unavailable NPZ pair, an S3/MPIPS
  call, server/infrastructure mutation, a secret disclosure, or user feedback
  not yet supplied as a bounded requirement.

## Side-effect authorization

### Explicitly authorized side effects

- Disposable local MySQL reset/migration/seed; local build/test work; starting
  and stopping the existing local loopback web and `image-gateway` worker
  processes.
- Repository changes limited to a local-deployment readiness evidence report
  and necessary documentation corrections.

Not authorized: Git commit, push, pull request, deployment, release,
production/server mutation, S3/MPIPS external call, bucket/IAM change, secret
disclosure, real-member import, or implementing future user feedback.

## Expected terminal outcome

`USER TESTING READY` — return an immutable implementation revision and redacted
local handoff evidence.  Subsequent user feedback returns to Planner/Reviewer
for a separate bounded remediation task.
