---
name: gps-tracking
type: feat
scope: vehicle-locations
status: pending
priority: high
created_date: 2026-04-18
completed_date:
srs_refs: ["REQ-010"]
migration_strategy: modify-existing
---

# Implement REQ-010 — optional vehicle GPS tracking (driver capture + admin map + auto-capture on service events)

## Description

Vehicle location tracking is OPTIONAL per SRS (per §REQ-010 preamble: "GPS usage is OPTIONAL. Location may be captured automatically via the mobile device's GPS or entered manually using coordinates."). This requirement ships the full module end-to-end behind the `SGTE_GPS_ENABLED` feature flag that was already piped through `HandleInertiaRequests` + `app-sidebar.tsx` during `fuec-generation`.

Today the scaffold is: a `vehicle_locations` table with (vehicle_id, recorded_at, latitude decimal(10,8), longitude decimal(11,8), is_manual, timestamps) and no service/user association; a `VehicleLocation` model that uses `LogsActivity` + `Searchable` (Scout — unneeded for volatile time-series); a `VehicleLocationController` that is a plain Blueprint resource gated on `VIEW_VEHICLES`; four `resources/js/pages/vehicle-locations/*.tsx` pages dumping JSON; no GPS-specific permissions in `Permission.php`. No driver-side capture UX exists — `driver/index.tsx` shows service cards with confirmStart/confirmEnd buttons but no location controls.

SRS §REQ-010 (lines 546–561) mandates four capabilities that this requirement delivers:

1. **Record + display current vehicle location** when a vehicle is in service.
2. **Store coordinates + timestamp + is_manual flag** on driver update.
3. **Map view of active vehicles** — a dispatcher watching real-time fleet position.
4. **MUST NOT block operations** when GPS is unavailable — graceful fallback to manual entry; failures never reject a service confirmation.

Four design decisions were made explicitly during Q&A:

1. **Driver UX location**: the GPS capture controls render **inline on the existing `driver/index.tsx` dashboard** — each service card gets an "Ubicación GPS" section with two buttons. No new page, no new driver-side controller method. Matches the existing confirmStart/confirmEnd pattern.
2. **Map tile provider**: **OpenStreetMap** — free, no API key, reliable. ODbL attribution required in the corner.
3. **Map refresh mechanism**: **30-second Inertia polling** via `router.reload({ only: ['activeServices'] })`. Matches how the gantt + day-summary pages refresh today. Low-ceremony; no new infra.
4. **Vehicle picker**: a new shared **`<VehicleCombobox />`** primitive parallel to `<UserCombobox />`, `<ThirdPartyCombobox />`, `<MunicipalityCombobox />`. Searchable by plate + brand + line. Used by the vehicle-locations index filter + create form; reusable by future screens (gantt filter, driver assignment).

Auto-capture on service events is the "make the happy path free" feature: when a driver taps confirmStart or confirmEnd, the browser's geolocation API is invoked, the coordinates piggyback on the existing POST, and a `VehicleLocation` row is persisted in the same request. Failures here (permission denied, GPS unavailable, timeout) never block the confirmation — the location write is wrapped in a `try/catch` that logs and swallows.

**Out of scope** (deliberately deferred):

- **Periodic background sync** — registering a location every N minutes while a service is active. Requires a service worker + the Background Sync API; out of scope for first release.
- **Multi-day location history analytics / heatmap** — aggregate fleet behavior visualization.
- **Route playback** — animated map replay of a completed service.
- **Commercial fleet-management integration** (Wialon, Geotab, etc.).
- **Geofence alerts** — notification when a driver leaves the planned route.
- **Dusk tests** — deferred (same call as `fuec-generation`). Pest covers the critical paths; Playwright MCP handles interactive verification.
- **Data retention / automated purging** — all rows kept indefinitely for now.
- **Reverb WebSocket push refresh** — 30s polling is enough; WebSocket can be a later enhancement.

## Acceptance Criteria

### Feature flag

- [ ] **AC1**: WHEN `config('sgte.gps_enabled')` is `false` AND a user visits `/vehicle-locations`, `/vehicle-locations/{id}`, `/vehicle-locations/create`, `/vehicle-locations/{id}/edit`, `/gps/map`, or `/driver/services/{service}/location` THEN all these routes return **404**. Controllers are never invoked.
- [ ] **AC2**: WHEN `config('sgte.gps_enabled')` is `false` THEN the GPS sidebar group is hidden from every role; when `true`, the group appears for admin + operator (per the VIEW_VEHICLE_LOCATIONS permission grant).
- [ ] **AC3**: WHEN `featureFlags.gps === false` THEN the "Ubicación GPS" card does NOT render on `driver/index.tsx` service cards AND the "Ubicaciones Recientes" card does NOT render on `/vehicles/{id}/show`.

### Schema modifications

- [ ] **AC4**: The existing `2026_02_27_225427_create_vehicle_locations_table.php` migration is modified **in place** (per project convention) to add: `service_id` (nullable FK → services, `onDelete cascade`), `accuracy` (nullable decimal(8,2)), `captured_by` (nullable FK → users, `onDelete set null`), composite index on `(vehicle_id, recorded_at)`.

### Permissions + model hygiene

- [ ] **AC5**: Three new permissions exist in `app/Enums/Permission.php`: `VIEW_VEHICLE_LOCATIONS = 'vehicle-locations.view'`, `REGISTER_VEHICLE_LOCATION = 'vehicle-locations.register'`, `DELETE_VEHICLE_LOCATIONS = 'vehicle-locations.delete'`. Grants in `seed_catalog_data`: Admin gets all three; Operator gets view + register; Driver gets register only.
- [ ] **AC6**: `VehicleLocationController` gate calls no longer reference `VIEW_VEHICLES` / `CREATE_VEHICLES` / `UPDATE_VEHICLES` / `DELETE_VEHICLES` — each action scopes to the new permission (`VIEW_VEHICLE_LOCATIONS` for index/show, `REGISTER_VEHICLE_LOCATION` for create/store/update, `DELETE_VEHICLE_LOCATIONS` for destroy).
- [ ] **AC7**: `VehicleLocation` model no longer uses the `Searchable` trait; the `getScoutKey()` + `toSearchableArray()` methods are removed. `LogsActivity` remains (tamper-evidence for compliance).

### Driver capture (inline on driver dashboard)

- [ ] **AC8**: WHEN a driver loads `/driver` AND has at least one open service for today AND `featureFlags.gps === true` THEN each service card renders an "Ubicación GPS" section with: (a) "Registrar con GPS" button that invokes `navigator.geolocation.getCurrentPosition(...)` and POSTs the coordinates to `driver.location.store` with `is_manual=false`; (b) "Registrar manualmente" button that opens a small inline form with lat/lng `<Input>` fields, POSTing with `is_manual=true`; (c) below the buttons, a list of the last 5 `VehicleLocation` rows for this service with timestamp + "Manual"/"GPS" badge.
- [ ] **AC9**: WHEN the browser geolocation API fails (permission denied, timeout, GPS unavailable) THEN the GPS button surfaces a muted "GPS no disponible — use entrada manual." message without navigating away or blocking the driver. The manual entry form remains available.
- [ ] **AC10**: WHEN a driver POSTs to `driver.location.store` for a service whose `driver_id` does NOT match the authenticated user's driver record THEN the response is **403** — same cross-driver check as `DriverDashboardController::confirmStart`.

### Auto-capture on confirmStart / confirmEnd

- [ ] **AC11**: `DriverDashboardController::confirmStart` accepts optional `latitude`, `longitude`, `accuracy`, `is_manual` fields alongside the existing confirmation payload. WHEN all three of {latitude, longitude, is_manual} are present AND `featureFlags.gps === true` THEN a `VehicleLocation` row is persisted with `service_id = $service->id`, `vehicle_id = $service->vehicle_id`, `captured_by = auth()->id()`, `recorded_at = now()` in the same request. Same contract for `confirmEnd`.
- [ ] **AC12**: WHEN the location write fails (DB error, invalid coordinates, etc.) THEN the confirmation still succeeds — the failure is caught, logged via `Log::warning(...)`, and swallowed. SRS §REQ-010 AC#4 explicitly mandates this non-blocking behavior.
- [ ] **AC13**: WHEN the coordinates are absent from the confirmStart/confirmEnd payload THEN no `VehicleLocation` row is written — location capture is opportunistic, not mandatory.

### Admin map view

- [ ] **AC14**: WHEN an admin or operator visits `/gps/map` AND `featureFlags.gps === true` THEN the page renders a full-viewport Leaflet map (react-leaflet) using OpenStreetMap tiles. A marker is placed per **active service today** (`service_status = 'open' AND service_date = today`) at the vehicle's latest known location (preference: the most recent `VehicleLocation` with `service_id = $service->id`; fallback: the most recent `VehicleLocation` for `vehicle_id` within the last 24 hours; if neither exists, the service is listed but has no marker).
- [ ] **AC15**: WHEN the user clicks a marker THEN a popup shows: vehicle plate, driver full name, last-updated timestamp (dateTimeFormatter `es-CO`), "Manual"/"GPS" badge, and a `<Link>` to `/services/{service.id}`.
- [ ] **AC16**: WHEN the map mounts AND no active services have coordinates THEN the map stays centered on Medellín (lat 6.2518, lng -75.5636, zoom 11). WHEN at least one active service has coordinates THEN the map calls `map.fitBounds(...)` on the marker coordinates so all visible vehicles fit in view.
- [ ] **AC17**: WHEN the page is mounted THEN a `setInterval` triggers `router.reload({ only: ['activeServices'] })` every 30 seconds; on unmount the interval is cleared. The map markers update without a full page reload.
- [ ] **AC18**: Non-authorized users (driver / accounting / unauthenticated) receive **403** (or 302 redirect to login for unauth). When `featureFlags.gps === false`, the route returns **404** even for admins.

### Vehicle detail integration

- [ ] **AC19**: WHEN an admin / operator visits `/vehicles/{id}` AND `featureFlags.gps === true` THEN a new "Ubicaciones Recientes" `<Card>` renders below the existing vehicle detail cards, showing the last 10 `VehicleLocation` rows for this vehicle ordered by `recorded_at` desc. Columns: Fecha/Hora, Coordenadas (font-mono lat, lng), Origen (Manual/GPS badge). When the vehicle has zero locations, the card shows "Sin registros de ubicación." WHEN `featureFlags.gps === false` THEN the card is not rendered.

### Rebuilt vehicle-locations pages

- [ ] **AC20**: `resources/js/pages/vehicle-locations/index.tsx` is a paginated DataTable with filters (`vehicle_id` via `<VehicleCombobox>`, `is_manual` Select, date range `recorded_from` + `recorded_to`) and 7 columns: Fecha/Hora, Vehículo (plate, link to `/vehicles/{id}`), Servicio (link to `/services/{id}` when `service_id` is set, otherwise "—"), Origen (Manual/GPS Badge), Coordenadas (font-mono), Precisión (meters or "—"), Registrado por (user name or "Sistema"), Acciones (Ver / Eliminar icon buttons gated on DELETE_VEHICLE_LOCATIONS for admin only).
- [ ] **AC21**: `resources/js/pages/vehicle-locations/show.tsx` renders 3 `<Card>` sections: Header (Vehículo + Fecha + Manual/GPS Badge + Eliminar button if admin), Coordenadas (lat/lng/accuracy + a small single-point Leaflet map centered on the point), Contexto (Servicio link + Registrado por).
- [ ] **AC22**: `resources/js/pages/vehicle-locations/{create,edit}.tsx` are admin-only manual-entry forms (`is_manual` forced to `true`). Non-admin visits to these routes receive 403 via the `REGISTER_VEHICLE_LOCATION` permission check on `VehicleLocationStoreRequest::authorize()`.

## Technical Specification

### Data Model

**Modified `vehicle_locations` migration (in place, per project convention):**

```
vehicle_locations (existing — modified)
├── id (bigint, PK)
├── vehicle_id (bigint, FK → vehicles.id)
├── service_id (bigint, FK → services.id, nullable, onDelete cascade)      [NEW]
├── recorded_at (timestamp)
├── latitude (decimal(10,8))
├── longitude (decimal(11,8))
├── accuracy (decimal(8,2), nullable)                                        [NEW]
├── is_manual (boolean, default false)
├── captured_by (bigint, FK → users.id, nullable, onDelete set null)         [NEW]
├── created_at / updated_at (timestamps)
├── INDEX (vehicle_id, recorded_at)                                          [NEW composite]
```

No new tables.

### Enums

Add to `app/Enums/Permission.php`:

- `VIEW_VEHICLE_LOCATIONS = 'vehicle-locations.view'`
- `REGISTER_VEHICLE_LOCATION = 'vehicle-locations.register'`
- `DELETE_VEHICLE_LOCATIONS = 'vehicle-locations.delete'`

Grants in `2026_03_13_000000_seed_catalog_data.php`:

| Permission | Admin | Operator | Driver | Accounting |
|---|:-:|:-:|:-:|:-:|
| VIEW_VEHICLE_LOCATIONS | ✓ | ✓ | - | - |
| REGISTER_VEHICLE_LOCATION | ✓ | ✓ | ✓ | - |
| DELETE_VEHICLE_LOCATIONS | ✓ | - | - | - |

Super Admin bypasses via `Gate::before` (already in place).

### Routes

All routes inside `Route::middleware('gps.enabled')` nested within the authenticated group, except where noted.

| Method | URI | Controller Action | Middleware | Name |
|---|---|---|---|---|
| GET | `/gps/map` | `VehicleLocationMapController@index` | `auth, verified, gps.enabled, can:vehicle-locations.view` | `gps.map` |
| resource | `/vehicle-locations` | `VehicleLocationController` (all verbs) | `auth, verified, gps.enabled` + per-action `can:*` | `vehicle-locations.*` |
| POST | `/driver/services/{service}/location` | `DriverLocationController@store` | `auth, verified, gps.enabled, can:vehicle-locations.register` | `driver.location.store` |

`DriverDashboardController::confirmStart` + `confirmEnd` keep their existing routes; they gain optional `latitude`/`longitude`/`accuracy`/`is_manual` fields in their request payload but the routes themselves are unchanged.

### Permissions

See table above. `artisan enum:typescript` regenerates `resources/js/enums/Permission.ts`.

### Pages

| Page | Component Path | Description |
|---|---|---|
| Map | `resources/js/pages/gps/map.tsx` | **NEW.** Full-viewport Leaflet map with 30s polling. |
| Vehicle-locations Index | `resources/js/pages/vehicle-locations/index.tsx` | **REWRITE.** `<DataTable>` + `useServerTable` + `<VehicleCombobox>` filter + date range. 7 columns. |
| Vehicle-locations Show | `resources/js/pages/vehicle-locations/show.tsx` | **REWRITE.** 3 Card sections + single-point map. |
| Vehicle-locations Create | `resources/js/pages/vehicle-locations/create.tsx` | **REWRITE.** Admin-only manual-entry form. |
| Vehicle-locations Edit | `resources/js/pages/vehicle-locations/edit.tsx` | **REWRITE.** Admin-only correction form. |
| Driver dashboard card | `resources/js/pages/driver/index.tsx` (extend) | **EXTEND.** Inline "Ubicación GPS" section per service card. |
| Vehicle show extension | `resources/js/pages/vehicles/show.tsx` (extend) | **EXTEND.** "Ubicaciones Recientes" card, gated on `featureFlags.gps`. |
| VehicleCombobox | `resources/js/components/vehicles/vehicle-combobox.tsx` | **NEW shared primitive.** Parallel to `<UserCombobox />` / `<ThirdPartyCombobox />`. Searchable on plate + brand + line. |

## Migration Strategy

`modify-existing`: edit the existing `2026_02_27_225427_create_vehicle_locations_table.php` migration in place per the `feedback_edit_primary_migrations` convention (no backfill migrations in early dev while stg/prod carry no real data).

After implementing: `./vendor/bin/sail artisan migrate:fresh --seed --no-interaction` to rebuild.

## Tasks

### Backend — Infrastructure

- [ ] **Task B1**: Create `App\Http\Middleware\EnsureGpsEnabled` that `abort(404)` when `! config('sgte.gps_enabled')`. Register as `gps.enabled` alias in `bootstrap/app.php` alongside the existing `fuec.enabled` alias.

- [ ] **Task B2**: Install `react-leaflet ^5.x` + `leaflet ^1.9.x` + `@types/leaflet ^1.9.x` via `./vendor/bin/sail npm install leaflet react-leaflet && ./vendor/bin/sail npm install -D @types/leaflet`.

### Backend — Data layer

- [ ] **Task B3**: Modify `database/migrations/2026_02_27_225427_create_vehicle_locations_table.php` in place: add `service_id` (nullable FK, `cascadeOnDelete`), `accuracy` decimal(8,2) nullable, `captured_by` (nullable FK to users, `nullOnDelete`), composite index `(vehicle_id, recorded_at)`. Preserve the existing columns in their current order; append new columns after `is_manual`.

- [ ] **Task B4**: Update `app/Models/VehicleLocation.php`:
    - Remove the `Searchable` trait + `getScoutKey()` + `toSearchableArray()` method.
    - Add `service()` BelongsTo and `capturedBy()` BelongsTo (to `User::class`).
    - Extend `$fillable` with `service_id`, `accuracy`, `captured_by`.
    - Extend `$casts` (decimal for accuracy).
    - Extend `getActivitylogOptions()->logOnly([...])` with the new columns.

- [ ] **Task B5**: Update `app/Models/Vehicle.php` with a `locations(): HasMany` returning `$this->hasMany(VehicleLocation::class)`.

- [ ] **Task B6**: Update `database/factories/VehicleLocationFactory.php` (create if absent) to produce valid rows with nullable `service_id` / `accuracy` / `captured_by`, default `is_manual = false`.

- [ ] **Task B7**: Add the three new permissions to `app/Enums/Permission.php` + `labels()` + grant them in `seed_catalog_data` per the table in Permissions. Run `./vendor/bin/sail artisan enum:typescript` to regenerate `resources/js/enums/Permission.ts`.

### Backend — Controllers + requests

- [ ] **Task B8**: Create `App\Http\Requests\VehicleLocationStoreRequest`:
    - `authorize()` → `Gate::allows(Permission::REGISTER_VEHICLE_LOCATION->value)`.
    - `rules()` → `vehicle_id` (required|integer|exists:vehicles,id), `service_id` (nullable|integer|exists:services,id), `recorded_at` (required|date), `latitude` (required|numeric|between:-90,90), `longitude` (required|numeric|between:-180,180), `is_manual` (boolean), `accuracy` (nullable|numeric|min:0|max:10000).

- [ ] **Task B9**: Create `App\Http\Requests\VehicleLocationUpdateRequest` with the same rules (admin corrections of historical rows).

- [ ] **Task B10**: Rewrite `app/Http/Controllers/VehicleLocationController.php`:
    - Gate each action on the new scoped permission (`VIEW_VEHICLE_LOCATIONS` for index/show, `REGISTER_VEHICLE_LOCATION` for create/store/update/edit, `DELETE_VEHICLE_LOCATIONS` for destroy).
    - `index`: paginate via `QueryBuilder` with eager-loaded `vehicle:id,plate`, `service:id,service_date`, `capturedBy:id,name`; filters: `AllowedFilter::exact('vehicle_id')`, `AllowedFilter::exact('is_manual')`, two `AllowedFilter::callback` for `recorded_from` + `recorded_to` (empty-string safe). Default sort `-recorded_at`.
    - `show`: eager-load the three relations.
    - `store`: uses `VehicleLocationStoreRequest`; sets `captured_by = auth()->id()` if absent; always sets `is_manual = true` when coming from the admin create flow.
    - `update`: uses `VehicleLocationUpdateRequest`; admin corrections.
    - `destroy`: soft-delete not needed — `LogsActivity` already captures the deletion.
    - Controller also passes `vehicles` (via `Vehicle::query()->orderBy('plate')->get(['id','plate','brand','line'])`) to the `index` page so `<VehicleCombobox>` has options.

- [ ] **Task B11**: Create `App\Http\Controllers\DriverLocationController` with a single `store(Request $request, Service $service)` method:
    - `Gate::authorize(Permission::REGISTER_VEHICLE_LOCATION->value)`.
    - Verify the authenticated user is the driver assigned to this service. Pattern: `$driver = $request->user()->driver; abort_if(! $driver || $service->driver_id !== $driver->id, 403);` — mirrors `DriverDashboardController::confirmStart`.
    - Inline-validate `latitude` / `longitude` / `is_manual` / `accuracy` via `$request->validate([...])`.
    - Create the `VehicleLocation` row with `service_id = $service->id`, `vehicle_id = $service->vehicle_id`, `captured_by = auth()->id()`, `recorded_at = now()`.
    - Redirect back to `/driver`.

- [ ] **Task B12**: Extend `DriverDashboardController::confirmStart` + `confirmEnd` to opportunistically persist a `VehicleLocation`:
    - Accept optional `latitude` / `longitude` / `accuracy` / `is_manual` fields in the request.
    - When all three of `{latitude, longitude, is_manual}` are present AND `config('sgte.gps_enabled')`, call a new `protected function persistLocationIfProvided(Service $service, Request $request): void` that wraps the DB write in `try/catch` — on failure, `Log::warning('Failed to persist GPS location', [...]) ` and return. The confirmation itself is never blocked.
    - Extend tests: one happy-path assertion that the `VehicleLocation` row exists, one failure assertion that a broken location payload still lets the confirmation succeed.

- [ ] **Task B13**: Create `App\Http\Controllers\VehicleLocationMapController` with a single `index()` method:
    - `Gate::authorize(Permission::VIEW_VEHICLE_LOCATIONS->value)`.
    - Query active services for today: `Service::query()->where('service_status', ServiceStatus::Open)->whereDate('service_date', today())->with(['vehicle:id,plate', 'driver:id,first_name,first_lastname'])->get()`.
    - For each service, compute the latest location: preference is `VehicleLocation::query()->where('service_id', $service->id)->orderByDesc('recorded_at')->first()`; fallback is `VehicleLocation::query()->where('vehicle_id', $service->vehicle_id)->where('recorded_at', '>=', now()->subDay())->orderByDesc('recorded_at')->first()`.
    - Shape the Inertia payload: `activeServices: [{ service_id, vehicle_plate, driver_name, location: { latitude, longitude, recorded_at, is_manual, accuracy } | null }, ...]`.
    - Render `gps/map`.

- [ ] **Task B14**: Register the routes in `routes/web.php` inside the `Route::middleware(['auth', 'verified'])` group: (1) all `/vehicle-locations/*` routes move under `Route::middleware('gps.enabled')->group(fn () => Route::resource(...)`); (2) add the `/gps/map` GET route; (3) add the `/driver/services/{service}/location` POST route — this one stays outside the `gps.enabled` middleware group on the URL level but IS gated by `gps.enabled` middleware inline.

### Frontend — Primitives

- [ ] **Task F1**: Create `resources/js/components/vehicles/vehicle-combobox.tsx` — parallel to `<UserCombobox />`. Props: `{ vehicles, value, onChange, placeholder?, disabled?, invalid?, id?, className? }`. Search keywords: plate + brand + line. Primary line: `{plate}`; muted secondary: `{brand} {line}`. Export `type VehicleOption = { id: number; plate: string; brand: string | null; line: string | null }`.

### Frontend — Driver integration

- [ ] **Task F2**: Extend `resources/js/pages/driver/index.tsx` with an "Ubicación GPS" section inside each service card:
    - Reads `page.props.auth?.featureFlags?.gps` — renders nothing when `false`.
    - Two buttons: "Registrar con GPS" (calls `navigator.geolocation.getCurrentPosition(success, error, { enableHighAccuracy: true, timeout: 10000 })`; on success POSTs to `/driver/services/{service.id}/location` with `{ latitude, longitude, accuracy, is_manual: false }`; on error shows the muted "GPS no disponible — use entrada manual." message) + "Registrar manualmente" (reveals a small inline form with `Input type="number" step="any"` for lat + lng, POSTs with `is_manual: true`).
    - Below the buttons: last 5 `VehicleLocation` rows for this service (server-provided via `driver.dashboard.index` payload extension — extend the controller to eager-load `service.recentLocations`). Timestamp formatted via `dateTimeFormatter`; Manual/GPS badge.
    - Also wraps the existing confirmStart + confirmEnd buttons so that before POSTing the confirmation, they invoke `getCurrentPosition` and attach `latitude` / `longitude` / `accuracy` / `is_manual: false` to the payload. On geolocation failure or timeout (2s soft cap), the confirmation fires without coordinates.

### Frontend — Vehicle-locations pages

- [ ] **Task F3**: Rewrite `resources/js/pages/vehicle-locations/index.tsx` around `<DataTable>` + `useServerTable`. Filters: `vehicle_id` (via `<VehicleCombobox>` above the table), `is_manual` (Select: Sí / No), `recorded_from` + `recorded_to` (two `<Input type="date">`). Columns per AC20.

- [ ] **Task F4**: Create `resources/js/pages/vehicle-locations/columns.tsx` with the 7 `ColumnDef<VehicleLocationRow>[]` entries. Acciones cell uses `<Can permission={Permission.DELETE_VEHICLE_LOCATIONS}><DataTableRowActions /></Can>` for admin gating.

- [ ] **Task F5**: Rewrite `resources/js/pages/vehicle-locations/show.tsx` with the 3 Card sections. The Coordenadas card embeds a small (300×250 px) Leaflet map centered on the point with a single marker.

- [ ] **Task F6**: Rewrite `resources/js/pages/vehicle-locations/create.tsx` and `edit.tsx` (bundled) with a shared `<VehicleLocationForm>` component extracted to `resources/js/components/vehicle-locations/vehicle-location-form.tsx`. Fields: `<VehicleCombobox>`, `recorded_at`, `latitude`, `longitude`, `is_manual` (defaulted true, hidden for admin create but editable on update), optional `accuracy`, optional `service_id` (Select filtered to services for the selected vehicle).

### Frontend — Map page

- [ ] **Task F7**: Create `resources/js/pages/gps/map.tsx`:
    - Import `leaflet/dist/leaflet.css` + the Leaflet default-marker-icon Vite workaround: `import icon from 'leaflet/dist/images/marker-icon.png'; import iconShadow from 'leaflet/dist/images/marker-shadow.png'; L.Marker.prototype.options.icon = L.icon({ iconUrl: icon, shadowUrl: iconShadow, ... });`.
    - Render `<MapContainer center={[6.2518, -75.5636]} zoom={11}>` with `<TileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' />`.
    - Render `<Marker>` per active service that has a `location`; inside `<Popup>` render plate + driver name + timestamp + Manual/GPS badge + Link to `/services/{id}`.
    - On mount, if at least one active service has coordinates, compute `L.latLngBounds(...)` and call `map.fitBounds(...)` via a helper `<FitBoundsOnMount>` component that uses `useMap()`.
    - `useEffect` with `setInterval(() => router.reload({ only: ['activeServices'] }), 30_000)` + cleanup on unmount.
    - Sidebar-height layout: page wrapper with `className="h-[calc(100vh-4rem)]"` so the map fills below the breadcrumb bar.

- [ ] **Task F8**: Extend `resources/js/components/app-sidebar.tsx` — inside the existing GPS NavGroup (which already has `featureFlag: 'gps'`), add a "Mapa" entry at the top pointing to `/gps/map`. Rename the existing "Ubicaciones" entry to appear below.

### Frontend — Vehicle detail integration

- [ ] **Task F9**: Extend `resources/js/pages/vehicles/show.tsx` with a new "Ubicaciones Recientes" `<Card>` gated on `page.props.auth?.featureFlags?.gps === true`. Pulls `vehicle.locations` from the controller (eager-loaded in a new Task B15).

- [ ] **Task B15** (backend pair): Extend `VehicleController@show` to eager-load `$vehicle->load(['locations' => fn ($q) => $q->orderByDesc('recorded_at')->limit(10)])`. No route changes.

### Tests

- [ ] **Task T1**: `tests/Feature/Http/Controllers/VehicleLocationControllerTest.php` — rewrite:
    - admin index returns paginated payload with eager-loaded relations
    - filter `vehicle_id` exact, filter `is_manual` exact, filter `recorded_from` / `recorded_to` date range
    - admin can create/update/destroy
    - operator can create (REGISTER grant) but not destroy (403)
    - driver receives 403 on index (no VIEW grant) but can register via the driver endpoint
    - feature flag off returns 404 on every route

- [ ] **Task T2**: `tests/Feature/Http/Controllers/DriverLocationControllerTest.php` (new):
    - Driver assigned to the service registers OK (201/302 redirect)
    - Driver NOT assigned receives 403
    - Missing `driver` relationship on user receives 403
    - Invalid lat (<-90 or >90) rejects 422
    - Invalid lng (<-180 or >180) rejects 422
    - Flag off returns 404

- [ ] **Task T3**: Extend `tests/Feature/Http/Controllers/DriverDashboardControllerTest.php`:
    - `confirmStart` with valid lat/lng/is_manual/accuracy persists a VehicleLocation row tied to the service + captured_by admin/driver user
    - `confirmStart` without coords does NOT persist a row
    - `confirmStart` with broken coords (e.g. out-of-range lat) still succeeds on the confirmation itself — no 422 — and the failed location write is swallowed (no row persisted; test asserts `VehicleLocation::count() === 0`)
    - Same three scenarios for `confirmEnd`

- [ ] **Task T4**: `tests/Feature/Http/Controllers/VehicleLocationMapControllerTest.php` (new):
    - admin sees `activeServices` payload with correct shape
    - operator sees `activeServices` payload
    - driver sees 403
    - accounting sees 403
    - flag off returns 404
    - a service with a location emits the location sub-object; a service without a location emits `location: null`
    - the latest-location preference logic: service_id match > vehicle_id-last-24h fallback

### Docs

- [ ] **Task X1**: Update `docs/phases/phase-5-optionals-deploy.md` §5.2 to flip from "scaffolded only" to "✅ done (behind feature flag `SGTE_GPS_ENABLED`)". Update the top-of-file status line.

- [ ] **Task X2**: Update `docs/phases/README.md` Phase 5 row to reflect both FUEC + GPS complete behind feature flags.

## Verification

### 1. Interactive verification — Playwright MCP

Reference users (all password `password`):

| Role | Email |
|---|---|
| Admin | `admin@sgte.app` |
| Operator | `operator@sgte.app` |
| Driver | `driver@sgte.app` |
| Accounting | `accounting@sgte.app` |

Preferred flow:

1. Flip `SGTE_GPS_ENABLED=true` in `.env`, clear config cache.
2. Seed data: `migrate:fresh --seed`.
3. Login as admin → `/gps/map` — verify map renders, Medellín centered, no markers (no service locations yet).
4. Open a second tab, login as driver → `/driver` — verify each service card shows "Registrar con GPS" + "Registrar manualmente" buttons.
5. Click "Registrar con GPS" — browser prompts for location permission; accept. Verify the request fires and a toast / reload confirms.
6. Return to admin tab on `/gps/map` — within 30s the marker appears at the registered location.
7. Click the marker — popup shows plate + driver + timestamp + GPS badge.
8. As driver, click "Confirmar Inicio" — verify the confirmation succeeds AND a new `VehicleLocation` is captured (check `/vehicle-locations` as admin).
9. Deny location permission in the driver tab — click "Registrar con GPS" again — verify the muted fallback message appears.
10. Use manual entry: lat `6.2518` / lng `-75.5636` — submit — verify the row persists with `is_manual=true`.
11. As admin visit `/vehicle-locations` — verify the new rows show, filter by vehicle works, date range narrows correctly.
12. Flip `SGTE_GPS_ENABLED=false`, clear config cache — revisit `/gps/map` → 404. Sidebar no longer shows the GPS group.
13. Login as operator, admin flag back on — `/gps/map` accessible (VIEW grant). `/vehicle-locations/create` → 403 if operator doesn't have REGISTER (per grants, they do, so it passes).
14. Use `mcp__laravel-boost__browser-logs` to check JS console errors during the flow.

- [ ] Scenario 1: Admin sees the map page with an active service marker.
- [ ] Scenario 2: Driver registers a GPS location; admin sees it on the map within 30s.
- [ ] Scenario 3: Driver registers a manual location (GPS denied); row persists with is_manual=true.
- [ ] Scenario 4: Driver's confirmStart / confirmEnd auto-capture coordinates opportunistically.
- [ ] Scenario 5: `/vehicles/{id}/show` "Ubicaciones Recientes" card renders when flag on, disappears when off.
- [ ] Scenario 6: Admin-only create/edit form works; operator is redirected / 403'd.
- [ ] Scenario 7: Flag off returns 404 on all gated routes.
- [ ] Scenario 8: Driver 403s on `/gps/map`.

### 2. Backend regression — Pest feature tests (required)

Tasks T1–T4 above MUST ship with this requirement. Run via `./vendor/bin/sail test --compact`. The full suite MUST stay green.

### 3. UI regression — Laravel Dusk browser tests (deferred)

Deferred to a follow-up per the Out-of-scope list. The Pest coverage (4 controllers × ~6 scenarios each = ~24 tests) pins every critical path; Playwright MCP handles interactive verification. When Dusk is resurrected, `FuecGenerationTest` is the closest convention reference.

### 4. API endpoints — curl

The only non-Inertia endpoint is the driver location POST. Verify with:

```bash
# Login as driver (password = 'password')
curl -s -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"driver@sgte.app","password":"password"}' \
  -c cookies-driver.txt

# Register a location for driver's own service (substitute {service_id})
curl -s -X POST http://localhost/driver/services/{service_id}/location \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: $(grep XSRF-TOKEN cookies-driver.txt | awk '{print $7}')" \
  -b cookies-driver.txt \
  -d '{"latitude":6.2518,"longitude":-75.5636,"is_manual":false,"accuracy":10}'
# Expected: 302 redirect back to /driver (or 200 for a JSON response)

# With a non-assigned service — expected 403
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost/driver/services/{other_service_id}/location \
  -H "Accept: application/json" \
  -b cookies-driver.txt \
  -d '{"latitude":6.2518,"longitude":-75.5636,"is_manual":false}'
# Expected: 403

# With feature flag off
SGTE_GPS_ENABLED=false php artisan config:clear
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost/driver/services/{service_id}/location -b cookies-driver.txt -d '{}'
# Expected: 404
```

## Dependencies

- **Phase 2 operational core** — merged; provides Service model + statuses + day-status that this requirement reads from.
- **fuec-generation** — merged; provides the feature-flag infrastructure (`config/sgte.php`, `HandleInertiaRequests` sharing `auth.featureFlags`, `app-sidebar.tsx` `featureFlag` NavGroup filtering). `EnsureGpsEnabled` is a direct parallel to `EnsureFuecEnabled`.
- **Driver role & DriverDashboardController** — already wired. This requirement extends `confirmStart` + `confirmEnd` without breaking their existing contract.
- **No other hard dependencies.**
- **New packages**: `react-leaflet ^5.x`, `leaflet ^1.9.x`, `@types/leaflet ^1.9.x` (dev).

## Notes

### Why OpenStreetMap over CartoDB / self-hosted

OSM is the de-facto default for Leaflet — works without configuration, no API key, well-documented Vite quirks. The cost of switching tile providers later is a one-line URL change in the `<TileLayer>` component; starting with OSM defers the decision to when the client actually objects to the visual style (if ever).

### Why 30-second polling over WebSocket push

Reverb is installed but the broadcasting infrastructure (channels, events, listeners) would triple the scope of this requirement for a latency improvement that most dispatchers won't notice. 30-second polling with `router.reload({ only: ['activeServices'] })` matches the gantt + day-summary refresh patterns, produces ~1 round-trip per open map tab per 30s, and is trivially cacheable. WebSocket push is a natural follow-up if real-time becomes a hard requirement.

### Driver UX inline on the dashboard

The inline placement avoids a new page + new controller method + new route + extra navigation taps. Drivers see the service card, the confirmStart/confirmEnd buttons, AND the location controls in one place. Matches how the existing confirmation buttons live today.

### Auto-capture failure mode is load-bearing

SRS §REQ-010 AC#4 explicitly mandates non-blocking: "WHEN GPS is not available THEN the system SHALL allow manual recording of coordinates without blocking the operation." The `try/catch` wrap in `persistLocationIfProvided` plus the `Log::warning` (not `Log::error`) plus the absence of any re-throw makes the confirmation truly independent of the location write. Task T3 pins this behavior with a dedicated test.

### Why no soft-delete on vehicle_locations

Location records are a time-series log. Admins correcting faulty entries delete outright (via `DELETE_VEHICLE_LOCATIONS` — admin only); the activity log captures the deletion for tamper-evidence. Soft-delete would complicate the "last 10 locations" queries and add no auditable value beyond what LogsActivity already provides.

### Estimated commit count

About **24–28 commits**, in rough order:
1. docs (this requirement file).
2. B1 (`EnsureGpsEnabled` middleware + alias).
3. B2 (npm install Leaflet packages).
4. B3 (modify vehicle_locations migration).
5. B4 + B5 + B6 (VehicleLocation model cleanup + Vehicle::locations() + factory).
6. B7 (permissions + seeder + enum:typescript).
7. B8 + B9 (request classes).
8. B10 (VehicleLocationController rewrite).
9. T1 (VehicleLocationController tests).
10. B11 (DriverLocationController).
11. T2 (DriverLocationController tests).
12. B12 (extend DriverDashboardController::confirmStart/End).
13. T3 (DriverDashboardController test extensions).
14. B13 (VehicleLocationMapController).
15. T4 (VehicleLocationMapController tests).
16. B14 (routes).
17. F1 (VehicleCombobox shared primitive).
18. F3 + F4 (vehicle-locations index + columns).
19. F5 (vehicle-locations show).
20. F6 (vehicle-locations create+edit + shared form).
21. F7 (gps/map page).
22. F8 (sidebar entry + Mapa link).
23. F2 (driver dashboard inline GPS card + confirmStart/End wrapper).
24. B15 + F9 (VehicleController::show eager-load + "Ubicaciones Recientes" card).
25. X1 + X2 (phase docs + requirement status flip).

Similar size to fuec-generation. The biggest individual tasks are the DriverDashboardController extension (B12 + T3 — fragile because auto-capture must never break confirmations) and the gps/map page (F7 — Leaflet Vite icon workaround + fitBounds + polling).
