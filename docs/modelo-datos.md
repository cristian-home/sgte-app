# Tablas de Base de Datos - SGTE (Versión Actualizada)

Este archivo contiene el modelo de datos actualizado basado en las notas del cliente y reuniones.

---

## Cambios Principales respecto a la versión anterior:

1. **Nueva entidad TERCERO** - Unifica clientes y proveedores (personas naturales y jurídicas)
2. **Nueva entidad TIPO_DOCUMENTO** - Catálogo de tipos de identificación
3. **Nueva tabla NOVEDAD_SERVICIO** - Para registrar novedades/incidencias
4. **Campo COD en VEHICULO** - Código interno combinado con centro de costos
5. **Campo CIUDAD en VEHICULO** - Para filtrar en el Gantt
6. **Rol CONTABILIDAD agregado** - Con permisos específicos
7. **GPS opcional** - Ubicación ya no es obligatoria

---

## Tabla 1: TipoDocumento

Catálogo de tipos de documento de identificación.

| Campo               | Tipo        | Descripción                         |
| ------------------- | ----------- | ----------------------------------- |
| id                  | UUID        | Primary Key                         |
| codigo              | VARCHAR(10) | Código del tipo (CC, NIT, CE, etc.) |
| nombre              | VARCHAR     | Nombre del tipo                     |
| es_persona_natural  | BOOLEAN     | Si aplica a personas naturales      |
| es_persona_juridica | BOOLEAN     | Si aplica a personas jurídicas      |

---

## Tabla 2: Tercero

Unifica clientes, proveedores y cualquier persona natural o jurídica (excepto conductores).

| Campo                 | Tipo    | Descripción                              |
| --------------------- | ------- | ---------------------------------------- |
| id                    | UUID    | Primary Key                              |
| tipo_documento_id     | UUID    | Foreign Key → TipoDocumento              |
| numero_identificacion | VARCHAR | Número de identificación                 |
| es_persona_natural    | BOOLEAN | Tipo de persona                          |
| primer_nombre         | VARCHAR | Solo personas naturales                  |
| segundo_nombre        | VARCHAR | Solo personas naturales                  |
| primer_apellido       | VARCHAR | Solo personas naturales                  |
| segundo_apellido      | VARCHAR | Solo personas naturales                  |
| razon_social          | VARCHAR | Solo personas jurídicas                  |
| nombre_comercial      | VARCHAR | Solo personas jurídicas                  |
| ciudad                | VARCHAR | Ciudad de ubicación                      |
| direccion             | VARCHAR | Dirección principal                      |
| telefono              | VARCHAR | Teléfono de contacto                     |
| email                 | VARCHAR | Correo electrónico                       |
| es_cliente            | BOOLEAN | Si es cliente                            |
| es_proveedor          | BOOLEAN | Si es proveedor (vehículos tercerizados) |
| activo                | BOOLEAN | Estado activo/inactivo                   |

---

## Tabla 3: Vehiculo

| Campo                  | Tipo       | Descripción                                  |
| ---------------------- | ---------- | -------------------------------------------- |
| id                     | UUID       | Primary Key                                  |
| cod                    | VARCHAR    | Código interno (18 = tercerizado)            |
| placa                  | VARCHAR(6) | Placa del vehículo                           |
| movil                  | VARCHAR    | Número de móvil                              |
| marca                  | VARCHAR    | Marca del vehículo                           |
| linea                  | VARCHAR    | Línea del vehículo                           |
| modelo                 | INT        | Año del modelo                               |
| tipo                   | ENUM       | Bus, Buseta, Van, Automóvil                  |
| motor                  | VARCHAR    | Número de motor                              |
| chasis                 | VARCHAR    | Número de chasis                             |
| capacidad              | INT        | Capacidad de pasajeros                       |
| ciudad                 | VARCHAR    | Ciudad de ubicación (para filtro Gantt)      |
| es_tercerizado         | BOOLEAN    | Si es vehículo de tercero                    |
| tercero_id             | UUID       | Foreign Key → Tercero (si es tercerizado)    |
| soat_vencimiento       | DATE       | Fecha de vencimiento del SOAT                |
| rtm_vencimiento        | DATE       | Fecha de vencimiento de la RTM               |
| tarjeta_op_vencimiento | DATE       | Fecha de vencimiento de Tarjeta de Operación |
| estado                 | ENUM       | Estado del vehículo                          |

---

## Tabla 1b: Eps

Catálogo de Entidades Promotoras de Salud (EPS) de Colombia.

| Campo  | Tipo        | Descripción          |
| ------ | ----------- | -------------------- |
| id     | UUID        | Primary Key          |
| codigo | VARCHAR(10) | Código de la EPS     |
| nombre | VARCHAR     | Nombre de la EPS     |

---

## Tabla 1c: FondoPensiones

Catálogo de Fondos de Pensiones de Colombia.

| Campo  | Tipo        | Descripción                  |
| ------ | ----------- | ---------------------------- |
| id     | UUID        | Primary Key                  |
| codigo | VARCHAR(10) | Código del fondo de pensiones|
| nombre | VARCHAR     | Nombre del fondo de pensiones|

---

## Tabla 1d: FondoCesantias

Catálogo de Fondos de Cesantías de Colombia.

| Campo  | Tipo        | Descripción                    |
| ------ | ----------- | ------------------------------ |
| id     | UUID        | Primary Key                    |
| codigo | VARCHAR(10) | Código del fondo de cesantías  |
| nombre | VARCHAR     | Nombre del fondo de cesantías  |

---

## Tabla 4: Conductor

| Campo                    | Tipo    | Descripción                         |
| ------------------------ | ------- | ----------------------------------- |
| id                       | UUID    | Primary Key                         |
| tipo_documento_id        | UUID    | Foreign Key → TipoDocumento         |
| numero_identificacion    | VARCHAR | Número de documento                 |
| primer_nombre            | VARCHAR | Primer nombre                       |
| segundo_nombre           | VARCHAR | Segundo nombre                      |
| primer_apellido          | VARCHAR | Primer apellido                     |
| segundo_apellido         | VARCHAR | Segundo apellido                    |
| ciudad                   | VARCHAR | Ciudad de residencia                |
| direccion                | VARCHAR | Dirección principal                 |
| telefono                 | VARCHAR | Teléfono de contacto                |
| email                    | VARCHAR | Correo electrónico                  |
| categoria_licencia       | VARCHAR | Categoría de licencia               |
| licencia_vencimiento     | DATE    | Fecha de vencimiento de la licencia |
| eps_id                   | UUID    | Foreign Key → Eps                   |
| fondo_pensiones_id       | UUID    | Foreign Key → FondoPensiones        |
| fondo_cesantias_id       | UUID    | Foreign Key → FondoCesantias        |
| seguridad_social_vigente | BOOLEAN | Estado de seguridad social          |
| activo                   | BOOLEAN | Si está activo en la empresa        |

---

## Tabla 5: Contrato

| Campo            | Tipo    | Descripción                            |
| ---------------- | ------- | -------------------------------------- |
| id               | UUID    | Primary Key                            |
| numero           | VARCHAR | Número de contrato                     |
| tercero_id       | UUID    | Foreign Key → Tercero (cliente)        |
| objeto           | ENUM    | Empresarial, Turismo, Salud, Ocasional |
| fecha_inicio     | DATE    | Fecha de inicio de vigencia            |
| fecha_fin        | DATE    | Fecha de fin de vigencia               |
| ruta_descripcion | TEXT    | Descripción de ruta autorizada         |
| es_generico      | BOOLEAN | Si es contrato genérico temporal       |

---

## Tabla 6: Servicio

| Campo            | Tipo     | Descripción                                       |
| ---------------- | -------- | ------------------------------------------------- |
| id               | UUID     | Primary Key                                       |
| contrato_id      | UUID     | Foreign Key → Contrato                            |
| vehiculo_id      | UUID     | Foreign Key → Vehiculo                            |
| conductor_id     | UUID     | Foreign Key → Conductor (nullable si tercerizado) |
| factura_id       | UUID     | Foreign Key → Factura (nullable)                  |
| fecha            | DATE     | Fecha del servicio                                |
| origen           | VARCHAR  | Origen del recorrido                              |
| destino          | VARCHAR  | Destino del recorrido                             |
| hora_inicio_plan | TIME     | Hora inicio planificada                           |
| duracion_plan    | INTERVAL | Duración estimada                                 |
| hora_inicio_real | TIME     | Hora inicio real                                  |
| hora_fin_real    | TIME     | Hora final real                                   |
| valor_unitario   | DECIMAL  | Valor unitario del servicio                       |
| cantidad         | INT      | Cantidad                                          |
| grupo            | VARCHAR  | Categoría de facturación (nullable)               |
| forma_pago       | ENUM     | Forma de pago                                     |
| estado_servicio  | ENUM     | ABIERTO/CERRADO                                   |

---

## Tabla 1e: TipoNovedad

Catálogo configurable de tipos de novedad/incidencia. Reemplaza el ENUM anterior para permitir gestión operativa sin cambios de código.

| Campo                        | Tipo        | Descripción                                          |
| ---------------------------- | ----------- | ---------------------------------------------------- |
| id                           | BIGINT      | Primary Key                                          |
| codigo                       | VARCHAR(10) | Código único (DELAY, ACCIDENT, BREAKDOWN, etc.)      |
| nombre                       | VARCHAR(100)| Nombre en español (Retraso, Accidente, Avería, etc.) |
| severidad                    | VARCHAR(20) | Severidad: informational, minor, major (ENUM PHP)    |
| afecta_facturacion_por_defecto | BOOLEAN   | Valor por defecto para afecta_facturación            |
| descripcion                  | TEXT        | Descripción opcional del tipo (nullable)             |
| soft_delete                  | TIMESTAMP   | Soft delete para desactivar sin perder histórico     |

Registros semilla:

| Código    | Nombre                | Severidad     | Afecta Facturación |
| --------- | --------------------- | ------------- | :----------------: |
| DELAY     | Retraso               | minor         |         No         |
| ACCIDENT  | Accidente             | major         |         Sí         |
| BREAKDOWN | Avería                | major         |         Sí         |
| TRAFFIC   | Tráfico               | informational |         No         |
| WEATHER   | Clima                 | minor         |         No         |
| NO_SHOW   | Cliente No Presentado | minor         |         Sí         |
| OTHER     | Otro                  | informational |         No         |

---

## Tabla 7: NovedadServicio

Registra novedades o incidencias de servicios.

| Campo              | Tipo      | Descripción                                |
| ------------------ | --------- | ------------------------------------------ |
| id                 | BIGINT    | Primary Key                                |
| servicio_id        | BIGINT    | Foreign Key → Servicio                     |
| tipo_novedad_id    | BIGINT    | Foreign Key → TipoNovedad (NOT NULL)       |
| descripcion        | TEXT      | Descripción de la novedad                  |
| registrado_por     | BIGINT    | Foreign Key → Usuario (quién registró)     |
| es_conductor       | BOOLEAN   | Si fue registrado por conductor            |
| fecha_registro     | TIMESTAMP | Fecha y hora del registro                  |
| afecta_facturacion | BOOLEAN   | Si afecta el valor de facturación          |
| valor_adicional    | DECIMAL   | Valor adicional por la novedad             |

---

## Tabla 8: FUEC

| Campo            | Tipo      | Descripción                 |
| ---------------- | --------- | --------------------------- |
| id               | UUID      | Primary Key                 |
| servicio_id      | UUID      | Foreign Key → Servicio      |
| consecutivo      | INT       | Número consecutivo del FUEC |
| fecha_generacion | TIMESTAMP | Fecha de generación         |
| codigo_qr        | VARCHAR   | Código QR de verificación   |
| estado           | ENUM      | Estado del documento        |
| pdf_url          | VARCHAR   | URL del PDF generado        |

> **Nota:** El módulo FUEC es opcional y puede activarse/desactivarse.

---

## Tabla 9: EstadoDia

| Campo           | Tipo      | Descripción                              |
| --------------- | --------- | ---------------------------------------- |
| id              | UUID      | Primary Key                              |
| fecha           | DATE      | Fecha del día (UNIQUE)                   |
| estado          | ENUM      | PROYECTADO/EJECUTADO                     |
| ejecutado_por   | UUID      | Foreign Key → User (nullable)            |
| ejecutado_fecha | TIMESTAMP | Fecha de ejecución (nullable)            |

---

## Tabla 10: Factura

Una factura puede agrupar múltiples servicios del mismo tercero.

| Campo          | Tipo    | Descripción               |
| -------------- | ------- | ------------------------- |
| id             | UUID    | Primary Key               |
| numero_factura | VARCHAR | Número de factura         |
| valor_total    | DECIMAL | Valor total de la factura |
| fecha_emision  | DATE    | Fecha de emisión          |
| estado_pago    | ENUM    | Estado de pago            |

---

## Tabla 11: UbicacionVehiculo

| Campo       | Tipo      | Descripción                  |
| ----------- | --------- | ---------------------------- |
| id          | UUID      | Primary Key                  |
| vehiculo_id | UUID      | Foreign Key → Vehiculo       |
| timestamp   | TIMESTAMP | Timestamp de la ubicación    |
| latitud     | DECIMAL   | Coordenada GPS latitud       |
| longitud    | DECIMAL   | Coordenada GPS longitud      |
| es_manual   | BOOLEAN   | Si fue ingresada manualmente |

> **Nota:** El uso de GPS es opcional (puede ser automático o manual).

---

## Roles y Permisos

Matriz de referencia para configurar `spatie/laravel-permission`:

| Función                              | Administrador | Operación | Conductor | Contabilidad |
| ------------------------------------ | :-----------: | :-------: | :-------: | :----------: |
| Gestionar vehículos                  |       ✓       |     -     |     -     |      -       |
| Gestionar conductores                |       ✓       |     -     |     -     |      -       |
| Gestionar contratos                  |       ✓       |     -     |     -     |      -       |
| Crear servicios                      |       ✓       |     ✓     |     -     |      -       |
| Editar servicios (proyectados)       |       ✓       |     ✓     |     -     |      -       |
| Editar servicios (ejecutados)        |       ✓       |     -     |     -     |      ✓       |
| Generar FUEC (opcional)              |       ✓       |     ✓     |     -     |      -       |
| Ejecutar día                         |       ✓       |     ✓     |     -     |      -       |
| Ver reportes                         |       ✓       |     ✓     |     -     |      ✓       |
| Ver servicios finalizados            |       ✓       |     -     |     -     |      ✓       |
| Generar facturas                     |       ✓       |     -     |     -     |      ✓       |
| Asociar servicios a facturas         |       ✓       |     -     |     -     |      ✓       |
| Registrar tiempos reales y novedades |       -       |     -     |     ✓     |      -       |
| Recibir notificaciones               |       ✓       |     ✓     |     ✓     |      ✓       |

---

## Tablas gestionadas por Laravel y paquetes

Las siguientes tablas son creadas y gestionadas automáticamente por el framework y paquetes de terceros:

| Tabla(s) | Paquete | Propósito |
| -------- | ------- | --------- |
| `users` | Laravel Auth (react-starter-kit) | Usuarios del sistema |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | spatie/laravel-permission | Roles y permisos |
| `activity_log` | spatie/laravel-activitylog | Log de auditoría |
| `notifications` | Laravel Notifications (canal database) | Notificaciones in-app y email |

> **Nota:** `NovedadServicio.registrado_por` y `EstadoDia.ejecutado_por` son FK a la tabla `users` de Laravel.

---

## Relaciones entre Tablas

| Tabla Origen  | Tabla Destino     | Tipo de Relación              |
| ------------- | ----------------- | ----------------------------- |
| TipoDocumento | Tercero           | One-to-Many                   |
| TipoDocumento | Conductor         | One-to-Many                   |
| Eps           | Conductor         | One-to-Many                   |
| FondoPensiones| Conductor         | One-to-Many                   |
| FondoCesantias| Conductor         | One-to-Many                   |
| Tercero       | Vehiculo          | One-to-Many (si es proveedor) |
| Tercero       | Contrato          | One-to-Many (si es cliente)   |
| Contrato      | Servicio          | One-to-Many                   |
| Vehiculo      | Servicio          | One-to-Many                   |
| Conductor     | Servicio          | One-to-Many                   |
| Factura       | Servicio          | One-to-Many                   |
| TipoNovedad   | NovedadServicio   | One-to-Many                   |
| Servicio      | NovedadServicio   | One-to-Many                   |
| Servicio      | FUEC              | One-to-One                    |
| Vehiculo      | UbicacionVehiculo | One-to-Many                   |
