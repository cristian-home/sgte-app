---
name: invoices-crud
type: feat
scope: invoices
status: completed
priority: high
created_date: 2026-04-14
completed_date: 2026-04-14
srs_refs: ["REQ-007", "REQ-011"]
migration_strategy: new
---

# Rebuild the Facturas module and introduce the payment-status primitives

## Description

The `invoices` module ships with a full backend (`InvoiceController` resource methods, `InvoiceStoreRequest` / `InvoiceUpdateRequest`, the `PaymentStatus` enum, the `third_party_id` FK added by the earlier plan A1) but **all four Inertia pages** (`index.tsx`, `show.tsx`, `create.tsx`, `edit.tsx`) are Blueprint-generated stubs that render a JSON dump. Nothing in this module is production-shaped today.

This rebuild is the **fifth Blueprint pilot** (after vehicles-crud, drivers-crud, third-parties-crud, and contracts-crud). Its structural role is twofold:

1. **First rebuild under the `Facturación` sidebar group**. Every previous rebuild has been in `Gestión` (admin + operator). Invoices live under `Facturación` (admin + accounting), so this rebuild is the first to exercise the `accounting@sgte.app` role in a full CRUD Dusk flow. Regression coverage must cover admin and accounting happy paths plus a driver/operator 403.

2. **Introduces a manual-state pill** that is NOT date-derived. vehicles/drivers both track expiring documents (`DocumentStatus`), contracts computes a four-state machine from `start_date + end_date + active` (`ContractPeriodStatus`). Invoices simply have a `payment_status` enum column with three states — **Pendiente / Pagado / Vencido** — that the database already tracks. No computation is needed. The new `<PaymentStatusPill />` primitive renders the state directly; `resources/js/lib/document-status.ts` is NOT extended because "payment status" is not a document-expiry concept and mixing it in would dilute that module's meaning.

The rebuild also adds a **dedicated state-transition route** for the most common operation: marking a pending invoice as paid. Rather than overloading `PUT /invoices/{id}` with a partial `{payment_status: 'paid'}` payload, a new `POST /invoices/{id}/mark-paid` endpoint (`InvoiceController@markPaid`) handles the transition explicitly. Rationale: the audit-log diff stays clean, the intent is explicit in both the URL and the activity log, and future transitions (`mark-overdue`, `cancel`, refund) get a natural home.

Two **validation tightenings** accompany the Inertia rewrite: `third_party_id` moves from `nullable` to `required` (the current nullable rule is an accidental hole — nothing in the UI supports draft invoices without a customer), and `total_value` moves from `between:-9999999999.99,9999999999.99` to `required, numeric, min:0.01, max:9999999999.99` (negative amounts would be a separate `notas crédito` domain concept, not an invoice).

This rebuild **re-uses `<ThirdPartyCombobox />`** (the primitive introduced by contracts-crud) for both the above-the-table filter on the index and the customer picker on the invoice form. Pass `role="customer"`; pass `forceIncludeCustomer={[invoice.third_party]}` on the edit form so a customer that was later flipped off the `is_customer` axis still shows in the combobox.

**Out of scope:** the `due_date` column and its auto-overdue scheduled job (separate billing-workflow requirement); bulk "Marcar como pagadas"; PDF export; a contract-scoped billing summary; a per-third-party Facturas card on `third-parties/show.tsx` (deferred); the "assign services to invoices" workflow (the `ASSIGN_SERVICES_TO_INVOICES` permission is granted to accounting but isn't exercised by this rebuild — the Servicios Facturados card is read-only); rebuilding the remaining Blueprint scaffold (Service-Incidents — its own requirement).

## Acceptance Criteria

- [x] **AC1**: WHEN an admin or accounting user navigates to `/invoices` THEN the page renders a paginated `<DataTable>` (not a JSON dump) with columns **Número**, **Cliente**, **Fecha Emisión**, **Valor Total**, **Estado**, **Acciones**.
- [x] **AC2**: WHEN a row renders THEN the **Número** cell shows `invoice.invoice_number` in font-mono and is a `<Link>` to `/invoices/{id}`.
- [x] **AC3**: WHEN a row renders THEN the **Cliente** cell shows the computed natural/legal name of `invoice.third_party` as a `<Link>` to `/third-parties/{third_party_id}`.
- [x] **AC4**: WHEN a row renders THEN the **Fecha Emisión** cell shows `issue_date` formatted via the shared `dateFormatter` (`es-CO`, `dd/mm/yyyy`).
- [x] **AC5**: WHEN a row renders THEN the **Valor Total** cell shows `total_value` formatted via `new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })` and is right-aligned with `tabular-nums`.
- [x] **AC6**: WHEN a row renders THEN the **Estado** cell shows a `<PaymentStatusPill />` keyed on `payment_status`:
    - `pending` → **Pendiente** (secondary Badge)
    - `paid` → **Pagado** (default Badge)
    - `overdue` → **Vencido!** (destructive Badge, exclamation suffix)
- [x] **AC7**: WHEN a row's status is `overdue` THEN the row is tinted with `bg-destructive/10 hover:bg-destructive/15`; WHEN the status is `pending` THEN the row is tinted with `bg-amber-100/60 hover:bg-amber-100/80 dark:bg-amber-900/20 dark:hover:bg-amber-900/30`; WHEN the status is `paid` THEN no tint is applied. Implemented via a `getRowClassName` prop passed to `<DataTable>`.
- [x] **AC8**: WHEN the user applies the **Estado** filter with value `pending`, `paid`, or `overdue` THEN only rows matching the enum remain. Implemented as `AllowedFilter::exact('payment_status')` on `InvoiceController@index`.
- [x] **AC9**: WHEN the user picks a customer from the `<ThirdPartyCombobox role="customer" />` rendered above the table THEN only invoices whose `third_party_id` matches remain. Implemented as `AllowedFilter::exact('third_party_id')`.
- [x] **AC10**: WHEN the user clicks the **Crear Factura** action on the index THEN a `<InvoiceCreateDialog />` modal opens. The modal contains the new `<InvoiceForm />` component and, on successful submit, closes AND the index refreshes with the new row visible.
- [x] **AC11**: WHEN the user navigates to `/invoices/create` directly THEN the standalone create page renders `<InvoiceForm />` (no `idPrefix`, no modal wrapper) with a Guardar / Cancelar action bar. Cancelar returns to `/invoices`.
- [x] **AC12**: WHEN the user navigates to `/invoices/{id}/edit` THEN the edit page renders `<InvoiceForm />` with the invoice's current values pre-filled AND an Actualizar / Cancelar action bar. The `<ThirdPartyCombobox />` inside the edit form receives `forceIncludeCustomer={[invoice.third_party]}` so a customer that has been flipped to `is_customer = false` STILL shows in the option list.
- [x] **AC13**: WHEN the user submits the create or update form AND `third_party_id` is empty THEN the request fails with a validation error on `third_party_id` ("El campo cliente es obligatorio." or Laravel's default message). WHEN `total_value` is `0`, negative, or missing THEN the request fails with a validation error on `total_value`. Enforced by the tightened `InvoiceStoreRequest` + `InvoiceUpdateRequest` rules.
- [x] **AC14**: WHEN the user clicks the **Número** link in any row THEN the app navigates to `/invoices/{id}` AND the show page renders **five** Card sections in this order:
    1. **Header card** — `invoice_number` (font-mono title), customer name (description), `<PaymentStatusPill />`, Editar button.
    2. **Datos de la Factura** — Número (font-mono), Fecha de Emisión, a **big right-aligned Valor Total hero** (see AC15), and a secondary Estado row that hosts the "Marcar como pagado" action when applicable (see AC16).
    3. **Cliente** — customer computed name, `documentType.code + identification_number` in font-mono, "Ver tercero" Link to `/third-parties/{third_party_id}`.
    4. **Observaciones** — `notes` rendered with `whitespace-pre-wrap`, or the empty-state "Sin observaciones." when `notes` is null or empty.
    5. **Servicios Facturados** — a small `<Table>` with the last 5 services where `services.invoice_id = invoice.id`, ordered by `service_date` DESC, columns **Fecha** (Link to services.show), **Contrato** (Link to contracts.show, or em-dash when the service has no contract), **Vehículo** (plate), **Valor**. Empty state "Sin servicios facturados.".
- [x] **AC15**: WHEN the show page renders the Datos de la Factura card THEN the Valor Total renders as a dedicated right-aligned block with `text-3xl font-bold tabular-nums`, formatted via `Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })`. This is the visual focal point of the card — matches how invoices present on paper.
- [x] **AC16**: WHEN `invoice.payment_status === 'pending'` THEN a **"Marcar como pagado"** button appears next to the secondary Estado row on the show page. Clicking it fires `POST /invoices/{id}/mark-paid` (via Inertia `router.post`) and, on success, the page reloads with `payment_status === 'paid'` and the button disappears. WHEN `payment_status` is `paid` or `overdue` THEN the button is NOT rendered.
- [x] **AC17**: WHEN an unauthenticated user hits `POST /invoices/{id}/mark-paid` THEN the request returns 401. WHEN an authenticated user without `UPDATE_INVOICES` permission hits the endpoint THEN the request returns 403. WHEN the invoice is already `paid` or `overdue` THEN the endpoint returns 422 with a validation error explaining the invoice is not pending.
- [x] **AC18**: WHEN a service row inside the Servicios Facturados card has `unit_value` OR `quantity` null THEN the **Valor** cell renders an em-dash `—`. WHEN both are non-null THEN it renders `unit_value * quantity` formatted as COP currency.
- [x] **AC19**: WHEN an admin OR accounting user navigates to `/invoices` or `/invoices/{id}` THEN they receive 200 and can see the page. WHEN an operator OR driver OR unauthenticated user hits either route THEN they receive 401/403 (driver and operator do NOT hold `VIEW_INVOICES`).
- [x] **AC20**: WHEN an accounting user renders the index THEN the **Acciones** column shows the Editar action but NOT the Eliminar action — the existing `<Can permission={Permission.DELETE_INVOICES}>` wrapper on the `DataTableRowActions` delete button handles this. Admin users see both actions.
- [x] **AC21**: WHEN an accounting user clicks Crear Factura on the index AND submits a valid payload THEN the row appears in the index (parallel to admin's flow). WHEN accounting clicks Editar on a row THEN the edit page renders; submitting valid changes returns to the index with the row updated.
- [x] **AC22**: WHEN `npm run types` runs THEN the invoices pages contribute zero new errors (the four pages move OUT of the pre-existing deferred-Blueprint TypeScript error bucket tracked in project memory).

## Technical Specification

### Data Model

**No new tables, no new columns.** Every field this requirement needs already exists. The `third_party_id` FK was added by the earlier plan's A1 commit.

```
invoices (existing — no changes)
├── id (bigint, PK)
├── third_party_id (bigint, FK → third_parties.id, NULLABLE at DB level)
├── invoice_number (varchar, unique)
├── total_value (decimal(12,2))
├── issue_date (date)
├── payment_status (varchar, PaymentStatus enum cast)
├── notes (text, nullable)
└── created_at / updated_at / deleted_at (softDeletes)
```

The **DB column** stays nullable; only the **request-layer validation** tightens to required. This is intentional — leaving the DB column nullable avoids a migration ripple while still enforcing the business rule at the HTTP boundary.

`services.invoice_id` already exists (confirmed in `Service::$fillable` + the logOptions list).

### Enums

**No new enums.** `PaymentStatus` (`pending` / `paid` / `overdue`) is already defined with a `label()` helper returning the Spanish strings. No new cases. No new permissions.

### Routes

**One new route** for the mark-paid state transition. The remaining six routes are unchanged.

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | `/invoices` | `InvoiceController@index` | `auth, verified, can:view-invoices` | `invoices.index` |
| GET | `/invoices/create` | `InvoiceController@create` | `auth, verified, can:create-invoices` | `invoices.create` |
| POST | `/invoices` | `InvoiceController@store` | `auth, verified` | `invoices.store` |
| GET | `/invoices/{invoice}` | `InvoiceController@show` | `auth, verified, can:view-invoices` | `invoices.show` |
| GET | `/invoices/{invoice}/edit` | `InvoiceController@edit` | `auth, verified, can:update-invoices` | `invoices.edit` |
| PUT | `/invoices/{invoice}` | `InvoiceController@update` | `auth, verified` | `invoices.update` |
| DELETE | `/invoices/{invoice}` | `InvoiceController@destroy` | `auth, verified, can:delete-invoices` | `invoices.destroy` |
| **POST** | **`/invoices/{invoice}/mark-paid`** | **`InvoiceController@markPaid`** | **`auth, verified`** | **`invoices.mark-paid`** |

The `markPaid` action does its own `Gate::authorize(Permission::UPDATE_INVOICES->value)` at the top of the method, matching the pattern used by every other action in the controller (ADR-005 §2).

### Permissions

**No new permissions.** `VIEW_INVOICES`, `CREATE_INVOICES`, `UPDATE_INVOICES`, `DELETE_INVOICES` already exist.

| Role | Has | Notes |
|---|---|---|
| Admin | All four (+ dashboard, audit, etc.) | Full CRUD + delete + mark-paid |
| Accounting | VIEW / CREATE / UPDATE + ASSIGN_SERVICES_TO_INVOICES | Full CRUD **except delete**; mark-paid works via UPDATE |
| Operator | None | 403 on any `/invoices` route |
| Driver | None | 403 on any `/invoices` route |

The existing `<Can permission={Permission.DELETE_INVOICES}>` wrapper on the `DataTableRowActions` delete button already hides the delete action for accounting. No seeder changes.

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Index | `resources/js/pages/invoices/index.tsx` | **REWRITE.** `<DataTable>` + `useServerTable` + `<ThirdPartyCombobox role="customer" />` above the table + `<InvoiceCreateDialog />` modal. Passes `getRowClassName` for row tinting. |
| Show | `resources/js/pages/invoices/show.tsx` | **REWRITE.** Five Card sections (header + Datos de la Factura + Cliente + Observaciones + Servicios Facturados). Big Valor Total hero. Conditional "Marcar como pagado" action. |
| Create | `resources/js/pages/invoices/create.tsx` | **REWRITE.** Thin wrapper around `<InvoiceForm />` with a Guardar / Cancelar action bar. |
| Edit | `resources/js/pages/invoices/edit.tsx` | **REWRITE.** Thin wrapper around `<InvoiceForm />` with pre-filled `useForm` and an Actualizar / Cancelar action bar. Passes `forceIncludeCustomer={[invoice.third_party]}`. |
| Columns | `resources/js/pages/invoices/columns.tsx` | **NEW.** TanStack `ColumnDef<InvoiceRow>[]`. |
| Form | `resources/js/components/invoices/invoice-form.tsx` | **NEW.** Shared form component used by create page, edit page, and create-modal dialog. Flat single-column layout with a 2-col responsive grid. |
| Modal | `resources/js/components/invoices/invoice-create-dialog.tsx` | **NEW.** Modal wrapper around `<InvoiceForm idPrefix="dlg" />` mirroring `contract-create-dialog.tsx`. |
| Payment Pill | `resources/js/components/invoices/payment-status-pill.tsx` | **NEW.** Single Badge keyed on the enum value. Exports `paymentStatusRowTint(invoice)` for the index row tinting. |

**No shared-lib extension.** `resources/js/lib/document-status.ts` is NOT touched — payment status is a manual-state axis that doesn't belong in that module.

## Migration Strategy

`new` (formal frontmatter value), but **no migration files are written or modified**. Every column, FK, enum, and permission this requirement needs already exists. After implementing this requirement, no `php artisan migrate` invocation is required.

## Tasks

### Backend

- [x] **Task B1**: Rewrite `InvoiceController@index` to paginate, eager-load, and accept the new filters.
  - Replace the trailing `->get()` with `->paginate($request->perPage())->withQueryString()`.
  - Add eager-loads: `'thirdParty:id,document_type_id,identification_number,is_natural_person,first_name,first_lastname,company_name,is_customer,is_provider'`, `'thirdParty.documentType:id,code,name'`.
  - `allowedFilters([ 'invoice_number', AllowedFilter::exact('payment_status'), AllowedFilter::exact('third_party_id') ])`.
  - `allowedSorts(['invoice_number', 'issue_date', 'total_value', 'payment_status'])` with `defaultSort('-issue_date')`.
  - Pass `thirdParties` (customers only, with documentType eager-loaded) via a new private `customerOptions()` method parallel to `ContractController::customerOptions()`.
  - Reference convention: `ContractController@index` after contracts-crud.

- [x] **Task B2**: Expand `InvoiceController@show` to load relationships + recent services.
  - Eager-load `thirdParty.documentType`.
  - Load `recentServices` as a separate query: last 5 `Service` records where `invoice_id = $invoice->id`, ordered by `service_date` DESC, with `->with(['vehicle:id,plate', 'contract:id,contract_number'])` and a `select(['id', 'service_date', 'service_status', 'vehicle_id', 'contract_id', 'unit_value', 'quantity', 'invoice_id'])`.
  - Pass them to the Inertia page as `invoice` (full model with relations) and `recentServices`.
  - Reference convention: `ContractController@show` after contracts-crud.

- [x] **Task B3**: Expand `InvoiceController@create` and `@edit` payloads.
  - `create()`: pass `thirdParties` (customers only, with documentType eager-loaded, via `customerOptions()`).
  - `edit()`: same as `create()`, AND eager-load `$invoice->load('thirdParty.documentType')` so the edit page can build the `forceInclude` array regardless of the customer's current `is_customer` flag.

- [x] **Task B4**: Add `InvoiceController@markPaid` and the new route.
  - New method in `InvoiceController`:
    ```php
    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize(Permission::UPDATE_INVOICES->value);

        if ($invoice->payment_status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'payment_status' => 'Solo facturas pendientes pueden marcarse como pagadas.',
            ]);
        }

        $invoice->update(['payment_status' => PaymentStatus::Paid]);

        return redirect()->route('invoices.show', $invoice);
    }
    ```
  - Register the route in `routes/web.php`: `Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');` placed immediately after the `Route::resource('invoices', ...)` registration so Wayfinder picks it up.
  - Run `php artisan wayfinder:generate` (or let the Vite plugin pick it up) — the frontend calls `InvoiceController.markPaid(id)` via the generated action function.

- [x] **Task B5**: Tighten `InvoiceStoreRequest` and `InvoiceUpdateRequest` validation rules.
  - `InvoiceStoreRequest::rules()`: change `third_party_id` from `['nullable', 'integer', 'exists:third_parties,id']` to `['required', 'integer', 'exists:third_parties,id']`. Change `total_value` from `['required', 'numeric', 'between:-9999999999.99,9999999999.99']` to `['required', 'numeric', 'min:0.01', 'max:9999999999.99']`.
  - Add a custom message: `messages()` returns `['third_party_id.required' => 'El cliente es obligatorio.', 'total_value.min' => 'El valor total debe ser mayor que cero.']`.
  - `InvoiceUpdateRequest::rules()`: same tightenings (check the file — if it currently duplicates the store rules verbatim, copy the same changes; if it has its own shape, adapt).
  - Also tighten the `invoice_number` unique rule on update to ignore the current invoice id: `Rule::unique('invoices', 'invoice_number')->ignore($this->route('invoice'))`.

### Frontend — shared primitives

- [x] **Task F1**: Create `resources/js/components/invoices/payment-status-pill.tsx`.
  - Props: `{ invoice: { payment_status: string }, className?: string }`.
  - Renders a single `<Badge>` keyed on the enum value:
    - `pending` → `variant="secondary"` with text "Pendiente"
    - `paid` → `variant="default"` with text "Pagado"
    - `overdue` → `variant="destructive"` with text "Vencido!"
  - Also exports `paymentStatusRowTint(invoice): string | undefined`:
    - `overdue` → `'bg-destructive/10 hover:bg-destructive/15'`
    - `pending` → `'bg-amber-100/60 hover:bg-amber-100/80 dark:bg-amber-900/20 dark:hover:bg-amber-900/30'`
    - `paid` → `undefined` (no tint)
  - Reference convention: `contract-period-pill.tsx` (similar shape, but simpler — no date math).

### Frontend — invoices-specific

- [x] **Task F2**: Create `resources/js/components/invoices/invoice-form.tsx`.
  - Flat single-column layout with a 2-col responsive grid (`md:grid-cols-2`).
  - Props: `{ data, setData, errors, thirdParties, idPrefix?, forceIncludeCustomer? }` where `thirdParties: ThirdPartyOption[]` is the customer list from the controller.
  - Field rows in this order:
    1. 2-col row: `invoice_number` (Input, required) + `third_party_id` (`<ThirdPartyCombobox role="customer" forceInclude={forceIncludeCustomer} ...>`, required).
    2. 3-col row (`md:grid-cols-3`): `issue_date` (Input type=date, required) + `total_value` (Input type=number with a "$" prefix via `<div className="relative">`, step="0.01", min="0.01", required) + `payment_status` (Select with the 3 enum values + Spanish labels from `PaymentStatus::label()`).
    3. Full-width row: `notes` (native textarea using the same inline shadcn-style classes as `contract-form.tsx`, rows=4, optional).
  - Required-field labels carry the `<RequiredMarker />` asterisk (same convention as `contract-form.tsx`). Required fields: `invoice_number`, `third_party_id`, `issue_date`, `total_value`, `payment_status`.
  - All input ids are prefixed with `idPrefix` so the modal and standalone page can coexist.
  - Currency input: wrap the `<Input>` in a flex-row with a muted `"$"` prefix (`<span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">$</span>` + `className="pl-7"` on the Input). Reference convention: search the codebase for an existing currency input, or ship the wrapper inline inside `invoice-form.tsx`.

- [x] **Task F3**: Create `resources/js/components/invoices/invoice-create-dialog.tsx`.
  - Modal wrapper mirroring `contract-create-dialog.tsx`. Owns its own `useForm` with defaults: `invoice_number: ''`, `third_party_id: ''`, `total_value: ''`, `issue_date: ''`, `payment_status: 'pending'`, `notes: ''`.
  - Submits to `InvoiceController.store()`. On success: `reset()` + `onOpenChange(false)`.
  - Wraps `<InvoiceForm idPrefix="dlg" {...} />` inside a `<DialogContent>` sized `max-h-[calc(100vh-4rem)] flex flex-col px-0 sm:max-w-3xl`.
  - Submit button "Guardar"; cancel via `<DialogClose />`.

- [x] **Task F4**: Create `resources/js/pages/invoices/columns.tsx`.
  - Six `ColumnDef<InvoiceRow>` entries:
    1. `invoice_number` (`accessorKey`) — header "Número" (sortable), cell renders `<Link>` to `invoices.show(id).url` with `font-mono`.
    2. `cliente` (computed `id: 'cliente'`) — header "Cliente", cell renders the third-party computed name inside a `<Link>` to `/third-parties/{third_party_id}`. Em-dash when `third_party` is null.
    3. `issue_date` (`accessorKey`) — header "Fecha Emisión" (sortable), cell renders `formatDate(issue_date)` via the shared `dateFormatter`.
    4. `total_value` (`accessorKey`) — header "Valor Total" (sortable), right-aligned header, cell right-aligned with `tabular-nums` and `currencyFormatter.format(Number(row.original.total_value))` where `currencyFormatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })` defined at the top of the file.
    5. `estado` (computed `id: 'estado'`) — header "Estado", cell renders `<PaymentStatusPill invoice={row.original} />`.
    6. `actions` — `<DataTableRowActions editUrl={invoices.edit(id).url} onDelete={...} />` wrapped in `<Can permission={Permission.DELETE_INVOICES}>`.
  - Define a local `type InvoiceRow = Invoice & { third_party?: ThirdParty & { document_type?: DocumentType | null } | null }` using the `Pick<T> & relations` convention.

- [x] **Task F5**: Rewrite `resources/js/pages/invoices/index.tsx`.
  - Replace the Blueprint body with the services/vehicles/drivers/third-parties/contracts index pattern.
  - Define `invoiceFilters: FilterDefinition[]`:
    - `payment_status` → "Estado" with options `pending / paid / overdue` (labels "Pendiente" / "Pagado" / "Vencido").
  - Render `<ThirdPartyCombobox role="customer" />` above the table wired to the `third_party_id` filter (mirrors `contracts/index.tsx`).
  - Wire `<InvoiceCreateDialog />` to the "Crear Factura" button via `useState`.
  - Pass `getRowClassName={(row) => paymentStatusRowTint(row.original)}` to `<DataTable>`.
  - Type the page props as `{ invoices: PaginatedData<InvoiceRow>, thirdParties: ThirdPartyOption[] }`.
  - Reference convention: `resources/js/pages/contracts/index.tsx`.

- [x] **Task F6**: Rewrite `resources/js/pages/invoices/show.tsx`.
  - **Five Card sections** in the order listed in AC14:
    1. Header card (font-mono title + customer description + `<PaymentStatusPill />` + Editar button).
    2. Datos de la Factura with the big right-aligned Valor Total hero (`text-3xl font-bold tabular-nums`) and the secondary Estado row hosting the "Marcar como pagado" button when `payment_status === 'pending'`.
    3. Cliente (name, `documentType.code + identification_number`, "Ver tercero" Link).
    4. Observaciones (`whitespace-pre-wrap` paragraph or "Sin observaciones." empty state).
    5. Servicios Facturados (inline `<Table>` with 4 columns, em-dash for missing Valor per AC18).
  - The mark-paid action fires `router.post(InvoiceController.markPaid(invoice.id).url)` with `preserveScroll: true`, relying on the redirect back to `show` to refresh the state.
  - Type the page props as `{ invoice: ShowInvoice, recentServices: RecentServiceRow[] }` using the `Pick<T> & relations` pattern.
  - Breadcrumbs: `[{ title: 'Facturas', href: invoices.index().url }, { title: invoice.invoice_number, href: '#' }]`.
  - Reference convention: `resources/js/pages/contracts/show.tsx`.

- [x] **Task F7**: Rewrite `resources/js/pages/invoices/create.tsx`.
  - `useForm` with default-empty values (`payment_status: 'pending'` by default).
  - Render `<InvoiceForm {...} thirdParties={thirdParties} />` with a Guardar / Cancelar action bar.
  - Type props as `{ thirdParties: ThirdPartyOption[] }`.

- [x] **Task F8**: Rewrite `resources/js/pages/invoices/edit.tsx`.
  - `useForm` pre-filled from the `invoice` prop (convert `total_value` decimal string to a form-friendly string, use `parseDueDate` + `toDateInput` helper parallel to `contracts/edit.tsx`).
  - Render `<InvoiceForm {...} thirdParties={thirdParties} forceIncludeCustomer={invoice.third_party ? [invoice.third_party] : []} />` with an Actualizar / Cancelar action bar.
  - Breadcrumbs: `[{ title: 'Facturas', href: ... }, { title: invoice.invoice_number, href: show }, { title: 'Editar', href: edit }]`.

### Tests

- [x] **Task T1 (Pest, backend — index + filters)**: Add to `tests/Feature/Http/Controllers/InvoiceControllerTest.php`:
  - `test('index returns paginated payload with third-party relations')` — seed 3 invoices with mixed customers; assert `invoices.data` is array, `per_page`, `current_page`, `total` exist, each row has `third_party.document_type` loaded.
  - `test('index passes customer options for the create modal and the combobox filter')` — assert the `thirdParties` prop contains only `is_customer = true` entries, each with `document_type` loaded.
  - `test('index filters by payment_status = pending / paid / overdue')` — seed 3 invoices (one per state); apply each filter; assert only the matching row remains.
  - `test('index filters by third_party_id exact')` — seed 2 invoices with different customers; apply filter; assert only one row remains.
  - `test('index defaults to -issue_date sort')` — seed 3 invoices with different dates; assert the latest issue_date is first in the default payload.

- [x] **Task T2 (Pest, backend — show)**:
  - `test('show returns invoice with thirdParty.documentType loaded')` — assert `invoice.third_party.document_type.code` is present.
  - `test('show returns recent services ordered by service_date desc')` — seed an invoice with 7 services; assert `recentServices` length is 5 AND the first row has the latest `service_date`.
  - `test('show returns empty recent services when the invoice has none')` — assert the array is empty.

- [x] **Task T3 (Pest, backend — store + update validation)**:
  - `test('store rejects null third_party_id')` — submit without `third_party_id`; assert 422 with a validation error.
  - `test('store rejects total_value <= 0')` — submit with `total_value: 0`; assert 422. Same with `total_value: -100`.
  - `test('store accepts total_value = 0.01')` — minimum edge case, assert 201.
  - `test('update rejects null third_party_id')` — parallel regression.
  - `test('update allows keeping the same invoice_number')` — regression for the `Rule::unique->ignore()` change.

- [x] **Task T4 (Pest, backend — markPaid endpoint)**:
  - `test('markPaid transitions pending invoices to paid')` — seed a pending invoice; hit `POST /invoices/{id}/mark-paid`; assert 302 + DB row updated + activity log entry created.
  - `test('markPaid rejects already-paid invoices with 422')` — seed a paid invoice; hit the endpoint; assert 422 + validation error under `payment_status`.
  - `test('markPaid rejects overdue invoices with 422')` — same as above for overdue.
  - `test('markPaid returns 403 for operator and driver')` — seed both; hit the endpoint; assert 403.
  - `test('markPaid returns 403 for accounting is WRONG')` — accounting HAS `UPDATE_INVOICES`, so mark-paid works for them. Pin this with a positive test: `test('accounting can mark invoices as paid')`.

- [x] **Task T5 (Pest, backend — authorization 403s)**:
  - `test('operator cannot view invoices')` — assert 403 on index + show.
  - `test('driver cannot view invoices')` — assert 403 on index + show.
  - `test('accounting cannot delete invoices')` — assert 403 on DELETE.

- [x] **Task T6 (Dusk, UI regression)**: Create `tests/Browser/InvoicesIndexAndShowTest.php` with four scenarios in a single consolidated file:

  1. **`invoices index renders the table with Spanish headers, overdue filter, and row tint`** — super-admin loads `/invoices`, asserts table headers (Número, Cliente, Fecha Emisión, Valor Total, Estado); applies `payment_status=overdue`; asserts only the overdue rows remain; asserts the currency is formatted with the `$` symbol and thousands separator.

  2. **`invoices show page renders the five cards and mark-paid transitions state`** — seed a pending invoice with 2 services, navigate to `/invoices/{id}`, assert all five Card headings are visible (Datos de la Factura, Cliente, Observaciones, Servicios Facturados), assert the big Valor Total number, click "Marcar como pagado", wait for the redirect, assert the button is gone AND the pill reads "Pagado".

  3. **`accounting user can CRUD invoices`** — login as `accounting@sgte.app` (password `password`); navigate to `/invoices`; assert the page loads; click Crear Factura; fill the form (seed a customer first); submit; assert the row appears; click the Editar action on the new row; change `notes`; submit; assert the change persists on the show page. **Assert the Eliminar action is NOT visible in the row actions menu for accounting.** This is the new-role coverage.

  4. **`operator receives 403 on /invoices`** — login as `operator@sgte.app`, visit `/invoices`, assert the 403 page OR the app-layout empty state (whichever the project renders for unauthorized users — check `AppLayout` behavior when the Inertia middleware short-circuits on authorize()).

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

1. Login as admin, navigate to `/invoices`. `browser_snapshot`. Verify headers + row tinting + currency formatting.
2. Apply `Estado = Vencido` filter. Verify only overdue rows remain.
3. Click a Número link. Snapshot the show page. Verify all five cards and the big Valor Total hero.
4. Click "Marcar como pagado" on a pending invoice. Verify the pill transitions to Pagado and the button disappears.
5. Click "Ver tercero" in the Cliente card. Verify the cross-link lands on the rebuilt third-party show page.
6. Logout. Login as accounting. Navigate to `/invoices`. Verify the page loads and the Acciones column has no Eliminar button.
7. Create a new invoice as accounting. Verify the row appears in the index.
8. Logout. Login as operator. Navigate to `/invoices`. Verify 403.
9. `mcp__laravel-boost__browser-logs` for any JS console errors during the flow.

- [x] Scenario 1: Admin sees the rebuilt index with formatting and row tint
- [x] Scenario 2: Admin applies the overdue filter
- [x] Scenario 3: Admin opens the show page — all five cards render
- [x] Scenario 4: Mark-paid transitions state
- [x] Scenario 5: Cross-link to third-party show works
- [x] Scenario 6: Accounting user can access and Edit but not Delete
- [x] Scenario 7: Accounting user can create a new invoice
- [x] Scenario 8: Operator receives 403

### 2. Backend regression — Pest feature tests (required)

Tasks T1–T5 above MUST be added to `tests/Feature/Http/Controllers/InvoiceControllerTest.php`. Run via `./vendor/bin/sail test --compact`. The full suite MUST stay green at **493+** tests passing (the current baseline after contracts-crud merged).

### 3. UI regression — Laravel Dusk browser tests (required)

Task T6 above MUST be added under `tests/Browser/InvoicesIndexAndShowTest.php`. Each test MUST:

- Assert no `[role="alert"]`, exception trace, or visible error UI.
- Assert key Spanish strings render with correct diacritics (Facturas, Número, Cliente, Fecha Emisión, Valor Total, Estado, Datos de la Factura, Servicios Facturados, Observaciones, Pendiente, Pagado, Vencido, "Marcar como pagado").
- Take screenshots at key interaction steps.
- Use `migrate:fresh --no-interaction` and build fixtures inline.

Run locally via `./vendor/bin/sail dusk --filter=InvoicesIndexAndShowTest`.

### 4. API endpoints (curl)

The `/invoices` routes are Inertia routes, not a public JSON API. Auth-gate verification only:

```bash
# Admin: 200 on index
curl -s -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@sgte.app","password":"password"}' \
  -c cookies-admin.txt

curl -s -o /dev/null -w "%{http_code}\n" \
  -H "Accept: text/html" \
  -b cookies-admin.txt \
  http://localhost/invoices
# Expected: 200

# Accounting: 200 on index (new role coverage)
curl -s -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"accounting@sgte.app","password":"password"}' \
  -c cookies-accounting.txt

curl -s -o /dev/null -w "%{http_code}\n" \
  -H "Accept: text/html" \
  -b cookies-accounting.txt \
  http://localhost/invoices
# Expected: 200

# Operator: 403
curl -s -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"operator@sgte.app","password":"password"}' \
  -c cookies-operator.txt

curl -s -o /dev/null -w "%{http_code}\n" \
  -H "Accept: text/html" \
  -b cookies-operator.txt \
  http://localhost/invoices
# Expected: 403
```

## Dependencies

- **vehicles-crud** (merged, commit `7e66dc2`) — reuses `getRowClassName`, `Pick<T> & relations`, modal-as-create-affordance, four-layer verification.
- **drivers-crud** (merged, commit `76c9fe7`) — form-extraction pattern.
- **third-parties-crud** (merged, commit `4a44b20`) — confirmed the orthogonality of document-status primitives; `<ThirdPartyCombobox>` was introduced for this axis.
- **contracts-crud** (merged, commit `05a76cf` via `e41196b`) — **direct prerequisite**. Introduced `<ThirdPartyCombobox />` (with `role` + `forceInclude` props) and established the Pill + row-tint convention this rebuild mirrors.
- **No new packages.**

## Notes

### Why `<PaymentStatusPill />` lives in `components/invoices/` and not in `lib/document-status.ts`

vehicles/drivers/contracts all derive their status from a **date axis** — `documentStatus()` and `contractPeriodStatus()` take dates + "today" and compute a state. Invoices don't. Their status is a column value that was set explicitly by a human (create/edit form, mark-paid action) or a future scheduled job (mark-overdue). Putting a pure passthrough mapper inside `document-status.ts` would dilute the module's meaning ("this file is about date-derived status") without any code-reuse benefit. The pill component ships alongside the invoices feature folder instead.

### Why a dedicated `/mark-paid` route instead of overloading `update()`

Three reasons:

1. **Intent is explicit.** The URL and the controller method name both say what's happening. `PUT /invoices/{id}` with `{payment_status: 'paid'}` would be indistinguishable from a routine notes edit in the route log.
2. **Audit-log diff stays clean.** `spatie/laravel-activitylog` captures all changed attributes on update. When mark-paid runs via `update()`, the diff contains just `{payment_status: pending → paid}`. When it runs via a dedicated method with the pre-check, it's identical — BUT the subject and causer still get their own row. The benefit shows up when future transitions (`mark-overdue`, cancel, refund) also need to be distinguishable in the audit log.
3. **Guard against illegal transitions.** The dedicated method can assert `payment_status === Pending` and throw a 422 if not. Overloading `update()` would either duplicate that guard inside `InvoiceUpdateRequest` (awkward) or let a paid invoice be marked paid again silently.

The marginal cost is one new method + one new route + one new feature test. It's worth it.

### Why tighten the validation rules as part of the rebuild

`third_party_id` and `total_value` have surprising nullable/negative rules in the current `InvoiceStoreRequest`. The Blueprint scaffold never exposed a form that would exercise them, so the holes went unnoticed. Tightening them in the same commit as the rebuild:

- Avoids shipping a rebuild that silently accepts null customers or zero-value invoices.
- Keeps the rule-tightening changelog on the same branch as the reason for the tightening (the UI that actually talks to the rules).
- Leaves the DB column `nullable` (no migration ripple) — the rule lives at the HTTP boundary where it can be relaxed later if draft-invoice semantics ever get added back.

### Accounting role — first-class CRUD regression coverage

Every previous Blueprint rebuild tested admin + operator + driver. This is the first one that puts accounting in the hot path. The Dusk scenario walks the full create → edit flow as accounting, and explicitly asserts:

- The Eliminar action is NOT in the row menu (accounting lacks `DELETE_INVOICES`).
- The Crear Factura button IS present (accounting has `CREATE_INVOICES`).
- The "Marcar como pagado" button works (accounting has `UPDATE_INVOICES`).

Any future CRUD rebuild under `Facturación` (or future billing features) can reuse this Dusk file as the pattern for accounting-role coverage.

### Servicios Facturados card — read-only in this rebuild

The `ASSIGN_SERVICES_TO_INVOICES` permission is granted to the accounting role in the catalog seeder. This hints at a future "assign services to this invoice" workflow (drag from an unassigned services list, link the invoice_id FK). This rebuild **does NOT implement that workflow** — the Servicios Facturados card is a read-only preview of the last 5 services already linked. Adding the assignment UI is a separate requirement (likely bundled with the Phase 4 billing workflow).

### Out of scope, deferred

- `due_date` column + auto-overdue scheduled job (separate billing-workflow requirement).
- Bulk "Marcar como pagadas" action.
- PDF export of an invoice.
- The Facturas card on `third-parties/show.tsx` (deferred to keep scope focused — will probably land when the invoices assignment workflow is built).
- The assign-services-to-invoices workflow itself.
- Contract → invoice navigation (no explicit "View invoices" link from the contract show page).
- Rebuilding the last Blueprint scaffold (Service-Incidents — its own requirement).

### Estimated commit count

About **14–16 commits**:

- 1 doc commit (this requirement file).
- 2 backend commits (B1+B3 paginate + payloads + T1 tests; B2 show + T2 tests).
- 1 backend commit (B5 validation tightenings + T3 tests).
- 1 backend commit (B4 markPaid route + T4 tests).
- 1 frontend commit (F1 PaymentStatusPill).
- 1 frontend commit (F2 InvoiceForm).
- 1 frontend commit (F3 InvoiceCreateDialog).
- 1 frontend commit (F4 columns.tsx).
- 1 frontend commit (F5 index rebuild + row tinting).
- 1 frontend commit (F6 show rebuild + mark-paid action wiring).
- 1 frontend commit (F7+F8 create + edit bundled).
- 1 Dusk test commit (T6).
- 1 polish commit (Prettier + any TS fixes + T5 authorization tests).
- 1 final docs commit (mark requirement completed).

Slightly higher than contracts-crud (13) because of the new `/mark-paid` endpoint + its tests, and the first-time accounting Dusk coverage. The Service-Incidents rebuild afterwards should be cheaper — no new shared primitives, existing patterns, small schema.
