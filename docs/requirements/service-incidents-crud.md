---
name: service-incidents-crud
type: feat
scope: service-incidents
status: pending
priority: medium
created_date: 2026-04-17
completed_date:
srs_refs: ["REQ-008", "REQ-011"]
migration_strategy: new
---

# Align the Novedades module with the merged CRUD rebuild conventions

## Description

The `service-incidents` module ships with a full backend (`ServiceIncidentController` resource methods, `ServiceIncidentStoreRequest` / `ServiceIncidentUpdateRequest`, the `IncidentSeverity` enum, the `BillingIncidentNotification` mail/database notification, driver-aware `redirectAfterMutation` logic) AND partially-built Inertia pages. Unlike the five previous rebuilds — each of which started from pure Blueprint JSON-dump stubs — the four `service-incidents/*.tsx` pages are already ~150 lines each with real components. They just **lag the conventions** established by vehicles-crud / drivers-crud / third-parties-crud / contracts-crud / invoices-crud: no pagination, no faceted filters wired through `<DataTable filters={...}>`, no pill or row tint, no five-card show-page, no `useServerTable`, no shared `Pick<T> & relations` typing pattern.

Scope: **re-align** index + show + create + edit to match the conventions. Keep every load-bearing server-side behavior intact:

- `BillingIncidentNotification` fires in `store()` when `affects_billing = true` (notifying super-admin + admin + accounting).
- `redirectAfterMutation()` sends drivers back to `/driver` instead of `/services/{id}` because drivers lack `VIEW_SERVICES`.
- `store()` automatically sets `registrar_id`, `reported_at`, and `is_driver_report` server-side (the request payload should NOT carry these).
- `?service_id=X` on `/service-incidents/create` preselects the parent service (driver-portal + services/show entry points).

**This is the sixth and last of the Blueprint scaffold rebuilds.** After this requirement lands, the `project_blueprint_scaffolds_deferred` memory entry should be retired and the pre-existing deferred-Blueprint TypeScript error bucket should be empty.

Unlike previous rebuilds, incidents are a **child resource** of Service (every incident belongs to a service, FK `service_id` is required). That changes three structural decisions:

1. **No modal-on-index create affordance.** A `<ServiceIncidentCreateDialog />` wouldn't make sense in a standalone-index context (which service would it apply to?). The "Crear Novedad" button on `/service-incidents` navigates to `/service-incidents/create` where the user picks a service via a new `<ServiceCombobox />` primitive. When `?service_id=X` is present (driver-portal + services/show paths), the form skips the picker entirely and shows the pre-selected service in a read-only summary block with a muted "Preseleccionado desde el servicio" note.
2. **The show page's third card is a Servicio card** (not an Observaciones card). It's the child's parent-ref surface, with a prominent "Ver servicio" link, secondary "Ver vehículo" / "Ver contrato" links, and the customer computed-name.
3. **The cross-link goes both ways.** Previous rebuilds only wired the forward direction ("the module's show page links out to related modules"). This one reverses it: `resources/js/pages/services/show.tsx` gains a new **Novedades** card listing the last 5 incidents for that service, with its own "+ Registrar Novedad" button that navigates to `/service-incidents/create?service_id={service.id}`.

Four primitives are introduced or touched:

- **New: `<IncidentSeverityPill />`** (`resources/js/components/incidents/incident-severity-pill.tsx`). Reads `incident.incident_type.severity` (3-value `IncidentSeverity` enum), renders a Badge: `informational → outline "Informativo"`, `minor → secondary "Menor"`, `major → destructive "Mayor!"`. Exports `incidentSeverityRowTint(incident)` helper for the index row tint (Major → destructive/10, Minor → amber, Informational → none).
- **New: `<ServiceCombobox />`** (`resources/js/components/services/service-combobox.tsx`). Parallel to `<ThirdPartyCombobox />`. Searchable combobox for picking a service; each option shows `service_date + vehicle.plate + contract.contract_number + driver.first_name driver.first_lastname`. Options come from the controller (last 60 days).
- **Touched but NOT extended: `resources/js/lib/document-status.ts`**. Severity is a discrete enum axis, not a date-derived axis — the pill lives alongside its own feature folder (parallel to `<PaymentStatusPill />` in the invoices rebuild). No `document-status.ts` edits.
- **Touched: `columns.tsx` uses the `usePermissions` hook pattern** established by invoices-crud to gate Edit and Delete actions separately, so accounting (which has VIEW_INCIDENTS but not UPDATE/DELETE) sees no Acciones entries.

Two validation **tightenings** accompany the Inertia alignment:

- `ServiceIncidentStoreRequest` currently only checks `CREATE_INCIDENTS` at the Gate layer, which means a driver could submit an incident with ANY `service_id` by guessing the integer — not just their own services. Add a rule: when the authenticated user is a driver AND NOT super-admin, the `service_id` MUST belong to a service whose `driver_id` matches the driver record linked to that user.
- `additional_value` currently allows negatives (`between:-9999999999.99,9999999999.99`). Tighten to `nullable, numeric, min:0, max:9999999999.99` — it's a surcharge on the base service price, not a refund; refunds/credits would be a separate domain concept.

**Out of scope:** bulk close; CSV/PDF export; an incident-resolution workflow (status = open/resolved/dismissed); linking an incident back to an invoice (the Impacto en Facturación card is read-only); rebuilding `services/show.tsx` beyond adding the Novedades card (its own future requirement); the `<ServiceCombobox />` adopting a date-range extension for > 60 day windows (add later if needed). No new Blueprint scaffolds remain.

## Acceptance Criteria

- [ ] **AC1**: WHEN an admin, operator, or accounting user navigates to `/service-incidents` THEN the page renders a paginated `<DataTable>` (not the current `->get()` payload) with columns **Servicio**, **Tipo**, **Descripción**, **Reporte**, **Registrado Por**, **Impacto**, **Acciones**.
- [ ] **AC2**: WHEN a row renders THEN the **Servicio** cell shows the vehicle plate (font-mono) AND the service date (es-CO format), both wrapped in a single `<Link>` to `/services/{service_id}`.
- [ ] **AC3**: WHEN a row renders THEN the **Tipo** cell shows the `incident_type.name` side-by-side with an `<IncidentSeverityPill />` reading the severity from `incident_type.severity`.
- [ ] **AC4**: WHEN a row renders THEN the **Descripción** cell shows the first line of `description` truncated to ~200px via `truncate` + a title tooltip containing the full text.
- [ ] **AC5**: WHEN a row renders THEN the **Reporte** cell shows `reported_at` formatted via `Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' })` (the existing helper handles both epoch-seconds and ISO strings).
- [ ] **AC6**: WHEN a row renders THEN the **Registrado Por** cell shows `registrar.name`; WHEN `is_driver_report === true` THEN it ALSO shows a `[Conductor]` Badge adjacent to the name.
- [ ] **AC7**: WHEN a row renders THEN the **Impacto** cell shows a destructive `[Afecta facturación]` Badge when `affects_billing === true`, otherwise an em-dash `—`.
- [ ] **AC8**: WHEN a row's severity is `major` THEN the row is tinted `bg-destructive/10 hover:bg-destructive/15`; WHEN `minor` THEN `bg-amber-100/60 hover:bg-amber-100/80 dark:bg-amber-900/20 dark:hover:bg-amber-900/30`; WHEN `informational` THEN no tint. Implemented via `getRowClassName={(row) => incidentSeverityRowTint(row.original)}` on `<DataTable>`.
- [ ] **AC9**: WHEN the user applies the **Tipo** faceted filter with an incident-type id THEN only rows matching that `incident_type_id` remain. Backed by `AllowedFilter::exact('incident_type_id')`.
- [ ] **AC10**: WHEN the user applies the **Severidad** faceted filter with value `informational`, `minor`, or `major` THEN only rows whose `incident_type.severity` matches remain. Backed by a new `AllowedFilter::callback('severity', ...)` on `ServiceIncidentController@index` that performs a `whereHas('incidentType', fn ($q) => $q->where('severity', $value))`.
- [ ] **AC11**: WHEN the user applies **Reporte del conductor** (Sí/No) OR **Afecta facturación** (Sí/No) filters THEN only rows matching the boolean remain. Existing `AllowedFilter::exact` rules.
- [ ] **AC12**: WHEN the user clicks the **Crear Novedad** action on `/service-incidents` THEN the app navigates to `/service-incidents/create` (NOT a modal). The create page renders `<ServiceCombobox />` as the first form field AND the form submits successfully once a service is chosen.
- [ ] **AC13**: WHEN the user navigates to `/service-incidents/create?service_id=X` (driver-portal + services/show entry points) THEN the create page skips the `<ServiceCombobox />` AND instead renders a read-only summary block showing the preselected service's date + vehicle plate + contract number + driver name, AND a muted "Preseleccionado desde el servicio" note beneath it.
- [ ] **AC14**: WHEN the user selects an `incident_type_id` whose `incident_type.affects_billing_default === true` THEN the `affects_billing` Switch auto-toggles to `true` (preserves existing behavior).
- [ ] **AC15**: WHEN a driver submits `POST /service-incidents` with a `service_id` belonging to a service that is NOT assigned to that driver's Driver record THEN the request fails with a 422 validation error on `service_id` with message "Solo puede registrar novedades en sus propios servicios." Super-admin bypasses this rule via `Gate::before`.
- [ ] **AC16**: WHEN the user submits a non-null `additional_value` that is negative THEN the request fails with 422 on `additional_value`. WHEN `additional_value` is null OR `>= 0` THEN the request is accepted (assuming other rules pass).
- [ ] **AC17**: WHEN the user clicks the incident number/link in any row THEN the app navigates to `/service-incidents/{id}` AND the show page renders **five** Card sections in this order:
    1. **Header card** — `incident_type.name` (title), `[Conductor]` Badge when `is_driver_report`, `<IncidentSeverityPill />`, Editar button.
    2. **Descripción** — `description` rendered with `whitespace-pre-wrap`, or "Sin descripción." when empty.
    3. **Servicio** — service_date (formatted), vehicle plate (font-mono), contract number, customer computed-name; a prominent "Ver servicio" `<Link>` to `/services/{id}` on the right; a secondary row with "Ver vehículo" and "Ver contrato" links.
    4. **Registrado** — `registrar.name`, `reported_at` (formatted), `is_driver_report` Badge when true.
    5. **Impacto en Facturación** — when `affects_billing === false`: "Sin impacto en facturación." muted text. When `affects_billing === true`: an `[Afecta facturación]` destructive Badge AND a currency-formatted hero showing `additional_value` with `text-xl font-bold tabular-nums` AND the `Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })` formatter.
- [ ] **AC18**: WHEN an admin, operator, or accounting user navigates to `/services/{id}` THEN a new **Novedades** card renders listing the last 5 incidents where `service_id = $service->id`, columns **Fecha** (Link to `/service-incidents/{id}`), **Tipo**, **Severidad** (`<IncidentSeverityPill />`), **Registrador**. The card includes a `+ Registrar Novedad` button that navigates to `/service-incidents/create?service_id={service.id}`. Empty state: "Sin novedades registradas.".
- [ ] **AC19**: WHEN a driver logs in, visits `/driver`, clicks "Registrar Novedad" on an assigned service, submits the form THEN the response redirects to `/driver` (NOT `/services/{id}`) — `redirectAfterMutation()` honors the driver-lacks-VIEW_SERVICES constraint. The incident appears in the database with `is_driver_report = true` and `registrar_id = auth()->id()` (server-autoset).
- [ ] **AC20**: WHEN an accounting user renders `/service-incidents` THEN the **Acciones** column shows NO Editar / Eliminar entries (accounting holds VIEW_INCIDENTS but neither UPDATE_INCIDENTS nor DELETE_INCIDENTS). Gated via the `usePermissions` hook pattern established by invoices-crud.
- [ ] **AC21**: WHEN an operator creates an incident with `affects_billing = true` THEN the `BillingIncidentNotification` is dispatched to all super-admin + admin + accounting users. Preserved from the current controller.
- [ ] **AC22**: WHEN a driver (not super-admin) OR unauthenticated user navigates to `/service-incidents` THEN they receive 401 (unauth) or 403 (driver lacks VIEW_INCIDENTS — verify; if the current seeder grants driver VIEW_INCIDENTS, they CAN see the index but it should be filtered to their own incidents; pin the observed behavior in Pest).
- [ ] **AC23**: WHEN `npm run types` runs THEN the service-incidents pages contribute zero new errors — the four pages move OUT of the pre-existing deferred-Blueprint TypeScript error bucket.
- [ ] **AC24**: AFTER this requirement lands THEN the `project_blueprint_scaffolds_deferred` memory entry is retired (all 6 Blueprint scaffolds are production-shaped).

## Technical Specification

### Data Model

**No new tables, no new columns.** Every field is already present.

```
service_incidents (existing)
├── id (bigint, PK)
├── service_id (bigint, FK → services.id)
├── incident_type_id (bigint, FK → incident_types.id)
├── description (text)
├── registrar_id (bigint, FK → users.id, server-autoset)
├── is_driver_report (boolean, server-autoset)
├── reported_at (timestamp, server-autoset)
├── affects_billing (boolean)
├── additional_value (decimal(12,2), nullable)
└── created_at / updated_at
```

`incident_types.severity` is the `IncidentSeverity` enum column read by the new pill. No change.

### Enums

**No new enums.** `IncidentSeverity` (`informational` / `minor` / `major`) already exists with a `label()` helper.

### Routes

**No new routes.** `Route::resource('service-incidents', ServiceIncidentController::class)` already provides every endpoint.

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | `/service-incidents` | `ServiceIncidentController@index` | `auth, verified` | `service-incidents.index` |
| GET | `/service-incidents/create` | `ServiceIncidentController@create` | `auth, verified` | `service-incidents.create` |
| POST | `/service-incidents` | `ServiceIncidentController@store` | `auth, verified` | `service-incidents.store` |
| GET | `/service-incidents/{serviceIncident}` | `ServiceIncidentController@show` | `auth, verified` | `service-incidents.show` |
| GET | `/service-incidents/{serviceIncident}/edit` | `ServiceIncidentController@edit` | `auth, verified` | `service-incidents.edit` |
| PUT | `/service-incidents/{serviceIncident}` | `ServiceIncidentController@update` | `auth, verified` | `service-incidents.update` |
| DELETE | `/service-incidents/{serviceIncident}` | `ServiceIncidentController@destroy` | `auth, verified` | `service-incidents.destroy` |

Authorization remains at the `Gate::authorize(Permission::*->value)` call at the top of each action.

### Permissions

**No new permissions.** Existing grants:

| Role | Has | Notes |
|---|---|---|
| Admin | VIEW / CREATE / UPDATE / DELETE_INCIDENTS | Full CRUD |
| Operator | VIEW / CREATE / UPDATE / DELETE_INCIDENTS | Full CRUD |
| Accounting | VIEW_INCIDENTS | View-only (billing awareness) |
| Driver | VIEW_INCIDENTS + CREATE_INCIDENTS | Scoped to their own services via the new FormRequest rule |

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Index | `resources/js/pages/service-incidents/index.tsx` | **REWRITE.** `<DataTable>` + `useServerTable`; 4 faceted filters (`incident_type_id`, `severity`, `is_driver_report`, `affects_billing`); severity row tinting. No above-the-table combobox. |
| Show | `resources/js/pages/service-incidents/show.tsx` | **REWRITE.** Five Card sections (Header / Descripción / Servicio / Registrado / Impacto en Facturación). |
| Create | `resources/js/pages/service-incidents/create.tsx` | **REWRITE.** Conditional `<ServiceCombobox />` when no `service` prop; read-only summary when prop provided. Auto-sets `affects_billing` from `incident_type.affects_billing_default`. |
| Edit | `resources/js/pages/service-incidents/edit.tsx` | **REWRITE.** Service is read-only (service-transfer is out of scope). Edits `incident_type_id`, `description`, `affects_billing`, `additional_value`. |
| Columns | `resources/js/pages/service-incidents/columns.tsx` | **EXTRACT + REWRITE.** Currently defined inline in index.tsx — extract to its own file (matches invoices/columns.tsx pattern). Uses `usePermissions` hook for Edit/Delete gating. |
| Pill | `resources/js/components/incidents/incident-severity-pill.tsx` | **NEW.** Single Badge + `incidentSeverityRowTint` helper. |
| Service picker | `resources/js/components/services/service-combobox.tsx` | **NEW.** Reusable combobox primitive. |
| Services show | `resources/js/pages/services/show.tsx` | **EXTEND.** Add a Novedades card listing the last 5 incidents + "+ Registrar Novedad" button. |
| Services controller | `app/Http/Controllers/ServiceController.php` | **EXTEND.** `@show` gains `recentIncidents` payload (last 5 with `incidentType` + `registrar` eager-loaded). |

## Migration Strategy

`new` (formal frontmatter value), but **no migration files are written or modified**. Every column, FK, and enum is already in place.

## Tasks

### Backend

- [ ] **Task B1**: Rewrite `ServiceIncidentController@index` to paginate + add severity filter.
  - Replace `->get()` with `->paginate($request->perPage())->withQueryString()`.
  - Tighten eager-loads: `service:id,service_date,vehicle_id,contract_id,driver_id`, `service.vehicle:id,plate`, `service.contract:id,contract_number`, `incidentType:id,code,name,severity`, `registrar:id,name`.
  - Add `AllowedFilter::callback('severity', function (Builder $query, $value) { $first = is_array($value) ? ($value[0] ?? '') : explode(',', (string) $value)[0]; $query->whereHas('incidentType', fn ($q) => $q->where('severity', $first)); })`.
  - `allowedSorts(['reported_at', 'service_id', 'incident_type_id'])` with `defaultSort('-reported_at')`.
  - Preserve the existing `incident_type_id` / `is_driver_report` / `affects_billing` / `service_id` exact filters.
  - Pass `incidentTypes` to the page (id + name + severity + affects_billing_default) for the faceted filter UI.

- [ ] **Task B2**: Expand `ServiceIncidentController@show` + `@edit` eager loads.
  - `@show`: load `service.vehicle:id,plate`, `service.contract:id,contract_number`, `service.contract.thirdParty:id,is_natural_person,first_name,first_lastname,company_name`, `incidentType:id,code,name,severity`, `registrar:id,name`.
  - `@edit`: same loads as show (the form renders the read-only parent summary).

- [ ] **Task B3**: Extend `ServiceIncidentController@create` to pass services for the new combobox.
  - When `?service_id=X` is absent, pass a `services` payload: last 60 days of services, eager-loaded with `vehicle:id,plate`, `contract:id,contract_number`, `driver:id,first_name,first_lastname`, ordered by `service_date` DESC.
  - When `?service_id=X` is present, preserve the current behavior (pass the `service` prop, don't pass `services`).
  - Move the service query into a private `recentServiceOptions()` method so `@edit` can optionally reuse it (currently `@edit` doesn't need it because editing a service transfer is out of scope, but keeping the helper available matches the `customerOptions()` pattern from contracts/invoices).

- [ ] **Task B4**: Harden `ServiceIncidentStoreRequest` + tighten `additional_value`.
  - Add a `rules()` clause: when the authenticated user has the driver role AND NOT super-admin, validate that `service_id` belongs to a service whose `driver_id` matches the driver record linked to that user. Use a closure rule or a dedicated custom rule class `ServiceBelongsToAuthenticatedDriver` (recommended — keeps the rule testable in isolation).
  - Change `additional_value` rule from `['nullable', 'numeric', 'between:-9999999999.99,9999999999.99']` to `['nullable', 'numeric', 'min:0', 'max:9999999999.99']`.
  - Add `messages()`: `['service_id.custom_rule_name' => 'Solo puede registrar novedades en sus propios servicios.', 'additional_value.min' => 'El valor adicional no puede ser negativo.']`.
  - Apply the same tightening to `ServiceIncidentUpdateRequest`.

- [ ] **Task B5**: Extend `ServiceController@show` with `recentIncidents` + services-show Novedades card payload.
  - Load `$recentIncidents = ServiceIncident::where('service_id', $service->id)->with(['incidentType:id,name,severity', 'registrar:id,name'])->orderByDesc('reported_at')->limit(5)->get(['id', 'service_id', 'incident_type_id', 'registrar_id', 'reported_at', 'is_driver_report', 'affects_billing'])`.
  - Pass to the Inertia page as `recentIncidents`.
  - Preserve all other props the page already receives.

### Frontend — shared primitives

- [ ] **Task F1**: Create `resources/js/components/incidents/incident-severity-pill.tsx`.
  - Default-export `<IncidentSeverityPill />` component; named-export `incidentSeverityRowTint(incident)` helper.
  - Props: `{ severity?: string | null, className?: string }` OR `{ incident: { incident_type?: { severity?: string | null } | null } }` — pick whichever reads cleaner at the call-sites (recommend taking `severity: string | null` directly to avoid deeply-coupled input shapes).
  - Label + variant table: `informational → outline "Informativo"`, `minor → secondary "Menor"`, `major → destructive "Mayor!"`, unknown → outline with the raw value.
  - Tooltip map: `{ informational: 'Incidente informativo', minor: 'Incidente menor', major: 'Incidente mayor — requiere atención' }`.
  - `incidentSeverityRowTint` returns: `major → 'bg-destructive/10 hover:bg-destructive/15'`, `minor → 'bg-amber-100/60 hover:bg-amber-100/80 dark:bg-amber-900/20 dark:hover:bg-amber-900/30'`, others → `undefined`.

- [ ] **Task F2**: Create `resources/js/components/services/service-combobox.tsx`.
  - Reference convention: `resources/js/components/third-parties/third-party-combobox.tsx`.
  - Props: `{ services: ServiceOption[]; value: string | number | null; onChange: (value: string) => void; forceInclude?: ServiceOption[]; placeholder?: string; searchPlaceholder?: string; disabled?: boolean; invalid?: boolean; id?: string; className?: string }`.
  - Define and export `type ServiceOption = Pick<Service, 'id' | 'service_date'> & { vehicle?: { id: number; plate: string } | null; contract?: { id: number; contract_number: string } | null; driver?: { id: number; first_name: string; first_lastname: string } | null }`.
  - Option render: primary line shows `${service_date} — ${vehicle.plate ?? '—'}`; secondary muted line shows `${contract.contract_number ?? '—'} · ${driver.first_name ?? ''} ${driver.first_lastname ?? ''}`.
  - Command search matches against `vehicle.plate`, `contract.contract_number`, `driver.first_name`, `driver.first_lastname`.
  - Empty state "Sin servicios recientes.".
  - `forceInclude` mirrors ThirdPartyCombobox — dedupe by id, append.

### Frontend — service-incidents-specific

- [ ] **Task F3**: Create `resources/js/pages/service-incidents/columns.tsx`.
  - Seven column defs: `servicio` (computed, Link to `/services/{id}`), `tipo` (incident_type.name + severity pill), `descripcion` (truncated), `reported_at` (formatted), `registrador` (name + Conductor badge), `impacto` (Afecta facturación badge or em-dash), `actions` (usePermissions-gated Edit + Delete).
  - Define a local `type ServiceIncidentRow = ServiceIncident & { service?: Service & { vehicle?: Vehicle | null; contract?: Contract | null } | null; incident_type?: IncidentType | null; registrar?: { id: number; name: string } | null }` using `Pick<T> & relations`.
  - Actions column: follow the `InvoiceRowActions` pattern from `resources/js/pages/invoices/columns.tsx` — a small component that reads `usePermissions().can()` for `UPDATE_INCIDENTS` and `DELETE_INCIDENTS` separately.

- [ ] **Task F4**: Rewrite `resources/js/pages/service-incidents/index.tsx`.
  - Replace the `useReactTable` + inline columns with `useServerTable<ServiceIncidentRow>({ data: paginatedServiceIncidents, columns })`.
  - Define `incidentFilters: FilterDefinition[]` with `incident_type_id`, `severity`, `is_driver_report`, `affects_billing` (options per AC9-11).
  - No above-the-table filter.
  - `getRowClassName={(row) => incidentSeverityRowTint(row.original.incident_type?.severity ?? null)}` (or equivalent — match the pill's input contract).
  - Wire the "Crear Novedad" button to `router.visit(serviceIncidents.create().url)` (NOT a modal).
  - Type props as `{ serviceIncidents: PaginatedData<ServiceIncidentRow>, incidentTypes: IncidentTypeOption[] }`.

- [ ] **Task F5**: Rewrite `resources/js/pages/service-incidents/show.tsx`.
  - Five Card sections in the order listed in AC17.
  - Type props as `{ serviceIncident: ShowServiceIncident }` where `ShowServiceIncident` uses `Pick<T> & relations` (match contracts/invoices show-page pattern).
  - Impacto card shows the `additional_value` hero with `text-xl font-bold tabular-nums` + `Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })`.
  - Reference convention: `resources/js/pages/invoices/show.tsx`.
  - Breadcrumbs: `[{ title: 'Novedades', href: serviceIncidents.index().url }, { title: incidentType.name ?? 'Novedad', href: '#' }]`.

- [ ] **Task F6**: Rewrite `resources/js/pages/service-incidents/create.tsx`.
  - Extract the form body into a new `resources/js/components/incidents/service-incident-form.tsx` component (parallel to `invoice-form.tsx`) so edit.tsx can reuse it.
  - Form props: `{ data, setData, errors, incidentTypes, services?, preselectedService?, idPrefix? }`.
  - When `preselectedService` is provided: render the read-only summary block + muted "Preseleccionado desde el servicio" note; hide the `<ServiceCombobox />`.
  - When `preselectedService` is null: render the `<ServiceCombobox services={services} ... />` as the first field.
  - `incident_type_id` Select auto-sets `affects_billing = incident_type.affects_billing_default` on change (preserve existing behavior).
  - `description` textarea (native with shadcn-style classes, matches contracts/invoices pattern).
  - `affects_billing` Switch.
  - `additional_value` Input with "$" prefix, `type="number"`, `step="0.01"`, `min="0"` (preserve parity with the invoices total_value input).
  - Type page props as `{ incidentTypes: IncidentTypeOption[], service?: Service | null, services?: ServiceOption[] }`.

- [ ] **Task F7**: Rewrite `resources/js/pages/service-incidents/edit.tsx`.
  - Render the same `<ServiceIncidentForm>` component with `preselectedService={serviceIncident.service}` (always, because service-transfer is out of scope — the picker stays hidden on edit).
  - Pre-fill `useForm` with the existing values; coerce `additional_value` decimal string back to a plain string for the input.
  - Breadcrumbs: `[Novedades, incident.incidentType.name, 'Editar']`.

- [ ] **Task F8**: Add the Novedades card to `resources/js/pages/services/show.tsx`.
  - Define a local `RecentIncidentRow` type.
  - Render a new `<Card>` after the existing primary cards with heading "Novedades" + description "Últimas 5 novedades registradas".
  - Inline `<Table>` with columns Fecha (Link to show), Tipo, Severidad (pill), Registrador.
  - Empty state: "Sin novedades registradas." muted paragraph.
  - `+ Registrar Novedad` Button gated by `<Can permission={Permission.CREATE_INCIDENTS}>`, navigating to `serviceIncidents.create.url({ query: { service_id: service.id } })` (verify Wayfinder's query builder surface; otherwise build the URL inline as `/service-incidents/create?service_id={service.id}`).

### Tests

- [ ] **Task T1 (Pest, backend — index pagination + severity filter)**: Add to `tests/Feature/Http/Controllers/ServiceIncidentControllerTest.php`:
  - `test('index returns paginated payload with relations')` — seed 3 incidents; assert `serviceIncidents.data` is array with pagination keys + each row has `service.vehicle`, `incident_type`, `registrar`.
  - `test('index filters by severity via the new callback filter')` — seed 3 incident types (one per severity) + 3 incidents (one each); apply `filter[severity]=major`; assert only the major row remains.
  - `test('index filters by incident_type_id / is_driver_report / affects_billing exact')` — preserved regression coverage.
  - `test('index defaults to -reported_at sort')` — seed 3 incidents with offset `reported_at`; assert the latest is first.
  - `test('index passes incidentTypes prop for the faceted filter')` — assert the prop is present and contains id/name/severity/affects_billing_default.

- [ ] **Task T2 (Pest, backend — show eager loads)**:
  - `test('show loads service.vehicle + service.contract.thirdParty + incidentType + registrar')` — assert all four relations are populated in the Inertia payload.

- [ ] **Task T3 (Pest, backend — store hardening)**:
  - `test('store accepts a driver submission when the service is assigned to them')` — seed a driver + service + factory incident via POST; assert 302 redirect to `/driver` + DB row created with `is_driver_report = true`.
  - `test('store rejects a driver submission for another drivers service with 422 on service_id')` — seed two drivers + one service for driver A + attempt to POST as driver B; assert 422 with `service_id` error.
  - `test('store super-admin bypasses the driver-scope rule')` — super-admin can post with any service_id.
  - `test('store rejects negative additional_value')` — POST with `additional_value = -100`; assert 422.
  - `test('store accepts null additional_value')` — POST without the key; assert 302.
  - `test('store accepts additional_value = 0')` — assert 302 (inclusive lower bound).

- [ ] **Task T4 (Pest, backend — BillingIncidentNotification regression)**:
  - `test('store dispatches BillingIncidentNotification to super-admin + admin + accounting when affects_billing is true')` — `Notification::fake()`, seed one of each role, POST with `affects_billing=true`, assert `Notification::assertSentTo($user, BillingIncidentNotification::class)` for each role.
  - `test('store does not dispatch when affects_billing is false')` — assert `Notification::assertNothingSent()`.

- [ ] **Task T5 (Pest, backend — redirectAfterMutation)**:
  - `test('store redirects driver to /driver regardless of service_id')` — pin the existing driver-aware branch.
  - `test('store redirects admin to /services/{id}')` — pin the default branch.

- [ ] **Task T6 (Pest, backend — services show Novedades payload)**:
  - `test('services show returns recentIncidents ordered by reported_at desc limited to 5')` — seed 7 incidents on one service; visit `services.show`; assert `recentIncidents` has 5 rows with the latest `reported_at` first.
  - `test('services show returns empty recentIncidents when none exist')` — assert empty array.

- [ ] **Task T7 (Pest, backend — authorization 403s)**:
  - `test('accounting can view /service-incidents')` — 200.
  - `test('accounting cannot update or delete incidents')` — 403 on PUT + DELETE.
  - `test('operator has full CRUD')` — 200 on each.
  - `test('unauthenticated user receives 401 on all routes')`.

- [ ] **Task T8 (Dusk, UI regression)**: Create `tests/Browser/ServiceIncidentsIndexAndShowTest.php` with four scenarios in a single consolidated file:

  1. **`service-incidents index renders with Spanish headers, severity filter, and row tint`** — super-admin loads `/service-incidents`, asserts headers (Servicio, Tipo, Descripción, Reporte, Registrado Por, Impacto); seeds a mix of severities; applies `filter[severity]=major`; verifies the destructive row tint is present and informational rows don't render.

  2. **`service-incidents show page renders the five cards and the affects_billing hero`** — seed a billing-affecting incident, navigate to `/service-incidents/{id}`, assert all five Card headings (Descripción, Servicio, Registrado, Impacto en Facturación), assert the big `additional_value` COP figure is rendered, assert the "Ver servicio" link is present.

  3. **`driver logs an incident from /driver and lands back on /driver after submit`** — login as `driver@sgte.app`, visit `/driver`, click "Registrar Novedad" on an assigned service, assert the create page renders with the service pre-selected AND the `<ServiceCombobox />` is NOT present, fill type + description, submit, assert the final URL is `/driver` (not `/services/{id}`), assert the success flash message is visible.

  4. **`accounting user sees rows but no Acciones menu entries`** — login as `accounting@sgte.app`, visit `/service-incidents`, assert rows are visible, assert the page source does NOT contain "Editar" / "Eliminar" in the row actions column (use `assertSourceMissing` like invoices-crud).

  - Use `migrate:fresh --no-interaction` in `beforeEach` and build fixtures inline.
  - Take screenshots at key interaction steps.

## Verification

### 1. Interactive verification — Playwright MCP

Reference users (all password `password`, except super admin which reads `SUPER_ADMIN_USER` / `SUPER_ADMIN_PASSWORD`):

| Role | Email |
|---|---|
| Admin | `admin@sgte.app` |
| Operator | `operator@sgte.app` |
| Driver | `driver@sgte.app` |
| Accounting | `accounting@sgte.app` |

Preferred flow:

1. Login as admin; navigate to `/service-incidents`. Snapshot; verify headers + row tint + severity pills.
2. Apply `Severidad = Mayor` filter; verify only major rows remain (tinted destructive).
3. Click an incident row → show page. Verify all five cards. For a billing-affecting incident, verify the COP hero.
4. Click "Ver servicio" in card 3; verify the cross-link lands on `/services/{id}`.
5. On the services/show page, verify the new Novedades card renders with the expected rows + "+ Registrar Novedad" button. Click it; verify the create page pre-selects the service AND the combobox is hidden.
6. Logout; login as driver. Visit `/driver`; click "Registrar Novedad" on a service; verify the create page. Submit; verify redirect back to `/driver`.
7. Logout; login as accounting. Navigate to `/service-incidents`. Verify rows visible; open a row-actions menu; verify no Editar/Eliminar entries.
8. `mcp__laravel-boost__browser-logs` for JS console errors.

- [ ] Scenario 1: Admin index + severity filter + row tint
- [ ] Scenario 2: Show page renders five cards + billing hero
- [ ] Scenario 3: Cross-link "Ver servicio" → services/show
- [ ] Scenario 4: Novedades card on services/show + deep-link
- [ ] Scenario 5: Driver flow /driver → Registrar Novedad → /driver
- [ ] Scenario 6: Accounting sees rows with no Acciones entries

### 2. Backend regression — Pest feature tests (required)

Tasks T1–T7 MUST be added to `tests/Feature/Http/Controllers/ServiceIncidentControllerTest.php` + `tests/Feature/Http/Controllers/ServiceControllerTest.php` (for the new `recentIncidents` on services/show). Run via `./vendor/bin/sail test --compact`. The full suite MUST stay green at **514+** tests passing (baseline after invoices-crud).

### 3. UI regression — Laravel Dusk browser tests (required)

Task T8 MUST be added under `tests/Browser/ServiceIncidentsIndexAndShowTest.php`. Each test MUST:

- Assert no `[role="alert"]`, exception trace, or visible error UI.
- Assert key Spanish strings render with correct diacritics (Novedades, Servicio, Descripción, Registrado Por, Impacto, Impacto en Facturación, Mayor, Menor, Informativo, Afecta facturación, Preseleccionado desde el servicio).
- Take screenshots at key interaction steps.

Run locally via `./vendor/bin/sail dusk --filter=ServiceIncidentsIndexAndShowTest`.

### 4. API endpoints (curl)

The `/service-incidents` routes are Inertia routes, not a public JSON API. Auth-gate verification only (admin 200, accounting 200, operator 200, driver 200 to index + 403 on UPDATE/DELETE, unauth 401).

## Dependencies

- **vehicles-crud** merged (`7e66dc2`).
- **drivers-crud** merged (`76c9fe7`).
- **third-parties-crud** merged (`4a44b20`).
- **contracts-crud** merged (`e41196b`).
- **invoices-crud** merged (`46fba03`) — direct prerequisite for the `usePermissions`-in-columns pattern + row-tint convention + Pick+&relations typing.
- **No new packages.**

## Notes

### Why `<IncidentSeverityPill />` lives in `components/incidents/` and not in `lib/document-status.ts`

Severity is a discrete enum axis (Informativo / Menor / Mayor) — NOT a date-derived state like vehicles' SOAT expiry or contracts' four-state temporal model. `document-status.ts` is scoped to date-derived state machines; extending it with a passthrough enum mapper would dilute the module's meaning without any code-reuse benefit. The pill component ships alongside its feature folder instead (same rationale as `<PaymentStatusPill />` from invoices-crud).

### Why the driver-scope check lives in the FormRequest, not a Gate

The check is data-dependent (it reads `services.driver_id` and compares to the authenticated user's driver record), which isn't a good fit for a permission-based Gate. FormRequest rules run after authorize() but before validation completes, so the 422 response carries the Spanish error message right to the field — cleaner UX than a 403 interstitial. The custom rule class `ServiceBelongsToAuthenticatedDriver` keeps the logic unit-testable in isolation and matches the established pattern for data-aware guards (e.g. `NoScheduleConflict` in ServiceStoreRequest).

### Why hardening `ServiceIncidentStoreRequest` matters even with the driver portal locking navigation

Navigation controls visibility, not authorization. A driver who guesses another driver's service id can POST directly to `/service-incidents` with that `service_id` and — as the code stands today — have the incident accepted. Closing this requires a validation-layer rule, which this requirement ships alongside the visible rewrite.

### Out of scope

- Incident-resolution workflow (status = open/resolved/dismissed).
- Incident → Invoice linkage (the Impacto en Facturación card is read-only; a future billing-workflow requirement will wire the assignment).
- `<ServiceCombobox />` growing a `dateRange` prop for custom windows — start with 60 days.
- Bulk close / bulk dismiss.
- PDF export.
- Rebuilding `services/show.tsx` beyond adding the Novedades card (own future requirement).

### Estimated commit count

About **14–16 commits**:

- 1 doc commit (this requirement file).
- 2 backend commits (B1 paginate + filters + T1 tests; B2+B3+B4 show/edit loads + store hardening + T2+T3 tests).
- 1 backend commit (B5 services/show `recentIncidents` + T6 tests).
- 1 backend commit (T4+T5 BillingIncidentNotification + redirectAfterMutation regressions).
- 1 frontend commit (F1 IncidentSeverityPill).
- 1 frontend commit (F2 ServiceCombobox).
- 1 frontend commit (F3 columns.tsx extraction with usePermissions gating).
- 1 frontend commit (F4 index rewrite with server table + row tint).
- 1 frontend commit (F5 show rewrite with five cards).
- 1 frontend commit (F6 create rewrite + service-incident-form extraction).
- 1 frontend commit (F7 edit rewrite).
- 1 frontend commit (F8 services/show Novedades card).
- 1 Dusk test commit (T8).
- 1 polish commit (Prettier + any TS fixes + T7 authorization tests if not bundled).
- 1 final docs commit (mark requirement completed + update the `project_blueprint_scaffolds_deferred` memory entry).

Slightly higher than invoices-crud (14) because of the two new shared primitives (IncidentSeverityPill + ServiceCombobox) AND the cross-axis touch on `services/show.tsx` + `ServiceController@show`.
