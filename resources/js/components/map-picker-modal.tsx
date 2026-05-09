import L from 'leaflet';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import { Loader2, MapPin } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    MapContainer,
    Marker,
    TileLayer,
    useMap,
    useMapEvents,
} from 'react-leaflet';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { MAPBOX_ATTRIBUTION, mapboxTileUrl } from '@/lib/mapbox';
import { reverseGeocode } from '@/lib/mapbox-geocoding';
import { cn } from '@/lib/utils';

// Vite quirk: rebind Leaflet's default icons to the Vite-resolved URLs.
// Same trick used by /gps/map.
L.Marker.prototype.options.icon = L.icon({
    iconRetinaUrl,
    iconUrl,
    shadowUrl: iconShadow,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
});

const BOGOTA_FALLBACK: { lat: number; lng: number } = {
    lat: 4.711,
    lng: -74.0721,
};
const REVERSE_DEBOUNCE_MS = 600;

type LatLng = { lat: number; lng: number };

interface MapPickerModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    initialCenter: LatLng | null;
    initialPin: LatLng | null;
    addressHint: string;
    municipalityHint: string | null;
    onConfirm: (coords: LatLng) => void;
}

/**
 * Modal wrapper. The body is rendered only while `open` is true so that
 * each opening remounts the inner component — pin / hint state are
 * initialized at mount time from props rather than reset via an effect,
 * which keeps React Compiler happy and avoids cascading rerenders.
 */
export default function MapPickerModal({
    open,
    onOpenChange,
    initialCenter,
    initialPin,
    addressHint,
    municipalityHint,
    onConfirm,
}: MapPickerModalProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={cn(
                    'sm:max-w-3xl',
                    'flex flex-col gap-3 p-0',
                    'h-[90vh] sm:h-[80vh]',
                )}
            >
                {open ? (
                    <MapPickerBody
                        initialCenter={initialCenter}
                        initialPin={initialPin}
                        addressHint={addressHint}
                        municipalityHint={municipalityHint}
                        onCancel={() => onOpenChange(false)}
                        onConfirm={onConfirm}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

interface BodyProps {
    initialCenter: LatLng | null;
    initialPin: LatLng | null;
    addressHint: string;
    municipalityHint: string | null;
    onCancel: () => void;
    onConfirm: (coords: LatLng) => void;
}

function MapPickerBody({
    initialCenter,
    initialPin,
    addressHint,
    municipalityHint,
    onCancel,
    onConfirm,
}: BodyProps) {
    const [pin, setPin] = useState<LatLng | null>(initialPin);
    const [hint, setHint] = useState<string>('—');
    const [hintLoading, setHintLoading] = useState(false);

    const reverseAbortRef = useRef<AbortController | null>(null);
    const reverseTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const center = initialPin ?? initialCenter ?? BOGOTA_FALLBACK;
    const initialZoom = initialPin ? 16 : initialCenter ? 14 : 11;

    useEffect(() => {
        return () => {
            if (reverseAbortRef.current) {
                reverseAbortRef.current.abort();
                reverseAbortRef.current = null;
            }
            if (reverseTimerRef.current) {
                clearTimeout(reverseTimerRef.current);
                reverseTimerRef.current = null;
            }
        };
    }, []);

    const scheduleReverse = (next: LatLng) => {
        if (reverseTimerRef.current) {
            clearTimeout(reverseTimerRef.current);
        }
        if (reverseAbortRef.current) {
            reverseAbortRef.current.abort();
            reverseAbortRef.current = null;
        }
        setHintLoading(true);
        reverseTimerRef.current = setTimeout(() => {
            const ac = new AbortController();
            reverseAbortRef.current = ac;
            reverseGeocode(
                next.lng,
                next.lat,
                {
                    permanent: false,
                    country: 'co',
                    language: 'es',
                    types: 'address,street',
                    limit: 1,
                },
                ac.signal,
            )
                .then((res) => {
                    if (ac.signal.aborted) return;
                    const f = res.features?.[0];
                    const text =
                        f?.properties.full_address ??
                        f?.properties.place_formatted ??
                        f?.properties.name ??
                        '—';
                    setHint(text);
                    setHintLoading(false);
                })
                .catch((err) => {
                    if ((err as { name?: string }).name === 'AbortError')
                        return;
                    setHint('—');
                    setHintLoading(false);
                });
        }, REVERSE_DEBOUNCE_MS);
    };

    const handlePinChange = (next: LatLng) => {
        setPin(next);
        scheduleReverse(next);
    };

    const confirmDisabled = pin === null;

    return (
        <>
            <DialogHeader className="px-6 pt-6">
                <DialogTitle>Marcar ubicación en el mapa</DialogTitle>
                <DialogDescription className="space-y-0.5">
                    <span className="block">
                        <strong>Dirección:</strong>{' '}
                        {addressHint || '(sin dirección escrita)'}
                    </span>
                    {municipalityHint && (
                        <span className="block">
                            <strong>Municipio:</strong> {municipalityHint}
                        </span>
                    )}
                </DialogDescription>
            </DialogHeader>

            <div className="relative flex-1 px-6">
                <div className="h-full overflow-hidden rounded-md border">
                    <MapContainer
                        center={[center.lat, center.lng]}
                        zoom={initialZoom}
                        style={{ height: '100%', width: '100%' }}
                    >
                        <TileLayer
                            url={mapboxTileUrl()}
                            attribution={MAPBOX_ATTRIBUTION}
                            tileSize={512}
                            zoomOffset={-1}
                        />
                        <ClickToDropPin onPinChange={handlePinChange} />
                        {pin && (
                            <Marker
                                position={[pin.lat, pin.lng]}
                                draggable
                                eventHandlers={{
                                    dragend: (e) => {
                                        const m = e.target as L.Marker;
                                        const ll = m.getLatLng();
                                        handlePinChange({
                                            lat: ll.lat,
                                            lng: ll.lng,
                                        });
                                    },
                                }}
                            />
                        )}
                        {pin && <RecenterMap target={pin} />}
                    </MapContainer>
                </div>
            </div>

            <div className="space-y-1 px-6 text-xs">
                <p className="flex items-center gap-1 text-muted-foreground">
                    <MapPin className="size-3" />
                    {pin ? (
                        <span>
                            Coordenadas:{' '}
                            <code>
                                {pin.lat.toFixed(7)},{pin.lng.toFixed(7)}
                            </code>
                        </span>
                    ) : (
                        <span>Haz click en el mapa para colocar el pin.</span>
                    )}
                </p>
                <p className="flex items-center gap-1 text-muted-foreground">
                    <span className="font-medium">Cerca de:</span>
                    {hintLoading && <Loader2 className="size-3 animate-spin" />}
                    <span className="truncate">{hint}</span>
                </p>
            </div>

            <DialogFooter className="gap-2 border-t px-6 pt-3 pb-6 sm:gap-2">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Cancelar
                </Button>
                <Button
                    type="button"
                    disabled={confirmDisabled}
                    onClick={() => {
                        if (pin) onConfirm(pin);
                    }}
                >
                    Confirmar
                </Button>
            </DialogFooter>
        </>
    );
}

function ClickToDropPin({
    onPinChange,
}: {
    onPinChange: (ll: LatLng) => void;
}) {
    useMapEvents({
        click(e) {
            onPinChange({ lat: e.latlng.lat, lng: e.latlng.lng });
        },
    });
    return null;
}

function RecenterMap({ target }: { target: LatLng }) {
    const map = useMap();
    useEffect(() => {
        // Pan smoothly to the new pin without changing zoom unless we
        // were below 14 (e.g. the user just dropped the first pin from
        // a country-level zoom).
        const targetZoom = Math.max(map.getZoom(), 14);
        map.flyTo([target.lat, target.lng], targetZoom, { duration: 0.5 });
    }, [map, target.lat, target.lng]);
    return null;
}
