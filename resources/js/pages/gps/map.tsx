import { Head, Link, router } from '@inertiajs/react';
import {
    APIProvider,
    AdvancedMarker,
    Map as GoogleMap,
    InfoWindow,
    Pin,
    useAdvancedMarkerRef,
    useMap,
} from '@vis.gl/react-google-maps';
import { MapPin } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { formatTimestampInViewerTz } from '@/lib/datetime';
import {
    GOOGLE_MAPS_BROWSER_KEY,
    GOOGLE_MAPS_MAP_ID,
    MEDELLIN_CENTER,
    MEDELLIN_ZOOM,
} from '@/lib/google-maps';

import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'GPS', href: '#' },
    { title: 'Mapa', href: '/gps/map' },
];

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

function toLatLng(p: CoordPair): google.maps.LatLngLiteral {
    return { lat: p.latitude, lng: p.longitude };
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
            // Google Maps panes stack internally; keep the legend above
            // them via an explicit z-index (Tailwind v4 doesn't emit an
            // arbitrary `z-[1000]` rule).
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

interface RouteData {
    service_id: number;
    color: string;
    path: google.maps.LatLngLiteral[];
    origin: google.maps.LatLngLiteral;
    destination: google.maps.LatLngLiteral;
    /** True when a real fetched route is drawn (solid), false for the estimated straight line (dashed). */
    confirmed: boolean;
}

interface MarkerService {
    service_id: number;
    vehicle_plate: string | null;
    driver_name: string | null;
    position: google.maps.LatLngLiteral;
    is_manual: boolean;
    recorded_at: string | null;
    route_distance_m: number | null;
    route_duration_s: number | null;
}

/**
 * Imperatively fits the map viewport to every plotted point — runs
 * whenever the dataset changes.
 */
function FitBounds({ services }: { services: ActiveService[] }) {
    const map = useMap();

    useEffect(() => {
        if (!map) return;
        const bounds = new google.maps.LatLngBounds();
        let count = 0;

        for (const s of services) {
            if (s.location) {
                bounds.extend({
                    lat: s.location.latitude,
                    lng: s.location.longitude,
                });
                count++;
            }
            if (s.origin) {
                bounds.extend(toLatLng(s.origin));
                count++;
            }
            if (s.destination) {
                bounds.extend(toLatLng(s.destination));
                count++;
            }
            if (s.route) {
                for (const p of s.route) {
                    bounds.extend(toLatLng(p));
                    count++;
                }
            }
        }

        if (count > 0) {
            map.fitBounds(bounds, 48);
        }
    }, [map, services]);

    return null;
}

/**
 * Draws a route as a native google.maps.Polyline — the library ships no
 * declarative `<Polyline>`. Confirmed routes render solid; estimated
 * straight-line fallbacks render dashed (stroke opacity 0 + dash icons).
 */
function RoutePolyline({
    path,
    color,
    confirmed,
}: {
    path: google.maps.LatLngLiteral[];
    color: string;
    confirmed: boolean;
}) {
    const map = useMap();

    useEffect(() => {
        if (!map) return;

        const polyline = new google.maps.Polyline({
            map,
            path,
            strokeColor: color,
            strokeOpacity: confirmed ? 0.75 : 0,
            strokeWeight: 4,
            icons: confirmed
                ? undefined
                : [
                      {
                          icon: {
                              path: 'M 0,-1 0,1',
                              strokeOpacity: 0.75,
                              strokeWeight: 4,
                              scale: 3,
                          },
                          offset: '0',
                          repeat: '14px',
                      },
                  ],
        });

        return () => {
            polyline.setMap(null);
        };
    }, [map, path, color, confirmed]);

    return null;
}

/**
 * A vehicle GPS marker with a click-toggled InfoWindow carrying the
 * plate, driver, timestamp, source badge, route summary, and a link to
 * the service detail page.
 */
function VehicleMarker({ service }: { service: MarkerService }) {
    const [markerRef, marker] = useAdvancedMarkerRef();
    const [open, setOpen] = useState(false);

    const distance = formatDistance(service.route_distance_m);
    const duration = formatDuration(service.route_duration_s);

    return (
        <>
            <AdvancedMarker
                ref={markerRef}
                position={service.position}
                onClick={() => setOpen((v) => !v)}
                title={
                    service.vehicle_plate ?? `Servicio ${service.service_id}`
                }
            >
                <Pin
                    background="#2563eb"
                    borderColor="#1e40af"
                    glyphColor="#ffffff"
                />
            </AdvancedMarker>
            {open && (
                <InfoWindow anchor={marker} onCloseClick={() => setOpen(false)}>
                    <div className="space-y-1 text-sm">
                        <div className="font-mono font-medium">
                            {service.vehicle_plate ?? '—'}
                        </div>
                        <div>{service.driver_name ?? '—'}</div>
                        <div className="text-xs text-muted-foreground">
                            {formatTimestampInViewerTz(service.recorded_at) ||
                                '—'}
                        </div>
                        <div>
                            {service.is_manual ? (
                                <Badge variant="outline">Manual</Badge>
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
                            Ver servicio #{service.service_id}
                        </Link>
                    </div>
                </InfoWindow>
            )}
        </>
    );
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

    const markerServices = useMemo<MarkerService[]>(
        () =>
            activeServices
                .filter((s) => s.location !== null)
                .map((s) => ({
                    service_id: s.service_id,
                    vehicle_plate: s.vehicle_plate,
                    driver_name: s.driver_name,
                    position: {
                        lat: s.location!.latitude,
                        lng: s.location!.longitude,
                    },
                    is_manual: s.location!.is_manual,
                    recorded_at: s.location!.recorded_at,
                    route_distance_m: s.route_distance_m,
                    route_duration_s: s.route_duration_s,
                })),
        [activeServices],
    );

    const routes = useMemo<RouteData[]>(
        () =>
            activeServices
                .filter((s) => s.origin !== null && s.destination !== null)
                .map((s) => {
                    const hasRealRoute =
                        s.route !== null && s.route.length >= 2;
                    return {
                        service_id: s.service_id,
                        color: serviceColor(s.service_id),
                        origin: toLatLng(s.origin!),
                        destination: toLatLng(s.destination!),
                        confirmed: hasRealRoute,
                        path: hasRealRoute
                            ? s.route!.map(toLatLng)
                            : [toLatLng(s.origin!), toLatLng(s.destination!)],
                    };
                }),
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
                    <APIProvider apiKey={GOOGLE_MAPS_BROWSER_KEY}>
                        <GoogleMap
                            mapId={GOOGLE_MAPS_MAP_ID}
                            defaultCenter={MEDELLIN_CENTER}
                            defaultZoom={MEDELLIN_ZOOM}
                            gestureHandling="greedy"
                            clickableIcons={false}
                            className="h-full w-full"
                        >
                            <FitBounds services={activeServices} />

                            {routes.map((route) => (
                                <RoutePolyline
                                    key={`route-${route.service_id}`}
                                    path={route.path}
                                    color={route.color}
                                    confirmed={route.confirmed}
                                />
                            ))}

                            {routes.map((route) => (
                                <AdvancedMarker
                                    key={`origin-${route.service_id}`}
                                    position={route.origin}
                                >
                                    <span
                                        className="block size-3.5 rounded-full"
                                        style={{
                                            background: route.color,
                                            border: `2px solid ${route.color}`,
                                        }}
                                    />
                                </AdvancedMarker>
                            ))}

                            {routes.map((route) => (
                                <AdvancedMarker
                                    key={`destination-${route.service_id}`}
                                    position={route.destination}
                                >
                                    <span
                                        className="block size-3.5 rounded-full bg-white"
                                        style={{
                                            border: `2px solid ${route.color}`,
                                        }}
                                    />
                                </AdvancedMarker>
                            ))}

                            {markerServices.map((service) => (
                                <VehicleMarker
                                    key={service.service_id}
                                    service={service}
                                />
                            ))}
                        </GoogleMap>
                    </APIProvider>
                </div>
            </div>
        </AppLayout>
    );
}
