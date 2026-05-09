import { Head, Link, router } from '@inertiajs/react';
import L from 'leaflet';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import { useEffect, useMemo } from 'react';
import { MapContainer, Marker, Popup, TileLayer, useMap } from 'react-leaflet';
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
const REFRESH_INTERVAL_MS = 30_000;

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
}

function FitBoundsOnData({ services }: { services: ActiveService[] }) {
    const map = useMap();

    useEffect(() => {
        const coords = services
            .map((s) => s.location)
            .filter(
                (l): l is NonNullable<ActiveService['location']> => l !== null,
            )
            .map((l) => L.latLng(l.latitude, l.longitude));

        if (coords.length > 0) {
            map.fitBounds(L.latLngBounds(coords).pad(0.15));
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mapa GPS" />
            <div className="flex h-[calc(100vh-6rem)] flex-1 flex-col gap-2 rounded-xl p-4">
                <div className="text-xs text-muted-foreground">
                    {markerServices.length} de {activeServices.length} servicios
                    activos con ubicación conocida. Actualización automática
                    cada {REFRESH_INTERVAL_MS / 1000}s.
                </div>
                <div className="flex-1 overflow-hidden rounded-md border">
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
                        <FitBoundsOnData services={markerServices} />
                        {markerServices.map((service) => (
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
                                        <div>{service.driver_name ?? '—'}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {formatTimestampInViewerTz(
                                                service.location.recorded_at,
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
                                        <Link
                                            href={`/services/${service.service_id}`}
                                            className="text-primary hover:underline"
                                        >
                                            Ver servicio #{service.service_id}
                                        </Link>
                                    </div>
                                </Popup>
                            </Marker>
                        ))}
                    </MapContainer>
                </div>
            </div>
        </AppLayout>
    );
}
