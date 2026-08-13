---
title: Local MHCS Queued-Capture Rehearsal and Manual Operator Testing Readiness
document_id: MHCS-TASK-LOCAL-DEPLOYMENT-READINESS-001
version: 1.2
status: validated-published
language: en-US
last_updated: 2026-08-13
scope:
  - prepare the accepted queued-capture application locally with synthetic data
  - hand off the complete Operator-to-DICOM journey for user-led testing
authority_note: This task is executable only after this exact content is committed and its immutable task revision is supplied to the Executor.
---

# Executable Task

## Task identity

**Task title:**
`Local MHCS Queued-Capture Rehearsal and Manual Operator Testing Readiness`

**Task path:**
`.agents/tasks/mvp-local-deployment-readiness.md`

**Task contract state:**
`Validated/Published when this exact content is committed and its commit SHA is supplied.`

**Delivery objective / Work Package / MVP:**
`Pre-deployment local MVP: user-led Operator and Image Gateway queued-capture verification`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The accepted implementation uses one durable-source, queued-MPIPS flow in
every environment. The capture request durably stores the radiograph NPZ,
matching gain NPZ, manifest, and signature on the configured private disk;
then it atomically accepts the capture, advances the ticket to `awaiting_ai`,
and queues `ProcessCaptureSet`. Only the `image-gateway` worker calls MPIPS
and persists validated DICOM.

For this disposable local rehearsal, the private disk is the existing local
filesystem. Production remains private S3. Disk selection changes neither the
business sequence, authorization, retry rule, nor DICOM visibility. The user,
not the Executor, performs the live NPZ-to-MPIPS-to-DICOM journey.

## Baseline and task revision

**Implementation baseline:**
`19ae9e16c6cae1ec0bfadf29afcf1c5fd6b2abfd` — accepted queued-capture
implementation, including remediation closure for releasing the browser unload
warning immediately after durable capture acceptance.

**Governing predecessor:**
`.agents/tasks/operator-async-capture-status-and-worklist-sync.md @
8afd3dedc9f7e4920d59beb9e94d2e480bd6bc9f`

**Task revision:**
`resolved when published`

## Authoritative inputs

### Governing authority

- `docs/mvp/decision-log.md` — MVP-DEC-039, MVP-DEC-040, and MVP-DEC-041.
- `.agents/context/project.md` — Image Gateway ownership, private MPIPS
  boundary, and asynchronous processing topology.
- `.agents/context/modules/operator/project.md` — active-site/current-shift
  operator workflow and queue ownership.
- `.agents/context/modules/image-gateway/project.md` — private-object,
  worker-only MPIPS, returned-DICOM, and authorization policy.

### Requirement traceability

- `ARCH-030`, `ARCH-041`, `ARCH-042` → queued Image Gateway topology in
  `.agents/context/project.md` and MVP-DEC-041.
- `IMG-006`, `IMG-007`, `IMG-013`, `IMG-028`, `IMG-060` →
  `.agents/context/modules/image-gateway/project.md` and MVP-DEC-040/041.
- `OPR-040`, `OPR-046`, `OPR-060`, `OPR-108`, `OPR-118` →
  `.agents/context/modules/operator/project.md` and MVP-DEC-041.

## Objective

Prepare one clean, disposable local runtime with synthetic Members and
Operators, four native HTTP workers plus one `image-gateway` queue worker, and
an accurate redacted manual checklist. The user must be able to start from
Operator login and manually test the queued capture through returned DICOM,
vertical read-only Cornerstone viewing, and normal authenticated DICOM
download.

## Scope

### In scope

- Confirm only by boolean/name checks that the ignored local runtime has
  `APP_ENV=local`, `MHCS_PRIVATE_OBJECT_DISK=local`, `QUEUE_CONNECTION=database`,
  and the existing local MPIPS and upload-limit variable names. Do not print,
  copy, log, or commit values, secrets, credentials, database names, or object
  identifiers.
- The owner has authorised resetting the existing disposable local runtime.
  Before any reset, assert without printing values that the application is in
  local mode, uses the local private disk, and uses MySQL at a loopback host
  (`127.0.0.1` or `localhost`). Stop if any assertion fails.
- Reset only the exact local private-object subtree
  `storage/app/private/objects` after confirming its `storage/app/private`
  parent is a real directory under this repository and an existing `objects`
  path is not a symlink. If `objects` is absent, create only that empty path.
  Reset it together with the confirmed disposable database; never list,
  inspect, copy, hash, or record objects.
- Run existing migrations and `Database\Seeders\MvpCoreClinicSeeder`. The
  seed creates five synthetic Members and three same-site Operators. Do not
  disclose the generated `credential.txt` contents.
- Build the existing frontend, then start the existing native loopback Laravel
  server on `127.0.0.1:8013` with `PHP_CLI_SERVER_WORKERS=4` and one existing
  database worker restricted to `image-gateway` with `--timeout=390`.
  These are exactly five local workers: four interchangeable HTTP workers and
  one MPIPS/Image Gateway queue worker. Do not add route-specific upload
  workers, a process manager, a new queue, Docker/Compose, or any dependency.
- Prove the web process has four HTTP worker children and the queue process is
  restricted to `image-gateway`; check loopback Operator login, public LCD,
  and the initial Operator route without calling S3 or MPIPS.
- Update `README.md` and `docs/mvp/local-core-walkthrough.md` only where needed
  to remove stale local-S3/encryption/page-open wording. They must state that
  local uses the configured private filesystem, that source acceptance queues
  MPIPS, and that an accepted capture continues if the page is closed.
- Replace the current contents of
  `docs/mvp/evidence/mvp-local-deployment-readiness.md` with this run's
  redacted evidence and manual checklist. Preserve the historical manual
  findings only as superseded context if still useful; do not represent them as
  defects in the accepted queued implementation.
- During this execution attempt, correct a factual local-launch, local-reset,
  or local-guide error directly when it is confined to this task. A user report
  involving capture behavior, queue state, authorization, MPIPS/DICOM,
  Cornerstone, or download behavior is a product defect and must return to the
  Planner/Reviewer rather than be broadened silently.

### Out of scope

- Production/server deployment, release, CI/CD, Docker/Compose, reverse proxy,
  37-member import, AWS/S3/bucket/IAM/region changes, or changing committed
  production defaults.
- Executor-driven NPZ submission, S3/AWS or MPIPS request/probe, raw NPZ or
  DICOM inspection/parsing/copying, object-store listing, or browser-automation
  workarounds.
- Application behavior changes, migrations, new workers, new queues, retry
  policy changes, or unbounded implementation of future user feedback.

### Preserved behavior

- Local and production use the same durable-source, accepted-capture,
  worker-only-MPIPS, returned-DICOM flow. Only the configured private disk
  differs: local filesystem here and private S3 in production.
- Every private object remains plain-byte, opaque-keyed, grant-authorised,
  integrity-checked, and non-public. Browsers never access raw NPZ.
- A current-shift Operator authorised at the active site may view and download
  returned raw DICOM as a normal authenticated attachment; the viewer remains
  vertical and read-only with automatic VOI plus zoom/pan only.
- During an NPZ XHR the controls and native unload protection remain active.
  Once safe status is `queued` or `processing`, processing is durable and the
  Operator may close the page; reopening resumes safe polling. A retry uploads
  only the unsuccessful source component.

## Dependencies and assumptions

### Dependencies

- The accepted implementation baseline above and its fake-backed verification
  evidence in `docs/mvp/evidence/mvp-operator-async-capture-status-and-worklist-sync.md`.
- An existing explicitly disposable local database and an already configured
  local MPIPS service for the user's later manual interaction. The Executor
  does not contact MPIPS.

### Approved assumptions

- `storage/app/private/objects` is the root used by the configured local
  private disk; `config/filesystems.php` keeps that disk private and unserved.
- Native PHP worker capacity is provided by the existing
  `PHP_CLI_SERVER_WORKERS=4` setting; a single `image-gateway` worker is the
  only local process permitted to call MPIPS.

### Remaining approval requirements

- User-led manual test observations require a later Planner/Reviewer review;
  neither this handoff nor local readiness is deployment or release approval.

## Required capabilities

- Repository read/write limited to the named documentation and evidence files.
- Local shell, PHP/Laravel, Node, and browser/loopback checks.
- Local process start/stop for the named loopback web and queue commands.

## Execution constraints

- Do not open `.env`, `credential.txt`, object files, NPZ, DICOM, or logs for
  content. Configuration checks may return only a PASS/FAIL result.
- Do not execute the reset unless the non-disclosing local-mode, MySQL,
  loopback-host, and private-disk assertions pass. A non-local disk,
  non-loopback host, missing private parent directory, symlink, occupied
  loopback port, or stale/unknown process is a stop condition; do not kill an
  unknown process.
- Use the existing application and queue commands only. Do not create scripts,
  compose files, supervisor configuration, test fixtures, or application code.
- Run automated checks sequentially because the test suite shares local
  private-storage state. Do not run PHPUnit commands in parallel.
- The user alone selects the approved non-clinical Grabber NPZ pair during the
  later manual journey. Do not put its path, filename, bytes, metadata, or
  contents into committed files, command output, or evidence.

## Acceptance criteria

- [ ] The local runtime has a confirmed private filesystem disk and no
  Executor-side S3/AWS call. The committed production S3 default is unchanged.
- [ ] Only the confirmed disposable database and exact local private-object
  subtree are reset; migrations and the existing synthetic seed complete.
- [ ] Exactly four native HTTP workers and one `image-gateway` queue worker
  are running for the user on loopback, with no new queue or worker type.
- [ ] Operator login, public LCD, and the initial Operator route are reachable
  locally, and the ignored credential file exists with mode `0600` without its
  contents being read or disclosed.
- [ ] The walkthrough and evidence accurately instruct the user to test the
  login-to-DICOM flow, byte-level upload progress, durable acceptance/page-close
  behavior, same-site/current-shift second-Operator visibility, vertical
  read-only viewer, and normal DICOM attachment download.
- [ ] Documentation/evidence contains no secret, credential, database name,
  object ID/key, patient data, NPZ/DICOM content, S3/MPIPS probe, production,
  or release claim.

## Verification requirements

### Required local preparation commands

Run these commands in order. They print no configuration value, credential,
object name, or binary content. Do not replace the exact object-reset target
with a broader path or a glob.

```bash
TARGET="."
TARGET="$TARGET" php artisan optimize:clear --quiet
TARGET="$TARGET" php artisan tinker --execute='$connection = config("database.default"); $host = config("database.connections.".$connection.".host"); if (! app()->environment("local") || $connection !== "mysql" || ! in_array($host, ["127.0.0.1", "localhost"], true) || config("mhcs.private_object_disk") !== "local") { throw new RuntimeException("Local runtime assertion failed."); } echo "Local runtime assertion: PASS".PHP_EOL;'

LOCAL_PRIVATE_ROOT="$(pwd)/storage/app/private"
LOCAL_OBJECT_ROOT="$LOCAL_PRIVATE_ROOT/objects"
test -d "$LOCAL_PRIVATE_ROOT" && test ! -L "$LOCAL_PRIVATE_ROOT"
test ! -e "$LOCAL_OBJECT_ROOT" || test ! -L "$LOCAL_OBJECT_ROOT"
rm -rf -- "$LOCAL_OBJECT_ROOT"
install -d --mode=0700 "$LOCAL_OBJECT_ROOT"

TARGET="$TARGET" php artisan migrate:fresh --force --quiet
TARGET="$TARGET" php artisan db:seed --quiet --class=Database\\Seeders\\MvpCoreClinicSeeder
```

Start the two existing local processes only after the port is confirmed free or
owned by the Executor's immediately preceding local start. Do not terminate an
unknown listener. Keep both processes running after successful handoff:

```bash
PHP_CLI_SERVER_WORKERS=4 TARGET="." php artisan serve --host=127.0.0.1 --port=8013
TARGET="." php artisan queue:work database --queue=image-gateway --timeout=390
```

### Required checks

- The first preparation command block is the required non-disclosing assertion
  for `APP_ENV=local`, MySQL on a loopback host, and
  `MHCS_PRIVATE_OBJECT_DISK=local`; it also clears configuration cache.
- Run `TARGET="." php artisan migrate:fresh --force --quiet` only after the
  confirmed destructive boundary, then seed without emitting synthetic
  identifiers with `TARGET="." php artisan db:seed --quiet
  --class=Database\\Seeders\\MvpCoreClinicSeeder`.
- Run sequential fake-backed checks:

  ```bash
  TARGET="." vendor/bin/phpunit \
    tests/Feature/Operator/MvpCoreClinicSeederTest.php \
    tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php --colors=never
  TARGET="." vendor/bin/pest tests/Browser/Mvp14OperatorDicomRehearsalTest.php --colors=never
  TARGET="." npm run build
  TARGET="." vendor/bin/pint --test
  TARGET="." git diff --check
  ```

- Verify process command lines and loopback responses without inspecting logs,
  object storage, or private file contents. Do not probe MPIPS; its live
  conversion is manual verification.

### Required evidence

The Executor must report:

- immutable execution revision or exact working-tree state;
- only redacted PASS/FAIL results for configuration, reset, migration, seed,
  build, tests, process count/queue restriction, loopback, and credential-file
  mode;
- exact documentation/evidence files changed;
- the user-facing manual checklist, process stop instructions, and any known
  manual verification gap;
- any blocker or deviation, without secrets, private data, filenames, object
  identifiers, or binary information.

## Stop conditions

- Stop before destructive work if `APP_ENV` is not local, the configured
  connection is not MySQL at a loopback host, the selected private disk is not
  `local`, or the local private parent/path is a symlink or outside the exact
  expected subtree.
- Stop if port `8013` is occupied by an unknown process, if a named process
  cannot start, or if preparation requires a new dependency, worker, queue,
  service, or application behavior change.
- Stop if readiness would require disclosure or inspection of a secret,
  credential, object, NPZ, DICOM, or configured external service, or any
  S3/AWS/MPIPS request.
- Stop and return to planning for any functional manual-test feedback outside
  a factual local-launch/local-guide correction.

## Side-effect authorization

### Explicitly authorised side effects

- After the stated confirmation, reset only the disposable local database and
  `storage/app/private/objects`; migrate and seed the existing synthetic data.
- Build local frontend assets; start the named loopback native web process and
  the one named local queue worker; leave them running for user testing.
- Update only `README.md`, `docs/mvp/local-core-walkthrough.md`, and
  `docs/mvp/evidence/mvp-local-deployment-readiness.md` when needed for this
  local handoff.

Not authorised: Git commit, push, pull request, production/server mutation,
release, AWS/S3/MPIPS call, bucket/IAM change, secret disclosure, real-member
import, application-code change, or unbounded feedback fixes.

## Expected terminal outcome

`USER TESTING READY` — return one immutable execution revision and redacted
local handoff evidence. The user then performs the live local journey and
returns observations to Planner/Reviewer for acceptance or bounded remediation.
