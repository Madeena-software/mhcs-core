# Centralized Study Worklist — V7 and A9 record

**Recorded:** 2026-10-13

**V7:** PASSED — `accepted_with_documented_low_risk_limitation`

**A9:** PASSED for bounded synthetic-development acceptance

**R8:** Not applicable

**G10:** NOT APPROVED

**Accepted implementation revision:** `36d689c05e3fab1f5cff909439a7b68aff7cb205`

## Identity and attribution

- Repository: `Madeena-software/mhcs-core`.
- Integration and implementation baseline:
  `c63577f2f677998ebbcc88ff0f407db028ffb539`.
- Governing [task](../../../.agents/tasks/centralized-study-worklist.md):
  `fe8753490d361702e034a364240cb104e779c22c` (version 1.0, Validated/Published).
  [Immutable task content](https://github.com/Madeena-software/mhcs-core/blob/fe8753490d361702e034a364240cb104e779c22c/.agents/tasks/centralized-study-worklist.md).
- Independently reviewed and accepted implementation:
  [36d689c05e3fab1f5cff909439a7b68aff7cb205](https://github.com/Madeena-software/mhcs-core/commit/36d689c05e3fab1f5cff909439a7b68aff7cb205).
- Published [execution evidence](centralized-study-worklist-execution.md),
  [fixed at the accepted implementation](https://github.com/Madeena-software/mhcs-core/blob/36d689c05e3fab1f5cff909439a7b68aff7cb205/docs/mvp/evidence/centralized-study-worklist-execution.md).
- Integration branch: `task/centralized-study-worklist-planning`.

The human handoff “MHCS — Integrate Accepted Centralized Study Worklist into
Main” supplies the external independent ChatGPT Planner/Reviewer verdict above.
That Reviewer inspected the baseline, immutable task, implementation diff,
domain-owned service contracts, authorization controls, tests and published
execution evidence. The Reviewer did not rerun the suites or obtain CI results.
This Executor records the supplied decision without new self-acceptance,
invented reviewer signatures, additional Product Owner approvals or a claim
that the external verdict is a submitted GitHub APPROVED review.

At recording preflight, fetched `main` matched the integration baseline and
the fetched task branch matched the accepted implementation. The governing
task content was unchanged, its ancestry was verified, and the accepted
baseline diff contained exactly the 21 files listed in the execution record.

## Accepted scope and AC disposition

The accepted journey is eligible Admin login → retained facility-session Board
or navigation → `/admin/study-worklist` → verified managed facility/local date
and one persisted session → minimized X-ray intake references and persisted
technical states → manual refresh and pagination. Each page contains at most
25 admissions, including intakes awaiting capture and progressed intakes.
Member, Operator and Image Gateway retain their respective owner contracts,
association validation and fresh authorization. No domain responsibility is
transferred to the Admin presentation surface.

These dispositions record the supplied AC1–AC8 verdict within the immutable
task's synthetic-development boundary; they do not amend its criteria.

| Criterion | Accepted disposition and evidence boundary |
|---|---|
| AC1 | Bounded Board/worklist journey, verified selection, no-capture and progressed intakes, stable pagination beyond 25 rows, refresh and distinct empty/denied/unavailable states. |
| AC2 | Persisted receipt, processing, DICOM-record and AI-job states remain separate; direct DICOM does not require legacy NPZ flags. Unknown/inconsistent states withhold. AI-ready remains unverified, never clinical completion or publication. |
| AC3 | Member facility/session/booking, Operator ticket/admission and Gateway capture/study/job integrity are validated through owner contracts; corrupt, duplicate, orphaned and stale associations withhold. |
| AC4 | Exact fresh four-capability and eligible-Admin authorization, HTTP/Livewire revocation and forged-scope protection, owner-read bypass prevention and withholding before serialization. |
| AC5 | Only approved operational references/context/states reach HTML, JSON, snapshots, audit and logs; sentinel identities, findings, private objects and URLs remain absent. No report-content read, image access, remote call or worker dispatch. |
| AC6 | Minimized attributable read audit commits before disclosure; source, integrity, append, commit and authorization failures withhold. Only necessary audit writes occur, with no domain/outbox/job mutation. |
| AC7 | Indonesian localized status/caution copy, labeled keyboard-operable controls, observation time and verified facility-local date/session semantics including midnight, overnight and DST cases. |
| AC8 | Accepted within bounded synthetic development on the published SQLite, scoped MySQL, HTTP/Livewire, Chromium and static/architecture/security evidence, with the combined-browser limitation below. Full-suite and CI certification are not established. |

## Approved disclosure and audit boundary

SW-D1 and SW-D2 remain the explicit Product Owner decisions recorded in the
governing task under affirmed internal product, technical and security/privacy
policy authority for synthetic development only.

- **SW-D1:** a fresh eligible internal Admin must hold `member.admin.access`,
  `member.schedule.read`, `member.booking.read` and `operator.audit.read` for
  intake/status disclosure in one verified managed-facility session, including
  authorized historical sessions. Panel access, role name, manage-only access
  or possession of a selector/reference is insufficient. Public values are
  restricted to session/ticket references, optional study display reference,
  receipt/processing/DICOM-record/AI-job states and approved facility/session
  context. Operational references can reveal participation; this approval
  is limited to the stated synthetic-development boundary.
- **SW-D2:** each domain validates its owned relationships through narrow
  contracts. Minimized attributable operational read audit commits before
  successful disclosure, with fresh authorization and audit-only database
  effects. Authorization, source, integrity or audit failure withholds data
  without inventing a successful empty result.

No patient identity fields, findings, clinical conclusions, report/PDF contents,
private object references, locators or internal row UUIDs are approved public
row fields. DICOM-record availability is not proof of storage reachability.
AI output readiness is not radiologist approval, diagnosis or patient publication.

## Existing verification evidence

These are previously reported local Executor results from the unchanged
execution record. Neither the external Reviewer nor this acceptance-recording
run reran the application suites. Local results are not GitHub CI.

| Check | Published result |
|---|---|
| Final focused SQLite / HTTP / Livewire worklist | 52 passed; 2,317 assertions; exit 0. |
| SQLite Admin / architecture-security / affected ingestion-AI regression | 295 passed; 1 skipped; 6,903 assertions; exit 0 (296 total). |
| MySQL 8.4.11 final worklist / Board | 76 passed; 3,085 assertions; zero skips; exit 0. |
| MySQL 8.4.11 affected ingestion / AI | 65 passed; 619 assertions; zero skips; exit 0. |
| Chromium worklist, separate | 2 passed; 30 assertions; exit 0. |
| Chromium Board, separate | 5 passed; 78 assertions; exit 0. Original and detached exact-baseline Board runs also passed separately. |
| Changed PHP / static preservation | 18 PHP paths syntax-checked; focused Pint, whitespace and translation/forbidden-surface inventory checks passed. |

The SQLite regression ran the preceding 51-worklist-case revision; the final
52-case focused run covers the subsequently strengthened audit tests. The
SQLite skip was the MySQL-only first X-ray publication concurrency case and
is not reclassified as passing here. The execution record retains commands,
synthetic isolation, setup corrections, unsuccessful attempts and backend
details. Disposable MySQL used temporary in-memory storage; this does not
certify crash/disk durability or production throughput.

At recording preflight, the accepted implementation had zero GitHub check runs
and zero commit statuses. Combined status `pending` with zero statuses denotes
absence of CI evidence, not a pending test or a passing CI result.

## Accepted limitation and unresolved dependencies

Two combined Worklist/Board Chromium runs encountered intermittent
`ResizeObserver loop completed with undelivered notifications` failures in an
existing Board management/registration journey. Separate Worklist and Board
runs passed; exact-baseline Board runs also passed separately. The combined-run
failure remains unexplained. Combined browser verification is not passing,
and no root cause is declared resolved or test expectation suppressed.

The external Reviewer accepted this documented low-risk limitation for bounded
synthetic-development acceptance only. It is not a production waiver or
authorization to repair the browser journey during integration.

- Installed-versus-locked dependency drift remains: Filament 5.7.8 / 5.7.5 and
  CommonMark 2.10.0 / 2.9.0. Dependencies and lockfiles remain unchanged;
  lock-aligned certification is outstanding.
- No full application suite, full MySQL migration/rollback probe, all Operator
  browser suites or CI run is established for this slice. Other previously
  reported browser failures remain outside this acceptance.
- Operational audit retention/access, deletion/holds, external privacy/legal,
  hospital/clinical, real-patient and environment/release obligations remain
  separate. APP-004 clinical retention is not extended by this record.
- MVP-GAP-028/029/031/035/036, temporary clinical credentials, clinical
  finalization, patient/result publication and AI safety/provenance gaps remain
  open. No AI report generator, clinical content or real-data use is accepted.

## Integration and release boundary

Under A9, `36d689c05e3fab1f5cff909439a7b68aff7cb205` is the immutable accepted
development implementation for this scope. This later acceptance-only commit
does not replace that implementation revision. The governing task, application
source, tests and historical execution evidence remain unchanged by recording.

The current human handoff explicitly authorizes publication of this record,
one PR into `main`, necessary review-state operations and a conditional
history-preserving merge retaining the exact task, implementation and recording
commits. Integration must independently verify the accepted diff, current
base/head, GitHub policies and absence of unauthorized deployment/production
side effects. Missing checks or reviews are not passing checks or approvals.
Any material failed integration condition requires stopping for Planner/Reviewer.
PR #8 and all branches must remain preserved.

**G10 remains NOT APPROVED.** Development acceptance and integration are not
production readiness or release authorization. No deployment, production-data
operation, schema/dependency/grant change, clinical finalization, patient
publication or Radiographer Workspace implementation is authorized by this run.
