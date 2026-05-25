---
name: driver-preflight-decline-action
type: feat
scope: driver
status: completed
priority: medium
created_date: 2026-04-19
completed_date: 2026-04-19
srs_refs: [REQ-009, REQ-012]
migration_strategy: modify-existing
---

# Driver pre-flight decline / reassignment flow

## Description

Surfaced by post-audit workflow review of the 2026-04-19 cross-role UX/QA audit.

Drivers currently have three actions on their assigned services: Confirmar Inicio, Confirmar Fin, Registrar Novedad. All three assume the service is already in progress or completed. There is no in-system way for a driver to say "I cannot run this service" *before* confirmStart fires — sickness, vehicle mechanical issue discovered pre-departure, double-booking, licence issue, etc. Today that conversation happens on WhatsApp/phone and ops manually edit the service, leaving the audit log silent on the original decline reason.

Proposed endpoint: `POST /driver/services/{service}/decline` with `{ reason_text, incident_type_id }`. Behavior:

1. Authorizes the same cross-driver 403 check used by `confirmStart`.
2. Creates a `ServiceIncident` (severity = high, `affects_billing = false`, incident_type pinned to "Rechazo previo al servicio").
3. Sets `services.driver_declined_at = now()` (new column).
4. Leaves `service_status = open` so ops can reassign.
5. Fires the existing email notification pipeline to the service's contract owner / ops manager.

Ops-side UI: surface declined services as a highlighted group on the Day Summary ("Pendientes de reasignación"), and a badge on the Gantt row.

## Acceptance Criteria

- [x] `/driver/services/{service}/decline` endpoint with Pest coverage for authorization, validation, and the side effects (incident created + `driver_declined_at` set + notification dispatched).
- [x] Driver service-detail page exposes a "Declinar servicio" action (disabled once `confirmStart` has fired); Day Summary renders a "Pendientes de reasignación" section filtered on `driver_declined_at IS NOT NULL AND service_status = open`; Dusk covers both UI assertions.
