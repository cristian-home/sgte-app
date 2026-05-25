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

Mobile-first interface for drivers. Shows the services assigned to the authenticated driver for the current day as cards, with inline actions to confirm start, confirm end, and register an incident. Requires linking the Driver model to User via `user_id` in the drivers table.

## Acceptance Criteria

- [x] WHEN a driver navigates to `/driver` THEN they see their services for the current day as a card list ordered by `planned_start_time`
- [x] WHEN a driver without a linked driver record navigates to `/driver` THEN they see a message instructing them to contact the administrator
- [x] WHEN a driver clicks "Confirmar Inicio" on a card THEN `actual_start_time` is recorded server-side (via `DriverDashboardController@confirmStart`)
- [x] WHEN a driver clicks "Confirmar Fin" on a card THEN `actual_end_time` is recorded
- [x] WHEN a driver clicks "Registrar Novedad" on a card THEN they are navigated to `GET /service-incidents/create?service_id=X`, which prefills the service, flags `is_driver_report=true` on submit, and redirects back to `/driver` afterwards
- [x] WHEN a driver attempts to confirm or register an incident on a service belonging to another driver THEN they receive 403
- [x] WHEN a user without the `services.register-times` permission navigates to `/driver` THEN they receive 403
- [x] WHEN the driver has services THEN they are listed ordered by planned time with status indicators (Abierto / Cerrado)
- [x] When a driver logs in, the `Panel` sidebar entry redirects to `/driver`

## Implementation Summary

- Migration: `add_user_id_to_drivers_table` — nullable FK `user_id` on drivers
- Model: `Driver.user()` BelongsTo, `User.driver()` HasOne
- Controller: `DriverDashboardController` with `index`, `confirmStart`, `confirmEnd`
- Routes: `GET /driver`, `POST /driver/services/{service}/confirm-start`, `POST /driver/services/{service}/confirm-end`
- Incident registration: the "Registrar Novedad" button links to `GET /service-incidents/create?service_id=X`; `ServiceIncidentController@store` sets `is_driver_report=true` when the registrar is a driver and redirects back to `/driver`
- Frontend: `resources/js/pages/driver/index.tsx` — card-based mobile-first layout
- Sidebar: "Conductor > Mis Servicios" group with `REGISTER_SERVICE_TIMES` permission; `Panel` redirects drivers to `/driver`
- Tests: feature tests cover dashboard index, confirm start/end, and cross-driver authorization

## Remaining gaps (not in scope for this requirement)

- There is no dedicated per-service detail page for drivers — the cards on `/driver` are themselves the detail view.
- Mapa GPS, Notificaciones, and Mi Perfil (present in older navigation mockups) are **not** implemented yet. The driver sidebar only exposes `Panel` and `Conductor > Mis Servicios`.
