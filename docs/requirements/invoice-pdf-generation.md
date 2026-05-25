---
name: invoice-pdf-generation
type: feat
scope: invoices
status: completed
priority: high
created_date: 2026-04-18
completed_date: 2026-04-18
srs_refs: ["REQ-011"]
migration_strategy: new
---

# Invoice PDF generation — informational download

## Description

The billing workflow that just merged (`invoice-service-assignment`) lets admin + accounting attach services to invoices, auto-compute totals, and manage billing-affecting incidents. Once an invoice is filled out, the logical next step is printing it — for the customer, for internal records, or for attaching to an email. This requirement adds **informational** PDF generation: a one-click "Descargar PDF" download from the invoice show page that renders a clean, customer-facing invoice layout.

**Explicitly not a fiscal document.** Colombian DIAN-compliant invoicing requires a certified electronic-invoicing provider integration (separate compliance project entirely — not this requirement). The PDFs produced here carry a prominent "INFORMATIVO" badge in the header and a footer disclaimer "Documento informativo — no constituye factura fiscal." rendered on every page so nobody confuses them for a fiscal invoice.

Four decisions locked up front:

1. **Package**: `barryvdh/laravel-dompdf`. Pure PHP, zero container-layer changes, handles Spanish diacritics, proven for invoice-style documents. DOMPDF's CSS support is limited (no flex / no grid / no CSS variables) — the Blade template uses tables + inline styles + simple margin/padding.
2. **Delivery**: inline (`Content-Disposition: inline`). The "Descargar PDF" link opens the endpoint in a new browser tab; the browser's PDF viewer renders it; the user can save/print from there. Closer to how SaaS billing apps behave than a forced download.
3. **Fiscal disclaimer**: a small red "INFORMATIVO" badge next to the header title plus a footer disclaimer on every page. Unambiguous without being obtrusive.
4. **Auth gate**: `VIEW_INVOICES` — same gate as the invoice show page. Admin + accounting + super-admin can download. Operator + driver stay blocked at the route level.

**Recompute-on-render**: the PDF handler calls `InvoiceTotalCalculator::recomputeFor($invoice)` before rendering so the printed total matches the current attached services + billing-affecting incidents. A stale total escaping into a PDF that gets emailed to a customer is a bigger problem than the minor side-effect of a GET-time DB write. Audit log noise from repeated recomputes that produce no delta is acceptable (activity-log only creates an entry on actual attribute changes).

**Out of scope**: signed PDFs; DIAN electronic invoicing (certified provider required); email-the-PDF workflow (future enhancement); PDF caching via stored files (regenerate per request — simpler, no staleness window); company-logo upload + embedding (placeholder text "SGTE" is sufficient — logo ingestion is a separate configuration requirement); multi-language PDFs (Spanish only); custom paper sizes (letter is the Colombia default).

## Acceptance Criteria

- [x] **AC1**: WHEN an authenticated admin, accounting, or super-admin user navigates to `GET /invoices/{invoice}/pdf` for any invoice they can view THEN the response is 200 with `Content-Type: application/pdf`.
- [x] **AC2**: WHEN the PDF response is returned THEN the `Content-Disposition` header begins with `inline;` AND contains `filename="factura-{invoice_number}.pdf"`.
- [x] **AC3**: WHEN the PDF response body is inspected THEN the first bytes match the PDF magic number `%PDF-`.
- [x] **AC4**: WHEN an unauthenticated user hits `GET /invoices/{invoice}/pdf` THEN the response is a redirect to `/login`.
- [x] **AC5**: WHEN an authenticated operator OR driver hits `GET /invoices/{invoice}/pdf` THEN the response is 403.
- [x] **AC6**: WHEN an authenticated accounting user hits `GET /invoices/{invoice}/pdf` THEN the response is 200 (pinning the VIEW_INVOICES grant for accounting).
- [x] **AC7**: WHEN the PDF handler is invoked THEN `InvoiceTotalCalculator::recomputeFor($invoice->fresh())` is called BEFORE the Blade template is rendered, so the printed total reflects the current attached services + billing-affecting incidents (even if `invoice.total_value` was stale in the DB).
- [x] **AC8**: WHEN the invoice has at least one attached service THEN the PDF Servicios Facturados table renders one row per attached service (ordered by `service_date` ASC) with columns **Fecha**, **Contrato**, **Vehículo**, **Valor Unit.**, **Cant.**, **Subtotal**.
- [x] **AC9**: WHEN the invoice has ZERO attached services THEN the Servicios Facturados section renders an italic muted note "Sin servicios asociados — valor total manual." INSTEAD of the table (existing manual-total invoices still print correctly).
- [x] **AC10**: WHEN at least one attached service has a `service_incidents` row with `affects_billing = true` THEN a separate **Novedades que afectan facturación** table renders below the services table with columns **Fecha**, **Servicio**, **Tipo**, **Descripción**, **Valor adicional**. WHEN none do, the section is omitted entirely.
- [x] **AC11**: WHEN `invoice.notes` is null OR empty THEN the Observaciones section is omitted. WHEN it has content THEN it renders preserving whitespace (newlines honored).
- [x] **AC12**: WHEN the PDF renders THEN the header contains the "SGTE" company placeholder text, the "Sistema de Gestión de Transporte Especial" subtitle, the "FACTURA INFORMATIVA" title, the invoice_number in font-mono, AND a small red "INFORMATIVO" badge.
- [x] **AC13**: WHEN ANY page of the PDF renders THEN the footer reads "Documento informativo — no constituye factura fiscal." on the left and "Generado el {now es-CO} — página X / Y" on the right.
- [x] **AC14**: WHEN an authenticated admin or accounting user loads `/invoices/{id}` THEN a "Descargar PDF" button is visible in the header card action row (between "Asignar Servicios" and "Editar"). WHEN a user lacks `VIEW_INVOICES` THEN the button is NOT rendered (gate via `<Can permission={Permission.VIEW_INVOICES}>`).
- [x] **AC15**: WHEN the user clicks "Descargar PDF" THEN a new browser tab opens pointing at `/invoices/{id}/pdf` (the anchor uses `href` + `target="_blank" rel="noreferrer"` — NOT an Inertia router.get, because the response is binary).
- [x] **AC16**: WHEN `npm run types` runs THEN the invoices pages contribute zero new errors.

## Technical Specification

### Data Model

**No new tables, no new columns.** All data read from existing relations.

### Enums

**No new enums.** `VIEW_INVOICES` permission already exists.

### Routes

**One new route.** Registered in `routes/web.php` immediately after `Route::post('invoices/{invoice}/mark-paid', ...)`:

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | `/invoices/{invoice}/pdf` | `InvoiceController@pdf` | `auth, verified, can:invoices.view` | `invoices.pdf` |

### Permissions

**No new permissions.** The `invoices.view` permission string is the existing `Permission::VIEW_INVOICES->value`.

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Invoice show | `resources/js/pages/invoices/show.tsx` | **EXTEND.** Add "Descargar PDF" button gated by `<Can permission={Permission.VIEW_INVOICES}>`. The button is a native `<a>` with `target="_blank"` (NOT Inertia), because the endpoint returns a binary stream. |
| Invoice PDF template | `resources/views/invoices/pdf.blade.php` | **NEW.** DOMPDF-compatible Blade template (tables + inline styles, no flex/grid, no CSS variables). 8 sections: header band / meta row / cliente block / services table (or fallback note) / billing-incidents table (conditional) / totales summary / observaciones (conditional) / footer on every page. |

### Packages

**One new package**: `barryvdh/laravel-dompdf`. Install via composer, publish config if needed. Compatible with Laravel 12.

## Migration Strategy

`new` (formal frontmatter value), but **no migration files are written or modified**. The requirement adds a package, a Blade view, a route, a controller action, and a frontend button — zero schema impact.

## Tasks

### Backend

- [x] **Task B1**: Install `barryvdh/laravel-dompdf`.
  - Run `./vendor/bin/sail composer require barryvdh/laravel-dompdf` — verify the version installed is compatible with Laravel 12 (composer will resolve; if it errors, pin to `^3.0`).
  - Publish the config only if needed for custom defaults (default package config is fine — letter paper, DejaVu Sans, etc. — we override paper at the Pdf::loadView level anyway).
  - Commit message: `chore(deps): ⬆️ add barryvdh/laravel-dompdf for invoice PDF generation`.

- [x] **Task B2**: Add `InvoiceController@pdf` action.
  - Signature: `public function pdf(Request $request, Invoice $invoice, InvoiceTotalCalculator $calculator): \Illuminate\Http\Response`.
  - At the top: `Gate::authorize(Permission::VIEW_INVOICES->value);` matching the show action's pattern.
  - `$calculator->recomputeFor($invoice->fresh());` to ensure the total is current at print-time.
  - Re-fetch the invoice after the recompute with the full eager-load chain needed by the Blade template:
    ```php
    $invoice = $invoice->fresh()->load([
        'thirdParty.documentType',
        'thirdParty.municipality.department',
        'services' => fn ($q) => $q->orderBy('service_date'),
        'services.vehicle:id,plate',
        'services.contract:id,contract_number',
        'services.serviceIncidents' => fn ($q) => $q->where('affects_billing', true),
        'services.serviceIncidents.incidentType:id,name',
    ]);
    ```
  - Compute the view-model values:
    - `$services = $invoice->services;`
    - `$billing_incidents = $services->flatMap(fn ($s) => $s->serviceIncidents)->values();`
    - `$subtotal_services = $services->sum(fn ($s) => (float) $s->unit_value * (int) $s->quantity);`
    - `$subtotal_incidents = $billing_incidents->sum(fn ($i) => (float) ($i->additional_value ?? 0));`
    - `$grand_total = $subtotal_services + $subtotal_incidents;` (this matches what the calculator just persisted; the PDF template uses this for the totals row directly rather than re-reading `invoice->total_value`, which avoids a string-vs-float comparison edge case).
    - `$customer_name = $this->computeCustomerName($invoice->thirdParty);` — extract to a private helper OR inline. Returns company_name for legal, or `first_name + first_lastname` for natural persons.
    - `$customer_document = $invoice->thirdParty?->documentType?->code . ' ' . $invoice->thirdParty?->identification_number` (trimmed).
    - `$customer_address_line = ...` — piece together municipality/department/address with separator safety: `array_filter([$mun?->name, $mun?->department?->name, $tp->address])` joined by " — ".
    - `$now_formatted = Carbon::now()->locale('es_CO')->isoFormat('LLLL')` — e.g. "jueves, 18 de abril de 2026 15:23".
  - Render: `return Pdf::loadView('invoices.pdf', compact('invoice', 'services', 'billing_incidents', 'subtotal_services', 'subtotal_incidents', 'grand_total', 'customer_name', 'customer_document', 'customer_address_line', 'now_formatted'))->setPaper('letter')->stream('factura-' . $invoice->invoice_number . '.pdf', ['Attachment' => false]);`
  - `Pdf` is the facade `Barryvdh\DomPDF\Facade\Pdf`.
  - Reference convention: `InvoiceController@markPaid` for the permission + return shape, `InvoiceController@show` for the eager-load pattern.

- [x] **Task B3**: Register the route.
  - Add in `routes/web.php` immediately after the existing `Route::post('invoices/{invoice}/mark-paid', ...)` line:
    ```php
    Route::get('invoices/{invoice}/pdf', [App\Http\Controllers\InvoiceController::class, 'pdf'])
        ->middleware('can:'.App\Enums\Permission::VIEW_INVOICES->value)
        ->name('invoices.pdf');
    ```
  - Run `./vendor/bin/sail artisan wayfinder:generate` so the frontend picks up `InvoiceController.pdf(id)` as an action URL.

- [x] **Task B4**: Create `resources/views/invoices/pdf.blade.php`.
  - DOMPDF constraints:
    - Use `<table>` for layout (no flex/grid).
    - Inline `style=""` attributes for colors/sizes (no CSS variables, limited class support depending on package setup — safest is inline).
    - Use `@php` blocks for formatting (currency, date) since the view runs through the Blade compiler but there's no Intl library.
    - Fonts: stick to the dompdf default (DejaVu Sans) which handles Spanish diacritics. No need to embed a custom font.
  - 8 sections (top to bottom):
    1. **Header band** — full-width 2-col table: left column "SGTE" (24pt bold) + "Sistema de Gestión de Transporte Especial" (9pt muted); right column right-aligned "FACTURA INFORMATIVA" (18pt bold) + invoice_number in font-mono (14pt) + small "INFORMATIVO" badge inline (white text on destructive red background, `padding: 2px 6px; background: #dc2626; color: #fff; border-radius: 3px; font-size: 9pt;`).
    2. **Meta row** — 3-col table: "Fecha de Emisión" (label muted + value), "Estado de Pago" (label + colored badge: Pendiente = amber background, Pagado = green, Vencido = red — all light-background + dark text inline-styled), "Valor Total" right-aligned ($ + number_format($grand_total, 0, ',', '.')).
    3. **Cliente block** — labeled section title "Cliente" (10pt bold uppercase), below it a 1-col block with customer_name (12pt), customer_document (font-mono, 10pt, muted), and customer_address_line (9pt, muted) — each conditionally rendered (no empty lines when a piece is null).
    4. **Servicios Facturados table** — section title "Servicios Facturados". When `$services->isEmpty()`: render `<p style="font-style: italic; color: #666;">Sin servicios asociados — valor total manual.</p>`. Otherwise a `<table>` with thead (`<tr>` background `#f3f4f6`, text-left columns: Fecha / Contrato / Vehículo / Valor Unit. / Cant. / Subtotal — last two right-aligned) and tbody with one row per service. Apply `border-bottom: 1px solid #e5e7eb` on each row.
    5. **Novedades que afectan facturación table** — only renders when `$billing_incidents->isNotEmpty()`. Section title "Novedades que afectan facturación". Same `<table>` shape as above with columns Fecha / Servicio / Tipo / Descripción / Valor adicional. Descripción cell uses `Str::limit($incident->description, 100)` for truncation.
    6. **Totales summary** — right-aligned block (80% width, margin-left auto simulated via `<table width="100%">` with padded cells): "Subtotal servicios" + value, "Subtotal novedades" + value (only when > 0), a separator row, "Total" in bold 14pt + value in bold 14pt.
    7. **Observaciones block** — only renders when `$invoice->notes` is non-null and non-empty (use `filled($invoice->notes)`). Section title "Observaciones" + `<p style="white-space: pre-wrap;">{{ $invoice->notes }}</p>`.
    8. **Footer** — use dompdf's page-footer script. Either via `@page_script` with an HTML block containing the disclaimer text on the left, "Generado el {{ $now_formatted }} — página <span class=\"pagenum\">" + PHP `PAGE_NUM / PAGE_COUNT` syntax on the right. If the @page_script approach is finicky under our dompdf version, fall back to absolute-positioned footer HTML at `position: fixed; bottom: 0;`. Pick whichever renders cleanly — the acceptance test (AC13) only checks text presence.
  - Include a single inline `<style>` block at the top of the Blade with the base typography: `body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; } h1,h2,h3 { margin: 0; }` etc. Keep it minimal.

### Frontend

- [x] **Task F1**: Add the "Descargar PDF" button to `resources/js/pages/invoices/show.tsx`.
  - In the header card action row (the flex row containing `<PaymentStatusPill />`, `Asignar Servicios`, `Editar`), add the button **between Asignar Servicios and Editar**.
  - Markup:
    ```tsx
    <Can permission={Permission.VIEW_INVOICES}>
        <Button
            asChild
            size="sm"
            variant="outline"
        >
            <a
                href={InvoiceController.pdf(invoice.id).url}
                target="_blank"
                rel="noreferrer"
            >
                <FileDown className="mr-1 size-4" />
                Descargar PDF
            </a>
        </Button>
    </Can>
    ```
  - Add `FileDown` to the lucide-react import line at the top of the file (alongside existing `CheckCircle2, FileText, Pencil, ListPlus, Trash2, RefreshCw`).
  - Verify `Permission` is already imported from `@/enums/Permission` (it is, from the invoice-service-assignment requirement).

### Tests

- [x] **Task T1 (Pest feature — happy path)**: Add to `tests/Feature/Http/Controllers/InvoiceControllerTest.php`:
  - `test('admin can download the invoice PDF')` — seed an invoice with 2 attached services + 1 billing-affecting incident, request `GET /invoices/{id}/pdf` as super-admin, assert:
    - Response status 200.
    - `Content-Type` header begins with `application/pdf`.
    - `Content-Disposition` header contains `inline; filename="factura-`.
    - The first 5 bytes of the response body are `%PDF-` (assert via `str_starts_with($response->getContent(), '%PDF-')`).

- [x] **Task T2 (Pest feature — accounting grant)**:
  - `test('accounting user can download the invoice PDF')` — same shape but authenticated as an accounting-role user. Assert 200.

- [x] **Task T3 (Pest feature — unauth + 403)**:
  - `test('operator receives 403 on the invoice PDF endpoint')`.
  - `test('driver receives 403 on the invoice PDF endpoint')`.
  - `test('unauthenticated user is redirected to login from the invoice PDF endpoint')` — assert 302 redirect to `/login`.

- [x] **Task T4 (Pest feature — recompute-on-render)**:
  - `test('PDF regenerates invoice total from the calculator on every request')` — seed an invoice with 1 attached service (unit_value=1000, quantity=1); manually overwrite `invoice.total_value = 9999` via direct query builder to bypass any observer; request the PDF as super-admin; after the request completes, assert `$invoice->fresh()->total_value` equals `1000.00`.

- [x] **Task T5 (Pest feature — inline disposition)**:
  - `test('invoice PDF downloads inline not as attachment')` — seed a minimal invoice, request the PDF, assert `Content-Disposition` header starts with `inline;` (using `str_starts_with` or `assertHeader`).

- [x] **Task T6 (Pest feature — content smoke test)**:
  - `test('PDF response content includes key invoice fields')` — seed an invoice with a distinctive invoice_number (e.g. `FAC-PDF-TEST-001`) and a customer with a distinctive company_name (e.g. `Cliente PDF Prueba S.A.`), request the PDF, capture the response body as a string, and assert BOTH strings appear in the raw bytes. (Note: dompdf embeds text in a way that's usually plaintext-searchable for ASCII, but font subsetting can obscure characters in dense pages — if this assertion fails for unrelated reasons under dompdf's behavior, fall back to rendering the Blade view directly via `view('invoices.pdf', [...])->render()` in a sibling test and asserting against that HTML output instead.)

- [x] **Task T7 (Dusk — button visible + target blank)**: Create `tests/Browser/InvoicePdfDownloadTest.php`:
  - `beforeEach`: `migrate:fresh --no-interaction`.
  - Scenario 1: `test('admin sees the Descargar PDF button on the invoice show page')` — super-admin logs in, seeds an invoice, visits `/invoices/{id}`, asserts `assertSee('Descargar PDF')` AND `assertSourceHas('target="_blank"')` on the PDF anchor. Take a screenshot.
  - Scenario 2: `test('operator cannot see the Descargar PDF button')` — operator logs in, visits an invoice show URL. Operator will likely hit a 403 page at that route since they lack `VIEW_INVOICES`. The assertion that matters: `assertSourceMissing('Descargar PDF')`. Take a screenshot.
  - DO NOT try to drive through the actual PDF render in Dusk — Chromedriver handles binary PDF responses inconsistently; the Pest suite covers functional correctness.

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

1. Login as admin; visit `/invoices/{id}` for an invoice with 2+ attached services and at least one billing-affecting incident. Verify "Descargar PDF" button is visible in the header card action row.
2. Click "Descargar PDF". Verify a new tab opens; the browser's PDF viewer renders the invoice; verify the header includes the INFORMATIVO badge; verify the services table is populated; verify the footer disclaimer.
3. For an invoice with ZERO attached services (pure manual total), download the PDF and verify the "Sin servicios asociados — valor total manual." note appears instead of the services table.
4. For an invoice with no billing-affecting incidents, verify the Novedades table is entirely absent.
5. Logout; login as accounting; repeat steps 1–2 to pin the VIEW_INVOICES grant.
6. Logout; login as operator; visit an invoice URL directly; verify 403 AND verify the "Descargar PDF" text is not in the page source.
7. `mcp__laravel-boost__browser-logs` — no JS errors during the flow.

- [x] Scenario 1: Admin clicks Descargar PDF, verifies tab opens and PDF renders with attached services
- [x] Scenario 2: Zero-services invoice renders the fallback note
- [x] Scenario 3: Billing-incidents table is omitted when not applicable
- [x] Scenario 4: Accounting can download
- [x] Scenario 5: Operator blocked (403)
- [x] Scenario 6: Footer disclaimer visible on every page

### 2. Backend regression — Pest feature tests (required)

Tasks T1–T6 MUST be added to `tests/Feature/Http/Controllers/InvoiceControllerTest.php`. Run via `./vendor/bin/sail test --compact`. The full suite MUST stay green at **561+** tests passing (baseline after invoice-service-assignment).

### 3. UI regression — Laravel Dusk browser tests (required)

Task T7 above MUST be added under `tests/Browser/InvoicePdfDownloadTest.php`. Each test MUST:

- Assert no `[role="alert"]`, exception trace, or visible error UI.
- Assert key Spanish strings (Descargar PDF, Factura, etc.).
- Take screenshots at key interaction steps.

Run locally via `./vendor/bin/sail dusk --filter=InvoicePdfDownloadTest`.

### 4. API endpoints — curl

Not applicable for this requirement. The PDF endpoint is not a public JSON API; it returns binary content bound to browser sessions. The Pest suite covers auth gate + response shape; the Dusk suite covers the UI affordance. Trying to curl-verify a binary PDF response adds nothing beyond what those layers already pin.

### 5. Static analysis

After all tasks are committed:

- `./vendor/bin/sail test --compact` — full Pest suite green.
- `./vendor/bin/pint --dirty --format agent` — no PHP formatting issues.
- `./vendor/bin/sail npm run types` — no new TypeScript errors.
- `./vendor/bin/sail npm run lint` — clean.
- `./vendor/bin/sail npm run format:check` — clean.
- `./vendor/bin/sail npm run build` — Vite build succeeds.
- Verify the newly-added package appears in `composer.lock`.

## Dependencies

- **invoices-crud** (merged — commit `46fba03`) — direct prerequisite for the show page the button mounts into.
- **invoice-service-assignment** (just merged to `develop`) — direct prerequisite. The calculator (`App\Services\InvoiceTotalCalculator`) is reused for recompute-on-render; the attached-services relationship is what the PDF actually prints.
- **New package**: `barryvdh/laravel-dompdf`.

## Notes

### Why inline + target="_blank"

Inline opens the browser's PDF viewer which is familiar and lets users decide whether to save or just preview. Forcing a download feels obstructive for an "I just want to double-check this invoice" flow. `target="_blank"` keeps the user's main app tab alive — losing your scroll position to a PDF download would be annoying. The `rel="noreferrer"` is a standard safety net for external-ish opens (even though this is same-origin, the habit is worth keeping).

### Why recompute on GET

Conventionally GET handlers should not mutate state. Here the justification is asymmetric: the downside of recomputing on every PDF load is one silent DB write that produces no activity-log entry when `total_value` is already correct. The downside of NOT recomputing is that a customer receives an invoice with a wrong total, which is a billing dispute. For a low-volume document (an invoice is printed a handful of times, not thousands), the trade-off clearly favors safety. Adding an observer that recomputes on Service / ServiceIncident save would eliminate the issue entirely — that's a potential future enhancement, but the cost/value is less clear when the volume is low.

### Why no logo image

Embedding logo images in dompdf requires either a `file://` URL to a server-local path or an inline `data:` URL. Both force per-project configuration (path to logo, size tuning, publishing a default placeholder). The "SGTE" text placeholder is good enough for an informational document and keeps the template fully self-contained. A future requirement can add a `company_logo_path` setting + image embedding.

### Why letter paper

Colombia historically uses letter paper (216 × 279 mm) in business contexts, partly due to US-centric import of templates and office equipment. A4 is also common. Letter is a safe default; if a particular client prefers A4, the `setPaper('a4')` swap is one line.

### Out of scope, deferred

- DIAN electronic-invoicing certification.
- PDF digital signatures.
- Emailing the PDF to the customer (needs a background-job workflow).
- Stored-file caching (simpler to regenerate).
- Company-logo embedding.
- A4 / custom paper sizes.
- Multi-language (English PDFs, etc.).
- Custom templates per customer (enterprise feature).

### Estimated commit count

About **8–10 commits**:

- 1 doc commit (this requirement file).
- 1 chore commit (install barryvdh/laravel-dompdf).
- 1 backend commit (B2 controller action + B3 route + wayfinder regen).
- 1 backend commit (B4 Blade template).
- 1 backend commit (T1–T6 Pest tests).
- 1 frontend commit (F1 Descargar PDF button on invoices/show.tsx).
- 1 Dusk test commit (T7).
- 1 polish commit (Prettier + any TS fixes).
- 1 final docs commit (mark requirement completed + tick the Phase 4 PDF checkbox).

Slightly lower than the rebuild requirements because the surface area is narrow: one route, one controller method, one Blade template, one button.
