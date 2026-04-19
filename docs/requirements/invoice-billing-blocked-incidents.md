---
name: invoice-billing-blocked-incidents
type: fix
scope: invoices
status: pending
priority: high
created_date: 2026-04-19
completed_date:
srs_refs: [REQ-011, REQ-013]
migration_strategy: new
---

# Exclude services with billing-affecting incidents from invoice assignment

## Description

Surfaced by post-audit workflow review of the 2026-04-19 cross-role UX/QA audit.

`ServiceIncident` has an `affects_billing` boolean that's meant to signal "this run had an issue that changes what we charge" — vehicle breakdown mid-service, route truncation, customer no-show, etc. The operator sets this when logging the incident. However, the audit did not verify that `InvoiceController::attachServices` actually filters these out when an accounting user picks services to attach. If the endpoint accepts every service regardless of incident state, then invoices can silently include runs that had billable issues — a direct REQ-011 compliance gap and reversing it after the invoice is sent to the client is painful.

Step 1 (verification): read `InvoiceController::attachServices` + `services.blade`/frontend chooser. If no filter on `affects_billing` exists, the gap is confirmed.

Step 2 (fix, assuming confirmed): in `attachServices`, reject attaching a service with any `incidents()->where('affects_billing', true)->exists()`. Return 422 with a message pointing at the offending incident. Also filter the chooser's candidate query to exclude those services by default, with an opt-in "mostrar servicios con novedades facturables" toggle that requires explicit confirmation per service.

Pest regression: attach an ok service → 200; attach a service with a billing-affecting incident → 422 + message names the incident; attach same service with the toggle on + `override_justification` → 200 + activity_log entry records the override.

## Acceptance Criteria

- [ ] `InvoiceController::attachServices` rejects (422) services with any `affects_billing=true` incident unless the request explicitly carries an `override_justification`; Pest covers the three branches (clean / blocked / overridden).
- [ ] Invoice create + edit page candidate-services chooser filters out billing-blocked services by default, with a toggle to show them that requires per-service justification before attach; Dusk asserts the default-excluded state.
