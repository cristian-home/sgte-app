# Phase 1 — Role Workflows & Domain Intent

> Synthesizes intent from SRS, ADRs (esp. 005, 006, 007), data-model.md, phase plans, and the 2026-05-08 datetime audit. Distinguishes **explicit** rules (docs/code) from **inferred** ones; inferred rules will be probed in Phase 3 and turned into bug-log entries if behavior diverges.
>
> Reference users (init-data seeder) for all role workflows below — passwords are `password` except super admin (`SUPER_ADMIN_USER` from `.env`).

## 0. Domain glossary

| Spanish | English | One-liner |
|---|---|---|
| **Servicio** | Service | Trip assignment: vehicle + (driver \| outsourced) + contract + origin/destination + planned time. Core operational unit. |
| **Conductor** | Driver | Licensed operator. Linked optionally to a `User` for portal access. Has license, EPS, pension, severance affiliations. |
| **Vehículo** | Vehicle | Fleet asset. Carries SOAT, RTM, Tarjeta de Operación expiries. `internal_code = 18` ⇒ outsourced (owned by a provider tercero). |
| **Tercero** | Third Party | Unified registry: natural/legal person. Flags `is_customer` and/or `is_provider`. Customers buy services; providers own outsourced vehicles. |
| **Contrato** | Contract | Customer agreement with validity window (half-open: `start_at <= now < end_at`). Object: Empresarial / Turismo / Salud / Ocasional. Generic contracts auto-generated when service date falls outside any existing contract. |
| **FUEC** | Formato Único de Extracto de Contrato | Compliance PDF per service. Requires valid contract + vehicle docs + driver license + an active MinTransporte number range. Feature-flagged `SGTE_FUEC_ENABLED`. |
| **Novedad** | Incident | Event during service execution: DELAY, ACCIDENT, BREAKDOWN, TRAFFIC, WEATHER, NO_SHOW, OTHER. May affect billing via `additional_value`. |
| **Resumen del Día** | Day Summary | Consolidated daily service list. Gate for transitioning a day to EJECUTADO. |
| **Planificador** | Gantt Planner | Daily Y-axis = vehicles, X-axis = hours. Vehicle rows with expired docs render grayed/blocked. |
| **Estado del Día** | Day Status | Operational day state: black (no services) → orange (PROYECTADO, ≥1 open) → green (EJECUTADO, all closed + admin executed). |
| **EPS / Fondo de Pensiones / Fondo de Cesantías** | Health / pension / severance fund | Colombian social-security entities; required FK on driver. |

## 1. Role responsibilities

### 1.1 Super Admin

**Identity in seed data:** whatever `SUPER_ADMIN_USER` in `.env` resolves to.

**What they do.** Emergency-access role. `Gate::before` returns `true` for SA on every gate check — bypasses both route middleware and `FormRequest::authorize()` layers. Has *zero* permissions explicitly assigned; the bypass is the feature.

**Boundaries.** None enforced by RBAC. Organizational/compliance limits live outside the system. The audit log still records SA actions as `causer_id = SA user`.

**Phase 2 probes.** For every "Admin can do X" scenario, verify SA can do X too. Special: verify SA can edit an EJECUTADO service *without* the justification field that Admin must supply (since the FormRequest layer is bypassed entirely).

### 1.2 Administrador (`admin@sgte.app`)

**What they do.** System administrator. Owns master data, billing, FUEC, audit, and user management. Can edit executed services with justification.

**Full CRUD on:** Vehicles · Drivers · Third Parties · Contracts · Services (incl. EJECUTADO day with justification) · Incidents · Invoices · FUEC · Vehicle Locations · Users · Roles (permissions) · Catalogs (EPS, pension, severance — Document Types and Incident Types are CRUD too).

**Read-only on:** Audit Log (every causer/subject in the system) · Reports.

**Landing page:** `/dashboard` — KPI cards + expiry alerts.

**Hard boundaries.**
- Editing service on EJECUTADO day requires a 10–500 char `justification` field; logged as `properties.edited_on_executed_day = true` in activity log.
- Cannot delete a service whose day is EJECUTADO unless they're Super Admin (inferred from `ServiceController::destroy` admin-only gate when `dayStatus.status = Executed`).
- Cannot delete another admin if that would leave zero admins (`UserController::destroy` last-admin guard).
- Cannot delete a User that's linked to a Driver (`driver.user_id IS NOT NULL`) — must clear linkage first.

### 1.3 Operación / Operator (`operator@sgte.app`)

**What they do.** Daily fleet ops. Creates and edits services on PROYECTADO days; records incidents; closes services; presses "Ejecutar Día" when ready.

**Full CRUD on:** Services (only while day is PROYECTADO) · Incidents · Day execution · Vehicles · Drivers · Third Parties · Contracts · Catalogs (Document Types, EPS, Pension Funds, Severance Funds, Incident Types) · Vehicle Locations (register only).

**Read-only on:** Calendar · Planificador · Reports.

**Hidden / 403:** Facturación · Administración (users / roles / permisos / auditoría) · FUEC · GPS · Importaciones.

**Landing page:** `/dashboard`.

**Hard boundaries.**
- Cannot edit services on EJECUTADO day — `ServiceUpdateRequest::authorize()` returns 403.
- Cannot view audit log (`can:audit-log.view` not granted).

### 1.4 Conductor / Driver (`driver@sgte.app`)

**What they do.** Field operator. Sees only today's assigned services. Confirms start/end, declines pre-flight, registers incidents, optionally registers GPS location.

**Full CRUD on:** Incidents (only on services they're the assigned driver of) · Vehicle Locations (only via `driver.location.store`).

**Restricted:** Services list — only today's assignments, no forward/backward navigation.

**Landing page:** `/driver` (login redirects there, not `/dashboard`).

**Hidden / 403:** Everything else — Producción top-level pages, Gestión, Facturación, Administración, FUEC config, GPS map (but they can register their own location via the inline `/driver/services/{service}/location` endpoint), Catálogos, Importaciones.

**Hard boundaries.**
- `DriverLocationController::store` rejects with 403 if `Auth::user()->driver?->id !== $service->driver_id`.
- Driver portal won't list a service for a date in the past or future — only `selectedDate` (defaults to today in operation_tz). Forward planning is intentionally hidden (REQ-012 design).
- Confirming start re-checks vehicle docs + driver license; expired ⇒ blocked.

### 1.5 Contabilidad / Accounting (`accounting@sgte.app`)

**What they do.** Billing & accounting. Builds invoices from executed-day services; manages payment status; can edit accounting fields of EJECUTADO services.

**Full CRUD on:** Invoices · Service accounting fields (`unit_value`, `quantity`, `billing_groups`, `payment_method`, `invoice_id`) on EJECUTADO day only.

**Read-only on:** Services (operational fields), Calendar, Resumen del Día, Vehicles, Drivers, Third Parties, Contracts, billing-affecting Incidents.

**Hidden / 403:** Producción operational edits, Administración, FUEC, GPS, Catalogs.

**Landing page:** `/dashboard`.

**Hard boundaries.**
- Can NOT create or edit services operationally (no `services.create` / `services.update-projected`).
- On EJECUTADO day, the `ServiceUpdateRequest` whitelists Accounting to only the accounting fields above; attempting to update planned_start_at / driver_id / etc. should fail validation/authorization (probe in Phase 3).
- No `incidents.create` — can view but not register.
- No `fuec.generate`.

## 2. Module-by-module intent (with invariants for Phase 2)

For each module: purpose, lifecycle, business rules (explicit vs inferred), cross-module dependencies, and "what to probe" notes that seed Phase 2's scenarios.

### 2.1 Vehicles

**Purpose.** Register fleet assets with their legal documentation. Block assignment if any doc lapses.

**Lifecycle.** Created with SOAT / RTM / Tarjeta de Operación expiries. No hard delete (soft-deletes via `SoftDeletes`). Code 18 = outsourced (requires `third_party_id`).

**Invariants — explicit.**
- (Code: `ServiceStoreRequest::validateVehicleDocumentsNotExpired()`) Vehicle cannot be assigned to a service when any of `soat_due_at`, `rtm_due_at`, `operation_card_due_at` has elapsed against the vehicle's `timezone` using half-open interval semantics.
- (Code: `GanttController@index`) Gantt rows where any doc is expired render `blocked: true` (grayed UI).
- (Schedule: `routes/console.php`) 30/15/5-day expiry alerts emitted daily at 07:00 via `app:check-expirations`.

**Invariants — inferred / to verify in Phase 3.**
- Re-licensing: does updating `*_due_at` to a future date automatically restore Gantt availability? (Should — code reads the column on each request, no cache.)
- Outsourced vehicles (`is_third_party = true`) hide the driver field on the service form and use provider info instead (REQ-003 AC 2). Verify the form actually omits driver and that `ServiceStoreRequest` accepts a null driver only when `vehicle.is_third_party = true`.

**Cross-module deps.** Municipality catalog · Third Party (only when outsourced).

### 2.2 Drivers

**Purpose.** Register drivers with license/SS data; enforce license validity + category mapping on assignment.

**Lifecycle.** Created with category (C1/C2/C3), `license_due_at`, `has_social_security` flag, EPS/pension/severance FKs. Optional `user_id` link enables the driver portal.

**Invariants — explicit.**
- (Code: `ServiceStoreRequest::LICENSE_CATEGORY_MAP`) Driver license category must be in the vehicle-type's allowed list. Permissive ("minimum category") semantics:
  - **Bus** → allowed: `C2, C3`
  - **Buseta** → allowed: `C2, C3`
  - **Van** → allowed: `C1, C2, C3`
  - **Automobile** → allowed: `C1, C2, C3`
  - Practical effect: a C3 driver can drive anything; a C1 driver can only drive Van or Automobile.
- (Code: `ServiceStoreRequest::validateDriverLicense()`) Expired license blocks assignment.
- (Q6 amended 2026-05-18) Driver with `has_social_security = false` is **hard-blocked** from service assignment. Rationale: legal liability for the company if a driver without active SS is in an accident. The earlier Q6 reading ("warn + auto-incident") is retracted; the validator at `ServiceStoreRequest.php:353` is the canonical behavior. Phase 2 catalog § 5.4 (`DRV-LC-03`) and § 8.4 (`SVC-LC-12`) updated.

**Cross-module deps.** EPS, pension, severance catalogs · municipality · optional User.

### 2.3 Third Parties

**Purpose.** One registry for both clients (`is_customer`) and providers (`is_provider`). Avoids duplicate addresses/contacts when an entity is both.

**Lifecycle.** Natural or legal person; soft-deleted, not hard-deleted.

**Invariants — explicit.**
- (Data model) Customer terceros are linkable from contracts and invoices.
- (Data model) Provider terceros own outsourced vehicles (`vehicles.third_party_id`).

**Invariants — explicit (confirmed Q8).**
- A tercero can hold `is_customer = true` AND `is_provider = true` simultaneously. Such a dual-flag entity may be used as the contract client on `/contracts` AND as the vehicle owner on `/vehicles` (outsourced code 18). The system imposes no exclusivity between the roles. Probe in Phase 3 that no UI or controller silently disables the second flag.

### 2.4 Contracts

**Purpose.** Formalize agreement for services. Time-bounded.

**Lifecycle.** Manual create (start/end + object enum) OR auto-generated as generic when a service date falls outside any existing contract for the same tercero.

**Invariants — explicit.**
- (ADR-007 + data model) `start_at`/`end_at` are half-open: `start_at <= now < end_at`.
- (Code: `ServiceStoreRequest::validateContractValidity()`) Service date outside contract → reject OR fall back to generic.
- (Trait: `HasTimezone`) Each contract carries its own `timezone`; the wall-clock day is anchored in that TZ, not viewer's.

**Invariants — explicit (Q5 amended 2026-05-18).**
- Generic contracts are named `GEN-NNNN-YYYY` where `NNNN` is a zero-padded sequential integer that resets per calendar year (verified in `ContractController::store`). The earlier Q5 reading ("Generic Contract #N global sequential") is retracted; the existing code naming is canonical.
- (Pending fix — bug-log:BUG-01) Auto-creation on out-of-window service date is **not yet implemented**; `ServiceStoreRequest` currently rejects. Triaged as a bug to fix. Phase 2 catalog scenarios `CTR-LC-02` / `SVC-LC-13` already assert the auto-create behavior — Pest tests for these get `->todo('bug-log:BUG-01')` until the fix lands.
- Generic-contract lifetime: not documented. Probe whether closing the service removes the contract (likely persists for audit; verify).

### 2.5 Services + Planificador (Gantt)

**Purpose.** Core operational unit. Captures planned + actual execution + billing context.

**Lifecycle.**
1. **Created** (PROYECTADO): operator picks vehicle, driver/none, contract, origin/destination, planned start + duration.
2. **Validated** before save: vehicle docs valid · driver license + category match · contract valid (or generic) · no vehicle-time conflict · no driver-time conflict (inferred — see Q2).
3. **Executed**: driver (or operator) confirms start → `actual_start_at` set. Incidents may be recorded. Confirm end → `actual_end_at` + computed `actual_duration`.
4. **Closed** (`service_status = closed`): incidents finalized; service ready for invoice. Once `actual_start_at` is set, it cannot be cleared (`docs/audits/*` — service-reopen invariant).
5. **Reopened** (admin only, with logging): per REQ-009. Limited circumstances.
6. **Day execution**: when all services closed, admin/operator presses "Ejecutar Día" → DayStatus = EJECUTADO. Day locks.

**Invariants — explicit.**
- (Code) Schedule conflict for vehicle: no other open service on same vehicle within `[planned_start_at, planned_start_at + planned_duration)`.
- (Code) Doc/license re-checks happen *both* on create AND on `confirmStart` from the driver portal — driver flow re-validates inline.
- (Code) Retroactive entry: service for a past day with status=closed on creation logs `properties.retroactive_entry = true`.
- (Audit) `actual_start_at` is irreversible — service can be reopened (status flip) but the wall-clock start time is preserved.

**Invariants — explicit (Q2 confirmed; Q4 with pending fix).**
- **Q2 — Driver double-booking is blocked.** A driver cannot be assigned to two overlapping services. Same enforcement strictness as vehicle conflicts. Phase 2 must cover both vehicle-and-driver and driver-only conflict scenarios.
- **Q4 — Late-add service on EJECUTADO day** (pending fix — bug-log:BUG-03): the **intended** behavior is that Admin (and Super Admin) may add a service to an already-executed day when a `justification` field (10–500 chars) is supplied; other roles are rejected. The day's status remains EJECUTADO. Currently `ServiceStoreRequest::validateExecutedDayRestriction` rejects for everyone — Pest scenarios `SVC-LC-17` get `->todo('bug-log:BUG-03')` until fixed.
- Generic contract auto-creation on out-of-window service date: pending — see § 2.4 above.

**Cross-module deps.** Vehicle · Driver · Contract · Third Party · Municipalities · DayStatus (auto-created on first service) · Incidents (1:many) · Invoice (0:1) · Fuec (0:many).

### 2.6 Day Statuses & "Ejecutar Día"

**Purpose.** Operational day state machine. Locks the day for editing.

**Lifecycle.**
- No services → no DayStatus row (calendar cell renders black).
- ≥1 open service → row exists with `status = projected` (orange).
- All services closed + button pressed → `status = executed`, `executor_id`, `executed_at` set (green).

**Invariants — explicit.**
- (Code: `DayStatusController@execute`) Refuses if any service for the day is still open, or if there are zero services.
- (REQ-009) On EJECUTADO day: Operación = read-only; Admin = edit with justification; Accounting = edit accounting fields only.

**Invariants — explicit (Q3 amended 2026-05-18; Q4 pending fix).**
- **Q3 — EJECUTADO is one-way for Admin and Operator; Super Admin override allowed with justification** (pending fix — bug-log:BUG-05). Intended behavior: a transition guard in `DayStatusUpdateRequest` rejects `executed → projected` for Admin and Operator. Super Admin may revert by supplying a `justification` field (10–500 chars); the controller must clear `executor_id` / `executed_at` on a successful reversal and append an audit-log entry. Currently no guard exists — any user with `day-summary.execute` can revert; Pest scenario `DAY-LC-01` gets `->todo('bug-log:BUG-05')`.
- **Q4 — Late-added service on EJECUTADO day** — see § 2.5 above (bug-log:BUG-03).

### 2.7 Incidents (Novedades / ServiceIncident)

**Purpose.** Capture events during service execution. May change billing.

**Invariants — explicit.**
- `is_driver_report` derived from causer's role at create time.
- `affects_billing` defaults from `incident_type.affects_billing_default`; user can override per-incident.
- `additional_value` populated only when `affects_billing = true`; positive = surcharge, negative = discount.
- (Code: `InvoiceServiceAttachRequest`) Attaching a service to an invoice when it has billing-affecting incidents requires an `override_justification` field; the override is recorded in activity log.

**Phase 2 probes.**
- Driver can only create incidents on services where `service.driver_id = current_user.driver.id` (`Gate::authorize` inside `store`).
- Multiple incidents on one service → invoice total = service unit_value × quantity + sum(incident.additional_value).
- Editing an incident's `additional_value` after invoice is generated — does `recompute-total` pick it up? (Idempotent endpoint exists; test it.)

### 2.8 Invoices

**Purpose.** Group closed services from one tercero into a billing document. Informational PDF (not DIAN-compliant fiscal doc).

**Invariants — explicit.**
- (Data model) `third_party_id` required — one invoice = services from one tercero.
- (`InvoiceServiceAttachRequest`) Can only attach closed services from EJECUTADO days.
- `markPaid` only valid when `payment_status = pending` (inline validation).

**Invariants — inferred.**
- Soft-deleted invoices remain in the activity log; verify UI doesn't expose un-delete.
- "One invoice = one tercero" — verify the `attach` endpoint rejects services from a different tercero.

### 2.9 FUEC

**Purpose.** Optional compliance PDF + QR-verified URL per service. Generates a consecutive number from an active MinTransporte range.

**Invariants — explicit (pre-generation checks).**
- Contract must be active (half-open window).
- Vehicle docs (SOAT, RTM, Operation Card) all valid against vehicle's `timezone`.
- Driver license valid + category match against vehicle.
- An `active` `FuecNumberRange` exists with `remaining() > 0`.
- No existing `vigente` FUEC for the same service (inferred — verify).

**Concurrency invariant.**
- (Code: `FuecGenerator`) Consecutive allocation is wrapped in a DB transaction with row-level lock; concurrent generation requests get different consecutives. Test by firing two simultaneous POSTs.
- Range exhaustion throws `FuecRangeExhaustedException` → 422 with friendly message.

**Lifecycle.**
- Create → status `vigente`, PDF written to MinIO/S3, public verify URL contains `uuid`.
- Cancel → status `anulado`, reason logged. Public verify shows `ANULADO`.

**Invariants — explicit (Q7 pending fix — bug-log:BUG-06).**
- **Q7 — Auto-supersede when generating a new FUEC for a service with an active one.** Intended behavior: the new generation auto-cancels the previous active FUEC in the same transaction, with a standardized cancellation reason `"Superseded by new FUEC generation"`. Currently `FuecPreGenerationChecks.php:120` rejects the second generation outright; Pest scenario `FUEC-LC-01` gets `->todo('bug-log:BUG-06')`.
- Activating a new number range requires manual deactivation of any current active range — verified working (FRG-LC-01); not atomic but enforced.

### 2.10 GPS / Vehicle Locations

**Purpose.** Optional vehicle tracking. Non-blocking if unavailable (REQ-010 AC 4).

**Invariants — explicit.**
- (Middleware) `gps.enabled` 404s all routes when flag is off.
- (Code: `DriverLocationController@store`) 403 if `driver.id !== service.driver_id`.

**Invariants — inferred.**
- Map polls every 30s — verify no auth refresh issue.
- Browser geolocation rejection: service execution still proceeds (REQ-010 AC 4).

### 2.11 Users / Roles / Permissions

**Invariants — explicit.**
- (`UserController::destroy`) Blocks self-delete, last-admin delete, and delete of a User linked to a Driver.
- (`UserController::store`/`update`) Optional welcome email with temp password (`must_change_password = true` flag).
- (`RoleController::update`) Permission sync logs delta via activity log.
- (`EnsureUserIsActive` middleware) `is_active = false` aborts every authenticated request.
- (`EnsurePasswordChanged`) `must_change_password = true` forces redirect to password change page.

**Invariants — inferred.**
- Roles can hold multiple per user (Spatie supports it). Verify the UI lets you assign multiple and the gate logic aggregates them.

### 2.12 Audit Log

**Purpose.** Append-only history of every domain mutation. REQ-009 compliance evidence.

**Invariants — explicit.**
- `LogsActivity` on every domain model (see model table).
- EJECUTADO-day service edits log `properties.justification` and `properties.edited_on_executed_day`.
- Filters: log_name, subject_type, causer_id, event, date range.

**Probes.** Update a service on EJECUTADO day → activity row contains justification. SuperAdmin update without justification → activity row still recorded but `justification` may be absent (verify Phase 3 outcome).

### 2.13 Data Imports

**Purpose.** Bulk upload of users / third-parties / drivers / vehicles via CSV. Dry-run + retry-as-real flow.

**Invariants — explicit.**
- Upload → dry-run job → status `dry_run_completed` with row-level errors; user can "retry as real" or fix CSV.
- Errors CSV generated for download.
- Purge removes source/errors from S3.

**Probes.** Upload bad CSV → errors file populated; download it; verify content matches errors shown.

### 2.14 Catalogs

Document Types · EPS · Pension Funds · Severance Funds · Incident Types · Municipalities · Departments.

**Invariants — explicit.**
- (`catalogs.manage` permission) Admin + Operator have CRUD (NOTE-02); other roles 403.
- (Data model) Soft-deleted incident types stay in history but don't appear in pickers.

**Probes.** Renaming a catalog code should NOT break existing records that reference it (use FK + display name). Deleting a catalog row referenced by a record should fail with FK constraint error (must surface as a friendly validation error, not a 500).

### 2.15 Settings (per-user)

- `/settings/profile` — name, email, email-verification reset on email change.
- `/settings/password` — throttled 6/min; clears `must_change_password`.
- `/settings/appearance` — theme.
- `/settings/two-factor` — Fortify TOTP/recovery codes (optional `password.confirm` middleware).

## 3. Datetime / timezone test surface

Drawn from ADR-007 + `docs/audits/2026-05-08-datetime-timezone-discovery.md`. The audit found 10 bugs from raw `Date.toISOString().slice(0,10)` patterns; these are now prevented but regression coverage is needed.

**Three timezones interact:**
- **Operation TZ** — `config('app.operation_tz')` = `America/Bogota`. Day boundaries, "today" semantics for ops, day-status transitions.
- **Record TZ** — each Service / Contract / Driver / Vehicle / Invoice / DataImport carries its own `timezone` column.
- **Viewer TZ** — browser's `Intl.DateTimeFormat().resolvedOptions().timeZone`, captured via `X-Viewer-Timezone` header → persisted to `users.timezone`.

**Test scenarios to enumerate in Phase 2:**

1. **Cross-midnight service creation.** Viewer in Madrid (UTC+2) at `2026-05-09 00:13` creates a service. Operation TZ is `America/Bogota` (`2026-05-08 17:13`). Service should land on May 8 in operation TZ. Day-summary view for May 8 should include it; for May 9 should not.

2. **Document expiry on the boundary.** Vehicle SOAT due `2026-05-08 00:00 America/Bogota`. At `2026-05-07 23:59 America/Bogota` the vehicle is still assignable; at `2026-05-08 00:00 America/Bogota` it is not. The service-create form, the Gantt blocked-row UI, and the FUEC pre-check all agree.

3. **Driver dashboard "today."** Driver opens `/driver` from Madrid (browser TZ = Europe/Madrid). The "today" services list should reflect operation TZ's today (Bogota), not Madrid's. Banner/hint should disclose the divergence.

4. **Retroactive entry alert.** Operator creates a service for a past operational day and immediately closes it. Backend logs `retroactive_entry = true` via activity log. The flash message should say "Registro retroactivo."

5. **Half-open contract boundary.** Contract `start_at = 2026-05-01 00:00 BOG`, `end_at = 2026-05-08 00:00 BOG`. Service on 2026-05-07 → accepted (in window). Service on 2026-05-08 → outside window → generic contract auto-created (or rejected, depending on UI flow).

6. **Audit log timestamps.** All shown in viewer TZ via `formatTimestampInViewerTz`. Switch the user's TZ from Bogota to Madrid → existing log entries re-format (verify via partial reload of `config.viewer_tz`).

## 4. Feature flags & phase-plan open items

| Flag | Default | What it gates |
|---|---|---|
| `SGTE_FUEC_ENABLED` | false in `.env`, **must be true** to test the FUEC module | All `/fuecs*` and `/fuec-number-ranges*` routes; sidebar FUEC group; FUEC generation in service detail |
| `SGTE_GPS_ENABLED` | false in `.env`, **must be true** to test the GPS module | `/gps/map`, `/vehicle-locations*`, `driver.location.store` |

**Phase-plan items still open** (per `docs/phases/` skim):

- Load testing with representative data (100 vehicles, 300 services/day) — outside the scope of this e2e plan but flag if perf regressions surface during Playwright runs.
- Formal security review — `/security-review` slash command exists. Out of this plan's scope; flag any auth-bypass/injection findings to bug log P0.
- Rate limiting beyond Laravel defaults — out of scope.
- Driver UI polish (F2: location-registration card) — verify the card actually exists on `/driver`; if not, scenario marked `todo()`.

## 5. ADR cheat sheet (constraints for testers)

| ADR | One-line for testers |
|---|---|
| ADR-001 | Permission strings are auto-generated. Always import from `@/enums/Permission`, never hardcode. |
| ADR-002 | `SearchesDatabase` trait → SQL pg_trgm on Postgres; degrades to LIKE on SQLite. Browser tests run against Postgres in Sail — relevance ordering is real. |
| ADR-003 | DataTable component — pagination, sort, filter affordances. UI testing should target its `data-testid`s (verify they exist). |
| ADR-004 | Scout + Typesense for Service full-text. Index invalidation lives in queue jobs — flush before/after tests if the Typesense index leaks state. |
| ADR-005 | **Three-layer authorization, no Eloquent Policies.** Probe every layer. |
| ADR-006 | CarbonImmutable everywhere; password rules `min:8 + mixedCase + numbers + symbols + uncompromised`; activity log on all domain models. |
| ADR-007 | **Per-row timezone pattern.** Every business datetime test must set TZ explicitly and use wall-clock accessors. |

## 6. Explicit-vs-inferred summary (carry into Phase 2)

**Explicit (SRS or code):**
- Vehicle docs + driver license validity blocks service assignment (REQ-003, 004, 005).
- Contract validity drives generic-contract creation (REQ-006).
- Vehicle-time conflicts blocked (REQ-003 AC 3).
- Day status state machine; EJECUTADO immutability with role-specific exceptions (REQ-001, REQ-009).
- Incident billing impact added to invoice total.
- FUEC pre-checks (contract + docs + license + range).
- Public FUEC verify page (REQ-007 AC).
- 30/15/5-day expiry alerts (REQ-004, REQ-005).

**Resolved by user (2026-05-18) — now explicit, treat as pass criteria:**
- **Q1.** License category mapping is permissive ("minimum"). Bus/Buseta→{C2,C3}; Van/Automobile→{C1,C2,C3} per `LICENSE_CATEGORY_MAP`.
- **Q2.** Driver double-booking is blocked (same as vehicle conflict).
- **Q3.** EJECUTADO is permanent; admin/accounting field edits + billing-impacting incidents are the only allowed exceptions.
- **Q4.** Adding a service to an EJECUTADO day is allowed as an after-the-fact exception; day stays EJECUTADO.
- **Q5.** Generic contracts named `Generic Contract #N` (sequential).
- **Q6.** `has_social_security = false` → assignment proceeds with warning + auto-incident.
- **Q7.** Multi-FUEC over time, one `vigente` at a time; new FUEC supersedes the previous.
- **Q8.** Tercero may be both `is_customer` and `is_provider`; full dual-role.

**Still inferred — Phase 3 must verify behavior:**
- Generic-contract lifetime (does closing service purge the generic contract?).
- Whether the new-FUEC-supersedes flow auto-cancels the prior FUEC vs. requires manual cancel first.
- Permission/role required to late-add a service to an EJECUTADO day and how it's audit-logged.
- Number-range activation atomicity (cannot have two active simultaneously).
