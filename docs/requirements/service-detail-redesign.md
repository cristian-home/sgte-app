---
name: service-detail-redesign
type: feat
scope: services
status: completed
priority: high
created_date: 2026-03-06
completed_date: 2026-03-06
srs_refs: ["REQ-003"]
migration_strategy: new
---

# Service Detail View Redesign

## Description

The current service detail view (`services/show`) is a flat, plain layout with simple label/value pairs in a single Card. It lacks visual hierarchy, iconography, and meaningful data visualization. This redesign transforms the page into a rich, card-based layout with icons, a route map placeholder, a timeline bar for schedule comparison, a billing summary with icons, and an inline incidents list. The goal is to make the interface more intuitive and informative for users at a glance.

## Acceptance Criteria

- [x] AC-1: WHEN a user navigates to a service detail page THEN the page title (`<Head>`) and page heading MUST display "Detalle de Servicio [Contract Number] - [Client Name]" (e.g., "Detalle de Servicio CT-0003-2026 - Hotel Dann Carlton Bogota").
- [x] AC-2: WHEN the page loads THEN it MUST display a two-column layout at the top row: left card "Datos Generales del Servicio" with icon-prefixed fields (Fecha, Contrato, Vehiculo, Conductor, Origen, Estado) and right card "Detalle de la Ruta" with origin (A marker) and destination (B marker) labels inside a styled placeholder area.
- [x] AC-3: WHEN the page loads THEN it MUST display a second row with two cards: left "Cronograma y Tiempos" with visual timeline bars comparing planned vs actual time ranges, and right "Resumen de Facturacion" with decorative icons and billing fields.
- [x] AC-4: WHEN the service has incidents THEN a full-width "Incidentes" card MUST appear below the second row, listing each incident with its type name, description, and reported_at timestamp. If there are no incidents, this section MUST display an empty state message.
- [x] AC-5: WHEN the user has the `UPDATE_PROJECTED_SERVICES` permission THEN the "Editar" button MUST appear in the top-right area of the page (next to the page heading). The "Volver" button MUST also appear in the top-right area.
- [x] AC-6: WHEN the day status is "executed" THEN the day-executed alert MUST be preserved and visible at the top of the page content, showing the executor name and timestamp.
- [x] AC-7: WHEN the service has incidents THEN the incidents count badge MUST be preserved and visible near the page heading.
- [x] AC-8: WHEN the planned and actual times are both present THEN the timeline bar component MUST visually render two horizontal bars (planned in one color, actual in another) scaled proportionally within a shared time axis, with time labels at start/end points and a duration badge for planned duration.

## Technical Specification

### Data Model

No new tables or columns are required. The existing `services` and `service_incidents` tables have all necessary data.

The backend controller MUST eager-load the full `serviceIncidents` relationship (with `incidentType` and `registrar`) instead of just the count, so incident details are available to the frontend.

### Enums

No new enums required.

### Routes

No new routes required. The existing `services.show` route (`GET /services/{service}`) is used.

### Permissions

No new permissions required. Existing `VIEW_SERVICES` and `UPDATE_PROJECTED_SERVICES` permissions apply.

### Pages

| Page | Component Path | Description |
|------|---------------|-------------|
| Show (redesigned) | `resources/js/pages/services/show.tsx` | Redesigned service detail view |
| Timeline Bar | `resources/js/components/services/service-timeline-bar.tsx` | Reusable timeline bar component for planned vs actual time comparison |

## Migration Strategy

- **new**: No migrations needed. This is a frontend-only redesign with a minor backend change to eager-load relationships.

## Tasks

### Backend

- [x] Task 1: Update `ServiceController@show` to eager-load `serviceIncidents.incidentType` and `serviceIncidents.registrar` in addition to the existing count
  - In `app/Http/Controllers/ServiceController.php`, modify the `show()` method
  - Change the `->load(...)` call to include `'serviceIncidents.incidentType'` and `'serviceIncidents.registrar'`
  - Keep `->loadCount('serviceIncidents')` for the badge count
  - Follow the existing eager-loading pattern already used for `contract.thirdParty`, `vehicle.thirdParty`, etc.

### Frontend

- [x] Task 2: Update page heading and `<Head>` title to show contract number + client name
  - In `resources/js/pages/services/show.tsx`
  - Build title string: `Detalle de Servicio ${service.contract?.contract_number} - ${clientName}`
  - `clientName` derives from `service.contract?.third_party?.company_name` or `${first_name} ${first_lastname}`
  - Set both `<Head title={...}>` and an `<h1>` element with this string
  - Update breadcrumbs: second item title MUST show the contract number instead of generic "Ver"

- [x] Task 3: Restructure layout to two-column card grid with "Datos Generales del Servicio" card
  - Replace the single Card layout with a responsive grid: `grid grid-cols-1 lg:grid-cols-2 gap-4`
  - Left card: "Datos Generales del Servicio" with icon-prefixed fields
  - Each field MUST have a Lucide icon to its left: `Calendar` (fecha), `FileText` (contrato), `Truck` (vehiculo), `User` (conductor), `MapPin` (origen), `CircleDot` (estado)
  - Fields layout: each field is a horizontal row with icon + label/value pair
  - Use existing `Card`, `CardHeader`, `CardTitle`, `CardContent` components
  - Preserve third-party vehicle display logic (COD 18)

- [x] Task 4: Create "Detalle de la Ruta" card with route placeholder
  - Right card in the first row
  - Display a styled placeholder area (e.g., dashed border, muted background, `min-h-[200px]`)
  - Inside the placeholder, show origin label with "A" marker badge and destination label with "B" marker badge
  - Origin text: municipality name (department) format
  - Destination text: municipality name (department) + address format
  - Include a subtle text note: "Mapa en desarrollo" (Map in development)

- [x] Task 5: Create `ServiceTimelineBar` component for planned vs actual time visualization
  - New file: `resources/js/components/services/service-timeline-bar.tsx`
  - Props: `plannedStart: string | null`, `plannedDuration: number | null`, `actualStart: string | null`, `actualEnd: string | null`
  - Compute a shared time axis from the earliest start to the latest end
  - Render two horizontal bars stacked vertically:
    - Top bar (planned): colored bar (e.g., blue/primary) spanning from plannedStart to plannedStart + plannedDuration
    - Bottom bar (actual): colored bar (e.g., green/emerald) spanning from actualStart to actualEnd
  - Show time labels at start and end of each bar
  - Show a duration badge for planned duration (e.g., "192 min")
  - If actual times are missing, show the actual bar as a dashed/empty placeholder
  - Include a legend indicating which color is planned vs actual

- [x] Task 6: Create "Cronograma y Tiempos" card (left of second row)
  - Uses the `ServiceTimelineBar` component from Task 5
  - Card title: "Cronograma y Tiempos"
  - Below the timeline bar, show text fields for: Hora Inicio Planificada, Duracion Planificada, Hora Inicio Real, Hora Fin Real, Duracion Real
  - Use a compact layout (e.g., two columns of small text below the bar)

- [x] Task 7: Create "Resumen de Facturacion" card (right of second row)
  - Card title: "Resumen de Facturacion"
  - Display 4 decorative Lucide icons in a row at the top of the card content: `Users` (grupo), `DollarSign` (valor), `Hash` (cantidad), `CreditCard` (metodo de pago)
  - Below the icons, display the billing fields in a grid: Grupo de Facturacion, Valor Unitario, Cantidad, Metodo de Pago
  - Preserve the existing currency formatting and PaymentMethod badge

- [x] Task 8: Create "Incidentes" card (full-width, third row)
  - Full-width card below the two-column rows
  - Card title: "Incidentes" with the existing count badge next to it (moved from header)
  - List each incident as a row with:
    - Incident type name (`serviceIncident.incidentType.name`)
    - Description (truncated to ~100 chars with tooltip for full text)
    - Reported at timestamp (formatted as date + time)
    - Registrar name (`serviceIncident.registrar.name`)
    - `affects_billing` indicator (small badge if true)
  - If no incidents, show an empty state: centered muted text "No se han registrado incidentes para este servicio."
  - Use a simple table or stacked list layout

- [x] Task 9: Move action buttons to top-right and preserve day-executed alert
  - Place "Editar" and "Volver" buttons at the top of the page, aligned right, in the same row as the page heading `<h1>`
  - Layout: `<h1>` on the left, buttons on the right using `flex justify-between items-start`
  - Preserve the `Can` permission wrapper on "Editar"
  - Day-executed alert MUST remain visible below the heading/buttons row and above the cards
  - Preserve the existing alert content (executor name + timestamp)

### Tests

- [x] Task 10: Update Pest feature test for service show endpoint
  - In `tests/Feature/Http/Controllers/ServiceControllerTest.php`
  - Add or update test: verify the show response includes `serviceIncidents` relationship data (not just count)
  - Assert that each incident includes `incidentType` and `registrar` nested data
  - Create a service with 2 incidents using factories, then assert the response props contain the incident details

- [x] Task 11: Create Dusk browser test for redesigned service detail view
  - New file: `tests/Browser/ServiceDetailTest.php`
  - Run `php artisan migrate:fresh --seed --no-interaction` for clean state
  - Login with super admin credentials from `env('SUPER_ADMIN_USER')` / `env('SUPER_ADMIN_PASSWORD')`
  - Test scenario 1: Navigate to a service detail page
    - Assert page heading contains contract number
    - Assert "Datos Generales del Servicio" card heading is visible
    - Assert "Detalle de la Ruta" card heading is visible
    - Assert "Cronograma y Tiempos" card heading is visible
    - Assert "Resumen de Facturacion" card heading is visible
    - Assert "Editar" and "Volver" buttons are visible in the top area
    - Assert no error messages or exception banners are visible
    - Take screenshot: `service-detail-overview`
  - Test scenario 2: Service with incidents
    - Create or navigate to a service that has incidents
    - Assert "Incidentes" card heading is visible
    - Assert incident type and description text is displayed
    - Take screenshot: `service-detail-incidents`
  - Test scenario 3: Service without incidents
    - Navigate to a service with no incidents
    - Assert empty state message "No se han registrado incidentes" is visible
    - Take screenshot: `service-detail-no-incidents`

## Verification

### UI (Laravel Dusk)

Dusk browser tests in `tests/Browser/ServiceDetailTest.php`. Use super admin credentials from `env('SUPER_ADMIN_USER')` / `env('SUPER_ADMIN_PASSWORD')`. Run `php artisan migrate:fresh --seed --no-interaction` before tests that need a clean database.

- [ ] Navigate to service detail page and verify all 4 card sections are visible with correct headings
- [ ] Verify page heading shows "Detalle de Servicio [contract number] - [client name]"
- [ ] Verify action buttons (Editar, Volver) are in the top-right area
- [ ] Verify incidents list renders for services with incidents
- [ ] Verify empty state for services without incidents
- [ ] Verify no error messages, exceptions, or broken layouts
- [ ] Take screenshots at each step for visual review

### API (curl)

```bash
# Login and get session cookie
curl -s -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@sgte.com","password":"password"}' \
  -c cookies.txt

# Verify service show endpoint returns incidents with nested relations
curl -s -X GET http://localhost/services/1 \
  -H "Accept: application/json" \
  -b cookies.txt | jq '.props.service.service_incidents'
```

## Dependencies

- None. All required models, relationships, and permissions already exist.

## Notes

- The map in "Detalle de la Ruta" is intentionally a placeholder. A future requirement will address real map integration (Leaflet/Google Maps) using municipality coordinates.
- The `ServiceTimelineBar` component is designed to be reusable — it could later be used in the Gantt view or day summary pages.
- The incidents section shows all incidents inline. If performance becomes an issue with many incidents per service, pagination or a "show more" pattern can be added later.
- All UI labels MUST remain in Spanish to match the existing application convention.
- Icons MUST use the Lucide React icon library (`lucide-react`), which is already a project dependency.
