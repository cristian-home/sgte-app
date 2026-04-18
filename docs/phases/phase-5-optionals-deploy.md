# Phase 5: Optional Modules and Deploy

> **Status: IN PROGRESS** — Deployment completed; FUEC (REQ-007) shipped behind a feature flag (`fuec-generation` merged); GPS (REQ-010) still pending.

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

### 5.1 FUEC module (REQ-007) — ✅ done

> **Status: shipped behind feature flag `SGTE_FUEC_ENABLED`.** `fuec-generation` merged — full backend + frontend + public QR verification + MinTransporte range CRUD + PDF generation to MinIO. bacon/bacon-qr-code (already installed via Fortify 2FA) is used directly for QR rendering; `simplesoftwareio/simple-qrcode` was skipped due to a version conflict with Fortify's bacon-qr-code ^3.0 pin.

- [x] Feature flag: `config('sgte.fuec_enabled')` reads from env; `EnsureFuecEnabled` middleware 404s every FUEC route when disabled; sidebar hides the group via shared `auth.featureFlags.fuec` Inertia prop.
- [x] Pre-generation validations — via `App\Rules\FuecPreGenerationChecks` (shared with `FuecStoreRequest` and re-run inside `FuecGenerator`'s transaction): contract vigente + covers today, vehicle SOAT/RTM/operation-card non-expired, driver license non-expired + compatible category, active MinTransporte range with numbers remaining, no duplicate active FUEC.
- [x] PDF generation — dompdf-rendered `resources/views/fuecs/pdf.blade.php` with Contract, Vehicle, Driver, Service (origin/destination), QR (SVG data-URI from `App\Support\QrCode`), and legal disclaimer footer.
- [x] Public verification page (`/fuec/verify/{uuid}`) — standalone Blade (no Inertia, no auth) showing VIGENTE/ANULADO + summary fields.
- [x] PDF storage on MinIO — `Storage::disk('s3')->put("fuecs/{$consecutive}.pdf", $bytes)`; stored at generation, streamed on `/fuecs/{fuec}/pdf`.
- [x] Consecutive from the MinTransporte range — `FuecGenerator` locks `fuec_number_ranges FOR UPDATE`, computes `max(consecutive_number within range) + 1 or range.range_from`, throws `FuecRangeExhaustedException` when > `range.range_to`.
- [x] New admin CRUD at `/fuec-number-ranges` for registering resolutions + activating/deactivating ranges. New permission `MANAGE_FUEC_NUMBER_RANGES`.
- [x] Cancel-only modify semantics — admins can `POST /fuecs/{fuec}/cancel` with a reason (min:10/max:500) that writes an activity log entry. No edit/soft-delete/hard-delete. Regeneration = cancel + create new.

Deferred to a follow-up requirement:
- Dashboard warning card when the active range has fewer than 50 remaining consecutives.
- Committed UI regression tests (Dusk) — the Pest coverage (33 tests: 13 generator + 20 controller/verify/range) pins every critical path; interactive Playwright MCP verification works against the running app.

### 5.2 Optional GPS location (REQ-010)

> **Status: scaffolded only.** A `vehicle_locations` table, model and basic CRUD exist, but there is **no** map view, **no** driver-side capture flow (browser geolocation or manual input), and **no** active-service filtering. GPS is optional and the rest of the system works fully without it.

- Location registration by the driver (automatic via browser geolocation or manual)
- Storage: coordinates + timestamp + `is_manual` indicator
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
