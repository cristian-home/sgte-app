# ADR-007: Datetime and timezone model

**Status:** Accepted
**Date:** 2026-05-08

## Context

SGTE is a Colombian fleet-management app whose data is read by operators (mostly in `America/Bogota`), drivers in the field (occasionally on devices set to other TZs), and — eventually — accounting users in offices that may be in different cities. Through April 2026 the app stored business dates and times in a mix of `DATE`, `TIME` and `timestamp` columns with no per-row timezone metadata, and the frontend computed "today" as `new Date().toISOString().slice(0, 10)` (browser-UTC). This produced a class of bugs visible at the day boundary: contracts looked expired hours before midnight, drivers in non-Bogotá TZs saw header dates that contradicted the service list, the service form's contract dropdown silently filtered out a contract starting on the same day as the service, and so on.

The audit at `docs/audits/2026-05-08-datetime-timezone-discovery.md` documents the 10 findings (F-001..F-010) that drove the rollout. The user explicitly asked for a single rule: **business datetime fields stored in UTC + a per-row timezone column, rendered in the viewer's TZ where appropriate.**

## Decision

### 1. UTC instants + per-row IANA `timezone` column

Every business datetime field is a `TIMESTAMPTZ` column named `*_at`, alongside a sibling `timezone` (`VARCHAR(64)`, NOT NULL, default `config('app.operation_tz')`) on the same row. There are no business `DATE` or `TIME` columns left except `services.service_date_local`, which is a denormalized day-bucket projected from `planned_start_at` in the row's TZ for BTree-indexed Gantt / Day Summary / Calendar queries.

Models with their own TZ today: `Service`, `Contract`, `Driver`, `Vehicle`, `Invoice`, `Fuec`, `ServiceIncident`, `DayStatus`, `DataImport`. Each uses `App\Concerns\HasTimezone`.

### 2. Half-open intervals for calendar-day periods

`start_at` is the first valid instant (00:00 of the first day in `timezone`); `end_at` is the **exclusive** end (00:00 of the day after the last valid day). "Active right now" is `start_at <= now() AND end_at > now()` — no microsecond games. Single-date deadlines (`license_due_at`, `*_due_at`, `issued_at`) follow the same convention: the document is valid until `due_at` exclusive, i.e. ends at the start of the day after the conventional last valid day in `timezone`.

### 3. Naming convention

Storage columns: `*_at` (instants). Wall-clock accessors: `*_date` for calendar projections (`Y-m-d`), `*_local` for time-of-day (`H:i`). Setters accept either format and project to the canonical `*_at`. Older `*_date` raw columns were dropped during the rollout.

### 4. Casts

Every `*_at` column casts as `'immutable_datetime:Y-m-d H:i:sP'`. The explicit `P` suffix preserves the offset on round-trip in both PostgreSQL and SQLite (used by tests). `service_date_local` and `day_statuses.date` cast as `'immutable_date:Y-m-d'` so JSON output is a clean `Y-m-d`.

### 5. Backend helpers

- `App\Support\Tz` — single source of truth for resolving "which TZ should I use?": `Tz::operation()`, `Tz::viewer($request)`, `Tz::for($modelOrTz)`, `Tz::nowIn($tz)`, `Tz::startOfDayInTzAsUtc($ymd, $tz)`, `Tz::endOfDayInTzAsUtc($ymd, $tz)`.
- `App\Concerns\HasTimezone` — trait every datetime-bearing model uses. Exposes `resolveTimezone(): string` with fallback to `Tz::operation()`.
- Day-bucket queries use `whereDate('service_date_local', $today)` against the denormalized column when matching against an operational day, never an instant column.

### 6. Viewer TZ capture and transport

`App\Http\Middleware\CaptureViewerTimezone` (registered in `bootstrap/app.php` before `HandleInertiaRequests`) reads the `X-Viewer-Timezone` request header and the `viewer_tz` cookie (header wins), validates against `timezone_identifiers_list()`, attaches the value to `request()->attributes`, and best-effort persists to `users.timezone` for authenticated users. The cookie lives in the non-encrypted list (alongside `appearance` and `sidebar_state`).

`HandleInertiaRequests` shares both `config.operation_tz` and `config.viewer_tz` to every page. The `useViewerTimezone()` hook in `resources/js/hooks/use-viewer-timezone.tsx` (mounted once in `app-sidebar-layout.tsx`) detects `Intl.DateTimeFormat().resolvedOptions().timeZone` on every authenticated visit, writes the cookie when it differs, and partial-reloads `config` when the SSR view diverges from the detected TZ.

### 7. Frontend rendering

Two formatter axes:

- **Event-anchored**: `formatEventDate(at, eventTz)`, `formatEventTime(at, eventTz)`, `formatEventDateTime(at, eventTz)`, `formatTimeRange(startAt, endAt, eventTz)`. Used for service times, contract periods, etc. — the audience cares about "what time was/will it be on the ground", not the viewer's wall clock.
- **Viewer-anchored**: `formatTimestampInViewerTz(at)`. Used for audit timestamps (`created_at`, `updated_at`, audit log rows, notifications) — the viewer wants their own clock.

Both helpers are native `Intl.DateTimeFormat` (no `date-fns-tz` or other npm dependency). Locale is `es-CO` by default; helpers accept a locale override.

For "today as a `Y-m-d` string" use `viewerToday(tz)` from `lib/datetime.ts`. **Do not** use `new Date().toISOString().slice(0, 10)` — that's browser-UTC and was the root of F-003 / F-004 / F-005.

### 8. Migrations

Pre-release the project consolidates alter migrations back into the `create_*` migration that owns the table (rationale: the app has no live data yet, so accumulated schema drift is noise). Vendor-published stubs (Fortify two-factor, Spatie ActivityLog `add_event` / `add_batch_uuid`, Spatie Permission, Spatie MediaLibrary) are left untouched to remain idempotent against `vendor:publish`. Once a real production deploy lands, this consolidation policy ends and additive alter migrations become the norm.

## Consequences

- A new model with a datetime field MUST follow the pattern. The reference implementation is `App\Models\Service` (with the saving hook that recomputes `service_date_local`).
- Tests that previously created models with `Carbon::today()->subDays(N)` etc. continue to work via the wall-clock setters; tests that wrote the raw `*_date` column directly were updated.
- `users.timezone` is best-effort: NULL until the first authenticated request with a header. Code that needs a TZ for a user without a header should fall back to `Tz::operation()`.
- The frontend timezone formatters skip pulling in `date-fns-tz` (≈40 KB minified), trading a small amount of code for one less dependency to maintain.

## References

- Audit: `docs/audits/2026-05-08-datetime-timezone-discovery.md`
- Reference model: `app/Models/Service.php`
- Backend helpers: `app/Support/Tz.php`, `app/Concerns/HasTimezone.php`
- Middleware: `app/Http/Middleware/CaptureViewerTimezone.php`
- Frontend: `resources/js/lib/datetime.ts`, `resources/js/hooks/use-viewer-timezone.tsx`
- Original spec: `docs/requirements/datetime-timezone-handling.md`
