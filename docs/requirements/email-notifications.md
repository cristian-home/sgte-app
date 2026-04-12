---
name: email-notifications
type: feat
scope: notifications
status: completed
priority: high
created_date: 2026-03-22
completed_date: 2026-03-22
srs_refs: ["REQ-013"]
migration_strategy: new
---

# Notificaciones por Correo Electrónico

## Description

Implementar 5 tipos de notificaciones por correo usando Laravel Notifications. Incluye un comando artisan schedulable para verificar vencimientos de documentos y licencias diariamente.

## Acceptance Criteria

- [x] WHEN un servicio se crea con conductor vinculado THEN el conductor recibe email de asignación
- [x] WHEN un documento de vehículo vence en 30/15/5 días THEN los administradores reciben email de alerta
- [x] WHEN una licencia de conductor vence en 30/15/5 días THEN los administradores reciben email de alerta
- [x] WHEN se registra una novedad que afecta facturación THEN admin + contabilidad reciben email
- [x] WHEN un día se ejecuta THEN los usuarios con rol contabilidad reciben email
- [x] WHEN se ejecuta `app:check-expirations` THEN se verifican todos los documentos y licencias

## Implementation Summary

- 5 Notification classes in `app/Notifications/`
- `CheckExpirations` artisan command scheduled daily at 07:00
- Notifications dispatched inline in controllers (ServiceController, ServiceIncidentController, DayStatusController)
- All notifications implement `ShouldQueue` for async processing
- 10 tests covering rendering and dispatch
