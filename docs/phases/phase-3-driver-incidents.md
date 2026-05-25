# Phase 3: Driver and Incidents

> **Status: COMPLETED** — Finished 2026-03-22

## Objective

Implement the driver interface for recording actual times, incident management, and email notifications.

## Covered requirements

- **REQ-012** - Incident Management
- **REQ-013** - Email Notifications
- Partial **REQ-003** - Start/end confirmation by driver

## Dependencies

- Phase 2 completed (service form, day states)

---

## Tasks

### 3.1 Driver interface ✅

- Simplified view of services assigned to the authenticated driver
- Action buttons:
  - **Confirmar inicio**: records `hora_inicio_real`
  - **Confirmar finalización**: records `hora_fin_real`
- Mobile-first design with card layout (drivers use the system from their phones)
- Access limited to services of the current day
- User-Driver relation via `user_id` in the drivers table (new migration)
- Sidebar entry "Conductor > Mis Servicios" with the `services.register-times` permission

### 3.2 Incident management (REQ-012) ✅

- Incident form accessible from:
  - Service detail (button "Registrar Novedad" with service_id prefilled)
  - General incidents listing
- Fields:
  - Incident type (configurable dropdown)
  - Detailed description
  - Indicator of billing impact
  - Additional value or discount (visible only if it affects billing)
- Automatic recording of: user (registrar_id), date/time (reported_at), whether driver reported (is_driver_report)
- Prefill `affects_billing` from the default of the selected type
- Visual indicator on the service when it has incidents
- Inline edit/delete actions in the service incidents table
- Redirect to the service view after create/edit/delete

### 3.3 Configurable incident types ✅

- Full admin CRUD with DataTable, form, and permissions (4 new)
- Sidebar entry under Catálogos: "Tipos de Novedad"
- Fields: code (unique), name, severity (Select with enum), affects billing (Switch), description
- Severity badge with differentiated colors (secondary/default/destructive)
- 16 tests including validation and authorization

### 3.4 Email notifications (REQ-013) ✅

5 notifications implemented with Laravel Notifications (ShouldQueue):

| Event | Recipient | Class |
| ----- | --------- | ----- |
| Service assigned to driver | Driver (linked User) | `ServiceAssignedNotification` |
| Vehicle document near expiration (30/15/5 days) | Administrators | `DocumentExpirationNotification` |
| Driver license near expiration (30/15/5 days) | Administrators | `LicenseExpirationNotification` |
| Incident that affects billing registered | Admin + Contabilidad | `BillingIncidentNotification` |
| Day executed | Contabilidad | `DayExecutedNotification` |

- `app:check-expirations` command scheduled daily at 07:00
- Inline dispatch in controllers (ServiceController, ServiceIncidentController, DayStatusController)
- 10 tests covering rendering and dispatch

---

## Requirement documentation

| Requirement | Document |
| ----------- | -------- |
| Incident types admin CRUD | [incident-types-admin-crud.md](../requirements/incident-types-admin-crud.md) |
| Service incidents management | [service-incidents-management.md](../requirements/service-incidents-management.md) |
| Driver interface | [driver-interface.md](../requirements/driver-interface.md) |
| Email notifications | [email-notifications.md](../requirements/email-notifications.md) |

## Completion criteria

- [x] Driver can confirm start and end of service from their interface
- [x] Incidents can be registered from the driver interface and the service form
- [x] Incidents with billing impact calculate additional value/discount
- [x] Visual incident indicator in service form and day summary
- [x] Email notification when a service is assigned to a driver
- [x] Automatic alerts for document and license expirations
- [x] Notification to accounting when a day is executed

---

## Blockers for Phase 4

None. Incidents, driver interface, and notifications are fully implemented and tested.
