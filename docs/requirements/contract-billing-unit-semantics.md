---
name: contract-billing-unit-semantics
type: refactor
scope: contracts
status: completed
priority: medium
created_date: 2026-04-19
completed_date: 2026-04-19
srs_refs: [REQ-011]
migration_strategy: modify-existing
---

# Make `services.quantity` semantically explicit via contract billing unit

## Description

Surfaced by post-audit workflow review of the 2026-04-19 cross-role UX/QA audit.

`services.quantity` is an integer field that defaults to 1 and multiplies `unit_value` at invoice time. The UI label reads just `Cantidad`. In Colombian special-transport contracting "quantity" means different things depending on the contract:

- **Per trip** (most common): quantity = number of trips on this service row.
- **Per passenger** (school contracts): quantity = head count.
- **Per day** (monthly corporate): quantity = days of service.
- **Per hour** (ad-hoc): quantity = billable hours.

The current model collapses all four into an unlabeled integer. Operators can't tell at a glance what they're entering; accounting can't validate an invoice total against the contract without reading the contract_object text field.

Proposed change: add `billing_unit_type` enum to `contracts` (viaje | pasajero | dia | hora). `services.quantity` retains its name but the form label + tooltip derive from the selected contract's `billing_unit_type`, e.g. "Cantidad (pasajeros)" with an inline hint "Contrato CT-0012 factura por pasajero."

Short-term alternative (minimum viable): just relabel the current field to `Cantidad (unidades del contrato)` + tooltip stating "la unidad de cobro depende del contrato asignado." Decouples the semantic commitment from UI clarity.

## Acceptance Criteria

- [x] `contracts.billing_unit_type` column added (modify the primary `create_contracts_table` migration) as a nullable enum with the four values; BillingUnitType PHP enum + TypeScript mirror generated via `artisan enum:typescript`.
- [x] Service form reads the selected contract's `billing_unit_type` and derives the `Cantidad` label + tooltip dynamically; fallback label for legacy null contracts is "Cantidad (unidades del contrato)." Dusk covers the relabel per contract type.
