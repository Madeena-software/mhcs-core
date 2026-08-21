# Prestige production rehearsal-data evidence

**Date:** 2026-08-21
**Status:** sanitized aggregate evidence; bounded operational fixture only
**Repository main:** `841465faac4e8b1dd3670103052a9c4f075bfd04`
**Accepted executable/runtime revision:** `827b59dd81516e44c0ec10e7afb9b6b804e81226`

This record reconciles the owner-approved Prestige rehearsal fixture against
independently verified GitHub Actions metadata and sanitized workflow outputs.
It contains no Member identifiers or production payloads.

## Verified Actions runs

| Stage | Workflow | Run ID | Conclusion | Head revision |
|---|---|---:|---|---|
| Checkpoint-1 deploy | Deploy to Production (Swarm Mode) | `32430059385` | success | `827b59dd81516e44c0ec10e7afb9b6b804e81226` |
| Checkpoint-1 verification/preflight | Verify Production | `32430507692` | success | `841465faac4e8b1dd3670103052a9c4f075bfd04` |
| Prestige APPLY | Apply Prestige Production Data | `32431190544` | success | `841465faac4e8b1dd3670103052a9c4f075bfd04` |
| Final canonical verification | Verify Production | `32431312696` | success | `841465faac4e8b1dd3670103052a9c4f075bfd04` |

## Sanitized observed results

- Production revision matched the accepted runtime revision and the deployed
  health state was healthy.
- Swarm and manager checks passed; local ingress returned HTTP 200; Laravel
  bootstrap and read-only database checks passed.
- The sanitized member-account verification reported 37 Prestige Users, 37
  linked Members, 37 active accounts, 37 login-enabled accounts, and exact
  linkage.
- The pre-reset condition passed, a fresh mandatory reset backup was verified,
  and the bounded Prestige production-data operation passed.
- The final fixture contains three schedules with these `Asia/Jakarta` target
  windows:

  - A: `2026-08-20 00:00:00` → `2026-08-27 00:00:00`
  - B: `2026-08-27 00:00:00` → `2026-08-28 00:00:00`
  - C: `2026-08-28 00:00:00` → `2026-08-29 00:00:00`

- Each target has `quota=37` and `confirmed=37`.
- Aggregate canonical verification reported:

  ```text
  schedule_count=3
  bookings=111
  distinct_members=37
  member_sets_equal=true
  charges=111
  reversals=0
  old_14_absent=true
  old_26_absent=true
  old_27_absent=true
  old_28_absent=true
  verification_passed=true
  ```

The 37-member set is the same fixed cohort across all three targets. This is a
Prestige-specific fixture exception. It does not change normal Member booking
rules, does not establish generic B2B-import completion, and does not close
complete production, security, privacy, release, or WP-28 conformance.

No credential value or identifying information is recorded here. This
documentation task performed no production mutation.
