# Navigation Map by Role - SGTE

Document describing the application's navigation structure for each role in the system, grouping roles when they share the same experience. The canonical groups are the ones defined in `resources/js/components/app-sidebar.tsx`.

## Access summary by role

| Module / View                          | Administrador | Operación | Conductor | Contabilidad |
| -------------------------------------- | :-----------: | :-------: | :-------: | :----------: |
| Login                                  |       ✓       |     ✓     |     ✓     |      ✓       |
| Panel (dashboard)                      |       ✓       |     ✓     | → /driver |      ✓       |
| Producción → Servicios                 |       ✓       |     ✓     |     -     |      ✓ (RO)  |
| Producción → Planificador (Gantt)      |       ✓       |     ✓     |     -     |      -       |
| Producción → Resumen del Día           |       ✓       |     ✓     |     -     |      ✓ (RO)  |
| Producción → Calendario                |       ✓       |     ✓     |     -     |      ✓ (RO)  |
| Producción → Novedades                 |       ✓       |     ✓     |     -     |      -       |
| Gestión → Vehículos                    |       ✓       |     ✓     |     -     |      -       |
| Gestión → Conductores                  |       ✓       |     ✓     |     -     |      -       |
| Gestión → Terceros                     |       ✓       |     ✓     |     -     |      -       |
| Gestión → Contratos                    |       ✓       |     ✓     |     -     |      -       |
| Facturación → Facturas                 |       ✓       |     -     |     -     |      ✓       |
| Administración → Usuarios              |       ✓       |     -     |     -     |      -       |
| Administración → Auditoría             |       ✓       |     -     |     -     |      -       |
| FUEC (scaffolded stub)                 |       ✓       |     -     |     -     |      -       |
| GPS → Ubicaciones (scaffolded stub)    |       ✓       |     -     |     -     |      -       |
| Catálogos → Tipos de Documento         |       ✓       |     ✓     |     -     |      -       |
| Catálogos → EPS                        |       ✓       |     ✓     |     -     |      -       |
| Catálogos → Fondos de Pensiones        |       ✓       |     ✓     |     -     |      -       |
| Catálogos → Fondos de Cesantías        |       ✓       |     ✓     |     -     |      -       |
| Catálogos → Tipos de Novedad           |       ✓       |     ✓     |     -     |      -       |
| Driver portal (/driver) — Mis Servicios |      -       |     -     |     ✓     |      -       |
| Registrar inicio/fin (driver cards)    |       -       |     -     |     ✓     |      -       |
| Registrar Novedad (from /driver card)  |       ✓       |     ✓     |     ✓     |      -       |

---

## 1. General navigation (all roles)

All users access the system through the same login screen. After authenticating, the system redirects the user to the main view corresponding to their role.

```plantuml
@startuml
!theme plain
title Flujo General de Acceso

[*] --> Login

Login --> DashboardOperativo : Administrador / Operación
Login --> VistaCondutor : Conductor
Login --> DashboardContable : Contabilidad

state "Dashboard Operativo" as DashboardOperativo {
  [*] --> CalendarioAnual
}

state "Mis Servicios" as VistaCondutor {
  [*] --> ListaServiciosHoy
}

state "Dashboard Contable" as DashboardContable {
  [*] --> CalendarioAnualRO
}

@enduml
```

---

## 2. Administrador and Operación

These two roles share the operational navigation. **Administrador** additionally has the **Administración** group (Usuarios + Auditoría), the **Facturación** group, and the scaffolded-stub groups **FUEC** and **GPS**. Operación is limited to Producción + Gestión + Catálogos.

### Sidebar menu

| Group → item                           | Administrador | Operación |
| -------------------------------------- | :-----------: | :-------: |
| Panel (top-level link)                 |       ✓       |     ✓     |
| Producción → Servicios                 |       ✓       |     ✓     |
| Producción → Planificador              |       ✓       |     ✓     |
| Producción → Resumen del Día           |       ✓       |     ✓     |
| Producción → Calendario                |       ✓       |     ✓     |
| Producción → Novedades                 |       ✓       |     ✓     |
| Gestión → Vehículos                    |       ✓       |     ✓     |
| Gestión → Conductores                  |       ✓       |     ✓     |
| Gestión → Terceros                     |       ✓       |     ✓     |
| Gestión → Contratos                    |       ✓       |     ✓     |
| Facturación → Facturas                 |       ✓       |     -     |
| Administración → Usuarios              |       ✓       |     -     |
| Administración → Auditoría             |       ✓       |     -     |
| FUEC → Documentos FUEC (stub)          |       ✓       |     -     |
| GPS → Ubicaciones (stub)               |       ✓       |     -     |
| Catálogos → Tipos de Documento         |       ✓       |     ✓     |
| Catálogos → EPS                        |       ✓       |     ✓     |
| Catálogos → Fondos de Pensiones        |       ✓       |     ✓     |
| Catálogos → Fondos de Cesantías        |       ✓       |     ✓     |
| Catálogos → Tipos de Novedad           |       ✓       |     ✓     |

### Full navigation map

```plantuml
@startuml
!theme plain
title Navegación - Administrador / Operación
skinparam state {
  BackgroundColor<<admin>> #FFF3E0
  BorderColor<<admin>> #FF9800
}

[*] --> Login
Login --> Dashboard

state "Producción (Dashboard)" as Dashboard {
  [*] --> CalendarioAnual
  CalendarioAnual --> VistaMes : Click en mes\n(/day-statuses/{year}/{month})
  VistaMes --> VistaMes : Prev/Next mes\n(flechas)
  VistaMes --> CalendarioAnual : Click titulo mes\n(/day-statuses/{year})
  VistaMes --> ServiciosDia : Click en dia\n(?selectedDay={day})
  ServiciosDia --> GanttDiario : Link a servicio
  VistaMes --> GanttDiario
  VistaMes --> ResumenDia
}

state "Planificador Gantt" as GanttDiario {
  [*] --> VistaFlota
  VistaFlota --> FormularioServicio : Click en\nbarra/celda
  FormularioServicio --> VistaFlota : Guardar /\nCancelar
  FormularioServicio --> RegistrarNovedad : + Novedad
  RegistrarNovedad --> FormularioServicio : Guardar
  FormularioServicio --> GenerarFUEC : FUEC\n(opcional)
}

state "Resumen del Día" as ResumenDia {
  [*] --> TablaServicios
  TablaServicios --> EjecutarDia : Todos cerrados
  TablaServicios --> ExportarResumen
}

state "Administración (solo Admin)" as Administracion <<admin>> {
  [*] --> GestionVehiculos
  [*] --> GestionConductores
  [*] --> GestionTerceros
  [*] --> GestionContratos
  GestionVehiculos --> DetalleVehiculo
  GestionConductores --> DetalleConductor
  GestionTerceros --> DetalleTercero
  GestionContratos --> DetalleContrato
}

state "Facturación (solo Admin)" as Facturacion <<admin>> {
  [*] --> ServiciosEjecutados
  ServiciosEjecutados --> AsociarFactura
  AsociarFactura --> GenerarPDF
}

state "Auditoría (solo Admin)" as Auditoria <<admin>> {
  [*] --> LogCambios
}

Dashboard --> Administracion : Menú lateral\n(solo Admin)
Dashboard --> Facturacion : Menú lateral\n(solo Admin)
Dashboard --> Auditoria : Menú lateral\n(solo Admin)

note right of Administracion
  Los módulos marcados
  ""(solo Admin)"" no son
  visibles para Operación
end note

@enduml
```

### Main flow: Calendario → Gantt → Servicio

```plantuml
@startuml
!theme plain
title Flujo Operativo Principal

|Calendario|
start
:Ver calendario anual\n(/day-statuses/{year}\n12 meses con colores de estado);
:Click en un mes;
:Ver vista mensual\n(/day-statuses/{year}/{month}\ndías con indicadores,\nflechas prev/next mes);
:Click en un día\n(?selectedDay={day});
:Ver servicios del día\n(tabla inline bajo calendario);

|Día Seleccionado|
if (¿Qué vista?) then (Planificador)
  :Abrir Gantt diario;
  |Gantt|
  :Ver flota con barras de servicio;
  :Filtrar por ciudad;
  if (¿Acción?) then (Celda vacía)
    :Abrir formulario\nnuevo servicio;
  else (Barra existente)
    :Abrir formulario\nedición servicio;
  endif
  :Completar formulario;
  :Guardar servicio;
  |Día Seleccionado|
else (Resumen)
  :Ver resumen del día;
  :Tabla de servicios\ncon estados;
  if (¿Todos cerrados?) then (sí)
    :Ejecutar día;
    :Estado → EJECUTADO\n(bloqueado);
  else (no)
    :Servicios pendientes\nde cierre;
  endif
endif

stop

@enduml
```

### Behavior differences by role

| Context                        | Administrador                            | Operación                    |
| ------------------------------ | ---------------------------------------- | ---------------------------- |
| Service on PROYECTADO day      | Create / Edit / Delete                   | Create / Edit / Delete       |
| Service on EJECUTADO day       | Edit with mandatory justification        | Read-only                    |
| Novedades                      | Register from the service form           | Register from the form       |
| Administración menu            | Visible (Vehículos, Conductores, etc.)   | Not visible                  |
| Facturación menu               | Visible                                  | Not visible                  |
| Auditoría menu                 | Visible                                  | Not visible                  |

---

## 3. Conductor

The driver has a simplified, mobile-oriented interface. They do not access the calendar or the Gantt. Their main (and only) view is the list of services assigned to them for the current day, at `/driver`. When a driver logs in, `Panel` redirects to `/driver`.

### Menu

| Section                                          | Access |
| ------------------------------------------------ | :----: |
| Panel → /driver (redirect)                       |   ✓    |
| Conductor → Mis Servicios                        |   ✓    |

> **Not implemented yet** (present in earlier mockups only): Mapa GPS, Notificaciones, Mi Perfil, dedicated per-service detail page. The service cards on `/driver` are themselves the detail view. Incidents are registered via `GET /service-incidents/create?service_id=X` from the "Registrar Novedad" button on each card.

### Navigation map

```plantuml
@startuml
!theme plain
title Navegación - Conductor (Mobile-First)

[*] --> Login
Login --> MisServicios

state "Mis Servicios del Día" as MisServicios {
  [*] --> ListaServicios
  ListaServicios : Servicios asignados\npara hoy
  ListaServicios --> DetalleServicio : Tap en servicio
}

state "Detalle del Servicio" as DetalleServicio {
  [*] --> InfoServicio
  InfoServicio : Placa, ruta,\nhorario, tercero
  InfoServicio --> ConfirmarInicio : Iniciar servicio
  ConfirmarInicio --> EnServicio
  EnServicio --> RegistrarNovedad : + Novedad
  RegistrarNovedad --> EnServicio
  EnServicio --> ConfirmarFin : Finalizar servicio
  ConfirmarFin --> ServicioCerrado
}

state "Registrar Novedad" as RegistrarNovedad {
  [*] --> FormNovedad
  FormNovedad : Tipo, descripción,\n¿afecta facturación?
}

@enduml
```

> **Note:** Earlier mockups showed `Mapa GPS` and `Notificaciones` as driver-side menu entries. Neither is implemented in the current driver portal — GPS capture is server-scaffolded only and notifications are delivered by email, not surfaced as an in-app inbox.

### Driver flow during a service

```plantuml
@startuml
!theme plain
title Flujo del Conductor - Ejecución de Servicio

start
:Recibe notificación de\nservicio asignado (email);

:Ingresa al sistema;
:Ver lista de servicios del día;
:Seleccionar servicio;

:Ver detalle del servicio\n(ruta, horario, tercero);

:Presionar "Iniciar Servicio";
:Sistema registra hora_inicio_real;

while (¿En ruta?) is (sí)
  if (¿Novedad?) then (sí)
    :Registrar novedad;
    :Tipo + Descripción;
    if (¿Afecta facturación?) then (sí)
      :Ingresar valor\nadicional/descuento;
    else (no)
    endif
  else (no)
  endif
  if (¿GPS habilitado?) then (sí)
    :Ubicación se actualiza\nautomáticamente;
  else (no)
    :Opción: ingresar\ncoordenadas manual;
  endif
endwhile (llegó a destino)

:Presionar "Finalizar Servicio";
:Sistema registra hora_fin_real;
:Sistema calcula duración real;

stop

@enduml
```

---

## 4. Contabilidad

The Contabilidad role accesses the calendar in read mode to navigate to executed days. Its focus is billing and reviewing finalized services. It can edit accounting fields of services on executed days.

### Sidebar menu

Contabilidad sees a subset of the canonical sidebar (Producción + Gestión read-only + Facturación). It does **not** see Catálogos, Administración, FUEC, or GPS. There is no dedicated "Reportes" or "Notificaciones" sidebar entry — these were on earlier mockups but are not implemented in the live sidebar.

| Group → item                         | Access  |
| ------------------------------------ | :-----: |
| Panel (top-level link)               |   ✓     |
| Producción → Servicios               |   ✓ RO  |
| Producción → Resumen del Día         |   ✓ RO  |
| Producción → Calendario              |   ✓ RO  |
| Gestión → Vehículos                  |   👁 RO  |
| Gestión → Conductores                |   👁 RO  |
| Gestión → Terceros                   |   👁 RO  |
| Gestión → Contratos                  |   👁 RO  |
| Facturación → Facturas               |   ✓     |

### Navigation map

```plantuml
@startuml
!theme plain
title Navegación - Contabilidad

[*] --> Login
Login --> DashboardContable

state "Dashboard (Lectura)" as DashboardContable {
  [*] --> CalendarioAnual
  CalendarioAnual --> VistaMes : Click en mes\n(/day-statuses/{year}/{month})
  VistaMes --> VistaMes : Prev/Next mes
  VistaMes --> CalendarioAnual : Click titulo mes
  VistaMes --> ServiciosDiaRO : Click en dia\n(?selectedDay={day})
  ServiciosDiaRO --> ResumenDiaRO : Ver resumen
}

state "Resumen del Día (lectura)" as ResumenDiaRO {
  [*] --> TablaServiciosRO
  TablaServiciosRO : Servicios del día\n(solo lectura operativa)
  TablaServiciosRO --> DetalleServicioContable : Click en servicio\nejecutado
}

state "Detalle Servicio (campos contables)" as DetalleServicioContable {
  [*] --> VistaServicio
  VistaServicio : Datos operativos (lectura)\nDatos contables (edición)
  VistaServicio --> AsociarFactura : Vincular factura
  VistaServicio --> VerNovedades : Ver novedades\ncon impacto
}

state "Facturación" as Facturacion {
  [*] --> ListaFacturas
  ListaFacturas --> NuevaFactura : + Nueva factura
  NuevaFactura --> SeleccionarServicios : Seleccionar servicios\ndel mismo tercero
  SeleccionarServicios --> CalcularTotal : Sistema calcula total\n(incluye novedades)
  CalcularTotal --> GenerarPDF
  ListaFacturas --> DetalleFactura : Click en factura
}

state "Reportes" as Reportes {
  [*] --> ReportesContables
  ReportesContables : Servicios por tercero,\nfacturación, novedades
}

state "Notificaciones" as Notificaciones {
  [*] --> ListaNotifs
  ListaNotifs : Días ejecutados,\nnovedades con impacto
}

DashboardContable --> Facturacion : Menú lateral
DashboardContable --> Reportes : Menú lateral
DashboardContable --> Notificaciones : Menú lateral

@enduml
```

### Billing flow

```plantuml
@startuml
!theme plain
title Flujo de Facturación - Contabilidad

start
:Recibe notificación:\n"Día ejecutado, listo para facturar";

:Navegar al calendario;
:Seleccionar día ejecutado (verde);
:Ver resumen del día;

if (¿Revisar novedades?) then (sí)
  :Revisar novedades con\nimpacto en facturación;
  :Verificar valores\nadicionales/descuentos;
else (no)
endif

:Ir a módulo de Facturación;
:Crear nueva factura;

:Seleccionar tercero;
:Sistema muestra servicios\nejecutados sin factura;

:Seleccionar servicios a incluir;
:Sistema calcula valor total\n(base + novedades);

:Ingresar número de factura;
:Ingresar fecha de emisión;
:Establecer forma de pago;

:Guardar factura;
:Generar PDF (informativo);

stop

@enduml
```

---

## 5. Sidebar menu structure

Consolidated representation of the canonical sidebar in `resources/js/components/app-sidebar.tsx`, visible to each role.

```plantuml
@startsalt
{
  {T
    + <b>SGTE - Menú Principal</b>
    ++ <&home> Panel                          | <color:green>A</color> <color:blue>O</color> <color:red>Co</color> <color:gray>C$</color>
    ++ <&calendar> Producción                 | <color:green>A</color> <color:blue>O</color>
    +++ Servicios                              | <color:green>A</color> <color:blue>O</color>
    +++ Planificador                           | <color:green>A</color> <color:blue>O</color>
    +++ Resumen del Día                        | <color:green>A</color> <color:blue>O</color>
    +++ Calendario                             | <color:green>A</color> <color:blue>O</color>
    +++ Novedades                              | <color:green>A</color> <color:blue>O</color>
    ++ <&wrench> Gestión                      | <color:green>A</color> <color:blue>O</color>
    +++ Vehículos                              | <color:green>A</color> <color:blue>O</color>
    +++ Conductores                            | <color:green>A</color> <color:blue>O</color>
    +++ Terceros                               | <color:green>A</color> <color:blue>O</color>
    +++ Contratos                              | <color:green>A</color> <color:blue>O</color>
    ++ <&dollar> Facturación                  | <color:green>A</color> <color:gray>C$</color>
    +++ Facturas                               | <color:green>A</color> <color:gray>C$</color>
    ++ <&shield> Administración               | <color:green>A</color>
    +++ Usuarios                               | <color:green>A</color>
    +++ Auditoría                              | <color:green>A</color>
    ++ <&document> FUEC (stub)                | <color:green>A</color>
    +++ Documentos FUEC                        | <color:green>A</color>
    ++ <&map-marker> GPS (stub)               | <color:green>A</color>
    +++ Ubicaciones                            | <color:green>A</color>
    ++ <&list> Catálogos                      | <color:green>A</color> <color:blue>O</color>
    +++ Tipos de Documento                     | <color:green>A</color> <color:blue>O</color>
    +++ EPS                                    | <color:green>A</color> <color:blue>O</color>
    +++ Fondos de Pensiones                    | <color:green>A</color> <color:blue>O</color>
    +++ Fondos de Cesantías                    | <color:green>A</color> <color:blue>O</color>
    +++ Tipos de Novedad                       | <color:green>A</color> <color:blue>O</color>
    ++ <&truck> Conductor (driver-only)       | <color:red>Co</color>
    +++ Mis Servicios (/driver)                | <color:red>Co</color>
  }
  ---
  <color:green>A</color> = Admin  |  <color:blue>O</color> = Operación  |  <color:red>Co</color> = Conductor  |  <color:gray>C$</color> = Contabilidad
}
@endsalt
```

---

## Reference

- [SRS - Section 3: Navigation Architecture](SRS.md#3-navigation-architecture)
- [SRS - Section 7: Roles and Permissions](SRS.md#7-roles-and-permissions)
