# Identified Bugs — 2026-05-21

> **Project:** SGTE (Sistema de Gestión de Transporte Especial)
> **Branch:** `develop`
> **Author:** Reported by the product owner; formalized 2026-05-21
> **Purpose:** Formal catalogue of four confirmed defects, with reproduction steps,
> expected vs. actual behavior, impact, and suspected root cause. A separate
> *Enhancements* section records one requested migration that is not a bug.

Each defect is written so it can be lifted directly into the `/create-req` →
`/address-req` workflow. Suspected root causes name concrete files and line
ranges as of the date above; line numbers may drift as the code evolves.

## Summary

| ID | Title | Severity | Area | Status |
|----|-------|----------|------|--------|
| BUG-001 | Silent login failure after driver account creation | High | Auth / Drivers | Resolved (2026-05-21) |
| BUG-002 | Contract modal submit triggers the underlying service form | High | Services / Contracts (UI) | Resolved (2026-05-21) |
| BUG-003 | Driver "finish service" action does not close the service | High | Services / Driver | Resolved (2026-05-21) |
| BUG-004 | Driver can add incidents / modify a service after it is closed | Medium | Services / Incidents | Open |

| ID | Title | Type | Area |
|----|-------|------|------|
| ENH-001 | Migrate mapping stack from Mapbox to Google Maps | Enhancement | Maps / GPS |

---

## BUG-001 — Silent login failure after driver account creation

### Summary

When a new driver (*conductor*) is created with the "create user account" option
enabled, the user record and the welcome/invitation email are generated
correctly. After the recipient opens the email and sets a password through the
link, the resulting account cannot complete a login: the login form is submitted
with a valid email and the new password, nothing visibly happens, and **no error
message is shown**.

### Severity

**High** — affected drivers are completely unable to use the application; the
only workaround is manual administrator intervention.

### Affected area & files

- `app/Http/Controllers/DriverController.php` — `store()`, user creation block (`~132-166`, account creation `~145-156`).
- `app/Actions/Fortify/ResetUserPassword.php` — `reset()` (`19-28`), the action invoked by the invitation link.
- `app/Providers/FortifyServiceProvider.php` — `Fortify::authenticateUsing()` closure (`47-61`).
- `app/Http/Middleware/EnsurePasswordChanged.php` — global middleware that gates users with `must_change_password = true`.
- `bootstrap/app.php` — registers `EnsurePasswordChanged` in the web middleware stack (`~31-32`).
- `app/Http/Controllers/Settings/PasswordController.php` — `update()` (`~28`), the **only** place `must_change_password` is reset to `false`.
- `app/Notifications/DriverAccountInvitationNotification.php` — builds the invitation/password-setup email.
- `app/Models/User.php` — `password` (`hashed`) and `must_change_password` casts.

### Steps to reproduce

1. Sign in as an administrator.
2. Go to **Conductores** and create a new driver.
3. Enable the **"create user account"** option and provide a valid `account_email`.
4. Submit. The driver is created and the success message confirms an invitation
   email was sent.
5. Open the invitation email (Mailpit in local/staging) and follow the
   "set your password" link.
6. Enter a valid new password and submit the reset form.
7. The browser lands on the `/login` page.
8. Enter the driver's email and the password just set, and submit the login form.
9. Observe that the login does not complete and no error is displayed.

### Expected behavior

After setting a password through the invitation link, the driver can sign in and
reach their driver dashboard (`/driver`) without further obstacles.

### Actual behavior

The login form submits and the session does not reach the intended destination;
no validation error or feedback is shown to the user.

### Impact

- Every driver provisioned through the "create account" flow is locked out.
- Administrators must intervene manually for each affected driver.
- Erodes trust in the driver onboarding flow.

### Suspected root cause

The reporter noted the cause was unclear. Investigation points to one confirmed
code gap as the primary suspect, plus two secondary factors:

1. **Primary — `must_change_password` is never cleared by the invitation flow.**
   `DriverController::store()` creates the user with `must_change_password => true`
   (`DriverController.php:151`). The invitation link runs
   `ResetUserPassword::reset()`, which updates the password but **does not** clear
   `must_change_password`. That flag is only ever reset to `false` by the in-app
   settings flow (`Settings/PasswordController::update()`). The global
   `EnsurePasswordChanged` middleware therefore keeps redirecting the
   freshly-authenticated user to `user-password.edit` instead of letting them
   reach the dashboard, so the login *appears* not to work even when
   authentication itself succeeds.
2. **Secondary — no auto-login after password reset.** Fortify's reset flow ends
   on `/login`, forcing a second manual sign-in. Combined with (1), the overall
   experience is confusing and easily read as a failure.
3. **Checked, not the cause — double hashing.** `Hash::make()` at creation plus
   the `User` model's `hashed` cast do not compound: the `hashed` cast detects an
   already-hashed value and skips re-hashing. This was considered and ruled out.

**To confirm:** after step 8, inspect the authenticated session and the user's
`must_change_password` value, and check whether the request is being redirected
to `user-password.edit` by `EnsurePasswordChanged`.

### Resolution (2026-05-21)

The bug was reproduced end-to-end with Playwright. Login *succeeds*; the failure
is an **infinite redirect loop** afterward, caused by **two** independent root
causes (the "Suspected root cause" above identified only one):

1. **The driver is created unverified.** `DriverController` (and `UserController`)
   passed `'email_verified_at' => now()` to `User::create()`, but `email_verified_at`
   is not in `User::$fillable`, so Eloquent silently discarded it. Since
   `User implements MustVerifyEmail`, the `verified` middleware then blocked the
   account. (The seeded users are unaffected because factories bypass `$fillable`.)
2. **`must_change_password` was never cleared** by `ResetUserPassword::reset()`.

Together they loop: `EnsurePasswordChanged` → `/settings/password` → (`verified`
fails) → `/email/verify` → (`EnsurePasswordChanged`, not whitelisted) →
`/settings/password` → … → `ERR_TOO_MANY_REDIRECTS`. The SPA cannot complete the
navigation, so it visually stays on `/login` with no error.

**Fix:**
- `DriverController::store()` / `inviteAccount()` and `UserController::store()` now
  call `$user->markEmailAsVerified()` after creation (the dead `email_verified_at`
  array key was removed).
- `ResetUserPassword::reset()` now also clears `must_change_password`.
- `EnsurePasswordChanged` whitelists the email-verification routes
  (`verification.notice` / `verification.verify` / `verification.send`) so the
  loop can never form again.

Regression tests added/updated in `tests/Feature/Auth/PasswordResetTest.php`,
`tests/Feature/Middleware/EnsurePasswordChangedTest.php`,
`tests/Feature/Http/Controllers/DriverControllerTest.php`, and
`tests/Feature/Http/Controllers/UserControllerTest.php`.

---

## BUG-002 — Contract modal submit triggers the underlying service form

### Summary

In the Services (*Servicios*) create/edit view, opening the "new contract" modal
and clicking its **Guardar** (Save) button submits the **service** form behind
the modal instead of creating the contract. The app attempts to create a service
with incomplete data and errors out, so a contract can never be created from this
modal.

### Severity

**High** — the in-context "create contract" shortcut is unusable; the service
form may also fail or submit partial data as a side effect.

### Affected area & files

- `resources/js/pages/services/create.tsx` — outer service `<form onSubmit={submit}>` (`~124`), with `<ContractDialog>` rendered **inside** that form (`~144-152`).
- `resources/js/pages/services/edit.tsx` — same structure as `create.tsx`.
- `resources/js/components/contracts/contract-dialog.tsx` — the contract modal, which renders its own `<form onSubmit={submit}>` and a `<Button type="submit">` (`~183-213`).
- `resources/js/components/ui/dialog.tsx` — Radix `Dialog` wrapper; content is rendered through `DialogPortal`.

### Steps to reproduce

1. Sign in as an administrator or operator.
2. Navigate to **Servicios → Crear Servicio** (`/servicios/create`).
3. In the contract field, click the **"+"** button to open the contract modal.
4. Fill the required contract fields (contract number, client, start/end dates,
   route description, object type).
5. Click **Guardar** inside the modal.
6. Observe that the contract is **not** created; instead the service form is
   submitted and fails because required service data is missing.

### Expected behavior

Clicking **Guardar** in the contract modal submits only the contract form,
creates the contract, closes the modal, and (in cascade mode) selects the new
contract in the service form — leaving the service form untouched.

### Actual behavior

The contract modal's submit button triggers the outer service form's
`onSubmit`. The application tries to create a service with incomplete data and
returns an error; the contract is never created.

### Impact

- Contracts cannot be created from the Services view.
- Users are forced to leave the service form, create the contract elsewhere, and
  start over — losing any unsaved service input.

### Suspected root cause

`<ContractDialog>` is rendered as a **React child of the outer service
`<form>`** (`services/create.tsx:144`, inside the `<form>` opened at line 124).
Although Radix `Dialog` portals the modal's DOM out of the service `<form>`,
React propagates events through the **React component tree**, not the DOM tree.
The `submit` event raised by the contract form therefore bubbles up the React
tree and also reaches the service `<form>`'s `onSubmit` handler.

Recommended direction: render `<ContractDialog>` as a **sibling** of the service
`<form>` (outside it) while keeping its open/close state in the parent — and/or
stop propagation of the contract form's submit event.

### Resolution (2026-05-21)

Reproduced with Playwright: a single click on the contract dialog's "Guardar"
fired **both** `POST /contracts` and `POST /services` — the empty service form's
`302` won the race and reloaded the page, aborting the contract request
client-side (the contract was still created server-side). Confirmed root cause as
above: React propagates the submit event through the component tree even though
Radix portals the dialog's DOM.

**Fix:**
- `services/create.tsx` — `<ContractDialog>` is now rendered as a **sibling** of
  the service `<form>` (outside it), so the contract form's submit event has no
  ancestor service `<form>` to bubble into. A comment pins it in place.
- `contract-dialog.tsx` — the dialog's `submit()` handler now also calls
  `e.stopPropagation()`, so the dialog is self-contained regardless of where it
  is mounted.

Verified after the fix: clicking "Guardar" fires only `POST /contracts`; the
contract is created, auto-selected in the service form, and no service is
submitted. Regression test added in `tests/Browser/ServiceFormTest.php`
(Dusk).

### Follow-up audit (2026-05-21)

A codebase-wide sweep for the same pattern found **10 dialog components that
render their own `<form>`**. One more live instance: `ThirdPartyDialog` (the
"Crear nuevo cliente" dialog) is nested inside `ContractForm`, so submitting it
also submitted the contract form — reproduced with Playwright (`POST
/third-parties` + `POST /contracts`).

Hardening fix: every dialog-with-form's `submit()` handler now calls
`e.stopPropagation()`, so a dialog's submit event can never reach an ancestor
`<form>` regardless of where the dialog is mounted. Applied to all 9 remaining
dialogs (`contract-dialog` already had it). Regression test for the nested case
added in `tests/Browser/ContractsIndexAndShowTest.php`. Verified: the nested
"Crear Tercero" dialog now fires only `POST /third-parties`.

---

## BUG-003 — Driver "finish service" action does not close the service

### Summary

When a driver marks a service as finished, the service is not closed in the
system: its `service_status` remains `open`. The service keeps showing as in
progress and an administrator must manually edit it and set the status to
`closed`.

### Severity

**High** — every driver-completed service is left in the wrong state, which
propagates to day summaries, FUEC generation, and invoicing.

### Affected area & files

- `app/Http/Controllers/DriverDashboardController.php` — `confirmEnd()` (`157-173`); records `actual_end_at` only.
- `app/Models/Service.php` — `service_status` field and cast.
- `app/Enums/ServiceStatus.php` — enum values `open` / `closed`.
- `database/migrations/2026_02_27_225424_create_services_table.php` — `service_status` column (`enum('open','closed')`, default `open`).
- Admin counterpart for comparison: `app/Http/Controllers/ServiceController.php` — `update()` and `app/Http/Requests/ServiceUpdateRequest.php` (the manual close path).

### Steps to reproduce

1. Sign in as a driver (`driver@sgte.app`).
2. Go to the driver dashboard (`/driver`, "Mis Servicios").
3. Pick a service already started (shows "En curso", `actual_start_at` set).
4. Click **Confirmar Fin** on the service card.
5. Observe that the card/service still shows status **Abierto** (open).
6. Confirm in the database: `SELECT service_status FROM services WHERE id = ?`
   returns `open`.

### Expected behavior

When the driver confirms the end of a service, the service transitions from
`open` to `closed` automatically (recording `actual_end_at`, and honoring the
invariant that a closed service has both `actual_start_at` and `actual_end_at`
set) — with no administrator intervention required.

### Actual behavior

`confirmEnd()` writes `actual_end_at = now()` but never updates
`service_status`. The service stays `open` until an administrator opens the
service edit form and changes the status to `closed` manually.

### Impact

- Driver-completed services remain in `open` state indefinitely.
- Day summaries and the operational calendar misreport services as in progress.
- Downstream processes that expect closed services (FUEC document generation,
  invoicing) are blocked or produce incorrect results.
- The audit trail records administrator edits instead of the genuine driver
  completion event.

### Suspected root cause

`DriverDashboardController::confirmEnd()` (`lines 157-173`) updates only
`actual_end_at`:

```php
$service->update([
    'actual_end_at' => now(),
]);
```

It never sets `service_status` to `ServiceStatus::Closed`. The status transition
exists only in the admin-facing `ServiceController::update()` path, so a service
finished by a driver is never closed automatically.

### Resolution (2026-05-21)

Reproduced as a driver: confirming start then end on service #54 left
`service_status = open` (card showed "Servicio completado" but the badge still
read "Abierto").

**Fix:** `DriverDashboardController::confirmEnd()` now sets
`service_status => ServiceStatus::Closed` alongside `actual_end_at`. A guard
(`abort_if($service->actual_start_at === null, 422, …)`) rejects a confirm-end
on a service that was never started, keeping the invariant that a closed service
carries both actual times.

Verified after the fix: a driver confirm-end transitions the service to
`closed` (badge "Cerrado"), no admin intervention. Regression tests added/updated
in `tests/Feature/Http/Controllers/DriverDashboardControllerTest.php` (close on
confirm-end; 422 when not started).

---

## BUG-004 — Driver can add incidents / modify a service after it is closed

### Summary

Once a service has been closed, a driver should not be able to add incidents
(*novedades*) or modify the service. Currently there is no backend restriction:
a driver can still create incidents against, and otherwise act on, a closed
service.

### Severity

**Medium** — data-integrity issue: closed services should be immutable, and
post-close incidents can affect billing.

### Affected area & files

- `app/Http/Controllers/ServiceIncidentController.php` — `store()` (`122-142`); checks only the `CREATE_INCIDENTS` permission, not the parent service status.
- `app/Http/Requests/ServiceIncidentStoreRequest.php` — validates `service_id` ownership but not `service_status`.
- `app/Rules/ServiceBelongsToAuthenticatedDriver.php` — confirms the driver owns the service; does not check status.
- `app/Http/Controllers/ServiceController.php` — `update()`; no explicit guard against editing a closed service.
- `resources/js/pages/driver/index.tsx` — the **Registrar Novedad** button is shown for every service regardless of status.

### Steps to reproduce

1. Have a service assigned to a driver and set its `service_status` to `closed`
   (e.g. via the admin edit form).
2. Sign in as that driver (`driver@sgte.app`).
3. Go to the driver dashboard (`/driver`).
4. On the closed service, click **Registrar Novedad**.
5. Fill the incident form (incident type, description, etc.) and submit.
6. Observe that the request succeeds (HTTP 200) and the incident is created and
   linked to the already-closed service.

### Expected behavior

Once a service is `closed`, the driver cannot create incidents for it or modify
its details. Attempts are rejected at the backend (validation/authorization
error) and the corresponding UI actions are hidden or disabled.

### Actual behavior

Incident creation against a closed service succeeds. No backend check rejects
the request, and the driver UI continues to offer the **Registrar Novedad**
action on closed services.

### Impact

- Incidents can be retroactively attached to completed work.
- Billing-affecting incidents (`affects_billing = true`) can change the financial
  picture of a service after it was considered final.
- The audit trail of closed services is polluted with post-close modifications.

### Suspected root cause

There is no status guard anywhere in the incident-creation chain:

- `ServiceIncidentController::store()` (`lines 122-142`) authorizes only the
  `CREATE_INCIDENTS` permission.
- `ServiceIncidentStoreRequest` validates that `service_id` exists and belongs to
  the authenticated driver (`ServiceBelongsToAuthenticatedDriver`) but never
  checks that the parent service is still `open`.
- `ServiceController::update()` has no explicit rule rejecting edits to a
  `closed` service.
- The driver UI (`driver/index.tsx`) renders **Registrar Novedad**
  unconditionally.

A guard on the parent service's `service_status` is needed in the backend
(controller/policy and/or form request), with the UI affordances hidden or
disabled to match.

---

## Enhancements / Requested Changes

These items were submitted alongside the bugs but are **not defects**. They are
recorded here for completeness and should be scoped separately.

### ENH-001 — Migrate mapping stack from Mapbox to Google Maps

**Type:** Enhancement / migration — **Area:** Maps / GPS

Replace the current Mapbox implementation with the **Google Maps JavaScript SDK
and APIs**. The migration should cover every mapping capability currently in use:

- Map rendering in the application views.
- Geolocation.
- Address search.
- Address validation.
- Routing / directions.
- Markers.
- **Static maps** for location previews.

**Scoping notes (to be resolved before implementation):**

- Requires a Google Maps API key and a billing account; the relevant Google APIs
  (Maps JavaScript, Places, Geocoding, Directions, Static Maps) must be enabled.
- Inventory the components that currently consume Mapbox (map views, address
  inputs, GPS pages) so the replacement surface is fully known.
- The `SGTE_GPS_ENABLED` feature flag gates the GPS sidebar group — coordinate
  the migration with that flag.
- Treat this as its own requirement (`/create-req`) rather than folding it into
  the bug-fix cycle.
