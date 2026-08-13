---
title: Local MHCS Operator-to-DICOM Deployment and Manual Testing Readiness
document_id: MHCS-TASK-LOCAL-DEPLOYMENT-READINESS-001
version: 1.3
status: validated-on-publication
language: en-US
last_updated: 2026-08-13
scope:
  - disposable local deployment of the current Operator and Image Gateway candidate
  - synthetic Operator-to-DICOM manual testing handoff
  - final portrait DICOM viewer manual-review evidence
authority_note: This revised local-testing task is validated/published only when this exact file is committed unchanged and its immutable task revision is supplied in the Executor handoff. It authorizes no production deployment, release, external probe, or application-behavior change.
---

# Executable Task

## Task identity

**Task title:**
`Local MHCS Operator-to-DICOM Deployment and Manual Testing Readiness`

**Task path:**
`.agents/tasks/mvp-local-deployment-readiness.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content; the governing SHA is supplied externally in the Executor handoff.`

**Delivery objective / Work Package / MVP:**
`Pre-deployment local MVP — provide one clean, safe, user-led end-to-end Operator rehearsal for the current DICOM-viewer candidate.`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

The earlier local-readiness handoff predates the accepted readable-display
references and the current portrait DICOM viewer work. Its historical evidence
therefore still records viewer and reference feedback that is no longer an
accurate test checklist.

This task prepares a disposable local runtime for the user to test the entire
current Operator flow: login, paper evidence, queue, basic examination, NPZ
pair submission, queued MPIPS processing, returned DICOM discovery, the
current-tab viewer, the named portrait-monitor popup, and normal DICOM
download. The live user journey supplies the outstanding manual review evidence
for the viewer candidate; it does not itself accept, deploy, or release it.

## Baseline and task revision

**Repository execution baseline:**
`73857140da680c6e15a33e1f6226935b377ec0ea` — current committed repository
state, including the current viewer implementation and task archival.

**Manual-review candidate:**
`a3bcf3fe48a929b87d0ed0e278d537f7a70c1394` — current portrait-viewer
remediation implementation, pending the manual evidence required by its
governing task.

**Related governing task:**
`.agents/tasks/archive/operator-portrait-dicom-viewer.md @
b0b4597250137655ce38e03c93a45fb1104a41b4`.

**Task revision:**
`The full SHA of the commit containing this exact task content, supplied by the Planner after publication.`

## Objective

Reset and start one confirmed disposable local MHCS runtime with synthetic data,
four native HTTP workers, and one `image-gateway` queue worker. Hand the user a
sanitized, current checklist for testing the complete Operator-to-DICOM journey
and the portrait viewer. Record only redacted readiness evidence and leave the
processes running for the user.

## Authoritative inputs

### Governing authority

- `docs/mvp/decision-log.md` MVP-DEC-035, MVP-DEC-036, MVP-DEC-037,
  MVP-DEC-040, and MVP-DEC-041.
- `.agents/context/project.md` — one MHCS application, private MPIPS boundary,
  and queued Image Gateway topology.
- `.agents/context/modules/operator/project.md` — active-site/current-shift
  Operator workflow and queue ownership.
- `.agents/context/modules/image-gateway/project.md` — private objects,
  worker-only MPIPS, returned-DICOM visibility, and raw-DICOM download policy.
- `.agents/tasks/archive/operator-portrait-dicom-viewer.md @
  b0b4597250137655ce38e03c93a45fb1104a41b4` — exact manual-viewer evidence
  required before the candidate can be accepted.

### Requirement traceability

- `ARCH-030`, `ARCH-041`, `ARCH-042` and MVP-DEC-041 → one durable-source,
  queued-MPIPS flow in local and production.
- `IMG-006`, `IMG-007`, `IMG-013`, `IMG-028`, `IMG-060` and MVP-DEC-035/036 →
  private source objects; current-site/current-shift returned-DICOM view and
  normal download.
- `OPR-040`, `OPR-046`, `OPR-060`, `OPR-108`, `OPR-118` → safe Operator flow
  from admission through X-ray result access.
- `UIL-001` and MVP-DEC-037 → Indonesian UI, including the viewer states.
- `MVP local-rehearsal feedback item 5` → portrait current-tab/popup viewer,
  safe failure state, automatic VOI, and zoom/pan only.

## Scope

### In scope

- Confirm without printing values that the ignored runtime is `local`, uses a
  loopback MySQL host, `QUEUE_CONNECTION=database`, and
  `MHCS_PRIVATE_OBJECT_DISK=local`. Confirm the existing local MPIPS and
  upload-limit variable **names** only. Do not open, print, copy, or record
  their values, credentials, database name, object identifiers, or files.
- After those non-disclosing assertions pass, reset only the confirmed
  disposable local database and the exact repository subtree
  `storage/app/private/objects`. Confirm the private parent is a real directory
  under this repository and neither it nor the target is a symlink; create only
  an absent empty `objects` directory with mode `0700`.
- Migrate and run the existing `Database\Seeders\MvpCoreClinicSeeder`. It must
  provide the existing five synthetic Members and same-site/current-shift
  Operators. Do not disclose `credential.txt` content.
- Build the existing frontend. Start exactly four native loopback HTTP workers
  on `127.0.0.1:8013` with `PHP_CLI_SERVER_WORKERS=4` and `--no-reload`, plus
  exactly one existing database queue worker restricted to `image-gateway` with
  `--timeout=390`. Do not add upload-specific workers, a process manager,
  Docker/Compose, a queue, or a dependency.
- For this owner-authorised local redeployment only, an existing listener on
  port `8013` may be stopped **only** when its inspected command and working
  directory identify it as this repository's prior loopback `php artisan
  serve` process. Never terminate an unknown listener or a non-loopback
  process. A stale matching `image-gateway` worker may likewise be stopped only
  after its command is confirmed; never kill by broad name/pattern.
- Verify the four HTTP workers, one queue restriction, loopback Operator login,
  public LCD, and initial Operator route without calling AWS/S3 or MPIPS and
  without inspecting logs, private objects, NPZ, or DICOM.
- Update only `README.md`, `docs/mvp/local-core-walkthrough.md`, and
  `docs/mvp/evidence/mvp-local-deployment-readiness.md` where required to match
  this run: local private filesystem (not S3), plain private objects (no MHCS
  application encryption), durable queued MPIPS after acceptance, and the
  current portrait-viewer manual checklist. Replace obsolete feedback wording
  with a historical note; do not present resolved work as a current defect.
- Write a sanitized user checklist that includes the full Operator workflow and
  the viewer-specific manual evidence below. Leave the web and queue processes
  running for the user after handoff.
- Correct only a factual local-launch, local-reset, or local-guide mistake
  confined to this task. Any application behavior, viewer, authorization,
  MPIPS, queue, storage, or DICOM defect reported during testing returns to the
  Planner/Reviewer; do not silently fix it here.

### Out of scope

- Application-code changes, migrations, Viewer/Cornerstone changes, new tests,
  test-runner repair, new worker/queue types, retry-policy changes, dependency
  changes, or implementation of manual-test feedback.
- Executor-driven NPZ submission; any AWS/S3 or MPIPS request/probe; raw NPZ
  or DICOM inspection/parsing/copying; storage listing; credential disclosure;
  or browser-automation workaround.
- Production/server deployment, release, CI/CD, Docker/Compose, reverse proxy,
  37-member import, real-member data, bucket/IAM/region change, or committed
  production-default change.

### Preserved behavior

- Local and production use the same durable-source, accepted-capture,
  worker-only-MPIPS, per-result DICOM publication flow. Only the configured
  private disk differs: local filesystem here and private S3 in production.
- Every private object remains plain-byte, opaque-keyed, grant-authorised,
  integrity-checked, and non-public. Browsers never receive raw NPZ.
- Any authorised current-shift Operator at the active site may view a returned
  DICOM and download it as the existing normal authenticated `.dcm` attachment.
  The viewer remains read-only: automatic VOI, zoom, and pan only.
- The internal UUID stays the protected route/authorization identity; the short
  `DCM-…` display reference remains the primary user-facing study label.
- During NPZ XHR, inputs and native unload protection remain active. At safe
  `queued`/`processing`, work is durable, the page may be closed, and retry
  uploads only a genuinely unsuccessful component.

## Dependencies and assumptions

### Dependencies

- The repository execution baseline and the reviewed viewer candidate above.
- An explicitly disposable local MySQL database; local private filesystem;
  local MPIPS service configured for the user's own later manual interaction;
  and the approved non-clinical Grabber NPZ pair already available locally.
- The user, not the Executor, performs the live upload and any MPIPS-triggering
  browser action.

### Approved assumptions

- `storage/app/private/objects` is the existing repository-local private object
  root used by the configured local disk and is not web-served.
- `PHP_CLI_SERVER_WORKERS=4` plus one `image-gateway` worker is the approved
  local topology for this run.
- The prior browser runner repeatedly hangs. Its absence does not authorize
  configuration repair; the Node/PHP evidence already exists and the user-led
  browser check below supplies product-facing evidence.

### Remaining approval requirements

- The user-led manual result requires Planner/Reviewer evaluation before the
  portrait-viewer candidate can become an accepted baseline.
- Local readiness is not deployment, production, or release authorization.

## Required capabilities

- Repository write limited to the three named guides/evidence files.
- Local shell, Laravel/Node commands, loopback checks, and narrowly identified
  local process start/stop.
- No external network, AWS/S3, MPIPS, production, credential, or clinical-file
  inspection capability is authorised.

## Execution constraints

- Do not open `.env`, `credential.txt`, object files, NPZ, DICOM, or logs for
  content. Configuration checks return PASS/FAIL only.
- Do not reset until local-mode, MySQL loopback-host, local-private-disk, real
  private-parent, non-symlink-path, and disposable-target assertions pass.
- Run PHPUnit sequentially. Do not run the known-hanging browser suite as a
  readiness gate and do not adjust its configuration.
- Never record a real path/filename, bytes, metadata, identifier, secret,
  credential, database name, object key, browser session, or external response
  in documentation or evidence.

## Acceptance criteria

- [ ] A confirmed disposable local runtime uses the local private filesystem;
  no Executor AWS/S3 or MPIPS call occurs, and committed production defaults
  are unchanged.
- [ ] Only the exact disposable database and local private-object subtree are
  reset, then migrations and the existing synthetic seed complete.
- [ ] Exactly four native loopback HTTP workers and one `image-gateway` queue
  worker run, with no new process type, queue, or dependency.
- [ ] Loopback Operator login, public LCD, and initial Operator route are
  reachable. The local credential file has mode `0600` without its contents
  being read or disclosed.
- [ ] The guides and evidence give the user an accurate Indonesian current-flow
  checklist and do not repeat resolved viewer/reference feedback as an open
  defect.
- [ ] The handoff checklist explicitly requires: primary Operator flow through
  capture; progress and durable page-close/reopen behavior; same-site/current-
  shift second-Operator discovery/download; short `DCM-…` study reference;
  current-tab viewer; **Buka di monitor** named popup and portrait resize;
  automatic VOI plus zoom/pan only; safe Indonesian error state if encountered;
  and normal DICOM attachment download.
- [ ] Evidence is sanitized and makes no production/release, real-clinical,
  secret, private-object, NPZ/DICOM-content, or external-probe claim.

## Verification requirements

### Required local preparation commands

Run these commands in order. They print no configuration value, credential,
object name, database name, or binary content. Do not replace the exact object
reset target with a broader path or a glob.

```bash
TARGET="."
TARGET="$TARGET" php artisan optimize:clear --quiet
TARGET="$TARGET" php artisan tinker --execute='$connection = config("database.default"); $host = config("database.connections.".$connection.".host"); if (! app()->environment("local") || $connection !== "mysql" || ! in_array($host, ["127.0.0.1", "localhost"], true) || config("mhcs.private_object_disk") !== "local" || config("queue.default") !== "database") { throw new RuntimeException("Local runtime assertion failed."); } echo "Local runtime assertion: PASS".PHP_EOL;'

LOCAL_PRIVATE_ROOT="$(pwd)/storage/app/private"
LOCAL_OBJECT_ROOT="$LOCAL_PRIVATE_ROOT/objects"
test -d "$LOCAL_PRIVATE_ROOT" && test ! -L "$LOCAL_PRIVATE_ROOT"
test ! -e "$LOCAL_OBJECT_ROOT" || test ! -L "$LOCAL_OBJECT_ROOT"
rm -rf -- "$LOCAL_OBJECT_ROOT"
install -d --mode=0700 "$LOCAL_OBJECT_ROOT"

TARGET="$TARGET" php artisan migrate:fresh --force --quiet
TARGET="$TARGET" php artisan db:seed --quiet --class=Database\\Seeders\\MvpCoreClinicSeeder
```

After confirming the port/process boundary, start and leave running these two
existing local processes:

```bash
PHP_CLI_SERVER_WORKERS=4 TARGET="." php artisan serve --no-reload --host=127.0.0.1 --port=8013
TARGET="." php artisan queue:work database --queue=image-gateway --timeout=390
```

### Required checks

- Run sequentially:

  ```bash
  TARGET="." node --test tests/JavaScript/operator-dicom-viewer.test.mjs
  TARGET="." vendor/bin/phpunit \
    tests/Feature/Operator/MvpCoreClinicSeederTest.php \
    tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php \
    tests/Feature/Operator/OperatorPortraitDicomViewerTest.php \
    tests/Feature/Localization/MvpApplicationIndonesianUiLocalizationTest.php
  TARGET="." vendor/bin/phpunit
  TARGET="." npm run build
  TARGET="." vendor/bin/pint --test
  TARGET="." git diff --check
  ```

- Verify process command lines/counts and the named loopback responses without
  logs, storage, or private-file inspection. Do not probe MPIPS; the user's
  manual browser action is the only approved live conversion trigger.

### Required user manual checklist

The Executor records this checklist in the walkthrough/evidence and leaves the
runtime ready. The user performs it and returns only sanitized PASS/FAIL,
symptoms, and non-sensitive screenshots:

1. Sign in as the primary seeded Operator, choose the assigned site, and run
   the synthetic Member from attendance through identity verification, paper
   consent/questionnaire, ticket printing, basic examination, and X-ray
   readiness.
2. Submit the approved local non-clinical radiograph/gain NPZ pair once. During
   upload, observe progress and disabled inputs. Once safe status is queued or
   processing, close and reopen the capture page; confirm durable polling and
   retry only a reported missing component.
3. When a result is ready, confirm the DICOM results list shows the short
   `DCM-…` reference. Open it in the current tab; verify a centred portrait-
   suitable read-only image, automatic VOI, zoom/pan, no editing controls, and
   normal **Unduh DICOM** attachment behavior.
4. Use **Buka di monitor**. Confirm one named compact popup opens at the same
   protected study, remains usable after portrait resize, preserves the DICOM
   reference/state/download, and hides broad workstation navigation. If a
   popup is blocked, confirm the Indonesian fallback and continue in the
   current tab.
5. If the viewer cannot load, confirm it leaves “Memuat DICOM…” for the safe
   Indonesian error state and still offers download and return actions. Do not
   manufacture a failure by modifying application code, data, or configuration.
6. Sign in as the second same-site/current-shift Operator. Confirm the returned
   study remains discoverable, viewable, and normally downloadable without
   queue/capture ownership changes. Confirm an unauthorised boundary is denied
   through the existing safe behavior.

### Required evidence

The Executor must report the immutable execution revision; redacted PASS/FAIL
for configuration/reset/migration/seed/build/tests/processes/loopback/credential
mode; exact guides/evidence files changed; known gaps; and the user checklist.
The user later reports manual outcomes separately. Neither report may expose
secrets, private file data, paths, identifiers, object keys, DICOM content, or
external responses.

## Stop conditions

- Stop before destructive work if local-mode, MySQL loopback host, database
  queue, local private disk, safe private path, or disposable boundary cannot
  be confirmed.
- Stop if an occupied port or stale worker cannot be positively identified as
  the exact prior local process described above, or if startup needs a new
  dependency, worker, queue, service, or application change.
- Stop if execution would inspect/disclose a secret, credential, object, NPZ,
  DICOM, MPIPS/AWS endpoint result, or real clinical data; or if it would make
  an AWS/S3/MPIPS call.
- Stop and return to Planner/Reviewer for every functional manual-test finding;
  do not implement feedback under this readiness task.

## Side-effect authorization

### Explicitly authorised side effects

- After every stated assertion passes, reset only the confirmed disposable
  local database and `storage/app/private/objects`; migrate and seed existing
  synthetic data.
- Stop only an exactly identified prior matching local web/queue process; build
  assets and start the two stated loopback processes for user testing.
- Update only `README.md`, `docs/mvp/local-core-walkthrough.md`, and
  `docs/mvp/evidence/mvp-local-deployment-readiness.md` as required for this
  local handoff.

Not authorised: Git commit, push, pull request, application-code or task-file
changes beyond this published contract, production/server mutation, release,
AWS/S3/MPIPS call, credential disclosure, real-member import, dependency
installation, or unbounded feedback fixes.

## Expected terminal outcome

`USER TESTING READY` — return one immutable execution revision with redacted
readiness evidence and leave the local runtime running for the user. The
Planner/Reviewer evaluates the user’s manual result, including whether the
portrait-viewer candidate can be accepted.
