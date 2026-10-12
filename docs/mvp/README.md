# MHCS Core Admin-first operational MVP

The active priority is **one persistent internal Admin login and one unified operational dashboard** over Member, Operator, Doctor, and Image Gateway capabilities. The intended destination is real operational use with patient data; this planning direction grants no production-data, clinical-policy, or release approval.

## Current synchronization marker

Documentation rebaseline: 2026-10-12. Application baseline: `b1a2204a2571920a6c696575a6480aad5a1294fd`. Governing [task](../../.agents/tasks/admin-first-documentation-rebaseline.md): `5e47b8d61db27741c2048b7c5131b5a7f0f4ef49`, independently validated after review of `74755cb2c0b40c061b4734c1c1fb5b267a7eb805`.

This documentation implementation is **Review Required**. It does not establish a new accepted application baseline. The earlier `841465faac4e8b1dd3670103052a9c4f075bfd04` / 2026-08-21 synchronization remains the date boundary for retained beta/status records.

## Authority and relationship

Business intent is pinned to `Madeena-software/mhcs-business-docs @ 645058e431f59c4450a136e72f140e6819b79f32`; unchanged local [business sources](../../.agents/context/project.md#intended-authority-map) remain accessible. Repository governance, approved source/requirement/architecture authority, and explicitly scoped human decisions govern delivery. Conflicts require recorded reconciliation, not silent replacement by task wording or current code.

The [implementation plan](../implementation/mhcs-core-implementation-plan.md), [requirements matrix](../implementation/mhcs-core-requirements-matrix.md), and [source-coverage ledger](../implementation/mhcs-core-source-coverage.md) are KEEP / historical authority and reference. Their IDs, assignments, source digests, and unresolved provenance remain unchanged. Work Packages retain long-term obligations; reprioritization does not complete or renumber them.

[MVP-DEC-044](decision-log.md#admin-first-priority-decision) supersedes the old portal-first/Operator-first active priority and date sequence only. Earlier approvals, clinical/security constraints, bounded exceptions, and evidence retain their original meaning. A deferral requires an owner, control, trigger, and closure evidence and cannot weaken an exposed boundary.

## Current delivery topology and integration gate

`mhcs-core` remains a modular monolith; MPIPS remains a separate repository and private processing service. Shared Admin navigation uses domain-owned application boundaries and does not authorize generic cross-module table editing. The current documentation work uses the authorized task branch; historical direct-main/workstream instructions are not permission to merge or release.

Preserve authenticated DICOM ingestion and legacy NPZ/MPIPS processing compatibility. Capture acceptance, DICOM availability, AI processing, clinical finalization, and Member publication are separate states. AI screening PDFs are not signed or clinically verified reports. Integration, security/privacy, and release gates remain required for later exposed behavior.

## Active operational scope

See the [scope and evidence table](beta-scope.md#admin-first-operational-scope):

- Admin-managed sites, shifts, Members, and examination assignments.
- Manual registration and planned CSV/XLSX bulk import with templates and validation.
- A centralized study worklist and authorized monitoring of ingestion, processing, and report status.
- Secure time-limited access to authorized finalized Member results without permanent portal accounts.
- Scoped temporary radiographer/radiologist workspaces with separate accountable actors, assignments, reports, and access events.

These are target outcomes, with existing foundations distinguished from planned features and unresolved policy. [Open gaps](beta-gap-register.md#admin-first-gaps-and-authority-reconciliation) prevent unsupported implementation/readiness claims.

## Active controlled-beta components

The earlier Member Portal, Operator Portal, Gateway, and shared Admin component grouping is historical. Existing portal code and accepted foundations are preserved. A no-permanent-Member-account target and scoped clinical workspaces do not authorize account removal, migration, internal Doctor implementation, or new access rights. Earlier Doctor exclusions and manual/external report fallbacks remain dated decisions, with their changed planning relevance recorded under MVP-DEC-044.

## Required consumption

Use this concise active map before selecting a dependent task:

| Document | Purpose |
|---|---|
| [Scope](beta-scope.md) | Existing foundations, target workflow, exclusions, and historical beta |
| [Gaps](beta-gap-register.md) | Open decisions, owners, controls, triggers, and preserved gap history |
| [Roadmap](roadmap.md) | Admin-first planning dependencies and retained milestone history |
| [Decisions](decision-log.md) | Attributed priority supersession and unchanged historical approvals |
| [Work Package status](work-package-status.md) | Current evidence limits and dated status records |

Each later task must identify its exact baseline, governing revision, affected authority/requirements/gaps, scope, preserved behavior, verification, and stop conditions. Load relevant references rather than reconstructing unchanged ledgers. See the [repository context](../../.agents/context/project.md) for source routing and governance.

## Historical MVP pivot baseline

The earlier pivot used planning baseline `bc300e158a790a7311c64eb7b20e8e81d4e3ec41` and publication commit `1960585472e13e78c8136280d8a76f7a9ad76a30`. Its WP-01/WP-02/WP-04 foundations remain historical evidence. Public/online registration was excluded from that initial beta; the new manual-registration priority does not approve public onboarding. The detailed historical scope, milestones, gaps, decisions, and evidence remain at their existing paths.
