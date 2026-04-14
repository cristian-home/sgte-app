---
name: contracts-crud
type: feat
scope: contracts
status: completed
priority: high
created_date: 2026-04-13
completed_date: 2026-04-14
srs_refs: ["REQ-006", "REQ-011"]
migration_strategy: new
---

# Rebuild the Contratos module and introduce the contract temporal-state primitives

## Description

The `contracts` module ships with a full backend (`ContractController` resource methods, `ContractStoreRequest` / `ContractUpdateRequest`, the `ContractObject` enum, and the `end_date >= start_date` guard added in commit `2f3b402`) but **all four Inertia pages** (`index.tsx`, `show.tsx`, `create.tsx`, `edit.tsx`) are Blueprint-generated stubs that dump JSON. There is no real table, no real form, no status pill, no cross-links — nothing in this module is production-shaped today.

This rebuild is the **fourth Blueprint pilot**, following vehicles-crud, drivers-crud, and third-parties-crud. Its structural role is to **re-introduce the document-status primitives** after the third-parties negative test confirmed they don't leak into abstractions that don't need them, but with a **date-range** variant of the state machine instead of the single-due-date pattern:

- vehicles / drivers → `'expired' | 'expiring_soon' | 'ok'` against a single `*_due_date` column, 30-day window.
- **contracts → `'vigente' | 'por_vencer' | 'vencido' | 'inactivo'`** against `start_date + end_date + active`, 60-day window. The `inactivo` fourth state is the manual "closed" branch that has no parallel in single-due-date documents.

Two new shared primitives are introduced by this requirement:

1. `contractPeriodStatus()` inside the existing `resources/js/lib/document-status.ts` — a pure helper computing the four-state machine. Centralizing it keeps the `<ContractPeriodPill />`, the controller's `contract_status` filter, the index row tint, and the dashboard alerts panel all in lock-step (mirroring how `statusFor` already orchestrates the vehicles + drivers flow).
2. `<ThirdPartyCombobox />` — a searchable combobox parallel to `<MunicipalityCombobox />`, for picking a customer from the contracts form. It accepts an optional `role` prop to filter options (`'customer'` / `'provider'`) and an optional `forceInclude` prop to preload the currently-selected value on edit forms even when the third party has been flipped to `is_customer = false`. This primitive will be reused by the upcoming invoices-crud rebuild.

The rebuild also touches the dashboard: `DashboardController::buildDocumentAlerts` gains a third branch that surfaces `vencido` and `por_vencer` contracts alongside vehicles + drivers, deep-linking to `/contracts?filter[contract_status]=vencido` or `expiring_soon`. The panel subtitle is updated to stop naming the 30-day window (since contracts use 60 days).

Finally, this rebuild retires the cross-link TODO from third-parties-crud: the **Contratos card** on `third-parties/show.tsx` (commit `ebedc3a`) currently links to `/contracts/{id}` which lands on the Blueprint stub. After this rebuild, every "Ver contrato" cell in the app lands on the real show page.

**Out of scope:** bulk edit / bulk close; CSV or PDF export; FUEC generation from a contract; a contract-addendum workflow; contract-scoped billing summary (deferred to invoices-crud); rebuilding the remaining two Blueprint scaffolds (Invoices, Service-Incidents) — each gets its own requirement.

## Acceptance Criteria

- [x] **AC1**: WHEN an admin or operator navigates to `/contracts` THEN the page renders a paginated `<DataTable>` (not a JSON dump) with columns **Número**, **Cliente**, **Objeto**, **Vigencia**, **Estado**, **Acciones**.
- [x] **AC2**: WHEN a contract row renders THEN the **Número** cell shows `contract.contract_number` in font-mono and is a `<Link>` to `/contracts/{id}`.
- [x] **AC3**: WHEN a contract row renders THEN the **Cliente** cell shows the computed third-party name (natural `first_name + first_lastname` OR `company_name`) as a `<Link>` to `/third-parties/{third_party_id}`.
- [x] **AC4**: WHEN a contract row renders THEN the **Objeto** cell shows the Spanish label for `contract_object` (Empresarial / Turismo / Salud / Ocasional).
- [x] **AC5**: WHEN a contract row renders THEN the **Vigencia** cell shows `start_date → end_date` formatted via the shared `dateFormatter` (`es-CO`, `dd/mm/yyyy`).
- [x] **AC6**: WHEN a contract row renders THEN the **Estado** cell shows a `<ContractPeriodPill />` computing the four-state machine against today:
    - `active === false` → **Inactivo** (outline Badge, no suffix).
    - `active === true AND today > end_date` → **Vencido!** (destructive Badge with exclamation suffix).
    - `active === true AND end_date within 60 days of today` → **Por vencer** (secondary Badge).
    - `active === true AND start_date <= today <= end_date` AND `end_date > today + 60d` → **Vigente** (default Badge).
    - `active === true AND today < start_date` → still **Vigente** (future-dated contracts are considered in-force once `active` is true; this mirrors the current-backend behavior).
- [x] **AC7**: WHEN the user applies the **Estado** filter with value `vigente`, `por_vencer`, `vencido`, or `inactivo` THEN only rows matching the four-state machine remain. The filter MUST be implemented as a `Spatie\QueryBuilder\AllowedFilter::callback` named `contract_status` on `ContractController@index`.
- [x] **AC8**: WHEN the user applies the **Objeto** filter with any of the 4 `ContractObject` enum values THEN only rows whose `contract_object` matches remain. The filter is `AllowedFilter::exact('contract_object')` (current backend has it as a partial filter — this rebuild tightens it to exact).
- [x] **AC9**: WHEN the user picks a customer from the `<ThirdPartyCombobox role="customer" />` rendered above the table THEN only contracts whose `third_party_id` matches remain.
- [x] **AC10**: WHEN the user applies the **Activo** filter (Sí / No) THEN only rows whose `active` boolean matches remain.
- [x] **AC11**: WHEN a row's state is `vencido` THEN the row is tinted with `bg-destructive/10`; WHEN a row's state is `por_vencer` THEN the row is tinted with `bg-amber-500/10`; WHEN a row's state is `inactivo` THEN the row is tinted with `bg-muted/60`; WHEN the state is `vigente` THEN no tint is applied. Implemented via a `getRowClassName` prop passed to `<DataTable>` (reuses the hook added in vehicles-crud).
- [x] **AC12**: WHEN the user clicks the **Crear Contrato** action on the index THEN a `<ContractCreateDialog />` modal opens. The modal contains the new shared `<ContractForm />` component and, on successful submit, closes AND the index refreshes with the new row visible.
- [x] **AC13**: WHEN the user navigates to `/contracts/create` directly THEN the standalone create page renders `<ContractForm />` (no `idPrefix`, no modal wrapper) with a Guardar / Cancelar action bar. Cancelar returns to `/contracts`.
- [x] **AC14**: WHEN the user navigates to `/contracts/{id}/edit` THEN the edit page renders `<ContractForm />` with the contract's current values pre-filled AND an Actualizar / Cancelar action bar. The `<ThirdPartyCombobox />` inside the edit form receives `forceInclude={[thirdParty]}` so a contract whose customer has been flipped to `is_customer=false` STILL shows the current value in the option list.
- [x] **AC15**: WHEN the user toggles `is_generic` to `true` inside `<ContractForm />` THEN the `contract_number` Input HIDES AND a muted description replaces it reading **"Se generará automáticamente al guardar (GEN-####-YYYY)."**. WHEN the user toggles `is_generic` back to `false` THEN the Input re-appears; any previously-typed value is preserved (no reset).
- [x] **AC16**: WHEN the user submits a generic contract with an empty `contract_number` THEN the existing backend auto-generation logic (`GEN-####-YYYY` with a per-year sequence count) applies unchanged. **This load-bearing logic in `ContractController@store` MUST be preserved as-is — the rebuild only touches the Inertia layer above it.**
- [x] **AC17**: WHEN the user clicks the **Número** link in any row THEN the app navigates to `/contracts/{id}` AND the show page renders **five** Card sections in this order:
    1. **Header card** — `contract_number` (font-mono) as the title, the customer's computed name as the description, a `<ContractPeriodPill />`, an Activo/Inactivo Badge, and an Editar button.
    2. **Datos del Contrato** — Objeto (Spanish label), Contrato Genérico (Sí/No Badge), Recorrido / Ruta (`route_description` rendered as a multi-line paragraph preserving whitespace).
    3. **Cliente** — a tiny summary block: customer name, `documentType.code + identification_number` in font-mono, a "Ver tercero" `<Link>` to `/third-parties/{third_party_id}`.
    4. **Vigencia** — 3-column layout: Fecha de Inicio (`start_date`), Fecha de Fin (`end_date`), Días restantes (computed from today relative to `end_date`, signed — negative for expired — displayed with the same four-state Badge variant as the pill).
    5. **Servicios Recientes** — a small `<Table>` with the last 5 services where `services.contract_id = contract.id`, ordered by `service_date` DESC, columns **Fecha** (Link to `/services/{id}`), **Vehículo** (plate), **Conductor** (computed name), **Estado** (ServiceStatus Badge). Empty state "Sin servicios registrados." inside the table when the array is empty.
- [x] **AC18**: WHEN the user clicks "Ver contrato" from any row in the Contratos card on `third-parties/show.tsx` (commit `ebedc3a`) THEN the user lands on the rebuilt `/contracts/{id}` show page (NOT a Blueprint stub).
- [x] **AC19**: WHEN an admin loads `/dashboard` AND at least one contract is `vencido` or within 60 days of `end_date` with `active = true` THEN the **Alertas de Documentos** panel shows one row per alerting contract with `kind = 'contract'`, `label = 'Contrato'`, `subject = contract_number`, `due_date = end_date`, `days_remaining` signed, AND a deep-link to `/contracts?filter[contract_status]=vencido` (when `days_remaining < 0`) or `/contracts?filter[contract_status]=expiring_soon` (when `>= 0`). The panel remains capped at `ALERTS_MAX_ROWS = 10` and continues to sort all rows by `days_remaining` ascending.
- [x] **AC20**: WHEN an admin loads `/dashboard` THEN the **Alertas de Documentos** card description MUST read **"Documentos y contratos vencidos o por vencer."** (it currently says "Documentos vencidos o por vencer en los próximos 30 días." — the 30-day reference is removed because contracts use a 60-day window).
- [x] **AC21**: WHEN a driver, accounting, or unauthenticated user navigates to `/contracts` or `/contracts/{id}` THEN they receive 401 (unauthenticated) or 403 (driver / accounting do NOT hold `VIEW_CONTRACTS`).
- [x] **AC22**: WHEN the TypeScript type-check runs (`npm run types`) THEN the contracts pages MUST contribute zero new errors (the pre-existing deferred-Blueprint errors tracked in project memory are NOT acceptable as a floor — contracts moves OUT of that bucket after this rebuild).

## Technical Specification

### Data Model

**No new tables, no new columns.** Every field this requirement needs already exists:

```
contracts (existing — no changes)
├── id (bigint, PK)
├── contract_number (varchar, unique)
├── third_party_id (bigint, FK → third_parties.id)
├── contract_object (varchar, ContractObject enum)
├── start_date (date)
├── end_date (date, after_or_equal:start_date enforced in ContractStoreRequest)
├── route_description (text)
├── is_generic (boolean, default false)
├── active (boolean, default true)
└── created_at / updated_at / deleted_at (softDeletes)
```

`services.contract_id` already exists and powers the "Servicios Recientes" card.

### Enums

**No new enums.** `ContractObject` (`business` / `tourism` / `health` / `occasional`) is already defined. A small TS-side constant map translates enum values to Spanish labels (`Empresarial`, `Turismo`, `Salud`, `Ocasional`) for display — this lives co-located with `columns.tsx` rather than in a generated TS enum (same pattern as `VehicleStatus` display labels).

### Routes

**No new routes.** The existing `Route::resource('contracts', ContractController::class)` already provides every endpoint. Authorization is enforced inside each controller action via `Gate::authorize(Permission::*->value)` (ADR-005 §2).

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | `/contracts` | `ContractController@index` | `auth, verified` | `contracts.index` |
| GET | `/contracts/create` | `ContractController@create` | `auth, verified` | `contracts.create` |
| POST | `/contracts` | `ContractController@store` | `auth, verified` | `contracts.store` |
| GET | `/contracts/{contract}` | `ContractController@show` | `auth, verified` | `contracts.show` |
| GET | `/contracts/{contract}/edit` | `ContractController@edit` | `auth, verified` | `contracts.edit` |
| PUT | `/contracts/{contract}` | `ContractController@update` | `auth, verified` | `contracts.update` |
| DELETE | `/contracts/{contract}` | `ContractController@destroy` | `auth, verified` | `contracts.destroy` |

### Permissions

**No new permissions.** `VIEW_CONTRACTS`, `CREATE_CONTRACTS`, `UPDATE_CONTRACTS`, `DELETE_CONTRACTS` already exist and are granted to **Admin** and **Operator** by `seed_catalog_data`. Driver and Accounting MUST NOT hold these.

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Index | `resources/js/pages/contracts/index.tsx` | **REWRITE.** `<DataTable>` + `useServerTable` + `<ThirdPartyCombobox role="customer" />` above the table + `<ContractCreateDialog />` modal. Passes `getRowClassName` for row tinting (first rebuild since vehicles-crud to re-use this hook). |
| Show | `resources/js/pages/contracts/show.tsx` | **REWRITE.** Five Card sections (header + Datos del Contrato + Cliente + Vigencia + Servicios Recientes). |
| Create | `resources/js/pages/contracts/create.tsx` | **REWRITE.** Thin wrapper around `<ContractForm />` with a Guardar/Cancelar action bar. |
| Edit | `resources/js/pages/contracts/edit.tsx` | **REWRITE.** Thin wrapper around `<ContractForm />` with pre-filled `useForm` and an Actualizar/Cancelar action bar. Passes `forceIncludeCustomer={[thirdParty]}` to the inner form. |
| Columns | `resources/js/pages/contracts/columns.tsx` | **NEW.** TanStack `ColumnDef<ContractRow>[]`. |
| Form | `resources/js/components/contracts/contract-form.tsx` | **NEW.** Shared form component used by create page, edit page, and create-modal dialog. Flat single-column layout with a 2-col responsive grid. Handles the `is_generic` conditional hiding of `contract_number`. |
| Modal | `resources/js/components/contracts/contract-create-dialog.tsx` | **NEW.** Modal wrapper around `<ContractForm idPrefix="dlg" />` mirroring `third-party-create-dialog.tsx`. |
| Period Pill | `resources/js/components/contracts/contract-period-pill.tsx` | **NEW.** Single Badge rendering the four-state label with variant-per-state. Also exports `contractRowTint(contract)` for the index `getRowClassName`. |
| Third-Party Combobox | `resources/js/components/third-parties/third-party-combobox.tsx` | **NEW — reusable primitive.** Parallel to `<MunicipalityCombobox />`. Props `{ thirdParties, value, onChange, role?, forceInclude?, placeholder?, disabled?, invalid?, id?, className? }`. Filters by role when provided; renders each option as `${documentType.code} ${identification_number} — ${computedName}`. |
| Shared helper | `resources/js/lib/document-status.ts` | **EXTEND.** Add `CONTRACT_EXPIRY_WINDOW_DAYS = 60`, `type ContractPeriodStatus`, `contractPeriodStatus()`, `contractStatusBadgeVariant()`. Do NOT move existing vehicle/driver exports. |

## Migration Strategy

`new` (formal frontmatter value), but **no migration files are written or modified**. Every column, FK, enum, and permission this requirement needs already exists.

After implementing this requirement, no `php artisan migrate` invocation is required.

## Tasks

### Backend

- [x] **Task B1**: Paginate `ContractController@index` and expand filters + eager-loads.
  - Replace the trailing `->get()` with `->paginate($request->perPage())->withQueryString()`.
  - Add eager-loads: `'thirdParty:id,document_type_id,identification_number,is_natural_person,first_name,first_lastname,company_name'`, `'thirdParty.documentType:id,code,name'`.
  - Tighten `contract_object` from a partial filter to `AllowedFilter::exact('contract_object')`.
  - Add `AllowedFilter::exact('third_party_id')`.
  - Add a new `AllowedFilter::callback('contract_status', fn ($query, $value) => ...)` that accepts `vigente | por_vencer | vencido | inactivo | expiring_soon | expired` (the last two are aliases used by the dashboard deep-links: `expiring_soon` → `por_vencer`, `expired` → `vencido`). The callback computes the state SQL-side using `today()`, `today() + 60 days`, `active`, `start_date`, `end_date`. Reference: the `docs_status` callback in `VehicleController@index`.
  - Add `allowedSorts(['contract_number', 'start_date', 'end_date', 'created_at'])` and `defaultSort('-created_at')`.
  - Pass `thirdPartyOptions` and (optionally) `customerCount` payloads so the upcoming `<ContractCreateDialog />` modal + the `<ThirdPartyCombobox />` above the table have their options in one trip. Pull customers via `ThirdParty::query()->where('is_customer', true)->with('documentType:id,code,name')->orderBy(...)->get([...])`.
  - Reference convention: `VehicleController@index` after vehicles-crud.

- [x] **Task B2**: Expand `ContractController@show` to load relationships + recent services.
  - Eager-load `thirdParty.documentType`.
  - Load `recentServices` as a separate query: last 5 `Service` records where `contract_id = $contract->id`, ordered by `service_date` DESC, `select(['id', 'service_date', 'service_status', 'vehicle_id', 'driver_id'])`, with `->with(['vehicle:id,plate', 'driver:id,first_name,first_lastname'])`.
  - Pass them to the Inertia page as `contract` (full model with `thirdParty.documentType`) and `recentServices`.
  - Reference convention: `ThirdPartyController@show` after third-parties-crud.

- [x] **Task B3**: Expand `ContractController@create` and `ContractController@edit` payloads.
  - `create()`: pass `thirdParties` (customers only, with documentType eager-loaded, `get(['id','document_type_id','identification_number','is_natural_person','first_name','first_lastname','company_name'])`).
  - `edit()`: same as `create()`, AND eager-load `$contract->load('thirdParty.documentType')` so the edit page can build the `forceInclude` array regardless of the customer's current `is_customer` flag.
  - Both methods must preserve the existing `Gate::authorize(...)` call exactly.

- [x] **Task B4**: Extend `DashboardController::buildDocumentAlerts` with a contracts branch.
  - Add `private const CONTRACT_EXPIRY_ALERT_DAYS = 60;` near the existing `EXPIRY_ALERT_DAYS` constant (keep the existing 30-day constant for vehicles + drivers).
  - Query `Contract::query()->select(['id', 'contract_number', 'end_date', 'active'])->where('active', true)->whereNotNull('end_date')->where('end_date', '<=', $today->copy()->addDays(self::CONTRACT_EXPIRY_ALERT_DAYS))->get()`.
  - Map each row to an alert array with `kind => 'contract'`, `label => 'Contrato'`, `subject => $contract->contract_number`, `due_date => $contract->end_date?->toDateString()`, `days_remaining => (int) $today->diffInDays($contract->end_date, false)`, `link => $this->contractAlertLink($daysRemaining)`.
  - Add the `contractAlertLink(int $daysRemaining): string` helper symmetric with `vehicleAlertLink`/`driverAlertLink`: `< 0` → `/contracts?filter[contract_status]=vencido`, otherwise `/contracts?filter[contract_status]=expiring_soon`.
  - Concat `$contractAlerts` into the existing `$vehicleAlerts->concat($driverAlerts)` pipeline. Preserve `sortBy('days_remaining')` and the `take(self::ALERTS_MAX_ROWS)` cap.
  - Update the PHPDoc `@return` tuple shape on `buildDocumentAlerts` (kind widens to `'vehicle'|'driver'|'contract'`).

- [x] **Task B5**: Update the dashboard card description in `resources/js/pages/dashboard.tsx`.
  - Change the "Alertas de Documentos" card subtitle from **"Documentos vencidos o por vencer en los próximos 30 días."** to **"Documentos y contratos vencidos o por vencer."**.
  - No other frontend dashboard wiring needed — the existing alert row renderer already uses `kind`, `label`, `subject`, `due_date`, `days_remaining`, `link`. Just confirm the renderer maps `kind === 'contract'` through the same row shape without crashing (it should — the keys are identical).

### Frontend — shared primitives

- [x] **Task F1**: Extend `resources/js/lib/document-status.ts`.
  - Add `export const CONTRACT_EXPIRY_WINDOW_DAYS = 60;` near the existing `EXPIRY_WINDOW_DAYS = 30`.
  - Add `export type ContractPeriodStatus = 'vigente' | 'por_vencer' | 'vencido' | 'inactivo';`.
  - Add `export function contractPeriodStatus(contract: { start_date: string | null; end_date: string | null; active: boolean }, today?: string): ContractPeriodStatus`.
    - `active === false` → `'inactivo'`.
    - `active === true` AND parsed `end_date` is null OR `end_date < today` → `'vencido'`.
    - `active === true` AND `end_date` within `CONTRACT_EXPIRY_WINDOW_DAYS` days of today → `'por_vencer'`.
    - Otherwise → `'vigente'`.
    - Reuses `parseDueDate` and the existing `DAYS_IN_MS` constant.
  - Add `export function contractStatusBadgeVariant(status: ContractPeriodStatus): 'default' | 'secondary' | 'destructive' | 'outline'` — `vigente → default`, `por_vencer → secondary`, `vencido → destructive`, `inactivo → outline`.
  - **Do NOT** rename or re-scope the existing `DocumentStatus` / `statusFor` / `documentStatus` exports — vehicles + drivers pills still use them.
  - Add a PHPDoc-style block at the top of the `contractPeriodStatus` helper noting that the 60-day window mirrors the server-side `CONTRACT_EXPIRY_ALERT_DAYS` constant in `DashboardController`.

- [x] **Task F2**: Create `resources/js/components/contracts/contract-period-pill.tsx`.
  - Props: `{ contract: { start_date: string | null; end_date: string | null; active: boolean }, today?: string, showDays?: boolean }`.
  - Renders a single `<Badge>` with variant from `contractStatusBadgeVariant()` and Spanish label:
    - `vigente` → "Vigente"
    - `por_vencer` → "Por vencer"
    - `vencido` → "Vencido!" (note the exclamation — signals action needed)
    - `inactivo` → "Inactivo"
  - When `showDays === true` AND the status is `por_vencer` OR `vencido`, append "(X días)" where X is the days-remaining (signed, negative for expired).
  - Also exports `contractRowTint(contract): string | undefined` — returns `'bg-destructive/10 hover:bg-destructive/15'` for `vencido`, `'bg-amber-500/10 hover:bg-amber-500/15'` for `por_vencer`, `'bg-muted/60 hover:bg-muted/70'` for `inactivo`, `undefined` for `vigente`. The pill and the tint helper share the same `contractPeriodStatus()` call so they can never disagree.

- [x] **Task F3**: Create `resources/js/components/third-parties/third-party-combobox.tsx`.
  - Reference convention: `resources/js/components/municipality-combobox.tsx`.
  - Props: `{ thirdParties: ThirdPartyOption[]; value: string | null; onChange: (value: string | null) => void; role?: 'customer' | 'provider'; forceInclude?: ThirdPartyOption[]; placeholder?: string; disabled?: boolean; invalid?: boolean; id?: string; className?: string }`.
  - Define and export `type ThirdPartyOption = Pick<ThirdParty, 'id' | 'identification_number' | 'is_natural_person' | 'first_name' | 'first_lastname' | 'company_name' | 'is_customer' | 'is_provider'> & { document_type?: Pick<DocumentType, 'id' | 'code' | 'name'> | null }`.
  - Filters `thirdParties` by `role` when provided (`is_customer === true` for `'customer'`, `is_provider === true` for `'provider'`), then merges in any `forceInclude` entries that are not already present (deduped by `id`). This is how edit forms show a value that the role filter would otherwise hide.
  - Renders each `CommandItem` as: primary line `${computedName}`, secondary muted line `${documentType?.code ?? '?'} ${identification_number}`.
  - Command search matches against `identification_number`, `first_name`, `first_lastname`, `company_name` (case-insensitive).
  - Empty state label "Sin terceros.".

### Frontend — contracts-specific

- [x] **Task F4**: Create `resources/js/components/contracts/contract-form.tsx`.
  - Flat single-column layout with a 2-col responsive grid (`md:grid-cols-2`).
  - Props: `{ data, setData, errors, thirdParties, idPrefix?, forceIncludeCustomer? }` where `thirdParties: ThirdPartyOption[]` is the customer list from the controller.
  - Field rows in this order:
    1. 2-col row: `contract_number` (Input; HIDDEN when `data.is_generic === true` — replaced by a muted `<p className="text-sm text-muted-foreground">Se generará automáticamente al guardar (GEN-####-YYYY).</p>`) + `third_party_id` (`<ThirdPartyCombobox role="customer" forceInclude={forceIncludeCustomer} ...>`).
    2. 3-col row (`md:grid-cols-3`): `contract_object` (Select with the four enum values + Spanish labels) + `start_date` (Input type=date) + `end_date` (Input type=date).
    3. 1-col full-width row: `route_description` (Textarea, rows=4).
    4. Flex-row of toggles: `is_generic` (Switch) + `active` (Switch). Labels "Contrato Genérico" and "Activo".
  - Required-field labels carry the small destructive asterisk via the same `<RequiredMarker />` convention used in `driver-form.tsx` + `third-party-form.tsx`. Required fields: `contract_number` (ONLY when `!is_generic`), `third_party_id`, `contract_object`, `start_date`, `end_date`, `route_description`.
  - All input ids are prefixed with `idPrefix` (e.g. `${idPrefix ?? ''}contract_number`) so the modal can coexist with the standalone page without id collisions.
  - When toggling `is_generic` from `true` → `false`, **preserve** any previously-typed `contract_number` value in `data` (do NOT clear it — the user might be un-selecting a generic and want to keep what they had).
  - Error messages render below each field from the `errors` object.

- [x] **Task F5**: Create `resources/js/components/contracts/contract-create-dialog.tsx`.
  - Modal wrapper mirroring `third-party-create-dialog.tsx`. Owns its own `useForm` with defaults: `contract_number: ''`, `third_party_id: ''`, `contract_object: 'business'`, `start_date: ''`, `end_date: ''`, `route_description: ''`, `is_generic: false`, `active: true`.
  - Submits to `ContractController.store()`. On success: `reset()` + `onOpenChange(false)`.
  - Wraps `<ContractForm idPrefix="dlg" {...} />` inside a `<DialogContent>` sized `max-h-[calc(100vh-4rem)] flex flex-col px-0 sm:max-w-3xl`.
  - Submit button "Guardar"; cancel via `<DialogClose />`.

- [x] **Task F6**: Create `resources/js/pages/contracts/columns.tsx`.
  - Six `ColumnDef<ContractRow>` entries:
    1. `contract_number` (`accessorKey`) — header "Número" (sortable via `<DataTableColumnHeader />`), cell renders `<Link>` to `contracts.show(id).url` with `font-mono` text.
    2. `cliente` (computed `id: 'cliente'`) — header "Cliente", cell renders the third-party computed name inside a `<Link>` to `/third-parties/{third_party_id}`.
    3. `contract_object` — header "Objeto", cell renders the Spanish label via a local constant map `{ business: 'Empresarial', tourism: 'Turismo', health: 'Salud', occasional: 'Ocasional' }`.
    4. `vigencia` (computed `id: 'vigencia'`) — header "Vigencia", cell renders `${dateFormatter.format(parseDueDate(start_date))} → ${dateFormatter.format(parseDueDate(end_date))}`.
    5. `estado` (computed `id: 'estado'`) — header "Estado", cell renders `<ContractPeriodPill contract={row.original} />`.
    6. `actions` — `<DataTableRowActions editUrl={contracts.edit(id).url} onDelete={...} />` wrapped in `<Can permission={Permission.DELETE_CONTRACTS}>`.
  - Define a local `type ContractRow = Contract & { third_party?: ThirdParty & { document_type?: DocumentType | null } | null }` using the `Pick<T> & relations` convention.

- [x] **Task F7**: Rewrite `resources/js/pages/contracts/index.tsx`.
  - Replace the `<pre>` JSON dump with the services/vehicles/drivers/third-parties index pattern.
  - Define `contractFilters: FilterDefinition[]`:
    - `contract_status` → "Estado" with options `vigente / por_vencer / vencido / inactivo`.
    - `contract_object` → "Objeto" with options `business / tourism / health / occasional`.
    - `active` → "Activo" with options `1 / 0`.
  - Render `<ThirdPartyCombobox role="customer" />` above the table wired to the `third_party_id` filter (mirrors how `vehicles/index.tsx` renders `<MunicipalityCombobox />` above the table).
  - Wire `<ContractCreateDialog />` to the "Crear Contrato" button via `useState`.
  - Pass `getRowClassName={(row) => contractRowTint(row.original)}` to `<DataTable>` — this rebuild re-introduces row tinting (first since vehicles-crud).
  - Type the page props as `{ contracts: PaginatedData<ContractRow>, thirdParties: ThirdPartyOption[] }`.
  - Reference convention: `resources/js/pages/vehicles/index.tsx`.

- [x] **Task F8**: Rewrite `resources/js/pages/contracts/show.tsx`.
  - **Five Card sections** in the order listed in AC17:
    1. Header card (title + description + `<ContractPeriodPill showDays />` + Activo Badge + Editar button).
    2. Datos del Contrato (Objeto label, Contrato Genérico Badge, Recorrido / Ruta preserving whitespace via `whitespace-pre-wrap`).
    3. Cliente (name, `documentType.code + identification_number`, "Ver tercero" Link).
    4. Vigencia (3-col: start, end, days-remaining with a Badge using `contractStatusBadgeVariant`).
    5. Servicios Recientes (inline `<Table>` with the 4 columns, empty state "Sin servicios registrados." inside an empty `<TableBody>`).
  - Type the page props as `{ contract: ShowContract, recentServices: RecentServiceRow[] }` using the `Pick<T> & relations` pattern.
  - Breadcrumbs: `[{ title: 'Contratos', href: contracts.index().url }, { title: contract.contract_number, href: '#' }]`.
  - Reference convention: `resources/js/pages/third-parties/show.tsx`.

- [x] **Task F9**: Rewrite `resources/js/pages/contracts/create.tsx` and `edit.tsx` (bundled).
  - `create.tsx`: `useForm` with the default-empty values, render `<ContractForm {...} thirdParties={thirdParties} />` with a Guardar / Cancelar action bar.
  - `edit.tsx`: `useForm` with values pre-filled from the `contract` prop, render `<ContractForm {...} thirdParties={thirdParties} forceIncludeCustomer={contract.third_party ? [contract.third_party] : []} />` with an Actualizar / Cancelar action bar.
  - Both pages type their props to include `thirdParties: ThirdPartyOption[]`; the edit page additionally accepts the full loaded `contract` with the `third_party.document_type` relation.

### Tests

- [x] **Task T1 (Pest, backend — shared helpers + controller index)**: Add to `tests/Feature/Http/Controllers/ContractControllerTest.php`:
  - `test('index returns paginated payload with third-party relations')` — seed 3 contracts each with a distinct customer; assert `contracts.data` is array, `per_page`, `current_page`, `total` exist, each row has `third_party.document_type` loaded.
  - `test('index passes customer options for the create modal and the combobox filter')` — assert `thirdParties` prop is present, contains only `is_customer = true` entries, and each option has `document_type` eager-loaded.

- [x] **Task T2 (Pest, backend — filters)**: Add to the same test file:
  - `test('index filters by contract_status = vigente')` — seed 4 contracts (vigente, por_vencer, vencido, inactivo); apply `filter[contract_status]=vigente`; assert only the vigente row remains.
  - `test('index filters by contract_status = por_vencer')` — same shape, 60-day window boundary cases (e.g. `end_date = today+59d`, `today+61d`, `today+0d`).
  - `test('index filters by contract_status = vencido')` — same shape, assert `active=false` does NOT pollute the `vencido` bucket (inactivo wins).
  - `test('index filters by contract_status = inactivo')` — same shape.
  - `test('index aliases contract_status = expiring_soon to por_vencer')` AND `test('index aliases contract_status = expired to vencido')` — the dashboard deep-links use the aliased vocabulary, so the backend MUST accept both.
  - `test('index filters by contract_object exact')` — seed 2 contracts (business + tourism); apply `filter[contract_object]=business`; assert only the business row remains. This pins the "tightened from partial to exact" change.
  - `test('index filters by third_party_id exact')` — seed 2 customers + 2 contracts; apply the filter; assert only one row remains.

- [x] **Task T3 (Pest, backend — show)**:
  - `test('show returns contract with thirdParty.documentType loaded')` — assert `contract.third_party.document_type.code` is present.
  - `test('show returns recent services ordered by service_date desc')` — seed a contract with 7 services; assert `recentServices` length is 5 AND the first row has the latest `service_date`.
  - `test('show returns empty recent services when the contract has none')` — assert `recentServices` is an empty array.

- [x] **Task T4 (Pest, backend — store + generic preservation)**:
  - `test('store auto-generates contract_number when is_generic is true and contract_number is blank')` — assert the resulting DB row has `contract_number = 'GEN-0001-{year}'` with the correct year. This pins the load-bearing domain logic.
  - `test('store preserves the user-supplied contract_number when is_generic is true and contract_number is non-blank')` — the user said "I want this specific number, but mark it generic". Assert the user value wins.
  - `test('store enforces end_date >= start_date')` — regression test for commit `2f3b402` (should already exist; if not, add it).

- [x] **Task T5 (Pest, backend — dashboard alerts)**: Add to `tests/Feature/Http/Controllers/DashboardControllerTest.php`:
  - `test('dashboard surfaces expired contracts in document alerts')` — seed 1 active contract with `end_date = today - 3d`; assert the dashboard payload's `documentAlerts` contains a row with `kind = 'contract'`, `label = 'Contrato'`, `days_remaining < 0`, and the deep-link ending with `filter[contract_status]=vencido`.
  - `test('dashboard surfaces contracts expiring within 60 days in document alerts')` — seed 1 active contract with `end_date = today + 45d`; assert a row with `kind = 'contract'`, `days_remaining = 45`, and the deep-link ending with `filter[contract_status]=expiring_soon`.
  - `test('dashboard does not surface contracts more than 60 days out')` — seed 1 active contract with `end_date = today + 120d`; assert zero contract rows in alerts.
  - `test('dashboard does not surface inactive contracts in alerts')` — seed 1 inactive contract with `end_date = today - 3d`; assert zero contract rows in alerts.
  - `test('dashboard sorts contract alerts into the 10-row cap alongside vehicles and drivers')` — seed a mix; assert the panel is capped at 10 and sorted by `days_remaining` ascending regardless of `kind`.

- [x] **Task T6 (Dusk, UI regression)**: Create `tests/Browser/ContractsIndexAndShowTest.php` with four scenarios in a single consolidated file (mirroring `VehiclesIndexAndShowTest.php`, `DriversIndexAndShowTest.php`, and `ThirdPartiesIndexAndShowTest.php`):

  1. **`contracts index renders the table with Spanish headers and the four-state filter`** — admin loads `/contracts`, asserts table headers (Número, Cliente, Objeto, Vigencia, Estado), no error banners; applies `contract_status = vencido` and asserts only the expired rows remain with destructive row tint visible.

  2. **`contracts show page renders the five cards including recent services`** — seed a contract with 3 services, navigate to `/contracts/{id}`, assert all five Card headings are visible (Datos del Contrato, Cliente, Vigencia, Servicios Recientes), assert the ContractPeriodPill shows the expected state, assert the customer name link points to `/third-parties/{third_party_id}`.

  3. **`contracts create dialog auto-generates contract_number when is_generic is true`** — admin clicks "Crear Contrato" from the index, toggles Contrato Genérico on, asserts the contract_number Input disappears and the muted description shows, fills the rest of the form, submits, asserts the new row appears in the index with a `GEN-0001-YYYY` number.

  4. **`third-party show 'Ver contrato' link lands on the rebuilt contract show page`** — seed a customer third party with 1 contract, login as admin, visit `/third-parties/{id}`, click the contract row in the Contratos card, assert the URL matches `/contracts/{id}` AND the contract_number is visible AND the page does NOT contain "auto-generated by Blueprint" anywhere. **This pins the cross-link regression we explicitly want to fix.**

  - Use `migrate:fresh --no-interaction` in `beforeEach` (not `--seed`) and build fixtures inline — same pattern as the previous Dusk suites.
  - Take screenshots at key interaction steps for visual review.

## Verification

Verification has four layers — use all of them that apply. Playwright MCP is for *interactive* development-time checks and does **not** replace committable regression coverage.

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
2. Navigate to `/contracts`. `mcp__playwright__browser_snapshot` — verify the table renders with the expected columns AND the four-state pill renders with color-tinted rows for `vencido` + `por_vencer`.
3. Apply `Estado = vencido`. Snapshot. Verify only `vencido` rows remain and the row tint is destructive.
4. Apply `Estado = vigente`. Snapshot. Verify tint disappears for those rows.
5. Click a Número link. Snapshot the show page. Verify all five cards render in order and the "Ver tercero" link in the Cliente card navigates to `/third-parties/{id}`.
6. Click "Crear Contrato" on `/contracts`. Snapshot the modal. Toggle Contrato Genérico on, verify the contract_number Input disappears and the muted note renders. Toggle off, verify it reappears. Fill the rest of the form and submit. Verify the new row appears.
7. Navigate to `/contracts/{id}/edit` for a contract whose customer has `is_customer = false` (flip it via tinker or another path). Verify the `<ThirdPartyCombobox />` STILL shows the current customer value (the `forceInclude` branch).
8. Navigate to `/dashboard` as admin. Snapshot the "Alertas de Documentos" card. Verify contract rows appear alongside vehicles and drivers, sorted by days-remaining, AND the card subtitle reads "Documentos y contratos vencidos o por vencer.".
9. Click a contract alert row. Verify the deep-link lands on `/contracts?filter[contract_status]=vencido` (or `expiring_soon`) and the table is pre-filtered.
10. From `/third-parties/{id}` for a customer, click a row in the Contratos card. Verify the URL changes to `/contracts/{id}` and the page is the rebuilt show page (NOT a Blueprint stub).
11. Logout. Login as driver. Navigate to `/contracts` — verify a 403 page appears. Same for accounting.
12. Use `mcp__laravel-boost__browser-logs` to inspect any JS console errors during the flow.

- [x] Scenario 1: Admin sees the rebuilt index, applies the four-state filter, verifies row tinting.
- [x] Scenario 2: Admin opens the show page — all five cards render correctly.
- [x] Scenario 3: Admin creates a generic contract via the modal — the auto-gen flow works end-to-end.
- [x] Scenario 4: Admin edits a contract whose customer has `is_customer = false` — the combobox preserves the value via `forceInclude`.
- [x] Scenario 5: Admin sees the dashboard panel with contract alerts AND the updated subtitle AND the deep-link works.
- [x] Scenario 6: "Ver contrato" cross-link from `third-parties/show.tsx` lands on the rebuilt show page.
- [x] Scenario 7: Driver receives 403 on `/contracts`.
- [x] Scenario 8: Accounting receives 403 on `/contracts`.

### 2. Backend regression — Pest feature tests (required)

Tasks T1–T5 above MUST be added to `tests/Feature/Http/Controllers/ContractControllerTest.php` and `tests/Feature/Http/Controllers/DashboardControllerTest.php`. Run via `./vendor/bin/sail test --compact`. The full suite MUST stay green at **474+** tests passing (the current baseline after third-parties-crud merged).

### 3. UI regression — Laravel Dusk browser tests (required)

Task T6 above MUST be added under `tests/Browser/ContractsIndexAndShowTest.php` (single consolidated file). Each test MUST:

- Assert no `[role="alert"]`, exception trace, or visible error UI.
- Assert key Spanish strings render with correct diacritics (Contratos, Número, Cliente, Objeto, Vigencia, Estado, Datos del Contrato, Servicios Recientes, Vigente, Por vencer, Vencido, Inactivo, "Se generará automáticamente al guardar").
- Take screenshots at key interaction steps for visual review.
- Use `migrate:fresh --no-interaction` in `beforeEach` (not `--seed`) and build fixtures inline.

Run locally via `./vendor/bin/sail dusk --filter=ContractsIndexAndShowTest`. CI does not run Dusk currently, but the suite MUST run cleanly locally before merge.

### 4. API endpoints (curl)

The `/contracts` routes are Inertia routes, not a public JSON API. Auth-gate verification only:

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
  http://localhost/contracts
# Expected: 200

# Driver: should get a 403
curl -s -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"driver@sgte.app","password":"password"}' \
  -c cookies-driver.txt

curl -s -o /dev/null -w "%{http_code}\n" \
  -H "Accept: text/html" \
  -b cookies-driver.txt \
  http://localhost/contracts
# Expected: 403
```

## Dependencies

- **vehicles-crud** must be merged (it is — commit `7e66dc2`). This requirement reuses the `getRowClassName` hook, the shared `resources/js/lib/document-status.ts` module, the `Pick<T> & relations` show-page typing pattern, the modal-as-create-affordance pattern, and the four-layer verification template.
- **drivers-crud** must be merged (it is — commit `76c9fe7`). Reuses the extracted form component pattern.
- **third-parties-crud** must be merged (it is — commit `4a44b20`). Confirmed that the document-status primitives are orthogonal to entities that don't need them. Also gave us the cross-link cleanup precedent that this rebuild finishes (Contratos card on `third-parties/show.tsx`).
- **No new packages.**

## Notes

### The four-state machine and the 60-day window

This is the first rebuild in the app to track temporal state as a **date range** (start + end) rather than a single due-date. The four-state machine `vigente / por_vencer / vencido / inactivo` emerges naturally:

- `active` = the manual kill-switch. When false → `inactivo` regardless of the dates.
- `end_date` = the forward-looking signal. When `active === true` AND `end_date < today` → `vencido` (action needed).
- `end_date within 60 days` → `por_vencer` (heads-up).
- Otherwise → `vigente`.

**Why 60 days instead of 30?** Contracts are renewed, not replaced — the lead time to renegotiate, sign, and file an addendum is measurably longer than the lead time to renew a SOAT or a driver license. The business wants to see contract risks on the dashboard early enough to act. This is a soft rule; the `CONTRACT_EXPIRY_WINDOW_DAYS = 60` constant lives in one place (`document-status.ts`) with a mirroring constant in `DashboardController`, so tuning it later is a two-line edit.

### Preserving the generic auto-generation logic

`ContractController@store` has a load-bearing domain rule (lines 50–56 of the current file) that auto-generates `contract_number` as `GEN-####-YYYY` when `is_generic = true` AND the user leaves the field blank. **This is intentional and MUST be preserved.** The rebuild only replaces the Inertia pages — not the controller logic. Task T4 pins this with a dedicated regression test.

The form-side hide behavior (AC15) is a UX enhancement: hiding the Input + showing the "auto-generated" note tells the user the field is handled for them, reducing the chance they type a conflicting value. The user said in Round 2: "Hide + show auto note".

### Reusable primitives introduced

After this requirement lands, the next Blueprint rebuild (Invoices or Service-Incidents) inherits:

- **`<ThirdPartyCombobox />`** — the invoices-crud rebuild will reuse it to pick a customer (and possibly a provider, via the `role` prop) on the invoice form. The `forceInclude` prop is specifically designed for the edit-form ex-customer edge case.
- **The four-state date-range state machine** — if service-incidents or any future entity tracks a date range, the `contractPeriodStatus` helper is a template to copy.
- **The 60-day window precedent** — future dashboard alerts can pick their own window (15d / 30d / 60d / 90d) without having to rewire `buildDocumentAlerts`; the pattern of per-entity constants is now established.

### Drive-by retired

This requirement removes the cross-link regression that third-parties-crud commit `ebedc3a` left in place: the Contratos card on `third-parties/show.tsx` currently links to `/contracts/{id}` which lands on the Blueprint stub. After this rebuild it lands on the real show page.

### Out of scope, deferred

- Contract addenda / amendments (separate workflow, separate requirement).
- Contract-scoped billing summary or open-balance card (deferred to invoices-crud).
- FUEC generation from a contract (deferred to the FUEC phase).
- CSV / PDF export of the contract list.
- Bulk edit / bulk close / bulk delete.
- A per-contract "Ver servicios" deep-link to a filtered services index (the Servicios Recientes card is enough for now; a dedicated filtered view is a separate requirement if the user wants it).
- Rebuilding the remaining two Blueprint scaffolds (Invoices, Service-Incidents) — each will get its own requirement following this template.

### Estimated commit count

About **14–16 commits**:

- 1 doc commit (this requirement file).
- 2 backend commits (B1 paginate + filters + T1/T2 tests; B2 show payload + B3 create/edit payloads + T3 tests).
- 1 backend commit (B4 dashboard alerts + T5 tests).
- 1 frontend commit (F1 document-status extension).
- 2 frontend commits (F2 ContractPeriodPill; F3 ThirdPartyCombobox).
- 1 frontend commit (F4 ContractForm).
- 1 frontend commit (F5 ContractCreateDialog).
- 1 frontend commit (F6 columns).
- 1 frontend commit (F7 index rebuild + row tinting).
- 1 frontend commit (F8 show rebuild).
- 1 frontend commit (F9 create+edit rewrite bundled).
- 1 frontend commit (B5 dashboard subtitle copy change).
- 1 Dusk test commit (T6).
- 1 polish commit (Prettier + TS fixes + T4 if not folded into B1).
- 1 final docs commit (mark requirement completed).

Slightly higher than drivers-crud (13) because of the two new shared primitives (ContractPeriodPill + ThirdPartyCombobox) and the dashboard extension branch. The invoices-crud rebuild afterwards should be cheaper because it inherits both primitives.
