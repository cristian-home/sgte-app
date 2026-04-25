---
name: datetime-timezone-handling
type: feat
scope: shared
status: pending
priority: high
created_date: 2026-04-24
completed_date:
srs_refs: [REQ-001, REQ-002, REQ-003, REQ-004, REQ-005, REQ-008, REQ-009, REQ-012]
migration_strategy: modify-existing
---

# Datetime and timezone handling — instant + event timezone storage with viewer-aware rendering

## Description

SGTE today persists service schedules as wall-clock fields with no timezone metadata:
`services.service_date` (DATE), `services.planned_start_time` / `actual_start_time` /
`actual_end_time` (TIME). The Laravel app runs in `UTC`, PostgreSQL runs in `UTC`, the host
browser runs in `America/Bogota`, and the React frontend renders these values by string-slicing
`"14:30:00" → "14:30"` with zero timezone conversion (see `resources/js/pages/gantt/components/service-bar.tsx:32`,
`resources/js/pages/services/show.tsx:73`, `resources/js/components/services/service-timeline-bar.tsx:15`).

This works today only because every operator sits in Colombia. It breaks for:

1. **Drivers/admins viewing the app from a device set to a different TZ.** The screen still
   says "14:30" because the field is a wall-clock string — that side is fine — but every
   *audit-style* timestamp (`created_at`, `reported_at`, `driver_declined_at`) is rendered with
   `new Intl.DateTimeFormat('es-CO', {...})` **without** a `timeZone` option, so it falls back
   to the browser's TZ. A driver on a phone with TZ accidentally set to UTC sees novedades
   reported "5 hours from now". Inconsistent.
2. **Future expansion to other countries / multi-tenant.** A service in Cancún (-5, no DST)
   vs one in Mexico City (-6) cannot coexist in the current schema without ambiguity.
3. **Services that cross midnight.** `actual_start_time = 23:50, actual_end_time = 01:20` →
   duration calculation goes negative.
4. **Backend "today" comparisons run in UTC.** Between 19:00 and 24:00 Bogotá, `now()->toDateString()`
   already returned the next calendar day. `ServiceStoreRequest`'s REQ-009 retroactive-entry
   check (`service_date < now()->toDateString()` triggers the manual-justification gate) and
   REQ-004/005 document expiry checks (`due_date <= service_date`) can both produce the wrong
   answer late at night.

The fix is the iCalendar / Google Calendar pattern: store every operationally scheduled or
executed event as a **UTC instant** (`TIMESTAMPTZ`) **plus an IANA timezone string**
(`'America/Bogota'`), source the TZ from the operation context (initially a per-service
column defaulted from config; multi-tenant later), and render in the viewer using the
**event's** timezone — not the browser's. The viewer's TZ is only used for genuinely instant
audit-style fields (`created_at`, audit-log entries).

Wall-clock fields with pure DATE semantics that have no time-of-day component
(`license_due_date`, `soat_due_date`, `rtm_due_date`, `operation_card_due_date`,
`contracts.start_date`/`end_date`, `invoices.issue_date`, `day_statuses.date`) stay as
`DATE` — they describe a calendar day in operations TZ. What changes is that every backend
comparison against "today" must be `Carbon::now($operationTz)->toDateString()`, never
`now()->toDateString()`.

## Acceptance Criteria

- [ ] **AC-1 — Services schema migrated to instant + TZ.** WHEN a fresh migration runs THEN
      the `services` table SHALL drop columns `service_date`, `planned_start_time`,
      `actual_start_time`, `actual_end_time`, AND it SHALL contain new columns
      `planned_start_at` (TIMESTAMPTZ NOT NULL), `actual_start_at` (TIMESTAMPTZ NULL),
      `actual_end_at` (TIMESTAMPTZ NULL), `timezone` (VARCHAR(64) NOT NULL DEFAULT
      `'America/Bogota'`), AND `service_date_local` (DATE NOT NULL, denormalized for
      day-range queries, recomputed from `planned_start_at` + `timezone` on every save).
- [ ] **AC-2 — Every plain `timestamp` migration column upgraded to `timestampTz`.** WHEN
      `php artisan db:show services` (or an equivalent inspection of every domain table)
      runs THEN every column listed under "Already instants" in the Description SHALL have
      type `TIMESTAMP WITH TIME ZONE`, never `TIMESTAMP WITHOUT TIME ZONE`.
- [ ] **AC-3 — Service form persists wall-clock through server-side conversion.** WHEN the
      service form posts `service_date=2026-04-24, planned_start_time=14:30,
      timezone=America/Bogota` THEN `ServiceStoreRequest::prepareForValidation()` MUST
      call `CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$time}", $timezone)->utc()`
      and persist `planned_start_at = 2026-04-24T19:30:00Z`,
      `service_date_local = 2026-04-24`. The frontend MUST NOT call `Date.toISOString()` on
      these wall-clock inputs at any point.
- [ ] **AC-4 — Service model exposes wall-clock accessors.** GIVEN any `Service` instance
      THEN `$service->service_date` (Y-m-d in event TZ), `$service->planned_start_local`
      (HH:mm), `$service->actual_start_local`, and `$service->actual_end_local` SHALL
      return the wall-clock projection in `$service->timezone` without any caller
      re-implementing the conversion.
- [ ] **AC-5 — Document expiry checks run against operational date.** WHEN
      `ServiceDocumentChecks::vehicleDocumentsValid` or `driverLicenseValid` runs THEN it
      MUST compare due dates against `$service->service_date_local` (operation TZ),
      never against `Carbon::now()->toDateString()` (UTC).
- [ ] **AC-6 — Operational "today" comparisons use operation TZ everywhere.** WHEN any
      backend code path compares against "today" or "now" for an operational decision
      (REQ-009 retroactive gate, REQ-004/005 expiry checks, Gantt date filter, Day Summary
      buckets, Driver portal active-services list, Dashboard KPIs) THEN it MUST call
      `Carbon::now(config('app.operation_tz'))->toDateString()`. A grep
      `grep -rE "now\(\)->toDateString\(\)" app/Http app/Support` MUST return zero hits in
      operational code paths after this REQ lands.
- [ ] **AC-7 — Central frontend datetime helper exists.** WHEN a developer needs to render
      a service time or instant THEN `resources/js/lib/datetime.ts` MUST export
      `formatEventTime(at, eventTz, opts?)`, `formatEventDate(at, eventTz, opts?)`,
      `formatEventDateTime(at, eventTz, opts?)`, `formatTimestampInViewerTz(at, opts?)`,
      and `formatTimeRange(startAt, endAt, eventTz, opts?)`. All five MUST be implemented
      on native `Intl.DateTimeFormat`. NO new npm dependency (`date-fns-tz` or similar)
      SHALL be added. Each helper SHALL accept `opts.viewerTzOverride?: string` to
      pre-wire the future viewer-TZ toggle without UI work in this REQ.
- [ ] **AC-8 — Every wall-clock render site migrated to the helper.** WHEN
      `grep -rE "function formatTime\(time" resources/js/` runs THEN it MUST return zero
      hits. WHEN `grep -rE "\.slice\(0, 5\)" resources/js/pages/services resources/js/pages/gantt resources/js/components/services` runs against fields named `*_time` THEN it MUST return zero hits. WHEN
      `grep -rE "new Intl\.DateTimeFormat\('es-CO'" resources/js/` runs THEN every match
      against an instant field MUST include a `timeZone` option.
- [ ] **AC-9 — Service form surfaces TZ confirmation tooltip.** WHEN a user types a date +
      time + selects a contract on `/services/create` THEN
      `resources/js/components/services/service-form.tsx` MUST render a tooltip near the
      time picker stating: *"Se guardará como {date} {time} {timezone} → {iso_utc}"* using
      the contract's resolved timezone (falling back to the Inertia-shared
      `config.operation_tz` when no contract is selected yet). The TZ value MUST stay
      read-only in this REQ — no TZ picker UI is built.
- [ ] **AC-10 — Cross-TZ Pest feature suite passes.** GIVEN a Pest harness that wraps
      tests in `date_default_timezone_set($tz)`, WHEN the suite runs with `$tz` set to
      each of `UTC`, `Asia/Tokyo`, `America/Los_Angeles`, `Europe/Madrid` THEN:
      (a) Service create persists `planned_start_at = 2026-04-24T19:30:00Z` and
      `service_date_local = 2026-04-24` for every host TZ;
      (b) `ServiceDocumentChecks` flags an expired SOAT when fired at 22:00 Bogotá
      regardless of host TZ;
      (c) REQ-009's `manual_entry_justification` gate fires on
      `service_date_local < today_in_operation_tz`, never against UTC today;
      (d) `GanttController?date=2026-04-24` returns the Bogotá-day services regardless of
      host TZ.
- [ ] **AC-11 — Dusk regression locks the visual contract.** GIVEN
      `tests/Browser/DatetimeTimezoneRenderTest.php` running locally via
      `./vendor/bin/sail dusk` with the Selenium browser TZ overridden to `Europe/Madrid`,
      WHEN the test navigates to `/gantt`, `/day-summary`, `/services/{id}`, and
      `/driver` for a service planned at 14:30 Bogotá THEN every page MUST render exactly
      `14:30` (event TZ), MUST NOT render error banners, AND MUST capture screenshots at
      each page for visual review. Per `feedback_run_dusk_at_feature_end.md`, the test
      MUST be run AND pass before claiming the REQ done — writing it without running it
      does not satisfy this AC.
- [ ] **AC-12 — Audit-log timestamp behaviour preserved.** WHEN a viewer in any browser TZ
      loads `/audit-log`, `/users`, `/settings/profile` THEN the rendered `created_at` /
      `updated_at` / `email_verified_at` values MUST continue to format in the **viewer's**
      TZ via `formatTimestampInViewerTz` — no regression versus current behaviour.

## Technical Specification

### Data Model

`services` (edit primary migration `2026_02_27_225424_create_services_table.php`):

```
services
├── id (bigint, PK)
├── ... (unchanged FKs and other columns)
├── service_date_local       DATE        NOT NULL    -- denormalized, in `timezone`
├── planned_start_at         TIMESTAMPTZ NOT NULL    -- UTC instant
├── planned_duration         INTEGER     NOT NULL    -- minutes (unchanged)
├── actual_start_at          TIMESTAMPTZ NULL        -- UTC instant
├── actual_end_at            TIMESTAMPTZ NULL        -- UTC instant
├── timezone                 VARCHAR(64) NOT NULL DEFAULT 'America/Bogota'
├── ... (unchanged columns: unit_value, quantity, etc.)
├── driver_declined_at       TIMESTAMPTZ NULL        -- already an instant; bump TZ
├── created_at, updated_at   TIMESTAMPTZ
└── deleted_at               TIMESTAMPTZ NULL

DROPPED: service_date, planned_start_time, actual_start_time, actual_end_time
```

Every other migration that uses `$table->timestamp()` or `$table->timestamps()` is upgraded
to `$table->timestampTz()` / `$table->timestampsTz()` — same semantics, more honest type:

- `users` (email_verified_at, two_factor_confirmed_at, created_at, updated_at)
- `jobs` (failed_at)
- `activity_log` (timestamps)
- `permission_tables`, all catalog tables (timestamps + softDeletes)
- `municipalities`, `departments`, `document_types`, `eps`, `pension_funds`,
  `severance_funds`, `incident_types`, `third_parties`, `drivers`, `vehicles`,
  `contracts`, `invoices`, `day_statuses` (executed_at + timestamps), `services`,
  `fuec_number_ranges`, `service_incidents` (reported_at + timestamps), `fuecs`
  (generated_at + timestamps), `vehicle_locations` (recorded_at + timestamps).

### Config

Add to `config/app.php`:

```php
'operation_tz' => env('OPERATION_TZ', 'America/Bogota'),
```

Add to `app/Http/Middleware/HandleInertiaRequests.php` shared props:

```php
'config' => [
    'operation_tz' => config('app.operation_tz'),
],
```

So the frontend reads the default operation TZ (and any new service inherits it from the
contract or, if missing, from this).

### Enums

None.

### Routes

None added or removed. Existing `services` and `gantt` controllers operate on the new fields.

### Permissions

None.

### Pages

No new pages. The following pages and components are touched to migrate render calls — no
new layouts:

| File | Change |
|---|---|
| `resources/js/lib/datetime.ts` | NEW — central helpers (`formatEventTime`, `formatEventDate`, `formatEventDateTime`, `formatTimestampInViewerTz`, `formatTimeRange`) |
| `resources/js/pages/services/{create,edit,show,columns}.tsx` | Replace `formatTime` slicers + service_date renders |
| `resources/js/pages/gantt/index.tsx` | Pass `service.timezone` into bars and grid |
| `resources/js/pages/gantt/components/{service-bar,hourly-grid,gantt-header}.tsx` | Replace `formatTime`; use event TZ for bar position |
| `resources/js/pages/gantt/gantt-utils.ts` | `serviceBarPosition` accepts `(plannedStartAt, plannedDuration, eventTz, gridDate)` and computes the bar offset against the grid's date in event TZ |
| `resources/js/pages/day-summary/{index,columns}.tsx` | Replace renders |
| `resources/js/pages/day-statuses/index.tsx`, `components/day-statuses/{day-services-table,month-detail}.tsx` | Replace renders |
| `resources/js/pages/driver/index.tsx` | Replace renders; service-detail flow shown to driver |
| `resources/js/pages/contracts/show.tsx`, `pages/drivers/show.tsx`, `pages/vehicles/show.tsx`, `pages/third-parties/show.tsx` | Replace nested service-card renders |
| `resources/js/pages/invoices/show.tsx`, `components/invoices/service-picker-dialog.tsx` | Replace renders |
| `resources/js/pages/service-incidents/{show,columns}.tsx`, `components/incidents/service-incident-form.tsx` | Replace `formatReportedAt` and service refs |
| `resources/js/pages/fuecs/{show,create,columns}.tsx` | Replace renders |
| `resources/js/components/services/{service-form,service-combobox,service-timeline-bar}.tsx` | Replace renders; service-form gets the TZ confirmation tooltip |
| `resources/js/pages/audit-log/columns.tsx`, `components/audit-log/audit-log-detail-sheet.tsx` | Migrate to `formatTimestampInViewerTz` (semantically same as current; centralizes the helper) |
| `resources/js/pages/vehicle-locations/{show,columns,create,edit}.tsx`, `pages/gps/map.tsx` | `recorded_at` rendered via event-TZ helper (the location's vehicle has an associated service TZ; if standalone, fall back to `operation_tz`) |
| `resources/js/lib/query-params.ts` | URL params `service_date` continues to be a wall-clock `Y-m-d`; document that it represents a day in operation TZ |

## Migration Strategy

**modify-existing** — every change lands by **editing the primary `create_*_table`
migration directly**. No new `alter_*_table` / `add_*_to_*_table` migrations are introduced.
Concretely:

- ❌ Do NOT create `2026_04_25_000000_alter_services_for_timezone.php` or any equivalent
  ALTER-style migration. The project rule (`feedback_edit_primary_migrations.md`) is to
  edit the primary migration that owns the data.
- ✅ Edit `2026_02_27_225424_create_services_table.php` in place to drop the wall-clock
  fields and add the new instant + TZ columns.
- ✅ Edit each other migration listed in **Data Model** in place to swap
  `$table->timestamp(...)` / `$table->timestamps()` for the `Tz` variants.
- ⚠ A **new** migration is only acceptable if a brand-new table is genuinely needed by
  this REQ. None is — every change in this REQ is a column-shape adjustment to existing
  tables, all of which are already owned by a primary `create_*_table` migration.

After the edits land, run:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

Staging and production currently hold no real data, so the destructive `migrate:fresh` is
safe. `ServiceFactory`, `ServiceIncidentFactory`, `FuecFactory`, `VehicleLocationFactory`,
`DayStatusFactory` are updated to emit the new field shape. Seeders that create demo
services (`database/seeders/`, `database/migrations/2026_03_13_000000_seed_catalog_data.php`)
are updated to use `planned_start_at` etc.

## Tasks

### Backend

- [ ] Edit `database/migrations/2026_02_27_225424_create_services_table.php`: drop
      `service_date`, `planned_start_time`, `actual_start_time`, `actual_end_time`; add
      `service_date_local` (DATE NOT NULL), `planned_start_at` (TIMESTAMPTZ NOT NULL),
      `actual_start_at` (TIMESTAMPTZ NULL), `actual_end_at` (TIMESTAMPTZ NULL), `timezone`
      (VARCHAR(64) NOT NULL DEFAULT `'America/Bogota'`). Change `driver_declined_at` from
      `timestamp()` to `timestampTz()`. Add an index on `service_date_local` to keep
      day-range queries fast.
- [ ] Bump every other `$table->timestamp(...)` and `$table->timestamps()` to
      `$table->timestampTz(...)` / `$table->timestampsTz()` in the migrations listed in
      **Data Model**. Follow the existing
      `database/migrations/2026_02_27_225424_create_services_table.php` and
      `database/migrations/0001_01_01_000000_create_users_table.php` as convention
      references for column ordering.
- [ ] Add `'operation_tz' => env('OPERATION_TZ', 'America/Bogota')` to `config/app.php`,
      and add a `'config' => ['operation_tz' => config('app.operation_tz')]` shared prop in
      `app/Http/Middleware/HandleInertiaRequests.php::share()`. Follow the existing
      `auth.permissions` / `auth.roles` shared props as convention.
- [ ] Update `app/Models/Service.php`:
  - [ ] Add casts: `planned_start_at`, `actual_start_at`, `actual_end_at` →
        `'immutable_datetime'`; `service_date_local` → `'immutable_date'`; `timezone` →
        `'string'`. Follow the existing `Service::$casts['driver_declined_at']` entry as
        convention.
  - [ ] Add accessors: `getServiceDateAttribute(): string` (Y-m-d in `timezone`),
        `getPlannedStartLocalAttribute(): string` (HH:mm in `timezone`),
        `getActualStartLocalAttribute(): ?string`, `getActualEndLocalAttribute(): ?string`.
        Follow the accessor pattern in `app/Models/Driver.php::getFullNameAttribute()` as
        convention.
  - [ ] Add a `saving` observer (or `Model::saving()` boot hook) that recomputes
        `service_date_local` from `planned_start_at` + `timezone`. Follow the existing
        boot hook in `app/Models/Service.php` if one exists; otherwise mirror the pattern
        from `app/Models/DayStatus.php`.
  - [ ] Update the `LogOptions::logOnly([...])` call in `Service::getActivitylogOptions()`
        to log `planned_start_at`, `actual_start_at`, `actual_end_at`, `timezone`,
        `service_date_local` instead of the dropped fields.
- [ ] Update `app/Http/Requests/ServiceStoreRequest.php` and
      `app/Http/Requests/ServiceUpdateRequest.php`:
  - [ ] Add `prepareForValidation()` that reads `service_date` (Y-m-d), `planned_start_time`
        (HH:mm), `timezone` (from request body, or the selected contract's timezone, or
        `config('app.operation_tz')` as fallback) and merges
        `planned_start_at = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$d} {$t}", $tz)->utc()->toIso8601String()`,
        `service_date_local = $d`. Follow `app/Http/Requests/ServiceStoreRequest.php`'s
        existing `validateExecutedDayRestriction()` / `validateContractCoversDate()`
        helpers as convention for the structure.
  - [ ] Add validation rules: `planned_start_at` → `['required', 'date']`; `timezone` →
        `['required', 'string', Rule::in(timezone_identifiers_list())]`;
        `service_date_local` → `['required', 'date_format:Y-m-d']`. Keep `service_date` and
        `planned_start_time` validations as cosmetic frontend helpers (still required for
        the form, but the persisted values are the instant + TZ).
- [ ] Update `app/Support/ServiceDocumentChecks.php`: `vehicleDocumentsValid` and
      `driverLicenseValid` SHALL accept a `CarbonImmutable $evaluationDate` parameter
      (instead of reading `$service->service_date` internally). Callers MUST pass the
      operation-TZ "today" or `$service->service_date_local`. Follow the existing
      signature and helper structure of the file.
- [ ] Replace every `now()->toDateString()` / `Carbon::today()` /
      `Carbon::now()->subDays()` call in operational code paths with the operation-TZ
      equivalent: `Carbon::now(config('app.operation_tz'))->toDateString()`. Files:
      `app/Http/Requests/ServiceStoreRequest.php`,
      `app/Http/Requests/ServiceUpdateRequest.php`,
      `app/Support/ServiceDocumentChecks.php`,
      `app/Http/Controllers/GanttController.php`,
      `app/Http/Controllers/DashboardController.php`,
      `app/Http/Controllers/DayStatusController.php`,
      `app/Http/Controllers/DayStatusExecutionController.php`,
      `app/Http/Controllers/DriverDashboardController.php`.
- [ ] `GanttController::index` filters services with
      `whereDate('service_date_local', $date)` (no semantic change; field rename).
      Verify the `$serviceDateCarbon` argument passed into
      `ServiceDocumentChecks::vehicleDocumentsValid()` is constructed with the operation TZ.
- [ ] Update `database/factories/ServiceFactory.php`,
      `database/factories/ServiceIncidentFactory.php`,
      `database/factories/FuecFactory.php`,
      `database/factories/VehicleLocationFactory.php`,
      `database/factories/DayStatusFactory.php` to emit the new field shape. Follow the
      existing `ServiceFactory::definition()` time-of-day generation as convention; the
      new factory MUST persist `planned_start_at` directly (not the wall-clock projection).
- [ ] Update seeders that create demo services
      (`database/seeders/DatabaseSeeder.php` if it spawns services, and
      `database/migrations/2026_03_13_000000_seed_catalog_data.php` if it does).

### Frontend

- [ ] Create `resources/js/lib/datetime.ts` exporting the five helpers
      (`formatEventTime`, `formatEventDate`, `formatEventDateTime`,
      `formatTimestampInViewerTz`, `formatTimeRange`) per **AC-7**. All MUST be
      implemented on native `Intl.DateTimeFormat`. Follow `resources/js/lib/date-utils.ts`
      as convention reference for module shape, exports, and JSDoc style. Do NOT add a
      new npm dependency.
- [ ] Update `Service` TypeScript type in `resources/js/types/models.ts`: add
      `planned_start_at: string`, `actual_start_at: string | null`,
      `actual_end_at: string | null`, `timezone: string`,
      `service_date_local: string`. Add accessor-fed read-only fields
      `service_date: string`, `planned_start_local: string`,
      `actual_start_local: string | null`, `actual_end_local: string | null` (the backend
      Service model exposes these via accessors). Drop `planned_start_time`,
      `actual_start_time`, `actual_end_time` from the type — they no longer exist on the
      wire.
- [ ] Migrate every render site listed in the Pages table. Each migration MUST replace the
      ad-hoc `formatTime` / `time.slice(0, 5)` / locally-declared
      `new Intl.DateTimeFormat('es-CO', {...})` block with a call to the central helper
      from `resources/js/lib/datetime.ts`. Follow
      `resources/js/pages/gantt/components/service-bar.tsx` as the canonical "before"
      shape and the post-migration version of the same file as the canonical "after"
      shape.
- [ ] Update `resources/js/components/services/service-form.tsx` to display the
      "Se guardará como…" tooltip near the time picker per **AC-9**. Source the timezone
      from the selected contract (read from the contracts list passed via Inertia props)
      or fall back to `config.operation_tz` from the shared Inertia prop. Follow the
      existing `<InputError />` / hint paragraph patterns in the same file as convention
      for placement.
- [ ] Confirm forms continue to send the wall-clock strings (`service_date`,
      `planned_start_time`) as plain text. No frontend `Date.toISOString()` SHALL be
      introduced for these fields. The conversion to instant happens server-side per
      **AC-3**.
- [ ] Run `npm run lint && npm run types && npm run format:check`. All MUST pass without
      errors.

### Tests

- [ ] Create `tests/Helpers/Tz.php` exporting a `withTimezone(string $tz, Closure $fn)`
      helper that wraps `$fn` with `date_default_timezone_set($tz)` and restores the
      previous TZ in a `finally` block. Follow the existing
      `tests/Feature/Http/Requests/ServiceBusinessRulesTest.php` style for module layout.
- [ ] Create `tests/Feature/Services/ServiceCreateAcrossTimezonesTest.php`. WHEN the test
      submits a service create with `service_date=2026-04-24, planned_start_time=14:30,
      timezone=America/Bogota` from each of `UTC`, `Asia/Tokyo`, `America/Los_Angeles`,
      `Europe/Madrid` THEN the persisted `planned_start_at` MUST equal
      `2026-04-24T19:30:00Z` and `service_date_local` MUST equal `2026-04-24` for every
      host TZ. Follow `tests/Feature/Http/Controllers/ServiceControllerTest.php` as
      convention for route auth setup and HTTP assertions.
- [ ] Create `tests/Feature/Services/ServiceRetroactiveGateTest.php`. WHEN host TZ is
      `Asia/Tokyo` and operation TZ is `America/Bogota` and the request fires at
      02:30Z (= 21:30 prev-day Bogotá = 11:30 same-day Tokyo) THEN
      `ServiceStoreRequest`'s REQ-009 retroactive gate MUST fire only when
      `service_date_local < today_in_Bogota`, not when `service_date_local <
      today_in_Tokyo` or `today_in_UTC`. Follow
      `tests/Feature/Http/Requests/ServiceBusinessRulesTest.php` as convention.
- [ ] Extend `tests/Feature/Http/Requests/ServiceBusinessRulesTest.php` (or create
      `tests/Feature/Services/ServiceDocumentChecksAcrossTimezonesTest.php` if the
      existing file is already crowded) with cross-TZ cases for SOAT / RTM / T.O. /
      licencia expiry. WHEN the request fires at 22:00 Bogotá (= next-day UTC) AND the
      vehicle's SOAT due date equals service-date-local THEN the validator MUST flag
      the document as expired regardless of host TZ.
- [ ] Create `tests/Feature/Http/Controllers/GanttControllerDateFilterTest.php`. WHEN
      `GET /gantt?date=2026-04-24` runs from each of the four host TZs THEN the response
      MUST list every service whose `service_date_local = 2026-04-24`. Follow
      `tests/Feature/Http/Controllers/ServiceControllerTest.php` as convention.
- [ ] Create `tests/Browser/DatetimeTimezoneRenderTest.php`. GIVEN a Bogotá-TZ service
      planned at 14:30 AND the Selenium browser TZ overridden to `Europe/Madrid` (via
      Chromium emulation in the Dusk DriverFactory), WHEN the test navigates to
      `/gantt?date={service_date_local}`, `/day-summary?date={service_date_local}`,
      `/services/{id}`, and `/driver` (logged in as the assigned driver) THEN every page
      MUST render exactly `14:30`, MUST NOT show error banners, and the test MUST capture
      a screenshot per page. Follow `tests/Browser/GanttBlockedServiceTest.php` and
      `tests/Browser/ServiceDetailTest.php` as convention. The test MUST be run via
      `./vendor/bin/sail dusk --filter=DatetimeTimezoneRenderTest` AND pass before
      claiming the REQ done — per `feedback_run_dusk_at_feature_end.md`, writing-without-running
      does not satisfy this task.
- [ ] Create `tests/Unit/Support/Datetime/HelperTest.php` (Pest) that exercises pure
      formatter logic for every `formatEventTime` / `formatTimeRange` edge case
      (cross-midnight span, DST boundary, viewerTzOverride pass-through). For the JS
      helpers in `resources/js/lib/datetime.ts`, add a Vitest spec at
      `resources/js/lib/__tests__/datetime.test.ts` if the project already has a Vitest
      setup; if not, document this as deferred and rely on the Dusk visual regression.

## Verification

### 1. Interactive verification — Playwright MCP

Reference users (all password `password`):

| Role | Email |
|---|---|
| Admin | `admin@sgte.app` |
| Operator | `operator@sgte.app` |
| Driver | `driver@sgte.app` |
| Accounting | `accounting@sgte.app` |

- [ ] Create a service via `/services/create` with `Fecha=2026-04-24, Hora=14:30, TZ=Bogotá`,
      navigate to `/gantt?date=2026-04-24`, assert via snapshot the bar lands in column
      `14:00–16:00`. Inspect `mcp__laravel-boost__database-query` to confirm
      `planned_start_at = 2026-04-24T19:30:00Z`, `service_date_local = 2026-04-24`,
      `timezone = America/Bogota`.
- [ ] Repeat the snapshot with browser context override
      (`mcp__playwright__browser_evaluate` setting a fake `Intl.DateTimeFormat` resolved
      timezone to `Europe/Madrid` — or relaunch MCP with TZ env override). Expected:
      same `14:30` displayed because we render in the event TZ, not the viewer's.
- [ ] Driver portal: log in as `driver@sgte.app`, confirm the assigned service still
      reads `14:30` regardless of the device TZ.

### 2. Backend regression — Pest feature tests

Listed in **Tasks → Tests** above. Must pass before merging.

### 3. UI regression — Dusk

- [ ] `tests/Browser/DatetimeTimezoneRenderTest.php` runs locally via
      `./vendor/bin/sail dusk` with a non-Bogotá Selenium TZ and screenshots show `14:30`
      across Gantt, Day Summary, and driver portal. Per `feedback_run_dusk_at_feature_end.md`,
      Dusk is mandatory and must be run, not just written.

### 4. API endpoints

No public API surface in this REQ.

## Dependencies

- Already-merged **document-expiry-service-date-recheck** REQ — `ServiceDocumentChecks`
  is the entry point we re-target.
- Already-merged **driver-preflight-decline-action** REQ — `driver_declined_at` exists.
- No external package additions. `Intl.DateTimeFormat` is native; `CarbonImmutable` ships
  with Laravel; `timezone_identifiers_list()` ships with PHP's `intl`/timezone extension.

## Notes

### Decision log

**Why store both instant + TZ (not just one)?** Storing only the instant loses the
operational anchor — "was this 14:30 Bogotá or 14:30 Lima?" — and breaks DST (relevant for
multi-tenant in Mexico/USA/Europe). Storing only wall-clock + TZ makes ordering/queries
expensive. Both is the iCalendar / Google Calendar pattern.

**Why render in JS, not PHP?** Three reasons:
1. The future "view in my local TZ" toggle is only viable client-side without an extra
   round trip.
2. `Intl.DateTimeFormat({ timeZone })` is native, free, supports every IANA TZ via the V8
   ICU bundle, and works identically in Node SSR.
3. Decoupling the API contract from presentation: the wire format ships
   `{ planned_start_at: ISO, timezone: 'America/Bogota' }` and presentation lives in one
   helper. PHP only converts on write (form → instant) and on rule comparisons.

**Why keep `service_date_local` as a denormalized DATE column?** Day-bucket queries
(`WHERE service_date_local = '2026-04-24'`) are dominant (Gantt, Day Summary, Annual
Calendar). Computing it from `planned_start_at AT TIME ZONE timezone` per query is doable
but adds a function call to every WHERE clause and prevents simple BTree indexing on the
date. Keeping it denormalized via a model observer is simpler and faster.

**Why not `date-fns-tz`?** Bundle size (~13 KB minzipped + IANA data) and an unnecessary
dependency. `Intl.DateTimeFormat({ timeZone })` covers every use case in this REQ. If a
later REQ wants nicer DSL (`formatDistance`, parsing), revisit.

**Why edit primary migrations vs. add a new migration?** Project memory rule
`feedback_edit_primary_migrations.md`: stg/prod hold no real data. Editing the primary
migration keeps the migration history clean and avoids a backfill migration that would
become dead weight forever.

### Out of scope

- **Per-municipality TZ tables.** A single global `operation_tz` plus a per-service column
  is enough for the Bogotá-and-future-multi-country case. When a real second TZ ships, we
  move the default to `contracts.timezone` and inherit on service create.
- **Rewriting historical activity_log entries.** Stale wall-clock JSON in
  `activity_log.properties` stays as-is. Only forward writes use the new shape.
- **Admin UI to pick a service's TZ.** The TZ is read-only (sourced from contract /
  config). If admins ever need to override per-service, that's a new REQ.
- **Frontend `viewer-tz` toggle (nice-to-have).** The helper API in `lib/datetime.ts`
  accepts a `viewerTzOverride?: string` argument from day one, so the future UI is just
  a setting in `/settings/profile.tsx` plus a small badge in event renders. Not part of
  this REQ.

### Follow-ups (nice-to-have, not blocking)

- Frontend toggle "Ver en mi hora local" with persistent user preference (≈30 LOC of UI
  on top of the already-supported helper API).
- Horizon job for proactive 30/7/1-day expiry alerts (already noted in
  `document-expiry-service-date-recheck.md` as optional phase 2; orthogonal to this REQ).
- Per-contract `timezone` column once multi-country becomes real.
