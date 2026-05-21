---
name: migrate-mapbox-to-google-maps
type: feat
scope: maps
status: completed
priority: medium
created_date: 2026-05-21
completed_date: 2026-05-21
srs_refs: ["REQ-010"]
migration_strategy: modify-existing
---

# ENH-001 — Migrate the Mapping Stack from Mapbox to Google Maps

## Description

SGTE's entire mapping stack currently runs on **Mapbox**: address autocomplete
and geocoding (Mapbox Geocoding v6), interactive maps (`react-leaflet` rendering
Mapbox raster tiles), and driving routes (Mapbox Directions v5). ENH-001
(`docs/bugs/2026-05-21-identified-bugs.md`) requests replacing the whole stack
with the **Google Maps Platform** and adding **static-map location previews** as
a new capability.

This is a full, one-way migration. The application is **not deployed** and has no
production data, so there is **no backward compatibility with Mapbox**: every
Mapbox/Leaflet code path, dependency, env var, and config key is removed and
replaced with Google equivalents. The database is rebuilt with
`php artisan migrate:fresh --seed`.

**Capability mapping:**

| Capability | Mapbox (removed) | Google (new) |
|---|---|---|
| Address autocomplete | Geocoding v6 forward | Places Autocomplete (Places API New) |
| Reverse geocoding | Geocoding v6 reverse | Geocoding API (`google.maps.Geocoder`) |
| Interactive map | `react-leaflet` + Mapbox raster tiles | Maps JavaScript API via `@vis.gl/react-google-maps` |
| Routing / directions | Directions v5 (`DirectionsClient`) | Routes API (server-side `RoutesClient`) |
| Static maps | not used | Maps Static API (new capability) |

**Decisions locked before drafting** (do not revisit during implementation):

- Google Cloud project is already provisioned. A **browser key** (HTTP-referrer
  restricted) and a **server key** (IP-restricted) are supplied via `.env`.
- The React wrapper is **`@vis.gl/react-google-maps`** (Google-maintained).
  `leaflet`, `react-leaflet`, `@types/leaflet`, and the dead
  `@mapbox/search-js-react` dependency are all removed.
- **Static maps are in scope** as a new feature, on the **service detail page**
  and the **driver dashboard service cards**.
- **Address validation** stays implicit — derived from the Google result's
  precision. The Address Validation API is **not** used.
- Google's `routable_points` analog does not exist; the place `location` is used
  directly and `pickRoutableCoords` is removed.

## Acceptance Criteria

- [x] AC1: WHEN an operator types ≥ 3 characters in the address field of the
  service form THEN the system SHALL show Google Places autocomplete suggestions
  restricted to Colombia (`includedRegionCodes: ['co']`) and biased toward the
  selected municipality's centroid when one is selected.
- [x] AC2: WHEN an operator selects an autocomplete suggestion THEN the system
  SHALL resolve the place details and store the address text, the coordinates as
  a `"lat,lng"` string (7 decimal places), the Google `place_id`, the accuracy
  as a Google `location_type` value, and set the coordinate source to `google`.
- [x] AC3: WHEN an operator opens the map picker and clicks or drags the pin
  THEN the system SHALL render an interactive Google map, reverse-geocode the pin
  to populate the "Cerca de:" hint, and on **Confirmar** store the coordinates
  with source `manual` and a `null` `place_id`.
- [x] AC4: WHEN an admin or operator opens `/gps/map` THEN the system SHALL
  render an interactive Google map showing one marker per active service's
  vehicle location, origin/destination markers, route polylines, and an info
  window per vehicle marker — with auto-fit bounds and the existing 5-minute
  auto-refresh preserved.
- [x] AC5: WHEN a service is saved with both origin and destination coordinates
  THEN the `FetchServiceRoute` job SHALL fetch driving route geometry from the
  Google Routes API and persist `route_geometry`, `route_distance_m`,
  `route_duration_s`, `route_fetched_at`, and `route_source = 'google'`.
- [x] AC6: WHEN any user views the service detail page (`services/show.tsx`)
  THEN the "Detalle de la Ruta" card SHALL display Google static-map previews for
  the origin and destination coordinates, replacing the "Mapa en desarrollo"
  placeholder; coordinates that are absent SHALL render a neutral empty state
  instead of a broken image.
- [x] AC7: WHEN a driver views their service cards on `/driver` THEN each card
  SHALL display Google static-map previews for the service's origin and
  destination coordinates when present.
- [x] AC8: WHEN a service is created or updated THEN `ServiceStoreRequest` SHALL
  accept and validate `origin_place_id` / `destination_place_id` (nullable
  string) and SHALL validate `origin_coordinates_source` /
  `destination_coordinates_source` against `['google', 'manual']`.
- [x] AC9: WHEN the codebase is built THEN it SHALL contain **no** reference to
  Mapbox or Leaflet — no imports, no `mapbox`/`leaflet` npm dependencies, no
  `MAPBOX_TOKEN` env var, no `services.mapbox` config key, no
  `App\Services\Mapbox` namespace — and `npm run build`, `npm run types`, Pint,
  and the Pest suite SHALL all pass.

## Technical Specification

### Data Model

Two columns are **added** to the existing `services` table:

```
services  (modify existing migration — add 2 columns)
├── origin_place_id        (varchar 255, nullable)  — Google Place ID for the origin address
└── destination_place_id   (varchar 255, nullable)  — Google Place ID for the destination address
```

- `origin_place_id` is placed immediately after `origin_coordinates_accuracy`.
- `destination_place_id` is placed immediately after
  `destination_coordinates_accuracy`.
- The Place ID is the durable Google reference for a geocoded address;
  `*_coordinates` (lat/lng) remain for rendering and routing. Both are populated
  only on a `source = 'google'` pick; a `source = 'manual'` pin leaves
  `*_place_id` null.

**Semantic changes to existing columns (no DDL change, value change only):**

- `origin_coordinates_source` / `destination_coordinates_source`: now store
  `'google'` instead of `'mapbox'` (still `'manual'` for map-picked pins).
- `route_source`: now stores `'google'` instead of `'mapbox'`.
- `origin_coordinates_accuracy` / `destination_coordinates_accuracy`: now store
  Google Geocoder `location_type` values (`ROOFTOP`, `RANGE_INTERPOLATED`,
  `GEOMETRIC_CENTER`, `APPROXIMATE`) instead of Mapbox Geocoding-v6 accuracy
  values. The longest value (`RANGE_INTERPOLATED`, 18 chars) fits the existing
  `varchar(20)` column.

### Enums

No PHP enum changes. `coordinates_source` and `route_source` are plain `varchar`
columns validated with `Rule::in(...)`; the TypeScript `CoordinatesSource` type
is a hand-written union in `location-field.tsx`, not an `enum:typescript`-generated
file. (Promoting these to a first-class PHP enum is explicitly **out of scope** —
see Notes.)

### Routes

No route changes. `/gps/map` (`gps.map`, gated by the `gps.enabled` middleware)
keeps its method, URI, controller, and name. No new endpoints.

### Permissions

None. Existing gates (`VIEW_VEHICLE_LOCATIONS` for `/gps/map`, the service
CRUD permissions) are unchanged.

### Configuration & Environment

**`config/services.php`** — remove the `mapbox` block, add:

```php
'google_maps' => [
    // Browser key: HTTP-referrer restricted. Shared with the Vite bundle
    // via VITE_GOOGLE_MAPS_BROWSER_KEY. Used for Maps JavaScript, Places
    // Autocomplete, client-side Geocoding, and Static Maps.
    'browser_key' => env('GOOGLE_MAPS_BROWSER_KEY'),
    // Server key: IP-restricted. Used server-side by RoutesClient.
    'server_key' => env('GOOGLE_MAPS_SERVER_KEY'),
    // Map ID for the vector map + AdvancedMarker support.
    'map_id' => env('GOOGLE_MAPS_MAP_ID'),
],
```

**`.env.example`** — remove `MAPBOX_TOKEN` / `VITE_MAPBOX_TOKEN`, add (with an
explanatory comment block matching the file's existing style):

```
GOOGLE_MAPS_BROWSER_KEY=
GOOGLE_MAPS_SERVER_KEY=
GOOGLE_MAPS_MAP_ID=
VITE_GOOGLE_MAPS_BROWSER_KEY="${GOOGLE_MAPS_BROWSER_KEY}"
VITE_GOOGLE_MAPS_MAP_ID="${GOOGLE_MAPS_MAP_ID}"
```

The browser key and Map ID reach the frontend through `import.meta.env.VITE_*`;
no controller or `HandleInertiaRequests` change is needed to pass them.

### Pages & Components

| Page / Component | Path | Change |
|---|---|---|
| Client maps config | `resources/js/lib/google-maps.ts` | **New** — replaces `mapbox.ts` |
| Geocoding/Places helper | `resources/js/lib/google-geocoding.ts` | **New** — replaces `mapbox-geocoding.ts` |
| Static map preview | `resources/js/components/services/location-static-map.tsx` | **New** |
| Mapbox config | `resources/js/lib/mapbox.ts` | **Delete** |
| Mapbox geocoding | `resources/js/lib/mapbox-geocoding.ts` | **Delete** |
| Address field | `resources/js/components/location-field.tsx` | **Rewrite** geocoding internals |
| Map picker modal | `resources/js/components/map-picker-modal.tsx` | **Rewrite** map rendering |
| GPS map page | `resources/js/pages/gps/map.tsx` | **Rewrite** map rendering |
| Service form | `resources/js/components/services/service-form.tsx` | **Adjust** — `APIProvider`, comments |
| Service detail | `resources/js/pages/services/show.tsx` | **Modify** — static maps |
| Driver dashboard | `resources/js/pages/driver/index.tsx` | **Modify** — static maps |
| City normalizer | `resources/js/lib/normalize-city.ts` | **Modify** — comment only |

### Packages

- **Add:** `@vis.gl/react-google-maps` (latest stable).
- **Remove:** `leaflet`, `react-leaflet`, `@types/leaflet`, `@mapbox/search-js-react`.

Adding/removing dependencies is pre-approved for this requirement.

## Migration Strategy

**`modify-existing`.** The application is not deployed and carries no production
data, so the `origin_place_id` / `destination_place_id` columns are added
directly to the existing `database/migrations/2026_02_27_225424_create_services_table.php`
migration rather than in a new migration file. After the change, the database is
rebuilt with `php artisan migrate:fresh --seed`. This keeps the schema history
clean and matches the project's `modify-existing` strategy for pre-deployment
work.

## Tasks

### Backend

- [x] **Task 1 — Add Place ID columns to the services migration.**
  In `database/migrations/2026_02_27_225424_create_services_table.php`:
  - [x] Add `$table->string('origin_place_id', 255)->nullable();` directly after
    the `origin_coordinates_accuracy` column.
  - [x] Add `$table->string('destination_place_id', 255)->nullable();` directly
    after the `destination_coordinates_accuracy` column.
  - [x] Update the inline Spanish comments at lines ~32 and ~36 that mention
    `'mapbox'` and "Geocoding v6" to describe Google (`'google'` source,
    `location_type` accuracy).
  - [x] Update the comment near line ~81 ("Mapbox returned no route…") to refer
    to the Google Routes API.

- [x] **Task 2 — Update the `Service` model.**
  In `app/Models/Service.php`:
  - [x] Add `'origin_place_id'` and `'destination_place_id'` to the `$fillable`
    array (next to the existing `*_coordinates*` keys).
  - [x] Add both keys to the activity-log `logOnly([...])` list so Place ID
    changes are audited alongside the coordinate fields.
  - [x] No cast needed (plain nullable strings). Leave the `route_geometry`
    `array` cast and the `booted()` route-refresh hooks unchanged.

- [x] **Task 3 — Update `ServiceStoreRequest` validation.**
  In `app/Http/Requests/ServiceStoreRequest.php`:
  - [x] Change `origin_coordinates_source` rule from
    `Rule::in(['mapbox', 'manual'])` to `Rule::in(['google', 'manual'])`.
  - [x] Change `destination_coordinates_source` rule the same way.
  - [x] Add `'origin_place_id' => ['nullable', 'string', 'max:255']`.
  - [x] Add `'destination_place_id' => ['nullable', 'string', 'max:255']`.
  - [x] Update the comment at line ~70 that mentions "picking a Mapbox
    suggestion" to say "Google".
  - [x] `ServiceUpdateRequest` extends `ServiceStoreRequest` and overrides no
    rules — no change required there; verify after editing.

- [x] **Task 4 — Replace the Mapbox config with `google_maps`.**
  In `config/services.php`, remove the `mapbox` array and add the `google_maps`
  array exactly as specified in *Configuration & Environment* above.

- [x] **Task 5 — Update `.env.example`.**
  Remove the `MAPBOX_TOKEN` / `VITE_MAPBOX_TOKEN` lines and their comment block;
  add the five `GOOGLE_MAPS_*` / `VITE_GOOGLE_MAPS_*` lines with an explanatory
  comment block matching the file's style. Note in the comment that the browser
  key must allow the local dev origin (`http://localhost`) as an HTTP referrer.

- [x] **Task 6 — Create `App\Services\Google\RoutesClient`.**
  New file `app/Services/Google/RoutesClient.php`, replacing
  `App\Services\Mapbox\DirectionsClient`. Mirror `DirectionsClient`'s public
  contract so `FetchServiceRoute` changes minimally:
  - [x] Constructor: `public function __construct(protected ?string $token = null)`
    — fall back to `config('services.google_maps.server_key')` when null/empty
    (same pattern as `DirectionsClient`).
  - [x] Method `driving(float $originLng, float $originLat, float $destLng, float $destLat): ?array`
    returning `array{geometry: array<int, array{0: float, 1: float}>, distance_m: int, duration_s: int}|null`.
  - [x] POST to `https://routes.googleapis.com/directions/v2:computeRoutes` with
    headers `X-Goog-Api-Key: <server_key>` and
    `X-Goog-FieldMask: routes.distanceMeters,routes.duration,routes.polyline.encodedPolyline`.
  - [x] Request body: `origin`/`destination` as `{location:{latLng:{latitude,longitude}}}`,
    `travelMode: 'DRIVE'`, `polylineEncoding: 'ENCODED_POLYLINE'`.
  - [x] Decode the returned `routes[0].polyline.encodedPolyline` (Google encoded
    polyline algorithm) into an array of `[lng, lat]` pairs so the stored
    `route_geometry` format is unchanged from the Mapbox era (the GeoJSON
    LineString `[lng, lat]` order consumed by `VehicleLocationMapController::geometryToLatLngs()`).
    Implement the decoder as a private method in this class.
  - [x] `duration` comes back as a string like `"843s"` — strip the trailing
    `s` and cast to int seconds.
  - [x] Return `null` on missing token, `ConnectionException`, non-2xx, or a
    malformed/empty `routes` array — log a warning with status + truncated body,
    matching `DirectionsClient`'s logging. Use `Http::timeout(10)->retry(2, 250, throw: false)`.
  - [x] Follow `app/Services/Mapbox/DirectionsClient.php` as the structural
    reference for error handling and PHPDoc.

- [x] **Task 7 — Rewire `FetchServiceRoute` to use `RoutesClient`.**
  In `app/Jobs/FetchServiceRoute.php`:
  - [x] Replace the `use App\Services\Mapbox\DirectionsClient;` import and the
    `handle(DirectionsClient $client)` type hint with `RoutesClient`.
  - [x] Change both `'route_source' => 'mapbox'` writes to `'route_source' => 'google'`.
  - [x] Update the class-level PHPDoc ("Fetches a driving route from Mapbox…")
    to refer to Google. Keep the `lat,lng` → `lng,lat` coordinate-order comment
    accurate.

- [x] **Task 8 — Delete the Mapbox backend service.**
  Delete `app/Services/Mapbox/DirectionsClient.php` and the now-empty
  `app/Services/Mapbox/` directory. Confirm no other file references the
  `App\Services\Mapbox` namespace.

- [x] **Task 9 — Update the address factory.**
  In `database/factories/Support/RealColombianAddresses.php`:
  - [x] Change every `'source' => 'mapbox'` (16 occurrences) to `'source' => 'google'`.
  - [x] Replace the Mapbox Geocoding-v6 `accuracy` values with Google
    `location_type` values: `rooftop`/`parcel`/`point` → `ROOFTOP`,
    `interpolated` → `RANGE_INTERPOLATED`, `approximate` → `APPROXIMATE`
    (use `GEOMETRIC_CENTER` for street-level entries if any). Keep the spread of
    precision levels so factory data still exercises the accuracy-badge tones.
  - [x] Optionally add a representative `'place_id'` key (a syntactically
    plausible `ChIJ…`-style string) to each entry; if added, ensure
    `ServiceFactory` maps it onto `origin_place_id` / `destination_place_id`.
    If a real-looking value cannot be produced, leave `place_id` absent (the
    columns are nullable).
  - [x] Update the file's header PHPDoc that explains the Mapbox `permanent`
    geocoding origin.

### Frontend

- [x] **Task 10 — Swap mapping dependencies.**
  - [x] `npm install @vis.gl/react-google-maps`.
  - [x] `npm uninstall leaflet react-leaflet @types/leaflet @mapbox/search-js-react`.
  - [x] Commit the resulting `package.json` + `package-lock.json`.
  - [x] Run via Sail on the host (`./vendor/bin/sail npm …`) — host `npm` fails
    (see project memory).

- [x] **Task 11 — Create `resources/js/lib/google-maps.ts`.**
  Replaces `mapbox.ts`. Exports:
  - [x] `GOOGLE_MAPS_BROWSER_KEY` and `GOOGLE_MAPS_MAP_ID` read from
    `import.meta.env.VITE_GOOGLE_MAPS_BROWSER_KEY` / `VITE_GOOGLE_MAPS_MAP_ID`,
    with a `import.meta.env.PROD` console warning when empty (mirror the old
    `mapbox.ts` warning).
  - [x] Default map center/zoom constants (Medellín, reusing the existing
    `gps/map.tsx` values; Bogotá fallback for the picker).
  - [x] `staticMapUrl({ lat, lng, zoom?, width?, height?, scale? })` — builds a
    `https://maps.googleapis.com/maps/api/staticmap` URL with a single red
    marker at the coordinate, `scale=2` for retina, and the browser key.
    Sensible defaults: `zoom=15`, `width=300`, `height=160`.

- [x] **Task 12 — Create `resources/js/lib/google-geocoding.ts`.**
  Replaces `mapbox-geocoding.ts`. Wraps the Google Maps JS SDK objects (loaded
  by `@vis.gl/react-google-maps`'s `useMapsLibrary`). Exports:
  - [x] Types `PlaceSuggestion` (prediction: `placeId`, primary/secondary text)
    and `ResolvedPlace` (`{ lat, lng, placeId, formattedAddress, locationType, placeName }`).
  - [x] `fetchAutocomplete(input, { sessionToken, locationBias?, signal? })` —
    uses `google.maps.places.AutocompleteSuggestion.fetchAutocompleteSuggestions`
    with `includedRegionCodes: ['co']`, `language: 'es'`, and an optional
    `locationBias` circle around the municipality centroid.
  - [x] `resolvePlace(placeId, sessionToken)` — fetches place details
    (`location`, `formattedAddress`, address components) to produce a
    `ResolvedPlace`. Round lat/lng to 7 decimals.
  - [x] `reverseGeocode(lat, lng)` — uses `google.maps.Geocoder` to produce a
    display string plus the detected city name (`locality` /
    `administrative_area_level_2` component) for the map-picker hint and chip
    auto-population.
  - [x] A helper mapping Google `location_type` → the accuracy-badge tone
    (`ROOFTOP` → green, `RANGE_INTERPOLATED` / `GEOMETRIC_CENTER` → yellow,
    `APPROXIMATE` → gray).
  - [x] Use a per-typeahead `AutocompleteSessionToken` (created when the user
    starts typing, consumed by `resolvePlace`) so autocomplete + details are
    billed as one session.

- [x] **Task 13 — Create `LocationStaticMap` component.**
  New `resources/js/components/services/location-static-map.tsx`:
  - [x] Props: `coordinates: string | null` (a `"lat,lng"` string), `label`
    (e.g. "Origen" / "Destino"), optional `className`, `width`, `height`.
  - [x] When coordinates parse, render an `<img>` with `src` from
    `staticMapUrl(...)`, `loading="lazy"`, descriptive `alt`, and rounded-border
    styling consistent with the project's card aesthetic.
  - [x] When coordinates are absent/unparseable, render a neutral muted empty
    state ("Sin ubicación", with a `MapPin` icon) at the same dimensions — never
    a broken image.

- [x] **Task 14 — Rewrite `map-picker-modal.tsx` on Google Maps.**
  - [x] Replace `react-leaflet` (`MapContainer`, `TileLayer`, `Marker`,
    `useMap`, `useMapEvents`) and the Leaflet icon-rebinding block with
    `@vis.gl/react-google-maps`: `<APIProvider>` (or rely on a parent provider),
    `<Map>` with `mapId={GOOGLE_MAPS_MAP_ID}`, `<AdvancedMarker>`.
  - [x] Click-to-drop: handle `<Map onClick={…}>`; drag: `<AdvancedMarker
    draggable onDragEnd={…}>`.
  - [x] Recenter-on-pin behaviour: pan via `useMap()` + `map.panTo(...)`,
    preserving the "don't zoom out below 14" rule.
  - [x] Keep the editable address draft input, the "Usar sugerencia" button, the
    debounced reverse-geocode "Cerca de:" hint, and the `onConfirm` payload
    shape (`{ coords, address, placeName }`) **unchanged**.
  - [x] Reverse geocoding goes through `google-geocoding.ts`'s `reverseGeocode`.

- [x] **Task 15 — Rewrite `gps/map.tsx` on Google Maps.**
  - [x] Replace `react-leaflet` (`MapContainer`, `TileLayer`, `Marker`,
    `Popup`, `CircleMarker`, `Polyline`, `useMap`) and the Leaflet icon block
    with `@vis.gl/react-google-maps`: `<APIProvider>` + `<Map mapId={…}>`.
  - [x] Vehicle markers → `<AdvancedMarker>` with `<InfoWindow>` carrying the
    existing popup content (plate, driver, timestamp, GPS/Manual badge, route
    distance/duration, link to the service).
  - [x] Origin/destination markers → small `<AdvancedMarker>`s with custom
    filled/hollow circle content, preserving the legend's meaning.
  - [x] Route polylines → a `Polyline` wrapper component built on `useMap()` +
    `new google.maps.Polyline(...)` (the library ships no `<Polyline>`); honor
    the per-service `serviceColor()` hue and the solid-vs-dashed
    confirmed/estimated distinction.
  - [x] Auto-fit bounds → replace `FitBoundsOnData` with a component that calls
    `map.fitBounds(new google.maps.LatLngBounds(...))` over all points.
  - [x] Keep the `MapLegend` overlay, the active-services count line, and the
    5-minute `router.reload` auto-refresh (with the `document.hidden` guard).

- [x] **Task 16 — Rewrite the geocoding internals of `location-field.tsx`.**
  - [x] Replace `forwardGeocode` / `findFeatureByMapboxId` / `pickRoutableCoords`
    imports with `google-geocoding.ts` functions.
  - [x] Typeahead pipeline: debounced `fetchAutocomplete`, biased by the
    selected municipality centroid (`locationBias` circle) instead of the
    Mapbox `proximity` string; keep the 250 ms debounce, 3-char minimum,
    `AbortController` cancellation, and keyboard navigation.
  - [x] Commit-on-pick: call `resolvePlace(placeId, sessionToken)`; store
    coordinates, `place_id`, `formattedAddress`, and the `location_type`
    accuracy. Remove the Mapbox `permanent` two-step (typeahead `permanent:false`
    → commit `permanent:true`) entirely — Google's place details call is the
    single commit step.
  - [x] Update `CoordinatesSource` to `'google' | 'manual' | ''` and the
    `onCoordinatesChange` source argument type to `'google' | 'manual'`.
  - [x] `pickRoutableCoords` is removed — use the place `location` directly
    (Google has no routable-point concept).
  - [x] `badgeTone` / `sourceLabel` / `CoordsIndicator`: map over Google
    `location_type` values and relabel "Mapbox" → "Google".
  - [x] Replace the "Powered by Mapbox" footer in `AddressDropdown` with the
    Google attribution required by Places policy (the "Powered by Google" logo
    asset) when predictions are shown outside a Google map.
  - [x] The `LocationField` must consume the surrounding `APIProvider` (added in
    `service-form.tsx`) so the Places library is loaded.

- [x] **Task 17 — Adjust `service-form.tsx`.**
  - [x] Wrap the form (or at least the `LocationField` + `MapPickerModal`
    subtree) in a single `<APIProvider apiKey={GOOGLE_MAPS_BROWSER_KEY}>` so
    both children share one Maps JS load. The service form renders inside the
    CRUD modal dialog; place the provider so it mounts with the form.
  - [x] Update any `place_id` plumbing: the form's data shape and the payload to
    `ServiceController` must carry `origin_place_id` / `destination_place_id`
    alongside the existing `*_coordinates*` fields.
  - [x] Update Mapbox-referencing comments (lines ~109–153, ~367, ~457) to
    describe Google.

- [x] **Task 18 — Wire static maps into the service detail page.**
  In `resources/js/pages/services/show.tsx`, replace the "Mapa en desarrollo"
  placeholder (~line 357) inside the "Detalle de la Ruta" card with two
  `LocationStaticMap` components — one for `service.origin_coordinates`, one for
  `service.destination_coordinates` — laid out beneath the existing origin and
  destination address blocks.

- [x] **Task 19 — Wire static maps into the driver dashboard.**
  In `resources/js/pages/driver/index.tsx`, add `LocationStaticMap` previews for
  the origin and destination coordinates to each service card's `CardContent`
  (near the existing origin/destination municipality rows, ~lines 260–271). Keep
  the cards compact — use a small preview size.

- [x] **Task 20 — Delete Mapbox frontend files & clean up comments.**
  - [x] Delete `resources/js/lib/mapbox.ts` and
    `resources/js/lib/mapbox-geocoding.ts`.
  - [x] Update the Mapbox comment in `resources/js/lib/normalize-city.ts`
    (the function still applies — it normalizes city names against the geocoder's
    place context; just retarget the wording to Google).
  - [x] Update the Mapbox mention in `resources/js/lib/debug-log.ts` (~line 71).
  - [x] Grep `resources/js` for `mapbox` / `leaflet` (case-insensitive) and
    confirm zero remaining references.

### Tests

- [x] **Task 21 — Create `tests/Feature/Services/Google/RoutesClientTest.php`.**
  - [x] `Http::fake()` a successful Routes API response with a known
    `encodedPolyline`; assert `driving()` returns decoded `[lng, lat]` geometry,
    correct `distance_m`, and `duration_s` parsed from the `"NNNs"` string.
  - [x] Assert `null` is returned for: empty server key, a non-2xx response, and
    a response with an empty/missing `routes` array.
  - [x] Assert the request carries the `X-Goog-Api-Key` and `X-Goog-FieldMask`
    headers and a body with `travelMode: 'DRIVE'`.
  - [x] Follow the existing Mapbox-era job/route tests for `Http::fake`
    conventions.

- [x] **Task 22 — Update `tests/Feature/Jobs/FetchServiceRouteTest.php`.**
  - [x] Swap the mocked/faked `DirectionsClient` for `RoutesClient`.
  - [x] Assert the persisted `route_source` is `'google'` on both the
    success and the no-route paths.
  - [x] Keep coverage of the "both coords required" and "marks attempted on
    failure" behaviours.

- [x] **Task 23 — Update coordinate/route feature tests for the `google` source.**
  - [x] `tests/Feature/Http/Controllers/ServiceControllerStoreCoordsTest.php` —
    change request payloads from `'…_coordinates_source' => 'mapbox'` to
    `'google'`; add assertions that `origin_place_id` / `destination_place_id`
    are accepted and persisted, and that an invalid source value (`'mapbox'`,
    now removed) is rejected with a validation error.
  - [x] `tests/Feature/Models/ServiceRouteCacheTest.php` — update any `'mapbox'`
    literals to `'google'`.
  - [x] `tests/Feature/Http/Controllers/VehicleLocationMapControllerTest.php` —
    update `'mapbox'` literals; the `route` geometry contract is unchanged.
  - [x] `tests/Feature/Http/Controllers/ServiceControllerTest.php` — update any
    `'mapbox'` literals.
  - [x] Grep `tests/` for `mapbox` (case-insensitive) and confirm zero
    remaining references.

- [x] **Task 24 — Dusk browser test: service form mapping.**
  In `tests/Browser/` (extend `ServiceFormTest.php` or add a sibling): assert the
  service create dialog renders the `LocationField` control without error
  banners, the address input and "Marcar en mapa" button are present with the
  expected Spanish labels, and opening the map picker shows the
  "Marcar ubicación en el mapa" dialog. Screenshot at each step. Do **not**
  assert Google's internal map tiles render (network-dependent) — assert the
  container and SGTE chrome only.

- [x] **Task 25 — Dusk browser test: GPS map & static-map previews.**
  - [x] `/gps/map` as admin: page renders without error banners; the heading,
    the active-services count line, and the `MapLegend` ("Símbolos", "Origen",
    "Destino", "Vehículo (GPS)") are present; screenshot.
  - [x] Service detail page: the "Detalle de la Ruta" card no longer shows
    "Mapa en desarrollo" and contains static-map `<img>` elements whose `src`
    contains `maps.googleapis.com/maps/api/staticmap`.
  - [x] Driver dashboard as `driver@sgte.app`: service cards render static-map
    previews; screenshot.

## Verification

### 1. Interactive verification — Playwright MCP

Reference users (all password `password`): `admin@sgte.app`, `operator@sgte.app`,
`driver@sgte.app`, `accounting@sgte.app`. Ensure the browser key allows
`http://localhost` as an HTTP referrer before testing.

- [ ] Scenario 1: As `admin`, open `/services/create`, select a municipality,
  type an address (≥ 3 chars), confirm Google autocomplete suggestions appear,
  pick one, and verify the address text + a coordinates indicator populate.
- [ ] Scenario 2: In the same form, click "Marcar en mapa", verify the Google
  map renders, click to drop a pin, confirm the "Cerca de:" reverse-geocode hint
  populates, press "Confirmar", and verify coordinates flow back to the form.
- [ ] Scenario 3: Save the service, then open its detail page and verify the
  "Detalle de la Ruta" card shows origin + destination static maps (no
  "Mapa en desarrollo").
- [ ] Scenario 4: As `admin`, open `/gps/map` and verify the Google map renders
  with markers/polylines for active services and the legend overlay.
- [ ] Scenario 5: As `driver@sgte.app`, open `/driver` and verify service cards
  show origin/destination static-map previews.
- [ ] Scenario 6: Read `mcp__laravel-boost__browser-logs` after each scenario
  and confirm no JS errors (e.g. Maps JS auth failures).

### 2. Backend regression — Pest feature tests

Run `./vendor/bin/sail test --compact`. Required coverage:

- [x] `RoutesClientTest` — polyline decode, distance/duration parsing, all
  `null` failure modes, request headers/body (Task 21).
- [x] `FetchServiceRouteTest` — `route_source = 'google'`, success + no-route
  paths (Task 22).
- [x] `ServiceControllerStoreCoordsTest` — `google` source accepted, `place_id`
  persisted, removed `mapbox` value rejected (Task 23).
- [x] `ServiceRouteCacheTest`, `VehicleLocationMapControllerTest`,
  `ServiceControllerTest` — green after the `google` literal updates (Task 23).
- [x] Full suite green; `vendor/bin/pint --dirty --format agent` clean.

### 3. UI regression — Laravel Dusk browser tests

Run `./vendor/bin/sail dusk`. Run `php artisan migrate:fresh --seed --no-interaction`
inside the test when a clean database is needed.

- [x] Service form mapping test (Task 24) — `LocationField` + map picker dialog
  render with the right Spanish copy, no error UI, screenshots captured.
- [x] GPS map + static-map test (Task 25) — `/gps/map` legend present, service
  detail shows static-map `<img>`s pointing at `maps.googleapis.com`, driver
  cards show previews, no error banners, screenshots captured.

### 4. API endpoints — curl

Not applicable — this requirement adds no API endpoints.

### 5. Build & type checks

- [x] `./vendor/bin/sail npm run build` succeeds.
- [x] `./vendor/bin/sail npm run types` passes (no `CoordinatesSource` /
  removed-import type errors).
- [x] `./vendor/bin/sail npm run lint` passes.
- [x] `grep -ri "mapbox\|leaflet"` over `app/`, `resources/js/`, `config/`,
  `tests/`, `.env.example`, `package.json` returns nothing (AC9).

## Dependencies

- **Google Cloud Platform (provided by the user — confirm before implementation):**
  - A GCP project with an active billing account.
  - Enabled APIs: **Maps JavaScript API**, **Places API (New)**,
    **Geocoding API**, **Routes API**, **Maps Static API**.
  - A **browser key** restricted by HTTP referrer — must allow the local dev
    origin (`http://localhost`) and any staging origin.
  - A **server key** restricted by IP — used by `RoutesClient`.
  - A **Map ID** (vector map) for `@vis.gl/react-google-maps` `<Map>` +
    `<AdvancedMarker>`.
  - These values populate `GOOGLE_MAPS_BROWSER_KEY`, `GOOGLE_MAPS_SERVER_KEY`,
    `GOOGLE_MAPS_MAP_ID` in `.env`.
- No dependency on other requirements.

## Notes

- **`routable_points` has no Google equivalent.** Mapbox returned a
  `routable_points.default` (where a vehicle can pull up); SGTE persisted it via
  `pickRoutableCoords`. Google Places returns a single `location` per place.
  After this migration the persisted coordinate is the place `location` directly.
  This is an accepted, minor fidelity change.
- **The Mapbox `permanent` flag is gone.** It encoded Mapbox's licensing for
  persisting geocoded coordinates. Google's terms allow storing the `place_id`
  indefinitely; persisting it (Task 1's new columns) is the durable reference.
  The autocomplete → place-details flow collapses from Mapbox's two-step
  (`permanent:false` typeahead + `permanent:true` commit) into one place-details
  call.
- **Promoting `coordinates_source` / `route_source` to a first-class PHP enum is
  out of scope.** The existing pattern (varchar + `Rule::in`) is kept to bound
  this migration. A follow-up requirement could remodel it.
- **Address Validation API is intentionally not used.** Result confidence is
  derived implicitly from the Google `location_type`, mirroring how Mapbox
  `accuracy` drove the confidence badge today.
- **Static-map signing.** Maps Static API URLs are unsigned here; for the
  pre-deployment / demo phase an API-key-restricted browser key is sufficient.
  URL signing can be revisited if request volume grows.
- **Dusk + Google Maps.** Interactive Google map rendering inside headless
  Selenium is network-dependent and flaky; Dusk assertions deliberately target
  SGTE's own chrome and the static-map `<img>` `src`, not Google's rendered
  tiles.
- Source context: `docs/bugs/2026-05-21-identified-bugs.md` (ENH-001);
  baseline behaviour audit: `docs/audits/2026-05-09-mapbox-autocomplete-baseline.md`.
