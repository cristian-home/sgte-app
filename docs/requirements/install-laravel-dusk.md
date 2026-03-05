---
name: install-laravel-dusk
type: feat
scope: testing
status: pending
priority: high
created_date: 2026-03-05
completed_date:
srs_refs: []
migration_strategy: new
---

# Install and Configure Laravel Dusk

## Description

Install and configure Laravel Dusk for browser testing following the official Sail documentation: a `selenium/standalone-chromium` Docker Compose service that the app container connects to via Docker networking. Use `php artisan sail:add selenium` to add the service — this is the standard Sail method that automatically modifies `compose.yaml` and adds `depends_on`.

**Key constraint:** We are inside a devcontainer. After `sail:add` modifies `compose.yaml`, a container restart is needed from the host to start the new Selenium service. This destroys `~/.claude` (Claude Code binary, credentials, memory). Before restarting, run `bash docker/claude-backup.sh` to back up, and after restarting, run `bash docker/claude-restore.sh` to restore. These scripts already exist in the project.

## Acceptance Criteria

- [ ] `laravel/dusk` is installed as a dev dependency
- [ ] `selenium` service is added to `compose.yaml` via `php artisan sail:add selenium` (uses `selenium/standalone-chromium`)
- [ ] `laravel.test` has `depends_on: selenium`
- [ ] `DuskTestCase.php` is configured to connect to Selenium (not local ChromeDriver)
- [ ] `.env.dusk.local` exists with `APP_URL=http://laravel.test` and PostgreSQL testing database
- [ ] A smoke test (`tests/Browser/ExampleTest.php`) passes when run with `php artisan dusk`
- [ ] Existing Pest tests still pass (`php artisan test --compact`)
- [ ] GitHub Actions CI workflow includes a Dusk job

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

- [ ] Add Selenium service via `php artisan sail:add selenium` (pass the service name explicitly to avoid adding defaults like MySQL)
  - [ ] **Do NOT use `--no-interaction`** — without a service argument it adds default services (mysql, redis, selenium, mailpit) which would add an unwanted MySQL service and overwrite `phpunit.xml`
  - [ ] This automatically adds the `selenium/standalone-chromium` service to `compose.yaml`
  - [ ] This automatically adds `selenium` to `laravel.test` `depends_on`
  - [ ] The Sail stub includes `extra_hosts`, `/dev/shm` volume, and `sail` network
  - [ ] Verify `compose.yaml` — ensure no MySQL service was added and `phpunit.xml` was not modified
- [ ] After this step, a container restart is needed from the host (see Dependencies section)

### Backend — Dusk Package

- [ ] Install Laravel Dusk package
  - [ ] Run `composer require --dev laravel/dusk`
  - [ ] Run `php artisan dusk:install --no-interaction` to scaffold `tests/Browser/`, `DuskTestCase.php`, pages, console, and screenshots directories

### Backend — DuskTestCase Configuration

- [ ] Configure `tests/DuskTestCase.php` for Sail + Selenium
  - [ ] Comment out `startChromeDriver()` in the `prepare()` method (Selenium replaces local ChromeDriver)
  - [ ] Override the `driver()` method to create a `RemoteWebDriver` connecting to `http://selenium:4444/wd/hub`
  - [ ] Add ChromeOptions for headless container operation:
    - `--disable-gpu`
    - `--headless=new`
    - `--no-sandbox`
    - `--window-size=1920,1080`

### Backend — Environment File

- [ ] Create `.env.dusk.local` environment file
  - [ ] Set `APP_URL=http://laravel.test` (Docker internal hostname — this is what Selenium's Chrome uses to reach the app)
  - [ ] Set `DB_CONNECTION=pgsql` (Dusk needs a real shared database — SQLite in-memory is per-process and Selenium's Chrome hitting the web server would see an empty database)
  - [ ] Set `DB_HOST=pgsql` (Docker service hostname)
  - [ ] Set `DB_PORT=5432`
  - [ ] Set `DB_DATABASE=testing` (uses the testing database created by `docker/pgsql/create-testing-database.sql`)
  - [ ] Set `DB_USERNAME` and `DB_PASSWORD` matching the main `.env` PostgreSQL credentials
  - [ ] Set `CACHE_STORE=array`
  - [ ] Set `QUEUE_CONNECTION=sync`
  - [ ] Set `SESSION_DRIVER=file`
  - [ ] Set `MAIL_MAILER=array`
  - [ ] Set `SCOUT_DRIVER=collection`

### CI/CD

- [ ] Add a `dusk` job to `.github/workflows/tests.yml`
  - [ ] Set `APP_URL=http://127.0.0.1:8000` (no Docker networking in CI)
  - [ ] Use `php artisan dusk:chrome-driver --detect` to match the CI Chrome version
  - [ ] Start ChromeDriver: `./vendor/laravel/dusk/bin/chromedriver-linux --port=9515 &`
  - [ ] Start web server: `php artisan serve --no-reload &`
  - [ ] Build assets first: `npm run build`
  - [ ] Run `php artisan dusk`
  - [ ] Upload `tests/Browser/screenshots` and `tests/Browser/console` as artifacts on failure

### Tests

- [ ] Adapt the default `ExampleTest.php` generated by `dusk:install`
  - [ ] The default test visits `/` — since the app requires auth, update to visit `/login` and assert the login page loads (e.g., `assertSee('Iniciar sesión')` or similar Spanish-language text visible on the login page)
- [ ] Run `php artisan dusk` and confirm the smoke test passes
- [ ] Run `php artisan test --compact` and confirm existing Pest tests still pass

### Cleanup

- [ ] Add to `.gitignore`:
  - [ ] `tests/Browser/screenshots/`
  - [ ] `tests/Browser/console/`
- [ ] Add `.env.dusk.local` to `.gitignore` (contains credentials; track `.env.dusk.example` instead)
- [ ] Create `.env.dusk.example` with placeholder values (same structure as `.env.dusk.local` but no real passwords)

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
