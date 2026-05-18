# Phase 5: Optional Modules and Deploy

> **Status: 🟡 MOSTLY COMPLETED** — Optional modules shipped and staging deploy works; the §5.4 pre-production checklist still has open items (load test, security review, rate limiting, production logs, scheduler entry for expiration alerts). See §5.4 below.

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

### 5.2 Optional GPS location (REQ-010) — ✅ done

> **Status: shipped behind feature flag `SGTE_GPS_ENABLED`.** `gps-tracking` merged — EnsureGpsEnabled middleware, schema edits (service_id + accuracy + captured_by + composite index), scoped permissions (VIEW/REGISTER/DELETE_VEHICLE_LOCATIONS), driver-side capture UX on the /driver dashboard, opportunistic auto-capture on confirmStart/End, dedicated admin map at /gps/map (react-leaflet + OpenStreetMap tiles, 30s polling), new <VehicleCombobox /> primitive, rebuild of the four vehicle-locations pages. Scout removed from the model (volatile time-series).

- [x] Location registration by the driver (automatic via browser geolocation or manual) — `DriverLocationController` + inline card on /driver dashboard pending F2 polish.
- [x] Storage: coordinates + timestamp + `is_manual` indicator — schema shipped; plus service_id link + accuracy + captured_by.
- [x] Map view with active vehicles — `/gps/map` via `VehicleLocationMapController` + Leaflet/OSM page.
- [x] Does not block any operation if no GPS data is available — `persistLocationIfProvided` in DriverDashboardController wraps the write in try/catch per SRS §REQ-010 AC#4.

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
- [ ] Load testing with representative data (100 vehicles, 300 services/day) — **PENDING, no evidence in repo**
- [ ] Security review (CSRF, XSS, SQL injection, validations) — **PENDING, no formal audit run; `/security-review` command is available**
- [ ] Configure rate limiting — **PENDING, no `RateLimiter::for(...)` definitions beyond Laravel defaults**
- [ ] Configure production logs — **PENDING, still on framework defaults; no centralized log shipping or rotation policy**
- [x] Document backup and restore process — handled via Dokploy + custom `backup` / `restore` scripts in the `SGTE Services` compose stack

---

## Packages

| Package | Use |
| ------- | --- |
| `simplesoftwareio/simple-qrcode` | QR generation for FUEC |
| `league/flysystem-aws-s3-v3` | Storage on MinIO (S3 driver) |
| `barryvdh/laravel-dompdf` | PDF for FUEC (reused from Phase 4) |

## Completion criteria

- [x] FUEC module implemented and gated by `SGTE_FUEC_ENABLED` (enabled by default in current envs)
- [x] PDF generation with QR working (bacon/bacon-qr-code + barryvdh/laravel-dompdf)
- [x] Public QR verification page (`/fuec/verify/{uuid}`)
- [x] Working GPS location recording (automatic and manual via `DriverLocationController`)
- [x] Working multi-stage Docker with FrankenPHP
- [x] Staging compose with all infrastructure services
- [x] CI/CD staging deploy configured (Dokploy)
- [x] SSL/HTTPS configured (Dokploy/Caddy)
- [x] Automatic database backups configured (Dokploy-managed)
- [x] Laravel scheduler configured for expiration alerts (`Schedule::command('app:check-expirations')->dailyAt('07:00')` in `routes/console.php`)
