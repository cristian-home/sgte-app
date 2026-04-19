---
name: sidebar-plataforma-relabel
type: fix
scope: ui
status: completed
priority: low
created_date: 2026-04-19
completed_date: 2026-04-19
srs_refs: []
migration_strategy: new
---

# Relabel sidebar group "Plataforma" to SGTE-specific copy

## Description

Surfaced by the 2026-04-19 cross-role UX/QA audit (severity: Polish).

The top-level sidebar group label reads `Plataforma` for every role (see `resources/js/components/nav-main.tsx:48`). It's a generic starter-kit string that isn't wrong but isn't evocative either — users don't think of SGTE as "a platform" but as "el sistema de gestión". Consider replacing with `SGTE`, `Módulos`, `Navegación`, or simply dropping the group label entirely (the logo already marks the app).

Needs a UX call — this is a label decision, not a bug.

See `docs/audits/2026-04-19-cross-role-audit.md#polish-plataforma` for the original observation.

## Acceptance Criteria

- [x] Decide on the new label (or remove the group label altogether) via a UX review.
- [x] `resources/js/components/nav-main.tsx` updated accordingly; Dusk sidebar assertions adjusted.

## Resolution

**Dropped the group label entirely.** The sub-group labels ("Producción", "Gestión", "Catálogos", "Facturación", "Administración", "FUEC", "GPS") already carry the navigation semantics, and the SGTE brand lives in the sidebar header — a top-level "Plataforma" label is redundant starter-kit carry-over that adds noise without adding information. Reversible if product ever wants a distinct brand label.
