---
name: audit-log-enhancements
type: feat
scope: audit-log
status: pending
priority: high
created_date: 2026-04-18
completed_date:
srs_refs: ["REQ-009"]
migration_strategy: new
---

# Complete REQ-009 — filterable audit log, justification surfacing, and the executed-day warning

## Description

The backend enforcement half of REQ-009 (Accounting Immutability Control) is already shipped: `ServiceUpdateRequest` blocks operators on executed-day services, scopes accounting to billing fields only, and requires `justification` (min:10, max:500) from admins; `ServiceController@update` extracts the justification and writes an `activity()` entry with `properties => ['justification' => ..., 'edited_on_executed_day' => true]` + causer. The service edit form (`resources/js/components/services/service-form.tsx`) renders a conditional "Justificación del cambio *" textarea via `{isAdminEdit && ...}`. `ServiceLockingTest.php` pins the 403/422 paths.

What is **missing** is the audit-surface half — the piece that actually lets a compliance reviewer find, filter, and read those justifications. The current `/audit-log` Inertia page is a plain table with a hard-coded 50-row cap, zero filter UI, no pagination, and — most importantly — the `AuditLogController@index` `map()` closure omits the `properties` bag entirely, which means the justification written by `ServiceController@update` is **invisible** to the auditor viewing the page. SRS §REQ-009 AC#4 explicitly mandates that the justification be part of the audit trail reviewable by administrators.

This requirement closes REQ-009 by rebuilding `/audit-log` around the `<DataTable>` + `useServerTable` pattern used by the six rebuilt CRUD modules, adding four filters (subject_type, causer_id, event, date range), extracting a new shared `<UserCombobox />` primitive, and projecting `properties` / `attributes` / `old_attributes` on every row so a "Ver detalles" side sheet can render the full diff. Rows where `properties.edited_on_executed_day === true` are tinted amber (consistent with `por_vencer` on vehicles/drivers/contracts) and the justification column renders prominently. A destructive-variant `<Alert>` banner is added above the justification textarea on the service edit form so admins understand upfront that the edit will be audited.

Three design decisions were made explicitly during Q&A:

1. **Details UX**: a shadcn `<Sheet>` sliding from the right — keeps row context visible and handles the 15+ fields a Service edit can touch far better than an inline expansion or a centered modal.
2. **Row tint**: `bg-amber-500/10` on executed-day edits — consistent with the `por_vencer` palette on other indexes; the Justificación column + the explicit badge already do the "this is unusual" signalling, so a separate destructive color would add noise.
3. **Subject-type filter options**: backend queries distinct `subject_type` from the last 1000 activity rows and maps each class string to a human label via a `SUBJECT_TYPE_LABELS` constant. This avoids showing an "EPS" option when no EPS rows have ever been edited while still adapting to whatever models end up logging activity.
4. **Warning banner**: shadcn `<Alert variant="destructive">` with `AlertTriangle` — idiomatic, matches the compliance gravity, avoids a one-off yellow Card.

After this requirement lands, `REQ-009` is complete and Phase 4 (Billing and Audit) can flip to ✅ Completed.

**Out of scope:**

- **DayStatus-edit justification**. SRS REQ-009 is scoped to Service records modified *during* an executed day; it does not mandate a justification for the DayStatus state transition itself (Ejecutado → Proyectado or vice-versa). The `executor_id` + timestamp already captured by `DayStatusUpdateRequest` is the current audit trail for that transition.
- **Invoice-after-payment edit guard**. SRS REQ-009 does not explicitly cover paid invoices. Invoice CRUD + the `TotalValueLockedWhenServicesAttached` rule + the existing role gate are the current guardrails; adding a justification requirement on paid-invoice edits is a separate compliance decision the user has not yet made.
- **Accounting-role justification on executed-service edits**. SRS AC#2 mandates justification only for Administrator edits. Accounting is already scoped to billing fields only (via `ServiceUpdateRequest::rules()`) and does not need a justification — the field-scoping *is* the immutability guarantee for that role.
- **Search over `properties.justification` free text**. Filter by causer / subject / event / date range is enough to narrow the set; full-text on justifications is a nice-to-have that can be a later requirement.
- **Export (CSV / PDF) of the audit log**. Deferred — compliance exports are a separate workflow.

## Acceptance Criteria

- [ ] **AC1**: WHEN an admin navigates to `/audit-log` THEN the page renders a paginated `<DataTable>` (not a 50-row capped list), using `useServerTable` with the same per-page / sort / filter contract as the six rebuilt CRUD modules.
- [ ] **AC2**: WHEN `AuditLogController@index` runs THEN the response projects, for every row: `id`, `log_name`, `description`, `event`, `subject_type` (basename), `subject_id`, `causer` (id/name/email or null), `created_at` (ISO 8601), **AND** the full `properties` bag, `attributes`, and `old_attributes` — the current implementation drops these and MUST be rewritten to include them.
- [ ] **AC3**: WHEN the user applies `filter[subject_type]=App\\Models\\Service` THEN only activity rows whose `subject_type` matches remain. The filter is `AllowedFilter::exact('subject_type')` (current filter is already present — verify it survives the rewrite).
- [ ] **AC4**: WHEN the user applies `filter[causer_id]={userId}` THEN only rows authored by that user remain. The filter is `AllowedFilter::exact('causer_id')`.
- [ ] **AC5**: WHEN the user applies `filter[event]=updated` (or `created` / `deleted` / `restored`) THEN only rows matching that event remain. The filter is `AllowedFilter::exact('event')`.
- [ ] **AC6**: WHEN the user applies `filter[created_from]=2026-04-01` THEN only rows with `created_at >= 2026-04-01 00:00:00` remain. WHEN the user applies `filter[created_to]=2026-04-15` THEN only rows with `created_at <= 2026-04-15 23:59:59` remain. Both filters are `AllowedFilter::callback` instances that accept `YYYY-MM-DD` strings and apply `whereDate('created_at', '>=', …)` / `whereDate('created_at', '<=', …)` respectively. Both filters MUST be safe to combine.
- [ ] **AC7**: WHEN `AuditLogController@index` runs THEN the payload ALSO includes `users: User::orderBy('name')->get(['id', 'name', 'email'])` (no role filter — any user can be a causer) and `subjectTypes: [{ value, label }, ...]` — distinct `subject_type` values from the last 1000 `activity_log` rows, each mapped to its human label via a new `SUBJECT_TYPE_LABELS` constant defined on `AuditLogController`. The list is sorted by label ascending.
- [ ] **AC8**: WHEN the page renders THEN the columns, in order, are: **Fecha** (dateTimeFormatter `es-CO`, font-mono text-xs), **Usuario** (causer name primary + email muted, or `—` when null), **Acción** (event Badge, outline variant), **Entidad** (Spanish label + `#{subject_id}`; a `<Link>` to `/services/{id}` / `/invoices/{id}` / `/contracts/{id}` / `/service-incidents/{id}` / `/day-statuses/{id}` when `subject_type` maps to a linkable module, otherwise plain text), **Descripción** (truncate `max-w-md`), **Justificación** (renders `properties.justification` inline with `truncate max-w-sm`, or `—` when absent), **Acciones** (single "Ver detalles" icon button, `Eye` icon from lucide-react).
- [ ] **AC9**: WHEN a row has `properties.edited_on_executed_day === true` THEN the row is tinted with `bg-amber-500/10 hover:bg-amber-500/15` via `getRowClassName` passed to `<DataTable>`. Every other row gets no tint.
- [ ] **AC10**: WHEN the user clicks the "Ver detalles" icon on any row THEN a shadcn `<Sheet side="right">` opens showing:
    1. A header with the causer name + email + `{event}` Badge + formatted timestamp.
    2. The activity `description` rendered in a muted block.
    3. A **Justificación** card rendering `properties.justification` in a blockquote style — only when present. Includes an amber "Día ejecutado" Badge when `properties.edited_on_executed_day === true`.
    4. A 2-column **Cambios** card rendering `old_attributes` / `attributes` as "Antes" / "Después" with per-key rows; each key gets one line per column; unchanged keys are omitted (compute intersection of the two objects; render only the keys present in at least one of them).
    5. A collapsible **Propiedades adicionales** `<details>` block rendering the rest of `properties` (everything except `justification` and `edited_on_executed_day`) as formatted JSON (`JSON.stringify(..., null, 2)` in a `<pre>`).
    6. A "Cerrar" `<SheetClose>` button at the bottom.
- [ ] **AC11**: WHEN the above-the-table filter bar renders THEN it contains, in order: **Usuario** (`<UserCombobox />` filtered against the payload's `users` list, wired to `causer_id`), **Entidad** (`<Select>` with the payload's `subjectTypes` options, wired to `subject_type`), **Acción** (`<Select>` with fixed options `created / updated / deleted / restored`, wired to `event`), **Desde** (`<Input type="date">` wired to `created_from`), **Hasta** (`<Input type="date">` wired to `created_to`). All filters wire through the existing `useServerTable` filter channel.
- [ ] **AC12**: WHEN the user edits a Service whose day is in `DayStatusEnum::Executed` state AND they are an Admin THEN a shadcn `<Alert variant="destructive">` renders **above** the existing "Justificación del cambio *" textarea in `service-form.tsx`, containing an `<AlertTriangle>` icon, title "Día ejecutado", and description "Este servicio pertenece a un día ejecutado. La modificación requiere justificación obligatoria y quedará registrada en la auditoría." The textarea's existing behavior and validation are unchanged.
- [ ] **AC13**: WHEN an operator, driver, accounting, or unauthenticated user navigates to `/audit-log` THEN they receive 401 (unauthenticated) or 403 (operator / driver / accounting do NOT hold `VIEW_AUDIT_LOG`).
- [ ] **AC14**: WHEN an admin edits an executed-day Service with a valid justification AND the controller persists the record THEN exactly **one** new `activity_log` row exists afterwards where `causer_id === admin.id`, `subject_type === 'App\\Models\\Service'`, `subject_id === $service->id`, `properties.justification` equals the submitted string, AND `properties.edited_on_executed_day === true`. This AC is load-bearing: REQ-009 AC#4 requires the audit trail to record the justification, and this pin ensures the existing `ServiceController@update` logic is not silently regressed by any refactor inside this requirement.
- [ ] **AC15**: WHEN `npm run types` runs THEN the new pages and components contribute zero new TypeScript errors. The existing deferred-scaffold pre-existing errors (kibo-ui Gantt + unrebuilt catalog/fuec/vehicle-location scaffold pages) are NOT acceptable as a floor for new files added by this requirement.
- [ ] **AC16**: WHEN the `<UserCombobox />` primitive is reused by any other screen (planned: invoices filter bar, service-incidents filter bar) THEN the component MUST import cleanly from `@/components/users/user-combobox` with the signature `{ users, value, onChange, placeholder?, disabled?, invalid?, id?, className? }` — no audit-log-specific assumptions baked into the primitive.
- [ ] **AC17**: WHEN this requirement is merged THEN `docs/phases/phase-4-billing-reports.md` §4.3 MUST be updated to check off the REQ-009 justification UX item AND `docs/phases/README.md` Phase 4 row MUST flip from 🔶 In progress to ✅ Completed. (Status text update is part of the final docs commit in this requirement.)

## Technical Specification

### Data Model

**No new tables, no new columns, no migrations.** `spatie/laravel-activitylog`'s `activity_log` table already stores `properties` as JSON and is populated correctly by the existing `ServiceController@update`.

### Enums

**No new enums.** `Permission::VIEW_AUDIT_LOG` already exists and is granted to Admin only by `seed_catalog_data`.

### Routes

**No new routes.** `Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index')` already exists in `routes/web.php` line 53 and is gated via `Gate::authorize(Permission::VIEW_AUDIT_LOG->value)` inside the controller action (ADR-005 §2).

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | `/audit-log` | `AuditLogController@index` | `auth, verified` (+ in-action Gate) | `audit-log.index` |

### Permissions

**No new permissions.** `VIEW_AUDIT_LOG` is already defined and seeded to Admin.

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Audit Log Index | `resources/js/pages/audit-log/index.tsx` | **REWRITE.** `<DataTable>` + `useServerTable` + above-the-table filter bar (`<UserCombobox />` + 2 Selects + 2 date Inputs) + "Ver detalles" icon button per row that opens `<AuditLogDetailSheet />`. Passes `getRowClassName` for executed-day amber tint. |
| Detail Sheet | `resources/js/components/audit-log/audit-log-detail-sheet.tsx` | **NEW.** Shadcn `<Sheet side="right">` rendering the full activity payload: causer header, description, Justificación card, Cambios (antes/después) diff, collapsible raw-JSON properties. Props: `{ open, onOpenChange, activity }`. |
| User Combobox | `resources/js/components/users/user-combobox.tsx` | **NEW reusable primitive.** Parallel to `<ThirdPartyCombobox />`. Filters `users` by name + email (case-insensitive). Props: `{ users, value, onChange, placeholder?, disabled?, invalid?, id?, className? }`. Will be reused by the invoices and service-incidents filter bars in future work. |
| Service Form Warning | `resources/js/components/services/service-form.tsx` | **EXTEND.** Add a destructive-variant shadcn `<Alert>` banner above the existing justification textarea block (the existing `{isAdminEdit && ...}` wrapper), inside the same conditional. |
| Subject-type Labels | `app/Http/Controllers/AuditLogController.php` (new constant) | **NEW.** `private const SUBJECT_TYPE_LABELS = [App\Models\Service::class => 'Servicio', App\Models\Invoice::class => 'Factura', ...]`. |

## Migration Strategy

`new` (formal frontmatter value), but **no migration files are written or modified**. Every column, FK, permission, and route this requirement needs already exists. After implementing this requirement, no `php artisan migrate` invocation is required.

## Tasks

### Backend

- [ ] **Task B1**: Rewrite `AuditLogController@index` to paginate, project the full activity shape, and add the date-range + combobox payloads.
  - Replace the current `->limit(...)->get()->map(...)` chain with `QueryBuilder::for(Activity::class)->with(['causer:id,name,email'])->allowedFilters([...])->allowedSorts(['created_at', 'log_name', 'event'])->defaultSort('-created_at')->paginate($request->perPage())->withQueryString()`.
  - Extend `allowedFilters` with `AllowedFilter::callback('created_from', fn ($query, $value) => $query->whereDate('created_at', '>=', $value))` and a mirroring `created_to` using `'<='`. Both MUST coerce `$value` to string and ignore empty strings (return early without modifying the query) so an empty form field doesn't break the SQL.
  - Use a `through()` transformer on the paginator to shape each row (use `Spatie\LaravelPackageTools\...` or `->through(fn (Activity $a) => [...])`) so the paginator wrapper (`data`, `per_page`, `current_page`, `total`, `links`) is preserved for `<DataTable>`.
  - The row shape MUST include: `id`, `log_name`, `description`, `event`, `subject_type` (raw class string — the frontend resolves the basename + label), `subject_id`, `causer` (id/name/email or null), `created_at` (ISO 8601), `properties` (full bag as array), `attributes` (array), `old_attributes` (array). The current closure drops the last three — those MUST be added.
  - Pass `users: User::query()->orderBy('name')->get(['id', 'name', 'email'])` as a second page prop.
  - Pass `subjectTypes: $this->subjectTypeOptions()` as a third page prop — see Task B2 for the helper.
  - Reference convention: `VehicleController@index` after vehicles-crud for the pagination + filter wiring; `InvoiceController@index` for the combobox-payload pattern.

- [ ] **Task B2**: Add `SUBJECT_TYPE_LABELS` constant + `subjectTypeOptions()` helper on `AuditLogController`.
  - Define `private const SUBJECT_TYPE_LABELS = [Service::class => 'Servicio', Invoice::class => 'Factura', Contract::class => 'Contrato', ServiceIncident::class => 'Novedad', DayStatus::class => 'Día', Vehicle::class => 'Vehículo', Driver::class => 'Conductor', ThirdParty::class => 'Tercero', User::class => 'Usuario', Fuec::class => 'FUEC', VehicleLocation::class => 'Ubicación', IncidentType::class => 'Tipo de Novedad', DocumentType::class => 'Tipo de Documento', Eps::class => 'EPS', PensionFund::class => 'Fondo de Pensiones', SeveranceFund::class => 'Fondo de Cesantías'];` — 16 entries covering every model with the `LogsActivity` trait.
  - Implement `private function subjectTypeOptions(): array`:
    - Query `Activity::query()->orderByDesc('created_at')->limit(1000)->distinct()->pluck('subject_type')->filter()->unique()->values()`.
    - Map each `$type` to `['value' => $type, 'label' => self::SUBJECT_TYPE_LABELS[$type] ?? class_basename($type)]`.
    - Sort the array by `label` ascending (case-insensitive).
    - Return as a plain PHP array (not a Collection), so Inertia serializes it as a JSON array.
  - Reference convention: any controller that exposes a reference/option list (e.g. `VehicleController::documentTypeOptions`).

- [ ] **Task B3**: Add `tests/Feature/Http/Controllers/AuditLogControllerTest.php` (new file) covering pagination, projection, filters, and authorization.
  - `test('index returns paginated payload with users and subjectTypes props')` — seed 3 activity rows, log in as admin, assert response has `activities.data`, `activities.per_page`, `activities.current_page`, `activities.total`, `users` is array with at least one `{id, name, email}`, `subjectTypes` is array with `[{value, label}]` entries.
  - `test('index projects the properties bag including justification and edited_on_executed_day')` — directly create an `Activity` via `activity()->performedOn($service)->causedBy($admin)->withProperties(['justification' => 'test reason', 'edited_on_executed_day' => true])->log('updated')`; load `/audit-log`; assert the row's `properties.justification === 'test reason'` AND `properties.edited_on_executed_day === true`.
  - `test('index projects attributes and old_attributes for diff rendering')` — create an `Activity` with `->withProperties(['attributes' => ['unit_value' => 100], 'old' => ['unit_value' => 50]])` (matches spatie's default shape); assert the projected row includes both.
  - `test('index filters by subject_type exact')` — seed 2 activities (one on a Service, one on a Vehicle); apply `filter[subject_type]=App\\Models\\Service`; assert only the Service row is returned.
  - `test('index filters by causer_id exact')` — seed 2 activities with different causers; apply `filter[causer_id]={userId}`; assert only the matching causer's row is returned.
  - `test('index filters by event exact')` — seed activities with events `created`, `updated`, `deleted`; apply `filter[event]=updated`; assert only the updated row is returned.
  - `test('index filters by created_from and created_to date range')` — seed 3 activities at `today-5d`, `today-2d`, `today`; apply `filter[created_from]=today-3d` AND `filter[created_to]=today-1d`; assert only the `today-2d` row is returned. Must use `Carbon::setTestNow` or explicit `travel()` to pin "today".
  - `test('index filters created_from and created_to ignore empty strings')` — apply `filter[created_from]=` (empty string); assert response is 200 and returns all rows (no SQL error).
  - `test('index returns 403 for operator')`, `test('index returns 403 for driver')`, `test('index returns 403 for accounting')`, `test('index returns 302 redirect for unauthenticated')` — role-gate pins.
  - `test('subjectTypes is dynamically computed from distinct subject_type values in the last 1000 activity rows')` — seed activities on Service + Vehicle + DayStatus; assert `subjectTypes` array contains exactly those three `value`s with their correct Spanish labels, sorted.
  - Reference convention: `tests/Feature/Http/Controllers/ContractControllerTest.php` after contracts-crud.

- [ ] **Task B4**: Extend `tests/Feature/Http/Controllers/ServiceLockingTest.php` with the REQ-009 AC#4 activity-log pin.
  - `test('admin editing an executed-day service with justification writes exactly one activity_log row with the justification in properties')` — following the existing test fixtures (contract + vehicle + driver + service on an executed day), act as admin, PUT `/services/{id}` with a valid payload including `justification => 'Corrección de fecha por error de captura inicial — aprobado por supervisor.'`, assert `Activity::query()->where('causer_id', $admin->id)->where('subject_type', Service::class)->where('subject_id', $service->id)->count() === 1`, load the row and assert `$activity->properties['justification'] === 'Corrección de fecha por error de captura inicial — aprobado por supervisor.'` AND `$activity->properties['edited_on_executed_day'] === true`.
  - Reference convention: the existing tests in the same file for Carbon + Service + DayStatus fixture setup.

### Frontend — shared primitive

- [ ] **Task F1**: Create `resources/js/components/users/user-combobox.tsx`.
  - Reference convention: `resources/js/components/third-parties/third-party-combobox.tsx` after contracts-crud.
  - Define and export `type UserOption = Pick<User, 'id' | 'name' | 'email'>`.
  - Props: `{ users: UserOption[]; value: number | null; onChange: (value: number | null) => void; placeholder?: string; disabled?: boolean; invalid?: boolean; id?: string; className?: string }`.
  - Renders each `CommandItem` as: primary line `{name}` (regular weight), secondary muted line `{email}` (text-xs text-muted-foreground).
  - Command search matches against `name` and `email` (case-insensitive).
  - Empty state label "Sin usuarios.".
  - Handles "clear selection" — a `<CommandItem>` at the top labeled "Todos los usuarios" that sets `value` to `null`. Same pattern as `<ThirdPartyCombobox />`.

### Frontend — audit-log-specific

- [ ] **Task F2**: Create `resources/js/components/audit-log/audit-log-detail-sheet.tsx`.
  - Shadcn `<Sheet side="right">` wrapping a `<SheetContent className="w-full sm:max-w-xl overflow-y-auto">`.
  - Props: `{ open: boolean; onOpenChange: (open: boolean) => void; activity: ActivityRow | null }` where `ActivityRow` is imported from `./index.tsx` (or extracted to a shared types file under `resources/js/types/audit-log.ts`).
  - Sheet header: causer name (or "Sistema" when null) + email muted + event Badge + formatted timestamp (reuse `dateTimeFormatter` from the index).
  - Body sections, in order:
    1. **Descripción** — `<Card>` wrapping the activity description.
    2. **Justificación** — `<Card>` rendering `activity.properties?.justification` in a `<blockquote className="border-l-4 pl-4 italic">`; only rendered when `justification` is truthy. An amber "Día ejecutado" `<Badge>` renders in the card header when `properties.edited_on_executed_day === true`. When absent, the entire card is omitted.
    3. **Cambios** — `<Card>` rendering a 2-column grid: left "Antes" (from `old_attributes`), right "Después" (from `attributes`). Compute keys as `new Set([...Object.keys(old || {}), ...Object.keys(attrs || {})])`. For each key, render a row with the key on the left and the value on the right in each column; render `—` when the key is absent in that bag. Only show the card when at least one of `attributes` / `old_attributes` is non-empty.
    4. **Propiedades adicionales** — a `<details>` with summary "Propiedades adicionales" wrapping a `<pre className="text-xs">` with `JSON.stringify(otherProps, null, 2)` where `otherProps` = `properties` minus `justification` and `edited_on_executed_day`. Only shown when at least one such key exists.
  - Sheet footer: a `<SheetClose asChild><Button variant="outline">Cerrar</Button></SheetClose>`.
  - Activity row `null` case: the Sheet content renders a muted "Sin actividad seleccionada." block (defensive — the index should never pass null when `open === true`, but the guard prevents a crash).

- [ ] **Task F3**: Extract `ActivityRow` type to `resources/js/types/audit-log.ts` (new).
  - Shape: `{ id: number; log_name: string | null; description: string; event: string | null; subject_type: string | null; subject_id: number | null; causer: { id: number; name: string; email: string } | null; created_at: string | null; properties: Record<string, unknown>; attributes: Record<string, unknown>; old_attributes: Record<string, unknown>; }`.
  - Also export `type SubjectTypeOption = { value: string; label: string }`.
  - Also export a `SUBJECT_TYPE_LINK_MAP` constant mapping `'App\\Models\\Service' → '/services'`, `'App\\Models\\Invoice' → '/invoices'`, `'App\\Models\\Contract' → '/contracts'`, `'App\\Models\\ServiceIncident' → '/service-incidents'`, `'App\\Models\\DayStatus' → '/day-statuses'`. Other subject types land on `null` (not linkable). The index's Entidad cell uses this map to decide whether to wrap in `<Link>`.

- [ ] **Task F4**: Rewrite `resources/js/pages/audit-log/index.tsx` around `<DataTable>` + `useServerTable`.
  - Replace the current bare table with the pattern used by `resources/js/pages/services/index.tsx`, but with audit-log-specific columns.
  - Define `auditLogFilters: FilterDefinition[]`:
    - `causer_id` (label: "Usuario") — renders via a custom channel (the `<UserCombobox />` is rendered above the table, not inline in the filter popover).
    - `subject_type` (label: "Entidad") — renders as a `<Select>` with options from `subjectTypes`.
    - `event` (label: "Acción") — renders as a `<Select>` with fixed options `[{value: 'created', label: 'Creado'}, {value: 'updated', label: 'Actualizado'}, {value: 'deleted', label: 'Eliminado'}, {value: 'restored', label: 'Restaurado'}]`.
    - `created_from` (label: "Desde") — renders as `<Input type="date">`.
    - `created_to` (label: "Hasta") — renders as `<Input type="date">`.
  - Render the `<UserCombobox />` above the table wired to the `causer_id` filter (mirrors how `contracts/index.tsx` renders `<ThirdPartyCombobox />` above the table).
  - Columns (via a new `columns.tsx` file or inline since there are only 7): Fecha, Usuario, Acción, Entidad, Descripción, Justificación, Acciones.
  - `getRowClassName={(row) => row.original.properties?.edited_on_executed_day ? 'bg-amber-500/10 hover:bg-amber-500/15' : undefined}`.
  - Acciones cell: a `<Button variant="ghost" size="icon">` with `<Eye>` icon that calls `setSelected(row.original)` + `setSheetOpen(true)`.
  - Below the DataTable, render `<AuditLogDetailSheet open={sheetOpen} onOpenChange={setSheetOpen} activity={selected} />`.
  - Typescript: page props `{ activities: PaginatedData<ActivityRow>, users: UserOption[], subjectTypes: SubjectTypeOption[] }`.
  - Breadcrumbs: `[{ title: 'Administración' }, { title: 'Auditoría', href: '/audit-log' }]`.
  - Reference conventions: `resources/js/pages/vehicles/index.tsx` for the DataTable skeleton; `resources/js/pages/contracts/index.tsx` for the above-the-table combobox pattern.

- [ ] **Task F5**: Extract an `auditLogColumns` const from `index.tsx` into `resources/js/pages/audit-log/columns.tsx` if inline defs push past ~60 lines.
  - Follows the same `ColumnDef<ActivityRow>[]` shape used by every rebuilt CRUD.
  - Entidad cell logic: `const linkPath = subject_type ? SUBJECT_TYPE_LINK_MAP[subject_type] : null; return linkPath && subject_id ? <Link href={`${linkPath}/${subject_id}`}>{`${label} #${subject_id}`}</Link> : <span>{label + (subject_id ? ` #${subject_id}` : '')}</span>` — where `label` resolves from `subjectTypes` or defaults to `class_basename(subject_type)` via a client-side helper.
  - Justificación cell logic: `return properties?.justification ? <span className="truncate max-w-sm">{properties.justification}</span> : <span className="text-muted-foreground">—</span>`.

- [ ] **Task F6**: Add the destructive `<Alert>` banner to `resources/js/components/services/service-form.tsx` above the existing justification textarea.
  - Inside the existing `{isAdminEdit && (<>...</>)}` block, prepend a `<Alert variant="destructive">` with:
    - `<AlertTriangle className="h-4 w-4" />` icon.
    - `<AlertTitle>Día ejecutado</AlertTitle>`.
    - `<AlertDescription>Este servicio pertenece a un día ejecutado. La modificación requiere justificación obligatoria y quedará registrada en la auditoría.</AlertDescription>`.
  - The existing h3 heading "Justificación del cambio" + textarea + error display remain untouched below the Alert.
  - No new imports should leak outside the form — only add `Alert`, `AlertDescription`, `AlertTitle` from `@/components/ui/alert` and `AlertTriangle` from `lucide-react`.

### Tests

- [ ] **Task T1**: Create `tests/Browser/AuditLogIndexTest.php` — Dusk suite covering the filter + detail-sheet flow.
  - `beforeEach`: `php artisan migrate:fresh --no-interaction` (build fixtures inline, not via `--seed`). Create admin, operator, and driver users via factories with matching Spatie roles. Create 3 `Activity` rows via raw `activity()` calls (one on a Service with `edited_on_executed_day => true` + a justification, one on a Vehicle as `updated`, one on an Invoice as `created`).
  - Scenario 1: `test('admin sees the audit log index with filter bar and table')` — login as admin, visit `/audit-log`, assert no `[role="alert"]` exception banner, assert the table headers Fecha / Usuario / Acción / Entidad / Descripción / Justificación / Acciones are visible, assert all 3 seeded rows appear, assert the executed-day row has a `bg-amber-500/10` style (probe via `attribute` selector or class presence via a `data-testid` the implementation adds).
  - Scenario 2: `test('admin filters audit log by subject_type = Service')` — on the same page, apply the subject_type filter to "Servicio" (the Spanish label in the Select); assert only the Service row remains; assert the URL query string contains `filter[subject_type]=App\\Models\\Service` (URL-encoded).
  - Scenario 3: `test('admin opens the detail sheet and reads the justification')` — click the "Ver detalles" icon on the executed-day row; assert a Sheet is visible with headings Descripción / Justificación / Cambios; assert the justification text seeded in `beforeEach` is visible inside the blockquote; close the Sheet.
  - Scenario 4: `test('operator gets 403 on /audit-log')` — logout, login as operator, visit `/audit-log`; assert the page shows a 403 indicator (either the Inertia 403 page or a response status — pick whichever the project already asserts for other role gates; reference: the 403 assertions in `ContractsIndexAndShowTest.php` + `InvoicesIndexAndShowTest.php`).
  - Each scenario takes a screenshot at a key step (e.g. `$browser->screenshot('audit-log-index-admin')`, `$browser->screenshot('audit-log-detail-sheet-justification')`). Screenshots land in `tests/Browser/screenshots/`.
  - Reference convention: `tests/Browser/InvoicesIndexAndShowTest.php` for the consolidated-file pattern.

- [ ] **Task T2**: Document the Playwright MCP walkthrough (in the Verification section below). This is not a committable test but an explicit checklist for manual verification during implementation.

### Docs

- [ ] **Task D1**: Update `docs/phases/phase-4-billing-reports.md` §4.3 once this requirement ships.
  - Add a ✅ bullet under §4.3 noting `audit-log-enhancements` merged + the justification UX is surfaced on `/audit-log` with filters.
  - Update the top-of-file status line to reflect Phase 4 as complete (pending the final merge, to be flipped in the final docs commit of this requirement).

- [ ] **Task D2**: Update `docs/phases/README.md` Phase 4 row from 🔶 to ✅.

## Verification

Verification has four layers. Playwright MCP is for *interactive* development-time checks and does **not** replace committable regression coverage.

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
2. Navigate to `/services`, open any service on an already-executed day, click Editar. `mcp__playwright__browser_snapshot` — verify the destructive `<Alert>` renders above the justification textarea with title "Día ejecutado" + the mandated description copy.
3. Submit a valid edit with a justification "Corrección de fecha por error de captura inicial — aprobado por supervisor.". `mcp__laravel-boost__browser-logs` to confirm no console errors.
4. Navigate to `/audit-log`. Snapshot — verify the DataTable renders with the 7 columns (Fecha / Usuario / Acción / Entidad / Descripción / Justificación / Acciones) and the above-the-table filter bar has the 5 controls (UserCombobox, Entidad Select, Acción Select, Desde date, Hasta date).
5. Verify the edit from step 3 appears at the top with amber row tint, the Justificación column renders the first ~80 chars of the submitted text, and the Entidad cell is a Link to `/services/{id}`.
6. Click "Ver detalles" on that row. Snapshot the Sheet — verify the justification renders in the blockquote card, the "Día ejecutado" Badge is visible, and the Cambios section shows the diffed fields with "Antes" / "Después" columns.
7. Apply the Usuario filter — pick admin via the `<UserCombobox />`. Snapshot — verify only admin-authored rows remain.
8. Apply `subject_type = Servicio` via the Entidad Select. Snapshot — verify only Service rows remain.
9. Apply `created_from = today - 7d`, `created_to = today`. Verify only rows in the range remain.
10. Clear all filters. Verify the full paginated set returns and the pagination controls work (Siguiente / Anterior).
11. Logout. Login as operator. Navigate to `/audit-log` — verify 403. Same for driver and accounting.
12. Use `mcp__laravel-boost__browser-logs` to inspect any JS console errors during the flow.

- [ ] Scenario 1: Admin sees the destructive Alert banner on the service edit form for an executed-day service.
- [ ] Scenario 2: Admin submits an edit + justification; the activity appears on `/audit-log` with amber tint and the justification in the Justificación cell.
- [ ] Scenario 3: Admin clicks "Ver detalles" — Sheet renders with the justification, diff, and raw properties.
- [ ] Scenario 4: Admin filters by Usuario + Entidad + date range; table narrows correctly.
- [ ] Scenario 5: Operator receives 403 on `/audit-log`.
- [ ] Scenario 6: Driver receives 403 on `/audit-log`.
- [ ] Scenario 7: Accounting receives 403 on `/audit-log`.

### 2. Backend regression — Pest feature tests (required)

Tasks B3 (new `AuditLogControllerTest.php`) and B4 (extend `ServiceLockingTest.php`) above MUST ship with this requirement. Run via `./vendor/bin/sail test --compact`. The full suite MUST stay green.

### 3. UI regression — Laravel Dusk browser tests (required)

Task T1 above (`tests/Browser/AuditLogIndexTest.php`) MUST ship. Each test MUST:

- Assert no `[role="alert"]`, exception trace, or visible error UI.
- Assert key Spanish strings render with correct diacritics (Auditoría, Justificación, Descripción, Día ejecutado, Usuario, Acción, Entidad, Fecha, Cambios, Antes, Después, "Corrección de fecha por error de captura inicial").
- Take screenshots at key interaction steps for visual review.
- Use `migrate:fresh --no-interaction` in `beforeEach` (not `--seed`) and build fixtures inline.

Run locally via `./vendor/bin/sail dusk --filter=AuditLogIndexTest`. CI does not run Dusk currently, but the suite MUST run cleanly locally before merge.

### 4. API endpoints (curl)

The `/audit-log` route is an Inertia route, not a public JSON API. Auth-gate verification only:

```bash
# Admin: should get a 200
curl -s -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@sgte.app","password":"password"}' \
  -c cookies-admin.txt

curl -s -o /dev/null -w "%{http_code}\n" \
  -H "Accept: text/html" \
  -b cookies-admin.txt \
  http://localhost/audit-log
# Expected: 200

# Operator: should get a 403
curl -s -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"operator@sgte.app","password":"password"}' \
  -c cookies-operator.txt

curl -s -o /dev/null -w "%{http_code}\n" \
  -H "Accept: text/html" \
  -b cookies-operator.txt \
  http://localhost/audit-log
# Expected: 403
```

## Dependencies

- **Phase 2** (day-status logic, executed-day semantics) — merged; provides the state machine that `ServiceUpdateRequest` checks.
- **Phase 4 billing workflow** (`invoices-crud`, `invoice-service-assignment`, `invoice-pdf-generation`) — merged; provides the invoice models whose activities show up in the audit log.
- **Six Blueprint CRUD rebuilds** (vehicles-crud, drivers-crud, third-parties-crud, contracts-crud, invoices-crud, service-incidents-crud) — merged; provides the DataTable + useServerTable + above-the-table combobox pattern this requirement reuses, and the `<ThirdPartyCombobox />` precedent for the new `<UserCombobox />`.
- **No new packages** — `spatie/laravel-activitylog`, `spatie/laravel-query-builder`, shadcn `<Sheet>`, shadcn `<Alert>`, lucide-react are all already installed.

## Notes

### REQ-009 coverage after this requirement

SRS §REQ-009 acceptance criteria (lines 525–542):

- AC#1 ("block all service fields for Dispatcher role while day is Ejecutado") — ✅ already covered by `ServiceUpdateRequest::authorize()`.
- AC#2 ("Administrator role needs mandatory justification") — ✅ already covered by `ServiceUpdateRequest::rules()` requiring `justification` (min:10, max:500) + the destructive Alert banner added by this requirement making the requirement explicit in the UI.
- AC#3 ("Accounting role can only modify accounting/invoicing fields") — ✅ already covered by the accounting branch of `ServiceUpdateRequest::rules()` returning a billing-only field list.
- AC#4 ("system SHALL log in the audit trail: user, date, previous value, new value, justification") — ✅ covered: `spatie/laravel-activitylog` writes causer + timestamp + `old_attributes` + `attributes` for every logged model, and `ServiceController@update` adds the justification to `properties`. This requirement adds the surface (filters + detail sheet + justification column) and the Pest pin (Task B4) that verifies the write.

After merge, Phase 4 is complete and both trackers flip to ✅.

### Why no backfill / no data migration

The `activity_log` table already exists, already carries `properties` as JSON, and already has the `causer_id` / `subject_type` / `subject_id` columns this requirement relies on (part of spatie/laravel-activitylog's default schema). The only thing being changed is the controller projection + the UI — no schema touched, no row rewrite needed.

### Why the subject-type filter is dynamic

A static whitelist of all 16 `LogsActivity` models would list types that rarely (or never) have activity entries — EPS, PensionFund, SeveranceFund, DocumentType. A dynamic list computed from the distinct `subject_type` values in the last 1000 activity rows adapts to what's actually being audited in the running system. The 1000-row window is a reasonable cap that keeps the query cheap while still surfacing long-tail models that appear occasionally.

### Reusable primitives introduced

- **`<UserCombobox />`** — parallel to `<ThirdPartyCombobox />` + `<MunicipalityCombobox />`. Will be reused by the invoices filter bar (filter by who created the invoice) and service-incidents filter bar (filter by who logged the incident) in future follow-up work. The primitive has no audit-log-specific assumptions.
- **`<AuditLogDetailSheet />`** — specific to the audit log, but the Sheet-for-detail-view pattern (as opposed to Dialog or inline expansion) is a precedent future "detail view on a list" requirements can follow when the detail payload has more than ~5 fields.

### Out of scope, deferred

- DayStatus-edit justification (SRS REQ-009 is scoped to Service records during an executed day).
- Invoice-after-payment edit guard (not in SRS REQ-009 explicitly).
- Full-text search over `properties.justification` (filter by causer/subject/event/date range is enough; FTS can be a later requirement).
- CSV / PDF export of the audit log (compliance export is a separate workflow).
- A per-record "Audit trail" tab on individual Service / Invoice / Contract show pages (would expose the same data scoped to the single record — valuable, but separate requirement; this one is for the admin-wide panel).
- Notification emails when a compliance-critical edit happens (dashboard alerts already surface `edited_on_executed_day` rows; email notifications on these events are out of scope).

### Estimated commit count

About **11–13 commits**:

- 1 doc commit (this requirement file).
- 1 backend commit (B1 + B2 — controller rewrite + subjectTypeOptions helper + SUBJECT_TYPE_LABELS constant).
- 1 backend commit (B3 — AuditLogControllerTest.php).
- 1 backend commit (B4 — ServiceLockingTest.php extension).
- 1 frontend commit (F1 — `<UserCombobox />`).
- 1 frontend commit (F3 — ActivityRow + SubjectTypeOption + SUBJECT_TYPE_LINK_MAP types).
- 1 frontend commit (F2 — `<AuditLogDetailSheet />`).
- 1 frontend commit (F4 + F5 — index rewrite + columns).
- 1 frontend commit (F6 — destructive Alert banner on service-form).
- 1 Dusk test commit (T1).
- 1 final docs commit (D1 + D2 — flip Phase 4 trackers to ✅, mark this requirement completed).

Likely smaller than contracts-crud (14) because this rebuild has no new shared business-logic primitive (no `contractPeriodStatus()` equivalent) — the `<UserCombobox />` is a direct parallel to `<ThirdPartyCombobox />` with no new state-machine logic. Similar to invoice-pdf-generation (10).
