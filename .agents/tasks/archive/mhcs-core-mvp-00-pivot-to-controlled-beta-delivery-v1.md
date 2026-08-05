---
name: mhcs-core-mvp-00-pivot-to-controlled-beta-delivery
description: Establish the controlled MHCS beta-delivery model, preserve Work Package traceability, define the Member, Operator, Image Gateway, and Admin Portal MVP boundaries, and create the repository documentation that governs future MVP tasks without changing runtime code.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-00 Pivot to Controlled Beta Delivery

## Objective

Establish `MVP-00 — Pivot to Controlled Beta Delivery` in `$TARGET`.

This is a repository-planning and documentation task only.

The observable outcome is a clear, repository-owned execution model in which:

- existing Work Packages remain the authoritative long-term capability and requirement roadmap;
- MVP tasks become the active implementation sequence for controlled beta delivery;
- unfinished Work Package requirements remain visible and are never silently discarded;
- the initial MVP is explicitly limited to:
  - Member Portal;
  - Operator Portal;
  - Image Gateway; and
  - Admin Portal;
- no Doctor Portal or internal MHCS doctor workflow is included in the MVP;
- teleradiology physicians and reporting services are treated as external participants or systems;
- the first implementation slice is Member login, mandatory first-password replacement, profile completion, dashboard, and logout;
- B2B bulk account import is deferred to a separate later MVP task;
- unavailable or incomplete capabilities are recorded in a maintained beta gap register;
- future agents can determine the active scope, accepted foundations, deferred capabilities, and stop conditions without relying on conversation memory; and
- no application code, migration, route, test, dependency, or runtime configuration is changed by this task.

A `succeeded` outcome means the MVP delivery model and its governing documentation are complete, internally consistent, and ready to be consumed by later MVP implementation tasks.

It does not mean any MVP feature has been implemented, beta deployment is approved, production readiness has been achieved, or deferred Work Package requirements are complete.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
- Ordered model preferences: None.
- Require preferred model: `false`

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Baseline and execution boundary

Treat commit:

`bc300e158a790a7311c64eb7b20e8e81d4e3ec41`

as the planning baseline for this pivot.

The current execution commit may be that commit or a descendant that publishes this task.

Before writing:

1. Resolve `$TARGET` to a canonical absolute path.
2. Confirm the expected `mhcs-core` repository.
3. Record the current branch and commit.
4. Confirm that repository history contains the baseline commit.
5. Record staged, modified, untracked, and relevant ignored paths.
6. Preserve all pre-existing work.
7. Stop as `awaiting-approval` if existing work overlaps the required MVP documentation paths.
8. Do not reset, clean, discard, stash, stage, commit, push, rewrite history, open a pull request, or trigger deployment.

The baseline contains the current WP-04 Member identity foundation.

The following known boundary must be recorded rather than remediated by this task:

- the online-registration source/state path is not approved for MVP exposure;
- no public or online registration route may depend on it during the initial beta;
- the path remains unwired and tracked in the beta gap register.

Do not modify WP-04 application code.

## Context and evidence

Read completely before planning or writing:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- all available module context files relevant to:
  - Member;
  - Operator;
  - Image Gateway;
  - administration or Filament;
- `$TARGET/docs/implementation/mhcs-core-requirements-matrix.md`;
- `$TARGET/docs/implementation/mhcs-core-source-coverage.md`;
- `$TARGET/docs/implementation/mhcs-core-implementation-plan.md`;
- every published Work Package task under `$TARGET/.agents/tasks/`;
- the current root and module documentation relevant to:
  - authentication;
  - Member identity;
  - Operator workflow;
  - queues and attendance;
  - Image Gateway;
  - teleradiology;
  - reports and result visibility;
  - administration;
  - audit;
  - deployment; and
- the complete current repository tree necessary to identify implemented, partially implemented, deferred, and not-started capabilities.

Use repository evidence only.

Do not infer that a Work Package is accepted merely because a task file exists.

Do not infer that a feature is implemented merely because a requirement, plan, route name, class name, migration, or placeholder exists.

When status cannot be established from repository evidence, record it as `unverified` and explain what evidence is missing.

## Delivery-model decision

Document the following relationship as binding for future MVP tasks.

### Work Packages

Work Packages remain the authoritative long-term capability, architecture, security, privacy, interoperability, and requirement roadmap.

They preserve:

- source requirement assignments;
- architectural intent;
- cross-module boundaries;
- non-MVP obligations;
- security and privacy requirements;
- deferred compliance and production decisions; and
- historical implementation evidence.

Existing Work Package task files must not be deleted, renamed, renumbered, rewritten, or marked complete without repository evidence.

### MVP tasks

MVP tasks define the active implementation sequence for controlled beta delivery.

An MVP task may deliberately defer a nonessential Work Package requirement only when all of the following are true:

- the active beta flow does not require it;
- the unavailable path is not exposed;
- the gap is recorded in `docs/mvp/beta-gap-register.md`;
- a temporary control is documented;
- the deferral does not weaken the security boundary of an exposed beta flow;
- the target MVP task or revisit trigger is recorded; and
- the task does not falsely claim the underlying Work Package requirement is complete.

MVP tasks do not replace Work Packages.

They provide a narrower implementation order that delivers visible and testable beta value earlier.

## Initial MVP product boundary

The controlled beta contains four primary application components.

### Member Portal

The Member Portal is the Member-facing application.

The initial Member flow is:

```text
existing adult Member account
→ login
→ mandatory first-password replacement when required
→ complete permitted profile fields
→ Member dashboard
→ logout
```

Later MVP tasks may add:

- available radiology services;
- examination request or booking;
- schedule and queue visibility;
- examination status;
- report or result visibility; and
- limited notifications where explicitly approved.

The Member Portal must not initially expose:

- public self-registration;
- online registration;
- child registration;
- guardian access;
- identity-document verification;
- direct editing of protected identity fields;
- payments;
- reward points; or
- unrelated clinical workflows.

### Operator Portal

The Operator Portal supports radiology operations.

Its MVP responsibilities may include:

- managing Member examination requests;
- scheduling;
- queue management;
- check-in and attendance state;
- examination readiness and operational status;
- associating an examination with an imaging study;
- initiating or monitoring teleradiology handling;
- monitoring Image Gateway failures;
- receiving or uploading returned reports where approved;
- publishing an approved result to the Member; and
- recording operational corrections and audit evidence.

The Operator is the primary internal operational user for the MVP.

There is no separate internal Doctor Portal in the MVP.

### Image Gateway

The Image Gateway is the imaging-system boundary.

Its MVP responsibilities may include:

- receiving or identifying imaging studies;
- correlating a study with the correct MHCS examination or request;
- validating required identifiers and routing metadata;
- recording ingestion and transfer state;
- routing or exporting studies to an external teleradiology destination;
- receiving supported status or report callbacks where available;
- exposing retryable and terminal failures to the Operator Portal and Admin Portal; and
- preserving idempotency, auditability, and study-to-examination traceability.

Do not claim DICOM, PACS, RIS, HL7, FHIR, vendor, or external teleradiology conformance unless repository evidence and a later bounded task prove it.

### Admin Portal

The Admin Portal is part of the MVP and must not be omitted from future planning.

Its MVP responsibilities may include:

- managing organizations;
- managing examination sites;
- managing Member and Operator accounts;
- assigning approved roles, permissions, organizations, and sites;
- managing radiology services and operational configuration;
- activating, suspending, or correcting accounts through approved workflows;
- viewing Member, queue, examination, Image Gateway, and teleradiology status;
- viewing failures requiring administrative intervention;
- viewing audit and operational records; and
- managing the controlled beta configuration.

The Admin Portal must not silently become a bypass around application authorization, audit, account-state, or data-ownership rules.

## Explicitly excluded MVP actor

The MVP does not include:

- a Doctor Portal;
- a doctor dashboard;
- internal MHCS doctor assignment;
- internal doctor work queues;
- doctor report authoring inside MHCS;
- doctor credentialing;
- doctor scheduling; or
- doctor-specific permissions beyond any unavoidable legacy foundation that remains unexposed.

A teleradiology physician is treated as an external service participant.

For MVP planning, returned reports may be handled through either:

1. manual Operator upload or controlled attachment; or
2. supported automated Image Gateway or external-service integration.

Until an automated integration contract is explicitly approved and implemented, manual Operator handling is the beta fallback.

Record this as a decision boundary, not as implemented functionality.

## Initial beta assumptions

Document these initial assumptions:

- the beta is controlled, not publicly open;
- the initial beta user is an adult Member with an existing account and linked Member record;
- initial accounts may be created through controlled development or beta seed data;
- B2B bulk import is not part of the first implementation slice;
- public and online registration remain unavailable;
- child and guardian flows remain unavailable;
- no Doctor Portal exists;
- Operator, Image Gateway, and Admin Portal capabilities are delivered incrementally;
- incomplete capabilities are visible in the gap register;
- a fresh beta database may be used only if consistent with current project and deployment policy;
- the forward-only UUID migration remains an explicit approval boundary;
- production object storage, credential delivery, retention, privacy procedure, and deployment approval remain unresolved unless repository evidence says otherwise; and
- beta status must never be described as production readiness.

## Required documentation

Create exactly these files unless equivalent files already exist and can be safely extended:

```text
docs/mvp/README.md
docs/mvp/beta-scope.md
docs/mvp/beta-gap-register.md
docs/mvp/roadmap.md
docs/mvp/decision-log.md
docs/mvp/work-package-status.md
```

Do not create feature code.

### `docs/mvp/README.md`

Define:

- the purpose of the MVP documentation;
- the relationship between Work Packages and MVP tasks;
- the active MVP components;
- the excluded Doctor Portal;
- the authority order among:
  - source requirements;
  - accepted architecture and Work Package evidence;
  - MVP decisions;
  - individual MVP tasks;
- how gaps and deferrals are recorded;
- how future tasks must consume these documents;
- how conflicting or stale information is handled; and
- that conversation memory is never the project source of truth.

Include an explicit instruction for future MVP tasks to read, at minimum:

```text
docs/mvp/README.md
docs/mvp/beta-scope.md
docs/mvp/beta-gap-register.md
docs/mvp/roadmap.md
docs/mvp/decision-log.md
docs/mvp/work-package-status.md
```

### `docs/mvp/beta-scope.md`

Define the current beta scope.

Include:

- beta objective;
- target users;
- four MVP components;
- initial Member vertical slice;
- Operator responsibilities;
- Image Gateway responsibilities;
- Admin Portal responsibilities;
- external teleradiology boundary;
- supported and unsupported flows;
- beta data assumptions;
- temporary operational controls;
- security boundaries that remain mandatory;
- exit criteria for expanding the beta; and
- explicit non-production status.

The first implementation target must be:

```text
MVP-01
Member login
→ mandatory password replacement
→ profile completion
→ dashboard
→ logout
```

### `docs/mvp/beta-gap-register.md`

Create a structured table with these columns:

| ID | Gap | Affected component or flow | Beta impact | Temporary control | Target MVP task or phase | Status | Revisit trigger | Notes |
|---|---|---|---|---|---|---|---|---|

Seed the register with at least:

- public registration unavailable;
- online registration remains unwired;
- B2B bulk import unavailable;
- initial beta limited to adults;
- guardian and dependent flows deferred;
- identity-verification UI unavailable;
- Doctor Portal excluded;
- internal doctor assignment and report authoring unavailable;
- Operator Portal not yet implemented;
- Admin Portal MVP controls not yet implemented;
- Member service request or booking unavailable;
- queue and attendance workflow unavailable;
- Image Gateway study ingestion or association unavailable;
- external teleradiology routing unavailable;
- automated report return unavailable;
- manual Operator report handling not yet implemented;
- Member result visibility unavailable;
- UUID migration approval pending;
- production object-storage policy unresolved;
- production credential-delivery process unresolved;
- privacy, retention, deletion, and anonymization procedures unresolved;
- production deployment not approved; and
- CI or deployment evidence gaps that are present in the repository.

Use stable IDs such as:

```text
MVP-GAP-001
MVP-GAP-002
```

Do not mark a seeded gap `closed` unless repository evidence proves it.

### `docs/mvp/roadmap.md`

Define the initial roadmap below.

#### MVP-00 — Pivot to Controlled Beta Delivery

Documentation and planning only.

#### MVP-01 — Member Access and Profile

Deliver:

- Member login;
- mandatory first-password replacement;
- profile completion;
- Member dashboard;
- logout;
- focused ownership and authentication tests.

Exclude account import.

#### MVP-02 — Admin Portal Foundation

Deliver the minimum Admin Portal required to:

- view and manage approved account state;
- manage organizations and examination sites;
- manage Member and Operator assignments;
- manage radiology-service configuration needed by later tasks;
- view foundational audit information.

Do not turn the Admin Portal into an unrestricted database editor.

#### MVP-03 — Member Radiology Service Request

Deliver:

- Member-visible service catalogue;
- examination-site selection where applicable;
- examination request or booking;
- Member request status.

#### MVP-04 — Operator Queue and Attendance

Deliver:

- Operator authentication and authorization;
- operational queue;
- scheduling;
- check-in;
- attendance and examination-state transitions;
- operational audit evidence.

#### MVP-05 — Image Gateway Study Intake and Correlation

Deliver:

- study intake or identification boundary;
- examination-to-study association;
- duplicate and mismatch handling;
- Image Gateway status visibility;
- Operator and Admin failure visibility.

#### MVP-06 — Operator Teleradiology Workflow

Deliver:

- study routing or export status;
- external teleradiology tracking;
- retry and failure handling;
- controlled manual report upload as the beta fallback;
- automated report return only when a supported contract exists;
- Operator review and publication controls.

#### MVP-07 — Member Result Visibility

Deliver:

- Member access to their own published result;
- strict ownership;
- safe presentation;
- publication-state handling;
- download or viewing only through approved private-object boundaries where applicable.

#### MVP-08 — B2B Account Import

Deliver separately:

- controlled Member and Operator import;
- validation and rejection reports;
- idempotency;
- duplicate detection;
- temporary credentials;
- mandatory password replacement;
- audit and secret-handling controls.

Do not implement this before MVP-01 unless the owner reprioritizes it.

#### MVP-09 — Beta Hardening and Deployment Readiness

Deliver:

- cross-MVP regression verification;
- operational runbook;
- beta monitoring;
- backup and restore evidence;
- migration approval resolution;
- remaining critical gap review;
- controlled beta deployment decision.

Mark MVP-02 through MVP-09 as provisional and reprioritizable based on beta evidence and owner decisions.

Document dependencies without claiming dates that repository evidence cannot support.

### `docs/mvp/decision-log.md`

Create a dated decision-log structure.

Each entry must include:

| Field | Meaning |
|---|---|
| Decision ID | Stable identifier |
| Date | Decision date |
| Decision | Approved direction |
| Reason | Why it was selected |
| Impact | Affected flows or components |
| Temporary control | Control used while incomplete |
| Revisit trigger | Condition requiring reconsideration |
| Status | Proposed, approved, superseded, or rejected |

Seed approved decisions for:

- active delivery pivots from sequential Work Package completion to controlled MVP sequencing;
- Work Packages remain the long-term authoritative roadmap;
- initial MVP components are Member Portal, Operator Portal, Image Gateway, and Admin Portal;
- Doctor Portal is excluded;
- teleradiology physician is external to MHCS;
- first implementation slice is Member login and profile completion;
- public and online registration remain unwired;
- B2B import is deferred;
- initial beta is adult-only;
- manual Operator report handling is the fallback until automated report return is approved;
- incomplete capabilities are tracked in the beta gap register;
- fresh beta database use does not resolve the production UUID migration decision;
- no production-readiness claim is permitted from MVP completion alone.

Use the repository's current date when the task is executed.

### `docs/mvp/work-package-status.md`

Create a Work Package status ledger.

Use these columns:

| Work Package | Title | Requirement assignment | Repository evidence | MVP relevance | Status | Deferred items | Notes |
|---|---|---|---|---|---|---|---|

Allowed status values:

```text
accepted-foundation
partially-implemented
deferred-until-post-mvp
not-started
unverified
```

Rules:

- derive status from repository evidence;
- preserve existing numbering and titles;
- do not rename Work Packages;
- do not alter requirement assignments;
- do not claim acceptance without accepted evidence;
- distinguish an accepted foundation from a complete long-term capability;
- identify which accepted foundations are consumed by each planned MVP task;
- identify Work Package requirements deliberately deferred by the beta scope; and
- record ambiguity rather than guessing.

At minimum, explicitly document the current WP-04 relationship:

- it supplies the current User and Member identity foundation;
- several identity and guardian capabilities exist but remain outside the initial beta;
- online registration remains unwired;
- the forward-only UUID migration remains an approval boundary;
- MVP-01 consumes authentication, User, Member, protected-identifier, audit, and ownership foundations;
- WP-04 is not to be reopened wholesale for the initial beta.

## Rules for future MVP tasks

Document that every future MVP task must state:

- task name and version;
- baseline commit;
- user-visible objective;
- target component;
- included vertical flow;
- explicit exclusions;
- existing Work Package foundations consumed;
- known gaps accepted;
- gap-register entries closed, changed, or created;
- routes, commands, interfaces, events, and projections exposed;
- data ownership and authorization boundaries;
- focused tests;
- documentation updates;
- full-verification trigger;
- stop conditions; and
- prohibited unrelated work.

Every future MVP task must stop and report rather than silently change:

- shared authentication architecture;
- UUID strategy;
- cross-module identity contracts;
- module ownership;
- production deployment policy;
- privacy, retention, deletion, or legal policy;
- teleradiology contractual assumptions;
- imaging interoperability claims;
- role semantics;
- requirement assignments; or
- a shared interface owned by another active MVP task.

Every future MVP implementation task must use focused verification during iteration.

The complete validation pipeline should run:

- at an explicit integration or release gate;
- after a material shared-contract change;
- before controlled beta deployment; or
- when required by a specific task.

Do not require Docker-wide, MySQL-wide, frontend, and full-suite validation after every small edit unless the changed boundary requires it.

## Repository safety and modification boundary

This task may modify only:

```text
docs/mvp/**
```

Do not modify:

- `.agents/`;
- published task files;
- `docs/implementation/`;
- application code;
- database migrations;
- factories or seeders;
- tests;
- routes;
- controllers;
- middleware;
- models;
- services;
- events;
- configuration;
- Composer files;
- npm files;
- frontend assets;
- CI;
- deployment scripts;
- Docker files;
- environment templates; or
- requirement counts, assignments, classifications, or source digests.

Do not add dependencies.

Do not access or mutate production or staging.

Do not generate real credentials, NIKs, KKs, KTP/KIA images, patient records, or external teleradiology data.

## Required checks

Perform only documentation-appropriate checks.

Required:

```bash
git diff --check
```

Also run an existing repository Markdown or static documentation check only when:

- it already exists;
- it requires no dependency installation;
- it does not trigger application, Docker, database, or frontend validation; and
- it is relevant to the changed files.

Do not run:

```text
the full PHPUnit suite
MySQL conformance tests
Docker builds
deployment validation
npm build
Composer audit
database migrations
seeders
external integration checks
```

## Acceptance criteria

The task succeeds only when:

- all six required `docs/mvp/` files exist;
- the Work Package history is preserved;
- the Work Package and MVP relationship is explicit;
- the baseline and WP-04 boundary are recorded accurately;
- the four MVP components are explicit;
- the Doctor Portal is explicitly excluded;
- the external teleradiology participant boundary is explicit;
- the Admin Portal is included throughout scope and roadmap;
- the first implementation task is Member login and profile completion;
- B2B import is a separate later task;
- every known unavailable capability is represented in the gap register;
- future MVP task rules and stop conditions are documented;
- no runtime or planning-authority file outside `docs/mvp/` is modified;
- no completion or production-readiness claim exceeds repository evidence;
- documentation is internally consistent;
- documentation links and task names are consistent;
- `git diff --check` passes; and
- the final report clearly identifies remaining owner decisions.

## Stop conditions

Stop as `awaiting-approval` when:

- the baseline commit is absent from repository history;
- current changes overlap `docs/mvp/**`;
- existing authoritative project documentation already defines a conflicting MVP model;
- Work Package assignments cannot be reconciled without rewriting authoritative implementation documents;
- a requested roadmap item requires inventing a legal, privacy, deployment, interoperability, or teleradiology policy;
- the repository contains an already-approved Doctor Portal requirement that cannot be deferred without owner approval;
- the Admin Portal ownership boundary is materially ambiguous;
- the Image Gateway or teleradiology boundary cannot be described without unsupported conformance claims;
- producing the status ledger would require guessing acceptance state;
- any required change extends outside `docs/mvp/**`; or
- any destructive or production-affecting operation would be required.

When stopped, do not partially implement feature code.

Report:

- the exact conflict;
- the affected files or decisions;
- the safest available options;
- the owner decision required; and
- the unchanged repository state.

## Final report

Stop after reporting:

- current baseline and execution commit;
- documents created or updated;
- Work Package classifications;
- MVP components and exclusions;
- initial MVP roadmap;
- seeded gap IDs;
- seeded decision IDs;
- checks run and observed results;
- files changed;
- ambiguities or owner decisions still required; and
- confirmation that no application code, test, migration, route, dependency, or runtime configuration was changed.

Do not commit or push.
