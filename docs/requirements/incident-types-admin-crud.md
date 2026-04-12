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

# Tipos de Novedad - CRUD Administrativo

## Description

Implementar la interfaz administrativa para gestionar los tipos de novedad (IncidentType). El modelo, migración, factory y enum de severidad ya existen. Faltan: controlador con permisos, form requests, páginas frontend (index con DataTable, create/edit con formulario), permisos en el enum, entrada en el sidebar bajo Catálogos, y tests.

## Acceptance Criteria

- [ ] WHEN un administrador navega a /incident-types THEN ve una tabla con todos los tipos de novedad (código, nombre, severidad, afecta facturación)
- [ ] WHEN un administrador hace clic en "Nuevo Tipo de Novedad" THEN ve un formulario con campos: código, nombre, severidad (select), afecta facturación (switch), descripción (textarea)
- [ ] WHEN un administrador envía el formulario con datos válidos THEN se crea el tipo de novedad y se redirige al index
- [ ] WHEN un administrador envía el formulario con datos inválidos THEN ve errores de validación inline
- [ ] WHEN un administrador edita un tipo de novedad THEN ve el formulario pre-llenado con los datos actuales
- [ ] WHEN un administrador elimina un tipo de novedad THEN se hace soft-delete y se redirige al index
- [ ] WHEN el controlador recibe un request THEN verifica los permisos incident-types.* via Gate
- [ ] WHEN un usuario navega al sidebar THEN ve "Tipos de Novedad" en el grupo Catálogos

## Technical Specification

### Data Model

No requiere cambios. El modelo `IncidentType` y la migración ya existen.

### Enums

Agregar 4 permisos al enum `Permission`:

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
| Index | `resources/js/pages/incident-types/index.tsx` | DataTable con código, nombre, severidad (badge de color), afecta facturación (check/x), acciones (editar/eliminar) |
| Create | `resources/js/pages/incident-types/create.tsx` | Formulario con Card: código, nombre, severidad (Select), afecta facturación (Switch), descripción (Textarea) |
| Edit | `resources/js/pages/incident-types/edit.tsx` | Mismo formulario que create, pre-llenado |

## Tasks

### Backend

- [ ] Add 4 permission cases to `app/Enums/Permission.php` with Spanish labels
- [ ] Regenerate TS enums: `php artisan enum:typescript`
- [ ] Create `app/Http/Controllers/IncidentTypeController.php` following EpsController pattern + Gate::authorize for permissions
- [ ] Create `app/Http/Requests/IncidentTypeStoreRequest.php` with rules: code (required, string, max:10, unique), name (required, string, max:100), severity (required, in:informational,minor,major), affects_billing_default (boolean), description (nullable, string)
- [ ] Create `app/Http/Requests/IncidentTypeUpdateRequest.php` with same rules (code unique ignoring current record)
- [ ] Register `Route::resource('incident-types', IncidentTypeController::class)` in routes/web.php

### Frontend

- [ ] Create `resources/js/pages/incident-types/index.tsx` with DataTable (follow EpsIndex/DocumentTypesIndex pattern)
- [ ] Create `resources/js/pages/incident-types/create.tsx` with useForm + Card (follow VehiclesCreate pattern)
- [ ] Create `resources/js/pages/incident-types/edit.tsx` with useForm pre-filled
- [ ] Add "Tipos de Novedad" entry to sidebar Catálogos group in `app-sidebar.tsx`

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
