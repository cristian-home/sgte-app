import { Link } from '@inertiajs/react';
import {
    AdvancedMarker,
    InfoWindow,
    Pin,
    useAdvancedMarkerRef,
} from '@vis.gl/react-google-maps';
import { X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatTimestampInViewerTz } from '@/lib/datetime';
import { formatDistance, formatDuration } from '@/lib/gps-map';
import type { MarkerService } from '@/types/gps-map';

/**
 * A vehicle GPS marker with an InfoWindow carrying the plate, driver,
 * timestamp, source badge, route summary, and a link to the service
 * detail page. The open state is lifted to the page so the services
 * panel and the marker stay in sync both ways.
 *
 * Google's default InfoWindow header is disabled (`headerDisabled`) so
 * the content sits flush at the top; the close "X" is our own, tucked
 * into the upper-right corner so it adapts to light/dark via theme
 * tokens like every other element on the bubble.
 */
export function VehicleMarker({
    service,
    open,
    onOpenChange,
}: {
    service: MarkerService;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [markerRef, marker] = useAdvancedMarkerRef();

    const distance = formatDistance(service.route_distance_m);
    const duration = formatDuration(service.route_duration_s);

    return (
        <>
            <AdvancedMarker
                ref={markerRef}
                position={service.position}
                onClick={() => onOpenChange(!open)}
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
                <InfoWindow
                    className="flex flex-col rounded-md bg-card p-0"
                    anchor={marker}
                    headerDisabled
                    onCloseClick={() => onOpenChange(false)}
                >
                    <header className="rounded-tl-md rounded-tr-md bg-primary/10 px-3 py-1.5 text-sm font-medium flex items-center justify-between gap-2">
                        <span>{service.vehicle_plate ?? 'Servicio sin placa'}</span>
                        <button
                            type="button"
                            onClick={() => onOpenChange(false)}
                            aria-label="Cerrar"
                            className="-mr-1 p-0.5 cursor-pointer rounded-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <X className="size-4" />
                        </button>
                    </header>
                    <div className="relative text-sm px-3 py-2 flex flex-col gap-1">
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
