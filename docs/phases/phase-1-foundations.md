# Phase 1: Foundations and Master Data

**Status:** Completed (100%)
**Last updated:** 2026-02-28

## Objective

Establish the project base: frontend/backend stack, authentication, authorization, scaffolding of models/migrations with Blueprint, and CRUDs for master entities.

## Covered requirements

- **REQ-004** - Vehicle Fleet Management
- **REQ-005** - Driver Management
- **REQ-006** - Contract Management
- **REQ-014** - Initial Data Insertion (Seeders)
- Partial **REQ-003** - Service data structure

---

## Tasks

### 1.1 Project setup

- [x] Create Laravel project with `laravel/react-starter-kit` (Inertia.js, React, shadcn/ui, Tailwind)
- [x] Configure PostgreSQL as the database (Docker/Sail)
- [x] Configure MinIO as the S3 filesystem
- [x] Configure environment variables (.env.example)

### 1.2 Package installation and configuration

- [x] `spatie/laravel-permission` — Roles and permissions
- [x] `spatie/laravel-activitylog` — Audit log
- [x] `spatie/laravel-query-builder` — Filtering/sorting in APIs
- [x] `kirschbaum-development/eloquent-power-joins` — Optimized joins
- [x] `spatie/laravel-medialibrary` — File management
- [x] `laravel/reverb` — WebSocket server (real time)
- [x] `laravel/scout` — Full-text search with Typesense
- [x] `laravel/horizon` — Redis queue monitoring
- [x] `laravel-shift/blueprint` — Code scaffolding

### 1.3 Scaffolding with Laravel Blueprint

- [x] `draft.yaml` defined with 14 entities
- [x] 14 models with relationships generated
- [x] 14 migrations executed on PostgreSQL
- [x] 14 controllers with full CRUD
- [x] 28 form requests (store/update) with improved validation
- [x] 14 factories + 14 seeders
- [x] 14 generated feature tests (193 tests, 570 assertions)
- [x] 56 generated React (Inertia) pages

**Entities:** DocumentType, Eps, PensionFund, SeveranceFund, ThirdParty, Driver, Vehicle, Contract, Invoice, DayStatus, Service, ServiceIncident, Fuec, VehicleLocation

### 1.4 Authentication and authorization

- [x] Base authentication (Laravel Fortify + react-starter-kit)
- [x] 5 roles in `App\Enums\Role` (super_admin, admin, operator, driver, accounting)
- [x] 47 granular permissions in `App\Enums\Permission` (pattern `resource.action`)
- [x] `RolesAndPermissionsSeeder` with `syncPermissions`
- [x] `UserSeeder` with 21 test users
- [x] `super_admin` Gate bypass in `AppServiceProvider`
- [x] `can:dashboard.view` middleware on the dashboard route
- [x] Command `php artisan enum:typescript` — generates TypeScript enums from PHP (see ADR-001)
- [x] Permissions and roles shared to the frontend via Inertia (`HandleInertiaRequests`)
- [x] `usePermissions()` hook with `can()`, `hasRole()`, and super_admin bypass
- [x] `<Can permission={...}>` component for conditional rendering
- [x] Gate applied in sidebar, header, and user menu

### 1.5 Real-time configuration

- [x] Laravel Reverb installed and configured
- [x] Laravel Echo initialized on the frontend (`app.tsx`)
- [ ] Broadcasting channels for Gantt and notifications (will be used in Phase 2/3)

### 1.6 Vehicle CRUD (REQ-004)

- [x] Model, migration, controller, form requests, factory, seeder
- [x] 4 React pages (index/create/show/edit)
- [x] Filtering and sorting with Spatie Query Builder
- [x] Conditional validation `is_third_party` → requires `third_party_id`
- [x] Form with COD 18 (outsourced) logic: Switch + Select of provider terceros
- [x] Selects for tipo (bus/buseta/van/automóvil) and estado (activo/mantenimiento/retirado)
- [x] Date fields for SOAT, RTM, and operating card expirations
- [ ] Scout search — Typesense index (search UI in Phase 2)
- [ ] Visual indicators for documents — Phase 2
- [ ] Automatic expiration alerts — Phase 2

### 1.7 Driver CRUD (REQ-005)

- [x] Model, migration, controller, form requests, factory, seeder
- [x] 4 React pages
- [x] EPS normalization: `Eps` catalog with FK `eps_id`
- [x] Pension fund normalization: `PensionFund` catalog with FK `pension_fund_id`
- [x] Severance fund normalization: `SeveranceFund` catalog with FK `severance_fund_id`
- [x] License category validation (`Rule::in(['C1','C2','C3'])`)
- [x] Valid license validation (`after:today` on store, `date` on update)
- [x] Filtering and sorting with Spatie Query Builder
- [ ] Automatic expiration alerts — Phase 2

### 1.7b Social security catalogs

- [x] `Eps` model — full CRUD, factory, seeder (8 Colombian EPS), test
- [x] `PensionFund` model — full CRUD, factory, seeder (5 funds), test
- [x] `SeveranceFund` model — full CRUD, factory, seeder (4 funds), test
- [x] Catalog pattern: code/name, SoftDeletes, LogsActivity, Searchable
- [x] Seeders with real data from the Colombian context

### 1.8 ThirdParty CRUD

- [x] Model, migration, controller, form requests, factory, seeder
- [x] TipoDocumento catalog implemented
- [x] Conditional validation `is_natural_person`: natural person requires first/last name, company requires legal name
- [x] Dynamic form for natural vs legal person with Switch
- [x] Document type select, checkboxes for client/provider/active
- [x] Filtering and sorting with Spatie Query Builder

### 1.9 Contract CRUD (REQ-006)

- [x] Model, migration, controller, form requests, factory, seeder
- [x] Automatic number generation for generic contracts (`GEN-XXXX-YYYY`)
- [x] Validity validation: `end_date` must be `after_or_equal:start_date`
- [x] `contract_number` nullable when `is_generic=true`
- [x] Filtering and sorting with Spatie Query Builder

### 1.10 Navigation (Sidebar)

- [x] Sidebar with collapsible groups per module (Producción, Administración, Facturación, FUEC, GPS, Catálogos)
- [x] Each group links to the index of its resources
- [x] Permission-based filtering (items and groups hide according to role)
- [x] Routes protected with middleware `['auth', 'verified']`

### 1.11 Initial data insertion (Seeders)

- [x] Catalog seeders (Roles, Permissions, DocumentType, Eps, PensionFund, SeveranceFund)
- [x] Seeders generated by Blueprint for all entities
- [x] Driver and catalog seeders with real Colombian data

### 1.12 QueryBuilder filters and sorting

- [x] 14 controllers with `allowedFilters` and `allowedSorts` configured
- [x] Exact filters for booleans/enums (`AllowedFilter::exact`)
- [x] Partial filters for text fields

---

## Implementation notes

- Laravel Blueprint was used to generate the full scaffolding of 14 entities (11 original + 3 social security catalogs).
- Permissions were expanded from 14 generic ones to 47 granular ones following the `resource.action` pattern for use in the backend (gates/middleware) and the frontend (show/hide UI).
- The string fields `eps`, `pension_fund`, and `severance_fund` in the Driver model were normalized to FKs pointing to dedicated catalogs (Eps, PensionFund, SeveranceFund), following the DocumentType pattern.
- `syncPermissions` is used in the seeder for idempotency on re-execution.
- PHP enums are shared with the frontend via `php artisan enum:typescript` (see ADR-001). Generated files in `resources/js/enums/` are versioned in git.
- The `HandleInertiaRequests` middleware shares `auth.permissions` and `auth.roles` on every Inertia response, enabling UI control without extra requests.
- Conditional validation implemented with `Rule::when()` for ThirdParty (natural vs legal person), Vehicle (outsourced), and Contract (generic).
- React forms using `useForm` + Wayfinder for ThirdParty (dynamic) and Vehicle (COD 18).

### Relevant commits

| Commit | Description |
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

## Blockers for Phase 2

None. All models, migrations, Eloquent relationships, controllers, validations, and base forms are ready. Remaining pending items (Scout search UI, expiration alerts, visual indicators) are Phase 2 features.
