---
name: requirement-slug
type: feat # feat | fix
scope: module-name # e.g., vehicles, drivers, contracts
status: pending # pending | in-progress | completed | blocked
priority: medium # low | medium | high | critical
created_date: YYYY-MM-DD
completed_date: # filled when completed
srs_refs: [] # e.g., ["RF-001", "RF-002"]
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

### UI (Laravel Dusk)

Dusk browser tests in `tests/Browser/`. Use super admin credentials from `env('SUPER_ADMIN_USER')` / `env('SUPER_ADMIN_PASSWORD')`. Run `php artisan migrate:fresh --seed --no-interaction` before tests that need a clean database.

- [ ] Scenario 1: Navigate to page X and verify element Y is visible
- [ ] Scenario 2: Fill form and verify submission succeeds

### API (curl)

curl commands to verify API endpoints. Use the same super admin credentials for authentication.

```bash
# Example: verify endpoint returns expected data
curl -s -X GET http://localhost/api/example \
  -H "Accept: application/json" \
  -b cookies.txt
```

## Dependencies

List any prerequisite requirements, packages, or features that must exist before this can be implemented.

- None

## Notes

Additional context, design decisions, or constraints.
