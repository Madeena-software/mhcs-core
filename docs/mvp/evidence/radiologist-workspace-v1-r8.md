# Radiologist Workspace v1 — consolidated R8 remediation

Status: **REVIEW REQUIRED**. Independent V7/A9 remains required. No acceptance,
release, production, real-patient operation, merge or deployment is asserted.

Repository: `Madeena-software/mhcs-core`; branch `feat/radiologist-workspace-v1`.
Reviewed predecessor: `3f81ac44b9f296ca2b2c893400ebf314bd535b88`.
Governing task remains `.agents/tasks/admin-assigned-radiologist-workspace-v1.md`
at `c8886a8ee0c7f11edae5f888fe42e79b86ef5607`; accepted implementation baseline
remains `0f27d2b58c804183b907e236aa9735f5fac876ac`.
The Product Owner's consolidated R8 handoff authorizes these bounded fixes,
synthetic verification and ordinary commit/push to the same feature branch.
The exact resulting remote revision is recorded in the terminal report and external
checkpoint; no self-containing commit identity is invented in this document.

## Findings and behavior

| Finding | Remediation and observed evidence |
|---|---|
| R8-01 | Removed the false assertion that an issued internal workspace proves complete reporting coverage. The production adapter explicitly reports `complete_sources=false`; the guard records unknown and denies reopen. Its internal case/handoff/report consistency checks remain. Tests cover internally not-finalized, finalized, unknown, inconsistent and superseded state, plus guard rejection of stale, uncoordinated, incomplete or mismatched evidence. Positive not-finalized guard tests use explicitly synthetic trusted-source fixtures only. |
| R8-02 | Credential revocation requires a named current eligibility revision. Its stored user/facility/service/modality and expiry are preserved in an appended revision with new verifier/time/evidence and durable audit. No original parsing, active target login or unexpired eligibility is required. The actor still needs the existing credential capability, active managed facility and fresh password. Unknown fields, forged scope, superseded revision and missing capability fail closed. The management page also remains available without parsing for a suspended target before workspace issuance. Prepared HTML/image/report access is reauthorized after revocation. |
| R8-03 | Management locks participating accounts in ID order before Operator serialization, rechecks the discovered assignee, then takes the guard/workspace/eligibility locks. Login takes eligibility only after the Operator handoff check. Candidate listing reads are advisory; actual issue/reassign still verifies original bytes and locks/rechecks exact eligibility. A deterministic MySQL probe additionally exposed Gateway-before-Operator inversion in Radiographer management. Owner-authorized unlocked locator discovery now precedes Operator locking, followed by a fresh locked Gateway selection comparison. Doctor's management locator follows that ordering too. No source verification, owner scope or durable audit check was removed. |
| R8-04 | Existing supported disposable image runs the unchanged bounded verifier with seccomp and default Docker sandboxing. Both real-Gateway Doctor feature tests, direct/converted browser journeys, MySQL migration up/down and all original Doctor races ran successfully. Exact final counts and baseline comparison follow below. |

Production has no authoritative complete historical/external clinical-source inventory
in the supplied repository authority. No migration, workspace issuance, synthetic
fixture or environment flag is promoted to that authority. Consequently **positive
production not-finalized evidence remains unavailable**, and technical reopen stays
denied while coverage is unknown. A future authoritative coordinated source inventory
is needed to enable that positive path; it is not fabricated by this remediation.
Internal report authorship, finalization and authorized read-only access still work.

## Scope and preservation

Changes are bounded to Doctor eligibility/status/login/assignment handling, the
existing owner locator/locking path, Admin credential controls, focused tests and
this evidence. No new table, dependency, permission grant, auth provider, publication,
AI-content access, financial action, repeat execution or amendment was introduced.
The governing task and accepted baseline were not advanced.

The optional lock argument on `RadiographerStudySource::selection` defaults to the
prior locked behavior. Its unlocked use returns only the existing minimal selector,
still authorizes the manager, and must be compared with a fresh locked owner result.
An existing Operator workspace is locked before taking Gateway locks. First issuance
without a workspace still serializes on the Gateway row before checking/creating it.
This preserves existing source/association checks while removing the observed cycle.

## Supported isolated runtime

Reused installed image `mhcs-radiographer-workspace:verified`, image ID
`380f70b20e32`, without rebuilding/installing dependencies. Observed PHP 8.4.26,
Node 24.21.0, util-linux 2.41.5, SQLite 3.46.1 and MySQL 8.4.8.
Disposable containers: `mhcs-r8-runtime`, `mhcs-r8-baseline`, `mhcs-r8-mysql`.
MySQL uses an isolated network alias `mysql`, empty synthetic root credentials,
database `mhcs_rad_synthetic` and disposable memory-backed storage. No existing
operational container, DB, volume, object or patient was reused.

Each application checkout is copied from explicit repository sources without `.env`;
installed vendor/node dependencies are mounted read-only. Current source is under
`/work`; the baseline container contains the immutable accepted source independently.
Baseline `.github` is copied from that verified checkout because git archive excludes
it. Baseline assets are built from its own source using Vite's installed native
config loader, avoiding writes into read-only node_modules. Synthetic storage and
bootstrap cache directories belong to the unprivileged application user.

The host's util-linux 2.39.3 still cannot run `--seccomp-filter`; no host fallback,
parser substitution or security-control relaxation was added. The supported image
has that capability. Kernel network-denial/CPU/memory verifier checks passed.
Docker's default seccomp remains enabled; no privileged or seccomp-unconfined run.
The browser uses a temporary localhost-only byte relay to the disposable PHP server
because Docker Desktop's published loopback port was inaccessible from this WSL
host. The relay does not modify requests, responses, authorization or verifier logic.

Initial attempts with incomplete source packaging/build assets, inactive direct-call
test sessions or root-owned disposable storage are discarded as final evidence.
The session fixture now explicitly starts a synthetic session before invoking
credential-protected services directly. No production authentication check was weakened.
The deterministic pre-fix source-lock probe failed with MySQL NOWAIT error 3572;
the same probe passes after the ordering correction.

## Commands and final results

| Command (inside the named disposable container unless noted) | Observed result |
|---|---|
| Current `php -d memory_limit=1G vendor/bin/phpunit --log-junit /work/storage/current-junit.xml` | 968 tests; 905 passed; 9 failures; 16 errors; 38 skipped; 13,781 assertions; exit 2 |
| Baseline same command with `/work/storage/baseline-junit.xml` | 900 tests; 857 passed; 9 failures; 16 errors; 18 skipped; 13,124 assertions; exit 2 |
| Focused `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Doctor tests/Feature/Doctor tests/Feature/Radiographer tests/Architecture tests/Security tests/ImageGateway/BoundedDicomVerifierTest.php` | 165 passed; 3,232 assertions; exit 0; includes real original parsing and sandbox limits |
| Final Doctor `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Doctor tests/Feature/Doctor` | 48 passed; 583 assertions; exit 0, including both formerly failing real-Gateway feature tests and the last no-parser management-page case |
| `MHCS_RAD_MYSQL_TESTS=1 php -d memory_limit=1G vendor/bin/phpunit tests/Integration/RadiologistConcurrencyTest.php tests/Integration/RadiographerConcurrencyTest.php` | 31 passed (20 Doctor + 11 Radiographer); 375 assertions; zero skips; exit 0; independent forked connections, barriers/NOWAIT probes, atomicity and MySQL migration down/up |
| Host `node --test tests/JavaScript/operator-dicom-viewer.test.mjs tests/JavaScript/radiologist-dicom-modality.test.mjs` | 19 passed; zero failed/skipped; exit 0 |
| Host `npm run build` | Exit 0; DICOM bundle check passed; existing large-chunk warning |
| Installed `vendor/bin/pint --test` on the eight changed non-Blade PHP files | Passed, exit 0 |
| `php -l` on all nine changed PHP/Blade files; `node --check tests/Browser/radiologist-workspace.mjs`; `git diff --check` | All passed |
| Final browser command below | Both rendered original journeys passed, exit 0 |

The **same 25 test identities** account for all baseline and candidate failures/errors;
there are no new or resolved failing/errored identities. The remaining errors include
missing installed Mpdf and WebP support; remaining failures involve existing Python/
PDF browser tooling. These dependencies were not changed. All 20 additional default
skips map exactly to Doctor cases passed in the explicit MySQL run; the baseline's
18 skips remain baseline skips in the default SQLite run. The 12 original Doctor
cases (assign/assign, save/save, finalize/finalize, four finalization conflicts,
three prepared reassign disclosures, two atomic failures) are all accounted for.
Added MySQL cases cover three credential-revoked disclosures, three ordered login
conflicts, reversible migration and ordered finalize/reopen source locking.


All PHP results are local test runs, not CI. Default full-suite skips include opt-in
MySQL cases; the explicit MySQL run below accounts for all 12 original Doctor race
cases and their added coverage. Test status identities, not just totals, are compared.

## Browser evidence

`MHCS_RAD_CONTAINER=mhcs-r8-runtime APP_ENV=testing node tests/Browser/radiologist-workspace.mjs`
executes fixture preparation through real PHP/Gateway in the supported container.
No `SyntheticRadiologistSource` is installed in this harness or the real-Gateway
Doctor feature tests. Converted fixtures are signed synthetic converted originals;
no operational MPIPS call or real clinical image is used.

Observed result:
`{"rendered":2,"paths":["direct","converted"],"finalizations":2,"repeatRequired":1,"revoked":1,"expired":1,"auditFailure":2}`.
The harness checks actual Cornerstone ready state and nonuniform canvas pixels,
explicit decisions, authored draft revisions, incorrect/correct fresh-password
finalization, immutable reports, preserved repeat-pending drafts, revoke/expiry,
audit-failure withholding and keyboard focus. Final screenshots now wait for the
read-only original viewer to finish rendering. These 2×2 synthetic pixels establish
rendering/integrity behavior, not clinical display assurance or diagnostic quality.

Artifacts outside Git:
`/home/faliq/.codex/task-state/mhcs-core/radiologist-r8/` contains final JUnit manifests,
source hashes, logs, baseline comparison and four synthetic screenshots:
`radiologist-direct-original.png`, `radiologist-converted-original.png`,
`radiologist-direct-final.png`, `radiologist-converted-final.png`.
No raw request/session/authentication proof or signed locator is persisted as evidence.

## Review boundary

Remaining baseline failures, the absent authoritative complete production source
inventory, and real credential/clinical/operational obligations remain explicit.
No CI, A9, G10, real professional licensing, certified signature, release or production
readiness is claimed. Independent Reviewer must assess this exact pushed revision.
