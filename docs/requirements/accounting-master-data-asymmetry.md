---
name: accounting-master-data-asymmetry
type: fix
scope: permissions
status: pending
priority: low
created_date: 2026-04-19
completed_date:
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

- [ ] Product call recorded in the SRS (add to `docs/SRS.md` §7 Roles and Permissions, accounting row).
- [ ] `seed_catalog_data.php` accounting role block updated to match the decision; `migrate:fresh --seed` run; `SharedPermissionsTest` updated if the permission count changes.
