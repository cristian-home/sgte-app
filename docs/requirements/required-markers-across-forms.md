---
name: required-markers-across-forms
type: fix
scope: app
status: pending
priority: low
created_date: 2026-04-19
completed_date:
srs_refs: []
migration_strategy: new
---

# Required-field markers on every create/edit form

## Description

Surfaced by the 2026-04-19 cross-role UX/QA audit, follow-up to F-006 (severity: Polish).

The audit patched the service form in-scope (`fecha *`, `contrato *`, `vehículo *`, etc.) but did not sweep the sibling create/edit forms. Follow the same pattern — suffix every required label with ` *` — across: vehicles, drivers, third-parties, contracts, invoices, users, incident-types, service-incidents, fuec-number-ranges, vehicle-locations, fuecs, and the five catalog modules (document-types, eps, pension-funds, severance-funds, incident-types).

The required fields are the ones the corresponding FormRequest lists as `required`. Check each sibling form against its `StoreRequest::rules()` and its `UpdateRequest::rules()` (rules may differ) and add the marker where the label doesn't already have one.

See `docs/audits/2026-04-19-cross-role-audit.md#f-006` for the original observation.

## Acceptance Criteria

- [ ] Every create/edit form in `resources/js/components/**/-form.tsx` (and any inline form in `resources/js/pages/**/create.tsx` / `**/edit.tsx`) has a trailing ` *` on the label of each field whose corresponding `StoreRequest` or `UpdateRequest` rule includes `'required'`.
- [ ] Dusk smoke test per module asserts the asterisk is present on the expected label set.
