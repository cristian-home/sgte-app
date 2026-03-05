---
name: day-status-logic
type: feat
scope: services
status: pending
priority: high
created_date: 2026-03-05
completed_date:
srs_refs: ["REQ-001", "REQ-009"]
migration_strategy: modify-existing
---

# Day Status Logic & Immutability Control

## Description

Implement the day status state machine and service immutability control for executed days. When the first service is created for a date, a `DayStatus` record MUST be automatically created with `status = projected`. The "Execute Day" action transitions a day to `executed` only when all services for that date are closed. Once executed, services are locked for operators, partially editable for accounting (billing fields only), and fully editable for admins with mandatory justification. All edits to executed-day services MUST be logged with the justification in the Spatie activity log's `properties` JSON column.

## Acceptance Criteria

- [ ] AC-1: WHEN the first service is created for a date that has no `DayStatus` record THEN a `DayStatus` record MUST be automatically created with `status = projected` for that date.
- [ ] AC-2: WHEN a service is created for a date that already has a `DayStatus` with `status = projected` THEN no new `DayStatus` record MUST be created.
- [ ] AC-3: WHEN the "Execute Day" action is triggered AND all services for that date have `service_status = closed` THEN the `DayStatus` MUST transition to `status = executed`, `executor_id` MUST be set to the authenticated user's ID, and `executed_at` MUST be set to the current timestamp.
- [ ] AC-4: WHEN the "Execute Day" action is triggered AND at least one service for that date has `service_status = open` THEN the action MUST fail with an error message: "No se puede ejecutar el día. Existen servicios abiertos."
- [ ] AC-5: WHEN a user with the `operator` role attempts to update a service on an executed day THEN the request MUST be rejected with a 403 response.
- [ ] AC-6: WHEN a user with the `accounting` role updates a service on an executed day THEN ONLY the fields `billing_group`, `unit_value`, `quantity`, `payment_method`, and `invoice_id` MUST be modifiable. All other fields MUST be ignored/rejected.
- [ ] AC-7: WHEN a user with the `admin` or `super_admin` role updates a service on an executed day THEN a `justification` text field MUST be required. The update MUST fail if justification is empty.
- [ ] AC-8: WHEN an admin edits a service on an executed day with justification THEN the activity log entry MUST include the justification in the `properties` JSON column alongside the old/new field values.
- [ ] AC-9: WHEN the service form is rendered for a service on an executed day by an operator THEN all fields MUST be displayed as read-only with no submit button.
- [ ] AC-10: WHEN the service form is rendered for a service on an executed day by accounting THEN only billing fields (`billing_group`, `unit_value`, `quantity`, `payment_method`) MUST be editable. All other fields MUST be read-only.

## Technical Specification

### Data Model

No new tables or columns. The existing `day_statuses` table has all required fields:

```
day_statuses
├── id (bigint, PK)
├── date (date, unique)
├── status (enum: 'projected', 'executed')
├── executor_id (bigint, FK → users.id, nullable)
├── executed_at (timestamp, nullable)
├── created_at (timestamp)
└── updated_at (timestamp)
```

The relationship between services and day statuses is through the `service_date` column — no FK needed. A DayStatus represents the state of ALL services on that date.

### Enums

No new enums. Existing enums used:

- `DayStatusEnum` (`projected`, `executed`)
- `ServiceStatus` (`open`, `closed`)
- `Permission` (uses `UPDATE_PROJECTED_SERVICES`, `UPDATE_EXECUTED_SERVICES`, `EXECUTE_DAY`)
- `Role` (uses `ADMIN`, `SUPER_ADMIN`, `OPERATOR`, `ACCOUNTING`)

### Routes

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| POST | /day-statuses/{day_status}/execute | DayStatusController@execute | auth, verified | day-statuses.execute |

All other day-status routes already exist via `Route::resource('day-statuses', DayStatusController::class)`.

### Permissions

No new permissions. Existing permissions and their role assignments:

| Permission | Admin | Operator | Accounting | Driver |
|-----------|:-----:|:--------:|:----------:|:------:|
| `UPDATE_PROJECTED_SERVICES` | Yes | Yes | No | No |
| `UPDATE_EXECUTED_SERVICES` | Yes | No | Yes | No |
| `EXECUTE_DAY` | Yes | Yes | No | No |
| `VIEW_DAY_SUMMARY` | Yes | Yes | Yes | No |

### Pages

No new pages. Changes are to existing service form behavior (read-only states, justification dialog).

## Migration Strategy

- **modify-existing**: No database migrations needed. All changes are to application logic: observer, controller methods, form request validation, and frontend form behavior.

## Tasks

### Backend

- [ ] Task 1: Create `App\Observers\ServiceObserver`
  - Register in `AppServiceProvider@boot` using `Service::observe(ServiceObserver::class)`
  - Implement `created(Service $service)` method:
    - Query `DayStatus::where('date', $service->service_date)->first()`
    - If no record exists: create `DayStatus::create(['date' => $service->service_date, 'status' => DayStatusEnum::Projected])`
    - If record already exists: do nothing
  - Implement `deleted(Service $service)` method:
    - Query remaining services for that date: `Service::where('service_date', $service->service_date)->whereNull('deleted_at')->count()`
    - If count is 0: delete the DayStatus record for that date (`DayStatus::where('date', $service->service_date)->delete()`)
    - If count > 0: do nothing
  - Follow `app/Providers/AppServiceProvider.php` for observer registration pattern

- [ ] Task 2: Add `execute` method to `DayStatusController`
  - Gate check: `Gate::authorize(Permission::EXECUTE_DAY->value)`
  - Query all services for the day: `Service::where('service_date', $dayStatus->date)->whereNull('deleted_at')`
  - Validate all services have `service_status = closed`. If any are open, return back with error: "No se puede ejecutar el día. Existen servicios abiertos."
  - Validate at least one service exists for the date. If none, return back with error: "No se puede ejecutar un día sin servicios."
  - Update the DayStatus: `$dayStatus->update(['status' => DayStatusEnum::Executed, 'executor_id' => auth()->id(), 'executed_at' => now()])`
  - Redirect back with success message: "Día ejecutado correctamente."

- [ ] Task 3: Register the execute route in `routes/web.php`
  - Add `Route::post('day-statuses/{day_status}/execute', [DayStatusController::class, 'execute'])->name('day-statuses.execute')` BEFORE the resource route to avoid conflict
  - Apply `['auth', 'verified']` middleware (same as other routes)

- [ ] Task 4: Update `ServiceUpdateRequest` to enforce day-status locking
  - In the `authorize()` method (or a custom `prepareForValidation` / `after` hook):
    - Look up the service being updated: `$service = $this->route('service')`
    - Look up the DayStatus for the service's date: `$dayStatus = DayStatus::where('date', $service->service_date)->first()`
    - If `$dayStatus?->status === DayStatusEnum::Executed`:
      - If user has role `operator` (and NOT admin/super_admin): return `false` from `authorize()` (403 forbidden)
      - If user has role `accounting` with `UPDATE_EXECUTED_SERVICES` permission: restrict allowed fields to ONLY `['billing_group', 'unit_value', 'quantity', 'payment_method', 'invoice_id']`. Override the validated data by filtering out non-billing fields. Add these restricted rules to `rules()` method.
      - If user has role `admin` or `super_admin`: require `justification` field — add rule `'justification' => ['required', 'string', 'min:10', 'max:500']` to validation rules when day is executed
    - If `$dayStatus?->status !== DayStatusEnum::Executed` or no DayStatus exists: standard validation (no locking)
  - Follow `VehicleUpdateRequest` as convention reference for conditional rules using `Rule::when()`

- [ ] Task 5: Update `ServiceController@update` to log justification for executed-day edits
  - After the standard `$service->update($validated)` call:
    - Check if the day is executed: `$dayStatus = DayStatus::where('date', $service->service_date)->first()`
    - If executed AND the request has a `justification` field:
      - Log a custom activity entry: `activity()->performedOn($service)->causedBy(auth()->user())->withProperties(['justification' => $request->input('justification'), 'edited_on_executed_day' => true])->log('Servicio editado en día ejecutado')`
    - The standard Spatie LogsActivity trait will also log the field changes automatically — this custom entry is ADDITIONAL to capture the justification context
  - Follow Spatie activity log documentation for `activity()` helper usage

- [ ] Task 6: Update `ServiceController@edit` to pass day status information
  - Look up DayStatus for the service's date: `$dayStatus = DayStatus::where('date', $service->service_date)->first()`
  - Pass `dayStatus` prop to the Inertia render (the full DayStatus model or null)
  - Pass `userPermissions` context (already shared via HandleInertiaRequests, but explicitly include `canEditExecuted` boolean for frontend convenience):
    - `canEditExecuted` = `auth()->user()->can(Permission::UPDATE_EXECUTED_SERVICES->value)`
    - `isAdmin` = `auth()->user()->hasAnyRole([Role::ADMIN, Role::SUPER_ADMIN])`

- [ ] Task 7: Update `ServiceController@store` to prevent creating services on executed days
  - In `ServiceStoreRequest` or in the controller before storing:
    - Look up DayStatus for the submitted `service_date`
    - If `$dayStatus?->status === DayStatusEnum::Executed`: reject with validation error on `service_date`: "No se pueden crear servicios en un día ejecutado."
  - This prevents backdating services into locked days

- [ ] Task 8: Update `ServiceController@destroy` to prevent deleting services on executed days
  - Before soft-deleting, check the day status
  - If day is executed AND user is not admin/super_admin: return 403
  - If day is executed AND user is admin/super_admin: allow deletion (justification not required for delete — the activity log's automatic `deleted` event suffices)

### Frontend

- [ ] Task 9: Update `resources/js/components/services/service-form.tsx` to support read-only mode
  - Add props: `dayStatus?: DayStatus | null`, `canEditExecuted?: boolean`, `isAdmin?: boolean`
  - Compute locking state:
    - `isExecutedDay = dayStatus?.status === 'executed'`
    - `isFullyLocked = isExecutedDay && !canEditExecuted && !isAdmin` (operator — all read-only)
    - `isBillingOnly = isExecutedDay && canEditExecuted && !isAdmin` (accounting — billing fields editable)
    - `isAdminEdit = isExecutedDay && isAdmin` (admin — all editable but requires justification)
  - When `isFullyLocked`:
    - All form fields MUST have `disabled={true}` attribute
    - Submit button MUST be hidden
    - Display an alert banner: "Este día está ejecutado. No se pueden modificar los servicios."
  - When `isBillingOnly`:
    - Non-billing fields MUST have `disabled={true}` (contract, vehicle, driver, date, times, municipalities, addresses, service_status)
    - Billing fields MUST remain editable: `billing_group`, `unit_value`, `quantity`, `payment_method`
    - Display an info banner: "Día ejecutado. Solo puede modificar los campos de facturación."
  - When `isAdminEdit`:
    - All fields remain editable
    - Display a warning banner: "Está editando un servicio en un día ejecutado. Se requiere justificación."
    - Show a `justification` textarea field (required, min 10 chars) at the bottom of the form before the submit button
    - Label: "Justificación del cambio"
    - Placeholder: "Explique el motivo de la modificación..."
  - Follow existing conditional field patterns in the vehicle form (e.g., `is_third_party` toggle)

- [ ] Task 10: Update `resources/js/pages/services/edit.tsx` to pass day status props
  - Receive `dayStatus`, `canEditExecuted`, `isAdmin` from controller props
  - Pass them through to `ServiceForm` component
  - Update page props TypeScript interface

- [ ] Task 11: Update `resources/js/pages/services/show.tsx` to display day execution status
  - If the service's day is executed: display a Badge or Alert with "Día Ejecutado" and the executor name + execution timestamp
  - Display format: "Ejecutado por {executor_name} el {executed_at formatted}"

### Tests

- [ ] Task 12: Create `tests/Feature/Observers/ServiceObserverTest.php` using `php artisan make:test --pest`
  - Test: creating the first service for a date auto-creates a DayStatus with `status = projected`
  - Test: creating a second service for the same date does NOT create a duplicate DayStatus
  - Test: creating a service for a different date creates a separate DayStatus
  - Test: deleting the last service for a date removes the DayStatus record
  - Test: deleting a service when other services remain on the same date does NOT remove the DayStatus
  - Use `RefreshDatabase` trait and Service/DayStatus factories

- [ ] Task 13: Create `tests/Feature/Http/Controllers/DayStatusExecuteTest.php` using `php artisan make:test --pest`
  - Test: executing a day with all services closed succeeds, sets status to `executed`, records executor_id and executed_at
  - Test: executing a day with at least one open service fails with error message
  - Test: executing a day with no services fails with error message
  - Test: unauthorized user (operator without EXECUTE_DAY) gets 403 — Note: operator HAS execute_day permission per seeder, so test with accounting role instead
  - Test: executing an already-executed day is idempotent or returns appropriate response
  - Use factories to set up services with controlled statuses

- [ ] Task 14: Create `tests/Feature/Http/Controllers/ServiceLockingTest.php` using `php artisan make:test --pest`
  - Test: operator can update a service on a projected day (normal behavior)
  - Test: operator CANNOT update a service on an executed day (403)
  - Test: accounting can update ONLY billing fields on an executed day
  - Test: accounting update with non-billing fields ignores/rejects those fields
  - Test: admin can update any field on an executed day WITH justification
  - Test: admin update on executed day WITHOUT justification fails validation
  - Test: admin update on executed day WITH justification succeeds and creates activity log entry with justification in properties
  - Test: creating a service on an executed day is rejected
  - Test: deleting a service on an executed day by operator is rejected (403)
  - Test: deleting a service on an executed day by admin succeeds
  - Use factories, create users with specific roles, and set up executed DayStatus records

- [ ] Task 15: Create `tests/Feature/Http/Controllers/ServiceLockingBillingFieldsTest.php` using `php artisan make:test --pest`
  - Test: accounting user updates `billing_group` on executed day — succeeds
  - Test: accounting user updates `unit_value` on executed day — succeeds
  - Test: accounting user updates `quantity` on executed day — succeeds
  - Test: accounting user updates `payment_method` on executed day — succeeds
  - Test: accounting user attempts to update `vehicle_id` on executed day — field is ignored/rejected
  - Test: accounting user attempts to update `driver_id` on executed day — field is ignored/rejected
  - Test: accounting user attempts to update `service_date` on executed day — field is ignored/rejected
  - Use factories with accounting role user and executed DayStatus

## Verification

### Backend (Pest Tests)

This requirement is backend logic only (no new UI pages). Verification is covered entirely by the Pest feature tests in Tasks 12-15. Key scenarios:

- ServiceObserver auto-creates/deletes DayStatus records
- Execute Day action enforces all-services-closed precondition
- Service locking by role on executed days (operator blocked, accounting billing-only, admin with justification)
- Activity log records justification for admin edits on executed days

No Dusk or curl verification is needed — all behavior is exercised through the Pest test suite.

## Dependencies

- `service-form` (pending) — provides the service create/edit form that this requirement adds locking behavior to
- `departments-municipalities-catalog` (completed) — provides Municipality model used in services

## Notes

- **Service-DayStatus relationship is by date, not FK.** A DayStatus record represents the state of all services on that calendar date. The query pattern is: `DayStatus::where('date', $service->service_date)->first()`. This is intentional — the SRS defines days as aggregate containers, not individual service attributes.
- **Justification is stored in Spatie activity log's `properties` JSON column.** No new database columns or tables are needed. The `activity()` helper creates an additional log entry with `{'justification': '...', 'edited_on_executed_day': true}` alongside the automatic field-change log from the `LogsActivity` trait.
- **Accounting's UPDATE_EXECUTED_SERVICES permission** allows editing ONLY billing fields: `billing_group`, `unit_value`, `quantity`, `payment_method`, `invoice_id`. This is enforced both server-side (form request strips non-billing fields) and client-side (non-billing fields disabled in form).
- **The ServiceObserver** handles auto-creating DayStatus on service creation and cleanup on last service deletion. It does NOT handle the transition from projected → executed (that's the explicit "Execute Day" action).
- **Reversing an executed day** (un-executing) is NOT in scope for this requirement. Once executed, the day stays executed. If this is needed in the future, it would be a separate admin action with its own justification requirements.
- **The `execute` route** uses POST (not PUT/PATCH) because it's a state transition action, not a general update. This follows REST conventions for custom actions.
- **Super Admin bypasses all gates** via the `Gate::before` callback in AppServiceProvider. This means super_admin can always edit executed services without restriction. The justification requirement still applies through form request validation (which checks role, not gate).
