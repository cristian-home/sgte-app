# SGTE Production Deployment Guide

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Local Testing](#local-testing)
4. [Dokploy Deployment](#dokploy-deployment)
5. [CI/CD (GitHub Actions)](#cicd-github-actions)
6. [Environment Variables](#environment-variables)
7. [Deployment Lifecycle](#deployment-lifecycle)
8. [Troubleshooting](#troubleshooting)

---

## Overview

SGTE uses **Laravel Octane with FrankenPHP** as the production application server, replacing the development `php artisan serve`. The production setup runs inside a single Docker container managed by Supervisor, which launches four processes:

| Process | Command | Port | Purpose |
|---------|---------|------|---------|
| **Octane** | `octane:start --server=frankenphp` | 8000 | HTTP application server |
| **Horizon** | `horizon` | — | Queue worker management |
| **Reverb** | `reverb:start` | 8080 | WebSocket server |
| **SSR** | `inertia:start-ssr` | 13714 | Server-side rendering |

Supporting services (PostgreSQL, Redis, Typesense, MinIO, Mailpit) run as separate containers via `compose.staging.yaml`.

---

## Architecture

```
                        ┌─────────────────────────┐
                        │      Dokploy/Traefik     │
                        │    (TLS termination)      │
                        └──────┬──────────┬────────┘
                               │ :8000    │ :8080
                        ┌──────▼──────────▼────────┐
                        │    SGTE App Container     │
                        │  ┌─────────────────────┐  │
                        │  │     Supervisor       │  │
                        │  ├─────────────────────┤  │
                        │  │ Octane (FrankenPHP)  │  │
                        │  │ Horizon              │  │
                        │  │ Reverb               │  │
                        │  │ Inertia SSR          │  │
                        │  └─────────────────────┘  │
                        └──────┬──────────┬────────┘
                               │          │
              ┌────────────────┼──────────┼────────────────┐
              │                │          │                │
        ┌─────▼────┐    ┌─────▼────┐  ┌──▼──┐    ┌───────▼──────┐
        │ PostgreSQL│    │  Redis   │  │MinIO│    │  Typesense   │
        │  :5432    │    │  :6379   │  │:9000│    │    :8108     │
        └──────────┘    └──────────┘  └─────┘    └──────────────┘
```

### Docker Image — Multi-Stage Build

The production Dockerfile (`docker/production/Dockerfile`) uses four stages:

1. **composer:latest** — Installs PHP dependencies with `--no-dev --ignore-platform-reqs`.
2. **dunglas/frankenphp (base)** — Shared base with PHP extensions, Node.js, and Supervisor.
3. **base (build)** — Copies source + vendor, runs `npm ci && npm run build:ssr`. The Wayfinder Vite plugin needs PHP to generate TypeScript route functions, which is why the frontend build cannot use a standalone Node image.
4. **base (production)** — Final image with application code, vendor, built assets, and cached views/events.

### Entrypoint (`start-container`)

The entrypoint runs at container startup (not at build time) because it needs runtime environment variables:

1. `php artisan config:cache` — caches config with actual env vars
2. `php artisan route:cache` — caches routes
3. `php artisan storage:link` — creates public storage symlink (idempotent)
4. `php artisan migrate --force` — runs pending migrations
5. `supervisord` — starts all four processes

### SSR Configuration

Vite SSR is configured with `ssr.noExternal: true` in `vite.config.ts`, which bundles all dependencies (React, Inertia, etc.) into the SSR output. This eliminates the need for `node_modules` at runtime — only the Node.js binary is required.

### Compression

FrankenPHP/Caddy compresses all HTTP responses (HTML, JSON, JS, CSS) automatically via the `encode zstd br gzip` directive in Octane's Caddyfile. Compression is negotiated per-request based on the client's `Accept-Encoding` header, with priority: Zstandard > Brotli > Gzip. No additional configuration is needed.

---

## Files

| File | Description |
|------|-------------|
| `docker/production/Dockerfile` | Multi-stage production build (4 stages) |
| `docker/production/supervisord.conf` | Supervisor config (4 processes) |
| `docker/production/start-container` | Entrypoint: config cache + migrations + supervisor |
| `compose.staging.yaml` | Infrastructure services + app (via `local` profile) |
| `.dockerignore` | Excludes dev artifacts from build context |
| `config/octane.php` | Octane configuration (server: frankenphp) |
| `.env.stg` | Environment variables for local testing (gitignored) |

---

## Local Testing

### Prerequisites

Docker installed on the **host machine** (not inside the Sail container). All commands run from the project root.

### 1. Create the Environment File

```bash
cp .env.stg.example .env.stg   # or create manually
```

See [Environment Variables](#environment-variables) for the full reference. Key values for local testing:

- `DB_HOST=pgsql`, `REDIS_HOST=redis`, `TYPESENSE_HOST=typesense` (compose service names)
- `DB_PASSWORD=secret` (must match the compose pgsql service)
- `APP_KEY=base64:...` (generate with `php artisan key:generate --show`)

### 2. Start Everything

```bash
docker compose -f compose.staging.yaml --profile local --env-file .env.stg up -d --build
```

This single command:
- Builds the production image from `docker/production/Dockerfile`
- Starts all infrastructure services (pgsql, redis, typesense, minio, mailpit)
- Waits for services to be healthy before starting the app
- Starts the app container with env vars from `.env.stg`
- Runs migrations automatically via the entrypoint

Subsequent runs without code changes can omit `--build`:

```bash
docker compose -f compose.staging.yaml --profile local --env-file .env.stg up -d
```

### 3. Verify

```bash
# Health endpoint (expected: 200)
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/up

# All supervisor processes running
docker exec sgte-app-app-1 supervisorctl status
# Expected:
#   horizon    RUNNING   pid ...
#   octane     RUNNING   pid ...
#   reverb     RUNNING   pid ...
#   ssr        RUNNING   pid ...

# Application logs
docker compose -f compose.staging.yaml logs app --tail 50

# Access the app in browser
open http://localhost:8000
```

### 4. Stop Everything

```bash
docker compose -f compose.staging.yaml --profile local --env-file .env.stg down
```

To also remove volumes (database data, redis, etc.):

```bash
docker compose -f compose.staging.yaml --profile local --env-file .env.stg down -v
```

---

## Dokploy Deployment

### Step 1: Set Up Supporting Services

In Dokploy, create a **Compose** project for the infrastructure services:

1. Go to **Projects** > **Create Project** (e.g., "SGTE Infrastructure")
2. Add a **Compose** service
3. Paste the contents of `compose.staging.yaml` as-is — the `app` service has `profiles: [local]` so Dokploy will skip it automatically
4. Set the required environment variables (see [Environment Variables](#environment-variables))
5. Deploy

### Step 2: Set Up the Application

In Dokploy, create an **Application** for SGTE:

1. Go to **Projects** > **Create Project** (e.g., "SGTE App") or use the same project
2. Add an **Application** service
3. Source: **Git** — point to your repository and branch (`develop` for staging, `main` for production)
4. Build type: **Dockerfile**
5. Dockerfile path: `docker/production/Dockerfile`
6. Set port to `8000`

### Step 3: Configure Networking

The app container must be on the same Docker network as the supporting services to resolve hostnames (`pgsql`, `redis`, `typesense`, `minio`, `mailpit`):

1. In the app service settings, go to **Advanced** > **Networks**
2. Add the network created by the compose project (usually `<project-name>_sgte`)
3. Alternatively, set the compose network as external and reference it in both

### Step 4: Configure Environment Variables

In the app service settings, go to **Environment** and add all required variables (see [Environment Variables](#environment-variables) section).

### Step 5: Configure Domain & TLS

1. In the app service, go to **Domains**
2. Add your domain (e.g., `staging.sgte.example.com`)
3. Dokploy/Traefik handles TLS automatically via Let's Encrypt
4. Set the container port to `8000`

### Step 6: Configure WebSocket Domain (Reverb)

If WebSocket connections need to be accessible externally:

1. Add a second domain for Reverb (e.g., `ws.staging.sgte.example.com`)
2. Set the container port to `8080`
3. Update `REVERB_HOST` and `VITE_REVERB_HOST` env vars to match

### Step 7: Deploy

Click **Deploy**. Dokploy will:
1. Clone the repo
2. Build the Docker image (4-stage multi-stage build)
3. Start the container
4. The entrypoint caches config/routes, runs migrations, then starts Supervisor
5. Supervisor starts all four processes

---

## CI/CD (GitHub Actions)

### Option A: Dokploy Auto-Deploy (Simplest)

Dokploy can connect directly to your GitHub repository and auto-deploy on every push — no GitHub Actions needed:

1. In Dokploy, go to the app service > **Deployments**
2. Enable the GitHub webhook
3. Every push to the configured branch triggers a build + deploy

This is the simplest option but provides no test gating — a push with failing tests will still deploy.

### Option B: GitHub Actions + Dokploy Webhook (Recommended)

Run your test suite first, and only deploy if tests pass. The workflow triggers Dokploy's API to redeploy. The Docker build happens on your VPS (not in CI), keeping GitHub Actions minutes low.

#### Setup

1. In Dokploy, go to **Settings** > **API/Tokens** and create an API token
2. In your Dokploy app settings, copy the **Application ID** (visible in the URL or General tab)
3. In GitHub, go to **Settings** > **Secrets and variables** > **Actions** and add:
   - `DOKPLOY_URL` — your Dokploy instance URL (e.g., `https://dokploy.example.com`)
   - `DOKPLOY_TOKEN` — the API token from step 1
   - `DOKPLOY_APP_ID` — the application ID from step 2

#### Workflow

The deploy workflow (`.github/workflows/deploy-staging.yml`) uses `workflow_run` to trigger after the existing `tests` and `linter` workflows succeed — no need to duplicate test steps:

```yaml
name: deploy-staging

on:
  workflow_run:
    workflows: [tests, linter]
    types: [completed]
    branches: [develop]

jobs:
  deploy:
    runs-on: ubuntu-latest
    if: >-
      github.event.workflow_run.conclusion == 'success' &&
      github.event.workflow_run.head_branch == 'develop'

    steps:
      - name: Trigger Dokploy deployment
        run: |
          curl -sSf -X POST "${{ secrets.DOKPLOY_URL }}/api/application.redeploy" \
            -H "Authorization: Bearer ${{ secrets.DOKPLOY_TOKEN }}" \
            -H "Content-Type: application/json" \
            -d '{"applicationId": "${{ secrets.DOKPLOY_APP_ID }}"}'
```

#### How It Works

```
Push to develop
      │
      ├──▶ tests workflow (Pest on PHP 8.5)
      ├──▶ linter workflow (Pint + Prettier + ESLint)
      │
      ▼ both pass
┌──────────────────┐         ┌─────────────────┐
│  deploy-staging   │────────▶│  Dokploy webhook │
│  (workflow_run)   │         │  Build + Deploy  │
└──────────────────┘         └─────────────────┘
      │ either fails
      ▼
  No deployment
```

- **Tests (PHP 8.5) and linting run in existing CI workflows** (already configured)
- **Deploy workflow triggers only after both succeed** via `workflow_run`
- **Docker build runs on your VPS** via Dokploy (keeps CI minutes and costs low)
- The deploy job itself takes ~5 seconds (a single API call)

---

## Environment Variables

### Minimal `.env.stg` Example (Local Testing)

```env
APP_NAME=SGTE
APP_ENV=staging
APP_KEY=base64:GENERATE_WITH_php_artisan_key_generate
APP_DEBUG=false
APP_URL=http://localhost:8000

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_CO

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug

# Database — hostname matches compose service name
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=sgte
DB_USERNAME=sail
DB_PASSWORD=secret

# Session, Queue, Cache — use Redis in staging/production
SESSION_DRIVER=redis
SESSION_LIFETIME=120
QUEUE_CONNECTION=redis
CACHE_STORE=redis
BROADCAST_CONNECTION=reverb

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Storage — MinIO (S3-compatible)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=sail
AWS_SECRET_ACCESS_KEY=password
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=sgte
AWS_ENDPOINT=http://minio:9000
AWS_URL=http://localhost:9000/sgte
AWS_USE_PATH_STYLE_ENDPOINT=true

MEDIA_DISK=media

# Mail — Mailpit for staging
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@sgte.local"
MAIL_FROM_NAME="${APP_NAME}"

# Search — Typesense
SCOUT_DRIVER=typesense
SCOUT_QUEUE=false
TYPESENSE_HOST=typesense
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
TYPESENSE_API_KEY=xyz

# WebSockets — Reverb
REVERB_APP_ID=sgte-app-id
REVERB_APP_KEY=sgte-app-key
REVERB_APP_SECRET=sgte-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

VITE_APP_NAME="${APP_NAME}"

# Super Admin (for seeding)
SUPER_ADMIN_USER="admin@sgte.local"
SUPER_ADMIN_PASSWORD=password
```

### Key Differences: Local vs Staging/Production

| Variable | Local Testing | Staging/Production |
|----------|-------------|-------------------|
| `APP_URL` | `http://localhost:8000` | `https://staging.sgte.example.com` |
| `APP_DEBUG` | `false` | `false` |
| `LOG_LEVEL` | `debug` | `warning` |
| `DB_PASSWORD` | `secret` | Strong unique password |
| `SCOUT_QUEUE` | `false` | `true` |
| `AWS_URL` | `http://localhost:9000/sgte` | `https://staging.sgte.example.com/storage` |
| `REVERB_HOST` | `localhost` | `ws.staging.sgte.example.com` |
| `REVERB_PORT` | `8080` | `443` |
| `REVERB_SCHEME` | `http` | `https` |

---

## Deployment Lifecycle

### Initial Deployment

```
1. Push code to repository (develop branch for staging)
2. CI runs tests → on success, triggers Dokploy webhook
3. Dokploy builds the Docker image:
   ├── Stage 1: composer install --no-dev
   ├── Stage 2: Base frankenphp image with PHP extensions + Node.js
   ├── Stage 3: npm ci + npm run build:ssr (with PHP for Wayfinder)
   └── Stage 4: Production image with built assets + view/event cache
4. Dokploy starts the new container
5. start-container entrypoint runs:
   ├── php artisan config:cache (with runtime env vars)
   ├── php artisan route:cache
   ├── php artisan storage:link
   ├── php artisan migrate --force
   └── supervisord starts (octane, horizon, reverb, ssr)
6. Dokploy routes traffic to the new container
7. Old container is stopped and removed
```

### Subsequent Deployments (Code Updates)

```
1. Developer pushes code changes
2. CI runs tests → triggers Dokploy on success
3. Dokploy rebuilds the image (cached layers speed this up)
4. New container starts → caches config → runs migrations → starts processes
5. Dokploy performs zero-downtime swap:
   ├── New container receives traffic
   └── Old container drains and stops
6. Horizon gracefully terminates (stopwaitsecs=3600 allows jobs to finish)
```

### Rollback

```
1. In Dokploy, go to the app's deployment history
2. Click "Rollback" on the previous working deployment
3. Dokploy restarts the previous image
4. If migrations need reversal: ssh into the container and run
   php artisan migrate:rollback --step=N
```

### Database Migrations

Migrations run automatically on every deployment via the entrypoint script. This is safe because:

- `--force` flag is required in production (Laravel safety check)
- Migrations are idempotent — running already-applied migrations is a no-op
- If a migration fails, the container exits and Dokploy keeps the old container running

For destructive migrations (dropping columns/tables), consider:
1. Deploy the code change without the destructive migration
2. Verify everything works
3. Deploy the destructive migration separately

### Database Seeding

Reference data and default users are handled by **data migrations**, not seeders. This means `php artisan migrate --force` is the only command needed — no separate seeding step.

| Environment | Command | What runs |
|-------------|---------|-----------|
| **Production** | `migrate --force` (auto on boot) | Reference data migration (roles, permissions, departments, document types, EPS, pension/severance funds, incident types) + 5 default users |
| **Staging** | `migrate --force` (auto on boot) | Same as production + demo data migration (third parties, drivers, vehicles, contracts, invoices, services) |
| **Development** | `migrate:fresh --seed` | Both data migrations + `DatabaseSeeder` (additional demo data via factories) |

**Default users** (created by data migration, one per role):
- Super admin: credentials from `SUPER_ADMIN_USER`/`SUPER_ADMIN_PASSWORD` env vars (defaults: `superadmin@sgte.app` / `password`)
- Admin (`admin@sgte.app`), Operator (`operator@sgte.app`), Driver (`driver@sgte.app`), Accounting (`accounting@sgte.app`)

**Adding new reference data**: create a new migration (e.g., `php artisan make:migration add_vandalism_incident_type`). It runs once on the next deploy via `migrate --force`.

### Monitoring & Health

- **Health endpoint**: `GET /up` returns HTTP 200 when the app is healthy
- **Supervisor status**: `docker exec <container> supervisorctl status`
- **Application logs**: `docker logs <container>` (all process output goes to stdout/stderr)
- **Horizon dashboard**: `https://your-domain.com/horizon` (protected by auth)
- **Queue health**: Horizon monitors queue sizes and worker status

### Scaling Considerations

The current setup runs all processes in a single container. For future scaling:

| Scenario | Solution |
|----------|----------|
| Need more HTTP capacity | Increase `--workers` count or run multiple app containers behind Traefik |
| Need more queue throughput | Adjust Horizon config (`config/horizon.php`) to add more workers |
| Need independent WebSocket scaling | Extract Reverb into its own container with a separate supervisor config |
| Need horizontal scaling | Run multiple app containers; ensure sessions use Redis (already configured) |

---

## Troubleshooting

### Container won't start

```bash
# Check container logs
docker compose -f compose.staging.yaml logs app --tail 100

# Common issues:
# - Missing APP_KEY → run: php artisan key:generate --show
# - Database unreachable → verify DB_HOST matches compose service name
# - Permission errors → verify storage/ and bootstrap/cache/ are writable
# - Config cache with wrong values → entrypoint should re-cache on every start
```

### Processes crashing

```bash
# Check individual process status
docker exec sgte-app-app-1 supervisorctl status

# Restart a specific process
docker exec sgte-app-app-1 supervisorctl restart octane

# Check process-specific logs
docker exec sgte-app-app-1 supervisorctl tail octane stderr
```

### Migration failures

```bash
# Run migrations manually
docker exec sgte-app-app-1 php artisan migrate --force

# Check migration status
docker exec sgte-app-app-1 php artisan migrate:status
```

### SSR not working

```bash
# Check if Node.js is available
docker exec sgte-app-app-1 node --version

# Check if SSR bundle exists
docker exec sgte-app-app-1 ls -la bootstrap/ssr/

# Restart SSR process
docker exec sgte-app-app-1 supervisorctl restart ssr
```

### Cache issues after deployment

```bash
# Clear all caches
docker exec sgte-app-app-1 php artisan optimize:clear

# Rebuild caches
docker exec sgte-app-app-1 php artisan optimize
```

### Database credential issues

PostgreSQL only sets credentials on first volume initialization. If credentials change after the volume was created, you must recreate the volume:

```bash
docker compose -f compose.staging.yaml --profile local --env-file .env.stg stop pgsql
docker compose -f compose.staging.yaml --profile local --env-file .env.stg rm -f pgsql
docker volume rm sgte-app_sgte-pgsql
docker compose -f compose.staging.yaml --profile local --env-file .env.stg up -d
```

