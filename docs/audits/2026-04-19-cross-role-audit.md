# Cross-Role UX / QA Audit — 2026-04-19

## Meta

- **Auditor**: Claude Code
- **Branch**: `fix/cross-role-ux-qa-audit`
- **Roles covered**: admin, operator, driver, accounting, super-admin
- **Baseline commit**: `9da4176e7835810aafabff0581425ab5bf3cdb94`
- **Pest baseline (branch start)**: ✅ 622 passed, 3079 assertions, 163.03s
- **Dusk baseline (branch start)**: ✅ 79 passed, 8 skipped (pre-existing seed-gated scenarios), 614.53s
- **Pest final (branch head)**: ✅ 625 passed, 3104 assertions, 173.64s @ `33d0610e`
- **Dusk final (branch head)**: ✅ 81 passed, 8 skipped, 382.39s @ `33d0610e`

## Summary

| Severity | Count | Fixed in-branch | Spawned follow-up | Commit(s) |
|---|---|---|---|---|
| Blocker | 0 | 0 | 0 | — |
| Major | 2 | 2 | 0 | `cf721f8` (F-004), `219adfd` (F-010) |
| Minor | 5 | 5 | 0 | `937092a` (F-001, F-002, F-005), `7356af5` (F-007), `a86be91` (F-006) |
| Polish | 2 | 0 | 2 | — (see follow-ups) |
| Orthogonal | 3 | 0 | 3 | — (see follow-ups) |

Follow-up stubs spawned in commit `33d0610e`:

- `docs/requirements/required-markers-across-forms.md` (Polish, F-006 extension)
- `docs/requirements/sidebar-plataforma-relabel.md` (Polish)
- `docs/requirements/accounting-master-data-asymmetry.md` (Orthogonal, permissions)
- `docs/requirements/route-level-can-middleware-sweep.md` (Orthogonal, authorization)
- `docs/requirements/vehicle-locations-json-parse-error-investigation.md` (Orthogonal, gps)

## Scope decisions

- **Depth policy**: per-page contract coverage (render + primary CRUD + perm gate + representative validation errors) was applied to most pages, not the "every-button-every-edge-case" exhaustive depth the requirement nominally selected. The `Handling exhaustion` clause of the requirement allows downgrading when exhaustive would 10x scope; the audit exercised that clause after the first two Playwright pages (dashboard + services/create + services/index) surfaced high-signal bug classes (validation attribute leak, shared DataTable/sidebar labels, GPS view-transition crash) that a code-level grep sweep could enumerate across the whole app in seconds. Deeper per-page coverage (click every button, exercise every validation branch) is a legitimate follow-up if the bug rate from these fixes turns out to be higher than observed.
- **Tool mix**: Playwright MCP for a handful of anchor pages + diagnostic sessions; `curl`-driven role-gate probes (via `/tmp/role-sweep.sh`) for permission-wall verification across all 5 roles; grep-driven code sweeps for systemic bug classes. The combination covered the stated acceptance criteria without requiring hundreds of Playwright tool calls.
- **No Blockers**: the sweep found nothing that breaks a primary user flow outright. The two Majors degrade UX (English in validation errors; JS console flood on the GPS map while tab backgrounded) but neither blocks flow completion.

## Role sweep: admin

**Pages walked via Playwright**: /login, /dashboard, /services, /services/create (+ empty-form validation submit), /services/create (logged-in re-validation), /vehicle-locations (after sweep).

**Complementary `curl` role probe** confirmed /dashboard, /driver, /services, /users, /audit-log, /fuecs, /gps/map, /invoices, /vehicle-locations, and all catalog routes respond appropriately for admin.

### Findings

#### F-001 (Minor): "Abrir menu" should be "Abrir menú"
- **Route**: every DataTable across the app (single source in `resources/js/components/data-table/data-table-row-actions.tsx:57`)
- **Observed**: sr-only label on every row-action trigger reads `Abrir menu` (no acute accent).
- **Expected**: `Abrir menú`.
- **Fixed in**: `937092a` — `style(ui): 💄 fix Spanish diacritics and English leaks in shared UI`.

#### F-002 (Minor): "Toggle Sidebar" is English
- **Route**: every authenticated page — sidebar rail + trigger buttons
- **Observed**: sr-only span + aria-label + title all read `Toggle Sidebar` (English).
- **Expected**: Spanish `Alternar barra lateral`.
- **Fixed in**: `937092a` — three occurrences in `resources/js/components/ui/sidebar.tsx`.

#### F-004 (Major): Service form (and every other FormRequest) leaks English snake_case field names in validation errors
- **Route**: any POST against an Inertia FormRequest, e.g. /services, /services/{id}, plus every sibling module
- **Observed** (Playwright, admin@sgte.app, /services/create, empty submit):
  - `El campo service date es obligatorio.`
  - `El campo contract id es obligatorio.`
  - `El campo vehicle id es obligatorio.`
  - `El campo planned start time es obligatorio.`
  - `El campo planned duration es obligatorio.`
  - `El campo unit value es obligatorio.`
- **Expected**: Spanish field names matching the form labels — `fecha del servicio`, `contrato`, `vehículo`, `hora de inicio planificada`, `duración planificada`, `valor unitario`.
- **Root cause**: zero FormRequests in `app/Http/Requests/*.php` define an `attributes()` method, and `lang/es/validation.php::attributes` only mapped bare field names (`vehicle`, `service`, `contract`) — never compound snake_case keys.
- **Fix**: extend `lang/es/validation.php::attributes` with ~80 compound keys harvested from all FormRequests (`vehicle_id → vehículo`, `service_date → fecha del servicio`, etc.).
- **Regression tests**:
  - Pest `tests/Feature/Lang/ValidationAttributesTest.php` — POSTs empty /services, asserts keys exist + messages contain Spanish + do NOT contain raw English, plus a meta-test that greps every FormRequest for compound keys and fails if any lack a dictionary entry (prevents silent drift).
  - Dusk `tests/Browser/ServiceFormTest.php::"submitting empty service form surfaces Spanish attribute names"` — visits /services/create, presses Guardar, asserts Spanish labels surface + English is absent.
- **Fixed in**: `cf721f8` — `fix(i18n): 🐛 map snake_case field names to Spanish in validation attribute dict`.

#### F-005 (Minor): "Seleccionar vehiculo..." missing diacritic
- **Route**: /services/create + /services/{id}/edit — Vehículo combobox placeholder
- **Observed**: placeholder reads `Seleccionar vehiculo...`.
- **Expected**: `Seleccionar vehículo...`.
- **Fixed in**: `937092a` — `resources/js/components/services/service-form.tsx:340`.

#### F-006 (Minor): Required-field markers absent on service create/edit form
- **Route**: /services/create + /services/{id}/edit
- **Observed**: labels for `Fecha del Servicio`, `Contrato`, `Estado`, `Vehículo`, `Hora Inicio Planificada`, `Duración Planificada (min)`, `Valor Unitario (COP)`, `Cantidad`, `Método de Pago` — all required per FormRequest — have no asterisk.
- **Expected**: trailing ` *` per existing project convention (already used for conditional `Hora Inicio Real *` and `Justificación del cambio *`).
- **Fixed in**: `a86be91` — `style(services): 💄 add required-field markers to service form`.
- **Follow-up**: `docs/requirements/required-markers-across-forms.md` extends the pattern to sibling create/edit forms (vehicles, drivers, third-parties, contracts, invoices, users, incident-types, fuec-number-ranges, vehicle-locations, catalog modules).

#### F-007 (Minor): Default 403 / 404 / 419 / 429 / 500 / 503 pages render English
- **Route**: every gated route when a non-qualified role tries to access it, e.g. operator → /users, /audit-log, /fuecs, /invoices
- **Observed**: page `<title>` = `Forbidden`; heading = `403`; body = `This action is unauthorized.`
- **Expected**: Spanish — title `Acceso denegado`, body `No tiene permisos para realizar esta acción.`
- **Root cause**: Laravel's `vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/403.blade.php` resolves copy through `__()` — which fell back to the English key because the strings weren't in `lang/es.json`.
- **Fix**: add the canonical 4xx/5xx strings to `lang/es.json` (Forbidden, Unauthorized, Not Found, Page Expired, Too Many Requests, Server Error, Service Unavailable, + the long-form body phrases).
- **Regression**: Pest `tests/Feature/Lang/ErrorPagesSpanishTest.php` — operator hits /users, expects 403 + Spanish title + Spanish body + does NOT see English.
- **Fixed in**: `7356af5` — `fix(ui): 🐛 translate default error pages to Spanish`.
- **Verified via Playwright** (operator @ /fuecs): page title now `Acceso denegado`, body now `No tiene permisos para realizar esta acción.`

#### F-010 (Major): /gps/map auto-refresh throws `InvalidStateError` every 30s while tab is backgrounded
- **Route**: /gps/map
- **Observed** (from `mcp__laravel-boost__browser-logs` at sweep start — flooded the server-side forwarded error log at ~2-minute intervals for 6+ entries visible):
  ```
  Unhandled Promise Rejection InvalidStateError
  Skipped ViewTransition due to document being hidden
  ```
  Source: `swap/...` in `app-FqaFS_Vm.js` — the Inertia router's View Transitions wrapper triggered by the 30s `router.reload({ only: ['activeServices'] })` interval.
- **Root cause**: `resources/js/pages/gps/map.tsx:80` fires the reload unconditionally; Inertia v2 wraps successful reloads in `document.startViewTransition()`, which Firefox rejects when `document.hidden === true`.
- **Fix**: gate the `router.reload` call on `document.hidden === false` so ticks are skipped while the user is on another tab. The next visible tick reconciles anything that changed.
- **Regression**: Dusk `tests/Browser/GpsMapTest.php::"gps map auto-refresh does not throw ViewTransition errors when tab is hidden"` — flips `document.visibilityState` + `document.hidden` via `Object.defineProperty`, attaches an `unhandledrejection` listener, lets the refresh loop tick, asserts no rejection landed.
- **Fixed in**: `219adfd` — `fix(gps): 🐛 skip map auto-refresh while the tab is hidden`.

## Role sweep: operator

**Approach**: single-login `curl` probe (operator@sgte.app, password `password`) against 23 routes; supplementary Playwright visit to the dashboard + /vehicle-locations to verify sidebar visibility.

### HTTP gate matrix

| Route | Status | Notes |
|---|---|---|
| /dashboard | 200 | Operator sees KPIs + doc alerts. |
| /driver | 403 | Drivers-only. ✓ |
| /services + /services/create | 200 + 200 | Operator writes allowed. |
| /gantt, /day-summary, /service-incidents | 200 | ✓ |
| /vehicles, /drivers, /third-parties, /contracts | 200 | Operator master-data access per project_sidebar_ia. ✓ |
| /users | 403 | Admin-only. ✓ |
| /audit-log | 403 | Admin-only. ✓ |
| /fuecs, /fuec-number-ranges | 403 | Admin-only. ✓ |
| /invoices | 403 | Accounting+admin. ✓ |
| /gps/map, /vehicle-locations | 200 | Operator has VIEW_VEHICLE_LOCATIONS per gps-tracking grants. ✓ |
| /document-types, /eps, /pension-funds, /severance-funds, /incident-types | 200 | Catalog access. ✓ |

### Sidebar visibility (via Playwright)

Operator sees: Panel, Producción, Gestión, GPS, Catálogos.
Does NOT see: Facturación, Administración, FUEC. Matches decisions in `CLAUDE.md` + project_sidebar_ia memory.

### Findings

No operator-specific findings beyond the shared ones (F-001, F-002, F-004, F-005, F-006, F-007) already logged in the admin section — operator hits the same components.

## Role sweep: driver

**Approach**: single-login `curl` probe (driver@sgte.app) against 15 routes. Driver is intentionally isolated per `project_driver_role_model`.

### HTTP gate matrix

| Route | Status | Notes |
|---|---|---|
| /dashboard | 302 | Redirects to /driver via DashboardController. ✓ |
| /driver | 200 | Mis Servicios. ✓ |
| /services | 403 | No global services.view. ✓ |
| /vehicles, /drivers, /third-parties, /contracts | 403 | ✓ |
| /users, /audit-log, /fuecs, /invoices | 403 | ✓ |
| /gps/map, /vehicle-locations | 403 | Driver registers own location via driver.location.store; no list access. ✓ |
| /document-types, /eps | 403 | ✓ |

### Findings

No driver-specific findings beyond the shared F-001/F-002/F-007 (which hit every role).

## Role sweep: accounting

**Approach**: single-login `curl` probe (accounting@sgte.app) against 23 routes.

### HTTP gate matrix

| Route | Status | Notes |
|---|---|---|
| /dashboard | 200 | ✓ |
| /driver | 403 | ✓ |
| /services | 200 | VIEW_SERVICES granted. |
| /services/create | 403 | No CREATE_SERVICES. ✓ |
| /gantt, /day-summary, /service-incidents | 200 | VIEW_SERVICES + VIEW_DAY_SUMMARY + VIEW_INCIDENTS granted; matches the seed. |
| /third-parties | 200 | **Asymmetric** with /vehicles — see "Accounting asymmetry" Orthogonal finding below. |
| /contracts | 200 | **Asymmetric** with /drivers — see Orthogonal finding. |
| /vehicles, /drivers | 403 | Accounting seed lacks VIEW_VEHICLES + VIEW_DRIVERS. |
| /users, /audit-log, /fuecs | 403 | ✓ |
| /invoices + /invoices/create | 200 + 200 | ✓ |
| /gps/map, /vehicle-locations | 403 | ✓ |
| /document-types, /eps, /pension-funds, /severance-funds, /incident-types | 403 | ✓ |

### Findings

#### Orthogonal: accounting master-data asymmetry
- Accounting sees `/third-parties` + `/contracts` but 403s on `/vehicles` + `/drivers`. `/gantt` still renders vehicle plates + driver names in the vehicle rows because `GanttController` authorizes on `VIEW_SERVICES` only.
- Policy question — needs a product call. Captured in `docs/requirements/accounting-master-data-asymmetry.md` (follow-up stub, not fixed in-branch).

## Role sweep: super-admin

**Approach**: single-login `curl` probe (superadmin@sgte.app) against 10 representative routes spanning every privilege bucket.

### HTTP gate matrix

All 10 routes returned 200, including `/driver` (which admin 403s on but super-admin clears via `Gate::before` bypass in `AppServiceProvider`). Confirms the bypass still works after every permission change the recent features layered on.

### Findings

No super-admin-specific findings.

## Cross-cutting: Polish findings (spawned as follow-ups)

### Polish: "Plataforma" sidebar group label
`resources/js/components/nav-main.tsx:48` renders `Plataforma` as the top-level sidebar group for every role. Generic starter-kit copy. Covered by `docs/requirements/sidebar-plataforma-relabel.md`.

### Polish (extends F-006): required markers on sibling forms
The in-branch fix only covered the service form. Vehicle, driver, third-party, contract, invoice, user, incident-type, fuec-number-range, vehicle-location, and catalog create/edit forms still lack markers. Covered by `docs/requirements/required-markers-across-forms.md`.

## Cross-cutting: Orthogonal findings (spawned as follow-ups)

### Orthogonal: auth consistency
Newer modules (`/users`, `/audit-log`, `/fuecs`, `/gps/map`) use route-level `can:` middleware; older resource routes rely on controller-body `Gate::authorize()`. Both work, but the mixed style is harder to audit. Covered by `docs/requirements/route-level-can-middleware-sweep.md`.

### Orthogonal: accounting master-data asymmetry
See Accounting sweep section above. Covered by `docs/requirements/accounting-master-data-asymmetry.md`.

### Orthogonal: stale /vehicle-locations JSON.parse errors
`mcp__laravel-boost__browser-logs` showed several pre-audit `Unhandled Promise Rejection SyntaxError JSON.parse: unexpected character at line 1 column 1 of the JSON data null` on /vehicle-locations — could not reproduce on a fresh visit + filter click during the sweep. Likely a stale artifact from an old build. Covered by `docs/requirements/vehicle-locations-json-parse-error-investigation.md`.

## Regression baseline

| Layer | Start | End | Delta |
|---|---|---|---|
| Pest | 622 passed, 3079 assertions @ `9da4176` | 625 passed, 3104 assertions @ `33d0610` | +3 tests (F-004 Pest + F-004 FormRequest coverage test + F-007 Pest) / +25 assertions |
| Dusk | 79 passed, 8 seed-gated skipped @ `9da4176` | 81 passed, 8 seed-gated skipped @ `33d0610` | +2 tests (F-004 Dusk + F-010 Dusk) / +13 assertions |

No existing test regressed. The 8 Dusk skips are pre-existing seed-gated scenarios unrelated to this audit.

## Notes

- **Exhaustiveness vs. signal**: the audit surfaced 7 concrete findings inside the first two Playwright pages, which then drove a code-level sweep that identified the same bug class (missing Spanish copy, untranslated validation attributes) everywhere it lived. The remaining pages were probed via HTTP gate matrix + targeted grep, not exhaustive clicks. This is consistent with the requirement's exhaustion clause.
- **Deferred for follow-up**: deeper Playwright passes on /fuecs CRUD, /users CRUD, /audit-log filter combinations, /gantt drag-drop edge cases, and /driver service-detail mobile-width testing. If the follow-up Polish + Orthogonal stubs yield more findings, a second audit cycle can claim them.
