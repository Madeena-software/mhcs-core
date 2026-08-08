# MVP-04J Private Vital-Signs Capture Evidence

## Execution boundary

The published task `mhcs-core-mvp-04j-private-vital-signs-capture-v1` was
executed with `TARGET="."` from `/var/www/mhcs-core` on branch `main` against
accepted baseline `c542b07cab53ef93f43a62f491ae06511150f674`. The baseline is an
ancestor of the candidate worktree. The task SHA-256 is
`eec4b83abc715032dfc1abbd48278e792f9b5e2f7e2ae7416998f80895e5abfa`.

The MVP-04I clinical/privacy closure was used as the authority for the
approved bundle and units: blood pressure, temperature, height, weight, and
BMI; `mmHg`, `°C`, `cm`, `kg`, and `kg/m²`; and the missing reasons
`unavailable`, `refused`, and `not_applicable`. No clinical ranges or
thresholds were invented.

No dependency installation, production configuration, real secret, commit,
or push was performed.

## Bounded implementation

- Added the explicit Member-side `OperatorVitalSignsContract` and its
  `Mvp04VitalSignsService` implementation. The Member-owned
  `member_vital_signs_assessments` table stores the longitudinal assessment,
  fixed units, effective time, values, and distinct missing reasons.
- Added the Operator-owned
  `operator_vital_signs_executions` association with performer, site,
  admission, occurrence time, operation ID, and assessment reference.
- Added private GET/POST routes under
  `/operator/basic-examination-worklist/{admission}/vital-signs`. The form is
  claimant-only, does not render Member or booking identity, fixes the
  approved units, and includes the copy `Screening result; not a diagnosis`.
- BMI is calculated on the server from stored height and weight values. A
  mandatory field must contain exactly one value or one approved missing
  reason; BMI has its own missing-reason semantics when it cannot be
  calculated.
- Each submission rechecks the trusted account, Operator permission, active
  site, site correspondence, assigned shift, admission scope, claimant, and
  `in_service` state inside the idempotent transaction. The checked-in booking
  and Member schedule/site correspondence are also revalidated.
- Audit and outbox records contain only neutral identifiers, state, stage,
  performer/site references, and occurrence time. They contain no clinical
  values, units, or missing reasons. Failed audit/outbox writes roll back both
  Member and Operator records.

The slice does not add glucose, cholesterol, uric acid, interview capture,
queue completion, X-ray advancement, Encounter/FHIR resources, profiles,
terminology mappings, validation packages, APIs, external exchange,
Member-facing UI, clinical diagnosis/ranges, retention, deletion,
anonymization, broader access, dependencies, commits, or pushes.

The internal shape is FHIR-aligned only at the observation-like boundary:
stable subject/member reference, performer/execution reference, effective and
occurrence time, value/unit pairs, and explicit missing-data semantics. No FHIR
package, resource, profile, API, or exchange behavior was added.

## Observed verification

| Check | Result |
|---|---:|
| MVP-04J focused suite | 6 tests / 96 assertions |
| MVP-04H prerequisite regression | 6 tests / 73 assertions |
| MVP-04G prerequisite regression | 6 tests / 66 assertions |
| MVP-04F prerequisite regression | 7 tests / 58 assertions |
| MVP-04E prerequisite regression | 6 tests / 61 assertions |
| MVP-04D prerequisite regression | 9 tests / 83 assertions |
| MVP-04C prerequisite regression | 6 tests / 64 assertions |
| MVP-04B prerequisite regression | 16 tests / 84 assertions |
| Operator portal | 8 tests / 63 assertions |
| Operator foundation | 15 tests / 56 assertions |
| WP-02 security | 24 tests / 103 assertions |
| Architecture | 6 tests / 1,597 assertions |
| Foundation feature suite | 9 tests / 42 assertions |
| Member database conformance | 3 passed / 5 skipped / 16 assertions |

Additional checks passed:

- Fresh testing SQLite migration, including both MVP-04J tables.
- PHP syntax checks for all `app`, `database`, `routes`, and `tests` PHP files.
- `vendor/bin/pint --test`.
- `composer validate --no-check-publish --no-interaction`; Composer reported
  existing PHP deprecation notices and confirmed `composer.json` is valid.
- Route listing confirmed the private vital-signs GET and POST routes.
- Task validation for MVP-04J and `git diff --check`.
- Focused tests covered positive capture, fixed units, server BMI, allowed
  missing reasons, mixed value/reason rejection, exact replay, changed-payload
  conflict, non-claimant/revoked/cross-site denial, privacy leakage, and
  audit/outbox rollback.

The full suite reported 212 passing tests and 5 skipped, with one unrelated
pre-existing failure in `tests/Feature/ExampleTest.php`: it expects `/` to
return 200 while the accepted baseline route redirects `/` to `/login` with
302. That baseline mismatch was not changed.

Graphify was refreshed after the final source changes and reported 2,641 nodes
and 6,191 edges. Its MVP-04J query located the focused tests, Member service,
local contract, Operator service, controller, and route boundary, with the
broader traversal truncated by the selected budget. Semantic extraction was
not configured because it requires an API key; no key or dependency was added.
Codebase Memory MCP was refreshed in fast mode for `var-www-mhcs-core`,
reporting 4,466 nodes and 11,568 edges, then queried for the new contract,
service, controller, and Operator submission symbols.

## Outcome and residual scope

Outcome: `succeeded` for the bounded MVP-04J task. The candidate is ready for
owner-controlled commit review against
`c542b07cab53ef93f43a62f491ae06511150f674`. The diff contains only the private
vital-signs schema, local command boundary, Operator capture flow, focused
tests, architecture allow-list update, and this evidence record. No commit or
push was made.

WP-11, WP-12, and WP-17 remain partially implemented. Queue completion and
X-ray workflow, deferred assessment fields, Encounter/FHIR behavior,
Member-facing visibility, public/LCD behavior, production privacy operations,
deployment, and `MVP-GAP-021` remain open.
