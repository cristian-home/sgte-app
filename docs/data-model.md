# Database Tables - SGTE (Updated Version)

This file contains the updated data model based on client notes and meetings.

> **Terminology note:** Product terminology in this document is in Spanish (as used in the UI); actual DB column names use English snake_case (listed in parentheses when they differ). All primary keys are `BIGINT` auto-increment (`$table->id()`), not UUID.

---

## Main changes from the previous version:

1. **New TERCERO entity** - Unifies clients and providers (natural and legal persons)
2. **New TIPO_DOCUMENTO entity** - Catalog of identification document types
3. **New NOVEDAD_SERVICIO table** - To record incidents/events
4. **`internal_code` field in Vehicle** - Internal vehicle code (value `18` flags an outsourced vehicle). There is no separate `cost_center` column.
5. **Municipality FKs** - Terceros, Vehicles, Drivers and Services reference the `municipalities` catalog instead of free-text city fields. Services split origin/destination into `origin_municipality_id` + `origin_address` + `origin_coordinates` (and destination).
6. **Driver ↔ User (1:1)** - Drivers carry a nullable `user_id` FK to `users`, which powers the `/driver` portal (`DriverDashboardController`).
7. **Invoice ↔ Tercero** - `invoices.third_party_id` FK (added during Phase D refactor) enforces "one invoice groups services from a single tercero".
8. **CONTABILIDAD role added** - With specific permissions
9. **Optional GPS** - Location is no longer required

---

## Table 1: TipoDocumento (`document_types`)

Catalog of identification document types.

| Field                 | Type        | Description                           |
| --------------------- | ----------- | ------------------------------------- |
| id                    | BIGINT      | Primary Key                           |
| code                  | VARCHAR(10) | Type code (CC, NIT, CE, etc.)         |
| name                  | VARCHAR     | Type name                             |
| is_natural_person     | BOOLEAN     | Whether it applies to natural persons |
| is_legal_person       | BOOLEAN     | Whether it applies to legal persons   |

---

## Table 2: Tercero (`third_parties`)

Unifies clients, providers, and any natural or legal person (except drivers).

| Field                 | Type    | Description                                |
| --------------------- | ------- | ------------------------------------------ |
| id                    | BIGINT  | Primary Key                                |
| document_type_id      | BIGINT  | Foreign Key → document_types               |
| identification_number | VARCHAR | Identification number                      |
| is_natural_person     | BOOLEAN | Person type                                |
| first_name            | VARCHAR | Natural persons only                       |
| middle_name           | VARCHAR | Natural persons only                       |
| first_lastname        | VARCHAR | Natural persons only                       |
| second_lastname       | VARCHAR | Natural persons only                       |
| company_name          | VARCHAR | Legal persons only (razón social)          |
| trade_name            | VARCHAR | Legal persons only (nombre comercial)      |
| municipality_id       | BIGINT  | Foreign Key → municipalities               |
| address               | VARCHAR | Main address                               |
| phone                 | VARCHAR | Contact phone                              |
| email                 | VARCHAR | Email address                              |
| is_client             | BOOLEAN | Whether it is a client                     |
| is_provider           | BOOLEAN | Whether it is a provider (outsourced veh.) |
| active                | BOOLEAN | Active/inactive status                     |

---

## Table 3: Vehiculo (`vehicles`)

| Field                   | Type       | Description                                                    |
| ----------------------- | ---------- | -------------------------------------------------------------- |
| id                      | BIGINT     | Primary Key                                                    |
| internal_code           | VARCHAR    | Internal vehicle code (value `18` flags an outsourced vehicle) |
| plate                   | VARCHAR(6) | Vehicle license plate                                          |
| movil                   | VARCHAR    | Mobile/radio number                                            |
| brand                   | VARCHAR    | Vehicle brand                                                  |
| line                    | VARCHAR    | Vehicle line                                                   |
| model                   | INT        | Model year                                                     |
| type                    | ENUM       | `bus`, `buseta`, `van`, `automobile`                           |
| engine_number           | VARCHAR    | Engine number                                                  |
| chassis_number          | VARCHAR    | Chassis number                                                 |
| passenger_capacity      | INT        | Passenger capacity                                             |
| municipality_id         | BIGINT     | FK → municipalities (used as Gantt filter)                     |
| is_third_party          | BOOLEAN    | Whether it is a third-party vehicle                            |
| third_party_id          | BIGINT     | FK → third_parties (if outsourced, otherwise NULL)             |
| soat_due_date           | DATE       | SOAT expiration date                                           |
| rtm_due_date            | DATE       | RTM expiration date                                            |
| operation_card_due_date | DATE       | Operating Card (Tarjeta de Operación) expiration date          |
| status                  | ENUM       | Vehicle status                                                 |

> **Note:** There is no `cost_center` column — internal vehicle identification is via `internal_code` alone.

---

## Table 1b: Departamento (`departments`)

Catalog of Colombian departments.

| Field | Type        | Description       |
| ----- | ----------- | ----------------- |
| id    | BIGINT      | Primary Key       |
| code  | VARCHAR(10) | DANE department code |
| name  | VARCHAR     | Department name   |

---

## Table 1c: Municipio (`municipalities`)

Catalog of Colombian municipalities, used for any address-like geographic reference (vehicles, drivers, terceros, services).

| Field         | Type        | Description                         |
| ------------- | ----------- | ----------------------------------- |
| id            | BIGINT      | Primary Key                         |
| department_id | BIGINT      | Foreign Key → departments           |
| code          | VARCHAR(10) | DANE municipality code              |
| name          | VARCHAR     | Municipality name                   |
| latitude      | DECIMAL     | Centroid latitude                   |
| longitude     | DECIMAL     | Centroid longitude                  |

---

## Table 1d: Eps (`eps`)

Catalog of Colombian Health Promotion Entities (EPS).

| Field | Type        | Description |
| ----- | ----------- | ----------- |
| id    | BIGINT      | Primary Key |
| code  | VARCHAR(10) | EPS code    |
| name  | VARCHAR     | EPS name    |

---

## Table 1e: FondoPensiones (`pension_funds`)

Catalog of Colombian Pension Funds.

| Field | Type        | Description       |
| ----- | ----------- | ----------------- |
| id    | BIGINT      | Primary Key       |
| code  | VARCHAR(10) | Pension fund code |
| name  | VARCHAR     | Pension fund name |

---

## Table 1f: FondoCesantias (`severance_funds`)

Catalog of Colombian Severance Funds.

| Field | Type        | Description         |
| ----- | ----------- | ------------------- |
| id    | BIGINT      | Primary Key         |
| code  | VARCHAR(10) | Severance fund code |
| name  | VARCHAR     | Severance fund name |

---

## Table 4: Conductor (`drivers`)

| Field                 | Type    | Description                                                   |
| --------------------- | ------- | ------------------------------------------------------------- |
| id                    | BIGINT  | Primary Key                                                   |
| user_id               | BIGINT  | FK → users (nullable, 1:1 link that powers the driver portal) |
| document_type_id      | BIGINT  | Foreign Key → document_types                                  |
| identification_number | VARCHAR | Document number                                               |
| first_name            | VARCHAR | First name                                                    |
| middle_name           | VARCHAR | Middle name                                                   |
| first_lastname        | VARCHAR | First surname                                                 |
| second_lastname       | VARCHAR | Second surname                                                |
| municipality_id       | BIGINT  | FK → municipalities (city of residence)                       |
| address               | VARCHAR | Main address                                                  |
| phone                 | VARCHAR | Contact phone                                                 |
| email                 | VARCHAR | Email address                                                 |
| license_category      | ENUM    | License category (`C1`, `C2`, `C3`)                           |
| license_due_date      | DATE    | License expiration date                                       |
| eps_id                | BIGINT  | Foreign Key → eps                                             |
| pension_fund_id       | BIGINT  | Foreign Key → pension_funds                                   |
| severance_fund_id     | BIGINT  | Foreign Key → severance_funds                                 |
| has_social_security   | BOOLEAN | Social security status                                       |
| active                | BOOLEAN | Whether active at the company                                 |

---

## Table 5: Contrato (`contracts`)

| Field             | Type    | Description                                |
| ----------------- | ------- | ------------------------------------------ |
| id                | BIGINT  | Primary Key                                |
| contract_number   | VARCHAR | Contract number                            |
| third_party_id    | BIGINT  | Foreign Key → third_parties (client)       |
| contract_object   | ENUM    | Empresarial, Turismo, Salud, Ocasional     |
| start_date        | DATE    | Start date of validity                     |
| end_date          | DATE    | End date of validity                      |
| route_description | TEXT    | Description of the authorized route        |
| is_generic        | BOOLEAN | Whether it is a temporary generic contract |

---

## Table 6: Servicio (`services`)

| Field                      | Type    | Description                                              |
| -------------------------- | ------- | -------------------------------------------------------- |
| id                         | BIGINT  | Primary Key                                              |
| contract_id                | BIGINT  | FK → contracts                                           |
| vehicle_id                 | BIGINT  | FK → vehicles                                            |
| driver_id                  | BIGINT  | FK → drivers (nullable when the vehicle is third-party)  |
| invoice_id                 | BIGINT  | FK → invoices (nullable)                                 |
| service_date               | DATE    | Service date                                             |
| origin_municipality_id     | BIGINT  | FK → municipalities                                      |
| origin_address             | VARCHAR | Free-text origin address                                 |
| origin_coordinates         | VARCHAR | Optional lat/lng pair for origin                         |
| destination_municipality_id | BIGINT | FK → municipalities                                      |
| destination_address        | VARCHAR | Free-text destination address                            |
| destination_coordinates    | VARCHAR | Optional lat/lng pair for destination                    |
| planned_start_time         | TIME    | Planned start time                                       |
| planned_duration           | INTEGER | Estimated duration in minutes                            |
| actual_start_time          | TIME    | Actual start time                                        |
| actual_end_time            | TIME    | Actual end time                                          |
| unit_value                 | DECIMAL | Unit value of the service                                |
| quantity                   | INT     | Quantity                                                 |
| billing_group              | VARCHAR | Billing category (nullable)                              |
| payment_method             | ENUM    | Payment method                                           |
| service_status             | ENUM    | `open` / `closed`                                        |

---

## Table 1g: TipoNovedad (`incident_types`)

Configurable catalog of incident/event types. Replaces the previous ENUM to allow operational management without code changes.

| Field                      | Type         | Description                                          |
| -------------------------- | ------------ | ---------------------------------------------------- |
| id                         | BIGINT       | Primary Key                                          |
| code                       | VARCHAR(10)  | Unique code (DELAY, ACCIDENT, BREAKDOWN, etc.)       |
| name                       | VARCHAR(100) | Spanish name (Retraso, Accidente, Avería, etc.)      |
| severity                   | VARCHAR(20)  | Severity: informational, minor, major (PHP ENUM)     |
| affects_billing_default    | BOOLEAN      | Default value for `affects_billing`                  |
| description                | TEXT         | Optional type description (nullable)                 |
| deleted_at                 | TIMESTAMP    | Soft delete to deactivate while preserving history   |

Seed records:

| Code      | Name                  | Severity      | Affects Billing |
| --------- | --------------------- | ------------- | :-------------: |
| DELAY     | Retraso               | minor         |       No        |
| ACCIDENT  | Accidente             | major         |       Yes       |
| BREAKDOWN | Avería                | major         |       Yes       |
| TRAFFIC   | Tráfico               | informational |       No        |
| WEATHER   | Clima                 | minor         |       No        |
| NO_SHOW   | Cliente No Presentado | minor         |       Yes       |
| OTHER     | Otro                  | informational |       No        |

---

## Table 7: NovedadServicio (`service_incidents`)

Records service incidents or events.

| Field             | Type      | Description                             |
| ----------------- | --------- | --------------------------------------- |
| id                | BIGINT    | Primary Key                             |
| service_id        | BIGINT    | Foreign Key → services                  |
| incident_type_id  | BIGINT    | Foreign Key → incident_types (NOT NULL) |
| description       | TEXT      | Incident description                    |
| registrar_id      | BIGINT    | Foreign Key → users (who recorded it)   |
| is_driver_report  | BOOLEAN   | Whether recorded by a driver            |
| reported_at       | TIMESTAMP | Date and time of the record             |
| affects_billing   | BOOLEAN   | Whether it affects the billing amount   |
| additional_value  | DECIMAL   | Additional value for the incident       |

---

## Table 8: FUEC (`fuecs`)

> **Note:** The FUEC module is scaffolded only. The table exists and a minimal CRUD is wired up, but PDF generation, QR codes, consecutive numbering, feature flag and public verification are not yet implemented (see `docs/phases/phase-5-optionals-deploy.md`).

| Field            | Type      | Description                |
| ---------------- | --------- | -------------------------- |
| id               | BIGINT    | Primary Key                |
| service_id       | BIGINT    | Foreign Key → services     |
| consecutive      | INT       | FUEC consecutive number    |
| generated_at     | TIMESTAMP | Generation date            |
| qr_code          | VARCHAR   | Verification QR code       |
| status           | ENUM      | Document status            |
| pdf_url          | VARCHAR   | URL of the generated PDF   |

---

## Table 9: EstadoDia (`day_statuses`)

| Field        | Type      | Description                   |
| ------------ | --------- | ----------------------------- |
| id           | BIGINT    | Primary Key                   |
| date         | DATE      | Day date (UNIQUE)             |
| status       | ENUM      | `projected` / `executed`      |
| executed_by  | BIGINT    | Foreign Key → users (nullable)|
| executed_at  | TIMESTAMP | Execution date (nullable)     |

---

## Table 10: Factura (`invoices`)

A single invoice groups multiple services from the same tercero. The `third_party_id` FK (added in Phase D) enforces this constraint at the schema level.

| Field          | Type    | Description                                          |
| -------------- | ------- | ---------------------------------------------------- |
| id             | BIGINT  | Primary Key                                          |
| third_party_id | BIGINT  | Foreign Key → third_parties (invoice client)         |
| invoice_number | VARCHAR | Invoice number                                       |
| total_value    | DECIMAL | Total invoice amount                                 |
| issue_date     | DATE    | Issue date                                           |
| payment_status | ENUM    | `pending` / `paid` / `overdue`                       |
| notes          | TEXT    | Free-form notes (nullable)                           |
| deleted_at     | TIMESTAMP | Soft delete                                        |

---

## Table 11: UbicacionVehiculo (`vehicle_locations`)

> **Note:** Scaffolded stub. The table exists but the driver-side capture, map view and active-service filtering are not yet implemented.

| Field       | Type      | Description                   |
| ----------- | --------- | ----------------------------- |
| id          | BIGINT    | Primary Key                   |
| vehicle_id  | BIGINT    | Foreign Key → vehicles        |
| timestamp   | TIMESTAMP | Location timestamp            |
| latitude    | DECIMAL   | GPS latitude coordinate       |
| longitude   | DECIMAL   | GPS longitude coordinate      |
| is_manual   | BOOLEAN   | Whether entered manually      |

> **Note:** GPS usage is optional (can be automatic or manual).

---

## Roles and Permissions

Reference matrix for configuring `spatie/laravel-permission`:

| Function                              | Administrador | Operación | Conductor | Contabilidad |
| ------------------------------------- | :-----------: | :-------: | :-------: | :----------: |
| Manage vehicles                       |       ✓       |     -     |     -     |      -       |
| Manage drivers                        |       ✓       |     -     |     -     |      -       |
| Manage contracts                      |       ✓       |     -     |     -     |      -       |
| Create services                       |       ✓       |     ✓     |     -     |      -       |
| Edit services (projected)             |       ✓       |     ✓     |     -     |      -       |
| Edit services (executed)              |       ✓       |     -     |     -     |      ✓       |
| Generate FUEC (optional)              |       ✓       |     ✓     |     -     |      -       |
| Execute day                           |       ✓       |     ✓     |     -     |      -       |
| View reports                          |       ✓       |     ✓     |     -     |      ✓       |
| View completed services               |       ✓       |     -     |     -     |      ✓       |
| Generate invoices                     |       ✓       |     -     |     -     |      ✓       |
| Associate services with invoices      |       ✓       |     -     |     -     |      ✓       |
| Record real times and incidents       |       -       |     -     |     ✓     |      -       |
| Receive notifications                 |       ✓       |     ✓     |     ✓     |      ✓       |

---

## Tables managed by Laravel and packages

The following tables are created and managed automatically by the framework and third-party packages:

| Table(s) | Package | Purpose |
| -------- | ------- | ------- |
| `users` | Laravel Auth (react-starter-kit) | System users |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | spatie/laravel-permission | Roles and permissions |
| `activity_log` | spatie/laravel-activitylog | Audit log |
| `notifications` | Laravel Notifications (database channel) | In-app and email notifications |

> **Note:** `NovedadServicio.registrado_por` and `EstadoDia.ejecutado_por` are FKs to Laravel's `users` table.

---

## Relationships between tables

| Source Table  | Target Table      | Relationship Type                         |
| ------------- | ----------------- | ----------------------------------------- |
| Department    | Municipality      | One-to-Many                               |
| Municipality  | Tercero           | One-to-Many                               |
| Municipality  | Vehiculo          | One-to-Many                               |
| Municipality  | Conductor         | One-to-Many                               |
| Municipality  | Servicio (origin/destination) | One-to-Many (twice per service) |
| TipoDocumento | Tercero           | One-to-Many                               |
| TipoDocumento | Conductor         | One-to-Many                               |
| User          | Conductor         | One-to-One (driver portal link, nullable) |
| Eps           | Conductor         | One-to-Many                               |
| FondoPensiones| Conductor         | One-to-Many                               |
| FondoCesantias| Conductor         | One-to-Many                               |
| Tercero       | Vehiculo          | One-to-Many (if provider)                 |
| Tercero       | Contrato          | One-to-Many (if client)                   |
| Tercero       | Factura           | One-to-Many (invoice client)              |
| Contrato      | Servicio          | One-to-Many                               |
| Vehiculo      | Servicio          | One-to-Many                               |
| Conductor     | Servicio          | One-to-Many                               |
| Factura       | Servicio          | One-to-Many                               |
| TipoNovedad   | NovedadServicio   | One-to-Many                               |
| Servicio      | NovedadServicio   | One-to-Many                               |
| Servicio      | FUEC              | One-to-One                                |
| Vehiculo      | UbicacionVehiculo | One-to-Many                               |
