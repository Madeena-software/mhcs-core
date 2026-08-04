# MHCS Core — Repository Entry Point

Before planning, reviewing, modifying files, generating code, or running commands:

1. Read `.agents/AGENTS.md` completely.
2. Follow it as the canonical repository-wide agent contract.
3. Inspect the current repository state before making claims about existing behavior.
4. Load only the context, skills, roles, tasks, and evidence relevant to the current request.
5. Stop and report the issue if a required instruction, context, skill, task, or evidence source cannot be read.
6. Do not guess missing architecture, clinical, financial, security, authorization, or product requirements.

Explicit user instructions and higher-priority runtime instructions take precedence over repository files.

## MHCS context routing

Read `.agents/context/project.md` for repository-wide architecture, technology choices, module boundaries, cross-module communication, security, transactions, deployment, and external integrations.

Read only the affected module context:

* Member: `.agents/context/modules/member/project.md`
* Operator: `.agents/context/modules/operator/project.md`
* Doctor: `.agents/context/modules/doctor/project.md`
* Image Gateway: `.agents/context/modules/image-gateway/project.md`

For cross-module work, read the context of every affected module before changing behavior.

For member-facing labels, navigation, statuses, notifications, onboarding, explanatory copy, report summaries, and user journeys, read:

`.agents/context/ui-language.md`

For UI layout, styling, and visual components, inspect the applicable approved references under:

`.agents/context/design/`

Do not invent a new visual system when an approved MHCS design reference exists.

## Task routing

Files under `.agents/tasks/` are versioned execution contracts and are not executed automatically.

Execute a task only when the user explicitly identifies that task or explicitly requests execution of a specific published task.

Before task execution:

1. Read `.agents/skills/agent-task/SKILL.md` completely.
2. Follow its Execute procedure.
3. Validate the identified task using the validator required by that skill.
4. Stop if validation fails or the required validator is unavailable.
5. Follow the task's runtime inputs, scope, iteration limit, approval gates, acceptance criteria, verification requirements, and output contract.
6. Do not edit a published task file to store runtime values, progress, command output, or results.
7. Report success only when all required acceptance criteria and verification checks pass.

## Framework boundary

Do not modify files marked with:

`<!-- antigravity-code-agent-template:managed -->`

during ordinary product implementation.

Modify managed framework files only when the user explicitly requests maintenance or customization of the Antigravity framework.

Project-specific context, task definitions, and other files intentionally created for MHCS may be added or revised through their applicable framework procedures.

## Evidence boundary

Files under `.agents/context/` describe approved requirements, constraints, and target behavior.

They are not proof that the corresponding behavior is already implemented.

Determine current behavior from repository evidence, including source code, migrations, configuration, tests, dependency manifests, command output, and version-control state.

Do not claim implementation or completion without relevant verification evidence.

Do not silently resolve material conflicts between approved context and repository behavior. Report conflicts affecting architecture, security, clinical behavior, financial behavior, authorization, privacy, or data integrity.
