---
name: service-form
type: feat
scope: services
status: completed
priority: high
created_date: 2026-03-05
completed_date: 2026-03-05
srs_refs: ["REQ-003"]
migration_strategy: modify-existing
---

# Service Form (Create, Edit, Show)

## Description

Implement the full service form (create + edit) and a read-only detail view (show) for the Service module. The service form is the primary data entry point for dispatchers to record transport services. It includes conditional logic for third-party vehicles (COD 18), schedule conflict validation, driver eligibility checks, execution time tracking, and billing fields. This requirement covers REQ-003 from the SRS.

## Acceptance Criteria

- [x] AC-1: WHEN the user selects a vehicle with `is_third_party = true` THEN the driver field MUST be hidden, `driver_id` MUST be set to `null`, and the associated provider (vehicle's `thirdParty`) MUST be displayed as read-only info.
- [x] AC-2: WHEN the user selects a vehicle with `is_third_party = false` THEN the driver field MUST be visible and required.
- [x] AC-3: WHEN a service is submitted with a vehicle that already has an overlapping service on the same `service_date` (time ranges overlap based on `planned_start_time` + `planned_duration`) THEN validation MUST fail with a specific error message indicating the conflict.
- [x] AC-4: WHEN a service is submitted with a driver that already has an overlapping service on the same `service_date` THEN validation MUST fail with a specific error message indicating the conflict.
- [x] AC-5: WHEN the driver dropdown is populated THEN drivers with `license_due_date < today` MUST be excluded from the selectable options.
- [x] AC-6: WHEN the user selects a driver that has `eps_id = null` OR `pension_fund_id = null` THEN a warning message MUST be displayed, but the selection MUST still be allowed.
- [x] AC-7: WHEN the contract dropdown is populated THEN only contracts with `active = true` AND `start_date <= service_date` AND `end_date >= service_date` MUST be shown.
- [x] AC-8: WHEN the create form is submitted with valid data THEN a new Service record MUST be created and the user MUST be redirected to the services index.
- [x] AC-9: WHEN the edit form is submitted with valid data THEN the Service record MUST be updated and the user MUST be redirected to the services index.
- [x] AC-10: WHEN the show page is accessed THEN all service fields MUST be displayed in read-only format with related entity details (contract, vehicle, driver, municipalities).
- [x] AC-11: WHEN the edit or show page is accessed for a service with incidents THEN a badge MUST display the count of related `serviceIncidents`.
- [x] AC-12: WHEN the service_status is changed to `closed` THEN `actual_start_time` and `actual_end_time` MUST be required.

## Technical Specification

### Data Model

No new tables or columns. The existing `services` table and `Service` model already have all required fields. Changes are limited to validation rules, controller logic, and frontend pages.

### Enums

No new enums. Existing enums used:

- `ServiceStatus` (`open`, `closed`)
- `PaymentMethod` (`cash`, `credit`, `transfer`)

### Routes

No new routes needed. The existing resource routes for services already cover all CRUD actions:

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | /services | ServiceController@index | auth, verified | services.index |
| GET | /services/create | ServiceController@create | auth, verified | services.create |
| POST | /services | ServiceController@store | auth, verified | services.store |
| GET | /services/{service} | ServiceController@show | auth, verified | services.show |
| GET | /services/{service}/edit | ServiceController@edit | auth, verified | services.edit |
| PUT | /services/{service} | ServiceController@update | auth, verified | services.update |
| DELETE | /services/{service} | ServiceController@destroy | auth, verified | services.destroy |

### Permissions

No new permissions. Existing permissions used:

- `VIEW_SERVICES` — gate on index, show
- `CREATE_SERVICES` — gate on create, store
- `UPDATE_PROJECTED_SERVICES` — gate on edit, update
- `DELETE_SERVICES` — gate on destroy

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Create | `resources/js/pages/services/create.tsx` | Form to create a new service |
| Edit | `resources/js/pages/services/edit.tsx` | Form to edit an existing service |
| Show | `resources/js/pages/services/show.tsx` | Read-only detail view |
| Form Component | `resources/js/components/services/service-form.tsx` | Reusable form component shared by create and edit |

## Migration Strategy

- **modify-existing**: No database migrations needed. All changes are to validation logic (form requests), controller methods, and frontend pages. The existing migration files remain unchanged.

## Tasks

### Backend

- [x] Task 1: Update `ServiceController@create` to pass reference data for the form
  - Gate check: `Permission::CREATE_SERVICES`
  - Pass `vehicles` — all active vehicles with `thirdParty` relationship eager-loaded: `Vehicle::query()->where('status', VehicleStatus::Active)->with('thirdParty:id,identification_number,first_name,first_lastname,company_name,is_natural_person')->get(['id', 'plate', 'is_third_party', 'third_party_id'])`
  - Pass `drivers` — all drivers with valid license (`license_due_date >= today`), include `eps_id` and `pension_fund_id` for social security warning: `Driver::query()->where('license_due_date', '>=', now()->toDateString())->get(['id', 'first_name', 'first_lastname', 'document_number', 'license_due_date', 'eps_id', 'pension_fund_id'])`
  - Pass `contracts` — all active contracts: `Contract::query()->where('active', true)->with('thirdParty:id,identification_number,first_name,first_lastname,company_name,is_natural_person')->get(['id', 'contract_number', 'third_party_id', 'contract_object', 'start_date', 'end_date', 'is_generic'])`
  - Pass `municipalities` — all municipalities with department: `Municipality::query()->with('department:id,name')->get(['id', 'name', 'department_id'])`
  - Follow `VehicleController@create` as convention reference

- [x] Task 2: Update `ServiceController@edit` to pass reference data and the service
  - Gate check: `Permission::UPDATE_PROJECTED_SERVICES`
  - Eager-load service relationships: `contract`, `vehicle.thirdParty`, `driver`, `originMunicipality.department`, `destinationMunicipality.department`
  - Load incident count: `$service->loadCount('serviceIncidents')`
  - Pass same reference data as create (vehicles, drivers, contracts, municipalities)
  - Follow `VehicleController@edit` as convention reference

- [x] Task 3: Update `ServiceController@show` to pass the service with all relationships
  - Gate check: `Permission::VIEW_SERVICES`
  - Eager-load: `contract.thirdParty`, `vehicle.thirdParty`, `driver`, `originMunicipality.department`, `destinationMunicipality.department`, `invoice`
  - Load incident count: `$service->loadCount('serviceIncidents')`
  - Follow existing show page patterns

- [x] Task 4: Update `ServiceController@store` to use Gate check
  - Add `Gate::authorize(Permission::CREATE_SERVICES->value)` if not already present
  - Ensure redirect goes to `services.index` with success flash message

- [x] Task 5: Update `ServiceController@update` to use correct Gate check
  - Add `Gate::authorize(Permission::UPDATE_PROJECTED_SERVICES->value)`
  - Ensure redirect goes to `services.index` with success flash message

- [x] Task 6: Create custom validation rule `App\Rules\NoScheduleConflict`
  - Constructor accepts: `string $field` (`vehicle_id` or `driver_id`), `int $fieldValue`, `string $serviceDate`, `string $plannedStartTime`, `int $plannedDuration`, `?int $excludeServiceId` (for edit)
  - Rule logic: Query `services` table for records matching the same `$field` value and `service_date`, excluding the current service (if editing) and soft-deleted records
  - Calculate time overlap: A conflict exists when `existing_start < new_end AND new_start < existing_end` where end = start + duration in minutes
  - Return failure message: "El {vehículo|conductor} ya tiene un servicio asignado en este horario ({HH:MM} - {HH:MM})."
  - Follow `app/Rules/` directory for existing rule patterns (create directory if it doesn't exist)

- [x] Task 7: Update `ServiceStoreRequest` validation rules
  - `contract_id`: required, integer, exists:contracts,id — add custom validation to ensure contract is active and covers the service_date (use `Rule::when` or `after` hook)
  - `vehicle_id`: required, integer, exists:vehicles,id — add `NoScheduleConflict` rule
  - `driver_id`: Change to conditionally required — `required_unless:is_third_party_vehicle,true` (or use `after` validation hook to check vehicle's `is_third_party` flag); when provided, validate `exists:drivers,id` and `NoScheduleConflict` rule
  - `actual_start_time`: required when `service_status = closed` (use `Rule::requiredIf`)
  - `actual_end_time`: required when `service_status = closed`, must be `after:actual_start_time`
  - Add `is_third_party_vehicle` as a boolean field (sent from frontend to assist conditional validation) or look up the vehicle in the `after` hook
  - Keep all other existing rules unchanged
  - Follow `VehicleStoreRequest` as convention reference for conditional rules

- [x] Task 8: Update `ServiceUpdateRequest` validation rules
  - Same rules as `ServiceStoreRequest` but pass `$this->route('service')->id` as `excludeServiceId` to `NoScheduleConflict` rule
  - Follow `VehicleUpdateRequest` as convention reference

### Frontend

- [x] Task 9: Create `resources/js/components/services/service-form.tsx` — reusable form component
  - Props interface: `{ data, setData, errors, vehicles, drivers, contracts, municipalities, incidentCount?, mode: 'create' | 'edit' }`
  - Layout: Grid with sections — "Datos del Servicio", "Origen y Destino", "Horarios", "Facturación"
  - **Section: Datos del Servicio**
    - `service_date` — date input (required)
    - `contract_id` — searchable select/combobox; filter options client-side by `service_date` (show only contracts where `start_date <= service_date <= end_date`); display: `contract_number - thirdParty.company_name` (or natural person name)
    - `vehicle_id` — searchable select/combobox; display: `plate`; on change: check `is_third_party` flag
    - `driver_id` — searchable select/combobox (hidden when selected vehicle is `is_third_party`); display: `first_name first_lastname (document_number)`; show warning icon/tooltip when driver has `eps_id = null` or `pension_fund_id = null` with text "El conductor no tiene seguridad social completa"
    - Provider info — read-only display shown when vehicle is `is_third_party` (show `vehicle.thirdParty.company_name` or natural person name)
    - `service_status` — select with ServiceStatus enum options (Abierto / Cerrado)
  - **Section: Origen y Destino**
    - `origin_municipality_id` — municipality dropdown (uses municipality dropdown component from prerequisite)
    - `origin_address` — text input
    - `destination_municipality_id` — municipality dropdown
    - `destination_address` — text input
  - **Section: Horarios**
    - `planned_start_time` — time input (required)
    - `planned_duration` — number input in minutes (required)
    - `actual_start_time` — time input (required when status = closed)
    - `actual_end_time` — time input (required when status = closed)
    - Display calculated actual duration as read-only when both actual times are provided: `actual_end_time - actual_start_time` formatted as "{N} min"
  - **Section: Facturación**
    - `billing_group` — text input
    - `unit_value` — number input (decimal, COP formatting hint)
    - `quantity` — number input (integer, default 1)
    - `payment_method` — select with PaymentMethod enum options
  - **Incidents badge** (edit mode only): Show badge with `incidentCount` next to section header or page title, linking to incidents list filtered by this service (if route exists)
  - Follow `resources/js/components/vehicles/vehicle-form.tsx` as convention reference for layout, grid, error display, and conditional field patterns

- [x] Task 10: Implement `resources/js/pages/services/create.tsx`
  - Props: `{ vehicles, drivers, contracts, municipalities }` (from controller)
  - Initialize `useForm` with default values: empty strings for text/select, empty string for date, `'open'` for service_status, `'credit'` for payment_method, `'1'` for quantity
  - Breadcrumbs: Servicios (index) > Crear
  - Submit: `post(ServiceController.store().url)`
  - Cancel: Link to `services.index().url`
  - Use `<Can permission={Permission.CREATE_SERVICES}>` if needed for conditional UI elements
  - Follow `resources/js/pages/vehicles/create.tsx` as convention reference

- [x] Task 11: Implement `resources/js/pages/services/edit.tsx`
  - Props: `{ service, vehicles, drivers, contracts, municipalities }` (from controller)
  - Initialize `useForm` with service data, casting IDs to strings as per vehicle edit pattern
  - Include `service_incidents_count` from loaded count for incidents badge
  - Breadcrumbs: Servicios (index) > Editar
  - Submit: `put(ServiceController.update(service.id).url)`
  - Cancel: Link to `services.index().url`
  - Follow `resources/js/pages/vehicles/edit.tsx` as convention reference

- [x] Task 12: Implement `resources/js/pages/services/show.tsx`
  - Props: `{ service }` (from controller, with all relationships eager-loaded)
  - Read-only layout using Card components with labeled fields in a grid
  - Display related entity names (not IDs): contract number, vehicle plate, driver full name, municipality names with department
  - Display origin/destination as: "Municipality Name (Department) — Address"
  - Display times formatted as HH:MM, durations as "{N} min"
  - Display currency values formatted as COP
  - Display service_status and payment_method as Badge components with labels
  - Display incidents count badge
  - Action buttons: "Editar" (link to edit, gated by `Permission.UPDATE_PROJECTED_SERVICES`), "Volver" (link to index)
  - Breadcrumbs: Servicios (index) > Ver
  - Follow existing show page patterns and use Card/grid layout

- [x] Task 13: Update `resources/js/types/models.ts` if needed
  - Ensure `Vehicle` type includes `is_third_party` and `third_party_id` with optional `thirdParty?: ThirdParty`
  - Ensure `Driver` type includes `eps_id`, `pension_fund_id`, `license_due_date`
  - Ensure `Service` type includes optional relationship types for `originMunicipality`, `destinationMunicipality` with nested `department`
  - Add `service_incidents_count?: number` to Service type (for withCount)

- [x] Task 14: Update `resources/js/pages/services/columns.tsx` — add link to show page
  - Make the service date or a "Ver" action link to `ServiceController.show(service.id).url`
  - Ensure edit action links to `ServiceController.edit(service.id).url`

### Tests

- [x] Task 15: Create `tests/Feature/Http/Controllers/ServiceControllerTest.php` using `php artisan make:test --pest`
  - Test `create` returns view with vehicles, drivers, contracts, municipalities props
  - Test `create` excludes drivers with expired license from drivers prop
  - Test `store` creates a service with valid data and redirects
  - Test `store` fails validation when required fields are missing
  - Test `store` fails when contract is inactive
  - Test `store` fails when contract date range does not cover service_date
  - Test `edit` returns view with service and reference data
  - Test `update` updates service and redirects
  - Test `show` returns view with service and eager-loaded relationships
  - Test `destroy` soft-deletes the service
  - Test unauthorized users cannot access create/store/edit/update/destroy
  - Use factories for all model creation; follow `tests/Feature/Http/Controllers/VehicleControllerTest.php` as convention reference

- [x] Task 16: Create `tests/Feature/Rules/NoScheduleConflictTest.php` using `php artisan make:test --pest`
  - Test no conflict when vehicle has no other services on the date
  - Test conflict detected when vehicle has overlapping service (partial overlap start)
  - Test conflict detected when vehicle has overlapping service (partial overlap end)
  - Test conflict detected when vehicle has fully enclosed service
  - Test no conflict when times are adjacent but not overlapping
  - Test no conflict when same vehicle has service on different date
  - Test excludeServiceId correctly excludes current service during edit
  - Test driver conflict detection (same scenarios as vehicle)
  - Use `RefreshDatabase` trait and Service factory

- [x] Task 17: Create `tests/Feature/Http/Controllers/ServiceControllerCod18Test.php` using `php artisan make:test --pest`
  - Test store succeeds without driver_id when vehicle is third-party (`is_third_party = true`)
  - Test store fails without driver_id when vehicle is NOT third-party
  - Test store sets driver_id to null when vehicle is third-party even if driver_id is provided
  - Use factories with appropriate states for third-party vehicles

- [x] Task 18: Create `tests/Feature/Http/Controllers/ServiceControllerStatusTest.php` using `php artisan make:test --pest`
  - Test store fails when `service_status = closed` and `actual_start_time` is missing
  - Test store fails when `service_status = closed` and `actual_end_time` is missing
  - Test store succeeds when `service_status = closed` and both actual times are provided
  - Test store succeeds when `service_status = open` and actual times are null

## Verification

### UI (Laravel Dusk)

Dusk browser tests in `tests/Browser/`. Use super admin credentials from `env('SUPER_ADMIN_USER')` / `env('SUPER_ADMIN_PASSWORD')`. Run `php artisan migrate:fresh --seed --no-interaction` before tests that need a clean database.

- [x] Navigate to `/services/create` and verify all form sections are displayed (Datos del Servicio, Origen y Destino, Horarios, Facturacion)
- [x] Select a third-party vehicle and verify the driver field is hidden and provider info is displayed
- [x] Select a non-third-party vehicle and verify the driver field is visible and required
- [x] Submit the form with valid data and verify redirect to services index with success message
- [x] Navigate to `/services/{id}` and verify all fields are displayed in read-only format
- [x] Navigate to `/services/{id}/edit` and verify the form is pre-populated with service data

## Dependencies

- `departments-municipalities-catalog` (completed) — provides Municipality model and FK columns on services table
- Municipality dropdown component (pending — separate prerequisite requirement) — provides the reusable municipality selector used in origin/destination fields

## Notes

- **Day locking (EJECUTADO)** is explicitly OUT OF SCOPE for this requirement. The form does not enforce read-only behavior based on day status. That will be handled in the separate `day-status-logic` requirement, which will add middleware/gate checks and frontend read-only state.
- **Gantt integration** is OUT OF SCOPE. The Gantt view will link to this form with pre-populated vehicle/time values, but that interaction is defined in the `daily-gantt` requirement.
- The `origin_coordinates` and `destination_coordinates` fields exist in the model but are NOT included in the form — they are reserved for future GPS integration.
- The `invoice_id` field is NOT included in the form — invoice assignment is handled in the invoicing module (Phase 4).
- The contract dropdown filtering by date range is done CLIENT-SIDE in the React form component. All active contracts are passed from the controller; the form filters them based on the selected `service_date`. This avoids extra API calls when the user changes the service date.
- The driver dropdown excludes expired-license drivers SERVER-SIDE (controller query). The social security warning is displayed CLIENT-SIDE based on `eps_id` / `pension_fund_id` values in the passed driver data.
- Schedule conflict validation (NoScheduleConflict rule) runs SERVER-SIDE in the form request. No client-side pre-validation of conflicts is required for this iteration.
- The `VehicleStatus::Active` enum is used to filter vehicles — only active vehicles appear in the dropdown. Vehicles in maintenance or retired status are excluded.
