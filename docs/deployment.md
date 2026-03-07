# SGTE Production Deployment Guide

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [What Was Done](#what-was-done)
4. [Local Testing](#local-testing)
5. [Dokploy Deployment](#dokploy-deployment)
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

The production Dockerfile (`docker/production/Dockerfile`) uses three stages:

1. **node:24-alpine** — Installs npm dependencies and runs `npm run build:ssr` to produce `public/build/` (client assets) and `bootstrap/ssr/` (SSR bundle).
2. **composer:latest** — Installs PHP dependencies with `--no-dev` (no dev packages).
3. **dunglas/frankenphp** — Final image with PHP extensions, Node.js runtime (for SSR), Supervisor, application code, and cached config/routes/views.

---

## What Was Done

### New Files

| File | Description |
|------|-------------|
| `docker/production/Dockerfile` | Multi-stage production build |
| `docker/production/supervisord.conf` | Supervisor config (4 processes) |
| `docker/production/start-container` | Entrypoint: migrations + supervisor |
| `compose.staging.yaml` | PostgreSQL, Redis, Typesense, MinIO, Mailpit |
| `.dockerignore` | Excludes dev artifacts from build context |
| `config/octane.php` | Octane configuration (server: frankenphp) |

### Modified Files

| File | Change |
|------|--------|
| `composer.json` | Added `laravel/octane` to `require` |
| `composer.lock` | Updated lockfile |

### What Was NOT Changed

- `docker/8.5/` — Sail dev container remains untouched
- `compose.yaml` — Dev compose remains untouched
- No database migrations, no route changes, no frontend changes

---

## Local Testing

### Prerequisites

You need Docker installed on your **host machine** (not inside the Sail container). These commands run from the project root on your host.

### 1. Build the Image

```bash
docker build -f docker/production/Dockerfile -t sgte-app:latest .
```

This takes a few minutes the first time (downloading base images, installing extensions). Subsequent builds use cached layers.

### 2. Start Supporting Services

```bash
# Create a .env file for staging (copy and adjust)
cp .env.example .env.staging

# Edit .env.staging with staging values (see Environment Variables section below)

# Start supporting services
docker compose -f compose.staging.yaml --env-file .env.staging up -d
```

Wait for services to be healthy:

```bash
docker compose -f compose.staging.yaml ps
```

### 3. Run the App Container

```bash
docker run --rm -d \
  --name sgte-app \
  --network sgte_sgte \
  -p 8000:8000 \
  -p 8080:8080 \
  --env-file .env.staging \
  sgte-app:latest
```

### 4. Verify

```bash
# Check health endpoint
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/up
# Expected: 200

# Check all supervisor processes are running
docker exec sgte-app supervisorctl status
# Expected:
#   horizon    RUNNING   pid ...
#   octane     RUNNING   pid ...
#   reverb     RUNNING   pid ...
#   ssr        RUNNING   pid ...

# Check application logs
docker logs sgte-app --tail 50

# Access the app in browser
open http://localhost:8000
```

### 5. Stop Everything

```bash
docker stop sgte-app
docker compose -f compose.staging.yaml down
```

### Full Local Test with Compose (Alternative)

Uncomment the `app` service in `compose.staging.yaml` and run:

```bash
docker compose -f compose.staging.yaml --env-file .env.staging up -d --build
```

This builds and starts everything in one command.

---

## Dokploy Deployment

### Step 1: Set Up Supporting Services

In Dokploy, create a **Compose** project for the infrastructure services:

1. Go to **Projects** > **Create Project** (e.g., "SGTE Infrastructure")
2. Add a **Compose** service
3. Paste the contents of `compose.staging.yaml`
4. Set the required environment variables (see below)
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
2. Build the Docker image (multi-stage)
3. Start the container
4. The entrypoint runs migrations automatically
5. Supervisor starts all four processes

---

## Environment Variables

### Minimal `.env.staging` Example

```env
APP_NAME=SGTE
APP_ENV=staging
APP_KEY=base64:GENERATE_WITH_php_artisan_key_generate
APP_DEBUG=false
APP_URL=https://staging.sgte.example.com

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_CO

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

# Database — hostname matches compose service name
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=sgte
DB_USERNAME=sail
DB_PASSWORD=CHANGE_THIS_IN_PRODUCTION

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
AWS_SECRET_ACCESS_KEY=CHANGE_THIS_IN_PRODUCTION
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=sgte
AWS_ENDPOINT=http://minio:9000
AWS_URL=https://staging.sgte.example.com/storage
AWS_USE_PATH_STYLE_ENDPOINT=true

MEDIA_DISK=media

# Mail — Mailpit for staging, real SMTP for production
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@sgte.example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Search — Typesense
SCOUT_DRIVER=typesense
SCOUT_QUEUE=true
TYPESENSE_HOST=typesense
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
TYPESENSE_API_KEY=CHANGE_THIS_IN_PRODUCTION

# WebSockets — Reverb
REVERB_APP_ID=sgte-app-id
REVERB_APP_KEY=CHANGE_THIS_IN_PRODUCTION
REVERB_APP_SECRET=CHANGE_THIS_IN_PRODUCTION
REVERB_HOST=ws.staging.sgte.example.com
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

VITE_APP_NAME="${APP_NAME}"

# Super Admin (for seeding)
SUPER_ADMIN_USER="admin@sgte.example.com"
SUPER_ADMIN_PASSWORD=CHANGE_THIS_IN_PRODUCTION
```

### Key Differences from Development

| Variable | Development | Staging/Production |
|----------|-------------|-------------------|
| `APP_ENV` | `local` | `staging` / `production` |
| `APP_DEBUG` | `true` | `false` |
| `SESSION_DRIVER` | `database` | `redis` |
| `QUEUE_CONNECTION` | `redis` | `redis` |
| `CACHE_STORE` | `redis` | `redis` |
| `SCOUT_QUEUE` | `false` | `true` |
| `LOG_LEVEL` | `debug` | `warning` |
| `BCRYPT_ROUNDS` | `12` | `12` |

---

## Deployment Lifecycle

### Initial Deployment

```
1. Push code to repository (develop branch for staging)
2. Dokploy detects the push (webhook or manual trigger)
3. Dokploy builds the Docker image:
   ├── Stage 1: npm ci + npm run build:ssr
   ├── Stage 2: composer install --no-dev
   └── Stage 3: Copy into frankenphp image + cache config/routes/views
4. Dokploy starts the new container
5. start-container entrypoint runs:
   ├── php artisan storage:link
   ├── php artisan migrate --force
   └── supervisord starts (octane, horizon, reverb, ssr)
6. Dokploy routes traffic to the new container
7. Old container is stopped and removed
```

### Subsequent Deployments (Code Updates)

```
1. Developer pushes code changes
2. Dokploy rebuilds the image (cached layers speed this up)
3. New container starts → runs migrations → starts processes
4. Dokploy performs zero-downtime swap:
   ├── New container receives traffic
   └── Old container drains and stops
5. Horizon gracefully terminates (stopwaitsecs=3600 allows jobs to finish)
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
docker logs sgte-app

# Common issues:
# - Missing APP_KEY → run: php artisan key:generate --show
# - Database unreachable → verify DB_HOST matches compose service name
# - Permission errors → verify storage/ and bootstrap/cache/ are writable
```

### Processes crashing

```bash
# Check individual process status
docker exec sgte-app supervisorctl status

# Restart a specific process
docker exec sgte-app supervisorctl restart octane

# Check process-specific logs
docker exec sgte-app supervisorctl tail octane stderr
```

### Migration failures

```bash
# Run migrations manually
docker exec sgte-app php artisan migrate --force

# Check migration status
docker exec sgte-app php artisan migrate:status
```

### SSR not working

```bash
# Check if Node.js is available
docker exec sgte-app node --version

# Check if SSR bundle exists
docker exec sgte-app ls -la bootstrap/ssr/

# Restart SSR process
docker exec sgte-app supervisorctl restart ssr
```

### Cache issues after deployment

```bash
# Clear all caches
docker exec sgte-app php artisan optimize:clear

# Rebuild caches
docker exec sgte-app php artisan optimize
```

### PHP 8.5 Note

The production Dockerfile currently uses `dunglas/frankenphp:latest-php8.4-bookworm` because PHP 8.5 is not yet available in the FrankenPHP Docker images. When a `php8.5` tag becomes available, update line 27 of `docker/production/Dockerfile`:

```dockerfile
# Change from:
FROM dunglas/frankenphp:latest-php8.4-bookworm AS production

# Change to:
FROM dunglas/frankenphp:latest-php8.5-bookworm AS production
```
