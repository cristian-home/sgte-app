---
name: invoice-service-assignment
type: feat
scope: invoices
status: completed
priority: high
created_date: 2026-04-18
completed_date: 2026-04-18
srs_refs: ["REQ-011"]
migration_strategy: new
---

# Assign services to invoices + incident-aware total computation

## Description

Invoices and services share an `invoice_id` FK (`services.invoice_id`, nullable), but today there is no UI or controller surface to actually populate it. Invoices are created with a manual `total_value`, and the relationship between an invoice and the services it bills is conceptually implied but operationally disconnected. REQ-011 defines the billing workflow:

> **REQ-011 AC1.** WHEN a service is closed THEN the system SHALL allow linking an invoice number to the service.
>
> **REQ-011 AC4.** WHEN an incident affects invoicing THEN the system SHALL calculate the corresponding additional amount or discount.

The `ASSIGN_SERVICES_TO_INVOICES` permission (`app/Enums/Permission.php:58`, Spanish label "Asociar servicios a facturas") has been defined since phase-setup and granted to `accounting` in the seeder, but is NOT checked by any route, controller, or gate anywhere in the codebase. This requirement is the first that actually exercises it.

Scope: four interconnected deliverables.

1. **A modal service picker** launched from the invoices show page that lets admin + accounting select multiple candidate services and attach them to the invoice in one request. Candidates are filtered server-side to the invoice's customer (`service.contract.third_party_id === invoice.third_party_id`), unbilled (`invoice_id IS NULL`), closed (`service_status = 'closed'`), and within the last 90 days.
2. **A detach action** on each row of the existing "Servicios Facturados" card (added in invoices-crud commit `703420d`), guarded by a shadcn `<AlertDialog>` confirmation. On confirm, the service's `invoice_id` is nulled and the invoice total recomputes.
3. **Automatic `total_value` computation** driven by a new `App\Services\InvoiceTotalCalculator` service class that is the single source of truth for the computation: `sum(service.unit_value * service.quantity) + sum(service_incidents.additional_value where affects_billing=true)`. Called by all three new endpoints.
4. **A `total_value` lock**: once any service is attached, the manual input on the invoice form becomes read-only with a muted "(calculado automáticamente)" note, and a new validation rule rejects any `total_value` the user tries to submit via PUT. A stale-total detection pill ("Total desactualizado — Recalcular") appears on the show page when `total_value !== computed_total` (covers the case where a linked service's `unit_value`/`quantity` or a linked incident's `affects_billing`/`additional_value` changes after attach-time).

**Out of scope:** a dedicated `/billing/pending-services` operational screen (separate requirement if accounting wants a shop-floor view); PDF invoice generation (separate requirement per the Phase 4 plan); REQ-009 accounting-immutability justification UX (separate requirement); services-side "Asignar a Factura" action on `services/show.tsx` (defer to keep scope invoice-centric); automatic recompute triggers on Service / ServiceIncident model save (deferred — the manual Recalcular button + stale pill handle this for now). Existing manual invoices without attached services are UNTOUCHED.

## Acceptance Criteria

- [x] **AC1**: WHEN an admin or accounting user navigates to `/invoices/{id}` AND the invoice has zero attached services THEN the page renders an **"Asignar Servicios"** button on the header card action row. WHEN the user lacks `ASSIGN_SERVICES_TO_INVOICES` THEN the button is NOT rendered (gated via `<Can permission={Permission.ASSIGN_SERVICES_TO_INVOICES}>`).
- [x] **AC2**: WHEN the user clicks "Asignar Servicios" THEN a `<ServicePickerDialog />` modal opens, displaying a multi-select table with columns **(checkbox)**, **Fecha**, **Vehículo**, **Conductor**, **Contrato**, **Valor estimado**, **Novedades**.
- [x] **AC3**: WHEN the picker renders THEN only services matching ALL of these conditions appear: `service.contract.third_party_id === invoice.third_party_id` AND `invoice_id IS NULL` AND `service_status === 'closed'` AND `service_date >= today - 90 days`.
- [x] **AC4**: WHEN the picker has services listed THEN the user CAN search by plate / contract number / driver name (client-side filter on the loaded list).
- [x] **AC5**: WHEN the user selects one or more services and clicks "Asignar" THEN the app fires `POST /invoices/{invoice}/services` with a `service_ids: [1,2,3]` payload AND on success the dialog closes AND the page refreshes with the attached services visible in the Servicios Facturados card AND the Valor Total hero reflects the new computed total.
- [x] **AC6**: WHEN the attach request includes a `service_id` whose `service.contract.third_party_id !== invoice.third_party_id` THEN the request fails with 422 AND the response error attaches to `service_ids` with message "Los servicios deben pertenecer al cliente de la factura.".
- [x] **AC7**: WHEN the attach request includes a `service_id` whose `service.invoice_id` is already set to a DIFFERENT invoice id THEN the request fails with 422 AND the response error message "Uno o más servicios ya están asociados a otra factura.". WHEN the `service_id` is already attached to the SAME invoice (idempotency case) THEN the request succeeds and the service's `invoice_id` stays unchanged.
- [x] **AC8**: WHEN the attach request includes a `service_id` whose `service_status !== 'closed'` THEN the request fails with 422 with message "Solo servicios cerrados pueden facturarse.".
- [x] **AC9**: WHEN the attach request's `service_ids` array is empty OR missing THEN the request fails with 422 on `service_ids`.
- [x] **AC10**: WHEN an admin or accounting user navigates to `/invoices/{id}` AND the invoice has AT LEAST ONE attached service THEN each row in the **Servicios Facturados** card gains a trailing `<Button variant="destructive" size="icon">` with a `Trash2` icon (visible only to users with `ASSIGN_SERVICES_TO_INVOICES`).
- [x] **AC11**: WHEN the user clicks the Trash2 icon THEN a shadcn `<AlertDialog>` opens with title "¿Desvincular servicio?" and description "Esta acción quitará el servicio de la factura y recalculará el valor total.", Cancelar / Confirmar buttons. WHEN the user clicks Confirmar THEN the app fires `DELETE /invoices/{invoice}/services/{service}` AND on success the row disappears AND the Valor Total hero updates AND, if this was the last attached service, the "(calculado automáticamente)" subtitle disappears and the total becomes manually editable again.
- [x] **AC12**: WHEN the invoice show page loads AND `total_value !== computed_total` THEN a small amber pill **"Total desactualizado"** renders next to the Valor Total hero with an adjacent **"Recalcular"** button. The controller passes `computed_total` alongside `invoice` in the show payload; drift detection runs client-side (both values formatted identically for comparison).
- [x] **AC13**: WHEN the user clicks **"Recalcular"** THEN the app fires `POST /invoices/{invoice}/recompute-total` AND on success the page refreshes AND the pill disappears AND the Valor Total matches `computed_total`.
- [x] **AC14**: WHEN `invoice.services_count > 0` THEN the Valor Total hero on the show page has a muted subtitle **"(calculado automáticamente)"** beneath the number.
- [x] **AC15**: WHEN the user navigates to `/invoices/{id}/edit` AND `invoice.services_count > 0` THEN the `total_value` Input is rendered with `readOnly` + muted and a note reads "(calculado automáticamente — hay {N} servicios asociados)".
- [x] **AC16**: WHEN the user submits `PUT /invoices/{invoice}` with any `total_value` value AND the invoice has at least one attached service THEN the request fails with 422 on `total_value` with message "El valor total se calcula automáticamente cuando hay servicios asociados.".
- [x] **AC17**: WHEN the invoice has zero attached services THEN the `total_value` field is fully editable on the edit form AND `PUT /invoices/{invoice}` accepts the field normally (preserves current behaviour for existing manual-total invoices).
- [x] **AC18**: WHEN `App\Services\InvoiceTotalCalculator::recomputeFor($invoice)` is called THEN it computes `sum(service.unit_value * service.quantity) + sum(service_incident.additional_value)` where incidents are filtered by `service_id IN attached_services AND affects_billing = true` AND overwrites `invoice.total_value` AND persists the change.
- [x] **AC19**: WHEN any of the three endpoints (attach / detach / recompute) runs THEN it calls `InvoiceTotalCalculator::recomputeFor($invoice)` as its single path to the computation. The calculator is the single source of truth.
- [x] **AC20**: WHEN an unauthenticated user hits any of the three new routes THEN the response is 401. WHEN an authenticated user without `ASSIGN_SERVICES_TO_INVOICES` (operator, driver) hits them THEN the response is 403.
- [x] **AC21**: WHEN accounting user (who has `ASSIGN_SERVICES_TO_INVOICES`) hits any of the three routes THEN the request succeeds, pinning the seeder's grant of the permission to that role.
- [x] **AC22**: WHEN `npm run types` runs THEN the invoices pages contribute zero new errors (the four invoices pages already moved OUT of the pre-existing deferred-Blueprint bucket after invoices-crud).

## Technical Specification

### Data Model

**No new tables, no new columns.** The `services.invoice_id` FK already exists (`$fillable` list in `app/Models/Service.php`) and is nullable. This requirement is a behavior layer on top.

```
services (existing — no changes)
├── id (bigint, PK)
├── contract_id (bigint, FK → contracts.id)           # used to derive customer
├── invoice_id (bigint, FK → invoices.id, nullable)   # the attach/detach target
├── service_status (varchar, ServiceStatus enum)      # must be 'closed' to attach
├── unit_value (decimal(12,2), nullable)              # used in total computation
├── quantity (integer, nullable)                      # used in total computation
└── ... (other fields)

service_incidents (existing — no changes)
├── service_id (bigint, FK → services.id)
├── affects_billing (boolean)                          # filter for incident addend
├── additional_value (decimal(12,2), nullable)         # added to total when affects_billing
└── ...

invoices (existing — no changes)
├── third_party_id (bigint, FK → third_parties.id)   # matched against service.contract.third_party_id
├── total_value (decimal(12,2))                       # overwritten by calculator when services attached
└── ...
```

### Enums

**No new enums.** `ASSIGN_SERVICES_TO_INVOICES` permission already exists; `ServiceStatus::Closed` already exists.

### Routes

**Three new routes**, registered immediately after the existing `Route::post('invoices/{invoice}/mark-paid', ...)` line in `routes/web.php` (and before the `Route::resource('invoices', ...)`):

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| POST | `/invoices/{invoice}/services` | `InvoiceController@attachServices` | `auth, verified, can:invoices.assign-services` | `invoices.services.attach` |
| DELETE | `/invoices/{invoice}/services/{service}` | `InvoiceController@detachService` | `auth, verified, can:invoices.assign-services` | `invoices.services.detach` |
| POST | `/invoices/{invoice}/recompute-total` | `InvoiceController@recomputeTotal` | `auth, verified, can:invoices.assign-services` | `invoices.recompute-total` |

Additionally, a new internal controller method `InvoiceController@candidateServices($invoice)` is NOT exposed as a route — instead, the candidate list is passed as a prop on the `show` payload (see Pages below) to keep the picker's initial render hydrated in one trip.

Authorization additionally guarded at the action level with `Gate::authorize(Permission::ASSIGN_SERVICES_TO_INVOICES->value)` matching the pattern across the controller.

### Permissions

**No new permissions.** `ASSIGN_SERVICES_TO_INVOICES = 'invoices.assign-services'` already exists in `app/Enums/Permission.php:58`.

| Role | Has | Notes |
|---|---|---|
| Super Admin | yes (via `Gate::before`) | Full access |
| Admin | yes (in seeder) | Full access |
| Accounting | yes (in seeder) | Full access — this is a core accounting workflow |
| Operator | no | 403 on attach/detach/recompute; UI omits the buttons |
| Driver | no | 403 (not that they ever touch `/invoices`) |

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Invoice show | `resources/js/pages/invoices/show.tsx` | **EXTEND.** Add "Asignar Servicios" button to the header card action row (gated). Add per-row Trash2 button + `<AlertDialog>` confirmation on the Servicios Facturados card. Add amber stale-total pill + "Recalcular" button next to the Valor Total hero. Add "(calculado automáticamente)" subtitle when `services_count > 0`. |
| Invoice edit | `resources/js/pages/invoices/edit.tsx` | **EXTEND.** Pass `services_count` to `<InvoiceForm />`; render `total_value` Input as `readOnly` with "(calculado automáticamente)" note when count > 0. |
| Invoice form | `resources/js/components/invoices/invoice-form.tsx` | **EXTEND.** New optional prop `isTotalLocked: boolean`. When true, the `total_value` Input gets `readOnly` + a muted description beneath it. |
| Service picker dialog | `resources/js/components/invoices/service-picker-dialog.tsx` | **NEW.** Modal with multi-select table. Props: `{ open, onOpenChange, invoice: { id; third_party_id }, candidates: ServicePickerRow[] }`. Uses Dialog from `components/ui/dialog.tsx`. Search input on top, Asignar / Cancelar footer buttons. Submits via `router.post`. |
| Detach confirmation | (inline in `invoices/show.tsx`) | **NEW.** `<AlertDialog>` from `components/ui/alert-dialog.tsx`, triggered by state. |

## Migration Strategy

`new` (formal frontmatter value), but **no migration files are written or modified**. All schema is already in place.

## Tasks

### Backend

- [x] **Task B1**: Create `app/Services/InvoiceTotalCalculator.php` with method `recomputeFor(Invoice $invoice): void`.
  - Eager-load attached services + their billing-affecting incidents.
  - Compute `$servicesTotal = sum(service.unit_value * service.quantity)` where both are non-null; services with either null contribute 0 to the sum.
  - Compute `$incidentsTotal = sum(service_incident.additional_value)` where `affects_billing = true` AND `service_id IN attached_service_ids` AND `additional_value` is non-null.
  - Persist via `$invoice->update(['total_value' => $servicesTotal + $incidentsTotal])`.
  - Add a unit test in `tests/Unit/InvoiceTotalCalculatorTest.php` covering: empty services list → 0; services-only; services + one billing incident; services + one non-billing incident (should NOT be included); null unit_value / null quantity / null additional_value edge cases.

- [x] **Task B2**: Create `app/Rules/TotalValueLockedWhenServicesAttached.php`.
  - `ValidationRule` implementing `validate($attribute, $value, $fail)`.
  - Reads `$this->route('invoice')` (via a constructor-injected invoice, OR the rule receives the Invoice as a parameter — prefer explicit constructor injection `new TotalValueLockedWhenServicesAttached($invoice)`).
  - If `$invoice->services()->count() > 0` AND the incoming `total_value` differs from the current `$invoice->total_value` (to allow passthrough on untouched form submits) → `$fail('El valor total se calcula automáticamente cuando hay servicios asociados.')`.
  - Reference convention: `app/Rules/ServiceBelongsToAuthenticatedDriver.php`.

- [x] **Task B3**: Wire the rule into `InvoiceUpdateRequest::rules()`.
  - Inside `rules()`, instantiate the rule with the current invoice resolved from `$this->route('invoice')`.
  - Append to the existing `total_value` rule array: `new TotalValueLockedWhenServicesAttached($this->route('invoice'))`.
  - Regression test: `tests/Feature/Http/Controllers/InvoiceControllerTest.php` → new test `test('update rejects total_value when services are attached')`.

- [x] **Task B4**: Create `app/Http/Requests/InvoiceServiceAttachRequest.php`.
  - `authorize()`: `Gate::allows(Permission::ASSIGN_SERVICES_TO_INVOICES->value)`.
  - `rules()`:
    - `service_ids` → `['required', 'array', 'min:1']`.
    - `service_ids.*` → `['integer', 'exists:services,id']`.
  - `after()` hook (Laravel 12 FormRequest `after()`):
    - Load the invoice from `$this->route('invoice')` and the incoming services.
    - For each service: ensure `service.contract.third_party_id === invoice.third_party_id` → else add error to `service_ids`: "Los servicios deben pertenecer al cliente de la factura.".
    - For each service: ensure `service.invoice_id IS NULL OR service.invoice_id === invoice.id` → else add error: "Uno o más servicios ya están asociados a otra factura.".
    - For each service: ensure `service.service_status === ServiceStatus::Closed` → else add error: "Solo servicios cerrados pueden facturarse.".

- [x] **Task B5**: Add three controller actions to `app/Http/Controllers/InvoiceController.php`.
  - `attachServices(InvoiceServiceAttachRequest $request, Invoice $invoice, InvoiceTotalCalculator $calculator): RedirectResponse`:
    - `Gate::authorize(Permission::ASSIGN_SERVICES_TO_INVOICES->value);`
    - Inside a DB transaction: `Service::whereIn('id', $validated['service_ids'])->update(['invoice_id' => $invoice->id]);`
    - `$calculator->recomputeFor($invoice->fresh());`
    - Redirect back to `invoices.show` with flash `success` "{count} servicio(s) asociado(s).".
  - `detachService(Request $request, Invoice $invoice, Service $service, InvoiceTotalCalculator $calculator): RedirectResponse`:
    - `Gate::authorize(Permission::ASSIGN_SERVICES_TO_INVOICES->value);`
    - Guard: if `$service->invoice_id !== $invoice->id` → `abort(404)` (defensive; the route already binds both params).
    - `$service->update(['invoice_id' => null]);`
    - `$calculator->recomputeFor($invoice->fresh());`
    - Redirect back to `invoices.show` with flash `success` "Servicio desvinculado.".
  - `recomputeTotal(Request $request, Invoice $invoice, InvoiceTotalCalculator $calculator): RedirectResponse`:
    - `Gate::authorize(Permission::ASSIGN_SERVICES_TO_INVOICES->value);`
    - `$calculator->recomputeFor($invoice);`
    - Redirect back to `invoices.show` with flash `success` "Total recalculado.".

- [x] **Task B6**: Add private method `candidateServices(Invoice $invoice): Collection` to `InvoiceController`.
  - Select from `services` WHERE `invoice_id IS NULL` AND `service_status = 'closed'` AND `service_date >= today - 90 days` AND `contract.third_party_id = invoice.third_party_id` (via `whereHas('contract', fn ($q) => $q->where('third_party_id', $invoice->third_party_id))`).
  - Eager-load `vehicle:id,plate`, `driver:id,first_name,first_lastname`, `contract:id,contract_number`, `serviceIncidents` (with `affects_billing` + `additional_value`).
  - Order by `service_date DESC, id DESC`.
  - Return the collection with only the fields the picker needs: `id, service_date, vehicle_id, driver_id, contract_id, unit_value, quantity, service_status`.

- [x] **Task B7**: Extend `InvoiceController@show` with `computed_total`, `services_count`, and `candidateServices` props.
  - `$computedTotal = $calculator->computeFor($invoice);` (a new read-only companion method on the calculator that returns the computed value WITHOUT persisting — OR, simpler: use the existing service classes to run the compute inline in the controller).
  - **Preferred shape**: add a `computeFor(Invoice $invoice): string` method to the calculator that returns the computed total as a decimal string without side effects. `recomputeFor` internally calls `computeFor` then persists.
  - Pass `computed_total`, `services_count` (from `$invoice->services()->count()` or a `loadCount('services')`), and `candidate_services` to the Inertia response.

- [x] **Task B8**: Extend `InvoiceController@edit` with `services_count`.
  - Add `$invoice->loadCount('services')` before the Inertia render, or pass the count explicitly.
  - The edit page already receives `invoice`; the new count rides along on the model attribute (`services_count`).

- [x] **Task B9**: Register the three routes in `routes/web.php` immediately before the `Route::resource('invoices', ...)` registration.
  - All three `->middleware('can:invoices.assign-services')`.
  - Names: `invoices.services.attach`, `invoices.services.detach`, `invoices.recompute-total`.
  - Run `./vendor/bin/sail artisan wayfinder:generate` after adding the routes so the frontend has generated action functions.

### Frontend

- [x] **Task F1**: Create `resources/js/components/invoices/service-picker-dialog.tsx`.
  - Reference convention: `resources/js/components/invoices/invoice-create-dialog.tsx` for the Dialog shell + form submit pattern.
  - Props: `{ open, onOpenChange, invoiceId, candidates: ServicePickerRow[] }`.
  - Define and export `type ServicePickerRow` with the fields from B6's candidate payload plus eager-loaded relations.
  - State: `const [selectedIds, setSelectedIds] = useState<number[]>([])`; `const [search, setSearch] = useState('')`.
  - Client-side filter: filter `candidates` by plate / contract number / driver first+last name case-insensitively on `search`.
  - Table markup (shadcn `<Table>`): header row with checkbox master-toggle + Fecha + Vehículo + Conductor + Contrato + Valor estimado + Novedades. Body rows render each candidate with a per-row checkbox that toggles `selectedIds`. Empty state: "Sin servicios candidatos." muted message.
  - Valor estimado cell: `unit_value * quantity` formatted via the same `Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })` used in `invoices/show.tsx`. If either is null → em-dash.
  - Novedades cell: count badge (secondary variant) with the number of `service_incidents` where `affects_billing = true`. If zero → em-dash.
  - Footer: Cancelar (DialogClose) + Asignar button (disabled when `selectedIds.length === 0`). Asignar fires `router.post(InvoiceController.attachServices(invoiceId).url, { service_ids: selectedIds }, { onSuccess: () => { reset(); onOpenChange(false); } })`.

- [x] **Task F2**: Extend `resources/js/pages/invoices/show.tsx` with the Asignar Servicios button, stale-total pill, Recalcular button, and Quitar + AlertDialog per row.
  - Props change: accept `computed_total: string`, `services_count: number`, `candidate_services: ServicePickerRow[]` alongside the existing `invoice` + `recentServices`.
  - Header card action row: before the existing Editar button, render a `<Can permission={Permission.ASSIGN_SERVICES_TO_INVOICES}>` + `<Button onClick={() => setPickerOpen(true)}>Asignar Servicios</Button>`.
  - Render `<ServicePickerDialog open={pickerOpen} onOpenChange={setPickerOpen} invoiceId={invoice.id} candidates={candidate_services} />` at the bottom of the main container.
  - Datos de la Factura card, Valor Total hero block:
    - Below the `text-3xl font-bold tabular-nums` amount: when `services_count > 0` render `<p className="text-xs text-muted-foreground">(calculado automáticamente)</p>`.
    - Right next to the block (flex row): when `Number(invoice.total_value) !== Number(computed_total)` render an amber `<Badge variant="outline" className="border-amber-500 text-amber-700 dark:text-amber-400">Total desactualizado</Badge>` + a `<Button variant="outline" size="sm" onClick={handleRecompute}>Recalcular</Button>`. The button fires `router.post(InvoiceController.recomputeTotal(invoice.id).url, {}, { preserveScroll: true })`.
    - When `services_count > 0` but totals match, still show the Recalcular button as a quiet affordance (smaller/ghost variant, no pill). Omit completely when `services_count === 0`.
  - Servicios Facturados card: add a 6th column header "" (empty for the Quitar button). Body cell at the end renders `<Can permission={Permission.ASSIGN_SERVICES_TO_INVOICES}><Button variant="ghost" size="icon" onClick={() => setDetachingServiceId(service.id)}><Trash2 className="size-4 text-destructive" /></Button></Can>`.
  - At the bottom of the container, render an `<AlertDialog open={detachingServiceId !== null} onOpenChange={(open) => !open && setDetachingServiceId(null)}>` with title "¿Desvincular servicio?", description "Esta acción quitará el servicio de la factura y recalculará el valor total.", AlertDialogAction "Confirmar" that fires `router.delete(InvoiceController.detachService(invoice.id, detachingServiceId).url, { preserveScroll: true, onFinish: () => setDetachingServiceId(null) })`.

- [x] **Task F3**: Extend `resources/js/components/invoices/invoice-form.tsx` with `isTotalLocked` prop.
  - New optional prop `isTotalLocked: boolean` (default false) + `servicesCount: number` (default 0).
  - When `isTotalLocked` is true: the `total_value` Input gets `readOnly`, the `$` prefix stays, and a muted `<p className="text-xs text-muted-foreground">(calculado automáticamente — hay {servicesCount} servicio(s) asociado(s))</p>` renders beneath the Input.
  - When false: behavior unchanged.

- [x] **Task F4**: Extend `resources/js/pages/invoices/edit.tsx` to pass `isTotalLocked`.
  - Read `services_count` from the invoice payload (either as `invoice.services_count` from `loadCount`, or as a separate prop if the controller passes it that way — prefer the `loadCount` convention).
  - Pass `isTotalLocked={(invoice.services_count ?? 0) > 0}` and `servicesCount={invoice.services_count ?? 0}` to `<InvoiceForm />`.

### Tests

- [x] **Task T1 (Pest unit — InvoiceTotalCalculator)**: Create `tests/Unit/InvoiceTotalCalculatorTest.php`.
  - `test('recomputeFor returns zero for an invoice with no services')` — seed invoice with 0 services, call recomputeFor, assert `$invoice->total_value === '0.00'`.
  - `test('recomputeFor sums unit_value times quantity across attached services')` — seed invoice + 2 services (1000 * 2, 500 * 3) → expect 3500.
  - `test('recomputeFor adds billing-affecting incident additional_value to the sum')` — seed 1 service (1000 * 1) + 1 incident with `affects_billing=true, additional_value=250` → expect 1250.
  - `test('recomputeFor ignores non-billing-affecting incidents')` — same shape as above but incident has `affects_billing=false` → expect 1000.
  - `test('recomputeFor treats null unit_value or quantity as zero contribution')` — seed 1 service with `unit_value=null, quantity=5` plus another with full values → expect the full-value one's sum only.
  - `test('recomputeFor persists the new total to the database')` — assert `$invoice->fresh()->total_value` after the call.
  - `test('computeFor returns the same value without persisting')` — seed + call, assert the return value AND assert the DB row is unchanged.

- [x] **Task T2 (Pest feature — attach endpoint)**: Add to `tests/Feature/Http/Controllers/InvoiceControllerTest.php`:
  - `test('admin can attach multiple closed services and the total recomputes')` — seed invoice + 2 closed services belonging to the customer, POST attach with both ids, assert redirect + both services' `invoice_id === invoice.id` + `invoice.total_value` matches the computed sum.
  - `test('attach rejects services whose contract belongs to a different customer')` — seed 1 service with a different customer's contract, POST attach, assert 422 on `service_ids` with the cross-customer message.
  - `test('attach rejects services already attached to a different invoice')` — seed 1 service pre-attached to another invoice, POST attach, assert 422.
  - `test('attach is idempotent when services already belong to this invoice')` — seed 1 service already attached to `invoice.id`, POST attach with the same id, assert 302 + no state change.
  - `test('attach rejects open-status services')` — seed 1 service with `service_status = open`, POST attach, assert 422 with the closed-only message.
  - `test('attach rejects empty service_ids array')` — POST with `service_ids: []`, assert 422 on `service_ids`.

- [x] **Task T3 (Pest feature — detach endpoint)**: Add:
  - `test('admin can detach a service and the total recomputes')` — seed invoice + 2 attached services, DELETE one, assert redirect + the service's `invoice_id` is null + the total matches the remaining service's computed value.
  - `test('detach returns 404 when the service is not attached to the invoice')` — seed service with `invoice_id` null OR pointing to a different invoice, DELETE on `/invoices/{invoice}/services/{service}`, assert 404.
  - `test('detaching the last service clears the auto-compute lock')` — seed invoice with 1 attached service, detach it, assert `invoice.fresh()->services_count === 0`.

- [x] **Task T4 (Pest feature — recompute endpoint)**:
  - `test('recompute endpoint updates the total when upstream values change')` — seed invoice + 1 service (1000 * 1), attach + assert total=1000, then update the service's `unit_value` to 2000 directly on the model (bypassing any observers), hit the recompute endpoint, assert `invoice.fresh()->total_value === 2000`.
  - `test('recompute picks up new billing-affecting incidents')` — seed invoice + 1 service, attach, add an incident with `affects_billing=true, additional_value=500`, hit recompute, assert total grew by 500.

- [x] **Task T5 (Pest feature — update lock)**:
  - `test('update rejects total_value changes when services are attached')` — seed invoice + 1 attached service, PUT `{ total_value: 9999, ... }` with other valid fields, assert 422 on `total_value` with the Spanish lock message.
  - `test('update allows other field changes when services are attached')` — same scenario, PUT with unchanged `total_value` but new `notes`, assert 302 + notes updated.
  - `test('update allows total_value changes when no services are attached')` — seed invoice with 0 services, PUT `{ total_value: 5000, ... }`, assert 302 + total_value updated (preserves existing behaviour).

- [x] **Task T6 (Pest feature — authorization)**:
  - `test('accounting user can attach and detach services')` — seed accounting user, attach + detach, assert both 302.
  - `test('operator receives 403 on attach endpoint')`.
  - `test('operator receives 403 on detach endpoint')`.
  - `test('operator receives 403 on recompute endpoint')`.
  - `test('driver receives 403 on all three endpoints')`.
  - `test('unauthenticated user receives 302 redirect to login on all three endpoints')`.

- [x] **Task T7 (Pest feature — show payload)**:
  - `test('show payload includes computed_total, services_count, and candidate_services')` — seed invoice + 1 attached service, GET show, assert all three props present with expected values.
  - `test('candidate_services excludes open-status services')` — seed customer + 2 services (1 closed + 1 open), GET show, assert only the closed one is in candidates.
  - `test('candidate_services excludes services from other customers')` — seed 2 customers + 1 closed service each, GET show for customer A's invoice, assert only customer A's service is in candidates.
  - `test('candidate_services excludes already-billed services')` — seed 2 closed services both attached to other invoices, assert neither appears.
  - `test('candidate_services respects the 90-day window')` — seed 1 closed service with `service_date = today - 95 days`, assert it is NOT in candidates.

- [x] **Task T8 (Dusk)**: Create `tests/Browser/InvoiceBillingWorkflowTest.php`.
  - Follow the `tests/Browser/InvoicesIndexAndShowTest.php` shape.
  - `beforeEach`: `migrate:fresh --no-interaction` (fixtures built inline).
  - Scenario 1: **admin attach flow** — super-admin logs in, seeds invoice + 2 closed services belonging to its customer, visits `/invoices/{id}`, asserts "Asignar Servicios" button visible, clicks it, asserts the picker dialog renders with both candidate rows, selects both checkboxes, clicks Asignar, asserts the dialog closes + both services appear in the Servicios Facturados card + the Valor Total hero shows the sum + "(calculado automáticamente)" subtitle is visible.
  - Scenario 2: **admin detach flow** — super-admin on the same invoice, clicks the Quitar icon on one row, asserts the AlertDialog "¿Desvincular servicio?" appears, clicks Confirmar, asserts the row disappears + the Valor Total updates + only 1 row remains. After detaching the last one, assert the "(calculado automáticamente)" subtitle disappears.
  - Scenario 3: **accounting user walk** — create an `accounting@sgte.app` user with the `accounting` role, login, perform attach + detach through the full UI, asserting both buttons are visible and the flows complete (pins the `ASSIGN_SERVICES_TO_INVOICES` accounting grant).
  - Scenario 4: **operator denied** — create an `operator@sgte.app` user with the `operator` role, login, visit an invoice show page, `assertSourceMissing('Asignar Servicios')` + `assertSourceMissing('Desvincular')`.
  - Screenshots at every key interaction step.

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

1. Login as admin; visit `/invoices/{id}` with attached services; verify Asignar Servicios button visible, Valor Total hero shows "(calculado automáticamente)" subtitle.
2. Click Asignar Servicios; verify picker shows candidates for this customer only; search filters by plate; select 2; click Asignar; verify refresh + total updates.
3. Click Quitar on one row; confirm the AlertDialog; verify the row disappears + total updates.
4. Set the total stale manually in tinker or via a direct Service update; reload the show page; verify the amber "Total desactualizado" pill + Recalcular button; click Recalcular; verify the pill disappears.
5. Logout; login as accounting; repeat steps 2–4 to pin the role permission.
6. Logout; login as operator; visit `/invoices/{id}`; verify NO Asignar Servicios button and NO Quitar icons.
7. `mcp__laravel-boost__browser-logs` to confirm no JS errors during the flow.

- [x] Scenario 1: Admin attach flow
- [x] Scenario 2: Admin detach flow
- [x] Scenario 3: Stale-total detection + Recalcular
- [x] Scenario 4: Accounting user walkthrough
- [x] Scenario 5: Operator UI omits affordances

### 2. Backend regression — Pest feature tests (required)

Tasks T1–T7 above MUST be added to `tests/Unit/InvoiceTotalCalculatorTest.php` + `tests/Feature/Http/Controllers/InvoiceControllerTest.php`. Run via `./vendor/bin/sail test --compact`. The full suite MUST stay green at **531+** tests passing (baseline after service-incidents-crud).

### 3. UI regression — Laravel Dusk browser tests (required)

Task T8 above MUST be added under `tests/Browser/InvoiceBillingWorkflowTest.php`. Each test MUST:

- Assert no `[role="alert"]` exception traces or visible error UI.
- Assert key Spanish strings render with correct diacritics (Asignar Servicios, Desvincular, Total desactualizado, Recalcular, calculado automáticamente).
- Take screenshots at key interaction steps.

Run locally via `./vendor/bin/sail dusk --filter=InvoiceBillingWorkflowTest`.

### 4. API endpoints — curl

The three new endpoints are Inertia routes, not public JSON APIs. Auth-gate verification only:

```bash
# Admin can hit attach (expect 302 redirect on valid payload)
curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST http://localhost/invoices/1/services \
  -H "Content-Type: application/json" \
  -H "X-Requested-With: XMLHttpRequest" \
  -b cookies-admin.txt \
  -d '{"service_ids":[1,2]}'

# Operator gets 403
curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST http://localhost/invoices/1/services \
  -H "Content-Type: application/json" \
  -b cookies-operator.txt \
  -d '{"service_ids":[1]}'
```

### 5. Static analysis

Run the full pipeline after all tasks are committed:

- `./vendor/bin/sail test --compact` — full Pest suite green.
- `./vendor/bin/pint --dirty --format agent` — no PHP formatting issues.
- `./vendor/bin/sail npm run types` — no new TypeScript errors in invoices pages.
- `./vendor/bin/sail npm run lint` — clean.
- `./vendor/bin/sail npm run format:check` — clean.
- `./vendor/bin/sail npm run build` — Vite build succeeds.

## Dependencies

- **invoices-crud** (merged — commit `46fba03`) — direct prerequisite. This requirement extends `invoices/show.tsx` (Servicios Facturados card added in commit `703420d`) and `invoices/edit.tsx`.
- **service-incidents-crud** (merged — latest develop) — direct prerequisite. The billing-affecting incident surface (`service_incidents.affects_billing` + `additional_value`) is what REQ-011 AC4 demands. Without this rebuild, the data shape would exist but there'd be no UI proving the hook works.
- **No new packages, no new permissions, no migrations.**

## Notes

### Why a dedicated `InvoiceTotalCalculator` service class

Three call sites (attach, detach, recompute) all need the same computation. Keeping the logic in a private controller method would force us to either duplicate or chain controller calls — both smell. A service class is the canonical Laravel answer: single source of truth, trivially unit-testable without HTTP, and explicit about its role (`recomputeFor` for side-effect, `computeFor` for pure read).

### Why the picker candidate list rides on the show payload

Alternative architectures: (a) fetch candidates lazily when the dialog opens via a separate `candidateServices` route; (b) ship them on show page load (current design). Option (a) saves bandwidth when the user never clicks Asignar; option (b) zeroes out the latency of the first dialog open. For the expected usage pattern (accounting opens an invoice specifically to bill it), option (b) wins. If candidate lists balloon into hundreds of rows, we can revisit.

### Stale-total detection is best-effort

The drift check runs on every show-page render by comparing `invoice.total_value` to `computed_total`. This catches drift caused by a linked service's `unit_value` / `quantity` change or an incident's `affects_billing` / `additional_value` change. It does NOT auto-fix — accounting fires the Recalcular button manually. The alternative (server-side observers on Service + ServiceIncident that auto-recompute affected invoices on save) is a separate requirement; it's more ambitious and has a blast-radius surface we don't want to take on at the same time as the core workflow.

### Why `total_value` stays editable when zero services are attached

REQ-011 wording ("THEN the system SHALL allow linking an invoice number to the service") doesn't forbid a manual total — it just says the system SHALL calculate when services are attached. Preserving the manual path for invoices with no attached services keeps existing seeded/historical invoices working AND gives accounting a migration path: they can attach services to existing invoices over time without the `total_value` on those invoices getting stomped mid-transition.

### Out of scope, deferred

- Observer-based auto-recompute (when a linked service's billable fields change, the invoice silently recomputes).
- `/billing/pending-services` shop-floor screen.
- PDF invoice generation.
- REQ-009 accounting-immutability justification prompt on service edits after day-execution.
- Services-side "Asignar a Factura" button on `services/show.tsx`.
- Cross-customer bulk-attach (technically possible but deliberately rejected for safety).

### Estimated commit count

About **12–14 commits**:

- 1 doc commit (this requirement file).
- 1 backend commit (B1 calculator + T1 unit tests).
- 1 backend commit (B2 + B3 locking rule + T5 tests).
- 1 backend commit (B4 attach request + B5 controller actions + B6 candidate helper + B7 + B8 show/edit payload extension + B9 routes + T2 + T3 + T4 + T7 tests).
- 1 backend commit (T6 authorization 403 tests — could be bundled).
- 1 frontend commit (F1 ServicePickerDialog).
- 1 frontend commit (F2 show-page extension — Asignar Servicios + stale pill + Quitar + AlertDialog).
- 1 frontend commit (F3 InvoiceForm isTotalLocked + F4 edit page wiring).
- 1 Dusk test commit (T8).
- 1 polish commit (Prettier + any TS fixes).
- 1 final docs commit (mark requirement completed + update phase-4-billing-reports.md checkboxes).

Slightly higher than the alignment rebuilds because the calculator + rule + three endpoints carry their own test weight.
