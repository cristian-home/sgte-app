import { MAPBOX_TOKEN } from '@/lib/mapbox';

/**
 * Typed helper around the Mapbox Geocoding v6 API
 * (https://api.mapbox.com/search/geocode/v6/forward and /reverse).
 *
 * Why this helper exists:
 *
 * - The `permanent` flag determines whether we have legal license to
 *   persist the resulting coordinates. To avoid silently storing data
 *   we don't have rights to, the `permanent` field is REQUIRED on
 *   every call. There is no default. Forgetting to set it is a type
 *   error.
 * - Both forward and reverse share roughly the same response shape;
 *   we type both with the same `GeocodingFeature`.
 * - `pickRoutableCoords` is the single place that decides whether to
 *   use the routable_points.default coordinate (vehicle stop) over
 *   the raw geometry — this is the SGTE-specific choice for fleet
 *   addressing.
 *
 * Free-tier reminder: forward with `permanent=false` is free up to
 * 100k requests/month. `permanent=true` has no free tier; budget for
 * one call per persisted address (~$5 / 1000 services).
 */

const ENDPOINT_FORWARD = 'https://api.mapbox.com/search/geocode/v6/forward';
const ENDPOINT_REVERSE = 'https://api.mapbox.com/search/geocode/v6/reverse';

export type GeocodingAccuracy =
    | 'rooftop'
    | 'parcel'
    | 'point'
    | 'interpolated'
    | 'approximate'
    | 'intersection';

export interface GeocodingFeature {
    type: 'Feature';
    id: string;
    geometry: {
        type: 'Point';
        coordinates: [number, number];
    };
    properties: {
        mapbox_id: string;
        feature_type: string;
        name: string;
        place_formatted?: string;
        full_address?: string;
        coordinates: {
            longitude: number;
            latitude: number;
            accuracy?: GeocodingAccuracy;
            routable_points?: Array<{
                name: 'default' | 'entrance';
                latitude: number;
                longitude: number;
            }>;
        };
        context?: {
            address?: {
                name?: string;
                address_number?: string;
                street_name?: string;
            };
            street?: { name?: string };
            postcode?: { name?: string };
            place?: { name?: string };
            district?: { name?: string };
            region?: { name?: string };
            country?: { name?: string; country_code?: string };
            neighborhood?: { name?: string };
            locality?: { name?: string };
        };
        match_code?: {
            confidence?: 'exact' | 'high' | 'medium' | 'low';
            address_number?: string;
            street?: string;
            postcode?: string;
            place?: string;
            region?: string;
            country?: string;
        };
    };
}

export interface GeocodingResponse {
    type: 'FeatureCollection';
    features: GeocodingFeature[];
    attribution?: string;
}

export interface ForwardOpts {
    /** Required: false for typeahead (cheap), true to commit a result for storage. */
    permanent: boolean;
    country?: string;
    language?: string;
    /** "lng,lat" or "ip" */
    proximity?: string;
    /** comma-separated, e.g. "address,street" */
    types?: string;
    limit?: number;
    /** When false (default behavior on autocomplete=false), the API matches exactly rather than as a prefix. */
    autocomplete?: boolean;
    /** Bounding box: "minLng,minLat,maxLng,maxLat" */
    bbox?: string;
}

export interface ReverseOpts {
    /** Required: false for hint UX, true if persisting. */
    permanent: boolean;
    country?: string;
    language?: string;
    /** comma-separated, e.g. "address,street" */
    types?: string;
    limit?: number;
}

function buildParams(
    opts: ForwardOpts | ReverseOpts,
    extra: Record<string, string>,
): string {
    const params = new URLSearchParams();
    params.set('access_token', MAPBOX_TOKEN);
    params.set('permanent', String(opts.permanent));
    if (opts.country) params.set('country', opts.country);
    if (opts.language) params.set('language', opts.language);
    if (opts.types) params.set('types', opts.types);
    if (opts.limit != null) params.set('limit', String(opts.limit));
    for (const [key, value] of Object.entries(extra)) {
        params.set(key, value);
    }
    return params.toString();
}

/**
 * Forward geocode (address text → features). Pass `permanent: true` only
 * when committing a result you are about to persist. The default
 * behavior on the API is autocomplete=true (prefix match).
 */
export async function forwardGeocode(
    query: string,
    opts: ForwardOpts,
    signal?: AbortSignal,
): Promise<GeocodingResponse> {
    const extra: Record<string, string> = { q: query };
    if (opts.proximity) extra.proximity = opts.proximity;
    if (opts.bbox) extra.bbox = opts.bbox;
    if (opts.autocomplete != null) {
        extra.autocomplete = String(opts.autocomplete);
    }
    const url = `${ENDPOINT_FORWARD}?${buildParams(opts, extra)}`;
    const res = await fetch(url, { signal });
    if (!res.ok) {
        throw new Error(`Mapbox forward geocode failed: ${res.status}`);
    }
    return (await res.json()) as GeocodingResponse;
}

/**
 * Reverse geocode (lng/lat → features). Used for the "Cerca de:" hint
 * on the manual map picker; that hint is display-only, never stored,
 * so always pass `permanent: false`.
 */
export async function reverseGeocode(
    lng: number,
    lat: number,
    opts: ReverseOpts,
    signal?: AbortSignal,
): Promise<GeocodingResponse> {
    const extra: Record<string, string> = {
        longitude: String(lng),
        latitude: String(lat),
    };
    const url = `${ENDPOINT_REVERSE}?${buildParams(opts, extra)}`;
    const res = await fetch(url, { signal });
    if (!res.ok) {
        throw new Error(`Mapbox reverse geocode failed: ${res.status}`);
    }
    return (await res.json()) as GeocodingResponse;
}

/**
 * Pick the best coordinate to persist for fleet routing purposes:
 * the `routable_points.default` (where a vehicle can pull up) when
 * Mapbox provides it, otherwise the feature geometry. Returns
 * `{ lat, lng }` rounded to 7 decimal places of precision.
 */
export function pickRoutableCoords(feature: GeocodingFeature): {
    lat: number;
    lng: number;
} {
    const routable = feature.properties.coordinates.routable_points?.find(
        (p) => p.name === 'default',
    );
    if (routable) {
        return {
            lat: round7(routable.latitude),
            lng: round7(routable.longitude),
        };
    }
    const [lng, lat] = feature.geometry.coordinates;
    return { lat: round7(lat), lng: round7(lng) };
}

function round7(value: number): number {
    return Math.round(value * 1e7) / 1e7;
}

/**
 * Find a feature by mapbox_id within a response. Used by the
 * permanent-commit flow: the typeahead returns suggestion S; we
 * re-issue with permanent=true; we match the feature back by id to
 * be sure we're persisting the exact thing the user picked.
 */
export function findFeatureByMapboxId(
    response: GeocodingResponse,
    mapboxId: string,
): GeocodingFeature | null {
    return (
        response.features.find((f) => f.properties.mapbox_id === mapboxId) ??
        null
    );
}
