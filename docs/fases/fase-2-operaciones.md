# Fase 2: Core Operativo

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

### 2.1 Calendario anual (REQ-001)

- Vista de 12 meses con indicadores de color por estado del día
  - **Negro**: sin servicios
  - **Naranja**: PROYECTADO (al menos un servicio registrado)
  - **Verde**: EJECUTADO (todos los servicios cerrados)
- Doble clic en mes → vista detallada de días
- Click en día → navegación al Gantt diario
- Componente React con Inertia.js

### 2.2 Planificador Gantt diario (REQ-002)

- Eje Y: listado de vehículos de la flota
- Eje X: horas del día (00:00 - 24:00)
- Barras horizontales por servicio con duración
- Filtro por ciudad del vehículo
- Vehículos con documentos vencidos: fila en gris, asignación bloqueada
- Click en celda vacía → formulario de nuevo servicio (vehículo y hora pre-seleccionados)
- Click en barra existente → formulario de edición del servicio
- Librería JS (Frappe Gantt o DHTMLX) integrada como componente React

### 2.3 Formulario de servicio (REQ-003)

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

### 2.4 Resumen del día (REQ-008)

- Tabla con: placa, conductor/proveedor, horarios, cliente, estado, indicador novedades
- Botón "Ejecutar Día" habilitado solo cuando todos los servicios están cerrados
- Exportar resumen

### 2.5 Estados del día y bloqueo (REQ-009)

- Lógica de transición: Sin datos → PROYECTADO → EJECUTADO
- Automáticamente PROYECTADO al registrar primer servicio del día
- EJECUTADO solo si todos los servicios cerrados
- Bloqueo de edición en estado EJECUTADO (excepto Administrador con justificación y Contabilidad)
- Registro de justificación obligatoria al modificar registros ejecutados

---

## Paquetes

| Paquete | Uso |
| ------- | --- |
| Frappe Gantt o DHTMLX | Diagrama Gantt interactivo |
| FullCalendar (JS) | Calendario anual/mensual |

## Decisiones técnicas

### Gantt

Evaluar entre:
- **Frappe Gantt**: open-source, más simple, menor curva de aprendizaje
- **DHTMLX Gantt**: más completo, mejor rendimiento con muchos registros, licencia comercial

La librería elegida se integrará como componente React que:
1. Recibe datos desde el backend via Inertia props (JSON de servicios del día)
2. Renderiza el Gantt en el cliente
3. Emite cambios al backend via Inertia router o llamadas API
4. Recibe actualizaciones en tiempo real via Laravel Echo (Reverb)

### Rendimiento (NFR-001)

- El Gantt debe soportar 100 vehículos y 300 servicios
- Usar paginación/virtualización si el rendimiento degrada
- Lazy loading de datos por ciudad (filtro)

## Criterios de completitud

- [ ] Calendario anual con colores por estado del día
- [ ] Gantt diario funcional con drag & click
- [ ] Filtro por ciudad en el Gantt
- [ ] Formulario de servicio con todas las validaciones
- [ ] Lógica de vehículo tercerizado (COD 18)
- [ ] Resumen del día con botón ejecutar
- [ ] Bloqueo de edición en días ejecutados
- [ ] Justificación obligatoria para editar registros ejecutados (admin)
