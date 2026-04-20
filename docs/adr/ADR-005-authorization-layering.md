# ADR-005: Authorization layering (no Eloquent Policies)

**Status:** Accepted
**Date:** 2026-04-13
**Supersedes:** partially supersedes the "policies in controllers" claim in ADR-001 §4.

## Context

SGTE uses `spatie/laravel-permission` with a role+permission model (5 roles, ~49 permissions). Authorization checks happen in **three** places in the codebase, but the pattern was never written down. New contributors repeatedly look for an `app/Policies/` directory (it does not exist) or try to add `authorize()` calls inside controller methods (too late in the request lifecycle for the FormRequest path).

This ADR documents where authorization lives and why.

## Decision

Authorization is enforced at three layers, in this order:

### Layer 1 — Super Admin bypass (`Gate::before`)

`App\Providers\AppServiceProvider::boot()` registers a `Gate::before` hook:

```php
Gate::before(fn ($user) => $user->hasRole(Role::SUPER_ADMIN->value) ? true : null);
```

The Super Admin role is bootstrapped from env vars (`SUPER_ADMIN_USER` + `SUPER_ADMIN_PASSWORD`) in the `seed_catalog_data` migration and bypasses **every** gate. It intentionally holds **no** explicit permissions — the bypass is the feature. This keeps emergency access decoupled from the permission matrix.

### Layer 2 — Route-level `can:` middleware (coarse gate)

Every resource route declares the minimum permission it requires via the built-in `can:` middleware. Examples:

```php
Route::get('dashboard', [DashboardController::class, 'show'])
    ->middleware(['auth', 'verified', 'can:dashboard.view'])
    ->name('dashboard');

Route::resource('users', UserController::class)->middleware('can:users.view');
```

This layer returns `403 Forbidden` **before** any controller code runs. It's the coarsest gate: it protects the route, not a specific payload.

**Applied strategy (reaffirmed 2026-04-19, route-level-can-middleware-sweep):** every resource/index/show-style route in `routes/web.php` declares a `can:{baseline}.view` middleware (or the domain-specific equivalent — `can:catalogs.manage` for the static-catalog resources, `can:day-summary.view` for the calendar endpoints, `can:services.view` for the Gantt, etc.). The mutation actions inherit the same resource-wide middleware because in this role matrix every role that holds a CREATE/UPDATE/DELETE permission also holds the corresponding VIEW permission — resource-wide `can:view` therefore does not accidentally block legitimate mutation traffic, and the FormRequest `authorize()` layer below re-checks the action-specific permission on top.

**One exception (`/service-incidents`):** drivers hold `CREATE_INCIDENTS` but deliberately do **not** hold `VIEW_INCIDENTS` — they file incidents against their own assigned services from the driver portal without being granted a general-purpose view of every incident across the fleet. A resource-wide `can:incidents.view` middleware would 403 the legitimate driver create flow, and Laravel's declarative resource syntax does not support per-action middleware cleanly. `/service-incidents` therefore stays on controller-body `Gate::authorize()` per action + `FormRequest::authorize()` on store/update. This is the one route family in the codebase where layer 2 is absent by design.

### Layer 3 — Fine-grained `FormRequest::authorize()` (payload-aware gate)

Store/Update FormRequests check the relevant `CREATE_*` / `UPDATE_*` permission inside `authorize()`:

```php
public function authorize(): bool
{
    return Gate::allows(Permission::CREATE_USERS->value);
}
```

**Why `authorize()` and not a `Gate::authorize()` call in the controller method?**

Laravel runs `FormRequest::authorize()` **before** `rules()`. If authorization fails, the response is `403 Forbidden`. If instead the controller calls `Gate::authorize()` in its body, the request goes through validation first — which means an unauthorized caller who submits invalid data gets `422 Unprocessable Entity` back, leaking the shape of the validation rules. The attacker learns more than they should.

This is a real incident we closed in the `fix(authorization)` commit on 2026-02-24 ("close pre-validation bypass and missing gates"). Reopening it by moving gates back into controller bodies would regress the fix.

### Why no Eloquent Policies?

Laravel Policies add an indirection: the `Permission` enum is the single source of truth, and Policies would just wrap `Gate::allows()` calls with a different namespace and boilerplate. The `spatie/laravel-permission` package already provides the abstraction we need; a second one is redundant.

Policies also invite **per-record** authorization logic (e.g., "a user can only update their own service"). In SGTE, per-record rules are:

1. Cross-driver 403 for `/driver/services/{service}/confirm-*` — enforced inline in `DriverDashboardController::confirmStart` / `confirmEnd`.
2. Day-executed restriction on service update — enforced inline in `ServiceUpdateRequest::authorize()` + `after()`.
3. Delete-your-own-account block — enforced inline in `UserController::destroy`.

These are few enough that inline checks are clearer than a full Policy class, and they keep the rule next to the request handling.

## Consequences

**Positive:**

- Single source of truth: `App\Enums\Permission` + `App\Enums\Role`.
- 403 always comes before validation, so we don't leak the validation shape to unauthorized callers.
- Super Admin emergency access is one provider registration, not a sprinkling of `if` statements.
- `CatalogAuthorizationTest` + `DashboardTest` + `SharedPermissionsTest` + `UserControllerTest` + `AuditLogControllerTest` (and per-feature tests) keep the behavior pinned.

**Negative:**

- Newcomers will still look for `app/Policies/` (there is none). This ADR is the pointer.
- Mixing `can:` middleware on routes with `Gate::authorize()` in controller bodies is slightly inconsistent; both patterns are tolerated as long as each route has *some* route-level gate.
- Per-record rules live inline rather than in a policy class — if the number grows past ~10, a pivot to policies might be worth revisiting.

**Key files:**

- `app/Enums/Permission.php`, `app/Enums/Role.php` — canonical names.
- `app/Providers/AppServiceProvider.php` — `Gate::before` registration.
- `database/migrations/2026_03_13_000000_seed_catalog_data.php` — role→permission mapping.
- `routes/web.php`, `routes/settings.php` — route-level gates.
- `app/Http/Requests/*StoreRequest.php`, `*UpdateRequest.php` — FormRequest `authorize()` gates.
- `tests/Feature/Authorization/CatalogAuthorizationTest.php` — example coverage.

## Cross-references

- **ADR-001** (frontend permission system) — frontend UX layer; this ADR is its backend counterpart.
- **CLAUDE.md** — project memory note "Authorization in FormRequest::authorize()" explains the incident that motivated the 403-before-validation rule.
