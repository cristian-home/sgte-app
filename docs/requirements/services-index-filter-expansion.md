---
name: services-index-filter-expansion
type: feat
scope: services
status: pending
priority: low
created_date: 2026-04-19
completed_date:
srs_refs: []
migration_strategy: new
---

# Expand /services index filters to cover contract, driver, vehicle, municipality, date range

## Description

Surfaced by post-audit workflow review of the 2026-04-19 cross-role UX/QA audit.

The `/services` index currently exposes two filters (`Estado`, `Método de pago`) plus free-text search over origen/destino/grupo. With 55+ services visible today and a working day typically producing 20-50 more per week, the index becomes hard to use for an operator triaging "which services today are on Contrato X" or "which services is Conductor Y running this week." The current workaround is Ctrl+F or exporting.

Proposed additions (reuse existing shared components — `ThirdPartyCombobox`, driver / vehicle comboboxes already shared from the Blueprint rebuilds):

- `contract_id` filter (Combobox)
- `driver_id` filter
- `vehicle_id` filter
- `origin_municipality_id` + `destination_municipality_id` filters (MunicipalityCombobox already exists)
- Date range (desde / hasta)
- Optional preset buttons: "Hoy", "Esta semana", "Pendientes de cerrar"

Wire through `spatie/laravel-query-builder` — all of these correspond to indexed foreign keys so performance is a non-issue.

## Acceptance Criteria

- [ ] `/services` index filter bar includes contract + driver + vehicle + origin/destination municipality + date range filters, all functional via `AllowedFilter::exact()` / `AllowedFilter::callback()` in `ServiceController::index`.
- [ ] Preset buttons "Hoy" / "Esta semana" pre-populate the date range; Dusk test covers at least contract filter + date range filter narrowing the result set.
