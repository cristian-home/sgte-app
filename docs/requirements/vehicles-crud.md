---
name: vehicles-crud
type: feat
scope: vehicles
status: completed
priority: high
created_date: 2026-04-13
completed_date: 2026-04-13
srs_refs: ["REQ-004"]
migration_strategy: new
---

# Rebuild the Vehículos index and show pages

## Description

The `vehicles` module has a usable backend (`VehicleController` with QueryBuilder filters, `VehicleStoreRequest` / `VehicleUpdateRequest`, full test coverage) and real `create.tsx` / `edit.tsx` forms backed by `<VehicleForm />`. However, the **index page** (`resources/js/pages/vehicles/index.tsx`) and the **show page** (`resources/js/pages/vehicles/show.tsx`) are still the Blueprint-generated stubs that render `JSON.stringify(...)` inside a `<pre>` block.

This is a load-bearing gap because:

1. After the 2026-04-13 sidebar rename (commit `b37a90b`), operators see **Gestión → Vehículos** as a first-class entry. Clicking through to a JSON dump makes the new IA look like false advertising.
2. The dashboard's **Alertas de Documentos** panel (commit `7d07d05`) surfaces vehicles with expiring SOAT / RTM / tarjeta de operación, but the alert rows have nowhere to land — clicking them today does nothing.
3. **REQ-004 §3** (alerts at 30 / 15 / 5 days) is partially fulfilled by the dashboard; this requirement closes the loop by making the corresponding vehicle list scannable, filterable, and visually annotated.

The rebuild MUST reuse every existing piece (`VehicleController`, `VehicleForm`, `VehicleCreateDialog`, `DataTable`, `useServerTable`, dashboard `buildDocumentAlerts`) and follow the `services/index.tsx` pattern.

**Out of scope:** rebuilding `vehicles/create.tsx` or `vehicles/edit.tsx` (already real forms), adding a map view, document upload via `spatie/laravel-medialibrary`, and rebuilding the other five Blueprint scaffolds (Drivers, Third-Parties, Contracts, Invoices, Service-Incidents) — each is a separate requirement.

## Acceptance Criteria

- [x] **AC1**: WHEN an admin or operator navigates to `/vehicles` THEN the page renders a paginated `<DataTable>` (not a JSON dump) with columns: **Placa**, **Cód. Interno**, **Tipo**, **Propietario**, **Estado**, **Documentos**, **Acciones**.
- [x] **AC2**: WHEN a vehicle has at least one expired document (SOAT, RTM, or tarjeta de operación) THEN its row background is `bg-destructive/10` AND the corresponding pill in the **Documentos** column renders with the `destructive` Badge variant.
- [x] **AC3**: WHEN a vehicle has at least one document expiring within 30 days AND no expired document THEN its row background is `bg-amber-100/50` (light mode) / `bg-amber-900/20` (dark mode) AND the corresponding pill renders with the `secondary` (warning) Badge variant.
- [x] **AC4**: WHEN a vehicle has all three documents more than 30 days from expiry THEN its row background is unchanged AND all three pills render with the `outline` Badge variant.
- [x] **AC5**: WHEN the user applies the **Estado** filter (Activo / En Mantenimiento / Retirado) THEN only rows whose `vehicles.status` matches the selected value remain visible.
- [x] **AC6**: WHEN the user applies the **Municipio** filter (combobox of all municipalities) THEN only rows whose `vehicles.municipality_id` matches the selected value remain visible.
- [x] **AC7**: WHEN the user applies the **Documentos** filter with value `expired` THEN only rows with `docs_status = expired` are visible; WHEN the value is `expiring_soon` THEN only rows with `docs_status = expiring_soon` are visible; WHEN the value is `ok` THEN only rows with `docs_status = ok` are visible.
- [x] **AC8**: WHEN the user toggles any of the per-document boolean filters (`soat_expired`, `rtm_expired`, `operation_card_expired`) THEN the result set narrows to the specified expiry state. Per-document filters are presented in an "advanced filters" section of the filter bar.
- [x] **AC9**: WHEN the user clicks the **Crear Vehículo** action on the index THEN the existing `<VehicleCreateDialog />` modal opens. WHEN a successful submit occurs THEN the modal closes AND the index refreshes with the new row visible.
- [x] **AC10**: WHEN the user clicks the **Placa** link in any row THEN the app navigates to `/vehicles/{id}` AND the show page renders five sections in this order: **Header card** (placa + código interno + estado badge), **Información General**, **Documentos** (three due dates + days-to-expiry pills, reusing the same `<VehicleDocumentPills />` component), **Propietario** (Empresa propia or tercero card), **Servicios Recientes** (last 5 services for this vehicle).
- [x] **AC11**: WHEN the **Servicios Recientes** card has zero services THEN it renders an empty state message "Sin servicios registrados.".
- [x] **AC12**: WHEN a user clicks any row in the dashboard's **Alertas de Documentos** panel that corresponds to a vehicle (kind `vehicle`) THEN the app navigates to `/vehicles?filter[docs_status]=expired` (when the alert is for an expired document) or `/vehicles?filter[docs_status]=expiring_soon` (when the alert is within 30 days).
- [x] **AC13**: WHEN an unauthenticated user navigates to `/vehicles` or `/vehicles/{id}` THEN they are redirected to `/login`.
- [x] **AC14**: WHEN a driver or accounting user navigates to `/vehicles` or `/vehicles/{id}` THEN they receive a 403 Forbidden response (Driver / Accounting do NOT hold `VIEW_VEHICLES` after Phase A2).

## Technical Specification

### Data Model

**No new tables, no new columns.** The existing `vehicles` table already has every field this requirement needs:

```
vehicles (existing — no changes)
├── id (bigint, PK)
├── internal_code (varchar, unique)
├── plate (varchar, unique)
├── mobile_number (varchar, nullable)
├── brand, line, model_year, engine_number, chassis_number (existing)
├── capacity (integer)
├── type (enum bus | buseta | van | automobile)
├── municipality_id (bigint, FK → municipalities.id, nullable)
├── is_third_party (boolean)
├── third_party_id (bigint, FK → third_parties.id, nullable)
├── soat_due_date (date, nullable)
├── rtm_due_date (date, nullable)
├── operation_card_due_date (date, nullable)
├── status (enum active | maintenance | retired)
├── created_at / updated_at / deleted_at (softDeletes)
```

### Enums

**No new enums.** `App\Enums\VehicleStatus` and `App\Enums\VehicleType` already exist with the expected cases.

### Routes

**No new routes.** The existing `Route::resource('vehicles', VehicleController::class)` (in `routes/web.php`) already provides every endpoint this requirement needs:

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | `/vehicles` | `VehicleController@index` | `auth, verified` | `vehicles.index` |
| GET | `/vehicles/create` | `VehicleController@create` | `auth, verified` | `vehicles.create` |
| POST | `/vehicles` | `VehicleController@store` | `auth, verified` | `vehicles.store` |
| GET | `/vehicles/{vehicle}` | `VehicleController@show` | `auth, verified` | `vehicles.show` |
| GET | `/vehicles/{vehicle}/edit` | `VehicleController@edit` | `auth, verified` | `vehicles.edit` |
| PUT | `/vehicles/{vehicle}` | `VehicleController@update` | `auth, verified` | `vehicles.update` |
| DELETE | `/vehicles/{vehicle}` | `VehicleController@destroy` | `auth, verified` | `vehicles.destroy` |

Authorization is enforced inside each controller action via `Gate::authorize(Permission::*->value)` (see ADR-005 §2).

### Permissions

**No new permissions.** The following already exist in `App\Enums\Permission` and are granted to **Admin** and **Operator** by the `seed_catalog_data` migration after Phase A2:

- `VIEW_VEHICLES` (`vehicles.view`)
- `CREATE_VEHICLES` (`vehicles.create`)
- `UPDATE_VEHICLES` (`vehicles.update`)
- `DELETE_VEHICLES` (`vehicles.delete`)

Driver and Accounting roles MUST NOT hold these permissions.

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Index | `resources/js/pages/vehicles/index.tsx` | **REWRITE.** Replace the JSON `<pre>` with a `<DataTable>` driven by `useServerTable<Vehicle>`. Wire filter dropdowns for Estado, Municipio, Documentos. Wire `<VehicleCreateDialog />` to the "Crear Vehículo" button (existing pattern, keep as-is). Apply row tinting via the DataTable's row-class hook. |
| Show | `resources/js/pages/vehicles/show.tsx` | **REWRITE.** Replace the JSON `<pre>` with five Card sections: header, Información General, Documentos, Propietario, Servicios Recientes. Follow the `services/show.tsx` pattern. |
| Columns | `resources/js/pages/vehicles/columns.tsx` | **NEW.** TanStack `ColumnDef<Vehicle, unknown>[]` array following the `services/columns.tsx` pattern. Each cell formatter is a small inline component. |
| Document pills | `resources/js/components/vehicles/vehicle-document-pills.tsx` | **NEW.** Reusable component rendering three Badge pills (SOAT, RTM, T.O.) given a vehicle. Variant per pill is computed from `due_date` against `today`: `destructive` if expired, `secondary` if within 30 days, `outline` otherwise. Used by both the index column and the show page Documentos card. |
| Create / Edit | `resources/js/pages/vehicles/create.tsx`, `edit.tsx` | **NO CHANGE.** Already real forms backed by `<VehicleForm />`. Out of scope. |
| Dashboard | `resources/js/pages/dashboard.tsx` | **MODIFY.** Wrap each row in the Alertas de Documentos `<ul>` in a `<Link>` whose `href` comes from a new `link` field on the alert payload (added by `DashboardController::buildDocumentAlerts`). |

## Migration Strategy

`new` (formal frontmatter value), but **no migration files are written or modified**. Every column, FK, enum, and permission this requirement needs already exists. The label `new` is used only because the template enum doesn't include a `none` value.

After implementing this requirement, no `php artisan migrate` invocation is required. The Pest feature suite still uses SQLite in-memory and runs unchanged.

## Tasks

### Backend

- [x] **Task B1**: Paginate `VehicleController@index` using `Request::perPage()`.
  - Replace the trailing `->get()` call with `->paginate($request->perPage())` so the response is a `LengthAwarePaginator`.
  - Read the per-page default from the existing `Request::macro('perPage')` (ADR-006 §7) — call it as `$request->perPage()`.
  - Keep the existing `allowedFilters` and `allowedSorts` (`internal_code`, `plate`, `brand`, exact `type`, exact `municipality_id`, exact `is_third_party`, exact `status`).
  - Reference convention: `app/Http/Controllers/ServiceController.php@index`.
  - Update the Inertia render call so the page receives `vehicles` as `PaginatedData<Vehicle>` (matches `services/index.tsx`).

- [x] **Task B2**: Add the `docs_status` filter to `VehicleController@index`.
  - Add a new `AllowedFilter::callback('docs_status', function ($query, $value) { ... })`.
  - Implement three branches:
    - `expired` → `$query->where(function ($q) { $q->whereDate('soat_due_date', '<', now()->toDateString()) ->orWhereDate('rtm_due_date', '<', now()->toDateString()) ->orWhereDate('operation_card_due_date', '<', now()->toDateString()); })`
    - `expiring_soon` → vehicles with at least one document `>= today AND <= today+30` AND no expired document. Implement as a single `whereExists` or compound `where` clause. Make sure it excludes vehicles already classed as `expired`.
    - `ok` → all three documents `> today+30`.
  - Reject any other value via `abort(422)` or by simply ignoring it (prefer ignoring).

- [x] **Task B3**: Add per-document boolean filters to `VehicleController@index`.
  - Add three `AllowedFilter::callback` entries: `soat_expired`, `rtm_expired`, `operation_card_expired`.
  - Each accepts a truthy value (`true`, `1`, `'true'`) and applies `whereDate('{column}', '<', now()->toDateString())`.
  - These compose with `docs_status` via AND. Document the composition behavior in the controller PHPDoc.

- [x] **Task B4**: Expand `VehicleController@show` to load relationships and recent services.
  - Eager-load `municipality.department`, `thirdParty`.
  - Load the last 5 services for this vehicle ordered by `service_date` DESC, then `planned_start_time` DESC, with `driver:id,first_name,first_lastname` and `contract.thirdParty:id,company_name,first_name,first_lastname,is_natural_person`.
  - Pass them to the Inertia page as `vehicle` (full model) and `recentServices` (array).

- [x] **Task B5**: Update `DashboardController::buildDocumentAlerts` to include a navigation link.
  - For each alert row, compute a `link` field:
    - When `kind === 'vehicle'` AND `days_remaining < 0` → `/vehicles?filter[docs_status]=expired`
    - When `kind === 'vehicle'` AND `days_remaining >= 0` → `/vehicles?filter[docs_status]=expiring_soon`
    - When `kind === 'driver'` → `/drivers` (the deferred Drivers rebuild will refine this later; for now it's a placeholder).
  - Add `'link' => $link` to the array shape returned by the closure.
  - Update the docblock for `buildDocumentAlerts` accordingly.

### Frontend

- [x] **Task F1**: Create `resources/js/components/vehicles/vehicle-document-pills.tsx`.
  - Props: `{ vehicle: Pick<Vehicle, 'soat_due_date' | 'rtm_due_date' | 'operation_card_due_date'>, today?: string }` (`today` defaults to `new Date().toISOString().slice(0,10)`).
  - Renders three `<Badge>` pills labeled `SOAT`, `RTM`, `T.O.` in order.
  - Variant logic per pill: if `due_date == null || due_date < today` → `destructive` with a `!` suffix; else if `due_date <= today+30` → `secondary` with a `!` suffix; else → `outline`.
  - Each pill has a `title` attribute showing the actual due date in `es-CO` format (e.g. `"SOAT vence 15/05/2026"`).

- [x] **Task F2**: Create `resources/js/pages/vehicles/columns.tsx`.
  - Export a `columns: ColumnDef<Vehicle, unknown>[]` array following `services/columns.tsx`.
  - Columns:
    1. `plate` — sortable header via `<DataTableColumnHeader />`, cell renders a `<Link>` to `vehicles.show(row.original.id).url` styled `text-primary hover:underline`.
    2. `internal_code` — sortable header, plain text cell.
    3. `type` — header label "Tipo", cell renders the `VehicleType.label` mapping (use the existing `VehicleTypeLabel` from the generated enum or re-derive).
    4. `propietario` (computed, `id: 'propietario'`) — header label "Propietario", cell renders `'Empresa'` when `is_third_party === false`, otherwise the third party's `company_name` or `first_name + first_lastname`.
    5. `status` — sortable header, cell renders a `<Badge>` with variant `default` for `active`, `secondary` for `maintenance`, `outline` for `retired`. Labels in Spanish via the existing `VehicleStatus.label()`.
    6. `documentos` (computed, `id: 'documentos'`) — header label "Documentos", cell renders `<VehicleDocumentPills vehicle={row.original} />`.
    7. `actions` — `<DataTableRowActions editUrl={vehicles.edit(row.original.id).url} onDelete={() => router.delete(vehicles.destroy(row.original.id).url, { preserveScroll: true })} />` wrapped in `<Can permission={Permission.DELETE_VEHICLES}>`.

- [x] **Task F3**: Rewrite `resources/js/pages/vehicles/index.tsx`.
  - Replace the `<pre>` JSON dump with the `services/index.tsx` shape:
    - Import `DataTable`, `useServerTable`, `Permission`, `Can`, `columns`, `Vehicle`, `PaginatedData`, `FilterDefinition`.
    - Define `vehicleFilters: FilterDefinition[]` containing:
      - `status` with options `active / maintenance / retired`.
      - `municipality_id` — populated dynamically from the `municipalities` prop. (If `FilterDefinition` doesn't yet support a typeahead combobox, add a small extension or render a separate `<MunicipalityCombobox />` outside the DataTable's filter row.)
      - `docs_status` with options `ok / expiring_soon / expired`.
      - `soat_expired`, `rtm_expired`, `operation_card_expired` — boolean toggles in an "Filtros avanzados" expandable section. If the existing `DataTable` API doesn't support expandable filter sections, render the booleans as a `<Collapsible>` block above the table.
  - Wire `<VehicleCreateDialog />` to the "Crear Vehículo" button via the existing `useState` pattern. After the modal closes successfully (`onSuccess`), call `router.reload({ only: ['vehicles'] })` to refresh the table.
  - Compute the row class via the DataTable's row-class hook (or a new `getRowClassName(row)` prop on `<DataTable>` — add it if it doesn't exist):
    - Any expired doc → `bg-destructive/10`
    - Any doc within 30 days, no expired → `bg-amber-100/50 dark:bg-amber-900/20`
    - Else → no class
  - Type the page props as `{ vehicles: PaginatedData<Vehicle>, municipalities: MunicipalityOption[], thirdParties: ThirdPartyOption[] }`.
  - Reference convention: `resources/js/pages/services/index.tsx`.

- [x] **Task F4**: Rewrite `resources/js/pages/vehicles/show.tsx`.
  - Five Card sections in this order:
    1. **Header card** — `<CardHeader>` with placa as `<CardTitle className="text-2xl font-mono">`, internal_code as `<CardDescription>`, and an estado `<Badge>` aligned right (use the same variant mapping as the index).
    2. **Información General** — two-column grid: Marca, Línea, Modelo (model_year), Tipo, Capacidad, Número Móvil, Número de Motor, Número de Chasis, Municipio (`municipality.name + ', ' + municipality.department.name`).
    3. **Documentos** — header "Documentos legales", contains a 3-column responsive grid where each cell shows `Label` + `Due date in es-CO format` + days-to-expiry pill (reuse `<VehicleDocumentPills />` for the badge variant logic OR render three labeled subcards each with one pill). At the bottom, a small note "Las alertas usan un margen de 30 días."
    4. **Propietario** — if `is_third_party === false`: render an "Empresa propia" empty state with a Truck icon. If `is_third_party === true`: render the third-party's name (`company_name` for legal person, `first_name + first_lastname` for natural person), `identification_number`, and a link to `/third-parties/{id}` (note: third-parties show page is also Blueprint stubbed — that's fine, the link works).
    5. **Servicios Recientes** — header "Servicios recientes (últimos 5)", contains a small `<Table>` with columns Fecha, Conductor, Tercero, Estado. Empty state "Sin servicios registrados." when `recentServices.length === 0`. Each row's Fecha is a `<Link>` to `services.show(service.id).url`.
  - Type the page props as `{ vehicle: Vehicle & { municipality: Municipality | null, third_party: ThirdParty | null }, recentServices: Service[] }`.
  - Breadcrumbs: `[{ title: 'Vehículos', href: vehicles.index().url }, { title: vehicle.plate, href: '#' }]`.
  - Reference convention: `resources/js/pages/services/show.tsx`.

- [x] **Task F5**: Add row tinting support to `<DataTable>`.
  - Inspect `resources/js/components/data-table/data-table.tsx`. If it doesn't already accept a `getRowClassName?: (row: Row<T>) => string | undefined` prop, add one.
  - Plumb the prop through the `<TableRow>` map so each row can receive an extra className.
  - **Backwards-compatible**: when the prop is absent, behavior is unchanged (no class added).
  - Update the `services/index.tsx` invocation? **No** — leave it unchanged; only the new vehicles index uses the new prop.

- [x] **Task F6**: Make dashboard alert rows clickable in `resources/js/pages/dashboard.tsx`.
  - Read the new `link` field from each alert row.
  - Wrap the `<li>` content in a `<Link href={alert.link}>` from `@inertiajs/react`. The hover state SHOULD give visual feedback (`hover:bg-muted/50` or similar).
  - Update the `DocumentAlert` TypeScript interface in `dashboard.tsx` to include `link: string`.

### Tests

- [x] **Task T1 (Pest, backend)**: Add to `tests/Feature/Http/Controllers/VehicleControllerTest.php`:
  - `test('index returns paginated payload')` — assert `vehicles.data` is an array, `vehicles.meta.per_page === 15` (the default).
  - `test('index filters by docs_status expired')` — seed 3 vehicles: one with expired SOAT, one with expiring RTM (within 30 days), one with everything > 60 days out. GET `/vehicles?filter[docs_status]=expired`, assert only the first vehicle is in the response.
  - `test('index filters by docs_status expiring_soon')` — same fixture, GET `?filter[docs_status]=expiring_soon`, assert only the second vehicle is in the response.
  - `test('index filters by docs_status ok')` — same fixture, GET `?filter[docs_status]=ok`, assert only the third vehicle is in the response.
  - `test('index filters by soat_expired boolean')` — GET `?filter[soat_expired]=true`, assert only the SOAT-expired vehicle is in the response.
  - `test('per-document filters compose with docs_status')` — GET `?filter[docs_status]=expired&filter[rtm_expired]=true`, assert the response contains only vehicles that are both globally expired AND specifically RTM-expired.
  - Reference convention: `tests/Feature/Http/Controllers/ServiceControllerTest.php` for filter coverage.

- [x] **Task T2 (Pest, backend)**: Add to `tests/Feature/Http/Controllers/VehicleControllerTest.php`:
  - `test('show returns vehicle with relationships and recent services')` — seed a vehicle, attach a municipality + third_party, and create 7 services for the vehicle. GET `/vehicles/{id}`, assert the Inertia payload's `recentServices` is an array of length 5, ordered by `service_date` DESC, and that `vehicle.municipality.department.name` is present.
  - `test('show returns empty recentServices when none exist')` — same setup without services, assert `recentServices` is an empty array.

- [x] **Task T3 (Pest, backend)**: Add to `tests/Feature/DashboardTest.php`:
  - `test('document alerts include a navigation link')` — seed a vehicle with expired SOAT and a driver with expired license. GET `/dashboard` as admin. Assert each alert row in the `documentAlerts` array has a `link` field. Assert the vehicle alert's link is `/vehicles?filter[docs_status]=expired` and the driver alert's link is `/drivers`.

- [x] **Task T4 (Pest, backend)**: Update existing `VehicleControllerTest` tests that hit the index to match the new `PaginatedData` shape if they currently assume `->get()` returns a flat array. Run `./vendor/bin/sail test --compact --filter=VehicleControllerTest` to find and fix the regressions.

- [x] **Task T5 (Dusk, UI regression)**: Create `tests/Browser/Vehicles/IndexRendersAndFiltersExpiredTest.php`:
  - Login as admin (use `env('SUPER_ADMIN_USER')` / `env('SUPER_ADMIN_PASSWORD')`).
  - Seed at least 3 vehicles via `Vehicle::factory()` with mixed expiry states.
  - Visit `/vehicles`. Assert page title contains `Vehículos`, no `[role="alert"]` is visible, and the table headers contain the Spanish strings `Placa`, `Cód. Interno`, `Tipo`, `Propietario`, `Estado`, `Documentos`.
  - Apply the `Documentos = Vencidos` filter via the filter dropdown.
  - Assert at least one row is visible and that row has the destructive tint class (`bg-destructive/10`).
  - Take a screenshot at the initial load and after filter applied.

- [x] **Task T6 (Dusk, UI regression)**: Create `tests/Browser/Vehicles/DashboardCrossLinkToFilteredIndexTest.php`:
  - Login as admin.
  - Seed a vehicle with an expired SOAT.
  - Visit `/dashboard`. Assert the **Alertas de Documentos** card is visible.
  - Click the alert row corresponding to the seeded vehicle.
  - Assert the browser lands on `/vehicles?filter[docs_status]=expired` (URL contains the query param).
  - Assert the matching row is visible with the destructive tint.
  - Take a screenshot at dashboard and at vehicles-after-click.

- [x] **Task T7 (Dusk, UI regression)**: Create `tests/Browser/Vehicles/ShowPageRendersAllSectionsTest.php`:
  - Login as admin.
  - Seed a vehicle with a municipality, a third-party owner, and 5+ services.
  - Visit `/vehicles/{id}`.
  - Assert each section heading is visible: `Información General`, `Documentos`, `Propietario`, `Servicios Recientes`. Header card MUST show the placa.
  - Assert no `[role="alert"]` is visible.
  - Take a screenshot at top of page and after scroll-to `Documentos` (use `$browser->script('document.querySelector(...).scrollIntoView()')`).

- [x] **Task T8 (Dusk, UI regression)**: Create `tests/Browser/Vehicles/CreateAndOperatorAccessTest.php`:
  - **Step 1 (admin)**: Login as admin. Visit `/vehicles`. Click `Crear Vehículo`, the `<VehicleCreateDialog />` modal should open. Fill the form with a unique placa (`TEST-${random}`) and required fields. Submit. Assert the modal closes and the new row appears in the index.
  - **Step 2 (operator)**: Logout. Login as operator (`operator@sgte.app` / `password`). Visit `/vehicles`, assert the table loads (no 403). Click the row-actions menu → Eliminar on the vehicle created in Step 1. Confirm the deletion. Assert the row disappears.
  - This test pins both the create flow AND the operator-can-delete behavior introduced in Phase A2.
  - Take screenshots at modal-open, modal-filled, and post-deletion-index states.

## Verification

Verification has three layers — use all of them. Playwright MCP is for *interactive* development checks and does NOT replace committable regression coverage.

### 1. Interactive verification — Playwright MCP

Reference users (all password `password`, except super admin which reads `SUPER_ADMIN_USER` / `SUPER_ADMIN_PASSWORD` from `.env`):

| Role | Email |
|---|---|
| Admin | `admin@sgte.app` |
| Operator | `operator@sgte.app` |
| Driver | `driver@sgte.app` |
| Accounting | `accounting@sgte.app` |

Preferred flow:

1. `mcp__playwright__browser_navigate http://localhost/login` and login as admin.
2. Navigate to `/vehicles`. Take an `mcp__playwright__browser_snapshot` and verify the table renders with the expected columns + a row tint on at least one expired-doc vehicle.
3. Apply the `Documentos = Vencidos` filter and snapshot again. Verify only tinted rows remain.
4. Click a row's Placa link. Snapshot the show page. Verify all five Card sections render in order.
5. Navigate to `/dashboard`. Click an alert row. Verify the URL changes to `/vehicles?filter[docs_status]=expired` and the matching row is visible.
6. Click `Crear Vehículo` on `/vehicles`. Snapshot the modal. Fill and submit. Snapshot the index after the modal closes — verify the new row appears.
7. Logout. Login as operator. Repeat steps 2 + 6 — verify operator can browse and create.
8. Logout. Login as driver. Navigate to `/vehicles` — verify a 403 page appears.
9. Use `mcp__laravel-boost__browser-logs` to inspect any JS console errors during the flow.

- [x] Scenario 1: Admin sees the rebuilt index, applies docs_status filter, sees tinted rows.
- [x] Scenario 2: Admin clicks dashboard alert, lands on filtered vehicles index.
- [x] Scenario 3: Admin opens the show page and verifies all five sections render with real data.
- [x] Scenario 4: Operator can create + delete via the modal and the row-actions menu.
- [x] Scenario 5: Driver receives 403 on `/vehicles`.

### 2. Backend regression — Pest feature tests (required)

Tasks T1–T4 above MUST be added to `tests/Feature/Http/Controllers/VehicleControllerTest.php` and `tests/Feature/DashboardTest.php`. Run via `./vendor/bin/sail test --compact`. The full suite MUST stay green at 442+ tests passing.

### 3. UI regression — Laravel Dusk browser tests (required)

Tasks T5–T8 above MUST be added under `tests/Browser/Vehicles/`. Each test MUST:

- Assert no `[role="alert"]`, exception trace, or visible error UI.
- Assert key Spanish strings render with correct diacritics (`Vehículos`, `Información General`, `Documentos`, `Propietario`, `Servicios recientes`).
- Take screenshots at key interaction steps for visual review.
- Reset state with `php artisan migrate:fresh --seed --no-interaction` when needed (use the Dusk `RefreshDatabase` trait or invoke artisan inside `setUp`).

Run locally via `./vendor/bin/sail dusk --filter=Vehicles`. CI does not run Dusk currently, but the suite MUST run cleanly locally before merge.

### 4. API endpoints (curl)

The `/vehicles` routes are Inertia routes, not a public JSON API, so curl verification is limited to confirming auth gates. Examples:

```bash
# Admin: should get a 200 + Inertia HTML
curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@sgte.app","password":"password"}' \
  -c cookies-admin.txt

curl -s -o /dev/null -w "%{http_code}\n" \
  -H "Accept: text/html" \
  -b cookies-admin.txt \
  http://localhost/vehicles
# Expected: 200

# Driver: should get a 403
curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"driver@sgte.app","password":"password"}' \
  -c cookies-driver.txt

curl -s -o /dev/null -w "%{http_code}\n" \
  -H "Accept: text/html" \
  -b cookies-driver.txt \
  http://localhost/vehicles
# Expected: 403
```

## Dependencies

- **None as a hard prerequisite.** Every backend piece this requires (controllers, models, permissions, factories) already exists and is tested.
- **Soft dependency on the dashboard alert link field**: Task B5 modifies `DashboardController::buildDocumentAlerts`. Task F6 consumes the new field. They must be implemented together (same commit or sequential commits) to avoid a half-broken state.
- **No new packages.**

## Notes

### Why this scope is intentionally narrow

The five other Blueprint scaffold modules (Drivers, Third-Parties, Contracts, Invoices, Service-Incidents) need the same treatment, but bundling them into one requirement would risk a multi-day diff with no rollback granularity. Vehículos is the right pilot because:

1. It's the entity tied to **REQ-004** (Fleet Management) and the dashboard's Alertas de Documentos panel, so it has the highest user-visible value.
2. The document-expiry visualization will produce reusable building blocks (`<VehicleDocumentPills />`, the row-tint hook on `<DataTable>`) that the other rebuilds can copy.
3. The `services/index.tsx` pattern is well-established and serves as a clean template.

### Reusable building blocks introduced by this requirement

After this requirement lands, the following pieces become available for the next Blueprint rebuild requirements:

- `<VehicleDocumentPills />` — pattern for "three-state expiry pill" reusable for Drivers (license_due_date, social security) with minor adaptation.
- `getRowClassName` prop on `<DataTable>` — generalizes "highlight a row based on a domain condition", reusable everywhere.
- The `docs_status` filter pattern on `VehicleController` — the same shape (single status filter + per-document booleans) is the right shape for the Drivers module's `license_status` filter.

### Out of scope, deferred to future requirements

- Rebuilding `vehicles/create.tsx` and `vehicles/edit.tsx` (already real forms).
- A vehicle history timeline beyond "last 5 services".
- Document upload via `spatie/laravel-medialibrary` (the package is installed but no model uses it yet — see ADR-006 §2).
- Bulk actions (bulk delete, bulk status change).
- Export to CSV / PDF.
- The other five Blueprint scaffolds (Drivers, Third-Parties, Contracts, Invoices, Service-Incidents) — each will get its own requirement following this template.
