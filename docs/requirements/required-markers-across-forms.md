---
name: required-markers-across-forms
type: fix
scope: app
status: completed
priority: low
created_date: 2026-04-19
completed_date: 2026-04-19
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

- [x] Every create/edit form in `resources/js/components/**/-form.tsx` (and any inline form in `resources/js/pages/**/create.tsx` / `**/edit.tsx`) has a trailing ` *` on the label of each field whose corresponding `StoreRequest` or `UpdateRequest` rule includes `'required'`.
- [x] Dusk smoke test per module asserts the asterisk is present on the expected label set.

## Audit results

- Already marked: `contract-form.tsx`, `driver-form.tsx`, `third-party-form.tsx`, `invoice-form.tsx`, `service-incident-form.tsx`, `service-form.tsx`, `fuec-number-range-form.tsx`, `vehicle-location-form.tsx`, `users/create.tsx`, `users/edit.tsx`.
- Fixed in this pass: `vehicles/vehicle-form.tsx` (14 required labels), `incident-types/create.tsx` + `incident-types/edit.tsx` (code / nombre / severidad).
- Skipped (still Blueprint stub pages, no real form fields yet): `document-types`, `eps`, `pension-funds`, `severance-funds` — tracked under the `project_blueprint_scaffolds_deferred` memory. `fuecs/edit.tsx` is also a stub (FUEC has no edit flow by design, it's cancel-only). `fuecs/create.tsx` is the generator picker with no required-text-input labels.
