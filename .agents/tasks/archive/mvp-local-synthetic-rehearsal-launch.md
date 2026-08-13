---
title: Local Synthetic Clinic Rehearsal Launch Guide
document_id: MHCS-TASK-LOCAL-SYNTHETIC-LAUNCH-001
version: 1.2
status: validated-published
language: en-US
last_updated: 2026-08-12
scope:
  - bounded remediation of the local synthetic rehearsal
  - five synthetic Members and two-Operator seed data
  - native local Laravel launch and DICOM rehearsal evidence
authority_note: This remediation task becomes executable only when its exact content is committed and published as validated.
---

# Executable Task

## Task identity

**Task title:**
`Local Synthetic Clinic Rehearsal Launch Guide`

**Task path:**
`.agents/tasks/mvp-local-synthetic-rehearsal-launch.md`

**Task contract state:**
`Validated/Published when this exact content is committed`

**Delivery objective / Work Package / MVP:**
`12 August MVP delivery target / local synthetic clinic handoff / WP-14 and WP-23`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

This is bounded remediation of the execution governed by
`.agents/tasks/mvp-local-synthetic-rehearsal-launch.md @
dcf315b81f0c925f753cff0d4d8c41939e9a0c10`.

Review of `9db7bbb53ca9fc5009326fb9f3f906715f18f6ec` and
`7316bc363731b9eab8d1427fd786ca76b20c9908` found material divergence: the
published two-Operator task was changed during execution to three Operators;
credentials were persisted to an ignored plaintext file instead of being
shown only in the interactive seed terminal; and a custom `serve` command was
introduced despite the task's explicit prohibition. Those changes are not
accepted merely because the focused PHP tests pass.

The new MPIPS integration contract at `f198f796087cb2cc3ac089123a058739356c72da`
is a separate, preserved authority input. This remediation does not implement,
call, configure, or test MPIPS or AWS.

## Baseline and task revision

**Original implementation baseline:**
`bd8f3c8cfb5d6bcadec3776e921e2a31ca730101`

**Remediation execution base:**
`f198f796087cb2cc3ac089123a058739356c72da` — contains the reviewed local
execution attempt and the separately preserved MPIPS documentation.

**Prior governing task revision:**
`.agents/tasks/mvp-local-synthetic-rehearsal-launch.md @
dcf315b81f0c925f753cff0d4d8c41939e9a0c10`

**Task revision:**
`Resolve from the commit that publishes this exact remediation content before execution.`

## Objective

**Objective:**
Restore a verified, copyable, local-only rehearsal for five synthetic Members,
one primary Operator, and one additional current-shift Operator, using only
the standard native Laravel commands and terminal-only one-time synthetic
credentials; then provide the required synthetic DICOM rehearsal evidence.

## Authoritative inputs

### Governing authority

- `docs/mvp/decision-log.md` — MVP-DEC-024, MVP-DEC-034, MVP-DEC-035, and MVP-DEC-036.
- `docs/mvp/beta-scope.md` — synthetic-only boundary and separate release gate.
- `docs/mvp/beta-gap-register.md` — MVP-GAP-020, MVP-GAP-022, and MVP-GAP-023 remain open.
- `.agents/context/modules/operator/project.md` and `.agents/context/modules/image-gateway/project.md`.
- Prior governing task revision at `dcf315b81f0c925f753cff0d4d8c41939e9a0c10`.
- Review evidence: `php artisan list --raw` at `f198f796087cb2cc3ac089123a058739356c72da` exposed a custom default `0.0.0.0:8013` serve command; focused PHP checks passed with 75 tests and 746 assertions but no browser or fresh-database evidence was supplied.

### Requirement traceability

- `MVP-DEC-034` → local/testing synthetic capture and DICOM bridge only.
- `MVP-DEC-035` and `MVP-DEC-036` → normal authenticated raw-DICOM download and authorised second-Operator access.
- `OPR-031..OPR-046`, `OPR-057..OPR-060`, and `IMG-060` → complete local Operator flow and read-only DICOM access.

## Scope

### In scope

- Restore the task, seeders, tests, README, and `docs/mvp/local-core-walkthrough.md` to the published two-Operator contract: five synthetic Members, one primary Operator, one same-site/current-shift second Operator, and no third-Operator journey.
- Restore terminal-only one-time synthetic credential output. Delete `database/seeders/MvpCredentialFile.php`, remove its use from seeders, remove the ignored `credential.txt` rule, and ensure the guide never tells a user to create, retain, or read a credential file.
- Delete `app/Console/Commands/Serve.php` and `app/Console/Kernel.php`. Use the framework's existing `php artisan serve --host=127.0.0.1 --port=8013` command; do not add a launcher, command override, PHP server default, or upload-limit mechanism.
- Revert the unapproved queue/controller/view/localization behavior and synthetic-capture filename-policy changes introduced by `9db7bbb…` and `7316bc3…` unless required solely to restore the exact prior two-Operator local rehearsal. Keep no new queue policy, claim result, third-Operator access path, or relaxed synthetic fixture acceptance from those commits.
- Restore `.env.example` local-upload changes that depended on the prohibited custom command. Do not inspect, print, modify, or disclose values in `.env`.
- Preserve `docs/mpips/mhcs-dicom-api.md` and `docs/mpips/examples/mhcs-dicom-manifest.example.json` exactly as they are at `f198f796…`; they are outside this remediation.
- Run the documented native local setup/rehearsal evidence with only synthetic data and correct only documentation/test gaps needed for reproducibility.

### Out of scope

- MPIPS HTTP integration, retries, asynchronous conversion jobs, DICOM validation changes, AWS/S3 configuration or use, credentials, queue workers, deployment, release, real data, and the 37-member server import.
- Any third Operator, credential-file workflow, custom Artisan command, new package, configuration abstraction, migration, new storage backend, server, Docker/Compose, reverse proxy, external-system mutation, or unrelated Operator behavior.

### Preserved behavior

- `MvpCoreClinicSeeder` remains local/testing-only, repeatable, and produces exactly five synthetic Members and two eligible Operators for this rehearsal.
- The local DICOM bridge remains synthetic-only; it is not represented as MPIPS or a real conversion result.
- The standard authenticated second Operator can discover, view read-only, and download the same returned synthetic DICOM; neither Operator downloads raw NPZ.
- Private objects, raw NPZ protection, Member/Doctor access, public LCD privacy, automatic site-and-shift ticket allocation, and active-site/current-shift authorization remain unchanged.
- No task text, scope, acceptance criterion, or governing authority is changed by the Executor during execution.

## Dependencies and assumptions

### Dependencies

- A workstation with the required PHP, Composer, Node/npm, supported empty local database, local-only security keys, existing fixtures, and Chromium runtime.
- The `f198f796…` MPIPS documents remain unmodified while this remediation is executed.

### Approved assumptions

- Framework-native `php artisan serve --host=127.0.0.1 --port=8013` supports this synthetic rehearsal without a custom command or changed PHP upload limits.
- The existing interactive seed-terminal output is sufficient for one-time synthetic login guidance; no credential file is required.

### Remaining approval requirements

- MPIPS integration and the user-directed use of AWS for local and production storage require a separate validated task after this remediation is accepted.
- Server deployment, real-member import, credential delivery, production storage, privacy/retention operations, and release remain separately approved.

## Required capabilities

- Repository read and write.
- Local PHP/Laravel, Composer, Node/npm, Vite, Chromium, and disposable-database execution.

## Execution constraints

- Begin by comparing all remediation-target files to `dcf315b81f0c925f753cff0d4d8c41939e9a0c10`; do not restore or modify `docs/mpips/`.
- Keep all credential values out of files, repository output, test assertions, evidence, chat, and logs. Report only that terminal-only credentials were observed without reproducing them.
- Treat `migrate:fresh` as destructive: run it only against an explicitly verified disposable local database. Never inspect or mutate a server, production, shared, or unknown database.
- Do not add or retain a custom server command, file-based credential output, third Operator, queue behavior change, or relaxed fixture identity rule.
- Do not access or mutate AWS/S3, MPIPS, or any external system. Existing `.env` values are out of bounds.
- Preserve unrelated changes and leave the working tree clean except for bounded remediation output.

## Acceptance criteria

- [ ] The reviewed implementation no longer contains `app/Console/Commands/Serve.php`, `app/Console/Kernel.php`, or `database/seeders/MvpCredentialFile.php`; `.gitignore`, seeders, tests, and guides contain no `credential.txt` workflow.
- [ ] The synthetic seeder remains repeatable and creates exactly five Members and two current-shift Operators. It provides one-time credentials only in its interactive terminal output, which is not copied into repository artifacts or evidence.
- [ ] README and the local walkthrough use the framework-native loopback serve command, identify the required local-only keys without values, warn about `migrate:fresh`, guide exactly the primary and second Operator journeys, and stop before MPIPS, AWS, deployment, real data, and release.
- [ ] The synthetic capture accepts only the repository-owned radiograph/gain fixture pair under the prior local contract; no relaxed filename/fixture policy or unrelated queue/controller/view behavior remains.
- [ ] A fresh disposable database migration/seed, focused PHP suite, second-Operator DICOM browser journey, Vite build, and whitespace check pass with only synthetic data.
- [ ] `docs/mpips/mhcs-dicom-api.md` and its example manifest are byte-for-byte unchanged from `f198f796087cb2cc3ac089123a058739356c72da`.

## Verification requirements

### Required checks

- Run `php artisan list --raw` and verify `serve` is the framework-native command, without a custom default description or command override.
- Run `php artisan test` for `MvpCoreClinicSeederTest`, `Mvp04dVerifiedCheckInTicketIssueTest`, the focused Operator arrival/verification/consent/basic-examination/X-ray/capture tests, the Indonesian localization test, and the synthetic Image Gateway study-access tests.
- Run the documented migration/seed path against a fresh disposable database, retaining any synthetic credentials only in the invoking terminal. Confirm five Members and two Operators without recording their values.
- Run the existing Chromium DICOM rehearsal including the second-Operator results worklist, viewer, and ordinary `.dcm` download.
- Run `npm run build`, `git diff --check`, and a byte comparison of both `docs/mpips/` files to `f198f796087cb2cc3ac089123a058739356c72da`.

### Required evidence

The Executor must report the implementation revision; every command actually
run; test totals; browser result; disposable-database confirmation; changed and
deleted files; byte-comparison result; build warnings separately from failures;
known gaps; and explicit confirmation that no credential value, `.env` value,
AWS/S3, MPIPS, server, deployment, real data, external mutation, or secret was
disclosed or used.

## Stop conditions

- Stop if restoring the original two-Operator, terminal-only, native-server contract requires a new package, migration, custom command, third Operator, credential file, AWS/S3, MPIPS, or other external dependency.
- Stop if the browser journey or documented setup can succeed only by retaining an out-of-scope behavior introduced in `9db7bbb…` or `7316bc3…`.
- Stop if the MPIPS documents cannot remain unchanged, a value from `.env` is needed, or any task authority must be changed to proceed.
- Stop if another pending implementation overlaps the remediation target before review.

## Side-effect authorization

### Explicitly authorized side effects

- Repository changes strictly limited to the bounded remediation files and the original local rehearsal documentation/tests.
- Disposable local database, local build, and local browser operations using synthetic data only.

Not authorized: Git commit, push, pull request, deployment, release, server or
external mutation, AWS/S3 or MPIPS access, real data, real credentials,
dependency change, migration, or secret access, copying, or disclosure.

## Expected terminal outcome

`IMPLEMENTATION AND VERIFICATION RESULT REQUIRED` — return an immutable
implementation revision and the full synthetic-only evidence for Reviewer R8
closure. The Planner/Reviewer then decides whether to accept the local baseline
and separately plans MPIPS/AWS integration.
