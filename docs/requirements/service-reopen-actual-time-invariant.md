---
name: service-reopen-actual-time-invariant
type: fix
scope: services
status: pending
priority: low
created_date: 2026-04-19
completed_date:
srs_refs: [REQ-009]
migration_strategy: new
---

# Define the actual_start_time / actual_end_time invariant across status transitions

## Description

Surfaced by post-audit workflow review of the 2026-04-19 cross-role UX/QA audit.

`Service.service_status` can transition Open→Closed (via driver confirm-end or operator edit) and — with the right permission — Closed→Open (operator edit to unwind an accidental close). The current codebase does not make explicit what happens to `actual_start_time` / `actual_end_time` on the reopen transition. Two failure modes:

1. Reopen retains both `actual_*_time` values: the service now reads as "Open but finished at 15:30", which is semantically nonsense and poisons downstream KPIs (Day Summary totals, invoice calculations, FUEC pre-gen checks).
2. Reopen clears both: you lose the legitimate "driver did start the trip" evidence if the close was a typo on only `actual_end_time`.

Proposed invariant:

- Closed→Open: clear `actual_end_time`, preserve `actual_start_time`. The service is "resumable" — it started but hasn't ended yet.
- Open→Closed (via operator edit, not driver confirmEnd): require both `actual_start_time` and `actual_end_time` to be set, same as create-as-closed.
- Open→Open or Closed→Closed: no-op on the time fields.

Enforce in `ServiceUpdateRequest::prepareForValidation()` (clear on reopen) + `after()` validator (require both on close). Activity-log each transition with the from/to status + preserved/cleared fields.

## Acceptance Criteria

- [ ] `ServiceUpdateRequest` clears `actual_end_time` on Closed→Open transitions and requires both `actual_*_time` fields on Open→Closed; Pest covers all four transition cells (O→O, O→C, C→O, C→C) with explicit assertions on field state after.
- [ ] Activity log entry for the transition records `status_from`, `status_to`, and which time fields were cleared or set.
