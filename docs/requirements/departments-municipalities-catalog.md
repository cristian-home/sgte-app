---
name: departments-municipalities-catalog
type: feat
scope: catalog
status: completed
priority: high
created_date: 2026-03-05
completed_date: 2026-03-05
srs_refs: []
migration_strategy: modify-existing
---

# Departments & Municipalities Catalog (DIVIPOLA)

## Description

Create Department and Municipality models seeded from the official Colombian DIVIPOLA CSV file (`municipalities_data.csv`). These are read-only catalog tables that normalize city/location references across the application. The existing `city` string columns on `vehicles`, `drivers`, and `third_parties` MUST be replaced with `municipality_id` foreign keys. The `services.origin` and `services.destination` string columns MUST be replaced with structured municipality FK + address + coordinates columns.

## Acceptance Criteria

- [x] AC-1: WHEN the seeder runs THEN the `departments` table MUST contain all unique departments from the DIVIPOLA CSV with their DANE code and name.
- [x] AC-2: WHEN the seeder runs THEN the `municipalities` table MUST contain all rows from the DIVIPOLA CSV with their DANE code, name, type, department FK, latitude, and longitude.
- [x] AC-3: WHEN a vehicle is created THEN it MUST reference a valid municipality via `municipality_id` FK instead of a free-text `city` column.
- [x] AC-4: WHEN a driver is created THEN it MUST reference a valid municipality via `municipality_id` FK instead of a free-text `city` column.
- [x] AC-5: WHEN a third party is created THEN it MUST reference a valid municipality via `municipality_id` FK instead of a free-text `city` column.
- [x] AC-6: WHEN a service is created THEN it MUST reference origin and destination municipalities via `origin_municipality_id` and `destination_municipality_id` FKs, with optional `origin_address`, `origin_coordinates`, `destination_address`, and `destination_coordinates` columns.
- [x] AC-7: WHEN `Department::municipalities()` is called THEN it MUST return the related municipalities.
- [x] AC-8: WHEN `Municipality::department()` is called THEN it MUST return the parent department.
- [x] AC-9: WHEN tests run with SQLite in-memory THEN factories MUST create valid Department and Municipality records for use in related model tests.

## Technical Specification

### Data Model

```
departments
├── id (bigint, PK, autoincrement)
├── code (string(2), unique, NOT NULL) — DANE/DIVIPOLA department code (e.g., "5", "11")
├── name (string(100), NOT NULL) — department name (e.g., "ANTIOQUIA", "BOGOTA D.C.")
├── created_at (timestamp)
└── updated_at (timestamp)
```

```
municipalities
├── id (bigint, PK, autoincrement)
├── department_id (bigint, FK → departments.id, NOT NULL)
├── code (string(5), unique, NOT NULL) — DANE/DIVIPOLA municipality code (e.g., "5001", "11001")
├── name (string(100), NOT NULL) — municipality name (e.g., "MEDELLIN", "BOGOTA D.C.")
├── type (string(30), NOT NULL) — "Municipio", "Isla", "Area no municipalizada"
├── latitude (decimal(10,8), nullable) — from CSV
├── longitude (decimal(11,8), nullable) — from CSV
├── created_at (timestamp)
└── updated_at (timestamp)
```

**Modified tables:**

```
vehicles (modify existing migration)
├── REMOVE: city (string, 100)
└── ADD: municipality_id (bigint, FK → municipalities.id, nullable, constrained)
```

```
drivers (modify existing migration)
├── REMOVE: city (string, 100)
└── ADD: municipality_id (bigint, FK → municipalities.id, nullable, constrained)
```

```
third_parties (modify existing migration)
├── REMOVE: city (string, 100)
└── ADD: municipality_id (bigint, FK → municipalities.id, nullable, constrained)
```

```
services (modify existing migration)
├── REMOVE: origin (string, 255)
├── REMOVE: destination (string, 255)
├── ADD: origin_municipality_id (bigint, FK → municipalities.id, nullable, constrained)
├── ADD: origin_address (string(255), nullable)
├── ADD: origin_coordinates (string(50), nullable) — stored as "lat,lng" text
├── ADD: destination_municipality_id (bigint, FK → municipalities.id, nullable, constrained)
├── ADD: destination_address (string(255), nullable)
└── ADD: destination_coordinates (string(50), nullable) — stored as "lat,lng" text
```

### Enums

No new enums required. The municipality `type` column uses plain string values from the CSV ("Municipio", "Isla", "Area no municipalizada").

### Routes

No new routes. Departments and municipalities are catalog data — no CRUD endpoints needed.

### Permissions

No new permissions needed. These are read-only catalog tables.

### Pages

No new pages. Municipalities and departments will be consumed as dropdown options in existing vehicle, driver, third-party, and service forms (future frontend tasks).

## Migration Strategy

- **modify-existing**: Since the application has NOT been deployed, all changes MUST be made directly in the existing migration files. No new "add column" migrations.
- The `departments` and `municipalities` migration files MUST be created with a timestamp that sorts BEFORE all other domain migrations (before `2026_02_27_225417`), e.g., `2026_02_27_200000_create_departments_table.php` and `2026_02_27_200001_create_municipalities_table.php`.
- The search indexes migration (`2026_02_28_082401_add_search_indexes_to_services_table.php`) MUST be updated to remove the `services_origin_trgm_idx` and `services_destination_trgm_idx` indexes since the `origin` and `destination` string columns no longer exist.
- After all changes, run `php artisan migrate:fresh --seed` to verify everything works.

## Tasks

### Backend

- [x] Task 1: Create `departments` migration at `database/migrations/2026_02_27_200000_create_departments_table.php`
  - Columns: `id`, `code` (string(2), unique), `name` (string(100)), `created_at`, `updated_at`
  - No soft deletes (catalog table, never deleted)
  - Follow `create_document_types_table.php` as convention reference

- [x] Task 2: Create `municipalities` migration at `database/migrations/2026_02_27_200001_create_municipalities_table.php`
  - Columns: `id`, `department_id` (FK → departments.id, constrained), `code` (string(5), unique), `name` (string(100)), `type` (string(30)), `latitude` (decimal(10,8), nullable), `longitude` (decimal(11,8), nullable), `created_at`, `updated_at`
  - Use `Schema::disableForeignKeyConstraints()` / `enableForeignKeyConstraints()` wrapping
  - No soft deletes

- [x] Task 3: Create `Department` model at `app/Models/Department.php`
  - Fillable: `code`, `name`
  - Casts: `id` → integer
  - Relationship: `municipalities(): HasMany`
  - No `SoftDeletes`, no `LogsActivity`, no `Searchable` (simple catalog model)
  - Follow `DocumentType` model as convention reference but simpler (no Scout, no activity log)

- [x] Task 4: Create `Municipality` model at `app/Models/Municipality.php`
  - Fillable: `department_id`, `code`, `name`, `type`, `latitude`, `longitude`
  - Casts: `id` → integer, `department_id` → integer, `latitude` → `decimal:8`, `longitude` → `decimal:8`
  - Relationships: `department(): BelongsTo`, `vehicles(): HasMany`, `drivers(): HasMany`, `thirdParties(): HasMany`
  - No `SoftDeletes`, no `LogsActivity`, no `Searchable`

- [x] Task 5: Create `DepartmentFactory` at `database/factories/DepartmentFactory.php`
  - `code`: `fake()->unique()->numerify('##')`
  - `name`: `fake()->unique()->state()` (or similar)
  - Follow `DocumentTypeFactory` as convention reference

- [x] Task 6: Create `MunicipalityFactory` at `database/factories/MunicipalityFactory.php`
  - `department_id`: `Department::factory()`
  - `code`: `fake()->unique()->numerify('#####')`
  - `name`: `fake()->city()`
  - `type`: `'Municipio'`
  - `latitude`: `fake()->latitude()`
  - `longitude`: `fake()->longitude()`
  - Follow `DriverFactory` FK pattern as convention reference

- [x] Task 7: Create `DepartmentAndMunicipalitySeeder` at `database/seeders/DepartmentAndMunicipalitySeeder.php`
  - Read `municipalities_data.csv` from local storage via `Storage::disk('local')`
  - CSV uses `;` as delimiter, skip first header row (clean single-row header)
  - Parse columns: department_code (col 0), department_name (col 1), municipality_code (col 2), municipality_name (col 3), type (col 4), longitude (col 5), latitude (col 6)
  - Coordinates use comma as decimal separator (e.g., `-75,581775`) — converted to dot decimal (e.g., `-75.581775`)
  - Insert departments and municipalities using `firstOrCreate` on `code`
  - Trim whitespace from all parsed values

- [x] Task 8: Register `DepartmentAndMunicipalitySeeder` in `DatabaseSeeder.php`
  - Add BEFORE `DocumentTypeSeeder` (since other seeders will need municipalities for FK references)

- [x] Task 9: Modify `create_vehicles_table` migration
  - Replace `$table->string('city', 100)` with `$table->foreignId('municipality_id')->nullable()->constrained()`
  - Place the new column in the same position where `city` was

- [x] Task 10: Modify `create_drivers_table` migration
  - Replace `$table->string('city', 100)` with `$table->foreignId('municipality_id')->nullable()->constrained()`
  - Place the new column in the same position where `city` was

- [x] Task 11: Modify `create_third_parties_table` migration
  - Replace `$table->string('city', 100)` with `$table->foreignId('municipality_id')->nullable()->constrained()`
  - Place the new column in the same position where `city` was

- [x] Task 12: Modify `create_services_table` migration
  - Remove `$table->string('origin', 255)` and `$table->string('destination', 255)`
  - Add in their place:
    - `$table->foreignId('origin_municipality_id')->nullable()->constrained('municipalities')`
    - `$table->string('origin_address', 255)->nullable()`
    - `$table->string('origin_coordinates', 50)->nullable()`
    - `$table->foreignId('destination_municipality_id')->nullable()->constrained('municipalities')`
    - `$table->string('destination_address', 255)->nullable()`
    - `$table->string('destination_coordinates', 50)->nullable()`

- [x] Task 13: Modify `add_search_indexes_to_services_table` migration
  - Remove the `services_origin_trgm_idx` and `services_destination_trgm_idx` index creation/drop statements
  - Keep only the `services_billing_group_trgm_idx` index and the `pg_trgm` extension creation

- [x] Task 14: Update `Vehicle` model
  - Replace `city` with `municipality_id` in `$fillable`
  - Add cast: `municipality_id` → integer
  - Add relationship: `municipality(): BelongsTo`
  - Update `getActivitylogOptions()`: replace `city` with `municipality_id`
  - Update `toSearchableArray()`: replace `city` with `municipality_id`

- [x] Task 15: Update `Driver` model
  - Replace `city` with `municipality_id` in `$fillable`
  - Add cast: `municipality_id` → integer
  - Add relationship: `municipality(): BelongsTo`
  - Update `getActivitylogOptions()`: replace `city` with `municipality_id`
  - Update `toSearchableArray()`: replace `city` with `municipality_id`

- [x] Task 16: Update `ThirdParty` model
  - Replace `city` with `municipality_id` in `$fillable`
  - Add cast: `municipality_id` → integer
  - Add relationship: `municipality(): BelongsTo`
  - Update `getActivitylogOptions()`: replace `city` with `municipality_id`
  - Update `toSearchableArray()`: replace `city` with `municipality_id`

- [x] Task 17: Update `Service` model
  - Replace `origin`, `destination` with `origin_municipality_id`, `origin_address`, `origin_coordinates`, `destination_municipality_id`, `destination_address`, `destination_coordinates` in `$fillable`
  - Add casts: `origin_municipality_id` → integer, `destination_municipality_id` → integer
  - Add relationships: `originMunicipality(): BelongsTo` (foreign key `origin_municipality_id`), `destinationMunicipality(): BelongsTo` (foreign key `destination_municipality_id`)
  - Update `getActivitylogOptions()`: replace `origin`, `destination` with the new column names
  - Update `searchableColumns()`: replace `origin`, `destination` with `origin_address`, `destination_address`

- [x] Task 18: Update `VehicleFactory`
  - Replace `'city' => fake()->city()` with `'municipality_id' => Municipality::factory()`
  - Add `use App\Models\Municipality` import

- [x] Task 19: Update `DriverFactory`
  - Replace `'city' => fake()->city()` with `'municipality_id' => Municipality::factory()`
  - Add `use App\Models\Municipality` import

- [x] Task 20: Update `ThirdPartyFactory`
  - Replace `'city' => fake()->city()` with `'municipality_id' => Municipality::factory()`
  - Add `use App\Models\Municipality` import

- [x] Task 21: Update `ServiceFactory`
  - Remove the `$cities` array and `origin`/`destination` random element picks
  - Add: `origin_municipality_id` → `Municipality::factory()`, `origin_address` → `fake()->optional()->streetAddress()`, `origin_coordinates` → `null`, `destination_municipality_id` → `Municipality::factory()`, `destination_address` → `fake()->optional()->streetAddress()`, `destination_coordinates` → `null`
  - Add `use App\Models\Municipality` import

- [x] Task 22: Update seeders that reference `city` field
  - `VehicleSeeder`: replaced `city` with `municipality_id` referencing seeded municipalities by DANE code
  - `DriverSeeder`: replaced `city` with `municipality_id`
  - `ThirdPartySeeder`: replaced `city` with `municipality_id`
  - `ServiceSeeder`: replaced `origin`/`destination` with `origin_municipality_id`/`destination_municipality_id` + address columns

### Frontend

No frontend tasks. The dropdown integration for municipality selection in forms will be handled in separate requirements.

### Tests

- [x] Task 23: Create `tests/Feature/DepartmentAndMunicipalitySeederTest.php` using `php artisan make:test --pest`
  - Test that `DepartmentAndMunicipalitySeeder` populates departments table with expected count (33 departments in Colombia)
  - Test that municipalities are created with correct department relationships
  - Test that municipality codes are unique
  - Test that coordinates are correctly parsed (dot decimal, not comma)
  - Use `RefreshDatabase` trait

- [x] Task 24: Create `tests/Feature/Models/DepartmentTest.php` using `php artisan make:test --pest`
  - Test `Department` can be created via factory
  - Test `municipalities()` relationship returns Municipality instances
  - Use `RefreshDatabase` trait

- [x] Task 25: Create `tests/Feature/Models/MunicipalityTest.php` using `php artisan make:test --pest`
  - Test `Municipality` can be created via factory
  - Test `department()` relationship returns Department instance
  - Test `vehicles()`, `drivers()`, `thirdParties()` relationships
  - Use `RefreshDatabase` trait

- [x] Task 26: Update existing model tests (if any) for Vehicle, Driver, ThirdParty, Service
  - Updated tests to use `municipality_id` instead of `city`
  - Updated tests to use `origin_address`/`destination_address` instead of `origin`/`destination`

- [x] Task 27: Run `php artisan migrate:fresh --seed` to verify all migrations and seeders work
  - All 223 tests pass (810 assertions)
  - Pint formatting clean
  - Build succeeds

## Dependencies

- None. This is a foundational catalog requirement with no external dependencies.

## Notes

- The DIVIPOLA CSV file is stored at `storage/app/private/municipalities_data.csv` (accessed via `Storage::disk('local')`)
- The CSV was cleaned to have a single header row with columns: `department_code;department_name;municipality_code;municipality_name;type;longitude;latitude`
- The original `DIVIPOLA_Municipios.csv` at the project root is not committed to git
- The CSV uses `;` as delimiter
- Coordinates in the CSV use comma as decimal separator (Colombian locale) — converted to dot decimal for storage
- Colombia has 32 departments + 1 capital district (Bogota D.C.) = 33 department-level entries
- The `municipality_id` FKs on vehicles, drivers, and third_parties are nullable to allow gradual data population
- The services origin/destination municipality FKs are nullable to allow services without a municipality reference
- The `type` column on municipalities stores the raw value from CSV: "Municipio", "Isla", or "Area no municipalizada"
- No controllers or frontend pages are created — municipalities will be consumed via API endpoints or shared Inertia data in future requirements
- Frontend form fields were updated from free-text `city` to `municipality_id` (currently plain input — dropdown integration in future requirement)
