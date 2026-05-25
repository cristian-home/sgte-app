---
name: route-level-can-middleware-sweep
type: refactor
scope: authorization
status: completed
priority: low
created_date: 2026-04-19
completed_date: 2026-04-19
srs_refs: []
migration_strategy: new
---

# Sweep routes/web.php to add can: middleware where the controller handles it today

## Description

Surfaced by the 2026-04-19 cross-role UX/QA audit (severity: Orthogonal, category: authorization consistency).

Newer modules (`/users`, `/audit-log`, `/fuecs`, `/fuec-number-ranges`, `/gps/map`, invoice sub-routes) use route-level `can:permission` middleware so the 403 is raised before the controller body. Older resource routes (`/services`, `/service-incidents`, `/incident-types`, `/vehicles`, `/drivers`, `/third-parties`, `/contracts`, `/day-statuses`, `/day-summary`, `/gantt`, `/invoices` index + CRUD, `/vehicle-locations`) rely on each controller method calling `Gate::authorize()` inside.

Both paths work today (the audit confirmed no 403 gap), but the mixed style makes the authorization map harder to audit. Align on one approach — either add `can:view-*` middleware declarations to every resource route in `routes/web.php`, or remove them everywhere and rely exclusively on `FormRequest::authorize()` + controller `Gate::authorize()` as documented in ADR-005.

Preferred: add route-level `can:` gates on `index` and `show` actions (where a controller-body check still happens on 403-throw, but the route pre-gate is cheaper and self-documenting); keep `FormRequest::authorize()` for mutations.

See `docs/audits/2026-04-19-cross-role-audit.md#orthogonal-auth-consistency` for the original observation.

## Acceptance Criteria

- [x] Authorization strategy reaffirmed in ADR-005 (decide per-action or per-resource).
- [x] `routes/web.php` consistently applies (or consistently omits) `can:` middleware on all resource routes per the chosen strategy.
- [x] Existing Pest authorization tests still pass unchanged.

## Resolution

**Per-resource** `can:{baseline}.view` middleware applied on every Route::resource in `routes/web.php` plus the non-resource view endpoints (Gantt, Day Summary, Day Statuses calendar, Audit log, mark-paid). The four static catalogs use `can:catalogs.manage` (matching the controller Gate). `/invoices` sub-routes that already had specific gates keep them unchanged.

**One exception**: `/service-incidents` cannot carry a resource-wide `can:incidents.view` because drivers hold `CREATE_INCIDENTS` without `VIEW_INCIDENTS` (they file incidents from the driver portal without a global incidents view). That resource keeps controller-body `Gate::authorize()` per action; documented as the single opt-out in ADR-005 §Layer 2.

All 697 Pest tests still pass unchanged. Dusk (101) still green.
