# Doctor module

Doctor owns the Radiologist v1 eligibility, temporary assignments, clinical quality,
independently authored report revisions and immutable internal finalization.
Admin presents these commands; monitoring does not grant assignment or credentialing.
Explicit `doctor.radiologist-workspace.issue`, `.revoke`, `.reassign` and
`.credential` capabilities and active `doctor_workspace_managers` facility records
are required. No roles or permissions are granted automatically. Credentialing and
sensitive assignment changes require the acting administrator's password again.

The current clinical reporting-source inventory is `doctor-workspace-v1`:
`doctor_workspaces` is the explicitly initialized clinical aggregate,
`doctor_report_versions` holds immutable authored content, and
`doctor_finalizations` binds one final report per case to its actual author,
finalizer, handoff, content digest, session and explicit internal attestation.
Existing Image Gateway AI reports are processing artifacts, never clinical reports.
Adding another clinical reporting source requires coordinated writers and extending
this inventory; absence of a report or the guard registry alone is not evidence.

`WorkspaceClinicalStatusSource` replaces the unavailable adapter only for registered,
current, consistent reporting aggregates. Unknown/unregistered/inconsistent state
still denies technical reopen. Every clinical command and assignment locks the
current Operator handoff before the Doctor guard; source verification, report,
history, status and required audit share a transaction. Protected responses recheck
current authorization, source integrity and durable audit before sending headers,
without holding a lock during network output. Output already authorized in flight
cannot be recalled.

Access defaults to 24 elapsed hours, at most 72, with no automatic renewal.
Eligibility must remain current for the exact facility, service and actual embedded
CR/DX modality. Reassignment invalidates old sessions and starts an empty independent
draft while preserving prior authorship/history. Each finalization verifies the
current password inside the action, with throttling and the session, generation,
case/source/handoff and expected revision checked again. No reusable challenge or
generic recent-password flag grants finalization. Authorized retries may identify
the same committed operation. Finalization does not extend access.

This is an internal clinical attestation, not certified signing. Member publication,
amendments, repeat execution, AI content access and financial actions are excluded.
Development fixtures establish no real professional or institutional authority.
