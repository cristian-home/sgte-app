---
name: third-parties-crud
type: feat
scope: third-parties
status: completed
priority: medium
created_date: 2026-04-14
completed_date: 2026-04-14
srs_refs: ["REQ-006"]
migration_strategy: new
---

# Rebuild the Terceros index and show pages, extract ThirdPartyForm

## Description

The `third-parties` module ships with a complete backend (`ThirdPartyController` resource methods, `ThirdPartyStoreRequest` / `ThirdPartyUpdateRequest`) and the create + edit pages are already real React forms. However, two of the four Inertia pages still ship as Blueprint-generated JSON-dump stubs:

- `resources/js/pages/third-parties/index.tsx` — `JSON.stringify(...)` inside a `<pre>` block.
- `resources/js/pages/third-parties/show.tsx` — JSON dump.

Additionally, `third-parties/create.tsx` and `third-parties/edit.tsx` **duplicate ~250 lines of identical form code**. There is no shared `<ThirdPartyForm />` component, which is technical debt parallel to what `<DriverForm />` solved for the drivers module.

This rebuild is the **third pilot** of the Blueprint scaffold rebuild and it serves a deliberate role: a **negative test for the abstractions** that vehicles-crud + drivers-crud established. Third parties have **no expiring-document axis** — no SOAT/RTM/license to flag, no compliance state machine, no row tinting. If the rebuild can re-use the `<DataTable>` + `useServerTable` + columns + modal + show-page conventions WITHOUT reaching for `<DocumentPills />`, `getRowClassName`, or any document-status helpers, then the abstractions don't accidentally couple "Blueprint rebuild" to "expiring document story". That's a useful confirmation before tackling Contracts, Invoices, and Service-Incidents.

This requirement also retires a cross-link TODO from the vehicles-crud rebuild: the `<Link href="/third-parties/{id}">Ver tercero</Link>` on `vehicles/show.tsx` (commit `1b58831`) currently lands on the Blueprint stub. After this rebuild, every "Ver tercero" cell in the app actually goes somewhere real.

**Out of scope:** bulk-edit dual-role flags; CSV export; per-third-party billing summary (deferred to invoices-crud); a tags or category system; rebuilding the remaining three Blueprint scaffolds (Contracts, Invoices, Service-Incidents) — each gets its own requirement.

## Acceptance Criteria

- [x] **AC1**: WHEN an admin or operator navigates to `/third-parties` THEN the page renders a paginated `<DataTable>` (not a JSON dump) with columns: **Documento**, **Nombre**, **Tipo**, **Roles**, **Municipio**, **Vinculación**, **Acciones**.
- [x] **AC2**: WHEN a third party has `is_natural_person = true` THEN the **Tipo** cell renders "Natural" AND the **Nombre** cell renders the trimmed full natural-person name (`first_name + first_lastname`).
- [x] **AC3**: WHEN a third party has `is_natural_person = false` THEN the **Tipo** cell renders "Jurídica" AND the **Nombre** cell renders `company_name`.
- [x] **AC4**: WHEN a third party has `is_customer = true` THEN the **Roles** cell shows a `[Cliente]` Badge; WHEN `is_provider = true` THEN it ALSO shows a `[Proveedor]` Badge. Both badges render side-by-side when both flags are true. When neither is set, the cell renders an em-dash `—`.
- [x] **AC5**: WHEN the user applies the **Estado** filter (Activo / Inactivo) THEN only rows whose `active` boolean matches remain.
- [x] **AC6**: WHEN the user applies the **Tipo persona** filter (Natural / Jurídica) THEN only rows whose `is_natural_person` matches remain.
- [x] **AC7**: WHEN the user applies the **Es cliente** filter (Sí / No) THEN only rows whose `is_customer` matches remain.
- [x] **AC8**: WHEN the user applies the **Es proveedor** filter (Sí / No) THEN only rows whose `is_provider` matches remain. Combining with **Es cliente** narrows via AND.
- [x] **AC9**: WHEN the user picks a municipality from the `<MunicipalityCombobox />` rendered above the table THEN only rows whose `municipality_id` matches remain.
- [x] **AC10**: WHEN the user clicks the **Crear Tercero** action on the index THEN a new `<ThirdPartyCreateDialog />` modal opens. The modal contains the new shared `<ThirdPartyForm />` component preserving the current flat-with-conditional layout (a single `is_natural_person` toggle swaps between the four name fields and `company_name + trade_name`). On successful submit the modal closes AND the index refreshes with the new row visible.
- [x] **AC11**: WHEN the user navigates to `/third-parties/create` directly THEN the standalone create page renders the same `<ThirdPartyForm />` (no `idPrefix`, no modal wrapper) with a Guardar / Cancelar action bar. Cancelar returns to `/third-parties`.
- [x] **AC12**: WHEN the user navigates to `/third-parties/{id}/edit` THEN the edit page renders `<ThirdPartyForm />` with the third party's current values pre-filled and an Actualizar / Cancelar action bar.
- [x] **AC13**: WHEN the user clicks the **Documento** link in any row THEN the app navigates to `/third-parties/{id}` AND the show page renders **three unconditional** Card sections in this order:
    1. **Header card** — full natural-person name OR `company_name` (with `trade_name` shown as a description when present), `documentType.code + identification_number` as the secondary label, an active/inactive Badge, and an Editar button.
    2. **Información General** — Tipo de Documento, Identificación, Tipo Persona (Natural / Jurídica), Cliente Badge (Sí/No variant), Proveedor Badge (Sí/No variant). Includes `trade_name` when the third party is a legal person and `trade_name` is non-empty.
    3. **Datos de Contacto** — Municipio (with department), Dirección, Teléfono, Correo Electrónico.
- [x] **AC14**: WHEN the third party has `is_provider = true` THEN a fourth **Vehículos del Tercero** Card renders with a small `<Table>` showing the last 5 vehicles where `vehicles.third_party_id = thirdParty.id`, ordered by `created_at` DESC, with columns **Placa** (Link to `/vehicles/{id}`), **Cód. Interno**, **Tipo**, **Estado**. Empty state "Sin vehículos asociados.".
- [x] **AC15**: WHEN the third party has `is_customer = true` THEN a fifth **Contratos** Card renders with a small `<Table>` showing the last 5 contracts where `contracts.third_party_id = thirdParty.id`, ordered by `start_date` DESC, with columns **Número** (Link to `/contracts/{id}`), **Objeto**, **Vigencia** (formatted `start_date → end_date` in `es-CO`), **Estado** (Activo/Inactivo from `active`). Empty state "Sin contratos registrados.".
- [x] **AC16**: WHEN the third party has `is_customer = false` AND `is_provider = false` THEN ONLY the three unconditional cards render (Header + Información General + Datos de Contacto). Neither Vehículos del Tercero nor Contratos appears in the DOM.
- [x] **AC17**: WHEN the user clicks "Ver tercero" from `vehicles/show.tsx` (the link in the Propietario card from commit `1b58831`) THEN the user lands on the rebuilt `/third-parties/{id}` show page (NOT the Blueprint stub).
- [x] **AC18**: WHEN a driver, accounting, or unauthenticated user navigates to `/third-parties` or `/third-parties/{id}` THEN they receive 401 (unauthenticated) or 403 (driver / accounting do NOT hold `VIEW_THIRD_PARTIES`).

## Technical Specification

### Data Model

**No new tables, no new columns.** Every field this requirement needs already exists:

```
third_parties (existing — no changes)
├── id (bigint, PK)
├── document_type_id (bigint, FK → document_types.id)
├── identification_number (varchar)
├── is_natural_person (boolean)
├── first_name, second_name (nullable), first_lastname, second_lastname (nullable)  -- natural person
├── company_name (nullable), trade_name (nullable)                                    -- legal person
├── municipality_id (bigint, FK → municipalities.id, nullable)
├── address, phone, email (varchar)
├── is_customer (boolean)
├── is_provider (boolean)
├── active (boolean)
└── created_at / updated_at / deleted_at (softDeletes)
```

### Enums

**No new enums.** No enum values are touched by this rebuild.

### Routes

**No new routes.** The existing `Route::resource('third-parties', ThirdPartyController::class)` already provides every endpoint:

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | `/third-parties` | `ThirdPartyController@index` | `auth, verified` | `third-parties.index` |
| GET | `/third-parties/create` | `ThirdPartyController@create` | `auth, verified` | `third-parties.create` |
| POST | `/third-parties` | `ThirdPartyController@store` | `auth, verified` | `third-parties.store` |
| GET | `/third-parties/{thirdParty}` | `ThirdPartyController@show` | `auth, verified` | `third-parties.show` |
| GET | `/third-parties/{thirdParty}/edit` | `ThirdPartyController@edit` | `auth, verified` | `third-parties.edit` |
| PUT | `/third-parties/{thirdParty}` | `ThirdPartyController@update` | `auth, verified` | `third-parties.update` |
| DELETE | `/third-parties/{thirdParty}` | `ThirdPartyController@destroy` | `auth, verified` | `third-parties.destroy` |

Authorization is enforced inside each controller action via `Gate::authorize(Permission::*->value)` (see ADR-005 §2).

### Permissions

**No new permissions.** `VIEW_THIRD_PARTIES`, `CREATE_THIRD_PARTIES`, `UPDATE_THIRD_PARTIES`, `DELETE_THIRD_PARTIES` already exist and are granted to **Admin** and **Operator** by the `seed_catalog_data` migration after Phase A2. Driver and Accounting roles MUST NOT hold these.

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Index | `resources/js/pages/third-parties/index.tsx` | **REWRITE.** `<DataTable>` + `useServerTable` + `<MunicipalityCombobox />` above the table + `<ThirdPartyCreateDialog />` modal. **No `getRowClassName` row tinting** — third parties have no compliance axis (deliberate negative test for the abstractions). |
| Show | `resources/js/pages/third-parties/show.tsx` | **REWRITE.** Three unconditional Card sections (Header + Información General + Datos de Contacto) plus up to two conditional cards (Vehículos del Tercero when `is_provider`, Contratos when `is_customer`). Page renders 3, 4, or 5 cards depending on role flags. |
| Create | `resources/js/pages/third-parties/create.tsx` | **REFACTOR.** Replace ~250 lines of inline form with `<ThirdPartyForm />`. Keep the `useForm` + Guardar/Cancelar action bar. |
| Edit | `resources/js/pages/third-parties/edit.tsx` | **REFACTOR.** Same as create — replace inline form with `<ThirdPartyForm />`. |
| Columns | `resources/js/pages/third-parties/columns.tsx` | **NEW.** TanStack `ColumnDef<ThirdPartyRow, unknown>[]`. |
| Form | `resources/js/components/third-parties/third-party-form.tsx` | **NEW.** Shared form component used by create page, edit page, and create-modal dialog. **Preserves the current flat-with-conditional layout** — a single `is_natural_person` toggle swaps between the 4 name fields and `company_name + trade_name`. |
| Modal | `resources/js/components/third-parties/third-party-create-dialog.tsx` | **NEW.** Modal wrapper around `<ThirdPartyForm idPrefix="dlg" />` mirroring `vehicle-create-dialog.tsx` and `driver-create-dialog.tsx`. |

## Migration Strategy

`new` (formal frontmatter value), but **no migration files are written or modified**. Every column, FK, enum, and permission this requirement needs already exists.

After implementing this requirement, no `php artisan migrate` invocation is required.

## Tasks

### Backend

- [x] **Task B1**: Paginate `ThirdPartyController@index` and enrich its payload.
  - Replace the trailing `->get()` with `->paginate($request->perPage())->withQueryString()` (per ADR-006 §7).
  - Add eager-loads: `'municipality:id,name,department_id'`, `'municipality.department:id,name'`, `'documentType:id,code,name'`.
  - Add `defaultSort('first_lastname')` (acceptable for legal persons because `first_lastname` is null and Postgres sorts nulls predictably; if test ordering becomes brittle, fall back to sorting by `id`).
  - Pass `municipalities` AND `documentTypes` to the page so the upcoming `<ThirdPartyCreateDialog />` modal has both option lists in a single trip.
  - Reference convention: `DriverController@index` after the drivers-crud rebuild.

- [x] **Task B2**: Expand `ThirdPartyController@show` to load relationships + recent vehicles + recent contracts.
  - Eager-load `municipality.department` and `documentType:id,code,name`.
  - Load `recentVehicles` as a separate query: last 5 `Vehicle` records where `third_party_id = $thirdParty->id`, ordered by `created_at` DESC, with columns `id, plate, internal_code, type, status` selected.
  - Load `recentContracts` as a separate query: last 5 `Contract` records where `third_party_id = $thirdParty->id`, ordered by `start_date` DESC, with columns `id, contract_number, contract_object, start_date, end_date, active` selected. The `contract_object` enum value should serialize to its string value via the model cast.
  - Pass them to the Inertia page as `thirdParty` (full model with relations), `recentVehicles` (array, possibly empty), and `recentContracts` (array, possibly empty).
  - The frontend gates whether to render the Vehículos / Contratos cards based on `thirdParty.is_provider` / `thirdParty.is_customer`. The backend always sends both arrays (empty when role is false), keeping the payload shape predictable for tests.

### Frontend

- [x] **Task F1**: Create `resources/js/components/third-parties/third-party-form.tsx`.
  - **Preserves the existing flat-with-conditional layout from create.tsx** (do NOT refactor to sectioned-with-headings).
  - Props: `{ data, setData, errors, documentTypes, municipalities, idPrefix? }`.
  - Field set in this order:
    1. `document_type_id` (Select) + `identification_number` (Input) in a 2-col grid.
    2. `is_natural_person` (Switch) with the dynamic label "Persona Natural" / "Persona Jurídica".
    3. **Conditional block**: when `is_natural_person === true` → 4 Inputs (`first_name`, `second_name`, `first_lastname`, `second_lastname`) in a 2-col grid; when false → 2 Inputs (`company_name`, `trade_name`).
    4. `municipality_id` (`<MunicipalityCombobox />`) + `address` (Input) + `phone` (Input) + `email` (Input) in a 2-col grid.
    5. Three Checkboxes in a flex row: `is_customer`, `is_provider`, `active`.
  - Required-field labels carry a small destructive-coloured asterisk via the same `<RequiredMarker />` convention used in `driver-form.tsx`. (Required fields are: `document_type_id`, `identification_number`, `address`, `phone`, `email`. The conditional name fields are also required *for their visible branch* — `first_name + first_lastname` for natural, `company_name` for legal — but this is enforced server-side; the visual marker should follow accordingly.)
  - Reference convention: read the current `resources/js/pages/third-parties/create.tsx` (lines 76–358) and lift its form body wholesale into the new component, parameterizing field ids with `idPrefix`.

- [x] **Task F2**: Refactor `resources/js/pages/third-parties/create.tsx` to use `<ThirdPartyForm />`.
  - Keep the `useForm` initialization with the same default-empty values.
  - Replace the entire `<form>` body (everything inside `<form onSubmit={submit} className="space-y-6">`) with `<ThirdPartyForm data={data} setData={setData} errors={errors} documentTypes={documentTypes} municipalities={municipalities} />` plus the existing Guardar / Cancelar action bar at the bottom.
  - Delete unused imports (the conditional rendering imports — `Switch`, `Checkbox`, `Select*`, etc. — are no longer needed in create.tsx).
  - Net effect: ~250 lines removed, ~10 lines added.

- [x] **Task F3**: Refactor `resources/js/pages/third-parties/edit.tsx` to use `<ThirdPartyForm />`.
  - Same edit pattern as F2: keep the `useForm` initialization (which reads from the `thirdParty` prop), replace the form body, drop unused imports.
  - May be bundled with F2 in a single commit since both files apply identical edits — left to the agent's judgment.

- [x] **Task F4**: Create `resources/js/components/third-parties/third-party-create-dialog.tsx`.
  - Modal wrapper mirroring `driver-create-dialog.tsx`. Owns its own `useForm` with all the default-empty fields. Submits to `ThirdPartyController.store()`. On success: `reset()` + `onOpenChange(false)`.
  - Wraps `<ThirdPartyForm idPrefix="dlg" {...} />` inside a `<DialogContent>` with the standard "max-h-[calc(100vh-4rem)] flex flex-col px-0 sm:max-w-3xl" sizing.
  - Submit button "Guardar"; cancel via `<DialogClose />`.

- [x] **Task F5**: Create `resources/js/pages/third-parties/columns.tsx`.
  - Seven `ColumnDef<ThirdPartyRow>` entries:
    1. `documento` (computed `id: 'documento'`) — header "Documento", cell renders `<Link to thirdParties.show(id).url>` with text `${documentType.code} ${identification_number}` in font-mono.
    2. `nombre` (computed `id: 'nombre'`, `accessorFn` for sorting) — header "Nombre" (sortable via `<DataTableColumnHeader />`), cell returns the natural-or-legal name (`is_natural_person ? first_name + first_lastname : company_name`).
    3. `tipo` (computed `id: 'tipo'`) — header "Tipo", cell renders "Natural" or "Jurídica".
    4. `roles` (computed `id: 'roles'`) — header "Roles", cell renders one or two `<Badge>` components side-by-side (`[Cliente]` when `is_customer`, `[Proveedor]` when `is_provider`). Em-dash `—` when neither flag is set.
    5. `municipio` (computed `id: 'municipio'`) — header "Municipio", cell renders `municipality?.name ?? '—'`.
    6. `vinculacion` (`accessorKey: 'active'`) — header "Vinculación", Badge: `default` "Activo" or `outline` "Inactivo".
    7. `actions` — `<DataTableRowActions editUrl={thirdParties.edit(id).url} onDelete={...} />` wrapped in `<Can permission={Permission.DELETE_THIRD_PARTIES}>`.
  - Use the same `Pick + & relations` pattern that vehicles/columns and drivers/columns settled on. Define a local `type ThirdPartyRow = ThirdParty & { document_type?: DocumentType | null; municipality?: Municipality | null }`.

- [x] **Task F6**: Rewrite `resources/js/pages/third-parties/index.tsx`.
  - Replace the `<pre>` JSON dump with the services/vehicles/drivers index pattern.
  - Define `thirdPartyFilters: FilterDefinition[]`:
    - `active` → "Estado" (1 → "Activo", 0 → "Inactivo")
    - `is_natural_person` → "Tipo persona" (1 → "Natural", 0 → "Jurídica")
    - `is_customer` → "Es cliente" (1 → "Sí", 0 → "No")
    - `is_provider` → "Es proveedor" (1 → "Sí", 0 → "No")
  - Render `<MunicipalityCombobox />` above the table for the `municipality_id` filter (matches vehicles + drivers convention).
  - Wire `<ThirdPartyCreateDialog />` to the "Crear Tercero" button via `useState`.
  - **Do NOT pass `getRowClassName`** — third parties have no compliance axis. This is the deliberate negative test for the abstraction.
  - Type the page props as `{ thirdParties: PaginatedData<ThirdPartyRow>, municipalities: MunicipalityOption[], documentTypes: DocumentTypeOption[] }`.
  - Reference convention: `resources/js/pages/drivers/index.tsx`.

- [x] **Task F7**: Rewrite `resources/js/pages/third-parties/show.tsx`.
  - **Three unconditional Card sections** in this order:
    1. **Header card** — header includes the full name (natural) OR `company_name`. When `trade_name` is non-empty, show it as the description below. `documentType.code + identification_number` in font-mono. Active Badge + Editar button.
    2. **Información General** — 2-col grid: Tipo de Documento (`code — name`), Identificación, Tipo Persona ("Natural" / "Jurídica"). Plus a dedicated row for Cliente / Proveedor badges (using `default` variant when the flag is true, `outline` when false). Plus `Trade Name` field when `is_natural_person === false` AND `trade_name` is non-empty.
    3. **Datos de Contacto** — 2-col grid: Municipio (with department), Dirección, Teléfono, Correo.
  - **Two conditional Cards** (rendered with a JSX guard, NOT with empty states):
    4. **Vehículos del Tercero** — only when `thirdParty.is_provider === true`. Contains a `<Table>` with the four columns. Empty state "Sin vehículos asociados." inside the table when `recentVehicles.length === 0`.
    5. **Contratos** — only when `thirdParty.is_customer === true`. Contains a `<Table>` with the four columns. Empty state "Sin contratos registrados." inside the table when `recentContracts.length === 0`.
  - Type the page props as `{ thirdParty: ShowThirdParty, recentVehicles: RecentVehicleRow[], recentContracts: RecentContractRow[] }` using the `Pick<T> & relations` pattern (matches vehicles/show.tsx + drivers/show.tsx).
  - Breadcrumbs: `[{ title: 'Terceros', href: thirdParties.index().url }, { title: nameOrCompany, href: '#' }]`.
  - Reference convention: `resources/js/pages/drivers/show.tsx`.

### Tests

- [x] **Task T1 (Pest, backend)**: Add to `tests/Feature/Http/Controllers/ThirdPartyControllerTest.php`:
  - `test('index returns paginated payload')` — assert `thirdParties.data` is array, `per_page`, `current_page`, `total` exist.
  - `test('index passes catalog data needed by the create modal')` — assert `municipalities` and `documentTypes` props are present.

- [x] **Task T2 (Pest, backend)**: Add filter tests:
  - `test('index filters by is_customer')` — seed 2 third parties with mixed flags; assert filter narrows correctly.
  - `test('index filters by is_provider')` — same shape.
  - `test('index filters compose is_customer AND is_provider')` — seed 4 third parties (cliente-only, proveedor-only, both, neither); apply both filters set to "Sí"; assert only the "both" row remains.
  - `test('index filters by is_natural_person')` — seed 2; assert filter narrows.

- [x] **Task T3 (Pest, backend)**: Add show tests:
  - `test('show returns recent vehicles when is_provider is true')` — seed a provider third party with 7 vehicles; assert `recentVehicles` length is 5 ordered by created_at DESC.
  - `test('show returns empty recent vehicles when is_provider is false')` — assert `recentVehicles` is an empty array.
  - `test('show returns recent contracts when is_customer is true')` — seed a customer third party with 7 contracts; assert `recentContracts` length is 5 ordered by start_date DESC.
  - `test('show returns empty recent contracts when is_customer is false')` — assert `recentContracts` is an empty array.

- [x] **Task T4 (Dusk, UI regression)**: Create `tests/Browser/ThirdPartiesIndexAndShowTest.php` with four scenarios in a single consolidated file (mirroring `VehiclesIndexAndShowTest.php` and `DriversIndexAndShowTest.php`):

  1. **`third parties index renders the table with Spanish headers and combines role filters`** — admin loads `/third-parties`, asserts table headers (Documento, Nombre, Tipo, Roles, Municipio, Vinculación), no error banners; applies `is_customer = Sí` AND `is_provider = Sí`, asserts only the "both" row remains.

  2. **`show page customer-only path renders Contratos but not Vehículos`** — seed a third party with `is_customer = true, is_provider = false`; navigate to `/third-parties/{id}`; assert the "Contratos" heading is visible AND the "Vehículos del Tercero" heading is NOT (`assertDontSee`).

  3. **`show page provider-only path renders Vehículos but not Contratos`** — seed a third party with `is_provider = true, is_customer = false`; navigate to `/third-parties/{id}`; assert the "Vehículos del Tercero" heading is visible AND the "Contratos" heading is NOT.

  4. **`vehicle show 'Ver tercero' link lands on the rebuilt third-party show page`** — seed a third-party-owned vehicle, login as admin, visit `/vehicles/{id}`, click the "Ver tercero" link, assert the URL matches `/third-parties/{id}` AND the third party's name is visible AND the page does NOT contain "auto-generated by Blueprint" anywhere. **This pins the cross-link regression we explicitly want to fix.**

  - Use `migrate:fresh --no-interaction` in `beforeEach` (not `--seed`) and build fixtures inline — same pattern as the previous Dusk suites.

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
2. Navigate to `/third-parties`. `mcp__playwright__browser_snapshot` — verify the table renders with the expected columns. **Verify NO row tinting** (third parties have no compliance axis — this is the negative test).
3. Apply `Es cliente = Sí` AND `Es proveedor = Sí`. Snapshot. Verify only "both" rows remain.
4. Click a Documento link. Snapshot the show page. Verify the conditional cards render based on the third party's role flags.
5. Click "Crear Tercero" on `/third-parties`. Snapshot the modal. Verify the natural-vs-legal toggle swaps the visible name fields.
6. From `/vehicles/{id}` for a third-party-owned vehicle, click "Ver tercero". Verify the URL changes to `/third-parties/{id}` and the page is the rebuilt show page (NOT a Blueprint stub).
7. Logout. Login as driver. Navigate to `/third-parties` — verify a 403 page appears.
8. Use `mcp__laravel-boost__browser-logs` to inspect any JS console errors during the flow.

- [x] Scenario 1: Admin sees the rebuilt index, applies the role-combination filter.
- [x] Scenario 2: Admin opens the show page for a customer-only third party — only Contratos card renders.
- [x] Scenario 3: Admin opens the show page for a provider-only third party — only Vehículos card renders.
- [x] Scenario 4: Admin opens the show page for a both-roles third party — both conditional cards render.
- [x] Scenario 5: "Ver tercero" cross-link from `vehicles/show.tsx` lands on the rebuilt show page.
- [x] Scenario 6: Driver receives 403 on `/third-parties`.

### 2. Backend regression — Pest feature tests (required)

Tasks T1–T3 above MUST be added to `tests/Feature/Http/Controllers/ThirdPartyControllerTest.php`. Run via `./vendor/bin/sail test --compact`. The full suite MUST stay green at 463+ tests passing (the current baseline after drivers-crud merged).

### 3. UI regression — Laravel Dusk browser tests (required)

Task T4 above MUST be added under `tests/Browser/ThirdPartiesIndexAndShowTest.php` (single consolidated file). Each test MUST:

- Assert no `[role="alert"]`, exception trace, or visible error UI.
- Assert key Spanish strings render with correct diacritics (Terceros, Información General, Datos de Contacto, Vehículos del Tercero, Vinculación).
- Take screenshots at key interaction steps for visual review.
- Use `migrate:fresh --no-interaction` in `beforeEach` (not `--seed`) and build fixtures inline.

Run locally via `./vendor/bin/sail dusk --filter=ThirdPartiesIndexAndShowTest`. CI does not run Dusk currently, but the suite MUST run cleanly locally before merge.

### 4. API endpoints (curl)

The `/third-parties` routes are Inertia routes, not a public JSON API. Auth-gate verification only:

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
  http://localhost/third-parties
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
  http://localhost/third-parties
# Expected: 403
```

## Dependencies

- **vehicles-crud** must be merged (it is — commit `7e66dc2`). This requirement reuses the `getRowClassName` hook (only to NOT pass it — confirms the prop is truly optional), the `Pick<T> & relations` show-page typing pattern, the modal-as-create-affordance pattern, and the four-layer verification template.
- **drivers-crud** must be merged (it is — commit `76c9fe7`). Reuses the form-extraction pattern (driver-form → third-party-form) and the conditional-rendering decisions captured in its requirement doc.
- **No new packages.**

## Notes

### Why this is the "negative test" pilot

vehicles-crud and drivers-crud both rebuild entities with an expiring-document axis. They use:

- `<DocumentPills />` / `<DriverLicensePill />` for status visualization
- `getRowClassName` for row tinting based on document state
- A `docs_status` / `license_status` filter on the controller
- A `parseDueDate`-based shared helper module
- Cross-links from the dashboard's Alertas de Documentos panel

If you accidentally couple the index/show rebuild conventions to those primitives, then every Blueprint rebuild hereafter requires an "expiring document" story even when the entity has none. Third parties don't. **If this rebuild lands clean WITHOUT importing any of those primitives**, it confirms the abstractions are properly orthogonal.

The deliberate negative-test markers in this requirement:

- The index page MUST NOT pass `getRowClassName` to `<DataTable>`.
- The columns file MUST NOT import `DocumentPills` / `LicensePill` / any document-status helper.
- The controller MUST NOT add a `*_status` filter or any reference to `LICENSE_EXPIRY_WINDOW_DAYS` / `DOCS_EXPIRY_WINDOW_DAYS`.
- The dashboard `buildDocumentAlerts` is not touched (no third-party alerts exist; nothing to deep-link).

If any of those slips in during implementation, that's a signal that the abstraction is leaking and the requirement needs renegotiation.

### Conditional show-page sections

This is the first show page in the app with a **variable card count** (3, 4, or 5 depending on `is_provider` / `is_customer`). The vehicles-crud and drivers-crud parallels always rendered exactly 5 cards. The conditional rendering convention introduced here will likely be reused by the Service-Incidents rebuild (incidents may or may not have `affects_billing` follow-on, may or may not have a linked invoice, etc.).

### Reusable building blocks introduced

After this requirement lands, the next Blueprint rebuild (Contracts, Invoices, or Service-Incidents) inherits:

- `<ThirdPartyForm />` itself — Contracts + Invoices both link to a third party and may want to embed a "Crear tercero" inline action that opens `<ThirdPartyCreateDialog />`.
- The conditional-card show-page convention for entities with optional sub-resources.
- A confirmed-orthogonal `<DataTable>` API that doesn't bake in document-status assumptions.

### Drive-by retired

This requirement removes the cross-link regression that vehicles-crud commit `1b58831` left in place: the `<Link href="/third-parties/{id}">Ver tercero</Link>` in the Propietario card on `vehicles/show.tsx` now lands on the rebuilt show page.

### Out of scope, deferred

- Bulk operations (bulk activate/deactivate, bulk role-flag toggle, bulk delete, CSV export).
- A per-third-party billing summary or open-balance card (deferred to invoices-crud / contracts-crud).
- Tags, categories, or custom-field metadata.
- An "embedded create" affordance from contracts/invoices forms (worth considering when those rebuilds happen).
- Rebuilding the remaining three Blueprint scaffolds (Contracts, Invoices, Service-Incidents) — each will get its own requirement following this template.

### Estimated commit count

About **12–14 commits**:

- 1 doc commit (this requirement file).
- 2 backend commits (B1 paginate/eager-load + T1+T2 tests; B2 show payload + T3 tests).
- 6 frontend commits (F1 third-party-form extraction; F2+F3 refactor create+edit bundled; F4 modal; F5 columns; F6 index rebuild; F7 show rebuild).
- 1 Dusk test commit (T4).
- 1 polish commit (Prettier + any TS fixes).
- 1 final docs commit (mark requirement completed).

Closer to vehicles-crud (12) than drivers-crud (13), reflecting the narrower index-and-show scope plus the form-extraction overhead.
