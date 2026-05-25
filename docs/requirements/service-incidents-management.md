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

# Service Incidents Management

## Description

Replace the scaffold pages for service-incidents with a fully functional UI. Add a "Registrar Novedad" button on the service detail view. Auto-assign registrar_id (authenticated user), reported_at (now), and is_driver_report (based on the user's role). Allow editing and deleting incidents from the service view.

## Acceptance Criteria

- [ ] WHEN a user with permission navigates to /service-incidents THEN they see a DataTable with all incidents (service, type, description, date, registrar, billing)
- [ ] WHEN a user clicks "Registrar Novedad" from the service detail THEN they see a form with service_id pre-filled
- [ ] WHEN a user submits the form THEN registrar_id, reported_at and is_driver_report are auto-assigned
- [ ] WHEN affects_billing is enabled THEN the additional_value field is shown
- [ ] WHEN the incident type changes THEN affects_billing is pre-filled with the default of the selected type
- [ ] WHEN a user edits an incident THEN they see the form pre-filled
- [ ] WHEN a user deletes an incident from the service detail THEN it is removed and the page is reloaded

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
