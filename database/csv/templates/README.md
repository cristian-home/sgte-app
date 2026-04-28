# Plantillas de importación masiva

Plantillas estáticas servidas por el endpoint `GET /admin/imports/templates/{type}` para que el super admin descargue, llene con los datos del cliente, y suba a través de `/admin/imports/create`.

## Convenciones generales

- **Encoding**: UTF-8 (con o sin BOM, ambos funcionan).
- **Separador**: coma (`,`). Si el cliente exporta con `;` desde Excel ES, `simple-excel` auto-detecta.
- **Fechas**: formato ISO `YYYY-MM-DD`.
- **Booleanos**: `1` o `0` (también acepta `true`/`false`).
- **Vacíos**: dejar la celda vacía. No escribir `null` ni `NULL`.

## `users.csv` v1 (2026-04-27)

| Columna | Tipo | Requerida | Notas |
|---|---|---|---|
| email | string | sí | clave natural; debe ser único entre todos los usuarios |
| name | string | sí | máximo 100 caracteres |
| role | enum | sí | uno de: `admin`, `operator`, `driver`, `accounting` (super_admin no permitido) |
| password | string | no | si vacío, se autogenera y el usuario debe resetear en el primer login |

Cuando `password` está vacío, el sistema asigna `must_change_password=true` y el middleware redirige al usuario a `/password/change` la primera vez que entra.

## `third_parties.csv` v1 (2026-04-27)

| Columna | Tipo | Requerida | Notas |
|---|---|---|---|
| document_type_code | string | sí | FK a `document_types.code` (ej. `CC`, `NIT`) |
| identification_number | string | sí | clave natural |
| is_natural_person | bool | sí | 1 = persona natural, 0 = persona jurídica |
| first_name | string | no | obligatorio para persona natural |
| second_name | string | no | |
| first_lastname | string | no | obligatorio para persona natural |
| second_lastname | string | no | |
| company_name | string | no | obligatorio para persona jurídica |
| trade_name | string | no | |
| address | string | sí | |
| phone | string | sí | |
| email | string | sí | |
| is_customer | bool | sí | 1 = cliente |
| is_provider | bool | sí | 1 = proveedor (puede ser ambos) |
| municipality_code | string | no | FK a `municipalities.code` (DANE 5 dígitos) |

## `drivers.csv` v1 (2026-04-27)

| Columna | Tipo | Requerida | Notas |
|---|---|---|---|
| document_type_code | string | sí | FK a `document_types.code` |
| identification_number | string | sí | clave natural |
| first_name | string | sí | |
| second_name | string | no | |
| first_lastname | string | sí | |
| second_lastname | string | no | |
| address | string | sí | |
| phone | string | sí | |
| email | string | sí | |
| license_category | enum | sí | uno de: `C1`, `C2`, `C3` |
| license_due_date | date | sí | YYYY-MM-DD |
| eps_code | string | sí | FK a `eps.code` |
| pension_fund_code | string | sí | FK a `pension_funds.code` |
| severance_fund_code | string | sí | FK a `severance_funds.code` |
| has_social_security | bool | sí | 1/0 |
| user_email | string | no | si presente, vincula al usuario; el user debe ya existir y tener rol `driver` |
| municipality_code | string | no | FK a `municipalities.code` |

## `vehicles.csv` v1 (2026-04-27)

| Columna | Tipo | Requerida | Notas |
|---|---|---|---|
| plate | string | sí | clave natural; exactamente 6 caracteres (se normaliza a uppercase) |
| internal_code | string | sí | hasta 20 caracteres |
| mobile_number | string | sí | |
| type | enum | sí | uno de: `bus`, `buseta`, `van`, `automobile` |
| brand | string | sí | |
| line | string | sí | |
| model_year | integer | sí | entre 1980 y 2100 |
| engine_number | string | sí | |
| chassis_number | string | sí | |
| capacity | integer | sí | número de pasajeros, mínimo 1 |
| is_third_party | bool | sí | 1/0 |
| third_party_identification | string | condicional | requerido si `is_third_party=1`; FK a `third_parties.identification_number` |
| soat_due_date | date | sí | YYYY-MM-DD |
| rtm_due_date | date | sí | YYYY-MM-DD |
| operation_card_due_date | date | sí | YYYY-MM-DD |
| municipality_code | string | no | FK a `municipalities.code` |

## Catálogos de referencia

Antes de llenar las plantillas, descargue los catálogos vigentes desde la UI (`/admin/imports`) para conocer los códigos exactos:

- **EPS** — `GET /admin/imports/reference/eps`
- **Fondos de Pensiones** — `GET /admin/imports/reference/pension-funds`
- **Fondos de Cesantías** — `GET /admin/imports/reference/severance-funds`
- **Municipios DIVIPOLA** — `GET /admin/imports/reference/municipalities`
- **Departamentos** — `GET /admin/imports/reference/departments`
- **Tipos de Documento** — `GET /admin/imports/reference/document-types`
- **Tipos de Novedad** — `GET /admin/imports/reference/incident-types`

## Versionado

Cuando se modifica una plantilla (agregar/quitar/renombrar columna):

1. Editar el CSV correspondiente.
2. Bumpear nota en este README: `vN (YYYY-MM-DD): cambio descrito`.
3. La validación de header del importer rechaza archivos con header viejo, forzando re-descarga.

## Notas conocidas

- **XLSX con múltiples sheets**: leemos solo la primera.
- **Celdas merged en el header**: rompen la validación; usar la plantilla sin modificar el header.
- **Fechas en serial Excel**: si Excel exportó la fecha como número crudo en lugar de string `YYYY-MM-DD`, la validación falla y la fila va a `errors.csv`.
