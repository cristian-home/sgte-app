---
name: service-incidents-management
type: feat
scope: incidents
status: completed
priority: high
created_date: 2026-03-20
completed_date: 2026-03-20
srs_refs: ["REQ-012"]
migration_strategy: new
---

# Gestión de Novedades de Servicio

## Description

Reemplazar las páginas scaffold de service-incidents con una UI funcional completa. Agregar un botón "Registrar Novedad" en la vista detalle del servicio. Auto-asignar registrar_id (usuario autenticado), reported_at (now), e is_driver_report (según rol del usuario). Permitir edición y eliminación de novedades desde la vista del servicio.

## Acceptance Criteria

- [ ] WHEN un usuario con permiso navega a /service-incidents THEN ve una DataTable con todas las novedades (servicio, tipo, descripción, fecha, registrador, facturación)
- [ ] WHEN un usuario hace clic en "Registrar Novedad" desde el detalle del servicio THEN ve un formulario con service_id pre-llenado
- [ ] WHEN un usuario envía el formulario THEN se auto-asigna registrar_id, reported_at y is_driver_report
- [ ] WHEN affects_billing está activado THEN se muestra el campo additional_value
- [ ] WHEN el tipo de novedad cambia THEN affects_billing se pre-llena con el default del tipo seleccionado
- [ ] WHEN un usuario edita una novedad THEN ve el formulario pre-llenado
- [ ] WHEN un usuario elimina una novedad desde el detalle del servicio THEN se elimina y se recarga la página

## Tasks

### Backend

- [ ] Update ServiceIncidentController: auto-set registrar_id, reported_at, is_driver_report in store; accept service_id in create; load service+incidentTypes for create/edit; redirect back to service show
- [ ] Update ServiceIncidentStoreRequest: remove registrar_id, reported_at, is_driver_report from required
- [ ] Update ServiceIncidentUpdateRequest: same changes

### Frontend

- [ ] Replace service-incidents/index.tsx with DataTable
- [ ] Replace service-incidents/create.tsx with functional form
- [ ] Replace service-incidents/edit.tsx with functional form
- [ ] Add "Registrar Novedad" button on services/show.tsx incident section
- [ ] Add delete action on incidents table in services/show.tsx

### Tests

- [ ] Update ServiceIncidentControllerTest to reflect auto-set fields

## Dependencies

- Requirement: incident-types-admin-crud (completed)
