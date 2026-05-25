# Phase 4: Billing and Audit

> **Status: ✅ COMPLETED** — Invoice CRUD + service-invoice association + informational PDF generation + REQ-009 accounting-immutability justification UX all merged to `develop` (rebuilds `invoices-crud`, `service-incidents-crud`, `invoice-service-assignment`, `invoice-pdf-generation`, `audit-log-enhancements`). Requires Phase 2 (completed); Phase 3 recommended.

## Objective

Implement the service billing module, enforce accounting immutability of executed records, and establish the audit log.

## Covered requirements

- **REQ-011** - Service Billing
- **REQ-009** - Accounting Immutability Control (complement)

## Dependencies

- Phase 2 completed (day states, closed services)
- Phase 3 recommended (incidents may affect billing)

---

## Tasks

### 4.1 Service billing (REQ-011) — ✅ done

- [x] Link invoice number to closed services — `invoice-service-assignment` merged.
- [x] Invoice form with Invoice number / Total amount / Issue date / Payment status — `invoices-crud` merged.
- [ ] Billing view: list of services pending invoicing (dedicated `/billing/pending-services` screen) — deferred to a future requirement; currently surfaced per-invoice via the `<ServicePickerDialog />`.
- [x] Filters (by tercero, by payment status) on the invoices index — `invoices-crud` merged. Date-range filter deferred.
- [x] Total amount computed taking billing-affecting incidents into account — `App\Services\InvoiceTotalCalculator` (`invoice-service-assignment` merged).

### 4.2 Service-invoice association — ✅ done (PDF pending)

- [x] A closed service can be associated with an invoice — `invoice-service-assignment` merged.
- [x] View to select multiple services from the same tercero and link them to a single invoice — `<ServicePickerDialog />` (`invoice-service-assignment` merged).
- [x] Only the Administrador and Contabilidad roles can bill — `ASSIGN_SERVICES_TO_INVOICES` now enforced at route + controller + UI (`invoice-service-assignment` merged).
- [x] Invoice PDF generation (informational, not fiscal) — `invoice-pdf-generation` merged. `barryvdh/laravel-dompdf` ^3.1 installed; `GET /invoices/{invoice}/pdf` streams inline; INFORMATIVO badge + footer disclaimer on every page.

### 4.3 Accounting immutability (REQ-009 complement) — ✅ done

- [x] In the EJECUTADO state:
  - Operación role: read-only — enforced by `ServiceUpdateRequest::authorize()` (403 for operators).
  - Administrador role: editing with mandatory justification — `ServiceUpdateRequest::rules()` requires `justification` min:10, max:500.
  - Contabilidad role: can associate invoices and edit accounting fields — accounting branch of `ServiceUpdateRequest::rules()` returns billing-only field list.
- [x] Every modification of an executed record recorded in the audit log — `ServiceController@update` writes an `activity()` entry with `properties => ['justification' => ..., 'edited_on_executed_day' => true]` + causer; `audit-log-enhancements` merged — `/audit-log` surfaces these via filters (subject_type, causer, event, date range) + a `<Sheet>` detail view that renders the justification, the before/after diff, and the full properties bag. A destructive-variant `<Alert>` banner on the service edit form makes the compliance contract explicit before typing.

### 4.4 Audit log

Backed by **`spatie/laravel-activitylog`** (already installed and trait'd on all domain models). REQ-009 (Accounting Immutability) is served by this package, with an admin viewer at `/audit-log` (`AuditLogController@index`).

- Automatically record changes on auditable models via the `LogsActivity` trait:
  - Service
  - Invoice
  - DayStatus
  - Contract
- Data captured per activity entry (via spatie defaults):
  - Causer (user who performed the change)
  - Timestamp
  - Old attributes / attributes (before/after)
  - Description
  - Subject (the model affected)
- ✅ done — `audit-log-enhancements` merged. Justification stored in `properties.justification` + `properties.edited_on_executed_day`. `/audit-log` now carries filters (subject_type, causer, event, date range) and a detail Sheet rendering the full diff + justification.

---

## Packages

| Package | Use |
| ------- | --- |
| `spatie/laravel-activitylog` | Automatic audit log (already installed and trait'd on all domain models) |
| `barryvdh/laravel-dompdf` | Invoice PDF generation (pending — not yet in composer.json) |

## Completion criteria

- [ ] Closed services can be linked to invoices
- [ ] Invoice total amount accounts for billing-affecting incidents
- [ ] Only Admin and Contabilidad can bill
- [ ] Informational invoice PDF generation
- [ ] Audit log records all changes on sensitive models
- [ ] Modifying executed records requires justification
- [ ] Working audit query view
