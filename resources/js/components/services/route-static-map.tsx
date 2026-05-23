import { MapPin } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';
import { staticMapUrl, staticRouteMapUrl } from '@/lib/google-maps';
import { cn } from '@/lib/utils';

interface RouteStaticMapProps {
    /** "lat,lng" string, or null when the origin is unknown. */
    origin: string | null;
    /** "lat,lng" string, or null when the destination is unknown. */
    destination: string | null;
    /**
     * Cached route as a GeoJSON LineString — array of [lng, lat] pairs
     * (matches `Service.route_geometry`). When absent, the static image
     * falls back to a straight line between the two markers.
     */
    geometry?: number[][] | null;
    className?: string;
    width?: number;
    height?: number;
}

function parseCoordinates(
    value: string | null,
): { lat: number; lng: number } | null {
    if (!value) {
        return null;
    }
    const match = /^(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)$/.exec(value.trim());
    if (!match) {
        return null;
    }
    const lat = Number(match[1]);
    const lng = Number(match[2]);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return null;
    }
    return { lat, lng };
}

/**
 * Google Maps Static API preview that frames an entire trip: A/B
 * markers at origin and destination plus the polyline between them.
 * Renders a neutral placeholder when either coordinate is missing.
 */
export default function RouteStaticMap({
    origin,
    destination,
    geometry,
    className,
    width = 600,
    height = 300,
}: RouteStaticMapProps) {
    const { resolvedAppearance } = useAppearance();
    const parsedOrigin = parseCoordinates(origin);
    const parsedDestination = parseCoordinates(destination);

    // Both sides unknown → keep the neutral placeholder so the layout
    // doesn't shift.
    if (!parsedOrigin && !parsedDestination) {
        return (
            <div
                className={cn(
                    'flex w-full flex-col items-center justify-center gap-1 rounded-md border border-dashed bg-muted/40 text-muted-foreground',
                    className,
                )}
                style={{ height }}
            >
                <MapPin className="size-5" />
                <span className="text-xs">Ruta no disponible</span>
            </div>
        );
    }

    // One side known → drop a single marker on a centered static map.
    // Better than the empty placeholder when the operator has at least
    // anchored one end of the trip.
    if (!parsedOrigin || !parsedDestination) {
        const point = parsedOrigin ?? parsedDestination!;
        const altLabel = parsedOrigin
            ? 'Mapa con el origen del servicio'
            : 'Mapa con el destino del servicio';
        return (
            <img
                src={staticMapUrl({
                    lat: point.lat,
                    lng: point.lng,
                    width,
                    height,
                    zoom: 13,
                })}
                alt={altLabel}
                width={width}
                height={height}
                loading="lazy"
                className={cn(
                    'h-auto w-full rounded-md border object-cover',
                    className,
                )}
            />
        );
    }

    return (
        <img
            src={staticRouteMapUrl({
                origin: parsedOrigin,
                destination: parsedDestination,
                geometry,
                theme: resolvedAppearance === 'dark' ? 'dark' : 'light',
                width,
                height,
            })}
            alt="Mapa de la ruta entre el origen y el destino"
            width={width}
            height={height}
            loading="lazy"
            className={cn(
                'h-auto w-full rounded-md border object-cover',
                className,
            )}
        />
    );
}
