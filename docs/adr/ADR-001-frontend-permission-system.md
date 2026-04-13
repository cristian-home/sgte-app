# ADR-001: Frontend permission system

**Status:** Accepted
**Date:** 2026-02-26

## Context

The application needs to show or hide UI elements (navigation items, buttons, sections) based on the authenticated user's permissions. Permissions are managed in the backend with `spatie/laravel-permission` and are defined as PHP enums in `App\Enums\Permission` and `App\Enums\Role`.

The React frontend (via Inertia.js) needs:
1. Access to the permission enums with type safety.
2. Knowledge of the current user's permissions and roles.
3. A utility to evaluate permissions, honoring the super_admin bypass.

## Decision

### 1. TypeScript enum generation from PHP

The `php artisan enum:typescript` command was created. It scans the string-backed enums in `app/Enums/` and generates `.ts` files in `resources/js/enums/` containing:
- `const` object (to use as constants)
- `type` union (for type safety)
- Label map (if the enum has a `label()` method)

Generated files are versioned in git (not in `.gitignore`) because the command is manual and a developer cloning the repo needs the build to succeed without extra steps. They are regenerated with `php artisan enum:typescript` whenever the PHP enums change.

### 2. Sharing permissions via Inertia

The `HandleInertiaRequests` middleware shares `auth.permissions` (array of strings) and `auth.roles` (array of strings) on every response. This avoids extra requests and works in both SSR and the browser.

For super_admin, the permissions array arrives empty (it has no permissions directly assigned); the bypass is handled on the frontend.

### 3. Frontend utilities

- **`usePermissions()` hook** — Exposes `can(permission)`, `hasRole(role)`, `isSuperAdmin`. If the user has the `super_admin` role, `can()` always returns `true` (mirroring `Gate::before()` on the backend).
- **`<Can permission={...}>` component** — Declarative conditional rendering with optional `fallback` prop.
- **`NavItem.permission`** — Optional field on the `NavItem` type; navigation components automatically filter out items the user lacks permission for.

### 4. Two layers of security

The frontend layer is UX only (hiding what doesn't apply). Real authorization stays in the backend. See **ADR-005 (Authorization layering)** for the full picture: a `Gate::before` hook for the Super Admin bypass, `can:` middleware on routes, and fine-grained `FormRequest::authorize()` gates. There are no Eloquent Policies.

## Consequences

**Positive:**
- End-to-end type safety: the same permission values in PHP and TypeScript.
- Adding a permission to a nav item is declarative (just one property).
- The super_admin bypass is faithfully replicated on the frontend.

**Negative:**
- Requires running `php artisan enum:typescript` manually when PHP enums change.
- Permissions ship on every Inertia response (minimal weight, ~1–2 KB for the current permission set — 49 cases as of 2026-04).

**Key files:**
- `app/Console/Commands/GenerateTypescriptEnums.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/hooks/use-permissions.ts`
- `resources/js/components/can.tsx`
- `resources/js/enums/` (generated)
