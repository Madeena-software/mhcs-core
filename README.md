# MHCS Core

MHCS Core is one Laravel modular monolith containing Member, Operator, Doctor,
and Image Gateway modules.

## Local Operator-to-DICOM rehearsal

Use the existing native Laravel HTTP server, database queue, local filesystem,
and the user's own MPIPS configuration. This is disposable, non-clinical
manual-testing readiness—not deployment, production, or release evidence. Do
not use Docker, Compose, a proxy, real patients, or real credentials.

The local runtime uses `MHCS_PRIVATE_OBJECT_DISK=local` and plain private
objects; production retains private S3. Capture acceptance durably queues MPIPS
through the existing `image-gateway` worker.

The seed supplies five synthetic Members and two already-called, operator-owned
X-ray admissions: one for the primary Operator and one for the second Operator.
Each has a completed prerequisite workflow and no capture set or DICOM. The
other three Members remain at the ordinary earlier-stage flow. Credentials are
written only to ignored `credential.txt` with mode `0600`; never disclose them.

Start the disposable runtime with the commands in
[the local walkthrough](docs/mvp/local-core-walkthrough.md), then record only
redacted evidence in
`docs/mvp/evidence/mvp-local-deployment-readiness.md`.
