---
name: incident-types-admin-crud
type: feat
scope: incidents
status: completed
priority: high
created_date: 2026-03-20
completed_date: 2026-03-20
srs_refs: ["REQ-012"]
migration_strategy: new
---

# Incident Types - Admin CRUD

## Description

Implement the admin interface to manage incident types (IncidentType). The model, migration, factory and severity enum already exist. Missing: controller with permissions, form requests, frontend pages (index with DataTable, create/edit with form), permissions in the enum, sidebar entry under Catálogos, and tests.

## Acceptance Criteria

- [ ] WHEN an administrator navigates to /incident-types THEN they see a table with all incident types (code, name, severity, affects billing)
- [ ] WHEN an administrator clicks "Nuevo Tipo de Novedad" THEN they see a form with fields: code, name, severity (select), affects billing (switch), description (textarea)
- [ ] WHEN an administrator submits the form with valid data THEN the incident type is created and the user is redirected to the index
- [ ] WHEN an administrator submits the form with invalid data THEN they see inline validation errors
- [ ] WHEN an administrator edits an incident type THEN they see the form pre-filled with the current data
- [ ] WHEN an administrator deletes an incident type THEN it is soft-deleted and the user is redirected to the index
- [ ] WHEN the controller receives a request THEN it verifies incident-types.* permissions via Gate
- [ ] WHEN a user browses the sidebar THEN they see "Tipos de Novedad" in the Catálogos group

## Technical Specification

### Data Model

No changes required. The `IncidentType` model and the migration already exist.

### Enums

Add 4 permissions to the `Permission` enum:

```php
case VIEW_INCIDENT_TYPES = 'incident-types.view';
case CREATE_INCIDENT_TYPES = 'incident-types.create';
case UPDATE_INCIDENT_TYPES = 'incident-types.update';
case DELETE_INCIDENT_TYPES = 'incident-types.delete';
```

### Routes

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | /incident-types | IncidentTypeController@index | auth, verified | incident-types.index |
| GET | /incident-types/create | IncidentTypeController@create | auth, verified | incident-types.create |
| POST | /incident-types | IncidentTypeController@store | auth, verified | incident-types.store |
| GET | /incident-types/{incident_type} | IncidentTypeController@show | auth, verified | incident-types.show |
| GET | /incident-types/{incident_type}/edit | IncidentTypeController@edit | auth, verified | incident-types.edit |
| PUT | /incident-types/{incident_type} | IncidentTypeController@update | auth, verified | incident-types.update |
| DELETE | /incident-types/{incident_type} | IncidentTypeController@destroy | auth, verified | incident-types.destroy |

### Permissions

```php
case VIEW_INCIDENT_TYPES = 'incident-types.view';    // 'Ver tipos de novedad'
case CREATE_INCIDENT_TYPES = 'incident-types.create'; // 'Crear tipos de novedad'
case UPDATE_INCIDENT_TYPES = 'incident-types.update'; // 'Editar tipos de novedad'
case DELETE_INCIDENT_TYPES = 'incident-types.delete'; // 'Eliminar tipos de novedad'
```

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Index | `resources/js/pages/incident-types/index.tsx` | DataTable with code, name, severity (color badge), affects billing (check/x), actions (edit/delete) |
| Create | `resources/js/pages/incident-types/create.tsx` | Form with Card: code, name, severity (Select), affects billing (Switch), description (Textarea) |
| Edit | `resources/js/pages/incident-types/edit.tsx` | Same form as create, pre-filled |

## Tasks

### Backend

- [ ] Add 4 permission cases to `app/Enums/Permission.php` with Spanish labels (UI)
- [ ] Regenerate TS enums: `php artisan enum:typescript`
- [ ] Create `app/Http/Controllers/IncidentTypeController.php` following EpsController pattern + Gate::authorize for permissions
- [ ] Create `app/Http/Requests/IncidentTypeStoreRequest.php` with rules: code (required, string, max:10, unique), name (required, string, max:100), severity (required, in:informational,minor,major), affects_billing_default (boolean), description (nullable, string)
- [ ] Create `app/Http/Requests/IncidentTypeUpdateRequest.php` with same rules (code unique ignoring current record)
- [ ] Register `Route::resource('incident-types', IncidentTypeController::class)` in routes/web.php

### Frontend

- [ ] Create `resources/js/pages/incident-types/index.tsx` with DataTable (follow EpsIndex/DocumentTypesIndex pattern)
- [ ] Create `resources/js/pages/incident-types/create.tsx` with useForm + Card (follow VehiclesCreate pattern)
- [ ] Create `resources/js/pages/incident-types/edit.tsx` with useForm pre-filled
- [ ] Add "Tipos de Novedad" entry to the sidebar Catálogos group in `app-sidebar.tsx`

### Tests

- [ ] Create `tests/Feature/Http/Controllers/IncidentTypeControllerTest.php` following EpsControllerTest pattern
- [ ] Test all CRUD actions: index, create, store, show, edit, update, destroy
- [ ] Test form request validation is applied (assertActionUsesFormRequest)
- [ ] Test Gate permissions are enforced

## Verification

### Tests (Pest)

```bash
php artisan test --compact tests/Feature/Http/Controllers/IncidentTypeControllerTest.php
```

## Dependencies

- None (model, migration, factory, and enum already exist)
