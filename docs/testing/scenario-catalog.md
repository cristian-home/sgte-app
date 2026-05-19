# Phase 2 — Scenario Catalog

> Every test case Phase 3 will exercise and Phase 4 will codify. IDs are stable: Phase 3 bug-log entries reference `SVC-LC-03`, Phase 4 Pest tests use the ID as the test name (e.g., `it('SVC-LC-03 vehicle double-booking is rejected')`).
>
> Conventions:
> - **ID prefix** = module code (table below). **Category** = `HP` (happy path) · `VAL` (validation/UX) · `RBAC` (authorization) · `LC` (logical conflict / domain invariant).
> - Pre-conditions assume a fresh `migrate:fresh --seed` with the init-data reference users. Anything beyond that is listed under "Setup."
> - "Status" column on each table tracks Phase 3 verification: 🟡 not yet probed · 🟢 verified passing · 🔴 verified failing (will be linked to a `bug-log.md` entry) · ⏭ skipped (e.g., feature flag off, scenario obsoleted).
> - For RBAC matrices: ✓ = allowed; ✗ = 403; ✗422 = 422 validation error (probably from FormRequest rules running after authorize); ✗404 = route doesn't even render (e.g., feature flag off); — = N/A.
> - Roles abbreviated: **SA** Super Admin · **AD** Admin · **OP** Operación · **DR** Conductor · **AC** Contabilidad.

## Module ID prefixes

| Prefix | Module |
|---|---|
| `AUTH` | Fortify auth (login, register, password reset, email verify, 2FA, password confirm) |
| `DASH` | Dashboard |
| `VEH` | Vehicles |
| `DRV` | Drivers |
| `TP` | Third Parties |
| `CTR` | Contracts |
| `SVC` | Services |
| `GNT` | Planificador (Gantt) |
| `DAY` | Day Summary + Day Statuses (execute día) |
| `INC` | Incidents (Novedades) |
| `INV` | Invoices |
| `FUEC` | FUEC documents |
| `FRG` | FUEC Number Ranges |
| `GPS` | Vehicle Locations + GPS map |
| `USR` | Users |
| `ROLE` | Roles & Permissions |
| `AUD` | Audit Log |
| `IMP` | Data Imports |
| `CAT` | Catalogs (Document Types, EPS, Pension Funds, Severance Funds, Incident Types) |
| `SET` | Settings (Profile, Password, Appearance, 2FA) |
| `DRIVER` | Driver portal (`/driver`) |
| `TZ` | Cross-cutting timezone scenarios |
| `LAYER` | Cross-cutting three-layer-authorization probes |

---

## 0. Cross-cutting — Three-layer authorization (ADR-005)

Every mutating route is protected by three layers. Phase 3 probes each layer in isolation to catch silent bypasses.

| ID | Layer probed | Scenario | Expected | Status |
|---|---|---|---|---|
| `LAYER-01` | Layer 1: SA bypass | Super Admin POSTs `/services` without `services.create` permission visible in `auth.permissions` (SA has none assigned). | 200/redirect — `Gate::before` short-circuits to `true`. | 🟡 |
| `LAYER-02` | Layer 1: SA bypass on FormRequest | Super Admin PUTs an EJECUTADO `services/{id}` without supplying `justification`. | Allowed; the FormRequest's `authorize()` is bypassed too. Activity log records the change with no `properties.justification`. | 🟡 |
| `LAYER-03` | Layer 2: route middleware | Accounting hits GET `/users`. | 403 from middleware before controller runs (no SQL queries, no Inertia response). | 🟢 |
| `LAYER-04` | Layer 2: route middleware (feature flag) | Any role hits `/fuecs` when `SGTE_FUEC_ENABLED=false`. | 404 from `EnsureFuecEnabled` (not 403, not 500). | 🟡 |
| `LAYER-05` | Layer 2: route middleware (feature flag) | Any role hits `/gps/map` when `SGTE_GPS_ENABLED=false`. | 404 from `EnsureGpsEnabled`. | 🟡 |
| `LAYER-06` | Layer 3: FormRequest authorize | **Accounting** POSTs `/services` (catalog corrected from OP since OP has `services.create`; AC has `services.view` for route middleware, lacks `services.create`). Valid payload. | 403 from `ServiceStoreRequest::authorize()` — never reaches `rules()`. | 🟢 |
| `LAYER-07` | Layer 3: FormRequest authorize (no info leakage) | Same as LAYER-06 but send an *invalid* payload (missing required fields). | Still 403, NOT 422. Confirms authorize runs before rules. | 🟢 |
| `LAYER-08` | Layer 3: granular gate inside controller | Driver POSTs `/service-incidents` for a service whose `driver_id` is not their `driver.id`. | **Pinned 2026-05-18:** 302 redirect + session error `"Solo puede registrar novedades en sus propios servicios."` (not 403 — deliberate UX to avoid info leakage about other drivers' services). | 🟢 |
| `LAYER-09` | Per-action vs resource gate | Driver visits GET `/service-incidents` (the index). | 403: driver has `incidents.create` but not `incidents.view`. Confirms the route's per-action gates fire correctly. | 🟢 |
| `LAYER-10` | EnsureUserIsActive | Admin sets a user's `is_active = false`; that user attempts to load `/dashboard`. | Aborted (likely 403 or logout). No partial render. | 🟡 |
| `LAYER-11` | EnsurePasswordChanged | New user with `must_change_password = true` logs in and tries to navigate to `/services`. | Redirected to `/settings/password` regardless of intended URL. | 🟡 |

---

## 1. Cross-cutting — Timezone & day-boundary (ADR-007)

Anchored in the 2026-05-08 datetime audit. The viewer's browser TZ is set with `X-Viewer-Timezone` header in Playwright runs. Each scenario specifies (operation TZ, record TZ, viewer TZ) explicitly — defaults are Bogota/Bogota/Bogota.

| ID | Scenario | Setup | Expected | Status |
|---|---|---|---|---|
| `TZ-01` | Cross-midnight service creation, viewer in Madrid | Viewer TZ = `Europe/Madrid` (UTC+2); operation TZ = `America/Bogota`; current wall-clock = `2026-05-09 00:13 Madrid` (`2026-05-08 17:13 Bogota`). Create service for `today` Bogota. | Service `service_date_local = 2026-05-08`; appears on Gantt for May 8, not May 9; day-summary for May 8 includes it. | 🟡 |
| `TZ-02` | SOAT boundary — pre-expiry | Vehicle SOAT due `2026-05-08 00:00 Bogota` (i.e., expires at start of May 8 wall-clock). Now = `2026-05-07 23:59 Bogota`. Create service for now+1h. | Service created; vehicle not gray on Gantt. | 🟡 |
| `TZ-03` | SOAT boundary — at expiry | Same vehicle, now = `2026-05-08 00:00 Bogota`. Try to create service. | Rejected by `validateVehicleDocumentsNotExpired`; vehicle row grayed on Gantt. | 🟡 |
| `TZ-04` | Driver dashboard "today" in operation TZ | Driver's browser TZ = `Europe/Madrid`; now = `2026-05-09 06:00 Madrid` (`2026-05-08 23:00 Bogota`). Driver loads `/driver`. | Header reads "viernes, 8 de mayo"; service list shows May 8 (Bogota) services. Banner discloses TZ mismatch. | 🟡 |
| `TZ-05` | Retroactive entry detection | Operator on `2026-05-09` creates a service for `2026-05-08` with `service_status = closed` on save. | Service stored; flash "Registro retroactivo"; activity log has `properties.retroactive_entry = true`. | 🟢 |
| `TZ-06` | Contract half-open boundary | Contract `start_at = 2026-05-01 00:00 BOG`, `end_at = 2026-05-08 00:00 BOG`. Create service on `2026-05-07` (in-window) and `2026-05-08` (out-of-window). | May 7: accepted using this contract. May 8: contract rejected; system offers/auto-creates `Generic Contract #N`. | 🟡 |
| `TZ-07` | Audit log shows viewer TZ | Admin views `/audit-log` from Bogota, then switches viewer TZ to Madrid (header). | Same row, same instant — timestamp re-renders in viewer TZ. `formatTimestampInViewerTz` used. | 🟡 |
| `TZ-08` | Driver license boundary | Driver `license_due_at = 2026-05-08 00:00 BOG`. Now = `2026-05-08 00:00 BOG`. Attempt assignment. | Rejected by `validateDriverLicense`. | 🟡 |
| `TZ-09` | Per-row TZ for Service ≠ operation TZ | Service planned for `2026-05-10 20:00 America/Lima` (`21:00 Bogota`). Persist with `timezone = America/Lima`. | Wall-clock accessor returns `2026-05-10 20:00`; Gantt for May 10 (operation TZ Bogota) shows it at the 21:00 slot. | 🟡 |
| `TZ-10` | DST-affected TZ | Service in a DST-observing TZ (e.g., `America/Santiago`) created near the DST flip. | Wall-clock and UTC instant remain consistent; no off-by-one-hour drift on subsequent reloads. | 🟡 |
| `TZ-11` | Viewer TZ capture via header | Fresh login. Browser detects `Intl.DateTimeFormat().resolvedOptions().timeZone = America/Mexico_City`. Subsequent request includes `X-Viewer-Timezone: America/Mexico_City`. | `config.viewer_tz` reflects new TZ; `users.timezone` row updated; partial reload triggered. | 🟡 |
| `TZ-12` | Invalid viewer TZ rejected | Send `X-Viewer-Timezone: Mars/Olympus`. | Header ignored (silent), fallback to `users.timezone` or operation TZ. No 500. | 🟡 |

---

## 2. Auth (Fortify)

Fortify provides login, register, password reset, email verify, password confirmation, 2FA. Most enforcement is upstream and not tested at deep length — focus on rate limiting, password rules, throttle, redirect targets.

### 2.1 Happy paths

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `AUTH-HP-01` | Login admin@sgte.app with correct password | 302 → `/dashboard`. Session cookie set. | 🟡 |
| `AUTH-HP-02` | Login driver@sgte.app | 302 → `/driver` (NOT `/dashboard`). | 🟡 |
| `AUTH-HP-03` | Login operator@sgte.app, accounting@sgte.app | 302 → `/dashboard`. | 🟡 |
| `AUTH-HP-04` | Login Super Admin (from `.env`) | 302 → `/dashboard`. | 🟡 |
| `AUTH-HP-05` | Password reset full flow: forgot → email → reset link → new password | New password works; old password fails. Mailpit shows email. | 🟡 |
| `AUTH-HP-06` | Email verification: register, click link from Mailpit | `email_verified_at` set; `verified` middleware no longer blocks. | 🟡 |
| `AUTH-HP-07` | 2FA enable: confirm code from authenticator, store recovery codes | Future logins require code. | 🟡 |
| `AUTH-HP-08` | 2FA recovery code login | Single-use; same code rejected on second attempt. | 🟡 |
| `AUTH-HP-09` | Logout | Session invalidated; subsequent request 302 → `/login`. | 🟡 |

### 2.2 Validation / negatives

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `AUTH-VAL-01` | Login with wrong password | 422 with generic "credentials" error (no email-exists leak). | 🟡 |
| `AUTH-VAL-02` | Login throttle: 5 wrong attempts | 429 + lockout. | 🟡 |
| `AUTH-VAL-03` | Register with password violating ADR-006 rules (length, casing, symbols, uncompromised) | 422 per-rule errors. | 🟡 |
| `AUTH-VAL-04` | Password reset with expired token | 422 / "token invalid". | 🟡 |
| `AUTH-VAL-05` | Password reset with mismatched confirmation | 422. | 🟡 |
| `AUTH-VAL-06` | 2FA challenge with wrong code | 422; sequential wrong attempts also throttled. | 🟡 |
| `AUTH-VAL-07` | Verification link tampered (bad signature) | 403 / 401. | 🟡 |

### 2.3 RBAC

Auth routes are mostly guest routes — no RBAC matrix. The `verified` middleware does block features for unverified users:

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `AUTH-RBAC-01` | User without `email_verified_at` loads `/dashboard` | 302 → `/email/verify`. | 🟡 |
| `AUTH-RBAC-02` | Same user with `?fortify_route=/login` shouldn't bypass | Still blocked. | 🟡 |

---

## 3. Dashboard

`/dashboard` is the post-login landing for Admin / Operator / Accounting / Super Admin. Shows KPIs + expiry alerts.

| ID | Scenario | Role | Expected | Status |
|---|---|---|---|---|
| `DASH-HP-01` | Load `/dashboard` | AD, OP, AC, SA | Renders KPI cards, expiry-alert list (30/15/5 day buckets). | 🟢 |
| `DASH-HP-02` | Expiry alert shows vehicles with SOAT due in ≤30d | AD | Each alert links to the vehicle's show page. | 🟢 |
| `DASH-RBAC-01` | Driver hits `/dashboard` | DR | 403 (no `dashboard.view`) OR redirect to `/driver` (verify which). | 🟡 |
| `DASH-LC-01` | Inactive vehicle (soft-deleted) excluded from expiry-alert | AD | Confirm soft-deleted records don't pollute alerts. | 🟡 |

---

## 4. Vehicles

### 4.1 Happy paths

| ID | Scenario | Role | Expected | Status |
|---|---|---|---|---|
| `VEH-HP-01` | Create owned vehicle with future SOAT/RTM/Operation Card | AD | 302 → index; row visible. | 🟡 |
| `VEH-HP-02` | Create outsourced vehicle (`is_third_party = true`, `third_party_id` populated) with provider tercero | AD | Row visible; provider linked. | 🟡 |
| `VEH-HP-03` | Edit vehicle SOAT to extend expiry | AD | Update saved; Gantt row no longer grayed on next request. | 🟡 |
| `VEH-HP-04` | Soft-delete vehicle | AD | Row hidden from default index; appears with `trashed` filter (if exposed). | 🟡 |
| `VEH-HP-05` | View vehicle show page | OP | Vehicle detail + recent services + recent locations (if GPS on). | 🟢 |

### 4.2 Validation

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `VEH-VAL-01` | Submit create with missing plate | 422 with field error. | 🟡 |
| `VEH-VAL-02` | Duplicate plate | 422 unique violation. | 🟡 |
| `VEH-VAL-03` | `is_third_party = true` without `third_party_id` | 422; outsourced requires a provider. | 🟡 |
| `VEH-VAL-04` | `is_third_party = false` but `third_party_id` set | 422 (or silently nulled — probe). | 🟡 |
| `VEH-VAL-05` | SOAT due date in the past on create | 422? Or accepted with warning? Probe. | 🟡 |
| `VEH-VAL-06` | Invalid timezone string in `timezone` field | 422 against `timezone_identifiers_list()`. | 🟡 |
| `VEH-VAL-07` | Municipality FK doesn't exist | 422 `exists` rule. | 🟡 |

### 4.3 RBAC

| Action | SA | AD | OP | DR | AC | Status |
|---|---|---|---|---|---|---|
| GET `/vehicles` (index) | ✓ | ✓ | ✓ | ✗ | ✓ | 🟡 |
| GET `/vehicles/{id}` (show) | ✓ | ✓ | ✓ | ✗ | ✓ | 🟡 |
| GET `/vehicles/create` | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |
| POST `/vehicles` (store) | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |
| PUT `/vehicles/{id}` | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |
| DELETE `/vehicles/{id}` | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `VEH-RBAC-01` | OP loads `/vehicles/create` | 200 — OP has `vehicles.create` (catalog corrected 2026-05-18 per NOTE-02). | 🟡 |
| `VEH-RBAC-02` | DR loads `/vehicles` | 403 (no `vehicles.view`). | 🟡 |
| `VEH-RBAC-03` | AC POSTs `/vehicles` with valid body | 403 from `VehicleStoreRequest::authorize` — Accounting is read-only on vehicles. | 🟡 |

### 4.4 Logical conflicts

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `VEH-LC-01` | After SOAT expiry passes, vehicle row is grayed on Gantt | Yes. | 🟡 |
| `VEH-LC-02` | Renewing SOAT (PUT future date) un-grays the row on next Gantt load | Yes. | 🟡 |
| `VEH-LC-03` | Vehicle with `is_third_party = true` shown without driver field on service form | Yes (REQ-003 AC 2). | 🟢 |
| `VEH-LC-04` | Soft-deleted vehicle hidden from service-create vehicle dropdown | Yes. | 🟡 |
| `VEH-LC-05` | Delete vehicle that has services attached | Should fail with FK or soft-delete only, never break audit linkage. | 🟡 |

---

## 5. Drivers

### 5.1 Happy paths

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `DRV-HP-01` | Create driver (without User link) with all required fields | Row visible. | 🟡 |
| `DRV-HP-02` | Create driver with `create_user_account = true` → User created + invite email | Mailpit shows invite; driver.user_id populated. | 🟡 |
| `DRV-HP-03` | `POST /drivers/{id}/invite-account` on existing driver without User | Creates User + sends invite. | 🟡 |
| `DRV-HP-04` | `POST /drivers/{id}/resend-invitation` | New email in Mailpit. | 🟡 |
| `DRV-HP-05` | Edit driver license expiry to a future date | Saved; gantt picker shows the driver again if previously blocked. | 🟡 |

### 5.2 Validation

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `DRV-VAL-01` | Missing required fields (first_name, etc.) | 422. | 🟡 |
| `DRV-VAL-02` | Invalid `license_category` (not C1/C2/C3) | 422. | 🟡 |
| `DRV-VAL-03` | `eps_id`/`pension_fund_id`/`severance_fund_id` FK missing | 422 exists. | 🟡 |
| `DRV-VAL-04` | Duplicate driver document_number for same document_type | 422 unique. | 🟡 |
| `DRV-VAL-05` | License due date in past on create | 422? Probe. | 🟡 |
| `DRV-VAL-06` | Invite email when driver already has user_id | 422 / friendly error. | 🟡 |

### 5.3 RBAC

| Action | SA | AD | OP | DR | AC | Status |
|---|---|---|---|---|---|---|
| GET `/drivers` | ✓ | ✓ | ✓ | ✗ | ✓ | 🟡 |
| GET `/drivers/{id}` | ✓ | ✓ | ✓ | ✗ | ✓ | 🟡 |
| GET `/drivers/create`, POST `/drivers` | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |
| PUT `/drivers/{id}` | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |
| DELETE `/drivers/{id}` | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |
| `invite-account`, `resend-invitation` | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |

### 5.4 Logical conflicts

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `DRV-LC-01` | Delete a driver linked to a User account | Fails (`UserController::destroy` blocks linked-driver delete — applies symmetrically here? Probe whether driver delete cascades or blocks). | 🟡 |
| `DRV-LC-02` | Driver portal access lost after `is_active = false` on their User | DR cannot login; existing session terminated by `EnsureUserIsActive`. | 🟡 |
| `DRV-LC-03` | Driver with `has_social_security = false` assigned to service (Q6 amended — hard block) | Service **rejected** with 422; `driver_id` error "El conductor no tiene seguridad social activa." | 🟡 |
| `DRV-LC-04` | C1 driver assigned to Bus or Buseta (Q1: not in allowed list) | Rejected (Bus/Buseta require C2 or C3). | 🟡 |
| `DRV-LC-05` | C3 driver assigned to Automobile (Q1: allowed in permissive mapping) | Accepted. | 🟡 |
| `DRV-LC-06` | Driver license expired → assignment to any vehicle | Rejected. | 🟡 |

---

## 6. Third Parties

### 6.1 Happy paths

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `TP-HP-01` | Create natural-person customer | Row visible. | 🟡 |
| `TP-HP-02` | Create legal-person provider | Row visible. | 🟡 |
| `TP-HP-03` | Create dual-flag tercero (`is_customer + is_provider`) (Q8) | Accepted; appears in both customer and provider pickers. | 🟢 |
| `TP-HP-04` | Edit tercero municipality | Saved. | 🟡 |
| `TP-HP-05` | Soft-delete tercero with no relations | Hidden from default index. | 🟡 |

### 6.2 Validation

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `TP-VAL-01` | Missing identification_number | 422. | 🟡 |
| `TP-VAL-02` | Duplicate (document_type, identification_number) | 422. | 🟡 |
| `TP-VAL-03` | Neither `is_customer` nor `is_provider` checked | 422 "at least one role." | 🟡 |
| `TP-VAL-04` | Natural-person fields filled while is_legal_person | 422 or silent normalization. | 🟡 |
| `TP-VAL-05` | Municipality FK invalid | 422 exists. | 🟡 |

### 6.3 RBAC

| Action | SA | AD | OP | DR | AC | Status |
|---|---|---|---|---|---|---|
| GET `/third-parties` | ✓ | ✓ | ✓ | ✗ | ✓ | 🟡 |
| POST `/third-parties` | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |
| PUT/DELETE | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |

### 6.4 Logical conflicts

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `TP-LC-01` | Delete tercero with active contracts | Soft-delete or 422 with friendly error (not 500). | 🟡 |
| `TP-LC-02` | Delete tercero whose vehicles exist (provider) | Same — protected. | 🟡 |
| `TP-LC-03` | Dual-flag tercero owns outsourced vehicle AND is contract client (Q8) | Both relations work simultaneously. | 🟡 |
| `TP-LC-04` | Toggle `is_customer` off on tercero with active contracts | 422 or silent (probe — should block to avoid orphaning contracts). | 🟡 |

---

## 7. Contracts

### 7.1 Happy paths

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `CTR-HP-01` | Create contract with customer tercero, valid date range, object Empresarial | Row visible. | 🟡 |
| `CTR-HP-02` | Edit contract end_at to extend validity | Saved. | 🟡 |
| `CTR-HP-03` | Soft-delete contract with no services | Hidden. | 🟡 |
| `CTR-HP-04` | Filter index by contract_status=vigente/por_vencer/vencido/inactivo | Each filter shows correct subset. | 🟡 |

### 7.2 Validation

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `CTR-VAL-01` | start_at >= end_at | 422. | 🟡 |
| `CTR-VAL-02` | Non-customer tercero as client | 422 (only `is_customer` allowed). | 🟡 |
| `CTR-VAL-03` | Object enum value not in (Empresarial/Turismo/Salud/Ocasional) | 422. | 🟡 |
| `CTR-VAL-04` | Invalid timezone string | 422. | 🟡 |

### 7.3 RBAC

| Action | SA | AD | OP | DR | AC | Status |
|---|---|---|---|---|---|---|
| GET `/contracts` | ✓ | ✓ | ✓ | ✗ | ✓ | 🟡 |
| POST/PUT/DELETE | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |

### 7.4 Logical conflicts

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `CTR-LC-01` | Service creation with date inside contract window | Uses this contract. | 🟡 |
| `CTR-LC-02` | Service date outside any contract for the tercero | Auto-creates generic contract named `GEN-NNNN-YYYY` (Q5 amended). **bug-log:BUG-01 — currently rejects with "fecha del servicio no esta dentro del rango del contrato".** | 🔴 |
| `CTR-LC-03` | Two consecutive generic contracts get NNNN and NNNN+1 within the same year | Sequence increments per-year (resets each Jan 1). | 🟡 |
| `CTR-LC-04` | Concurrent service creates that both need new generics | No N collision (transactional). | 🟡 |
| `CTR-LC-05` | Contract `end_at = 2026-05-08 00:00 BOG`; service `service_date = 2026-05-08` | Out-of-window (half-open) → generic. | 🟡 |
| `CTR-LC-06` | Soft-deleted contract excluded from contract picker | Yes. | 🟡 |
| `CTR-LC-07` | Generic contract lifetime after service closed | Probe: persists? auto-purged? **Inferred; record outcome.** | 🟡 |

---

## 8. Services (REQ-003, REQ-009)

The most invariant-rich module. Logical-conflict section is the heart of the catalog.

### 8.1 Happy paths

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `SVC-HP-01` | Create owned-vehicle service: valid vehicle + driver + contract + origin + destination + planned_start | Service stored; DayStatus row created (status=projected); appears on Gantt + index. | 🟡 |
| `SVC-HP-02` | Create outsourced-vehicle service (no driver) | Service stored without driver_id; UI hides driver field. | 🟡 |
| `SVC-HP-03` | Edit service in PROYECTADO day (planned_start_at shift) | Saved; no justification required. | 🟡 |
| `SVC-HP-04` | Driver portal: confirm start | `actual_start_at` set. | 🟡 |
| `SVC-HP-05` | Confirm end → service_status = closed | `actual_end_at` set; activity log records transition. | 🟡 |
| `SVC-HP-06` | Index search by vehicle license plate | Returns matches (SearchesDatabase / Scout). | 🟢 |
| `SVC-HP-07` | Index filter date_from / date_to | Returns intersection. | 🟢 |
| `SVC-HP-08` | Delete service while day is PROYECTADO | Allowed. | 🟡 |

### 8.2 Validation

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `SVC-VAL-01` | Missing vehicle_id | 422. | 🟡 |
| `SVC-VAL-02` | Missing planned_start_at / service_date | 422. | 🟡 |
| `SVC-VAL-03` | Driver_id supplied with outsourced vehicle | 422 or silently nulled — probe. | 🟡 |
| `SVC-VAL-04` | origin_address filled but origin_coordinates missing | 422 ("must pick from Mapbox or pin map"). | 🟡 |
| `SVC-VAL-05` | origin_coordinates malformed (not lat,lng) | 422 regex. | 🟡 |
| `SVC-VAL-06` | Invalid timezone string | 422. | 🟡 |
| `SVC-VAL-07` | Justification field missing on EJECUTADO-day edit (Admin) | 422 "justification required" 10–500 chars. | 🟡 |
| `SVC-VAL-08` | Justification < 10 chars | 422 min. | 🟡 |
| `SVC-VAL-09` | Justification > 500 chars | 422 max. | 🟡 |
| `SVC-VAL-10` | Payment_method not in enum | 422. | 🟡 |

### 8.3 RBAC

| Action | SA | AD | OP | DR | AC | Status |
|---|---|---|---|---|---|---|
| GET `/services` | ✓ | ✓ | ✓ | ✗ | ✓ | 🟡 |
| GET `/services/{id}` | ✓ | ✓ | ✓ | ✗* | ✓ | 🟡 |
| POST `/services` | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |
| PUT (PROYECTADO day) | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |
| PUT (EJECUTADO day, full edit) | ✓ | ✓ (w/ justif) | ✗ | ✗ | ✗ | 🟡 |
| PUT (EJECUTADO day, accounting fields only) | ✓ | ✓ | ✗ | ✗ | ✓ | 🟡 |
| DELETE (PROYECTADO day) | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |
| DELETE (EJECUTADO day) | ✓ | ✓ | ✗ | ✗ | ✗ | 🟡 |

\* Driver sees services through `/driver`, not `/services/{id}` directly — verify driver gets 403 on the direct URL.

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `SVC-RBAC-01` | OP PUTs EJECUTADO service (any field) | 403 from `ServiceUpdateRequest::authorize`. | 🟡 |
| `SVC-RBAC-02` | AC PUTs EJECUTADO service with operational field (e.g., driver_id) | 422 / 403 — only accounting fields whitelisted. | 🟡 |
| `SVC-RBAC-03` | AC PUTs EJECUTADO service with accounting fields only (unit_value, quantity, billing_group, payment_method, invoice_id) | Allowed; saved. | 🟡 |
| `SVC-RBAC-04` | DR GET `/services/{id}` | 403. | 🟡 |
| `SVC-RBAC-05` | AC POSTs `/services` | 403 (no `services.create`). | 🟡 |

### 8.4 Logical conflicts (priority section)

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `SVC-LC-01` | Vehicle double-booking: vehicle V has service `[09:00–11:00]`; create service for same V `[10:00–12:00]` | Rejected with conflict error. | 🟢 |
| `SVC-LC-02` | Vehicle V has service `[09:00–11:00]`; create same-V service `[11:00–13:00]` (exact boundary) | Accepted (half-open). | 🟢 |
| `SVC-LC-03` | Vehicle V has service `[09:00–11:00]` planned, second service `[10:00–12:00]` whose `actual_end_at` has cleared the conflict | Probe — does planner consider planned-only conflicts or also actual? | 🟡 |
| `SVC-LC-04` | Driver double-booking (Q2): driver D has service `[09:00–11:00]`; assign D to another service `[10:00–12:00]` | Rejected (per Q2 decision). | 🟢 |
| `SVC-LC-05` | Driver double-booking across vehicles (D in two different vehicles overlapping) | Rejected. | 🟡 |
| `SVC-LC-06` | Assign vehicle with expired SOAT | Rejected. | 🟢 |
| `SVC-LC-07` | Assign vehicle with expired RTM | Rejected. | 🟢 |
| `SVC-LC-08` | Assign vehicle with expired Operation Card | Rejected. | 🟢 |
| `SVC-LC-09` | Assign driver with expired license | Rejected. | 🟢 |
| `SVC-LC-10` | Assign driver category not in vehicle-type allowed list (C1 → Bus) | Rejected. | 🟢 |
| `SVC-LC-11` | Assign driver category higher than minimum (C3 → Automobile) | Accepted (Q1). | 🟢 |
| `SVC-LC-12` | Assign driver `has_social_security = false` (Q6 amended — hard block) | Rejected with 422 "El conductor no tiene seguridad social activa." | 🟢 |
| `SVC-LC-13` | Service date outside contract window → auto-generic | Generic contract `GEN-NNNN-YYYY` created on the fly, used by the service. **bug-log:BUG-01.** | 🟢 |
| `SVC-LC-14` | Service date inside contract window with explicit contract chosen | Uses given contract. | 🟢 |
| `SVC-LC-15` | Service spanning midnight (planned start 23:30, duration 2h) | `service_date_local` = start day; appears on Gantt for start day. | 🟡 |
| `SVC-LC-16` | Retroactive entry: create service for yesterday with status=closed (TZ-05) | `properties.retroactive_entry = true` in activity. | 🟡 |
| `SVC-LC-17` | Late-add service on EJECUTADO day (Q4) — Admin **with `justification` 10–500 chars** | Accepted; day stays EJECUTADO; audit log records `properties.late_added_on_executed_day = true` + justification. **bug-log:BUG-03 — currently rejects for all roles.** | 🟢 |
| `SVC-LC-18` | Late-add service on EJECUTADO day — Operator (no justification path) | 403 / 422 — only Admin and Super Admin allowed; bug-log:BUG-03 prevents probing the role gate until BUG-03 is fixed. | 🔴 |
| `SVC-LC-19` | Reopen a closed service (status → open) while preserving `actual_start_at` (audit invariant) | Allowed for Admin; `actual_start_at` unchanged in DB. | 🟡 |
| `SVC-LC-20` | Attempt to clear `actual_start_at` via PUT | Rejected (irreversible). | 🟡 |
| `SVC-LC-21` | Status transition log entries match the actual delta (open→closed, closed→open) | Activity rows show both events. | 🟡 |
| `SVC-LC-22` | Edit EJECUTADO service: Admin supplies `justification = "Ajuste cliente"` | Accepted; activity row has `properties.justification` and `edited_on_executed_day = true`. | 🟡 |
| `SVC-LC-23` | DELETE service whose day is EJECUTADO — Admin | Allowed (per `destroy` admin-only path). | 🟡 |
| `SVC-LC-24` | DELETE service whose day is EJECUTADO — Operator | 403. | 🟡 |
| `SVC-LC-25` | Soft-deleted vehicle/driver/contract excluded from service-create dropdowns | Yes. | 🟡 |
| `SVC-LC-26` | Create service when no FuecNumberRange exists and FUEC feature on | Service still creates (FUEC not required at create); FUEC button later disabled. | 🟡 |
| `SVC-LC-27` | Create service then immediately invoice it — must wait for day execute | Invoice attach should fail until day is EJECUTADO. | 🟡 |

---

## 9. Planificador (Gantt)

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `GNT-HP-01` | Load `/gantt` for today (operation TZ) | Vehicles on Y, hours 06:00–22:00 on X (or whatever the range is), service bars rendered. | 🟢 |
| `GNT-HP-02` | Switch date via query param `?date=YYYY-MM-DD` | Renders that day's data. | 🟡 |
| `GNT-HP-03` | Filter by municipality | Y axis filters to vehicles in that municipality. | 🟡 |
| `GNT-LC-01` | Vehicle with expired doc rendered as `blocked: true` (grayed row) | Yes. | 🟡 |
| `GNT-LC-02` | Service bar reflects planned_start_at + planned_duration in operation TZ | Width matches duration; left position matches start hour. | 🟡 |
| `GNT-LC-03` | Service spanning midnight: bar on start-day Gantt extends to right edge; next-day Gantt has no bar | Verify visual + data. | 🟡 |
| `GNT-RBAC-01` | DR / AC visits `/gantt` | DR: 403. AC: probe — likely allowed (read-only). | 🟡 |
| `GNT-LC-04` | Click "create service" on Gantt grid → opens services/create with prefill | Vehicle ID + planned_start_at prefilled. | 🟡 |

---

## 10. Day Summary + Day Statuses

### 10.1 Happy paths

| ID | Scenario | Role | Expected | Status |
|---|---|---|---|---|
| `DAY-HP-01` | Load `/day-summary?date=YYYY-MM-DD` | AD/OP/AC | Table of services for the day + summary counts. | 🟢 |
| `DAY-HP-02` | Export day-summary CSV | OP | CSV stream with header + one row per service. | 🟡 |
| `DAY-HP-03` | Calendar view `/day-statuses/2026` | AD | Year heatmap: black/orange/green tiles. | 🟢 |
| `DAY-HP-04` | Click a day tile → drill into month view | AD | Month + day services panel. | 🟢 |
| `DAY-HP-05` | Execute day with all closed services | OP/AD | DayStatus → executed; `executor_id`, `executed_at` set; accounting notified. | 🟡 |

### 10.2 Validation

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `DAY-VAL-01` | Execute day with zero services | 422 / friendly error. | 🟡 |
| `DAY-VAL-02` | Execute day with at least one open service | 422; lists open services. | 🟡 |

### 10.3 RBAC

| Action | SA | AD | OP | DR | AC | Status |
|---|---|---|---|---|---|---|
| GET `/day-summary` | ✓ | ✓ | ✓ | ✗ | ✓ | 🟡 |
| Export CSV | ✓ | ✓ | ✓ | ✗ | ✓ | 🟡 |
| GET calendar | ✓ | ✓ | ✓ | ✗ | ✓ | 🟡 |
| Execute day | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |

### 10.4 Logical conflicts

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `DAY-LC-01` | EJECUTADO transition guard — Admin PUTs status back to projected (Q3 amended) | 422 with transition-guard error. **bug-log:BUG-05 — currently no guard; reversal succeeds and leaves `executed_at` populated.** | 🟢 |
| `DAY-LC-01b` | EJECUTADO Super-Admin override — SA PUTs status=projected with `justification` 10–500 chars | Accepted; controller clears `executor_id` + `executed_at`; activity log captures justification. **bug-log:BUG-05 — controller logic absent.** | 🟢 |
| `DAY-LC-01c` | EJECUTADO Super-Admin override without justification | 422 "justification required". **bug-log:BUG-05.** | 🟢 |
| `DAY-LC-02` | New service POST on EJECUTADO day — currently rejected for all roles (Phase 4 pins current behavior) | 422 with `service_date` error. Once BUG-03 is fixed, SVC-LC-17 takes over the admin-with-justification happy path and this scenario narrows to non-admin roles. | 🟢 |
| `DAY-LC-03` | Day with 0 services renders as black (no DayStatus row) | Yes. | 🟡 |
| `DAY-LC-04` | First service POST creates the DayStatus row with status=projected (orange) | Yes. | 🟡 |
| `DAY-LC-05` | After day executed: Admin can edit service with justification | Audit log has `edited_on_executed_day=true`. | 🟡 |
| `DAY-LC-06` | After day executed: Accounting edits unit_value | Allowed; audit captures. | 🟡 |
| `DAY-LC-07` | After day executed: Operator attempts edit | 403. | 🟡 |
| `DAY-LC-08` | Notification on day execute reaches Accounting users via Mailpit | Email logged. | 🟡 |

---

## 11. Incidents (Novedades / ServiceIncident)

### 11.1 Happy paths

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `INC-HP-01` | Operator creates DELAY incident on a service | Stored; `is_driver_report = false`. | 🟢 |
| `INC-HP-02` | Driver creates DELAY incident via `/service-incidents` from their dashboard | Stored; `is_driver_report = true`. | 🟡 |
| `INC-HP-03` | Incident with `affects_billing = true`, `additional_value = -50000` | Stored; invoice recompute reduces total by 50k. | 🟡 |
| `INC-HP-04` | Driver decline via `/driver/services/{id}/decline` with reason | Service stored with `driver_declined_at`; auto-incident created; operator notified. | 🟡 |

### 11.2 Validation

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `INC-VAL-01` | Missing incident_type_id | 422. | 🟡 |
| `INC-VAL-02` | Missing description | 422. | 🟡 |
| `INC-VAL-03` | `affects_billing=true` but `additional_value` null | 422 (probe). | 🟡 |
| `INC-VAL-04` | Decline without reason | 422 from `DriverDeclineServiceRequest`. | 🟡 |

### 11.3 RBAC

| Action | SA | AD | OP | DR | AC | Status |
|---|---|---|---|---|---|---|
| GET `/service-incidents` (index) | ✓ | ✓ | ✓ | ✗ | ✓ (billing-only filter) | 🟡 |
| POST `/service-incidents` | ✓ | ✓ | ✓ | ✓ (own services) | ✗ | 🟡 |
| PUT/DELETE | ✓ | ✓ | ✓ | ✗ | ✗ | 🟡 |

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `INC-RBAC-01` | DR POSTs incident for service whose driver_id ≠ them (LAYER-08) | 403 from `Gate::authorize` inside controller. | 🟡 |
| `INC-RBAC-02` | AC POSTs incident | 403 (no `incidents.create`). | 🟡 |
| `INC-RBAC-03` | DR GETs `/service-incidents/{id}` for someone else's service | 403. | 🟡 |

### 11.4 Logical conflicts

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `INC-LC-01` | Multiple incidents with `affects_billing = true` accumulate on invoice total | Sum of `additional_value`. | 🟡 |
| `INC-LC-02` | Edit incident's `additional_value` after invoice is generated; trigger `/invoices/{id}/recompute-total` | Invoice total updated. | 🟡 |
| `INC-LC-03` | Default `affects_billing` follows `incident_type.affects_billing_default` | Yes; user can override per incident. | 🟡 |
| `INC-LC-04` | Service with incidents shows visual indicator on form (REQ-003 AC 7) | UI flag present. | 🟡 |
| `INC-LC-05` | Notification on billing-impacting incident reaches operator/admin via Mailpit | Email present. | 🟡 |

---

## 12. Invoices

### 12.1 Happy paths

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `INV-HP-01` | Accounting creates invoice for tercero | Row visible; status pending. | 🟢 |
| `INV-HP-02` | Attach closed services from EJECUTADO days | `service.invoice_id` populated. | 🟡 |
| `INV-HP-03` | Recompute total | Total = Σ(service unit_value × quantity) + Σ(incident additional_value). | 🟡 |
| `INV-HP-04` | Mark pending invoice as paid | Status = paid. | 🟡 |
| `INV-HP-05` | Download informational PDF | PDF stream with disclaimer ("no es factura DIAN"). | 🟡 |
| `INV-HP-06` | Detach a service from invoice | Service.invoice_id cleared; total recomputed. | 🟡 |

### 12.2 Validation

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `INV-VAL-01` | Missing third_party_id | 422. | 🟡 |
| `INV-VAL-02` | Duplicate invoice_number for same tercero | 422 (or silent — probe). | 🟡 |
| `INV-VAL-03` | mark-paid when status ≠ pending | 422. | 🟡 |
| `INV-VAL-04` | Attach service that's not closed | 422 / friendly error. | 🟡 |
| `INV-VAL-05` | Attach service whose day is not EJECUTADO | 422. | 🟡 |
| `INV-VAL-06` | Attach with billing-affecting incident, no `override_justification` | 422 "justification required to override". | 🟡 |
| `INV-VAL-07` | override_justification < 10 chars | 422. | 🟡 |

### 12.3 RBAC

| Action | SA | AD | OP | DR | AC | Status |
|---|---|---|---|---|---|---|
| GET `/invoices` | ✓ | ✓ | ✗ | ✗ | ✓ | 🟡 |
| POST / PUT | ✓ | ✓ | ✗ | ✗ | ✓ | 🟡 |
| DELETE | ✓ | ✓ | ✗ | ✗ | ✓ | 🟡 |
| mark-paid | ✓ | ✓ | ✗ | ✗ | ✓ | 🟡 |
| attach/detach/recompute | ✓ | ✓ | ✗ | ✗ | ✓ | 🟡 |
| PDF | ✓ | ✓ | ✗ | ✗ | ✓ | 🟡 |

### 12.4 Logical conflicts

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `INV-LC-01` | Attach service from a different tercero than the invoice's | 422. | 🟡 |
| `INV-LC-02` | Total updates when incident edited (INC-LC-02 cross-ref) | Yes. | 🟡 |
| `INV-LC-03` | `recompute-total` is idempotent (call twice → same total) | Yes. | 🟡 |
| `INV-LC-04` | Soft-deleted services excluded from attach picker | Yes. | 🟡 |
| `INV-LC-05` | Attach with billing-affecting incident + override_justification | Allowed; activity log records override + justification. | 🟡 |
| `INV-LC-06` | Invoice involving a dual-flag tercero (TP-HP-03) | Works — tercero must be `is_customer`. | 🟡 |
| `INV-LC-07` | Invoice for tercero who lost `is_customer` flag (impossible if guarded; probe) | Should fail or be blocked. | 🟡 |

---

## 13. FUEC (requires `SGTE_FUEC_ENABLED=true`)

### 13.1 Happy paths

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `FUEC-HP-01` | Generate FUEC for service S1: valid contract, valid docs, valid license, active range with remaining | Status=active; PDF stored; consecutive allocated. | 🟡 |
| `FUEC-HP-02` | View FUEC PDF | PDF stream from S3 / MinIO. | 🟡 |
| `FUEC-HP-03` | Cancel FUEC with reason 10–500 chars | Status=cancelled; `cancellation_reason` populated. | 🟡 |
| `FUEC-HP-04` | Public verify `/fuec/verify/{uuid}` for active FUEC | Blade view shows VIGENTE + summary. No auth. | 🟡 |
| `FUEC-HP-05` | Public verify for cancelled FUEC | Shows ANULADO + reason. | 🟡 |
| `FUEC-HP-06` | Preview FUEC PDF (`/fuecs/preview`) | PDF stream; no DB write, no consecutive consumed. | 🟡 |
| `FUEC-HP-07` | `/fuecs/candidate-services` returns closed services w/o active FUEC | JSON list. | 🟢 |

### 13.2 Validation / pre-checks

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `FUEC-VAL-01` | Generate with expired vehicle SOAT | Rejected pre-check. | 🟡 |
| `FUEC-VAL-02` | Generate with expired RTM | Rejected. | 🟡 |
| `FUEC-VAL-03` | Generate with expired Operation Card | Rejected. | 🟡 |
| `FUEC-VAL-04` | Generate with expired driver license | Rejected. | 🟡 |
| `FUEC-VAL-05` | Generate when no active FuecNumberRange | Rejected. | 🟡 |
| `FUEC-VAL-06` | Generate when active range has `remaining() = 0` | Rejected with `FuecRangeExhaustedException` translated to 422. | 🟡 |
| `FUEC-VAL-07` | Cancel reason < 10 chars | 422. | 🟡 |
| `FUEC-VAL-08` | Cancel reason > 500 chars | 422. | 🟡 |
| `FUEC-VAL-09` | Cancel an already-cancelled FUEC | 422 (status guard). | 🟡 |
| `FUEC-VAL-10` | Generate with mismatched driver category | Rejected (uses same `LICENSE_CATEGORY_MAP`). | 🟡 |
| `FUEC-VAL-11` | Generate against a service whose contract is outside validity window | Rejected. | 🟡 |

### 13.3 RBAC

| Action | SA | AD | OP | DR | AC | Status |
|---|---|---|---|---|---|---|
| GET `/fuecs` | ✓ | ✓ | ✗ | ✗ | ✗ | 🟡 |
| POST `/fuecs` (generate) | ✓ | ✓ | ✗ | ✗ | ✗ | 🟡 |
| `/fuecs/preview` | ✓ | ✓ | ✗ | ✗ | ✗ | 🟡 |
| Cancel | ✓ | ✓ | ✗ | ✗ | ✗ | 🟡 |
| `/fuec/verify/{uuid}` (public) | ✓ | ✓ | ✓ | ✓ | ✓ (no auth) | 🟡 |

When flag off: all `/fuecs*` routes return 404 (LAYER-04).

### 13.4 Logical conflicts

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `FUEC-LC-01` | Generate two FUECs for same service in sequence (Q7) | Second generation **auto-cancels** the first in the same transaction; first row becomes `cancelled` with `cancellation_reason = "Superseded by new FUEC generation"`; second is `active`. **bug-log:BUG-06 — currently rejects with "Anule el actual antes de generar uno nuevo."** | 🟢 |
| `FUEC-LC-02` | Two concurrent POST `/fuecs` for different services drawing from same range | Both get distinct consecutives (transaction lock). | 🟡 |
| `FUEC-LC-03` | Two concurrent POST `/fuecs` for the same service | One succeeds, the other 422 (or supersede). Probe. | 🟡 |
| `FUEC-LC-04` | Range exhaustion mid-batch: range has 1 left, two concurrent generates | One succeeds, one gets `FuecRangeExhaustedException` 422. | 🟡 |
| `FUEC-LC-05` | Generate FUEC, then expire vehicle doc, then verify QR | Verify still shows VIGENTE (doc expiry after generation doesn't invalidate the issued FUEC). | 🟡 |
| `FUEC-LC-06` | QR for cancelled FUEC publicly shows ANULADO + reason | Yes. | 🟡 |
| `FUEC-LC-07` | Activity log captures FUEC generate + cancel events with consecutive | Yes. | 🟡 |

---

## 14. FUEC Number Ranges (requires `SGTE_FUEC_ENABLED=true`)

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `FRG-HP-01` | Create range 1000–2000, set active | Row created; old active range deactivated. | 🟢 |
| `FRG-HP-02` | View range remaining count (1000 if no FUECs used) | Matches. | 🟡 |
| `FRG-HP-03` | Edit range, set active=true | Other ranges deactivated atomically. | 🟡 |
| `FRG-HP-04` | Delete unused range | OK. | 🟡 |
| `FRG-VAL-01` | range_from > range_to | 422. | 🟡 |
| `FRG-VAL-02` | Overlap with existing range | 422 (probe — may or may not be enforced). | 🟡 |
| `FRG-VAL-03` | Delete range that has FUECs | 422 friendly error. | 🟡 |
| `FRG-LC-01` | Activation atomicity: two consecutive PUTs trying to activate range A then B | Only B active at end; A reverts to inactive. | 🟡 |
| `FRG-LC-02` | After exhaustion, dashboard warning surfaces (Phase 5 deferred — verify it exists) | If not implemented, mark `todo()`. | 🟡 |
| `FRG-RBAC-01` | OP/AC/DR access | 403 (only Admin via `fuec-number-ranges.manage`). | 🟡 |

---

## 15. GPS / Vehicle Locations (requires `SGTE_GPS_ENABLED=true`)

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `GPS-HP-01` | Driver registers location from `/driver` card (lat/lng/accuracy) | Stored with `is_manual=false`, `service_id` set, `captured_by` = driver's user. | 🟡 |
| `GPS-HP-02` | Admin views `/gps/map`, sees active service pins | Leaflet markers visible; 30s polling refreshes. | 🟢 |
| `GPS-HP-03` | List `/vehicle-locations` filter by vehicle + date range | Returns matching records. | 🟢 |
| `GPS-VAL-01` | Lat out of [-90, 90] | 422 (probe — may be unguarded). | 🟡 |
| `GPS-VAL-02` | Lng out of [-180, 180] | 422 (probe). | 🟡 |
| `GPS-RBAC-01` | DR POSTs location for service whose driver_id ≠ them | 403 from `DriverLocationController::store`. | 🟡 |
| `GPS-RBAC-02` | OP / AC view `/gps/map` | OP: probe — likely allowed. AC: probe. | 🟡 |
| `GPS-LC-01` | Geolocation permission denied; service execution continues uninterrupted (REQ-010 AC 4) | Yes. | 🟡 |
| `GPS-LC-02` | Soft-delete a vehicle with location history | Locations preserved for audit. | 🟡 |

When flag off: all GPS routes 404 (LAYER-05).

---

## 16. Users

### 16.1 Happy paths

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `USR-HP-01` | Create user with role Admin + send welcome email | User created; Mailpit shows temp password email; `must_change_password = true`. | 🟢 |
| `USR-HP-02` | Update user roles | Roles synced; activity log records delta. | 🟡 |
| `USR-HP-03` | Toggle is_active off then on | Inactive user can't login; reactivated user can. | 🟡 |
| `USR-HP-04` | Reset password (send link) | Mailpit shows reset email. | 🟡 |
| `USR-HP-05` | Delete user with no relations | Soft-deleted. | 🟡 |

### 16.2 Validation

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `USR-VAL-01` | Duplicate email | 422 unique. | 🟡 |
| `USR-VAL-02` | Password violates ADR-006 rules | 422 per-rule errors. | 🟡 |
| `USR-VAL-03` | No roles selected | 422 (probe — may allow no-role user). | 🟡 |

### 16.3 RBAC

| Action | SA | AD | OP | DR | AC | Status |
|---|---|---|---|---|---|---|
| GET `/users` | ✓ | ✓ | ✗ | ✗ | ✗ | 🟡 |
| POST / PUT / DELETE | ✓ | ✓ | ✗ | ✗ | ✗ | 🟡 |
| Toggle active / reset-password | ✓ | ✓ | ✗ | ✗ | ✗ | 🟡 |

### 16.4 Logical conflicts

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `USR-LC-01` | Self-delete (Admin tries to delete themselves) | 422. | 🟡 |
| `USR-LC-02` | Last-admin delete (single admin attempts to delete the only other admin? no — themselves) | 422. | 🟡 |
| `USR-LC-03` | Delete user linked to a driver (driver.user_id != null) | 422; must un-link first. | 🟡 |
| `USR-LC-04` | Inactive user existing session → next request | Aborted by `EnsureUserIsActive`. | 🟡 |
| `USR-LC-05` | User with `must_change_password = true` redirected to /settings/password | Yes (LAYER-11 mirror). | 🟡 |
| `USR-LC-06` | Multi-role user: Admin + Accounting | Sees union of permissions on both groups in sidebar. | 🟡 |

---

## 17. Roles & Permissions

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `ROLE-HP-01` | View `/roles/{role}` | Shows permission groups + sample users. | 🟢 |
| `ROLE-HP-02` | Update role description + sync permissions | Saved; activity log records permission delta. | 🟡 |
| `ROLE-HP-03` | View `/permissions` (read-only grouped) | Renders. | 🟢 |
| `ROLE-VAL-01` | Try to update Super Admin role's permissions | Probe — may be allowed but should not change runtime behavior (SA bypasses gates regardless). | 🟡 |
| `ROLE-RBAC-01` | Non-admin access | 403. | 🟡 |
| `ROLE-LC-01` | Removing `services.view` from Operator role | Operator immediately loses access on next request (no cache lag). | 🟡 |
| `ROLE-LC-02` | TS enum out-of-sync with PHP enum (regenerate enum via `php artisan enum:typescript`) | UI `<Can>` matches backend gate. Verify after `enum:typescript` run. | 🟡 |

---

## 18. Audit Log

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `AUD-HP-01` | Load `/audit-log` (Admin) | Paginated table of activities. | 🟢 |
| `AUD-HP-02` | Filter by subject_type=Service + causer=admin + date range | Returns matching subset. | 🟡 |
| `AUD-HP-03` | Filter by event=updated | Returns updates only. | 🟡 |
| `AUD-RBAC-01` | OP / AC / DR access | 403. | 🟡 |
| `AUD-LC-01` | Service update on EJECUTADO day shows `properties.justification` + `edited_on_executed_day = true` | Yes. | 🟡 |
| `AUD-LC-02` | Service status transition open→closed records both old and new status in `properties` | Yes. | 🟡 |
| `AUD-LC-03` | Role permission sync records delta of added/removed permissions | Yes. | 🟡 |
| `AUD-LC-04` | Soft-deleted record's history remains queryable | Yes. | 🟡 |
| `AUD-LC-05` | Activity log table is append-only (no UPDATE) | Verify no controller code updates activity rows. | 🟡 |

---

## 19. Data Imports

### 19.1 Happy paths

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `IMP-HP-01` | Upload valid users CSV in dry-run mode | Job processes; status `dry_run_completed`; row counts shown. | 🟡 |
| `IMP-HP-02` | Retry the import as real (no re-upload) | Records inserted into users table. | 🟡 |
| `IMP-HP-03` | Download template CSV for vehicles | Returns header-only CSV. | 🟡 |
| `IMP-HP-04` | Download catalog reference CSV (eps) | Returns full catalog data. | 🟡 |
| `IMP-HP-05` | Purge import (source + errors from S3) | Files removed; `files_purged_at` set. | 🟡 |

### 19.2 Validation

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `IMP-VAL-01` | Upload CSV with missing required columns | Dry-run completes with row-level errors. | 🟡 |
| `IMP-VAL-02` | Upload CSV with invalid FK references (e.g., eps_code not in catalog) | Row errors; download error CSV. | 🟡 |
| `IMP-VAL-03` | Upload non-CSV file | 422 mime. | 🟡 |
| `IMP-VAL-04` | Upload file exceeding limit | 422. | 🟡 |
| `IMP-VAL-05` | Download errors when import had none | 404 friendly. | 🟡 |

### 19.3 RBAC

Only Admin. OP/AC/DR → 403 on every route.

### 19.4 Logical conflicts

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `IMP-LC-01` | Retry-as-real when CSV has errors | Probe: rejected? partial-success? | 🟡 |
| `IMP-LC-02` | `update_existing = true` re-imports matching records | Existing rows updated, not duplicated. | 🟡 |
| `IMP-LC-03` | Dry-run twice on the same file with no fixes | Identical row error report each time. | 🟡 |

---

## 20. Catalogs (Document Types, EPS, Pension Funds, Severance Funds, Incident Types)

Five catalogs, similar shape. Patterns repeat — record one set of scenarios and parameterize.

| ID (template) | Scenario | Expected | Status |
|---|---|---|---|
| `CAT-HP-01` (×5) | Create new entry with code + name | Row visible. | 🟡 |
| `CAT-HP-02` (×5) | Edit name | Saved. | 🟡 |
| `CAT-HP-03` (×5) | Soft-delete (or hard if no soft-delete trait) | Row hidden. | 🟡 |
| `CAT-VAL-01` (×5) | Duplicate code | 422 unique. | 🟡 |
| `CAT-VAL-02` (×5) | Empty name | 422. | 🟡 |
| `CAT-RBAC-01` | DR / AC access (any catalog) | 403 (no `catalogs.manage`). AD + OP have `catalogs.manage` and can CRUD all five catalogs. | 🟡 |
| `CAT-LC-01` | Delete catalog row referenced by a driver/tercero/incident | 422 friendly, never 500. | 🟡 |
| `CAT-LC-02` | Rename catalog code | Existing references continue to work (FK by id, not code). | 🟡 |
| `CAT-LC-03` (IncidentType only) | Soft-delete IncidentType | Removed from picker; existing incidents intact. | 🟡 |
| `CAT-LC-04` (IncidentType only) | Toggle `affects_billing_default` | New incidents default accordingly; existing ones unchanged. | 🟡 |

---

## 21. Settings (per-user)

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `SET-HP-01` | View `/settings/profile` | Shows name, email, email-verified status. | 🟢 |
| `SET-HP-02` | Update name | Saved; navbar reflects new name. | 🟡 |
| `SET-HP-03` | Update email | `email_verified_at` cleared; verification email re-sent. | 🟡 |
| `SET-HP-04` | Update password (correct current + valid new) | Saved; `must_change_password = false`. | 🟢 |
| `SET-HP-05` | Toggle theme (`/settings/appearance`) | Preference stored (cookie or DB). | 🟢 |
| `SET-HP-06` | Enable 2FA (TOTP flow) | Recovery codes shown; subsequent login requires code. | 🟡 |
| `SET-HP-07` | Delete account (`DELETE /settings/profile`) | Soft-deleted; logged out; session invalidated. | 🟡 |
| `SET-VAL-01` | Update password with wrong current_password | 422. | 🟡 |
| `SET-VAL-02` | Update password violating rules | 422. | 🟡 |
| `SET-VAL-03` | Password change throttle: 7th attempt in 1 min | 429. | 🟡 |
| `SET-RBAC-01` | Unauthenticated access to /settings/* | 302 to /login. | 🟡 |
| `SET-LC-01` | Email change → middleware redirects user to verify before resuming | Yes. | 🟡 |
| `SET-LC-02` | Delete account that's linked to a driver | Probe — should block (USR-LC-03 mirror) or cascade. | 🟡 |

---

## 22. Driver portal (`/driver`)

| ID | Scenario | Expected | Status |
|---|---|---|---|
| `DRIVER-HP-01` | Login as driver → redirect to `/driver` | Yes. | 🟡 |
| `DRIVER-HP-02` | Default `selectedDate` = today in operation TZ (TZ-04 cross-ref) | Yes. | 🟡 |
| `DRIVER-HP-03` | Card for assigned service shows confirm-start button when not started | Yes. | 🟡 |
| `DRIVER-HP-04` | After confirm-start, card shows confirm-end | Yes. | 🟡 |
| `DRIVER-HP-05` | After confirm-end, card shows completed badge | Yes. | 🟡 |
| `DRIVER-HP-06` | Decline a service pre-flight with reason | Service marked declined; auto-incident created; operator notified. | 🟡 |
| `DRIVER-HP-07` | Register location card present (Phase 5 F2 deferred — verify exists) | If absent, mark `todo()`. | 🟡 |
| `DRIVER-VAL-01` | Confirm-start when vehicle SOAT has just expired | 422 / friendly "Documentos vencidos." | 🟡 |
| `DRIVER-VAL-02` | Confirm-start when driver license has just expired | 422. | 🟡 |
| `DRIVER-RBAC-01` | Non-driver hits `/driver` | 403 (no `services.register-times`). | 🟡 |
| `DRIVER-RBAC-02` | Driver hits `/dashboard` | 302 → `/driver` (or 403; probe). | 🟡 |
| `DRIVER-LC-01` | Forward planning: select future date | Probe — should be hidden by design (REQ-012). | 🟡 |
| `DRIVER-LC-02` | Past services view | Probe — same. | 🟡 |
| `DRIVER-LC-03` | Confirm-start on a service whose vehicle was deleted | 422 / friendly. | 🟡 |
| `DRIVER-LC-04` | Driver confirms end after midnight (wall-clock crosses day boundary) | `actual_end_at` stored as UTC instant correctly; day status logic unchanged. | 🟡 |

---

## 23. Test execution plan (Phase 3 ordering)

Phase 3 runs the Playwright MCP against these scenarios in roughly this order — earlier groups bootstrap state for later ones:

1. **AUTH + LAYER + DASH** — basic login + RBAC matrix for all roles.
2. **Catalogs (CAT)** — verify seed data is intact and CRUD smoke works.
3. **Master data (VEH, DRV, TP, CTR)** — independent CRUD per module.
4. **Services + Gantt (SVC, GNT)** — the largest module; pulls in all master-data dependencies.
5. **Day Statuses + Day Summary (DAY)** — depends on services existing.
6. **Incidents (INC)** — depends on services + incident types.
7. **Invoices (INV)** — depends on closed services on EJECUTADO days + incidents.
8. **FUEC + FRG** — depends on services + ranges; requires feature flag.
9. **GPS** — depends on services; requires feature flag.
10. **Users / Roles / Audit log (USR, ROLE, AUD)** — depends on activity from earlier phases for AUD.
11. **Imports (IMP)** — independent; CSV uploads.
12. **Settings + Driver portal (SET, DRIVER)** — independent.
13. **Timezone (TZ)** — runs against a separately-seeded DB with services in multiple TZs.

Each group is one `migrate:fresh --seed`. Findings during the run get logged to `bug-log.md` with the scenario ID as the reference.

---

## 24. Phase 4 mapping

Phase 4 (Pest 4 browser tests) translates this catalog 1:1. Suggested directory layout:

```
tests/Browser/
├── Auth/                      (AUTH-*)
├── Layer/                     (LAYER-*)
├── Timezone/                  (TZ-*)
├── Dashboard/                 (DASH-*)
├── Vehicles/                  (VEH-*)
├── Drivers/                   (DRV-*)
├── ThirdParties/              (TP-*)
├── Contracts/                 (CTR-*)
├── Services/                  (SVC-*, GNT-*)
├── DaySummary/                (DAY-*)
├── Incidents/                 (INC-*)
├── Invoices/                  (INV-*)
├── Fuec/                      (FUEC-*, FRG-*)
├── Gps/                       (GPS-*)
├── Users/                     (USR-*)
├── RolesPermissions/          (ROLE-*)
├── AuditLog/                  (AUD-*)
├── Imports/                   (IMP-*)
├── Catalogs/                  (CAT-*)
├── Settings/                  (SET-*)
└── Driver/                    (DRIVER-*)
```

Each Pest `it()` name starts with the scenario ID: `it('SVC-LC-04 driver double-booking is rejected', …)`. Scenarios that fail during Phase 3 are wrapped in `->todo()` with a `// bug-log:NN` comment pointing at the bug-log entry.
