---
name: mhcs-core-mvp-00-remediation-module-owned-administration
description: Correct the controlled-beta MVP documentation so the shared administrator interface is composed of module-owned administration areas for Member, Operator, and Image Gateway rather than being described as one monolithic cross-module Admin Portal.
version: 1
---

<!-- antigravity-code-agent-template:managed -->
# Task: MHCS Core MVP-00 Remediation — Module-Owned Administration

## Objective

Remediate the MVP-00 documentation produced at commit:

`171819222abc69496a75363c6c4ef0e6fde5e689`

so that it accurately reflects the approved MHCS modular administration topology.

The current documentation describes `Admin Portal` as one independent MVP component and assigns a broad standalone `MVP-02 — Admin Portal Foundation`.

That wording is too coarse and risks creating a monolithic cross-module administration domain.

The approved architecture instead requires:

- one modular `mhcs-core` application;
- one shared authentication and authorization foundation;
- one administrator-facing interface, currently represented by the shared Filament `/admin` surface where applicable;
- module-owned administrative areas, resources, actions, configuration, and audit views;
- Member administration remaining owned by the Member module;
- Operator administration remaining owned by the Operator module;
- Image Gateway operational administration remaining owned by the Image Gateway module;
- genuinely shared administration limited to genuinely shared platform primitives; and
- no direct cross-module table mutation or business-rule bypass through the administrator interface.

This task changes planning documentation only.

It does not implement or restructure Filament panels, routes, resources, policies, application services, module code, or runtime behavior.

A `succeeded` outcome means all MVP documents use one consistent administration model:

```text
shared administrator interface
└── module-owned administration areas
    ├── Member administration
    ├── Operator administration
    └── Image Gateway operational administration
```

The documentation must not claim that each module is independently deployed or that every module necessarily has a separate URL or separate Filament panel.

## Runtime requirements

- Required capabilities:
  - `repository-read`
  - `repository-write`
  - `shell`
- Ordered model preferences: None.
- Require preferred model: `false`

## Runtime inputs

- `TARGET` (required): Path to the root of the `mhcs-core` repository.

## Context and evidence

Read completely before planning or writing:

- `$TARGET/AGENTS.md`;
- `$TARGET/.agents/AGENTS.md`;
- `$TARGET/.agents/skills/agent-task/SKILL.md`;
- `$TARGET/.agents/context/project.md`;
- `$TARGET/.agents/context/modules/member/project.md`;
- `$TARGET/.agents/context/modules/operator/project.md`;
- `$TARGET/.agents/context/modules/image-gateway/project.md`;
- `$TARGET/docs/mvp/README.md`;
- `$TARGET/docs/mvp/beta-scope.md`;
- `$TARGET/docs/mvp/beta-gap-register.md`;
- `$TARGET/docs/mvp/roadmap.md`;
- `$TARGET/docs/mvp/decision-log.md`;
- `$TARGET/docs/mvp/work-package-status.md`; and
- commit `171819222abc69496a75363c6c4ef0e6fde5e689`.

Treat these source-derived constraints as binding:

1. `mhcs-core` is one modular application, not independently deployed Member,
   Operator, Doctor, Image Gateway, or Admin services.
2. Business rules and tables remain module-owned.
3. Shared infrastructure must contain only genuinely shared primitives and must
   not become a miscellaneous cross-module business layer.
4. The application has a shared authenticated context.
5. The Member module specifies that Member administrators use the Filament
   panel at `/admin` for Member-owned administration.
6. The Operator module specifies that a global administrator manages
   Operator-owned sites and operational configuration.
7. The Image Gateway module specifies administrator-only operational access and
   queue workers, not a separate end-user application.
8. The context does not prove that Member, Operator, and Image Gateway each use
   independently deployed admin applications or distinct URLs.
9. The administrator interface must not bypass module application services,
   authorization, audit, state transitions, or ownership boundaries.
10. Doctor Portal and internal Doctor workflow remain excluded from this MVP.

Use repository evidence only.

Do not reinterpret the architecture from conversation memory.

## Scope and constraints

- This task is documentation-only.
- Allowed file changes are limited to `docs/mvp/**`.
- Do not modify application code, Filament code, panel providers, routes,
  resources, policies, tests, migrations, dependencies, or runtime
  configuration.
- Preserve all existing Work Packages, task files, requirement assignments,
  accepted evidence, and module context.
- Preserve the historical fact that MVP-00 originally used the broader
  `Admin Portal` wording.
- Do not delete decision history merely because a decision is corrected.
- Do not claim that separate physical admin portals already exist.
- Do not claim that a shared `/admin` interface is already fully implemented.
- Do not commit, stage, push, deploy, access production, or perform
  production-affecting operations.
- Do not continue into MVP-01 or any feature implementation.

## Execution policy

- Mode: `agentic-loop`
- Maximum iterations: `5`
- Approval gates: stop as `awaiting-approval` whenever any declared stop condition is met.

## Execution procedure

1. Resolve and verify `$TARGET`.
2. Confirm the repository, current branch, and current commit.
3. Verify that commit `171819222abc69496a75363c6c4ef0e6fde5e689` is present
   in repository history.
4. Inspect staged, modified, untracked, and relevant ignored files.
5. Stop as `awaiting-approval` if existing work overlaps `docs/mvp/**`.
6. Read the required architecture, module context, and all six MVP documents.
7. Identify every statement that treats Admin Portal as a monolithic
   cross-module business component or assigns module-owned administration to a
   generic shared owner.
8. Apply the bounded documentation corrections below.
9. Re-read all six MVP documents for terminology, roadmap, gap, and decision
   consistency.
10. Run the required documentation checks.
11. Re-read this task and report the outcome.

## Required remediation

### 1. Correct the administration topology

Across the MVP documentation, replace the ambiguous model:

```text
Admin Portal as one independent cross-module administration component
```

with:

```text
shared administrator interface
with module-owned administration areas
```

The corrected documentation must explain:

- the user may enter through a shared administrator-facing interface;
- the interface may use the shared Filament `/admin` surface;
- Member, Operator, and Image Gateway administrative resources remain owned by
  their respective modules;
- each module defines its own authorization, actions, state transitions,
  configuration, projections, and audit behavior;
- shared navigation or presentation does not transfer data or business-rule
  ownership;
- shared platform administration is limited to genuinely shared primitives;
- module administration must invoke approved module application boundaries;
- a generic administrator must not directly edit unrelated module tables; and
- separate panels or URLs may be introduced later only through an explicit
  architecture decision and implementation task.

Do not use wording that implies three independently deployed administrator
applications.

### 2. Update `docs/mvp/README.md`

Correct `Active controlled-beta components`.

The preferred model is:

- Member Portal;
- Operator Portal;
- Image Gateway module and workers; and
- shared administrator interface composed of module-owned administration areas.

Clarify that the fourth item is an application interface, not a new business
domain that owns Member, Operator, or Image Gateway records.

Add a concise administration-ownership rule.

### 3. Update `docs/mvp/beta-scope.md`

Replace the current generic `### Admin Portal` section with a section such as:

```md
### Shared administrator interface and module-owned administration
```

Define at least:

#### Member administration

- Member accounts and Member-owned identity administration;
- Member-owned service offerings, schedules, bookings, and later Member
  commercial configuration when included by an MVP task;
- use of Member-owned application services and authorization;
- no direct mutation of Operator or Image Gateway records.

#### Operator administration

- Operator accounts and assignments;
- Operator-owned organizations, physical sites, protocol configuration, queue
  exceptions, and operational controls;
- use of Operator-owned application services and authorization;
- no direct mutation of Member or Image Gateway records.

#### Image Gateway operational administration

- processing status;
- conversion jobs;
- retry and terminal-failure handling;
- exceptional compliance operations;
- storage and publication operational visibility;
- administrator-only access, not a separate end-user application;
- no direct mutation of Member booking or Operator queue ownership.

Also clarify:

- one authenticated administrator account may have more than one authorized
  module administration capability;
- authorization remains capability- and module-specific;
- shared UI does not imply shared ownership.

### 4. Update `docs/mvp/roadmap.md`

Replace the broad standalone:

```text
MVP-02 — Admin Portal Foundation
```

with a bounded initial administration slice.

Use:

```text
MVP-02 — Shared Admin Shell and Member Administration Foundation
```

MVP-02 may deliver only:

- the shared administrator-facing shell or navigation needed for MVP;
- Member module administration needed to manage controlled Member accounts;
- approved account-state actions through existing application boundaries;
- Member-owned administrative views required for the initial beta;
- foundational audit visibility relevant to that slice.

MVP-02 must not claim ownership of all Operator and Image Gateway
administration.

Update later roadmap entries so:

- `MVP-04 — Operator Queue and Attendance` includes the required
  Operator-owned administration for sites, assignments, protocol/queue
  exceptions, and operational configuration used by that slice;
- `MVP-05 — Image Gateway Study Intake and Correlation` includes the required
  Image Gateway operational administration for intake status, correlation
  failures, retry visibility, and terminal failures;
- `MVP-06 — Operator Teleradiology Workflow` may extend Operator and Image
  Gateway administration only within their existing module ownership;
- no roadmap task creates a generic cross-module database editor.

Keep the remaining roadmap order unless this correction strictly requires a
terminology or dependency update.

### 5. Update `docs/mvp/decision-log.md`

Preserve decision history.

Do not delete `MVP-DEC-003`.

Change its status from `Approved` to `Superseded` and make clear that its
four-component wording was too ambiguous about administration ownership.

Append a new approved decision using the next available stable ID.

The new decision must state:

```text
MHCS uses a shared administrator interface composed of module-owned
administration areas.
```

Record:

- reason: preserve module ownership while allowing one authenticated
  administrator experience;
- impact: README, beta scope, roadmap, gap register, and future MVP tasks;
- temporary control: no generic cross-module administrator resource or direct
  table-editing surface;
- revisit trigger: an explicit architecture decision to split or restructure
  panels;
- status: Approved.

Do not renumber existing decisions.

### 6. Update `docs/mvp/beta-gap-register.md`

Preserve stable gap IDs and history.

Update `MVP-GAP-010` so it no longer describes one monolithic Admin Portal.

It should represent the initial shared admin shell and Member administration
foundation.

Add new gaps with the next available stable IDs for:

- Operator-owned administration required by Operator MVP flows; and
- Image Gateway operational administration required by Image Gateway MVP
  flows.

Each new gap must include:

- affected module and flow;
- beta impact;
- temporary control;
- target MVP task;
- status;
- revisit trigger; and
- notes preserving module ownership.

Update ambiguous affected-component labels such as:

```text
Member/Admin
Member/Operator/Admin
Operator/Admin
```

only where required to identify the owning module administration area.

Do not close unrelated gaps.

### 7. Update `docs/mvp/work-package-status.md`

Inspect the ledger for wording that assigns all administration to one generic
Admin Portal.

Correct only affected cells or notes.

Preserve:

- Work Package numbering;
- titles;
- requirement assignments;
- evidence;
- status values; and
- unrelated deferred items.

Where administration is relevant, identify the owning module rather than a
generic cross-module Admin owner.

### 8. Preserve excluded and deferred scope

Do not change these established MVP decisions:

- Doctor Portal remains excluded;
- teleradiology physician remains external;
- public and online registration remain unavailable;
- B2B import remains deferred;
- initial beta remains adult-only;
- MVP-01 remains Member login, mandatory password replacement, profile
  completion, dashboard, and logout;
- production readiness remains a separate approval boundary.

## Required checks

Run:

```bash
git diff --check
```

Verify the allowed-path boundary with Git.

Verify that only files under:

```text
docs/mvp/**
```

were modified.

Search the six MVP documents for ambiguous terms including:

```text
Admin Portal
single Admin
generic administrator
Member/Admin
Operator/Admin
```

Review each remaining occurrence manually.

A remaining `Admin Portal` phrase is allowed only when it clearly refers to the
shared administrator-facing interface and immediately preserves module-owned
administration.

Do not run:

- PHPUnit;
- MySQL tests;
- Docker;
- migrations;
- seeders;
- npm build;
- Composer audit;
- deployment checks; or
- external integration checks.

## Acceptance criteria

- [ ] Only files under `docs/mvp/**` are modified.
- [ ] All six MVP documents were read and reconciled.
- [ ] The documentation describes one shared administrator-facing interface.
- [ ] The documentation explicitly preserves module-owned administration.
- [ ] Member administration remains owned by the Member module.
- [ ] Operator administration remains owned by the Operator module.
- [ ] Image Gateway operational administration remains owned by the Image Gateway module.
- [ ] The documentation does not claim separately deployed module admin applications.
- [ ] The documentation does not create a monolithic cross-module Admin business domain.
- [ ] Shared platform administration is limited to genuinely shared primitives.
- [ ] MVP-02 is narrowed to the shared admin shell and Member administration foundation.
- [ ] Operator administration is assigned to the relevant Operator MVP task.
- [ ] Image Gateway operational administration is assigned to the relevant Image Gateway MVP task.
- [ ] `MVP-DEC-003` is preserved and marked `Superseded`.
- [ ] A new approved administration-topology decision is appended with a stable ID.
- [ ] `MVP-GAP-010` is corrected without deleting its history.
- [ ] New stable gaps exist for Operator administration and Image Gateway operational administration.
- [ ] Doctor exclusion and other established MVP boundaries remain unchanged.
- [ ] No application code, route, test, migration, dependency, or runtime configuration is modified.
- [ ] `git diff --check` passes.
- [ ] The final documentation is internally consistent and does not overclaim implementation.

## Verification

- Method: Run `git diff --check` and verify the six MVP documents, administration terminology, decision and gap continuity, and allowed-path boundary with Git.
- Expected result: The check passes, module-owned administration is consistently documented, decision and gap history is preserved, and no file outside `docs/mvp/**` is modified.

## Stop conditions

Stop as `awaiting-approval` when:

- commit `171819222abc69496a75363c6c4ef0e6fde5e689` is absent from repository
  history;
- existing work overlaps `docs/mvp/**`;
- repository context proves that each module must use a separate physical
  Filament panel or separate route and the required topology cannot be
  documented without a new architecture decision;
- repository context proves that all administration is intentionally owned by
  one cross-module business domain;
- changing the roadmap would require renumbering published MVP tasks;
- decision or gap IDs cannot be preserved safely;
- a required correction extends outside `docs/mvp/**`;
- any implementation, migration, deployment, or production operation is
  required; or
- source evidence is materially contradictory.

When stopped, report the exact conflict, affected documents, safest options,
and owner decision required.

Do not make speculative partial corrections.

## Output

- `succeeded`: all acceptance criteria and checks pass.
- `failed`: execution occurred but a required criterion or check failed.
- `blocked`: required tooling or evidence is unavailable.
- `awaiting-approval`: an approval gate or stop condition is reached.
- `exhausted`: the iteration limit is reached before completion.

## Final report

Report:

- baseline and current commit;
- documents modified;
- administration topology adopted;
- roadmap changes;
- decision IDs changed or added;
- gap IDs changed or added;
- ambiguous terms intentionally retained and why;
- checks run and observed results;
- files changed;
- unresolved owner decisions; and
- confirmation that no application code, Filament code, route, test, migration,
  dependency, runtime configuration, commit, push, or deployment was performed.

Do not commit or push.
