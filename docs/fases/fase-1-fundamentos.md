# Fase 1: Fundamentos y Datos Maestros

**Estado:** En progreso (~90%)
**Última actualización:** 2026-02-26

## Objetivo

Establecer la base del proyecto: stack frontend/backend, autenticación, autorización, scaffolding de modelos/migraciones con Blueprint, y CRUDs de entidades maestras.

## Requerimientos cubiertos

- **REQ-004** - Gestión de Flota Vehicular
- **REQ-005** - Gestión de Conductores
- **REQ-006** - Gestión de Contratos
- **REQ-014** - Inserción Inicial de Datos (Seeders)
- Parcial **REQ-003** - Estructura de datos para servicios

---

## Tareas

### 1.1 Setup del proyecto

- [x] Crear proyecto Laravel con `laravel/react-starter-kit` (Inertia.js, React, shadcn/ui, Tailwind)
- [x] Configurar PostgreSQL como BD (Docker/Sail)
- [ ] Configurar MinIO como filesystem S3
- [x] Configurar variables de entorno (.env.example)

### 1.2 Instalación y configuración de paquetes

- [x] `spatie/laravel-permission` — Roles y permisos
- [x] `spatie/laravel-activitylog` — Log de auditoría
- [x] `spatie/laravel-query-builder` — Filtrado/ordenamiento en APIs
- [x] `kirschbaum-development/eloquent-power-joins` — Joins optimizados
- [x] `spatie/laravel-medialibrary` — Gestión de archivos
- [x] `laravel/reverb` — WebSocket server (tiempo real)
- [x] `laravel/scout` — Búsqueda full-text con Typesense
- [x] `laravel/horizon` — Monitoreo de colas Redis
- [x] `laravel-shift/blueprint` — Scaffolding de código

### 1.3 Scaffolding con Laravel Blueprint

- [x] `draft.yaml` definido con 11 entidades
- [x] 11 modelos con relaciones generados
- [x] 11 migraciones ejecutadas en PostgreSQL
- [x] 11 controladores con CRUD completo
- [x] 22 form requests (store/update)
- [x] 11 factories + 11 seeders
- [x] 11 feature tests generados
- [x] 44 páginas React (Inertia) generadas

**Entidades:** DocumentType, ThirdParty, Driver, Vehicle, Contract, Invoice, DayStatus, Service, ServiceIncident, Fuec, VehicleLocation

### 1.4 Autenticación y autorización

- [x] Autenticación base (Laravel Fortify + react-starter-kit)
- [x] 5 roles en `App\Enums\Role` (super_admin, admin, operator, driver, accounting)
- [x] 43 permisos granulares en `App\Enums\Permission` (patrón `recurso.accion`)
- [x] `RolesAndPermissionsSeeder` con `syncPermissions`
- [x] `UserSeeder` con 21 usuarios de prueba
- [x] Gate `super_admin` bypass en `AppServiceProvider`
- [x] Middleware `can:dashboard.view` en ruta dashboard

### 1.5 Configuración de tiempo real

- [x] Laravel Reverb instalado y configurado
- [ ] Laravel Echo configuración de canales en frontend
- [ ] Canales broadcasting para Gantt y notificaciones (se usará en Fase 2/3)

### 1.6 CRUD de Vehículos (REQ-004)

- [x] Modelo, migración, controlador, form requests, factory, seeder
- [x] 4 páginas React (index/create/show/edit)
- [ ] Búsqueda Scout — pendiente configurar índice Typesense
- [ ] Filtrado Query Builder en controlador
- [ ] Lógica COD 18 (tercerizado) en frontend
- [ ] Indicadores visuales de documentos — Fase 2
- [ ] Alertas automáticas por vencimiento — Fase 2

### 1.7 CRUD de Conductores (REQ-005)

- [x] Modelo, migración, controlador, form requests, factory, seeder
- [x] 4 páginas React
- [ ] Validación custom de licencia vigente
- [ ] Alertas automáticas por vencimiento — Fase 2

### 1.8 CRUD de Terceros

- [x] Modelo, migración, controlador, form requests, factory, seeder
- [x] Catálogo TipoDocumento implementado
- [ ] Formulario dinámico persona natural vs jurídica en frontend

### 1.9 CRUD de Contratos (REQ-006)

- [x] Modelo, migración, controlador, form requests, factory, seeder
- [ ] Lógica de generación automática de contratos genéricos
- [ ] Validación custom de vigencia

### 1.10 Inserción inicial de datos (Seeders)

- [x] Seeders catálogos (Roles, Permisos, DocumentType)
- [x] Seeders generados por Blueprint para todas las entidades
- [ ] Personalizar seeders con datos reales del cliente

---

## Notas de implementación

- Se usó Laravel Blueprint para generar el scaffolding completo de 11 entidades.
- Los permisos se expandieron de 14 genéricos a 43 granulares con patrón `recurso.accion` para uso en backend (gates/middleware) y frontend (mostrar/ocultar UI).
- Se usa `syncPermissions` en el seeder para idempotencia al re-ejecutar.

### Commits relevantes

| Commit | Descripción |
|--------|-------------|
| `d75ae94` | Initial commit |
| `edfeab0` | Add development skills and Docker Sail configuration |
| `84d9523` | Add Laravel Reverb real-time broadcasting support |
| `cc6004a` | Install Laravel Horizon for Redis queue monitoring |
| `73b3816` | Add role-based access control with Spatie Permission |
| `8572701` | Add roles and permissions seeders with enum definitions |
| `4c116d9` | Add custom generators for enhanced code generation |
| `b454f75` | Add url prop sharing for SSR support |

---

## Bloqueantes para Fase 2

Todos los modelos, migraciones, relaciones Eloquent y controladores necesarios para la Fase 2 están listos. Los pendientes de esta fase (Scout, Query Builder, formularios dinámicos) no bloquean el inicio de la Fase 2.
