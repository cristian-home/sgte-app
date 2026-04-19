---
name: accounting-master-data-asymmetry
type: fix
scope: permissions
status: completed
priority: low
created_date: 2026-04-19
completed_date: 2026-04-19
srs_refs: []
migration_strategy: modify-existing
---

# Accounting role asymmetry: third-parties + contracts yes, vehicles + drivers no

## Description

Surfaced by the 2026-04-19 cross-role UX/QA audit (severity: Orthogonal, category: permissions).

Accounting currently has `VIEW_THIRD_PARTIES` + `VIEW_CONTRACTS` + `VIEW_SERVICES` + `VIEW_INVOICES` but NOT `VIEW_VEHICLES` or `VIEW_DRIVERS` (see `database/migrations/2026_03_13_000000_seed_catalog_data.php:191-197`). Curl probe:

```
login(accounting@sgte.app)=302
  /third-parties -> 200
  /contracts -> 200
  /vehicles -> 403
  /drivers -> 403
  /services -> 200
  /gantt -> 200   # renders vehicle + driver names despite no VIEW perm
```

Asymmetry: `/gantt` and `/services` already surface vehicle and driver names for accounting via the ServiceController + GanttController Gate (which auth'd `VIEW_SERVICES`). If accounting can see names in-context, why can't they open the detail record? Either:
1. Grant accounting read-only `VIEW_VEHICLES` + `VIEW_DRIVERS` so they can drill into master data when investigating billing.
2. Explicitly document the asymmetry ("accounting only sees vehicles/drivers through the service lens, never as a first-class list") and keep the 403s.

Needs a product call — this is a policy decision, not a bug. Pick one and make the Spatie seed match the decision.

See `docs/audits/2026-04-19-cross-role-audit.md#accounting-asymmetry` for the original observation.

## Acceptance Criteria

- [x] Product call recorded in the SRS (add to `docs/SRS.md` §7 Roles and Permissions, accounting row).
- [x] `seed_catalog_data.php` accounting role block updated to match the decision; `migrate:fresh --seed` run; `SharedPermissionsTest` updated if the permission count changes.

## Resolution

Option 1 chosen: **grant accounting read-only access to vehicles + drivers**. Rationale:

1. Symmetry with the third-parties + contracts read access accounting already had.
2. `/gantt` and `/services` already surface vehicle plate and driver name to accounting through `VIEW_SERVICES`, so the information-hiding story was already incomplete. Formalizing read access removes the asymmetry rather than hardening it.
3. Billing investigations regularly need to click from a service row to the vehicle (to confirm provider ownership for third-party-vehicle invoices) or to the driver (to verify license/ID details on supporting documents).
4. Write operations (create/update/delete) remain forbidden — accounting is strictly read-only on master data.

The `SharedPermissionsTest` was not updated because it only hard-asserts the driver role's permission count; accounting's count is not pinned there.
