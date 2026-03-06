# Address Requirement: $ARGUMENTS

You are an autonomous implementation agent. Your job is to fully implement the requirement described in `docs/requirements/$ARGUMENTS.md` — from branch creation to final commit — without asking the user any questions.

## CRITICAL RULES

1. **Never ask questions.** If something is ambiguous, make the best decision and document it in a commit message.
2. **Never skip tests.** Every change must have corresponding tests.
3. **Never commit failing code.** Run tests before every commit.
4. **Always run `vendor/bin/pint --dirty --format agent`** before committing PHP files.
5. **Always run `npm run lint` and `npm run format`** before committing JS/TS files.
6. **Use conventional commits** matching the project format: `{type}({scope}): {emoji} {description}`.
7. **If the requirement specifies modifying existing migrations**, modify them and run `php artisan migrate:fresh --seed --no-interaction` instead of creating new migrations.
8. **If tests fail after changes**, fix the code (not the tests) unless the tests themselves are wrong.
9. **Do NOT include `Co-Authored-By` trailers** or any Claude/AI attribution in commit messages. Commit messages must only contain the conventional commit subject and optional body.

## PHASE 0: Read & Understand

1. Read the requirement document: `docs/requirements/$ARGUMENTS.md`
2. Read `CLAUDE.md` for project conventions.
3. Read any referenced docs (SRS, data model, navigation) if mentioned in the requirement.
4. Read related existing code (sibling controllers, models, tests) to match conventions.
5. Identify the requirement `type` from the frontmatter (`feat` or `fix`) and the `scope`.

## PHASE 1: Branch Setup

1. Ensure you are on the `develop` branch and it is up to date:
   ```
   git checkout develop
   git pull origin develop  (if remote exists, otherwise skip)
   ```
2. Create and switch to a new branch:
   - For `feat` type: `git checkout -b feat/$ARGUMENTS`
   - For `fix` type: `git checkout -b fix/$ARGUMENTS`

## PHASE 2: Plan Implementation Order

Based on the requirement's Tasks section, plan the implementation in this order:

1. **PHP Enums** — if the requirement adds new enum values
2. **Database Migrations** — new tables or column modifications
3. **Models** — Eloquent models with relationships, casts, fillable
4. **Factories & Seeders** — for test data
5. **Form Requests** — validation rules
6. **Controllers** — following existing conventions (check sibling controllers)
7. **Routes** — add to appropriate route file
8. **Permissions** — if new permissions are needed (enum + seeder + migration)
9. **Frontend Pages** — React + Inertia pages in `resources/js/pages/`
10. **Frontend Components** — shared components if needed
11. **Tests** — Feature tests using Pest (HTTP endpoint tests)
12. **TypeScript Enums** — run `php artisan enum:typescript` if PHP enums changed

## PHASE 3: Iterative Implementation

For each task in the requirement document:

1. **Implement** the task following project conventions.
2. **Run relevant tests** to verify the change works:
   ```
   php artisan test --compact --filter=RelevantTest
   ```
3. **Format code** before committing:
   - PHP: `vendor/bin/pint --dirty --format agent`
   - JS/TS: `npm run lint && npm run format`
4. **Stage and commit** with a conventional commit message:
   ```
   git add <specific-files>
   git commit -m "{type}({scope}): {emoji} {description}"
   ```
5. **Update the requirement doc** — check off the completed task:
   - Change `- [ ] Task description` to `- [x] Task description`
   - Commit the doc update with: `docs({scope}): ✅ mark task as completed`

### Commit Type Reference
| Type | When |
|------|------|
| `feat` | New feature or functionality |
| `fix` | Bug fix |
| `refactor` | Code restructuring without behavior change |
| `test` | Adding or updating tests |
| `chore` | Tooling, deps, config |
| `docs` | Documentation only |
| `style` | Formatting, no logic change |

### Emoji Reference (match existing commits)
| Emoji | Meaning |
|-------|---------|
| ✨ | New feature |
| 🐛 | Bug fix |
| 🔨 | Refactor |
| 🧪 | Tests |
| 🏗️ | Architecture/scaffold |
| 🌱 | Seeders/data |
| 👔 | Business logic |
| 💥 | Breaking change |
| ⬆️ | Dependencies |
| 🔍️ | Search |
| 🚧 | Work in progress |

## PHASE 4: Update Requirement Document

1. Update the frontmatter `status` from `pending` to `completed`.
2. Add `completed_date` to the frontmatter.
3. Ensure all task checkboxes are checked.
4. Commit: `docs($ARGUMENTS): ✅ mark requirement as completests/Browser/screenshotsted`

## PHASE 5: End-to-End Verification

After all implementation and unit/feature tests pass, verify the functionality works end-to-end:

### UI Features (Laravel Dusk)

If the requirement involves UI pages or components, write and run Laravel Dusk browser tests to verify both functional behavior and visual correctness:

1. Ensure the database is seeded: `php artisan migrate:fresh --seed --no-interaction`
2. Write Dusk tests in `tests/Browser/` that exercise the implemented UI flows.
3. Use the super admin credentials from environment variables:
   - Email: `env('SUPER_ADMIN_USER')`
   - Password: `env('SUPER_ADMIN_PASSWORD')`
4. Each Dusk test MUST verify **visual consistency** in addition to functional behavior:
   - **No errors on screen**: Assert that no exception messages, stack traces, or error banners are visible on the page.
   - **Key UI elements are visible and correct**: Assert that headings, labels, buttons, tables, and form fields are present and display the expected text.
   - **Layout makes sense**: Assert that data is rendered in the correct sections (e.g., a table has the expected columns, a form has the expected fields).
   - **Take screenshots** (`$browser->screenshot('descriptive-name')`) at key steps so visual output can be reviewed. Screenshots are saved to `tests/Browser/screenshots/`.
5. Run Dusk tests: `php artisan dusk --filter=RelevantTest`
6. If Dusk tests fail, fix the code and re-run.

### API Endpoints (curl)

If the requirement involves API routes, verify them with `curl`:

1. Ensure the database is seeded: `php artisan migrate:fresh --seed --no-interaction`
2. Authenticate as super admin using the credentials from environment variables:
   - Email: `env('SUPER_ADMIN_USER')`
   - Password: `env('SUPER_ADMIN_PASSWORD')`
3. Use `curl` to hit the API endpoints and verify responses (status codes, JSON structure, data).
4. If responses are unexpected, fix the code and re-test.

### Static Analysis & Build

Run the full verification pipeline:

```bash
# 1. Run all tests
php artisan test --compact

# 2. PHP formatting
vendor/bin/pint --dirty --format agent

# 3. TypeScript type check
npm run types

# 4. ESLint
npm run lint

# 5. Prettier
npm run format:check

# 6. Build (ensure no build errors)
npm run build
```

If any step fails, fix the issue, commit the fix, and re-run verification.

## PHASE 6: Summary

After all phases complete, output a summary:

```
## Implementation Complete: $ARGUMENTS

**Branch**: feat/$ARGUMENTS (or fix/$ARGUMENTS)
**Commits**: [list each commit hash + message]
**Tests**: [number passing / number total]
**Files Changed**: [count]

### What was implemented:
- [bullet list of completed tasks]

### Notes:
- [any decisions made, deviations from spec, or things to review]
```
