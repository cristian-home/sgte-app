---
name: document-expiry-service-date-recheck
type: fix
scope: services
status: pending
priority: medium
created_date: 2026-04-19
completed_date:
srs_refs: [REQ-004, REQ-005]
migration_strategy: new
---

# Re-check vehicle / driver document expiry at service-date, not just creation

## Description

Surfaced by post-audit workflow review of the 2026-04-19 cross-role UX/QA audit.

`ServiceStoreRequest` gates creation through `ServiceDocumentChecks::vehicleDocumentsValid` + `driverLicenseValid`, evaluated against `service_date` at the moment the form is submitted. There is no re-validation between creation and execution. A service scheduled 5 days out passes at creation; if the driver's licence expires 3 days later, the service is still green on the Gantt and the driver can confirmStart on a vehicle with a technically-expired document.

Proposed fix, two layers:

1. **Render-time (Gantt)**: `GanttController::index` already fetches vehicles + services per date. Apply the same document-check helpers row-by-row and emit `service.blocked = true` when either the vehicle or driver has expired paperwork as-of `service_date`. Frontend (`resources/js/pages/gantt/index.tsx`) greys those rows with a tooltip stating which document expired. The `blocked` plumbing is already wired from a previous REQ-004 pass — just needs the calculation and the CSS state.
2. **Execution-time (driver portal)**: `DriverDashboardController::confirmStart` re-runs the gauntlet. If any doc expired between assignment and the tap, return a 422 with `"Tu licencia venció el YYYY-MM-DD. Contacta a Operaciones."` (or the equivalent for vehicle docs). Driver cannot start.

Optional (phase 2): a nightly Horizon job that sends ops a 30/7/1-day heads-up email for vehicles and drivers with paperwork expiring within the window — proactive notice instead of day-of surprise.

## Acceptance Criteria

- [ ] Gantt rows render with a disabled/grey state + tooltip when the assigned vehicle or driver has a document expired as-of the service's date; Pest coverage for the controller payload, Dusk assertion for the visual state.
- [ ] `DriverDashboardController::confirmStart` re-checks documents against `service.service_date` (NOT today) and 422s with a Spanish message naming the expired document; Pest covers each expiry branch (SOAT / RTM / T.O. / licencia / categoría incompatible / seguridad social) individually.
