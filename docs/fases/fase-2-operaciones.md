# Fase 2: Core Operativo

> **Estado: COMPLETADA** — Finalizada 2026-03-07

## Objetivo

Implementar la funcionalidad central del sistema: calendario, planificador Gantt, formulario de servicio y control de estados del día.

## Requerimientos cubiertos

- **REQ-001** - Gestión de Calendario y Estados
- **REQ-002** - Vista de Flota Diaria (Gantt)
- **REQ-003** - Formulario de Servicio
- **REQ-008** - Resumen del Día
- **REQ-009** - Control de Inmutabilidad Contable

## Dependencias

- Fase 1 completada (migraciones, CRUDs, auth)

---

## Tareas

### 2.1 Calendario anual (REQ-001) ✅

- Vista de 12 meses con indicadores de color por estado del día
  - **Negro**: sin servicios
  - **Naranja**: PROYECTADO (al menos un servicio registrado)
  - **Verde**: EJECUTADO (todos los servicios cerrados)
- Click en mes → vista detallada de días con navegación prev/next
- Click en día → carga inline de servicios del día
- Componente React custom con Inertia.js (sin dependencia de FullCalendar)

### 2.2 Planificador Gantt diario (REQ-002) ✅

- Eje Y: listado de vehículos de la flota
- Eje X: horas del día (00:00 - 24:00)
- Barras horizontales por servicio con duración
- Filtro por municipio del vehículo (MunicipalityCombobox)
- Vehículos con documentos vencidos: fila en gris, asignación bloqueada
- Click en celda vacía → formulario de nuevo servicio (vehículo y hora pre-seleccionados)
- Click en barra existente → navegación al detalle del servicio
- Componente React custom (sin librería Gantt externa)

### 2.3 Formulario de servicio (REQ-003) ✅

- Campos obligatorios: placa, conductor, tercero/contrato, origen, destino, hora inicio, duración
- Lógica para vehículos tercerizados (COD 18):
  - Ocultar campo conductor
  - Mostrar proveedor asociado
- Selector de contrato con validación de vigencia
- Opción de contrato genérico temporal
- Validación de conflictos de horario
- Validación de conductor: licencia vigente + seguridad social
- Campos de ejecución: hora inicio real, hora fin real, duración real (calculada)
- Indicador visual de novedades registradas
- Campos de facturación: grupo, valor unitario, cantidad, forma de pago
- Estado: Abierto / Cerrado

### 2.4 Resumen del día (REQ-008) ✅

- Tabla consolidada con estadísticas ejecutivas (total, cerrados, abiertos, con novedades, tercerizado)
- Botón "Ejecutar Día" habilitado solo cuando todos los servicios están cerrados
- Exportación CSV

### 2.5 Estados del día y bloqueo (REQ-009) ✅

- Lógica de transición: Sin datos → PROYECTADO → EJECUTADO
- Automáticamente PROYECTADO al registrar primer servicio del día
- EJECUTADO solo si todos los servicios cerrados
- Bloqueo de edición en estado EJECUTADO (excepto Administrador con justificación y Contabilidad)
- Registro de justificación obligatoria al modificar registros ejecutados
- Activity log con spatie/laravel-activitylog

---

## Decisiones técnicas

### Gantt y Calendario

Se optó por **componentes React custom** en lugar de librerías externas (Frappe Gantt, DHTMLX, FullCalendar). Esto permitió:
1. Control total sobre la UI/UX con Tailwind CSS y shadcn/ui
2. Integración nativa con Inertia props y navegación
3. Sin dependencias adicionales de JS ni costos de licencia
4. Rendimiento adecuado para el volumen esperado

### Catálogo geográfico

Se implementó el catálogo DIVIPOLA (departamentos y municipios de Colombia) como soporte para los filtros del Gantt y los formularios de servicio. Incluye un componente `MunicipalityCombobox` reutilizable con búsqueda y agrupación por departamento.

### Vista detalle de servicio

Se rediseñó la vista show del servicio con layout de tarjetas, barra de timeline (planificado vs real), resumen de facturación e indicadores de incidencias.

---

## Documentación de requerimientos

Cada feature de esta fase tiene su documento detallado en `docs/requirements/`:

| Requerimiento | Documento |
| ------------- | --------- |
| Calendario anual | [annual-calendar.md](../requirements/annual-calendar.md) |
| Gantt diario | [daily-gantt.md](../requirements/daily-gantt.md) |
| Formulario de servicio | [service-form.md](../requirements/service-form.md) |
| Resumen del día | [day-summary.md](../requirements/day-summary.md) |
| Lógica de estados del día | [day-status-logic.md](../requirements/day-status-logic.md) |
| Rediseño detalle de servicio | [service-detail-redesign.md](../requirements/service-detail-redesign.md) |
| Catálogo departamentos/municipios | [departments-municipalities-catalog.md](../requirements/departments-municipalities-catalog.md) |
| Combobox de municipios | [municipality-combobox.md](../requirements/municipality-combobox.md) |

## Criterios de completitud

- [x] Calendario anual con colores por estado del día
- [x] Gantt diario funcional con click-to-create
- [x] Filtro por municipio en el Gantt
- [x] Formulario de servicio con todas las validaciones
- [x] Lógica de vehículo tercerizado (COD 18)
- [x] Resumen del día con botón ejecutar y exportación CSV
- [x] Bloqueo de edición en días ejecutados
- [x] Justificación obligatoria para editar registros ejecutados (admin)

---

## Bloqueantes para Fase 3

Ninguno. Calendario, Gantt, formulario de servicio, estados del día y resumen del día están completamente implementados y testeados.
