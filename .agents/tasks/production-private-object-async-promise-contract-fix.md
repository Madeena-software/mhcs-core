---
title: MHCS Core Private-Object Async Promise Contract Fix
document_id: MHCS-TASK-PRIVATE-OBJECT-ASYNC-PROMISE-CONTRACT-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-26
scope:
  - correction of the S3-backed asynchronous private-object promise contract
  - regression coverage for Image Gateway concurrent source persistence
authority_note: This task authorizes only the bounded application and test fix described below. Production deployment and production runtime validation remain separately authorized side effects.
---

# Executable Task

## Task identity

**Task title:**
`Correct the PrivateObjectStore asynchronous promise contract used by Image Gateway capture persistence`

**Task path:**
`.agents/tasks/production-private-object-async-promise-contract-fix.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content.`

**Delivery objective / Work Package / MVP:**
`Production private-object persistence corrective fix`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

Production run `32940350517`, executed from workflow revision
`95bfc6889544c8809f66d9e706d0f9f2166ef59c`, established the bounded mechanism
for the observed Image Gateway source-write failure. The S3-backed
`PlainLocalObjectStore::putStreamAsync()` callback is declared to return
`PrivateObject` but directly returns the nested metadata `PromiseInterface`.
PHP therefore can reject the outer promise with a `TypeError` after both the
object and metadata requests have started. `ImageGatewayCaptureService`
consumes the settled promises and treats rejected radiograph/gain states as
source failures.

This is a separate application fix task. It does not reopen or combine the
parked internal MinIO `:9000` topology investigation.

## Baseline and task revision

**Implementation baseline:**
`95bfc6889544c8809f66d9e706d0f9f2166ef59c`

**Observed production application revision:**
`b6232a158b3f6884fd9823bc875abc432676b781`

**Root-cause evidence:**
`production workflow run 32940350517`, authorized workflow revision
`95bfc6889544c8809f66d9e706d0f9f2166ef59c`

**Prior root-cause task:**
`.agents/tasks/production-private-storage-root-cause-investigation.md`

**Task revision:**
`The full SHA of the commit containing this exact task content, supplied after publication.`

The implementation baseline and task revision are distinct. The task becomes
executable only after its exact immutable publication revision is resolved.

## Objective

Correct the S3-backed `PlainLocalObjectStore::putStreamAsync()` promise chain
so successful primary-object and metadata persistence fulfills the returned
`PromiseInterface` with a `PrivateObject`, and verify that concurrent Image
Gateway radiograph/gain persistence settles as fulfilled when both writes
succeed.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — delivery, evidence, review, and side-effect boundaries.
- `.agents/context/project.md` and the Image Gateway architecture context — private opaque object storage and Image Gateway ownership boundaries.
- `.agents/tasks/production-private-storage-root-cause-investigation.md` — prior investigation authority and separate-fix routing.
- Production run `32940350517` — observed sanitized mechanism evidence, not product authority.

### Requirement traceability

- `PRIVATE-OBJECT-ASYNC-001` → successful asynchronous object plus metadata persistence fulfills with `PrivateObject`.
- `PRIVATE-OBJECT-ASYNC-002` → primary-object and metadata failures remain rejected and are not swallowed.
- `PRIVATE-OBJECT-ASYNC-003` → Image Gateway concurrent radiograph/gain persistence is not failed by the old promise-contract mismatch.
- `PRIVATE-OBJECT-ASYNC-004` → existing synchronous storage behavior and storage/security invariants are preserved.

## Scope

### In scope

- `app/Shared/Storage/PlainLocalObjectStore.php` asynchronous promise-chain correction.
- Targeted regression tests for the S3-backed async storage contract, metadata sequencing, failure propagation, and concurrent pair settlement.
- The closest existing Image Gateway capture-service regression seam where needed to prove successful radiograph/gain persistence is accepted.
- Focused verification using existing repository fakes, mocks, and test conventions.

### Out of scope

- AWS endpoint, MinIO endpoint, routing, listener, or internal `:9000` topology changes.
- Bucket, IAM, ACL policy, ownership, credentials, secrets, or configuration changes.
- Docker, Swarm, reverse proxy, network, firewall, deployment, restart, or release work.
- Database schema changes, upload-size tuning, unrelated frontend changes, or broad refactoring.
- Production workflow dispatch, production S3 writes/deletes, manual S3 operations, or production runtime validation.
- Reopening the root-cause investigation or claiming the realistic large-payload probe ran; it was skipped after the earlier failure boundary.

### Preserved behavior and invariants

- `putStreamAsync()` remains a `PromiseInterface` and fulfills only after both primary object and metadata persistence succeed.
- The fulfilled value remains a `PrivateObject` with the existing key, checksum, byte count, and creation timestamp.
- Existing `ACL => private`, key naming, metadata schema, checksum, byte count, stream handling, and `AuthenticatedContext` checks remain unchanged.
- Primary-object failure and metadata failure propagate as rejected promises; no real persistence failure is converted into fulfillment.
- Synchronous `putStream()` and `put()` behavior remains unchanged.
- No production storage endpoint/configuration change is required.

## Dependencies and assumptions

### Dependencies

- The implementation baseline remains `95bfc6889544c8809f66d9e706d0f9f2166ef59c` or is explicitly reconciled by Planner.
- Existing Guzzle promise, Laravel filesystem, and repository test dependencies remain available.
- Existing test fakes/mocks can model successful and failed primary/metadata S3 operations without real production access.

### Approved assumptions

- Promise assimilation/flattening may be repaired using the smallest repository-consistent implementation; this task does not prescribe exact syntax.
- Concurrency is not the fundamental root cause. The reproduced mechanism is the incompatible callback return type in the nested async promise chain.
- The observed production evidence is sufficient to publish this separate fix task, but implementation must still prove the acceptance criteria locally.

### Remaining approval requirements

- Repository implementation, commit, and push may be authorized by this task according to repository policy.
- Production deployment, production workflow execution, production S3 operations, and production runtime validation require separate explicit authorization.
- If implementation requires a configuration, infrastructure, schema, dependency, or scope decision outside this task, stop and return to planning.

## Required capabilities

- Repository read/write and local test execution.
- Existing Guzzle/Laravel/PHP test tooling.
- Codebase Memory MCP or equivalent repository intelligence when materially useful.
- No production, secret-management, deployment, or external-storage capability.

## Execution constraints

- Inspect and reuse existing storage and Image Gateway test patterns before adding helpers or abstractions.
- Make the smallest coherent change that fixes promise assimilation and preserves existing request arguments and metadata behavior.
- Do not add dependencies or production S3 configuration.
- Do not weaken authorization, validation, privacy, cleanup, or failure handling.
- Do not make real S3 calls in ordinary tests.
- Do not modify unrelated application, configuration, database, resource, workflow, or task files.

## Acceptance criteria

- [ ] A successful S3-backed `putStreamAsync()` resolves its returned promise only after primary object and metadata persistence complete.
- [ ] The eventual fulfilled value is a `PrivateObject`.
- [ ] A successful single async write does not throw or reject because a nested `PromiseInterface` is returned from an intermediate callback.
- [ ] Two independently initiated async writes can be passed to `Utils::settle()` before waiting, and both report `state=fulfilled` when their writes succeed.
- [ ] Image Gateway concurrent radiograph/gain persistence is not marked failed solely because of the old promise-contract `TypeError`.
- [ ] Existing synchronous `putStream()` and `put()` behavior is preserved.
- [ ] ACL, key naming, metadata schema, checksum, byte count, stream handling, and `AuthenticatedContext` checks are preserved.
- [ ] Primary-object S3 failure rejects the returned promise.
- [ ] Metadata S3 failure rejects the returned promise.
- [ ] Real persistence failures are not silently converted into fulfilled promises.
- [ ] No production endpoint/configuration or internal MinIO topology change is required.

## Verification requirements

### Required checks

- Add or update a focused promise-contract test proving successful S3-backed async persistence resolves to `PrivateObject`.
- Add a regression assertion that catches the defective nested-promise/callback-return-type shape.
- Start two async writes before settlement and verify `Utils::settle([$radiographPromise, $gainPromise])->wait()` yields two fulfilled states for successful mocked persistence.
- Verify the returned promise does not fulfill before metadata persistence succeeds.
- Verify primary-object failure and metadata failure each reject the returned promise.
- Exercise the closest existing Image Gateway capture persistence seam and verify successful radiograph/gain writes are accepted.
- Run the focused PHPUnit/Pest tests covering the changed storage and Image Gateway behavior, plus relevant existing tests.
- Run `git diff --check` and inspect the final diff and working-tree status.

### Required evidence

The Executor MUST report:

- governing task path and immutable task revision;
- implementation baseline and implementation revision or exact working-tree state;
- changed files;
- commands and observed results;
- tests added or changed;
- evidence for every acceptance criterion, including failure propagation;
- known verification gaps and material deviations;
- confirmation that no production access, deployment, workflow dispatch, or real S3 operation occurred.

## Root-cause traceability

The implementation and review record must preserve this evidence chain:

```text
successful sync private-document persistence
→ production run 32940350517
→ direct SDK async PASS
→ private ACL PASS
→ real radiograph key shape PASS
→ single wrapper persistence PASS but non-AWS rejection evidence
→ concurrent wrapper promises rejected while metadata exists
→ PlainLocalObjectStore::putStreamAsync() source inspection
→ incompatible callback return type
→ ImageGatewayCaptureService Utils::settle() consumes rejected promises
→ operator sees radiograph/gain as missing
```

The realistic `89,660,664` / `17,713,052` byte probe did not run because P6
short-circuited. Do not claim size is irrelevant to every future storage
failure; only the currently reproduced promise-rejection mechanism is
sufficiently explained without it.

## Stop conditions

Stop and return to planning if:

- the baseline no longer matches or cannot be safely reconciled;
- a required product, requirement, architecture, dependency, or approval decision is missing;
- the fix requires configuration, infrastructure, schema, deployment, production, or external-system mutation;
- the promise contract cannot be corrected without changing the approved storage or Image Gateway boundary;
- acceptance requires swallowing real persistence failures or weakening existing security/data invariants;
- the task would need unrelated refactoring or scope expansion.

## Side-effect authorization

### Explicitly authorized side effects

- Repository-only changes within the in-scope application and test files.
- Local test and static verification commands.
- One repository commit and push for the bounded implementation, if normal repository policy authorizes them.

### Not authorized by this task

- Production deployment, release, runtime validation, workflow dispatch, production S3 access, manual S3 writes/deletes, or external-system mutation.
- Changes to endpoints, secrets, credentials, IAM, buckets, policies, MinIO, Docker/Swarm, network, firewall, database schema, or unrelated repository areas.

## Expected terminal outcome

### Review Required

Use when the bounded implementation and truthful verification evidence are
available for Planner/Reviewer evaluation. Implementation acceptance is not
production deployment or release authorization.

### Planning Required

Use when a stop condition prevents safe completion, or when the mechanism,
scope, baseline, or authority differs materially from this task.

