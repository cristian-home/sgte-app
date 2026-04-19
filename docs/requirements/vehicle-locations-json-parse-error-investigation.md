---
name: vehicle-locations-json-parse-error-investigation
type: fix
scope: gps
status: pending
priority: low
created_date: 2026-04-19
completed_date:
srs_refs: [REQ-010]
migration_strategy: new
---

# Investigate stale JSON.parse errors on /vehicle-locations

## Description

Surfaced by the 2026-04-19 cross-role UX/QA audit (severity: Orthogonal, category: stale observation).

Initial `browser-logs` probe at audit start showed several consecutive unhandled promise rejections on `/vehicle-locations`:

```
Unhandled Promise Rejection SyntaxError JSON.parse: unexpected character
at line 1 column 1 of the JSON data null
```

The audit could not reproduce the error on a fresh visit + filter click during the sweep — so these may be stale from a prior interaction that's no longer in the codebase (the vehicle-locations pages were rebuilt in the gps-tracking requirement). Worth a dedicated investigation pass to either:
1. Reproduce and fix, OR
2. Confirm definitively that the old-build interaction is impossible in current code and document the conclusion.

See `docs/audits/2026-04-19-cross-role-audit.md#stale-json-parse-errors` for the original observation.

## Acceptance Criteria

- [ ] Fresh `migrate:fresh --seed` DB; visit `/vehicle-locations` as admin + operator; exercise every filter (vehicle combobox, desde/hasta date pickers), pagination, and column sort. Assert no unhandled rejections in `mcp__laravel-boost__browser-logs`.
- [ ] If reproducible, patch + add a Dusk scenario that would have caught it. If not reproducible, close as "not reproducible — old build artifact".
