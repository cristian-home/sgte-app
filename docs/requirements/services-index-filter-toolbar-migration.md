---
name: services-index-filter-toolbar-migration
type: fix
scope: services
status: completed
priority: low
created_date: 2026-04-19
completed_date: 2026-04-19
srs_refs: []
migration_strategy: new
---

# Move the /services index filters into the existing DataTable toolbar

## Description

Follow-up / correction to `services-index-filter-expansion` (2026-04-19).

In that pass the audit called for adding Contrato / Conductor / Vehículo / Municipio Origen / Municipio Destino / Desde / Hasta filters to `/services`. The implementation shipped them as a stacked `<Select>` bar ABOVE the DataTable instead of reusing the existing `DataTableFacetedFilter` pattern that already powers the toolbar's `+ Estado` and `+ Método de pago` buttons.

Result: two inconsistent filter UIs on the same page (red-boxed stacked selects + green-boxed toolbar filters in the screenshot the user flagged). The correct pattern is the toolbar one.

This requirement migrates the new filters into the toolbar, adds a `DataTableDateRangeFilter` that matches the `DataTableFacetedFilter` styling for the Desde/Hasta pair, moves the preset buttons (Hoy / Esta semana / Pendientes de cerrar) inline into the toolbar row, and deletes the stacked-select bar. The filters become multi-select (matching `DataTableFacetedFilter`'s native behavior) since `spatie/laravel-query-builder`'s `AllowedFilter::exact` accepts comma-separated values and runs `WHERE IN` — a small UX upgrade for free.

Out of scope: the other index pages (vehicles, drivers, third-parties, contracts, invoices, service-incidents, vehicle-locations, fuecs, audit-log). Their toolbars predate this sweep and use different patterns; aligning them is a separate requirement if product wants it.

## Acceptance Criteria

- [x] The stacked `<Select>` bar above `/services` is deleted. Contrato / Conductor / Vehículo / Municipio Origen / Municipio Destino render as **multi-select** `DataTableFacetedFilter` buttons inline in the existing DataTable toolbar next to `+ Estado` and `+ Método de pago`. Picking two values on any filter narrows the result set using the `WHERE IN` semantics of `AllowedFilter::exact`.
- [x] A new `DataTableDateRangeFilter` component is added to `resources/js/components/data-table/`. It renders a toolbar-style `+ Rango de fechas` button (dashed outline, PlusCircle icon, badge when any value is set) that opens a popover with Desde + Hasta `<input type="date">` fields and a "Limpiar" action. On change it writes `filter[date_from]` / `filter[date_to]` through the same `setFilter` hook path the faceted filters use.
- [x] The three preset buttons (Hoy / Esta semana / Pendientes de cerrar) render inline in the same toolbar row — small outline buttons that invoke the existing `applyPreset` behavior (set date_from+date_to or set service_status=open).
- [x] Pest regression: `GET /services?filter[contract_id]=<a>,<b>` returns services belonging to EITHER contract (confirms multi-select works end-to-end through `AllowedFilter::exact`).
- [x] Dusk regression on `/services`: click `+ Contrato` → pick two contracts → table narrows; click `+ Rango de fechas` → fill Desde+Hasta → table narrows; click `Hoy` preset → URL query carries `filter[date_from]` and `filter[date_to]` matching today.

## Technical Specification

### Data Model

No schema changes.

### Enums

No changes.

### Routes

No changes. `GET /services` already accepts the filter params introduced by `services-index-filter-expansion`.

### Permissions

No changes.

### Pages / Components

| Path | Status | Description |
|---|---|---|
| `resources/js/components/data-table/data-table-date-range-filter.tsx` | **NEW** | Toolbar-style date-range popover matching the `DataTableFacetedFilter` visual language. Props: `{ name: string, label: string, from: string, to: string, onChange: (range: { from: string, to: string }) => void }`. Emits an object with both fields each change so the consumer can push through `setFilter('date_from', [from])` + `setFilter('date_to', [to])` together. |
| `resources/js/components/data-table/data-table-toolbar.tsx` | MODIFIED | Accept a new `extraFilters?: React.ReactNode` prop rendered inline after the faceted-filter map, before the "Limpiar" button. Accept a new `leadingActions?: React.ReactNode` prop rendered inline at the end of the left cluster (for the preset buttons). |
| `resources/js/components/data-table/data-table.tsx` | MODIFIED | Pass through the two new props to `DataTableToolbar`. |
| `resources/js/pages/services/index.tsx` | MODIFIED | Delete the `<ServicesIndexFilters>` render. Build a `FilterDefinition[]` from `filterContracts / filterDrivers / filterVehicles / filterMunicipalities` and concatenate onto the existing `serviceFilters`. Pass the `DataTableDateRangeFilter` and the three preset `Button`s through `extraFilters`. Adjust `applyPreset` to stay behavioral parity (today / this-week / open_only). |
| `resources/js/components/services/services-index-filters.tsx` | **DELETED** | Superseded by the toolbar integration. Remove the file and its imports. |

### URL contract

No change from `services-index-filter-expansion`. Filter params continue to flow through `filter[contract_id]`, `filter[driver_id]`, `filter[vehicle_id]`, `filter[origin_municipality_id]`, `filter[destination_municipality_id]`, `filter[date_from]`, `filter[date_to]`, `filter[service_status]`, `filter[payment_method]`. Multi-select faceted filters continue to join values with `,` (existing `useServerTable` behavior).

## Migration Strategy

**new** — no migrations involved. This is a frontend refactor + one Pest regression; the backend filter surface was finalized in `services-index-filter-expansion`.

## Tasks

### Backend

- [ ] None. Verify via Pest that `AllowedFilter::exact('contract_id')` handles `filter[contract_id]=<a>,<b>` by generating `WHERE contract_id IN (a, b)`.

### Frontend

- [ ] Create `resources/js/components/data-table/data-table-date-range-filter.tsx`:
  - Component name `DataTableDateRangeFilter`, `'use no memo'` at top.
  - Props: `{ label: string, from: string, to: string, onChange: (range: { from: string, to: string }) => void }`.
  - Render a `<Popover>` with a `<PopoverTrigger asChild>` wrapping a `<Button variant="outline" size="sm" className="h-8 border-dashed">` showing `<PlusCircle />` + `{label}`.
  - When `from !== ''` or `to !== ''`, show the same `<Separator /><Badge variant="secondary" className="rounded-sm px-1 font-normal">…</Badge>` pattern as `DataTableFacetedFilter`. Badge text: `${from || '…'} → ${to || '…'}`.
  - `<PopoverContent className="w-64 p-3" align="start">` contains two `<div className="space-y-1">` blocks, each with `<Label>` + `<Input type="date">`. Bind `value` to `from` / `to`; emit `onChange({ from: e.target.value, to })` and vice versa.
  - Below the two inputs, a full-width `<Button variant="ghost" size="sm" onClick={() => onChange({ from: '', to: '' })}>Limpiar</Button>` rendered only when any value is set.
  - Follow the visual styling of `data-table-faceted-filter.tsx` (dashed outline trigger, vertical Separator before Badge, Popover `align="start"`).

- [ ] Extend `resources/js/components/data-table/data-table-toolbar.tsx`:
  - Add `extraFilters?: React.ReactNode` prop — rendered inline immediately after the `filters.map(...)` block, before the "Limpiar" button.
  - Add `leadingActions?: React.ReactNode` prop — rendered inline after `extraFilters` (before "Limpiar"). Intent: preset buttons that SET filters rather than being filters themselves.
  - Wire both props through without wrapping divs so flex layout continues to work.

- [ ] Extend `resources/js/components/data-table/data-table.tsx`:
  - Add the same two new optional props to `ServerSideProps<TData>` and forward them to `<DataTableToolbar>`.

- [ ] Rewrite `resources/js/pages/services/index.tsx`:
  - Remove the `<ServicesIndexFilters>` render and its import.
  - Keep `filterContracts / filterDrivers / filterVehicles / filterMunicipalities` props from the controller (no backend change needed).
  - Build `FilterDefinition[]` from those arrays, with value = `String(id)` and label per the current `contractLabel` / `driverLabel` helpers (lift them inline or to a local helper in this file).
  - Concatenate with the existing `serviceFilters` and pass the unified array as `filters`.
  - Render `<DataTableDateRangeFilter label="Rango de fechas" from={singleFilter('date_from')} to={singleFilter('date_to')} onChange={({from, to}) => { setFilter('date_from', from ? [from] : []); setFilter('date_to', to ? [to] : []); }} />` inside `extraFilters`.
  - Render three preset buttons inside `leadingActions`:
    - `<Button variant="outline" size="sm" className="h-8" onClick={() => applyPreset('today')}>Hoy</Button>`
    - `Esta semana` → `applyPreset('this_week')`
    - `Pendientes de cerrar` → `applyPreset('open_only')`
  - Keep the existing `applyPreset` behavior (today → date_from=date_to=today; this_week → Monday..Sunday; open_only → service_status=['open']).
  - `onChange` on the date-range filter must push both `date_from` and `date_to` through `setFilter` in the same tick — but because the useServerTable hook races two rapid `setFilter` calls (known limitation documented in `services-index-filter-expansion`), collapse them into a single `navigate` call if you can, or accept that the faster one wins and document it in the commit. The popover UX means both dates are chosen before the user closes it, so one-at-a-time input is acceptable; only call `onChange` when the user edits a field, and always include both current values in the emitted object so the merge is safe.

- [ ] Delete `resources/js/components/services/services-index-filters.tsx`. Remove any remaining imports across the codebase.

### Tests

- [ ] Pest: in `tests/Feature/Http/Controllers/ServiceIndexFiltersTest.php`, add a test `index accepts comma-separated contract_id for multi-select filtering` that creates 3 services on 3 different contracts, requests `filter[contract_id]={a.id},{b.id}`, and asserts the returned paginator `data` contains exactly the 2 services for contracts A and B (not the one on contract C).

- [ ] Dusk: add a test in `tests/Browser/ServicesIndexFiltersTest.php` (extend the existing one — do not create a new file). New scenario: fresh migrate + seed; admin login; visit `/services`. Assert the `<ServicesIndexFilters>` stacked bar text ("Presets:", "Municipio Origen", "Municipio Destino", "Desde", "Hasta") is no longer present as a standalone section above the table. Click the `+ Contrato` toolbar button, pick the two seeded contracts, assert the faceted filter count badge shows "2" and the table narrows. Click the `+ Rango de fechas` button, assert the popover opens with Desde + Hasta inputs, fill them, close, assert the URL carries `filter[date_from]` and `filter[date_to]`. Click the `Hoy` preset, assert the URL reflects today's date.

## Verification

### 1. Interactive verification — Playwright MCP

Reference users: `admin@sgte.app` / `operator@sgte.app` (password `password`).

- [ ] Navigate to `/services`; assert the toolbar row now includes `+ Contrato`, `+ Conductor`, `+ Vehículo`, `+ Municipio Origen`, `+ Municipio Destino`, `+ Rango de fechas`, plus the three preset buttons, alongside the existing `+ Estado` and `+ Método de pago`. The old stacked-select bar should be gone.
- [ ] Click `+ Contrato`; pick two contracts; confirm the faceted badge shows `2` and the table narrows. URL carries `filter[contract_id]=<a>,<b>`.
- [ ] Click `+ Rango de fechas`; set Desde = yesterday, Hasta = today; confirm the faceted badge updates and the table narrows. Click "Limpiar" inside the popover → the badge disappears.
- [ ] Click `Hoy`; confirm the URL has `filter[date_from]=<today>&filter[date_to]=<today>` and the `+ Rango de fechas` badge shows today's date twice.
- [ ] Check browser console with `mcp__laravel-boost__browser-logs` — no errors.

### 2. Backend regression — Pest feature tests

- [ ] `tests/Feature/Http/Controllers/ServiceIndexFiltersTest.php` gains the multi-value `contract_id` test. Run: `./vendor/bin/sail test --compact --filter=ServiceIndexFiltersTest` — all existing + new tests pass. Full suite: `./vendor/bin/sail test --compact` — 700+ tests green.

### 3. UI regression — Laravel Dusk browser tests

- [ ] Extend `tests/Browser/ServicesIndexFiltersTest.php` with the scenarios described above. Run: `./vendor/bin/sail dusk --filter=ServicesIndexFiltersTest`. Each test MUST:
  - Assert no error banners (`[role="alert"]` pattern from other tests), no exception traces, no English leaks.
  - Assert the expected Spanish toolbar text: `Contrato`, `Conductor`, `Vehículo`, `Municipio Origen`, `Municipio Destino`, `Rango de fechas`, `Hoy`, `Esta semana`, `Pendientes de cerrar`.
  - Screenshot at key interactions (`services-index-toolbar-contracts-picked`, `services-index-toolbar-date-range-filled`, `services-index-toolbar-preset-hoy`).

### 4. API endpoints — curl

Not applicable. `/services` is an Inertia route; the JSON refetch path from `useServerTable` is already covered by the Pest test above (which uses `getJson`).

## Dependencies

- Builds on `services-index-filter-expansion` (already merged to `develop`). No other dependencies.

## Notes

### Why delete the stacked-select bar entirely rather than refactor

The bar's layout (5 Select dropdowns across multiple rows) fights the toolbar pattern. Keeping any of it would leave two different filter UIs on the same page. A clean removal in favor of the faceted-filter pattern is the right call per the user's direction.

### Why multi-select is safe

Every role that can land on `/services` already holds `VIEW_SERVICES` (admin, operator, accounting). The backend `AllowedFilter::exact('contract_id')` in spatie/laravel-query-builder natively handles comma-separated values by branching to `whereIn`, so the multi-select upgrade needs zero backend changes — only the Pest test above confirms the contract.

### Known limitation carried over from `services-index-filter-expansion`

The `useServerTable` hook races two rapid `setFilter` calls because its `currentParams` only refreshes after the previous fetch returns. For the new `DataTableDateRangeFilter`, only one `setFilter` fires per field edit (on change of Desde OR Hasta, not both at once), so the race doesn't apply here — users edit one field, see the table update, then edit the other. This is called out explicitly so a future refactor doesn't try to coalesce the two writes without also fixing the hook.

### Component naming

The new filter is `DataTableDateRangeFilter` (not `DateRangeFilter` or `ToolbarDateRange`) to match the `DataTableFacetedFilter` naming. Lives next to it in `resources/js/components/data-table/`.
