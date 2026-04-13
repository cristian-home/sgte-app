# ASCII Mockups - Special Transport Management System (SGTE)

> ASCII representations of the application's main views.
> Fixed width: 100 characters. Sidebar: 24 chars. Content: 73 chars.
> No emojis in sidebar/header to avoid misalignment from variable widths.

---

## General Layout Structure

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Breadcrumbs: Produccion > Calendario > 2025                             │
│  SGTE                  ├─────────────────────────────────────────────────────────────────────────┤
│  Transporte Especial   │                                                                         │
│                        │  ###################################################################    │
│  PRODUCCION            │  ##                                                               ##    │
│  > Calendario        < │  ##                    CONTENIDO PRINCIPAL                        ##    │
│  > Gantt Diario        │  ##                                                               ##    │
│  > Resumen del Dia     │  ##   Aqui se renderiza la vista activa del menu lateral.         ##    │
│                        │  ##   El contenido ocupa todo el espacio disponible.              ##    │
│  ADMINISTRACION        │  ##                                                               ##    │
│  > Vehiculos           │  ##                                                               ##    │
│  > Conductores         │  ###################################################################    │
│  > Terceros            │                                                                         │
│  > Contratos           │                                                                         │
│                        │                                                                         │
│  FACTURACION           │                                                                         │
│  > Facturas            │                                                                         │
│  > Serv.Ejecutados     │                                                                         │
│                        │                                                                         │
│  OPCIONALES            │                                                                         │
│  > FUEC                │                                                                         │
│  > Mapa GPS            │                                                                         │
│                        │                                                                         │
│  ──────────────────    │                                                                         │
│  [U] Admin User        │                                                                         │
│                        │                                                                         │
├────────────────────────┴─────────────────────────────────────────────────────────────────────────┤
│  (c) 2025 SGTE - Sistema de Gestion de Transporte Especial                                       │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

**Sidebar:** Grouped sections (Produccion, Administracion, Facturacion, Opcionales).
The `<` indicator marks the active view. Footer shows the user and settings.

---

## 0. GENERAL DASHBOARD (Home Page)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Dashboard General                                                       │
│  SGTE                  │ ═══════════════════════════════════════════════════════════════════════ │
│  Transporte Especial   │                                                                         │
│                        │ ┌────────────────┐ ┌────────────────┐ ┌────────────────┐ ┌────────────┐ │
│  PRODUCCION            │ │ Vehiculos      │ │ Conductores    │ │ Serv. Hoy      │ │ Facturas   │ │
│  > Calendario          │ │    24          │ │    18          │ │    12          │ │ Pendientes │ │
│  > Gantt Diario        │ │ [ok 20] [!!4]  │ │ [ok 15] [!!3]  │ │ [Ab 8] [Ce 4]  │ │    5       │ │
│  > Resumen del Dia     │ └────────────────┘ └────────────────┘ └────────────────┘ └────────────┘ │
│                        │                                                                         │
│  ADMINISTRACION        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│  > Vehiculos           │ │ ALERTAS DE DOCUMENTOS                                 [Ver todas]   │ │
│  > Conductores         │ │ ─────────────────────────────────────────────────────────────────── │ │
│  > Terceros            │ │ [XX] SOAT Vencido   - ABC-123           Vencio: 05/10/2025          │ │
│  > Contratos           │ │ [!!] RTM por vencer - DEF-456           Vence en 12 dias            │ │
│                        │ │ [!!] Licencia       - Hernandez J.      Vence en 8 dias             │ │
│  FACTURACION           │ │ [!!] Tarjeta Op.    - GHI-789           Vence en 25 dias            │ │
│  > Facturas            │ └─────────────────────────────────────────────────────────────────────┘ │
│  > Serv.Ejecutados     │                                                                         │
│  OPCIONALES            │                                                                         │
│  > FUEC                │                                                                         │
│  > Mapa GPS            │                                                                         │
│                        │                                                                         │
│  ──────────────────    │                                                                         │
│  [U] Admin User        │                                                                         │
│                        │                                                                         │
│                        │                                                                         │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

**Notes:**
- Top KPIs: counters for vehicles, drivers, today's services, and pending invoices.
- Indicators: `ok`=valid, `!!`=expiring soon, `XX`=expired, `Ab`=open, `Ce`=closed.
- Alerts panel: shows expired or expiring documents (SOAT, RTM, Operation Card, Licenses).
- Content adapts to the user's role (drivers are redirected to `/driver`).
- **Implementation status:** the live dashboard builds KPI cards + the document-alerts panel only. The "Accesos Rápidos" and "Actividad Reciente" blocks shown in earlier mockups are deferred and not yet implemented.

---

## 1. DASHBOARD / YEARLY CALENDAR

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Calendario Anual 2025                                                   │
│  SGTE                  │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│  PRODUCCION            │                                                                         │
│  > Calendario        < │                                                                         │
│  > Gantt Diario        │                                                                         │
│  > Resumen del Dia     │                                                                         │
│                        │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐       │
│  ADMINISTRACION        │  │  ENERO   │ │ FEBRERO  │ │  MARZO   │ │  ABRIL   │ │  MAYO    │       │
│  > Vehiculos           │  │ Lu Ma Mi │ │ Lu Ma Mi │ │ Lu Ma Mi │ │ Lu Ma Mi │ │ Lu Ma Mi │       │
│  > Conductores         │  │ Ju Vi Sa │ │ Ju Vi Sa │ │ Ju Vi Sa │ │ Ju Vi Sa │ │ Ju Vi Sa │       │
│  > Terceros            │  │ Do       │ │ Do       │ │ Do       │ │ Do       │ │ Do       │       │
│  > Contratos           │  │          │ │          │ │          │ │          │ │          │       │
│                        │  │ @@ @@ ## │ │ @@ ## ## │ │ @@ @@ ## │ │ ## ## @@ │ │ @@ @@ ## │       │
│  FACTURACION           │  │ ## @@ @@ │ │ @@ @@ ## │ │ ## @@ @@ │ │ @@ ## ## │ │ ## @@ @@ │       │
│  > Facturas            │  │ @@ ## ## │ │ ## @@ @@ │ │ @@ @@ ## │ │ ## @@ @@ │ │ @@ ## ## │       │
│  > Serv.Ejecutados     │  │ ## @@ @@ │ │ @@ ## ## │ │ ## ## @@ │ │ @@ @@ ## │ │ ## @@ @@ │       │
│                        │  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘       │
│  OPCIONALES            │                                                                         │
│  > FUEC                │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐       │
│  > Mapa GPS            │  │  JUNIO   │ │  JULIO   │ │  AGOSTO  │ │SEPTIEMBRE│ │ OCTUBRE  │       │
│                        │  │ Lu Ma Mi │ │ Lu Ma Mi │ │ Lu Ma Mi │ │ Lu Ma Mi │ │ Lu Ma Mi │       │
│  ──────────────────    │  │ Ju Vi Sa │ │ Ju Vi Sa │ │ Ju Vi Sa │ │ Ju Vi Sa │ │ Ju Vi Sa │       │
│  [U] Admin User        │  │ Do       │ │ Do       │ │ Do       │ │ Do       │ │ Do       │       │
│                        │  │          │ │          │ │          │ │          │ │          │       │
│                        │  │ ## ## @@ │ │ @@ @@ ## │ │ ## @@ @@ │ │ @@ ## ## │ │ @@ @@ ## │       │
│                        │  │ @@ ## ## │ │ ## @@ ## │ │ @@ ## @@ │ │ ## @@ @@ │ │ ## @@ ## │       │
│                        │  │ ## @@ @@ │ │ @@ ## ## │ │ ## @@ ## │ │ @@ ## @@ │ │ @@ ## ## │       │
│                        │  │ @@ ## ## │ │ ## @@ @@ │ │ ## ## ## │ │ ## ## ## │ │ ## ## ## │       │
│                        │  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘       │
│                        │                                                                         │
│                        │  ┌──────────┐ ┌──────────┐                                              │
│                        │  │ NOVIEMBRE│ │ DICIEMBRE│  Leyenda:                                    │
│                        │  │ Lu Ma Mi │ │ Lu Ma Mi │  ## = Sin datos (Negro)                      │
│                        │  │ Ju Vi Sa │ │ Ju Vi Sa │  @@ = Proyectado (Naranja)                   │
│                        │  │ Do       │ │ Do       │  ## = Ejecutado (Verde)                      │
│                        │  │          │ │          │                                              │
│                        │  │ @@ ## ## │ │ ## ## ## │                                              │
│                        │  │ @@ @@ ## │ │ ## ## ## │                                              │
│                        │  │ ## ## ## │ │ ## ## ## │                                              │
│                        │  │ ## ## ## │ │ ## ## ## │                                              │
│                        │  └──────────┘ └──────────┘                                              │
│                        │                                                                         │
│                        │  [< 2024]                                                     [2026 >]  │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

**Notes:** Clicking a month navigates to `/day-statuses/{year}/{month}` (monthly view). Colored days: Black (empty),
Orange (projected), Green (executed). Grid of 5 months per row + 2 on the last row.
URL: `/day-statuses/{year}` — year arrows change the path, not query params.

---

## 2. MONTHLY VIEW (Month detail)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ [<] Octubre 2025 [>]                               [Titulo = volver]    │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ Filtros: [Ciudad: Todas v]  [Modalidad: Todas v]  [Buscar]          │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │   Domingo   Lunes    Martes  Miercoles  Jueves  Viernes   Sabado        │
│                        │  ┌────────┬────────┬────────┬────────┬────────┬────────┬────────┐       │
│                        │  │        │        │        │ 1      │ 2      │ 3      │ 4      │       │
│                        │  │        │        │        │ @@@@@@ │ @@@@@@ │ @@@@@@ │ @@@@@@ │       │
│                        │  │        │        │        │ 2 serv │ 1 serv │ 3 serv │ 1 serv │       │
│                        │  ├────────┼────────┼────────┼────────┼────────┼────────┼────────┤       │
│                        │  │ 5      │ 6      │ 7      │ 8      │ 9      │ 10     │ 11     │       │
│                        │  │ ###### │ @@@@@@ │ @@@@@@ │ @@@@@@ │ @@@@@@ │ @@@@@@ │ @@@@@@ │       │
│                        │  │ 0 serv │ 4 serv │ 2 serv │ 1 serv │ 3 serv │ 5 serv │ 2 serv │       │
│                        │  ├────────┼────────┼────────┼────────┼────────┼────────┼────────┤       │
│                        │  │ 12     │ 13     │ 14     │ 15     │ 16     │ 17     │ 18     │       │
│                        │  │ @@@@@@ │ ###### │ @@@@@@ │ @@@@@@ │ @@@@@@ │ @@@@@@ │ @@@@@@ │       │
│                        │  │ 1 serv │ 0 serv │ 2 serv │ 4 serv │ 1 serv │ 3 serv │ 2 serv │       │
│                        │  ├────────┼────────┼────────┼────────┼────────┼────────┼────────┤       │
│                        │  │ 19     │ 20     │ 21     │ 22     │ 23     │ 24     │ 25     │       │
│                        │  │ @@@@@@ │ @@@@@@ │ ###### │ ###### │ @@@@@@ │ @@@@@@ │ @@@@@@ │       │
│                        │  │ 3 serv │ 2 serv │ 0 serv │ 0 serv │ 1 serv │ 4 serv │ 2 serv │       │
│                        │  ├────────┼────────┼────────┼────────┼────────┼────────┼────────┤       │
│                        │  │ 26     │ 27     │ 28     │ 29     │ 30     │ 31     │        │       │
│                        │  │ @@@@@@ │ @@@@@@ │ @@@@@@ │ ###### │ @@@@@@ │ @@@@@@ │        │       │
│                        │  │ 1 serv │ 2 serv │ 3 serv │ 0 serv │ 2 serv │ 1 serv │        │       │
│                        │  └────────┴────────┴────────┴────────┴────────┴────────┴────────┘       │
│                        │                                                                         │
│                        │  Leyenda: ###### = Sin datos    @@@@@@ = Proyectado    ###### = Ejec.   │
│                        │                                                                         │
│                        │  Click en dia -> Carga servicios del dia inline (ver abajo)            │
│                        │                                                                         │
│                        │  ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │  │ Servicios del 2025-10-15                                            │ │
│                        │  │ ─────────────────────────────────────────────────────────────────── │ │
│                        │  │ Hora   Ruta                    Vehiculo  Conductor   Valor  Estado  │ │
│                        │  │ 06:00  Bogota -> Chia          ABC-123   Perez J.    $80k   Abierto │ │
│                        │  │ 08:30  Bogota -> Zipaquira     DEF-456   Lopez M.   $120k   Cerrado │ │
│                        │  │ 14:00  Chia -> Bogota          GHI-789   Garcia R.   $95k   Abierto │ │
│                        │  └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

**Notes:** Clicking a day loads the day's services inline below the calendar (does not navigate to another page).
URL: `/day-statuses/{year}/{month}?selectedDay={day}`. The `[<]` `[>]` arrows navigate to the previous/next month.
Clicking the month title (e.g. "Octubre 2025") returns to the yearly view. Colors indicate day status.

---

## 3. DAILY GANTT - Fleet Planner

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Planificador Gantt - Miercoles, 15 de Octubre de 2025                   │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ Fecha: [15/10/2025]  Ciudad: [Bogota v]  [Filtrar]                  │ │
│                        │ │ [< Dia Anterior]  [Gantt] [Resumen]  [Siguiente Dia >]              │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ Hora ->  06:00  08:00  10:00  12:00  14:00  16:00  18:00  20:00         │
│                        │ ─────────┬──────┬──────┬──────┬──────┬──────┬──────┬──────┬──────       │
│                        │ WTO-250  │      │[=SERV-001==]│      │      │      │      │             │
│                        │ ─────────┼──────┼──────┼──────┼──────┼──────┼──────┼──────┼──────       │
│                        │ TOY-250  │      │      │      │[===SERV-002========]      │             │
│                        │ ─────────┼──────┼──────┼──────┼──────┼──────┼──────┼──────┼──────       │
│                        │ ABC-123  │//////│//////│//////│//////│//////│//////│//////│//////       │
│                        │          │ [SOAT VENCIDO - 05/10/2025]  Vehiculo bloqueado              │
│                        │ ─────────┼──────┼──────┼──────┼──────┼──────┼──────┼──────┼──────       │
│                        │ XYZ-789  │      │      │[S003]│      │[S004]│      │[S005]│             │
│                        │ ─────────┼──────┼──────┼──────┼──────┼──────┼──────┼──────┼──────       │
│                        │ DEF-456  │      │[S005]│      │      │      │[====SERV-006=====]        │
│                        │ ─────────┼──────┼──────┼──────┼──────┼──────┼──────┼──────┼──────       │
│                        │ GHI-789  │[S007]│      │      │      │      │      │      │             │
│                        │ ─────────┴──────┴──────┴──────┴──────┴──────┴──────┴──────┴──────       │
│                        │                                                                         │
│                        │ Click en celda vacia -> Nuevo Servicio                                  │
│                        │ Click en barra -> Editar Servicio                                       │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

**Notes:**
- Y axis: List of fleet vehicles
- X axis: Day schedule (06:00 - 22:00)
- Horizontal bars = Assigned services
- Blocked vehicles shown in grey with the expired document
- `//////` = blocked row (vehicle with expired documents)

---

## 4. SERVICE FORM (Create/Edit)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ {Nuevo Servicio | Editar Servicio SERV-001}       [<- Volver al Gantt]  │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ INFORMACION GENERAL                                                 │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ ID Servicio:      [SERV-001                  ] (Automatico)         │ │
│                        │ │ Placa Vehiculo:   [WTO-250 (COD: 01)        v] *                    │ │
│                        │ │                   [ok SOAT] [ok RTM] [ok Tarjeta]                   │ │
│                        │ │ Conductor:        [Hernandez Perez Juan     v] *                    │ │
│                        │ │                   [!! Licencia vence en 15 dias]                    │ │
│                        │ │                                                                     │ │
│                        │ │ CONTRATO                                                            │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Tercero/Cliente:  [Empresa ABC S.A.S.       v] *                    │ │
│                        │ │ Contrato:         [CONT-2025-001 (Vigente)  v] *                    │ │
│                        │ │                   [Tipo: Empresarial | Vigente hasta: 31/12/25]     │ │
│                        │ │ [+ Crear Contrato Generico Temporal]                                │ │
│                        │ │                                                                     │ │
│                        │ │ RUTA Y HORARIO                                                      │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Origen:           [Bogota - Calle 100 # 15-20       ]               │ │
│                        │ │ Destino:          [Chia - Centro Comercial Fontanar ]               │ │
│                        │ │ Fecha Servicio:   [15/10/2025              ] *                      │ │
│                        │ │ Hora Inicio Plan: [08:00                   ] *                      │ │
│                        │ │ Duracion Estim.:  [2:00 horas              ] *                      │ │
│                        │ │                   [Llegada estimada: 10:00]                         │ │
│                        │ │                                                                     │ │
│                        │ │ EJECUCION (Visible en modo edicion)                                 │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Hora Inicio Real: [08:15                   ]  [ok Confirmada]       │ │
│                        │ │ Hora Final Real:  [10:30                   ]  [ok Confirmada]       │ │
│                        │ │ Duracion Real:    [2:15 horas]  [+15 min exceso]                    │ │
│                        │ │                                                                     │ │
│                        │ │ FACTURACION                                                         │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Grupo:            [EJECUTIVOS              v]                       │ │
│                        │ │ Valor Unitario:   [$ 150,000 COP            ]                       │ │
│                        │ │ Cantidad:         [1                        ]                       │ │
│                        │ │ Forma de Pago:    [CREDITO 30 DIAS         v]                       │ │
│                        │ │                                                                     │ │
│                        │ │ ESTADO                                                              │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Estado Servicio:  (*) Abierto  ( ) Cerrado                          │ │
│                        │ │ Estado Dia:       PROYECTADO (Dia en modo edicion)                  │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ [Guardar]  [Cancelar]                                                   │
│                        │                                                                         │
│                        │ Notas:                                                                  │
│                        │ * Campos marcados con * son obligatorios                                │
│                        │ * Si vehiculo es tercerizado (internal_code = 18), no se muestra        │
│                        │   conductor                                                             │
│                        │ * En dia EJECUTADO, solo Contabilidad edita campos de facturacion       │
│                        │ * Novedades: se registran desde `/service-incidents/create`             │
│                        │   (o desde el boton "Registrar Novedad" en las tarjetas de `/driver`)   │
│                        │ * FUEC: vive en su propio modulo (Fase 5, aun solo scaffolded)          │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

---

## 5. DAY SUMMARY

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Resumen del Dia - Miercoles, 15 de Octubre de 2025                      │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ Fecha: [15/10/2025]    [Ver Gantt]  [Exportar Excel]                │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ SERVICIOS DEL DIA                                                       │
│                        │ ┌──────────┬──────────────────┬─────────────┬──────────┬───────┐        │
│                        │ │ Placa    │ Conductor/Provee │ Horario     │ Cliente  │Estado │        │
│                        │ ├──────────┼──────────────────┼─────────────┼──────────┼───────┤        │
│                        │ │ WTO-250  │ Hernandez P. J.  │ 08:00-10:30 │ EmpABC   │ok Cer │        │
│                        │ │          │                  │             │          │!! 1Nov│        │
│                        │ ├──────────┼──────────────────┼─────────────┼──────────┼───────┤        │
│                        │ │ TOY-250  │ Carlos Martin L. │ 12:00-16:00 │ TurXYZ   │ok Cer │        │
│                        │ ├──────────┼──────────────────┼─────────────┼──────────┼───────┤        │
│                        │ │ XYZ-789  │ Proveedor Transp │ 10:00-12:00 │ SaludPls │Abiert │        │
│                        │ │ 3ro      │                  │             │          │!! 2Nov│        │
│                        │ ├──────────┼──────────────────┼─────────────┼──────────┼───────┤        │
│                        │ │ DEF-456  │ Rodriguez Ana M. │ 14:00-18:00 │ EmpXYZ   │Abiert │        │
│                        │ ├──────────┼──────────────────┼─────────────┼──────────┼───────┤        │
│                        │ │ GHI-789  │ Proveedor Expres │ 06:00-08:00 │ Emp123   │ok Cer │        │
│                        │ │ 3ro      │                  │             │          │       │        │
│                        │ └──────────┴──────────────────┴─────────────┴──────────┴───────┘        │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ RESUMEN EJECUTIVO                                                   │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Total Servicios:  5   Cerrados: 3   Abiertos: 2                     │ │
│                        │ │ Con Novedades:    2   Vehiculos 3ros: 2                             │ │
│                        │ │                                                                     │ │
│                        │ │ ESTADO DEL DIA                                                      │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Estado:  [PROYECTADO]                                               │ │
│                        │ │                                                                     │ │
│                        │ │ [!! Ejecutar Dia]  (Deshabilitado: Hay servicios abiertos)          │ │
│                        │ │                                                                     │ │
│                        │ │ Para ejecutar el dia, todos los servicios deben estar cerrados.     │ │
│                        │ │ Cambiara estado a EJECUTADO y bloqueara edicion.                    │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ [< Dia Anterior]                                  [Siguiente Dia >]     │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

**Notes:**
- OPEN state: Service in progress or pending
- CLOSED state: Service finalized by the driver
- Click on a row -> View/Edit service
- "Execute Day" only enabled when every service is CLOSED

---

## 6. VEHICLES LIST (CRUD)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Gestion de Vehiculos                                                    │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ [+ Nuevo Vehiculo]  [Buscar...]  [Ciudad: Todas v]                  │ │
│                        │ │                     [Exportar]  [Refrescar]                         │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ ┌─────────┬───────┬────────┬────────┬──────┬──────────────┬────────┐    │
│                        │ │ Placa   │ Movil │ Marca  │ Ciudad │ COD  │ Documentos   │ Estado │    │
│                        │ ├─────────┼───────┼────────┼────────┼──────┼──────────────┼────────┤    │
│                        │ │ WTO-250 │ M-101 │ Toyota │ Bogota │ 01   │ ok ok ok     │ Activo │    │
│                        │ │         │       │ Hiace  │        │      │ SOAT RTM Tar │        │    │
│                        │ ├─────────┼───────┼────────┼────────┼──────┼──────────────┼────────┤    │
│                        │ │ TOY-250 │ M-102 │ Toyota │ Bogota │ 02   │ ok !! ok     │ Prec.  │    │
│                        │ │         │       │ Fortu. │        │      │    RTM:12d   │        │    │
│                        │ ├─────────┼───────┼────────┼────────┼──────┼──────────────┼────────┤    │
│                        │ │ ABC-123 │ M-103 │ Nissan │ Bogota │ 03   │ XX ok ok     │ Bloq.  │    │
│                        │ │         │       │ Urvan  │        │      │ SOAT:VENCIDO │        │    │
│                        │ ├─────────┼───────┼────────┼────────┼──────┼──────────────┼────────┤    │
│                        │ │ XYZ-789 │EXT-01 │ Ford   │ Medell │ 18   │ ok ok ok     │ Activo │    │
│                        │ │         │       │Transit │        │ 3ro  │              │ 3ro    │    │
│                        │ ├─────────┼───────┼────────┼────────┼──────┼──────────────┼────────┤    │
│                        │ │ DEF-456 │ M-104 │Merced. │ Cali   │ 04   │ ok ok ok     │ Activo │    │
│                        │ │         │       │Sprint. │        │      │              │        │    │
│                        │ ├─────────┼───────┼────────┼────────┼──────┼──────────────┼────────┤    │
│                        │ │ ...     │ ...   │ ...    │ ...    │ ...  │ ...          │ ...    │    │
│                        │ └─────────┴───────┴────────┴────────┴──────┴──────────────┴────────┘    │
│                        │                                                                         │
│                        │ Leyenda:                                                                │
│                        │   Activo  = Documentos vigentes                                         │
│                        │   Prec.   = Algun documento vence en < 15 dias                          │
│                        │   Bloq.   = Documento vencido, no puede asignarse a servicios           │
│                        │   3ro     = COD 18, vinculado a proveedor                               │
│                        │   ok=Vigente  !!=Por vencer  XX=Vencido                                 │
│                        │                                                                         │
│                        │ [< 1-5 de 25 >]  [Anterior] [1] [2] [3] ... [5] [Siguiente]             │
│                        │ Acciones por fila: [Ver] [Editar] [Eliminar]                            │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

---

## 7. VEHICLE FORM (Create/Edit)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ {Nuevo Vehiculo | Editar Vehiculo WTO-250}       [<- Volver a Lista]    │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ INFORMACION BASICA                                                  │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Placa:             [WTO-250               ] * (6 caracteres)        │ │
│                        │ │ COD:               [01                    ] * (Codigo interno)      │ │
│                        │ │                    [info: 18 = Vehiculo tercerizado]                │ │
│                        │ │ Numero Movil:      [M-101                 ] * (Identificador)       │ │
│                        │ │                                                                     │ │
│                        │ │ ESPECIFICACIONES TECNICAS                                           │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Marca:             [Toyota                ] *                       │ │
│                        │ │ Linea:             [Hiace Commuter        ] *                       │ │
│                        │ │ Modelo (Ano):      [2023                  ] *                       │ │
│                        │ │ Tipo Vehiculo:     [Van                   v] *                      │ │
│                        │ │                    (Bus | Buseta | Van | Automovil)                 │ │
│                        │ │ Numero Motor:      [1HZ123456789          ] *                       │ │
│                        │ │ Numero Chasis:     [JT1234567890ABCDEF    ] *                       │ │
│                        │ │ Capacidad Pasaj.:  [15                    ] *                       │ │
│                        │ │                                                                     │ │
│                        │ │ UBICACION Y PROPIEDAD                                               │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Ciudad Ubicacion:  [Bogota                v] *                      │ │
│                        │ │ [ ] Es Vehiculo Tercerizado                                         │ │
│                        │ │ Proveedor:         [Proveedor Transportes v] (Si es 3ro)            │ │
│                        │ │                                                                     │ │
│                        │ │ DOCUMENTACION LEGAL                                                 │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ SOAT Vencimiento:  [15/12/2025            ] [ok Vigente]            │ │
│                        │ │                    [Alertas: 30d | 15d | 5d]                        │ │
│                        │ │ RTM Vencimiento:   [20/11/2025            ] [!! 15 dias]            │ │
│                        │ │                    [!! Vence pronto]                                │ │
│                        │ │ Tarjeta Operacion: [30/06/2026            ] [ok Vigente]            │ │
│                        │ │                                                                     │ │
│                        │ │ ESTADO                                                              │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Estado:            [Activo                v]                        │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ [Guardar]  [Cancelar]                                                   │
│                        │                                                                         │
│                        │ Notas:                                                                  │
│                        │ * Si COD = 18, se marca automaticamente como tercerizado                │
│                        │ * Vehiculos con documentos vencidos se bloquean en el Gantt             │
│                        │ * Las alertas se envian a 30, 15 y 5 dias del vencimiento               │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

---

## 8. DRIVERS LIST (CRUD)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Gestion de Conductores                                                  │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ [+ Nuevo Conductor]  [Buscar...]  [Exportar]                        │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ ┌────────────────┬────────────┬──────┬───────────┬──────┬────────┐      │
│                        │ │ Nombre         │ Documento  │ Cat. │ Licencia  │Seg.S.│ Estado │      │
│                        │ ├────────────────┼────────────┼──────┼───────────┼──────┼────────┤      │
│                        │ │ Hernandez P.   │CC 79456123 │  C1  │ 15/12/25  │ ok   │ Activo │      │
│                        │ │ Juan Carlos    │            │      │ [ok]      │      │        │      │
│                        │ ├────────────────┼────────────┼──────┼───────────┼──────┼────────┤      │
│                        │ │ Martinez L.    │CC 80123456 │  B1  │ 05/11/25  │ ok   │ Prec.  │      │
│                        │ │ Carlos Alberto │            │      │ [!!] 10d  │      │        │      │
│                        │ ├────────────────┼────────────┼──────┼───────────┼──────┼────────┤      │
│                        │ │ Rodriguez A.   │CC 52789012 │  C1  │ 01/09/25  │ ok   │ Inact. │      │
│                        │ │ Maria          │            │      │ [XX]VENC. │      │        │      │
│                        │ ├────────────────┼────────────┼──────┼───────────┼──────┼────────┤      │
│                        │ │ Lopez Gomez    │CC 71234567 │  B2  │ 20/12/25  │ !!   │ Prec.  │      │
│                        │ │ Pedro Jose     │            │      │ [ok]      │Pend. │        │      │
│                        │ ├────────────────┼────────────┼──────┼───────────┼──────┼────────┤      │
│                        │ │ ...            │ ...        │ ...  │ ...       │ ...  │ ...    │      │
│                        │ └────────────────┴────────────┴──────┴───────────┴──────┴────────┘      │
│                        │                                                                         │
│                        │ Leyenda:                                                                │
│                        │   Activo = Licencia vigente + Seguridad social al dia                   │
│                        │   Prec.  = Licencia vence en < 15 dias o Seg.Social pendiente           │
│                        │   Inact. = Licencia vencida o Seg.Social no vigente                     │
│                        │                                                                         │
│                        │ [< 1-4 de 12 >]  [Anterior] [1] [2] [Siguiente]                         │
│                        │ Acciones: [Ver] [Editar] [Eliminar]                                     │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

---

## 9. DRIVER FORM (Create/Edit)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ {Nuevo Conductor | Editar Conductor}             [<- Volver a Lista]    │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ INFORMACION PERSONAL                                                │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Tipo Documento:    [Cedula Ciudadania      v] *                     │ │
│                        │ │ Numero Documento:  [79.456.123              ] *                     │ │
│                        │ │ Primer Nombre:     [Juan                    ] *                     │ │
│                        │ │ Segundo Nombre:    [Carlos                  ]                       │ │
│                        │ │ Primer Apellido:   [Hernandez               ] *                     │ │
│                        │ │ Segundo Apellido:  [Perez                   ]                       │ │
│                        │ │                                                                     │ │
│                        │ │ CONTACTO                                                            │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Ciudad Residencia: [Bogota                 v] *                     │ │
│                        │ │ Direccion:         [Calle 123 # 45-67, Barrio Centro              ] │ │
│                        │ │ Telefono:          [300-123-4567            ] *                     │ │
│                        │ │ Correo:            [juan.hernandez@email.com                      ] │ │
│                        │ │                                                                     │ │
│                        │ │ LICENCIA DE CONDUCCION                                              │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Categoria:         [C1 (Vehiculos > 9 pasajeros) v] *               │ │
│                        │ │ Vencimiento:       [15/12/2025              ] [ok Vigente]          │ │
│                        │ │                                                                     │ │
│                        │ │ SEGURIDAD SOCIAL                                                    │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ EPS:               [Sanitas                v] *                     │ │
│                        │ │ Fondo Pensiones:   [Porvenir               v] *                     │ │
│                        │ │ Fondo Cesantias:   [Colfondos              v] *                     │ │
│                        │ │ [ok] Seguridad Social Vigente                                       │ │
│                        │ │                                                                     │ │
│                        │ │ ESTADO                                                              │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ [ok] Activo                                                         │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ [Guardar]  [Cancelar]                                                   │
│                        │                                                                         │
│                        │ Notas:                                                                  │
│                        │ * Categoria C1 requerida para Bus, Buseta, Van                          │
│                        │ * Categoria B2 suficiente solo para Automovil                           │
│                        │ * Conductores con licencia vencida no aparecen en selector              │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

---

## 10. THIRD PARTIES LIST (CRUD)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Gestion de Terceros (Clientes y Proveedores)                            │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ [+ Nuevo Tercero]  [Buscar...]  [Clientes] [Proveedores]            │ │
│                        │ │                    [Exportar]  [Refrescar]                          │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ ┌───────────────────┬────────────┬─────────┬─────────┬────────────┐     │
│                        │ │ Nombre/Razon Soc. │ NIT/CC     │ Tipo    │ Ciudad  │ Rol        │     │
│                        │ ├───────────────────┼────────────┼─────────┼─────────┼────────────┤     │
│                        │ │ Empresa ABC SAS   │ NIT 901.2..│ Juridica│ Bogota  │ [Cli] [Pro]│     │
│                        │ ├───────────────────┼────────────┼─────────┼─────────┼────────────┤     │
│                        │ │ Turismo XYZ Ltda  │ NIT 830.5..│ Juridica│ Medelli │ [Cli]      │     │
│                        │ ├───────────────────┼────────────┼─────────┼─────────┼────────────┤     │
│                        │ │ Proveedor Transp. │ NIT 800.1..│ Juridica│ Bogota  │       [Pro]│     │
│                        │ │ Express SA        │            │         │         │            │     │
│                        │ ├───────────────────┼────────────┼─────────┼─────────┼────────────┤     │
│                        │ │ Carlos Alberto    │ CC 80.123..│ Natural │ Cali    │ [Cli]      │     │
│                        │ │ Martinez Lopez    │            │         │         │            │     │
│                        │ ├───────────────────┼────────────┼─────────┼─────────┼────────────┤     │
│                        │ │ Salud Plus IPS    │ NIT 900.8..│ Juridica│ Bogota  │ [Cli]      │     │
│                        │ ├───────────────────┼────────────┼─────────┼─────────┼────────────┤     │
│                        │ │ ...               │ ...        │ ...     │ ...     │ ...        │     │
│                        │ └───────────────────┴────────────┴─────────┴─────────┴────────────┘     │
│                        │                                                                         │
│                        │ [< 1-5 de 45 >]  [Anterior] [1] [2] ... [9] [Siguiente]                 │
│                        │ Acciones: [Ver] [Editar] [Eliminar]                                     │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

**Notes:** Role: [Cli]=Client  [Pro]=Provider. A third party can be both.

---

## 11. CONTRACTS LIST (CRUD)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Gestion de Contratos                                                    │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ [+ Nuevo Contrato]  [Buscar...]  [Por Cliente]                      │ │
│                        │ │ [Vigentes] [Por Vencer] [Exportar]                                  │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ ┌────────────────┬────────────────┬───────────┬───────────┬────────┐    │
│                        │ │ Num. Contrato  │ Cliente        │ Objeto    │ Vigencia  │ Estado │    │
│                        │ ├────────────────┼────────────────┼───────────┼───────────┼────────┤    │
│                        │ │ CONT-2025-001  │ Empresa ABC    │Empresarial│01/01-31/12│ok Vig. │    │
│                        │ │                │ S.A.S.         │           │ 2025      │        │    │
│                        │ ├────────────────┼────────────────┼───────────┼───────────┼────────┤    │
│                        │ │ CONT-2025-002  │ Turismo XYZ    │ Turismo   │01/03-30/09│ok Vig. │    │
│                        │ │                │ Ltda.          │           │ 2025      │        │    │
│                        │ ├────────────────┼────────────────┼───────────┼───────────┼────────┤    │
│                        │ │ CONT-2025-003  │ Salud Plus IPS │ Salud     │01/01-31/12│ok Vig. │    │
│                        │ ├────────────────┼────────────────┼───────────┼───────────┼────────┤    │
│                        │ │ CONT-2024-045  │ Empresa XYZ    │ Ocasional │01/06-30/11│!! Ven. │    │
│                        │ │                │ S.A.S.         │           │ 2024      │ 15 dias│    │
│                        │ ├────────────────┼────────────────┼───────────┼───────────┼────────┤    │
│                        │ │ GEN-2025-T-128 │ Carlos Martin. │ Temporal  │ 15/10/25  │ Gen.   │    │
│                        │ │ [Generico]     │                │ (1 dia)   │ (1 dia)   │        │    │
│                        │ ├────────────────┼────────────────┼───────────┼───────────┼────────┤    │
│                        │ │ CONT-2024-012  │ Proveedor Ext. │Empresarial│01/01-31/05│XX Venc │    │
│                        │ │                │                │           │ 2024      │        │    │
│                        │ ├────────────────┼────────────────┼───────────┼───────────┼────────┤    │
│                        │ │ ...            │ ...            │ ...       │ ...       │ ...    │    │
│                        │ └────────────────┴────────────────┴───────────┴───────────┴────────┘    │
│                        │                                                                         │
│                        │ Leyenda: ok=Vigente  !!=Por vencer<30d  XX=Vencido  Gen.=Generico       │
│                        │                                                                         │
│                        │ [< 1-6 de 128 >]  [Anterior] [1] [2] ... [13] [Siguiente]               │
│                        │ Acciones: [Ver] [Editar] [Eliminar] [Ver Servicios]                     │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

---

## 12. BILLING - Executed Services

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Facturacion - Servicios Ejecutados                                      │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ Filtros:                                                            │ │
│                        │ │ [Desde: 01/10/2025]  [Hasta: 31/10/2025]                            │ │
│                        │ │ [Cliente: Todas v]  [Estado: Sin Facturar v]                        │ │
│                        │ │ [Buscar]  [Limpiar]  [Exportar]                                     │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ SERVICIOS EJECUTADOS (Dias con estado EJECUTADO)                        │
│                        │ ┌─────────┬─────────┬──────────────────┬─────────┬────────┬───────┐     │
│                        │ │ Fecha   │Servicio │Vehiculo/Conductor│ Cliente │ Valor  │Est.Fac│     │
│                        │ ├─────────┼─────────┼──────────────────┼─────────┼────────┼───────┤     │
│                        │ │15/10/25 │SERV-001 │WTO-250/Hernandez │ EmpABC  │$175,000│!! Pend│     │
│                        │ │         │         │                  │         │(B:150k │       │     │
│                        │ │         │         │                  │         │+N:25k) │       │     │
│                        │ ├─────────┼─────────┼──────────────────┼─────────┼────────┼───────┤     │
│                        │ │15/10/25 │SERV-002 │TOY-250/Carlos M. │ TurXYZ  │$200,000│!! Pend│     │
│                        │ ├─────────┼─────────┼──────────────────┼─────────┼────────┼───────┤     │
│                        │ │15/10/25 │SERV-004 │XYZ-789/Proveedor │ EmpXYZ  │$150,000│ok Fac.│     │
│                        │ │         │         │                  │         │        │ FAC-45│     │
│                        │ ├─────────┼─────────┼──────────────────┼─────────┼────────┼───────┤     │
│                        │ │14/10/25 │SERV-089 │ABC-123/Lopez P.  │SaludPls │$180,000│ok Fac.│     │
│                        │ │         │         │                  │         │        │ FAC-44│     │
│                        │ ├─────────┼─────────┼──────────────────┼─────────┼────────┼───────┤     │
│                        │ │ ...     │ ...     │ ...              │ ...     │ ...    │ ...   │     │
│                        │ └─────────┴─────────┴──────────────────┴─────────┴────────┴───────┘     │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ RESUMEN                                                             │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Total Servicios:      45                                            │ │
│                        │ │ Valor Total:          $8,500,000                                    │ │
│                        │ │ Sin Facturar:         12 servicios    $2,100,000                    │ │
│                        │ │ Facturados:           33 servicios    $6,400,000                    │ │
│                        │ │                                                                     │ │
│                        │ │ [+ Crear Factura con Seleccionados]                                 │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ [< 1-5 de 45 >]                                                         │
│                        │ Acciones: [Ver Servicio] [Asociar a Factura] [Ver Factura]              │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

**Notes:** Only services from days in EXECUTED state. !! Pend=No invoice. ok Fac=Has an invoice.

---

## 13. INVOICE FORM (Create/Edit)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Nueva Factura                                            [<- Volver]    │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ INFORMACION DE FACTURA                                              │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Numero Factura:    [FAC-2025-046            ] *                     │ │
│                        │ │ Fecha Emision:     [20/10/2025              ] *                     │ │
│                        │ │ Cliente:           [Empresa ABC S.A.S.     v] *                     │ │
│                        │ │                                                                     │ │
│                        │ │ SERVICIOS A FACTURAR                                                │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Servicios del cliente (estado EJECUTADO, sin factura):              │ │
│                        │ │                                                                     │ │
│                        │ │ [X] SERV-001  15/10/25  WTO-250  Bogota-Chia      $175,000          │ │
│                        │ │     (Base: $150,000 + Novedad: $25,000)                             │ │
│                        │ │ [X] SERV-015  16/10/25  TOY-250  Bogota-Soacha    $200,000          │ │
│                        │ │ [ ] SERV-023  18/10/25  XYZ-789  Medell-Rionegro  $150,000          │ │
│                        │ │     (Cliente diferente - no disponible)                             │ │
│                        │ │ [X] SERV-031  20/10/25  WTO-250  Bogota-Zipaquira $180,000          │ │
│                        │ │     (Base: $180,000 + Novedad: $0)                                  │ │
│                        │ │                                                                     │ │
│                        │ │ RESUMEN DE VALORES                                                  │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Subtotal Servicios:                   $530,000                      │ │
│                        │ │ Valor Novedades (adicionales):       +$25,000                       │ │
│                        │ │ ───────────────────────────────────────────────                     │ │
│                        │ │ TOTAL FACTURA:                        $555,000                      │ │
│                        │ │                                                                     │ │
│                        │ │ FORMA DE PAGO                                                       │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Forma de Pago:     [Credito 30 dias          v] *                   │ │
│                        │ │ Estado Pago:       [Pendiente de Pago        v]                     │ │
│                        │ │                                                                     │ │
│                        │ │ OBSERVACIONES                                                       │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ [Servicios de transporte empresarial - Octubre 2025               ] │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ [Guardar Factura]  [Cancelar]  [Vista Previa PDF]                       │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

**Notes:** Only services from the same client. Only EXECUTED with no invoice. Total includes incidents.

---

## 14. MOBILE INTERFACE - DRIVER (My Services)

```
┌─────────────────────────────┐
│ Bat 85%  4G           14:30 │
├─────────────────────────────┤
│  [=]  Mis Servicios    [N]  │
├─────────────────────────────┤
│                             │
│  Hola, Juan                 │
│  Servicios para hoy:        │
│                             │
│  ┌───────────────────────┐  │
│  │ SERV-001              │  │
│  │ 08:00 - 10:30         │  │
│  │ Vehiculo: WTO-250     │  │
│  │ Bogota -> Chia        │  │
│  │ Cliente: Empresa ABC  │  │
│  │                       │  │
│  │ [!! Pendiente]        │  │
│  │                       │  │
│  │ [>> INICIAR SERVICIO] │  │
│  └───────────────────────┘  │
│                             │
│  ┌───────────────────────┐  │
│  │ SERV-003              │  │
│  │ 14:00 - 16:00         │  │
│  │ Vehiculo: TOY-250     │  │
│  │ Bogota -> Soacha      │  │
│  │ Cliente: Turismo XYZ  │  │
│  │                       │  │
│  │ [!! Pendiente]        │  │
│  └───────────────────────┘  │
│                             │
│  ┌───────────────────────┐  │
│  │ SERV-089              │  │
│  │ 06:00 - 08:00         │  │
│  │ Vehiculo: XYZ-789     │  │
│  │ Medellin -> Rionegro  │  │
│  │ Cliente: Salud Plus   │  │
│  │                       │  │
│  │ [ok Completado]       │  │
│  │ [Ver Detalle]         │  │
│  └───────────────────────┘  │
│                             │
├─────────────────────────────┤
│  [Inicio]  [Mapa] [Perfil]  │
└─────────────────────────────┘
```

**Notes:** Mobile-first for drivers. States: !! Pending | In Progress | ok Completed

---

## 15. MOBILE INTERFACE - Service Detail (Driver)

```
┌─────────────────────────────┐
│ Bat 85%  4G         08:05   │
├─────────────────────────────┤
│  [<-]  Detalle Servicio     │
├─────────────────────────────┤
│                             │
│  SERV-001                   │
│  [** EN CURSO **]           │
│                             │
│  ┌───────────────────────┐  │
│  │ RUTA                  │  │
│  │                       │  │
│  │ Origen:               │  │
│  │ Bogota - Calle 100    │  │
│  │                       │  │
│  │ Destino:              │  │
│  │ Chia - Centro Fontanar│  │
│  └───────────────────────┘  │
│                             │
│  ┌───────────────────────┐  │
│  │ HORARIO               │  │
│  │                       │  │
│  │ Inicio Plan:    08:00 │  │
│  │ Inicio Real:    08:05 │  │
│  │                       │  │
│  │ Duracion Est:   2:00h │  │
│  │ Duracion Real:  0:05h │  │
│  │                       │  │
│  │ Est. Llegada:   10:05 │  │
│  └───────────────────────┘  │
│                             │
│  ┌───────────────────────┐  │
│  │ VEHICULO              │  │
│  │ Placa: WTO-250        │  │
│  │ Movil: M-101          │  │
│  │ Marca: Toyota Hiace   │  │
│  └───────────────────────┘  │
│                             │
│  ┌───────────────────────┐  │
│  │ CLIENTE               │  │
│  │ Empresa ABC S.A.S.    │  │
│  │ Contacto: 601-555..   │  │
│  └───────────────────────┘  │
│                             │
│  [+ Registrar Novedad]      │
│                             │
│  ┌───────────────────────┐  │
│  │ NOVEDADES (1)         │  │
│  │                       │  │
│  │ * Retraso por trafico │  │
│  │   08:05 - +15 min     │  │
│  │   [Ver foto]          │  │
│  └───────────────────────┘  │
│                             │
│  ┌───────────────────────┐  │
│  │ MI UBICACION          │  │
│  │ Lat: 4.7110 N         │  │
│  │ Lon: -74.0721 W       │  │
│  │ [Actualizar GPS]      │  │
│  │ [Ingresar Manual]     │  │
│  └───────────────────────┘  │
│                             │
│  [===FINALIZAR SERVICIO===] │
│                             │
├─────────────────────────────┤
│  [Inicio]  [Mapa] [Perfil]  │
└─────────────────────────────┘
```

**Notes:** View while a service is running. Large button to finalize. GPS is optional.

---

## 16. MODAL - Register Incident

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                                  │
│   ┌──────────────────────────────────────────────────────────────────────────────────────────┐   │
│   │ REGISTRAR NOVEDAD                                                                   [X]  │   │
│   │ ════════════════════════════════════════════════════════════════════════════════════════ │   │
│   │                                                                                          │   │
│   │ Servicio: SERV-001  |  Fecha: 15/10/2025  |  Vehiculo: WTO-250                           │   │
│   │                                                                                          │   │
│   │ ──────────────────────────────────────────────────────────────────────────────────────── │   │
│   │                                                                                          │   │
│   │ Tipo de Novedad:   [Retraso por trafico                                     v] *         │   │
│   │                                                                                          │   │
│   │                    Opciones:                                                             │   │
│   │                    - Retraso por trafico                                                 │   │
│   │                    - Retraso por clima                                                   │   │
│   │                    - Cambio de ruta                                                      │   │
│   │                    - Problema mecanico                                                   │   │
│   │                    - Cliente no se presento                                              │   │
│   │                    - Demora en punto de recogida                                         │   │
│   │                    - Otro                                                                │   │
│   │                                                                                          │   │
│   │ ──────────────────────────────────────────────────────────────────────────────────────── │   │
│   │                                                                                          │   │
│   │ Descripcion:                                                                             │   │
│   │ ┌──────────────────────────────────────────────────────────────────────────────────────┐ │   │
│   │ │ Accidente en autopista norte a la altura de la calle 180.                            │ │   │
│   │ │ El trafico esta detenido. Se estima demora de 15-20 minutos.                         │ │   │
│   │ └──────────────────────────────────────────────────────────────────────────────────────┘ │   │
│   │                                                                                          │   │
│   │ ──────────────────────────────────────────────────────────────────────────────────────── │   │
│   │                                                                                          │   │
│   │ [ ] Esta novedad afecta la facturacion                                                   │   │
│   │                                                                                          │   │
│   │ Si marca esta opcion, el valor del servicio podria modificarse:                          │   │
│   │                                                                                          │   │
│   │ Tipo de ajuste:   (*) Valor Adicional  ( ) Descuento                                     │   │
│   │ Valor:            [$ 25,000              ] COP                                           │   │
│   │ Justificacion:    [Por tiempo extra de espera                                          ] │   │
│   │                                                                                          │   │
│   │ ──────────────────────────────────────────────────────────────────────────────────────── │   │
│   │                                                                                          │   │
│   │ [Adjuntar Foto/Documento]  (opcional)                                                    │   │
│   │ ┌──────────────────────────────────────────────────────────────────────────────────────┐ │   │
│   │ │ foto_trafico.jpg  [Eliminar]                                                         │ │   │
│   │ └──────────────────────────────────────────────────────────────────────────────────────┘ │   │
│   │                                                                                          │   │
│   │ ──────────────────────────────────────────────────────────────────────────────────────── │   │
│   │                                                                                          │   │
│   │ Registrado por: Juan Hernandez (Conductor)                                               │   │
│   │ Fecha/Hora: 15/10/2025 08:15                                                             │   │
│   │                                                                                          │   │
│   │       [Guardar Novedad]  [Cancelar]                                                      │   │
│   │                                                                                          │   │
│   └──────────────────────────────────────────────────────────────────────────────────────────┘   │
│                                                                                                  │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```

**Notes:** Modal over the current screen. Incident type is a configurable catalog.
Impact on billing is optional. Photographic evidence can be attached.

---

## 17. NOTIFICATIONS / INBOX

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Notificaciones                                                          │
│                        │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │ Filtros: [Todas] [No leidas] [Por Tipo] [Limpiar]                   │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ ┌─────────────────────────────────────────────────────────────────────┐ │
│                        │ │                                                                     │ │
│                        │ │ [*] Vencimiento SOAT - Vehiculo ABC-123                             │ │
│                        │ │     El SOAT del vehiculo ABC-123 vence en 5 dias (20/10/2025).      │ │
│                        │ │     [Ver vehiculo]                                   Hace 2 horas   │ │
│                        │ │                                                                     │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │                                                                     │ │
│                        │ │ [*] Vencimiento Licencia - Conductor Martinez Lopez                 │ │
│                        │ │     La licencia de Carlos Martinez vence en 10 dias (05/11/2025).   │ │
│                        │ │     Categoria: B1                                                   │ │
│                        │ │     [Ver conductor]                                  Hace 5 horas   │ │
│                        │ │                                                                     │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │                                                                     │ │
│                        │ │ [*] Dia Ejecutado - 15/10/2025                                      │ │
│                        │ │     El dia 15/10/2025 ha sido ejecutado.                            │ │
│                        │ │     Total servicios: 5 | Valor total: $875,000                      │ │
│                        │ │     [Ver resumen] [Ir a Facturacion]               Hace 8 horas     │ │
│                        │ │                                                                     │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │                                                                     │ │
│                        │ │ [*] Novedad con Impacto - SERV-001                                  │ │
│                        │ │     Se registro una novedad que afecta la facturacion.              │ │
│                        │ │     Servicio: SERV-001 | Valor adicional: $25,000                   │ │
│                        │ │     Registrada por: Hernandez Perez Juan (Conductor)                │ │
│                        │ │     [Ver servicio]                                   Hace 8 horas   │ │
│                        │ │                                                                     │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │                                                                     │ │
│                        │ │ [ ] Servicio Completado - SERV-089                                  │ │
│                        │ │     El conductor completo el servicio SERV-089.                     │ │
│                        │ │     Duracion real: 2:15 horas (Estimada: 2:00)                      │ │
│                        │ │     [Ver servicio]                                  Ayer 17:30      │ │
│                        │ │                                                                     │ │
│                        │ │ [*] = No leida    [ ] = Leida                                       │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ [< 1-5 de 23 >]  [Anterior] [Siguiente]                                 │
│                        │ Acciones: [Marcar todo como leido]  [Eliminar leidas]                   │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

---

## 18. SETTINGS / ACCOUNT CONFIGURATION

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Configuracion                                                           │
│  SGTE                  │ ═══════════════════════════════════════════════════════════════════════ │
│  Transporte Especial   │                                                                         │
│                        │ ┌──────────────────────┬──────────────────────────────────────────────┐ │
│  PRODUCCION            │ │                      │                                              │ │
│  > Calendario          │ │ [Perfil]           < │  Informacion del Perfil                      │ │
│  > Gantt Diario        │ │ [Contrasena]         │  ──────────────────────────────────────────  │ │
│  > Resumen del Dia     │ │ [Apariencia]         │  Actualiza tu nombre y correo electronico    │ │
│                        │ │ [Seguridad 2FA]      │                                              │ │
│  ADMINISTRACION        │ │                      │  Nombre:   [Admin User                    ]  │ │
│  > Vehiculos           │ │                      │  Email:    [admin@sgte.co                 ]  │ │
│  > Conductores         │ │                      │                                              │ │
│  > Terceros            │ │                      │  [Guardar]  (ok Guardado)                    │ │
│  > Contratos           │ │                      │                                              │ │
│                        │ │                      │  ──────────────────────────────────────────  │ │
│  FACTURACION           │ │                      │  Eliminar cuenta                             │ │
│  > Facturas            │ │                      │  [Eliminar mi cuenta]                        │ │
│  > Serv.Ejecutados     │ │                      │                                              │ │
│                        │ └──────────────────────┴──────────────────────────────────────────────┘ │
│  OPCIONALES            │                                                                         │
│  > FUEC                │                                                                         │
│  > Mapa GPS            │                                                                         │
│                        │                                                                         │
│  ──────────────────    │                                                                         │
│  [U] Admin User        │                                                                         │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

**Notes:**
- Left-hand submenu with 4 sections: Profile, Password, Appearance, 2FA Security.
- **Profile:** Edit name and email. Option to delete the account.
- **Password:** Change the current password.
- **Appearance:** Theme selector (Light, Dark, System).
- **2FA Security:** Enable/disable two-factor authentication with TOTP.
- The `<` indicator marks the active section.
- Accessible from the user menu in the header (the [Admin] icon).

---

## VIEW SUMMARY

| #  | View                     | Primary Role       | Description                                     |
|----|--------------------------|--------------------|-------------------------------------------------|
| 0  | General Dashboard        | All                | KPIs, alerts, quick access, activity            |
| 1  | Dashboard/Calendar       | All                | Yearly view with status colors                  |
| 2  | Monthly View             | All                | Day detail for a given month                    |
| 3  | Daily Gantt              | Admin/Operations   | Fleet planner with service bars                 |
| 4  | Service Form             | Admin/Operations   | Create/edit service                             |
| 5  | Day Summary              | All                | Day's service list with statuses                |
| 6  | Vehicles List            | Admin              | Vehicle CRUD with document status               |
| 7  | Vehicle Form             | Admin              | Create/edit vehicle                             |
| 8  | Drivers List             | Admin              | Driver CRUD                                     |
| 9  | Driver Form              | Admin              | Create/edit driver                              |
| 10 | Third Parties List       | Admin              | Clients and providers CRUD                      |
| 11 | Contracts List           | Admin              | Contracts CRUD                                  |
| 12 | Billing                  | Admin/Accounting   | Executed services ready to bill                 |
| 13 | Invoice Form             | Admin/Accounting   | Create invoice from services                    |
| 14 | My Services (Mobile)     | Driver             | Day's service list                              |
| 15 | Service Detail (Mobile)  | Driver             | Run a service with GPS                          |
| 16 | Incident Modal           | All                | Register an incident                            |
| 17 | Notifications            | All                | Notifications inbox                             |
| 18 | Settings                 | All                | Profile, password, appearance, 2FA              |

---

## COLORS AND STATES

### Day States
| State      | Color   | Representation | Description                        |
|------------|---------|----------------|------------------------------------|
| Empty      | Black   | `######`       | Day with no services registered    |
| Projected  | Orange  | `@@@@@@`       | Has services, OPEN state           |
| Executed   | Green   | `######`       | All services CLOSED                |

### Service States
| State   | Indicator | Description                   |
|---------|-----------|-------------------------------|
| Open    | `Abiert`  | In progress or pending        |
| Closed  | `ok Cer`  | Finalized by the driver       |

### Vehicle States
| State        | Indicator | Description                    |
|--------------|-----------|--------------------------------|
| Active       | `Activo`  | Documents valid                |
| Caution      | `Prec.`   | Document expires in < 15 days  |
| Blocked      | `Bloq.`   | Expired document               |
| Outsourced   | `3ro`     | COD 18, linked to a provider   |

### Document States
| State        | Indicator | Description              |
|--------------|-----------|--------------------------|
| Valid        | `ok`      | Document up to date      |
| Expiring     | `!!`      | Expires in < 15 days     |
| Expired      | `XX`      | Expired document         |

### Billing States
| State     | Indicator  | Description              |
|-----------|------------|--------------------------|
| Pending   | `!! Pend`  | No invoice attached      |
| Invoiced  | `ok Fac.`  | Has an invoice number    |

---

## REUSABLE COMPONENTS

```
┌─────────────────────────────────────────────────────────────────────┐
│ COMPONENTES DE UI IDENTIFICADOS                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  DatePicker:   [15/10/2025              ]                           │
│                                                                     │
│  TimePicker:   [08:00                   ]                           │
│                                                                     │
│  Select:       [Opcion seleccionada     v]                          │
│                                                                     │
│  Search:       [Buscar...                ]                          │
│                                                                     │
│  Checkbox:     [X] Opcion marcada                                   │
│                [ ] Opcion no marcada                                │
│                                                                     │
│  Radio:        (*) Opcion 1  ( ) Opcion 2                           │
│                                                                     │
│  Textarea:     ┌─────────────────────────────────────────────┐      │
│                │ Contenido multilinea...                     │      │
│                └─────────────────────────────────────────────┘      │
│                                                                     │
│  Table:        ┌────────┬────────┬────────┐                         │
│                │ Col 1  │ Col 2  │ Col 3  │                         │
│                ├────────┼────────┼────────┤                         │
│                │ Dato 1 │ Dato 2 │ Dato 3 │                         │
│                └────────┴────────┴────────┘                         │
│                                                                     │
│  Badge:        [ok Estado] [!! Alerta] [XX Error]                   │
│                                                                     │
│  Card:         ┌─────────────────────────┐                          │
│                │  Titulo                 │                          │
│                │  ───────────────────────│                          │
│                │  Contenido de la card   │                          │
│                │  [Accion]               │                          │
│                └─────────────────────────┘                          │
│                                                                     │
│  Modal:        ┌─────────────────────────┐                          │
│       ======== │  Titulo Modal     [X]   │ ========                 │
│       ======== │  ───────────────────────│ ========                 │
│       ======== │  Contenido...           │ ========                 │
│       ======== │  [Guardar] [Cancelar]   │ ========                 │
│       ======== └─────────────────────────┘ ========                 │
│                                                                     │
│  Indicadores de estado:                                             │
│    ok = Vigente/Correcto (verde)                                    │
│    !! = Advertencia/Por vencer (amarillo/naranja)                   │
│    XX = Error/Vencido (rojo)                                        │
│    3ro = Tercerizado (azul)                                         │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

*End of ASCII Mockups - SGTE v2.0*
