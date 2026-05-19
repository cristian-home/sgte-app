# Phase 1 — Nav/Action Map

> Source-of-truth inventory of every route, controller, Inertia page, sidebar entry, and model relationship in SGTE. Feeds Phase 2 (scenario catalog) and Phase 4 (Pest browser tests).
>
> **Extraction method:** routes/* + bootstrap/app.php parsed by source read (host had no PHP). Re-run `./vendor/bin/sail artisan route:list --json` from inside the container to spot drift.

## 1. Routes

### 1.1 Public + Driver-portal + main app (`routes/web.php`)

Every authenticated route also has `EnsureUserIsActive` and `EnsurePasswordChanged` from the global stack. `auth` and `verified` are listed explicitly where they appear in route groups. Gate names in the `can:` column come from `App\Enums\Permission`.

| Method | URI | Name | Controller@action | Middleware / Gates | Page / response | Notes |
|---|---|---|---|---|---|---|
| GET | `/` | `home` | Closure | — | Redirect → `dashboard` | |
| GET | `/dashboard` | `dashboard` | `DashboardController@show` | `auth`, `verified`, `can:dashboard.view` | `dashboard` | KPIs + doc-expiry alerts |
| GET | `/fuec/verify/{uuid}` | `fuec.verify` | `FuecVerifyController@show` | `fuec.enabled` | Blade `fuecs.verify` | **Public**, no auth |
| GET | `/driver` | `driver.dashboard` | `DriverDashboardController@index` | `auth`, `verified` | `driver/index` | Gate `services.register-times` inside action |
| POST | `/driver/services/{service}/confirm-start` | `driver.confirm-start` | `DriverDashboardController@confirmStart` | `auth`, `verified` | Redirect | Re-checks docs valid inline |
| POST | `/driver/services/{service}/confirm-end` | `driver.confirm-end` | `DriverDashboardController@confirmEnd` | `auth`, `verified` | Redirect | |
| POST | `/driver/services/{service}/decline` | `driver.decline` | `DriverDashboardController@decline` | `auth`, `verified` | Redirect | Uses `DriverDeclineServiceRequest`; REQ-012 |
| POST | `/driver/services/{service}/location` | `driver.location.store` | `DriverLocationController@store` | `auth`, `verified`, `gps.enabled`, `can:vehicle-locations.register` | Redirect | 403 if driver mismatch |
| GET | `/gantt` | `gantt.index` | `GanttController@index` | `auth`, `verified`, `can:services.view` | `gantt/index` | Blocks vehicle rows w/ expired docs (REQ-004) |
| GET | `/day-summary` | `day-summary.index` | `DaySummaryController@index` | `auth`, `verified`, `can:day-summary.view` | `day-summary/index` | |
| GET | `/day-summary/export` | `day-summary.export` | `DaySummaryController@export` | `auth`, `verified`, `can:day-summary.view` | CSV | |
| GET | `/day-statuses` | `day-statuses.index` | `DayStatusController@index` | `auth`, `verified`, `can:day-summary.view` | Redirect → calendar (current year) | |
| GET | `/day-statuses/{year}` | `day-statuses.calendar` | `DayStatusController@calendar` | `auth`, `verified`, `can:day-summary.view` | `day-statuses/index` | Year heatmap |
| GET | `/day-statuses/{year}/{month}` | `day-statuses.calendar-month` | `DayStatusController@calendarMonth` | `auth`, `verified`, `can:day-summary.view` | `day-statuses/index` | Month view + optional `selectedDay` |
| POST | `/day-statuses/{day_status}/execute` | `day-statuses.execute` | `DayStatusController@execute` | `auth`, `verified`, `can:day-summary.execute` | Redirect | Locks day, notifies accounting |
| `*` | `/day-statuses` resource (store/show/edit/update/destroy) | `day-statuses.*` | `DayStatusController` | `can:day-summary.view` + `can:day-summary.execute` in actions | | Resource exposed but day-status rows usually created implicitly by service.store |
| GET | `/services` | `services.index` | `ServiceController@index` | `auth`, `verified`, `can:services.view` | `services/index` | Search via SearchesDatabase / Scout |
| GET | `/services/create` | `services.create` | `ServiceController@create` | `can:services.view` | `services/create` | Gate `services.create` inside |
| POST | `/services` | `services.store` | `ServiceController@store` | `can:services.view` | Redirect → `services.index` | `ServiceStoreRequest`; logs retroactive entries; sends driver notif |
| GET | `/services/{service}` | `services.show` | `ServiceController@show` | `can:services.view` | `services/show` | |
| GET | `/services/{service}/edit` | `services.edit` | `ServiceController@edit` | `can:services.view` + (`services.update-projected` OR `services.update-executed`) inside | `services/edit` | |
| PUT | `/services/{service}` | `services.update` | `ServiceController@update` | (same) | Redirect | `ServiceUpdateRequest`; status-transition logging |
| DELETE | `/services/{service}` | `services.destroy` | `ServiceController@destroy` | `can:services.view` + `can:services.delete` inside | Redirect | Admin-only if day executed |
| `*` | `/service-incidents` resource | `service-incidents.*` | `ServiceIncidentController` | `auth`, `verified` only at route; gates per action (`incidents.view`, `incidents.create`, `incidents.update`, `incidents.delete`) | mixed | ADR-005: route-level gate intentionally omitted because driver has CREATE but not VIEW |
| GET | `/incident-types` (+ resource) | `incident-types.*` | `IncidentTypeController` | `can:incident-types.view` route + per-action gates | mixed | Catalog |
| `*` | `/third-parties` resource | `third-parties.*` | `ThirdPartyController` | `can:third-parties.view` route + `third-parties.create`/`third-parties.update`/`third-parties.delete` in actions | mixed | JSON if `Accept: application/json` |
| `*` | `/drivers` resource | `drivers.*` | `DriverController` | `can:drivers.view` route + action gates | mixed | |
| POST | `/drivers/{driver}/invite-account` | `drivers.invite-account` | `DriverController@inviteAccount` | `can:drivers.update` | Redirect | Creates linked User + invites |
| POST | `/drivers/{driver}/resend-invitation` | `drivers.resend-invitation` | `DriverController@resendInvitation` | `can:drivers.update` | Redirect | |
| `*` | `/vehicles` resource | `vehicles.*` | `VehicleController` | `can:vehicles.view` route + action gates | mixed | |
| `*` | `/contracts` resource | `contracts.*` | `ContractController` | `can:contracts.view` route + action gates | mixed | Auto-numbering for generic contracts |
| `*` | `/invoices` resource | `invoices.*` | `InvoiceController` | `can:invoices.view` route + action gates | mixed | |
| POST | `/invoices/{invoice}/mark-paid` | `invoices.mark-paid` | `InvoiceController@markPaid` | `can:invoices.update` | Redirect | Pending → Paid only |
| GET | `/invoices/{invoice}/pdf` | `invoices.pdf` | `InvoiceController@pdf` | `can:invoices.view` | PDF stream | Informational, not DIAN-compliant |
| POST | `/invoices/{invoice}/services` | `invoices.services.attach` | `InvoiceController@attachServices` | `can:invoices.assign-services` | Redirect | May override billing-affecting incidents (logged) |
| DELETE | `/invoices/{invoice}/services/{service}` | `invoices.services.detach` | `InvoiceController@detachService` | `can:invoices.assign-services` | Redirect | |
| POST | `/invoices/{invoice}/recompute-total` | `invoices.recompute-total` | `InvoiceController@recomputeTotal` | `can:invoices.assign-services` | Redirect | Idempotent |
| `*` | `/fuecs` resource | `fuecs.*` | `FuecController` | `fuec.enabled` + `can:fuec.view` / `can:fuec.generate` | mixed | |
| GET | `/fuecs/candidate-services` | `fuecs.candidate-services` | `FuecController@candidateServices` | `fuec.enabled`, `can:fuec.generate` | JSON | Closed services w/o active FUEC |
| POST | `/fuecs/preview` | `fuecs.preview` | `FuecController@preview` | `fuec.enabled`, `can:fuec.generate` | PDF stream | Non-committing preview (REQ-007 AC#2) |
| GET | `/fuecs/{fuec}/pdf` | `fuecs.pdf` | `FuecController@pdf` | `fuec.enabled`, `can:fuec.view` | PDF stream | From S3 |
| POST | `/fuecs/{fuec}/cancel` | `fuecs.cancel` | `FuecController@cancel` | `fuec.enabled`, `can:fuec.generate` | Redirect | `FuecCancelRequest` w/ reason |
| `*` | `/fuec-number-ranges` resource | `fuec-number-ranges.*` | `FuecNumberRangeController` | `fuec.enabled`, `can:fuec-number-ranges.manage` | mixed | Activating one deactivates others |
| GET | `/gps/map` | `gps.map` | `VehicleLocationMapController@index` | `gps.enabled`, `can:vehicle-locations.view` | `gps/map` | Leaflet + 30s polling |
| `*` | `/vehicle-locations` resource | `vehicle-locations.*` | `VehicleLocationController` | `gps.enabled` route + per-action gates | mixed | |
| `*` | `/users` (no resource macro — explicit verbs) | `users.*` | `UserController` | `can:users.view` route + per-action gates | mixed | Blocks self/last-admin/linked-driver delete |
| PATCH | `/users/{user}/active` | `users.toggle-active` | `UserController@toggleActive` | `can:users.view` | Redirect / JSON | |
| POST | `/users/{user}/reset-password` | `users.reset-password` | `UserController@resetPassword` | `can:users.view` | Redirect | |
| GET | `/roles`, `/roles/{role}` | `roles.index`, `roles.show` | `RoleController` | `can:users.view` | `roles/index`, `roles/show` | |
| PUT | `/roles/{role}` | `roles.update` | `RoleController@update` | `can:users.view` | Redirect | Syncs role permissions |
| GET | `/permissions` | `permissions.index` | `PermissionController@index` | `can:users.view` | `permissions/index` | Read-only grouped list |
| GET | `/audit-log` | `audit-log.index` | `AuditLogController@index` | `can:audit-log.view` | `audit-log/index` | Filters: log_name, subject_type, causer, event, date range |
| `*` | `/document-types`, `/eps`, `/pension-funds`, `/severance-funds` resources | `*.*` | `DocumentTypeController`, `EpsController`, `PensionFundController`, `SeveranceFundController` | `can:catalogs.manage` | mixed | Static catalogs |

### 1.2 Settings (`routes/settings.php`)

| Method | URI | Name | Controller@action | Middleware | Page |
|---|---|---|---|---|---|
| GET | `/settings` | — | Closure | `auth` | Redirect → `/settings/profile` |
| GET | `/settings/profile` | `profile.edit` | `Settings\ProfileController@edit` | `auth` | `settings/profile` |
| PATCH | `/settings/profile` | `profile.update` | `Settings\ProfileController@update` | `auth` | Redirect |
| DELETE | `/settings/profile` | `profile.destroy` | `Settings\ProfileController@destroy` | `auth`, `verified` | Redirect → `/` (logout + invalidate) |
| GET | `/settings/password` | `user-password.edit` | `Settings\PasswordController@edit` | `auth`, `verified` | `settings/password` |
| PUT | `/settings/password` | `user-password.update` | `Settings\PasswordController@update` | `auth`, `verified`, `throttle:6,1` | Redirect |
| GET | `/settings/appearance` | `appearance.edit` | Closure | `auth`, `verified` | `settings/appearance` |
| GET | `/settings/two-factor` | `two-factor.show` | `Settings\TwoFactorAuthenticationController@show` | `auth`, `verified` (+ optional `password.confirm`) | `settings/two-factor` |

### 1.3 Admin / Data Imports

| Method | URI | Name | Controller@action | Middleware | Response |
|---|---|---|---|---|---|
| GET | `/admin/imports` | `admin.imports.index` | `DataImportController@index` | `can:data-imports.manage` | `admin/imports/index` |
| GET | `/admin/imports/create` | `admin.imports.create` | `DataImportController@create` | `can:data-imports.manage` | `admin/imports/create` |
| POST | `/admin/imports` | `admin.imports.store` | `DataImportController@store` | `can:data-imports.manage` | Redirect → show |
| GET | `/admin/imports/{import}` | `admin.imports.show` | `DataImportController@show` | `can:data-imports.manage` | `admin/imports/show` |
| GET | `/admin/imports/templates/{type}` | `admin.imports.templates.show` | `DataImportTemplateController@show` | `can:data-imports.manage` | CSV download (users/third-parties/drivers/vehicles) |
| GET | `/admin/imports/reference/{catalog}` | `admin.imports.reference.show` | `DataImportReferenceController@show` | `can:data-imports.manage` | CSV stream (catalog data) |
| DELETE | `/admin/imports/{import}/files` | `admin.imports.purge` | `DataImportController@purge` | `can:data-imports.manage` | Redirect | Removes S3 files |
| GET | `/admin/imports/{import}/download/source` | `admin.imports.download.source` | `DataImportController@downloadSource` | `can:data-imports.manage` | File |
| GET | `/admin/imports/{import}/download/errors` | `admin.imports.download.errors` | `DataImportController@downloadErrors` | `can:data-imports.manage` | File (404 if no errors) |

### 1.4 API (`routes/api.php`)

Only a stub: `GET /api/` returns `{"message": "Bienvenido a la API"}`. No real REST endpoints.

### 1.5 Auth (`routes/auth.php` via Fortify)

Fortify-supplied routes for login, register, password reset, email verification, password confirmation, 2FA challenge, logout. Rendered by pages under `resources/js/pages/auth/`. Standard Laravel Fortify behavior — not enumerated here.

## 2. Controllers → Form Requests

Every public action that mutates state goes through a Form Request whose `authorize()` enforces permission gates (per ADR-005, layer 3). Read actions rely on the route-level `can:` middleware (layer 2).

| Controller | Action | Form Request | Notes |
|---|---|---|---|
| `DashboardController` | `show` | — | Computes KPIs + 30/15/5-day expiry alerts |
| `DriverDashboardController` | `index`, `confirmStart`, `confirmEnd` | inline | Re-checks docs in `confirmStart`; persists GPS opportunistically |
| `DriverDashboardController` | `decline` | `DriverDeclineServiceRequest` | Creates incident + notifies operator |
| `DriverLocationController` | `store` | inline | 403 if driver ≠ service.driver |
| `FuecVerifyController` | `show` | — | Blade, public |
| `GanttController` | `index` | — | Tags vehicle rows with `blocked: true` on doc expiry |
| `DaySummaryController` | `index`, `export` | — | CSV streaming for export |
| `DocumentTypeController` | `store`, `update` | `DocumentTypeStoreRequest`, `DocumentTypeUpdateRequest` | Catalog |
| `EpsController` | `store`, `update` | `EpsStoreRequest`, `EpsUpdateRequest` | Catalog |
| `PensionFundController` | `store`, `update` | `PensionFundStoreRequest`, `PensionFundUpdateRequest` | Catalog |
| `SeveranceFundController` | `store`, `update` | `SeveranceFundStoreRequest`, `SeveranceFundUpdateRequest` | Catalog |
| `IncidentTypeController` | `store`, `update` | `IncidentTypeStoreRequest`, `IncidentTypeUpdateRequest` | Catalog |
| `ThirdPartyController` | `store`, `update` | `ThirdPartyStoreRequest`, `ThirdPartyUpdateRequest` | JSON if `Accept: application/json` |
| `DriverController` | `store`, `update`, `inviteAccount` | `DriverStoreRequest`, `DriverUpdateRequest`, `DriverInviteAccountRequest` | Optionally creates linked User + sends invite |
| `VehicleController` | `store`, `update` | `VehicleStoreRequest`, `VehicleUpdateRequest` | |
| `ContractController` | `store`, `update` | `ContractStoreRequest`, `ContractUpdateRequest` | Auto-numbers generic contracts |
| `ServiceController` | `store` | `ServiceStoreRequest` | Conflict + doc/license + contract validation; retroactive logging |
| `ServiceController` | `update` | `ServiceUpdateRequest` | Status-transition + reopen logging; justification required on executed day |
| `ServiceController` | `destroy` | inline | Admin-only on executed day |
| `InvoiceController` | `store`, `update` | `InvoiceStoreRequest`, `InvoiceUpdateRequest` | |
| `InvoiceController` | `attachServices` | `InvoiceServiceAttachRequest` | May override billing-affecting incidents (override is logged) |
| `InvoiceController` | `markPaid`, `detachService`, `recomputeTotal`, `pdf` | inline | |
| `ServiceIncidentController` | `store`, `update` | `ServiceIncidentStoreRequest`, `ServiceIncidentUpdateRequest` | `is_driver_report` derived from role; notifies on billing impact |
| `DayStatusController` | `store`, `update` | `DayStatusStoreRequest`, `DayStatusUpdateRequest` | |
| `DayStatusController` | `execute` | inline | Blocks if no services / any open services remain |
| `FuecController` | `store`, `preview` | `FuecStoreRequest` | Pre-checks contract/vehicle/driver/range; catches `FuecRangeExhaustedException` |
| `FuecController` | `cancel` | `FuecCancelRequest` | Reason 10–500 chars |
| `FuecNumberRangeController` | `store`, `update` | `FuecNumberRangeStoreRequest`, `FuecNumberRangeUpdateRequest` | Activating one deactivates others |
| `VehicleLocationController` | `store`, `update` | `VehicleLocationStoreRequest`, `VehicleLocationUpdateRequest` | Defaults `captured_by = current user` |
| `UserController` | `store`, `update`, `toggleActive`, `resetPassword` | `UserStoreRequest`, `UserUpdateRequest`, `UserToggleActiveRequest`, `UserResetPasswordRequest` | Welcome email + temp password optional |
| `UserController` | `destroy` | inline | Blocks self-delete, last-admin, linked-driver |
| `RoleController` | `update` | `RoleUpdateRequest` | Syncs permissions, logs delta |
| `AuditLogController` | `index` | — | QueryBuilder on `Activity` |
| `DataImportController` | `store` | `DataImportStoreRequest` | Handles dry-run / retry-as-real / upload to S3 / dispatch job |
| `Settings\ProfileController` | `update` | `ProfileUpdateRequest` | Resets `email_verified_at` on email change |
| `Settings\ProfileController` | `destroy` | `ProfileDeleteRequest` | Soft-deletes + logout + invalidate session |
| `Settings\PasswordController` | `update` | `PasswordUpdateRequest` | Clears `must_change_password` flag |
| `Settings\TwoFactorAuthenticationController` | `show` | `TwoFactorAuthenticationRequest` | Standard Fortify flow |

## 3. Inertia page tree

`resources/js/pages/*` — every page resolves from a controller `Inertia::render('…')` call. Path alias `@/` → `resources/js/`.

| Page path | Props consumed | Form mutations | Domain components |
|---|---|---|---|
| `dashboard.tsx` | `kpis`, `documentAlerts` | — | — |
| `driver/index.tsx` | `services`, `driver`, `selectedDate`, `isToday` | confirm-start / confirm-end / decline inline | `DriverServiceCard`, `ServiceStatusBadge` |
| `gantt/index.tsx` | `vehicles`, `services`, `dayStatus`, `municipalities`, `date`, `municipalityId`, `canCreateServices` | Date/municipality filters | `GanttHeader`, `HourlyGrid`, `ServiceBar`, `VehicleSidebarItem` |
| `day-summary/index.tsx` | `services`, `dayStatus`, `summary`, `date`, `canExecuteDay` | — | — |
| `day-statuses/index.tsx` | `dayStatuses`, `serviceCounts`, `year`, `month`, `selectedDate`, `dayServices` | Calendar nav + drill-down | — |
| `services/index.tsx` | `services`, `filterContracts`, `filterDrivers`, `filterVehicles`, `filterMunicipalities` | Filter bar + row actions | — |
| `services/create.tsx` | `vehicles`, `drivers`, `contracts`, `municipalities`, `prefill` | POST `/services` | — |
| `services/edit.tsx` | `service`, `dayStatus`, `canEditExecuted`, `isAdmin`, `vehicles`, `drivers`, `contracts`, `municipalities` | PUT `/services/{id}` | — |
| `services/show.tsx` | `service`, `dayStatus`, `recentIncidents` | Links → edit/delete/create-incident | — |
| `service-incidents/{index,create,edit,show}.tsx` | varies | POST / PUT | `ServiceCombobox` on create |
| `incident-types/{index,create,edit,show}.tsx` | varies | POST / PUT | — |
| `vehicles/{index,create,edit,show}.tsx` | varies (`thirdParties`, `municipalities` on form pages) | POST / PUT | — |
| `drivers/{index,create,edit,show}.tsx` | varies (+ catalog options) | POST / PUT + invite/resend on show | — |
| `third-parties/{index,create,edit,show}.tsx` | varies | POST / PUT | `ThirdPartyCombobox` on index |
| `contracts/{index,create,edit,show}.tsx` | varies | POST / PUT | `ThirdPartyCombobox` on index |
| `invoices/{index,create,edit,show}.tsx` | `invoice`, `recentServices`, `computedTotal`, `candidateServices`, `blockedCandidateServices` (show) | POST / PUT + service picker (attach/detach), mark-paid, pdf | `ServicePickerDialog`, `ThirdPartyCombobox` |
| `fuecs/{index,create,show}.tsx` | `fuecs` / `fuec`, `verifyUrl` | service picker → POST; cancel | `ServiceCombobox` |
| `fuec-number-ranges/{index,create,edit,show}.tsx` | varies | POST / PUT | — |
| `gps/map.tsx` | `activeServices` | — | Leaflet |
| `vehicle-locations/{index,create,edit,show}.tsx` | varies | POST / PUT | — |
| `users/index.tsx` | `users`, `availableRoles` | Inline actions: toggle-active, reset-password, delete | — |
| `roles/{index,show}.tsx` | `role`, `users`, `permissionGroups`, `assignedPermissions` (show) | PUT `/roles/{role}` | — |
| `permissions/index.tsx` | `groups` | — | — |
| `audit-log/index.tsx` | `activities`, `users`, `subjectTypes` | Filter bar | — |
| `admin/imports/{index,create,show}.tsx` | `imports`, `types`, `import` | Upload + retry-as-real + purge / download | — |
| `document-types/`, `eps/`, `pension-funds/`, `severance-funds/` (full CRUD pages) | catalog rows | POST / PUT | — |
| `settings/{profile,password,appearance,two-factor}.tsx` | varies | PATCH / PUT | — |
| `auth/{login,register,forgot-password,reset-password,verify-email,confirm-password,two-factor-challenge}.tsx` | Fortify | Fortify forms | — |
| `welcome.tsx` | — | — | — |

## 4. Shared Inertia data + global middleware

`HandleInertiaRequests` shares to every page:

| Key | Type | Source |
|---|---|---|
| `name`, `tagline` | string | `config('app.*')` |
| `url` | string | request fullUrl |
| `auth.user` | `User \| null` | request user |
| `auth.permissions` | `string[]` | `user->getAllPermissions()->pluck('name')` |
| `auth.roles` | `string[]` | `user->getRoleNames()` |
| `auth.featureFlags.fuec` | bool | `config('sgte.fuec_enabled')` |
| `auth.featureFlags.gps` | bool | `config('sgte.gps_enabled')` |
| `sidebarOpen` | bool | Cookie (default true) |
| `config.operation_tz` | string | `Tz::operation()` |
| `config.viewer_tz` | string | `Tz::viewer($request)` |

Global middleware stack of note (registered in `bootstrap/app.php`):

| Middleware | Role |
|---|---|
| `HandleAppearance` | Theme detection |
| `CaptureViewerTimezone` | Reads `X-Viewer-Timezone` header / `viewer_tz` cookie; persists to `users.timezone` |
| `HandleInertiaRequests` | Shared props above |
| `AddLinkHeadersForPreloadedAssets` | HTTP/2 preload hints |
| `EnsureUserIsActive` | 403 if `user.is_active = false` |
| `EnsurePasswordChanged` | Forces redirect to password change if `must_change_password = true` |
| `EnsureFuecEnabled` (alias `fuec.enabled`) | 404 entire FUEC route group when flag off |
| `EnsureGpsEnabled` (alias `gps.enabled`) | 404 entire GPS route group when flag off |

## 5. Sidebar navigation

Hierarchy in `resources/js/components/app-sidebar.tsx`. Each item is hidden unless the user has the listed permission (or the listed role for the driver-only group).

| Group | Icon | Visibility gate | Items (label → Wayfinder route) |
|---|---|---|---|
| Panel | LayoutGrid | `dashboard.view` | Panel → `dashboard()` |
| Conductor (Driver-only landing) | Truck | role `Driver` (also `services.register-times`) | Mis Servicios → `driverDashboardIndex()` |
| Producción | Calendar | `services.view` | Servicios → `servicesIndex()`; Planificador → `ganttIndex()`; Resumen del Día → `daySummaryIndex()`; Calendario → `dayStatusesCalendar(year)`; Novedades → `serviceIncidentsIndex()` |
| Gestión | Wrench | `vehicles.view` | Vehículos, Conductores, Terceros, Contratos → resp. `*Index()` |
| Facturación | Receipt | `invoices.view` | Facturas → `invoicesIndex()` |
| Administración | Shield | `users.view` | Usuarios, Roles, Permisos, Auditoría |
| FUEC (conditional) | FileText | `auth.featureFlags.fuec` && (`fuec.view` ∥ `fuec.generate`) | Documentos FUEC, Rangos de Números |
| GPS (conditional) | MapPin | `auth.featureFlags.gps` && `vehicle-locations.view` | Mapa, Ubicaciones |
| Catálogos | Settings | `catalogs.manage` | Tipos de Documento, EPS, Fondos de Pensión, Fondos de Cesantías, Tipos de Novedad |
| Configuración | Settings | `data-imports.manage` | Importaciones de Datos → `admin.imports.index` |

CLAUDE.md notes Driver redirects from `/` → `/driver`. Confirm in Phase 3 that Operator and Accounting do NOT see Administración / FUEC / GPS items even if feature flags are on.

## 6. Models, casts, traits, relationships

Datetime-bearing models are listed first (they own the ADR-007 invariants tested most heavily).

| Model | Owns its timezone? | Key datetime casts (immutable_datetime) | Relationships | Other traits |
|---|---|---|---|---|
| `Service` | Yes (`timezone` col) | `planned_start_at`, `actual_start_at`, `actual_end_at`, `service_date_local` (immutable_date) | `belongsTo` Contract, Vehicle, Driver, Invoice, Municipality (origin & destination); `hasMany` ServiceIncident, VehicleLocation, Fuec | `HasTimezone`, `LogsActivity`, `SoftDeletes`, `SearchesDatabase`, `Scout Searchable` |
| `Contract` | Yes | `start_at`, `end_at` | `belongsTo` ThirdParty; `hasMany` Service | `HasTimezone`, `LogsActivity`, `SoftDeletes` |
| `Driver` | Yes | `license_due_at` | `belongsTo` User, Municipality, DocumentType, Eps, PensionFund, SeveranceFund; `hasMany` Service, VehicleLocation | `HasTimezone`, `Scout Searchable`, `LogsActivity` |
| `Vehicle` | Yes | `soat_due_at`, `rtm_due_at`, `operation_card_due_at` | `belongsTo` Municipality, ThirdParty; `hasMany` Service, VehicleLocation | `HasTimezone`, `Scout Searchable`, `LogsActivity`, `SoftDeletes` |
| `Invoice` | Yes | `issued_at` | `belongsTo` ThirdParty; `hasMany` Service | `LogsActivity`, `SoftDeletes` |
| `DataImport` | Yes | timestamps | `belongsTo` User | — |
| `ServiceIncident` | Inherits from Service | `reported_at` | `belongsTo` Service, IncidentType, User (`registrar_id`) | `LogsActivity` |
| `Fuec` | Inherits from Service | `generated_at` | `belongsTo` Service, FuecNumberRange | `LogsActivity` |
| `DayStatus` | Operational TZ only | `date` (DATE, no TZ), `executed_at` | `belongsTo` User (`executor_id`) | `LogsActivity` |
| `FuecNumberRange` | n/a | — | `hasMany` Fuec | — |
| `VehicleLocation` | n/a | `recorded_at` | `belongsTo` Vehicle, Service, User (`captured_by`) | — |
| `ThirdParty` | n/a | — | `belongsTo` Municipality, DocumentType; `hasMany` Vehicle, Contract, Invoice | `LogsActivity`, `SoftDeletes` |
| `User` | Stores viewer TZ in `timezone` col | timestamps + `last_login_at`, `email_verified_at` | `hasOne` Driver; Spatie roles/permissions; `TwoFactorAuthenticatable` | `Scout Searchable`, `LogsActivity` |
| `IncidentType` | n/a | — | `hasMany` ServiceIncident | — |
| `Municipality` | n/a | — | `belongsTo` Department; `hasMany` Service (×2), Vehicle, ThirdParty, Driver | — |
| `Department` | n/a | — | `hasMany` Municipality | — |
| `DocumentType`, `Eps`, `PensionFund`, `SeveranceFund` | n/a | — | `hasMany` Driver / ThirdParty | — |

Key enums in `app/Enums/` (consumed by TS via `php artisan enum:typescript`):

- `Role` — Super Admin, Admin, Operator, Driver, Accounting.
- `Permission` — 54 permissions (CRUD per module + `audit-log.view` + `*.manage`).
- `ServiceStatus`, `DayStatusEnum`, `PaymentMethod`, `PaymentStatus`, `VehicleType`, `VehicleStatus`, `FuecStatus`, `DataImportType`, `DataImportStatus`, `IncidentSeverity`.

## 7. Three-layer authorization recap (ADR-005)

Every mutating request is guarded by three layers. Phase 2 scenarios must probe each:

1. **Super Admin bypass** — `Gate::before` in `AppServiceProvider` returns `true` for the Super Admin role. Scenario: SA can do anything tested below.
2. **Route-level `can:` middleware** — coarse gate before controller runs. Scenario: a role without the route-level permission gets 403 *before* the controller hydrates anything (e.g., Accounting visiting `/users` → 403).
3. **FormRequest `authorize()`** — fine-grained, runs *before* `rules()`. Prevents info leakage (you don't see validation errors if you weren't allowed). Scenario: a role with `services.view` but not `services.create` gets 403 from `ServiceStoreRequest::authorize()`, not a 422.

No Eloquent Policies are used; do not write Policy-based tests.
