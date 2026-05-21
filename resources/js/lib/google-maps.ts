/**
 * Google Maps Platform client-side configuration.
 *
 * The browser key is injected at build-time via the
 * `VITE_GOOGLE_MAPS_BROWSER_KEY` env variable, which is itself
 * interpolated from `GOOGLE_MAPS_BROWSER_KEY` in `.env` (see
 * `.env.example`). Same source of truth the backend reads via
 * `config('services.google_maps.browser_key')`. `GOOGLE_MAPS_MAP_ID`
 * identifies the vector map required by `<Map>` + `<AdvancedMarker>`.
 *
 * Scope: the browser key is HTTP-referrer restricted in the Google Cloud
 * console so a leak in the JS bundle doesn't translate into account
 * abuse. The Map ID is not a secret.
 */
export const GOOGLE_MAPS_BROWSER_KEY: string =
    (import.meta.env.VITE_GOOGLE_MAPS_BROWSER_KEY as string | undefined) ?? '';

export const GOOGLE_MAPS_MAP_ID: string =
    (import.meta.env.VITE_GOOGLE_MAPS_MAP_ID as string | undefined) ?? '';

if (import.meta.env.PROD && !GOOGLE_MAPS_BROWSER_KEY) {
    console.warn(
        'VITE_GOOGLE_MAPS_BROWSER_KEY is empty — maps, address autocomplete and static-map previews will fail.',
    );
}

/** Default map center: Medellín — matches the historical /gps/map view. */
export const MEDELLIN_CENTER: { lat: number; lng: number } = {
    lat: 6.2518,
    lng: -75.5636,
};
export const MEDELLIN_ZOOM = 11;

/**
 * Fallback center for the manual map picker when no municipality is
 * selected (country-level Bogotá view).
 */
export const BOGOTA_FALLBACK: { lat: number; lng: number } = {
    lat: 4.711,
    lng: -74.0721,
};

interface StaticMapOptions {
    lat: number;
    lng: number;
    zoom?: number;
    width?: number;
    height?: number;
    scale?: number;
}

/**
 * Build a Maps Static API URL with a single red marker pinned at the
 * coordinate. `scale=2` requests a 2x raster so the preview stays crisp
 * on retina/HiDPI displays.
 */
export function staticMapUrl({
    lat,
    lng,
    zoom = 15,
    width = 300,
    height = 160,
    scale = 2,
}: StaticMapOptions): string {
    const params = new URLSearchParams({
        center: `${lat},${lng}`,
        zoom: String(zoom),
        size: `${width}x${height}`,
        scale: String(scale),
        markers: `color:red|${lat},${lng}`,
        key: GOOGLE_MAPS_BROWSER_KEY,
    });
    return `https://maps.googleapis.com/maps/api/staticmap?${params.toString()}`;
}
