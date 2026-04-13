# Phase 4: Billing and Audit

> **Status: PENDING** — Requires Phase 2 (completed); Phase 3 recommended

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

### 4.1 Service billing (REQ-011)

- Link invoice number to closed services
- Invoice form:
  - Invoice number
  - Total amount (computed from service + incidents)
  - Issue date
  - Payment status (Pendiente, Pagada, Anulada)
- Billing view: list of services pending invoicing
- Filters: by tercero, by date, by payment status
- Total amount computed taking billing-affecting incidents into account

### 4.2 Service-invoice association

- A closed service can be associated with an invoice
- View to select multiple services from the same tercero and link them to a single invoice
- Only the Administrador and Contabilidad roles can bill
- Invoice PDF generation (informational, not fiscal)

### 4.3 Accounting immutability (REQ-009 complement)

- In the EJECUTADO state:
  - Operación role: read-only
  - Administrador role: editing with mandatory justification
  - Contabilidad role: can associate invoices and edit accounting fields
- Every modification of an executed record must be recorded in the audit log

### 4.4 Audit log

Implement using `owen-it/laravel-auditing`:

- Automatically record changes on auditable models:
  - Servicio
  - Factura
  - EstadoDia
  - Contrato
- Data captured per change:
  - User who performed the change
  - Date and time
  - Previous value
  - New value
  - Justification (additional field for executed records)
- Audit query view for Administrador
- Filters: by model, by user, by date range

---

## Packages

| Package | Use |
| ------- | --- |
| `owen-it/laravel-auditing` | Automatic audit log |
| `barryvdh/laravel-dompdf` | Invoice PDF generation |

## Completion criteria

- [ ] Closed services can be linked to invoices
- [ ] Invoice total amount accounts for billing-affecting incidents
- [ ] Only Admin and Contabilidad can bill
- [ ] Informational invoice PDF generation
- [ ] Audit log records all changes on sensitive models
- [ ] Modifying executed records requires justification
- [ ] Working audit query view
