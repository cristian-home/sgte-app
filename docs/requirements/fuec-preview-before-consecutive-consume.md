---
name: fuec-preview-before-consecutive-consume
type: feat
scope: fuec
status: completed
priority: medium
created_date: 2026-04-19
completed_date: 2026-04-19
srs_refs: [REQ-007]
migration_strategy: new
---

# Render a full FUEC preview before consuming a MinTransporte consecutive

## Description

Surfaced by post-audit workflow review of the 2026-04-19 cross-role UX/QA audit.

The FUEC module is intentionally cancel-only per MinTransporte — once a consecutive number is issued, editing is prohibited. The correct remedy for any defect (a typo in the contract customer's `company_name`, a stale third-party address, a wrong driver assignment inherited from the service) is to cancel and re-generate, which burns the original consecutive. Over time the range log looks like "generated 1001 (cancelled), generated 1002 (cancelled), generated 1003 (active)" — which is fine for the regulator but painful for the operator doing data-quality cleanup and faster-than-expected for small ranges.

Proposed mitigation: a non-committing "Vista previa" step on `/fuecs/create`. The current flow is [pick service] → [submit] → [consume consecutive + render PDF + store]. New flow: [pick service] → [Vista previa] renders the exact PDF in an inline iframe from form state (no database write, no consecutive consumed) → [Confirmar y generar] commits.

Implementation: extract the PDF render from `FuecGenerator::generateFor()` so the Blade template can render with a synthetic `Fuec` view model that isn't persisted. The preview runs the pre-generation validation gauntlet too, so invalid data never even gets previewed.

Bonus: add a `cancellation_reason` text column to `fuecs` so every cancel logs *why* a number was burned — audit/compliance can distinguish "typo fix" from "service cancelled legitimately."

## Acceptance Criteria

- [x] `/fuecs/create` surfaces a "Vista previa" button that renders the would-be PDF inline without creating a `Fuec` row or incrementing the range; Pest covers the preview endpoint (authz, validation gauntlet runs, no DB write).
- [x] `fuecs.cancellation_reason` column added (modify primary migration); `POST /fuecs/{fuec}/cancel` requires it; audit log entry for the cancellation includes the reason.
