# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**SGTE** (Sistema de Gestión de Transporte Especial) — a fleet management system for special transport in Colombia. Built with Laravel 12 + Inertia v2 + React 19 + Tailwind CSS v4. The UI labels and domain terminology are in Spanish.

Domain modules: vehicles, drivers, third-parties (clients/providers), contracts, services (Gantt-based scheduling), day summaries, incidents, invoices, FUEC document generation, and reports.

## Host vs. Sail Devcontainer — IMPORTANT

This project ships a **Sail-based devcontainer** (`compose.yaml` + `docker/8.5/`) that runs PostgreSQL, Redis, Typesense, MinIO, Mailpit, Reverb, and the Laravel app itself. You can open the project two ways:

1. **VS Code devcontainer mode** → shell runs *inside* the `laravel.test` container. PHP, Node, artisan, etc. run natively.
2. **Host shell** → open a terminal on the host and use `./vendor/bin/sail <command>` to execute anything that needs the containerized services.

**Detect which side you're on** with `test -f /.dockerenv && echo INSIDE || echo HOST`.

### Rule: tests and e2e must run inside the container

When you are on the **host machine** (i.e., `test -f /.dockerenv` returns nothing), **always** run unit, feature, and browser (Dusk) tests through `./vendor/bin/sail` so they hit the same PHP runtime, extensions, and infra services as CI and production. Running `php artisan test` directly on the host happens to work for SQLite-in-memory feature tests but:

- Uses whatever PHP version the host has (may not be 8.5.3).
- Skips integration with the real Postgres/Redis/Typesense/MinIO containers.
- Diverges from what Dusk, Horizon, queue workers, or any test relying on real services need.
- Masks bugs that only show up in the containerized stack.

```bash
# ✅ Correct (when on host)
./vendor/bin/sail up -d                                    # start services if down
./vendor/bin/sail test --compact                           # full suite
./vendor/bin/sail test --compact --filter=testName         # single test
./vendor/bin/sail artisan test --compact tests/Feature/Foo # directory
./vendor/bin/sail dusk                                     # browser tests

# ❌ Wrong on host (use sail)
php artisan test
vendor/bin/pest
```

If `./vendor/bin/sail` is unavailable (inside the container) or the user explicitly asks to bypass Sail, call `artisan test` / `pest` directly.

## Development Commands

```bash
# NOTE: the commands below assume you are inside the Sail container (VS Code
# devcontainer mode). If you are on the host, prefix with `./vendor/bin/sail`.

# Full dev environment (server + queue + logs + vite)
composer run dev

# Individual services
php artisan serve          # Backend only
npm run dev                # Vite dev server only

# Build
npm run build              # Production build
npm run build:ssr          # Production + SSR build

# Testing
php artisan test --compact                          # Run all tests
php artisan test --compact tests/Feature/Auth       # Run a directory
php artisan test --compact --filter=testName        # Run specific test

# Linting & formatting
vendor/bin/pint --dirty --format agent   # Format modified PHP files (REQUIRED before finalizing PHP changes)
npm run lint                             # ESLint fix
npm run format                           # Prettier format resources/
npm run format:check                     # Prettier check
npm run types                            # TypeScript type check (tsc --noEmit)

# Full test + lint pipeline
composer run test    # config:clear → pint --test → artisan test

# Code generation
php artisan enum:typescript              # Regenerate TS enums from PHP enums → resources/js/enums/
```

## Architecture

### Backend (Laravel 12)

- **Routing**: `bootstrap/app.php` registers routes (web, api, console, channels) and middleware. No `Kernel.php`.
- **Providers**: `bootstrap/providers.php` → AppServiceProvider, FortifyServiceProvider, HorizonServiceProvider.
- **Auth**: Laravel Fortify (headless). Super Admin role bypasses all gates via `Gate::before` in AppServiceProvider.
- **Permissions**: Spatie Permission package. Roles defined in `app/Enums/Role.php` (5 roles). Permissions in `app/Enums/Permission.php` (47 permissions, CRUD pattern per module).
- **Validation**: Form Request classes in `app/Http/Requests/` — never inline validation in controllers.
- **Shared Inertia data**: `HandleInertiaRequests` middleware shares `auth.user`, `auth.permissions`, `auth.roles`, `sidebarOpen`, `name`, `url` to all pages.

### Frontend (React 19 + Inertia v2)

- **Pages**: `resources/js/pages/` — resolved by Inertia from controller `render('page-name')` calls.
- **Layouts**: `resources/js/layouts/` — `app-layout.tsx` (main), `auth-layout.tsx` (auth), `settings/layout.tsx`.
- **Components**: `resources/js/components/` — shared app components. `components/ui/` for base UI primitives. `components/kibo-ui/` for custom complex components.
- **Path alias**: `@/` → `resources/js/` (configured in tsconfig.json and vite).
- **Prettier**: 4-space indentation, single quotes, semicolons, `tailwindcss` plugin for class ordering.

### Permission System (Full Stack)

PHP enums (`app/Enums/Permission.php`, `app/Enums/Role.php`) are the source of truth. Run `php artisan enum:typescript` to generate TypeScript mirrors in `resources/js/enums/`. These files are auto-generated — do not edit manually.

Frontend usage:
- `<Can permission={Permission.VIEW_VEHICLES}>` component for conditional rendering
- `usePermissions()` hook → `can()`, `hasRole()`, `isSuperAdmin`

### Wayfinder (Route Generation)

Wayfinder auto-generates TypeScript route functions in `resources/js/actions/` (controller actions) and `resources/js/routes/` (named routes). Do not edit these directories manually — they regenerate on build.

### Infrastructure (Docker via Sail)

Services in `compose.yaml`: PostgreSQL 18, Redis, Typesense (search), MinIO (S3 storage), Mailpit (email testing), Reverb (WebSockets).

### Production Docker & Deployment

- Production Dockerfile: `docker/production/Dockerfile` (4-stage: composer → base frankenphp → build → production).
- Staging compose: `compose.staging.yaml` — infrastructure services + app via `profiles: [local]`.
- Local testing: `docker compose -f compose.staging.yaml --profile local --env-file .env.stg up -d --build`
- Deployment target: Dokploy (VPS). See `docs/deployment.md` for full guide.

### Repository & Dokploy Deployment

- **GitHub**: `cristian-home/sgte-app`. Default working branch is `develop`; `main` is reserved for a future "stable initial version" that will eventually auto-deploy to production.
- **Dokploy panel**: self-hosted on the VPS. The live app is the `SGTE Laravel App` service inside the `SGTE` project. Two services coexist: `SGTE Laravel App` (type: application, built from `docker/production/Dockerfile`) and `SGTE Services` (type: compose, runs Postgres/Redis/MinIO/Typesense/etc.).
- **Naming caveat**: the Dokploy environment is labelled `production` but the running container has `APP_ENV=staging`. It's de facto a staging deploy used for client demos — do not treat it as real production.
- **Deploys are manual.** Autodeploy is off on the Dokploy GitHub App integration, and `deploy-staging.yml` uses `workflow_dispatch`. Trigger a deploy either from GitHub Actions (Run workflow button) or from Dokploy's "Deploy" button on the SGTE Laravel App page.
- **Dokploy credentials** live in `.env` (gitignored): `DOKPLOY_URL`, `DOKPLOY_TOKEN`, `DOKPLOY_PROJECT_ID`, `DOKPLOY_ENVIRONMENT_ID`, `DOKPLOY_APP_ID`. Use `source .env` in bash to call the API. Do not commit these or echo them back.
- **Known security debt**: Dokploy panel is exposed on `http://…:3000` (no TLS), and the API's `application.one` endpoint returns all service secrets in plain text when queried — prefer narrower endpoints. Pending: HTTPS for the panel, dedicated domain + Let's Encrypt for the app, rotating any secrets that have been observed in responses.

### CI/CD

- `.github/workflows/tests.yml` — Pest tests (PHP 8.5, SQLite in-memory), runs on every push.
- `.github/workflows/lint.yml` — Pint + Prettier + ESLint, runs on every push.
- `.github/workflows/deploy-staging.yml` — Manual `workflow_dispatch` trigger. Calls Dokploy's `application.redeploy` API with the secrets `DOKPLOY_URL`, `DOKPLOY_TOKEN`, `DOKPLOY_APP_ID`. No auto-deploy on push.

### Testing

- Pest 4 with RefreshDatabase on Feature tests (`tests/Pest.php`).
- SQLite in-memory for tests (`phpunit.xml`).
- Feature tests in `tests/Feature/`, unit tests in `tests/Unit/`.
- Use factories for model creation in tests.

### Key Packages

- **spatie/laravel-permission**: Role & permission management
- **spatie/laravel-activitylog**: Activity logging on models
- **spatie/laravel-medialibrary**: File/media attachments
- **spatie/laravel-query-builder**: API query filtering
- **laravel/scout + Typesense**: Full-text search
- **laravel/horizon**: Queue monitoring dashboard
- **laravel/reverb + laravel-echo**: Real-time WebSocket broadcasting

## Documentation

Project documentation lives in `/docs/`: SRS (`SRS.md`), data model (`data-model.md`), navigation structure (`navigation.md`), UI mockups (`mockups.md`), ADRs in `/docs/adr/`, and phase plans in `/docs/phases/`.

## Browser Automation for UI Verification

The project has **Playwright MCP** configured in local scope (personal, not committed to the repo — lives in `~/.claude.json` under the project path). It's the preferred way to verify UI changes, debug front-end behavior, or walk through features interactively.

**Setup**: already installed via `claude mcp add playwright -s local -- npx -y @playwright/mcp@latest --user-data-dir=<repo>/.claude/playwright-profile --browser=chromium --output-dir=<repo>/.claude/playwright-output`. Both dirs live under `/.claude/*` which is gitignored, so browser sessions (cookies, localStorage) and the MCP's snapshot/log output stay out of the repo. A safety-net `/.playwright-mcp` entry in `.gitignore` catches the legacy default output path too. If the MCP isn't available in a fresh session, re-run the `claude mcp add` command.

**When to use which tool**:

| Task | Tool |
|---|---|
| Explore a UI flow, click through a feature, take a screenshot on demand | Playwright MCP (`mcp__playwright__*`) |
| Read browser console errors for a running local app | `mcp__laravel-boost__browser-logs` |
| Committable regression tests | Laravel Dusk (`./vendor/bin/sail dusk`) — currently disabled in CI but the machinery works |

**Testing with multiple roles**: the reference users (created by the init-data migration) all share the password `password`, except the super admin which reads from `.env`. To switch roles during a Playwright MCP session:

1. Ensure you're on the login page: `browser_navigate http://localhost/login`
2. Fill email + password, submit.
3. The session cookie persists in the user-data-dir until you logout or delete the profile directory.

Reference users (all password `password`):

| Role | Email |
|---|---|
| Admin | `admin@sgte.app` |
| Operator | `operator@sgte.app` |
| Driver | `driver@sgte.app` |
| Accounting | `accounting@sgte.app` |
| Super Admin | whatever `SUPER_ADMIN_USER` is set to in `.env` |

**Efficiency tips for token usage**:
- Default to `browser_snapshot` (accessibility tree) over `browser_take_screenshot`. The a11y snapshot is ~200-600 tokens vs. 1500-3000 for image analysis.
- Use `browser_take_screenshot` only when the question is visual (alignment, colors, spacing).
- Keep the MCP session warm: don't restart it between checks — the persistent profile means the logged-in session is still there.
- Use `mcp__laravel-boost__browser-logs` instead of scraping console output from Playwright when you only need JS errors.

## Git & Commit Conventions

- **Never add `Co-Authored-By: Claude …` trailers, "Generated with Claude Code" footers, or any other AI attribution to commit messages or PR bodies.** Write commits as if authored entirely by the user. This overrides the default Claude Code commit template.
- **Format**: Conventional Commits + Gitmoji. The emoji goes **after** the colon, at the start of the description:
  ```
  type(scope): <emoji> short imperative description
  ```
  Example: `feat(infrastructure): 🏗️ add staging compose for supporting services`.
- Keep the subject under ~72 chars; put details in the body (one blank line, then bullet points or prose).
- **Emoji selection is lax, not strict.** Gitmoji is the baseline vocabulary, but feel free to pick a more contextually evocative emoji when it fits the change better — e.g., 🐳 for Docker work, 🐘 for Postgres, 🦎 for Laravel-specific refactors, 📦 for packaging, 🚀 for releases/deploys. The goal is that someone skimming `git log` can tell what each commit is about at a glance; stick to gitmoji when there isn't an obviously better fit.
- **Common type → gitmoji mapping used in this repo** (check `git log` when in doubt — consistency with existing history matters more than strict adherence):

  | Type | Gitmoji | When to use |
  |---|---|---|
  | `feat` | ✨ `:sparkles:` (generic) · 🏗️ `:building_construction:` (infra) · 🎉 `:tada:` (initial) | New feature or capability |
  | `fix` | 🐛 `:bug:` · 🚑 `:ambulance:` (hotfix) · 🔒 `:lock:` (security) | Bug fix |
  | `refactor` | ♻️ `:recycle:` · 🔨 `:hammer:` | Code restructuring, no behavior change |
  | `docs` | 📖 `:book:` · 📝 `:memo:` | Documentation |
  | `test` | 🧪 `:test_tube:` · ✅ `:white_check_mark:` | Tests added/updated |
  | `ci` | 👷 `:construction_worker:` · 💚 `:green_heart:` (fix CI) | CI/CD changes |
  | `chore` | 🔧 `:wrench:` · ⬆️ `:arrow_up:` (deps) · 🔥 `:fire:` (remove code) | Tooling, config, dependencies |
  | `style` | 🎨 `:art:` · 💄 `:lipstick:` (UI) | Formatting, whitespace, UI polish |
  | `perf` | ⚡ `:zap:` | Performance improvements |

- Before writing a commit message, skim `git log --oneline -10` to match the flavor of recent history.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5.3
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/octane (OCTANE) - v2
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/scout (SCOUT) - v10
- laravel/wayfinder (WAYFINDER) - v0
- laravel/boost (BOOST) - v2
- laravel/dusk (DUSK) - v8
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- laravel-echo (ECHO) - v2
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `wayfinder-development` — Activates whenever referencing backend routes in frontend components. Use when importing from @/actions or @/routes, calling Laravel routes from TypeScript, or working with Wayfinder route functions.
- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `inertia-react-development` — Develops Inertia.js v2 React client-side applications. Activates when creating React pages, forms, or navigation; using &lt;Link&gt;, &lt;Form&gt;, useForm, or router; working with deferred props, prefetching, or polling; or when user mentions React with Inertia, React pages, React forms, or React navigation.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.
- `developing-with-fortify` — Laravel Fortify headless authentication backend development. Activate when implementing authentication features including login, registration, password reset, email verification, two-factor authentication (2FA/TOTP), profile updates, headless auth, authentication scaffolding, or auth guards in Laravel applications.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== wayfinder/core rules ===

# Laravel Wayfinder

Wayfinder generates TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

- IMPORTANT: Activate `wayfinder-development` skill whenever referencing backend routes in frontend components.
- Invokable Controllers: `import StorePost from '@/actions/.../StorePostController'; StorePost()`.
- Parameter Binding: Detects route keys (`{post:slug}`) — `show({ slug: "my-post" })`.
- Query Merging: `show(1, { mergeQuery: { page: 2, sort: null } })` merges with current URL, `null` removes params.
- Inertia: Use `.form()` with `<Form>` component or `form.submit(store())` with useForm.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

=== laravel/fortify rules ===

# Laravel Fortify

- Fortify is a headless authentication backend that provides authentication routes and controllers for Laravel applications.
- IMPORTANT: Always use the `search-docs` tool for detailed Laravel Fortify patterns and documentation.
- IMPORTANT: Activate `developing-with-fortify` skill when working with Fortify authentication features.

</laravel-boost-guidelines>
