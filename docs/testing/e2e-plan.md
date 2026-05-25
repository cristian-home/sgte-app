# SGTE — End-to-End Test Plan

> **Status (2026-05-18):** Phase 1 in review. See [Phase status](#phase-status) below.

## Purpose

Exercise every user-facing workflow in SGTE end-to-end, with three goals (in priority order):

1. **Catch logical failures** that unit tests miss — driver/vehicle double-booking, expired documents allowing assignment, contract date overruns, retroactive-entry edge cases, timezone day-boundary bugs, etc.
2. **Verify RBAC** — every route enforced at every layer (Super Admin bypass → route `can:` middleware → FormRequest `authorize()`). All 5 roles tested.
3. **Smoke every page and form** — no 500s on navigation, every form's happy path submits, every validation rule fires on bad input.

## Scope

**Modules — all of them.** Vehicles, Drivers, Third Parties, Contracts, Services + Planificador, Day Summary, Day Statuses, Incidents (Novedades), Invoices, FUEC, GPS / Vehicle Locations, Users, Roles, Permissions, Audit Log, Data Imports, Catalogs (Document Types, EPS, Pension Funds, Severance Funds, Incident Types), Settings (Profile, Password, Appearance, 2FA), Auth (Fortify routes).

**Roles — all five.** Super Admin, Administrador, Operación, Conductor, Contabilidad.

**Conflict depth — hard cases.** Beyond validation errors and 403s, the plan explicitly hunts for: driver double-booking; vehicle double-booking; assignment to vehicles with expired SOAT / RTM / Tarjeta de Operación; assignment to drivers with expired license or category-mismatch; services scheduled outside contract validity; FUEC generation against invalid prerequisites; cross-timezone day-boundary edge cases; EJECUTADO day immutability except justified-admin/accounting-fields exceptions; FUEC range exhaustion; invoice billing-impact correctness when incidents are involved.

## Sequencing (phase-by-phase, check-in after each)

| # | Phase | Output | Status |
|---|---|---|---|
| 1 | **Inventory + intent.** Map every route, controller, Inertia page, sidebar entry, and model. Synthesize per-role workflows from SRS/ADRs/data-model. | [`nav-action-map.md`](./nav-action-map.md), [`role-workflows.md`](./role-workflows.md) | **In review** |
| 2 | **Scenario catalog.** For every module + role, enumerate happy-path / validation / RBAC / logical-conflict scenarios with expected outcomes. | `scenario-catalog.md` | Pending |
| 3 | **Playwright MCP exploration.** Drive the live app through the catalog. Log findings (no fixes). Update catalog with discovered surprises. | `bug-log.md` (+ updates to `scenario-catalog.md`) | Pending |
| 4 | **Pest 4 browser tests.** Translate the catalog into committed browser tests under `tests/Browser/`. Known-failing scenarios from `bug-log.md` use `->todo()`. | `tests/Browser/**/*.php` | Pending |

After each phase the doc set is reviewed with the user before advancing.

## Bug-handling policy

**Log-only during Phase 3.** I do not fix bugs while exploring — fixes are a separate decision the user makes after the bug log is reviewed.

- Every finding goes into `bug-log.md` with: severity (P0 / P1 / P2), reproduction steps, expected vs. observed, suspected root cause (one line), affected route/file if known.
- Phase 4 still writes the regression test, but if the scenario currently fails, the test is wrapped in `->todo()` with a `// bug-log:NN` reference. When the bug is later fixed, the `todo` is removed in the same commit as the fix.
- Severity guide:
  - **P0** — data loss, security boundary breach, RBAC bypass, immutability rule violated (e.g., Operación can edit EJECUTADO service).
  - **P1** — logical failure that lets invalid state through (e.g., driver double-booking succeeds, FUEC generated with expired vehicle doc).
  - **P2** — UX / validation / cosmetic (missing required-field error, wrong redirect target, label typo).

## Test environment & state reset

- **Container.** All tests run inside the Sail devcontainer (PHP 8.5.5, real Postgres / Redis / Typesense / MinIO / Mailpit). From the host: `./vendor/bin/sail …`. Direct `php artisan test` on the host is forbidden per CLAUDE.md.
- **Reset between scenario groups.** `./vendor/bin/sail artisan migrate:fresh --seed` before each independent scenario block. The init-data seeder creates the reference users (`admin@sgte.app`, `operator@sgte.app`, `driver@sgte.app`, `accounting@sgte.app`, all password `password`; Super Admin from `SUPER_ADMIN_USER` env).
- **Playwright MCP profile.** Persistent user-data-dir at `.claude/playwright-profile/` keeps login state. Switch roles by visiting `/login` and re-authing.
- **Feature flags.** `SGTE_FUEC_ENABLED=true` and `SGTE_GPS_ENABLED=true` must be set in `.env` to exercise those routes; otherwise the middleware returns 404 and those rows in the catalog are marked "skipped — flag off."

## Deliverables (artifact map)

```
docs/testing/
├── e2e-plan.md            ← this file
├── nav-action-map.md      ← Phase 1: routes ↔ controllers ↔ pages ↔ sidebar ↔ models
├── role-workflows.md      ← Phase 1: per-role narratives + invariants per module
├── scenario-catalog.md    ← Phase 2: enumerated test cases with expected outcomes
└── bug-log.md             ← Phase 3: findings, severities, repro steps

tests/Browser/             ← Phase 4: Pest 4 browser tests, organized by module
├── Auth/
├── Services/
├── Planificador/
├── DaySummary/
├── Incidents/
├── Invoices/
├── Fuec/
├── Vehicles/
├── Drivers/
├── ThirdParties/
├── Contracts/
├── Users/
├── RolesPermissions/
├── AuditLog/
├── Catalogs/
├── Gps/
├── Imports/
├── Driver/                ← driver-portal flows
└── Settings/
```

## Phase status

| Phase | Output | Status |
|---|---|---|
| 1. Inventory + intent | `nav-action-map.md`, `role-workflows.md` | ✅ Complete — open questions resolved 2026-05-18 |
| 2. Scenario catalog | `scenario-catalog.md` | ✅ Draft complete — awaiting review |
| 3. Playwright MCP exploration | `bug-log.md` updates | ✅ Initial pass complete — 6 spec-divergence bugs logged + 7 positive confirmations |
| 4. Pest 4 browser tests | `tests/Browser/**` | ✅ First cut: `tests/Browser/E2eHardCasesTest.php` — **20 passing, 5 todos (bug-log:BUG-01/03/05/06), 1 skipped**. Covers the highest-value logical conflicts + LAYER probes. Remaining ~330 catalog scenarios are smoke / happy-path / form-rendering, largely covered by the 28 pre-existing browser tests in this directory; incremental top-up later. |

## Resolved questions (Phase 1, answered 2026-05-18)

These behaviors were ambiguous in the docs and have been pinned down by the user. They are now treated as pass criteria in Phase 2 scenarios. Phase 3 still verifies the live system actually behaves this way; any divergence becomes a bug log entry.

| # | Topic | Resolution |
|---|---|---|
| 1 | License category mapping | **Permissive** ("minimum category"). Bus/Buseta → allow `{C2, C3}`; Van/Automobile → allow `{C1, C2, C3}`. Verified in `ServiceStoreRequest::LICENSE_CATEGORY_MAP`. |
| 2 | Driver double-booking | **Blocked** (same enforcement as vehicle conflict). |
| 3 | EJECUTADO day reversal | **Permanent for Admin/Operator; Super Admin may override with justification** (10–500 chars). Currently no guard — bug-log:BUG-05. Admin field-edit / Accounting field-edit / billing-impacting incident creation still allowed on EJECUTADO day. |
| 4 | New service on EJECUTADO day | **Admin can late-add with justification**; other roles rejected; day stays EJECUTADO. Currently rejected for everyone — bug-log:BUG-03. |
| 5 | Generic contract auto-naming | Format `GEN-NNNN-YYYY` (per-year sequential, zero-padded) — matches existing manual `/contracts` path. Earlier "Generic Contract #N" reading retracted 2026-05-18. Auto-creation on out-of-window service date still needs to land — bug-log:BUG-01. |
| 6 | `has_social_security = false` | **Hard block** (amended 2026-05-18). Active SS is mandatory for service assignment. Rationale: legal liability if a driver without active SS is in an accident. Earlier "warn + auto-incident" reading retracted. |
| 7 | Multi-FUEC per service | Multiple FUECs over the service lifetime; **one active at a time.** New FUEC **auto-cancels** the previous in the same transaction with reason `"Superseded by new FUEC generation"`. Currently requires manual cancel — bug-log:BUG-06. |
| 8 | Tercero dual-flag (customer + provider) | **Fully supported.** Same entity may own outsourced vehicles AND be the client on contracts/invoices. |

A few inferred behaviors remain to be probed during Phase 3 — they don't block Phase 2 scenario drafting because the catalog will simply assert both "what should happen per Q3/Q4/Q7" *and* what we actually observe. Open in Phase 3:

- Generic-contract lifetime (does closing the service remove it, or is it persisted for audit?).
- Whether new-FUEC supersedes the previous automatically vs. requires manual cancel first.
- Which role is allowed to late-add a service to an EJECUTADO day, and how it's recorded in the audit log.
- FUEC number-range activation atomicity (no two `active = true` simultaneously).
