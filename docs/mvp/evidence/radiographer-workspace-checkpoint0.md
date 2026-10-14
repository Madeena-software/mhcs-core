# Radiographer workspace — checkpoint 0 execution evidence

## Current resume result — GD authorized, checkpoint 0 verified

The Product Owner's “MHCS — Unblock Build and Finish Radiographer Workspace”
launcher authorizes GD and demonstrably necessary locked PHP extension repairs
in both existing PHP stages, unchanged dependency versions/lockfiles, isolated
verification and ordinary commit/push on the existing branch. This authorization
resolves the historical GD stop documented below. The governing task is unchanged.

Implemented GD with Debian `libpng-dev`, `libjpeg62-turbo-dev`,
`libfreetype6-dev`, `docker-php-ext-configure gd --with-freetype --with-jpeg`
and `docker-php-ext-install ... gd` in both stages. No other missing extension
repair was necessary. Composer platform validation remains enabled.

The complete command `docker build --target app -t
mhcs-radiographer-checkpoint0:local .` **passed**. Final image ID:
`sha256:c4729ec2d773448d102008f3f10e81f95a3144190f75462222557e5a092628a5`.
The image retains its www-data user, PHP-FPM entrypoint and health check.

The first target-image probe correctly failed closed: Node 24 rejects the
workstation Node 22's V8 `--no-expose-wasm` option. The adapter now uses
Node 24's supported `--jitless` invocation while retaining all memory, CPU,
time, output, permission and seccomp limits. The production-target image was
rebuilt and verification repeated. Parser tests require the packaged Node 24;
the workstation Node 22 produces a startup warning with this invocation and
is not claimed compatible. No warning was waived to make verification pass.

Added PHP `SensitiveParameter` attributes to bytes and owner-identity inputs:
the first synthetic failure exposed argument values in PHP's default stack
trace formatting. A test explicitly disables PHP's global argument-hiding
setting and verifies that both inputs remain redacted. No real data was used.

Exact isolated probe preparation/execution (the helper contains only synthetic
Part-10 generators and checks, and no application environment bootstrap):

```sh
docker create --name mhcs-rad0-probe-gd-fixed-20261014 --network none --entrypoint php mhcs-radiographer-checkpoint0:local /tmp/check-dicom-runtime.php
docker cp tests/Deployment/check-dicom-runtime.php mhcs-rad0-probe-gd-fixed-20261014:/tmp/check-dicom-runtime.php
docker cp tests/Support/SyntheticDicom.php mhcs-rad0-probe-gd-fixed-20261014:/tmp/SyntheticDicom.php
docker start -a mhcs-rad0-probe-gd-fixed-20261014
```

**Passed, container exit 0, user www-data (UID 33), network none:**

- all **19** extension requirements from locked production packages;
- PHP **8.4.26**, Node **24.21.0**, locked parser package **1.8.21**;
- actual explicit/implicit synthetic Part-10 parsing and immutable hash;
- conflicting file-meta, unsupported syntax, truncation and duplicate rejection;
- kernel socket denial, CPU termination, allocation and filesystem-read denial.

A second disposable container used the same final application image with the
already installed development vendor tree and test files copied in solely to
run PHPUnit; this is **not** a change to the production image's dependencies.
It ran as www-data with network none. Its command was:

```sh
php /var/www/html/vendor/bin/phpunit tests/ImageGateway/BoundedDicomVerifierTest.php tests/Deployment/ProductionDockerBuildResilienceTest.php tests/Architecture tests/Security
```

**Passed: 63 tests, 2,237 assertions, exit 0.** An initial test-file path error
was corrected by copying `tests/.` into the existing directory; the complete
command was rerun successfully. `vendor/bin/pint --dirty` and `git diff --check`
passed. Composer/npm declarations, both lockfiles and the immutable governing
task match the prior revision byte-for-byte. No CI or production runner was used.

### Remaining bounded integrity decision

**Planning Required: owner-authoritative clinical-finalization source.**
Checkpoint 0 is verified; the complete workspace is not implemented or accepted.
The next delivery work requires a safe owner contract for the task's clinical
guard. Governing task lines 153/157/183 require serialized applicable finalization
checks, controlled reopen only when no clinically finalized report exists, and
explicit withholding/stop when Doctor status is unknown. AI state cannot replace
this guard, and the task forbids fabricating a Doctor context/workflow.

Read-only inspection of `app/Modules/Doctor/` finds only its provider and boundary
READMEs. `DoctorServiceProvider` registers the module name; it binds no status
query. `Application/Contracts/README.md` reserves contracts for later work, and
the Domain/Infrastructure READMEs state no business persistence is implemented.
The inspected migrations contain AI reports but no Doctor finalization records.
Graph coverage was checked for these bounded paths/scopes, and material claims
were verified from source. This does not claim that external clinical reports
do not exist. No production systems or patient records were inspected.

Required input: identify the trusted owner-authoritative status source/contract,
or approve a minimal Doctor-owned guard registry populated only through trusted
verified input, defaulting to unknown and serialized with reopen/finalization.
The registry option is a new bounded architecture decision, not permission to
implement clinical reporting or fabricate professional accounts. It was not
introduced without that decision. A clarification was requested while the
independent build/parser verification continued; no reply was available in this
execution attempt. No W/B decision or task publication was reopened.

Checkpoint 1–4 workspace application features, RAD-URL acceptance, browser,
MySQL concurrency and full Operator/Grabber/MPIPS/AI regressions remain
unimplemented/unverified. No production deployment, data change, real-patient
access, production credentials/workflows, PR, main merge or G10 action occurred.

## Historical first attempt — before GD authorization

Date: 2026-10-14. Terminal state: **Planning Required**. Checkpoint 0 has
**not passed**; this is a bounded execution attempt, not vertical-slice acceptance.

Governing contract: `.agents/tasks/admin-issued-radiographer-workspace.md` at
`25d396e576ee513bee1a7e2aa2db57a65afdd666`. Implementation baseline:
`f8f0bb9e2e0cd12d1b797cf6a6689f26336274ba`. Branch:
`feat/radiographer-workspace`. Remote main still matched the baseline in this run.
The governing contract file is unchanged.

## Authorization and sequencing

The Product Owner's supplied “MHCS — Resume Execution After Checkpoint 0 Stop”
launcher accepts the original runtime stop and authorizes a bounded B3 amendment:
official compatible Node 24 in PHP-FPM, the existing locked parser, minimum
Gateway adapter/synthetic tests, isolated builds, and ordinary commit/push only
to this branch. It preserves the immutable task revision and requires successful
target application-image execution as the non-root user before proceeding.
The later “re run again” instruction reports Docker Desktop started and directs
resumption under that authorization. No wider dependency, release, real-data,
production-runner, PR or main-branch authority is inferred.

## Observed implementation and local evidence

- `docker version` and `docker.exe version` both returned client/server 29.7.2,
  Linux/amd64. The earlier engine-unavailable blocker is resolved.
- `docker run --rm --network none --entrypoint sh php:8.4-fpm -c
  'cat /etc/os-release; command -v prlimit; command -v timeout; command -v setpriv'`
  succeeded: Debian 13/trixie, with the existing platform limit/privilege tools.
- Dockerfile now explicitly selects PHP 8.4 FPM trixie and official
  `node:24.21.0-trixie-slim`. Only the Node binary and the parser's manifest,
  minified runtime and license are copied; the frontend dependency tree is not
  shipped. Existing entrypoint, www-data user and health check remain intact.
  Final binary/library compatibility is **not verified**, because app build failed.
- Gateway `BoundedDicomVerifier` uses Symfony Process array arguments and binary
  stdin. It checks source length/hash first, removes inherited environment,
  enforces 64 MiB input, 16 KiB output, five-second wall/CPU limits, 1 GiB virtual
  address-space limit, 64 MiB Node heap, no core files and zero file-write limit.
  A static seccomp filter denies socket creation/outgoing syscalls on x86_64 and
  aarch64; other architectures fail closed. Node permissions limit filesystem
  reads to the worker and parser and do not grant writes, children or addons.
  The only adapter temporary file holds static filter instructions, never patient
  bytes. No parser input/output or underlying exception is included in errors.
- Worker reuses the installed parser's exported element/stream/dataset APIs,
  rather than implementing a DICOM decoder. It rejects duplicate root/meta tags,
  bad lengths/VR, malformed sequences, partial/warning results, unsupported
  charset/syntax, UID and SOP/file-meta conflicts, invalid image geometry or
  multiframe input. It supports single-frame uncompressed explicit/implicit
  little-endian monochrome CR/DX, ASCII/UTF-8, and compares extracted identity and
  supplied expected UID/identity values against owner input. It grants no access,
  routes or disclosure authority; owner integration remains unimplemented.
- Package manifest and lockfile identify `dicom-parser@1.8.21`. Its existing
  minified distribution reports internal `parser.version` **1.8.12**; these are
  recorded separately, not presented as equal or repaired through an upgrade.
  Local minified artifact SHA-256:
  `2b990e92de021a9c0d58f7dca693c95fa76be6398648b68441df9423de284a2b`.
- `vendor/bin/phpunit tests/ImageGateway/BoundedDicomVerifierTest.php
  tests/Deployment/ProductionDockerBuildResilienceTest.php tests/Architecture`
  **passed: 29 tests, 2,095 assertions**. Synthetic explicit/implicit fixtures and
  supplied UID variants exercise actual Part-10 bytes, source immutability,
  owner patient/name/DOB/sex/accession/UID conflicts, file-meta mismatch,
  unsupported encoding/frames, malformed/truncated/oversized data and duplicate
  identity. Kernel socket denial, CPU termination and allocation failure were
  observed locally. An earlier resource-test error was the expected CPU signal
  being thrown by Symfony; the test now handles and asserts the termination.
- `vendor/bin/pint --dirty` passed. This is local evidence on PHP 8.5.4 / Node
  22.22.1, not target PHP 8.4 / Node 24 execution or CI. A synthetic-only
  `tests/Deployment/check-dicom-runtime.php` probe is provided for the later
  disposable target image; **it has not been run**.

## Mandatory target-build failure and bounded decision

Command:

```sh
docker build --target app -t mhcs-radiographer-checkpoint0:local .
```

Failed with exit code 1 at Dockerfile line 17 (Composer process exit code 2):

```text
mpdf/mpdf v8.3.1 requires ext-gd * -> it is missing from your system.
```

The Composer build-stage source is byte-identical to the immutable baseline;
`composer.lock` already requires GD for locked mPDF v8.3.1. Both PHP stages omit
GD from their extension lists. This pre-existing application-image packaging
defect prevents the required final-image test. No platform requirement was
bypassed and no dependency was updated. Full build logs are ephemeral at
`/tmp/mhcs-radiographer-checkpoint0-build.log` on the execution workstation.

**Recommended minimal authorization:** permit installing/configuring the existing
required PHP GD extension and its necessary system build libraries in both the
Composer and PHP-FPM stages, without changing Composer/npm lockfiles or package
versions. Then repeat the app build and execute synthetic parser/limit probes as
www-data in the final isolated image before considering checkpoint 0 passed.
An alternative is an independently repaired, compatible repository Dockerfile
baseline, reconciled with this task before resumption. Neither option implies
deployment permission. The current Node/parser-only amendment does not explicitly
authorize repairing this separate PHP runtime dependency.

## Security research and remaining evidence

[The upstream parser advisory page](https://github.com/cornerstonejs/dicomParser/security/advisories)
reported no published advisories. An anonymous exact npm package/version query
to OSV returned no advisories for 1.8.21. Absence of known advisories is not a
security certification. The distribution's stale embedded version remains
explicit evidence for review. [Node's July 2026 security release](https://nodejs.org/en/blog/vulnerability/july-2026-security-releases)
addresses permission-model bypasses and other flaws in 24.18.1; selected 24.21.0
is later. [Node permission documentation](https://raw.githubusercontent.com/nodejs/node/v24.21.0/doc/api/permissions.md)
does not offer a malicious-code sandbox or network control; kernel denial is
therefore independently enforced and locally tested. Final-image advisory,
library compatibility, limit and non-root execution evidence remain unverified.

No RAD-URL criterion is claimed complete. This work supplies partial development
evidence for the parser prerequisite to RAD-URL-004b and synthetic-only
RAD-URL-008b. Checkpoints 1–4, complete converted-path reconciliation, eligible
access, QC/handoff/reopen, browser, MySQL and Operator/Grabber/MPIPS/AI regression
evidence are **not performed**. Architecture checks passed locally; no application
or clinical acceptance, independent A9 review or G10 authorization is claimed.

No production deployment/migration/backfill, production runner or workflows,
real-patient access, production credentials, upstream MPIPS mutation, external
messages, PR creation, main mutation, merge/rebase or force-push occurred.
