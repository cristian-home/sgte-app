# Mockups ASCII - Sistema de Gestión de Transporte Especial (SGTE)

> Representaciones ASCII de las vistas principales de la aplicación.
> Ancho fijo: 100 caracteres. Sidebar: 24 chars. Contenido: 73 chars.
> Sin emojis en sidebar/header para evitar desalineación por ancho variable.

---

## Estructura General de Layout

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

**Sidebar:** Secciones agrupadas (Produccion, Administracion, Facturacion, Opcionales).
El indicador `<` marca la vista activa. Footer con usuario y configuracion.

---

## 0. DASHBOARD GENERAL (Página de Inicio)

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
│                        │ ┌──────────────────────────────────┐ ┌──────────────────────────────┐   │
│  OPCIONALES            │ │ ACCESOS RAPIDOS                  │ │ ACTIVIDAD RECIENTE           │   │
│  > FUEC                │ │ ──────────────────────────────── │ │ ──────────────────────────── │   │
│  > Mapa GPS            │ │ [Ir al Calendario Anual]         │ │ Serv SERV-102 creado  10:30  │   │
│                        │ │ [Ir al Gantt de Hoy]             │ │ Serv SERV-101 cerrado 09:45  │   │
│  ──────────────────    │ │ [Ver Resumen de Hoy]             │ │ Novedad en SERV-098   09:20  │   │
│  [U] Admin User        │ │ [+ Nuevo Servicio]               │ │ Factura F-045 creada  08:50  │   │
│                        │ └──────────────────────────────────┘ │ Dia 24/02 ejecutado   08:30  │   │
│                        │                                      └──────────────────────────────┘   │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

**Notas:**
- KPIs superiores: contadores de vehiculos, conductores, servicios del dia y facturas pendientes.
- Indicadores: `ok`=vigente, `!!`=por vencer, `XX`=vencido, `Ab`=abierto, `Ce`=cerrado.
- Panel de alertas: muestra documentos vencidos o por vencer (SOAT, RTM, Tarjeta, Licencias).
- Accesos rapidos: enlaces directos a las vistas mas usadas.
- Actividad reciente: ultimas acciones en el sistema (servicios, novedades, facturas).
- El contenido se adapta segun el rol del usuario (el conductor ve solo sus servicios).

---

## 1. DASHBOARD / CALENDARIO ANUAL

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Calendario Anual 2025                                                   │
│  SGTE                  │ ═══════════════════════════════════════════════════════════════════════ │
│                        │                                                                         │
│  PRODUCCION            │ ┌─────────────────────────────────────────────────────────────────────┐ │
│  > Calendario        < │ │ Filtros: [Ciudad: Todas v]  [Modalidad: Todas v]  [Buscar]          │ │
│  > Gantt Diario        │ └─────────────────────────────────────────────────────────────────────┘ │
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

**Notas:** Doble click en mes abre vista mensual. Dias en color: Negro (vacio),
Naranja (proyectado), Verde (ejecutado). Grid de 5 meses por fila + 2 en la ultima.

---

## 2. VISTA MENSUAL (Detalle de un mes)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│  [=] SGTE                                                       [Buscar] [Tema] [Notif] [Admin]  │
├────────────────────────┬─────────────────────────────────────────────────────────────────────────┤
│                        │ Octubre 2025                                       [<- Volver al Ano]   │
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
│                        │  Click en dia -> Opciones: [Planificador Gantt] [Resumen del Dia]       │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

**Notas:** Click en dia muestra opciones: Gantt o Resumen. Colores indican estado del dia.

---

## 3. GANTT DIARIO - Planificador de Flota

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
│                        │ Act.     │      │ EmpABC      │      │      │      │      │             │
│                        │ ─────────┼──────┼──────┼──────┼──────┼──────┼──────┼──────┼──────       │
│                        │ TOY-250  │      │      │      │[===SERV-002========]      │             │
│                        │ Act.     │      │      │      │ TurXYZ             │      │             │
│                        │ ─────────┼──────┼──────┼──────┼──────┼──────┼──────┼──────┼──────       │
│                        │ ABC-123  │//////│//////│//////│//////│//////│//////│//////│//////       │
│                        │ BLOQ.    │ [SOAT VENCIDO - 05/10/2025]  Vehiculo bloqueado              │
│                        │ ─────────┼──────┼──────┼──────┼──────┼──────┼──────┼──────┼──────       │
│                        │ XYZ-789  │      │      │[S003]│      │[S004]│      │[S005]│             │
│                        │ Act.     │      │      │SaludP│      │EmpXYZ│      │SaludP│             │
│                        │ ─────────┼──────┼──────┼──────┼──────┼──────┼──────┼──────┼──────       │
│                        │ DEF-456  │      │[S005]│      │      │      │[====SERV-006=====]        │
│                        │ Prec.    │      │Ocasio│      │      │      │ EmpABC                    │
│                        │ ─────────┼──────┼──────┼──────┼──────┼──────┼──────┼──────┼──────       │
│                        │ GHI-789  │[S007]│      │      │      │      │      │      │             │
│                        │ 3ro.     │Provee│      │      │      │      │      │      │             │
│                        │ ─────────┴──────┴──────┴──────┴──────┴──────┴──────┴──────┴──────       │
│                        │                                                                         │
│                        │ Leyenda: Act.=Activo  Prec.=RTM<15d  BLOQ.=Doc.vencido  3ro=COD 18      │
│                        │                                                                         │
│                        │ Click en celda vacia -> Nuevo Servicio                                  │
│                        │ Click en barra -> Editar Servicio                                       │
│                        │                                                                         │
│                        │ [+ Nuevo Servicio]  [FUEC (Opcional)]  [Resumen]                        │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

**Notas:**
- Eje Y: Lista de vehiculos de la flota
- Eje X: Horario del dia (06:00 - 22:00)
- Barras horizontales = Servicios asignados
- Vehiculos bloqueados en gris con documento vencido
- `//////` = fila bloqueada (vehiculo con documentos vencidos)

---

## 4. FORMULARIO DE SERVICIO (Crear/Editar)

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
│                        │ │ NOVEDADES                                                           │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ [!! Ver 2 novedades registradas]                                    │ │
│                        │ │ ┌─ Novedad #1 ──────────────────────────────────────────────────┐   │ │
│                        │ │ │ Tipo: Retraso por trafico | Registrada por: Conductor         │   │ │
│                        │ │ │ Descripcion: Accidente en autopista norte, demora 15 min      │   │ │
│                        │ │ │ [Afecta facturacion: Si | Valor adicional: $25,000]           │   │ │
│                        │ │ └───────────────────────────────────────────────────────────────┘   │ │
│                        │ │                                                                     │ │
│                        │ │ ESTADO                                                              │ │
│                        │ │ ─────────────────────────────────────────────────────────────────── │ │
│                        │ │ Estado Servicio:  (*) Abierto  ( ) Cerrado                          │ │
│                        │ │ Estado Dia:       PROYECTADO (Dia en modo edicion)                  │ │
│                        │ └─────────────────────────────────────────────────────────────────────┘ │
│                        │                                                                         │
│                        │ [Guardar]  [Cancelar]  [FUEC (Opcional)]  [+ Novedad]                   │
│                        │                                                                         │
│                        │ Notas:                                                                  │
│                        │ * Campos marcados con * son obligatorios                                │
│                        │ * Si vehiculo es COD 18 (Tercerizado), no se muestra conductor          │
│                        │ * En dia EJECUTADO, solo Contabilidad edita campos de facturacion       │
│                        │                                                                         │
└────────────────────────┴─────────────────────────────────────────────────────────────────────────┘
```

---

## 5. RESUMEN DEL DIA

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
│                        │ │ Total Servicios:     5                                              │ │
│                        │ │ Servicios Cerrados:  3  [ok]                                        │ │
│                        │ │ Servicios Abiertos:  2  [!!]                                        │ │
│                        │ │ Con Novedades:       2                                              │ │
│                        │ │ Vehiculos 3ros:      2                                              │ │
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

**Notas:**
- Estado ABIERTO: Servicio en ejecucion o pendiente
- Estado CERRADO: Servicio finalizado por conductor
- Click en fila -> Ver/Editar servicio
- Ejecutar Dia solo habilitado cuando todos los servicios esten CERRADOS

---

## 6. LISTADO DE VEHICULOS (CRUD)

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

## 7. FORMULARIO DE VEHICULO (Crear/Editar)

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

## 8. LISTADO DE CONDUCTORES (CRUD)

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

## 9. FORMULARIO DE CONDUCTOR (Crear/Editar)

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

## 10. LISTADO DE TERCEROS (CRUD)

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

**Notas:** Rol: [Cli]=Cliente  [Pro]=Proveedor. Un tercero puede ser ambos.

---

## 11. LISTADO DE CONTRATOS (CRUD)

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

## 12. FACTURACION - Servicios Ejecutados

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

**Notas:** Solo servicios de dias EJECUTADO. !! Pend=Sin factura. ok Fac=Con factura.

---

## 13. FORMULARIO DE FACTURA (Crear/Editar)

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

**Notas:** Solo servicios del mismo cliente. Solo EJECUTADOS sin factura. Total incluye novedades.

---

## 14. INTERFAZ MOVIL - CONDUCTOR (Mis Servicios)

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

**Notas:** Mobile-first para conductores. Estados: !! Pendiente | En Curso | ok Completado

---

## 15. INTERFAZ MOVIL - Detalle de Servicio (Conductor)

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

**Notas:** Vista durante ejecucion. Boton grande para finalizar. GPS opcional.

---

## 16. MODAL - Registrar Novedad

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

**Notas:** Modal sobre pantalla actual. Tipo de novedad es catalogo configurable.
Afectacion a facturacion es opcional. Puede adjuntar evidencia fotografica.

---

## 17. NOTIFICACIONES / BANDEJA DE ENTRADA

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

## 18. SETTINGS / CONFIGURACION DE CUENTA

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

**Notas:**
- Submenu lateral izquierdo con 4 secciones: Perfil, Contrasena, Apariencia, Seguridad 2FA.
- **Perfil:** Editar nombre y correo. Opcion de eliminar cuenta.
- **Contrasena:** Cambiar contrasena actual por una nueva.
- **Apariencia:** Selector de tema (Claro, Oscuro, Sistema).
- **Seguridad 2FA:** Activar/desactivar autenticacion de dos factores con TOTP.
- El indicador `<` marca la seccion activa.
- Accesible desde el menu de usuario en el header (icono [Admin]).

---

## RESUMEN DE VISTAS

| #  | Vista                    | Rol Principal      | Descripcion                                     |
|----|--------------------------|--------------------|-------------------------------------------------|
| 0  | Dashboard General        | Todos              | KPIs, alertas, accesos rapidos, actividad       |
| 1  | Dashboard/Calendario     | Todos              | Vista anual con colores de estado               |
| 2  | Vista Mensual            | Todos              | Detalle de dias de un mes                       |
| 3  | Gantt Diario             | Admin/Operacion    | Planificador de flota con barras de servicio    |
| 4  | Formulario de Servicio   | Admin/Operacion    | Crear/editar servicio                           |
| 5  | Resumen del Dia          | Todos              | Lista de servicios del dia con estados          |
| 6  | Listado de Vehiculos     | Admin              | CRUD de vehiculos con estado de documentos      |
| 7  | Formulario de Vehiculo   | Admin              | Crear/editar vehiculo                           |
| 8  | Listado de Conductores   | Admin              | CRUD de conductores                             |
| 9  | Formulario de Conductor  | Admin              | Crear/editar conductor                          |
| 10 | Listado de Terceros      | Admin              | CRUD de clientes y proveedores                  |
| 11 | Listado de Contratos     | Admin              | CRUD de contratos                               |
| 12 | Facturacion              | Admin/Contabilidad | Servicios ejecutados listos para facturar       |
| 13 | Formulario de Factura    | Admin/Contabilidad | Crear factura con servicios                     |
| 14 | Mis Servicios (Mobile)   | Conductor          | Lista de servicios del dia                      |
| 15 | Detalle Servicio (Mobile)| Conductor          | Ejecutar servicio con GPS                       |
| 16 | Modal Novedad            | Todos              | Registrar novedad/incidencia                    |
| 17 | Notificaciones           | Todos              | Bandeja de notificaciones                       |
| 18 | Settings/Configuracion   | Todos              | Perfil, contrasena, apariencia, 2FA             |

---

## COLORES Y ESTADOS

### Estados del Dia
| Estado     | Color   | Representacion | Descripcion                        |
|------------|---------|----------------|------------------------------------|
| Sin datos  | Negro   | `######`       | Dia sin servicios registrados      |
| Proyectado | Naranja | `@@@@@@`       | Tiene servicios, estado ABIERTO    |
| Ejecutado  | Verde   | `######`       | Todos los servicios CERRADOS       |

### Estados de Servicio
| Estado  | Indicador | Descripcion                   |
|---------|-----------|-------------------------------|
| Abierto | `Abiert`  | En ejecucion o pendiente      |
| Cerrado | `ok Cer`  | Finalizado por conductor      |

### Estados de Vehiculo
| Estado       | Indicador | Descripcion                    |
|--------------|-----------|--------------------------------|
| Activo       | `Activo`  | Documentos vigentes            |
| Precaucion   | `Prec.`   | Documento vence en < 15 dias   |
| Bloqueado    | `Bloq.`   | Documento vencido              |
| Tercerizado  | `3ro`     | COD 18, vinculado a proveedor  |

### Estados de Documentos
| Estado     | Indicador | Descripcion              |
|------------|-----------|--------------------------|
| Vigente    | `ok`      | Documento al dia         |
| Por vencer | `!!`      | Vence en < 15 dias       |
| Vencido    | `XX`      | Documento vencido        |

### Estados de Facturacion
| Estado    | Indicador  | Descripcion              |
|-----------|------------|--------------------------|
| Pendiente | `!! Pend`  | Sin factura asociada     |
| Facturado | `ok Fac.`  | Con numero de factura    |

---

## COMPONENTES REUTILIZABLES

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

*Fin de los Mockups ASCII - SGTE v2.0*
