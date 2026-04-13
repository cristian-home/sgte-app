# Database Tables - SGTE (Updated Version)

This file contains the updated data model based on client notes and meetings.

---

## Main changes from the previous version:

1. **New TERCERO entity** - Unifies clients and providers (natural and legal persons)
2. **New TIPO_DOCUMENTO entity** - Catalog of identification document types
3. **New NOVEDAD_SERVICIO table** - To record incidents/events
4. **COD field in VEHICULO** - Internal code combined with cost center
5. **CIUDAD field in VEHICULO** - Used for filtering in the Gantt
6. **CONTABILIDAD role added** - With specific permissions
7. **Optional GPS** - Location is no longer required

---

## Table 1: TipoDocumento

Catalog of identification document types.

| Field               | Type        | Description                           |
| ------------------- | ----------- | ------------------------------------- |
| id                  | UUID        | Primary Key                           |
| codigo              | VARCHAR(10) | Type code (CC, NIT, CE, etc.)         |
| nombre              | VARCHAR     | Type name                             |
| es_persona_natural  | BOOLEAN     | Whether it applies to natural persons |
| es_persona_juridica | BOOLEAN     | Whether it applies to legal persons   |

---

## Table 2: Tercero

Unifies clients, providers, and any natural or legal person (except drivers).

| Field                 | Type    | Description                                |
| --------------------- | ------- | ------------------------------------------ |
| id                    | UUID    | Primary Key                                |
| tipo_documento_id     | UUID    | Foreign Key → TipoDocumento                |
| numero_identificacion | VARCHAR | Identification number                      |
| es_persona_natural    | BOOLEAN | Person type                                |
| primer_nombre         | VARCHAR | Natural persons only                       |
| segundo_nombre        | VARCHAR | Natural persons only                       |
| primer_apellido       | VARCHAR | Natural persons only                       |
| segundo_apellido      | VARCHAR | Natural persons only                       |
| razon_social          | VARCHAR | Legal persons only                         |
| nombre_comercial      | VARCHAR | Legal persons only                         |
| ciudad                | VARCHAR | City                                       |
| direccion             | VARCHAR | Main address                               |
| telefono              | VARCHAR | Contact phone                              |
| email                 | VARCHAR | Email address                              |
| es_cliente            | BOOLEAN | Whether it is a client                     |
| es_proveedor          | BOOLEAN | Whether it is a provider (outsourced veh.) |
| activo                | BOOLEAN | Active/inactive status                     |

---

## Table 3: Vehiculo

| Field                  | Type       | Description                                  |
| ---------------------- | ---------- | -------------------------------------------- |
| id                     | UUID       | Primary Key                                  |
| cod                    | VARCHAR    | Internal code (18 = outsourced)              |
| placa                  | VARCHAR(6) | Vehicle license plate                        |
| movil                  | VARCHAR    | Mobile number                                |
| marca                  | VARCHAR    | Vehicle brand                                |
| linea                  | VARCHAR    | Vehicle line                                 |
| modelo                 | INT        | Model year                                   |
| tipo                   | ENUM       | Bus, Buseta, Van, Automóvil                  |
| motor                  | VARCHAR    | Engine number                                |
| chasis                 | VARCHAR    | Chassis number                               |
| capacidad              | INT        | Passenger capacity                           |
| ciudad                 | VARCHAR    | City (for Gantt filter)                      |
| es_tercerizado         | BOOLEAN    | Whether it is a third-party vehicle          |
| tercero_id             | UUID       | Foreign Key → Tercero (if outsourced)        |
| soat_vencimiento       | DATE       | SOAT expiration date                         |
| rtm_vencimiento        | DATE       | RTM expiration date                          |
| tarjeta_op_vencimiento | DATE       | Operating Card expiration date               |
| estado                 | ENUM       | Vehicle status                               |

---

## Table 1b: Eps

Catalog of Colombian Health Promotion Entities (EPS).

| Field  | Type        | Description |
| ------ | ----------- | ----------- |
| id     | UUID        | Primary Key |
| codigo | VARCHAR(10) | EPS code    |
| nombre | VARCHAR     | EPS name    |

---

## Table 1c: FondoPensiones

Catalog of Colombian Pension Funds.

| Field  | Type        | Description       |
| ------ | ----------- | ----------------- |
| id     | UUID        | Primary Key       |
| codigo | VARCHAR(10) | Pension fund code |
| nombre | VARCHAR     | Pension fund name |

---

## Table 1d: FondoCesantias

Catalog of Colombian Severance Funds.

| Field  | Type        | Description        |
| ------ | ----------- | ------------------ |
| id     | UUID        | Primary Key        |
| codigo | VARCHAR(10) | Severance fund code|
| nombre | VARCHAR     | Severance fund name|

---

## Table 4: Conductor

| Field                    | Type    | Description                    |
| ------------------------ | ------- | ------------------------------ |
| id                       | UUID    | Primary Key                    |
| tipo_documento_id        | UUID    | Foreign Key → TipoDocumento    |
| numero_identificacion    | VARCHAR | Document number                |
| primer_nombre            | VARCHAR | First name                     |
| segundo_nombre           | VARCHAR | Middle name                    |
| primer_apellido          | VARCHAR | First surname                  |
| segundo_apellido         | VARCHAR | Second surname                 |
| ciudad                   | VARCHAR | City of residence              |
| direccion                | VARCHAR | Main address                   |
| telefono                 | VARCHAR | Contact phone                  |
| email                    | VARCHAR | Email address                  |
| categoria_licencia       | VARCHAR | License category               |
| licencia_vencimiento     | DATE    | License expiration date        |
| eps_id                   | UUID    | Foreign Key → Eps              |
| fondo_pensiones_id       | UUID    | Foreign Key → FondoPensiones   |
| fondo_cesantias_id       | UUID    | Foreign Key → FondoCesantias   |
| seguridad_social_vigente | BOOLEAN | Social security status         |
| activo                   | BOOLEAN | Whether active at the company  |

---

## Table 5: Contrato

| Field            | Type    | Description                              |
| ---------------- | ------- | ---------------------------------------- |
| id               | UUID    | Primary Key                              |
| numero           | VARCHAR | Contract number                          |
| tercero_id       | UUID    | Foreign Key → Tercero (client)           |
| objeto           | ENUM    | Empresarial, Turismo, Salud, Ocasional   |
| fecha_inicio     | DATE    | Start date of validity                   |
| fecha_fin        | DATE    | End date of validity                     |
| ruta_descripcion | TEXT    | Description of the authorized route      |
| es_generico      | BOOLEAN | Whether it is a temporary generic contract |

---

## Table 6: Servicio

| Field            | Type     | Description                                         |
| ---------------- | -------- | --------------------------------------------------- |
| id               | UUID     | Primary Key                                         |
| contrato_id      | UUID     | Foreign Key → Contrato                              |
| vehiculo_id      | UUID     | Foreign Key → Vehiculo                              |
| conductor_id     | UUID     | Foreign Key → Conductor (nullable if outsourced)    |
| factura_id       | UUID     | Foreign Key → Factura (nullable)                    |
| fecha            | DATE     | Service date                                        |
| origen           | VARCHAR  | Trip origin                                         |
| destino          | VARCHAR  | Trip destination                                    |
| hora_inicio_plan | TIME     | Planned start time                                  |
| duracion_plan    | INTERVAL | Estimated duration                                  |
| hora_inicio_real | TIME     | Actual start time                                   |
| hora_fin_real    | TIME     | Actual end time                                     |
| valor_unitario   | DECIMAL  | Unit value of the service                           |
| cantidad         | INT      | Quantity                                            |
| grupo            | VARCHAR  | Billing category (nullable)                         |
| forma_pago       | ENUM     | Payment method                                      |
| estado_servicio  | ENUM     | ABIERTO/CERRADO                                     |

---

## Table 1e: TipoNovedad

Configurable catalog of incident/event types. Replaces the previous ENUM to allow operational management without code changes.

| Field                          | Type        | Description                                          |
| ------------------------------ | ----------- | ---------------------------------------------------- |
| id                             | BIGINT      | Primary Key                                          |
| codigo                         | VARCHAR(10) | Unique code (DELAY, ACCIDENT, BREAKDOWN, etc.)       |
| nombre                         | VARCHAR(100)| Spanish name (Retraso, Accidente, Avería, etc.)      |
| severidad                      | VARCHAR(20) | Severity: informational, minor, major (PHP ENUM)     |
| afecta_facturacion_por_defecto | BOOLEAN     | Default value for afecta_facturacion                 |
| descripcion                    | TEXT        | Optional type description (nullable)                 |
| soft_delete                    | TIMESTAMP   | Soft delete to deactivate while preserving history   |

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

## Table 7: NovedadServicio

Records service incidents or events.

| Field              | Type      | Description                             |
| ------------------ | --------- | --------------------------------------- |
| id                 | BIGINT    | Primary Key                             |
| servicio_id        | BIGINT    | Foreign Key → Servicio                  |
| tipo_novedad_id    | BIGINT    | Foreign Key → TipoNovedad (NOT NULL)    |
| descripcion        | TEXT      | Incident description                    |
| registrado_por     | BIGINT    | Foreign Key → User (who recorded it)    |
| es_conductor       | BOOLEAN   | Whether recorded by a driver            |
| fecha_registro     | TIMESTAMP | Date and time of the record             |
| afecta_facturacion | BOOLEAN   | Whether it affects the billing amount   |
| valor_adicional    | DECIMAL   | Additional value for the incident       |

---

## Table 8: FUEC

| Field            | Type      | Description                |
| ---------------- | --------- | -------------------------- |
| id               | UUID      | Primary Key                |
| servicio_id      | UUID      | Foreign Key → Servicio     |
| consecutivo      | INT       | FUEC consecutive number    |
| fecha_generacion | TIMESTAMP | Generation date            |
| codigo_qr        | VARCHAR   | Verification QR code       |
| estado           | ENUM      | Document status            |
| pdf_url          | VARCHAR   | URL of the generated PDF   |

> **Note:** The FUEC module is optional and can be enabled/disabled.

---

## Table 9: EstadoDia

| Field           | Type      | Description                   |
| --------------- | --------- | ----------------------------- |
| id              | UUID      | Primary Key                   |
| fecha           | DATE      | Day date (UNIQUE)             |
| estado          | ENUM      | PROYECTADO/EJECUTADO          |
| ejecutado_por   | UUID      | Foreign Key → User (nullable) |
| ejecutado_fecha | TIMESTAMP | Execution date (nullable)     |

---

## Table 10: Factura

A single invoice can group multiple services from the same tercero.

| Field          | Type    | Description             |
| -------------- | ------- | ----------------------- |
| id             | UUID    | Primary Key             |
| numero_factura | VARCHAR | Invoice number          |
| valor_total    | DECIMAL | Total invoice amount    |
| fecha_emision  | DATE    | Issue date              |
| estado_pago    | ENUM    | Payment status          |

---

## Table 11: UbicacionVehiculo

| Field       | Type      | Description                   |
| ----------- | --------- | ----------------------------- |
| id          | UUID      | Primary Key                   |
| vehiculo_id | UUID      | Foreign Key → Vehiculo        |
| timestamp   | TIMESTAMP | Location timestamp            |
| latitud     | DECIMAL   | GPS latitude coordinate       |
| longitud    | DECIMAL   | GPS longitude coordinate      |
| es_manual   | BOOLEAN   | Whether entered manually      |

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

| Source Table  | Target Table      | Relationship Type             |
| ------------- | ----------------- | ----------------------------- |
| TipoDocumento | Tercero           | One-to-Many                   |
| TipoDocumento | Conductor         | One-to-Many                   |
| Eps           | Conductor         | One-to-Many                   |
| FondoPensiones| Conductor         | One-to-Many                   |
| FondoCesantias| Conductor         | One-to-Many                   |
| Tercero       | Vehiculo          | One-to-Many (if provider)     |
| Tercero       | Contrato          | One-to-Many (if client)       |
| Contrato      | Servicio          | One-to-Many                   |
| Vehiculo      | Servicio          | One-to-Many                   |
| Conductor     | Servicio          | One-to-Many                   |
| Factura       | Servicio          | One-to-Many                   |
| TipoNovedad   | NovedadServicio   | One-to-Many                   |
| Servicio      | NovedadServicio   | One-to-Many                   |
| Servicio      | FUEC              | One-to-One                    |
| Vehiculo      | UbicacionVehiculo | One-to-Many                   |
