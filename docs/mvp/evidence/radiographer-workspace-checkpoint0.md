# Radiographer workspace — checkpoint 0 execution stop

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
