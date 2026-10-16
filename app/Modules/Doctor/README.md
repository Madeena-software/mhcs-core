# Doctor module

Doctor owns the Radiologist v1 eligibility, temporary assignments, clinical quality,
independently authored report revisions and immutable internal finalization.
Admin presents these commands; monitoring does not grant assignment or credentialing.
Explicit `doctor.radiologist-workspace.issue`, `.revoke`, `.reassign` and
`.credential` capabilities and active `doctor_workspace_managers` facility records
are required. No roles or permissions are granted automatically. Credentialing and
sensitive assignment changes require the acting administrator's password again.

The internal reporting tables prove only their own consistency. They do not
establish a complete inventory of historical or external clinical sources.
`WorkspaceClinicalStatusSource` therefore explicitly reports incomplete coverage;
the guard records unknown and denies technical reopen even for an issued workspace
without a final report. Positive not-finalized production authority remains unavailable
until an authoritative case/handoff-specific inventory and coordinated source adapter
are established. Test-only complete-source fixtures are not production authority.

Account locks precede the Operator current-handoff lock, followed by the Doctor
case guard, workspace and eligibility locks. Management discovers participating
accounts first, locks them in ID order and rechecks the current assignee after
locking the workspace. A changed discovery fails closed. Login takes eligibility
locks only after the handoff lock. Candidate listings are advisory unlocked reads;
assignment independently locks and revalidates the chosen individual's eligibility.
Protected responses recheck authorization, original integrity and durable audit
before sending headers, without holding locks during network output.

Credential revocation names an existing eligibility revision, whose exact facility,
service and modality are preserved. It requires the credentialing capability,
managed facility, fresh administrator authentication and attributable evidence.
It appends history even if the target is suspended or its eligibility expired;
DICOM inspection is required for verification, not revocation. A superseded revision
is rejected rather than silently revoking a different scope or revision.

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
