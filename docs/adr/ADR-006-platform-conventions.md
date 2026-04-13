# ADR-006: Platform conventions

**Status:** Accepted
**Date:** 2026-04-13

## Context

A handful of cross-cutting decisions live in `AppServiceProvider::boot()` and in trait-level defaults on the domain models. Individually each is small; collectively they shape how every controller, model, and test behaves. Without a written record, new contributors encounter surprises ("why is my date `CarbonImmutable`?", "why can't I drop the database locally?", "where does the audit log come from?") and accidentally work around conventions instead of leaning on them.

This ADR captures those decisions in one place.

## Decision

### 1. Activity logging — `spatie/laravel-activitylog` on every domain model

Every domain model (`Service`, `Vehicle`, `Driver`, `ThirdParty`, `Contract`, `Invoice`, `DayStatus`, `ServiceIncident`, `Fuec`, `VehicleLocation`, and the catalog models) uses `Spatie\Activitylog\Traits\LogsActivity` with a per-model `getActivitylogOptions()` that lists the `logOnly` columns.

The `activity_log` table is the canonical audit store for **REQ-009 (Accounting Immutability Control)**. `ServiceController::update` also writes a custom log entry capturing the `justification` field when services are edited on an executed day.

Admins view the log through `/audit-log` (see `AuditLogController`, ADR-005 for authorization). There is no need to install a second auditing package (`owen-it/laravel-auditing` was considered and rejected — it would duplicate effort with no benefit).

### 2. Media library — `spatie/laravel-medialibrary`

`spatie/laravel-medialibrary` (v11) is installed with a `create_media_table` migration already in place. The intended disk is MinIO (S3-compatible), exposed as `MEDIA_DISK=media` in the environment.

**Current adoption**: the Media table exists but no domain model attaches files yet. When a feature needs attachments (vehicle photos, contract PDFs, incident evidence), the pattern is:

1. `implements HasMedia` + `use InteractsWithMedia` on the model.
2. Register collections in `registerMediaCollections()`.
3. Use the `media` disk, not `local` / `public` / `s3` directly.

### 3. Queue topology — Horizon + Redis, Reverb for broadcasting

- **Queue driver**: Redis.
- **Monitor**: Horizon dashboard at `/horizon` (bundled, not custom).
- **Queued work** (as of 2026-04): Scout indexing jobs (`ScoutReindexJob`), broadcast notifications (`BillingIncidentNotification`), and any future job classes.
- **Broadcasting**: `laravel/reverb` (self-hosted WebSockets) + `laravel-echo` on the frontend.

Channels convention is TBD — the first Reverb channel will establish the naming. See the open thread in `phases/phase-5-optionals-deploy.md`.

### 4. `CarbonImmutable` as the default date class

Set in `AppServiceProvider::boot()`:

```php
Date::use(CarbonImmutable::class);
```

This prevents the classic "I called `$date->addDay()` expecting a copy and mutated the original" bug. Model `date` / `datetime` casts, factory `fake()->dateTimeBetween()`, and any direct `now()` / `today()` call return immutable instances.

**Consequence for contributors**: never rely on chaining mutation. Write `$copy = $date->addDay();` not `$date->addDay();`.

### 5. `DB::prohibitDestructiveCommands()` in non-local environments

`AppServiceProvider::boot()` calls `DB::prohibitDestructiveCommands($this->app->environment('staging', 'production'));`

This prevents `db:wipe`, `migrate:fresh`, and `migrate:rollback` from running in staging/production. Local and testing environments are unaffected.

**Consequence for contributors**: a staging deploy cannot reset the DB through artisan. If you need to reset a staging DB you do it at the Postgres level, not through Laravel.

### 6. `Password::defaults()` complexity rules

`AppServiceProvider::boot()` registers:

```php
Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised());
```

All password validation rules that call `Password::defaults()` (e.g., `UserStoreRequest`, Fortify registration) automatically apply min 8 characters, mixed case, numbers, symbols, and a check against the "Have I Been Pwned" compromised-password list.

**Consequence for contributors**: don't re-declare password rules per FormRequest. Call `Password::defaults()` and you get the project-wide policy.

### 7. `Request::macro('perPage')` for paginated endpoints

`AppServiceProvider::boot()` defines a `Request` macro:

```php
Request::macro('perPage', fn (int $default = 15, int $max = 100)
    => min((int) $this->input('per_page', $default), $max));
```

Controllers that paginate should call `$request->perPage()` rather than reading `per_page` directly. This enforces a consistent upper bound and prevents clients from requesting 10,000-row pages.

**Current usage**: the index controllers that use Spatie QueryBuilder read the page size through this macro.

### 8. Wayfinder-generated routes are not hand-edited

`resources/js/actions/` (controller actions) and `resources/js/routes/` (named routes) are regenerated by the `laravel/wayfinder` Vite plugin on every build. Do not edit them by hand — changes will be overwritten.

## Consequences

**Positive:**

- Contributors have one place to point a new developer at.
- Cross-cutting behaviors are consistent (immutable dates, policy-level password rules, safe staging/production protections).
- The audit strategy is explicitly single-stack (no duplication via two auditing packages).

**Negative:**

- This ADR will need updating when any of the conventions change (e.g., when the first media collection is added, or when a queue/channel convention is nailed down).

**Key files:**

- `app/Providers/AppServiceProvider.php` — where almost all of this is wired.
- `app/Models/*` — `LogsActivity` trait adoption.
- `config/activitylog.php`, `config/media-library.php`, `config/horizon.php`, `config/reverb.php` — per-package config.
- `compose.yaml`, `docker/production/Dockerfile`, `start-container` — runtime supervisor (Horizon + Reverb workers).
- `database/migrations/2026_02_22_232634_create_activity_log_table.php`, `2026_02_23_000254_create_media_table.php` — supporting tables.

## When to revisit

- When the first media collection is added (update §2).
- When a Reverb channel convention is nailed down (update §3).
- If the `Password::defaults()` policy needs to relax (regulatory / usability pressure).
