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

# Interfaz del Conductor

## Description

Interfaz mobile-first para conductores. Muestra los servicios asignados al conductor para el día actual con botones para confirmar inicio y fin de servicio. Requiere vincular el modelo Driver a User via `user_id` en la tabla drivers.

## Acceptance Criteria

- [x] WHEN un conductor navega a /driver THEN ve sus servicios del día actual
- [x] WHEN un conductor sin driver vinculado navega a /driver THEN ve un mensaje indicando contactar al administrador
- [x] WHEN un conductor hace clic en "Confirmar Inicio" THEN se registra la hora_inicio_real
- [x] WHEN un conductor hace clic en "Confirmar Fin" THEN se registra la hora_fin_real
- [x] WHEN un conductor intenta confirmar un servicio de otro conductor THEN recibe 403
- [x] WHEN un usuario sin permiso services.register-times navega a /driver THEN recibe 403
- [x] WHEN el conductor tiene servicios THEN los ve ordenados por hora planificada con indicadores de estado

## Implementation Summary

- Migration: `add_user_id_to_drivers_table` — nullable FK `user_id` on drivers
- Model: Driver.user() BelongsTo, User.driver() HasOne
- Controller: `DriverDashboardController` with index, confirmStart, confirmEnd
- Routes: GET /driver, POST /driver/services/{service}/confirm-start, POST /driver/services/{service}/confirm-end
- Frontend: `resources/js/pages/driver/index.tsx` — card-based mobile-first layout
- Sidebar: "Conductor > Mis Servicios" group with REGISTER_SERVICE_TIMES permission
- Tests: 7 tests covering all CRUD and authorization scenarios
