---
name: vehicle-locations-json-parse-error-investigation
type: fix
scope: gps
status: completed
priority: low
created_date: 2026-04-19
completed_date: 2026-04-19
srs_refs: [REQ-010]
migration_strategy: new
---

# Investigate stale JSON.parse errors on /vehicle-locations

## Description

Surfaced by the 2026-04-19 cross-role UX/QA audit (severity: Orthogonal, category: stale observation).

Initial `browser-logs` probe at audit start showed several consecutive unhandled promise rejections on `/vehicle-locations`:

```
Unhandled Promise Rejection SyntaxError JSON.parse: unexpected character
at line 1 column 1 of the JSON data null
```

The audit could not reproduce the error on a fresh visit + filter click during the sweep — so these may be stale from a prior interaction that's no longer in the codebase (the vehicle-locations pages were rebuilt in the gps-tracking requirement). Worth a dedicated investigation pass to either:
1. Reproduce and fix, OR
2. Confirm definitively that the old-build interaction is impossible in current code and document the conclusion.

See `docs/audits/2026-04-19-cross-role-audit.md#stale-json-parse-errors` for the original observation.

## Acceptance Criteria

- [x] Fresh `migrate:fresh --seed` DB; visit `/vehicle-locations` as admin + operator; exercise every filter (vehicle combobox, desde/hasta date pickers), pagination, and column sort. Assert no unhandled rejections in `mcp__laravel-boost__browser-logs`.
- [x] If reproducible, patch + add a Dusk scenario that would have caught it. If not reproducible, close as "not reproducible — old build artifact".

## Resolution

**Reproduced — not stale.** Fresh `migrate:fresh --seed`, admin login, single click on the Vehículo combobox, and Playwright's console captured:

```
[52958ms] Unexpected token '<', "<!DOCTYPE "... is not valid JSON
```

The server was returning HTML (the full Inertia page) when `useServerTable` refetched with `Accept: application/json`, and `response.json()` in the hook exploded — exactly the audit's symptom.

### Root cause

The `useServerTable` hook fetches filter/sort/pagination updates with `{ headers: { Accept: 'application/json' } }` and no `X-Inertia: true` header. Laravel's Inertia middleware only emits its JSON payload when the `X-Inertia` header is set; otherwise it returns the full HTML page. `VehicleLocationController::index` had no `wantsJson()` branch, so the hook got HTML and blew up.

`ServiceController::index` already had the correct branch (`if ($request->wantsJson()) return response()->json($services)`), which is why the bug never surfaced on `/services`. **Every other controller backing a `useServerTable` page had the same gap** — the audit only caught it on `/vehicle-locations` because that's what the tester filtered. Systemic fix applied.

### Fix

Added `if ($request->wantsJson()) { return response()->json($paginator); }` before the `Inertia::render(...)` call in every `useServerTable`-consuming controller:

- `VehicleLocationController` (the audit target)
- `VehicleController`, `ThirdPartyController`, `DriverController`, `ContractController`, `InvoiceController`, `ServiceIncidentController`, `FuecController`, `AuditLogController` (same latent bug)

Each index return type updated to `Response|JsonResponse`. No behavior change for full-page navigation (Inertia path unchanged), but filter/sort/pagination refetches now receive the raw paginator JSON shape that the hook expects.

### Regression coverage

- **Pest** (`tests/Feature/Http/Controllers/VehicleLocationControllerJsonTest.php`): 3 tests — JSON refetch returns paginator shape with `data`, `current_page`, `last_page`, `total` keys; filters apply; plain GET still returns HTML (Inertia path intact).
- **Dusk** (`tests/Browser/VehicleLocationsJsonParseTest.php`): navigates to `/vehicle-locations`, runs a browser-side `fetch('/vehicle-locations?filter[vehicle_id]=1', {headers: {Accept: 'application/json'}})`, parses the response, asserts `{ data: [], current_page: ..., ... }`. Would fail with the HTML fallback the audit caught.
- Playwright MCP walkthrough: visited as admin, applied vehicle filter (8→3 rows), applied desde+hasta date range (3→1 row), sort by Fecha/Hora, cleared filters — no JSON.parse errors in the browser console at any step.
