# Phase 2: Operational Core

> **Status: COMPLETED** — Finished 2026-03-07

## Objective

Implement the core functionality of the system: calendar, Gantt planner, service form, and day state control.

## Covered requirements

- **REQ-001** - Calendar and State Management
- **REQ-002** - Daily Fleet View (Gantt)
- **REQ-003** - Service Form
- **REQ-008** - Day Summary
- **REQ-009** - Accounting Immutability Control

## Dependencies

- Phase 1 completed (migrations, CRUDs, auth)

---

## Tasks

### 2.1 Annual calendar (REQ-001) ✅

- 12-month view with color indicators per day status
  - **Black**: no services
  - **Orange**: PROYECTADO (at least one service registered)
  - **Green**: EJECUTADO (all services closed)
- Click on month → detailed day view with prev/next navigation
- Click on day → inline load of that day's services
- Custom React component with Inertia.js (no FullCalendar dependency)

### 2.2 Daily Gantt planner (REQ-002) ✅

- Y axis: list of fleet vehicles
- X axis: hours of the day (00:00 - 24:00)
- Horizontal bars per service with duration
- Filter by vehicle municipality (MunicipalityCombobox)
- Vehicles with expired documents: row greyed out, assignment blocked
- Click on empty cell → new service form (vehicle and time pre-selected)
- Click on existing bar → navigate to service detail
- Custom React component (no external Gantt library)

### 2.3 Service form (REQ-003) ✅

- Required fields: placa, conductor, tercero/contrato, origen, destino, hora inicio, duración
- Logic for outsourced vehicles (COD 18):
  - Hide driver field
  - Show associated provider
- Contract selector with validity check
- Option for a temporary generic contract
- Schedule conflict validation
- Driver validation: valid license + social security
- Execution fields: actual start time, actual end time, actual duration (computed)
- Visual indicator for registered incidents
- Billing fields: group, unit value, quantity, payment method
- Status: Abierto / Cerrado

### 2.4 Day summary (REQ-008) ✅

- Consolidated table with executive statistics (total, closed, open, with incidents, outsourced)
- "Ejecutar Día" button enabled only when all services are closed
- CSV export

### 2.5 Day states and locking (REQ-009) ✅

- Transition logic: No data → PROYECTADO → EJECUTADO
- Automatically PROYECTADO when the first service of the day is registered
- EJECUTADO only if all services are closed
- Edit lock in the EJECUTADO state (except Administrador with justification and Contabilidad)
- Mandatory justification when modifying executed records
- Activity log with spatie/laravel-activitylog

---

## Technical decisions

### Gantt and Calendar

**Custom React components** were chosen instead of an external Gantt/calendar library. This enabled:
1. Full control over UI/UX with Tailwind CSS and shadcn/ui
2. Native integration with Inertia props and navigation
3. No additional JS dependencies or licensing costs
4. Adequate performance for the expected volume

### Geographic catalog

The DIVIPOLA catalog (Colombian departments and municipalities) was implemented to support Gantt filters and service forms. It includes a reusable `MunicipalityCombobox` component with search and grouping by department.

### Service detail view

The service show view was redesigned with a card layout, a timeline bar (planned vs actual), a billing summary, and incident indicators.

---

## Requirement documentation

Each feature in this phase has a detailed document in `docs/requirements/`:

| Requirement | Document |
| ----------- | -------- |
| Annual calendar | [annual-calendar.md](../requirements/annual-calendar.md) |
| Daily Gantt | [daily-gantt.md](../requirements/daily-gantt.md) |
| Service form | [service-form.md](../requirements/service-form.md) |
| Day summary | [day-summary.md](../requirements/day-summary.md) |
| Day status logic | [day-status-logic.md](../requirements/day-status-logic.md) |
| Service detail redesign | [service-detail-redesign.md](../requirements/service-detail-redesign.md) |
| Departments/municipalities catalog | [departments-municipalities-catalog.md](../requirements/departments-municipalities-catalog.md) |
| Municipality combobox | [municipality-combobox.md](../requirements/municipality-combobox.md) |

## Completion criteria

- [x] Annual calendar with day-status colors
- [x] Working daily Gantt with click-to-create
- [x] Municipality filter in the Gantt
- [x] Service form with all validations
- [x] Outsourced vehicle logic (COD 18)
- [x] Day summary with execute button and CSV export
- [x] Edit lock on executed days
- [x] Mandatory justification to edit executed records (admin)

---

## Blockers for Phase 3

None. Calendar, Gantt, service form, day states, and day summary are fully implemented and tested.
