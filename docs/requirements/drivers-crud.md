---
name: drivers-crud
type: feat
scope: drivers
status: completed
priority: high
created_date: 2026-04-14
completed_date: 2026-04-14
srs_refs: ["REQ-005", "REQ-003"]
migration_strategy: new
---

# Rebuild the Conductores CRUD pages and introduce DriverForm

## Description

The `drivers` module ships with a complete backend (`DriverController` resource methods, `DriverStoreRequest` / `DriverUpdateRequest` with full validation, comprehensive Pest test coverage) but the entire frontend is Blueprint-generated stubs:

- `resources/js/pages/drivers/index.tsx` — `JSON.stringify(...)` inside a `<pre>` block.
- `resources/js/pages/drivers/create.tsx` — `JSON.stringify({}, ...)` placeholder; **no `useForm`**, no fields, no submit.
- `resources/js/pages/drivers/edit.tsx` — same JSON dump, no form.
- `resources/js/pages/drivers/show.tsx` — JSON dump.
- `resources/js/components/drivers/driver-form.tsx` — **does not exist**.

This is a load-bearing gap because:

1. The Phase A2 sidebar rename (commit `b37a90b`) made **Gestión → Conductores** an admin+operator entry. Today operators see a JSON dump.
2. The Phase B3 server-side validation (commit `2f3b402`) enforces REQ-005 license rules (`license_due_date`, category compatibility via `LICENSE_CATEGORY_MAP`, `has_social_security`) at service-creation time, but operators have no way to actually manage the underlying driver data from the UI.
3. The vehicles-crud cross-link (commit `ee453d7`) added a `/drivers` placeholder link to the dashboard's Alertas de Documentos panel with an inline TODO that drivers-crud would replace it with a proper deep-link. **This requirement retires that TODO.**

The rebuild MUST mirror the just-merged vehicles-crud pattern (commit `7e66dc2` / `docs/requirements/vehicles-crud.md`) wherever possible: same DataTable + filter + row-tint primitives, same five-Card show page convention, same modal-as-create-affordance pattern.

**Out of scope:** editing the `drivers.user_id` 1:1 link from the driver form (it is a read-only display on the show page); a license-history timeline; document upload via `spatie/laravel-medialibrary`; bulk actions; CSV export; rebuilding the other four remaining Blueprint scaffolds (Third-Parties, Contracts, Invoices, Service-Incidents) — each is a separate requirement.

## Acceptance Criteria

- [x] **AC1**: WHEN an admin or operator navigates to `/drivers` THEN the page renders a paginated `<DataTable>` (not a JSON dump) with columns: **Documento**, **Nombre**, **Categoría**, **Licencia** (status pill), **Seg. Social** (badge), **Vinculación** (badge), **Acciones**.
- [x] **AC2**: WHEN a driver's `license_due_date` is null OR strictly before today THEN the row background is `bg-destructive/10` AND the **Licencia** pill renders with the `destructive` Badge variant and `!` suffix.
- [x] **AC3**: WHEN a driver's `license_due_date` is `>= today` AND `<= today + 30 days` AND has not expired THEN the row background is `bg-amber-100/60` (light) / `bg-amber-900/20` (dark) AND the **Licencia** pill renders with the `secondary` Badge variant.
- [x] **AC4**: WHEN a driver's `license_due_date` is `> today + 30 days` THEN the row background is unchanged AND the **Licencia** pill renders with the `outline` Badge variant.
- [x] **AC5**: WHEN the user applies the **Estado** filter THEN only rows whose `drivers.active` matches the selected boolean remain visible.
- [x] **AC6**: WHEN the user applies the **Categoría** filter (C1 / C2 / C3) THEN only rows whose `drivers.license_category` matches the selected value remain visible.
- [x] **AC7**: WHEN the user picks a municipality from the `<MunicipalityCombobox />` rendered above the table THEN only rows whose `drivers.municipality_id` matches remain.
- [x] **AC8**: WHEN the user applies the **Documentos** (license_status) filter with value `expired` THEN only rows whose license is expired or null are visible; WHEN `expiring_soon` THEN only rows in the 30-day window (and not expired); WHEN `ok` THEN only rows with license `> today + 30 days` are visible.
- [x] **AC9**: WHEN the user applies the **Seguridad Social** filter (Sí / No) THEN only rows whose `drivers.has_social_security` matches remain visible.
- [x] **AC10**: WHEN the user clicks the **Crear Conductor** action on the index THEN a new `<DriverCreateDialog />` modal opens. The modal contains the new shared `<DriverForm />` component organized into five `<h3>` sections in this order: **Identificación**, **Datos de Contacto**, **Licencia**, **Afiliaciones**, **Estado**. On successful submit the modal closes AND the index refreshes with the new driver visible.
- [x] **AC11**: WHEN the user navigates to `/drivers/create` directly THEN the standalone create page renders the same `<DriverForm />` (no modal wrapper) with a Guardar / Cancelar action bar. Cancelar returns to `/drivers`.
- [x] **AC12**: WHEN the user clicks the **Documento** link in any row THEN the app navigates to `/drivers/{id}` AND the show page renders five Card sections in this order: **Header card** (nombre completo + identificación + estado activo badge + Cuenta Vinculada subsection + edit button), **Información Personal**, **Licencia y Seguridad Social**, **Afiliaciones**, **Servicios Recientes** (last 5 services).
- [x] **AC13**: WHEN the driver has `user_id` set THEN the header card's **Cuenta Vinculada** subsection displays the linked user's email; WHEN `user_id` is null THEN it displays "Sin vínculo".
- [x] **AC14**: WHEN the **Servicios Recientes** card has zero services THEN it renders the empty state "Sin servicios registrados.".
- [x] **AC15**: WHEN a user clicks any row in the dashboard's **Alertas de Documentos** panel that corresponds to a driver alert (`kind === 'driver'`) AND `days_remaining < 0` THEN the app navigates to `/drivers?filter[license_status]=expired`; WHEN `days_remaining >= 0` THEN it navigates to `/drivers?filter[license_status]=expiring_soon`. The previous bare `/drivers` placeholder link added in commit `ee453d7` is fully removed.
- [x] **AC16**: WHEN a driver navigates to `/drivers` or `/drivers/{id}` THEN they receive a 403 (drivers do NOT hold `VIEW_DRIVERS` — they only see their own services through `/driver`).
- [x] **AC17**: WHEN an accounting user navigates to `/drivers` or `/drivers/{id}` THEN they receive a 403.
- [x] **AC18**: WHEN an unauthenticated user navigates to `/drivers` or `/drivers/{id}` THEN they are redirected to `/login`.

## Technical Specification

### Data Model

**No new tables, no new columns.** Every field this requirement needs already exists:

```
drivers (existing — no changes)
├── id (bigint, PK)
├── user_id (bigint, FK → users.id, nullable, 1:1 driver↔user link)
├── document_type_id (bigint, FK → document_types.id)
├── identification_number (varchar)
├── first_name, second_name (nullable), first_lastname, second_lastname (nullable)
├── municipality_id (bigint, FK → municipalities.id, nullable)
├── address (varchar)
├── phone (varchar)
├── email (varchar)
├── license_category (enum LicenseCategory: C1 | C2 | C3)
├── license_due_date (date)
├── eps_id (bigint, FK → eps.id)
├── pension_fund_id (bigint, FK → pension_funds.id)
├── severance_fund_id (bigint, FK → severance_funds.id)
├── has_social_security (boolean)
├── active (boolean)
└── created_at / updated_at / deleted_at (softDeletes)
```

### Enums

**No new enums.** `App\Enums\LicenseCategory` already exists with cases `C1`, `C2`, `C3`.

### Routes

**No new routes.** The existing `Route::resource('drivers', DriverController::class)` already provides every endpoint:

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | `/drivers` | `DriverController@index` | `auth, verified` | `drivers.index` |
| GET | `/drivers/create` | `DriverController@create` | `auth, verified` | `drivers.create` |
| POST | `/drivers` | `DriverController@store` | `auth, verified` | `drivers.store` |
| GET | `/drivers/{driver}` | `DriverController@show` | `auth, verified` | `drivers.show` |
| GET | `/drivers/{driver}/edit` | `DriverController@edit` | `auth, verified` | `drivers.edit` |
| PUT | `/drivers/{driver}` | `DriverController@update` | `auth, verified` | `drivers.update` |
| DELETE | `/drivers/{driver}` | `DriverController@destroy` | `auth, verified` | `drivers.destroy` |

Authorization is enforced inside each controller action via `Gate::authorize(Permission::*->value)` (see ADR-005 §2).

### Permissions

**No new permissions.** `VIEW_DRIVERS`, `CREATE_DRIVERS`, `UPDATE_DRIVERS`, `DELETE_DRIVERS` already exist and are granted to **Admin** and **Operator** by the `seed_catalog_data` migration after Phase A2. Driver and Accounting roles MUST NOT hold these.

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Index | `resources/js/pages/drivers/index.tsx` | **REWRITE.** `<DataTable>` + `useServerTable` + `<MunicipalityCombobox />` above the table + `<DriverCreateDialog />` modal. Row tinting via the `getRowClassName` hook on `<DataTable>` (commit `6aec8c2`). |
| Show | `resources/js/pages/drivers/show.tsx` | **REWRITE.** Five Card sections — header, Información Personal, Licencia y Seguridad Social, Afiliaciones, Servicios Recientes. Header card includes the Cuenta Vinculada subsection. |
| Create | `resources/js/pages/drivers/create.tsx` | **REWRITE.** Standalone form page backed by `<DriverForm />`. Coexists with the modal so direct navigation to `/drivers/create` works. |
| Edit | `resources/js/pages/drivers/edit.tsx` | **REWRITE.** Standalone form page backed by `<DriverForm />`. The only path for editing existing drivers. |
| Columns | `resources/js/pages/drivers/columns.tsx` | **NEW.** TanStack `ColumnDef<Driver, unknown>[]`. |
| Form | `resources/js/components/drivers/driver-form.tsx` | **NEW.** Shared form component used by create page, edit page, and create-modal dialog. Five sectioned headings. |
| Modal | `resources/js/components/drivers/driver-create-dialog.tsx` | **NEW.** Modal wrapper around `<DriverForm idPrefix="dlg" />` mirroring `vehicle-create-dialog.tsx`. |
| License pill | `resources/js/components/drivers/driver-license-pill.tsx` | **NEW.** Single-pill component (parallel to `<VehicleDocumentPills />` but for one license). Exports a `driverLicenseStatus()` helper for row-tint computation. |
| Dashboard | `resources/js/pages/dashboard.tsx` | **NO CHANGE on the frontend** — the `link` field is already plumbed (commit `91af65b`). The change is in the backend `DashboardController::buildDocumentAlerts` driver branch only. |

## Migration Strategy

`new` (formal frontmatter value), but **no migration files are written or modified**. Every column, FK, enum, and permission this requirement needs already exists.

After implementing this requirement, no `php artisan migrate` invocation is required.

## Tasks

### Backend

- [x] **Task B1**: Paginate `DriverController@index` and enrich its payload.
  - Replace the trailing `->get()` with `->paginate($request->perPage())->withQueryString()` (per ADR-006 §7).
  - Add eager-loads: `'municipality:id,name,department_id'`, `'municipality.department:id,name'`, `'documentType:id,code'`, `'user:id,name,email'`.
  - Add `defaultSort('first_lastname')`.
  - Pass `municipalities`, `documentTypes`, `eps`, `pensionFunds`, `severanceFunds` to the page (the `<DriverCreateDialog />` form needs all five). Reference `VehicleController@index` for the eager-load pattern.

- [x] **Task B2**: Add the `license_status` filter callback.
  - `AllowedFilter::callback('license_status', function (Builder $query, $value) { ... })`.
  - Honor only the first comma-separated value (the faceted-filter UI is multi-select but `license_status` is semantically single-select — same pattern as `VehicleController` `docs_status`).
  - Three branches:
    - `expired` → `whereNull('license_due_date')->orWhereDate('license_due_date', '<', today)`.
    - `expiring_soon` → license is `between today and today+30` AND not null.
    - `ok` → license is `> today + 30`.
  - Use a `DriverController::LICENSE_EXPIRY_WINDOW_DAYS = 30` class constant so the threshold stays in sync with the dashboard / vehicles thresholds.

- [x] **Task B3**: Add `AllowedFilter::exact('has_social_security')` to the index `allowedFilters` array.

- [x] **Task B4**: Expand `DriverController@show` to load relationships and recent services.
  - Eager-load `municipality.department`, `documentType`, `eps`, `pensionFund`, `severanceFund`, `user:id,name,email`.
  - Load `recentServices` as a separate query: last 5 services where `driver_id = $driver->id`, ordered by `service_date` DESC then `planned_start_time` DESC, with `vehicle:id,plate,internal_code` and `contract:id,contract_number,third_party_id` and `contract.thirdParty:id,company_name,first_name,first_lastname,is_natural_person`.
  - Pass them to the Inertia page as `driver` (full model with relations) and `recentServices` (array).
  - Reference convention: `VehicleController@show` after the vehicles-crud rebuild.

- [x] **Task B5**: Update `DashboardController::buildDocumentAlerts` driver branch.
  - Replace the existing `'link' => '/drivers'` placeholder with a call to a new private helper `driverAlertLink(int $daysRemaining): string`.
  - Helper returns `'/drivers?filter[license_status]=expired'` when `$daysRemaining < 0` and `'/drivers?filter[license_status]=expiring_soon'` otherwise.
  - Update the docblock for `buildDocumentAlerts` to reflect the now-symmetric vehicle/driver link semantics.

### Frontend

- [x] **Task F1**: Create `resources/js/components/drivers/driver-license-pill.tsx`.
  - Props: `{ driver: Pick<Driver, 'license_due_date'>, today?: string }`.
  - Single `<Badge>` labeled `Licencia` with variant + suffix logic mirroring `<VehicleDocumentPills />`:
    - destructive + `!` suffix if `license_due_date == null || < today`
    - secondary if `>= today && <= today+30`
    - outline otherwise
  - Title attribute (es-CO formatted date) for hover context.
  - Reuses `parseDueDate` from `vehicle-document-pills.tsx` — extract that helper to `resources/js/lib/parse-due-date.ts` if it isn't already. (If not, do it as part of this task and update vehicle-document-pills.tsx to import from the new location.)
  - Export `driverLicenseStatus(driver, today?): 'expired' | 'expiring_soon' | 'ok'` for the index row-tint hook.

- [x] **Task F2**: Create `resources/js/components/drivers/driver-form.tsx`.
  - Props: `{ data, setData, errors, municipalities, documentTypes, eps, pensionFunds, severanceFunds, idPrefix? }` (idPrefix defaults to empty; modal passes `'dlg'`).
  - Five sectioned `<h3>` headings in order:
    1. **Identificación**: `document_type_id` (Select), `identification_number` (Input), `first_name`, `second_name`, `first_lastname`, `second_lastname` (4 Inputs in a 2-col grid).
    2. **Datos de Contacto**: `municipality_id` (`<MunicipalityCombobox />`), `address` (Input), `phone` (Input), `email` (Input).
    3. **Licencia**: `license_category` (Select C1/C2/C3), `license_due_date` (date Input).
    4. **Afiliaciones**: `eps_id`, `pension_fund_id`, `severance_fund_id` (three Select components), `has_social_security` (Switch with label "Seguridad social activa").
    5. **Estado**: `active` (Switch with label "Conductor activo").
  - Each field uses `<InputError message={errors.fieldName} />` for inline validation feedback.
  - Required fields display the `*` marker convention (the project hasn't introduced a `<RequiredMarker />` yet — emit a small `<span className="text-destructive">*</span>` next to required labels).
  - Reference convention: `resources/js/components/vehicles/vehicle-form.tsx`.

- [x] **Task F3**: Create `resources/js/components/drivers/driver-create-dialog.tsx`.
  - Props: `{ open, onOpenChange, municipalities, documentTypes, eps, pensionFunds, severanceFunds }`.
  - `useForm` with the 17 default-empty fields; on success `reset()` + `onOpenChange(false)`.
  - `<Dialog><DialogContent>` with `<DriverForm idPrefix="dlg" {...} />` inside a scrollable content area.
  - Submit button "Guardar"; cancel via `<DialogClose />`.
  - Reference convention: `resources/js/components/vehicles/vehicle-create-dialog.tsx`.

- [x] **Task F4**: Create `resources/js/pages/drivers/columns.tsx`.
  - Seven `ColumnDef<Driver>` entries:
    1. `documento` (computed `id: 'documento'`) — header "Documento", cell renders `<Link to drivers.show(id).url>` with text `${documentType.code} ${identification_number}` in font-mono.
    2. `nombre` (computed `id: 'nombre'`) — header "Nombre", cell renders `${first_name} ${first_lastname}`.
    3. `license_category` — sortable header "Categoría", cell renders the enum value plain.
    4. `licencia` (computed `id: 'licencia'`) — header "Licencia", cell renders `<DriverLicensePill driver={row.original} />`.
    5. `seg_social` (computed `id: 'seg_social'`) — header "Seg. Social", cell renders Badge `default` "Sí" when `has_social_security === true`, else `outline` "No".
    6. `vinculacion` (computed `id: 'vinculacion'`) — header "Vinculación", cell renders Badge `default` "Activo" when `active === true`, else `outline` "Inactivo".
    7. `actions` — `<DataTableRowActions editUrl={drivers.edit(id).url} onDelete={...} />` wrapped in `<Can permission={Permission.DELETE_DRIVERS}>`.

- [x] **Task F5**: Rewrite `resources/js/pages/drivers/index.tsx`.
  - Replace the `<pre>` JSON dump with the services-pattern shape.
  - Define `driverFilters: FilterDefinition[]`:
    - `active` → "Estado" (true → "Activo", false → "Inactivo")
    - `license_category` → "Categoría" (C1, C2, C3)
    - `license_status` → "Documentos" (ok / expiring_soon / expired)
    - `has_social_security` → "Seguridad Social" (true → "Sí", false → "No")
  - Render `<MunicipalityCombobox />` above the table for the `municipality_id` filter (matches vehicles index).
  - Wire `<DriverCreateDialog />` to the "Crear Conductor" button via `useState`. After successful close, the modal's own `useForm.onSuccess` does `reset()` + `onOpenChange(false)` and Inertia's redirect refreshes the index automatically.
  - Apply `getRowClassName` row tinting using `driverLicenseStatus()`:
    - expired → `bg-destructive/10 hover:bg-destructive/15`
    - expiring_soon → `bg-amber-100/60 hover:bg-amber-100/80 dark:bg-amber-900/20 dark:hover:bg-amber-900/30`
    - ok → undefined
  - Type the page props as `{ drivers: PaginatedData<Driver>, municipalities: MunicipalityOption[], documentTypes: DocumentType[], eps: Eps[], pensionFunds: PensionFund[], severanceFunds: SeveranceFund[] }`.
  - Reference convention: `resources/js/pages/vehicles/index.tsx`.

- [x] **Task F6**: Rewrite `resources/js/pages/drivers/show.tsx`.
  - Five Card sections in this order:
    1. **Header card** — `<CardHeader>` with the full name (`first_name + second_name + first_lastname + second_lastname`, trimmed) as `<CardTitle>`, `${documentType.code} ${identification_number}` as `<CardDescription>`, and an active `<Badge>` aligned right (variant `default` → "Activo", `outline` → "Inactivo"). Edit button beside the badge.
    2. **Cuenta Vinculada subsection** — INSIDE the header card's `<CardContent>` (compact, 2 lines): if `driver.user`, show the user's email + a small `<User />` icon; if `driver.user === null`, show "Sin vínculo" in muted text.
    3. **Información Personal** — 2-column grid: Tipo de Documento, Identificación, Segundo Nombre, Segundo Apellido, Municipio (`municipality.name + ', ' + municipality.department.name`), Dirección, Teléfono, Email.
    4. **Licencia y Seguridad Social** — 2-column grid: Categoría, Vencimiento (es-CO formatted), `<DriverLicensePill />` for the visual state, Seguridad Social badge (Sí/No).
    5. **Afiliaciones** — 3-column grid: EPS (name + code), Fondo de Pensiones, Fondo de Cesantías.
    6. **Servicios Recientes** — `<Table>` with columns Fecha, Vehículo, Tercero, Estado. Empty state "Sin servicios registrados.". Each Fecha is a `<Link>` to `services.show(service.id).url`.
  - Type the page props as `{ driver: Driver & { ...relations }, recentServices: RecentServiceRow[] }`. Use the same Pick + & relations pattern that `vehicles/show.tsx` settled on.
  - Breadcrumbs: `[{ title: 'Conductores', href: drivers.index().url }, { title: full name, href: '#' }]`.
  - Reference convention: `resources/js/pages/vehicles/show.tsx`.

- [x] **Task F7**: Rewrite `resources/js/pages/drivers/create.tsx` and `resources/js/pages/drivers/edit.tsx` to be real form pages.
  - Both reuse `<DriverForm />` (no `idPrefix`).
  - `create.tsx` uses `useForm` with default-empty values, posts to `DriverController.store().url`, redirects to `drivers.index()` on success.
  - `edit.tsx` uses `useForm` initialized from the `driver` prop, puts to `DriverController.update(driver.id).url`.
  - Both pages wrap the form in a `<Card>` with a "Conductores › Crear" / "Conductores › Editar" breadcrumb.
  - Reference convention: `resources/js/pages/vehicles/create.tsx` and `vehicles/edit.tsx`.

### Tests

- [x] **Task T1 (Pest, backend)**: Add to `tests/Feature/Http/Controllers/DriverControllerTest.php`:
  - `test('index returns paginated payload')` — assert `drivers.data` is array, `drivers.per_page`, `drivers.current_page`, `drivers.total` exist.
  - `test('index passes catalog data for the create modal')` — assert `municipalities`, `documentTypes`, `eps`, `pensionFunds`, `severanceFunds` props are present.

- [x] **Task T2 (Pest, backend)**: Add filter tests to `DriverControllerTest`:
  - `test('index filters by license_status expired')` — seed 3 drivers (expired SOAT-equivalent license, expiring within 30 days, OK); assert only the expired one comes back.
  - `test('index filters by license_status expiring_soon')` — same fixture; assert only the within-30 driver.
  - `test('index filters by license_status ok')` — same fixture; assert only the OK driver.
  - `test('index filters by has_social_security')` — seed 2 drivers with mixed `has_social_security`; assert filter narrows correctly.
  - `test('index filters compose')` — combine `license_status=expired` with `has_social_security=false`; assert only the matching driver.

- [x] **Task T3 (Pest, backend)**: Add show tests to `DriverControllerTest`:
  - `test('show returns driver with relationships and recent services')` — seed a driver with municipality/department/documentType/eps/pensionFund/severanceFund/user + 7 services; assert `driver.municipality.department.name`, `driver.user.email`, and `recentServices` length 5 in DESC order.
  - `test('show returns empty recentServices when none exist')` — same setup without services; `recentServices` is an empty array.
  - `test('show renders user link when user_id is set')` — assert `driver.user` is present in the payload.
  - `test('show renders no user link when user_id is null')` — assert `driver.user` is null.

- [x] **Task T4 (Pest, backend)**: Add a dashboard test to `tests/Feature/DashboardTest.php`:
  - `test('driver alerts include license_status navigation link')` — seed an expired-license driver and an expiring-license driver; GET `/dashboard`; assert the expired alert has `link === '/drivers?filter[license_status]=expired'` and the expiring alert has `link === '/drivers?filter[license_status]=expiring_soon'`.

- [x] **Task T5 (Pest, backend)**: Update existing `DriverControllerTest` if any test assumes the index response shape was a flat array — `test('index behaves as expected')` may need a paginated-shape adjustment.

- [x] **Task T6 (Dusk, UI regression)**: Create `tests/Browser/DriversIndexAndShowTest.php` with four scenarios (consolidated single file, mirroring `VehiclesIndexAndShowTest.php`):
  1. **driversIndexRendersAndFiltersExpiredLicense** — admin loads `/drivers`, asserts table headers in Spanish (Documento / Nombre / Categoría / Licencia / Seg. Social / Vinculación / Acciones), no error banners; applies `license_status = Vencidas`, asserts tinted rows + filtered count.
  2. **dashboardCrossLinkToFilteredDrivers** — admin clicks an Alertas de Documentos row for a seeded expired-license driver; asserts the URL contains `filter` + `license_status` + `expired` and the matching driver row is visible.
  3. **showPageRendersFiveCards** — admin navigates to `/drivers/{id}` for a driver with a linked user; asserts each section heading renders (Información Personal / Licencia y Seguridad Social / Afiliaciones / Servicios Recientes) and the "Cuenta Vinculada" email is shown; takes screenshots at top of page and at scroll-to-Afiliaciones.
  4. **createModalOpensWithFiveSections** — admin clicks "Crear Conductor"; the dialog opens; asserts each of the five form section headings (Identificación / Datos de Contacto / Licencia / Afiliaciones / Estado) is visible. Field-typing left to the Pest feature test (Radix Select + MunicipalityCombobox are brittle to drive in Dusk).
  - Use `migrate:fresh --no-interaction` in `beforeEach` (NOT `--seed`) and build fixtures inline — same pattern as `VehiclesIndexAndShowTest`. The Dusk suite relies on the ContractSeeder defensive guard added in commit `d81e4f7`.

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

1. `mcp__playwright__browser_navigate http://localhost/login`, login as admin.
2. Navigate to `/drivers`. `mcp__playwright__browser_snapshot` — verify the table renders with the expected columns + at least one row tinted for an expired or expiring license.
3. Apply `Documentos = Vencidas` filter, snapshot again. Verify only tinted rows remain.
4. Click a Documento link. Snapshot the show page. Verify all five Card sections + the Cuenta Vinculada subsection.
5. Navigate to `/dashboard`. Click a driver alert row. Verify the URL changes to `/drivers?filter[license_status]=expired` and the matching driver is visible.
6. Click "Crear Conductor" on `/drivers`. Snapshot the modal. Verify all five form sections are present.
7. Logout. Login as operator. Repeat steps 2 + 6 — verify operator can browse and create.
8. Logout. Login as driver. Navigate to `/drivers` — verify a 403 page appears.
9. Use `mcp__laravel-boost__browser-logs` to inspect any JS console errors during the flow.

- [x] Scenario 1: Admin sees the rebuilt index, applies license_status filter, sees tinted rows.
- [x] Scenario 2: Admin clicks dashboard alert, lands on filtered drivers index.
- [x] Scenario 3: Admin opens the show page and verifies all five cards + Cuenta Vinculada.
- [x] Scenario 4: Operator can create + delete via the modal and the row-actions menu.
- [x] Scenario 5: Driver and Accounting users receive 403 on `/drivers`.

### 2. Backend regression — Pest feature tests (required)

Tasks T1–T5 above MUST be added to `tests/Feature/Http/Controllers/DriverControllerTest.php` and `tests/Feature/DashboardTest.php`. Run via `./vendor/bin/sail test --compact`. The full suite MUST stay green at 451+ tests passing.

### 3. UI regression — Laravel Dusk browser tests (required)

Task T6 above MUST be added under `tests/Browser/DriversIndexAndShowTest.php` (single consolidated file). Each test MUST:

- Assert no `[role="alert"]`, exception trace, or visible error UI.
- Assert key Spanish strings render with correct diacritics (Conductores, Identificación, Información Personal, Categoría, Vinculación, Cuenta Vinculada).
- Take screenshots at key interaction steps for visual review.
- Use `migrate:fresh --no-interaction` in `beforeEach` (not `--seed`) and build fixtures inline.

Run locally via `./vendor/bin/sail dusk --filter=DriversIndexAndShowTest`. CI does not run Dusk currently, but the suite MUST run cleanly locally before merge.

### 4. API endpoints (curl)

The `/drivers` routes are Inertia routes, not a public JSON API. Auth-gate verification only:

```bash
# Admin: should get a 200
curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@sgte.app","password":"password"}' \
  -c cookies-admin.txt

curl -s -o /dev/null -w "%{http_code}\n" \
  -H "Accept: text/html" \
  -b cookies-admin.txt \
  http://localhost/drivers
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
  http://localhost/drivers
# Expected: 403
```

## Dependencies

- **vehicles-crud** must be merged (it is — commit `7e66dc2`). This requirement reuses the `getRowClassName` hook, the row-tint convention, the dashboard `link` plumbing, and the modal-as-create-affordance pattern that landed there.
- **No new packages.**

## Notes

### Why this scope is bigger than vehicles-crud

vehicles-crud only had to rebuild index + show because `vehicles/create.tsx`, `vehicles/edit.tsx`, and `vehicle-form.tsx` were already real. Drivers has none of those — all four pages are Blueprint stubs and `driver-form.tsx` doesn't exist. Expected commit count is roughly **17** vs vehicles-crud's 12.

### Reusable building blocks introduced

After this requirement lands, the next Blueprint rebuild (Third-Parties or Contracts) inherits:

- The `parseDueDate` helper extracted to `resources/js/lib/parse-due-date.ts` (Task F1).
- Confidence that `<DataTable getRowClassName>` works for two distinct domain models (vehicles and drivers).
- Two reference implementations of "Documento + status + Estado" CRUD pages — Third-Parties is the natural next-easiest because it has no compliance-axis (no docs to expire), and Contracts can use the date-range pattern from Servicios.

### Drive-by cleanup retired

This requirement removes the `'link' => '/drivers'` placeholder TODO that vehicles-crud commit `ee453d7` left in `DashboardController::buildDocumentAlerts`. The dashboard's driver alerts will deep-link symmetrically with the vehicle alerts after this lands.

### Out of scope, deferred

- Editing `drivers.user_id` from the form (Q&A round 1 decision: read-only display only).
- A license-renewal history timeline.
- Document upload (license photo, ID copy) via `spatie/laravel-medialibrary`.
- Bulk operations (bulk activate/deactivate, bulk delete, CSV export).
- The other four Blueprint scaffolds (Third-Parties, Contracts, Invoices, Service-Incidents) — each will get its own requirement following this template.
