---
name: driver-interface
type: feat
scope: drivers
status: completed
priority: high
created_date: 2026-03-22
completed_date: 2026-03-22
srs_refs: ["REQ-003", "REQ-012"]
migration_strategy: new
---

# Driver Interface

## Description

Mobile-first interface for drivers. Shows the services assigned to the driver for the current day with buttons to confirm start and end of service. Requires linking the Driver model to User via `user_id` in the drivers table.

## Acceptance Criteria

- [x] WHEN a driver navigates to /driver THEN they see their services for the current day
- [x] WHEN a driver without a linked driver record navigates to /driver THEN they see a message instructing them to contact the administrator
- [x] WHEN a driver clicks "Confirmar Inicio" THEN the hora_inicio_real is recorded
- [x] WHEN a driver clicks "Confirmar Fin" THEN the hora_fin_real is recorded
- [x] WHEN a driver attempts to confirm a service belonging to another driver THEN they receive 403
- [x] WHEN a user without the services.register-times permission navigates to /driver THEN they receive 403
- [x] WHEN the driver has services THEN they are listed ordered by planned time with status indicators

## Implementation Summary

- Migration: `add_user_id_to_drivers_table` — nullable FK `user_id` on drivers
- Model: Driver.user() BelongsTo, User.driver() HasOne
- Controller: `DriverDashboardController` with index, confirmStart, confirmEnd
- Routes: GET /driver, POST /driver/services/{service}/confirm-start, POST /driver/services/{service}/confirm-end
- Frontend: `resources/js/pages/driver/index.tsx` — card-based mobile-first layout
- Sidebar: "Conductor > Mis Servicios" group with REGISTER_SERVICE_TIMES permission
- Tests: 7 tests covering all CRUD and authorization scenarios
