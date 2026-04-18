---
name: fuec-generation
type: feat
scope: fuecs
status: pending
priority: high
created_date: 2026-04-18
completed_date:
srs_refs: ["REQ-007"]
migration_strategy: new
---

# Implement REQ-007 — FUEC generation, public QR verification, MinTransporte range, and module feature flag

## Description

The FUEC (Formato Único de Extracto de Contrato) is a document legally required by Colombia's Ministerio de Transporte for every special-transport service; it certifies that the service is being provided under a valid contract with a driver and vehicle whose documents are in order. Today the SGTE FUEC module is a Blueprint scaffold: the `fuecs` table exists with six columns and a status enum, the `FuecController` is a generic resource controller with no PDF / QR / validation logic, and all four `resources/js/pages/fuecs/*.tsx` pages dump JSON. SRS §REQ-007 (lines 416–475) mandates five capabilities that this requirement delivers end-to-end:

1. **Pre-generation validation** of contract vigente + vehicle docs non-expired + driver license non-expired.
2. **PDF** with contract / vehicle / driver / origin-destination / service date+time / QR / consecutive number.
3. **Consecutive from the MinTransporte-authorized range** (not a simple auto-increment — the number must come from an externally-granted allocation).
4. **Public QR verification** page showing VIGENTE or ANULADO.
5. **Feature flag** to disable the module without removing the logic, since FUEC use is case-dependent (some clients / contracts don't require it).

Four design decisions were made explicitly during Q&A:

1. **Modify path = cancel-only**. FUECs go `active` → `cancelled` via a dedicated endpoint requiring a min:10 max:500 reason written to the activity log. No edit, no soft-delete, no hard-delete. SRS §REQ-007 AC#4 treats the document as bi-state (Vigente / Anulado) — this maps cleanly.
2. **PDF regeneration = cancel + create new**. When an admin needs to redo a FUEC (render bug, data fix), they cancel the broken one and create a fresh FUEC for the same service. The new FUEC consumes the next consecutive and produces an independent audit trail. No "regenerar PDF" button.
3. **Dashboard range-exhaustion warning = deferred**. Out of scope for this already-large requirement; admin can check `/fuec-number-ranges` manually. Ship later if needed.
4. **Service picker = extend `<ServicePickerDialog />`**. The invoice module's existing dialog gets a new `mode: 'invoice' | 'fuec'` prop that tweaks the filter (closed services with no active fuec vs no invoice_id), the submit shape (single-select for fuec vs multi-select for invoice), and the labels. One shared primitive; no duplication.

**Out of scope:**

- **MinTransporte e-government API live integration** — just a local range table for now. An admin registers the resolution + range manually after the paper authorization arrives.
- **Bulk FUEC generation** — one service at a time.
- **Multi-language PDF** — Spanish only.
- **Per-company / per-client PDF templates** — single template for this release.
- **Regenerate-PDF button** — cancel + create new covers every regeneration case with a cleaner audit trail.
- **Dashboard range-exhaustion warning** — deferred to a follow-up requirement.
- **GPS module (REQ-010)** — separate requirement.

## Acceptance Criteria

### Feature flag

- [ ] **AC1**: WHEN `config('sgte.fuec_enabled')` is `false` AND an admin visits `/fuecs`, `/fuecs/create`, `/fuecs/{id}`, `/fuec-number-ranges`, or `/fuec/verify/{uuid}` THEN all these routes return **404**. The module's controllers are never invoked.
- [ ] **AC2**: WHEN `config('sgte.fuec_enabled')` is `false` THEN the FUEC sidebar group is hidden from every role. When `true`, it shows only for admin (plus super-admin via `Gate::before`).
- [ ] **AC3**: WHEN the admin's Inertia page loads THEN `props.auth.featureFlags.fuec` is a boolean matching `config('sgte.fuec_enabled')`. The sidebar reads this prop to decide visibility.

### MinTransporte authorization range CRUD

- [ ] **AC4**: WHEN an admin navigates to `/fuec-number-ranges` THEN the page renders a paginated `<DataTable>` with columns **Resolución**, **Año**, **Rango** (range_from – range_to), **Disponibles** (computed: range_to − (used consecutive count within this range) + 1 when the range is active, or the raw remaining slots when inactive), **Activo** (Sí / No), **Notas**, **Acciones**. Create / Edit / Delete actions are available only to admin (permission `MANAGE_FUEC_NUMBER_RANGES`).
- [ ] **AC5**: WHEN an admin submits a new `FuecNumberRange` with `active: true` AND another range already has `active: true` THEN the new row's insert MUST fail at the DB level via a partial unique index on `(active) WHERE active = true`. The UI surfaces a Spanish error "Ya existe un rango activo. Desactive el rango vigente antes de activar uno nuevo.".
- [ ] **AC6**: WHEN an admin submits a range with `range_from >= range_to` THEN the FormRequest validator rejects with "El número inicial debe ser menor que el final.".
- [ ] **AC7**: Non-admin users (operator / driver / accounting) visiting `/fuec-number-ranges` receive **403**.

### `fuecs` schema modifications

- [ ] **AC8**: The existing `create_fuecs_table` migration is modified **in place** (per project convention — no backfill migration) to add: `soft_deletes`, `uuid` (string 36, unique index, generated server-side via Laravel's `HasUuids` trait on the model), `pdf_disk` (string, default `'s3'`), `fuec_number_range_id` (foreign key to `fuec_number_ranges`, `onDelete('restrict')`). The `pdf_url` column is **renamed** to `pdf_path` to reflect it's a disk path, not a URL. Indexes are added on `consecutive_number`, `uuid`, and `fuec_number_range_id`.

### Pre-generation validation

- [ ] **AC9**: WHEN an admin submits `POST /fuecs` with a `service_id` THEN `FuecStoreRequest` + its `after()` hooks run the full gauntlet and reject with a **list** of top-level `errors.fuec_pre_generation.*` Spanish messages when any check fails:
    - Service not found OR not in `ServiceStatus::Closed` → "El servicio no está cerrado."
    - Contract `active === false` OR `today < start_date` OR `today > end_date` → "El contrato asociado no está vigente."
    - Vehicle `soat_due_date < today` → "El SOAT del vehículo está vencido (venció YYYY-MM-DD)."
    - Vehicle `rtm_due_date < today` → "La RTM del vehículo está vencida (venció YYYY-MM-DD)."
    - Vehicle `operation_card_due_date < today` → "La Tarjeta de Operación del vehículo está vencida (venció YYYY-MM-DD)."
    - Driver `license_due_date < today` → "La licencia del conductor está vencida (venció YYYY-MM-DD)."
    - Driver license category not in `LICENSE_CATEGORY_MAP[vehicle.type]` → "La categoría de licencia del conductor (C1) no permite operar este tipo de vehículo (Buseta requiere C2/C3)."
    - No `FuecNumberRange` with `active = true` → "No hay un rango MinTransporte activo. Registre uno en Administración → Rangos FUEC."
    - Active range has zero remaining numbers → "El rango MinTransporte activo se agotó. Registre un nuevo rango."
    - Service already has a FUEC with `status = active` → "Este servicio ya tiene un FUEC vigente. Anule el actual antes de generar uno nuevo."
- [ ] **AC10**: The validator logic lives in a **reusable** `App\Rules\FuecPreGenerationChecks` class or shared trait so the `FuecGenerator` service can re-run it inside its transaction (defense in depth against race conditions).

### Generation workflow

- [ ] **AC11**: WHEN `FuecStoreRequest` passes THEN `FuecController@store` delegates to `App\Services\FuecGenerator::generateFor(Service $service, User $causer): Fuec` which, inside a `DB::transaction(...)`:
    1. Re-runs the pre-generation checks (race-condition guard).
    2. Locks `fuec_number_ranges` for update (`->lockForUpdate()`) and finds the single active row.
    3. Computes the next consecutive as `max(consecutive_number WHERE fuec_number_range_id = $range->id) + 1` OR `$range->range_from` when no fuec has been issued from this range yet.
    4. Validates the computed consecutive `<= $range->range_to`; throws `App\Exceptions\FuecRangeExhaustedException` otherwise.
    5. Generates a UUID token via `(string) Str::uuid()` for the `uuid` column.
    6. Renders the PDF via `Pdf::loadView('fuecs.pdf', [...])->setPaper('letter')->output()` with the QR image embedded as a base64 data URL (QR payload = `route('fuec.verify', $uuid)`, 200×200 px, ECC level M).
    7. Persists the PDF to MinIO: `Storage::disk('s3')->put("fuecs/{$consecutive}.pdf", $pdfBytes)`.
    8. Creates the `Fuec` row with `status = 'active'`, `generated_at = now()`, `pdf_path = "fuecs/{$consecutive}.pdf"`, `pdf_disk = 's3'`, `qr_code = $uuid`, `fuec_number_range_id = $range->id`, `consecutive_number = $consecutive`, `service_id = $service->id`, `uuid = $uuid`.
    9. Writes an activity log entry on the fuec with `properties.consecutive_number`, `properties.fuec_number_range_id`, and `causer_id = $causer->id`.
- [ ] **AC12**: WHEN the generator throws `FuecRangeExhaustedException` THEN the transaction rolls back, no `Fuec` is persisted, no PDF is written to MinIO, and the controller returns a `422` with a Spanish `errors.fuec_pre_generation.range_exhausted` message.
- [ ] **AC13**: WHEN two admins submit the same service concurrently THEN exactly ONE FUEC is created — the second admin receives the "Este servicio ya tiene un FUEC vigente." error. Protected by the transaction + the re-run of `FuecPreGenerationChecks` inside it + the database-enforced uniqueness guard.

### QR + PDF artifact

- [ ] **AC14**: The generated PDF renders on letter paper with, in order: (1) header row — company name placeholder + big "FUEC Nº {consecutive}" + "Resolución {resolution_number} de {resolution_year}" / "Rango autorizado: {range_from}–{range_to}"; (2) Contrato table — número, cliente (name / company), objeto (Spanish label), vigencia; (3) Vehículo table — placa, marca, línea, modelo año, capacidad; (4) Conductor table — nombre completo, cédula (document_type + identification_number), categoría + vencimiento de licencia; (5) Servicio table — fecha, hora planificada, duración estimada, origen (municipio + dirección), destino (municipio + dirección); (6) QR verification box with the QR image (200×200) + the verification URL printed beneath in font-mono; (7) footer on every page — "Documento generado por SGTE — verificable en: {url}" + legal disclaimer "Este documento es de uso único, intransferible, y únicamente es válido junto con la tarjeta de operación del vehículo y la licencia de conducción del conductor.".
- [ ] **AC15**: WHEN an admin visits `GET /fuecs/{fuec}/pdf` THEN the server streams the stored PDF bytes from `Storage::disk($fuec->pdf_disk)->get($fuec->pdf_path)` with `Content-Type: application/pdf` and `Content-Disposition: inline; filename=fuec-{consecutive}.pdf`. No regeneration — the blob is static post-creation.

### Public verification endpoint

- [ ] **AC16**: WHEN an **unauthenticated** user visits `GET /fuec/verify/{uuid}` AND the module is enabled AND the uuid matches an `active` FUEC THEN the server renders the public Blade view `fuecs/verify.blade.php` (NOT Inertia — no auth, simpler, shareable as a printed QR) with: a large green "VIGENTE" badge, the consecutive number, generation timestamp, contract number, vehicle plate, driver full name, service date+time+route (city→city), and the SGTE branding.
- [ ] **AC17**: WHEN the same URL is visited AND the FUEC's `status = 'cancelled'` THEN the page renders a large red "ANULADO" badge + cancellation timestamp + the same summary fields as AC16 (so the verifier sees why the document is invalid).
- [ ] **AC18**: WHEN the UUID doesn't match any `Fuec` OR the module is disabled THEN the response is **404**.

### Cancel action

- [ ] **AC19**: WHEN an admin `POST`s `/fuecs/{fuec}/cancel` with a `reason` (min:10 max:500) body AND the target FUEC is `active` THEN the server flips `status` to `cancelled`, writes an activity log entry with `properties.reason`, `properties.cancelled_at`, `causer_id = $admin->id`. Non-admin users receive **403**. Missing or short reason triggers a **422** with `errors.reason`.
- [ ] **AC20**: WHEN the same endpoint is hit on a FUEC that's already `cancelled` THEN the response is **422** with "Este FUEC ya está anulado." — no second activity entry is written.

### Rebuilt Inertia pages + pill primitive

- [ ] **AC21**: `resources/js/pages/fuecs/index.tsx` is rewritten around `<DataTable>` + `useServerTable` with 6 columns (Consecutivo mono / Servicio link → `/services/{id}` / Vehículo plate / Conductor name / Estado `<FuecStatusPill />` / Acciones). Filters: `status` (active / cancelled). Sort: `consecutive_number` / `generated_at`. The `create` action opens `fuecs/create.tsx` directly (not a modal — the pre-gen validation requires a full page for the error list).
- [ ] **AC22**: `resources/js/pages/fuecs/show.tsx` is rewritten with 4 Card sections: Header (consecutive + `<FuecStatusPill />` + Descargar PDF + Cancelar button), Servicio summary, PDF preview iframe (`<iframe src="/fuecs/{id}/pdf" />`), QR + verification URL block (shows the QR image re-rendered client-side via `qrcode.react` OR just points to `/fuec/verify/{uuid}` with a button — pick the simpler path; since the PDF already embeds the QR, a plain link + URL text is sufficient). The Cancelar button is a shadcn `<AlertDialog>` — on confirm it POSTs to `/fuecs/{fuec}/cancel` with the reason captured in a textarea.

### Shared primitives

- [ ] **AC23**: `resources/js/components/fuecs/fuec-status-pill.tsx` is a new shared primitive — `active → <Badge variant="default">Vigente</Badge>`, `cancelled → <Badge variant="destructive">Anulado</Badge>`. Mirrors the pattern from `<PaymentStatusPill />` — it's a manual-enum state, not date-derived, so it lives alongside its feature folder rather than in `resources/js/lib/document-status.ts`.
- [ ] **AC24**: `resources/js/components/invoices/service-picker-dialog.tsx` gains a new optional `mode: 'invoice' | 'fuec'` prop (defaults to `'invoice'` for backward compatibility). When `mode === 'fuec'`: (a) filter excludes services with an `active` fuec instead of services with an `invoice_id`; (b) selection is single-select (radio-style) instead of multi-select (checkbox); (c) submit button label reads "Seleccionar servicio" instead of "Agregar a factura". Corresponding API endpoint — the candidate-services list for FUEC creation lives on `FuecController@candidateServices` (`GET /fuecs/candidate-services`), parallel to `InvoiceController@candidateServices`.

## Technical Specification

### Data Model

**One new table:**

```
fuec_number_ranges
├── id (bigint, PK)
├── resolution_number (string(50))
├── resolution_year (smallInteger — e.g. 2026)
├── range_from (unsignedInteger)
├── range_to (unsignedInteger)
├── active (boolean, default false)
├── notes (text, nullable)
├── created_at / updated_at (timestamps)
├── INDEX(active) where active = true UNIQUE (partial index — Postgres: "WHERE active = true"; SQLite test env: accept the unique index without the predicate, controller layer also validates)
```

**Modified `fuecs` migration (primary migration edit, per project convention):**

```
fuecs (existing, modified in place)
├── id (bigint, PK)
├── service_id (bigint, FK → services.id)
├── consecutive_number (integer)
├── generated_at (timestamp)
├── qr_code (string(255))                          [existing — repurposed to hold the UUID token]
├── status (enum: 'active', 'cancelled')
├── pdf_path (string(500), nullable)                [RENAMED from pdf_url]
├── pdf_disk (string, default 's3')                 [NEW]
├── uuid (string(36), unique)                        [NEW]
├── fuec_number_range_id (bigint, FK → fuec_number_ranges.id, onDelete restrict) [NEW]
├── created_at / updated_at (timestamps)
├── deleted_at (soft deletes)                        [NEW]
├── INDEX(consecutive_number)                        [NEW]
├── INDEX(uuid)                                      [NEW]
├── INDEX(fuec_number_range_id)                      [NEW]
```

### Enums

Add to `app/Enums/Permission.php`:

- `MANAGE_FUEC_NUMBER_RANGES = 'fuec-number-ranges.manage'`

Grant to **Admin** only in `seed_catalog_data` (and Super Admin bypasses via `Gate::before`).

`App\Enums\FuecStatus` already exists with `active` + `cancelled` — no changes.

### Routes

All FUEC routes (except the public verify) live inside the `auth, verified` middleware group AND a new `EnsureFuecEnabled` middleware that 404s when `config('sgte.fuec_enabled') === false`.

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | `/fuecs` | `FuecController@index` | `auth, verified, fuec.enabled, can:fuec.view` | `fuecs.index` |
| GET | `/fuecs/candidate-services` | `FuecController@candidateServices` | `auth, verified, fuec.enabled, can:fuec.generate` | `fuecs.candidate-services` |
| GET | `/fuecs/create` | `FuecController@create` | `auth, verified, fuec.enabled, can:fuec.generate` | `fuecs.create` |
| POST | `/fuecs` | `FuecController@store` | `auth, verified, fuec.enabled, can:fuec.generate` | `fuecs.store` |
| GET | `/fuecs/{fuec}` | `FuecController@show` | `auth, verified, fuec.enabled, can:fuec.view` | `fuecs.show` |
| GET | `/fuecs/{fuec}/pdf` | `FuecController@pdf` | `auth, verified, fuec.enabled, can:fuec.view` | `fuecs.pdf` |
| POST | `/fuecs/{fuec}/cancel` | `FuecController@cancel` | `auth, verified, fuec.enabled, can:fuec.generate` | `fuecs.cancel` |
| resource | `/fuec-number-ranges` | `FuecNumberRangeController` | `auth, verified, fuec.enabled, can:fuec-number-ranges.manage` | `fuec-number-ranges.*` |
| GET | `/fuec/verify/{uuid}` | `FuecVerifyController@show` | `fuec.enabled` (NO auth) | `fuec.verify` |

Explicitly **remove** `edit` + `update` + `destroy` from the `Route::resource('fuecs', ...)` — those verbs don't exist in this module. Use `Route::resource('fuecs', FuecController::class)->only(['index', 'create', 'store', 'show'])`.

### Permissions

| Permission | Role grant |
|---|---|
| `VIEW_FUEC` (existing) | Admin |
| `GENERATE_FUEC` (existing) | Admin |
| `MANAGE_FUEC_NUMBER_RANGES` (NEW) | Admin |

Super Admin bypasses via `Gate::before` in `AppServiceProvider` (already implemented).

### Pages

| Page | Component Path | Description |
|---|---|---|
| FUEC Index | `resources/js/pages/fuecs/index.tsx` | **REWRITE.** `<DataTable>` + `useServerTable`. 6 columns, status filter. |
| FUEC Create | `resources/js/pages/fuecs/create.tsx` | **REWRITE.** Service picker (`<ServicePickerDialog mode="fuec" />`) + pre-gen validation summary rendered before submit + Guardar button. |
| FUEC Show | `resources/js/pages/fuecs/show.tsx` | **REWRITE.** 4 Card sections (Header / Servicio / PDF preview iframe / QR + URL). Cancelar is a shadcn AlertDialog with a reason textarea. |
| FUEC Status Pill | `resources/js/components/fuecs/fuec-status-pill.tsx` | **NEW shared primitive.** Parallel to `<PaymentStatusPill />`. |
| Extend Service Picker | `resources/js/components/invoices/service-picker-dialog.tsx` | **EXTEND.** Add optional `mode: 'invoice' \| 'fuec'` prop. |
| Range Index | `resources/js/pages/fuec-number-ranges/index.tsx` | **NEW.** Basic DataTable + Crear button. |
| Range Create / Edit / Show | `resources/js/pages/fuec-number-ranges/{create,edit,show}.tsx` | **NEW.** Simple forms + detail view (pattern: Catálogos modules). |
| Public Verify | `resources/views/fuecs/verify.blade.php` | **NEW Blade.** Standalone public page (no Inertia, no AppLayout). |
| PDF Template | `resources/views/fuecs/pdf.blade.php` | **NEW Blade.** dompdf-rendered document. |

## Migration Strategy

`new` in the frontmatter, but in practice this is a mix: one new migration file (`create_fuec_number_ranges_table`) plus in-place edits to the existing `2026_02_27_225426_create_fuecs_table.php` per project convention (feedback memory `feedback_edit_primary_migrations.md` — no backfill migrations in early dev while stg/prod have no real data).

After implementing: `./vendor/bin/sail artisan migrate:fresh --seed --no-interaction` to rebuild the schema.

## Tasks

### Backend — Infrastructure

- [ ] **Task B1**: Feature flag wiring.
    - Create `config/sgte.php` with `return ['fuec_enabled' => env('SGTE_FUEC_ENABLED', false)];`.
    - Create `App\Http\Middleware\EnsureFuecEnabled` that `abort(404)` when `! config('sgte.fuec_enabled')`. Register it in `bootstrap/app.php`'s `->alias(['fuec.enabled' => EnsureFuecEnabled::class])`.
    - Update `app/Http/Middleware/HandleInertiaRequests.php` to share `auth.featureFlags` with `fuec: (bool) config('sgte.fuec_enabled'), gps: (bool) config('sgte.gps_enabled', false)` (future-proofs the shape for REQ-010).
    - Add `SGTE_FUEC_ENABLED=true` to `.env.example` with a brief comment.

- [ ] **Task B2**: Install `simplesoftwareio/simple-qrcode ^4.x` via composer. Verify its service provider auto-registers (it does by default in Laravel 12). Verify the `QrCode` facade works via `./vendor/bin/sail artisan tinker` with a throwaway call `QrCode::size(200)->generate('https://example.com')` (returns an SVG string).

### Backend — Data layer

- [ ] **Task B3**: New migration `create_fuec_number_ranges_table` with columns per the Data Model section. Include the partial unique index on `(active) WHERE active = true` via `DB::statement('CREATE UNIQUE INDEX fuec_number_ranges_one_active_uidx ON fuec_number_ranges (active) WHERE active = true;')` (Postgres). For the SQLite test env, conditionally skip the partial-index SQL via `if (Schema::getConnection()->getDriverName() === 'pgsql')` — the controller validator is the backstop.

- [ ] **Task B4**: New `App\Models\FuecNumberRange` with `$fillable` covering every column except `id` / timestamps; `$casts = ['resolution_year' => 'integer', 'range_from' => 'integer', 'range_to' => 'integer', 'active' => 'boolean']`; `LogsActivity` trait with `logOnly(['resolution_number', 'resolution_year', 'range_from', 'range_to', 'active'])`; a `fuecs(): HasMany` relation to `Fuec::class`; a `remaining(): int` accessor = `range_to - (Fuec::where('fuec_number_range_id', $this->id)->max('consecutive_number') ?? ($range_from - 1))`.

- [ ] **Task B5**: New `App\Models\FuecNumberRangeFactory` producing random valid ranges. Used by tests.

- [ ] **Task B6**: Modify `2026_02_27_225426_create_fuecs_table.php` — add `uuid`, `pdf_disk`, `fuec_number_range_id` columns (with FK + `onDelete('restrict')`), rename `pdf_url` → `pdf_path`, add `softDeletes()`, add the three new indexes. Edit the Schema::create closure directly (per project convention).

- [ ] **Task B7**: Update `app/Models/Fuec.php`:
    - Add `HasUuids` trait (`Illuminate\Database\Eloquent\Concerns\HasUuids`) — overrides `uniqueIds()` to return `['uuid']` so Laravel generates it automatically on create.
    - Add `$fillable`: `uuid`, `pdf_disk`, `pdf_path` (remove `pdf_url`), `fuec_number_range_id`.
    - Add `SoftDeletes` trait.
    - Add `fuecNumberRange(): BelongsTo` relation.
    - Update `$casts` if needed (no change expected — status already casts to `FuecStatus`).
    - Update `logOnly` in `getActivitylogOptions` to include the new columns.
    - Update `toSearchableArray()` to project the new keys (rename `pdf_url` → `pdf_path`, add `uuid` + `fuec_number_range_id`).

- [ ] **Task B8**: Update `database/factories/FuecFactory.php` (if it exists — create if missing) to produce a FUEC with a pre-existing `FuecNumberRange`, default `status = active`, synthetic UUID + `pdf_path`.

- [ ] **Task B9**: Add `MANAGE_FUEC_NUMBER_RANGES = 'fuec-number-ranges.manage'` case to `app/Enums/Permission.php`. Add a human label in `labels()`. Grant to Admin in `database/migrations/2026_03_13_000000_seed_catalog_data.php` (or wherever the role-permission seeding lives — verify by grep).

- [ ] **Task B10**: Run `./vendor/bin/sail artisan enum:typescript` to regenerate `resources/js/enums/Permission.ts`.

### Backend — Validation + generation core

- [ ] **Task B11**: Extract the shared document-expiry + contract-coverage checks from `app/Http/Requests/ServiceStoreRequest.php` into a reusable class `app/Support/ServiceDocumentChecks.php` with static methods: `contractCoversDate(Contract $contract, Carbon $date): ?string`, `vehicleDocumentsValid(Vehicle $vehicle, Carbon $date): array<string>`, `driverLicenseValid(Driver $driver, Vehicle $vehicle, Carbon $date): array<string>`. Each returns a Spanish error message (or array of messages) on failure, or `null` / `[]` when valid. The `LICENSE_CATEGORY_MAP` constant moves to this class (or to a new `App\Support\LicenseCategory` enum-like helper — pick the lighter option). Update `ServiceStoreRequest` to call the extracted methods.

- [ ] **Task B12**: New `App\Rules\FuecPreGenerationChecks` — an invokable class that accepts a `Service $service` in its constructor and, when invoked by a `Validator::after()` hook, appends Spanish error messages for every failed check per AC9. Internally calls `ServiceDocumentChecks::*` + checks for an active `FuecNumberRange` + range availability + no active FUEC on the service.

- [ ] **Task B13**: New `App\Http\Requests\FuecStoreRequest`:
    - `authorize()` → `Gate::allows(Permission::GENERATE_FUEC->value)`.
    - `rules()` → `['service_id' => ['required', 'integer', 'exists:services,id']]`.
    - `after()` → returns an array with one closure that resolves `$this->service_id` to a `Service` and runs `FuecPreGenerationChecks` against it.

- [ ] **Task B14**: New `App\Http\Requests\FuecCancelRequest`:
    - `authorize()` → `Gate::allows(Permission::GENERATE_FUEC->value)`.
    - `rules()` → `['reason' => ['required', 'string', 'min:10', 'max:500']]`.
    - `messages()` → Spanish messages for each rule.

- [ ] **Task B15**: New `App\Exceptions\FuecRangeExhaustedException` (extends `RuntimeException`) with a static `for(FuecNumberRange $range): self` factory.

- [ ] **Task B16**: New `App\Services\FuecGenerator` with public method `generateFor(Service $service, User $causer): Fuec` implementing the 9-step transaction per AC11. Private helpers: `resolveActiveRange(): FuecNumberRange` (locked select), `computeNextConsecutive(FuecNumberRange $range): int` (max + 1 or range_from), `renderPdf(Service $service, Fuec $fuec, FuecNumberRange $range): string` (returns binary PDF bytes), `storePdf(string $bytes, int $consecutive): array{disk: string, path: string}`. The service is constructor-injectable so tests can mock the storage disk + QR renderer.

### Backend — Controllers + routes

- [ ] **Task B17**: Rewrite `app/Http/Controllers/FuecController.php`:
    - `index` — paginate via `QueryBuilder::for(Fuec::class)->with(['service.vehicle:id,plate', 'service.driver:id,first_name,first_lastname', 'fuecNumberRange:id,resolution_number,resolution_year'])->allowedFilters([AllowedFilter::exact('status'), 'consecutive_number'])->allowedSorts(['consecutive_number', 'generated_at'])->defaultSort('-generated_at')->paginate($request->perPage())->withQueryString()`.
    - `create` — Inertia page; payload includes `mostRecentCandidate` (the most recent closed service with no active FUEC) as a convenience.
    - `candidateServices` — returns JSON list of closed services with no active FUEC (paginated + searchable). Parallel to `InvoiceController@candidateServices`.
    - `store` — injects `FuecGenerator`, calls `generateFor(...)`, catches `FuecRangeExhaustedException` + throws `ValidationException` with a Spanish top-level message, returns `redirect()->route('fuecs.show', $fuec)`.
    - `show` — eager-loads `service.vehicle`, `service.driver`, `service.contract.thirdParty`, `fuecNumberRange`.
    - `pdf` — streams `Storage::disk($fuec->pdf_disk)->get($fuec->pdf_path)` with Content-Type application/pdf, Content-Disposition inline.
    - `cancel` — accepts `FuecCancelRequest`, transitions `active` → `cancelled`, writes activity entry, returns redirect to show.
    - Remove `edit`, `update`, `destroy`.

- [ ] **Task B18**: New `app/Http/Controllers/FuecNumberRangeController.php` — standard resource controller with `Gate::authorize(Permission::MANAGE_FUEC_NUMBER_RANGES->value)` on every action, and an `active` toggle enforced by catching the unique-index violation and translating it into a validator error.

- [ ] **Task B19**: New `app/Http/Requests/FuecNumberRangeStoreRequest` + `FuecNumberRangeUpdateRequest` with `resolution_number` (required, string, max:50), `resolution_year` (required, integer, between:2000:2100), `range_from` (required, integer, min:1), `range_to` (required, integer, gt:range_from), `active` (boolean), `notes` (nullable, string, max:1000).

- [ ] **Task B20**: New `app/Http/Controllers/FuecVerifyController.php` — public, single `show(string $uuid): \Illuminate\Contracts\View\View` action. Looks up the FUEC by `uuid` with eager-loaded `service.vehicle.municipality.department`, `service.contract.thirdParty.documentType`, `service.driver.documentType`, `fuecNumberRange`. Renders `view('fuecs.verify', compact('fuec'))`. 404s when the UUID doesn't match.

- [ ] **Task B21**: Rewrite the FUEC routes block in `routes/web.php` per the Routes table above. Group all authenticated routes under `Route::middleware(['auth', 'verified', 'fuec.enabled'])` with individual `can:*` middlewares per action. Register `/fuec/verify/{uuid}` OUTSIDE the auth group, gated only by `fuec.enabled`.

### Frontend — Primitives

- [ ] **Task F1**: New `resources/js/components/fuecs/fuec-status-pill.tsx` — `<Badge variant>` with the two states (Vigente / Anulado). Reference: `resources/js/components/invoices/payment-status-pill.tsx`.

- [ ] **Task F2**: Extend `resources/js/components/invoices/service-picker-dialog.tsx` with an optional `mode?: 'invoice' | 'fuec'` prop (defaults to `'invoice'`). The internal logic branches on `mode` to control (a) the candidate-services endpoint URL (`/invoices/{id}/candidate-services` vs `/fuecs/candidate-services`), (b) single-select radio vs multi-select checkbox rendering, (c) submit label. Type-safe — the callback signature widens with a discriminated union. The invoice callers pass no `mode` prop → backward compatible.

### Frontend — FUEC pages

- [ ] **Task F3**: Rewrite `resources/js/pages/fuecs/index.tsx` — `<DataTable>` + `useServerTable`, 6 columns, `status` filter, Crear button link to `/fuecs/create`. Row tint: none (no orthogonal "por vencer" state). Reference convention: `resources/js/pages/invoices/index.tsx` after invoices-crud.

- [ ] **Task F4**: New `resources/js/pages/fuecs/columns.tsx` — 6 `ColumnDef<FuecRow>` entries (Consecutivo / Servicio link / Vehículo / Conductor / Estado pill / Acciones → "Ver" icon link to show page; no Eliminar since delete isn't a verb).

- [ ] **Task F5**: Rewrite `resources/js/pages/fuecs/create.tsx` — inline `<ServicePickerDialog mode="fuec">` for picking the service; a pre-gen validation summary renders before the submit button (list of checks; unchecked items grey'd out; failing items rendered in destructive color with the returned Spanish message); submit button POSTs `/fuecs` with `service_id`. Inertia's `errors.fuec_pre_generation.*` surface as top-level list items.

- [ ] **Task F6**: Rewrite `resources/js/pages/fuecs/show.tsx` — 4 Card sections per AC22. Cancelar button uses shadcn `<AlertDialog>` with a textarea for `reason` (min:10 max:500, client-side `required + minLength` for UX). Descargar PDF is a plain `<a href="/fuecs/{id}/pdf" target="_blank">` with the icon.

### Frontend — FuecNumberRange pages

- [ ] **Task F7**: New `resources/js/pages/fuec-number-ranges/index.tsx` — DataTable with 6 columns. Reference convention: `resources/js/pages/eps/index.tsx` (simple catalog module).

- [ ] **Task F8**: New `resources/js/pages/fuec-number-ranges/create.tsx` + `edit.tsx` — shared form component extracted to `resources/js/components/fuec-number-ranges/fuec-number-range-form.tsx`. Fields: `resolution_number`, `resolution_year` (Input type=number), `range_from`, `range_to`, `active` (Switch), `notes` (Textarea).

- [ ] **Task F9**: New `resources/js/pages/fuec-number-ranges/show.tsx` — 2 Card sections: Datos de la Resolución + Estadísticas de Uso (consecutivos usados / restantes / primer / último FUEC emitido). Links back to `/fuecs?filter[fuec_number_range_id]={id}` when a service's filter can be wired (optional — can ship without it).

### Frontend — Sidebar + types

- [ ] **Task F10**: Update `resources/js/components/app-sidebar.tsx` to read `auth.featureFlags.fuec` from the shared Inertia props; hide the FUEC group entry (and its "Documentos FUEC" + new "Rangos FUEC" entries) when the flag is `false`. Verify the sidebar gracefully handles older sessions / unauthenticated requests where `auth.featureFlags` might be undefined (fall back to `false`).

- [ ] **Task F11**: Update `resources/js/types/auth.ts` (or wherever `Auth` is typed) to include `featureFlags: { fuec: boolean; gps: boolean }`.

### Blade templates

- [ ] **Task D1**: New `resources/views/fuecs/pdf.blade.php` — letter-paper, dompdf-compatible HTML (no flex/grid, use tables + inline styles, DejaVu Sans font). Structure per AC14. Reference: `resources/views/invoices/pdf.blade.php`.

- [ ] **Task D2**: New `resources/views/fuecs/verify.blade.php` — standalone Blade page (no `@extends('layouts.app')` — a self-contained HTML document with minimal inline CSS + the shadcn color palette hex values). Renders the VIGENTE/ANULADO badge (big, top-center), the summary fields (2-col grid), and SGTE branding at the footer. Legible on mobile (responsive viewport meta tag, padding).

### Tests

- [ ] **Task T1**: `tests/Feature/Services/FuecGeneratorTest.php` — **9 scenarios**:
    1. Happy path — returns Fuec with next consecutive, writes PDF to the fake `s3` disk (use `Storage::fake('s3')`), activity log entry exists.
    2. Service not closed → throws ValidationException with Spanish message.
    3. Contract not vigente → rejects.
    4. Expired SOAT → rejects.
    5. Expired RTM → rejects.
    6. Expired operation card → rejects.
    7. Expired license → rejects.
    8. Incompatible license category → rejects.
    9. Range exhausted → throws `FuecRangeExhaustedException`.

- [ ] **Task T2**: `tests/Feature/Http/Controllers/FuecControllerTest.php` — **happy-path POST, auth 403s for non-admin, feature-flag 404, pdf streams, cancel happy path, cancel on already-cancelled 422, candidateServices endpoint shape**. ~10 tests.

- [ ] **Task T3**: `tests/Feature/Http/Controllers/FuecVerifyControllerTest.php` — **active → VIGENTE in response HTML, cancelled → ANULADO in response HTML, nonexistent uuid → 404, flag disabled → 404**.

- [ ] **Task T4**: `tests/Feature/Http/Controllers/FuecNumberRangeControllerTest.php` — **CRUD happy paths + non-admin 403 + "already active range" rejection test + range_from >= range_to rejection**.

- [ ] **Task T5**: Extend `tests/Feature/Http/Requests/FuecStoreRequestTest.php` (create if absent) — covering every individual check in `FuecPreGenerationChecks` for narrow failure-mode assertions.

- [ ] **Task T6**: `tests/Browser/FuecGenerationTest.php` — Dusk. Admin navigates to `/fuecs`, clicks Crear, picks a valid service via the picker dialog, submits, asserts the show page renders with Consecutivo + "Descargar PDF" button + QR URL. Setup: `migrate:fresh --no-interaction` + seed a FuecNumberRange + a closed service with valid docs + valid driver license.

- [ ] **Task T7**: `tests/Browser/FuecPublicVerifyTest.php` — Dusk. Unauthenticated browser visits `/fuec/verify/{uuid}` for an active FUEC → VIGENTE visible; then via `loginAs($admin)` visits `/fuecs/{id}`, clicks Cancelar, provides a reason, submits; logout; revisit `/fuec/verify/{uuid}` → ANULADO visible.

### Docs

- [ ] **Task X1**: Update `docs/phases/phase-5-optionals-deploy.md` §5.1 — flip the status from "scaffolded only" to "✅ done (behind feature flag `SGTE_FUEC_ENABLED`)"; update the top-of-file status line.

- [ ] **Task X2**: Update `docs/phases/README.md` Phase 5 row to reflect FUEC done / GPS pending.

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

1. Seed a `FuecNumberRange` via `./vendor/bin/sail artisan tinker`.
2. Login as admin; navigate to `/fuec-number-ranges`; snapshot — verify the range appears with "Activo: Sí".
3. Navigate to `/fuecs/create`; pick a closed service via the picker; verify the pre-gen validation summary shows all green checks; submit; land on `/fuecs/{id}` with the PDF preview iframe visible.
4. Click the verification URL; new tab opens on `/fuec/verify/{uuid}` showing VIGENTE.
5. Back on the show page, click Cancelar; type a reason; confirm; status flips to Anulado.
6. Re-visit `/fuec/verify/{uuid}`; page shows ANULADO.
7. Flip `SGTE_FUEC_ENABLED=false` in `.env`, clear config cache, revisit `/fuecs` — 404. Sidebar no longer shows FUEC entries.
8. Logout, re-login as operator; `/fuec-number-ranges` → 403; `/fuecs` → 403 (since the gate doesn't see permission).
9. Logout, re-login as admin, flip the flag back, `/fuec/verify/{uuid}` works again.

- [ ] Scenario 1: Admin registers a FuecNumberRange and sees it in the list.
- [ ] Scenario 2: Admin generates a FUEC for a valid closed service.
- [ ] Scenario 3: Pre-gen validation fails with specific messages for a service whose vehicle has expired SOAT.
- [ ] Scenario 4: Generated PDF renders in the show page's iframe.
- [ ] Scenario 5: Public verify page shows VIGENTE.
- [ ] Scenario 6: Admin cancels the FUEC; verify page flips to ANULADO.
- [ ] Scenario 7: `SGTE_FUEC_ENABLED=false` hides the sidebar entry and 404s the routes.
- [ ] Scenario 8: Operator 403s on both `/fuecs` and `/fuec-number-ranges`.

### 2. Backend regression — Pest feature tests (required)

Tasks T1–T5 above MUST ship with this requirement. Run via `./vendor/bin/sail test --compact`. The full suite MUST stay green.

### 3. UI regression — Laravel Dusk browser tests (required)

Tasks T6–T7 above MUST ship under `tests/Browser/FuecGenerationTest.php` and `tests/Browser/FuecPublicVerifyTest.php`. Each test MUST:

- Assert no `[role="alert"]`, exception trace, or visible error UI (except where explicitly expected, e.g. the 403 page in negative scenarios).
- Assert Spanish strings render with correct diacritics: **Generar FUEC**, **Consecutivo**, **Vigente**, **Anulado**, **Resolución**, **Rango autorizado**, **Descargar PDF**, **Anular**, **Motivo de anulación**, **Documento generado por SGTE — verificable en**.
- Take screenshots at key interaction steps.
- Use `migrate:fresh --no-interaction` + inline fixtures (no `--seed`).

Run via `./vendor/bin/sail dusk --filter=Fuec`. CI does not run Dusk currently; the suite MUST run cleanly locally before merge.

### 4. API endpoints — curl

The only public (non-Inertia) endpoint is `GET /fuec/verify/{uuid}`. Verify with:

```bash
# With feature flag enabled + a real UUID from the database
UUID=$(./vendor/bin/sail artisan tinker --execute='echo App\\Models\\Fuec::first()?->uuid ?? "none";')
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/fuec/verify/$UUID
# Expected: 200

curl -s -o /dev/null -w "%{http_code}\n" http://localhost/fuec/verify/deadbeef-dead-beef-dead-beefdeadbeef
# Expected: 404

# With feature flag disabled
SGTE_FUEC_ENABLED=false php artisan config:clear
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/fuec/verify/$UUID
# Expected: 404
```

## Dependencies

- **Phase 1 + Phase 2** (catalogs + services + day-status) — merged; provides the Vehicle / Driver / Contract / Service models this requirement reads from.
- **invoice-pdf-generation** — merged; provides `barryvdh/laravel-dompdf` ^3.1 already installed + `resources/views/invoices/pdf.blade.php` as PDF Blade convention.
- **invoice-service-assignment** — merged; provides `<ServicePickerDialog />` which this requirement extends with a `mode` prop.
- **REQ-004 / REQ-005 validators** on Vehicle and Driver — already implemented; refactored into `App\Support\ServiceDocumentChecks` by Task B11 for reuse.
- **No other hard dependencies.**
- **New packages**: `simplesoftwareio/simple-qrcode ^4.x`.

## Notes

### Why the MinTransporte range as its own CRUD

MinTransporte authorizes a fixed consecutive range via a resolution (a printed government document). When the range is exhausted, the company must request another resolution and register the new range. A dedicated CRUD gives admins a first-class UI for managing this — printing the "Resolución X de Y" metadata on every FUEC and tracking usage over time. Alternative designs (config file, single-row settings table) would bury this compliance data where it's hard to audit or migrate.

### Feature flag vs permission

SRS §REQ-007 explicitly calls out: "This module is OPTIONAL and is initially DISABLED. The related logic remains in the system so that it can be activated or resumed in the future." The feature flag + middleware model preserves the code path (for future activation) while making the module's absence a deploy-time decision, not a role-matrix one. Separately, `VIEW_FUEC` + `GENERATE_FUEC` + `MANAGE_FUEC_NUMBER_RANGES` are the role-level gates for users who CAN see a world where FUEC is on.

### Why no soft-delete at the model level when module is disabled

The `fuecs` table's `softDeletes()` column is for admin cleanup of stale rows — it's orthogonal to the feature flag. Turning the module off doesn't delete any data; turning it back on re-exposes the full history.

### Public verification: Blade instead of Inertia

The `/fuec/verify/{uuid}` page serves three use-cases: a government inspector scanning the QR at a checkpoint; a client verifying a vendor's FUEC before signing off on an invoice; an internal user spot-checking. None of these should require a JavaScript runtime or a logged-in session. A standalone server-rendered Blade page loads instantly on any device (including cheap phones), is trivially cacheable at the edge, and has a clear printable layout.

### Shared primitives introduced

- `<FuecStatusPill />` — parallel to `<PaymentStatusPill />` + `<IncidentSeverityPill />`; manual-enum state, lives in the feature folder.
- `<ServicePickerDialog mode="fuec">` — the dialog extracted during invoice-service-assignment gets its first reuse with a different selection shape.
- `App\Support\ServiceDocumentChecks` — the first extraction of `ServiceStoreRequest`'s inline validation helpers into a reusable class. Both `ServiceStoreRequest` and `FuecPreGenerationChecks` call through it; future places that need the same domain rule (e.g. a "validate now" button on the service edit page) inherit for free.

### Estimated commit count

About **24–28 commits**, in order:
1. docs (this requirement).
2. B1 + .env.example edit (feature flag + middleware).
3. B2 (package install).
4. B3 (migration for fuec_number_ranges).
5. B4 + B5 (model + factory).
6. B6 (modify fuecs migration).
7. B7 + B8 (Fuec model updates + factory).
8. B9 + B10 (permission + TS regeneration).
9. B11 (extract ServiceDocumentChecks).
10. B12 (FuecPreGenerationChecks rule).
11. B13 + B14 + B15 (request classes + exception).
12. B16 (FuecGenerator service).
13. T1 (FuecGenerator tests).
14. B17 + B21 (FuecController rewrite + routes).
15. T2 (FuecController tests).
16. B18 + B19 (FuecNumberRangeController + requests).
17. T4 (FuecNumberRangeController tests).
18. B20 (FuecVerifyController).
19. T3 (FuecVerifyController tests).
20. D1 (PDF Blade).
21. D2 (Verify Blade).
22. F1 + F2 (FuecStatusPill + ServicePickerDialog extension).
23. F3 + F4 (index rewrite + columns).
24. F5 (create page).
25. F6 (show page).
26. F7 + F8 + F9 (FuecNumberRange pages).
27. F10 + F11 (sidebar + types).
28. T6 + T7 (Dusk tests).
29. X1 + X2 (phase docs + requirement status flip).

Larger than most rebuilds because this requirement ships a full business module (not just a CRUD), including: one cross-cutting extraction (`ServiceDocumentChecks`), one new CRUD (`FuecNumberRange`), three new controllers, two Blade templates, and a public-facing endpoint.
