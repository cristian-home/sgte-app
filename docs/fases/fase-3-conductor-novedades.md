# Fase 3: Conductor y Novedades

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

### 3.1 Interfaz del conductor

- Vista simplificada de servicios asignados al conductor autenticado
- Botones de acción:
  - **Confirmar inicio**: registra `hora_inicio_real`
  - **Confirmar finalización**: registra `hora_fin_real`, calcula duración real
- Diseño mobile-first (conductores usan el sistema desde celular)
- Acceso solo a servicios del día actual

### 3.2 Gestión de novedades (REQ-012)

- Formulario de novedad accesible desde:
  - Interfaz del conductor (para servicios asignados)
  - Formulario de servicio (para roles admin/operación)
- Campos:
  - Tipo de novedad (desplegable configurable)
  - Descripción detallada
  - Indicador de afectación a facturación
  - Valor adicional o descuento (si afecta facturación)
- Registro automático de: usuario, fecha/hora, si fue conductor
- Indicador visual en el servicio cuando tiene novedades
- Listado de novedades del servicio con historial completo

### 3.3 Tipos de novedad configurables

- Tabla catálogo `incident_types` (modelo `IncidentType`) con código, nombre, severidad y valor por defecto de afectación a facturación
- Enum PHP `IncidentSeverity` (informational, minor, major) para clasificar la severidad
- Seeder inicial con 7 tipos: Retraso, Accidente, Avería, Tráfico, Clima, Cliente No Presentado, Otro
- Administrador puede agregar/editar tipos desde la interfaz sin cambios de código

### 3.4 Notificaciones por correo (REQ-013)

Implementar notificaciones usando Laravel Notifications:

| Evento | Destinatario | Canal |
| ------ | ------------ | ----- |
| Servicio asignado al conductor | Conductor | Email |
| Documento de vehículo próximo a vencer (30/15/5 días) | Administrador | Email |
| Licencia de conductor próxima a vencer (30/15/5 días) | Administrador | Email |
| Novedad que afecta facturación registrada | Admin + Contabilidad | Email |
| Día ejecutado | Contabilidad | Email |

- Configurar cola de correos (database driver o Redis)
- Templates de correo (Markdown mailables)
- Comando artisan schedulable para verificar vencimientos diariamente

---

## Paquetes

| Paquete | Uso |
| ------- | --- |
| Laravel Notifications (built-in) | Sistema de notificaciones |
| Laravel Mail (built-in) | Envío de correos |
| Laravel Scheduler (built-in) | Tareas programadas para vencimientos |

## Criterios de completitud

- [ ] Conductor puede confirmar inicio y fin de servicio desde su interfaz
- [ ] Novedades registrables desde interfaz conductor y formulario de servicio
- [ ] Novedades con afectación a facturación calculan valor adicional/descuento
- [ ] Indicador visual de novedades en formulario de servicio y resumen del día
- [ ] Notificación email al asignar servicio a conductor
- [ ] Alertas automáticas de vencimiento de documentos y licencias
- [ ] Notificación a contabilidad cuando se ejecuta un día
