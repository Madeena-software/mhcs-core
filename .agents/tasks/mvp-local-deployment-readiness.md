---
title: Local MHCS Deployment and Manual Operator Testing Readiness
document_id: MHCS-TASK-LOCAL-DEPLOYMENT-READINESS-001
version: 1.1
status: validated-published
language: en-US
last_updated: 2026-08-13
scope:
  - prepare the existing MHCS application locally with dummy data
  - hand off the complete Operator-to-DICOM journey for user-led manual testing
authority_note: This task is executable only after this exact content is committed and its immutable task revision is supplied to the Executor.
---

# Executable Task

## Task identity

**Task title:**
`Local MHCS Deployment and Manual Operator Testing Readiness`

**Task path:**
`.agents/tasks/mvp-local-deployment-readiness.md`

**Task contract state:**
`Validated/Published when this exact content is committed and its commit SHA is supplied.`

**Delivery objective / Work Package / MVP:**
`Pre-deployment local MVP: user-led Operator and Image Gateway verification`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The reviewed implementation revision
`6f91c7b0c830c6bbbdc358ccfafe2ee25a16a47a` is accepted as the functional
baseline for the approved plain-private-object and concurrent-capture design.
Its fake-backed tests, full PHPUnit suite, browser checks, build, formatter,
and diff check passed.  The automated live rehearsal stopped in the seeded
identity/consent browser journey before capture, so it did not reach configured
S3, MPIPS, the DICOM viewer, or normal download.

MVP-DEC-039 explicitly makes this remaining interactive verification
user-led.  This task prepares a disposable local runtime and hands the user a
safe, exact manual checklist.  It does not require the Executor to force a
browser automation workaround, run a capture, or contact S3/MPIPS.

## Baseline and task revision

**Implementation baseline:**
`6f91c7b0c830c6bbbdc358ccfafe2ee25a16a47a` — accepted for local manual
testing, with the live external journey pending user observation.

**Governing predecessor:**
`.agents/tasks/private-object-concurrent-capture-transport.md @
10ac3604fd57e647b6d500801f74387521033237`

**Task revision:**
`resolved when published`

## Authoritative inputs

- `docs/mvp/decision-log.md` — MVP-DEC-033, MVP-DEC-035, MVP-DEC-036,
  MVP-DEC-038, and MVP-DEC-039.
- `.agents/context/project.md`, `.agents/context/modules/operator/project.md`,
  and `.agents/context/modules/image-gateway/project.md`.
- `README.md` and `docs/mvp/local-core-walkthrough.md`.
- `docs/mvp/evidence/mvp-local-mpips-operator-rehearsal.md` — automated checks
  passed; the live external path remains user-led manual verification.

## Objective

Prepare the existing application, database queue worker, dummy accounts, and
local test guide so the user can manually test the full Operator workflow from
login through two NPZ uploads, configured local MPIPS, vertical read-only
Cornerstone viewer, and a normal authenticated DICOM download.

## In scope

- Confirm by names only—not values—that local configuration supplies the
  existing application/security keys, disposable MySQL database, database
  queue, private S3 disk, AWS configuration, and local MPIPS configuration
  named by the walkthrough.  Do not read, print, copy, change, or commit a
  value or secret.
- Confirm the selected MySQL database is expressly local and disposable.  Warn
  immediately before `migrate:fresh`; then run existing migrations and
  `Database\Seeders\MvpCoreClinicSeeder`.
- Build existing frontend assets where needed and start only the native
  loopback web server at `127.0.0.1:8013` plus the existing database queue
  worker restricted to `image-gateway`, using its configured 390-second
  timeout.  Leave both running for the user after successful handoff.
- Verify loopback reachability of the Operator login, public LCD, and the
  initial Operator portal route.  Confirm ignored local `credential.txt`
  exists with restrictive permissions without opening, printing, or copying it.
- Update only the local readiness evidence/report and existing walkthrough if
  a command/path/operational step is inaccurate.  Do not alter application
  behavior to make browser automation pass.
- Provide an exact manual checklist which directs the user to: log in with a
  seeded Operator account; select the seeded active site; complete the
  existing arrival, identity, paper-consent, ticket, basic-examination, paper
  questionnaire, and X-ray steps; keep the capture page open; upload the
  approved local radiograph/gain pair; use a component-only retry if shown;
  open the result as a second authorised same-site/current-shift Operator;
  verify vertical read-only viewing; and download the raw DICOM as a normal
  browser file.  Do not place the NPZ pair path/name/bytes/metadata in a
  committed guide or evidence.
- Create `docs/mvp/evidence/mvp-local-deployment-readiness.md` with task and
  implementation revisions, redacted migration/seed/build/process/readiness
  results, manual checklist, process stop instructions, known limitations, and
  explicit non-disclosure/non-production confirmation.

## Out of scope

- Production/server deployment, 37-member import, bucket/IAM/provider/region
  changes, reverse proxy, Docker/Compose, CI/CD, release, or a real-data run.
- Executor-driven capture, direct S3/MPIPS probe, NPZ inspection/copy/rename,
  DICOM parsing/editing, or any attempt to force the browser journey.
- Implementing manual-test feedback not yet supplied by the user.  A reported
  defect returns to Planner/Reviewer for a bounded task.

## Preserved behavior

- Private plain-byte S3 objects remain opaque-keyed, grant-authorised,
  integrity-checked, and non-public.  Raw NPZ remains unavailable to browsers.
- The 100 MiB individual file setting is `MHCS_MAX_UPLOAD_MB=100`; the
  application derives the two-file multipart envelope.
- MPIPS remains an MHCS-to-private-service call only.  The Indonesian UI,
  active-site/current-shift authorization, read-only vertical Cornerstone
  viewer, and ordinary authenticated DICOM attachment download remain intact.

## Acceptance criteria

- [ ] A disposable local database is freshly migrated and seeded with existing
  dummy data, and the native web/queue processes are reachable on loopback.
- [ ] The evidence report gives the user a redacted, accurate manual checklist
  and process-stop instructions, without a secret, credential, object ID,
  patient/binary data, or production/release claim.
- [ ] The user can begin manual testing without an Executor-side S3/MPIPS call
  or an automation workaround.  The live DICOM journey is labelled manual
  verification, not claimed as automatically proven.

## Verification requirements

- Run `npm run build`, `vendor/bin/pint --test`, `git diff --check`, and the
  focused existing dummy-seed/Operator-startup browser checks that do not call
  S3 or MPIPS.
- Record redacted migration/seed, web process, queue process, loopback, and
  credential-file permission outcomes.

## Stop conditions

- Stop if the database is not demonstrably disposable, local configuration is
  incomplete, the application/worker cannot start, or readiness needs an
  application/infrastructure change outside this task.
- Stop if completing handoff would reveal a secret, credential, bucket/object
  identifier, patient data, NPZ/DICOM content, or require an Executor S3/MPIPS
  request.

## Side-effect authorization

### Explicitly authorised side effects

- Disposable local MySQL reset/migration/seed, local build/test work, and
  starting the existing loopback web process plus `image-gateway` worker.
- Repository changes limited to readiness evidence and necessary local-guide
  corrections.

Not authorised: Git commit, push, pull request, production/server mutation,
release, S3/MPIPS calls, bucket/IAM changes, secret disclosure, real-member
import, or unreviewed feedback fixes.

## Expected terminal outcome

`USER TESTING READY` — return an immutable implementation revision and
redacted local handoff evidence.  User-reported findings return to
Planner/Reviewer for a separate bounded remediation task.
