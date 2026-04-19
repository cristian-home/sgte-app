---
name: service-retroactive-entry-gating
type: fix
scope: services
status: completed
priority: medium
created_date: 2026-04-19
completed_date: 2026-04-19
srs_refs: [REQ-009]
migration_strategy: modify-existing
---

# Gate "create service as Cerrado" to truly retroactive entries

## Description

Surfaced by post-audit workflow review of the 2026-04-19 cross-role UX/QA audit.

`ServiceStoreRequest` currently lets any operator create a service with `service_status = closed` + `actual_start_time` + `actual_end_time` in a single POST, regardless of `service_date`. This bypasses the driver confirm-start / confirm-end chain entirely — the activity_log just records "admin created service" without distinguishing a back-filled historical record from a live-executed service someone shortcut.

For REQ-009 (accounting immutability + audit traceability) the two flows need different provenance. Proposed rule:

- If `service_date >= today`, force `service_status = open` on create; reject `actual_start_time` / `actual_end_time`. The driver workflow is the only path to `closed`.
- If `service_date < today`, allow `closed` + actual times but require a non-empty `manual_entry_justification` string. Persist it on the services row (new column) and tag the activity_log entry with `source: retroactive_entry`.

Mirrors the executed-day justification pattern already used for `Día Ejecutado` edits.

## Acceptance Criteria

- [x] `ServiceStoreRequest` enforces the date-based rule + requires `manual_entry_justification` for past-date closed entries; Pest coverage for both branches (today-future → rejected, past-date with justification → accepted, past-date without → 422).
- [x] `services.manual_entry_justification` column added (modify the primary `create_services_table` migration) and activity_log entries for retroactive creates include a `source=retroactive_entry` property so filtering in `/audit-log` distinguishes them.
