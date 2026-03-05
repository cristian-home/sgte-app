---
name: install-laravel-dusk
type: feat
scope: testing
status: completed
priority: high
created_date: 2026-03-05
completed_date: 2026-03-05
srs_refs: []
migration_strategy: new
---

# Install and Configure Laravel Dusk

## Description

Install and configure Laravel Dusk for browser testing following the official Sail documentation: a `selenium/standalone-chromium` Docker Compose service that the app container connects to via Docker networking. Use `php artisan sail:add selenium` to add the service — this is the standard Sail method that automatically modifies `compose.yaml` and adds `depends_on`.

**Key constraint:** We are inside a devcontainer. After `sail:add` modifies `compose.yaml`, a container restart is needed from the host to start the new Selenium service. This destroys `~/.claude` (Claude Code binary, credentials, memory). Before restarting, run `bash docker/claude-backup.sh` to back up, and after restarting, run `bash docker/claude-restore.sh` to restore. These scripts already exist in the project.

## Acceptance Criteria

- [x] `laravel/dusk` is installed as a dev dependency
- [x] `selenium` service is added to `compose.yaml` via `php artisan sail:add selenium` (uses `selenium/standalone-chromium`)
- [x] `laravel.test` has `depends_on: selenium`
- [x] `DuskTestCase.php` is configured to connect to Selenium (not local ChromeDriver)
- [x] `.env.dusk.local` exists with `APP_URL=http://laravel.test` and PostgreSQL testing database
- [x] A smoke test (`tests/Browser/ExampleTest.php`) passes when run with `php artisan dusk`
- [x] Existing Pest tests still pass (`php artisan test --compact`)
- [x] GitHub Actions CI workflow includes a Dusk job

## Technical Specification

### Data Model

No database changes required.

### Enums

No enum changes required.

### Routes

No route changes required.

### Permissions

No permission changes required.

### Pages

No page changes required.

### Container Architecture

```
┌─── Docker Compose (sail network) ──────────────────────────────┐
│                                                                  │
│  ┌── laravel.test ──────────────────┐   ┌── selenium ────────┐  │
│  │                                   │   │                    │  │
│  │  supervisord                      │   │  standalone-chromium │  │
│  │  ├── [php]     artisan serve :80  │   │  (Selenium + Chrome│  │
│  │  ├── [horizon] artisan horizon    │   │   on port 4444)    │  │
│  │  └── [reverb]  artisan reverb     │   │                    │  │
│  │                                   │   │  Navigates to:     │  │
│  │  php artisan dusk (test runner)   │──▶│  http://laravel.   │  │
│  │  └── connects to selenium:4444    │   │  test (port 80)    │  │
│  │                                   │   │                    │  │
│  └───────────────────────────────────┘   └────────────────────┘  │
│                                                                  │
│  ┌── pgsql ─────────┐                                           │
│  │  testing database │◀── both test runner and web server use    │
│  └──────────────────-┘                                           │
└──────────────────────────────────────────────────────────────────┘
```

The test runner (inside `laravel.test`) sends WebDriver commands to the Selenium container. Selenium's Chrome browser navigates to `http://laravel.test` — the app's Docker hostname on the `sail` network. Both the test process and the web server connect to the same PostgreSQL `testing` database.

## Migration Strategy

Not applicable — no database changes.

## Tasks

### Infrastructure — Docker Compose

- [x] Add Selenium service via `php artisan sail:add selenium` (pass the service name explicitly to avoid adding defaults like MySQL)
  - [x] **Do NOT use `--no-interaction`** — without a service argument it adds default services (mysql, redis, selenium, mailpit) which would add an unwanted MySQL service and overwrite `phpunit.xml`
  - [x] This automatically adds the `selenium/standalone-chromium` service to `compose.yaml`
  - [x] This automatically adds `selenium` to `laravel.test` `depends_on`
  - [x] The Sail stub includes `extra_hosts`, `/dev/shm` volume, and `sail` network
  - [x] Verify `compose.yaml` — ensure no MySQL service was added and `phpunit.xml` was not modified
- [x] After this step, a container restart is needed from the host (see Dependencies section)

### Backend — Dusk Package

- [x] Install Laravel Dusk package
  - [x] Run `composer require --dev laravel/dusk`
  - [x] Run `php artisan dusk:install --no-interaction` to scaffold `tests/Browser/`, `DuskTestCase.php`, pages, console, and screenshots directories

### Backend — DuskTestCase Configuration

- [x] Configure `tests/DuskTestCase.php` for Sail + Selenium
  - [x] Comment out `startChromeDriver()` in the `prepare()` method (Selenium replaces local ChromeDriver)
  - [x] Override the `driver()` method to create a `RemoteWebDriver` connecting to `http://selenium:4444/wd/hub`
  - [x] Add ChromeOptions for headless container operation:
    - `--disable-gpu`
    - `--headless=new`
    - `--no-sandbox`
    - `--window-size=1920,1080`

### Backend — Environment File

- [x] Create `.env.dusk.local` environment file
  - [x] Set `APP_URL=http://laravel.test` (Docker internal hostname — this is what Selenium's Chrome uses to reach the app)
  - [x] Set `DB_CONNECTION=pgsql` (Dusk needs a real shared database — SQLite in-memory is per-process and Selenium's Chrome hitting the web server would see an empty database)
  - [x] Set `DB_HOST=pgsql` (Docker service hostname)
  - [x] Set `DB_PORT=5432`
  - [x] Set `DB_DATABASE=testing` (uses the testing database created by `docker/pgsql/create-testing-database.sql`)
  - [x] Set `DB_USERNAME` and `DB_PASSWORD` matching the main `.env` PostgreSQL credentials
  - [x] Set `CACHE_STORE=array`
  - [x] Set `QUEUE_CONNECTION=sync`
  - [x] Set `SESSION_DRIVER=file`
  - [x] Set `MAIL_MAILER=array`
  - [x] Set `SCOUT_DRIVER=collection`

### CI/CD

- [x] Add a `dusk` job to `.github/workflows/tests.yml`
  - [x] Set `APP_URL=http://127.0.0.1:8000` (no Docker networking in CI)
  - [x] Use `php artisan dusk:chrome-driver --detect` to match the CI Chrome version
  - [x] Start ChromeDriver: `./vendor/laravel/dusk/bin/chromedriver-linux --port=9515 &`
  - [x] Start web server: `php artisan serve --no-reload &`
  - [x] Build assets first: `npm run build`
  - [x] Run `php artisan dusk`
  - [x] Upload `tests/Browser/screenshots` and `tests/Browser/console` as artifacts on failure

### Tests

- [x] Adapt the default `ExampleTest.php` generated by `dusk:install`
  - [x] The default test visits `/` — since the app requires auth, update to visit `/login` and assert the login page loads (e.g., `assertSee('Iniciar sesión')` or similar Spanish-language text visible on the login page)
- [x] Run `php artisan dusk` and confirm the smoke test passes
- [x] Run `php artisan test --compact` and confirm existing Pest tests still pass

### Cleanup

- [x] Add to `.gitignore`:
  - [x] `tests/Browser/screenshots/`
  - [x] `tests/Browser/console/`
- [x] Add `.env.dusk.local` to `.gitignore` (contains credentials; track `.env.dusk.example` instead)
- [x] Create `.env.dusk.example` with placeholder values (same structure as `.env.dusk.local` but no real passwords)

## Dependencies

- **Container restart required.** After `sail:add` modifies `compose.yaml`, the containers must be restarted from the host to start the new Selenium service. Workflow:
  1. Inside the container: `bash docker/claude-backup.sh` (backs up Claude Code to mounted volume)
  2. From the host: `./vendor/bin/sail down && ./vendor/bin/sail up -d`
  3. Inside the new container: `bash docker/claude-restore.sh` (restores Claude Code)
- PostgreSQL testing database must exist (already configured via `docker/pgsql/create-testing-database.sql`)

## Notes

- **Why Selenium service, not local ChromeDriver?** This is the official Sail + Dusk pattern from the Laravel documentation. Use `php artisan sail:add selenium` — the simplest way to add it. The Selenium container provides a clean, isolated Chromium instance accessible via Docker networking. No need to install Chrome inside the app container.
- **Why PostgreSQL for Dusk instead of SQLite?** Dusk's browser (inside the Selenium container) hits the web server (inside `laravel.test`). The test runner also runs inside `laravel.test`. SQLite in-memory databases are per-process, so the web server would see an empty database. PostgreSQL is shared via Docker networking.
- **How Dusk env swap works:** When `php artisan dusk` runs, it temporarily replaces `.env` with `.env.dusk.local`. The supervisor-managed `php artisan serve` re-reads `.env` on each HTTP request (no config cache in dev), so it automatically uses the Dusk database during tests. After tests complete, Dusk restores the original `.env`.
- **Container restart concern:** Restarting containers destroys `~/.claude` in HOME. Use `docker/claude-backup.sh` before and `docker/claude-restore.sh` after. These scripts back up the binary (~454 MB) and config (~32 MB) to the mounted project volume (`.claude-backup/`, already in `.gitignore`).
- **supervisorctl:** After running Dusk, you may want to run `supervisorctl restart php` to ensure the web server picks up the restored `.env` cleanly, though it should happen automatically since there's no config cache.
