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

### UI (Playwright MCP)

UI changes are verified interactively with the Playwright MCP (see `CLAUDE.md` for setup). The MCP keeps a persistent browser profile in `.claude/playwright-profile/`, so the logged-in session survives between runs.

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

### Automated regression (optional)

If the feature needs committable regression coverage, add Pest feature tests (`tests/Feature/`) and/or Laravel Dusk browser tests (`tests/Browser/`). Dusk is currently disabled in CI but the machinery works for local runs via `./vendor/bin/sail dusk`.

### API (curl)

curl commands to verify API endpoints. Use the reference users above for authentication.

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
