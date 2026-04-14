---
name: requirement-slug
type: feat # feat | fix
scope: module-name # e.g., vehicles, drivers, contracts
status: pending # pending | in-progress | completed | blocked
priority: medium # low | medium | high | critical
created_date: YYYY-MM-DD
completed_date: # filled when completed
srs_refs: [] # e.g., ["REQ-001", "REQ-002"]
migration_strategy: new # new | modify-existing
---

# Requirement Title

## Description

Brief description of the requirement. What problem does it solve? Why is it needed?

## Acceptance Criteria

- [ ] Criterion 1: specific, testable outcome
- [ ] Criterion 2: specific, testable outcome
- [ ] Criterion 3: specific, testable outcome

## Technical Specification

### Data Model

Describe any new tables, columns, or relationships needed.

```
table_name
├── id (bigint, PK)
├── column_name (type, constraints)
├── foreign_key_id (bigint, FK → other_table.id)
├── created_at (timestamp)
└── updated_at (timestamp)
```

### Enums

List any new PHP enums or values to add to existing enums.

### Routes

| Method | URI | Controller Action | Middleware | Name |
|--------|-----|-------------------|------------|------|
| GET | /example | ExampleController@index | auth | example.index |

### Permissions

List any new permissions needed (will be added to `app/Enums/Permission.php`).

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Index | `resources/js/pages/example/index.tsx` | List view |

## Migration Strategy

- **new**: Create new migration files.
- **modify-existing**: Modify existing migration files and run `php artisan migrate:fresh --seed`.

Specify which approach and why.

## Tasks

### Backend

- [ ] Task 1: description
  - [ ] Sub-task 1a
  - [ ] Sub-task 1b
- [ ] Task 2: description

### Frontend

- [ ] Task 3: description
- [ ] Task 4: description

### Tests

- [ ] Task 5: description

## Verification

Verification has three distinct layers — use all of them that apply. Playwright MCP is for *interactive* development-time checks and does **not** replace committable regression coverage.

### 1. Interactive verification — Playwright MCP

UI changes are verified interactively with the Playwright MCP (see `CLAUDE.md` for setup). The MCP keeps a persistent browser profile in `.claude/playwright-profile/`, so the logged-in session survives between runs. This is ephemeral — nothing here is committed to the repo.

Reference users (all password `password`, except super admin which reads `SUPER_ADMIN_USER` / `SUPER_ADMIN_PASSWORD` from `.env`):

| Role | Email |
|---|---|
| Admin | `admin@sgte.app` |
| Operator | `operator@sgte.app` |
| Driver | `driver@sgte.app` |
| Accounting | `accounting@sgte.app` |

Preferred flow:

1. `mcp__playwright__browser_navigate` to `http://localhost/login`
2. Fill in email + password, submit
3. Navigate to the page under test
4. Prefer `mcp__playwright__browser_snapshot` (accessibility tree, ~200–600 tokens) for assertions
5. Use `mcp__playwright__browser_take_screenshot` only when the check is visual (alignment, spacing, color)
6. Read JS errors with `mcp__laravel-boost__browser-logs` when relevant

- [ ] Scenario 1: Navigate to page X and verify element Y is visible
- [ ] Scenario 2: Fill form and verify submission succeeds

### 2. Backend regression — Pest feature tests (required for any backend change)

Any requirement that touches controllers, form requests, models, rules, services, jobs, or notifications MUST ship with Pest feature tests in `tests/Feature/` asserting the HTTP / Inertia contract, happy path, and representative failure paths (authorization 403s, validation errors, edge cases).

Run locally via `./vendor/bin/sail test --compact`. CI runs the full suite on every push.

- [ ] Feature test N: describe the scenario

### 3. UI regression — Laravel Dusk browser tests (required for any user-facing UI change)

Any requirement that adds or meaningfully changes a user-facing page MUST ship with Laravel Dusk browser tests in `tests/Browser/` asserting:

- The page renders without error banners, exception traces, or visible error UI
- Key elements (headings, labels, buttons, table columns, form fields, badges) appear with the expected Spanish copy
- Layout is correct (right columns in tables, right fields in forms, data in the right sections)
- Screenshots captured at key interaction steps for visual review

Reference users and credentials are the same as the Playwright MCP table above. When a clean database is needed, run `php artisan migrate:fresh --seed --no-interaction` inside the Dusk test.

Dusk is currently disabled in CI but runs locally via `./vendor/bin/sail dusk`. This is the project's committable regression mechanism for the UI; Playwright MCP does not replace it.

- [ ] Dusk test N: describe the scenario

### 4. API endpoints — curl (when applicable)

For public API endpoints, include curl commands to verify responses. Use the reference users above for authentication.

```bash
# Example: login and hit a protected endpoint
curl -s -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@sgte.app","password":"password"}' \
  -c cookies.txt

curl -s -X GET http://localhost/api/example \
  -H "Accept: application/json" \
  -b cookies.txt
```

## Dependencies

List any prerequisite requirements, packages, or features that must exist before this can be implemented.

- None

## Notes

Additional context, design decisions, or constraints.
