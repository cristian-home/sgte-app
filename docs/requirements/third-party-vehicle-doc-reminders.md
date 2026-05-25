---
name: third-party-vehicle-doc-reminders
type: feat
scope: vehicles
status: completed
priority: low
created_date: 2026-04-19
completed_date: 2026-04-19
srs_refs: [REQ-004]
migration_strategy: new
---

# Automated reminder emails for third-party vehicle document expiry

## Description

Surfaced by post-audit workflow review of the 2026-04-19 cross-role UX/QA audit.

Third-party (`is_third_party = true`) vehicles have SOAT / RTM / Tarjeta de Operación expiry dates just like owned fleet, but there is no self-service way for the provider to upload fresh documents. Today operations staff manually ask the provider for updated scans, receive them over WhatsApp/email, and upload them via the `/vehicles/{id}/edit` admin form. This means SGTE ops staff silently owns compliance burden for fleets the company does not operate.

Proposed stopgap (this requirement):

1. Nightly Horizon job `ScanThirdPartyVehicleDocuments` queries `vehicles` where `is_third_party = true` and any of `soat_due_date`, `rtm_due_date`, `operation_card_due_date` falls in the next 30/7/1-day windows.
2. Groups results by `ThirdParty` (owner); sends the owner's contact email a single digest per day listing their vehicles + which documents are expiring + the exact due date.
3. Also cc's `env('SGTE_OPS_ALERT_EMAIL')` so ops has visibility.
4. Template lives in `resources/views/emails/third-party-doc-reminders.blade.php`; uses the existing notification pipeline.

Longer-term (separate requirement): a provider portal with signed-URL uploads. Out of scope here.

## Acceptance Criteria

- [x] `ScanThirdPartyVehicleDocuments` job dispatched daily via scheduler; Pest coverage asserts it groups by ThirdParty, filters on the three date windows, and queues one mail per provider with the correct vehicle list.
- [x] Email template renders provider's company name + per-vehicle doc expiry table in Spanish; Mailpit inspection verifies subject + body format.
