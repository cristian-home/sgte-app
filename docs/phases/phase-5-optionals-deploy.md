# Phase 5: Optional Modules and Deploy

> **Status: IN PROGRESS** — Deployment completed, optional modules pending

## Objective

Implement the optional modules (FUEC, GPS) as latent functionality and prepare the production deployment.

## Covered requirements

- **REQ-007** - FUEC Generation (optional)
- **REQ-010** - Vehicle Location Tracking (optional GPS)

## Dependencies

- No hard blockers; can start in parallel with Phase 4
- Requires services and vehicles from Phases 1-2

---

## Tasks

### 5.1 FUEC module (REQ-007)

> This module ships **disabled** initially. The logic is in place, ready to be enabled in the future.

- Feature flag to enable/disable the FUEC module
- Pre-generation validations:
  - Valid contract
  - Valid vehicle documents
  - Valid driver license
- PDF generation with:
  - Contract, vehicle, and driver data
  - Service origin and destination
  - Date and time
  - Verification QR code
  - Unique consecutive number
- Public verification page on QR scan (status VIGENTE/ANULADO)
- PDF storage on MinIO
- Consecutive from the range authorized by MinTransporte

### 5.2 Optional GPS location (REQ-010)

> GPS is optional. The system works fully without it.

- Location registration by the driver (automatic via browser geolocation or manual)
- Storage: coordinates + timestamp + `es_manual` indicator
- Map view with active vehicles (only those with a reported location)
- Does not block any operation if no GPS data is available

### 5.3 Deploy preparation ✅

- **Dockerization:**
  - Multi-stage Dockerfile with FrankenPHP (4 stages: composer → base → build → production)
  - `compose.staging.yaml` with profiles: infrastructure (always) + app (`local` profile)
  - `.dockerignore` optimized for production builds
- **Dokploy:**
  - CI/CD workflow (`deploy-staging.yml`) with automatic redeploy via Dokploy API
  - Documented production environment variables
  - Automatic SSL/HTTPS via Dokploy/Caddy
- **Infrastructure:**
  - Laravel Octane with FrankenPHP as the production server
  - Supervisor for Octane + Horizon + Reverb + SSR
  - Config/route caching in the entrypoint (runtime env vars)
  - Automatic compression (gzip + brotli + zstd) via FrankenPHP/Caddy
- **Documentation:** Full guide in [`docs/deployment.md`](../deployment.md)
- **Requirement:** [frankenphp-production-docker.md](../requirements/frankenphp-production-docker.md)

### 5.4 Pre-production checklist

- [x] Initial data seeders (roles, permissions, document types, incident types)
- [ ] Load testing with representative data (100 vehicles, 300 services/day)
- [ ] Security review (CSRF, XSS, SQL injection, validations)
- [ ] Configure rate limiting
- [ ] Configure production logs
- [ ] Document backup and restore process

---

## Packages

| Package | Use |
| ------- | --- |
| `simplesoftwareio/simple-qrcode` | QR generation for FUEC |
| `league/flysystem-aws-s3-v3` | Storage on MinIO (S3 driver) |
| `barryvdh/laravel-dompdf` | PDF for FUEC (reused from Phase 4) |

## Completion criteria

- [ ] FUEC module implemented and disabled (feature flag)
- [ ] PDF generation with QR working
- [ ] Public QR verification page
- [ ] Working GPS location recording (automatic and manual)
- [x] Working multi-stage Docker with FrankenPHP
- [x] Staging compose with all infrastructure services
- [x] CI/CD staging deploy configured (Dokploy)
- [x] SSL/HTTPS configured (Dokploy/Caddy)
- [ ] Automatic database backups configured
- [ ] Laravel scheduler configured for expiration alerts
