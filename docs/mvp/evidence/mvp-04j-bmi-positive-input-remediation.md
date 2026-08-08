# MVP-04J BMI Positive-Input Remediation Evidence

The published task `mhcs-core-mvp-04j-bmi-positive-input-remediation-v1` was
executed with `TARGET="."` against review commit
`6c21b4a667eaab6d90957563c2fc695d7096fbdf` on branch `main`. The task
validated successfully; its SHA-256 is
`4d5dd1e8568575181d910fcd3d7294b85852df18ae753203788d5476bf4dbd24`.

The remediation adds one mathematical input-integrity invariant: supplied
height and weight must be finite and strictly positive. The HTTP controller
rejects zero, negative, and non-finite values before invoking the Operator
service. `Mvp04VitalSignsService::normalize()` independently enforces the
same rule before BMI calculation or Member persistence. No clinical range,
unit, formula, rounding, missing-reason, queue, FHIR, privacy, or access
behavior changed.

Focused regression coverage exercises zero, negative, and `1e309` inputs for
both height and weight at both trust boundaries. Invalid HTTP submissions
produce validation failures with no assessment, execution, audit/outbox
success evidence, or handled idempotency result. Existing positive capture,
approved missing-reason, replay, authorization, privacy, and rollback tests
remain covered.

Verification passed:

- MVP-04J focused suite: 8 tests / 146 assertions.
- MVP-04H prerequisite: 6 tests / 73 assertions.
- Operator portal: 8 tests / 63 assertions.
- WP-02 security: 24 tests / 103 assertions.
- Architecture: 6 tests / 1,597 assertions.
- Fresh testing SQLite migration, Pint, PHP syntax, route listing, Composer
  validation, task validation, privacy/source inspection, Graphify, Codebase
  Memory MCP, and `git diff --check`.

Graphify was refreshed after the source and evidence changes and reported
2,663 nodes and 6,221 edges; its focused query located the remediation task, tests, contract,
and BMI path, with the broader traversal truncated by the selected budget.
Codebase Memory MCP was refreshed in fast mode for `var-www-mhcs-core`,
reporting 4,831 nodes and 12,140 edges, then queried for the controller and
Member normalizer paths. Semantic extraction remains unavailable without a
configured API key; no key or dependency was added.

The candidate diff is ready for owner-controlled review against the review
commit. No commit or push was made.
