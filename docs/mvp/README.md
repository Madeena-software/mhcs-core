# MHCS Core controlled-beta MVP

This directory is the repository source of truth for the active controlled-beta delivery sequence. It records scope, decisions, gaps, roadmap order, and the evidence-based relationship between MVP tasks and the long-term Work Package roadmap.

This is planning documentation. It does not prove that a feature is implemented, that a beta is deployed, or that production readiness has been approved.

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

## Baseline

This pivot uses planning baseline \`bc300e158a790a7311c64eb7b20e8e81d4e3ec41\`. The execution commit that published this task is \`1960585472e13e78c8136280d8a76f7a9ad76a30\` on \`main\`. The current repository evidence includes the WP-01/WP-02 foundations and the current WP-04 Member identity foundation; this documentation does not claim long-term Work Package completion.

The online-registration source/state path exists in the Member identity boundary but is not approved for MVP exposure. No public or online registration route may depend on it during the initial beta. It remains unwired and is tracked in the gap register.
