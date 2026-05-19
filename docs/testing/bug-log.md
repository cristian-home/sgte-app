# Phase 3 — Bug Log

> Findings from Playwright MCP exploration against the live Sail stack. Each entry references a scenario ID from [`scenario-catalog.md`](./scenario-catalog.md). No fixes are applied during this phase — the user triages from this log.

## Severity legend

- **P0** — data loss, security boundary breach, RBAC bypass, immutability rule violated (e.g., Operación can edit EJECUTADO service).
- **P1** — logical failure that lets invalid state through (e.g., driver double-booking succeeds, FUEC generated with expired vehicle doc).
- **P2** — UX / validation / cosmetic.

## Entry template

```
### BUG-NN — <one-line title>

- **Severity**: P0 / P1 / P2
- **Scenario**: `<SVC-LC-04>` (link to catalog)
- **Route / file**: e.g., `POST /services` → `app/Http/Requests/ServiceStoreRequest.php`
- **Expected**: <what the catalog says should happen>
- **Observed**: <what actually happens>
- **Repro**:
  1. …
  2. …
- **Suspected cause**: <one line>
- **Notes**: <anything else>
```

## Status (2026-05-18 — all 4 actionable bugs fixed)

| ID | Status | Test verifying behavior |
|---|---|---|
| BUG-01 | ✅ Fixed — `ServiceController::maybeBridgeToGenericContract` + `create_generic_contract` opt-in flag | `SVC-LC-13` |
| BUG-02 | n/a — Q5 retracted | — |
| BUG-03 | ✅ Fixed — `ServiceStoreRequest::validateExecutedDayRestriction` admin-with-justification bypass + `ServiceController::store` audit log | `SVC-LC-17`, `SVC-LC-17b` (operator still rejected), `SVC-LC-17c` (justification required) |
| BUG-04 | n/a — Q6 retracted | `SVC-LC-12` (verifies hard block) |
| BUG-05 | ✅ Fixed — `DayStatusUpdateRequest::after()` transition guard + SA override w/ justification + controller clears executor_id/executed_at | `DAY-LC-01`, `DAY-LC-01b` (SA reversal succeeds), `DAY-LC-01c` (justification required) |
| BUG-06 | ✅ Fixed — `FuecGenerator::generateFor` auto-cancels previous active FUEC with reason `"Superseded by new FUEC generation"`; pre-check no longer rejects | `FUEC-LC-01` |

Pest verification: `./vendor/bin/sail test tests/Browser/E2eHardCasesTest.php` → 28 passing.
Full Dusk suite regression check: `./vendor/bin/sail dusk` → 142 passing, 5 pre-existing failures unrelated to these fixes.

## Triage decisions (2026-05-18)

User reviewed the 6 spec divergences. Final calls:

| ID | Decision | Notes |
|---|---|---|
| BUG-01 | **Bug — fix** | Auto-create generic contract on out-of-window service date. UI: combobox can offer "Crear contrato genérico para esta fecha." |
| BUG-02 | **Not a bug — catalog/Q5 update** | The code's `GEN-NNNN-YYYY` naming is the canonical convention. The prior Q5 answer ("Generic Contract #N") is retracted. Auto-creation from BUG-01 must also use `GEN-NNNN-YYYY`. |
| BUG-03 | **Bug — fix** | Validation should allow Admin to bypass when `justification` is supplied; non-admin still 422. Mirror `ServiceUpdateRequest`'s EJECUTADO logic. |
| BUG-04 | **Not a bug — Q6 retracted** | Hard block on `has_social_security = false` is the correct behavior. Reasoning: legal liability if a driver without active SS is in an accident. Docs updated to reflect this. |
| BUG-05 | **Bug — fix** | Add transition guard: `executed → projected` rejected for Admin/Operator. Super Admin may override with `justification` field (10–500 chars) + audit log entry. `executor_id` / `executed_at` should clear on a permitted reversal. |
| BUG-06 | **Bug — fix** | Auto-cancel previous active FUEC inside the same transaction as new generation. Standardized cancellation reason: `"Superseded by new FUEC generation"`. |

**Bugs to fix:** BUG-01, BUG-03, BUG-05, BUG-06.
**Not bugs (catalog/docs updated):** BUG-02, BUG-04.

Phase 4 Pest tests will:
- For bugs-to-fix: write the test against the **intended** behavior and wrap in `->todo('bug-log:BUG-NN')` until each is fixed.
- For not-bugs: write the test against the **current** behavior; no todo wrapper.

## Findings

_(Populated during Phase 3 exploration. Empty entries mean nothing logged yet for that section.)_

### Cross-cutting (AUTH, LAYER, TZ)

### Master data (VEH, DRV, TP, CTR)

### BUG-01 — Generic contract auto-creation on out-of-window service date is NOT implemented

- **Severity**: P1 (logical failure — service rejected where Q5 says it should auto-create a generic contract)
- **Scenario**: `CTR-LC-02`, `SVC-LC-13`, `TZ-06`
- **Route / file**: `app/Http/Requests/ServiceStoreRequest.php:203-240` (`validateContractCoversDate`)
- **Expected** (per user's Q5 answer + SRS REQ-006 AC 3): when a service's `service_date_local` falls outside the selected contract's `[start_at, end_at)` window, the system auto-creates a temporary generic contract for that service.
- **Observed**: the FormRequest validator adds an error `"La fecha del servicio no esta dentro del rango del contrato."` and rejects the service POST. The user has to navigate manually to `/contracts/create`, tick `is_generic`, save (which gets `GEN-NNNN-YYYY`), then re-open `/services/create` and pick it.
- **Repro**:
  1. Pick any existing non-generic contract `C` with `end_at = 2026-12-16` (the seeded C3 is such).
  2. As Operator or Admin, POST `/services` with `contract_id = C.id`, `service_date_local = 2027-01-15` (outside window).
  3. Response: 422 with the error above. No auto-generic created.
- **Suspected cause**: `ServiceStoreRequest::validateContractCoversDate()` calls `$validator->errors()->add(...)` rather than delegating to a service-layer auto-generic generator.
- **Notes**: Bundled with BUG-02 below — naming convention also diverges.

### BUG-02 — Generic contract naming uses `GEN-NNNN-YYYY`, not `Generic Contract #N`

- **Severity**: P2 (cosmetic / spec divergence; functional sequencing works)
- **Scenario**: `CTR-LC-02`, `CTR-LC-03`
- **Route / file**: `app/Http/Controllers/ContractController.php:147-153`
- **Expected** (per Q5): contracts marked `is_generic = true` are named `Generic Contract #1`, `Generic Contract #2`, … with a single global sequential N.
- **Observed**: numbering format is `GEN-%04d-%d` resolving to `GEN-0001-2026`, `GEN-0002-2026`, …, with **per-year scoping** (the sequence resets each calendar year because the `count()` filter is `LIKE 'GEN-%-{$year}'`).
- **Repro**: As Admin, POST `/contracts` with `is_generic = true`, `contract_number = ""`. Inspect resulting row.
- **Suspected cause**: implementation predates the Q5 spec decision; or the spec was changed after implementation.
- **Notes**: per-year sequencing also means two contracts named `GEN-0001-2026` and `GEN-0001-2027` could coexist; a global monotone sequence (Q5 intent) would prevent that.


### Services & Planificador (SVC, GNT)

### BUG-03 — New service on EJECUTADO day is hard-rejected; no admin-exception path

- **Severity**: P0 (spec divergence, blocks legitimate late-add use case)
- **Scenario**: `SVC-LC-17`, `DAY-LC-02`
- **Route / file**: `app/Http/Requests/ServiceStoreRequest.php:190-201` (`validateExecutedDayRestriction`)
- **Expected** (per user's Q4 answer): "The new service is an exception that can be added after the fact, but it doesn't change the status of the day as a whole."
- **Observed**: every role — including Admin and Super Admin — gets a 422 with error `"No se pueden crear servicios en un día ejecutado."` (Super Admin bypasses authorize() but not validation; the error is added inside `after()`, which runs even for SA.)
- **Repro**:
  1. Execute a day (any seeded projected day where all services are closed).
  2. As Admin (or any other role), POST `/services` with `service_date_local` = that day's date and valid body.
  3. Response: 422; `validateExecutedDayRestriction` adds the error to `service_date`.
- **Suspected cause**: the validator was written before Q4 was resolved. To honor Q4, the check should either be gated to non-admin roles or replaced with a "justification required" branch akin to `ServiceUpdateRequest`'s EJECUTADO handling.
- **Notes**: even Super Admin can't add a late service via the normal flow — they'd have to go through tinker or a custom endpoint. Pest test wraps as `->todo('bug-log:BUG-03')` until fixed.

### BUG-04 — `has_social_security = false` hard-blocks driver assignment

- **Severity**: P1 (spec divergence)
- **Scenario**: `SVC-LC-12`, `DRV-LC-03`
- **Route / file**: `app/Http/Requests/ServiceStoreRequest.php:353-355` (`validateDriverLicense`)
- **Expected** (per user's Q6 answer + SRS REQ-005 AC 5): "The assignment is allowed to proceed, but a warning is shown to the user and an incident is automatically recorded."
- **Observed**: line 353 `if ($driver->has_social_security === false) { $validator->errors()->add('driver_id', 'El conductor no tiene seguridad social activa.'); }` — hard-rejects the service create with a validation error. No warning toast path, no auto-incident creation.
- **Repro**:
  1. Pick any driver, set `has_social_security = false` (e.g., D1 in tinker).
  2. POST `/services` with `driver_id = D1.id` and otherwise-valid body.
  3. Response: 422 with the `driver_id` error.
- **Suspected cause**: implementation pattern-matched the other "blocking" license checks. The Q6 design wants a soft block — accept the create but log an automatic incident and surface a warning.
- **Notes**: the auto-incident mechanism doesn't appear to exist anywhere — there's no code in `ServiceController::store` that creates a `ServiceIncident` after an SS-less driver is assigned. So fixing this is more than removing the validator line; the warning+incident workflow has to be built.

### BUG-05 — EJECUTADO day is reversible via `PUT /day-statuses/{id}`

- **Severity**: P0 (audit / immutability violation — REQ-001, REQ-009 spec the day-status state machine as one-way)
- **Scenario**: `DAY-LC-01`
- **Route / file**: `app/Http/Requests/DayStatusUpdateRequest.php`, `app/Http/Controllers/DayStatusController.php@update`
- **Expected** (per user's Q3 answer): EJECUTADO is permanent. No path can flip a day back to PROYECTADO.
- **Observed**: `DayStatusUpdateRequest::rules()` validates `'status' => ['required', Rule::enum(DayStatusEnum::class)]` and `authorize()` only checks `Gate::allows('day-summary.execute')`. Nothing blocks `status: executed → projected`. Any user with `day-summary.execute` (Admin or Operator) can PUT the row back.
- **Repro**:
  1. Find or execute an EJECUTADO day (DayStatus with `status = 'executed'`).
  2. As Admin or Operator, PUT `/day-statuses/{id}` with `date` unchanged, `status: "projected"`.
  3. Response: 302 success; DB row flipped to projected.
- **Suspected cause**: missing state-transition guard. Should reject any transition out of `executed`.
- **Notes**: also means the controller doesn't unset `executed_at` / `executor_id` when reverted, leaving the row in an inconsistent state (status=projected but executed_at populated). Compounds the bug.

### BUG-06 — FUEC supersede is not automatic; existing active FUEC blocks new generation

- **Severity**: P1 (spec divergence + UX friction)
- **Scenario**: `FUEC-LC-01`
- **Route / file**: `app/Rules/FuecPreGenerationChecks.php:120`
- **Expected** (per user's Q7 answer): "When a new FUEC is generated for a service, it supersedes the previous one, which becomes inactive."
- **Observed**: pre-generation check rejects with `'Este servicio ya tiene un FUEC vigente. Anule el actual antes de generar uno nuevo.'` ("This service already has an active FUEC. Cancel the current one before generating a new one.")
- **Repro**:
  1. Generate a FUEC for service S1 (status=active).
  2. POST `/fuecs` again with `service_id = S1` and the same body.
  3. Response: 422 with the above error.
- **Suspected cause**: pre-check predates the Q7 supersede design. To match Q7, the rule should let the new generation proceed and atomically cancel the previous active one in the same transaction.
- **Notes**: if Q7 was a future-design statement (rather than a current-state spec), then this is "not yet implemented" rather than a bug. Either way the scenario doesn't pass as written; logging.

### Other findings (positive confirmations)

- ✅ **Q1 license category mapping is permissive** in `ServiceStoreRequest::LICENSE_CATEGORY_MAP` (verified earlier — Bus/Buseta → {C2, C3}; Van/Auto → {C1, C2, C3}).
- ✅ **Q2 driver double-booking is blocked** by `NoScheduleConflict` applied to `driver_id` (line 103-110 of `ServiceStoreRequest::rules()`) with half-open-interval semantics in `app/Rules/NoScheduleConflict.php`.
- ✅ **Q8 dual-flag terceros supported** — TP5 in seed has both `is_customer = true` AND `is_provider = true`; no exclusivity constraint in `ThirdPartyStoreRequest` (verified earlier in tinker).
- ✅ **EJECUTADO service edit gating works correctly** — `ServiceUpdateRequest::authorize/rules/after()` properly:
  - 403s Operator on EJECUTADO day.
  - Requires `justification` (10–500 chars) for Admin / Super Admin on EJECUTADO day.
  - Whitelists `unit_value`, `quantity`, `billing_groups`, `payment_method`, `invoice_id` only for Accounting on EJECUTADO day.
- ✅ **`NoScheduleConflict` uses correct half-open semantics** — comparison is `$existingStart < $newEnd && $newStart < $existingEnd`. A service ending at 11:00 + new service starting at 11:00 do NOT conflict (boundary touch is OK). Confirms catalog scenario `SVC-LC-02`.
- ✅ **Retroactive entry detection** — `validateRetroactiveEntry` rejects future/today-closed (correct), and requires `manual_entry_justification` for past-day closed (correct, but the catalog should add `SVC-VAL-11` to test this).
- ✅ **FUEC active-range exclusivity** — `FuecNumberRangeController:151` rejects activating a second range with `'Ya existe un rango activo. Desactive el rango vigente antes de activar uno nuevo.'` (FRG-LC-01 — works but requires manual deactivation, not atomic activation).


### Day cycle (DAY)

### Incidents (INC)

### Invoices (INV)

### FUEC + Number Ranges (FUEC, FRG)

### GPS (GPS)

### Users / Roles / Audit Log (USR, ROLE, AUD)

### BUG-08 — Validation error message uses English "line" instead of Spanish "línea"

- **Severity**: P2 (cosmetic, single label)
- **Scenario**: walkthrough catch on `/vehicles/create`
- **Route / file**: likely `lang/es/validation.php` attributes section — `line` attribute missing the `línea` translation
- **Expected**: blank-submit on the Línea field should render "El campo línea es obligatorio."
- **Observed**: renders "El campo line es obligatorio." (raw column name).
- **Repro**: Login as admin, visit `/vehicles/create`, submit blank, scroll to Línea field. All other field labels correctly localized (código interno, placa, etc.).
- **Suspected cause**: Missing attribute mapping in `lang/es/validation.php` `attributes` array.

### BUG-09 — Four catalog "create" pages are Blueprint placeholder stubs

- **Severity**: P1 (admin functionality non-existent in UI)
- **Scenario**: walkthrough catch on Catálogos sidebar group
- **Route / file**: `resources/js/pages/{document-types,eps,pension-funds,severance-funds}/create.tsx`
- **Expected**: real create form with code/name/active inputs and submit handler
- **Observed**: page renders heading like "Eps Create", paragraph "This component was auto-generated by Blueprint.", and a `{}` block. No form, no functional submit. Page title also reads in kebab/English ("Eps Create - SGTE").

  Affected (4 of 5):
  - `/document-types/create` → "Document-Types Create"
  - `/eps/create` → "Eps Create"
  - `/pension-funds/create` → "Pension-Funds Create"
  - `/severance-funds/create` → "Severance-Funds Create"

  Properly built (1 of 5):
  - `/incident-types/create` → "Crear Tipo de Novedad - SGTE" ✓

- **Repro**: Login as admin, visit any of the 4 routes above. See Blueprint stub.
- **Notes**: The matching edit pages may have the same issue — not verified yet. Index pages render fine (verified during smoke pass). Backend Form Requests (`EpsStoreRequest`, etc.) exist and are wired; only the React pages are missing.

### BUG-10 — UI does not surface `justification` field when service_date is on an EJECUTADO day

- **Severity**: P1 (BUG-03 fix is unreachable via UI)
- **Scenario**: `SVC-LC-17` UI variant (Pest verifies backend at HTTP level)
- **Route / file**: `resources/js/pages/services/create.tsx` (likely needs a conditional reveal based on `dayStatus.status === 'executed'`)
- **Expected**: When admin picks `service_date` corresponding to an EJECUTADO day, the form should:
  1. Display a warning banner "Día ejecutado — agregar servicio requiere justificación."
  2. Reveal a `justification` textarea (10–500 chars).
  3. Submit will succeed only with the justification filled (backend BUG-03 fix enforces this).
- **Observed**: After picking `service_date = 2026-06-16` (an EJECUTADO day), no warning surfaces and no justification field appears. Submitting via UI hits the backend, which rejects without justification — but the user has no path to provide one.
- **Repro**: As admin, execute a day first (e.g., 2026-06-16). Then visit `/services/create`, set service_date to 2026-06-16. No justification UI appears.
- **Notes**: Pair scenario with BUG-03 — both are needed for the feature to work end-to-end. Frontend work is the remaining ask.

### BUG-07 — `data-imports.manage` permission is not assigned to any role (CLOSED — not a bug)

**Decision (2026-05-18):** Intended design. Data imports are a Super-Admin-only feature; the permission is defined in the DB so the gate-check can be expressed, but assigning it to any role would broaden the surface beyond what's appropriate. SA reaches `/admin/imports` via `Gate::before` bypass. CLAUDE.md sidebar reference will be updated to mark Importaciones as SA-only.

Original report retained below for historical context.

- **Severity**: ~~P1~~ (closed — by design)
- **Scenario**: walkthrough catch (no catalog row yet; closest = `IMP-RBAC-*`)
- **Route / file**: `database/seeders/{Permission,Role}Seeder.php` or wherever the role-permission seed lives
- **Expected** (per CLAUDE.md "Administración (admin only) — Usuarios, Roles, Permisos, Auditoría, Importaciones" + `nav-action-map.md` § 1.5 "Configuración → manage_data_imports → Importaciones de Datos"): Admin role should have `data-imports.manage`. The `Configuración → Importaciones` sidebar group should appear for Admin.
- **Observed**: Tinker output (2026-05-18):
  ```
  admin => data-imports.manage: NO
  operator => data-imports.manage: NO
  accounting => data-imports.manage: NO
  driver => data-imports.manage: NO
  super_admin => data-imports.manage: NO
  ```
  The permission row exists in `permissions` table but is unassigned. `/admin/imports*` routes are reachable only via Super Admin's `Gate::before` bypass. Admin sidebar correctly hides the group (no permission → no link), but the feature is effectively dead for the intended audience.
- **Repro**: Login as Admin. Sidebar shows Panel/Producción/Gestión/Facturación/Administración/FUEC/GPS/Catálogos — no Configuración group. Direct navigation to `/admin/imports` returns 403.
- **Suspected cause**: Role-permission seeder omits `data-imports.manage` from the admin role's permission set.
- **Notes**: Should also verify the `Administración` sidebar group includes Importaciones once the permission is wired. Per CLAUDE.md the menu structure groups Importaciones under Administración, but `app-sidebar.tsx` (per `nav-action-map.md` § 5) puts it under a separate `Configuración` group. Either is fine; pick one.


### Imports (IMP)

### Catalogs (CAT)

### Settings + Driver portal (SET, DRIVER)

---

## Walkthrough summary (2026-05-18 — 5 roles × ~50 routes × ~10 forms via Playwright MCP)

| Role | Sidebar visible | Pages smoke-tested | RBAC probes | Form deep tests | Signature flow |
|---|---|---|---|---|---|
| **Admin** | Panel · Producción · Gestión · Facturación · Administración · FUEC · GPS · Catálogos (no Importaciones — BUG-07) | 22/22 render clean | n/a (broadest) | Vehicle create happy path ✓, Driver/ThirdParty/Contract/Service/Incident/Invoice/FUEC-Range submit-blank all surface Spanish validation ✓ | Day execute (2026-06-16) ✓ |
| **Operator** | Panel · Producción · Gestión · GPS · Catálogos | 5 expected | 7/7 expected 403s ✓ | Service create validation ✓ | n/a |
| **Accounting** | Panel · Producción · Gestión · Facturación | n/a | 10/10 expected 403s + 1 expected 200 (invoices.create) ✓ | covered by existing `InvoiceBillingWorkflowTest` | Invoice attach (existing) |
| **Driver** | Panel · Conductor → Mis Servicios | /driver portal ✓ | 11/11 expected 403s ✓ | n/a | confirm/decline covered by `DriverPreflightDeclineTest` (existing) |
| **Super Admin** | All sidebar groups including Conductor (NOTE-05) | n/a | 8/8 expected 200s on routes others get 403 on ✓ (gate-bypass verified) | n/a | BUG-05 SA reversal verified by Pest at HTTP layer |

Total new bugs found during walkthrough: **4** (BUG-07, BUG-08, BUG-09, BUG-10).
Total RBAC assertions verified: **>40** routes across all 5 roles.

## Session notes

A scratchpad of observations that didn't rise to a bug but are worth remembering during Phase 4. Examples: surprising-but-correct behaviors, UX rough edges that aren't bugs, performance smells.

### NOTE-01 — Permission naming convention is `module.action`, not `action_module`

Phase 1's `nav-action-map.md` and `scenario-catalog.md` assumed snake_case permission names like `view_vehicles`, `create_services`, `view_audit_log`. The actual permissions in `app/Enums/Permission.php` (verified against `users.getAllPermissions()` in tinker) use **dot + kebab-case**:

| In catalog (wrong) | Actual | In catalog (wrong) | Actual |
|---|---|---|---|
| `view_vehicles` | `vehicles.view` | `view_audit_log` | `audit-log.view` |
| `create_services` | `services.create` | `manage_fuec_number_ranges` | `fuec-number-ranges.manage` |
| `update_projected_services` | `services.update-projected` | `view_day_summary` | `day-summary.view` |
| `update_executed_services` | `services.update-executed` | `execute_day` | `day-summary.execute` |
| `assign_services_to_invoices` | `invoices.assign-services` | `register_vehicle_location` | `vehicle-locations.register` |
| `dashboard.view` | `dashboard.view` (only one matching the format) | — | — |

Catalog RBAC tables remain conceptually valid (5 roles × actions matrix) but Phase 4 Pest tests must use the real names when asserting gate behavior. Catalog will be corrected end-of-Phase-3 in a single sweep.

### NOTE-02 — Operator role has more permissions than Phase 1 inventoried

Operator's actual permission set (verified via tinker) includes **full CRUD on vehicles, drivers, third-parties, contracts** plus `catalogs.manage`, `incident-types.*` (CRUD), `day-summary.execute`, `vehicle-locations.register`, and `reports.view`. Phase 1 `role-workflows.md` § 1.3 described Operator as "read-only" on vehicles/drivers/third-parties/contracts — that's incorrect. The catalog § 4.3, 5.3, 6.3, 7.3 RBAC matrices need to be updated: Operator should be ✓ for create/update/delete on those master-data modules.

Implication for Phase 4: tests asserting "OP gets 403 on POST /vehicles" would falsely fail. The actual security boundary is around Administración (users / roles / audit log), Facturación (invoices), FUEC, and GPS — Operator is blocked from those, which is what the catalog must reflect.

### NOTE-03 — Accounting role has `services.update-executed` but NOT `services.update-projected`

Confirms the EJECUTADO-day exception. Accounting cannot edit a service while its day is PROYECTADO; they can only touch executed services (and even then, limited to accounting fields per `ServiceUpdateRequest` whitelist). Catalog § 8.3 row "PUT (EJECUTADO day, accounting fields only)" is correct; row "PUT (PROYECTADO day)" should mark AC as ✗ for the same reason.

### NOTE-04a — Catalog terminology corrections (additional)

Verified against the live schema / enums:

- Vehicle plate column is **`plate`**, not `license_plate` (catalog § 4 had it as `license_plate`).
- FUEC status enum values are **`active` / `cancelled`**, not the Spanish `vigente` / `anulado` I assumed (the latter only appear in UI labels — see role-workflows.md § 2.9). Catalog § 13.1 should be updated.
- "Code 18 = outsourced" convention from CLAUDE.md/SRS is **not enforced** in the seed/code. `internal_code` is a free-form string (V-001..V-005 in seed). The outsourced relationship is captured by the boolean `vehicles.is_third_party` + `vehicles.third_party_id` FK. Phase 4 tests should key off `is_third_party`, not `internal_code = "18"`.
- Seeded contract numbering is `CT-NNNN-YYYY`, including **generic** contracts (e.g., `CT-0004-2026` has `is_generic = true`). This **diverges from Q5** which the user said should be `Generic Contract #N`. Logged as a potential bug below — verify whether the *runtime* generator uses a different scheme than the seeder.

### NOTE-05 — Super Admin sees "Conductor" sidebar group despite no Driver link

- Observed during walkthrough: SA's sidebar shows `Panel · Conductor · Producción · Gestión · Facturación · Administración · FUEC · GPS · Catálogos`. The Conductor group is normally driver-only (per `role-workflows.md` § 1.1) but appears for SA because `Gate::before` returns true on every gate check including `services.register-times`.
- This is technically correct (SA bypasses everything) but UX-odd because SA doesn't have a Driver model linked, so clicking into `Mis Servicios` will show no assigned services. Recommend hiding the Conductor group for users who lack a `driver_id` linkage, not just the permission. Not bug-logged — it's a known consequence of the Gate::before bypass design.

### NOTE-04 — Super Admin user is `superadmin@sgte.app`, not env-driven

CLAUDE.md says Super Admin email reads from `SUPER_ADMIN_USER` in `.env`. In the seeded fresh install, no such env var is set and the seeder uses the hardcoded `superadmin@sgte.app` with password `password`. Verify whether the env var is consulted at all (it might be — needs a code check) or whether CLAUDE.md doc is stale. Either way, the working SA credentials for this Phase 3 session are `superadmin@sgte.app` / `password`.

