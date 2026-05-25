---
name: daily-gantt
type: feat
scope: services
status: completed
priority: high
created_date: 2026-03-05
completed_date: 2026-03-05
srs_refs: ["REQ-002"]
migration_strategy: modify-existing
---

# Daily Gantt Fleet Planner

## Description

Implement the daily Gantt fleet planner — the primary planning interface for dispatchers. The view displays all fleet vehicles on the Y-axis and hours of the day on the X-axis, with horizontal bars representing assigned services. Dispatchers can click an empty cell to create a new service (with vehicle and time pre-selected) or click an existing bar to edit it. Vehicles with expired documents are shown in gray with assignment blocked. A municipality filter allows planning by location. The implementation wraps the existing kibo-ui Gantt component (`resources/js/components/kibo-ui/gantt/index.tsx`) which already provides the daily range, drag-and-drop, overlapping feature handling, and sidebar infrastructure.

## Acceptance Criteria

- [x] AC-1: WHEN the user navigates to the Gantt page for a specific date THEN all fleet vehicles with `status = active` MUST be displayed on the Y-axis, each as a row with the vehicle plate visible in the sidebar.
- [x] AC-2: WHEN the Gantt is rendered THEN the X-axis MUST display hours from 06:00 to 22:00, with each hour as a column.
- [x] AC-3: WHEN a vehicle has services on the selected date THEN horizontal bars MUST be displayed at the correct time position, with width proportional to `planned_duration`. If `actual_start_time` and `actual_end_time` exist, a secondary indicator MUST show actual duration.
- [x] AC-4: WHEN the user clicks an empty cell in the Gantt THEN the browser MUST navigate to the service create page with `vehicle_id` and `planned_start_time` pre-populated from the click position, and `service_date` set to the Gantt's current date.
- [x] AC-5: WHEN the user clicks a service bar THEN the browser MUST navigate to the service edit page for that service.
- [x] AC-6: WHEN a vehicle has ANY expired document (`soat_due_date < today` OR `rtm_due_date < today` OR `operation_card_due_date < today`) THEN the vehicle row MUST be rendered in gray with a "BLOQ." label, and clicking empty cells in that row MUST be disabled (no service creation allowed).
- [x] AC-7: WHEN a vehicle has a document expiring within 15 days THEN the sidebar MUST display a warning indicator ("Prec.") next to the vehicle plate.
- [x] AC-8: WHEN the user selects a municipality in the filter dropdown THEN only vehicles with `municipality_id` matching the selected municipality MUST be displayed.
- [x] AC-9: WHEN the user clicks the previous/next day navigation arrows THEN the Gantt MUST reload with data for the new date.
- [x] AC-10: WHEN a vehicle has `is_third_party = true` THEN the sidebar MUST display a "3ro" label next to the vehicle plate.
- [x] AC-11: WHEN the Gantt date corresponds to an executed day THEN a banner MUST display "Día Ejecutado" and empty-cell click (new service creation) MUST be disabled.

## Technical Specification

### Data Model

No new tables or columns. The Gantt reads from existing `vehicles`, `services`, `day_statuses`, and `municipalities` tables.

### Enums

No new enums. Uses existing:

- `VehicleStatus` (`active`, `maintenance`, `retired`)
- `ServiceStatus` (`open`, `closed`)
- `DayStatusEnum` (`projected`, `executed`)

### Routes

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | /gantt | GanttController@index | auth, verified | gantt.index |

A dedicated `GanttController` is created because the Gantt page has a distinct data shape (vehicles + services + day status + municipalities for a single date) that does not fit the existing `ServiceController` or `DayStatusController` index patterns.

### Permissions

No new permissions. Uses existing:

- `VIEW_SERVICES` — gate on Gantt access (dispatchers who can view services can view the Gantt)
- `CREATE_SERVICES` — determines whether empty-cell click is enabled

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Gantt | `resources/js/pages/gantt/index.tsx` | Daily Gantt fleet planner page |
| Vehicle Sidebar Item | `resources/js/components/gantt/vehicle-sidebar-item.tsx` | Custom sidebar row for vehicle info |
| Service Bar | `resources/js/components/gantt/service-bar.tsx` | Custom feature bar for service display |

## Migration Strategy

- **modify-existing**: No database changes. New controller, route, and frontend page only.

## Tasks

### Backend

- [x] Task 1: Create `GanttController` using `php artisan make:controller GanttController --no-interaction`
  - **`index` method:**
    - Gate check: `Gate::authorize(Permission::VIEW_SERVICES->value)`
    - Accept `date` query parameter (default: today). Validate it is a valid date format (`Y-m-d`).
    - Accept `municipality_id` query parameter (optional, integer, exists:municipalities,id).
    - Query vehicles:
      ```php
      $vehiclesQuery = Vehicle::query()
          ->where('status', VehicleStatus::Active)
          ->with(['thirdParty:id,company_name,first_name,first_lastname,is_natural_person', 'municipality:id,name,department_id', 'municipality.department:id,name'])
          ->select(['id', 'plate', 'is_third_party', 'third_party_id', 'municipality_id', 'soat_due_date', 'rtm_due_date', 'operation_card_due_date'])
          ->orderBy('plate');
      ```
    - Apply municipality filter if provided: `$vehiclesQuery->where('municipality_id', $municipalityId)`
    - Query services for the date:
      ```php
      Service::where('service_date', $date)
          ->whereNull('deleted_at')
          ->with(['driver:id,first_name,first_lastname', 'contract:id,contract_number,third_party_id', 'contract.thirdParty:id,company_name,first_name,first_lastname,is_natural_person'])
          ->get()
      ```
    - Query day status: `DayStatus::where('date', $date)->with('executor:id,name')->first()`
    - Query municipalities for the filter dropdown: `Municipality::query()->with('department:id,name')->orderBy('name')->get(['id', 'name', 'code', 'department_id'])`
    - Pass to Inertia: `vehicles`, `services`, `dayStatus`, `municipalities`, `date`, `canCreateServices` (boolean from `Gate::allows(Permission::CREATE_SERVICES->value)`)
  - Follow existing controller patterns for Inertia rendering

- [x] Task 2: Register the Gantt route in `routes/web.php`
  - Add `Route::get('gantt', [GanttController::class, 'index'])->name('gantt.index')` inside the authenticated route group
  - Apply `['auth', 'verified']` middleware

- [x] Task 3: Add Gantt link to the sidebar navigation
  - In `resources/js/components/app-sidebar.tsx`:
    - Import the Gantt controller action: `import GanttController from '@/actions/App/Http/Controllers/GanttController'`
    - Add a "Planificador Gantt" item in the "Producción" section, below "Calendario"
    - Use `LayoutGrid` or `GanttChart` icon from lucide-react (check available icons)
    - Gate by `Permission.VIEW_SERVICES`
    - After adding the route, run Wayfinder generation to create the TypeScript action file

### Frontend

- [x] Task 4: Create `resources/js/components/gantt/vehicle-sidebar-item.tsx` — custom sidebar row component
  - **Props:**
    ```
    {
      vehicle: {
        id: number; plate: string; is_third_party: boolean;
        soat_due_date: string | null; rtm_due_date: string | null; operation_card_due_date: string | null;
        third_party?: { company_name: string | null; first_name: string | null; first_lastname: string | null; is_natural_person: boolean };
        municipality?: { name: string; department?: { name: string } };
      }
      isBlocked: boolean
      hasWarning: boolean
    }
    ```
  - **Display layout** (compact, fits in sidebar row):
    - Vehicle plate in bold (e.g., "WTO-250")
    - Status labels as small badges next to plate:
      - `isBlocked` → red badge "BLOQ." with `bg-red-100 text-red-700`
      - `hasWarning` → yellow badge "Prec." with `bg-yellow-100 text-yellow-700`
      - `is_third_party` → blue badge "3ro" with `bg-blue-100 text-blue-700`
    - For blocked vehicles: display which document is expired as tooltip (e.g., "SOAT vencido: 05/10/2025")
    - Row background: `isBlocked ? 'bg-neutral-100 dark:bg-neutral-800 opacity-60' : ''`
  - Use `Tooltip` component from shadcn for document expiry details
  - Use `Badge` component from shadcn for status labels

- [x] Task 5: Create `resources/js/components/gantt/service-bar.tsx` — custom feature bar component
  - **Props:**
    ```
    {
      service: Service & { driver?: Pick<Driver, 'first_name' | 'first_lastname'>; contract?: Contract & { thirdParty?: ThirdParty } }
      onClick: (serviceId: number) => void
    }
    ```
  - **Display:**
    - Bar color by `service_status`:
      - `open` → `bg-orange-400 hover:bg-orange-500` (matches projected theme)
      - `closed` → `bg-green-400 hover:bg-green-500` (matches executed theme)
    - Bar content (truncated to fit):
      - Line 1: Contract client name (e.g., "EmpABC") — truncated with ellipsis
      - Line 2: Driver name or "3ro" if no driver — smaller text, muted color
    - Cursor: `cursor-pointer`
    - On click: call `onClick(service.id)` which navigates to edit page
  - Use `Tooltip` component to show full service details on hover:
    - Origin → Destination
    - Planned: HH:MM - HH:MM (duration)
    - Actual: HH:MM - HH:MM (if available)
    - Status: Abierto/Cerrado
  - Integrate with `GanttFeatureItemCard` pattern from kibo-ui Gantt component

- [x] Task 6: Create `resources/js/pages/gantt/index.tsx` — the main Gantt page
  - **Props** (from controller):
    ```
    {
      vehicles: Vehicle[]
      services: Service[]
      dayStatus: DayStatus | null
      municipalities: Municipality[]
      date: string  // 'YYYY-MM-DD'
      canCreateServices: boolean
    }
    ```
  - **Layout:**
    - `AppLayout` wrapper with breadcrumbs: `[{ title: 'Planificador Gantt' }]`
    - `<Head title="Planificador Gantt" />`
  - **Header bar** (above Gantt):
    - Date display: formatted as "Miércoles, 15 de Octubre de 2025" (Spanish locale, use `formatDateEs` from date-utils)
    - Date input: allow user to pick a specific date
    - Previous/Next day navigation: `ChevronLeft` / `ChevronRight` buttons, trigger Inertia visit with `date` param
    - Municipality filter: `MunicipalityCombobox` component, on change triggers Inertia visit with `municipality_id` param
    - Navigation tabs: `[Gantt]` (active) | `[Resumen]` (links to day-summary page when available, disabled for now)
    - Day status badge: show `DayStatusEnum` label if day status exists (orange "Proyectado" or green "Ejecutado")
  - **Gantt integration:**
    - Use `GanttProvider` with `range="daily"` and appropriate zoom level
    - Transform data for Gantt:
      - Each vehicle becomes a sidebar group/row
      - Each service becomes a `GanttFeature`:
        ```
        {
          id: String(service.id),
          name: `${service.contract?.thirdParty?.company_name || 'Servicio'}`,
          startAt: parseTimeToDate(date, service.planned_start_time),
          endAt: addMinutes(parseTimeToDate(date, service.planned_start_time), service.planned_duration),
          status: { id: service.service_status, name: statusLabel, color: statusColor },
          lane: String(service.vehicle_id)  // groups by vehicle
        }
        ```
    - **Vehicle blocking logic** (computed with `useMemo`):
      - For each vehicle, compute `isBlocked`:
        ```
        const today = new Date()
        const isBlocked = [vehicle.soat_due_date, vehicle.rtm_due_date, vehicle.operation_card_due_date]
          .some(d => d && new Date(d) < today)
        ```
      - Compute `hasWarning` (expires within 15 days):
        ```
        const warningDate = addDays(today, 15)
        const hasWarning = !isBlocked && [vehicle.soat_due_date, vehicle.rtm_due_date, vehicle.operation_card_due_date]
          .some(d => d && new Date(d) < warningDate)
        ```
    - **Sidebar:** Use `GanttSidebar` with custom `VehicleSidebarItem` for each vehicle
    - **Feature bars:** Use `GanttFeatureList` with custom `ServiceBar` rendering via `GanttFeatureItem`
    - **Empty cell click handler** (`onAddItem`):
      - If vehicle row is blocked: do nothing (handler checks vehicle ID from click position)
      - If day is executed: do nothing
      - If `canCreateServices` is false: do nothing
      - Otherwise: navigate to service create page with query params: `router.get(ServiceController.create().url, { vehicle_id: vehicleId, planned_start_time: timeFromClick, service_date: date })`
    - **Service bar click handler:**
      - Navigate to service edit: `router.get(ServiceController.edit(serviceId).url)`
  - **Helper function** `parseTimeToDate(dateStr: string, timeStr: string): Date`:
    - Combines the Gantt date with a time string (e.g., "08:30") into a full Date object
    - Used to convert `planned_start_time` into Gantt-compatible Date positions
  - Follow kibo-ui Gantt usage patterns and existing page layout conventions

- [x] Task 7: Update `ServiceController@create` to accept pre-populated query params
  - Read optional query parameters: `vehicle_id`, `planned_start_time`, `service_date`
  - Pass them as `prefill` prop to Inertia render:
    ```php
    'prefill' => [
        'vehicle_id' => $request->query('vehicle_id'),
        'planned_start_time' => $request->query('planned_start_time'),
        'service_date' => $request->query('service_date'),
    ]
    ```
  - The service create page (from `service-form` requirement) MUST use these prefill values to initialize the `useForm` defaults when present

- [x] Task 8: Update `resources/js/pages/services/create.tsx` to use prefill data
  - Accept `prefill` prop: `{ vehicle_id?: string; planned_start_time?: string; service_date?: string }`
  - When initializing `useForm`, use prefill values as defaults if provided:
    ```
    const form = useForm({
      vehicle_id: prefill?.vehicle_id ?? '',
      planned_start_time: prefill?.planned_start_time ?? '',
      service_date: prefill?.service_date ?? '',
      // ... other defaults
    })
    ```
  - This enables the Gantt → Create Service flow with pre-populated vehicle and time

- [x] Task 9: Create `resources/js/lib/gantt-utils.ts` — utility functions for Gantt data transformation
  - `parseTimeToDate(dateStr: string, timeStr: string): Date` — combines date string and time string into Date object
  - `servicesToGanttFeatures(services: Service[], date: string): GanttFeature[]` — transforms Service array into GanttFeature array
  - `computeVehicleDocStatus(vehicle: Vehicle): { isBlocked: boolean; hasWarning: boolean; expiredDocs: string[] }` — determines vehicle document status
  - Export all functions for use in Gantt page and tests

### Tests

- [x] Task 10: Create `tests/Feature/Http/Controllers/GanttControllerTest.php` using `php artisan make:test --pest`
  - Test: index returns `vehicles`, `services`, `dayStatus`, `municipalities`, `date`, `canCreateServices` props
  - Test: index with `?date=2026-03-10` returns services filtered to that date
  - Test: index without date param defaults to today
  - Test: index with `?municipality_id=X` returns only vehicles in that municipality
  - Test: vehicles include document expiry dates (`soat_due_date`, `rtm_due_date`, `operation_card_due_date`)
  - Test: vehicles include `thirdParty` relationship when `is_third_party = true`
  - Test: services include `driver` and `contract.thirdParty` relationships
  - Test: `dayStatus` is null when no DayStatus exists for the date
  - Test: `dayStatus` includes executor when day is executed
  - Test: `canCreateServices` is true for user with `CREATE_SERVICES` permission
  - Test: `canCreateServices` is false for user without `CREATE_SERVICES` permission
  - Test: user without `VIEW_SERVICES` permission gets 403
  - Use factories to create vehicles with specific document dates and services on specific dates
  - Follow `tests/Feature/Http/Controllers/VehicleControllerTest.php` as convention reference

- [x] Task 11: Create `tests/Feature/Http/Controllers/GanttControllerFilterTest.php` using `php artisan make:test --pest`
  - Test: municipality filter returns only vehicles with matching `municipality_id`
  - Test: municipality filter does NOT affect services (all services for the date are returned regardless of vehicle municipality — the frontend handles mapping)
  - Test: invalid municipality_id is handled gracefully (empty result or validation error)
  - Test: services for soft-deleted records are excluded
  - Use factories with multiple municipalities and vehicles

## Verification

### UI (Laravel Dusk)

Dusk browser tests in `tests/Browser/`. Use super admin credentials from `env('SUPER_ADMIN_USER')` / `env('SUPER_ADMIN_PASSWORD')`. Run `php artisan migrate:fresh --seed --no-interaction` before tests that need a clean database.

- [x] Navigate to `/gantt` and verify the Gantt chart is displayed with vehicles on the Y-axis and hours on the X-axis
- [x] Verify service bars are displayed at correct time positions for vehicles with services
- [x] Click an empty cell and verify navigation to the service create page with pre-populated vehicle/time/date
- [x] Click a service bar and verify navigation to the service edit page
- [x] Verify blocked vehicles (expired documents) display "BLOQ." label and gray styling
- [x] Verify vehicles with documents expiring within 15 days display "Prec." warning
- [x] Select a municipality in the filter and verify only matching vehicles are shown
- [x] Click previous/next day navigation and verify the Gantt reloads with the new date
- [x] Verify third-party vehicles display "3ro" label in the sidebar
- [x] Verify executed day displays "Dia Ejecutado" banner and disables empty-cell clicks

## Dependencies

- `service-form` (pending) — provides the service create/edit pages that the Gantt navigates to
- `day-status-logic` (pending) — provides DayStatus records and executed-day indicator
- `municipality-combobox` (pending) — provides the municipality filter dropdown component

## Notes

- **The existing kibo-ui Gantt component is used as the foundation.** It already provides daily range with hourly columns, drag-and-drop via `@dnd-kit`, overlapping feature handling, sidebar, and scroll management. This requirement wraps it with fleet-specific data mapping and custom components — it does NOT create a Gantt from scratch.
- **Time range is 06:00–22:00** as shown in the mockup. The kibo-ui Gantt in `daily` mode shows 24 hours by default. The initial scroll position SHOULD be set to 06:00 for usability. Services outside this range are still visible by scrolling.
- **Vehicle blocking is computed client-side.** The controller passes raw document expiry dates, and the frontend computes blocked/warning status. This avoids duplicating date logic in the backend and keeps the Gantt responsive to date changes.
- **The "15 days" warning threshold** for document expiry is hardcoded in the frontend utility. If this needs to be configurable in the future, it can be moved to a config value passed as an Inertia prop.
- **Real-time updates via Laravel Echo (Reverb)** are mentioned in the Phase 2 spec but are OUT OF SCOPE for this requirement. Real-time Gantt updates will be a separate enhancement. For now, the Gantt loads data on page visit and refreshes on navigation.
- **Drag-and-drop rescheduling** — the kibo-ui Gantt supports drag-and-drop. For this initial implementation, dragging a service bar will NOT save changes automatically. The user MUST click the bar to edit. Drag-to-reschedule (calling the update endpoint directly) can be added as a future enhancement.
- **The Gantt page is a NEW page** at `/gantt`, not a replacement of the day-statuses index (which is the calendar). The sidebar will have both "Calendario" and "Planificador Gantt" as separate navigation items.
- **The "Resumen" tab** in the header links to the day-summary page. Since that requirement is separate, the tab MUST be rendered but link to the services index filtered by date as a temporary target (same pattern as the calendar day click).
- **Service bar colors** follow the project's status color convention: orange for open (matches "projected" theme), green for closed (matches "executed" theme). This provides visual consistency with the calendar view.
- **The `planned_duration` field** is stored as integer minutes in the database. The Gantt converts this to a Date range using `addMinutes(startDate, planned_duration)` for positioning.
