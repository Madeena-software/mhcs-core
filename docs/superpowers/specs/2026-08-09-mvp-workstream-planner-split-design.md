# MVP Workstream Planner Split Design

**Date:** 2026-08-09

**Status:** Approved for implementation

## Objective

Separate review-and-task planning into two delivery workstreams while preserving
the approved `mhcs-core` modular-monolith architecture:

- the main workstream delivers Member, Member-owned administration, Operator,
  and Operator-owned administration; and
- the Image Gateway workstream delivers Image Gateway storage and processing,
  the AI SDK integration, and the private MPIPS API integration.

The Image Gateway feature branch will merge into `main`. This is a branch and
delivery-ownership decision, not a new repository, service, deployment, trust,
or data-ownership boundary.

## Current Evidence

The approved architecture places Member, Operator, Doctor, and Image Gateway in
one `mhcs-core` runtime. Image Gateway currently contains only a bounded
security and worker-boundary foundation; durable intake, MPIPS processing, AI
routing, publication, and the complete capture-acceptance contract remain
incomplete. Operator completion and Member result visibility still depend on
eventual Image Gateway integration.

The existing `.agents/prompts/sol-review-plan-create-task.md` has no workstream
filter. It can therefore select an Image Gateway task while being used on
`main`, or select Member or Operator work while being used on the Image Gateway
feature branch.

## Selected Design

### Planner prompts

Use two self-contained prompt files. Do not introduce a shared include,
template engine, or third prompt file.

1. Keep `.agents/prompts/sol-review-plan-create-task.md` as the main-workstream
   planner so existing launch instructions remain valid.
2. Add
   `.agents/prompts/sol-review-plan-create-image-gateway-task.md` as the
   Image Gateway workstream planner.

Both prompts retain the existing review, evidence-authority, freshness,
task-template, single-task, task-validation, no-product-implementation,
no-commit, no-push, and final-report requirements. Each prompt must inspect and
report the current Git branch and use the accepted baseline and latest completed
implementation from that branch.

The main-workstream planner may create tasks only for:

- Member Portal and Member-owned application behavior;
- Member-owned administration in the shared administrator interface;
- Operator Portal and Operator-owned application behavior;
- Operator-owned administration in the shared administrator interface; and
- shared or cross-module integration verification after an approved Image
  Gateway contract has been merged, provided the task does not implement Image
  Gateway internals.

It must not create tasks that implement Image Gateway storage, conversion,
workers, retries, AI routing, MPIPS adapters, publication internals, or Image
Gateway operational administration.

The Image Gateway planner may create tasks only for:

- Image Gateway application contracts and their implementation;
- durable imaging-object storage, manifests, idempotency, processing state,
  retries, and failure handling;
- private MPIPS API integration;
- AI SDK integration, AI routing, and publication behavior;
- Image Gateway operational administration; and
- focused integration tests needed to prove the Gateway-owned boundary.

It must not create Member Portal, Operator Portal, Member-administration, or
Operator-administration product features. A cross-workstream dependency must be
recorded as deliberately deferred; it must not be converted into work owned by
the wrong planner.

### MVP delivery documentation

No new operational file is added under `docs/mvp/`. The existing authority set
is updated in place:

- `docs/mvp/README.md` defines the two workstreams and states that final beta
  completion requires the Image Gateway branch to merge and pass integration
  verification.
- `docs/mvp/beta-scope.md` distinguishes workstream ownership from final
  integrated product scope. Image Gateway remains a final MVP component.
- `docs/mvp/roadmap.md` becomes a dependency-aware parallel-workstream roadmap,
  not one global linear task queue. Existing MVP identifiers are preserved.
- `docs/mvp/decision-log.md` records the owner-approved branch and workstream
  decision.
- `docs/mvp/beta-gap-register.md` identifies which open Gateway-related gaps are
  owned by the Image Gateway workstream and which Member or Operator gaps merely
  depend on it.
- `docs/mvp/work-package-status.md` marks WP-23 and WP-24 as Image Gateway
  workstream packages and records the cross-workstream dependencies affecting
  WP-14, WP-15, and WP-17 without changing requirement assignments or evidence
  status.

The following authorities remain unchanged because architecture, module
ownership, requirements, and the long-term plan have not changed:

- `.agents/context/**`;
- `docs/implementation/mhcs-core-requirements-matrix.md`;
- `docs/implementation/mhcs-core-source-coverage.md`; and
- `docs/implementation/mhcs-core-implementation-plan.md`.

### Roadmap ownership

| Milestone | Delivery ownership |
|---|---|
| MVP-04 | Main workstream |
| MVP-05 | Image Gateway workstream |
| MVP-06 | Shared integration milestone: Operator behavior on `main`; Gateway routing and processing on the Image Gateway branch |
| MVP-07 | Shared integration milestone: Member presentation on `main`; Gateway publication boundary on the Image Gateway branch |
| MVP-08 | Main workstream |
| MVP-09 | Integrated verification after the Image Gateway branch merges |

MVP identifiers are not renumbered. Neither planner advances solely by MVP
number. It must select the smallest independently testable next slice within
its workstream after reviewing the current branch evidence.

### Merge and integration boundary

The Image Gateway branch uses the existing `app/Modules/ImageGateway` module
boundary and the same database, queue, authentication, and authorization
foundations as `main`. It does not introduce an internal network API between
Operator, Member, and Image Gateway. MPIPS remains the private network service
boundary.

Image Gateway owns the complete-capture acceptance and publication contracts.
The main workstream may consume those contracts only after their approved form
is available on its branch. Before that merge, main-owned tasks must either
choose an independent Member or Operator slice or record the Gateway dependency
as deferred.

The final integration gate must verify at least:

- Operator-to-Image-Gateway complete-capture acceptance and retry semantics;
- Image-Gateway-to-MPIPS private conversion behavior;
- Image-Gateway-to-AI routing and publication behavior included by the merged
  branch;
- Member and Operator authorization at every exposed result/status boundary;
- idempotency and failure behavior across the merged workflow; and
- compatibility of migrations, configuration, queues, and focused test suites.

## Validation

The implementation is accepted when:

1. exactly two planner prompts exist for these workstreams;
2. the original planner filename remains the main-workstream entry point;
3. each prompt still creates and validates exactly one task and prohibits
   product implementation, commits, and pushes;
4. each planner has an explicit positive scope and explicit excluded scope;
5. the six MVP delivery documents consistently describe parallel workstreams,
   eventual merge, and the integrated release gate;
6. MVP numbering, module ownership, Work Package requirement assignments, gap
   status, and architecture authority remain intact;
7. no `.agents/context/**`, requirements-matrix, source-coverage, or long-term
   implementation-plan content changes; and
8. repository checks find no stale statement that the roadmap is a single
   global sequence or that Image Gateway has been removed from the final MVP.

## Out of Scope

- Product code, migrations, dependencies, tests, or runtime configuration.
- Creating or executing a published task.
- Implementing Image Gateway, AI SDK, or MPIPS behavior.
- Renaming or renumbering existing MVPs or Work Packages.
- Splitting Image Gateway into another repository or deployable service.
- Changing Doctor Portal scope, clinical requirements, privacy policy, or
  production-readiness claims.
