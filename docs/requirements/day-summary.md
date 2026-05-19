---
name: day-summary
type: feat
scope: services
status: completed
priority: high
created_date: 2026-03-05
completed_date: 2026-03-06
srs_refs: ["REQ-008"]
migration_strategy: modify-existing
---

# Day Summary

## Description

Implement the Day Summary page — a consolidated view of all services for a specific date with an executive summary, an "Execute Day" action, and CSV export. The page shows a table with vehicle plate, driver/provider, time range, client, status, and incident count for each service. An executive summary section displays aggregated stats. The "Execute Day" button is enabled only when all services are closed, triggering the state transition defined in the `day-status-logic` requirement. The page is accessible by admin, operator (full access), and accounting (read-only).

## Acceptance Criteria

- [x] AC-1: WHEN the user navigates to the day summary for a specific date THEN a table MUST display all services for that date with columns: Placa, Conductor/Proveedor, Horario, Cliente, Estado, Novedades.
- [x] AC-2: WHEN a service's vehicle has `is_third_party = true` THEN the "Conductor/Proveedor" column MUST display the provider name (from `vehicle.thirdParty`) instead of the driver name, with a "3ro" badge.
- [x] AC-3: WHEN a service has related `serviceIncidents` THEN the "Novedades" column MUST display a warning badge with the incident count (e.g., "2 Nov"). WHEN there are no incidents THEN it MUST display "—".
- [x] AC-4: WHEN the page is rendered THEN an executive summary section MUST display: total services, closed services count, open services count, services with incidents count, and third-party vehicle count.
- [x] AC-5: WHEN all services for the date have `service_status = closed` THEN the "Ejecutar Día" button MUST be enabled. WHEN at least one service is open THEN the button MUST be disabled with a tooltip: "Para ejecutar el día, todos los servicios deben estar cerrados."
- [x] AC-6: WHEN the user clicks "Ejecutar Día" THEN the system MUST call the `day-statuses/{id}/execute` endpoint (from `day-status-logic`). On success, the page MUST refresh showing the day as "EJECUTADO" with the executor name and timestamp.
- [x] AC-7: WHEN the day status is `executed` THEN the page MUST display a green "EJECUTADO" banner with "Ejecutado por {name} el {date}" and the "Ejecutar Día" button MUST be hidden.
- [x] AC-8: WHEN the user clicks the "Exportar CSV" button THEN the browser MUST download a CSV file with the day's service data.
- [x] AC-9: WHEN the user clicks the previous/next day navigation arrows THEN the page MUST reload with data for the new date.
- [x] AC-10: WHEN the user clicks a service row in the table THEN the browser MUST navigate to the service show page.

## Technical Specification

### Data Model

No new tables or columns. The day summary reads from existing `services`, `day_statuses`, `vehicles`, `drivers`, `contracts`, `third_parties`, and `service_incidents` tables.

### Enums

No new enums. Uses existing:

- `ServiceStatus` (`open`, `closed`)
- `DayStatusEnum` (`projected`, `executed`)
- `Permission` (`VIEW_DAY_SUMMARY`, `EXECUTE_DAY`)

### Routes

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | /day-summary | DaySummaryController@index | auth, verified | day-summary.index |
| GET | /day-summary/export | DaySummaryController@export | auth, verified | day-summary.export |

A dedicated `DaySummaryController` is created because this page has a distinct data shape and purpose from the `DayStatusController` (which serves the calendar) and `ServiceController` (which serves the CRUD). The day summary aggregates services, vehicles, drivers, and day status into a single consolidated view.

### Permissions

No new permissions. Uses existing:

- `VIEW_DAY_SUMMARY` — gate on page access and export
- `EXECUTE_DAY` — determines whether the "Ejecutar Día" button is shown (the execute endpoint itself is defined in `day-status-logic`)

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Day Summary | `resources/js/pages/day-summary/index.tsx` | Main summary page with table, stats, and actions |

## Migration Strategy

- **modify-existing**: No database changes. New controller, routes, and frontend page only.

## Tasks

### Backend

- [x] Task 1: Create `DaySummaryController` using `php artisan make:controller DaySummaryController --no-interaction`
  - **`index` method:**
    - Gate check: `Gate::authorize(Permission::VIEW_DAY_SUMMARY->value)`
    - Accept `date` query parameter (default: today). Validate as valid date format (`Y-m-d`).
    - Query services for the date with all needed relationships:
      ```php
      $services = Service::where('service_date', $date)
          ->whereNull('deleted_at')
          ->with([
              'vehicle:id,plate,is_third_party,third_party_id',
              'vehicle.thirdParty:id,company_name,first_name,first_lastname,is_natural_person',
              'driver:id,first_name,first_lastname',
              'contract:id,contract_number,third_party_id',
              'contract.thirdParty:id,company_name,first_name,first_lastname,is_natural_person',
          ])
          ->withCount('serviceIncidents')
          ->orderBy('planned_start_time')
          ->get();
      ```
    - Query day status: `DayStatus::where('date', $date)->with('executor:id,name')->first()`
    - Compute executive summary server-side:
      ```php
      $summary = [
          'total' => $services->count(),
          'closed' => $services->where('service_status', ServiceStatus::Closed)->count(),
          'open' => $services->where('service_status', ServiceStatus::Open)->count(),
          'with_incidents' => $services->where('service_incidents_count', '>', 0)->count(),
          'third_party' => $services->filter(fn ($s) => $s->vehicle?->is_third_party)->count(),
      ];
      ```
    - Pass to Inertia: `services`, `dayStatus`, `summary`, `date`, `canExecuteDay` (boolean from `Gate::allows(Permission::EXECUTE_DAY->value)`)

- [x] Task 2: Add `export` method to `DaySummaryController`
  - Gate check: `Gate::authorize(Permission::VIEW_DAY_SUMMARY->value)`
  - Accept `date` query parameter (required, valid date)
  - Query same service data as index method
  - Generate CSV content using Laravel's `StreamedResponse`:
    ```php
    return response()->streamDownload(function () use ($services, $date) {
        $handle = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        // Header row
        fputcsv($handle, ['Placa', 'Conductor/Proveedor', 'Hora Inicio', 'Hora Fin', 'Duración (min)', 'Cliente', 'Estado', 'Novedades', 'Valor Unitario', 'Cantidad', 'Forma de Pago', 'Grupo Facturación']);
        foreach ($services as $service) {
            fputcsv($handle, [
                $service->vehicle?->plate ?? '',
                $service->vehicle?->is_third_party
                    ? ($service->vehicle?->thirdParty?->company_name ?? $service->vehicle?->thirdParty?->first_name.' '.$service->vehicle?->thirdParty?->first_lastname)
                    : ($service->driver?->first_name.' '.$service->driver?->first_lastname),
                $service->planned_start_time,
                $service->actual_end_time ?? '',
                $service->planned_duration,
                $service->contract?->thirdParty?->company_name ?? $service->contract?->thirdParty?->first_name.' '.$service->contract?->thirdParty?->first_lastname ?? '',
                $service->service_status->value === 'closed' ? 'Cerrado' : 'Abierto',
                $service->service_incidents_count,
                $service->unit_value,
                $service->quantity,
                $service->payment_method->value,
                collect($service->billing_groups ?? [])
                    ->map(fn ($group) => $group->label())
                    ->implode(', '),
            ]);
        }
        fclose($handle);
    }, "resumen-dia-{$date}.csv", ['Content-Type' => 'text/csv']);
    ```
  - No external package needed — uses Laravel's built-in streaming response

- [x] Task 3: Register routes in `routes/web.php`
  - Add inside the authenticated route group:
    ```php
    Route::get('day-summary/export', [DaySummaryController::class, 'export'])->name('day-summary.export');
    Route::get('day-summary', [DaySummaryController::class, 'index'])->name('day-summary.index');
    ```
  - The export route MUST be registered BEFORE the index route to avoid route parameter conflicts
  - Apply `['auth', 'verified']` middleware

### Frontend

- [x] Task 4: Create `resources/js/pages/day-summary/index.tsx` — main summary page
  - **Props** (from controller):
    ```
    {
      services: (Service & {
        vehicle?: Vehicle & { thirdParty?: ThirdParty };
        driver?: Pick<Driver, 'id' | 'first_name' | 'first_lastname'>;
        contract?: Contract & { thirdParty?: ThirdParty };
        service_incidents_count: number;
      })[]
      dayStatus: (DayStatus & { executor?: { id: number; name: string } }) | null
      summary: { total: number; closed: number; open: number; with_incidents: number; third_party: number }
      date: string
      canExecuteDay: boolean
    }
    ```
  - **Layout:** `AppLayout` wrapper
  - **Breadcrumbs:** `[{ title: 'Resumen del Día' }]`
  - **Page title:** `<Head title="Resumen del Día" />`
  - **Header section:**
    - Title: "Resumen del Día — {formatted date}" (Spanish locale, e.g., "Miércoles, 15 de Octubre de 2025")
    - Date input for date selection
    - Previous/Next day navigation: `ChevronLeft` / `ChevronRight` buttons, trigger Inertia visit with `date` param
    - Navigation links: `[Ver Gantt]` (links to `/gantt?date={date}`)
    - Day status badge:
      - No DayStatus → gray badge "Sin Datos"
      - Projected → orange badge "Proyectado"
      - Executed → green badge "Ejecutado por {executor.name} el {executed_at formatted}"
  - **Executive summary section** (Card with grid of stats):
    - "Total Servicios: {total}" — neutral color
    - "Cerrados: {closed}" — green text/badge
    - "Abiertos: {open}" — orange text/badge
    - "Con Novedades: {with_incidents}" — yellow/warning text
    - "Vehículos 3ros: {third_party}" — blue text
    - Use `Card` + grid layout (`grid-cols-5` or `grid-cols-2 md:grid-cols-5`)
  - **Services table** using the project's `DataTable` component (client-side mode since data is not paginated):
    - Column definitions in a separate file (Task 5)
    - Row click handler: navigate to `ServiceController.show(service.id).url`
    - No server-side pagination needed — all services for a single day are passed as props (typically < 100 rows)
  - **Action bar** (below table):
    - "Ejecutar Día" button:
      - Hidden when `!canExecuteDay` (user lacks permission)
      - Hidden when day is already executed
      - Disabled when `summary.open > 0` — show tooltip: "Para ejecutar el día, todos los servicios deben estar cerrados."
      - Enabled when `summary.open === 0` AND day is not executed
      - On click: show confirmation dialog (AlertDialog) "¿Está seguro que desea ejecutar el día? Esta acción bloqueará la edición de los servicios." with "Ejecutar" (confirm) and "Cancelar" buttons
      - On confirm: `router.post(route('day-statuses.execute', dayStatus.id))` — uses the execute endpoint from `day-status-logic` requirement
      - If no DayStatus exists yet (day has no services): button is hidden
    - "Exportar CSV" button:
      - Always visible for users with `VIEW_DAY_SUMMARY` permission
      - On click: open `/day-summary/export?date={date}` in a new tab or trigger download via `window.location.href`
      - Use `Download` icon from lucide-react
  - Follow existing page layout patterns from `resources/js/pages/services/index.tsx`

- [x] Task 5: Create `resources/js/pages/day-summary/columns.tsx` — table column definitions
  - **Columns:**
    1. **Placa** — `service.vehicle.plate`; if `vehicle.is_third_party`, append small blue "3ro" badge
    2. **Conductor/Proveedor** — conditional display:
       - If `vehicle.is_third_party`: show `vehicle.thirdParty.company_name` (or natural person name) with "Proveedor" subtitle
       - Else: show `driver.first_name driver.first_lastname`
    3. **Horario** — format as "HH:MM - HH:MM" using `planned_start_time` and computed end time (`planned_start_time + planned_duration`). If `actual_start_time` and `actual_end_time` exist, show actual times in a second line with smaller/muted text
    4. **Cliente** — `service.contract.thirdParty.company_name` (or natural person name); show contract number as subtitle in muted text
    5. **Estado** — `Badge` component:
       - `closed` → green badge "Cerrado"
       - `open` → orange badge "Abierto"
    6. **Novedades** — incident count display:
       - `service_incidents_count > 0` → yellow/warning badge with count (e.g., "2 Nov")
       - `service_incidents_count === 0` → muted "—"
  - **Row styling:**
    - Cursor pointer on all rows (clickable)
    - Subtle hover effect
  - Use `ColumnDef` from `@tanstack/react-table`
  - Follow `resources/js/pages/services/columns.tsx` as convention reference for formatters and badge patterns

- [x] Task 6: Add Day Summary link to the sidebar navigation
  - In `resources/js/components/app-sidebar.tsx`:
    - Import the DaySummary controller action
    - Add a "Resumen del Día" item in the "Producción" section, below "Planificador Gantt"
    - Use `ClipboardList` or `FileText` icon from lucide-react
    - Gate by `Permission.VIEW_DAY_SUMMARY`
    - After adding the route, run Wayfinder generation to create the TypeScript action file

- [x] Task 7: Update Gantt page header to link to Day Summary
  - In `resources/js/pages/gantt/index.tsx` (from `daily-gantt` requirement):
    - The "Resumen" tab in the header MUST link to `/day-summary?date={date}` instead of the temporary services index target
    - This creates bidirectional navigation: Gantt ↔ Day Summary for the same date

### Tests

- [x] Task 8: Create `tests/Feature/Http/Controllers/DaySummaryControllerTest.php` using `php artisan make:test --pest`
  - Test: index returns `services`, `dayStatus`, `summary`, `date`, `canExecuteDay` props
  - Test: index with `?date=2026-03-10` returns services filtered to that date
  - Test: index without date param defaults to today
  - Test: services include `vehicle`, `driver`, `contract.thirdParty` relationships
  - Test: services include `service_incidents_count`
  - Test: summary totals are correctly computed (total, closed, open, with_incidents, third_party)
  - Test: `dayStatus` is null when no DayStatus exists for the date
  - Test: `dayStatus` includes executor when day is executed
  - Test: `canExecuteDay` is true for user with `EXECUTE_DAY` permission
  - Test: `canExecuteDay` is false for user without `EXECUTE_DAY` permission
  - Test: user without `VIEW_DAY_SUMMARY` permission gets 403
  - Use factories to create services with various statuses and incident counts
  - Follow `tests/Feature/Http/Controllers/VehicleControllerTest.php` as convention reference

- [x] Task 9: Create `tests/Feature/Http/Controllers/DaySummaryExportTest.php` using `php artisan make:test --pest`
  - Test: export returns a CSV file with correct Content-Type header (`text/csv`)
  - Test: export filename matches pattern `resumen-dia-{date}.csv`
  - Test: CSV contains header row with expected column names
  - Test: CSV contains one data row per service for the specified date
  - Test: CSV correctly displays provider name for third-party vehicles
  - Test: CSV correctly displays driver name for non-third-party vehicles
  - Test: user without `VIEW_DAY_SUMMARY` permission gets 403
  - Test: export with `?date=` missing or invalid returns validation error
  - Use factories to create services with known data and assert CSV content

## Verification

### UI (Laravel Dusk)

Dusk browser tests in `tests/Browser/`. Use super admin credentials from `env('SUPER_ADMIN_USER')` / `env('SUPER_ADMIN_PASSWORD')`. Run `php artisan migrate:fresh --seed --no-interaction` before tests that need a clean database.

- [x] Navigate to `/day-summary` and verify the services table is displayed with all expected columns
- [x] Verify the executive summary section displays correct aggregated stats (total, closed, open, with incidents, third-party)
- [x] Verify third-party vehicles display provider name with "3ro" badge in the Conductor/Proveedor column
- [x] Verify services with incidents display a warning badge with count
- [x] Click a service row and verify navigation to the service show page
- [x] Click previous/next day navigation and verify the page reloads with the new date
- [x] Verify "Ejecutar Dia" button is disabled when open services exist, with tooltip
- [x] Verify "Ejecutar Dia" button works when all services are closed (day transitions to executed)
- [x] Verify executed day displays green "EJECUTADO" banner with executor name and timestamp

### API (curl)

```bash
# Verify CSV export downloads correctly
curl -s -o /dev/null -w "%{http_code}" -X GET "http://localhost/day-summary/export?date=2026-03-05" \
  -b cookies.txt
# Expected: 200 with Content-Type: text/csv
```

## Dependencies

- `service-form` (pending) — provides the service show page that row clicks navigate to
- `day-status-logic` (pending) — provides the `day-statuses/{id}/execute` endpoint and auto-created DayStatus records
- `daily-gantt` (pending) — provides the Gantt page that the "Ver Gantt" link navigates to (bidirectional navigation)

## Notes

- **CSV export uses Laravel's built-in `StreamedResponse`** — no external package (like maatwebsite/laravel-excel) is needed. The CSV includes a UTF-8 BOM for Excel compatibility. If Excel/XLSX format is needed in the future, the maatwebsite/laravel-excel package can be added as an enhancement.
- **The services table is NOT paginated.** A single day typically has fewer than 100 services, so all data is passed as Inertia props and rendered client-side using the existing `DataTable` component. The `useServerTable` hook is NOT needed — use `@tanstack/react-table` directly with the `columns` definition.
- **The "Execute Day" action** calls the endpoint defined in `day-status-logic` (`POST /day-statuses/{id}/execute`). If the DayStatus record doesn't exist yet (no services created via the observer), the button is hidden. The frontend must check for `dayStatus !== null` before showing the button.
- **Bidirectional navigation between Gantt and Day Summary** — both pages have header links to switch to the other view for the same date. The date parameter is carried through.
- **The executive summary is computed server-side** to avoid discrepancies between displayed stats and table data. All counts use the same services collection.
- **Accounting users** see the full table (read-only) but do NOT see the "Ejecutar Día" button (they lack `EXECUTE_DAY` permission). They can export CSV.
- **The `service_incidents_count`** is loaded via `withCount('serviceIncidents')` on the Eloquent query, which adds a `service_incidents_count` attribute to each service without loading the full incidents collection. This is efficient for display purposes.
- **Time range display** in the "Horario" column computes the end time client-side: `planned_start_time + planned_duration` (in minutes). For example, start at "08:00" with duration 150 min → "08:00 - 10:30". The helper from `resources/js/lib/gantt-utils.ts` (or a new `time-utils.ts`) can be reused.
