---
title: MHCS Core Production Private-Storage Root-Cause Investigation
document_id: MHCS-TASK-PRODUCTION-PRIVATE-STORAGE-ROOT-CAUSE-001
version: 1.0
status: validated-published
language: en-US
last_updated: 2026-08-26
scope:
  - ongoing private-storage and NPZ persistence root-cause investigation
  - bounded diagnostic workflows and static safety tests
authority_note: This umbrella task authorizes only bounded diagnostic implementation and separately gated diagnostic execution within the stated investigation. It authorizes no corrective change or unapproved operational side effect.
---

# Executable Task

## Task identity

**Task title:**
`MHCS Core Production Private-Storage Root-Cause Investigation`

**Task path:**
`.agents/tasks/production-private-storage-root-cause-investigation.md`

**Task contract state:**
`Validated/Published upon immutable publication of this exact content.`

**Delivery objective / Work Package / MVP:**
`Ongoing production private-storage / NPZ root-cause investigation`

**Owner / designated planning authority:**
`Faliq Adlan, CTO`

## Delivery context

This is one stable diagnostic authority for the ongoing investigation of
private object persistence, NPZ capture behavior, and related consent and
questionnaire persistence evidence. It replaces the need for a new narrow task
for every bounded probe while the investigation remains within this task's
side-effect boundary.

The investigation ends when one root cause is confirmed. Any corrective work
after confirmation requires a separate fix task.

## Baseline and task revision

**Implementation baseline:**
`063af519e4f989ede7bdebffef7d4140252218bf`

**Task revision:**
`The full SHA of the commit containing this exact task content, supplied after publication.`

The implementation revision for each bounded diagnostic change is separate
from the immutable revision containing this task.

## Objective

Maintain a bounded, evidence-driven diagnostic program that can identify one
confirmed root cause for the private-storage / NPZ investigation without
silently changing application, storage, infrastructure, security, or data
behavior.

## Authoritative inputs

### Governing authority

- `.agents/AGENTS.md` and `.agents/software-workflow.md` — delivery, evidence, review, and side-effect boundaries.
- `.agents/context/project.md` and relevant module context — approved architecture and private-storage boundaries.
- Existing implementation, historical diagnostic tasks, workflows, tests, and observed verification evidence — supporting implementation context only.
- CTO/user approval for this umbrella scope and each separately gated runtime execution where operational risk exists.

### Requirement traceability

- `PRIVATE-STORAGE-DIAG-001` → establish sanitized configuration and deployment provenance.
- `PRIVATE-STORAGE-DIAG-002` → diagnose local and effective production S3 reachability and MinIO/Docker listener topology read-only.
- `PRIVATE-STORAGE-DIAG-003` → compare bounded sync-vs-async behavior, ACL, key/prefix, stream/size, authorization, and error families only when separately authorized.
- `PRIVATE-STORAGE-DIAG-004` → verify informed-consent/questionnaire persistence and NPZ private-object persistence without unapproved data mutation.
- `PRIVATE-STORAGE-DIAG-005` → collect sanitized evidence and refine diagnostic workflows/tests until one root cause is confirmed.

## Scope

### In scope

- GitHub Actions deployment-configuration provenance diagnostics, including sanitized `AWS_ENDPOINT` classification.
- Local read-only S3 diagnostics and production effective S3 configuration inspection.
- Bounded local sync-vs-async differential diagnostics when separately authorized.
- MinIO/Docker connectivity, listener, bind-scope, and host-gateway diagnostics.
- Informed-consent and questionnaire persistence verification.
- NPZ private-object persistence diagnostics.
- Sync-vs-async, ACL, key/prefix, stream/size, authorization, and error-family investigation.
- Bounded diagnostic workflow and static-test refinements.
- Evidence collection and interpretation until one root cause is confirmed.

### Out of scope

- Application or business-logic changes.
- Storage implementation fixes or corrective infrastructure changes.
- `AWS_ENDPOINT` or any GitHub Secret mutation, disclosure, hashing, encoding, or bypass of masking.
- Bucket, IAM, ACL, ownership, policy, endpoint, region, credential, or other configuration mutation.
- Database writes, migrations, seeders, queue changes, or clinical/data mutation.
- Deployment, release, service restart, container restart, firewall mutation, or network-policy mutation.
- New production access, synthetic writes, object deletion, or other operational side effect unless separately and explicitly authorized within the applicable diagnostic boundary.
- Rewriting or deleting historical diagnostic task files.

### Preserved behavior

- Existing application, storage, security, privacy, data, deployment, and production behavior remains unchanged.
- Diagnostic output contains only approved sanitized classifications and evidence.
- Raw secrets, credentials, bucket names, endpoints, hostnames, object keys, payloads, identifiers, and clinical contents are never emitted.
- Historical diagnostic tasks remain untouched.

## Dependencies and assumptions

### Dependencies

- A clean or explicitly reconciled implementation baseline for each diagnostic refinement.
- Existing repository workflow/test conventions and applicable approved architecture context.
- Separate authority for any runtime execution whose operational risk exceeds repository-only inspection.

### Approved assumptions

- A bounded diagnostic probe remains under this umbrella task while it serves this investigation and does not materially expand the permitted side-effect boundary.
- No new task is required for each diagnostic probe within that boundary.
- Repository implementation/refinement of bounded diagnostic workflows and their tests is authorized by this umbrella task.

### Remaining approval requirements

- Individual runtime execution remains separately gated where operational risk exists, especially production inspection or any synthetic write.
- If a probe requires a materially broader side effect than this task permits, refine and republish this same umbrella task before execution; do not create a new narrow diagnostic task.
- Any corrective change after root-cause confirmation requires a separate validated fix task and its own authority.

## Required capabilities

- Repository read/write and local test execution.
- Codebase Memory MCP or equivalent repository intelligence when materially useful.
- No production, external network, secret-management, or deployment capability is required for repository implementation.

## Execution constraints

- Prefer the smallest bounded diagnostic workflow and static coverage that proves the required safety properties.
- Use standard-library or already-installed mechanisms; add no dependency for diagnostics.
- Keep raw secret values internal to the process and emit only fixed classifications.
- Use `workflow_dispatch` for manual diagnostics unless an approved existing boundary requires otherwise.
- Set explicit least-privilege permissions and fail closed on unsafe or unverifiable conditions.
- Do not dispatch a diagnostic as part of implementation or verification.

## Acceptance criteria

- [ ] Bounded diagnostic workflows and focused static tests can be added or refined under this umbrella task without creating a new task per probe.
- [ ] The `AWS_ENDPOINT` provenance probe is manual-only, GitHub-hosted, repository-local, network-free, and emits only sanitized classifications.
- [ ] Diagnostic implementations cannot mutate secrets, configuration, buckets, IAM, ACL/policy, databases, services, networks, or production state.
- [ ] Individual operationally risky executions remain explicitly gated and are not implied by repository implementation.
- [ ] Historical diagnostic task files are unchanged.
- [ ] The investigation remains active until one root cause is confirmed; corrective work then routes to a separate fix task.

## Verification requirements

### Required checks

- Focused static tests for each diagnostic workflow's trigger, runner, output, secret-safety, and prohibited-side-effect properties.
- Relevant PHP test suite or focused PHPUnit invocation.
- Git diff and history inspection proving historical task files are untouched and task/implementation revisions are distinct.

### Required evidence

The Executor MUST report the exact umbrella task revision, implementation revision,
parent chain, commands and observed results, known gaps, and any runtime
execution performed. A diagnostic dispatch is not part of implementation.

## Stop conditions

Stop and return to planning when a diagnostic requires a materially broader
side effect, a corrective change, a missing authority decision, production
mutation, secret/configuration mutation, data mutation, or scope beyond this
investigation.

## Side-effect authorization

### Explicitly authorized side effects

- Repository implementation/refinement of bounded diagnostic workflows and their static tests within this investigation.
- Commit and push of the umbrella task and its bounded diagnostic implementation.

### Not authorized by this task

- Any runtime diagnostic dispatch without separate authorization where operational risk exists.
- Production inspection or synthetic writes without the applicable separate execution approval.
- Any corrective application, storage, infrastructure, secret/configuration, policy, database, deployment, restart, firewall, or network change.

## Expected terminal outcome

### Review Required

Use when a bounded diagnostic implementation and truthful verification evidence
are available for review. Implementation acceptance does not authorize dispatch,
production execution, or release.

### Planning Required

Use when the investigation confirms a root cause and corrective work is needed,
or when a required side-effect boundary or authority decision expands. Create a
separate fix task for corrective work.

## Review and remediation handling

Bounded corrections within this investigation update and republish this same
stable umbrella task when the contract changes. Materially broader side effects
or corrective objectives require planning and a separate fix task.
