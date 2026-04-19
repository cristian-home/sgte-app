---
name: cross-role-ux-qa-audit
type: fix
scope: app
status: pending
priority: high
created_date: 2026-04-19
completed_date:
srs_refs: []
migration_strategy: new
---

# Cross-Role UX / QA Audit

## Description

Conduct an **exhaustive** cross-role UX and QA audit of the SGTE app using the Playwright MCP for all five reference roles — admin, operator, driver, accounting, and super-admin. Walk every sidebar entry, every button, every primary action exposed to each role; catalog every finding; triage findings into severities (blocker / major / minor / polish / orthogonal); fix blockers + majors + minors in-branch with regression coverage; spawn minimal follow-up requirement stubs for polish + orthogonal items.

**Why now**: all 25 requirement docs are `completed` and all 5 phases in `docs/phases/README.md` are ✅. The app has recently absorbed three large landings — audit-log-enhancements (REQ-009), fuec-generation (REQ-007), gps-tracking (REQ-010) — plus six Blueprint CRUD rebuilds. Accumulated risk surface: Spanish diacritic misses, missing breadcrumbs, missing required-field markers, inconsistent DataTable filter shapes, permission-gate drift (UI shows what the backend 403s, or the inverse), broken empty states, stale Blueprint remnants, sibling label drift, and mockup-vs-code divergence flagged (but not verified) in `/home/cristian/.claude/plans/misty-sniffing-steele.md`. This requirement is the quality gate before the next client demo and should run independently — do not trust any prior plan's claim that label polish has been done.

## Acceptance Criteria

- [ ] AC-1: `docs/audits/` folder exists and contains `2026-04-19-cross-role-audit.md` as the findings report. Folder is new to this branch.
- [ ] AC-2: The findings report covers five role sweeps: `admin@sgte.app`, `operator@sgte.app`, `driver@sgte.app`, `accounting@sgte.app`, and super-admin (email from `SUPER_ADMIN_USER`). All five sweeps are marked complete in the report.
- [ ] AC-3: Every finding has a row in the report containing route, role, severity, screenshot path (relative to repo), reproduction steps, suggested fix, and — if spawning a follow-up — the target `docs/requirements/*.md` filename.
- [ ] AC-4: Every **Blocker** severity finding is fixed in this requirement's branch. No Blocker remains open at the end.
- [ ] AC-5: Every **Major** severity finding is fixed in this branch. No Major remains open at the end.
- [ ] AC-6: Every **Minor** severity finding is fixed in this branch. No Minor remains open at the end.
- [ ] AC-7: Every **Polish** severity finding has a corresponding minimal requirement stub at `docs/requirements/{slug}.md` with `status: pending` and 1-2 acceptance criteria.
- [ ] AC-8: Every **Orthogonal** finding (perf, security, doc rot, N+1, missing index, outdated ADR — anything outside UX/QA scope) has a minimal requirement stub in `docs/requirements/{slug}.md` regardless of severity, and is NOT fixed in this branch.
- [ ] AC-9: Each Blocker + Major fix ships with a Pest feature test in `tests/Feature/` AND a Dusk scenario in `tests/Browser/` that reproduces the original failure (before the fix) and passes after.
- [ ] AC-10: Each Minor fix ships with a Pest feature test if it touches a backend path (controller, form request, model, rule, service, job, notification). Frontend-only Minor fixes (diacritic, breadcrumb, required marker, empty-state copy) do not require new tests.
- [ ] AC-11: Full Pest suite (`./vendor/bin/sail test --compact`) is green at branch head. Full Dusk suite (`./vendor/bin/sail dusk`) is green at branch head. A green baseline was also captured at branch start and recorded in the findings report.
- [ ] AC-12: Commits follow the one-commit-per-category cadence — e.g. `style(ui): 💄 sweep Spanish diacritics across module index pages`, `fix(services): 🐛 ...`, `fix(fuec): 🐛 ...`, `docs(audit): 📖 add findings report + spawn follow-up stubs`. No commit-per-finding noise.
- [ ] AC-13: The findings report's summary section tallies the final counts per severity bucket (Blocker / Major / Minor / Polish / Orthogonal) and lists the commit hashes that fixed each bucket.

## Technical Specification

### Data Model

No new database changes are expected for the audit itself. Bug fixes may introduce schema tweaks case-by-case; those follow the project convention — **modify the primary migration in place** (no backfill migrations; staging has no real data) and run `php artisan migrate:fresh --seed --no-interaction`.

### Enums

No new enums for the audit. Bug fixes may add or correct enum values case-by-case; if so, regenerate TypeScript via `php artisan enum:typescript`.

### Routes

No new routes for the audit. Bug fixes may add or correct routes case-by-case.

### Permissions

No new permissions for the audit. Bug fixes may correct permission-gate drift case-by-case.

### Pages

No new pages for the audit. Bug fixes touch existing pages; polish items that are fixed in-scope also touch existing pages. Follow-up stubs may propose new pages but do not create them.

### Findings Report Schema

The report at `docs/audits/2026-04-19-cross-role-audit.md` must follow this structure:

```markdown
# Cross-Role UX / QA Audit — 2026-04-19

## Meta

- **Auditor**: Claude Code
- **Branch**: fix/cross-role-ux-qa-audit
- **Roles covered**: admin, operator, driver, accounting, super-admin
- **Pest baseline (branch start)**: ✅ green / commit hash
- **Dusk baseline (branch start)**: ✅ green / commit hash
- **Pest final (branch head)**: ✅ green / commit hash
- **Dusk final (branch head)**: ✅ green / commit hash

## Summary

| Severity | Count | Fixed in-branch | Spawned follow-up | Commit(s) |
|---|---|---|---|---|
| Blocker | N | N | 0 | `<hashes>` |
| Major | N | N | 0 | `<hashes>` |
| Minor | N | N | 0 | `<hashes>` |
| Polish | N | 0 | N | — |
| Orthogonal | N | 0 | N | — |

## Role sweep: admin

### Pages walked

| Route | Rendered | Breadcrumbs | Required markers | CRUD | Validation | Perm gate | DataTable | Empty state | Audit log | Console clean |
|---|---|---|---|---|---|---|---|---|---|---|
| /dashboard | ✅ | ✅ | n/a | n/a | n/a | ✅ | n/a | n/a | n/a | ✅ |
| ... | ... | ... | ... | ... | ... | ... | ... | ... | ... | ... |

### Findings

#### F-001 (Major): <short title>

- **Route**: `/services/create`
- **Role**: admin
- **Severity**: Major
- **Screenshot**: `docs/audits/screenshots/2026-04-19/F-001-services-create-missing-asterisk.png`
- **Repro**:
  1. Log in as admin
  2. Go to /services/create
  3. Observe that `placa vehículo` has no asterisk marker
- **Expected**: asterisk on required fields per project convention (see `<file>:<line>`)
- **Actual**: no marker
- **Suggested fix**: wrap label with existing `<RequiredMarker />` primitive at `resources/js/pages/services/create.tsx:NN`
- **Fixed in**: commit `<hash>` — `style(services): 💄 add required markers to service form`
- **Follow-up stub**: — (fixed in-scope)

[... one section per finding ...]

## Role sweep: operator
[...]

## Role sweep: driver
[...]

## Role sweep: accounting
[...]

## Role sweep: super-admin
[...]
```

Screenshots live under `docs/audits/screenshots/2026-04-19/` and are committed alongside the report.

### Severity Rubric

| Severity | Definition | Action |
|---|---|---|
| **Blocker** | Breaks a primary user flow. Examples: 500 error, crash, data loss, security bypass (UI button lets non-admin do admin work), broken login, page won't render. | Fix in-branch with Pest + Dusk regression. |
| **Major** | Visible functional bug that does not block the user but degrades the feature. Examples: validation error silently swallowed, button does nothing on click, permission gate drift (UI shows button the backend 403s, or inverse), empty-state renders `undefined`, audit log not written, DataTable filter resets on pagination. | Fix in-branch with Pest + Dusk regression. |
| **Minor** | Visible defect with no functional impact. Examples: missing Spanish diacritic (`Vehiculos` instead of `Vehículos`), missing breadcrumb, missing required-field marker, empty state renders English copy, label inconsistent with siblings, stale Blueprint remnant. | Fix in-branch. Add Pest test only if backend-touching. |
| **Polish** | Purely visual or cosmetic drift. Examples: card spacing inconsistent between sibling pages, button color slightly off, icon sizing mismatch, mockup-vs-code divergence where code is correct but visual is different from `docs/mockups.md`. | Spawn minimal follow-up req stub. Do NOT fix in-branch. |
| **Orthogonal** | Finding is outside the audit's UX/QA scope. Examples: N+1 query, missing database index, dormant race condition, outdated ADR wording, stale docstring, dependency bump needed, security hardening. | Spawn minimal follow-up req stub regardless of severity. Do NOT fix in-branch. |

### Mockup-vs-Code Drift

When Playwright reveals a divergence between `docs/mockups.md` and the actual UI, **log both in the findings report** (severity: Polish or Orthogonal — auditor's call). Do NOT automatically trust either side. If the code is functionally correct and the mockup is stale, the follow-up stub should be "update mockup to match code"; if the code is visually missing something the mockup specified, the follow-up stub should be "implement mockup detail X".

## Migration Strategy

**new** — no database work expected for the audit itself. Per-bug-fix schema changes follow the project's `modify-existing` convention (edit the primary migration in place; run `migrate:fresh --seed` locally) and are scoped to that commit.

## Tasks

### Phase 0 — Branch + baseline

- [ ] Create branch `fix/cross-role-ux-qa-audit` from `develop`
- [ ] Create `docs/audits/` folder
- [ ] Create empty `docs/audits/screenshots/2026-04-19/` folder
- [ ] Run full Pest suite baseline: `./vendor/bin/sail test --compact`. Record pass/fail + commit hash in the report's Meta section. If not green, STOP and report.
- [ ] Run full Dusk suite baseline: `./vendor/bin/sail dusk`. Record pass/fail + commit hash in the report's Meta section. If not green, STOP and report.
- [ ] Commit the empty report skeleton with Meta + baseline — `docs(audit): 📖 open cross-role UX/QA audit + record green baseline`

### Phase 1 — Role sweeps (exhaustive)

For each role, walk every page listed below and run the verification checklist from the Description for each page. Capture one `browser_snapshot` per page state transition, one `browser_take_screenshot` when a visual anomaly is suspected. After each sweep, append the role's section to the findings report and commit the report (docs-only commit) before moving to the next role.

**Verification checklist per page** (apply every item that's relevant):
1. Page renders (no 500, no blank, no English leak, no mojibake)
2. Breadcrumbs present and each level navigates
3. Required-field markers (`*`) on create + edit forms
4. Primary CRUD happy path end-to-end (create, read, update, delete/cancel)
5. **Every** validation error path surfaces correctly in Spanish with diacritics (exhaustive depth requires exercising each `rules()` branch)
6. UI and backend permission gates agree (buttons hidden ⇔ route 403)
7. DataTable filters, search, pagination, sort all work (exercise **each** filter)
8. Empty state renders Spanish copy (not `No data` / `No results`)
9. Audit log records writes for covered flows (verify via `/audit-log`)
10. Browser console clean (`mcp__laravel-boost__browser-logs` after each page)
11. Interact with each button on the page — even rarely-used ones like "Exportar" if present — to ensure they do something or are correctly disabled
12. Resize the browser to tablet width once per role to catch responsive regressions

- [ ] **T1.1 Admin sweep** (`admin@sgte.app`):
  - [ ] /dashboard (Panel)
  - [ ] /services, /services/create, /services/{id}, /services/{id}/edit, /gantt (Planificador), /day-summary, /annual-calendar, /service-incidents (Producción)
  - [ ] /vehicles, /vehicles/create, /vehicles/{id}, /vehicles/{id}/edit (include "Ubicaciones Recientes" card), /drivers (same CRUD arc), /third-parties (same), /contracts (same) (Gestión)
  - [ ] /invoices (full CRUD arc + PDF download + service-assignment flow) (Facturación)
  - [ ] /users (full CRUD arc + role assignment), /audit-log (filter each filter, open detail sheet, trigger Día-ejecutado amber tint) (Administración)
  - [ ] /fuecs (full CRUD arc), /fuecs/create (pre-gen validation errors), /fuecs/{id} (PDF viewer + QR), /fuec-number-ranges (CRUD arc), public /fuec/verify/{uuid} VIGENTE + ANULADO + 404 paths (FUEC)
  - [ ] /gps/map (markers + popups + 30s refresh verify), /vehicle-locations (full CRUD arc + filters) (GPS)
  - [ ] /document-types, /eps, /pension-funds, /severance-funds, /incident-types (all 5 catalog CRUD arcs) (Catálogos)
  - [ ] /settings/profile, /settings/password, /settings/appearance (Configuración)
  - [ ] Commit the admin sweep notes: `docs(audit): 📖 log admin role sweep findings`

- [ ] **T1.2 Operator sweep** (`operator@sgte.app`):
  - [ ] All Producción entries (same as admin)
  - [ ] All Gestión entries
  - [ ] All Catálogos entries
  - [ ] Negative: attempt to hit /users, /audit-log, /invoices, /fuecs, /gps/map, /vehicle-locations — each MUST 403
  - [ ] Verify sidebar does NOT show Administración, Facturación, FUEC, GPS groups
  - [ ] Commit: `docs(audit): 📖 log operator role sweep findings`

- [ ] **T1.3 Driver sweep** (`driver@sgte.app`):
  - [ ] /dashboard redirects to /driver
  - [ ] /driver (Mis Servicios)
  - [ ] /driver/services/{service} (Confirmar Inicio, Confirmar Fin, Registrar Novedad, Registrar Ubicación GPS with + without location grant)
  - [ ] Negative: attempt every admin route — each MUST 403
  - [ ] Commit: `docs(audit): 📖 log driver role sweep findings`

- [ ] **T1.4 Accounting sweep** (`accounting@sgte.app`):
  - [ ] /dashboard
  - [ ] Producción (read-only expectation — verify by attempting a write and checking 403 or disabled)
  - [ ] /invoices (full CRUD arc + PDF download + service-assignment flow)
  - [ ] Negative: attempt /users, /audit-log, /fuecs, /gps/map, /vehicle-locations, Gestión writes — each MUST 403
  - [ ] Commit: `docs(audit): 📖 log accounting role sweep findings`

- [ ] **T1.5 Super-admin sweep** (`SUPER_ADMIN_USER` from `.env`):
  - [ ] Light render-only sweep: visit every page visited in T1.1 and verify it renders without error. Do NOT re-run every CRUD arc.
  - [ ] Verify `Gate::before` bypass by visiting a route the super-admin's assigned roles would not grant (pick something niche from /audit-log filters or similar).
  - [ ] Commit: `docs(audit): 📖 log super-admin render-only sweep findings`

### Phase 2 — Triage

- [ ] **T2.1** Triage every finding in the report into one of: Blocker / Major / Minor / Polish / Orthogonal per the rubric.
- [ ] **T2.2** For each Polish finding, draft a minimal requirement stub and save to `docs/requirements/{slug}.md` with:
  - Frontmatter (`type`, `scope`, `status: pending`, `priority: low`, `created_date: 2026-04-19`)
  - Description (1-2 paragraphs)
  - Acceptance Criteria (1-2 items)
  - NO Technical Specification / Tasks / Verification — left for `/address-req` or a future `/create-req` pass
- [ ] **T2.3** For each Orthogonal finding, same minimal stub — note the category in the Description ("Performance: ...", "Security: ...", "Documentation rot: ...").
- [ ] **T2.4** Commit: `docs(audit): 📖 triage findings + spawn follow-up stubs`

### Phase 3 — In-scope fixes (one commit per category)

Categories likely to emerge (extend the list as findings reveal more):

- [ ] **T3.1 Spanish diacritic sweep** (Minor): fix every `Vehiculos`/`Gestion`/`Categoria`/`Informacion`/etc. across pages, breadcrumbs, sidebar, page titles, validation messages. Commit: `style(ui): 💄 sweep Spanish diacritics across module pages`
- [ ] **T3.2 Breadcrumbs** (Minor): add breadcrumbs to every index page that's missing them (`breadcrumbs={[{ title: '...' }]}` on `<AppLayout>`). Commit: `style(ui): 💄 add breadcrumbs to index pages`
- [ ] **T3.3 Required-field markers** (Minor): audit every create/edit form and add `<RequiredMarker />` (or the project's equivalent) to required fields. Commit: `style(ui): 💄 add required-field markers to create/edit forms`
- [ ] **T3.4 Empty-state copy** (Minor): swap English empty-state copy for Spanish, verify all DataTable + list empty states render. Commit: `style(ui): 💄 localize empty-state copy to Spanish`
- [ ] **T3.5 Permission-gate drift** (Major): for each UI button that the backend 403s (or inverse), align — typically hide the button via `<Can>` and/or fix the route's middleware. Each fix ships with a Pest + Dusk test. Commit: `fix(permissions): 🔒 align UI gates with backend authorization`
- [ ] **T3.6 Validation error surfacing** (Major): any `rules()` branch that silently fails or shows the wrong message, fixed. Each fix ships with a Pest + Dusk test. Commit: `fix(validation): 🐛 surface validation errors consistently in Spanish`
- [ ] **T3.7 Other Blockers + Majors** (emerges from findings): one commit per module where bugs cluster. Each commit ships with Pest + Dusk tests. Expected commits like `fix(services): 🐛 ...`, `fix(fuec): 🐛 ...`, `fix(gps): 🐛 ...`.
- [ ] **T3.8 Stale Blueprint remnants** (Minor/Major depending on impact): remove or rewrite. Commit: `fix(ui): 🐛 remove stale Blueprint remnants surfaced during audit`

### Phase 4 — Report wrap-up + regression baseline

- [ ] **T4.1** Update the findings report summary table with final counts + commit hashes per bucket.
- [ ] **T4.2** Run full Pest suite: `./vendor/bin/sail test --compact`. Must be green. Record commit hash in report Meta.
- [ ] **T4.3** Run full Dusk suite: `./vendor/bin/sail dusk`. Must be green. Record commit hash in report Meta.
- [ ] **T4.4** Run formatters: `vendor/bin/pint --dirty --format agent`, `npm run lint`, `npm run format`, `npm run types`. All must pass.
- [ ] **T4.5** Mark this requirement `status: completed` + set `completed_date: 2026-04-19` (or actual completion date).
- [ ] **T4.6** Commit: `docs(audit): ✅ close audit with final tally + green regression baseline`

## Verification

### 1. Interactive verification — Playwright MCP

Primary tool for the entire audit. Walk every role per the checklist in Task T1.1–T1.5. Prefer `browser_snapshot` over `browser_take_screenshot` except when visual alignment is the question. Read browser JS errors via `mcp__laravel-boost__browser-logs` at the end of each page.

Reference users (all password `password`, super admin reads `.env`):

| Role | Email |
|---|---|
| Admin | `admin@sgte.app` |
| Operator | `operator@sgte.app` |
| Driver | `driver@sgte.app` |
| Accounting | `accounting@sgte.app` |
| Super Admin | `SUPER_ADMIN_USER` |

- [ ] Scenario 1: Admin exhaustive sweep per T1.1 checklist (every page + every button + every validation path + each filter)
- [ ] Scenario 2: Operator exhaustive sweep per T1.2 + negative 403 checks
- [ ] Scenario 3: Driver exhaustive sweep per T1.3 + negative 403 checks + GPS with and without location grant
- [ ] Scenario 4: Accounting exhaustive sweep per T1.4 + negative 403 checks
- [ ] Scenario 5: Super-admin render-only sweep per T1.5 + `Gate::before` bypass spot-check
- [ ] Scenario 6: Tablet-width responsive sweep (one pass per role, abbreviated)

### 2. Backend regression — Pest feature tests

Every Blocker + Major + Minor backend-touching fix MUST ship with a Pest feature test in `tests/Feature/` that reproduces the original failure (asserts-failing before the fix) and passes after. No exceptions.

- [ ] Pest baseline at branch start: `./vendor/bin/sail test --compact` green, hash recorded in report Meta
- [ ] Pest per-fix regression for every Blocker + Major + backend-touching Minor
- [ ] Pest final at branch head: `./vendor/bin/sail test --compact` green, hash recorded in report Meta

### 3. UI regression — Laravel Dusk browser tests

Every Blocker + Major fix MUST ship with a Dusk scenario in `tests/Browser/` that reproduces the original failure (asserts-failing before the fix) and passes after. Minor UI-only fixes (diacritic, breadcrumb, required marker, empty-state copy) do NOT require Dusk.

When a clean database is needed, use `php artisan migrate:fresh --seed --no-interaction` inside the test. Each Dusk test MUST assert:

- No visible error banner or `[role="alert"]` on the page
- Expected Spanish labels with diacritics are present
- Layout is correct (right columns / right fields / right sections)
- Screenshot captured at key steps (`$browser->screenshot('audit-F-NNN-<slug>')`)

- [ ] Dusk baseline at branch start: `./vendor/bin/sail dusk` green, hash recorded in report Meta
- [ ] Dusk per-fix regression for every Blocker + Major
- [ ] Dusk final at branch head: `./vendor/bin/sail dusk` green, hash recorded in report Meta

### 4. API endpoints — curl

The audit may uncover public-API regressions. Any fixed bug that touches a public (non-Inertia) API route MUST ship with a curl reproduction snippet in the finding's report entry.

```bash
# Template — fill per finding when applicable
curl -s -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@sgte.app","password":"password"}' \
  -c cookies.txt

curl -s -X GET http://localhost/<route-under-test> \
  -H "Accept: application/json" \
  -b cookies.txt
```

## Dependencies

- None. The audit sits downstream of every feature already merged to `develop`; it assumes a green baseline.

## Notes

### Commit cadence reminder

**One commit per category**, not per finding. Example:

- ✅ `style(ui): 💄 sweep Spanish diacritics across module pages` (fixes 12 findings in one commit)
- ❌ `fix(vehicles): 🐛 add ú to Vehículos` (too granular)

The report's summary table lists commit hashes per severity bucket so findings remain traceable.

### Handling exhaustion

Exhaustive depth is the chosen policy. If the auditor discovers a page whose exhaustive pass would 10x the audit scope (e.g. Planificador Gantt with thousands of edge cases), downgrade that page to per-page contract coverage (render + primary CRUD + perm gate + 1 validation error) and log the decision in the Notes section of the report with a suggested follow-up stub for deeper coverage.

### Do NOT fix in-scope

- Polish severities (spawn follow-up stub only)
- Orthogonal findings (spawn follow-up stub only, regardless of severity)
- Anything tagged "out of scope" below

### Out of scope

- New feature work
- The six Blueprint CRUD rebuilds (already done — verify they're clean, don't rebuild)
- FUEC + GPS feature-flag infrastructure (already verified in their respective requirements)
- Deployment / CI concerns
- WCAG accessibility deep dives (unless surfaced as a blocker — e.g. keyboard trap, missing focus state on primary action)
- Load / stress testing
- Cross-browser matrix beyond the Chromium Playwright MCP defaults

### Follow-up stub template

Minimal stub body for Polish + Orthogonal findings:

```markdown
---
name: {slug-from-finding}
type: fix
scope: {module-or-app}
status: pending
priority: low
created_date: 2026-04-19
completed_date:
srs_refs: []
migration_strategy: new
---

# {Short title}

## Description

Surfaced by the 2026-04-19 cross-role UX/QA audit, finding F-NNN (severity: Polish | Orthogonal).

{1-2 paragraph description}

See `docs/audits/2026-04-19-cross-role-audit.md#f-nnn` for the original observation + screenshot.

## Acceptance Criteria

- [ ] {1-2 AC items, just enough to anchor the follow-up}
```

### Report entry template

```markdown
#### F-NNN ({Blocker|Major|Minor|Polish|Orthogonal}): {short title}

- **Route**: `{path}`
- **Role**: {admin|operator|driver|accounting|super-admin}
- **Severity**: {Blocker|Major|Minor|Polish|Orthogonal}
- **Screenshot**: `docs/audits/screenshots/2026-04-19/F-NNN-{slug}.png`
- **Repro**:
  1. {step}
  2. {step}
- **Expected**: {what should happen}
- **Actual**: {what happens}
- **Suggested fix**: {file + line + change}
- **Fixed in**: {commit hash + message}  (or `—` if spawned as follow-up)
- **Follow-up stub**: {`docs/requirements/{slug}.md`}  (or `—` if fixed in-scope)
```
