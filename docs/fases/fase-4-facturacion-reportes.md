# Fase 4: Facturación y Auditoría

> **Estado: PENDIENTE** — Requiere Fase 2 (completada); Fase 3 recomendada

## Objetivo

Implementar el módulo de facturación de servicios, asegurar la inmutabilidad contable de registros ejecutados y establecer el log de auditoría.

## Requerimientos cubiertos

- **REQ-011** - Facturación de Servicios
- **REQ-009** - Control de Inmutabilidad Contable (complemento)

## Dependencias

- Fase 2 completada (estados del día, servicios cerrados)
- Fase 3 recomendada (novedades pueden afectar facturación)

---

## Tareas

### 4.1 Facturación de servicios (REQ-011)

- Vincular número de factura a servicios cerrados
- Formulario de factura:
  - Número de factura
  - Valor total (calculado desde servicio + novedades)
  - Fecha de emisión
  - Estado de pago (Pendiente, Pagada, Anulada)
- Vista de facturación: listado de servicios pendientes de facturar
- Filtros: por tercero, por fecha, por estado de pago
- Cálculo de valor total considerando novedades con afectación

### 4.2 Asociación servicio-factura

- Un servicio cerrado puede asociarse a una factura
- Vista para seleccionar múltiples servicios del mismo tercero y asociarlos a una factura
- Solo roles Administrador y Contabilidad pueden facturar
- Generación de PDF de factura (informativo, no fiscal)

### 4.3 Inmutabilidad contable (REQ-009 complemento)

- En estado EJECUTADO:
  - Rol Operación: lectura solamente
  - Rol Administrador: edición con justificación obligatoria
  - Rol Contabilidad: puede asociar facturas y editar campos contables
- Cada modificación a un registro ejecutado debe registrarse en el log de auditoría

### 4.4 Log de auditoría

Implementar usando `owen-it/laravel-auditing`:

- Registrar automáticamente cambios en modelos auditables:
  - Servicio
  - Factura
  - EstadoDia
  - Contrato
- Datos capturados por cambio:
  - Usuario que realizó el cambio
  - Fecha y hora
  - Valor anterior
  - Valor nuevo
  - Justificación (campo adicional para registros ejecutados)
- Vista de consulta de auditoría para Administrador
- Filtros: por modelo, por usuario, por rango de fechas

---

## Paquetes

| Paquete | Uso |
| ------- | --- |
| `owen-it/laravel-auditing` | Log de auditoría automático |
| `barryvdh/laravel-dompdf` | Generación de PDF de facturas |

## Criterios de completitud

- [ ] Servicios cerrados pueden asociarse a facturas
- [ ] Valor total de factura calcula novedades con afectación
- [ ] Solo Admin y Contabilidad pueden facturar
- [ ] Generación de PDF informativo de factura
- [ ] Log de auditoría registra todos los cambios en modelos sensibles
- [ ] Modificación de registros ejecutados requiere justificación
- [ ] Vista de consulta de auditoría funcional
