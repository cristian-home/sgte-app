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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dlog } from '@/lib/debug-log';
import { MAPBOX_ATTRIBUTION, mapboxTileUrl } from '@/lib/mapbox';
import { type GeocodingFeature, reverseGeocode } from '@/lib/mapbox-geocoding';
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
    /**
     * Pre-populates the editable address field inside the modal — the
     * operator's existing draft, which they can refine before confirm.
     * Whatever they leave in the field at confirm time becomes the
     * persisted address text for the form.
     */
    addressHint: string;
    municipalityHint: string | null;
    /**
     * Returns the chosen coordinates, the address text the operator
     * typed inside the modal, and — when Mapbox could reverse-geocode
     * the pin — the city name detected via the `place` context. The
     * form uses `coords` + `address` together as a single intentional
     * commit, and `placeName` to fuzzy-match against the DANE catalog
     * so the LocationField chip can auto-populate when the operator
     * dropped a pin without selecting a municipality first.
     */
    onConfirm: (data: {
        coords: LatLng;
        address: string;
        placeName: string | null;
    }) => void;
    /** Used as a debug-log channel suffix to distinguish origin vs destination. */
    instanceLabel?: string;
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
    instanceLabel,
}: MapPickerModalProps) {
    const channel = `map-picker:${instanceLabel ?? 'default'}`;
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                dlog(channel, next ? 'open' : 'close', {
                    addressHint,
                    municipalityHint,
                    initialCenter,
                    initialPin,
                });
                onOpenChange(next);
            }}
        >
            <DialogContent
                className={cn(
                    'sm:max-w-3xl',
                    'flex flex-col gap-3 p-0',
                    'h-[90vh] sm:h-[80vh]',
                )}
            >
                {open ? (
                    <MapPickerBody
                        channel={channel}
                        initialCenter={initialCenter}
                        initialPin={initialPin}
                        addressHint={addressHint}
                        municipalityHint={municipalityHint}
                        onCancel={() => {
                            dlog(channel, 'cancel');
                            onOpenChange(false);
                        }}
                        onConfirm={(data) => {
                            dlog(channel, 'confirm', data);
                            onConfirm(data);
                        }}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

interface BodyProps {
    channel: string;
    initialCenter: LatLng | null;
    initialPin: LatLng | null;
    addressHint: string;
    municipalityHint: string | null;
    onCancel: () => void;
    onConfirm: (data: {
        coords: LatLng;
        address: string;
        placeName: string | null;
    }) => void;
}

function MapPickerBody({
    channel,
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
    // Editable mirror of the form's address input. Pre-populated with
    // whatever the operator already had typed; confirming the modal
    // pushes this value back as the canonical address text.
    const [addressDraft, setAddressDraft] = useState<string>(addressHint);
    // Last reverse-geocode feature, kept so we can extract
    // `properties.context.place.name` on confirm — the form uses it to
    // auto-populate the city chip when the operator hasn't selected one.
    const [lastReverseFeature, setLastReverseFeature] =
        useState<GeocodingFeature | null>(null);

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
        dlog(channel, 'reverse scheduled', {
            lat: next.lat,
            lng: next.lng,
            debounce_ms: REVERSE_DEBOUNCE_MS,
        });
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
                    // Include `place` so the response context exposes
                    // the city name (`properties.context.place.name`),
                    // used on confirm to auto-populate the form chip.
                    types: 'address,street,place',
                    limit: 1,
                },
                ac.signal,
            )
                .then((res) => {
                    if (ac.signal.aborted) return;
                    const f = res.features?.[0] ?? null;
                    const text =
                        f?.properties.full_address ??
                        f?.properties.place_formatted ??
                        f?.properties.name ??
                        '—';
                    dlog(channel, 'reverse hint', {
                        text,
                        placeName: f?.properties.context?.place?.name ?? null,
                    });
                    setHint(text);
                    setLastReverseFeature(f);
                    setHintLoading(false);
                })
                .catch((err) => {
                    if ((err as { name?: string }).name === 'AbortError')
                        return;
                    dlog(channel, 'reverse error', {
                        error: (err as Error).message,
                    });
                    setHint('—');
                    setLastReverseFeature(null);
                    setHintLoading(false);
                });
        }, REVERSE_DEBOUNCE_MS);
    };

    const handlePinChange = (next: LatLng, source: 'click' | 'drag') => {
        dlog(channel, `pin ${source}`, { lat: next.lat, lng: next.lng });
        setPin(next);
        scheduleReverse(next);
    };

    const confirmDisabled = pin === null;

    return (
        <>
            <DialogHeader className="px-6 pt-6">
                <DialogTitle>Marcar ubicación en el mapa</DialogTitle>
                <DialogDescription>
                    Coloca el pin donde el conductor debe parar y escribe abajo
                    la dirección que verá en su panel.
                    {municipalityHint && (
                        <span className="mt-1 block">
                            <strong>Municipio:</strong> {municipalityHint}
                        </span>
                    )}
                </DialogDescription>
            </DialogHeader>

            <div className="space-y-1 px-6">
                <Label htmlFor="map-picker-address">
                    Dirección visible para el conductor
                </Label>
                <div className="flex items-stretch gap-2">
                    <Input
                        id="map-picker-address"
                        value={addressDraft}
                        onChange={(e) => setAddressDraft(e.target.value)}
                        placeholder="Ej: Calle 41A Sur #83-17, Casa de don Pepe…"
                        autoComplete="off"
                        spellCheck={false}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!hint || hint === '—'}
                        onClick={() => setAddressDraft(hint)}
                        title="Usar la dirección que sugiere el mapa como texto"
                        className="shrink-0"
                    >
                        Usar sugerencia
                    </Button>
                </div>
            </div>

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
                            detectRetina
                        />
                        <ClickToDropPin
                            onPinChange={(ll) => handlePinChange(ll, 'click')}
                        />
                        {pin && (
                            <Marker
                                position={[pin.lat, pin.lng]}
                                draggable
                                eventHandlers={{
                                    dragend: (e) => {
                                        const m = e.target as L.Marker;
                                        const ll = m.getLatLng();
                                        handlePinChange(
                                            { lat: ll.lat, lng: ll.lng },
                                            'drag',
                                        );
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
                        if (pin) {
                            onConfirm({
                                coords: pin,
                                address: addressDraft.trim(),
                                placeName:
                                    lastReverseFeature?.properties.context
                                        ?.place?.name ?? null,
                            });
                        }
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
