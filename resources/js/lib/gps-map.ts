/// <reference types="google.maps" />

import type { CoordPair } from '@/types/gps-map';

/**
 * Stable per-service color via golden-angle hue stepping. Service IDs
 * that are close numerically still end up far apart visually so two
 * adjacent rows don't get near-identical lines on the map.
 */
export function serviceColor(id: number): string {
    const hue = (id * 137.508) % 360;
    return `hsl(${hue.toFixed(0)}, 70%, 42%)`;
}

/**
 * Convert a `{latitude, longitude}` pair (the backend shape) to Google's
 * `{lat, lng}` literal.
 */
export function toLatLng(p: CoordPair): google.maps.LatLngLiteral {
    return { lat: p.latitude, lng: p.longitude };
}

export function formatDistance(m: number | null): string | null {
    if (m === null || m === undefined) return null;
    if (m < 1000) return `${m} m`;
    return `${(m / 1000).toFixed(1)} km`;
}

export function formatDuration(s: number | null): string | null {
    if (s === null || s === undefined) return null;
    const minutes = Math.round(s / 60);
    if (minutes < 60) return `${minutes} min`;
    const h = Math.floor(minutes / 60);
    const rem = minutes % 60;
    return rem === 0 ? `${h} h` : `${h} h ${rem} min`;
}
