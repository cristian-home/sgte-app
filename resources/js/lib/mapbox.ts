/**
 * Mapbox client-side configuration.
 *
 * The token is injected at build-time via the `VITE_MAPBOX_TOKEN` env
 * variable, which is itself interpolated from `MAPBOX_TOKEN` in `.env`
 * (see `.env.example`). Same source of truth that backend reads via
 * `config('services.mapbox.token')`.
 *
 * Scope: public token (pk....). Should be URL-restricted in
 * account.mapbox.com so a leak doesn't translate into account abuse.
 */
export const MAPBOX_TOKEN: string =
    (import.meta.env.VITE_MAPBOX_TOKEN as string | undefined) ?? '';

if (import.meta.env.PROD && !MAPBOX_TOKEN) {
    console.warn(
        'VITE_MAPBOX_TOKEN is empty — Address Autofill and Mapbox tiles will fail.',
    );
}

/**
 * Default Mapbox style ID used by the GPS map view. `streets-v12` is a
 * good general-purpose street view; alternatives include `light-v11`,
 * `dark-v11`, `outdoors-v12`, `satellite-streets-v12`.
 */
export const MAPBOX_DEFAULT_STYLE = 'mapbox/streets-v12';

/**
 * Build a `<TileLayer url={...} />` URL for react-leaflet pointing at a
 * Mapbox raster style. Use `tileSize={512} zoomOffset={-1}` on the
 * `<TileLayer>` to match Mapbox's tile sizing.
 */
export function mapboxTileUrl(style: string = MAPBOX_DEFAULT_STYLE): string {
    return `https://api.mapbox.com/styles/v1/${style}/tiles/{z}/{x}/{y}?access_token=${MAPBOX_TOKEN}`;
}

/**
 * Mapbox + OpenStreetMap attribution string required by Mapbox ToS for
 * any map that uses Mapbox tiles. Mapbox-rendered tilesets bundle OSM
 * data, so OSM credit is also required. Pass to react-leaflet's
 * `<TileLayer attribution={...}>` prop.
 */
export const MAPBOX_ATTRIBUTION =
    '&copy; <a href="https://www.mapbox.com/about/maps/" target="_blank" rel="noopener noreferrer">Mapbox</a> ' +
    '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> ' +
    '<strong><a href="https://www.mapbox.com/map-feedback/" target="_blank" rel="noopener noreferrer">Improve this map</a></strong>';
