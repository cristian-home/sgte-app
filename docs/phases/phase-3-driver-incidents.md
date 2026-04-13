# Fase 3: Conductor y Novedades

> **Estado: COMPLETADA** — Finalizada 2026-03-22

## Objetivo

Implementar la interfaz del conductor para registro de tiempos reales, la gestión de novedades/incidencias y las notificaciones por correo electrónico.

## Requerimientos cubiertos

- **REQ-012** - Gestión de Novedades/Incidencias
- **REQ-013** - Notificaciones por Correo Electrónico
- Parcial **REQ-003** - Confirmación de inicio/fin por conductor

## Dependencias

- Fase 2 completada (formulario de servicio, estados del día)

---

## Tareas

### 3.1 Interfaz del conductor ✅

- Vista simplificada de servicios asignados al conductor autenticado
- Botones de acción:
  - **Confirmar inicio**: registra `hora_inicio_real`
  - **Confirmar finalización**: registra `hora_fin_real`
- Diseño mobile-first con layout de tarjetas (conductores usan el sistema desde celular)
- Acceso solo a servicios del día actual
- Relación User-Driver via `user_id` en tabla drivers (migración nueva)
- Sidebar "Conductor > Mis Servicios" con permiso `services.register-times`

### 3.2 Gestión de novedades (REQ-012) ✅

- Formulario de novedad accesible desde:
  - Detalle del servicio (botón "Registrar Novedad" con service_id pre-llenado)
  - Listado general de novedades
- Campos:
  - Tipo de novedad (desplegable configurable)
  - Descripción detallada
  - Indicador de afectación a facturación
  - Valor adicional o descuento (visible solo si afecta facturación)
- Registro automático de: usuario (registrar_id), fecha/hora (reported_at), si fue conductor (is_driver_report)
- Pre-llenado de affects_billing desde el default del tipo seleccionado
- Indicador visual en el servicio cuando tiene novedades
- Acciones de editar/eliminar inline en tabla de incidentes del servicio
- Redirect a vista del servicio tras crear/editar/eliminar

### 3.3 Tipos de novedad configurables ✅

- CRUD administrativo completo con DataTable, formulario, permisos (4 nuevos)
- Entrada en sidebar bajo Catálogos: "Tipos de Novedad"
- Campos: código (unique), nombre, severidad (Select con enum), afecta facturación (Switch), descripción
- Badge de severidad con colores diferenciados (secondary/default/destructive)
- 16 tests incluyendo validación y autorización

### 3.4 Notificaciones por correo (REQ-013) ✅

5 notificaciones implementadas con Laravel Notifications (ShouldQueue):

| Evento | Destinatario | Clase |
| ------ | ------------ | ----- |
| Servicio asignado al conductor | Conductor (User vinculado) | `ServiceAssignedNotification` |
| Documento de vehículo próximo a vencer (30/15/5 días) | Administradores | `DocumentExpirationNotification` |
| Licencia de conductor próxima a vencer (30/15/5 días) | Administradores | `LicenseExpirationNotification` |
| Novedad que afecta facturación registrada | Admin + Contabilidad | `BillingIncidentNotification` |
| Día ejecutado | Contabilidad | `DayExecutedNotification` |

- Comando `app:check-expirations` schedulado diariamente a las 07:00
- Dispatch inline en controladores (ServiceController, ServiceIncidentController, DayStatusController)
- 10 tests cubriendo rendering y dispatch

---

## Documentación de requerimientos

| Requerimiento | Documento |
| ------------- | --------- |
| Tipos de novedad admin CRUD | [incident-types-admin-crud.md](../requirements/incident-types-admin-crud.md) |
| Gestión de novedades de servicio | [service-incidents-management.md](../requirements/service-incidents-management.md) |
| Interfaz del conductor | [driver-interface.md](../requirements/driver-interface.md) |
| Notificaciones por correo | [email-notifications.md](../requirements/email-notifications.md) |

## Criterios de completitud

- [x] Conductor puede confirmar inicio y fin de servicio desde su interfaz
- [x] Novedades registrables desde interfaz conductor y formulario de servicio
- [x] Novedades con afectación a facturación calculan valor adicional/descuento
- [x] Indicador visual de novedades en formulario de servicio y resumen del día
- [x] Notificación email al asignar servicio a conductor
- [x] Alertas automáticas de vencimiento de documentos y licencias
- [x] Notificación a contabilidad cuando se ejecuta un día

---

## Bloqueantes para Fase 4

Ninguno. Novedades, interfaz conductor y notificaciones están completamente implementados y testeados.
