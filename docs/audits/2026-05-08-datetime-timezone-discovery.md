# Datetime/timezone discovery audit — 2026-05-08

Status: ready-for-fix-plan
Owner: cristian + Claude Code
Goal: reproduce, locate and document every observable date/datetime/timezone bug across SGTE so a follow-up plan-mode session can design the fix.

## TL;DR — what to fix and in what order

1. **F-001 / F-007 (storage and serialization)** — Add a global `Y-m-d` serializer for `date` casts (or move to per-row `timezone` + `datetime` columns), and add a `timezone` column to every model with business dates.
2. **F-002 (services form)** — Normalize date strings before string-comparing in `service-form.tsx:245-252`.
3. **F-003 (document-status)** — Anchor `today` in operation_tz (or per-record TZ) instead of UTC-string parsed-as-local in `document-status.ts:72,125`.
4. **F-008 (Fuec / ServiceIncident / DayStatus.executed_at)** — Change `timestamp` cast to `datetime` so JSON emits ISO instead of Unix integer.
5. **F-006 (architecture)** — Capture the viewer's IANA TZ on the client and forward it via Inertia/HandleInertiaRequests so the backend can use it for any future TZ-aware rendering.
6. **F-004 / F-005 (UI today)** — Replace `new Date().toISOString().slice(0,10)` calls with helpers reading operation_tz (or viewer TZ once F-006 is in).
7. **F-009 (driver UX)** — Decide whether to widen the driver dashboard window (today + N days) or add a calendar.
8. **F-010 (DayStatus shadowing)** — Resolve the `date` accessor / cast collision.

## Scope and rules

- Read-only on app code; the only writable artifact is this document.
- Browser-driven via Playwright MCP. Multi-TZ probing (Bogotá, UTC, New_York, Madrid).
- Backend already has `config('app.timezone') = 'UTC'`, `config('app.operation_tz') = 'America/Bogota'` and the `services` table uses `timestampTz` + per-row `timezone` column.

## Pre-flight (captured 2026-05-08 19:20 UTC / 14:20 Bogotá)

| Layer | Value |
|---|---|
| Container time (`date -u`) | `Fri May  8 19:20:53 UTC 2026` |
| Container PHP `date_default_timezone_get()` | `UTC` |
| Postgres `SHOW timezone` | `UTC` |
| Host Mac `date` | `Fri May  8 14:20:54 -05 2026` (offset `-05`, i.e. America/Bogotá) |
| `config('app.timezone')` | `UTC` |
| `config('app.operation_tz')` | `America/Bogota` |
| `now()` (UTC) | `2026-05-08T19:20:57+00:00` |
| `now(operation_tz)` | `2026-05-08T14:20:57-05:00` |
| `now(operation_tz)->toDateString()` | `2026-05-08` |

Conclusion: storage layer is correctly UTC, operational TZ is `America/Bogota`. Any inconsistency observed below is therefore in **(a)** how the frontend computes "today", **(b)** how non-`services` models persist business dates without a TZ column, or **(c)** how Laravel serializes `date` casts to JSON consumed by string-based comparisons in React.

## Hypotheses to validate (before evidence)

| # | Hypothesis | Source |
|---|---|---|
| H1 | Frontend computes "today" as `new Date().toISOString().slice(0,10)` (browser-UTC), diverging from `operation_tz` after 19:00 Bogotá. | `resources/js/components/services/service-form.tsx:312` |
| H2 | Inertia does not share the browser-detected IANA TZ to backend; nothing in `HandleInertiaRequests` captures it. | `app/Http/Middleware/HandleInertiaRequests.php` |
| H3 | Contract `start_date` / `end_date` (`date` cast) is serialized as `'YYYY-MM-DDTHH:mm:ss.uuuuuuZ'`; frontend filters with string-lex against `'YYYY-MM-DD'`, breaking. | `resources/js/components/services/service-form.tsx:245-252` |
| H4 | Models with business dates lack a `timezone` column: Contract, Driver (`license_due_date`), Invoice (`issue_date`), Vehicle (docs), Fuec (`generated_at`), ServiceIncident (`reported_at`), DataImport. | various migrations |
| H5 | Driver Dashboard query is correct in operation_tz, but the bug surfaces when the operator (with non-Bogotá browser) created the service for the wrong calendar day to begin with. | `app/Http/Controllers/DriverDashboardController.php:34,47` |

## Findings template

> Each finding gets a unique ID `F-NNN`, a short title, and these fields. Add screenshots/snapshots under `.claude/playwright-output/` and reference them by filename.

```
### F-NNN — <short title>
- Severity: high | medium | low
- Module: services | contracts | driver | invoices | fuec | …
- Browser TZ: …
- Steps to reproduce:
- Observed:
- Expected:
- Suspected root cause: <path:line>
- Related code:
- Snapshots: <files in .claude/playwright-output/>
- Notes:
```

## Findings

### F-001 — Eloquent serializes `date` casts as full ISO with `Z`, not `Y-m-d`

- Severity: high
- Module: backend serialization (cross-cutting)
- Browser TZ: any
- Steps to reproduce:
  1. As admin, navigate to `http://localhost/services/create`.
  2. In the browser console, fetch `/services/create` with the Inertia headers and inspect `props.contracts[0]`.
- Observed: `start_date` and `end_date` come back as `"2026-01-01T00:00:00.000000Z"` (ISO 8601 with microseconds + Z), not as `"2026-01-01"`.
- Expected: pure `Y-m-d` since the column is a date cast.
- Suspected root cause: default Carbon JSON serialization for `date` casts in Laravel 12. No `Y-m-d` format override is applied on Contract, Driver (`license_due_date`), Vehicle (`soat_due_date`, `rtm_due_date`), Invoice (`issue_date`), or any model with `'field' => 'date'` casts.
- Related code:
  - `app/Models/Contract.php:43-55` — `'start_date' => 'date'`, `'end_date' => 'date'`
  - `app/Models/Driver.php:51-66` — `'license_due_date' => 'date'`
  - `app/Models/Service.php:90` — `'service_date_local' => 'immutable_date'` (same shape)
- Notes: this is the root cause of F-002 and any other frontend code that does `<=`/`>=` between a backend `date` field and a `Y-m-d` string. **Affects every model that uses a `date` cast.**

### F-002 — Service form drops a contract when `contract.start_date == service_date`

- Severity: high (matches user-reported "contrato vencido" symptom)
- Module: services (create form)
- Browser TZ: any
- Steps to reproduce:
  1. Suppose a contract has `start_date = '2026-05-08'`.
  2. Operator opens `/services/create` and picks `service_date = '2026-05-08'`.
  3. Open the contract dropdown.
- Observed: the contract is missing from the dropdown.
- Expected: contract appears (it's valid as of today).
- Suspected root cause: `resources/js/components/services/service-form.tsx:245-252` — string-lex filter:
  ```ts
  contracts.filter(c => c.start_date <= data.service_date && c.end_date >= data.service_date)
  ```
  With F-001, `c.start_date = '2026-05-08T00:00:00.000000Z'`. The string `'2026-05-08T00:00:00.000000Z' <= '2026-05-08'` evaluates to `false` (longer string is greater), so the filter rejects.
- Related code: `resources/js/components/services/service-form.tsx:245-252`.
- Verification (proven, not visually demonstrated):
  ```js
  '2026-05-08T00:00:00.000000Z' <= '2026-05-08' // → false (BUG)
  '2026-05-08T00:00:00.000000Z' >= '2026-05-08' // → true (the asymmetric end_date case is benign)
  '2026-01-01T00:00:00.000000Z' <= '2026-05-08' // → true (older contracts are fine)
  '2025-12-31T00:00:00.000000Z' >= '2026-05-08' // → false (older end_date correctly excluded)
  ```
  Symmetric fix needed: normalize both sides to `Y-m-d` (or compare as dates).

### F-003 — `localTodayMs` mixes UTC date with local-time interpretation

- Severity: medium (user-visible in last hours of the calendar day)
- Module: documents/contracts status pills (cross-cutting)
- Browser TZ: any
- Steps to reproduce:
  - At 23:00 Bogotá on day D, open any view rendering `<ContractPeriodPill>` or `<VehicleDocumentPills>`.
  - A contract or document due exactly on day D is shown as "vencido".
- Observed: status flips to `vencido`/`expired` between ~19:00–23:59 Bogotá (because UTC has already rolled to D+1).
- Expected: status flips at midnight in the user's local TZ (or operation_tz).
- Suspected root cause: `resources/js/lib/document-status.ts:72-73,125-128`:
  ```ts
  const todayString = today ?? new Date().toISOString().slice(0, 10);  // UTC date
  const todayMs = new Date(`${todayString}T00:00:00`).getTime();        // interpreted as LOCAL midnight
  ```
  The string is the UTC calendar day, but it is parsed as if it were local time. After UTC rolls past midnight, `todayString` jumps to D+1 even though the local clock is still on D.
- Related code:
  - `resources/js/lib/document-status.ts:72-73`
  - `resources/js/lib/document-status.ts:125-128`
  - `documentStatus()`, `statusFor()`, `contractPeriodStatus()`, `contractDaysRemaining()` all derive from this.
- Notes: there is no per-record `timezone` to anchor "today" to either, so the natural fix requires both the data and the renderer to agree on a TZ.

### F-004 — Retroactive-entry gate computes `today` in browser-UTC

- Severity: medium
- Module: services (create form, "registro retroactivo" alert)
- Browser TZ: any non-UTC
- Steps to reproduce:
  - At 19:00 Bogotá, on `/services/create`, pick a service date that the operator considers "today" (e.g. `2026-05-08` while local is May 8 evening).
  - Toggle status to Cerrado.
- Observed: form alerts "Registro retroactivo" when the operator is creating same-day, because UTC has rolled to `2026-05-09`.
- Expected: alert only when the picked date is strictly before today in operation_tz.
- Suspected root cause: `resources/js/components/services/service-form.tsx:312`:
  ```ts
  const todayIso = new Date().toISOString().slice(0, 10); // UTC date
  ```
- Related code: `resources/js/components/services/service-form.tsx:312-324`. Backend equivalent in `app/Http/Requests/ServiceStoreRequest.php:160` uses `Carbon::now($operationTz)->toDateString()`, so the front and back disagree on what "today" means.

### F-005 — Driver dashboard header label uses browser TZ; service list uses operation_tz

- Severity: medium (visible inconsistency)
- Module: driver dashboard
- Browser TZ: anything not equal to operation_tz
- Steps to reproduce:
  1. Set browser TZ to e.g. UTC or America/New_York via `Emulation.setTimezoneOverride`.
  2. Login as `driver@sgte.app` and visit `/driver`.
- Observed: header shows `viernes, 8 de mayo de 2026` (browser TZ), but the service list is filtered using `today_in_operation_tz` (server). When the browser is on a different calendar day from operation_tz, the header announces a date that doesn't match the data.
- Expected: header and list agree on the same notion of "today" — preferably operation_tz, with a note when the viewer is in a different TZ.
- Suspected root cause: `resources/js/pages/driver/index.tsx:84-89`:
  ```ts
  const today = new Intl.DateTimeFormat('es-CO', {...}).format(new Date()); // browser TZ
  ```
  vs `app/Http/Controllers/DriverDashboardController.php:34,47`:
  ```php
  $today = Carbon::now($operationTz)->toDateString();
  ```
- Related code: as above.

### F-006 — Inertia shared props don't include the viewer's IANA timezone

- Severity: medium (architecturally required by the requested model)
- Module: cross-cutting
- Steps to reproduce: read `app/Http/Middleware/HandleInertiaRequests.php` — no `timezone` field shared from `Intl.DateTimeFormat().resolvedOptions().timeZone`.
- Observed: backend has no way to know the user's actual TZ; only `config.operation_tz` is exposed.
- Expected: capture the browser TZ (e.g. via a meta tag set on first page-load JS, or a one-off `POST /api/me/timezone`, or an axios interceptor adding a header) and share it in `auth.user.timezone` or `viewer.timezone`. The user's design states "ese timezone debe ser correspondiente al timezone del navegador/red actual" — that requires explicit transport.
- Related code: `app/Http/Middleware/HandleInertiaRequests.php:36-57`.

### F-007 — Business-date models lack a per-row `timezone` column

- Severity: high (core of the requested model)
- Module: data model
- Models affected (per user's stated design "all business date/datetime fields stored UTC + a `timezone` column per row"):
  - `Contract` — `start_date`, `end_date` (`date` cast). Migration `database/migrations/2026_02_27_225421_create_contracts_table.php`. No `timezone` column.
  - `Driver` — `license_due_date` (`date`). Migration `database/migrations/2026_02_27_225419_create_drivers_table.php`. No per-row TZ.
  - `Vehicle` — `soat_due_date`, `rtm_due_date`, `tarjeta_operacion_due_date` (and similar). No per-row TZ.
  - `Invoice` — `issue_date` (`date`). No per-row TZ.
  - `Fuec` — `generated_at` (`timestamp` cast — see F-008). Migration says `timestampTz`.
  - `ServiceIncident` — `reported_at` (`timestamp` cast). Same shape concern.
  - `DataImport` — `started_at`, `completed_at`, `files_purged_at` (`immutable_datetime` without offset suffix).
- Service is the only model that already follows the model: `Service.timezone` + `service_date_local` + `planned_start_at` (`timestampTz`).
- Notes: contract semantics are typically "calendar-day dates" tied to a jurisdiction. Two design choices for the fix:
  - Each contract carries its own IANA TZ (column `contracts.timezone`) and the date is interpreted in that TZ. **Aligns with user's request.**
  - Or treat dates as floating (no TZ) and only timestamps as instants. Simpler but doesn't match the user's stated rule.

### F-008 — `timestamp` cast emits Unix integers in JSON (not ISO)

- Severity: medium (was low — empirically confirmed worse than expected)
- Module: backend models
- Observed via tinker (`Fuec::first()->toArray()`):
  - `app/Models/Fuec.php:51` — `'generated_at' => 'timestamp'` → JSON value `1778266972` (Unix integer, **not a string**).
  - `app/Models/ServiceIncident.php:45` — `'reported_at' => 'timestamp'` → JSON value `1771914600`.
  - `app/Models/DayStatus.php:41` — `'executed_at' => 'timestamp'` → JSON value `1771956000`.
- Expected: ISO 8601 string with offset. The `timestamp` cast in Laravel returns the Unix epoch seconds, which most frontend formatters (including the project's `parseDueDate` and `formatTimestampInViewerTz`) expect as ISO strings.
- Suspected impact: any pill or label displaying these values in the UI is likely showing "1778266972" or `Invalid Date` somewhere. Worth a focused UI smoke check.
- Fix path: change cast to `'datetime'` or `'immutable_datetime:Y-m-d H:i:sP'`.

### F-010 — `DayStatus.date` ($appends includes `'date'`) double-serializes

- Severity: low
- Module: DayStatus
- Observed: `DayStatus.toArray()` produces `date = "2026-02-24T00:00:00.000000Z"` despite an accessor at `app/Models/DayStatus.php:73` returning `$this->date?->toDateString()`. The `$appends = ['date']` adds the accessor, but the underlying attribute's cast (`'date' => 'date'` at line 38) is what wins on `toArray()` keys with the same name.
- Expected: `Y-m-d` (matching the index/calendar UI expectations).
- Fix path: rename the underlying column to e.g. `service_day` and keep `date` as the accessor; or drop the cast and let the accessor decide; or apply a global Y-m-d serializer (see fix plan).

### F-009 — Bug 1 ("driver no ve servicio") did NOT reproduce in baseline

- Severity: needs reclassification
- Module: services + driver dashboard
- Steps tried: logged in as admin in Bogotá TZ; created service id=56 with `service_date=2026-05-08 14:00 America/Bogota` assigned to driver_id=1 (Carlos Martinez, linked to `driver@sgte.app`). Logged in as driver. Service appeared correctly. Repeated with browser TZ overridden to UTC. Service still appeared.
- Observed: backend logic is correct.
- Hypothesis: original report likely conflates one of these:
  - (a) Operator created a service for a non-today date (tomorrow / next week) — driver dashboard only shows today, by design (`DriverDashboardController.php:47` uses `whereDate('service_date_local', $today_op)`).
  - (b) Operator picked a date intending "today" but the form's date input interpreted it as one calendar day off due to browser TZ — possible if the operator used `data:` prefill from a query param computed via UTC.
  - (c) Driver session was stale and not refreshed.
- Action for fix planning: extend the driver dashboard to either (1) show a multi-day list (today + N days), or (2) add a calendar/picker. Independent of the TZ work.

## Smoke pass per module

Probed via DB inspection (`tinker`) + JSON shape capture from Inertia endpoints + targeted UI visits. Visual UI smoke for the modules below is queued for a follow-up session if needed; the structural defects already surface from the JSON shape check.

| Module | Storage | JSON shape | Notes |
|---|---|---|---|
| Services | OK (timestampTz + per-row tz) | service_date_local emits ISO+Z; planned_start_at emits `Y-m-d H:i:sP`; service_date accessor emits `Y-m-d` ✓ | F-002, F-004, F-009 in the form. |
| Contracts | dates only (`date` cast) | start_date/end_date emit ISO+Z | F-001, F-002, F-003, F-007. |
| Drivers | license_due_date `date` | ISO+Z | F-001, F-003 in the pill. F-007 (no per-row TZ). |
| Vehicles | soat/rtm/operation_card `date` | ISO+Z | F-001, F-003 in pills. F-007. |
| Invoices | issue_date `date` | ISO+Z | F-001. F-007. |
| FUEC | generated_at `timestampTz` col, `timestamp` cast | Unix integer (1778266972) | F-008 confirmed. |
| Service Incidents | reported_at column likely `timestampTz`, `timestamp` cast | Unix integer | F-008 confirmed. |
| Day Statuses | date `date` cast (also `executed_at` `timestamp` cast) | date → ISO+Z, executed_at → Unix integer | F-001, F-008, F-010. |
| Day Summary | URL takes `Y-m-d`; page formats via `formatDateEs` parsing `Y-m-d`+`T12:00:00` | n/a | uses `Y-m-d` pivot — likely OK as long as it lands a `Y-m-d` value. Will degrade if pivot becomes ISO+Z. |
| Gantt / Planificador | uses `instantToHoursInTz` (TZ-aware) ✓ | n/a | probably OK; deserves a UI smoke. |
| Calendar (`/day-statuses/2026`) | DayStatus rows | F-001 on `date` | header & cells render via `parseDueDate` which tolerates ISO+Z; visual smoke recommended. |
| Audit log | activity_log_table uses `timestampsTz` ✓ | created_at ISO+Z | OK; ensure `formatTimestampInViewerTz` is wired in the renderer. |

## Summary table

| ID | Severity | Module | Theme |
|---|---|---|---|
| F-001 | high | backend serialization | `date` cast emits ISO+Z |
| F-002 | high | services form | string-lex contract filter |
| F-003 | medium | documents/contracts pills | UTC-vs-local mismatch in `localTodayMs` |
| F-004 | medium | services form | retroactive gate uses UTC |
| F-005 | medium | driver dashboard | header/list disagree on "today" |
| F-006 | medium | architecture | viewer TZ not transported |
| F-007 | high | data model | business dates lack `timezone` column |
| F-008 | medium | backend casts | `timestamp` cast emits Unix integer (Fuec, ServiceIncident, DayStatus) |
| F-009 | n/a | services + driver | original Bug 1 did not reproduce; reclassify as UX |
| F-010 | low | DayStatus | `date` $appends accessor shadowed by `date` cast |

## Suspect files for the fix plan

Backend:
- `app/Models/Contract.php:43-55` — add `timezone` column + `Y-m-d` serializer for date casts.
- `app/Models/Driver.php:51-66` — same; `license_due_date` carries TZ from driver's domicile.
- `app/Models/Vehicle.php` — same for SOAT/RTM/Tarjeta de Operación due dates.
- `app/Models/Invoice.php:39-48` — same; `issue_date` carries TZ.
- `app/Models/Fuec.php:44-54` — fix `timestamp` → `datetime` cast.
- `app/Models/ServiceIncident.php:37-49` — fix `timestamp` → `datetime` cast.
- `app/Http/Middleware/HandleInertiaRequests.php:36-57` — share viewer TZ.
- New: a serializer/macro that emits `Y-m-d` for `date` casts (Carbon::serializeDateAs / model attribute) — applied globally.

Frontend:
- `resources/js/components/services/service-form.tsx:245-252,312` — normalize date strings before compare; replace UTC `today` with operation_tz `today`.
- `resources/js/lib/document-status.ts:68-128` — anchor `today` in operation_tz (or the record's TZ when present).
- `resources/js/pages/driver/index.tsx:84-89` — render header in operation_tz; surface a hint when viewer TZ differs.
- `resources/js/components/contracts/contract-period-pill.tsx` — consume the F-003 fix.
- `resources/js/lib/datetime.ts` — already correct; lean on it for new code paths.

Data model & migrations:
- New migration: add `timezone` column to `contracts`, `drivers`, `vehicles`, `invoices`, `fuecs`, `service_incidents`, `data_imports`. Default to `config('app.operation_tz')` for existing rows.
- New migration: rename / convert `*_date` columns where they should be timestamps with TZ instead of pure dates (per user's instruction). Confirm with owner before changing column types.

Architecture decisions for the fix plan:
- D1 — Date model: keep "calendar-day with per-row timezone" for contract/license/document due dates? Or store as instants (`endOfDay` UTC + TZ)? User said "todos los demas campos date o datetime que no sean los de timestamps de laravel sino propios de la logica de negocio tambien se guarden en UTC junto con una columna timezone". → instants + TZ. Means migration of pure-date columns to timestamps.
- D2 — Source of viewer TZ: client-detected via `Intl.DateTimeFormat().resolvedOptions().timeZone`, sent to backend on first request and stored on `users.timezone` (or just session-level)? Or a `<meta>` injected by frontend and forwarded as a header on every Inertia request?
- D3 — Migration of existing data: backfill `timezone = config('app.operation_tz')` and reinterpret existing date values in that TZ. Tests in `tests/Feature` and Dusk regression must cover the cross-TZ matrix.

