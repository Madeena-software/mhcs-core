# MHCS Core controlled-beta MVP

This directory is the repository source of truth for active controlled-beta
delivery state. It records scope, decisions, gaps, roadmap dependencies,
and the evidence-based relationship between MVP tasks and the long-term Work
Package roadmap.

This is planning documentation. It does not prove that a feature is implemented, that a beta is deployed, or that production readiness has been approved.

## Current synchronization marker

Reconciled against: `841465faac4e8b1dd3670103052a9c4f075bfd04`

Reconciliation date: `2026-08-21`

MVP is the active bounded delivery scope. Work Packages remain the long-term
authoritative roadmap. Current implementation status comes from current
observed source, tests, accepted evidence, Git history, and verified runtime
evidence; older evidence remains historical evidence for its recorded date.
Delivery now proceeds sequentially on `mhcs-core` `main`, including Member,
Operator, Image Gateway, and shared administration. MPIPS remains a separate
repository. Its processing service/network boundary is private and is the only
internal network service boundary.

## Authority and relationship

Work Packages remain the authoritative long-term capability, architecture, security, privacy, interoperability, and requirement roadmap. They preserve requirement assignments, architectural intent, cross-module ownership, non-MVP obligations, deferred decisions, and historical evidence.

MVP tasks define the narrower active implementation sequence for controlled beta delivery. They do not replace, rewrite, renumber, delete, or complete Work Packages. A deferral is valid only when the flow does not require the capability, the unavailable path is not exposed, a temporary control is recorded, a target task or trigger is named, and the deferral does not weaken an exposed security boundary.

Authority order for resolving scope and behavior is:

1. source requirements and their maintained implementation matrix;
2. accepted architecture and Work Package evidence;
3. approved MVP decisions in \`decision-log.md\`;
4. an individual published MVP task; and
5. conversation memory, which is never project authority.

Repository evidence controls implementation status. A task file, route name, class, migration, placeholder, plan, or claim is not proof by itself. Conflicting or stale documentation must be reported, then corrected through the owning documentation workflow; it must not be silently overridden by an MVP task.

## Current delivery topology and integration gate

All remaining MVP delivery occurs sequentially on `mhcs-core` `main`:

```text
mhcs-core / main
  - Member
  - Operator
  - Image Gateway
  - shared administration

MPIPS
  - separate repository
  - separate processing service
  - private runtime/network boundary
  - only internal network service boundary
```

This is still one modular monolith; the topology does not create separate
application services or change module ownership. Final MVP planning still
requires focused integration verification for capture acceptance and retries,
MPIPS conversion, authorization, idempotency, failure handling, and the
remaining report/publication boundaries. MVP completion, Work Package
completion, production readiness, and release approval remain separate claims.

## Active controlled-beta components

The initial beta contains four application-facing components:

- Member Portal;
- Operator Portal;
- Image Gateway module and workers; and
- a shared administrator interface composed of module-owned administration
  areas.

The fourth item is an application interface over the modular `mhcs-core`
application, not a new business domain that owns Member, Operator, or Image
Gateway records. It may use the shared Filament `/admin` surface where
applicable; this documentation does not claim that the surface is fully
implemented or that separate panels or URLs exist.

Administration ownership rule: Member, Operator, and Image Gateway own their
respective administrative resources, authorization, actions, state
transitions, configuration, projections, and audit behavior. Shared navigation
and genuinely shared platform primitives do not transfer that ownership.
Administration must use the owning module's application boundaries; a generic
administrator must not directly edit unrelated module tables.

There is no Doctor Portal or internal MHCS doctor workflow in this MVP. Teleradiology physicians and reporting services are external participants or systems. Manual Operator report handling is the fallback until a supported automated contract is approved and implemented.

## Required consumption

Every future MVP task must read all six documents before planning or changing behavior:

\`\`\`text
docs/mvp/README.md
docs/mvp/beta-scope.md
docs/mvp/beta-gap-register.md
docs/mvp/roadmap.md
docs/mvp/decision-log.md
docs/mvp/work-package-status.md
\`\`\`

The task must reconcile its baseline, component, vertical flow, exclusions, Work Package foundations, accepted gaps, exposed interfaces, ownership and authorization boundaries, focused tests, documentation changes, full-verification trigger, stop conditions, and prohibited unrelated work. It must state any gap entries closed, changed, or created.

Gaps are maintained in \`beta-gap-register.md\`. A gap is not closed because code exists; closure requires repository evidence and the verification required by the owning task. Decisions that affect shared authentication, UUID strategy, module ownership, privacy, deployment, role semantics, interoperability, teleradiology assumptions, requirement assignments, or another active task's shared interface must stop and be reported for owner review.

## Historical MVP pivot baseline

This pivot uses planning baseline \`bc300e158a790a7311c64eb7b20e8e81d4e3ec41\`. The execution commit that published this task is \`1960585472e13e78c8136280d8a76f7a9ad76a30\` on \`main\`. At the MVP pivot baseline, repository evidence included the WP-01/WP-02 foundations and the then-current WP-04 Member identity foundation. Current implementation status is recorded in `docs/mvp/work-package-status.md` and must be verified from repository evidence; this documentation does not claim long-term Work Package completion.

The online-registration source/state path exists in the Member identity boundary but is not approved for MVP exposure. No public or online registration route may depend on it during the initial beta. It remains unwired and is tracked in the gap register.
