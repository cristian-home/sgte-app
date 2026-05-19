import { Head, Link, router } from '@inertiajs/react';
import L from 'leaflet';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import { MapPin } from 'lucide-react';
import { Fragment, useEffect, useMemo } from 'react';
import {
    CircleMarker,
    MapContainer,
    Marker,
    Polyline,
    Popup,
    TileLayer,
    useMap,
} from 'react-leaflet';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { formatTimestampInViewerTz } from '@/lib/datetime';
import { MAPBOX_ATTRIBUTION, mapboxTileUrl } from '@/lib/mapbox';

import type { BreadcrumbItem } from '@/types';

// Vite quirk: Leaflet's default icons break because its default URLs are
// resolved relative to the CSS file. Rebind to the imported asset URLs.
L.Marker.prototype.options.icon = L.icon({
    iconRetinaUrl,
    iconUrl,
    shadowUrl: iconShadow,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'GPS', href: '#' },
    { title: 'Mapa', href: '/gps/map' },
];

const MEDELLIN_CENTER: [number, number] = [6.2518, -75.5636];
const MEDELLIN_ZOOM = 11;
const REFRESH_INTERVAL_MS = 300_000;

interface CoordPair {
    latitude: number;
    longitude: number;
}

interface ActiveService {
    service_id: number;
    vehicle_plate: string | null;
    driver_name: string | null;
    location: {
        latitude: number;
        longitude: number;
        accuracy: number | null;
        is_manual: boolean;
        recorded_at: string | null;
    } | null;
    origin: CoordPair | null;
    destination: CoordPair | null;
    route: CoordPair[] | null;
    route_distance_m: number | null;
    route_duration_s: number | null;
}

// Stable per-service color via golden-angle hue stepping. Service IDs
// that are close numerically still end up far apart visually so two
// adjacent rows don't get near-identical lines on the map.
function serviceColor(id: number): string {
    const hue = (id * 137.508) % 360;
    return `hsl(${hue.toFixed(0)}, 70%, 42%)`;
}

function toLatLng(p: CoordPair): [number, number] {
    return [p.latitude, p.longitude];
}

function formatDistance(m: number | null): string | null {
    if (m === null || m === undefined) return null;
    if (m < 1000) return `${m} m`;
    return `${(m / 1000).toFixed(1)} km`;
}

function formatDuration(s: number | null): string | null {
    if (s === null || s === undefined) return null;
    const minutes = Math.round(s / 60);
    if (minutes < 60) return `${minutes} min`;
    const h = Math.floor(minutes / 60);
    const rem = minutes % 60;
    return rem === 0 ? `${h} h` : `${h} h ${rem} min`;
}

function MapLegend() {
    // Use a neutral foreground color for the example glyphs so the
    // legend doesn't pretend to belong to any particular service —
    // each real service uses its own HSL hue.
    return (
        <div
            className="pointer-events-none absolute bottom-2 left-2 w-48 rounded-md border bg-card/95 p-3 text-xs shadow-md backdrop-blur"
            // Tailwind v4 doesn't emit arbitrary `z-[1000]` rules. Leaflet
            // panes sit at z-index 200-700 internally, so the legend
            // needs to stack above them via inline style.
            style={{ zIndex: 1000 }}
        >
            <div className="mb-2 font-medium">Símbolos</div>
            <ul className="space-y-1.5 text-muted-foreground">
                <li className="flex items-center gap-2">
                    <span className="inline-block size-3 rounded-full bg-foreground" />
                    <span>Origen</span>
                </li>
                <li className="flex items-center gap-2">
                    <span className="inline-block size-3 rounded-full border-2 border-foreground bg-background" />
                    <span>Destino</span>
                </li>
                <li className="flex items-center gap-2">
                    <MapPin className="size-3.5 fill-blue-500 text-blue-500" />
                    <span>Vehículo (GPS)</span>
                </li>
                <li className="flex items-center gap-2">
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 4"
                        className="h-1 w-4 text-foreground"
                    >
                        <line
                            x1="0"
                            y1="2"
                            x2="24"
                            y2="2"
                            stroke="currentColor"
                            strokeWidth="3"
                            strokeLinecap="round"
                        />
                    </svg>
                    <span>Ruta confirmada</span>
                </li>
                <li className="flex items-center gap-2">
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 4"
                        className="h-1 w-4 text-foreground"
                    >
                        <line
                            x1="0"
                            y1="2"
                            x2="24"
                            y2="2"
                            stroke="currentColor"
                            strokeWidth="3"
                            strokeLinecap="round"
                            strokeDasharray="4 4"
                        />
                    </svg>
                    <span>Ruta estimada</span>
                </li>
            </ul>
        </div>
    );
}

function FitBoundsOnData({ services }: { services: ActiveService[] }) {
    const map = useMap();

    useEffect(() => {
        const points: L.LatLng[] = [];

        for (const s of services) {
            if (s.location) {
                points.push(
                    L.latLng(s.location.latitude, s.location.longitude),
                );
            }
            if (s.origin) {
                points.push(L.latLng(s.origin.latitude, s.origin.longitude));
            }
            if (s.destination) {
                points.push(
                    L.latLng(s.destination.latitude, s.destination.longitude),
                );
            }
            if (s.route) {
                for (const p of s.route) {
                    points.push(L.latLng(p.latitude, p.longitude));
                }
            }
        }

        if (points.length > 0) {
            map.fitBounds(L.latLngBounds(points).pad(0.15));
        }
    }, [map, services]);

    return null;
}

export default function GpsMap({
    activeServices,
}: {
    activeServices: ActiveService[];
}) {
    useEffect(() => {
        // Skip the auto-refresh when the tab is hidden — Inertia v2
        // triggers View Transitions on successful responses, and the
        // browser throws InvalidStateError when the document isn't
        // visible. The next refresh after the tab is refocused picks
        // up whatever changed while we were away.
        const interval = setInterval(() => {
            if (typeof document !== 'undefined' && document.hidden) {
                return;
            }
            router.reload({ only: ['activeServices'] });
        }, REFRESH_INTERVAL_MS);
        return () => clearInterval(interval);
    }, []);

    const markerServices = useMemo(
        () =>
            activeServices.filter(
                (
                    s,
                ): s is ActiveService & {
                    location: NonNullable<ActiveService['location']>;
                } => s.location !== null,
            ),
        [activeServices],
    );

    const routableServices = useMemo(
        () =>
            activeServices.filter(
                (
                    s,
                ): s is ActiveService & {
                    origin: NonNullable<ActiveService['origin']>;
                    destination: NonNullable<ActiveService['destination']>;
                } => s.origin !== null && s.destination !== null,
            ),
        [activeServices],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mapa GPS" />
            <div
                className="flex flex-1 flex-col gap-2 rounded-xl p-4"
                // Tailwind 4.3.0 silently drops `h-[calc(100vh-Xrem)]` for
                // some values (notably 6rem), so go inline. 5rem matches
                // the actual chrome: header h-16 (64px) + main m-2 (16px).
                style={{ height: 'calc(100vh - 5rem)' }}
            >
                <div className="text-xs text-muted-foreground">
                    {markerServices.length} de {activeServices.length} servicios
                    activos con ubicación conocida. Actualización automática
                    cada {REFRESH_INTERVAL_MS / 60_000} min.
                </div>
                <div className="relative flex-1 overflow-hidden rounded-md border">
                    <MapLegend />
                    <MapContainer
                        center={MEDELLIN_CENTER}
                        zoom={MEDELLIN_ZOOM}
                        style={{ height: '100%', width: '100%' }}
                    >
                        <TileLayer
                            url={mapboxTileUrl()}
                            attribution={MAPBOX_ATTRIBUTION}
                            tileSize={512}
                            zoomOffset={-1}
                        />
                        <FitBoundsOnData services={activeServices} />

                        {routableServices.map((service) => {
                            const color = serviceColor(service.service_id);
                            const hasRealRoute =
                                service.route !== null &&
                                service.route.length >= 2;
                            const polylinePositions: [number, number][] =
                                hasRealRoute
                                    ? service.route!.map(toLatLng)
                                    : [
                                          toLatLng(service.origin),
                                          toLatLng(service.destination),
                                      ];

                            return (
                                <Fragment key={`route-${service.service_id}`}>
                                    <Polyline
                                        positions={polylinePositions}
                                        pathOptions={{
                                            color,
                                            weight: 4,
                                            opacity: 0.75,
                                            dashArray: hasRealRoute
                                                ? undefined
                                                : '6 8',
                                        }}
                                    />
                                    <CircleMarker
                                        center={toLatLng(service.origin)}
                                        radius={6}
                                        pathOptions={{
                                            color,
                                            fillColor: color,
                                            fillOpacity: 1,
                                            weight: 2,
                                        }}
                                    />
                                    <CircleMarker
                                        center={toLatLng(service.destination)}
                                        radius={6}
                                        pathOptions={{
                                            color,
                                            fillColor: '#ffffff',
                                            fillOpacity: 1,
                                            weight: 2,
                                        }}
                                    />
                                </Fragment>
                            );
                        })}

                        {markerServices.map((service) => {
                            const distance = formatDistance(
                                service.route_distance_m,
                            );
                            const duration = formatDuration(
                                service.route_duration_s,
                            );

                            return (
                                <Marker
                                    key={service.service_id}
                                    position={[
                                        service.location.latitude,
                                        service.location.longitude,
                                    ]}
                                >
                                    <Popup>
                                        <div className="space-y-1 text-sm">
                                            <div className="font-mono font-medium">
                                                {service.vehicle_plate ?? '—'}
                                            </div>
                                            <div>
                                                {service.driver_name ?? '—'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {formatTimestampInViewerTz(
                                                    service.location
                                                        .recorded_at,
                                                ) || '—'}
                                            </div>
                                            <div>
                                                {service.location.is_manual ? (
                                                    <Badge variant="outline">
                                                        Manual
                                                    </Badge>
                                                ) : (
                                                    <Badge>GPS</Badge>
                                                )}
                                            </div>
                                            {(distance || duration) && (
                                                <div className="text-xs text-muted-foreground">
                                                    Ruta:{' '}
                                                    {[distance, duration]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </div>
                                            )}
                                            <Link
                                                href={`/services/${service.service_id}`}
                                                className="text-primary hover:underline"
                                            >
                                                Ver servicio #
                                                {service.service_id}
                                            </Link>
                                        </div>
                                    </Popup>
                                </Marker>
                            );
                        })}
                    </MapContainer>
                </div>
            </div>
        </AppLayout>
    );
}
